<?php

namespace Tests\Feature;

use Tests\TestCase;

class DailyFrontdeskReportTest extends TestCase
{
    private function template(): object
    {
        return require database_path('report_templates/daily_frontdesk_reference.php');
    }

    public function test_migration_uses_current_room_night_and_guest_tables(): void
    {
        $path = database_path('migrations/2026_09_18_120000_create_daily_frontdesk_report.php');
        $this->assertFileExists($path);
        $sql = file_get_contents($path);

        $this->assertStringContainsString('CREATE PROCEDURE rpt_daily_frontdesk', $sql);
        $this->assertStringContainsString('markets AS m', $sql);
        $this->assertStringContainsString('room_night_bills AS rnb', $sql);
        $this->assertStringContainsString('late_checkins AS lc', $sql);
        $this->assertStringContainsString('booking_room_guests AS g', $sql);
        $this->assertStringContainsString('booking_child_breakfast_details AS bfd', $sql);
        $this->assertStringContainsString('DATE_SUB(v_from, INTERVAL 1 DAY)', $sql);
        $this->assertStringContainsString('t.InhouseRoom = COALESCE(o.room_count, 0) - t.CheckoutRoom + t.RawDayUseRoom', $sql);
        $this->assertStringContainsString('DayUseRoom = CheckinRoom + InhouseRoom - RawDayUseRoom', $sql);
        $this->assertStringContainsString("'Khách TA'", $sql);
        $this->assertStringContainsString("'Khách OTA'", $sql);
        $this->assertStringContainsString("'Khách Corp'", $sql);
        $this->assertStringContainsString("'Khách Walk-in, FIT, Fanpage'", $sql);
        $this->assertStringContainsString('DAILY_FRONTDESK_REFERENCE', $sql);
    }

    public function test_design_sync_migration_persists_the_reference_layout(): void
    {
        $path = database_path('migrations/2026_09_18_150000_sync_all_four_reports_exact_design.php');
        $this->assertFileExists($path);
        $sql = file_get_contents($path);

        $this->assertStringContainsString("DAILY_FRONTDESK_REFERENCE", $sql);
        $this->assertStringContainsString("'content_json'", $sql);
        $this->assertStringContainsString("'content_html'", $sql);
        $this->assertStringContainsString("'margin_top'", $sql);
        $this->assertStringContainsString("'margin_left'", $sql);
    }

    public function test_template_matches_the_legacy_nine_column_daily_layout(): void
    {
        $definition = $this->template()->definition();
        $table = $this->template()->blocks()['detail'][0];

        $this->assertSame('A4', $definition['page_size']);
        $this->assertSame('landscape', $definition['page_orientation']);
        $this->assertSame(8, $definition['margin_top']);
        $this->assertSame(6, $definition['margin_right']);
        $this->assertSame(8, $definition['margin_bottom']);
        $this->assertSame(6, $definition['margin_left']);
        $this->assertCount(9, $table['columns']);
        $this->assertSame('DateFormatted', $table['grouping'][0]['field']);
        $this->assertSame('columns', $this->template()->blocks()['header'][0]['type']);
        $this->assertSame('30%', $this->template()->blocks()['header'][0]['columns'][0]['width']);
        $this->assertSame('70%', $this->template()->blocks()['header'][0]['columns'][1]['width']);
        $this->assertStringContainsString('background: #d9deea', $this->template()->css());
    }

    public function test_template_renders_four_segments_and_daily_totals(): void
    {
        $rows = [];
        foreach ([
            ['Khách TA', 1, 2, 3, 4, 5, 6],
            ['Khách OTA', 2, 3, 4, 5, 6, 7],
            ['Khách Corp', 3, 4, 5, 6, 7, 8],
            ['Khách Walk-in, FIT, Fanpage', 4, 5, 6, 7, 8, 9],
        ] as $index => $values) {
            $rows[] = [
                'Date' => '01/09/2026', 'DateFormatted' => '01/09/2026', 'SegmentOrder' => $index + 1,
                'Segment' => $values[0], 'CheckinRoom' => $values[1], 'CheckoutRoom' => $values[2],
                'InhouseRoom' => $values[3], 'DayUseRoom' => $values[4], 'BreakfastGuestNum' => $values[5],
                'NoBreakfastGuestNum' => $values[6], 'Notes' => '', 'CustomerFeedback' => '',
            ];
        }

        $rendered = $this->template()->render([
            'hotel' => ['name' => 'PMS Hotel', 'address' => 'Nha Trang', 'logo' => ''],
            'report' => ['generated_by' => 'Admin'],
            'parameters' => ['p_from_date' => '01-09-2026', 'p_to_date' => '01-09-2026'],
            'rows' => $rows,
        ]);

        $this->assertStringContainsString('BÁO CÁO LỄ TÂN HẰNG NGÀY', $rendered);
        $this->assertStringContainsString('01/09/2026', $rendered);
        $this->assertStringContainsString('Khách OTA', $rendered);
        $this->assertStringContainsString('Tổng', $rendered);
        $this->assertStringContainsString('class="report-grand-total-row"', $rendered);
        $this->assertStringContainsString('10', $rendered);
    }
}
