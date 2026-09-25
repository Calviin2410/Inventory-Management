<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Barrel;
use App\Models\Invoice;
use App\Models\User;
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

        $this->patchJson("/api/invoices/{$invoice->id}", ['status' => 'unpaid'])
            ->assertOk()
            ->assertJsonPath('payment_method', null)
            ->assertJsonPath('payment_date', null);
    }

    public function test_staff_can_view_an_invoice(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'normal_staff']));
        $invoice = $this->invoice();

        $this->getJson("/api/invoices/{$invoice->id}")
            ->assertOk()
            ->assertJsonFragment(['invoice_no' => 'KT09999']);
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
}
