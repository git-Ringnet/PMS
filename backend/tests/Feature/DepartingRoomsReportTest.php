<?php

namespace Tests\Feature;

use App\Services\TemplateRendererService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class DepartingRoomsReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_preserves_departure_filters_and_legacy_exclusions(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_08_27_130000_create_departing_rooms_report.php'));

        $this->assertStringContainsString('br.departure_date BETWEEN p_from_date AND p_to_date', $migration);
        $this->assertStringContainsString('br.status IN (0, 1, 2)', $migration);
        $this->assertStringContainsString('b.status IN (0, 1, 2)', $migration);
        $this->assertStringContainsString('rs.is_availability = 1', $migration);
        $this->assertStringContainsString('COALESCE(r.is_internal, 0) = 0', $migration);
        $this->assertStringContainsString("br.room_number NOT LIKE '0%'", $migration);
        $this->assertStringContainsString('p_show_services_amount TINYINT', $migration);
    }

    public function test_reference_template_renders_detail_grouping_and_room_type_summary(): void
    {
        $reference = require database_path('report_templates/departing_rooms_reference.php');
        $htmlMethod = new ReflectionMethod($reference, 'html');
        $html = $htmlMethod->invoke($reference);

        $data = [
            'parameters' => [
                'p_from_date' => '2026-08-27',
                'p_to_date' => '2026-08-27',
                'p_show_room_rate' => 0,
                'p_show_services_amount' => 0,
            ],
            'report' => ['generated_by' => 'Tester', 'generated_at' => '27/08/2026 12:00'],
            'summary' => ['row_count' => 1],
            'rows' => [[
                'DepartureDateGroup' => '27-08-2026',
                'CompanyId' => null,
                'Company' => null,
                'BookingId' => 10,
                'BookingName' => 'Walkin Guest',
                'RentalRoomId' => 'G10',
                'Room' => '101',
                'RoomTypeCode' => 'DLX',
                'SummaryRoomTypeCode' => 'DLX',
                'GuestName' => 'Khách thử nghiệm',
                'ArrivalDate' => '26/08/2026 - 14:00',
                'DepartureDate' => '27/08/2026 - 12:00',
                'RoomNight' => 1,
                'ExtraBed' => 0,
                'Adult' => 2,
                'Infant' => 0,
                'Child' => 1,
                'AdultInfantChild' => '2 / 0 / 1',
                'Rate' => 1000000,
                'AmountServices' => 50000,
                'Special' => 'BF',
                'Note' => 'Ghi chú',
                'RoomQuantity' => 1,
                'RoomTypePercent' => 100,
            ]],
        ];

        $rendered = app(TemplateRendererService::class)->render($html, '', $data);

        $this->assertStringContainsString('BÁO CÁO PHÒNG ĐI', $rendered);
        $this->assertStringContainsString('Công Ty: KHÁCH LẺ', $rendered);
        $this->assertStringContainsString('BẢNG KÊ CHI TIẾT THEO LOẠI PHÒNG', $rendered);
        $this->assertStringContainsString('rate-visible-0', $rendered);
        $this->assertStringContainsString('services-visible-0', $rendered);
        $this->assertStringContainsString('<td>DLX</td><td>1</td><td>1</td><td>2</td><td>0</td><td>1</td><td>100%</td>', $rendered);
    }
}
