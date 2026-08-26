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
}
