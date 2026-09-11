<?php

use App\Services\TemplateRendererService;

/** Reference layout for legacy sp_206 (freeitem=0), product summary. */
return new class
{
    public function definition(): array
    {
        return [
            'report' => 'LAUNDRY_INVOICES_BY_PRODUCT_REFERENCE',
            'name' => 'Báo cáo hóa đơn giặt ủi theo sản phẩm - Mẫu tham chiếu',
            'page_size' => 'A4', 'page_orientation' => 'portrait',
            'margin_top' => 6, 'margin_bottom' => 6, 'margin_left' => 5, 'margin_right' => 5,
            'version' => '1.0', 'content_html' => $this->html(),
            'content_json' => $this->blocks(), 'css' => $this->css(),
        ];
    }

    public function html(): string
    {
        return <<<'HTML'
<div class="report-header"><div class="hotel-header"><div class="hotel-logo">{{hotel.logo}}</div><div class="hotel-information"><div><b>Địa chỉ:</b> {{hotel.address}}</div><div><b>Người dùng:</b> {{report.generated_by}} <b>Ngày:</b> {{report.generated_at}}</div></div></div><hr><h1>BÁO CÁO HÓA ĐƠN GIẶT ỦI (THEO SẢN PHẨM)</h1><p class="period"><b>Ngày:</b> {{parameters.p_from_date}} &nbsp; ~ &nbsp; {{parameters.p_to_date}}</p></div>
<table class="product-report"><colgroup><col style="width:7%"><col style="width:34%"><col style="width:12%"><col style="width:12%"><col style="width:11%"><col style="width:12%"><col style="width:12%"></colgroup><thead><tr><th>ID</th><th>Sản phẩm</th><th>Đơn vị</th><th>Đơn giá</th><th>Số lượng</th><th>Thành tiền</th><th>Giảm giá</th></tr></thead><tbody class="pms-grouped-rows" data-source="rows" data-group-configured="1" data-group-by="DateGroup"><tr class="pms-group-header" data-group-level="0" data-group-field="DateGroup" data-group-enabled-by="parameters.p_group_by_date"><td colspan="7" class="date-group">Ngày: {{row.DateGroup}}</td></tr><tr class="pms-detail-row"><td>{{row.MaProduct}}</td><td>{{row.Product}}</td><td>{{row.Currency}}</td><td>{{row.Rate|number}}</td><td>{{row.Quantity|number}}</td><td>{{row.Total|number}}</td><td>{{row.DiscountAmount|number}}</td></tr></tbody><tfoot><tr><td colspan="5">Tổng tiền</td><td>{{totals.Total|number}}</td><td>{{totals.DiscountAmount|number}}</td></tr></tfoot></table>
HTML;
    }

    public function blocks(): array
    {
        return [
            'header' => [
                ['id' => 'laundry_product_hotel_header', 'type' => 'text', 'content' => '<div class="hotel-header"><div class="hotel-logo">{{hotel.logo}}</div><div class="hotel-information"><div><b>Địa chỉ:</b> {{hotel.address}}</div><div><b>Người dùng:</b> {{report.generated_by}} <b>Ngày:</b> {{report.generated_at}}</div></div></div>'],
                ['id' => 'laundry_product_divider', 'type' => 'divider', 'content' => '<hr>'],
                ['id' => 'laundry_product_title', 'type' => 'text', 'content' => '<h1>BÁO CÁO HÓA ĐƠN GIẶT ỦI (THEO SẢN PHẨM)</h1>', 'style' => ['textAlign' => 'center']],
                ['id' => 'laundry_product_period', 'type' => 'text', 'content' => '<p class="period"><b>Ngày:</b> {{parameters.p_from_date}} &nbsp; ~ &nbsp; {{parameters.p_to_date}}</p>', 'style' => ['textAlign' => 'center']],
            ],
            'detail' => [[
                'id' => 'laundry_product_table', 'type' => 'table', 'dataSource' => 'rows', 'tableType' => 'dynamic', 'tableStyle' => 'grid',
                'grouping' => [
                    ['id' => 'laundry_product_date_group', 'field' => 'DateGroup', 'label' => 'Ngày: {{row.DateGroup}}', 'className' => 'date-group', 'sort' => 'ASC', 'enabledBy' => 'parameters.p_group_by_date'],
                ],
                'columns' => [
                    ['header' => 'ID', 'value' => 'row.MaProduct', 'width' => '7%', 'align' => 'center'],
                    ['header' => 'Sản phẩm', 'value' => 'row.Product', 'width' => '34%', 'align' => 'left'],
                    ['header' => 'Đơn vị', 'value' => 'row.Currency', 'width' => '12%', 'align' => 'center'],
                    ['header' => 'Đơn giá', 'value' => 'row.Rate', 'width' => '12%', 'align' => 'right', 'format' => 'number'],
                    ['header' => 'Số lượng', 'value' => 'row.Quantity', 'width' => '11%', 'align' => 'right', 'format' => 'number'],
                    ['header' => 'Thành tiền', 'value' => 'row.Total', 'width' => '12%', 'align' => 'right', 'format' => 'number'],
                    ['header' => 'Giảm giá', 'value' => 'row.DiscountAmount', 'width' => '12%', 'align' => 'right', 'format' => 'number'],
                ],
                'customRows' => [[
                    'id' => 'laundry_product_totals', 'scope' => 'table', 'className' => 'product-total-row',
                    'cells' => [
                        ['type' => 'text', 'content' => 'Tổng tiền', 'colspan' => 5, 'align' => 'center'],
                        ['type' => 'binding', 'binding' => 'totals.Total', 'colspan' => 1, 'align' => 'right', 'format' => 'number'],
                        ['type' => 'binding', 'binding' => 'totals.DiscountAmount', 'colspan' => 1, 'align' => 'right', 'format' => 'number'],
                    ],
                ]],
            ]], 'footer' => [],
        ];
    }

    public function css(): string
    {
        return 'body{color:#0f172a;font-family:Arial,Helvetica,sans-serif;font-size:9px;margin:0 auto}.hotel-header{display:grid;grid-template-columns:175px 1fr;align-items:center;min-height:65px}.hotel-logo{display:flex;align-items:center;min-height:55px}.hotel-logo img{max-width:120px;max-height:55px;object-fit:contain}.hotel-information{line-height:1.8}hr{margin:0 0 10px;border:0;border-top:1px solid #cbd5e1}h1{margin:0;text-align:center;font-size:18px}.period{margin:8px 0 12px;text-align:center}.product-report{width:100%;border-collapse:collapse;table-layout:fixed}.product-report th,.product-report td{border:1px solid #cbd5e1;padding:4px 3px;line-height:1.15;vertical-align:middle;overflow-wrap:anywhere}.product-report th{background:#e2e8f0;color:#334155;text-align:center}.product-report .date-group{background:#fef3c7;color:#b45309;font-weight:bold;text-align:left}.product-report td:nth-child(1),.product-report td:nth-child(3){text-align:center}.product-report td:nth-child(n+4){text-align:right;white-space:nowrap}.product-report tfoot td{background:#f1f5f9;font-weight:bold}@media print{thead{display:table-header-group}tr{break-inside:avoid}}';
    }

    public function prepareData(array $data): array { return $this->sanitize($data); }
    public function render(array $data, ?TemplateRendererService $renderer = null): string
    {
        $renderer ??= new TemplateRendererService();
        $d = $this->definition();
        return $renderer->render($this->html(), $this->css(), $this->prepareData($data), array_intersect_key($d, array_flip(['page_size','page_orientation','margin_top','margin_bottom','margin_left','margin_right'])));
    }
    private function sanitize(mixed $value): mixed
    {
        if (is_array($value)) return array_map(fn ($item) => $this->sanitize($item), $value);
        if (!is_string($value)) return $value;
        return str_replace(['{','}'], ['&#123;','&#125;'], htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    }
};
