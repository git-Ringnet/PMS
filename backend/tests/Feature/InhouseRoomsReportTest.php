<?php

namespace Tests\Feature;

use App\Services\TemplateRendererService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class InhouseRoomsReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_contains_legacy_inhouse_rules(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_08_27_140000_create_inhouse_rooms_report.php'));

        $this->assertStringContainsString('system_date_rolls', $migration);
        $this->assertStringContainsString('room_night_bills', $migration);
        $this->assertStringContainsString("br.room_number NOT LIKE '0%'", $migration);
        $this->assertStringContainsString('is_internal', $migration);
        $this->assertStringContainsString('rs.is_availability = 1', $migration);
        $this->assertStringContainsString('p_actual TINYINT', $migration);
        $this->assertStringContainsString('p_vat TINYINT', $migration);
        $this->assertStringContainsString('p_no_vat TINYINT', $migration);
        $this->assertStringContainsString("br.status = 4 AND rd.has_room_night = 1", $migration);
        $this->assertStringContainsString('RoomTypePercent', $migration);
        $this->assertStringContainsString("'Adult','Infant','Child'", str_replace(' ', '', $migration));
    }

    public function test_accuracy_migration_counts_distinct_rooms_and_deduplicates_room_nights(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_08_27_170000_fix_room_report_data_accuracy.php'));

        $this->assertStringContainsString('COUNT(DISTINCT RentalRoomId)', $migration);
        $this->assertStringContainsString('WHERE IsMainGuest = 1', $migration);
        $this->assertStringContainsString('ORDER BY sb.Ma DESC', $migration);
        $this->assertStringNotContainsString('COUNT(*) OVER (PARTITION BY result.SummaryRoomTypeCode)', $migration);
    }

    public function test_reference_template_contains_legacy_layout_and_switchable_columns(): void
    {
        $reference = require database_path('report_templates/inhouse_rooms_reference.php');
        $method = new ReflectionMethod($reference, 'html');
        $html = $method->invoke($reference);
        $rendered = app(TemplateRendererService::class)->render($html, '', [
            'parameters' => ['p_from_date' => '2026-08-27', 'p_to_date' => '2026-08-27'],
            'report' => ['generated_by' => 'Tester', 'generated_at' => '27/08/2026 12:00'],
            'summary' => ['row_count' => 1],
            'rows' => [[
                'StayDateGroup' => '27-08-2026', 'CompanyId' => 1, 'BookingId' => 10,
                'Booking' => '10 - TEST', 'RentalRoomId' => 'R10', 'Room' => '101',
                'RoomTypeCode' => 'DLX', 'GuestName' => 'Khách thử',
                'ArrivalDate' => '26/08/2026 - 14:00', 'DepartureDate' => '28/08/2026 - 12:00',
                'RoomNight' => 2, 'AdultChild' => '2 / 0 / 1', 'Rate' => 1000000,
                'NoShowLate' => null, 'Special' => 'BF', 'Note' => 'Ghi chú',
                'SummaryRoomTypeCode' => 'DLX', 'RoomTypePercent' => 100,
            ]],
        ]);

        $this->assertStringContainsString('BÁO CÁO PHÒNG Ở', $rendered);
        $this->assertStringContainsString('BẢNG KÊ CHI TIẾT THEO LOẠI PHÒNG', $rendered);
        $this->assertStringContainsString('Không tới/', $rendered);
        $this->assertStringContainsString('27-08-2026', $rendered);
        $this->assertStringContainsString('<th>Người lớn</th>', $rendered);
        $this->assertStringContainsString('<th>Em bé</th>', $rendered);
        $this->assertStringContainsString('<th>Trẻ em</th>', $rendered);
        $this->assertStringContainsString('<td>100%</td>', $rendered);
    }
}
