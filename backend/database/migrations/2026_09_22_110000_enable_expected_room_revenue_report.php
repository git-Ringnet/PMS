<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SOURCE = 'RPT_EXPECTED_ROOM_REVENUE_NIGHT_AUDIT';
    private const REPORT = 'EXPECTED_ROOM_REVENUE_NIGHT_AUDIT';
    private const TEMPLATE = 'EXPECTED_ROOM_REVENUE_REFERENCE';

    public function up(): void
    {
        $legacyMigration = require database_path('migrations/2026_09_21_170000_create_expected_room_revenue_night_audit_report.php');
        $visitedDatabases = [];

        foreach ($this->branchConnections() as $connectionName) {
            $db = DB::connection($connectionName);
            if ($db->getDriverName() !== 'mysql') {
                continue;
            }

            $database = $db->getDatabaseName();
            if (isset($visitedDatabases[$database])) {
                continue;
            }
            $visitedDatabases[$database] = true;

            if (! $db->table('report_definitions')->where('code', self::REPORT)->exists()) {
                $db->unprepared('DROP PROCEDURE IF EXISTS rpt_expected_room_revenue_night_audit');
                $db->unprepared($legacyMigration->procedureSql());
                $this->createMissingConfiguration($db);
            }

            $db->table('report_definitions')->where('code', self::REPORT)->update([
                'is_active' => true,
                'show_in_menu' => true,
                'menu_locations' => json_encode(['frontdesk', 'reservation'], JSON_UNESCAPED_UNICODE),
                'menu_top_order' => 20,
                'menu_group_order' => 0,
                'menu_item_order' => 164,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        foreach ($this->branchConnections() as $connectionName) {
            $db = DB::connection($connectionName);
            if ($db->getDriverName() === 'mysql') {
                $db->table('report_definitions')->where('code', self::REPORT)->update([
                    'show_in_menu' => false,
                    'menu_locations' => json_encode(['night_audit'], JSON_UNESCAPED_UNICODE),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function branchConnections(): array
    {
        return array_values(array_unique(array_merge(
            [config('database.default', 'mysql')],
            array_values(config('database_domains.branch_connections', []))
        )));
    }

    private function createMissingConfiguration($db): void
    {
        $now = now();
        $database = $db->getDatabaseName();
        $parameters = [
            ['name' => 'p_date', 'mode' => 'IN', 'data_type' => 'date', 'database_type' => 'date', 'position' => 1, 'required' => true],
        ];
        $fields = [
            ['name' => 'STT', 'type' => 'integer', 'nullable' => true],
            ['name' => 'BookingCode', 'type' => 'string', 'nullable' => true],
            ['name' => 'Guest', 'type' => 'string', 'nullable' => true],
            ['name' => 'Room', 'type' => 'string', 'nullable' => true],
            ['name' => 'ArrivalDate', 'type' => 'string', 'nullable' => true],
            ['name' => 'DepartureDate', 'type' => 'string', 'nullable' => true],
            ['name' => 'ServiceId', 'type' => 'string', 'nullable' => true],
            ['name' => 'ServiceName', 'type' => 'string', 'nullable' => true],
            ['name' => 'RateTotal', 'type' => 'number', 'nullable' => true],
            ['name' => 'Company', 'type' => 'string', 'nullable' => true],
            ['name' => 'NoteBooking', 'type' => 'string', 'nullable' => true],
        ];
        $defaults = ['p_date' => now()->toDateString()];

        $db->table('report_data_sources')->updateOrInsert(['code' => self::SOURCE], [
            'name' => 'Dữ liệu doanh thu dự kiến tiền phòng',
            'description' => 'Dự kiến và dịch vụ đã post trong ngày Sang Ngày theo sp_095 Army.',
            'source_type' => 'procedure',
            'schema_name' => $database,
            'object_name' => 'rpt_expected_room_revenue_night_audit',
            'parameter_schema' => json_encode($parameters, JSON_UNESCAPED_UNICODE),
            'field_schema' => json_encode($fields, JSON_UNESCAPED_UNICODE),
            'sample_parameters' => json_encode($defaults, JSON_UNESCAPED_UNICODE),
            'max_rows' => 5000,
            'is_active' => true,
            'last_discovered_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $sourceId = $db->table('report_data_sources')->where('code', self::SOURCE)->value('id');

        $definition = (require database_path('report_templates/expected_room_revenue_reference.php'))->definition();
        $template = $db->table('templates')->where('report', self::TEMPLATE)->first();
        if (! $template) {
            $templateId = $db->table('templates')->insertGetId([
                'report' => self::TEMPLATE,
                'group' => 'Báo cáo quản lý / Sang ngày',
                'name' => 'Báo cáo dự kiến doanh thu tiền phòng',
                'report_data_source_id' => $sourceId,
                'parameter_defaults' => json_encode($defaults, JSON_UNESCAPED_UNICODE),
                'page_size' => $definition['page_size'],
                'page_orientation' => $definition['page_orientation'],
                'margin_top' => $definition['margin_top'],
                'margin_right' => $definition['margin_right'],
                'margin_bottom' => $definition['margin_bottom'],
                'margin_left' => $definition['margin_left'],
                'content_json' => json_encode($definition['content_json'], JSON_UNESCAPED_UNICODE),
                'content_html' => $definition['content_html'],
                'css' => $definition['css'],
                'is_default' => true,
                'version' => '1.0',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $templateId = $template->id;
            $db->table('templates')->where('id', $templateId)->update([
                'report_data_source_id' => $sourceId,
                'updated_at' => $now,
            ]);
        }

        $ui = [
            ['name' => 'p_date', 'label' => 'Ngày báo cáo', 'control' => 'date', 'default' => '$today', 'required' => true],
        ];
        $db->table('report_definitions')->updateOrInsert(['code' => self::REPORT], [
            'name' => 'Báo cáo dự kiến doanh thu tiền phòng',
            'group' => 'Báo cáo quản lý / Sang ngày',
            'description' => 'Xem khoản tiền phòng và dịch vụ dự kiến hoặc đã post trong ngày trước khi chạy Sang Ngày.',
            'report_data_source_id' => $sourceId,
            'parameter_ui_schema' => json_encode($ui, JSON_UNESCAPED_UNICODE),
            'sort_order' => 164,
            'is_active' => true,
            'show_in_menu' => true,
            'menu_locations' => json_encode(['frontdesk', 'reservation'], JSON_UNESCAPED_UNICODE),
            'menu_top_order' => 20,
            'menu_group_order' => 0,
            'menu_item_order' => 164,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $reportId = $db->table('report_definitions')->where('code', self::REPORT)->value('id');
        $db->table('report_definition_template')->updateOrInsert(
            ['report_definition_id' => $reportId, 'template_id' => $templateId],
            ['is_default' => true, 'created_at' => $now, 'updated_at' => $now]
        );
    }
};
