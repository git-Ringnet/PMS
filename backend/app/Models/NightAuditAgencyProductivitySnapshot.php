<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NightAuditAgencyProductivitySnapshot extends Model
{
    use HasFactory;

    protected $table = 'night_audit_agency_productivity_snapshots';

    protected $fillable = [
        'night_audit_run_id',
        'snapshot_date',
        'company_id',
        'company_name',
        'market_segment_id',
        'source_code',
        'user_sale',
        'area_id',
        'num_of_rooms',
        'room_nights',
        'foc',
        'house_use',
        'guest_nights',
        'num_of_guests',
        'room_revenue',
        'extra_bed_revenue',
        'extra_rollaway_revenue',
        'total_revenue',
        'revenue_per_room_night',
        'revenue_percent',
        'average_revenue',
        'rav',
        'rav3',
    ];

    protected $casts = [
        'snapshot_date'           => 'date',
        'room_revenue'            => 'decimal:2',
        'extra_bed_revenue'       => 'decimal:2',
        'extra_rollaway_revenue'  => 'decimal:2',
        'total_revenue'           => 'decimal:2',
        'revenue_per_room_night'  => 'decimal:4',
        'revenue_percent'         => 'decimal:4',
        'average_revenue'         => 'decimal:4',
        'rav3'                    => 'decimal:4',
    ];
}
