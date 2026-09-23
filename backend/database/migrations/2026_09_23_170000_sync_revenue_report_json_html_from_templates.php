<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $templates = [
            'SUMMARY_SERVICE_INVOICES_REFERENCE' => 'summary_service_invoices_reference.php',
            'DEPOSITS_SUMMARY_REFERENCE' => 'deposits_summary_reference.php',
            'DAILY_SUMMARY_REFERENCE' => 'daily_summary_reference.php',
            'COMPANY_OCCUPANCY_DETAIL_REFERENCE' => 'company_occupancy_detail_reference.php',
            'COMPANY_OCCUPANCY_REFERENCE' => 'company_occupancy_reference.php',
            'SALESPERSON_REVENUE_SUMMARY_REFERENCE' => 'salesperson_revenue_summary_reference.php',
            'SALESPERSON_REVENUE_DETAIL_REFERENCE' => 'salesperson_revenue_detail_reference.php',
        ];

        $visitedDatabases = [];
        foreach ($this->branchConnections() as $connectionName) {
            $db = DB::connection($connectionName);
            if ($db->getDriverName() !== 'mysql') {
                continue;
            }

            $database = $db->getDatabaseName();
            if (isset($visitedDatabases[$database])) {
                continue;
            }
            $visitedDatabases[$database] = true;

            foreach ($templates as $report => $file) {
                $definition = (require database_path('report_templates/'.$file))->definition();
                $db->table('templates')->where('report', $report)->update([
                    'content_json' => json_encode($definition['content_json'], JSON_UNESCAPED_UNICODE),
                    'content_html' => $definition['content_html'],
                    'css' => $definition['css'],
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Keep the corrected JSON-to-HTML report contract when rolling back metadata-only migrations.
    }

    private function branchConnections(): array
    {
        return array_values(array_unique(array_merge(
            [config('database.default', 'mysql')],
            array_values(config('database_domains.branch_connections', []))
        )));
    }
};
