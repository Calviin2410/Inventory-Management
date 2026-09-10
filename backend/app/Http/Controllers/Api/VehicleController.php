<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Http\Request;

class VehicleController extends Controller
{
    public function index()
    {
        return response()->json(
            Vehicle::orderBy('plate_number')->get()
        );
    }

    public function store(Request $request)
    {
        abort_unless(
            $request->user()->isAdmin(),
            403,
            'Only administrators can add vehicles.'
        );

        $data = $request->validate([
            'plate_number' => [
                'required',
                'string',
                'max:30',
                'unique:vehicles,plate_number'
            ],
        ]);

        $vehicle = Vehicle::create($data);

        return response()->json(
            $vehicle,
            201
        );
    }
}