<?php
namespace AppModels;
use IlluminateDatabaseEloquentModel;

class Sp6001 extends Model
{
    protected $table = 'sp6001';
    public $incrementing = false;
    public $timestamps = false;
    protected $primaryKey = null;
    protected $fillable = ['BillId','DetailId','MaProduct','ProductGroupId','Product','Rate','Quantity','Discount','DiscountAmount','Increase','IncreaseAmount','TotalAmount','Note','Deleted'];
}
