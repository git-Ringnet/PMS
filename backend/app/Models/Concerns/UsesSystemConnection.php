<?php

namespace App\Models\Concerns;

trait UsesSystemConnection
{
    public function getConnectionName()
    {
        return config('database_domains.system_connection', 'mysql_system');
    }
}
