<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SOURCE = 'VIP_GUESTS';
    private const REPORT = 'VIP_GUESTS';
    private const TEMPLATE = 'VIP_GUESTS_STANDARD';

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_vip_guests');
        DB::unprepared($this->procedureSql());

        $this->seedReportConfiguration();
    }

    public function procedureSql(): string
    {
        return <<<'SQL'
CREATE PROCEDURE rpt_vip_guests(
    IN p_from_date DATETIME,
    IN p_to_date DATETIME,
    IN p_guest_type INT
)
READS SQL DATA
BEGIN
    SELECT
        TRIM(CONCAT_WS(' ', NULLIF(g.title, ''), g.full_name)) AS GuestName,
        br.status AS Status,
        CASE br.status
            WHEN 0 THEN 'Đăng ký'
            WHEN 1 THEN 'Đang ở'
            WHEN 2 THEN 'Đã trả'
            WHEN 3 THEN 'Hủy phòng'
            WHEN 4 THEN 'Không đến'
            WHEN 100 THEN 'Phòng chuyển'
            ELSE 'Đăng ký'
        END AS StatusVi,
        CONCAT(COALESCE(hs.prefix_booking_id, ''), b.id) AS BookingId,
        COALESCE(br.room_number, '') AS Room,
        COALESCE(gt.name, gt.code, g.guest_type, 'VIP') AS GuestType,
        COALESCE(gt.id, CAST(g.guest_type AS UNSIGNED), 0) AS GuestTypeId,
        DATE_FORMAT(COALESCE(brg.actual_arrival_date, br.arrival_date), '%d-%m-%Y') AS ArrivalDate,
        DATE_FORMAT(COALESCE(brg.actual_checkout_date, br.departure_date), '%d-%m-%Y') AS DepartureDate,
        COALESCE(br.rate, 0) AS Rate,
        COALESCE(br.adults, 0) AS Adult,
        COALESCE(br.children_qty, 0) AS Child,
        CONCAT(COALESCE(br.adults, 0), '/', COALESCE(br.children_qty, 0)) AS AdultChild,
        COALESCE(c.name, c.code, '') AS Company,
        COALESCE(br.note, b.note, '') AS Note
    FROM guests AS g
    INNER JOIN booking_room_guests AS brg
        ON brg.guest_id = g.id
    INNER JOIN booking_rooms AS br
        ON br.id = brg.booking_room_id
       AND br.deleted_at IS NULL
    INNER JOIN bookings AS b
        ON b.id = br.booking_id
       AND b.deleted_at IS NULL
    LEFT JOIN guest_types AS gt
        ON gt.id = g.guest_type
        OR gt.code = g.guest_type
        OR gt.name = g.guest_type
    LEFT JOIN companies AS c
        ON c.id = b.company_id
    LEFT JOIN rooms AS r
        ON r.room_number = br.room_number
    LEFT JOIN hotel_settings AS hs
        ON hs.id = (SELECT MIN(id) FROM hotel_settings)
    WHERE COALESCE(r.is_internal, 0) = 0
      AND (r.room_number IS NULL OR r.room_number NOT LIKE '0%')
      AND (brg.status IS NULL OR brg.status <> 3)
      AND (gt.code IS NULL OR gt.code <> 'RegularGuest')
      AND COALESCE(g.guest_type, '') <> '6'
      AND (
          gt.code LIKE '%VIP%'
          OR gt.name LIKE '%VIP%'
          OR g.guest_type LIKE '%VIP%'
          OR g.guest_type IN ('1', '2', '3', '4')
      )
      AND (
          COALESCE(p_guest_type, 0) = 0
          OR gt.id = p_guest_type
          OR gt.code = CONCAT('VIP', p_guest_type)
          OR gt.code = CONCAT('VIP ', p_guest_type)
          OR g.guest_type = CAST(p_guest_type AS CHAR)
      )
      AND (
          (COALESCE(brg.actual_arrival_date, br.arrival_date) <= DATE(p_to_date)
           AND COALESCE(brg.actual_checkout_date, br.departure_date) >= DATE(p_from_date))
      )
    ORDER BY
        COALESCE(gt.order_index, gt.id, 99) ASC,
        gt.name ASC,
        GuestName ASC,
        ArrivalDate ASC;
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
        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_vip_guests');
    }

    private function seedReportConfiguration(): void
    {
        $now = now();
        $database = DB::connection()->getDatabaseName();

        $parameters = [
            ['name' => 'p_from_date', 'mode' => 'IN', 'data_type' => 'datetime', 'database_type' => 'datetime', 'position' => 1, 'required' => true],
            ['name' => 'p_to_date', 'mode' => 'IN', 'data_type' => 'datetime', 'database_type' => 'datetime', 'position' => 2, 'required' => true],
            ['name' => 'p_guest_type', 'mode' => 'IN', 'data_type' => 'int', 'database_type' => 'int', 'position' => 3, 'required' => false],
        ];

        $fields = collect([
            'GuestName', 'Status', 'StatusVi', 'BookingId', 'Room',
            'GuestType', 'GuestTypeId', 'ArrivalDate', 'DepartureDate',
            'Rate', 'Adult', 'Child', 'AdultChild', 'Company', 'Note',
        ])->map(function (string $name): array {
            $numeric = ['Status', 'GuestTypeId', 'Rate', 'Adult', 'Child'];
            return [
                'name' => $name,
                'type' => in_array($name, $numeric, true) ? 'number' : 'string',
                'nullable' => ! in_array($name, ['GuestName', 'GuestType'], true),
            ];
        })->all();

        $defaults = [
            'p_from_date' => now()->startOfDay()->format('Y-m-d H:i:s'),
            'p_to_date' => now()->endOfDay()->format('Y-m-d H:i:s'),
            'p_guest_type' => 0,
        ];

        DB::table('report_data_sources')->updateOrInsert(['code' => self::SOURCE], [
            'name' => 'Dữ liệu báo cáo khách VIP',
            'description' => 'MySQL chuyển đổi theo legacy sp_295; danh sách khách VIP đang lưu trú hoặc đăng ký.',
            'source_type' => 'procedure',
            'schema_name' => $database,
            'object_name' => 'rpt_vip_guests',
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

        $definition = (require database_path('report_templates/vip_guests_reference.php'))->definition();
        DB::table('templates')->updateOrInsert(['report' => self::TEMPLATE], [
            'group' => 'Báo cáo khách',
            'name' => 'Báo cáo khách VIP',
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
            ['name' => 'p_from_date', 'label' => 'Ngày', 'control' => 'date-range', 'range_end_parameter' => 'p_to_date', 'default' => '$today', 'required' => true],
            ['name' => 'p_to_date', 'label' => 'Đến ngày', 'control' => 'hidden', 'default' => '$today', 'required' => true],
            [
                'name' => 'p_guest_type',
                'label' => 'Loại khách',
                'control' => 'select',
                'default' => 0,
                'required' => false,
                'options' => [
                    ['value' => 0, 'label' => 'Tất cả'],
                    ['value' => 1, 'label' => 'VIP 1'],
                    ['value' => 2, 'label' => 'VIP 2'],
                    ['value' => 3, 'label' => 'VIP 3'],
                    ['value' => 4, 'label' => 'VIP 4'],
                ],
            ],
        ];

        DB::table('report_definitions')->updateOrInsert(['code' => self::REPORT], [
            'name' => 'Báo cáo khách VIP',
            'group' => 'Báo cáo khách',
            'description' => 'Danh sách khách VIP theo loại khách và thời gian lưu trú.',
            'report_data_source_id' => $sourceId,
            'parameter_ui_schema' => json_encode($ui, JSON_UNESCAPED_UNICODE),
            'sort_order' => 38,
            'is_active' => true,
            'show_in_menu' => true,
            'menu_locations' => json_encode(['reservation', 'frontdesk']),
            'menu_top_order' => 20,
            'menu_group_order' => 20,
            'menu_item_order' => 38,
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
