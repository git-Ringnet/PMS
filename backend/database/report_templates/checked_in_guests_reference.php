<?php

use Illuminate\Support\Facades\DB;

return new class
{
    public function apply(): void
    {
        DB::table('templates')->where('report', 'CHECKED_IN_GUESTS_STANDARD')->update([
            'name' => 'Danh sách khách đã nhận phòng - Mẫu tham chiếu',
            'page_size' => 'A4',
            'page_orientation' => 'portrait',
            'margin_top' => 6,
            'margin_bottom' => 6,
            'margin_left' => 5,
            'margin_right' => 5,
            'content_json' => json_encode($this->blocks(), JSON_UNESCAPED_UNICODE),
            'content_html' => $this->html(),
            'css' => $this->css(),
            'version' => '1.0',
            'updated_at' => now(),
        ]);
    }

    private function blocks(): array
    {
        return [
            'header' => [
                ['id' => 'checked_in_title', 'type' => 'text', 'content' => '<h1>BÁO CÁO DANH SÁCH KHÁCH ĐÃ NHẬN PHÒNG</h1>'],
                ['id' => 'checked_in_period', 'type' => 'text', 'content' => '<p>Ngày: {{parameters.p_from_date}} &nbsp; ~ &nbsp; {{parameters.p_to_date}}</p>'],
            ],
            'detail' => [[
                'id' => 'checked_in_rows', 'type' => 'table', 'dataSource' => 'rows', 'tableType' => 'dynamic', 'tableStyle' => 'grid',
                'columns' => [
                    ['header' => 'Mã ĐK', 'value' => 'row.BookingId'], ['header' => 'Phòng', 'value' => 'row.Room'],
                    ['header' => 'Dạng phòng', 'value' => 'row.RoomTypeCode'], ['header' => 'Tên ĐK', 'value' => 'row.BookingName'],
                    ['header' => 'Tên khách', 'value' => 'row.GuestName'], ['header' => 'Ngày đến', 'value' => 'row.ActualArrivalDate'],
                    ['header' => 'Ngày đi', 'value' => 'row.DepartureDate'], ['header' => 'Số đêm', 'value' => 'row.RoomNight'],
                    ['header' => 'Quốc tịch', 'value' => 'row.Nationality'], ['header' => 'Ghi chú', 'value' => 'row.Note'],
                ],
            ]],
        ];
    }

    private function html(): string
    {
        return <<<'HTML'
<div class="report-header-band">
  <div class="hotel-meta"><b>Địa chỉ:</b> {{hotel.address}}<span><b>Người dùng:</b> {{report.generated_by}}</span><span><b>Ngày:</b> {{report.generated_at}}</span></div>
  <hr>
  <h1>BÁO CÁO DANH SÁCH KHÁCH ĐÃ NHẬN PHÒNG</h1>
  <p class="period"><b>Ngày:</b> {{parameters.p_from_date}} &nbsp; ~ &nbsp; {{parameters.p_to_date}}</p>
</div>
<table class="checked-in-table">
  <thead><tr><th>Mã ĐK</th><th>Phòng</th><th>Dạng<br>phòng</th><th>Tên ĐK</th><th>Tên khách</th><th>Ngày đến</th><th>Ngày đi</th><th>Số<br>đêm</th><th>Quốc tịch</th><th>Ghi chú</th></tr></thead>
  <tbody class="pms-grouped-rows" data-source="rows" data-group-by="BookingId">
    <tr class="pms-group-header"><td colspan="10" class="booking-header">Tên ĐK: {{row.BookingName}}</td></tr>
    <tr class="pms-subgroup-note"><td colspan="10" class="note-row">Note: {{row.Note}}</td></tr>
    <tr class="pms-detail-row"><td>{{row.BookingId}}</td><td>{{row.Room}}</td><td>{{row.RoomTypeCode}}</td><td>{{row.BookingName}}</td><td>{{row.GuestName}}</td><td>{{row.ActualArrivalDate}}</td><td>{{row.DepartureDate}}</td><td>{{row.RoomNight}}</td><td>{{row.Nationality}}</td><td>{{row.Note}}</td></tr>
    <tr class="pms-group-footer"><td colspan="2" class="total-label">Tổng số phòng</td><td>{{group.distinct.RentalRoomId}}</td><td colspan="2" class="total-label">Tổng số khách</td><td>{{group.count}}</td><td colspan="4"></td></tr>
  </tbody>
</table>
<p class="grand-total"><b>Tổng số dòng:</b> {{summary.row_count}}</p>
HTML;
    }

    private function css(): string
    {
        return <<<'CSS'
body { color: #111; font-family: Arial, Helvetica, sans-serif; font-size: 9px; }
h1 { margin: 10px 0 5px; text-align: center; font-size: 19px; }
.hotel-meta { display: flex; gap: 18px; justify-content: space-between; font-size: 8.5px; }
.hotel-meta span { margin-left: auto; }
.period { margin: 0 0 12px; text-align: center; font-weight: 700; }
.checked-in-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
.checked-in-table th, .checked-in-table td { border: 1px solid #c6cbd2; padding: 4px 5px; vertical-align: middle; overflow-wrap: anywhere; }
.checked-in-table th { background: #dce3ef; text-align: center; font-weight: 700; }
.checked-in-table td:nth-child(1), .checked-in-table td:nth-child(2), .checked-in-table td:nth-child(3), .checked-in-table td:nth-child(6), .checked-in-table td:nth-child(7), .checked-in-table td:nth-child(8), .checked-in-table td:nth-child(9) { text-align: center; }
.booking-header, .note-row { border-left-color: transparent !important; border-right-color: transparent !important; font-weight: 700; }
.note-row { font-weight: 400; }
.total-label { text-align: right; font-weight: 700; background: #f1f5f9; }
.grand-total { margin-top: 6px; text-align: right; font-weight: 700; }
@media print { thead { display: table-header-group; } tr { break-inside: avoid; } }
CSS;
    }
};
