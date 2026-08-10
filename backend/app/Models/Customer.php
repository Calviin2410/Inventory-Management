<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'phone'];

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function rentedBarrels(): HasMany
    {
        return $this->hasMany(Barrel::class, 'current_customer_id');
    }
}
