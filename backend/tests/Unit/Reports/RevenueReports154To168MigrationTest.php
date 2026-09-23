<?php

namespace Tests\Unit\Reports;

use Tests\TestCase;

class RevenueReports154To168MigrationTest extends TestCase
{
    private function templateDefinition(string $file): array
    {
        return (require database_path('report_templates/'.$file))->definition();
    }

    private function migration(): string
    {
        return file_get_contents(database_path('migrations/2026_09_22_170000_create_revenue_reports_154_159_160_166_167_168.php'));
    }

    public function test_all_report_installations_use_isolated_codes_and_supported_menu_locations(): void
    {
        $migration = $this->migration();

        foreach ([
            ['SUMMARY_SERVICE_INVOICES', 'RPT_SUMMARY_SERVICE_INVOICES', 'rpt_summary_service_invoices'],
            ['DEPOSITS_SUMMARY', 'RPT_DEPOSITS_SUMMARY', 'rpt_deposits_summary'],
            ['DAILY_SUMMARY', 'RPT_DAILY_SUMMARY', 'rpt_daily_summary'],
            ['COMPANY_OCCUPANCY_DETAIL', 'RPT_COMPANY_OCCUPANCY_DETAIL', 'rpt_company_occupancy_detail'],
            ['COMPANY_OCCUPANCY', 'RPT_COMPANY_OCCUPANCY', 'rpt_company_occupancy'],
            ['SALESPERSON_REVENUE_SUMMARY', 'RPT_SALESPERSON_REVENUE_SUMMARY', 'rpt_salesperson_revenue_summary'],
            ['SALESPERSON_REVENUE_DETAIL', 'RPT_SALESPERSON_REVENUE_DETAIL', 'rpt_salesperson_revenue_detail'],
        ] as [$report, $source, $procedure]) {
            $this->assertStringContainsString("'report' => '{$report}'", $migration);
            $this->assertStringContainsString("'source' => '{$source}'", $migration);
            $this->assertStringContainsString("'procedure' => '{$procedure}'", $migration);
        }

        $this->assertStringContainsString("'menu_locations' => json_encode(['reservation', 'frontdesk']", $migration);
        $this->assertStringNotContainsString('MainLayout.vue', $migration);
    }

    public function test_report_159_uses_one_row_per_settlement_and_settlement_amount(): void
    {
        $migration = $this->migration();

        $this->assertStringContainsString('payment_debt_settlements AS s', $migration);
        $this->assertStringContainsString('s.payment_date, si.payment_date, si.invoice_date, p.date', $migration);
        $this->assertStringContainsString('CASE WHEN COALESCE(p_option, 1) = 5 THEN COALESCE(s.amount, 0)', $migration);
        $this->assertStringContainsString('s.payment_id = p.id', $migration);
    }

    public function test_report_160_keeps_plan_fields_blank_and_uses_derived_month_label(): void
    {
        $migration = $this->migration();
        $template = file_get_contents(database_path('report_templates/daily_summary_reference.php'));
        $enricher = file_get_contents(base_path('app/Services/Reports/ReportDatasetEnricher.php'));

        $this->assertStringContainsString('NULL AS PlanAmount', $migration);
        $this->assertStringContainsString('NULL AS Rate', $migration);
        $this->assertStringContainsString('parameters.p_month_label', $template);
        $this->assertStringContainsString('enrichDailySummaryLabels', $enricher);
    }

    public function test_167_has_summary_contract_and_168_has_separate_summary_detail_contracts(): void
    {
        $migration = $this->migration();

        foreach (['CompanyCode','CompanyName','OccupancyRate','RoomNight','GuestQty','ActualADR','RackADR','RoomRevenue','FbRevenue','OtherRevenue','TotalRevenue'] as $field) {
            $this->assertStringContainsString("'{$field}'", $migration);
        }
        foreach (['SALESPERSON_REVENUE_SUMMARY', 'SALESPERSON_REVENUE_DETAIL', 'SalesPersonCode', 'SalesPersonName', 'BookingCode', 'FocRoomNights'] as $fragment) {
            $this->assertStringContainsString($fragment, $migration);
        }
        $this->assertStringNotContainsString('p_show_detail', $migration);
    }

