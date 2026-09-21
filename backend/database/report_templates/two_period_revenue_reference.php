<?php

/**
 * Designer v1 reference template for legacy Army report sp_217.
 * The HTML is compiled from the Designer blocks below so persisted JSON/HTML stay aligned.
 */
return new class
{
    private const TABLE_ID = 'two_period_revenue_table';

    public function definition(): array
    {
        return [
            'code' => 'TWO_PERIOD_REVENUE',
            'name' => 'Báo cáo doanh thu hai giai đoạn',
            'report' => 'TWO_PERIOD_REVENUE_REFERENCE',
            'page_size' => 'A4',
            'page_orientation' => 'landscape',
            'margin_top' => 7,
            'margin_right' => 5,
            'margin_bottom' => 7,
            'margin_left' => 5,
            'version' => '1.0',
            'content_html' => $this->html(),
            'content_json' => $this->blocks(),
            'css' => $this->css(),
            'columns' => $this->columns(),
            'data_contract' => [
                'rows' => [
                    'STT' => 'number',
                    'Ma' => 'number',
                    'RegisterID2' => 'string',
                    'ArrivalDateVW' => 'string',
                    'DepartureDateVW' => 'string',
                    'Guest' => 'string',
                    'DateHDDV' => 'string',
                    'DescriptionServive' => 'string',
                    'OriginalRate' => 'number',
                    'ServiceChargeAmount' => 'number',
                    'SpecialTaxAmount' => 'number',
                    'TaxAmount' => 'number',
                    'Amount' => 'number',
                    'PaymentMethod' => 'string',
                    'Company' => 'string',
                    'Username' => 'string',
                    'OpenTime' => 'string',
                    'Outlet' => 'string',
                    'OutletName' => 'string',
                    'ServiceId' => 'string',
                    'FirstNameService' => 'string',
                    'DateHDBH' => 'string',
                    'DateHDBHLabel' => 'string',
                    'InvoiceId' => 'number',
                    'Room' => 'string',
                ],
                'parameters' => [
                    'p_from_date' => 'string',
                    'p_to_date' => 'string',
                    'p_department' => 'string',
                    'p_outlet' => 'string',
                    'p_services' => 'string',
                    'p_user' => 'string',
                    'p_order_by' => 'string',
                    'p_order_direction' => 'string',
                    'p_group_by_day' => 'boolean',
                    'p_show_registration' => 'boolean',
                    'p_show_dep_date' => 'boolean',
                ],
            ],
        ];
    }

    /**
     * Visible report columns, stored as Designer metadata and used to compile the table.
     */
    public function columns(): array
    {
        $headerStyle = [
            'backgroundColor' => '#dee2ed',
            'color' => '#1e293b',
            'fontWeight' => 'bold',
            'textAlign' => 'center',
            'border' => '1px solid #aeb5c0',
            'padding' => '4px 3px',
            'fontSize' => '8px',
            'whiteSpace' => 'normal',
        ];
        $textStyle = [
            'backgroundColor' => '#ffffff',
            'border' => '1px solid #aeb5c0',
            'padding' => '3px 3px',
            'fontSize' => '7.5px',
            'verticalAlign' => 'middle',
            'overflowWrap' => 'anywhere',
        ];
        $moneyStyle = array_merge($textStyle, ['textAlign' => 'right', 'whiteSpace' => 'nowrap']);

        $definitions = [
            ['STT', 'STT', '3%', 'center', null],
            ['Ma', 'Mã HĐ', '5%', 'center', 'number'],
            ['RegisterID2', 'Mã ĐK', '5%', 'center', null],
            ['ArrivalDateVW', 'Ngày đến', '5%', 'center', null],
            ['DepartureDateVW', 'Ngày đi', '5%', 'center', null],
            ['Guest', 'Tên khách', '8%', 'left', null],
            ['DateHDDV', 'Ngày dịch vụ', '5%', 'center', null],
            ['DescriptionServive', 'Mô tả', '11%', 'left', null],
            ['OriginalRate', 'Giá gốc', '7%', 'right', 'number'],
            ['ServiceChargeAmount', 'Phí dịch vụ', '7%', 'right', 'number'],
            ['SpecialTaxAmount', 'Thuế đặc biệt', '6%', 'right', 'number'],
            ['TaxAmount', 'Thuế', '5%', 'right', 'number'],
            ['Amount', 'Doanh thu', '6%', 'right', 'number'],
            ['PaymentMethod', 'Hình thức thanh toán', '5%', 'center', null],
            ['Company', 'Công ty', '8%', 'left', null],
            ['Username', 'Người dùng', '5%', 'center', null],
            ['OpenTime', 'Giờ', '4%', 'center', null],
        ];

        return array_map(static function (array $definition) use ($headerStyle, $textStyle, $moneyStyle): array {
            [$key, $header, $width, $align, $format] = $definition;

            return [
                'id' => 'two_period_revenue_column_'.$key,
                'header' => $header,
                'value' => 'row.'.$key,
                'width' => $width,
                'align' => $align,
                'format' => $format,
                'headerStyle' => $headerStyle,
                'cellStyle' => $align === 'right' ? $moneyStyle : array_merge($textStyle, ['textAlign' => $align]),
            ];
        }, $definitions);
    }

    public function blocks(): array
    {
        $groupHeaderStyle = [
            'border' => '1px solid #aeb5c0',
            'padding' => '4px 6px',
            'fontSize' => '8px',
            'fontWeight' => 'bold',
            'textAlign' => 'left',
            'backgroundColor' => '#ffffff',
        ];
        $subtotalStyle = [
            'backgroundColor' => '#dee2ed',
            'border' => '1px solid #aeb5c0',
            'padding' => '4px 3px',
            'fontSize' => '8px',
            'fontWeight' => 'bold',
        ];

        return [
            'header' => [
                [
                    'id' => 'two_period_revenue_header_band',
                    'type' => 'columns',
                    'style' => [
                        'display' => 'flex',
                        'justifyContent' => 'space-between',
                        'alignItems' => 'flex-start',
                        'marginTop' => '0px',
                        'marginBottom' => '4px',
                        'paddingTop' => '0px',
                        'paddingBottom' => '0px',
                    ],
                    'columns' => [
                        [
                            'id' => 'two_period_revenue_logo_column',
                            'width' => '30%',
                            'blocks' => [[
                                'id' => 'two_period_revenue_logo',
                                'type' => 'text',
                                'content' => '<div class="hotel-logo">{{hotel.logo}}</div>',
                                'style' => ['fontSize' => '10px'],
                            ]],
                        ],
                        [
                            'id' => 'two_period_revenue_hotel_column',
                            'width' => '70%',
                            'blocks' => [[
                                'id' => 'two_period_revenue_hotel_information',
                                'type' => 'text',
                                'content' => '<div class="hotel-information"><div><b>Địa chỉ:</b> {{hotel.address}}</div><div><b>Nhân viên:</b> {{report.generated_by}} &nbsp; <b>Ngày:</b> {{report.generated_at}}</div></div>',
                                'style' => ['textAlign' => 'right', 'fontSize' => '9px'],
                            ]],
                        ],
                    ],
                ],
                [
                    'id' => 'two_period_revenue_divider',
                    'type' => 'divider',
                    'content' => '<hr class="header-divider">',
                    'style' => ['marginTop' => '2px', 'marginBottom' => '8px'],
                ],
                [
                    'id' => 'two_period_revenue_title',
                    'type' => 'text',
                    'content' => '<h1 class="report-title">BÁO CÁO DOANH THU HAI GIAI ĐOẠN</h1>',
                    'style' => ['textAlign' => 'center', 'fontSize' => '16px', 'fontWeight' => 'bold'],
                ],
                [
                    'id' => 'two_period_revenue_period',
                    'type' => 'text',
                    'content' => '<p class="report-period"><b>Ngày:</b> {{parameters.p_from_date}} ~ {{parameters.p_to_date}}</p>',
                    'style' => ['textAlign' => 'center', 'fontSize' => '9px'],
                ],
            ],
            'detail' => [[
                'id' => self::TABLE_ID,
                'type' => 'table',
                'dataSource' => 'rows',
                'tableType' => 'dynamic',
                'tableStyle' => 'grid',
                'style' => ['width' => '100%', 'tableLayout' => 'fixed', 'borderCollapse' => 'collapse'],
                'columns' => $this->columns(),
                'grouping' => [
                    [
                        'id' => 'two_period_revenue_outlet_group',
                        'field' => 'Outlet',
                        'label' => 'Outlet: {{row.Outlet}} - {{row.OutletName}}',
                        'className' => 'outlet-group-header',
                        'sort' => 'ASC',
                        'headerCells' => [[
                            'id' => 'two_period_revenue_outlet_group_cell',
                            'type' => 'text',
                            'content' => 'Outlet: {{row.Outlet}} - {{row.OutletName}}',
                            'colspan' => 17,
                            'style' => array_merge($groupHeaderStyle, ['color' => '#167044']),
                        ]],
                    ],
                    [
                        'id' => 'two_period_revenue_service_group',
                        'field' => 'FirstNameService',
                        'label' => 'Dịch vụ: {{row.FirstNameService}}',
                        'className' => 'service-group-header',
                        'sort' => 'ASC',
                        'headerCells' => [[
                            'id' => 'two_period_revenue_service_group_cell',
                            'type' => 'text',
                            'content' => 'Dịch vụ: {{row.FirstNameService}}',
                            'colspan' => 17,
                            'style' => array_merge($groupHeaderStyle, ['color' => '#24823e', 'fontWeight' => 'normal']),
                        ]],
                    ],
                    [
                        'id' => 'two_period_revenue_invoice_date_group',
                        'field' => 'DateHDBH',
                        'label' => 'Ngày hóa đơn: {{row.DateHDBHLabel}}',
                        'className' => 'invoice-date-group-header',
                        'sort' => 'ASC',
                        'enabledBy' => 'parameters.p_group_by_day',
                        'headerCells' => [[
                            'id' => 'two_period_revenue_invoice_date_group_cell',
                            'type' => 'text',
                            'content' => 'Ngày hóa đơn: {{row.DateHDBHLabel}}',
                            'colspan' => 17,
                            'style' => array_merge($groupHeaderStyle, ['color' => '#334155', 'fontWeight' => 'normal']),
                        ]],
                    ],
                    [
                        'id' => 'two_period_revenue_registration_group',
                        'field' => 'RegisterID2',
                        'label' => 'Đăng ký: {{row.RegisterID2}}',
                        'className' => 'registration-group-header',
                        'sort' => 'ASC',
                        'enabledBy' => 'parameters.p_show_registration',
                        'headerCells' => [[
                            'id' => 'two_period_revenue_registration_group_cell',
                            'type' => 'text',
                            'content' => 'Đăng ký: {{row.RegisterID2}}',
                            'colspan' => 17,
                            'style' => array_merge($groupHeaderStyle, ['color' => '#1d4ed8', 'fontWeight' => 'normal']),
                        ]],
                    ],
                ],
                'customRows' => [
                    [
                        'id' => 'two_period_revenue_outlet_subtotal',
                        'scope' => 'group',
                        'level' => 0,
                        'className' => 'outlet-subtotal-row',
                        'cells' => [
                            ['id' => 'outlet_subtotal_label', 'type' => 'text', 'content' => 'Tổng:', 'colspan' => 8, 'align' => 'right', 'style' => array_merge($subtotalStyle, ['textAlign' => 'right'])],
                            ['id' => 'outlet_subtotal_original_rate', 'type' => 'binding', 'binding' => 'group.sum.OriginalRate', 'format' => 'number', 'colspan' => 1, 'align' => 'right', 'style' => $subtotalStyle],
                            ['id' => 'outlet_subtotal_service_charge', 'type' => 'binding', 'binding' => 'group.sum.ServiceChargeAmount', 'format' => 'number', 'colspan' => 1, 'align' => 'right', 'style' => $subtotalStyle],
                            ['id' => 'outlet_subtotal_special_tax', 'type' => 'binding', 'binding' => 'group.sum.SpecialTaxAmount', 'format' => 'number', 'colspan' => 1, 'align' => 'right', 'style' => $subtotalStyle],
                            ['id' => 'outlet_subtotal_tax', 'type' => 'binding', 'binding' => 'group.sum.TaxAmount', 'format' => 'number', 'colspan' => 1, 'align' => 'right', 'style' => $subtotalStyle],
                            ['id' => 'outlet_subtotal_amount', 'type' => 'binding', 'binding' => 'group.sum.Amount', 'format' => 'number', 'colspan' => 1, 'align' => 'right', 'style' => $subtotalStyle],
                            ['id' => 'outlet_subtotal_trailing_blank', 'type' => 'text', 'content' => '', 'colspan' => 4, 'style' => $subtotalStyle],
                        ],
                    ],
                    [
                        'id' => 'two_period_revenue_grand_total',
                        'scope' => 'table',
                        'className' => 'grand-total-row',
                        'cells' => [
                            ['id' => 'grand_total_label', 'type' => 'text', 'content' => 'Tổng cộng:', 'colspan' => 8, 'align' => 'right', 'style' => array_merge($subtotalStyle, ['textAlign' => 'right'])],
                            ['id' => 'grand_total_original_rate', 'type' => 'binding', 'binding' => 'aggregate.rows.sum.OriginalRate', 'format' => 'number', 'colspan' => 1, 'align' => 'right', 'style' => $subtotalStyle],
                            ['id' => 'grand_total_service_charge', 'type' => 'binding', 'binding' => 'aggregate.rows.sum.ServiceChargeAmount', 'format' => 'number', 'colspan' => 1, 'align' => 'right', 'style' => $subtotalStyle],
                            ['id' => 'grand_total_special_tax', 'type' => 'binding', 'binding' => 'aggregate.rows.sum.SpecialTaxAmount', 'format' => 'number', 'colspan' => 1, 'align' => 'right', 'style' => $subtotalStyle],
                            ['id' => 'grand_total_tax', 'type' => 'binding', 'binding' => 'aggregate.rows.sum.TaxAmount', 'format' => 'number', 'colspan' => 1, 'align' => 'right', 'style' => $subtotalStyle],
                            ['id' => 'grand_total_amount', 'type' => 'binding', 'binding' => 'aggregate.rows.sum.Amount', 'format' => 'number', 'colspan' => 1, 'align' => 'right', 'style' => $subtotalStyle],
                            ['id' => 'grand_total_trailing_blank', 'type' => 'text', 'content' => '', 'colspan' => 4, 'style' => $subtotalStyle],
                        ],
                    ],
                ],
            ]],
            'footer' => [],
        ];
    }

    public function html(): string
    {
        $blocks = $this->blocks();
        $html = '<div class="two-period-revenue-report">'.PHP_EOL;

        foreach ($blocks['header'] as $block) {
            if (($block['type'] ?? '') === 'columns') {
                $html .= $this->compileColumnsBlock($block);
            } elseif (($block['type'] ?? '') === 'divider') {
                $html .= $block['content'].PHP_EOL;
            } elseif (($block['type'] ?? '') === 'text') {
                $html .= '<div class="designer-text-block">'.$block['content'].'</div>'.PHP_EOL;
            }
        }

        foreach ($blocks['detail'] as $block) {
            if (($block['type'] ?? '') === 'table') {
                $html .= $this->compileTableBlock($block);
            }
        }

        foreach ($blocks['footer'] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $html .= '<div class="designer-footer-block">'.$block['content'].'</div>'.PHP_EOL;
            }
        }

        return $html.'</div>';
    }

    private function compileColumnsBlock(array $block): string
    {
        $style = $this->compileStyle($block['style'] ?? []);
        $html = '<div class="hotel-header-band" style="'.$style.'">'.PHP_EOL;

        foreach ($block['columns'] ?? [] as $column) {
            $columnStyle = $this->compileStyle(['width' => $column['width'] ?? '50%']);
            $html .= '<div class="hotel-header-column" style="'.$columnStyle.'">'.PHP_EOL;
            foreach ($column['blocks'] ?? [] as $nestedBlock) {
                if (($nestedBlock['type'] ?? '') !== 'text') {
                    continue;
                }
                $nestedStyle = $this->compileStyle($nestedBlock['style'] ?? []);
                $html .= '<div class="hotel-header-text" style="'.$nestedStyle.'">'.$nestedBlock['content'].'</div>'.PHP_EOL;
            }
            $html .= '</div>'.PHP_EOL;
        }

        return $html.'</div>'.PHP_EOL;
    }

    private function compileTableBlock(array $block): string
    {
        $columns = $block['columns'] ?? [];
        $grouping = $block['grouping'] ?? [];
        $tableStyle = $this->compileStyle($block['style'] ?? []);
        $html = '<table id="'.self::TABLE_ID.'" class="two-period-revenue-table" style="'.$tableStyle.'">'.PHP_EOL;
        $html .= '<colgroup>'.PHP_EOL;
        foreach ($columns as $column) {
            $html .= '<col style="width:'.htmlspecialchars((string) ($column['width'] ?? 'auto'), ENT_QUOTES, 'UTF-8').'">'.PHP_EOL;
        }
        $html .= '</colgroup><thead><tr>'.PHP_EOL;
        foreach ($columns as $column) {
            $style = $this->compileStyle($column['headerStyle'] ?? []);
            $html .= '<th style="'.$style.'">'.($column['header'] ?? '').'</th>'.PHP_EOL;
        }
        $html .= '</tr></thead>'.PHP_EOL;

        $firstGroupField = (string) ($grouping[0]['field'] ?? '');
        $html .= '<tbody class="pms-grouped-rows" data-source="'.htmlspecialchars((string) ($block['dataSource'] ?? 'rows'), ENT_QUOTES, 'UTF-8').'" data-group-configured="1" data-group-by="'.htmlspecialchars($firstGroupField, ENT_QUOTES, 'UTF-8').'">'.PHP_EOL;
        foreach ($grouping as $level => $group) {
            $enabledBy = ! empty($group['enabledBy'])
                ? ' data-group-enabled-by="'.htmlspecialchars((string) $group['enabledBy'], ENT_QUOTES, 'UTF-8').'"'
                : '';
            $sort = strtoupper((string) ($group['sort'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
            $html .= '<tr class="pms-group-header '.htmlspecialchars((string) ($group['className'] ?? ''), ENT_QUOTES, 'UTF-8').'" data-group-level="'.(int) $level.'" data-group-field="'.htmlspecialchars((string) $group['field'], ENT_QUOTES, 'UTF-8').'" data-group-sort="'.$sort.'"'.$enabledBy.'>';
            foreach ($group['headerCells'] ?? [] as $cell) {
                $style = $this->compileStyle($cell['style'] ?? []);
                $html .= '<td colspan="'.max(1, (int) ($cell['colspan'] ?? count($columns))).'" style="'.$style.'">'.($cell['content'] ?? '').'</td>';
            }
            $html .= '</tr>'.PHP_EOL;
        }

        $html .= '<tr class="pms-detail-row">'.PHP_EOL;
        foreach ($columns as $column) {
            $value = (string) ($column['value'] ?? '');
            $format = ($column['format'] ?? '') === 'number' ? '|number' : '';
            $style = $this->compileStyle($column['cellStyle'] ?? []);
            $html .= '<td style="'.$style.'">{{'.$value.$format.'}}</td>'.PHP_EOL;
        }
        $html .= '</tr>'.PHP_EOL;

        foreach ($block['customRows'] ?? [] as $row) {
            $scope = $row['scope'] ?? 'table';
            if ($scope === 'group') {
                $html .= $this->compileCustomRow($row, 'pms-group-custom-row');
            }
        }
        $html .= '</tbody>'.PHP_EOL;

        $tableRows = array_values(array_filter($block['customRows'] ?? [], static fn (array $row): bool => ($row['scope'] ?? 'table') === 'table'));
        if ($tableRows !== []) {
            $html .= '<tfoot>'.PHP_EOL;
            foreach ($tableRows as $row) {
                $html .= $this->compileCustomRow($row, 'pms-custom-row');
            }
            $html .= '</tfoot>'.PHP_EOL;
        }

        return $html.'</table>'.PHP_EOL;
    }

    private function compileCustomRow(array $row, string $class): string
    {
        $levelAttr = isset($row['level']) ? ' data-group-level="'.(int) $row['level'].'"' : '';
        $html = '<tr class="'.$class.' '.htmlspecialchars((string) ($row['className'] ?? ''), ENT_QUOTES, 'UTF-8').'"'.$levelAttr.'>';
        foreach ($row['cells'] ?? [] as $cell) {
            $content = (string) ($cell['content'] ?? '');
            if (($cell['type'] ?? '') === 'binding') {
                $content = '{{'.($cell['binding'] ?? '').(($cell['format'] ?? '') === 'number' ? '|number' : '').'}}';
            }
            $style = $this->compileStyle($cell['style'] ?? []);
            $html .= '<td colspan="'.max(1, (int) ($cell['colspan'] ?? 1)).'" style="'.$style.'">'.$content.'</td>';
        }
        return $html.'</tr>'.PHP_EOL;
    }

    private function compileStyle(array $style): string
    {
        $parts = [];
        foreach ($style as $name => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $property = strtolower((string) preg_replace('/([A-Z])/', '-$1', (string) $name));
            $parts[] = $property.': '.$value;
        }
        return implode('; ', $parts);
    }

    public function css(): string
    {
        return <<<'CSS'
.two-period-revenue-report { font-family: Arial, Helvetica, sans-serif; color: #111827; font-size: 8px; }
.hotel-header-band { display: flex; justify-content: space-between; align-items: flex-start; width: 100%; }
.hotel-header-column { box-sizing: border-box; }
.hotel-logo { min-height: 42px; display: flex; align-items: center; }
.hotel-logo img { max-width: 120px; max-height: 42px; object-fit: contain; }
.hotel-information { text-align: right; line-height: 1.5; color: #111827; }
.header-divider { border: 0; border-top: 1px solid #333333; margin: 2px 0 8px; }
.report-title { margin: 3px 0 2px; text-align: center; font-size: 16px; font-weight: 700; }
.report-period { margin: 2px 0 10px; text-align: center; font-size: 9px; }
.two-period-revenue-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
.two-period-revenue-table th,
.two-period-revenue-table td { box-sizing: border-box; vertical-align: middle; line-height: 1.15; overflow-wrap: anywhere; }
.two-period-revenue-table .pms-detail-row td { background: #ffffff; }
.two-period-revenue-table .outlet-subtotal-row td,
.two-period-revenue-table .grand-total-row td { background: #dee2ed; font-weight: 700; }
@media print {
  .two-period-revenue-table thead { display: table-header-group; }
  .two-period-revenue-table tr { break-inside: avoid; }
}
CSS;
    }
};
