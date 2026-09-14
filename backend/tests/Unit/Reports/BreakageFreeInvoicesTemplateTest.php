<?php

namespace Tests\Unit\Reports;

use Tests\TestCase;

class BreakageFreeInvoicesTemplateTest extends TestCase
{
    private function template(): object
    {
        return require database_path('report_templates/breakage_free_invoices_reference.php');
    }

    public function test_free_invoice_report_has_independent_metadata_and_layout(): void
    {
        $template = $this->template();
        $definition = $template->definition();
        $detail = $template->blocks()['detail'][0];

        $this->assertSame('BREAKAGE_FREE_INVOICES_REFERENCE', $definition['report']);
        $this->assertSame('Báo cáo hóa đơn hàng bể vỡ miễn phí - Mẫu tham chiếu', $definition['name']);
        $this->assertSame(13, count($detail['columns']));
        $this->assertSame('HTTT', $detail['columns'][12]['header']);
        $this->assertSame('row.HTTT', $detail['columns'][12]['value']);
        $this->assertSame('DateGroup', $detail['groups'][0]['field']);
        $this->assertStringContainsString('BÁO CÁO HÓA ĐƠN HÀNG BỂ VỠ MIỄN PHÍ', $template->html());
        $this->assertStringNotContainsString('LAUNDRY_INVOICES_REFERENCE', file_get_contents(database_path('report_templates/breakage_free_invoices_reference.php')));
    }

    public function test_free_template_renders_empty_and_free_rows_without_placeholders(): void
    {
        $rendered = $this->template()->render([
            'hotel' => ['address' => 'Synthetic Hotel', 'logo' => ''],
            'report' => ['generated_by' => 'Test', 'generated_at' => '15-08-2026'],
            'parameters' => ['p_from_date' => '15-08-2026', 'p_to_date' => '15-08-2026'],
            'rows' => [[
                'STT' => 1, 'BookingId' => 'BK-FREE', 'Room' => '101', 'Guest' => 'Guest A',
                'DescriptionServive' => 'Hàng bể vỡ', 'Product' => 'Áo sơ mi', 'TotalAmount' => 120000,
                'DiscountAmount' => 120000, 'NetAmount' => 0, 'PaymentID' => 'PM-CL', 'BillNote' => 'Free',
                'Username' => 'user', 'Ca' => 'S', 'HTTT' => 'Miễn phí', 'DateGroup' => '15-08-2026',
            ]],
            'product_summary' => [['Product' => 'Áo sơ mi', 'Quantity' => 1, 'TotalAmount' => 0]],
            'totals' => ['TotalAmount' => 120000, 'DiscountAmount' => 120000, 'NetAmount' => 0],
        ]);

        $this->assertStringContainsString('BÁO CÁO HÓA ĐƠN HÀNG BỂ VỠ MIỄN PHÍ', $rendered);
        $this->assertStringContainsString('BK-FREE', $rendered);
        $this->assertStringContainsString('Free', $rendered);
        $this->assertStringNotContainsString('{{', $rendered);
    }
}

