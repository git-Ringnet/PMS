<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const COMPANY_REPORT = 'COMPANY_DEBT';
    private const COMPANY_SOURCE = 'COMPANY_DEBT';
    private const COMPANY_TEMPLATE = 'COMPANY_DEBT_LEGACY_DTX_V2';
    private const PAID_REPORT = 'PAID_COMPANY_DEBTS';
    private const PAID_SOURCE = 'PAID_COMPANY_DEBTS';
    private const PAID_TEMPLATE = 'PAID_COMPANY_DEBTS_LEGACY_ARMY_V2';

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_company_debt_legacy_dtx_v2');
        DB::unprepared($this->companyProcedure());
        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_paid_company_debts_legacy_army_v2');
        DB::unprepared($this->paidProcedure());

        $now = now();
        $database = DB::connection()->getDatabaseName();
        $companySourceId = $this->upsertSource($now, $database, self::COMPANY_SOURCE, 'rpt_company_debt_legacy_dtx_v2', $this->companyParameters(), $this->companyFields(), $this->companyDefaults(), 'Báo cáo công nợ công ty theo baseline ProVista DTX sp_212.');
        $paidSourceId = $this->upsertSource($now, $database, self::PAID_SOURCE, 'rpt_paid_company_debts_legacy_army_v2', $this->paidParameters(), $this->paidFields(), $this->paidDefaults(), 'Báo cáo công nợ đã thanh toán theo baseline ProVista Army/Navy sp_294.');

        $companyTemplateId = $this->upsertTemplate($now, self::COMPANY_TEMPLATE, $companySourceId, 'Báo cáo công nợ công ty - Legacy DTX', 'company_debt_reference.php', $this->companyDefaults());
        $paidTemplateId = $this->upsertTemplate($now, self::PAID_TEMPLATE, $paidSourceId, 'Báo cáo công nợ đã thanh toán - Legacy Army/Navy', 'paid_company_debts_reference.php', $this->paidDefaults());

        $this->pointReportAtClone($now, self::COMPANY_REPORT, $companySourceId, $companyTemplateId, $this->companyUi(), 'Công nợ công ty theo mẫu legacy DTX.');
        $this->pointReportAtClone($now, self::PAID_REPORT, $paidSourceId, $paidTemplateId, $this->paidUi(), 'Công nợ đã thanh toán theo mẫu legacy Army/Navy.');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $this->restoreSources();
        $this->restoreExistingReport(self::COMPANY_REPORT, self::COMPANY_SOURCE, 'COMPANY_DEBT_REFERENCE', $this->legacyCompanyUi());
        $this->restoreExistingReport(self::PAID_REPORT, self::PAID_SOURCE, 'PAID_COMPANY_DEBTS_REFERENCE', $this->legacyPaidUi());

        DB::table('templates')->whereIn('report', [self::COMPANY_TEMPLATE, self::PAID_TEMPLATE])->delete();
        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_company_debt_legacy_dtx_v2');
        DB::unprepared('DROP PROCEDURE IF EXISTS rpt_paid_company_debts_legacy_army_v2');
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
        SELECT
            p2.id AS payment_id,
            SUM(s2.amount) AS PaidAmount
        FROM payments AS p2
        INNER JOIN payment_debt_settlements AS s2
            ON (
                s2.payment_id = p2.id
                OR (s2.payment_id IS NULL AND s2.legacy_payment_table_id = p2.legacy_id)
            )
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
                    'Reference', s.description,
                    'Amount', s.amount,
                    'PaymentMethod', COALESCE(NULLIF(settlement_methods.name, ''), s.payment_method_id, ''),
                    'Username', COALESCE(s.created_by, ''),
                    'Status', 'valid'
                ) ORDER BY s.payment_date, COALESCE(s.legacy_id, s.id) SEPARATOR ','), ']')
                FROM payment_debt_settlements AS s
                LEFT JOIN payment_methods AS settlement_methods ON settlement_methods.code = s.payment_method_id
                WHERE (
                    s.payment_id = p.id
                    OR (s.payment_id IS NULL AND s.legacy_payment_table_id = p.legacy_id)
                )
                  AND s.edit_flag = 0
            ) ELSE NULL END AS SettlementItems,
            si.invoice_date AS SortDate,
            COALESCE(si.legacy_booking_id, si.booking_id, p.booking_id, 0) AS SortBooking,
            COALESCE(p.legacy_id, p.id) AS SortPayment
        FROM sales_invoices AS si
        INNER JOIN payments AS p
            ON (
                p.invoice_id IS NOT NULL AND p.invoice_id = si.id
            )
            OR (
                p.invoice_id IS NULL
                AND p.payment_id = COALESCE(NULLIF(si.payment_code, ''), CAST(si.legacy_payment_id AS CHAR))
            )
        INNER JOIN companies AS c ON c.id = p.company_id
        LEFT JOIN booking_rooms AS br
            ON br.id = COALESCE(si.booking_room_id, p.booking_room_id)
           AND br.deleted_at IS NULL
        LEFT JOIN bookings AS b
            ON b.id = COALESCE(si.booking_id, p.booking_id, si.legacy_booking_id, br.booking_id)
           AND b.deleted_at IS NULL
        LEFT JOIN hotel_settings AS hs ON hs.id = (SELECT MIN(id) FROM hotel_settings)
        LEFT JOIN payment_settlements AS settlements ON settlements.payment_id = p.id
        WHERE p.payment_method_id = 'AC'
          AND COALESCE(p.pack2, '') = ''
          AND p.edit_flag = 0
          AND (COALESCE(p_all, 0) = 1 OR si.invoice_date BETWEEN p_from_date AND p_to_date)
          AND (p_company_id IS NULL OR p_company_id = 0 OR p.company_id = p_company_id)
          AND (
              p_debt_status IS NULL
              OR p_debt_status = ''
              OR p_debt_status = 'all'
              OR (p_debt_status = 'unpaid' AND si.amount - COALESCE(settlements.PaidAmount, 0) > 0)
              OR (p_debt_status = 'paid' AND si.amount - COALESCE(settlements.PaidAmount, 0) <= 0)
          )
          AND EXISTS (
              SELECT 1
              FROM service_bills AS sb
              WHERE (si.legacy_id IS NOT NULL AND sb.InvoiceId = si.legacy_id)
                 OR (si.legacy_id IS NULL AND sb.InvoiceId = si.id)
          )
    )
    SELECT
        BookingId, Room, CompanyId, CompanyName, ArrivalDate, DepartureDate, Description,
        TotalAmount, `Date`, DateGroup, Username, PaidMarker, MaxDebt, ReCreditLimit,
        Outlet, AC, PaidAmount, RemainingAmount, RoomId, Guest, Pack5, PaymentId,
        StatusBooking, Ma, StatusRoom, SettlementItems
    FROM source_rows
    ORDER BY SortDate, CompanyId, SortBooking, Room, SortPayment;
