<?php

use Illuminate\Support\Facades\DB;

return new class
{
    public function apply(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') return;
        DB::table('templates')->where('report', 'CANCELLED_ROOMS_STANDARD')->update([
            'content_json' => json_encode(['header' => $this->header(), 'detail' => $this->detail(), 'footer' => $this->footer()], JSON_UNESCAPED_UNICODE),
            'content_html' => $this->html(), 'css' => $this->css(), 'updated_at' => now(),
        ]);
    }

    private function header(): array
    {
        return [
            ['id' => 'cancelled_rooms_hotel', 'type' => 'text', 'content' => '<div class="hotel-header"><div class="hotel-logo">{{hotel.logo}}</div><div class="hotel-information"><div><b>Địa chỉ:</b> {{hotel.address}}</div><div><b>Nhân viên:</b> {{report.generated_by}} <b>Ngày:</b> {{report.generated_at}}</div></div></div>', 'style' => ['marginBottom' => '4px']],
            ['id' => 'cancelled_rooms_divider', 'type' => 'divider', 'content' => '<hr>', 'style' => ['marginBottom' => '10px']],
            ['id' => 'cancelled_rooms_title', 'type' => 'text', 'content' => '<h1>BÁO CÁO PHÒNG HỦY</h1>', 'style' => ['textAlign' => 'center', 'marginBottom' => '8px']],
            ['id' => 'cancelled_rooms_period', 'type' => 'text', 'content' => '<p class="period"><b>Ngày:</b> {{parameters.p_from_date}} ~ {{parameters.p_to_date}}</p>', 'style' => ['textAlign' => 'center', 'marginBottom' => '10px']],
        ];
    }

    private function detail(): array
    {
        return [[
            'id' => 'cancelled_rooms_table', 'type' => 'table', 'dataSource' => 'rows', 'tableType' => 'dynamic', 'tableStyle' => 'grid',
            'groups' => [[
                'id' => 'cancel_reason_group', 'field' => 'CancelReasonGroup', 'label' => 'Lý do hủy: {{row.CancelReasonGroup}}',
                'className' => 'reason-group', 'enabledBy' => 'parameters.p_group_by_reason', 'sort' => 'ASC',
            ]],
            'groupBy' => 'CancelReasonGroup', 'groupEnabledBy' => 'parameters.p_group_by_reason',
            'groupHeader' => '<td colspan="14" class="reason-group">Lý do hủy: {{row.CancelReasonGroup}}</td>', 'columns' => $this->columns(),
        ]];
    }

    private function footer(): array
    {
        return [['id' => 'cancelled_rooms_total', 'type' => 'text', 'content' => '<div class="total"><b>Tổng:</b> {{summary.row_count}}</div>', 'style' => ['textAlign' => 'right', 'fontWeight' => 'bold']]];
    }

    private function columns(): array
    {
        return [
            ['header' => 'STT', 'value' => 'row.STT', 'width' => '4%', 'align' => 'center'],
            ['header' => 'Mã ĐK', 'value' => 'row.BookingCode', 'width' => '8%'],
            ['header' => 'Tên ĐK', 'value' => 'row.BookingName', 'width' => '13%'],
            ['header' => 'Tên Công Ty', 'value' => 'row.Company', 'width' => '11%'],
            ['header' => 'Ngày Tạo', 'value' => 'row.DateDangKy', 'width' => '8%', 'align' => 'center'],
            ['header' => 'Tình Trạng ĐK', 'value' => 'row.BookingStatus', 'width' => '9%', 'align' => 'center'],
            ['header' => 'Phòng', 'value' => 'row.Room', 'width' => '5%', 'align' => 'center'],
            ['header' => 'Ngày Đến', 'value' => 'row.ArrivalDate', 'width' => '8%', 'align' => 'center'],
            ['header' => 'Ngày Đi', 'value' => 'row.DepartureDate', 'width' => '8%', 'align' => 'center'],
            ['header' => 'Ngày Hủy', 'value' => 'row.CancelDate', 'width' => '8%', 'align' => 'center'],
            ['header' => 'Giờ', 'value' => 'row.CancelTime', 'width' => '5%', 'align' => 'center'],
            ['header' => 'Người Hủy', 'value' => 'row.UserName', 'width' => '8%'],
            ['header' => 'Lý Do', 'value' => 'row.CancelReason', 'width' => '8%'],
            ['header' => 'Hủy Trước', 'value' => 'row.SoCancelDate', 'width' => '5%', 'align' => 'center'],
        ];
    }

    private function html(): string
    {
        return <<<'HTML'
<div class="report-header"><div class="hotel-header"><div class="hotel-logo">{{hotel.logo}}</div><div class="hotel-information"><div><b>Địa chỉ:</b> {{hotel.address}}</div><div><b>Nhân viên:</b> {{report.generated_by}} <b>Ngày:</b> {{report.generated_at}}</div></div></div><hr><h1>BÁO CÁO PHÒNG HỦY</h1><p class="period"><b>Ngày:</b> {{parameters.p_from_date}} ~ {{parameters.p_to_date}}</p></div>
<table class="cancelled-rooms-table"><thead><tr><th>STT</th><th>Mã ĐK</th><th>Tên ĐK</th><th>Tên Công Ty</th><th>Ngày Tạo</th><th>Tình Trạng ĐK</th><th>Phòng</th><th>Ngày Đến</th><th>Ngày Đi</th><th>Ngày Hủy</th><th>Giờ</th><th>Người Hủy</th><th>Lý Do</th><th>Hủy Trước</th></tr></thead>
<tbody class="pms-grouped-rows" data-source="rows" data-group-configured="1" data-group-by="CancelReasonGroup"><tr class="pms-group-header" data-group-level="0" data-group-field="CancelReasonGroup" data-group-sort="ASC" data-group-enabled-by="parameters.p_group_by_reason"><td colspan="14" class="reason-group">Lý do hủy: {{row.CancelReasonGroup}}</td></tr><tr class="pms-detail-row"><td>{{row.STT}}</td><td>{{row.BookingCode}}</td><td>{{row.BookingName}}</td><td>{{row.Company}}</td><td>{{row.DateDangKy}}</td><td>{{row.BookingStatus}}</td><td>{{row.Room}}</td><td>{{row.ArrivalDate}}</td><td>{{row.DepartureDate}}</td><td>{{row.CancelDate}}</td><td>{{row.CancelTime}}</td><td>{{row.UserName}}</td><td>{{row.CancelReason}}</td><td>{{row.SoCancelDate}}</td></tr></tbody></table>
<div class="total"><b>Tổng:</b> {{summary.row_count}}</div>
HTML;
    }

    private function css(): string
    {
        return <<<'CSS'
body { color: #111; font-family: Arial, Helvetica, sans-serif; font-size: 9px; }
.hotel-header { display: grid; grid-template-columns: 175px 1fr; align-items: center; min-height: 65px; }
.hotel-logo { display: flex; align-items: center; min-height: 55px; }
.hotel-logo img { max-width: 120px; max-height: 55px; object-fit: contain; }
.hotel-information { line-height: 1.8; }
hr { margin: 0 0 10px; border: 0; border-top: 1px solid #111; }
h1 { margin: 0; text-align: center; font-size: 18px; }
.period { margin: 8px 0 12px; text-align: center; }
.cancelled-rooms-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
.cancelled-rooms-table th, .cancelled-rooms-table td { border: 1px solid #c7cdd6; padding: 4px 3px; line-height: 1.15; overflow-wrap: anywhere; }
.cancelled-rooms-table th { background: #d9e1ec; text-align: center; }
.reason-group { background: #fff; color: #b91c1c; font-weight: bold; text-align: left; }
.reason-group-visible-0 { display: none; }
.cancelled-rooms-table td:nth-child(1), .cancelled-rooms-table td:nth-child(5), .cancelled-rooms-table td:nth-child(6), .cancelled-rooms-table td:nth-child(7), .cancelled-rooms-table td:nth-child(8), .cancelled-rooms-table td:nth-child(9), .cancelled-rooms-table td:nth-child(10), .cancelled-rooms-table td:nth-child(11), .cancelled-rooms-table td:nth-child(14) { text-align: center; }
.total { margin-top: 6px; text-align: right; }
@media print { thead { display: table-header-group; } tr { break-inside: avoid; } }
CSS;
    }
};
