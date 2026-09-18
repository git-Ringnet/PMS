<?php

use App\Services\TemplateRendererService;

return new class
{
    public function definition(): array
    {
        return [
            'code' => 'DAILY_FRONTDESK',
            'name' => 'Báo cáo lễ tân hằng ngày',
            'report' => 'DAILY_FRONTDESK_REFERENCE',
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
                    'Date' => 'string',
                    'DateFormatted' => 'string',
                    'SegmentOrder' => 'integer',
                    'Segment' => 'string',
                    'CheckinRoom' => 'integer',
                    'CheckoutRoom' => 'integer',
                    'InhouseRoom' => 'integer',
                    'DayUseRoom' => 'integer',
                    'BreakfastGuestNum' => 'integer',
                    'NoBreakfastGuestNum' => 'integer',
                    'Notes' => 'string',
                    'CustomerFeedback' => 'string',
                ],
                'parameters' => [
                    'p_from_date' => 'string',
                    'p_to_date' => 'string',
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
  <h1>BÁO CÁO LỄ TÂN HẰNG NGÀY</h1>
  <p class="period"><b>Ngày</b> &nbsp; {{parameters.p_from_date}} &nbsp; ~ &nbsp; {{parameters.p_to_date}}</p>
</div>
<table class="daily-frontdesk-table">
  <colgroup>
    <col style="width:14%">
    <col style="width:9%">
    <col style="width:9%">
    <col style="width:9%">
    <col style="width:11%">
    <col style="width:12%">
    <col style="width:12%">
    <col style="width:11%">
    <col style="width:13%">
  </colgroup>
  <thead>
    <tr>
      <th>Khách</th>
      <th>Số phòng<br>check in</th>
      <th>Số phòng<br>check out</th>
      <th>Số phòng<br>inhouse</th>
      <th>Tổng số<br>phòng ở<br>trong ngày</th>
      <th>Số lượng<br>khách ăn<br>sáng ngày<br>hôm sau</th>
      <th>Số lượng<br>khách<br>không ăn<br>sáng ngày<br>hôm sau</th>
      <th>Ghi chú</th>
      <th>Ý kiến phản hồi<br>khách hàng</th>
    </tr>
  </thead>
  <tbody class="pms-grouped-rows" data-source="rows" data-group-by="DateFormatted">
    <tr class="pms-group-header">
      <td class="group-date center">Ngày</td>
      <td class="group-date center">{{row.DateFormatted}}</td>
      <td></td><td></td><td></td><td></td><td></td><td></td><td></td>
    </tr>
    <tr class="pms-detail-row">
      <td class="segment">{{row.Segment}}</td>
      <td class="center">{{row.CheckinRoom|number}}</td>
      <td class="center">{{row.CheckoutRoom|number}}</td>
      <td class="center">{{row.InhouseRoom|number}}</td>
      <td class="center">{{row.DayUseRoom|number}}</td>
      <td class="center">{{row.BreakfastGuestNum|number}}</td>
      <td class="center">{{row.NoBreakfastGuestNum|number}}</td>
      <td>{{row.Notes}}</td>
      <td>{{row.CustomerFeedback}}</td>
    </tr>
    <tr class="pms-group-footer">
      <td class="total-label-sub">Tổng</td>
      <td class="center bold">{{group.sum.CheckinRoom|number}}</td>
      <td class="center bold">{{group.sum.CheckoutRoom|number}}</td>
      <td class="center bold">{{group.sum.InhouseRoom|number}}</td>
      <td class="center bold">{{group.sum.DayUseRoom|number}}</td>
      <td class="center bold">{{group.sum.BreakfastGuestNum|number}}</td>
      <td class="center bold">{{group.sum.NoBreakfastGuestNum|number}}</td>
      <td></td><td></td>
    </tr>
  </tbody>
  <tfoot>
    <tr class="report-grand-total-row">
      <td class="total-label-grand">Tổng</td>
      <td class="center bold">{{aggregate.rows.sum.CheckinRoom|number}}</td>
      <td class="center bold">{{aggregate.rows.sum.CheckoutRoom|number}}</td>
      <td class="center bold">{{aggregate.rows.sum.InhouseRoom|number}}</td>
      <td class="center bold">{{aggregate.rows.sum.DayUseRoom|number}}</td>
      <td class="center bold">{{aggregate.rows.sum.BreakfastGuestNum|number}}</td>
      <td class="center bold">{{aggregate.rows.sum.NoBreakfastGuestNum|number}}</td>
      <td></td><td></td>
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
                    'id' => 'daily_header_grid',
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
                                'id' => 'daily_logo',
                                'type' => 'text',
                                'content' => '<div class="hotel-logo">{{hotel.logo}}</div>',
                                'style' => ['minHeight' => '50px', 'marginTop' => '0px', 'marginBottom' => '0px'],
                            ]],
                        ],
                        [
                            'width' => '70%',
                            'blocks' => [[
                                'id' => 'daily_hotel_info',
                                'type' => 'text',
                                'content' => '<div class="hotel-information"><div><b>Địa chỉ:</b> {{hotel.address}}</div><div><b>Nhân viên:</b> {{report.generated_by}} &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <b>Ngày:</b> {{report.generated_at}}</div></div>',
                                'style' => ['textAlign' => 'right', 'fontSize' => '9.5px', 'lineHeight' => '1.5', 'marginTop' => '0px', 'marginBottom' => '0px'],
                            ]],
                        ],
                    ],
                ],
                [
                    'id' => 'daily_divider',
                    'type' => 'divider',
                    'content' => '<div class="header-divider"></div>',
                    'style' => ['borderTop' => '1px solid #111111', 'marginTop' => '4px', 'marginBottom' => '10px'],
                ],
                [
                    'id' => 'daily_title',
                    'type' => 'text',
                    'content' => '<h1 style="text-align: center; font-size: 18px; font-weight: bold; margin: 6px 0 4px; color: #111111;">BÁO CÁO LỄ TÂN HẰNG NGÀY</h1>',
                    'style' => ['textAlign' => 'center', 'fontWeight' => 'bold', 'fontSize' => '18px', 'color' => '#111111', 'marginTop' => '6px', 'marginBottom' => '4px'],
                ],
                [
                    'id' => 'daily_period',
                    'type' => 'text',
                    'content' => '<p class="period" style="text-align: center; font-size: 10px; margin: 2px 0 12px; color: #111111; font-weight: bold;"><b>Ngày</b> &nbsp; {{parameters.p_from_date}} &nbsp; ~ &nbsp; {{parameters.p_to_date}}</p>',
                    'style' => ['textAlign' => 'center', 'fontSize' => '10px', 'fontWeight' => 'bold', 'color' => '#111111', 'marginTop' => '2px', 'marginBottom' => '12px'],
                ],
            ],
            'detail' => [[
                'id' => 'daily_frontdesk_table',
                'type' => 'table',
                'dataSource' => 'rows',
                'tableType' => 'dynamic',
                'tableClassName' => 'daily-frontdesk-table',
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
                    ['id' => 'daily_date_group', 'field' => 'DateFormatted', 'label' => 'Ngày: {{row.DateFormatted}}', 'color' => '#d32f2f'],
                ],
                'columns' => $this->columns(),
                'customRows' => [
                    [
                        'id' => 'daily_subtotal_row',
                        'enabledBy' => '',
                        'scope' => 'group',
                        'level' => 0,
                        'className' => 'pms-group-footer',
                        'cells' => [
                            [
                                'id' => 'sub_label',
                                'type' => 'text',
                                'content' => 'Tổng',
                                'colspan' => 1,
                                'align' => 'right',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'right', 'backgroundColor' => '#ffffff', 'padding' => '4px 5px'],
                            ],
                            [
                                'id' => 'sub_checkin',
                                'type' => 'binding',
                                'binding' => 'group.sum.CheckinRoom',
                                'colspan' => 1,
                                'align' => 'center',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'center', 'backgroundColor' => '#ffffff', 'padding' => '4px 5px'],
                            ],
                            [
                                'id' => 'sub_checkout',
                                'type' => 'binding',
                                'binding' => 'group.sum.CheckoutRoom',
                                'colspan' => 1,
                                'align' => 'center',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'center', 'backgroundColor' => '#ffffff', 'padding' => '4px 5px'],
                            ],
                            [
                                'id' => 'sub_inhouse',
                                'type' => 'binding',
                                'binding' => 'group.sum.InhouseRoom',
                                'colspan' => 1,
                                'align' => 'center',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'center', 'backgroundColor' => '#ffffff', 'padding' => '4px 5px'],
                            ],
                            [
                                'id' => 'sub_dayuse',
                                'type' => 'binding',
                                'binding' => 'group.sum.DayUseRoom',
                                'colspan' => 1,
                                'align' => 'center',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'center', 'backgroundColor' => '#ffffff', 'padding' => '4px 5px'],
                            ],
                            [
                                'id' => 'sub_breakfast',
                                'type' => 'binding',
                                'binding' => 'group.sum.BreakfastGuestNum',
                                'colspan' => 1,
                                'align' => 'center',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'center', 'backgroundColor' => '#ffffff', 'padding' => '4px 5px'],
                            ],
                            [
                                'id' => 'sub_nobreakfast',
                                'type' => 'binding',
                                'binding' => 'group.sum.NoBreakfastGuestNum',
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
                        'id' => 'daily_grand_total_row',
                        'enabledBy' => '',
                        'scope' => 'table',
                        'level' => 0,
                        'className' => 'report-grand-total-row',
                        'cells' => [
                            [
                                'id' => 'grand_label',
                                'type' => 'text',
                                'content' => 'Tổng',
                                'colspan' => 1,
                                'align' => 'right',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'right', 'backgroundColor' => '#d9deea', 'padding' => '4px 5px'],
                            ],
                            [
                                'id' => 'grand_checkin',
                                'type' => 'binding',
                                'binding' => 'aggregate.rows.sum.CheckinRoom',
                                'colspan' => 1,
                                'align' => 'center',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'center', 'backgroundColor' => '#d9deea', 'padding' => '4px 5px'],
                            ],
                            [
                                'id' => 'grand_checkout',
                                'type' => 'binding',
                                'binding' => 'aggregate.rows.sum.CheckoutRoom',
                                'colspan' => 1,
                                'align' => 'center',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'center', 'backgroundColor' => '#d9deea', 'padding' => '4px 5px'],
                            ],
                            [
                                'id' => 'grand_inhouse',
                                'type' => 'binding',
                                'binding' => 'aggregate.rows.sum.InhouseRoom',
                                'colspan' => 1,
                                'align' => 'center',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'center', 'backgroundColor' => '#d9deea', 'padding' => '4px 5px'],
                            ],
                            [
                                'id' => 'grand_dayuse',
                                'type' => 'binding',
                                'binding' => 'aggregate.rows.sum.DayUseRoom',
                                'colspan' => 1,
                                'align' => 'center',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'center', 'backgroundColor' => '#d9deea', 'padding' => '4px 5px'],
                            ],
                            [
                                'id' => 'grand_breakfast',
                                'type' => 'binding',
                                'binding' => 'aggregate.rows.sum.BreakfastGuestNum',
                                'colspan' => 1,
                                'align' => 'center',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'center', 'backgroundColor' => '#d9deea', 'padding' => '4px 5px'],
                            ],
                            [
                                'id' => 'grand_nobreakfast',
                                'type' => 'binding',
                                'binding' => 'aggregate.rows.sum.NoBreakfastGuestNum',
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
            'Khách',
            'Số phòng<br>check in',
            'Số phòng<br>check out',
            'Số phòng<br>inhouse',
            'Tổng số<br>phòng ở<br>trong ngày',
            'Số lượng<br>khách ăn<br>sáng ngày<br>hôm sau',
            'Số lượng<br>khách<br>không ăn<br>sáng ngày<br>hôm sau',
            'Ghi chú',
            'Ý kiến phản hồi<br>khách hàng',
        ];
        $values = [
            'row.Segment',
            'row.CheckinRoom',
            'row.CheckoutRoom',
            'row.InhouseRoom',
            'row.DayUseRoom',
            'row.BreakfastGuestNum',
            'row.NoBreakfastGuestNum',
            'row.Notes',
            'row.CustomerFeedback',
        ];
        $widths = ['14%', '9%', '9%', '9%', '11%', '12%', '12%', '11%', '13%'];
        return array_map(function (string $header, int $index) use ($values, $widths): array {
            $numeric = $index >= 1 && $index <= 6;
            return [
                'header' => $header,
                'value' => $values[$index],
                'width' => $widths[$index],
                'align' => $numeric ? 'center' : 'left',
                'format' => $numeric ? 'number' : null,
                'headerStyle' => [
                    'backgroundColor' => '#d9deea',
                    'border' => '1px solid #aeb5c0',
                    'textAlign' => 'center',
                    'fontWeight' => 'bold',
                    'padding' => '4px 6px',
                    'fontSize' => '9.5px',
                    'color' => '#111111',
                ],
                'cellStyle' => [
                    'border' => '1px solid #aeb5c0',
                    'textAlign' => $numeric ? 'center' : 'left',
                    'padding' => '4px 6px',
                    'fontSize' => '9.5px',
                    'color' => '#111111',
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
.daily-frontdesk-table { width: 100%; border-collapse: collapse; font-size: 9.5px; line-height: 1.25; table-layout: fixed; }
.daily-frontdesk-table th, .daily-frontdesk-table td { border: 1px solid #aeb5c0; padding: 4px 6px; vertical-align: middle; }
.daily-frontdesk-table thead th { background: #d9deea; text-align: center; font-weight: bold; color: #111111; font-size: 9.5px; }
.daily-frontdesk-table .pms-group-header td { background: #ffffff; font-weight: bold; }
.daily-frontdesk-table .group-date { color: #d32f2f; font-weight: bold; }
.daily-frontdesk-table .segment { font-weight: normal; }
.daily-frontdesk-table .pms-group-footer td { background: #ffffff; font-weight: bold; }
.daily-frontdesk-table .report-grand-total-row td { background: #d9deea; font-weight: bold; }
.number { text-align: right; }
.center { text-align: center; }
.bold { font-weight: bold; }
.total-label-sub { text-align: center; font-weight: bold; }
.total-label-grand { text-align: center; font-weight: bold; }
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
