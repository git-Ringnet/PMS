<?php

use Illuminate\Support\Facades\DB;

return new class
{
    public function apply(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::table('templates')
            ->where('report', 'ARRIVING_ROOMS_STANDARD')
            ->update([
                'name' => 'Báo cáo phòng đến - Mẫu tham chiếu',
                'page_size' => 'A4',
                'page_orientation' => 'portrait',
                'margin_top' => 6,
                'margin_bottom' => 6,
                'margin_left' => 5,
                'margin_right' => 5,
                'content_json' => json_encode($this->blocks(), JSON_UNESCAPED_UNICODE),
                'content_html' => $this->html(),
                'css' => $this->css(),
                'version' => '3.0',
                'updated_at' => now(),
            ]);
    }

    private function blocks(): array
    {
        return [
            'header' => [
                [
                    'id' => 'arrival_hotel_header',
                    'type' => 'text',
                    'content' => '<div class="hotel-header"><div class="hotel-logo">{{hotel.logo}}</div><div class="hotel-information"><div><b>Địa chỉ:</b><span>{{hotel.address}}</span></div><div><b>Người dùng:</b><span>{{report.generated_by}}</span><b class="date-label">Ngày:</b><span>{{report.generated_at}}</span></div></div></div>',
                    'style' => ['marginBottom' => '6px'],
                ],
                [
                    'id' => 'arrival_divider',
                    'type' => 'divider',
                    'content' => '<hr class="header-divider">',
                    'style' => ['marginBottom' => '6px'],
                ],
                [
                    'id' => 'arrival_title',
                    'type' => 'text',
                    'content' => '<h1>BÁO CÁO PHÒNG ĐẾN</h1>',
                    'style' => ['textAlign' => 'center', 'fontWeight' => 'bold', 'marginBottom' => '6px'],
                ],
                [
                    'id' => 'arrival_period',
                    'type' => 'text',
                    'content' => '<p class="report-period"><b>Ngày:</b><span>{{parameters.p_from_date}}</span><b>~</b><span>{{parameters.p_to_date}}</span></p>',
                    'style' => ['textAlign' => 'center', 'marginBottom' => '12px'],
                ],
            ],
            'detail' => [[
                'id' => 'arrival_grouped_table',
                'type' => 'table',
                'dataSource' => 'rows',
                'tableType' => 'dynamic',
                'tableStyle' => 'grid',
                'isNew' => false,
                'groupBy' => 'ArrivalDateGroup',
                'subgroupBy' => 'CompanyId',
                'subsubgroupBy' => 'BookingId',
                'groupHeader' => '<td colspan="13" class="date-row">Ngày: {{row.ArrivalDateGroup}}</td>',
                'subgroupHeader' => '<td colspan="13" class="company-row">Công Ty: {{row.Company|KHÁCH LẺ}}</td>',
                'subsubgroupHeader' => '<td colspan="13" class="booking-row">Đăng Ký: {{row.BookingId}} - {{row.Company|KHÁCH LẺ}} - {{row.Phone}} - Liên hệ:</td>',
                'subsubgroupNote' => '<td class="note-label">Ghi Chú:</td><td colspan="12" class="note-content">{{row.Note}}</td>',
                'subgroupFooter' => '<td class="group-total-label">Tổng Theo C.ty</td><td class="group-total-value">{{group.distinct.RentalRoomId}}</td><td colspan="8"></td><td class="group-total-value">{{group.sum.Adult}} / {{group.sum.Child}}</td><td colspan="2"></td>',
                'columns' => [
                    ['header' => 'Mã DK', 'value' => 'row.BookingId', 'width' => '8%', 'align' => 'left'],
                    ['header' => 'Phòng', 'value' => 'row.Room', 'width' => '6%', 'align' => 'center'],
                    ['header' => 'Loại Phòng', 'value' => 'row.RoomTypeCode', 'width' => '7%', 'align' => 'center'],
                    ['header' => 'Tên Khách', 'value' => 'row.GuestName', 'width' => '13%', 'align' => 'left'],
                    ['header' => 'Giới tính', 'value' => 'row.Gender', 'width' => '6%', 'align' => 'center'],
                    ['header' => 'Ngày Đến', 'value' => 'row.ArrivalDate', 'width' => '10%', 'align' => 'center'],
                    ['header' => 'Ngày Đi', 'value' => 'row.DepartureDate', 'width' => '10%', 'align' => 'center'],
                    ['header' => 'Số điện thoại', 'value' => 'row.Phone', 'width' => '9%', 'align' => 'center'],
                    ['header' => 'Số dư', 'value' => 'row.Balance', 'width' => '7%', 'align' => 'right'],
                    ['header' => 'Đêm', 'value' => 'row.RoomNight', 'width' => '5%', 'align' => 'center'],
                    ['header' => 'N.Lớn T.Em', 'value' => 'row.AdultChild', 'width' => '7%', 'align' => 'center'],
                    ['header' => 'Giá Phòng', 'value' => 'row.Rate', 'width' => '7%', 'align' => 'right'],
                    ['header' => 'Yêu Cầu Đặc Biệt', 'value' => 'row.Special', 'width' => '9%', 'align' => 'left'],
                ],
                'style' => ['marginTop' => '4px', 'marginBottom' => '4px'],
            ]],
            'footer' => [[
                'id' => 'arrival_grand_total',
                'type' => 'text',
                'content' => '<div class="grand-total"><b>Tổng số dòng:</b> {{summary.row_count}}</div>',
                'style' => ['textAlign' => 'right', 'fontWeight' => 'bold', 'marginTop' => '5px'],
            ]],
        ];
    }

    private function html(): string
    {
        return <<<'HTML'
<div class="report-header-band">
  <div id="arrival_hotel_header">
    <div class="hotel-header">
      <div class="hotel-logo">{{hotel.logo}}</div>
      <div class="hotel-information">
        <div><b>Địa chỉ:</b><span>{{hotel.address}}</span></div>
        <div>
          <b>Người dùng:</b><span>{{report.generated_by}}</span>
          <b class="date-label">Ngày:</b><span>{{report.generated_at}}</span>
        </div>
      </div>
    </div>
  </div>
  <hr class="header-divider">
  <div id="arrival_title"><h1>BÁO CÁO PHÒNG ĐẾN</h1></div>
  <div id="arrival_period"><p class="report-period"><b>Ngày:</b><span>{{parameters.p_from_date}}</span><b>~</b><span>{{parameters.p_to_date}}</span></p></div>
</div>
<div class="report-detail-band">
  <table class="arrival-report-table">
    <thead>
      <tr>
        <th>Mã DK</th>
        <th>Phòng</th>
        <th>Loại<br>Phòng</th>
        <th>Tên Khách</th>
        <th>Giới<br>tính</th>
        <th>Ngày Đến</th>
        <th>Ngày Đi</th>
        <th>Số điện<br>thoại</th>
        <th>Số dư</th>
        <th>Đêm</th>
        <th>N.Lớn<br>T.Em</th>
        <th>Giá<br>Phòng</th>
        <th>Yêu Cầu<br>Đặc Biệt</th>
      </tr>
    </thead>
    <tbody class="pms-grouped-rows" data-source="rows" data-group-by="ArrivalDateGroup" data-subgroup-by="CompanyId" data-subsubgroup-by="BookingId">
      <tr class="pms-group-header">
        <td colspan="13" class="date-row">Ngày: {{row.ArrivalDateGroup}}</td>
      </tr>
      <tr class="pms-subgroup-header">
        <td colspan="13" class="company-row">Công Ty: {{row.Company|KHÁCH LẺ}}</td>
      </tr>
      <tr class="pms-subsubgroup-header">
        <td colspan="13" class="booking-row">Đăng Ký: {{row.BookingId}} - {{row.Company|KHÁCH LẺ}} - {{row.Phone}} - Liên hệ:</td>
      </tr>
      <tr class="pms-subsubgroup-note">
        <td class="note-label">Ghi Chú:</td>
        <td colspan="12" class="note-content">{{row.Note}}</td>
      </tr>
      <tr class="pms-detail-row">
        <td class="green-text">{{row.BookingId}}</td>
        <td>{{row.Room}}</td>
        <td>{{row.RoomTypeCode}}</td>
        <td>{{row.GuestName}}</td>
        <td>{{row.Gender}}</td>
        <td>{{row.ArrivalDate}}</td>
        <td>{{row.DepartureDate}}</td>
        <td>{{row.Phone}}</td>
        <td>{{row.Balance}}</td>
        <td>{{row.RoomNight}}</td>
        <td>{{row.AdultChild}}</td>
        <td>{{row.Rate|number}}</td>
        <td>{{row.Special}}</td>
      </tr>
      <tr class="pms-subgroup-footer">
        <td class="group-total-label">Tổng Theo C.ty</td>
        <td class="group-total-value">{{group.distinct.RentalRoomId}}</td>
        <td colspan="8"></td>
        <td class="group-total-value">{{group.sum.Adult}} / {{group.sum.Child}}</td>
        <td colspan="2"></td>
      </tr>
    </tbody>
  </table>
</div>
<div class="report-footer-band">
  <div class="grand-total"><b>Tổng số dòng:</b> {{summary.row_count}}</div>
</div>
HTML;
    }

    private function css(): string
    {
        return <<<'CSS'
body { color: #111; font-family: Arial, Helvetica, sans-serif; font-size: 8.5px; }
.hotel-header { display: grid; grid-template-columns: 175px 1fr; align-items: center; min-height: 85px; }
.hotel-logo { display: flex; align-items: center; justify-content: flex-start; min-height: 75px; }
.hotel-logo-image { display: block; max-width: 115px; max-height: 75px; object-fit: contain; }
.hotel-logo-fallback { display: grid; place-items: center; width: 72px; height: 72px; transform: rotate(45deg); background: linear-gradient(135deg, #22a4d6, #0b6fa4); color: white; font-size: 15px; font-weight: 800; }
.hotel-information { font-size: 9px; line-height: 1.9; }
.hotel-information > div { display: grid; grid-template-columns: 82px minmax(220px, 1fr) 55px auto; column-gap: 8px; }
.hotel-information .date-label { text-align: right; }
.header-divider { margin: 0 0 7px; border: 0; border-top: 1.5px solid #000; }
h1 { margin: 0; text-align: center; font-size: 20px; line-height: 1.25; }
.report-period { display: flex; justify-content: center; gap: 26px; margin: 8px 0 16px; font-size: 9px; }
.arrival-report-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
.arrival-report-table th, .arrival-report-table td { border: 1px solid #ccc; padding: 4px 5px; line-height: 1.15; vertical-align: middle; overflow-wrap: anywhere; }
.arrival-report-table th { background: #d9e1ec; text-align: center; font-weight: 700; }
.arrival-report-table th:nth-child(1) { width: 8%; }.arrival-report-table th:nth-child(2) { width: 6%; }.arrival-report-table th:nth-child(3) { width: 7%; }.arrival-report-table th:nth-child(4) { width: 13%; }.arrival-report-table th:nth-child(5) { width: 6%; }.arrival-report-table th:nth-child(6), .arrival-report-table th:nth-child(7) { width: 10%; }.arrival-report-table th:nth-child(8) { width: 9%; }.arrival-report-table th:nth-child(9) { width: 7%; }.arrival-report-table th:nth-child(10) { width: 5%; }.arrival-report-table th:nth-child(11), .arrival-report-table th:nth-child(12) { width: 7%; }.arrival-report-table th:nth-child(13) { width: 9%; }
.arrival-report-table td:nth-child(2), .arrival-report-table td:nth-child(3), .arrival-report-table td:nth-child(5), .arrival-report-table td:nth-child(6), .arrival-report-table td:nth-child(7), .arrival-report-table td:nth-child(8), .arrival-report-table td:nth-child(9), .arrival-report-table td:nth-child(10), .arrival-report-table td:nth-child(11) { text-align: center; }
.arrival-report-table td:nth-child(12) { text-align: right; white-space: nowrap; }
.date-row { color: #851c1c; font-weight: bold; background: #fff; padding: 4px 6px !important; text-align: left; font-size: 9.5px; border-left-color: transparent !important; border-right-color: transparent !important; }
.company-row { font-weight: bold; color: #000; background: #fff; padding: 4px 6px !important; text-align: left; font-size: 9.5px; border-left-color: transparent !important; border-right-color: transparent !important; }
.booking-row { font-weight: bold; color: #000; background: #fff; padding: 4.5px 6px !important; text-align: left; font-size: 9px; border-left-color: transparent !important; border-right-color: transparent !important; }
.note-label { text-align: right; font-weight: bold; color: #000; background: #fff; vertical-align: top; }
.note-content { font-weight: bold; color: #000; background: #fff; text-align: left; white-space: pre-wrap; font-size: 9px; }
.green-text { color: #16a34a; font-weight: bold; text-align: left !important; }
.group-total-label, .group-total-value { background: #fff; font-weight: 700; }
.group-total-label { text-align: right; }.group-total-value { text-align: center; }
.grand-total { margin-top: 5px; text-align: right; }
@media print { thead { display: table-header-group; } tr { break-inside: avoid; } }
CSS;
    }
};
