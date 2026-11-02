<?php

return new class
{
    public function definition(): array
    {
        $blocks = $this->blocks();

        return [
            'report' => 'COMPANY_OCCUPANCY_REFERENCE',
            'name' => 'Báo cáo công suất công ty',
            'page_size' => 'A4',
            'page_orientation' => 'landscape',
            'margin_top' => 8,
            'margin_right' => 6,
            'margin_bottom' => 8,
            'margin_left' => 6,
            'version' => '1.0',
            'content_json' => $blocks,
            'content_html' => $this->compileBlocks($blocks),
            'css' => $this->css(),
        ];
    }

    private function columns(): array
    {
        $definitions = [
            ['CompanyCode', 'Mã', '7%', 'left', 'text'],
            ['CompanyName', 'Công Ty', '21%', 'left', 'text'],
            ['OccupancyRate', 'Công suất', '6%', 'right', 'percent'],
            ['RoomNight', 'Đêm<br>phòng', '5%', 'right', 'number'],
            ['GuestQty', 'SL khách', '5%', 'right', 'number'],
            ['ActualADR', 'Giá phòng trung<br>bình', '9%', 'right', 'number'],
            ['RackADR', 'Giá phòng TB<br>(không FOC/Giảm<br>giá)', '12%', 'right', 'number'],
            ['RoomRevenue', 'Doanh thu phòng', '10%', 'right', 'number'],
            ['FbRevenue', 'Doanh thu F&B', '7%', 'right', 'number'],
            ['OtherRevenue', 'Doanh thu khác', '9%', 'right', 'number'],
            ['TotalRevenue', 'Doanh Thu', '9%', 'right', 'number'],
        ];

        return array_map(static function (array $column, int $index): array {
            [$field, $header, $width, $align, $format] = $column;
            $cellStyle = [
                'border' => '1px solid #718096',
                'textAlign' => $align,
                'padding' => '3px 2px',
            ];
            if ($field === 'CompanyCode') {
                $cellStyle['color'] = '#16a34a';
                $cellStyle['fontWeight'] = 'bold';
            }

            return [
                'id' => 'company_occupancy_'.$index,
                'field' => $field,
                'header' => $header,
                'value' => 'row.'.$field,
                'width' => $width,
                'align' => $align,
                'format' => $format,
                'headerStyle' => [
                    'backgroundColor' => '#eaedf3',
                    'border' => '1px solid #718096',
                    'textAlign' => 'center',
                    'fontWeight' => 'bold',
                    'color' => '#000000',
                ],
                'cellStyle' => $cellStyle,
            ];
        }, $definitions, array_keys($definitions));
    }

    private function blocks(): array
    {
        return [
            'header' => [
                [
                    'id' => 'company_occupancy_hotel',
                    'type' => 'text',
                    'content' => '<div class="report-header">'
                        . '<div class="hotel-logo">{{hotel.logo}}</div>'
                        . '<div class="hotel-information">Địa chỉ: {{hotel.address}}<br>Nhân viên: {{report.generated_by}}&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Ngày: {{report.generated_date}}</div>'
                        . '</div>',
                    'style' => [
                        'textAlign' => 'left',
                        'fontSize' => '13px',
                        'color' => '#1e293b',
                    ],
                ],
                [
                    'id' => 'company_occupancy_title',
                    'type' => 'text',
                    'content' => '<h1>BÁO CÁO CÔNG SUẤT CÔNG TY</h1>',
                    'style' => [
                        'textAlign' => 'center',
                        'fontSize' => '16px',
                        'fontWeight' => 'bold',
                    ],
                ],
                [
                    'id' => 'company_occupancy_period',
                    'type' => 'text',
                    'content' => '<p class="period">Ngày: {{parameters.p_from_date}} ~ {{parameters.p_to_date}}</p>',
                    'style' => [
                        'textAlign' => 'center',
                        'fontSize' => '10px',
                    ],
                ],
            ],
            'detail' => [[
                'id' => 'company_occupancy_table',
                'type' => 'table',
                'dataSource' => 'rows',
                'tableType' => 'dynamic',
                'tableStyle' => 'grid',
                'tableClassName' => 'company-occupancy-table',
                'topHeader' => array_map(static fn (int $index): array => ['content' => (string) ($index + 1), 'align' => 'center'], array_keys($this->columns())),
                'columns' => $this->columns(),
                'groups' => [
                    [
                        'id' => 'group_header_1',
                        'field' => 'GroupHeader',
                        'sort' => 'ASC',
                        'enabledBy' => 'parameters.p_is_grouped',
                        'className' => 'group-header-cell',
                        'label' => '{{row.GroupHeader}}',
                    ],
                ],
                'groupBy' => 'GroupHeader',
                'customRows' => [
                    [
                        'id' => 'group_footer_1',
                        'scope' => 'group',
                        'level' => 0,
                        'enabledBy' => 'parameters.p_is_grouped',
                        'className' => 'pms-group-footer',
                        'cells' => [
                            ['id' => 'gf_1', 'type' => 'text', 'content' => 'Tổng nhóm:', 'colspan' => 2, 'align' => 'right', 'className' => 'group-footer-label'],
                            ['id' => 'gf_2', 'type' => 'text', 'content' => '', 'colspan' => 1, 'align' => 'center'],
                            ['id' => 'gf_3', 'type' => 'binding', 'binding' => 'group.sum.RoomNight', 'format' => 'number', 'colspan' => 1, 'align' => 'right'],
                            ['id' => 'gf_4', 'type' => 'binding', 'binding' => 'group.sum.GuestQty', 'format' => 'number', 'colspan' => 1, 'align' => 'right'],
                            ['id' => 'gf_5', 'type' => 'text', 'content' => '', 'colspan' => 2, 'align' => 'center'],
                            ['id' => 'gf_6', 'type' => 'binding', 'binding' => 'group.sum.RoomRevenue', 'format' => 'number', 'colspan' => 1, 'align' => 'right'],
                            ['id' => 'gf_7', 'type' => 'binding', 'binding' => 'group.sum.FbRevenue', 'format' => 'number', 'colspan' => 1, 'align' => 'right'],
                            ['id' => 'gf_8', 'type' => 'binding', 'binding' => 'group.sum.OtherRevenue', 'format' => 'number', 'colspan' => 1, 'align' => 'right'],
                            ['id' => 'gf_9', 'type' => 'binding', 'binding' => 'group.sum.TotalRevenue', 'format' => 'number', 'colspan' => 1, 'align' => 'right'],
                        ],
                    ],
                    [
                        'id' => 'table_footer_1',
                        'scope' => 'table',
                        'position' => 'footer',
                        'className' => 'total',
                        'cells' => [
                            ['id' => 'tf_1', 'type' => 'text', 'content' => 'Tổng:', 'align' => 'left', 'fontWeight' => 'bold'],
                            ['id' => 'tf_2', 'type' => 'binding', 'binding' => 'aggregate.rows.count', 'format' => 'number', 'align' => 'right', 'fontWeight' => 'bold'],
                            ['id' => 'tf_3', 'type' => 'binding', 'binding' => 'aggregate.rows.summary_occupancy', 'format' => 'percent', 'align' => 'right', 'fontWeight' => 'bold'],
                            ['id' => 'tf_4', 'type' => 'binding', 'binding' => 'aggregate.rows.sum.RoomNight', 'format' => 'number', 'align' => 'right', 'fontWeight' => 'bold'],
                            ['id' => 'tf_5', 'type' => 'binding', 'binding' => 'aggregate.rows.sum.GuestQty', 'format' => 'number', 'align' => 'right', 'fontWeight' => 'bold'],
                            ['id' => 'tf_6', 'type' => 'binding', 'binding' => 'aggregate.rows.summary_actual_adr', 'format' => 'number', 'align' => 'right', 'fontWeight' => 'bold'],
                            ['id' => 'tf_7', 'type' => 'binding', 'binding' => 'aggregate.rows.summary_rack_adr', 'format' => 'number', 'align' => 'right', 'fontWeight' => 'bold'],
                            ['id' => 'tf_8', 'type' => 'binding', 'binding' => 'aggregate.rows.sum.RoomRevenue', 'format' => 'number', 'align' => 'right', 'fontWeight' => 'bold'],
                            ['id' => 'tf_9', 'type' => 'binding', 'binding' => 'aggregate.rows.sum.FbRevenue', 'format' => 'number', 'align' => 'right', 'fontWeight' => 'bold'],
                            ['id' => 'tf_10', 'type' => 'binding', 'binding' => 'aggregate.rows.sum.OtherRevenue', 'format' => 'number', 'align' => 'right', 'fontWeight' => 'bold'],
                            ['id' => 'tf_11', 'type' => 'binding', 'binding' => 'aggregate.rows.sum.TotalRevenue', 'format' => 'number', 'align' => 'right', 'fontWeight' => 'bold'],
                        ],
                    ],
                ],
                'style' => [
                    'width' => '100%',
                ],
            ]],
            'footer' => [
                [
                    'id' => 'company_occupancy_note',
                    'type' => 'text',
                    'content' => '<div class="report-note">'
                        . '<p><strong>Note (Ghi chú):</strong></p>'
                        . '<p>3. %OCC: 4/ Room Availabe</p>'
                        . '<p>4. Room Night: Room sale + FOC + HU</p>'
                        . '<p>6. Average Room Rate (Doanh thu TB): 8/4</p>'
                        . '<p>7. Average Room Rate w/o HU,FOC (Doanh thu TB không b/g FOC,HU): 8 / Room sales</p>'
                        . '<p>8. Room Revenue (DT phòng): RM,ER</p>'
                        . '<p>9. FB Revenue (Doanh thu FB): Doanh thu từ bộ phận nhà hàng</p>'
                        . '<p>10. Other Revenue (Doanh thu khác): Các doanh thu khác không bao gồm mục 8,9</p>'
                        . '<p>11. Revenue (Doanh thu): Mục 8+9+10</p>'
                        . '</div>',
                    'style' => [
                        'fontSize' => '8.5px',
                        'marginTop' => '10px',
                    ],
                ],
            ],
        ];
    }

    public function html(): string
    {
        return $this->compileBlocks($this->blocks());
    }

    private function compileBlocks(array $bands): string
    {
        $html = '';
        foreach (['header', 'detail', 'footer'] as $band) {
            $html .= '<div class="report-'.$band.'-band">';
            foreach ($bands[$band] ?? [] as $block) {
                if (($block['type'] ?? '') === 'text') {
                    $html .= (string) ($block['content'] ?? '');
                }
                if (($block['type'] ?? '') === 'table') {
                    $html .= $this->compileTable($block);
                }
            }
            $html .= '</div>';
        }

        return $html;
    }

    private function compileTable(array $block): string
    {
        $columns = $block['columns'] ?? [];
        $html = '<table class="'.htmlspecialchars((string) ($block['tableClassName'] ?? 'company-occupancy-table'), ENT_QUOTES, 'UTF-8').'" style="width: 100%; border-collapse: collapse;"><colgroup>';
        foreach ($columns as $column) {
            $html .= '<col style="width:'.htmlspecialchars((string) ($column['width'] ?? 'auto'), ENT_QUOTES, 'UTF-8').'">';
        }
        $html .= '</colgroup><thead>';

        // Tầng 1: Tên cột
        $html .= '<tr>';
        foreach ($columns as $column) {
            $html .= '<th>'.($column['header'] ?? '').'</th>';
        }
        $html .= '</tr>';

        // Tầng 2: Đánh số thứ tự 1..11
        if (! empty($block['topHeader'])) {
            $html .= '<tr class="index-row">';
            foreach ($block['topHeader'] as $cell) {
                $html .= '<th>'.($cell['content'] ?? '').'</th>';
            }
            $html .= '</tr>';
        }
        $html .= '</thead>';

        // Tbody hỗ trợ cả Flat và Grouped Rows
        $html .= '<tbody class="pms-grouped-rows" data-source="rows" data-group-by="GroupHeader" data-group-configured="1">';
        $html .= '<tr class="pms-group-header" data-group-field="GroupHeader" data-group-enabled-by="parameters.p_is_grouped">';
        $html .= '<td colspan="'.count($columns).'" class="group-header-cell">{{row.GroupHeader}}</td>';
        $html .= '</tr>';

        $html .= '<tr class="pms-detail-row" data-source="'.($block['dataSource'] ?? 'rows').'">';
        foreach ($columns as $column) {
            $field = (string) ($column['field'] ?? '');
            $value = 'row.'.$field;
            if (($column['format'] ?? '') === 'number') {
                $value .= '|number';
            }
            if (($column['format'] ?? '') === 'percent') {
                $value .= '|percent';
            }
            $extraClass = $field === 'CompanyCode' ? ' company-code' : '';
            $html .= '<td class="'.htmlspecialchars((string) ($column['align'] ?? 'left'), ENT_QUOTES, 'UTF-8').$extraClass.'">{{'.$value.'}}</td>';
        }
        $html .= '</tr>';

        $html .= '<tr class="pms-group-footer" data-group-enabled-by="parameters.p_is_grouped">';
        $html .= '<td colspan="2" class="group-footer-label">Tổng nhóm:</td>';
        $html .= '<td></td>';
        $html .= '<td class="right">{{group.sum.RoomNight|number}}</td>';
        $html .= '<td class="right">{{group.sum.GuestQty|number}}</td>';
        $html .= '<td></td><td></td>';
        $html .= '<td class="right">{{group.sum.RoomRevenue|number}}</td>';
        $html .= '<td class="right">{{group.sum.FbRevenue|number}}</td>';
        $html .= '<td class="right">{{group.sum.OtherRevenue|number}}</td>';
        $html .= '<td class="right">{{group.sum.TotalRevenue|number}}</td>';
        $html .= '</tr>';
        $html .= '</tbody>';

        $tableCustomRows = array_filter($block['customRows'] ?? [], static fn (array $r): bool => ($r['scope'] ?? 'table') === 'table');
        foreach ($tableCustomRows as $row) {
            $html .= '<tfoot><tr class="total">';
            foreach ($row['cells'] ?? [] as $cell) {
                $content = ($cell['type'] ?? '') === 'binding'
                    ? '{{'.($cell['binding'] ?? '').(! empty($cell['format']) ? '|'.$cell['format'] : '').'}}'
                    : (string) ($cell['content'] ?? '');
                $alignClass = ! empty($cell['align']) ? ' class="'.$cell['align'].'"' : '';
                $colspan = max(1, (int) ($cell['colspan'] ?? 1));
                $html .= '<td colspan="'.$colspan.'"'.$alignClass.'>'.$content.'</td>';
            }
            $html .= '</tr></tfoot>';
        }

        return $html.'</table>';
    }

    public function css(): string
    {
        return '.report-header { display: flex; justify-content: space-between; align-items: flex-start; min-height: 50px; }
.hotel-logo img { max-width: 140px; max-height: 50px; object-fit: contain; }
.hotel-information { text-align: right; font-size: 9px; line-height: 1.6; color: #000; }
h1 { text-align: center; font-size: 16px; font-weight: bold; margin: 10px 0 4px; color: #000; }
.period { text-align: center; font-size: 10px; margin: 0 0 10px; color: #000; }
.company-occupancy-table { width: 100% !important; table-layout: fixed; border-collapse: collapse; font-size: 8.5px; border: 1px solid #718096 !important; }
.company-occupancy-table th, .company-occupancy-table td { border: 1px solid #718096 !important; padding: 3px 2px; vertical-align: middle; }
.company-occupancy-table th { background: #eaedf3 !important; text-align: center; font-weight: bold; color: #000; }
.company-occupancy-table .index-row th { font-size: 8px; font-weight: bold; background: #eaedf3 !important; color: #000; padding: 2px 1px; }
.company-occupancy-table td.right { text-align: right; }
.company-occupancy-table td.left { text-align: left; }
.company-occupancy-table td.company-code { color: #16a34a !important; font-weight: bold; }
.company-occupancy-table td.group-header-cell { background: #f1f5f9; font-weight: bold; text-align: left; padding: 4px 6px; font-size: 9px; }
.company-occupancy-table tr.pms-group-footer td { background: #f8fafc; font-weight: bold; padding: 3px 2px; }
.company-occupancy-table td.group-footer-label { text-align: right; font-weight: bold; }
.company-occupancy-table tfoot td, .company-occupancy-table tr.total td { background: #eaedf3 !important; font-weight: bold; }
.report-note { font-size: 8.5px; margin-top: 10px; line-height: 1.5; color: #000; }
.report-note p { margin: 1px 0; }
@media print { thead { display: table-header-group; } tr { break-inside: avoid; } }';
    }
};
