<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HousekeepingServiceBillDetail extends Model
{
    protected $table = 'housekeeping_service_bill_details';
    public $incrementing = false;
    public $timestamps = false;
    protected $primaryKey = null;

    protected $fillable = ['BillId', 'DetailId', 'MaProduct', 'ProductGroupId', 'Product', 'Rate', 'Quantity', 'Discount', 'DiscountAmount', 'Increase', 'IncreaseAmount', 'TotalAmount', 'Note', 'Deleted'];
}
