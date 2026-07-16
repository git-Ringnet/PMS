<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Guest extends Model
{
    use HasFactory;

    const STATUS_ACTIVE = 1;

    protected $fillable = [
        'full_name', 'title', 'id_type', 'id_number', 'id_issue_date',
        'passport_number', 'passport_expiry', 'dob', 'gender', 'nationality_code',
        'phone', 'email', 'address', 'guest_type',
        'province', 'district', 'ward',
        'residence_type', 'temp_residence_to',
        'visa_no', 'entry_date', 'visa_expiry_date',
        'entry_purpose', 'border_gate', 'occupation',
        'note', 'guest_status',
    ];

    protected $casts = [
        'dob'              => 'date',
        'id_issue_date'    => 'date',
        'passport_expiry'  => 'date',
        'temp_residence_to'=> 'date',
        'entry_date'       => 'date',
        'visa_expiry_date' => 'date',
        'gender'           => 'integer',
        'guest_status'     => 'integer',
    ];


    public function bookingRoomGuests()
    {
        return $this->hasMany(BookingRoomGuest::class);
    }
}
