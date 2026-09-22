<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function rentalReport(Request $request)
    {
        abort_unless(
            $request->user()->isAdmin(),
            403,
            'Only administrators can access reports.'
        );

        $query = Invoice::with([
            'customer',
            'items.barrel',
        ])
            ->latest('issued_date')
            ->latest('id');

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
            'customer_id',
            'status',
        ]);

        $results = $query->paginate(20);

        return response()->json(array_merge(
            $results->toArray(),
            ['summary' => [
                'total_invoices' => $summaryInvoices->count(),
                'paid_invoices' => $summaryInvoices->where('status', 'paid')->count(),
                'unpaid_invoices' => $summaryInvoices->where('status', 'unpaid')->count(),
                'total_customers' => $summaryInvoices->pluck('customer_id')->filter()->unique()->count(),
            ]]
        ));
    }
}
