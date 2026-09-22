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
            $table->string('phone_normalized', 20)->nullable()->after('phone');
        });

        $seen = [];

        DB::table('customers')
            ->orderBy('id')
            ->get(['id', 'phone'])
            ->each(function ($customer) use (&$seen) {
                $phone = $this->normalizePhone($customer->phone);

                if ($phone === null || isset($seen[$phone])) {
                    return;
                }

                DB::table('customers')
                    ->where('id', $customer->id)
                    ->update(['phone_normalized' => $phone]);

                $seen[$phone] = true;
            });

        Schema::table('customers', function (Blueprint $table) {
            $table->unique('phone_normalized');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropUnique(['phone_normalized']);
            $table->dropColumn('phone_normalized');
        });
    }

    private function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '60')) {
            $digits = '0'.substr($digits, 2);
        }

        return $digits;
    }
};
