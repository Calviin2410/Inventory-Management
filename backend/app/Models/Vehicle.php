<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    protected $fillable = [
        'plate_number',
        'description',
    ];

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}