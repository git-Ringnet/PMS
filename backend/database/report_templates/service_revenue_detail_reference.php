<?php

/**
 * Designer template for SERVICE_REVENUE_DETAIL, the legacy
 * "Báo cáo doanh thu dịch vụ chi tiết" (sp_134). Rows are grouped by service
 * with a subtotal per service, followed by the per-outlet summary built in
 * TotalRevenueDataAdapter::adaptDetail().
 */
return new class
{
    public function definition(): array
    {
        return [
            'code' => 'SERVICE_REVENUE_DETAIL',
            'name' => 'Báo cáo doanh thu dịch vụ chi tiết',
            'report' => 'SERVICE_REVENUE_DETAIL_REFERENCE',
            'page_size' => 'A4',
            'page_orientation' => 'landscape',
            'margin_top' => 8,
            'margin_right' => 8,
            'margin_bottom' => 8,
            'margin_left' => 8,
            'version' => '1.0',
            'content_json' => $this->blocks(),
            'content_html' => $this->html(),
            'css' => $this->css(),
            'columns' => $this->columns(),
        ];
    }

    public function columns(): array
    {
        return [
            ['field' => 'STT', 'header' => 'STT', 'width' => '3%', 'class' => 'center'],
            ['field' => 'BillId', 'header' => 'Mã', 'width' => '4%', 'class' => 'center'],
            ['field' => 'BillDate', 'header' => 'Ngày', 'width' => '6%', 'class' => 'center'],
            ['field' => 'BookingCode', 'header' => 'Mã ĐK', 'width' => '5%', 'class' => 'center'],
            ['field' => 'Room', 'header' => 'Phòng', 'width' => '4%', 'class' => 'center'],
            ['field' => 'GuestName', 'header' => 'Tên khách', 'width' => '11%', 'class' => 'left'],
            ['field' => 'ArrivalDate', 'header' => 'Ngày đến', 'width' => '6%', 'class' => 'center'],
            ['field' => 'DepartureDate', 'header' => 'Ngày đi', 'width' => '6%', 'class' => 'center'],
            ['field' => 'Description', 'header' => 'Mô tả', 'width' => '14%', 'class' => 'left'],
            ['field' => 'OriginalRate', 'header' => 'Giá gốc', 'width' => '6.5%', 'class' => 'num'],
            ['field' => 'ServiceChargeAmount', 'header' => 'Phí dịch vụ', 'width' => '5.5%', 'class' => 'num'],
            ['field' => 'TaxAmount', 'header' => 'Thuế', 'width' => '5.5%', 'class' => 'num'],
            ['field' => 'Amount', 'header' => 'Tổng', 'width' => '6.5%', 'class' => 'num'],
            ['field' => 'PaymentId', 'header' => 'Mã TT', 'width' => '4%', 'class' => 'center'],
            ['field' => 'PaymentMode', 'header' => 'HTTT', 'width' => '4%', 'class' => 'center'],
            ['field' => 'Username', 'header' => 'Người dùng', 'width' => '5%', 'class' => 'center'],
            ['field' => 'OutletName', 'header' => 'Outlet', 'width' => '4.5%', 'class' => 'center'],
        ];
    }

    public function blocks(): array
    {
        return [
            'header' => [
                ['id' => 'service_revenue_detail_header', 'type' => 'text', 'content' => $this->headerHtml()],
            ],
            'detail' => [
                ['id' => 'service_revenue_detail_table', 'type' => 'text', 'content' => $this->tableHtml()],
                ['id' => 'service_revenue_detail_outlets', 'type' => 'text', 'content' => $this->outletHtml()],
            ],
            'footer' => [],
        ];
    }

    public function html(): string
    {
        return $this->headerHtml().$this->tableHtml().$this->outletHtml();
    }

    private function headerHtml(): string
    {
        return '<div class="srd-header"><div class="hotel-logo">{{hotel.logo}}</div><div class="srd-meta"><div><b>Địa chỉ:</b> {{hotel.address}}</div><div><b>Người dùng:</b> {{report.generated_by}} &nbsp; <b>Ngày:</b> {{report.generated_at}}</div></div></div><hr class="srd-divider"><h1 class="srd-title">BÁO CÁO DOANH THU DỊCH VỤ CHI TIẾT</h1><p class="srd-period"><b>Từ ngày:</b> {{parameters.p_from_date}} &nbsp; <b>Đến ngày:</b> {{parameters.p_to_date}}</p>';
    }

    private function tableHtml(): string
    {
        $columns = $this->columns();
        $html = '<table class="srd-table"><colgroup>';
        foreach ($columns as $column) {
            $html .= '<col style="width:'.$column['width'].'">';
        }
        $html .= '</colgroup><thead><tr>';
        foreach ($columns as $column) {
            $html .= '<th>'.htmlspecialchars($column['header'], ENT_QUOTES, 'UTF-8').'</th>';
        }
        $html .= '</tr></thead><tbody class="pms-grouped-rows" data-source="rows" data-group-by="ServiceName">';
        $html .= '<tr class="pms-group-header"><td colspan="17" class="srd-group">Dịch vụ: {{row.ServiceName}}</td></tr>';
        $html .= '<tr class="pms-detail-row">';
        foreach ($columns as $column) {
            $format = $column['class'] === 'num' ? '|number' : '';
            $attributes = match ($column['field']) {
                'BookingCode' => ' data-pms-booking="{{row.BookingCode}}"',
                'GuestName', 'Description' => ' data-no-translate',
                default => '',
            };
            $html .= '<td class="'.$column['class'].'"'.$attributes.'>{{row.'.$column['field'].$format.'}}</td>';
        }
        $html .= '</tr><tr class="pms-group-footer"><td colspan="9" class="right subtotal">Tổng doanh thu theo dịch vụ</td>';
        foreach (['OriginalRate', 'ServiceChargeAmount', 'TaxAmount', 'Amount'] as $field) {
            $html .= '<td class="num subtotal">{{group.sum.'.$field.'|number}}</td>';
        }
        $html .= '<td colspan="4" class="subtotal"></td></tr></tbody><tfoot><tr class="srd-total"><td colspan="9" class="right">Tổng doanh thu</td>';
        foreach (['OriginalRate', 'ServiceChargeAmount', 'TaxAmount', 'Amount'] as $field) {
            $html .= '<td class="num">{{aggregate.rows.sum.'.$field.'|number}}</td>';
        }

        return $html.'<td colspan="4"></td></tr></tfoot></table>';
    }

    private function outletHtml(): string
    {
        return '<h2 class="srd-subtitle">Tổng doanh thu theo outlet</h2><table class="srd-outlets"><thead><tr><th>Outlet</th><th>Giá gốc</th><th>Phí dịch vụ</th><th>Thuế</th><th>Tổng</th></tr></thead><tbody><tr class="pms-detail-row" data-source="outlet_summary"><td>{{item.OutletName}}</td><td class="num">{{item.OriginalRate|number}}</td><td class="num">{{item.ServiceChargeAmount|number}}</td><td class="num">{{item.TaxAmount|number}}</td><td class="num">{{item.Amount|number}}</td></tr></tbody></table>';
    }

    public function css(): string
    {
        return <<<'CSS'
body { font-family: Arial, Helvetica, sans-serif; color: #111827; font-size: 9px; }
.srd-header { display: flex; justify-content: space-between; align-items: flex-end; min-height: 56px; }
.hotel-logo img { max-width: 130px; max-height: 56px; object-fit: contain; }
.srd-meta { text-align: right; line-height: 1.6; }
.srd-divider { border: 0; border-top: 1px solid #374151; margin: 4px 0 6px; }
.srd-title { text-align: center; font-size: 17px; font-weight: bold; margin: 6px 0 4px; }
.srd-period { text-align: center; margin: 0 0 8px; font-size: 10px; }
.srd-table, .srd-outlets { border-collapse: collapse; font-size: 8px; }
.srd-table { width: 100%; table-layout: fixed; }
.srd-table th, .srd-table td, .srd-outlets th, .srd-outlets td { border: 1px solid #9ca3af; padding: 3px 2px; vertical-align: middle; overflow-wrap: anywhere; }
.srd-table th, .srd-outlets th { background: #d9deea; text-align: center; font-weight: bold; }
.srd-table td.num, .srd-outlets td.num { text-align: right; }
.srd-table td.center { text-align: center; }
.srd-table td.right { text-align: right; font-weight: bold; }
.srd-group { background: #eef2f7; font-weight: bold; }
.srd-table td.subtotal { font-weight: bold; background: #f8fafc; }
.srd-total td { font-weight: bold; background: #f3f4f6; border-top: 2px solid #374151; }
.srd-subtitle { font-size: 11px; margin: 12px 0 4px; }
.srd-outlets { width: 50%; }
@media print { thead { display: table-header-group; } tr { break-inside: avoid; } }
CSS;
    }
};
