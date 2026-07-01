<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FbProductOutlet extends Model
{
    protected $table = 'fb_product_outlets';

    protected $fillable = [
        'fb_product_id',
        'outlet_id',
        'is_active',
        'original_amount',
        'service_charge_percent',
        'service_charge_amount',
        'special_tax_percent',
        'special_tax_amount',
        'tax_percent',
        'tax_amount',
        'price',
        'combo_original',
        'combo_service',
        'combo_special',
        'combo_tax',
        'combo_price',
        'update_price',
        'update_combo_price',
        'is_expanded',
        'selectedCounterOutlets',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'update_price' => 'boolean',
        'update_combo_price' => 'boolean',
        'is_expanded' => 'boolean',
        'selectedCounterOutlets' => 'array',
        'original_amount' => 'decimal:2',
        'service_charge_percent' => 'float',
        'service_charge_amount' => 'decimal:2',
        'special_tax_percent' => 'float',
        'special_tax_amount' => 'decimal:2',
        'tax_percent' => 'float',
        'tax_amount' => 'decimal:2',
        'price' => 'decimal:2',
        'combo_original' => 'decimal:2',
        'combo_service' => 'float',
        'combo_special' => 'float',
        'combo_tax' => 'float',
        'combo_price' => 'decimal:2',
    ];

    public function product()
    {
        return $this->belongsTo(FbProduct::class, 'fb_product_id');
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class, 'outlet_id');
    }
}
