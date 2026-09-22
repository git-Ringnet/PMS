<?php

return new class
{
    public function definition(): array
    {
        return [
            'report' => 'SUMMARY_SERVICE_INVOICES_REFERENCE',
            'name' => 'Báo cáo hóa đơn dịch vụ tổng hợp',
            'page_size' => 'A4', 'page_orientation' => 'landscape',
            'margin_top' => 8, 'margin_right' => 5, 'margin_bottom' => 8, 'margin_left' => 5,
            'version' => '1.0', 'content_json' => $this->blocks(), 'content_html' => $this->html(), 'css' => $this->css(),
        ];
    }

    private function blocks(): array
    {
        return ['header' => [['id' => 'header', 'type' => 'text', 'content' => 'BÁO CÁO HÓA ĐƠN DỊCH VỤ TỔNG HỢP']], 'detail' => [['id' => 'table', 'type' => 'table', 'dataSource' => 'rows', 'tableType' => 'dynamic', 'columns' => array_map(fn ($f, $l) => ['field' => $f, 'header' => $l, 'value' => 'row.'.$f], $this->fields(), $this->labels())]], 'footer' => []];
    }

    private function fields(): array
    {
        return ['BookingCode','RoomNumber','ArrivalDate','DepartureDate','GuestName','Description','Amount','PaymentMethod','CompanyName','OpenTime','Note'];
    }

    private function labels(): array
    {
        return ['Mã ĐK','Phòng','Ngày Đến','Ngày Đi','Tên Khách','Mô Tả','Doanh Thu','HTTT','Công Ty','Giờ','Ghi chú'];
    }

    public function html(): string
    {
        $headers = '';
        foreach ($this->labels() as $label) { $headers .= '<th>'.$label.'</th>'; }
        $cells = '';
        foreach ($this->fields() as $field) { $format = $field === 'Amount' ? '|number' : ''; $cells .= '<td>{{row.'.$field.$format.'}}</td>'; }
        return '<div class="report-header"><div class="hotel-logo">{{hotel.logo}}</div><div class="hotel-information">Địa chỉ: {{hotel.address}}<br>Người dùng: {{report.generated_by}}<br>Ngày: {{report.generated_at}}</div></div>'
            .'<h1>BÁO CÁO HÓA ĐƠN DỊCH VỤ TỔNG HỢP</h1><p class="period">Ngày: {{parameters.p_from_date}} ~ {{parameters.p_to_date}}</p>'
            .'<table><thead><tr>'.$headers.'</tr></thead><tbody><tr class="pms-detail-row" data-source="rows">'.$cells.'</tr><tr class="total"><td colspan="6">Tổng:</td><td>{{aggregate.rows.sum.Amount|number}}</td><td colspan="4"></td></tr></tbody></table>'
            .'<h3>Tổng hợp doanh thu</h3><table class="summary"><thead><tr><th>Doanh Thu</th><th>Tổng</th></tr></thead><tbody><tr class="pms-detail-row" data-source="service_summary"><td>{{item.Label}}</td><td>{{item.Amount|number}}</td></tr></tbody></table>';
    }

    public function css(): string
    {
        return '.report-header{display:flex;justify-content:space-between;min-height:55px}.hotel-logo{width:30%}.hotel-information{width:70%;text-align:right;font-size:9px;line-height:1.5}h1{text-align:center;font-size:17px;margin:10px 0 3px}h3{text-align:center;margin:14px 0 5px}p.period{text-align:center;font-style:italic;font-size:10px}table{width:100%;border-collapse:collapse;font-size:8px}th,td{border:1px solid #aeb5c0;padding:3px 2px}th{background:#d9deea;text-align:center}td:nth-child(7){text-align:right}.total td,.summary th,.summary td{background:#d9deea;font-weight:bold}.summary{width:38%;margin:0 auto;font-size:9px}';
    }
};
