<?php

namespace Tests\Unit\Reports;

use App\Services\TemplateRendererService;
use Tests\TestCase;

class LaundryInvoicesTemplateTest extends TestCase
{
    private function template(): object
    {
        return require database_path('report_templates/laundry_invoices_reference.php');
    }

    public function test_reference_is_pure_and_exposes_portrait_metadata_and_contract_blocks(): void
    {
        $path = database_path('report_templates/laundry_invoices_reference.php');
        $source = file_get_contents($path);
        $template = $this->template();
        $definition = $template->definition();

        $this->assertIsString($source);
        $this->assertStringNotContainsString('DB::', $source);
        $this->assertStringNotContainsString('function apply', $source);
        $this->assertSame('LAUNDRY_INVOICES_REFERENCE', $definition['report']);
        $this->assertSame('A4', $definition['page_size']);
        $this->assertSame('portrait', $definition['page_orientation']);
        $this->assertSame(13, count($template->blocks()['detail'][0]['columns']));
        $this->assertSame(
            ['STT', 'Mã ĐK', 'Phòng', 'Tên Khách', 'Mô Tả', 'Sản phẩm', 'Tổng tiền', 'Giảm Giá', 'Thành Tiền', 'Mã TT', 'Ghi Chú', 'Người Dùng', 'Ca'],
            array_column($template->blocks()['detail'][0]['columns'], 'header')
        );
        $this->assertSame(100.0, array_sum(array_map(static fn (array $column): float => (float) rtrim($column['width'], '%'), $template->blocks()['detail'][0]['columns'])));
        $this->assertSame(
            ['Product', 'Quantity', 'TotalAmount'],
            array_map(
                static fn (array $column): string => substr($column['value'], 5),
                $template->blocks()['detail'][2]['columns']
            )
        );
        $totalsRow = $template->blocks()['detail'][0]['customRows'][0];
        $groups = $template->blocks()['detail'][0]['groups'];
        $this->assertSame('DateGroup', $groups[0]['field']);
        $this->assertSame('date-group', $groups[0]['className']);
        $this->assertSame('ASC', $groups[0]['sort']);
        $this->assertSame('table', $totalsRow['scope']);
        $this->assertSame(['totals.TotalAmount', 'totals.DiscountAmount', 'totals.NetAmount'], array_values(array_filter(array_column($totalsRow['cells'], 'binding'))));
        $this->assertStringContainsString('<col style="width:60%"><col style="width:20%"><col style="width:20%">', $template->html());
        $this->assertStringContainsString('text-align:left!important', $template->css());
        $this->assertStringContainsString('#laundry_invoices_table table', $template->css());
        $this->assertStringContainsString('#laundry_product_summary_table table', $template->css());
        $this->assertStringContainsString('#e2e8f0', $template->css());
        $this->assertStringContainsString('#cbd5e1', $template->css());
    }

    public function test_renderer_groups_by_date_renders_totals_and_product_summary_without_placeholders(): void
    {
        $template = $this->template();
        $rendered = $template->render([
            'hotel' => ['address' => 'Synthetic Hotel', 'logo' => '<img src="/trusted-logo.svg" alt="Logo">'],
            'report' => ['generated_by' => 'Synthetic User', 'generated_at' => '15-08-2026'],
            'parameters' => ['p_from_date' => '15-08-2026', 'p_to_date' => '15-08-2026'],
            'rows' => [
                ['STT' => 1, 'BookingId' => 'BK-1', 'Room' => '101', 'Guest' => 'Guest A', 'DescriptionServive' => 'Giặt ủi', 'Product' => 'Áo sơ mi', 'TotalAmount' => 120000, 'DiscountAmount' => 10000, 'NetAmount' => 110000, 'PaymentID' => 'PM-1', 'BillNote' => 'Đã nhận', 'Username' => 'user1', 'Ca' => 'S', 'DateGroup' => '15-08-2026'],
                ['STT' => 2, 'BookingId' => 'BK-2', 'Room' => '202', 'Guest' => 'Guest B', 'DescriptionServive' => 'Giặt khô', 'Product' => 'Quần âu', 'TotalAmount' => 250000, 'DiscountAmount' => 0, 'NetAmount' => 250000, 'PaymentID' => 'PM-2', 'BillNote' => '', 'Username' => 'user2', 'Ca' => 'C', 'DateGroup' => '16-08-2026'],
            ],
            'product_summary' => [
                ['Product' => 'Áo sơ mi', 'Quantity' => 2, 'TotalAmount' => 120000],
                ['Product' => 'Quần âu', 'Quantity' => 1, 'TotalAmount' => 250000],
            ],
            'totals' => ['TotalAmount' => 370000, 'DiscountAmount' => 10000, 'NetAmount' => 360000],
        ]);

        $this->assertSame(2, substr_count($rendered, 'class="date-group"'));
        $this->assertSame(2, substr_count($rendered, '<td>BK-'));
        $this->assertStringContainsString('padding-top: 6mm;', $rendered);
        $this->assertStringContainsString('@page', $rendered);
        $this->assertStringContainsString('margin: 6mm 5mm 6mm 5mm;', $rendered);
        $this->assertStringContainsString('Tổng tiền</td><td>370.000</td><td>10.000</td><td>360.000</td>', $rendered);
        $this->assertStringContainsString('<td>Áo sơ mi</td><td>2</td><td>120.000</td>', $rendered);
        $this->assertStringContainsString('<td>Quần âu</td><td>1</td><td>250.000</td>', $rendered);
        $this->assertDoesNotMatchRegularExpression('/\{\{[A-Za-z0-9_.|]+\}\}/', $rendered);
    }

