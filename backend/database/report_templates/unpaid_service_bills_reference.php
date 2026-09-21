<?php

use App\Services\TemplateRendererService;

return new class
{
    public function definition(): array
    {
        return [
            'code' => 'UNPAID_SERVICE_BILLS',
            'name' => 'Báo cáo hóa đơn dịch vụ chưa thanh toán',
            'report' => 'UNPAID_SERVICE_BILLS_REFERENCE',
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
                    'Ma' => 'integer',
                    'DateFormatted' => 'string',
                    'RefId' => 'string',
                    'PaymentId' => 'string',
                    'BookingId' => 'string',
                    'ArrivalDate' => 'string',
                    'DepartureDate' => 'string',
                    'BusinessName' => 'string',
                    'Guest' => 'string',
                    'DescriptionServive' => 'string',
                    'OriginalRate' => 'number',
                    'ServiceChargeAmount' => 'number',
                    'TaxAmount' => 'number',
                    'TienQDTD' => 'number',
                    'Status' => 'integer',
                    'Username' => 'string',
                    'OpenTime' => 'string',
                    'ServiceId' => 'string',
                    'ServiceName' => 'string',
                    'HTTT' => 'string',
                ],
                'parameters' => [
                    'p_from_date' => 'string',
                    'p_to_date' => 'string',
                    'p_user' => 'string',
                    'p_sort_by' => 'string',
                    'p_sort_type' => 'string',
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
  <h1>BÁO CÁO HÓA ĐƠN DỊCH VỤ CHƯA THANH TOÁN</h1>
  <p class="period"><b>Ngày</b> &nbsp; {{parameters.p_from_date}} &nbsp; ~ &nbsp; {{parameters.p_to_date}}</p>
</div>
<table class="unpaid-service-bills-table">
  <colgroup>
    <col style="width:3%">
    <col style="width:5%">
    <col style="width:8%">
    <col style="width:13%">
    <col style="width:7%">
    <col style="width:7%">
    <col style="width:14%">
    <col style="width:11%">
    <col style="width:7%">
    <col style="width:5%">
    <col style="width:5%">
    <col style="width:7%">
    <col style="width:4%">
    <col style="width:5%">
    <col style="width:4%">
  </colgroup>
  <thead>
    <tr>
      <th>STT</th>
      <th>Mã</th>
      <th>Mã ĐK</th>
      <th>Tên Khách</th>
      <th>Ngày Đến</th>
      <th>Ngày Đi</th>
      <th>Mô Tả</th>
      <th>Công Ty</th>
      <th>Giá Gốc</th>
      <th>Phí PV</th>
      <th>Thuế</th>
      <th>Tổng</th>
      <th>HTTT</th>
      <th>Người Dùng</th>
      <th>Giờ</th>
    </tr>
  </thead>
  <tbody class="pms-grouped-rows" data-source="rows" data-group-by="ServiceId">
    <tr class="pms-group-header">
      <td colspan="15" class="bold">Dịch vụ &nbsp;&nbsp; {{row.ServiceId}}</td>
    </tr>
    <tr class="pms-detail-row">
      <td class="center">{{row.Index}}</td>
      <td class="center">{{row.Ma}}</td>
      <td class="center booking-code">{{row.BookingId}}</td>
      <td>{{row.Guest}}</td>
      <td class="center">{{row.ArrivalDate}}</td>
      <td class="center">{{row.DepartureDate}}</td>
      <td>{{row.DescriptionServive}}</td>
      <td>{{row.BusinessName}}</td>
      <td class="number">{{row.OriginalRate|number}}</td>
      <td class="number">{{row.ServiceChargeAmount|number}}</td>
      <td class="number">{{row.TaxAmount|number}}</td>
      <td class="number bold">{{row.TienQDTD|number}}</td>
      <td class="center">{{row.HTTT}}</td>
      <td class="center">{{row.Username}}</td>
      <td class="center">{{row.OpenTime}}</td>
    </tr>
    <tr class="pms-group-footer">
      <td colspan="8" class="total-label">Tổng doanh thu theo dịch vụ</td>
      <td class="number bold">{{group.sum.OriginalRate|number}}</td>
      <td class="number bold">{{group.sum.ServiceChargeAmount|number}}</td>
      <td class="number bold">{{group.sum.TaxAmount|number}}</td>
      <td class="number bold">{{group.sum.TienQDTD|number}}</td>
      <td colspan="3"></td>
    </tr>
  </tbody>
  <tfoot>
    <tr class="report-grand-total-row">
      <td colspan="8" class="total-label">TỔNG CỘNG</td>
      <td class="number bold">{{aggregate.rows.sum.OriginalRate|number}}</td>
      <td class="number bold">{{aggregate.rows.sum.ServiceChargeAmount|number}}</td>
      <td class="number bold">{{aggregate.rows.sum.TaxAmount|number}}</td>
      <td class="number bold">{{aggregate.rows.sum.TienQDTD|number}}</td>
      <td colspan="3"></td>
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
                    'id' => 'unpaid_bills_header_grid',
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
                                'id' => 'unpaid_bills_logo',
                                'type' => 'text',
                                'content' => '<div class="hotel-logo">{{hotel.logo}}</div>',
                                'style' => ['minHeight' => '50px', 'marginTop' => '0px', 'marginBottom' => '0px'],
                            ]],
                        ],
                        [
                            'width' => '70%',
                            'blocks' => [[
                                'id' => 'unpaid_bills_hotel_info',
                                'type' => 'text',
                                'content' => '<div class="hotel-information"><div><b>Địa chỉ:</b> {{hotel.address}}</div><div><b>Nhân viên:</b> {{report.generated_by}} &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <b>Ngày:</b> {{report.generated_at}}</div></div>',
                                'style' => ['textAlign' => 'right', 'fontSize' => '9.5px', 'lineHeight' => '1.5', 'marginTop' => '0px', 'marginBottom' => '0px'],
                            ]],
                        ],
                    ],
                ],
                [
                    'id' => 'unpaid_bills_divider',
                    'type' => 'divider',
                    'content' => '<div class="header-divider"></div>',
                    'style' => ['borderTop' => '1px solid #111111', 'marginTop' => '4px', 'marginBottom' => '10px'],
                ],
                [
                    'id' => 'unpaid_bills_title',
                    'type' => 'text',
                    'content' => '<h1 style="text-align: center; font-size: 18px; font-weight: bold; margin: 6px 0 4px; color: #111111;">BÁO CÁO HÓA ĐƠN DỊCH VỤ CHƯA THANH TOÁN</h1>',
                    'style' => ['textAlign' => 'center', 'fontWeight' => 'bold', 'fontSize' => '18px', 'color' => '#111111', 'marginTop' => '6px', 'marginBottom' => '4px'],
                ],
                [
                    'id' => 'unpaid_bills_period',
                    'type' => 'text',
                    'content' => '<p class="period" style="text-align: center; font-size: 10px; margin: 2px 0 12px; color: #111111; font-weight: bold;"><b>Ngày</b> &nbsp; {{parameters.p_from_date}} &nbsp; ~ &nbsp; {{parameters.p_to_date}}</p>',
                    'style' => ['textAlign' => 'center', 'fontSize' => '10px', 'fontWeight' => 'bold', 'color' => '#111111', 'marginTop' => '2px', 'marginBottom' => '12px'],
                ],
            ],
            'detail' => [[
                'id' => 'unpaid_bills_table',
                'type' => 'table',
                'dataSource' => 'rows',
                'tableType' => 'dynamic',
                'tableClassName' => 'unpaid-service-bills-table',
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
                    ['id' => 'unpaid_bills_service_group', 'field' => 'ServiceId', 'label' => 'Dịch vụ: {{row.ServiceId}}', 'color' => '#111111'],
                ],
                'columns' => $this->columns(),
                'customRows' => [
                    [
                        'id' => 'unpaid_bills_subtotal_row',
                        'enabledBy' => '',
                        'scope' => 'group',
                        'level' => 0,
                        'className' => 'pms-group-footer',
                        'cells' => [
                            [
                                'id' => 'sub_label',
                                'type' => 'text',
                                'content' => 'Tổng doanh thu theo dịch vụ',
                                'colspan' => 8,
                                'align' => 'right',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'right', 'backgroundColor' => '#ffffff', 'padding' => '4px 5px'],
                            ],
                            [
                                'id' => 'sub_orig_rate',
                                'type' => 'binding',
                                'binding' => 'group.sum.OriginalRate',
                                'colspan' => 1,
                                'align' => 'right',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'right', 'backgroundColor' => '#ffffff', 'padding' => '4px 5px'],
                            ],
                            [
                                'id' => 'sub_service_charge',
                                'type' => 'binding',
                                'binding' => 'group.sum.ServiceChargeAmount',
                                'colspan' => 1,
                                'align' => 'right',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'right', 'backgroundColor' => '#ffffff', 'padding' => '4px 5px'],
                            ],
                            [
                                'id' => 'sub_tax',
                                'type' => 'binding',
                                'binding' => 'group.sum.TaxAmount',
                                'colspan' => 1,
                                'align' => 'right',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'right', 'backgroundColor' => '#ffffff', 'padding' => '4px 5px'],
                            ],
                            [
                                'id' => 'sub_total',
                                'type' => 'binding',
                                'binding' => 'group.sum.TienQDTD',
                                'colspan' => 1,
                                'align' => 'right',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'right', 'backgroundColor' => '#ffffff', 'padding' => '4px 5px'],
                            ],
                            [
                                'id' => 'sub_empty',
                                'type' => 'text',
                                'content' => '',
                                'colspan' => 3,
                                'align' => 'center',
                                'style' => ['backgroundColor' => '#ffffff', 'padding' => '4px 5px'],
                            ],
                        ],
                    ],
                    [
                        'id' => 'unpaid_bills_grand_total_row',
                        'enabledBy' => '',
                        'scope' => 'table',
                        'level' => 0,
                        'className' => 'report-grand-total-row',
                        'cells' => [
                            [
                                'id' => 'grand_label',
                                'type' => 'text',
                                'content' => 'TỔNG CỘNG',
                                'colspan' => 8,
                                'align' => 'right',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'right', 'backgroundColor' => '#d9deea', 'padding' => '4px 5px'],
                            ],
                            [
                                'id' => 'grand_orig_rate',
                                'type' => 'binding',
                                'binding' => 'aggregate.rows.sum.OriginalRate',
                                'colspan' => 1,
                                'align' => 'right',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'right', 'backgroundColor' => '#d9deea', 'padding' => '4px 5px'],
                            ],
                            [
                                'id' => 'grand_service_charge',
                                'type' => 'binding',
                                'binding' => 'aggregate.rows.sum.ServiceChargeAmount',
                                'colspan' => 1,
                                'align' => 'right',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'right', 'backgroundColor' => '#d9deea', 'padding' => '4px 5px'],
                            ],
                            [
                                'id' => 'grand_tax',
                                'type' => 'binding',
                                'binding' => 'aggregate.rows.sum.TaxAmount',
                                'colspan' => 1,
                                'align' => 'right',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'right', 'backgroundColor' => '#d9deea', 'padding' => '4px 5px'],
                            ],
                            [
                                'id' => 'grand_total',
                                'type' => 'binding',
                                'binding' => 'aggregate.rows.sum.TienQDTD',
                                'colspan' => 1,
                                'align' => 'right',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'right', 'backgroundColor' => '#d9deea', 'padding' => '4px 5px'],
                            ],
                            [
                                'id' => 'grand_empty',
                                'type' => 'text',
                                'content' => '',
                                'colspan' => 3,
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
            'STT', 'Mã', 'Mã ĐK', 'Tên Khách', 'Ngày Đến', 'Ngày Đi', 'Mô Tả',
            'Công Ty', 'Giá Gốc', 'Phí PV', 'Thuế', 'Tổng', 'HTTT', 'Người Dùng', 'Giờ'
        ];
        $values = [
            'row.Index', 'row.Ma', 'row.BookingId', 'row.Guest', 'row.ArrivalDate', 'row.DepartureDate', 'row.DescriptionServive',
            'row.BusinessName', 'row.OriginalRate', 'row.ServiceChargeAmount', 'row.TaxAmount', 'row.TienQDTD', 'row.HTTT', 'row.Username', 'row.OpenTime'
        ];
        $widths = ['3%', '5%', '8%', '13%', '7%', '7%', '14%', '11%', '7%', '5%', '5%', '7%', '4%', '5%', '4%'];
        return array_map(function (string $header, int $index) use ($values, $widths): array {
            $numeric = in_array($index, [8, 9, 10, 11], true);
            $center = in_array($index, [0, 1, 2, 4, 5, 12, 13, 14], true);
            $isCode = $index === 2;
            return [
                'header' => $header,
                'value' => $values[$index],
                'width' => $widths[$index],
                'align' => $numeric ? 'right' : ($center ? 'center' : 'left'),
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
                    'textAlign' => $numeric ? 'right' : ($center ? 'center' : 'left'),
                    'padding' => '4px 5px',
                    'fontSize' => '9.5px',
                    'color' => $isCode ? '#2e7d32' : '#111111',
                    'fontWeight' => $isCode || $index === 11 ? 'bold' : 'normal',
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
.unpaid-service-bills-table { width: 100%; border-collapse: collapse; font-size: 9.5px; line-height: 1.25; table-layout: fixed; }
.unpaid-service-bills-table th, .unpaid-service-bills-table td { border: 1px solid #aeb5c0; padding: 4px 5px; vertical-align: middle; }
.unpaid-service-bills-table thead th { background: #d9deea; text-align: center; font-weight: bold; color: #111111; font-size: 9.5px; }
.unpaid-service-bills-table .pms-group-header td { background: #ffffff; font-weight: bold; padding: 4px 6px; }
.unpaid-service-bills-table .booking-code { color: #2e7d32; font-weight: bold; text-align: center; }
.unpaid-service-bills-table .pms-group-footer td { background: #ffffff; font-weight: bold; }
.unpaid-service-bills-table .report-grand-total-row td { background: #d9deea; font-weight: bold; }
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
