<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Room extends Model
{
    protected static function booted(): void
    {
        static::creating(function ($room) {
            if (empty($room->orders) || (int)$room->orders === 0) {
                $maxOrder = static::max('orders');
                $room->orders = $maxOrder ? ((int)$maxOrder + 1) : 1;
            }
        });
    }

    protected $fillable = [
        'room_number',
        'room_form_id',
        'room_class_id',
        'max_guests',
        'floor',
        'area',
        'extra_beds_limit',
        'grid_row',
        'grid_column',
        'owner_room',
        'linked_room',
        'is_internal',
        'room_status_code',
        'status',
        'notes',
        'orders',
    ];

    /**
     * Accessor cho thuộc tính legacy $room->status (suy ra từ room_status_code)
     */
    public function getStatusAttribute(): string
    {
        return match($this->attributes['room_status_code'] ?? 'vacant_ready') {
            'vacant_dirty', 'occupied_dirty' => 'dirty',
            'turndown'                        => 'checkout',
            'ooo', 'occupied_ooo'             => 'maintenance',
            'oos'                             => 'maintenance',
            'housekeeping'                    => 'maintenance',
            'dnd'                             => 'dnd',
            'vacant_priority'                 => 'reserved',
            default                           => 'available',
        };
    }

    /**
     * Mutator cho thuộc tính legacy $room->status (ánh xá sang room_status_code)
     */
    public function setStatusAttribute($value): void
    {
        // Nếu status mới đã tương thích với getStatusAttribute() hiện tại (ví dụ: housekeeping, oos đã tương ứng 'maintenance') thì không ghi đè room_status_code
        if (isset($this->attributes['room_status_code']) && $this->getStatusAttribute() === $value) {
            return;
        }

        $code = match($value) {
            'dirty'       => 'vacant_dirty',
            'checkout'    => 'turndown',
            'maintenance' => 'ooo',
            'dnd'         => 'dnd',
            'reserved'    => 'vacant_priority',
            'occupied'    => 'occupied_ready',
            default       => 'vacant_ready',
        };
        $this->attributes['room_status_code'] = $code;
    }

    protected $casts = [
        'max_guests' => 'integer',
        'extra_beds_limit' => 'integer',
        'grid_row' => 'integer',
        'grid_column' => 'integer',
        'is_internal' => 'boolean',
        'orders' => 'integer',
    ];

    public function roomForm(): BelongsTo
    {
        return $this->belongsTo(RoomForm::class);
    }

    public function roomClass(): BelongsTo
    {
        return $this->belongsTo(RoomClass::class);
    }

    public function roomStatus(): BelongsTo
    {
        return $this->belongsTo(RoomStatus::class, 'room_status_code', 'code');
    }

    public function locks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RoomLock::class, 'room_number', 'room_number');
    }

    public function activeLock(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(RoomLock::class, 'room_number', 'room_number')->where('is_active', 1);
    }

    public function allActiveLocks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RoomLock::class, 'room_number', 'room_number')->where('is_active', 1)->orderBy('start_date');
    }

    /**
     * Scope chỉ lấy phòng thực tế (bỏ phòng nội bộ và phòng ảo có số phòng bắt đầu bằng 0)
     */
    public function scopePhysical($query)
    {
        return $query->where('is_internal', false)
            ->where('room_number', 'not like', '0%');
    }

    /**
     * Scope lấy phòng ảo / phòng nội bộ theo đúng quy ước nghiệp vụ.
     */
    public function scopeVirtual($query)
    {
        return $query->where(function ($query) {
            $query->where('is_internal', true)
                ->orWhere('room_number', 'like', '0%');
        });
    }

    /**
     * Kiểm tra phòng ảo: phòng nội bộ hoặc số phòng bắt đầu bằng 0.
     */
    public function getIsVirtualAttribute(): bool
    {
        return $this->is_internal || str_starts_with((string) $this->room_number, '0');
    }
}
