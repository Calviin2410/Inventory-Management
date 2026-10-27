<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Invoice;
use App\Support\InvoiceOrdering;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SettlementController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Only administrators can access the settlements page.');

        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'search' => ['nullable', 'string', 'max:100'],
            'search_by' => ['sometimes', 'in:all,invoice,customer,barrel'],
            'settlement_status' => ['nullable', 'in:settled,unsettled'],
            'sort' => ['nullable', 'in:invoice_no,issued_date'],
            'direction' => ['nullable', 'in:asc,desc'],
        ]);

        $today = now('Asia/Kuala_Lumpur')->toDateString();
        $from = $data['from'] ?? $today;
        $to = $data['to'] ?? $from;
        $start = CarbonImmutable::parse($from, 'Asia/Kuala_Lumpur')->startOfDay()->utc();
        $end = CarbonImmutable::parse($to, 'Asia/Kuala_Lumpur')->addDay()->startOfDay()->utc();

        $query = Invoice::query()
                ->with('settledByUser:id,name')
                ->with(['customer:id,name', 'items.barrel:id,code'])
                ->where('created_at', '>=', $start)
                ->where('created_at', '<', $end)
                ->latest('created_at')
                ->latest('id');

        if ($search = trim($data['search'] ?? '')) {
            $searchBy = $data['search_by'] ?? 'all';
            if ($searchBy === 'invoice') {
                $query->where('invoice_no', 'like', '%'.$search.'%');
            } elseif ($searchBy === 'customer') {
                $query->whereHas('customer', fn ($customerQuery) => $customerQuery->where('name', 'like', '%'.$search.'%'));
            } elseif ($searchBy === 'barrel') {
                $query->whereHas('items.barrel', fn ($barrelQuery) => $barrelQuery->where('code', 'like', '%'.$search.'%'));
            } else {
                $query->where(function ($builder) use ($search) {
                    $builder->where('invoice_no', 'like', '%'.$search.'%')
                        ->orWhereHas('customer', fn ($customerQuery) => $customerQuery->where('name', 'like', '%'.$search.'%'))
                        ->orWhereHas('items.barrel', fn ($barrelQuery) => $barrelQuery->where('code', 'like', '%'.$search.'%'));
                });
            }
        }

        if (! empty($data['settlement_status'])) {
            $query->where('settlement_status', $data['settlement_status']);
        }

        InvoiceOrdering::apply($query, $request->query('sort'), $request->query('direction'), 'created_at');

        return response()->json($query->get());
    }

    public function update(Request $request, Invoice $invoice)
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'action' => ['required', Rule::in(['settle', 'reopen'])],
            'remark' => ['required', 'string', 'max:1000'],
        ]);

        $data['remark'] = trim($data['remark']);
        if ($data['remark'] === '') {
            throw ValidationException::withMessages([
                'remark' => 'A remark is required.',
            ]);
        }

        $before = $invoice->only([
            'settlement_status',
            'settlement_remark',
            'settled_at',
            'settled_by',
        ]);
        $before['settled_by'] = $invoice->settledByUser()->value('name');

        $invoice = DB::transaction(function () use ($data, $invoice, $request) {
            $lockedInvoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $isSettling = $data['action'] === 'settle';

            if ($isSettling && $lockedInvoice->status !== 'paid') {
                throw ValidationException::withMessages([
                    'action' => ['This invoice must be marked Paid before it can be settled.'],
                ]);
            }

            if ($isSettling && $lockedInvoice->waste_sell_amount === null) {
                throw ValidationException::withMessages([
                    'action' => ['Sell Waste must be recorded before this invoice can be settled.'],
                ]);
            }

            if ($isSettling && $lockedInvoice->settlement_status === 'settled') {
                throw ValidationException::withMessages([
                    'action' => 'This invoice is already settled.',
                ]);
            }

            if (! $isSettling && $lockedInvoice->settlement_status !== 'settled') {
                throw ValidationException::withMessages([
                    'action' => 'This invoice is already unsettled.',
                ]);
            }

            $lockedInvoice->update([
                'settlement_status' => $isSettling ? 'settled' : 'unsettled',
                'settlement_remark' => $data['remark'],
                'settled_at' => $isSettling ? now() : null,
                'settled_by' => $isSettling ? $request->user()?->id : null,
            ]);

            return $lockedInvoice->fresh();
        });

        $after = $invoice->only([
            'settlement_status',
            'settlement_remark',
            'settled_at',
            'settled_by',
        ]);
        $after['settled_by'] = $data['action'] === 'settle'
            ? $request->user()?->name
            : null;

        ActivityLog::record(
            $request,
            $data['action'] === 'settle' ? 'settled' : 'reopened',
            'Invoice',
            $invoice->id,
            $invoice->invoice_no,
            ($data['action'] === 'settle' ? 'Settled invoice ' : 'Reopened settlement for invoice ').$invoice->invoice_no,
            $before,
            $after,
        );

        return response()->json($invoice->load('settledByUser:id,name'));
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless(
            $request->user()?->isAdmin(),
            403,
            'Only administrators can update settlement status.'
        );
    }
}
