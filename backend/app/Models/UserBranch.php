<?php

namespace App\Models;

use App\Models\Concerns\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserBranch extends Model
{
    use UsesSystemConnection;

    protected $fillable = ['user_id', 'system_branch_id', 'is_primary'];

    protected $casts = ['is_primary' => 'boolean'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(SystemBranch::class, 'system_branch_id');
    }
}