    public function test_hardcoded_legacy_revenue_rules_are_documented(): void
    {
        $document = file_get_contents(base_path('../.codex/docs/hardcoded/report_revenue_legacy_rules.md'));

        foreach (['SP1610', 'SP1600', 'func_054', 'RM', 'FB', 'MB', 'LA', 'PU', 'DO'] as $fragment) {
            $this->assertStringContainsString($fragment, $document);
        }
    }

    public function test_data_loading_hotfixes_keep_ambiguous_sort_and_grouping_out_of_runtime(): void
    {
        $migration = $this->migration();
        $hotfix = file_get_contents(database_path('migrations/2026_09_23_100000_fix_revenue_reports_159_167_procedure_contract.php'));
        $patch = file_get_contents(database_path('migrations/2026_09_23_130000_finalize_revenue_reports_contracts.php'));

        $this->assertStringNotContainsString("THEN Amount END DESC", $migration);
        $this->assertStringNotContainsString("THEN Amount END ASC", $migration);
        $this->assertStringContainsString('COALESCE(s.amount, 0)', $migration);
        $this->assertStringContainsString('grouped_rows.CompanyCode', $migration);
        $this->assertStringContainsString('GROUP BY grouped_rows.CompanyCode, grouped_rows.CompanyName', $migration);
        $this->assertStringContainsString("SHOW CREATE PROCEDURE", $hotfix);
        $this->assertStringContainsString('rpt_deposits_summary', $hotfix);
        $this->assertStringContainsString('rpt_company_occupancy', $hotfix);
        $this->assertStringContainsString('SUM(x.BreakfastAmount)', $patch);
    }

    public function test_report_154_metadata_matches_procedure_output_and_grouped_template_contract(): void
    {
        $migration = $this->migration();
        $template = $this->templateDefinition('summary_service_invoices_reference.php');
        $patch = file_get_contents(database_path('migrations/2026_09_23_130000_finalize_revenue_reports_contracts.php'));
        $json = json_encode($template['content_json'], JSON_UNESCAPED_UNICODE);

        foreach (['Stt', 'ServiceCode', 'RevenueGroupName', 'ServiceGroupHeader', 'DateGroupHeader'] as $field) {
            $this->assertStringContainsString("'{$field}'", $migration);
        }
        $this->assertStringContainsString("['p_services','text']", $migration);
        $this->assertStringContainsString('"groups"', $json);
        $this->assertStringContainsString('RevenueGroupName', $json);
        $this->assertStringContainsString('parameters.p_group_by_service', $json);
        $this->assertStringContainsString('parameters.p_group_by_date', $json);
        $this->assertStringContainsString('data-group-field="RevenueGroupName"', $template['content_html']);
        $this->assertStringContainsString('row.Amount|number', $template['content_html']);
        $this->assertStringContainsString('repairSummaryServiceInvoicesMetadata', $patch);
    }

    public function test_report_159_and_167_preserve_grouping_and_breakfast_split_contracts(): void
    {
        $migration = $this->migration();
        $depositsTemplate = $this->templateDefinition('deposits_summary_reference.php');
        $patch = file_get_contents(database_path('migrations/2026_09_23_130000_finalize_revenue_reports_contracts.php'));
        $depositsJson = json_encode($depositsTemplate['content_json'], JSON_UNESCAPED_UNICODE);

        $this->assertStringContainsString('DepositGroup', $depositsJson);
        $this->assertStringContainsString('PaymentMethodName', $depositsJson);
        $this->assertStringContainsString('data-group-field="DepositGroup"', $depositsTemplate['content_html']);
        $this->assertStringContainsString('data-group-field="PaymentMethodName"', $depositsTemplate['content_html']);
        $this->assertStringContainsString('group.sum.Amount', $depositsJson);
        $this->assertStringContainsString('SUM(x.BreakfastAmount)', $migration);
        $this->assertStringContainsString('COALESCE(p_include_breakfast, 1) = 0', $migration);
        $this->assertStringContainsString('SUM(x.BreakfastAmount)', $patch);
    }

    public function test_report_160_matches_legacy_row_order_and_configured_revenue_groups(): void
    {
        $migration = $this->migration();
        $this->assertStringContainsString("DECLARE v_conference_list TEXT DEFAULT ''", $migration);
        $this->assertStringContainsString("COALESCE(NULLIF(MAX(CASE WHEN LOWER(name) = 'conferencerevenue'", $migration);
        $this->assertStringContainsString("UNION ALL SELECT '1-11',1,'FOC'", $migration);
        $this->assertStringContainsString("UNION ALL SELECT '6-1',6,'Tình trạng cơ sở vật chất',NULL,NULL,NULL,NULL,1,'Đã hoàn tất'", $migration);
        $this->assertStringContainsString("ORDER BY GroupIndex, CAST(SUBSTRING_INDEX(SortOrder, '-', -1) AS UNSIGNED)", $migration);
    }

