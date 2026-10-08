<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Barrel;
use App\Models\Invoice;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvoicePaymentTest extends TestCase
{
    use RefreshDatabase;

    private function invoice(): Invoice
    {
        $customer = Customer::create(['name' => 'Payment Customer']);

        return Invoice::create([
            'invoice_no' => 'KT09999',
            'customer_id' => $customer->id,
            'issued_date' => '2026-09-23',
            'status' => 'unpaid',
        ]);
    }

    public function test_staff_can_mark_an_invoice_paid_with_payment_details(): void
    {
        $staff = User::factory()->create(['role' => 'normal_staff']);
        $invoice = $this->invoice();
        Sanctum::actingAs($staff);

        $this->patchJson("/api/invoices/{$invoice->id}", [
            'status' => 'paid',
            'payment_method' => 'bank_in',
            'payment_date' => '2026-09-23',
        ])->assertOk()->assertJsonFragment([
            'status' => 'paid',
            'payment_method' => 'bank_in',
            'payment_date' => '2026-09-23',
        ]);

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => 'paid',
            'payment_method' => 'bank_in',
            'payment_date' => '2026-09-23',
        ]);
    }

    public function test_payment_details_are_required_when_marking_paid(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'normal_staff']));
        $invoice = $this->invoice();

        $this->patchJson("/api/invoices/{$invoice->id}", ['status' => 'paid'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['payment_method', 'payment_date']);
    }

    public function test_staff_cannot_edit_invoice_management_fields(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'normal_staff']));
        $invoice = $this->invoice();

        $this->patchJson("/api/invoices/{$invoice->id}", ['address' => 'Changed'])
            ->assertForbidden();
    }

    public function test_setting_unpaid_clears_payment_details(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'normal_staff']));
        $invoice = $this->invoice();
        $invoice->update([
            'status' => 'paid',
            'payment_method' => 'cash',
            'payment_date' => '2026-09-23',
        ]);

        $this->patchJson("/api/invoices/{$invoice->id}", [
            'status' => 'unpaid',
            'unpaid_remark' => 'Payment was reversed by the customer.',
        ])
            ->assertOk()
            ->assertJsonPath('payment_method', null)
            ->assertJsonPath('payment_date', null);

        $this->assertDatabaseHas('activity_logs', [
            'subject_id' => $invoice->id,
            'action' => 'updated',
        ]);

        $log = \App\Models\ActivityLog::latest('id')->firstOrFail();
        $this->assertSame(
            'Payment was reversed by the customer.',
            $log->new_values['unpaid_remark']
        );
    }

    public function test_setting_unpaid_requires_a_remark(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'normal_staff']));
        $invoice = $this->invoice();
        $invoice->update(['status' => 'paid']);

        $this->patchJson("/api/invoices/{$invoice->id}", ['status' => 'unpaid'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('unpaid_remark');
    }

    public function test_staff_can_view_an_invoice(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'normal_staff']));
        $invoice = $this->invoice();

        $this->getJson("/api/invoices/{$invoice->id}")
            ->assertOk()
            ->assertJsonFragment(['invoice_no' => 'KT09999']);
    }

    public function test_invoice_responses_include_creator_and_vehicle(): void
    {
        $creator = User::factory()->create([
            'name' => 'Salesman A',
            'role' => 'normal_staff',
        ]);
        $vehicle = Vehicle::create([
            'plate_number' => 'VAB 1234',
            'plate_number_normalized' => 'VAB1234',
            'status' => 'available',
        ]);
        $invoice = $this->invoice();
        $invoice->update([
            'user_id' => $creator->id,
            'vehicle_id' => $vehicle->id,
        ]);

        Sanctum::actingAs($creator);

        $this->getJson('/api/invoices')
            ->assertOk()
            ->assertJsonPath('data.0.created_by.name', 'Salesman A')
            ->assertJsonPath('data.0.vehicle.plate_number', 'VAB 1234');

        $this->getJson("/api/invoices/{$invoice->id}")
            ->assertOk()
            ->assertJsonPath('created_by.name', 'Salesman A')
            ->assertJsonPath('vehicle.plate_number', 'VAB 1234');
    }

    public function test_admin_can_update_invoice_item_rental_dates(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $invoice = $this->invoice();
        $barrel = Barrel::create(['code' => 'DATE-001']);
        $item = $invoice->items()->create([
            'barrel_id' => $barrel->id,
            'rental_start' => '2026-09-25',
            'rental_end' => '2026-10-09',
        ]);

        $this->patchJson("/api/invoices/{$invoice->id}", [
            'items' => [[
                'id' => $item->id,
                'rental_start' => '2026-09-26',
                'rental_end' => '2026-10-12',
            ]],
        ])->assertOk();

        $this->assertDatabaseHas('invoice_items', [
            'id' => $item->id,
            'rental_start' => '2026-09-26',
            'rental_end' => '2026-10-12',
        ]);
    }

    public function test_admin_can_update_invoice_total_amount(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $invoice = $this->invoice();

        $this->patchJson("/api/invoices/{$invoice->id}", [
            'total_amount' => 325.50,
        ])->assertOk()->assertJsonPath('total_amount', '325.50');

        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'total_amount' => 325.50,
        ]);
    }

    public function test_staff_cannot_update_invoice_item_rental_dates(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'normal_staff']));
        $invoice = $this->invoice();
        $barrel = Barrel::create(['code' => 'DATE-002']);
        $item = $invoice->items()->create([
            'barrel_id' => $barrel->id,
            'rental_start' => '2026-09-25',
            'rental_end' => '2026-10-09',
        ]);

        $this->patchJson("/api/invoices/{$invoice->id}", [
            'items' => [[
                'id' => $item->id,
                'rental_start' => '2026-09-26',
                'rental_end' => '2026-10-12',
            ]],
        ])->assertForbidden();
    }

    public function test_admin_can_delete_an_invoice_and_release_its_barrel(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $invoice = $this->invoice();
        $barrel = Barrel::create([
            'code' => 'DELETE-001',
            'status' => 'rented',
            'current_customer_id' => $invoice->customer_id,
        ]);
        $invoice->items()->create([
            'barrel_id' => $barrel->id,
            'rental_start' => '2026-10-06',
            'rental_end' => null,
        ]);
        Sanctum::actingAs($admin);

        $this->deleteJson("/api/invoices/{$invoice->id}")
            ->assertOk()
            ->assertJsonFragment(['message' => 'Invoice deleted successfully.']);

        $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
        $this->assertDatabaseMissing('invoice_items', ['invoice_id' => $invoice->id]);
        $this->assertDatabaseHas('barrels', [
            'id' => $barrel->id,
            'status' => 'available',
            'current_customer_id' => null,
        ]);
    }

    public function test_staff_cannot_delete_an_invoice(): void
    {
        $staff = User::factory()->create(['role' => 'normal_staff']);
        $invoice = $this->invoice();
        Sanctum::actingAs($staff);

        $this->deleteJson("/api/invoices/{$invoice->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('invoices', ['id' => $invoice->id]);
    }
}
