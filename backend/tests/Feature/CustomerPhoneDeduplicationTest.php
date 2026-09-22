<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerPhoneDeduplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_phone_format_reuses_existing_customer(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $original = $this->postJson('/api/customers', [
            'name' => 'John Tan',
            'phone' => '012-345 6789',
        ])->assertCreated()->json();

        $matched = $this->postJson('/api/customers', [
            'name' => 'Different Name',
            'phone' => '+60 12-345 6789',
        ])
            ->assertOk()
            ->assertJsonPath('already_exists', true)
            ->json();

        $this->assertSame($original['id'], $matched['id']);
        $this->assertSame('John Tan', $matched['name']);
        $this->assertSame(1, Customer::count());
    }

    public function test_customer_can_be_found_by_normalized_phone(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $customer = Customer::create([
            'name' => 'ABC Trading',
            'phone' => '03-1234 5678',
            'phone_normalized' => '0312345678',
        ]);

        $this->getJson('/api/customers?phone_exact=0312345678')
            ->assertOk()
            ->assertJsonPath('data.0.id', $customer->id);
    }
}
