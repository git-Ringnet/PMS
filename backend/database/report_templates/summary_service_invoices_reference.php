<?php

return new class
{
    public function definition(): array
    {
        $blocks = $this->blocks();

        return [
            'report' => 'SUMMARY_SERVICE_INVOICES_REFERENCE',
            'name' => 'Báo cáo hóa đơn dịch vụ tổng hợp',
            'page_size' => 'A4', 'page_orientation' => 'landscape',
            'margin_top' => 8, 'margin_right' => 5, 'margin_bottom' => 8, 'margin_left' => 5,
            'version' => '1.0', 'content_json' => $blocks, 'content_html' => $this->compileBlocks($blocks), 'css' => $this->css(),
        ];
    }

    private function blocks(): array
    {
        return [
            'header' => [[
                'id' => 'summary_service_header', 'type' => 'text',
                'content' => '<div class="report-header"><div class="hotel-logo">{{hotel.logo}}</div><div class="hotel-information">Địa chỉ: {{hotel.address}}<br>Người dùng: {{report.generated_by}}<br>Ngày: {{report.generated_at}}</div></div><h1>BÁO CÁO HÓA ĐƠN DỊCH VỤ TỔNG HỢP</h1><p class="period">Ngày: {{parameters.p_from_date}} ~ {{parameters.p_to_date}}</p>',
            ]],
            'detail' => [[
                'id' => 'table',
                'type' => 'table',
                'dataSource' => 'rows',
                'tableType' => 'dynamic',
                'tableClassName' => 'summary-service-invoices-table',
                'groups' => [
                    ['id' => 'revenue_group', 'field' => 'RevenueGroupName', 'label' => 'Nhóm doanh thu: {{row.RevenueGroupName}}'],
                    ['id' => 'service_group', 'field' => 'ServiceGroupHeader', 'label' => '{{row.ServiceGroupHeader}}', 'enabledBy' => 'parameters.p_group_by_service'],
                    ['id' => 'date_group', 'field' => 'DateGroupHeader', 'label' => 'Ngày: {{row.DateGroupHeader}}', 'enabledBy' => 'parameters.p_group_by_date'],
                ],
                'columns' => array_map(fn ($f, $l) => ['field' => $f, 'header' => $l, 'value' => 'row.'.$f, 'format' => $f === 'Amount' ? 'number' : 'text'], $this->fields(), $this->labels()),
                'customRows' => [[
                    'scope' => 'group', 'level' => 1,
                    'cells' => [
                        ['type' => 'text', 'colspan' => 6, 'content' => 'Tổng dịch vụ:', 'align' => 'right', 'fontWeight' => 'bold'],
                        ['type' => 'binding', 'binding' => 'group.sum.Amount', 'format' => 'number', 'align' => 'right', 'fontWeight' => 'bold'],
                        ['colspan' => 4, 'content' => ''],
                    ],
                ], [
                    'scope' => 'table',
                    'cells' => [
                        ['type' => 'text', 'colspan' => 6, 'content' => 'Tổng:', 'align' => 'right', 'fontWeight' => 'bold'],
                        ['type' => 'binding', 'binding' => 'aggregate.rows.sum.Amount', 'format' => 'number', 'align' => 'right', 'fontWeight' => 'bold'],
                        ['colspan' => 4, 'content' => ''],
                    ],
                ]],
            ]],
            'footer' => [
                ['id' => 'summary_service_summary_title', 'type' => 'text', 'content' => '<h3>Tổng hợp doanh thu</h3>'],
                [
                'id' => 'summary_service_summary', 'type' => 'table', 'dataSource' => 'service_summary',
                'tableType' => 'dynamic', 'tableClassName' => 'summary-service-summary-table',
                'columns' => [
                    ['field' => 'Label', 'header' => 'Doanh Thu', 'value' => 'row.Label', 'format' => 'text'],
                    ['field' => 'Amount', 'header' => 'Tổng', 'value' => 'row.Amount', 'format' => 'number'],
                ],
                ],
            ],
        ];
    }

    private function fields(): array
    {
        return ['BookingCode','RoomNumber','ArrivalDate','DepartureDate','GuestName','Description','Amount','PaymentMethod','CompanyName','OpenTime','Note'];
    }

    private function labels(): array
    {
        return ['Mã ĐK','Phòng','Ngày Đến','Ngày Đi','Tên Khách','Mô Tả','Doanh Thu','HTTT','Công Ty','Giờ','Ghi chú'];
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
                $html .= $this->compileBlock($block);
            }
            $html .= '</div>';
        }

        return $html;
    }

    private function compileBlock(array $block): string
    {
        if (in_array($block['type'] ?? '', ['text', 'divider'], true)) {
            return (string) ($block['content'] ?? '');
        }

        return ($block['type'] ?? '') === 'table' ? $this->compileTable($block) : '';
    }

    private function compileTable(array $block): string
    {
        $columns = $block['columns'] ?? [];
        $groups = $block['groups'] ?? [];
        $class = htmlspecialchars((string) ($block['tableClassName'] ?? 'report-table'), ENT_QUOTES, 'UTF-8');
        $html = '<table class="'.$class.'"><colgroup>';
        foreach ($columns as $column) {
            $html .= '<col style="width:'.htmlspecialchars((string) ($column['width'] ?? 'auto'), ENT_QUOTES, 'UTF-8').'">';
        }
        $html .= '</colgroup><thead>';
        if (! empty($block['topHeader'])) {
            $html .= '<tr class="index-row">';
            foreach ($block['topHeader'] as $cell) {
                $html .= '<th>'.($cell['content'] ?? '').'</th>';
            }
            $html .= '</tr>';
        }
        $html .= '<tr>';
        foreach ($columns as $column) {
            $html .= '<th>'.($column['header'] ?? '').'</th>';
        }
        $html .= '</tr></thead>';

        $source = htmlspecialchars((string) ($block['dataSource'] ?? 'rows'), ENT_QUOTES, 'UTF-8');
        $html .= '<tbody'.($groups ? ' class="pms-grouped-rows" data-source="'.$source.'" data-group-configured="1"' : '').'>';
        foreach ($groups as $level => $group) {
            $enabledBy = ! empty($group['enabledBy']) ? ' data-group-enabled-by="'.htmlspecialchars((string) $group['enabledBy'], ENT_QUOTES, 'UTF-8').'"' : '';
            $html .= '<tr class="pms-group-header" data-group-level="'.$level.'" data-group-field="'.htmlspecialchars((string) ($group['field'] ?? ''), ENT_QUOTES, 'UTF-8').'"'.$enabledBy.'><td colspan="'.count($columns).'">'.($group['label'] ?? '').'</td></tr>';
        }
        $html .= '<tr class="pms-detail-row"'.($groups ? '' : ' data-source="'.$source.'"').'>';
        foreach ($columns as $column) {
            $value = (string) ($column['value'] ?? '');
            if (($column['format'] ?? '') === 'number' && ! str_ends_with($value, '|number')) {
                $value .= '|number';
            }
            $html .= '<td class="'.htmlspecialchars((string) ($column['align'] ?? 'left'), ENT_QUOTES, 'UTF-8').'">{{'.$value.'}}</td>';
        }
        $html .= '</tr>';
        foreach ($block['customRows'] ?? [] as $row) {
            if (($row['scope'] ?? 'table') === 'table') {
                continue;
            }
            $html .= $this->compileCustomRow($row, 'pms-'.$row['scope'].'-custom-row', $row['scope'] === 'group');
        }
        $html .= '</tbody>';

        $tableRows = array_filter($block['customRows'] ?? [], static fn (array $row): bool => ($row['scope'] ?? 'table') === 'table');
        if ($tableRows !== []) {
            $html .= '<tfoot>';
            foreach ($tableRows as $row) {
                $html .= $this->compileCustomRow($row, (string) ($row['className'] ?? 'total'));
            }
            $html .= '</tfoot>';
        }

        return $html.'</table>';
    }

    private function compileCustomRow(array $row, string $class, bool $withLevel = false): string
    {
        $level = $withLevel ? ' data-group-level="'.max(0, (int) ($row['level'] ?? 0)).'"' : '';
        $html = '<tr class="'.htmlspecialchars($class, ENT_QUOTES, 'UTF-8').'"'.$level.'>';
        foreach ($row['cells'] ?? [] as $cell) {
            $style = $this->inlineStyle($cell['style'] ?? []);
            if (! empty($cell['align'])) {
                $style = 'text-align: '.htmlspecialchars((string) $cell['align'], ENT_QUOTES, 'UTF-8').';'.$style;
            }
            $html .= '<td colspan="'.max(1, (int) ($cell['colspan'] ?? 1)).'" style="'.$style.'">'.$this->cellContent($cell).'</td>';
        }
        return $html.'</tr>';
    }

    private function cellContent(array $cell): string
    {
        if (($cell['type'] ?? '') === 'binding' || ! empty($cell['binding'])) {
            return '{{'.($cell['binding'] ?? '').(! empty($cell['format']) ? '|'.$cell['format'] : '').'}}';
        }
        return (string) ($cell['content'] ?? '');
    }

    private function inlineStyle(array $style): string
    {
        foreach (['align' => 'textAlign', 'fontWeight' => 'fontWeight'] as $source => $target) {
            if (isset($style[$source]) && ! isset($style[$target])) {
                $style[$target] = $style[$source];
            }
        }
        $declarations = [];
        foreach ($style as $property => $value) {
            if (! is_scalar($value) || (string) $value === '') {
                continue;
            }
            $property = strtolower((string) preg_replace('/([a-z])([A-Z])/', '$1-$2', (string) $property));
            $declarations[] = $property.': '.$value;
        }
        return implode(';', $declarations);
    }

    public function css(): string
    {
        return '.report-header{display:flex;justify-content:space-between;min-height:55px}.hotel-logo{width:30%}.hotel-logo img{max-width:120px;max-height:48px}.hotel-information{width:70%;text-align:right;font-size:9px;line-height:1.5}h1{text-align:center;font-size:17px;margin:10px 0 3px}h3{text-align:center;margin:14px 0 5px}p.period{text-align:center;font-style:italic;font-size:10px}table{width:100%;border-collapse:collapse;font-size:8px;table-layout:fixed}th,td{border:1px solid #aeb5c0;padding:3px 2px}th{background:#d9deea;text-align:center}.pms-group-header td{background:#f1f5f9;color:#b82c2c;font-weight:bold;text-align:left}.pms-group-custom-row td{background:#eef2f7;font-weight:bold}.total td,.summary th,.summary td{background:#d9deea;font-weight:bold}.summary{width:38%;margin:0 auto;font-size:9px}@media print{thead{display:table-header-group}}';
    }
};
