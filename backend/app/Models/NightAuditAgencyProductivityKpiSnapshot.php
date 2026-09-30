<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NightAuditAgencyProductivityKpiSnapshot extends Model
{
    use HasFactory;

    protected $table = 'night_audit_agency_productivity_kpi_snapshots';

    protected $fillable = [
        'night_audit_run_id',
        'snapshot_date',
        'company_id',
        'travel_agency',
        'no_of_rooms',
        'room_nights',
        'room_nights_p',
        'no_of_guests',
        'guest_nights',
        'no_of_guests_p',
        'revenue',
        'revenue_p',
        'avg_revenue',
        'rav',
        'foc',
        'hu',
    ];

    protected $casts = [
        'snapshot_date'   => 'date',
        'room_nights_p'   => 'decimal:4',
        'no_of_guests_p'  => 'decimal:4',
        'revenue'         => 'decimal:2',
        'revenue_p'       => 'decimal:4',
        'avg_revenue'     => 'decimal:2',
    ];
}
