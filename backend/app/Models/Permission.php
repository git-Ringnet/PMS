<?php

namespace App\Models;

use App\Models\Concerns\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    use UsesSystemConnection;

    protected $fillable = [
        'code', 'name', 'module', 'description', 'application_code', 'screen_code',
        'screen_name', 'path', 'action', 'screen_type', 'sort_order',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permissions');
    }
}
