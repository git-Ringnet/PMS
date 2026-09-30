<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NightAuditRunStep extends Model
{
    use HasFactory;

    protected $table = 'night_audit_run_steps';

    protected $fillable = [
        'run_id',
        'step_code',
        'step_name',
        'step_order',
        'status',
        'started_at',
        'finished_at',
        'affected_rows',
        'summary',
        'error_message',
    ];

    protected $casts = [
        'started_at'  => 'datetime',
        'finished_at' => 'datetime',
        'summary'     => 'array',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(NightAuditRun::class, 'run_id');
    }
}
