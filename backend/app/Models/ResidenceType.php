<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ResidenceType extends Model
{
    protected $table = 'residence_types';

    protected $fillable = [
        'code',
        'name',
        'name_new_form',
        'order_index',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order_index' => 'integer',
    ];
}
