<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemDateRoll extends Model
{
    use HasFactory;

    protected $fillable = [
        'system_date',
        'actual_date',
        'shift',
        'username',
    ];

    protected $casts = [
        'system_date' => 'datetime',
        'actual_date' => 'datetime',
    ];
}
