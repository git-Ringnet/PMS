<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RegistrationStatus extends Model
{
    use HasFactory;

    protected $table = 'registration_statuses';

    protected $fillable = [
        'id',
        'booking_status_id',
        'name',
        'color',
        'cut_off_day',
        'confirmation_days',
        'description',
        'status_value',
        'is_hidden',
        'is_availability',
        'bk_definite',   // 4 = trạng thái tự chuyển khi hủy booking
        'vietnamese',
        'english',
        'order_index',
    ];

    protected $casts = [
        'booking_status_id' => 'integer',
        'cut_off_day' => 'integer',
        'confirmation_days' => 'integer',
        'is_hidden' => 'boolean',
        'is_availability' => 'boolean',
        'bk_definite' => 'integer',
        'order_index' => 'integer',
    ];

    public function getCutoffDayAttribute()
    {
        return $this->cut_off_day ?? $this->confirmation_days ?? 0;
    }
}
