<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingRoomGuest extends Model
{
    use HasFactory;

    // =========================================
    // STATUS CONSTANTS (đồng bộ với BookingRoom)
    // =========================================
    const STATUS_ACTIVE      = 0; // Đang ở / Active
    const STATUS_CHECKED_IN  = 1; // Đã check-in (inhouse)
    const STATUS_CHECKED_OUT = 2; // Đã checkout lẻ (checkout sớm/muộn)
    const STATUS_CANCELLED   = 3; // Đã hủy (cascade từ hủy phòng)

    protected $fillable = [
        'booking_room_id',
        'guest_id',
        'is_primary',
        'status',
        // Ngày nghiệp vụ thực tế của từng khách
        'actual_arrival_date',
        'actual_arrival_time',
        'actual_checkout_date',
        'actual_checkout_time',
        'checkin_by',
        'checkout_by',
        // Ăn sáng riêng: NULL = kế thừa từ booking_room.breakfast
        'breakfast',
    ];

    protected $casts = [
        'is_primary'           => 'boolean',
        'status'               => 'integer',
        'actual_arrival_date'  => 'date',
        'actual_checkout_date' => 'date',
        'breakfast'            => 'boolean',
    ];

    // =========================================
    // RELATIONSHIPS
    // =========================================

    public function bookingRoom()
    {
        return $this->belongsTo(BookingRoom::class);
    }

    public function guest()
    {
        return $this->belongsTo(Guest::class);
    }
}
