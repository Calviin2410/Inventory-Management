<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
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
        
        $data = $request->validate([
            'plate_number' => [
                'required',
                'string',
                'max:30',
                'unique:vehicles,plate_number',
            ],
            'status' => [
                'nullable',
                Rule::in([
                    'available',
                    'maintenance',
                ]),
            ],
        ]);

        $vehicle = Vehicle::create([
            'plate_number' => strtoupper(
                trim($data['plate_number'])
            ),
            'status' => $data['status'] ?? 'available',
        ]);

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

        $data = $request->validate([
            'plate_number' => [
                'required',
                'string',
                'max:30',
                Rule::unique(
                    'vehicles',
                    'plate_number'
                )->ignore($vehicle->id),
            ],
            'status' => [
                'required',
                Rule::in([
                    'available',
                    'maintenance',
                ]),
            ],
        ]);

        $vehicle->update([
            'plate_number' => strtoupper(
                trim($data['plate_number'])
            ),
            'status' => $data['status'],
        ]);

        return response()->json($vehicle);
    }

    public function destroy(Vehicle $vehicle,Request $request)
    {
        $this->ensureAdmin($request);
        $vehicle->delete();

        return response()->json([
            'message' =>
                'Vehicle deleted successfully.',
        ]);
    }
}