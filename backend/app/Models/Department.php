<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $fillable = [
        'code',
        'name',
        'phone',
        'show'
    ];

    public function hotelServices()
    {
        return $this->belongsToMany(HotelService::class)
            ->withPivot('description')
            ->withTimestamps();
    }
}
