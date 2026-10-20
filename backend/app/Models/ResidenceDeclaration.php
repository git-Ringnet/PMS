<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Danh sách khách đã khai báo lưu trú (SP8007)
 */
class ResidenceDeclaration extends Model
{
    protected $table = 'residence_declarations';

    protected $fillable = [
        'guest_id',
        'guest_kind',
        'booking_id',
        'booking_room_id',
        'declare_date',
        'declarer',
        'status',
    ];

    protected $casts = [
        'booking_id' => 'integer',
        'status' => 'integer',
        'declare_date' => 'datetime',
    ];
}
