<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class StaffController extends Controller
{
    private function ensureAdmin(Request $request): void
    {
        abort_unless(
            $request->user()?->isAdmin(),
            403,
            'Only administrators can manage staff accounts.'
        );
    }

    public function index(Request $request)
    {
        $this->ensureAdmin($request);

        $primaryAdminId = User::query()
            ->where('role', 'admin')
            ->min('id');
        $canViewAllAdmins = (int) $request->user()->id === (int) $primaryAdminId;

        return response()->json(
            User::query()
                ->where(function ($query) use ($request) {
                    $query->where('role', 'normal_staff')
                        ->orWhere(function ($adminQuery) use ($request, $canViewAllAdmins) {
                            // The first-created admin (Bryan in production) can
                            // see all admins. Other admins only see themselves.
                            $adminQuery->where('role', 'admin')
                                ->when(!$canViewAllAdmins, fn ($query) => $query->where('id', $request->user()->id));
                        });
                })
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'role', 'created_at'])
        );
    }

    public function store(Request $request)
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $staff = User::create([
            'name' => trim($data['name']),
            'email' => strtolower(trim($data['email'])),
            'password' => Hash::make($data['password']),
            'role' => 'normal_staff',
        ]);

        ActivityLog::record(
            $request,
            'created',
            'Staff',
            $staff->id,
            $staff->name,
            'Created staff account for '.$staff->name,
            null,
            $staff->only(['name', 'email', 'role'])
        );

        return response()->json(
            $staff->only(['id', 'name', 'email', 'role', 'created_at']),
            201
        );
    }

    public function resetPassword(Request $request, User $staff)
    {
        $this->ensureAdmin($request);

        abort_unless(
            $staff->role === 'normal_staff',
            404,
            'Staff account not found.'
        );

        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $staff->update([
            'password' => Hash::make($data['password']),
        ]);

        $staff->tokens()->delete();

        ActivityLog::record(
            $request,
            'password_reset',
            'Staff',
            $staff->id,
            $staff->name,
            'Reset password for staff '.$staff->name
        );

        return response()->json([
            'message' => 'Staff password reset successfully.',
        ]);
    }

    public function destroy(Request $request, User $staff)
    {
        $this->ensureAdmin($request);

        abort_unless(
            $staff->role === 'normal_staff',
            404,
            'Staff account not found.'
        );

        $staffData = $staff->only(['id', 'name', 'email', 'role']);

        ActivityLog::record(
            $request,
            'deleted',
            'Staff',
            $staff->id,
            $staff->name,
            'Deleted staff account for '.$staff->name,
            $staffData
        );

        // Invoice and activity-log history is retained; nullable user links are
        // detached by the database constraints when the account is removed.
        $staff->tokens()->delete();
        $staff->delete();

        return response()->json(['message' => 'Staff account deleted successfully.']);
    }
}
