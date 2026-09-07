<?php

namespace Tests\Feature;

use App\Services\TemplateRendererService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplementaryServicesReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_contains_sp2102_filters_and_report_definition(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_09_04_100000_create_supplementary_services_report.php'));

        foreach (['booking_room_services', 'service_date', 'p_from_date', 'p_to_date', 'p_service_codes', 'FIND_IN_SET', "service_code <> 'RM'", 'GroupQuantity', 'GroupTotal', "'control' => 'select'", 'deleted_at IS NULL', 'hotel-services', 'SUPPLEMENTARY_SERVICES_STANDARD'] as $text) {
            $this->assertStringContainsString($text, $migration);
        }
        $this->assertStringContainsString('SP2102', file_get_contents(database_path('migrations/2026_07_08_100001_create_booking_room_services_table.php')));
        $this->assertStringNotContainsString('ALTER TABLE bookings', $migration);
        $this->assertFileDoesNotExist(database_path('migrations/2026_09_04_110000_fix_supplementary_services_report_layout.php'));
        $this->assertFileDoesNotExist(database_path('migrations/2026_09_04_120000_set_supplementary_services_single_service.php'));
        $this->assertFileDoesNotExist(database_path('migrations/2026_09_04_130000_seed_supplementary_services_bands.php'));
    }

    public function test_reference_template_groups_by_service_and_renders_totals(): void
    {
        $html = file_get_contents(database_path('report_templates/supplementary_services_reference.php'));
        $this->assertStringContainsString('data-group-by="ServiceName"', $html);
        $this->assertStringContainsString('{{row.GroupQuantity|number}}', $html);
        $this->assertStringContainsString('{{row.GroupTotal|number}}', $html);
        $this->assertStringContainsString('colspan="4"', $html);
        $this->assertStringContainsString('private function contentJson()', $html);
        $this->assertStringContainsString('colspan="4"', $html);

        $data = [
            'parameters' => ['p_from_date' => '24/08/2026', 'p_to_date' => '24/08/2026'],
            'summary' => ['row_count' => 2],
            'rows' => [
            ['BookingCode' => 'GAL4264', 'Room' => '802', 'Guest' => 'ZOL', 'ServiceDate' => '24/08/2026', 'ServiceName' => 'Extra Person', 'Quantity' => 1, 'Rate' => 250000, 'Total' => 250000, 'GroupQuantity' => 2, 'GroupTotal' => 500000],
            ['BookingCode' => 'GAL5716', 'Room' => '1203', 'Guest' => 'OLGA', 'ServiceDate' => '24/08/2026', 'ServiceName' => 'Extra Person', 'Quantity' => 1, 'Rate' => 250000, 'Total' => 250000, 'GroupQuantity' => 2, 'GroupTotal' => 500000],
            ],
        ];
        $rendered = app(TemplateRendererService::class)->render('<table><tbody class="pms-grouped-rows" data-source="rows" data-group-by="ServiceName"><tr class="pms-group-header"><td>{{row.ServiceName}}</td></tr><tr class="pms-detail-row"><td>{{row.BookingCode}}</td></tr><tr class="pms-group-footer"><td>{{row.GroupTotal|number}}</td></tr></tbody></table>', '', $data);

        $this->assertStringContainsString('Extra Person', $rendered);
        $this->assertStringContainsString('500.000', $rendered);
        $this->assertStringNotContainsString('{{row.GroupTotal|number}}', $rendered);

    }

    public function test_configured_group_rows_support_group_and_detail_scopes(): void
    {
        $html = '<table><tbody class="pms-grouped-rows" data-source="rows" data-group-configured="1" data-group-by="ServiceName">'
            . '<tr class="pms-group-header" data-group-level="0" data-group-field="ServiceName"><td>{{row.ServiceName}}</td></tr>'
            . '<tr class="pms-detail-row"><td>{{row.BookingCode}}</td></tr>'
            . '<tr class="pms-detail-custom-row"><td>Chi tiết</td></tr>'
            . '<tr class="pms-group-custom-row" data-group-level="0"><td>{{row.GroupTotal|number}}</td></tr>'
            . '<tr class="pms-group-footer"><td>{{row.GroupTotal|number}}</td></tr>'
            . '</tbody></table>';

        $rendered = app(TemplateRendererService::class)->render($html, '', [
            'rows' => [
                ['ServiceName' => 'Extra Person', 'BookingCode' => 'GAL4264', 'GroupTotal' => 500000],
                ['ServiceName' => 'Extra Person', 'BookingCode' => 'GAL5716', 'GroupTotal' => 500000],
            ],
        ]);

        $this->assertSame(2, substr_count($rendered, 'Chi tiết'));
        $this->assertSame(1, substr_count($rendered, '500.000'));
    }
}
