<?php

namespace Tests\Unit\Reports;

use Tests\TestCase;

class MinibarFreeInvoicesTemplateTest extends TestCase
{
    private function template(): object
    {
        return require database_path('report_templates/minibar_free_invoices_reference.php');
    }

    public function test_free_invoice_report_has_independent_metadata_and_layout(): void
    {
        $template = $this->template();
        $definition = $template->definition();
        $detail = $template->blocks()['detail'][0];

        $this->assertSame('MINIBAR_FREE_INVOICES_REFERENCE', $definition['report']);
        $this->assertSame('Báo cáo hóa đơn minibar miễn phí - Mẫu tham chiếu', $definition['name']);
        $this->assertSame('columns', $definition['content_json']['header'][0]['type']);
        $this->assertSame(['30%', '70%'], array_column($definition['content_json']['header'][0]['columns'], 'width'));
        $this->assertStringContainsString('class="report-header-band"', $template->html());
        $this->assertSame(13, count($detail['columns']));
        $this->assertSame('HTTT', $detail['columns'][12]['header']);
        $this->assertSame('row.HTTT', $detail['columns'][12]['value']);
        $this->assertSame('DateGroup', $detail['groups'][0]['field']);
        $this->assertStringContainsString('BÁO CÁO HÓA ĐƠN MINIBAR MIỄN PHÍ', $template->html());
        $this->assertStringNotContainsString('MINIBAR_INVOICES_REFERENCE', file_get_contents(database_path('report_templates/minibar_free_invoices_reference.php')));
        $this->assertStringNotContainsString('GIẶT ỦI', $template->html());
    }

    public function test_free_template_renders_empty_and_free_rows_without_placeholders(): void
    {
        $rendered = $this->template()->render([
            'hotel' => ['address' => 'Synthetic Hotel', 'logo' => ''],
            'report' => ['generated_by' => 'Test', 'generated_at' => '22-11-2025'],
            'parameters' => ['p_from_date' => '22-11-2025', 'p_to_date' => '24-11-2025'],
            'rows' => [[
                'STT' => 1, 'BookingId' => 3794, 'Room' => '705', 'Guest' => 'Guest Test',
                'DescriptionServive' => 'Minibar', 'Product' => 'Mì ly - 2', 'TotalAmount' => 50000,
                'DiscountAmount' => 50000, 'NetAmount' => 0, 'PaymentID' => 3742, 'BillNote' => 'Free',
                'Username' => 'admin', 'Ca' => 'S', 'HTTT' => 'Miễn phí', 'DateGroup' => '22-11-2025',
            ]],
            'product_summary' => [['Product' => 'Mì ly', 'Quantity' => 2, 'TotalAmount' => 0]],
            'totals' => ['TotalAmount' => 50000, 'DiscountAmount' => 50000, 'NetAmount' => 0],
        ]);

        $this->assertStringContainsString('BÁO CÁO HÓA ĐƠN MINIBAR MIỄN PHÍ', $rendered);
        $this->assertStringContainsString('3794', $rendered);
        $this->assertStringContainsString('705', $rendered);
        $this->assertStringContainsString('Mì ly - 2', $rendered);
        $this->assertStringNotContainsString('{{', $rendered);
    }
}
