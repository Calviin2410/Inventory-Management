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
		'waste_sale_amount',
		'waste_sale_remark',
		'waste_sale_recorded_at',
		'waste_sale_recorded_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'issued_date' => 'date:Y-m-d',
            'payment_date' => 'date:Y-m-d',
            'settled_at' => 'datetime',
			'total_amount' => 'decimal:2',
			'waste_sale_amount' => 'decimal:2',
			'waste_sale_recorded_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function settledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'settled_by');
    }

	public function wasteSaleRecordedBy(): BelongsTo
	{
		return $this->belongsTo(User::class, 'waste_sale_recorded_by');
	}
}
