<?php

namespace Tests\Feature;

use App\Services\TemplateRendererService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoShowByDayReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_report_is_separate_from_existing_no_show_report(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_09_04_140000_create_no_show_by_day_report.php'));

        foreach (['NO_SHOW_BY_DAY', 'rpt_no_show_by_day', 'late_checkins', 'booking_room_services', 'room_night_bills', 'p_booking', 'p_division', 'sp_056', 'DateSortKey'] as $text) {
            $this->assertStringContainsString($text, $migration);
        }

        $this->assertStringContainsString("DATE_FORMAT(br.CheckoutDate, '%d/%m/%Y') AS CheckoutDate", $migration);
        $this->assertStringContainsString('charge.ChargeDate = DATE(lc.late_checkin_date)', $migration);
        $this->assertStringContainsString('COALESCE(charge.Total, billing.Total, 0) AS Total', $migration);
        $this->assertStringContainsString('COALESCE(p_type, 2) = 0 AND charge.BillId IS NOT NULL', $migration);
        $this->assertStringContainsString('COALESCE(p_type, 2) = 1 AND charge.BillId IS NULL', $migration);
        $this->assertStringContainsString("'control' => 'select', 'default' => '', 'required' => false, 'options_source' => 'bookings'", $migration);
        $this->assertStringContainsString("CONCAT(COALESCE(hs.prefix_booking_id, ''), b.id)", $migration);

        $this->assertStringContainsString("'NO_SHOW'", file_get_contents(database_path('migrations/2026_09_03_260000_create_no_show_report.php')));
        $this->assertStringContainsString('NO_SHOW_BY_DAY_STANDARD', file_get_contents(database_path('report_templates/no_show_by_day_reference.php')));
        $this->assertFileDoesNotExist(database_path('migrations/2026_09_04_150000_fix_no_show_by_day_legacy_accuracy.php'));
        $this->assertFileDoesNotExist(database_path('migrations/2026_09_04_160000_fix_no_show_by_day_posted_bill_amount.php'));
    }

    public function test_template_groups_by_room_type_then_late_checkin_date(): void
    {
        $template = require database_path('report_templates/no_show_by_day_reference.php');
        $reflection = new \ReflectionClass($template);
        $method = $reflection->getMethod('html');
        $method->setAccessible(true);
        $html = $method->invoke($template);

        $rendered = app(TemplateRendererService::class)->render($html, '', [
            'rows' => [
                ['RoomType' => 'Charge', 'DateSortKey' => '20260701', 'LateCheckInDate' => '01/07/2026', 'Room' => '502', 'BookingId' => 'GAL1', 'Total' => 750000],
                ['RoomType' => 'Charge', 'DateSortKey' => '20260701', 'LateCheckInDate' => '01/07/2026', 'Room' => '503', 'BookingId' => 'GAL2', 'Total' => 750000],
                ['RoomType' => 'No Charge', 'DateSortKey' => '20260702', 'LateCheckInDate' => '02/07/2026', 'Room' => '508', 'BookingId' => 'GAL3', 'Total' => 0],
            ],
        ]);

        $this->assertStringContainsString('Charge', $rendered);
        $this->assertStringContainsString('No Charge', $rendered);
        $this->assertStringContainsString('750.000', $rendered);
        $this->assertSame(1, substr_count($rendered, 'Ngày: 01/07/2026'));
        $this->assertSame(1, substr_count($rendered, 'Ngày: 02/07/2026'));
        $this->assertSame(3, substr_count($rendered, 'Tổng:'));
        $this->assertStringContainsString('class="total-value">2</td>', $rendered);
        $this->assertMatchesRegularExpression('/grand-total-row[^>]*>.*?<td[^>]*>3<\/td>/s', $rendered);
        $this->assertStringNotContainsString('{{row.RoomType}}', $rendered);
        $this->assertStringNotContainsString('{{group.count}}', $rendered);
        $this->assertStringNotContainsString('{{aggregate.rows.count}}', $rendered);
    }

    public function test_multi_branch_sort_supports_late_checkin_fields_without_changing_no_show_fields(): void
    {
        $controller = (new \ReflectionClass(\App\Http\Controllers\Api\ReportDefinitionController::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod($controller, 'sortNoShowRows');
        $method->setAccessible(true);

        $byDayRows = [
            ['LateCheckInDate' => '02/07/2026', 'LateCheckInTime' => '05:00', 'Room' => '502'],
            ['LateCheckInDate' => '01/07/2026', 'LateCheckInTime' => '06:00', 'Room' => '501'],
        ];
        $method->invokeArgs($controller, [&$byDayRows, 'ASC']);
        $this->assertSame('01/07/2026', $byDayRows[0]['LateCheckInDate']);

        $legacyRows = [
            ['NoshowDate' => '01/07/2026', 'NoshowTime' => '05:00', 'Room' => '501'],
            ['NoshowDate' => '02/07/2026', 'NoshowTime' => '06:00', 'Room' => '502'],
        ];
        $method->invokeArgs($controller, [&$legacyRows, 'DESC']);
        $this->assertSame('02/07/2026', $legacyRows[0]['NoshowDate']);
    }
}
