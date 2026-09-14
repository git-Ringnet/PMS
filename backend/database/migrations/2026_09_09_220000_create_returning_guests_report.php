<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SOURCE = 'RETURNING_GUESTS';
    private const REPORT = 'RETURNING_GUESTS';
    private const TEMPLATE = 'RETURNING_GUESTS_STANDARD';

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_returning_guests');
        DB::unprepared(<<<'SQL'
CREATE PROCEDURE rpt_returning_guests(
    IN p_from_date DATE,
    IN p_to_date DATE,
    IN p_filter_by_stay TINYINT
)
READS SQL DATA
BEGIN
    WITH base_rows AS (
        SELECT
            g.passport_number AS passport,
            g.full_name AS guest_name,
            b.id AS booking_id,
            br.id AS rental_room_id,
            br.room_number,
            rc.code AS room_type,
            br.rate,
            br.arrival_date,
            br.departure_date,
            c.name AS company,
            CONCAT(b.id, '|', br.arrival_date, '|', br.departure_date) AS visit_key
        FROM booking_room_guests AS brg
        INNER JOIN guests AS g ON g.id = brg.guest_id
        INNER JOIN booking_rooms AS br ON br.id = brg.booking_room_id
        INNER JOIN bookings AS b ON b.id = br.booking_id
        INNER JOIN registration_statuses AS rs ON rs.id = b.registration_status_id
            AND rs.is_availability = 1
        LEFT JOIN rooms AS r ON r.room_number = br.room_number
        LEFT JOIN room_classes AS rc ON rc.id = br.room_class_id
        LEFT JOIN companies AS c ON c.id = b.company_id
        WHERE b.deleted_at IS NULL
          AND br.deleted_at IS NULL
          AND NULLIF(TRIM(g.passport_number), '') IS NOT NULL
          AND COALESCE(r.is_internal, 0) = 0
          AND (br.room_number IS NULL OR br.room_number NOT LIKE '0%')
    ),
    returning_passports AS (
        SELECT passport
        FROM base_rows
        GROUP BY passport
        HAVING COUNT(DISTINCT booking_id) > 1
    ),
    eligible_passports AS (
        SELECT DISTINCT b.passport
        FROM base_rows AS b
        INNER JOIN returning_passports AS rp ON rp.passport = b.passport
        WHERE b.arrival_date <= p_to_date
          AND b.departure_date >= p_from_date
    ),
    filtered_rows AS (
        SELECT b.*
        FROM base_rows AS b
        INNER JOIN eligible_passports AS ep ON ep.passport = b.passport
        WHERE p_filter_by_stay = 0
           OR (b.arrival_date <= p_to_date AND b.departure_date >= p_from_date)
    )
    SELECT
        DENSE_RANK() OVER (ORDER BY fr.passport) AS STT,
        fr.passport AS Passport,
        fr.guest_name AS GuestName,
        CONCAT(COALESCE(hs.prefix_booking_id, ''), fr.booking_id) AS BookingId,
        fr.room_number AS Room,
        fr.room_type AS RoomType,
        fr.rate AS Rate,
        DATE_FORMAT(fr.arrival_date, '%d-%m-%Y') AS ArrivalDate,
        DATE_FORMAT(fr.departure_date, '%d-%m-%Y') AS DepartureDate,
        fr.company AS Company,
        fr.visit_key AS VisitKey
    FROM filtered_rows AS fr
    LEFT JOIN hotel_settings AS hs ON hs.id = (SELECT MIN(id) FROM hotel_settings)
    ORDER BY fr.passport, fr.arrival_date, fr.departure_date, fr.booking_id, fr.room_number;
END
SQL);

        $now = now();
        $database = DB::connection()->getDatabaseName();
        $parameters = [
            ['name' => 'p_from_date', 'mode' => 'IN', 'data_type' => 'date', 'database_type' => 'date', 'position' => 1, 'required' => true],
            ['name' => 'p_to_date', 'mode' => 'IN', 'data_type' => 'date', 'database_type' => 'date', 'position' => 2, 'required' => true],
            ['name' => 'p_filter_by_stay', 'mode' => 'IN', 'data_type' => 'tinyint', 'database_type' => 'tinyint', 'position' => 3, 'required' => true],
        ];
        $fields = collect(['STT', 'Passport', 'GuestName', 'BookingId', 'Room', 'RoomType', 'Rate', 'ArrivalDate', 'DepartureDate', 'Company', 'VisitKey'])
            ->map(fn (string $name) => [
                'name' => $name,
                'type' => in_array($name, ['STT', 'Rate'], true) ? 'number' : 'string',
                'nullable' => ! in_array($name, ['STT', 'Passport', 'GuestName', 'BookingId', 'ArrivalDate', 'DepartureDate', 'VisitKey'], true),
            ])->all();
        $defaults = ['p_from_date' => now()->toDateString(), 'p_to_date' => now()->toDateString(), 'p_filter_by_stay' => 0];
        $ui = [
            ['name' => 'p_from_date', 'label' => 'Ngày ở', 'control' => 'date-range', 'range_end_parameter' => 'p_to_date', 'default' => '$today', 'required' => true],
            ['name' => 'p_to_date', 'label' => 'Đến ngày', 'control' => 'hidden', 'default' => '$today', 'required' => true],
            ['name' => 'p_filter_by_stay', 'label' => 'Lọc đăng ký theo giai đoạn ở', 'control' => 'checkbox', 'default' => false, 'required' => false],
        ];

        DB::table('report_data_sources')->updateOrInsert(['code' => self::SOURCE], [
            'name' => 'Dữ liệu báo cáo khách quay lại',
            'description' => 'Danh sách khách có nhiều lần lưu trú theo Passport.',
            'source_type' => 'procedure',
            'schema_name' => $database,
            'object_name' => 'rpt_returning_guests',
            'parameter_schema' => json_encode($parameters, JSON_UNESCAPED_UNICODE),
            'field_schema' => json_encode($fields, JSON_UNESCAPED_UNICODE),
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
            'name' => 'Báo cáo khách quay lại',
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
        ]);
        $templateId = DB::table('templates')->where('report', self::TEMPLATE)->value('id');

        DB::table('report_definitions')->updateOrInsert(['code' => self::REPORT], [
            'name' => 'Báo cáo khách quay lại',
            'group' => 'Báo cáo khách',
            'description' => 'Danh sách khách quay lại theo Passport và giai đoạn lưu trú.',
            'report_data_source_id' => $sourceId,
            'parameter_ui_schema' => json_encode($ui, JSON_UNESCAPED_UNICODE),
            'sort_order' => 37,
            'is_active' => true,
            'show_in_menu' => true,
            'menu_locations' => json_encode(['reservation', 'frontdesk']),
            'menu_top_order' => 20,
            'menu_group_order' => 20,
            'menu_item_order' => 37,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $reportId = DB::table('report_definitions')->where('code', self::REPORT)->value('id');
        DB::table('report_definition_template')->updateOrInsert(
            ['report_definition_id' => $reportId, 'template_id' => $templateId],
            ['is_default' => true, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now]
        );

        (require database_path('report_templates/returning_guests_reference.php'))->apply();
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
        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_returning_guests');
    }
};
