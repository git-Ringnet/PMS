<?php

use App\Services\TemplateRendererService;

/**
 * Designer v1 reference for REVENUE_ARMY / legacy ProVistaArmyHotel.dbo.sp_292.
 * The stored procedure/data-source integration is intentionally not registered
 * until its legacy-key and runtime amount mappings are verified.
 */
return new class
{
    public function definition(): array
    {
        return [
            'code' => 'REVENUE_ARMY',
            'name' => 'Báo cáo doanh thu',
            'report' => 'REVENUE_ARMY_REFERENCE',
            'page_size' => 'A4',
            'page_orientation' => 'landscape',
            'margin_top' => 8,
            'margin_right' => 5,
            'margin_bottom' => 8,
            'margin_left' => 5,
            'version' => '1.0',
            'parameter_defaults' => [
                'p_date' => '$today',
                'p_company_id' => '0',
                'p_booking_id' => '0',
            ],
            'content_html' => $this->html(),
            'content_json' => $this->blocks(),
            'css' => $this->css(),
            'columns' => $this->columns(),
            'data_contract' => [
                'rows' => [
                    'Index' => 'integer',
                    'Ma' => 'integer',
                    'BookingName' => 'string',
                    'Company' => 'string',
                    'ArrivalDate' => 'string',
                    'DepartureDate' => 'string',
                    'RoomNo' => 'integer',
                    'RoomToday' => 'number',
                    'ExtraRoomToday' => 'number',
                    'MinibarToday' => 'number',
                    'LaundryToday' => 'number',
                    'BrokenToday' => 'number',
                    'RestaurantToday' => 'number',
                    'BreakfastSurchargeToday' => 'number',
                    'OtherToday' => 'number',
                    'TotalToday' => 'number',
                    'PrevDay' => 'number',
                    'TotalRevenue' => 'number',
                    'Cash' => 'number',
                    'BankTransfer' => 'number',
                    'CityLedger' => 'number',
                    'Commission' => 'number',
                    'InhouseRoom' => 'number',
                ],
                'parameters' => [
                    'p_date' => 'date',
                    'p_company_id' => 'string',
                    'p_booking_id' => 'string',
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
        return [
            'header' => [
                [
                    'id' => 'revenue_army_header_band',
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
                                'id' => 'revenue_army_logo',
                                'type' => 'text',
                                'content' => '<div class="hotel-logo">{{hotel.logo}}</div>',
                                'style' => ['minHeight' => '50px'],
                            ]],
                        ],
                        [
                            'width' => '70%',
                            'blocks' => [[
                                'id' => 'revenue_army_hotel_info',
                                'type' => 'text',
                                'content' => '<div class="hotel-information"><div><b>Địa chỉ:</b> {{hotel.address}}</div><div><b>Người dùng:</b> {{report.generated_by}} &nbsp;&nbsp;&nbsp;&nbsp; <b>Ngày:</b> {{report.generated_at}}</div></div>',
                                'style' => ['textAlign' => 'right', 'fontSize' => '9.5px', 'lineHeight' => '1.5'],
                            ]],
                        ],
                    ],
                ],
                [
                    'id' => 'revenue_army_divider',
                    'type' => 'divider',
                    'content' => '<hr class="header-divider">',
                    'style' => ['borderTop' => '1px solid #111111', 'marginTop' => '4px', 'marginBottom' => '8px'],
                ],
                [
                    'id' => 'revenue_army_title',
                    'type' => 'text',
                    'content' => '<h1 class="report-title">BÁO CÁO DOANH THU</h1>',
                    'style' => ['textAlign' => 'center', 'fontWeight' => 'bold', 'fontSize' => '17px'],
                ],
                [
                    'id' => 'revenue_army_period',
                    'type' => 'text',
                    'content' => '<p class="report-period"><b>Ngày:</b> {{parameters.p_date}}</p>',
                    'style' => ['textAlign' => 'center', 'fontSize' => '10px'],
                ],
            ],
            'detail' => [[
                'id' => 'revenue_army_table',
                'type' => 'table',
                'dataSource' => 'rows',
                'tableType' => 'dynamic',
                'tableStyle' => 'grid',
                'tableClassName' => 'revenue-army-table',
                'style' => [
                    'width' => '100%',
                    'fontSize' => '8.5px',
                    'lineHeight' => '1.2',
                    'borderCollapse' => 'collapse',
                    'borderWidth' => '1px',
                    'borderStyle' => 'solid',
                    'borderColor' => '#aeb5c0',
                    'backgroundColor' => '#ffffff',
                ],
                'hasTwoTierHeader' => true,
                'topHeader' => $this->topHeader(),
                'columns' => $this->columns(),
                'customRows' => [$this->grandTotalRow()],
            ]],
            'footer' => [[
                'id' => 'revenue_army_signatures',
                'type' => 'text',
                'content' => '<div class="location-date">Quy Nhơn, Ngày {{parameters.p_date}}</div><div class="signature-grid"><div>Chữ Ký Người Lập</div><div>Trưởng Bộ Phận</div><div>Kế Toán</div><div>Tổng Quản Lý</div><div>Giám Đốc</div></div>',
                'style' => ['fontSize' => '9.5px'],
            ]],
        ];
    }

    public function columns(): array
    {
        $definitions = [
            ['Index', 'STT', '2%', 'center', 'text'],
            ['Ma', 'Mã đăng ký', '4%', 'center', 'text'],
            ['BookingName', 'Tên khách', '13%', 'left', 'text'],
            ['Company', 'Đơn vị', '7%', 'left', 'text'],
            ['ArrivalDate', 'Ngày đến', '4.5%', 'center', 'text'],
            ['DepartureDate', 'Ngày đi', '4.5%', 'center', 'text'],
            ['RoomNo', 'SP', '2.5%', 'center', 'number'],
            ['RoomToday', 'Tiền phòng', '3.5%', 'right', 'number'],
            ['ExtraRoomToday', 'Phụ thu tiền phòng', '4%', 'right', 'number'],
            ['MinibarToday', 'Minibar', '3%', 'right', 'number'],
            ['LaundryToday', 'Giặt', '2.5%', 'right', 'number'],
            ['BrokenToday', 'Bể vỡ', '3%', 'right', 'number'],
            ['RestaurantToday', 'Nhà hàng', '3.5%', 'right', 'number'],
            ['BreakfastSurchargeToday', 'Phụ thu ăn sáng', '3.5%', 'right', 'number'],
            ['OtherToday', 'Dịch vụ', '3.5%', 'right', 'number'],
            ['TotalToday', 'Tổng DT', '4.5%', 'right', 'number'],
            ['PrevDay', 'DT ngày trước', '4.5%', 'right', 'number'],
            ['TotalRevenue', 'Tổng cộng', '5.5%', 'right', 'number'],
            ['Cash', 'TM', '4%', 'right', 'number'],
            ['BankTransfer', 'CK', '4%', 'right', 'number'],
            ['Commission', 'HH', '3.5%', 'right', 'number'],
            ['CityLedger', 'Còn nợ', '4%', 'right', 'number'],
            ['InhouseRoom', 'Phòng còn ở', '5%', 'right', 'number'],
        ];

        return array_map(static function (array $definition): array {
            [$field, $header, $width, $align, $format] = $definition;
            return [
                'id' => 'revenue_army_'.$field,
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
                    'padding' => '3px 2px',
                    'fontSize' => '8.5px',
                    'color' => '#111111',
                ],
                'cellStyle' => [
                    'border' => '1px solid #aeb5c0',
                    'textAlign' => $align,
                    'verticalAlign' => 'middle',
                    'padding' => '3px 2px',
                    'fontSize' => '8.5px',
                    'color' => '#111111',
                ],
            ];
        }, $definitions);
    }

    private function topHeader(): array
    {
        return [
            ['label' => 'STT', 'rowspan' => 2, 'width' => '2%', 'align' => 'center'],
            ['label' => 'Mã đăng ký', 'rowspan' => 2, 'width' => '4%', 'align' => 'center'],
            ['label' => 'Tên khách', 'rowspan' => 2, 'width' => '13%', 'align' => 'center'],
            ['label' => 'Đơn vị', 'rowspan' => 2, 'width' => '7%', 'align' => 'center'],
            ['label' => 'Ngày đến', 'rowspan' => 2, 'width' => '4.5%', 'align' => 'center'],
            ['label' => 'Ngày đi', 'rowspan' => 2, 'width' => '4.5%', 'align' => 'center'],
            ['label' => 'SP', 'rowspan' => 2, 'width' => '2.5%', 'align' => 'center'],
            ['label' => 'Các khoản thu trong ngày', 'colspan' => 8, 'align' => 'center'],
            ['label' => 'Tổng DT', 'rowspan' => 2, 'width' => '4.5%', 'align' => 'center'],
            ['label' => 'DT ngày trước', 'rowspan' => 2, 'width' => '4.5%', 'align' => 'center'],
            ['label' => 'Tổng cộng', 'rowspan' => 2, 'width' => '5.5%', 'align' => 'center'],
            ['label' => 'Phòng đã trả', 'colspan' => 4, 'align' => 'center'],
            ['label' => 'Phòng còn ở', 'rowspan' => 2, 'width' => '5%', 'align' => 'center'],
        ];
    }

    private function grandTotalRow(): array
    {
        $cells = [[
            'id' => 'revenue_army_count_label',
            'type' => 'text',
            'content' => 'Tổng số BK: {{aggregate.rows.count}}',
            'colspan' => 6,
            'align' => 'left',
            'style' => ['fontWeight' => 'bold', 'textAlign' => 'left', 'backgroundColor' => '#d9deea', 'padding' => '4px 3px'],
        ], [
            'id' => 'revenue_army_total_room_count',
            'type' => 'binding',
            'binding' => 'aggregate.rows.sum.RoomNo',
            'colspan' => 1,
            'align' => 'center',
            'format' => 'number',
            'style' => ['fontWeight' => 'bold', 'textAlign' => 'center', 'backgroundColor' => '#d9deea', 'padding' => '4px 2px'],
        ]];

        foreach ([
            'RoomToday', 'ExtraRoomToday', 'MinibarToday', 'LaundryToday', 'BrokenToday',
            'RestaurantToday', 'BreakfastSurchargeToday', 'OtherToday', 'TotalToday',
            'PrevDay', 'TotalRevenue', 'Cash', 'BankTransfer', 'Commission', 'CityLedger', 'InhouseRoom',
        ] as $field) {
            $cells[] = [
                'id' => 'revenue_army_total_'.$field,
                'type' => 'binding',
                'binding' => 'aggregate.rows.sum.'.$field,
                'colspan' => 1,
                'align' => 'right',
                'format' => 'number',
                'style' => ['fontWeight' => 'bold', 'textAlign' => 'right', 'backgroundColor' => '#d9deea', 'padding' => '4px 2px'],
            ];
        }

        return [
            'id' => 'revenue_army_grand_total',
            'enabledBy' => '',
            'scope' => 'table',
            'level' => 0,
            'className' => 'report-grand-total-row',
            'cells' => $cells,
        ];
    }

    public function css(): string
    {
        return <<<'CSS'
body { font-family: Arial, Helvetica, sans-serif; color: #111111; font-size: 9px; }
.report-header-band { width: 100%; margin-bottom: 4px; }
.hotel-logo img { max-width: 120px; max-height: 50px; object-fit: contain; }
.hotel-information { text-align: right; font-size: 9.5px; line-height: 1.5; }
.header-divider { border: 0; border-top: 1px solid #111111; margin: 4px 0 8px; }
.report-title { text-align: center; font-size: 17px; font-weight: bold; margin: 4px 0 2px; color: #111111; }
.report-period { text-align: center; font-size: 10px; margin: 2px 0 10px; color: #111111; }
.revenue-army-table { width: 100%; table-layout: fixed; border-collapse: collapse; border: 1px solid #aeb5c0; font-size: 8.5px; line-height: 1.2; }
.revenue-army-table th, .revenue-army-table td { border: 1px solid #aeb5c0; padding: 3px 2px; vertical-align: middle; overflow-wrap: anywhere; }
.revenue-army-table thead th { background-color: #d9deea; color: #111111; font-weight: bold; text-align: center; }
.revenue-army-table tfoot td { background-color: #d9deea; font-weight: bold; border-top: 1px solid #aeb5c0; }
.location-date { text-align: right; font-style: italic; font-size: 9.5px; margin: 12px 0 10px; }
.signature-grid { display: flex; justify-content: space-between; text-align: center; margin-top: 10px; font-size: 9.5px; }
.signature-grid > div { width: 20%; }
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
            if ($band === 'header') {
                $html .= '<div class="report-header-band">';
            } else {
                $html .= '<div class="report-'.$band.'-band">';
            }
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
            $html = '<div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 4px;">';
            foreach ($block['columns'] ?? [] as $column) {
                $html .= '<div style="width: '.htmlspecialchars((string) ($column['width'] ?? 'auto'), ENT_QUOTES, 'UTF-8').';">';
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
        $topHeader = $block['topHeader'] ?? [];
        $rowspanFields = [];
        foreach ($topHeader as $header) {
            if ((int) ($header['rowspan'] ?? 1) > 1) {
                $rowspanFields[] = (string) ($header['label'] ?? '');
            }
        }

        $tableStyle = $this->compileStyle($block['style'] ?? []);
        $tableClass = htmlspecialchars((string) ($block['tableClassName'] ?? 'report-table'), ENT_QUOTES, 'UTF-8');
        $html = '<table class="'.$tableClass.'"'.($tableStyle !== '' ? ' style="'.$tableStyle.'"' : '').'><colgroup>';
        foreach ($columns as $column) {
            $html .= '<col style="width: '.htmlspecialchars((string) ($column['width'] ?? 'auto'), ENT_QUOTES, 'UTF-8').'">';
        }
        $html .= '</colgroup><thead>';

        if (! empty($block['hasTwoTierHeader']) && $topHeader !== []) {
            $html .= '<tr>';
            foreach ($topHeader as $header) {
                $attrs = '';
                if (! empty($header['rowspan'])) {
                    $attrs .= ' rowspan="'.(int) $header['rowspan'].'"';
                }
                if (! empty($header['colspan'])) {
                    $attrs .= ' colspan="'.(int) $header['colspan'].'"';
                }
                $style = $this->compileStyle(array_merge(['textAlign' => $header['align'] ?? 'center'], $header['style'] ?? []));
                $html .= '<th'.$attrs.($style !== '' ? ' style="'.$style.'"' : '').'>'.htmlspecialchars((string) ($header['label'] ?? ''), ENT_QUOTES, 'UTF-8').'</th>';
            }
            $html .= '</tr><tr>';
            foreach ($columns as $column) {
                if (in_array((string) ($column['header'] ?? ''), $rowspanFields, true)) {
                    continue;
                }
                $headerStyle = $this->compileStyle($column['headerStyle'] ?? ['textAlign' => $column['align'] ?? 'center']);
                $html .= '<th'.($headerStyle !== '' ? ' style="'.$headerStyle.'"' : '').'>'.htmlspecialchars((string) ($column['header'] ?? ''), ENT_QUOTES, 'UTF-8').'</th>';
            }
            $html .= '</tr>';
        } else {
            $html .= '<tr>';
            foreach ($columns as $column) {
                $html .= '<th>'.htmlspecialchars((string) ($column['header'] ?? ''), ENT_QUOTES, 'UTF-8').'</th>';
            }
            $html .= '</tr>';
        }

        $html .= '</thead><tbody class="pms-grouped-rows" data-source="'.htmlspecialchars((string) ($block['dataSource'] ?? 'rows'), ENT_QUOTES, 'UTF-8').'" data-group-configured="1">';
        $html .= '<tr class="pms-detail-row">';
        foreach ($columns as $column) {
            $value = (string) ($column['value'] ?? '');
            $format = ($column['format'] ?? '') === 'number' ? '|number' : '';
            $cellStyle = $this->compileStyle($column['cellStyle'] ?? ['textAlign' => $column['align'] ?? 'left']);
            $html .= '<td'.($cellStyle !== '' ? ' style="'.$cellStyle.'"' : '').'>{{'.htmlspecialchars($value, ENT_QUOTES, 'UTF-8').$format.'}}</td>';
        }
        $html .= '</tr></tbody>';

        $customRows = array_values(array_filter($block['customRows'] ?? [], static fn (array $row): bool => ($row['scope'] ?? 'table') === 'table'));
        if ($customRows !== []) {
            $html .= '<tfoot>';
            foreach ($customRows as $row) {
                $rowClass = htmlspecialchars('pms-custom-row '.($row['className'] ?? ''), ENT_QUOTES, 'UTF-8');
                $html .= '<tr class="'.$rowClass.'">';
                foreach ($row['cells'] ?? [] as $cell) {
                    $colspan = max(1, (int) ($cell['colspan'] ?? 1));
                    $style = $this->compileStyle($cell['style'] ?? ['textAlign' => $cell['align'] ?? 'left']);
                    $content = ($cell['type'] ?? '') === 'binding'
                        ? '{{'.($cell['binding'] ?? '').(! empty($cell['format']) ? '|'.$cell['format'] : '').'}}'
                        : (string) ($cell['content'] ?? '');
                    $html .= '<td colspan="'.$colspan.'"'.($style !== '' ? ' style="'.$style.'"' : '').'>'.$content.'</td>';
                }
                $html .= '</tr>';
            }
            $html .= '</tfoot>';
        }

        return $html.'</table>';
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
