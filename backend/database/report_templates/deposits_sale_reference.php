<?php

return new class
{
    public function definition(): array
    {
        return [
            'page_size' => 'A4', 'page_orientation' => 'portrait',
            'margin_top' => 8, 'margin_right' => 6, 'margin_bottom' => 8, 'margin_left' => 6,
            'content_html' => $this->html(), 'content_json' => $this->blocks(), 'css' => $this->css(),
        ];
    }

    private function html(): string
    {
        return $this->compileDesignerBlocks($this->blocks());
    }

    private function blocks(): array
    {
        $blocks = [
            'header' => [
                [
                    'id' => 'deposits_sale_hotel_header',
                    'type' => 'columns',
                    'style' => [
                        'textAlign' => 'left', 'fontSize' => '13px',
                        'paddingTop' => '0px', 'paddingBottom' => '0px', 'paddingLeft' => '0px', 'paddingRight' => '0px',
                        'marginTop' => '0px', 'marginBottom' => '6px', 'color' => '#111111', 'fontWeight' => 'normal',
                    ],
                    'columns' => [
                        [
                            'width' => '30%',
                            'blocks' => [[
                                'id' => 'deposits_sale_hotel_logo',
                                'type' => 'text',
                                'content' => '<div class="hotel-logo" style="min-height: 58px;">{{hotel.logo}}</div>',
                                'style' => [
                                    'textAlign' => 'left', 'fontSize' => '14px', 'marginLeft' => '20px',
                                    'paddingTop' => '0px', 'paddingBottom' => '0px', 'paddingLeft' => '0px', 'paddingRight' => '0px',
                                    'marginTop' => '0px', 'marginBottom' => '0px', 'color' => '#111111', 'fontWeight' => 'normal',
                                ],
                            ]],
                        ],
                        [
                            'width' => '70%',
                            'blocks' => [[
                                'id' => 'deposits_sale_hotel_information',
                                'type' => 'text',
                                'content' => '<div class="hotel-information"><div><b>Địa chỉ:</b> {{hotel.address}}</div><div><b>Nhân viên:</b> {{report.generated_by}} &nbsp;&nbsp;&nbsp;&nbsp; <b>Ngày:</b> {{report.generated_at}}</div></div>',
                                'style' => [
                                    'textAlign' => 'right', 'fontSize' => '9px',
                                    'paddingTop' => '0px', 'paddingBottom' => '0px', 'paddingLeft' => '0px', 'paddingRight' => '0px',
                                    'marginTop' => '0px', 'marginBottom' => '0px', 'color' => '#111111', 'fontWeight' => 'normal',
                                ],
                            ]],
                        ],
                    ],
                ],
                [
                    'id' => 'deposits_sale_divider', 'type' => 'divider', 'content' => '<hr class="header-divider">',
                    'style' => [
                        'height' => '16px', 'minHeight' => '16px',
                        'paddingTop' => '0px', 'paddingBottom' => '0px', 'paddingLeft' => '8px', 'paddingRight' => '8px',
                        'marginTop' => '0px', 'marginBottom' => '9px',
                    ],
                ],
                [
                    'id' => 'deposits_sale_title', 'type' => 'text', 'content' => 'BÁO CÁO TIỀN ĐẶT CỌC',
                    'style' => [
                        'textAlign' => 'center', 'fontFamily' => 'Arial, Helvetica, sans-serif', 'fontSize' => '18px',
                        'color' => '#111111', 'paddingTop' => '0px', 'paddingBottom' => '0px', 'paddingLeft' => '0px', 'paddingRight' => '0px',
                        'marginTop' => '0px', 'marginBottom' => '0px', 'fontWeight' => 'bold',
                    ],
                ],
                [
                    'id' => 'deposits_sale_period', 'type' => 'text',
                    'content' => 'Ngày: {{parameters.p_from_date}}  ~  {{parameters.p_to_date}}',
                    'style' => [
                        'textAlign' => 'center', 'fontFamily' => 'Arial, Helvetica, sans-serif', 'fontSize' => '10px',
                        'color' => '#111111', 'paddingTop' => '0px', 'paddingBottom' => '0px', 'paddingLeft' => '0px', 'paddingRight' => '0px',
                        'marginTop' => '30px', 'marginBottom' => '13px', 'fontWeight' => 'normal',
                    ],
                ],
            ],
            'detail' => [
                [
                    'id' => 'deposits_sale_table', 'type' => 'table', 'dataSource' => 'rows', 'tableType' => 'dynamic',
                    'tableStyle' => 'grid',
                    'style' => [
                        'fontSize' => '10px', 'fontFamily' => 'Arial, Helvetica, sans-serif', 'color' => '#111111',
                        'paddingTop' => '0px', 'paddingBottom' => '0px', 'paddingLeft' => '0px', 'paddingRight' => '0px',
                        'marginTop' => '0px', 'marginBottom' => '0px', 'fontWeight' => 'normal', 'width' => '100%',
                    ],
                    'groups' => [[
                        'id' => 'deposits_sale_type_group', 'field' => 'GroupHeader', 'label' => '{{row.GroupHeader}}',
                        'className' => '', 'enabledBy' => '', 'sort' => 'ASC',
                        'headerCells' => [[
                            'id' => 'deposits_sale_type_group_header', 'type' => 'text', 'content' => '{{row.GroupHeader}}',
                            'colspan' => 11, 'align' => 'left', 'backgroundColor' => '#ffffff', 'color' => '#111111',
                            'fontSize' => '10px', 'fontWeight' => 'bold', 'borderColor' => '#ffffff',
                        ]],
                    ]],
                    'columns' => $this->columns(),
                    'customRows' => [
                        [
                            'id' => 'deposits_sale_company_total', 'enabledBy' => '', 'scope' => 'group', 'level' => 0,
                            'className' => 'pms-group-footer',
                            'cells' => [
                                ['id' => 'company_total_label', 'type' => 'text', 'content' => 'Tổng Theo C.ty', 'colspan' => 8, 'align' => 'right'],
                                ['id' => 'company_total_value', 'type' => 'binding', 'binding' => 'group.sum.Amount', 'colspan' => 1, 'align' => 'right', 'format' => 'number'],
                                ['id' => 'company_total_spacer', 'type' => 'text', 'content' => '', 'colspan' => 2, 'align' => 'left'],
                            ],
                        ],
                        [
                            'id' => 'deposits_sale_deposit_total', 'enabledBy' => '', 'scope' => 'table', 'level' => 0,
                            'className' => 'deposit-total-row',
                            'cells' => [
                                ['id' => 'deposit_total_label', 'type' => 'text', 'content' => 'Tổng Tiền Đặt Cọc', 'colspan' => 8, 'align' => 'right'],
                                ['id' => 'deposit_total_value', 'type' => 'binding', 'binding' => 'aggregate.rows.sum.DepositAmount', 'colspan' => 1, 'align' => 'right', 'format' => 'number'],
                                ['id' => 'deposit_total_spacer', 'type' => 'text', 'content' => '', 'colspan' => 2, 'align' => 'left'],
                            ],
                        ],
                        [
                            'id' => 'deposits_sale_total', 'enabledBy' => '', 'scope' => 'table', 'level' => 0,
                            'className' => 'report-total-row',
                            'cells' => [
                                ['id' => 'report_total_label', 'type' => 'text', 'content' => 'Tổng', 'colspan' => 8, 'align' => 'right'],
                                ['id' => 'report_total_value', 'type' => 'binding', 'binding' => 'aggregate.rows.sum.Amount', 'colspan' => 1, 'align' => 'right', 'format' => 'number'],
                                ['id' => 'report_total_spacer', 'type' => 'text', 'content' => '', 'colspan' => 2, 'align' => 'left'],
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'deposits_sale_allocation_title', 'type' => 'text', 'content' => 'Bảng Phân Bổ Tiền Tệ',
                    'style' => [
                        'textAlign' => 'center', 'fontFamily' => 'Arial, Helvetica, sans-serif', 'fontSize' => '18px',
                        'color' => '#111111', 'paddingTop' => '0px', 'paddingBottom' => '0px', 'paddingLeft' => '0px', 'paddingRight' => '0px',
                        'marginTop' => '28px', 'marginBottom' => '14px', 'fontWeight' => 'bold',
                    ],
                ],
                [
                    'id' => 'deposits_sale_allocation', 'type' => 'table', 'dataSource' => 'currency_allocations',
                    'tableType' => 'dynamic', 'tableStyle' => 'grid',
                    'style' => [
                        'fontSize' => '10px', 'fontFamily' => 'Arial, Helvetica, sans-serif', 'color' => '#111111',
                        'paddingTop' => '0px', 'paddingBottom' => '0px', 'paddingLeft' => '0px', 'paddingRight' => '0px',
                        'marginTop' => '0px', 'marginBottom' => '0px', 'marginLeft' => 'auto', 'marginRight' => 'auto',
                        'fontWeight' => 'normal', 'width' => '84%',
                    ],
                    'columns' => [
                        ['header' => 'HTTT', 'value' => 'item.PaymentMethodName', 'width' => '18%', 'align' => 'center'],
                        ['header' => 'Mã Thu Ngân', 'value' => 'item.CashierCode', 'width' => '14%', 'align' => 'center'],
                        ['header' => 'Thu Ngân', 'value' => 'item.CashAmount', 'width' => '12%', 'align' => 'right', 'format' => 'number'],
                        ['header' => 'Đặt Cọc', 'value' => 'item.DepositAmount', 'width' => '12%', 'align' => 'right', 'format' => 'number'],
                        ['header' => 'Thu Ngân + Đặt Cọc', 'value' => 'item.PositiveAmount', 'width' => '18%', 'align' => 'right', 'format' => 'number'],
                        ['header' => 'Hoàn Tiền', 'value' => 'item.RefundAmount', 'width' => '13%', 'align' => 'right', 'format' => 'number'],
                        ['header' => 'Tổng', 'value' => 'item.Amount', 'width' => '13%', 'align' => 'right', 'format' => 'number'],
                    ],
                    'customRows' => [[
                        'id' => 'deposits_sale_allocation_total', 'scope' => 'table', 'className' => 'allocation-total',
                        'cells' => [
                            ['id' => 'allocation_total_label', 'type' => 'text', 'content' => 'Tổng', 'colspan' => 2, 'align' => 'left'],
                            ['id' => 'allocation_cash_total', 'type' => 'binding', 'binding' => 'aggregate.currency_allocations.sum.CashAmount', 'align' => 'right', 'format' => 'number'],
                            ['id' => 'allocation_deposit_total', 'type' => 'binding', 'binding' => 'aggregate.currency_allocations.sum.DepositAmount', 'align' => 'right', 'format' => 'number'],
                            ['id' => 'allocation_positive_total', 'type' => 'binding', 'binding' => 'aggregate.currency_allocations.sum.PositiveAmount', 'align' => 'right', 'format' => 'number'],
                            ['id' => 'allocation_refund_total', 'type' => 'binding', 'binding' => 'aggregate.currency_allocations.sum.RefundAmount', 'align' => 'right', 'format' => 'number'],
                            ['id' => 'allocation_grand_total', 'type' => 'binding', 'binding' => 'aggregate.currency_allocations.sum.Amount', 'align' => 'right', 'format' => 'number'],
                        ],
                    ]],
                ],
            ],
            'footer' => [[
                'id' => 'deposits_sale_signatures', 'type' => 'static-table', 'tableStyle' => 'none',
                'style' => [
                    'fontSize' => '10px', 'fontFamily' => 'Arial, Helvetica, sans-serif', 'color' => '#111111',
                    'paddingTop' => '0px', 'paddingBottom' => '0px', 'paddingLeft' => '0px', 'paddingRight' => '0px',
                    'marginTop' => '18px', 'marginBottom' => '0px', 'marginLeft' => 'auto', 'marginRight' => 'auto',
                    'fontWeight' => 'bold', 'width' => '84%',
                ],
                'columns' => [['width' => '33.333%'], ['width' => '33.333%'], ['width' => '33.333%']],
                'rows' => [[
                    'cells' => [
                        ['content' => 'Nhân viên', 'style' => ['textAlign' => 'center', 'fontSize' => '10px', 'fontWeight' => 'bold', 'color' => '#111111', 'border' => 'none', 'paddingTop' => '0px', 'paddingRight' => '0px', 'paddingBottom' => '0px', 'paddingLeft' => '0px']],
                        ['content' => 'Trưởng phòng', 'style' => ['textAlign' => 'center', 'fontSize' => '10px', 'fontWeight' => 'bold', 'color' => '#111111', 'border' => 'none', 'paddingTop' => '0px', 'paddingRight' => '0px', 'paddingBottom' => '0px', 'paddingLeft' => '0px']],
                        ['content' => 'Bộ phận kế toán', 'style' => ['textAlign' => 'center', 'fontSize' => '10px', 'fontWeight' => 'bold', 'color' => '#111111', 'border' => 'none', 'paddingTop' => '0px', 'paddingRight' => '0px', 'paddingBottom' => '0px', 'paddingLeft' => '0px']],
                    ],
                ]],
            ]],
        ];

        foreach ($blocks['detail'] as &$block) {
            if (! in_array($block['id'] ?? '', ['deposits_sale_table', 'deposits_sale_allocation'], true)) {
                continue;
            }

            foreach ($block['columns'] as &$column) {
                $column['headerStyle'] = array_merge($this->tableCellStyle(isHeader: true, align: $column['align'] ?? 'left'), $column['headerStyle'] ?? []);
                $column['cellStyle'] = array_merge($this->tableCellStyle(isHeader: false, align: $column['align'] ?? 'left'), $column['cellStyle'] ?? []);
            }
            unset($column);

            if (isset($block['customRows'])) {
                foreach ($block['customRows'] as &$row) {
                    foreach ($row['cells'] as &$cell) {
                        $cell = array_merge($this->customTotalCellStyle(), $cell);
                    }
                    unset($cell);
                }
                unset($row);
            }
        }
        unset($block);

        return $blocks;
    }

    private function tableCellStyle(bool $isHeader, string $align): array
    {
        return [
            'textAlign' => $align,
            'verticalAlign' => 'middle',
            'fontFamily' => 'Arial, Helvetica, sans-serif',
            'fontSize' => '10px',
            'fontWeight' => $isHeader ? 'bold' : 'normal',
            'color' => '#111111',
            'backgroundColor' => $isHeader ? '#d9deea' : '#ffffff',
            'border' => '1px solid #aeb5c0',
            'paddingTop' => '4px',
            'paddingRight' => '3px',
            'paddingBottom' => '4px',
            'paddingLeft' => '3px',
            'lineHeight' => '1.1',
            'overflowWrap' => 'anywhere',
            'whiteSpace' => $align === 'right' ? 'nowrap' : 'normal',
        ];
    }

    private function customTotalCellStyle(): array
    {
        return [
            'backgroundColor' => '#d9deea',
            'color' => '#111111',
            'fontSize' => '10px',
            'fontWeight' => 'bold',
            'borderColor' => '#aeb5c0',
        ];
    }

    private function columns(): array
    {
        return [
            ['header'=>'Mã ĐK','value'=>'row.BookingCode','width'=>'7%','align'=>'center'],['header'=>'Phòng','value'=>'row.Room','width'=>'5%','align'=>'center'],['header'=>'Tên Khách','value'=>'row.GuestInfo','width'=>'12%','align'=>'left'],['header'=>'Ngày Đến','value'=>'row.ArrivalDate','width'=>'8%','align'=>'center'],['header'=>'Ngày Đi','value'=>'row.DepartureDate','width'=>'8%','align'=>'center'],['header'=>'Giờ','value'=>'row.OpenTime','width'=>'5%','align'=>'center'],['header'=>'Mã TT','value'=>'row.PaymentMethod','width'=>'5%','align'=>'center'],['header'=>'Mã HĐ','value'=>'row.BillID','width'=>'5%','align'=>'center'],['header'=>'Tổng','value'=>'row.Amount','width'=>'8%','align'=>'right','format'=>'number'],['header'=>'Người Dùng','value'=>'row.Username','width'=>'9%','align'=>'center'],['header'=>'Mô tả','value'=>'row.Description','width'=>'28%','align'=>'left'],
        ];
    }

    /**
     * Runtime HTML is a compiled artifact of the Designer blocks above.
     * Do not add report layout markup outside content_json.
     */
    private function compileDesignerBlocks(array $bands): string
    {
        $html = '';
        foreach (['header', 'detail', 'footer'] as $band) {
            if ($band === 'header') {
                $html .= $this->compileHeader($bands[$band] ?? []);
                continue;
            }

            $html .= '<div class="report-'.($band === 'detail' ? 'detail' : $band).'-band">';
            foreach ($bands[$band] ?? [] as $block) {
                $html .= $this->compileDesignerBlock($block);
            }
            $html .= '</div>';
        }

        return $html;
    }

    public function compileHeader(array $blocks): string
    {
        $html = '<div class="report-header-band">';
        foreach ($blocks as $block) {
            if (is_array($block)) {
                $html .= $this->compileDesignerBlock($block);
            }
        }

        return $html.'</div>';
    }

    private function compileDesignerBlock(array $block): string
    {
        $id = htmlspecialchars((string) ($block['id'] ?? ''), ENT_QUOTES, 'UTF-8');
        $style = $this->compileStyle($block['style'] ?? []);
        $safeId = preg_replace('/[^a-zA-Z0-9_-]/', '-', $id);
        $class = 'pms-template-block-'.$safeId;
        $fontSize = !empty($block['style']['fontSize'])
            ? '<style>.'.$class.', .'.$class.' * { font-size: '.$block['style']['fontSize'].' !important; }</style>'
            : '';
        $open = $fontSize.'<div id="'.$id.'" class="'.$class.'" style="'.$style.'">';

        if (in_array($block['type'] ?? '', ['text', 'divider'], true)) {
            return $open."\n  ".($block['content'] ?? '')."\n</div>\n";
        }

        if (($block['type'] ?? '') === 'columns') {
            $html = $open."\n  <table style=\"width: 100%; border: none; border-collapse: collapse; margin: 0; padding: 0;\">\n    <tr style=\"border: none;\">\n";
            foreach ($block['columns'] ?? [] as $column) {
                $width = htmlspecialchars((string) ($column['width'] ?? '50%'), ENT_QUOTES, 'UTF-8');
                $html .= '      <td style="width: '.$width.'; border: none; padding: 0; vertical-align: top;">' ."\n";
                foreach ($column['blocks'] ?? [] as $nestedBlock) {
                    if (is_array($nestedBlock)) {
                        $html .= $this->compileDesignerBlock($nestedBlock);
                    }
                }
                $html .= "      </td>\n";
            }

            return $html."    </tr>\n  </table>\n</div>\n";
        }

        if (($block['type'] ?? '') === 'static-table') {
            $html = $open."\n  <table style=\"width: 100%; border-collapse: collapse; border: none;\">\n    <tbody>\n";
            foreach ($block['rows'] ?? [] as $row) {
                $rowStyle = $this->compileStyle($row['style'] ?? []);
                $html .= '      <tr style="'.$rowStyle.'">'."\n";
                foreach ($row['cells'] ?? [] as $index => $cell) {
                    $column = $block['columns'][$index] ?? [];
                    $cellStyle = $this->compileStyle(array_merge($row['style'] ?? [], $cell['style'] ?? []));
                    $html .= '        <td style="padding: 6px 8px; border: 1px solid #cbd5e1; width:'.($column['width'] ?? 'auto').';'.$cellStyle.'">'.($cell['content'] ?? '')."</td>\n";
                }
                $html .= "      </tr>\n";
            }

            return $html."    </tbody>\n  </table>\n</div>\n";
        }

        if (($block['type'] ?? '') !== 'table') {
            return $open.'</div>';
        }

        $columns = $block['columns'] ?? [];
        $html = $open."\n  <table style=\"width: 100%; border-collapse: collapse; border: none;\">\n    <thead>\n      <tr>\n";
        $tableStyle = $block['tableStyle'] ?? 'grid';
        $thStyle = 'padding: 6px 8px; font-weight: bold;';
        $tdStyle = 'padding: 6px 8px;';
        if ($tableStyle === 'grid') {
            $thStyle .= ' border-bottom: 2px solid #cbd5e1; border-right: 1px solid #cbd5e1;';
            $tdStyle .= ' border-bottom: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0;';
        } elseif ($tableStyle === 'horizontal') {
            $thStyle .= ' border-bottom: 2px solid #cbd5e1;';
            $tdStyle .= ' border-bottom: 1px solid #e2e8f0;';
        } else {
            $thStyle .= ' border: none;';
            $tdStyle .= ' border: none;';
        }
        foreach ($columns as $column) {
            $headerStyle = $this->compileStyle(array_merge([
                'textAlign' => 'center',
                'fontWeight' => 'bold',
            ], $column['headerStyle'] ?? []));
            $html .= '        <th style="'.$thStyle.' width: '.($column['width'] ?? 'auto').'; '.$headerStyle.';">'.($column['header'] ?? '')."</th>\n";
        }
        $html .= "      </tr>\n    </thead>\n";
        $groups = $block['groups'] ?? [];
        $source = htmlspecialchars((string) ($block['dataSource'] ?? 'rows'), ENT_QUOTES, 'UTF-8');
        $html .= '<tbody'.($groups ? ' class="pms-grouped-rows" data-source="'.$source.'" data-group-by="'.htmlspecialchars((string) ($groups[0]['field'] ?? ''), ENT_QUOTES, 'UTF-8').'"' : '').'>';
        foreach ($groups as $group) {
            $html .= '<tr class="pms-group-header" data-group-level="0" data-group-field="'.htmlspecialchars((string) ($group['field'] ?? ''), ENT_QUOTES, 'UTF-8').'" data-group-sort="'.($group['sort'] ?? 'ASC').'">';
            $headerCells = $group['headerCells'] ?? [];
            if ($headerCells !== []) {
                foreach ($headerCells as $cell) {
                    $content = $cell['content'] ?? '';
                    if (($cell['type'] ?? '') === 'binding') {
                        $content = '{{'.($cell['binding'] ?? '').'}}';
                    }
                    $cellStyle = $this->compileStyle([
                        'textAlign' => $cell['align'] ?? 'left',
                        'backgroundColor' => $cell['backgroundColor'] ?? null,
                        'color' => $cell['color'] ?? null,
                        'borderColor' => $cell['borderColor'] ?? null,
                        'fontSize' => $cell['fontSize'] ?? null,
                        'fontWeight' => $cell['fontWeight'] ?? 'bold',
                    ]);
                    $html .= '<td colspan="'.max(1, (int) ($cell['colspan'] ?? count($columns))).'" style="'.$tdStyle.' '.$cellStyle.'">'.$content.'</td>';
                }
            } else {
                $html .= '<td colspan="'.max(1, count($columns)).'" style="'.$tdStyle.' text-align: left; font-weight: bold;">'.($group['label'] ?? '').'</td>';
            }
            $html .= '</tr>';
        }
        $html .= '      <tr class="pms-detail-row"'.($groups ? '' : ' data-source="'.$source.'"').">\n";
        foreach ($columns as $column) {
            $format = ($column['format'] ?? '') === 'number' ? '|number' : '';
            $cellStyle = $this->compileStyle(array_merge([
                'textAlign' => $column['align'] ?? 'left',
            ], $column['cellStyle'] ?? []));
            $html .= '        <td style="'.$tdStyle.' '.$cellStyle.';">{{'.($column['value'] ?? '').$format."}}</td>\n";
        }
        $html .= "      </tr>\n";
        foreach (($block['customRows'] ?? []) as $row) {
            if (($row['scope'] ?? 'table') === 'table') {
                continue;
            }
            $html .= $this->compileCustomRow($row);
        }
        $html .= '</tbody>';
        $tableRows = array_filter($block['customRows'] ?? [], fn (array $row) => ($row['scope'] ?? 'table') === 'table');
        if ($tableRows) {
            $html .= '<tfoot>';
            foreach ($tableRows as $row) {
                $html .= $this->compileCustomRow($row);
            }
            $html .= '</tfoot>';
        }

        return $html.'</table></div>';
    }

    private function compileCustomRow(array $row): string
    {
        $html = '<tr class="pms-custom-row '.htmlspecialchars((string) ($row['className'] ?? ''), ENT_QUOTES, 'UTF-8').'">';
        foreach ($row['cells'] ?? [] as $cell) {
            $content = $cell['content'] ?? '';
            if (($cell['type'] ?? '') === 'binding') {
                $content = '{{'.($cell['binding'] ?? '').(($cell['format'] ?? '') === 'number' ? '|number' : '').'}}';
            }
            $cellStyle = $this->compileStyle([
                'textAlign' => $cell['align'] ?? 'left',
                'backgroundColor' => $cell['backgroundColor'] ?? null,
                'color' => $cell['color'] ?? null,
                'borderColor' => $cell['borderColor'] ?? null,
                'fontSize' => $cell['fontSize'] ?? null,
                'fontWeight' => $cell['fontWeight'] ?? 'bold',
            ]);
            $html .= '<td colspan="'.max(1, (int) ($cell['colspan'] ?? 1)).'" style="padding: 6px 8px; '.$cellStyle.'">'.$content.'</td>';
        }

        return $html.'</tr>';
    }

    private function compileStyle(array $style): string
    {
        $parts = [];
        foreach ($style as $name => $value) {
            if ($value !== null && $value !== '') {
                $parts[] = strtolower((string) preg_replace('/([A-Z])/', '-$1', (string) $name)).': '.$value;
            }
        }

        return implode('; ', $parts);
    }

    private function css(): string
    {
        return <<<'CSS'
#deposits_sale_table table,#deposits_sale_allocation table,#deposits_sale_signatures table{margin:0;table-layout:fixed}
@media print{thead{display:table-header-group}tr{break-inside:avoid}}
CSS;
    }
};
