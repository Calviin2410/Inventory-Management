<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\ActivityLog;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = Customer::query()
            ->latest('id');

        if ($request->filled('phone_exact')) {
            $query->where(
                'phone_normalized',
                Customer::normalizePhone($request->query('phone_exact'))
            );
        } elseif ($search = $request->query('search')) {
            $normalizedSearch = Customer::normalizePhone($search);

            $query->where(function ($q) use ($search, $normalizedSearch) {
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

                if ($normalizedSearch !== null) {
                    $q->orWhere(
                        'phone_normalized',
                        'like',
                        '%' . $normalizedSearch . '%'
                    );
                }
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

        $normalizedPhone = Customer::normalizePhone($data['phone'] ?? null);

        if (
            $normalizedPhone !== null
            && ! in_array(strlen($normalizedPhone), [10, 11], true)
        ) {
            return response()->json([
                'message' => 'Please enter a valid phone number.',
                'errors' => [
                    'phone' => ['The phone number must contain 10 or 11 digits.'],
                ],
            ], 422);
        }

        if ($normalizedPhone !== null) {
            $existingCustomer = Customer::where(
                'phone_normalized',
                $normalizedPhone
            )->first();

            if ($existingCustomer) {
                $existingCustomer->setAttribute('already_exists', true);

                return response()->json($existingCustomer);
            }
        }

        try {
            $customer = Customer::create([
                'name' => $data['name'] ?? null,
                'phone' => $normalizedPhone,
                'phone_normalized' => $normalizedPhone,
            ]);
        } catch (QueryException $error) {
            if ($normalizedPhone === null) {
                throw $error;
            }

            $customer = Customer::where(
                'phone_normalized',
                $normalizedPhone
            )->firstOrFail();
            $customer->setAttribute('already_exists', true);

            return response()->json($customer);
        }

        $customer->setAttribute('already_exists', false);

        ActivityLog::record(
            $request,
            'created',
            'Customer',
            $customer->id,
            $customer->name ?: $customer->phone,
            'Created customer '.($customer->name ?: '-'),
            null,
            $customer->only(['name', 'phone'])
        );

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
