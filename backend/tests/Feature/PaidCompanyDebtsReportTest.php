<?php

namespace Tests\Feature;

use App\Services\Reports\PaidCompanyDebtsDataAdapter;
use Tests\TestCase;

class PaidCompanyDebtsReportTest extends TestCase
{
    private function template(): object
    {
        return require database_path('report_templates/paid_company_debts_reference.php');
    }

    public function test_migration_file_contains_valid_procedure_and_configuration(): void
    {
        $path = database_path('migrations/2026_09_16_190000_create_paid_company_debts_report.php');
        $this->assertFileExists($path);

        $sql = file_get_contents($path);
        $this->assertStringContainsString('CREATE PROCEDURE rpt_paid_company_debts', $sql);
        $this->assertStringContainsString('payments AS p', $sql);
        $this->assertStringContainsString('payment_debt_settlements AS s', $sql);
        $this->assertStringContainsString("p.payment_method_id = 'AC'", $sql);
        $this->assertStringContainsString('p_view_type', $sql);
        $this->assertStringContainsString('p_group_by_company', $sql);
        $this->assertStringContainsString('PAID_COMPANY_DEBTS_REFERENCE', $sql);
    }

    public function test_paid_company_debts_template_definition_and_blocks(): void
    {
        $template = $this->template();
        $definition = $template->definition();
        $blocks = $template->blocks();
        $tableBlock = $blocks['detail'][0];
        $pmSummaryBlock = $blocks['detail'][2];

        $this->assertSame('PAID_COMPANY_DEBTS_REFERENCE', $definition['report']);
        $this->assertSame('Báo cáo công nợ đã thanh toán', $definition['name']);
        $this->assertSame('A4', $definition['page_size']);
        $this->assertTrue($tableBlock['hasTwoTierHeader']);
        $this->assertSame('Thông Tin Công Nợ', $tableBlock['topHeader'][0]['label']);
        $this->assertSame('Thông Tin Thanh Toán', $tableBlock['topHeader'][1]['label']);

        $this->assertCount(16, $tableBlock['columns']);
        $this->assertSame('Mã Đăng Ký', $tableBlock['columns'][0]['header']);
        $this->assertSame('Phòng', $tableBlock['columns'][1]['header']);
        $this->assertSame('Tên Đăng Ký', $tableBlock['columns'][2]['header']);
        $this->assertSame('Ngày Đến', $tableBlock['columns'][3]['header']);
        $this->assertSame('Ngày Đi', $tableBlock['columns'][4]['header']);
        $this->assertSame('Tổng Tiền', $tableBlock['columns'][5]['header']);
        $this->assertSame('HTTT', $tableBlock['columns'][6]['header']);
        $this->assertSame('Ngày Công Nợ', $tableBlock['columns'][7]['header']);
        $this->assertSame('Nhân Viên', $tableBlock['columns'][8]['header']);
        $this->assertSame('Đã trả', $tableBlock['columns'][9]['header']);
        $this->assertSame('Ngày Thanh Toán', $tableBlock['columns'][10]['header']);
        $this->assertSame('Tổng Tiền Thanh Toán', $tableBlock['columns'][11]['header']);
        $this->assertSame('HTTT', $tableBlock['columns'][12]['header']);
        $this->assertSame('Còn Lại', $tableBlock['columns'][13]['header']);
        $this->assertSame('Nhân Viên', $tableBlock['columns'][14]['header']);
        $this->assertSame('Ghi Chú', $tableBlock['columns'][15]['header']);

        $this->assertSame('DateGroup', $tableBlock['grouping'][0]['field']);
        $this->assertSame('CompanyName', $tableBlock['grouping'][1]['field']);

        $this->assertSame('payment_method_summary', $pmSummaryBlock['dataSource']);
        $this->assertSame('HTTT', $pmSummaryBlock['columns'][0]['header']);
        $this->assertSame('Tổng Tiền', $pmSummaryBlock['columns'][1]['header']);
    }

