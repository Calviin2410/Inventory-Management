<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Barrel;
use App\Models\Invoice;
use App\Models\Customer;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    // GET /api/invoices?limit=5
    public function index(Request $request)
    {
        $query = Invoice::with([
            'customer',
            'createdBy:id,name',
            'vehicle:id,plate_number',
            'items.barrel:id,code',
        ]);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where(
                    'invoice_no',
                    'like',
                    '%' . $search . '%'
                )
                ->orWhereHas('customer', function ($customerQuery) use ($search) {
                    $customerQuery->where(
                        'name',
                        'like',
                        '%' . $search . '%'
                    );
                });
            });
        }

        if (
            $request->filled('status')
            && in_array($request->query('status'), ['paid', 'unpaid'], true)
        ) {
            $query->where('status', $request->query('status'));
        }

        $sortDirection = $request->query('direction') === 'desc' ? 'desc' : 'asc';
        if ($request->query('sort') === 'invoice_no') {
            $numberExpression = match (DB::connection()->getDriverName()) {
                'mysql' => 'CAST(SUBSTRING(invoice_no, 4) AS UNSIGNED)',
                'pgsql', 'sqlite' => 'CAST(SUBSTR(invoice_no, 4) AS INTEGER)',
                default => 'CAST(SUBSTRING(invoice_no, 4) AS INTEGER)',
            };

            $query->orderByRaw($numberExpression.' '.$sortDirection)
                ->orderBy('id', $sortDirection);
        } else {
            $query->latest('issued_date')->latest('id');
        }

        return response()->json(
            $query->paginate(20)
        );
    }


    // GET /api/invoices/{invoice}
    public function show(Invoice $invoice)
    {
        return response()->json(
            $invoice->load([
                'customer',
                'createdBy:id,name',
                'vehicle',
                'items.barrel',
            ])
        );
    }

    public function update(Request $request, Invoice $invoice)
    {
        abort_unless(
            $request->user()?->isAdmin() || (int) $invoice->user_id === (int) $request->user()?->id,
            403,
            'Only the invoice creator or an administrator can edit this invoice.'
        );

        $managementFields = [
            'customer_name',
            'customer_phone',
            'issued_date',
            'address',
            'notes',
            'total_amount',
        ];

        if ($request->hasAny($managementFields)) {
            abort_unless(
                $request->user()?->isAdmin(),
                403,
                'Only administrators can edit invoice details.'
            );
        }

        $staffAllowedFields = [
            'status',
            'payment_method',
            'payment_date',
            'unpaid_remark',
            'items',
        ];

        if (! $request->user()?->isAdmin()) {
            abort_if(
                collect($request->all())->keys()->diff($staffAllowedFields)->isNotEmpty(),
                403,
                'Staff can only update invoice payment details.'
            );
        }

        $isBeingMarkedPaid = $request->input('status') === 'paid'
            && $invoice->status !== 'paid';

        $data = $request->validate([
            'customer_name' => ['sometimes', 'required', 'string', 'max:255'],
            'customer_phone' => ['sometimes', 'nullable', 'string', 'max:20'],

            'issued_date' => [
                'sometimes',
                'required',
                'date'
            ],

            'address' => [
                'nullable',
                'string'
            ],

            'notes' => [
                'nullable',
                'string'
            ],

            'total_amount' => [
                'sometimes',
                'required',
                'numeric',
                'min:0',
                'max:9999999999.99',
            ],

            'status' => [
                'sometimes',
                'required',
                'in:unpaid,paid,cancelled'
            ],

            'payment_method' => [
                $isBeingMarkedPaid ? 'required' : 'nullable',
                'in:cash,bank_in',
            ],

            'payment_date' => [
                $isBeingMarkedPaid ? 'required' : 'nullable',
                'date',
            ],

            'unpaid_remark' => [
                $request->input('status') === 'unpaid' && $invoice->status !== 'unpaid'
                    ? 'required'
                    : 'nullable',
                'string',
                'max:1000',
            ],

            'items' => ['sometimes', 'array'],
            'items.*.id' => [
                'required',
                'integer',
                Rule::exists('invoice_items', 'id')->where(
                    fn ($query) => $query->where('invoice_id', $invoice->id)
                ),
            ],
            'items.*.rental_end' => [
                'nullable',
                'date',
            ],
        ]);

        foreach ($data['items'] ?? [] as $itemData) {
            $item = $invoice->items()->whereKey($itemData['id'])->firstOrFail();
            if (
                ! empty($itemData['rental_end'])
                && \Illuminate\Support\Carbon::parse($itemData['rental_end'])
                    ->lt(\Illuminate\Support\Carbon::parse($item->rental_start))
            ) {
                return response()->json([
                    'message' => 'Rental end date must be on or after rental start date.',
                    'errors' => ['items' => ['Rental end date must be on or after rental start date.']],
                ], 422);
            }
        }

        if (($data['status'] ?? null) === 'unpaid') {
            $data['payment_method'] = null;
            $data['payment_date'] = null;
        }

        $unpaidRemark = isset($data['unpaid_remark'])
            ? trim($data['unpaid_remark'])
            : null;
        unset($data['unpaid_remark']);

        $trackedFields = [
            'customer_id',
            'issued_date',
            'address',
            'notes',
            'total_amount',
            'status',
            'payment_method',
            'payment_date',
        ];
        $before = $invoice->only($trackedFields);
        $beforeItems = $invoice->items()
            ->orderBy('id')
            ->get(['id', 'rental_start', 'rental_end'])
            ->toArray();

        DB::transaction(function () use ($invoice, $data) {
            $invoiceFields = collect($data)->except(['items', 'customer_name', 'customer_phone'])->all();
            $invoice->update($invoiceFields);

            if (array_key_exists('customer_name', $data) || array_key_exists('customer_phone', $data)) {
                $customer = $invoice->customer;
                if ($customer) {
                    $customerChanges = [];
                    if (array_key_exists('customer_name', $data)) {
                        $customerChanges['name'] = $data['customer_name'];
                    }
                    if (array_key_exists('customer_phone', $data)) {
                        $phone = Customer::normalizePhone($data['customer_phone']);
                        $customerChanges['phone'] = $phone;
                        $customerChanges['phone_normalized'] = $phone;
                        $existingNumbers = $customer->phone_numbers ?? array_filter([$customer->phone_normalized]);
                        $customerChanges['phone_numbers'] = $phone === null
                            ? []
                            : array_values(array_unique(array_merge(
                                [$phone],
                                array_filter($existingNumbers, fn ($number) => $number !== $customer->phone_normalized)
                            )));
                    }
                    $customer->update($customerChanges);
                }
            }

            foreach ($data['items'] ?? [] as $itemData) {
                $invoice->items()->whereKey($itemData['id'])
                    ->update(['rental_end' => $itemData['rental_end'] ?? null]);
            }
        });

        $after = $invoice->fresh()->only($trackedFields);
        $changedBefore = [];
        $changedAfter = [];

        foreach ($trackedFields as $field) {
            if (($before[$field] ?? null) !== ($after[$field] ?? null)) {
                $changedBefore[$field] = $before[$field] ?? null;
                $changedAfter[$field] = $after[$field] ?? null;
            }
        }

        $afterItems = $invoice->items()
            ->orderBy('id')
            ->get(['id', 'rental_start', 'rental_end'])
            ->toArray();

        if ($beforeItems !== $afterItems) {
            $changedBefore['items'] = $beforeItems;
            $changedAfter['items'] = $afterItems;
        }

        if ($unpaidRemark !== null && array_key_exists('status', $changedAfter)) {
            $changedAfter['unpaid_remark'] = $unpaidRemark;
        }

        if ($changedAfter !== []) {
            $description = array_key_exists('status', $changedAfter)
                ? 'Changed invoice '.$invoice->invoice_no.' status from '
                    .($changedBefore['status'] ?? 'unknown').' to '
                    .$changedAfter['status']
                : 'Updated invoice '.$invoice->invoice_no;

            ActivityLog::record(
                $request,
                'updated',
                'Invoice',
                $invoice->id,
                $invoice->invoice_no,
                $description,
                $changedBefore,
                $changedAfter
            );
        }

        return response()->json(
            $invoice->load([
                'customer',
                'createdBy:id,name',
                'vehicle:id,plate_number',
                'items.barrel'
            ])
        );
    }

    public function destroy(Request $request, Invoice $invoice)
    {
        abort_unless(
            $request->user()?->isAdmin(),
            403,
            'Only administrators can delete invoices.'
        );

        $invoice->load('items');
        $barrelIds = $invoice->items->pluck('barrel_id')->unique()->values();
        $snapshot = $invoice->only([
            'invoice_no',
            'customer_id',
            'vehicle_id',
            'user_id',
            'issued_date',
            'status',
        ]);
        $invoiceId = $invoice->id;
        $invoiceNo = $invoice->invoice_no;

        DB::transaction(function () use ($invoice, $barrelIds) {
            $invoice->delete();

            foreach ($barrelIds as $barrelId) {
                $remainingRental = \App\Models\InvoiceItem::query()
                    ->with('invoice:id,customer_id')
                    ->where('barrel_id', $barrelId)
                    ->whereNull('rental_end')
                    ->latest('id')
                    ->first();

                Barrel::whereKey($barrelId)->update([
                    'status' => $remainingRental ? 'rented' : 'available',
                    'current_customer_id' => $remainingRental?->invoice?->customer_id,
                ]);
            }
        });

        ActivityLog::record(
            $request,
            'deleted',
            'Invoice',
            $invoiceId,
            $invoiceNo,
            'Deleted invoice '.$invoiceNo,
            $snapshot
        );

        return response()->json([
            'message' => 'Invoice deleted successfully.',
        ]);
    }


    // GET /api/invoices-next-number
    public function nextInvoiceNo()
    {
        return response()->json([
            'invoice_no' => $this->generateNextInvoiceNo()
        ]);
    }


    // POST /api/invoices
    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => [
                'required',
                'exists:customers,id'
            ],

            'vehicle_id' => [
                'nullable',
                Rule::exists('vehicles', 'id')->where(
                    fn ($query) =>
                        $query->where('status', 'available')
                ),
            ],

            'address' => [
                'required',
                'string'
            ],

            'notes' => [
                'nullable',
                'string'
            ],

            'total_amount' => [
                'nullable',
                'numeric',
                'min:0',
                'max:9999999999.99',
            ],

            'items' => [
                'required',
                'array',
                'min:1'
            ],

            'items.*.barrel_id' => [
                'required',
                'exists:barrels,id'
            ],

            'items.*.description' => [
                'nullable',
                'string'
            ],

            'items.*.rental_start' => [
                'required',
                'date'
            ],

            'items.*.rental_end' => [
                'nullable',
                'date',
                'after_or_equal:items.*.rental_start'
            ],
        ]);


        /*
         * Check that all selected barrels
         * are still available.
         */
        $barrelIds = collect($data['items'])
            ->pluck('barrel_id');

        $barrels = Barrel::whereIn(
            'id',
            $barrelIds
        )->get();

        $notAvailable = $barrels->where(
            'status',
            '!=',
            'available'
        );


        if ($notAvailable->isNotEmpty()) {
            return response()->json([
                'message' =>
                    'Some barrels are not available: '
                    . $notAvailable
                        ->pluck('code')
                        ->implode(', ')
            ], 422);
        }


        /*
         * Create Invoice
         */
        $invoice = DB::transaction(
            function () use ($data, $request) {

                /*
                 * Generate invoice number:
                 *
                 * TKS00001
                 * TKS00002
                 * TKS00003
                 */
                DB::table('invoice_number_sequences')
                    ->where('prefix', 'TKS')
                    ->lockForUpdate()
                    ->first();

                $invoiceNo =
                    $this->generateNextInvoiceNo();


                $invoice = Invoice::create([
                    'invoice_no' => $invoiceNo,

                    'customer_id' =>
                        $data['customer_id'],

                    'vehicle_id' =>
                        $data['vehicle_id'],

                    'user_id' =>
                        $request->user()?->id,

                    // The invoice date is the date the document is created,
                    // independent from the rental start date.
                    'issued_date' =>
                        now('Asia/Kuala_Lumpur')->toDateString(),

                    'address' => $data['address'],
                    
                    'status' =>
                        'unpaid',

                    'total_amount' =>
                        $data['total_amount'] ?? 0,

                    'notes' =>
                        $data['notes'] ?? null,
                ]);


                /*
                 * Create invoice items
                 * and mark barrels as rented.
                 */
                foreach ($data['items'] as $item) {

                    $invoice
                        ->items()
                        ->create($item);


                    Barrel::where(
                        'id',
                        $item['barrel_id']
                    )->update([
                        'status' =>
                            'rented',

                        'current_customer_id' =>
                            $data['customer_id'],
                    ]);
                }


                return $invoice;
            }
        );

        ActivityLog::record(
            $request,
            'created',
            'Invoice',
            $invoice->id,
            $invoice->invoice_no,
            'Created invoice '.$invoice->invoice_no,
            null,
            $invoice->only([
                'invoice_no',
                'customer_id',
                'vehicle_id',
                'issued_date',
                'status',
            ])
        );


        return response()->json(
            $invoice->load([
                'customer',
                'createdBy:id,name',
                'vehicle',
                'items.barrel'
            ]),
            201
        );
    }


    /**
     * Return the smallest unused positive TKS invoice number.
     *
     * Existing TKS numbers are inspected by their numeric suffix so deleted
     * invoices leave reusable gaps (TKS00002 before TKS00011, for example).
     */
    private function generateNextInvoiceNo(): string
    {
        $usedNumbers = [];
        $invoiceNumbers = Invoice::query()
            ->where('invoice_no', 'like', 'TKS%')
            ->pluck('invoice_no');

        foreach ($invoiceNumbers as $invoiceNo) {
            if (preg_match('/^TKS(\d+)$/', $invoiceNo, $matches)) {
                $number = (int) $matches[1];
                if ($number > 0) {
                    $usedNumbers[$number] = true;
                }
            }
        }

        $nextNumber = 1;
        while (isset($usedNumbers[$nextNumber])) {
            $nextNumber++;
        }

        return 'TKS'.str_pad(
            (string) $nextNumber,
            5,
            '0',
            STR_PAD_LEFT
        );
    }
}
