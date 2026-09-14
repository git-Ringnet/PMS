<?php

namespace Tests\Unit\Reports;

use Tests\TestCase;

class BreakageInvoicesTemplateTest extends TestCase
{
    public function test_reference_has_its_own_title_and_preserves_the_thirteen_column_contract(): void
    {
        $template = require database_path('report_templates/breakage_invoices_reference.php');
        $laundry = require database_path('report_templates/laundry_invoices_reference.php');
        $definition = $template->definition();

        $this->assertSame('BREAKAGE_INVOICES_REFERENCE', $definition['report']);
        $this->assertCount(13, $definition['content_json']['detail'][0]['columns']);
        $this->assertSame($laundry->blocks()['detail'], $template->blocks()['detail']);
        $this->assertStringContainsString('HÀNG BỂ VỠ', $template->html());
        $this->assertStringContainsString('HÀNG BỂ VỠ', $template->blocks()['header'][2]['content']);
        $this->assertStringContainsString('GIẶT ỦI', $laundry->html());
    }

    public function test_empty_render_preserves_layout_without_inventing_business_rows(): void
    {
        $template = require database_path('report_templates/breakage_invoices_reference.php');
        $rendered = $template->render([
            'hotel' => ['logo' => '', 'address' => ''],
            'report' => ['generated_by' => '', 'generated_at' => ''],
            'parameters' => ['p_from_date' => '', 'p_to_date' => ''],
            'rows' => [], 'product_summary' => [],
            'totals' => ['TotalAmount' => 0, 'DiscountAmount' => 0, 'NetAmount' => 0],
        ]);

        $this->assertStringContainsString('BÁO CÁO HÓA ĐƠN HÀNG BỂ VỠ', $rendered);
        $this->assertStringContainsString('BẢNG KÊ THEO SẢN PHẨM', $rendered);
        $this->assertStringNotContainsString('{{', $rendered);
        $this->assertStringNotContainsString('class="date-group"', $rendered);
    }

    public function test_text_is_escaped_without_replacing_title_words_inside_user_data(): void
    {
        $template = require database_path('report_templates/breakage_invoices_reference.php');
        $data = ['report' => ['generated_by' => '<script>x</script> BÁO CÁO HÓA ĐƠN GIẶT ỦI {{hotel.address}}']];
        $rendered = $template->render($data);

        $this->assertStringNotContainsString('<script>', $rendered);
        $this->assertStringContainsString('&lt;script&gt;x&lt;/script&gt; BÁO CÁO HÓA ĐƠN GIẶT ỦI', $rendered);
        $this->assertStringContainsString('&#123;&#123;hotel.address&#125;&#125;', $rendered);
        $this->assertSame('<script>x</script> BÁO CÁO HÓA ĐƠN GIẶT ỦI {{hotel.address}}', $data['report']['generated_by']);
    }
}
