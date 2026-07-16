<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingChild extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id', 'booking_room_id', 'full_name', 'title',
        'dob', 'nationality_code', 'age_group', 'child_status',
    ];

    protected $casts = [
        'child_status' => 'integer',
        'dob'          => 'date',
    ];


    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function bookingRoom()
    {
        return $this->belongsTo(BookingRoom::class);
    }

    public function breakfastDetails()
    {
        return $this->hasMany(BookingChildBreakfastDetail::class);
    }
}
