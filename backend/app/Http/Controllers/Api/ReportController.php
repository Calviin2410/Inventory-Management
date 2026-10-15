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
        abort_unless(
            in_array($request->user()?->role, ['admin', 'staff', 'normal_staff'], true),
            403,
            'Only administrators and staff can access reports.'
        );

        $query = Invoice::with([
            'customer',
            'items.barrel',
        ])
            ->latest('issued_date')
            ->latest('id');

        InvoiceOrdering::apply($query, $request->query('sort'), $request->query('direction'));

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
