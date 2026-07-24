<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoomStatus extends Model
{
    protected $fillable = [
        'code',
        'name_vi',
        'name_en',
        'icon',
        'is_occupied',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_occupied' => 'boolean',
        'is_active'   => 'boolean',
    ];

    /**
     * Map room_status_code -> icon name (dùng cho frontend)
     */
    public static function iconMap(): array
    {
        return [
            'vacant_ready'    => null,
            'vacant_dirty'    => 'dirty',
            'vacant_clean'    => 'clean',
            'ooo'             => 'ooo',
            'oos'             => 'oos',
            'turndown'        => 'checkout',
            'housekeeping'    => 'housekeeping-service',
            'dnd'             => 'dnd',
            'vacant_priority' => 'priority',
            'occupied_ready'  => null,
            'occupied_dirty'  => 'dirty',
            'occupied_clean'  => null,
            'occupied_ooo'    => 'ooo',
        ];
    }

    /**
     * Lấy icon theo code
     */
    public static function getIcon(string $code): ?string
    {
        return static::iconMap()[$code] ?? null;
    }
}
