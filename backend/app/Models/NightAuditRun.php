<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NightAuditRun extends Model
{
    use HasFactory;

    protected $table = 'night_audit_runs';

    protected $fillable = [
        'source_system_date',
        'target_system_date',
        'actual_started_at',
        'actual_finished_at',
        'shift',
        'username',
        'status',
        'idempotency_key',
        'error_code',
        'error_message',
        'metadata',
    ];

    protected $casts = [
        'source_system_date' => 'date',
        'target_system_date' => 'date',
        'actual_started_at'  => 'datetime',
        'actual_finished_at' => 'datetime',
        'metadata'           => 'array',
    ];

    public function steps(): HasMany
    {
        return $this->hasMany(NightAuditRunStep::class, 'run_id')->orderBy('step_order');
    }
}
