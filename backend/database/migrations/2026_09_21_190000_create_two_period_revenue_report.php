<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SOURCE = 'RPT_TWO_PERIOD_REVENUE';
    private const REPORT = 'TWO_PERIOD_REVENUE';
    private const TEMPLATE = 'TWO_PERIOD_REVENUE_REFERENCE';

    public function up(): void
    {
        $connections = [DB::getDefaultConnection()];
        $visitedDatabases = [];

        foreach ($connections as $connectionName) {
            $connection = DB::connection($connectionName);
            if ($connection->getDriverName() !== 'mysql') {
                continue;
            }

            $databaseName = $connection->getDatabaseName();
            if (isset($visitedDatabases[$databaseName])) {
                continue;
            }
            $visitedDatabases[$databaseName] = true;

            try {
                $connection->unprepared('DROP PROCEDURE IF EXISTS rpt_two_period_revenue');
                $connection->unprepared($this->procedureSql());
                $this->syncReportConfiguration($connectionName, $databaseName);
            } catch (\Throwable $exception) {
                throw new \RuntimeException(
                    "Failed to install TWO_PERIOD_REVENUE on connection {$connectionName} ({$databaseName}): {$exception->getMessage()}",
                    (int) $exception->getCode(),
                    $exception
                );
            }
        }
    }

    public function procedureSql(): string
    {
        return <<<'SQL'
CREATE PROCEDURE rpt_two_period_revenue(
    IN p_from_date DATE,
    IN p_to_date DATE,
    IN p_department VARCHAR(50),
    IN p_outlet VARCHAR(50),
    IN p_services VARCHAR(1000),
    IN p_user VARCHAR(50),
    IN p_order_by VARCHAR(20),
    IN p_order_direction VARCHAR(4),
    IN p_group_by_day TINYINT,
    IN p_show_registration TINYINT,
    IN p_show_dep_date TINYINT
)
READS SQL DATA
BEGIN
    DECLARE v_order_by VARCHAR(20) DEFAULT 'MA';
    DECLARE v_order_direction VARCHAR(4) DEFAULT 'ASC';

    SET v_order_by = CASE UPPER(TRIM(COALESCE(p_order_by, '')))
        WHEN 'DATE' THEN 'DATE'
        WHEN 'INVOICEID' THEN 'INVOICEID'
        WHEN 'ROOM' THEN 'ROOM'
        ELSE 'MA'
    END;
    SET v_order_direction = CASE UPPER(TRIM(COALESCE(p_order_direction, '')))
        WHEN 'DESC' THEN 'DESC'
        ELSE 'ASC'
    END;

    WITH base_rows AS (
        SELECT
            sb.Ma AS Ma,
            COALESCE(NULLIF(sb.RegisterID2, 0), br.booking_id) AS BookingKey,
            COALESCE(
                CONCAT(
                    COALESCE(hs.prefix_booking_id, ''),
                    COALESCE(NULLIF(sb.RegisterID2, 0), br.booking_id)
                ),
                ''
            ) AS RegisterID2,
            DATE_FORMAT(COALESCE(br.arrival_date, b.arrival_date), '%d/%m/%Y') AS ArrivalDateVW,
            DATE_FORMAT(COALESCE(br.departure_date, b.departure_date), '%d/%m/%Y') AS DepartureDateVW,
            COALESCE(NULLIF(b.booking_name, ''), NULLIF(sb.Guest, ''), '') AS Guest,
            DATE_FORMAT(sb.Date, '%d/%m/%Y') AS DateHDDV,
            COALESCE(sb.DescriptionServive, '') AS DescriptionServive,
            ROUND(
                (
                    COALESCE(sb.TotalAmount0, sb.Amount, 0) * 100.0
                    / NULLIF(100.0 + COALESCE(sb.Tax, 0), 0)
                    * 100.0 / NULLIF(100.0 + COALESCE(sb.SpecialTax, 0), 0)
                    * 100.0 / NULLIF(100.0 + COALESCE(sb.ServiceCharge, 0), 0)
                ),
                2
            ) AS OriginalRate,
            ROUND(
                (
                    COALESCE(sb.TotalAmount0, sb.Amount, 0) * 100.0
                    / NULLIF(100.0 + COALESCE(sb.Tax, 0), 0)
                    * 100.0 / NULLIF(100.0 + COALESCE(sb.SpecialTax, 0), 0)
                    * COALESCE(sb.ServiceCharge, 0)
                    / NULLIF(100.0 + COALESCE(sb.ServiceCharge, 0), 0)
                ),
                2
            ) AS ServiceChargeAmount,
            ROUND(
                (
                    COALESCE(sb.TotalAmount0, sb.Amount, 0) * 100.0
                    / NULLIF(100.0 + COALESCE(sb.Tax, 0), 0)
                    * COALESCE(sb.SpecialTax, 0)
                    / NULLIF(100.0 + COALESCE(sb.SpecialTax, 0), 0)
                ),
                2
            ) AS SpecialTaxAmount,
            ROUND(
                (
                    COALESCE(sb.TotalAmount0, sb.Amount, 0)
                    * COALESCE(sb.Tax, 0)
                    / NULLIF(100.0 + COALESCE(sb.Tax, 0), 0)
                ),
                2
            ) AS TaxAmount,
            COALESCE(sb.TotalAmount0, sb.Amount, 0) AS Amount,
            COALESCE(NULLIF(si.pack1, ''), method_summary.PaymentMethod, '') AS PaymentMethod,
            COALESCE(c.name, 'KHÁCH LẺ') AS Company,
            COALESCE(sb.Username, '') AS Username,
            COALESCE(sb.OpenTime, '') AS OpenTime,
            COALESCE(sb.Outlet, '') AS Outlet,
            COALESCE(o.name, sb.Outlet, '') AS OutletName,
            COALESCE(sb.ServiceId, '') AS ServiceId,
            COALESCE(srv.name, sb.ServiceId, '') AS FirstNameService,
            DATE_FORMAT(si.invoice_date, '%Y-%m-%d') AS DateHDBH,
            DATE_FORMAT(si.invoice_date, '%d/%m/%Y') AS DateHDBHLabel,
            sb.InvoiceId AS InvoiceId,
            COALESCE(br.room_number, '') AS Room,
            DATE(sb.Date) AS ServiceDateSort,
            DATE(si.invoice_date) AS InvoiceDateSort
        FROM service_bills AS sb
        INNER JOIN sales_invoices AS si
            ON si.id = sb.InvoiceId
        LEFT JOIN (
            SELECT
                p.invoice_id,
                GROUP_CONCAT(DISTINCT p.payment_method_id ORDER BY p.payment_method_id SEPARATOR ', ') AS PaymentMethod,
                MAX(CASE WHEN COALESCE(pm.is_free, 0) = 0 THEN 1 ELSE 0 END) AS HasNonFreeMethod
            FROM payments AS p
            LEFT JOIN payment_methods AS pm
                ON pm.code = p.payment_method_id
            WHERE p.invoice_id IS NOT NULL
              AND p.edit_flag = 0
            GROUP BY p.invoice_id
        ) AS method_summary
            ON method_summary.invoice_id = si.id
        LEFT JOIN booking_rooms AS br
            ON br.id = sb.RentalRoomId2
        LEFT JOIN bookings AS b
            ON b.id = COALESCE(NULLIF(sb.RegisterID2, 0), br.booking_id)
        LEFT JOIN companies AS c
            ON c.id = COALESCE(b.company_id, sb.CompanyId2)
        LEFT JOIN hotel_services AS srv
            ON srv.code = sb.ServiceId
        LEFT JOIN outlets AS o
            ON o.code = sb.Outlet
        LEFT JOIN hotel_settings AS hs
            ON hs.id = (SELECT MIN(hs0.id) FROM hotel_settings AS hs0)
        WHERE sb.PaymentId IS NOT NULL
          AND si.invoice_date IS NOT NULL
          AND sb.Date IS NOT NULL
          AND (
              (
                  NULLIF(si.pack1, '') IS NOT NULL
                  AND NOT EXISTS (
                      SELECT 1
                      FROM payment_methods AS pm
                      WHERE pm.code = si.pack1
                        AND pm.is_free = 1
                  )
              )
              OR (
                  NULLIF(si.pack1, '') IS NULL
                  AND COALESCE(method_summary.HasNonFreeMethod, 0) = 1
              )
          )
          AND (
              (COALESCE(p_show_dep_date, 1) = 1
                  AND si.invoice_date >= p_from_date
                  AND si.invoice_date < DATE_ADD(p_to_date, INTERVAL 1 DAY))
              OR
              (COALESCE(p_show_dep_date, 1) = 0
                  AND DATE(sb.Date) = p_from_date)
          )
          AND (
              YEAR(sb.Date) <> YEAR(si.invoice_date)
              OR MONTH(sb.Date) <> MONTH(si.invoice_date)
          )
          AND (COALESCE(TRIM(p_department), '') = '' OR sb.DepartmentId = TRIM(p_department))
          AND (COALESCE(TRIM(p_outlet), '') = '' OR sb.Outlet = TRIM(p_outlet))
          AND (COALESCE(TRIM(p_user), '') = '' OR sb.Username = TRIM(p_user))
          AND (
              COALESCE(TRIM(p_services), '') = ''
              OR FIND_IN_SET(
                  UPPER(sb.ServiceId),
                  UPPER(REPLACE(TRIM(p_services), ' ', ''))
              ) > 0
          )
    )
    SELECT
        ROW_NUMBER() OVER (
            ORDER BY
                CASE WHEN v_order_by = 'MA' AND v_order_direction = 'ASC' THEN base.Ma END ASC,
                CASE WHEN v_order_by = 'MA' AND v_order_direction = 'DESC' THEN base.Ma END DESC,
                CASE WHEN v_order_by = 'DATE' AND v_order_direction = 'ASC' THEN base.ServiceDateSort END ASC,
                CASE WHEN v_order_by = 'DATE' AND v_order_direction = 'DESC' THEN base.ServiceDateSort END DESC,
                CASE WHEN v_order_by = 'INVOICEID' AND v_order_direction = 'ASC' THEN base.InvoiceId END ASC,
                CASE WHEN v_order_by = 'INVOICEID' AND v_order_direction = 'DESC' THEN base.InvoiceId END DESC,
                CASE WHEN v_order_by = 'ROOM' AND v_order_direction = 'ASC' THEN base.Room END ASC,
                CASE WHEN v_order_by = 'ROOM' AND v_order_direction = 'DESC' THEN base.Room END DESC,
                base.Ma ASC
        ) AS STT,
        base.Ma,
        base.RegisterID2,
        base.ArrivalDateVW,
        base.DepartureDateVW,
        base.Guest,
        base.DateHDDV,
        base.DescriptionServive,
        base.OriginalRate,
        base.ServiceChargeAmount,
        base.SpecialTaxAmount,
        base.TaxAmount,
        base.Amount,
        base.PaymentMethod,
        base.Company,
        base.Username,
        base.OpenTime,
        base.Outlet,
        base.OutletName,
        base.ServiceId,
        base.FirstNameService,
        base.DateHDBH,
        base.DateHDBHLabel,
        base.InvoiceId,
        base.Room
    FROM base_rows AS base
    ORDER BY
        CASE WHEN v_order_by = 'MA' AND v_order_direction = 'ASC' THEN base.Ma END ASC,
        CASE WHEN v_order_by = 'MA' AND v_order_direction = 'DESC' THEN base.Ma END DESC,
        CASE WHEN v_order_by = 'DATE' AND v_order_direction = 'ASC' THEN base.ServiceDateSort END ASC,
        CASE WHEN v_order_by = 'DATE' AND v_order_direction = 'DESC' THEN base.ServiceDateSort END DESC,
        CASE WHEN v_order_by = 'INVOICEID' AND v_order_direction = 'ASC' THEN base.InvoiceId END ASC,
        CASE WHEN v_order_by = 'INVOICEID' AND v_order_direction = 'DESC' THEN base.InvoiceId END DESC,
        CASE WHEN v_order_by = 'ROOM' AND v_order_direction = 'ASC' THEN base.Room END ASC,
        CASE WHEN v_order_by = 'ROOM' AND v_order_direction = 'DESC' THEN base.Room END DESC,
        base.Ma ASC;
END
SQL;
    }

    private function syncReportConfiguration(string $connectionName, string $databaseName): void
    {
        $db = DB::connection($connectionName);
        $now = now();

        $parameters = [
            ['name' => 'p_from_date', 'mode' => 'IN', 'data_type' => 'date', 'database_type' => 'date', 'position' => 1, 'required' => true],
            ['name' => 'p_to_date', 'mode' => 'IN', 'data_type' => 'date', 'database_type' => 'date', 'position' => 2, 'required' => true],
            ['name' => 'p_department', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(50)', 'position' => 3, 'required' => true],
            ['name' => 'p_outlet', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(50)', 'position' => 4, 'required' => true],
            ['name' => 'p_services', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(1000)', 'position' => 5, 'required' => true],
            ['name' => 'p_user', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(50)', 'position' => 6, 'required' => true],
            ['name' => 'p_order_by', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(20)', 'position' => 7, 'required' => true],
            ['name' => 'p_order_direction', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(4)', 'position' => 8, 'required' => true],
            ['name' => 'p_group_by_day', 'mode' => 'IN', 'data_type' => 'tinyint', 'database_type' => 'tinyint', 'position' => 9, 'required' => true],
            ['name' => 'p_show_registration', 'mode' => 'IN', 'data_type' => 'tinyint', 'database_type' => 'tinyint', 'position' => 10, 'required' => true],
            ['name' => 'p_show_dep_date', 'mode' => 'IN', 'data_type' => 'tinyint', 'database_type' => 'tinyint', 'position' => 11, 'required' => true],
        ];

        $fields = [
            ['name' => 'STT', 'type' => 'number', 'nullable' => false],
            ['name' => 'Ma', 'type' => 'number', 'nullable' => false],
            ['name' => 'RegisterID2', 'type' => 'string', 'nullable' => true],
            ['name' => 'ArrivalDateVW', 'type' => 'string', 'nullable' => true],
            ['name' => 'DepartureDateVW', 'type' => 'string', 'nullable' => true],
            ['name' => 'Guest', 'type' => 'string', 'nullable' => true],
            ['name' => 'DateHDDV', 'type' => 'string', 'nullable' => true],
            ['name' => 'DescriptionServive', 'type' => 'string', 'nullable' => true],
            ['name' => 'OriginalRate', 'type' => 'number', 'nullable' => true],
            ['name' => 'ServiceChargeAmount', 'type' => 'number', 'nullable' => true],
            ['name' => 'SpecialTaxAmount', 'type' => 'number', 'nullable' => true],
            ['name' => 'TaxAmount', 'type' => 'number', 'nullable' => true],
            ['name' => 'Amount', 'type' => 'number', 'nullable' => false],
            ['name' => 'PaymentMethod', 'type' => 'string', 'nullable' => true],
            ['name' => 'Company', 'type' => 'string', 'nullable' => true],
            ['name' => 'Username', 'type' => 'string', 'nullable' => true],
            ['name' => 'OpenTime', 'type' => 'string', 'nullable' => true],
            ['name' => 'Outlet', 'type' => 'string', 'nullable' => true],
            ['name' => 'OutletName', 'type' => 'string', 'nullable' => true],
            ['name' => 'ServiceId', 'type' => 'string', 'nullable' => true],
            ['name' => 'FirstNameService', 'type' => 'string', 'nullable' => true],
            ['name' => 'DateHDBH', 'type' => 'string', 'nullable' => true],
            ['name' => 'DateHDBHLabel', 'type' => 'string', 'nullable' => true],
            ['name' => 'InvoiceId', 'type' => 'number', 'nullable' => true],
            ['name' => 'Room', 'type' => 'string', 'nullable' => true],
        ];

        $defaults = [
            'p_from_date' => now()->startOfMonth()->format('Y-m-d'),
            'p_to_date' => now()->endOfMonth()->format('Y-m-d'),
            'p_department' => '',
            'p_outlet' => '',
            'p_services' => '',
            'p_user' => '',
            'p_order_by' => 'Ma',
            'p_order_direction' => 'ASC',
            'p_group_by_day' => 0,
            'p_show_registration' => 0,
            'p_show_dep_date' => 1,
        ];

        $db->table('report_data_sources')->updateOrInsert(['code' => self::SOURCE], [
            'name' => 'Dữ liệu báo cáo doanh thu hai giai đoạn',
            'description' => 'Báo cáo theo Army sp_217: dịch vụ phát sinh và hóa đơn thanh toán thuộc khác tháng/năm.',
            'source_type' => 'procedure',
            'schema_name' => $databaseName,
            'object_name' => 'rpt_two_period_revenue',
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
        $templateDefinition = (require database_path('report_templates/two_period_revenue_reference.php'))->definition();
        $db->table('templates')->updateOrInsert(['report' => self::TEMPLATE], [
            'group' => 'Báo cáo doanh thu',
            'name' => 'Báo cáo doanh thu hai giai đoạn',
            'report_data_source_id' => $sourceId,
            'parameter_defaults' => json_encode($defaults, JSON_UNESCAPED_UNICODE),
            'page_size' => $templateDefinition['page_size'],
            'page_orientation' => $templateDefinition['page_orientation'],
            'margin_top' => $templateDefinition['margin_top'],
            'margin_right' => $templateDefinition['margin_right'],
            'margin_bottom' => $templateDefinition['margin_bottom'],
            'margin_left' => $templateDefinition['margin_left'],
            'content_json' => json_encode($templateDefinition['content_json'], JSON_UNESCAPED_UNICODE),
            'content_html' => $templateDefinition['content_html'],
            'css' => $templateDefinition['css'],
            'is_default' => true,
            'version' => $templateDefinition['version'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $templateId = $db->table('templates')->where('report', self::TEMPLATE)->value('id');
        $ui = [
            ['name' => 'p_from_date', 'label' => 'Ngày', 'control' => 'date-range', 'range_end_parameter' => 'p_to_date', 'default' => '$month_start', 'required' => true],
            ['name' => 'p_to_date', 'label' => 'Đến ngày', 'control' => 'hidden', 'default' => '$month_end', 'required' => true],
            ['name' => 'p_department', 'label' => 'Bộ phận', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'service-departments'],
            ['name' => 'p_outlet', 'label' => 'Outlet', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'outlets'],
            ['name' => 'p_services', 'label' => 'Dịch vụ', 'control' => 'multi-select', 'default' => '', 'required' => false, 'options_source' => 'hotel-services'],
            ['name' => 'p_user', 'label' => 'Chọn người dùng', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'users'],
            ['name' => 'p_order_by', 'label' => 'Sắp xếp theo', 'control' => 'select', 'layout' => 'inline', 'default' => 'Ma', 'required' => true, 'options' => [
                ['label' => 'Mã hóa đơn', 'value' => 'Ma'],
                ['label' => 'Ngày dịch vụ', 'value' => 'Date'],
                ['label' => 'Mã hóa đơn thanh toán', 'value' => 'InvoiceId'],
                ['label' => 'Phòng', 'value' => 'Room'],
            ]],
            ['name' => 'p_order_direction', 'label' => 'Thứ tự', 'control' => 'select', 'layout' => 'inline', 'default' => 'ASC', 'required' => true, 'options' => [
                ['label' => 'Tăng dần', 'value' => 'ASC'],
                ['label' => 'Giảm dần', 'value' => 'DESC'],
            ]],
            ['name' => 'p_group_by_day', 'label' => 'Nhóm theo ngày', 'control' => 'checkbox', 'default' => false, 'required' => true],
            ['name' => 'p_show_registration', 'label' => 'Đăng ký', 'control' => 'checkbox', 'default' => false, 'required' => true],
            ['name' => 'p_show_dep_date', 'label' => 'Chế độ ngày thanh toán', 'control' => 'hidden', 'default' => 1, 'required' => true],
        ];

        $db->table('report_definitions')->updateOrInsert(['code' => self::REPORT], [
            'name' => 'Báo cáo doanh thu hai giai đoạn',
            'group' => 'Báo cáo doanh thu',
            'description' => 'Dịch vụ có tháng phát sinh khác tháng thanh toán hóa đơn, theo legacy Army sp_217.',
            'report_data_source_id' => $sourceId,
            'parameter_ui_schema' => json_encode($ui, JSON_UNESCAPED_UNICODE),
            'sort_order' => 161,
            'is_active' => true,
            'show_in_menu' => true,
            'menu_locations' => json_encode(['frontdesk', 'reservation'], JSON_UNESCAPED_UNICODE),
            'menu_top_order' => 20,
            'menu_group_order' => 30,
            'menu_item_order' => 161,
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
        $connections = [DB::getDefaultConnection()];
        $visitedDatabases = [];

        foreach ($connections as $connectionName) {
            $connection = DB::connection($connectionName);
            if ($connection->getDriverName() !== 'mysql') {
                continue;
            }

            $databaseName = $connection->getDatabaseName();
            if (isset($visitedDatabases[$databaseName])) {
                continue;
            }
            $visitedDatabases[$databaseName] = true;

            $connection->unprepared('DROP PROCEDURE IF EXISTS rpt_two_period_revenue');
            $connection->table('report_definitions')
                ->where('code', self::REPORT)
                ->update(['is_active' => false, 'show_in_menu' => false, 'updated_at' => now()]);
            $connection->table('report_data_sources')
                ->where('code', self::SOURCE)
                ->update(['is_active' => false, 'updated_at' => now()]);
        }
    }
};
