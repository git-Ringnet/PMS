<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingCancelLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'cancel_type',
        'booking_id',
        'booking_room_id',
        'cancel_reason_id',
        'note',
        'cancelled_by_user_id',
        'cancelled_by_username',
        'cancelled_at',
    ];

    protected $casts = [
        'cancelled_at' => 'datetime',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function bookingRoom()
    {
        return $this->belongsTo(BookingRoom::class);
    }

    public function cancelReason()
    {
        return $this->belongsTo(CancelReason::class);
    }
}
