<?php

namespace App\Models;

use App\Models\Concerns\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;

class SecurityAllowedIp extends Model
{
    use UsesSystemConnection;

    protected $table = 'security_allowed_ips';

    protected $fillable = ['name', 'ip'];
}
