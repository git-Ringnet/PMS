<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FbCombo extends Model
{
    protected $table = 'fb_combos';

    protected $fillable = [
        'parent_id',
        'child_id',
        'quantity',
        'price',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'decimal:2',
    ];

    public function parent()
    {
        return $this->belongsTo(FbProduct::class, 'parent_id');
    }

    public function child()
    {
        return $this->belongsTo(FbProduct::class, 'child_id');
    }
}
