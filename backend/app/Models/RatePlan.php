<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RatePlan extends Model
{
    protected $table = 'rate_plans';

    protected $fillable = [
        'rate_code',
        'code',
        'description',
        'begin_date',
        'end_date',
        'period',
    ];

    protected $casts = [
        'begin_date' => 'date:Y-m-d',
        'end_date'   => 'date:Y-m-d',
        'period'     => 'array',
    ];

    public function rateCode(): BelongsTo
    {
        return $this->belongsTo(RateCode::class, 'rate_code', 'code');
    }

    public function dailies(): HasMany
    {
        return $this->hasMany(RatePlanDaily::class, 'code', 'code')
                    ->where('rate_code', $this->rate_code);
    }
}