<?php

namespace Tests\Unit\Reports;

use Tests\TestCase;

class HousekeepingInvoiceLegacyContractRepairTest extends TestCase
{
    public function test_repair_is_scoped_to_housekeeping_reports_and_preserves_legacy_contracts(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_11_23_170000_repair_housekeeping_invoice_legacy_contract.php'));

        foreach ([
            'rpt_laundry_invoices', 'rpt_breakage_invoices', 'rpt_minibar_invoices',
            'rpt_laundry_invoices_by_product', 'rpt_breakage_invoices_by_product', 'rpt_minibar_invoices_by_product',
            'rpt_laundry_free_invoices', 'rpt_breakage_free_invoices', 'rpt_minibar_free_invoices',
            "pay.PaymentMethod = 'CL'", "sb.RegisterID2 IS NULL AND sb.RentalRoomId2 IS NOT NULL",
            "UPPER(COALESCE(NULLIF(p_view_type, ''), 'ALL')) = 'ALL'", "sb.Edit = 1 AND h.BillEdit = 1",
            'h.BillEdit = 0', 'HAVING SUM(d.Quantity) > 0', 'BREAKAGE_INVOICES_BY_PRODUCT_STANDARD',
            'BREAKAGE_FREE_INVOICES_STANDARD',
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $migration);
        }

        $this->assertStringNotContainsString('sb.Amount <> 0', $migration);
        $this->assertStringNotContainsString('ReportsPage.vue', $migration);
        $this->assertStringNotContainsString('HousekeepingInvoiceFilters.vue', $migration);
        $this->assertStringNotContainsString('mysql_hkt', $migration);
    }
}
