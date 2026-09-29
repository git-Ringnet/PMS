<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SOURCE = 'RPT_ROOM_RATE_STATISTICS';
    private const REPORT = 'ROOM_RATE_STATISTICS';
    private const TEMPLATE = 'ROOM_RATE_STATISTICS_REFERENCE';

    public function up(): void
    {
        $visitedDatabases = [];

        foreach ([DB::getDefaultConnection()] as $conn) {
            try {
                if (DB::connection($conn)->getDriverName() !== 'mysql') {
                    continue;
                }

                $database = DB::connection($conn)->getDatabaseName();
                if (isset($visitedDatabases[$database])) {
                    continue;
                }
                $visitedDatabases[$database] = true;

                // 1. Create or replace Stored Procedure
                DB::connection($conn)->unprepared('DROP PROCEDURE IF EXISTS rpt_room_rate_statistics');
                DB::connection($conn)->unprepared($this->procedureSql());

                // 2. Sync report metadata and templates
                $this->syncReportConfiguration($conn);
            } catch (\Throwable $e) {
                // Preserve best-effort handling for this target database.
            }
        }
    }

    public function procedureSql(): string
    {
        return <<<'SQL'
CREATE PROCEDURE rpt_room_rate_statistics(
    IN p_from_date VARCHAR(20),
    IN p_to_date VARCHAR(20),
    IN p_rate_code VARCHAR(500)
)
READS SQL DATA
BEGIN
    DECLARE v_division VARCHAR(20) DEFAULT '';
    DECLARE v_from DATE;
    DECLARE v_to DATE;

    SELECT COALESCE(prefix_booking_id, '') INTO v_division FROM hotel_settings LIMIT 1;

    IF p_from_date LIKE '%/%' THEN
        SET v_from = STR_TO_DATE(LEFT(p_from_date, 10), '%d/%m/%Y');
    ELSE
        SET v_from = CAST(LEFT(p_from_date, 10) AS DATE);
    END IF;

    IF p_to_date LIKE '%/%' THEN
        SET v_to = STR_TO_DATE(LEFT(p_to_date, 10), '%d/%m/%Y');
    ELSE
        SET v_to = CAST(LEFT(p_to_date, 10) AS DATE);
    END IF;

    WITH valid_rooms AS (
        SELECT
            pt.id AS RoomId,
            pt.booking_id AS BookingId,
            pt.room_number AS RoomNumber,
            pt.room_class_id AS RoomClassId,
            pt.arrival_date AS ArrivalDate,
            COALESCE(pt.departure_date, pt.CheckoutDate) AS DepartureDate,
            COALESCE(pt.ActutalNumOfDays, pt.NumOfDays, DATEDIFF(COALESCE(pt.departure_date, pt.CheckoutDate), pt.arrival_date), 0) AS NumOfDays,
            COALESCE(pt.adults, 0) AS Adults,
            COALESCE(pt.children_qty, 0) AS Children,
            COALESCE(pt.rate_code, '') AS RateCode
        FROM booking_rooms pt
        WHERE COALESCE(pt.rate_code, '') <> ''
          AND pt.room_number NOT LIKE '0%'
          AND pt.status NOT IN (3, 4)
          AND (
              COALESCE(p_rate_code, '') = ''
              OR FIND_IN_SET(pt.rate_code, p_rate_code) > 0
          )
          AND (
              pt.arrival_date BETWEEN v_from AND v_to
              OR COALESCE(pt.departure_date, pt.CheckoutDate) BETWEEN v_from AND v_to
              OR (pt.arrival_date < v_from AND COALESCE(pt.departure_date, pt.CheckoutDate) > v_to)
          )
    )
    SELECT
        ROW_NUMBER() OVER (
            ORDER BY
                r.RateCode ASC,
                r.ArrivalDate ASC,
                r.BookingId ASC,
                r.RoomId ASC
        ) AS `Index`,
        CONCAT(v_division, r.BookingId) AS BookingCode,
        COALESCE(dk.booking_name, '') AS BookingName,
        COALESCE(comp.name, comp.trading_name, comp.code, 'KHÁCH LẺ') AS CompanyName,
        DATE_FORMAT(r.ArrivalDate, '%d-%m-%Y') AS ArrivalDate,
        DATE_FORMAT(r.DepartureDate, '%d-%m-%Y') AS DepartureDate,
        r.NumOfDays,
        r.RoomNumber AS Room,
        r.Adults,
        r.Children,
        COALESCE(lp.code, lp.name, '') AS RoomType,
        r.RateCode,
        COALESCE(rc.Description, rc.Ma, r.RateCode) AS RateCodeDescription
    FROM valid_rooms r
    LEFT JOIN bookings dk ON dk.id = r.BookingId
    LEFT JOIN room_classes lp ON lp.id = r.RoomClassId
    LEFT JOIN companies comp ON (comp.id = dk.company_id OR comp.code = dk.company_id)
    LEFT JOIN room_rate_codes rc ON rc.Ma = r.RateCode
    ORDER BY
        r.RateCode ASC,
        r.ArrivalDate ASC,
        r.BookingId ASC,
        r.RoomId ASC;
END
SQL;
    }

    private function syncReportConfiguration(string $conn): void
    {
        $now = now();
        $db = DB::connection($conn);
        $database = $db->getDatabaseName();

        $parameters = [
            ['name' => 'p_from_date', 'type' => 'date', 'required' => true],
            ['name' => 'p_to_date', 'type' => 'date', 'required' => true],
            ['name' => 'p_rate_code', 'type' => 'string', 'required' => false],
        ];

        $fields = [
            ['name' => 'Index', 'type' => 'integer'],
            ['name' => 'BookingCode', 'type' => 'string'],
            ['name' => 'BookingName', 'type' => 'string'],
            ['name' => 'CompanyName', 'type' => 'string'],
            ['name' => 'ArrivalDate', 'type' => 'string'],
            ['name' => 'DepartureDate', 'type' => 'string'],
            ['name' => 'NumOfDays', 'type' => 'integer'],
            ['name' => 'Room', 'type' => 'string'],
            ['name' => 'Adults', 'type' => 'integer'],
            ['name' => 'Children', 'type' => 'integer'],
            ['name' => 'RoomType', 'type' => 'string'],
            ['name' => 'RateCode', 'type' => 'string'],
            ['name' => 'RateCodeDescription', 'type' => 'string'],
        ];

        $defaults = [
            'p_from_date' => now()->startOfDay()->format('Y-m-d'),
            'p_to_date' => now()->endOfDay()->format('Y-m-d'),
            'p_rate_code' => '',
        ];

        $db->table('report_data_sources')->updateOrInsert(['code' => self::SOURCE], [
            'name' => 'Dữ liệu báo cáo thống kê mã giá phòng',
            'description' => 'MySQL chuyển đổi theo legacy sp_287; thống kê phòng và số đêm theo từng mã giá phòng.',
            'source_type' => 'procedure',
            'schema_name' => $database,
            'object_name' => 'rpt_room_rate_statistics',
            'parameter_schema' => json_encode($parameters, JSON_UNESCAPED_UNICODE),
            'field_schema' => json_encode($fields, JSON_UNESCAPED_UNICODE),
            'sample_parameters' => json_encode($defaults),
            'max_rows' => 5000,
            'is_active' => true,
            'last_discovered_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $sourceId = $db->table('report_data_sources')->where('code', self::SOURCE)->value('id');

        $definition = (require database_path('report_templates/room_rate_statistics_reference.php'))->definition();
        $db->table('templates')->updateOrInsert(['report' => self::TEMPLATE], [
            'group' => 'Báo cáo thống kê',
            'name' => 'Báo cáo thống kê mã giá phòng',
            'report_data_source_id' => $sourceId,
            'parameter_defaults' => json_encode($defaults),
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
        $templateId = $db->table('templates')->where('report', self::TEMPLATE)->value('id');

        $ui = [
            ['name' => 'p_from_date', 'label' => 'Chọn ngày', 'control' => 'date-range', 'range_end_parameter' => 'p_to_date', 'default' => '$today', 'required' => true],
            ['name' => 'p_to_date', 'label' => 'Đến ngày', 'control' => 'hidden', 'default' => '$today', 'required' => true],
            ['name' => 'p_rate_code', 'label' => 'Mã giá phòng', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'rate-codes'],
        ];

        $db->table('report_definitions')->updateOrInsert(['code' => self::REPORT], [
            'name' => 'Báo cáo thống kê mã giá phòng',
            'group' => 'Báo cáo thống kê',
            'description' => 'Báo cáo thống kê lượt phòng theo mã giá phòng theo sp_287 legacy.',
            'report_data_source_id' => $sourceId,
            'parameter_ui_schema' => json_encode($ui, JSON_UNESCAPED_UNICODE),
            'sort_order' => 180,
            'is_active' => true,
            'show_in_menu' => true,
            'menu_locations' => json_encode(['frontdesk', 'reservation', 'report']),
            'menu_top_order' => 20,
            'menu_group_order' => 30,
            'menu_item_order' => 180,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $reportId = $db->table('report_definitions')->where('code', self::REPORT)->value('id');

        $db->table('report_definition_template')->updateOrInsert(
            ['report_definition_id' => $reportId, 'template_id' => $templateId],
            ['is_default' => true, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now]
        );
    }

    public function down(): void
    {
        // Preserve data
    }
};
