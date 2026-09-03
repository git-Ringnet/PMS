<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SOURCE_CODE = 'ROOM_MOVES';
    private const REPORT_CODE = 'ROOM_MOVES';
    private const TEMPLATE_CODE = 'ROOM_MOVES_STANDARD';

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') return;
        $this->createProcedure();
        $this->seedReportConfiguration();
        (require database_path('report_templates/room_moves_reference.php'))->apply();
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') return;
        $reportId = DB::table('report_definitions')->where('code', self::REPORT_CODE)->value('id');
        $templateId = DB::table('templates')->where('report', self::TEMPLATE_CODE)->value('id');
        if ($reportId) {
            DB::table('report_definition_template')->where('report_definition_id', $reportId)->delete();
            DB::table('report_definitions')->where('id', $reportId)->delete();
        }
        if ($templateId) DB::table('templates')->where('id', $templateId)->delete();
        DB::table('report_data_sources')->where('code', self::SOURCE_CODE)->delete();
        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_room_moves');
    }

    private function createProcedure(): void
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_room_moves');
        DB::unprepared(<<<'SQL'
CREATE PROCEDURE rpt_room_moves(
    IN p_from_date DATE,
    IN p_to_date DATE,
    IN p_user VARCHAR(100),
    IN p_sort_by VARCHAR(20),
    IN p_order_type VARCHAR(20)
)
READS SQL DATA
BEGIN
    SELECT
        ROW_NUMBER() OVER (ORDER BY new_br.arrival_date, old_br.id) AS STT,
        CONCAT(COALESCE(hs.prefix_booking_id, 'GAL'), old_br.booking_id) AS BookingCode,
        old_br.booking_id AS BookingId,
        CONCAT(COALESCE(hs.prefix_booking_id, 'GAL'), new_br.booking_id) AS BookingCode1,
        new_br.booking_id AS BookingId1,
        old_br.id AS RentalRoomId,
        new_br.id AS RentalRoomId1,
        old_br.actual_arrival_date AS ArrivalDate,
        old_br.room_number AS Room,
        old_rc.code AS RoomTypeCode,
        old_rc.name AS RoomType,
        old_br.rate AS Rate,
        old_br.move_room AS MoveRoom,
        new_br.arrival_date AS ArrivalDate1,
        new_br.room_number AS Room1,
        new_rc.code AS RoomTypeCode1,
        new_rc.name AS RoomType1,
        new_br.rate AS Rate1,
        new_br.check_in_user AS Username,
        TRIM(CONCAT_WS(' ', NULLIF(g.title, ''), g.full_name)) AS Guest,
        old_br.reason AS Reason,
        old_br.departure_date AS OldDepartureDate
    FROM booking_rooms AS old_br
    INNER JOIN booking_rooms AS new_br ON new_br.id = old_br.move_room AND new_br.deleted_at IS NULL
    INNER JOIN bookings AS b ON b.id = old_br.booking_id AND b.deleted_at IS NULL
    INNER JOIN booking_room_guests AS brg
        ON brg.booking_room_id = new_br.id AND brg.is_primary = 1 AND brg.status IN (0, 1, 2, 4, 100)
    INNER JOIN guests AS g ON g.id = brg.guest_id
    INNER JOIN room_classes AS old_rc ON old_rc.id = old_br.room_class_id
    INNER JOIN room_classes AS new_rc ON new_rc.id = new_br.room_class_id
    LEFT JOIN hotel_settings AS hs ON hs.id = (SELECT MIN(id) FROM hotel_settings)
    WHERE old_br.deleted_at IS NULL
      AND old_br.status = 100
      AND new_br.arrival_date >= p_from_date
      AND new_br.arrival_date < DATE_ADD(p_to_date, INTERVAL 1 DAY)
      AND (p_user IS NULL OR p_user = '' OR new_br.check_in_user LIKE CONCAT('%', p_user, '%'))
    ORDER BY
        CASE WHEN p_sort_by IN ('Room', 'Room1') AND p_order_type = 'ASC' THEN CAST(NULLIF(old_br.room_number, '') AS UNSIGNED) END ASC,
        CASE WHEN p_sort_by IN ('Room', 'Room1') AND p_order_type = 'DESC' THEN CAST(NULLIF(old_br.room_number, '') AS UNSIGNED) END DESC,
        CASE WHEN p_sort_by = 'ArrivalDate' AND p_order_type = 'ASC' THEN old_br.actual_arrival_date END ASC,
        CASE WHEN p_sort_by = 'ArrivalDate' AND p_order_type = 'DESC' THEN old_br.actual_arrival_date END DESC,
        CASE WHEN p_sort_by = 'ArrivalDate1' AND p_order_type = 'ASC' THEN new_br.arrival_date END ASC,
        CASE WHEN p_sort_by = 'ArrivalDate1' AND p_order_type = 'DESC' THEN new_br.arrival_date END DESC,
        old_br.id;
END
SQL);
    }

    private function seedReportConfiguration(): void
    {
        $database = DB::connection()->getDatabaseName();
        $now = now();
        $parameterSchema = [
            $this->parameter('p_from_date', 'date', 1), $this->parameter('p_to_date', 'date', 2),
            $this->parameter('p_user', 'varchar', 3), $this->parameter('p_sort_by', 'varchar', 4),
            $this->parameter('p_order_type', 'varchar', 5),
        ];
        $fields = ['STT', 'BookingCode', 'BookingCode1', 'BookingId', 'BookingId1', 'RentalRoomId', 'RentalRoomId1', 'ArrivalDate', 'Room', 'RoomTypeCode', 'RoomType', 'Rate', 'MoveRoom', 'ArrivalDate1', 'Room1', 'RoomTypeCode1', 'RoomType1', 'Rate1', 'Username', 'Guest', 'Reason', 'OldDepartureDate'];
        $numericFields = ['STT', 'BookingId', 'BookingId1', 'Rate', 'Rate1'];
        $fieldSchema = array_map(fn (string $name) => ['name' => $name, 'type' => in_array($name, $numericFields, true) ? 'number' : 'string', 'nullable' => ! in_array($name, ['BookingCode', 'BookingId', 'RentalRoomId', 'Room', 'Room1', 'ArrivalDate1'], true)], $fields);
        $defaults = ['p_from_date' => now()->toDateString(), 'p_to_date' => now()->toDateString(), 'p_user' => '', 'p_sort_by' => 'ArrivalDate1', 'p_order_type' => 'ASC'];

        DB::table('report_data_sources')->updateOrInsert(['code' => self::SOURCE_CODE], [
            'name' => 'Dữ liệu báo cáo chuyển phòng', 'description' => 'MySQL chuyển đổi từ ProVista sp_129 và vw_017.',
            'source_type' => 'procedure', 'schema_name' => $database, 'object_name' => 'rpt_room_moves',
            'parameter_schema' => json_encode($parameterSchema, JSON_UNESCAPED_UNICODE), 'field_schema' => json_encode($fieldSchema, JSON_UNESCAPED_UNICODE),
            'sample_parameters' => json_encode($defaults), 'max_rows' => 2000, 'is_active' => true, 'last_discovered_at' => $now, 'updated_at' => $now, 'created_at' => $now,
        ]);
        $sourceId = DB::table('report_data_sources')->where('code', self::SOURCE_CODE)->value('id');

        DB::table('templates')->updateOrInsert(['report' => self::TEMPLATE_CODE], [
            'group' => 'Báo cáo phòng', 'name' => 'Báo cáo chuyển phòng', 'report_data_source_id' => $sourceId, 'parameter_defaults' => json_encode($defaults),
            'page_size' => 'A4', 'page_orientation' => 'landscape', 'margin_top' => 6, 'margin_bottom' => 6, 'margin_left' => 5, 'margin_right' => 5,
            'content_json' => json_encode(['header' => [], 'detail' => [], 'footer' => []]), 'content_html' => '<h1>BÁO CÁO CHUYỂN PHÒNG</h1>', 'css' => '', 'is_default' => false, 'version' => '1.0', 'updated_at' => $now, 'created_at' => $now,
        ]);
        $templateId = DB::table('templates')->where('report', self::TEMPLATE_CODE)->value('id');

        DB::table('report_definitions')->updateOrInsert(['code' => self::REPORT_CODE], [
            'name' => 'Báo cáo chuyển phòng', 'group' => 'Báo cáo phòng', 'description' => 'Danh sách phòng chuyển theo ngày chuyển và người thực hiện.',
            'report_data_source_id' => $sourceId, 'parameter_ui_schema' => json_encode($this->parameterUiSchema(), JSON_UNESCAPED_UNICODE), 'sort_order' => 25,
            'is_active' => true, 'show_in_menu' => true, 'menu_locations' => json_encode(['reservation', 'frontdesk']), 'menu_top_order' => 20, 'menu_group_order' => 10, 'menu_item_order' => 25, 'updated_at' => $now, 'created_at' => $now,
        ]);
        $reportId = DB::table('report_definitions')->where('code', self::REPORT_CODE)->value('id');
        DB::table('report_definition_template')->updateOrInsert(['report_definition_id' => $reportId, 'template_id' => $templateId], ['is_default' => true, 'sort_order' => 0, 'updated_at' => $now, 'created_at' => $now]);
    }

    private function parameter(string $name, string $dataType, int $position): array
    {
        return ['name' => $name, 'mode' => 'IN', 'data_type' => $dataType, 'database_type' => $dataType === 'varchar' ? 'varchar(100)' : 'date', 'max_length' => $dataType === 'varchar' ? 100 : null, 'numeric_precision' => null, 'numeric_scale' => null, 'position' => $position, 'required' => true];
    }

    private function parameterUiSchema(): array
    {
        return [
            ['name' => 'p_from_date', 'label' => 'Từ ngày', 'control' => 'date-range', 'range_end_parameter' => 'p_to_date', 'default' => '$today', 'required' => true, 'options' => []],
            ['name' => 'p_to_date', 'label' => 'Đến ngày', 'control' => 'hidden', 'default' => '$today', 'required' => true, 'options' => []],
            ['name' => 'p_user', 'label' => 'Người thực hiện', 'control' => 'text', 'default' => '', 'required' => false, 'options' => []],
            ['name' => 'p_sort_by', 'label' => 'Sắp xếp theo', 'control' => 'select', 'default' => 'ArrivalDate1', 'required' => false, 'options' => [['value' => 'Room', 'label' => 'Phòng cũ'], ['value' => 'Room1', 'label' => 'Phòng mới'], ['value' => 'ArrivalDate', 'label' => 'Ngày đến ban đầu'], ['value' => 'ArrivalDate1', 'label' => 'Ngày chuyển']]],
            ['name' => 'p_order_type', 'label' => 'Thứ tự', 'control' => 'select', 'default' => 'ASC', 'required' => false, 'options' => [['value' => 'ASC', 'label' => 'Tăng dần'], ['value' => 'DESC', 'label' => 'Giảm dần']]],
        ];
    }
};
