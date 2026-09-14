<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SOURCE = 'EARLY_CHECKOUT_ROOMS';
    private const REPORT = 'EARLY_CHECKOUT_ROOMS';
    private const TEMPLATE = 'EARLY_CHECKOUT_ROOMS_STANDARD';

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_early_checkout_rooms');
        DB::unprepared(<<<'SQL'
CREATE PROCEDURE rpt_early_checkout_rooms(
    IN p_from_date DATE,
    IN p_to_date DATE
)
READS SQL DATA
BEGIN
    SELECT
        ROW_NUMBER() OVER (ORDER BY br.CheckoutDate, r.orders, br.id) AS STT,
        DATE_FORMAT(br.CheckoutDate, '%d/%m/%Y') AS CheckoutDateGroup,
        1 AS PeriodGroup,
        br.id AS RentalRoomId,
        CONCAT(COALESCE(hs.prefix_booking_id, ''), b.id) AS BookingId,
        br.room_number AS Room,
        rc.code AS RoomType,
        TRIM(CONCAT_WS(' ', NULLIF(g.title, ''), g.full_name)) AS GuestName,
        DATE_FORMAT(br.arrival_date, '%d/%m/%Y') AS ArrivalDate,
        DATE_FORMAT(br.planned_departure_date, '%d/%m/%Y') AS PlannedDepartureDate,
        DATE_FORMAT(br.CheckoutDate, '%d/%m/%Y') AS ActualCheckoutDate,
        DATEDIFF(br.planned_departure_date, br.CheckoutDate) AS EarlyCheckoutDays,
        br.check_out_user AS CheckoutUser,
        COALESCE(br.note, b.note) AS Note,
        r.orders AS RoomOrder
    FROM booking_rooms AS br
    INNER JOIN bookings AS b
        ON b.id = br.booking_id
       AND b.deleted_at IS NULL
    INNER JOIN booking_room_guests AS brg
        ON brg.booking_room_id = br.id
       AND brg.is_primary = 1
       AND brg.status = 2
    INNER JOIN guests AS g ON g.id = brg.guest_id
    INNER JOIN room_classes AS rc ON rc.id = br.room_class_id
    LEFT JOIN rooms AS r ON r.room_number = br.room_number
    LEFT JOIN hotel_settings AS hs ON hs.id = (SELECT MIN(id) FROM hotel_settings)
    WHERE br.deleted_at IS NULL
      AND br.status = 2
      AND br.CheckoutDate BETWEEN p_from_date AND p_to_date
      AND br.planned_departure_date IS NOT NULL
      AND br.CheckoutDate < br.planned_departure_date
      AND COALESCE(r.is_internal, 0) = 0
      AND (br.room_number IS NULL OR br.room_number NOT LIKE '0%')
    ORDER BY br.CheckoutDate, r.orders, br.id;
END
SQL);

        $now = now();
        $database = DB::connection()->getDatabaseName();
        $parameters = [
            ['name' => 'p_from_date', 'mode' => 'IN', 'data_type' => 'date', 'database_type' => 'date', 'position' => 1, 'required' => true],
            ['name' => 'p_to_date', 'mode' => 'IN', 'data_type' => 'date', 'database_type' => 'date', 'position' => 2, 'required' => true],
        ];
        $fields = collect([
            'STT', 'CheckoutDateGroup', 'PeriodGroup', 'RentalRoomId', 'BookingId', 'Room', 'RoomType',
            'GuestName', 'ArrivalDate', 'PlannedDepartureDate', 'ActualCheckoutDate',
            'EarlyCheckoutDays', 'CheckoutUser', 'Note', 'RoomOrder',
        ])->map(fn (string $name) => [
            'name' => $name,
            'type' => in_array($name, ['STT', 'PeriodGroup', 'RentalRoomId', 'EarlyCheckoutDays', 'RoomOrder'], true) ? 'integer' : 'string',
            'nullable' => ! in_array($name, ['STT', 'CheckoutDateGroup', 'PeriodGroup', 'RentalRoomId', 'BookingId', 'Room', 'RoomType', 'GuestName', 'ActualCheckoutDate', 'EarlyCheckoutDays'], true),
        ])->all();
        $defaults = ['p_from_date' => now()->toDateString(), 'p_to_date' => now()->toDateString()];
        $ui = [
            ['name' => 'p_from_date', 'label' => 'Ngày checkout', 'control' => 'date-range', 'range_end_parameter' => 'p_to_date', 'default' => '$today', 'required' => true],
            ['name' => 'p_to_date', 'label' => 'Đến ngày', 'control' => 'hidden', 'default' => '$today', 'required' => true],
        ];

        DB::table('report_data_sources')->updateOrInsert(['code' => self::SOURCE], [
            'name' => 'Dữ liệu báo cáo phòng checkout sớm',
            'description' => 'Phòng có ngày checkout thực tế sớm hơn ngày đi dự kiến ban đầu.',
            'source_type' => 'procedure',
            'schema_name' => $database,
            'object_name' => 'rpt_early_checkout_rooms',
            'parameter_schema' => json_encode($parameters, JSON_UNESCAPED_UNICODE),
            'field_schema' => json_encode($fields, JSON_UNESCAPED_UNICODE),
            'sample_parameters' => json_encode($defaults),
            'max_rows' => 5000,
            'is_active' => true,
            'last_discovered_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $sourceId = DB::table('report_data_sources')->where('code', self::SOURCE)->value('id');

        DB::table('templates')->updateOrInsert(['report' => self::TEMPLATE], [
            'group' => 'Báo cáo phòng',
            'name' => 'Báo cáo phòng checkout sớm',
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
            'name' => 'Báo cáo phòng checkout sớm',
            'group' => 'Báo cáo phòng',
            'description' => 'Thống kê các phòng checkout sớm theo ngày checkout thực tế.',
            'report_data_source_id' => $sourceId,
            'parameter_ui_schema' => json_encode($ui, JSON_UNESCAPED_UNICODE),
            'sort_order' => 27,
            'is_active' => true,
            'show_in_menu' => true,
            'menu_locations' => json_encode(['reservation', 'frontdesk']),
            'menu_top_order' => 20,
            'menu_group_order' => 10,
            'menu_item_order' => 27,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $reportId = DB::table('report_definitions')->where('code', self::REPORT)->value('id');
        DB::table('report_definition_template')->updateOrInsert(
            ['report_definition_id' => $reportId, 'template_id' => $templateId],
            ['is_default' => true, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now]
        );

        (require database_path('report_templates/early_checkout_rooms_reference.php'))->apply();
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
        if ($templateId) DB::table('templates')->where('id', $templateId)->delete();
        DB::table('report_data_sources')->where('code', self::SOURCE)->delete();
        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_early_checkout_rooms');
    }
};
