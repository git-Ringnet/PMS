<?php

namespace App\Models;

use App\Models\Concerns\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Position extends Model
{
    use UsesSystemConnection;

    protected $fillable = ['organization_department_id', 'code', 'name', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];

    public function department(): BelongsTo
    {
        return $this->belongsTo(OrganizationDepartment::class, 'organization_department_id');
    }

    public function branchRoles(): HasMany
    {
        return $this->hasMany(PositionBranchRole::class);
    }

    public function userAssignments(): HasMany
    {
        return $this->hasMany(UserBranchPosition::class);
    }
}
