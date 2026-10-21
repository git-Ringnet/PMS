<?php

namespace App\Models;

use App\Models\Concerns\UsesSystemConnection;
use Illuminate\Database\Eloquent\Model;

class SecurityExemptUser extends Model
{
    use UsesSystemConnection;

    protected $table = 'security_exempt_users';

    protected $fillable = ['user_id'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
