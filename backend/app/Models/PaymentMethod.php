<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'account',
        'account_name',
        'bank_name',
        'service_charge',
        'department',
        'payment_group',
        'is_free',
        'is_inactive',
    ];

    protected $casts = [
        'service_charge' => 'decimal:2',
        'payment_group' => 'integer',
        'is_free' => 'boolean',
        'is_inactive' => 'boolean',
    ];
}
