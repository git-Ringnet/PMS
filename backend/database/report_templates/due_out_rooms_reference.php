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
            ->where('report', 'DUE_OUT_ROOMS_STANDARD')
            ->update([
                'name' => 'Danh sách phòng Due Out - Mẫu tham chiếu legacy',
                'page_size' => 'A4',
                'page_orientation' => 'landscape',
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
                    'id' => 'due_out_hotel',
                    'type' => 'text',
                    'content' => '<div class="hotel-header"><div class="hotel-logo">{{hotel.logo}}</div><div class="hotel-information"><div><b>Địa chỉ:</b> {{hotel.address}}</div><div><b>Người dùng:</b> {{report.generated_by}} <b class="generated-date">Ngày:</b> {{report.generated_at}}</div></div></div>',
                    'style' => ['fontSize' => '9px', 'marginBottom' => '4px'],
                ],
                [
                    'id' => 'due_out_divider',
                    'type' => 'divider',
                    'content' => '<hr class="header-divider">',
                    'style' => ['marginBottom' => '4px'],
                ],
                [
                    'id' => 'due_out_title',
                    'type' => 'text',
                    'content' => '<h1>DANH SÁCH PHÒNG DUE OUT</h1>',
                    'style' => ['textAlign' => 'center', 'fontSize' => '20px', 'fontWeight' => 'bold', 'marginBottom' => '5px'],
                ],
                [
                    'id' => 'due_out_period',
                    'type' => 'text',
                    'content' => '<p class="report-period"><b>Ngày:</b><span>{{parameters.p_from_date}}</span><b>~</b><span>{{parameters.p_to_date}}</span></p>',
                    'style' => ['textAlign' => 'center', 'fontSize' => '9px', 'marginBottom' => '10px'],
                ],
            ],
            'detail' => [
                [
                    'id' => 'due_out_table',
                    'type' => 'table',
                    'dataSource' => 'rows',
                    'tableType' => 'dynamic',
                    'tableStyle' => 'grid',
                    'isNew' => false,
                    'groupBy' => 'DepartureDateGroup',
                    'groupHeader' => '<td colspan="12" class="date-row">Ngày: {{row.DepartureDateGroup}}</td>',
                    'groupFooter' => '<td colspan="2" class="total-label">Tổng Theo Ngày</td><td class="total-value">{{group.distinct.RentalRoomId}}</td><td colspan="5"></td><td class="total-value">{{group.sum.RoomNight}}</td><td class="total-value">{{group.sum.ExtraBed}}</td><td class="total-value">{{group.sum.Adult}} / {{group.sum.Child}}</td><td></td>',
                    'columns' => $this->columns(),
                    'style' => ['fontSize' => '9px', 'marginTop' => '4px', 'marginBottom' => '0px'],
                ],
                [
                    'id' => 'due_out_period_total',
                    'type' => 'text',
                    'content' => '<table class="due-out-table period-total-table"><tbody class="pms-grouped-rows" data-source="rows" data-group-by="PeriodGroup"><tr class="pms-group-footer"><td colspan="2" class="total-label">Tổng Giai Đoạn</td><td class="total-value">{{group.distinct.RentalRoomId}}</td><td colspan="5"></td><td class="total-value">{{group.sum.RoomNight}}</td><td class="total-value">{{group.sum.ExtraBed}}</td><td class="total-value">{{group.sum.Adult}} / {{group.sum.Child}}</td><td></td></tr></tbody></table>',
                    'style' => ['fontSize' => '9px', 'marginTop' => '0px', 'marginBottom' => '4px'],
                ],
            ],
            'footer' => [],
        ];
    }

    private function columns(): array
    {
        return [
            ['header' => 'STT', 'value' => 'row.STT', 'width' => '4%', 'align' => 'center'],
            ['header' => 'Mã ĐK', 'value' => 'row.BookingId', 'width' => '7%', 'align' => 'center'],
            ['header' => 'Tên Khách', 'value' => 'row.GuestName', 'width' => '15%', 'align' => 'left'],
            ['header' => 'Công Ty', 'value' => 'row.Company', 'width' => '12%', 'align' => 'left'],
            ['header' => 'Phòng', 'value' => 'row.Room', 'width' => '6%', 'align' => 'center'],
            ['header' => 'Loại Phòng', 'value' => 'row.RoomType', 'width' => '8%', 'align' => 'center'],
            ['header' => 'Ngày Đến', 'value' => 'row.ArrivalDate', 'width' => '11%', 'align' => 'center'],
            ['header' => 'Ngày Đi', 'value' => 'row.DepartureDate', 'width' => '11%', 'align' => 'center'],
            ['header' => 'Đêm', 'value' => 'row.RoomNight', 'width' => '5%', 'align' => 'center'],
            ['header' => 'TG', 'value' => 'row.ExtraBed', 'width' => '5%', 'align' => 'center'],
            ['header' => 'N.Lớn / T.Em', 'value' => 'row.AdultChild', 'width' => '8%', 'align' => 'center'],
            ['header' => 'Ghi Chú', 'value' => 'row.Note', 'width' => '8%', 'align' => 'left'],
        ];
    }

    private function html(): string
    {
        return <<<'HTML'
<div class="report-header-band">
  <div class="hotel-header"><div class="hotel-logo">{{hotel.logo}}</div><div class="hotel-information"><div><b>Địa chỉ:</b> {{hotel.address}}</div><div><b>Người dùng:</b> {{report.generated_by}} <b class="generated-date">Ngày:</b> {{report.generated_at}}</div></div></div>
  <hr class="header-divider">
  <h1>DANH SÁCH PHÒNG DUE OUT</h1>
  <p class="report-period"><b>Ngày:</b><span>{{parameters.p_from_date}}</span><b>~</b><span>{{parameters.p_to_date}}</span></p>
</div>
<div class="report-detail-band">
  <table class="due-out-table">
    <colgroup><col style="width:4%"><col style="width:7%"><col style="width:15%"><col style="width:12%"><col style="width:6%"><col style="width:8%"><col style="width:11%"><col style="width:11%"><col style="width:5%"><col style="width:5%"><col style="width:8%"><col style="width:8%"></colgroup>
    <thead><tr><th>STT</th><th>Mã ĐK</th><th>Tên Khách</th><th>Công Ty</th><th>Phòng</th><th>Loại<br>Phòng</th><th>Ngày Đến</th><th>Ngày Đi</th><th>Đêm</th><th>TG</th><th>N.Lớn<br>T.Em</th><th>Ghi Chú</th></tr></thead>
    <tbody class="pms-grouped-rows" data-source="rows" data-group-by="DepartureDateGroup">
      <tr class="pms-group-header"><td colspan="12" class="date-row">Ngày: {{row.DepartureDateGroup}}</td></tr>
      <tr class="pms-detail-row"><td>{{row.STT}}</td><td>{{row.BookingId}}</td><td>{{row.GuestName}}</td><td>{{row.Company}}</td><td>{{row.Room}}</td><td>{{row.RoomType}}</td><td>{{row.ArrivalDate}}</td><td>{{row.DepartureDate}}</td><td>{{row.RoomNight}}</td><td>{{row.ExtraBed}}</td><td>{{row.AdultChild}}</td><td>{{row.Note}}</td></tr>
      <tr class="pms-group-footer"><td colspan="2" class="total-label">Tổng Theo Ngày</td><td class="total-value">{{group.distinct.RentalRoomId}}</td><td colspan="5"></td><td class="total-value">{{group.sum.RoomNight}}</td><td class="total-value">{{group.sum.ExtraBed}}</td><td class="total-value">{{group.sum.Adult}} / {{group.sum.Child}}</td><td></td></tr>
    </tbody>
  </table>
  <table class="due-out-table period-total-table">
    <colgroup><col style="width:4%"><col style="width:7%"><col style="width:15%"><col style="width:12%"><col style="width:6%"><col style="width:8%"><col style="width:11%"><col style="width:11%"><col style="width:5%"><col style="width:5%"><col style="width:8%"><col style="width:8%"></colgroup>
    <tbody class="pms-grouped-rows" data-source="rows" data-group-by="PeriodGroup">
      <tr class="pms-group-footer"><td colspan="2" class="total-label">Tổng Giai Đoạn</td><td class="total-value">{{group.distinct.RentalRoomId}}</td><td colspan="5"></td><td class="total-value">{{group.sum.RoomNight}}</td><td class="total-value">{{group.sum.ExtraBed}}</td><td class="total-value">{{group.sum.Adult}} / {{group.sum.Child}}</td><td></td></tr>
    </tbody>
  </table>
</div>
HTML;
    }

    private function css(): string
    {
        return <<<'CSS'
body { color: #111; font-family: Arial, Helvetica, sans-serif; font-size: 9px; }
.hotel-header { display: grid; grid-template-columns: 180px 1fr; align-items: center; min-height: 66px; }
.hotel-logo { display: flex; align-items: center; min-height: 58px; }
.hotel-logo-image { display: block; max-width: 120px; max-height: 58px; object-fit: contain; }
.hotel-logo-fallback { display: grid; place-items: center; width: 56px; height: 56px; background: #1688bd; color: #fff; font-weight: 800; }
.hotel-information { line-height: 1.8; }
.hotel-information .generated-date { margin-left: 36px; }
.header-divider { margin: 0 0 5px; border: 0; border-top: 1.5px solid #000; }
h1 { margin: 0; text-align: center; font-size: 20px; line-height: 1.25; }
.report-period { display: flex; justify-content: center; gap: 24px; margin: 7px 0 12px; }
.due-out-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
.due-out-table th, .due-out-table td { border: 1px solid #b9c3d0; padding: 4px; line-height: 1.15; vertical-align: middle; overflow-wrap: anywhere; }
.due-out-table th { background: #d9e1ec; text-align: center; font-weight: 700; }
.due-out-table td:nth-child(1), .due-out-table td:nth-child(2), .due-out-table td:nth-child(5), .due-out-table td:nth-child(6), .due-out-table td:nth-child(7), .due-out-table td:nth-child(8), .due-out-table td:nth-child(9), .due-out-table td:nth-child(10), .due-out-table td:nth-child(11) { text-align: center; }
.date-row { color: #b91c1c; background: #fff; font-weight: 700; text-align: left !important; }
.total-label { background: #fff; font-weight: 700; text-align: right !important; }
.total-value { background: #fff; font-weight: 700; text-align: center !important; }
.period-total-table { margin-top: -1px; }
@media print { thead { display: table-header-group; } tr { break-inside: avoid; } }
CSS;
    }
};
