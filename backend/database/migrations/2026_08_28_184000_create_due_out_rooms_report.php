<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SOURCE = 'DUE_OUT_ROOMS';

    private const REPORT = 'DUE_OUT_ROOMS';

    private const TEMPLATE = 'DUE_OUT_ROOMS_STANDARD';

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $this->createProcedure();
        $this->seedConfiguration();

        (require database_path('report_templates/due_out_rooms_reference.php'))->apply();
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
        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_due_out_rooms');
    }

    private function createProcedure(): void
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_due_out_rooms');
        DB::unprepared(<<<'SQL'
CREATE PROCEDURE rpt_due_out_rooms(
    IN p_from_date DATE,
    IN p_to_date DATE,
    IN p_room_class_id BIGINT,
    IN p_registration_status_id BIGINT,
    IN p_area VARCHAR(100),
    IN p_company_id BIGINT,
    IN p_booking_id BIGINT,
    IN p_show_main_guest TINYINT
)
READS SQL DATA
BEGIN
    SELECT
        CASE
            WHEN base.RoomQuantity = 1 THEN SUM(base.RoomQuantity) OVER (
                PARTITION BY base.DepartureDateSort
                ORDER BY base.RoomOrder, base.RentalRoomId, base.IsMainGuest DESC, base.GuestName
            )
            ELSE NULL
        END AS STT,
        base.*
    FROM (
        SELECT
            br.departure_date AS DepartureDateSort,
            DATE_FORMAT(br.departure_date, '%d/%m/%Y') AS DepartureDateGroup,
            1 AS PeriodGroup,
            CASE WHEN brg.is_primary = 1 THEN b.id ELSE NULL END AS BookingId,
            br.id AS RentalRoomId,
            CASE WHEN brg.is_primary = 1 THEN COALESCE(br.room_number, '') ELSE NULL END AS Room,
            CASE WHEN brg.is_primary = 1 THEN rc.code ELSE NULL END AS RoomType,
            TRIM(CONCAT_WS(' ', NULLIF(g.title, ''), g.full_name)) AS GuestName,
            CONCAT(
                DATE_FORMAT(br.arrival_date, '%d/%m/%Y'),
                IF(br.arrival_time IS NULL, '', CONCAT(' - ', TIME_FORMAT(br.arrival_time, '%H:%i')))
            ) AS ArrivalDate,
            CONCAT(
                DATE_FORMAT(br.departure_date, '%d/%m/%Y'),
                IF(br.departure_time IS NULL, '', CONCAT(' - ', TIME_FORMAT(br.departure_time, '%H:%i')))
            ) AS DepartureDate,
            CASE WHEN brg.is_primary = 1 THEN br.ActutalNumOfDays ELSE NULL END AS RoomNight,
            CASE WHEN brg.is_primary = 1 THEN br.extra_bed_qty ELSE NULL END AS ExtraBed,
            CASE WHEN brg.is_primary = 1 THEN br.adults ELSE NULL END AS Adult,
            CASE WHEN brg.is_primary = 1 THEN br.children_qty ELSE NULL END AS Child,
            CASE
                WHEN brg.is_primary = 1 THEN CONCAT(br.adults, ' / ', br.children_qty)
                ELSE NULL
            END AS AdultChild,
            b.company_id AS CompanyId,
            c.name AS Company,
            COALESCE(br.note, b.note) AS Note,
            brg.is_primary AS IsMainGuest,
            r.orders AS RoomOrder,
            CASE WHEN brg.is_primary = 1 THEN 1 ELSE 0 END AS RoomQuantity
        FROM booking_rooms AS br
        INNER JOIN bookings AS b ON b.id = br.booking_id AND b.deleted_at IS NULL
        INNER JOIN booking_room_guests AS brg
            ON brg.booking_room_id = br.id
           AND brg.status IN (0, 1, 2)
           AND (brg.actual_checkout_date IS NULL OR brg.actual_checkout_date >= br.departure_date)
        INNER JOIN guests AS g ON g.id = brg.guest_id
        INNER JOIN room_classes AS rc ON rc.id = br.room_class_id
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
            br.departure_date AS DepartureDateSort,
            DATE_FORMAT(br.departure_date, '%d/%m/%Y') AS DepartureDateGroup,
            1 AS PeriodGroup,
            NULL AS BookingId,
            br.id AS RentalRoomId,
            NULL AS Room,
            NULL AS RoomType,
            TRIM(CONCAT_WS(' ', NULLIF(bc.title, ''), bc.full_name)) AS GuestName,
            CONCAT(
                DATE_FORMAT(br.arrival_date, '%d/%m/%Y'),
                IF(br.arrival_time IS NULL, '', CONCAT(' - ', TIME_FORMAT(br.arrival_time, '%H:%i')))
            ) AS ArrivalDate,
            CONCAT(
                DATE_FORMAT(br.departure_date, '%d/%m/%Y'),
                IF(br.departure_time IS NULL, '', CONCAT(' - ', TIME_FORMAT(br.departure_time, '%H:%i')))
            ) AS DepartureDate,
            NULL AS RoomNight,
            NULL AS ExtraBed,
            NULL AS Adult,
            NULL AS Child,
            NULL AS AdultChild,
            b.company_id AS CompanyId,
            c.name AS Company,
            COALESCE(br.note, b.note) AS Note,
            -1 AS IsMainGuest,
            r.orders AS RoomOrder,
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
          AND EXISTS (
              SELECT 1
              FROM booking_room_guests AS eligible_guest
              WHERE eligible_guest.booking_room_id = br.id
                AND eligible_guest.status IN (0, 1, 2)
                AND (eligible_guest.actual_checkout_date IS NULL OR eligible_guest.actual_checkout_date >= br.departure_date)
          )
    ) AS base
    ORDER BY base.DepartureDateSort, base.RoomOrder, base.RentalRoomId,
        base.IsMainGuest DESC, base.GuestName;
END
SQL);
    }

    private function seedConfiguration(): void
    {
        $now = now();
        $database = DB::connection()->getDatabaseName();
        $parameters = collect([
            ['p_from_date', 'date', 'date'],
            ['p_to_date', 'date', 'date'],
            ['p_room_class_id', 'bigint', 'bigint'],
            ['p_registration_status_id', 'bigint', 'bigint'],
            ['p_area', 'varchar', 'varchar(100)'],
            ['p_company_id', 'bigint', 'bigint'],
            ['p_booking_id', 'bigint', 'bigint'],
            ['p_show_main_guest', 'tinyint', 'tinyint'],
        ])->values()->map(fn (array $parameter, int $index) => [
            'name' => $parameter[0],
            'mode' => 'IN',
            'data_type' => $parameter[1],
            'database_type' => $parameter[2],
            'position' => $index + 1,
            'required' => true,
        ])->all();
        $numericFields = [
            'STT', 'PeriodGroup', 'BookingId', 'RoomNight', 'ExtraBed',
            'Adult', 'Child', 'CompanyId', 'IsMainGuest', 'RoomOrder', 'RoomQuantity',
        ];
        $fields = collect([
            'STT', 'DepartureDateSort', 'DepartureDateGroup', 'PeriodGroup', 'BookingId',
            'RentalRoomId', 'Room', 'RoomType', 'GuestName', 'ArrivalDate', 'DepartureDate',
            'RoomNight', 'ExtraBed', 'Adult', 'Child', 'AdultChild', 'CompanyId', 'Company',
            'Note', 'IsMainGuest', 'RoomOrder', 'RoomQuantity',
        ])->map(fn (string $name) => [
            'name' => $name,
            'type' => in_array($name, $numericFields, true) ? 'integer' : 'string',
            'nullable' => ! in_array($name, ['DepartureDateGroup', 'PeriodGroup', 'RentalRoomId', 'GuestName', 'ArrivalDate', 'DepartureDate', 'RoomQuantity'], true),
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
        ];
        $ui = [
            ['name' => 'p_from_date', 'label' => 'Chọn ngày', 'control' => 'date-range', 'range_end_parameter' => 'p_to_date', 'default' => '$today', 'required' => true],
            ['name' => 'p_to_date', 'label' => 'Đến ngày', 'control' => 'hidden', 'default' => '$today', 'required' => true],
            ['name' => 'p_room_class_id', 'label' => 'Chọn loại phòng', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'room-classes'],
            ['name' => 'p_registration_status_id', 'label' => 'Chọn tình trạng đăng ký', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'registration-statuses'],
            ['name' => 'p_area', 'label' => 'Chọn khu vực', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'areas'],
            ['name' => 'p_company_id', 'label' => 'Chọn công ty', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'companies'],
            ['name' => 'p_booking_id', 'label' => 'Chọn đăng ký', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'bookings'],
            ['name' => 'p_show_main_guest', 'label' => 'Chỉ hiển thị khách chính', 'control' => 'hidden', 'default' => true, 'required' => false],
        ];

        DB::table('report_data_sources')->updateOrInsert(
            ['code' => self::SOURCE],
            [
                'name' => 'Dữ liệu báo cáo phòng Due Out',
                'description' => 'MySQL chuyển đổi từ ProVista sp_008 cho mẫu danh sách Due Out.',
                'source_type' => 'procedure',
                'schema_name' => $database,
                'object_name' => 'rpt_due_out_rooms',
                'parameter_schema' => json_encode($parameters, JSON_UNESCAPED_UNICODE),
                'field_schema' => json_encode($fields, JSON_UNESCAPED_UNICODE),
                'sample_parameters' => json_encode($defaults),
                'max_rows' => 5000,
                'is_active' => true,
                'last_discovered_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
        $sourceId = DB::table('report_data_sources')->where('code', self::SOURCE)->value('id');

        DB::table('templates')->updateOrInsert(
            ['report' => self::TEMPLATE],
            [
                'group' => 'Báo cáo phòng',
                'name' => 'Danh sách phòng Due Out - Mẫu tham chiếu legacy',
                'report_data_source_id' => $sourceId,
                'parameter_defaults' => json_encode($defaults),
                'page_size' => 'A4',
                'page_orientation' => 'landscape',
                'margin_top' => 6,
                'margin_bottom' => 6,
                'margin_left' => 5,
                'margin_right' => 5,
                'content_json' => json_encode(['header' => [], 'detail' => [], 'footer' => []]),
                'content_html' => '',
                'css' => '',
                'is_default' => false,
                'version' => '1.0',
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
        $templateId = DB::table('templates')->where('report', self::TEMPLATE)->value('id');

        DB::table('report_definitions')->updateOrInsert(
            ['code' => self::REPORT],
            [
                'name' => 'Danh sách phòng Due Out',
                'group' => 'Báo cáo phòng',
                'description' => 'Danh sách phòng có ngày đi trong khoảng chọn theo legacy sp_008.',
                'report_data_source_id' => $sourceId,
                'parameter_ui_schema' => json_encode($ui, JSON_UNESCAPED_UNICODE),
                'sort_order' => 22,
                'is_active' => true,
                'show_in_menu' => true,
                'menu_locations' => json_encode(['reservation', 'frontdesk']),
                'menu_top_order' => 20,
                'menu_group_order' => 10,
                'menu_item_order' => 22,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
        $reportId = DB::table('report_definitions')->where('code', self::REPORT)->value('id');

        DB::table('report_definition_template')->updateOrInsert(
            ['report_definition_id' => $reportId, 'template_id' => $templateId],
            ['is_default' => true, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now]
        );
    }
};
