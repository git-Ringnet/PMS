<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const LEGACY_REPORTS = ['EXPECTED_BREAKFAST_1', 'EXPECTED_BREAKFAST_2'];

    private const LEGACY_TEMPLATES = ['EXPECTED_BREAKFAST_1_STANDARD', 'EXPECTED_BREAKFAST_2_STANDARD'];

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $now = now();

        foreach ([DB::getDefaultConnection()] as $connectionName) {
            $connection = DB::connection($connectionName);
            if ($connection->getDriverName() !== 'mysql') {
                continue;
            }

            $legacyReports = $connection->table('report_definitions')
                ->whereIn('code', self::LEGACY_REPORTS)
                ->get(['id', 'report_data_source_id']);
            $legacyReportIds = $legacyReports->pluck('id')->all();
            $legacySourceIds = $legacyReports->pluck('report_data_source_id')->filter()->unique()->all();

            if ($legacyReportIds !== []) {
                $connection->table('report_definition_template')
                    ->whereIn('report_definition_id', $legacyReportIds)
                    ->delete();
                $connection->table('report_definitions')
                    ->whereIn('id', $legacyReportIds)
                    ->delete();
            }

            foreach (self::LEGACY_TEMPLATES as $code) {
                $template = $connection->table('templates')->where('report', $code)->first(['id']);
                if (! $template) {
                    continue;
                }

                $isUnused = ! $connection->table('report_definition_template')->where('template_id', $template->id)->exists()
                    && ! $connection->table('print_template_slots')->where('template_id', $template->id)->exists()
                    && ! $connection->table('template_versions')->where('template_id', $template->id)->exists();
                if ($isUnused) {
                    $connection->table('templates')->where('id', $template->id)->delete();
                }
            }

            foreach ($legacySourceIds as $sourceId) {
                $isUnused = ! $connection->table('report_definitions')->where('report_data_source_id', $sourceId)->exists()
                    && ! $connection->table('templates')->where('report_data_source_id', $sourceId)->exists();
                if ($isUnused) {
                    $connection->table('report_data_sources')->where('id', $sourceId)->delete();
                }
            }

            $connection->table('templates')
                ->where('report', 'EXPECTED_BREAKFAST_DETAIL')
                ->update([
                    'name' => 'Báo cáo dự kiến khách ăn sáng - Chi tiết',
                    'updated_at' => $now,
                ]);
        }
    }

    public function down(): void
    {
        // Removed legacy report metadata has no safe automatic restoration.
    }
};
