<?php

use Illuminate\Support\Facades\DB;

return new class
{
    public function apply(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') return;
        DB::table('templates')->where('report', 'ROOM_MOVES_STANDARD')->update([
            'content_json' => json_encode(['header' => $this->header(), 'detail' => $this->detail(), 'footer' => $this->footer()], JSON_UNESCAPED_UNICODE),
            'content_html' => $this->html(), 'css' => $this->css(), 'updated_at' => now(),
        ]);
    }

    private function header(): array
    {
        return [
            ['id' => 'room_moves_hotel_header', 'type' => 'text', 'content' => '<div class="hotel-header"><div class="hotel-logo">{{hotel.logo}}</div><div class="hotel-information"><div><b>Địa chỉ:</b> {{hotel.address}}</div><div><b>Nhân viên:</b> {{report.generated_by}} <b>Ngày:</b> {{report.generated_at}}</div></div></div>', 'style' => ['marginBottom' => '4px']],
            ['id' => 'room_moves_divider', 'type' => 'divider', 'content' => '<hr>', 'style' => ['marginBottom' => '10px']],
            ['id' => 'room_moves_title', 'type' => 'text', 'content' => '<h1>BÁO CÁO CHUYỂN PHÒNG</h1>', 'style' => ['textAlign' => 'center', 'marginBottom' => '8px']],
            ['id' => 'room_moves_period', 'type' => 'text', 'content' => '<p class="period"><b>Ngày:</b> {{parameters.p_from_date}} ~ {{parameters.p_to_date}}</p>', 'style' => ['textAlign' => 'center', 'marginBottom' => '10px']],
        ];
    }

    private function detail(): array
    {
        return [['id' => 'room_moves_table', 'type' => 'table', 'dataSource' => 'rows', 'tableType' => 'dynamic', 'tableStyle' => 'grid', 'columns' => $this->columns()]];
    }

    private function footer(): array
    {
        return [['id' => 'room_moves_total', 'type' => 'text', 'content' => '<div class="total"><b>Tổng số lượt chuyển:</b> {{summary.row_count}}</div>', 'style' => ['textAlign' => 'right', 'fontWeight' => 'bold']]];
    }

    private function columns(): array
    {
        return [
            ['header' => 'STT', 'value' => 'row.STT', 'width' => '4%', 'align' => 'center'],
            ['header' => 'Mã ĐK', 'value' => 'row.BookingCode', 'width' => '8%'],
            ['header' => 'Tên Khách', 'value' => 'row.Guest', 'width' => '14%'],
            ['header' => 'Ngày Đến', 'value' => 'row.ArrivalDate', 'width' => '9%', 'align' => 'center'],
            ['header' => 'Phòng', 'value' => 'row.Room', 'width' => '6%', 'align' => 'center'],
            ['header' => 'Loại Phòng', 'value' => 'row.RoomType', 'width' => '11%', 'align' => 'center'],
            ['header' => 'Giá Phòng', 'value' => 'row.Rate', 'width' => '8%', 'align' => 'right'],
            ['header' => 'Ngày Chuyển', 'value' => 'row.ArrivalDate1', 'width' => '9%', 'align' => 'center'],
            ['header' => 'Phòng Chuyển', 'value' => 'row.Room1', 'width' => '7%', 'align' => 'center'],
            ['header' => 'BK Chuyển', 'value' => 'row.BookingCode1', 'width' => '8%', 'align' => 'center'],
            ['header' => 'Loại Phòng', 'value' => 'row.RoomType1', 'width' => '11%', 'align' => 'center'],
            ['header' => 'Giá Phòng', 'value' => 'row.Rate1', 'width' => '8%', 'align' => 'right'],
            ['header' => 'Người Dùng', 'value' => 'row.Username', 'width' => '8%'],
            ['header' => 'Lý Do', 'value' => 'row.Reason', 'width' => '9%'],
        ];
    }

    private function html(): string
    {
        return <<<'HTML'
<div class="report-header"><div class="hotel-header"><div class="hotel-logo">{{hotel.logo}}</div><div class="hotel-information"><div><b>Địa chỉ:</b> {{hotel.address}}</div><div><b>Nhân viên:</b> {{report.generated_by}} <b>Ngày:</b> {{report.generated_at}}</div></div></div><hr><h1>BÁO CÁO CHUYỂN PHÒNG</h1><p class="period"><b>Ngày:</b> {{parameters.p_from_date}} ~ {{parameters.p_to_date}}</p></div>
<table class="room-moves-table"><thead><tr><th>STT</th><th>Mã ĐK</th><th>Tên Khách</th><th>Ngày Đến</th><th>Phòng</th><th>Loại Phòng</th><th>Giá Phòng</th><th>Ngày Chuyển</th><th>Phòng Chuyển</th><th>BK Chuyển</th><th>Loại Phòng</th><th>Giá Phòng</th><th>Người Dùng</th><th>Lý Do</th></tr></thead>
<tbody><tr class="pms-detail-row" data-source="rows"><td>{{row.STT}}</td><td>{{row.BookingCode}}</td><td>{{row.Guest}}</td><td>{{row.ArrivalDate}}</td><td>{{row.Room}}</td><td>{{row.RoomType}}</td><td>{{row.Rate}}</td><td>{{row.ArrivalDate1}}</td><td>{{row.Room1}}</td><td>{{row.BookingCode1}}</td><td>{{row.RoomType1}}</td><td>{{row.Rate1}}</td><td>{{row.Username}}</td><td>{{row.Reason}}</td></tr></tbody></table>
<div class="total"><b>Tổng số lượt chuyển:</b> {{summary.row_count}}</div>
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
.room-moves-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
.room-moves-table th, .room-moves-table td { border: 1px solid #c7cdd6; padding: 4px 3px; line-height: 1.15; overflow-wrap: anywhere; }
.room-moves-table th { background: #d9e1ec; text-align: center; }
.room-moves-table td:nth-child(1), .room-moves-table td:nth-child(4), .room-moves-table td:nth-child(5), .room-moves-table td:nth-child(6), .room-moves-table td:nth-child(8), .room-moves-table td:nth-child(9), .room-moves-table td:nth-child(10), .room-moves-table td:nth-child(11), .room-moves-table td:nth-child(13) { text-align: center; }
.room-moves-table td:nth-child(7), .room-moves-table td:nth-child(12) { text-align: right; white-space: nowrap; }
.total { margin-top: 6px; text-align: right; }
@media print { thead { display: table-header-group; } tr { break-inside: avoid; } }
CSS;
    }
};
