<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoomRatePlan extends Model
{
    use HasFactory;

    protected $table = 'room_rate_plans';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'RateCode',
        'Code',
        'Description',
        'BeginDate',
        'EndDate',
        'Period', // JSON matrix
    ];

    protected $casts = [
        'BeginDate' => 'date:Y-m-d',
        'EndDate' => 'date:Y-m-d',
        // Period is stored as JSON string, but we can cast it to array
        // However, in SQL server it was ntext. If we store JSON in MySQL, we can cast to array
        'Period' => 'array', 
    ];

    // Laravel doesn't support composite primary keys out of the box nicely for all operations,
    // so we handle it manually if needed or use a package, but for simple saving we can use where clauses.

    protected function setKeysForSaveQuery($query)
    {
        $query->where('RateCode', '=', $this->getAttribute('RateCode'))
              ->where('Code', '=', $this->getAttribute('Code'));
        return $query;
    }

    public function rateCodeModel()
    {
        return $this->belongsTo(RoomRateCode::class, 'RateCode', 'Ma');
    }
}
