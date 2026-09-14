<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SOURCE = 'INHOUSE_GUESTS';

    private const REPORT = 'INHOUSE_GUESTS';

    private const TEMPLATE = 'INHOUSE_GUESTS_STANDARD';

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_inhouse_guests');
        DB::unprepared(<<<'SQL'
CREATE PROCEDURE rpt_inhouse_guests(
    IN p_from_date DATE,
    IN p_to_date DATE,
    IN p_company_id BIGINT,
    IN p_booking_id BIGINT,
    IN p_view_agency TINYINT,
    IN p_show_booking TINYINT,
    IN p_show_note TINYINT
)
READS SQL DATA
BEGIN
    DECLARE v_system_date DATE;

    SELECT DATE(system_date)
    INTO v_system_date
    FROM system_date_rolls
    ORDER BY id DESC
    LIMIT 1;

    SET v_system_date = COALESCE(v_system_date, CURRENT_DATE());

    WITH RECURSIVE report_dates AS (
        SELECT p_from_date AS stay_date
        WHERE p_from_date IS NOT NULL
          AND p_to_date IS NOT NULL
          AND p_from_date <= p_to_date

        UNION ALL

        SELECT DATE_ADD(stay_date, INTERVAL 1 DAY)
        FROM report_dates
        WHERE stay_date < p_to_date
    ),
    posted_room_nights AS (
        SELECT DISTINCT
            sb.RentalRoomId1 AS rental_room_id,
            DATE(rnb.date) AS stay_date
        FROM service_bills AS sb
        INNER JOIN room_night_bills AS rnb
            ON rnb.bill_id = sb.Ma
           AND rnb.is_room_night = 1
        WHERE sb.ServiceId = 'RM'
          AND sb.Edit = 0
          AND sb.RentalRoomId1 IS NOT NULL
    ),
    eligible_room_days AS (
        /* func_054: ngày quá khứ chỉ lấy tiền phòng đã post. */
        SELECT
            br.id AS rental_room_id,
            prn.stay_date
        FROM posted_room_nights AS prn
        INNER JOIN booking_rooms AS br ON br.id = prn.rental_room_id
        INNER JOIN report_dates AS rd ON rd.stay_date = prn.stay_date
        WHERE prn.stay_date < v_system_date
          AND br.status IN (1, 2, 100)
          AND (br.status <> 100 OR COALESCE(br.ActutalNumOfDays, 0) <> 0)
          AND prn.stay_date BETWEEN DATE(br.arrival_date) AND DATE(COALESCE(br.CheckoutDate, br.departure_date))

        UNION

        /* func_054: sinh room-night hiện tại/tương lai cho phòng đang ở, trừ phòng ảo. */
        SELECT
            br.id AS rental_room_id,
            rd.stay_date
        FROM booking_rooms AS br
        INNER JOIN report_dates AS rd
            ON rd.stay_date >= v_system_date
           AND rd.stay_date >= DATE(br.arrival_date)
           AND rd.stay_date <= DATE_ADD(
                DATE(br.arrival_date),
                INTERVAL GREATEST(
                    COALESCE(br.ActutalNumOfDays, 0) - IF(COALESCE(br.is_day_use, 0) = 1, 0, 1),
                    0
                ) DAY
           )
        WHERE br.status = 1
          AND (COALESCE(br.ActutalNumOfDays, 0) <> 0 OR COALESCE(br.is_day_use, 0) = 1)
          AND br.room_number IS NOT NULL
          AND br.room_number NOT LIKE '0%'
          AND rd.stay_date <= DATE(COALESCE(br.CheckoutDate, br.departure_date))

        UNION

        /* func_054 giữ các room-night đã post ngoài khoảng sinh, gồm cả phòng ảo. */
        SELECT
            br.id AS rental_room_id,
            prn.stay_date
        FROM posted_room_nights AS prn
        INNER JOIN booking_rooms AS br ON br.id = prn.rental_room_id
        INNER JOIN report_dates AS rd ON rd.stay_date = prn.stay_date
        WHERE prn.stay_date >= v_system_date
          AND br.status = 1
          AND prn.stay_date BETWEEN DATE(br.arrival_date) AND DATE(COALESCE(br.CheckoutDate, br.departure_date))

        UNION

        /* sp_285: khách checkout hôm nay dùng room-night ngày hôm trước và đổi ngày sang ngày hệ thống. */
        SELECT
            br.id AS rental_room_id,
            v_system_date AS stay_date
        FROM booking_rooms AS br
        INNER JOIN report_dates AS rd ON rd.stay_date = v_system_date
        WHERE br.status = 1
          AND DATE(br.CheckoutDate) = v_system_date
          AND v_system_date BETWEEN DATE(br.arrival_date) AND DATE(br.CheckoutDate)
          AND EXISTS (
              SELECT 1
              FROM posted_room_nights AS previous_night
              WHERE previous_night.rental_room_id = br.id
                AND previous_night.stay_date = DATE_SUB(v_system_date, INTERVAL 1 DAY)
          )
    ),
    report_rows AS (
        SELECT
            erd.stay_date AS StayDate,
            DATE_FORMAT(erd.stay_date, '%d-%m-%Y') AS StayDateGroup,
            CONCAT(COALESCE(hs.prefix_booking_id, ''), b.id) AS BookingId,
            br.id AS RentalRoomId,
            brg.guest_id AS CustomerId,
            br.room_number AS Room,
            COALESCE(NULLIF(rf.name, ''), '---') AS RoomKind,
            COALESCE(NULLIF(rc.code, ''), '---') AS RoomType,
            b.booking_name AS BookingName,
            TRIM(CONCAT_WS(' ', NULLIF(g.title, ''), g.full_name)) AS Guest,
            DATE_FORMAT(COALESCE(brg.actual_arrival_date, br.actual_arrival_date, br.arrival_date), '%d-%m-%Y') AS ArrivalDate,
            DATE_FORMAT(br.departure_date, '%d-%m-%Y') AS DepartureDate,
            br.ActutalNumOfDays AS NumOfDays,
            COALESCE(
                NULLIF(n.nationality_name_en, ''),
                NULLIF(n.nationality_name, ''),
                NULLIF(g.nationality_code, ''),
                'Unidentified'
            ) AS NationalityName,
            COALESCE(NULLIF(g.nationality_code, ''), '---') AS Nationality,
            CASE
                WHEN g.title IN ('Mr.', 'Dr.') THEN 'M'
                ELSE 'F'
            END AS Gender,
            CASE
                WHEN COALESCE(p_show_note, 0) = 1
                    THEN COALESCE(NULLIF(br.note, ''), NULLIF(b.note, ''), '')
                ELSE ''
            END AS Note,
            COALESCE(NULLIF(b.note, ''), NULLIF(br.note, ''), '') AS BookingNote,
            b.company_id AS CompanyId,
            COALESCE(NULLIF(c.name, ''), 'Khách lẻ') AS Company,
            CASE
                WHEN COALESCE(p_view_agency, 0) = 1
                    THEN CONCAT(COALESCE(b.company_id, 0), ':', COALESCE(NULLIF(c.name, ''), 'Khách lẻ'))
                ELSE 'ALL'
            END AS AgencyGroupKey,
            CASE
                WHEN COALESCE(p_view_agency, 0) = 1 THEN COALESCE(NULLIF(c.name, ''), 'Khách lẻ')
                ELSE ''
            END AS AgencyLabel,
            CONCAT(b.id, ':', COALESCE(b.booking_name, '')) AS BookingGroupKey,
            COALESCE(r.orders, 999999) AS RoomOrder,
            brg.is_primary AS IsMainGuest
        FROM eligible_room_days AS erd
        INNER JOIN booking_rooms AS br ON br.id = erd.rental_room_id
        INNER JOIN bookings AS b ON b.id = br.booking_id
        INNER JOIN registration_statuses AS rs
            ON rs.id = b.registration_status_id
           AND rs.is_availability = 1
        INNER JOIN booking_room_guests AS brg
            ON brg.booking_room_id = br.id
           AND brg.status IN (0, 1, 2, 100)
        INNER JOIN guests AS g ON g.id = brg.guest_id
        LEFT JOIN rooms AS r ON r.room_number = br.room_number
        LEFT JOIN room_forms AS rf ON rf.id = COALESCE(r.room_form_id, br.RoomKind)
        LEFT JOIN room_classes AS rc ON rc.id = br.room_class_id
        LEFT JOIN companies AS c ON c.id = b.company_id
        LEFT JOIN nationalities AS n ON n.id = (
            SELECT n2.id
            FROM nationalities AS n2
            WHERE n2.nationality_id = g.nationality_code
               OR n2.nationality_code = g.nationality_code
            ORDER BY (n2.nationality_id = g.nationality_code) DESC, n2.id
            LIMIT 1
        )
        LEFT JOIN hotel_settings AS hs ON hs.id = (SELECT MIN(id) FROM hotel_settings)
        WHERE (p_company_id IS NULL OR p_company_id = 0 OR b.company_id = p_company_id)
          AND (p_booking_id IS NULL OR p_booking_id = 0 OR b.id = p_booking_id)
    ),
    nationality_totals AS (
        SELECT
            NationalityName,
            COUNT(*) AS NationalityGuestCount,
            COUNT(DISTINCT RentalRoomId) AS NationalityRoomCount,
            SUM(Gender = 'M') AS NationalityMaleCount,
            SUM(Gender = 'F') AS NationalityFemaleCount,
            SUM(Gender NOT IN ('M', 'F')) AS NationalityOtherCount
        FROM report_rows
        GROUP BY NationalityName
    ),
    report_totals AS (
        SELECT
            COUNT(*) AS TotalGuest,
            COUNT(DISTINCT RentalRoomId) AS TotalRoom
        FROM report_rows
    )
    SELECT
        report_rows.*,
        (report_rows.Gender = 'M') AS GenderMale,
        (report_rows.Gender = 'F') AS GenderFemale,
        (report_rows.Gender NOT IN ('M', 'F')) AS GenderOther,
        nationality_totals.NationalityGuestCount,
        nationality_totals.NationalityRoomCount,
        ROUND(
            nationality_totals.NationalityGuestCount * 100 / NULLIF(report_totals.TotalGuest, 0),
            2
        ) AS NationalityPercent,
        nationality_totals.NationalityMaleCount,
        nationality_totals.NationalityFemaleCount,
        nationality_totals.NationalityOtherCount,
        report_totals.TotalGuest,
        report_totals.TotalRoom,
        COALESCE(p_view_agency, 0) AS ViewAgency,
        COALESCE(p_show_booking, 0) AS ShowBooking
    FROM report_rows
    INNER JOIN nationality_totals
        ON nationality_totals.NationalityName = report_rows.NationalityName
    CROSS JOIN report_totals
    ORDER BY
        report_rows.StayDate,
        IF(COALESCE(p_view_agency, 0) = 1, report_rows.Company, ''),
        report_rows.BookingId,
        report_rows.RoomOrder,
        report_rows.RentalRoomId,
        report_rows.IsMainGuest DESC,
        report_rows.Guest;
END
SQL);

        $now = now();
        $database = DB::connection()->getDatabaseName();
        $parameters = [
            ['name' => 'p_from_date', 'mode' => 'IN', 'data_type' => 'date', 'database_type' => 'date', 'position' => 1, 'required' => true],
            ['name' => 'p_to_date', 'mode' => 'IN', 'data_type' => 'date', 'database_type' => 'date', 'position' => 2, 'required' => true],
            ['name' => 'p_company_id', 'mode' => 'IN', 'data_type' => 'bigint', 'database_type' => 'bigint', 'position' => 3, 'required' => false],
            ['name' => 'p_booking_id', 'mode' => 'IN', 'data_type' => 'bigint', 'database_type' => 'bigint', 'position' => 4, 'required' => false],
            ['name' => 'p_view_agency', 'mode' => 'IN', 'data_type' => 'tinyint', 'database_type' => 'tinyint', 'position' => 5, 'required' => true],
            ['name' => 'p_show_booking', 'mode' => 'IN', 'data_type' => 'tinyint', 'database_type' => 'tinyint', 'position' => 6, 'required' => false],
            ['name' => 'p_show_note', 'mode' => 'IN', 'data_type' => 'tinyint', 'database_type' => 'tinyint', 'position' => 7, 'required' => false],
        ];
        $fields = [
            'StayDate', 'StayDateGroup', 'BookingId', 'RentalRoomId', 'CustomerId', 'Room', 'RoomKind',
            'RoomType', 'BookingName', 'Guest', 'ArrivalDate', 'DepartureDate', 'NumOfDays', 'NationalityName',
            'Nationality', 'Gender', 'Note', 'BookingNote', 'CompanyId', 'Company', 'AgencyGroupKey', 'AgencyLabel',
            'BookingGroupKey', 'RoomOrder', 'IsMainGuest', 'GenderMale', 'GenderFemale', 'GenderOther',
            'NationalityGuestCount', 'NationalityRoomCount', 'NationalityPercent', 'NationalityMaleCount',
            'NationalityFemaleCount', 'NationalityOtherCount', 'TotalGuest', 'TotalRoom', 'ViewAgency', 'ShowBooking',
        ];
        $numeric = [
            'CompanyId', 'RoomOrder', 'IsMainGuest', 'GenderMale', 'GenderFemale', 'GenderOther',
            'NationalityGuestCount', 'NationalityRoomCount', 'NationalityPercent', 'NationalityMaleCount',
            'NationalityFemaleCount', 'NationalityOtherCount', 'TotalGuest', 'TotalRoom', 'ViewAgency', 'ShowBooking',
        ];
        $fieldSchema = array_map(
            fn (string $name): array => [
                'name' => $name,
                'type' => in_array($name, $numeric, true) ? 'number' : 'string',
                'nullable' => true,
            ],
            $fields
        );
        $defaults = [
            'p_from_date' => now()->toDateString(),
            'p_to_date' => now()->toDateString(),
            'p_company_id' => null,
            'p_booking_id' => null,
            'p_view_agency' => 0,
            'p_show_booking' => 0,
            'p_show_note' => 0,
        ];
        $ui = [
            ['name' => 'p_view_agency', 'label' => 'Cách xem', 'control' => 'radio', 'default' => 0, 'required' => true, 'options' => [['value' => 0, 'label' => 'Xem theo nhóm'], ['value' => 1, 'label' => 'Xem bởi đại lý du lịch']]],
            ['name' => 'p_from_date', 'label' => 'Ngày', 'control' => 'date-range', 'range_end_parameter' => 'p_to_date', 'default' => '$today', 'required' => true, 'options' => []],
            ['name' => 'p_to_date', 'label' => 'Đến ngày', 'control' => 'hidden', 'default' => '$today', 'required' => true, 'options' => []],
            ['name' => 'p_company_id', 'label' => 'Công ty/Đại lý', 'control' => 'hidden', 'default' => '', 'required' => false, 'options' => []],
            ['name' => 'p_booking_id', 'label' => 'Lọc theo đăng ký', 'control' => 'select', 'default' => '', 'required' => false, 'options' => [], 'options_source' => 'bookings'],
            ['name' => 'p_show_booking', 'label' => 'Hiển thị đăng ký', 'control' => 'checkbox', 'default' => false, 'required' => false, 'options' => []],
            ['name' => 'p_show_note', 'label' => 'Hiển thị ghi chú', 'control' => 'checkbox', 'default' => false, 'required' => false, 'options' => []],
        ];

        DB::table('report_data_sources')->updateOrInsert(['code' => self::SOURCE], [
            'name' => 'Dữ liệu danh sách khách ở',
            'description' => 'Danh sách khách ở theo legacy sp_285 và nhánh RM/room-night của func_054.',
            'source_type' => 'procedure',
            'schema_name' => $database,
            'object_name' => 'rpt_inhouse_guests',
            'parameter_schema' => json_encode($parameters, JSON_UNESCAPED_UNICODE),
            'field_schema' => json_encode($fieldSchema, JSON_UNESCAPED_UNICODE),
            'sample_parameters' => json_encode($defaults),
            'max_rows' => 10000,
            'is_active' => true,
            'last_discovered_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $sourceId = DB::table('report_data_sources')->where('code', self::SOURCE)->value('id');
        DB::table('templates')->updateOrInsert(['report' => self::TEMPLATE], [
            'group' => 'Báo cáo khách',
            'name' => 'Báo cáo danh sách khách ở - Mẫu chuẩn',
            'report_data_source_id' => $sourceId,
            'parameter_defaults' => json_encode($defaults),
            'page_size' => 'A4',
            'page_orientation' => 'portrait',
            'margin_top' => 6,
            'margin_bottom' => 6,
            'margin_left' => 5,
            'margin_right' => 5,
            'content_json' => json_encode(['header' => [], 'detail' => [], 'footer' => []]),
            'content_html' => '<h1>BÁO CÁO DANH SÁCH KHÁCH Ở</h1>',
            'css' => '',
            'is_default' => false,
            'version' => '1.0',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        (require database_path('report_templates/inhouse_guests_reference.php'))->apply();

        $templateId = DB::table('templates')->where('report', self::TEMPLATE)->value('id');
        DB::table('report_definitions')->updateOrInsert(['code' => self::REPORT], [
            'name' => 'Báo cáo danh sách khách ở',
            'group' => 'Báo cáo khách',
            'description' => 'Danh sách khách theo từng ngày lưu trú và tổng hợp quốc tịch/giới tính.',
            'report_data_source_id' => $sourceId,
            'parameter_ui_schema' => json_encode($ui, JSON_UNESCAPED_UNICODE),
            'sort_order' => 35,
            'is_active' => true,
            'show_in_menu' => true,
            'menu_locations' => json_encode(['reservation', 'frontdesk']),
            'menu_top_order' => 20,
            'menu_group_order' => 20,
            'menu_item_order' => 35,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $reportId = DB::table('report_definitions')->where('code', self::REPORT)->value('id');
        DB::table('report_definition_template')->updateOrInsert(
            ['report_definition_id' => $reportId, 'template_id' => $templateId],
            ['is_default' => true, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now]
        );
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $reportId = DB::table('report_definitions')->where('code', self::REPORT)->value('id');
        $templateId = DB::table('templates')->where('report', self::TEMPLATE)->value('id');

        if ($reportId) {
            DB::table('report_definition_template')->where('report_definition_id', $reportId)->delete();
            DB::table('report_definitions')->where('id', $reportId)->delete();
        }
        if ($templateId) {
            DB::table('templates')->where('id', $templateId)->delete();
        }

        DB::table('report_data_sources')->where('code', self::SOURCE)->delete();
        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_inhouse_guests');
    }
};
