<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Barrel;
use App\Models\Invoice;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function summary(Request $request)
    {
        $recentInvoicesQuery = Invoice::with(['customer', 'createdBy:id,name', 'items.barrel:id,code'])
            ->latest('issued_date')
            ->latest('id');

        if (! $request->user()->isAdmin()) {
            $recentInvoicesQuery->where('user_id', $request->user()->id);
        }

        return response()->json([
            'total_invoices' => Invoice::count(),

            'paid_invoices' => Invoice::where(
                'status',
                'paid'
            )->count(),

            'unpaid_invoices' => Invoice::where(
                'status',
                'unpaid'
            )->count(),

            'settled_invoices' => Invoice::where(
                'settlement_status',
                'settled'
            )->count(),

            'unsettled_invoices' => Invoice::where(
                'settlement_status',
                'unsettled'
            )->count(),

            'available_barrels' => Barrel::where(
                'status',
                'available'
            )->count(),

            'rented_barrels' => Barrel::where(
                'status',
                'rented'
            )->count(),

            'recent_invoices' => $recentInvoicesQuery
                ->limit(20)
                ->get(),
        ]);
    }
}
