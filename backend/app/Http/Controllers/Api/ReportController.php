<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Support\InvoiceOrdering;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function rentalReport(Request $request)
    {
        $request->validate([
            'search_by' => ['sometimes', 'in:all,invoice,customer,barrel'],
            'payment_status' => ['nullable', 'in:paid,unpaid'],
            'settlement_status' => ['nullable', 'in:settled,unsettled'],
        ]);

        abort_unless(
            in_array($request->user()?->role, ['admin', 'staff', 'normal_staff'], true),
            403,
            'Only administrators and staff can access reports.'
        );

        if ($request->boolean('export')) {
            abort_unless(
                $request->user()?->isAdmin(),
                403,
                'Only administrators can export reports.'
            );
        }

        $query = Invoice::with([
            'customer',
            'createdBy:id,name',
            'items.barrel',
        ])
            ->latest('issued_date')
            ->latest('id');

        InvoiceOrdering::apply($query, $request->query('sort'), $request->query('direction'));

        if ($request->filled('payment_status')) {
            $query->where('status', $request->query('payment_status'));
        }

        if ($request->filled('settlement_status')) {
            $query->where('settlement_status', $request->query('settlement_status'));
        }

        if ($fromDate = $request->query('from_date')) {
            $query->whereDate(
                'issued_date',
                '>=',
                $fromDate
            );
        }

        if ($toDate = $request->query('to_date')) {
            $query->whereDate(
                'issued_date',
                '<=',
                $toDate
            );
        }

        if ($search = trim((string) $request->query('search', ''))) {
            $searchBy = $request->query('search_by', 'all');

            if ($searchBy === 'invoice') {
                $query->where('invoice_no', 'like', "%{$search}%");
            } elseif ($searchBy === 'customer') {
                $query->whereHas('customer', function ($customerQuery) use ($search) {
                    $customerQuery->where('name', 'like', "%{$search}%");
                });
            } elseif ($searchBy === 'barrel') {
                $query->whereHas('items.barrel', function ($barrelQuery) use ($search) {
                    $barrelQuery->where('code', 'like', "%{$search}%");
                });
            } else {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery
                        ->where('invoice_no', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($customerQuery) use ($search) {
                            $customerQuery->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('items.barrel', function ($barrelQuery) use ($search) {
                            $barrelQuery->where('code', 'like', "%{$search}%");
                        });
                });
            }
        }

        if ($request->boolean('export')) {
            return response()->json($query->get());
        }

        $summaryQuery = clone $query;
        $summaryInvoices = $summaryQuery->get([
            'id',
            'status',
            'settlement_status',
        ]);

        $results = $query->paginate(20);

        return response()->json(array_merge(
            $results->toArray(),
            ['summary' => [
                'total_invoices' => $summaryInvoices->count(),
                'paid_invoices' => $summaryInvoices->where('status', 'paid')->count(),
                'unpaid_invoices' => $summaryInvoices->where('status', 'unpaid')->count(),
                'settled_invoices' => $summaryInvoices->where('settlement_status', 'settled')->count(),
                'unsettled_invoices' => $summaryInvoices->where('settlement_status', 'unsettled')->count(),
            ]]
        ));
    }
}
