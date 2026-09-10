<?php

namespace Tests\Feature;

use App\Services\TemplateRendererService;
use ReflectionMethod;
use Tests\TestCase;

class EarlyCheckoutRoomsReportTest extends TestCase
{
    public function test_migration_filters_rooms_by_actual_checkout_before_planned_departure(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_09_09_110000_create_early_checkout_rooms_report.php'));

        $this->assertStringContainsString('CREATE PROCEDURE rpt_early_checkout_rooms', $migration);
        $this->assertStringContainsString('br.CheckoutDate BETWEEN p_from_date AND p_to_date', $migration);
        $this->assertStringContainsString('br.CheckoutDate < br.planned_departure_date', $migration);
        $this->assertStringContainsString('br.planned_departure_date', $migration);
        $this->assertStringContainsString('br.status = 2', $migration);
        $this->assertStringContainsString('COALESCE(r.is_internal, 0) = 0', $migration);
        $this->assertStringContainsString("'EARLY_CHECKOUT_ROOMS_STANDARD'", $migration);
    }

    public function test_reference_template_contains_early_checkout_fields(): void
    {
        $reference = require database_path('report_templates/early_checkout_rooms_reference.php');
        $html = (new ReflectionMethod($reference, 'html'))->invoke($reference);
        $data = [
            'parameters' => ['p_from_date' => '2026-09-09', 'p_to_date' => '2026-09-09'],
            'report' => ['generated_by' => 'Tester', 'generated_at' => '09/09/2026 12:00:00'],
            'hotel' => ['logo' => '', 'address' => 'Nha Trang'],
            'rows' => [[
                'STT' => 1, 'RentalRoomId' => 1001, 'BookingId' => 265, 'CheckoutDateGroup' => '09/09/2026',
                'Room' => '1203', 'RoomType' => 'DLXOV', 'GuestName' => 'Nguyễn Văn A',
                'ArrivalDate' => '07/09/2026', 'PlannedDepartureDate' => '12/09/2026',
                'ActualCheckoutDate' => '09/09/2026', 'EarlyCheckoutDays' => 3,
                'CheckoutUser' => 'tester', 'Note' => '',
            ]],
        ];

        $rendered = app(TemplateRendererService::class)->render($html, '', $data);
        $this->assertStringContainsString('BÁO CÁO PHÒNG CHECKOUT SỚM', $rendered);
        $this->assertStringContainsString('12/09/2026', $rendered);
        $this->assertStringContainsString('09/09/2026', $rendered);
        $this->assertStringContainsString('>3</td>', $rendered);
        $this->assertStringNotContainsString('Tổng số phòng:', $rendered);
    }

    public function test_reference_template_contains_design_blocks(): void
    {
        $reference = require database_path('report_templates/early_checkout_rooms_reference.php');
        $blocks = (new ReflectionMethod($reference, 'blocks'))->invoke($reference);

        $this->assertCount(1, $blocks['header']);
        $this->assertSame('table', $blocks['detail'][0]['type']);
        $this->assertSame('rows', $blocks['detail'][0]['dataSource']);
        $this->assertSame('CheckoutDateGroup', $blocks['detail'][0]['groupBy']);
        $this->assertCount(11, $blocks['detail'][0]['columns']);
        $this->assertSame([], $blocks['footer']);
    }
}
