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

    /**
     * Dòng 438: bảng dùng khóa chính ghép (warehouse_id, date, product_id).
     * Thiếu điều kiện này, save() của một dòng đã có sinh UPDATE không WHERE
     * và ghi đè nhập/xuất/chuyển của mọi dòng trong bảng.
     */
    protected function setKeysForSaveQuery($query)
    {
        foreach (['warehouse_id', 'date', 'product_id'] as $column) {
            $query->where($column, '=', $this->getOriginal($column) ?? $this->getAttribute($column));
        }

        return $query;
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
