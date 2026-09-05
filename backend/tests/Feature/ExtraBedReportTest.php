<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\ReportDefinitionController;
use App\Models\Template;
use App\Services\TemplateRendererService;
use Tests\TestCase;

class ExtraBedReportTest extends TestCase
{
    private function migration(): string
    {
        return file_get_contents(database_path('migrations/2026_09_05_100000_create_extra_bed_report.php'));
    }

    public function test_extra_bed_report_preserves_separate_posted_and_setup_sources(): void
    {
        $migration = $this->migration();

        foreach (['EXTRA_BED', 'rpt_extra_bed', 'p_from_date', 'p_to_date', 'p_booking_id', 'linked_eb', 'posted', 'setup', 'booking_room_services', 'service_bills', 'service_bill_details', "service_code = 'EB'", "ServiceId = 'EB'", 'UNION ALL'] as $text) {
            $this->assertStringContainsString($text, $migration);
        }

        $this->assertStringContainsString("brs.service_bill_id IS NULL", $migration);
        $this->assertStringContainsString('COALESCE(le.BookingRoomId, br2.id, br1.id)', $migration);
        $this->assertStringNotContainsString('ALTER TABLE bookings', $migration);
    }

    public function test_posted_extra_bed_uses_linked_or_bill_quantity_and_bill_amount(): void
    {
        $migration = $this->migration();

        $this->assertStringContainsString('COALESCE(NULLIF(le.Quantity, 0), NULLIF(sb.Quantity, 0), NULLIF(pd.Quantity, 0), 0)', $migration);
        $this->assertStringContainsString('COALESCE(sb.Amount, le.Total, pd.Total, 0) AS ExtraBedTotal', $migration);
        $this->assertStringContainsString('sb.Guest AS GuestSnapshot', $migration);
        $this->assertStringContainsString('COALESCE(sb.Amount, le.Total, pd.Total, 0) / COALESCE', $migration);
    }

    public function test_unposted_setup_starts_at_system_date_and_is_deduplicated(): void
    {
        $migration = $this->migration();

        $this->assertStringContainsString('DECLARE v_system_date DATE', $migration);
        $this->assertStringContainsString('brs.service_date >= GREATEST(p_from_date, v_system_date)', $migration);
        $this->assertStringContainsString('active_br.status IN (0, 1, 2)', $migration);
        $this->assertStringContainsString('COALESCE(brs.is_posted, 0) = 0', $migration);
        $this->assertStringContainsString('NOT EXISTS', $migration);
        $this->assertStringContainsString('COALESCE(existing_bill.RentalRoomId2, existing_bill.RentalRoomId1) = brs.booking_room_id', $migration);
    }

    public function test_posted_history_keeps_virtual_rooms_and_uses_nightly_room_rate(): void
    {
        $migration = $this->migration();

        $this->assertStringContainsString('room_night_bills AS rnb', $migration);
        $this->assertStringContainsString('COALESCE(prr.rate, srr.Rate, br.rate, 0) AS RoomRate', $migration);
        $this->assertStringContainsString("active_br.room_number IS NULL OR active_br.room_number NOT LIKE '0%'", $migration);
        $this->assertStringNotContainsString('rs_status.is_availability', $migration);
        $this->assertStringNotContainsString('room.is_internal', $migration);
    }

    public function test_service_date_is_normalized_and_has_iso_group_key(): void
    {
        $migration = $this->migration();

        $this->assertStringContainsString('DATE(sb.Date) AS ServiceDate', $migration);
        $this->assertStringContainsString("DATE_FORMAT(rs.ServiceDate, '%d/%m/%Y') AS ServiceDate", $migration);
        $this->assertStringContainsString("DATE_FORMAT(rs.ServiceDate, '%Y-%m-%d') AS ServiceDateSort", $migration);
    }

    public function test_extra_bed_template_contains_requested_columns_and_date_group(): void
    {
        $template = file_get_contents(database_path('report_templates/extra_bed_reference.php'));

        foreach (['BÁO CÁO EXTRA BED', 'BookingCode', 'BookingName', 'RoomType', 'ArrivalDate', 'DepartureDate', 'Adults', 'Babies', 'Children', 'RoomRate', 'ExtraBedQuantity', 'ExtraBedRate', 'ExtraBedTotal', 'ServiceDateSort', 'Tổng SL EB'] as $text) {
            $this->assertStringContainsString($text, $template);
        }

        $definition = require database_path('report_templates/extra_bed_reference.php');
        $method = new \ReflectionMethod($definition, 'contentJson');
        $content = $method->invoke($definition);

        $this->assertSame('ServiceDateSort', $content['detail'][0]['groups'][0]['field']);
        $this->assertCount(15, $content['detail'][0]['columns']);
    }

