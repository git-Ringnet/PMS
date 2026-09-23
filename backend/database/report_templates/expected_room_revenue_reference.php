<?php

/** Designer v1 reference for the Night Audit expected room revenue report. */
return new class
{
    public function definition(): array
    {
        return [
            'report' => 'EXPECTED_ROOM_REVENUE_REFERENCE',
            'name' => 'Báo cáo dự kiến doanh thu tiền phòng',
            'page_size' => 'A4', 'page_orientation' => 'landscape',
            'margin_top' => 8, 'margin_right' => 5, 'margin_bottom' => 8, 'margin_left' => 5,
            'version' => '1.0',
            'content_html' => $this->html(),
            'content_json' => $this->blocks(),
            'css' => $this->css(),
            'data_contract' => [
                'rows' => [
                    'STT' => 'integer', 'BookingCode' => 'string', 'Guest' => 'string', 'Room' => 'string',
                    'ArrivalDate' => 'string', 'DepartureDate' => 'string', 'ServiceId' => 'string',
                    'ServiceName' => 'string', 'RateTotal' => 'number', 'Company' => 'string', 'NoteBooking' => 'string',
                ],
                'parameters' => ['p_date' => 'date'],
            ],
        ];
    }

    public function columns(): array
    {
        return [
            ['STT', 'STT', '4%', 'center', 'text'],
            ['BookingCode', 'Mã BK', '8%', 'center', 'text'],
            ['Guest', 'Khách', '16%', 'left', 'text'],
            ['Room', 'Phòng', '6%', 'center', 'text'],
            ['ArrivalDate', 'Ngày Đến', '8%', 'center', 'text'],
            ['DepartureDate', 'Ngày Đi', '8%', 'center', 'text'],
            ['ServiceId', 'Dịch Vụ', '6%', 'center', 'text'],
            ['ServiceName', 'Mô Tả Dịch Vụ', '16%', 'left', 'text'],
            ['RateTotal', 'Tổng', '9%', 'right', 'number'],
            ['Company', 'Công Ty', '10%', 'left', 'text'],
            ['NoteBooking', 'Ghi Chú', '9%', 'left', 'text'],
        ];
    }

    public function blocks(): array
    {
        $totalCells = [
            ['id' => 'expected_room_revenue_total_label', 'type' => 'text', 'content' => 'Tổng Cộng:', 'colspan' => 8, 'align' => 'right', 'style' => ['fontWeight' => 'bold', 'backgroundColor' => '#d9deea']],
            ['id' => 'expected_room_revenue_total_amount', 'type' => 'binding', 'binding' => 'aggregate.rows.sum.RateTotal', 'colspan' => 1, 'align' => 'right', 'format' => 'number', 'style' => ['fontWeight' => 'bold', 'backgroundColor' => '#d9deea']],
            ['id' => 'expected_room_revenue_total_company', 'type' => 'text', 'content' => '', 'colspan' => 2, 'style' => ['backgroundColor' => '#d9deea']],
        ];

        return [
            'header' => [
                ['id' => 'expected_room_revenue_header_band', 'type' => 'columns', 'style' => ['display' => 'flex', 'alignItems' => 'flex-start'], 'columns' => [
                    ['width' => '20%', 'blocks' => [['id' => 'expected_room_revenue_logo', 'type' => 'text', 'content' => '<div class="hotel-logo">{{hotel.logo}}</div>']]],
                    ['width' => '80%', 'blocks' => [['id' => 'expected_room_revenue_header_info', 'type' => 'text', 'content' => '<div class="hotel-information"><b>{{hotel.name}}</b><br>{{hotel.address}}</div>']]],
                ]],
                ['id' => 'expected_room_revenue_title', 'type' => 'text', 'content' => '<h1 class="report-title">BÁO CÁO DOANH THU DỰ KIẾN</h1>'],
                ['id' => 'expected_room_revenue_date', 'type' => 'text', 'content' => '<p class="report-date"><b>Ngày:</b> {{parameters.p_date}}</p>'],
            ],
            'detail' => [[
                'id' => 'expected_room_revenue_table', 'type' => 'table', 'dataSource' => 'rows',
                'tableType' => 'dynamic', 'tableStyle' => 'grid', 'tableClassName' => 'expected-room-revenue-table',
                'columns' => array_map(static function (array $column, int $i): array {
                    [$field, $header, $width, $align, $format] = $column;
                    return [
                        'id' => 'expected_room_revenue_column_'.$i, 'field' => $field, 'header' => $header,
                        'value' => 'row.'.$field, 'width' => $width, 'align' => $align, 'format' => $format,
                        'headerStyle' => ['backgroundColor' => '#d9deea', 'border' => '1px solid #aeb5c0', 'textAlign' => 'center', 'fontWeight' => 'bold'],
                        'cellStyle' => ['border' => '1px solid #aeb5c0', 'textAlign' => $align, 'verticalAlign' => 'middle'],
                    ];
                }, $this->columns(), array_keys($this->columns())),
                'customRows' => [[
                    'id' => 'expected_room_revenue_grand_total', 'scope' => 'table', 'level' => 0,
                    'className' => 'expected-room-revenue-grand-total', 'cells' => $totalCells,
                ]],
            ]],
            'footer' => [],
        ];
    }

    public function html(): string
    {
        $html = '<div class="report-header-band"><div class="hotel-header"><div class="hotel-logo">{{hotel.logo}}</div><div class="hotel-information"><b>{{hotel.name}}</b><br>{{hotel.address}}</div></div><h1 class="report-title">BÁO CÁO DOANH THU DỰ KIẾN</h1><p class="report-date"><b>Ngày:</b> {{parameters.p_date}}</p></div>';
        $html .= '<table class="expected-room-revenue-table"><colgroup>';
        foreach ($this->columns() as $column) {
            $html .= '<col style="width:'.$column[2].'">';
        }
        $html .= '</colgroup><thead><tr>';
        foreach ($this->columns() as $column) {
            $html .= '<th>'.$column[1].'</th>';
        }
        $html .= '</tr></thead><tbody><tr class="pms-detail-row" data-source="rows">';
        foreach ($this->columns() as $column) {
            $html .= '<td>{{row.'.$column[0].($column[4] === 'number' ? '|number' : '').'}}</td>';
        }
        $html .= '</tr></tbody><tfoot><tr class="pms-custom-row expected-room-revenue-grand-total"><td colspan="8">Tổng Cộng:</td><td>{{aggregate.rows.sum.RateTotal|number}}</td><td colspan="2"></td></tr></tfoot></table>';

        return $html;
    }

    public function css(): string
    {
        return <<<'CSS'
body { font-family: Arial, Helvetica, sans-serif; color: #111; font-size: 9px; }
.hotel-header { display: flex; justify-content: space-between; align-items: flex-start; min-height: 54px; }
.hotel-logo img { max-width: 130px; max-height: 50px; object-fit: contain; }
.hotel-information { text-align: right; font-size: 9px; line-height: 1.4; }
.report-title { text-align: center; font-size: 18px; font-weight: bold; margin: 8px 0; }
.report-date { text-align: center; margin: 0 0 10px; }
.expected-room-revenue-table { width: 100%; table-layout: fixed; border-collapse: collapse; border: 1px solid #aeb5c0; font-size: 9px; }
.expected-room-revenue-table th, .expected-room-revenue-table td { border: 1px solid #aeb5c0; padding: 3px 4px; vertical-align: middle; overflow-wrap: anywhere; }
.expected-room-revenue-table thead th { background: #d9deea; text-align: center; font-weight: bold; }
.expected-room-revenue-table tfoot td { background: #d9deea; font-weight: bold; }
@media print { thead { display: table-header-group; } tr { break-inside: avoid; } }
CSS;
    }
};
