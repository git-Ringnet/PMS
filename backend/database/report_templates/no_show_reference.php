<?php

use Illuminate\Support\Facades\DB;

return new class
{
    public function apply(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') return;
        DB::table('templates')->where('report', 'NO_SHOW_STANDARD')->update([
            'content_json' => json_encode(['header' => $this->header(), 'detail' => $this->detail(), 'footer' => $this->footer()], JSON_UNESCAPED_UNICODE),
            'content_html' => $this->html(), 'css' => $this->css(), 'updated_at' => now(),
        ]);
    }

    private function header(): array
    {
        return [
            ['id' => 'no_show_hotel', 'type' => 'text', 'content' => '<div class="hotel-header"><div class="hotel-logo">{{hotel.logo}}</div><div class="hotel-information"><div><b>Địa chỉ:</b> {{hotel.address}}</div><div><b>Nhân viên:</b> {{report.generated_by}} <b>Ngày:</b> {{report.generated_at}}</div></div></div>'],
            ['id' => 'no_show_divider', 'type' => 'divider', 'content' => '<hr>'],
            ['id' => 'no_show_title', 'type' => 'text', 'content' => '<h1>BÁO CÁO PHÒNG NO SHOW</h1>', 'style' => ['textAlign' => 'center']],
            ['id' => 'no_show_period', 'type' => 'text', 'content' => '<p class="period"><b>Ngày:</b> {{parameters.p_from_date}} ~ {{parameters.p_to_date}}</p>', 'style' => ['textAlign' => 'center']],
        ];
    }

    private function detail(): array
    {
        return [[
            'id' => 'no_show_table', 'type' => 'table', 'dataSource' => 'rows', 'tableType' => 'dynamic', 'tableStyle' => 'grid',
            'groups' => [['id' => 'no_show_charge_group', 'field' => 'RoomType', 'label' => '{{row.RoomType}}', 'className' => 'charge-group', 'sort' => 'ASC']],
            'groupBy' => 'RoomType', 'groupHeader' => '<td colspan="12" class="charge-group">{{row.RoomType}}</td>', 'columns' => $this->columns(),
            'customRows' => [[
                'id' => 'no_show_total_row', 'enabledBy' => '', 'className' => 'total-row',
                'cells' => [
                    ['id' => 'no_show_total_label', 'type' => 'text', 'content' => 'Tổng', 'colspan' => 12, 'align' => 'right', 'format' => '', 'className' => 'total-label'],
                    ['id' => 'no_show_total_value', 'type' => 'count', 'content' => '', 'binding' => '', 'aggregateField' => '', 'colspan' => 1, 'align' => 'center', 'format' => '', 'className' => 'total-value'],
                ],
            ]],
        ]];
    }

    private function columns(): array
    {
        return [
            ['header' => 'Phòng', 'value' => 'row.Room', 'width' => '6%', 'align' => 'center'], ['header' => 'Mã ĐK', 'value' => 'row.BookingId', 'width' => '8%'],
            ['header' => 'Tên Nhóm', 'value' => 'row.BookingName', 'width' => '15%'], ['header' => 'Công Ty', 'value' => 'row.Company', 'width' => '12%'],
            ['header' => 'Ngày Tạo', 'value' => 'row.BookingDate', 'width' => '8%', 'align' => 'center'], ['header' => 'Ngày Đến', 'value' => 'row.ArrivalDate', 'width' => '8%', 'align' => 'center'],
            ['header' => 'Đêm', 'value' => 'row.NumOfDays', 'width' => '5%', 'align' => 'center'], ['header' => 'Ngày Vắng', 'value' => 'row.NoshowDate', 'width' => '8%', 'align' => 'center'],
            ['header' => 'Giờ', 'value' => 'row.NoshowTime', 'width' => '5%', 'align' => 'center'], ['header' => 'Giá', 'value' => 'row.Total', 'width' => '9%', 'format' => 'number', 'align' => 'right'],
            ['header' => 'Người Dùng', 'value' => 'row.Username', 'width' => '8%'], ['header' => 'Ca', 'value' => 'row.Ca', 'width' => '4%', 'align' => 'center'], ['header' => 'Lý Do', 'value' => 'row.Reason', 'width' => '12%'],
        ];
    }

    private function footer(): array { return []; }

    private function html(): string
    {
        return <<<'HTML'
<div class="report-header"><div class="hotel-header"><div class="hotel-logo">{{hotel.logo}}</div><div class="hotel-information"><div><b>Địa chỉ:</b> {{hotel.address}}</div><div><b>Nhân viên:</b> {{report.generated_by}} <b>Ngày:</b> {{report.generated_at}}</div></div></div><hr><h1>BÁO CÁO PHÒNG NO SHOW</h1><p class="period"><b>Ngày:</b> {{parameters.p_from_date}} ~ {{parameters.p_to_date}}</p></div>
<table class="no-show-table"><thead><tr><th>Phòng</th><th>Mã ĐK</th><th>Tên Nhóm</th><th>Công Ty</th><th>Ngày Tạo</th><th>Ngày Đến</th><th>Đêm</th><th>Ngày Vắng</th><th>Giờ</th><th>Giá</th><th>Người Dùng</th><th>Ca</th><th>Lý Do</th></tr></thead><tbody class="pms-grouped-rows" data-source="rows" data-group-configured="1" data-group-by="RoomType"><tr class="pms-group-header"><td colspan="13" class="charge-group">{{row.RoomType}}</td></tr><tr class="pms-detail-row"><td>{{row.Room}}</td><td>{{row.BookingId}}</td><td>{{row.BookingName}}</td><td>{{row.Company}}</td><td>{{row.BookingDate}}</td><td>{{row.ArrivalDate}}</td><td>{{row.NumOfDays}}</td><td>{{row.NoshowDate}}</td><td>{{row.NoshowTime}}</td><td>{{row.Total|number}}</td><td>{{row.Username}}</td><td>{{row.Ca}}</td><td>{{row.Reason}}</td></tr></tbody><tfoot><tr class="pms-custom-row total-row"><td colspan="12" class="total-label">Tổng</td><td class="total-value">{{aggregate.rows.count}}</td></tr></tfoot></table>
HTML;
    }

    private function css(): string
    {
        return <<<'CSS'
body { color:#111; font-family:Arial,Helvetica,sans-serif; font-size:9px; }.hotel-header{display:grid;grid-template-columns:180px 1fr;align-items:center;min-height:60px}.hotel-logo{min-height:50px}.hotel-logo img{max-width:120px;max-height:50px;object-fit:contain}.hotel-information{line-height:1.8}hr{margin:0 0 8px;border:0;border-top:1px solid #111}h1{margin:0;text-align:center;font-size:18px}.period{text-align:center;margin:8px 0 12px}.no-show-table{width:100%;border-collapse:collapse;table-layout:fixed}.no-show-table th,.no-show-table td{border:1px solid #b9c3d0;padding:4px 3px;line-height:1.15;overflow-wrap:anywhere}.no-show-table th{background:#d9e1ec;text-align:center}.charge-group{background:#fff;color:#b91c1c;font-weight:bold;text-align:left}.total-label,.total-value{background:#d9e1ec;font-weight:bold}.total-label{text-align:right}.total-value{text-align:center}@media print{thead{display:table-header-group}tfoot{display:table-footer-group}tr{break-inside:avoid}}
CSS;
    }
};
