<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const INSTALLATIONS = [
        [
            'source' => 'RPT_REVENUE_ARMY',
            'report' => 'REVENUE_ARMY',
            'template' => 'REVENUE_ARMY_REFERENCE',
            'procedure' => 'rpt_revenue_army',
            'source_name' => 'Dữ liệu báo cáo doanh thu Army',
            'source_description' => 'Doanh thu theo ngày theo sp_292 Army Quy Nhơn, gồm doanh thu ngày, lũy kế và thanh toán.',
            'report_name' => 'Báo cáo doanh thu',
            'group' => 'Báo cáo doanh thu',
            'description' => 'Báo cáo doanh thu theo ngày theo legacy sp_292 Army.',
            'sort_order' => 150,
            'menu_group_order' => 10,
            'menu_item_order' => 150,
        ],
        [
            'source' => 'RPT_REVENUE_BY_DEPARTURE_DATE',
            'report' => 'REVENUE_BY_DEPARTURE_DATE',
            'template' => 'REVENUE_BY_DEPARTURE_DATE_REFERENCE',
            'procedure' => 'rpt_revenue_by_departure_date',
            'source_name' => 'Dữ liệu doanh thu đăng ký theo ngày đi',
            'source_description' => 'Doanh thu theo thời điểm trả phòng theo sp_238/sp_240; hỗ trợ chi tiết phòng hoặc nhóm đăng ký.',
            'report_name' => 'Báo cáo doanh thu đăng ký theo ngày đi',
            'group' => 'Báo cáo doanh thu',
            'description' => 'Báo cáo doanh thu đăng ký theo ngày đi theo legacy sp_238 và sp_240.',
            'sort_order' => 151,
            'menu_group_order' => 10,
            'menu_item_order' => 151,
        ],
        [
            'source' => 'RPT_RECEPTION_CASHIER_SHIFT',
            'report' => 'RECEPTION_CASHIER_SHIFT',
            'template' => 'RECEPTION_CASHIER_SHIFT_REFERENCE',
            'procedure' => 'rpt_reception_cashier_shift',
            'source_name' => 'Dữ liệu báo cáo thu ngân lễ tân',
            'source_description' => 'Giao dịch thu ngân theo sp_039 Navy, gồm nhóm giao dịch và phân bổ phương thức thanh toán.',
            'report_name' => 'Báo cáo thu ngân lễ tân',
            'group' => 'Báo cáo thu ngân',
            'description' => 'Báo cáo thu ngân lễ tân theo legacy sp_039 Navy.',
            'sort_order' => 158,
            'menu_group_order' => 20,
            'menu_item_order' => 158,
        ],
    ];

    public function up(): void
    {
        $visitedDatabases = [];
        foreach ($this->branchConnections() as $connectionName) {
            $db = DB::connection($connectionName);
            if ($db->getDriverName() !== 'mysql') {
                continue;
            }

            $database = $db->getDatabaseName();
            if (isset($visitedDatabases[$database])) {
                continue;
            }
            $visitedDatabases[$database] = true;
            $capabilities = $this->schemaCapabilities($db);

            $db->unprepared('DROP PROCEDURE IF EXISTS rpt_revenue_army');
            $db->unprepared($this->revenueArmyProcedure($capabilities));
            $db->unprepared('DROP PROCEDURE IF EXISTS rpt_revenue_by_departure_date');
            $db->unprepared($this->revenueByDepartureDateProcedure($capabilities));
            $db->unprepared('DROP PROCEDURE IF EXISTS rpt_reception_cashier_shift');
            $db->unprepared($this->receptionCashierShiftProcedure($capabilities));
            $this->syncConfiguration($connectionName);
        }
    }

    public function down(): void
    {
        foreach ($this->branchConnections() as $connectionName) {
            $db = DB::connection($connectionName);
            if ($db->getDriverName() !== 'mysql') {
                continue;
            }

            foreach (self::INSTALLATIONS as $installation) {
                $reportId = $db->table('report_definitions')->where('code', $installation['report'])->value('id');
                if ($reportId) {
                    $db->table('report_definition_template')->where('report_definition_id', $reportId)->delete();
                    $db->table('report_definitions')->where('id', $reportId)->delete();
                }
                $db->table('report_data_sources')->where('code', $installation['source'])->delete();
                $db->unprepared('DROP PROCEDURE IF EXISTS '.$installation['procedure']);
            }
        }
    }

    private function branchConnections(): array
    {
        return array_values(array_unique(array_merge(
            [config('database.default', 'mysql')],
            array_values(config('database_domains.branch_connections', []))
        )));
    }

    public function schemaCapabilities($db): array
    {
        return [
            'sales_invoices_booking_id' => $db->getSchemaBuilder()->hasColumn('sales_invoices', 'booking_id'),
            'sales_invoices_booking_room_id' => $db->getSchemaBuilder()->hasColumn('sales_invoices', 'booking_room_id'),
            'sales_invoices_rental_room_id' => $db->getSchemaBuilder()->hasColumn('sales_invoices', 'rental_room_id'),
            'sales_invoices_company_id' => $db->getSchemaBuilder()->hasColumn('sales_invoices', 'company_id'),
            'sales_invoices_guest_name' => $db->getSchemaBuilder()->hasColumn('sales_invoices', 'guest_name'),
            'sales_invoices_payment_code' => $db->getSchemaBuilder()->hasColumn('sales_invoices', 'payment_code'),
            'sales_invoices_department' => $db->getSchemaBuilder()->hasColumn('sales_invoices', 'department'),
            'payments_invoice_id' => $db->getSchemaBuilder()->hasColumn('payments', 'invoice_id'),
        ];
    }

    private function syncConfiguration(string $connectionName): void
    {
        $db = DB::connection($connectionName);
        $now = now();
        $database = $db->getDatabaseName();

        $configurations = [
            [
                'installation' => self::INSTALLATIONS[0],
                'parameters' => [
                    ['name' => 'p_date', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(20)', 'position' => 1, 'required' => true],
                    ['name' => 'p_company_id', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(50)', 'position' => 2, 'required' => false],
                    ['name' => 'p_booking_id', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(50)', 'position' => 3, 'required' => false],
                ],
                'fields' => [
                    'Index' => 'integer', 'BookingId' => 'string', 'GuestName' => 'string', 'BusinessName' => 'string',
                    'ArrivalDate' => 'string', 'DepartureDate' => 'string', 'RoomCount' => 'integer',
                    'RoomToday' => 'number', 'ExtraRoomToday' => 'number', 'MinibarToday' => 'number',
                    'LaundryToday' => 'number', 'BrokenToday' => 'number', 'RestaurantToday' => 'number',
                    'OtherToday' => 'number', 'TotalToday' => 'number', 'PrevDay' => 'number',
                    'TotalRevenue' => 'number', 'Cash' => 'number', 'BankTransfer' => 'number',
                    'Commission' => 'number', 'CityLedger' => 'number', 'InhouseRoom' => 'number',
                ],
                'defaults' => ['p_date' => now()->toDateString(), 'p_company_id' => '', 'p_booking_id' => ''],
                'ui' => [
                    ['name' => 'p_date', 'label' => 'Ngày', 'control' => 'date', 'default' => '$today', 'required' => true],
                    ['name' => 'p_company_id', 'label' => 'Công ty', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'companies'],
                    ['name' => 'p_booking_id', 'label' => 'Đăng ký', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'bookings'],
                ],
            ],
            [
                'installation' => self::INSTALLATIONS[1],
                'parameters' => [
                    ['name' => 'p_from_date', 'mode' => 'IN', 'data_type' => 'date', 'database_type' => 'date', 'position' => 1, 'required' => true],
                    ['name' => 'p_to_date', 'mode' => 'IN', 'data_type' => 'date', 'database_type' => 'date', 'position' => 2, 'required' => true],
                    ['name' => 'p_from_time', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(10)', 'position' => 3, 'required' => false],
                    ['name' => 'p_to_time', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(10)', 'position' => 4, 'required' => false],
                    ['name' => 'p_company_id', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(50)', 'position' => 5, 'required' => false],
                    ['name' => 'p_group_by_booking', 'mode' => 'IN', 'data_type' => 'tinyint', 'database_type' => 'tinyint', 'position' => 6, 'required' => false],
                ],
                'fields' => [
                    'CodeBooking' => 'string', 'RoomNumber' => 'string', 'Company' => 'string', 'GuestName' => 'string',
                    'Arrival' => 'string', 'Departure' => 'string', 'RoomCharge' => 'number', 'ExtraBed' => 'number',
                    'ExtraPerson' => 'number', 'ExtraRoomCharge' => 'number', 'Tour' => 'number',
                    'Transportation' => 'number', 'Miscel' => 'number', 'Minibar' => 'number', 'Laundry' => 'number',
                    'Broken' => 'number', 'BreakfastSplitdown' => 'number', 'BreakfastCharge' => 'number',
                    'Food_Res' => 'number', 'Beverage_Res' => 'number', 'Other_Res' => 'number',
                    'OtherRevenue' => 'number', 'TotalRevenue' => 'number', 'PaymentMethod' => 'string',
                ],
                'defaults' => [
                    'p_from_date' => now()->toDateString(), 'p_to_date' => now()->toDateString(),
                    'p_from_time' => '00:00', 'p_to_time' => '23:59', 'p_company_id' => '', 'p_group_by_booking' => 0,
                ],
                'ui' => [
                    ['name' => 'p_from_date', 'label' => 'Từ ngày', 'control' => 'date-range', 'range_end_parameter' => 'p_to_date', 'default' => '$today', 'required' => true],
                    ['name' => 'p_to_date', 'label' => 'Đến ngày', 'control' => 'hidden', 'default' => '$today', 'required' => true],
                    ['name' => 'p_from_time', 'label' => 'Từ giờ', 'control' => 'text', 'default' => '00:00', 'required' => true],
                    ['name' => 'p_to_time', 'label' => 'Đến giờ', 'control' => 'text', 'default' => '23:59', 'required' => true],
                    ['name' => 'p_company_id', 'label' => 'Công ty', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'companies'],
                    ['name' => 'p_group_by_booking', 'label' => 'Nhóm theo đăng ký', 'control' => 'checkbox', 'default' => false, 'required' => false],
                ],
            ],
            [
                'installation' => self::INSTALLATIONS[2],
                'parameters' => [
                    ['name' => 'p_from_date', 'mode' => 'IN', 'data_type' => 'date', 'database_type' => 'date', 'position' => 1, 'required' => true],
                    ['name' => 'p_to_date', 'mode' => 'IN', 'data_type' => 'date', 'database_type' => 'date', 'position' => 2, 'required' => true],
                    ['name' => 'p_department', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(255)', 'position' => 3, 'required' => false],
                    ['name' => 'p_user', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(255)', 'position' => 4, 'required' => false],
                    ['name' => 'p_shift', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(50)', 'position' => 5, 'required' => false],
                    ['name' => 'p_from_time', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(10)', 'position' => 6, 'required' => false],
                    ['name' => 'p_to_time', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(10)', 'position' => 7, 'required' => false],
                    ['name' => 'p_company_id', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(50)', 'position' => 8, 'required' => false],
                    ['name' => 'p_view_deposit', 'mode' => 'IN', 'data_type' => 'tinyint', 'database_type' => 'tinyint', 'position' => 9, 'required' => false],
                    ['name' => 'p_view_amount_zero', 'mode' => 'IN', 'data_type' => 'tinyint', 'database_type' => 'tinyint', 'position' => 10, 'required' => false],
                    ['name' => 'p_payment_method', 'mode' => 'IN', 'data_type' => 'varchar', 'database_type' => 'varchar(255)', 'position' => 11, 'required' => false],
                ],
                'fields' => [
                    'BookingStatus' => 'integer', 'StatusRoom' => 'integer', 'BillID' => 'string', 'Date' => 'string',
                    'Room' => 'string', 'Guest' => 'string', 'OpenTime' => 'string', 'PaymentID' => 'string',
                    'Amount' => 'number', 'Username' => 'string', 'Description' => 'string', 'PaymentMethod' => 'string',
                    'PaymentMethodName' => 'string', 'Department' => 'string', 'NumOfRoom' => 'string',
                    'Deposit' => 'integer', 'ShowDeposit' => 'string', 'MaBooking' => 'string', 'ArrivalDate' => 'string',
                    'DepartureDate' => 'string', 'GuestInfo' => 'string', 'Company' => 'string', 'CardId' => 'string',
                ],
                'defaults' => [
                    'p_from_date' => now()->toDateString(), 'p_to_date' => now()->toDateString(), 'p_department' => 'FO',
                    'p_user' => '', 'p_shift' => '', 'p_from_time' => '00:00', 'p_to_time' => '23:59',
                    'p_company_id' => '', 'p_view_deposit' => 1, 'p_view_amount_zero' => 0, 'p_payment_method' => '',
                ],
                'ui' => [
                    ['name' => 'p_from_date', 'label' => 'Ngày', 'control' => 'date-range', 'range_end_parameter' => 'p_to_date', 'default' => '$today', 'required' => true],
                    ['name' => 'p_to_date', 'label' => 'Đến ngày', 'control' => 'hidden', 'default' => '$today', 'required' => true],
                    ['name' => 'p_shift', 'label' => 'Ca làm việc', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'report-shifts'],
                    ['name' => 'p_from_time', 'label' => 'Từ giờ', 'control' => 'text', 'default' => '00:00', 'required' => true],
                    ['name' => 'p_to_time', 'label' => 'Đến giờ', 'control' => 'text', 'default' => '23:59', 'required' => true],
                    ['name' => 'p_department', 'label' => 'Bộ phận', 'control' => 'multi-select', 'default' => ['FO'], 'required' => false, 'options_source' => 'service-departments'],
                    ['name' => 'p_company_id', 'label' => 'Công ty', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'companies'],
                    ['name' => 'p_user', 'label' => 'Người dùng', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'users'],
                    ['name' => 'p_view_deposit', 'label' => 'Hiển thị đặt cọc', 'control' => 'checkbox', 'default' => true, 'required' => false],
                    ['name' => 'p_view_amount_zero', 'label' => 'Hiển thị tiền = 0', 'control' => 'checkbox', 'default' => false, 'required' => false],
                    ['name' => 'p_payment_method', 'label' => 'Phương thức thanh toán', 'control' => 'hidden', 'default' => '', 'required' => false],
                ],
            ],
        ];

        foreach ($configurations as $configuration) {
            $installation = $configuration['installation'];
            $db->table('report_data_sources')->updateOrInsert(['code' => $installation['source']], [
                'name' => $configuration['installation']['source_name'],
                'description' => $configuration['installation']['source_description'],
                'source_type' => 'procedure',
                'schema_name' => $database,
                'object_name' => $installation['procedure'],
                'parameter_schema' => json_encode($configuration['parameters'], JSON_UNESCAPED_UNICODE),
                'field_schema' => json_encode(array_map(static fn (string $name, string $type): array => ['name' => $name, 'type' => $type, 'nullable' => true], array_keys($configuration['fields']), $configuration['fields']), JSON_UNESCAPED_UNICODE),
                'sample_parameters' => json_encode($configuration['defaults'], JSON_UNESCAPED_UNICODE),
                'max_rows' => 5000,
                'is_active' => true,
                'last_discovered_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $sourceId = $db->table('report_data_sources')->where('code', $installation['source'])->value('id');

            $definition = (require database_path('report_templates/'.strtolower($installation['template']).'.php'))->definition();
            $template = $db->table('templates')->where('report', $installation['template'])->first();
            if (! $template) {
                $templateId = $db->table('templates')->insertGetId([
                    'report' => $installation['template'],
                    'group' => $installation['group'],
                    'name' => $definition['name'],
                    'report_data_source_id' => $sourceId,
                    'parameter_defaults' => json_encode($configuration['defaults'], JSON_UNESCAPED_UNICODE),
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
            } else {
                $templateId = $template->id;
                $db->table('templates')->where('id', $templateId)->update(['report_data_source_id' => $sourceId, 'updated_at' => $now]);
            }

            $db->table('report_definitions')->updateOrInsert(['code' => $installation['report']], [
                'name' => $installation['report_name'],
                'group' => $installation['group'],
                'description' => $installation['description'],
                'report_data_source_id' => $sourceId,
                'parameter_ui_schema' => json_encode($configuration['ui'], JSON_UNESCAPED_UNICODE),
                'sort_order' => $installation['sort_order'],
                'is_active' => true,
                'show_in_menu' => true,
                'menu_locations' => json_encode(['frontdesk', 'reservation'], JSON_UNESCAPED_UNICODE),
                'menu_top_order' => 20,
                'menu_group_order' => $installation['menu_group_order'],
                'menu_item_order' => $installation['menu_item_order'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $reportId = $db->table('report_definitions')->where('code', $installation['report'])->value('id');
            $db->table('report_definition_template')->updateOrInsert(
                ['report_definition_id' => $reportId, 'template_id' => $templateId],
                ['is_default' => true, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    public function revenueArmyProcedure(array $capabilities = []): string
    {
        $invoiceBookingId = ($capabilities['sales_invoices_booking_id'] ?? true) ? 'si.booking_id' : 'si.legacy_booking_id';

        return str_replace('__INVOICE_BOOKING_ID__', $invoiceBookingId, <<<'SQL'
CREATE PROCEDURE rpt_revenue_army(
    IN p_date VARCHAR(20),
    IN p_company_id VARCHAR(50),
    IN p_booking_id VARCHAR(50)
)
READS SQL DATA
BEGIN
    DECLARE v_report_date DATE;

    SET v_report_date = CASE
        WHEN p_date LIKE '%/%' THEN STR_TO_DATE(LEFT(p_date, 10), '%d/%m/%Y')
        ELSE CAST(LEFT(p_date, 10) AS DATE)
    END;

    WITH room_agg AS (
        SELECT booking_id, COUNT(*) AS room_count
        FROM booking_rooms
        WHERE deleted_at IS NULL AND COALESCE(status, 0) <> 3
        GROUP BY booking_id
    ), eligible_bookings AS (
        SELECT b.id AS booking_id, b.booking_name, b.arrival_date, b.departure_date,
               COALESCE(ra.room_count, 0) AS room_count,
               COALESCE(c.name, c.trading_name, c.code, 'KHÁCH LẺ') AS company_name
        FROM bookings b
        LEFT JOIN room_agg ra ON ra.booking_id = b.id
        LEFT JOIN companies c ON c.id = b.company_id
        WHERE b.deleted_at IS NULL
          AND COALESCE(b.status, 0) <> 3
          AND (v_report_date BETWEEN b.arrival_date AND b.departure_date OR EXISTS (
              SELECT 1 FROM sales_invoices si
              WHERE __INVOICE_BOOKING_ID__ = b.id
                AND si.invoice_date IS NOT NULL
                AND DATE(si.invoice_date) = v_report_date
                AND COALESCE(si.status, 0) <> 3
          ))
          AND (COALESCE(p_company_id, '') IN ('', '0') OR CAST(b.company_id AS CHAR) = p_company_id)
          AND (COALESCE(p_booking_id, '') IN ('', '0') OR CAST(b.id AS CHAR) = p_booking_id)
    ), revenue_details AS (
        SELECT __INVOICE_BOOKING_ID__ AS booking_id,
            SUM(CASE WHEN UPPER(COALESCE(si.outlet, '')) = 'RM' AND DATE(si.invoice_date) = v_report_date THEN COALESCE(si.amount, 0) ELSE 0 END) AS room_today,
            SUM(CASE WHEN UPPER(COALESCE(si.outlet, '')) IN ('EB','EP','ER','KC','KE','UP','EI','LO','BD','BF') AND DATE(si.invoice_date) = v_report_date THEN COALESCE(si.amount, 0) ELSE 0 END) AS extra_room_today,
            SUM(CASE WHEN UPPER(COALESCE(si.outlet, '')) = 'MB' AND DATE(si.invoice_date) = v_report_date THEN COALESCE(si.amount, 0) ELSE 0 END) AS minibar_today,
            SUM(CASE WHEN UPPER(COALESCE(si.outlet, '')) = 'LA' AND DATE(si.invoice_date) = v_report_date THEN COALESCE(si.amount, 0) ELSE 0 END) AS laundry_today,
            SUM(CASE WHEN UPPER(COALESCE(si.outlet, '')) IN ('BR','BK') AND DATE(si.invoice_date) = v_report_date THEN COALESCE(si.amount, 0) ELSE 0 END) AS broken_today,
            SUM(CASE WHEN UPPER(COALESCE(si.outlet, '')) = 'FB' AND DATE(si.invoice_date) = v_report_date THEN COALESCE(si.amount, 0) ELSE 0 END) AS restaurant_today,
            SUM(CASE WHEN UPPER(COALESCE(si.outlet, '')) NOT IN ('RM','EB','EP','ER','KC','KE','UP','EI','LO','BD','BF','MB','LA','BR','BK','FB') AND DATE(si.invoice_date) = v_report_date THEN COALESCE(si.amount, 0) ELSE 0 END) AS other_today,
            SUM(CASE WHEN DATE(si.invoice_date) < v_report_date THEN COALESCE(si.amount, 0) ELSE 0 END) AS prev_day
        FROM sales_invoices si
        WHERE __INVOICE_BOOKING_ID__ IN (SELECT booking_id FROM eligible_bookings)
          AND si.invoice_date IS NOT NULL
          AND COALESCE(si.status, 0) <> 3
        GROUP BY __INVOICE_BOOKING_ID__
    ), payment_details AS (
        SELECT p.booking_id,
            SUM(CASE WHEN COALESCE(pm.code, p.payment_method_id) IN ('CA','TM') OR COALESCE(pm.payment_group, 0) = 1 THEN COALESCE(p.amount, 0) ELSE 0 END) AS cash_amount,
            SUM(CASE WHEN COALESCE(pm.code, p.payment_method_id) IN ('BT','CD','CK') OR COALESCE(pm.payment_group, 0) = 2 THEN COALESCE(p.amount, 0) ELSE 0 END) AS bank_amount,
            SUM(CASE WHEN COALESCE(pm.code, p.payment_method_id) = 'HH' THEN COALESCE(p.amount, 0) ELSE 0 END) AS commission_amount,
            SUM(CASE WHEN COALESCE(pm.code, p.payment_method_id) = 'AC' OR COALESCE(pm.payment_group, 0) = 4 THEN COALESCE(p.amount, 0) ELSE 0 END) AS city_ledger_amount
        FROM payments p
        LEFT JOIN payment_methods pm ON pm.code = p.payment_method_id OR CAST(pm.id AS CHAR) = p.payment_method_id
        WHERE p.booking_id IN (SELECT booking_id FROM eligible_bookings)
          AND p.date <= v_report_date
          AND COALESCE(p.edit_flag, 0) = 0
          AND p.deleted_at IS NULL
        GROUP BY p.booking_id
    ), calculated AS (
        SELECT
            eb.booking_id,
            eb.booking_name,
            eb.company_name,
            eb.arrival_date,
            eb.departure_date,
            eb.room_count,
            ROUND(COALESCE(rd.room_today, 0), 0) AS room_today,
            ROUND(COALESCE(rd.extra_room_today, 0), 0) AS extra_room_today,
            ROUND(COALESCE(rd.minibar_today, 0), 0) AS minibar_today,
            ROUND(COALESCE(rd.laundry_today, 0), 0) AS laundry_today,
            ROUND(COALESCE(rd.broken_today, 0), 0) AS broken_today,
            ROUND(COALESCE(rd.restaurant_today, 0), 0) AS restaurant_today,
            ROUND(COALESCE(rd.other_today, 0), 0) AS other_today,
            ROUND(COALESCE(rd.prev_day, 0), 0) AS prev_day,
            ROUND(COALESCE(pd.cash_amount, 0), 0) AS cash_amount,
            ROUND(COALESCE(pd.bank_amount, 0), 0) AS bank_amount,
            ROUND(COALESCE(pd.commission_amount, 0), 0) AS commission_amount,
            ROUND(COALESCE(pd.city_ledger_amount, 0), 0) AS city_ledger_amount
        FROM eligible_bookings eb
        LEFT JOIN revenue_details rd ON rd.booking_id = eb.booking_id
        LEFT JOIN payment_details pd ON pd.booking_id = eb.booking_id
    )
    SELECT
        ROW_NUMBER() OVER (ORDER BY arrival_date, booking_id) AS `Index`,
        CAST(booking_id AS CHAR) AS BookingId,
        CAST(booking_id AS CHAR) AS Ma,
        booking_name AS GuestName,
        booking_name AS BookingName,
        company_name AS BusinessName,
        company_name AS Company,
        DATE_FORMAT(arrival_date, '%d/%m/%Y') AS ArrivalDate,
        DATE_FORMAT(departure_date, '%d/%m/%Y') AS DepartureDate,
        room_count AS RoomCount,
        room_count AS RoomNo,
        room_today AS RoomToday,
        extra_room_today AS ExtraRoomToday,
        minibar_today AS MinibarToday,
        laundry_today AS LaundryToday,
        broken_today AS BrokenToday,
        restaurant_today AS RestaurantToday,
        0 AS BreakfastSurchargeToday,
        other_today AS OtherToday,
        ROUND(room_today + extra_room_today + minibar_today + laundry_today + broken_today + restaurant_today + other_today, 0) AS TotalToday,
        prev_day AS PrevDay,
        ROUND(room_today + extra_room_today + minibar_today + laundry_today + broken_today + restaurant_today + other_today + prev_day, 0) AS TotalRevenue,
        cash_amount AS Cash,
        bank_amount AS BankTransfer,
        commission_amount AS Commission,
        city_ledger_amount AS CityLedger,
        GREATEST(0, ROUND((room_today + extra_room_today + minibar_today + laundry_today + broken_today + restaurant_today + other_today + prev_day) - (cash_amount + bank_amount + commission_amount + city_ledger_amount), 0)) AS InhouseRoom
    FROM calculated
    WHERE room_count > 0
      AND (room_today + extra_room_today + minibar_today + laundry_today + broken_today + restaurant_today + other_today + prev_day) <> 0
    ORDER BY arrival_date, booking_id;
END
SQL
        );
    }

    public function revenueByDepartureDateProcedure(array $capabilities = []): string
    {
        $invoiceBookingId = ($capabilities['sales_invoices_booking_id'] ?? true) ? 'si.booking_id' : 'si.legacy_booking_id';
        $invoiceRoomId = ($capabilities['sales_invoices_booking_room_id'] ?? true)
            ? "COALESCE(NULLIF(si.booking_room_id, ''), "
                . (($capabilities['sales_invoices_rental_room_id'] ?? true) ? "CAST(si.rental_room_id AS CHAR), " : '')
                . "si.legacy_rental_room_id)"
            : 'si.legacy_rental_room_id';
        $invoiceCompanyId = ($capabilities['sales_invoices_company_id'] ?? true) ? 'si.company_id' : 'NULL';
        $invoiceGuestName = ($capabilities['sales_invoices_guest_name'] ?? true) ? 'si.guest_name' : 'NULL';
        $invoicePaymentCode = ($capabilities['sales_invoices_payment_code'] ?? true) ? 'si.payment_code' : 'NULL';
        $invoiceDepartment = ($capabilities['sales_invoices_department'] ?? true) ? 'si.department' : 'NULL';

        return strtr(<<<'SQL'
CREATE PROCEDURE rpt_revenue_by_departure_date(
    IN p_from_date DATE,
    IN p_to_date DATE,
    IN p_from_time VARCHAR(10),
    IN p_to_time VARCHAR(10),
    IN p_company_id VARCHAR(50),
    IN p_group_by_booking TINYINT
)
READS SQL DATA
BEGIN
    DECLARE v_from_time TIME;
    DECLARE v_to_time TIME;

    SET v_from_time = COALESCE(NULLIF(p_from_time, ''), '00:00:00');
    SET v_to_time = COALESCE(NULLIF(p_to_time, ''), '23:59:59');

    WITH invoice_items AS (
        SELECT
            COALESCE(__INVOICE_BOOKING_ID__, si.legacy_booking_id) AS booking_id,
            COALESCE(br.room_number, si.room, '') AS room_number,
            COALESCE(b.booking_name, __INVOICE_GUEST_NAME__, '') AS guest_name,
            COALESCE(c.name, c.trading_name, c.code, 'KHÁCH LẺ') AS company_name,
            COALESCE(br.arrival_date, b.arrival_date) AS arrival_date,
            COALESCE(br.departure_date, b.departure_date) AS departure_date,
            COALESCE(br.departure_time, '12:00:00') AS departure_time,
            UPPER(COALESCE(si.outlet, '')) AS outlet,
            UPPER(COALESCE(__INVOICE_DEPARTMENT__, '')) AS department,
            COALESCE(si.amount, 0) AS amount,
            si.invoice_date,
            __INVOICE_PAYMENT_CODE__ AS payment_code,
            si.id AS invoice_id
        FROM sales_invoices si
        LEFT JOIN bookings b ON b.id = COALESCE(__INVOICE_BOOKING_ID__, si.legacy_booking_id)
        LEFT JOIN booking_rooms br ON br.id = __INVOICE_ROOM_ID__
        LEFT JOIN companies c ON c.id = COALESCE(__INVOICE_COMPANY_ID__, b.company_id)
        WHERE si.invoice_date IS NOT NULL
          AND COALESCE(si.status, 0) <> 3
          AND (COALESCE(p_company_id, '') IN ('', '0') OR CAST(COALESCE(__INVOICE_COMPANY_ID__, b.company_id) AS CHAR) = p_company_id)
    ), filtered_items AS (
        SELECT *
        FROM invoice_items
        WHERE CAST(CONCAT(departure_date, ' ', departure_time) AS DATETIME)
              BETWEEN CAST(CONCAT(p_from_date, ' ', v_from_time) AS DATETIME)
                  AND CAST(CONCAT(p_to_date, ' ', v_to_time) AS DATETIME)
    ), payment_methods_by_booking AS (
        SELECT
            p.booking_id,
            GROUP_CONCAT(DISTINCT COALESCE(pm.code, p.payment_method_id)
                         ORDER BY COALESCE(pm.code, p.payment_method_id) SEPARATOR ', ') AS payment_method
        FROM payments p
        LEFT JOIN payment_methods pm ON pm.code = p.payment_method_id OR CAST(pm.id AS CHAR) = p.payment_method_id
        WHERE COALESCE(p.edit_flag, 0) = 0
          AND p.deleted_at IS NULL
        GROUP BY p.booking_id
    )
    SELECT
        CAST(filtered_items.booking_id AS CHAR) AS CodeBooking,
        CASE WHEN COALESCE(p_group_by_booking, 0) = 1 THEN '' ELSE room_number END AS RoomNumber,
        company_name AS Company,
        guest_name AS GuestName,
        DATE_FORMAT(arrival_date, '%d/%m/%Y') AS Arrival,
        DATE_FORMAT(departure_date, '%d/%m/%Y') AS Departure,
        ROUND(SUM(CASE WHEN outlet = 'RM' THEN amount ELSE 0 END), 0) AS RoomCharge,
        ROUND(SUM(CASE WHEN outlet = 'EB' THEN amount ELSE 0 END), 0) AS ExtraBed,
        ROUND(SUM(CASE WHEN outlet = 'EP' THEN amount ELSE 0 END), 0) AS ExtraPerson,
        ROUND(SUM(CASE WHEN outlet IN ('EI', 'LO', 'ER') THEN amount ELSE 0 END), 0) AS ExtraRoomCharge,
        ROUND(SUM(CASE WHEN outlet = 'TO' THEN amount ELSE 0 END), 0) AS Tour,
        ROUND(SUM(CASE WHEN outlet IN ('PU', 'DO') THEN amount ELSE 0 END), 0) AS Transportation,
        ROUND(SUM(CASE WHEN department = 'FO' AND outlet NOT IN ('RM','EB','EP','EI','LO','ER','TO','PU','DO','MB','LA','BR','BK','RF','RB','PC','BC','BD','BF','FB') THEN amount ELSE 0 END), 0) AS Miscel,
        ROUND(SUM(CASE WHEN outlet = 'MB' THEN amount ELSE 0 END), 0) AS Minibar,
        ROUND(SUM(CASE WHEN outlet = 'LA' THEN amount ELSE 0 END), 0) AS Laundry,
        ROUND(SUM(CASE WHEN outlet IN ('BR', 'BK') THEN amount ELSE 0 END), 0) AS Broken,
        ROUND(SUM(CASE WHEN outlet = 'BF' THEN amount ELSE 0 END), 0) AS BreakfastSplitdown,
        ROUND(SUM(CASE WHEN outlet IN ('BC', 'BD') THEN amount ELSE 0 END), 0) AS BreakfastCharge,
        ROUND(SUM(CASE WHEN outlet = 'RF' THEN amount ELSE 0 END), 0) AS Food_Res,
        ROUND(SUM(CASE WHEN outlet IN ('RB', 'PC') THEN amount ELSE 0 END), 0) AS Beverage_Res,
        ROUND(SUM(CASE WHEN department = 'FB' AND outlet NOT IN ('RF','RB','PC','BC','BD','BF') THEN amount ELSE 0 END), 0) AS Other_Res,
        ROUND(SUM(CASE WHEN department NOT IN ('FO', 'HK', 'FB') THEN amount ELSE 0 END), 0) AS OtherRevenue,
        ROUND(SUM(amount), 0) AS TotalRevenue,
        COALESCE(pmb.payment_method, '') AS PaymentMethod
    FROM filtered_items
    LEFT JOIN payment_methods_by_booking pmb ON pmb.booking_id = filtered_items.booking_id
    GROUP BY filtered_items.booking_id,
        CASE WHEN COALESCE(p_group_by_booking, 0) = 1 THEN 0 ELSE room_number END,
        company_name, guest_name, arrival_date, departure_date, pmb.payment_method
    ORDER BY filtered_items.booking_id, RoomNumber;
END
SQL,
        [
            '__INVOICE_BOOKING_ID__' => $invoiceBookingId,
            '__INVOICE_ROOM_ID__' => $invoiceRoomId,
            '__INVOICE_COMPANY_ID__' => $invoiceCompanyId,
            '__INVOICE_GUEST_NAME__' => $invoiceGuestName,
            '__INVOICE_PAYMENT_CODE__' => $invoicePaymentCode,
            '__INVOICE_DEPARTMENT__' => $invoiceDepartment,
        ]);
    }

    public function receptionCashierShiftProcedure(array $capabilities = []): string
    {
        $paymentInvoiceId = ($capabilities['payments_invoice_id'] ?? true) ? 'p.invoice_id' : 'p.id';

        return str_replace('__PAYMENT_INVOICE_ID__', $paymentInvoiceId, <<<'SQL'
CREATE PROCEDURE rpt_reception_cashier_shift(
    IN p_from_date DATE,
    IN p_to_date DATE,
    IN p_department VARCHAR(255),
    IN p_user VARCHAR(255),
    IN p_shift VARCHAR(50),
    IN p_from_time VARCHAR(10),
    IN p_to_time VARCHAR(10),
    IN p_company_id VARCHAR(50),
    IN p_view_deposit TINYINT,
    IN p_view_amount_zero TINYINT,
    IN p_payment_method VARCHAR(255)
)
READS SQL DATA
BEGIN
    DECLARE v_prefix VARCHAR(50) DEFAULT '';
    SELECT COALESCE(prefix_booking_id, '') INTO v_prefix FROM hotel_settings ORDER BY id LIMIT 1;

    SELECT
        b.status AS BookingStatus,
        br.status AS StatusRoom,
        CAST(__PAYMENT_INVOICE_ID__ AS CHAR) AS BillID,
        DATE_FORMAT(p.date, '%d/%m/%Y') AS `Date`,
        COALESCE(br.room_number, '') AS Room,
        COALESCE(NULLIF(p.guest_display, ''), NULLIF(g.full_name, ''), b.booking_name, '') AS Guest,
        COALESCE(NULLIF(p.open_time, ''), DATE_FORMAT(p.created_at, '%H:%i')) AS OpenTime,
        CAST(p.id AS CHAR) AS PaymentID,
        p.amount AS Amount,
        COALESCE(NULLIF(p.created_by, ''), p.username, '') AS Username,
        COALESCE(p.description, '') AS Description,
        COALESCE(pm.code, p.payment_method_id, '') AS PaymentMethod,
        COALESCE(pm.name, '') AS PaymentMethodName,
        COALESCE(NULLIF(d.code, ''), p.department_id, '') AS Department,
        COALESCE(br.room_number, '') AS NumOfRoom,
        CASE WHEN p.amount < 0 THEN 2 WHEN COALESCE(p.pack2, '') = 'DPR' THEN 1 ELSE 0 END AS Deposit,
        CASE WHEN p.amount < 0 THEN 'Hoàn Trả' WHEN COALESCE(p.pack2, '') = 'DPR' THEN 'Đặt cọc' ELSE 'Thu Ngân' END AS ShowDeposit,
        CONCAT(v_prefix, COALESCE(CAST(p.booking_id AS CHAR), '')) AS MaBooking,
        DATE_FORMAT(b.arrival_date, '%d/%m/%Y') AS ArrivalDate,
        DATE_FORMAT(b.departure_date, '%d/%m/%Y') AS DepartureDate,
        COALESCE(NULLIF(p.guest_display, ''), NULLIF(g.full_name, ''), b.booking_name, '') AS GuestInfo,
        COALESCE(c.name, c.trading_name, c.code, '') AS Company,
        CASE
            WHEN COALESCE(pm.code, p.payment_method_id, '') = 'CD'
                 AND COALESCE(b.card_no, '') <> ''
                 AND CHAR_LENGTH(b.card_no) >= 4
            THEN CONCAT(REPEAT('*', CHAR_LENGTH(b.card_no) - 4), RIGHT(b.card_no, 4))
            ELSE ''
        END AS CardId
    FROM payments p
    LEFT JOIN payment_methods pm ON pm.code = p.payment_method_id OR CAST(pm.id AS CHAR) = p.payment_method_id
    LEFT JOIN bookings b ON b.id = p.booking_id
    LEFT JOIN booking_rooms br ON br.id = p.booking_room_id
    LEFT JOIN booking_room_guests brg ON brg.booking_room_id = br.id AND brg.is_primary = 1 AND COALESCE(brg.status, 0) <> 3
    LEFT JOIN guests g ON g.id = COALESCE(p.guest_id, brg.guest_id)
    LEFT JOIN companies c ON c.id = COALESCE(p.company_id, b.company_id)
    LEFT JOIN departments d ON d.code = p.department_id
    WHERE p.date BETWEEN p_from_date AND p_to_date
      AND COALESCE(p.edit_flag, 0) = 0
      AND p.deleted_at IS NULL
      AND (COALESCE(p_user, '') = '' OR FIND_IN_SET(COALESCE(NULLIF(p.created_by, ''), p.username), REPLACE(p_user, ' ', '')) > 0)
      AND (COALESCE(p_shift, '') = '' OR p.shift = p_shift)
      AND (COALESCE(p_department, '') = '' OR FIND_IN_SET(COALESCE(NULLIF(d.code, ''), p.department_id), REPLACE(p_department, ' ', '')) > 0)
      AND (COALESCE(p_payment_method, '') = '' OR FIND_IN_SET(COALESCE(pm.code, p.payment_method_id), REPLACE(p_payment_method, ' ', '')) > 0)
      AND (COALESCE(p_company_id, '') IN ('', '-1', '0') OR CAST(COALESCE(p.company_id, b.company_id) AS CHAR) = p_company_id)
      AND (COALESCE(p_from_time, '') = '' OR TIME(COALESCE(p.open_time, TIME(p.created_at))) >= p_from_time)
      AND (COALESCE(p_to_time, '') = '' OR TIME(COALESCE(p.open_time, TIME(p.created_at))) <= p_to_time)
      AND (COALESCE(p_view_amount_zero, 0) = 1 OR p.amount <> 0)
      AND (COALESCE(p_view_deposit, 1) = 1 OR COALESCE(p.pack2, '') <> 'DPR')
    ORDER BY p.date ASC, COALESCE(p.open_time, TIME(p.created_at)) ASC, p.id ASC;
END
SQL
        );
    }
};
