<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('invoice_number_sequences')) {
            DB::table('invoice_number_sequences')->insertOrIgnore(['prefix' => 'C']);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('invoice_number_sequences')) {
            DB::table('invoice_number_sequences')->where('prefix', 'C')->delete();
        }
    }
};
