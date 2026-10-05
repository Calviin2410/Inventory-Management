<?php

namespace Tests\Feature;

use App\Models\Barrel;
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
}
