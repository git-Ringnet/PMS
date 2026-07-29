<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BookingRoomService extends Model
{
    use HasFactory, SoftDeletes;

    /** Keep an explicitly allocated amount when splitting by a requested amount. */
    public bool $preserveTotalAmount = false;

    protected $fillable = [
        'booking_room_id',
        'guest_id',
        'service_bill_id',
        'service_bill_detail_no',
        'housekeeping_service_bill_id',
        'housekeeping_service_bill_detail_no',
        'service_code',
        'service_name',
        'service_date',
        'quantity',
        'rate',
        'total_amount',
        'department',
        'note',
        'tax',
        'service_charge',
        'unit',
        'folio',
        'is_room',
        'is_posted',
        'posted_at',
        'created_by',
        'updated_by',
        'deleted_at',
    ];

    protected static function boot()
    {
        parent::boot();

        // Tự động tính total_amount = quantity * rate khi tạo/cập nhật
        static::saving(function ($model) {
            if (!$model->preserveTotalAmount) {
                $model->total_amount = floatval($model->quantity) * floatval($model->rate);
            }
        });
    }

    protected $casts = [
        'service_date'  => 'date',
        'quantity'      => 'decimal:6',
        'rate'          => 'decimal:2',
        'total_amount'  => 'decimal:2',
        'is_room'       => 'integer',
        'folio'         => 'integer',
        'is_posted'     => 'integer',
        'posted_at'     => 'datetime',
    ];

    // Service code constants
    const CODE_ROOM        = 'RM'; // Tiền phòng
    const CODE_EXTRA_BED   = 'EB'; // Thêm giường
    const CODE_BF_CHILD    = 'BD'; // Phụ thu ăn sáng trẻ em

    public function bookingRoom()
    {
        return $this->belongsTo(BookingRoom::class);
    }

    public function guest()
    {
        return $this->belongsTo(Guest::class);
    }
}
