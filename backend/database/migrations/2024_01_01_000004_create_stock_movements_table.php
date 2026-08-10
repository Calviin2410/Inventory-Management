<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // in = 入库, out = 出库(销售), adjustment = 盘点调整
            $table->enum('type', ['in', 'out', 'adjustment']);
            $table->integer('quantity'); // 正数表示增加,负数表示减少
            $table->string('reason')->nullable(); // 如:采购入库 / 销售出库 / 盘点调整
            $table->string('reference_no')->nullable(); // 关联单据号
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
