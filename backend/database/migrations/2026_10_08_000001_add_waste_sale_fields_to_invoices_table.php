<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('waste_sell_amount', 12, 2)->nullable()->after('total_amount');
            $table->text('waste_sell_remark')->nullable()->after('waste_sell_amount');
            $table->timestamp('waste_sell_recorded_at')->nullable()->after('waste_sell_remark');
            $table->foreignId('waste_sell_recorded_by')
                ->nullable()
                ->after('waste_sell_recorded_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('waste_sell_recorded_by');
            $table->dropColumn([
                'waste_sell_amount',
                'waste_sell_remark',
                'waste_sell_recorded_at',
            ]);
        });
    }
};
