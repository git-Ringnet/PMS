<?php

namespace Tests\Feature;

use App\Models\HotelSetting;
use App\Services\TemplateRendererService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SharedReportLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_hotel_logo_preview_normalizes_relative_setup_path(): void
    {
        HotelSetting::create([
            'hotel_name' => 'Test Hotel',
            'logo_url' => 'uploads/hotel/logo.png',
        ]);

        $data = app(TemplateRendererService::class)->getMockData('Room');

        $this->assertStringContainsString('src="/uploads/hotel/logo.png"', $data['hotel']['logo']);
    }

    public function test_designer_band_tables_are_not_given_global_vertical_margin(): void
    {
        $renderer = app(TemplateRendererService::class);
        $method = new \ReflectionMethod($renderer, 'buildFullHtmlDocument');
        $html = $method->invoke($renderer, '<div class="report-detail-band"><table><tr><td>x</td></tr></table></div>', '');

        $this->assertStringContainsString('.report-header-band table, .report-detail-band table, .report-footer-band table', $html);
        $this->assertStringContainsString('margin-top: 0;', $html);
        $this->assertStringContainsString('margin-bottom: 0;', $html);
    }
}
