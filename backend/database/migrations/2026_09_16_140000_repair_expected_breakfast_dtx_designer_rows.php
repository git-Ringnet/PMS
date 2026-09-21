<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $provider = require database_path('report_templates/expected_breakfast_dtx_summary_reference.php');
        $referenceById = [];
        foreach ($provider->blocks()['detail'] ?? [] as $block) {
            if (!empty($block['customRows']) && !empty($block['id'])) {
                $referenceById[$block['id']] = $block['customRows'];
            }
        }
        $now = now();

        foreach (['mysql', 'mysql_hkt1', 'mysql_hkt2', 'mysql_hkt3', 'mysql_hkt4'] as $connectionName) {
            $connection = DB::connection($connectionName);
            if ($connection->getDriverName() !== 'mysql') {
                continue;
            }

            $template = $connection->table('templates')
                ->where('report', 'EXPECTED_BREAKFAST_DTX_SUMMARY')
                ->first();
            $stored = $template ? json_decode($template->content_json ?? '', true) : null;
            if (!$template || !is_array($stored) || !is_array($stored['detail'] ?? null)) {
                continue;
            }

            $changed = false;
            foreach ($stored['detail'] as &$block) {
                $id = $block['id'] ?? null;
                if (!$id || !isset($referenceById[$id]) || !empty($block['customRows'])) {
                    continue;
                }

                $block['customRows'] = $referenceById[$id];
                $changed = true;
            }
            unset($block);

            if ($changed) {
                $connection->table('templates')
                    ->where('id', $template->id)
                    ->update([
                        'content_json' => json_encode($stored, JSON_UNESCAPED_UNICODE),
                        'updated_at' => $now,
                    ]);
            }
        }
    }

    public function down(): void
    {
        // Keep Designer content intact when rolling back the repair migration.
    }
};
