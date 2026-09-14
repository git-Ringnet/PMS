<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SOURCE = 'DAY_USE_ROOMS';
    private const REPORT = 'DAY_USE_ROOMS';
    private const TEMPLATE = 'DAY_USE_ROOMS_STANDARD';

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_day_use_rooms');
        DB::unprepared(<<<'SQL'
CREATE PROCEDURE rpt_day_use_rooms(
    IN p_from_date DATE,
    IN p_to_date DATE,
    IN p_user VARCHAR(100),
    IN p_sort_by VARCHAR(30),
    IN p_sort_order VARCHAR(4)
)
READS SQL DATA
BEGIN
    SELECT
        ROW_NUMBER() OVER (
            ORDER BY
                CASE WHEN UPPER(COALESCE(p_sort_order, 'ASC')) = 'DESC'
                     AND COALESCE(p_sort_by, 'Room') = 'ArrivalDate'
                     THEN br.arrival_date END DESC,
                CASE WHEN UPPER(COALESCE(p_sort_order, 'ASC')) <> 'DESC'
                     AND COALESCE(p_sort_by, 'Room') = 'ArrivalDate'
                     THEN br.arrival_date END ASC,
                CASE WHEN UPPER(COALESCE(p_sort_order, 'ASC')) = 'DESC'
                     AND COALESCE(p_sort_by, 'Room') = 'Room'
                     THEN br.room_number END DESC,
                CASE WHEN UPPER(COALESCE(p_sort_order, 'ASC')) <> 'DESC'
                     AND COALESCE(p_sort_by, 'Room') = 'Room'
                     THEN br.room_number END ASC,
                br.id
        ) AS STT,
        CONCAT(COALESCE(hs.prefix_booking_id, ''), b.id) AS BookingId,
        c.name AS Company,
        br.room_number AS Room,
        rc.code AS RoomType,
        CONCAT(DATE_FORMAT(br.arrival_date, '%d/%m/%Y'),
               IF(br.arrival_time IS NULL, '', CONCAT(' - ', TIME_FORMAT(br.arrival_time, '%H:%i')))) AS ArrivalDate,
        CONCAT(DATE_FORMAT(br.departure_date, '%d/%m/%Y'),
               IF(br.departure_time IS NULL, '', CONCAT(' - ', TIME_FORMAT(br.departure_time, '%H:%i')))) AS DepartureDate,
        br.adults AS Adult,
        br.babies AS Baby,
        br.children_qty AS Child,
        CONCAT(br.adults, ' / ', br.babies, ' / ', br.children_qty) AS AdultBabyChild,
        br.rate AS Rate,
        COALESCE(br.note, b.note) AS Note,
        br.id AS RentalRoomId,
        b.id AS BookingNumericId,
        br.arrival_date AS ArrivalDateSort,
        r.orders AS RoomOrder
    FROM booking_rooms br
    INNER JOIN bookings b ON b.id = br.booking_id AND b.deleted_at IS NULL
    INNER JOIN room_classes rc ON rc.id = br.room_class_id
    LEFT JOIN rooms r ON r.room_number = br.room_number
    LEFT JOIN companies c ON c.id = b.company_id
    LEFT JOIN hotel_settings hs ON 1 = 1
    WHERE br.deleted_at IS NULL
      AND br.is_day_use = 1
      AND br.arrival_date = br.departure_date
      AND br.status IN (0, 1, 2)
      AND b.status IN (0, 1, 2)
      AND br.arrival_date BETWEEN p_from_date AND p_to_date
      AND (COALESCE(p_user, '') = '' OR br.created_by = p_user)
      AND (br.room_number IS NULL OR br.room_number NOT LIKE '0%')
      AND COALESCE(r.is_internal, 0) = 0
    ORDER BY
        CASE WHEN UPPER(COALESCE(p_sort_order, 'ASC')) = 'DESC'
             AND COALESCE(p_sort_by, 'Room') = 'ArrivalDate' THEN br.arrival_date END DESC,
        CASE WHEN UPPER(COALESCE(p_sort_order, 'ASC')) <> 'DESC'
             AND COALESCE(p_sort_by, 'Room') = 'ArrivalDate' THEN br.arrival_date END ASC,
        CASE WHEN UPPER(COALESCE(p_sort_order, 'ASC')) = 'DESC'
             AND COALESCE(p_sort_by, 'Room') = 'Room' THEN br.room_number END DESC,
        CASE WHEN UPPER(COALESCE(p_sort_order, 'ASC')) <> 'DESC'
             AND COALESCE(p_sort_by, 'Room') = 'Room' THEN br.room_number END ASC,
        br.id;
END
SQL);

        $now = now();
        $database = DB::connection()->getDatabaseName();
        $parameters = [
            ['name' => 'p_from_date', 'mode' => 'IN', 'data_type' => 'date', 'database_type' => 'date', 'position' => 1, 'required' => true],
            ['name' => 'p_to_date', 'mode' => 'IN', 'data_type' => 'date', 'database_type' => 'date', 'position' => 2, 'required' => true],
            ['name' => 'p_user', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(100)', 'position' => 3, 'required' => false],
            ['name' => 'p_sort_by', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(30)', 'position' => 4, 'required' => true],
            ['name' => 'p_sort_order', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(4)', 'position' => 5, 'required' => true],
        ];
        $fields = collect(['STT', 'BookingId', 'Company', 'Room', 'RoomType', 'ArrivalDate', 'DepartureDate', 'Adult', 'Baby', 'Child', 'AdultBabyChild', 'Rate', 'Note', 'RentalRoomId', 'BookingNumericId', 'ArrivalDateSort', 'RoomOrder'])
            ->map(fn ($name) => [
                'name' => $name,
                'type' => in_array($name, ['STT', 'Adult', 'Baby', 'Child', 'RentalRoomId', 'BookingNumericId', 'RoomOrder'], true) ? 'integer' : (in_array($name, ['Rate'], true) ? 'number' : 'string'),
                'nullable' => true,
            ])->all();

        DB::table('report_data_sources')->updateOrInsert(
            ['code' => self::SOURCE],
            [
                'name' => 'Dữ liệu báo cáo phòng Day Use',
                'description' => 'MySQL chuyển đổi từ ProVista sp_132.',
                'source_type' => 'procedure',
                'schema_name' => $database,
                'object_name' => 'rpt_day_use_rooms',
                'parameter_schema' => json_encode($parameters, JSON_UNESCAPED_UNICODE),
                'field_schema' => json_encode($fields, JSON_UNESCAPED_UNICODE),
                'sample_parameters' => json_encode(['p_from_date' => now()->toDateString(), 'p_to_date' => now()->toDateString(), 'p_user' => '', 'p_sort_by' => 'Room', 'p_sort_order' => 'ASC'], JSON_UNESCAPED_UNICODE),
                'max_rows' => 5000,
                'is_active' => true,
                'last_discovered_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $sourceId = DB::table('report_data_sources')->where('code', self::SOURCE)->value('id');
        if (DB::table('templates')->where('report', self::TEMPLATE)->doesntExist()) {
            DB::table('templates')->updateOrInsert(
            ['report' => self::TEMPLATE],
            [
                'group' => 'Báo cáo phòng',
                'name' => 'Báo cáo phòng ở trong ngày (Day Use) - Mẫu legacy',
                'report_data_source_id' => $sourceId,
                'page_size' => 'A4',
                'page_orientation' => 'landscape',
                'margin_top' => 6,
                'margin_bottom' => 6,
                'margin_left' => 5,
                'margin_right' => 5,
                'content_json' => json_encode((require database_path('report_templates/day_use_rooms_reference.php'))->contentJson(), JSON_UNESCAPED_UNICODE),
                'content_html' => (require database_path('report_templates/day_use_rooms_reference.php'))->html(),
                'css' => (require database_path('report_templates/day_use_rooms_reference.php'))->css(),
                'version' => '1.0',
                'created_at' => $now,
                'updated_at' => $now,
            ]
            );
        } else {
            DB::table('templates')->where('report', self::TEMPLATE)->update([
                'report_data_source_id' => $sourceId,
                'updated_at' => $now,
            ]);
        }

        $templateId = DB::table('templates')->where('report', self::TEMPLATE)->value('id');
        $ui = [
            ['name' => 'p_from_date', 'label' => 'Ngày', 'control' => 'date-range', 'range_end_parameter' => 'p_to_date', 'default' => '$today', 'required' => true],
            ['name' => 'p_to_date', 'label' => 'Đến ngày', 'control' => 'hidden', 'default' => '$today', 'required' => true],
            ['name' => 'p_user', 'label' => 'Người dùng', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'users'],
            ['name' => 'p_sort_by', 'label' => 'Sắp xếp theo', 'control' => 'select', 'default' => 'Room', 'required' => true, 'options' => [['label' => 'Phòng', 'value' => 'Room'], ['label' => 'Ngày đến', 'value' => 'ArrivalDate']]],
            ['name' => 'p_sort_order', 'label' => 'Thứ tự', 'control' => 'select', 'default' => 'ASC', 'required' => true, 'options' => [['label' => 'ASC', 'value' => 'ASC'], ['label' => 'DESC', 'value' => 'DESC']]],
        ];
        DB::table('report_definitions')->updateOrInsert(
            ['code' => self::REPORT],
            [
                'name' => 'Báo cáo phòng ở trong ngày (Day Use)',
                'group' => 'Báo cáo phòng',
                'description' => 'Danh sách phòng Day Use theo legacy sp_132.',
                'report_data_source_id' => $sourceId,
                'parameter_ui_schema' => json_encode($ui, JSON_UNESCAPED_UNICODE),
                'sort_order' => 33,
                'is_active' => true,
                'show_in_menu' => true,
                'menu_locations' => json_encode(['reservation', 'frontdesk']),
                'menu_top_order' => 20,
                'menu_group_order' => 10,
                'menu_item_order' => 33,
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
        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_day_use_rooms');
    }
};
