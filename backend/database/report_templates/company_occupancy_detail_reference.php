<?php

return new class
{
    public function definition(): array
    {
        $blocks = $this->blocks();

        return [
            'report' => 'COMPANY_OCCUPANCY_DETAIL_REFERENCE',
            'name' => 'Báo cáo chi tiết công suất công ty',
            'page_size' => 'A4',
            'page_orientation' => 'portrait',
            'margin_top' => 6,
            'margin_right' => 4,
            'margin_bottom' => 6,
            'margin_left' => 4,
            'version' => '1.1',
            'content_json' => $blocks,
            'content_html' => $this->compileBlocks($blocks),
            'css' => $this->css(),
        ];
    }

    private function columns(): array
    {
        $definitions = [
            ['RowNumber', 'STT', '4%', 'center', 'number'],
            ['BookingCode', 'Mã ĐK', '8%', 'center', 'text'],
            ['GuestName', 'Tên ĐK', '20%', 'left', 'text'],
            ['ArrivalDate', 'Ngày Đến', '9%', 'center', 'text'],
            ['DepartureDate', 'Ngày Đi', '9%', 'center', 'text'],
            ['RoomNight', 'Đêm<br>Phòng', '6%', 'right', 'number'],
            ['GuestNight', 'Đêm<br>Khách', '6%', 'right', 'number'],
            ['RoomRevenue', 'DT Tiền<br>Phòng', '11%', 'right', 'number'],
            ['ExtraBedRevenue', 'DT Thêm<br>Giường', '9%', 'right', 'number'],
            ['ChildSurchargeRevenue', 'Phụ Thu<br>Trẻ Em', '9%', 'right', 'number'],
            ['TotalRevenue', 'Tổng DT', '9%', 'right', 'number'],
        ];

        return array_map(static function (array $column, int $index): array {
            [$field, $header, $width, $align, $format] = $column;

            return [
                'id' => 'company_occupancy_detail_'.$index,
                'field' => $field,
                'header' => $header,
                'value' => 'row.'.$field,
                'width' => $width,
                'align' => $align,
                'format' => $format,
                'headerStyle' => ['backgroundColor' => '#d9deea', 'border' => '1px solid #aeb5c0', 'textAlign' => 'center', 'fontWeight' => 'bold', 'padding' => '3px 2px'],
                'cellStyle' => ['border' => '1px solid #aeb5c0', 'textAlign' => $align, 'padding' => '3px 2px'],
            ];
        }, $definitions, array_keys($definitions));
    }

    private function blocks(): array
    {
        $columns = $this->columns();

        return [
            'header' => [
                ['id' => 'company_occupancy_detail_hotel', 'type' => 'text', 'content' => '<div class="report-header"><div class="hotel-logo">{{hotel.logo}}</div><div class="hotel-information"><div><strong>Địa chỉ:</strong>&nbsp;&nbsp;&nbsp;{{hotel.address}}</div><div><strong>Nhân viên:</strong>&nbsp;&nbsp;&nbsp;{{report.generated_by}}<span class="header-date"><strong>Ngày</strong>&nbsp;&nbsp;&nbsp;{{report.generated_date}}</span></div></div></div>'],
                ['id' => 'company_occupancy_detail_title', 'type' => 'text', 'content' => '<h1>BÁO CÁO CHI TIẾT CÔNG SUẤT CÔNG TY</h1>'],
                ['id' => 'company_occupancy_detail_period', 'type' => 'text', 'content' => '<p class="period"><strong>Ngày:</strong>&nbsp;&nbsp;&nbsp;{{parameters.p_from_date}}&nbsp;&nbsp;&nbsp;&nbsp;~&nbsp;&nbsp;&nbsp;&nbsp;{{parameters.p_to_date}}</p>'],
            ],
            'detail' => [[
                'id' => 'company_occupancy_detail_table',
                'type' => 'table',
                'dataSource' => 'rows',
                'tableType' => 'dynamic',
                'tableStyle' => 'grid',
                'tableClassName' => 'company-occupancy-detail-table',
                'columns' => $columns,
                'groupBy' => 'CompanyCode',
                'customRows' => [[
                    'id' => 'company_occupancy_detail_total',
                    'scope' => 'table',
                    'position' => 'footer',
                    'className' => 'total',
                    'cells' => [
                        ['id' => 'tf_1', 'type' => 'text', 'content' => 'Tổng Giai Đoạn {{aggregate.rows.count}} BK', 'colspan' => 5, 'align' => 'right'],
                        ['id' => 'tf_2', 'type' => 'binding', 'binding' => 'aggregate.rows.sum.RoomNight', 'format' => 'number', 'align' => 'right'],
                        ['id' => 'tf_3', 'type' => 'binding', 'binding' => 'aggregate.rows.sum.GuestNight', 'format' => 'number', 'align' => 'right'],
                        ['id' => 'tf_4', 'type' => 'binding', 'binding' => 'aggregate.rows.sum.RoomRevenue', 'format' => 'number', 'align' => 'right'],
                        ['id' => 'tf_5', 'type' => 'binding', 'binding' => 'aggregate.rows.sum.ExtraBedRevenue', 'format' => 'number', 'align' => 'right'],
                        ['id' => 'tf_6', 'type' => 'binding', 'binding' => 'aggregate.rows.sum.ChildSurchargeRevenue', 'format' => 'number', 'align' => 'right'],
                        ['id' => 'tf_7', 'type' => 'binding', 'binding' => 'aggregate.rows.sum.TotalRevenue', 'format' => 'number', 'align' => 'right'],
                    ],
                ]],
            ]],
            'footer' => [],
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
        $html = '<table class="'.htmlspecialchars((string) ($block['tableClassName'] ?? 'report-table'), ENT_QUOTES, 'UTF-8').'" style="width:100%;border-collapse:collapse"><colgroup>';
        foreach ($columns as $column) $html .= '<col style="width:'.htmlspecialchars((string) ($column['width'] ?? 'auto'), ENT_QUOTES, 'UTF-8').'">';
        $html .= '</colgroup><thead><tr>';
        foreach ($columns as $column) $html .= '<th>'.($column['header'] ?? '').'</th>';
        $html .= '</tr></thead><tbody class="pms-grouped-rows" data-source="rows" data-group-by="CompanyCode">';
        $html .= '<tr class="pms-group-header"><td colspan="'.count($columns).'" class="group-header-cell">{{row.CompanyName}}</td></tr>';
        $html .= '<tr class="pms-detail-row" data-source="rows">';
        foreach ($columns as $column) {
            $value = 'row.'.($column['field'] ?? '');
            if (($column['format'] ?? '') === 'number') $value .= '|number';
            $html .= '<td class="'.htmlspecialchars((string) ($column['align'] ?? 'left'), ENT_QUOTES, 'UTF-8').'">{{'.$value.'}}</td>';
        }
        $html .= '</tr><tr class="pms-group-footer"><td colspan="5" class="group-footer-label">Tổng</td><td class="right">{{group.sum.RoomNight|number}}</td><td class="right">{{group.sum.GuestNight|number}}</td><td class="right">{{group.sum.RoomRevenue|number}}</td><td class="right">{{group.sum.ExtraBedRevenue|number}}</td><td class="right">{{group.sum.ChildSurchargeRevenue|number}}</td><td class="right">{{group.sum.TotalRevenue|number}}</td></tr></tbody>';
        foreach ($block['customRows'] ?? [] as $row) {
            $html .= '<tfoot><tr class="total">';
            foreach ($row['cells'] ?? [] as $cell) {
                $content = ($cell['type'] ?? '') === 'binding' ? '{{'.($cell['binding'] ?? '').(! empty($cell['format']) ? '|'.$cell['format'] : '').'}}' : (string) ($cell['content'] ?? '');
                $align = ! empty($cell['align']) ? ' class="'.htmlspecialchars((string) $cell['align'], ENT_QUOTES, 'UTF-8').'"' : '';
                $html .= '<td colspan="'.max(1, (int) ($cell['colspan'] ?? 1)).'"'.$align.'>'.$content.'</td>';
            }
            $html .= '</tr></tfoot>';
        }

        return $html.'</table>';
    }

    public function css(): string
    {
        return '.report-header{display:flex;justify-content:space-between;align-items:flex-start;min-height:54px}.hotel-logo img{max-width:115px;max-height:52px;object-fit:contain}.hotel-information{text-align:left;font-size:9px;line-height:1.8;color:#000;margin-top:7px}.header-date{display:inline-block;margin-left:80px}h1{text-align:center;font-size:18px;font-weight:bold;margin:8px 0 4px;color:#000}.period{text-align:center;font-size:10px;margin:0 0 10px;color:#000}.company-occupancy-detail-table{width:100%!important;table-layout:fixed;border-collapse:collapse;font-size:8.5px;line-height:1.15}.company-occupancy-detail-table th,.company-occupancy-detail-table td{border:1px solid #aeb5c0!important;padding:3px 2px;vertical-align:middle;overflow-wrap:anywhere}.company-occupancy-detail-table th{background:#d9deea!important;text-align:center;font-weight:bold}.company-occupancy-detail-table td.right{text-align:right}.company-occupancy-detail-table td.center{text-align:center}.company-occupancy-detail-table td.left{text-align:left}.company-occupancy-detail-table td.group-header-cell{background:#fff!important;font-weight:bold;text-align:left;padding:4px 3px}.company-occupancy-detail-table tr.pms-group-footer td,.company-occupancy-detail-table tfoot td{background:#d9deea!important;font-weight:bold}.company-occupancy-detail-table td.group-footer-label{text-align:right}@media print{thead{display:table-header-group}tr{break-inside:avoid}}';
    }
};
