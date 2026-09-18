<?php

use App\Services\TemplateRendererService;

return new class
{
    public function definition(): array
    {
        return [
            'code' => 'CANCELLED_INVOICES_PAYMENTS',
            'name' => 'Báo cáo hủy hóa đơn/thanh toán',
            'report' => 'CANCELLED_INVOICES_PAYMENTS_REFERENCE',
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
                    'Room1' => 'string',
                    'Service' => 'string',
                    'CreatedDate' => 'string',
                    'CreatedHour' => 'string',
                    'AmountAm' => 'number',
                    'CreatedUser' => 'string',
                    'Date' => 'string',
                    'OpenTime' => 'string',
                    'AmountDuong' => 'number',
                    'Username' => 'string',
                    'Description' => 'string',
                    'InvoiceDateFormatted' => 'string',
                    'DepartmentId' => 'string',
                    'DepartmentName' => 'string',
                ],
                'parameters' => [
                    'p_mode' => 'string',
                    'p_from_date' => 'string',
                    'p_to_date' => 'string',
                    'p_department' => 'string',
                    'p_outlet' => 'string',
                    'p_service' => 'string',
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
  <h1>{{parameters.p_mode_label}}</h1>
  <p class="period"><b>Ngày:</b> {{parameters.p_from_date}} &nbsp; ~ &nbsp; {{parameters.p_to_date}}</p>
</div>
<table class="cancelled-invoices-payments-table">
  <colgroup>
    <col style="width:4%">
    <col style="width:10%">
    <col style="width:9%">
    <col style="width:7%">
    <col style="width:6%">
    <col style="width:8%">
    <col style="width:8%">
    <col style="width:7%">
    <col style="width:6%">
    <col style="width:8%">
    <col style="width:8%">
    <col style="width:19%">
  </colgroup>
  <thead>
    <tr>
      <th rowspan="2">STT</th>
      <th rowspan="2">Mã ĐK/Phòng</th>
      <th rowspan="2">Dịch Vụ</th>
      <th colspan="4">Thông Tin Tạo</th>
      <th colspan="4">Thông Tin Hủy</th>
      <th rowspan="2">Lý Do</th>
    </tr>
    <tr>
      <th>Ngày</th>
      <th>Giờ</th>
      <th>Tổng</th>
      <th>Người Dùng</th>
      <th>Ngày</th>
      <th>Giờ</th>
      <th>Tổng</th>
      <th>Người Dùng</th>
    </tr>
  </thead>
  <tbody class="pms-grouped-rows" data-source="rows" data-group-by="InvoiceDateFormatted" data-subgroup-by="DepartmentId">
    <tr class="pms-group-header">
      <td colspan="12"><span class="group-date">Ngày:</span> {{row.InvoiceDateFormatted}}</td>
    </tr>
    <tr class="pms-subgroup-header">
      <td colspan="12"><span class="group-department">Bộ phận:</span> {{row.DepartmentName}}</td>
    </tr>
    <tr class="pms-detail-row">
      <td class="center">{{row.Index}}</td>
      <td class="center booking-code">{{row.Room1}}</td>
      <td>{{row.Service}}</td>
      <td class="center">{{row.CreatedDate}}</td>
      <td class="center">{{row.CreatedHour}}</td>
      <td class="number">{{row.AmountAm|number}}</td>
      <td class="center">{{row.CreatedUser}}</td>
      <td class="center">{{row.Date}}</td>
      <td class="center">{{row.OpenTime}}</td>
      <td class="number">{{row.AmountDuong|number}}</td>
      <td class="center">{{row.Username}}</td>
      <td>{{row.Description}}</td>
    </tr>
    <tr class="pms-subgroup-footer">
      <td colspan="5" class="total-label">Tổng</td>
      <td class="number bold">{{group.sum.AmountAm|number}}</td>
      <td colspan="3"></td>
      <td class="number bold">{{group.sum.AmountDuong|number}}</td>
      <td colspan="2"></td>
    </tr>
  </tbody>
  <tfoot>
    <tr class="report-grand-total-row">
      <td colspan="5" class="grand-total-label">Tổng Giai Đoạn</td>
      <td class="number bold">{{aggregate.rows.sum.AmountAm|number}}</td>
      <td colspan="3"></td>
      <td class="number bold">{{aggregate.rows.sum.AmountDuong|number}}</td>
      <td colspan="2"></td>
    </tr>
  </tfoot>
</table>
<div class="signature-grid">
  <div class="sig-col"><b>FOM</b></div>
  <div class="sig-col"><b>ACC</b></div>
  <div class="sig-col"><b>GM</b></div>
</div>
HTML;
    }

    public function blocks(): array
    {
        return [
            'header' => [
                [
                    'id' => 'cancelled_header_grid',
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
                                'id' => 'cancelled_logo',
                                'type' => 'text',
                                'content' => '<div class="hotel-logo">{{hotel.logo}}</div>',
                                'style' => ['minHeight' => '50px', 'marginTop' => '0px', 'marginBottom' => '0px'],
                            ]],
                        ],
                        [
                            'width' => '70%',
                            'blocks' => [[
                                'id' => 'cancelled_hotel_info',
                                'type' => 'text',
                                'content' => '<div class="hotel-information"><div><b>Địa chỉ:</b> {{hotel.address}}</div><div><b>Nhân viên:</b> {{report.generated_by}} &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <b>Ngày:</b> {{report.generated_at}}</div></div>',
                                'style' => ['textAlign' => 'right', 'fontSize' => '9.5px', 'lineHeight' => '1.5', 'marginTop' => '0px', 'marginBottom' => '0px'],
                            ]],
                        ],
                    ],
                ],
                [
                    'id' => 'cancelled_divider',
                    'type' => 'divider',
                    'content' => '<div class="header-divider"></div>',
                    'style' => ['borderTop' => '1px solid #111111', 'marginTop' => '4px', 'marginBottom' => '10px'],
                ],
                [
                    'id' => 'cancelled_title',
                    'type' => 'text',
                    'content' => '<h1 style="text-align: center; font-size: 18px; font-weight: bold; margin: 6px 0 4px; color: #111111;">{{parameters.p_mode_label}}</h1>',
                    'style' => ['textAlign' => 'center', 'fontWeight' => 'bold', 'fontSize' => '18px', 'color' => '#111111', 'marginTop' => '6px', 'marginBottom' => '4px'],
                ],
                [
                    'id' => 'cancelled_period',
                    'type' => 'text',
                    'content' => '<p class="period" style="text-align: center; font-size: 10px; margin: 2px 0 12px; color: #111111;"><b>Ngày:</b> {{parameters.p_from_date}} &nbsp; ~ &nbsp; {{parameters.p_to_date}}</p>',
                    'style' => ['textAlign' => 'center', 'fontSize' => '10px', 'color' => '#111111', 'marginTop' => '2px', 'marginBottom' => '12px'],
                ],
            ],
            'detail' => [[
                'id' => 'cancelled_table',
                'type' => 'table',
                'dataSource' => 'rows',
                'tableType' => 'dynamic',
                'tableClassName' => 'cancelled-invoices-payments-table',
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
                'hasTwoTierHeader' => true,
                'topHeader' => [
                    ['label' => 'STT', 'rowspan' => 2, 'width' => '4%', 'align' => 'center'],
                    ['label' => 'Mã ĐK/Phòng', 'rowspan' => 2, 'width' => '10%', 'align' => 'center'],
                    ['label' => 'Dịch Vụ', 'rowspan' => 2, 'width' => '9%', 'align' => 'center'],
                    ['label' => 'Thông Tin Tạo', 'colspan' => 4, 'align' => 'center'],
                    ['label' => 'Thông Tin Hủy', 'colspan' => 4, 'align' => 'center'],
                    ['label' => 'Lý Do', 'rowspan' => 2, 'width' => '19%', 'align' => 'center'],
                ],
                'grouping' => [
                    ['id' => 'cancelled_date_group', 'field' => 'InvoiceDateFormatted', 'label' => 'Ngày: {{row.InvoiceDateFormatted}}', 'color' => '#d32f2f'],
                    ['id' => 'cancelled_department_group', 'field' => 'DepartmentId', 'label' => 'Bộ phận: {{row.DepartmentName}}', 'color' => '#111111'],
                ],
                'columns' => $this->columns(),
                'customRows' => [
                    [
                        'id' => 'cancelled_subtotal_row',
                        'enabledBy' => '',
                        'scope' => 'group',
                        'level' => 1,
                        'className' => 'pms-subgroup-footer',
                        'cells' => [
                            [
                                'id' => 'sub_label',
                                'type' => 'text',
                                'content' => 'Tổng',
                                'colspan' => 5,
                                'align' => 'right',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'right', 'backgroundColor' => '#ffffff', 'padding' => '4px 5px'],
                            ],
                            [
                                'id' => 'sub_amount_am',
                                'type' => 'binding',
                                'binding' => 'group.sum.AmountAm',
                                'colspan' => 1,
                                'align' => 'right',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'right', 'backgroundColor' => '#ffffff', 'padding' => '4px 5px'],
                            ],
                            [
                                'id' => 'sub_mid_empty',
                                'type' => 'text',
                                'content' => '',
                                'colspan' => 3,
                                'align' => 'center',
                                'style' => ['backgroundColor' => '#ffffff', 'padding' => '4px 5px'],
                            ],
                            [
                                'id' => 'sub_amount_duong',
                                'type' => 'binding',
                                'binding' => 'group.sum.AmountDuong',
                                'colspan' => 1,
                                'align' => 'right',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'right', 'backgroundColor' => '#ffffff', 'padding' => '4px 5px'],
                            ],
                            [
                                'id' => 'sub_end_empty',
                                'type' => 'text',
                                'content' => '',
                                'colspan' => 2,
                                'align' => 'center',
                                'style' => ['backgroundColor' => '#ffffff', 'padding' => '4px 5px'],
                            ],
                        ],
                    ],
                    [
                        'id' => 'cancelled_grand_total_row',
                        'enabledBy' => '',
                        'scope' => 'table',
                        'level' => 0,
                        'className' => 'report-grand-total-row',
                        'cells' => [
                            [
                                'id' => 'grand_label',
                                'type' => 'text',
                                'content' => 'Tổng Giai Đoạn',
                                'colspan' => 5,
                                'align' => 'right',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'right', 'backgroundColor' => '#d9deea', 'padding' => '4px 5px'],
                            ],
                            [
                                'id' => 'grand_amount_am',
                                'type' => 'binding',
                                'binding' => 'aggregate.rows.sum.AmountAm',
                                'colspan' => 1,
                                'align' => 'right',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'right', 'backgroundColor' => '#d9deea', 'padding' => '4px 5px'],
                            ],
                            [
                                'id' => 'grand_mid_empty',
                                'type' => 'text',
                                'content' => '',
                                'colspan' => 3,
                                'align' => 'center',
                                'style' => ['backgroundColor' => '#d9deea', 'padding' => '4px 5px'],
                            ],
                            [
                                'id' => 'grand_amount_duong',
                                'type' => 'binding',
                                'binding' => 'aggregate.rows.sum.AmountDuong',
                                'colspan' => 1,
                                'align' => 'right',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'right', 'backgroundColor' => '#d9deea', 'padding' => '4px 5px'],
                            ],
                            [
                                'id' => 'grand_end_empty',
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
            'footer' => [
                [
                    'id' => 'cancelled_signatures',
                    'type' => 'text',
                    'content' => '<div class="signature-grid"><div class="sig-col"><b>FOM</b></div><div class="sig-col"><b>ACC</b></div><div class="sig-col"><b>GM</b></div></div>',
                    'style' => ['marginTop' => '35px', 'marginBottom' => '15px'],
                ],
            ],
        ];
    }

    public function columns(): array
    {
        $headers = [
            'STT', 'Mã ĐK/Phòng', 'Dịch Vụ',
            'Ngày', 'Giờ', 'Tổng', 'Người Dùng',
            'Ngày', 'Giờ', 'Tổng', 'Người Dùng',
            'Lý Do'
        ];
        $values = [
            'row.Index', 'row.Room1', 'row.Service',
            'row.CreatedDate', 'row.CreatedHour', 'row.AmountAm', 'row.CreatedUser',
            'row.Date', 'row.OpenTime', 'row.AmountDuong', 'row.Username',
            'row.Description'
        ];
        $widths = ['4%', '10%', '9%', '7%', '6%', '8%', '8%', '7%', '6%', '8%', '8%', '19%'];
        return array_map(function (string $header, int $index) use ($values, $widths): array {
            $numeric = in_array($index, [5, 9], true);
            $center = in_array($index, [0, 1, 3, 4, 6, 7, 8, 10], true);
            $isCode = $index === 1;
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
.period { text-align: center; font-size: 10px; margin: 2px 0 12px; color: #111111; }
.cancelled-invoices-payments-table { width: 100%; border-collapse: collapse; font-size: 9.5px; line-height: 1.25; table-layout: fixed; }
.cancelled-invoices-payments-table th, .cancelled-invoices-payments-table td { border: 1px solid #aeb5c0; padding: 4px 5px; vertical-align: middle; }
.cancelled-invoices-payments-table thead th { background: #d9deea; text-align: center; font-weight: bold; color: #111111; font-size: 9.5px; }
.cancelled-invoices-payments-table .pms-group-header td { background: #ffffff; font-weight: bold; padding: 4px 6px; }
.cancelled-invoices-payments-table .group-date { color: #d32f2f; font-weight: bold; }
.cancelled-invoices-payments-table .pms-subgroup-header td { background: #ffffff; font-weight: bold; padding: 4px 6px; }
.cancelled-invoices-payments-table .group-department { font-weight: bold; color: #111111; }
.cancelled-invoices-payments-table .booking-code { color: #2e7d32; font-weight: bold; text-align: center; }
.cancelled-invoices-payments-table .pms-subgroup-footer td { background: #ffffff; font-weight: bold; }
.cancelled-invoices-payments-table .report-grand-total-row td { background: #d9deea; font-weight: bold; }
.number { text-align: right; }
.center { text-align: center; }
.bold { font-weight: bold; }
.total-label { text-align: right; font-weight: bold; padding-right: 8px; }
.grand-total-label { text-align: center; font-weight: bold; }
.signature-grid { display: flex; justify-content: space-around; margin-top: 35px; margin-bottom: 15px; font-size: 10px; text-align: center; }
.signature-grid .sig-col { width: 25%; text-align: center; font-weight: bold; }
@media print { thead { display: table-header-group; } tr { break-inside: avoid; } }
CSS;
    }

    public function render(array $data, ?TemplateRendererService $renderer = null): string
    {
        $renderer ??= new TemplateRendererService();
        $data['parameters']['p_mode_label'] = !empty($data['parameters']['p_mode_label'])
            ? $data['parameters']['p_mode_label']
            : (strtoupper((string) ($data['parameters']['p_mode'] ?? 'BILL')) === 'PAYMENT' ? 'BÁO CÁO HỦY THANH TOÁN' : 'BÁO CÁO HỦY HÓA ĐƠN');
        $definition = $this->definition();
        return $renderer->render($definition['content_html'], $definition['css'], $data, array_intersect_key($definition, array_flip(['page_size', 'page_orientation', 'margin_top', 'margin_bottom', 'margin_left', 'margin_right'])));
    }
};