    public function test_empty_dataset_preserves_headers_and_supplied_zero_totals(): void
    {
        $rendered = $this->template()->render([
            'hotel' => ['address' => '', 'logo' => ''],
            'report' => ['generated_by' => 'Test', 'generated_at' => '09-09-2026'],
            'parameters' => ['p_from_date' => '09-09-2026', 'p_to_date' => '09-09-2026'],
            'rows' => [],
            'product_summary' => [],
            'totals' => ['TotalAmount' => 0, 'DiscountAmount' => 0, 'NetAmount' => 0],
        ]);

        $this->assertStringContainsString('BÁO CÁO HÓA ĐƠN GIẶT ỦI', $rendered);
        $this->assertStringContainsString('BẢNG KÊ THEO SẢN PHẨM', $rendered);
        $this->assertStringContainsString('Tổng tiền</td><td>0</td><td>0</td><td>0</td>', $rendered);
        $this->assertStringNotContainsString('class="date-group"', $rendered);
        $this->assertStringNotContainsString('{{', $rendered);
    }

    public function test_render_escapes_raw_text_preserves_trusted_logo_and_blocks_placeholder_smuggling(): void
    {
        $template = $this->template();
        $rawGuest = '<script>alert("x")</script> {{hotel.address}}';
        $rawNote = '<img src=x onerror=alert(1)> {{totals.NetAmount}}';
        $rawProduct = '<b>Áo</b> {{report.generated_by}}';
        $data = [
            'hotel' => ['address' => 'Synthetic Hotel', 'logo' => '<img src="/trusted-logo.svg" alt="Logo">'],
            'report' => ['generated_by' => 'Synthetic User', 'generated_at' => '15-08-2026'],
            'parameters' => ['p_from_date' => '15-08-2026', 'p_to_date' => '15-08-2026'],
            'rows' => [[
                'STT' => 1, 'BookingId' => 'BK-1', 'Room' => '101', 'Guest' => $rawGuest, 'DescriptionServive' => 'Dịch vụ', 'Product' => $rawProduct,
                'TotalAmount' => 1000, 'DiscountAmount' => 0, 'NetAmount' => 1000, 'PaymentID' => 'PM-1', 'BillNote' => $rawNote, 'Username' => 'user', 'Ca' => 'S', 'DateGroup' => '15-08-2026',
            ]],
            'product_summary' => [['Product' => $rawProduct, 'Quantity' => 1, 'TotalAmount' => 1000]],
            'totals' => ['TotalAmount' => 1000, 'DiscountAmount' => 0, 'NetAmount' => 1000],
        ];

        $prepared = $template->prepareData($data);
        $rendered = $template->render($data, new TemplateRendererService());

        $this->assertIsInt($prepared['totals']['TotalAmount']);
        $this->assertSame(1000, $prepared['totals']['TotalAmount']);
        $this->assertStringNotContainsString('<script>', $rendered);
        $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $rendered);
        $this->assertStringContainsString('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', $rendered);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $rendered);
        $this->assertStringContainsString('&#123;&#123;hotel.address&#125;&#125;', $rendered);
        $this->assertStringContainsString('&#123;&#123;totals.NetAmount&#125;&#125;', $rendered);
        $this->assertStringContainsString('&#123;&#123;report.generated_by&#125;&#125;', $rendered);
        $this->assertStringContainsString('<img src="/trusted-logo.svg" alt="Logo">', $rendered);
        $this->assertStringNotContainsString('&lt;img src=&quot;/trusted-logo.svg&quot;', $rendered);
        $this->assertDoesNotMatchRegularExpression('/\{\{[A-Za-z0-9_.|]+\}\}/', $rendered);
    }
}
