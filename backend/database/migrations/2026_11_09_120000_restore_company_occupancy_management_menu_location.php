<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Báo cáo công suất công ty / chi tiết công suất công ty bị tạo lại (migration 2026_11_04) với
 * menu_locations chỉ có reservation + frontdesk nên mất khỏi menu "Báo Cáo Quản Lý".
 * Bổ sung lại location management, giữ nguyên các location đang có.
 */
return new class extends Migration
{
    private const CODES = ['COMPANY_OCCUPANCY', 'COMPANY_OCCUPANCY_DETAIL'];

    public function up(): void
    {
        if (! Schema::hasTable('report_definitions')) {
            return;
        }

        $reports = DB::table('report_definitions')->whereIn('code', self::CODES)->get(['id', 'menu_locations']);
        foreach ($reports as $report) {
            $locations = json_decode((string) $report->menu_locations, true);
            $locations = is_array($locations) ? array_values(array_unique(array_map('strval', $locations))) : [];
            if (in_array('management', $locations, true)) {
                continue;
            }
            $locations[] = 'management';
            DB::table('report_definitions')->where('id', $report->id)->update([
                'menu_locations' => json_encode($locations, JSON_UNESCAPED_UNICODE),
                'show_in_menu' => true,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Không gỡ management để không mất cấu hình menu đã chỉnh tay.
    }
};
