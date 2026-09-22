<?php

use App\Services\TemplateRendererService;

/**
 * Designer v1 reference for RECEPTION_CASHIER_SHIFT / Navy sp_039.
 * This file defines presentation and binding contracts only. It does not
 * register a report or claim a runtime data-source mapping.
 */
return new class
{
    public function definition(): array
    {
        return [
            'code' => 'RECEPTION_CASHIER_SHIFT',
            'name' => 'Báo cáo thu ngân lễ tân',
            'report' => 'RECEPTION_CASHIER_SHIFT_REFERENCE',
            'page_size' => 'A4',
            'page_orientation' => 'landscape',
            'margin_top' => 8,
            'margin_right' => 5,
            'margin_bottom' => 8,
            'margin_left' => 5,
            'version' => '1.0',
            'parameter_defaults' => [
                'p_from_date' => '$today',
                'p_to_date' => '$today',
                'p_department' => 'FO',
                'p_user' => '',
                'p_shift' => '',
                'p_from_time' => '00:00',
                'p_to_time' => '23:59',
                'p_company' => '-1',
                'p_view_deposit' => 1,
                'p_view_amount_zero' => 0,
                'p_payment_method' => '',
            ],
            'content_html' => $this->html(),
            'content_json' => $this->blocks(),
            'css' => $this->css(),
            'columns' => $this->columns(),
            'data_contract' => [
                'rows' => [
                    'BookingStatus' => 'integer',
                    'StatusRoom' => 'integer',
                    'BillID' => 'string',
                    'Date' => 'string',
                    'Room' => 'string',
                    'Guest' => 'string',
                    'OpenTime' => 'string',
                    'PaymentID' => 'string',
                    'Amount' => 'number',
                    'Username' => 'string',
                    'Description' => 'string',
                    'PaymentMethod' => 'string',
                    'PaymentMethodName' => 'string',
                    'Department' => 'string',
                    'NumOfRoom' => 'string',
                    'Deposit' => 'integer',
                    'ShowDeposit' => 'string',
                    'DepositGroupTitle' => 'string',
                    'MaBooking' => 'string',
                    'ArrivalDate' => 'string',
                    'DepartureDate' => 'string',
                    'GuestInfo' => 'string',
                    'Provide2' => 'string',
                    'Company' => 'string',
                    'CardId' => 'string',
                    'Division' => 'string',
                    'PaymentMethodGroup' => 'string',
                    'PaymentMethodGroupTitle' => 'string',
                ],
                'currency_allocations' => [
                    'PaymentMethod' => 'string',
                    'PaymentMethodName' => 'string',
                    'PaymentMethodTitle' => 'string',
                    'CashierAmount' => 'number',
                    'DepositAmount' => 'number',
                    'TotalCashierDeposit' => 'number',
                    'RefundAmount' => 'number',
                    'NetTotal' => 'number',
                ],
                'parameters' => [
                    'p_from_date' => 'date',
                    'p_to_date' => 'date',
                    'p_department' => 'string',
                    'p_user' => 'string',
                    'p_shift' => 'string',
                    'p_from_time' => 'string',
                    'p_to_time' => 'string',
                    'p_company' => 'string',
                    'p_view_deposit' => 'integer',
                    'p_view_amount_zero' => 'integer',
                    'p_payment_method' => 'string',
                ],
            ],
            'blocked_datasets' => [
                'city_ledger_rows' => 'Chưa xác minh nguồn dữ liệu và công thức; Navy sp_039 không trả dataset này.',
            ],
        ];
    }

    public function html(): string
    {
        return $this->compileDesignerBlocks($this->blocks());
    }

    public function blocks(): array
    {
        return [
            'header' => [
                [
                    'id' => 'cashier_shift_header_band',
                    'type' => 'columns',
                    'style' => [
                        'display' => 'flex',
                        'justifyContent' => 'space-between',
                        'alignItems' => 'flex-start',
                        'marginBottom' => '4px',
                    ],
                    'columns' => [
                        [
                            'width' => '30%',
                            'blocks' => [[
                                'id' => 'cashier_shift_logo',
                                'type' => 'text',
                                'content' => '<div class="hotel-logo">{{hotel.logo}}</div>',
                                'style' => ['minHeight' => '50px'],
                            ]],
                        ],
                        [
                            'width' => '70%',
                            'blocks' => [[
                                'id' => 'cashier_shift_hotel_info',
                                'type' => 'text',
                                'content' => '<div class="hotel-information"><div><b>Địa chỉ:</b> {{hotel.address}}</div><div><b>Người dùng:</b> {{report.generated_by}} &nbsp;&nbsp; <b>Ngày:</b> {{report.generated_at}}</div></div>',
                                'style' => ['textAlign' => 'right', 'fontSize' => '9px', 'lineHeight' => '1.35'],
                            ]],
                        ],
                    ],
                ],
                [
                    'id' => 'cashier_shift_divider',
                    'type' => 'divider',
                    'content' => '<hr class="cashier-shift-divider">',
                    'style' => ['borderTop' => '1px solid #111111', 'marginTop' => '3px', 'marginBottom' => '7px'],
                ],
                [
                    'id' => 'cashier_shift_title',
                    'type' => 'text',
                    'content' => '<h1 class="cashier-shift-title">BÁO CÁO THU NGÂN LỄ TÂN</h1>',
                    'style' => ['textAlign' => 'center', 'fontWeight' => 'bold', 'fontSize' => '17px'],
                ],
                [
                    'id' => 'cashier_shift_filter_summary',
                    'type' => 'text',
                    'content' => '<div class="cashier-shift-filter-summary"><span><b>Ngày:</b> {{parameters.p_from_date}} &nbsp; ~ &nbsp; {{parameters.p_to_date}}</span><span><b>Ca:</b> {{parameters.p_shift}}</span><span><b>Giờ:</b> {{parameters.p_from_time}} &nbsp; ~ &nbsp; {{parameters.p_to_time}}</span></div>',
                    'style' => ['textAlign' => 'center', 'fontSize' => '9.5px'],
                ],
            ],
            'detail' => [
                $this->mainTableBlock(),
                [
                    'id' => 'cashier_shift_currency_title',
                    'type' => 'text',
                    'content' => '<h2 class="cashier-shift-section-title">Bảng Phân Bổ Tiền Tệ</h2>',
                    'style' => ['textAlign' => 'center', 'fontWeight' => 'bold', 'fontSize' => '14px'],
                ],
                $this->currencyTableBlock(),
                [
                    'id' => 'cashier_shift_city_ledger_title',
                    'type' => 'text',
                    'content' => '<h2 class="cashier-shift-section-title">Tổng Hợp Công Nợ Công Ty</h2>',
                    'style' => ['textAlign' => 'center', 'fontWeight' => 'bold', 'fontSize' => '14px'],
                ],
                [
                    'id' => 'cashier_shift_city_ledger_pending',
                    'type' => 'text',
                    'content' => '<p class="cashier-shift-pending">Bảng công nợ công ty: Chưa xác minh nguồn dữ liệu runtime.</p>',
                    'style' => ['textAlign' => 'center', 'fontSize' => '9px', 'color' => '#92400e'],
                ],
            ],
            'footer' => [[
                'id' => 'cashier_shift_signatures',
                'type' => 'text',
                'content' => '<div class="cashier-shift-signatures"><div>Nhân viên</div><div>Trưởng phòng</div><div>Bộ phận kế toán</div></div>',
                'style' => ['fontSize' => '9.5px'],
            ]],
        ];
    }

    public function columns(): array
    {
        return $this->mainColumns();
    }

    private function mainColumns(): array
    {
        return $this->columnsFromDefinitions([
            ['MaBooking', 'Mã ĐK', '7%', 'center', 'text'],
            ['Room', 'Phòng', '5%', 'center', 'text'],
            ['GuestInfo', 'Tên Khách', '13%', 'left', 'text'],
            ['ArrivalDate', 'Ngày Đến', '6.5%', 'center', 'text'],
            ['DepartureDate', 'Ngày Đi', '6.5%', 'center', 'text'],
            ['OpenTime', 'Giờ', '4.5%', 'center', 'text'],
            ['BillID', 'Mã TT', '6%', 'center', 'text'],
            ['Amount', 'Số Tiền', '8%', 'right', 'number'],
            ['Username', 'Người dùng', '6%', 'center', 'text'],
            ['Description', 'Mô Tả', '19%', 'left', 'text'],
            ['CardId', 'Thẻ (4 số cuối)', '18.5%', 'left', 'text'],
        ], 'cashier_shift_main');
    }

    private function allocationColumns(): array
    {
        return $this->columnsFromDefinitions([
            ['PaymentMethodTitle', 'HTTT', '25%', 'left', 'text'],
            ['CashierAmount', 'Thu Ngân', '15%', 'right', 'number'],
            ['DepositAmount', 'Đặt Cọc', '15%', 'right', 'number'],
            ['TotalCashierDeposit', 'Thu Ngân + Đặt Cọc', '20%', 'right', 'number'],
            ['RefundAmount', 'Hoàn Tiền', '12%', 'right', 'number'],
            ['NetTotal', 'Tổng', '13%', 'right', 'number'],
        ], 'cashier_shift_allocation');
    }

    private function columnsFromDefinitions(array $definitions, string $prefix): array
    {
        return array_map(static function (array $definition) use ($prefix): array {
            [$field, $header, $width, $align, $format] = $definition;
            return [
                'id' => $prefix.'_'.$field,
                'field' => $field,
                'header' => $header,
                'value' => 'row.'.$field,
                'width' => $width,
                'align' => $align,
                'format' => $format,
                'headerStyle' => [
                    'backgroundColor' => '#d9deea',
                    'border' => '1px solid #aeb5c0',
                    'textAlign' => 'center',
                    'fontWeight' => 'bold',
                    'padding' => '4px 5px',
                    'fontSize' => '9.5px',
                    'color' => '#111111',
                ],
                'cellStyle' => [
                    'border' => '1px solid #aeb5c0',
                    'textAlign' => $align,
                    'verticalAlign' => 'middle',
                    'padding' => '4px 5px',
                    'fontSize' => '9.5px',
                    'color' => '#111111',
                ],
            ];
        }, $definitions);
    }

    private function mainTableBlock(): array
    {
        $columnCount = count($this->mainColumns());
        return [
            'id' => 'cashier_shift_detail_table',
            'type' => 'table',
            'dataSource' => 'rows',
            'tableType' => 'dynamic',
            'tableStyle' => 'grid',
            'tableClassName' => 'cashier-shift-main-table',
            'style' => ['width' => '100%', 'borderCollapse' => 'collapse', 'fontSize' => '9.5px', 'backgroundColor' => '#ffffff'],
            'columns' => $this->mainColumns(),
            'grouping' => [
                [
                    'id' => 'cashier_shift_type_group',
                    'field' => 'Deposit',
                    'sort' => 'ASC',
                    'enabledBy' => '',
                    'headerCells' => [[
                        'id' => 'cashier_shift_type_group_cell',
                        'type' => 'text',
                        'content' => '<strong>{{row.DepositGroupTitle}}</strong>',
                        'colspan' => $columnCount,
                        'align' => 'left',
                        'style' => ['backgroundColor' => '#f3f4f6', 'fontWeight' => 'bold', 'padding' => '5px 4px'],
                    ]],
                ],
                [
                    'id' => 'cashier_shift_method_group',
                    'field' => 'PaymentMethodGroup',
                    'sort' => 'ASC',
                    'enabledBy' => '',
                    'headerCells' => [[
                        'id' => 'cashier_shift_method_group_cell',
                        'type' => 'text',
                        'content' => '<span style="color: #dc2626; font-weight: bold;">Thanh Toán: {{row.PaymentMethodGroupTitle}}</span>',
                        'colspan' => $columnCount,
                        'align' => 'left',
                        'style' => ['backgroundColor' => '#ffffff', 'padding' => '4px'],
                    ]],
                ],
            ],
            'customRows' => [
                [
                    'id' => 'cashier_shift_method_subtotal',
                    'enabledBy' => '',
                    'scope' => 'group',
                    'level' => 1,
                    'className' => 'cashier-shift-subtotal',
                    'cells' => [
                        ['id' => 'method_subtotal_label', 'type' => 'text', 'content' => 'Tổng', 'colspan' => 7, 'align' => 'right', 'style' => ['fontWeight' => 'bold', 'backgroundColor' => '#d9deea']],
                        ['id' => 'method_subtotal_amount', 'type' => 'binding', 'binding' => 'group.sum.Amount', 'format' => 'number', 'colspan' => 1, 'align' => 'right', 'style' => ['fontWeight' => 'bold', 'backgroundColor' => '#d9deea']],
                        ['id' => 'method_subtotal_blank', 'type' => 'text', 'content' => '', 'colspan' => 3, 'align' => 'left', 'style' => ['backgroundColor' => '#d9deea']],
                    ],
                ],
                [
                    'id' => 'cashier_shift_type_subtotal',
                    'enabledBy' => '',
                    'scope' => 'group',
                    'level' => 0,
                    'className' => 'cashier-shift-subtotal',
                    'cells' => [
                        ['id' => 'type_subtotal_label', 'type' => 'text', 'content' => '{{row.ShowDeposit}} Total', 'colspan' => 7, 'align' => 'right', 'style' => ['fontWeight' => 'bold', 'backgroundColor' => '#d9deea']],
                        ['id' => 'type_subtotal_amount', 'type' => 'binding', 'binding' => 'group.sum.Amount', 'format' => 'number', 'colspan' => 1, 'align' => 'right', 'style' => ['fontWeight' => 'bold', 'backgroundColor' => '#d9deea']],
                        ['id' => 'type_subtotal_blank', 'type' => 'text', 'content' => '', 'colspan' => 3, 'align' => 'left', 'style' => ['backgroundColor' => '#d9deea']],
                    ],
                ],
                [
                    'id' => 'cashier_shift_detail_grand_total',
                    'enabledBy' => '',
                    'scope' => 'table',
                    'level' => 0,
                    'className' => 'cashier-shift-grand-total',
                    'cells' => [
                        ['id' => 'grand_count', 'type' => 'text', 'content' => 'Tổng số: {{aggregate.rows.count}}', 'colspan' => 2, 'align' => 'left', 'style' => ['fontWeight' => 'bold', 'backgroundColor' => '#d1d5db']],
                        ['id' => 'grand_empty_left', 'type' => 'text', 'content' => '', 'colspan' => 4, 'align' => 'left', 'style' => ['backgroundColor' => '#d1d5db']],
                        ['id' => 'grand_label', 'type' => 'text', 'content' => 'Tổng', 'colspan' => 1, 'align' => 'right', 'style' => ['fontWeight' => 'bold', 'backgroundColor' => '#d1d5db']],
                        ['id' => 'grand_amount', 'type' => 'binding', 'binding' => 'aggregate.rows.sum.Amount', 'format' => 'number', 'colspan' => 1, 'align' => 'right', 'style' => ['fontWeight' => 'bold', 'backgroundColor' => '#d1d5db']],
                        ['id' => 'grand_empty_right', 'type' => 'text', 'content' => '', 'colspan' => 3, 'align' => 'left', 'style' => ['backgroundColor' => '#d1d5db']],
                    ],
                ],
            ],
        ];
    }

    private function currencyTableBlock(): array
    {
        $columns = $this->allocationColumns();
        $cells = [[
            'id' => 'allocation_total_label', 'type' => 'text', 'content' => 'Tổng', 'colspan' => 1,
            'align' => 'left', 'style' => ['fontWeight' => 'bold', 'backgroundColor' => '#d1d5db'],
        ]];
        foreach (['CashierAmount', 'DepositAmount', 'TotalCashierDeposit', 'RefundAmount', 'NetTotal'] as $field) {
            $cells[] = [
                'id' => 'allocation_total_'.$field, 'type' => 'binding',
                'binding' => 'aggregate.currency_allocations.sum.'.$field, 'format' => 'number',
                'colspan' => 1, 'align' => 'right',
                'style' => ['fontWeight' => 'bold', 'backgroundColor' => '#d1d5db'],
            ];
        }

        return [
            'id' => 'cashier_shift_currency_table',
            'type' => 'table',
            'dataSource' => 'currency_allocations',
            'tableType' => 'dynamic',
            'tableStyle' => 'grid',
            'tableClassName' => 'cashier-shift-summary-table',
            'style' => ['width' => '55%', 'marginLeft' => 'auto', 'marginRight' => 'auto', 'borderCollapse' => 'collapse', 'fontSize' => '9.5px'],
            'columns' => $columns,
            'customRows' => [[
                'id' => 'cashier_shift_currency_grand_total',
                'scope' => 'table',
                'className' => 'cashier-shift-grand-total',
                'cells' => $cells,
            ]],
        ];
    }

    public function css(): string
    {
        return <<<'CSS'
body { font-family: Arial, Helvetica, sans-serif; color: #111827; font-size: 10px; }
.report-header-band { width: 100%; margin-bottom: 5px; }
.hotel-logo img { max-width: 140px; max-height: 55px; object-fit: contain; }
.hotel-information { text-align: right; font-size: 9px; line-height: 1.35; }
.cashier-shift-divider { border: 0; border-top: 1px solid #111111; margin: 3px 0 7px; }
.cashier-shift-title { text-align: center; font-size: 17px; font-weight: bold; margin: 2px 0 5px; }
.cashier-shift-filter-summary { display: flex; justify-content: center; gap: 24px; flex-wrap: wrap; font-size: 9.5px; margin: 4px 0 12px; }
.cashier-shift-section-title { text-align: center; font-size: 14px; font-weight: bold; margin: 18px 0 8px; }
.cashier-shift-main-table, .cashier-shift-summary-table { width: 100%; border-collapse: collapse; table-layout: fixed; margin: 0 auto 8px; }
.cashier-shift-main-table { font-size: 9.5px; }
.cashier-shift-summary-table { font-size: 9.5px; }
.cashier-shift-main-table th, .cashier-shift-main-table td,
.cashier-shift-summary-table th, .cashier-shift-summary-table td { border: 1px solid #aeb5c0; padding: 4px 5px; vertical-align: middle; overflow-wrap: anywhere; }
.cashier-shift-main-table thead th, .cashier-shift-summary-table thead th { background: #d9deea; color: #111827; font-weight: bold; text-align: center; }
.cashier-shift-main-table .pms-group-header td { background: #f3f4f6; font-size: 10px; }
.cashier-shift-main-table .pms-subgroup-header td { background: #ffffff; }
.cashier-shift-main-table .cashier-shift-subtotal td { background: #d9deea; }
.cashier-shift-main-table .cashier-shift-grand-total td,
.cashier-shift-summary-table .cashier-shift-grand-total td { background: #d1d5db; font-weight: bold; }
.cashier-shift-signatures { display: flex; justify-content: space-between; margin: 26px 0 0; text-align: center; page-break-inside: avoid; }
.cashier-shift-signatures > div { width: 30%; }
@media print { thead { display: table-header-group; } tr { break-inside: avoid; } }
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
        $type = $block['type'] ?? 'text';
        if (in_array($type, ['text', 'divider'], true)) {
            return ($block['content'] ?? '')."\n";
        }
        if ($type === 'columns') {
            $html = '<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:4px;">';
            foreach ($block['columns'] ?? [] as $column) {
                $html .= '<div style="width:'.htmlspecialchars((string) ($column['width'] ?? 'auto'), ENT_QUOTES, 'UTF-8').';">';
                foreach ($column['blocks'] ?? [] as $child) {
                    $html .= $this->compileBlock($child);
                }
                $html .= '</div>';
            }
            return $html.'</div>';
        }
        return $type === 'table' ? $this->compileTable($block) : '';
    }

    private function compileTable(array $block): string
    {
        $columns = $block['columns'] ?? [];
        $dataSource = htmlspecialchars((string) ($block['dataSource'] ?? 'rows'), ENT_QUOTES, 'UTF-8');
        $className = htmlspecialchars((string) ($block['tableClassName'] ?? 'report-table'), ENT_QUOTES, 'UTF-8');
        $style = $this->compileStyle($block['style'] ?? []);
        $html = '<table class="'.$className.'"'.($style !== '' ? ' style="'.$style.'"' : '').'><colgroup>';
        foreach ($columns as $column) {
            $html .= '<col style="width:'.htmlspecialchars((string) ($column['width'] ?? 'auto'), ENT_QUOTES, 'UTF-8').'">';
        }
        $html .= '</colgroup><thead><tr>';
        foreach ($columns as $column) {
            $headerStyle = $this->compileStyle($column['headerStyle'] ?? ['textAlign' => $column['align'] ?? 'center']);
            $html .= '<th'.($headerStyle !== '' ? ' style="'.$headerStyle.'"' : '').'>'.htmlspecialchars((string) ($column['header'] ?? ''), ENT_QUOTES, 'UTF-8').'</th>';
        }
        $html .= '</tr></thead><tbody class="pms-grouped-rows" data-source="'.$dataSource.'" data-group-configured="1">';

        foreach ($block['grouping'] ?? $block['groups'] ?? [] as $index => $group) {
            $specificClass = $index === 0 ? '' : ($index === 1 ? ' pms-subgroup-header' : ' pms-subsubgroup-header');
            $level = (int) $index;
            $field = htmlspecialchars((string) ($group['field'] ?? ''), ENT_QUOTES, 'UTF-8');
            $sort = strtoupper((string) ($group['sort'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
            $enabledBy = ! empty($group['enabledBy']) ? ' data-group-enabled-by="'.htmlspecialchars((string) $group['enabledBy'], ENT_QUOTES, 'UTF-8').'"' : '';
            $html .= '<tr class="pms-group-header'.$specificClass.'" data-group-level="'.$level.'" data-group-field="'.$field.'" data-group-sort="'.$sort.'"'.$enabledBy.'>';
            foreach ($group['headerCells'] ?? [] as $cell) {
                $cellClass = htmlspecialchars((string) ($cell['className'] ?? ''), ENT_QUOTES, 'UTF-8');
                $cellStyle = $this->compileStyle($cell['style'] ?? ['textAlign' => $cell['align'] ?? 'left']);
                $html .= '<td colspan="'.max(1, (int) ($cell['colspan'] ?? count($columns))).'"'.($cellClass !== '' ? ' class="'.$cellClass.'"' : '').($cellStyle !== '' ? ' style="'.$cellStyle.'"' : '').'>'.($cell['content'] ?? '').'</td>';
            }
            $html .= '</tr>';
        }

        $html .= '<tr class="pms-detail-row">';
        foreach ($columns as $column) {
            $value = htmlspecialchars((string) ($column['value'] ?? ''), ENT_QUOTES, 'UTF-8');
            $format = ($column['format'] ?? '') === 'number' ? '|number' : '';
            $cellStyle = $this->compileStyle($column['cellStyle'] ?? ['textAlign' => $column['align'] ?? 'left']);
            $html .= '<td'.($cellStyle !== '' ? ' style="'.$cellStyle.'"' : '').'>{{'.$value.$format.'}}</td>';
        }
        $html .= '</tr>';

        foreach (array_filter($block['customRows'] ?? [], static fn (array $row): bool => ($row['scope'] ?? '') === 'group') as $row) {
            $level = (int) ($row['level'] ?? 0);
            $rowClass = htmlspecialchars('pms-group-custom-row '.($row['className'] ?? ''), ENT_QUOTES, 'UTF-8');
            $html .= '<tr class="'.$rowClass.'" data-group-level="'.$level.'">'.$this->compileCells($row['cells'] ?? [], count($columns)).'</tr>';
        }
        $html .= '</tbody>';

        $tableRows = array_filter($block['customRows'] ?? [], static fn (array $row): bool => ($row['scope'] ?? 'table') === 'table');
        if ($tableRows !== []) {
            $html .= '<tfoot>';
            foreach ($tableRows as $row) {
                $rowClass = htmlspecialchars('pms-custom-row '.($row['className'] ?? ''), ENT_QUOTES, 'UTF-8');
                $visibleBy = ! empty($row['enabledBy']) ? ' data-visible-by="'.htmlspecialchars((string) $row['enabledBy'], ENT_QUOTES, 'UTF-8').'"' : '';
                $html .= '<tr class="'.$rowClass.'"'.$visibleBy.'>'.$this->compileCells($row['cells'] ?? [], count($columns)).'</tr>';
            }
            $html .= '</tfoot>';
        }

        return $html.'</table>';
    }

    private function compileCells(array $cells, int $columnCount): string
    {
        $html = '';
        foreach ($cells as $cell) {
            $span = max(1, (int) ($cell['colspan'] ?? 1));
            $style = $this->compileStyle($cell['style'] ?? ['textAlign' => $cell['align'] ?? 'left']);
            $content = ($cell['type'] ?? '') === 'binding'
                ? '{{'.($cell['binding'] ?? '').(! empty($cell['format']) ? '|'.$cell['format'] : '').'}}'
                : (string) ($cell['content'] ?? '');
            $html .= '<td colspan="'.$span.'"'.($style !== '' ? ' style="'.$style.'"' : '').'>'.$content.'</td>';
        }
        return $html;
    }

    private function compileStyle(array $style): string
    {
        $parts = [];
        foreach ($style as $name => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $property = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', (string) $name));
            $parts[] = $property.': '.$value;
        }
        return implode('; ', $parts);
    }
};
