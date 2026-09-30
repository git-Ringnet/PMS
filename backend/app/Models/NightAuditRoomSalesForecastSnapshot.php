<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NightAuditRoomSalesForecastSnapshot extends Model
{
    use HasFactory;

    protected $table = 'night_audit_room_sales_forecast_snapshots';

    protected $fillable = [
        'night_audit_run_id',
        'snapshot_date',
        'dep_adult',
        'dep_child',
        'dep_rooms',
        'arr_adult',
        'arr_child',
        'arr_rooms',
        'occ_adult',
        'occ_child',
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
        'rm',
        'eb',
        'er',
        'ooo_room',
    ];

    protected $casts = [
        'snapshot_date'       => 'date',
        'revenue'             => 'decimal:2',
        'avg_rate'            => 'decimal:2',
        'avg_rate_2'          => 'decimal:2',
        'percent_occupancy'   => 'decimal:4',
        'percent_occupancy_2' => 'decimal:4',
        'rm'                  => 'decimal:2',
        'eb'                  => 'decimal:2',
        'er'                  => 'decimal:2',
    ];
}
