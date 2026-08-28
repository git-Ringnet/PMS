<?php

use Illuminate\Support\Facades\DB;

return new class
{
    public function apply(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') return;
        DB::table('templates')->where('report', 'INHOUSE_ROOMS_STANDARD')->update([
            'content_json' => json_encode($this->blocks(), JSON_UNESCAPED_UNICODE),
            'content_html' => $this->html(), 'css' => $this->css(), 'version' => '1.0', 'updated_at' => now(),
        ]);
    }

    private function blocks(): array
    {
        return ['header' => [
            ['id'=>'inhouse_hotel','type'=>'text','content'=>'<div class="hotel-header"><div class="hotel-logo">{{hotel.logo}}</div><div class="hotel-information"><div><b>Địa chỉ:</b> {{hotel.address}}</div><div><b>Người dùng:</b> {{report.generated_by}} <b>Ngày:</b> {{report.generated_at}}</div></div></div>','style'=>['fontSize'=>'9px','marginBottom'=>'4px']],
            ['id'=>'inhouse_divider','type'=>'divider','content'=>'<hr>','style'=>['marginBottom'=>'4px']],
            ['id'=>'inhouse_title','type'=>'text','content'=>'<h1>BÁO CÁO PHÒNG Ở</h1>','style'=>['fontSize'=>'20px','textAlign'=>'center','fontWeight'=>'bold','marginBottom'=>'4px']],
            ['id'=>'inhouse_period','type'=>'text','content'=>'<p class="period">Ngày: {{parameters.p_from_date}} &nbsp; ~ &nbsp; {{parameters.p_to_date}}</p>','style'=>['fontSize'=>'9px','textAlign'=>'center','marginBottom'=>'6px']],
        ], 'detail' => [[
            'id' => 'inhouse_table', 'type' => 'table', 'dataSource' => 'rows', 'tableType' => 'dynamic', 'tableStyle' => 'grid',
            'groupBy' => 'StayDateGroup', 'subgroupBy' => 'CompanyId', 'subsubgroupBy' => 'BookingId',
            'groupHeader' => '<td colspan="14" class="date-row">Ngày: {{row.StayDateGroup}}</td>',
            'subgroupHeader' => '<td colspan="14" class="company-row">Công Ty: {{row.Company}}</td>',
            'subsubgroupHeader' => '<td colspan="14" class="booking-row">Đăng Ký: {{row.Booking}}</td>',
            'subsubgroupNote' => '<td class="note-label">Ghi Chú:</td><td colspan="13">{{row.Note}}</td>',
            'columns' => $this->columns(),
        ]]];
    }

    private function columns(): array
    {
        return [
            ['header' => 'Mã ĐK', 'value' => 'row.BookingId'], ['header' => 'Phòng', 'value' => 'row.Room'],
            ['header' => 'Loại Phòng', 'value' => 'row.RoomTypeCode'], ['header' => 'Tên Khách', 'value' => 'row.GuestName'],
            ['header' => 'Ngày Đến', 'value' => 'row.ArrivalDate'], ['header' => 'Ngày Đi', 'value' => 'row.DepartureDate'],
            ['header' => 'Đêm', 'value' => 'row.RoomNight'], ['header' => 'Người lớn', 'value' => 'row.Adult'],
            ['header' => 'Em bé', 'value' => 'row.Infant'], ['header' => 'Trẻ em', 'value' => 'row.Child'],
            ['header' => 'Giá Phòng', 'value' => 'row.Rate'], ['header' => 'Không tới / Trễ', 'value' => 'row.NoShowLate'],
            ['header' => 'Yêu Cầu Đặc Biệt', 'value' => 'row.Special'], ['header' => 'Ghi Chú', 'value' => 'row.Note'],
        ];
    }

    private function html(): string
    {
        return <<<'HTML'
<div class="report-header-band"><div class="hotel-header"><div class="hotel-logo">{{hotel.logo}}</div><div class="hotel-information"><div><b>Địa chỉ:</b> {{hotel.address}}</div><div><b>Người dùng:</b> {{report.generated_by}} <b>Ngày:</b> {{report.generated_at}}</div></div></div><hr><h1>BÁO CÁO PHÒNG Ở</h1><p class="period">Ngày: {{parameters.p_from_date}} &nbsp; ~ &nbsp; {{parameters.p_to_date}}</p></div>
<table class="inhouse-table"><thead><tr><th>Mã ĐK</th><th>Phòng</th><th>Loại<br>Phòng</th><th>Tên Khách</th><th>Ngày Đến</th><th>Ngày Đi</th><th>Đêm</th><th>Người lớn</th><th>Em bé</th><th>Trẻ em</th><th class="rate-col">Giá<br>Phòng</th><th class="detail-col">Không tới/<br>Trễ</th><th>Yêu Cầu<br>Đặc Biệt</th><th>Ghi Chú</th></tr></thead><tbody class="pms-grouped-rows" data-source="rows" data-group-by="StayDateGroup" data-subgroup-by="CompanyId" data-subsubgroup-by="BookingId"><tr class="pms-group-header"><td colspan="14" class="date-row">Ngày: {{row.StayDateGroup}}</td></tr><tr class="pms-subgroup-header"><td colspan="14" class="company-row">Công Ty: {{row.Company}}</td></tr><tr class="pms-subsubgroup-header"><td colspan="14" class="booking-row">Đăng Ký: {{row.Booking}}</td></tr><tr class="pms-subsubgroup-note"><td>Ghi Chú:</td><td colspan="13">{{row.Note}}</td></tr><tr class="pms-detail-row"><td>{{row.BookingId}}</td><td>{{row.Room}}</td><td>{{row.RoomTypeCode}}</td><td>{{row.GuestName}}</td><td>{{row.ArrivalDate}}</td><td>{{row.DepartureDate}}</td><td>{{row.RoomNight}}</td><td>{{row.Adult}}</td><td>{{row.Infant}}</td><td>{{row.Child}}</td><td class="rate-col">{{row.Rate|number}}</td><td class="detail-col">{{row.NoShowLate}}</td><td>{{row.Special}}</td><td>{{row.Note}}</td></tr></tbody></table><h2>BẢNG KÊ CHI TIẾT THEO LOẠI PHÒNG</h2><table class="summary-table"><thead><tr><th>Loại Phòng</th><th>Số lượng</th><th>Đêm</th><th>Người lớn</th><th>Em bé</th><th>Trẻ em</th><th>Phần trăm</th></tr></thead><tbody class="pms-grouped-rows" data-source="rows" data-group-by="SummaryRoomTypeCode"><tr class="pms-group-footer"><td>{{row.SummaryRoomTypeCode}}</td><td>{{group.distinct.RentalRoomId}}</td><td>{{group.sum.RoomNight}}</td><td>{{group.sum.Adult}}</td><td>{{group.sum.Infant}}</td><td>{{group.sum.Child}}</td><td>{{row.RoomTypePercent}}%</td></tr></tbody></table>
HTML;
    }

    private function css(): string
    {
        return <<<'CSS'
body{font-family:Arial,sans-serif;font-size:8.5px;color:#111}.hotel-header{display:grid;grid-template-columns:175px 1fr;min-height:65px;align-items:center}.hotel-logo{min-height:55px}.hotel-logo-image{max-width:115px;max-height:55px}.hotel-information{font-size:9px;line-height:1.8}hr{border:0;border-top:1.5px solid #000}h1{text-align:center;font-size:20px;margin:5px 0}.period{text-align:center;font-weight:bold}.inhouse-table,.summary-table{width:100%;border-collapse:collapse;table-layout:fixed}.inhouse-table th,.inhouse-table td,.summary-table th,.summary-table td{border:1px solid #ccc;padding:4px;vertical-align:middle;overflow-wrap:anywhere}.inhouse-table th,.summary-table th{background:#d9e1ec;text-align:center}.date-row{color:#851c1c;font-weight:bold}.company-row,.booking-row{font-weight:bold}.inhouse-table td:nth-child(2),.inhouse-table td:nth-child(3),.inhouse-table td:nth-child(5),.inhouse-table td:nth-child(6),.inhouse-table td:nth-child(7),.inhouse-table td:nth-child(8),.inhouse-table td:nth-child(9),.inhouse-table td:nth-child(10),.inhouse-table td:nth-child(12){text-align:center}.inhouse-table td:nth-child(11){text-align:right}.summary-table{margin-top:45px}.summary-table th,.summary-table td{text-align:center}h2{text-align:center;margin-top:45px;font-size:18px}.rate-col{display:table-cell}.detail-col{display:table-cell}@media print{thead{display:table-header-group}tr{break-inside:avoid}}
CSS;
    }
};
