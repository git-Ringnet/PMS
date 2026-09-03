<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_no_show');
        DB::unprepared(<<<'SQL'
CREATE PROCEDURE rpt_no_show(
    IN p_from_date DATE,
    IN p_to_date DATE,
    IN p_type TINYINT,
    IN p_user VARCHAR(50),
    IN p_type_money VARCHAR(20),
    IN p_sort_type CHAR(4),
    IN p_division VARCHAR(20)
)
READS SQL DATA
BEGIN
    SELECT
        ROW_NUMBER() OVER (
            ORDER BY
                CASE WHEN UPPER(COALESCE(p_sort_type, 'ASC')) = 'DESC' THEN ns.noshow_date END DESC,
                CASE WHEN UPPER(COALESCE(p_sort_type, 'ASC')) <> 'DESC' THEN ns.noshow_date END ASC,
                br.room_number, br.id, COALESCE(billing.BillingDate, charged.ChargeDate, DATE(ns.noshow_date))
        ) AS STT,
        CASE WHEN charged.RoomId IS NOT NULL THEN 'Charge' ELSE 'No Charge' END AS RoomType,
        br.room_number AS Room,
        CONCAT(COALESCE(hs.prefix_booking_id, 'GAL'), b.id) AS BookingId,
        b.booking_name AS BookingName,
        c.name AS Company,
        DATE_FORMAT(b.booking_date, '%d/%m/%Y') AS BookingDate,
        DATE_FORMAT(br.arrival_date, '%d/%m/%Y') AS ArrivalDate,
        br.ActutalNumOfDays AS NumOfDays,
        DATE_FORMAT(ns.noshow_date, '%d/%m/%Y') AS NoshowDate,
        ns.noshow_time AS NoshowTime,
        COALESCE(billing.Total, charged.Total, br.rate, 0) AS Total,
        COALESCE(ns.username, '') AS Username,
        COALESCE(ns.shift, '') AS Ca,
        COALESCE(ns.reason, '') AS Reason,
        COALESCE(billing.BillingDate, charged.ChargeDate, DATE(ns.noshow_date)) AS Date,
        br.id AS Ma,
        hs.division AS Division
    FROM noshow_logs AS ns
    INNER JOIN booking_rooms AS br ON br.id = ns.booking_room_id
    INNER JOIN bookings AS b ON b.id = br.booking_id
    LEFT JOIN companies AS c ON c.id = b.company_id
    LEFT JOIN hotel_settings AS hs ON hs.id = (SELECT MIN(id) FROM hotel_settings)
    LEFT JOIN (
        SELECT
            brs.booking_room_id AS RoomId,
            brs.service_date AS BillingDate,
            SUM(brs.total_amount) AS Total
        FROM booking_room_services AS brs
        WHERE brs.service_code = 'RM'
          AND brs.deleted_at IS NULL
          AND brs.service_date >= p_from_date
          AND brs.service_date < DATE_ADD(p_to_date, INTERVAL 1 DAY)
        GROUP BY brs.booking_room_id, brs.service_date
    ) AS billing ON billing.RoomId = br.id
    LEFT JOIN (
        SELECT
            sb.RentalRoomId1 AS RoomId,
            DATE(sb.Date) AS ChargeDate,
            SUM(sb.Amount) AS Total
        FROM service_bills AS sb
        INNER JOIN room_night_bills AS rnb
          ON rnb.bill_id = sb.Ma
         AND rnb.is_room_night = 1
        WHERE sb.ServiceId = 'RM'
          AND sb.Edit = 0
          AND sb.Date >= p_from_date
          AND sb.Date < DATE_ADD(p_to_date, INTERVAL 1 DAY)
        GROUP BY sb.RentalRoomId1, DATE(sb.Date)
    ) AS charged
      ON charged.RoomId = br.id
     AND (billing.BillingDate IS NULL OR charged.ChargeDate = billing.BillingDate)
    WHERE br.status = 4
      AND ns.noshow_date IS NOT NULL
      AND (p_user IS NULL OR p_user = '' OR br.created_by LIKE CONCAT('%', p_user, '%'))
      AND (p_division IS NULL OR p_division = '' OR p_division = '__current__' OR hs.division = p_division)
      AND (
          (ns.noshow_date >= p_from_date AND ns.noshow_date < DATE_ADD(p_to_date, INTERVAL 1 DAY))
          OR (billing.BillingDate >= p_from_date AND billing.BillingDate <= p_to_date)
          OR (charged.ChargeDate >= p_from_date AND charged.ChargeDate <= p_to_date)
      )
      AND (
          COALESCE(p_type, 2) = 2
          OR (COALESCE(p_type, 2) = 0 AND charged.RoomId IS NOT NULL)
          OR (COALESCE(p_type, 2) = 1 AND charged.RoomId IS NULL)
      )
    ORDER BY
        CASE WHEN UPPER(COALESCE(p_sort_type, 'ASC')) = 'DESC' THEN ns.noshow_date END DESC,
        CASE WHEN UPPER(COALESCE(p_sort_type, 'ASC')) <> 'DESC' THEN ns.noshow_date END ASC,
        br.room_number, br.id, COALESCE(billing.BillingDate, charged.ChargeDate, DATE(ns.noshow_date));
END
SQL);

        DB::table('report_data_sources')->where('code', 'NO_SHOW')->update([
            'description' => 'MySQL chuyển đổi từ ProVista sp_054 và sp_054_Division; dữ liệu tính tiền tách khỏi bill Charge.',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Không khôi phục procedure cũ vì loại mất No Charge chưa có bill RM.
    }
};
