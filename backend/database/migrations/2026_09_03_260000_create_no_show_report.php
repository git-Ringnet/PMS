<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SOURCE = 'NO_SHOW';
    private const REPORT = 'NO_SHOW';
    private const TEMPLATE = 'NO_SHOW_STANDARD';

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') return;

        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_no_show');
        DB::unprepared(<<<'SQL'
CREATE PROCEDURE rpt_no_show(
    IN p_from_date DATE,
    IN p_to_date DATE,
    IN p_type TINYINT,
    IN p_user VARCHAR(50),
    IN p_type_money VARCHAR(20),
    IN p_sort_type CHAR(4),
    IN p_division VARCHAR(20)
)
READS SQL DATA
BEGIN
    SELECT
        ROW_NUMBER() OVER (
            ORDER BY
                CASE WHEN UPPER(COALESCE(p_sort_type, 'ASC')) = 'DESC' THEN NULL END,
                CASE WHEN UPPER(COALESCE(p_sort_type, 'ASC')) <> 'DESC' THEN ns.noshow_date END ASC,
                CASE WHEN UPPER(COALESCE(p_sort_type, 'ASC')) = 'DESC' THEN ns.noshow_date END DESC,
                br.room_number, br.id
        ) AS STT,
        CASE WHEN charge.RoomId IS NULL THEN 'No Charge' ELSE 'Charge' END AS RoomType,
        br.room_number AS Room,
        CONCAT(COALESCE(hs.prefix_booking_id, 'GAL'), b.id) AS BookingId,
        b.booking_name AS BookingName,
        c.name AS Company,
        DATE_FORMAT(b.booking_date, '%d/%m/%Y') AS BookingDate,
        DATE_FORMAT(br.arrival_date, '%d/%m/%Y') AS ArrivalDate,
        br.ActutalNumOfDays AS NumOfDays,
        DATE_FORMAT(ns.noshow_date, '%d/%m/%Y') AS NoshowDate,
        ns.noshow_time AS NoshowTime,
        COALESCE(charge.Total, 0) AS Total,
        COALESCE(ns.username, '') AS Username,
        COALESCE(ns.shift, '') AS Ca,
        COALESCE(ns.reason, '') AS Reason,
        charge.ChargeDate AS Date,
        br.id AS Ma,
        hs.division AS Division
    FROM noshow_logs AS ns
    INNER JOIN booking_rooms AS br ON br.id = ns.booking_room_id
    INNER JOIN bookings AS b ON b.id = br.booking_id
    LEFT JOIN companies AS c ON c.id = b.company_id
    LEFT JOIN hotel_settings AS hs ON hs.id = (SELECT MIN(id) FROM hotel_settings)
    LEFT JOIN (
        SELECT
            COALESCE(sb.RentalRoomId1, sb.RentalRoomId2) AS RoomId,
            SUM(sb.Amount) AS Total,
            MAX(DATE(sb.Date)) AS ChargeDate
        FROM service_bills AS sb
        WHERE sb.ServiceId = 'RM'
          AND sb.Edit = 0
          AND sb.Date >= p_from_date
          AND sb.Date < DATE_ADD(p_to_date, INTERVAL 1 DAY)
        GROUP BY COALESCE(sb.RentalRoomId1, sb.RentalRoomId2)
    ) AS charge ON charge.RoomId = br.id
    WHERE br.status = 4
      AND ns.noshow_date IS NOT NULL
      AND (p_user IS NULL OR p_user = '' OR ns.username LIKE CONCAT('%', p_user, '%'))
      AND (p_division IS NULL OR p_division = '' OR p_division = '__current__' OR hs.division = p_division)
      AND (
          (ns.noshow_date >= p_from_date AND ns.noshow_date < DATE_ADD(p_to_date, INTERVAL 1 DAY))
          OR (charge.ChargeDate >= p_from_date AND charge.ChargeDate <= p_to_date)
      )
      AND (
          COALESCE(p_type, 2) = 2
          OR (COALESCE(p_type, 2) = 0 AND charge.RoomId IS NOT NULL)
          OR (COALESCE(p_type, 2) = 1 AND charge.RoomId IS NULL)
      )
    ORDER BY
        CASE WHEN UPPER(COALESCE(p_sort_type, 'ASC')) = 'DESC' THEN ns.noshow_date END DESC,
        CASE WHEN UPPER(COALESCE(p_sort_type, 'ASC')) <> 'DESC' THEN ns.noshow_date END ASC,
        br.room_number, br.id;
END
SQL);

        $now = now();
        $database = DB::connection()->getDatabaseName();
        $parameters = [
            ['name' => 'p_from_date', 'mode' => 'IN', 'data_type' => 'date', 'database_type' => 'date', 'position' => 1, 'required' => true],
            ['name' => 'p_to_date', 'mode' => 'IN', 'data_type' => 'date', 'database_type' => 'date', 'position' => 2, 'required' => true],
            ['name' => 'p_type', 'mode' => 'IN', 'data_type' => 'tinyint', 'database_type' => 'tinyint', 'position' => 3, 'required' => true],
            ['name' => 'p_user', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(50)', 'position' => 4, 'required' => false],
            ['name' => 'p_type_money', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(20)', 'position' => 5, 'required' => false],
            ['name' => 'p_sort_type', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'char(4)', 'position' => 6, 'required' => true],
            ['name' => 'p_division', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(20)', 'position' => 7, 'required' => false],
        ];
        $fields = ['STT', 'RoomType', 'Room', 'BookingId', 'BookingName', 'Company', 'BookingDate', 'ArrivalDate', 'NumOfDays', 'NoshowDate', 'NoshowTime', 'Total', 'Username', 'Ca', 'Reason', 'Date', 'Ma', 'Division'];
        $numeric = ['STT', 'NumOfDays'];
        $fieldSchema = array_map(fn (string $name) => [
            'name' => $name,
            'type' => in_array($name, $numeric, true) ? 'integer' : (in_array($name, ['Total'], true) ? 'number' : 'string'),
            'nullable' => !in_array($name, ['STT', 'Room', 'BookingId', 'BookingName', 'ArrivalDate', 'NoshowDate'], true),
        ], $fields);
        $defaults = ['p_from_date' => now()->toDateString(), 'p_to_date' => now()->toDateString(), 'p_type' => 2, 'p_user' => '', 'p_type_money' => '', 'p_sort_type' => 'ASC', 'p_division' => '__current__'];

        DB::table('report_data_sources')->updateOrInsert(['code' => self::SOURCE], [
            'name' => 'Dữ liệu báo cáo phòng No-show', 'description' => 'MySQL chuyển đổi từ ProVista sp_054 và sp_054_Division.',
            'source_type' => 'procedure', 'schema_name' => $database, 'object_name' => 'rpt_no_show',
            'parameter_schema' => json_encode($parameters, JSON_UNESCAPED_UNICODE), 'field_schema' => json_encode($fieldSchema, JSON_UNESCAPED_UNICODE),
            'sample_parameters' => json_encode($defaults), 'max_rows' => 5000, 'is_active' => true, 'last_discovered_at' => $now, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $sourceId = DB::table('report_data_sources')->where('code', self::SOURCE)->value('id');

        DB::table('templates')->updateOrInsert(['report' => self::TEMPLATE], [
            'group' => 'Báo cáo phòng', 'name' => 'Báo cáo phòng No-show', 'report_data_source_id' => $sourceId,
            'parameter_defaults' => json_encode($defaults), 'page_size' => 'A4', 'page_orientation' => 'landscape',
            'margin_top' => 8, 'margin_bottom' => 8, 'margin_left' => 5, 'margin_right' => 5,
            'content_json' => json_encode(['header' => [], 'detail' => [], 'footer' => []]), 'content_html' => '<h1>BÁO CÁO PHÒNG NO SHOW</h1>',
            'css' => '', 'is_default' => false, 'version' => '1.0', 'created_at' => $now, 'updated_at' => $now,
        ]);
        $templateId = DB::table('templates')->where('report', self::TEMPLATE)->value('id');

        DB::table('report_definitions')->updateOrInsert(['code' => self::REPORT], [
            'name' => 'Báo cáo phòng No-show', 'group' => 'Báo cáo phòng', 'description' => 'Danh sách phòng không đến theo sp_054.',
            'report_data_source_id' => $sourceId, 'parameter_ui_schema' => json_encode($this->uiSchema(), JSON_UNESCAPED_UNICODE),
            'sort_order' => 12, 'is_active' => true, 'show_in_menu' => true, 'menu_locations' => json_encode(['reservation', 'frontdesk']),
            'menu_top_order' => 30, 'menu_group_order' => 10, 'menu_item_order' => 12, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $reportId = DB::table('report_definitions')->where('code', self::REPORT)->value('id');
        DB::table('report_definition_template')->updateOrInsert(['report_definition_id' => $reportId, 'template_id' => $templateId], ['is_default' => true, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now]);
        (require database_path('report_templates/no_show_reference.php'))->apply();
    }

    private function uiSchema(): array
    {
        return [
            ['name' => 'p_from_date', 'label' => 'Ngày', 'control' => 'date-range', 'range_end_parameter' => 'p_to_date', 'default' => '$today', 'required' => true, 'options' => []],
            ['name' => 'p_to_date', 'label' => 'Đến ngày', 'control' => 'hidden', 'default' => '$today', 'required' => true, 'options' => []],
            ['name' => 'p_type', 'label' => 'Loại tiền', 'control' => 'radio', 'default' => 2, 'required' => true, 'options' => [['value' => 2, 'label' => 'All'], ['value' => 0, 'label' => 'Charge'], ['value' => 1, 'label' => 'No Charge']]],
            ['name' => 'p_user', 'label' => 'Người dùng', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'users', 'options' => []],
            ['name' => 'p_sort_type', 'label' => 'Sắp xếp theo ngày No-show', 'control' => 'select', 'default' => 'ASC', 'required' => true, 'options' => [['value' => 'ASC', 'label' => 'Tăng dần'], ['value' => 'DESC', 'label' => 'Giảm dần']]],
            ['name' => 'p_division', 'label' => 'Chi nhánh', 'control' => 'select', 'default' => '__current__', 'required' => false, 'options' => [['value' => '__all__', 'label' => 'Tất cả chi nhánh'], ['value' => '__current__', 'label' => 'Chi nhánh hiện tại']]],
            ['name' => 'p_type_money', 'label' => 'Loại tiền legacy', 'control' => 'hidden', 'default' => '', 'required' => false, 'options' => []],
        ];
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') return;
        $reportId = DB::table('report_definitions')->where('code', self::REPORT)->value('id');
        $templateId = DB::table('templates')->where('report', self::TEMPLATE)->value('id');
        if ($reportId) { DB::table('report_definition_template')->where('report_definition_id', $reportId)->delete(); DB::table('report_definitions')->where('id', $reportId)->delete(); }
        if ($templateId) DB::table('templates')->where('id', $templateId)->delete();
        DB::table('report_data_sources')->where('code', self::SOURCE)->delete();
        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_no_show');
    }
};
