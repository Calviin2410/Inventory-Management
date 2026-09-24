<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SettlementTest extends TestCase
{
    use RefreshDatabase;

    private function invoice(array $attributes = []): Invoice
    {
        return Invoice::create(array_merge([
            'invoice_no' => 'KT08888',
            'customer_id' => Customer::create(['name' => 'Settlement Customer'])->id,
            'issued_date' => now('Asia/Kuala_Lumpur')->toDateString(),
            'status' => 'unpaid',
        ], $attributes));
    }

    public function test_admin_sees_today_invoices_by_default(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $today = $this->invoice();
        $old = $this->invoice(['invoice_no' => 'KT07777']);
        $old->forceFill(['created_at' => now()->subDays(2)])->save();

        $this->getJson('/api/settlements')
            ->assertOk()
            ->assertJsonFragment(['id' => $today->id, 'invoice_no' => 'KT08888'])
            ->assertJsonMissing(['invoice_no' => 'KT07777']);
    }

    public function test_admin_can_view_a_previous_date_range(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $oldDate = now('Asia/Kuala_Lumpur')->subDays(2);
        $old = $this->invoice(['invoice_no' => 'KT06666']);
        $old->forceFill([
            'created_at' => $oldDate->copy()->setTime(12, 0)->utc(),
        ])->save();

        $date = $oldDate->toDateString();
        $this->getJson("/api/settlements?from={$date}&to={$date}")
            ->assertOk()
            ->assertJsonFragment(['invoice_no' => 'KT06666']);
    }

    public function test_non_admin_cannot_view_or_update_settlements(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'normal_staff']));
        $invoice = $this->invoice();

        $this->getJson('/api/settlements')->assertForbidden();
        $this->patchJson("/api/settlements/{$invoice->id}", [
            'action' => 'settle',
            'remark' => 'Not allowed.',
        ])->assertForbidden();
    }

    public function test_remark_is_required_to_settle(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $invoice = $this->invoice();

        $this->patchJson("/api/settlements/{$invoice->id}", [
            'action' => 'settle',
            'remark' => '',
        ])->assertUnprocessable()->assertJsonValidationErrors('remark');
    }

    public function test_admin_can_settle_and_reopen_with_remarks(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);
        $invoice = $this->invoice();

        $this->patchJson("/api/settlements/{$invoice->id}", [
            'action' => 'settle',
            'remark' => 'Daily records checked.',
        ])->assertOk()->assertJsonFragment([
            'settlement_status' => 'settled',
            'settlement_remark' => 'Daily records checked.',
            'settled_by' => $admin->id,
        ]);

        $this->patchJson("/api/settlements/{$invoice->id}", [
            'action' => 'reopen',
            'remark' => 'Correction is required.',
        ])->assertOk()->assertJsonFragment([
            'settlement_status' => 'unsettled',
            'settlement_remark' => 'Correction is required.',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'subject_id' => $invoice->id,
            'action' => 'settled',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'subject_id' => $invoice->id,
            'action' => 'reopened',
        ]);
    }
}
