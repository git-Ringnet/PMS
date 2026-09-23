<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    private const CONNECTIONS = ['mysql', 'mysql_hkt1', 'mysql_hkt2', 'mysql_hkt3', 'mysql_hkt4'];
    private const SOURCE = 'RPT_WEEKLY_ROOM_REPORT';
    private const REPORT = 'WEEKLY_ROOM_REPORT';
    private const TEMPLATE = 'WEEKLY_ROOM_REPORT_REFERENCE';

    public function up(): void
    {
        $visitedDatabases = [];

        foreach (self::CONNECTIONS as $connectionName) {
            try {
                $db = DB::connection($connectionName);
                if ($db->getDriverName() !== 'mysql') {
                    continue;
                }

                $database = $db->getDatabaseName();
                if (isset($visitedDatabases[$database])) {
                    continue;
                }
                $visitedDatabases[$database] = true;

                $db->unprepared('DROP PROCEDURE IF EXISTS rpt_weekly_room_report');
                $db->unprepared($this->procedureSql());
                $this->syncReportConfiguration($connectionName, $database);
            } catch (\Throwable $exception) {
                Log::warning('Weekly room report migration skipped unavailable branch connection.', [
                    'connection' => $connectionName,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    public function down(): void
    {
        $visitedDatabases = [];

        foreach (self::CONNECTIONS as $connectionName) {
            try {
                $db = DB::connection($connectionName);
                if ($db->getDriverName() !== 'mysql') {
                    continue;
                }

                $database = $db->getDatabaseName();
                if (isset($visitedDatabases[$database])) {
                    continue;
                }
                $visitedDatabases[$database] = true;

                $reportId = $db->table('report_definitions')->where('code', self::REPORT)->value('id');
                $templateId = $db->table('templates')->where('report', self::TEMPLATE)->value('id');
                if ($reportId) {
                    $db->table('report_definition_template')->where('report_definition_id', $reportId)->delete();
                    $db->table('report_definitions')->where('id', $reportId)->delete();
                }
                if ($templateId) {
                    $db->table('templates')->where('id', $templateId)->delete();
                }
                $db->table('report_data_sources')->where('code', self::SOURCE)->delete();
                $db->unprepared('DROP PROCEDURE IF EXISTS rpt_weekly_room_report');
            } catch (\Throwable $exception) {
                Log::warning('Weekly room report rollback skipped unavailable branch connection.', [
                    'connection' => $connectionName,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function procedureSql(): string
    {
        return <<<'SQL'
CREATE PROCEDURE rpt_weekly_room_report(
    IN p_from_date DATE,
    IN p_to_date DATE,
    IN p_division VARCHAR(20)
)
READS SQL DATA
BEGIN
    DECLARE v_start_date DATE;
    DECLARE v_end_date DATE;

    /*
      The active branch is selected by the request connection. p_division is
      retained as the canonical report contract; cross-branch aggregation is
      orchestrated by the report controller and calls this procedure with
      p_division = '__current__' on each authorized branch.
    */
    SET v_start_date = DATE_SUB(
        COALESCE(p_from_date, CURRENT_DATE()),
        INTERVAL WEEKDAY(COALESCE(p_from_date, CURRENT_DATE())) DAY
    );
    SET v_end_date = DATE_ADD(v_start_date, INTERVAL 6 DAY);

    WITH RECURSIVE week_days AS (
        SELECT v_start_date AS report_date
        UNION ALL
        SELECT DATE_ADD(report_date, INTERVAL 1 DAY)
        FROM week_days
        WHERE report_date < v_end_date
    ),
    daily_arrivals AS (
        SELECT
            br.arrival_date AS report_date,
            COUNT(*) AS arr_rooms,
            SUM(COALESCE(br.adults, 0) + COALESCE(br.children_qty, 0)) AS arr_guests
        FROM booking_rooms AS br
        INNER JOIN bookings AS b ON b.id = br.booking_id
        LEFT JOIN registration_statuses AS rs ON rs.id = b.registration_status_id
        LEFT JOIN rooms AS r ON r.room_number = br.room_number
        WHERE br.deleted_at IS NULL
          AND br.status IN (0, 1, 2)
          AND b.deleted_at IS NULL
          AND b.status NOT IN (3, 4)
          AND COALESCE(rs.is_availability, 1) = 1
          AND (br.room_number IS NULL OR br.room_number NOT LIKE '0%')
          AND COALESCE(r.is_internal, 0) = 0
          AND br.arrival_date BETWEEN v_start_date AND v_end_date
        GROUP BY br.arrival_date
    ),
    daily_departures AS (
        SELECT
            br.departure_date AS report_date,
            COUNT(*) AS dep_rooms,
            SUM(COALESCE(br.adults, 0) + COALESCE(br.children_qty, 0)) AS dep_guests
        FROM booking_rooms AS br
        INNER JOIN bookings AS b ON b.id = br.booking_id
        LEFT JOIN registration_statuses AS rs ON rs.id = b.registration_status_id
        LEFT JOIN rooms AS r ON r.room_number = br.room_number
        WHERE br.deleted_at IS NULL
          AND br.status IN (0, 1, 2)
          AND b.deleted_at IS NULL
          AND b.status NOT IN (3, 4)
          AND COALESCE(rs.is_availability, 1) = 1
          AND (br.room_number IS NULL OR br.room_number NOT LIKE '0%')
          AND COALESCE(r.is_internal, 0) = 0
          AND br.departure_date BETWEEN v_start_date AND v_end_date
        GROUP BY br.departure_date
    ),
    daily_occupied AS (
        SELECT
            wd.report_date,
            COUNT(*) AS occ_rooms,
            SUM(COALESCE(br.adults, 0) + COALESCE(br.children_qty, 0)) AS occ_guests
        FROM week_days AS wd
        INNER JOIN booking_rooms AS br
            ON wd.report_date >= br.arrival_date
           AND wd.report_date < br.departure_date
        INNER JOIN bookings AS b ON b.id = br.booking_id
        LEFT JOIN registration_statuses AS rs ON rs.id = b.registration_status_id
        LEFT JOIN rooms AS r ON r.room_number = br.room_number
        WHERE br.deleted_at IS NULL
          AND br.status IN (0, 1, 2)
          AND b.deleted_at IS NULL
          AND b.status NOT IN (3, 4)
          AND COALESCE(rs.is_availability, 1) = 1
          AND (br.room_number IS NULL OR br.room_number NOT LIKE '0%')
          AND COALESCE(r.is_internal, 0) = 0
        GROUP BY wd.report_date
    ),
    daily_capacity AS (
        SELECT
            wd.report_date,
            GREATEST(
                (
                    SELECT COUNT(*)
                    FROM rooms AS r0
                    WHERE COALESCE(r0.is_internal, 0) = 0
                ) - (
                    SELECT COUNT(DISTINCT rl.room_number)
                    FROM room_locks AS rl
                    INNER JOIN rooms AS locked_room ON locked_room.room_number = rl.room_number
                    WHERE COALESCE(locked_room.is_internal, 0) = 0
                      AND rl.lock_type = 'OOO'
                      AND rl.is_active = 1
                      AND COALESCE(rl.start_date, '1000-01-01 00:00:00') <= CONCAT(wd.report_date, ' 23:59:59')
                      AND COALESCE(rl.end_date, '9999-12-31 23:59:59') >= CONCAT(wd.report_date, ' 00:00:00')
                ),
                0
            ) AS available_rooms
        FROM week_days AS wd
    )
    SELECT
        wd.report_date AS report_date,
        DATE_FORMAT(wd.report_date, '%d/%m/%Y') AS report_date_display,
        CASE WEEKDAY(wd.report_date)
            WHEN 0 THEN 'Thứ Hai'
            WHEN 1 THEN 'Thứ Ba'
            WHEN 2 THEN 'Thứ Tư'
            WHEN 3 THEN 'Thứ Năm'
            WHEN 4 THEN 'Thứ Sáu'
            WHEN 5 THEN 'Thứ Bảy'
            WHEN 6 THEN 'Chủ Nhật'
        END AS day_name,
        COALESCE(da.arr_rooms, 0) AS arr_rooms,
        COALESCE(da.arr_guests, 0) AS arr_guests,
        COALESCE(dd.dep_rooms, 0) AS dep_rooms,
        COALESCE(dd.dep_guests, 0) AS dep_guests,
        COALESCE(occ.occ_rooms, 0) AS occ_rooms,
        COALESCE(occ.occ_guests, 0) AS occ_guests,
        dc.available_rooms AS available_rooms,
        CASE
            WHEN dc.available_rooms > 0
                THEN ROUND(COALESCE(occ.occ_rooms, 0) * 100.0 / dc.available_rooms, 2)
            ELSE 0.00
        END AS occupancy_rate,
        COALESCE(NULLIF(hs.division, ''), '') AS Division
    FROM week_days AS wd
    LEFT JOIN daily_arrivals AS da ON da.report_date = wd.report_date
    LEFT JOIN daily_departures AS dd ON dd.report_date = wd.report_date
    LEFT JOIN daily_occupied AS occ ON occ.report_date = wd.report_date
    INNER JOIN daily_capacity AS dc ON dc.report_date = wd.report_date
    LEFT JOIN hotel_settings AS hs ON hs.id = (SELECT MIN(id) FROM hotel_settings)
    ORDER BY wd.report_date ASC;
END
SQL;
    }

    private function syncReportConfiguration(string $connectionName, string $database): void
    {
        $db = DB::connection($connectionName);
        $now = now();
        $definition = (require database_path('report_templates/weekly_room_report_reference.php'))->definition();
        $defaults = [
            'p_from_date' => now()->timezone('Asia/Ho_Chi_Minh')->startOfWeek()->toDateString(),
            'p_to_date' => now()->timezone('Asia/Ho_Chi_Minh')->startOfWeek()->addDays(6)->toDateString(),
            'p_division' => '__current__',
        ];

        $parameters = [
            ['name' => 'p_from_date', 'mode' => 'IN', 'data_type' => 'date', 'database_type' => 'date', 'position' => 1, 'required' => true],
            ['name' => 'p_to_date', 'mode' => 'IN', 'data_type' => 'date', 'database_type' => 'date', 'position' => 2, 'required' => true],
            ['name' => 'p_division', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(20)', 'position' => 3, 'required' => true],
        ];
        $fields = [
            ['name' => 'report_date', 'type' => 'date', 'nullable' => false],
            ['name' => 'report_date_display', 'type' => 'string', 'nullable' => false],
            ['name' => 'day_name', 'type' => 'string', 'nullable' => false],
            ['name' => 'arr_rooms', 'type' => 'integer', 'nullable' => false],
            ['name' => 'arr_guests', 'type' => 'integer', 'nullable' => false],
            ['name' => 'dep_rooms', 'type' => 'integer', 'nullable' => false],
            ['name' => 'dep_guests', 'type' => 'integer', 'nullable' => false],
            ['name' => 'occ_rooms', 'type' => 'integer', 'nullable' => false],
            ['name' => 'occ_guests', 'type' => 'integer', 'nullable' => false],
            ['name' => 'available_rooms', 'type' => 'integer', 'nullable' => false],
            ['name' => 'occupancy_rate', 'type' => 'number', 'nullable' => false],
            ['name' => 'Division', 'type' => 'string', 'nullable' => false],
        ];

        $db->table('report_data_sources')->updateOrInsert(['code' => self::SOURCE], [
            'name' => 'Dữ liệu báo cáo phòng hàng tuần',
            'description' => 'Báo cáo 7 ngày từ Thứ Hai đến Chủ Nhật, chuyển đổi từ legacy sp_023_Division trên schema PMS hiện tại.',
            'source_type' => 'procedure',
            'schema_name' => $database,
            'object_name' => 'rpt_weekly_room_report',
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

        $db->table('templates')->updateOrInsert(['report' => self::TEMPLATE], [
            'group' => 'Báo cáo phòng',
            'name' => 'Báo cáo phòng hàng tuần',
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
            'version' => $definition['version'] ?? '1.0',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $templateId = $db->table('templates')->where('report', self::TEMPLATE)->value('id');

        $db->table('report_definitions')->updateOrInsert(['code' => self::REPORT], [
            'name' => 'Báo cáo phòng hàng tuần',
            'group' => 'Báo cáo phòng',
            'description' => 'Tổng hợp Đến, Đi, Ở và Công suất theo 7 ngày trong tuần.',
            'report_data_source_id' => $sourceId,
            'parameter_ui_schema' => json_encode($this->uiSchema(), JSON_UNESCAPED_UNICODE),
            'sort_order' => 14,
            'is_active' => true,
            'show_in_menu' => true,
            'menu_locations' => json_encode(['reservation', 'frontdesk'], JSON_UNESCAPED_UNICODE),
            'menu_top_order' => 20,
            'menu_group_order' => 10,
            'menu_item_order' => 14,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $reportId = $db->table('report_definitions')->where('code', self::REPORT)->value('id');

        $db->table('report_definition_template')->updateOrInsert(
            ['report_definition_id' => $reportId, 'template_id' => $templateId],
            ['is_default' => true, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now]
        );
    }

    private function uiSchema(): array
    {
        return [
            [
                'name' => 'p_week_preset',
                'label' => 'Chọn tuần',
                'control' => 'select',
                'default' => 'this_week',
                'required' => true,
                'options' => [
                    ['value' => 'this_week', 'label' => 'Tuần này'],
                    ['value' => 'last_week', 'label' => 'Tuần trước'],
                    ['value' => 'next_week', 'label' => 'Tuần sau'],
                    ['value' => 'custom', 'label' => 'Tùy chọn tuần...'],
                ],
            ],
            [
                'name' => 'p_from_date',
                'label' => 'Tuần bắt đầu',
                'control' => 'date-range',
                'range_end_parameter' => 'p_to_date',
                'default' => '$today',
                'required' => true,
            ],
            [
                'name' => 'p_to_date',
                'label' => 'Tuần kết thúc',
                'control' => 'hidden',
                'default' => '$today',
                'required' => true,
            ],
            [
                'name' => 'p_division',
                'label' => 'Chi nhánh',
                'control' => 'select',
                'default' => '__current__',
                'required' => true,
                'options_source' => 'branches',
                'options' => [
                    ['value' => '__current__', 'label' => 'Chi nhánh hiện tại'],
                    ['value' => '__all__', 'label' => 'Tất cả chi nhánh'],
                ],
            ],
        ];
    }
};
