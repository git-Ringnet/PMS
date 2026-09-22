<?php

return new class
{
    public function definition(): array
    {
        return ['report' => 'DEPOSITS_SUMMARY_REFERENCE', 'name' => 'Báo cáo tiền đặt cọc', 'page_size' => 'A4', 'page_orientation' => 'landscape', 'margin_top' => 8, 'margin_right' => 5, 'margin_bottom' => 8, 'margin_left' => 5, 'version' => '1.0', 'content_json' => $this->blocks(), 'content_html' => $this->html(), 'css' => $this->css()];
    }

    private function fields(): array { return ['MaDatCoc','MTT','PaymentDate','TimePayment','BookingRoomCode','BookingName','BusinessName','ArrivalDate','DepartureDate','Amount','PaymentMethodName','Description','Username']; }
    private function labels(): array { return ['Mã Đặt Cọc','Mã TT','Ngày Đặt Cọc','Giờ','Mã ĐK/Phòng','Tên Đăng Ký','Công Ty','Ngày Đến','Ngày Đi','Tổng','HTTT','Ghi Chú','Người Dùng']; }
    private function blocks(): array { return ['header' => [['id' => 'header', 'type' => 'text', 'content' => 'BÁO CÁO TIỀN ĐẶT CỌC']], 'detail' => [['id' => 'table', 'type' => 'table', 'dataSource' => 'rows', 'tableType' => 'dynamic', 'columns' => array_map(fn ($f, $l) => ['field' => $f, 'header' => $l, 'value' => 'row.'.$f], $this->fields(), $this->labels())]], 'footer' => []]; }
    public function html(): string
    {
        $headers = ''; foreach ($this->labels() as $label) { $headers .= '<th>'.$label.'</th>'; }
        $cells = ''; foreach ($this->fields() as $field) { $format = $field === 'Amount' ? '|number' : ''; $cells .= '<td>{{row.'.$field.$format.'}}</td>'; }
        return '<div class="report-header"><div class="hotel-logo">{{hotel.logo}}</div><div class="hotel-information">Địa chỉ: {{hotel.address}}<br>Nhân viên: {{report.generated_by}}<br>Ngày in: {{report.generated_at}}</div></div><h1>BÁO CÁO TIỀN ĐẶT CỌC</h1><p class="period">Ngày: {{parameters.p_from_date}} ~ {{parameters.p_to_date}}</p><table><thead><tr>'.$headers.'</tr></thead><tbody><tr class="pms-detail-row" data-source="rows">'.$cells.'</tr><tr class="total"><td colspan="9">Tổng cộng:</td><td>{{aggregate.rows.sum.Amount|number}}</td><td colspan="3"></td></tr></tbody></table>';
    }
    public function css(): string { return '.report-header{display:flex;justify-content:space-between;min-height:55px}.hotel-logo{width:30%}.hotel-information{width:70%;text-align:right;font-size:9px;line-height:1.5}h1{text-align:center;font-size:17px;margin:10px 0 3px}p.period{text-align:center;font-style:italic;font-size:10px}table{width:100%;border-collapse:collapse;font-size:8px}th,td{border:1px solid #aeb5c0;padding:3px 2px}th{background:#d9deea;text-align:center}.total td{background:#d9deea;font-weight:bold}'; }
};
