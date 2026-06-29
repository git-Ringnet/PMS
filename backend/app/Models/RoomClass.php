<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoomClass extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'color',
        'is_active',
        'room_class_group_id',
        'notes',
        'image_path',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function roomClassGroup(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(RoomClassGroup::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function standardRates(): HasMany
    {
        return $this->hasMany(StandardRate::class);
    }
}
