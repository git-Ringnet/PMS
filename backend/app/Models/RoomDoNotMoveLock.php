<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoomDoNotMoveLock extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_room_id',
        'locked_by_user_id',
        'locked_by_username',
        'locked_at',
        'unlocked_by_user_id',
        'unlocked_by_username',
        'unlocked_at',
        'note',
    ];

    protected $casts = [
        'locked_at'   => 'datetime',
        'unlocked_at' => 'datetime',
    ];

    public function bookingRoom()
    {
        return $this->belongsTo(BookingRoom::class);
    }

    /**
     * Kiểm tra lock này đang active (chưa unlock).
     */
    public function isActive(): bool
    {
        return is_null($this->unlocked_at);
    }
}
