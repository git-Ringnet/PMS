<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HkAssignmentGroup extends Model
{
    protected $table = 'hk_assignment_groups';

    protected $fillable = ['assignment_id', 'color', 'sort_order'];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(HkAssignment::class, 'assignment_id');
    }

    public function staffList(): HasMany
    {
        return $this->hasMany(HkAssignmentGroupStaff::class, 'group_id');
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(HkAssignmentGroupRoom::class, 'group_id');
    }
}
