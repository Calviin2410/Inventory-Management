<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            if (Schema::hasColumn('invoice_items', 'unit_price')) {
                $table->dropColumn('unit_price');
            }

            if (!Schema::hasColumn('invoice_items', 'description')) {
                $table->text('description')
                    ->nullable()
                    ->after('barrel_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            if (Schema::hasColumn('invoice_items', 'description')) {
                $table->dropColumn('description');
            }

            if (!Schema::hasColumn('invoice_items', 'unit_price')) {
                $table->decimal('unit_price', 12, 2)
                    ->default(0)
                    ->after('barrel_id');
            }
        });
    }
};