<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'phone', 'phone_normalized'];

    protected $hidden = ['phone_normalized'];

    public static function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '60')) {
            $digits = '0'.substr($digits, 2);
        }

        return $digits;
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function rentedBarrels(): HasMany
    {
        return $this->hasMany(Barrel::class, 'current_customer_id');
    }
}
