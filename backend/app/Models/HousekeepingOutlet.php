<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HousekeepingOutlet extends Model
{
    protected $fillable = [
        'code', 'name', 'service_code', 'is_active', 'show_in_add_service', 'order_index'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'show_in_add_service' => 'boolean',
        'order_index' => 'integer',
    ];
}
