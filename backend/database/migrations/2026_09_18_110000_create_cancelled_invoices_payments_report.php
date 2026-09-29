<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SOURCE = 'RPT_CANCELLED_INVOICES_PAYMENTS';
    private const REPORT = 'CANCELLED_INVOICES_PAYMENTS';
    private const TEMPLATE = 'CANCELLED_INVOICES_PAYMENTS_REFERENCE';

    public function up(): void
    {
        $visited = [];

        foreach ([DB::getDefaultConnection()] as $connectionName) {
            try {
                $connection = DB::connection($connectionName);
                if ($connection->getDriverName() !== 'mysql') {
                    continue;
                }

                $database = $connection->getDatabaseName();
                if (isset($visited[$database])) {
                    continue;
                }
                $visited[$database] = true;

                $connection->unprepared('DROP PROCEDURE IF EXISTS rpt_cancelled_invoices_payments');
                $connection->unprepared($this->procedureSql());
                $this->syncReportConfiguration($connectionName);
            } catch (\Throwable $exception) {
                // Keep the existing best-effort handling for an unavailable target database.
                report($exception);
            }
        }
    }

    public function procedureSql(): string
    {
        return <<<'SQL'
CREATE PROCEDURE rpt_cancelled_invoices_payments(
    IN p_mode VARCHAR(10),
    IN p_from_date VARCHAR(20),
    IN p_to_date VARCHAR(20),
    IN p_department VARCHAR(20),
    IN p_outlet VARCHAR(20),
    IN p_service VARCHAR(20),
    IN p_user VARCHAR(50),
    IN p_sort_by VARCHAR(20),
    IN p_sort_type VARCHAR(10)
)
READS SQL DATA
BEGIN
    DECLARE v_from DATE;
    DECLARE v_to DATE;
    DECLARE v_prefix VARCHAR(20) DEFAULT '';

    SELECT COALESCE(prefix_booking_id, '')
      INTO v_prefix
      FROM hotel_settings
      ORDER BY id
      LIMIT 1;

    IF p_from_date IS NULL OR p_from_date = '' THEN
        SET v_from = CURDATE();
    ELSEIF INSTR(p_from_date, '/') > 0 THEN
        SET v_from = STR_TO_DATE(LEFT(p_from_date, 10), '%d/%m/%Y');
    ELSE
        SET v_from = STR_TO_DATE(LEFT(p_from_date, 10), '%Y-%m-%d');
    END IF;

    IF p_to_date IS NULL OR p_to_date = '' THEN
        SET v_to = v_from;
    ELSEIF INSTR(p_to_date, '/') > 0 THEN
        SET v_to = STR_TO_DATE(LEFT(p_to_date, 10), '%d/%m/%Y');
    ELSE
        SET v_to = STR_TO_DATE(LEFT(p_to_date, 10), '%Y-%m-%d');
    END IF;

    IF UPPER(COALESCE(p_mode, 'BILL')) = 'BILL' THEN
        SELECT
            ROW_NUMBER() OVER (
                ORDER BY
                    DATE(duong.Date),
                    COALESCE(am.DepartmentId, ''),
                    CASE WHEN COALESCE(p_sort_by, 'Room') = 'CreatedHour'
                              AND UPPER(COALESCE(p_sort_type, 'ASC')) = 'DESC'
                         THEN COALESCE(am.OpenTime, '') END DESC,
                    CASE WHEN COALESCE(p_sort_by, 'Room') = 'CreatedHour'
                              AND UPPER(COALESCE(p_sort_type, 'ASC')) <> 'DESC'
                         THEN COALESCE(am.OpenTime, '') END ASC,
                    CASE WHEN COALESCE(p_sort_by, 'Room') = 'Room'
                              AND UPPER(COALESCE(p_sort_type, 'ASC')) = 'DESC'
                         THEN COALESCE(pt.room_number, '') END DESC,
                    CASE WHEN COALESCE(p_sort_by, 'Room') = 'Room'
                              AND UPPER(COALESCE(p_sort_type, 'ASC')) <> 'DESC'
                         THEN COALESCE(pt.room_number, '') END ASC,
                    duong.Ma
            ) AS `Index`,
            CASE
                WHEN pt.room_number IS NULL AND duong.RegisterID2 IS NOT NULL THEN CONCAT(v_prefix, duong.RegisterID2)
                WHEN pt.room_number IS NOT NULL AND duong.RegisterID2 IS NULL THEN pt.room_number
                WHEN pt.room_number IS NULL AND duong.RegisterID2 IS NULL THEN ''
                ELSE CONCAT(v_prefix, duong.RegisterID2, '/', pt.room_number)
            END AS Room1,
            COALESCE(NULLIF(srv.name, ''), duong.ServiceId, '') AS Service,
            DATE_FORMAT(am.Date, '%d/%m/%Y') AS CreatedDate,
            COALESCE(am.OpenTime, '') AS CreatedHour,
            ABS(COALESCE(am.TotalAmount0, am.Amount, 0)) AS AmountAm,
            COALESCE(NULLIF(am.CreatedUser, ''), am.Username, '') AS CreatedUser,
            DATE_FORMAT(duong.Date, '%d/%m/%Y') AS Date,
            COALESCE(duong.OpenTime, '') AS OpenTime,
            ABS(COALESCE(duong.TotalAmount0, duong.Amount, 0)) AS AmountDuong,
            COALESCE(duong.Username, '') AS Username,
            COALESCE(duong.DescriptionServive, '') AS Description,
            DATE_FORMAT(duong.Date, '%d/%m/%Y') AS InvoiceDateFormatted,
            COALESCE(am.DepartmentId, '') AS DepartmentId,
            COALESCE(NULLIF(dep.name, ''), am.DepartmentId, '') AS DepartmentName
        FROM service_bills AS duong
        INNER JOIN service_bills AS am
            ON duong.Pack1 = CAST(am.Ma AS CHAR)
            OR duong.AdjustmentBillId = am.Ma
        LEFT JOIN hotel_services AS srv ON srv.code = duong.ServiceId
        LEFT JOIN departments AS dep ON dep.code = am.DepartmentId
        LEFT JOIN booking_rooms AS pt ON pt.id = duong.RentalRoomId2
        WHERE duong.Status = 3
          AND duong.Edit = 1
          AND duong.Pack1 IS NOT NULL
          AND CAST(am.Date AS DATE) BETWEEN v_from AND v_to
          AND (COALESCE(p_department, '') = '' OR am.DepartmentId = p_department)
          AND (COALESCE(p_outlet, '') = '' OR duong.Outlet = p_outlet)
          AND (COALESCE(p_service, '') = '' OR duong.ServiceId = p_service)
          AND (COALESCE(p_user, '') = '' OR duong.Username LIKE CONCAT('%', p_user, '%'))
        ORDER BY `Index`;
    ELSE
        SELECT
            ROW_NUMBER() OVER (
                ORDER BY
                    DATE(duong.date),
                    COALESCE(am.department_id, ''),
                    CASE WHEN COALESCE(p_sort_by, 'Room') = 'CreatedHour'
                              AND UPPER(COALESCE(p_sort_type, 'ASC')) = 'DESC'
                         THEN COALESCE(am.open_time, '') END DESC,
                    CASE WHEN COALESCE(p_sort_by, 'Room') = 'CreatedHour'
                              AND UPPER(COALESCE(p_sort_type, 'ASC')) <> 'DESC'
                         THEN COALESCE(am.open_time, '') END ASC,
                    CASE WHEN COALESCE(p_sort_by, 'Room') = 'Room'
                              AND UPPER(COALESCE(p_sort_type, 'ASC')) = 'DESC'
                         THEN COALESCE(pt.room_number, '') END DESC,
                    CASE WHEN COALESCE(p_sort_by, 'Room') = 'Room'
                              AND UPPER(COALESCE(p_sort_type, 'ASC')) <> 'DESC'
                         THEN COALESCE(pt.room_number, '') END ASC,
                    duong.id
            ) AS `Index`,
            CASE
                WHEN pt.room_number IS NULL AND duong.booking_id IS NOT NULL THEN CONCAT(v_prefix, duong.booking_id)
                WHEN pt.room_number IS NOT NULL AND duong.booking_id IS NULL THEN pt.room_number
                WHEN pt.room_number IS NULL AND duong.booking_id IS NULL THEN ''
                ELSE CONCAT(v_prefix, duong.booking_id, '/', pt.room_number)
            END AS Room1,
            COALESCE(NULLIF(pm.name, ''), duong.payment_method_id, 'Cash') AS Service,
            DATE_FORMAT(COALESCE(am.created_at, am.date), '%d/%m/%Y') AS CreatedDate,
            COALESCE(am.open_time, '') AS CreatedHour,
            ABS(COALESCE(am.amount, 0)) AS AmountAm,
            COALESCE(NULLIF(am.created_by, ''), am.username, '') AS CreatedUser,
            DATE_FORMAT(duong.date, '%d/%m/%Y') AS Date,
            COALESCE(duong.open_time, '') AS OpenTime,
            ABS(COALESCE(duong.amount, 0)) AS AmountDuong,
            COALESCE(NULLIF(duong.created_by, ''), duong.username, '') AS Username,
            COALESCE(NULLIF(duong.reason, ''), duong.description, '') AS Description,
            DATE_FORMAT(duong.date, '%d/%m/%Y') AS InvoiceDateFormatted,
            COALESCE(am.department_id, '') AS DepartmentId,
            COALESCE(NULLIF(dep.name, ''), am.department_id, '') AS DepartmentName
        FROM payments AS duong
        INNER JOIN payments AS am
            ON duong.reversal_ref = am.id
            OR duong.reversal_ref = am.legacy_id
        LEFT JOIN payment_methods AS pm ON pm.code = duong.payment_method_id
        LEFT JOIN departments AS dep ON dep.code = am.department_id
        LEFT JOIN booking_rooms AS pt ON pt.id = duong.booking_room_id
        WHERE duong.status = 3
          AND duong.edit_flag = 1
          AND duong.deleted_at IS NULL
          AND CAST(COALESCE(am.created_at, am.date) AS DATE) BETWEEN v_from AND v_to
          AND (COALESCE(p_department, '') = '' OR am.department_id = p_department)
          AND (COALESCE(p_outlet, '') = '' OR duong.outlet = p_outlet)
          AND (COALESCE(p_user, '') = '' OR COALESCE(duong.created_by, duong.username, '') LIKE CONCAT('%', p_user, '%'))
        ORDER BY `Index`;
    END IF;
END
SQL;
    }

    private function syncReportConfiguration(string $connectionName): void
    {
        $now = now();
        $db = DB::connection($connectionName);
        $database = $db->getDatabaseName();

        $parameters = [
            ['name' => 'p_mode', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(10)', 'position' => 1, 'required' => true],
            ['name' => 'p_from_date', 'mode' => 'IN', 'data_type' => 'date', 'database_type' => 'varchar(20)', 'position' => 2, 'required' => true],
            ['name' => 'p_to_date', 'mode' => 'IN', 'data_type' => 'date', 'database_type' => 'varchar(20)', 'position' => 3, 'required' => true],
            ['name' => 'p_department', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(20)', 'position' => 4, 'required' => false],
            ['name' => 'p_outlet', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(20)', 'position' => 5, 'required' => false],
            ['name' => 'p_service', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(20)', 'position' => 6, 'required' => false],
            ['name' => 'p_user', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(50)', 'position' => 7, 'required' => false],
            ['name' => 'p_sort_by', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(20)', 'position' => 8, 'required' => true],
            ['name' => 'p_sort_type', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(10)', 'position' => 9, 'required' => true],
        ];
        $fields = collect(['Index', 'Room1', 'Service', 'CreatedDate', 'CreatedHour', 'AmountAm', 'CreatedUser', 'Date', 'OpenTime', 'AmountDuong', 'Username', 'Description', 'InvoiceDateFormatted', 'DepartmentId', 'DepartmentName'])
            ->map(fn (string $name) => [
                'name' => $name,
                'type' => in_array($name, ['Index'], true) ? 'integer' : (in_array($name, ['AmountAm', 'AmountDuong'], true) ? 'number' : 'string'),
                'nullable' => ! in_array($name, ['Index', 'Room1', 'Service', 'Date'], true),
            ])->all();
        $defaults = [
            'p_mode' => 'BILL',
            'p_from_date' => now()->toDateString(),
            'p_to_date' => now()->toDateString(),
            'p_department' => '',
            'p_outlet' => '',
            'p_service' => '',
            'p_user' => '',
            'p_sort_by' => 'Room',
            'p_sort_type' => 'ASC',
        ];

        $db->table('report_data_sources')->updateOrInsert(['code' => self::SOURCE], [
            'name' => 'Dữ liệu báo cáo hủy hóa đơn/thanh toán',
            'description' => 'MySQL chuyển đổi từ legacy sp_068 và sp_070.',
            'source_type' => 'procedure',
            'schema_name' => $database,
            'object_name' => 'rpt_cancelled_invoices_payments',
            'parameter_schema' => json_encode($parameters, JSON_UNESCAPED_UNICODE),
            'field_schema' => json_encode($fields, JSON_UNESCAPED_UNICODE),
            'sample_parameters' => json_encode($defaults, JSON_UNESCAPED_UNICODE),
            'max_rows' => 5000,
            'is_active' => true,
            'last_discovered_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $sourceId = $db->table('report_data_sources')->where('code', self::SOURCE)->value('id');

        $definition = (require database_path('report_templates/cancelled_invoices_payments_reference.php'))->definition();
        $db->table('templates')->updateOrInsert(['report' => self::TEMPLATE], [
            'group' => 'Báo cáo thống kê lễ tân',
            'name' => $definition['name'],
            'report_data_source_id' => $sourceId,
            'parameter_defaults' => json_encode($defaults, JSON_UNESCAPED_UNICODE),
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
        $templateId = $db->table('templates')->where('report', self::TEMPLATE)->value('id');

        $ui = [
            ['name' => 'p_mode', 'label' => 'Chế độ xem', 'control' => 'radio', 'default' => 'BILL', 'required' => true, 'options' => [['value' => 'BILL', 'label' => 'Hủy hóa đơn'], ['value' => 'PAYMENT', 'label' => 'Hủy thanh toán']]],
            ['name' => 'p_from_date', 'label' => 'Ngày', 'control' => 'date-range', 'range_end_parameter' => 'p_to_date', 'default' => '$today', 'required' => true],
            ['name' => 'p_to_date', 'label' => 'Đến ngày', 'control' => 'hidden', 'default' => '$today', 'required' => true],
            ['name' => 'p_department', 'label' => 'Bộ phận', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'service-departments'],
            ['name' => 'p_outlet', 'label' => 'Outlet', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'outlets'],
            ['name' => 'p_service', 'label' => 'Dịch vụ', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'hotel-services'],
            ['name' => 'p_user', 'label' => 'Người dùng', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'users'],
            ['name' => 'p_sort_by', 'label' => 'Sắp xếp theo', 'control' => 'select', 'default' => 'Room', 'required' => true, 'options' => [['value' => 'Room', 'label' => 'Phòng'], ['value' => 'CreatedHour', 'label' => 'Giờ tạo']]],
            ['name' => 'p_sort_type', 'label' => 'Thứ tự', 'control' => 'select', 'default' => 'ASC', 'required' => true, 'options' => [['value' => 'ASC', 'label' => 'ASC'], ['value' => 'DESC', 'label' => 'DESC']]],
        ];

        $db->table('report_definitions')->updateOrInsert(['code' => self::REPORT], [
            'name' => 'Báo cáo hủy hóa đơn/thanh toán',
            'group' => 'Báo cáo thống kê lễ tân',
            'description' => 'Báo cáo hủy hóa đơn và hủy thanh toán theo legacy sp_068/sp_070.',
            'report_data_source_id' => $sourceId,
            'parameter_ui_schema' => json_encode($ui, JSON_UNESCAPED_UNICODE),
            'sort_order' => 45,
            'is_active' => true,
            'show_in_menu' => true,
            'menu_locations' => json_encode(['frontdesk', 'reservation']),
            'menu_top_order' => 20,
            'menu_group_order' => 30,
            'menu_item_order' => 45,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $reportId = $db->table('report_definitions')->where('code', self::REPORT)->value('id');
        $db->table('report_definition_template')->updateOrInsert(
            ['report_definition_id' => $reportId, 'template_id' => $templateId],
            ['is_default' => true, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now]
        );
    }

    public function down(): void
    {
        foreach ([DB::getDefaultConnection()] as $connectionName) {
            try {
                $db = DB::connection($connectionName);
                if ($db->getDriverName() !== 'mysql') {
                    continue;
                }
                $reportId = $db->table('report_definitions')->where('code', self::REPORT)->value('id');
                $templateId = $db->table('templates')->where('report', self::TEMPLATE)->value('id');
                if ($reportId) {
                    $db->table('report_definition_template')->where('report_definition_id', $reportId)->delete();
                    $db->table('report_definitions')->where('id', $reportId)->delete();
                }
                if ($templateId) {
                    $db->table('templates')->where('id', $templateId)->delete();
                }
                $db->table('report_data_sources')->where('code', self::SOURCE)->delete();
                $db->unprepared('DROP PROCEDURE IF EXISTS rpt_cancelled_invoices_payments');
            } catch (\Throwable $exception) {
                report($exception);
            }
        }
    }
};
