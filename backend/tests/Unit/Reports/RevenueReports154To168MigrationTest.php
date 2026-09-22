<?php

namespace Tests\Unit\Reports;

use Tests\TestCase;

class RevenueReports154To168MigrationTest extends TestCase
{
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
}
