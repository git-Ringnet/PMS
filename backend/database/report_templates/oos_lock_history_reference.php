<?php

use Illuminate\Support\Facades\DB;

return new class
{
    public function apply(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        DB::table('templates')->where('report', 'OOS_LOCK_HISTORY_STANDARD')->update([
            'content_json' => json_encode(['detail' => [[
                'id' => 'oos_lock_history_table',
                'type' => 'table',
                'dataSource' => 'rows',
                'tableType' => 'dynamic',
                'tableStyle' => 'grid',
                'groupBy' => 'GroupName',
                'columns' => $this->columns(),
            ]]], JSON_UNESCAPED_UNICODE),
            'content_html' => $this->html(),
            'css' => $this->css(),
            'version' => '1.0',
            'updated_at' => now(),
        ]);
    }

    private function columns(): array
    {
        return [
            ['header' => 'Phòng', 'value' => 'row.Room'],
            ['header' => 'Ngày Bắt Đầu', 'value' => 'row.DateBeginTime'],
            ['header' => 'Ngày Kết Thúc', 'value' => 'row.EndDateTime'],
            ['header' => 'Người Mở Khóa', 'value' => 'row.UserUnlock'],
            ['header' => 'Ngày Khóa', 'value' => 'row.LockDateTime'],
            ['header' => 'Người Khóa', 'value' => 'row.Username'],
            ['header' => 'Mô Tả', 'value' => 'row.Note'],
        ];
    }

    private function html(): string
    {
        return <<<'HTML'
<div class="report-header-band"><div class="hotel-header"><div class="hotel-logo">{{hotel.logo}}</div><div class="hotel-information"><div><b>Địa chỉ:</b> {{hotel.address}}</div><div><b>Nhân viên:</b> {{report.generated_by}} <b>Ngày:</b> {{report.generated_at}}</div></div></div><hr><h1>BÁO CÁO LỊCH SỬ KHÓA PHÒNG OOS</h1><p class="period">Ngày: {{parameters.p_from_date}} &nbsp; ~ &nbsp; {{parameters.p_to_date}}</p></div>
<table class="oos-lock-table"><thead><tr><th>Phòng</th><th>Ngày Bắt Đầu</th><th>Ngày Kết Thúc</th><th>Người Mở Khóa</th><th>Ngày Khóa</th><th>Người Khóa</th><th>Mô Tả</th></tr></thead><tbody class="pms-grouped-rows" data-source="rows" data-group-by="GroupName"><tr class="pms-group-header"><td colspan="7">{{row.GroupName}}</td></tr><tr class="pms-detail-row"><td>{{row.Room}}</td><td>{{row.DateBeginTime}}</td><td>{{row.EndDateTime}}</td><td>{{row.UserUnlock}}</td><td>{{row.LockDateTime}}</td><td>{{row.Username}}</td><td>{{row.Note}}</td></tr></tbody></table>
HTML;
    }

    private function css(): string
    {
        return <<<'CSS'
body{font-family:Arial,sans-serif;font-size:9px;color:#111}.hotel-header{display:grid;grid-template-columns:175px 1fr;min-height:65px;align-items:center}.hotel-logo{min-height:55px}.hotel-information{font-size:9px;line-height:1.8}hr{border:0;border-top:1.5px solid #000}h1{text-align:center;font-size:20px;margin:5px 0}.period{text-align:center;font-weight:bold}.oos-lock-table{width:100%;border-collapse:collapse;table-layout:fixed}.oos-lock-table th,.oos-lock-table td{border:1px solid #ccc;padding:5px;vertical-align:middle;overflow-wrap:anywhere}.oos-lock-table th{background:#d9e1ec;text-align:center}.oos-lock-table td:nth-child(1),.oos-lock-table td:nth-child(4),.oos-lock-table td:nth-child(5),.oos-lock-table td:nth-child(6){text-align:center}.pms-group-header{font-weight:bold;color:#851c1c}@media print{thead{display:table-header-group}tr{break-inside:avoid}}
CSS;
    }
};
