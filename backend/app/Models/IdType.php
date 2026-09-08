<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IdType extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'order_index',
        'is_active',
    ];

    protected $casts = [
        'order_index' => 'integer',
        'is_active'   => 'boolean',
    ];
}
