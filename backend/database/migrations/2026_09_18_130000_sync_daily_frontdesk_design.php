<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TEMPLATE = 'DAILY_FRONTDESK_REFERENCE';

    public function up(): void
    {
        $visited = [];

        foreach ([DB::getDefaultConnection()] as $connectionName) {
            try {
                $db = DB::connection($connectionName);
                if ($db->getDriverName() !== 'mysql') {
                    continue;
                }

                $database = $db->getDatabaseName();
                if (isset($visited[$database])) {
                    continue;
                }
                $visited[$database] = true;

                $templateId = $db->table('templates')->where('report', self::TEMPLATE)->value('id');
                if (! $templateId) {
                    continue;
                }

                $definition = (require database_path('report_templates/daily_frontdesk_reference.php'))->definition();
                $db->table('templates')->where('id', $templateId)->update([
                    'page_size' => $definition['page_size'],
                    'page_orientation' => $definition['page_orientation'],
                    'margin_top' => $definition['margin_top'],
                    'margin_right' => $definition['margin_right'],
                    'margin_bottom' => $definition['margin_bottom'],
                    'margin_left' => $definition['margin_left'],
                    'content_json' => json_encode($definition['content_json'], JSON_UNESCAPED_UNICODE),
                    'content_html' => $definition['content_html'],
                    'css' => $definition['css'],
                    'version' => $definition['version'],
                    'updated_at' => now(),
                ]);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }
    }

    public function down(): void
    {
        // Keep the saved design intact when rolling back the synchronization migration.
    }
};
