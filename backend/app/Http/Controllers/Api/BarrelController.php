<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Barrel;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BarrelController extends Controller
{
    // GET /api/barrels?status=rented
    public function index(Request $request)
    {
        $query = Barrel::with([
            'currentCustomer',
            'invoiceItems.invoice',
        ]);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $barrels = $query
            ->orderBy('code')
            ->get()
            ->map(function ($barrel) {

                $latestInvoiceItem = $barrel->invoiceItems
                    ->sortByDesc('id')
                    ->first();

                $barrel->invoice_no =
                    $latestInvoiceItem?->invoice?->invoice_no;

                unset($barrel->invoiceItems);

                return $barrel;
            });

        return response()->json($barrels);
    }

    // POST /api/barrels  — add a new barrel to the fleet
    public function store(Request $request)
    {
        abort_unless(
            $request->user()->isAdmin(),
            403,
            'Only administrators can add barrels.'
        );

        $data = $request->validate([
            'code' => [
                'required',
                'string',
                'unique:barrels,code'
            ],

            'type' => [
                'nullable',
                'string'
            ],
        ]);

        $barrel = Barrel::create(
            $data + [
                'status' => 'available'
            ]
        );

        return response()->json(
            $barrel,
            201
        );
    }

    public function update(Request $request, Barrel $barrel)
    {
        $data = $request->validate([
            'status' => ['required', 'in:available,rented,returning'],
        ]);

        if ($data['status'] === 'rented' && ! $barrel->current_customer_id) {
            throw ValidationException::withMessages([
                'status' => ['A barrel can only be marked rented through an invoice.'],
            ]);
        }

        if ($data['status'] === 'available') {
            return $this->markReturned($barrel);
        }

        $barrel->update(['status' => $data['status']]);

        return response()->json($barrel->fresh()->load('currentCustomer'));
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

    public function destroy(
        Request $request,
        Barrel $barrel
    )
    {
        abort_unless(
            $request->user()->isAdmin(),
            403,
            'Only administrators can delete barrels.'
        );

        if ($barrel->status === 'rented') {
            return response()->json([
                'message' =>
                    'A rented barrel cannot be deleted.'
            ], 422);
        }

        $barrel->delete();

        return response()->json([
            'message' =>
                'Barrel deleted successfully.'
        ]);
    }
}
