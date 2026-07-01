<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FbProductCategory extends Model
{
    protected $table = 'fb_product_categories';

    protected $fillable = [
        'name',
        'image',
        'parent_id',
        'code',
        'description',
        'order_index',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order_index' => 'integer',
    ];

    public function parent()
    {
        return $this->belongsTo(FbProductCategory::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(FbProductCategory::class, 'parent_id')->orderBy('order_index');
    }

    public function products()
    {
        return $this->hasMany(FbProduct::class, 'fb_product_category_id');
    }
}
