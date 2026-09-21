<?php

namespace Tests\Feature;

use Tests\TestCase;

class RoomSpecialRequestsReportTest extends TestCase
{
    private function template(): object
    {
        return require database_path('report_templates/room_special_requests_reference.php');
    }

    public function test_migration_file_contains_valid_procedure_and_configuration(): void
    {
        $path = database_path('migrations/2026_09_16_180000_create_room_special_requests_report.php');
        $this->assertFileExists($path);

        $sql = file_get_contents($path);
        $this->assertStringContainsString('CREATE PROCEDURE rpt_room_special_requests', $sql);
        $this->assertStringContainsString('booking_room_special_requests AS brsr', $sql);
        $this->assertStringContainsString('special_requests AS sr', $sql);
        $this->assertStringContainsString('booking_rooms AS br', $sql);
        $this->assertStringContainsString('bookings AS b', $sql);
        $this->assertStringContainsString('GROUP_CONCAT(DISTINCT sr.name', $sql);
        $this->assertStringContainsString('p_date_type', $sql);
        $this->assertStringContainsString('ROOM_SPECIAL_REQUESTS_REFERENCE', $sql);
    }

    public function test_room_special_requests_template_definition_and_blocks(): void
    {
        $template = $this->template();
        $definition = $template->definition();
        $blocks = $template->blocks();
        $tableBlock = $blocks['detail'][0];

        $this->assertSame('ROOM_SPECIAL_REQUESTS_REFERENCE', $definition['report']);
        $this->assertSame('Báo cáo yêu cầu đặc biệt', $definition['name']);
        $this->assertSame('A4', $definition['page_size']);
        $this->assertSame('portrait', $definition['page_orientation']);

        $this->assertCount(9, $tableBlock['columns']);
        $this->assertSame('Mã ĐK', $tableBlock['columns'][0]['header']);
        $this->assertSame('row.BookingId', $tableBlock['columns'][0]['value']);
        $this->assertSame('Phòng', $tableBlock['columns'][1]['header']);
        $this->assertSame('Loại Phòng', $tableBlock['columns'][2]['header']);
        $this->assertSame('Tên Khách', $tableBlock['columns'][3]['header']);
        $this->assertSame('Ngày Đến', $tableBlock['columns'][4]['header']);
        $this->assertSame('Ngày Đi', $tableBlock['columns'][5]['header']);
        $this->assertSame('N.Lớn', $tableBlock['columns'][6]['header']);
        $this->assertSame('T.Em', $tableBlock['columns'][7]['header']);
        $this->assertSame('Yêu Cầu Đặc Biệt', $tableBlock['columns'][8]['header']);
        $this->assertArrayNotHasKey('grouping', $tableBlock);

        $this->assertCount(3, $tableBlock['customRows']);
        $this->assertSame(['detail', 'detail', 'table'], array_column($tableBlock['customRows'], 'scope'));
        $this->assertSame('Đăng Ký:', $tableBlock['customRows'][0]['cells'][0]['content']);
        $this->assertSame('row.BookingId', $tableBlock['customRows'][0]['cells'][1]['binding']);
        $this->assertSame('Ghi Chú:', $tableBlock['customRows'][1]['cells'][0]['content']);
        $this->assertSame('row.BookingNote', $tableBlock['customRows'][1]['cells'][1]['binding']);
    }

    public function test_room_special_requests_template_renders_properly(): void
    {
        $rendered = $this->template()->render([
            'hotel' => ['address' => '195 Nguyễn Thiện Thuật, Nha Trang', 'logo' => '<div>Logo</div>'],
            'report' => ['generated_by' => 'Admin', 'generated_at' => '15/08/2026'],
            'parameters' => ['p_from_date' => '15-08-2026', 'p_to_date' => '15-08-2026'],
            'rows' => [
                [
                    'BookingId' => 'GAL6418',
                    'BookingNote' => '3 Superior Double Room - BF-B2C Pay by TRIP.COM',
                    'Room' => '508',
                    'RoomType' => 'Superior Double',
                    'GuestName' => 'Guest 1',
                    'ArrivalDate' => '15-08-2026',
                    'DepartureDate' => '18-08-2026',
                    'Adults' => 2,
                    'Children' => 0,
                    'AdultsDisplay' => '2/0',
                    'ChildrenDisplay' => '0',
                    'SpecialRequests' => 'Honeymoon, High Floor',
                ],
                [
                    'BookingId' => 'GAL6649',
                    'BookingNote' => '5 SUP DBL - CTY TT',
                    'Room' => '603',
                    'RoomType' => 'Deluxe Twin with Balcony',
                    'GuestName' => 'Guest 2',
                    'ArrivalDate' => '14-08-2026',
                    'DepartureDate' => '15-08-2026',
                    'Adults' => 2,
                    'Children' => 0,
                    'AdultsDisplay' => '2/0',
                    'ChildrenDisplay' => '0',
                    'SpecialRequests' => 'Quite Room',
                ],
            ],
        ]);

        $this->assertStringContainsString('BÁO CÁO YÊU CẦU ĐẶC BIỆT', $rendered);
        $this->assertStringContainsString('Đăng Ký:', $rendered);
        $this->assertStringContainsString('GAL6418', $rendered);
        $this->assertStringContainsString('Ghi Chú:', $rendered);
        $this->assertStringContainsString('3 Superior Double Room - BF-B2C Pay by TRIP.COM', $rendered);
        $this->assertStringContainsString('white-space: pre-wrap', $rendered);
        $this->assertStringContainsString('Honeymoon, High Floor', $rendered);
        $this->assertStringContainsString('GAL6649', $rendered);
        $this->assertStringContainsString('5 SUP DBL - CTY TT', $rendered);
        $this->assertStringContainsString('Quite Room', $rendered);
        $this->assertStringNotContainsString('pms-group-header', $rendered);
        $this->assertStringNotContainsString('{{', $rendered);
    }
}
