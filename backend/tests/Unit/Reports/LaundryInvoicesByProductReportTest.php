<?php

namespace Tests\Unit\Reports;

use Tests\TestCase;

class LaundryInvoicesByProductReportTest extends TestCase
{
    public function test_migration_has_isolated_registration_and_legacy_sp206_product_contract(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_09_11_140000_create_laundry_invoice_product_report.php'));
        foreach (['LAUNDRY_INVOICES_BY_PRODUCT','rpt_laundry_invoices_by_product','LAUNDRY_INVOICES_BY_PRODUCT_STANDARD','sp_206','freeitem','p_group_by_date',"h.Outlet='LA'",'SUM(d.Quantity)','GROUP BY CASE WHEN','d.Deleted=0','h.FOCType'] as $fragment) $this->assertStringContainsString($fragment, $migration);
        $this->assertStringNotContainsString('ReportsPage.vue', $migration);
    }

    public function test_reference_template_matches_product_summary_layout(): void
    {
        $template = require database_path('report_templates/laundry_invoices_by_product_reference.php');
        $d = $template->definition();
        $this->assertSame('LAUNDRY_INVOICES_BY_PRODUCT_REFERENCE', $d['report']);
        $this->assertSame(7, count($d['content_json']['detail'][0]['columns']));
        $this->assertSame(['ID','Sản phẩm','Đơn vị','Đơn giá','Số lượng','Thành tiền','Giảm giá'], array_column($d['content_json']['detail'][0]['columns'],'header'));
        $this->assertSame('parameters.p_group_by_date', $d['content_json']['detail'][0]['grouping'][0]['enabledBy']);
        $this->assertSame(100.0, array_sum(array_map(fn($c)=>(float)rtrim($c['width'],'%'),$d['content_json']['detail'][0]['columns'])));
        $html = $template->render(['hotel'=>['address'=>'Hotel','logo'=>''],'report'=>['generated_by'=>'User','generated_at'=>'2026-09-11'],'parameters'=>['p_from_date'=>'11/09/2026','p_to_date'=>'11/09/2026'],'rows'=>[['MaProduct'=>1,'Product'=>'Áo','Currency'=>'VND','Rate'=>10000,'Quantity'=>2,'Total'=>20000,'DiscountAmount'=>0]],'totals'=>['Total'=>20000,'DiscountAmount'=>0]]);
        $this->assertStringContainsString('BÁO CÁO HÓA ĐƠN GIẶT ỦI (THEO SẢN PHẨM)', $html);
        $this->assertStringContainsString('<td>Áo</td>', $html);
        $this->assertStringContainsString('20.000', $html);
        $this->assertStringNotContainsString('{{', $html);
    }
}
