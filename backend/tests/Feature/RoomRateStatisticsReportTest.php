<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RoomRateStatisticsReportTest extends TestCase
{
    private function template(): object
    {
        return require database_path('report_templates/room_rate_statistics_reference.php');
    }

    public function test_migration_contains_valid_procedure_and_configuration(): void
    {
        $path = database_path('migrations/2026_09_18_140000_create_room_rate_statistics_report.php');
        $this->assertFileExists($path);

        $sql = file_get_contents($path);
        $this->assertStringContainsString('CREATE PROCEDURE rpt_room_rate_statistics', $sql);
        $this->assertStringContainsString('booking_rooms pt', $sql);
        $this->assertStringContainsString('p_from_date', $sql);
        $this->assertStringContainsString('p_to_date', $sql);
        $this->assertStringContainsString('p_rate_code', $sql);
        $this->assertStringContainsString('ROOM_RATE_STATISTICS_REFERENCE', $sql);
    }

    public function test_template_definition_and_columns(): void
    {
        $template = $this->template();
        $definition = $template->definition();
        $blocks = $template->blocks();
        $tableBlock = $blocks['detail'][0];

        $this->assertSame('ROOM_RATE_STATISTICS_REFERENCE', $definition['report']);
        $this->assertSame('Báo cáo thống kê mã giá phòng', $definition['name']);
        $this->assertSame('A4', $definition['page_size']);
        $this->assertSame('landscape', $definition['page_orientation']);

        $this->assertCount(12, $tableBlock['columns']);
        $this->assertSame('Mã', $tableBlock['columns'][0]['header']);
        $this->assertSame('Mã ĐK', $tableBlock['columns'][1]['header']);
        $this->assertSame('Tên Đăng Ký', $tableBlock['columns'][2]['header']);
        $this->assertSame('Công Ty', $tableBlock['columns'][3]['header']);
        $this->assertSame('Ngày Đến', $tableBlock['columns'][4]['header']);
        $this->assertSame('Ngày Đi', $tableBlock['columns'][5]['header']);
        $this->assertSame('Đêm', $tableBlock['columns'][6]['header']);
        $this->assertSame('Phòng', $tableBlock['columns'][7]['header']);
        $this->assertSame('Người<br>Lớn', $tableBlock['columns'][8]['header']);
        $this->assertSame('Trẻ<br>Em', $tableBlock['columns'][9]['header']);
        $this->assertSame('Loại Phòng', $tableBlock['columns'][10]['header']);
        $this->assertSame('Mã Giá Phòng', $tableBlock['columns'][11]['header']);

        $this->assertSame('RateCode', $tableBlock['grouping'][0]['field']);
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
                'p_rate_code' => '',
            ],
            'rows' => [
                [
                    'Index' => 1,
                    'BookingCode' => 'SM6719',
                    'BookingName' => 'NING NING',
                    'CompanyName' => 'TRIP.COM',
                    'ArrivalDate' => '12-08-2026',
                    'DepartureDate' => '15-08-2026',
                    'NumOfDays' => 3,
                    'Room' => '705',
                    'Adults' => 4,
                    'Children' => 0,
                    'RoomType' => 'FAM',
                    'RateCode' => 'B2B',
                    'RateCodeDescription' => 'Giá B2B',
                ],
                [
                    'Index' => 2,
                    'BookingCode' => 'SM5483',
                    'BookingName' => 'IRINA KONSTANTINOVA',
                    'CompanyName' => 'HOTELBEDS',
                    'ArrivalDate' => '08-08-2026',
                    'DepartureDate' => '19-08-2026',
                    'NumOfDays' => 11,
                    'Room' => '403',
                    'Adults' => 3,
                    'Children' => 0,
                    'RoomType' => 'DLXTB',
                    'RateCode' => 'B2B',
                    'RateCodeDescription' => 'Giá B2B',
                ],
            ],
        ];

        $html = $template->render($sampleData);

        $this->assertStringContainsString('BÁO CÁO THỐNG KÊ MÃ GIÁ PHÒNG', $html);
        $this->assertStringContainsString('3A Quân Trấn, Nha Trang', $html);
        $this->assertStringContainsString('SM6719', $html);
        $this->assertStringContainsString('705', $html);
        $this->assertStringContainsString('B2B', $html);
        $this->assertStringContainsString('Mã Giá Phòng', $html);
    }

    public function test_stored_procedure_executes_successfully_in_database(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('MySQL driver required for stored procedure test.');
        }

        $results = DB::select('CALL rpt_room_rate_statistics(?, ?, ?)', [
            '2026-08-15',
            '2026-08-15',
            '',
        ]);

        $this->assertIsArray($results);
    }
}
