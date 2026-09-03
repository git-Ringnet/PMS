<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') return;

        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_cancelled_rooms');
        DB::unprepared(<<<'SQL'
CREATE PROCEDURE rpt_cancelled_rooms(
    IN p_from_date DATE, IN p_to_date DATE, IN p_view_type VARCHAR(20), IN p_booking_id BIGINT,
    IN p_division VARCHAR(20), IN p_group_by_reason TINYINT
)
READS SQL DATA
BEGIN
    SELECT ROW_NUMBER() OVER (ORDER BY
            CASE WHEN COALESCE(p_group_by_reason, 0) = 1 THEN COALESCE(NULLIF(l.note, ''), cr.name, br.reason, 'Không có lý do') END,
            l.cancelled_at, b.id, br.room_number, br.id
        ) AS STT,
        br.id AS RoomId,
        CONCAT(COALESCE(hs.prefix_booking_id, 'GAL'), b.id) AS BookingCode,
        b.id AS BookingId,
        b.booking_name AS BookingName,
        c.name AS Company,
        DATE_FORMAT(b.booking_date, '%d/%m/%Y') AS DateDangKy,
        rs.name AS BookingStatus,
        br.room_number AS Room,
        DATE_FORMAT(br.arrival_date, '%d/%m/%Y') AS ArrivalDate,
        DATE_FORMAT(br.departure_date, '%d/%m/%Y') AS DepartureDate,
        DATE_FORMAT(l.cancelled_at, '%d/%m/%Y') AS CancelDate,
        TIME_FORMAT(l.cancelled_at, '%H:%i') AS CancelTime,
        COALESCE(l.cancelled_by_username, '') AS UserName,
        COALESCE(NULLIF(l.note, ''), cr.name, br.reason, '') AS CancelReason,
        COALESCE(NULLIF(l.note, ''), cr.name, br.reason, 'Không có lý do') AS CancelReasonGroup,
        DATEDIFF(br.arrival_date, DATE(l.cancelled_at)) AS SoCancelDate,
        br.rate AS Rate,
        rc.code AS RoomType,
        hs.division AS Division
    FROM booking_cancel_logs l
    INNER JOIN booking_rooms br ON br.id = l.booking_room_id
    INNER JOIN bookings b ON b.id = COALESCE(l.booking_id, br.booking_id)
    LEFT JOIN room_classes rc ON rc.id = br.room_class_id
    LEFT JOIN companies c ON c.id = b.company_id
    LEFT JOIN registration_statuses rs ON rs.id = b.registration_status_id
    LEFT JOIN cancel_reasons cr ON cr.id = l.cancel_reason_id
    LEFT JOIN hotel_settings hs ON hs.id = (SELECT MIN(hs1.id) FROM hotel_settings hs1)
    WHERE l.cancel_type = 'room'
      AND (
          p_division IS NULL
          OR p_division = ''
          OR p_division = '__current__'
          OR CAST(p_division AS BINARY) = CAST(hs.division AS BINARY)
      )
      AND (p_booking_id IS NULL OR b.id = p_booking_id)
      AND (
        (COALESCE(p_view_type, 'CancelDate') = 'ArrivalDate'
            AND br.arrival_date >= p_from_date
            AND br.arrival_date < DATE_ADD(p_to_date, INTERVAL 1 DAY)
            AND br.status = 3)
        OR
        (COALESCE(p_view_type, 'CancelDate') <> 'ArrivalDate'
            AND l.cancelled_at >= p_from_date
            AND l.cancelled_at < DATE_ADD(p_to_date, INTERVAL 1 DAY))
      )
    ORDER BY
        CASE WHEN COALESCE(p_group_by_reason, 0) = 1 THEN COALESCE(NULLIF(l.note, ''), cr.name, br.reason, 'Không có lý do') END,
        l.cancelled_at, b.id, br.room_number, br.id;
END
SQL);
    }

    public function down(): void
    {
        // Không khôi phục procedure có mapping loại phòng và so sánh collation chưa chính xác.
    }
};
