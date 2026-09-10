<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SOURCE = 'COMPANY_DEBT';
    private const REPORT = 'COMPANY_DEBT';
    private const TEMPLATE = 'COMPANY_DEBT_REFERENCE';

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_company_debt');
        DB::unprepared($this->procedureSql());

        $this->seedReportConfiguration();
    }

    public function procedureSql(): string
    {
        return <<<'SQL'
CREATE PROCEDURE rpt_company_debt(
    IN p_from_date DATETIME,
    IN p_to_date DATETIME,
    IN p_company_id BIGINT,
    IN p_group_by_date TINYINT,
    IN p_show_settlements TINYINT
)
READS SQL DATA
BEGIN
    SELECT DISTINCT
        CONCAT(COALESCE(hs.prefix_booking_id, ''), COALESCE(si.legacy_booking_id, '')) AS BookingId,
        si.room AS Room,
        p.company_id AS CompanyId,
        c.name AS CompanyName,
        COALESCE(b.arrival_date, DATE(si.invoice_date)) AS ArrivalDate,
        CASE
            WHEN b.arrival_date IS NULL THEN NULL
            ELSE DATE_ADD(b.arrival_date, INTERVAL b.num_of_days DAY)
        END AS DepartureDate,
        p.description AS Description,
        si.amount AS TotalAmount,
        si.invoice_date AS Date,
        DATE_FORMAT(si.invoice_date, '%Y-%m-%d') AS DateGroup,
        si.username AS Username,
        CASE WHEN paid.PaidAmount IS NOT NULL THEN '☑' ELSE '' END AS PaidMarker,
        c.legacy_re_credit_limit AS MaxDebt,
        c.legacy_re_credit_limit AS ReCreditLimit,
        si.outlet AS Outlet,
        p.legacy_payment_total_amount0 AS AC,
        paid.PaidAmount AS PaidAmount,
        si.amount - COALESCE(paid.PaidAmount, 0) AS RemainingAmount,
        COALESCE(si.legacy_rental_room_id, CAST(si.legacy_booking_id AS CHAR)) AS RoomId,
        p.guest_display AS Guest,
        p.legacy_pack5 AS Pack5,
        COALESCE(p.legacy_id, p.id) AS PaymentId,
        b.status AS StatusBooking,
        b.id AS Ma,
        CASE WHEN br_status.id IS NOT NULL THEN 2 ELSE NULL END AS StatusRoom,
        CASE
            WHEN COALESCE(p_show_settlements, 0) = 1 THEN (
                SELECT CONCAT('[', GROUP_CONCAT(JSON_OBJECT(
                        'BookingId', CONCAT(COALESCE(hs.prefix_booking_id, ''), COALESCE(si.legacy_booking_id, '')),
                        'CompanyName', c.name,
                        'PaymentId', COALESCE(p.legacy_id, p.id),
                        'SettlementId', COALESCE(s.legacy_id, s.id),
                        'Date', s.payment_date,
                        'Reference', s.description,
                        'Amount', s.amount,
                        'Username', COALESCE(s.created_by, ''),
                        'Status', 'valid'
                    ) ORDER BY COALESCE(s.legacy_id, s.id) SEPARATOR ','), ']')
                FROM payment_debt_settlements AS s
                WHERE s.edit_flag = 0
                  AND (s.payment_id = p.id OR s.legacy_payment_table_id = p.legacy_id)
            )
            ELSE NULL
        END AS SettlementItems
    FROM service_bills AS sb
    INNER JOIN sales_invoices AS si
        ON si.legacy_id = sb.InvoiceId
    INNER JOIN payments AS p
        ON si.legacy_payment_id = p.payment_id
    INNER JOIN companies AS c
        ON c.id = p.company_id
    LEFT JOIN booking_rooms AS br_context
        ON br_context.id = si.legacy_rental_room_id
       AND br_context.deleted_at IS NULL
    INNER JOIN bookings AS b
        ON b.id = COALESCE(si.legacy_booking_id, br_context.booking_id)
       AND b.deleted_at IS NULL
    LEFT JOIN booking_rooms AS br_status
        ON (COALESCE(si.legacy_rental_room_id, CAST(si.legacy_booking_id AS CHAR)) = br_status.id
            OR COALESCE(si.legacy_rental_room_id, CAST(si.legacy_booking_id AS CHAR)) = CAST(br_status.booking_id AS CHAR))
       AND br_status.deleted_at IS NULL
       AND br_status.status = 2
    LEFT JOIN hotel_settings AS hs
        ON hs.id = (SELECT MIN(id) FROM hotel_settings)
    LEFT JOIN (
        SELECT
            p2.id AS payment_id,
            SUM(s2.amount) AS PaidAmount
        FROM payments AS p2
        INNER JOIN payment_debt_settlements AS s2
            ON s2.edit_flag = 0
           AND (s2.payment_id = p2.id OR s2.legacy_payment_table_id = p2.legacy_id)
        GROUP BY p2.id
    ) AS paid
        ON paid.payment_id = p.id
    WHERE p.payment_method_id = 'AC'
      AND COALESCE(p.pack2, '') = ''
      AND p.edit_flag = 0
      AND si.invoice_date BETWEEN p_from_date AND p_to_date
      AND (p_company_id IS NULL OR p_company_id = 0 OR p.company_id = p_company_id)
    ORDER BY si.invoice_date, p.company_id, si.legacy_booking_id, si.room, p.legacy_id;
END
SQL;
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
        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_company_debt');
    }

    private function seedReportConfiguration(): void
    {
        $now = now();
        $database = DB::connection()->getDatabaseName();
        $parameters = [
            ['name' => 'p_from_date', 'mode' => 'IN', 'data_type' => 'datetime', 'database_type' => 'datetime', 'position' => 1, 'required' => true],
            ['name' => 'p_to_date', 'mode' => 'IN', 'data_type' => 'datetime', 'database_type' => 'datetime', 'position' => 2, 'required' => true],
            ['name' => 'p_company_id', 'mode' => 'IN', 'data_type' => 'bigint', 'database_type' => 'bigint', 'position' => 3, 'required' => false],
            ['name' => 'p_group_by_date', 'mode' => 'IN', 'data_type' => 'tinyint', 'database_type' => 'tinyint', 'position' => 4, 'required' => true],
            ['name' => 'p_show_settlements', 'mode' => 'IN', 'data_type' => 'tinyint', 'database_type' => 'tinyint', 'position' => 5, 'required' => true],
        ];
        $fields = collect([
            'BookingId', 'Room', 'CompanyId', 'CompanyName', 'ArrivalDate', 'DepartureDate',
            'Description', 'TotalAmount', 'Date', 'DateGroup', 'Username', 'PaidMarker',
            'MaxDebt', 'ReCreditLimit', 'Outlet', 'AC', 'PaidAmount', 'RemainingAmount', 'RoomId', 'Guest',
            'Pack5', 'PaymentId', 'StatusBooking', 'Ma', 'StatusRoom', 'SettlementItems',
        ])->map(function (string $name): array {
            $numeric = ['CompanyId', 'TotalAmount', 'MaxDebt', 'ReCreditLimit', 'AC', 'PaidAmount', 'RemainingAmount', 'PaymentId', 'StatusBooking', 'Ma', 'StatusRoom'];
            return [
                'name' => $name,
                'type' => in_array($name, $numeric, true) ? 'number' : 'string',
                'nullable' => ! in_array($name, ['BookingId', 'TotalAmount', 'Date', 'DateGroup'], true),
            ];
        })->all();
        $defaults = [
            'p_from_date' => now()->startOfDay()->format('Y-m-d H:i:s'),
            'p_to_date' => now()->endOfDay()->format('Y-m-d H:i:s'),
            'p_company_id' => null,
            'p_group_by_date' => 1,
            'p_show_settlements' => 0,
        ];
        DB::table('report_data_sources')->updateOrInsert(['code' => self::SOURCE], [
            'name' => 'Dữ liệu báo cáo công nợ công ty',
            'description' => 'MySQL chuyển đổi từ ProVista sp_212; dòng chi tiết giữ grain SELECT DISTINCT.',
            'source_type' => 'procedure',
            'schema_name' => $database,
            'object_name' => 'rpt_company_debt',
            'parameter_schema' => json_encode($parameters, JSON_UNESCAPED_UNICODE),
            'field_schema' => json_encode($fields, JSON_UNESCAPED_UNICODE),
            'sample_parameters' => json_encode($defaults),
            'max_rows' => 5000,
            'is_active' => true,
            'last_discovered_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $sourceId = DB::table('report_data_sources')->where('code', self::SOURCE)->value('id');

        $definition = (require database_path('report_templates/company_debt_reference.php'))->definition();
        DB::table('templates')->updateOrInsert(['report' => self::TEMPLATE], [
            'group' => 'Báo cáo công nợ',
            'name' => 'Báo cáo công nợ công ty',
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
            'is_default' => false,
            'version' => '1.0',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $templateId = DB::table('templates')->where('report', self::TEMPLATE)->value('id');

        $ui = [
            ['name' => 'p_from_date', 'label' => 'Từ ngày', 'control' => 'date-range', 'range_end_parameter' => 'p_to_date', 'default' => '$today', 'required' => true],
            ['name' => 'p_to_date', 'label' => 'Đến ngày', 'control' => 'hidden', 'default' => '$today', 'required' => true],
            ['name' => 'p_company_id', 'label' => 'Chọn công ty', 'control' => 'select', 'default' => '', 'required' => false, 'options' => [], 'options_source' => 'companies'],
            ['name' => 'p_group_by_date', 'label' => 'Nhóm theo ngày', 'control' => 'checkbox', 'default' => true, 'required' => false],
            ['name' => 'p_show_settlements', 'label' => 'Hiển thị chi tiết giải trừ', 'control' => 'checkbox', 'default' => false, 'required' => false],
        ];
        DB::table('report_definitions')->updateOrInsert(['code' => self::REPORT], [
            'name' => 'Báo cáo công nợ công ty',
            'group' => 'Báo cáo công nợ',
            'description' => 'Công nợ công ty theo ngày, công ty và chi tiết giải trừ tùy chọn.',
            'report_data_source_id' => $sourceId,
            'parameter_ui_schema' => json_encode($ui, JSON_UNESCAPED_UNICODE),
            'sort_order' => 38,
            'is_active' => true,
            'show_in_menu' => true,
            'menu_locations' => json_encode(['frontdesk', 'reservation']),
            'menu_top_order' => 20,
            'menu_group_order' => 30,
            'menu_item_order' => 38,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $reportId = DB::table('report_definitions')->where('code', self::REPORT)->value('id');
        DB::table('report_definition_template')->updateOrInsert(
            ['report_definition_id' => $reportId, 'template_id' => $templateId],
            ['is_default' => true, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now]
        );
    }
};
