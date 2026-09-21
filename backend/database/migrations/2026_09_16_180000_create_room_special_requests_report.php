<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SOURCE = 'ROOM_SPECIAL_REQUESTS';
    private const REPORT = 'ROOM_SPECIAL_REQUESTS';
    private const TEMPLATE = 'ROOM_SPECIAL_REQUESTS_REFERENCE';

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_room_special_requests');
        DB::unprepared($this->procedureSql());

        $this->seedReportConfiguration();
    }

    public function procedureSql(): string
    {
        return <<<'SQL'
CREATE PROCEDURE rpt_room_special_requests(
    IN p_from_date DATETIME,
    IN p_to_date DATETIME,
    IN p_date_type TINYINT
)
READS SQL DATA
BEGIN
    SELECT
        CONCAT(COALESCE(hs.prefix_booking_id, ''), b.id) AS BookingId,
        COALESCE(b.note, '') AS BookingNote,
        COALESCE(r.room_number, '') AS Room,
        COALESCE(rc.name, '') AS RoomType,
        COALESCE(
            (SELECT g.full_name
             FROM booking_room_guests AS brg
             INNER JOIN guests AS g ON g.id = brg.guest_id
             WHERE brg.booking_room_id = br.id
             ORDER BY brg.is_primary DESC, brg.id ASC
             LIMIT 1),
            b.booking_name,
            ''
        ) AS GuestName,
        DATE_FORMAT(br.arrival_date, '%d-%m-%Y') AS ArrivalDate,
        DATE_FORMAT(br.departure_date, '%d-%m-%Y') AS DepartureDate,
        COALESCE(br.adults, 1) AS Adults,
        COALESCE(br.children_qty, 0) AS Children,
        CONCAT(COALESCE(br.adults, 1), '/', COALESCE(br.children_qty, 0)) AS AdultsDisplay,
        CAST(COALESCE(br.children_qty, 0) AS CHAR) AS ChildrenDisplay,
        GROUP_CONCAT(DISTINCT sr.name ORDER BY sr.sort_order, sr.id SEPARATOR ', ') AS SpecialRequests
    FROM booking_room_special_requests AS brsr
    INNER JOIN special_requests AS sr
        ON sr.id = brsr.special_request_id
    INNER JOIN booking_rooms AS br
        ON br.id = brsr.booking_room_id
       AND br.deleted_at IS NULL
    INNER JOIN bookings AS b
        ON b.id = br.booking_id
       AND b.deleted_at IS NULL
    LEFT JOIN rooms AS r
        ON r.room_number = br.room_number
    LEFT JOIN room_classes AS rc
        ON rc.id = br.room_class_id
    LEFT JOIN registration_statuses AS rs
        ON rs.id = br.status
    LEFT JOIN hotel_settings AS hs
        ON hs.id = (SELECT MIN(id) FROM hotel_settings)
    WHERE COALESCE(r.is_internal, 0) = 0
      AND (r.room_number IS NULL OR r.room_number NOT LIKE '0%')
      AND (rs.bk_definite IS NULL OR rs.bk_definite <> 4)
      AND (
          (COALESCE(p_date_type, 0) = 0 AND br.arrival_date < p_to_date AND br.departure_date > p_from_date)
          OR (p_date_type = 1 AND br.arrival_date BETWEEN p_from_date AND p_to_date)
          OR (p_date_type = 2 AND br.departure_date BETWEEN p_from_date AND p_to_date)
      )
    GROUP BY
        b.id,
        b.note,
        br.id,
        r.room_number,
        rc.name,
        br.arrival_date,
        br.departure_date,
        br.adults,
        br.children_qty,
        hs.prefix_booking_id,
        b.booking_name
    ORDER BY b.id ASC, br.arrival_date ASC, r.room_number ASC;
END
SQL;
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
        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_room_special_requests');
    }

    private function seedReportConfiguration(): void
    {
        $now = now();
        $database = DB::connection()->getDatabaseName();
        $parameters = [
            ['name' => 'p_from_date', 'mode' => 'IN', 'data_type' => 'datetime', 'database_type' => 'datetime', 'position' => 1, 'required' => true],
            ['name' => 'p_to_date', 'mode' => 'IN', 'data_type' => 'datetime', 'database_type' => 'datetime', 'position' => 2, 'required' => true],
            ['name' => 'p_date_type', 'mode' => 'IN', 'data_type' => 'tinyint', 'database_type' => 'tinyint', 'position' => 3, 'required' => true],
        ];

        $fields = collect([
            'BookingId', 'BookingNote', 'Room', 'RoomType', 'GuestName',
            'ArrivalDate', 'DepartureDate', 'Adults', 'Children',
            'AdultsDisplay', 'ChildrenDisplay', 'SpecialRequests',
        ])->map(function (string $name): array {
            $numeric = ['Adults', 'Children'];
            return [
                'name' => $name,
                'type' => in_array($name, $numeric, true) ? 'number' : 'string',
                'nullable' => ! in_array($name, ['BookingId', 'SpecialRequests'], true),
            ];
        })->all();

        $defaults = [
            'p_from_date' => now()->startOfDay()->format('Y-m-d H:i:s'),
            'p_to_date' => now()->endOfDay()->format('Y-m-d H:i:s'),
            'p_date_type' => 0,
        ];

        DB::table('report_data_sources')->updateOrInsert(['code' => self::SOURCE], [
            'name' => 'Dữ liệu báo cáo yêu cầu đặc biệt',
            'description' => 'MySQL chuyển đổi theo legacy sp_297; phòng có yêu cầu đặc biệt theo đăng ký.',
            'source_type' => 'procedure',
            'schema_name' => $database,
            'object_name' => 'rpt_room_special_requests',
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

        $definition = (require database_path('report_templates/room_special_requests_reference.php'))->definition();
        DB::table('templates')->updateOrInsert(['report' => self::TEMPLATE], [
            'group' => 'Báo cáo phòng',
            'name' => 'Báo cáo yêu cầu đặc biệt',
            'report_data_source_id' => $sourceId,
            'parameter_defaults' => json_encode($defaults),
            'page_size' => $definition['page_size'],
            'page_orientation' => $definition['page_orientation'],
            'margin_top' => $definition['margin_top'],
            'margin_right' => $definition['margin_right'],
            'margin_bottom' => $definition['margin_bottom'],
            'margin_left' => $definition['margin_left'],
            'content_json' => json_encode($definition['content_json'], JSON_UNESCAPED_UNICODE),
            'content_html' => $definition['content_html'],
            'css' => $definition['css'],
            'is_default' => true,
            'version' => '1.0',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $templateId = DB::table('templates')->where('report', self::TEMPLATE)->value('id');

        $ui = [
            ['name' => 'p_from_date', 'label' => 'Chọn ngày', 'control' => 'date-range', 'range_end_parameter' => 'p_to_date', 'default' => '$today', 'required' => true],
            ['name' => 'p_to_date', 'label' => 'Đến ngày', 'control' => 'hidden', 'default' => '$today', 'required' => true],
            [
                'name' => 'p_date_type',
                'label' => 'Xem theo ngày',
                'control' => 'select',
                'default' => 0,
                'required' => true,
                'options' => [
                    ['value' => 0, 'label' => 'Ngày ở'],
                    ['value' => 1, 'label' => 'Ngày đến'],
                    ['value' => 2, 'label' => 'Ngày đi'],
                ],
            ],
        ];

        DB::table('report_definitions')->updateOrInsert(['code' => self::REPORT], [
            'name' => 'Báo cáo yêu cầu đặc biệt',
            'group' => 'Báo cáo phòng',
            'description' => 'Danh sách các phòng có yêu cầu đặc biệt theo đăng ký và khoảng thời gian.',
            'report_data_source_id' => $sourceId,
            'parameter_ui_schema' => json_encode($ui, JSON_UNESCAPED_UNICODE),
            'sort_order' => 55,
            'is_active' => true,
            'show_in_menu' => true,
            'menu_locations' => json_encode(['frontdesk', 'reservation']),
            'menu_top_order' => 20,
            'menu_group_order' => 10,
            'menu_item_order' => 55,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $reportId = DB::table('report_definitions')->where('code', self::REPORT)->value('id');

        DB::table('report_definition_template')->updateOrInsert(
            ['report_definition_id' => $reportId, 'template_id' => $templateId],
            ['is_default' => true, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now]
        );
    }
};
