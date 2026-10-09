<?php

namespace Tests\Feature;

use App\Models\Barrel;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BarrelIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_barrels_are_naturally_sorted_and_paginated_by_thirty(): void
    {
        $user = User::factory()->create(['role' => 'normal_staff']);
        Sanctum::actingAs($user);

        foreach (range(1, 25) as $code) {
            Barrel::create([
                'code' => (string) $code,
                'status' => 'available',
            ]);
        }

        foreach (range(1, 10) as $code) {
            Barrel::create([
                'code' => str_pad((string) $code, 3, '0', STR_PAD_LEFT),
                'status' => 'available',
            ]);
        }

        $response = $this->getJson('/api/barrels')->assertOk();

        $this->assertSame(30, $response->json('per_page'));
        $this->assertSame(35, $response->json('total'));
        $this->assertSame(
            [
                ...array_map(
                    fn (int $code) => str_pad((string) $code, 3, '0', STR_PAD_LEFT),
                    range(1, 10)
                ),
                ...array_map('strval', range(1, 20)),
            ],
            array_column($response->json('data'), 'code')
        );
    }

    public function test_rented_barrel_cannot_be_manually_set_available(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'normal_staff']));
        $customer = Customer::create(['name' => 'Flow Customer']);
        $barrel = Barrel::create([
            'code' => 'FLOW-001',
            'status' => 'rented',
            'current_customer_id' => $customer->id,
        ]);
        $invoice = Invoice::create([
            'invoice_no' => 'TKS-FLOW-001',
            'customer_id' => $customer->id,
            'issued_date' => now()->toDateString(),
            'status' => 'paid',
        ]);
        $item = $invoice->items()->create([
            'barrel_id' => $barrel->id,
            'rental_start' => now()->toDateString(),
        ]);

        $this->patchJson("/api/barrels/{$barrel->id}", [
            'status' => 'available',
        ])->assertUnprocessable()->assertJsonValidationErrors('status');

        $this->assertNull($item->fresh()->rental_end);
        $this->getJson('/api/barrels?search=FLOW-001')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'rented')
            ->assertJsonPath('data.0.current_customer_id', $customer->id)
            ->assertJsonPath('data.0.invoice_id', $invoice->id)
            ->assertJsonPath('data.0.invoice_no', 'TKS-FLOW-001');
    }

    public function test_rented_barrel_shows_latest_invoice_even_with_planned_end_date(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'normal_staff']));
        $customer = Customer::create(['name' => 'Current Customer']);
        $barrel = Barrel::create([
            'code' => 'FLOW-002',
            'status' => 'rented',
            'current_customer_id' => $customer->id,
        ]);
        $invoice = Invoice::create([
            'invoice_no' => 'TKS-FLOW-002',
            'customer_id' => $customer->id,
            'issued_date' => now()->toDateString(),
            'status' => 'paid',
        ]);
        $invoice->items()->create([
            'barrel_id' => $barrel->id,
            'rental_start' => now()->toDateString(),
            'rental_end' => now()->addDays(14)->toDateString(),
        ]);

        $this->getJson('/api/barrels?search=FLOW-002')
            ->assertOk()
            ->assertJsonPath('data.0.status', 'rented')
            ->assertJsonPath('data.0.invoice_id', $invoice->id)
            ->assertJsonPath('data.0.invoice_no', 'TKS-FLOW-002')
            ->assertJsonPath('data.0.current_customer.name', 'Current Customer');
    }

    public function test_available_barrel_cannot_be_manually_set_in_transit(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'normal_staff']));
        $barrel = Barrel::create([
            'code' => 'FLOW-TRANSIT-GUARD',
            'status' => 'available',
        ]);

        $this->patchJson("/api/barrels/{$barrel->id}", [
            'status' => 'returning',
        ])->assertUnprocessable()->assertJsonValidationErrors('status');

        $this->assertDatabaseHas('barrels', [
            'id' => $barrel->id,
            'status' => 'available',
        ]);
    }

    public function test_in_transit_barrel_can_be_marked_available_but_not_rented(): void
    {
        Sanctum::actingAs(User::factory()->create(['role' => 'normal_staff']));
        $barrel = Barrel::create([
            'code' => 'FLOW-TRANSIT-AVAILABLE',
            'status' => 'returning',
        ]);

        $this->patchJson("/api/barrels/{$barrel->id}", [
            'status' => 'rented',
        ])->assertUnprocessable()->assertJsonValidationErrors('status');

        $this->patchJson("/api/barrels/{$barrel->id}", [
            'status' => 'available',
        ])->assertOk()->assertJsonPath('status', 'available');
    }

	public function test_in_transit_barrel_falls_back_to_latest_invoice_customer(): void
	{
		Sanctum::actingAs(User::factory()->create(['role' => 'normal_staff']));
		$customer = Customer::create(['name' => 'Transit Customer']);
		$barrel = Barrel::create([
			'code' => 'FLOW-003',
			'status' => 'returning',
			'current_customer_id' => null,
		]);
		$invoice = Invoice::create([
			'invoice_no' => 'TKS-FLOW-003',
			'customer_id' => $customer->id,
			'issued_date' => now()->toDateString(),
			'status' => 'paid',
		]);
		$invoice->items()->create([
			'barrel_id' => $barrel->id,
			'rental_start' => now()->toDateString(),
		]);

		$this->getJson('/api/barrels?search=FLOW-003')
			->assertOk()
			->assertJsonPath('data.0.invoice_no', 'TKS-FLOW-003')
			->assertJsonPath('data.0.current_customer.name', 'Transit Customer');
	}
}
