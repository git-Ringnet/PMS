<?php

namespace Tests\Feature;

use App\Services\TemplateRendererService;
use Tests\TestCase;

class DayUseRoomsReportTest extends TestCase
{
    private function migration(): string
    {
        return file_get_contents(database_path('migrations/2026_09_07_160000_create_day_use_rooms_report.php'));
    }

    private function latestProcedureMigration(): string
    {
        return file_get_contents(database_path('migrations/2026_09_07_163000_fix_day_use_availability_and_sorting.php'));
    }

    public function test_report_preserves_sp_132_filters_and_separate_guest_counts(): void
    {
        $migration = $this->migration();

        foreach (['DAY_USE_ROOMS', 'rpt_day_use_rooms', 'br.is_day_use = 1', 'br.arrival_date = br.departure_date', 'br.adults', 'br.babies', 'br.children_qty', 'r.is_internal', 'br.room_number NOT LIKE \'0%\'', 'p_user', 'p_sort_by', 'p_sort_order', 'sp_132'] as $text) {
            $this->assertStringContainsString($text, $migration);
        }

        $this->assertStringNotContainsString('ALTER TABLE bookings', $migration);
        $this->assertStringNotContainsString('ALTER TABLE booking_rooms', $migration);
    }

    public function test_template_exposes_three_guest_columns_and_day_groups(): void
    {
        $template = require database_path('report_templates/day_use_rooms_reference.php');
        $html = $template->html();

        foreach (['BÁO CÁO PHÒNG Ở TRONG NGÀY (DAY USE)', 'Người lớn', 'Em bé', 'Trẻ em', 'ArrivalDateSort', 'group.sum.Baby'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
    }

    public function test_report_filters_available_registration_statuses_and_honors_sort_parameters(): void
    {
        $migration = $this->latestProcedureMigration();

        $this->assertStringContainsString('INNER JOIN registration_statuses rs ON rs.id = b.registration_status_id', $migration);
        $this->assertStringContainsString('rs.is_availability = 1', $migration);
        $this->assertGreaterThanOrEqual(8, substr_count($migration, "COALESCE(p_sort_by, 'Room')"));
        $this->assertGreaterThanOrEqual(8, substr_count($migration, "UPPER(COALESCE(p_sort_order, 'ASC'))"));
        $this->assertStringContainsString("= 'ArrivalDate'", $migration);
        $this->assertStringContainsString("= 'Room'", $migration);
    }

    public function test_template_renders_detail_rows_and_independent_totals(): void
    {
        $template = require database_path('report_templates/day_use_rooms_reference.php');
        $rendered = app(TemplateRendererService::class)->render($template->html(), '', [
            'hotel' => ['address' => 'Ha Noi'],
            'report' => ['generated_by' => 'tester', 'generated_at' => '07/09/2026'],
            'parameters' => ['p_from_date' => '01/09/2026', 'p_to_date' => '07/09/2026'],
            'rows' => [
                ['STT' => 1, 'BookingId' => 'GAL120', 'Company' => 'BINH DOAN 15', 'Room' => '802', 'RoomType' => 'FOV', 'ArrivalDate' => '02/09/2026 - 16:18', 'DepartureDate' => '02/09/2026 - 16:23', 'Adult' => 3, 'Baby' => 1, 'Child' => 2, 'Rate' => 500000, 'Note' => '', 'RentalRoomId' => 'BR120', 'ArrivalDateSort' => '2026-09-02', 'PeriodGroup' => 'all'],
            ],
        ]);

        foreach (['GAL120', 'BINH DOAN 15', '500.000', 'Người lớn', 'Em bé', 'Trẻ em'] as $text) {
            $this->assertStringContainsString($text, $rendered);
        }
        $this->assertStringNotContainsString('{{row.', $rendered);
        $this->assertStringNotContainsString('{{group.', $rendered);
    }
}
