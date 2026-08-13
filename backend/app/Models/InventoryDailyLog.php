<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryDailyLog extends Model
{
    protected $table = 'inventory_daily_logs';

    protected $primaryKey = null;
    public $incrementing = false;

    protected $fillable = ['warehouse_id', 'date', 'product_id', 'receive', 'export', 'transfer'];

    protected $casts = [
        'receive'  => 'float',
        'export'   => 'float',
        'transfer' => 'float',
    ];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
