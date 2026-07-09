<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BookingRoomService extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'booking_room_id',
        'service_code',
        'service_name',
        'service_date',
        'quantity',
        'rate',
        'is_room',
        'is_posted',
        'posted_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'service_date' => 'date',
        'quantity'     => 'decimal:2',
        'rate'         => 'decimal:2',
        'is_room'      => 'integer',
        'is_posted'    => 'integer',
        'posted_at'    => 'datetime',
    ];

    // Service code constants
    const CODE_ROOM        = 'RM'; // Tiền phòng
    const CODE_EXTRA_BED   = 'EB'; // Thêm giường
    const CODE_BF_CHILD    = 'BD'; // Phụ thu ăn sáng trẻ em

    public function bookingRoom()
    {
        return $this->belongsTo(BookingRoom::class);
    }
}
