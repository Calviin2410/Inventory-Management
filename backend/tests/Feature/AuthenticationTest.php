<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_login_issues_a_short_lived_token(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
            'remember' => false,
        ])->assertOk()->assertJsonStructure(['user', 'token']);

        $expiresAt = $user->tokens()->latest('id')->firstOrFail()->expires_at;

        $this->assertTrue($expiresAt->between(now()->addHours(11), now()->addHours(13)));
    }

    public function test_remembered_login_issues_a_thirty_day_token(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
            'remember' => true,
        ])->assertOk()->assertJsonStructure(['user', 'token']);

        $expiresAt = $user->tokens()->latest('id')->firstOrFail()->expires_at;

        $this->assertTrue($expiresAt->between(now()->addDays(29), now()->addDays(31)));
    }
}
