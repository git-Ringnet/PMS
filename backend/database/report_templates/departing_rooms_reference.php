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
            ->where('report', 'DEPARTING_ROOMS_STANDARD')
            ->update([
                'name' => 'Báo cáo phòng đi - Mẫu tham chiếu legacy',
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
                [
                    'id' => 'departure_hotel_header',
                    'type' => 'text',
                    'content' => '<div class="hotel-header"><div class="hotel-logo">{{hotel.logo}}</div><div class="hotel-information"><div><b>Địa chỉ:</b><span>{{hotel.address}}</span></div><div><b>Người dùng:</b><span>{{report.generated_by}}</span><b class="date-label">Ngày:</b><span>{{report.generated_at}}</span></div></div></div>',
                    'style' => ['marginBottom' => '6px'],
                ],
                [
                    'id' => 'departure_divider',
                    'type' => 'divider',
                    'content' => '<hr class="header-divider">',
                    'style' => ['marginBottom' => '6px'],
                ],
                [
                    'id' => 'departure_title',
                    'type' => 'text',
                    'content' => '<h1>BÁO CÁO PHÒNG ĐI</h1>',
                    'style' => ['textAlign' => 'center', 'fontWeight' => 'bold', 'marginBottom' => '6px'],
                ],
                [
                    'id' => 'departure_period',
                    'type' => 'text',
                    'content' => '<p class="report-period"><b>Ngày:</b><span>{{parameters.p_from_date}}</span><b>~</b><span>{{parameters.p_to_date}}</span></p>',
                    'style' => ['textAlign' => 'center', 'marginBottom' => '12px'],
                ],
            ],
            'detail' => [
                [
                    'id' => 'departure_grouped_table',
                    'type' => 'table',
                    'dataSource' => 'rows',
                    'tableType' => 'dynamic',
                    'tableStyle' => 'grid',
                    'isNew' => false,
                    'groupBy' => 'DepartureDateGroup',
                    'subgroupBy' => 'CompanyId',
                    'subsubgroupBy' => 'BookingId',
                    'groupHeader' => '<td colspan="12" class="date-row">Ngày: {{row.DepartureDateGroup}}</td>',
                    'subgroupHeader' => '<td colspan="12" class="company-row">Công Ty: {{row.Company|KHÁCH LẺ}}</td>',
                    'subsubgroupHeader' => '<td colspan="12" class="booking-row">Đăng Ký: {{row.BookingId}} - {{row.BookingName}}</td>',
                    'subsubgroupNote' => '<td class="note-label">Notice:</td><td colspan="11" class="note-content">{{row.Note}}</td>',
                    'subgroupFooter' => '<td class="group-total-label">Tổng Theo C.ty</td><td class="group-total-value">{{group.distinct.RentalRoomId}}</td><td colspan="4"></td><td class="group-total-value">{{group.sum.RoomNight}}</td><td></td><td class="group-total-value">{{group.sum.Adult}} / {{group.sum.Infant}} / {{group.sum.Child}}</td><td colspan="3"></td>',
                    'columns' => $this->columns(),
                    'style' => ['marginTop' => '4px', 'marginBottom' => '4px'],
                ],
                [
                    'id' => 'departure_room_type_title',
                    'type' => 'text',
                    'content' => '<h2>BẢNG KÊ CHI TIẾT THEO LOẠI PHÒNG</h2>',
                    'style' => ['textAlign' => 'center', 'fontWeight' => 'bold', 'marginTop' => '34px', 'marginBottom' => '12px'],
                ],
                [
                    'id' => 'departure_room_type_summary',
                    'type' => 'table',
                    'dataSource' => 'rows',
                    'tableType' => 'dynamic',
                    'tableStyle' => 'grid',
                    'isNew' => false,
                    'groupBy' => 'SummaryRoomTypeCode',
                    'groupFooter' => '<td>{{row.SummaryRoomTypeCode}}</td><td>{{group.sum.RoomQuantity}}</td><td>{{group.sum.RoomNight}}</td><td>{{group.sum.Adult}}</td><td>{{group.sum.Infant}}</td><td>{{group.sum.Child}}</td><td>{{row.RoomTypePercent}}%</td>',
                    'columns' => [
                        ['header' => 'Loại Phòng', 'value' => 'row.SummaryRoomTypeCode', 'width' => '25%', 'align' => 'center'],
                        ['header' => 'Số lượng', 'value' => 'row.RoomQuantity', 'width' => '12%', 'align' => 'center'],
                        ['header' => 'Đêm', 'value' => 'row.RoomNight', 'width' => '12%', 'align' => 'center'],
                        ['header' => 'N.Lớn', 'value' => 'row.Adult', 'width' => '12%', 'align' => 'center'],
                        ['header' => 'E.Bé', 'value' => 'row.Infant', 'width' => '12%', 'align' => 'center'],
                        ['header' => 'T.Em', 'value' => 'row.Child', 'width' => '12%', 'align' => 'center'],
                        ['header' => 'Phần trăm', 'value' => 'row.RoomTypePercent', 'width' => '15%', 'align' => 'center'],
                    ],
                    'style' => ['marginTop' => '4px', 'marginBottom' => '4px'],
                ],
            ],
            'footer' => [[
                'id' => 'departure_grand_total',
                'type' => 'text',
                'content' => '<div class="grand-total"><b>Tổng số dòng:</b> {{summary.row_count}}</div>',
                'style' => ['textAlign' => 'right', 'fontWeight' => 'bold', 'marginTop' => '5px'],
            ]],
        ];
    }

    private function columns(): array
    {
        return [
            ['header' => 'Mã ĐK', 'value' => 'row.BookingId', 'width' => '7%', 'align' => 'left'],
            ['header' => 'Phòng', 'value' => 'row.Room', 'width' => '6%', 'align' => 'center'],
            ['header' => 'Loại Phòng', 'value' => 'row.RoomTypeCode', 'width' => '7%', 'align' => 'center'],
            ['header' => 'Tên Khách', 'value' => 'row.GuestName', 'width' => '15%', 'align' => 'left'],
            ['header' => 'Ngày Đến', 'value' => 'row.ArrivalDate', 'width' => '11%', 'align' => 'center'],
            ['header' => 'Ngày Đi', 'value' => 'row.DepartureDate', 'width' => '11%', 'align' => 'center'],
            ['header' => 'Đêm', 'value' => 'row.RoomNight', 'width' => '5%', 'align' => 'center'],
            ['header' => 'Thêm Giường', 'value' => 'row.ExtraBed', 'width' => '7%', 'align' => 'center'],
            ['header' => 'N.Lớn / E.Bé / T.Em', 'value' => 'row.AdultInfantChild', 'width' => '9%', 'align' => 'center'],
            ['header' => 'Giá Phòng', 'value' => 'row.Rate', 'width' => '7%', 'align' => 'right'],
            ['header' => 'Dịch Vụ', 'value' => 'row.AmountServices', 'width' => '7%', 'align' => 'right'],
            ['header' => 'Yêu Cầu Đặc Biệt', 'value' => 'row.Special', 'width' => '8%', 'align' => 'left'],
        ];
    }

    private function html(): string
    {
        return <<<'HTML'
<div class="report-header-band">
  <div class="hotel-header">
    <div class="hotel-logo">{{hotel.logo}}</div>
    <div class="hotel-information">
      <div><b>Địa chỉ:</b><span>{{hotel.address}}</span></div>
      <div><b>Người dùng:</b><span>{{report.generated_by}}</span><b class="date-label">Ngày:</b><span>{{report.generated_at}}</span></div>
    </div>
  </div>
  <hr class="header-divider">
  <h1>BÁO CÁO PHÒNG ĐI</h1>
  <p class="report-period"><b>Ngày:</b><span>{{parameters.p_from_date}}</span><b>~</b><span>{{parameters.p_to_date}}</span></p>
</div>
<div class="report-detail-band">
  <table class="departure-report-table">
    <thead><tr>
      <th>Mã ĐK</th><th>Phòng</th><th>Loại<br>Phòng</th><th>Tên Khách</th><th>Ngày Đến</th><th>Ngày Đi</th><th>Đêm</th><th>Thêm<br>Giường</th><th>N.Lớn<br>E.Bé / T.Em</th>
      <th class="rate-col rate-visible-{{parameters.p_show_room_rate}}">Giá<br>Phòng</th>
      <th class="services-col services-rate-{{parameters.p_show_room_rate}} services-visible-{{parameters.p_show_services_amount}}">Dịch<br>Vụ</th>
      <th>Yêu Cầu<br>Đặc Biệt</th>
    </tr></thead>
    <tbody class="pms-grouped-rows" data-source="rows" data-group-by="DepartureDateGroup" data-subgroup-by="CompanyId" data-subsubgroup-by="BookingId">
      <tr class="pms-group-header"><td colspan="12" class="date-row">Ngày: {{row.DepartureDateGroup}}</td></tr>
      <tr class="pms-subgroup-header"><td colspan="12" class="company-row">Công Ty: {{row.Company|KHÁCH LẺ}}</td></tr>
      <tr class="pms-subsubgroup-header"><td colspan="12" class="booking-row">Đăng Ký: {{row.BookingId}} - {{row.BookingName}}</td></tr>
      <tr class="pms-subsubgroup-note"><td class="note-label">Notice:</td><td colspan="11" class="note-content">{{row.Note}}</td></tr>
      <tr class="pms-detail-row">
        <td class="green-text">{{row.BookingId}}</td><td>{{row.Room}}</td><td>{{row.RoomTypeCode}}</td><td>{{row.GuestName}}</td><td>{{row.ArrivalDate}}</td><td>{{row.DepartureDate}}</td><td>{{row.RoomNight}}</td><td>{{row.ExtraBed}}</td><td>{{row.AdultInfantChild}}</td>
        <td class="rate-col rate-visible-{{parameters.p_show_room_rate}}">{{row.Rate|number}}</td>
        <td class="services-col services-rate-{{parameters.p_show_room_rate}} services-visible-{{parameters.p_show_services_amount}}">{{row.AmountServices|number}}</td>
        <td>{{row.Special}}</td>
      </tr>
      <tr class="pms-subgroup-footer"><td class="group-total-label">Tổng Theo C.ty</td><td class="group-total-value">{{group.distinct.RentalRoomId}}</td><td colspan="4"></td><td class="group-total-value">{{group.sum.RoomNight}}</td><td></td><td class="group-total-value">{{group.sum.Adult}} / {{group.sum.Infant}} / {{group.sum.Child}}</td><td colspan="3"></td></tr>
    </tbody>
  </table>

  <h2>BẢNG KÊ CHI TIẾT THEO LOẠI PHÒNG</h2>
  <table class="room-type-summary-table">
    <thead><tr><th>Loại Phòng</th><th>Số lượng</th><th>Đêm</th><th>N.Lớn</th><th>E.Bé</th><th>T.Em</th><th>Phần trăm</th></tr></thead>
    <tbody class="pms-grouped-rows" data-source="rows" data-group-by="SummaryRoomTypeCode">
      <tr class="pms-group-footer"><td>{{row.SummaryRoomTypeCode}}</td><td>{{group.sum.RoomQuantity}}</td><td>{{group.sum.RoomNight}}</td><td>{{group.sum.Adult}}</td><td>{{group.sum.Infant}}</td><td>{{group.sum.Child}}</td><td>{{row.RoomTypePercent}}%</td></tr>
    </tbody>
  </table>
</div>
<div class="report-footer-band"><div class="grand-total"><b>Tổng số dòng:</b> {{summary.row_count}}</div></div>
HTML;
    }

    private function css(): string
    {
        return <<<'CSS'
body { color: #111; font-family: Arial, Helvetica, sans-serif; font-size: 8px; }
.hotel-header { display: grid; grid-template-columns: 175px 1fr; align-items: center; min-height: 85px; }
.hotel-logo { display: flex; align-items: center; justify-content: flex-start; min-height: 75px; }
.hotel-logo-image { display: block; max-width: 115px; max-height: 75px; object-fit: contain; }
.hotel-logo-fallback { display: grid; place-items: center; width: 72px; height: 72px; transform: rotate(45deg); background: linear-gradient(135deg, #22a4d6, #0b6fa4); color: white; font-size: 15px; font-weight: 800; }
.hotel-information { font-size: 9px; line-height: 1.9; }
.hotel-information > div { display: grid; grid-template-columns: 82px minmax(220px, 1fr) 55px auto; column-gap: 8px; }
.hotel-information .date-label { text-align: right; }
.header-divider { margin: 0 0 7px; border: 0; border-top: 1.5px solid #000; }
h1 { margin: 0; text-align: center; font-size: 20px; line-height: 1.25; }
h2 { margin: 34px 0 12px; text-align: center; font-size: 15px; }
.report-period { display: flex; justify-content: center; gap: 26px; margin: 8px 0 16px; font-size: 9px; }
.departure-report-table, .room-type-summary-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
.departure-report-table th, .departure-report-table td, .room-type-summary-table th, .room-type-summary-table td { border: 1px solid #c7cdd6; padding: 4px 4px; line-height: 1.15; vertical-align: middle; overflow-wrap: anywhere; }
.departure-report-table th, .room-type-summary-table th { background: #d9e1ec; text-align: center; font-weight: 700; }
.departure-report-table td:nth-child(2), .departure-report-table td:nth-child(3), .departure-report-table td:nth-child(5), .departure-report-table td:nth-child(6), .departure-report-table td:nth-child(7), .departure-report-table td:nth-child(8), .departure-report-table td:nth-child(9) { text-align: center; }
.rate-col, .services-col { text-align: right; white-space: nowrap; }
.rate-visible-0, .services-rate-0, .services-visible-0 { display: none; }
.date-row { color: #b91c1c; font-weight: bold; background: #fff; text-align: left; font-size: 9.5px; border-left-color: transparent !important; border-right-color: transparent !important; }
.company-row, .booking-row { font-weight: bold; background: #fff; text-align: left; font-size: 9px; border-left-color: transparent !important; border-right-color: transparent !important; }
.note-label { text-align: right; font-weight: bold; background: #fff; vertical-align: top; }
.note-content { font-weight: bold; background: #fff; text-align: left; white-space: pre-wrap; font-size: 9px; }
.green-text { color: #16a34a; font-weight: bold; text-align: left !important; }
.group-total-label, .group-total-value { background: #fff; font-weight: 700; }
.group-total-label { text-align: right; }.group-total-value { text-align: center; }
.room-type-summary-table td { text-align: center; }
.grand-total { margin-top: 5px; text-align: right; }
@media print { thead { display: table-header-group; } tr { break-inside: avoid; } }
CSS;
    }
};
