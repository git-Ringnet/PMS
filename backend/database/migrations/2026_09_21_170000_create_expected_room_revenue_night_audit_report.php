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
        $visitedDatabases = [];
        $connections = array_values(array_unique(array_merge(
            ['mysql'],
            array_values(config('database_domains.branch_connections', []))
        )));
        foreach ($connections as $connection) {
            $db = DB::connection($connection);
            if ($db->getDriverName() !== 'mysql') {
                continue;
            }
            $database = $db->getDatabaseName();
            if (isset($visitedDatabases[$database])) {
                continue;
            }
            $visitedDatabases[$database] = true;

            $db->unprepared('DROP PROCEDURE IF EXISTS rpt_expected_room_revenue_night_audit');
            $db->unprepared($this->procedureSql());
            $this->syncConfiguration($connection);
        }
    }

    public function procedureSql(): string
    {
        return <<<'SQL'
CREATE PROCEDURE rpt_expected_room_revenue_night_audit(IN p_date DATE)
READS SQL DATA
BEGIN
    DECLARE v_prefix VARCHAR(50) DEFAULT '';
    SELECT COALESCE(prefix_booking_id, '') INTO v_prefix FROM hotel_settings ORDER BY id LIMIT 1;

    WITH eligible_rooms AS (
        SELECT
            br.id AS BookingRoomId,
            br.booking_id AS BookingId,
            br.room_number AS Room,
            br.arrival_date AS ArrivalDate,
            br.departure_date AS DepartureDate,
            br.note AS RoomNote,
            b.booking_name AS BookingName,
            b.company_id AS CompanyId,
            b.note AS BookingNote
        FROM booking_rooms br
        INNER JOIN bookings b ON b.id = br.booking_id
        WHERE br.status IN (0, 1)
          AND br.arrival_date <= p_date
          AND br.departure_date > p_date
    ), posted_source AS (
        SELECT
            sb.Ma AS SourceId,
            sbd.Ma AS DetailNo,
            COALESCE(NULLIF(sb.RentalRoomId2, ''), sb.RentalRoomId1) AS BookingRoomId,
            sbd.ServiceId,
            COALESCE(sbd.DescriptionServive, sb.DescriptionServive, '') AS ServiceName,
            COALESCE(sbd.Amount, 0) AS RateTotal,
            COALESCE(sb.Guest, '') AS GuestSnapshot
        FROM service_bills sb
        INNER JOIN service_bill_details sbd ON sbd.BillServiceId = sb.Ma
        LEFT JOIN room_night_bills rnb ON rnb.bill_id = sb.Ma AND rnb.is_room_night = 1
        WHERE COALESCE(sb.Edit, 0) = 0
          AND sbd.ServiceId IS NOT NULL
          AND COALESCE(sbd.Quantity, 1) <> 0
          AND (
              (sbd.ServiceId = 'RM' AND rnb.date = p_date)
              OR (sbd.ServiceId <> 'RM' AND DATE(sb.Date) = p_date)
          )
    ), posted AS (
        SELECT MIN(SourceId) AS SourceId, MIN(DetailNo) AS DetailNo, BookingRoomId,
               ServiceId, MAX(ServiceName) AS ServiceName, SUM(RateTotal) AS RateTotal,
               MAX(GuestSnapshot) AS GuestSnapshot
        FROM posted_source
        GROUP BY BookingRoomId, ServiceId
    ), projected_source AS (
        SELECT
            brs.id AS SourceId,
            0 AS DetailNo,
            brs.booking_room_id AS BookingRoomId,
            brs.service_code AS ServiceId,
            COALESCE(NULLIF(brs.service_name, ''), brs.service_code) AS ServiceName,
            COALESCE(brs.total_amount, brs.quantity * brs.rate, 0) AS RateTotal,
            '' AS GuestSnapshot
        FROM booking_room_services brs
        WHERE brs.deleted_at IS NULL
          AND COALESCE(brs.is_posted, 0) = 0
          AND brs.service_bill_id IS NULL
          AND COALESCE(brs.quantity, 0) <> 0
          AND DATE(brs.service_date) = p_date
    ), projected AS (
        SELECT MIN(SourceId) AS SourceId, MIN(DetailNo) AS DetailNo, BookingRoomId,
               ServiceId, MAX(ServiceName) AS ServiceName, SUM(RateTotal) AS RateTotal,
               MAX(GuestSnapshot) AS GuestSnapshot
        FROM projected_source
        GROUP BY BookingRoomId, ServiceId
    ), report_rows AS (
        SELECT * FROM posted
        UNION ALL
        SELECT * FROM projected
    ), primary_guest AS (
        SELECT booking_room_id, guest_id,
               ROW_NUMBER() OVER (
                   PARTITION BY booking_room_id
                   ORDER BY is_primary DESC, id ASC
               ) AS row_no
        FROM booking_room_guests
        WHERE COALESCE(status, 0) <> 3
    )
    SELECT
        ROW_NUMBER() OVER (ORDER BY CASE WHEN rr.ServiceId = 'RM' THEN 0 ELSE 1 END, rr.ServiceId, er.Room, er.BookingId, rr.SourceId, rr.DetailNo) AS STT,
        CONCAT(v_prefix, er.BookingId) AS BookingCode,
        COALESCE(NULLIF(rr.GuestSnapshot, ''), NULLIF(TRIM(g.full_name), ''), er.BookingName, '') AS Guest,
        COALESCE(er.Room, '') AS Room,
        DATE_FORMAT(er.ArrivalDate, '%d-%m-%Y') AS ArrivalDate,
        DATE_FORMAT(er.DepartureDate, '%d-%m-%Y') AS DepartureDate,
        rr.ServiceId,
        COALESCE(NULLIF(hs.name, ''), NULLIF(rr.ServiceName, ''), rr.ServiceId, '') AS ServiceName,
        rr.RateTotal,
        COALESCE(c.name, c.trading_name, c.code, '') AS Company,
        COALESCE(NULLIF(er.RoomNote, ''), er.BookingNote, '') AS NoteBooking
    FROM report_rows rr
    INNER JOIN eligible_rooms er ON er.BookingRoomId = rr.BookingRoomId
    LEFT JOIN primary_guest pg ON pg.booking_room_id = er.BookingRoomId AND pg.row_no = 1
    LEFT JOIN guests g ON g.id = pg.guest_id
    LEFT JOIN hotel_services hs ON hs.code = rr.ServiceId
        AND hs.id = (SELECT MIN(hs0.id) FROM hotel_services hs0 WHERE hs0.code = rr.ServiceId)
    LEFT JOIN companies c ON c.id = er.CompanyId
    ORDER BY CASE WHEN rr.ServiceId = 'RM' THEN 0 ELSE 1 END, rr.ServiceId, er.Room, er.BookingId, rr.SourceId, rr.DetailNo;
END
SQL;
    }

    private function syncConfiguration(string $connection): void
    {
        $db = DB::connection($connection);
        $now = now();
        $database = $db->getDatabaseName();
        $parameters = [
            ['name' => 'p_date', 'mode' => 'IN', 'data_type' => 'date', 'database_type' => 'date', 'position' => 1, 'required' => true],
        ];
        $fields = [
            ['name' => 'STT', 'type' => 'integer'], ['name' => 'BookingCode', 'type' => 'string'],
            ['name' => 'Guest', 'type' => 'string'], ['name' => 'Room', 'type' => 'string'],
            ['name' => 'ArrivalDate', 'type' => 'string'], ['name' => 'DepartureDate', 'type' => 'string'],
            ['name' => 'ServiceId', 'type' => 'string'], ['name' => 'ServiceName', 'type' => 'string'],
            ['name' => 'RateTotal', 'type' => 'number'], ['name' => 'Company', 'type' => 'string'],
            ['name' => 'NoteBooking', 'type' => 'string'],
        ];
        $defaults = ['p_date' => now()->toDateString()];

        $db->table('report_data_sources')->updateOrInsert(['code' => self::SOURCE], [
            'name' => 'Dữ liệu doanh thu dự kiến tiền phòng',
            'description' => 'Dự kiến và dịch vụ đã post trong ngày Sang Ngày; theo sp_095 Army, dựa trên booking_room_services và service bills runtime.',
            'source_type' => 'procedure', 'schema_name' => $database, 'object_name' => 'rpt_expected_room_revenue_night_audit',
            'parameter_schema' => json_encode($parameters, JSON_UNESCAPED_UNICODE),
            'field_schema' => json_encode($fields, JSON_UNESCAPED_UNICODE),
            'sample_parameters' => json_encode($defaults, JSON_UNESCAPED_UNICODE), 'max_rows' => 5000,
            'is_active' => true, 'last_discovered_at' => $now, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $sourceId = $db->table('report_data_sources')->where('code', self::SOURCE)->value('id');
        $template = (require database_path('report_templates/expected_room_revenue_reference.php'))->definition();
        $db->table('templates')->updateOrInsert(['report' => self::TEMPLATE], [
            'group' => 'Báo cáo quản lý / Sang ngày', 'name' => 'Báo cáo dự kiến doanh thu tiền phòng',
            'report_data_source_id' => $sourceId, 'parameter_defaults' => json_encode($defaults, JSON_UNESCAPED_UNICODE),
            'page_size' => $template['page_size'], 'page_orientation' => $template['page_orientation'],
            'margin_top' => $template['margin_top'], 'margin_right' => $template['margin_right'],
            'margin_bottom' => $template['margin_bottom'], 'margin_left' => $template['margin_left'],
            'content_json' => json_encode($template['content_json'], JSON_UNESCAPED_UNICODE),
            'content_html' => $template['content_html'], 'css' => $template['css'],
            'is_default' => true, 'version' => '1.0', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $templateId = $db->table('templates')->where('report', self::TEMPLATE)->value('id');
        $ui = [['name' => 'p_date', 'label' => 'Ngày báo cáo', 'control' => 'date', 'default' => '$today', 'required' => true]];
        $db->table('report_definitions')->updateOrInsert(['code' => self::REPORT], [
            'name' => 'Báo cáo dự kiến doanh thu tiền phòng', 'group' => 'Báo cáo quản lý / Sang ngày',
            'description' => 'Xem khoản tiền phòng và dịch vụ dự kiến hoặc đã post trong ngày trước khi chạy Sang Ngày.',
            'report_data_source_id' => $sourceId, 'parameter_ui_schema' => json_encode($ui, JSON_UNESCAPED_UNICODE),
            'sort_order' => 164, 'is_active' => true, 'show_in_menu' => false,
            'menu_locations' => json_encode(['night_audit'], JSON_UNESCAPED_UNICODE),
            'menu_top_order' => 0, 'menu_group_order' => 0, 'menu_item_order' => 164,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $reportId = $db->table('report_definitions')->where('code', self::REPORT)->value('id');
        $db->table('report_definition_template')->updateOrInsert(
            ['report_definition_id' => $reportId, 'template_id' => $templateId],
            ['is_default' => true, 'created_at' => $now, 'updated_at' => $now]
        );
    }

    public function down(): void
    {
        // Keep report metadata and any user-edited Designer template intact.
        $connections = array_values(array_unique(array_merge(
            ['mysql'],
            array_values(config('database_domains.branch_connections', []))
        )));
        foreach ($connections as $connection) {
            $db = DB::connection($connection);
            if ($db->getDriverName() === 'mysql') {
                $db->unprepared('DROP PROCEDURE IF EXISTS rpt_expected_room_revenue_night_audit');
            }
        }
    }
};
