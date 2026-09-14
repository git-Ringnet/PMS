<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HousekeepingServiceBill extends Model
{
    protected $table = 'housekeeping_service_bills';
    protected $primaryKey = 'Ma';

    protected $fillable = ['BookingId', 'GuestId', 'NumOfRoom', 'HumanNumber', 'BillOriginalAmount', 'BillServicesChargeAmount', 'BillSpecialTaxAmount', 'BillTaxAmount', 'BillTotalAmount', 'BillDiscountAmount', 'BillAmount', 'BillDiscount', 'BillServicesCharge', 'BillSpecialTax', 'BillTax', 'IncludeServicesCharge', 'IncludeSpecialTax', 'IncludeTax', 'FOCType', 'BillNote', 'Status', 'Outlet', 'Date', 'Department', 'RoomNo', 'BillServiceId', 'Currency', 'ExchangeRate', 'BillTime', 'BillUsername', 'BillShift', 'BillEdit', 'CaptainOrder', 'IsExport', 'user_id'];

    protected $casts = [
        'HumanNumber' => 'integer',
        'BillOriginalAmount' => 'decimal:6',
        'BillServicesChargeAmount' => 'decimal:6',
        'BillSpecialTaxAmount' => 'decimal:6',
        'BillTaxAmount' => 'decimal:6',
        'BillTotalAmount' => 'decimal:6',
        'BillDiscountAmount' => 'decimal:6',
        'BillAmount' => 'decimal:6',
        'IncludeServicesCharge' => 'boolean',
        'IncludeSpecialTax' => 'boolean',
        'IncludeTax' => 'boolean',
        'FOCType' => 'integer',
        'BillShift' => 'integer',
        'IsExport' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $bill) {
            $bill->user_id ??= auth()->id();
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
