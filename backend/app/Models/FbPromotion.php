<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FbPromotion extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'outlet_id',
        'discount_percent',
        'increase_percent',
        'discount_amount',
        'increase_amount',
        'is_auto_apply',
        'is_active',
        'start_date',
        'end_date',
        'apply_by_time',
        'start_time',
        'end_time',
        'company_id',
        'customer_source_id',
        'is_all_product',
    ];

    protected $casts = [
        'is_auto_apply' => 'boolean',
        'is_active' => 'boolean',
        'apply_by_time' => 'boolean',
        'is_all_product' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function products()
    {
        return $this->hasMany(FbPromotionProduct::class);
    }
}
