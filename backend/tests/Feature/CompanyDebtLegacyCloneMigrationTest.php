<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class CompanyDebtLegacyCloneMigrationTest extends TestCase
{
    public function test_v2_migration_isolatedly_registers_the_legacy_clone_contract(): void
    {
        $path = dirname(__DIR__, 2).'/database/migrations/2026_11_20_110000_align_company_debt_reports_with_legacy_contract.php';
        $migration = file_get_contents($path);

        $this->assertIsString($migration);
        $this->assertStringContainsString('rpt_company_debt_legacy_dtx_v2', $migration);
        $this->assertStringContainsString('rpt_paid_company_debts_legacy_army_v2', $migration);
        $this->assertStringContainsString('p.invoice_id IS NOT NULL AND p.invoice_id = si.id', $migration);
        $this->assertStringContainsString('p.invoice_id IS NULL', $migration);
        $this->assertStringContainsString('EXISTS (', $migration);
        $this->assertStringContainsString('FROM service_bills AS sb', $migration);
        $this->assertStringContainsString("'p_is_fb'", $migration);
        $this->assertStringContainsString('IN p_debt_status VARCHAR(20)', $migration);
        $this->assertStringContainsString('IN p_all TINYINT', $migration);
        $this->assertStringContainsString("COALESCE(p_all, 0) = 1 OR si.invoice_date BETWEEN p_from_date AND p_to_date", $migration);
        $this->assertStringContainsString("p_debt_status = 'unpaid'", $migration);
        $this->assertStringContainsString("p_debt_status = 'paid'", $migration);
        $this->assertStringContainsString("'label' => 'Xem tất cả', 'control' => 'checkbox'", $migration);
        $this->assertStringContainsString("'label' => 'Ngày', 'control' => 'date-range'", $migration);
        $this->assertStringContainsString("'control' => 'select', 'default' => 1", $migration);
        $this->assertStringContainsString("'label' => 'Xem theo ngày trả công nợ'", $migration);
        $this->assertStringContainsString("'page_orientation'", $migration);
        $this->assertStringContainsString('is_default', $migration);
        $this->assertStringContainsString("'PaymentMethod', COALESCE(NULLIF(settlement_methods.name, ''), s.payment_method_id, '')", $migration);
    }

    public function test_v3_migration_keeps_the_previous_template_and_registers_detail_rows(): void
    {
        $path = dirname(__DIR__, 2).'/database/migrations/2026_11_20_130000_clone_company_debt_legacy_detail_layout.php';
        $migration = file_get_contents($path);

        $this->assertIsString($migration);
        $this->assertStringContainsString('COMPANY_DEBT_LEGACY_DTX_V3', $migration);
        $this->assertStringContainsString('COMPANY_DEBT_LEGACY_DTX_V2', $migration);
        $this->assertStringContainsString("'PaymentMethod', COALESCE(NULLIF(settlement_methods.name, ''), s.payment_method_id, '')", $migration);
        $this->assertStringContainsString("'p_show_settlements' => 1", $migration);
    }
}
