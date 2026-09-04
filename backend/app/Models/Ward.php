<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ward extends Model
{
    protected $fillable = [
        'code',
        'name',
        'type',
        'district_code',
        'district_name',
        'province_name',
        'order_index',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order_index' => 'integer',
    ];
}
