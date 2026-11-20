<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const SOURCE = 'COMPANY_DEBT';
    private const REPORT = 'COMPANY_DEBT';
    private const TEMPLATE = 'COMPANY_DEBT_LEGACY_DTX_V3';
    private const PREVIOUS_TEMPLATE = 'COMPANY_DEBT_LEGACY_DTX_V2';

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $reportId = DB::table('report_definitions')->where('code', self::REPORT)->value('id');
        $sourceId = DB::table('report_data_sources')->where('code', self::SOURCE)->value('id');
        if (! $reportId || ! $sourceId) {
            return;
        }

        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_company_debt_legacy_dtx_v2');
        DB::unprepared($this->companyProcedure());

        $now = now();
        $defaults = $this->defaults();
        DB::table('report_data_sources')->where('id', $sourceId)->update([
            'sample_parameters' => json_encode($defaults, JSON_UNESCAPED_UNICODE),
            'updated_at' => $now,
        ]);

        $definition = (require database_path('report_templates/company_debt_reference.php'))->definition();
        DB::table('templates')->updateOrInsert(['report' => self::TEMPLATE], [
            'group' => 'Báo cáo công nợ',
            'name' => 'Báo cáo công nợ công ty - Legacy DTX (chi tiết giải trừ)',
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
            'is_default' => false,
            'version' => '3.0',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $templateId = DB::table('templates')->where('report', self::TEMPLATE)->value('id');
        DB::table('report_definitions')->where('id', $reportId)->update([
            'parameter_ui_schema' => json_encode($this->ui(), JSON_UNESCAPED_UNICODE),
            'updated_at' => $now,
        ]);
        DB::table('report_definition_template')->where('report_definition_id', $reportId)->update([
            'is_default' => false,
            'updated_at' => $now,
        ]);
        DB::table('report_definition_template')->updateOrInsert(
            ['report_definition_id' => $reportId, 'template_id' => $templateId],
            ['is_default' => true, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now]
        );
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $reportId = DB::table('report_definitions')->where('code', self::REPORT)->value('id');
        $previousTemplateId = DB::table('templates')->where('report', self::PREVIOUS_TEMPLATE)->value('id');
        $templateId = DB::table('templates')->where('report', self::TEMPLATE)->value('id');
        $now = now();

        if ($reportId && $previousTemplateId) {
            DB::table('report_definition_template')->where('report_definition_id', $reportId)->update(['is_default' => false, 'updated_at' => $now]);
            DB::table('report_definition_template')->updateOrInsert(
                ['report_definition_id' => $reportId, 'template_id' => $previousTemplateId],
                ['is_default' => true, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        if ($templateId) {
            DB::table('report_definition_template')->where('template_id', $templateId)->delete();
            DB::table('templates')->where('id', $templateId)->delete();
        }
    }

    private function defaults(): array
    {
        return [
            'p_from_date' => now()->startOfDay()->format('Y-m-d H:i:s'),
            'p_to_date' => now()->endOfDay()->format('Y-m-d H:i:s'),
            'p_company_id' => null,
            'p_debt_status' => 'all',
            'p_all' => 0,
            'p_group_by_date' => 1,
            'p_show_settlements' => 1,
        ];
    }

    private function ui(): array
    {
        return [
            ['name' => 'p_from_date', 'label' => 'Ngày', 'control' => 'date-range', 'range_end_parameter' => 'p_to_date', 'default' => '$today', 'required' => true],
            ['name' => 'p_to_date', 'label' => 'Đến ngày', 'control' => 'hidden', 'default' => '$today', 'required' => true],
            ['name' => 'p_company_id', 'label' => 'Công ty', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'companies'],
            ['name' => 'p_debt_status', 'label' => 'Xem theo', 'control' => 'select', 'default' => 'all', 'required' => true, 'options' => [['value' => 'all', 'label' => 'Tất cả'], ['value' => 'unpaid', 'label' => 'Chưa thanh toán'], ['value' => 'paid', 'label' => 'Đã thanh toán']]],
            ['name' => 'p_all', 'label' => 'Xem tất cả', 'control' => 'checkbox', 'default' => false, 'required' => false],
            ['name' => 'p_group_by_date', 'label' => 'Nhóm theo ngày', 'control' => 'hidden', 'default' => true, 'required' => false],
            ['name' => 'p_show_settlements', 'label' => 'Hiển thị chi tiết giải trừ', 'control' => 'hidden', 'default' => true, 'required' => false],
        ];
    }

    private function companyProcedure(): string
    {
        return <<<'SQL'
CREATE PROCEDURE rpt_company_debt_legacy_dtx_v2(
    IN p_from_date DATETIME,
    IN p_to_date DATETIME,
    IN p_company_id BIGINT,
    IN p_debt_status VARCHAR(20),
    IN p_all TINYINT,
    IN p_group_by_date TINYINT,
    IN p_show_settlements TINYINT
)
READS SQL DATA
BEGIN
    WITH payment_settlements AS (
        SELECT p2.id AS payment_id, SUM(s2.amount) AS PaidAmount
        FROM payments AS p2
        INNER JOIN payment_debt_settlements AS s2
            ON (s2.payment_id = p2.id OR (s2.payment_id IS NULL AND s2.legacy_payment_table_id = p2.legacy_id))
           AND s2.edit_flag = 0
        GROUP BY p2.id
    ), source_rows AS (
        SELECT DISTINCT
            CONCAT(COALESCE(hs.prefix_booking_id, ''), COALESCE(si.legacy_booking_id, si.booking_id, p.booking_id, '')) AS BookingId,
            COALESCE(NULLIF(si.room, ''), br.room_number, '') AS Room,
            p.company_id AS CompanyId,
            c.name AS CompanyName,
            DATE_FORMAT(COALESCE(br.arrival_date, b.arrival_date), '%d/%m/%Y') AS ArrivalDate,
            DATE_FORMAT(COALESCE(br.departure_date, DATE_ADD(b.arrival_date, INTERVAL b.num_of_days DAY)), '%d/%m/%Y') AS DepartureDate,
            COALESCE(p.description, '') AS Description,
            si.amount AS TotalAmount,
            DATE_FORMAT(si.invoice_date, '%d/%m/%Y') AS `Date`,
            CASE WHEN COALESCE(p_group_by_date, 1) = 1 THEN DATE_FORMAT(si.invoice_date, '%d/%m/%Y') ELSE '' END AS DateGroup,
            COALESCE(si.username, '') AS Username,
            CASE WHEN si.amount - COALESCE(settlements.PaidAmount, 0) <= 0 THEN '☑' ELSE '' END AS PaidMarker,
            c.legacy_re_credit_limit AS MaxDebt,
            c.legacy_re_credit_limit AS ReCreditLimit,
            si.outlet AS Outlet,
            p.legacy_payment_total_amount0 AS AC,
            COALESCE(settlements.PaidAmount, 0) AS PaidAmount,
            si.amount - COALESCE(settlements.PaidAmount, 0) AS RemainingAmount,
            COALESCE(si.legacy_rental_room_id, CAST(si.booking_room_id AS CHAR), CAST(si.legacy_booking_id AS CHAR), CAST(si.booking_id AS CHAR)) AS RoomId,
            COALESCE(p.guest_display, '') AS Guest,
            p.legacy_pack5 AS Pack5,
            COALESCE(p.legacy_id, p.id) AS PaymentId,
            b.status AS StatusBooking,
            b.id AS Ma,
            CASE WHEN br.status = 2 THEN 2 ELSE NULL END AS StatusRoom,
            CASE WHEN COALESCE(p_show_settlements, 0) = 1 THEN (
                SELECT CONCAT('[', GROUP_CONCAT(JSON_OBJECT(
                    'BookingId', CONCAT(COALESCE(hs.prefix_booking_id, ''), COALESCE(si.legacy_booking_id, si.booking_id, p.booking_id, '')),
                    'CompanyName', c.name,
                    'PaymentId', COALESCE(p.legacy_id, p.id),
                    'SettlementId', COALESCE(s.legacy_id, s.id),
                    'Date', DATE_FORMAT(s.payment_date, '%d/%m/%Y'),
                    'PaymentMethod', COALESCE(NULLIF(settlement_methods.name, ''), s.payment_method_id, ''),
                    'Reference', s.description,
                    'Amount', s.amount,
                    'Username', COALESCE(s.created_by, ''),
                    'Status', 'valid'
                ) ORDER BY s.payment_date, COALESCE(s.legacy_id, s.id) SEPARATOR ','), ']')
                FROM payment_debt_settlements AS s
                LEFT JOIN payment_methods AS settlement_methods ON settlement_methods.code = s.payment_method_id
                WHERE (s.payment_id = p.id OR (s.payment_id IS NULL AND s.legacy_payment_table_id = p.legacy_id))
                  AND s.edit_flag = 0
            ) ELSE NULL END AS SettlementItems,
            si.invoice_date AS SortDate,
            COALESCE(si.legacy_booking_id, si.booking_id, p.booking_id, 0) AS SortBooking,
            COALESCE(p.legacy_id, p.id) AS SortPayment
        FROM sales_invoices AS si
        INNER JOIN payments AS p
            ON (p.invoice_id IS NOT NULL AND p.invoice_id = si.id)
            OR (p.invoice_id IS NULL AND p.payment_id = COALESCE(NULLIF(si.payment_code, ''), CAST(si.legacy_payment_id AS CHAR)))
        INNER JOIN companies AS c ON c.id = p.company_id
        LEFT JOIN booking_rooms AS br ON br.id = COALESCE(si.booking_room_id, p.booking_room_id) AND br.deleted_at IS NULL
        LEFT JOIN bookings AS b ON b.id = COALESCE(si.booking_id, p.booking_id, si.legacy_booking_id, br.booking_id) AND b.deleted_at IS NULL
        LEFT JOIN hotel_settings AS hs ON hs.id = (SELECT MIN(id) FROM hotel_settings)
        LEFT JOIN payment_settlements AS settlements ON settlements.payment_id = p.id
        WHERE p.payment_method_id = 'AC'
          AND COALESCE(p.pack2, '') = ''
          AND p.edit_flag = 0
          AND (COALESCE(p_all, 0) = 1 OR si.invoice_date BETWEEN p_from_date AND p_to_date)
          AND (p_company_id IS NULL OR p_company_id = 0 OR p.company_id = p_company_id)
          AND (p_debt_status IS NULL OR p_debt_status = '' OR p_debt_status = 'all'
              OR (p_debt_status = 'unpaid' AND si.amount - COALESCE(settlements.PaidAmount, 0) > 0)
              OR (p_debt_status = 'paid' AND si.amount - COALESCE(settlements.PaidAmount, 0) <= 0))
          AND EXISTS (
              SELECT 1 FROM service_bills AS sb
              WHERE (si.legacy_id IS NOT NULL AND sb.InvoiceId = si.legacy_id)
                 OR (si.legacy_id IS NULL AND sb.InvoiceId = si.id)
          )
    )
    SELECT BookingId, Room, CompanyId, CompanyName, ArrivalDate, DepartureDate, Description,
        TotalAmount, `Date`, DateGroup, Username, PaidMarker, MaxDebt, ReCreditLimit,
        Outlet, AC, PaidAmount, RemainingAmount, RoomId, Guest, Pack5, PaymentId,
        StatusBooking, Ma, StatusRoom, SettlementItems
    FROM source_rows
    ORDER BY SortDate, CompanyId, SortBooking, Room, SortPayment;
END
SQL;
    }
};
