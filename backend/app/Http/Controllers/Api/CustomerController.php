<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = Customer::query()
            ->latest('id');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where(
                    'name',
                    'like',
                    '%' . $search . '%'
                )
                ->orWhere(
                    'phone',
                    'like',
                    '%' . $search . '%'
                );
            });
        }

        return response()->json(
            $query->paginate(20)
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        $customer = Customer::create($data);

        return response()->json($customer, 201);
    }

    public function options()
    {
        return response()->json(
            Customer::orderBy('name')
                ->get([
                    'id',
                    'name',
                    'phone',
                ])
        );
    }

    public function show(Request $request, Customer $customer)
    {
        $query = $customer->invoices()
            ->with('items.barrel:id,code')
            ->latest('issued_date')
            ->latest('id');

        if (
            $request->filled('status')
            && in_array($request->query('status'), ['paid', 'unpaid'], true)
        ) {
            $query->where('status', $request->query('status'));
        }

        $invoices = $query->paginate(20);

        return response()->json([
            'customer' => $customer,
            'invoices' => $invoices,
        ]);
    }
}
