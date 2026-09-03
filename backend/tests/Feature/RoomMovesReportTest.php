<?php

namespace Tests\Feature;

use App\Services\TemplateRendererService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class RoomMovesReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_preserves_legacy_room_move_filters_and_safe_sorting(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_09_03_100000_create_room_moves_report.php'));
        $this->assertStringContainsString('old_br.status = 100', $migration);
        $this->assertStringContainsString('new_br.arrival_date >= p_from_date', $migration);
        $this->assertStringContainsString('new_br.arrival_date < DATE_ADD(p_to_date, INTERVAL 1 DAY)', $migration);
        $this->assertStringContainsString('new_br.check_in_user LIKE CONCAT', $migration);
        $this->assertStringContainsString('old_br.move_room', $migration);
        $this->assertStringContainsString('ROW_NUMBER()', $migration);
        $this->assertStringContainsString('BookingCode1', $migration);
        $this->assertStringContainsString("p_sort_by IN ('Room', 'Room1')", $migration);
        $this->assertStringNotContainsString('PREPARE ', $migration);
    }

    public function test_reference_template_renders_legacy_room_move_columns(): void
    {
        $reference = require database_path('report_templates/room_moves_reference.php');
        $html = (new ReflectionMethod($reference, 'html'))->invoke($reference);
        $data = [
            'parameters' => ['p_from_date' => '2026-09-03', 'p_to_date' => '2026-09-03'],
            'summary' => ['row_count' => 1],
            'rows' => [[
                'STT' => 1, 'BookingCode' => 'GAL100', 'BookingCode1' => 'GAL100', 'Guest' => 'Nguyễn Văn A', 'ArrivalDate' => '01/09/2026', 'Room' => '101', 'RoomType' => 'Deluxe', 'Rate' => 1000000,
                'Room1' => '202', 'RoomType1' => 'Superior', 'Rate1' => 1200000, 'ArrivalDate1' => '03/09/2026', 'Username' => 'tester', 'Reason' => 'Khách yêu cầu',
            ]],
        ];
        $rendered = app(TemplateRendererService::class)->render($html, '', $data);
        $this->assertStringContainsString('BÁO CÁO CHUYỂN PHÒNG', $rendered);
        $this->assertStringContainsString('GAL100', $rendered);
        $this->assertStringContainsString('BK Chuyển', $rendered);
        $this->assertStringContainsString('Khách yêu cầu', $rendered);
        $this->assertStringContainsString('Tổng số lượt chuyển:', $rendered);
        $this->assertStringNotContainsString('{{row.Rate|number}}', $rendered);
        $this->assertStringNotContainsString('{{row.Rate1|number}}', $rendered);
    }
}
