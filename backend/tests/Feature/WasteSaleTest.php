<?php

namespace Tests\Feature;

use App\Models\Customer;
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

    public function test_all_authenticated_users_can_view_waste_sales(): void
    {
        $invoice = $this->invoice();
        $invoice->update([
            'waste_sale_amount' => 50,
            'waste_sale_recorded_at' => '2026-10-08',
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

    public function test_staff_can_record_and_update_a_waste_sale(): void
    {
        $staff = User::factory()->create(['role' => 'normal_staff']);
        Sanctum::actingAs($staff);
        $invoice = $this->invoice();

        $this->patchJson("/api/waste-sales/{$invoice->id}", [
            'amount' => 125.50,
            'remark' => 'Sold recyclable material.',
            'received_date' => '2026-10-07',
        ])->assertOk()->assertJsonFragment([
            'waste_sale_amount' => '125.50',
            'waste_sale_remark' => 'Sold recyclable material.',
            'waste_sale_recorded_by' => $staff->id,
        ]);

        $this->patchJson("/api/waste-sales/{$invoice->id}", [
            'amount' => 150,
            'remark' => null,
            'received_date' => '2026-10-08',
        ])->assertOk()->assertJsonFragment([
            'waste_sale_amount' => '150.00',
            'waste_sale_remark' => null,
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'subject_id' => $invoice->id,
            'action' => 'waste_sale_recorded',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'subject_id' => $invoice->id,
            'action' => 'waste_sale_updated',
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
            'received_date' => '2026-10-08',
        ])->assertUnprocessable()->assertJsonValidationErrors('amount');
    }

    public function test_waste_page_only_contains_recorded_sales_and_filters_received_date(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'normal_staff']));
        $included = $this->invoice();
        $included->update([
            'waste_sale_amount' => 88,
            'waste_sale_recorded_at' => '2026-10-08',
        ]);
        $excluded = $this->invoice();

        $this->getJson('/api/waste-sales?from_date=2026-10-08&to_date=2026-10-08')
            ->assertOk()
            ->assertJsonFragment(['id' => $included->id])
            ->assertJsonMissing(['id' => $excluded->id]);
    }
}
