<?php

namespace Tests\Unit\Reports;

use Tests\TestCase;

class RevenueReportsMigrationTest extends TestCase
{
    public function test_rows_150_151_and_158_are_registered_with_legacy_procedures_and_templates(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_09_22_100000_create_revenue_reports_150_151_158.php'));

        foreach ([
            ['REVENUE_ARMY', 'rpt_revenue_army', 'REVENUE_ARMY_REFERENCE'],
            ['REVENUE_BY_DEPARTURE_DATE', 'rpt_revenue_by_departure_date', 'REVENUE_BY_DEPARTURE_DATE_REFERENCE'],
            ['RECEPTION_CASHIER_SHIFT', 'rpt_reception_cashier_shift', 'RECEPTION_CASHIER_SHIFT_REFERENCE'],
        ] as [$report, $procedure, $template]) {
            $this->assertStringContainsString("'report' => '{$report}'", $migration);
            $this->assertStringContainsString("'procedure' => '{$procedure}'", $migration);
            $this->assertStringContainsString("'template' => '{$template}'", $migration);
        }

        $this->assertStringContainsString("'menu_locations' => json_encode(['frontdesk', 'reservation']", $migration);
        $this->assertStringContainsString('schemaCapabilities', $migration);
    }

    public function test_branch_compatibility_keeps_legacy_columns_optional(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_09_22_100000_create_revenue_reports_150_151_158.php'));

        foreach ([
            'sales_invoices_booking_id',
            'sales_invoices_department',
            'sales_invoices_company_id',
            'payments_invoice_id',
            '__INVOICE_BOOKING_ID__',
            '__PAYMENT_INVOICE_ID__',
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $migration);
        }
    }

    public function test_row_164_is_visible_and_temporarily_exempted_only_for_its_report_route(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_09_22_110000_enable_expected_room_revenue_report.php'));
        $router = file_get_contents(base_path('../frontend/src/router/index.js'));

        $this->assertStringContainsString("'EXPECTED_ROOM_REVENUE_NIGHT_AUDIT'", $migration);
        $this->assertStringContainsString("'show_in_menu' => true", $migration);
        $this->assertStringContainsString("to.query.report === 'EXPECTED_ROOM_REVENUE_NIGHT_AUDIT'", $router);
    }

    public function test_reception_cashier_template_has_city_ledger_dataset(): void
    {
        $template = file_get_contents(database_path('report_templates/reception_cashier_shift_reference.php'));

        $this->assertStringContainsString("'dataSource' => 'city_ledger_rows'", $template);
        $this->assertStringContainsString('PaidAmount', $template);
        $this->assertStringContainsString('BalanceAmount', $template);
    }
}
