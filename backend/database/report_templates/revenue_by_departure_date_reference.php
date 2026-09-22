<?php

/**
 * Designer v1 reference layout for legacy sp_238/sp_240.
 * A report data source is intentionally not registered until the runtime
 * booking, service, payment and outlet mappings are verified.
 */
return new class
{
    public function definition(): array
    {
        $rowContract = [];
        foreach ($this->columns() as $column) {
            $rowContract[$column['field']] = $column['format'] === 'number' ? 'number' : 'string';
        }

        return [
            'report' => 'REVENUE_BY_DEPARTURE_DATE_REFERENCE',
            'name' => 'Báo cáo doanh thu đăng ký theo ngày đi',
            'page_size' => 'A4',
            'page_orientation' => 'landscape',
            'margin_top' => 6,
            'margin_bottom' => 6,
            'margin_left' => 4,
            'margin_right' => 4,
            'version' => '1.0',
            'content_html' => $this->html(),
            'content_json' => $this->blocks(),
            'css' => $this->css(),
            'data_contract' => [
                'rows' => $rowContract,
                'parameters' => [
                    'p_from_date' => 'date', 'p_to_date' => 'date',
                    'p_from_time' => 'time', 'p_to_time' => 'time',
                    'p_company_id' => 'string', 'p_group_by_booking' => 'boolean',
                    'p_service_ids' => 'string',
                ],
            ],
        ];
    }

    public function columns(): array
    {
        $definitions = [
            ['CodeBooking', 'Mã ĐK', '3.5%', 'center', 'text'],
            ['RoomNumber', 'Phòng', '3%', 'center', 'text'],
            ['Company', 'Công Ty', '7%', 'left', 'text'],
            ['GuestName', 'Tên Khách', '9.5%', 'left', 'text'],
            ['Arrival', 'Ngày Đến', '4.5%', 'center', 'text'],
            ['Departure', 'Ngày Đi', '4.5%', 'center', 'text'],
            ['RoomCharge', 'Tiền Phòng', '4.5%', 'right', 'number'],
            ['ExtraBed', 'PT Thêm Giường', '3.5%', 'right', 'number'],
            ['ExtraPerson', 'PT Thêm Người', '3.5%', 'right', 'number'],
            ['ExtraRoomCharge', 'PT Tiền Phòng', '3.5%', 'right', 'number'],
            ['Tour', 'Vé', '3%', 'right', 'number'],
            ['Transportation', 'Đưa Đón Khách', '3.5%', 'right', 'number'],
            ['Miscel', 'DT Khác (FO)', '3.5%', 'right', 'number'],
            ['Minibar', 'Minibar', '3.5%', 'right', 'number'],
            ['Laundry', 'Giặt Ủi', '3.5%', 'right', 'number'],
            ['Broken', 'Bể Vỡ', '3%', 'right', 'number'],
            ['BreakfastSplitdown', 'AS Từ Tiền Phòng', '4%', 'right', 'number'],
            ['BreakfastCharge', 'Phí AS', '3.5%', 'right', 'number'],
            ['Food_Res', 'Thức Ăn', '4%', 'right', 'number'],
            ['Beverage_Res', 'Đồ Uống', '3.5%', 'right', 'number'],
            ['Other_Res', 'Khác (F&B)', '3%', 'right', 'number'],
            ['OtherRevenue', 'Doanh Thu Khác', '3%', 'right', 'number'],
            ['TotalRevenue', 'Tổng Doanh Thu', '5%', 'right', 'number'],
            ['PaymentMethod', 'Hình Thức Thanh Toán', '4.5%', 'center', 'text'],
        ];

        return array_map(static function (array $column, int $index): array {
            [$field, $header, $width, $align, $format] = $column;
            return [
                'id' => 'departure_revenue_'.$index,
                'field' => $field,
                'header' => $header,
                'value' => 'row.'.$field,
                'width' => $width,
                'align' => $align,
                'format' => $format,
                'headerStyle' => ['backgroundColor' => '#d9deea', 'border' => '1px solid #aeb5c0', 'textAlign' => 'center', 'fontWeight' => 'bold', 'padding' => '2.5px 2px'],
                'cellStyle' => ['border' => '1px solid #aeb5c0', 'textAlign' => $align, 'verticalAlign' => 'middle', 'padding' => '2.5px 2px'],
            ];
        }, $definitions, array_keys($definitions));
    }

    public function blocks(): array
    {
        $columns = $this->columns();
        $numericTotals = array_slice($columns, 6, 17);
        $totalCells = [[
            'id' => 'departure_revenue_total_label', 'type' => 'text',
            'content' => 'Tổng cộng', 'colspan' => 6, 'align' => 'right',
            'style' => ['fontWeight' => 'bold', 'backgroundColor' => '#d9deea'],
        ]];
        foreach ($numericTotals as $column) {
            $totalCells[] = [
                'id' => 'departure_revenue_total_'.$column['field'], 'type' => 'binding',
                'binding' => 'aggregate.rows.sum.'.$column['field'], 'colspan' => 1,
                'align' => 'right', 'format' => 'number',
                'style' => ['fontWeight' => 'bold', 'backgroundColor' => '#d9deea'],
            ];
        }
        $totalCells[] = ['id' => 'departure_revenue_total_payment_method', 'type' => 'text', 'content' => '', 'colspan' => 1];

        return [
            'header' => [
                ['id' => 'departure_revenue_header_band', 'type' => 'columns', 'style' => ['display' => 'flex', 'justifyContent' => 'space-between'], 'columns' => [
                    ['width' => '30%', 'blocks' => [['id' => 'departure_revenue_logo', 'type' => 'text', 'content' => '<div class="hotel-logo">{{hotel.logo}}</div>']]],
                    ['width' => '70%', 'blocks' => [['id' => 'departure_revenue_hotel_info', 'type' => 'text', 'content' => '<div class="hotel-information"><b>Địa chỉ:</b> {{hotel.address}}<br><b>Nhân viên:</b> {{report.generated_by}} &nbsp; <b>Ngày:</b> {{report.generated_at}}</div>']]],
                ]],
                ['id' => 'departure_revenue_divider', 'type' => 'divider', 'content' => '<hr class="header-divider">'],
                ['id' => 'departure_revenue_title', 'type' => 'text', 'content' => '<h1 class="report-title">BÁO CÁO DOANH THU ĐĂNG KÝ THEO NGÀY ĐI</h1>'],
                ['id' => 'departure_revenue_period', 'type' => 'text', 'content' => '<p class="report-period">Từ ngày {{parameters.p_from_date}} {{parameters.p_from_time}} đến ngày {{parameters.p_to_date}} {{parameters.p_to_time}}</p>'],
            ],
            'detail' => [[
                'id' => 'departure_revenue_detail_table', 'type' => 'table',
                'dataSource' => 'rows', 'tableType' => 'dynamic', 'tableStyle' => 'grid',
                'tableClassName' => 'departure-revenue-table', 'hasTwoTierHeader' => true,
                'topHeader' => $this->topHeader(), 'columns' => $columns,
                'customRows' => [[
                    'id' => 'departure_revenue_grand_total', 'scope' => 'table', 'level' => 0,
                    'className' => 'departure-revenue-grand-total', 'cells' => $totalCells,
                ]],
            ]],
            'footer' => [],
        ];
    }

    private function topHeader(): array
    {
        $headers = [];
        foreach (array_slice($this->columns(), 0, 6) as $column) {
            $headers[] = ['label' => $column['header'], 'rowspan' => 2, 'width' => $column['width'], 'align' => 'center'];
        }
        $headers[] = ['label' => 'FO', 'colspan' => 8, 'align' => 'center'];
        $headers[] = ['label' => 'Doanh Thu HK', 'colspan' => 3, 'align' => 'center'];
        $headers[] = ['label' => 'F&B Revenue', 'colspan' => 4, 'align' => 'center'];
        foreach (array_slice($this->columns(), 21, 3) as $column) {
            $headers[] = ['label' => $column['header'], 'rowspan' => 2, 'width' => $column['width'], 'align' => 'center'];
        }

        return $headers;
    }

    public function html(): string
    {
        $blocks = $this->blocks();
        $html = '<div class="report-header-band">';
        foreach ($blocks['header'] as $block) {
            if (($block['type'] ?? '') === 'columns') {
                $html .= '<div class="hotel-header">';
                foreach ($block['columns'] as $column) {
                    $html .= '<div style="width:'.$column['width'].'">'.($column['blocks'][0]['content'] ?? '').'</div>';
                }
                $html .= '</div>';
            } else {
                $html .= $block['content'] ?? '';
            }
        }
        $html .= '</div><table class="departure-revenue-table"><colgroup>';
        foreach ($this->columns() as $column) {
            $html .= '<col style="width:'.$column['width'].'">';
        }
        $html .= '</colgroup><thead><tr>';
        foreach ($this->topHeader() as $header) {
            $span = isset($header['rowspan']) ? ' rowspan="2"' : ' colspan="'.(int) $header['colspan'].'"';
            $html .= '<th'.$span.'>'.$header['label'].'</th>';
        }
        $html .= '</tr><tr>';
        foreach (array_slice($this->columns(), 6, 15) as $column) {
            $html .= '<th>'.$column['header'].'</th>';
        }
        $html .= '</tr></thead><tbody class="pms-detail-rows" data-source="rows"><tr class="pms-detail-row" data-source="rows">';
        foreach ($this->columns() as $column) {
            $value = 'row.'.$column['field'];
            $html .= '<td>{{'.$value.($column['format'] === 'number' ? '|number' : '').'}}</td>';
        }
        $html .= '</tr></tbody><tfoot><tr class="pms-custom-row departure-revenue-grand-total">';
        foreach ($blocks['detail'][0]['customRows'][0]['cells'] as $cell) {
            $colspan = (int) ($cell['colspan'] ?? 1);
            $content = $cell['type'] === 'binding' ? '{{'.$cell['binding'].'|number}}' : ($cell['content'] ?? '');
            $html .= '<td colspan="'.$colspan.'">'.$content.'</td>';
        }
        $html .= '</tr></tfoot></table>';

        return $html;
    }

    public function css(): string
    {
        return <<<'CSS'
body { font-family: "Segoe UI", Arial, sans-serif; color: #111; font-size: 8px; }
.hotel-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 4px; font-size: 9px; }
.hotel-logo img { max-width: 120px; max-height: 48px; object-fit: contain; }
.hotel-information { text-align: right; line-height: 1.4; }
.header-divider { border: 0; border-top: 1px solid #aeb5c0; margin: 4px 0 6px; }
.report-title { text-align: center; font-size: 16px; font-weight: bold; margin: 2px 0 4px; }
.report-period { text-align: center; font-size: 9.5px; margin: 0 0 8px; }
.departure-revenue-table { width: 100%; table-layout: fixed; border-collapse: collapse; border: 1px solid #aeb5c0; font-size: 8px; line-height: 1.15; }
.departure-revenue-table th, .departure-revenue-table td { border: 1px solid #aeb5c0; padding: 2.5px 2px; vertical-align: middle; overflow-wrap: anywhere; }
.departure-revenue-table thead th { background: #d9deea; font-weight: bold; text-align: center; }
.departure-revenue-table tfoot td { background: #d9deea; font-weight: bold; }
@media print { thead { display: table-header-group; } tr { break-inside: avoid; } }
CSS;
    }
};
