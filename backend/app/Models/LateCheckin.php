<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LateCheckin extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_room_id',
        'late_checkin_date',
        'actual_arrival_date',
        'late_checkin_time',
        'reason',
        'status',
        'username',
        'shift',
    ];

    protected $casts = [
        'late_checkin_date' => 'datetime',
        'actual_arrival_date' => 'datetime',
    ];

    public function bookingRoom()
    {
        return $this->belongsTo(BookingRoom::class);
    }
}
