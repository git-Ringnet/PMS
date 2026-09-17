<?php

namespace Tests\Unit\Reports;

use App\Models\ReportDefinition;
use App\Services\Reports\ReportDatasetEnricher;
use Tests\TestCase;

class ExpectedBreakfastReportTest extends TestCase
{
    public function test_migration_has_isolated_registration_and_legacy_contracts(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_09_15_130000_create_expected_breakfast_report.php'));

        foreach ([
            'EXPECTED_BREAKFAST',
            'rpt_expected_breakfast',
            'EXPECTED_BREAKFAST_SUMMARY_STANDARD',
            'EXPECTED_BREAKFAST_DETAIL_STANDARD',
            'sp_035',
            'sp_032',
            'p_from_date',
            'p_to_date',
            'p_show_type',
            'p_user',
            'p_sort_by',
            'p_sort_type',
            'p_late_checkin',
            'p_show_room_details',
            'p_group_by_booking',
            'PHÒNG Ở THẬT',
            'PHÒNG LATE CHECK IN',
            'br.status IN (0, 1, 2, 100)',
            'booking_children',
            'booking_child_breakfast_details',
            'booking_room_guests',
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $migration);
        }
    }

    public function test_summary_template_render_and_layout(): void
    {
        $template = require database_path('report_templates/expected_breakfast_summary_reference.php');
        $def = $template->definition();

        $this->assertSame('EXPECTED_BREAKFAST_1_REFERENCE', $def['report']);
        $this->assertSame('portrait', $def['page_orientation']);

        $html = $template->render([
            'hotel' => ['address' => 'Nha Trang, Khánh Hòa', 'logo' => ''],
            'report' => ['generated_by' => 'Admin', 'generated_at' => '15/09/2026'],
            'parameters' => ['p_from_date' => '09/09/2026', 'p_to_date' => '09/09/2026'],
            'rows' => [
                [
                    'DateGroup' => '09/09/2026',
                    'BookingCode' => 'BK1001',
                    'Room' => '101',
                    'Adults' => 2,
                    'Children' => 1,
                    'ChildrenNK' => 0,
                    'TotalPax' => 3,
                    'GuestName' => 'Nguyễn Văn A',
                    'CompanyName' => 'Agoda',
                    'Note' => 'Khách VIP',
                    'DateTotalAdults' => 2,
                    'DateTotalChildren' => 1,
                    'DateTotalChildrenNK' => 0,
                    'DateTotalPax' => 3,
                ],
            ],
            'country_summary' => [
                [
                    'Nationality' => 'Việt Nam',
                    'Quantity' => 3,
                    'Percentage' => '100.00%',
                ],
            ],
            'totals' => [
                'Adults' => 2,
                'Children' => 1,
                'ChildrenNK' => 0,
                'TotalPax' => 3,
                'CountryTotalPax' => 3,
            ],
        ]);

        $this->assertStringContainsString('BÁO CÁO DỰ KIẾN KHÁCH ĂN SÁNG', $html);
        $this->assertStringContainsString('101', $html);
        $this->assertStringContainsString('Nguyễn Văn A', $html);
        $this->assertStringContainsString('BK1001', $html);
        $this->assertStringContainsString('THỐNG KÊ KHÁCH THEO QUỐC GIA', $html);
        $this->assertStringContainsString('Việt Nam', $html);
        $this->assertStringContainsString('100.00%', $html);
        $this->assertStringNotContainsString('{{', $html);
    }

