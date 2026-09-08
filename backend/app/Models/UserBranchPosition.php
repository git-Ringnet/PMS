<?php

namespace App\Models;

use App\Models\Concerns\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserBranchPosition extends Model
{
    use UsesSystemConnection;

    protected $fillable = ['user_id', 'system_branch_id', 'application_code', 'position_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(SystemBranch::class, 'system_branch_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }
}
