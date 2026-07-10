<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FbOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'parent_item_id',
        'product_id',
        'product_name',
        'quantity',
        'price',
        'discount',
        'surcharge',
        'base_discount',
        'base_surcharge',
        'note',
    ];

    public function order()
    {
        return $this->belongsTo(FbOrder::class, 'order_id');
    }

    public function product()
    {
        return $this->belongsTo(FbProduct::class, 'product_id');
    }
}
