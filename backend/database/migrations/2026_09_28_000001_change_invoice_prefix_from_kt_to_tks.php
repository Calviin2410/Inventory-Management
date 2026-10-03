<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('invoices')
            ->where('invoice_no', 'like', 'KT%')
            ->orderBy('id')
            ->get(['id', 'invoice_no'])
            ->each(function ($invoice): void {
                DB::table('invoices')
                    ->where('id', $invoice->id)
                    ->update([
                        'invoice_no' => 'TKS'.substr($invoice->invoice_no, 2),
                    ]);
            });
    }

    public function down(): void
    {
        DB::table('invoices')
            ->where('invoice_no', 'like', 'TKS%')
            ->orderBy('id')
            ->get(['id', 'invoice_no'])
            ->each(function ($invoice): void {
                DB::table('invoices')
                    ->where('id', $invoice->id)
                    ->update([
                        'invoice_no' => 'KT'.substr($invoice->invoice_no, 3),
                    ]);
            });
    }
};
