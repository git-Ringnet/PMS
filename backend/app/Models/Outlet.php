<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Outlet extends Model
{
    protected $fillable = [
        'code',
        'name',
        'department_code',
        'service_code',
        'is_active',
        'order_index',
        'check_voucher',
        'check_combo',
        'account_number',
        'account_name',
        'bank_name',
        'payment_content',
        'connector',
        'vat_config_id'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'check_voucher' => 'boolean',
        'check_combo' => 'boolean',
        'order_index' => 'integer',
        'vat_config_id' => 'integer'
    ];

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_code', 'code');
    }

    public function service()
    {
        return $this->belongsTo(HotelService::class, 'service_code', 'code');
    }
}
