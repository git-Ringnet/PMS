<?php

return new class
{
    public function definition(): array
    {
        $blocks = $this->blocks();

        return [
            'report' => 'SALESPERSON_REVENUE_DETAIL_REFERENCE',
            'name' => 'Báo cáo chi tiết doanh thu theo người bán',
            'page_size' => 'A4', 'page_orientation' => 'landscape',
            'margin_top' => 6, 'margin_right' => 4, 'margin_bottom' => 6, 'margin_left' => 4,
            'version' => '1.0', 'content_json' => $blocks, 'content_html' => $this->compileBlocks($blocks), 'css' => $this->css(),
        ];
    }

    private function columns(): array
    {
        $definitions = [
            ['BookingCode', 'Mã ĐK', '6%', 'center', 'text'], ['BookingName', 'Tên Đăng Ký', '10%', 'left', 'text'],
            ['ArrivalDate', 'Ngày Đến', '5%', 'center', 'text'], ['DepartureDate', 'Ngày Đi', '5%', 'center', 'text'],
            ['RoomNights', 'Đêm phòng', '5%', 'right', 'number'], ['FocRoomNights', 'FOC', '4%', 'right', 'number'],
            ['GuestQty', 'SL khách', '5%', 'right', 'number'], ['CompanyName', 'Công Ty', '10%', 'left', 'text'],
            ['MarketSegment', 'Thị trường', '7%', 'center', 'text'], ['RoomRevenue', 'Doanh thu phòng', '9%', 'right', 'number'],
            ['FbRevenue', 'Doanh thu F&B', '8%', 'right', 'number'], ['OtherRevenue', 'Doanh thu khác', '8%', 'right', 'number'],
            ['TotalRevenue', 'Tổng Doanh Thu', '10%', 'right', 'number'], ['SalesPersonName', 'Người Bán', '8%', 'left', 'text'],
        ];

        return array_map(static function (array $column, int $index): array {
            [$field, $header, $width, $align, $format] = $column;
            return [
                'id' => 'salesperson_detail_'.$index, 'field' => $field, 'header' => $header,
                'value' => 'row.'.$field, 'width' => $width, 'align' => $align, 'format' => $format,
                'headerStyle' => ['backgroundColor' => '#d9deea', 'border' => '1px solid #aeb5c0', 'textAlign' => 'center', 'fontWeight' => 'bold'],
                'cellStyle' => ['border' => '1px solid #aeb5c0', 'textAlign' => $align, 'padding' => '2px 1px'],
            ];
        }, $definitions, array_keys($definitions));
    }

    private function blocks(): array
    {
        return [
            'header' => [
                ['id' => 'salesperson_detail_hotel', 'type' => 'text', 'content' => '<div class="report-header"><div class="hotel-logo">{{hotel.logo}}</div><div class="hotel-information">Địa chỉ: {{hotel.address}}<br>Người dùng: {{report.generated_by}}<br>Ngày: {{report.generated_at}}</div></div>'],
                ['id' => 'salesperson_detail_title', 'type' => 'text', 'content' => '<h1>BÁO CÁO CHI TIẾT DOANH THU THEO NGƯỜI BÁN</h1>'],
                ['id' => 'salesperson_detail_period', 'type' => 'text', 'content' => '<p class="period">Từ ngày {{parameters.p_from_date}} đến ngày {{parameters.p_to_date}}</p>'],
            ],
            'detail' => [[
                'id' => 'salesperson_detail_table', 'type' => 'table', 'dataSource' => 'rows', 'tableType' => 'dynamic',
                'tableStyle' => 'grid', 'tableClassName' => 'salesperson-detail-table', 'columns' => $this->columns(),
                'customRows' => [['scope' => 'table', 'position' => 'footer', 'cells' => [
                    ['type' => 'text', 'content' => 'Tổng: {{aggregate.rows.count}}', 'colspan' => 9, 'align' => 'right'],
                    ['type' => 'binding', 'binding' => 'aggregate.rows.sum.RoomRevenue', 'format' => 'number', 'align' => 'right'],
                    ['type' => 'binding', 'binding' => 'aggregate.rows.sum.FbRevenue', 'format' => 'number', 'align' => 'right'],
                    ['type' => 'binding', 'binding' => 'aggregate.rows.sum.OtherRevenue', 'format' => 'number', 'align' => 'right'],
                    ['type' => 'binding', 'binding' => 'aggregate.rows.sum.TotalRevenue', 'format' => 'number', 'align' => 'right'], ['type' => 'text', 'content' => ''],
                ]]],
            ]],
            'footer' => [['id' => 'salesperson_detail_signatures', 'type' => 'text', 'content' => '<div class="signatures"><span>Người lập biểu</span><span>Giám đốc khách sạn</span></div>']],
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
        $html .= '</colgroup><thead><tr>'; foreach ($columns as $column) $html .= '<th>'.($column['header'] ?? '').'</th>'; $html .= '</tr></thead><tbody><tr class="pms-detail-row" data-source="'.($block['dataSource'] ?? 'rows').'">';
        foreach ($columns as $column) {
            $value = (string) ($column['value'] ?? ''); if (($column['format'] ?? '') === 'number') $value .= '|number';
            $html .= '<td class="'.htmlspecialchars((string) ($column['align'] ?? 'left'), ENT_QUOTES, 'UTF-8').'">{{'.$value.'}}</td>';
        }
        $html .= '</tr></tbody>';
        foreach ($block['customRows'] ?? [] as $row) { $html .= '<tfoot><tr class="total">'; foreach ($row['cells'] ?? [] as $cell) { $content = ($cell['type'] ?? '') === 'binding' ? '{{'.($cell['binding'] ?? '').(! empty($cell['format']) ? '|'.$cell['format'] : '').'}}' : (string) ($cell['content'] ?? ''); $html .= '<td colspan="'.max(1, (int) ($cell['colspan'] ?? 1)).'">'.$content.'</td>'; } $html .= '</tr></tfoot>'; }
        return $html.'</table>';
    }

    private function colgroup(): string
    {
        return implode('', array_map(static fn (array $column): string => '<col style="width:'.$column['width'].'">', $this->columns()));
    }

    public function css(): string
    {
        return '.report-header{display:flex;justify-content:space-between;min-height:50px}.hotel-logo img{max-width:120px;max-height:45px}.hotel-information{text-align:right;font-size:9px;line-height:1.5}h1{text-align:center;font-size:16px;margin:8px 0 3px}.period{text-align:center;font-style:italic;font-size:9px}.salesperson-detail-table{width:100%;table-layout:fixed;border-collapse:collapse;font-size:7.5px;line-height:1.1}.salesperson-detail-table th,.salesperson-detail-table td{border:1px solid #aeb5c0;padding:2px 1px;vertical-align:middle;overflow-wrap:anywhere}.salesperson-detail-table th{background:#d9deea;text-align:center}.salesperson-detail-table td.right{text-align:right}.salesperson-detail-table td.center{text-align:center}.salesperson-detail-table td.left{text-align:left}.salesperson-detail-table tfoot td{background:#d9deea;font-weight:bold}.signatures{display:flex;justify-content:space-around;margin-top:24px;font-size:9px}@media print{thead{display:table-header-group}tr{break-inside:avoid}}';
    }
};
