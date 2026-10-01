<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NightAuditRoomSalesForecastDetailSnapshot extends Model
{
    use HasFactory;

    protected $table = 'night_audit_room_sales_forecast_detail_snapshots';

    protected $fillable = [
        'night_audit_run_id',
        'snapshot_date',
        'rm',
        'eb',
        'er',
        'bf',
        'ep',
        'us',
        'ee',
        'el',
        'occ_rooms',
        'house_use',
        'foc_all',
        'foc',
        'foc_owner',
        'room_sales',
        'extra_bed',
        'revenue',
        'avg_rate',
        'avg_rate_2',
        'room_available',
        'percent_occupancy',
        'percent_occupancy_2',
        'baby_cot',
    ];

    protected $casts = [
        'snapshot_date'       => 'date',
        'rm'                  => 'decimal:2',
        'eb'                  => 'decimal:2',
        'er'                  => 'decimal:2',
        'bf'                  => 'decimal:2',
        'ep'                  => 'decimal:2',
        'us'                  => 'decimal:2',
        'ee'                  => 'decimal:2',
        'el'                  => 'decimal:2',
        'revenue'             => 'decimal:2',
        'avg_rate'            => 'decimal:2',
        'avg_rate_2'          => 'decimal:2',
        'percent_occupancy'   => 'decimal:4',
        'percent_occupancy_2' => 'decimal:4',
    ];
}
