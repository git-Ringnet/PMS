<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InhouseGuestsReportTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): string
    {
        return file_get_contents(database_path('migrations/2026_09_05_190000_create_inhouse_guests_report.php'));
    }

    public function test_procedure_preserves_sp285_and_func054_room_night_rules(): void
    {
        $migration = $this->migration();

        foreach ([
            "sb.ServiceId = 'RM'",
            'rnb.is_room_night = 1',
            'sb.Edit = 0',
            'prn.stay_date < v_system_date',
            'br.status IN (1, 2, 100)',
            'br.status = 1',
            "br.room_number NOT LIKE '0%'",
            'DATE(br.CheckoutDate) = v_system_date',
            'DATE_SUB(v_system_date, INTERVAL 1 DAY)',
            'rs.is_availability = 1',
        ] as $rule) {
            $this->assertStringContainsString($rule, $migration);
        }

        $this->assertStringNotContainsString('br.status = 4', $migration);
        $this->assertStringNotContainsString('br.status = 0', $migration);
    }

    public function test_report_is_registered_without_changing_business_schema(): void
    {
        $migration = $this->migration();

        $this->assertStringContainsString('CREATE PROCEDURE rpt_inhouse_guests', $migration);
        $this->assertStringContainsString("private const SOURCE = 'INHOUSE_GUESTS'", $migration);
        $this->assertStringContainsString("private const TEMPLATE = 'INHOUSE_GUESTS_STANDARD'", $migration);
        $this->assertStringContainsString("'menu_locations' => json_encode(['reservation', 'frontdesk'])", $migration);
        $this->assertStringNotContainsString('Schema::create', $migration);
        $this->assertStringNotContainsString('Schema::table', $migration);
    }

    public function test_sidebar_matches_legacy_controls(): void
    {
        $migration = $this->migration();

        foreach ([
            'Xem theo nhóm',
            'Xem bởi đại lý du lịch',
            "'control' => 'date-range'",
            "'options_source' => 'bookings'",
            'Hiển thị đăng ký',
            'Hiển thị ghi chú',
        ] as $control) {
            $this->assertStringContainsString($control, $migration);
        }
    }

    public function test_template_matches_legacy_detail_and_summary_columns(): void
    {
        $template = require database_path('report_templates/inhouse_guests_reference.php');
        $htmlMethod = new \ReflectionMethod($template, 'html');
        $html = $htmlMethod->invoke($template);

        foreach ([
            'BÁO CÁO DANH SÁCH KHÁCH Ở',
            'Mã ĐK',
            'Dạng<br>Phòng',
            'Tên ĐK',
            'Tên Khách',
            'Số<br>Đêm',
            'Quốc Tịch',
            'Ghi Chú',
            'Phần Trăm Theo<br>Đếm Khách (%)',
            'Nam',
            'Nữ',
            'Khác',
        ] as $label) {
            $this->assertStringContainsString($label, $html);
        }

        $this->assertStringContainsString('data-group-field="StayDateGroup"', $html);
        $this->assertStringContainsString('data-group-enabled-by="parameters.p_view_agency"', $html);
        $this->assertStringContainsString('data-group-enabled-by="parameters.p_show_booking"', $html);
    }

    public function test_template_renders_detail_totals_and_one_row_per_nationality(): void
    {
        $template = require database_path('report_templates/inhouse_guests_reference.php');
        $htmlMethod = new \ReflectionMethod($template, 'html');
        $cssMethod = new \ReflectionMethod($template, 'css');

        $rows = [
            $this->row(['CustomerId' => 'G1', 'Guest' => 'Mr. Guest 1', 'Gender' => 'M', 'GenderMale' => 1, 'GenderFemale' => 0]),
            $this->row(['CustomerId' => 'G2', 'Guest' => 'Ms. Guest 2', 'Gender' => 'F', 'GenderMale' => 0, 'GenderFemale' => 1]),
        ];

        $rendered = app(\App\Services\TemplateRendererService::class)->render(
            $htmlMethod->invoke($template),
            $cssMethod->invoke($template),
            [
                'hotel' => ['address' => 'Nha Trang', 'logo' => ''],
                'report' => ['generated_by' => 'tester', 'generated_at' => '08/09/2026'],
                'parameters' => [
                    'p_from_date' => '08/09/2026',
                    'p_to_date' => '08/09/2026',
                    'p_view_agency' => 0,
                    'p_show_booking' => 0,
                ],
                'rows' => $rows,
            ]
        );

        $this->assertStringContainsString('GAL101', $rendered);
        $this->assertStringContainsString('Mr. Guest 1', $rendered);
        $this->assertStringContainsString('<b>2</b>', $rendered);
        $this->assertSame(1, substr_count($rendered, '<td>Vietnam</td><td>2</td><td>1</td><td>100.00%</td>'));
        $this->assertStringNotContainsString('{{row.', $rendered);
        $this->assertStringNotContainsString('{{aggregate.', $rendered);
    }

    public function test_designer_definition_contains_legacy_report_bands(): void
    {
        $template = require database_path('report_templates/inhouse_guests_reference.php');
        $blocksMethod = new \ReflectionMethod($template, 'blocks');
        $blocks = $blocksMethod->invoke($template);

        $this->assertSame('columns', $blocks['header'][0]['type']);
        $this->assertSame('hotel.logo', $blocks['header'][0]['columns'][0]['blocks'][0]['content']);
        $this->assertCount(10, $blocks['detail'][0]['columns']);
        $this->assertSame('StayDateGroup', $blocks['detail'][0]['groups'][0]['field']);
        $this->assertSame('parameters.p_view_agency', $blocks['detail'][0]['groups'][1]['enabledBy']);
        $this->assertSame('parameters.p_show_booking', $blocks['detail'][0]['groups'][2]['enabledBy']);
        $this->assertCount(7, $blocks['footer'][1]['columns']);
    }

    private function row(array $overrides = []): array
    {
        return array_merge([
            'StayDate' => '2026-09-08',
            'StayDateGroup' => '08-09-2026',
            'BookingId' => 'GAL101',
            'BookingGroupKey' => '101:Walkin Guest',
            'BookingName' => 'Walkin Guest',
            'RentalRoomId' => 'R101',
            'CustomerId' => 'G1',
            'Room' => '201',
            'RoomKind' => 'Double',
            'RoomType' => 'DLX',
            'Guest' => 'Mr. Guest 1',
            'ArrivalDate' => '07-09-2026',
            'DepartureDate' => '09-09-2026',
            'NumOfDays' => 2,
            'NationalityName' => 'Vietnam',
            'NationalityGuestCount' => 2,
            'NationalityRoomCount' => 1,
            'NationalityPercent' => '100.00',
            'NationalityMaleCount' => 1,
            'NationalityFemaleCount' => 1,
            'NationalityOtherCount' => 0,
            'Gender' => 'M',
            'GenderMale' => 1,
            'GenderFemale' => 0,
            'GenderOther' => 0,
            'AgencyGroupKey' => 'ALL',
            'AgencyLabel' => '',
            'Note' => '',
        ], $overrides);
    }
}
