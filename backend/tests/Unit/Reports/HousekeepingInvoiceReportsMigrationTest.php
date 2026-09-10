<?php

namespace Tests\Unit\Reports;

use Tests\TestCase;

class HousekeepingInvoiceReportsMigrationTest extends TestCase
{
    public function test_report_metadata_accepts_housekeeping_shift_and_department_lookups(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/Api/ReportDefinitionController.php'));

        $this->assertStringContainsString(
            'hotel-services,report-shifts,service-departments',
            $controller
        );
    }

    public function test_runtime_registration_keeps_each_invoice_report_in_its_own_migration(): void
    {
        $migrations = [
            'LAUNDRY_INVOICES' => ['2026_09_10_300000_create_housekeeping_invoice_reports.php', 'LA', 'rpt_laundry_invoices', 'LAUNDRY_INVOICES_STANDARD', 'laundry_invoices_reference.php'],
            'BREAKAGE_INVOICES' => ['2026_09_10_320000_create_breakage_invoice_report.php', 'BR', 'rpt_breakage_invoices', 'BREAKAGE_INVOICES_STANDARD', 'breakage_invoices_reference.php'],
            'MINIBAR_INVOICES' => ['2026_09_10_330000_create_minibar_invoice_report.php', 'MB', 'rpt_minibar_invoices', 'MINIBAR_INVOICES_STANDARD', 'minibar_invoices_reference.php'],
        ];

        foreach ($migrations as $code => [$file, $outlet, $procedure, $template, $provider]) {
            $migration = file_get_contents(database_path('migrations/'.$file));
            $this->assertStringContainsString("'{$code}'", $migration);
            $this->assertStringContainsString("'outlet' => '{$outlet}'", $migration);
            $this->assertStringContainsString("'procedure' => '{$procedure}'", $migration);
            $this->assertStringContainsString("'template' => '{$template}'", $migration);
            $this->assertStringContainsString("'template_file' => '{$provider}'", $migration);
        }

        $migration = file_get_contents(database_path('migrations/2026_09_10_300000_create_housekeeping_invoice_reports.php'));
        $this->assertSame(9, substr_count($migration, "'mode' => 'IN'"));
        $this->assertStringContainsString("'content_json' => json_encode(\$templateDefinition['content_json'] ?? []", $migration);
        $this->assertStringContainsString("'content_html' => \$templateDefinition['content_html'] ?? ''", $migration);
        $this->assertStringContainsString("'css' => \$templateDefinition['css'] ?? ''", $migration);
    }

    public function test_procedure_keeps_legacy_view_filters_money_mapping_products_and_payment_choice(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_09_10_300000_create_housekeeping_invoice_reports.php'));

        foreach ([
            'sb.Amount <> 0',
            'sb.Status <> 4',
            "UPPER(p_view_type) = 'POST'",
            'sb.Edit = 0',
            "COALESCE(pay.PaymentMethod, '') <> 'CL'",
            "UPPER(p_view_type) = 'CORRECT'",
            'sb.Edit = 1',
            'h.BillEdit = 1',
            "h.Outlet = '__OUTLET__'",
            "pay.PaymentMethod = 'CL'",
            "ORDER BY COALESCE(p.legacy_id, p.id), p.id",
            "CONCAT('* ', pl.Product, ' - ', CAST(pl.Quantity AS CHAR))",
            "CONCAT('[', GROUP_CONCAT(JSON_OBJECT(",
            'h.BillOriginalAmount AS TotalAmount',
            'h.BillDiscountAmount AS DiscountAmount',
            'COALESCE(h.BillTotalAmount, h.BillAmount) AS NetAmount',
            'SUM(h.BillOriginalAmount) OVER () AS ReportTotalAmount',
            'SUM(h.BillDiscountAmount) OVER () AS ReportDiscountAmount',
            'SUM(COALESCE(h.BillTotalAmount, h.BillAmount)) OVER () AS ReportNetAmount',
            'LEFT JOIN guests AS g ON g.id = h.GuestId',
            'COALESCE(h.NumOfRoom, h.RoomNo)',
            "WHEN sb.RegisterID2 IS NULL AND sb.RentalRoomId2 IS NOT NULL THEN h.RoomNo",
            'sb.Guest',
            'THEN h.BillUsername',
            'CAST(h.BillShift AS CHAR)',
            "h.RoomNo REGEXP '^[0-9]+$'",
            "sb.ServiceId REGEXP '^[0-9]+$'",
            "'options_source' => 'report-shifts'",
            "'options_source' => 'service-departments'",
            "['value' => 'ServiceId', 'label' => 'Dịch vụ']",
            "['value' => 'RefId', 'label' => 'Mã tham chiếu']",
            'THEN h.Ma',
            'END DESC',
            'END ASC',
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $migration);
        }

        $this->assertStringNotContainsString("['value' => 'Amount'", $migration);
        $this->assertStringNotContainsString("['value' => 'Username'", $migration);
        $this->assertStringNotContainsString("CAST(NULLIF(h.GuestId, '') AS UNSIGNED)", $migration);
        $this->assertStringNotContainsString('JSON_ARRAYAGG', $migration);
    }

    public function test_compatibility_migrations_are_kept_with_their_report_codes(): void
    {
        $laundryMigration = file_get_contents(database_path('migrations/2026_09_10_310000_fix_report_json_aggregation_compatibility.php'));
        $companyMigration = file_get_contents(database_path('migrations/2026_09_10_311000_fix_company_debt_json_aggregation_compatibility.php'));

        $this->assertStringContainsString('rpt_laundry_invoices', $laundryMigration);
        $this->assertStringNotContainsString('rpt_breakage_invoices', $laundryMigration);
        $this->assertStringNotContainsString('rpt_minibar_invoices', $laundryMigration);
        $this->assertStringContainsString('rpt_company_debt', $companyMigration);
    }
}
