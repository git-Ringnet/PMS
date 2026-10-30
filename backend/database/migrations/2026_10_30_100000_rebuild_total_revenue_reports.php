<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Rebuilds "Báo cáo tổng doanh thu" after the legacy report (sp_018 day rows,
 * sp_021 MTD, sp_036 YTD, sp_038 CTM, sp_044 chart) and adds its drill-down
 * target "Báo cáo doanh thu dịch vụ chi tiết" (sp_134).
 *
 * rpt_total_revenue returns raw amounts per period/date/service code; the
 * column mapping lives in App\Services\Reports\TotalRevenueDataAdapter.
 */
return new class extends Migration
{
    private const TOTAL_SOURCE = 'RPT_TOTAL_REVENUE';
    private const TOTAL_REPORT = 'TOTAL_REVENUE';
    private const TOTAL_TEMPLATE = 'TOTAL_REVENUE_REFERENCE';
    private const DETAIL_SOURCE = 'RPT_SERVICE_REVENUE_DETAIL';
    private const DETAIL_REPORT = 'SERVICE_REVENUE_DETAIL';
    private const DETAIL_TEMPLATE = 'SERVICE_REVENUE_DETAIL_REFERENCE';
    private const GROUP = 'Báo cáo thống kê lễ tân';

    public function up(): void
    {
        $connectionName = DB::getDefaultConnection();

        try {
            $db = DB::connection($connectionName);
            if ($db->getDriverName() !== 'mysql') {
                return;
            }

            $db->unprepared('DROP PROCEDURE IF EXISTS `rpt_total_revenue`');
            $db->unprepared($this->totalProcedureSql());
            $db->unprepared('DROP PROCEDURE IF EXISTS `rpt_service_revenue_detail`');
            $db->unprepared($this->detailProcedureSql());
            $this->syncTotalRevenue($connectionName);
            $this->syncServiceRevenueDetail($connectionName);
        } catch (Throwable $exception) {
            Log::warning('Total revenue report rebuild skipped unavailable branch connection.', [
                'connection' => $connectionName,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function totalProcedureSql(): string
    {
        return <<<'SQL'
CREATE PROCEDURE `rpt_total_revenue`(
    IN p_from_date DATE,
    IN p_to_date DATE,
    IN p_view_by_time TINYINT,
    IN p_from_time VARCHAR(5),
    IN p_to_time VARCHAR(5),
    IN p_department VARCHAR(10),
    IN p_outlet VARCHAR(10),
    IN p_user VARCHAR(50),
    IN p_view_free TINYINT,
    IN p_amount_type VARCHAR(10)
)
READS SQL DATA
BEGIN
    /* p_amount_type (TOTAL / NOT_VAT) is applied by TotalRevenueDataAdapter:
       both amount and original_rate are returned below. */
    DECLARE v_to_date DATE;
    DECLARE v_from_date DATE;
    DECLARE v_by_time TINYINT;
    DECLARE v_from_time TIME;
    DECLARE v_to_time TIME;
    DECLARE v_month_start DATE;
    DECLARE v_year_start DATE;

    SET v_to_date = COALESCE(p_to_date, p_from_date, CURRENT_DATE());
    SET v_from_date = LEAST(COALESCE(p_from_date, v_to_date), v_to_date);
    SET v_by_time = IF(COALESCE(p_view_by_time, 0) = 1, 1, 0);
    SET v_from_time = IF(v_by_time = 1, COALESCE(TIME(NULLIF(TRIM(p_from_time), '')), '00:00:00'), '00:00:00');
    SET v_to_time = IF(v_by_time = 1, COALESCE(TIME(NULLIF(TRIM(p_to_time), '')), '23:59:00'), '23:59:00');
    SET v_month_start = DATE_SUB(v_to_date, INTERVAL DAYOFMONTH(v_to_date) - 1 DAY);
    SET v_year_start = MAKEDATE(YEAR(v_to_date), 1);

    WITH bill_lines AS (
        SELECT
            sb.PaymentId AS payment_id,
            TIMESTAMP(DATE(sb.Date), COALESCE(TIME(NULLIF(TRIM(sb.OpenTime), '')), '00:00:00')) AS bill_at,
            CASE
                WHEN v_by_time = 1 AND COALESCE(TIME(NULLIF(TRIM(sb.OpenTime), '')), '00:00:00') >= v_from_time
                    THEN DATE_ADD(DATE(sb.Date), INTERVAL 1 DAY)
                ELSE DATE(sb.Date)
            END AS business_date,
            UPPER(TRIM(COALESCE(d.ServiceId, ''))) AS service_id,
            UPPER(TRIM(COALESCE(d.DepartmentId, ''))) AS department_id,
            CASE WHEN UPPER(TRIM(COALESCE(sb.ServiceId, ''))) = 'RM' THEN 1 ELSE 0 END AS is_room_bill,
            COALESCE(d.Amount, 0) AS amount,
            COALESCE(d.OriginalRate, 0) AS original_rate,
            COALESCE(d.ServiceChargeAmount, 0) AS service_charge_amount,
            COALESCE(d.TaxAmount, 0) AS tax_amount
        FROM service_bills AS sb
        INNER JOIN service_bill_details AS d
            ON d.BillServiceId = sb.Ma
        WHERE COALESCE(sb.Edit, 0) = 0
          AND sb.Date >= DATE_SUB(LEAST(v_year_start, v_from_date), INTERVAL 1 DAY)
          AND sb.Date < DATE_ADD(v_to_date, INTERVAL 1 DAY)
          AND (COALESCE(TRIM(p_department), '') = '' OR sb.DepartmentId = TRIM(p_department))
          AND (COALESCE(TRIM(p_outlet), '') = '' OR sb.Outlet = TRIM(p_outlet))
          AND (COALESCE(TRIM(p_user), '') = '' OR sb.Username = TRIM(p_user))
          AND (
              sb.PaymentId IS NULL
              OR EXISTS (
                  SELECT 1
                  FROM payments AS p
                  LEFT JOIN payment_methods AS pm
                      ON pm.code = p.payment_method_id
                  WHERE p.payment_id = sb.PaymentId
                    AND COALESCE(p.edit_flag, 0) = 0
                    AND p.deleted_at IS NULL
                    AND (COALESCE(p_view_free, 0) = 1 OR COALESCE(pm.is_free, 0) = 0)
              )
          )
    ),
    windows AS (
        SELECT 'DAY' AS period_code, 1 AS period_order,
               TIMESTAMP(DATE_SUB(v_from_date, INTERVAL v_by_time DAY), v_from_time) AS window_from,
               TIMESTAMP(v_to_date, v_to_time) AS window_to,
               0 AS unpaid_only
        UNION ALL
        SELECT 'MTD', 2, TIMESTAMP(DATE_SUB(v_month_start, INTERVAL v_by_time DAY), v_from_time), TIMESTAMP(v_to_date, v_to_time), 0
        UNION ALL
        SELECT 'YTD', 3, TIMESTAMP(DATE_SUB(v_year_start, INTERVAL v_by_time DAY), v_from_time), TIMESTAMP(v_to_date, v_to_time), 0
        UNION ALL
        SELECT 'CTM', 4, TIMESTAMP(DATE_SUB(v_month_start, INTERVAL v_by_time DAY), v_from_time), TIMESTAMP(v_to_date, v_to_time), 1
    )
    SELECT
        w.period_code,
        CASE WHEN w.period_code = 'DAY' THEN DATE_FORMAT(bl.business_date, '%Y-%m-%d') ELSE '' END AS business_date,
        bl.service_id,
        bl.department_id,
        bl.is_room_bill,
        ROUND(SUM(bl.amount), 2) AS amount,
        ROUND(SUM(bl.original_rate), 2) AS original_rate,
        ROUND(SUM(bl.service_charge_amount), 2) AS service_charge_amount,
        ROUND(SUM(bl.tax_amount), 2) AS tax_amount
    FROM windows AS w
    INNER JOIN bill_lines AS bl
        ON bl.bill_at BETWEEN w.window_from AND w.window_to
       AND (w.unpaid_only = 0 OR bl.payment_id IS NULL)
    GROUP BY
        w.period_code, w.period_order,
        CASE WHEN w.period_code = 'DAY' THEN DATE_FORMAT(bl.business_date, '%Y-%m-%d') ELSE '' END,
        bl.service_id, bl.department_id, bl.is_room_bill
    ORDER BY w.period_order, business_date, bl.service_id, bl.department_id, bl.is_room_bill;
END
SQL;
    }

    public function detailProcedureSql(): string
    {
        return <<<'SQL'
CREATE PROCEDURE `rpt_service_revenue_detail`(
    IN p_from_date DATE,
    IN p_to_date DATE,
    IN p_view_by_time TINYINT,
    IN p_from_time VARCHAR(5),
    IN p_to_time VARCHAR(5),
    IN p_department VARCHAR(10),
    IN p_outlet VARCHAR(10),
    IN p_user VARCHAR(50),
    IN p_services VARCHAR(2000),
    IN p_view_free TINYINT,
    IN p_unpaid_only TINYINT
)
READS SQL DATA
BEGIN
    DECLARE v_to_date DATE;
    DECLARE v_from_date DATE;
    DECLARE v_by_time TINYINT;
    DECLARE v_from_time TIME;
    DECLARE v_to_time TIME;
    DECLARE v_services VARCHAR(2000);

    SET v_to_date = COALESCE(p_to_date, p_from_date, CURRENT_DATE());
    SET v_from_date = LEAST(COALESCE(p_from_date, v_to_date), v_to_date);
    SET v_by_time = IF(COALESCE(p_view_by_time, 0) = 1, 1, 0);
    SET v_from_time = IF(v_by_time = 1, COALESCE(TIME(NULLIF(TRIM(p_from_time), '')), '00:00:00'), '00:00:00');
    SET v_to_time = IF(v_by_time = 1, COALESCE(TIME(NULLIF(TRIM(p_to_time), '')), '23:59:00'), '23:59:00');
    SET v_services = UPPER(REPLACE(COALESCE(p_services, ''), ' ', ''));

    WITH bill_lines AS (
        SELECT
            sb.Ma AS bill_id,
            sb.PaymentId AS payment_id,
            DATE(sb.Date) AS bill_date,
            COALESCE(NULLIF(sb.RegisterID2, 0), br.booking_id) AS booking_id,
            br.room_number,
            COALESCE(br.arrival_date, b.arrival_date) AS arrival_date,
            COALESCE(br.departure_date, b.departure_date) AS departure_date,
            COALESCE(NULLIF(sb.Guest, ''), NULLIF(b.booking_name, ''), '') AS guest_name,
            COALESCE(sb.DescriptionServive, '') AS description,
            UPPER(TRIM(COALESCE(sb.Outlet, ''))) AS outlet_code,
            COALESCE(sb.Username, '') AS username,
            CASE
                WHEN UPPER(TRIM(COALESCE(d.ServiceId, ''))) = 'BF'
                     AND UPPER(TRIM(COALESCE(sb.ServiceId, ''))) <> 'RM'
                    THEN 'BS'
                ELSE UPPER(TRIM(COALESCE(d.ServiceId, '')))
            END AS service_id,
            COALESCE(d.OriginalRate, 0) AS original_rate,
            COALESCE(d.ServiceChargeAmount, 0) AS service_charge_amount,
            COALESCE(d.TaxAmount, 0) AS tax_amount,
            COALESCE(d.Amount, 0) AS amount
        FROM service_bills AS sb
        INNER JOIN service_bill_details AS d
            ON d.BillServiceId = sb.Ma
        LEFT JOIN booking_rooms AS br
            ON br.id = COALESCE(NULLIF(sb.RentalRoomId2, ''), NULLIF(sb.RentalRoomId1, ''))
        LEFT JOIN bookings AS b
            ON b.id = COALESCE(NULLIF(sb.RegisterID2, 0), br.booking_id)
        WHERE COALESCE(sb.Edit, 0) = 0
          AND sb.Date >= DATE_SUB(v_from_date, INTERVAL 1 DAY)
          AND sb.Date < DATE_ADD(v_to_date, INTERVAL 1 DAY)
          AND TIMESTAMP(DATE(sb.Date), COALESCE(TIME(NULLIF(TRIM(sb.OpenTime), '')), '00:00:00'))
              BETWEEN TIMESTAMP(DATE_SUB(v_from_date, INTERVAL v_by_time DAY), v_from_time)
                  AND TIMESTAMP(v_to_date, v_to_time)
          AND (COALESCE(TRIM(p_department), '') = '' OR sb.DepartmentId = TRIM(p_department))
          AND (COALESCE(TRIM(p_outlet), '') = '' OR sb.Outlet = TRIM(p_outlet))
          AND (COALESCE(TRIM(p_user), '') = '' OR sb.Username = TRIM(p_user))
          AND (COALESCE(p_unpaid_only, 0) = 0 OR sb.PaymentId IS NULL)
          AND (
              sb.PaymentId IS NULL
              OR EXISTS (
                  SELECT 1
                  FROM payments AS p
                  LEFT JOIN payment_methods AS pm
                      ON pm.code = p.payment_method_id
                  WHERE p.payment_id = sb.PaymentId
                    AND COALESCE(p.edit_flag, 0) = 0
                    AND p.deleted_at IS NULL
                    AND (COALESCE(p_view_free, 0) = 1 OR COALESCE(pm.is_free, 0) = 0)
              )
          )
    ),
    grouped AS (
        SELECT
            bl.bill_id, bl.payment_id, bl.bill_date, bl.booking_id, bl.room_number,
            bl.arrival_date, bl.departure_date, bl.guest_name, bl.description,
            bl.outlet_code, bl.username, bl.service_id,
            SUM(bl.original_rate) AS original_rate,
            SUM(bl.service_charge_amount) AS service_charge_amount,
            SUM(bl.tax_amount) AS tax_amount,
            SUM(bl.amount) AS amount
        FROM bill_lines AS bl
        WHERE v_services = '' OR FIND_IN_SET(bl.service_id, v_services) > 0
        GROUP BY
            bl.bill_id, bl.payment_id, bl.bill_date, bl.booking_id, bl.room_number,
            bl.arrival_date, bl.departure_date, bl.guest_name, bl.description,
            bl.outlet_code, bl.username, bl.service_id
    ),
    payment_modes AS (
        SELECT
            p.payment_id,
            GROUP_CONCAT(DISTINCT p.payment_method_id ORDER BY p.payment_method_id SEPARATOR ', ') AS payment_mode
        FROM payments AS p
        WHERE p.payment_id IN (SELECT DISTINCT g.payment_id FROM grouped AS g WHERE g.payment_id IS NOT NULL)
          AND COALESCE(p.edit_flag, 0) = 0
          AND p.deleted_at IS NULL
        GROUP BY p.payment_id
    )
    SELECT
        ROW_NUMBER() OVER (ORDER BY g.service_id, g.bill_date, g.bill_id) AS STT,
        g.bill_id AS BillId,
        DATE_FORMAT(g.bill_date, '%d/%m/%Y') AS BillDate,
        CASE WHEN g.booking_id IS NULL THEN '' ELSE CONCAT(COALESCE(hs.prefix_booking_id, ''), g.booking_id) END AS BookingCode,
        COALESCE(g.room_number, '') AS Room,
        g.guest_name AS GuestName,
        COALESCE(DATE_FORMAT(g.arrival_date, '%d/%m/%Y'), '') AS ArrivalDate,
        COALESCE(DATE_FORMAT(g.departure_date, '%d/%m/%Y'), '') AS DepartureDate,
        g.service_id AS ServiceId,
        CASE
            WHEN g.service_id = 'BF' THEN CONCAT('BF - ', COALESCE(srv.name, 'Ăn sáng'), ' (tách từ tiền phòng)')
            WHEN g.service_id = 'BS' THEN CONCAT('BF - ', COALESCE(bf.name, 'Ăn sáng'))
            WHEN srv.name IS NULL OR srv.name = '' THEN g.service_id
            ELSE CONCAT(g.service_id, ' - ', srv.name)
        END AS ServiceName,
        g.description AS Description,
        ROUND(g.original_rate, 0) AS OriginalRate,
        ROUND(g.service_charge_amount, 0) AS ServiceChargeAmount,
        ROUND(g.tax_amount, 0) AS TaxAmount,
        ROUND(g.amount, 0) AS Amount,
        CASE WHEN g.payment_id IS NULL THEN '' ELSE CAST(g.payment_id AS CHAR) END AS PaymentId,
        COALESCE(pmode.payment_mode, '') AS PaymentMode,
        g.username AS Username,
        CASE
            WHEN o.name IS NULL OR o.name = '' THEN g.outlet_code
            ELSE CONCAT(g.outlet_code, ' - ', o.name)
        END AS OutletName
    FROM grouped AS g
    LEFT JOIN hotel_services AS srv
        ON srv.code = g.service_id
    LEFT JOIN hotel_services AS bf
        ON bf.code = 'BF'
    LEFT JOIN outlets AS o
        ON o.code = g.outlet_code
    LEFT JOIN payment_modes AS pmode
        ON pmode.payment_id = g.payment_id
    LEFT JOIN hotel_settings AS hs
        ON hs.id = (SELECT MIN(hs0.id) FROM hotel_settings AS hs0)
    ORDER BY g.service_id, g.bill_date, g.bill_id;
END
SQL;
    }

    public function totalParameterUi(): array
    {
        return [
            ['name' => 'p_from_date', 'label' => 'Ngày', 'control' => 'date-range', 'range_end_parameter' => 'p_to_date', 'default' => '$today', 'required' => true, 'options' => []],
            ['name' => 'p_to_date', 'label' => 'Đến ngày', 'control' => 'hidden', 'default' => '$today', 'required' => true, 'options' => []],
            ['name' => 'p_view_by_time', 'label' => 'Xem theo khung giờ', 'control' => 'checkbox', 'default' => false, 'required' => false],
            ['name' => 'p_from_time', 'label' => 'Từ giờ ngày hôm trước', 'control' => 'time', 'default' => '00:00', 'required' => false],
            ['name' => 'p_to_time', 'label' => 'Đến giờ hôm nay', 'control' => 'time', 'default' => '23:59', 'required' => false],
            ['name' => 'p_department', 'label' => 'Bộ phận', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'service-departments'],
            ['name' => 'p_outlet', 'label' => 'Khu', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'outlets'],
            ['name' => 'p_user', 'label' => 'Người dùng', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'users'],
            ['name' => 'p_view_free', 'label' => 'Xem hóa đơn miễn phí', 'control' => 'checkbox', 'default' => false, 'required' => false],
            ['name' => 'p_amount_type', 'label' => 'Xem theo', 'control' => 'radio', 'default' => 'TOTAL', 'required' => false, 'options' => [
                ['value' => 'TOTAL', 'label' => 'Tổng cộng'],
                ['value' => 'NOT_VAT', 'label' => 'Not VAT'],
            ]],
        ];
    }

    public function detailParameterUi(): array
    {
        $ui = array_values(array_filter(
            $this->totalParameterUi(),
            static fn (array $item): bool => $item['name'] !== 'p_amount_type'
        ));
        array_splice($ui, 8, 0, [
            ['name' => 'p_services', 'label' => 'Dịch vụ', 'control' => 'multi-select', 'default' => '', 'required' => false, 'options_source' => 'hotel-services'],
        ]);
        $ui[] = ['name' => 'p_unpaid_only', 'label' => 'Chỉ hóa đơn chưa thanh toán', 'control' => 'hidden', 'default' => 0, 'required' => false];

        return $ui;
    }

    private function syncTotalRevenue(string $connectionName): void
    {
        $parameters = $this->procedureParameters([
            ['p_from_date', 'date'], ['p_to_date', 'date'], ['p_view_by_time', 'tinyint'], ['p_from_time', 'varchar'],
            ['p_to_time', 'varchar'], ['p_department', 'varchar'], ['p_outlet', 'varchar'], ['p_user', 'varchar'],
            ['p_view_free', 'tinyint'], ['p_amount_type', 'varchar'],
        ]);
        $fields = [
            ['name' => 'period_code', 'type' => 'string'],
            ['name' => 'business_date', 'type' => 'string'],
            ['name' => 'service_id', 'type' => 'string'],
            ['name' => 'department_id', 'type' => 'string'],
            ['name' => 'is_room_bill', 'type' => 'integer'],
            ['name' => 'amount', 'type' => 'decimal'],
            ['name' => 'original_rate', 'type' => 'decimal'],
            ['name' => 'service_charge_amount', 'type' => 'decimal'],
            ['name' => 'tax_amount', 'type' => 'decimal'],
        ];

        $this->syncReport($connectionName, [
            'source' => self::TOTAL_SOURCE,
            'source_name' => 'Dữ liệu báo cáo tổng doanh thu',
            'source_description' => 'Doanh thu theo kỳ/ngày/mã dịch vụ theo sp_018, sp_021, sp_036, sp_038 hệ thống cũ.',
            'object' => 'rpt_total_revenue',
            'parameters' => $parameters,
            'fields' => $fields,
            'report' => self::TOTAL_REPORT,
            'description' => 'Tổng doanh thu theo ngày và nhóm dịch vụ FO/HK/FB, kèm M.T.D, Y.T.D, CTM và biểu đồ theo mẫu hệ thống cũ.',
            'template' => self::TOTAL_TEMPLATE,
            'template_file' => 'total_revenue_reference.php',
            'ui' => $this->totalParameterUi(),
            'order' => 41,
        ]);
    }

    private function syncServiceRevenueDetail(string $connectionName): void
    {
        $parameters = $this->procedureParameters([
            ['p_from_date', 'date'], ['p_to_date', 'date'], ['p_view_by_time', 'tinyint'], ['p_from_time', 'varchar'],
            ['p_to_time', 'varchar'], ['p_department', 'varchar'], ['p_outlet', 'varchar'], ['p_user', 'varchar'],
            ['p_services', 'varchar'], ['p_view_free', 'tinyint'], ['p_unpaid_only', 'tinyint'],
        ]);
        $fields = array_map(
            static fn (string $name): array => [
                'name' => $name,
                'type' => in_array($name, ['OriginalRate', 'ServiceChargeAmount', 'TaxAmount', 'Amount'], true)
                    ? 'decimal'
                    : (in_array($name, ['STT', 'BillId'], true) ? 'integer' : 'string'),
            ],
            ['STT', 'BillId', 'BillDate', 'BookingCode', 'Room', 'GuestName', 'ArrivalDate', 'DepartureDate', 'ServiceId',
                'ServiceName', 'Description', 'OriginalRate', 'ServiceChargeAmount', 'TaxAmount', 'Amount', 'PaymentId',
                'PaymentMode', 'Username', 'OutletName']
        );

        $this->syncReport($connectionName, [
            'source' => self::DETAIL_SOURCE,
            'source_name' => 'Dữ liệu báo cáo doanh thu dịch vụ chi tiết',
            'source_description' => 'Chi tiết hóa đơn dịch vụ theo mã dịch vụ theo sp_134 hệ thống cũ.',
            'object' => 'rpt_service_revenue_detail',
            'parameters' => $parameters,
            'fields' => $fields,
            'report' => self::DETAIL_REPORT,
            'description' => 'Chi tiết hóa đơn dịch vụ theo mã dịch vụ; mở từ báo cáo tổng doanh thu bằng nhấp đúp vào số tiền.',
            'template' => self::DETAIL_TEMPLATE,
            'template_file' => 'service_revenue_detail_reference.php',
            'ui' => $this->detailParameterUi(),
            'order' => 42,
        ]);
    }

    private function procedureParameters(array $definitions): array
    {
        $parameters = [];
        foreach ($definitions as $index => [$name, $type]) {
            $parameters[] = [
                'name' => $name,
                'mode' => 'IN',
                'data_type' => $type,
                'database_type' => $type,
                'position' => $index + 1,
                'required' => in_array($name, ['p_from_date', 'p_to_date'], true),
            ];
        }

        return $parameters;
    }

    private function syncReport(string $connectionName, array $config): void
    {
        $db = DB::connection($connectionName);
        $now = now();
        $defaults = [];
        foreach ($config['ui'] as $item) {
            $defaults[$item['name']] = $item['default'] === '$today' ? $now->toDateString() : $item['default'];
        }

        $db->table('report_data_sources')->updateOrInsert(['code' => $config['source']], [
            'name' => $config['source_name'],
            'description' => $config['source_description'],
            'source_type' => 'procedure',
            'schema_name' => $db->getDatabaseName(),
            'object_name' => $config['object'],
            'parameter_schema' => json_encode($config['parameters'], JSON_UNESCAPED_UNICODE),
            'field_schema' => json_encode($config['fields'], JSON_UNESCAPED_UNICODE),
            'sample_parameters' => json_encode($defaults, JSON_UNESCAPED_UNICODE),
            'max_rows' => 20000,
            'is_active' => true,
            'last_discovered_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $sourceId = $db->table('report_data_sources')->where('code', $config['source'])->value('id');

        $definition = (require database_path('report_templates/'.$config['template_file']))->definition();
        $db->table('templates')->updateOrInsert(['report' => $config['template']], [
            'group' => self::GROUP,
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
            'version' => $definition['version'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $templateId = $db->table('templates')->where('report', $config['template'])->value('id');

        $db->table('report_definitions')->updateOrInsert(['code' => $config['report']], [
            'name' => $definition['name'],
            'group' => self::GROUP,
            'description' => $config['description'],
            'report_data_source_id' => $sourceId,
            'parameter_ui_schema' => json_encode($config['ui'], JSON_UNESCAPED_UNICODE),
            'sort_order' => $config['order'],
            'is_active' => true,
            'show_in_menu' => true,
            'menu_locations' => json_encode(['frontdesk', 'reservation'], JSON_UNESCAPED_UNICODE),
            'menu_top_order' => 20,
            'menu_group_order' => 30,
            'menu_item_order' => $config['order'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $reportId = $db->table('report_definitions')->where('code', $config['report'])->value('id');

        $db->table('report_definition_template')->updateOrInsert(
            ['report_definition_id' => $reportId, 'template_id' => $templateId],
            ['is_default' => true, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now]
        );
    }

    public function down(): void
    {
        // Keep the report configuration recoverable; no destructive rollback is required.
    }
};
