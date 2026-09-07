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

            // Set created_by and updated_by
            $currentUser = auth()->user()?->username ?? 'system';
            $model->created_by = $model->created_by ?: $currentUser;
            $model->updated_by = $model->updated_by ?: $currentUser;

            // Format original_room_class_id to "room_class_id-room_form_id"
            if (!empty($model->original_room_class_id)) {
                if (strpos((string)$model->original_room_class_id, '-') === false) {
                    $classId = $model->original_room_class_id;
                    $standardRate = \App\Models\StandardRate::where('room_class_id', $classId)->first();
                    $formId = $standardRate ? $standardRate->room_form_id : 1;
                    $model->original_room_class_id = "{$classId}-{$formId}";
                }
            } else if (!empty($model->room_class_id)) {
                $classId = $model->room_class_id;
                $standardRate = \App\Models\StandardRate::where('room_class_id', $classId)->first();
                $formId = $standardRate ? $standardRate->room_form_id : 1;
                $model->original_room_class_id = "{$classId}-{$formId}";
            }

            // Set RoomKind
            if (!empty($model->room_class_id)) {
                $standardRate = \App\Models\StandardRate::where('room_class_id', $model->room_class_id)->first();
                $roomForm = $standardRate ? \App\Models\RoomForm::find($standardRate->room_form_id) : null;
                $model->RoomKind = $roomForm ? $roomForm->id : null;
            }
            if ($model->ActutalNumOfDays === null && !empty($model->arrival_date) && !empty($model->departure_date)) {
                $arr = \Carbon\Carbon::parse($model->arrival_date);
                $dep = \Carbon\Carbon::parse($model->departure_date);
                $diff = $arr->diffInDays($dep);
                $model->ActutalNumOfDays = $diff > 0 ? $diff : 1;
            }
            // Giữ riêng kế hoạch ban đầu để nhận diện checkout sớm sau khi ngày đi thực tế thay đổi.
            $model->planned_departure_date = $model->planned_departure_date ?: $model->departure_date;
            $model->NumOfDays = $model->NumOfDays ?: $model->ActutalNumOfDays;

            // Phòng chưa checkout luôn giữ lịch checkout dự kiến để tương thích legacy.
            if (in_array((int) $model->status, [self::STATUS_BOOKED, self::STATUS_CHECKED_IN], true)) {
                $model->CheckoutDate = $model->CheckoutDate ?: $model->departure_date;
                $model->CheckoutTime = $model->CheckoutTime ?: '12:00:00';
            }
        });

        static::updating(function ($model) {
            $model->updated_by = auth()->user()?->username ?? 'system';

            // Keep original_room_class_id unchanged on updates
            if ($model->isDirty('original_room_class_id')) {
                $model->original_room_class_id = $model->getOriginal('original_room_class_id');
            }

            // Update RoomKind if room_class_id changes
            if ($model->isDirty('room_class_id') && !empty($model->room_class_id)) {
                $standardRate = \App\Models\StandardRate::where('room_class_id', $model->room_class_id)->first();
                $roomForm = $standardRate ? \App\Models\RoomForm::find($standardRate->room_form_id) : null;
                $model->RoomKind = $roomForm ? $roomForm->id : null;
            }
            if ($model->isDirty('arrival_date') || $model->isDirty('departure_date') || $model->ActutalNumOfDays === null) {
                if (!empty($model->arrival_date) && !empty($model->departure_date)) {
                    $arr = \Carbon\Carbon::parse($model->arrival_date);
                    $dep = \Carbon\Carbon::parse($model->departure_date);
                    $diff = $arr->diffInDays($dep);
                    $model->ActutalNumOfDays = $diff > 0 ? $diff : 1;
                }
            }

            // Cho phép sửa kế hoạch khi còn là reservation; từ lúc check-in phải giữ nguyên để nhận diện gia hạn/trả sớm.
            if ((int) $model->getOriginal('status') === self::STATUS_BOOKED
                && ($model->isDirty('arrival_date') || $model->isDirty('departure_date'))) {
                $model->planned_departure_date = $model->departure_date;
                $model->NumOfDays = $model->ActutalNumOfDays;
            }

            if ($model->isDirty('departure_date')
                && in_array((int) $model->status, [self::STATUS_BOOKED, self::STATUS_CHECKED_IN], true)) {
                $model->CheckoutDate = $model->departure_date;
                $model->CheckoutTime = '12:00:00';
            }
        });

        static::updated(function ($model) {
            if ($model->wasChanged('status') && (int) $model->status === self::STATUS_CHECKED_OUT) {
                $model->children()
                    ->whereIn('child_status', [BookingRoomGuest::STATUS_ACTIVE, BookingRoomGuest::STATUS_CHECKED_IN])
                    ->update(['child_status' => BookingRoomGuest::STATUS_CHECKED_OUT]);

                $model->childAssignments()
                    ->whereIn('status', [BookingRoomGuest::STATUS_ACTIVE, BookingRoomGuest::STATUS_CHECKED_IN])
                    ->update([
                        'status' => BookingRoomGuest::STATUS_CHECKED_OUT,
                        'actual_checkout_date' => $model->CheckoutDate?->toDateString() ?: $model->departure_date?->toDateString(),
                        'actual_checkout_time' => $model->CheckoutTime ?: now()->format('H:i:s'),
                        'checkout_by' => $model->check_out_user,
                    ]);
            }

            $isActive = in_array((int) $model->status, [self::STATUS_BOOKED, self::STATUS_CHECKED_IN], true);
            $restoredToActive = $model->wasChanged('status') && $isActive;

            if (!$isActive || (!$model->wasChanged('departure_date') && !$restoredToActive)) {
                return;
            }

            $departureDate = $model->departure_date?->toDateString();
            if (!$departureDate) {
                return;
            }

            $model->guests()
                ->whereIn('status', [BookingRoomGuest::STATUS_ACTIVE, BookingRoomGuest::STATUS_CHECKED_IN])
                ->update([
                    'actual_checkout_date' => $departureDate,
                    'actual_checkout_time' => '12:00:00',
                ]);

            $childAssignments = $model->childAssignments();
            if ($restoredToActive) {
                $childAssignments->where('status', BookingRoomGuest::STATUS_CHECKED_OUT);
            } else {
                $childAssignments->whereIn('status', [BookingRoomGuest::STATUS_ACTIVE, BookingRoomGuest::STATUS_CHECKED_IN]);
            }
            $childAssignments->update([
                'status' => (int) $model->status,
                'actual_checkout_date' => $departureDate,
                'actual_checkout_time' => '12:00:00',
                'checkout_by' => null,
            ]);
        });

        static::saving(function ($model) {
            $currentUser = auth()->user()?->username ?? 'system';
            if ($model->status === self::STATUS_CHECKED_IN && empty($model->check_in_user)) {
                $model->check_in_user = $currentUser;
            }
            if ($model->status === self::STATUS_CHECKED_OUT && empty($model->check_out_user)) {
                $model->check_out_user = $currentUser;
            }
            if (in_array((int) $model->status, [self::STATUS_BOOKED, self::STATUS_CHECKED_IN], true)) {
                $model->CheckoutDate = $model->CheckoutDate ?: $model->departure_date;
                $model->CheckoutTime = $model->CheckoutTime ?: '12:00:00';
            }
            // If the room is not checked in yet, actual_arrival_date always equals arrival_date
            if ($model->status === self::STATUS_BOOKED) {
                $model->actual_arrival_date = $model->arrival_date;
            } else {
                if (empty($model->actual_arrival_date)) {
                    $model->actual_arrival_date = $model->arrival_date;
                }
            }

            // Tự động tính số đêm (ActutalNumOfDays) của phòng
            if (!empty($model->arrival_date) && !empty($model->departure_date)) {
                $arr = \Carbon\Carbon::parse($model->arrival_date);
                $dep = \Carbon\Carbon::parse($model->departure_date);
                $diff = $arr->diffInDays($dep);
                $model->ActutalNumOfDays = $diff > 0 ? $diff : 1; // Nếu cùng ngày (day use) thì tính 1 ngày
            }

            // Reset giá thêm giường về 0 nếu số lượng giường phụ bằng 0
            if (empty($model->extra_bed_qty) || (int)$model->extra_bed_qty === 0) {
                $model->extra_bed_rate = 0;
            }
        });
    }

     protected $fillable = [
        'booking_id',
        'room_number',
        'room_class_id',
        'RoomKind',
        'original_room_class_id',
        'arrival_date',
        'departure_date',
        'planned_departure_date',
        'NumOfDays',
        'ActutalNumOfDays',
        'actual_arrival_date',
        'arrival_time',
        'departure_time',
        'CheckoutDate',
        'CheckoutTime',
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
        'no_post',
        'move_room',
        'note',
        'reason',
        'updated_by',
        'check_in_user',
        'check_out_user',
        'no_show_day',
    ];

    protected $casts = [
        'arrival_date'           => 'date',
        'departure_date'         => 'date',
        'planned_departure_date' => 'date',
        'NumOfDays'    => 'integer',
        'ActutalNumOfDays'       => 'integer',
        'actual_arrival_date'    => 'date',
        'CheckoutDate'           => 'date',
        'rate'                   => 'decimal:2',
        'extra_bed_rate'         => 'decimal:2',
        'status'                 => 'integer',
        'no_show_day'            => 'integer',
        'adults'                 => 'integer',
        'babies'                 => 'integer',
        'children_qty'           => 'integer',
        'extra_bed_qty'          => 'integer',
        'is_do_not_move'         => 'integer',
        'no_post'                => 'boolean',
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
    const STATUS_NOSHOW     = 4; // Noshow
    const STATUS_MOVED      = 100; // Đã chuyển/gộp sang phòng khác

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

    public function bookingStatus()
    {
        return $this->belongsTo(BookingStatus::class, 'status', 'id');
    }

    public function roomClass()
    {
        return $this->belongsTo(RoomClass::class);
    }

    public function originalRoomClass()
    {
        return $this->belongsTo(RoomClass::class, 'original_room_class_only_id');
    }

    public function getOriginalRoomClassOnlyIdAttribute()
    {
        if (!empty($this->original_room_class_id)) {
            $parts = explode('-', $this->original_room_class_id);
            return (int)$parts[0];
        }
        return null;
    }

    public function services()
    {
        return $this->hasMany(BookingRoomService::class);
    }

    public function lateCheckins()
    {
        return $this->hasMany(LateCheckin::class, 'booking_room_id');
    }

    public function serviceBills()
    {
        return $this->hasMany(ServiceBill::class, 'RentalRoomId1', 'id');
    }

    public function currentServiceBills()
    {
        return $this->hasMany(ServiceBill::class, 'RentalRoomId2', 'id');
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

    public function childAssignments()
    {
        return $this->hasMany(BookingRoomChild::class, 'booking_room_id');
    }

    public function assignedChildren()
    {
        return $this->belongsToMany(BookingChild::class, 'booking_room_children', 'booking_room_id', 'booking_child_id')
            ->withPivot('status')
            ->wherePivot('status', 1);
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
    public function moveToRoom($newRoomNumber, $systemDateStr, $currentUser, $reason = null)
    {
        $systemDate = \Carbon\Carbon::parse($systemDateStr);
        $moveTime = now()->format('H:i:s');
        $originalArrivalDate = $this->actual_arrival_date?->toDateString() ?: $this->arrival_date->toDateString();
        
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
        $attributes['CheckoutDate'] = $this->departure_date->toDateString();
        $attributes['CheckoutTime'] = '12:00:00';
        
        // Tạo phòng mới
        $newRoom = self::create($attributes);
        
        // Chuyển / Sao chép Khách lưu trú
        foreach ($this->guests as $gPivot) {
            \App\Models\BookingRoomGuest::create([
                'booking_room_id' => $newRoom->id,
                'guest_id' => $gPivot->guest_id,
                'is_primary' => $gPivot->is_primary,
                'status' => BookingRoomGuest::STATUS_CHECKED_IN,
                'actual_arrival_date' => $systemDate->toDateString(),
                'actual_arrival_time' => $moveTime,
                'actual_checkout_date' => $gPivot->actual_checkout_date ?: $this->departure_date->toDateString(),
                'actual_checkout_time' => '12:00:00',
                'checkin_by' => $currentUser,
                'breakfast' => $gPivot->breakfast,
            ]);
        }

        foreach ($this->guests as $gPivot) {
            $gPivot->update([
                'status' => self::STATUS_MOVED,
                'actual_arrival_date' => $gPivot->actual_arrival_date ?: $originalArrivalDate,
                'actual_arrival_time' => $gPivot->actual_arrival_time ?: ($this->arrival_time ?: $moveTime),
                'actual_checkout_date' => $systemDate->toDateString(),
                'actual_checkout_time' => $moveTime,
                'checkout_by' => $currentUser,
            ]);
        }
        
        // Chuyển Trẻ em / Em bé
        foreach ($this->children as $child) {
            BookingRoomChild::updateOrCreate(
                ['booking_child_id' => $child->id, 'booking_room_id' => $this->id],
                [
                    'status' => self::STATUS_MOVED,
                    'actual_checkout_date' => $systemDate->toDateString(),
                    'actual_checkout_time' => $moveTime,
                    'checkout_by' => $currentUser,
                ]
            );
            BookingRoomChild::updateOrCreate(
                ['booking_child_id' => $child->id, 'booking_room_id' => $newRoom->id],
                [
                    'status' => BookingRoomGuest::STATUS_CHECKED_IN,
                    'actual_arrival_date' => $systemDate->toDateString(),
                    'actual_arrival_time' => $moveTime,
                    'actual_checkout_date' => $this->departure_date->toDateString(),
                    'actual_checkout_time' => '12:00:00',
                    'checkin_by' => $currentUser,
                    'checkout_by' => null,
                ]
            );
            $child->update([
                'booking_room_id' => $newRoom->id,
                'child_status' => BookingRoomGuest::STATUS_CHECKED_IN,
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
            'departure_time' => $moveTime,
            'status' => self::STATUS_MOVED,
            'move_room' => $newRoom->id,
            'CheckoutDate' => $systemDate->toDateString(),
            'CheckoutTime' => $moveTime,
            'reason' => $reason ?: $this->reason,
            'note' => trim(($this->note ? $this->note . ' | ' : '') . "Đã chuyển sang phòng {$newRoomNumber}" . ($reason ? ": {$reason}" : '')),
            'check_out_user' => $currentUser,
        ]);
        
        return $newRoom;
    }
}
