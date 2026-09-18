<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UnpaidServiceBillsReportTest extends TestCase
{
    private function template(): object
    {
        return require database_path('report_templates/unpaid_service_bills_reference.php');
    }

    public function test_migration_contains_valid_procedure_and_configuration(): void
    {
        $path = database_path('migrations/2026_09_18_130000_create_unpaid_service_bills_report.php');
        $this->assertFileExists($path);

        $sql = file_get_contents($path);
        $this->assertStringContainsString('CREATE PROCEDURE rpt_unpaid_service_bills', $sql);
        $this->assertStringContainsString('service_bills AS hddv', $sql);
        $this->assertStringContainsString('service_bill_details AS dvct', $sql);
        $this->assertStringContainsString('p_from_date', $sql);
        $this->assertStringContainsString('p_to_date', $sql);
        $this->assertStringContainsString('p_user', $sql);
        $this->assertStringContainsString('UNPAID_SERVICE_BILLS_REFERENCE', $sql);
    }

    public function test_template_definition_and_columns(): void
    {
        $template = $this->template();
        $definition = $template->definition();
        $blocks = $template->blocks();
        $tableBlock = $blocks['detail'][0];

        $this->assertSame('UNPAID_SERVICE_BILLS_REFERENCE', $definition['report']);
        $this->assertSame('Báo cáo hóa đơn dịch vụ chưa thanh toán', $definition['name']);
        $this->assertSame('A4', $definition['page_size']);
        $this->assertSame('landscape', $definition['page_orientation']);

        $this->assertCount(15, $tableBlock['columns']);
        $this->assertSame('STT', $tableBlock['columns'][0]['header']);
        $this->assertSame('Mã', $tableBlock['columns'][1]['header']);
        $this->assertSame('Mã ĐK', $tableBlock['columns'][2]['header']);
        $this->assertSame('Tên Khách', $tableBlock['columns'][3]['header']);
        $this->assertSame('Ngày Đến', $tableBlock['columns'][4]['header']);
        $this->assertSame('Ngày Đi', $tableBlock['columns'][5]['header']);
        $this->assertSame('Mô Tả', $tableBlock['columns'][6]['header']);
        $this->assertSame('Công Ty', $tableBlock['columns'][7]['header']);
        $this->assertSame('Giá Gốc', $tableBlock['columns'][8]['header']);
        $this->assertSame('Phí PV', $tableBlock['columns'][9]['header']);
        $this->assertSame('Thuế', $tableBlock['columns'][10]['header']);
        $this->assertSame('Tổng', $tableBlock['columns'][11]['header']);
        $this->assertSame('HTTT', $tableBlock['columns'][12]['header']);
        $this->assertSame('Người Dùng', $tableBlock['columns'][13]['header']);
        $this->assertSame('Giờ', $tableBlock['columns'][14]['header']);

        $this->assertSame('ServiceId', $tableBlock['grouping'][0]['field']);
    }

    public function test_template_renders_valid_html_with_sample_data(): void
    {
        $template = $this->template();

        $sampleData = [
            'hotel' => [
                'name' => 'DTX Hotel Nha Trang',
                'address' => '3A Quân Trấn, Nha Trang',
                'logo' => '<img src="/logo.png" />',
            ],
            'report' => [
                'generated_by' => 'Admin',
                'generated_at' => '15/08/2026 10:00',
            ],
            'parameters' => [
                'p_from_date' => '15-08-2026',
                'p_to_date' => '15-08-2026',
            ],
            'rows' => [
                [
                    'Index' => 1,
                    'Ma' => 53817,
                    'DateFormatted' => '15-08-2026',
                    'RefId' => '',
                    'PaymentId' => '',
                    'BookingId' => 'SM6686',
                    'ArrivalDate' => '20-08-2026',
                    'DepartureDate' => '22-08-2026',
                    'BusinessName' => 'TRIP.COM',
                    'Guest' => 'Linh Do Thuy',
                    'DescriptionServive' => 'Ăn sáng buffet người lớn',
                    'OriginalRate' => 100000,
                    'ServiceChargeAmount' => 5000,
                    'TaxAmount' => 10500,
                    'TienQDTD' => 115500,
                    'Status' => 1,
                    'Username' => 'admin',
                    'OpenTime' => '13:58',
                    'ServiceId' => 'BF',
                    'ServiceName' => 'Ăn sáng',
                    'HTTT' => '',
                ],
            ],
        ];

        $html = $template->render($sampleData);

        $this->assertStringContainsString('BÁO CÁO HÓA ĐƠN DỊCH VỤ CHƯA THANH TOÁN', $html);
        $this->assertStringContainsString('3A Quân Trấn, Nha Trang', $html);
        $this->assertStringContainsString('SM6686', $html);
        $this->assertStringContainsString('53817', $html);
        $this->assertStringContainsString('115.500', $html);
        $this->assertStringContainsString('Tổng doanh thu theo dịch vụ', $html);
    }

    public function test_stored_procedure_executes_successfully_in_database(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('MySQL driver required for stored procedure test.');
        }

        $results = DB::select('CALL rpt_unpaid_service_bills(?, ?, ?, ?, ?, ?, ?, ?, ?)', [
            '2026-08-15',
            '2026-08-15',
            '',
            'Ma',
            'ASC',
            '',
            '',
            '',
            '',
        ]);

        $this->assertIsArray($results);
    }
}
