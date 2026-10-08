<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('waste_sale_amount', 12, 2)->nullable()->after('total_amount');
            $table->text('waste_sale_remark')->nullable()->after('waste_sale_amount');
            $table->timestamp('waste_sale_recorded_at')->nullable()->after('waste_sale_remark');
            $table->foreignId('waste_sale_recorded_by')
                ->nullable()
                ->after('waste_sale_recorded_at')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('waste_sale_recorded_by');
            $table->dropColumn([
                'waste_sale_amount',
                'waste_sale_remark',
                'waste_sale_recorded_at',
            ]);
        });
    }
};
