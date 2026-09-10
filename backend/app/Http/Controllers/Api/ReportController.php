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

        return response()->json(
            $query->get()
        );
    }
}