<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BookingRoom extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'booking_id',
        'room_number',
        'room_class_id',
        'original_room_class_id',
        'arrival_date',
        'departure_date',
        'arrival_time',
        'departure_time',
        'rate',
        'adults',
        'extra_bed_qty',
        'extra_bed_rate',
        'status',
        'is_do_not_move',
        'note',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'arrival_date'           => 'date',
        'departure_date'         => 'date',
        'rate'                   => 'decimal:2',
        'extra_bed_rate'         => 'decimal:2',
        'status'                 => 'integer',
        'adults'                 => 'integer',
        'extra_bed_qty'          => 'integer',
        'is_do_not_move'         => 'integer',
    ];

    // =========================================
    // STATUS CONSTANTS
    // =========================================
    const STATUS_BOOKED     = 0; // Đã đặt, chưa check-in
    const STATUS_CHECKED_IN = 1; // Đang ở (inhouse)
    const STATUS_CHECKED_OUT = 2; // Đã trả phòng
    const STATUS_CANCELLED  = 3; // Đã hủy

    // =========================================
    // RELATIONSHIPS
    // =========================================

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function room()
    {
        return $this->belongsTo(Room::class, 'room_number', 'room_number');
    }

    public function roomClass()
    {
        return $this->belongsTo(RoomClass::class);
    }

    public function originalRoomClass()
    {
        return $this->belongsTo(RoomClass::class, 'original_room_class_id');
    }

    public function services()
    {
        return $this->hasMany(BookingRoomService::class);
    }

    public function specialRequests()
    {
        return $this->hasMany(BookingRoomSpecialRequest::class);
    }

    public function guests()
    {
        return $this->hasMany(BookingRoomGuest::class);
    }

    public function children()
    {
        return $this->hasMany(BookingChild::class);
    }

    public function doNotMoveLocks()
    {
        return $this->hasMany(RoomDoNotMoveLock::class);
    }

    public function cancelLogs()
    {
        return $this->hasMany(BookingCancelLog::class);
    }

    /**
     * Lock hiện tại đang active (chưa unlock).
     */
    public function activeDoNotMoveLock()
    {
        return $this->hasOne(RoomDoNotMoveLock::class)->whereNull('unlocked_at');
    }

    // =========================================
    // SCOPES
    // =========================================

    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_BOOKED, self::STATUS_CHECKED_IN]);
    }

    public function scopeBooked($query)
    {
        return $query->where('status', self::STATUS_BOOKED);
    }

    public function scopeInhouse($query)
    {
        return $query->where('status', self::STATUS_CHECKED_IN);
    }

    /**
     * Check xem phòng này có đang bị Do Not Move không.
     */
    public function isDoNotMoveLocked(): bool
    {
        return $this->is_do_not_move === 1;
    }
}
