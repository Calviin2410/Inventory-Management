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

    public function index()
    {
        return response()->json(
            Driver::orderBy('name')->get()
        );
    }

    public function store(Request $request)
    {
        $this->ensureAdmin($request);

        abort_unless(
            $request->user()->isAdmin(),
            403,
            'Only administrators can add drivers.'
        );

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
}