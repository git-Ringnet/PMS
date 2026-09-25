<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SOURCE = 'RPT_ROOM_FORECAST';
    private const REPORT = 'ROOM_FORECAST';
    private const TEMPLATE = 'ROOM_FORECAST_REFERENCE';
    private const PROCEDURE = 'rpt_room_forecast';

    public function up(): void
    {
        $visitedDatabases = [];

        foreach ($this->targetConnections() as $connectionName) {
            $db = DB::connection($connectionName);
            if ($db->getDriverName() !== 'mysql') {
                continue;
            }

            $database = $db->getDatabaseName();
            if (isset($visitedDatabases[$database])) {
                continue;
            }
            $visitedDatabases[$database] = true;

            $db->unprepared('DROP PROCEDURE IF EXISTS `'.self::PROCEDURE.'`');
            $db->unprepared($this->procedureSql());
            $this->syncConfiguration($db, $database);
        }
    }

    public function down(): void
    {
        $visitedDatabases = [];

        foreach ($this->targetConnections() as $connectionName) {
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
            if ($reportId) {
                $db->table('report_definition_template')->where('report_definition_id', $reportId)->delete();
                $db->table('report_definitions')->where('id', $reportId)->delete();
            }
            $db->table('templates')->where('report', self::TEMPLATE)->delete();
            $db->table('report_data_sources')->where('code', self::SOURCE)->delete();
            $db->unprepared('DROP PROCEDURE IF EXISTS `'.self::PROCEDURE.'`');
        }
    }

    public function procedureSql(): string
    {
        return <<<'SQL'
CREATE PROCEDURE rpt_room_forecast(
    IN p_from_date DATE,
    IN p_to_date DATE,
    IN p_user VARCHAR(50),
    IN p_branch VARCHAR(20),
    IN p_room_type VARCHAR(20),
    IN p_revenue_detail TINYINT,
    IN p_include_breakfast TINYINT
)
READS SQL DATA
BEGIN
    DECLARE v_from DATE;
    DECLARE v_to DATE;
    DECLARE v_swap DATE;
    DECLARE v_total_rooms INT DEFAULT 0;
    DECLARE v_breakfast_adult_rate DECIMAL(15,2) DEFAULT 0;

    SET v_from = COALESCE(p_from_date, CURRENT_DATE());
    SET v_to = COALESCE(p_to_date, v_from);
    IF v_to < v_from THEN
        SET v_swap = v_from;
        SET v_from = v_to;
        SET v_to = v_swap;
    END IF;
    SET p_include_breakfast = COALESCE(p_include_breakfast, 1);

    SET v_breakfast_adult_rate = COALESCE((
        SELECT hs.breakfast_adult_rate
        FROM hotel_settings hs
        ORDER BY hs.id
        LIMIT 1
    ), 0);

    SELECT COUNT(*)
      INTO v_total_rooms
    FROM rooms r
    WHERE COALESCE(r.is_internal, 0) = 0
      AND COALESCE(r.room_number, '') NOT LIKE '0%'
      AND (
          COALESCE(p_room_type, '') = ''
          OR CAST(r.room_class_id AS CHAR) = p_room_type
      );

    WITH RECURSIVE calendar AS (
        SELECT v_from AS dt
        UNION ALL
        SELECT dt + INTERVAL 1 DAY
        FROM calendar
        WHERE dt < v_to
    ), eligible_rooms AS (
        SELECT
            br.id,
            br.arrival_date,
            br.departure_date,
            br.rate,
            br.rate_code,
            br.breakfast,
            br.extra_bed_qty,
            br.extra_bed_rate,
            br.adults,
            br.children_qty,
            COALESCE(r.is_internal, 0) AS room_internal,
            COALESCE(r.room_number, '') AS room_number,
            b.breakfast_included AS booking_breakfast_included
        FROM booking_rooms br
        INNER JOIN bookings b ON b.id = br.booking_id
        LEFT JOIN rooms r ON r.room_number = br.room_number
        WHERE br.deleted_at IS NULL
          AND b.deleted_at IS NULL
          AND b.status NOT IN (3, 4)
          AND br.status IN (0, 1, 2)
          AND (COALESCE(p_user, '') = '' OR b.sales_person = p_user OR b.created_by = p_user)
          AND (
              COALESCE(p_room_type, '') = ''
              OR CAST(COALESCE(r.room_class_id, br.room_class_id) AS CHAR) = p_room_type
          )
          AND (r.room_number IS NULL OR r.room_number NOT LIKE '0%')
    ), daily_base AS (
        SELECT
            c.dt AS report_date,
            COUNT(DISTINCT CASE
                WHEN er.departure_date = c.dt THEN er.id
            END) AS DepRooms,
            COALESCE(SUM(CASE
                WHEN er.departure_date = c.dt
                THEN COALESCE(er.adults, 0) + COALESCE(er.children_qty, 0)
                ELSE 0
            END), 0) AS DepAdult,
            COUNT(DISTINCT CASE
                WHEN er.arrival_date = c.dt THEN er.id
            END) AS ArrRooms,
            COALESCE(SUM(CASE
                WHEN er.arrival_date = c.dt
                THEN COALESCE(er.adults, 0) + COALESCE(er.children_qty, 0)
                ELSE 0
            END), 0) AS ArrAdult,
            COUNT(DISTINCT CASE
                WHEN c.dt >= er.arrival_date AND c.dt < er.departure_date THEN er.id
            END) AS OccRooms,
            COALESCE(SUM(CASE
                WHEN c.dt >= er.arrival_date AND c.dt < er.departure_date
                THEN COALESCE(er.adults, 0) + COALESCE(er.children_qty, 0)
                ELSE 0
            END), 0) AS OccAdult,
            COUNT(DISTINCT CASE
                WHEN c.dt >= er.arrival_date AND c.dt < er.departure_date
                 AND (er.room_internal = 1 OR UPPER(COALESCE(er.rate_code, '')) = 'HU')
                THEN er.id
            END) AS HouseUse,
            COUNT(DISTINCT CASE
                WHEN c.dt >= er.arrival_date AND c.dt < er.departure_date
                 AND (er.room_internal = 0 OR er.room_internal IS NULL)
                 AND (
                     COALESCE(er.rate, 0) = 0
                     OR UPPER(COALESCE(er.rate_code, '')) LIKE 'FOC%'
                     OR er.rate_code IS NULL
                 )
                THEN er.id
            END) AS FOCAll,
            COALESCE(SUM(CASE
                WHEN c.dt >= er.arrival_date AND c.dt < er.departure_date
                THEN COALESCE(er.rate, 0)
                   + COALESCE(er.extra_bed_rate, 0) * COALESCE(er.extra_bed_qty, 0)
                   + CASE
                       WHEN p_include_breakfast <> 0
                        AND (COALESCE(er.breakfast, 0) = 1 OR COALESCE(er.booking_breakfast_included, 0) = 1)
                       THEN COALESCE(er.adults, 0) * v_breakfast_adult_rate
                       ELSE 0
                     END
                ELSE 0
            END), 0) AS Revenue,
            (
                SELECT COUNT(*)
                FROM rooms available_room
                WHERE COALESCE(available_room.is_internal, 0) = 0
                  AND COALESCE(available_room.room_number, '') NOT LIKE '0%'
                  AND (
                      COALESCE(p_room_type, '') = ''
                      OR CAST(available_room.room_class_id AS CHAR) = p_room_type
                  )
                  AND (
                      LOWER(COALESCE(available_room.room_status_code, '')) = 'ooo'
                      OR EXISTS (
                          SELECT 1
                          FROM room_locks rl
                          WHERE rl.room_number = available_room.room_number
                            AND UPPER(COALESCE(rl.lock_type, 'OOO')) = 'OOO'
                            AND COALESCE(rl.is_active, 1) = 1
                            AND (rl.start_date IS NULL OR DATE(rl.start_date) <= c.dt)
                            AND (rl.end_date IS NULL OR DATE(rl.end_date) >= c.dt)
                      )
                  )
            ) AS OOO
        FROM calendar c
        LEFT JOIN eligible_rooms er ON 1 = 1
        GROUP BY c.dt
    )
    SELECT
        DATE_FORMAT(report_date, '%d/%m/%Y') AS `Date`,
        DepRooms,
        DepAdult,
        ArrRooms,
        ArrAdult,
        OccRooms,
        OccAdult,
        HouseUse,
        FOCAll,
        GREATEST(OccRooms - HouseUse - FOCAll, 0) AS RoomSales,
        CAST(Revenue AS DECIMAL(15,2)) AS Revenue,
        CAST(ROUND(Revenue / NULLIF(OccRooms - HouseUse, 0), 0) AS DECIMAL(15,2)) AS AvgRate,
        CAST(ROUND(Revenue / NULLIF(GREATEST(OccRooms - HouseUse - FOCAll, 0), 0), 0) AS DECIMAL(15,2)) AS AvgRate2,
        GREATEST(v_total_rooms - OOO, 1) AS RoomAvible,
        CAST(ROUND((OccRooms / GREATEST(v_total_rooms - OOO, 1)) * 100, 2) AS DECIMAL(8,2)) AS PercentOccupancy,
        CAST(ROUND((GREATEST(OccRooms - HouseUse - FOCAll, 0) / GREATEST(v_total_rooms - OOO, 1)) * 100, 2) AS DECIMAL(8,2)) AS PercentOccupancy1,
        CAST(ROUND(Revenue / GREATEST(v_total_rooms - OOO, 1), 0) AS DECIMAL(15,2)) AS RevPAR
    FROM daily_base
    ORDER BY report_date ASC;
END
SQL;
    }

    private function targetConnections(): array
    {
        return [DB::getDefaultConnection()];
    }

    private function syncConfiguration($db, string $database): void
    {
        $now = now();
        $parameters = [
            ['name' => 'p_from_date', 'mode' => 'IN', 'data_type' => 'date', 'database_type' => 'date', 'position' => 1, 'required' => true],
            ['name' => 'p_to_date', 'mode' => 'IN', 'data_type' => 'date', 'database_type' => 'date', 'position' => 2, 'required' => true],
            ['name' => 'p_user', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(50)', 'position' => 3, 'required' => false],
            ['name' => 'p_branch', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(20)', 'position' => 4, 'required' => false],
            ['name' => 'p_room_type', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(20)', 'position' => 5, 'required' => false],
            ['name' => 'p_revenue_detail', 'mode' => 'IN', 'data_type' => 'tinyint', 'database_type' => 'tinyint', 'position' => 6, 'required' => false],
            ['name' => 'p_include_breakfast', 'mode' => 'IN', 'data_type' => 'tinyint', 'database_type' => 'tinyint', 'position' => 7, 'required' => false],
        ];
        $fields = [
            ['name' => 'Date', 'type' => 'string'],
            ['name' => 'DepRooms', 'type' => 'integer'],
            ['name' => 'DepAdult', 'type' => 'integer'],
            ['name' => 'ArrRooms', 'type' => 'integer'],
            ['name' => 'ArrAdult', 'type' => 'integer'],
            ['name' => 'OccRooms', 'type' => 'integer'],
            ['name' => 'OccAdult', 'type' => 'integer'],
            ['name' => 'HouseUse', 'type' => 'integer'],
            ['name' => 'FOCAll', 'type' => 'integer'],
            ['name' => 'RoomSales', 'type' => 'integer'],
            ['name' => 'Revenue', 'type' => 'number'],
            ['name' => 'AvgRate', 'type' => 'number'],
            ['name' => 'AvgRate2', 'type' => 'number'],
            ['name' => 'RoomAvible', 'type' => 'integer'],
            ['name' => 'PercentOccupancy', 'type' => 'number'],
            ['name' => 'PercentOccupancy1', 'type' => 'number'],
            ['name' => 'RevPAR', 'type' => 'number'],
        ];
        $defaults = [
            'p_from_date' => now()->toDateString(),
            'p_to_date' => now()->toDateString(),
            'p_user' => '',
            'p_branch' => '__current__',
            'p_room_type' => '',
            'p_revenue_detail' => 1,
            'p_include_breakfast' => 1,
        ];

        $db->table('report_data_sources')->updateOrInsert(['code' => self::SOURCE], [
            'name' => 'Dữ liệu báo cáo dự đoán bán phòng',
            'description' => 'Dự báo phòng đi, phòng đến, phòng ở, doanh thu phòng và công suất theo sp_023 legacy trên connection chi nhánh hiện tại.',
            'source_type' => 'procedure',
            'schema_name' => $database,
            'object_name' => self::PROCEDURE,
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

        $definition = (require database_path('report_templates/room_forecast_reference.php'))->definition();
        $template = $db->table('templates')->where('report', self::TEMPLATE)->first();
        $templateValues = [
            'group' => 'Báo cáo phòng',
            'name' => $definition['name'],
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
            'updated_at' => $now,
        ];
        if (! $template) {
            $templateValues['report'] = self::TEMPLATE;
            $templateValues['created_at'] = $now;
            $templateId = $db->table('templates')->insertGetId($templateValues);
        } else {
            $templateId = $template->id;
            $db->table('templates')->where('id', $templateId)->update([
                'report_data_source_id' => $sourceId,
                'updated_at' => $now,
            ]);
        }

        $ui = [
            ['name' => 'p_from_date', 'label' => 'Chọn ngày', 'control' => 'date-range', 'range_end_parameter' => 'p_to_date', 'default' => '$today', 'required' => true],
            ['name' => 'p_to_date', 'label' => 'Đến ngày', 'control' => 'hidden', 'default' => '$today', 'required' => true],
            ['name' => 'p_user', 'label' => 'Người dùng / Người bán', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'users'],
            ['name' => 'p_branch', 'label' => 'Chi nhánh', 'control' => 'hidden', 'default' => '__current__', 'required' => false],
            ['name' => 'p_room_type', 'label' => 'Loại phòng', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'room-classes'],
            ['name' => 'p_revenue_detail', 'label' => 'Hiển thị chi tiết doanh thu', 'control' => 'checkbox', 'default' => true, 'required' => false],
            ['name' => 'p_include_breakfast', 'label' => 'Doanh thu phòng bao gồm ăn sáng', 'control' => 'checkbox', 'default' => true, 'required' => false],
        ];

        $db->table('report_definitions')->updateOrInsert(['code' => self::REPORT], [
            'name' => $definition['name'],
            'group' => 'Báo cáo phòng',
            'description' => 'Báo cáo dự đoán bán phòng 17 cột FO; bỏ 4 cột tài chính ở chế độ HK bằng p_revenue_detail.',
            'report_data_source_id' => $sourceId,
            'parameter_ui_schema' => json_encode($ui, JSON_UNESCAPED_UNICODE),
            'sort_order' => 169,
            'is_active' => true,
            'show_in_menu' => true,
            'menu_locations' => json_encode(['frontdesk', 'housekeeping'], JSON_UNESCAPED_UNICODE),
            'menu_top_order' => 20,
            'menu_group_order' => 20,
            'menu_item_order' => 169,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $reportId = $db->table('report_definitions')->where('code', self::REPORT)->value('id');
        $db->table('report_definition_template')->updateOrInsert(
            ['report_definition_id' => $reportId, 'template_id' => $templateId],
            ['is_default' => true, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now]
        );
    }
};
