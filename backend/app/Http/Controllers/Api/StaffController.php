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

        return response()->json(
            User::query()
                ->where('role', 'normal_staff')
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
}
