<?php

use App\Services\TemplateRendererService;

return new class
{
    public function definition(): array
    {
        return [
            'code' => 'ROOM_RATE_STATISTICS',
            'name' => 'Báo cáo thống kê mã giá phòng',
            'report' => 'ROOM_RATE_STATISTICS_REFERENCE',
            'page_size' => 'A4',
            'page_orientation' => 'landscape',
            'margin_top' => 8,
            'margin_right' => 6,
            'margin_bottom' => 8,
            'margin_left' => 6,
            'version' => '1.0',
            'content_html' => $this->html(),
            'content_json' => $this->blocks(),
            'css' => $this->css(),
            'columns' => $this->columns(),
            'data_contract' => [
                'rows' => [
                    'Index' => 'integer',
                    'BookingCode' => 'string',
                    'BookingName' => 'string',
                    'CompanyName' => 'string',
                    'ArrivalDate' => 'string',
                    'DepartureDate' => 'string',
                    'NumOfDays' => 'integer',
                    'Room' => 'string',
                    'Adults' => 'integer',
                    'Children' => 'integer',
                    'RoomType' => 'string',
                    'RateCode' => 'string',
                    'RateCodeDescription' => 'string',
                ],
                'parameters' => [
                    'p_from_date' => 'string',
                    'p_to_date' => 'string',
                    'p_rate_code' => 'string',
                ],
            ],
        ];
    }

    public function html(): string
    {
        return <<<'HTML'
<div class="report-header-band">
  <div class="report-header-grid">
    <div class="hotel-logo">{{hotel.logo}}</div>
    <div class="hotel-information">
      <div><b>Địa chỉ:</b> {{hotel.address}}</div>
      <div><b>Nhân viên:</b> {{report.generated_by}} &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <b>Ngày:</b> {{report.generated_at}}</div>
    </div>
  </div>
  <div class="header-divider"></div>
  <h1>BÁO CÁO THỐNG KÊ MÃ GIÁ PHÒNG</h1>
  <p class="period"><b>Ngày:</b> {{parameters.p_from_date}} &nbsp; ~ &nbsp; {{parameters.p_to_date}}</p>
</div>
<table class="room-rate-statistics-table">
  <colgroup>
    <col style="width:4%">
    <col style="width:8%">
    <col style="width:20%">
    <col style="width:14%">
    <col style="width:8%">
    <col style="width:8%">
    <col style="width:5%">
    <col style="width:6%">
    <col style="width:6%">
    <col style="width:5%">
    <col style="width:8%">
    <col style="width:8%">
  </colgroup>
  <thead>
    <tr>
      <th>Mã</th>
      <th>Mã ĐK</th>
      <th>Tên Đăng Ký</th>
      <th>Công Ty</th>
      <th>Ngày Đến</th>
      <th>Ngày Đi</th>
      <th>Đêm</th>
      <th>Phòng</th>
      <th>Người<br>Lớn</th>
      <th>Trẻ<br>Em</th>
      <th>Loại Phòng</th>
      <th>Mã Giá Phòng</th>
    </tr>
  </thead>
  <tbody class="pms-grouped-rows" data-source="rows" data-group-by="RateCode">
    <tr class="pms-group-header">
      <td colspan="12" class="bold">Mã Giá Phòng &nbsp;&nbsp; {{row.RateCode}}</td>
    </tr>
    <tr class="pms-detail-row">
      <td class="center">{{row.Index}}</td>
      <td class="center booking-code">{{row.BookingCode}}</td>
      <td>{{row.BookingName}}</td>
      <td>{{row.CompanyName}}</td>
      <td class="center">{{row.ArrivalDate}}</td>
      <td class="center">{{row.DepartureDate}}</td>
      <td class="center">{{row.NumOfDays|number}}</td>
      <td class="center">{{row.Room}}</td>
      <td class="center">{{row.Adults|number}}</td>
      <td class="center">{{row.Children|number}}</td>
      <td class="center">{{row.RoomType}}</td>
      <td class="center">{{row.RateCode}}</td>
    </tr>
    <tr class="pms-group-footer">
      <td colspan="6" class="total-label">Tổng</td>
      <td class="center bold">{{group.sum.NumOfDays|number}}</td>
      <td class="center bold">{{group.count|number}}</td>
      <td class="center bold">{{group.sum.Adults|number}}</td>
      <td class="center bold">{{group.sum.Children|number}}</td>
      <td colspan="2"></td>
    </tr>
  </tbody>
  <tfoot>
    <tr class="report-grand-total-row">
      <td colspan="6" class="total-label">Tổng</td>
      <td class="center bold">{{aggregate.rows.sum.NumOfDays|number}}</td>
      <td class="center bold">{{aggregate.rows.count|number}}</td>
      <td class="center bold">{{aggregate.rows.sum.Adults|number}}</td>
      <td class="center bold">{{aggregate.rows.sum.Children|number}}</td>
      <td colspan="2"></td>
    </tr>
  </tfoot>
</table>
HTML;
    }

    public function blocks(): array
    {
        return [
            'header' => [
                [
                    'id' => 'room_rate_header_grid',
                    'type' => 'columns',
                    'style' => [
                        'display' => 'flex',
                        'justifyContent' => 'space-between',
                        'alignItems' => 'flex-start',
                        'marginTop' => '0px',
                        'marginBottom' => '4px',
                        'marginLeft' => '0px',
                        'marginRight' => '0px',
                        'paddingTop' => '0px',
                        'paddingBottom' => '0px',
                        'paddingLeft' => '0px',
                        'paddingRight' => '0px',
                    ],
                    'columns' => [
                        [
                            'width' => '30%',
                            'blocks' => [[
                                'id' => 'room_rate_logo',
                                'type' => 'text',
                                'content' => '<div class="hotel-logo">{{hotel.logo}}</div>',
                                'style' => ['minHeight' => '50px', 'marginTop' => '0px', 'marginBottom' => '0px'],
                            ]],
                        ],
                        [
                            'width' => '70%',
                            'blocks' => [[
                                'id' => 'room_rate_hotel_info',
                                'type' => 'text',
                                'content' => '<div class="hotel-information"><div><b>Địa chỉ:</b> {{hotel.address}}</div><div><b>Nhân viên:</b> {{report.generated_by}} &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <b>Ngày:</b> {{report.generated_at}}</div></div>',
                                'style' => ['textAlign' => 'right', 'fontSize' => '9.5px', 'lineHeight' => '1.5', 'marginTop' => '0px', 'marginBottom' => '0px'],
                            ]],
                        ],
                    ],
                ],
                [
                    'id' => 'room_rate_divider',
                    'type' => 'divider',
                    'content' => '<div class="header-divider"></div>',
                    'style' => ['borderTop' => '1px solid #111111', 'marginTop' => '4px', 'marginBottom' => '10px'],
                ],
                [
                    'id' => 'room_rate_title',
                    'type' => 'text',
                    'content' => '<h1 style="text-align: center; font-size: 18px; font-weight: bold; margin: 6px 0 4px; color: #111111;">BÁO CÁO THỐNG KÊ MÃ GIÁ PHÒNG</h1>',
                    'style' => ['textAlign' => 'center', 'fontWeight' => 'bold', 'fontSize' => '18px', 'color' => '#111111', 'marginTop' => '6px', 'marginBottom' => '4px'],
                ],
                [
                    'id' => 'room_rate_period',
                    'type' => 'text',
                    'content' => '<p class="period" style="text-align: center; font-size: 10px; margin: 2px 0 12px; color: #111111; font-weight: bold;"><b>Ngày:</b> {{parameters.p_from_date}} &nbsp; ~ &nbsp; {{parameters.p_to_date}}</p>',
                    'style' => ['textAlign' => 'center', 'fontSize' => '10px', 'fontWeight' => 'bold', 'color' => '#111111', 'marginTop' => '2px', 'marginBottom' => '12px'],
                ],
            ],
            'detail' => [[
                'id' => 'room_rate_table',
                'type' => 'table',
                'dataSource' => 'rows',
                'tableType' => 'dynamic',
                'tableClassName' => 'room-rate-statistics-table',
                'style' => [
                    'width' => '100%',
                    'fontSize' => '9.5px',
                    'borderCollapse' => 'collapse',
                    'borderColor' => '#aeb5c0',
                    'borderWidth' => '1px',
                    'borderStyle' => 'solid',
                    'marginTop' => '4px',
                    'marginBottom' => '0px',
                    'marginLeft' => '0px',
                    'marginRight' => '0px',
                    'paddingTop' => '0px',
                    'paddingBottom' => '0px',
                    'paddingLeft' => '0px',
                    'paddingRight' => '0px',
                    'backgroundColor' => '#ffffff',
                ],
                'grouping' => [
                    ['id' => 'room_rate_group', 'field' => 'RateCode', 'label' => 'Mã Giá Phòng: {{row.RateCode}}', 'color' => '#111111'],
                ],
                'columns' => $this->columns(),
                'customRows' => [
                    [
                        'id' => 'room_rate_subtotal_row',
                        'enabledBy' => '',
                        'scope' => 'group',
                        'level' => 0,
                        'className' => 'pms-group-footer',
                        'cells' => [
                            [
                                'id' => 'sub_label',
                                'type' => 'text',
                                'content' => 'Tổng',
                                'colspan' => 6,
                                'align' => 'right',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'right', 'backgroundColor' => '#ffffff', 'padding' => '4px 5px'],
                            ],
                            [
                                'id' => 'sub_num_days',
                                'type' => 'binding',
                                'binding' => 'group.sum.NumOfDays',
                                'colspan' => 1,
                                'align' => 'center',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'center', 'backgroundColor' => '#ffffff', 'padding' => '4px 5px'],
                            ],
                            [
                                'id' => 'sub_room_count',
                                'type' => 'binding',
                                'binding' => 'group.count',
                                'colspan' => 1,
                                'align' => 'center',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'center', 'backgroundColor' => '#ffffff', 'padding' => '4px 5px'],
                            ],
                            [
                                'id' => 'sub_adults',
                                'type' => 'binding',
                                'binding' => 'group.sum.Adults',
                                'colspan' => 1,
                                'align' => 'center',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'center', 'backgroundColor' => '#ffffff', 'padding' => '4px 5px'],
                            ],
                            [
                                'id' => 'sub_children',
                                'type' => 'binding',
                                'binding' => 'group.sum.Children',
                                'colspan' => 1,
                                'align' => 'center',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'center', 'backgroundColor' => '#ffffff', 'padding' => '4px 5px'],
                            ],
                            [
                                'id' => 'sub_empty',
                                'type' => 'text',
                                'content' => '',
                                'colspan' => 2,
                                'align' => 'center',
                                'style' => ['backgroundColor' => '#ffffff', 'padding' => '4px 5px'],
                            ],
                        ],
                    ],
                    [
                        'id' => 'room_rate_grand_total_row',
                        'enabledBy' => '',
                        'scope' => 'table',
                        'level' => 0,
                        'className' => 'report-grand-total-row',
                        'cells' => [
                            [
                                'id' => 'grand_label',
                                'type' => 'text',
                                'content' => 'Tổng',
                                'colspan' => 6,
                                'align' => 'right',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'right', 'backgroundColor' => '#d9deea', 'padding' => '4px 5px'],
                            ],
                            [
                                'id' => 'grand_num_days',
                                'type' => 'binding',
                                'binding' => 'aggregate.rows.sum.NumOfDays',
                                'colspan' => 1,
                                'align' => 'center',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'center', 'backgroundColor' => '#d9deea', 'padding' => '4px 5px'],
                            ],
                            [
                                'id' => 'grand_room_count',
                                'type' => 'binding',
                                'binding' => 'aggregate.rows.count',
                                'colspan' => 1,
                                'align' => 'center',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'center', 'backgroundColor' => '#d9deea', 'padding' => '4px 5px'],
                            ],
                            [
                                'id' => 'grand_adults',
                                'type' => 'binding',
                                'binding' => 'aggregate.rows.sum.Adults',
                                'colspan' => 1,
                                'align' => 'center',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'center', 'backgroundColor' => '#d9deea', 'padding' => '4px 5px'],
                            ],
                            [
                                'id' => 'grand_children',
                                'type' => 'binding',
                                'binding' => 'aggregate.rows.sum.Children',
                                'colspan' => 1,
                                'align' => 'center',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'center', 'backgroundColor' => '#d9deea', 'padding' => '4px 5px'],
                            ],
                            [
                                'id' => 'grand_empty',
                                'type' => 'text',
                                'content' => '',
                                'colspan' => 2,
                                'align' => 'center',
                                'style' => ['backgroundColor' => '#d9deea', 'padding' => '4px 5px'],
                            ],
                        ],
                    ],
                ],
            ]],
            'footer' => [],
        ];
    }

    public function columns(): array
    {
        $headers = [
            'Mã', 'Mã ĐK', 'Tên Đăng Ký', 'Công Ty', 'Ngày Đến', 'Ngày Đi',
            'Đêm', 'Phòng', 'Người<br>Lớn', 'Trẻ<br>Em', 'Loại Phòng', 'Mã Giá Phòng'
        ];
        $values = [
            'row.Index', 'row.BookingCode', 'row.BookingName', 'row.CompanyName', 'row.ArrivalDate', 'row.DepartureDate',
            'row.NumOfDays', 'row.Room', 'row.Adults', 'row.Children', 'row.RoomType', 'row.RateCode'
        ];
        $widths = ['4%', '8%', '20%', '14%', '8%', '8%', '5%', '6%', '6%', '5%', '8%', '8%'];
        return array_map(function (string $header, int $index) use ($values, $widths): array {
            $numeric = in_array($index, [6, 8, 9], true);
            $center = in_array($index, [0, 1, 4, 5, 6, 7, 8, 9, 10, 11], true);
            $isCode = $index === 1;
            return [
                'header' => $header,
                'value' => $values[$index],
                'width' => $widths[$index],
                'align' => $center ? 'center' : 'left',
                'format' => $numeric ? 'number' : null,
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
                    'textAlign' => $center ? 'center' : 'left',
                    'padding' => '4px 5px',
                    'fontSize' => '9.5px',
                    'color' => $isCode ? '#2e7d32' : '#111111',
                    'fontWeight' => $isCode ? 'bold' : 'normal',
                ],
            ];
        }, $headers, array_keys($headers));
    }

    public function css(): string
    {
        return <<<'CSS'
body { font-family: Arial, Helvetica, sans-serif; color: #111111; font-size: 9.5px; line-height: 1.3; }
.report-header-grid { display: flex; justify-content: space-between; align-items: flex-start; min-height: 50px; }
.hotel-logo img { max-height: 50px; max-width: 190px; object-fit: contain; }
.hotel-information { text-align: right; font-size: 9.5px; line-height: 1.5; }
.header-divider { border-top: 1px solid #111111; margin: 4px 0 10px; }
.report-header-band h1 { font-size: 18px; text-align: center; margin: 6px 0 4px; font-weight: bold; color: #111111; }
.period { text-align: center; font-size: 10px; margin: 2px 0 12px; color: #111111; font-weight: bold; }
.room-rate-statistics-table { width: 100%; border-collapse: collapse; font-size: 9.5px; line-height: 1.25; table-layout: fixed; }
.room-rate-statistics-table th, .room-rate-statistics-table td { border: 1px solid #aeb5c0; padding: 4px 5px; vertical-align: middle; }
.room-rate-statistics-table thead th { background: #d9deea; text-align: center; font-weight: bold; color: #111111; font-size: 9.5px; }
.room-rate-statistics-table .pms-group-header td { background: #ffffff; font-weight: bold; padding: 4px 6px; }
.room-rate-statistics-table .booking-code { color: #2e7d32; font-weight: bold; text-align: center; }
.room-rate-statistics-table .pms-group-footer td { background: #ffffff; font-weight: bold; }
.room-rate-statistics-table .report-grand-total-row td { background: #d9deea; font-weight: bold; }
.number { text-align: right; }
.center { text-align: center; }
.bold { font-weight: bold; }
.total-label { text-align: right; font-weight: bold; padding-right: 8px; }
@media print { thead { display: table-header-group; } tr { break-inside: avoid; } }
CSS;
    }

    public function render(array $data, ?TemplateRendererService $renderer = null): string
    {
        $renderer ??= new TemplateRendererService();
        $definition = $this->definition();
        return $renderer->render($definition['content_html'], $definition['css'], $data, array_intersect_key($definition, array_flip(['page_size', 'page_orientation', 'margin_top', 'margin_bottom', 'margin_left', 'margin_right'])));
    }
};
