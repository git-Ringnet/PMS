<?php

use App\Services\TemplateRendererService;

return new class
{
    public function definition(): array
    {
        return [
            'code' => 'ROOM_FORECAST',
            'report' => 'ROOM_FORECAST_REFERENCE',
            'name' => 'Báo cáo dự đoán bán phòng',
            'page_size' => 'A4',
            'page_orientation' => 'landscape',
            'margin_top' => 10,
            'margin_right' => 10,
            'margin_bottom' => 8,
            'margin_left' => 10,
            'version' => '1.0',
            'content_json' => $this->blocks(),
            'content_html' => $this->html(),
            'css' => $this->css(),
            'columns' => $this->columns(),
            'data_contract' => [
                'rows' => [
                    'Date' => 'string',
                    'DepRooms' => 'integer',
                    'DepAdult' => 'integer',
                    'ArrRooms' => 'integer',
                    'ArrAdult' => 'integer',
                    'OccRooms' => 'integer',
                    'OccAdult' => 'integer',
                    'HouseUse' => 'integer',
                    'FOCAll' => 'integer',
                    'RoomSales' => 'integer',
                    'Revenue' => 'number',
                    'AvgRate' => 'number',
                    'AvgRate2' => 'number',
                    'RoomAvible' => 'integer',
                    'PercentOccupancy' => 'number',
                    'PercentOccupancy1' => 'number',
                    'RevPAR' => 'number',
                ],
                'parameters' => [
                    'p_from_date' => 'string',
                    'p_to_date' => 'string',
                    'p_user' => 'string',
                    'p_branch' => 'string',
                    'p_room_type' => 'string',
                    'p_revenue_detail' => 'integer',
                    'p_include_breakfast' => 'integer',
                ],
            ],
        ];
    }

    public function html(): string
    {
        return '<div class="report-header-band">
  <div class="report-header-grid">
    <div class="hotel-logo">{{hotel.logo}}</div>
    <div class="hotel-information">
      <div><b>{{hotel.name}}</b></div>
      <div><b>Địa chỉ:</b> {{hotel.address}}</div>
      <div><b>Nhân viên:</b> {{report.generated_by}} &nbsp;&nbsp;&nbsp;&nbsp; <b>Ngày:</b> {{report.generated_at}}</div>
    </div>
  </div>
  <div class="header-divider"></div>
  <h1>BÁO CÁO DỰ ĐOÁN BÁN PHÒNG</h1>
  <p class="period"><b>Ngày:</b> {{parameters.p_from_date}} &nbsp; ~ &nbsp; {{parameters.p_to_date}}</p>
</div>
<section class="pms-conditional-block" data-condition-id="room-forecast-fo" data-visible-by="parameters.p_revenue_detail">
'.$this->tableHtml(true).'
</section><!--pms-condition-end:room-forecast-fo-->
<section class="pms-conditional-block" data-condition-id="room-forecast-hk" data-visible-by="parameters.p_revenue_detail" data-visible-when="falsy">
'.$this->tableHtml(false).'
</section><!--pms-condition-end:room-forecast-hk-->
<div class="signatures">
  <span>Lễ Tân Trưởng</span>
  <span>Bộ phận kinh doanh</span>
</div>';
    }

    public function blocks(): array
    {
        return [
            'header' => [
                [
                    'id' => 'room_forecast_header_grid',
                    'type' => 'columns',
                    'style' => [
                        'display' => 'flex',
                        'justifyContent' => 'space-between',
                        'alignItems' => 'flex-start',
                        'marginTop' => '0px',
                        'marginBottom' => '4px',
                        'paddingTop' => '0px',
                        'paddingBottom' => '0px',
                        'paddingLeft' => '0px',
                        'paddingRight' => '0px',
                    ],
                    'columns' => [
                        [
                            'width' => '30%',
                            'blocks' => [[
                                'id' => 'room_forecast_logo',
                                'type' => 'text',
                                'content' => '<div class="hotel-logo">{{hotel.logo}}</div>',
                                'style' => ['minHeight' => '50px'],
                            ]],
                        ],
                        [
                            'width' => '70%',
                            'blocks' => [[
                                'id' => 'room_forecast_hotel_info',
                                'type' => 'text',
                                'content' => '<div class="hotel-information"><div><b>{{hotel.name}}</b></div><div><b>Địa chỉ:</b> {{hotel.address}}</div><div><b>Nhân viên:</b> {{report.generated_by}} &nbsp;&nbsp;&nbsp;&nbsp; <b>Ngày:</b> {{report.generated_at}}</div></div>',
                                'style' => ['textAlign' => 'right', 'fontSize' => '9.5px', 'lineHeight' => '1.5'],
                            ]],
                        ],
                    ],
                ],
                [
                    'id' => 'room_forecast_divider',
                    'type' => 'divider',
                    'content' => '<div class="header-divider"></div>',
                    'style' => ['borderTop' => '1px solid #111111', 'marginTop' => '4px', 'marginBottom' => '10px'],
                ],
                [
                    'id' => 'room_forecast_title',
                    'type' => 'text',
                    'content' => '<h1>BÁO CÁO DỰ ĐOÁN BÁN PHÒNG</h1>',
                    'style' => ['textAlign' => 'center', 'fontWeight' => 'bold', 'fontSize' => '18px', 'marginTop' => '6px', 'marginBottom' => '4px'],
                ],
                [
                    'id' => 'room_forecast_period',
                    'type' => 'text',
                    'content' => '<p class="period"><b>Ngày:</b> {{parameters.p_from_date}} &nbsp; ~ &nbsp; {{parameters.p_to_date}}</p>',
                    'style' => ['textAlign' => 'center', 'fontSize' => '10px', 'fontWeight' => 'bold', 'marginTop' => '2px', 'marginBottom' => '12px'],
                ],
            ],
            'detail' => [
                $this->tableBlock(true),
                $this->tableBlock(false),
            ],
            'footer' => [
                [
                    'id' => 'room_forecast_signatures',
                    'type' => 'columns',
                    'style' => ['display' => 'flex', 'justifyContent' => 'space-around', 'marginTop' => '30px'],
                    'columns' => [
                        ['width' => '50%', 'blocks' => [['type' => 'text', 'content' => '<p style="text-align:center;font-weight:bold;">Lễ Tân Trưởng</p>', 'style' => ['fontSize' => '11px']]]],
                        ['width' => '50%', 'blocks' => [['type' => 'text', 'content' => '<p style="text-align:center;font-weight:bold;">Bộ phận kinh doanh</p>', 'style' => ['fontSize' => '11px']]]],
                    ],
                ],
            ],
        ];
    }

    public function columns(): array
    {
        return [
            $this->column('Date', "Ngày\n(1)", '95px', 'center', null, ['color' => '#16a34a', 'fontWeight' => 'bold']),
            $this->column('DepRooms', "P.Đi\n(2)", '55px'),
            $this->column('DepAdult', "K.Đi\n(3)", '55px'),
            $this->column('ArrRooms', "P.Đến\n(4)", '55px'),
            $this->column('ArrAdult', "K.Đến\n(5)", '55px'),
            $this->column('OccRooms', "P.Ở\n(6)", '55px'),
            $this->column('OccAdult', "K.Ở\n(7)", '55px'),
            $this->column('HouseUse', "Nội bộ\n(8)", '55px'),
            $this->column('FOCAll', "Phòng MP\n(9)", '65px'),
            $this->column('RoomSales', "P.Bán\n(10)", '55px'),
            $this->column('Revenue', "Doanh Thu\n(11)", '110px', 'right', 'number'),
            $this->column('AvgRate', "Giá phòng TB (w/o HU)\n(12)", '95px', 'right', 'number'),
            $this->column('AvgRate2', "Giá phòng TB (w/o HU,FOC)\n(13)", '100px', 'right', 'number'),
            $this->column('RoomAvible', "Phòng có thể bán\n(14)", '75px'),
            $this->column('PercentOccupancy', "Công suất\n(15)", '75px', 'right', 'percent'),
            $this->column('PercentOccupancy1', "Công suất (w/o HU,FOC)\n(16)", '85px', 'right', 'percent'),
            $this->column('RevPAR', "DThu/Tổng phòng\n(17)", '95px', 'right', 'number'),
        ];
    }

    public function css(): string
    {
        return <<<'CSS'
body { font-family: Arial, Helvetica, sans-serif; color: #111111; font-size: 9px; line-height: 1.3; }
.report-header-grid { display: flex; justify-content: space-between; align-items: flex-start; min-height: 50px; }
.hotel-logo img { max-height: 55px; max-width: 190px; object-fit: contain; }
.hotel-information { text-align: right; font-size: 9.5px; line-height: 1.5; }
.header-divider { border-top: 1px solid #111111; margin: 4px 0 10px; }
.report-header-band h1 { font-size: 18px; text-align: center; margin: 6px 0 4px; font-weight: bold; color: #111111; }
.period { text-align: center; font-size: 10px; margin: 2px 0 12px; color: #111111; font-weight: bold; }
.room-forecast-table { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 8.5px; line-height: 1.2; }
.room-forecast-table th, .room-forecast-table td { border: 1px solid #aeb5c0; padding: 4px 2px; vertical-align: middle; }
.room-forecast-table thead th { background: #dee2ed; text-align: center; font-weight: bold; white-space: normal; }
.room-forecast-table .index-row th { color: #64748b; font-size: 7px; padding-top: 1px; padding-bottom: 1px; }
.room-forecast-table td { text-align: right; }
.room-forecast-table td.date { color: #16a34a; font-weight: bold; text-align: center; }
.room-forecast-table tfoot td { background: #dee2ed; font-weight: bold; }
.room-forecast-table .total-label { text-align: center; }
.signatures { display: flex; justify-content: space-around; margin-top: 30px; font-size: 11px; font-weight: bold; }
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
            array_intersect_key($definition, array_flip([
                'page_size', 'page_orientation', 'margin_top', 'margin_bottom', 'margin_left', 'margin_right',
            ]))
        );
    }

    private function tableBlock(bool $financial): array
    {
        return [
            'id' => $financial ? 'room_forecast_fo_table' : 'room_forecast_hk_table',
            'type' => 'table',
            'dataSource' => 'rows',
            'tableType' => 'dynamic',
            'tableStyle' => 'grid',
            'tableClassName' => 'room-forecast-table',
            'visibleWhen' => 'parameters.p_revenue_detail',
            'visibleWhenMode' => $financial ? 'truthy' : 'falsy',
            'style' => [
                'width' => '100%',
                'fontSize' => '8.5px',
                'borderCollapse' => 'collapse',
                'borderColor' => '#aeb5c0',
                'borderWidth' => '1px',
                'borderStyle' => 'solid',
            ],
            'topHeader' => array_map(
                static fn (int $index): array => ['content' => '('.($index + 1).')', 'align' => 'center'],
                array_keys($financial ? $this->columns() : $this->hkColumns())
            ),
            'columns' => $financial ? $this->columns() : $this->hkColumns(),
            'customRows' => [$this->totalRow($financial)],
        ];
    }

    private function tableHtml(bool $financial): string
    {
        $columns = $financial ? $this->columns() : $this->hkColumns();
        $html = '<table class="room-forecast-table"><colgroup>';
        foreach ($columns as $column) {
            $html .= '<col style="width:'.htmlspecialchars((string) $column['width'], ENT_QUOTES, 'UTF-8').'">';
        }
        $html .= '</colgroup><thead><tr class="index-row">';
        foreach ($columns as $index => $column) {
            $html .= '<th>'.($index + 1).'</th>';
        }
        $html .= '</tr><tr>';
        foreach ($columns as $column) {
            $html .= '<th>'.str_replace("\n", '<br>', (string) $column['header']).'</th>';
        }
        $html .= '</tr></thead><tbody><tr class="pms-detail-row" data-source="rows">';
        foreach ($columns as $column) {
            $field = substr((string) $column['value'], 4);
            $class = $field === 'Date' ? ' class="date"' : '';
            $modifier = ($column['format'] ?? '') === 'number' ? '|number' : '';
            $suffix = ($column['format'] ?? '') === 'percent' ? '%' : '';
            $html .= '<td'.$class.'>{{row.'.$field.$modifier.'}}'.$suffix.'</td>';
        }
        $html .= '</tr></tbody><tfoot><tr>';
        foreach ($this->totalRow($financial)['cells'] as $cell) {
            $colspan = max(1, (int) ($cell['colspan'] ?? 1));
            if (($cell['type'] ?? '') === 'binding') {
                $content = '{{'.($cell['binding'] ?? '').(! empty($cell['format']) ? '|'.$cell['format'] : '').'}}';
            } else {
                $content = (string) ($cell['content'] ?? '');
            }
            $html .= '<td colspan="'.$colspan.'">'.$content.'</td>';
        }

        return $html.'</tr></tfoot></table>';
    }

    private function totalRow(bool $financial): array
    {
        $cells = [
            ['type' => 'text', 'content' => 'Tổng: {{aggregate.rows.count}}', 'align' => 'center'],
            ['type' => 'binding', 'binding' => 'aggregate.rows.sum.DepRooms', 'format' => 'number'],
            ['type' => 'binding', 'binding' => 'aggregate.rows.sum.DepAdult', 'format' => 'number'],
            ['type' => 'binding', 'binding' => 'aggregate.rows.sum.ArrRooms', 'format' => 'number'],
            ['type' => 'binding', 'binding' => 'aggregate.rows.sum.ArrAdult', 'format' => 'number'],
            ['type' => 'binding', 'binding' => 'aggregate.rows.sum.OccRooms', 'format' => 'number'],
            ['type' => 'binding', 'binding' => 'aggregate.rows.sum.OccAdult', 'format' => 'number'],
            ['type' => 'binding', 'binding' => 'aggregate.rows.sum.HouseUse', 'format' => 'number'],
            ['type' => 'binding', 'binding' => 'aggregate.rows.sum.FOCAll', 'format' => 'number'],
            ['type' => 'binding', 'binding' => 'aggregate.rows.sum.RoomSales', 'format' => 'number'],
        ];

        if ($financial) {
            $cells[] = ['type' => 'binding', 'binding' => 'aggregate.rows.sum.Revenue', 'format' => 'number'];
            $cells[] = ['type' => 'text', 'content' => '-'];
            $cells[] = ['type' => 'text', 'content' => '-'];
        }

        $cells[] = ['type' => 'text', 'content' => '-'];
        $cells[] = ['type' => 'text', 'content' => '-'];
        $cells[] = ['type' => 'text', 'content' => '-'];
        if ($financial) {
            $cells[] = ['type' => 'text', 'content' => '-'];
        }

        return [
            'id' => $financial ? 'room_forecast_fo_total' : 'room_forecast_hk_total',
            'scope' => 'table',
            'className' => 'report-grand-total-row',
            'cells' => $cells,
        ];
    }

    private function hkColumns(): array
    {
        $all = $this->columns();

        return array_values(array_filter($all, static function (array $column): bool {
            return ! in_array(substr((string) $column['value'], 4), ['Revenue', 'AvgRate', 'AvgRate2', 'RevPAR'], true);
        }));
    }

    private function column(string $field, string $header, string $width, string $align = 'right', ?string $format = null, array $cellStyle = []): array
    {
        return [
            'id' => 'room_forecast_'.$field,
            'field' => $field,
            'header' => $header,
            'value' => 'row.'.$field,
            'width' => $width,
            'align' => $align,
            'format' => $format,
            'headerStyle' => [
                'backgroundColor' => '#dee2ed',
                'border' => '1px solid #aeb5c0',
                'textAlign' => 'center',
                'fontWeight' => 'bold',
                'padding' => '4px 2px',
                'fontSize' => '8.5px',
                'color' => '#111111',
            ],
            'cellStyle' => array_merge([
                'border' => '1px solid #cbd5e1',
                'textAlign' => $align,
                'padding' => '4px 2px',
                'fontSize' => '8.5px',
                'color' => '#111111',
            ], $cellStyle),
        ];
    }
};
