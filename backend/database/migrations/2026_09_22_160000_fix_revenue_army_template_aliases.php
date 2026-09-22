<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $baseMigration = require database_path('migrations/2026_09_22_100000_create_revenue_reports_150_151_158.php');
        $connections = array_values(array_unique(array_merge(
            [config('database.default', 'mysql')],
            array_values(config('database_domains.branch_connections', []))
        )));

        $visitedDatabases = [];
        foreach ($connections as $connectionName) {
            $db = DB::connection($connectionName);
            if ($db->getDriverName() !== 'mysql') {
                continue;
            }

            $database = $db->getDatabaseName();
            if (isset($visitedDatabases[$database])) {
                continue;
            }
            $visitedDatabases[$database] = true;

            $capabilities = $baseMigration->schemaCapabilities($db);
            $db->unprepared('DROP PROCEDURE IF EXISTS rpt_revenue_army');
            $db->unprepared($baseMigration->revenueArmyProcedure($capabilities));
        }
    }

    public function down(): void
    {
        // The aliased procedure remains backward compatible when this patch is rolled back.
    }
};
