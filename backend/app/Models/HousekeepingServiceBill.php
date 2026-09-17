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
        'BillOriginalAmount' => 'decimal:2',
        'BillServicesChargeAmount' => 'decimal:2',
        'BillSpecialTaxAmount' => 'decimal:2',
        'BillTaxAmount' => 'decimal:2',
        'BillTotalAmount' => 'decimal:2',
        'BillDiscountAmount' => 'decimal:2',
        'BillAmount' => 'decimal:2',
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
