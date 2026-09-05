<?php

use Illuminate\Support\Facades\DB;

return new class
{
    public function apply(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::table('templates')->where('report', 'EXTRA_BED_STANDARD')->update([
            'content_json' => json_encode($this->contentJson(), JSON_UNESCAPED_UNICODE),
            'content_html' => $this->html(),
            'css' => $this->css(),
            'updated_at' => now(),
        ]);
    }

    private function html(): string
    {
        return <<<'HTML'
<div class="report-header"><div class="hotel-header"><div class="hotel-logo">{{hotel.logo}}</div><div class="hotel-information"><div><b>Địa chỉ:</b> {{hotel.address}}</div><div><b>Nhân viên:</b> {{report.generated_by}} <b>Ngày:</b> {{report.generated_at}}</div></div></div><hr><h1>BÁO CÁO EXTRA BED</h1><p class="period"><b>Ngày:</b> {{parameters.p_from_date}} ~ {{parameters.p_to_date}}</p></div>
<table class="extra-bed-table"><thead><tr><th>Mã ĐK</th><th>Tên ĐK</th><th>Phòng</th><th>Tên khách</th><th>Loại phòng</th><th>Đến</th><th>Đi</th><th>NL</th><th>EB</th><th>TE</th><th>Giá phòng</th><th>Ngày EB</th><th>SL EB</th><th>Giá EB</th><th>Thành tiền</th></tr></thead>
<tbody class="pms-grouped-rows" data-source="rows" data-group-by="ServiceDateSort"><tr class="pms-group-header"><td colspan="15"><b>Ngày: {{row.ServiceDate}}</b></td></tr><tr class="pms-detail-row"><td>{{row.BookingCode}}</td><td>{{row.BookingName}}</td><td>{{row.Room}}</td><td>{{row.Guest}}</td><td>{{row.RoomType}}</td><td>{{row.ArrivalDate}}</td><td>{{row.DepartureDate}}</td><td>{{row.Adults}}</td><td>{{row.Babies}}</td><td>{{row.Children}}</td><td>{{row.RoomRate|number}}</td><td>{{row.ServiceDate}}</td><td>{{row.ExtraBedQuantity|number}}</td><td>{{row.ExtraBedRate|number}}</td><td>{{row.ExtraBedTotal|number}}</td></tr></tbody></table>
<div class="total"><b>Tổng số dòng:</b> {{summary.row_count}} <b>Tổng SL EB:</b> {{aggregate.rows.sum.ExtraBedQuantity|number}} <b>Tổng tiền:</b> {{aggregate.rows.sum.ExtraBedTotal|number}}</div>
HTML;
    }

    private function contentJson(): array
    {
        $definitions = [
            ['Mã ĐK', 'BookingCode', '8%'], ['Tên ĐK', 'BookingName', '11%'], ['Phòng', 'Room', '5%'], ['Tên khách', 'Guest', '11%'], ['Loại phòng', 'RoomType', '8%'],
            ['Đến', 'ArrivalDate', '6%'], ['Đi', 'DepartureDate', '6%'], ['NL', 'Adults', '3%'], ['EB', 'Babies', '3%'], ['TE', 'Children', '3%'],
            ['Giá phòng', 'RoomRate', '8%'], ['Ngày EB', 'ServiceDate', '7%'], ['SL EB', 'ExtraBedQuantity', '5%'], ['Giá EB', 'ExtraBedRate', '7%'], ['Thành tiền', 'ExtraBedTotal', '8%'],
        ];
        $numeric = ['RoomRate', 'ExtraBedQuantity', 'ExtraBedRate', 'ExtraBedTotal'];
        $center = ['Room', 'ArrivalDate', 'DepartureDate', 'Adults', 'Babies', 'Children', 'ServiceDate', 'ExtraBedQuantity'];
        $columns = array_map(function (array $definition) use ($numeric, $center): array {
            $field = $definition[1];
            return [
                'header' => $definition[0],
                'value' => 'row.'.$field,
                'width' => $definition[2],
                'align' => in_array($field, $center, true) ? 'center' : (in_array($field, $numeric, true) ? 'right' : 'left'),
                'format' => in_array($field, $numeric, true) ? 'number' : null,
            ];
        }, $definitions);

        return [
            'header' => [[
                'id' => 'extra_bed_header',
                'type' => 'text',
                'content' => '<h1>BÁO CÁO EXTRA BED</h1><p class="period"><b>Ngày:</b> {{parameters.p_from_date}} ~ {{parameters.p_to_date}}</p>',
            ]],
            'detail' => [[
                'id' => 'extra_bed_detail',
                'type' => 'table',
                'dataSource' => 'rows',
                'tableType' => 'dynamic',
                'tableStyle' => 'grid',
                'columns' => $columns,
                'groups' => [['field' => 'ServiceDateSort', 'label' => '<b>Ngày: {{row.ServiceDate}}</b>', 'sort' => 'ASC']],
            ]],
            'footer' => [[
                'id' => 'extra_bed_footer',
                'type' => 'text',
                'content' => '<div class="total"><b>Tổng số dòng:</b> {{summary.row_count}} <b>Tổng SL EB:</b> {{aggregate.rows.sum.ExtraBedQuantity|number}} <b>Tổng tiền:</b> {{aggregate.rows.sum.ExtraBedTotal|number}}</div>',
            ]],
        ];
    }

    private function css(): string
    {
        return <<<'CSS'
body { color: #111; font-family: Arial, Helvetica, sans-serif; font-size: 8px; }
.hotel-header { display: grid; grid-template-columns: 175px 1fr; align-items: center; min-height: 55px; }.hotel-logo { min-height: 45px; }.hotel-logo img { max-width: 120px; max-height: 45px; object-fit: contain; }.hotel-information { line-height: 1.6; } hr { margin: 0 0 8px; border: 0; border-top: 1px solid #111; } h1 { margin: 0; text-align: center; font-size: 16px; }.period { margin: 6px 0 8px; text-align: center; }
.extra-bed-table { width: 100%; border-collapse: collapse; table-layout: fixed; }.extra-bed-table th, .extra-bed-table td { border: 1px solid #c7cdd6; padding: 3px 2px; line-height: 1.1; overflow-wrap: anywhere; }.extra-bed-table th { background: #d9e1ec; text-align: center; }.extra-bed-table .pms-group-header td { background: #f4f6f8; }.extra-bed-table td:nth-child(3), .extra-bed-table td:nth-child(6), .extra-bed-table td:nth-child(7), .extra-bed-table td:nth-child(8), .extra-bed-table td:nth-child(9), .extra-bed-table td:nth-child(10), .extra-bed-table td:nth-child(12), .extra-bed-table td:nth-child(13) { text-align: center; }.extra-bed-table td:nth-child(11), .extra-bed-table td:nth-child(14), .extra-bed-table td:nth-child(15) { text-align: right; white-space: nowrap; }.total { margin-top: 6px; text-align: right; } @media print { thead { display: table-header-group; } tr { break-inside: avoid; } }
CSS;
    }
};
