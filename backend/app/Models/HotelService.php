<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HotelService extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'service_charge',
        'tax',
        'special_tax',
        'include_service_charge',
        'include_tax',
        'include_special_tax',
        'folio',
        'short_name',
        'unit',
        'price',
        'department',
    ];

    protected $casts = [
        'include_service_charge' => 'boolean',
        'include_tax' => 'boolean',
        'include_special_tax' => 'boolean',
        'price' => 'float',
        'service_charge' => 'float',
        'tax' => 'float',
        'special_tax' => 'float',
    ];
}
