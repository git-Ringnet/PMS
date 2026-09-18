<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CONNECTIONS = ['mysql', 'mysql_hkt1', 'mysql_hkt2', 'mysql_hkt3', 'mysql_hkt4'];
    private const SOURCE = 'RPT_UNPAID_SERVICE_BILLS';
    private const REPORT = 'UNPAID_SERVICE_BILLS';
    private const TEMPLATE = 'UNPAID_SERVICE_BILLS_REFERENCE';

    public function up(): void
    {
        $visitedDatabases = [];

        foreach (self::CONNECTIONS as $conn) {
            try {
                if (DB::connection($conn)->getDriverName() !== 'mysql') {
                    continue;
                }

                $database = DB::connection($conn)->getDatabaseName();
                if (isset($visitedDatabases[$database])) {
                    continue;
                }
                $visitedDatabases[$database] = true;

                // 1. Create or replace Stored Procedure
                DB::connection($conn)->unprepared('DROP PROCEDURE IF EXISTS rpt_unpaid_service_bills');
                DB::connection($conn)->unprepared($this->procedureSql());

                // 2. Sync report metadata and templates
                $this->syncReportConfiguration($conn);
            } catch (\Throwable $e) {
                // Continue for other branches
            }
        }
    }

    public function procedureSql(): string
    {
        return <<<'SQL'
CREATE PROCEDURE rpt_unpaid_service_bills(
    IN p_from_date VARCHAR(20),
    IN p_to_date VARCHAR(20),
    IN p_user VARCHAR(50),
    IN p_sort_by VARCHAR(50),
    IN p_sort_type VARCHAR(10),
    IN p_department VARCHAR(50),
    IN p_service VARCHAR(50),
    IN p_company VARCHAR(100),
    IN p_outlet VARCHAR(50)
)
READS SQL DATA
BEGIN
    DECLARE v_division VARCHAR(20) DEFAULT '';
    DECLARE v_from DATE;
    DECLARE v_to DATE;

    SELECT COALESCE(prefix_booking_id, '') INTO v_division FROM hotel_settings LIMIT 1;

    IF p_from_date LIKE '%/%' THEN
        SET v_from = STR_TO_DATE(LEFT(p_from_date, 10), '%d/%m/%Y');
    ELSE
        SET v_from = CAST(LEFT(p_from_date, 10) AS DATE);
    END IF;

    IF p_to_date LIKE '%/%' THEN
        SET v_to = STR_TO_DATE(LEFT(p_to_date, 10), '%d/%m/%Y');
    ELSE
        SET v_to = CAST(LEFT(p_to_date, 10) AS DATE);
    END IF;

    WITH raw_bills AS (
        SELECT
            hddv.Ma,
            hddv.Date,
            hddv.RefId,
            hddv.PaymentId,
            hddv.RegisterID2,
            hddv.RentalRoomId2,
            hddv.RentalRoomId1,
            hddv.CustomerId2,
            COALESCE(dvct.DescriptionServive, hddv.DescriptionServive, '') AS DescriptionServive,
            hddv.Status,
            hddv.Username,
            hddv.OpenTime,
            hddv.ServiceId,
            hddv.ServiceCharge,
            hddv.Tax,
            hddv.SpecialTax,
            hddv.Outlet,
            hddv.DepartmentId,
            COALESCE(dvct.Amount, hddv.TotalAmount0, hddv.Amount, 0) AS TienQDTD,
            CASE 
                WHEN dvct.OriginalRate IS NOT NULL AND dvct.OriginalRate > 0 THEN dvct.OriginalRate
                ELSE (((COALESCE(hddv.TotalAmount0, hddv.Amount, 0) * 100) / (100 + COALESCE(hddv.Tax, 0)) * 100) / (100 + COALESCE(hddv.SpecialTax, 0)) * 100) / (100 + COALESCE(hddv.ServiceCharge, 0))
            END AS OriginalRate,
            CASE 
                WHEN dvct.ServiceChargeAmount IS NOT NULL AND dvct.ServiceChargeAmount > 0 THEN dvct.ServiceChargeAmount
                ELSE (((COALESCE(hddv.TotalAmount0, hddv.Amount, 0) * 100) / (100 + COALESCE(hddv.Tax, 0)) * 100) / (100 + COALESCE(hddv.SpecialTax, 0)) * COALESCE(hddv.ServiceCharge, 0)) / (100 + COALESCE(hddv.ServiceCharge, 0))
            END AS ServiceChargeAmount,
            CASE 
                WHEN dvct.TaxAmount IS NOT NULL AND dvct.TaxAmount > 0 THEN dvct.TaxAmount
                ELSE (COALESCE(hddv.TotalAmount0, hddv.Amount, 0) * COALESCE(hddv.Tax, 0)) / (100 + COALESCE(hddv.Tax, 0))
            END AS TaxAmount,
            pt.arrival_date AS PtArrivalDate,
            pt.departure_date AS PtDepartureDate,
            pt.booking_id AS PtBookingId,
            pt.room_number AS PtRoom,
            CASE 
                WHEN hddv.RegisterID2 IS NULL THEN
                    CASE 
                        WHEN pt.booking_id IS NULL THEN COALESCE(pt.room_number, '')
                        ELSE CONCAT(v_division, pt.booking_id, '_R:', COALESCE(pt.room_number, ''))
                    END
                ELSE CONCAT(v_division, hddv.RegisterID2)
            END AS BookingIdFormatted,
            COALESCE(pt.booking_id, hddv.RegisterID2) AS ResolvedBookingId
        FROM service_bills AS hddv
        LEFT JOIN service_bill_details AS dvct ON hddv.Ma = dvct.BillServiceId
        LEFT JOIN booking_rooms AS pt ON (CAST(pt.id AS CHAR) = CAST(hddv.RentalRoomId2 AS CHAR) OR CAST(pt.id AS CHAR) = CAST(hddv.RentalRoomId1 AS CHAR))
        WHERE hddv.Edit = 0
          AND hddv.PaymentId IS NULL
          AND CAST(hddv.Date AS DATE) BETWEEN v_from AND v_to
          AND (COALESCE(p_user, '') = '' OR hddv.Username = p_user)
          AND (COALESCE(p_department, '') = '' OR hddv.DepartmentId = p_department)
          AND (COALESCE(p_service, '') = '' OR hddv.ServiceId = p_service)
          AND (COALESCE(p_outlet, '') = '' OR hddv.Outlet = p_outlet)
    )
    SELECT
        ROW_NUMBER() OVER (
            ORDER BY
                b.ServiceId ASC,
                CASE WHEN p_sort_by = 'Date' AND p_sort_type = 'DESC' THEN b.Date END DESC,
                CASE WHEN p_sort_by = 'Date' AND (p_sort_type IS NULL OR p_sort_type = 'ASC') THEN b.Date END ASC,
                CASE WHEN (p_sort_by IS NULL OR p_sort_by = 'Ma') AND p_sort_type = 'DESC' THEN b.Ma END DESC,
                CASE WHEN (p_sort_by IS NULL OR p_sort_by = 'Ma') AND (p_sort_type IS NULL OR p_sort_type = 'ASC') THEN b.Ma END ASC,
                b.Ma ASC
        ) AS `Index`,
        b.Ma,
        DATE_FORMAT(b.Date, '%d-%m-%Y') AS DateFormatted,
        COALESCE(b.RefId, '') AS RefId,
        COALESCE(b.PaymentId, '') AS PaymentId,
        b.BookingIdFormatted AS BookingId,
        COALESCE(DATE_FORMAT(COALESCE(b.PtArrivalDate, dk.arrival_date), '%d-%m-%Y'), '') AS ArrivalDate,
        COALESCE(DATE_FORMAT(COALESCE(b.PtDepartureDate, dk.departure_date), '%d-%m-%Y'), '') AS DepartureDate,
        COALESCE(comp.name, comp.trading_name, comp.code, '') AS BusinessName,
        CASE 
            WHEN b.RegisterID2 IS NULL THEN COALESCE(k.full_name, dk.booking_name, '')
            ELSE COALESCE(dk.booking_name, k.full_name, '')
        END AS Guest,
        COALESCE(b.DescriptionServive, '') AS DescriptionServive,
        ROUND(b.OriginalRate, 0) AS OriginalRate,
        ROUND(b.ServiceChargeAmount, 0) AS ServiceChargeAmount,
        ROUND(b.TaxAmount, 0) AS TaxAmount,
        ROUND(b.TienQDTD, 0) AS TienQDTD,
        b.Status,
        COALESCE(b.Username, '') AS Username,
        COALESCE(b.OpenTime, '') AS OpenTime,
        b.ServiceId,
        COALESCE(dv.name, b.ServiceId) AS ServiceName,
        '' AS HTTT
    FROM raw_bills b
    LEFT JOIN bookings dk ON dk.id = b.ResolvedBookingId
    LEFT JOIN guests k ON (CAST(k.id AS CHAR) = CAST(b.CustomerId2 AS CHAR))
    LEFT JOIN companies comp ON (comp.id = dk.company_id OR comp.code = dk.company_id)
    LEFT JOIN hotel_services dv ON dv.code = b.ServiceId
    WHERE (
        COALESCE(p_company, '') = ''
        OR comp.code LIKE CONCAT('%', p_company, '%')
        OR comp.name LIKE CONCAT('%', p_company, '%')
    )
    ORDER BY
        b.ServiceId ASC,
        CASE WHEN p_sort_by = 'Date' AND p_sort_type = 'DESC' THEN b.Date END DESC,
        CASE WHEN p_sort_by = 'Date' AND (p_sort_type IS NULL OR p_sort_type = 'ASC') THEN b.Date END ASC,
        CASE WHEN (p_sort_by IS NULL OR p_sort_by = 'Ma') AND p_sort_type = 'DESC' THEN b.Ma END DESC,
        CASE WHEN (p_sort_by IS NULL OR p_sort_by = 'Ma') AND (p_sort_type IS NULL OR p_sort_type = 'ASC') THEN b.Ma END ASC,
        b.Ma ASC;
END
SQL;
    }

    private function syncReportConfiguration(string $conn): void
    {
        $now = now();
        $db = DB::connection($conn);
        $database = $db->getDatabaseName();

        $parameters = [
            ['name' => 'p_from_date', 'type' => 'date', 'required' => true],
            ['name' => 'p_to_date', 'type' => 'date', 'required' => true],
            ['name' => 'p_user', 'type' => 'string', 'required' => false],
            ['name' => 'p_sort_by', 'type' => 'string', 'required' => false],
            ['name' => 'p_sort_type', 'type' => 'string', 'required' => false],
            ['name' => 'p_department', 'type' => 'string', 'required' => false],
            ['name' => 'p_service', 'type' => 'string', 'required' => false],
            ['name' => 'p_company', 'type' => 'string', 'required' => false],
            ['name' => 'p_outlet', 'type' => 'string', 'required' => false],
        ];

        $fields = [
            ['name' => 'Index', 'type' => 'integer'],
            ['name' => 'Ma', 'type' => 'integer'],
            ['name' => 'DateFormatted', 'type' => 'string'],
            ['name' => 'BookingId', 'type' => 'string'],
            ['name' => 'ArrivalDate', 'type' => 'string'],
            ['name' => 'DepartureDate', 'type' => 'string'],
            ['name' => 'Guest', 'type' => 'string'],
            ['name' => 'DescriptionServive', 'type' => 'string'],
            ['name' => 'BusinessName', 'type' => 'string'],
            ['name' => 'OriginalRate', 'type' => 'decimal'],
            ['name' => 'ServiceChargeAmount', 'type' => 'decimal'],
            ['name' => 'TaxAmount', 'type' => 'decimal'],
            ['name' => 'TienQDTD', 'type' => 'decimal'],
            ['name' => 'Username', 'type' => 'string'],
            ['name' => 'OpenTime', 'type' => 'string'],
            ['name' => 'ServiceId', 'type' => 'string'],
            ['name' => 'ServiceName', 'type' => 'string'],
            ['name' => 'HTTT', 'type' => 'string'],
        ];

        $defaults = [
            'p_from_date' => now()->startOfDay()->format('Y-m-d'),
            'p_to_date' => now()->endOfDay()->format('Y-m-d'),
            'p_user' => '',
            'p_sort_by' => 'Ma',
            'p_sort_type' => 'ASC',
            'p_department' => '',
            'p_service' => '',
            'p_company' => '',
            'p_outlet' => '',
        ];

        $db->table('report_data_sources')->updateOrInsert(['code' => self::SOURCE], [
            'name' => 'Dữ liệu báo cáo hóa đơn dịch vụ chưa thanh toán',
            'description' => 'MySQL chuyển đổi theo legacy sp_046; chi tiết các bill dịch vụ chưa thanh toán theo giai đoạn xem.',
            'source_type' => 'procedure',
            'schema_name' => $database,
            'object_name' => 'rpt_unpaid_service_bills',
            'parameter_schema' => json_encode($parameters, JSON_UNESCAPED_UNICODE),
            'field_schema' => json_encode($fields, JSON_UNESCAPED_UNICODE),
            'sample_parameters' => json_encode($defaults),
            'max_rows' => 5000,
            'is_active' => true,
            'last_discovered_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $sourceId = $db->table('report_data_sources')->where('code', self::SOURCE)->value('id');

        $definition = (require database_path('report_templates/unpaid_service_bills_reference.php'))->definition();
        $db->table('templates')->updateOrInsert(['report' => self::TEMPLATE], [
            'group' => 'Báo cáo doanh thu',
            'name' => 'Báo cáo hóa đơn dịch vụ chưa thanh toán',
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
        $templateId = $db->table('templates')->where('report', self::TEMPLATE)->value('id');

        $ui = [
            ['name' => 'p_from_date', 'label' => 'Chọn ngày', 'control' => 'date-range', 'range_end_parameter' => 'p_to_date', 'default' => '$today', 'required' => true],
            ['name' => 'p_to_date', 'label' => 'Đến ngày', 'control' => 'hidden', 'default' => '$today', 'required' => true],
            ['name' => 'p_user', 'label' => 'Chọn người dùng', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'users'],
            ['name' => 'p_sort_by', 'label' => 'Sắp xếp theo', 'control' => 'select', 'default' => 'Ma', 'required' => false, 'options' => [
                ['label' => 'Mã', 'value' => 'Ma'],
                ['label' => 'Ngày', 'value' => 'Date'],
            ]],
            ['name' => 'p_sort_type', 'label' => 'Thứ tự sắp xếp', 'control' => 'select', 'default' => 'ASC', 'required' => false, 'options' => [
                ['label' => 'Tăng dần (ASC)', 'value' => 'ASC'],
                ['label' => 'Giảm dần (DESC)', 'value' => 'DESC'],
            ]],
            ['name' => 'p_department', 'label' => 'Bộ phận', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'service-departments'],
            ['name' => 'p_service', 'label' => 'Dịch vụ', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'hotel-services'],
            ['name' => 'p_company', 'label' => 'Công ty', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'companies'],
        ];

        $db->table('report_definitions')->updateOrInsert(['code' => self::REPORT], [
            'name' => 'Báo cáo hóa đơn dịch vụ chưa thanh toán',
            'group' => 'Báo cáo doanh thu',
            'description' => 'Báo cáo chi tiết các bill dịch vụ chưa thanh toán theo sp_046 legacy.',
            'report_data_source_id' => $sourceId,
            'parameter_ui_schema' => json_encode($ui, JSON_UNESCAPED_UNICODE),
            'sort_order' => 170,
            'is_active' => true,
            'show_in_menu' => true,
            'menu_locations' => json_encode(['frontdesk', 'cashier', 'report']),
            'menu_top_order' => 20,
            'menu_group_order' => 30,
            'menu_item_order' => 170,
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
        // Preserve data
    }
};
