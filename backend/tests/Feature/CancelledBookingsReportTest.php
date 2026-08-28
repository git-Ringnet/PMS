<?php

namespace Tests\Feature;

use App\Services\TemplateRendererService;
use ReflectionMethod;
use Tests\TestCase;

class CancelledBookingsReportTest extends TestCase
{
    public function test_migration_preserves_legacy_modes_and_new_report_rules(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_08_28_190000_create_cancelled_bookings_report.php'));

        $this->assertStringContainsString('CREATE PROCEDURE rpt_cancelled_bookings', $migration);
        $this->assertStringContainsString("l.cancel_type = 'booking'", $migration);
        $this->assertStringContainsString("l.cancel_type = 'room'", $migration);
        $this->assertStringContainsString("COALESCE(p_view_type, 'CancelDate') = 'ArrivalDate'", $migration);
        $this->assertStringContainsString('DATE(l.cancelled_at) BETWEEN p_from_date AND p_to_date', $migration);
        $this->assertStringContainsString('DATEDIFF(b.arrival_date, DATE(l.cancelled_at))', $migration);
        $this->assertStringContainsString('br.adults AS Adult', $migration);
        $this->assertStringContainsString('br.babies AS Baby', $migration);
        $this->assertStringContainsString('br.children_qty AS Child', $migration);
        $this->assertStringContainsString('COALESCE(r.is_internal, 0) = 0', $migration);
        $this->assertStringContainsString('NOT EXISTS (', $migration);
        $this->assertStringContainsString('eligible_physical_room.is_internal', $migration);
        $this->assertStringNotContainsString("room_number NOT LIKE '0%'", $migration);
        $this->assertStringNotContainsString('b.deleted_at IS NULL', $migration);
        $this->assertStringNotContainsString('br.deleted_at IS NULL', $migration);
        $this->assertStringContainsString('CANCELLED_BOOKINGS_STANDARD', $migration);
    }

    public function test_room_detail_correction_uses_all_rooms_from_cancelled_booking(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_08_28_191000_update_cancelled_bookings_room_details.php'));

        $this->assertStringContainsString("WHERE l.cancel_type = 'booking'", $migration);
        $this->assertStringContainsString('INNER JOIN booking_rooms AS br ON br.booking_id = b.id', $migration);
        $this->assertStringContainsString('FROM booking_rooms AS counted_room', $migration);
        $this->assertStringContainsString('COUNT(DISTINCT counted_room.id) AS RoomCount', $migration);
        $this->assertStringNotContainsString('br.id = l.booking_room_id', $migration);
        $this->assertStringNotContainsString("l.cancel_type = 'room'", $migration);
    }

    public function test_reference_template_supports_summary_and_room_detail_modes(): void
    {
        $reference = require database_path('report_templates/cancelled_bookings_reference.php');
        $html = (new ReflectionMethod($reference, 'html'))->invoke($reference);
        $css = (new ReflectionMethod($reference, 'css'))->invoke($reference);
        $blocks = (new ReflectionMethod($reference, 'blocks'))->invoke($reference);
        $data = [
            'parameters' => [
                'p_from_date' => '2026-08-01',
                'p_to_date' => '2026-08-25',
                'p_show_room_info' => 1,
            ],
            'report' => ['generated_by' => 'Tester', 'generated_at' => '28/08/2026 15:00:00'],
            'hotel' => ['logo' => '', 'address' => 'Nha Trang'],
            'summary' => ['row_count' => 1],
            'rows' => [[
                'PeriodGroup' => 1,
                'CancellationGroup' => '1|20260820155200|tester|By Anex',
                'CancellationId' => 10,
                'BookingId' => 2676,
                'BookingCode' => 'SL2676',
                'BookingName' => 'ZIMENS DENIS',
                'Company' => 'ANEX VN',
                'BookingDate' => '08/07/2026',
                'BookingArrivalDate' => '31/08/2026',
                'BookingDepartureDate' => '10/09/2026',
                'CancelDate' => '20/08/2026',
                'CancelTime' => '15:52',
                'CancelledBy' => 'kimtuyen',
                'CancelReason' => 'By Anex on 16.07.2026',
                'DaysCancelBefore' => 11,
                'RoomId' => 'BR-1',
                'Room' => '',
                'RoomType' => 'DLX-TPL',
                'RoomArrivalDate' => '31/08/2026',
                'RoomDepartureDate' => '10/09/2026',
                'Rate' => 770000,
                'Adult' => 2,
                'Baby' => 1,
                'Child' => 1,
                'RoomCount' => 1,
            ]],
        ];

        $rendered = app(TemplateRendererService::class)->render($html, $css, $data);

        $this->assertStringContainsString('BÁO CÁO HỦY ĐĂNG KÝ', $rendered);
        $this->assertStringContainsString('detail-visible-1', $rendered);
        $this->assertStringContainsString('SL2676', $rendered);
        $this->assertStringContainsString('DLX-TPL', $rendered);
        $this->assertStringContainsString('770.000', $rendered);
        $this->assertStringContainsString('Tổng phòng', $rendered);
        $this->assertSame('CancellationGroup', $blocks['detail'][1]['groupBy']);
        $this->assertSame('row.Room', $blocks['detail'][1]['columns'][0]['value']);
    }

    public function test_reference_template_displays_summary_when_checkbox_is_false(): void
    {
        $reference = require database_path('report_templates/cancelled_bookings_reference.php');
        $html = (new ReflectionMethod($reference, 'html'))->invoke($reference);
        $css = (new ReflectionMethod($reference, 'css'))->invoke($reference);
        $data = [
            'parameters' => [
                'p_from_date' => '2026-08-09',
                'p_to_date' => '2026-08-09',
                'p_show_room_info' => false,
            ],
            'report' => ['generated_by' => 'Tester', 'generated_at' => '28/08/2026 17:25:42'],
            'hotel' => ['logo' => '', 'address' => 'Nha Trang'],
            'rows' => [[
                'PeriodGroup' => 1,
                'BookingCode' => 'GAL2',
                'BookingName' => 'Walkin Guest',
                'Company' => 'Công ty TNHH Du lịch HKT',
                'BookingDate' => '09/08/2026',
                'BookingArrivalDate' => '09/08/2026',
                'BookingDepartureDate' => '12/08/2026',
                'CancelDate' => '09/08/2026',
                'CancelTime' => '17:01',
                'CancelledBy' => 'testuser',
                'CancelReason' => 'Khách tự hủy',
                'DaysCancelBefore' => 0,
                'RoomCount' => 4,
            ]],
        ];

        $rendered = app(TemplateRendererService::class)->render($html, $css, $data);

        $this->assertStringContainsString('summary-visible-', $rendered);
        $this->assertStringContainsString('.summary-visible-, .summary-visible-0', $rendered);
        $this->assertStringContainsString('GAL2', $rendered);
        $this->assertStringContainsString('Walkin Guest', $rendered);
        $this->assertStringContainsString('Tổng BK', $rendered);
    }
}
