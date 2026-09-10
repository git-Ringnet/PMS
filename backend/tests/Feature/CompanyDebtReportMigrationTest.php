<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class CompanyDebtReportMigrationTest extends TestCase
{
    public function test_migration_contains_the_company_debt_runtime_contract_without_running_it(): void
    {
        $path = dirname(__DIR__, 2).'/database/migrations/2026_09_10_260000_create_company_debt_report.php';
        $sql = file_get_contents($path);

        $this->assertIsString($sql);
        $this->assertStringContainsString('CREATE PROCEDURE rpt_company_debt', $sql);
        $this->assertStringContainsString('SELECT DISTINCT', $sql);
        $this->assertStringContainsString('si.legacy_id = sb.InvoiceId', $sql);
        $this->assertStringContainsString('si.legacy_payment_id = p.payment_id', $sql);
        $this->assertStringContainsString('INNER JOIN companies AS c', $sql);
        $this->assertStringContainsString('INNER JOIN bookings AS b', $sql);
        $this->assertStringContainsString("COALESCE(si.legacy_rental_room_id, CAST(si.legacy_booking_id AS CHAR))", $sql);
        $this->assertStringContainsString('br_status.status = 2', $sql);
        $this->assertStringContainsString("p.payment_method_id = 'AC'", $sql);
        $this->assertStringContainsString("COALESCE(p.pack2, '') = ''", $sql);
        $this->assertStringContainsString('p.edit_flag = 0', $sql);
        $this->assertStringContainsString('s2.edit_flag = 0', $sql);
        $this->assertStringContainsString('s2.legacy_payment_table_id = p2.legacy_id', $sql);
        $this->assertStringContainsString('COALESCE(p.legacy_id, p.id) AS PaymentId', $sql);
        $this->assertStringContainsString("'SettlementId', COALESCE(s.legacy_id, s.id)", $sql);
        $this->assertStringContainsString('si.invoice_date BETWEEN p_from_date AND p_to_date', $sql);
        $this->assertStringContainsString("CASE WHEN paid.PaidAmount IS NOT NULL THEN '☑' ELSE '' END AS PaidMarker", $sql);
        $this->assertStringContainsString('si.amount - COALESCE(paid.PaidAmount, 0) AS RemainingAmount', $sql);
        $this->assertStringContainsString("CONCAT('[', GROUP_CONCAT(JSON_OBJECT(", $sql);
        $this->assertStringNotContainsString('JSON_ARRAYAGG', $sql);
        $this->assertStringContainsString("'p_show_settlements'", $sql);
        $this->assertStringContainsString("company_debt_reference.php'))->definition()", $sql);
    }
}
