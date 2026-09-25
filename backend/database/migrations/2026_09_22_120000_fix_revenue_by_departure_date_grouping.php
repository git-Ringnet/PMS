<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $migration = require database_path('migrations/2026_09_22_100000_create_revenue_reports_150_151_158.php');
        $visitedDatabases = [];

        foreach ([DB::getDefaultConnection()] as $connectionName) {
            $db = DB::connection($connectionName);
            if ($db->getDriverName() !== 'mysql') {
                continue;
            }

            $database = $db->getDatabaseName();
            if (isset($visitedDatabases[$database])) {
                continue;
            }
            $visitedDatabases[$database] = true;

            $capabilities = $migration->schemaCapabilities($db);
            $db->unprepared('DROP PROCEDURE IF EXISTS rpt_revenue_by_departure_date');
            $db->unprepared($migration->revenueByDepartureDateProcedure($capabilities));
        }
    }

    public function down(): void
    {
        // The previous procedure definition remains valid for rollback ordering.
    }
};
