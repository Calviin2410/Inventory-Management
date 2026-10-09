<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Barrel;
use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BarrelController extends Controller
{
    public function index(Request $request)
    {
        $query = Barrel::with([
            'currentCustomer',
            'invoiceItems.invoice.customer',
        ]);

        if ($status = $request->query('status')) {
            $query->where(
                'status',
                $status
            );
        }

        if ($search = $request->query('search')) {
            $query->where(
                'code',
                'like',
                '%' . $search . '%'
            );
        }

        $barrels = $this->orderByBarrelCode($query)
            ->paginate(30);

        $barrels->getCollection()->transform(
            function ($barrel) {
                $latestInvoiceItem =
                    $barrel->status === 'available'
                        ? null
                        : $barrel->invoiceItems
                            ->sortByDesc('id')
                            ->first();

                $barrel->invoice_no =
                    $latestInvoiceItem
                        ?->invoice
                        ?->invoice_no;
				$barrel->invoice_id =
					$latestInvoiceItem
						?->invoice
						?->id;

				if (! $barrel->currentCustomer && $latestInvoiceItem?->invoice?->customer) {
					$barrel->setRelation(
						'currentCustomer',
						$latestInvoiceItem->invoice->customer
					);
				}

                unset(
                    $barrel->invoiceItems
                );

                return $barrel;
            }
        );

        return response()->json(
            $barrels
        );
    }

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
        ]);

        $barrel = Barrel::create([
            'code' => $data['code'],
            'status' => 'available',
        ]);

        ActivityLog::record(
            $request,
            'created',
            'Barrel',
            $barrel->id,
            $barrel->code,
            'Created barrel '.$barrel->code,
            null,
            $barrel->only(['code', 'status'])
        );

        return response()->json(
            $barrel,
            201
        );
    }


    public function update(
        Request $request,
        Barrel $barrel
    ) {
        $data = $request->validate([
            'status' => [
                'required',
                // Rented/in-transit states are assigned by invoice and waste-sale flows.
                'in:available'
            ],
        ]);

        if ($barrel->status === 'rented') {
            throw ValidationException::withMessages([
                'status' => [
                    'A rented barrel cannot be marked available directly. Record the waste sale first.'
                ],
            ]);
        }

        return $this->markReturned($request, $barrel);
    }


    public function markReturned(Request $request, Barrel $barrel)
    {
        if ($barrel->status === 'rented') {
            throw ValidationException::withMessages([
                'status' => ['A rented barrel cannot be marked available directly. Record the waste sale first.'],
            ]);
        }

        // Repeated return requests should not create duplicate activity entries.
        if (
            $barrel->status === 'available'
            && $barrel->current_customer_id === null
        ) {
            return response()->json(
                $barrel->fresh()->load('currentCustomer')
            );
        }

        $before = $barrel->only(['status', 'current_customer_id']);

        $barrel->update([
            'status' => 'available',
            'current_customer_id' => null,
        ]);

        $latestOpenRental =
            $barrel->invoiceItems()
                ->whereNull('rental_end')
                ->latest()
                ->first();

        if ($latestOpenRental) {
            $latestOpenRental->update([
                'rental_end' =>
                    now()->toDateString()
            ]);
        }

        ActivityLog::record(
            $request,
            'returned',
            'Barrel',
            $barrel->id,
            $barrel->code,
            'Marked barrel '.$barrel->code.' as returned',
            $before,
            $barrel->fresh()->only(['status', 'current_customer_id'])
        );

        return response()->json(
            $barrel->fresh()
        );
    }


    public function destroy(
        Request $request,
        Barrel $barrel
    ) {
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

        $snapshot = $barrel->only([
            'code',
            'status',
            'current_customer_id',
        ]);
        $barrelId = $barrel->id;
        $barrelCode = $barrel->code;

        $barrel->delete();

        ActivityLog::record(
            $request,
            'deleted',
            'Barrel',
            $barrelId,
            $barrelCode,
            'Deleted barrel '.$barrelCode,
            $snapshot
        );

        return response()->json([
            'message' =>
                'Barrel deleted successfully.'
        ]);
    }

    public function available()
    {
        return response()->json(
            $this->orderByBarrelCode(
                Barrel::where('status', 'available')
            )
                ->get()
        );
    }

    /**
     * Keep zero-padded barrel codes together, then sort the remaining codes
     * naturally (1, 2, ... 10, 11) instead of alphabetically (1, 10, 11, 2).
     */
    private function orderByBarrelCode(Builder $query): Builder
    {
        return $query
            ->orderByRaw("CASE WHEN code LIKE '0%' THEN 0 ELSE 1 END")
            ->orderByRaw('LENGTH(code)')
            ->orderBy('code');
    }
}
