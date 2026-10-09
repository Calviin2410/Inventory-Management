<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_number_sequences', function (Blueprint $table) {
            $table->string('prefix')->primary();
        });

        DB::table('invoice_number_sequences')->insert(['prefix' => 'TKS']);
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_number_sequences');
    }
};
