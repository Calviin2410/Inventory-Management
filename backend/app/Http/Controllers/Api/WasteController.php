<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Barrel;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WasteController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
        ]);

        $query = Invoice::query()
            ->with([
                'items.barrel:id,code',
                'wasteSaleRecordedBy:id,name',
            ])
            ->whereNotNull('waste_sale_amount')
            ->latest('issued_date')
            ->latest('id');

        if ($search = trim($data['search'] ?? '')) {
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('invoice_no', 'like', '%'.$search.'%')
                    ->orWhereHas('items.barrel', function ($barrelQuery) use ($search) {
                        $barrelQuery->where('code', 'like', '%'.$search.'%');
                    });
            });
        }

        if (! empty($data['from_date'])) {
            $query->whereDate('waste_sale_recorded_at', '>=', $data['from_date']);
        }

        if (! empty($data['to_date'])) {
            $query->whereDate('waste_sale_recorded_at', '<=', $data['to_date']);
        }

        $totalAmountReceived = (clone $query)
            ->reorder()
            ->sum('waste_sale_amount');
        $sales = $query->paginate(20);

        return response()->json(array_merge(
            $sales->toArray(),
            ['total_amount_received' => $totalAmountReceived]
        ));
    }

    public function update(Request $request, Invoice $invoice)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'remark' => ['nullable', 'string', 'max:1000'],
        ]);

        $before = $invoice->only([
            'waste_sale_amount',
            'waste_sale_remark',
            'waste_sale_recorded_at',
            'waste_sale_recorded_by',
        ]);

        $invoice = DB::transaction(function () use ($data, $invoice, $request) {
            $lockedInvoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $lockedInvoice->update([
                'waste_sale_amount' => $data['amount'],
                'waste_sale_remark' => filled($data['remark'] ?? null)
                    ? trim($data['remark'])
                    : null,
                'waste_sale_recorded_at' => now(),
                'waste_sale_recorded_by' => $request->user()->id,
            ]);

            $activeBarrelIds = $lockedInvoice->items()
                ->get(['id', 'barrel_id'])
                ->filter(function ($item) {
                    return ! $item->barrel
                        ?->invoiceItems()
                        ->where('id', '>', $item->id)
                        ->exists();
                })
                ->pluck('barrel_id');

            Barrel::query()
                ->whereIn('id', $activeBarrelIds)
                ->where('status', 'rented')
                ->update([
					'status' => 'returning',
					'current_customer_id' => $lockedInvoice->customer_id,
				]);

            return $lockedInvoice->fresh();
        });

        $action = $before['waste_sale_amount'] === null
            ? 'waste_sale_recorded'
            : 'waste_sale_updated';

        ActivityLog::record(
            $request,
            $action,
            'Invoice',
            $invoice->id,
            $invoice->invoice_no,
            ($action === 'waste_sale_recorded' ? 'Recorded' : 'Updated')
                .' waste sale for invoice '.$invoice->invoice_no,
            $before,
            $invoice->only([
                'waste_sale_amount',
                'waste_sale_remark',
                'waste_sale_recorded_at',
                'waste_sale_recorded_by',
            ]),
        );

        return response()->json(
            $invoice->load([
                'customer',
                'createdBy:id,name',
                'vehicle:id,plate_number',
                'items.barrel:id,code',
                'wasteSaleRecordedBy:id,name',
            ])
        );
    }
}
