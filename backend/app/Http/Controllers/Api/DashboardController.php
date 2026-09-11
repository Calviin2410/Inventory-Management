<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Barrel;
use App\Models\Customer;
use App\Models\Invoice;

class DashboardController extends Controller
{
    public function summary()
    {
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

            'total_customers' => Customer::count(),

            'available_barrels' => Barrel::where(
                'status',
                'available'
            )->count(),

            'rented_barrels' => Barrel::where(
                'status',
                'rented'
            )->count(),

            'recent_invoices' => Invoice::with('customer')
                ->latest('issued_date')
                ->latest('id')
                ->limit(5)
                ->get(),
        ]);
    }
}