<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryCheck extends Model
{
    use SoftDeletes;

    protected $table = 'inventory_checks';

    protected $fillable = ['warehouse_id', 'month', 'note', 'created_by'];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryCheckItem::class, 'check_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Kiểm tra trong tháng đã có nhật ký nhập/xuất chưa
     * (nếu có thì không cho xóa phiếu kiểm kê)
     */
    public function hasLogs(): bool
    {
        return InventoryDailyLog::where('warehouse_id', $this->warehouse_id)
            ->where('date', 'like', $this->month . '%')
            ->exists();
    }
}
