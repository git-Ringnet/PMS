<?php

namespace Tests\Unit;

use App\Services\TemplateRendererService;
use Tests\TestCase;

class TemplateRendererServiceTest extends TestCase
{
    public function test_it_renders_store_rows_with_row_bindings(): void
    {
        $html = <<<'HTML'
<table><tbody><tr class="pms-detail-row" data-source="rows"><td>{{row.GuestName}}</td><td>{{row.RoomNumber}}</td></tr></tbody></table>
HTML;

        $rendered = app(TemplateRendererService::class)->render($html, '', [
            'rows' => [
                ['GuestName' => 'Nguyễn Văn A', 'RoomNumber' => '506'],
                ['GuestName' => 'Trần Thị B', 'RoomNumber' => '707'],
            ],
        ]);

        $this->assertStringContainsString('<td>Nguyễn Văn A</td><td>506</td>', $rendered);
        $this->assertStringContainsString('<td>Trần Thị B</td><td>707</td>', $rendered);
        $this->assertStringNotContainsString('{{row.', $rendered);
    }

    public function test_it_formats_numbers_in_ordinary_detail_rows(): void
    {
        $html = <<<'HTML'
<table><tbody><tr class="pms-detail-row" data-source="rows"><td>{{row.Rate|number}}</td></tr></tbody></table>
HTML;

        $rendered = app(TemplateRendererService::class)->render($html, '', [
            'rows' => [['Rate' => 1234567.89]],
        ]);

        $this->assertStringContainsString('<td>1.234.568</td>', $rendered);
        $this->assertStringNotContainsString('{{row.Rate|number}}', $rendered);
    }

    public function test_it_renders_grouped_report_rows_and_totals(): void
    {
        $html = <<<'HTML'
<table><tbody class="pms-grouped-rows" data-source="rows" data-group-by="CompanyId" data-subgroup-by="RentalRoomId">
<tr class="pms-group-header"><td>Company: {{row.Company|Khách lẻ}}</td></tr>
<tr class="pms-subgroup-header"><td>Booking: {{row.Booking}}</td></tr>
<tr class="pms-detail-row"><td>{{row.GuestName}}</td><td>{{row.Rate|number}}</td></tr>
<tr class="pms-group-footer"><td>Rooms: {{group.distinct.RentalRoomId}} / Adults: {{group.sum.Adult}}</td></tr>
</tbody></table>
HTML;

        $rendered = app(TemplateRendererService::class)->render($html, '', [
            'rows' => [
                ['CompanyId' => 1, 'Company' => 'AB TOUR', 'RentalRoomId' => 'R1', 'Booking' => '1001 - AB TOUR', 'GuestName' => 'Guest A', 'Rate' => 470000, 'Adult' => 2],
                ['CompanyId' => 1, 'Company' => 'AB TOUR', 'RentalRoomId' => 'R1', 'Booking' => '1001 - AB TOUR', 'GuestName' => 'Guest B', 'Rate' => null, 'Adult' => null],
                ['CompanyId' => 1, 'Company' => 'AB TOUR', 'RentalRoomId' => 'R2', 'Booking' => '1002 - AB TOUR', 'GuestName' => 'Guest C', 'Rate' => 650000, 'Adult' => 1],
            ],
        ]);

        $this->assertSame(1, substr_count($rendered, 'Company: AB TOUR'));
        $this->assertSame(2, substr_count($rendered, 'Booking:'));
        $this->assertStringContainsString('470.000', $rendered);
        $this->assertStringContainsString('Rooms: 2 / Adults: 3', $rendered);
    }

