<?php

use Illuminate\Support\Facades\DB;

return new class
{
    public function apply(int $sourceId): void
    {
        $now = now();
        DB::table('templates')->updateOrInsert(['report' => 'NO_SHOW_BY_DAY_STANDARD'], [
            'group' => 'Báo cáo phòng', 'name' => 'Báo cáo phòng No Show theo ngày', 'report_data_source_id' => $sourceId,
            'parameter_defaults' => json_encode(['p_from_date' => now()->toDateString(), 'p_to_date' => now()->toDateString(), 'p_type' => 2, 'p_user' => '', 'p_type_money' => '', 'p_sort_type' => 'ASC', 'p_booking' => '', 'p_division' => '__current__']),
            'page_size' => 'A4', 'page_orientation' => 'landscape', 'margin_top' => 8, 'margin_bottom' => 8, 'margin_left' => 5, 'margin_right' => 5,
            'content_json' => json_encode(['header' => $this->header(), 'detail' => $this->detail(), 'footer' => []], JSON_UNESCAPED_UNICODE),
            'content_html' => $this->html(), 'css' => $this->css(), 'is_default' => false, 'version' => '1.0', 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    private function header(): array
    {
        return [
            ['id' => 'no_show_day_hotel', 'type' => 'text', 'content' => '<div class="hotel-header"><div class="hotel-logo">{{hotel.logo}}</div><div class="hotel-information"><div><b>Địa chỉ:</b> {{hotel.address}}</div><div><b>Nhân viên:</b> {{report.generated_by}} <b>Ngày:</b> {{report.generated_at}}</div></div></div>'],
            ['id' => 'no_show_day_divider', 'type' => 'divider', 'content' => '<hr>'],
            ['id' => 'no_show_day_title', 'type' => 'text', 'content' => '<h1>BÁO CÁO PHÒNG NO SHOW THEO NGÀY</h1>', 'style' => ['textAlign' => 'center']],
            ['id' => 'no_show_day_period', 'type' => 'text', 'content' => '<p class="period"><b>Ngày:</b> {{parameters.p_from_date}} ~ {{parameters.p_to_date}}</p>', 'style' => ['textAlign' => 'center']],
        ];
    }

    private function detail(): array
    {
        return [[
            'id' => 'no_show_day_table', 'type' => 'table', 'dataSource' => 'rows', 'tableType' => 'dynamic', 'tableStyle' => 'grid',
            'groups' => [
                ['id' => 'no_show_day_type_group', 'field' => 'RoomType', 'label' => '{{row.RoomType}}', 'className' => 'charge-group', 'sort' => 'ASC'],
                ['id' => 'no_show_day_date_group', 'field' => 'DateSortKey', 'label' => 'Ngày: {{row.LateCheckInDate}}', 'className' => 'date-group', 'sort' => 'ASC'],
            ],
            'groupBy' => 'RoomType', 'columns' => $this->columns(), 'customRows' => [
                [
                    'id' => 'no_show_day_date_total', 'scope' => 'group', 'level' => 1, 'enabledBy' => '', 'className' => 'date-total-row',
                    'cells' => [
                        ['id' => 'no_show_day_date_total_label', 'type' => 'text', 'content' => 'Tổng:', 'colspan' => 1, 'align' => 'left', 'format' => '', 'className' => 'total-label'],
                        ['id' => 'no_show_day_date_total_value', 'type' => 'text', 'content' => '{{group.count}}', 'colspan' => 1, 'align' => 'center', 'format' => '', 'className' => 'total-value'],
                        ['id' => 'no_show_day_date_total_empty', 'type' => 'text', 'content' => '', 'colspan' => 12, 'align' => 'left', 'format' => '', 'className' => 'total-empty'],
                    ],
                ],
                [
                    'id' => 'no_show_day_grand_total', 'scope' => 'table', 'level' => 0, 'enabledBy' => '', 'className' => 'grand-total-row',
                    'cells' => [
                        ['id' => 'no_show_day_grand_total_label', 'type' => 'text', 'content' => 'Tổng:', 'colspan' => 1, 'align' => 'left', 'format' => '', 'className' => 'total-label'],
                        ['id' => 'no_show_day_grand_total_value', 'type' => 'count', 'content' => '', 'binding' => '', 'aggregateField' => '', 'colspan' => 1, 'align' => 'center', 'format' => '', 'className' => 'total-value'],
                        ['id' => 'no_show_day_grand_total_empty', 'type' => 'text', 'content' => '', 'colspan' => 12, 'align' => 'left', 'format' => '', 'className' => 'total-empty'],
                    ],
                ],
            ],
        ]];
    }

    private function columns(): array
    {
        return [
            ['header' => 'Phòng', 'value' => 'row.Room', 'width' => '6%', 'align' => 'center'],
            ['header' => 'Mã ĐK', 'value' => 'row.BookingId', 'width' => '8%'],
            ['header' => 'Tên ĐK', 'value' => 'row.BookingName', 'width' => '15%'],
            ['header' => 'Công Ty', 'value' => 'row.Company', 'width' => '12%'],
            ['header' => 'Ngày Tạo', 'value' => 'row.BookingDate', 'width' => '8%', 'align' => 'center'],
            ['header' => 'Ngày Đến', 'value' => 'row.ArrivalDate', 'width' => '8%', 'align' => 'center'],
            ['header' => 'Ngày Đi', 'value' => 'row.CheckoutDate', 'width' => '8%', 'align' => 'center'],
            ['header' => 'Đêm', 'value' => 'row.NumOfDays', 'width' => '5%', 'align' => 'center'],
            ['header' => 'Ngày Vắng', 'value' => 'row.LateCheckInDate', 'width' => '8%', 'align' => 'center'],
            ['header' => 'Giờ', 'value' => 'row.LateCheckInTime', 'width' => '5%', 'align' => 'center'],
            ['header' => 'Giá', 'value' => 'row.Total', 'width' => '9%', 'format' => 'number', 'align' => 'right'],
            ['header' => 'Người Dùng', 'value' => 'row.Username', 'width' => '8%'],
            ['header' => 'Ca', 'value' => 'row.Ca', 'width' => '4%', 'align' => 'center'],
            ['header' => 'Lý Do', 'value' => 'row.Reason', 'width' => '12%'],
        ];
    }

    private function html(): string
    {
        return '<div class="report-header"><div class="hotel-header"><div class="hotel-logo">{{hotel.logo}}</div><div class="hotel-information"><div><b>Địa chỉ:</b> {{hotel.address}}</div><div><b>Nhân viên:</b> {{report.generated_by}} <b>Ngày:</b> {{report.generated_at}}</div></div></div><hr><h1>BÁO CÁO PHÒNG NO SHOW THEO NGÀY</h1><p class="period"><b>Ngày:</b> {{parameters.p_from_date}} ~ {{parameters.p_to_date}}</p></div><table class="no-show-day-table"><thead><tr><th>Phòng</th><th>Mã ĐK</th><th>Tên ĐK</th><th>Công Ty</th><th>Ngày Tạo</th><th>Ngày Đến</th><th>Ngày Đi</th><th>Đêm</th><th>Ngày Vắng</th><th>Giờ</th><th>Giá</th><th>Người Dùng</th><th>Ca</th><th>Lý Do</th></tr></thead><tbody class="pms-grouped-rows" data-source="rows" data-group-configured="1" data-group-by="RoomType"><tr class="pms-group-header" data-group-level="0" data-group-field="RoomType" data-group-sort="ASC"><td colspan="14" class="charge-group">{{row.RoomType}}</td></tr><tr class="pms-group-header" data-group-level="1" data-group-field="DateSortKey" data-group-sort="ASC"><td colspan="14" class="date-group">Ngày: {{row.LateCheckInDate}}</td></tr><tr class="pms-detail-row"><td>{{row.Room}}</td><td>{{row.BookingId}}</td><td>{{row.BookingName}}</td><td>{{row.Company}}</td><td>{{row.BookingDate}}</td><td>{{row.ArrivalDate}}</td><td>{{row.CheckoutDate}}</td><td>{{row.NumOfDays}}</td><td>{{row.LateCheckInDate}}</td><td>{{row.LateCheckInTime}}</td><td>{{row.Total|number}}</td><td>{{row.Username}}</td><td>{{row.Ca}}</td><td>{{row.Reason}}</td></tr><tr class="pms-group-custom-row date-total-row" data-group-level="1"><td colspan="1" class="total-label">Tổng:</td><td colspan="1" class="total-value">{{group.count}}</td><td colspan="12" class="total-empty"></td></tr></tbody><tfoot><tr class="pms-custom-row grand-total-row"><td colspan="1" class="total-label">Tổng:</td><td colspan="1" class="total-value">{{aggregate.rows.count}}</td><td colspan="12" class="total-empty"></td></tr></tfoot></table>';
    }

    private function css(): string
    {
        return 'body{color:#111;font-family:Arial,Helvetica,sans-serif;font-size:9px}.hotel-header{display:grid;grid-template-columns:180px 1fr;align-items:center;min-height:60px}.hotel-logo{min-height:50px}.hotel-logo img{max-width:120px;max-height:50px;object-fit:contain}.hotel-information{line-height:1.8}hr{margin:0 0 8px;border:0;border-top:1px solid #111}h1{margin:0;text-align:center;font-size:18px}.period{text-align:center;margin:8px 0 12px}.no-show-day-table{width:100%;border-collapse:collapse;table-layout:fixed}.no-show-day-table th,.no-show-day-table td{border:1px solid #b9c3d0;padding:4px 3px;line-height:1.15;overflow-wrap:anywhere}.no-show-day-table th{background:#d9e1ec;text-align:center}.charge-group{color:#b91c1c;font-weight:bold;text-align:left}.date-group{font-weight:bold;text-align:left;background:#fff}.date-total-row td,.grand-total-row td{font-weight:bold}.grand-total-row td{background:#d9e1ec}@media print{thead{display:table-header-group}tfoot{display:table-footer-group}tr{break-inside:avoid}}';
    }
};
