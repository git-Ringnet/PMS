<?php

namespace Tests\Feature;

use App\Services\TemplateRendererService;
use Tests\TestCase;

class RoomForecastReportTest extends TestCase
{
    private function template(): object
    {
        return require database_path('report_templates/room_forecast_reference.php');
    }

    public function test_migration_contains_current_runtime_schema_and_full_output_contract(): void
    {
        $path = database_path('migrations/2026_09_24_100000_create_room_forecast_report.php');
        $this->assertFileExists($path);

        $sql = file_get_contents($path);
        $this->assertStringContainsString('CREATE PROCEDURE rpt_room_forecast', $sql);
        $this->assertStringContainsString('br.room_number', $sql);
        $this->assertStringContainsString('r.room_number', $sql);
        $this->assertStringContainsString('br.adults', $sql);
        $this->assertStringContainsString('br.children_qty', $sql);
        $this->assertStringContainsString('rl.start_date', $sql);
        $this->assertStringContainsString('rl.end_date', $sql);
        $this->assertStringContainsString('b.status NOT IN (3, 4)', $sql);
        $this->assertStringContainsString('p_include_breakfast', $sql);
        $this->assertStringContainsString('p_revenue_detail', $sql);

        foreach (['adults_qty', 'br.room_id', 'rl.start_time', 'rl.end_time'] as $legacyIdentifier) {
            $this->assertStringNotContainsString($legacyIdentifier, $sql);
        }
        $this->assertStringContainsString('IN p_branch VARCHAR(20)', $sql);

        $definition = $this->template()->definition();
        $this->assertCount(17, $definition['data_contract']['rows']);
        $this->assertSame([
            'Date', 'DepRooms', 'DepAdult', 'ArrRooms', 'ArrAdult', 'OccRooms', 'OccAdult',
            'HouseUse', 'FOCAll', 'RoomSales', 'Revenue', 'AvgRate', 'AvgRate2', 'RoomAvible',
            'PercentOccupancy', 'PercentOccupancy1', 'RevPAR',
        ], array_keys($definition['data_contract']['rows']));
    }

    public function test_template_defines_fo_17_columns_and_hk_13_columns_with_conditional_visibility(): void
    {
        $definition = $this->template()->definition();
        $blocks = $definition['content_json'];

        $this->assertSame('ROOM_FORECAST_REFERENCE', $definition['report']);
        $this->assertSame('A4', $definition['page_size']);
        $this->assertSame('landscape', $definition['page_orientation']);
        $this->assertSame(10, $definition['margin_top']);
        $this->assertSame(8, $definition['margin_bottom']);

        $this->assertCount(2, $blocks['detail']);
        $this->assertCount(17, $blocks['detail'][0]['columns']);
        $this->assertCount(13, $blocks['detail'][1]['columns']);
        $this->assertSame('parameters.p_revenue_detail', $blocks['detail'][0]['visibleWhen']);
        $this->assertSame('truthy', $blocks['detail'][0]['visibleWhenMode']);
        $this->assertSame('parameters.p_revenue_detail', $blocks['detail'][1]['visibleWhen']);
        $this->assertSame('falsy', $blocks['detail'][1]['visibleWhenMode']);

        $hkFields = array_map(
            static fn (array $column): string => (string) $column['field'],
            $blocks['detail'][1]['columns']
        );
        $this->assertSame([
            'Date', 'DepRooms', 'DepAdult', 'ArrRooms', 'ArrAdult', 'OccRooms', 'OccAdult',
            'HouseUse', 'FOCAll', 'RoomSales', 'RoomAvible', 'PercentOccupancy', 'PercentOccupancy1',
        ], $hkFields);

        $this->assertStringContainsString('pms-condition-end:room-forecast-fo', $definition['content_html']);
        $this->assertStringContainsString('pms-condition-end:room-forecast-hk', $definition['content_html']);
        $this->assertStringContainsString('Doanh Thu', $definition['content_html']);
        $this->assertStringContainsString('Công suất (w/o HU,FOC)', $definition['content_html']);
    }

    public function test_render_shows_all_financial_columns_for_fo_mode(): void
    {
        $html = $this->template()->render($this->sampleData(true));

        $this->assertStringContainsString('Doanh Thu', $html);
        $this->assertStringContainsString('Giá phòng TB (w/o HU)', $html);
        $this->assertStringContainsString('Giá phòng TB (w/o HU,FOC)', $html);
        $this->assertStringContainsString('DThu/Tổng phòng', $html);
        $this->assertStringContainsString('1.250.000', $html);
        $this->assertStringContainsString('50.00%', $html);
        $this->assertStringContainsString('23/09/2026', $html);
    }

    public function test_render_hides_financial_columns_for_hk_mode_but_keeps_operational_columns(): void
    {
        $html = $this->template()->render($this->sampleData(false));

        $this->assertStringNotContainsString('Doanh Thu', $html);
        $this->assertStringNotContainsString('Giá phòng TB (w/o HU)', $html);
        $this->assertStringNotContainsString('Giá phòng TB (w/o HU,FOC)', $html);
        $this->assertStringNotContainsString('DThu/Tổng phòng', $html);
        $this->assertStringContainsString('P.Ở', $html);
        $this->assertStringContainsString('Phòng có thể bán', $html);
        $this->assertStringContainsString('Công suất (w/o HU,FOC)', $html);
        $this->assertStringContainsString('23/09/2026', $html);
    }

    private function sampleData(bool $revenueDetail): array
    {
        return [
            'hotel' => [
                'name' => 'PMS Test Hotel',
                'address' => 'Test address',
                'logo' => '',
            ],
            'report' => [
                'generated_by' => 'Admin',
                'generated_at' => '23/09/2026',
            ],
            'parameters' => [
                'p_from_date' => '23/09/2026',
                'p_to_date' => '23/09/2026',
                'p_revenue_detail' => $revenueDetail ? 1 : 0,
                'p_branch' => '__current__',
                'p_include_breakfast' => 1,
            ],
            'rows' => [[
                'Date' => '23/09/2026',
                'DepRooms' => 1,
                'DepAdult' => 2,
                'ArrRooms' => 1,
                'ArrAdult' => 2,
                'OccRooms' => 2,
                'OccAdult' => 4,
                'HouseUse' => 0,
                'FOCAll' => 0,
                'RoomSales' => 2,
                'Revenue' => 1250000,
                'AvgRate' => 625000,
                'AvgRate2' => 625000,
                'RoomAvible' => 10,
                'PercentOccupancy' => '50.00',
                'PercentOccupancy1' => '50.00',
                'RevPAR' => 125000,
            ]],
        ];
    }
}
