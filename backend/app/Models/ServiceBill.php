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
        'Amount' => 'decimal:2',
        'Quantity' => 'decimal:2',
        'ExchangeRate1' => 'decimal:2',
        'ExchangeRate2' => 'decimal:2',
        'TotalAmount1' => 'decimal:2',
        'TotalAmount2' => 'decimal:2',
        'TotalAmount0' => 'decimal:2',
        'ConvertRate' => 'float',
        'ConvertAmount' => 'decimal:2',
        'ConvertRate2' => 'float',
        'ConvertAmount2' => 'decimal:2',
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

    public function salesInvoice()
    {
        return $this->belongsTo(SalesInvoice::class, 'InvoiceId', 'id');
    }

    /**
     * Bỏ qua bill RM "bổ sung tiền phòng" khi cần tìm bill tiền phòng chuẩn của một ngày.
     */
    public function scopeExcludeSupplementalRoomCharge($query)
    {
        return $query->whereNotExists(fn ($sub) => self::supplementalRoomChargeConstraint($sub));
    }

    public function scopeOnlySupplementalRoomCharge($query)
    {
        return $query->whereExists(fn ($sub) => self::supplementalRoomChargeConstraint($sub));
    }

    /**
     * Bill bổ sung tiền phòng: room_night_bills.is_room_night = 0 trên phòng không phải Day Use.
     * Tiền phòng Day Use cũng có thể is_room_night = 0 (IsRoomNightRoomDayUse = 0) nên phải loại trừ.
     */
    private static function supplementalRoomChargeConstraint($sub): void
    {
        $sub->selectRaw('1')
            ->from('room_night_bills')
            ->join('booking_rooms', 'booking_rooms.id', '=', 'service_bills.RentalRoomId1')
            ->join('bookings', 'bookings.id', '=', 'booking_rooms.booking_id')
            ->whereColumn('room_night_bills.bill_id', 'service_bills.Ma')
            ->where('room_night_bills.is_room_night', 0)
            ->where(fn ($q) => $q->whereNull('booking_rooms.is_day_use')->orWhere('booking_rooms.is_day_use', 0))
            ->where(fn ($q) => $q->whereNull('bookings.is_day_use')->orWhere('bookings.is_day_use', 0))
            ->whereRaw('DATE(booking_rooms.arrival_date) <> DATE(booking_rooms.departure_date)');
    }
}
