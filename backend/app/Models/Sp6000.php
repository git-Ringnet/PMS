<?php
namespace AppModels;
use IlluminateDatabaseEloquentModel;

class Sp6000 extends Model
{
    protected $table = 'sp6000';
    protected $primaryKey = 'Ma';
    protected $fillable = ['BookingId','BillOriginalAmount','BillDiscountAmount','BillAmount','BillDiscount','BillServicesCharge','BillSpecialTax','BillTax','BillNote','Status','Outlet','Date','Department','RoomNo','BillServiceId','Currency','ExchangeRate','BillUsername','BillEdit'];
}