    public function test_existing_extra_bed_template_is_not_overwritten_by_migration(): void
    {
        $migration = $this->migration();

        $this->assertStringContainsString('$templateCreated = $templateId === null', $migration);
        $this->assertStringContainsString('if ($templateCreated)', $migration);
        $this->assertStringNotContainsString("updateOrInsert(['report' => self::TEMPLATE]", $migration);
    }

    public function test_report_template_summary_contains_saved_page_settings(): void
    {
        $template = new Template([
            'page_size' => 'A4',
            'page_orientation' => 'portrait',
            'margin_top' => 6,
            'margin_bottom' => 7,
            'margin_left' => 8,
            'margin_right' => 9,
            'parameter_defaults' => ['p_from_date' => '$today'],
        ]);
        $template->id = 16;

        $controller = (new \ReflectionClass(ReportDefinitionController::class))->newInstanceWithoutConstructor();
        $method = new \ReflectionMethod($controller, 'templateSummary');
        $summary = $method->invoke($controller, $template);

        $this->assertSame('portrait', $summary['page_orientation']);
        $this->assertSame(6, $summary['margin_top']);
        $this->assertSame(7, $summary['margin_bottom']);
        $this->assertSame(8, $summary['margin_left']);
        $this->assertSame(9, $summary['margin_right']);
        $this->assertSame(['p_from_date' => '$today'], $summary['parameter_defaults']);
    }

    public function test_extra_bed_template_renders_rows_groups_and_totals_without_placeholders(): void
    {
        $definition = require database_path('report_templates/extra_bed_reference.php');
        $method = new \ReflectionMethod($definition, 'html');
        $html = $method->invoke($definition);
        $rows = [
            ['BookingCode' => 'GAL101', 'BookingName' => 'DOAN A', 'Room' => '201', 'Guest' => 'NGUYEN A', 'RoomType' => 'Deluxe', 'ArrivalDate' => '05/09/2026', 'DepartureDate' => '07/09/2026', 'Adults' => 2, 'Babies' => 0, 'Children' => 1, 'RoomRate' => 1200000, 'ServiceDate' => '05/09/2026', 'ServiceDateSort' => '2026-09-05', 'ExtraBedQuantity' => 2, 'ExtraBedRate' => 300000, 'ExtraBedTotal' => 600000],
            ['BookingCode' => 'GAL102', 'BookingName' => 'LE B', 'Room' => '202', 'Guest' => 'LE B', 'RoomType' => 'Suite', 'ArrivalDate' => '06/09/2026', 'DepartureDate' => '08/09/2026', 'Adults' => 2, 'Babies' => 0, 'Children' => 0, 'RoomRate' => 1800000, 'ServiceDate' => '06/09/2026', 'ServiceDateSort' => '2026-09-06', 'ExtraBedQuantity' => 1, 'ExtraBedRate' => 350000, 'ExtraBedTotal' => 350000],
        ];

        $rendered = app(TemplateRendererService::class)->render($html, '', [
            'hotel' => ['address' => 'Ha Noi'],
            'report' => ['generated_by' => 'tester', 'generated_at' => '06/09/2026'],
            'parameters' => ['p_from_date' => '05/09/2026', 'p_to_date' => '06/09/2026'],
            'summary' => ['row_count' => 2],
            'rows' => $rows,
        ]);

        $this->assertStringContainsString('GAL101', $rendered);
        $this->assertStringContainsString('GAL102', $rendered);
        $this->assertStringContainsString('600.000', $rendered);
        $this->assertStringContainsString('950.000', $rendered);
        $this->assertSame(1, substr_count($rendered, 'Ngày: 05/09/2026'));
        $this->assertSame(1, substr_count($rendered, 'Ngày: 06/09/2026'));
        $this->assertStringNotContainsString('{{row.', $rendered);
        $this->assertStringNotContainsString('{{aggregate.', $rendered);
    }
}
