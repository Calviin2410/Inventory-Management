<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WasteController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $query = Invoice::query()
            ->with([
                'items.barrel:id,code',
                'wasteSaleRecordedBy:id,name',
            ])
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

        return response()->json($query->paginate(20));
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
                'items.barrel:id,code',
                'wasteSaleRecordedBy:id,name',
            ])
        );
    }
}
