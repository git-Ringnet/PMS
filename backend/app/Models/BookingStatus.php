<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BookingStatus extends Model
{
    use HasFactory;

    // Cho phép khóa chính id tự định nghĩa (0, 1, 2, 3, 4, 100) và không tự động tăng (auto-increment)
    public $incrementing = false;
    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'name',
        'name_en',
        'description',
    ];

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'status', 'id');
    }

    public function bookingRooms(): HasMany
    {
        return $this->hasMany(BookingRoom::class, 'status', 'id');
    }
}
