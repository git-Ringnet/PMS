<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SOURCE = 'EXPECTED_BREAKFAST';
    private const REPORT = 'EXPECTED_BREAKFAST';
    private const TEMPLATE_SUMMARY = 'EXPECTED_BREAKFAST_SUMMARY_STANDARD';
    private const TEMPLATE_DETAIL = 'EXPECTED_BREAKFAST_DETAIL_STANDARD';

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP PROCEDURE IF EXISTS `rpt_expected_breakfast`');
        DB::unprepared(<<<'SQL'
CREATE PROCEDURE `rpt_expected_breakfast`(
    IN p_from_date DATE,
    IN p_to_date DATE,
    IN p_show_type TINYINT,
    IN p_user VARCHAR(100),
    IN p_sort_by VARCHAR(30),
    IN p_sort_type VARCHAR(4),
    IN p_late_checkin TINYINT,
    IN p_show_room_details TINYINT,
    IN p_group_by_booking TINYINT
)
READS SQL DATA
BEGIN
    SET p_from_date = COALESCE(p_from_date, CURDATE());
    SET p_to_date = GREATEST(p_from_date, COALESCE(p_to_date, p_from_date));
    SET p_show_type = COALESCE(p_show_type, 1);
    SET p_sort_by = COALESCE(NULLIF(p_sort_by, ''), 'Room');
    SET p_sort_type = UPPER(COALESCE(NULLIF(p_sort_type, ''), 'ASC'));
    SET p_late_checkin = COALESCE(p_late_checkin, 1);
    SET p_show_room_details = COALESCE(p_show_room_details, 0);
    SET p_group_by_booking = COALESCE(p_group_by_booking, 0);

    -- 1. Date calendar series
    DROP TEMPORARY TABLE IF EXISTS tmp_dates;
    CREATE TEMPORARY TABLE tmp_dates (
        report_date DATE PRIMARY KEY
    );

    SET @cur_d = p_from_date;
    WHILE @cur_d <= p_to_date DO
        INSERT INTO tmp_dates (report_date) VALUES (@cur_d);
        SET @cur_d = DATE_ADD(@cur_d, INTERVAL 1 DAY);
    END WHILE;

    -- 2. Rooms active on each date
    DROP TEMPORARY TABLE IF EXISTS tmp_active_rooms;
    CREATE TEMPORARY TABLE tmp_active_rooms (
        report_date DATE,
        booking_room_id BIGINT,
        booking_id BIGINT,
        room_number VARCHAR(20),
        room_type_name VARCHAR(50),
        is_late_checkin TINYINT,
        arrival_date DATE,
        departure_date DATE,
        arrival_time TIME,
        breakfast TINYINT,
        adults INT,
        children_qty INT,
        babies INT,
        note TEXT,
        booking_note TEXT,
        booking_name VARCHAR(255),
        company_name VARCHAR(255),
        created_by VARCHAR(100),
        INDEX (booking_room_id, report_date),
        INDEX (report_date)
    );

    -- 2a. In-house regular rooms (PHÒNG Ở THẬT)
    INSERT INTO tmp_active_rooms (
        report_date, booking_room_id, booking_id, room_number, room_type_name,
        is_late_checkin, arrival_date, departure_date, arrival_time,
        breakfast, adults, children_qty, babies, note, booking_note,
        booking_name, company_name, created_by
    )
    SELECT DISTINCT
        d.report_date,
        br.id AS booking_room_id,
        b.id AS booking_id,
        br.room_number,
        'PHÒNG Ở THẬT' AS room_type_name,
        0 AS is_late_checkin,
        br.arrival_date,
        br.departure_date,
        br.arrival_time,
        br.breakfast,
        br.adults,
        br.children_qty,
        br.babies,
        br.note,
        b.note AS booking_note,
        b.booking_name,
        c.name AS company_name,
        br.created_by
    FROM tmp_dates d
    CROSS JOIN booking_rooms br
    INNER JOIN bookings b ON b.id = br.booking_id AND b.deleted_at IS NULL
    LEFT JOIN registration_statuses rs ON rs.id = b.registration_status_id
    LEFT JOIN rooms r ON r.room_number = br.room_number
    LEFT JOIN companies c ON c.id = b.company_id
    LEFT JOIN booking_room_guests brg ON brg.booking_room_id = br.id AND brg.status <> 3
    WHERE br.deleted_at IS NULL
      AND br.status IN (0, 1, 2, 100)
      AND COALESCE(rs.is_availability, 1) = 1
      AND (br.room_number IS NULL OR br.room_number NOT LIKE '0%')
      AND COALESCE(r.is_internal, 0) = 0
      AND (
          (brg.id IS NOT NULL AND (
              (d.report_date BETWEEN DATE_ADD(brg.actual_arrival_date, INTERVAL 1 DAY) AND brg.actual_checkout_date)
              OR (brg.actual_arrival_date = brg.actual_checkout_date AND d.report_date = brg.actual_arrival_date)
              OR (d.report_date = brg.actual_arrival_date AND br.arrival_time <= '00:01')
          ))
          OR (brg.id IS NULL AND (
              (d.report_date BETWEEN DATE_ADD(br.arrival_date, INTERVAL 1 DAY) AND br.departure_date)
              OR (br.arrival_date = br.departure_date AND d.report_date = br.arrival_date)
              OR (d.report_date = br.arrival_date AND br.arrival_time <= '00:01')
          ))
      )
      AND br.id NOT IN (
          SELECT lc.booking_room_id
          FROM late_checkins lc
          WHERE lc.actual_arrival_date = d.report_date
      );

    -- 2b. Late check-in rooms (PHÒNG LATE CHECK IN)
    IF p_late_checkin = 1 THEN
        INSERT INTO tmp_active_rooms (
            report_date, booking_room_id, booking_id, room_number, room_type_name,
            is_late_checkin, arrival_date, departure_date, arrival_time,
            breakfast, adults, children_qty, babies, note, booking_note,
            booking_name, company_name, created_by
        )
        SELECT DISTINCT
            d.report_date,
            br.id AS booking_room_id,
            b.id AS booking_id,
            br.room_number,
            'PHÒNG LATE CHECK IN' AS room_type_name,
            1 AS is_late_checkin,
            br.arrival_date,
            br.departure_date,
            br.arrival_time,
            br.breakfast,
            br.adults,
            br.children_qty,
            br.babies,
            br.note,
            b.note AS booking_note,
            b.booking_name,
            c.name AS company_name,
            br.created_by
        FROM tmp_dates d
        CROSS JOIN booking_rooms br
        INNER JOIN bookings b ON b.id = br.booking_id AND b.deleted_at IS NULL
        LEFT JOIN registration_statuses rs ON rs.id = b.registration_status_id
        LEFT JOIN rooms r ON r.room_number = br.room_number
        LEFT JOIN companies c ON c.id = b.company_id
        WHERE br.deleted_at IS NULL
          AND br.status IN (0, 1, 2, 100)
          AND COALESCE(rs.is_availability, 1) = 1
          AND (br.room_number IS NULL OR br.room_number NOT LIKE '0%')
          AND COALESCE(r.is_internal, 0) = 0
          AND (br.arrival_date < d.report_date OR br.arrival_time <= '05:30' OR br.arrival_time <= '09:30')
          AND (
              br.id IN (
                  SELECT lc.booking_room_id
                  FROM late_checkins lc
                  WHERE lc.actual_arrival_date = d.report_date
              )
              OR br.id IN (
                  SELECT sb.RentalRoomId1
                  FROM service_bills sb
                  WHERE sb.ServiceId = 'RM' AND DATE(sb.Date) = d.report_date
              )
          );
    END IF;

    -- 3. Room aggregations (Adults, Children, Breakfast determination)
    DROP TEMPORARY TABLE IF EXISTS tmp_room_summary;
    CREATE TEMPORARY TABLE tmp_room_summary (
        report_date DATE,
        booking_room_id BIGINT,
        booking_id BIGINT,
        booking_code VARCHAR(50),
        booking_name VARCHAR(255),
        company_name VARCHAR(255),
        room_number VARCHAR(20),
        room_type_name VARCHAR(50),
        is_late_checkin TINYINT,
        guest_name VARCHAR(255),
        nationality VARCHAR(100),
        arrival_date DATE,
        departure_date DATE,
        adults INT,
        children INT,
        children_nk INT,
        children_no_bf INT,
        total_pax INT,
        extra_bf_desc TEXT,
        note TEXT,
        is_breakfast TINYINT,
        detail_room TEXT,
        INDEX (booking_room_id, report_date)
    );

    INSERT INTO tmp_room_summary
    SELECT
        ar.report_date,
        ar.booking_room_id,
        ar.booking_id,
        CONCAT(COALESCE(hs.prefix_booking_id, ''), ar.booking_id) AS booking_code,
        COALESCE(ar.booking_name, '') AS booking_name,
        COALESCE(ar.company_name, '') AS company_name,
        COALESCE(ar.room_number, '') AS room_number,
        ar.room_type_name,
        ar.is_late_checkin,
        COALESCE(
            (SELECT TRIM(CONCAT_WS(' ', NULLIF(g.title, ''), g.full_name))
             FROM booking_room_guests brg
             INNER JOIN guests g ON g.id = brg.guest_id
             WHERE brg.booking_room_id = ar.booking_room_id AND brg.status <> 3
             ORDER BY brg.is_primary DESC, brg.id ASC LIMIT 1),
            ''
        ) AS guest_name,
        COALESCE(
            (SELECT COALESCE(n.nationality_name, g.nationality_code)
             FROM booking_room_guests brg
             INNER JOIN guests g ON g.id = brg.guest_id
             LEFT JOIN nationalities n ON n.nationality_code = g.nationality_code
             WHERE brg.booking_room_id = ar.booking_room_id AND brg.status <> 3
             ORDER BY brg.is_primary DESC, brg.id ASC LIMIT 1),
            ''
        ) AS nationality,
        ar.arrival_date,
        ar.departure_date,
        COALESCE(
            NULLIF((SELECT COUNT(brg.id)
                    FROM booking_room_guests brg
                    WHERE brg.booking_room_id = ar.booking_room_id AND brg.status <> 3), 0),
            ar.adults,
            1
        ) AS adults,
        COALESCE(
            (SELECT COUNT(bcbd.id)
             FROM booking_children bc
             INNER JOIN booking_child_breakfast_details bcbd ON bcbd.booking_child_id = bc.id
             WHERE bc.booking_room_id = ar.booking_room_id
               AND bcbd.service_date = ar.report_date
               AND bcbd.breakfast = 1 AND COALESCE(bcbd.is_free, 0) = 0 AND COALESCE(bcbd.amount, 0) > 0),
            0
        ) AS children,
        COALESCE(
            (SELECT COUNT(bcbd.id)
             FROM booking_children bc
             INNER JOIN booking_child_breakfast_details bcbd ON bcbd.booking_child_id = bc.id
             WHERE bc.booking_room_id = ar.booking_room_id
               AND bcbd.service_date = ar.report_date
               AND bcbd.breakfast = 1 AND (COALESCE(bcbd.is_free, 0) = 1 OR COALESCE(bcbd.amount, 0) = 0)),
            COALESCE(ar.babies, 0)
        ) AS children_nk,
        COALESCE(
            (SELECT COUNT(bcbd.id)
             FROM booking_children bc
             INNER JOIN booking_child_breakfast_details bcbd ON bcbd.booking_child_id = bc.id
             WHERE bc.booking_room_id = ar.booking_room_id
               AND bcbd.service_date = ar.report_date
               AND bcbd.breakfast = 0),
            0
        ) AS children_no_bf,
        0 AS total_pax,
        COALESCE(
            (SELECT GROUP_CONCAT(CONCAT(COALESCE(brs.service_name, brs.service_code), ': ', brs.quantity) SEPARATOR '\n')
             FROM booking_room_services brs
             WHERE brs.booking_room_id = ar.booking_room_id
               AND DATE(brs.service_date) = DATE_SUB(ar.report_date, INTERVAL 1 DAY)
               AND (brs.service_code = 'BF' OR brs.service_code IN ('AL', 'BD', 'BE', 'BU'))),
            ''
        ) AS extra_bf_desc,
        COALESCE(ar.note, ar.booking_note, '') AS note,
        CASE
            WHEN ar.breakfast = 1 THEN 1
            WHEN (SELECT COUNT(*) FROM booking_room_services brs
                  WHERE brs.booking_room_id = ar.booking_room_id
                    AND DATE(brs.service_date) = DATE_SUB(ar.report_date, INTERVAL 1 DAY)
                    AND (brs.service_code = 'BF' OR brs.service_code IN ('AL', 'BD', 'BE', 'BU'))) > 0 THEN 1
            WHEN (SELECT COUNT(*) FROM booking_children bc
                  INNER JOIN booking_child_breakfast_details bcbd ON bcbd.booking_child_id = bc.id
                  WHERE bc.booking_room_id = ar.booking_room_id
                    AND bcbd.service_date = ar.report_date
                    AND bcbd.breakfast = 1) > 0 THEN 1
            ELSE 0
        END AS is_breakfast,
        '' AS detail_room
    FROM tmp_active_rooms ar
    LEFT JOIN hotel_settings hs ON 1 = 1
    WHERE (p_user IS NULL OR p_user = '' OR ar.created_by = p_user);

    -- Calculate total_pax and detail_room
    UPDATE tmp_room_summary
    SET total_pax = adults + children + children_nk,
        detail_room = CONCAT(
            'Phòng ', room_number,
            ' - BK ', booking_id,
            IF(booking_name != '', CONCAT(' - ', booking_name), ''),
            ' - Người lớn: ', adults,
            ' - Trẻ em: ', children,
            ' - Trẻ em MP: ', children_nk,
            ' - Trẻ em KAS: ', children_no_bf
        );

    -- Filter by p_show_type (1: Có ăn sáng, 2: Không ăn sáng, 0: Tất cả)
    IF p_show_type = 1 THEN
        DELETE FROM tmp_room_summary WHERE is_breakfast <> 1;
    ELSEIF p_show_type = 2 THEN
        DELETE FROM tmp_room_summary WHERE is_breakfast <> 0;
    END IF;

    -- Return result depending on p_show_room_details
    IF p_show_room_details = 0 THEN
        -- Summary template output (matching sp_035)
        SELECT
            ROW_NUMBER() OVER (
                ORDER BY
                    CASE WHEN p_group_by_booking = 1 THEN s.booking_id END ASC,
                    CASE WHEN p_sort_type = 'DESC' AND p_sort_by = 'Room' THEN CAST(s.room_number AS UNSIGNED) END DESC,
                    CASE WHEN p_sort_type <> 'DESC' AND p_sort_by = 'Room' THEN CAST(s.room_number AS UNSIGNED) END ASC,
                    CASE WHEN p_sort_type = 'DESC' AND p_sort_by = 'ArrivalDate' THEN s.arrival_date END DESC,
                    CASE WHEN p_sort_type <> 'DESC' AND p_sort_by = 'ArrivalDate' THEN s.arrival_date END ASC,
                    CASE WHEN p_sort_type = 'DESC' AND p_sort_by = 'DepartureDate' THEN s.departure_date END DESC,
                    CASE WHEN p_sort_type <> 'DESC' AND p_sort_by = 'DepartureDate' THEN s.departure_date END ASC,
                    s.booking_room_id ASC
            ) AS STT,
            DATE_FORMAT(s.report_date, '%d/%m/%Y') AS DateGroup,
            s.report_date AS Date,
            s.booking_code AS BookingCode,
            s.booking_code AS Booking,
            s.booking_id AS BookingId,
            s.booking_name AS BookingName,
            s.room_number AS Room,
            s.guest_name AS GuestName,
            s.company_name AS CompanyName,
            s.nationality AS Nationality,
            s.adults AS Adults,
            s.adults AS Adult,
            s.children AS Children,
            s.children AS Child,
            s.children_nk AS ChildrenNK,
            s.children_nk AS ChildMP,
            s.children_no_bf AS ChildrenNoBreakfast,
            s.children_no_bf AS ChildKAS,
            s.total_pax AS TotalPax,
            s.extra_bf_desc AS Description,
            s.note AS Note,
            s.room_type_name AS RoomType,
            s.is_breakfast AS IsBreakfast,
            DATE_FORMAT(s.arrival_date, '%d/%m/%Y') AS ArrivalDate,
            DATE_FORMAT(s.departure_date, '%d/%m/%Y') AS DepartureDate,
            s.detail_room AS DetailRoom,
            SUM(s.adults) OVER (PARTITION BY s.report_date) AS DateTotalAdults,
            SUM(s.children) OVER (PARTITION BY s.report_date) AS DateTotalChildren,
            SUM(s.children_nk) OVER (PARTITION BY s.report_date) AS DateTotalChildrenNK,
            SUM(s.total_pax) OVER (PARTITION BY s.report_date) AS DateTotalPax,
            SUM(s.adults) OVER () AS ReportTotalAdults,
            SUM(s.children) OVER () AS ReportTotalChildren,
            SUM(s.children_nk) OVER () AS ReportTotalChildrenNK,
            SUM(s.total_pax) OVER () AS ReportTotalPax
        FROM tmp_room_summary s
        ORDER BY STT;

    ELSE
        -- Detail template output (matching sp_032)
        DROP TEMPORARY TABLE IF EXISTS tmp_detail_rows;
        CREATE TEMPORARY TABLE tmp_detail_rows (
            report_date DATE,
            booking_room_id BIGINT,
            booking_id BIGINT,
            booking_code VARCHAR(50),
            booking_name VARCHAR(255),
            room_number VARCHAR(20),
            room_type_name VARCHAR(50),
            detail_room TEXT,
            guest_name VARCHAR(255),
            nationality VARCHAR(100),
            arrival_date DATE,
            departure_date DATE,
            note TEXT,
            is_breakfast TINYINT,
            is_child TINYINT,
            adults INT,
            children INT,
            children_nk INT,
            children_no_bf INT,
            total_pax INT
        );

        -- Adult guests from booking_room_guests
        INSERT INTO tmp_detail_rows
        SELECT
            s.report_date,
            s.booking_room_id,
            s.booking_id,
            s.booking_code,
            s.booking_name,
            s.room_number,
            s.room_type_name,
            s.detail_room,
            COALESCE(TRIM(CONCAT_WS(' ', NULLIF(g.title, ''), g.full_name)), s.guest_name) AS guest_name,
            COALESCE(n.nationality_name, g.nationality_code, s.nationality) AS nationality,
            COALESCE(brg.actual_arrival_date, s.arrival_date) AS arrival_date,
            COALESCE(brg.actual_checkout_date, s.departure_date) AS departure_date,
            COALESCE(NULLIF(g.note, ''), s.note) AS note,
            s.is_breakfast,
            0 AS is_child,
            s.adults,
            s.children,
            s.children_nk,
            s.children_no_bf,
            s.total_pax
        FROM tmp_room_summary s
        LEFT JOIN booking_room_guests brg ON brg.booking_room_id = s.booking_room_id AND brg.status <> 3
        LEFT JOIN guests g ON g.id = brg.guest_id
        LEFT JOIN nationalities n ON n.nationality_code = g.nationality_code;

        -- Children from booking_children
        INSERT INTO tmp_detail_rows
        SELECT
            s.report_date,
            s.booking_room_id,
            s.booking_id,
            s.booking_code,
            s.booking_name,
            s.room_number,
            s.room_type_name,
            s.detail_room,
            CONCAT('Chd. ', bc.full_name) AS guest_name,
            COALESCE(n.nationality_name, bc.nationality_code, s.nationality) AS nationality,
            s.arrival_date,
            s.departure_date,
            COALESCE(NULLIF(bc.note, ''), s.note) AS note,
            s.is_breakfast,
            1 AS is_child,
            s.adults,
            s.children,
            s.children_nk,
            s.children_no_bf,
            s.total_pax
        FROM tmp_room_summary s
        INNER JOIN booking_children bc ON bc.booking_room_id = s.booking_room_id
        LEFT JOIN nationalities n ON n.nationality_code = bc.nationality_code;

        -- Return detailed rows
        SELECT
            ROW_NUMBER() OVER (
                ORDER BY
                    CASE WHEN p_group_by_booking = 1 THEN d.booking_id END ASC,
                    CASE WHEN p_sort_type = 'DESC' AND p_sort_by = 'Room' THEN CAST(d.room_number AS UNSIGNED) END DESC,
                    CASE WHEN p_sort_type <> 'DESC' AND p_sort_by = 'Room' THEN CAST(d.room_number AS UNSIGNED) END ASC,
                    CASE WHEN p_sort_type = 'DESC' AND p_sort_by = 'ArrivalDate' THEN d.arrival_date END DESC,
                    CASE WHEN p_sort_type <> 'DESC' AND p_sort_by = 'ArrivalDate' THEN d.arrival_date END ASC,
                    CASE WHEN p_sort_type = 'DESC' AND p_sort_by = 'DepartureDate' THEN d.departure_date END DESC,
                    CASE WHEN p_sort_type <> 'DESC' AND p_sort_by = 'DepartureDate' THEN d.departure_date END ASC,
                    d.booking_room_id ASC,
                    d.is_child ASC,
                    d.guest_name ASC
            ) AS STT,
            DATE_FORMAT(d.report_date, '%d/%m/%Y') AS DateGroup,
            d.report_date AS Date,
            d.room_type_name AS RoomType,
            d.detail_room AS DetailRoom,
            d.room_number AS Room,
            d.guest_name AS GuestName,
            d.nationality AS Nationality,
            DATE_FORMAT(d.arrival_date, '%d/%m/%Y') AS ArrivalDate,
            DATE_FORMAT(d.departure_date, '%d/%m/%Y') AS DepartureDate,
            d.note AS Note,
            d.booking_code AS BookingCode,
            d.booking_code AS Booking,
            d.booking_id AS BookingId,
            d.booking_name AS BookingName,
            d.adults AS Adults,
            d.adults AS Adult,
            d.children AS Children,
            d.children AS Child,
            d.children_nk AS ChildrenNK,
            d.children_nk AS ChildMP,
            d.children_no_bf AS ChildrenNoBreakfast,
            d.children_no_bf AS ChildKAS,
            d.total_pax AS TotalPax,
            d.is_breakfast AS IsBreakfast,
            SUM(d.adults) OVER () AS ReportTotalAdults,
            SUM(d.children) OVER () AS ReportTotalChildren,
            SUM(d.children_nk) OVER () AS ReportTotalChildrenNK,
            SUM(d.total_pax) OVER () AS ReportTotalPax
        FROM tmp_detail_rows d
        ORDER BY STT;

        DROP TEMPORARY TABLE IF EXISTS tmp_detail_rows;
    END IF;

    DROP TEMPORARY TABLE IF EXISTS tmp_room_summary;
    DROP TEMPORARY TABLE IF EXISTS tmp_active_rooms;
    DROP TEMPORARY TABLE IF EXISTS tmp_dates;
END
SQL);

        $now = now();
        $database = DB::connection()->getDatabaseName();
        $parameters = [
            ['name' => 'p_from_date', 'mode' => 'IN', 'data_type' => 'date', 'database_type' => 'date', 'position' => 1, 'required' => true],
            ['name' => 'p_to_date', 'mode' => 'IN', 'data_type' => 'date', 'database_type' => 'date', 'position' => 2, 'required' => true],
            ['name' => 'p_show_type', 'mode' => 'IN', 'data_type' => 'tinyint', 'database_type' => 'tinyint', 'position' => 3, 'required' => true],
            ['name' => 'p_user', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(100)', 'position' => 4, 'required' => false],
            ['name' => 'p_sort_by', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(30)', 'position' => 5, 'required' => true],
            ['name' => 'p_sort_type', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(4)', 'position' => 6, 'required' => true],
            ['name' => 'p_late_checkin', 'mode' => 'IN', 'data_type' => 'tinyint', 'database_type' => 'tinyint', 'position' => 7, 'required' => false],
            ['name' => 'p_show_room_details', 'mode' => 'IN', 'data_type' => 'tinyint', 'database_type' => 'tinyint', 'position' => 8, 'required' => false],
            ['name' => 'p_group_by_booking', 'mode' => 'IN', 'data_type' => 'tinyint', 'database_type' => 'tinyint', 'position' => 9, 'required' => false],
        ];

        $fieldNames = [
            'STT', 'DateGroup', 'Date', 'BookingCode', 'Booking', 'BookingId', 'BookingName',
            'Room', 'GuestName', 'CompanyName', 'Nationality', 'Adults', 'Adult',
            'Children', 'Child', 'ChildrenNK', 'ChildMP', 'ChildrenNoBreakfast', 'ChildKAS',
            'TotalPax', 'Description', 'Note', 'RoomType', 'IsBreakfast', 'ArrivalDate',
            'DepartureDate', 'DetailRoom', 'DateTotalAdults', 'DateTotalChildren',
            'DateTotalChildrenNK', 'DateTotalPax', 'ReportTotalAdults', 'ReportTotalChildren',
            'ReportTotalChildrenNK', 'ReportTotalPax',
        ];

        $fields = collect($fieldNames)->map(fn ($name) => [
            'name' => $name,
            'type' => in_array($name, [
                'STT', 'BookingId', 'Adults', 'Adult', 'Children', 'Child', 'ChildrenNK',
                'ChildMP', 'ChildrenNoBreakfast', 'ChildKAS', 'TotalPax', 'IsBreakfast',
                'DateTotalAdults', 'DateTotalChildren', 'DateTotalChildrenNK', 'DateTotalPax',
                'ReportTotalAdults', 'ReportTotalChildren', 'ReportTotalChildrenNK', 'ReportTotalPax'
            ], true) ? 'integer' : 'string',
            'nullable' => true,
        ])->all();

        DB::table('report_data_sources')->updateOrInsert(
            ['code' => self::SOURCE],
            [
                'name' => 'Dữ liệu báo cáo dự kiến khách ăn sáng',
                'description' => 'MySQL chuyển đổi từ ProVista sp_035 & sp_032.',
                'source_type' => 'procedure',
                'schema_name' => $database,
                'object_name' => 'rpt_expected_breakfast',
                'parameter_schema' => json_encode($parameters, JSON_UNESCAPED_UNICODE),
                'field_schema' => json_encode($fields, JSON_UNESCAPED_UNICODE),
                'sample_parameters' => json_encode([
                    'p_from_date' => now()->toDateString(),
                    'p_to_date' => now()->toDateString(),
                    'p_show_type' => 1,
                    'p_user' => '',
                    'p_sort_by' => 'Room',
                    'p_sort_type' => 'ASC',
                    'p_late_checkin' => 1,
                    'p_show_room_details' => 0,
                    'p_group_by_booking' => 0,
                ], JSON_UNESCAPED_UNICODE),
                'max_rows' => 5000,
                'is_active' => true,
                'last_discovered_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $sourceId = DB::table('report_data_sources')->where('code', self::SOURCE)->value('id');

        // 1. Template Summary
        $summaryProvider = require database_path('report_templates/expected_breakfast_summary_reference.php');
        $summaryDef = $summaryProvider->definition();
        $summaryData = [
            'group' => 'Báo cáo phòng',
            'name' => 'Báo cáo dự kiến khách ăn sáng (Tổng hợp) - Mẫu chuẩn',
            'report_data_source_id' => $sourceId,
            'page_size' => 'A4',
            'page_orientation' => 'portrait',
            'margin_top' => 6,
            'margin_bottom' => 6,
            'margin_left' => 5,
            'margin_right' => 5,
            'content_json' => json_encode($summaryDef['content_json'], JSON_UNESCAPED_UNICODE),
            'content_html' => $summaryDef['content_html'],
            'css' => $summaryDef['css'],
            'version' => '1.0',
            'updated_at' => $now,
        ];

        DB::table('templates')->updateOrInsert(
            ['report' => self::TEMPLATE_SUMMARY],
            array_merge($summaryData, ['created_at' => $now])
        );

        // 2. Template Detail
        $detailProvider = require database_path('report_templates/expected_breakfast_detail_reference.php');
        $detailDef = $detailProvider->definition();
        $detailData = [
            'group' => 'Báo cáo phòng',
            'name' => 'Báo cáo dự kiến khách ăn sáng (Chi tiết) - Mẫu chuẩn',
            'report_data_source_id' => $sourceId,
            'page_size' => 'A4',
            'page_orientation' => 'portrait',
            'margin_top' => 6,
            'margin_bottom' => 6,
            'margin_left' => 5,
            'margin_right' => 5,
            'content_json' => json_encode($detailDef['content_json'], JSON_UNESCAPED_UNICODE),
            'content_html' => $detailDef['content_html'],
            'css' => $detailDef['css'],
            'version' => '1.0',
            'updated_at' => $now,
        ];

        DB::table('templates')->updateOrInsert(
            ['report' => self::TEMPLATE_DETAIL],
            array_merge($detailData, ['created_at' => $now])
        );

        // Sync templates on the database targeted by this migration
        foreach ([DB::getDefaultConnection()] as $conn) {
            try {
                if (DB::connection($conn)->getDriverName() === 'mysql') {
                    $tenantSourceId = DB::connection($conn)->table('report_data_sources')->where('code', self::SOURCE)->value('id') ?: $sourceId;
                    DB::connection($conn)->table('templates')->updateOrInsert(
                        ['report' => self::TEMPLATE_SUMMARY],
                        array_merge($summaryData, ['report_data_source_id' => $tenantSourceId, 'created_at' => $now])
                    );
                    DB::connection($conn)->table('templates')->updateOrInsert(
                        ['report' => self::TEMPLATE_DETAIL],
                        array_merge($detailData, ['report_data_source_id' => $tenantSourceId, 'created_at' => $now])
                    );
                }
            } catch (\Throwable) {
                // Ignore unavailable tenant connection
            }
        }

        $summaryTemplateId = DB::table('templates')->where('report', self::TEMPLATE_SUMMARY)->value('id');
        $detailTemplateId = DB::table('templates')->where('report', self::TEMPLATE_DETAIL)->value('id');

        $uiSchema = [
            ['name' => 'p_from_date', 'label' => 'Ngày', 'control' => 'date-range', 'range_end_parameter' => 'p_to_date', 'default' => '$today', 'required' => true],
            ['name' => 'p_to_date', 'label' => 'Đến ngày', 'control' => 'hidden', 'default' => '$today', 'required' => true],
            [
                'name' => 'p_show_type',
                'label' => 'Loại',
                'control' => 'select',
                'default' => 1,
                'required' => true,
                'options' => [
                    ['label' => 'Phòng có ăn sáng', 'value' => 1],
                    ['label' => 'Phòng không ăn sáng', 'value' => 2],
                    ['label' => 'Tất cả', 'value' => 0],
                ],
            ],
            [
                'name' => 'p_user',
                'label' => 'Người dùng',
                'control' => 'select',
                'default' => '',
                'required' => false,
                'placeholder' => 'Select Value',
                'options_source' => 'users',
                'options' => [],
            ],
            [
                'name' => 'p_sort_by',
                'label' => 'Sắp xếp theo',
                'control' => 'select',
                'default' => 'Room',
                'required' => true,
                'options' => [
                    ['label' => 'Room', 'value' => 'Room'],
                    ['label' => 'Ngày đến', 'value' => 'ArrivalDate'],
                    ['label' => 'Ngày đi', 'value' => 'DepartureDate'],
                ],
            ],
            [
                'name' => 'p_sort_type',
                'label' => 'Thứ tự',
                'control' => 'select',
                'default' => 'ASC',
                'required' => true,
                'options' => [
                    ['label' => 'ASC', 'value' => 'ASC'],
                    ['label' => 'DESC', 'value' => 'DESC'],
                ],
            ],
            [
                'name' => 'p_late_checkin',
                'label' => 'Tính phòng late checkin',
                'control' => 'checkbox',
                'default' => true,
                'required' => false,
            ],
            [
                'name' => 'p_show_room_details',
                'label' => 'Hiển thị thông tin phòng',
                'control' => 'checkbox',
                'default' => false,
                'required' => false,
            ],
            [
                'name' => 'p_group_by_booking',
                'label' => 'Đăng ký theo nhóm',
                'control' => 'checkbox',
                'default' => false,
                'required' => false,
            ],
        ];

        DB::table('report_definitions')->updateOrInsert(
            ['code' => self::REPORT],
            [
                'name' => 'Báo cáo dự kiến khách ăn sáng',
                'group' => 'Báo cáo phòng',
                'description' => 'Dự kiến khách ăn sáng theo ngày với mẫu tổng hợp và chi tiết khách trong phòng (ProVista sp_035 & sp_032).',
                'report_data_source_id' => $sourceId,
                'parameter_ui_schema' => json_encode($uiSchema, JSON_UNESCAPED_UNICODE),
                'sort_order' => 34,
                'is_active' => true,
                'show_in_menu' => true,
                'menu_locations' => json_encode(['reservation', 'frontdesk']),
                'menu_top_order' => 20,
                'menu_group_order' => 10,
                'menu_item_order' => 34,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $reportId = DB::table('report_definitions')->where('code', self::REPORT)->value('id');

        // Link templates
        DB::table('report_definition_template')->updateOrInsert(
            ['report_definition_id' => $reportId, 'template_id' => $summaryTemplateId],
            ['is_default' => true, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now]
        );

        DB::table('report_definition_template')->updateOrInsert(
            ['report_definition_id' => $reportId, 'template_id' => $detailTemplateId],
            ['is_default' => false, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now]
        );
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $reportId = DB::table('report_definitions')->where('code', self::REPORT)->value('id');
        $summaryTemplateId = DB::table('templates')->where('report', self::TEMPLATE_SUMMARY)->value('id');
        $detailTemplateId = DB::table('templates')->where('report', self::TEMPLATE_DETAIL)->value('id');

        if ($reportId) {
            DB::table('report_definition_template')->where('report_definition_id', $reportId)->delete();
            DB::table('report_definitions')->where('id', $reportId)->delete();
        }

        if ($summaryTemplateId) {
            DB::table('templates')->where('id', $summaryTemplateId)->delete();
        }

        if ($detailTemplateId) {
            DB::table('templates')->where('id', $detailTemplateId)->delete();
        }

        DB::table('report_data_sources')->where('code', self::SOURCE)->delete();
        DB::unprepared('DROP PROCEDURE IF EXISTS `rpt_expected_breakfast`');
    }
};
