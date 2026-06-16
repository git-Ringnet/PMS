<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HotelSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_name',
        'hotel_name',
        'hotel_name1',
        'address',
        'address1',
        'phone',
        'fax',
        'email',
        'website',
        'account',
        'bank_code',
        'bank',
        'tax_code',
        'account_name',
        'serial',
        'invoice_number',
        'invoice_number_length',
        'form_no',
        'logo',
        'invoice_address',
        'breakfast_adult_rate',
        'breakfast_child_rate',
        'extra_bed_rate',
        'room_number',
        'division',
        'currency',
        'prefix_booking_id',
        'channel_manager',
        'facebook',
        'hotel_link',
        'pos_serial',
        'pos_invoice_number',
        'pos_invoice_number_length',
        'pos_invoice_form_no',
        'pos_invoice_symbol',
        'logo_url',
        'qr_code_url',
    ];

    protected $casts = [
        'invoice_number_length' => 'integer',
        'breakfast_adult_rate' => 'decimal:2',
        'breakfast_child_rate' => 'decimal:2',
        'extra_bed_rate' => 'decimal:2',
        'room_number' => 'integer',
        'pos_invoice_number_length' => 'integer',
    ];
}
