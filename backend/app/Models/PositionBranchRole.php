<?php

namespace App\Models;

use App\Models\Concerns\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PositionBranchRole extends Model
{
    use UsesSystemConnection;

    protected $fillable = ['position_id', 'system_branch_id', 'application_code', 'role_id', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(SystemBranch::class, 'system_branch_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
