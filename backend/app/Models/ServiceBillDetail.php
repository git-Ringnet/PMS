<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceBillDetail extends Model
{
    protected $table = 'service_bill_details';
    public $incrementing = false;
    public $timestamps = false;
    protected $primaryKey = null;

    protected $fillable = ['BillServiceId', 'Ma', 'DepartmentId', 'ServiceId', 'DescriptionServive', 'OriginalRate', 'Quantity', 'ServiceCharge', 'SpecialTax', 'Tax', 'Amount', 'Currency', 'Exchange', 'Pack1', 'Pack2', 'Pack3', 'DetailBillOriginalAmount', 'DiscountAmount', 'IncreaseAmount', 'VatId', 'VatNumber'];

    protected $casts = ['Quantity' => 'decimal:6'];

    protected static function booted(): void
    {
        static::creating(function (self $detail) {
            if ($detail->Quantity === null || $detail->Quantity === '') {
                $rate = (float) $detail->OriginalRate;
                $detail->Quantity = $rate != 0 ? (float) $detail->Amount / $rate : 1;
            }
        });
    }
}
