<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CONNECTIONS = ['mysql', 'mysql_hkt1', 'mysql_hkt2', 'mysql_hkt3', 'mysql_hkt4'];

    public function up(): void
    {
        $visitedDatabases = [];

        $reportsToSync = [
            [
                'template_key' => 'CANCELLED_INVOICES_PAYMENTS_REFERENCE',
                'report_code' => 'CANCELLED_INVOICES_PAYMENTS',
                'file' => 'cancelled_invoices_payments_reference.php',
            ],
            [
                'template_key' => 'DAILY_FRONTDESK_REFERENCE',
                'report_code' => 'DAILY_FRONTDESK',
                'file' => 'daily_frontdesk_reference.php',
            ],
            [
                'template_key' => 'UNPAID_SERVICE_BILLS_REFERENCE',
                'report_code' => 'UNPAID_SERVICE_BILLS',
                'file' => 'unpaid_service_bills_reference.php',
            ],
            [
                'template_key' => 'ROOM_RATE_STATISTICS_REFERENCE',
                'report_code' => 'ROOM_RATE_STATISTICS',
                'file' => 'room_rate_statistics_reference.php',
            ],
        ];

        foreach (self::CONNECTIONS as $conn) {
            try {
                if (DB::connection($conn)->getDriverName() !== 'mysql') {
                    continue;
                }

                $database = DB::connection($conn)->getDatabaseName();
                if (isset($visitedDatabases[$database])) {
                    continue;
                }
                $visitedDatabases[$database] = true;

                $now = now();
                $db = DB::connection($conn);

                foreach ($reportsToSync as $item) {
                    $templatePath = database_path('report_templates/' . $item['file']);
                    if (!file_exists($templatePath)) {
                        continue;
                    }

                    $templateObj = require $templatePath;
                    $definition = $templateObj->definition();

                    $db->table('templates')->where('report', $item['template_key'])->update([
                        'name' => $definition['name'],
                        'page_size' => $definition['page_size'],
                        'page_orientation' => $definition['page_orientation'],
                        'margin_top' => $definition['margin_top'],
                        'margin_right' => $definition['margin_right'],
                        'margin_bottom' => $definition['margin_bottom'],
                        'margin_left' => $definition['margin_left'],
                        'content_json' => json_encode($definition['content_json'], JSON_UNESCAPED_UNICODE),
                        'content_html' => $definition['content_html'],
                        'css' => $definition['css'],
                        'updated_at' => $now,
                    ]);

                    $db->table('report_definitions')->where('code', $item['report_code'])->update([
                        'name' => $definition['name'],
                        'updated_at' => $now,
                    ]);
                }
            } catch (\Throwable $e) {
                // Continue for other branches
            }
        }
    }

    public function down(): void
    {
        // Preserve data
    }
};
