<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RegistrationStatus extends Model
{
    use HasFactory;

    protected $table = 'registration_statuses';

    protected $fillable = [
        'name',
        'color',
        'confirmation_days',
        'description',
        'status_value',
        'is_hidden',
        'is_availability',
        'bk_definite',   // 4 = trạng thái tự chuyển khi hủy booking
    ];

    protected $casts = [
        'confirmation_days' => 'integer',
        'is_hidden' => 'boolean',
        'is_availability' => 'boolean',
        'bk_definite' => 'integer',
    ];
}
