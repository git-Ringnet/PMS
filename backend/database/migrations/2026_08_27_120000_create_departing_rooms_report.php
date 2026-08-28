<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Historical migration retained so existing local databases keep a consistent
 * migration history. The experimental Departing Rooms report was removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $reportId = DB::table('report_definitions')->where('code', 'DEPARTING_ROOMS')->value('id');
        $templateId = DB::table('templates')->where('report', 'DEPARTING_ROOMS_STANDARD')->value('id');

        if ($reportId) {
            DB::table('report_definition_template')->where('report_definition_id', $reportId)->delete();
            DB::table('report_definitions')->where('id', $reportId)->delete();
        }
        if ($templateId) {
            DB::table('templates')->where('id', $templateId)->delete();
        }

        DB::table('report_data_sources')->where('code', 'DEPARTING_ROOMS')->delete();
        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_departing_rooms');
    }

    public function down(): void
    {
        // The report was intentionally removed and must not be recreated.
    }
};
