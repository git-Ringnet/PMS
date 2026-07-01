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
        ];
    }

    /**
     * Relationship: User who performed the action.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
