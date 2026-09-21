<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
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

        $driver->update($data);

        return response()->json($driver->fresh());
    }
}
