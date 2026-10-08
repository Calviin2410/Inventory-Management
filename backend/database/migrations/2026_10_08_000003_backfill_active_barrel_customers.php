<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('barrels')
            ->whereIn('status', ['rented', 'returning'])
            ->whereNull('current_customer_id')
            ->orderBy('id')
            ->chunkById(100, function ($barrels) {
                foreach ($barrels as $barrel) {
                    $customerId = DB::table('invoice_items')
                        ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
                        ->where('invoice_items.barrel_id', $barrel->id)
                        ->whereNotNull('invoices.customer_id')
                        ->orderByDesc('invoice_items.id')
                        ->value('invoices.customer_id');

                    if ($customerId) {
                        DB::table('barrels')
                            ->where('id', $barrel->id)
                            ->whereNull('current_customer_id')
                            ->update([
                                'current_customer_id' => $customerId,
                                'updated_at' => now(),
                            ]);
                    }
                }
            });
    }

    public function down(): void
    {
        // This migration repairs existing relationships. Reverting it could
        // remove customer assignments changed legitimately after deployment.
    }
};
