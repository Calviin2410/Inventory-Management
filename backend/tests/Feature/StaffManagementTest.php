<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_first_created_admin_can_see_other_admin_accounts(): void
    {
        $earlierAdmin = User::factory()->create(['role' => 'admin']);
        $laterAdmin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'normal_staff']);

        Sanctum::actingAs($earlierAdmin);

        $this->getJson('/api/staff')
            ->assertOk()
            ->assertJsonFragment(['id' => $staff->id])
            ->assertJsonFragment(['id' => $laterAdmin->id, 'role' => 'admin'])
            ->assertJsonFragment(['id' => $earlierAdmin->id, 'role' => 'admin']);

        Sanctum::actingAs($laterAdmin);

        $this->getJson('/api/staff')
            ->assertOk()
            ->assertJsonFragment(['id' => $laterAdmin->id, 'role' => 'admin'])
            ->assertJsonMissing(['id' => $earlierAdmin->id, 'role' => 'admin'])
            ->assertJsonFragment(['id' => $staff->id]);
    }

    public function test_non_admin_cannot_manage_staff_accounts(): void
    {
        $staff = User::factory()->create(['role' => 'normal_staff']);
        Sanctum::actingAs($staff);

        $this->getJson('/api/staff')->assertForbidden();
        $this->postJson('/api/staff', [
            'name' => 'New Staff',
            'email' => 'staff@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertForbidden();
        $this->patchJson("/api/staff/{$staff->id}/password", [
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertForbidden();
        $this->deleteJson("/api/staff/{$staff->id}")->assertForbidden();
    }

    public function test_admin_can_create_a_staff_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Sanctum::actingAs($admin);

        $this->postJson('/api/staff', [
            'name' => 'Mei Staff',
            'email' => 'mei.staff@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertCreated()->assertJsonFragment([
            'name' => 'Mei Staff',
            'role' => 'normal_staff',
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'mei.staff@example.com',
            'role' => 'normal_staff',
        ]);
    }

    public function test_public_registration_endpoint_is_not_available(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Public User',
            'email' => 'public@example.com',
            'password' => 'Password123!',
        ])->assertNotFound();
    }

    public function test_admin_can_reset_staff_password_and_revoke_existing_tokens(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'normal_staff']);
        $staff->createToken('existing-session');

        Sanctum::actingAs($admin);

        $this->patchJson("/api/staff/{$staff->id}/password", [
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertOk();

        $staff->refresh();

        $this->assertTrue(Hash::check('NewPassword123!', $staff->password));
        $this->assertCount(0, $staff->tokens);
    }

    public function test_admin_can_delete_staff_but_cannot_delete_an_admin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'normal_staff']);
        Sanctum::actingAs($admin);

        $this->deleteJson("/api/staff/{$staff->id}")
            ->assertOk()
            ->assertJsonFragment(['message' => 'Staff account deleted successfully.']);

        $this->assertDatabaseMissing('users', ['id' => $staff->id]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'deleted',
            'subject_type' => 'Staff',
            'subject_id' => $staff->id,
        ]);

        $this->deleteJson("/api/staff/{$admin->id}")->assertNotFound();
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'role' => 'admin']);
    }
}
