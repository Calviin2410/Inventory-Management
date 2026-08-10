<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'user_id',
        'type',
        'quantity',
        'reason',
        'reference_no',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // 每次创建流水记录时,自动累加/扣减 products.quantity
    // 这样 products.quantity 始终是流水的汇总结果,不需要手动维护
    protected static function booted(): void
    {
        static::created(function (StockMovement $movement) {
            $movement->product()->increment('quantity', $movement->quantity);
        });
    }
}