    public function test_detail_template_render_and_layout(): void
    {
        $template = require database_path('report_templates/expected_breakfast_detail_reference.php');
        $def = $template->definition();

        $this->assertSame('EXPECTED_BREAKFAST_2_REFERENCE', $def['report']);
        $this->assertSame('portrait', $def['page_orientation']);

        $html = $template->render([
            'hotel' => ['address' => 'Nha Trang, Khánh Hòa', 'logo' => ''],
            'report' => ['generated_by' => 'Admin', 'generated_at' => '15/09/2026'],
            'parameters' => ['p_from_date' => '09/09/2026', 'p_to_date' => '09/09/2026'],
            'rows' => [
                [
                    'DateGroup' => '09/09/2026',
                    'RoomType' => 'PHÒNG Ở THẬT',
                    'DetailRoom' => 'Phòng 101 - BK 1001 - Đoàn Thắng - Người lớn: 2 - Trẻ em: 1 - Trẻ em MP: 0 - Trẻ em KAS: 0',
                    'Room' => '101',
                    'GuestName' => 'Nguyễn Văn A',
                    'Nationality' => 'Việt Nam',
                    'ArrivalDate' => '08/09/2026',
                    'DepartureDate' => '10/09/2026',
                    'Note' => 'Không hút thuốc',
                ],
            ],
            'totals' => [],
        ]);

        $this->assertStringContainsString('BÁO CÁO DỰ KIẾN KHÁCH ĂN SÁNG', $html);
        $this->assertStringContainsString('PHÒNG Ở THẬT', $html);
        $this->assertStringContainsString('Phòng 101', $html);
        $this->assertStringContainsString('Nguyễn Văn A', $html);
        $this->assertStringContainsString('Việt Nam', $html);
        $this->assertStringNotContainsString('{{', $html);
    }

    public function test_dataset_enricher_calculates_country_summary(): void
    {
        $enricher = app(ReportDatasetEnricher::class);

        $reportDef = new ReportDefinition();
        $reportDef->code = 'EXPECTED_BREAKFAST';

        $data = [
            'rows' => [
                ['Nationality' => 'Việt Nam', 'TotalPax' => 3],
                ['Nationality' => 'Hàn Quốc', 'TotalPax' => 2],
                ['Nationality' => 'Việt Nam', 'TotalPax' => 1],
            ],
            'totals' => [],
        ];

        $enriched = $enricher->enrich($reportDef, $data);

        $this->assertArrayHasKey('country_summary', $enriched);
        $this->assertCount(2, $enriched['country_summary']);
        $this->assertSame('Việt Nam', $enriched['country_summary'][0]['Nationality']);
        $this->assertSame(4, $enriched['country_summary'][0]['Quantity']);
        $this->assertSame('66.67%', $enriched['country_summary'][0]['Percentage']);
        $this->assertSame(6, $enriched['totals']['CountryTotalPax']);
    }

    public function test_two_summary_forms_use_designer_bindings(): void
    {
        $army = require database_path('report_templates/expected_breakfast_army_summary_reference.php');
        $armyDefinition = $army->definition();
        $this->assertSame('EXPECTED_BREAKFAST_ARMY_SUMMARY', $armyDefinition['report']);

        $armyHtml = $army->render([
            'parameters' => ['p_from_date' => '10/08/2026', 'p_to_date' => '10/08/2026'],
            'rows' => [['BookingCode' => 'BK1', 'Room' => '101', 'Adults' => 2, 'Children' => 0, 'ChildrenNK' => 0, 'TotalPax' => 2, 'GuestName' => 'Khách A', 'CompanyName' => 'Công ty', 'Note' => '']],
            'country_summary' => [['Nationality' => 'VN', 'Quantity' => 2, 'Percentage' => '100.00%']],
        ]);
        $this->assertStringContainsString('2', $armyHtml);
        $this->assertStringNotContainsString('{{', $armyHtml);

        $dtx = require database_path('report_templates/expected_breakfast_dtx_summary_reference.php');
        $dtxDefinition = $dtx->definition();
        $columns = $dtxDefinition['content_json']['detail'][0]['columns'];
        $this->assertSame('EXPECTED_BREAKFAST_DTX_SUMMARY', $dtxDefinition['report']);
        $this->assertCount(10, $columns);
        $this->assertCount(2, $dtxDefinition['content_json']['detail'][0]['customRows']);
        $this->assertSame('row.BreakfastChildExtraAmount', $columns[7]['value']);

        $dateChildAmount = collect($dtxDefinition['content_json']['detail'][0]['customRows'][0]['cells'])
            ->firstWhere('id', 'dtx_date_child_amount');
        $this->assertSame(
            'row.DateTotalBreakfastChildExtraAmount',
            $dateChildAmount['binding'],
        );

        $totalChildAmount = collect($dtxDefinition['content_json']['detail'][0]['customRows'][1]['cells'])
            ->firstWhere('id', 'dtx_total_child_amount');
        $this->assertSame(
            'aggregate.rows.sum.BreakfastChildExtraAmount',
            $totalChildAmount['binding'],
        );
        $this->assertSame(
            'breakfast-summary-table dtx-breakfast-summary-table',
            $dtxDefinition['content_json']['detail'][0]['tableClassName'],
        );
        $this->assertStringContainsString('BreakfastTotalAmount', $dtxDefinition['content_html']);
    }

