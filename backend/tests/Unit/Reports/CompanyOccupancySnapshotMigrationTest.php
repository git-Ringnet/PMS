<?php

namespace Tests\Unit\Reports;

use Tests\TestCase;

class CompanyOccupancySnapshotMigrationTest extends TestCase
{
    private function migration(): string
    {
        return file_get_contents(database_path('migrations/2026_11_04_120000_implement_company_occupancy_snapshot_report.php'));
    }

    public function test_historical_snapshot_contract_captures_all_legacy_report_components(): void
    {
        $migration = $this->migration();

        foreach ([
            'night_audit_company_occupancy_daily_snapshots',
            'night_audit_company_occupancy_snapshots',
            'breakfast_revenue',
            'fb_revenue',
            'other_revenue',
            'total_by_date',
            'original_total',
            'revenue_service_codes',
            'revenue_er_service_codes',
        ] as $value) {
            $this->assertStringContainsString($value, $migration);
        }
    }

    public function test_summary_and_detail_use_snapshot_only_before_system_date(): void
    {
        $migration = $this->migration();

        $this->assertStringContainsString('night_audit_company_occupancy_snapshots s', $migration);
        $this->assertStringContainsString("LEAST(v_to, DATE_SUB(v_system_date, INTERVAL 1 DAY))", $migration);
        $this->assertStringContainsString('v_date < v_system_date', $migration);
        $this->assertStringContainsString('captureCompanyOccupancyReport', file_get_contents(app_path('Http/Controllers/Api/NightAuditController.php')));
    }

    public function test_breakfast_toggle_is_applied_to_room_revenue_once(): void
    {
        $migration = $this->migration();

        $this->assertStringContainsString('s.room_revenue - CASE WHEN COALESCE(p_include_breakfast, 1) = 0 THEN s.breakfast_revenue ELSE 0 END', $migration);
        $this->assertStringContainsString("'p_include_breakfast'", $migration);
    }

    public function test_legacy_toggle_layout_is_configuration_not_report_specific_frontend_code(): void
    {
        $migration = $this->migration();
        $reportsPage = file_get_contents(base_path('../frontend/src/pages/reports/ReportsPage.vue'));

        $this->assertStringContainsString("'layout' => ['group' => 'company_occupancy_switches'", $migration);
        $this->assertStringContainsString('reportParameterGroups', $reportsPage);
        $this->assertStringContainsString("layout.type === 'two-column'", $reportsPage);
    }

    public function test_market_and_source_toggles_use_designer_editable_single_selection_metadata(): void
    {
        $migration = $this->migration();
        $reportsPage = file_get_contents(base_path('../frontend/src/pages/reports/ReportsPage.vue'));
        $designer = file_get_contents(base_path('../frontend/src/pages/config/components/hotel/TemplateEditorModal.vue'));
        $controller = file_get_contents(app_path('Http/Controllers/Api/ReportDefinitionController.php'));

        $this->assertStringContainsString("'selection_group' => ['key' => 'company_occupancy_group_dimension'", $migration);
        $this->assertStringContainsString("selection_group?.mode === 'single'", $reportsPage);
        $this->assertStringContainsString('updateReportConditionExclusivePeer', $designer);
        $this->assertStringContainsString('Chọn một cùng', $designer);
        $this->assertStringNotContainsString('placeholder="Để trống: độc lập"', $designer);
        $this->assertStringContainsString('/parameter-ui-schema', $designer);
        $this->assertStringContainsString('updateParameterUiSchema', $controller);
        $this->assertStringContainsString("'selection_group' => \$ui['selection_group'] ?? null", $controller);
    }

    public function test_summary_sorting_keeps_legacy_group_then_revenue_order(): void
    {
        $migration = $this->migration();

        $this->assertStringContainsString("WHEN 'date' THEN DATE_FORMAT(d.ActivityDate, '%Y-%m-%d')", $migration);
        $this->assertStringContainsString("WHEN 'marketsegment' THEN CONCAT('M:'", $migration);
        $this->assertStringContainsString("WHEN 'sourcecode' THEN CONCAT('S:'", $migration);
        $this->assertStringContainsString('ORDER BY GroupHeaderId, TotalRevenue DESC, CompanyName ASC', $migration);
    }

    public function test_detail_switch_stays_on_summary_report_and_is_saved_as_template_column_configuration(): void
    {
        $migration = $this->migration();
        $template = file_get_contents(database_path('report_templates/company_occupancy_reference.php'));

        $this->assertStringNotContainsString("'linked_report_code' => 'COMPANY_OCCUPANCY_DETAIL'", $migration);
        $this->assertStringNotContainsString("'view_mode' => 'detail'", $migration);
        $this->assertStringContainsString("'p_show_detail'", $migration);
        $this->assertStringContainsString("'visibleWhen' => \\$column[5] ?? ''", $template);
        $this->assertStringContainsString("'parameters.p_show_detail'", $template);
        $this->assertStringContainsString('data-pms-auto-colspan', $template);
    }
}
