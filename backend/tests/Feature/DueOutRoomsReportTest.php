<?php

namespace Tests\Feature;

use App\Services\TemplateRendererService;
use ReflectionMethod;
use Tests\TestCase;

class DueOutRoomsReportTest extends TestCase
{
    public function test_migration_preserves_sp_008_filters_and_exclusions(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_08_28_184000_create_due_out_rooms_report.php'));

        $this->assertStringContainsString('CREATE PROCEDURE rpt_due_out_rooms', $migration);
        $this->assertStringContainsString('br.departure_date BETWEEN p_from_date AND p_to_date', $migration);
        $this->assertStringContainsString('br.status IN (0, 1, 2)', $migration);
        $this->assertStringContainsString('b.status IN (0, 1, 2)', $migration);
        $this->assertStringContainsString('rs.is_availability = 1', $migration);
        $this->assertStringContainsString('p_room_class_id', $migration);
        $this->assertStringContainsString('p_registration_status_id', $migration);
        $this->assertStringContainsString('p_area', $migration);
        $this->assertStringContainsString('p_company_id', $migration);
        $this->assertStringContainsString('p_booking_id', $migration);
        $this->assertStringContainsString('COALESCE(r.is_internal, 0) = 0', $migration);
        $this->assertStringContainsString("br.room_number NOT LIKE '0%'", $migration);
        $this->assertStringContainsString("'DUE_OUT_ROOMS_STANDARD'", $migration);
    }

    public function test_reference_template_renders_daily_and_period_totals(): void
    {
        $reference = require database_path('report_templates/due_out_rooms_reference.php');
        $html = (new ReflectionMethod($reference, 'html'))->invoke($reference);
        $blocks = (new ReflectionMethod($reference, 'blocks'))->invoke($reference);
        $data = [
            'parameters' => ['p_from_date' => '2026-07-14', 'p_to_date' => '2026-07-15'],
            'report' => ['generated_by' => 'Tester', 'generated_at' => '28/08/2026 12:00:00'],
            'hotel' => ['logo' => '', 'address' => 'Nha Trang'],
            'summary' => ['row_count' => 2],
            'rows' => [
                [
                    'STT' => 1, 'DepartureDateGroup' => '14/07/2026', 'PeriodGroup' => 1,
                    'BookingId' => 265, 'RentalRoomId' => 1001, 'GuestName' => 'Lê Xuân Nghị',
                    'Company' => 'KHÁCH LẺ', 'Room' => '1203', 'RoomType' => 'DLXOV',
                    'ArrivalDate' => '13/07/2026 - 20:01', 'DepartureDate' => '14/07/2026 - 08:52',
                    'RoomNight' => 1, 'ExtraBed' => 0, 'Adult' => 2, 'Child' => 0,
                    'AdultChild' => '2 / 0', 'Note' => '', 'RoomQuantity' => 1,
                ],
                [
                    'STT' => 1, 'DepartureDateGroup' => '15/07/2026', 'PeriodGroup' => 1,
                    'BookingId' => 260, 'RentalRoomId' => 1002, 'GuestName' => 'Shin Jiyong',
                    'Company' => 'KHÁCH LẺ', 'Room' => '1209', 'RoomType' => 'DLXOV',
                    'ArrivalDate' => '13/07/2026 - 10:19', 'DepartureDate' => '15/07/2026 - 04:01',
                    'RoomNight' => 2, 'ExtraBed' => 1, 'Adult' => 1, 'Child' => 1,
                    'AdultChild' => '1 / 1', 'Note' => '', 'RoomQuantity' => 1,
                ],
            ],
        ];

        $rendered = app(TemplateRendererService::class)->render($html, '', $data);

        $this->assertStringContainsString('DANH SÁCH PHÒNG DUE OUT', $rendered);
        $this->assertStringContainsString('Ngày: 14/07/2026', $rendered);
        $this->assertStringContainsString('Ngày: 15/07/2026', $rendered);
        $this->assertSame(2, substr_count($rendered, 'Tổng Theo Ngày'));
        $this->assertStringContainsString('Tổng Giai Đoạn', $rendered);
        $this->assertStringContainsString('>3 / 1</td>', $rendered);
        $this->assertSame('DepartureDateGroup', $blocks['detail'][0]['groupBy']);
        $this->assertStringContainsString('data-group-by="PeriodGroup"', $blocks['detail'][1]['content']);
    }
}
