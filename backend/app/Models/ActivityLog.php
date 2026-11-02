<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    /**
     * Logs are write-only, no updated_at needed.
     */
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'user_name',
        'employee_code',
        'action',
        'module',
        'component',
        'description',
        'target_type',
        'target_id',
        'target_label',
        'booking_code',
        'room_code',
        'log_version',
        'system_date',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'request_method',
        'request_url',
        'response_status',
        'duration_ms',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
            'system_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        // "Ngày thao tác" = ngày hệ thống (ngày nghiệp vụ) tại thời điểm ghi log, không phải ngày máy
        static::creating(function (self $log): void {
            if (empty($log->system_date)) {
                $log->system_date = self::currentSystemDate();
            }
        });
    }

    public static function currentSystemDate(): ?string
    {
        try {
            $value = SystemDateRoll::latest('id')->value('system_date');

            return $value ? \Carbon\Carbon::parse($value)->toDateString() : now('Asia/Ho_Chi_Minh')->toDateString();
        } catch (\Throwable $e) {
            return now('Asia/Ho_Chi_Minh')->toDateString();
        }
    }

    /**
     * Relationship: User who performed the action.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
