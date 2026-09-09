<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SOURCE = 'TRANSPORTATION';
    private const REPORT = 'TRANSPORTATION';
    private const TEMPLATE = 'TRANSPORTATION_STANDARD';

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_transportation');
        DB::unprepared(<<<'SQL'
CREATE PROCEDURE rpt_transportation(
    IN p_from_date DATE,
    IN p_to_date DATE,
    IN p_group_by_date TINYINT
)
READS SQL DATA
BEGIN
    SELECT
        ROW_NUMBER() OVER (
            ORDER BY transport_date, is_arrival DESC, booking_id, transport_time
        ) AS STT,
        DATE_FORMAT(transport_date, '%d/%m/%Y') AS DateGroup,
        CASE WHEN is_arrival = 1 THEN 'Thông Tin Đón Khách' ELSE 'Thông Tin Tiễn Khách' END AS TransportGroup,
        CASE WHEN is_arrival = 1 THEN 'Đón khách' ELSE 'Trả khách' END AS TransportType,
        CONCAT(COALESCE(hs.prefix_booking_id, ''), booking_id) AS BookingId,
        b.booking_name AS BookingName,
        vehicle AS ArrivalBy,
        code_number AS CodeNumber,
        DATE_FORMAT(transport_date, '%d/%m/%Y') AS TransportDate,
        transport_time AS TransportTime,
        rate AS Rate,
        pickup_dropoff AS PickupDropoff,
        transport.note AS Note,
        transport.is_arrival AS IsArrival
    FROM (
        SELECT
            b.id AS booking_id,
            b.booking_name,
            JSON_UNQUOTE(JSON_EXTRACT(b.shuttle_info, CONCAT('$[', item_indexes.item_index, '].vehicle'))) AS vehicle,
            JSON_UNQUOTE(JSON_EXTRACT(b.shuttle_info, CONCAT('$[', item_indexes.item_index, '].code'))) AS code_number,
            STR_TO_DATE(JSON_UNQUOTE(JSON_EXTRACT(b.shuttle_info, CONCAT('$[', item_indexes.item_index, '].date'))), '%Y-%m-%d') AS transport_date,
            JSON_UNQUOTE(JSON_EXTRACT(b.shuttle_info, CONCAT('$[', item_indexes.item_index, '].time'))) AS transport_time,
            CAST(JSON_UNQUOTE(JSON_EXTRACT(b.shuttle_info, CONCAT('$[', item_indexes.item_index, '].price'))) AS DECIMAL(18, 0)) AS rate,
            JSON_UNQUOTE(JSON_EXTRACT(b.shuttle_info, CONCAT('$[', item_indexes.item_index, '].location'))) AS pickup_dropoff,
            JSON_UNQUOTE(JSON_EXTRACT(b.shuttle_info, CONCAT('$[', item_indexes.item_index, '].note'))) AS note,
            CASE
                WHEN JSON_UNQUOTE(JSON_EXTRACT(b.shuttle_info, CONCAT('$[', item_indexes.item_index, '].type'))) IN ('Đón', 'đón', 'Don', 'don', 'arrival', 'arrive', '1') THEN 1
                ELSE 0
            END AS is_arrival
        FROM bookings AS b
        JOIN (
            SELECT 0 AS item_index UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
            UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9
            UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL SELECT 12 UNION ALL SELECT 13 UNION ALL SELECT 14
            UNION ALL SELECT 15 UNION ALL SELECT 16 UNION ALL SELECT 17 UNION ALL SELECT 18 UNION ALL SELECT 19
        ) AS item_indexes
            ON item_indexes.item_index < JSON_LENGTH(COALESCE(b.shuttle_info, JSON_ARRAY()))
        WHERE b.deleted_at IS NULL
          AND JSON_UNQUOTE(JSON_EXTRACT(b.shuttle_info, CONCAT('$[', item_indexes.item_index, '].date'))) IS NOT NULL
    ) AS transport
    INNER JOIN bookings AS b ON b.id = transport.booking_id
    LEFT JOIN hotel_settings AS hs ON hs.id = (SELECT MIN(id) FROM hotel_settings)
    WHERE transport.transport_date BETWEEN p_from_date AND p_to_date
    ORDER BY transport.transport_date, transport.is_arrival DESC, transport.booking_id, transport.transport_time;
END
SQL);

        $now = now();
        $database = DB::connection()->getDatabaseName();
        $parameters = [
            ['name' => 'p_from_date', 'mode' => 'IN', 'data_type' => 'date', 'database_type' => 'date', 'position' => 1, 'required' => true],
            ['name' => 'p_to_date', 'mode' => 'IN', 'data_type' => 'date', 'database_type' => 'date', 'position' => 2, 'required' => true],
            ['name' => 'p_group_by_date', 'mode' => 'IN', 'data_type' => 'tinyint', 'database_type' => 'tinyint', 'position' => 3, 'required' => true],
        ];
        $numericFields = ['STT', 'Rate', 'IsArrival'];
        $fields = collect([
            'STT', 'DateGroup', 'TransportGroup', 'TransportType', 'BookingId', 'BookingName',
            'ArrivalBy', 'CodeNumber', 'TransportDate', 'TransportTime', 'Rate', 'PickupDropoff', 'Note', 'IsArrival',
        ])->map(fn (string $name) => [
            'name' => $name,
            'type' => in_array($name, $numericFields, true) ? 'number' : 'string',
            'nullable' => ! in_array($name, ['STT', 'DateGroup', 'TransportGroup', 'TransportType', 'BookingId', 'TransportDate', 'IsArrival'], true),
        ])->all();
        $defaults = ['p_from_date' => now()->toDateString(), 'p_to_date' => now()->toDateString(), 'p_group_by_date' => 0];
        $ui = [
            ['name' => 'p_from_date', 'label' => 'Ngày', 'control' => 'date-range', 'range_end_parameter' => 'p_to_date', 'default' => '$today', 'required' => true],
            ['name' => 'p_to_date', 'label' => 'Đến ngày', 'control' => 'hidden', 'default' => '$today', 'required' => true],
            ['name' => 'p_group_by_date', 'label' => 'Nhóm theo ngày', 'control' => 'checkbox', 'default' => false, 'required' => false],
        ];

        DB::table('report_data_sources')->updateOrInsert(['code' => self::SOURCE], [
            'name' => 'Dữ liệu báo cáo đưa đón khách',
            'description' => 'Tổng hợp lịch đón và trả khách theo ngày.',
            'source_type' => 'procedure',
            'schema_name' => $database,
            'object_name' => 'rpt_transportation',
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
            'name' => 'Báo cáo đưa đón khách',
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
            'name' => 'Báo cáo đưa đón khách',
            'group' => 'Báo cáo khách',
            'description' => 'Tổng hợp toàn bộ lịch đưa đón khách theo ngày.',
            'report_data_source_id' => $sourceId,
            'parameter_ui_schema' => json_encode($ui, JSON_UNESCAPED_UNICODE),
            'sort_order' => 36,
            'is_active' => true,
            'show_in_menu' => true,
            'menu_locations' => json_encode(['reservation', 'frontdesk']),
            'menu_top_order' => 20,
            'menu_group_order' => 20,
            'menu_item_order' => 36,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $reportId = DB::table('report_definitions')->where('code', self::REPORT)->value('id');
        DB::table('report_definition_template')->updateOrInsert(
            ['report_definition_id' => $reportId, 'template_id' => $templateId],
            ['is_default' => true, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now]
        );

        (require database_path('report_templates/transportation_reference.php'))->apply();
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') return;

        $reportId = DB::table('report_definitions')->where('code', self::REPORT)->value('id');
        $templateId = DB::table('templates')->where('report', self::TEMPLATE)->value('id');
        if ($reportId) {
            DB::table('report_definition_template')->where('report_definition_id', $reportId)->delete();
            DB::table('report_definitions')->where('id', $reportId)->delete();
        }
        if ($templateId) DB::table('templates')->where('id', $templateId)->delete();
        DB::table('report_data_sources')->where('code', self::SOURCE)->delete();
        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_transportation');
    }
};
