<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    protected $table = 'warehouses';

    protected $fillable = ['name', 'outlet_id', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function inventoryChecks(): HasMany
    {
        return $this->hasMany(InventoryCheck::class);
    }

    public function dailyLogs(): HasMany
    {
        return $this->hasMany(InventoryDailyLog::class);
    }

    /** Kiểm tra đã phát sinh nhật ký chưa (để chặn xóa) */
    public function hasLogs(): bool
    {
        return $this->dailyLogs()->exists();
    }
}