    public function test_it_renders_a_secondary_summary_data_source(): void
    {
        $html = <<<'HTML'
<table><tbody>
<tr class="pms-detail-row" data-source="room_type_summary"><td>{{item.room_type_code}}</td><td>{{item.qty}}</td><td>{{item.percentage}}</td></tr>
<tr><td>Tổng</td><td>{{room_type_summary_total.qty}}</td><td>{{room_type_summary_total.percentage}}</td></tr>
</tbody></table>
HTML;

        $rendered = app(TemplateRendererService::class)->render($html, '', [
            'room_type_summary' => [
                ['room_type_code' => 'SUPD', 'qty' => 2, 'percentage' => '66.67%'],
                ['room_type_code' => 'SUPT', 'qty' => 1, 'percentage' => '33.33%'],
            ],
            'room_type_summary_total' => ['qty' => 3, 'percentage' => '100%'],
        ]);

        $this->assertStringContainsString('<td>SUPD</td><td>2</td><td>66.67%</td>', $rendered);
        $this->assertStringContainsString('<td>SUPT</td><td>1</td><td>33.33%</td>', $rendered);
        $this->assertStringContainsString('<td>Tổng</td><td>3</td><td>100%</td>', $rendered);
    }

    public function test_it_renders_dynamic_group_levels_from_template_configuration(): void
    {
        $html = <<<'HTML'
<table><tbody class="pms-grouped-rows" data-source="rows" data-group-configured="1" data-group-by="Reason">
<tr class="pms-group-header" data-group-level="0" data-group-field="Reason" data-group-sort="ASC" data-group-enabled-by="parameters.group_reason"><td>Lý do: {{row.Reason}}</td></tr>
<tr class="pms-group-header" data-group-level="1" data-group-field="BookingCode" data-group-sort="DESC"><td>Mã ĐK: {{row.BookingCode}}</td></tr>
<tr class="pms-detail-row"><td>{{row.Room}}</td></tr>
</tbody></table>
HTML;
        $data = [
            'parameters' => ['group_reason' => false],
            'rows' => [
                ['Reason' => 'Khách đổi lịch', 'BookingCode' => 'GAL1', 'Room' => '101'],
                ['Reason' => 'Khách đổi lịch', 'BookingCode' => 'GAL2', 'Room' => '102'],
            ],
        ];

        $withoutReason = app(TemplateRendererService::class)->render($html, '', $data);
        $this->assertStringNotContainsString('Lý do:', $withoutReason);
        $this->assertSame(2, substr_count($withoutReason, 'Mã ĐK:'));
        $this->assertLessThan(strpos($withoutReason, 'Mã ĐK: GAL1'), strpos($withoutReason, 'Mã ĐK: GAL2'));

        $data['parameters']['group_reason'] = true;
        $withReason = app(TemplateRendererService::class)->render($html, '', $data);
        $this->assertSame(1, substr_count($withReason, 'Lý do: Khách đổi lịch'));
        $this->assertSame(2, substr_count($withReason, 'Mã ĐK:'));
    }

    public function test_it_renders_custom_table_row_aggregates_and_visibility(): void
    {
        $html = <<<'HTML'
<table><tfoot>
<tr class="pms-custom-row"><td>{{aggregate.rows.count}}</td><td>{{aggregate.rows.sum.Total|number}}</td><td>{{aggregate.rows.distinct_count.BookingId}}</td></tr>
<tr class="pms-custom-row" data-visible-by="parameters.show_note"><td>Ghi chú</td></tr>
</tfoot></table>
HTML;
        $data = [
            'parameters' => ['show_note' => false],
            'rows' => [
                ['BookingId' => 1, 'Total' => 100000],
                ['BookingId' => 1, 'Total' => 200000],
                ['BookingId' => 2, 'Total' => 300000],
            ],
        ];

        $rendered = app(TemplateRendererService::class)->render($html, '', $data);
        $this->assertStringContainsString('<td>3</td><td>600.000</td><td>2</td>', $rendered);
        $this->assertStringNotContainsString('Ghi chú', $rendered);

        $data['parameters']['show_note'] = true;
        $visible = app(TemplateRendererService::class)->render($html, '', $data);
        $this->assertStringContainsString('Ghi chú', $visible);
    }
}
