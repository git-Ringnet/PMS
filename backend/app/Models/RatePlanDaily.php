<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RatePlanDaily extends Model
{
    protected $table = 'rate_plan_dailies';

    protected $fillable = [
        'rate_code',
        'date',
        'code',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
    ];

    public function rateCode(): BelongsTo
    {
        return $this->belongsTo(RateCode::class, 'rate_code', 'code');
    }

    public function ratePlan(): BelongsTo
    {
        return $this->belongsTo(RatePlan::class, 'code', 'code');
    }
}