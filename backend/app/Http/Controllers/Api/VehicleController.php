<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VehicleController extends Controller
{

    private function ensureAdmin(Request $request): void
    {
        abort_unless(
            $request->user()?->isAdmin(),
            403,
            'Only administrators can manage vehicles.'
        );
    }

    public function index()
    {
        return response()->json(
            Vehicle::orderBy('plate_number')->get()
        );
    }

    public function show(Vehicle $vehicle,Request $request)
    {
        $this->ensureAdmin($request);

        return response()->json($vehicle);
    }

    public function store(Request $request)
    {
        $this->ensureAdmin($request);

        $request->merge([
            'plate_number' => Vehicle::formatPlateNumber($request->input('plate_number')),
            'plate_number_normalized' => Vehicle::normalizePlateNumber($request->input('plate_number')),
        ]);
        
        $data = $request->validate([
            'plate_number' => [
                'required',
                'string',
                'max:30',
            ],
            'plate_number_normalized' => [
                'required',
                'unique:vehicles,plate_number_normalized',
            ],
            'status' => [
                'nullable',
                Rule::in([
                    'available',
                    'maintenance',
                ]),
            ],
        ], [
            'plate_number_normalized.unique' => 'This plate number already exists.',
        ]);

        $vehicle = Vehicle::create([
            'plate_number' => $data['plate_number'],
            'plate_number_normalized' => $data['plate_number_normalized'],
            'status' => $data['status'] ?? 'available',
        ]);

        ActivityLog::record(
            $request,
            'created',
            'Vehicle',
            $vehicle->id,
            $vehicle->plate_number,
            'Created vehicle '.$vehicle->plate_number,
            null,
            $vehicle->only(['plate_number', 'status'])
        );

        return response()->json(
            $vehicle,
            201
        );
    }

    public function update(
        Request $request,
        Vehicle $vehicle
    ) {
        $this->ensureAdmin($request);

        $request->merge([
            'plate_number' => Vehicle::formatPlateNumber($request->input('plate_number')),
            'plate_number_normalized' => Vehicle::normalizePlateNumber($request->input('plate_number')),
        ]);

        $data = $request->validate([
            'plate_number' => [
                'required',
                'string',
                'max:30',
            ],
            'plate_number_normalized' => [
                'required',
                Rule::unique('vehicles', 'plate_number_normalized')
                    ->ignore($vehicle->id),
            ],
            'status' => [
                'required',
                Rule::in([
                    'available',
                    'maintenance',
                ]),
            ],
        ], [
            'plate_number_normalized.unique' => 'This plate number already exists.',
        ]);

        $before = $vehicle->only(['plate_number', 'status']);

        $vehicle->update([
            'plate_number' => $data['plate_number'],
            'plate_number_normalized' => $data['plate_number_normalized'],
            'status' => $data['status'],
        ]);

        ActivityLog::record(
            $request,
            'updated',
            'Vehicle',
            $vehicle->id,
            $vehicle->plate_number,
            'Updated vehicle '.$vehicle->plate_number,
            $before,
            $vehicle->fresh()->only(['plate_number', 'status'])
        );

        return response()->json($vehicle);
    }

    public function destroy(Vehicle $vehicle,Request $request)
    {
        $this->ensureAdmin($request);
        $snapshot = $vehicle->only(['plate_number', 'status']);
        $vehicleId = $vehicle->id;
        $plateNumber = $vehicle->plate_number;

        $vehicle->delete();

        ActivityLog::record(
            $request,
            'deleted',
            'Vehicle',
            $vehicleId,
            $plateNumber,
            'Deleted vehicle '.$plateNumber,
            $snapshot
        );

        return response()->json([
            'message' =>
                'Vehicle deleted successfully.',
        ]);
    }
}
