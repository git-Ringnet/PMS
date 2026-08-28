<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SOURCE_CODE = 'DEPARTING_ROOMS';

    private const REPORT_CODE = 'DEPARTING_ROOMS';

    private const TEMPLATE_CODE = 'DEPARTING_ROOMS_STANDARD';

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $this->createProcedure();
        $this->seedReportConfiguration();

        $referenceTemplate = require database_path('report_templates/departing_rooms_reference.php');
        $referenceTemplate->apply();
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $reportId = DB::table('report_definitions')->where('code', self::REPORT_CODE)->value('id');
        $templateId = DB::table('templates')->where('report', self::TEMPLATE_CODE)->value('id');

        if ($reportId) {
            DB::table('report_definition_template')->where('report_definition_id', $reportId)->delete();
            DB::table('report_definitions')->where('id', $reportId)->delete();
        }
        if ($templateId) {
            DB::table('templates')->where('id', $templateId)->delete();
        }

        DB::table('report_data_sources')->where('code', self::SOURCE_CODE)->delete();
        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_departing_rooms');
    }

    private function createProcedure(): void
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_departing_rooms');
        DB::unprepared(<<<'SQL'
CREATE PROCEDURE rpt_departing_rooms(
    IN p_from_date DATE,
    IN p_to_date DATE,
    IN p_room_class_id BIGINT,
    IN p_registration_status_id BIGINT,
    IN p_area VARCHAR(100),
    IN p_company_id BIGINT,
    IN p_booking_id BIGINT,
    IN p_show_main_guest TINYINT,
    IN p_show_room_rate TINYINT,
    IN p_show_services_amount TINYINT
)
READS SQL DATA
BEGIN
    SELECT
        base.*,
        CASE
            WHEN SUM(base.RoomQuantity) OVER () = 0 THEN 0
            ELSE ROUND(
                SUM(base.RoomQuantity) OVER (PARTITION BY base.SummaryRoomTypeCode)
                * 100 / SUM(base.RoomQuantity) OVER (),
                2
            )
        END AS RoomTypePercent
    FROM (
        SELECT
            DATE_FORMAT(br.departure_date, '%d-%m-%Y') AS DepartureDateGroup,
            CONCAT(b.id, ' - ', b.booking_name) AS Booking,
            b.id AS BookingId,
            b.id AS RegisterId,
            br.id AS RentalRoomId,
            CASE WHEN brg.is_primary = 1 THEN COALESCE(br.room_number, '') ELSE NULL END AS Room,
            CASE WHEN brg.is_primary = 1 THEN rc.code ELSE NULL END AS RoomTypeCode,
            CASE WHEN brg.is_primary = 1 THEN rc.name ELSE NULL END AS RoomType,
            rc.code AS SummaryRoomTypeCode,
            CASE WHEN brg.is_primary = 1 THEN orc.name ELSE NULL END AS RoomTypeOriginal,
            TRIM(CONCAT_WS(' ', NULLIF(g.title, ''), g.full_name)) AS GuestName,
            CONCAT(
                DATE_FORMAT(br.arrival_date, '%d/%m/%Y'),
                IF(br.arrival_time IS NULL, '', CONCAT(' - ', TIME_FORMAT(br.arrival_time, '%H:%i')))
            ) AS ArrivalDate,
            CONCAT(
                DATE_FORMAT(br.departure_date, '%d/%m/%Y'),
                IF(br.departure_time IS NULL, '', CONCAT(' - ', TIME_FORMAT(br.departure_time, '%H:%i')))
            ) AS DepartureDate,
            CASE WHEN brg.is_primary = 1 THEN br.extra_bed_qty ELSE NULL END AS ExtraBed,
            CASE
                WHEN brg.is_primary = 1 AND COALESCE(p_show_room_rate, 1) = 1
                    THEN br.extra_bed_rate
                ELSE NULL
            END AS ExtraBedRate,
            CASE WHEN brg.is_primary = 1 THEN br.ActutalNumOfDays ELSE NULL END AS RoomNight,
            CASE WHEN brg.is_primary = 1 THEN br.adults ELSE NULL END AS Adult,
            CASE WHEN brg.is_primary = 1 THEN br.babies ELSE NULL END AS Infant,
            CASE WHEN brg.is_primary = 1 THEN br.children_qty ELSE NULL END AS Child,
            CASE
                WHEN brg.is_primary = 1
                    THEN CONCAT(br.adults, ' / ', br.babies, ' / ', br.children_qty)
                ELSE NULL
            END AS AdultInfantChild,
            CASE
                WHEN brg.is_primary = 1 AND COALESCE(p_show_room_rate, 1) = 1 THEN br.rate
                ELSE NULL
            END AS Rate,
            CASE
                WHEN brg.is_primary = 1
                    AND COALESCE(p_show_room_rate, 1) = 1
                    AND COALESCE(p_show_services_amount, 1) = 1
                THEN (
                    SELECT COALESCE(SUM(sb.Amount), 0)
                    FROM service_bills AS sb
                    WHERE sb.RentalRoomId1 = br.id
                      AND sb.Edit = 0
                      AND sb.Status = 1
                      AND sb.ServiceId <> 'RM'
                )
                ELSE NULL
            END AS AmountServices,
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
            b.special_requests AS SpecialDangKy,
            CASE WHEN brg.is_primary = 1 THEN 1 ELSE 0 END AS RoomQuantity
        FROM booking_rooms AS br
        INNER JOIN bookings AS b ON b.id = br.booking_id AND b.deleted_at IS NULL
        INNER JOIN booking_room_guests AS brg
            ON brg.booking_room_id = br.id
           AND brg.status IN (0, 1, 2)
           AND (brg.actual_checkout_date IS NULL OR brg.actual_checkout_date >= br.departure_date)
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
          AND br.departure_date BETWEEN p_from_date AND p_to_date
          AND (COALESCE(p_show_main_guest, 1) = 0 OR brg.is_primary = 1)
          AND (p_area IS NULL OR p_area = '' OR r.area = p_area)
          AND (p_company_id IS NULL OR b.company_id = p_company_id)
          AND (p_booking_id IS NULL OR b.id = p_booking_id)
          AND (p_room_class_id IS NULL OR br.room_class_id = p_room_class_id)
          AND (p_registration_status_id IS NULL OR b.registration_status_id = p_registration_status_id)
          AND COALESCE(r.is_internal, 0) = 0
          AND (br.room_number IS NULL OR br.room_number NOT LIKE '0%')

        UNION ALL

        SELECT
            DATE_FORMAT(br.departure_date, '%d-%m-%Y') AS DepartureDateGroup,
            CONCAT(b.id, ' - ', b.booking_name) AS Booking,
            b.id AS BookingId,
            b.id AS RegisterId,
            br.id AS RentalRoomId,
            NULL AS Room,
            NULL AS RoomTypeCode,
            NULL AS RoomType,
            rc.code AS SummaryRoomTypeCode,
            NULL AS RoomTypeOriginal,
            TRIM(CONCAT_WS(' ', NULLIF(bc.title, ''), bc.full_name)) AS GuestName,
            CONCAT(
                DATE_FORMAT(br.arrival_date, '%d/%m/%Y'),
                IF(br.arrival_time IS NULL, '', CONCAT(' - ', TIME_FORMAT(br.arrival_time, '%H:%i')))
            ) AS ArrivalDate,
            CONCAT(
                DATE_FORMAT(br.departure_date, '%d/%m/%Y'),
                IF(br.departure_time IS NULL, '', CONCAT(' - ', TIME_FORMAT(br.departure_time, '%H:%i')))
            ) AS DepartureDate,
            NULL AS ExtraBed,
            NULL AS ExtraBedRate,
            NULL AS RoomNight,
            NULL AS Adult,
            NULL AS Infant,
            NULL AS Child,
            NULL AS AdultInfantChild,
            NULL AS Rate,
            NULL AS AmountServices,
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
            b.special_requests AS SpecialDangKy,
            0 AS RoomQuantity
        FROM booking_rooms AS br
        INNER JOIN bookings AS b ON b.id = br.booking_id AND b.deleted_at IS NULL
        INNER JOIN booking_room_children AS brc
            ON brc.booking_room_id = br.id
           AND brc.status IN (0, 1, 2)
           AND (brc.actual_checkout_date IS NULL OR brc.actual_checkout_date >= br.departure_date)
        INNER JOIN booking_children AS bc ON bc.id = brc.booking_child_id
        INNER JOIN room_classes AS rc ON rc.id = br.room_class_id
        LEFT JOIN rooms AS r ON r.room_number = br.room_number
        LEFT JOIN companies AS c ON c.id = b.company_id
        LEFT JOIN registration_statuses AS rs ON rs.id = b.registration_status_id
        WHERE COALESCE(p_show_main_guest, 1) = 0
          AND br.deleted_at IS NULL
          AND br.status IN (0, 1, 2)
          AND b.status IN (0, 1, 2)
          AND (rs.id IS NULL OR rs.is_availability = 1)
          AND br.departure_date BETWEEN p_from_date AND p_to_date
          AND (p_area IS NULL OR p_area = '' OR r.area = p_area)
          AND (p_company_id IS NULL OR b.company_id = p_company_id)
          AND (p_booking_id IS NULL OR b.id = p_booking_id)
          AND (p_room_class_id IS NULL OR br.room_class_id = p_room_class_id)
          AND (p_registration_status_id IS NULL OR b.registration_status_id = p_registration_status_id)
          AND COALESCE(r.is_internal, 0) = 0
          AND (br.room_number IS NULL OR br.room_number NOT LIKE '0%')
    ) AS base
    ORDER BY base.DepartureDateGroup, base.CompanyId, base.BookingId,
        base.RoomOrder, base.RentalRoomId, base.IsMainGuest DESC, base.GuestName;
END
SQL);
    }

    private function seedReportConfiguration(): void
    {
        $database = DB::connection()->getDatabaseName();
        $now = now();
        $parameterSchema = [
            $this->parameter('p_from_date', 'date', 'date', 1),
            $this->parameter('p_to_date', 'date', 'date', 2),
            $this->parameter('p_room_class_id', 'bigint', 'bigint', 3),
            $this->parameter('p_registration_status_id', 'bigint', 'bigint', 4),
            $this->parameter('p_area', 'varchar', 'varchar(100)', 5),
            $this->parameter('p_company_id', 'bigint', 'bigint', 6),
            $this->parameter('p_booking_id', 'bigint', 'bigint', 7),
            $this->parameter('p_show_main_guest', 'tinyint', 'tinyint', 8),
            $this->parameter('p_show_room_rate', 'tinyint', 'tinyint', 9),
            $this->parameter('p_show_services_amount', 'tinyint', 'tinyint', 10),
        ];
        $numericFields = [
            'BookingId', 'RegisterId', 'ExtraBed', 'RoomNight', 'Adult', 'Infant', 'Child',
            'CompanyId', 'IsMainGuest', 'RoomOrder', 'RoomQuantity',
        ];
        $decimalFields = ['ExtraBedRate', 'Rate', 'AmountServices', 'RoomTypePercent'];
        $fieldSchema = collect([
            'DepartureDateGroup', 'Booking', 'BookingId', 'RegisterId', 'RentalRoomId', 'Room',
            'RoomTypeCode', 'RoomType', 'SummaryRoomTypeCode', 'RoomTypeOriginal', 'GuestName', 'ArrivalDate', 'DepartureDate',
            'ExtraBed', 'ExtraBedRate', 'RoomNight', 'Adult', 'Infant', 'Child', 'AdultInfantChild',
            'Rate', 'AmountServices', 'Special', 'CompanyId', 'Company', 'Note', 'IsMainGuest',
            'GuestType', 'BookingName', 'Breakfast', 'Phone', 'Gender', 'RoomOrder',
            'BookingStatusName', 'SpecialDangKy', 'RoomQuantity', 'RoomTypePercent',
        ])->map(fn (string $name) => [
            'name' => $name,
            'type' => in_array($name, $numericFields, true)
                ? 'integer'
                : (in_array($name, $decimalFields, true) ? 'number' : 'string'),
            'nullable' => ! in_array($name, [
                'DepartureDateGroup', 'Booking', 'BookingId', 'RegisterId', 'RentalRoomId',
                'GuestName', 'ArrivalDate', 'DepartureDate', 'RoomQuantity', 'RoomTypePercent',
            ], true),
        ])->all();

        $defaults = [
            'p_from_date' => now()->toDateString(),
            'p_to_date' => now()->toDateString(),
            'p_room_class_id' => null,
            'p_registration_status_id' => null,
            'p_area' => null,
            'p_company_id' => null,
            'p_booking_id' => null,
            'p_show_main_guest' => 1,
            'p_show_room_rate' => 0,
            'p_show_services_amount' => 0,
        ];

        DB::table('report_data_sources')->updateOrInsert(
            ['code' => self::SOURCE_CODE],
            [
                'name' => 'Dữ liệu báo cáo phòng đi',
                'description' => 'MySQL chuyển đổi từ ProVista sp_008, dùng dữ liệu đăng ký của PMS.',
                'source_type' => 'procedure',
                'schema_name' => $database,
                'object_name' => 'rpt_departing_rooms',
                'parameter_schema' => json_encode($parameterSchema, JSON_UNESCAPED_UNICODE),
                'field_schema' => json_encode($fieldSchema, JSON_UNESCAPED_UNICODE),
                'sample_parameters' => json_encode($defaults),
                'max_rows' => 2000,
                'is_active' => true,
                'last_discovered_at' => $now,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
        $sourceId = DB::table('report_data_sources')->where('code', self::SOURCE_CODE)->value('id');

        DB::table('templates')->updateOrInsert(
            ['report' => self::TEMPLATE_CODE],
            [
                'group' => 'Báo cáo phòng',
                'name' => 'Báo cáo phòng đi - Mẫu tham chiếu legacy',
                'report_data_source_id' => $sourceId,
                'parameter_defaults' => json_encode($defaults),
                'page_size' => 'A4',
                'page_orientation' => 'portrait',
                'margin_top' => 6,
                'margin_bottom' => 6,
                'margin_left' => 5,
                'margin_right' => 5,
                'content_json' => json_encode(['header' => [], 'detail' => [], 'footer' => []]),
                'content_html' => '<h1>BÁO CÁO PHÒNG ĐI</h1>',
                'css' => '',
                'is_default' => false,
                'version' => '1.0',
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
        $templateId = DB::table('templates')->where('report', self::TEMPLATE_CODE)->value('id');

        DB::table('report_definitions')->updateOrInsert(
            ['code' => self::REPORT_CODE],
            [
                'name' => 'Báo cáo phòng đi',
                'group' => 'Báo cáo phòng',
                'description' => 'Danh sách khách/phòng có ngày đi trong khoảng được chọn.',
                'report_data_source_id' => $sourceId,
                'parameter_ui_schema' => json_encode($this->parameterUiSchema(), JSON_UNESCAPED_UNICODE),
                'sort_order' => 20,
                'is_active' => true,
                'show_in_menu' => true,
                'menu_locations' => json_encode(['reservation', 'frontdesk']),
                'menu_top_order' => 20,
                'menu_group_order' => 10,
                'menu_item_order' => 20,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
        $reportId = DB::table('report_definitions')->where('code', self::REPORT_CODE)->value('id');

        DB::table('report_definition_template')->updateOrInsert(
            ['report_definition_id' => $reportId, 'template_id' => $templateId],
            ['is_default' => true, 'sort_order' => 0, 'updated_at' => $now, 'created_at' => $now]
        );
    }

    private function parameter(string $name, string $dataType, string $databaseType, int $position): array
    {
        return [
            'name' => $name,
            'mode' => 'IN',
            'data_type' => $dataType,
            'database_type' => $databaseType,
            'max_length' => $dataType === 'varchar' ? 100 : null,
            'numeric_precision' => null,
            'numeric_scale' => null,
            'position' => $position,
            'required' => true,
        ];
    }

    private function parameterUiSchema(): array
    {
        return [
            ['name' => 'p_from_date', 'label' => 'Chọn ngày', 'control' => 'date-range', 'range_end_parameter' => 'p_to_date', 'default' => '$today', 'required' => true, 'options' => []],
            ['name' => 'p_to_date', 'label' => 'Đến ngày', 'control' => 'hidden', 'default' => '$today', 'required' => true, 'options' => []],
            ['name' => 'p_area', 'label' => 'Chọn khu vực', 'control' => 'select', 'default' => '', 'required' => false, 'options' => [], 'options_source' => 'areas'],
            ['name' => 'p_company_id', 'label' => 'Chọn công ty', 'control' => 'select', 'default' => '', 'required' => false, 'options' => [], 'options_source' => 'companies'],
            ['name' => 'p_booking_id', 'label' => 'Chọn đăng ký', 'control' => 'select', 'default' => '', 'required' => false, 'options' => [], 'options_source' => 'bookings'],
            ['name' => 'p_show_main_guest', 'label' => 'Chỉ hiển thị khách chính', 'control' => 'checkbox', 'default' => true, 'required' => false, 'options' => []],
            ['name' => 'p_room_class_id', 'label' => 'Chọn loại phòng', 'control' => 'select', 'default' => '', 'required' => false, 'options' => [], 'options_source' => 'room-classes'],
            ['name' => 'p_registration_status_id', 'label' => 'Chọn tình trạng đăng ký', 'control' => 'select', 'default' => '', 'required' => false, 'options' => [], 'options_source' => 'registration-statuses'],
            ['name' => 'p_show_room_rate', 'label' => 'Room Rate', 'control' => 'checkbox', 'default' => false, 'required' => false, 'options' => []],
            ['name' => 'p_show_services_amount', 'label' => 'Services Amount', 'control' => 'checkbox', 'default' => false, 'required' => false, 'options' => []],
        ];
    }
};
