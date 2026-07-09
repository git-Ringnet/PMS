<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Guest extends Model
{
    use HasFactory;

    protected $fillable = [
        'full_name', 'id_number', 'passport_number',
        'dob', 'gender', 'nationality_code',
        'phone', 'email', 'address', 'guest_status',
    ];

    protected $casts = [
        'dob'          => 'date',
        'gender'       => 'integer',
        'guest_status' => 'integer',
    ];

    public function bookingRoomGuests()
    {
        return $this->hasMany(BookingRoomGuest::class);
    }
}
