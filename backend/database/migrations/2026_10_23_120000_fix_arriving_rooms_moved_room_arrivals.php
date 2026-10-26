<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Báo cáo phòng đến: tính theo ngày đến thật của chuỗi chuyển phòng.
 * Dòng phòng tạo khi chuyển phòng có arrival_date = ngày chuyển, actual_arrival_date = ngày đến gốc,
 * nên ngày đến thật = LEAST(arrival_date, actual_arrival_date). Phòng chuyển trong cùng ngày đến
 * vẫn được tính; phòng chuyển của khách đã đến từ ngày trước không còn tính là phòng đến.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_arriving_rooms');
        DB::unprepared(<<<'SQL'
CREATE PROCEDURE rpt_arriving_rooms(
    IN p_from_date DATE,
    IN p_to_date DATE,
    IN p_room_class_id BIGINT,
    IN p_registration_status_id BIGINT,
    IN p_area VARCHAR(100),
    IN p_company_id BIGINT,
    IN p_booking_id BIGINT,
    IN p_show_main_guest TINYINT,
    IN p_show_room_rate TINYINT
)
READS SQL DATA
BEGIN
    SELECT result.*
    FROM (
        SELECT
            DATE_FORMAT(LEAST(DATE(br.arrival_date), DATE(COALESCE(br.actual_arrival_date, br.arrival_date))), '%d-%m-%Y') AS ArrivalDateGroup,
            CONCAT(b.id, ' - ', b.booking_name) AS Booking,
            b.id AS BookingId,
            b.id AS RegisterId,
            br.id AS RentalRoomId,
            CASE WHEN brg.is_primary = 1 THEN COALESCE(br.room_number, '') ELSE NULL END AS Room,
            CASE WHEN brg.is_primary = 1 THEN rc.code ELSE NULL END AS RoomTypeCode,
            CASE WHEN brg.is_primary = 1 THEN rc.name ELSE NULL END AS RoomType,
            CASE WHEN brg.is_primary = 1 THEN orc.name ELSE NULL END AS RoomTypeOriginal,
            TRIM(CONCAT_WS(' ', NULLIF(g.title, ''), g.full_name)) AS GuestName,
            CONCAT(DATE_FORMAT(LEAST(DATE(br.arrival_date), DATE(COALESCE(br.actual_arrival_date, br.arrival_date))), '%d/%m/%Y'),
                IF(br.arrival_time IS NULL, '', CONCAT(' - ', TIME_FORMAT(br.arrival_time, '%H:%i')))) AS ArrivalDate,
            CONCAT(DATE_FORMAT(br.departure_date, '%d/%m/%Y'),
                IF(br.departure_time IS NULL, '', CONCAT(' - ', TIME_FORMAT(br.departure_time, '%H:%i')))) AS DepartureDate,
            CASE WHEN brg.is_primary = 1 THEN br.extra_bed_qty ELSE NULL END AS ExtraBed,
            CASE WHEN brg.is_primary = 1 THEN br.ActutalNumOfDays ELSE NULL END AS RoomNight,
            CASE WHEN brg.is_primary = 1 THEN br.adults ELSE NULL END AS Adult,
            CASE WHEN brg.is_primary = 1 THEN br.children_qty ELSE NULL END AS Child,
            CASE WHEN brg.is_primary = 1 THEN CONCAT(br.adults, ' / ', br.children_qty) ELSE NULL END AS AdultChild,
            CASE WHEN brg.is_primary = 1 AND COALESCE(p_show_room_rate, 1) = 1 THEN br.rate ELSE NULL END AS Rate,
            CASE WHEN brg.is_primary = 1 THEN (
                SELECT GROUP_CONCAT(sr.code ORDER BY sr.sort_order, sr.code SEPARATOR ', ')
                FROM booking_room_special_requests AS brsr
                INNER JOIN special_requests AS sr ON sr.id = brsr.special_request_id
                WHERE brsr.booking_room_id = br.id
            ) ELSE NULL END AS Special,
            b.company_id AS CompanyId,
            c.name AS Company,
            COALESCE(br.note, b.note) AS Note,
            brg.is_primary AS IsMainGuest,
            g.guest_type AS GuestType,
            b.booking_name AS BookingName,
            COALESCE(brg.breakfast, br.breakfast) AS Breakfast,
            g.phone AS Phone,
            CASE g.gender WHEN 0 THEN 'Nam' WHEN 1 THEN 'Nữ' ELSE 'Khác' END AS Gender,
            r.orders AS RoomOrder,
            COALESCE(rs.vietnamese, rs.name) AS BookingStatusName,
            b.special_requests AS SpecialDangKy
        FROM booking_rooms AS br
        INNER JOIN bookings AS b ON b.id = br.booking_id AND b.deleted_at IS NULL
        INNER JOIN booking_room_guests AS brg ON brg.booking_room_id = br.id AND brg.status IN (0, 1, 2)
        INNER JOIN guests AS g ON g.id = brg.guest_id
        INNER JOIN room_classes AS rc ON rc.id = br.room_class_id
        LEFT JOIN room_classes AS orc
            ON orc.id = CAST(SUBSTRING_INDEX(br.original_room_class_id, '-', 1) AS UNSIGNED)
        LEFT JOIN rooms AS r ON r.room_number = br.room_number
        LEFT JOIN companies AS c ON c.id = b.company_id
        LEFT JOIN registration_statuses AS rs ON rs.id = b.registration_status_id
        WHERE br.deleted_at IS NULL
          AND br.status IN (0, 1, 2)
          AND b.status IN (0, 1, 2)
          AND (rs.id IS NULL OR rs.is_availability = 1)
          AND LEAST(DATE(br.arrival_date), DATE(COALESCE(br.actual_arrival_date, br.arrival_date))) BETWEEN p_from_date AND p_to_date
          AND (COALESCE(p_show_main_guest, 1) = 0 OR brg.is_primary = 1)
          AND (p_area IS NULL OR p_area = '' OR r.area = p_area)
          AND (p_company_id IS NULL OR b.company_id = p_company_id)
          AND (p_booking_id IS NULL OR b.id = p_booking_id)
          AND (p_room_class_id IS NULL OR br.room_class_id = p_room_class_id)
          AND (p_registration_status_id IS NULL OR b.registration_status_id = p_registration_status_id)

        UNION ALL

        SELECT
            DATE_FORMAT(LEAST(DATE(br.arrival_date), DATE(COALESCE(br.actual_arrival_date, br.arrival_date))), '%d-%m-%Y') AS ArrivalDateGroup,
            CONCAT(b.id, ' - ', b.booking_name) AS Booking,
            b.id AS BookingId,
            b.id AS RegisterId,
            br.id AS RentalRoomId,
            NULL AS Room,
            NULL AS RoomTypeCode,
            NULL AS RoomType,
            NULL AS RoomTypeOriginal,
            TRIM(CONCAT_WS(' ', NULLIF(bc.title, ''), bc.full_name)) AS GuestName,
            CONCAT(DATE_FORMAT(LEAST(DATE(br.arrival_date), DATE(COALESCE(br.actual_arrival_date, br.arrival_date))), '%d/%m/%Y'),
                IF(br.arrival_time IS NULL, '', CONCAT(' - ', TIME_FORMAT(br.arrival_time, '%H:%i')))) AS ArrivalDate,
            CONCAT(DATE_FORMAT(br.departure_date, '%d/%m/%Y'),
                IF(br.departure_time IS NULL, '', CONCAT(' - ', TIME_FORMAT(br.departure_time, '%H:%i')))) AS DepartureDate,
            NULL AS ExtraBed,
            NULL AS RoomNight,
            NULL AS Adult,
            NULL AS Child,
            NULL AS AdultChild,
            NULL AS Rate,
            NULL AS Special,
            b.company_id AS CompanyId,
            c.name AS Company,
            COALESCE(br.note, b.note) AS Note,
            -1 AS IsMainGuest,
            bc.age_group AS GuestType,
            b.booking_name AS BookingName,
            br.breakfast AS Breakfast,
            bc.phone AS Phone,
            CASE bc.gender WHEN 0 THEN 'Nam' WHEN 1 THEN 'Nữ' ELSE 'Khác' END AS Gender,
            r.orders AS RoomOrder,
            NULL AS BookingStatusName,
            b.special_requests AS SpecialDangKy
        FROM booking_rooms AS br
        INNER JOIN bookings AS b ON b.id = br.booking_id AND b.deleted_at IS NULL
        INNER JOIN booking_children AS bc ON bc.booking_room_id = br.id AND bc.child_status IN (0, 1, 2)
        LEFT JOIN rooms AS r ON r.room_number = br.room_number
        LEFT JOIN companies AS c ON c.id = b.company_id
        LEFT JOIN registration_statuses AS rs ON rs.id = b.registration_status_id
        WHERE COALESCE(p_show_main_guest, 1) = 0
          AND br.deleted_at IS NULL
          AND br.status IN (0, 1, 2)
          AND b.status IN (0, 1, 2)
          AND (rs.id IS NULL OR rs.is_availability = 1)
          AND LEAST(DATE(br.arrival_date), DATE(COALESCE(br.actual_arrival_date, br.arrival_date))) BETWEEN p_from_date AND p_to_date
          AND (p_area IS NULL OR p_area = '' OR r.area = p_area)
          AND (p_company_id IS NULL OR b.company_id = p_company_id)
          AND (p_booking_id IS NULL OR b.id = p_booking_id)
          AND (p_room_class_id IS NULL OR br.room_class_id = p_room_class_id)
          AND (p_registration_status_id IS NULL OR b.registration_status_id = p_registration_status_id)
    ) AS result
    ORDER BY result.RoomOrder, result.RentalRoomId, result.IsMainGuest DESC, result.GuestName;
END
SQL);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_arriving_rooms');
        DB::unprepared(<<<'SQL'
CREATE PROCEDURE rpt_arriving_rooms(
    IN p_from_date DATE,
    IN p_to_date DATE,
    IN p_room_class_id BIGINT,
    IN p_registration_status_id BIGINT,
    IN p_area VARCHAR(100),
    IN p_company_id BIGINT,
    IN p_booking_id BIGINT,
    IN p_show_main_guest TINYINT,
    IN p_show_room_rate TINYINT
)
READS SQL DATA
BEGIN
    SELECT result.*
    FROM (
        SELECT
            DATE_FORMAT(br.arrival_date, '%d-%m-%Y') AS ArrivalDateGroup,
            CONCAT(b.id, ' - ', b.booking_name) AS Booking,
            b.id AS BookingId,
            b.id AS RegisterId,
            br.id AS RentalRoomId,
            CASE WHEN brg.is_primary = 1 THEN COALESCE(br.room_number, '') ELSE NULL END AS Room,
            CASE WHEN brg.is_primary = 1 THEN rc.code ELSE NULL END AS RoomTypeCode,
            CASE WHEN brg.is_primary = 1 THEN rc.name ELSE NULL END AS RoomType,
            CASE WHEN brg.is_primary = 1 THEN orc.name ELSE NULL END AS RoomTypeOriginal,
            TRIM(CONCAT_WS(' ', NULLIF(g.title, ''), g.full_name)) AS GuestName,
            CONCAT(DATE_FORMAT(br.arrival_date, '%d/%m/%Y'),
                IF(br.arrival_time IS NULL, '', CONCAT(' - ', TIME_FORMAT(br.arrival_time, '%H:%i')))) AS ArrivalDate,
            CONCAT(DATE_FORMAT(br.departure_date, '%d/%m/%Y'),
                IF(br.departure_time IS NULL, '', CONCAT(' - ', TIME_FORMAT(br.departure_time, '%H:%i')))) AS DepartureDate,
            CASE WHEN brg.is_primary = 1 THEN br.extra_bed_qty ELSE NULL END AS ExtraBed,
            CASE WHEN brg.is_primary = 1 THEN br.ActutalNumOfDays ELSE NULL END AS RoomNight,
            CASE WHEN brg.is_primary = 1 THEN br.adults ELSE NULL END AS Adult,
            CASE WHEN brg.is_primary = 1 THEN br.children_qty ELSE NULL END AS Child,
            CASE WHEN brg.is_primary = 1 THEN CONCAT(br.adults, ' / ', br.children_qty) ELSE NULL END AS AdultChild,
            CASE WHEN brg.is_primary = 1 AND COALESCE(p_show_room_rate, 1) = 1 THEN br.rate ELSE NULL END AS Rate,
            CASE WHEN brg.is_primary = 1 THEN (
                SELECT GROUP_CONCAT(sr.code ORDER BY sr.sort_order, sr.code SEPARATOR ', ')
                FROM booking_room_special_requests AS brsr
                INNER JOIN special_requests AS sr ON sr.id = brsr.special_request_id
                WHERE brsr.booking_room_id = br.id
            ) ELSE NULL END AS Special,
            b.company_id AS CompanyId,
            c.name AS Company,
            COALESCE(br.note, b.note) AS Note,
            brg.is_primary AS IsMainGuest,
            g.guest_type AS GuestType,
            b.booking_name AS BookingName,
            COALESCE(brg.breakfast, br.breakfast) AS Breakfast,
            g.phone AS Phone,
            CASE g.gender WHEN 0 THEN 'Nam' WHEN 1 THEN 'Nữ' ELSE 'Khác' END AS Gender,
            r.orders AS RoomOrder,
            COALESCE(rs.vietnamese, rs.name) AS BookingStatusName,
            b.special_requests AS SpecialDangKy
        FROM booking_rooms AS br
        INNER JOIN bookings AS b ON b.id = br.booking_id AND b.deleted_at IS NULL
        INNER JOIN booking_room_guests AS brg ON brg.booking_room_id = br.id AND brg.status IN (0, 1, 2)
        INNER JOIN guests AS g ON g.id = brg.guest_id
        INNER JOIN room_classes AS rc ON rc.id = br.room_class_id
        LEFT JOIN room_classes AS orc
            ON orc.id = CAST(SUBSTRING_INDEX(br.original_room_class_id, '-', 1) AS UNSIGNED)
        LEFT JOIN rooms AS r ON r.room_number = br.room_number
        LEFT JOIN companies AS c ON c.id = b.company_id
        LEFT JOIN registration_statuses AS rs ON rs.id = b.registration_status_id
        WHERE br.deleted_at IS NULL
          AND br.status IN (0, 1, 2)
          AND b.status IN (0, 1, 2)
          AND (rs.id IS NULL OR rs.is_availability = 1)
          AND br.arrival_date BETWEEN p_from_date AND p_to_date
          AND (COALESCE(p_show_main_guest, 1) = 0 OR brg.is_primary = 1)
          AND (p_area IS NULL OR p_area = '' OR r.area = p_area)
          AND (p_company_id IS NULL OR b.company_id = p_company_id)
          AND (p_booking_id IS NULL OR b.id = p_booking_id)
          AND (p_room_class_id IS NULL OR br.room_class_id = p_room_class_id)
          AND (p_registration_status_id IS NULL OR b.registration_status_id = p_registration_status_id)
          AND NOT EXISTS (
              SELECT 1
              FROM booking_rooms AS moved_from
              WHERE moved_from.move_room = br.id AND moved_from.deleted_at IS NULL
          )

        UNION ALL

        SELECT
            DATE_FORMAT(br.arrival_date, '%d-%m-%Y') AS ArrivalDateGroup,
            CONCAT(b.id, ' - ', b.booking_name) AS Booking,
            b.id AS BookingId,
            b.id AS RegisterId,
            br.id AS RentalRoomId,
            NULL AS Room,
            NULL AS RoomTypeCode,
            NULL AS RoomType,
            NULL AS RoomTypeOriginal,
            TRIM(CONCAT_WS(' ', NULLIF(bc.title, ''), bc.full_name)) AS GuestName,
            CONCAT(DATE_FORMAT(br.arrival_date, '%d/%m/%Y'),
                IF(br.arrival_time IS NULL, '', CONCAT(' - ', TIME_FORMAT(br.arrival_time, '%H:%i')))) AS ArrivalDate,
            CONCAT(DATE_FORMAT(br.departure_date, '%d/%m/%Y'),
                IF(br.departure_time IS NULL, '', CONCAT(' - ', TIME_FORMAT(br.departure_time, '%H:%i')))) AS DepartureDate,
            NULL AS ExtraBed,
            NULL AS RoomNight,
            NULL AS Adult,
            NULL AS Child,
            NULL AS AdultChild,
            NULL AS Rate,
            NULL AS Special,
            b.company_id AS CompanyId,
            c.name AS Company,
            COALESCE(br.note, b.note) AS Note,
            -1 AS IsMainGuest,
            bc.age_group AS GuestType,
            b.booking_name AS BookingName,
            br.breakfast AS Breakfast,
            bc.phone AS Phone,
            CASE bc.gender WHEN 0 THEN 'Nam' WHEN 1 THEN 'Nữ' ELSE 'Khác' END AS Gender,
            r.orders AS RoomOrder,
            NULL AS BookingStatusName,
            b.special_requests AS SpecialDangKy
        FROM booking_rooms AS br
        INNER JOIN bookings AS b ON b.id = br.booking_id AND b.deleted_at IS NULL
        INNER JOIN booking_children AS bc ON bc.booking_room_id = br.id AND bc.child_status IN (0, 1, 2)
        LEFT JOIN rooms AS r ON r.room_number = br.room_number
        LEFT JOIN companies AS c ON c.id = b.company_id
        LEFT JOIN registration_statuses AS rs ON rs.id = b.registration_status_id
        WHERE COALESCE(p_show_main_guest, 1) = 0
          AND br.deleted_at IS NULL
          AND br.status IN (0, 1, 2)
          AND b.status IN (0, 1, 2)
          AND (rs.id IS NULL OR rs.is_availability = 1)
          AND br.arrival_date BETWEEN p_from_date AND p_to_date
          AND (p_area IS NULL OR p_area = '' OR r.area = p_area)
          AND (p_company_id IS NULL OR b.company_id = p_company_id)
          AND (p_booking_id IS NULL OR b.id = p_booking_id)
          AND (p_room_class_id IS NULL OR br.room_class_id = p_room_class_id)
          AND (p_registration_status_id IS NULL OR b.registration_status_id = p_registration_status_id)
          AND NOT EXISTS (
              SELECT 1
              FROM booking_rooms AS moved_from
              WHERE moved_from.move_room = br.id AND moved_from.deleted_at IS NULL
          )
    ) AS result
    ORDER BY result.RoomOrder, result.RentalRoomId, result.IsMainGuest DESC, result.GuestName;
END
SQL);
    }
};
