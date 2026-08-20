<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    /**
     * Database connection cho bảng personal_access_tokens (luôn dùng mysql_system)
     */
    protected $connection = 'mysql_system';
}
