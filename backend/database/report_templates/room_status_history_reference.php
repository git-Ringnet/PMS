<?php

use Illuminate\Support\Facades\DB;

return new class
{
    public function apply(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::table('templates')->where('report', 'ROOM_STATUS_HISTORY_STANDARD')->update([
            'content_json' => json_encode(['header' => $this->header(), 'detail' => $this->detail(), 'footer' => $this->footer()], JSON_UNESCAPED_UNICODE),
            'content_html' => $this->html(),
            'css' => $this->css(),
            'page_size' => 'A4',
            'page_orientation' => 'portrait',
            'margin_top' => 6,
            'margin_bottom' => 6,
            'margin_left' => 5,
            'margin_right' => 5,
            'version' => '1.0',
            'updated_at' => now(),
        ]);
    }

    private function header(): array
    {
        return [
            ['id' => 'room_status_history_hotel_header', 'type' => 'text', 'content' => '<div class="hotel-header"><div class="hotel-logo">{{hotel.logo}}</div><div class="hotel-information"><div><b>Địa chỉ:</b> {{hotel.address}}</div><div><b>Nhân viên:</b> {{report.generated_by}} <b>Ngày:</b> {{report.generated_at}}</div></div></div>', 'style' => ['marginBottom' => '4px']],
            ['id' => 'room_status_history_divider', 'type' => 'divider', 'content' => '<hr>', 'style' => ['marginBottom' => '10px']],
            ['id' => 'room_status_history_title', 'type' => 'text', 'content' => '<h1>BÁO CÁO THAY ĐỔI TÌNH TRẠNG PHÒNG</h1>', 'style' => ['textAlign' => 'center', 'marginBottom' => '8px']],
            ['id' => 'room_status_history_period', 'type' => 'text', 'content' => '<p class="period"><b>Ngày:</b> {{parameters.p_from_date}} ~ {{parameters.p_to_date}}</p>', 'style' => ['textAlign' => 'center', 'marginBottom' => '10px']],
        ];
    }

    private function detail(): array
    {
        return [['id' => 'room_status_history_table', 'type' => 'table', 'dataSource' => 'rows', 'tableType' => 'dynamic', 'tableStyle' => 'grid', 'columns' => $this->columns()]];
    }

    private function footer(): array
    {
        return [];
    }

    private function columns(): array
    {
        return [
            ['header' => 'Phòng', 'value' => 'row.Room', 'width' => '11%', 'align' => 'center'],
            ['header' => 'Thời gian thao tác', 'value' => 'row.Date', 'width' => '24%', 'align' => 'center'],
            ['header' => 'Người dùng', 'value' => 'row.Username', 'width' => '13%', 'align' => 'center'],
            ['header' => 'Từ trạng thái', 'value' => 'row.StatusFromVietnamese', 'width' => '26%', 'align' => 'center'],
            ['header' => 'Sang trạng thái', 'value' => 'row.StatusToVietnamese', 'width' => '26%', 'align' => 'center'],
        ];
    }

    private function html(): string
    {
        return <<<'HTML'
<div class="report-header"><div class="hotel-header"><div class="hotel-logo">{{hotel.logo}}</div><div class="hotel-information"><div><b>Địa chỉ:</b> {{hotel.address}}</div><div><b>Nhân viên:</b> {{report.generated_by}} <b>Ngày:</b> {{report.generated_at}}</div></div></div><hr><h1>BÁO CÁO THAY ĐỔI TÌNH TRẠNG PHÒNG</h1><p class="period"><b>Ngày:</b> {{parameters.p_from_date}} ~ {{parameters.p_to_date}}</p></div>
<table class="room-status-history-table"><colgroup><col style="width:11%"><col style="width:24%"><col style="width:13%"><col style="width:26%"><col style="width:26%"></colgroup><thead><tr><th>Phòng</th><th>Thời gian thao tác</th><th>Người dùng</th><th>Từ trạng thái</th><th>Sang trạng thái</th></tr></thead><tbody><tr class="pms-detail-row" data-source="rows"><td>{{row.Room}}</td><td>{{row.Date}}</td><td>{{row.Username}}</td><td>{{row.StatusFromVietnamese}}</td><td>{{row.StatusToVietnamese}}</td></tr></tbody></table>
HTML;
    }

    private function css(): string
    {
        return <<<'CSS'
body{color:#111;font-family:Arial,Helvetica,sans-serif;font-size:9px}.hotel-header{display:grid;grid-template-columns:175px 1fr;align-items:center;min-height:65px}.hotel-logo{display:flex;align-items:center;min-height:55px}.hotel-logo img{max-width:120px;max-height:55px;object-fit:contain}.hotel-information{line-height:1.8}hr{margin:0;border:0;border-top:1px solid #111}h1{margin:48px 0 0;text-align:center;font-size:20px}.period{margin:32px 0 38px;text-align:center;font-weight:bold}.room-status-history-table{width:100%;border-collapse:collapse;table-layout:fixed}.room-status-history-table th,.room-status-history-table td{border:1px solid #bfc4cc;padding:5px 4px;line-height:1.15;text-align:center;overflow-wrap:anywhere}.room-status-history-table th{background:#d9e1ec;font-weight:bold}.room-status-history-table td{font-weight:600}@media print{thead{display:table-header-group}tr{break-inside:avoid}}
CSS;
    }
};
