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
        $reference = $provider->blocks();
        $now = now();

        foreach ([DB::getDefaultConnection()] as $connectionName) {
            $connection = DB::connection($connectionName);
            if ($connection->getDriverName() !== 'mysql') {
                continue;
            }

            $template = $connection->table('templates')
                ->where('report', 'EXPECTED_BREAKFAST_DTX_SUMMARY')
                ->first();
            if (!$template || !$template->content_json) {
                continue;
            }

            $stored = json_decode($template->content_json, true);
            if (!is_array($stored)) {
                continue;
            }

            $changed = false;
            foreach ($reference['detail'] ?? [] as $referenceBlock) {
                $referenceRows = $referenceBlock['customRows'] ?? null;
                if (!$referenceRows) {
                    continue;
                }

                foreach ($stored['detail'] ?? [] as &$storedBlock) {
                    if (($storedBlock['id'] ?? null) !== ($referenceBlock['id'] ?? null)) {
                        continue;
                    }
                    if (!empty($storedBlock['customRows'])) {
                        continue;
                    }

                    $storedBlock['customRows'] = $referenceRows;
                    $changed = true;
                }
                unset($storedBlock);
            }

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
        // Keep Designer content intact when rolling back the metadata migration.
    }
};
