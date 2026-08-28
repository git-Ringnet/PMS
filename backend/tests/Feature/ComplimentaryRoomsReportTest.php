<?php

namespace Tests\Feature;

use App\Services\TemplateRendererService;
use ReflectionMethod;
use Tests\TestCase;

class ComplimentaryRoomsReportTest extends TestCase
{
    public function test_migration_contains_legacy_complimentary_rules(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_08_27_150000_create_complimentary_rooms_report.php'));

        $this->assertStringContainsString('rpt_complimentary_rooms', $migration);
        $this->assertStringContainsString("rd.room_rate = 0", $migration);
        $this->assertStringContainsString("LIKE 'FOC%'", $migration);
        $this->assertStringContainsString("p.payment_method_id) = 'CL'", $migration);
        $this->assertStringContainsString("name = 'TachFOC'", $migration);
        $this->assertStringContainsString('room_night_bills', $migration);
        $this->assertStringContainsString('r.is_internal', $migration);
        $this->assertStringContainsString("br.room_number NOT LIKE '0%'", $migration);
        $this->assertStringContainsString('p_room_rate_code', $migration);
    }

    public function test_accuracy_migration_applies_tach_foc_and_groups_daily_room_bills(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_08_27_170000_fix_room_report_data_accuracy.php'));

        $this->assertStringContainsString('v_tach_foc = 1', $migration);
        $this->assertStringContainsString('v_tach_foc <> 1', $migration);
        $this->assertStringContainsString('GROUP BY sb.RentalRoomId1, DATE(sb.Date)', $migration);
        $this->assertStringContainsString("THEN 'FOC OWNER'", $migration);
    }

    public function test_reference_template_contains_legacy_columns(): void
    {
        $reference = require database_path('report_templates/complimentary_rooms_reference.php');
        $html = (new ReflectionMethod($reference, 'html'))->invoke($reference);
        $rendered = app(TemplateRendererService::class)->render($html, '', [
            'parameters' => ['p_from_date' => '2026-08-27', 'p_to_date' => '2026-08-27'],
            'report' => ['generated_by' => 'Tester', 'generated_at' => '27/08/2026 12:00'],
            'rows' => [[
                'StayDateGroup' => '27-08-2026', 'BookingId' => 10, 'GuestName' => 'Test Guest', 'Room' => '101',
                'ArrivalDate' => '27/08/2026', 'DepartureDate' => '28/08/2026', 'Company' => 'Test Company',
                'RoomRateCode' => 'FOC', 'Note' => 'Compliment',
            ]],
        ]);

        $this->assertStringContainsString('BÁO CÁO PHÒNG MIỄN PHÍ', $rendered);
        $this->assertStringContainsString('Mã Giá Phòng', $rendered);
        $this->assertStringContainsString('FOC', $rendered);
        $this->assertStringContainsString('27-08-2026', $rendered);
    }
}
