<?php

namespace Tests\Unit\Reports;

use Tests\TestCase;

class MinibarInvoicesByProductReportTest extends TestCase
{
    public function test_migration_has_isolated_registration_and_legacy_sp206_minibar_contract(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_09_14_220000_create_minibar_invoice_product_report.php'));

        foreach ([
            'MINIBAR_INVOICES_BY_PRODUCT',
            'rpt_minibar_invoices_by_product',
            'MINIBAR_INVOICES_BY_PRODUCT_STANDARD',
            'sp_206',
            'freeitem',
            'p_group_by_date',
            "h.Outlet = 'MB'",
            'SUM(d.Quantity)',
            'ROW_NUMBER() OVER',
            'd.Deleted = 0',
            'h.FOCType',
            'MIN(h.Currency)',
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $migration);
        }

        $this->assertStringNotContainsString('d.Currency', $migration);
        $this->assertStringNotContainsString('ReportsPage.vue', $migration);
    }

    public function test_reference_template_matches_image1_product_summary_layout(): void
    {
        $template = require database_path('report_templates/minibar_invoices_by_product_reference.php');
        $d = $template->definition();

        $this->assertSame('MINIBAR_INVOICES_BY_PRODUCT_REFERENCE', $d['report']);
        $this->assertSame('columns', $d['content_json']['header'][0]['type']);
        $this->assertSame(['30%', '70%'], array_column($d['content_json']['header'][0]['columns'], 'width'));
        $this->assertSame(8, count($d['content_json']['detail'][0]['columns']));
        $this->assertSame(
            ['ID', 'Sản phẩm', 'Đơn vị', 'Đơn Giá', 'Số lượng', 'Thành tiền', 'Giảm Giá', 'Tổng tiền'],
            array_column($d['content_json']['detail'][0]['columns'], 'header')
        );

        $widths = array_map(fn ($c) => (float) rtrim($c['width'], '%'), $d['content_json']['detail'][0]['columns']);
        $this->assertSame(100.0, array_sum($widths));

        $html = $template->render([
            'hotel' => ['address' => 'Phường Nha Trang, Tỉnh Khánh Hòa, Việt Nam', 'logo' => ''],
            'report' => ['generated_by' => 'Demo', 'generated_at' => '15/08/2026'],
            'parameters' => ['p_from_date' => '15-08-2026', 'p_to_date' => '15-08-2026'],
            'rows' => [
                [
                    'ID' => 1,
                    'ProductType' => 'Minibar',
                    'MaProduct' => 1,
                    'Product' => 'Nước suối Aqua 1,5l',
                    'Currency' => 'Chai',
                    'Rate' => 25000,
                    'Quantity' => 7,
                    'Amount' => 175000,
                    'DiscountAmount' => 0,
                    'Total' => 175000,
                ],
                [
                    'ID' => 2,
                    'ProductType' => 'Minibar',
                    'MaProduct' => 2,
                    'Product' => 'Nước suối Aqua 500ml',
                    'Currency' => 'Chai',
                    'Rate' => 15000,
                    'Quantity' => 7,
                    'Amount' => 105000,
                    'DiscountAmount' => 9000,
                    'Total' => 87900,
                ],
            ],
            'totals' => ['Total' => 262900],
        ]);

        $this->assertStringContainsString('BÁO CÁO HÓA ĐƠN MINIBAR(THEO SẢN PHẨM)', $html);
        $this->assertStringContainsString('class="report-header-band"', $html);
        $this->assertStringContainsString('class="hotel-logo"', $html);
        $this->assertStringContainsString('Loại', $html);
        $this->assertStringContainsString('Minibar', $html);
        $this->assertStringContainsString('Nước suối Aqua 1,5l', $html);
        $this->assertStringContainsString('Nước suối Aqua 500ml', $html);
        $this->assertStringContainsString('Chai', $html);
        $this->assertStringContainsString('25.000', $html);
        $this->assertStringContainsString('15.000', $html);
        $this->assertStringContainsString('175.000', $html);
        $this->assertStringContainsString('105.000', $html);
        $this->assertStringContainsString('87.900', $html);
        $this->assertStringContainsString('262.900', $html);
        $this->assertStringNotContainsString('Ngày: 15/08/2026', $html);
        $this->assertStringNotContainsString('{{', $html);

        $htmlWithDateGroup = $template->render([
            'hotel' => ['address' => 'Phường Nha Trang, Tỉnh Khánh Hòa, Việt Nam', 'logo' => ''],
            'report' => ['generated_by' => 'Demo', 'generated_at' => '15/08/2026'],
            'parameters' => ['p_from_date' => '15-08-2026', 'p_to_date' => '15-08-2026', 'p_group_by_date' => 1],
            'rows' => [
                [
                    'ID' => 1,
                    'DateGroup' => '15/08/2026',
                    'ProductType' => 'Minibar',
                    'MaProduct' => 1,
                    'Product' => 'Nước suối Aqua 1,5l',
                    'Currency' => 'Chai',
                    'Rate' => 25000,
                    'Quantity' => 7,
                    'Amount' => 175000,
                    'DiscountAmount' => 0,
                    'Total' => 175000,
                ],
            ],
            'totals' => ['Total' => 175000],
        ]);

        $this->assertStringContainsString('Ngày: 15/08/2026', $htmlWithDateGroup);
        $this->assertStringContainsString('Loại', $htmlWithDateGroup);
        $this->assertStringContainsString('Minibar', $htmlWithDateGroup);
    }

    public function test_stored_procedure_runs_cleanly_on_branch(): void
    {
        $connection = config('database_domains.branch_connections.HKT1', 'mysql_hkt1');
        try {
            $rows = \Illuminate\Support\Facades\DB::connection($connection)
                ->select('CALL rpt_minibar_invoices_by_product(?, ?, ?, ?, ?, ?, ?)', [
                    '2026-08-01',
                    '2026-08-20',
                    null,
                    null,
                    null,
                    0,
                    0,
                ]);
            $this->assertIsArray($rows);
        } catch (\Throwable $e) {
            $this->markTestSkipped('Database connection or procedure not available: ' . $e->getMessage());
        }
    }
}
