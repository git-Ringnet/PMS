<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HousekeepingServiceBill extends Model
{
    protected $table = 'housekeeping_service_bills';
    protected $primaryKey = 'Ma';

    protected $fillable = ['BookingId', 'GuestId', 'BillOriginalAmount', 'BillDiscountAmount', 'BillAmount', 'BillDiscount', 'BillServicesCharge', 'BillSpecialTax', 'BillTax', 'BillNote', 'Status', 'Outlet', 'Date', 'Department', 'RoomNo', 'BillServiceId', 'Currency', 'ExchangeRate', 'BillUsername', 'BillEdit'];
}
