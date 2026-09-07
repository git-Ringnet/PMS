<?php

namespace App\Models;

use App\Models\Concerns\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use UsesSystemConnection;

    protected $fillable = [
        'code', 'name', 'description', 'level', 'department_scope', 'is_active',
        'allow_historical_date_actions',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'level' => 'integer',
        'allow_historical_date_actions' => 'boolean',
    ];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles')
            ->withPivot('system_branch_id')
            ->withTimestamps();
    }

    public function branchPermissions(): HasMany
    {
        return $this->hasMany(BranchRolePermission::class);
    }

    public function positionAssignments(): HasMany
    {
        return $this->hasMany(PositionBranchRole::class);
    }
}