    public function test_data_adapter_computes_payment_method_summary_and_totals(): void
    {
        $adapter = new PaidCompanyDebtsDataAdapter();
        $adapted = $adapter->adapt([
            'rows' => [
                [
                    'DebtRowKey' => 1,
                    'AmountCN' => 20495000,
                    'AmountTT' => 20495000,
                    'PaymentMethodTT' => 'BT',
                ],
                [
                    'DebtRowKey' => 2,
                    'AmountCN' => 3600000,
                    'AmountTT' => 3600000,
                    'PaymentMethodTT' => 'BT',
                ],
            ],
        ]);

        $this->assertArrayHasKey('payment_method_summary', $adapted);
        $this->assertCount(1, $adapted['payment_method_summary']);
        $this->assertSame('BT', $adapted['payment_method_summary'][0]['Method']);
        $this->assertEquals(24095000, $adapted['payment_method_summary'][0]['Amount']);

        $this->assertEquals(24095000, $adapted['totals']['DebtAmount']);
        $this->assertEquals(24095000, $adapted['totals']['PaidAmount']);
        $this->assertEquals(0, $adapted['totals']['RemainingAmount']);
    }

    public function test_paid_company_debts_template_renders_properly(): void
    {
        $rendered = $this->template()->render([
            'hotel' => ['address' => '66 Hàn Mặc Tử, Quy Nhơn', 'logo' => '<div>Logo</div>'],
            'report' => ['generated_by' => 'Admin', 'generated_at' => '13/07/2026'],
            'parameters' => ['p_from_date' => '05/07/2026', 'p_to_date' => '06/07/2026'],
            'rows' => [
                [
                    'DebtRowKey' => 48,
                    'BookingId' => '48',
                    'Room' => '',
                    'BookingName' => 'Anh Dũng',
                    'ArrivalDate' => '02/07/2026',
                    'DepartureDate' => '05/07/2026',
                    'AmountCN' => 20495000,
                    'PaymentMethodCN' => 'AC',
                    'DateCN' => '05/07/2026',
                    'UserCN' => 'Võ Anh Khánh',
                    'IsPaidMarker' => '☑',
                    'DateTT' => '05/07/2026',
                    'AmountTT' => 20495000,
                    'PaymentMethodTT' => 'BT',
                    'RemainAmount' => 0,
                    'UserTT' => 'Võ Anh Khánh',
                    'Description' => 'NGUYỄN VĂN DŨNG (NGUYỄN BÍ THƯ TỈNH TRẢ NỢ)',
                    'Company' => 'Nguyễn bí thư thành ủy Quy Nhơn',
                    'CompanyName' => 'Nguyễn bí thư thành ủy Quy Nhơn',
                    'DateGroup' => '05/07/2026',
                    'CompanyTotalDebt' => 20495000,
                    'CompanyTotalPaid' => 20495000,
                    'CompanyRemaining' => 0,
                    'DateTotalDebt' => 20495000,
                    'DateTotalPaid' => 20495000,
                    'DateRemaining' => 0,
                ],
            ],
            'payment_method_summary' => [
                ['Method' => 'BT', 'Amount' => 20495000],
            ],
            'totals' => [
                'DebtAmount' => 20495000,
                'PaidAmount' => 20495000,
                'RemainingAmount' => 0,
            ],
        ]);

        $this->assertStringContainsString('BÁO CÁO CÔNG NỢ ĐÃ THANH TOÁN', $rendered);
        $this->assertStringContainsString('Thông Tin Công Nợ', $rendered);
        $this->assertStringContainsString('Thông Tin Thanh Toán', $rendered);
        $this->assertStringContainsString('05/07/2026', $rendered);
        $this->assertStringContainsString('Nguyễn bí thư thành ủy Quy Nhơn', $rendered);
        $this->assertStringContainsString('20.495.000', $rendered);
        $this->assertStringContainsString('BT', $rendered);
        $this->assertStringContainsString('BẢNG KÊ HÌNH THỨC THANH TOÁN', $rendered);
        $this->assertStringNotContainsString('{{', $rendered);
    }

    public function test_dataset_enricher_adapts_paid_company_debts(): void
    {
        $enricher = app(\App\Services\Reports\ReportDatasetEnricher::class);
        $definition = new \App\Models\ReportDefinition(['code' => 'PAID_COMPANY_DEBTS']);

        $result = [
            'rows' => [
                [
                    'DebtRowKey' => 1,
                    'AmountCN' => 1000000,
                    'AmountTT' => 1000000,
                    'PaymentMethodTT' => 'TM',
                    'UserCN' => 'admin',
                    'UserTT' => 'admin',
                ],
            ],
        ];

        $enriched = $enricher->enrich($definition, $result);
        $this->assertArrayHasKey('payment_method_summary', $enriched);
        $this->assertArrayHasKey('totals', $enriched);
        $this->assertSame('TM', $enriched['payment_method_summary'][0]['Method']);
        $this->assertEquals(1000000, $enriched['totals']['DebtAmount']);
    }
}
