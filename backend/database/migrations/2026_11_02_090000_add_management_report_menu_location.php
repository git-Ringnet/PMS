<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dòng 464: giữ menu /reports hiện hữu khi tách cây menu management.
     * Chỉ bổ sung location mới, không thay thế location hoặc quyền báo cáo đang có.
     */
    public function up(): void
    {
        if (! Schema::hasTable('report_definitions')) {
            return;
        }

        $now = now();
        $reports = DB::table('report_definitions')
            ->where('show_in_menu', true)
            ->get(['id', 'menu_locations']);

        foreach ($reports as $report) {
            $locations = json_decode((string) $report->menu_locations, true);
            $locations = is_array($locations) ? $locations : [];
            $locations = array_values(array_unique(array_map('strval', $locations)));

            if (in_array('management', $locations, true)) {
                continue;
            }

            $locations[] = 'management';
            DB::table('report_definitions')
                ->where('id', $report->id)
                ->update([
                    'menu_locations' => json_encode($locations, JSON_UNESCAPED_UNICODE),
                    'updated_at' => $now,
                ]);
        }
    }

    public function down(): void
    {
        // Không xóa management khi rollback để không làm mất cấu hình menu đã chỉnh tay.
    }
};
