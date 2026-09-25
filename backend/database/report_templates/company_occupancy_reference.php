<?php

return new class
{
    public function definition(): array
    {
        $blocks = $this->blocks();

        return [
            'report' => 'COMPANY_OCCUPANCY_REFERENCE',
            'name' => 'Báo cáo công suất công ty',
            'page_size' => 'A4', 'page_orientation' => 'landscape',
            'margin_top' => 6, 'margin_right' => 4, 'margin_bottom' => 6, 'margin_left' => 4,
            'version' => '1.0', 'content_json' => $blocks, 'content_html' => $this->compileBlocks($blocks), 'css' => $this->css(),
        ];
    }

    private function columns(): array
    {
        $definitions = [
            ['CompanyCode', 'Mã', '7%', 'left', 'text'], ['CompanyName', 'Công Ty', '17%', 'left', 'text'],
            ['OccupancyRate', 'Công suất', '7%', 'right', 'percent'], ['RoomNight', 'Đêm phòng', '7%', 'right', 'number'],
            ['GuestQty', 'SL khách', '7%', 'right', 'number'], ['ActualADR', 'Giá phòng TB', '11%', 'right', 'number'],
            ['RackADR', 'Giá phòng TB không FOC', '13%', 'right', 'number'], ['RoomRevenue', 'Doanh thu phòng', '10%', 'right', 'number'],
            ['FbRevenue', 'Doanh thu F&B', '9%', 'right', 'number'], ['OtherRevenue', 'Doanh thu khác', '9%', 'right', 'number'],
            ['TotalRevenue', 'Doanh Thu', '10%', 'right', 'number'],
        ];

        return array_map(static function (array $column, int $index): array {
            [$field, $header, $width, $align, $format] = $column;
            return [
                'id' => 'company_occupancy_'.$index, 'field' => $field, 'header' => $header,
                'value' => 'row.'.$field, 'width' => $width, 'align' => $align, 'format' => $format,
                'headerStyle' => ['backgroundColor' => '#d9deea', 'border' => '1px solid #aeb5c0', 'textAlign' => 'center', 'fontWeight' => 'bold'],
                'cellStyle' => ['border' => '1px solid #aeb5c0', 'textAlign' => $align, 'padding' => '3px 2px'],
            ];
        }, $definitions, array_keys($definitions));
    }

    private function blocks(): array
    {
        return [
            'header' => [
                ['id' => 'company_occupancy_hotel', 'type' => 'text', 'content' => '<div class="report-header"><div class="hotel-logo">{{hotel.logo}}</div><div class="hotel-information">Địa chỉ: {{hotel.address}}<br>Người dùng: {{report.generated_by}}<br>Ngày: {{report.generated_at}}</div></div>'],
                ['id' => 'company_occupancy_title', 'type' => 'text', 'content' => '<h1>BÁO CÁO CÔNG SUẤT CÔNG TY</h1>'],
                ['id' => 'company_occupancy_period', 'type' => 'text', 'content' => '<p class="period">Từ ngày {{parameters.p_from_date}} đến ngày {{parameters.p_to_date}}</p>'],
            ],
            'detail' => [[
                'id' => 'company_occupancy_table', 'type' => 'table', 'dataSource' => 'rows', 'tableType' => 'dynamic',
                'tableStyle' => 'grid', 'tableClassName' => 'company-occupancy-table',
                'topHeader' => array_map(static fn (int $index): array => ['content' => (string) ($index + 1), 'align' => 'center'], array_keys($this->columns())),
                'columns' => $this->columns(),
                'customRows' => [[
                    'scope' => 'table', 'position' => 'footer',
                    'cells' => [
                        ['type' => 'text', 'content' => 'Tổng', 'colspan' => 2, 'align' => 'right'], ['type' => 'text', 'content' => '-', 'align' => 'right'],
                        ['type' => 'binding', 'binding' => 'aggregate.rows.sum.RoomNight', 'format' => 'number', 'align' => 'right'],
                        ['type' => 'binding', 'binding' => 'aggregate.rows.sum.GuestQty', 'format' => 'number', 'align' => 'right'], ['type' => 'text', 'content' => '', 'colspan' => 2],
                        ['type' => 'binding', 'binding' => 'aggregate.rows.sum.RoomRevenue', 'format' => 'number', 'align' => 'right'],
                        ['type' => 'binding', 'binding' => 'aggregate.rows.sum.FbRevenue', 'format' => 'number', 'align' => 'right'],
                        ['type' => 'binding', 'binding' => 'aggregate.rows.sum.OtherRevenue', 'format' => 'number', 'align' => 'right'],
                        ['type' => 'binding', 'binding' => 'aggregate.rows.sum.TotalRevenue', 'format' => 'number', 'align' => 'right'],
                    ],
                ]],
            ]],
            'footer' => [
                ['id' => 'company_occupancy_note', 'type' => 'text', 'content' => '<p class="note">Ghi chú: Công suất = Đêm phòng / số phòng khả dụng trong kỳ. Doanh thu = Tiền phòng + F&amp;B + Dịch vụ khác.</p>'],
                ['id' => 'company_occupancy_signatures', 'type' => 'text', 'content' => '<div class="signatures"><span>Người lập biểu</span><span>Giám đốc khách sạn</span></div>'],
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
                if (($block['type'] ?? '') === 'text') $html .= (string) ($block['content'] ?? '');
                if (($block['type'] ?? '') === 'table') $html .= $this->compileTable($block);
            }
            $html .= '</div>';
        }
        return $html;
    }

    private function compileTable(array $block): string
    {
        $columns = $block['columns'] ?? [];
        $html = '<table class="'.htmlspecialchars((string) ($block['tableClassName'] ?? 'report-table'), ENT_QUOTES, 'UTF-8').'"><colgroup>';
        foreach ($columns as $column) $html .= '<col style="width:'.htmlspecialchars((string) ($column['width'] ?? 'auto'), ENT_QUOTES, 'UTF-8').'">';
        $html .= '</colgroup><thead>';
        if (! empty($block['topHeader'])) {
            $html .= '<tr class="index-row">'; foreach ($block['topHeader'] as $cell) $html .= '<th>'.($cell['content'] ?? '').'</th>'; $html .= '</tr>';
        }
        $html .= '<tr>'; foreach ($columns as $column) $html .= '<th>'.($column['header'] ?? '').'</th>'; $html .= '</tr></thead><tbody><tr class="pms-detail-row" data-source="'.($block['dataSource'] ?? 'rows').'">';
        foreach ($columns as $column) {
            $value = (string) ($column['value'] ?? '');
            if (($column['format'] ?? '') === 'number') $value .= '|number';
            $suffix = ($column['format'] ?? '') === 'percent' ? '%' : '';
            $html .= '<td class="'.htmlspecialchars((string) ($column['align'] ?? 'left'), ENT_QUOTES, 'UTF-8').'">{{'.$value.'}}'.$suffix.'</td>';
        }
        $html .= '</tr></tbody>';
        foreach ($block['customRows'] ?? [] as $row) {
            $html .= '<tfoot><tr class="total">';
            foreach ($row['cells'] ?? [] as $cell) {
                $content = ($cell['type'] ?? '') === 'binding' ? '{{'.($cell['binding'] ?? '').(! empty($cell['format']) ? '|'.$cell['format'] : '').'}}' : (string) ($cell['content'] ?? '');
                $html .= '<td colspan="'.max(1, (int) ($cell['colspan'] ?? 1)).'">'.$content.'</td>';
            }
            $html .= '</tr></tfoot>';
        }
        return $html.'</table>';
    }

    private function colgroup(): string
    {
        return implode('', array_map(static fn (array $column): string => '<col style="width:'.$column['width'].'">', $this->columns()));
    }

    public function css(): string
    {
        return '.report-header{display:flex;justify-content:space-between;min-height:50px}.hotel-logo img{max-width:120px;max-height:45px}.hotel-information{text-align:right;font-size:9px;line-height:1.5}h1{text-align:center;font-size:16px;margin:8px 0 3px}.period{text-align:center;font-style:italic;font-size:9px}.company-occupancy-table{width:100%;table-layout:fixed;border-collapse:collapse;font-size:8px}.company-occupancy-table th,.company-occupancy-table td{border:1px solid #aeb5c0;padding:3px 2px;vertical-align:middle}.company-occupancy-table th{background:#d9deea;text-align:center}.company-occupancy-table .index-row th{font-size:7px;color:#64748b;padding:1px}.company-occupancy-table td.right{text-align:right}.company-occupancy-table td.left{text-align:left}.company-occupancy-table td.total,.company-occupancy-table tfoot td{background:#d9deea;font-weight:bold}.note{font-size:8px;margin-top:10px}.signatures{display:flex;justify-content:space-around;margin-top:24px;font-size:9px}@media print{thead{display:table-header-group}tr{break-inside:avoid}}';
    }
};
