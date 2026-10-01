<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NightAuditRoomTypeSnapshot extends Model
{
    use HasFactory;

    protected $table = 'night_audit_room_type_snapshots';

    protected $fillable = [
        'night_audit_run_id',
        'snapshot_date',
        'room_type_id',
        'room_type',
        'room_type_code',
        'inventory',
        'ooo',
        'room_available',
        'no_of_night',
        'adr',
        'revenue',
        'revenue_percent',
        'occupancy_percent',
    ];

    protected $casts = [
        'snapshot_date'     => 'date',
        'adr'               => 'decimal:2',
        'revenue'           => 'decimal:2',
        'revenue_percent'   => 'decimal:4',
        'occupancy_percent' => 'decimal:4',
    ];
}
