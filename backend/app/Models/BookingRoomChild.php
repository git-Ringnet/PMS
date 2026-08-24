<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingRoomChild extends Model
{
    protected $fillable = ['booking_child_id', 'booking_room_id', 'status'];

    protected $casts = ['status' => 'integer'];

    public function child()
    {
        return $this->belongsTo(BookingChild::class, 'booking_child_id');
    }

    public function room()
    {
        return $this->belongsTo(BookingRoom::class, 'booking_room_id');
    }
}