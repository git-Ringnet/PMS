<?php

use App\Services\TemplateRendererService;

/**
 * Reference template provider for VIP_GUESTS (legacy sp_295 reference).
 * Configured with full Designer blocks (content_json), HTML, and CSS
 * matching legacy screenshots (Báo_cáo_khách_VIP_1.png and BC_khách_VIP_1.png).
 */
return new class
{
    public function definition(): array
    {
        return [
            'code' => 'VIP_GUESTS',
            'name' => 'Báo cáo khách VIP',
            'report' => 'VIP_GUESTS_STANDARD',
            'page_size' => 'A4',
            'page_orientation' => 'landscape',
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
                    'GuestName' => 'string',
                    'Status' => 'number',
                    'StatusVi' => 'string',
                    'BookingId' => 'string',
                    'Room' => 'string',
                    'GuestType' => 'string',
                    'ArrivalDate' => 'string',
                    'DepartureDate' => 'string',
                    'Rate' => 'number',
                    'Adult' => 'number',
                    'Child' => 'number',
                    'AdultChild' => 'string',
                    'Company' => 'string',
                    'Note' => 'string',
                ],
                'parameters' => [
                    'p_from_date' => 'string',
                    'p_to_date' => 'string',
                    'p_guest_type' => 'int',
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
                'id' => 'vip_guests_header_band',
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
                            'id' => 'vip_guests_logo',
                            'type' => 'text',
                            'content' => '<div class="hotel-logo" style="min-height: 52px;">{{hotel.logo}}</div>',
                            'style' => ['fontSize' => '13px'],
                        ]],
                    ],
                    [
                        'width' => '70%',
                        'blocks' => [[
                            'id' => 'vip_guests_hotel_info',
                            'type' => 'text',
                            'content' => '<div class="hotel-information" style="text-align: right; font-size: 10.5px; line-height: 1.5; color: #1e293b;">'
                                .'<div><b>Địa chỉ:</b> {{hotel.address}}</div>'
                                .'<div><b>Nhân viên:</b> {{report.generated_by}} &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <b>Ngày:</b> {{report.generated_at}}</div>'
                                .'</div>',
                            'style' => ['textAlign' => 'right'],
                        ]],
                    ],
                ],
            ],
            [
                'id' => 'vip_guests_divider',
                'type' => 'divider',
                'content' => '<hr class="header-divider" style="border: none; border-top: 1px solid #000; margin: 4px 0 10px 0;">',
                'style' => ['marginTop' => '2px', 'marginBottom' => '8px'],
            ],
            [
                'id' => 'vip_guests_title',
                'type' => 'text',
                'content' => '<h1 style="text-align: center; font-size: 18px; font-weight: bold; margin: 6px 0 4px 0; text-transform: uppercase;">BÁO CÁO KHÁCH VIP</h1>',
                'style' => ['textAlign' => 'center', 'fontWeight' => 'bold'],
            ],
            [
                'id' => 'vip_guests_period',
                'type' => 'text',
                'content' => '<p class="report-period" style="text-align: center; font-size: 11px; margin: 2px 0 12px 0;">{{parameters.p_from_date}} - {{parameters.p_to_date}}</p>',
                'style' => ['textAlign' => 'center', 'fontSize' => '11px', 'marginBottom' => '10px'],
            ],
        ];

        return [
            'header' => $header,
            'detail' => [[
                'id' => 'vip_guests_table',
                'type' => 'table',
                'dataSource' => 'rows',
                'tableType' => 'dynamic',
                'tableStyle' => 'grid',
                'tableClassName' => 'vip-guests-table',
                'style' => ['width' => '100%', 'fontSize' => '11px'],
                'grouping' => [
                    [
                        'id' => 'vip_guests_guest_type_group',
                        'field' => 'GuestType',
                        'label' => 'LOẠI KHÁCH: {{row.GuestType}}',
                        'className' => 'guest-type-group-header',
                        'sort' => 'ASC',
                        'headerCells' => [
                            [
                                'id' => 'group_label_cell',
                                'type' => 'text',
                                'content' => '<span style="color: #ff1414; font-weight: bold;">LOẠI KHÁCH:</span>',
                                'colspan' => 1,
                                'align' => 'left',
                                'className' => 'group-title-cell',
                            ],
                            [
                                'id' => 'group_value_cell',
                                'type' => 'text',
                                'content' => '<span style="color: #ff1414; font-weight: bold;">{{row.GuestType}}</span>',
                                'colspan' => 1,
                                'align' => 'left',
                                'className' => 'group-value-cell',
                            ],
                            ['id' => 'group_empty_3', 'type' => 'text', 'content' => '', 'colspan' => 1],
                            ['id' => 'group_empty_4', 'type' => 'text', 'content' => '', 'colspan' => 1],
                            ['id' => 'group_empty_5', 'type' => 'text', 'content' => '', 'colspan' => 1],
                            ['id' => 'group_empty_6', 'type' => 'text', 'content' => '', 'colspan' => 1],
                            ['id' => 'group_empty_7', 'type' => 'text', 'content' => '', 'colspan' => 1],
                            ['id' => 'group_empty_8', 'type' => 'text', 'content' => '', 'colspan' => 1],
                            ['id' => 'group_empty_9', 'type' => 'text', 'content' => '', 'colspan' => 1],
                            ['id' => 'group_empty_10', 'type' => 'text', 'content' => '', 'colspan' => 1],
                            ['id' => 'group_empty_11', 'type' => 'text', 'content' => '', 'colspan' => 1],
                        ],
                    ],
                ],
                'columns' => $this->columns(),
                'customRows' => [
                    [
                        'id' => 'vip_guests_group_total_row',
                        'enabledBy' => '',
                        'scope' => 'group',
                        'level' => 0,
                        'className' => 'pms-group-footer',
                        'cells' => [
                            ['id' => 'group_total_label', 'type' => 'text', 'content' => 'TỔNG CỘNG', 'colspan' => 1, 'align' => 'left', 'style' => ['fontWeight' => 'bold']],
                            ['id' => 'group_total_count', 'type' => 'binding', 'binding' => 'group.count', 'colspan' => 1, 'align' => 'center', 'format' => 'number', 'style' => ['fontWeight' => 'bold']],
                            ['id' => 'group_total_c3', 'type' => 'text', 'content' => '', 'colspan' => 1],
                            ['id' => 'group_total_c4', 'type' => 'text', 'content' => '', 'colspan' => 1],
                            ['id' => 'group_total_c5', 'type' => 'text', 'content' => '', 'colspan' => 1],
                            ['id' => 'group_total_c6', 'type' => 'text', 'content' => '', 'colspan' => 1],
                            ['id' => 'group_total_c7', 'type' => 'text', 'content' => '', 'colspan' => 1],
                            ['id' => 'group_total_c8', 'type' => 'text', 'content' => '', 'colspan' => 1],
                            ['id' => 'group_total_c9', 'type' => 'text', 'content' => '', 'colspan' => 1],
                            ['id' => 'group_total_c10', 'type' => 'text', 'content' => '', 'colspan' => 1],
                            ['id' => 'group_total_c11', 'type' => 'text', 'content' => '', 'colspan' => 1],
                        ],
                    ],
                    [
                        'id' => 'vip_guests_grand_total_row',
                        'enabledBy' => '',
                        'scope' => 'table',
                        'level' => 0,
                        'className' => 'report-grand-total-row',
                        'cells' => [
                            ['id' => 'grand_total_label', 'type' => 'text', 'content' => 'TỔNG CỘNG', 'colspan' => 1, 'align' => 'left', 'style' => ['fontWeight' => 'bold']],
                            ['id' => 'grand_total_count', 'type' => 'binding', 'binding' => 'aggregate.rows.count', 'colspan' => 1, 'align' => 'center', 'format' => 'number', 'style' => ['fontWeight' => 'bold']],
                            ['id' => 'grand_total_c3', 'type' => 'text', 'content' => '', 'colspan' => 1],
                            ['id' => 'grand_total_c4', 'type' => 'text', 'content' => '', 'colspan' => 1],
                            ['id' => 'grand_total_c5', 'type' => 'text', 'content' => '', 'colspan' => 1],
                            ['id' => 'grand_total_c6', 'type' => 'text', 'content' => '', 'colspan' => 1],
                            ['id' => 'grand_total_c7', 'type' => 'text', 'content' => '', 'colspan' => 1],
                            ['id' => 'grand_total_c8', 'type' => 'text', 'content' => '', 'colspan' => 1],
                            ['id' => 'grand_total_c9', 'type' => 'text', 'content' => '', 'colspan' => 1],
                            ['id' => 'grand_total_c10', 'type' => 'text', 'content' => '', 'colspan' => 1],
                            ['id' => 'grand_total_c11', 'type' => 'text', 'content' => '', 'colspan' => 1],
                        ],
                    ],
                ],
            ]],
            'footer' => [],
        ];
    }

    public function columns(): array
    {
        return [
            ['header' => 'Tên Khách', 'value' => 'row.GuestName', 'width' => '16%', 'align' => 'left'],
            ['header' => 'Tình Trạng', 'value' => 'row.StatusVi', 'width' => '8%', 'align' => 'center'],
            ['header' => 'Đăng Ký', 'value' => 'row.BookingId', 'width' => '6.5%', 'align' => 'center'],
            ['header' => 'Phòng', 'value' => 'row.Room', 'width' => '6.5%', 'align' => 'center'],
            ['header' => 'Loại Khách', 'value' => 'row.GuestType', 'width' => '6.5%', 'align' => 'center'],
            ['header' => 'Ngày Đến', 'value' => 'row.ArrivalDate', 'width' => '9.5%', 'align' => 'center'],
            ['header' => 'Ngày Đi', 'value' => 'row.DepartureDate', 'width' => '9.5%', 'align' => 'center'],
            ['header' => 'Giá Phòng', 'value' => 'row.Rate|number', 'width' => '8%', 'align' => 'right'],
            ['header' => "Người Lớn/\nTrẻ Em", 'value' => 'row.AdultChild', 'width' => '6.5%', 'align' => 'center'],
            ['header' => 'Công Ty', 'value' => 'row.Company', 'width' => '10.5%', 'align' => 'left'],
            ['header' => 'Ghi Chú', 'value' => 'row.Note', 'width' => '12.5%', 'align' => 'left', 'className' => 'note-cell'],
        ];
    }

    public function css(): string
    {
        return <<<'CSS'
.vip-guests-table {
    width: 100%;
    border-collapse: collapse;
    font-family: inherit;
    font-size: 11px;
    line-height: 1.35;
    margin-top: 4px;
}
.vip-guests-table th,
.vip-guests-table td {
    border: 1px solid #c8c8c8;
    padding: 4px 6px;
    vertical-align: middle;
}
.vip-guests-table thead th {
    background-color: #dee2ed;
    color: #0f172a;
    font-weight: bold;
    text-align: center;
    border: 1px solid #c8c8c8;
}
.vip-guests-table .group-title-cell,
.vip-guests-table .group-value-cell {
    color: #ff1414;
    font-weight: bold;
    background-color: #ffffff;
}
.vip-guests-table .pms-group-header td {
    background-color: #ffffff;
    border: 1px solid #c8c8c8;
}
.vip-guests-table .pms-group-footer td,
.vip-guests-table .pms-group-custom-row td,
.vip-guests-table .report-grand-total-row td {
    background-color: #dee2ed;
    font-weight: bold;
    border: 1px solid #c8c8c8;
}
.vip-guests-table .note-cell {
    white-space: pre-wrap;
    line-height: 1.35;
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
        $className = $block['tableClassName'] ?? 'vip-guests-table';

        $html = '<table class="'.$className.'">'."\n";
        $html .= '  <colgroup>'."\n";
        foreach ($columns as $col) {
            $html .= '    <col style="width: '.($col['width'] ?? 'auto').'">'."\n";
        }
        $html .= '  </colgroup>'."\n";

        $html .= '  <thead>'."\n    <tr>\n";
        foreach ($columns as $col) {
            $headerText = nl2br(htmlspecialchars((string) ($col['header'] ?? ''), ENT_QUOTES, 'UTF-8'));
            $html .= '      <th style="text-align: '.($col['align'] ?? 'center').';">'.$headerText.'</th>'."\n";
        }
        $html .= "    </tr>\n  </thead>\n";

        $groups = $block['grouping'] ?? [];
        $primaryGroup = $groups[0] ?? null;
        $groupByAttr = $primaryGroup ? ' data-group-by="'.htmlspecialchars((string) $primaryGroup['field'], ENT_QUOTES, 'UTF-8').'"' : '';

        $html .= '  <tbody class="pms-grouped-rows" data-source="'.($block['dataSource'] ?? 'rows').'"'.$groupByAttr.'>'."\n";

        if ($primaryGroup) {
            $html .= '    <tr class="pms-group-header" data-group-field="'.htmlspecialchars((string) $primaryGroup['field'], ENT_QUOTES, 'UTF-8').'">'."\n";
            if (! empty($primaryGroup['headerCells'])) {
                foreach ($primaryGroup['headerCells'] as $cell) {
                    $colspan = ($cell['colspan'] ?? 1) > 1 ? ' colspan="'.$cell['colspan'].'"' : '';
                    $classAttr = ! empty($cell['className']) ? ' class="'.$cell['className'].'"' : '';
                    $html .= '      <td'.$colspan.$classAttr.'>'.($cell['content'] ?? '').'</td>'."\n";
                }
            } else {
                $html .= '      <td colspan="'.count($columns).'">'.($primaryGroup['label'] ?? '').'</td>'."\n";
            }
            $html .= "    </tr>\n";
        }

        // Detail row
        $html .= "    <tr class=\"pms-detail-row\">\n";
        foreach ($columns as $col) {
            $format = ($col['format'] ?? '') === 'number' ? '|number' : '';
            $classAttr = ! empty($col['className']) ? ' class="'.$col['className'].'"' : '';
            $styleAttr = ' style="text-align: '.($col['align'] ?? 'left').';"';
            $html .= '      <td'.$classAttr.$styleAttr.'>{{'.($col['value'] ?? '').$format.'}}</td>'."\n";
        }
        $html .= "    </tr>\n";

        // Group footer row (subtotal)
        $html .= '    <tr class="pms-group-custom-row pms-group-footer" data-group-level="0">'."\n";
        $html .= '      <td style="font-weight: bold; text-align: left;">TỔNG CỘNG</td>'."\n";
        $html .= '      <td style="font-weight: bold; text-align: center;">{{group.count}}</td>'."\n";
        for ($i = 2; $i < count($columns); $i++) {
            $html .= '      <td></td>'."\n";
        }
        $html .= "    </tr>\n";

        $html .= "  </tbody>\n";

        // Grand total row in tfoot
        $customRows = $block['customRows'] ?? [];
        $tableCustomRows = array_filter($customRows, static fn (array $r): bool => ($r['scope'] ?? 'table') === 'table');
        if (! empty($tableCustomRows)) {
            $html .= "  <tfoot>\n";
            foreach ($tableCustomRows as $crow) {
                $html .= '    <tr class="'.($crow['className'] ?? '').'">'."\n";
                foreach ($crow['cells'] ?? [] as $cell) {
                    $colspan = isset($cell['colspan']) && $cell['colspan'] > 1 ? ' colspan="'.$cell['colspan'].'"' : '';
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
