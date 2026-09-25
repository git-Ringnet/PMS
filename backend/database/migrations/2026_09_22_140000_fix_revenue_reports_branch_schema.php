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
            foreach ([
                ['rpt_revenue_army', $baseMigration->revenueArmyProcedure($capabilities)],
                ['rpt_revenue_by_departure_date', $baseMigration->revenueByDepartureDateProcedure($capabilities)],
                ['rpt_reception_cashier_shift', $baseMigration->receptionCashierShiftProcedure($capabilities)],
            ] as [$procedure, $sql]) {
                $db->unprepared('DROP PROCEDURE IF EXISTS '.$procedure);
                $db->unprepared($sql);
            }
        }
    }

    public function down(): void
    {
        // The compatibility definitions remain valid when this patch is rolled back.
    }
};
