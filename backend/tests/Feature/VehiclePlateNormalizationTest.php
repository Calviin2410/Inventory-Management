<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VehiclePlateNormalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_plate_duplicates_ignore_spaces_and_letter_case(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $this->postJson('/api/vehicles', [
            'plate_number' => 'JQK 1234',
        ])->assertCreated();

        $this->postJson('/api/vehicles', [
            'plate_number' => 'jqk1234',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('plate_number_normalized');

        $this->assertDatabaseCount('vehicles', 1);
        $this->assertDatabaseHas('vehicles', [
            'plate_number' => 'JQK 1234',
            'plate_number_normalized' => 'JQK1234',
        ]);
    }

    public function test_extra_spaces_are_cleaned_for_display(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $this->postJson('/api/vehicles', [
            'plate_number' => '  kv   1   a  ',
        ])->assertCreated()
            ->assertJsonFragment(['plate_number' => 'KV 1 A'])
            ->assertJsonMissingPath('plate_number_normalized');
    }
}
