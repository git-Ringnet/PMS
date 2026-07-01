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

    protected $casts = [
        'change_table' => 'boolean',
        'open_key' => 'boolean',
        'is_alcohol' => 'boolean',
        'flexible_price' => 'boolean',
        'is_active' => 'boolean',
        'track_stock' => 'boolean',
        'price' => 'decimal:2',
        'original_amount' => 'decimal:2',
        'service_charge_percent' => 'float',
        'service_charge_amount' => 'decimal:2',
        'tax_percent' => 'float',
        'tax_amount' => 'decimal:2',
        'special_tax_percent' => 'float',
        'special_tax_amount' => 'decimal:2',
    ];

    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }
}
