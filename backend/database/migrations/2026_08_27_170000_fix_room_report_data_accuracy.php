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

        $this->replaceInhouseRoomsProcedure();
        $this->replaceComplimentaryRoomsProcedure();
    }

    public function down(): void
    {
        // Data-accuracy correction. Restoring the faulty procedures is intentionally unsupported.
    }

    private function replaceInhouseRoomsProcedure(): void
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_inhouse_rooms');
        DB::unprepared(<<<'SQL'
CREATE PROCEDURE rpt_inhouse_rooms(
    IN p_from_date DATE, IN p_to_date DATE, IN p_actual TINYINT,
    IN p_room_class_id BIGINT, IN p_registration_status_id BIGINT,
    IN p_area VARCHAR(100), IN p_company_id BIGINT, IN p_booking_id BIGINT,
    IN p_show_main_guest TINYINT, IN p_show_detail TINYINT,
    IN p_show_room_rate TINYINT, IN p_vat TINYINT, IN p_no_vat TINYINT
)
READS SQL DATA
BEGIN
    DECLARE v_system_date DATE;
    SELECT DATE(system_date) INTO v_system_date FROM system_date_rolls ORDER BY id DESC LIMIT 1;
    SET v_system_date = COALESCE(v_system_date, CURRENT_DATE());

    WITH RECURSIVE report_dates AS (
        SELECT p_from_date AS stay_date
        UNION ALL
        SELECT DATE_ADD(stay_date, INTERVAL 1 DAY)
        FROM report_dates
        WHERE stay_date < p_to_date
    ),
    room_days AS (
        SELECT br.id, d.stay_date,
               COALESCE((
                   SELECT rnb.rate
                   FROM service_bills sb
                   INNER JOIN room_night_bills rnb ON rnb.bill_id = sb.Ma AND rnb.is_room_night = 1
                   WHERE sb.RentalRoomId1 = br.id
                     AND DATE(sb.Date) = d.stay_date
                     AND sb.ServiceId = 'RM'
                     AND sb.Edit = 0
                   ORDER BY sb.Ma DESC
                   LIMIT 1
               ), br.rate) AS night_rate,
               EXISTS (
                   SELECT 1
                   FROM service_bills sb
                   INNER JOIN room_night_bills rnb ON rnb.bill_id = sb.Ma AND rnb.is_room_night = 1
                   WHERE sb.RentalRoomId1 = br.id
                     AND DATE(sb.Date) = d.stay_date
                     AND sb.ServiceId = 'RM'
                     AND sb.Edit = 0
               ) AS has_room_night
        FROM booking_rooms br
        CROSS JOIN report_dates d
    ),
    eligible AS (
        SELECT br.id, rd.stay_date, rd.night_rate, br.booking_id, br.room_number, br.room_class_id,
               br.original_room_class_id, br.arrival_date, br.departure_date, br.CheckoutDate,
               br.arrival_time, br.departure_time, br.ActutalNumOfDays, br.status AS room_status,
               br.rate, br.adults, br.babies, br.children_qty, br.extra_bed_qty, br.breakfast,
               br.note AS room_note, b.booking_name, b.note AS booking_note, b.special_requests,
               b.company_id, b.registration_status_id, b.has_vat, b.is_day_use,
               c.name AS company_name, rs.name AS registration_status_name,
               rc.code AS room_type_code, rc.name AS room_type_name, orc.name AS original_room_type_name,
               r.area, r.orders AS room_order, r.is_internal,
               CASE
                   WHEN br.status = 4 AND rd.has_room_night = 1 THEN 'X'
                   WHEN br.status = 1 AND rd.stay_date > br.arrival_date + INTERVAL GREATEST(br.ActutalNumOfDays - 1, 0) DAY THEN 'X'
                   ELSE NULL
               END AS no_show_late
        FROM booking_rooms br
        INNER JOIN room_days rd ON rd.id = br.id
        INNER JOIN bookings b ON b.id = br.booking_id AND b.deleted_at IS NULL
        INNER JOIN room_classes rc ON rc.id = br.room_class_id
        LEFT JOIN room_classes orc ON orc.id = CAST(SUBSTRING_INDEX(br.original_room_class_id, '-', 1) AS UNSIGNED)
        LEFT JOIN rooms r ON r.room_number = br.room_number
        LEFT JOIN companies c ON c.id = b.company_id
        LEFT JOIN registration_statuses rs ON rs.id = b.registration_status_id
        WHERE br.deleted_at IS NULL
          AND (r.is_internal IS NULL OR r.is_internal = 0)
          AND (br.room_number IS NULL OR br.room_number NOT LIKE '0%')
          AND (rs.id IS NULL OR rs.is_availability = 1)
          AND (p_area IS NULL OR p_area = '' OR r.area = p_area)
          AND (p_company_id IS NULL OR b.company_id = p_company_id)
          AND (p_booking_id IS NULL OR b.id = p_booking_id)
          AND (p_room_class_id IS NULL OR br.room_class_id = p_room_class_id)
          AND (p_registration_status_id IS NULL OR b.registration_status_id = p_registration_status_id)
          AND ((p_vat = 1 AND p_no_vat = 0 AND b.has_vat = 1)
            OR (p_vat = 0 AND p_no_vat = 1 AND b.has_vat = 0)
            OR (p_vat = 0 AND p_no_vat = 0))
          AND (
             (p_actual = 1 AND rd.stay_date = v_system_date AND br.status = 1
                AND (br.departure_date >= v_system_date OR br.is_day_use = 1 OR b.is_day_use = 1))
             OR (p_actual = 0 AND rd.stay_date = v_system_date
                AND ((br.status = 1 AND br.departure_date > v_system_date)
                  OR (br.status = 0 AND br.arrival_date = v_system_date)))
             OR (rd.stay_date <> v_system_date AND rd.stay_date >= br.arrival_date
                AND rd.stay_date < br.departure_date AND rd.has_room_night = 1)
             OR (br.status = 4 AND rd.has_room_night = 1)
          )
    ),
    result AS (
        SELECT e.stay_date AS StayDateGroup,
               CONCAT(e.booking_id, ' - ', e.booking_name) AS Booking,
               e.booking_id AS BookingId, e.booking_id AS RegisterId, e.id AS RentalRoomId,
               CASE WHEN g.is_primary = 1 THEN COALESCE(e.room_number, '') ELSE NULL END AS Room,
               CASE WHEN g.is_primary = 1 THEN e.room_type_code ELSE NULL END AS RoomTypeCode,
               CASE WHEN g.is_primary = 1 THEN e.room_type_name ELSE NULL END AS RoomType,
               CASE WHEN g.is_primary = 1 THEN e.original_room_type_name ELSE NULL END AS RoomTypeOriginal,
               TRIM(CONCAT_WS(' ', NULLIF(gu.title, ''), gu.full_name)) AS GuestName,
               CONCAT(DATE_FORMAT(e.arrival_date, '%d/%m/%Y'), IF(e.arrival_time IS NULL, '', CONCAT(' - ', TIME_FORMAT(e.arrival_time, '%H:%i')))) AS ArrivalDate,
               CONCAT(DATE_FORMAT(e.departure_date, '%d/%m/%Y'), IF(e.departure_time IS NULL, '', CONCAT(' - ', TIME_FORMAT(e.departure_time, '%H:%i')))) AS DepartureDate,
               DATE_FORMAT(e.CheckoutDate, '%d/%m/%Y') AS ActualDepartureDate,
               CASE WHEN g.is_primary = 1 THEN e.extra_bed_qty ELSE NULL END AS ExtraBed,
               CASE WHEN g.is_primary = 1 THEN e.ActutalNumOfDays ELSE NULL END AS RoomNight,
               CASE WHEN g.is_primary = 1 THEN e.adults ELSE NULL END AS Adult,
               CASE WHEN g.is_primary = 1 THEN e.babies ELSE NULL END AS Infant,
               CASE WHEN g.is_primary = 1 THEN e.children_qty ELSE NULL END AS Child,
               CASE WHEN g.is_primary = 1 THEN CONCAT(e.adults, ' / ', e.babies, ' / ', e.children_qty) ELSE NULL END AS AdultChild,
               CASE WHEN g.is_primary = 1 AND p_show_room_rate = 1 THEN e.night_rate ELSE NULL END AS Rate,
               CASE WHEN p_show_detail = 1 THEN e.no_show_late ELSE NULL END AS NoShowLate,
               CASE WHEN g.is_primary = 1 THEN e.special_requests ELSE NULL END AS Special,
               COALESCE(e.room_note, e.booking_note) AS Note, e.company_id AS CompanyId,
               e.company_name AS Company, e.registration_status_name AS BookingStatusName,
               g.is_primary AS IsMainGuest, e.room_type_code AS SummaryRoomTypeCode,
               e.room_order AS RoomOrder
        FROM eligible e
        INNER JOIN booking_room_guests g ON g.booking_room_id = e.id AND g.status IN (0, 1, 2, 4, 100)
        INNER JOIN guests gu ON gu.id = g.guest_id
        WHERE (COALESCE(p_show_main_guest, 1) = 0 OR g.is_primary = 1)

        UNION ALL

        SELECT e.stay_date, CONCAT(e.booking_id, ' - ', e.booking_name), e.booking_id, e.booking_id, e.id,
               NULL, NULL, NULL, NULL, TRIM(CONCAT_WS(' ', NULLIF(ch.title, ''), ch.full_name)),
               CONCAT(DATE_FORMAT(e.arrival_date, '%d/%m/%Y'), IF(e.arrival_time IS NULL, '', CONCAT(' - ', TIME_FORMAT(e.arrival_time, '%H:%i')))),
               CONCAT(DATE_FORMAT(e.departure_date, '%d/%m/%Y'), IF(e.departure_time IS NULL, '', CONCAT(' - ', TIME_FORMAT(e.departure_time, '%H:%i')))),
               NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL,
               e.company_id, e.company_name, NULL, -1, e.room_type_code, e.room_order
        FROM eligible e
        INNER JOIN booking_room_children brc ON brc.booking_room_id = e.id AND brc.status IN (0, 1, 2, 4, 100)
        INNER JOIN booking_children ch ON ch.id = brc.booking_child_id
        WHERE COALESCE(p_show_main_guest, 1) = 0
    ),
    room_type_totals AS (
        SELECT SummaryRoomTypeCode, COUNT(DISTINCT RentalRoomId) AS room_count
        FROM result
        WHERE IsMainGuest = 1
        GROUP BY SummaryRoomTypeCode
    ),
    room_total AS (
        SELECT COUNT(DISTINCT RentalRoomId) AS room_count
        FROM result
        WHERE IsMainGuest = 1
    )
    SELECT result.*,
           ROUND(COALESCE(room_type_totals.room_count * 100 / NULLIF(room_total.room_count, 0), 0), 2) AS RoomTypePercent
    FROM result
    LEFT JOIN room_type_totals ON room_type_totals.SummaryRoomTypeCode = result.SummaryRoomTypeCode
    CROSS JOIN room_total
    ORDER BY result.RoomOrder, result.RentalRoomId, result.IsMainGuest DESC, result.GuestName;
END
SQL);
    }

    private function replaceComplimentaryRoomsProcedure(): void
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_complimentary_rooms');
        DB::unprepared(<<<'SQL'
CREATE PROCEDURE rpt_complimentary_rooms(
    IN p_from_date DATE, IN p_to_date DATE, IN p_room_rate_code VARCHAR(50)
)
READS SQL DATA
BEGIN
    DECLARE v_system_date DATE;
    DECLARE v_tach_foc TINYINT DEFAULT 1;
    SELECT DATE(system_date) INTO v_system_date FROM system_date_rolls ORDER BY id DESC LIMIT 1;
    SET v_system_date = COALESCE(v_system_date, CURRENT_DATE());
    SELECT COALESCE(CAST(value AS UNSIGNED), 1) INTO v_tach_foc
    FROM hotel_configs WHERE name = 'TachFOC' LIMIT 1;

    WITH RECURSIVE dates AS (
        SELECT p_from_date AS stay_date
        UNION ALL
        SELECT DATE_ADD(stay_date, INTERVAL 1 DAY)
        FROM dates
        WHERE stay_date < p_to_date
    ),
    daily_rm AS (
        SELECT sb.RentalRoomId1 AS rental_room_id, DATE(sb.Date) AS stay_date,
               SUM(COALESCE(rnb.rate, sb.Amount, 0)) AS room_rate,
               MAX(NULLIF(rnb.rate_code, '')) AS rate_code,
               MAX(CASE WHEN UPPER(COALESCE(p.payment_method_id, '')) = 'CL'
                          OR COALESCE(pm.is_free, 0) = 1 THEN 1 ELSE 0 END) AS has_free_payment,
               1 AS has_room_night
        FROM service_bills sb
        INNER JOIN room_night_bills rnb ON rnb.bill_id = sb.Ma AND rnb.is_room_night = 1
        LEFT JOIN payments p ON p.id = sb.PaymentId
        LEFT JOIN payment_methods pm ON pm.code = p.payment_method_id
        WHERE sb.ServiceId = 'RM'
          AND sb.Edit = 0
          AND DATE(sb.Date) BETWEEN p_from_date AND p_to_date
        GROUP BY sb.RentalRoomId1, DATE(sb.Date)
    ),
    room_days AS (
        SELECT br.id, d.stay_date,
               CASE WHEN drm.has_room_night = 1 THEN drm.room_rate ELSE COALESCE(br.rate, 0) END AS room_rate,
               UPPER(COALESCE(NULLIF(drm.rate_code, ''), NULLIF(br.rate_code, ''), '')) AS daily_rate_code,
               COALESCE(drm.has_room_night, 0) AS has_room_night,
               COALESCE(drm.has_free_payment, 0) AS has_free_payment
        FROM booking_rooms br
        CROSS JOIN dates d
        LEFT JOIN daily_rm drm ON drm.rental_room_id = br.id AND drm.stay_date = d.stay_date
    ),
    eligible AS (
        SELECT rd.*, br.booking_id, br.room_number, br.arrival_date, br.departure_date,
               br.ActutalNumOfDays, br.is_day_use, br.status, br.rate, br.rate_code,
               b.note, b.company_id
        FROM room_days rd
        INNER JOIN booking_rooms br ON br.id = rd.id
        INNER JOIN bookings b ON b.id = br.booking_id AND b.deleted_at IS NULL
        LEFT JOIN rooms r ON r.room_number = br.room_number
        WHERE br.deleted_at IS NULL
          AND (r.is_internal IS NULL OR r.is_internal = 0)
          AND (br.room_number IS NULL OR br.room_number NOT LIKE '0%')
          AND br.status IN (0, 1, 2, 4, 100)
          AND (br.status <> 100 OR br.ActutalNumOfDays <> 0)
          AND rd.stay_date >= br.arrival_date
          AND rd.stay_date <= DATE_ADD(br.arrival_date, INTERVAL GREATEST(br.ActutalNumOfDays - IF(br.is_day_use = 1, 0, 1), 0) DAY)
          AND (
              (v_tach_foc = 1
               AND (rd.stay_date >= v_system_date OR rd.has_room_night = 1)
               AND (rd.room_rate = 0 OR rd.has_free_payment = 1))
              OR
              (v_tach_foc <> 1 AND (
                  (rd.daily_rate_code LIKE 'FOC%' AND (rd.has_room_night = 0 OR rd.room_rate = 0))
                  OR rd.daily_rate_code LIKE 'HU%'
                  OR (rd.daily_rate_code NOT LIKE 'FOC%'
                      AND rd.daily_rate_code NOT LIKE 'HU%'
                      AND (rd.room_rate = 0 OR rd.has_free_payment = 1))
              ))
          )
    )
    SELECT e.stay_date AS StayDateGroup, e.booking_id AS BookingId, e.id AS RentalRoomId,
           TRIM(CONCAT_WS(' ', NULLIF(g.title, ''), g.full_name)) AS GuestName,
           e.room_number AS Room,
           DATE_FORMAT(e.arrival_date, '%d/%m/%Y') AS ArrivalDate,
           DATE_FORMAT(e.departure_date, '%d/%m/%Y') AS DepartureDate,
           c.name AS Company,
           CASE
               WHEN e.daily_rate_code LIKE 'FOC OWN%' THEN 'FOC OWNER'
               WHEN e.daily_rate_code LIKE 'FOC%' THEN 'FOC'
               WHEN e.daily_rate_code LIKE 'HU%' THEN 'HU'
               ELSE 'Compliment'
           END AS RoomRateCode,
           e.room_rate AS Rate, e.note AS Note, v_tach_foc AS TachFOCMode
    FROM eligible e
    INNER JOIN booking_room_guests brg ON brg.booking_room_id = e.id
        AND brg.is_primary = 1 AND brg.status IN (0, 1, 2, 4, 100)
    INNER JOIN guests g ON g.id = brg.guest_id
    LEFT JOIN companies c ON c.id = e.company_id
    WHERE p_room_rate_code IS NULL OR p_room_rate_code = ''
       OR (UPPER(p_room_rate_code) = 'FOC' AND e.daily_rate_code = 'FOC')
       OR (UPPER(p_room_rate_code) IN ('FOC OWN', 'FOC OWNER') AND e.daily_rate_code LIKE 'FOC OWN%')
       OR (UPPER(p_room_rate_code) = 'HU' AND e.daily_rate_code LIKE 'HU%')
       OR (UPPER(p_room_rate_code) = 'COMPLIMENT'
           AND e.daily_rate_code NOT LIKE 'FOC%' AND e.daily_rate_code NOT LIKE 'HU%')
    ORDER BY e.stay_date, e.booking_id, e.room_number;
END
SQL);
    }
};
