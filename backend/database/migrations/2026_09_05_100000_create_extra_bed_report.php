<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SOURCE = 'EXTRA_BED';
    private const REPORT = 'EXTRA_BED';
    private const TEMPLATE = 'EXTRA_BED_STANDARD';

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_extra_bed');
        DB::unprepared(<<<'SQL'
CREATE PROCEDURE rpt_extra_bed(
    IN p_from_date DATE,
    IN p_to_date DATE,
    IN p_booking_id VARCHAR(50)
)
READS SQL DATA
BEGIN
    DECLARE v_system_date DATE;
    SELECT DATE(system_date) INTO v_system_date FROM system_date_rolls ORDER BY id DESC LIMIT 1;
    SET v_system_date = COALESCE(v_system_date, CURRENT_DATE());

    WITH linked_eb AS (
        SELECT
            brs.service_bill_id AS BillId,
            MAX(brs.booking_room_id) AS BookingRoomId,
            SUM(brs.quantity) AS Quantity,
            MAX(brs.rate) AS Rate,
            SUM(brs.total_amount) AS Total
        FROM booking_room_services AS brs
        WHERE brs.service_code = 'EB'
          AND brs.service_bill_id IS NOT NULL
        GROUP BY brs.service_bill_id
    ), posted_detail AS (
        SELECT
            sbd.BillServiceId,
            SUM(sbd.Quantity) AS Quantity,
            SUM(sbd.Amount) AS Total,
            MAX(sbd.OriginalRate) AS Rate
        FROM service_bill_details AS sbd
        WHERE sbd.ServiceId = 'EB'
        GROUP BY sbd.BillServiceId
    ), posted AS (
        SELECT
            sb.Ma AS BillId,
            DATE(sb.Date) AS ServiceDate,
            COALESCE(le.BookingRoomId, br2.id, br1.id) AS BookingRoomId,
            COALESCE(NULLIF(le.Quantity, 0), NULLIF(sb.Quantity, 0), NULLIF(pd.Quantity, 0), 0) AS ExtraBedQuantity,
            CASE
                WHEN COALESCE(NULLIF(le.Quantity, 0), NULLIF(sb.Quantity, 0), NULLIF(pd.Quantity, 0), 0) > 0
                    THEN COALESCE(sb.Amount, le.Total, pd.Total, 0) / COALESCE(NULLIF(le.Quantity, 0), NULLIF(sb.Quantity, 0), NULLIF(pd.Quantity, 0))
                ELSE COALESCE(le.Rate, pd.Rate, 0)
            END AS ExtraBedRate,
            COALESCE(sb.Amount, le.Total, pd.Total, 0) AS ExtraBedTotal,
            sb.Guest AS GuestSnapshot,
            1 AS IsPosted,
            'service_bill' AS Source
        FROM service_bills AS sb
        LEFT JOIN linked_eb AS le ON le.BillId = sb.Ma
        LEFT JOIN posted_detail AS pd ON pd.BillServiceId = sb.Ma
        LEFT JOIN booking_rooms AS br2 ON br2.id = sb.RentalRoomId2
        LEFT JOIN booking_rooms AS br1 ON br1.id = sb.RentalRoomId1
        WHERE sb.ServiceId = 'EB'
          AND COALESCE(sb.Edit, 0) = 0
          AND sb.Date >= p_from_date
          AND sb.Date < DATE_ADD(p_to_date, INTERVAL 1 DAY)
          AND COALESCE(NULLIF(le.Quantity, 0), NULLIF(sb.Quantity, 0), NULLIF(pd.Quantity, 0), 0) > 0
    ), setup AS (
        SELECT
            brs.id AS BillId,
            brs.service_date AS ServiceDate,
            brs.booking_room_id AS BookingRoomId,
            brs.quantity AS ExtraBedQuantity,
            brs.rate AS ExtraBedRate,
            brs.total_amount AS ExtraBedTotal,
            NULL AS GuestSnapshot,
            0 AS IsPosted,
            'booking_room_service' AS Source
        FROM booking_room_services AS brs
        INNER JOIN booking_rooms AS active_br ON active_br.id = brs.booking_room_id
            AND active_br.deleted_at IS NULL
            AND active_br.status IN (0, 1, 2)
        WHERE brs.deleted_at IS NULL
          AND brs.service_code = 'EB'
          AND brs.service_date >= GREATEST(p_from_date, v_system_date)
          AND brs.service_date < DATE_ADD(p_to_date, INTERVAL 1 DAY)
          AND COALESCE(brs.is_posted, 0) = 0
          AND brs.service_bill_id IS NULL
          AND brs.quantity > 0
          AND (active_br.room_number IS NULL OR active_br.room_number NOT LIKE '0%')
          AND NOT EXISTS (
              SELECT 1
              FROM service_bills AS existing_bill
              WHERE existing_bill.ServiceId = 'EB'
                AND COALESCE(existing_bill.Edit, 0) = 0
                AND DATE(existing_bill.Date) = brs.service_date
                AND COALESCE(existing_bill.RentalRoomId2, existing_bill.RentalRoomId1) = brs.booking_room_id
          )
    ), rows_source AS (
        SELECT BillId, ServiceDate, BookingRoomId, ExtraBedQuantity, ExtraBedRate, ExtraBedTotal, GuestSnapshot, IsPosted, Source FROM posted
        UNION ALL
        SELECT BillId, ServiceDate, BookingRoomId, ExtraBedQuantity, ExtraBedRate, ExtraBedTotal, GuestSnapshot, IsPosted, Source FROM setup
    ), posted_room_rate AS (
        SELECT
            sb.RentalRoomId1 AS BookingRoomId,
            DATE(rnb.date) AS ServiceDate,
            rnb.rate,
            ROW_NUMBER() OVER (
                PARTITION BY sb.RentalRoomId1, DATE(rnb.date)
                ORDER BY sb.Ma DESC
            ) AS row_no
        FROM service_bills AS sb
        INNER JOIN room_night_bills AS rnb ON rnb.bill_id = sb.Ma AND rnb.is_room_night = 1
        WHERE sb.ServiceId = 'RM'
          AND COALESCE(sb.Edit, 0) = 0
    ), setup_room_rate AS (
        SELECT booking_room_id AS BookingRoomId, service_date AS ServiceDate, MAX(rate) AS Rate
        FROM booking_room_services
        WHERE service_code = 'RM' AND deleted_at IS NULL
        GROUP BY booking_room_id, service_date
    ), primary_guest AS (
        SELECT
            brg.booking_room_id,
            brg.guest_id,
            ROW_NUMBER() OVER (
                PARTITION BY brg.booking_room_id
                ORDER BY brg.is_primary DESC,
                    CASE brg.status WHEN 1 THEN 0 WHEN 0 THEN 1 WHEN 2 THEN 2 ELSE 3 END,
                    brg.id
            ) AS row_no
        FROM booking_room_guests AS brg
        WHERE brg.status <> 3
    )
    SELECT
        ROW_NUMBER() OVER (ORDER BY rs.ServiceDate, br.room_number, b.id, rs.BillId) AS STT,
        CONCAT(COALESCE(hs.prefix_booking_id, 'GAL'), b.id) AS BookingCode,
        b.booking_name AS BookingName,
        br.room_number AS Room,
        COALESCE(NULLIF(rs.GuestSnapshot, ''), NULLIF(TRIM(CONCAT_WS(' ', NULLIF(g.title, ''), g.full_name)), ''), b.booking_name) AS Guest,
        rc.name AS RoomType,
        DATE_FORMAT(br.arrival_date, '%d/%m/%Y') AS ArrivalDate,
        DATE_FORMAT(COALESCE(br.CheckoutDate, br.departure_date), '%d/%m/%Y') AS DepartureDate,
        br.adults AS Adults,
        br.babies AS Babies,
        br.children_qty AS Children,
        COALESCE(prr.rate, srr.Rate, br.rate, 0) AS RoomRate,
        DATE_FORMAT(rs.ServiceDate, '%d/%m/%Y') AS ServiceDate,
        DATE_FORMAT(rs.ServiceDate, '%Y-%m-%d') AS ServiceDateSort,
        rs.ExtraBedQuantity,
        rs.ExtraBedRate,
        rs.ExtraBedTotal,
        rs.IsPosted,
        rs.Source,
        rs.BillId
    FROM rows_source AS rs
    INNER JOIN booking_rooms AS br ON br.id = rs.BookingRoomId
    INNER JOIN bookings AS b ON b.id = br.booking_id
    LEFT JOIN room_classes AS rc ON rc.id = br.room_class_id
    LEFT JOIN primary_guest AS pg ON pg.booking_room_id = br.id AND pg.row_no = 1
    LEFT JOIN guests AS g ON g.id = pg.guest_id
    LEFT JOIN hotel_settings AS hs ON hs.id = (SELECT MIN(id) FROM hotel_settings)
    LEFT JOIN posted_room_rate AS prr ON prr.BookingRoomId = br.id AND prr.ServiceDate = rs.ServiceDate AND prr.row_no = 1
    LEFT JOIN setup_room_rate AS srr ON srr.BookingRoomId = br.id AND srr.ServiceDate = rs.ServiceDate
    WHERE (p_booking_id IS NULL OR p_booking_id = '' OR b.id = p_booking_id)
    ORDER BY rs.ServiceDate, br.room_number, b.id, rs.BillId;
END
SQL);

        $now = now();
        $database = DB::connection()->getDatabaseName();
        $parameters = [
            ['name' => 'p_from_date', 'mode' => 'IN', 'data_type' => 'date', 'database_type' => 'date', 'position' => 1, 'required' => true],
            ['name' => 'p_to_date', 'mode' => 'IN', 'data_type' => 'date', 'database_type' => 'date', 'position' => 2, 'required' => true],
            ['name' => 'p_booking_id', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(50)', 'position' => 3, 'required' => false],
        ];
        $fields = ['STT', 'BookingCode', 'BookingName', 'Room', 'Guest', 'RoomType', 'ArrivalDate', 'DepartureDate', 'Adults', 'Babies', 'Children', 'RoomRate', 'ServiceDate', 'ServiceDateSort', 'ExtraBedQuantity', 'ExtraBedRate', 'ExtraBedTotal', 'IsPosted', 'Source', 'BillId'];
        $numeric = ['STT', 'Adults', 'Babies', 'Children', 'RoomRate', 'ExtraBedQuantity', 'ExtraBedRate', 'ExtraBedTotal', 'IsPosted'];
        $fieldSchema = array_map(fn (string $name) => ['name' => $name, 'type' => in_array($name, $numeric, true) ? 'number' : 'string', 'nullable' => !in_array($name, ['STT', 'BookingCode', 'Room', 'ServiceDate'], true)], $fields);
        $defaults = ['p_from_date' => now()->toDateString(), 'p_to_date' => now()->toDateString(), 'p_booking_id' => ''];
        $ui = [
            ['name' => 'p_from_date', 'label' => 'Từ ngày', 'control' => 'date-range', 'range_end_parameter' => 'p_to_date', 'default' => '$today', 'required' => true, 'options' => []],
            ['name' => 'p_to_date', 'label' => 'Đến ngày', 'control' => 'hidden', 'default' => '$today', 'required' => true, 'options' => []],
            ['name' => 'p_booking_id', 'label' => 'Đăng ký', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'bookings', 'options' => []],
        ];

        DB::table('report_data_sources')->updateOrInsert(['code' => self::SOURCE], [
            'name' => 'Dữ liệu báo cáo Extra Bed', 'description' => 'Extra Bed theo ngày, gồm setup chưa post và bill EB đã post.', 'source_type' => 'procedure',
            'schema_name' => $database, 'object_name' => 'rpt_extra_bed', 'parameter_schema' => json_encode($parameters, JSON_UNESCAPED_UNICODE),
            'field_schema' => json_encode($fieldSchema, JSON_UNESCAPED_UNICODE), 'sample_parameters' => json_encode($defaults), 'max_rows' => 5000,
            'is_active' => true, 'last_discovered_at' => $now, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $sourceId = DB::table('report_data_sources')->where('code', self::SOURCE)->value('id');
        $templateId = DB::table('templates')->where('report', self::TEMPLATE)->value('id');
        $templateCreated = $templateId === null;
        if ($templateCreated) {
            $templateId = DB::table('templates')->insertGetId([
                'group' => 'Báo cáo phòng', 'name' => 'Báo cáo Extra Bed', 'report' => self::TEMPLATE,
                'report_data_source_id' => $sourceId, 'parameter_defaults' => json_encode($defaults),
                'page_size' => 'A4', 'page_orientation' => 'landscape', 'margin_top' => 6, 'margin_bottom' => 6, 'margin_left' => 5, 'margin_right' => 5,
                'content_json' => json_encode(['header' => [], 'detail' => [], 'footer' => []]), 'content_html' => '<h1>BÁO CÁO EXTRA BED</h1>',
                'css' => '', 'is_default' => false, 'version' => '1.0', 'created_at' => $now, 'updated_at' => $now,
            ]);
        } else {
            DB::table('templates')->where('id', $templateId)->update(['report_data_source_id' => $sourceId]);
        }
        DB::table('report_definitions')->updateOrInsert(['code' => self::REPORT], [
            'name' => 'Báo cáo Extra Bed', 'group' => 'Báo cáo phòng', 'description' => 'Thống kê số lượng và doanh thu Extra Bed theo từng ngày.',
            'report_data_source_id' => $sourceId, 'parameter_ui_schema' => json_encode($ui, JSON_UNESCAPED_UNICODE), 'sort_order' => 31,
            'is_active' => true, 'show_in_menu' => true, 'menu_locations' => json_encode(['reservation', 'frontdesk']), 'menu_top_order' => 20,
            'menu_group_order' => 10, 'menu_item_order' => 31, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $reportId = DB::table('report_definitions')->where('code', self::REPORT)->value('id');
        DB::table('report_definition_template')->updateOrInsert(['report_definition_id' => $reportId, 'template_id' => $templateId], ['is_default' => true, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now]);
        if ($templateCreated) {
            (require database_path('report_templates/extra_bed_reference.php'))->apply();
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') return;
        $reportId = DB::table('report_definitions')->where('code', self::REPORT)->value('id');
        $templateId = DB::table('templates')->where('report', self::TEMPLATE)->value('id');
        if ($reportId) { DB::table('report_definition_template')->where('report_definition_id', $reportId)->delete(); DB::table('report_definitions')->where('id', $reportId)->delete(); }
        if ($templateId) DB::table('templates')->where('id', $templateId)->delete();
        DB::table('report_data_sources')->where('code', self::SOURCE)->delete();
        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_extra_bed');
    }
};
