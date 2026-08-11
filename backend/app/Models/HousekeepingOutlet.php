<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HousekeepingOutlet extends Model
{
    protected $fillable = [
        'code', 'name', 'group_key', 'service_code', 'is_active', 'order_index'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order_index' => 'integer',
    ];
}
