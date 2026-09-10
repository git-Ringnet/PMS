<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ServiceBill extends Model
{
    protected $table = 'service_bills';
    protected $primaryKey = 'Ma';
    public $incrementing = true;

    protected $fillable = ['Date', 'OpenTime', 'Guest', 'RefId', 'DepartmentId', 'ServiceId', 'DescriptionServive', 'Quantity', 'Amount', 'ServiceCharge', 'SpecialTax', 'Tax', 'Currency', 'Exchange', 'NotPrint', 'Edit', 'Folio', 'PaymentId', 'VATNumber', 'VatId', 'RegisterId1', 'RentalRoomId1', 'CustomerId1', 'CompanyId1', 'RegisterID2', 'RentalRoomId2', 'CustomerId2', 'CompanyId2', 'Username', 'employee_code', 'Ca', 'Status', 'BillExchangeRate', 'BillExchangeAmount', 'InvoiceId', 'Outlet', 'Pack1', 'Pack2', 'Pack3', 'Serial', 'InvoiceNumber', 'DebitAccount', 'CreditAccount', 'RevenueAccount', 'CostAccount', 'ParentBillId', 'Year', 'Month', 'Day', 'CreatedUser', 'CreatedDate', 'CreatedHour', 'UpdatedUser', 'UpdatedDate', 'UpdatedHour', 'OwnerUser', 'RootOwnerUser', 'ExchangeRate1', 'ExchangeRate2', 'TotalAmount1', 'TotalAmount2', 'Currency0', 'TotalAmount0', 'Currency1', 'ConvertRate', 'ConvertAmount', 'Currency2', 'ConvertRate2', 'ConvertAmount2', 'IsSyncT', 'AdjustmentBillId', 'IsAdjustment', 'MisaRefId', 'user_id'];
    public $timestamps = true;

    protected $casts = [
        'NotPrint' => 'integer',
        'BillExchangeRate' => 'float',
        'BillExchangeAmount' => 'float',
        'ParentBillId' => 'integer',
        'ExchangeRate1' => 'decimal:6',
        'ExchangeRate2' => 'decimal:6',
        'TotalAmount1' => 'decimal:6',
        'TotalAmount2' => 'decimal:6',
        'TotalAmount0' => 'decimal:6',
        'ConvertRate' => 'float',
        'ConvertAmount' => 'decimal:6',
        'ConvertRate2' => 'float',
        'ConvertAmount2' => 'decimal:6',
        'IsSyncT' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (ServiceBill $bill) {
            $bill->user_id ??= Auth::id();
            if (Auth::user()) {
                $bill->employee_code = Auth::user()->employee_code;
            }

            if (!$bill->Ca) {
                $bill->Ca = SystemDateRoll::latest('id')->value('shift') ?: '1';
            }
        });
    }

    public function employeeOperator()
    {
        return $this->belongsTo(User::class, 'employee_code', 'employee_code');
    }

    public function usernameOperator()
    {
        return $this->belongsTo(User::class, 'Username', 'username');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function hotelService()
    {
        return $this->belongsTo(HotelService::class, 'ServiceId', 'code');
    }
}
