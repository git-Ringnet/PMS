<?php

namespace Tests\Unit\Reports;

use App\Services\TemplateRendererService;
use PHPUnit\Framework\TestCase;

class CompanyDebtTemplateTest extends TestCase
{
    private object $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = require dirname(__DIR__, 2).'/../database/report_templates/company_debt_reference.php';
    }

    public function test_definition_exposes_the_presentation_contract_and_ten_columns_in_order(): void
    {
        $definition = $this->provider->definition();
        $headers = array_column($definition['columns'], 'header');

        $this->assertSame('COMPANY_DEBT', $definition['code']);
        $this->assertSame('COMPANY_DEBT_REFERENCE', $definition['report']);
        $this->assertSame('portrait', $definition['page_orientation']);
        $this->assertSame(8, $definition['margin_top']);
        $this->assertSame(5, $definition['margin_right']);
        $this->assertSame(8, $definition['margin_bottom']);
        $this->assertSame(5, $definition['margin_left']);
        $this->assertSame($this->provider->html(), $definition['content_html']);
        $this->assertSame($this->provider->blocks(), $definition['content_json']);
        $this->assertSame($this->provider->css(), $definition['css']);
        $this->assertSame([
            'Mã ĐK', 'Phòng', 'Công Ty', 'Ngày Đến', 'Ngày Đi', 'Ghi Chú',
            'Tổng tiền', 'Ngày', 'Thu Ngân', 'Đã trả',
        ], $headers);
        $this->assertSame('DateGroup', $definition['grouping']['levels'][0]['field']);
        $this->assertSame('CompanyId', $definition['grouping']['levels'][1]['field']);
        $this->assertSame('number', $definition['data_contract']['rows']['TotalAmount']);
        $this->assertSame('string|int', $definition['data_contract']['rows']['CompanyId']);
        $this->assertSame('string', $definition['data_contract']['rows']['PaidMarker']);
    }

    public function test_populated_render_groups_rows_renders_summary_aggregates_and_escapes_raw_text(): void
    {
        $data = $this->dataSet();
        $rendered = $this->provider->render($data, new TemplateRendererService());

        $this->assertStringContainsString('<img src="/trusted/logo.png" alt="Logo">', $rendered);
        $this->assertStringContainsString('BÁO CÁO CÔNG NỢ CÔNG TY', $rendered);
        $this->assertStringContainsString('Công nợ tối đa: 5.000.000', $rendered);
        $this->assertStringContainsString('<td colspan="2" class="money company-group-total-value">200.000</td>', $rendered);
        $this->assertStringContainsString('<td colspan="2" class="money company-group-total-value">100.000</td>', $rendered);
        $this->assertStringContainsString('<td class="money">300.000</td><td class="money">100.000</td><td class="money">200.000</td>', $rendered);
        $this->assertStringContainsString('<td class="total-label">Tổng</td><td class="money">300.000</td><td class="money">100.000</td><td class="money">200.000</td>', $rendered);

        $this->assertStringContainsString('&lt;script&gt;alert(&apos;x&apos;)&lt;/script&gt;', $rendered);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $rendered);
        $this->assertStringNotContainsString('<script>alert', $rendered);
        $this->assertStringNotContainsString('<img src=x onerror=alert', $rendered);
        $this->assertStringNotContainsString('Injected value', $rendered);
        $this->assertStringContainsString('&#123;parameters.injected&#125;', $rendered);

        $datePosition = strpos($rendered, 'Ngày: 2026-09-01');
        $companyPosition = strpos($rendered, 'Công ty: ACME');
        $secondDatePosition = strpos($rendered, 'Ngày: 2026-09-02');
        $this->assertIsInt($datePosition);
        $this->assertIsInt($companyPosition);
        $this->assertIsInt($secondDatePosition);
        $this->assertLessThan($companyPosition, $datePosition);
        $this->assertLessThan($secondDatePosition, $companyPosition);
    }

    public function test_empty_render_keeps_static_layout_without_detail_or_summary_rows(): void
    {
        $rendered = $this->provider->render([
            'hotel' => ['logo' => '<img src="/trusted/logo.png" alt="Logo">', 'address' => 'Address'],
            'report' => ['generated_by' => 'Staff', 'generated_at' => '2026-09-09'],
            'parameters' => ['p_from_date' => '2026-09-01', 'p_to_date' => '2026-09-01'],
            'rows' => [],
            'company_summary' => [],
            'totals' => ['TotalAmount' => 0, 'PaidAmount' => 0, 'RemainingAmount' => 0],
        ], new TemplateRendererService());

        $this->assertStringContainsString('Bảng Kê Tổng Hợp Công Nợ', $rendered);
        $this->assertStringContainsString('<tbody></tbody>', $rendered);
        $this->assertStringContainsString('<td class="money">0</td><td class="money">0</td><td class="money">0</td>', $rendered);
        $this->assertStringContainsString('Staff</span><span>FOM</span><span>DGM</span><span>GM', $rendered);
        $this->assertStringNotContainsString('pms-detail-row', $rendered);
        $this->assertStringNotContainsString('{{', $rendered);
    }

    public function test_blocks_and_html_keep_the_same_ten_column_order_widths_and_summary_table(): void
    {
        $definitionHeaders = array_column($this->provider->definition()['columns'], 'header');
        $blockHeaders = array_column($this->provider->blocks()['detail'][0]['columns'], 'header');
        preg_match('/<thead><tr>(.*?)<\/tr><\/thead>/s', $this->provider->html(), $matches);
        preg_match_all('/<th>(.*?)<\/th>/s', $matches[1], $headerMatches);

        $this->assertSame($definitionHeaders, $blockHeaders);
        $this->assertCount(10, $headerMatches[1]);
        $this->assertSame($definitionHeaders, $headerMatches[1]);
        preg_match('/<colgroup>(.*?)<\/colgroup>/s', $this->provider->html(), $colgroup);
        preg_match_all('/<col style="width:([^\"]+)">/', $colgroup[1], $widthMatches);
        $blockWidths = array_column($this->provider->blocks()['detail'][0]['columns'], 'width');
        $this->assertSame($blockWidths, $widthMatches[1]);
        $this->assertEquals(100, array_sum(array_map('floatval', $blockWidths)));
        $this->assertStringContainsString('row.MaxDebt|number', $this->provider->blocks()['detail'][0]['groups'][1]['label']);
        $this->assertNotEmpty($this->provider->blocks()['detail'][0]['customRows']);
        $footerBlocks = array_column($this->provider->blocks()['footer'], null, 'id');
        $this->assertStringContainsString('data-visible-by="parameters.p_show_settlements"', $footerBlocks['company_debt_settlement_details']['content']);
        $this->assertStringContainsString('data-source="settlement_details"', $footerBlocks['company_debt_settlement_details']['content']);
        $this->assertStringContainsString('Bảng Kê Tổng Hợp Công Nợ', $footerBlocks['company_debt_summary_title']['content']);
        $this->assertNotEmpty($footerBlocks['company_debt_summary_table']['customRows']);
        $this->assertStringContainsString('data-source="company_summary"', $this->provider->html());
        $this->assertStringContainsString('{{totals.RemainingAmount|number}}', $this->provider->html());
        $this->assertStringContainsString('#company_debt_detail_table table', $this->provider->css());
        $this->assertStringContainsString('#company_debt_summary_table table', $this->provider->css());
        $this->assertStringContainsString('#e2e8f0', $this->provider->css());
        $this->assertStringContainsString('#cbd5e1', $this->provider->css());
    }

    public function test_settlement_details_follow_the_explicit_visibility_parameter(): void
    {
        $data = $this->dataSet();
        $data['settlement_details'] = [[
            'BookingId' => 'B-001',
            'CompanyName' => 'ACME',
            'PaymentId' => 'PAY-1',
            'SettlementId' => 'SET-1',
            'Date' => '2026-09-02',
            'Reference' => 'REF-1',
            'Amount' => 100000,
        ]];

        $hidden = $this->provider->render($data, new TemplateRendererService());
        $this->assertStringNotContainsString('PAY-1', $hidden);
        $this->assertStringNotContainsString('Mã giải trừ', $hidden);

        $data['parameters']['p_show_settlements'] = true;
        $shown = $this->provider->render($data, new TemplateRendererService());
        $this->assertStringContainsString('Mã giải trừ', $shown);
        $this->assertStringContainsString('<td>PAY-1</td><td>SET-1</td>', $shown);
        $this->assertStringContainsString('<td class="money">100.000</td>', $shown);
    }

    private function dataSet(): array
    {
        return [
            'hotel' => [
                'logo' => '<img src="/trusted/logo.png" alt="Logo">',
                'address' => 'A & B <address>',
            ],
            'report' => ['generated_by' => 'Demo <staff>', 'generated_at' => '2026-09-09'],
            'parameters' => [
                'p_from_date' => '2026-09-01',
                'p_to_date' => '2026-09-02',
                'injected' => 'Injected value',
            ],
            'rows' => [
                [
                    'BookingId' => 'B-001', 'Room' => '101', 'CompanyId' => 10,
                    'CompanyName' => 'ACME <script>alert(\'x\')</script>{{parameters.injected}}',
                    'ArrivalDate' => '2026-08-30', 'DepartureDate' => '2026-09-02',
                    'Description' => '<img src=x onerror=alert(1)>{{report.generated_by}}',
                    'TotalAmount' => 125000, 'Date' => '2026-09-01', 'DateGroup' => '2026-09-01',
                    'Username' => 'cashier', 'PaidMarker' => '☑', 'MaxDebt' => 5000000,
                ],
                [
                    'BookingId' => 'B-002', 'Room' => '102', 'CompanyId' => 10,
                    'CompanyName' => 'ACME', 'ArrivalDate' => '2026-09-01', 'DepartureDate' => '2026-09-03',
                    'Description' => 'Second supplied row', 'TotalAmount' => 75000, 'Date' => '2026-09-01',
                    'DateGroup' => '2026-09-01', 'Username' => 'cashier', 'PaidMarker' => '', 'MaxDebt' => 5000000,
                ],
                [
                    'BookingId' => 'B-003', 'Room' => '201', 'CompanyId' => 20,
                    'CompanyName' => 'BETA', 'ArrivalDate' => '2026-09-02', 'DepartureDate' => '2026-09-04',
                    'Description' => 'Third supplied row', 'TotalAmount' => 100000, 'Date' => '2026-09-02',
                    'DateGroup' => '2026-09-02', 'Username' => 'cashier', 'PaidMarker' => '☑', 'MaxDebt' => 7000000,
                ],
            ],
            'company_summary' => [
                ['CompanyName' => 'ACME', 'TotalAmount' => 200000, 'PaidAmount' => 100000, 'RemainingAmount' => 100000],
                ['CompanyName' => 'BETA', 'TotalAmount' => 100000, 'PaidAmount' => 0, 'RemainingAmount' => 100000],
            ],
            'totals' => ['TotalAmount' => 300000, 'PaidAmount' => 100000, 'RemainingAmount' => 200000],
        ];
    }
}
