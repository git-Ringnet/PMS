<?php

namespace Tests\Feature;

use App\Services\TemplateRendererService;
use ReflectionMethod;
use Tests\TestCase;

class TransportationReportTest extends TestCase
{
    public function test_migration_reads_shuttle_info_and_filters_by_date(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_09_09_150000_create_transportation_report.php'));

        $this->assertStringContainsString('CREATE PROCEDURE rpt_transportation', $migration);
        $this->assertStringContainsString('JSON_EXTRACT', $migration);
        $this->assertStringContainsString("].code", $migration);
        $this->assertStringContainsString('transport.transport_date BETWEEN p_from_date AND p_to_date', $migration);
        $this->assertStringContainsString('IN p_group_by_date TINYINT', $migration);
        $this->assertStringContainsString("IN ('Đón', 'đón', 'Don', 'don', 'arrival', 'arrive', '1')", $migration);
        $this->assertStringContainsString("'TRANSPORTATION_STANDARD'", $migration);
    }

    public function test_template_has_date_and_transport_groups(): void
    {
        $template = require database_path('report_templates/transportation_reference.php');
        $blocks = (new ReflectionMethod($template, 'blocks'))->invoke($template);
        $html = (new ReflectionMethod($template, 'html'))->invoke($template);

        $groups = $blocks['detail'][0]['groups'];
        $this->assertCount(4, $blocks['header']);
        $this->assertSame('columns', $blocks['header'][0]['type']);
        $this->assertSame('divider', $blocks['header'][1]['type']);
        $this->assertSame('text', $blocks['header'][2]['type']);
        $this->assertSame('text', $blocks['header'][3]['type']);
        $this->assertSame('DateGroup', $groups[0]['field']);
        $this->assertSame('IsArrival', $groups[1]['field']);
        $this->assertSame('DESC', $groups[1]['sort']);
        $this->assertCount(10, $blocks['detail'][0]['columns']);
        $this->assertStringContainsString('data-subgroup-by="IsArrival"', $html);
        $this->assertStringContainsString('data-group-enabled-by="parameters.p_group_by_date"', $html);
        $this->assertStringContainsString('{{row.TransportGroup}}', $html);
        $this->assertStringContainsString('Tổng:', $html);
    }

    public function test_template_renders_transport_fields(): void
    {
        $template = require database_path('report_templates/transportation_reference.php');
        $html = (new ReflectionMethod($template, 'html'))->invoke($template);
        $data = [
            'parameters' => ['p_from_date' => '2026-09-09', 'p_to_date' => '2026-09-10'],
            'report' => ['generated_by' => 'Tester', 'generated_at' => '09/09/2026 12:00:00'],
            'hotel' => ['logo' => '', 'address' => 'Nha Trang'],
            'rows' => [[
                'STT' => 1, 'DateGroup' => '09/09/2026', 'TransportGroup' => 'ĐÓN KHÁCH',
                'BookingId' => 'PMS1001', 'BookingName' => 'Nguyễn Văn A', 'ArrivalBy' => '7 Seater car',
                'CodeNumber' => 'VN123', 'TransportDate' => '09/09/2026', 'TransportTime' => '10:30',
                'Rate' => 250000, 'PickupDropoff' => 'Sân bay Cam Ranh', 'Note' => '',
            ]],
        ];

        $rendered = app(TemplateRendererService::class)->render($html, '', $data);
        $this->assertStringContainsString('BÁO CÁO ĐƯA ĐÓN KHÁCH', $rendered);
        $this->assertStringContainsString('VN123', $rendered);
        $this->assertStringContainsString('Sân bay Cam Ranh', $rendered);
    }
}
