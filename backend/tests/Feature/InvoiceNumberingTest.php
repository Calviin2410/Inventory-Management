<?php

namespace Tests\Feature;

use App\Models\Barrel;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvoiceNumberingTest extends TestCase
{
    use RefreshDatabase;

    public function test_next_invoice_number_uses_the_smallest_missing_number(): void
    {
        $customer = Customer::create(['name' => 'Numbering Customer']);

        foreach ([1, 3, 4, 5, 6, 7, 8, 9, 10] as $number) {
            Invoice::create([
                'invoice_no' => 'TKS'.str_pad((string) $number, 5, '0', STR_PAD_LEFT),
                'customer_id' => $customer->id,
                'issued_date' => '2026-10-09',
            ]);
        }

        Sanctum::actingAs(User::factory()->create(['role' => 'normal_staff']));

        $this->getJson('/api/invoices-next-number')
            ->assertOk()
            ->assertJsonPath('invoice_no', 'TKS00002');
    }

    public function test_c_and_tks_invoice_numbers_are_generated_and_filtered_independently(): void
    {
        $customer = Customer::create(['name' => 'Prefix Customer']);
        foreach (['TKS00001', 'C00001', 'C00003'] as $invoiceNo) {
            Invoice::create([
                'invoice_no' => $invoiceNo,
                'customer_id' => $customer->id,
                'issued_date' => '2026-10-09',
            ]);
        }

        Sanctum::actingAs(User::factory()->create(['role' => 'normal_staff']));

        $this->getJson('/api/invoices-next-number?prefix=C')
            ->assertOk()
            ->assertJsonPath('invoice_no', 'C00002');

        $this->getJson('/api/invoices?prefix=C')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.invoice_no', 'C00003')
            ->assertJsonPath('data.1.invoice_no', 'C00001');

        $this->getJson('/api/invoices?prefix=TKS')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.invoice_no', 'TKS00001');
    }

    public function test_invoice_can_be_created_with_the_c_prefix(): void
    {
        $customer = Customer::create(['name' => 'Prefix Customer']);
        $barrel = Barrel::create(['code' => 'PREFIX-A-001', 'status' => 'available']);
        Sanctum::actingAs(User::factory()->create(['role' => 'normal_staff']));

        $this->postJson('/api/invoices', [
            'invoice_prefix' => 'C',
            'customer_id' => $customer->id,
            'address' => '1 Test Street',
            'items' => [[
                'barrel_id' => $barrel->id,
                'rental_start' => '2026-10-09',
            ]],
        ])->assertCreated()->assertJsonPath('invoice_no', 'C00001');

        $this->getJson('/api/invoices-next-number?prefix=TKS')
            ->assertOk()
            ->assertJsonPath('invoice_no', 'TKS00001');
    }

    public function test_invoice_creation_fills_a_gap_then_continues_after_the_maximum(): void
    {
        $customer = Customer::create(['name' => 'Numbering Customer']);
        foreach ([1, 3, 4] as $number) {
            Invoice::create([
                'invoice_no' => 'TKS'.str_pad((string) $number, 5, '0', STR_PAD_LEFT),
                'customer_id' => $customer->id,
                'issued_date' => '2026-10-09',
            ]);
        }

        $barrel = Barrel::create(['code' => 'NUM-001', 'status' => 'available']);
        Sanctum::actingAs(User::factory()->create(['role' => 'normal_staff']));

        $this->postJson('/api/invoices', [
            'customer_id' => $customer->id,
            'address' => '1 Test Street',
            'items' => [[
                'barrel_id' => $barrel->id,
                'rental_start' => '2026-10-09',
            ]],
        ])->assertCreated()->assertJsonPath('invoice_no', 'TKS00002');

        $this->assertDatabaseHas('barrels', [
            'id' => $barrel->id,
            'status' => 'rented',
        ]);
        $createdLog = ActivityLog::query()
            ->where('subject_label', 'TKS00002')
            ->where('action', 'created')
            ->firstOrFail();
        $this->assertSame('available', $createdLog->old_values['barrel_NUM-001_status']);
        $this->assertSame('rented', $createdLog->new_values['barrel_NUM-001_status']);

        $this->getJson('/api/invoices-next-number')
            ->assertOk()
            ->assertJsonPath('invoice_no', 'TKS00005');
    }

    public function test_invoice_list_sorts_invoice_numbers_numerically_in_both_directions(): void
    {
        $customer = Customer::create(['name' => 'Numbering Customer']);
        foreach ([10, 2, 1] as $number) {
            Invoice::create([
                'invoice_no' => 'TKS'.str_pad((string) $number, 5, '0', STR_PAD_LEFT),
                'customer_id' => $customer->id,
                'issued_date' => '2026-10-09',
            ]);
        }

        Sanctum::actingAs(User::factory()->create(['role' => 'normal_staff']));

        $this->getJson('/api/invoices?sort=invoice_no&direction=asc')
            ->assertOk()
            ->assertJsonPath('data.0.invoice_no', 'TKS00001')
            ->assertJsonPath('data.1.invoice_no', 'TKS00002')
            ->assertJsonPath('data.2.invoice_no', 'TKS00010');

        $this->getJson('/api/invoices?sort=invoice_no&direction=desc')
            ->assertOk()
            ->assertJsonPath('data.0.invoice_no', 'TKS00010')
            ->assertJsonPath('data.1.invoice_no', 'TKS00002')
            ->assertJsonPath('data.2.invoice_no', 'TKS00001');
    }
}
