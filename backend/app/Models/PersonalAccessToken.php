<?php

namespace App\Models;

use App\Models\Concerns\UsesSystemConnection;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    use UsesSystemConnection;

    /**
     * Database connection cho bảng personal_access_tokens (luôn dùng mysql_system)
     */
}
