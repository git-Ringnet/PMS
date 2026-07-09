<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingChildBreakfastDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_child_id', 'service_date',
        'breakfast', 'is_free', 'is_extra_charge', 'is_room', 'amount',
    ];

    protected $casts = [
        'service_date'    => 'date',
        'breakfast'       => 'boolean',
        'is_free'         => 'boolean',
        'is_extra_charge' => 'boolean',
        'is_room'         => 'boolean',
        'amount'          => 'decimal:2',
    ];

    public function bookingChild()
    {
        return $this->belongsTo(BookingChild::class);
    }
}
