<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('settlement_status', 20)->default('unsettled')->index();
            $table->text('settlement_remark')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->foreignId('settled_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['settled_by']);
            $table->dropColumn([
                'settlement_status',
                'settlement_remark',
                'settled_at',
                'settled_by',
            ]);
        });
    }
};
