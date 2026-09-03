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
            ->where('report', 'CANCELLED_BOOKINGS_STANDARD')
            ->update([
                'name' => 'Báo cáo hủy đăng ký - Mẫu tham chiếu legacy',
                'page_size' => 'A4',
                'page_orientation' => 'landscape',
                'margin_top' => 8,
                'margin_bottom' => 8,
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
                    'id' => 'cancelled_booking_hotel',
                    'type' => 'text',
                    'content' => '<div class="hotel-header"><div class="hotel-logo">{{hotel.logo}}</div><div class="hotel-information"><div><b>Địa chỉ:</b> {{hotel.address}}</div><div><b>Nhân viên:</b> {{report.generated_by}} <b class="generated-date">Ngày:</b> {{report.generated_at}}</div></div></div>',
                    'style' => ['fontSize' => '9px', 'marginBottom' => '4px'],
                ],
                [
                    'id' => 'cancelled_booking_divider',
                    'type' => 'divider',
                    'content' => '<hr class="header-divider">',
                    'style' => ['marginBottom' => '5px'],
                ],
                [
                    'id' => 'cancelled_booking_title',
                    'type' => 'text',
                    'content' => '<h1>BÁO CÁO HỦY ĐĂNG KÝ</h1>',
                    'style' => ['textAlign' => 'center', 'fontSize' => '20px', 'fontWeight' => 'bold', 'marginBottom' => '5px'],
                ],
                [
                    'id' => 'cancelled_booking_period',
                    'type' => 'text',
                    'content' => '<p class="report-period"><b>Ngày:</b><span>{{parameters.p_from_date}}</span><b>~</b><span>{{parameters.p_to_date}}</span></p>',
                    'style' => ['textAlign' => 'center', 'fontSize' => '9px', 'marginBottom' => '10px'],
                ],
            ],
            'detail' => [
                [
                    'id' => 'cancelled_booking_summary_table',
                    'type' => 'table',
                    'dataSource' => 'rows',
                    'tableType' => 'dynamic',
                    'tableStyle' => 'grid',
                    'isNew' => false,
                    'visibleWhen' => ['parameter' => 'p_show_room_info', 'equals' => false],
                    'columns' => $this->summaryColumns(),
                    'style' => ['fontSize' => '8px', 'marginTop' => '4px', 'marginBottom' => '0px'],
                ],
                [
                    'id' => 'cancelled_booking_room_table',
                    'type' => 'table',
                    'dataSource' => 'rows',
                    'tableType' => 'dynamic',
                    'tableStyle' => 'grid',
                    'isNew' => false,
                    'visibleWhen' => ['parameter' => 'p_show_room_info', 'equals' => true],
                    'groupBy' => 'CancellationGroup',
                    'columns' => $this->roomColumns(),
                    'style' => ['fontSize' => '8px', 'marginTop' => '4px', 'marginBottom' => '0px'],
                ],
            ],
            'footer' => [],
        ];
    }

    private function summaryColumns(): array
    {
        return [
            ['header' => 'Mã ĐK', 'value' => 'row.BookingCode', 'width' => '7%', 'align' => 'center'],
            ['header' => 'Tên ĐK', 'value' => 'row.BookingName', 'width' => '13%', 'align' => 'left'],
            ['header' => 'Công Ty', 'value' => 'row.Company', 'width' => '10%', 'align' => 'left'],
            ['header' => 'Ngày Tạo', 'value' => 'row.BookingDate', 'width' => '8%', 'align' => 'center'],
            ['header' => 'Ngày Đến', 'value' => 'row.BookingArrivalDate', 'width' => '8%', 'align' => 'center'],
            ['header' => 'Ngày Đi', 'value' => 'row.BookingDepartureDate', 'width' => '8%', 'align' => 'center'],
            ['header' => 'Ngày Hủy', 'value' => 'row.CancelDate', 'width' => '8%', 'align' => 'center'],
            ['header' => 'Giờ', 'value' => 'row.CancelTime', 'width' => '6%', 'align' => 'center'],
            ['header' => 'Người Hủy', 'value' => 'row.CancelledBy', 'width' => '9%', 'align' => 'center'],
            ['header' => 'Lý Do', 'value' => 'row.CancelReason', 'width' => '16%', 'align' => 'left'],
            ['header' => 'Hủy Trước', 'value' => 'row.DaysCancelBefore', 'width' => '7%', 'align' => 'center'],
        ];
    }

    private function roomColumns(): array
    {
        return [
            ['header' => 'Phòng', 'value' => 'row.Room', 'width' => '15%', 'align' => 'center'],
            ['header' => 'Loại Phòng', 'value' => 'row.RoomType', 'width' => '20%', 'align' => 'center'],
            ['header' => 'Ngày Đến', 'value' => 'row.RoomArrivalDate', 'width' => '20%', 'align' => 'center'],
            ['header' => 'Ngày Đi', 'value' => 'row.RoomDepartureDate', 'width' => '20%', 'align' => 'center'],
            ['header' => 'Giá', 'value' => 'row.Rate', 'width' => '25%', 'align' => 'right'],
        ];
    }

    private function html(): string
    {
        return <<<'HTML'
<div class="report-header-band">
  <div class="hotel-header"><div class="hotel-logo">{{hotel.logo}}</div><div class="hotel-information"><div><b>Địa chỉ:</b> {{hotel.address}}</div><div><b>Nhân viên:</b> {{report.generated_by}} <b class="generated-date">Ngày:</b> {{report.generated_at}}</div></div></div>
  <hr class="header-divider">
  <h1>BÁO CÁO HỦY ĐĂNG KÝ</h1>
  <p class="report-period"><b>Ngày:</b><span>{{parameters.p_from_date}}</span><b>~</b><span>{{parameters.p_to_date}}</span></p>
</div>
<div class="report-detail-band">
  <div class="summary-section summary-visible-{{parameters.p_show_room_info}}">
    <table class="cancel-summary-table">
      <colgroup><col style="width:7%"><col style="width:13%"><col style="width:10%"><col style="width:8%"><col style="width:8%"><col style="width:8%"><col style="width:8%"><col style="width:6%"><col style="width:9%"><col style="width:16%"><col style="width:7%"></colgroup>
      <thead><tr><th>Mã ĐK</th><th>Tên ĐK</th><th>Công Ty</th><th>Ngày Tạo</th><th>Ngày Đến</th><th>Ngày Đi</th><th>Ngày Hủy</th><th>Giờ</th><th>Người Hủy</th><th>Lý Do</th><th>Hủy<br>Trước</th></tr></thead>
      <tbody class="pms-grouped-rows" data-source="rows" data-group-by="PeriodGroup">
        <tr class="pms-detail-row"><td>{{row.BookingCode}}</td><td>{{row.BookingName}}</td><td>{{row.Company}}</td><td>{{row.BookingDate}}</td><td>{{row.BookingArrivalDate}}</td><td>{{row.BookingDepartureDate}}</td><td>{{row.CancelDate}}</td><td>{{row.CancelTime}}</td><td>{{row.CancelledBy}}</td><td>{{row.CancelReason}}</td><td>{{row.DaysCancelBefore}}</td></tr>
        <tr class="pms-group-footer"><td class="total-label">Tổng BK</td><td class="total-value">{{group.count}}</td><td colspan="7"></td><td class="total-label">Tổng phòng</td><td class="total-value">{{group.sum.RoomCount}}</td></tr>
      </tbody>
    </table>
  </div>

  <div class="detail-section detail-visible-{{parameters.p_show_room_info}}">
    <table class="cancel-room-table">
      <colgroup><col style="width:15%"><col style="width:20%"><col style="width:20%"><col style="width:20%"><col style="width:25%"></colgroup>
      <thead><tr><th>Phòng</th><th>Loại Phòng</th><th>Ngày Đến</th><th>Ngày Đi</th><th>Giá</th></tr></thead>
      <tbody class="pms-grouped-rows" data-source="rows" data-group-by="CancellationGroup">
        <tr class="pms-group-header"><td colspan="5" class="booking-group-cell"><div class="booking-grid"><div><b>Mã ĐK</b><span>{{row.BookingCode}}</span></div><div><b>Tên ĐK</b><span>{{row.BookingName}}</span></div><div><b>Công Ty</b><span>{{row.Company}}</span></div><div><b>Ngày Tạo</b><span>{{row.BookingDate}}</span></div><div><b>Ngày Đến</b><span>{{row.BookingArrivalDate}}</span></div><div><b>Ngày Đi</b><span>{{row.BookingDepartureDate}}</span></div><div><b>Ngày Hủy</b><span>{{row.CancelDate}}</span></div><div><b>Giờ</b><span>{{row.CancelTime}}</span></div><div><b>Người Hủy</b><span>{{row.CancelledBy}}</span></div><div><b>Lý Do</b><span>{{row.CancelReason}}</span></div><div><b>Hủy Trước</b><span>{{row.DaysCancelBefore}}</span></div></div></td></tr>
        <tr class="pms-detail-row"><td>{{row.Room}}</td><td>{{row.RoomType}}</td><td>{{row.RoomArrivalDate}}</td><td>{{row.RoomDepartureDate}}</td><td>{{row.Rate|number}}</td></tr>
      </tbody>
    </table>
    <table class="cancel-total-table"><tbody class="pms-grouped-rows" data-source="rows" data-group-by="PeriodGroup"><tr class="pms-group-footer"><td>Tổng BK</td><td>{{group.distinct.BookingId}}</td><td>Tổng phòng</td><td>{{group.distinct.RoomId}}</td></tr></tbody></table>
  </div>
</div>
HTML;
    }

    private function css(): string
    {
        return <<<'CSS'
body { color: #111; font-family: Arial, Helvetica, sans-serif; font-size: 8px; }
.hotel-header { display: grid; grid-template-columns: 180px 1fr; align-items: center; min-height: 66px; }
.hotel-logo { display: flex; align-items: center; min-height: 58px; }
.hotel-logo-image { display: block; max-width: 120px; max-height: 58px; object-fit: contain; }
.hotel-logo-fallback { display: grid; place-items: center; width: 56px; height: 56px; background: #1688bd; color: #fff; font-weight: 800; }
.hotel-information { line-height: 1.8; }
.hotel-information .generated-date { margin-left: 36px; }
.header-divider { margin: 0 0 5px; border: 0; border-top: 1.5px solid #000; }
h1 { margin: 0; text-align: center; font-size: 20px; line-height: 1.25; }
.report-period { display: flex; justify-content: center; gap: 24px; margin: 7px 0 12px; }
.summary-section, .detail-section { display: none; }
.summary-visible-, .summary-visible-0, .summary-visible-false, .detail-visible-1, .detail-visible-true { display: block; }
.cancel-summary-table, .cancel-room-table, .cancel-total-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
.cancel-summary-table th, .cancel-summary-table td, .cancel-room-table th, .cancel-room-table td, .cancel-total-table td { border: 1px solid #b9c3d0; padding: 4px; line-height: 1.15; vertical-align: middle; overflow-wrap: anywhere; }
.cancel-summary-table th, .cancel-room-table th { background: #d9e1ec; text-align: center; font-weight: 700; }
.cancel-summary-table td:nth-child(1), .cancel-summary-table td:nth-child(4), .cancel-summary-table td:nth-child(5), .cancel-summary-table td:nth-child(6), .cancel-summary-table td:nth-child(7), .cancel-summary-table td:nth-child(8), .cancel-summary-table td:nth-child(9), .cancel-summary-table td:nth-child(11) { text-align: center; }
.booking-group-cell { padding: 0 !important; border: 0 !important; }
.booking-grid { display: grid; grid-template-columns: 7fr 13fr 10fr 8fr 8fr 8fr 8fr 6fr 9fr 16fr 7fr; border-top: 1px solid #b9c3d0; border-left: 1px solid #b9c3d0; }
.booking-grid > div { display: grid; grid-template-rows: auto 1fr; border-right: 1px solid #b9c3d0; border-bottom: 1px solid #b9c3d0; text-align: center; overflow-wrap: anywhere; }
.booking-grid b { background: #d9e1ec; padding: 4px 2px; border-bottom: 1px solid #b9c3d0; }
.booking-grid span { padding: 4px 2px; }
.cancel-room-table td:nth-child(1), .cancel-room-table td:nth-child(2), .cancel-room-table td:nth-child(3), .cancel-room-table td:nth-child(4) { text-align: center; }
.cancel-room-table td:nth-child(5) { text-align: right; }
.total-label, .cancel-total-table td:nth-child(1), .cancel-total-table td:nth-child(3) { font-weight: 700; text-align: right !important; }
.total-value, .cancel-total-table td:nth-child(2), .cancel-total-table td:nth-child(4) { font-weight: 700; text-align: center !important; }
.cancel-total-table { margin-top: -1px; }
@media print { thead { display: table-header-group; } tr { break-inside: avoid; } }
CSS;
    }
};
