<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NoshowLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_room_id',
        'noshow_date',
        'noshow_time',
        'reason',
        'status',
        'username',
        'shift',
    ];

    protected $casts = [
        'noshow_date' => 'datetime',
    ];

    public function bookingRoom()
    {
        return $this->belongsTo(BookingRoom::class);
    }
}
