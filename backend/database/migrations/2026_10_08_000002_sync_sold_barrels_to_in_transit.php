<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rentedBarrelIds = DB::table('barrels')
            ->where('status', 'rented')
            ->pluck('id');

        foreach ($rentedBarrelIds as $barrelId) {
            $latestInvoiceItem = DB::table('invoice_items')
                ->where('barrel_id', $barrelId)
                ->orderByDesc('id')
                ->first(['invoice_id']);

            if (! $latestInvoiceItem) {
                continue;
            }

            $hasRecordedSale = DB::table('invoices')
                ->where('id', $latestInvoiceItem->invoice_id)
                ->whereNotNull('waste_sell_amount')
                ->exists();

            if ($hasRecordedSale) {
                DB::table('barrels')
                    ->where('id', $barrelId)
                    ->update([
                        'status' => 'returning',
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    public function down(): void
    {
        // This is a data correction. Reverting it could overwrite legitimate
        // status changes made after the migration ran.
    }
};
