<?php

use Illuminate\Support\Facades\DB;

return new class
{
    public function apply(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::table('templates')->where('report', 'SUPPLEMENTARY_SERVICES_STANDARD')->update([
            'content_json' => json_encode($this->contentJson(), JSON_UNESCAPED_UNICODE),
            'content_html' => $this->html(),
            'css' => $this->css(),
            'updated_at' => now(),
        ]);
    }

    private function html(): string
    {
        return <<<'HTML'
<div class="report-header"><div class="hotel-header"><div class="hotel-logo">{{hotel.logo}}</div><div class="hotel-information"><div><b>Địa chỉ:</b> {{hotel.address}}</div><div><b>Nhân viên:</b> {{report.generated_by}} <b>Ngày:</b> {{report.generated_at}}</div></div></div><hr><h1>BÁO CÁO DỊCH VỤ BỔ SUNG</h1><p class="period"><b>Ngày:</b> {{parameters.p_from_date}} ~ {{parameters.p_to_date}}</p></div>
<table class="supplementary-services-table"><thead><tr><th>Mã ĐK</th><th>Phòng</th><th>Tên Khách</th><th>Ngày Dịch Vụ</th><th>Dịch Vụ</th><th>Số Lượng</th><th>Giá</th><th>Tổng</th></tr></thead>
<tbody class="pms-grouped-rows" data-source="rows" data-group-by="ServiceName">
<tr class="pms-group-header"><td colspan="8"><b>Dịch vụ: {{row.ServiceName}}</b></td></tr>
<tr class="pms-detail-row"><td>{{row.BookingCode}}</td><td>{{row.Room}}</td><td>{{row.Guest}}</td><td>{{row.ServiceDate}}</td><td>{{row.ServiceName}}</td><td>{{row.Quantity}}</td><td>{{row.Rate|number}}</td><td>{{row.Total|number}}</td></tr>
<tr class="pms-group-footer"><td colspan="4"></td><td><b>Tổng:</b></td><td><b>{{row.GroupQuantity|number}}</b></td><td></td><td><b>{{row.GroupTotal|number}}</b></td></tr>
</tbody></table>
<div class="total"><b>Tổng số dòng:</b> {{summary.row_count}} <b>Tổng tiền:</b> {{aggregate.rows.sum.Total|number}}</div>
HTML;
    }

    private function contentJson(): array
    {
        return [
            'header' => [[
                'id' => 'supplementary_header',
                'type' => 'text',
                'content' => '<div class="report-header"><div class="hotel-header"><div class="hotel-logo">{{hotel.logo}}</div><div class="hotel-information"><div><b>Địa chỉ:</b> {{hotel.address}}</div><div><b>Nhân viên:</b> {{report.generated_by}} <b>Ngày:</b> {{report.generated_at}}</div></div></div><hr><h1>BÁO CÁO DỊCH VỤ BỔ SUNG</h1><p class="period"><b>Ngày:</b> {{parameters.p_from_date}} ~ {{parameters.p_to_date}}</p></div>',
            ]],
            'detail' => [[
                'id' => 'supplementary_detail_table',
                'type' => 'table',
                'dataSource' => 'rows',
                'tableType' => 'dynamic',
                'tableStyle' => 'grid',
                'columns' => [
                    ['header' => 'Mã ĐK', 'value' => 'row.BookingCode', 'width' => '12%', 'align' => 'left'],
                    ['header' => 'Phòng', 'value' => 'row.Room', 'width' => '8%', 'align' => 'center'],
                    ['header' => 'Tên Khách', 'value' => 'row.Guest', 'width' => '17%', 'align' => 'left'],
                    ['header' => 'Ngày Dịch Vụ', 'value' => 'row.ServiceDate', 'width' => '12%', 'align' => 'center'],
                    ['header' => 'Dịch Vụ', 'value' => 'row.ServiceName', 'width' => '19%', 'align' => 'left'],
                    ['header' => 'Số Lượng', 'value' => 'row.Quantity', 'width' => '9%', 'align' => 'center'],
                    ['header' => 'Giá', 'value' => 'row.Rate', 'width' => '11%', 'align' => 'right', 'format' => 'number'],
                    ['header' => 'Tổng', 'value' => 'row.Total', 'width' => '12%', 'align' => 'right', 'format' => 'number'],
                ],
                'groups' => [['field' => 'ServiceName', 'label' => '<b>Dịch vụ: {{row.ServiceName}}</b>', 'sort' => 'ASC']],
                'groupFooter' => '<td colspan="4"></td><td><b>Tổng:</b></td><td><b>{{row.GroupQuantity|number}}</b></td><td></td><td><b>{{row.GroupTotal|number}}</b></td>',
            ]],
            'footer' => [[
                'id' => 'supplementary_footer',
                'type' => 'text',
                'content' => '<div class="total"><b>Tổng số dòng:</b> {{summary.row_count}} <b>Tổng tiền:</b> {{aggregate.rows.sum.Total|number}}</div>',
            ]],
        ];
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
.supplementary-services-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
.supplementary-services-table th, .supplementary-services-table td { border: 1px solid #c7cdd6; padding: 4px 3px; line-height: 1.15; overflow-wrap: anywhere; }
.supplementary-services-table th { background: #d9e1ec; text-align: center; }
.supplementary-services-table .pms-group-header td { background: #f4f6f8; }
.supplementary-services-table .pms-group-footer td { background: #fafafa; }
.supplementary-services-table td:nth-child(2), .supplementary-services-table td:nth-child(4), .supplementary-services-table td:nth-child(6) { text-align: center; }
.supplementary-services-table td:nth-child(7), .supplementary-services-table td:nth-child(8) { text-align: right; white-space: nowrap; }
.total { margin-top: 6px; text-align: right; }
@media print { thead { display: table-header-group; } tr { break-inside: avoid; } }
CSS;
    }
};
