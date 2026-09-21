<?php

namespace Tests\Feature;

use Tests\TestCase;

class CancelledInvoicesPaymentsReportTest extends TestCase
{
    private function template(): object
    {
        return require database_path('report_templates/cancelled_invoices_payments_reference.php');
    }

    public function test_migration_keeps_the_legacy_modes_and_current_payment_mapping(): void
    {
        $path = database_path('migrations/2026_09_18_110000_create_cancelled_invoices_payments_report.php');
        $this->assertFileExists($path);
        $sql = file_get_contents($path);

        $this->assertStringContainsString('CREATE PROCEDURE rpt_cancelled_invoices_payments', $sql);
        $this->assertSame(9, substr_count($sql, 'IN p_'));
        $this->assertStringContainsString('service_bills AS duong', $sql);
        $this->assertStringContainsString('payments AS duong', $sql);
        $this->assertStringContainsString('duong.reversal_ref = am.id', $sql);
        $this->assertStringContainsString('duong.reversal_ref = am.legacy_id', $sql);
        $this->assertStringContainsString('ABS(COALESCE(am.TotalAmount0, am.Amount, 0))', $sql);
        $this->assertStringContainsString('ABS(COALESCE(am.amount, 0))', $sql);
        $this->assertDoesNotMatchRegularExpression('/FROM payments AS duong.*?duong\\.pack1/is', $sql);
        $this->assertStringContainsString('CANCELLED_INVOICES_PAYMENTS_REFERENCE', $sql);
    }

    public function test_template_has_the_legacy_two_tier_layout_and_two_group_levels(): void
    {
        $definition = $this->template()->definition();
        $table = $this->template()->blocks()['detail'][0];

        $this->assertSame('A4', $definition['page_size']);
        $this->assertSame('landscape', $definition['page_orientation']);
        $this->assertSame(8, $definition['margin_top']);
        $this->assertSame(6, $definition['margin_right']);
        $this->assertSame(8, $definition['margin_bottom']);
        $this->assertSame(6, $definition['margin_left']);
        $this->assertCount(12, $table['columns']);
        $this->assertTrue($table['hasTwoTierHeader']);
        $this->assertCount(2, $table['grouping']);
        $this->assertSame('InvoiceDateFormatted', $table['grouping'][0]['field']);
        $this->assertSame('DepartmentId', $table['grouping'][1]['field']);
        $this->assertSame('4%', $definition['columns'][0]['width']);
        $this->assertSame('19%', $definition['columns'][11]['width']);
        $this->assertFileExists(database_path('migrations/2026_09_18_150000_sync_all_four_reports_exact_design.php'));
    }

    public function test_template_renders_grouped_rows_and_amount_totals(): void
    {
        $rendered = $this->template()->render([
            'hotel' => ['name' => 'PMS Hotel', 'address' => 'Nha Trang', 'logo' => ''],
            'report' => ['generated_by' => 'Admin'],
            'parameters' => ['p_from_date' => '01-09-2026', 'p_to_date' => '02-09-2026', 'p_mode' => 'BILL', 'p_mode_label' => 'BÁO CÁO HỦY HÓA ĐƠN'],
            'rows' => [
                ['Index' => 1, 'Room1' => 'BK001/101', 'Service' => 'Room', 'CreatedDate' => '01/09/2026', 'CreatedHour' => '10:00', 'AmountAm' => 100000, 'CreatedUser' => 'AM', 'Date' => '01/09/2026', 'OpenTime' => '11:00', 'AmountDuong' => 110000, 'Username' => 'DUONG', 'Description' => 'Khách hủy', 'InvoiceDateFormatted' => '01/09/2026', 'DepartmentId' => 'FO', 'DepartmentName' => 'Lễ tân'],
                ['Index' => 2, 'Room1' => 'BK002/201', 'Service' => 'Minibar', 'CreatedDate' => '02/09/2026', 'CreatedHour' => '12:00', 'AmountAm' => 200000, 'CreatedUser' => 'AM2', 'Date' => '02/09/2026', 'OpenTime' => '13:00', 'AmountDuong' => 220000, 'Username' => 'DUONG2', 'Description' => 'Nhập sai', 'InvoiceDateFormatted' => '02/09/2026', 'DepartmentId' => 'FB', 'DepartmentName' => 'Nhà hàng'],
            ],
        ]);

        $this->assertStringContainsString('BÁO CÁO HỦY HÓA ĐƠN', $rendered);
        $this->assertStringContainsString('01/09/2026', $rendered);
        $this->assertStringContainsString('Lễ tân', $rendered);
        $this->assertStringContainsString('BK001/101', $rendered);
        $this->assertStringContainsString('100.000', $rendered);
        $this->assertStringContainsString('Tổng Giai Đoạn', $rendered);
        $this->assertStringContainsString('background: #d9deea', $rendered);
        $this->assertStringContainsString('300.000', $rendered);
        $this->assertStringContainsString('330.000', $rendered);
    }
}
