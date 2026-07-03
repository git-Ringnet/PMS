<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FbPrinter extends Model
{
    protected $fillable = [
        'outlet_id',
        'name',
        'type',
        'num_of_prints',
        'driver_name',
    ];

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }
}
