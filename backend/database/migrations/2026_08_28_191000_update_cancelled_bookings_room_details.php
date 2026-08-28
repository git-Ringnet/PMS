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

        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_cancelled_bookings');
        DB::unprepared(<<<'SQL'
CREATE PROCEDURE rpt_cancelled_bookings(
    IN p_from_date DATE,
    IN p_to_date DATE,
    IN p_show_room_info TINYINT,
    IN p_view_type VARCHAR(20),
    IN p_booking_id BIGINT
)
READS SQL DATA
BEGIN
    IF COALESCE(p_show_room_info, 0) = 1 THEN
        SELECT
            'room' AS RowType,
            1 AS PeriodGroup,
            l.id AS CancellationId,
            CONCAT(
                b.id, '|', DATE_FORMAT(l.cancelled_at, '%Y%m%d%H%i%s'), '|',
                COALESCE(l.cancelled_by_username, ''), '|',
                COALESCE(NULLIF(l.note, ''), cr.name, br.reason, '')
            ) AS CancellationGroup,
            b.id AS BookingId,
            CONCAT(COALESCE(hs.prefix_booking_id, 'GAL'), b.id) AS BookingCode,
            b.booking_name AS BookingName,
            c.name AS Company,
            DATE_FORMAT(b.booking_date, '%d/%m/%Y') AS BookingDate,
            DATE_FORMAT(b.arrival_date, '%d/%m/%Y') AS BookingArrivalDate,
            DATE_FORMAT(b.departure_date, '%d/%m/%Y') AS BookingDepartureDate,
            DATE_FORMAT(l.cancelled_at, '%d/%m/%Y') AS CancelDate,
            TIME_FORMAT(l.cancelled_at, '%H:%i') AS CancelTime,
            l.cancelled_by_username AS CancelledBy,
            COALESCE(NULLIF(l.note, ''), cr.name, br.reason, '') AS CancelReason,
            DATEDIFF(br.arrival_date, DATE(l.cancelled_at)) AS DaysCancelBefore,
            rs.name AS BookingStatus,
            hs.division AS Division,
            br.id AS RoomId,
            br.room_number AS Room,
            rc.code AS RoomType,
            DATE_FORMAT(br.arrival_date, '%d/%m/%Y') AS RoomArrivalDate,
            DATE_FORMAT(br.departure_date, '%d/%m/%Y') AS RoomDepartureDate,
            br.rate AS Rate,
            br.adults AS Adult,
            br.babies AS Baby,
            br.children_qty AS Child,
            1 AS RoomCount,
            COALESCE(r.is_internal, 0) AS IsInternal
        FROM booking_cancel_logs AS l
        INNER JOIN bookings AS b ON b.id = l.booking_id
        INNER JOIN booking_rooms AS br ON br.booking_id = b.id
        LEFT JOIN rooms AS r ON r.room_number = br.room_number
        LEFT JOIN room_classes AS rc ON rc.id = br.room_class_id
        LEFT JOIN companies AS c ON c.id = b.company_id
        LEFT JOIN registration_statuses AS rs ON rs.id = b.registration_status_id
        LEFT JOIN cancel_reasons AS cr ON cr.id = l.cancel_reason_id
        LEFT JOIN hotel_settings AS hs ON hs.id = (SELECT MIN(id) FROM hotel_settings)
        WHERE l.cancel_type = 'booking'
          AND COALESCE(r.is_internal, 0) = 0
          AND (p_booking_id IS NULL OR b.id = p_booking_id)
          AND (
              (COALESCE(p_view_type, 'CancelDate') = 'ArrivalDate'
                  AND br.arrival_date BETWEEN p_from_date AND p_to_date
                  AND br.status = 3)
              OR
              (COALESCE(p_view_type, 'CancelDate') <> 'ArrivalDate'
                  AND DATE(l.cancelled_at) BETWEEN p_from_date AND p_to_date)
          )
        ORDER BY l.cancelled_at, b.id, br.room_number, br.id;
    ELSE
        SELECT
            'booking' AS RowType,
            1 AS PeriodGroup,
            l.id AS CancellationId,
            CAST(l.id AS CHAR) AS CancellationGroup,
            b.id AS BookingId,
            CONCAT(COALESCE(hs.prefix_booking_id, 'GAL'), b.id) AS BookingCode,
            b.booking_name AS BookingName,
            c.name AS Company,
            DATE_FORMAT(b.booking_date, '%d/%m/%Y') AS BookingDate,
            DATE_FORMAT(b.arrival_date, '%d/%m/%Y') AS BookingArrivalDate,
            DATE_FORMAT(b.departure_date, '%d/%m/%Y') AS BookingDepartureDate,
            DATE_FORMAT(l.cancelled_at, '%d/%m/%Y') AS CancelDate,
            TIME_FORMAT(l.cancelled_at, '%H:%i') AS CancelTime,
            l.cancelled_by_username AS CancelledBy,
            COALESCE(NULLIF(l.note, ''), cr.name, '') AS CancelReason,
            DATEDIFF(b.arrival_date, DATE(l.cancelled_at)) AS DaysCancelBefore,
            rs.name AS BookingStatus,
            hs.division AS Division,
            NULL AS RoomId,
            NULL AS Room,
            NULL AS RoomType,
            NULL AS RoomArrivalDate,
            NULL AS RoomDepartureDate,
            NULL AS Rate,
            NULL AS Adult,
            NULL AS Baby,
            NULL AS Child,
            COALESCE(room_counts.RoomCount, 0) AS RoomCount,
            0 AS IsInternal
        FROM booking_cancel_logs AS l
        INNER JOIN bookings AS b ON b.id = l.booking_id
        LEFT JOIN companies AS c ON c.id = b.company_id
        LEFT JOIN registration_statuses AS rs ON rs.id = b.registration_status_id
        LEFT JOIN cancel_reasons AS cr ON cr.id = l.cancel_reason_id
        LEFT JOIN hotel_settings AS hs ON hs.id = (SELECT MIN(id) FROM hotel_settings)
        LEFT JOIN (
            SELECT
                counted_room.booking_id,
                COUNT(DISTINCT counted_room.id) AS RoomCount
            FROM booking_rooms AS counted_room
            LEFT JOIN rooms AS physical_room ON physical_room.room_number = counted_room.room_number
            WHERE COALESCE(physical_room.is_internal, 0) = 0
            GROUP BY counted_room.booking_id
        ) AS room_counts ON room_counts.booking_id = b.id
        WHERE l.cancel_type = 'booking'
          AND DATE(l.cancelled_at) BETWEEN p_from_date AND p_to_date
          AND (p_booking_id IS NULL OR b.id = p_booking_id)
          AND (
              NOT EXISTS (
                  SELECT 1 FROM booking_rooms AS any_room
                  WHERE any_room.booking_id = b.id
              )
              OR EXISTS (
                  SELECT 1
                  FROM booking_rooms AS eligible_room
                  LEFT JOIN rooms AS eligible_physical_room
                    ON eligible_physical_room.room_number = eligible_room.room_number
                  WHERE eligible_room.booking_id = b.id
                    AND COALESCE(eligible_physical_room.is_internal, 0) = 0
              )
          )
        ORDER BY l.cancelled_at, b.id;
    END IF;
END
SQL);
    }

    public function down(): void
    {
        // Business correction: restoring room-log-only behavior is intentionally unsupported.
    }
};
