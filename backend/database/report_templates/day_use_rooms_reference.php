<?php

use Illuminate\Support\Facades\DB;

return new class
{
    public function apply(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::table('templates')->where('report', 'DAY_USE_ROOMS_STANDARD')->update([
            'content_json' => json_encode($this->contentJson(), JSON_UNESCAPED_UNICODE),
            'content_html' => $this->html(),
            'css' => $this->css(),
            'updated_at' => now(),
        ]);
    }

    public function contentJson(): array
    {
        return [
            'header' => [
                ['id' => 'day_use_hotel', 'type' => 'text', 'content' => '<div class="hotel-header"><div class="hotel-logo">{{hotel.logo}}</div><div><b>Địa chỉ:</b> {{hotel.address}}<br><b>Nhân viên:</b> {{report.generated_by}} <b>Ngày:</b> {{report.generated_at}}</div></div>', 'style' => ['fontSize' => '9px']],
                ['id' => 'day_use_title', 'type' => 'text', 'content' => '<h1>BÁO CÁO PHÒNG Ở TRONG NGÀY (DAY USE)</h1><p class="report-period">Ngày: {{parameters.p_from_date}} &nbsp;~&nbsp; {{parameters.p_to_date}}</p>', 'style' => ['textAlign' => 'center']],
            ],
            'detail' => [[
                'id' => 'day_use_table', 'type' => 'table', 'dataSource' => 'rows', 'tableType' => 'dynamic', 'tableStyle' => 'grid', 'isNew' => false,
                'groupBy' => 'ArrivalDateSort',
                'groupHeader' => '<td colspan="13" class="date-row">Ngày đến: {{row.ArrivalDate}}</td>',
                'groupFooter' => '<td colspan="3" class="total-label">Tổng:</td><td class="total-value">{{group.distinct.RentalRoomId}}</td><td colspan="3"></td><td class="total-value">{{group.sum.Adult}}</td><td class="total-value">{{group.sum.Baby}}</td><td class="total-value">{{group.sum.Child}}</td><td></td><td class="total-value">{{group.sum.Rate}}</td><td></td>',
                'columns' => $this->columns(),
            ]],
            'footer' => [],
        ];

        return [
            'header' => [
                ['id' => 'day_use_hotel', 'type' => 'text', 'content' => '<div class="hotel-header"><div class="hotel-logo">{{hotel.logo}}</div><div><b>Địa chỉ:</b> {{hotel.address}}<br><b>Nhân viên:</b> {{report.generated_by}} <b>Ngày:</b> {{report.generated_at}}</div></div>', 'style' => ['fontSize' => '9px']],
                ['id' => 'day_use_title', 'type' => 'text', 'content' => '<h1>BÁO CÁO PHÒNG Ở TRONG NGÀY (DAY USE)</h1><p class="report-period">Ngày: {{parameters.p_from_date}} &nbsp;~&nbsp; {{parameters.p_to_date}}</p>', 'style' => ['textAlign' => 'center']],
            ],
            'detail' => [[
                'id' => 'day_use_table', 'type' => 'table', 'dataSource' => 'rows', 'tableType' => 'dynamic', 'tableStyle' => 'grid', 'isNew' => false,
                'groupBy' => 'ArrivalDateSort',
                'groupHeader' => '<td colspan="12" class="date-row">Ngày đến: {{row.ArrivalDate}}</td>',
                'groupFooter' => '<td colspan="3" class="total-label">Tổng:</td><td class="total-value">{{group.distinct.RentalRoomId}}</td><td colspan="3"></td><td class="total-value">{{group.sum.Adult}}</td><td class="total-value">{{group.sum.Baby}}</td><td class="total-value">{{group.sum.Child}}</td><td class="total-value">{{group.sum.Rate}}</td><td></td>',
                'columns' => $this->columns(),
            ]],
            'footer' => [],
        ];
    }

    private function columns(): array
    {
        $columns = [
            ['header' => 'STT', 'value' => 'row.STT', 'width' => '4%', 'align' => 'center'],
            ['header' => 'Mã ĐK', 'value' => 'row.BookingId', 'width' => '8%', 'align' => 'center'],
            ['header' => 'Công ty', 'value' => 'row.Company', 'width' => '12%', 'align' => 'left'],
            ['header' => 'Phòng', 'value' => 'row.Room', 'width' => '6%', 'align' => 'center'],
            ['header' => 'Loại phòng', 'value' => 'row.RoomType', 'width' => '8%', 'align' => 'center'],
            ['header' => 'Ngày đến', 'value' => 'row.ArrivalDate', 'width' => '12%', 'align' => 'center'],
            ['header' => 'Ngày đi', 'value' => 'row.DepartureDate', 'width' => '12%', 'align' => 'center'],
            ['header' => 'Người lớn', 'value' => 'row.Adult', 'width' => '6%', 'align' => 'center'],
            ['header' => 'Em bé', 'value' => 'row.Baby', 'width' => '6%', 'align' => 'center'],
            ['header' => 'Trẻ em', 'value' => 'row.Child', 'width' => '6%', 'align' => 'center'],
            ['header' => 'N.Lớn/EB/T.Em', 'value' => 'row.AdultBabyChild', 'width' => '10%', 'align' => 'center'],
            ['header' => 'Giá phòng', 'value' => 'row.Rate', 'width' => '8%', 'align' => 'right', 'format' => 'number'],
            ['header' => 'Ghi chú', 'value' => 'row.Note', 'width' => '10%', 'align' => 'left'],
        ];
        return array_values(array_filter(
            $columns,
            static fn (array $column): bool => $column['value'] !== 'row.AdultBabyChild'
        ));
    }

    public function html(): string
    {
        $html = <<<'HTML'
<div class="report-header-band"><div class="hotel-header"><div class="hotel-logo">{{hotel.logo}}</div><div class="hotel-information"><div><b>Địa chỉ:</b> {{hotel.address}}</div><div><b>Nhân viên:</b> {{report.generated_by}} <b>Ngày:</b> {{report.generated_at}}</div></div></div><hr class="header-divider"><h1>BÁO CÁO PHÒNG Ở TRONG NGÀY (DAY USE)</h1><p class="report-period"><b>Ngày:</b> {{parameters.p_from_date}} &nbsp;~&nbsp; {{parameters.p_to_date}}</p></div>
<table class="day-use-table"><thead><tr><th>STT</th><th>Mã ĐK</th><th>Công ty</th><th>Phòng</th><th>Loại phòng</th><th>Ngày đến</th><th>Ngày đi</th><th>Người lớn</th><th>Em bé</th><th>Trẻ em</th><th>N.Lớn/EB/T.Em</th><th>Giá phòng</th><th>Ghi chú</th></tr></thead><tbody class="pms-grouped-rows" data-source="rows" data-group-by="ArrivalDateSort"><tr class="pms-group-header"><td colspan="13" class="date-row">Ngày đến: {{row.ArrivalDate}}</td></tr><tr class="pms-detail-row"><td>{{row.STT}}</td><td>{{row.BookingId}}</td><td>{{row.Company}}</td><td>{{row.Room}}</td><td>{{row.RoomType}}</td><td>{{row.ArrivalDate}}</td><td>{{row.DepartureDate}}</td><td>{{row.Adult}}</td><td>{{row.Baby}}</td><td>{{row.Child}}</td><td>{{row.AdultBabyChild}}</td><td>{{row.Rate|number}}</td><td>{{row.Note}}</td></tr><tr class="pms-group-footer"><td colspan="3">Tổng:</td><td>{{group.distinct.RentalRoomId}}</td><td colspan="3"></td><td>{{group.sum.Adult}}</td><td>{{group.sum.Baby}}</td><td>{{group.sum.Child}}</td><td></td><td>{{group.sum.Rate|number}}</td><td></td></tr></tbody></table>
<table class="period-total"><tbody class="pms-grouped-rows" data-source="rows" data-group-by="PeriodGroup"><tr class="pms-group-footer"><td colspan="3">Tổng toàn bộ:</td><td>{{aggregate.distinct.RentalRoomId}}</td><td colspan="3"></td><td>{{aggregate.sum.Adult}}</td><td>{{aggregate.sum.Baby}}</td><td>{{aggregate.sum.Child}}</td><td></td><td>{{aggregate.sum.Rate|number}}</td><td></td></tr></tbody></table>
HTML;
        $html = preg_replace('/<th>[^<]*\/EB\/T\.Em<\/th>/', '', $html);
        $html = str_replace(['<td>{{row.AdultBabyChild}}</td>', 'colspan="13"', '<td></td><td>{{group.sum.Rate'], ['','colspan="12"', '<td>{{group.sum.Rate'], $html);
        return $html;
    }

    public function css(): string
    {
        return <<<'CSS'
body{font-family:Arial,sans-serif;color:#111;font-size:9px}.hotel-header{display:grid;grid-template-columns:180px 1fr;min-height:58px;align-items:center}.hotel-logo img{max-width:120px;max-height:52px}.header-divider{border:0;border-top:1px solid #111;margin:4px 0 8px}h1{text-align:center;font-size:18px;margin:5px 0}.report-period{text-align:center;margin:4px 0 10px}.day-use-table,.period-total{width:100%;border-collapse:collapse;table-layout:fixed}.day-use-table th,.day-use-table td,.period-total td{border:1px solid #b8c0cc;padding:4px;vertical-align:middle;overflow-wrap:anywhere}.day-use-table th{background:#d9e1ec;text-align:center}.date-row{color:#dc2626;font-weight:bold;background:#fff}.pms-group-footer td,.period-total td{background:#d9e1ec;font-weight:bold}.period-total{margin-top:0}.period-total td{text-align:center}.period-total td:first-child{text-align:left}
CSS;
    }
};
