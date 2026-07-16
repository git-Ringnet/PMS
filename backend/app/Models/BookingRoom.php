<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BookingRoom extends Model
{
    use HasFactory, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $lastRoom = self::withTrashed()
                    ->where('id', 'like', 'G%')
                    ->orderByRaw('CAST(SUBSTRING(id, 2) AS UNSIGNED) DESC')
                    ->first();
                $nextNum = 1;
                if ($lastRoom && preg_match('/^G(\d+)$/', $lastRoom->id, $matches)) {
                    $nextNum = intval($matches[1]) + 1;
                }
                $model->id = 'G' . str_pad($nextNum, 7, '0', STR_PAD_LEFT);
            }
        });

        static::saving(function ($model) {
            $currentUser = auth()->user()?->username ?? 'system';
            if ($model->status === self::STATUS_CHECKED_IN && empty($model->check_in_user)) {
                $model->check_in_user = $currentUser;
            }
            if ($model->status === self::STATUS_CHECKED_OUT && empty($model->check_out_user)) {
                $model->check_out_user = $currentUser;
            }
            // If the room is not checked in yet, actual_arrival_date always equals arrival_date
            if ($model->status === self::STATUS_BOOKED) {
                $model->actual_arrival_date = $model->arrival_date;
            } else {
                if (empty($model->actual_arrival_date)) {
                    $model->actual_arrival_date = $model->arrival_date;
                }
            }

            // Tự động tính số đêm (num_of_days) của phòng
            if (!empty($model->arrival_date) && !empty($model->departure_date)) {
                $arr = \Carbon\Carbon::parse($model->arrival_date);
                $dep = \Carbon\Carbon::parse($model->departure_date);
                $diff = $arr->diffInDays($dep);
                $model->num_of_days = $diff > 0 ? $diff : 1; // Nếu cùng ngày (day use) thì tính 1 ngày
            }
        });
    }

     protected $fillable = [
        'booking_id',
        'room_number',
        'room_class_id',
        'original_room_class_id',
        'arrival_date',
        'departure_date',
        'num_of_days',
        'actual_arrival_date',
        'arrival_time',
        'departure_time',
        'rate',
        'rate_code',
        'breakfast',
        'is_day_use',
        'discount',
        'discount_type',
        'discount_value',
        'discount_unit',
        'base_price',
        'adults',
        'babies',
        'children_qty',
        'extra_bed_qty',
        'extra_bed_rate',
        'status',
        'is_do_not_move',
        'move_room',
        'note',
        'created_by',
        'updated_by',
        'check_in_user',
        'check_out_user',
    ];

    protected $casts = [
        'arrival_date'           => 'date',
        'departure_date'         => 'date',
        'num_of_days'            => 'integer',
        'actual_arrival_date'    => 'date',
        'rate'                   => 'decimal:2',
        'extra_bed_rate'         => 'decimal:2',
        'status'                 => 'integer',
        'adults'                 => 'integer',
        'babies'                 => 'integer',
        'children_qty'           => 'integer',
        'extra_bed_qty'          => 'integer',
        'is_do_not_move'         => 'integer',
        'breakfast'              => 'boolean',
        'is_day_use'             => 'boolean',
        'discount_value'         => 'decimal:2',
        'base_price'             => 'decimal:2',
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

    /**
     * Thực hiện chuyển phòng (Room Move/Transfer) cho phòng đang CheckedIn.
     * Tự động chia tách folio, cập nhật trạng thái phòng cũ và tạo phòng mới.
     */
    public function moveToRoom($newRoomNumber, $systemDateStr, $currentUser)
    {
        $systemDate = \Carbon\Carbon::parse($systemDateStr);
        
        $attributes = $this->getAttributes();
        unset($attributes['id']);
        unset($attributes['created_at']);
        unset($attributes['updated_at']);
        unset($attributes['deleted_at']);
        
        $attributes['room_number'] = $newRoomNumber;
        $attributes['arrival_date'] = $systemDate->toDateString();
        $attributes['actual_arrival_date'] = $this->actual_arrival_date 
            ? $this->actual_arrival_date->toDateString() 
            : $this->arrival_date->toDateString();
        $attributes['status'] = self::STATUS_CHECKED_IN;
        $attributes['check_in_user'] = $this->check_in_user ?? $currentUser;
        $attributes['check_out_user'] = null;
        $attributes['move_room'] = null;
        
        // Tạo phòng mới
        $newRoom = self::create($attributes);
        
        // Chuyển / Sao chép Khách lưu trú
        foreach ($this->guests as $gPivot) {
            \App\Models\BookingRoomGuest::create([
                'booking_room_id' => $newRoom->id,
                'guest_id' => $gPivot->guest_id,
                'is_primary' => $gPivot->is_primary,
            ]);
        }
        
        // Chuyển Trẻ em / Em bé
        foreach ($this->children as $child) {
            $child->update([
                'booking_room_id' => $newRoom->id,
            ]);
        }
        
        // Chuyển các dịch vụ trong tương lai (từ ngày hệ thống trở đi) sang phòng mới
        foreach ($this->services as $service) {
            $sDate = \Carbon\Carbon::parse($service->service_date);
            if ($sDate->greaterThanOrEqualTo($systemDate)) {
                $service->update([
                    'booking_room_id' => $newRoom->id,
                ]);
            }
        }
        
        // Cập nhật thông tin phòng cũ (đã chuyển)
        $this->update([
            'departure_date' => $systemDate->toDateString(),
            'status' => self::STATUS_CHECKED_OUT,
            'move_room' => $newRoom->id,
            'check_out_user' => $currentUser,
        ]);
        
        return $newRoom;
    }
}
