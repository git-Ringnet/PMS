<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryTransfer extends Model
{
    protected $table = 'inventory_transfers';

    protected $fillable = [
        'warehouse_id', 'product_id', 'date',
        'quantity', 'transfer_to_warehouse_id', 'hour', 'created_by',
    ];

    protected $casts = [
        'quantity' => 'float',
    ];

    public function warehouseFrom(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function warehouseTo(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'transfer_to_warehouse_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
