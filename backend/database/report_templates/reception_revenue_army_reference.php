<?php

/**
 * Designer v1 reference template for legacy ProVistaArmyHotel sp_293.
 * The data contract retains every one of the 21 stored procedure aliases;
 * the printed detail table intentionally shows the 11 columns from the legacy UI.
 */
return new class
{
    public function definition(): array
    {
        $blocks = $this->blocks();

        return [
            'code' => 'RECEPTION_REVENUE_ARMY',
            'name' => 'Báo cáo doanh thu lễ tân Army',
            'report' => 'RECEPTION_REVENUE_ARMY_REFERENCE',
            'page_size' => 'A4',
            'page_orientation' => 'landscape',
            'margin_top' => 8,
            'margin_right' => 6,
            'margin_bottom' => 8,
            'margin_left' => 6,
            'version' => '1.0',
            'content_html' => $this->compileBlocks($blocks),
            'content_json' => $blocks,
            'css' => $this->css(),
            'columns' => $this->columns(),
            'data_contract' => [
                'rows' => [
                    'BookingId' => 'string',
                    'Date' => 'datetime',
                    'Room' => 'string',
                    'ArrivalDate' => 'date',
                    'DepartureDate' => 'date',
                    'GuestName' => 'string',
                    'DescriptionServive' => 'string',
                    'OriginalRate' => 'number',
                    'ServiceChargeAmount' => 'number',
                    'SpecialTaxAmount' => 'number',
                    'TaxAmount' => 'number',
                    'Amount' => 'number',
                    'PaymentMethod' => 'string',
                    'Company' => 'string',
                    'OpenTime' => 'string',
                    'Description' => 'string',
                    'DisplayName' => 'string',
                    'ServiceId' => 'string',
                    'FirstNameService' => 'string',
                    'Name' => 'string',
                    'NameVI' => 'string',
                ],
                'parameters' => [
                    'p_from_date' => 'date',
                    'p_to_date' => 'date',
                    'p_user' => 'string',
                    'p_service' => 'string',
                ],
            ],
        ];
    }

    public function columns(): array
    {
        $labels = [
            ['BookingId', 'Mã ĐK', '6%', 'center', 'text'],
            ['Room', 'Phòng', '5%', 'center', 'text'],
            ['ArrivalDate', 'Ngày Đến', '7%', 'center', 'text'],
            ['DepartureDate', 'Ngày Đi', '7%', 'center', 'text'],
            ['GuestName', 'Tên Khách', '14%', 'left', 'text'],
            ['DescriptionServive', 'Mô Tả', '18%', 'left', 'text'],
            ['Amount', 'Doanh Thu', '8%', 'right', 'number'],
            ['PaymentMethod', 'HTTT', '5%', 'center', 'text'],
            ['Company', 'Công Ty', '12%', 'left', 'text'],
            ['OpenTime', 'Giờ', '6%', 'center', 'text'],
            ['Description', 'Ghi Chú', '12%', 'left', 'text'],
        ];

        return array_map(static function (array $column): array {
            [$field, $header, $width, $align, $format] = $column;
            $cellStyle = ['padding' => '3px 4px', 'verticalAlign' => 'middle'];
            if ($field === 'BookingId') {
                $cellStyle += ['fontWeight' => 'bold', 'color' => '#2e7d32'];
            }

            return [
                'id' => strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $field)),
                'field' => $field,
                'header' => $header,
                'value' => 'row.'.$field,
                'width' => $width,
                'align' => $align,
                'format' => $format,
                'headerStyle' => [
                    'backgroundColor' => '#d9deea',
                    'fontWeight' => 'bold',
                    'textAlign' => 'center',
                    'padding' => '4px 5px',
                    'border' => '1px solid #aeb5c0',
                ],
                'cellStyle' => $cellStyle + [
                    'border' => '1px solid #aeb5c0',
                    'wordBreak' => 'break-word',
                ],
            ];
        }, $labels);
    }

    public function blocks(): array
    {
        $columns = $this->columns();
        $columnCount = count($columns);

        return [
            'header' => [
                [
                    'id' => 'reception_revenue_army_hotel_header',
                    'type' => 'columns',
                    'style' => ['display' => 'flex', 'justifyContent' => 'space-between', 'alignItems' => 'flex-start', 'marginBottom' => '4px'],
                    'columns' => [
                        [
                            'width' => '30%',
                            'blocks' => [[
                                'id' => 'reception_revenue_army_logo',
                                'type' => 'text',
                                'content' => '<div class="hotel-logo" style="min-height: 48px;">{{hotel.logo}}</div>',
                            ]],
                        ],
                        [
                            'width' => '70%',
                            'blocks' => [[
                                'id' => 'reception_revenue_army_hotel_information',
                                'type' => 'text',
                                'content' => '<div class="hotel-information" style="text-align: right; font-size: 10px; line-height: 1.45;">'
                                    .'<div><strong>{{hotel.name}}</strong></div>'
                                    .'<div>{{hotel.address}}</div>'
                                    .'<div><b>Nhân viên:</b> {{report.generated_by}} &nbsp;&nbsp; <b>Ngày in:</b> {{report.generated_at}}</div>'
                                    .'</div>',
                                'style' => ['textAlign' => 'right', 'fontSize' => '10px'],
                            ]],
                        ],
                    ],
                ],
                [
                    'id' => 'reception_revenue_army_divider',
                    'type' => 'divider',
                    'content' => '<hr class="header-divider" style="border: none; border-top: 1px solid #333; margin: 2px 0 7px 0;">',
                ],
                [
                    'id' => 'reception_revenue_army_title',
                    'type' => 'text',
                    'content' => '<h1 style="text-align: center; font-size: 17px; font-weight: bold; margin: 3px 0; text-transform: uppercase;">BÁO CÁO DOANH THU LỄ TÂN</h1>',
                    'style' => ['textAlign' => 'center', 'fontWeight' => 'bold', 'fontSize' => '17px'],
                ],
                [
                    'id' => 'reception_revenue_army_period',
                    'type' => 'text',
                    'content' => '<p style="text-align: center; font-size: 10px; margin: 2px 0 8px 0;"><b>Từ ngày:</b> {{parameters.p_from_date}} &nbsp; <b>Đến ngày:</b> {{parameters.p_to_date}}</p>',
                    'style' => ['textAlign' => 'center', 'fontSize' => '10px'],
                ],
            ],
            'detail' => [[
                'id' => 'reception_revenue_army_detail_table',
                'type' => 'table',
                'dataSource' => 'rows',
                'tableType' => 'dynamic',
                'tableStyle' => 'grid',
                'tableClassName' => 'reception-revenue-army-table',
                'style' => [
                    'width' => '100%',
                    'fontSize' => '9px',
                    'borderCollapse' => 'collapse',
                    'borderColor' => '#aeb5c0',
                    'borderWidth' => '1px',
                    'borderStyle' => 'solid',
                    'backgroundColor' => '#ffffff',
                ],
                'grouping' => [
                    [
                        'id' => 'reception_revenue_army_revenue_group',
                        'field' => 'DisplayName',
                        'label' => '{{row.NameVI}}',
                        'sort' => 'ASC',
                        'headerCells' => [[
                            'id' => 'reception_revenue_army_revenue_group_cell',
                            'type' => 'text',
                            'content' => '<strong>Nhóm doanh thu: {{row.NameVI}}</strong>',
                            'colspan' => $columnCount,
                            'align' => 'left',
                            'style' => ['backgroundColor' => '#f1f5f9', 'fontWeight' => 'bold', 'padding' => '4px 6px'],
                        ]],
                    ],
                    [
                        'id' => 'reception_revenue_army_service_group',
                        'field' => 'ServiceId',
                        'label' => '{{row.ServiceId}} - {{row.FirstNameService}}',
                        'sort' => 'ASC',
                        'headerCells' => [[
                            'id' => 'reception_revenue_army_service_group_cell',
                            'type' => 'text',
                            'className' => 'reception_revenue_army_service_group_cell',
                            'content' => '<span style="font-weight: bold;">Dịch vụ: {{row.ServiceId}} - {{row.FirstNameService}}</span>',
                            'colspan' => $columnCount,
                            'align' => 'left',
                            'style' => ['backgroundColor' => '#ffffff', 'padding' => '3px 8px'],
                        ]],
                    ],
                ],
                'columns' => $columns,
                'customRows' => [
                    [
                        'id' => 'reception_revenue_army_service_subtotal',
                        'scope' => 'group',
                        'level' => 1,
                        'className' => 'service-subtotal-row',
                        'cells' => [
                            ['id' => 'service_total_label', 'type' => 'text', 'content' => 'Tổng:', 'colspan' => 6, 'align' => 'right', 'style' => ['fontWeight' => 'bold']],
                            ['id' => 'service_total_amount', 'type' => 'binding', 'binding' => 'group.sum.Amount', 'colspan' => 1, 'align' => 'right', 'format' => 'number', 'style' => ['fontWeight' => 'bold']],
                            ['id' => 'service_total_spacer', 'type' => 'text', 'content' => '', 'colspan' => 4],
                        ],
                    ],
                    [
                        'id' => 'reception_revenue_army_category_subtotal',
                        'scope' => 'group',
                        'level' => 0,
                        'className' => 'revenue-group-subtotal-row',
                        'cells' => [
                            ['id' => 'category_total_label', 'type' => 'text', 'content' => 'Tổng {{row.NameVI}}:', 'colspan' => 6, 'align' => 'right', 'style' => ['fontWeight' => 'bold']],
                            ['id' => 'category_total_amount', 'type' => 'binding', 'binding' => 'group.sum.Amount', 'colspan' => 1, 'align' => 'right', 'format' => 'number', 'style' => ['fontWeight' => 'bold']],
                            ['id' => 'category_total_spacer', 'type' => 'text', 'content' => '', 'colspan' => 4],
                        ],
                    ],
                    [
                        'id' => 'reception_revenue_army_grand_total',
                        'scope' => 'table',
                        'className' => 'report-grand-total-row',
                        'cells' => [
                            ['id' => 'grand_total_label', 'type' => 'text', 'content' => 'Tổng cộng:', 'colspan' => 6, 'align' => 'right', 'style' => ['fontWeight' => 'bold']],
                            ['id' => 'grand_total_amount', 'type' => 'binding', 'binding' => 'aggregate.rows.sum.Amount', 'colspan' => 1, 'align' => 'right', 'format' => 'number', 'style' => ['fontWeight' => 'bold']],
                            ['id' => 'grand_total_spacer', 'type' => 'text', 'content' => '', 'colspan' => 4],
                        ],
                    ],
                ],
            ]],
            'footer' => [
                [
                    'id' => 'reception_revenue_army_summary_table',
                    'type' => 'table',
                    'dataSource' => 'revenue_summary',
                    'tableType' => 'dynamic',
                    'tableStyle' => 'grid',
                    'tableClassName' => 'reception-revenue-army-summary-table',
                    'style' => [
                        'width' => '35%',
                        'margin' => '12px auto 0',
                        'fontSize' => '9px',
                        'borderCollapse' => 'collapse',
                        'borderColor' => '#aeb5c0',
                        'borderWidth' => '1px',
                        'borderStyle' => 'solid',
                        'backgroundColor' => '#ffffff',
                    ],
                    'columns' => [
                        [
                            'id' => 'reception_revenue_army_summary_group',
                            'field' => 'GroupName',
                            'header' => 'Nhóm doanh thu',
                            'value' => 'row.GroupName',
                            'width' => '70%',
                            'align' => 'center',
                            'format' => 'text',
                            'headerStyle' => ['backgroundColor' => '#d9deea', 'fontWeight' => 'bold', 'textAlign' => 'center', 'padding' => '4px 5px', 'border' => '1px solid #aeb5c0'],
                            'cellStyle' => ['textAlign' => 'center', 'padding' => '3px 4px', 'border' => '1px solid #aeb5c0'],
                        ],
                        [
                            'id' => 'reception_revenue_army_summary_amount',
                            'field' => 'TotalAmount',
                            'header' => 'Tổng',
                            'value' => 'row.TotalAmount',
                            'width' => '30%',
                            'align' => 'center',
                            'format' => 'number',
                            'headerStyle' => ['backgroundColor' => '#d9deea', 'fontWeight' => 'bold', 'textAlign' => 'center', 'padding' => '4px 5px', 'border' => '1px solid #aeb5c0'],
                            'cellStyle' => ['textAlign' => 'center', 'padding' => '3px 4px', 'border' => '1px solid #aeb5c0'],
                        ],
                    ],
                    'customRows' => [[
                        'id' => 'reception_revenue_army_summary_total',
                        'scope' => 'table',
                        'className' => 'summary-grand-total-row',
                        'cells' => [
                            ['id' => 'summary_total_label', 'type' => 'text', 'content' => 'Tổng', 'align' => 'center', 'style' => ['fontWeight' => 'bold']],
                            ['id' => 'summary_total_amount', 'type' => 'binding', 'binding' => 'aggregate.revenue_summary.sum.TotalAmount', 'align' => 'center', 'format' => 'number', 'style' => ['fontWeight' => 'bold']],
                        ],
                    ]],
                ],
                [
                    'id' => 'reception_revenue_army_signatures',
                    'type' => 'columns',
                    'style' => ['display' => 'flex', 'justifyContent' => 'space-between', 'marginTop' => '18px', 'pageBreakInside' => 'avoid'],
                    'columns' => array_map(static function (string $label, string $note, int $index): array {
                        return [
                            'width' => '19%',
                            'blocks' => [[
                                'id' => 'reception_revenue_army_signature_'.$index,
                                'type' => 'text',
                                'content' => '<div style="text-align: center; font-size: 9px;"><strong>'.$label.'</strong><div style="font-size: 8px; font-style: italic; margin-top: 2px;">'.$note.'</div><div style="height: 45px;"></div></div>',
                                'style' => ['textAlign' => 'center', 'fontSize' => '9px'],
                            ]],
                        ];
                    }, ['NGƯỜI LẬP BIỂU', 'THỦ QUỸ', 'KẾ TOÁN TRƯỞNG', 'KT DOANH THU', 'GIÁM ĐỐC'], ['(Ký, họ tên)', '(Ký, họ tên)', '(Ký, họ tên)', '(Ký, họ tên)', '(Ký, họ tên, đóng dấu)'], range(1, 5)),
                ],
            ],
        ];
    }

    public function css(): string
    {
        return <<<'CSS'
.reception-revenue-army-table {
    width: 100%;
    table-layout: fixed;
    border-collapse: collapse;
    color: #111111;
    font-family: 'Segoe UI', Arial, sans-serif;
    font-size: 9px;
    line-height: 1.25;
}
.reception-revenue-army-table th,
.reception-revenue-army-table td {
    border: 1px solid #aeb5c0;
    padding: 3px 4px;
    vertical-align: middle;
    overflow-wrap: anywhere;
}
.reception-revenue-army-table thead th {
    background: #d9deea;
    font-weight: bold;
    text-align: center;
    padding: 4px 5px;
}
.reception-revenue-army-table .pms-group-header td {
    background: #f1f5f9;
}
.reception-revenue-army-table .reception_revenue_army_service_group_cell {
    background: #ffffff;
}
.reception-revenue-army-table .service-subtotal-row td {
    background: #fafafa;
    border-top: 1px dashed #cbd5e1;
}
.reception-revenue-army-table .revenue-group-subtotal-row td {
    background: #f8fafc;
    border-top: 1px solid #94a3b8;
}
.reception-revenue-army-table .report-grand-total-row td {
    background: #dee2ed;
    border-top: 2px solid #000000;
    font-weight: bold;
}
.reception-revenue-army-summary-table {
    width: 35%;
    table-layout: fixed;
    border-collapse: collapse;
    color: #111111;
    font-family: 'Segoe UI', Arial, sans-serif;
    font-size: 9px;
    line-height: 1.25;
}
.reception-revenue-army-summary-table th,
.reception-revenue-army-summary-table td {
    border: 1px solid #aeb5c0;
    padding: 3px 4px;
    vertical-align: middle;
}
.reception-revenue-army-summary-table thead th,
.reception-revenue-army-summary-table .summary-grand-total-row td {
    background: #dee2ed;
    font-weight: bold;
    text-align: center;
}
CSS;
    }

    private function compileBlocks(array $bands): string
    {
        $html = '';
        foreach (['header', 'detail', 'footer'] as $band) {
            $html .= '<div class="report-'.$band.'-band">' . "\n";
            foreach ($bands[$band] ?? [] as $block) {
                $html .= $this->compileBlock($block);
            }
            $html .= "</div>\n";
        }

        return $html;
    }

    private function compileBlock(array $block): string
    {
        if (($block['type'] ?? '') === 'text' || ($block['type'] ?? '') === 'divider') {
            return ($block['content'] ?? '')."\n";
        }

        if (($block['type'] ?? '') === 'columns') {
            $html = '<div style="'.htmlspecialchars($this->inlineStyle($block['style'] ?? []), ENT_QUOTES, 'UTF-8').'">' . "\n";
            foreach ($block['columns'] ?? [] as $column) {
                $html .= '<div style="width: '.htmlspecialchars((string) ($column['width'] ?? 'auto'), ENT_QUOTES, 'UTF-8').';">' . "\n";
                foreach ($column['blocks'] ?? [] as $child) {
                    $html .= $this->compileBlock($child);
                }
                $html .= "</div>\n";
            }
            $html .= "</div>\n";
            return $html;
        }

        return ($block['type'] ?? '') === 'table' ? $this->compileTable($block) : '';
    }

    private function compileTable(array $block): string
    {
        $columns = $block['columns'] ?? [];
        $groups = $block['grouping'] ?? [];
        $customRows = $block['customRows'] ?? [];
        $class = htmlspecialchars((string) ($block['tableClassName'] ?? 'report-table'), ENT_QUOTES, 'UTF-8');
        $html = '<table class="'.$class.'" style="'.htmlspecialchars($this->inlineStyle($block['style'] ?? []), ENT_QUOTES, 'UTF-8').'">' . "\n<colgroup>\n";
        foreach ($columns as $column) {
            $html .= '<col style="width: '.htmlspecialchars((string) ($column['width'] ?? 'auto'), ENT_QUOTES, 'UTF-8').'">' . "\n";
        }
        $html .= "</colgroup>\n<thead><tr>\n";
        foreach ($columns as $column) {
            $header = htmlspecialchars((string) ($column['header'] ?? ''), ENT_QUOTES, 'UTF-8');
            $style = $this->inlineStyle($column['headerStyle'] ?? []);
            $html .= '<th style="'.htmlspecialchars($style, ENT_QUOTES, 'UTF-8').'">'.$header.'</th>' . "\n";
        }
        $html .= "</tr></thead>\n";

        $source = htmlspecialchars((string) ($block['dataSource'] ?? 'rows'), ENT_QUOTES, 'UTF-8');
        $groupField = htmlspecialchars((string) ($groups[0]['field'] ?? ''), ENT_QUOTES, 'UTF-8');
        $html .= '<tbody class="pms-grouped-rows" data-source="'.$source.'" data-group-configured="1" data-group-by="'.$groupField.'">' . "\n";
        foreach ($groups as $level => $group) {
            $field = htmlspecialchars((string) ($group['field'] ?? ''), ENT_QUOTES, 'UTF-8');
            $sort = strtoupper((string) ($group['sort'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
            $html .= '<tr class="pms-group-header" data-group-level="'.$level.'" data-group-field="'.$field.'" data-group-sort="'.$sort.'">' . "\n";
            foreach ($group['headerCells'] ?? [] as $cell) {
                $span = max(1, (int) ($cell['colspan'] ?? 1));
                $cellClass = ! empty($cell['className']) ? ' class="'.htmlspecialchars((string) $cell['className'], ENT_QUOTES, 'UTF-8').'"' : '';
                $style = htmlspecialchars($this->inlineStyle($cell['style'] ?? []), ENT_QUOTES, 'UTF-8');
                $html .= '<td colspan="'.$span.'"'.$cellClass.' style="'.$style.'">'.($cell['content'] ?? '').'</td>' . "\n";
            }
            $html .= "</tr>\n";
        }

        $html .= "<tr class=\"pms-detail-row\">\n";
        foreach ($columns as $column) {
            $value = (string) ($column['value'] ?? '');
            if (($column['format'] ?? '') === 'number') {
                $value .= '|number';
            }
            $cellStyle = htmlspecialchars($this->inlineStyle($column['cellStyle'] ?? []), ENT_QUOTES, 'UTF-8');
            $html .= '<td style="'.$cellStyle.'">{{'.$value.'}}</td>' . "\n";
        }
        $html .= "</tr>\n";

        foreach ($customRows as $row) {
            if (($row['scope'] ?? 'table') !== 'group') {
                continue;
            }
            $level = max(0, (int) ($row['level'] ?? 0));
            $rowClass = htmlspecialchars('pms-group-custom-row pms-group-footer '.($row['className'] ?? ''), ENT_QUOTES, 'UTF-8');
            $html .= '<tr class="'.$rowClass.'" data-group-level="'.$level.'">' . "\n";
            foreach ($row['cells'] ?? [] as $cell) {
                $span = max(1, (int) ($cell['colspan'] ?? 1));
                $align = htmlspecialchars((string) ($cell['align'] ?? 'left'), ENT_QUOTES, 'UTF-8');
                $style = htmlspecialchars($this->inlineStyle($cell['style'] ?? []), ENT_QUOTES, 'UTF-8');
                $content = ! empty($cell['binding'])
                    ? '{{'.$cell['binding'].(! empty($cell['format']) ? '|'.$cell['format'] : '').'}}'
                    : ($cell['content'] ?? '');
                $html .= '<td colspan="'.$span.'" style="text-align: '.$align.';'.$style.'">'.$content.'</td>' . "\n";
            }
            $html .= "</tr>\n";
        }
        $html .= "</tbody>\n";

        $tableRows = array_filter($customRows, static fn (array $row): bool => ($row['scope'] ?? 'table') === 'table');
        if ($tableRows !== []) {
            $html .= "<tfoot>\n";
            foreach ($tableRows as $row) {
                $rowClass = htmlspecialchars((string) ($row['className'] ?? ''), ENT_QUOTES, 'UTF-8');
                $html .= '<tr class="'.$rowClass.'">' . "\n";
                foreach ($row['cells'] ?? [] as $cell) {
                    $span = max(1, (int) ($cell['colspan'] ?? 1));
                    $align = htmlspecialchars((string) ($cell['align'] ?? 'left'), ENT_QUOTES, 'UTF-8');
                    $style = htmlspecialchars($this->inlineStyle($cell['style'] ?? []), ENT_QUOTES, 'UTF-8');
                    $content = ! empty($cell['binding'])
                        ? '{{'.$cell['binding'].(! empty($cell['format']) ? '|'.$cell['format'] : '').'}}'
                        : ($cell['content'] ?? '');
                    $html .= '<td colspan="'.$span.'" style="text-align: '.$align.';'.$style.'">'.$content.'</td>' . "\n";
                }
                $html .= "</tr>\n";
            }
            $html .= "</tfoot>\n";
        }

        return $html."</table>\n";
    }

    private function inlineStyle(array $style): string
    {
        $declarations = [];
        foreach ($style as $property => $value) {
            if (! is_scalar($value) || (string) $value === '') {
                continue;
            }
            $property = strtolower((string) preg_replace('/([a-z])([A-Z])/', '$1-$2', (string) $property));
            $declarations[] = $property.': '.(string) $value;
        }

        return implode('; ', $declarations);
    }
};
