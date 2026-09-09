<?php

use Illuminate\Support\Facades\DB;

return new class
{
    public function apply(): void
    {
        DB::table('templates')->where('report', 'RETURNING_GUESTS_STANDARD')->update([
            'content_json' => json_encode($this->blocks(), JSON_UNESCAPED_UNICODE),
            'content_html' => $this->html(),
            'css' => $this->css(),
            'updated_at' => now(),
        ]);
    }

    private function blocks(): array
    {
        return [
            'header' => [
                ['id' => 'returning_hotel_header', 'type' => 'columns', 'columns' => [
                    ['width' => '36%', 'blocks' => [['id' => 'returning_logo', 'type' => 'image', 'content' => 'hotel.logo', 'imageUrl' => '', 'style' => ['textAlign' => 'left']]]],
                    ['width' => '64%', 'blocks' => [['id' => 'returning_hotel_meta', 'type' => 'text', 'content' => '<div><b>Địa chỉ:</b>&nbsp;&nbsp; {{hotel.address}}</div><div><b>Nhân viên:</b>&nbsp;&nbsp; {{report.generated_by}} <b style="float:right">Ngày:&nbsp;&nbsp; {{report.generated_at}}</b></div>', 'style' => ['fontSize' => '9px', 'lineHeight' => '1.8']]]],
                ]],
                ['id' => 'returning_divider', 'type' => 'divider', 'content' => '<hr class="header-divider">'],
                ['id' => 'returning_title', 'type' => 'text', 'content' => '<h1>BÁO CÁO KHÁCH QUAY LẠI</h1>', 'style' => ['textAlign' => 'center', 'fontSize' => '20px', 'fontWeight' => 'bold']],
                ['id' => 'returning_period', 'type' => 'text', 'content' => '<p class="report-period"><b>Ngày:</b>&nbsp;&nbsp; {{parameters.p_from_date}} &nbsp;&nbsp; ~ &nbsp;&nbsp; {{parameters.p_to_date}}</p>', 'style' => ['textAlign' => 'center', 'fontSize' => '9px', 'fontWeight' => 'bold']],
            ],
            'detail' => [[
                'id' => 'returning_rows',
                'type' => 'table',
                'dataSource' => 'rows',
                'tableType' => 'dynamic',
                'tableStyle' => 'grid',
                'groups' => [[
                    'id' => 'returning_passport_group',
                    'field' => 'Passport',
                    'label' => '{{row.GuestName}}',
                    'className' => 'guest-group',
                    'sort' => 'ASC',
                    'headerCells' => [
                        ['id' => 'returning_group_stt', 'type' => 'binding', 'binding' => 'row.STT', 'colspan' => 1, 'align' => 'center'],
                        ['id' => 'returning_group_guest', 'type' => 'text', 'content' => 'Guest Name: {{row.GuestName}}', 'colspan' => 3, 'align' => 'left', 'className' => 'guest-group'],
                        ['id' => 'returning_group_passport', 'type' => 'text', 'content' => 'ID/Passport: {{row.Passport}}', 'colspan' => 3, 'align' => 'left', 'className' => 'passport-group'],
                        ['id' => 'returning_group_total', 'type' => 'text', 'content' => 'Total visit: {{group.distinct.VisitKey}}', 'colspan' => 1, 'align' => 'right', 'className' => 'visit-total'],
                    ],
                ]],
                'groupBy' => 'Passport',
                'columns' => $this->columns(),
                'customRows' => [],
            ]],
            'footer' => [['id' => 'returning_total', 'type' => 'text', 'content' => '<div class="grand-total"><b>Tổng số khách:</b> {{aggregate.rows.distinct_count.Passport}}</div>', 'style' => ['fontWeight' => 'bold']]],
        ];
    }

    private function columns(): array
    {
        return [
            ['header' => 'STT', 'value' => 'row.STT', 'width' => '6%', 'align' => 'center'],
            ['header' => 'Mã đăng ký', 'value' => 'row.BookingId', 'width' => '12%', 'align' => 'center'],
            ['header' => 'Phòng', 'value' => 'row.Room', 'width' => '9%', 'align' => 'center'],
            ['header' => 'Loại phòng', 'value' => 'row.RoomType', 'width' => '13%', 'align' => 'center'],
            ['header' => 'Giá phòng', 'value' => 'row.Rate|number', 'width' => '13%', 'align' => 'right'],
            ['header' => 'Ngày đến', 'value' => 'row.ArrivalDate', 'width' => '12%', 'align' => 'center'],
            ['header' => 'Ngày đi', 'value' => 'row.DepartureDate', 'width' => '12%', 'align' => 'center'],
            ['header' => 'Công ty', 'value' => 'row.Company', 'width' => '23%', 'align' => 'left'],
        ];
    }

    private function html(): string
    {
        return <<<'HTML'
<div class="report-header-band"><div class="hotel-header"><div class="hotel-logo">{{hotel.logo}}</div><div class="hotel-information"><div><b>Địa chỉ:</b> {{hotel.address}}</div><div><b>Nhân viên:</b> {{report.generated_by}} <b class="generated-date">Ngày:</b> {{report.generated_at}}</div></div></div><hr class="header-divider"><h1>BÁO CÁO KHÁCH QUAY LẠI</h1><p class="report-period"><b>Ngày:</b><span>{{parameters.p_from_date}}</span><b>~</b><span>{{parameters.p_to_date}}</span></p></div>
<table class="returning-table"><thead><tr><th>STT</th><th>Mã đăng ký</th><th>Phòng</th><th>Loại phòng</th><th>Giá phòng</th><th>Ngày đến</th><th>Ngày đi</th><th>Công ty</th></tr></thead><tbody class="pms-grouped-rows" data-source="rows" data-group-configured="1" data-group-by="Passport"><tr class="pms-group-header" data-group-level="0" data-group-field="Passport" data-group-sort="ASC"><td>{{row.STT}}</td><td colspan="3" class="guest-group">Guest Name: {{row.GuestName}}</td><td colspan="3" class="passport-group">ID/Passport: {{row.Passport}}</td><td class="visit-total">Total visit: {{group.distinct.VisitKey}}</td></tr><tr class="pms-detail-row"><td></td><td>{{row.BookingId}}</td><td>{{row.Room}}</td><td>{{row.RoomType}}</td><td>{{row.Rate|number}}</td><td>{{row.ArrivalDate}}</td><td>{{row.DepartureDate}}</td><td>{{row.Company}}</td></tr></tbody></table>
<div class="grand-total"><b>Tổng số khách:</b> {{aggregate.rows.distinct_count.Passport}}</div>
HTML;
    }

    private function css(): string
    {
        return 'body{color:#111;font-family:Arial,Helvetica,sans-serif;font-size:9px}.hotel-header{display:grid;grid-template-columns:36% 64%;align-items:center;min-height:66px}.hotel-logo img,.hotel-logo-image{display:block;max-width:120px;max-height:70px;object-fit:contain}.hotel-information{line-height:1.8}.generated-date{margin-left:36px}.header-divider{margin:0 0 22px;border:0;border-top:1px solid #111}h1{margin:0;text-align:center;font-size:20px;line-height:1.25}.report-period{display:flex;justify-content:center;gap:24px;margin:20px 0 12px}.returning-table{width:100%;border-collapse:collapse;table-layout:fixed}.returning-table th,.returning-table td{border:1px solid #999;padding:4px;line-height:1.15;vertical-align:middle;overflow-wrap:anywhere}.returning-table th{background:#d9e1ec;text-align:center;font-weight:700}.returning-table td:first-child,.returning-table td:nth-child(2),.returning-table td:nth-child(3),.returning-table td:nth-child(4),.returning-table td:nth-child(5),.returning-table td:nth-child(6),.returning-table td:nth-child(7){text-align:center}.guest-group,.passport-group,.visit-total{font-weight:700}.guest-group,.passport-group{text-align:left!important}.visit-total{text-align:right}.grand-total{margin-top:0;padding:5px;background:#d9e1ec;font-weight:700}@media print{thead{display:table-header-group}tr{break-inside:avoid}}';
    }
};
