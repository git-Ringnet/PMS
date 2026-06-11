<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HotelSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'address',
        'tax_code',
        'phone',
        'fax',
        'email',
        'facebook',
        'channel_manager',
        'currency',
        'bank_name',
        'bank_account_name',
        'bank_account_number',
        'adult_breakfast_price',
        'child_breakfast_price',
        'extra_bed_price',
        'total_rooms',
        'website',
        'booking_prefix',
        'logo_url',
        'qr_code_url',
    ];

    protected $casts = [
        'adult_breakfast_price' => 'decimal:2',
        'child_breakfast_price' => 'decimal:2',
        'extra_bed_price' => 'decimal:2',
        'total_rooms' => 'integer',
    ];
}
