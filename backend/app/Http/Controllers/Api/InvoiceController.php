<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Barrel;
use App\Models\Invoice;
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
            'items.barrel:id,code',
        ])  
            ->latest('issued_date')
            ->latest('id');

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
                'driver',
                'vehicle',
                'items.barrel'
            ])
        );
    }

    public function update(Request $request, Invoice $invoice)
    {
        $managementFields = [
            'customer_id',
            'issued_date',
            'address',
            'notes',
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
            'customer_id' => [
                'sometimes',
                'required',
                'exists:customers,id'
            ],

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
        ]);

        if (($data['status'] ?? null) === 'unpaid') {
            $data['payment_method'] = null;
            $data['payment_date'] = null;
        }

        $trackedFields = [
            'customer_id',
            'issued_date',
            'address',
            'notes',
            'status',
            'payment_method',
            'payment_date',
        ];
        $before = $invoice->only($trackedFields);

        $invoice->update($data);

        $after = $invoice->fresh()->only($trackedFields);
        $changedBefore = [];
        $changedAfter = [];

        foreach ($trackedFields as $field) {
            if (($before[$field] ?? null) !== ($after[$field] ?? null)) {
                $changedBefore[$field] = $before[$field] ?? null;
                $changedAfter[$field] = $after[$field] ?? null;
            }
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
                'items.barrel'
            ])
        );
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

            'driver_id' => [
                'required',
                Rule::exists('drivers', 'id')->where(
                    fn ($query) =>
                        $query->where('status', 'available')
                ),
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


                $total = 0;

                /*
                 * Generate invoice number:
                 *
                 * KT00001
                 * KT00002
                 * KT00003
                 */
                $invoiceNo =
                    $this->generateNextInvoiceNo();


                $invoice = Invoice::create([
                    'invoice_no' => $invoiceNo,

                    'customer_id' =>
                        $data['customer_id'],

                    'driver_id' =>
                        $data['driver_id'],

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
                        $total,

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
                'driver_id',
                'vehicle_id',
                'issued_date',
                'status',
            ])
        );


        return response()->json(
            $invoice->load([
                'customer',
                'driver',
                'vehicle',
                'items.barrel'
            ]),
            201
        );
    }


    /*
     * Generate the next invoice number.
     *
     * Examples:
     * KT00001
     * KT00002
     * KT00003
     */
    private function generateNextInvoiceNo(): string
    {
        /*
         * Only look for invoices
         * using the KT format.
         */
        $lastInvoice = Invoice::where(
            'invoice_no',
            'like',
            'KT%'
        )
            ->orderByDesc('id')
            ->first();


        /*
         * No KT invoice yet.
         */
        if (!$lastInvoice) {
            return 'KT00001';
        }


        /*
         * KT00001
         *     ↓
         * 00001
         *     ↓
         * 1
         */
        $lastNumber = (int) str_replace(
            'KT',
            '',
            $lastInvoice->invoice_no
        );


        $nextNumber =
            $lastNumber + 1;


        /*
         * 2
         * ↓
         * 00002
         * ↓
         * KT00002
         */
        return 'KT' . str_pad(
            (string) $nextNumber,
            5,
            '0',
            STR_PAD_LEFT
        );
    }
}