    public function test_aligned_migration_keeps_one_report_with_two_legacy_modes(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_09_16_100000_align_expected_breakfast_legacy_forms.php'));

        foreach (['rpt_expected_breakfast_1', 'rpt_expected_breakfast_2', 'rpt_expected_breakfast', 'p_show_room_details', 'room_night_bills', 'BreakfastAdultAmount', 'EXPECTED_BREAKFAST_DTX_SUMMARY'] as $fragment) {
            $this->assertStringContainsString($fragment, $migration);
        }

        $designerRepair = file_get_contents(database_path('migrations/2026_09_16_140000_repair_expected_breakfast_dtx_designer_rows.php'));
        $this->assertStringContainsString('content_json', $designerRepair);
        $this->assertStringContainsString('customRows', $designerRepair);

        $cleanup = file_get_contents(database_path('migrations/2026_09_16_150000_remove_duplicate_expected_breakfast_reports.php'));
        $this->assertStringContainsString('EXPECTED_BREAKFAST_1_STANDARD', $cleanup);
        $this->assertStringContainsString('EXPECTED_BREAKFAST_2_STANDARD', $cleanup);

        $designerDefaults = file_get_contents(database_path('migrations/2026_09_16_160000_sync_expected_breakfast_designer_parameters.php'));
        $this->assertStringContainsString("'p_show_room_details' => \$showRoomDetails", $designerDefaults);

        $designer = file_get_contents(base_path('../frontend/src/pages/config/components/hotel/TemplateEditorModal.vue'));
        $this->assertStringContainsString('parameter_schema', $designer);
        $this->assertStringContainsString("http.get('/report-data-sources'", $designer);
        $this->assertStringContainsString('http.get(`/templates/${props.templateId}`)', $designer);

        $childBreakfastFix = file_get_contents(database_path('migrations/2026_09_16_170000_fix_expected_breakfast_child_breakfast_and_designer_layout.php'));
        foreach (['booking_child_breakfast_details', 'DATE_SUB', 'is_extra_charge', 'asm_code', 'BreakfastChildExtraAmount', 'dtx-breakfast-summary-table'] as $fragment) {
            $this->assertStringContainsString($fragment, $childBreakfastFix);
        }

        $designerJsonRepair = file_get_contents(database_path('migrations/2026_09_16_171000_repair_expected_breakfast_designer_json_bindings.php'));
        foreach (['content_json', 'tableClassName', 'BreakfastChildExtraAmount', 'DateTotalBreakfastChildExtraAmount'] as $fragment) {
            $this->assertStringContainsString($fragment, $designerJsonRepair);
        }

        $this->assertStringContainsString('loadDataSources()', $designer);
    }

    public function test_reports_page_switches_template_on_show_details_toggle(): void
    {
        $vueFile = file_get_contents(base_path('../frontend/src/pages/reports/ReportsPage.vue'));

        $this->assertStringContainsString("!['EXPECTED_BREAKFAST', 'EXPECTED_BREAKFAST_1', 'EXPECTED_BREAKFAST_2'].includes(activeTab.value?.code)", $vueFile);
        $this->assertStringContainsString('p_show_room_details', $vueFile);
        $this->assertStringContainsString('selectedTemplateId', $vueFile);
    }
}
