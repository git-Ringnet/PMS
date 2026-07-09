<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingRoomSpecialRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_room_id', 'special_request_id', 'note',
    ];

    public function bookingRoom()
    {
        return $this->belongsTo(BookingRoom::class);
    }

    public function specialRequest()
    {
        return $this->belongsTo(SpecialRequest::class);
    }
}
