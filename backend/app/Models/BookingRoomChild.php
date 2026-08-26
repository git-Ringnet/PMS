<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingRoomChild extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $assignment): void {
            $room = BookingRoom::find($assignment->booking_room_id);
            if (!$room) {
                return;
            }

            $assignment->actual_checkout_date = $assignment->actual_checkout_date ?: $room->departure_date;
            $assignment->actual_checkout_time = $assignment->actual_checkout_time ?: '12:00:00';
        });
    }

    protected $fillable = [
        'booking_child_id',
        'booking_room_id',
        'status',
        'actual_arrival_date',
        'actual_arrival_time',
        'actual_checkout_date',
        'actual_checkout_time',
        'checkin_by',
        'checkout_by',
    ];

    protected $casts = [
        'status' => 'integer',
        'actual_arrival_date' => 'date',
        'actual_checkout_date' => 'date',
    ];

    public function child()
    {
        return $this->belongsTo(BookingChild::class, 'booking_child_id');
    }

    public function room()
    {
        return $this->belongsTo(BookingRoom::class, 'booking_room_id');
    }
}
