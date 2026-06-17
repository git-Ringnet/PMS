<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoomRateDailyMapping extends Model
{
    use HasFactory;

    protected $table = 'room_rate_daily_mappings';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'RateCode',
        'Date',
        'Code',
    ];

    protected $casts = [
        'Date' => 'date:Y-m-d',
    ];

    protected function setKeysForSaveQuery($query)
    {
        $query->where('RateCode', '=', $this->getAttribute('RateCode'))
              ->where('Date', '=', $this->getAttribute('Date'));
        return $query;
    }

    public function rateCodeModel()
    {
        return $this->belongsTo(RoomRateCode::class, 'RateCode', 'Ma');
    }

    public function ratePlan()
    {
        // Custom relation since it's a composite key on rate_plans
        // Usually we fetch plans using RateCode and Code.
        // We can just rely on the controller logic to join or fetch.
    }
}
