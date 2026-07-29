<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceBillDetail extends Model
{
    protected $table = 'service_bill_details';
    public $incrementing = false;
    public $timestamps = false;
    protected $primaryKey = null;

    protected $fillable = ['BillServiceId', 'Ma', 'DepartmentId', 'ServiceId', 'DescriptionServive', 'OriginalRate', 'ServiceCharge', 'SpecialTax', 'Tax', 'Amount', 'Currency', 'Exchange', 'Pack1', 'Pack2', 'Pack3', 'DetailBillOriginalAmount', 'DiscountAmount', 'IncreaseAmount', 'VatId', 'VatNumber'];
}
