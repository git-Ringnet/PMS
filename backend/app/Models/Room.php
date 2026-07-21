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
}
