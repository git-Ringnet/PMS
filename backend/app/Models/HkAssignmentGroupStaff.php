<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HkAssignmentGroupStaff extends Model
{
    protected $table = 'hk_assignment_group_staff';

    protected $fillable = ['group_id', 'staff_id'];

    public function group(): BelongsTo
    {
        return $this->belongsTo(HkAssignmentGroup::class, 'group_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(HkStaff::class, 'staff_id');
    }
}
