<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckedOutGuestsReportTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): string
    {
        return file_get_contents(database_path('migrations/2026_09_05_110000_create_checked_out_guests_report.php'));
    }

    public function test_report_matches_legacy_checkout_filters_and_sorting(): void
    {
        $migration = $this->migration();

        $this->assertStringContainsString('brg.status = 2', $migration);
        $this->assertStringContainsString('brg.actual_checkout_date BETWEEN p_from_date AND p_to_date', $migration);
        $this->assertStringContainsString('p_company_id', $migration);
        $this->assertStringContainsString('p_booking_id', $migration);
        $this->assertStringContainsString("p_order_by = 'ArrivalDate'", $migration);
        $this->assertStringContainsString("p_order_by = 'Room'", $migration);
        $this->assertStringContainsString("p_order_by = 'DepartureDate'", $migration);
        $this->assertStringContainsString("p_order_by = 'OpenTimeDi'", $migration);
    }

    public function test_report_preserves_legacy_vw035_fields_and_prefix(): void
    {
        $migration = $this->migration();

        foreach (['prefix_booking_id', 'BookingName', 'Guest', 'RoomType', 'Nationality', 'CheckoutDate', 'CheckoutTime', 'ExtraBed', 'IsMainGuest', 'BabyCotInRoom'] as $field) {
            $this->assertStringContainsString($field, $migration);
        }
        $this->assertStringContainsString('CONCAT(COALESCE(hs.prefix_booking_id, \'\'), b.id)', $migration);
    }

    public function test_template_exposes_checkout_guest_columns_and_no_placeholders_after_render(): void
    {
        $template = require database_path('report_templates/checked_out_guests_reference.php');
        $method = new \ReflectionMethod($template, 'html');
        $html = $method->invoke($template);

        foreach (['BÁO CÁO DANH SÁCH KHÁCH ĐÃ TRẢ PHÒNG', 'BookingId', 'Guest', 'CheckoutTime', 'Nationality', 'NoteMoi'] as $field) {
            $this->assertStringContainsString($field, $html);
        }
        $this->assertStringContainsString('data-source="rows"', $html);

        $rendered = app(\App\Services\TemplateRendererService::class)->render($html, '', [
            'hotel' => ['address' => 'Ha Noi'],
            'report' => ['generated_by' => 'tester', 'generated_at' => '06/09/2026'],
            'parameters' => ['p_from_date' => '05/09/2026', 'p_to_date' => '06/09/2026'],
            'summary' => ['row_count' => 1],
            'rows' => [[
                'BookingId' => 'GAL101', 'BookingName' => 'DOAN A', 'Room' => '201', 'RoomKind' => 'Double', 'RoomType' => 'DLX',
                'Guest' => 'NGUYEN A', 'Nationality' => 'VN', 'ArrivalDate' => '05/09/2026', 'DepartureDate' => '06/09/2026',
                'NumOfDays' => 1, 'CheckoutTime' => '12:00', 'UserCheckout' => 'tester', 'NoteMoi' => '', 'RentalRoomId' => 'R1',
            ]],
        ]);

        $this->assertStringContainsString('GAL101', $rendered);
        $this->assertStringNotContainsString('{{row.', $rendered);
    }

    public function test_designer_layout_matches_legacy_report_bands(): void
    {
        $template = require database_path('report_templates/checked_out_guests_reference.php');
        $method = new \ReflectionMethod($template, 'blocks');
        $blocks = $method->invoke($template);

        $this->assertSame('columns', $blocks['header'][0]['type']);
        $this->assertSame('hotel.logo', $blocks['header'][0]['columns'][0]['blocks'][0]['content']);
        $this->assertSame('divider', $blocks['header'][1]['type']);
        $this->assertStringContainsString('20px', $blocks['header'][2]['style']['fontSize']);

        $table = $blocks['detail'][0];
        $this->assertCount(13, $table['columns']);
        $this->assertSame('BookingId', $table['groups'][0]['field']);
        $this->assertStringContainsString('Tên Nhóm', $table['groups'][0]['label']);
        $this->assertSame(['detail', 'group'], array_column($table['customRows'], 'scope'));
        $this->assertSame('static-table', $blocks['footer'][0]['type']);
        $this->assertCount(4, $blocks['footer'][0]['rows']);
    }

    public function test_template_css_does_not_override_designer_spacing(): void
    {
        $template = require database_path('report_templates/checked_out_guests_reference.php');
        $method = new \ReflectionMethod($template, 'css');
        $css = $method->invoke($template);

        $this->assertStringNotContainsString('min-height:105px', $css);
        $this->assertStringNotContainsString('min-height:82px', $css);
        $this->assertStringNotContainsString('margin:8px 0 48px', $css);
        $this->assertStringNotContainsString('margin:0 0 34px', $css);
        $this->assertStringNotContainsString('margin:0 0 25px', $css);
        $this->assertStringContainsString('.report-header-band table,.report-detail-band table,.report-footer-band table{margin-top:0;margin-bottom:0}', $css);
    }
}
