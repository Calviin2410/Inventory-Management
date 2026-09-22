<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class DriverController extends Controller
{
    private function ensureAdmin(Request $request): void
    {
        abort_unless(
            $request->user()?->isAdmin(),
            403,
            'Only administrators can manage drivers.'
        );
    }

    public function index(Request $request)
    {
        $query = Driver::query()->orderBy('name');

        if ($request->boolean('available_only')) {
            $query->where('status', 'available');
        }

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255'
            ],

            'phone' => [
                'nullable',
                'string',
                'max:20'
            ],
        ]);

        $driver = Driver::create($data);

        ActivityLog::record(
            $request,
            'created',
            'Driver',
            $driver->id,
            $driver->name,
            'Created driver '.$driver->name,
            null,
            $driver->only(['name', 'phone', 'status'])
        );

        return response()->json(
            $driver,
            201
        );
    }

    public function show(Request $request, Driver $driver)
    {
        $this->ensureAdmin($request);

        return response()->json($driver);
    }

    public function update(Request $request, Driver $driver)
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],
            'phone' => [
                'sometimes',
                'nullable',
                'string',
                'max:20',
            ],
            'status' => [
                'sometimes',
                'required',
                'in:available,unavailable',
            ],
        ]);

        $before = $driver->only(['name', 'phone', 'status']);

        $driver->update($data);

        $after = $driver->fresh()->only(['name', 'phone', 'status']);

        ActivityLog::record(
            $request,
            'updated',
            'Driver',
            $driver->id,
            $driver->name,
            'Updated driver '.$driver->name,
            $before,
            $after
        );

        return response()->json($driver->fresh());
    }

    public function destroy(Request $request, Driver $driver)
    {
        $this->ensureAdmin($request);

        $snapshot = $driver->only(['name', 'phone', 'status']);
        $driverId = $driver->id;
        $driverName = $driver->name;

        $driver->delete();

        ActivityLog::record(
            $request,
            'deleted',
            'Driver',
            $driverId,
            $driverName,
            'Deleted driver '.$driverName,
            $snapshot
        );

        return response()->json([
            'message' => 'Driver deleted successfully.',
        ]);
    }
}
