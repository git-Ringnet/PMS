<?php

return new class
{
    public function definition(): array
    {
        return ['report' => 'DAILY_SUMMARY_REFERENCE', 'name' => 'Báo cáo tổng hợp ngày', 'page_size' => 'A4', 'page_orientation' => 'portrait', 'margin_top' => 10, 'margin_right' => 10, 'margin_bottom' => 10, 'margin_left' => 10, 'version' => '1.0', 'content_json' => $this->blocks(), 'content_html' => $this->html(), 'css' => $this->css()];
    }
    private function blocks(): array { return ['header' => [['id' => 'header', 'type' => 'text', 'content' => 'BÁO CÁO TỔNG HỢP NGÀY']], 'detail' => [['id' => 'table', 'type' => 'table', 'dataSource' => 'rows', 'tableType' => 'dynamic']], 'footer' => []]; }
    public function html(): string { return '<div class="report-header"><div>{{hotel.name}}</div><div>Ngày in: {{report.generated_at}}</div></div><h1>BÁO CÁO TỔNG HỢP NGÀY</h1><p class="period">Ngày: {{parameters.p_date}}</p><table><thead><tr><th>STT</th><th>NỘI DUNG</th><th>NGÀY</th><th>LŨY KẾ THÁNG {{parameters.p_month_label}}</th><th>DỰ KIẾN THÁNG</th><th>TỈ LỆ HOÀN THÀNH</th></tr></thead><tbody><tr class="pms-detail-row" data-source="rows"><td>{{row.SortOrder}}</td><td>{{row.Content}}</td><td>{{row.DateAmount|number}}</td><td>{{row.MonthAmount|number}}</td><td>{{row.PlanAmount|number}}</td><td>{{row.Rate}}</td></tr></tbody></table><div class="signatures"><span>Người lập biểu</span><span>Giám đốc khách sạn</span></div>'; }
    public function css(): string { return '.report-header{display:flex;justify-content:space-between;font-size:10px;border-bottom:1px solid #111;padding-bottom:5px}h1{text-align:center;font-size:17px;margin:15px 0 3px}p.period{text-align:center;font-style:italic;font-size:10px}table{width:100%;border-collapse:collapse;font-size:9px}th,td{border:1px solid #aeb5c0;padding:5px}th{background:#d9deea;text-align:center}td:nth-child(n+3){text-align:right}.signatures{display:flex;justify-content:space-around;margin-top:38px;font-size:10px}'; }
};
