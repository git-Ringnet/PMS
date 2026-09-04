<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SOURCE = 'NO_SHOW_BY_DAY';
    private const REPORT = 'NO_SHOW_BY_DAY';
    private const TEMPLATE = 'NO_SHOW_BY_DAY_STANDARD';

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') return;

        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_no_show_by_day');
        DB::unprepared(<<<'SQL'
CREATE PROCEDURE rpt_no_show_by_day(
    IN p_from_date DATE,
    IN p_to_date DATE,
    IN p_type TINYINT,
    IN p_user VARCHAR(50),
    IN p_type_money VARCHAR(20),
    IN p_sort_type CHAR(4),
    IN p_booking VARCHAR(20),
    IN p_division VARCHAR(20)
)
READS SQL DATA
BEGIN
    SELECT
        ROW_NUMBER() OVER (
            ORDER BY
                CASE WHEN UPPER(COALESCE(p_sort_type, 'ASC')) = 'DESC' THEN lc.late_checkin_date END DESC,
                CASE WHEN UPPER(COALESCE(p_sort_type, 'ASC')) <> 'DESC' THEN lc.late_checkin_date END ASC,
                br.room_number, br.id
        ) AS STT,
        CASE WHEN charge.BillId IS NULL THEN 'No Charge' ELSE 'Charge' END AS RoomType,
        br.room_number AS Room,
        CONCAT(COALESCE(hs.prefix_booking_id, ''), b.id) AS BookingId,
        b.booking_name AS BookingName,
        c.name AS Company,
        DATE_FORMAT(b.booking_date, '%d/%m/%Y') AS BookingDate,
        DATE_FORMAT(lc.actual_arrival_date, '%d/%m/%Y') AS ArrivalDate,
        DATE_FORMAT(br.CheckoutDate, '%d/%m/%Y') AS CheckoutDate,
        br.ActutalNumOfDays AS NumOfDays,
        DATE_FORMAT(lc.late_checkin_date, '%d/%m/%Y') AS LateCheckInDate,
        lc.late_checkin_time AS LateCheckInTime,
        CASE
            WHEN UPPER(COALESCE(p_sort_type, 'ASC')) = 'DESC'
                THEN LPAD(99999999 - CAST(DATE_FORMAT(lc.late_checkin_date, '%Y%m%d') AS UNSIGNED), 8, '0')
            ELSE DATE_FORMAT(lc.late_checkin_date, '%Y%m%d')
        END AS DateSortKey,
        billing.service_date AS Date,
        COALESCE(billing.Total, 0) AS Total,
        lc.username AS Username,
        lc.shift AS Ca,
        lc.reason AS Reason,
        charge.BillId,
        br.id AS Ma,
        hs.division AS Division
    FROM late_checkins lc
    INNER JOIN booking_rooms br ON br.id = lc.booking_room_id
    INNER JOIN bookings b ON b.id = br.booking_id
    LEFT JOIN companies c ON c.id = b.company_id
    LEFT JOIN hotel_settings hs ON hs.id = (SELECT MIN(id) FROM hotel_settings)
    LEFT JOIN (
        SELECT booking_room_id, service_date, SUM(total_amount) AS Total
        FROM booking_room_services
        WHERE service_code = 'RM' AND deleted_at IS NULL
        GROUP BY booking_room_id, service_date
    ) billing ON billing.booking_room_id = br.id
        AND billing.service_date = DATE(lc.late_checkin_date)
    LEFT JOIN (
        SELECT
            sb.RentalRoomId1 AS RoomId,
            DATE(sb.Date) AS ChargeDate,
            MAX(sb.Ma) AS BillId,
            SUM(sb.Amount) AS Total
        FROM service_bills sb
        INNER JOIN room_night_bills rnb ON rnb.bill_id = sb.Ma AND rnb.is_room_night = 1
        WHERE sb.ServiceId = 'RM' AND sb.Edit = 0
        GROUP BY sb.RentalRoomId1, DATE(sb.Date)
    ) charge ON charge.RoomId = br.id
        AND charge.ChargeDate = billing.service_date
    WHERE lc.late_checkin_date >= p_from_date
      AND lc.late_checkin_date < DATE_ADD(p_to_date, INTERVAL 1 DAY)
      AND (p_user IS NULL OR p_user = '' OR br.created_by LIKE CONCAT('%', p_user, '%'))
      AND (p_booking IS NULL OR p_booking = '' OR CAST(b.id AS CHAR) = p_booking)
      AND (p_division IS NULL OR p_division = '' OR p_division = '__current__' OR hs.division = p_division)
      AND (
          COALESCE(p_type, 2) = 2
          OR (COALESCE(p_type, 2) = 0 AND charge.BillId IS NOT NULL)
          OR (COALESCE(p_type, 2) = 1 AND charge.BillId IS NULL)
      )
    ORDER BY
        CASE WHEN UPPER(COALESCE(p_sort_type, 'ASC')) = 'DESC' THEN lc.late_checkin_date END DESC,
        CASE WHEN UPPER(COALESCE(p_sort_type, 'ASC')) <> 'DESC' THEN lc.late_checkin_date END ASC,
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
            ['name' => 'p_booking', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(20)', 'position' => 7, 'required' => false],
            ['name' => 'p_division', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(20)', 'position' => 8, 'required' => false],
        ];
        $fields = ['STT', 'RoomType', 'Room', 'BookingId', 'BookingName', 'Company', 'BookingDate', 'ArrivalDate', 'CheckoutDate', 'NumOfDays', 'LateCheckInDate', 'LateCheckInTime', 'DateSortKey', 'Date', 'Total', 'Username', 'Ca', 'Reason', 'BillId', 'Ma', 'Division'];
        $fieldSchema = array_map(fn (string $name) => [
            'name' => $name,
            'type' => in_array($name, ['STT', 'NumOfDays', 'BillId'], true) ? 'integer' : (in_array($name, ['Total'], true) ? 'number' : 'string'),
            'nullable' => !in_array($name, ['STT', 'Room', 'BookingId', 'BookingName', 'LateCheckInDate'], true),
        ], $fields);
        $defaults = ['p_from_date' => now()->toDateString(), 'p_to_date' => now()->toDateString(), 'p_type' => 2, 'p_user' => '', 'p_type_money' => '', 'p_sort_type' => 'ASC', 'p_booking' => '', 'p_division' => '__current__'];

        DB::table('report_data_sources')->updateOrInsert(['code' => self::SOURCE], [
            'name' => 'Dữ liệu báo cáo phòng No Show theo ngày',
            'description' => 'MySQL chuyển đổi từ ProVista sp_056 và sp_056_Division.',
            'source_type' => 'procedure', 'schema_name' => $database, 'object_name' => 'rpt_no_show_by_day',
            'parameter_schema' => json_encode($parameters, JSON_UNESCAPED_UNICODE), 'field_schema' => json_encode($fieldSchema, JSON_UNESCAPED_UNICODE),
            'sample_parameters' => json_encode($defaults), 'max_rows' => 5000, 'is_active' => true, 'last_discovered_at' => $now, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $sourceId = DB::table('report_data_sources')->where('code', self::SOURCE)->value('id');
        DB::table('report_definitions')->updateOrInsert(['code' => self::REPORT], [
            'name' => 'Báo cáo phòng No Show theo ngày', 'group' => 'Báo cáo phòng', 'description' => 'Danh sách phòng No Show theo ngày theo ProVista sp_056.',
            'report_data_source_id' => $sourceId, 'parameter_ui_schema' => json_encode($this->uiSchema(), JSON_UNESCAPED_UNICODE),
            'sort_order' => 13, 'is_active' => true, 'show_in_menu' => true, 'menu_locations' => json_encode(['reservation', 'frontdesk']),
            'menu_top_order' => 30, 'menu_group_order' => 10, 'menu_item_order' => 13, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $reportId = DB::table('report_definitions')->where('code', self::REPORT)->value('id');
        (require database_path('report_templates/no_show_by_day_reference.php'))->apply($sourceId);
        $templateId = DB::table('templates')->where('report', self::TEMPLATE)->value('id');
        DB::table('report_definition_template')->updateOrInsert(['report_definition_id' => $reportId, 'template_id' => $templateId], ['is_default' => true, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now]);
    }

    private function uiSchema(): array
    {
        return [
            ['name' => 'p_from_date', 'label' => 'Ngày', 'control' => 'date-range', 'range_end_parameter' => 'p_to_date', 'default' => '$today', 'required' => true, 'options' => []],
            ['name' => 'p_to_date', 'label' => 'Đến ngày', 'control' => 'hidden', 'default' => '$today', 'required' => true, 'options' => []],
            ['name' => 'p_division', 'label' => 'Chi nhánh', 'control' => 'select', 'default' => '__current__', 'required' => false, 'options' => [['value' => '__all__', 'label' => 'Tất cả chi nhánh'], ['value' => '__current__', 'label' => 'Chi nhánh hiện tại']]],
            ['name' => 'p_booking', 'label' => 'Chọn đăng ký', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'bookings', 'options' => []],
            ['name' => 'p_user', 'label' => 'Người dùng', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'users', 'options' => []],
            ['name' => 'p_sort_type', 'label' => 'Sắp xếp theo ngày Check-in', 'control' => 'select', 'default' => 'ASC', 'required' => true, 'options' => [['value' => 'ASC', 'label' => 'Tăng dần'], ['value' => 'DESC', 'label' => 'Giảm dần']]],
            ['name' => 'p_type', 'label' => 'Loại tiền', 'control' => 'radio', 'default' => 2, 'required' => true, 'options' => [['value' => 2, 'label' => 'Tất cả'], ['value' => 0, 'label' => 'Charge'], ['value' => 1, 'label' => 'No Charge']]],
            ['name' => 'p_type_money', 'label' => 'Loại tiền legacy', 'control' => 'hidden', 'default' => '', 'required' => false, 'options' => []],
        ];
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') return;
        $reportId = DB::table('report_definitions')->where('code', self::REPORT)->value('id');
        $templateId = DB::table('templates')->where('report', self::TEMPLATE)->value('id');
        if ($reportId) DB::table('report_definition_template')->where('report_definition_id', $reportId)->delete();
        if ($reportId) DB::table('report_definitions')->where('id', $reportId)->delete();
        if ($templateId) DB::table('templates')->where('id', $templateId)->delete();
        DB::table('report_data_sources')->where('code', self::SOURCE)->delete();
        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_no_show_by_day');
    }
};
