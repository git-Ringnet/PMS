<?php

return new class
{
    public function definition(): array
    {
        $blocks = $this->blocks();
        return ['report' => 'DEPOSITS_SUMMARY_REFERENCE', 'name' => 'Báo cáo tiền đặt cọc', 'page_size' => 'A4', 'page_orientation' => 'landscape', 'margin_top' => 8, 'margin_right' => 5, 'margin_bottom' => 8, 'margin_left' => 5, 'version' => '1.0', 'content_json' => $blocks, 'content_html' => $this->compileBlocks($blocks), 'css' => $this->css()];
    }

    private function fields(): array { return ['MaDatCoc','MTT','PaymentDate','TimePayment','BookingRoomCode','BookingName','BusinessName','ArrivalDate','DepartureDate','Amount','PaymentMethodName','Description','Username']; }
    private function labels(): array { return ['Mã Đặt Cọc','Mã TT','Ngày Đặt Cọc','Giờ','Mã ĐK/Phòng','Tên Đăng Ký','Công Ty','Ngày Đến','Ngày Đi','Tổng','HTTT','Ghi Chú','Người Dùng']; }
    private function blocks(): array
    {
        return [
            'header' => [['id' => 'deposit_header', 'type' => 'text', 'content' => '<div class="report-header"><div class="hotel-logo">{{hotel.logo}}</div><div class="hotel-information">Địa chỉ: {{hotel.address}}<br>Nhân viên: {{report.generated_by}}<br>Ngày in: {{report.generated_at}}</div></div><h1>BÁO CÁO TIỀN ĐẶT CỌC</h1><p class="period">Ngày: {{parameters.p_from_date}} ~ {{parameters.p_to_date}}</p>']],
            'detail' => [[
                'id' => 'table',
                'type' => 'table',
                'dataSource' => 'rows',
                'tableType' => 'dynamic',
                'tableClassName' => 'deposits-summary-table',
                'groups' => [
                    ['id' => 'deposit_group', 'field' => 'DepositGroup', 'label' => 'Trạng thái: {{row.DepositGroup}}'],
                    ['id' => 'payment_method_group', 'field' => 'PaymentMethodName', 'label' => 'Hình thức: {{row.PaymentMethodName}}'],
                ],
                'columns' => array_map(fn ($f, $l) => ['field' => $f, 'header' => $l, 'value' => 'row.'.$f, 'format' => $f === 'Amount' ? 'number' : 'text'], $this->fields(), $this->labels()),
                'customRows' => [
                    [
                        'scope' => 'group',
                        'level' => 1,
                        'cells' => [
                            ['type' => 'text', 'colspan' => 9, 'content' => 'Tổng {{row.PaymentMethodName}}:', 'align' => 'right', 'fontWeight' => 'bold'],
                            ['type' => 'binding', 'binding' => 'group.sum.Amount', 'format' => 'number', 'align' => 'right', 'fontWeight' => 'bold'],
                            ['colspan' => 3, 'content' => ''],
                        ],
                    ],
                    [
                        'scope' => 'table',
                        'cells' => [
                            ['type' => 'text', 'colspan' => 9, 'content' => 'Tổng cộng:', 'align' => 'right', 'fontWeight' => 'bold'],
                            ['type' => 'binding', 'binding' => 'aggregate.rows.sum.Amount', 'format' => 'number', 'align' => 'right', 'fontWeight' => 'bold'],
                            ['colspan' => 3, 'content' => ''],
                        ],
                    ],
                ],
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
        $groups = $block['groups'] ?? [];
        $html = '<table class="'.htmlspecialchars((string) ($block['tableClassName'] ?? 'report-table'), ENT_QUOTES, 'UTF-8').'"><colgroup>';
        foreach ($columns as $column) $html .= '<col style="width:'.htmlspecialchars((string) ($column['width'] ?? 'auto'), ENT_QUOTES, 'UTF-8').'">';
        $html .= '</colgroup><thead><tr>';
        foreach ($columns as $column) $html .= '<th>'.($column['header'] ?? '').'</th>';
        $html .= '</tr></thead><tbody'.($groups ? ' class="pms-grouped-rows" data-source="'.($block['dataSource'] ?? 'rows').'" data-group-configured="1"' : '').'>';
        foreach ($groups as $level => $group) {
            $enabledBy = ! empty($group['enabledBy']) ? ' data-group-enabled-by="'.htmlspecialchars((string) $group['enabledBy'], ENT_QUOTES, 'UTF-8').'"' : '';
            $html .= '<tr class="pms-group-header" data-group-level="'.$level.'" data-group-field="'.htmlspecialchars((string) ($group['field'] ?? ''), ENT_QUOTES, 'UTF-8').'"'.$enabledBy.'><td colspan="'.count($columns).'">'.($group['label'] ?? '').'</td></tr>';
        }
        $source = (string) ($block['dataSource'] ?? 'rows');
        $html .= '<tr class="pms-detail-row"'.($groups ? '' : ' data-source="'.$source.'"').'>';
        foreach ($columns as $column) {
            $value = (string) ($column['value'] ?? '');
            if (($column['format'] ?? '') === 'number') $value .= '|number';
            $html .= '<td class="'.htmlspecialchars((string) ($column['align'] ?? 'left'), ENT_QUOTES, 'UTF-8').'">{{'.$value.'}}</td>';
        }
        $html .= '</tr>';
        foreach ($block['customRows'] ?? [] as $row) if (($row['scope'] ?? 'table') !== 'table') $html .= $this->compileRow($row, 'pms-group-custom-row', true);
        $html .= '</tbody>';
        $tableRows = array_filter($block['customRows'] ?? [], static fn (array $row): bool => ($row['scope'] ?? 'table') === 'table');
        if ($tableRows) {
            $html .= '<tfoot>'; foreach ($tableRows as $row) $html .= $this->compileRow($row, 'total'); $html .= '</tfoot>';
        }
        return $html.'</table>';
    }

    private function compileRow(array $row, string $class, bool $group = false): string
    {
        $level = $group ? ' data-group-level="'.max(0, (int) ($row['level'] ?? 0)).'"' : '';
        $html = '<tr class="'.$class.'"'.$level.'>';
        foreach ($row['cells'] ?? [] as $cell) {
            $content = ($cell['type'] ?? '') === 'binding' ? '{{'.($cell['binding'] ?? '').(! empty($cell['format']) ? '|'.$cell['format'] : '').'}}' : (string) ($cell['content'] ?? '');
            $html .= '<td colspan="'.max(1, (int) ($cell['colspan'] ?? 1)).'">'.$content.'</td>';
        }
        return $html.'</tr>';
    }
    public function css(): string { return '.report-header{display:flex;justify-content:space-between;min-height:55px}.hotel-logo{width:30%}.hotel-logo img{max-width:120px;max-height:48px}.hotel-information{width:70%;text-align:right;font-size:9px;line-height:1.5}h1{text-align:center;font-size:17px;margin:10px 0 3px}p.period{text-align:center;font-style:italic;font-size:10px}table{width:100%;border-collapse:collapse;font-size:8px;table-layout:fixed}th,td{border:1px solid #aeb5c0;padding:3px 2px}th{background:#d9deea;text-align:center}.pms-group-header td{background:#f1f5f9;color:#b82c2c;font-weight:bold;text-align:left}.pms-group-custom-row td,.total td{background:#d9deea;font-weight:bold}'; }
};
