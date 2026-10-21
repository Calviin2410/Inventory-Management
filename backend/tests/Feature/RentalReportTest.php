<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Barrel;
use App\Models\Invoice;
use App\Models\InvoiceItem;
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

    public function test_normal_staff_can_access_rental_reports(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'normal_staff']));

        $this->getJson('/api/reports/rentals')
            ->assertOk()
            ->assertJsonPath('summary.total_invoices', 0);
    }

    public function test_rental_report_search_matches_invoice_customer_and_barrel(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $customer = Customer::create(['name' => 'Searchable Customer']);
        $barrel = Barrel::create(['code' => 'BARREL-SEARCH-42']);
        $invoice = Invoice::create([
            'invoice_no' => 'TKS-SEARCH-001',
            'customer_id' => $customer->id,
            'issued_date' => '2026-10-08',
            'status' => 'unpaid',
            'settlement_status' => 'unsettled',
        ]);
        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'barrel_id' => $barrel->id,
            'rental_start' => '2026-10-08',
        ]);

        foreach (['TKS-SEARCH', 'Searchable Customer', 'BARREL-SEARCH'] as $term) {
            $this->getJson('/api/reports/rentals?search='.urlencode($term))
                ->assertOk()
                ->assertJsonPath('total', 1)
                ->assertJsonPath('data.0.id', $invoice->id);
        }
    }
}
