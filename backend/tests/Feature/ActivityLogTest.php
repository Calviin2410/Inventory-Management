<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Barrel;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_reset_creates_safe_activity_log(): void
    {
        $admin = User::factory()->create([
            'name' => 'Calvin Admin',
            'role' => 'admin',
        ]);
        $staff = User::factory()->create([
            'name' => 'Alex Staff',
            'role' => 'normal_staff',
        ]);

        Sanctum::actingAs($admin);

        $this->patchJson("/api/staff/{$staff->id}/password", [
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertOk();

        $log = ActivityLog::sole();

        $this->assertSame('password_reset', $log->action);
        $this->assertSame('Staff', $log->subject_type);
        $this->assertSame($staff->id, $log->subject_id);
        $this->assertSame($admin->id, $log->user_id);
        $this->assertNull($log->old_values);
        $this->assertNull($log->new_values);
        $this->assertStringNotContainsString('NewPassword123!', $log->toJson());
    }

    public function test_only_admin_can_view_activity_logs(): void
    {
        $staff = User::factory()->create(['role' => 'normal_staff']);
        Sanctum::actingAs($staff);

        $this->getJson('/api/activity-logs')->assertForbidden();
    }

    public function test_repeated_barrel_return_does_not_create_a_duplicate_log(): void
    {
        $staff = User::factory()->create(['role' => 'normal_staff']);
        $customer = Customer::create([
            'name' => 'Test Customer',
            'phone' => '0123456789',
            'phone_normalized' => '0123456789',
        ]);
        $barrel = Barrel::create([
            'code' => '003',
            'status' => 'rented',
            'current_customer_id' => $customer->id,
        ]);

        Sanctum::actingAs($staff);

        $this->postJson("/api/barrels/{$barrel->id}/return")->assertOk();
        $this->postJson("/api/barrels/{$barrel->id}/return")->assertOk();

        $this->assertDatabaseCount('activity_logs', 1);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'returned',
            'subject_type' => 'Barrel',
            'subject_id' => $barrel->id,
        ]);
    }
}
