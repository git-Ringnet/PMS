<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $db = DB::connection(DB::getDefaultConnection());
        if ($db->getDriverName() !== 'mysql') {
            return;
        }

        foreach (['report_data_sources', 'report_definitions', 'templates'] as $table) {
            if (! $db->getSchemaBuilder()->hasTable($table)) {
                return;
            }
        }

        $summaryRoutine = $this->routineDefinition($db, 'rpt_company_occupancy');
        $detailRoutine = $this->routineDefinition($db, 'rpt_company_occupancy_detail');
        $summaryFields = (string) ($db->table('report_data_sources')->where('code', 'RPT_COMPANY_OCCUPANCY')->value('field_schema') ?? '');
        $detailFields = (string) ($db->table('report_data_sources')->where('code', 'RPT_COMPANY_OCCUPANCY_DETAIL')->value('field_schema') ?? '');
        $summaryUi = (string) ($db->table('report_definitions')->where('code', 'COMPANY_OCCUPANCY')->value('parameter_ui_schema') ?? '');

        $needsRepair = $summaryRoutine === ''
            || $detailRoutine === ''
            || ! str_contains($summaryRoutine, 'RevenuePercentage')
            || ! str_contains($detailRoutine, 'RowNumber')
            || ! str_contains($detailRoutine, 'ExtraBedRevenue')
            || ! str_contains($summaryFields, 'RevenuePercentage')
            || ! str_contains($detailFields, 'RowNumber')
            || ! str_contains($summaryUi, 'linked_report_code')
            || ! str_contains($summaryUi, 'visible_when')
            || ! str_contains($summaryUi, 'maps_to');

        if (! $needsRepair) {
            return;
        }

        $finalizer = require database_path('migrations/2026_11_02_100000_finalize_company_occupancy_legacy_pdf.php');
        $finalizer->up();
    }

    public function down(): void
    {
        // Không rollback contract đã được đối soát với legacy.
    }

    private function routineDefinition($db, string $name): string
    {
        try {
            $row = $db->selectOne('SHOW CREATE PROCEDURE `'.$name.'`');

            return (string) ($row->{'Create Procedure'} ?? '');
        } catch (Throwable) {
            return '';
        }
    }
};