    public function test_166_and_168_keep_internal_booking_code_separate_from_external_reference(): void
    {
        $migration = $this->migration();
        $this->assertStringContainsString('CONCAT(v_prefix, b.id) AS BookingCode', $migration);
        $this->assertStringContainsString("COALESCE(NULLIF(b.external_booking_code, ''), '') AS ReferenceCode", $migration);
        $this->assertStringContainsString('LEFT JOIN bookings bx ON bx.id = COALESCE(br.booking_id, NULLIF(sb.RegisterID2, 0))', $migration);
        $this->assertStringContainsString('COALESCE(p_filter_mode, 1) = 1 AND DATE(COALESCE(rnb.date, sb.Date)) BETWEEN COALESCE(bx.arrival_date', $migration);
    }

    public function test_all_reference_templates_have_designer_columns_and_runtime_contract_fields(): void
    {
        $templates = [
            ['daily_summary_reference.php', 'daily-summary-table', 'SortOrder'],
            ['company_occupancy_detail_reference.php', 'company-occupancy-detail-table', 'BookingCode'],
            ['company_occupancy_reference.php', 'company-occupancy-table', 'ActualADR'],
            ['salesperson_revenue_summary_reference.php', 'salesperson-summary-table', 'RoomNights'],
            ['salesperson_revenue_detail_reference.php', 'salesperson-detail-table', 'FocRoomNights'],
        ];

        foreach ($templates as [$file, $class, $field]) {
            $template = file_get_contents(database_path('report_templates/'.$file));
            $this->assertStringContainsString("'columns'", $template);
            $this->assertStringContainsString($class, $template);
            $this->assertStringContainsString($field, $template);
        }
    }

    public function test_grouped_reference_templates_render_without_losing_rows_or_totals(): void
    {
        $renderer = app(\App\Services\TemplateRendererService::class);
        $rows = [
            [
                'BookingCode' => 'BK001', 'RoomNumber' => '101', 'ArrivalDate' => '23/09/2026', 'DepartureDate' => '24/09/2026',
                'GuestName' => 'Khách 1', 'Description' => 'Dịch vụ', 'Amount' => 100, 'PaymentMethod' => 'CASH',
                'CompanyName' => 'Khách lẻ', 'OpenTime' => '10:00', 'Note' => '', 'RevenueGroupName' => 'Doanh Thu Dịch Vụ',
                'ServiceGroupHeader' => 'Dịch vụ: FB - F&B', 'DateGroupHeader' => '23/09/2026',
                'DepositGroup' => 'Chưa sử dụng', 'MaDatCoc' => '1', 'MTT' => '', 'PaymentDate' => '23/09/2026', 'TimePayment' => '10:00',
                'BookingRoomCode' => 'BK001/101', 'BookingName' => 'Khách 1', 'BusinessName' => 'Khách lẻ', 'ArrivalDate' => '23/09/2026',
                'DepartureDate' => '24/09/2026', 'PaymentMethodName' => 'Tiền mặt', 'Description' => '', 'Username' => 'admin',
            ],
        ];

        $service = (require database_path('report_templates/summary_service_invoices_reference.php'))->definition();
        $serviceHtml = $renderer->render($service['content_html'], $service['css'], [
            'rows' => [$rows[0]],
            'service_summary' => [['Label' => 'Tổng', 'Amount' => 100]],
            'parameters' => ['p_group_by_service' => 1, 'p_group_by_date' => 1],
        ]);
        $this->assertStringContainsString('BK001', $serviceHtml);
        $this->assertStringContainsString('100', $serviceHtml);

        $deposit = (require database_path('report_templates/deposits_summary_reference.php'))->definition();
        $depositHtml = $renderer->render($deposit['content_html'], $deposit['css'], ['rows' => [$rows[0]], 'parameters' => []]);
        $this->assertStringContainsString('BK001/101', $depositHtml);
        $this->assertStringContainsString('Tổng cộng', $depositHtml);
    }
}
