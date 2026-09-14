<?php

use Illuminate\Support\Facades\DB;

return new class
{
    public function apply(): void
    {
        DB::table('templates')->where('report', 'TRANSPORTATION_STANDARD')->update([
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
                [
                    'id' => 'transportation_hotel_header',
                    'type' => 'columns',
                    'style' => ['marginBottom' => '8px', 'whiteSpace' => 'normal'],
                    'columns' => [
                        ['width' => '36%', 'blocks' => [['id' => 'transportation_logo', 'type' => 'image', 'content' => 'hotel.logo', 'imageUrl' => '', 'style' => ['textAlign' => 'left', 'paddingTop' => '4px', 'paddingBottom' => '4px']]]],
                        ['width' => '64%', 'blocks' => [['id' => 'transportation_hotel_meta', 'type' => 'text', 'content' => '<div><b>Địa chỉ:</b>&nbsp;&nbsp; {{hotel.address}}</div><div><b>Nhân viên:</b>&nbsp;&nbsp; {{report.generated_by}} <b style="float:right">Ngày:&nbsp;&nbsp; {{report.generated_at}}</b></div>', 'style' => ['fontSize' => '9px', 'lineHeight' => '1.8', 'paddingTop' => '8px']]]],
                    ],
                ],
                ['id' => 'transportation_divider', 'type' => 'divider', 'content' => '<hr class="header-divider">', 'style' => ['marginBottom' => '12px']],
                ['id' => 'transportation_title', 'type' => 'text', 'content' => '<h1>BÁO CÁO ĐƯA ĐÓN KHÁCH</h1>', 'style' => ['textAlign' => 'center', 'fontSize' => '20px', 'fontWeight' => 'bold', 'marginBottom' => '8px']],
                ['id' => 'transportation_period', 'type' => 'text', 'content' => '<p class="report-period"><b>Ngày:</b>&nbsp;&nbsp; {{parameters.p_from_date}} &nbsp;&nbsp; ~ &nbsp;&nbsp; {{parameters.p_to_date}}</p>', 'style' => ['textAlign' => 'center', 'fontSize' => '9px', 'fontWeight' => 'bold', 'marginBottom' => '10px']],
            ],
            'detail' => [[
                'id' => 'transportation_rows',
                'type' => 'table',
                'dataSource' => 'rows',
                'tableType' => 'dynamic',
                'tableStyle' => 'grid',
                'groups' => [
                    ['id' => 'transportation_date_group', 'field' => 'DateGroup', 'label' => 'Ngày: {{row.DateGroup}}', 'className' => 'date-row', 'sort' => 'ASC', 'enabledBy' => 'parameters.p_group_by_date'],
                    ['id' => 'transportation_type_group', 'field' => 'IsArrival', 'label' => '{{row.TransportGroup}}', 'className' => 'type-row', 'sort' => 'DESC'],
                ],
                'style' => ['fontSize' => '9px', 'whiteSpace' => 'normal'],
                'columns' => $this->columns(),
                'customRows' => [[
                    'id' => 'transportation_date_total',
                    'scope' => 'group',
                    'level' => 0,
                    'enabledBy' => '',
                    'className' => 'transportation-total',
                    'cells' => [
                        ['id' => 'transportation_total_label', 'type' => 'text', 'content' => 'Tổng:', 'colspan' => 9, 'align' => 'right', 'format' => '', 'className' => 'total-label'],
                        ['id' => 'transportation_total_value', 'type' => 'binding', 'binding' => 'group.count', 'content' => '{{group.count}}', 'colspan' => 1, 'align' => 'center', 'format' => '', 'className' => 'total-value'],
                    ],
                ]],
            ]],
            'footer' => [],
        ];
    }

    private function columns(): array
    {
        return [
            ['header' => 'STT', 'value' => 'row.STT', 'width' => '4%', 'align' => 'center'],
            ['header' => 'Mã ĐK', 'value' => 'row.BookingId', 'width' => '8%', 'align' => 'center'],
            ['header' => 'Tên ĐK', 'value' => 'row.BookingName', 'width' => '15%', 'align' => 'left'],
            ['header' => 'Loại phương tiện', 'value' => 'row.ArrivalBy', 'width' => '12%', 'align' => 'center'],
            ['header' => 'Mã chuyến bay', 'value' => 'row.CodeNumber', 'width' => '10%', 'align' => 'center'],
            ['header' => 'Ngày', 'value' => 'row.TransportDate', 'width' => '9%', 'align' => 'center'],
            ['header' => 'Giờ', 'value' => 'row.TransportTime', 'width' => '7%', 'align' => 'center'],
            ['header' => 'Giá', 'value' => 'row.Rate|number', 'width' => '8%', 'align' => 'right'],
            ['header' => 'Điểm đón/trả', 'value' => 'row.PickupDropoff', 'width' => '15%', 'align' => 'left'],
            ['header' => 'Ghi chú', 'value' => 'row.Note', 'width' => '12%', 'align' => 'left'],
        ];
    }

    private function html(): string
    {
        return <<<'HTML'
<div class="report-header-band"><div class="hotel-header"><div class="hotel-logo">{{hotel.logo}}</div><div class="hotel-information"><div><b>Địa chỉ:</b> {{hotel.address}}</div><div><b>Nhân viên:</b> {{report.generated_by}} <b class="generated-date">Ngày:</b> {{report.generated_at}}</div></div></div><hr class="header-divider"><h1>BÁO CÁO ĐƯA ĐÓN KHÁCH</h1><p class="report-period"><b>Ngày:</b><span>{{parameters.p_from_date}}</span><b>~</b><span>{{parameters.p_to_date}}</span></p></div>
<div class="report-detail-band"><table class="transportation-table"><thead><tr><th>STT</th><th>Mã ĐK</th><th>Tên ĐK</th><th>Loại phương tiện</th><th>Mã chuyến bay</th><th>Ngày</th><th>Giờ</th><th>Giá</th><th>Điểm đón/trả</th><th>Ghi chú</th></tr></thead><tbody class="pms-grouped-rows" data-source="rows" data-group-configured="1" data-group-by="DateGroup" data-subgroup-by="IsArrival"><tr class="pms-group-header" data-group-level="0" data-group-field="DateGroup" data-group-enabled-by="parameters.p_group_by_date"><td colspan="10" class="date-row">Ngày: {{row.DateGroup}}</td></tr><tr class="pms-group-header" data-group-level="1" data-group-field="IsArrival" data-group-sort="DESC"><td colspan="10" class="type-row">{{row.TransportGroup}}</td></tr><tr class="pms-detail-row"><td>{{row.STT}}</td><td>{{row.BookingId}}</td><td>{{row.BookingName}}</td><td>{{row.ArrivalBy}}</td><td>{{row.CodeNumber}}</td><td>{{row.TransportDate}}</td><td>{{row.TransportTime}}</td><td>{{row.Rate|number}}</td><td>{{row.PickupDropoff}}</td><td>{{row.Note}}</td></tr><tr class="pms-group-custom-row" data-group-level="0"><td colspan="9" class="total-label">Tổng:</td><td class="total-value">{{group.count}}</td></tr></tbody></table></div>
HTML;
    }

    private function css(): string
    {
        return <<<'CSS'
body { color: #111; font-family: Arial, Helvetica, sans-serif; font-size: 9px; }
.hotel-header { display: grid; grid-template-columns: 180px 1fr; align-items: center; min-height: 66px; }
.hotel-logo { display: flex; align-items: center; min-height: 58px; }
.hotel-logo-image { display: block; max-width: 120px; max-height: 58px; object-fit: contain; }
.hotel-information { line-height: 1.8; }
.hotel-information .generated-date { margin-left: 36px; }
.header-divider { margin: 0 0 5px; border: 0; border-top: 1.5px solid #000; }
h1 { margin: 0; text-align: center; font-size: 20px; line-height: 1.25; }
.report-period { display: flex; justify-content: center; gap: 24px; margin: 7px 0 12px; }
.transportation-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
.transportation-table th, .transportation-table td { border: 1px solid #9aa4b2; padding: 4px; line-height: 1.15; vertical-align: middle; overflow-wrap: anywhere; }
.transportation-table th { background: #d9e1ec; text-align: center; font-weight: 700; }
.date-row { color: #111; background: #d9e1ec; font-weight: 700; text-align: left !important; }
.type-row { background: #eef2f7; font-weight: 700; text-align: left !important; }
.total-label, .total-value { background: #f4f4f4; font-weight: 700; }
.total-label { text-align: right !important; }
.total-value { text-align: center !important; }
@media print { thead { display: table-header-group; } tr { break-inside: avoid; } }
CSS;
    }
};
