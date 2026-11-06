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
            'version' => '2.1',
            'content_json' => $blocks,
            'content_html' => $this->compileBlocks($blocks),
            'css' => $this->css(),
        ];
    }

    private function columns(): array
    {
        $definitions = [
            ['CompanyCode', 'Mã', '5%', 'left', 'text'],
            ['CompanyName', 'Công Ty', '12%', 'left', 'text'],
            ['TotalRevenue', 'Doanh Thu', '11%', 'right', 'number'],
            ['RevenuePercentage', '% Doanh Thu', '7%', 'right', 'percent'],
            ['AverageRevenue', 'Doanh Thu TB', '10%', 'right', 'number'],
            ['RoomQty', 'SL Phòng', '6%', 'right', 'number'],
            ['RoomNight', 'Đêm<br>Phòng', '6%', 'right', 'number'],
            ['FocRoomNights', 'Phòng<br>MP', '5%', 'right', 'number'],
            ['HuRoomNights', 'Nội Bộ', '5%', 'right', 'number'],
            ['OccupancyRate', 'Công<br>Suất', '7%', 'right', 'percent'],
            ['GuestQty', 'SL Khách', '6%', 'right', 'number'],
            ['GuestNight', 'Đêm Khách', '6%', 'right', 'number', 'parameters.p_show_detail'],
            ['RoomRevenue', 'Tiền Phòng', '7%', 'right', 'number', 'parameters.p_show_detail'],
            ['ExtraBedRevenue', 'Giá Thêm Giường', '7%', 'right', 'number', 'parameters.p_show_detail'],
        ];

        return array_map(static function (array $column, int $index): array {
            [$field, $header, $width, $align, $format] = $column;

            return [
                'id' => 'company_occupancy_'.$index,
                'field' => $field,
                'header' => $header,
                'value' => 'row.'.$field,
                'width' => $width,
                'align' => $align,
                'format' => $format,
                'visibleWhen' => $column[5] ?? '',
                'visibleWhenMode' => 'truthy',
                'headerStyle' => [
                    'backgroundColor' => '#d9deea',
                    'border' => '1px solid #aeb5c0',
                    'textAlign' => 'center',
                    'fontWeight' => 'bold',
                    'color' => '#000000',
                ],
                'cellStyle' => [
                    'border' => '1px solid #aeb5c0',
                    'textAlign' => $align,
                    'padding' => '3px 2px',
                ],
            ];
        }, $definitions, array_keys($definitions));
    }

    private function blocks(): array
    {
        $columns = $this->columns();
        $totalCells = [
            ['id' => 'tf_1', 'type' => 'text', 'content' => 'Tổng:', 'align' => 'left', 'fontWeight' => 'bold', 'columnId' => $columns[0]['id']],
            ['id' => 'tf_2', 'type' => 'binding', 'binding' => 'aggregate.rows.count', 'format' => 'number', 'align' => 'right', 'fontWeight' => 'bold', 'columnId' => $columns[1]['id']],
            ['id' => 'tf_3', 'type' => 'binding', 'binding' => 'aggregate.rows.sum.TotalRevenue', 'format' => 'number', 'align' => 'right', 'fontWeight' => 'bold', 'columnId' => $columns[2]['id']],
            ['id' => 'tf_4', 'type' => 'text', 'content' => '100%', 'align' => 'right', 'fontWeight' => 'bold', 'columnId' => $columns[3]['id']],
            ['id' => 'tf_5', 'type' => 'binding', 'binding' => 'aggregate.rows.summary_average_revenue', 'format' => 'number', 'align' => 'right', 'fontWeight' => 'bold', 'columnId' => $columns[4]['id']],
            ['id' => 'tf_6', 'type' => 'binding', 'binding' => 'aggregate.rows.sum.RoomQty', 'format' => 'number', 'align' => 'right', 'fontWeight' => 'bold', 'columnId' => $columns[5]['id']],
            ['id' => 'tf_7', 'type' => 'binding', 'binding' => 'aggregate.rows.sum.RoomNight', 'format' => 'number', 'align' => 'right', 'fontWeight' => 'bold', 'columnId' => $columns[6]['id']],
            ['id' => 'tf_8', 'type' => 'binding', 'binding' => 'aggregate.rows.sum.FocRoomNights', 'format' => 'number', 'align' => 'right', 'fontWeight' => 'bold', 'columnId' => $columns[7]['id']],
            ['id' => 'tf_9', 'type' => 'binding', 'binding' => 'aggregate.rows.sum.HuRoomNights', 'format' => 'number', 'align' => 'right', 'fontWeight' => 'bold', 'columnId' => $columns[8]['id']],
            ['id' => 'tf_10', 'type' => 'binding', 'binding' => 'aggregate.rows.summary_occupancy', 'format' => 'percent', 'align' => 'right', 'fontWeight' => 'bold', 'columnId' => $columns[9]['id']],
            ['id' => 'tf_11', 'type' => 'binding', 'binding' => 'aggregate.rows.sum.GuestQty', 'format' => 'number', 'align' => 'right', 'fontWeight' => 'bold', 'columnId' => $columns[10]['id']],
            ['id' => 'tf_12', 'type' => 'binding', 'binding' => 'aggregate.rows.sum.GuestNight', 'format' => 'number', 'align' => 'right', 'fontWeight' => 'bold', 'columnId' => $columns[11]['id']],
            ['id' => 'tf_13', 'type' => 'binding', 'binding' => 'aggregate.rows.sum.RoomRevenue', 'format' => 'number', 'align' => 'right', 'fontWeight' => 'bold', 'columnId' => $columns[12]['id']],
            ['id' => 'tf_14', 'type' => 'binding', 'binding' => 'aggregate.rows.sum.ExtraBedRevenue', 'format' => 'number', 'align' => 'right', 'fontWeight' => 'bold', 'columnId' => $columns[13]['id']],
        ];

        return [
            'header' => [
                ['id' => 'company_occupancy_hotel', 'type' => 'text', 'content' => '<div class="report-header"><div class="hotel-logo">{{hotel.logo}}</div><div class="hotel-information"><div><strong>Địa Chỉ</strong>&nbsp;&nbsp;&nbsp;{{hotel.address}}</div><div><strong>Người Dùng</strong>&nbsp;&nbsp;&nbsp;{{report.generated_by}}<span class="header-date"><strong>Ngày</strong>&nbsp;&nbsp;&nbsp;{{report.generated_date}}</span></div></div></div>'],
                ['id' => 'company_occupancy_title', 'type' => 'text', 'content' => '<h1>BÁO CÁO CÔNG SUẤT CÔNG TY</h1>'],
                ['id' => 'company_occupancy_period', 'type' => 'text', 'content' => '<p class="period"><strong>Ngày:</strong>&nbsp;&nbsp;&nbsp;{{parameters.p_from_date}}&nbsp;&nbsp;&nbsp;&nbsp;~&nbsp;&nbsp;&nbsp;&nbsp;{{parameters.p_to_date}}</p>'],
            ],
            'detail' => [[
                'id' => 'company_occupancy_table',
                'type' => 'table',
                'dataSource' => 'rows',
                'tableType' => 'dynamic',
                'tableStyle' => 'grid',
                'tableClassName' => 'company-occupancy-table',
                'topHeader' => array_map(static fn (array $column, int $index): array => ['id' => 'company_occupancy_index_'.$index, 'columnId' => $column['id'], 'content' => (string) ($index + 1), 'align' => 'center'], $columns, array_keys($columns)),
                'columns' => $columns,
                'groups' => [[
                    'id' => 'company_occupancy_group',
                    'field' => 'GroupHeaderId',
                    'label' => '{{row.GroupHeader}}',
                    'className' => 'group-header-cell',
                    'enabledBy' => 'parameters.p_is_grouped',
                    'sort' => 'ASC',
                    'headerCells' => [[
                        'id' => 'company_occupancy_group_header',
                        'type' => 'text',
                        'content' => '{{row.GroupHeader}}',
                        'colspan' => 14,
                        'autoColspan' => true,
                        'align' => 'left',
                        'fontWeight' => 'bold',
                    ]],
                ]],
                'customRows' => [[
                    'id' => 'company_occupancy_total',
                    'scope' => 'table',
                    'position' => 'footer',
                    'className' => 'total',
                    'cells' => $totalCells,
                ], [
                    'id' => 'company_occupancy_group_total',
                    'scope' => 'group',
                    'level' => 0,
                    'enabledBy' => 'parameters.p_is_grouped',
                    'className' => 'group-total',
                    'cells' => [
                        ['id' => 'gt_1', 'type' => 'text', 'content' => 'Tổng:', 'colspan' => 2, 'align' => 'left', 'fontWeight' => 'bold', 'columnId' => $columns[0]['id']],
                        ['id' => 'gt_2', 'type' => 'binding', 'binding' => 'group.sum.TotalRevenue', 'format' => 'number', 'align' => 'right', 'fontWeight' => 'bold', 'columnId' => $columns[2]['id']],
                        ['id' => 'gt_3', 'type' => 'binding', 'binding' => 'group.sum.RevenuePercentage', 'format' => 'percent', 'align' => 'right', 'fontWeight' => 'bold', 'columnId' => $columns[3]['id']],
                        ['id' => 'gt_4', 'type' => 'text', 'content' => '', 'align' => 'right', 'columnId' => $columns[4]['id']],
                        ['id' => 'gt_5', 'type' => 'binding', 'binding' => 'group.sum.RoomQty', 'format' => 'number', 'align' => 'right', 'fontWeight' => 'bold', 'columnId' => $columns[5]['id']],
                        ['id' => 'gt_6', 'type' => 'binding', 'binding' => 'group.sum.RoomNight', 'format' => 'number', 'align' => 'right', 'fontWeight' => 'bold', 'columnId' => $columns[6]['id']],
                        ['id' => 'gt_7', 'type' => 'binding', 'binding' => 'group.sum.FocRoomNights', 'format' => 'number', 'align' => 'right', 'fontWeight' => 'bold', 'columnId' => $columns[7]['id']],
                        ['id' => 'gt_8', 'type' => 'binding', 'binding' => 'group.sum.HuRoomNights', 'format' => 'number', 'align' => 'right', 'fontWeight' => 'bold', 'columnId' => $columns[8]['id']],
                        ['id' => 'gt_9', 'type' => 'text', 'content' => '', 'align' => 'right', 'columnId' => $columns[9]['id']],
                        ['id' => 'gt_10', 'type' => 'binding', 'binding' => 'group.sum.GuestQty', 'format' => 'number', 'align' => 'right', 'fontWeight' => 'bold', 'columnId' => $columns[10]['id']],
                        ['id' => 'gt_11', 'type' => 'binding', 'binding' => 'group.sum.GuestNight', 'format' => 'number', 'align' => 'right', 'fontWeight' => 'bold', 'columnId' => $columns[11]['id']],
                        ['id' => 'gt_12', 'type' => 'binding', 'binding' => 'group.sum.RoomRevenue', 'format' => 'number', 'align' => 'right', 'fontWeight' => 'bold', 'columnId' => $columns[12]['id']],
                        ['id' => 'gt_13', 'type' => 'binding', 'binding' => 'group.sum.ExtraBedRevenue', 'format' => 'number', 'align' => 'right', 'fontWeight' => 'bold', 'columnId' => $columns[13]['id']],
                    ],
                ]],
            ]],
            'footer' => [[
                'id' => 'company_occupancy_note',
                'type' => 'text',
                'content' => '<div class="report-note"><p><strong>Note (Ghi chú):</strong></p><p>3. %OCC: 4/ Room Available</p><p>4. Room Night: Room Sale + FOC + HU</p><p>6. Average Room Rate (Doanh thu TB): 8/4</p><p>7. Average Room Rate w/o HU,FOC (Doanh thu TB không b/g FOC,HU): 8 / Room Sales</p><p>8. Room Revenue (DT phòng): RM,ER</p><p>9. FB Revenue (DT FB): Doanh thu từ bộ phận nhà hàng</p><p>10. Other Revenue (DT khác): Các doanh thu khác không bao gồm mục 8,9</p><p>11. Revenue (Doanh thu): Mục 8+9+10</p></div>',
            ]],
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
                if (($block['type'] ?? '') === 'text') $html .= (string) ($block['content'] ?? '');
                if (($block['type'] ?? '') === 'table') $html .= $this->compileTable($block);
            }
            $html .= '</div>';
        }

        return $html;
    }

    private function columnRuntimeAttributes(array $column): string
    {
        $id = (string) ($column['id'] ?? '');
        if ($id === '') {
            return '';
        }

        $attributes = ' data-pms-column-id="'.htmlspecialchars($id, ENT_QUOTES, 'UTF-8').'"';
        if (! empty($column['visibleWhen'])) {
            $attributes .= ' data-visible-by="'.htmlspecialchars((string) $column['visibleWhen'], ENT_QUOTES, 'UTF-8').'"';
            $attributes .= ' data-visible-when="'.(($column['visibleWhenMode'] ?? 'truthy') === 'falsy' ? 'falsy' : 'truthy').'"';
        }

        return $attributes;
    }

    private function linkedColumnRuntimeAttributes(array $columns, array $cell): string
    {
        $columnId = (string) ($cell['columnId'] ?? '');
        foreach ($columns as $column) {
            if (($column['id'] ?? '') === $columnId) {
                return $this->columnRuntimeAttributes($column);
            }
        }

        return '';
    }

    private function compileTable(array $block): string
    {
        $columns = $block['columns'] ?? [];
        $groups = $block['groups'] ?? [];
        $customRows = $block['customRows'] ?? [];
        $html = '<table class="'.htmlspecialchars((string) ($block['tableClassName'] ?? 'company-occupancy-table'), ENT_QUOTES, 'UTF-8').'" style="width:100%;border-collapse:collapse"><colgroup>';
        foreach ($columns as $column) $html .= '<col'.$this->columnRuntimeAttributes($column).' style="width:'.htmlspecialchars((string) ($column['width'] ?? 'auto'), ENT_QUOTES, 'UTF-8').'">';
        $html .= '</colgroup><thead><tr>';
        foreach ($columns as $column) $html .= '<th'.$this->columnRuntimeAttributes($column).'>'.($column['header'] ?? '').'</th>';
        $html .= '</tr>';
        if (! empty($block['topHeader'])) {
            $html .= '<tr class="index-row">';
            foreach ($block['topHeader'] as $cell) $html .= '<th'.$this->linkedColumnRuntimeAttributes($columns, $cell).'>'.($cell['content'] ?? '').'</th>';
            $html .= '</tr>';
        }
        $html .= '</thead>';
        if ($groups !== []) {
            $html .= '<tbody class="pms-grouped-rows" data-source="'.htmlspecialchars((string) ($block['dataSource'] ?? 'rows'), ENT_QUOTES, 'UTF-8').'" data-group-configured="1" data-group-by="'.htmlspecialchars((string) ($groups[0]['field'] ?? ''), ENT_QUOTES, 'UTF-8').'">';
            foreach ($groups as $index => $group) {
                $enabledBy = ! empty($group['enabledBy']) ? ' data-group-enabled-by="'.htmlspecialchars((string) $group['enabledBy'], ENT_QUOTES, 'UTF-8').'"' : '';
                $html .= '<tr class="pms-group-header" data-group-level="'.$index.'" data-group-field="'.htmlspecialchars((string) ($group['field'] ?? ''), ENT_QUOTES, 'UTF-8').'" data-group-sort="'.htmlspecialchars((string) ($group['sort'] ?? 'ASC'), ENT_QUOTES, 'UTF-8').'"'.$enabledBy.'>';
                foreach ($group['headerCells'] ?? [] as $cell) {
                    $autoColspan = ! empty($cell['autoColspan']) ? ' data-pms-auto-colspan="1"' : '';
                    $html .= '<td'.$autoColspan.' colspan="'.max(1, (int) ($cell['colspan'] ?? count($columns))).'" class="'.htmlspecialchars((string) ($group['className'] ?? ''), ENT_QUOTES, 'UTF-8').'">'.($cell['content'] ?? '').'</td>';
                }
                $html .= '</tr>';
            }
        } else {
            $html .= '<tbody>';
        }
        $html .= '<tr class="pms-detail-row"'.($groups === [] ? ' data-source="'.htmlspecialchars((string) ($block['dataSource'] ?? 'rows'), ENT_QUOTES, 'UTF-8').'"' : '').'>';
        foreach ($columns as $column) {
            $value = 'row.'.($column['field'] ?? '');
            if (($column['format'] ?? '') === 'number') $value .= '|number';
            if (($column['format'] ?? '') === 'percent') $value .= '|percent';
            $html .= '<td'.$this->columnRuntimeAttributes($column).' class="'.htmlspecialchars((string) ($column['align'] ?? 'left'), ENT_QUOTES, 'UTF-8').'">{{'.$value.'}}</td>';
        }
        $html .= '</tr>';
        foreach (array_filter($customRows, static fn (array $row): bool => ($row['scope'] ?? 'table') === 'group') as $row) {
            $enabledBy = ! empty($row['enabledBy']) ? ' data-visible-by="'.htmlspecialchars((string) $row['enabledBy'], ENT_QUOTES, 'UTF-8').'"' : '';
            $html .= '<tr class="pms-group-custom-row '.htmlspecialchars((string) ($row['className'] ?? ''), ENT_QUOTES, 'UTF-8').'" data-group-level="'.max(0, (int) ($row['level'] ?? 0)).'"'.$enabledBy.'>';
            foreach ($row['cells'] ?? [] as $cell) {
                $content = ($cell['type'] ?? '') === 'binding' ? '{{'.($cell['binding'] ?? '').(! empty($cell['format']) ? '|'.$cell['format'] : '').'}}' : (string) ($cell['content'] ?? '');
                $html .= '<td'.$this->linkedColumnRuntimeAttributes($columns, $cell).' colspan="'.max(1, (int) ($cell['colspan'] ?? 1)).'" class="'.htmlspecialchars((string) ($cell['align'] ?? 'left'), ENT_QUOTES, 'UTF-8').'">'.$content.'</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody>';
        foreach (array_filter($customRows, static fn (array $row): bool => ($row['scope'] ?? 'table') !== 'group') as $row) {
            $html .= '<tfoot><tr class="total">';
            foreach ($row['cells'] ?? [] as $cell) {
                $content = ($cell['type'] ?? '') === 'binding' ? '{{'.($cell['binding'] ?? '').(! empty($cell['format']) ? '|'.$cell['format'] : '').'}}' : (string) ($cell['content'] ?? '');
                $align = ! empty($cell['align']) ? ' class="'.htmlspecialchars((string) $cell['align'], ENT_QUOTES, 'UTF-8').'"' : '';
                $html .= '<td'.$this->linkedColumnRuntimeAttributes($columns, $cell).' colspan="'.max(1, (int) ($cell['colspan'] ?? 1)).'"'.$align.'>'.$content.'</td>';
            }
            $html .= '</tr></tfoot>';
        }

        return $html.'</table>';
    }

    public function css(): string
    {
        return '.report-header{display:flex;justify-content:space-between;align-items:flex-start;min-height:54px}.hotel-logo img{max-width:115px;max-height:52px;object-fit:contain}.hotel-information{text-align:left;font-size:9px;line-height:1.8;color:#000;margin-top:7px}.header-date{display:inline-block;margin-left:80px}h1{text-align:center;font-size:19px;font-weight:bold;margin:8px 0 4px;color:#000}.period{text-align:center;font-size:10px;margin:0 0 10px;color:#000}.company-occupancy-table{width:100%!important;table-layout:fixed;border-collapse:collapse;font-size:8.5px;border:1px solid #aeb5c0!important}.company-occupancy-table th,.company-occupancy-table td{border:1px solid #aeb5c0!important;padding:3px 2px;vertical-align:middle}.company-occupancy-table th{background:#d9deea!important;text-align:center;font-weight:bold;color:#000}.company-occupancy-table .index-row th{font-size:8px;padding:2px 1px}.company-occupancy-table td.right{text-align:right}.company-occupancy-table td.left{text-align:left}.company-occupancy-table tfoot td{background:#d9deea!important;font-weight:bold}.report-note{font-size:8.5px;margin-top:10px;line-height:1.45;color:#000}.report-note p{margin:1px 0}@media print{thead{display:table-header-group}tr{break-inside:avoid}}';
    }
};
