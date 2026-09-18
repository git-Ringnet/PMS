<?php

use App\Services\TemplateRendererService;

/**
 * Reference template provider for PAID_COMPANY_DEBTS (sp_294 legacy reference).
 * Configured with full 2-tier header Designer blocks, HTML, and CSS.
 */
return new class
{
    public function definition(): array
    {
        return [
            'code' => 'PAID_COMPANY_DEBTS',
            'name' => 'Báo cáo công nợ đã thanh toán',
            'report' => 'PAID_COMPANY_DEBTS_REFERENCE',
            'page_size' => 'A4',
            'page_orientation' => 'portrait',
            'margin_top' => 6,
            'margin_right' => 5,
            'margin_bottom' => 6,
            'margin_left' => 5,
            'version' => '1.0',
            'content_html' => $this->html(),
            'content_json' => $this->blocks(),
            'css' => $this->css(),
            'columns' => $this->columns(),
            'data_contract' => [
                'rows' => [
                    'DebtRowKey' => 'string|int',
                    'BookingId' => 'string',
                    'Room' => 'string',
                    'BookingName' => 'string',
                    'ArrivalDate' => 'string',
                    'DepartureDate' => 'string',
                    'AmountCN' => 'number',
                    'AmountCNForTotal' => 'number',
                    'PaymentMethodCN' => 'string',
                    'DateCN' => 'string',
                    'UserCN' => 'string',
                    'IsPaidMarker' => 'string',
                    'DateTT' => 'string',
                    'AmountTT' => 'number',
                    'PaymentMethodTT' => 'string',
                    'RemainAmount' => 'number',
                    'UserTT' => 'string',
                    'Description' => 'string',
                    'Company' => 'string',
                    'CompanyName' => 'string',
                    'DateGroup' => 'string',
                ],
                'payment_method_summary' => [
                    'Method' => 'string',
                    'Amount' => 'number',
                ],
                'totals' => [
                    'DebtAmount' => 'number',
                    'PaidAmount' => 'number',
                    'RemainingAmount' => 'number',
                ],
                'parameters' => [
                    'p_from_date' => 'string',
                    'p_to_date' => 'string',
                    'p_view_type' => 'int',
                    'p_company_id' => 'int',
                    'p_group_by_company' => 'int',
                ],
            ],
        ];
    }

    public function html(): string
    {
        return $this->compileDesignerBlocks($this->blocks());
    }

    public function blocks(): array
    {
        $header = [
            [
                'id' => 'paid_debts_header_band',
                'type' => 'columns',
                'style' => [
                    'display' => 'flex',
                    'justifyContent' => 'space-between',
                    'alignItems' => 'flex-start',
                    'marginBottom' => '6px',
                ],
                'columns' => [
                    [
                        'width' => '30%',
                        'blocks' => [[
                            'id' => 'paid_debts_logo',
                            'type' => 'text',
                            'content' => '<div class="hotel-logo" style="min-height: 52px;">{{hotel.logo}}</div>',
                            'style' => ['fontSize' => '13px'],
                        ]],
                    ],
                    [
                        'width' => '70%',
                        'blocks' => [[
                            'id' => 'paid_debts_hotel_info',
                            'type' => 'text',
                            'content' => '<div class="hotel-information" style="text-align: right; font-size: 11px; line-height: 1.4;">'
                                .'<div><b>Địa chỉ:</b> {{hotel.address}}</div>'
                                .'<div><b>Nhân viên:</b> {{report.generated_by}} &nbsp;&nbsp; <b>Ngày:</b> {{report.generated_at}}</div>'
                                .'</div>',
                            'style' => ['textAlign' => 'right'],
                        ]],
                    ],
                ],
            ],
            [
                'id' => 'paid_debts_divider',
                'type' => 'divider',
                'content' => '<hr class="header-divider" style="border: none; border-top: 1px solid #000; margin: 4px 0 8px 0;">',
                'style' => ['marginTop' => '2px', 'marginBottom' => '6px'],
            ],
            [
                'id' => 'paid_debts_title',
                'type' => 'text',
                'content' => '<h1 style="text-align: center; font-size: 18px; font-weight: bold; margin: 4px 0; text-transform: uppercase;">BÁO CÁO CÔNG NỢ ĐÃ THANH TOÁN</h1>',
                'style' => ['textAlign' => 'center', 'fontWeight' => 'bold'],
            ],
            [
                'id' => 'paid_debts_period',
                'type' => 'text',
                'content' => '<p class="period" style="text-align: center; font-size: 11px; margin: 2px 0 10px 0;">{{parameters.p_from_date}} - {{parameters.p_to_date}}</p>',
                'style' => ['textAlign' => 'center', 'fontSize' => '11px', 'marginBottom' => '10px'],
            ],
        ];

        return [
            'header' => $header,
            'detail' => [
                [
                    'id' => 'paid_company_debts_table',
                    'type' => 'table',
                    'dataSource' => 'rows',
                    'tableType' => 'dynamic',
                    'tableStyle' => 'grid',
                    'tableClassName' => 'paid-company-debts-table',
                    'style' => ['width' => '100%', 'fontSize' => '9.5px'],
                    'hasTwoTierHeader' => true,
                    'topHeader' => [
                        ['label' => 'Thông Tin Công Nợ', 'colspan' => 10, 'align' => 'center', 'style' => ['backgroundColor' => '#f1f5f9', 'fontWeight' => 'bold']],
                        ['label' => 'Thông Tin Thanh Toán', 'colspan' => 6, 'align' => 'center', 'style' => ['backgroundColor' => '#e2e8f0', 'fontWeight' => 'bold']],
                    ],
                    'grouping' => [
                        [
                            'id' => 'paid_debts_date_group',
                            'field' => 'DateGroup',
                            'label' => 'Ngày: {{row.DateGroup}}',
                            'className' => 'date-group-header',
                            'sort' => 'ASC',
                            'headerCells' => [
                                [
                                    'id' => 'date_group_cell',
                                    'type' => 'text',
                                    'content' => '<span style="color: #b91c1c; font-weight: bold;">Ngày: {{row.DateGroup}}</span>',
                                    'colspan' => 16,
                                    'align' => 'left',
                                    'style' => ['padding' => '4px 6px', 'fontWeight' => 'bold'],
                                ],
                            ],
                        ],
                        [
                            'id' => 'paid_debts_company_group',
                            'field' => 'CompanyName',
                            'label' => 'Công Ty: {{row.CompanyName}}',
                            'className' => 'company-group-header',
                            'sort' => 'ASC',
                            'headerCells' => [
                                [
                                    'id' => 'company_group_cell',
                                    'type' => 'text',
                                    'content' => '<span style="color: #0f172a; font-weight: bold;">Công Ty: {{row.CompanyName}}</span>',
                                    'colspan' => 16,
                                    'align' => 'left',
                                    'style' => ['padding' => '4px 6px', 'fontWeight' => 'bold'],
                                ],
                            ],
                        ],
                    ],
                    'columns' => $this->columns(),
                    'customRows' => [
                        [
                            'id' => 'paid_debts_company_total_row',
                            'enabledBy' => '',
                            'scope' => 'group',
                            'level' => 1,
                            'className' => 'company-subtotal-row',
                            'cells' => [
                                ['id' => 'c_sub_label', 'type' => 'text', 'content' => 'Tổng:', 'colspan' => 5, 'align' => 'right', 'style' => ['fontWeight' => 'bold']],
                                ['id' => 'c_sub_debt', 'type' => 'binding', 'binding' => 'row.CompanyTotalDebt', 'colspan' => 1, 'align' => 'right', 'format' => 'number', 'style' => ['fontWeight' => 'bold']],
                                ['id' => 'c_sub_spacer1', 'type' => 'text', 'content' => '', 'colspan' => 5, 'align' => 'left'],
                                ['id' => 'c_sub_paid', 'type' => 'binding', 'binding' => 'row.CompanyTotalPaid', 'colspan' => 1, 'align' => 'right', 'format' => 'number', 'style' => ['fontWeight' => 'bold']],
                                ['id' => 'c_sub_spacer2', 'type' => 'text', 'content' => '-', 'colspan' => 1, 'align' => 'center'],
                                ['id' => 'c_sub_remain', 'type' => 'binding', 'binding' => 'row.CompanyRemaining', 'colspan' => 1, 'align' => 'right', 'format' => 'number', 'style' => ['fontWeight' => 'bold']],
                                ['id' => 'c_sub_spacer3', 'type' => 'text', 'content' => '', 'colspan' => 2, 'align' => 'left'],
                            ],
                        ],
                        [
                            'id' => 'paid_debts_date_total_row',
                            'enabledBy' => '',
                            'scope' => 'group',
                            'level' => 0,
                            'className' => 'date-subtotal-row',
                            'cells' => [
                                ['id' => 'd_sub_label', 'type' => 'text', 'content' => 'Tổng Ngày:', 'colspan' => 5, 'align' => 'right', 'style' => ['fontWeight' => 'bold']],
                                ['id' => 'd_sub_debt', 'type' => 'binding', 'binding' => 'row.DateTotalDebt', 'colspan' => 1, 'align' => 'right', 'format' => 'number', 'style' => ['fontWeight' => 'bold']],
                                ['id' => 'd_sub_spacer1', 'type' => 'text', 'content' => '', 'colspan' => 5, 'align' => 'left'],
                                ['id' => 'd_sub_paid', 'type' => 'binding', 'binding' => 'row.DateTotalPaid', 'colspan' => 1, 'align' => 'right', 'format' => 'number', 'style' => ['fontWeight' => 'bold']],
                                ['id' => 'd_sub_spacer2', 'type' => 'text', 'content' => '-', 'colspan' => 1, 'align' => 'center'],
                                ['id' => 'd_sub_remain', 'type' => 'binding', 'binding' => 'row.DateRemaining', 'colspan' => 1, 'align' => 'right', 'format' => 'number', 'style' => ['fontWeight' => 'bold']],
                                ['id' => 'd_sub_spacer3', 'type' => 'text', 'content' => '', 'colspan' => 2, 'align' => 'left'],
                            ],
                        ],
                        [
                            'id' => 'paid_debts_grand_total_row',
                            'enabledBy' => '',
                            'scope' => 'table',
                            'level' => 0,
                            'className' => 'report-grand-total-row',
                            'cells' => [
                                ['id' => 'g_sub_label', 'type' => 'text', 'content' => 'Tổng Cộng', 'colspan' => 5, 'align' => 'right', 'style' => ['fontWeight' => 'bold']],
                                ['id' => 'g_sub_debt', 'type' => 'binding', 'binding' => 'aggregate.totals.DebtAmount', 'colspan' => 1, 'align' => 'right', 'format' => 'number', 'style' => ['fontWeight' => 'bold']],
                                ['id' => 'g_sub_spacer1', 'type' => 'text', 'content' => '', 'colspan' => 5, 'align' => 'left'],
                                ['id' => 'g_sub_paid', 'type' => 'binding', 'binding' => 'aggregate.totals.PaidAmount', 'colspan' => 1, 'align' => 'right', 'format' => 'number', 'style' => ['fontWeight' => 'bold']],
                                ['id' => 'g_sub_spacer2', 'type' => 'text', 'content' => '-', 'colspan' => 1, 'align' => 'center'],
                                ['id' => 'g_sub_remain', 'type' => 'binding', 'binding' => 'aggregate.totals.RemainingAmount', 'colspan' => 1, 'align' => 'right', 'format' => 'number', 'style' => ['fontWeight' => 'bold']],
                                ['id' => 'g_sub_spacer3', 'type' => 'text', 'content' => '', 'colspan' => 2, 'align' => 'left'],
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'payment_method_summary_section',
                    'type' => 'text',
                    'content' => '<h2 class="payment-method-title" style="text-align: center; font-size: 13px; font-weight: bold; margin: 18px 0 6px 0;">BẢNG KÊ HÌNH THỨC THANH TOÁN</h2>',
                ],
                [
                    'id' => 'payment_method_summary_table',
                    'type' => 'table',
                    'dataSource' => 'payment_method_summary',
                    'tableType' => 'dynamic',
                    'tableStyle' => 'grid',
                    'tableClassName' => 'payment-method-summary-table',
                    'style' => ['width' => '50%', 'margin' => '0 auto', 'fontSize' => '10px'],
                    'columns' => [
                        ['header' => 'HTTT', 'value' => 'item.Method', 'width' => '40%', 'align' => 'center', 'style' => ['fontWeight' => '500']],
                        ['header' => 'Tổng Tiền', 'value' => 'item.Amount', 'width' => '60%', 'align' => 'right', 'format' => 'number'],
                    ],
                    'customRows' => [
                        [
                            'id' => 'pm_summary_total',
                            'enabledBy' => '',
                            'scope' => 'table',
                            'level' => 0,
                            'className' => 'pm-summary-total-row',
                            'cells' => [
                                ['id' => 'pm_total_label', 'type' => 'text', 'content' => 'Tổng:', 'align' => 'center', 'style' => ['color' => '#b91c1c', 'fontWeight' => 'bold']],
                                ['id' => 'pm_total_amount', 'type' => 'binding', 'binding' => 'aggregate.payment_method_summary.sum.Amount', 'align' => 'right', 'format' => 'number', 'style' => ['fontWeight' => 'bold']],
                            ],
                        ],
                    ],
                ],
            ],
            'footer' => [],
        ];
    }

    public function columns(): array
    {
        return [
            ['header' => 'Mã Đăng Ký', 'value' => 'row.BookingId', 'width' => '5%', 'align' => 'center'],
            ['header' => 'Phòng', 'value' => 'row.Room', 'width' => '4%', 'align' => 'center'],
            ['header' => 'Tên Đăng Ký', 'value' => 'row.BookingName', 'width' => '9%', 'align' => 'left'],
            ['header' => 'Ngày Đến', 'value' => 'row.ArrivalDate', 'width' => '6%', 'align' => 'center'],
            ['header' => 'Ngày Đi', 'value' => 'row.DepartureDate', 'width' => '6%', 'align' => 'center'],
            ['header' => 'Tổng Tiền', 'value' => 'row.AmountCN', 'width' => '8%', 'align' => 'right', 'format' => 'number'],
            ['header' => 'HTTT', 'value' => 'row.PaymentMethodCN', 'width' => '4%', 'align' => 'center'],
            ['header' => 'Ngày Công Nợ', 'value' => 'row.DateCN', 'width' => '6%', 'align' => 'center'],
            ['header' => 'Nhân Viên', 'value' => 'row.UserCN', 'width' => '7%', 'align' => 'left'],
            ['header' => 'Đã trả', 'value' => 'row.IsPaidMarker', 'width' => '4%', 'align' => 'center', 'style' => ['fontSize' => '11px']],
            ['header' => 'Ngày Thanh Toán', 'value' => 'row.DateTT', 'width' => '6%', 'align' => 'center'],
            ['header' => 'Tổng Tiền Thanh Toán', 'value' => 'row.AmountTT', 'width' => '8%', 'align' => 'right', 'format' => 'number'],
            ['header' => 'HTTT', 'value' => 'row.PaymentMethodTT', 'width' => '4%', 'align' => 'center'],
            ['header' => 'Còn Lại', 'value' => 'row.RemainAmount', 'width' => '6%', 'align' => 'right', 'format' => 'number'],
            ['header' => 'Nhân Viên', 'value' => 'row.UserTT', 'width' => '7%', 'align' => 'left'],
            ['header' => 'Ghi Chú', 'value' => 'row.Description', 'width' => '10%', 'align' => 'left'],
        ];
    }

    public function css(): string
    {
        return <<<'CSS'
.paid-company-debts-table {
    width: 100%;
    border-collapse: collapse;
    font-family: inherit;
    font-size: 9.5px;
    line-height: 1.25;
    margin-top: 4px;
}
.paid-company-debts-table th,
.paid-company-debts-table td {
    border: 1px solid #cbd5e1;
    padding: 3px 4px;
    vertical-align: middle;
}
.paid-company-debts-table thead tr:first-child th {
    font-size: 10px;
    font-weight: bold;
    padding: 4px;
}
.paid-company-debts-table thead tr:nth-child(2) th {
    background-color: #f8fafc;
    color: #0f172a;
    font-weight: bold;
    text-align: center;
}
.paid-company-debts-table .date-group-header td {
    background-color: #fff;
    border-top: 1px solid #94a3b8;
}
.paid-company-debts-table .company-group-header td {
    background-color: #fff;
}
.paid-company-debts-table .company-subtotal-row td,
.paid-company-debts-table .date-subtotal-row td,
.paid-company-debts-table .report-grand-total-row td {
    background-color: #fff;
    font-weight: bold;
    border-top: 1px solid #cbd5e1;
    border-bottom: 1px solid #cbd5e1;
}
.payment-method-summary-table {
    width: 50%;
    margin: 6px auto 0 auto;
    border-collapse: collapse;
    font-size: 10px;
}
.payment-method-summary-table th,
.payment-method-summary-table td {
    border: 1px solid #cbd5e1;
    padding: 4px 8px;
}
.payment-method-summary-table thead th {
    background-color: #f1f5f9;
    font-weight: bold;
    text-align: center;
}
.hotel-logo img {
    max-height: 52px;
    object-fit: contain;
}
CSS;
    }

    public function render(array $data, ?TemplateRendererService $renderer = null): string
    {
        $renderer ??= new TemplateRendererService();
        $definition = $this->definition();

        return $renderer->render(
            $definition['content_html'],
            $definition['css'],
            $data,
            array_intersect_key($definition, array_flip(['page_size', 'page_orientation', 'margin_top', 'margin_bottom', 'margin_left', 'margin_right']))
        );
    }

    private function compileDesignerBlocks(array $bands): string
    {
        $html = '';
        foreach (['header', 'detail', 'footer'] as $band) {
            if ($band === 'header') {
                $html .= '<div class="report-header-band">'."\n";
                foreach ($bands['header'] ?? [] as $block) {
                    $html .= $this->compileBlock($block);
                }
                $html .= '</div>'."\n";
                continue;
            }

            $html .= '<div class="report-'.($band === 'detail' ? 'detail' : $band).'-band">'."\n";
            foreach ($bands[$band] ?? [] as $block) {
                $html .= $this->compileBlock($block);
            }
            $html .= '</div>'."\n";
        }

        return $html;
    }

    private function compileBlock(array $block): string
    {
        $type = $block['type'] ?? 'text';
        if ($type === 'text' || $type === 'divider') {
            return ($block['content'] ?? '')."\n";
        }

        if ($type === 'columns') {
            $html = '<div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px;">'."\n";
            foreach ($block['columns'] ?? [] as $col) {
                $width = $col['width'] ?? 'auto';
                $html .= '  <div style="width: '.$width.';">'."\n";
                foreach ($col['blocks'] ?? [] as $b) {
                    $html .= '    '.$this->compileBlock($b);
                }
                $html .= '  </div>'."\n";
            }
            $html .= '</div>'."\n";
            return $html;
        }

        if ($type === 'table') {
            return $this->compileTable($block);
        }

        return '';
    }

    private function compileTable(array $block): string
    {
        $columns = $block['columns'] ?? [];
        $className = $block['tableClassName'] ?? 'report-table';
        $styleAttr = ! empty($block['style']['width']) ? ' style="width: '.$block['style']['width'].';"' : '';

        $html = '<table class="'.$className.'"'.$styleAttr.'>'."\n";
        $html .= '  <colgroup>'."\n";
        foreach ($columns as $col) {
            $html .= '    <col style="width: '.($col['width'] ?? 'auto').'">'."\n";
        }
        $html .= '  </colgroup>'."\n";

        $html .= '  <thead>'."\n";
        if (! empty($block['hasTwoTierHeader']) && ! empty($block['topHeader'])) {
            $html .= "    <tr>\n";
            foreach ($block['topHeader'] as $th) {
                $thStyle = 'text-align: '.($th['align'] ?? 'center').';';
                if (! empty($th['style']['backgroundColor'])) {
                    $thStyle .= ' background-color: '.$th['style']['backgroundColor'].';';
                }
                $html .= '      <th colspan="'.($th['colspan'] ?? 1).'" style="'.$thStyle.'">'.($th['label'] ?? '').'</th>'."\n";
            }
            $html .= "    </tr>\n";
        }

        $html .= "    <tr>\n";
        foreach ($columns as $col) {
            $html .= '      <th style="text-align: '.($col['align'] ?? 'center').';">'.($col['header'] ?? '').'</th>'."\n";
        }
        $html .= "    </tr>\n  </thead>\n";

        $groups = $block['grouping'] ?? [];
        $primaryGroup = $groups[0] ?? null;
        $secondaryGroup = $groups[1] ?? null;
        $dataSource = $block['dataSource'] ?? 'rows';
        $customRows = $block['customRows'] ?? [];

        if ($primaryGroup) {
            $groupByAttr = ' data-group-by="'.htmlspecialchars((string) $primaryGroup['field'], ENT_QUOTES, 'UTF-8').'"';
            $subGroupByAttr = $secondaryGroup ? ' data-subgroup-by="'.htmlspecialchars((string) $secondaryGroup['field'], ENT_QUOTES, 'UTF-8').'"' : '';

            $html .= '  <tbody class="pms-grouped-rows" data-source="'.$dataSource.'"'.$groupByAttr.$subGroupByAttr.'>'."\n";

            $html .= '    <tr class="pms-group-header" data-group-level="0" data-group-field="'.htmlspecialchars((string) $primaryGroup['field'], ENT_QUOTES, 'UTF-8').'">'."\n";
            if (! empty($primaryGroup['headerCells'])) {
                foreach ($primaryGroup['headerCells'] as $cell) {
                    $html .= '      <td colspan="'.($cell['colspan'] ?? count($columns)).'">'
                        .($cell['content'] ?? '').'</td>'."\n";
                }
            } else {
                $html .= '      <td colspan="'.count($columns).'">'.($primaryGroup['label'] ?? '').'</td>'."\n";
            }
            $html .= "    </tr>\n";

            if ($secondaryGroup) {
                $html .= '    <tr class="pms-subgroup-header" data-group-level="1" data-group-field="'.htmlspecialchars((string) $secondaryGroup['field'], ENT_QUOTES, 'UTF-8').'">'."\n";
                if (! empty($secondaryGroup['headerCells'])) {
                    foreach ($secondaryGroup['headerCells'] as $cell) {
                        $html .= '      <td colspan="'.($cell['colspan'] ?? count($columns)).'">'
                            .($cell['content'] ?? '').'</td>'."\n";
                    }
                } else {
                    $html .= '      <td colspan="'.count($columns).'">'.($secondaryGroup['label'] ?? '').'</td>'."\n";
                }
                $html .= "    </tr>\n";
            }

            $html .= "    <tr class=\"pms-detail-row\">\n";
            foreach ($columns as $col) {
                $format = ($col['format'] ?? '') === 'number' ? '|number' : '';
                $colStyle = 'text-align: '.($col['align'] ?? 'left').';';
                if (! empty($col['style']['fontSize'])) {
                    $colStyle .= ' font-size: '.$col['style']['fontSize'].';';
                }
                $html .= '      <td style="'.$colStyle.'">{{'.($col['value'] ?? '').$format.'}}</td>'."\n";
            }
            $html .= "    </tr>\n";

            $customRows = $block['customRows'] ?? [];
            $groupCustomRows = array_filter($customRows, fn ($r) => ($r['scope'] ?? 'table') === 'group');
            foreach ($groupCustomRows as $crow) {
                $levelAttr = isset($crow['level']) ? ' data-group-level="'.$crow['level'].'"' : '';
                $html .= '    <tr class="pms-group-footer '.($crow['className'] ?? '').'"'.$levelAttr.'>'."\n";
                foreach ($crow['cells'] ?? [] as $cell) {
                    $colspan = isset($cell['colspan']) ? ' colspan="'.$cell['colspan'].'"' : '';
                    $align = $cell['align'] ?? 'left';
                    $val = ! empty($cell['binding']) ? '{{'.$cell['binding'].(! empty($cell['format']) ? '|'.$cell['format'] : '').'}}' : ($cell['content'] ?? '');
                    $html .= '      <td'.$colspan.' style="text-align: '.$align.'; font-weight: bold;">'.$val.'</td>'."\n";
                }
                $html .= "    </tr>\n";
            }

            $html .= "  </tbody>\n";
        } else {
            $html .= "  <tbody>\n";
            $html .= '    <tr class="pms-detail-row" data-source="'.$dataSource.'">'."\n";
            foreach ($columns as $col) {
                $format = ($col['format'] ?? '') === 'number' ? '|number' : '';
                $colStyle = 'text-align: '.($col['align'] ?? 'left').';';
                if (! empty($col['style']['fontWeight'])) {
                    $colStyle .= ' font-weight: '.$col['style']['fontWeight'].';';
                }
                $html .= '      <td style="'.$colStyle.'">{{'.($col['value'] ?? '').$format.'}}</td>'."\n";
            }
            $html .= "    </tr>\n";
            $html .= "  </tbody>\n";
        }

        $tableCustomRows = array_filter($customRows, fn ($r) => ($r['scope'] ?? 'table') === 'table');
        if (! empty($tableCustomRows)) {
            $html .= "  <tfoot>\n";
            foreach ($tableCustomRows as $crow) {
                $html .= '    <tr class="'.($crow['className'] ?? '').'">'."\n";
                foreach ($crow['cells'] ?? [] as $cell) {
                    $colspan = isset($cell['colspan']) ? ' colspan="'.$cell['colspan'].'"' : '';
                    $align = $cell['align'] ?? 'left';
                    $val = ! empty($cell['binding']) ? '{{'.$cell['binding'].(! empty($cell['format']) ? '|'.$cell['format'] : '').'}}' : ($cell['content'] ?? '');
                    $html .= '      <td'.$colspan.' style="text-align: '.$align.'; font-weight: bold;">'.$val.'</td>'."\n";
                }
                $html .= "    </tr>\n";
            }
            $html .= "  </tfoot>\n";
        }

        $html .= "</table>\n";
        return $html;
    }
};
