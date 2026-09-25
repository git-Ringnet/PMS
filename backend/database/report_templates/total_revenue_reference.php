<?php

/**
 * Reference Designer template for TOTAL_REVENUE phase 1.
 *
 * This is the Sheet 72 / sp_292 folio-by-booking layout. The setup-tree
 * revenue model (AT7620/AT7621) is intentionally outside this template.
 */
return new class
{
    public function definition(): array
    {
        $blocks = $this->blocks();

        return [
            'code' => 'TOTAL_REVENUE',
            'name' => 'Báo cáo tổng doanh thu',
            'report' => 'TOTAL_REVENUE_REFERENCE',
            'page_size' => 'A4',
            'page_orientation' => 'landscape',
            'margin_top' => 8,
            'margin_right' => 8,
            'margin_bottom' => 8,
            'margin_left' => 8,
            'version' => '1.0',
            'content_json' => $blocks,
            'content_html' => $this->html(),
            'css' => $this->css(),
            'columns' => $this->columns(),
            'data_contract' => [
                'rows' => [
                    'stt' => 'integer',
                    'booking_code' => 'string',
                    'guest_name_rooms' => 'string',
                    'company_name' => 'string',
                    'arrival_date_display' => 'string',
                    'departure_date_display' => 'string',
                    'room_count' => 'number',
                    'room_revenue' => 'number',
                    'extra_room_revenue' => 'number',
                    'minibar_revenue' => 'number',
                    'laundry_revenue' => 'number',
                    'damage_revenue' => 'number',
                    'fb_revenue' => 'number',
                    'other_service_revenue' => 'number',
                    'daily_total_revenue' => 'number',
                    'previous_days_revenue' => 'number',
                    'grand_total_revenue' => 'number',
                    'paid_cash' => 'number',
                    'paid_bank' => 'number',
                    'paid_commission' => 'number',
                    'paid_debt' => 'number',
                    'inhouse_balance' => 'number',
                ],
                'parameters' => [
                    'p_date' => 'date',
                    'p_company_id' => 'integer',
                    'p_booking_id' => 'integer',
                ],
            ],
        ];
    }

    public function columns(): array
    {
        return [
            ['field' => 'stt', 'header' => 'STT', 'width' => '3%', 'align' => 'center', 'format' => 'number'],
            ['field' => 'booking_code', 'header' => 'Mã đăng ký', 'width' => '5%', 'align' => 'center', 'format' => 'text'],
            ['field' => 'guest_name_rooms', 'header' => 'Tên khách', 'width' => '11%', 'align' => 'left', 'format' => 'text'],
            ['field' => 'company_name', 'header' => 'Đơn vị', 'width' => '7%', 'align' => 'left', 'format' => 'text'],
            ['field' => 'arrival_date_display', 'header' => 'Ngày đến', 'width' => '5%', 'align' => 'center', 'format' => 'text'],
            ['field' => 'departure_date_display', 'header' => 'Ngày đi', 'width' => '5%', 'align' => 'center', 'format' => 'text'],
            ['field' => 'room_count', 'header' => 'SP', 'width' => '3%', 'align' => 'right', 'format' => 'number'],
            ['field' => 'room_revenue', 'header' => 'Tiền phòng', 'width' => '6%', 'align' => 'right', 'format' => 'number'],
            ['field' => 'extra_room_revenue', 'header' => 'Phụ thu', 'width' => '5%', 'align' => 'right', 'format' => 'number'],
            ['field' => 'minibar_revenue', 'header' => 'Minibar', 'width' => '5%', 'align' => 'right', 'format' => 'number'],
            ['field' => 'laundry_revenue', 'header' => 'Giặt', 'width' => '4%', 'align' => 'right', 'format' => 'number'],
            ['field' => 'damage_revenue', 'header' => 'Bể vỡ', 'width' => '4%', 'align' => 'right', 'format' => 'number'],
            ['field' => 'fb_revenue', 'header' => 'Nhà hàng', 'width' => '5%', 'align' => 'right', 'format' => 'number'],
            ['field' => 'other_service_revenue', 'header' => 'Dịch vụ', 'width' => '5%', 'align' => 'right', 'format' => 'number'],
            ['field' => 'daily_total_revenue', 'header' => 'Tổng DT', 'width' => '6%', 'align' => 'right', 'format' => 'number'],
            ['field' => 'previous_days_revenue', 'header' => 'DT ngày trước', 'width' => '6%', 'align' => 'right', 'format' => 'number'],
            ['field' => 'grand_total_revenue', 'header' => 'Tổng cộng', 'width' => '6%', 'align' => 'right', 'format' => 'number'],
            ['field' => 'paid_cash', 'header' => 'TM', 'width' => '5%', 'align' => 'right', 'format' => 'number'],
            ['field' => 'paid_bank', 'header' => 'CK', 'width' => '5%', 'align' => 'right', 'format' => 'number'],
            ['field' => 'paid_commission', 'header' => 'HH', 'width' => '4%', 'align' => 'right', 'format' => 'number'],
            ['field' => 'paid_debt', 'header' => 'Còn nợ', 'width' => '5%', 'align' => 'right', 'format' => 'number'],
            ['field' => 'inhouse_balance', 'header' => 'Phòng còn ở', 'width' => '6%', 'align' => 'right', 'format' => 'number'],
        ];
    }

    public function blocks(): array
    {
        $columns = array_map(static function (array $column, int $index): array {
            return [
                'id' => 'total_revenue_column_'.$index,
                'field' => $column['field'],
                'header' => $column['header'],
                'value' => 'row.'.$column['field'],
                'width' => $column['width'],
                'align' => $column['align'],
                'format' => $column['format'],
                'headerStyle' => [
                    'backgroundColor' => '#d9deea',
                    'border' => '1px solid #9ca3af',
                    'textAlign' => 'center',
                    'fontWeight' => 'bold',
                    'fontSize' => '8px',
                ],
                'cellStyle' => [
                    'border' => '1px solid #9ca3af',
                    'textAlign' => $column['align'],
                    'padding' => '3px 2px',
                    'fontSize' => '8px',
                ],
            ];
        }, $this->columns(), array_keys($this->columns()));

        return [
            'header' => [
                [
                    'id' => 'total_revenue_header',
                    'type' => 'columns',
                    'style' => ['display' => 'flex', 'justifyContent' => 'space-between', 'alignItems' => 'flex-start'],
                    'columns' => [
                        ['width' => '30%', 'blocks' => [['id' => 'total_revenue_logo', 'type' => 'text', 'content' => '<div class="hotel-logo">{{hotel.logo}}</div>']]],
                        ['width' => '70%', 'align' => 'right', 'blocks' => [['id' => 'total_revenue_meta', 'type' => 'text', 'content' => '<div class="hotel-information"><b>{{hotel.name}}</b><br>Địa chỉ: {{hotel.address}}<br>Người dùng: {{report.generated_by}}</div>']]],
                    ],
                ],
                ['id' => 'total_revenue_title', 'type' => 'text', 'content' => '<h1 class="total-revenue-title">BÁO CÁO DOANH THU</h1>'],
                ['id' => 'total_revenue_period', 'type' => 'text', 'content' => '<p class="total-revenue-period"><b>Ngày:</b> {{parameters.p_date}}</p>'],
            ],
            'detail' => [[
                'id' => 'total_revenue_table',
                'type' => 'table',
                'dataSource' => 'rows',
                'tableType' => 'dynamic',
                'tableStyle' => 'grid',
                'tableClassName' => 'total-revenue-table',
                'hasTwoTierHeader' => true,
                'topHeader' => $this->topHeader(),
                'tier2Headers' => $this->tier2Headers(),
                'columns' => $columns,
                'customRows' => [[
                    'id' => 'total_revenue_grand_total',
                    'scope' => 'table',
                    'level' => 0,
                    'className' => 'total-revenue-grand-total',
                    'cells' => $this->totalCells(),
                ]],
            ]],
            'footer' => [
                ['id' => 'total_revenue_location_date', 'type' => 'text', 'content' => '<div class="total-revenue-location">{{hotel.city}}, Ngày {{parameters.p_date}}</div>'],
                ['id' => 'total_revenue_signatures', 'type' => 'text', 'content' => '<div class="total-revenue-signatures"><span>Chữ Ký Người Lập</span><span>Trưởng Bộ Phận</span><span>Kế Toán</span><span>Tổng Quản Lý</span><span>Giám Đốc</span></div>'],
            ],
        ];
    }

    private function topHeader(): array
    {
        return [
            ['label' => 'STT', 'rowspan' => 2],
            ['label' => 'Mã đăng ký', 'rowspan' => 2],
            ['label' => 'Tên khách', 'rowspan' => 2],
            ['label' => 'Đơn vị', 'rowspan' => 2],
            ['label' => 'Ngày đến', 'rowspan' => 2],
            ['label' => 'Ngày đi', 'rowspan' => 2],
            ['label' => 'SP', 'rowspan' => 2],
            ['label' => 'Các khoản thu trong ngày', 'colspan' => 7],
            ['label' => 'Tổng DT', 'rowspan' => 2],
            ['label' => 'DT ngày trước', 'rowspan' => 2],
            ['label' => 'Tổng cộng', 'rowspan' => 2],
            ['label' => 'Phòng đã trả', 'colspan' => 4],
            ['label' => 'Phòng còn ở', 'rowspan' => 2],
        ];
    }

    private function tier2Headers(): array
    {
        return [
            ['label' => 'Tiền phòng'],
            ['label' => 'Phụ thu'],
            ['label' => 'Minibar'],
            ['label' => 'Giặt'],
            ['label' => 'Bể vỡ'],
            ['label' => 'Nhà hàng'],
            ['label' => 'Dịch vụ'],
            ['label' => 'TM'],
            ['label' => 'CK'],
            ['label' => 'HH'],
            ['label' => 'Còn nợ'],
        ];
    }

    private function totalCells(): array
    {
        $fields = array_column($this->columns(), 'field');
        $cells = [[
            'id' => 'total_revenue_total_label',
            'type' => 'text',
            'content' => 'Tổng số BK: {{aggregate.rows.count}}',
            'colspan' => 6,
            'align' => 'left',
        ]];

        foreach (array_slice($fields, 6) as $field) {
            $cells[] = [
                'id' => 'total_revenue_total_'.$field,
                'type' => 'binding',
                'binding' => 'aggregate.rows.sum.'.$field,
                'format' => 'number',
                'align' => 'right',
            ];
        }

        return $cells;
    }

    public function html(): string
    {
        $columns = $this->columns();
        $html = '<div class="total-revenue-header"><div class="total-revenue-hotel"><div class="hotel-logo">{{hotel.logo}}</div><div class="hotel-information"><b>{{hotel.name}}</b><br>Địa chỉ: {{hotel.address}}<br>Người dùng: {{report.generated_by}}</div></div><h1 class="total-revenue-title">BÁO CÁO DOANH THU</h1><p class="total-revenue-period"><b>Ngày:</b> {{parameters.p_date}}</p></div>';
        $html .= '<table class="total-revenue-table"><colgroup>';
        foreach ($columns as $column) {
            $html .= '<col style="width:'.htmlspecialchars($column['width'], ENT_QUOTES, 'UTF-8').'">';
        }
        $html .= '</colgroup><thead><tr class="total-revenue-header-row">';
        foreach ($this->topHeader() as $header) {
            $attributes = isset($header['rowspan']) ? ' rowspan="2"' : ' colspan="'.(int) $header['colspan'].'"';
            $html .= '<th'.$attributes.'>'.htmlspecialchars($header['label'], ENT_QUOTES, 'UTF-8').'</th>';
        }
        $html .= '</tr><tr class="total-revenue-subheader-row">';
        foreach ($this->tier2Headers() as $header) {
            $html .= '<th>'.htmlspecialchars($header['label'], ENT_QUOTES, 'UTF-8').'</th>';
        }
        $html .= '</tr></thead><tbody><tr class="pms-detail-row" data-source="rows">';
        foreach ($columns as $column) {
            $value = '{{row.'.$column['field'].(($column['format'] ?? '') === 'number' ? '|number' : '').'}}';
            $html .= '<td class="'.htmlspecialchars($column['align'], ENT_QUOTES, 'UTF-8').'">'.$value.'</td>';
        }
        $html .= '</tr></tbody><tfoot><tr class="pms-custom-row total-revenue-grand-total">';
        $html .= '<td colspan="6">Tổng số BK: {{aggregate.rows.count}}</td>';
        foreach (array_slice(array_column($columns, 'field'), 6) as $field) {
            $html .= '<td class="right">{{aggregate.rows.sum.'.htmlspecialchars($field, ENT_QUOTES, 'UTF-8').'|number}}</td>';
        }
        $html .= '</tr></tfoot></table><div class="total-revenue-location">{{hotel.city}}, Ngày {{parameters.p_date}}</div><div class="total-revenue-signatures"><span>Chữ Ký Người Lập</span><span>Trưởng Bộ Phận</span><span>Kế Toán</span><span>Tổng Quản Lý</span><span>Giám Đốc</span></div>';

        return $html;
    }

    public function css(): string
    {
        return <<<'CSS'
body { font-family: Arial, Helvetica, sans-serif; color: #111827; font-size: 9px; }
.total-revenue-hotel { display: flex; justify-content: space-between; align-items: flex-start; min-height: 52px; }
.hotel-logo img { max-width: 130px; max-height: 50px; object-fit: contain; }
.hotel-information { text-align: right; font-size: 9px; line-height: 1.4; }
.total-revenue-title { text-align: center; font-size: 17px; font-weight: bold; margin: 8px 0 3px; text-transform: uppercase; }
.total-revenue-period { text-align: center; font-size: 10px; font-style: italic; margin: 0 0 8px; }
.total-revenue-table { width: 100%; table-layout: fixed; border-collapse: collapse; border: 1px solid #9ca3af; font-size: 8px; }
.total-revenue-table th, .total-revenue-table td { border: 1px solid #9ca3af; padding: 3px 2px; vertical-align: middle; overflow-wrap: anywhere; }
.total-revenue-table th { background: #d9deea; text-align: center; font-weight: bold; }
.total-revenue-table td.right { text-align: right; }
.total-revenue-table td.center { text-align: center; }
.total-revenue-table td.left { text-align: left; }
.total-revenue-table tfoot td { background: #f3f4f6; font-weight: bold; border-top: 2px solid #374151; }
.total-revenue-location { text-align: right; font-style: italic; margin-top: 12px; }
.total-revenue-signatures { display: flex; justify-content: space-between; margin-top: 34px; text-align: center; font-size: 9px; font-weight: bold; }
.total-revenue-signatures span { width: 19%; }
@media print { thead { display: table-header-group; } tr { break-inside: avoid; } }
CSS;
    }
};
