<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'country',
        'short_name',
        'decimals_to_round',
        'is_main',
        'is_active',
        'exchange_rate',
        'image_path',
    ];

    protected $casts = [
        'decimals_to_round' => 'integer',
        'is_main' => 'boolean',
        'is_active' => 'boolean',
        'exchange_rate' => 'decimal:4',
    ];
}
