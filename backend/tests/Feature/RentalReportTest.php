<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RentalReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_includes_settled_and_unsettled_invoice_counts(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));
        $customer = Customer::create(['name' => 'Report Customer']);

        Invoice::create([
            'invoice_no' => 'TKS-RPT-001',
            'customer_id' => $customer->id,
            'issued_date' => '2026-10-08',
            'status' => 'paid',
            'settlement_status' => 'settled',
        ]);
        Invoice::create([
            'invoice_no' => 'TKS-RPT-002',
            'customer_id' => $customer->id,
            'issued_date' => '2026-10-08',
            'status' => 'unpaid',
            'settlement_status' => 'unsettled',
        ]);

        $this->getJson('/api/reports/rentals')
            ->assertOk()
            ->assertJsonPath('summary.total_invoices', 2)
            ->assertJsonPath('summary.settled_invoices', 1)
            ->assertJsonPath('summary.unsettled_invoices', 1)
            ->assertJsonMissingPath('summary.total_customers');
    }
}
