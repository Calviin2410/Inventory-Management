<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->json('phone_numbers')->nullable()->after('phone_normalized');
        });

        DB::table('customers')->whereNotNull('phone')->orderBy('id')->each(function ($customer) {
            $numbers = preg_split('/\s*[,;\n]\s*/', $customer->phone, -1, PREG_SPLIT_NO_EMPTY);
            $numbers = array_values(array_unique(array_map(function ($phone) {
                $digits = preg_replace('/\D+/', '', $phone);
                return str_starts_with($digits, '60') ? '0'.substr($digits, 2) : $digits;
            }, $numbers)));

            DB::table('customers')->where('id', $customer->id)->update([
                'phone_numbers' => json_encode($numbers),
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('phone_numbers');
        });
    }
};
