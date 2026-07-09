<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingRoomGuest extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_room_id', 'guest_id', 'is_primary', 'status',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'status'     => 'integer',
    ];

    public function bookingRoom()
    {
        return $this->belongsTo(BookingRoom::class);
    }

    public function guest()
    {
        return $this->belongsTo(Guest::class);
    }
}
