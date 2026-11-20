<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServicePackage extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code', 'description', 'currency', 'nights', 'display_price',
        'begin_date', 'end_date', 'days_of_week', 'is_inactive',
    ];

    protected $casts = [
        'begin_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'days_of_week' => 'array',
        'display_price' => 'decimal:2',
        'is_inactive' => 'boolean',
    ];

    public function items()
    {
        return $this->hasMany(ServicePackageItem::class)->orderBy('detail_no');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
}
