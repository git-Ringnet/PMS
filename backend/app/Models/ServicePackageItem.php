<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServicePackageItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_package_id', 'detail_no', 'service_code', 'quantity', 'rate',
        'service_charge', 'special_tax', 'tax', 'order_day', 'outlet', 'note',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'rate' => 'decimal:2',
        'service_charge' => 'decimal:4',
        'special_tax' => 'decimal:4',
        'tax' => 'decimal:4',
    ];

    public function servicePackage()
    {
        return $this->belongsTo(ServicePackage::class);
    }

    public function hotelService()
    {
        return $this->belongsTo(HotelService::class, 'service_code', 'code');
    }
}
