<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GuestTitle extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'gender',
        'is_adult',
        'is_infant',
        'order_index',
        'is_active',
    ];

    protected $casts = [
        'gender'      => 'integer',
        'is_adult'    => 'boolean',
        'is_infant'   => 'boolean',
        'order_index' => 'integer',
        'is_active'   => 'boolean',
    ];
}
