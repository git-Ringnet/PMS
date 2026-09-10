<?php

namespace Tests\Unit\Reports;

use Tests\TestCase;

class MinibarInvoicesTemplateTest extends TestCase
{
    private function template(): object
    {
        return require database_path('report_templates/minibar_invoices_reference.php');
    }

    public function test_reference_has_its_own_title_and_preserves_the_la_layout_contract(): void
    {
        $template = $this->template();
        $laundry = require database_path('report_templates/laundry_invoices_reference.php');
        $definition = $template->definition();

        $this->assertSame('MINIBAR_INVOICES_REFERENCE', $definition['report']);
        $this->assertCount(13, $definition['content_json']['detail'][0]['columns']);
        $this->assertSame($laundry->blocks()['detail'], $template->blocks()['detail']);
        $this->assertStringContainsString('MINIBAR', $template->html());
        $this->assertStringContainsString('MINIBAR', $template->blocks()['header'][2]['content']);
        $this->assertStringContainsString('GIẶT ỦI', $laundry->html());
        $this->assertStringNotContainsString('GIẶT ỦI', $template->html());
    }

    public function test_empty_render_preserves_layout_without_inventing_business_rows(): void
    {
        $rendered = $this->template()->render([
            'hotel' => ['logo' => '', 'address' => ''],
            'report' => ['generated_by' => '', 'generated_at' => ''],
            'parameters' => ['p_from_date' => '', 'p_to_date' => ''],
            'rows' => [], 'product_summary' => [],
            'totals' => ['TotalAmount' => 0, 'DiscountAmount' => 0, 'NetAmount' => 0],
        ]);

        $this->assertStringContainsString('BÁO CÁO HÓA ĐƠN MINIBAR', $rendered);
        $this->assertStringContainsString('BẢNG KÊ THEO SẢN PHẨM', $rendered);
        $this->assertStringNotContainsString('BÁO CÁO HÓA ĐƠN GIẶT ỦI', $rendered);
        $this->assertStringNotContainsString('{{', $rendered);
        $this->assertStringNotContainsString('class="date-group"', $rendered);
    }

    public function test_title_words_inside_user_data_are_not_replaced_after_rendering(): void
    {
        $template = $this->template();
        $generatedBy = '<script>x</script> BÁO CÁO HÓA ĐƠN GIẶT ỦI {{hotel.address}}';
        $data = [
            'hotel' => ['logo' => '', 'address' => 'Synthetic Hotel'],
            'report' => ['generated_by' => $generatedBy, 'generated_at' => '09-09-2026'],
            'parameters' => ['p_from_date' => '09-09-2026', 'p_to_date' => '09-09-2026'],
            'rows' => [], 'product_summary' => [],
            'totals' => ['TotalAmount' => 0, 'DiscountAmount' => 0, 'NetAmount' => 0],
        ];
        $rendered = $template->render($data);

        $this->assertStringContainsString('BÁO CÁO HÓA ĐƠN MINIBAR', $rendered);
        $this->assertStringContainsString('&lt;script&gt;x&lt;/script&gt; BÁO CÁO HÓA ĐƠN GIẶT ỦI', $rendered);
        $this->assertStringContainsString('&#123;&#123;hotel.address&#125;&#125;', $rendered);
        $this->assertStringNotContainsString('<script>', $rendered);
        $this->assertSame($generatedBy, $data['report']['generated_by']);
    }
}
