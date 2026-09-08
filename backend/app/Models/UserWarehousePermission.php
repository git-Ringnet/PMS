<?php

namespace App\Models;

use App\Models\Concerns\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserWarehousePermission extends Model
{
    use UsesSystemConnection;

    protected $fillable = ['user_id', 'system_branch_id', 'warehouse_id'];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(SystemBranch::class, 'system_branch_id');
    }
}
