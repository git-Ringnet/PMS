<?php

use App\Services\TemplateRendererService;

/** Reference layout for legacy sp_206 (Outlet='MB', freeitem=0), product summary. */
return new class
{
    public function definition(): array
    {
        return [
            'report' => 'MINIBAR_INVOICES_BY_PRODUCT_REFERENCE',
            'name' => 'Báo cáo hóa đơn minibar theo sản phẩm - Mẫu tham chiếu',
            'page_size' => 'A4',
            'page_orientation' => 'portrait',
            'margin_top' => 6,
            'margin_bottom' => 6,
            'margin_left' => 5,
            'margin_right' => 5,
            'version' => '1.1',
            'content_html' => $this->html(),
            'content_json' => $this->blocks(),
            'css' => $this->css(),
        ];
    }

    public function html(): string
    {
        return $this->compileDesignerBlocks($this->blocks());
    }

    public function compileHeader(array $header): string
    {
        $html = '<div class="report-header-band">'.PHP_EOL;
        foreach ($header as $block) {
            $html .= $this->compileDesignerBlock($block);
        }
        return $html.'</div>'.PHP_EOL;
    }

    public function blocks(): array
    {
        return [
            'header' => $this->headerBlocks(
                'minibar_product',
                '<h1>BÁO CÁO HÓA ĐƠN MINIBAR(THEO SẢN PHẨM)</h1>',
                '<p class="period"><b>Ngày:</b> {{parameters.p_from_date}} &nbsp; ~ &nbsp; {{parameters.p_to_date}}</p>'
            ),
            'detail' => [[
                'id' => 'minibar_product_table',
                'type' => 'table',
                'dataSource' => 'rows',
                'tableType' => 'dynamic',
                'tableStyle' => 'grid',
                'grouping' => [
                    [
                        'id' => 'minibar_product_date_group',
                        'field' => 'DateGroup',
                        'label' => 'Ngày: {{row.DateGroup}}',
                        'className' => 'date-group',
                        'sort' => 'ASC',
                        'enabledBy' => 'parameters.p_group_by_date',
                        'headerCells' => [
                            [
                                'id' => 'minibar_product_date_cell',
                                'type' => 'text',
                                'content' => 'Ngày: {{row.DateGroup}}',
                                'colspan' => 8,
                                'align' => 'left',
                                'className' => 'date-group-cell',
                                'style' => [
                                    'color' => '#1e293b',
                                    'fontWeight' => 'bold',
                                    'textAlign' => 'left',
                                    'backgroundColor' => '#f1f5f9',
                                ],
                            ],
                        ],
                    ],
                    [
                        'id' => 'minibar_product_type_group',
                        'field' => 'ProductType',
                        'label' => 'Loại &nbsp; {{row.ProductType}}',
                        'className' => 'type-group',
                        'sort' => 'ASC',
                        'headerCells' => [
                            [
                                'id' => 'minibar_product_type_cell',
                                'type' => 'text',
                                'content' => 'Loại &nbsp; {{row.ProductType}}',
                                'colspan' => 8,
                                'align' => 'left',
                                'className' => 'type-group-cell',
                                'style' => [
                                    'color' => '#dc2626',
                                    'fontWeight' => 'bold',
                                    'textAlign' => 'left',
                                    'backgroundColor' => '#ffffff',
                                ],
                            ],
                        ],
                    ],
                ],
                'columns' => [
                    ['header' => 'ID', 'value' => 'row.ID', 'width' => '5%', 'align' => 'center'],
                    ['header' => 'Sản phẩm', 'value' => 'row.Product', 'width' => '31%', 'align' => 'left'],
                    ['header' => 'Đơn vị', 'value' => 'row.Currency', 'width' => '9%', 'align' => 'center'],
                    ['header' => 'Đơn Giá', 'value' => 'row.Rate', 'width' => '11%', 'align' => 'right', 'format' => 'number'],
                    ['header' => 'Số lượng', 'value' => 'row.Quantity', 'width' => '9%', 'align' => 'right', 'format' => 'number'],
                    ['header' => 'Thành tiền', 'value' => 'row.Amount', 'width' => '11%', 'align' => 'right', 'format' => 'number'],
                    ['header' => 'Giảm Giá', 'value' => 'row.DiscountAmount', 'width' => '11%', 'align' => 'right', 'format' => 'number'],
                    ['header' => 'Tổng tiền', 'value' => 'row.Total', 'width' => '13%', 'align' => 'right', 'format' => 'number'],
                ],
                'customRows' => [
                    [
                        'id' => 'minibar_product_subtotals',
                        'scope' => 'group',
                        'level' => 0,
                        'className' => 'product-subtotal-row',
                        'cells' => [
                            ['id' => 'subtotal_label', 'type' => 'text', 'content' => 'Tổng tiền', 'colspan' => 7, 'align' => 'right', 'className' => 'subtotal-label', 'style' => ['fontWeight' => 'bold', 'textAlign' => 'right', 'backgroundColor' => '#f8fafc']],
                            ['id' => 'subtotal_value', 'type' => 'binding', 'binding' => 'group.sum.Total', 'colspan' => 1, 'align' => 'right', 'format' => 'number', 'className' => 'subtotal-value', 'style' => ['fontWeight' => 'bold', 'textAlign' => 'right', 'backgroundColor' => '#f8fafc', 'whiteSpace' => 'nowrap']],
                        ],
                    ],
                    [
                        'id' => 'minibar_product_totals',
                        'scope' => 'table',
                        'level' => 0,
                        'className' => 'product-total-row',
                        'cells' => [
                            ['id' => 'grandtotal_label', 'type' => 'text', 'content' => 'Tổng tiền', 'colspan' => 7, 'align' => 'right', 'className' => 'grandtotal-label', 'style' => ['fontWeight' => 'bold', 'textAlign' => 'right', 'backgroundColor' => '#f8fafc']],
                            ['id' => 'grandtotal_value', 'type' => 'binding', 'binding' => 'aggregate.rows.sum.Total', 'colspan' => 1, 'align' => 'right', 'format' => 'number', 'className' => 'grandtotal-value', 'style' => ['fontWeight' => 'bold', 'textAlign' => 'right', 'backgroundColor' => '#f8fafc', 'whiteSpace' => 'nowrap']],
                        ],
                    ],
                ],
            ]],
            'footer' => [],
        ];
    }

    /**
     * Canonical header band used by the Designer and report templates.
     * The title and period remain report-specific when reused by migrations.
     */
    public function headerBlocks(string $prefix, string $title, string $period): array
    {
        $safePrefix = preg_replace('/[^a-zA-Z0-9_-]/', '_', $prefix) ?: 'report';
        $baseStyle = [
            'textAlign' => 'left', 'fontSize' => '13px',
            'paddingTop' => '5px', 'paddingBottom' => '5px',
            'paddingLeft' => '0px', 'paddingRight' => '0px',
            'marginTop' => '10px', 'marginBottom' => '10px',
            'color' => '#1e293b', 'fontWeight' => 'normal',
            'whiteSpace' => 'normal', 'backgroundColor' => '',
            'borderSide' => 'all', 'borderStyle' => 'none',
            'borderWidth' => '0px', 'borderColor' => '#cbd5e1',
            'borderRadius' => '0px',
        ];
        $nestedStyle = [
            'textAlign' => 'left', 'fontSize' => '9px',
            'paddingTop' => '0px', 'paddingBottom' => '0px',
            'paddingLeft' => '0px', 'paddingRight' => '0px',
            'marginTop' => '0px', 'marginBottom' => '0px',
            'color' => '#1e293b', 'fontWeight' => 'normal',
            'whiteSpace' => 'normal', 'backgroundColor' => '',
            'borderSide' => 'all', 'borderStyle' => 'none',
            'borderWidth' => '0px', 'borderColor' => '#cbd5e1',
            'borderRadius' => '0px',
        ];
        $bandStyle = [
            'textAlign' => 'left', 'fontSize' => '13px',
            'paddingTop' => '0px', 'paddingBottom' => '0px',
            'paddingLeft' => '0px', 'paddingRight' => '0px',
            'marginTop' => '0px', 'marginBottom' => '6px',
            'color' => '#1e293b', 'fontWeight' => 'normal',
            'whiteSpace' => 'normal', 'backgroundColor' => '',
            'borderSide' => 'all', 'borderStyle' => 'none',
            'borderWidth' => '0px', 'borderColor' => '#cbd5e1',
            'borderRadius' => '0px',
        ];
        $titleStyle = array_merge($bandStyle, [
            'textAlign' => 'center', 'fontSize' => '18px', 'marginBottom' => '0px',
            'fontWeight' => 'bold',
        ]);
        $periodStyle = array_merge($bandStyle, [
            'textAlign' => 'center', 'fontSize' => '11px',
            'marginTop' => '4px', 'marginBottom' => '14px',
        ]);

        return [
            [
                'id' => $safePrefix.'_header_band',
                'type' => 'columns',
                'style' => $baseStyle,
                'columns' => [
                    [
                        'width' => '30%',
                        'blocks' => [[
                            'id' => $safePrefix.'_logo',
                            'type' => 'text',
                            'content' => '<div class="hotel-logo" style="min-height: 58px;">{{hotel.logo}}</div>',
                            'style' => array_merge($nestedStyle, ['fontSize' => '14px', 'marginLeft' => '20px']),
                        ]],
                    ],
                    [
                        'width' => '70%',
                        'blocks' => [[
                            'id' => $safePrefix.'_hotel_information',
                            'type' => 'text',
                            'content' => '<div class="hotel-information"><div><b>Địa chỉ:</b> {{hotel.address}}</div><div><b>Người Dùng:</b> {{report.generated_by}} &nbsp; <b>Ngày:</b> {{report.generated_at}}</div></div>',
                            'style' => array_merge($nestedStyle, ['textAlign' => 'right']),
                        ]],
                    ],
                ],
            ],
            [
                'id' => $safePrefix.'_divider',
                'type' => 'divider',
                'content' => '<hr class="header-divider">',
                'style' => $bandStyle,
            ],
            [
                'id' => $safePrefix.'_title',
                'type' => 'text',
                'content' => $title,
                'style' => $titleStyle,
            ],
            [
                'id' => $safePrefix.'_period',
                'type' => 'text',
                'content' => $period,
                'style' => $periodStyle,
            ],
        ];
    }

    private function compileDesignerBlocks(array $bands): string
    {
        $html = '';
        foreach (['header', 'detail', 'footer'] as $band) {
            if ($band === 'header') {
                $html .= $this->compileHeader($bands[$band] ?? []);
                continue;
            }
            $html .= '<div class="report-'.($band === 'detail' ? 'detail' : $band).'-band">'."\n";
            foreach ($bands[$band] ?? [] as $block) {
                $html .= $this->compileDesignerBlock($block);
            }
            $html .= '</div>'."\n";
        }

        return $html;
    }

    private function compileDesignerBlock(array $block): string
    {
        $id = htmlspecialchars((string) ($block['id'] ?? ''), ENT_QUOTES, 'UTF-8');
        $style = $this->compileStyle($block['style'] ?? []);
        $safeId = preg_replace('/[^a-zA-Z0-9_-]/', '-', $id);
        $class = 'pms-template-block-'.$safeId;
        $fontSize = ! empty($block['style']['fontSize'])
            ? '<style>.'.$class.', .'.$class.' * { font-size: '.$block['style']['fontSize'].' !important; }</style>'."\n"
            : '';
        $open = $fontSize.'<div id="'.$id.'" class="'.$class.'" style="'.$style.'">';

        if (in_array($block['type'] ?? '', ['text', 'divider'], true)) {
            return $open."\n  ".($block['content'] ?? '')."\n</div>\n";
        }

        if (($block['type'] ?? '') === 'columns') {
            $html = $open."\n  <table style=\"width: 100%; border: none; border-collapse: collapse; margin: 0; padding: 0;\">\n    <tr style=\"border: none;\">\n";
            foreach ($block['columns'] ?? [] as $column) {
                $html .= '      <td style="width: '.($column['width'] ?? '50%').'; border: none; padding: 0; vertical-align: top;">'."\n";
                foreach ($column['blocks'] ?? [] as $nestedBlock) {
                    $html .= $this->compileDesignerBlock($nestedBlock);
                }
                $html .= "      </td>\n";
            }
            return $html."    </tr>\n  </table>\n</div>\n";
        }

        if (($block['type'] ?? '') !== 'table') {
            return $open."\n</div>\n";
        }

        $columns = $block['columns'] ?? [];
        $html = $open."\n  <table style=\"width: 100%; border-collapse: collapse; border: none;\">\n    <thead>\n      <tr>\n";
        $thStyle = 'padding: 6px 8px; font-weight: bold; border-bottom: 2px solid #cbd5e1; border-right: 1px solid #cbd5e1;';
        $tdStyle = 'padding: 6px 8px; border-bottom: 1px solid #e2e8f0; border-right: 1px solid #e2e8f0;';

        foreach ($columns as $column) {
            $width = $column['width'] ?? 'auto';
            $align = $column['align'] ?? 'left';
            $html .= '        <th style="'.$thStyle.' width: '.$width.'; text-align: '.$align.'; font-weight: bold;">'.($column['header'] ?? '')."</th>\n";
        }
        $html .= "      </tr>\n    </thead>\n";

        $groups = $block['grouping'] ?? $block['groups'] ?? [];
        $source = htmlspecialchars((string) ($block['dataSource'] ?? 'rows'), ENT_QUOTES, 'UTF-8');
        $html .= '    <tbody class="pms-grouped-rows" data-source="'.$source.'" data-group-configured="1" data-group-by="'.htmlspecialchars((string) ($groups[0]['field'] ?? ''), ENT_QUOTES, 'UTF-8')."\">\n";

        foreach ($groups as $index => $group) {
            $enabledByAttr = ! empty($group['enabledBy']) ? ' data-group-enabled-by="'.htmlspecialchars((string) $group['enabledBy'], ENT_QUOTES, 'UTF-8').'"' : '';
            $html .= '      <tr class="pms-group-header" data-group-level="'.$index.'" data-group-field="'.htmlspecialchars((string) ($group['field'] ?? ''), ENT_QUOTES, 'UTF-8').'" data-group-sort="'.($group['sort'] ?? 'ASC').'"'.$enabledByAttr.'>';
            if (! empty($group['headerCells'])) {
                foreach ($group['headerCells'] as $cell) {
                    $cellClass = ! empty($cell['className']) ? ' class="'.$cell['className'].'"' : '';
                    $cellStyle = $this->compileStyle($cell['style'] ?? []);
                    $html .= '<td colspan="'.max(1, (int) ($cell['colspan'] ?? count($columns))).'"'.$cellClass.' style="'.$tdStyle.' '.$cellStyle.'">'.($cell['content'] ?? '').'</td>';
                }
            } else {
                $html .= '<td colspan="'.max(1, count($columns)).'" style="'.$tdStyle.' text-align: left; font-weight: bold;">'.($group['label'] ?? '').'</td>';
            }
            $html .= "</tr>\n";
        }

        $html .= "      <tr class=\"pms-detail-row\">\n";
        foreach ($columns as $column) {
            $format = ($column['format'] ?? '') === 'number' ? '|number' : '';
            $align = $column['align'] ?? 'left';
            $html .= '        <td style="'.$tdStyle.' text-align: '.$align.';">{{'.($column['value'] ?? '').$format."}}</td>\n";
        }
        $html .= "      </tr>\n";

        foreach (($block['customRows'] ?? []) as $row) {
            if (($row['scope'] ?? 'table') === 'table') {
                continue;
            }
            $html .= $this->compileCustomRow($row, 'pms-group-custom-row', $tdStyle);
        }
        $html .= "    </tbody>\n";

        $tableRows = array_filter($block['customRows'] ?? [], fn (array $row) => ($row['scope'] ?? 'table') === 'table');
        if ($tableRows) {
            $html .= "    <tfoot>\n";
            foreach ($tableRows as $row) {
                $html .= $this->compileCustomRow($row, 'pms-custom-row', $tdStyle);
            }
            $html .= "    </tfoot>\n";
        }

        return $html."  </table>\n</div>\n";
    }

    private function compileCustomRow(array $row, string $className, string $tdStyle): string
    {
        $customClass = ! empty($row['className']) ? ' '.htmlspecialchars((string) $row['className'], ENT_QUOTES, 'UTF-8') : '';
        $levelAttr = isset($row['level']) ? ' data-group-level="'.(int) $row['level'].'"' : '';
        $visibleAttr = ! empty($row['enabledBy']) ? ' data-visible-by="'.htmlspecialchars((string) $row['enabledBy'], ENT_QUOTES, 'UTF-8').'"' : '';
        $html = '      <tr class="'.$className.$customClass.'"'.$levelAttr.$visibleAttr.">\n";

        foreach ($row['cells'] ?? [] as $cell) {
            $content = $cell['content'] ?? '';
            if (($cell['type'] ?? '') === 'binding') {
                $content = '{{'.($cell['binding'] ?? '').(($cell['format'] ?? '') === 'number' ? '|number' : '').'}}';
            }
            $cellClass = ! empty($cell['className']) ? ' class="'.$cell['className'].'"' : '';
            $cellStyle = $this->compileStyle($cell['style'] ?? []);
            $html .= '        <td colspan="'.max(1, (int) ($cell['colspan'] ?? 1)).'"'.$cellClass.' style="'.$tdStyle.' '.$cellStyle.'">'.$content."</td>\n";
        }

        return $html."      </tr>\n";
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

    public function css(): string
    {
        return <<<'CSS'
body { color: #0f172a; font-family: Arial, Helvetica, sans-serif; font-size: 10px; margin: 0 auto; }
.hotel-header { display: grid; grid-template-columns: 175px 1fr; align-items: center; min-height: 65px; }
.hotel-logo { display: flex; align-items: center; min-height: 55px; }
.hotel-logo img { max-width: 120px; max-height: 55px; object-fit: contain; }
.hotel-information { line-height: 1.8; text-align: right; font-size: 9.5px; }
hr, .header-divider { margin: 0 0 10px; border: 0; border-top: 1px solid #cbd5e1; }
h1 { margin: 10px 0 6px; text-align: center; font-size: 18px; font-weight: bold; letter-spacing: 0.5px; }
.period { margin: 4px 0 14px; text-align: center; font-size: 11px; }
.product-report, #minibar_product_table table { width: 100%; border-collapse: collapse; table-layout: fixed; }
.product-report th, .product-report td, #minibar_product_table th, #minibar_product_table td { border: 1px solid #cbd5e1; padding: 5px 4px !important; line-height: 1.2; vertical-align: middle; }
.product-report th, #minibar_product_table th { background: #e2e8f0; color: #1e293b; font-weight: bold; text-align: center; }
.type-group td, .type-group-cell, .pms-group-header td { font-weight: bold; text-align: left; color: #dc2626 !important; background: #fff !important; }
.subtotal-label, .grandtotal-label, .product-subtotal-row td, .product-total-row td { font-weight: bold; text-align: right; background: #f8fafc; }
.subtotal-value, .grandtotal-value, .product-subtotal-row td:last-child, .product-total-row td:last-child { font-weight: bold; text-align: right; background: #f8fafc; white-space: nowrap; }
.product-report tfoot td, #minibar_product_table tfoot td { font-weight: bold; border-top: 1.5px solid #94a3b8; }
@media print {
  thead { display: table-header-group; }
  tr { break-inside: avoid; }
}
CSS;
    }

    public function prepareData(array $data): array
    {
        return $this->sanitize($data);
    }

    public function render(array $data, ?TemplateRendererService $renderer = null): string
    {
        $renderer ??= new TemplateRendererService();
        $d = $this->definition();

        return $renderer->render(
            $this->html(),
            $this->css(),
            $this->prepareData($data),
            array_intersect_key($d, array_flip(['page_size', 'page_orientation', 'margin_top', 'margin_bottom', 'margin_left', 'margin_right']))
        );
    }

    private function sanitize(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($item) => $this->sanitize($item), $value);
        }
        if (! is_string($value)) {
            return $value;
        }

        return str_replace(['{', '}'], ['&#123;', '&#125;'], htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    }
};
