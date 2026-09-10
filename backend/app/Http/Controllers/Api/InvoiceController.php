<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Barrel;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    // GET /api/invoices?limit=5
    public function index(Request $request)
    {
        $query = Invoice::with('customer')
            ->latest('issued_date')
            ->latest('id');

        if ($limit = $request->query('limit')) {
            return response()->json(
                $query->limit((int) $limit)->get()
            );
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
        ]);

        $invoice->update($data);

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
                'exists:drivers,id'
            ],

            'vehicle_id' => [
                'required',
                'exists:vehicles,id'
            ],

            'issued_date' => [
                'required',
                'date'
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

                    'issued_date' =>
                        $data['issued_date'],

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