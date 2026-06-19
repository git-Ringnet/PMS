<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'product_category_id',
        'name',
        'currency',
        'price',
        'note',
        'change_table',
        'open_key',
        'is_alcohol',
        'goods',
        'image',
        'is_in_stock',
        'service_charge_percent',
        'tax_percent',
        'special_tax_percent',
        'original_amount',
        'service_charge_amount',
        'tax_amount',
        'special_tax_amount',
        'inventory_id',
        'flexible_price',
        'misa_id',
        'product_code',
        'debit_account',
        'credit_account',
        'is_active',
        'track_stock',
    ];

    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }
}
