<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SOURCE = 'RPT_SALES_INVOICES';
    private const REPORT = 'SALES_INVOICES';
    private const TEMPLATE = 'SALES_INVOICES_REFERENCE';

    public function up(): void
    {
        $visitedDatabases = [];

        foreach ([DB::getDefaultConnection()] as $conn) {
            try {
                if (DB::connection($conn)->getDriverName() !== 'mysql') {
                    continue;
                }

                $database = DB::connection($conn)->getDatabaseName();
                if (isset($visitedDatabases[$database])) {
                    continue;
                }
                $visitedDatabases[$database] = true;

                // 1. Add nullable columns to sales_invoices table if missing
                if (Schema::connection($conn)->hasTable('sales_invoices')) {
                    Schema::connection($conn)->table('sales_invoices', function (Blueprint $table) use ($conn) {
                        $columns = [
                            'booking_id' => fn () => $table->unsignedBigInteger('booking_id')->nullable()->after('room'),
                            'rental_room_id' => fn () => $table->unsignedBigInteger('rental_room_id')->nullable()->after('booking_id'),
                            'payment_id' => fn () => $table->unsignedBigInteger('payment_id')->nullable()->after('rental_room_id'),
                            'company_id' => fn () => $table->unsignedBigInteger('company_id')->nullable()->after('payment_id'),
                            'guest_name' => fn () => $table->string('guest_name', 255)->nullable()->after('company_id'),
                            'original_rate' => fn () => $table->decimal('original_rate', 15, 2)->default(0)->nullable()->after('amount'),
                            'service_charge_amount' => fn () => $table->decimal('service_charge_amount', 15, 2)->default(0)->nullable()->after('original_rate'),
                            'special_tax' => fn () => $table->decimal('special_tax', 15, 2)->default(0)->nullable()->after('service_charge_amount'),
                            'tax' => fn () => $table->decimal('tax', 15, 2)->default(0)->nullable()->after('special_tax'),
                            'discount' => fn () => $table->decimal('discount', 15, 2)->default(0)->nullable()->after('tax'),
                            'department' => fn () => $table->string('department', 50)->nullable()->after('discount'),
                            'pack1' => fn () => $table->string('pack1', 50)->nullable()->after('department'),
                        ];

                        foreach ($columns as $columnName => $callback) {
                            if (! Schema::connection($conn)->hasColumn('sales_invoices', $columnName)) {
                                $callback();
                            }
                        }
                    });
                }

                // 2. Create or replace Stored Procedure rpt_sales_invoices
                DB::connection($conn)->unprepared('DROP PROCEDURE IF EXISTS rpt_sales_invoices');
                DB::connection($conn)->unprepared($this->procedureSql());

                // 3. Sync report metadata and templates
                $this->syncReportConfiguration($conn);
            } catch (\Throwable $e) {
                // Preserve best-effort handling for this target database.
            }
        }
    }

    public function procedureSql(): string
    {
        return <<<'SQL'
CREATE PROCEDURE rpt_sales_invoices(
    IN p_from_date VARCHAR(20),
    IN p_to_date VARCHAR(20),
    IN p_department VARCHAR(50),
    IN p_company VARCHAR(100),
    IN p_user VARCHAR(50),
    IN p_export_type VARCHAR(10),
    IN p_sort_by VARCHAR(50),
    IN p_sort_type VARCHAR(10),
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

    WITH base_invoices AS (
        SELECT
            bh.id AS Ma,
            COALESCE(bh.bill_id, '') AS BillID,
            COALESCE(bh.payment_id, bh.legacy_payment_id, '') AS PaymentID,
            DATE_FORMAT(bh.invoice_date, '%d/%m/%Y') AS InvoiceDateFormatted,
            bh.invoice_date AS InvoiceDate,
            CASE
                WHEN COALESCE(bh.booking_id, bh.legacy_booking_id) IS NULL THEN COALESCE(bh.room, '')
                ELSE CONCAT('C:', v_division, COALESCE(bh.booking_id, bh.legacy_booking_id))
            END AS Room,
            CASE
                WHEN COALESCE(bh.booking_id, bh.legacy_booking_id) IS NULL THEN TRIM(COALESCE(bh.guest_name, ''))
                ELSE REPLACE(COALESCE(bh.guest_name, ''), 'BK ', CONCAT('BK ', v_division))
            END AS GuestName,
            COALESCE(bh.original_rate, 0) AS OriginalRateHDBH,
            COALESCE(bh.service_charge_amount, 0) AS ServiceChargeAmountHDBH,
            COALESCE(bh.special_tax, 0) AS BillSpecialTaxBH,
            COALESCE(bh.tax, 0) AS BillTaxBH,
            COALESCE(bh.amount, 0) AS Amount,
            COALESCE(bh.currency, 'VND') AS DVT,
            COALESCE(bh.pack1, '') AS PaymentMethod,
            COALESCE(bh.username, '') AS Username,
            COALESCE(comp.name, comp.trading_name, comp.code, '') AS CompanyName,
            (
                SELECT COALESCE(SUM(p.amount), 0)
                FROM payments p
                LEFT JOIN payment_methods pm ON pm.code = p.payment_method_id
                WHERE (p.id = bh.payment_id OR p.legacy_id = bh.legacy_payment_id OR p.invoice_number = bh.bill_id)
                  AND COALESCE(pm.payment_group, 1) = 1
                  AND COALESCE(p.pack2, '') <> 'DPR'
            ) AS Cash,
            (
                SELECT COALESCE(SUM(p.amount), 0)
                FROM payments p
                LEFT JOIN payment_methods pm ON pm.code = p.payment_method_id
                WHERE (p.id = bh.payment_id OR p.legacy_id = bh.legacy_payment_id OR p.invoice_number = bh.bill_id)
                  AND COALESCE(pm.payment_group, 1) = 2
                  AND COALESCE(p.pack2, '') <> 'DPR'
            ) AS Card,
            (
                SELECT COALESCE(SUM(p.amount), 0)
                FROM payments p
                LEFT JOIN payment_methods pm ON pm.code = p.payment_method_id
                WHERE (p.id = bh.payment_id OR p.legacy_id = bh.legacy_payment_id OR p.invoice_number = bh.bill_id)
                  AND COALESCE(pm.payment_group, 1) = 3
            ) AS Voucher,
            (
                SELECT COALESCE(SUM(p.amount), 0)
                FROM payments p
                LEFT JOIN payment_methods pm ON pm.code = p.payment_method_id
                WHERE (p.id = bh.payment_id OR p.legacy_id = bh.legacy_payment_id OR p.invoice_number = bh.bill_id)
                  AND COALESCE(pm.payment_group, 1) = 4
            ) AS City,
            (
                SELECT COALESCE(SUM(p.amount), 0)
                FROM payments p
                LEFT JOIN payment_methods pm ON pm.code = p.payment_method_id
                WHERE (p.id = bh.payment_id OR p.legacy_id = bh.legacy_payment_id OR p.invoice_number = bh.bill_id)
                  AND COALESCE(pm.payment_group, 1) = 1
                  AND COALESCE(p.pack2, '') = 'DPR'
            ) AS DPCash,
            (
                SELECT COALESCE(SUM(p.amount), 0)
                FROM payments p
                LEFT JOIN payment_methods pm ON pm.code = p.payment_method_id
                WHERE (p.id = bh.payment_id OR p.legacy_id = bh.legacy_payment_id OR p.invoice_number = bh.bill_id)
                  AND COALESCE(pm.payment_group, 1) = 2
                  AND COALESCE(p.pack2, '') = 'DPR'
            ) AS DPCard,
            (
                SELECT COALESCE(p.vat_number, '')
                FROM payments p
                WHERE (p.id = bh.payment_id OR p.legacy_id = bh.legacy_payment_id OR p.invoice_number = bh.bill_id)
                  AND p.vat_number IS NOT NULL AND p.vat_number <> ''
                LIMIT 1
            ) AS VATNo
        FROM sales_invoices bh
        LEFT JOIN bookings dk ON (dk.id = bh.booking_id OR dk.id = bh.legacy_booking_id)
        LEFT JOIN booking_rooms pt ON (pt.id = bh.rental_room_id OR pt.id = bh.legacy_rental_room_id)
        LEFT JOIN bookings dk1 ON dk1.id = pt.booking_id
        LEFT JOIN companies comp ON (comp.id = bh.company_id OR comp.code = bh.company_id)
        WHERE (
            COALESCE(p_export_type, '') = ''
            OR (p_export_type = '1' AND COALESCE(dk.has_vat, dk1.has_vat, 0) = 1)
            OR (p_export_type = '0' AND COALESCE(dk.has_vat, dk1.has_vat, 0) = 0)
        )
        AND CASE
            WHEN LENGTH(COALESCE(bh.pack1, '')) = 2 THEN bh.pack1
            ELSE SUBSTRING(COALESCE(bh.pack1, ''), 1, 2)
        END NOT IN (SELECT code FROM payment_methods WHERE is_free = 1)
        AND bh.amount <> 0
        AND CAST(bh.invoice_date AS DATE) BETWEEN v_from AND v_to
        AND (COALESCE(p_department, '') = '' OR bh.department = p_department)
        AND (COALESCE(p_outlet, '') = '' OR bh.outlet = p_outlet)
        AND (COALESCE(p_user, '') = '' OR bh.username = p_user)
        AND (
            COALESCE(p_company, '') = ''
            OR comp.code LIKE CONCAT('%', p_company, '%')
            OR comp.name LIKE CONCAT('%', p_company, '%')
            OR CAST(comp.id AS CHAR) = p_company
        )
    )
    SELECT
        Ma,
        BillID,
        PaymentID,
        InvoiceDateFormatted,
        InvoiceDate,
        Room,
        GuestName,
        OriginalRateHDBH,
        ServiceChargeAmountHDBH,
        BillSpecialTaxBH,
        BillTaxBH,
        Amount,
        DVT,
        PaymentMethod,
        Username,
        CompanyName,
        Cash,
        Card,
        Voucher,
        City,
        DPCash,
        DPCard,
        COALESCE(VATNo, '') AS VATNo
    FROM base_invoices
    ORDER BY
        CASE WHEN p_sort_by = 'Room' AND p_sort_type = 'DESC' THEN Room END DESC,
        CASE WHEN p_sort_by = 'Room' AND (p_sort_type IS NULL OR p_sort_type = 'ASC') THEN Room END ASC,
        CASE WHEN (p_sort_by IS NULL OR p_sort_by = 'Date') AND p_sort_type = 'DESC' THEN InvoiceDate END DESC,
        CASE WHEN (p_sort_by IS NULL OR p_sort_by = 'Date') AND (p_sort_type IS NULL OR p_sort_type = 'ASC') THEN InvoiceDate END ASC,
        Ma ASC;
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
            ['name' => 'p_department', 'type' => 'string', 'required' => false],
            ['name' => 'p_company', 'type' => 'string', 'required' => false],
            ['name' => 'p_user', 'type' => 'string', 'required' => false],
            ['name' => 'p_export_type', 'type' => 'string', 'required' => false],
            ['name' => 'p_sort_by', 'type' => 'string', 'required' => false],
            ['name' => 'p_sort_type', 'type' => 'string', 'required' => false],
            ['name' => 'p_outlet', 'type' => 'string', 'required' => false],
        ];

        $fields = [
            ['name' => 'Ma', 'type' => 'integer'],
            ['name' => 'BillID', 'type' => 'string'],
            ['name' => 'PaymentID', 'type' => 'string'],
            ['name' => 'InvoiceDateFormatted', 'type' => 'string'],
            ['name' => 'Room', 'type' => 'string'],
            ['name' => 'GuestName', 'type' => 'string'],
            ['name' => 'Amount', 'type' => 'decimal'],
            ['name' => 'Cash', 'type' => 'decimal'],
            ['name' => 'Card', 'type' => 'decimal'],
            ['name' => 'Voucher', 'type' => 'decimal'],
            ['name' => 'City', 'type' => 'decimal'],
            ['name' => 'DPCash', 'type' => 'decimal'],
            ['name' => 'DPCard', 'type' => 'decimal'],
            ['name' => 'VATNo', 'type' => 'string'],
            ['name' => 'CompanyName', 'type' => 'string'],
        ];

        $defaults = [
            'p_from_date' => now()->startOfDay()->format('Y-m-d'),
            'p_to_date' => now()->endOfDay()->format('Y-m-d'),
            'p_department' => '',
            'p_company' => '',
            'p_user' => '',
            'p_export_type' => '',
            'p_sort_by' => 'Date',
            'p_sort_type' => 'ASC',
            'p_outlet' => '',
        ];

        $db->table('report_data_sources')->updateOrInsert(['code' => self::SOURCE], [
            'name' => 'Dữ liệu báo cáo hóa đơn bán hàng',
            'description' => 'MySQL chuyển đổi theo legacy sp_094; chi tiết hóa đơn bán hàng theo ngày, khách, công ty và thanh toán.',
            'source_type' => 'procedure',
            'schema_name' => $database,
            'object_name' => 'rpt_sales_invoices',
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

        $definition = (require database_path('report_templates/sales_invoices_reference.php'))->definition();
        $db->table('templates')->updateOrInsert(['report' => self::TEMPLATE], [
            'group' => 'Báo cáo thống kê lễ tân',
            'name' => 'Báo cáo hóa đơn bán hàng',
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
            ['name' => 'p_department', 'label' => 'Chọn bộ phận', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'service-departments'],
            ['name' => 'p_company', 'label' => 'Chọn công ty', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'companies'],
            ['name' => 'p_user', 'label' => 'Chọn người dùng', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'users'],
            ['name' => 'p_export_type', 'label' => 'Xem Theo', 'control' => 'select', 'default' => '', 'required' => false, 'options' => [
                ['label' => 'All', 'value' => ''],
                ['label' => 'Có xuất HĐ VAT', 'value' => '1'],
                ['label' => 'Không xuất HĐ VAT', 'value' => '0'],
            ]],
            ['name' => 'p_sort_by', 'label' => 'Sắp xếp theo', 'control' => 'hidden', 'default' => 'Date', 'required' => false],
            ['name' => 'p_sort_type', 'label' => 'Thứ tự sắp xếp', 'control' => 'hidden', 'default' => 'ASC', 'required' => false],
        ];

        $db->table('report_definitions')->updateOrInsert(['code' => self::REPORT], [
            'name' => 'Báo cáo hóa đơn bán hàng',
            'group' => 'Báo cáo thống kê lễ tân',
            'description' => 'Báo cáo chi tiết hóa đơn bán hàng theo ngày, khách, công ty và hình thức thanh toán theo sp_094 legacy.',
            'report_data_source_id' => $sourceId,
            'parameter_ui_schema' => json_encode($ui, JSON_UNESCAPED_UNICODE),
            'sort_order' => 40,
            'is_active' => true,
            'show_in_menu' => true,
            'menu_locations' => json_encode(['frontdesk', 'reservation']),
            'menu_top_order' => 20,
            'menu_group_order' => 30,
            'menu_item_order' => 40,
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
        // Preserve compatible data and layout.
    }
};
