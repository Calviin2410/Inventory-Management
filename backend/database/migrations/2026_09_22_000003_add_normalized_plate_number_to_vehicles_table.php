<?php

use App\Models\Vehicle;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('plate_number_normalized', 30)
                ->nullable()
                ->after('plate_number');
        });

        $seen = [];

        DB::table('vehicles')->orderBy('id')->each(function ($vehicle) use (&$seen) {
            $normalized = Vehicle::normalizePlateNumber($vehicle->plate_number);

            if (isset($seen[$normalized])) {
                throw new RuntimeException(
                    "Duplicate vehicle plates must be resolved before migration: {$vehicle->plate_number}."
                );
            }

            $seen[$normalized] = true;

            DB::table('vehicles')->where('id', $vehicle->id)->update([
                'plate_number' => Vehicle::formatPlateNumber($vehicle->plate_number),
                'plate_number_normalized' => $normalized,
            ]);
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->unique(
                'plate_number_normalized',
                'vehicles_plate_number_normalized_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropUnique('vehicles_plate_number_normalized_unique');
            $table->dropColumn('plate_number_normalized');
        });
    }
};
