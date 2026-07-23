<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Room extends Model
{
    use HasFactory;

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
        'status',
        'notes',
        'orders',
    ];

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
     * Scope chỉ lấy phòng thực tế (bỏ phòng ảo / phòng nội bộ: floor=0, grid_row=0, grid_column=0 hoặc is_internal=true)
     */
    public function scopePhysical($query)
    {
        return $query->where('is_internal', false)
            ->where(function ($q) {
                $q->whereNotIn('floor', ['0', 0, 'Floor 0', 'Tầng 0', 'Floor virtual', 'Virtual'])
                  ->orWhere('grid_row', '>', 0)
                  ->orWhere('grid_column', '>', 0);
            });
    }

    /**
     * Scope lấy phòng ảo / phòng nội bộ (floor = 0 & grid_row = 0 & grid_column = 0 HOẶC is_internal = true)
     */
    public function scopeVirtual($query)
    {
        return $query->where(function ($q) {
            $q->where('is_internal', true)
              ->orWhere(function ($sub) {
                  $sub->whereIn('floor', ['0', 0, 'Floor 0', 'Tầng 0', 'Floor virtual', 'Virtual'])
                      ->where('grid_row', 0)
                      ->where('grid_column', 0);
              });
        });
    }

    /**
     * Accessor kiểm tra xem phòng có phải phòng ảo không
     */
    public function getIsVirtualAttribute(): bool
    {
        if ($this->is_internal) {
            return true;
        }
        $floorStr = trim((string)$this->floor);
        $isFloorZero = in_array($floorStr, ['0', 'Floor 0', 'Tầng 0', 'Floor virtual', 'Virtual'], true);
        $isRowZero = (int)$this->grid_row === 0;
        $isColZero = (int)$this->grid_column === 0;

        if ($isFloorZero && $isRowZero && $isColZero) {
            return true;
        }

        return false;
    }
}
