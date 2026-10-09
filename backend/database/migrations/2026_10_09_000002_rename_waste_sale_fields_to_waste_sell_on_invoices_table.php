<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $oldRecordedByExists = Schema::hasColumn('invoices', 'waste_sale_recorded_by');
        $newRecordedByExists = Schema::hasColumn('invoices', 'waste_sell_recorded_by');

        if ($oldRecordedByExists && ! $newRecordedByExists) {
            // The recorded-by column was originally created with a foreign key.
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropForeign(['waste_sale_recorded_by']);
            });
        }

        foreach ([
            'waste_sale_amount' => 'waste_sell_amount',
            'waste_sale_remark' => 'waste_sell_remark',
            'waste_sale_recorded_at' => 'waste_sell_recorded_at',
            'waste_sale_recorded_by' => 'waste_sell_recorded_by',
        ] as $oldName => $newName) {
            if (Schema::hasColumn('invoices', $oldName) && ! Schema::hasColumn('invoices', $newName)) {
                Schema::table('invoices', function (Blueprint $table) use ($oldName, $newName) {
                    $table->renameColumn($oldName, $newName);
                });
            }
        }

        if ($oldRecordedByExists && ! $newRecordedByExists) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->foreign('waste_sell_recorded_by')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        $newRecordedByExists = Schema::hasColumn('invoices', 'waste_sell_recorded_by');
        $oldRecordedByExists = Schema::hasColumn('invoices', 'waste_sale_recorded_by');

        if ($newRecordedByExists && ! $oldRecordedByExists) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropForeign(['waste_sell_recorded_by']);
            });
        }

        foreach ([
            'waste_sell_amount' => 'waste_sale_amount',
            'waste_sell_remark' => 'waste_sale_remark',
            'waste_sell_recorded_at' => 'waste_sale_recorded_at',
            'waste_sell_recorded_by' => 'waste_sale_recorded_by',
        ] as $newName => $oldName) {
            if (Schema::hasColumn('invoices', $newName) && ! Schema::hasColumn('invoices', $oldName)) {
                Schema::table('invoices', function (Blueprint $table) use ($newName, $oldName) {
                    $table->renameColumn($newName, $oldName);
                });
            }
        }

        if ($newRecordedByExists && ! $oldRecordedByExists) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->foreign('waste_sale_recorded_by')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }
    }
};
