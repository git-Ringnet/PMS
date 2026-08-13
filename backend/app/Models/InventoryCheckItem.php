<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryCheckItem extends Model
{
    protected $table = 'inventory_check_items';

    protected $fillable = [
        'check_id', 'product_id',
        'well_balance', 'stoke_take', 'different_qty', 'final_balance',
        'unit', 'note',
    ];

    protected $casts = [
        'well_balance'  => 'float',
        'stoke_take'    => 'float',
        'different_qty' => 'float',
        'final_balance' => 'float',
    ];

    protected static function booted(): void
    {
        // Tự tính chênh lệch khi save
        static::saving(function (self $item) {
            $item->different_qty = ($item->stoke_take ?? 0) - ($item->well_balance ?? 0);
        });
    }

    public function check(): BelongsTo
    {
        return $this->belongsTo(InventoryCheck::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
