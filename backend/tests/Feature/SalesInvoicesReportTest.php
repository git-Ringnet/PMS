<?php

namespace Tests\Feature;

use App\Models\ReportDefinition;
use App\Services\Reports\ReportDatasetEnricher;
use App\Services\Reports\SalesInvoicesDataAdapter;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SalesInvoicesReportTest extends TestCase
{
    private function template(): object
    {
        return require database_path('report_templates/sales_invoices_reference.php');
    }

    public function test_migration_file_contains_valid_procedure_and_configuration(): void
    {
        $path = database_path('migrations/2026_09_18_100000_create_sales_invoices_report.php');
        $this->assertFileExists($path);

        $sql = file_get_contents($path);
        $this->assertStringContainsString('CREATE PROCEDURE rpt_sales_invoices', $sql);
        $this->assertStringContainsString('sales_invoices bh', $sql);
        $this->assertStringContainsString('hotel_settings', $sql);
        $this->assertStringContainsString('p_from_date', $sql);
        $this->assertStringContainsString('p_to_date', $sql);
        $this->assertStringContainsString('p_export_type', $sql);
        $this->assertStringContainsString('SALES_INVOICES_REFERENCE', $sql);
    }

    public function test_sales_invoices_template_definition_and_blocks(): void
    {
        $template = $this->template();
        $definition = $template->definition();
        $blocks = $template->blocks();
        $tableBlock = $blocks['detail'][0];
        $allocBlock = $blocks['detail'][2];

        $this->assertSame('SALES_INVOICES_REFERENCE', $definition['report']);
        $this->assertSame('Báo cáo hóa đơn bán hàng', $definition['name']);
        $this->assertSame('A4', $definition['page_size']);
        $this->assertSame('landscape', $definition['page_orientation']);

        $this->assertTrue($tableBlock['hasTwoTierHeader']);
        $this->assertSame('Mã HĐ', $tableBlock['topHeader'][0]['label']);
        $this->assertSame('Hình Thức Thanh Toán', $tableBlock['topHeader'][8]['label']);

        $this->assertCount(14, $tableBlock['columns']);
        $this->assertSame('Mã HĐ', $tableBlock['columns'][0]['header']);
        $this->assertSame('Mã TT', $tableBlock['columns'][1]['header']);
        $this->assertSame('Ngày', $tableBlock['columns'][2]['header']);
        $this->assertSame('Phòng', $tableBlock['columns'][3]['header']);
        $this->assertSame('MÃ VAT', $tableBlock['columns'][4]['header']);
        $this->assertSame('Khách', $tableBlock['columns'][5]['header']);
        $this->assertSame('Công Ty', $tableBlock['columns'][6]['header']);
        $this->assertSame('Doanh Thu', $tableBlock['columns'][7]['header']);
        $this->assertSame('Tiền Mặt', $tableBlock['columns'][8]['header']);
        $this->assertSame('CK/Thẻ', $tableBlock['columns'][9]['header']);
        $this->assertSame('Voucher Miễn Phí', $tableBlock['columns'][10]['header']);
        $this->assertSame('City Ledger', $tableBlock['columns'][11]['header']);
        $this->assertSame('Đặt Cọc Tiền Mặt', $tableBlock['columns'][12]['header']);
        $this->assertSame('Đặt Cọc Bằng Thẻ', $tableBlock['columns'][13]['header']);

        $this->assertSame('InvoiceDateFormatted', $tableBlock['grouping'][0]['field']);

        $this->assertSame('currency_allocations', $allocBlock['dataSource']);
        $this->assertSame('Hình Thức Thanh Toán', $allocBlock['columns'][0]['header']);
        $this->assertSame('Thu Ngân', $allocBlock['columns'][1]['header']);
        $this->assertSame('Đặt Cọc', $allocBlock['columns'][2]['header']);
        $this->assertSame('Tổng Theo C.ty', $allocBlock['columns'][3]['header']);
    }

    public function test_data_adapter_computes_currency_allocations_and_formats_rows(): void
    {
        $adapter = new SalesInvoicesDataAdapter();
        $sampleData = [
            'rows' => [
                [
                    'BillID' => '422',
                    'PaymentID' => '739',
                    'InvoiceDateFormatted' => '14/07/2026',
                    'Room' => 'C:265',
                    'VATNo' => 'C26MBD:0000000',
                    'GuestName' => 'BK 265 - KHÁCH LẺ - Anh Nghị',
                    'CompanyName' => 'KHÁCH LẺ',
                    'Amount' => 800000,
                    'Cash' => 0,
                    'Card' => 800000,
                    'PaymentMethod' => 'BT',
                ],
                [
                    'BillID' => '423',
                    'PaymentID' => '738',
                    'InvoiceDateFormatted' => '14/07/2026',
                    'Room' => 'C:237',
                    'VATNo' => 'C26MBD:0000000',
                    'GuestName' => 'BK 237 - Booking.com - Faycal w',
                    'CompanyName' => 'Booking.com',
                    'Amount' => 3061071,
                    'Cash' => 0,
                    'Card' => 3061071,
                    'PaymentMethod' => 'CD',
                ],
                [
                    'BillID' => '424',
                    'PaymentID' => '740',
                    'InvoiceDateFormatted' => '14/07/2026',
                    'Room' => 'C:260',
                    'VATNo' => 'C26MBD:0000000',
                    'GuestName' => 'BK 260 - KHÁCH LẺ - SHIN/JIYONG - KOR',
                    'CompanyName' => 'KHÁCH LẺ',
                    'Amount' => 978000,
                    'Cash' => 978000,
                    'Card' => 0,
                    'PaymentMethod' => 'CA',
                ],
            ],
        ];

        $adapted = $adapter->adapt($sampleData);

        $this->assertArrayHasKey('currency_allocations', $adapted);
        $this->assertCount(3, $adapted['currency_allocations']);

        // Bank transfer / Chuyển khoản (Bill 422: 800,000)
        $this->assertSame('Bank transfer/ Chuyển khoản', $adapted['currency_allocations'][0]['Method']);
        $this->assertEquals(800000, $adapted['currency_allocations'][0]['CashierAmount']);
        $this->assertEquals(800000, $adapted['currency_allocations'][0]['TotalAmount']);

        // Cash / Tiền mặt (Bill 424: 978,000)
        $this->assertSame('Cash/ Tiền mặt', $adapted['currency_allocations'][1]['Method']);
        $this->assertEquals(978000, $adapted['currency_allocations'][1]['CashierAmount']);
        $this->assertEquals(978000, $adapted['currency_allocations'][1]['TotalAmount']);

        // Credit Card / Cà thẻ (Bill 423: 3,061,071)
        $this->assertSame('Credit Card/ Cà thẻ', $adapted['currency_allocations'][2]['Method']);
        $this->assertEquals(3061071, $adapted['currency_allocations'][2]['CashierAmount']);
        $this->assertEquals(3061071, $adapted['currency_allocations'][2]['TotalAmount']);
    }

    public function test_template_renders_valid_html_with_sample_data(): void
    {
        $template = $this->template();
        $adapter = new SalesInvoicesDataAdapter();

        $sampleData = [
            'hotel' => [
                'name' => 'Golden Crest Quy Nhơn',
                'address' => '66 Hàn Mặc Tử, P. Quy Nhơn',
                'logo' => '<img src="/logo.png" />',
            ],
            'report' => [
                'generated_by' => 'Admin',
            ],
            'parameters' => [
                'p_from_date' => '14/07/2026',
                'p_to_date' => '14/07/2026',
            ],
            'rows' => [
                [
                    'BillID' => '422',
                    'PaymentID' => '739',
                    'InvoiceDateFormatted' => '14/07/2026',
                    'Room' => 'C:265',
                    'VATNo' => 'C26MBD:0000000',
                    'GuestName' => 'BK 265 - KHÁCH LẺ - Anh Nghị',
                    'CompanyName' => 'KHÁCH LẺ',
                    'Amount' => 800000,
                    'Cash' => 0,
                    'Card' => 800000,
                    'Voucher' => 0,
                    'City' => 0,
                    'DPCash' => 0,
                    'DPCard' => 0,
                    'PaymentMethod' => 'BT',
                ],
            ],
        ];

        $adapted = $adapter->adapt($sampleData);
        $html = $template->render($adapted);

        $this->assertStringContainsString('BÁO CÁO HÓA ĐƠN BÁN HÀNG', $html);
        $this->assertStringContainsString('Bảng Phân Bổ Tiền Tệ', $html);
        $this->assertStringContainsString('66 Hàn Mặc Tử', $html);
        $this->assertStringContainsString('14/07/2026', $html);
        $this->assertStringContainsString('422', $html);
        $this->assertStringContainsString('800.000', $html);
    }

    public function test_stored_procedure_executes_successfully_in_database(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('MySQL driver required for stored procedure test.');
        }

        $results = DB::select('CALL rpt_sales_invoices(?, ?, ?, ?, ?, ?, ?, ?, ?)', [
            '2026-07-14',
            '2026-07-14',
            '',
            '',
            '',
            '',
            'Date',
            'ASC',
            '',
        ]);

        $this->assertIsArray($results);
    }

    public function test_report_enricher_integrates_sales_invoices(): void
    {
        $enricher = app(ReportDatasetEnricher::class);
        $reportDef = new ReportDefinition();
        $reportDef->code = 'SALES_INVOICES';

        $data = [
            'rows' => [
                [
                    'BillID' => '101',
                    'Amount' => 500000,
                    'Cash' => 500000,
                    'InvoiceDate' => '2026-07-14',
                    'PaymentMethod' => 'CA',
                ],
            ],
        ];

        $enriched = $enricher->enrich($reportDef, $data);
        $this->assertArrayHasKey('currency_allocations', $enriched);
        $this->assertCount(3, $enriched['currency_allocations']);
        $this->assertEquals(500000, $enriched['currency_allocations'][1]['CashierAmount']);
    }
}
