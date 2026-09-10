<?php

use App\Services\TemplateRendererService;

/**
 * Presentation-only reference provider for the company debt report.
 *
 * This file deliberately has no migration, database access, runtime registration, or
 * business calculation. Values in the report contract are supplied by the caller.
 */
return new class
{
    /**
     * Return metadata in the LA pure-provider shape consumed by the isolated tests.
     *
     * The data contract is presentation-only; it is not a legacy-to-new database mapping.
     */
    public function definition(): array
    {
        return [
            'code' => 'COMPANY_DEBT',
            'name' => 'Báo cáo công nợ công ty',
            'report' => 'COMPANY_DEBT_REFERENCE',
            'content_html' => $this->html(),
            'content_json' => $this->blocks(),
            'css' => $this->css(),
            'description' => 'Mẫu trình bày công nợ công ty theo ngày và công ty.',
            'page_size' => 'A4',
            'page_orientation' => 'portrait',
            'margin_top' => 8,
            'margin_right' => 5,
            'margin_bottom' => 8,
            'margin_left' => 5,
            'render_method' => 'render',
            'grouping' => [
                'levels' => [
                    ['field' => 'DateGroup', 'label' => 'Ngày', 'sort' => 'ASC'],
                    ['field' => 'CompanyId', 'label' => 'Công ty', 'sort' => 'ASC'],
                ],
                'max_debt_field' => 'MaxDebt',
                'aggregate_field' => 'TotalAmount',
            ],
            'columns' => $this->columns(),
            'data_contract' => [
                'rows' => [
                    'BookingId' => 'string',
                    'Room' => 'string',
                    'CompanyId' => 'string|int',
                    'CompanyName' => 'string',
                    'ArrivalDate' => 'string',
                    'DepartureDate' => 'string',
                    'Description' => 'string',
                    'TotalAmount' => 'number',
                    'Date' => 'string',
                    'DateGroup' => 'string',
                    'Username' => 'string',
                    'PaidMarker' => 'string',
                    'MaxDebt' => 'number',
                ],
                'company_summary' => [
                    'CompanyName' => 'string',
                    'TotalAmount' => 'number',
                    'PaidAmount' => 'number',
                    'RemainingAmount' => 'number',
                ],
                'totals' => [
                    'TotalAmount' => 'number',
                    'PaidAmount' => 'number',
                    'RemainingAmount' => 'number',
                ],
                // Settlement rows are supplied by a future read-only adapter. Their
                // monetary meaning is intentionally opaque to this presentation layer.
                'settlement_details' => [
                    'BookingId' => 'string',
                    'CompanyName' => 'string',
                    'PaymentId' => 'string|int',
                    'SettlementId' => 'string|int',
                    'Date' => 'string',
                    'Reference' => 'string',
                    'Amount' => 'number',
                    'Username' => 'string',
                    'Status' => 'string',
                ],
                'parameters' => [
                    'p_group_by_date' => 'bool',
                    'p_show_settlements' => 'bool',
                ],
            ],
        ];
    }

    /**
     * Escape caller-provided presentation text before invoking the shared raw renderer.
     *
     * hotel.logo is the one explicit trusted HTML exception: it represents the existing
     * hotel context and is preserved to avoid double-escaping an already-renderable logo.
     */
    public function render(array $data, ?TemplateRendererService $renderer = null): string
    {
        $renderer ??= new TemplateRendererService();

        // Keep the legacy two-level view for callers that predate the optional
        // grouping/detail parameters. Explicit false still disables date grouping.
        $data['parameters'] = array_replace([
            'p_group_by_date' => true,
            'p_show_settlements' => false,
        ], is_array($data['parameters'] ?? null) ? $data['parameters'] : []);

        $rendered = $renderer->render($this->html(), $this->css(), $this->escapeData($data), [
            'page_size' => 'A4',
            'page_orientation' => 'portrait',
            'margin_top' => 8,
            'margin_bottom' => 8,
            'margin_left' => 5,
            'margin_right' => 5,
        ]);

        // The shared grouped-row renderer resolves group.sum values but does not
        // support modifiers on that context. Keep this presentation-only fix
        // local to this reference provider and to the company total cell.
        return $this->formatCompanyGroupTotals($rendered);
    }

    public function blocks(): array
    {
        return [
            'header' => [
                [
                    'id' => 'company_debt_hotel_header',
                    'type' => 'text',
                    'content' => '<div class="hotel-header"><div class="hotel-logo">{{hotel.logo}}</div><div class="hotel-information"><div><b>Địa chỉ:</b> {{hotel.address}}</div><div><b>Nhân viên:</b> {{report.generated_by}} <span class="generated-date"><b>Ngày:</b> {{report.generated_at}}</span></div></div></div>',
                    'style' => ['fontSize' => '9px', 'marginBottom' => '4px'],
                ],
                ['id' => 'company_debt_divider', 'type' => 'divider', 'content' => '<hr class="header-divider">'],
                [
                    'id' => 'company_debt_title',
                    'type' => 'text',
                    'content' => '<h1>BÁO CÁO CÔNG NỢ CÔNG TY</h1>',
                    'style' => ['textAlign' => 'center', 'fontSize' => '20px', 'fontWeight' => 'bold'],
                ],
                [
                    'id' => 'company_debt_period',
                    'type' => 'text',
                    'content' => '<p class="report-period"><b>Ngày:</b> {{parameters.p_from_date}} &nbsp;&nbsp; ~ &nbsp;&nbsp; {{parameters.p_to_date}}</p>',
                    'style' => ['textAlign' => 'center', 'fontSize' => '9px'],
                ],
            ],
            'detail' => [
                [
                    'id' => 'company_debt_detail_table',
                    'type' => 'table',
                    'dataSource' => 'rows',
                    'tableType' => 'dynamic',
                    'tableStyle' => 'grid',
                    'groups' => [
                        ['id' => 'company_debt_date_group', 'field' => 'DateGroup', 'label' => 'Ngày: {{row.DateGroup}}', 'className' => 'date-group', 'sort' => 'ASC', 'enabledBy' => 'parameters.p_group_by_date'],
                        ['id' => 'company_debt_company_group', 'field' => 'CompanyId', 'label' => 'Công ty: {{row.CompanyName}} <span class="max-debt-label">Công nợ tối đa: {{row.MaxDebt|number}}</span>', 'className' => 'company-group', 'sort' => 'ASC'],
                    ],
                    'columns' => $this->columns(),
                    'customRows' => [
                        [
                            'id' => 'company_debt_company_total',
                            'scope' => 'group',
                            'level' => 1,
                            'className' => 'company-total-row',
                            'cells' => [
                                ['id' => 'company_debt_total_label', 'type' => 'text', 'content' => 'Tổng', 'colspan' => 3, 'align' => 'left', 'className' => 'total-label'],
                                ['id' => 'company_debt_total_amount', 'type' => 'text', 'content' => '{{group.sum.TotalAmount}}', 'colspan' => 2, 'align' => 'right', 'className' => 'money company-group-total-value'],
                                ['id' => 'company_debt_remaining_label', 'type' => 'text', 'content' => 'Số tiền còn nợ', 'colspan' => 2, 'align' => 'left', 'className' => 'remaining-label'],
                                ['id' => 'company_debt_remaining_value', 'type' => 'text', 'content' => '—', 'colspan' => 3, 'align' => 'right', 'className' => 'missing-value'],
                            ],
                        ],
                    ],
                ],
            ],
            'footer' => [
                [
                    'id' => 'company_debt_settlement_details',
                    'type' => 'text',
                    'content' => '<div class="report-settlement-band"><table class="company-debt-settlements"><tbody><tr class="pms-custom-row" data-visible-by="parameters.p_show_settlements"><th>Mã ĐK</th><th>Công Ty</th><th>Mã thanh toán</th><th>Mã giải trừ</th><th>Ngày</th><th>Tham chiếu</th><th>Số tiền</th></tr><tr class="pms-detail-row pms-custom-row" data-source="settlement_details" data-visible-by="parameters.p_show_settlements"><td>{{item.BookingId}}</td><td>{{item.CompanyName}}</td><td>{{item.PaymentId}}</td><td>{{item.SettlementId}}</td><td>{{item.Date}}</td><td>{{item.Reference}}</td><td class="money">{{item.Amount|number}}</td></tr></tbody></table></div>',
                    'style' => ['fontSize' => '9px'],
                ],
                ['id' => 'company_debt_summary_title', 'type' => 'text', 'content' => '<h2>Bảng Kê Tổng Hợp Công Nợ</h2>'],
                [
                    'id' => 'company_debt_summary_table',
                    'type' => 'table',
                    'dataSource' => 'company_summary',
                    'tableType' => 'dynamic',
                    'tableStyle' => 'grid',
                    'columns' => [
                        ['header' => 'Công Ty', 'value' => 'item.CompanyName', 'width' => '40%'],
                        ['header' => 'Tổng', 'value' => 'item.TotalAmount', 'width' => '20%', 'format' => 'number'],
                        ['header' => 'Đã trả', 'value' => 'item.PaidAmount', 'width' => '20%', 'format' => 'number'],
                        ['header' => 'Chưa Thanh Toán', 'value' => 'item.RemainingAmount', 'width' => '20%', 'format' => 'number'],
                    ],
                    'customRows' => [
                        [
                            'id' => 'company_debt_summary_total',
                            'scope' => 'table',
                            'cells' => [
                                ['id' => 'company_debt_summary_total_label', 'type' => 'text', 'content' => 'Tổng', 'colspan' => 1, 'align' => 'left', 'className' => 'total-label'],
                                ['id' => 'company_debt_summary_total_amount', 'type' => 'binding', 'binding' => 'totals.TotalAmount', 'colspan' => 1, 'align' => 'right', 'format' => 'number', 'className' => 'money'],
                                ['id' => 'company_debt_summary_paid_amount', 'type' => 'binding', 'binding' => 'totals.PaidAmount', 'colspan' => 1, 'align' => 'right', 'format' => 'number', 'className' => 'money'],
                                ['id' => 'company_debt_summary_remaining_amount', 'type' => 'binding', 'binding' => 'totals.RemainingAmount', 'colspan' => 1, 'align' => 'right', 'format' => 'number', 'className' => 'money'],
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'company_debt_signatures',
                    'type' => 'text',
                    'content' => '<div class="signature-labels"><span>Staff</span><span>FOM</span><span>DGM</span><span>GM</span></div>',
                ],
            ],
        ];
    }

    public function html(): string
    {
        return <<<'HTML'
<div class="report-header-band">
  <div class="hotel-header"><div class="hotel-logo">{{hotel.logo}}</div><div class="hotel-information"><div><b>Địa chỉ:</b> {{hotel.address}}</div><div><b>Nhân viên:</b> {{report.generated_by}} <span class="generated-date"><b>Ngày:</b> {{report.generated_at}}</span></div></div></div>
  <hr class="header-divider">
  <h1>BÁO CÁO CÔNG NỢ CÔNG TY</h1>
  <p class="report-period"><b>Ngày:</b> <span>{{parameters.p_from_date}}</span><b>~</b><span>{{parameters.p_to_date}}</span></p>
</div>
<div class="report-detail-band">
  <table class="company-debt-table">
    <colgroup><col style="width:7%"><col style="width:7%"><col style="width:10%"><col style="width:10%"><col style="width:10%"><col style="width:20%"><col style="width:13%"><col style="width:11%"><col style="width:8%"><col style="width:4%"></colgroup>
    <thead><tr><th>Mã ĐK</th><th>Phòng</th><th>Công Ty</th><th>Ngày Đến</th><th>Ngày Đi</th><th>Ghi Chú</th><th>Tổng tiền</th><th>Ngày</th><th>Thu Ngân</th><th>Đã trả</th></tr></thead>
    <tbody class="pms-grouped-rows" data-source="rows" data-group-configured="1" data-group-by="DateGroup">
      <tr class="pms-group-header" data-group-level="0" data-group-field="DateGroup" data-group-sort="ASC" data-group-enabled-by="parameters.p_group_by_date"><td colspan="10" class="date-group">Ngày: {{row.DateGroup}}</td></tr>
      <tr class="pms-group-header" data-group-level="1" data-group-field="CompanyId" data-group-sort="ASC"><td colspan="5" class="company-group">Công ty: {{row.CompanyName}}</td><td colspan="5" class="max-debt">Công nợ tối đa: {{row.MaxDebt|number}}</td></tr>
      <tr class="pms-detail-row"><td>{{row.BookingId}}</td><td>{{row.Room}}</td><td>{{row.CompanyName}}</td><td>{{row.ArrivalDate}}</td><td>{{row.DepartureDate}}</td><td>{{row.Description}}</td><td class="money">{{row.TotalAmount|number}}</td><td>{{row.Date}}</td><td>{{row.Username}}</td><td class="paid-marker">{{row.PaidMarker}}</td></tr>
      <tr class="pms-group-custom-row company-total-row" data-group-level="1"><td colspan="3" class="total-label">Tổng</td><td colspan="2" class="money company-group-total-value">{{group.sum.TotalAmount}}</td><td colspan="2" class="remaining-label">Số tiền còn nợ</td><td colspan="3" class="missing-value">—</td></tr>
    </tbody>
  </table>
</div>
<div class="report-settlement-band">
  <table class="company-debt-settlements">
    <tbody>
      <tr class="pms-custom-row" data-visible-by="parameters.p_show_settlements"><th>Mã ĐK</th><th>Công Ty</th><th>Mã thanh toán</th><th>Mã giải trừ</th><th>Ngày</th><th>Tham chiếu</th><th>Số tiền</th></tr>
      <tr class="pms-detail-row pms-custom-row" data-source="settlement_details" data-visible-by="parameters.p_show_settlements"><td>{{item.BookingId}}</td><td>{{item.CompanyName}}</td><td>{{item.PaymentId}}</td><td>{{item.SettlementId}}</td><td>{{item.Date}}</td><td>{{item.Reference}}</td><td class="money">{{item.Amount|number}}</td></tr>
    </tbody>
  </table>
</div>
<div class="report-summary-band">
  <h2>Bảng Kê Tổng Hợp Công Nợ</h2>
  <table class="company-debt-summary">
    <colgroup><col style="width:40%"><col style="width:20%"><col style="width:20%"><col style="width:20%"></colgroup>
    <thead><tr><th>Công Ty</th><th>Tổng</th><th>Đã trả</th><th>Chưa Thanh Toán</th></tr></thead>
    <tbody><tr class="pms-detail-row" data-source="company_summary"><td>{{item.CompanyName}}</td><td class="money">{{item.TotalAmount|number}}</td><td class="money">{{item.PaidAmount|number}}</td><td class="money">{{item.RemainingAmount|number}}</td></tr></tbody>
    <tfoot><tr><td class="total-label">Tổng</td><td class="money">{{totals.TotalAmount|number}}</td><td class="money">{{totals.PaidAmount|number}}</td><td class="money">{{totals.RemainingAmount|number}}</td></tr></tfoot>
  </table>
</div>
<div class="signature-labels"><span>Staff</span><span>FOM</span><span>DGM</span><span>GM</span></div>
HTML;
    }

    public function css(): string
    {
        return <<<'CSS'
body { color: #0f172a; font-family: Arial, Helvetica, sans-serif; font-size: 9px; }
.hotel-header { display: grid; grid-template-columns: 38% 62%; align-items: center; min-height: 66px; }
.hotel-logo { display: flex; align-items: center; min-height: 58px; }
.hotel-logo img, .hotel-logo-image { display: block; max-width: 120px; max-height: 60px; object-fit: contain; }
.hotel-information { line-height: 1.8; }
.hotel-information .generated-date { float: right; }
.header-divider { margin: 0 0 22px; border: 0; border-top: 1px solid #cbd5e1; }
h1 { margin: 0; text-align: center; font-size: 20px; line-height: 1.25; }
.report-period { display: flex; justify-content: center; gap: 18px; margin: 20px 0 42px; font-weight: 700; }
.company-debt-table, #company_debt_detail_table table, .company-debt-summary, #company_debt_summary_table table { width: 100%; border-collapse: collapse; table-layout: fixed; }
.company-debt-table th, .company-debt-table td, #company_debt_detail_table th, #company_debt_detail_table td, .company-debt-summary th, .company-debt-summary td, #company_debt_summary_table th, #company_debt_summary_table td { border: 1px solid #cbd5e1; padding: 4px 3px; line-height: 1.15; vertical-align: middle; overflow-wrap: anywhere; }
.company-debt-table th, #company_debt_detail_table th, .company-debt-summary th, #company_debt_summary_table th { background: #e2e8f0; color: #334155; text-align: center; font-weight: 700; }
.company-debt-table td, #company_debt_detail_table td { text-align: center; }
.company-debt-table td:nth-child(3), .company-debt-table td:nth-child(6), #company_debt_detail_table td:nth-child(3), #company_debt_detail_table td:nth-child(6), .company-debt-summary td:first-child, #company_debt_summary_table td:first-child { text-align: left; }
.money { text-align: right !important; }
.paid-marker { text-align: center !important; }
.date-group { color: #334155; background: #f8fafc; font-weight: 700; text-align: left !important; }
.company-group { background: #fff; font-weight: 700; text-align: left !important; }
.max-debt { background: #f8fafc; font-weight: 700; text-align: right !important; }
.max-debt-label { float: right; }
.pms-group-footer td { font-weight: 700; background: #f8fafc; }
.pms-group-custom-row td { font-weight: 700; background: #f8fafc; }
.total-label { text-align: left !important; }
.remaining-label { text-align: left !important; }
.missing-value { color: #64748b; text-align: right !important; }
.report-summary-band { margin-top: 28px; }
.report-settlement-band { margin-top: 18px; }
.company-debt-settlements { width: 100%; border-collapse: collapse; }
.company-debt-settlements > thead th { background: #e2e8f0; }
.settlement-detail-table { width: 100%; margin: 0; border-collapse: collapse; }
.settlement-detail-table td { border: 0; padding: 0; }
h2 { margin: 0 0 12px; text-align: center; font-size: 18px; }
.company-debt-summary, #company_debt_summary_table table { width: 72%; margin: 0 auto; }
.company-debt-summary td, #company_debt_summary_table td { text-align: center; }
.company-debt-summary tfoot td, #company_debt_summary_table tfoot td { background: #f1f5f9; font-weight: 700; }
.signature-labels { display: flex; justify-content: space-between; margin: 32px 8% 0; font-weight: 700; }
@media print { thead { display: table-header-group; } tr { break-inside: avoid; } }
CSS;
    }

    private function columns(): array
    {
        return [
            ['header' => 'Mã ĐK', 'value' => 'row.BookingId', 'width' => '7%', 'align' => 'center'],
            ['header' => 'Phòng', 'value' => 'row.Room', 'width' => '7%', 'align' => 'center'],
            ['header' => 'Công Ty', 'value' => 'row.CompanyName', 'width' => '10%', 'align' => 'left'],
            ['header' => 'Ngày Đến', 'value' => 'row.ArrivalDate', 'width' => '10%', 'align' => 'center'],
            ['header' => 'Ngày Đi', 'value' => 'row.DepartureDate', 'width' => '10%', 'align' => 'center'],
            ['header' => 'Ghi Chú', 'value' => 'row.Description', 'width' => '20%', 'align' => 'left'],
            ['header' => 'Tổng tiền', 'value' => 'row.TotalAmount', 'width' => '13%', 'format' => 'number', 'align' => 'right'],
            ['header' => 'Ngày', 'value' => 'row.Date', 'width' => '11%', 'align' => 'center'],
            ['header' => 'Thu Ngân', 'value' => 'row.Username', 'width' => '8%', 'align' => 'center'],
            ['header' => 'Đã trả', 'value' => 'row.PaidMarker', 'width' => '4%', 'align' => 'center'],
        ];
    }

    private function escapeData(array $data): array
    {
        return $this->escapeValue($data, []);
    }

    private function formatCompanyGroupTotals(string $rendered): string
    {
        $pattern = '/(<td\b[^>]*class="[^"]*\bcompany-group-total-value\b[^"]*"[^>]*>)(-?[0-9]+(?:\.[0-9]+)?)(<\/td>)/i';

        return preg_replace_callback($pattern, static function (array $matches): string {
            return $matches[1].number_format((float) $matches[2], 0, ',', '.').$matches[3];
        }, $rendered) ?? $rendered;
    }

    private function escapeValue(mixed $value, array $path): mixed
    {
        if (is_array($value)) {
            $escaped = [];
            foreach ($value as $key => $item) {
                $escaped[$key] = $this->escapeValue($item, [...$path, (string) $key]);
            }

            return $escaped;
        }

        if ($path === ['hotel', 'logo'] && is_string($value)) {
            // Trusted existing hotel HTML; deliberately do not escape or double-escape it.
            return $value;
        }

        if (is_int($value) || is_float($value) || is_bool($value) || $value === null) {
            return $value;
        }

        if ($value instanceof Stringable) {
            $value = (string) $value;
        }

        return is_string($value) ? $this->escapeText($value) : $value;
    }

    private function escapeText(string $value): string
    {
        $escaped = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');

        // The shared renderer performs a second placeholder pass after row expansion.
        // Entity-encode braces so caller text containing {{...}} stays text.
        return strtr($escaped, ['{' => '&#123;', '}' => '&#125;']);
    }
};
