<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Barrel;
use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WasteSaleTest extends TestCase
{
    use RefreshDatabase;

    private function invoice(): Invoice
    {
        return Invoice::create([
            'invoice_no' => 'TKS'.str_pad((string) (Invoice::count() + 9000), 5, '0', STR_PAD_LEFT),
            'customer_id' => Customer::create(['name' => 'Waste Customer'])->id,
            'issued_date' => now()->toDateString(),
            'status' => 'paid',
        ]);
    }

    public function test_all_authenticated_users_can_view_waste_sells(): void
    {
        $invoice = $this->invoice();
        $invoice->update([
            'waste_sell_amount' => 50,
            'waste_sell_recorded_at' => '2026-10-08',
        ]);

        foreach (['admin', 'normal_staff'] as $role) {
            Sanctum::actingAs(User::factory()->create(['role' => $role]));
            $this->getJson('/api/waste-sales')
                ->assertOk()
                ->assertJsonFragment([
                    'id' => $invoice->id,
                    'invoice_no' => $invoice->invoice_no,
                ]);
        }
    }

    public function test_staff_can_record_and_update_a_waste_sell(): void
    {
        $staff = User::factory()->create(['role' => 'normal_staff']);
        Sanctum::actingAs($staff);
        $invoice = $this->invoice();
        $invoice->update(['user_id' => $staff->id]);
        $barrel = Barrel::create(['code' => 'WASTE-001', 'status' => 'rented']);
        $invoice->items()->create([
            'barrel_id' => $barrel->id,
            'rental_start' => now()->toDateString(),
            'rental_end' => now()->addDays(14)->toDateString(),
        ]);

        $this->patchJson("/api/waste-sales/{$invoice->id}", [
            'amount' => 125.50,
            'remark' => 'Sold recyclable material.',
        ])->assertOk()->assertJsonFragment([
            'waste_sell_amount' => '125.50',
            'waste_sell_remark' => 'Sold recyclable material.',
        ])->assertJsonPath('waste_sell_recorded_by.id', $staff->id)
            ->assertJsonPath('customer.name', 'Waste Customer')
            ->assertJsonPath('created_by.name', $staff->name)
            ->assertJsonMissingPath('waste_sale_recorded_by');

        $this->patchJson("/api/waste-sales/{$invoice->id}", [
            'amount' => 150,
            'remark' => null,
        ])->assertOk()->assertJsonFragment([
            'waste_sell_amount' => '150.00',
            'waste_sell_remark' => null,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'subject_id' => $invoice->id,
            'action' => 'waste_sell_recorded',
        ]);
        $recordedLog = ActivityLog::query()
            ->where('subject_id', $invoice->id)
            ->where('action', 'waste_sell_recorded')
            ->firstOrFail();
        $this->assertSame('rented', $recordedLog->old_values['barrel_WASTE-001_status']);
        $this->assertSame('returning', $recordedLog->new_values['barrel_WASTE-001_status']);
        $this->assertDatabaseHas('barrels', [
            'id' => $barrel->id,
            'status' => 'returning',
			'current_customer_id' => $invoice->customer_id,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'subject_id' => $invoice->id,
            'action' => 'waste_sell_updated',
        ]);
    }

    public function test_amount_is_required_and_cannot_be_negative(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'normal_staff']));
        $invoice = $this->invoice();

        $this->patchJson("/api/waste-sales/{$invoice->id}", [
            'remark' => 'Missing amount.',
        ])->assertUnprocessable()->assertJsonValidationErrors('amount');

        $this->patchJson("/api/waste-sales/{$invoice->id}", [
            'amount' => -1,
        ])->assertUnprocessable()->assertJsonValidationErrors('amount');
    }

    public function test_waste_page_only_contains_recorded_sales_and_filters_received_date(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'normal_staff']));
        $included = $this->invoice();
        $included->update([
            'waste_sell_amount' => 88,
            'waste_sell_recorded_at' => '2026-10-08',
        ]);
        $excluded = $this->invoice();

        $this->getJson('/api/waste-sales?from_date=2026-10-08&to_date=2026-10-08')
            ->assertOk()
            ->assertJsonPath('per_page', 20)
            ->assertJsonPath(
                'total_amount_received',
                fn ($amount) => (float) $amount === 88.0
            )
            ->assertJsonFragment(['id' => $included->id])
            ->assertJsonMissing(['id' => $excluded->id]);
    }

    public function test_waste_page_can_filter_by_received_by_user(): void
    {
        $receiver = User::factory()->create(['name' => 'Receiver A']);
        $otherReceiver = User::factory()->create(['name' => 'Receiver B']);
        $included = $this->invoice();
        $included->update([
            'waste_sell_amount' => 88,
            'waste_sell_recorded_at' => '2026-10-08',
            'waste_sell_recorded_by' => $receiver->id,
        ]);
        $excluded = $this->invoice();
        $excluded->update([
            'waste_sell_amount' => 30,
            'waste_sell_recorded_at' => '2026-10-08',
            'waste_sell_recorded_by' => $otherReceiver->id,
        ]);

        Sanctum::actingAs(User::factory()->create(['role' => 'normal_staff']));

        $this->getJson('/api/waste-sales?received_by='.$receiver->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $included->id)
            ->assertJsonPath(
                'total_amount_received',
                fn ($amount) => (float) $amount === 88.0
            )
            ->assertJsonCount(2, 'received_by_options');
    }

    public function test_waste_page_can_filter_invoices_by_waste_sale_status(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'normal_staff']));

        $unsold = $this->invoice();
        $sold = $this->invoice();
        $sold->update(['waste_sell_amount' => 0]);

        $this->getJson('/api/waste-sales?waste_status=unsold')
            ->assertOk()
            ->assertJsonFragment(['id' => $unsold->id])
            ->assertJsonMissing(['id' => $sold->id]);

        $this->getJson('/api/waste-sales?waste_status=sold')
            ->assertOk()
            ->assertJsonFragment(['id' => $sold->id])
            ->assertJsonMissing(['id' => $unsold->id]);
    }

    public function test_waste_page_includes_invoices_without_a_recorded_sale(): void
    {
        $unrecorded = $this->invoice();
        $recorded = $this->invoice();
        $recorded->update([
            'waste_sell_amount' => 42.50,
            'waste_sell_recorded_at' => '2026-10-08',
        ]);
        Sanctum::actingAs(User::factory()->create(['role' => 'normal_staff']));

        $this->getJson('/api/waste-sales')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['id' => $unrecorded->id])
            ->assertJsonPath(
                'total_amount_received',
                fn ($amount) => (float) $amount === 42.5
            );
    }

    public function test_staff_cannot_record_a_waste_sale_for_an_invoice_they_did_not_create(): void
    {
        $invoice = $this->invoice();
        Sanctum::actingAs(User::factory()->create(['role' => 'normal_staff']));

        $this->patchJson("/api/waste-sales/{$invoice->id}", [
            'amount' => 25,
        ])->assertForbidden();
    }
}
