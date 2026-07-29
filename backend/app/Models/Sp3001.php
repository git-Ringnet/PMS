<?php
namespace AppModels;
use IlluminateDatabaseEloquentModel;

class Sp3001 extends Model
{
    protected $table = 'sp3001';
    public $incrementing = false;
    public $timestamps = false;
    protected $primaryKey = null;
    protected $fillable = ['BillServiceId','Ma','DepartmentId','ServiceId','DescriptionServive','OriginalRate','ServiceCharge','SpecialTax','Tax','Amount','Currency','Exchange','Pack1','Pack2','Pack3','DetailBillOriginalAmount','DiscountAmount','IncreaseAmount','VatId','VatNumber'];
}
