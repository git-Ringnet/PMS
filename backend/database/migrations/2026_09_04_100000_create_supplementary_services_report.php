<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SOURCE = 'SUPPLEMENTARY_SERVICES';
    private const REPORT = 'SUPPLEMENTARY_SERVICES';
    private const TEMPLATE = 'SUPPLEMENTARY_SERVICES_STANDARD';

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_supplementary_services');
        DB::unprepared(<<<'SQL'
CREATE PROCEDURE rpt_supplementary_services(
    IN p_from_date DATE,
    IN p_to_date DATE,
    IN p_service_codes VARCHAR(2000)
)
READS SQL DATA
BEGIN
    SELECT
        ROW_NUMBER() OVER (ORDER BY brs.service_date, COALESCE(brs.service_name, hsrv.name, brs.service_code), br.room_number, b.id, brs.id) AS STT,
        CONCAT(COALESCE(hs.prefix_booking_id, 'GAL'), b.id) AS BookingCode,
        br.room_number AS Room,
        TRIM(CONCAT_WS(' ', NULLIF(g.title, ''), g.full_name)) AS Guest,
        DATE_FORMAT(brs.service_date, '%d/%m/%Y') AS ServiceDate,
        COALESCE(NULLIF(brs.service_name, ''), NULLIF(hsrv.name, ''), brs.service_code) AS ServiceName,
        brs.service_code AS ServiceCode,
        brs.quantity AS Quantity,
        brs.rate AS Rate,
        brs.total_amount AS Total,
        SUM(brs.quantity) OVER (PARTITION BY COALESCE(NULLIF(brs.service_name, ''), NULLIF(hsrv.name, ''), brs.service_code)) AS GroupQuantity,
        SUM(brs.total_amount) OVER (PARTITION BY COALESCE(NULLIF(brs.service_name, ''), NULLIF(hsrv.name, ''), brs.service_code)) AS GroupTotal,
        brs.is_room AS IsRoom,
        brs.is_posted AS IsPosted,
        brs.id AS ServiceId
    FROM booking_room_services AS brs
    INNER JOIN booking_rooms AS br ON br.id = brs.booking_room_id AND br.deleted_at IS NULL
    INNER JOIN bookings AS b ON b.id = br.booking_id AND b.deleted_at IS NULL
    LEFT JOIN booking_room_guests AS brg ON brg.booking_room_id = br.id AND brg.is_primary = 1 AND brg.status <> 3
    LEFT JOIN guests AS g ON g.id = brg.guest_id
    LEFT JOIN hotel_services AS hsrv ON hsrv.code = brs.service_code
    LEFT JOIN hotel_settings AS hs ON hs.id = (SELECT MIN(id) FROM hotel_settings)
    WHERE brs.deleted_at IS NULL
      AND brs.service_code <> 'RM'
      AND brs.service_date >= p_from_date
      AND brs.service_date < DATE_ADD(p_to_date, INTERVAL 1 DAY)
      AND (p_service_codes IS NULL OR p_service_codes = '' OR FIND_IN_SET(brs.service_code, REPLACE(p_service_codes, ' ', '')) > 0)
    ORDER BY brs.service_date, ServiceName, br.room_number, b.id, brs.id;
END
SQL);

        $now = now();
        $database = DB::connection()->getDatabaseName();
        $parameters = [
            ['name' => 'p_from_date', 'mode' => 'IN', 'data_type' => 'date', 'database_type' => 'date', 'position' => 1, 'required' => true],
            ['name' => 'p_to_date', 'mode' => 'IN', 'data_type' => 'date', 'database_type' => 'date', 'position' => 2, 'required' => true],
            ['name' => 'p_service_codes', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(2000)', 'position' => 3, 'required' => false],
        ];
        $fields = ['STT', 'BookingCode', 'Room', 'Guest', 'ServiceDate', 'ServiceName', 'ServiceCode', 'Quantity', 'Rate', 'Total', 'GroupQuantity', 'GroupTotal', 'IsRoom', 'IsPosted', 'ServiceId'];
        $numeric = ['STT', 'Quantity', 'Rate', 'Total', 'GroupQuantity', 'GroupTotal', 'IsRoom', 'IsPosted', 'ServiceId'];
        $fieldSchema = array_map(fn (string $name) => ['name' => $name, 'type' => in_array($name, $numeric, true) ? 'number' : 'string', 'nullable' => !in_array($name, ['STT', 'BookingCode', 'ServiceDate', 'ServiceName'], true)], $fields);
        $defaults = ['p_from_date' => now()->toDateString(), 'p_to_date' => now()->toDateString(), 'p_service_codes' => ''];
        $ui = [
            ['name' => 'p_from_date', 'label' => 'Ngày', 'control' => 'date-range', 'range_end_parameter' => 'p_to_date', 'default' => '$today', 'required' => true, 'options' => []],
            ['name' => 'p_to_date', 'label' => 'Đến ngày', 'control' => 'hidden', 'default' => '$today', 'required' => true, 'options' => []],
            ['name' => 'p_service_codes', 'label' => 'Dịch vụ', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'hotel-services', 'options' => []],
        ];

        DB::table('report_data_sources')->updateOrInsert(['code' => self::SOURCE], [
            'name' => 'Dữ liệu báo cáo dịch vụ bổ sung', 'description' => 'Dịch vụ bổ sung theo SP2102.', 'source_type' => 'procedure',
            'schema_name' => $database, 'object_name' => 'rpt_supplementary_services', 'parameter_schema' => json_encode($parameters, JSON_UNESCAPED_UNICODE),
            'field_schema' => json_encode($fieldSchema, JSON_UNESCAPED_UNICODE), 'sample_parameters' => json_encode($defaults), 'max_rows' => 5000,
            'is_active' => true, 'last_discovered_at' => $now, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $sourceId = DB::table('report_data_sources')->where('code', self::SOURCE)->value('id');

        DB::table('templates')->updateOrInsert(['report' => self::TEMPLATE], [
            'group' => 'Báo cáo phòng', 'name' => 'Báo cáo dịch vụ bổ sung', 'report_data_source_id' => $sourceId, 'parameter_defaults' => json_encode($defaults),
            'page_size' => 'A4', 'page_orientation' => 'portrait', 'margin_top' => 8, 'margin_bottom' => 8, 'margin_left' => 5, 'margin_right' => 5,
            'content_json' => json_encode(['header' => [], 'detail' => [], 'footer' => []]), 'content_html' => '<h1>BÁO CÁO DỊCH VỤ BỔ SUNG</h1>',
            'css' => '', 'is_default' => false, 'version' => '1.0', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $templateId = DB::table('templates')->where('report', self::TEMPLATE)->value('id');

        DB::table('report_definitions')->updateOrInsert(['code' => self::REPORT], [
            'name' => 'Báo cáo dịch vụ bổ sung', 'group' => 'Báo cáo phòng', 'description' => 'Danh sách dịch vụ bổ sung theo ngày dịch vụ và loại dịch vụ.',
            'report_data_source_id' => $sourceId, 'parameter_ui_schema' => json_encode($ui, JSON_UNESCAPED_UNICODE), 'sort_order' => 30,
            'is_active' => true, 'show_in_menu' => true, 'menu_locations' => json_encode(['reservation', 'frontdesk']), 'menu_top_order' => 20,
            'menu_group_order' => 10, 'menu_item_order' => 30, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $reportId = DB::table('report_definitions')->where('code', self::REPORT)->value('id');
        DB::table('report_definition_template')->updateOrInsert(['report_definition_id' => $reportId, 'template_id' => $templateId], ['is_default' => true, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now]);
        (require database_path('report_templates/supplementary_services_reference.php'))->apply();
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') return;
        $reportId = DB::table('report_definitions')->where('code', self::REPORT)->value('id');
        $templateId = DB::table('templates')->where('report', self::TEMPLATE)->value('id');
        if ($reportId) { DB::table('report_definition_template')->where('report_definition_id', $reportId)->delete(); DB::table('report_definitions')->where('id', $reportId)->delete(); }
        if ($templateId) DB::table('templates')->where('id', $templateId)->delete();
        DB::table('report_data_sources')->where('code', self::SOURCE)->delete();
        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_supplementary_services');
    }
};
