<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Guest extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $lastGuest = self::where('id', 'like', 'K%')
                    ->orderByRaw('CAST(SUBSTRING(id, 2) AS UNSIGNED) DESC')
                    ->first();
                $nextNum = 1;
                if ($lastGuest && preg_match('/^K(\d+)$/', $lastGuest->id, $matches)) {
                    $nextNum = intval($matches[1]) + 1;
                }
                $model->id = 'K' . str_pad($nextNum, 9, '0', STR_PAD_LEFT);
            }
        });
    }

    const STATUS_ACTIVE = 1;

    protected $fillable = [
        'full_name', 'title', 'id_type', 'id_number', 'id_issue_date',
        'passport_number', 'passport_expiry', 'dob', 'gender', 'nationality_code',
        'phone', 'email', 'address', 'guest_type',
        'province', 'district', 'ward',
        'residence_type', 'temp_residence_to',
        'visa_no', 'entry_date', 'visa_expiry_date',
        'entry_purpose', 'border_gate', 'occupation',
        'note', 'guest_status', 'avatar',
    ];

    protected $casts = [
        'dob'              => 'date',
        'id_issue_date'    => 'date',
        'passport_expiry'  => 'date',
        'temp_residence_to'=> 'date',
        'entry_date'       => 'date',
        'visa_expiry_date' => 'date',
        'gender'           => 'integer',
        'guest_status'     => 'integer',
    ];


    public function bookingRoomGuests()
    {
        return $this->hasMany(BookingRoomGuest::class);
    }
}
