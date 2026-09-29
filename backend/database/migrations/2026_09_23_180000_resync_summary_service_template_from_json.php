<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $definition = (require database_path('report_templates/summary_service_invoices_reference.php'))->definition();
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

            $db->table('templates')->where('report', 'SUMMARY_SERVICE_INVOICES_REFERENCE')->update([
                'content_json' => json_encode($definition['content_json'], JSON_UNESCAPED_UNICODE),
                'content_html' => $definition['content_html'],
                'css' => $definition['css'],
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Keep the JSON-driven template contract when rolling back metadata-only migrations.
    }

    private function targetConnections(): array
    {
        return [DB::getDefaultConnection()];
    }
};
