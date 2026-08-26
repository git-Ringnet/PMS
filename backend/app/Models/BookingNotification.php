<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id', 'scope_type', 'booking_room_ids', 'starts_on', 'ends_on',
        'description', 'created_by_user_id', 'updated_by_user_id',
    ];

    protected $casts = [
        'booking_room_ids' => 'array',
        'starts_on' => 'date',
        'ends_on' => 'date',
    ];

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
