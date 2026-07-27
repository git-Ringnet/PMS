<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FbProduct extends Model
{
    protected $table = 'fb_products';

    protected $fillable = [
        'fb_product_category_id',
        'name',
        'product_code',
        'name_en',
        'short_name',
        'service_group',
        'vat_billing_name',
        'unit_id',
        'barcode',
        'is_print',
        'is_gate_ticket',
        'is_dish_exchange',
        'is_pre_printed',
        'no_reinvest',
        'is_contra',
        'processing_time',
        'serving_time',
        'is_combo',
        'price',
        'original_amount',
        'service_charge_percent',
        'service_charge_amount',
        'tax_percent',
        'tax_amount',
        'special_tax_percent',
        'special_tax_amount',
        'flexible_price',
        'change_table',
        'open_key',
        'is_alcohol',
        'track_stock',
        'image',
        'note',
        'is_active',
        'entrance_ip',
        'entrance_gate_ticket_type',
        'exchange_limit_hours',
        'is_fixed_price',
        'is_print_one_ticket',
        'ticket_type',
        'is_in_stock',
        'fb_printer_ids',
        'is_get_price_from_items',
        'is_check_combo',
        'combo_max_items',
    ];

    protected $casts = [
        'is_print'               => 'boolean',
        'is_gate_ticket'         => 'boolean',
        'is_dish_exchange'       => 'boolean',
        'is_pre_printed'         => 'boolean',
        'no_reinvest'            => 'boolean',
        'is_contra'              => 'boolean',
        'is_combo'               => 'boolean',
        'is_get_price_from_items'=> 'boolean',
        'is_check_combo'         => 'boolean',
        'combo_max_items'        => 'integer',
        'is_active'              => 'boolean',
        'flexible_price'         => 'boolean',
        'change_table'           => 'boolean',
        'open_key'               => 'boolean',
        'is_alcohol'             => 'boolean',
        'track_stock'            => 'boolean',
        'processing_time'        => 'integer',
        'serving_time'           => 'integer',
        'price'                  => 'decimal:2',
        'original_amount'        => 'decimal:2',
        'service_charge_percent' => 'float',
        'service_charge_amount'  => 'decimal:2',
        'tax_percent'            => 'float',
        'tax_amount'             => 'decimal:2',
        'special_tax_percent'    => 'float',
        'special_tax_amount'     => 'decimal:2',
        'entrance_ip'            => 'string',
        'entrance_gate_ticket_type' => 'integer',
        'exchange_limit_hours'   => 'integer',
        'is_fixed_price'         => 'boolean',
        'is_print_one_ticket'    => 'boolean',
        'ticket_type'            => 'string',
        'is_in_stock'            => 'integer',
        'fb_printer_ids'          => 'array',
    ];

    public function category()
    {
        return $this->belongsTo(FbProductCategory::class, 'fb_product_category_id');
    }

    public function unit()
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_id');
    }

    public function outletPrices()
    {
        return $this->hasMany(FbProductOutlet::class, 'fb_product_id');
    }

    public function comboItems()
    {
        return $this->hasMany(FbCombo::class, 'parent_id');
    }

}
