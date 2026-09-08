<?php

namespace App\Models;

use App\Models\Concerns\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;

class BranchRolePermission extends Model
{
    use UsesSystemConnection;

    protected $fillable = ['system_branch_id', 'role_id', 'permission_id'];
}
