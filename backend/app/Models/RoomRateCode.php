<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoomRateCode extends Model
{
    use HasFactory;

    protected $table = 'room_rate_codes';
    protected $primaryKey = 'Ma';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'Ma',
        'Description',
        'CreateDate',
        'BeginDate',
        'EndDate',
        'IncludeBF',
        'Currency',
        'PromotionCode',
        'SourceCode',
        'MarketSegment',
        'Type',
        'Value',
        'Disable',
        'AllowChangeRate',
        'IsChannelManager',
        'IsDaily',
    ];

    protected $casts = [
        'CreateDate' => 'datetime',
        'BeginDate' => 'date:Y-m-d',
        'EndDate' => 'date:Y-m-d',
        'IncludeBF' => 'boolean',
        'Disable' => 'boolean',
        'AllowChangeRate' => 'boolean',
        'IsChannelManager' => 'boolean',
        'IsDaily' => 'boolean',
    ];

    public function ratePlans()
    {
        return $this->hasMany(RoomRatePlan::class, 'RateCode', 'Ma');
    }

    public function dailyMappings()
    {
        return $this->hasMany(RoomRateDailyMapping::class, 'RateCode', 'Ma');
    }
}
