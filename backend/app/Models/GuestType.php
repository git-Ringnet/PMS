<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GuestType extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'order_index',
        'is_active',
    ];

    protected $casts = [
        'order_index' => 'integer',
        'is_active'   => 'boolean',
    ];
}
