<?php

use Illuminate\Support\Facades\DB;

return new class
{
    public function apply(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') return;
        DB::table('templates')->where('report', 'COMPLIMENTARY_ROOMS_STANDARD')->update([
            'content_json' => json_encode($this->blocks(), JSON_UNESCAPED_UNICODE),
            'content_html' => $this->html(), 'css' => $this->css(), 'version' => '1.0', 'updated_at' => now(),
        ]);
    }

    private function blocks(): array
    {
        return ['header' => [
            ['id'=>'complimentary_hotel','type'=>'text','content'=>'<div class="hotel-header"><div class="hotel-logo">{{hotel.logo}}</div><div class="hotel-information"><div><b>Địa chỉ:</b> {{hotel.address}}</div><div><b>Nhân viên:</b> {{report.generated_by}} <b>Ngày:</b> {{report.generated_at}}</div></div></div>','style'=>['fontSize'=>'9px','marginBottom'=>'4px']],
            ['id'=>'complimentary_divider','type'=>'divider','content'=>'<hr>','style'=>['marginBottom'=>'4px']],
            ['id'=>'complimentary_title','type'=>'text','content'=>'<h1>BÁO CÁO PHÒNG MIỄN PHÍ</h1>','style'=>['fontSize'=>'20px','textAlign'=>'center','fontWeight'=>'bold','marginBottom'=>'4px']],
            ['id'=>'complimentary_period','type'=>'text','content'=>'<p class="period">Ngày: {{parameters.p_from_date}} &nbsp; ~ &nbsp; {{parameters.p_to_date}}</p>','style'=>['fontSize'=>'9px','textAlign'=>'center','marginBottom'=>'6px']],
        ], 'detail' => [[
            'id' => 'complimentary_rooms_table', 'type' => 'table', 'dataSource' => 'rows', 'tableType' => 'dynamic', 'tableStyle' => 'grid',
            'groupBy' => 'StayDateGroup', 'columns' => $this->columns(),
        ]]];
    }

    private function columns(): array
    {
        return [
            ['header' => 'Mã ĐK', 'value' => 'row.BookingId'], ['header' => 'Tên Khách', 'value' => 'row.GuestName'],
            ['header' => 'Phòng', 'value' => 'row.Room'], ['header' => 'Ngày Đến', 'value' => 'row.ArrivalDate'],
            ['header' => 'Ngày Đi', 'value' => 'row.DepartureDate'], ['header' => 'Công Ty', 'value' => 'row.Company'],
            ['header' => 'Mã Giá Phòng', 'value' => 'row.RoomRateCode'], ['header' => 'Ghi Chú', 'value' => 'row.Note'],
        ];
    }

    private function html(): string
    {
        return <<<'HTML'
<div class="report-header-band"><div class="hotel-header"><div class="hotel-logo">{{hotel.logo}}</div><div class="hotel-information"><div><b>Địa chỉ:</b> {{hotel.address}}</div><div><b>Nhân viên:</b> {{report.generated_by}} <b>Ngày:</b> {{report.generated_at}}</div></div></div><hr><h1>BÁO CÁO PHÒNG MIỄN PHÍ</h1><p class="period">Ngày: {{parameters.p_from_date}} &nbsp; ~ &nbsp; {{parameters.p_to_date}}</p></div>
<table class="complimentary-table"><thead><tr><th>Mã ĐK</th><th>Tên Khách</th><th>Phòng</th><th>Ngày Đến</th><th>Ngày Đi</th><th>Công Ty</th><th>Mã Giá Phòng</th><th>Ghi Chú</th></tr></thead><tbody class="pms-grouped-rows" data-source="rows" data-group-by="StayDateGroup"><tr class="pms-group-header"><td colspan="8">Ngày: {{row.StayDateGroup}}</td></tr><tr class="pms-detail-row"><td>{{row.BookingId}}</td><td>{{row.GuestName}}</td><td>{{row.Room}}</td><td>{{row.ArrivalDate}}</td><td>{{row.DepartureDate}}</td><td>{{row.Company}}</td><td>{{row.RoomRateCode}}</td><td>{{row.Note}}</td></tr></tbody></table>
HTML;
    }

    private function css(): string
    {
        return <<<'CSS'
body{font-family:Arial,sans-serif;font-size:9px;color:#111}.hotel-header{display:grid;grid-template-columns:175px 1fr;min-height:65px;align-items:center}.hotel-logo{min-height:55px}.hotel-information{font-size:9px;line-height:1.8}hr{border:0;border-top:1.5px solid #000}h1{text-align:center;font-size:20px;margin:5px 0}.period{text-align:center;font-weight:bold}.complimentary-table{width:100%;border-collapse:collapse;table-layout:fixed}.complimentary-table th,.complimentary-table td{border:1px solid #ccc;padding:5px;vertical-align:middle;overflow-wrap:anywhere}.complimentary-table th{background:#d9e1ec;text-align:center}.complimentary-table td:nth-child(1),.complimentary-table td:nth-child(3),.complimentary-table td:nth-child(4),.complimentary-table td:nth-child(5),.complimentary-table td:nth-child(7){text-align:center}.pms-group-header{font-weight:bold;color:#851c1c}@media print{thead{display:table-header-group}tr{break-inside:avoid}}
CSS;
    }
};
