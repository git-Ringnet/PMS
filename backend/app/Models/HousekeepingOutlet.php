<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HousekeepingOutlet extends Model
{
    protected $fillable = [
        'code', 'name', 'service_code', 'is_active', 'show_in_add_service',
        'default_service_charge_percent', 'default_tax_percent', 'default_special_tax_percent',
        'order_index'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'show_in_add_service' => 'boolean',
        'default_service_charge_percent' => 'float',
        'default_tax_percent' => 'float',
        'default_special_tax_percent' => 'float',
        'order_index' => 'integer',
    ];
}
