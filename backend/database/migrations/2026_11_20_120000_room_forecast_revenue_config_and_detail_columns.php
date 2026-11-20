<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * TC_BC_BP_02 (BP dòng 163 / Lễ tân dòng 352), file DANH MỤC BÁO CÁO.xlsx tab "BC dự đoán bán phòng":
 *  - Doanh thu theo thông số Revenue; DT phòng khác theo RevenueER;
 *  - Giá TB theo AverageRoomRateIncludedOthersRoomRevenue (0: chỉ RM, 1: theo Revenue);
 *  - Mẫu chi tiết: DT phòng (RM không dayuse), P.Theo giờ (RM dayuse), DT phòng khác, Doanh thu.
 *
 * Chỉ tạo lại thủ tục + trường dữ liệu + mẫu mặc định (chưa bị chỉnh); không đụng cấu hình menu báo cáo.
 */
return new class extends Migration
{
    private const SOURCE = 'RPT_ROOM_FORECAST';
    private const TEMPLATE = 'ROOM_FORECAST_REFERENCE';
    private const PROCEDURE = 'rpt_room_forecast';

    public function up(): void
    {
        $db = DB::connection();
        if ($db->getDriverName() !== 'mysql') {
            return;
        }

        $source = require database_path('migrations/2026_09_24_100000_create_room_forecast_report.php');

        $db->unprepared('DROP PROCEDURE IF EXISTS `'.self::PROCEDURE.'`');
        // Thủ tục có truy vấn con tương quan trong SUM() nên bị ONLY_FULL_GROUP_BY (strict của Laravel) từ chối;
        // sql_mode lúc tạo được lưu cùng thủ tục (giống migration gốc).
        $sqlMode = $db->selectOne('SELECT @@SESSION.sql_mode AS mode')->mode;
        $collation = $db->selectOne('SELECT @@SESSION.collation_connection AS c')->c;
        $db->statement("SET SESSION sql_mode = REPLACE(@@SESSION.sql_mode, 'ONLY_FULL_GROUP_BY', '')");
        $db->statement('SET SESSION collation_connection = utf8mb4_unicode_ci');
        try {
            $db->unprepared($source->procedureSql());
        } finally {
            $db->statement('SET SESSION sql_mode = ?', [$sqlMode]);
            $db->statement('SET SESSION collation_connection = '.$collation);
        }

        $this->syncFieldSchema($db);
        $this->syncTemplate($db);
    }

    public function down(): void
    {
        // Không khôi phục thủ tục cũ.
    }

    private function syncFieldSchema($db): void
    {
        $row = $db->table('report_data_sources')->where('code', self::SOURCE)->first();
        if (! $row) {
            return;
        }
        $fields = json_decode((string) $row->field_schema, true);
        if (! is_array($fields) || in_array('RMDay', array_column($fields, 'name'), true)) {
            return;
        }
        $out = [];
        foreach ($fields as $field) {
            $out[] = $field;
            if (($field['name'] ?? '') === 'RM') {
                $out[] = ['name' => 'RMDay', 'type' => 'number'];
            }
        }
        $db->table('report_data_sources')->where('id', $row->id)->update([
            'field_schema' => json_encode($out, JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);
    }

    private function syncTemplate($db): void
    {
        $template = $db->table('templates')->where('report', self::TEMPLATE)->first();
        if (! $template || (float) ($template->version ?? 1.0) > 1.0) {
            return; // mẫu đã được người dùng chỉnh bằng Designer: không ghi đè
        }

        $definition = (require database_path('report_templates/room_forecast_reference.php'))->definition();
        $db->table('templates')->where('id', $template->id)->update([
            'content_json' => json_encode($definition['content_json'], JSON_UNESCAPED_UNICODE),
            'content_html' => $definition['content_html'],
            'css' => $definition['css'],
            'updated_at' => now(),
        ]);
    }
};
