<?php

namespace App\Models;

use App\Models\Concerns\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;

class SecuritySetting extends Model
{
    use UsesSystemConnection;

    protected $table = 'security_settings';

    protected $fillable = [
        'auto_logout_enabled', 'auto_logout_hours', 'ip_restriction_enabled', 'time_restriction_enabled',
    ];

    protected $casts = [
        'auto_logout_enabled' => 'boolean',
        'auto_logout_hours' => 'integer',
        'ip_restriction_enabled' => 'boolean',
        'time_restriction_enabled' => 'boolean',
    ];

    /** Cấu hình dùng chung: luôn chỉ có một dòng. */
    public static function current(): self
    {
        return static::query()->first() ?? static::create([]);
    }
}
