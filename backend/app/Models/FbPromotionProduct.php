<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FbPromotionProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'fb_promotion_id',
        'fb_product_id',
    ];

    public function promotion()
    {
        return $this->belongsTo(FbPromotion::class, 'fb_promotion_id');
    }

    public function product()
    {
        return $this->belongsTo(FbProduct::class, 'fb_product_id');
    }
}
