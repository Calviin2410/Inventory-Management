<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    protected $fillable = [
        'plate_number',
        'plate_number_normalized',
        'status',
    ];

    protected $hidden = [
        'plate_number_normalized',
    ];

    public static function normalizePlateNumber(?string $plateNumber): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim((string) $plateNumber)));
    }

    public static function formatPlateNumber(?string $plateNumber): string
    {
        return strtoupper(preg_replace('/\s+/', ' ', trim((string) $plateNumber)));
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
