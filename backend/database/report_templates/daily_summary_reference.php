<?php

return new class
{
    public function definition(): array
    {
        $blocks = $this->blocks();

        return [
            'report' => 'DAILY_SUMMARY_REFERENCE',
            'name' => 'Báo cáo tổng hợp ngày',
            'page_size' => 'A4', 'page_orientation' => 'portrait',
            'margin_top' => 10, 'margin_right' => 10, 'margin_bottom' => 10, 'margin_left' => 10,
            'version' => '1.0', 'content_json' => $blocks, 'content_html' => $this->compileBlocks($blocks), 'css' => $this->css(),
        ];
    }

    private function columns(): array
    {
        return [
            ['field' => 'SortOrder', 'header' => 'STT', 'width' => '10%', 'align' => 'center', 'format' => 'text'],
            ['field' => 'Content', 'header' => 'NỘI DUNG', 'width' => '36%', 'align' => 'left', 'format' => 'text'],
            ['field' => 'DateAmount', 'header' => 'NGÀY', 'width' => '14%', 'align' => 'right', 'format' => 'number'],
            ['field' => 'MonthAmount', 'header' => 'LŨY KẾ THÁNG {{parameters.p_month_label}}', 'width' => '16%', 'align' => 'right', 'format' => 'number'],
            ['field' => 'PlanAmount', 'header' => 'DỰ KIẾN THÁNG', 'width' => '12%', 'align' => 'right', 'format' => 'number'],
            ['field' => 'Rate', 'header' => 'TỈ LỆ HOÀN THÀNH', 'width' => '12%', 'align' => 'right', 'format' => 'text'],
        ];
    }

    private function blocks(): array
    {
        $columns = array_map(static function (array $column, int $index): array {
            return [
                'id' => 'daily_summary_'.$index,
                'field' => $column['field'], 'header' => $column['header'], 'value' => 'row.'.$column['field'],
                'width' => $column['width'], 'align' => $column['align'], 'format' => $column['format'],
                'headerStyle' => ['backgroundColor' => '#d9deea', 'border' => '1px solid #aeb5c0', 'textAlign' => 'center', 'fontWeight' => 'bold'],
                'cellStyle' => ['border' => '1px solid #aeb5c0', 'textAlign' => $column['align'], 'padding' => '5px'],
            ];
        }, $this->columns(), array_keys($this->columns()));

        return [
            'header' => [
                ['id' => 'daily_summary_hotel', 'type' => 'text', 'content' => '<div class="report-header"><div class="hotel-name">{{hotel.name}}</div><div class="hotel-information">Địa chỉ: {{hotel.address}}<br>Ngày in: {{report.generated_at}}</div></div>'],
                ['id' => 'daily_summary_title', 'type' => 'text', 'content' => '<h1>BÁO CÁO TỔNG HỢP NGÀY</h1>'],
                ['id' => 'daily_summary_period', 'type' => 'text', 'content' => '<p class="period">Ngày: {{parameters.p_date}}</p>'],
            ],
            'detail' => [[
                'id' => 'daily_summary_table', 'type' => 'table', 'dataSource' => 'rows', 'tableType' => 'dynamic',
                'tableStyle' => 'grid', 'tableClassName' => 'daily-summary-table', 'columns' => $columns,
            ]],
            'footer' => [['id' => 'daily_summary_signatures', 'type' => 'text', 'content' => '<div class="signatures"><span>Người lập biểu</span><span>Giám đốc khách sạn</span></div>']],
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
        $html .= '</colgroup><thead><tr>';
        foreach ($columns as $column) $html .= '<th>'.($column['header'] ?? '').'</th>';
        $html .= '</tr></thead><tbody><tr class="pms-detail-row" data-source="'.($block['dataSource'] ?? 'rows').'">';
        foreach ($columns as $column) {
            $value = (string) ($column['value'] ?? '');
            if (($column['format'] ?? '') === 'number') $value .= '|number';
            $html .= '<td class="'.htmlspecialchars((string) ($column['align'] ?? 'left'), ENT_QUOTES, 'UTF-8').'">{{'.$value.'}}</td>';
        }
        return $html.'</tr></tbody></table>';
    }

    private function colgroup(): string
    {
        return implode('', array_map(static fn (array $column): string => '<col style="width:'.$column['width'].'">', $this->columns()));
    }

    public function css(): string
    {
        return '.report-header{display:flex;justify-content:space-between;font-size:10px;border-bottom:1px solid #111;padding-bottom:5px}.hotel-information{text-align:right;line-height:1.4}h1{text-align:center;font-size:17px;margin:15px 0 3px}.period{text-align:center;font-style:italic;font-size:10px}.daily-summary-table{width:100%;table-layout:fixed;border-collapse:collapse;font-size:9px}.daily-summary-table th,.daily-summary-table td{border:1px solid #aeb5c0;padding:5px;vertical-align:middle}.daily-summary-table th{background:#d9deea;text-align:center}.daily-summary-table td.right{text-align:right}.daily-summary-table td.center{text-align:center}.daily-summary-table td.left{text-align:left}.signatures{display:flex;justify-content:space-around;margin-top:38px;font-size:10px}@media print{thead{display:table-header-group}tr{break-inside:avoid}}';
    }
};
