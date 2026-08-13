<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HkAssignmentGroupRoom extends Model
{
    protected $table = 'hk_assignment_group_rooms';

    protected $fillable = [
        'group_id', 'room_id',
        'room_status_snapshot', 'booking_status_snapshot',
        'assigned_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(HkAssignmentGroup::class, 'group_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'room_id');
    }
}
