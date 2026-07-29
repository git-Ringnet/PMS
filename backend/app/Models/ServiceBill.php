<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceBill extends Model
{
    protected $table = 'service_bills';
    protected $primaryKey = 'Ma';
    public $incrementing = true;

    protected $fillable = ['Date', 'OpenTime', 'Guest', 'DepartmentId', 'ServiceId', 'DescriptionServive', 'Quantity', 'Amount', 'ServiceCharge', 'SpecialTax', 'Tax', 'Currency', 'Exchange', 'Edit', 'Folio', 'PaymentId', 'VatId', 'RegisterId1', 'RentalRoomId1', 'CustomerId1', 'CompanyId1', 'RegisterID2', 'RentalRoomId2', 'CustomerId2', 'CompanyId2', 'Username', 'Ca', 'Status', 'InvoiceId', 'Outlet', 'Pack1', 'Pack2', 'Pack3', 'Year', 'Month', 'Day', 'CreatedUser', 'CreatedDate', 'CreatedHour', 'UpdatedDate', 'AdjustmentBillId', 'MisaRefId'];
    public $timestamps = true;
}
