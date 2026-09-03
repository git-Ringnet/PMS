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
                br.room_number, br.id
        ) AS STT,
        CASE WHEN billing.HasRoomNight = 1 THEN 'Charge' ELSE 'No Charge' END AS RoomType,
        br.room_number AS Room,
        CONCAT(COALESCE(hs.prefix_booking_id, 'GAL'), b.id) AS BookingId,
        b.booking_name AS BookingName,
        c.name AS Company,
        DATE_FORMAT(b.booking_date, '%d/%m/%Y') AS BookingDate,
        DATE_FORMAT(br.arrival_date, '%d/%m/%Y') AS ArrivalDate,
        br.ActutalNumOfDays AS NumOfDays,
        DATE_FORMAT(ns.noshow_date, '%d/%m/%Y') AS NoshowDate,
        ns.noshow_time AS NoshowTime,
        billing.Total AS Total,
        COALESCE(ns.username, '') AS Username,
        COALESCE(ns.shift, '') AS Ca,
        COALESCE(ns.reason, '') AS Reason,
        billing.BillingDate AS Date,
        br.id AS Ma,
        hs.division AS Division
    FROM noshow_logs AS ns
    INNER JOIN booking_rooms AS br ON br.id = ns.booking_room_id
    INNER JOIN bookings AS b ON b.id = br.booking_id
    LEFT JOIN companies AS c ON c.id = b.company_id
    LEFT JOIN hotel_settings AS hs ON hs.id = (SELECT MIN(id) FROM hotel_settings)
    INNER JOIN (
        SELECT
            COALESCE(sb.RentalRoomId1, sb.RentalRoomId2) AS RoomId,
            SUM(sb.Amount) AS Total,
            MAX(DATE(sb.Date)) AS BillingDate,
            MAX(CASE WHEN rnb.bill_id IS NOT NULL THEN 1 ELSE 0 END) AS HasRoomNight
        FROM service_bills AS sb
        LEFT JOIN room_night_bills AS rnb
          ON rnb.bill_id = sb.Ma
         AND rnb.is_room_night = 1
        WHERE sb.ServiceId = 'RM'
          AND sb.Edit = 0
          AND sb.Date >= p_from_date
          AND sb.Date < DATE_ADD(p_to_date, INTERVAL 1 DAY)
        GROUP BY COALESCE(sb.RentalRoomId1, sb.RentalRoomId2)
    ) AS billing ON billing.RoomId = br.id
    WHERE br.status = 4
      AND ns.noshow_date IS NOT NULL
      AND (p_user IS NULL OR p_user = '' OR br.created_by LIKE CONCAT('%', p_user, '%'))
      AND (p_division IS NULL OR p_division = '' OR p_division = '__current__' OR hs.division = p_division)
      AND (
          (ns.noshow_date >= p_from_date AND ns.noshow_date < DATE_ADD(p_to_date, INTERVAL 1 DAY))
          OR (billing.BillingDate >= p_from_date AND billing.BillingDate <= p_to_date)
      )
      AND (
          COALESCE(p_type, 2) = 2
          OR (COALESCE(p_type, 2) = 0 AND billing.HasRoomNight = 1)
          OR (COALESCE(p_type, 2) = 1 AND billing.HasRoomNight = 0)
      )
    ORDER BY
        CASE WHEN UPPER(COALESCE(p_sort_type, 'ASC')) = 'DESC' THEN ns.noshow_date END DESC,
        CASE WHEN UPPER(COALESCE(p_sort_type, 'ASC')) <> 'DESC' THEN ns.noshow_date END ASC,
        br.room_number, br.id;
END
SQL);

        DB::table('report_data_sources')->where('code', 'NO_SHOW')->update([
            'description' => 'MySQL chuyển đổi từ ProVista sp_054 và sp_054_Division; Charge đối chiếu SP3004.IsRoomNight.',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Không khôi phục procedure cũ vì phân loại Charge/No Charge không khớp legacy.
    }
};
