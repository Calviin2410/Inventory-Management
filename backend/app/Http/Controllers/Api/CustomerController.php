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
            $phone = Customer::normalizePhone($request->query('phone_exact'));
            $query->where(function ($q) use ($phone) {
                $q->where('phone_normalized', $phone)
                    ->orWhereJsonContains('phone_numbers', $phone);
            });
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
                    )->orWhereJsonContains('phone_numbers', $normalizedSearch);
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
            'phone_numbers' => ['sometimes', 'array', 'max:10'],
            'phone_numbers.*' => ['nullable', 'string', 'max:20'],
        ]);

        $enteredNumbers = $data['phone_numbers'] ?? array_filter([$data['phone'] ?? null]);
        $normalizedNumbers = array_values(array_unique(array_filter(array_map(
            fn ($phone) => Customer::normalizePhone($phone),
            $enteredNumbers
        ))));

        foreach ($normalizedNumbers as $number) {
            if (! in_array(strlen($number), [10, 11], true)) {
                return response()->json([
                    'message' => 'Please enter valid phone numbers.',
                    'errors' => ['phone_numbers' => ['Each phone number must contain 10 or 11 digits.']],
                ], 422);
            }
        }

        $normalizedPhone = $normalizedNumbers[0] ?? null;

        if ($normalizedNumbers !== []) {
            $matches = Customer::query()->where(function ($query) use ($normalizedNumbers) {
                foreach ($normalizedNumbers as $number) {
                    $query->orWhere('phone_normalized', $number)
                        ->orWhereJsonContains('phone_numbers', $number);
                }
            })->get();

            if ($matches->pluck('id')->unique()->count() > 1) {
                return response()->json([
                    'message' => 'These phone numbers belong to different customers. Please check the numbers.',
                ], 422);
            }

            if ($existingCustomer = $matches->first()) {
                $existingCustomer->phone_numbers = array_values(array_unique(array_merge(
                    $existingCustomer->phone_numbers ?? array_filter([$existingCustomer->phone_normalized]),
                    $normalizedNumbers
                )));
                $existingCustomer->save();
                $existingCustomer->setAttribute('already_exists', true);
                return response()->json($existingCustomer);
            }
        }

        try {
            $customer = Customer::create([
                'name' => $data['name'] ?? null,
                'phone' => $normalizedPhone,
                'phone_normalized' => $normalizedPhone,
                'phone_numbers' => $normalizedNumbers,
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
