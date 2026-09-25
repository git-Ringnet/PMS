<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $baseMigration = require database_path('migrations/2026_09_22_170000_create_revenue_reports_154_159_160_166_167_168.php');
        $methods = [
            'rpt_summary_service_invoices' => 'summaryServiceInvoicesProcedure',
            'rpt_daily_summary' => 'dailySummaryProcedure',
        ];
        $visitedDatabases = [];

        foreach ($this->targetConnections() as $connectionName) {
            $db = DB::connection($connectionName);
            if ($db->getDriverName() !== 'mysql') {
                continue;
            }

            $database = $db->getDatabaseName();
            if (isset($visitedDatabases[$database])) {
                continue;
            }
            $visitedDatabases[$database] = true;

            foreach ($methods as $procedure => $method) {
                $reflection = new ReflectionMethod($baseMigration, $method);
                $reflection->setAccessible(true);
                $db->unprepared('DROP PROCEDURE IF EXISTS `'.$procedure.'`');
                $db->unprepared($reflection->invoke($baseMigration));
            }
        }
    }

    public function down(): void
    {
        // Keep the corrected legacy revenue group labels when rolling back metadata-only migrations.
    }

    private function targetConnections(): array
    {
        return [DB::getDefaultConnection()];
    }
};
