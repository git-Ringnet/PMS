<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RateCode extends Model
{
    protected $table = 'rate_codes';

    protected $fillable = [
        'code',
        'description',
        'begin_date',
        'end_date',
        'include_bf',
        'currency',
        'type',
        'value',
        'disable',
        'allow_change_rate',
        'is_channel_manager',
        'promotion_code',
        'source_code',
        'market_segment',
    ];

    protected $casts = [
        'begin_date'         => 'date:Y-m-d',
        'end_date'           => 'date:Y-m-d',
        'include_bf'         => 'boolean',
        'disable'            => 'boolean',
        'allow_change_rate'  => 'boolean',
        'is_channel_manager' => 'boolean',
        'value'              => 'array',
    ];

    public function ratePlans(): HasMany
    {
        return $this->hasMany(RatePlan::class, 'rate_code', 'code');
    }

    public function ratePlanDailies(): HasMany
    {
        return $this->hasMany(RatePlanDaily::class, 'rate_code', 'code');
    }
}