<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomRateCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'description',
        'room_class_id',
        'room_form_id',
        'adults',
        'children',
        'start_date',
        'end_date',
        'price',
        'breakfast_price',
        'extra_bed_price',
        'has_breakfast',
        'is_allowed',
        'rate_type',
    ];

    protected $casts = [
        'adults' => 'integer',
        'children' => 'integer',
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'price' => 'decimal:2',
        'breakfast_price' => 'decimal:2',
        'extra_bed_price' => 'decimal:2',
        'has_breakfast' => 'boolean',
        'is_allowed' => 'boolean',
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