END
SQL;
    }

    private function paidProcedure(): string
    {
        return <<<'SQL'
CREATE PROCEDURE rpt_paid_company_debts_legacy_army_v2(
    IN p_from_date DATETIME,
    IN p_to_date DATETIME,
    IN p_view_type TINYINT,
    IN p_company_id BIGINT,
    IN p_group_by_company TINYINT,
    IN p_is_fb TINYINT
)
READS SQL DATA
BEGIN
    WITH debt_payments AS (
        SELECT
            p.id AS DebtRowKey,
            ROW_NUMBER() OVER (PARTITION BY p.id ORDER BY COALESCE(s.payment_date, '9999-12-31'), COALESCE(s.id, 0)) AS PaymentSortNo,
            COUNT(*) OVER (PARTITION BY p.id) AS DebtRowCount,
            CONCAT(COALESCE(hs.prefix_booking_id, ''), COALESCE(b.id, p.booking_id, '')) AS BookingId,
            DATE_FORMAT(p.date, '%d/%m/%Y') AS DateCN,
            COALESCE(r.room_number, br.room_number, '') AS Room,
            COALESCE(b.booking_name, p.guest_display, '') AS BookingName,
            CASE WHEN COALESCE(p_is_fb, 0) = 1 THEN NULL ELSE DATE_FORMAT(COALESCE(br.arrival_date, b.arrival_date), '%d/%m/%Y') END AS ArrivalDate,
            CASE WHEN COALESCE(p_is_fb, 0) = 1 THEN NULL ELSE DATE_FORMAT(COALESCE(br.departure_date, DATE_ADD(b.arrival_date, INTERVAL b.num_of_days DAY)), '%d/%m/%Y') END AS DepartureDate,
            p.amount AS AmountCN,
            COALESCE(p.payment_method_id, 'AC') AS PaymentMethodCN,
            COALESCE(p.created_by, p.username, '') AS UserCN,
            DATE_FORMAT(s.payment_date, '%d/%m/%Y') AS DateTT,
            s.payment_date AS RawDateTT,
            COALESCE(s.amount, 0) AS AmountTT,
            COALESCE(s.payment_method_id, '') AS PaymentMethodTT,
            COALESCE(s.created_by, '') AS UserTT,
            COALESCE(s.description, '') AS Description,
            COALESCE(c.name, 'Chưa gán công ty') AS CompanyName,
            c.id AS CompanyId,
            s.id AS SettlementId,
            CASE WHEN COALESCE(p_view_type, 0) = 0 THEN DATE_FORMAT(p.date, '%d/%m/%Y') ELSE DATE_FORMAT(s.payment_date, '%d/%m/%Y') END AS DateGroup,
            CASE WHEN COALESCE(p_view_type, 0) = 0 THEN p.date ELSE s.payment_date END AS RawDateGroup,
            SUM(COALESCE(s.amount, 0)) OVER (
                PARTITION BY p.id
                ORDER BY COALESCE(s.payment_date, '9999-12-31'), COALESCE(s.id, 0)
                ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
            ) AS PaidRunning,
            SUM(COALESCE(s.amount, 0)) OVER (PARTITION BY p.id) AS PaidTotal
        FROM payments AS p
        LEFT JOIN payment_debt_settlements AS s
            ON (
                s.payment_id = p.id
                OR (s.payment_id IS NULL AND s.legacy_payment_table_id = p.legacy_id)
            )
           AND s.edit_flag = 0
        LEFT JOIN bookings AS b ON b.id = p.booking_id AND b.deleted_at IS NULL
        LEFT JOIN booking_rooms AS br ON br.id = p.booking_room_id AND br.deleted_at IS NULL
        LEFT JOIN rooms AS r ON r.room_number = br.room_number
        LEFT JOIN companies AS c ON c.id = p.company_id
        LEFT JOIN hotel_settings AS hs ON hs.id = (SELECT MIN(id) FROM hotel_settings)
        WHERE p.payment_method_id = 'AC'
          AND p.amount > 0
          AND p.edit_flag = 0
          AND (p_company_id IS NULL OR p_company_id = 0 OR p.company_id = p_company_id)
          AND (
              (COALESCE(p_is_fb, 0) = 0 AND (p.department_id IS NULL OR p.department_id <> 'FB'))
              OR (p_is_fb = 1 AND p.department_id = 'FB')
          )
          AND (
              (COALESCE(p_view_type, 0) = 0 AND p.date BETWEEN p_from_date AND p_to_date)
              OR (p_view_type = 1 AND s.payment_date BETWEEN p_from_date AND p_to_date)
          )
    ), final_rows AS (
        SELECT
            dp.*,
            CASE WHEN dp.PaymentSortNo = 1 THEN dp.AmountCN ELSE 0 END AS AmountCNForTotal,
            GREATEST(0, dp.AmountCN - dp.PaidRunning) AS RemainAmount,
            CASE WHEN dp.PaidTotal >= dp.AmountCN THEN '☑' ELSE '' END AS IsPaidMarker,
            CASE WHEN dp.PaymentSortNo = dp.DebtRowCount THEN GREATEST(0, dp.AmountCN - dp.PaidTotal) ELSE 0 END AS RemainAmountForTotal
        FROM debt_payments AS dp
    )
    SELECT
        fr.DebtRowKey, fr.PaymentSortNo, fr.DebtRowCount, fr.BookingId, fr.DateCN,
        fr.Room, fr.BookingName, fr.ArrivalDate, fr.DepartureDate, fr.AmountCN,
        fr.AmountCNForTotal, fr.PaymentMethodCN, fr.UserCN, fr.DateTT, fr.AmountTT,
        fr.PaymentMethodTT, fr.RemainAmount, fr.RemainAmountForTotal, fr.UserTT,
        fr.Description, fr.CompanyName AS Company, fr.CompanyName, fr.CompanyId,
        fr.DateGroup, fr.IsPaidMarker,
        SUM(fr.AmountCNForTotal) OVER (PARTITION BY fr.DateGroup, fr.CompanyId) AS CompanyTotalDebt,
        SUM(fr.AmountTT) OVER (PARTITION BY fr.DateGroup, fr.CompanyId) AS CompanyTotalPaid,
        SUM(fr.RemainAmountForTotal) OVER (PARTITION BY fr.DateGroup, fr.CompanyId) AS CompanyRemaining,
        SUM(fr.AmountCNForTotal) OVER (PARTITION BY fr.DateGroup) AS DateTotalDebt,
        SUM(fr.AmountTT) OVER (PARTITION BY fr.DateGroup) AS DateTotalPaid,
        SUM(fr.RemainAmountForTotal) OVER (PARTITION BY fr.DateGroup) AS DateRemaining
    FROM final_rows AS fr
    ORDER BY
        CASE WHEN COALESCE(p_group_by_company, 0) = 1 THEN fr.CompanyName ELSE fr.RawDateGroup END ASC,
        CASE WHEN COALESCE(p_group_by_company, 0) = 1 THEN fr.RawDateGroup ELSE fr.CompanyName END ASC,
        fr.DebtRowKey ASC,
        fr.PaymentSortNo ASC;
END
SQL;
    }

    private function upsertSource($now, string $database, string $code, string $procedure, array $parameters, array $fields, array $defaults, string $description): int
    {
        DB::table('report_data_sources')->updateOrInsert(['code' => $code], [
            'name' => $description,
            'description' => $description,
            'source_type' => 'procedure',
            'schema_name' => $database,
            'object_name' => $procedure,
            'parameter_schema' => json_encode($parameters, JSON_UNESCAPED_UNICODE),
            'field_schema' => json_encode($fields, JSON_UNESCAPED_UNICODE),
            'sample_parameters' => json_encode($defaults, JSON_UNESCAPED_UNICODE),
            'max_rows' => 5000,
            'is_active' => true,
            'last_discovered_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) DB::table('report_data_sources')->where('code', $code)->value('id');
    }

    private function upsertTemplate($now, string $report, int $sourceId, string $name, string $provider, array $defaults): int
    {
        $definition = (require database_path('report_templates/'.$provider))->definition();
        DB::table('templates')->updateOrInsert(['report' => $report], [
            'group' => 'Báo cáo công nợ',
            'name' => $name,
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
            'version' => '2.0',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) DB::table('templates')->where('report', $report)->value('id');
    }

    private function pointReportAtClone($now, string $reportCode, int $sourceId, int $templateId, array $ui, string $description): void
    {
        $reportId = DB::table('report_definitions')->where('code', $reportCode)->value('id');
        if (! $reportId) {
            return;
        }

        DB::table('report_definitions')->where('id', $reportId)->update([
            'report_data_source_id' => $sourceId,
            'parameter_ui_schema' => json_encode($ui, JSON_UNESCAPED_UNICODE),
            'description' => $description,
            'updated_at' => $now,
        ]);
        DB::table('report_definition_template')->where('report_definition_id', $reportId)->update(['is_default' => false, 'updated_at' => $now]);
        DB::table('report_definition_template')->updateOrInsert(
            ['report_definition_id' => $reportId, 'template_id' => $templateId],
            ['is_default' => true, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now]
        );
    }

    private function restoreExistingReport(string $reportCode, string $sourceCode, string $templateCode, array $ui): void
    {
        $reportId = DB::table('report_definitions')->where('code', $reportCode)->value('id');
        $sourceId = DB::table('report_data_sources')->where('code', $sourceCode)->value('id');
        $templateId = DB::table('templates')->where('report', $templateCode)->value('id');
        if (! $reportId || ! $sourceId || ! $templateId) {
            return;
        }

        $now = now();
        DB::table('report_definition_template')->where('report_definition_id', $reportId)->whereIn('template_id', DB::table('templates')->whereIn('report', [self::COMPANY_TEMPLATE, self::PAID_TEMPLATE])->pluck('id'))->delete();
        DB::table('report_definitions')->where('id', $reportId)->update([
            'report_data_source_id' => $sourceId,
            'parameter_ui_schema' => json_encode($ui, JSON_UNESCAPED_UNICODE),
            'updated_at' => $now,
        ]);
        DB::table('report_definition_template')->updateOrInsert(
            ['report_definition_id' => $reportId, 'template_id' => $templateId],
            ['is_default' => true, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now]
        );
    }

    private function restoreSources(): void
    {
        $now = now();
        DB::table('report_data_sources')->where('code', self::COMPANY_SOURCE)->update([
            'name' => 'Dữ liệu báo cáo công nợ công ty',
            'description' => 'MySQL chuyển đổi từ ProVista sp_212; dòng chi tiết giữ grain SELECT DISTINCT.',
            'object_name' => 'rpt_company_debt',
            'parameter_schema' => json_encode($this->legacyCompanyParameters(), JSON_UNESCAPED_UNICODE),
            'field_schema' => json_encode($this->companyFields(), JSON_UNESCAPED_UNICODE),
            'sample_parameters' => json_encode($this->legacyCompanyDefaults(), JSON_UNESCAPED_UNICODE),
            'updated_at' => $now,
        ]);
        DB::table('report_data_sources')->where('code', self::PAID_SOURCE)->update([
            'name' => 'Dữ liệu báo cáo công nợ đã thanh toán',
            'description' => 'MySQL chuyển đổi theo legacy sp_294; danh sách các khoản nợ công ty đã thanh toán/giải trừ.',
            'object_name' => 'rpt_paid_company_debts',
            'parameter_schema' => json_encode($this->legacyPaidParameters(), JSON_UNESCAPED_UNICODE),
            'field_schema' => json_encode($this->legacyPaidFields(), JSON_UNESCAPED_UNICODE),
            'sample_parameters' => json_encode($this->legacyPaidDefaults(), JSON_UNESCAPED_UNICODE),
            'updated_at' => $now,
        ]);
    }

    private function companyParameters(): array
    {
        return $this->parameters([
            ['p_from_date', 'datetime', true], ['p_to_date', 'datetime', true], ['p_company_id', 'bigint', false],
            ['p_debt_status', 'varchar', true], ['p_all', 'tinyint', true], ['p_group_by_date', 'tinyint', true], ['p_show_settlements', 'tinyint', true],
        ]);
    }

    private function legacyCompanyParameters(): array
    {
        return $this->parameters([
            ['p_from_date', 'datetime', true], ['p_to_date', 'datetime', true], ['p_company_id', 'bigint', false],
            ['p_group_by_date', 'tinyint', true], ['p_show_settlements', 'tinyint', true],
        ]);
    }

    private function paidParameters(): array
    {
        return $this->parameters([
            ['p_from_date', 'datetime', true], ['p_to_date', 'datetime', true], ['p_view_type', 'tinyint', true],
            ['p_company_id', 'bigint', false], ['p_group_by_company', 'tinyint', false], ['p_is_fb', 'tinyint', true],
        ]);
    }

    private function legacyPaidParameters(): array
    {
        return $this->parameters([
            ['p_from_date', 'datetime', true], ['p_to_date', 'datetime', true], ['p_view_type', 'tinyint', true],
            ['p_company_id', 'bigint', false], ['p_group_by_company', 'tinyint', false],
        ]);
    }

    private function parameters(array $definitions): array
    {
        return array_map(static fn (array $definition, int $index): array => [
            'name' => $definition[0], 'mode' => 'IN', 'data_type' => $definition[1], 'database_type' => $definition[1],
            'position' => $index + 1, 'required' => $definition[2],
        ], $definitions, array_keys($definitions));
    }

    private function companyFields(): array
    {
        return $this->fields(['BookingId','Room','CompanyId','CompanyName','ArrivalDate','DepartureDate','Description','TotalAmount','Date','DateGroup','Username','PaidMarker','MaxDebt','ReCreditLimit','Outlet','AC','PaidAmount','RemainingAmount','RoomId','Guest','Pack5','PaymentId','StatusBooking','Ma','StatusRoom','SettlementItems'], ['CompanyId','TotalAmount','MaxDebt','ReCreditLimit','AC','PaidAmount','RemainingAmount','PaymentId','StatusBooking','Ma','StatusRoom']);
    }

    private function paidFields(): array
    {
        return $this->fields(['DebtRowKey','PaymentSortNo','DebtRowCount','BookingId','DateCN','Room','BookingName','ArrivalDate','DepartureDate','AmountCN','AmountCNForTotal','PaymentMethodCN','UserCN','DateTT','AmountTT','PaymentMethodTT','RemainAmount','RemainAmountForTotal','UserTT','Description','Company','CompanyName','CompanyId','DateGroup','IsPaidMarker','CompanyTotalDebt','CompanyTotalPaid','CompanyRemaining','DateTotalDebt','DateTotalPaid','DateRemaining'], ['DebtRowKey','PaymentSortNo','DebtRowCount','AmountCN','AmountCNForTotal','AmountTT','RemainAmount','RemainAmountForTotal','CompanyId','CompanyTotalDebt','CompanyTotalPaid','CompanyRemaining','DateTotalDebt','DateTotalPaid','DateRemaining']);
    }

    private function legacyPaidFields(): array
    {
        return $this->fields(['DebtRowKey','BookingId','Room','BookingName','ArrivalDate','DepartureDate','AmountCN','AmountCNForTotal','PaymentMethodCN','DateCN','UserCN','IsPaidMarker','DateTT','AmountTT','PaymentMethodTT','RemainAmount','UserTT','Description','Company','CompanyName','DateGroup','CompanyTotalDebt','CompanyTotalPaid','CompanyRemaining','DateTotalDebt','DateTotalPaid','DateRemaining'], ['DebtRowKey','AmountCN','AmountCNForTotal','AmountTT','RemainAmount','CompanyTotalDebt','CompanyTotalPaid','CompanyRemaining','DateTotalDebt','DateTotalPaid','DateRemaining']);
    }

    private function fields(array $names, array $numeric): array
    {
        return array_map(static fn (string $name): array => ['name' => $name, 'type' => in_array($name, $numeric, true) ? 'number' : 'string', 'nullable' => true], $names);
    }

    private function companyDefaults(): array
    {
        return ['p_from_date' => now()->startOfDay()->format('Y-m-d H:i:s'), 'p_to_date' => now()->endOfDay()->format('Y-m-d H:i:s'), 'p_company_id' => null, 'p_debt_status' => 'all', 'p_all' => 0, 'p_group_by_date' => 1, 'p_show_settlements' => 1];
    }

    private function legacyCompanyDefaults(): array
    {
        return ['p_from_date' => now()->startOfDay()->format('Y-m-d H:i:s'), 'p_to_date' => now()->endOfDay()->format('Y-m-d H:i:s'), 'p_company_id' => null, 'p_group_by_date' => 1, 'p_show_settlements' => 0];
    }

    private function paidDefaults(): array
    {
        return ['p_from_date' => now()->startOfDay()->format('Y-m-d H:i:s'), 'p_to_date' => now()->endOfDay()->format('Y-m-d H:i:s'), 'p_view_type' => 1, 'p_company_id' => null, 'p_group_by_company' => 0, 'p_is_fb' => 0];
    }

    private function legacyPaidDefaults(): array
    {
        return ['p_from_date' => now()->startOfDay()->format('Y-m-d H:i:s'), 'p_to_date' => now()->endOfDay()->format('Y-m-d H:i:s'), 'p_view_type' => 0, 'p_company_id' => null, 'p_group_by_company' => 0];
    }

    private function companyUi(): array
    {
        return [
            ['name' => 'p_from_date', 'label' => 'Từ ngày', 'control' => 'date-range', 'range_end_parameter' => 'p_to_date', 'default' => '$today', 'required' => true],
            ['name' => 'p_to_date', 'label' => 'Đến ngày', 'control' => 'hidden', 'default' => '$today', 'required' => true],
            ['name' => 'p_company_id', 'label' => 'Công ty', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'companies'],
            ['name' => 'p_debt_status', 'label' => 'Xem theo', 'control' => 'select', 'default' => 'all', 'required' => true, 'options' => [['value' => 'all', 'label' => 'Tất cả'], ['value' => 'unpaid', 'label' => 'Chưa thanh toán'], ['value' => 'paid', 'label' => 'Đã thanh toán']]],
            ['name' => 'p_all', 'label' => 'Xem tất cả', 'control' => 'checkbox', 'default' => false, 'required' => false],
            ['name' => 'p_group_by_date', 'label' => 'Nhóm theo ngày', 'control' => 'hidden', 'default' => true, 'required' => false],
            ['name' => 'p_show_settlements', 'label' => 'Hiển thị chi tiết giải trừ', 'control' => 'hidden', 'default' => true, 'required' => false],
        ];
    }

    private function legacyCompanyUi(): array
    {
        return [
            ['name' => 'p_from_date', 'label' => 'Từ ngày', 'control' => 'date-range', 'range_end_parameter' => 'p_to_date', 'default' => '$today', 'required' => true],
            ['name' => 'p_to_date', 'label' => 'Đến ngày', 'control' => 'hidden', 'default' => '$today', 'required' => true],
            ['name' => 'p_company_id', 'label' => 'Công ty', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'companies'],
            ['name' => 'p_group_by_date', 'label' => 'Nhóm theo ngày', 'control' => 'checkbox', 'default' => true, 'required' => false],
            ['name' => 'p_show_settlements', 'label' => 'Hiển thị chi tiết giải trừ', 'control' => 'checkbox', 'default' => false, 'required' => false],
        ];
    }

    private function paidUi(): array
    {
        return [
            ['name' => 'p_from_date', 'label' => 'Ngày', 'control' => 'date-range', 'range_end_parameter' => 'p_to_date', 'default' => '$today', 'required' => true],
            ['name' => 'p_to_date', 'label' => 'Đến ngày', 'control' => 'hidden', 'default' => '$today', 'required' => true],
            ['name' => 'p_view_type', 'label' => 'Xem theo', 'control' => 'select', 'default' => 1, 'required' => true, 'options' => [['value' => 0, 'label' => 'Xem theo ngày công nợ'], ['value' => 1, 'label' => 'Xem theo ngày trả công nợ']]],
            ['name' => 'p_company_id', 'label' => 'Công ty', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'companies'],
            ['name' => 'p_group_by_company', 'label' => 'Nhóm theo công ty', 'control' => 'hidden', 'default' => 0, 'required' => false],
            ['name' => 'p_is_fb', 'label' => 'Loại dịch vụ', 'control' => 'hidden', 'default' => 0, 'required' => true],
        ];
    }

    private function legacyPaidUi(): array
    {
        return [
            ['name' => 'p_from_date', 'label' => 'Từ ngày', 'control' => 'date-range', 'range_end_parameter' => 'p_to_date', 'default' => '$today', 'required' => true],
            ['name' => 'p_to_date', 'label' => 'Đến ngày', 'control' => 'hidden', 'default' => '$today', 'required' => true],
            ['name' => 'p_view_type', 'label' => 'Xem theo', 'control' => 'select', 'default' => 0, 'required' => true, 'options' => [['value' => 0, 'label' => 'Xem theo ngày trả công nợ'], ['value' => 1, 'label' => 'Xem theo ngày công nợ']]],
            ['name' => 'p_company_id', 'label' => 'Chọn công ty', 'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'companies'],
            ['name' => 'p_group_by_company', 'label' => 'Nhóm theo công ty', 'control' => 'checkbox', 'default' => false, 'required' => false],
        ];
    }
};
