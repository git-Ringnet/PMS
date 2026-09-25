<?php

namespace Tests\Feature;

use App\Services\TemplateRendererService;
use Tests\TestCase;

class WeeklyRoomReportTest extends TestCase
{
    private function template(): object
    {
        return require database_path('report_templates/weekly_room_report_reference.php');
    }

    public function test_migration_contains_weekly_contract_and_current_schema_rules(): void
    {
        $path = database_path('migrations/2026_09_24_110000_create_weekly_room_report.php');
        $this->assertFileExists($path);

        $sql = file_get_contents($path);
        $this->assertStringContainsString('CREATE PROCEDURE rpt_weekly_room_report', $sql);
        $this->assertStringContainsString('p_division VARCHAR(20)', $sql);
        $this->assertStringNotContainsString('p_branch', $sql);
        $this->assertStringContainsString('b.status NOT IN (3, 4)', $sql);
        $this->assertStringContainsString('locked_room.room_number = rl.room_number', $sql);
        $this->assertStringContainsString('booking_rooms AS br', $sql);
        $this->assertStringContainsString('rooms AS r', $sql);
        $this->assertStringContainsString('room_locks AS rl', $sql);
        $this->assertStringContainsString('COALESCE(NULLIF(hs.division, \'\'), \'\') AS Division', $sql);
        $this->assertStringContainsString('p_division = \'__current__\'', $sql);
        $this->assertStringContainsString("'value' => '__all__'", $sql);
    }

    public function test_template_defines_nine_columns_and_two_tier_header(): void
    {
        $definition = $this->template()->definition();
        $table = $definition['content_json']['detail'][0];

        $this->assertSame('WEEKLY_ROOM_REPORT', $definition['code']);
        $this->assertSame('A4', $definition['page_size']);
        $this->assertSame('portrait', $definition['page_orientation']);
        $this->assertTrue($table['hasTwoTierHeader']);
        $this->assertCount(2, $table['headerRows']);
        $this->assertCount(9, $table['columns']);
        $this->assertSame('CÔNG SUẤT (%)', $table['headerRows'][0][5]['content']);
        $this->assertSame('row.occupancy_rate', $table['columns'][8]['value']);
    }

    public function test_template_renders_seven_rows_and_weighted_weekly_total(): void
    {
        $rows = [];
        for ($day = 0; $day < 7; $day++) {
            $date = new \DateTimeImmutable('2026-07-13 +'.$day.' days');
            $rows[] = [
                'report_date' => $date->format('Y-m-d'),
                'report_date_display' => $date->format('d/m/Y'),
                'day_name' => ['Thứ Hai', 'Thứ Ba', 'Thứ Tư', 'Thứ Năm', 'Thứ Sáu', 'Thứ Bảy', 'Chủ Nhật'][$day],
                'arr_rooms' => 10 + $day,
                'arr_guests' => 20 + $day,
                'dep_rooms' => 5 + $day,
                'dep_guests' => 10 + $day,
                'occ_rooms' => 8 + $day,
                'occ_guests' => 16 + $day,
                'available_rooms' => 10,
                'occupancy_rate' => 80 + ($day * 1.0),
                'Division' => 'HKT1',
            ];
        }

        $data = [
            'hotel' => ['address' => 'Địa chỉ test', 'logo' => ''],
            'report' => ['generated_by' => 'Admin', 'generated_at' => '23/09/2026 10:00:00'],
            'parameters' => [
                'p_from_date' => '13/07/2026',
                'p_to_date' => '19/07/2026',
                'p_division_label' => 'HKT1',
                'p_weekly_occupancy_rate' => '110.00',
            ],
            'rows' => $rows,
        ];

        $template = $this->template();
        $html = (new TemplateRendererService())->render(
            $template->definition()['content_html'],
            $template->definition()['css'],
            $data
        );

        $this->assertSame(7, substr_count($html, 'class="date-cell"'));
        $this->assertStringContainsString('ĐẾN', $html);
        $this->assertStringContainsString('CÔNG SUẤT (%)', $html);
        $this->assertStringContainsString('110.00%', $html);
        $this->assertStringContainsString('13/07/2026', $html);
        $this->assertStringNotContainsString('{{', $html);
    }

    public function test_weekly_ui_uses_only_canonical_division_values(): void
    {
        $path = database_path('migrations/2026_09_24_110000_create_weekly_room_report.php');
        $sql = file_get_contents($path);

        $this->assertStringContainsString("'value' => '__current__'", $sql);
        $this->assertStringContainsString("'value' => '__all__'", $sql);
        $this->assertStringNotContainsString("'name' => 'p_branch'", $sql);
    }
}
