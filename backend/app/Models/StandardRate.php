<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StandardRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_class_id',
        'room_form_id',
        'room_price',
        'extra_bed_price',
    ];

    protected $casts = [
        'room_price' => 'decimal:2',
        'extra_bed_price' => 'decimal:2',
    ];

    public function roomClass(): BelongsTo
    {
        return $this->belongsTo(RoomClass::class);
    }

    public function roomForm(): BelongsTo
    {
        return $this->belongsTo(RoomForm::class);
    }
}
