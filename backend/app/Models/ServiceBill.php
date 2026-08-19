<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ServiceBill extends Model
{
    protected $table = 'service_bills';
    protected $primaryKey = 'Ma';
    public $incrementing = true;

    protected $fillable = ['Date', 'OpenTime', 'Guest', 'DepartmentId', 'ServiceId', 'DescriptionServive', 'Quantity', 'Amount', 'ServiceCharge', 'SpecialTax', 'Tax', 'Currency', 'Exchange', 'Edit', 'Folio', 'PaymentId', 'VatId', 'RegisterId1', 'RentalRoomId1', 'CustomerId1', 'CompanyId1', 'RegisterID2', 'RentalRoomId2', 'CustomerId2', 'CompanyId2', 'Username', 'employee_code', 'Ca', 'Status', 'InvoiceId', 'Outlet', 'Pack1', 'Pack2', 'Pack3', 'Year', 'Month', 'Day', 'CreatedUser', 'CreatedDate', 'CreatedHour', 'UpdatedDate', 'AdjustmentBillId', 'IsAdjustment', 'MisaRefId', 'user_id'];
    public $timestamps = true;

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
