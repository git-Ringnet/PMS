<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    protected $table = 'warehouses';

    protected $fillable = ['name', 'outlet_id', 'is_active'];

    protected $appends = ['outlet_ids'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Danh sách outlet codes gán cho kho dạng mảng
     */
    public function getOutletIdsAttribute(): array
    {
        if (empty($this->outlet_id)) {
            return [];
        }
        if (str_starts_with($this->outlet_id, '[')) {
            $decoded = json_decode($this->outlet_id, true);
            if (is_array($decoded)) {
                return array_values(array_filter($decoded));
            }
        }
        return array_values(array_filter(array_map('trim', explode(',', $this->outlet_id))));
    }

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
