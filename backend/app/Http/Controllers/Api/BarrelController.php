<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Barrel;
use Illuminate\Http\Request;

class BarrelController extends Controller
{
    // GET /api/barrels?status=rented
    public function index(Request $request)
    {
        $query = Barrel::with('currentCustomer');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return response()->json($query->orderBy('code')->get());
    }

    // POST /api/barrels  — add a new barrel to the fleet
    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'unique:barrels,code'],
            'type' => ['nullable', 'string'],
        ]);

        $barrel = Barrel::create($data + ['status' => 'available']);

        return response()->json($barrel, 201);
    }

    // POST /api/barrels/{barrel}/return — mark a rented barrel as returned
    public function markReturned(Barrel $barrel)
    {
        $barrel->update([
            'status' => 'available',
            'current_customer_id' => null,
        ]);

        // close out the open rental period on the most recent invoice item for this barrel
        $barrel->invoiceItems()
            ->whereNull('rental_end')
            ->latest()
            ->first()
            ?->update(['rental_end' => now()->toDateString()]);

        return response()->json($barrel->fresh());
    }
}
