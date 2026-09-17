<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VipGuestsReportTest extends TestCase
{
    private function template(): object
    {
        return require database_path('report_templates/vip_guests_reference.php');
    }

    public function test_migration_file_contains_valid_procedure_and_configuration(): void
    {
        $path = database_path('migrations/2026_09_17_170000_create_vip_guests_report.php');
        $this->assertFileExists($path);

        $sql = file_get_contents($path);
        $this->assertStringContainsString('CREATE PROCEDURE rpt_vip_guests', $sql);
        $this->assertStringContainsString('guests AS g', $sql);
        $this->assertStringContainsString('booking_room_guests AS brg', $sql);
        $this->assertStringContainsString('booking_rooms AS br', $sql);
        $this->assertStringContainsString('guest_types AS gt', $sql);
        $this->assertStringContainsString('bookings AS b', $sql);
        $this->assertStringContainsString('companies AS c', $sql);
        $this->assertStringContainsString('p_from_date', $sql);
        $this->assertStringContainsString('p_to_date', $sql);
        $this->assertStringContainsString('p_guest_type', $sql);
        $this->assertStringContainsString('VIP_GUESTS_STANDARD', $sql);
        $this->assertStringContainsString('Báo cáo khách VIP', $sql);
        $this->assertStringContainsString('Báo cáo khách', $sql);
    }

    public function test_vip_guests_template_definition_and_blocks(): void
    {
        $template = $this->template();
        $definition = $template->definition();
        $blocks = $template->blocks();
        $tableBlock = $blocks['detail'][0];

        $this->assertSame('VIP_GUESTS_STANDARD', $definition['report']);
        $this->assertSame('Báo cáo khách VIP', $definition['name']);
        $this->assertSame('A4', $definition['page_size']);
        $this->assertSame('landscape', $definition['page_orientation']);
        $this->assertSame(6, $definition['margin_top']);
        $this->assertSame(5, $definition['margin_right']);
        $this->assertSame(6, $definition['margin_bottom']);
        $this->assertSame(5, $definition['margin_left']);

        $this->assertCount(11, $tableBlock['columns']);
        $headers = array_column($tableBlock['columns'], 'header');
        $this->assertSame('Tên Khách', $headers[0]);
        $this->assertSame('Tình Trạng', $headers[1]);
        $this->assertSame('Đăng Ký', $headers[2]);
        $this->assertSame('Phòng', $headers[3]);
        $this->assertSame('Loại Khách', $headers[4]);
        $this->assertSame('Ngày Đến', $headers[5]);
        $this->assertSame('Ngày Đi', $headers[6]);
        $this->assertSame('Giá Phòng', $headers[7]);
        $this->assertStringContainsString('Người Lớn', $headers[8]);
        $this->assertSame('Công Ty', $headers[9]);
        $this->assertSame('Ghi Chú', $headers[10]);

        $this->assertArrayHasKey('grouping', $tableBlock);
        $this->assertCount(1, $tableBlock['grouping']);
        $this->assertSame('GuestType', $tableBlock['grouping'][0]['field']);
        $this->assertStringContainsString('LOẠI KHÁCH:', $tableBlock['grouping'][0]['headerCells'][0]['content']);
        $this->assertStringContainsString('#ff1414', $tableBlock['grouping'][0]['headerCells'][0]['content']);

        $this->assertArrayHasKey('customRows', $tableBlock);
        $this->assertCount(2, $tableBlock['customRows']);
        $this->assertSame('group', $tableBlock['customRows'][0]['scope']);
        $this->assertSame('group.count', $tableBlock['customRows'][0]['cells'][1]['binding']);
        $this->assertSame('table', $tableBlock['customRows'][1]['scope']);
        $this->assertSame('aggregate.rows.count', $tableBlock['customRows'][1]['cells'][1]['binding']);
    }

    public function test_vip_guests_template_renders_properly(): void
    {
        $rendered = $this->template()->render([
            'hotel' => [
                'address' => '195 Nguyễn Thiện Thuật, Phường Nha Trang, Tỉnh Khánh Hòa, Việt Nam',
                'logo' => '<img src="/logo.png" />',
            ],
            'report' => [
                'generated_by' => 'Admin',
                'generated_at' => '15/08/2026',
            ],
            'parameters' => [
                'p_from_date' => '15-08-2026',
                'p_to_date' => '15-08-2026',
                'p_guest_type' => 0,
            ],
            'rows' => [
                [
                    'GuestName' => 'KICHKIREVA TATIANA',
                    'Status' => 1,
                    'StatusVi' => 'Đang ở',
                    'BookingId' => 'GAL6457',
                    'Room' => '702',
                    'GuestType' => 'VIP 1',
                    'ArrivalDate' => '11-08-2026',
                    'DepartureDate' => '15-08-2026',
                    'Rate' => 757631,
                    'Adult' => 2,
                    'Child' => 0,
                    'AdultChild' => '2/0',
                    'Company' => 'EMERGING',
                    'Note' => 'Deluxe Twin City View. Có ăn sáng. Pay by Emerging Travel',
                ],
                [
                    'GuestName' => 'Guest 1',
                    'Status' => 0,
                    'StatusVi' => 'Đăng ký',
                    'BookingId' => 'GAL6418',
                    'Room' => '508',
                    'GuestType' => 'VIP 2',
                    'ArrivalDate' => '15-08-2026',
                    'DepartureDate' => '18-08-2026',
                    'Rate' => 671925,
                    'Adult' => 2,
                    'Child' => 0,
                    'AdultChild' => '2/0',
                    'Company' => 'TRIP.COM',
                    'Note' => '3 Superior Double Room - BF-B2C. Pay by TRIP.COM',
                ],
            ],
        ]);

        $this->assertStringContainsString('BÁO CÁO KHÁCH VIP', $rendered);
        $this->assertStringContainsString('195 Nguyễn Thiện Thuật', $rendered);
        $this->assertStringContainsString('Admin', $rendered);
        $this->assertStringContainsString('15/08/2026', $rendered);
        $this->assertStringContainsString('15-08-2026 - 15-08-2026', $rendered);

        // Group 1
        $this->assertStringContainsString('LOẠI KHÁCH:', $rendered);
        $this->assertStringContainsString('VIP 1', $rendered);
        $this->assertStringContainsString('KICHKIREVA TATIANA', $rendered);
        $this->assertStringContainsString('Đang ở', $rendered);
        $this->assertStringContainsString('GAL6457', $rendered);
        $this->assertStringContainsString('702', $rendered);
        $this->assertStringContainsString('757.631', $rendered);
        $this->assertStringContainsString('EMERGING', $rendered);

        // Group 2
        $this->assertStringContainsString('VIP 2', $rendered);
        $this->assertStringContainsString('Guest 1', $rendered);
        $this->assertStringContainsString('Đăng ký', $rendered);
        $this->assertStringContainsString('GAL6418', $rendered);
        $this->assertStringContainsString('508', $rendered);
        $this->assertStringContainsString('671.925', $rendered);
        $this->assertStringContainsString('TRIP.COM', $rendered);

        // Totals
        $this->assertStringContainsString('TỔNG CỘNG', $rendered);
        $this->assertStringContainsString('#dee2ed', $this->template()->css());
        $this->assertStringContainsString('#ff1414', $this->template()->css());
    }
}
