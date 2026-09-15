<?php

use App\Services\TemplateRendererService;

/**
 * Pure reference provider for the legacy MB (minibar free) invoice report (sp_202).
 *
 * This file deliberately has no database side effects. The presentation
 * contract is supplied by the report data source; this provider only defines
 * the layout and protects untrusted presentation text before using the
 * existing raw-string renderer.
 */
return new class
{
    /**
     * Return metadata and all template payloads without applying anything to
     * the runtime templates table.
     */
    public function definition(): array
    {
        return [
            'report' => 'MINIBAR_FREE_INVOICES_REFERENCE',
            'name' => 'Báo cáo hóa đơn minibar miễn phí - Mẫu tham chiếu',
            'page_size' => 'A4',
            'page_orientation' => 'portrait',
            'margin_top' => 6,
            'margin_bottom' => 6,
            'margin_left' => 5,
            'margin_right' => 5,
            'version' => '1.0',
            'content_html' => $this->html(),
            'content_json' => $this->blocks(),
            'css' => $this->css(),
        ];
    }

    public function html(): string
    {
        $headerProvider = require __DIR__.'/minibar_invoices_by_product_reference.php';
        $headerHtml = $headerProvider->compileHeader($this->blocks()['header']);

        return $headerHtml.<<<'HTML'
<table class="minibar-free-invoices-table"><colgroup><col style="width:3%"><col style="width:7%"><col style="width:5%"><col style="width:13%"><col style="width:13%"><col style="width:11%"><col style="width:8%"><col style="width:8%"><col style="width:8%"><col style="width:7%"><col style="width:8%"><col style="width:5%"><col style="width:4%"></colgroup><thead><tr><th>STT</th><th>Mã ĐK</th><th>Phòng</th><th>Tên Khách</th><th>Mô Tả</th><th>Sản phẩm</th><th>Tổng tiền</th><th>Giảm Giá</th><th>Thành<br>Tiền</th><th>Mã TT</th><th>Ghi Chú</th><th>Người<br>Dùng</th><th>HTTT</th></tr></thead><tbody class="pms-grouped-rows" data-source="rows" data-group-by="DateGroup"><tr class="pms-group-header"><td colspan="13" class="date-group">Ngày: {{row.DateGroup}}</td></tr><tr class="pms-detail-row"><td>{{row.STT}}</td><td>{{row.BookingId}}</td><td>{{row.Room}}</td><td>{{row.Guest}}</td><td>{{row.DescriptionServive}}</td><td>{{row.Product}}</td><td>{{row.TotalAmount|number}}</td><td>{{row.DiscountAmount|number}}</td><td>{{row.NetAmount|number}}</td><td>{{row.PaymentID}}</td><td>{{row.BillNote}}</td><td>{{row.Username}}</td><td>{{row.HTTT}}</td></tr></tbody><tfoot><tr class="invoice-total-row"><td colspan="6">Tổng tiền</td><td>{{totals.TotalAmount|number}}</td><td>{{totals.DiscountAmount|number}}</td><td>{{totals.NetAmount|number}}</td><td colspan="4"></td></tr></tfoot></table>
<h2 class="product-summary-title">BẢNG KÊ THEO SẢN PHẨM</h2><table class="minibar-free-product-summary"><colgroup><col style="width:60%"><col style="width:20%"><col style="width:20%"></colgroup><thead><tr><th>Sản Phẩm</th><th>Số Lượng</th><th>Tổng Tiền</th></tr></thead><tbody><tr class="pms-detail-row" data-source="product_summary"><td>{{item.Product}}</td><td>{{item.Quantity}}</td><td>{{item.TotalAmount|number}}</td></tr></tbody></table>
HTML;
    }

    /**
     * Structured blocks mirror the HTML layout and are suitable as the source
     * for a future runtime registration or designer import.
     */
    public function blocks(): array
    {
        $headerProvider = require __DIR__.'/minibar_invoices_by_product_reference.php';
        $header = $headerProvider->headerBlocks(
            'minibar_free_invoices',
            '<h1>BÁO CÁO HÓA ĐƠN MINIBAR MIỄN PHÍ</h1>',
            '<p class="report-period"><b>Ngày:</b> {{parameters.p_from_date}} &nbsp; ~ &nbsp; {{parameters.p_to_date}}</p>'
        );

        return [
            'header' => $header,
            'detail' => [
                [
                    'id' => 'MINIBAR_FREE_INVOICES_table',
                    'type' => 'table',
                    'dataSource' => 'rows',
                    'tableType' => 'dynamic',
                    'tableStyle' => 'grid',
                    'groups' => [[
                        'id' => 'MINIBAR_FREE_INVOICES_date_group',
                        'field' => 'DateGroup',
                        'label' => 'Ngày: {{row.DateGroup}}',
                        'className' => 'date-group',
                        'enabledBy' => '',
                        'sort' => 'ASC',
                    ]],
                    'columns' => $this->invoiceColumns(),
                    'customRows' => [[
                        'id' => 'minibar_invoice_totals',
                        'enabledBy' => '',
                        'scope' => 'table',
                        'level' => 0,
                        'className' => 'invoice-total-row',
                        'cells' => [
                            ['id' => 'minibar_invoice_total_label', 'type' => 'text', 'content' => 'Tổng tiền', 'binding' => '', 'aggregateField' => '', 'colspan' => 6, 'align' => 'center', 'format' => ''],
                            ['id' => 'minibar_invoice_total_amount', 'type' => 'binding', 'content' => '', 'binding' => 'totals.TotalAmount', 'aggregateField' => '', 'colspan' => 1, 'align' => 'right', 'format' => 'number'],
                            ['id' => 'minibar_invoice_discount_amount', 'type' => 'binding', 'content' => '', 'binding' => 'totals.DiscountAmount', 'aggregateField' => '', 'colspan' => 1, 'align' => 'right', 'format' => 'number'],
                            ['id' => 'minibar_invoice_net_amount', 'type' => 'binding', 'content' => '', 'binding' => 'totals.NetAmount', 'aggregateField' => '', 'colspan' => 1, 'align' => 'right', 'format' => 'number'],
                            ['id' => 'minibar_invoice_totals_spacer', 'type' => 'text', 'content' => '', 'binding' => '', 'aggregateField' => '', 'colspan' => 4, 'align' => 'left', 'format' => ''],
                        ],
                    ]],
                ],
                ['id' => 'minibar_free_product_summary_title', 'type' => 'text', 'content' => '<h2 class="product-summary-title">BẢNG KÊ THEO SẢN PHẨM</h2>'],
                [
                    'id' => 'minibar_free_product_summary_table',
                    'type' => 'table',
                    'dataSource' => 'product_summary',
                    'tableType' => 'dynamic',
                    'tableStyle' => 'grid',
                    'columns' => [
                        ['header' => 'Sản Phẩm', 'value' => 'item.Product', 'width' => '60%', 'align' => 'left'],
                        ['header' => 'Số Lượng', 'value' => 'item.Quantity', 'width' => '20%', 'align' => 'center'],
                        ['header' => 'Tổng Tiền', 'value' => 'item.TotalAmount', 'width' => '20%', 'align' => 'right', 'format' => 'number'],
                    ],
                ],
            ],
            'footer' => [],
        ];
    }

    public function contentJson(): array
    {
        return $this->blocks();
    }

    public function css(): string
    {
        return <<<'CSS'
body{color:#0f172a;font-family:Arial,Helvetica,sans-serif;font-size:9px;max-width:210mm;margin:0 auto}.hotel-header{display:grid;grid-template-columns:175px 1fr;align-items:center;min-height:65px}.hotel-logo{display:flex;align-items:center;min-height:55px}.hotel-logo img{max-width:120px;max-height:55px;object-fit:contain}.hotel-information{line-height:1.8}.header-divider{margin:0 0 10px;border:0;border-top:1px solid #cbd5e1}h1{margin:0;text-align:center;font-size:18px}.report-period{margin:8px 0 12px;text-align:center}.minibar-free-invoices-table,#MINIBAR_FREE_INVOICES_table table,.minibar-free-product-summary,#minibar_free_product_summary_table table{width:100%;border-collapse:collapse;table-layout:fixed}.minibar-free-invoices-table th,.minibar-free-invoices-table td,#MINIBAR_FREE_INVOICES_table th,#MINIBAR_FREE_INVOICES_table td,.minibar-free-product-summary th,.minibar-free-product-summary td,#minibar_free_product_summary_table th,#minibar_free_product_summary_table td{border:1px solid #cbd5e1;padding:4px 3px;line-height:1.15;vertical-align:middle;overflow-wrap:anywhere}.minibar-free-invoices-table th,.minibar-free-product-summary th,#MINIBAR_FREE_INVOICES_table th,#minibar_free_product_summary_table th{background:#e2e8f0;color:#334155;text-align:center}.minibar-free-invoices-table .date-group,#MINIBAR_FREE_INVOICES_table .date-group{background:#f8fafc;color:#334155;font-weight:bold;text-align:left!important}.minibar-free-invoices-table .invoice-total-row td,#MINIBAR_FREE_INVOICES_table .invoice-total-row td{background:#f1f5f9;font-weight:bold}.minibar-free-invoices-table .invoice-total-row td:first-child,#MINIBAR_FREE_INVOICES_table .invoice-total-row td:first-child{text-align:center}.minibar-free-invoices-table td:nth-child(1),.minibar-free-invoices-table td:nth-child(3),.minibar-free-invoices-table td:nth-child(10),.minibar-free-invoices-table td:nth-child(13),#MINIBAR_FREE_INVOICES_table td:nth-child(1),#MINIBAR_FREE_INVOICES_table td:nth-child(3),#MINIBAR_FREE_INVOICES_table td:nth-child(10),#MINIBAR_FREE_INVOICES_table td:nth-child(13),.minibar-free-product-summary td:nth-child(2),#minibar_free_product_summary_table td:nth-child(2){text-align:center}.minibar-free-invoices-table td:nth-child(7),.minibar-free-invoices-table td:nth-child(8),.minibar-free-invoices-table td:nth-child(9),#MINIBAR_FREE_INVOICES_table td:nth-child(7),#MINIBAR_FREE_INVOICES_table td:nth-child(8),#MINIBAR_FREE_INVOICES_table td:nth-child(9),.minibar-free-product-summary td:nth-child(3),#minibar_free_product_summary_table td:nth-child(3){text-align:right;white-space:nowrap}.product-summary-title{margin:28px 0 20px;text-align:center;font-size:18px}.minibar-free-product-summary,#minibar_free_product_summary_table table{width:60%;margin:0 auto}.minibar-free-product-summary td,#minibar_free_product_summary_table td{min-height:22px}@media print{thead{display:table-header-group}tr{break-inside:avoid}}
CSS;
    }

    /**
     * Prepare the presentation contract and render it with the existing
     * renderer. Numbers remain numbers for aggregate/number formatting.
     * hotel.logo is an explicitly trusted existing context value.
     */
    public function render(array $data, ?TemplateRendererService $renderer = null): string
    {
        $renderer ??= new TemplateRendererService();

        $definition = $this->definition();
        $pageOptions = [];
        foreach (['page_size', 'page_orientation', 'margin_top', 'margin_bottom', 'margin_left', 'margin_right'] as $key) {
            if (array_key_exists($key, $definition)) {
                $pageOptions[$key] = $definition[$key];
            }
        }

        return $renderer->render($this->html(), $this->css(), $this->prepareData($data), $pageOptions);
    }

    public function prepareData(array $data): array
    {
        return $this->sanitizeValue($data);
    }

    private function invoiceColumns(): array
    {
        return [
            ['header' => 'STT', 'value' => 'row.STT', 'width' => '3%', 'align' => 'center'],
            ['header' => 'Mã ĐK', 'value' => 'row.BookingId', 'width' => '7%', 'align' => 'center'],
            ['header' => 'Phòng', 'value' => 'row.Room', 'width' => '5%', 'align' => 'center'],
            ['header' => 'Tên Khách', 'value' => 'row.Guest', 'width' => '13%', 'align' => 'left'],
            ['header' => 'Mô Tả', 'value' => 'row.DescriptionServive', 'width' => '13%', 'align' => 'left'],
            ['header' => 'Sản phẩm', 'value' => 'row.Product', 'width' => '11%', 'align' => 'left'],
            ['header' => 'Tổng tiền', 'value' => 'row.TotalAmount', 'width' => '8%', 'align' => 'right', 'format' => 'number'],
            ['header' => 'Giảm Giá', 'value' => 'row.DiscountAmount', 'width' => '8%', 'align' => 'right', 'format' => 'number'],
            ['header' => 'Thành Tiền', 'value' => 'row.NetAmount', 'width' => '8%', 'align' => 'right', 'format' => 'number'],
            ['header' => 'Mã TT', 'value' => 'row.PaymentID', 'width' => '7%', 'align' => 'center'],
            ['header' => 'Ghi Chú', 'value' => 'row.BillNote', 'width' => '8%', 'align' => 'left'],
            ['header' => 'Người Dùng', 'value' => 'row.Username', 'width' => '5%', 'align' => 'left'],
            ['header' => 'HTTT', 'value' => 'row.HTTT', 'width' => '4%', 'align' => 'center'],
        ];
    }

    private function sanitizeValue(mixed $value, string $path = ''): mixed
    {
        if (is_array($value)) {
            $sanitized = [];
            foreach ($value as $key => $nestedValue) {
                $nestedPath = $path === '' ? (string) $key : $path.'.'.$key;
                $sanitized[$key] = $this->sanitizeValue($nestedValue, $nestedPath);
            }

            return $sanitized;
        }

        if (! is_string($value) || $path === 'hotel.logo') {
            return $value;
        }

        $escaped = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // Renderer interpolation happens before its final placeholder pass;
        // encode braces so user text cannot smuggle a second placeholder.
        return str_replace(['{', '}'], ['&#123;', '&#125;'], $escaped);
    }
};
