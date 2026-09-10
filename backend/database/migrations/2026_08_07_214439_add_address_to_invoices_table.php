<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Kept for existing migration histories. The column is now part of the
        // original create_invoices_table migration.
    }

    public function down(): void
    {
        // No-op: this migration no longer owns the address column.
    }
};