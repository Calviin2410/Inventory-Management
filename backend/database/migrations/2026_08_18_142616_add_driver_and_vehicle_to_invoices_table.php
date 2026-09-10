<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {

            $table->foreignId('driver_id')
                ->nullable()
                ->after('customer_id')
                ->constrained('drivers')
                ->nullOnDelete();

            $table->foreignId('vehicle_id')
                ->nullable()
                ->after('driver_id')
                ->constrained('vehicles')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('driver_id');
            $table->dropConstrainedForeignId('vehicle_id');
        });
    }
};