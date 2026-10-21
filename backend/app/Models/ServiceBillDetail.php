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
        'Quantity' => 'decimal:2',
        'OriginalRate' => 'decimal:2',
        'ServiceChargeAmount' => 'decimal:2',
        'SpecialTaxAmount' => 'decimal:2',
        'TaxAmount' => 'decimal:2',
        'Amount' => 'decimal:2',
        'BillExchangeRate' => 'float',
        'BillExchangeAmount' => 'decimal:2',
        'DetailBillOriginalAmount' => 'decimal:2',
        'DetailBillServiceChargeAmount' => 'decimal:2',
        'DetailBillSpecialTaxAmount' => 'decimal:2',
        'DetailBillTaxAmount' => 'decimal:2',
        'DetailBillTotalAmount' => 'decimal:2',
        'OriginalAmount' => 'decimal:2',
        'DiscountAmount' => 'decimal:2',
        'IncreaseAmount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $detail) {
            if ($detail->Quantity === null || $detail->Quantity === '') {
                // OriginalRate là đơn giá CHƯA thuế phí, nên số lượng = tiền chưa
                // thuế / đơn giá. Chia Amount (đã gồm thuế phí) cho OriginalRate
                // ra số lượng sai kiểu 1.13.
                $rate = (float) $detail->OriginalRate;
                $net = $detail->OriginalAmount ?? $detail->DetailBillOriginalAmount;
                $detail->Quantity = ($rate != 0 && $net !== null && $net !== '')
                    ? round((float) $net / $rate, 2)
                    : 1;
            }
        });
    }
}
