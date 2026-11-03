<?php

use App\Services\Reports\TotalRevenueDataAdapter;

/**
 * Designer template for TOTAL_REVENUE, laid out like the legacy
 * "Báo cáo tổng doanh thu" (sp_018 day rows, sp_021 MTD, sp_036 YTD,
 * sp_038 CTM, sp_044 pie chart). Data comes from TotalRevenueDataAdapter.
 *
 * Every amount cell carries data-pms-drill so a double-click opens
 * SERVICE_REVENUE_DETAIL filtered by the service codes of that cell.
 */
return new class
{
    public function definition(): array
    {
        return [
            'code' => 'TOTAL_REVENUE',
            'name' => 'Báo cáo tổng doanh thu',
            'report' => 'TOTAL_REVENUE_REFERENCE',
            'page_size' => 'A4',
            'page_orientation' => 'landscape',
            'margin_top' => 8,
            'margin_right' => 8,
            'margin_bottom' => 8,
            'margin_left' => 8,
            'version' => '2.0',
            'content_json' => $this->blocks(),
            'content_html' => $this->html(),
            'css' => $this->css(),
            'columns' => $this->columns(),
            'data_contract' => [
                'rows' => [
                    'period_code' => 'string',
                    'business_date' => 'string',
                    'service_id' => 'string',
                    'department_id' => 'string',
                    'is_room_bill' => 'integer',
                    'amount' => 'number',
                    'original_rate' => 'number',
                    'service_charge_amount' => 'number',
                    'tax_amount' => 'number',
                ],
                'parameters' => [
                    'p_from_date' => 'date',
                    'p_to_date' => 'date',
                    'p_view_by_time' => 'integer',
                    'p_from_time' => 'string',
                    'p_to_time' => 'string',
                    'p_department' => 'string',
                    'p_outlet' => 'string',
                    'p_user' => 'string',
                    'p_view_free' => 'integer',
                ],
            ],
        ];
    }

    /** Amount columns after "Ngày", in legacy order (FO 7, HK 3, FB 5, SPA, Showroom, Tổng). */
    public function columns(): array
    {
        $columns = [];
        foreach (TotalRevenueDataAdapter::COLUMNS as $field => $column) {
            $columns[] = ['field' => $field, 'header' => $column['label'], 'group' => $column['group']];
        }
        $columns[] = ['field' => 'Total', 'header' => 'Tổng', 'group' => null];

        return $columns;
    }

    public function blocks(): array
    {
        return [
            'header' => [
                ['id' => 'total_revenue_header', 'type' => 'text', 'content' => $this->headerHtml()],
                ['id' => 'total_revenue_title', 'type' => 'text', 'content' => $this->titleHtml()],
            ],
            'detail' => [
                ['id' => 'total_revenue_table', 'type' => 'text', 'content' => $this->tableHtml()],
                ['id' => 'total_revenue_summary', 'type' => 'text', 'content' => $this->summaryHtml()],
                ['id' => 'total_revenue_chart', 'type' => 'text', 'content' => $this->chartHtml()],
            ],
            'footer' => [],
        ];
    }

    public function html(): string
    {
        return $this->headerHtml().$this->titleHtml().$this->tableHtml().$this->summaryHtml().$this->chartHtml();
    }

    private function headerHtml(): string
    {
        return '<div class="tr-header"><div class="hotel-logo">{{hotel.logo}}</div><table class="tr-meta"><tr><td class="tr-meta-label">Địa chỉ:</td><td colspan="3">{{hotel.address}}</td></tr><tr><td class="tr-meta-label">Người dùng:</td><td>{{report.generated_by}}</td><td class="tr-meta-label">Ngày:</td><td>{{report.generated_at}}</td></tr></table></div><hr class="tr-divider">';
    }

    private function titleHtml(): string
    {
        return '<h1 class="tr-title">BÁO CÁO TỔNG DOANH THU</h1><p class="tr-period"><b>Ngày:</b> {{revenue.from_label}} &nbsp; ~ &nbsp; {{revenue.to_label}}</p><p class="tr-window">(Doanh thu ghi nhận từ {{revenue.window_from}} đến {{revenue.window_to}})</p>';
    }

    private function tableHtml(): string
    {
        $columns = $this->columns();
        $html = '<table class="tr-table"><colgroup><col style="width:6.5%">';
        foreach ($columns as $column) {
            $html .= '<col style="width:'.($column['field'] === 'Total' ? '6.5' : '5.15').'%">';
        }
        $html .= '</colgroup><thead><tr><th rowspan="2">Ngày</th>';
        $html .= '<th colspan="7">FO</th><th colspan="3">HK</th><th colspan="5">FB</th>';
        foreach ($columns as $column) {
            if ($column['group'] === null) {
                $html .= '<th rowspan="2">'.htmlspecialchars($column['header'], ENT_QUOTES, 'UTF-8').'</th>';
            }
        }
        $html .= '</tr><tr>';
        foreach ($columns as $column) {
            if ($column['group'] !== null) {
                $html .= '<th>'.htmlspecialchars($column['header'], ENT_QUOTES, 'UTF-8').'</th>';
            }
        }
        $html .= '</tr></thead><tbody>';

        $html .= $this->amountRow('pms-detail-row', 'revenue.days', 'item');
        $html .= $this->amountRow('tr-sum-row', null, 'revenue.sum');
        $html .= '<tr class="tr-group-row"><td class="center">Tổng</td><td colspan="7" class="center">{{revenue.sum.FO|number}}</td><td colspan="3" class="center">{{revenue.sum.HK|number}}</td><td colspan="5" class="center">{{revenue.sum.FB|number}}</td>';
        foreach ($columns as $column) {
            if ($column['group'] === null) {
                $html .= '<td class="num">{{revenue.sum.'.$column['field'].'|number}}</td>';
            }
        }
        $html .= '</tr>';
        $html .= $this->amountRow('pms-detail-row tr-period-row', 'revenue.periods', 'item');

        return $html.'</tbody></table>';
    }

    private function amountRow(string $class, ?string $source, string $prefix): string
    {
        $html = '<tr class="'.$class.'"'.($source ? ' data-source="'.$source.'"' : '').'><td class="center">{{'.$prefix.'.Label}}</td>';
        foreach ($this->columns() as $column) {
            $field = $column['field'];
            $html .= '<td class="num" data-pms-drill="{{'.$prefix.'.'.$field.'_drill}}">{{'.$prefix.'.'.$field.'|number}}</td>';
        }

        return $html.'</tr>';
    }

    private function summaryHtml(): string
    {
        return '<div class="tr-summary"><div class="tr-notes"><div>* MTD: Month to date / Đầu tháng đến ngày xem</div><div>* YTD: Year to date / Đầu năm đến ngày xem</div><div>* CTM: Balance to month / Công nợ trong tháng</div><div>* Nhấp đúp vào số tiền để xem báo cáo doanh thu dịch vụ chi tiết</div></div><table class="tr-fees"><tr><td class="tr-fee-label">Phí phục vụ</td><td class="num">{{revenue.sum.ServiceCharge|number}}</td></tr><tr><td class="tr-fee-label">Phí VAT</td><td class="num">{{revenue.sum.Tax|number}}</td></tr><tr><td class="tr-fee-label">Số tiền đã thanh toán</td><td class="num">{{revenue.sum.Total|number}}</td></tr></table></div>';
    }

    private function chartHtml(): string
    {
        return '<div class="tr-chart"><div class="tr-chart-image">{{revenue.chart_svg}}</div><table class="tr-legend"><tbody><tr class="pms-detail-row" data-source="revenue.chart_legend"><td><span class="tr-swatch" style="background:{{item.Color}}"></span>{{item.Label}}: {{item.Percent}}</td></tr></tbody></table></div>';
    }

    public function css(): string
    {
        return <<<'CSS'
body { font-family: Arial, Helvetica, sans-serif; color: #111827; font-size: 9px; }
.tr-header { display: flex; justify-content: space-between; align-items: flex-end; min-height: 60px; }
.hotel-logo img { max-width: 130px; max-height: 60px; object-fit: contain; }
.tr-meta { width: auto !important; margin-left: auto; border-collapse: collapse; font-size: 9px; }
.tr-meta td { border: 0 !important; }
.tr-meta td { padding: 1px 6px; }
.tr-meta-label { font-weight: bold; }
.tr-divider { border: 0; border-top: 1px solid #374151; margin: 4px 0 6px; }
.tr-title { text-align: center; font-size: 18px; font-weight: bold; margin: 6px 0 4px; }
.tr-period, .tr-window { text-align: center; margin: 0 0 3px; font-size: 10px; }
.tr-window { font-weight: bold; margin-bottom: 8px; }
.tr-table { width: 100%; table-layout: fixed; border-collapse: collapse; font-size: 8px; }
.tr-table th, .tr-table td { border: 1px solid #9ca3af; padding: 3px 2px; vertical-align: middle; overflow-wrap: anywhere; }
.tr-table th { background: #d9deea; text-align: center; font-weight: bold; }
.tr-table td.num { text-align: right; }
.tr-table td.center { text-align: center; }
.tr-table tbody tr:nth-last-child(-n+5) td { font-weight: bold; height: 22px; }
.tr-table td[data-pms-drill]:not([data-pms-drill=""]) { cursor: pointer; }
.tr-table td[data-pms-drill]:not([data-pms-drill=""]):hover { background: #e0f2fe; }
.tr-summary { display: flex; justify-content: space-between; align-items: flex-start; margin-top: 10px; gap: 16px; }
.tr-notes { line-height: 1.5; }
.tr-fees { border-collapse: collapse; width: 22%; font-size: 9px; }
.tr-fees td { border: 1px solid #9ca3af; padding: 3px 4px; }
.tr-fee-label { background: #d9deea; font-weight: bold; }
.tr-fees td.num { text-align: right; font-weight: bold; }
.tr-chart { display: flex; justify-content: center; align-items: center; gap: 28px; margin-top: 14px; break-inside: avoid; }
.tr-legend { width: auto !important; border: 1px solid #d1d5db; border-collapse: collapse; font-size: 8px; }
.tr-legend td { border: 0 !important; }
.tr-legend tbody { display: grid; grid-template-columns: repeat(2, auto); column-gap: 18px; padding: 6px 8px; }
.tr-legend td { padding: 1px 0; white-space: nowrap; }
.tr-swatch { display: inline-block; width: 9px; height: 9px; margin-right: 5px; vertical-align: middle; }
@media print { thead { display: table-header-group; } tr { break-inside: avoid; } .tr-table td:hover { background: transparent !important; } }
CSS;
    }
};
