<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_no',
        'customer_id',
        'driver_id',
        'vehicle_id',
        'user_id',
        'issued_date',
        'address',
        'status',
        'payment_method',
        'payment_date',
        'settlement_status',
        'settlement_remark',
        'settled_at',
        'settled_by',
        'total_amount',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'issued_date' => 'date:Y-m-d',
            'payment_date' => 'date:Y-m-d',
            'settled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function settledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'settled_by');
    }
}
