<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookingChild extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $lastChild = self::where('id', 'like', 'T%')
                    ->orderByRaw('CAST(SUBSTRING(id, 2) AS UNSIGNED) DESC')
                    ->first();
                $nextNum = 1;
                if ($lastChild && preg_match('/^T(\d+)$/', $lastChild->id, $matches)) {
                    $nextNum = intval($matches[1]) + 1;
                }
                $model->id = 'T' . str_pad($nextNum, 9, '0', STR_PAD_LEFT);
            }
        });
    }

    protected $fillable = [
        'booking_id', 'booking_room_id', 'full_name', 'title',
        'dob', 'nationality_code', 'age_group', 'child_status',
    ];

    protected $casts = [
        'child_status' => 'integer',
        'dob'          => 'date',
    ];


    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function bookingRoom()
    {
        return $this->belongsTo(BookingRoom::class);
    }

    public function breakfastDetails()
    {
        return $this->hasMany(BookingChildBreakfastDetail::class);
    }
}
