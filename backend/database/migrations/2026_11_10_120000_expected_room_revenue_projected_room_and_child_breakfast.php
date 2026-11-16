<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Task 516 (TC_LT_20 dòng 280) - Báo cáo dự kiến doanh thu tiền phòng (Sang ngày):
 * - Thêm tiền phòng dự kiến của các phòng chưa post và không có dòng giá RM theo đêm (lấy booking_rooms.rate).
 * - Thêm phụ thu ăn sáng trẻ em có phụ phí lưu theo chi tiết từng đêm (booking_child_breakfast_details).
 * - Mẫu in mặc định: A4 khổ dọc, chữ lớn hơn.
 */
return new class extends Migration
{
    private const TEMPLATE = 'EXPECTED_ROOM_REVENUE_REFERENCE';

    public function up(): void
    {
        $db = DB::connection();
        if ($db->getDriverName() !== 'mysql') {
            return;
        }

        $db->unprepared('DROP PROCEDURE IF EXISTS rpt_expected_room_revenue_night_audit');
        $db->unprepared($this->procedureSql());

        $template = (require database_path('report_templates/expected_room_revenue_reference.php'))->definition();
        $db->table('templates')->where('report', self::TEMPLATE)->update([
            'page_size' => $template['page_size'],
            'page_orientation' => $template['page_orientation'],
            'margin_top' => $template['margin_top'], 'margin_right' => $template['margin_right'],
            'margin_bottom' => $template['margin_bottom'], 'margin_left' => $template['margin_left'],
            'css' => $template['css'],
            'updated_at' => now(),
        ]);
    }

    public function procedureSql(): string
    {
        return <<<'SQL'
CREATE PROCEDURE rpt_expected_room_revenue_night_audit(IN p_date DATE)
READS SQL DATA
BEGIN
    DECLARE v_prefix VARCHAR(50) DEFAULT '';
    DECLARE v_child_code VARCHAR(50) DEFAULT 'BD';
    SELECT COALESCE(prefix_booking_id, '') INTO v_prefix FROM hotel_settings ORDER BY id LIMIT 1;
    SELECT COALESCE(NULLIF(NULLIF(TRIM(value), ''), '0'), 'BD') INTO v_child_code
      FROM hotel_configs WHERE name = 'Booking_BFChildSetServiceId' LIMIT 1;

    WITH eligible_rooms AS (
        SELECT
            br.id AS BookingRoomId,
            br.booking_id AS BookingId,
            br.room_number AS Room,
            br.arrival_date AS ArrivalDate,
            br.departure_date AS DepartureDate,
            br.note AS RoomNote,
            br.rate AS RoomRate,
            b.booking_name AS BookingName,
            b.company_id AS CompanyId,
            b.note AS BookingNote
        FROM booking_rooms br
        INNER JOIN bookings b ON b.id = br.booking_id
        WHERE br.status IN (0, 1)
          AND br.deleted_at IS NULL
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
              -- Task 516: bill RM đã post nhưng không có room_night_bills thì lấy theo ngày bill
              OR (sbd.ServiceId = 'RM' AND DATE(sb.Date) = p_date
                  AND NOT EXISTS (SELECT 1 FROM room_night_bills rnx WHERE rnx.bill_id = sb.Ma))
              OR (sbd.ServiceId <> 'RM' AND DATE(sb.Date) = p_date)
          )

        UNION ALL

        -- Task 516: bill đã post nhưng không có dòng chi tiết thì lấy theo dòng bill.
        SELECT
            sb.Ma AS SourceId,
            0 AS DetailNo,
            COALESCE(NULLIF(sb.RentalRoomId2, ''), sb.RentalRoomId1) AS BookingRoomId,
            sb.ServiceId,
            COALESCE(sb.DescriptionServive, '') AS ServiceName,
            COALESCE(sb.Amount, 0) AS RateTotal,
            COALESCE(sb.Guest, '') AS GuestSnapshot
        FROM service_bills sb
        WHERE COALESCE(sb.Edit, 0) = 0
          AND sb.ServiceId IS NOT NULL
          AND DATE(sb.Date) = p_date
          AND NOT EXISTS (SELECT 1 FROM service_bill_details sbx WHERE sbx.BillServiceId = sb.Ma)
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

        UNION ALL

        -- Task 516: tiền phòng dự kiến của phòng chưa post và không có dòng giá RM theo đêm.
        SELECT
            0 AS SourceId,
            0 AS DetailNo,
            er.BookingRoomId,
            'RM' AS ServiceId,
            'Tiền phòng' AS ServiceName,
            COALESCE(er.RoomRate, 0) AS RateTotal,
            '' AS GuestSnapshot
        FROM eligible_rooms er
        WHERE COALESCE(er.RoomRate, 0) > 0
          AND NOT EXISTS (
              SELECT 1 FROM posted_source ps
              WHERE ps.BookingRoomId = er.BookingRoomId AND ps.ServiceId = 'RM'
          )
          AND NOT EXISTS (
              SELECT 1 FROM booking_room_services rm
              WHERE rm.booking_room_id = er.BookingRoomId
                AND rm.service_code = 'RM'
                AND rm.deleted_at IS NULL
                AND DATE(rm.service_date) = p_date
          )

        UNION ALL

        -- Task 516: phụ thu ăn sáng trẻ em có phụ phí lưu theo chi tiết từng đêm (chưa post).
        SELECT
            bcbd.id AS SourceId,
            0 AS DetailNo,
            bc.booking_room_id AS BookingRoomId,
            v_child_code AS ServiceId,
            'Phụ thu ăn sáng trẻ em' AS ServiceName,
            COALESCE(bcbd.amount, 0) AS RateTotal,
            '' AS GuestSnapshot
        FROM booking_child_breakfast_details bcbd
        INNER JOIN booking_children bc ON bc.id = bcbd.booking_child_id
        WHERE DATE(bcbd.service_date) = p_date
          AND COALESCE(bcbd.breakfast, 0) = 1
          AND COALESCE(bcbd.is_extra_charge, 0) = 1
          AND COALESCE(bcbd.is_free, 0) = 0
          AND COALESCE(bcbd.amount, 0) > 0
          AND COALESCE(bc.child_status, 0) <> 3
          AND COALESCE(bc.age_group, '') <> 'baby'
          AND NOT EXISTS (
              SELECT 1 FROM service_bills csb
              WHERE csb.RentalRoomId1 = bc.booking_room_id
                AND csb.ServiceId = v_child_code
                AND csb.CustomerId1 = bc.id
                AND COALESCE(csb.Edit, 0) = 0
                AND DATE(csb.Date) = p_date
          )
          AND NOT EXISTS (
              SELECT 1 FROM booking_room_services cbrs
              WHERE cbrs.booking_room_id = bc.booking_room_id
                AND cbrs.service_code = v_child_code
                AND cbrs.deleted_at IS NULL
                AND DATE(cbrs.service_date) = p_date
          )
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

    public function down(): void
    {
        $db = DB::connection();
        if ($db->getDriverName() !== 'mysql') {
            return;
        }
        $legacy = require database_path('migrations/2026_09_21_170000_create_expected_room_revenue_night_audit_report.php');
        $db->unprepared('DROP PROCEDURE IF EXISTS rpt_expected_room_revenue_night_audit');
        $db->unprepared($legacy->procedureSql());
    }
};
