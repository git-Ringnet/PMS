<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceBillDetail extends Model
{
    protected $table = 'service_bill_details';
    public $incrementing = false;
    public $timestamps = false;
    protected $primaryKey = null;

    protected $fillable = ['BillServiceId', 'Ma', 'DepartmentId', 'ServiceId', 'DescriptionServive', 'OriginalRate', 'Quantity', 'ServiceCharge', 'SpecialTax', 'Tax', 'ServiceChargeAmount', 'SpecialTaxAmount', 'TaxAmount', 'Amount', 'Currency', 'Exchange', 'BillExchangeRate', 'BillExchangeAmount', 'Pack1', 'Pack2', 'Pack3', 'DetailBillOriginalAmount', 'DetailBillServiceChargeAmount', 'DetailBillSpecialTaxAmount', 'DetailBillTaxAmount', 'DetailBillTotalAmount', 'OriginalAmount', 'DiscountAmount', 'IncreaseAmount', 'VatId', 'VatNumber'];

    protected $casts = [
        'Quantity' => 'decimal:6',
        'OriginalRate' => 'decimal:6',
        'ServiceChargeAmount' => 'decimal:6',
        'SpecialTaxAmount' => 'decimal:6',
        'TaxAmount' => 'decimal:6',
        'Amount' => 'decimal:6',
        'BillExchangeRate' => 'float',
        'BillExchangeAmount' => 'decimal:6',
        'DetailBillOriginalAmount' => 'decimal:6',
        'DetailBillServiceChargeAmount' => 'decimal:6',
        'DetailBillSpecialTaxAmount' => 'decimal:6',
        'DetailBillTaxAmount' => 'decimal:6',
        'DetailBillTotalAmount' => 'decimal:6',
        'OriginalAmount' => 'decimal:6',
        'DiscountAmount' => 'decimal:6',
        'IncreaseAmount' => 'decimal:6',
    ];

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
