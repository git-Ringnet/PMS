<?php

use App\Services\TemplateRendererService;

/**
 * Reference template provider for SALES_INVOICES (sp_094 legacy reference).
 * Configured with 2-tier header, group by Date, subtotal and grand total,
 * and secondary currency allocation table (Bảng Phân Bổ Tiền Tệ).
 */
return new class
{
    public function definition(): array
    {
        return [
            'code' => 'SALES_INVOICES',
            'name' => 'Báo cáo hóa đơn bán hàng',
            'report' => 'SALES_INVOICES_REFERENCE',
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
                    'BillID' => 'string',
                    'PaymentID' => 'string',
                    'InvoiceDateFormatted' => 'string',
                    'Room' => 'string',
                    'VATNo' => 'string',
                    'GuestName' => 'string',
                    'CompanyName' => 'string',
                    'Amount' => 'number',
                    'Cash' => 'number',
                    'Card' => 'number',
                    'Voucher' => 'number',
                    'City' => 'number',
                    'DPCash' => 'number',
                    'DPCard' => 'number',
                ],
                'currency_allocations' => [
                    'Method' => 'string',
                    'CashierAmount' => 'number',
                    'DepositAmount' => 'number',
                    'TotalAmount' => 'number',
                ],
                'parameters' => [
                    'p_from_date' => 'string',
                    'p_to_date' => 'string',
                    'p_department' => 'string',
                    'p_company' => 'string',
                    'p_user' => 'string',
                    'p_export_type' => 'string',
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
        $header = [
            [
                'id' => 'sales_invoices_header_band',
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
                            'id' => 'sales_invoices_logo',
                            'type' => 'text',
                            'content' => '<div class="hotel-logo" style="min-height: 50px;">{{hotel.logo}}</div>',
                            'style' => [
                                'fontSize' => '12px',
                                'marginTop' => '0px',
                                'marginBottom' => '0px',
                                'marginLeft' => '0px',
                                'marginRight' => '0px',
                                'paddingTop' => '0px',
                                'paddingBottom' => '0px',
                                'paddingLeft' => '0px',
                                'paddingRight' => '0px',
                            ],
                        ]],
                    ],
                    [
                        'width' => '70%',
                        'blocks' => [[
                            'id' => 'sales_invoices_hotel_info',
                            'type' => 'text',
                            'content' => '<div class="hotel-information" style="text-align: right; font-size: 10px; line-height: 1.5;">'
                                .'<div><b>Địa chỉ:</b> {{hotel.address}}</div>'
                                .'<div><b>Nhân viên:</b> {{report.generated_by}}</div>'
                                .'</div>',
                            'style' => [
                                'textAlign' => 'right',
                                'fontSize' => '10px',
                                'marginTop' => '0px',
                                'marginBottom' => '0px',
                                'marginLeft' => '0px',
                                'marginRight' => '0px',
                                'paddingTop' => '0px',
                                'paddingBottom' => '0px',
                                'paddingLeft' => '0px',
                                'paddingRight' => '0px',
                            ],
                        ]],
                    ],
                ],
            ],
            [
                'id' => 'sales_invoices_divider',
                'type' => 'divider',
                'content' => '<hr class="header-divider" style="border: none; border-top: 1px solid #333; margin: 2px 0 8px 0;">',
                'style' => [
                    'marginTop' => '2px',
                    'marginBottom' => '6px',
                    'marginLeft' => '0px',
                    'marginRight' => '0px',
                    'paddingTop' => '0px',
                    'paddingBottom' => '0px',
                    'paddingLeft' => '0px',
                    'paddingRight' => '0px',
                ],
            ],
            [
                'id' => 'sales_invoices_title',
                'type' => 'text',
                'content' => '<h1 style="text-align: center; font-size: 18px; font-weight: bold; margin: 4px 0; text-transform: uppercase;">BÁO CÁO HÓA ĐƠN BÁN HÀNG</h1>',
                'style' => [
                    'textAlign' => 'center',
                    'fontWeight' => 'bold',
                    'fontSize' => '18px',
                    'marginTop' => '4px',
                    'marginBottom' => '0px',
                    'marginLeft' => '0px',
                    'marginRight' => '0px',
                    'paddingTop' => '0px',
                    'paddingBottom' => '0px',
                    'paddingLeft' => '0px',
                    'paddingRight' => '0px',
                ],
            ],
            [
                'id' => 'sales_invoices_period',
                'type' => 'text',
                'content' => '<p class="period" style="text-align: center; font-size: 10px; margin: 2px 0 10px 0;"><b>Ngày:</b> {{parameters.p_from_date}} &nbsp; ~ &nbsp; {{parameters.p_to_date}}</p>',
                'style' => [
                    'textAlign' => 'center',
                    'fontSize' => '10px',
                    'marginTop' => '2px',
                    'marginBottom' => '10px',
                    'marginLeft' => '0px',
                    'marginRight' => '0px',
                    'paddingTop' => '0px',
                    'paddingBottom' => '0px',
                    'paddingLeft' => '0px',
                    'paddingRight' => '0px',
                ],
            ],
        ];

        return [
            'header' => $header,
            'detail' => [
                [
                    'id' => 'sales_invoices_table',
                    'type' => 'table',
                    'dataSource' => 'rows',
                    'tableType' => 'dynamic',
                    'tableStyle' => 'grid',
                    'tableClassName' => 'sales-invoices-table',
                    'style' => [
                        'width' => '100%',
                        'fontSize' => '9.5px',
                        'borderCollapse' => 'collapse',
                        'marginTop' => '4px',
                        'marginBottom' => '0px',
                        'marginLeft' => '0px',
                        'marginRight' => '0px',
                        'paddingTop' => '0px',
                        'paddingBottom' => '0px',
                        'paddingLeft' => '0px',
                        'paddingRight' => '0px',
                        'borderWidth' => '1px',
                        'borderStyle' => 'solid',
                        'borderColor' => '#aeb5c0',
                        'borderSide' => 'all',
                        'backgroundColor' => '#ffffff',
                    ],
                    'hasTwoTierHeader' => true,
                    'topHeader' => [
                        ['label' => 'Mã HĐ', 'rowspan' => 2, 'width' => '5%', 'align' => 'center'],
                        ['label' => 'Mã TT', 'rowspan' => 2, 'width' => '5%', 'align' => 'center'],
                        ['label' => 'Ngày', 'rowspan' => 2, 'width' => '7%', 'align' => 'center'],
                        ['label' => 'Phòng', 'rowspan' => 2, 'width' => '5%', 'align' => 'center'],
                        ['label' => 'MÃ VAT', 'rowspan' => 2, 'width' => '9%', 'align' => 'center'],
                        ['label' => 'Khách', 'rowspan' => 2, 'width' => '16%', 'align' => 'center'],
                        ['label' => 'Công Ty', 'rowspan' => 2, 'width' => '10%', 'align' => 'center'],
                        ['label' => 'Doanh Thu', 'rowspan' => 2, 'width' => '7%', 'align' => 'center'],
                        ['label' => 'Hình Thức Thanh Toán', 'colspan' => 6, 'align' => 'center'],
                    ],
                    'groups' => [
                        [
                            'id' => 'sales_invoices_date_group',
                            'field' => 'InvoiceDateFormatted',
                            'label' => 'Ngày: {{row.InvoiceDateFormatted}}',
                            'className' => 'date-group-header',
                            'enabledBy' => '',
                            'sort' => 'ASC',
                            'headerCells' => [
                                [
                                    'id' => 'date_group_cell',
                                    'type' => 'text',
                                    'content' => '<span style="color: #2e7d32; font-weight: bold;">Ngày:</span> {{row.InvoiceDateFormatted}}',
                                    'colspan' => 14,
                                    'align' => 'left',
                                    'backgroundColor' => '#ffffff',
                                    'color' => '#2e7d32',
                                    'borderColor' => '#aeb5c0',
                                    'fontSize' => '9.5px',
                                    'fontWeight' => 'bold',
                                    'style' => [
                                        'backgroundColor' => '#ffffff',
                                        'color' => '#2e7d32',
                                        'border' => '1px solid #aeb5c0',
                                        'padding' => '4px 6px',
                                        'fontWeight' => 'bold',
                                        'fontSize' => '9.5px',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'grouping' => [
                        [
                            'id' => 'sales_invoices_date_group',
                            'field' => 'InvoiceDateFormatted',
                            'label' => 'Ngày: {{row.InvoiceDateFormatted}}',
                            'className' => 'date-group-header',
                            'enabledBy' => '',
                            'sort' => 'ASC',
                            'headerCells' => [
                                [
                                    'id' => 'date_group_cell',
                                    'type' => 'text',
                                    'content' => '<span style="color: #2e7d32; font-weight: bold;">Ngày:</span> {{row.InvoiceDateFormatted}}',
                                    'colspan' => 14,
                                    'align' => 'left',
                                    'backgroundColor' => '#ffffff',
                                    'color' => '#2e7d32',
                                    'borderColor' => '#aeb5c0',
                                    'fontSize' => '9.5px',
                                    'fontWeight' => 'bold',
                                    'style' => [
                                        'backgroundColor' => '#ffffff',
                                        'color' => '#2e7d32',
                                        'border' => '1px solid #aeb5c0',
                                        'padding' => '4px 6px',
                                        'fontWeight' => 'bold',
                                        'fontSize' => '9.5px',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'columns' => $this->columns(),
                    'customRows' => [
                        [
                            'id' => 'sales_invoices_day_subtotal',
                            'enabledBy' => '',
                            'scope' => 'group',
                            'level' => 0,
                            'className' => 'day-subtotal-row',
                            'cells' => [
                                ['id' => 'sub_blank', 'type' => 'text', 'content' => '', 'colspan' => 3, 'align' => 'center',
                                 'backgroundColor' => '#ffffff', 'borderColor' => '#aeb5c0', 'fontSize' => '9.5px',
                                 'style' => ['backgroundColor' => '#ffffff', 'border' => '1px solid #aeb5c0', 'padding' => '3px 4px', 'fontSize' => '9.5px']],
                                ['id' => 'sub_qty', 'type' => 'text', 'content' => 'Số lượng:', 'colspan' => 1, 'align' => 'center',
                                 'fontWeight' => 'bold', 'backgroundColor' => '#ffffff', 'borderColor' => '#aeb5c0', 'fontSize' => '9.5px',
                                 'style' => ['backgroundColor' => '#ffffff', 'border' => '1px solid #aeb5c0', 'fontWeight' => 'bold', 'padding' => '3px 4px', 'fontSize' => '9.5px', 'textAlign' => 'center']],
                                ['id' => 'sub_label', 'type' => 'text', 'content' => 'Tổng của Ngày', 'colspan' => 3, 'align' => 'right',
                                 'fontWeight' => 'bold', 'backgroundColor' => '#ffffff', 'borderColor' => '#aeb5c0', 'fontSize' => '9.5px',
                                 'style' => ['backgroundColor' => '#ffffff', 'border' => '1px solid #aeb5c0', 'fontWeight' => 'bold', 'padding' => '3px 4px', 'fontSize' => '9.5px', 'textAlign' => 'right']],
                                ['id' => 'sub_amount', 'type' => 'text', 'content' => '{{group.sum.Amount|number}}', 'colspan' => 1, 'align' => 'right',
                                 'fontWeight' => 'bold', 'backgroundColor' => '#ffffff', 'borderColor' => '#aeb5c0', 'fontSize' => '9.5px',
                                 'style' => ['backgroundColor' => '#ffffff', 'border' => '1px solid #aeb5c0', 'fontWeight' => 'bold', 'padding' => '3px 4px', 'fontSize' => '9.5px', 'textAlign' => 'right']],
                                ['id' => 'sub_cash', 'type' => 'text', 'content' => '{{group.sum.Cash|number}}', 'colspan' => 1, 'align' => 'right',
                                 'fontWeight' => 'bold', 'backgroundColor' => '#ffffff', 'borderColor' => '#aeb5c0', 'fontSize' => '9.5px',
                                 'style' => ['backgroundColor' => '#ffffff', 'border' => '1px solid #aeb5c0', 'fontWeight' => 'bold', 'padding' => '3px 4px', 'fontSize' => '9.5px', 'textAlign' => 'right']],
                                ['id' => 'sub_card', 'type' => 'text', 'content' => '{{group.sum.Card|number}}', 'colspan' => 1, 'align' => 'right',
                                 'fontWeight' => 'bold', 'backgroundColor' => '#ffffff', 'borderColor' => '#aeb5c0', 'fontSize' => '9.5px',
                                 'style' => ['backgroundColor' => '#ffffff', 'border' => '1px solid #aeb5c0', 'fontWeight' => 'bold', 'padding' => '3px 4px', 'fontSize' => '9.5px', 'textAlign' => 'right']],
                                ['id' => 'sub_voucher', 'type' => 'text', 'content' => '{{group.sum.Voucher|number}}', 'colspan' => 1, 'align' => 'right',
                                 'fontWeight' => 'bold', 'backgroundColor' => '#ffffff', 'borderColor' => '#aeb5c0', 'fontSize' => '9.5px',
                                 'style' => ['backgroundColor' => '#ffffff', 'border' => '1px solid #aeb5c0', 'fontWeight' => 'bold', 'padding' => '3px 4px', 'fontSize' => '9.5px', 'textAlign' => 'right']],
                                ['id' => 'sub_city', 'type' => 'text', 'content' => '{{group.sum.City|number}}', 'colspan' => 1, 'align' => 'right',
                                 'fontWeight' => 'bold', 'backgroundColor' => '#ffffff', 'borderColor' => '#aeb5c0', 'fontSize' => '9.5px',
                                 'style' => ['backgroundColor' => '#ffffff', 'border' => '1px solid #aeb5c0', 'fontWeight' => 'bold', 'padding' => '3px 4px', 'fontSize' => '9.5px', 'textAlign' => 'right']],
                                ['id' => 'sub_dpcash', 'type' => 'text', 'content' => '{{group.sum.DPCash|number}}', 'colspan' => 1, 'align' => 'right',
                                 'fontWeight' => 'bold', 'backgroundColor' => '#ffffff', 'borderColor' => '#aeb5c0', 'fontSize' => '9.5px',
                                 'style' => ['backgroundColor' => '#ffffff', 'border' => '1px solid #aeb5c0', 'fontWeight' => 'bold', 'padding' => '3px 4px', 'fontSize' => '9.5px', 'textAlign' => 'right']],
                                ['id' => 'sub_dpcard', 'type' => 'text', 'content' => '{{group.sum.DPCard|number}}', 'colspan' => 1, 'align' => 'right',
                                 'fontWeight' => 'bold', 'backgroundColor' => '#ffffff', 'borderColor' => '#aeb5c0', 'fontSize' => '9.5px',
                                 'style' => ['backgroundColor' => '#ffffff', 'border' => '1px solid #aeb5c0', 'fontWeight' => 'bold', 'padding' => '3px 4px', 'fontSize' => '9.5px', 'textAlign' => 'right']],
                            ],
                        ],
                        [
                            'id' => 'sales_invoices_grand_total',
                            'enabledBy' => '',
                            'scope' => 'table',
                            'level' => 0,
                            'className' => 'report-grand-total-row',
                            'cells' => [
                                ['id' => 'gt_label', 'type' => 'text', 'content' => 'Tổng:', 'colspan' => 7, 'align' => 'right',
                                 'fontWeight' => 'bold', 'backgroundColor' => '#ffffff', 'borderColor' => '#aeb5c0', 'fontSize' => '9.5px',
                                 'style' => ['backgroundColor' => '#ffffff', 'border' => '1px solid #aeb5c0', 'fontWeight' => 'bold', 'padding' => '3px 4px', 'fontSize' => '9.5px', 'textAlign' => 'right']],
                                ['id' => 'gt_amount', 'type' => 'binding', 'binding' => 'aggregate.rows.sum.Amount', 'colspan' => 1, 'align' => 'right', 'format' => 'number',
                                 'fontWeight' => 'bold', 'backgroundColor' => '#ffffff', 'borderColor' => '#aeb5c0', 'fontSize' => '9.5px',
                                 'style' => ['backgroundColor' => '#ffffff', 'border' => '1px solid #aeb5c0', 'fontWeight' => 'bold', 'padding' => '3px 4px', 'fontSize' => '9.5px', 'textAlign' => 'right']],
                                ['id' => 'gt_cash', 'type' => 'binding', 'binding' => 'aggregate.rows.sum.Cash', 'colspan' => 1, 'align' => 'right', 'format' => 'number',
                                 'fontWeight' => 'bold', 'backgroundColor' => '#ffffff', 'borderColor' => '#aeb5c0', 'fontSize' => '9.5px',
                                 'style' => ['backgroundColor' => '#ffffff', 'border' => '1px solid #aeb5c0', 'fontWeight' => 'bold', 'padding' => '3px 4px', 'fontSize' => '9.5px', 'textAlign' => 'right']],
                                ['id' => 'gt_card', 'type' => 'binding', 'binding' => 'aggregate.rows.sum.Card', 'colspan' => 1, 'align' => 'right', 'format' => 'number',
                                 'fontWeight' => 'bold', 'backgroundColor' => '#ffffff', 'borderColor' => '#aeb5c0', 'fontSize' => '9.5px',
                                 'style' => ['backgroundColor' => '#ffffff', 'border' => '1px solid #aeb5c0', 'fontWeight' => 'bold', 'padding' => '3px 4px', 'fontSize' => '9.5px', 'textAlign' => 'right']],
                                ['id' => 'gt_voucher', 'type' => 'binding', 'binding' => 'aggregate.rows.sum.Voucher', 'colspan' => 1, 'align' => 'right', 'format' => 'number',
                                 'fontWeight' => 'bold', 'backgroundColor' => '#ffffff', 'borderColor' => '#aeb5c0', 'fontSize' => '9.5px',
                                 'style' => ['backgroundColor' => '#ffffff', 'border' => '1px solid #aeb5c0', 'fontWeight' => 'bold', 'padding' => '3px 4px', 'fontSize' => '9.5px', 'textAlign' => 'right']],
                                ['id' => 'gt_city', 'type' => 'binding', 'binding' => 'aggregate.rows.sum.City', 'colspan' => 1, 'align' => 'right', 'format' => 'number',
                                 'fontWeight' => 'bold', 'backgroundColor' => '#ffffff', 'borderColor' => '#aeb5c0', 'fontSize' => '9.5px',
                                 'style' => ['backgroundColor' => '#ffffff', 'border' => '1px solid #aeb5c0', 'fontWeight' => 'bold', 'padding' => '3px 4px', 'fontSize' => '9.5px', 'textAlign' => 'right']],
                                ['id' => 'gt_dpcash', 'type' => 'binding', 'binding' => 'aggregate.rows.sum.DPCash', 'colspan' => 1, 'align' => 'right', 'format' => 'number',
                                 'fontWeight' => 'bold', 'backgroundColor' => '#ffffff', 'borderColor' => '#aeb5c0', 'fontSize' => '9.5px',
                                 'style' => ['backgroundColor' => '#ffffff', 'border' => '1px solid #aeb5c0', 'fontWeight' => 'bold', 'padding' => '3px 4px', 'fontSize' => '9.5px', 'textAlign' => 'right']],
                                ['id' => 'gt_dpcard', 'type' => 'binding', 'binding' => 'aggregate.rows.sum.DPCard', 'colspan' => 1, 'align' => 'right', 'format' => 'number',
                                 'fontWeight' => 'bold', 'backgroundColor' => '#ffffff', 'borderColor' => '#aeb5c0', 'fontSize' => '9.5px',
                                 'style' => ['backgroundColor' => '#ffffff', 'border' => '1px solid #aeb5c0', 'fontWeight' => 'bold', 'padding' => '3px 4px', 'fontSize' => '9.5px', 'textAlign' => 'right']],
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'sales_invoices_allocation_title',
                    'type' => 'text',
                    'content' => '<h2 style="text-align: center; font-size: 16px; font-weight: bold; margin: 24px 0 10px 0;">Bảng Phân Bổ Tiền Tệ</h2>',
                    'style' => [
                        'textAlign' => 'center',
                        'fontWeight' => 'bold',
                        'fontSize' => '16px',
                        'marginTop' => '24px',
                        'marginBottom' => '10px',
                        'marginLeft' => '0px',
                        'marginRight' => '0px',
                        'paddingTop' => '0px',
                        'paddingBottom' => '0px',
                        'paddingLeft' => '0px',
                        'paddingRight' => '0px',
                    ],
                ],
                [
                    'id' => 'sales_invoices_allocation_table',
                    'type' => 'table',
                    'dataSource' => 'currency_allocations',
                    'tableType' => 'dynamic',
                    'tableStyle' => 'grid',
                    'tableClassName' => 'sales-invoices-allocation-table',
                    'style' => [
                        'width' => '100%',
                        'fontSize' => '9.5px',
                        'borderCollapse' => 'collapse',
                        'marginTop' => '0px',
                        'marginBottom' => '14px',
                        'marginLeft' => '0px',
                        'marginRight' => '0px',
                        'paddingTop' => '0px',
                        'paddingBottom' => '0px',
                        'paddingLeft' => '0px',
                        'paddingRight' => '0px',
                        'borderWidth' => '1px',
                        'borderStyle' => 'solid',
                        'borderColor' => '#aeb5c0',
                        'borderSide' => 'all',
                        'backgroundColor' => '#ffffff',
                    ],
                    'columns' => [
                        [
                            'header' => 'Hình Thức Thanh Toán',
                            'value' => 'item.Method',
                            'width' => '34%',
                            'align' => 'left',
                            'headerStyle' => ['backgroundColor' => '#d9deea', 'border' => '1px solid #aeb5c0', 'textAlign' => 'center', 'fontWeight' => 'bold', 'padding' => '4px 6px', 'fontSize' => '9.5px', 'color' => '#111111'],
                            'cellStyle' => ['border' => '1px solid #aeb5c0', 'textAlign' => 'left', 'padding' => '4px 6px', 'fontSize' => '9.5px', 'color' => '#111111'],
                        ],
                        [
                            'header' => 'Thu Ngân',
                            'value' => 'item.CashierAmount',
                            'width' => '22%',
                            'align' => 'right',
                            'format' => 'number',
                            'headerStyle' => ['backgroundColor' => '#d9deea', 'border' => '1px solid #aeb5c0', 'textAlign' => 'center', 'fontWeight' => 'bold', 'padding' => '4px 6px', 'fontSize' => '9.5px', 'color' => '#111111'],
                            'cellStyle' => ['border' => '1px solid #aeb5c0', 'textAlign' => 'right', 'padding' => '4px 6px', 'fontSize' => '9.5px', 'color' => '#111111'],
                        ],
                        [
                            'header' => 'Đặt Cọc',
                            'value' => 'item.DepositAmount',
                            'width' => '22%',
                            'align' => 'right',
                            'format' => 'number',
                            'headerStyle' => ['backgroundColor' => '#d9deea', 'border' => '1px solid #aeb5c0', 'textAlign' => 'center', 'fontWeight' => 'bold', 'padding' => '4px 6px', 'fontSize' => '9.5px', 'color' => '#111111'],
                            'cellStyle' => ['border' => '1px solid #aeb5c0', 'textAlign' => 'right', 'padding' => '4px 6px', 'fontSize' => '9.5px', 'color' => '#111111'],
                        ],
                        [
                            'header' => 'Tổng Theo C.ty',
                            'value' => 'item.TotalAmount',
                            'width' => '22%',
                            'align' => 'right',
                            'format' => 'number',
                            'headerStyle' => ['backgroundColor' => '#d9deea', 'border' => '1px solid #aeb5c0', 'textAlign' => 'center', 'fontWeight' => 'bold', 'padding' => '4px 6px', 'fontSize' => '9.5px', 'color' => '#111111'],
                            'cellStyle' => ['border' => '1px solid #aeb5c0', 'textAlign' => 'right', 'padding' => '4px 6px', 'fontSize' => '9.5px', 'color' => '#111111'],
                        ],
                    ],
                    'customRows' => [
                        [
                            'id' => 'allocation_grand_total',
                            'enabledBy' => '',
                            'scope' => 'table',
                            'level' => 0,
                            'className' => 'allocation-total-row',
                            'cells' => [
                                ['id' => 'alloc_spacer', 'type' => 'text', 'content' => '', 'colspan' => 2, 'align' => 'center',
                                 'backgroundColor' => '#d9deea', 'borderColor' => '#aeb5c0', 'fontSize' => '9.5px',
                                 'style' => ['backgroundColor' => '#d9deea', 'border' => '1px solid #aeb5c0', 'padding' => '4px 6px', 'fontSize' => '9.5px']],
                                ['id' => 'alloc_total_label', 'type' => 'text', 'content' => 'Tổng Theo C.ty', 'colspan' => 1, 'align' => 'right',
                                 'fontWeight' => 'bold', 'backgroundColor' => '#d9deea', 'borderColor' => '#aeb5c0', 'fontSize' => '9.5px',
                                 'style' => ['backgroundColor' => '#d9deea', 'border' => '1px solid #aeb5c0', 'fontWeight' => 'bold', 'padding' => '4px 6px', 'fontSize' => '9.5px', 'textAlign' => 'right']],
                                ['id' => 'alloc_total_val', 'type' => 'binding', 'binding' => 'aggregate.currency_allocations.sum.TotalAmount', 'colspan' => 1, 'align' => 'right', 'format' => 'number',
                                 'fontWeight' => 'bold', 'backgroundColor' => '#d9deea', 'borderColor' => '#aeb5c0', 'fontSize' => '9.5px',
                                 'style' => ['backgroundColor' => '#d9deea', 'border' => '1px solid #aeb5c0', 'fontWeight' => 'bold', 'padding' => '4px 6px', 'fontSize' => '9.5px', 'textAlign' => 'right']],
                            ],
                        ],
                    ],
                ],
            ],
            'footer' => [],
        ];
    }

    public function columns(): array
    {
        return [
            [
                'header' => 'Mã HĐ',
                'value' => 'row.BillID',
                'width' => '5%',
                'align' => 'center',
                'headerStyle' => ['backgroundColor' => '#d9deea', 'border' => '1px solid #aeb5c0', 'textAlign' => 'center', 'fontWeight' => 'bold', 'padding' => '3px 4px', 'fontSize' => '9.5px', 'color' => '#111111'],
                'cellStyle' => ['border' => '1px solid #aeb5c0', 'textAlign' => 'center', 'padding' => '3px 4px', 'fontSize' => '9.5px', 'color' => '#2e7d32', 'fontWeight' => '500'],
            ],
            [
                'header' => 'Mã TT',
                'value' => 'row.PaymentID',
                'width' => '5%',
                'align' => 'center',
                'headerStyle' => ['backgroundColor' => '#d9deea', 'border' => '1px solid #aeb5c0', 'textAlign' => 'center', 'fontWeight' => 'bold', 'padding' => '3px 4px', 'fontSize' => '9.5px', 'color' => '#111111'],
                'cellStyle' => ['border' => '1px solid #aeb5c0', 'textAlign' => 'center', 'padding' => '3px 4px', 'fontSize' => '9.5px', 'color' => '#111111'],
            ],
            [
                'header' => 'Ngày',
                'value' => 'row.InvoiceDateFormatted',
                'width' => '7%',
                'align' => 'center',
                'headerStyle' => ['backgroundColor' => '#d9deea', 'border' => '1px solid #aeb5c0', 'textAlign' => 'center', 'fontWeight' => 'bold', 'padding' => '3px 4px', 'fontSize' => '9.5px', 'color' => '#111111'],
                'cellStyle' => ['border' => '1px solid #aeb5c0', 'textAlign' => 'center', 'padding' => '3px 4px', 'fontSize' => '9.5px', 'color' => '#111111'],
            ],
            [
                'header' => 'Phòng',
                'value' => 'row.Room',
                'width' => '5%',
                'align' => 'center',
                'headerStyle' => ['backgroundColor' => '#d9deea', 'border' => '1px solid #aeb5c0', 'textAlign' => 'center', 'fontWeight' => 'bold', 'padding' => '3px 4px', 'fontSize' => '9.5px', 'color' => '#111111'],
                'cellStyle' => ['border' => '1px solid #aeb5c0', 'textAlign' => 'center', 'padding' => '3px 4px', 'fontSize' => '9.5px', 'color' => '#111111'],
            ],
            [
                'header' => 'MÃ VAT',
                'value' => 'row.VATNo',
                'width' => '9%',
                'align' => 'center',
                'headerStyle' => ['backgroundColor' => '#d9deea', 'border' => '1px solid #aeb5c0', 'textAlign' => 'center', 'fontWeight' => 'bold', 'padding' => '3px 4px', 'fontSize' => '9.5px', 'color' => '#111111'],
                'cellStyle' => ['border' => '1px solid #aeb5c0', 'textAlign' => 'center', 'padding' => '3px 4px', 'fontSize' => '9.5px', 'color' => '#111111'],
            ],
            [
                'header' => 'Khách',
                'value' => 'row.GuestName',
                'width' => '16%',
                'align' => 'left',
                'headerStyle' => ['backgroundColor' => '#d9deea', 'border' => '1px solid #aeb5c0', 'textAlign' => 'center', 'fontWeight' => 'bold', 'padding' => '3px 4px', 'fontSize' => '9.5px', 'color' => '#111111'],
                'cellStyle' => ['border' => '1px solid #aeb5c0', 'textAlign' => 'left', 'padding' => '3px 4px', 'fontSize' => '9.5px', 'color' => '#111111'],
            ],
            [
                'header' => 'Công Ty',
                'value' => 'row.CompanyName',
                'width' => '10%',
                'align' => 'left',
                'headerStyle' => ['backgroundColor' => '#d9deea', 'border' => '1px solid #aeb5c0', 'textAlign' => 'center', 'fontWeight' => 'bold', 'padding' => '3px 4px', 'fontSize' => '9.5px', 'color' => '#111111'],
                'cellStyle' => ['border' => '1px solid #aeb5c0', 'textAlign' => 'left', 'padding' => '3px 4px', 'fontSize' => '9.5px', 'color' => '#111111'],
            ],
            [
                'header' => 'Doanh Thu',
                'value' => 'row.Amount',
                'width' => '7%',
                'align' => 'right',
                'format' => 'number',
                'headerStyle' => ['backgroundColor' => '#d9deea', 'border' => '1px solid #aeb5c0', 'textAlign' => 'center', 'fontWeight' => 'bold', 'padding' => '3px 4px', 'fontSize' => '9.5px', 'color' => '#111111'],
                'cellStyle' => ['border' => '1px solid #aeb5c0', 'textAlign' => 'right', 'padding' => '3px 4px', 'fontSize' => '9.5px', 'color' => '#111111'],
            ],
            [
                'header' => 'Tiền Mặt',
                'value' => 'row.Cash',
                'width' => '6%',
                'align' => 'right',
                'format' => 'number',
                'headerStyle' => ['backgroundColor' => '#d9deea', 'border' => '1px solid #aeb5c0', 'textAlign' => 'center', 'fontWeight' => 'bold', 'padding' => '3px 4px', 'fontSize' => '9.5px', 'color' => '#111111'],
                'cellStyle' => ['border' => '1px solid #aeb5c0', 'textAlign' => 'right', 'padding' => '3px 4px', 'fontSize' => '9.5px', 'color' => '#111111'],
            ],
            [
                'header' => 'CK/Thẻ',
                'value' => 'row.Card',
                'width' => '6%',
                'align' => 'right',
                'format' => 'number',
                'headerStyle' => ['backgroundColor' => '#d9deea', 'border' => '1px solid #aeb5c0', 'textAlign' => 'center', 'fontWeight' => 'bold', 'padding' => '3px 4px', 'fontSize' => '9.5px', 'color' => '#111111'],
                'cellStyle' => ['border' => '1px solid #aeb5c0', 'textAlign' => 'right', 'padding' => '3px 4px', 'fontSize' => '9.5px', 'color' => '#111111'],
            ],
            [
                'header' => 'Voucher Miễn Phí',
                'value' => 'row.Voucher',
                'width' => '6%',
                'align' => 'right',
                'format' => 'number',
                'headerStyle' => ['backgroundColor' => '#d9deea', 'border' => '1px solid #aeb5c0', 'textAlign' => 'center', 'fontWeight' => 'bold', 'padding' => '3px 4px', 'fontSize' => '9.5px', 'color' => '#111111'],
                'cellStyle' => ['border' => '1px solid #aeb5c0', 'textAlign' => 'right', 'padding' => '3px 4px', 'fontSize' => '9.5px', 'color' => '#111111'],
            ],
            [
                'header' => 'City Ledger',
                'value' => 'row.City',
                'width' => '6%',
                'align' => 'right',
                'format' => 'number',
                'headerStyle' => ['backgroundColor' => '#d9deea', 'border' => '1px solid #aeb5c0', 'textAlign' => 'center', 'fontWeight' => 'bold', 'padding' => '3px 4px', 'fontSize' => '9.5px', 'color' => '#111111'],
                'cellStyle' => ['border' => '1px solid #aeb5c0', 'textAlign' => 'right', 'padding' => '3px 4px', 'fontSize' => '9.5px', 'color' => '#111111'],
            ],
            [
                'header' => 'Đặt Cọc Tiền Mặt',
                'value' => 'row.DPCash',
                'width' => '6%',
                'align' => 'right',
                'format' => 'number',
                'headerStyle' => ['backgroundColor' => '#d9deea', 'border' => '1px solid #aeb5c0', 'textAlign' => 'center', 'fontWeight' => 'bold', 'padding' => '3px 4px', 'fontSize' => '9.5px', 'color' => '#111111'],
                'cellStyle' => ['border' => '1px solid #aeb5c0', 'textAlign' => 'right', 'padding' => '3px 4px', 'fontSize' => '9.5px', 'color' => '#111111'],
            ],
            [
                'header' => 'Đặt Cọc Bằng Thẻ',
                'value' => 'row.DPCard',
                'width' => '6%',
                'align' => 'right',
                'format' => 'number',
                'headerStyle' => ['backgroundColor' => '#d9deea', 'border' => '1px solid #aeb5c0', 'textAlign' => 'center', 'fontWeight' => 'bold', 'padding' => '3px 4px', 'fontSize' => '9.5px', 'color' => '#111111'],
                'cellStyle' => ['border' => '1px solid #aeb5c0', 'textAlign' => 'right', 'padding' => '3px 4px', 'fontSize' => '9.5px', 'color' => '#111111'],
            ],
        ];
    }

    public function css(): string
    {
        return <<<'CSS'
body {
    font-family: Arial, Helvetica, sans-serif;
    color: #111;
    font-size: 9.5px;
}
.sales-invoices-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 9.5px;
    line-height: 1.25;
    margin-top: 4px;
}
.sales-invoices-table th,
.sales-invoices-table td {
    border: 1px solid #aeb5c0;
    padding: 3px 4px;
    vertical-align: middle;
}
.sales-invoices-table thead th {
    background-color: #d9deea;
    color: #111;
    font-weight: bold;
    text-align: center;
}
.sales-invoices-table .date-group-header td {
    background-color: #fff;
    border-top: 1px solid #94a3b8;
    text-align: left;
}
.sales-invoices-table .day-subtotal-row td,
.sales-invoices-table .report-grand-total-row td {
    background-color: #fff;
    font-weight: bold;
    border-top: 1px solid #aeb5c0;
    border-bottom: 1px solid #aeb5c0;
}
.sales-invoices-allocation-table {
    width: 55%;
    margin: 0 auto;
    border-collapse: collapse;
    font-size: 9.5px;
}
.sales-invoices-allocation-table th,
.sales-invoices-allocation-table td {
    border: 1px solid #aeb5c0;
    padding: 4px 6px;
    vertical-align: middle;
}
.sales-invoices-allocation-table thead th {
    background-color: #d9deea;
    font-weight: bold;
    text-align: center;
}
.sales-invoices-allocation-table .allocation-total-row td {
    background-color: #d9deea;
    font-weight: bold;
}
.hotel-logo img {
    max-height: 50px;
    object-fit: contain;
}
@media print {
    thead { display: table-header-group; }
    tr { break-inside: avoid; }
}
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
                $html .= '<div class="report-header-band">'."\n";
                foreach ($bands['header'] ?? [] as $block) {
                    $html .= $this->compileBlock($block);
                }
                $html .= '</div>'."\n";
                continue;
            }

            $html .= '<div class="report-'.($band === 'detail' ? 'detail' : $band).'-band">'."\n";
            foreach ($bands[$band] ?? [] as $block) {
                $html .= $this->compileBlock($block);
            }
            $html .= '</div>'."\n";
        }

        return $html;
    }

    private function compileBlock(array $block): string
    {
        $type = $block['type'] ?? 'text';
        if ($type === 'text' || $type === 'divider') {
            return ($block['content'] ?? '')."\n";
        }

        if ($type === 'columns') {
            $html = '<div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 4px;">'."\n";
            foreach ($block['columns'] ?? [] as $col) {
                $width = $col['width'] ?? 'auto';
                $html .= '  <div style="width: '.$width.';">'."\n";
                foreach ($col['blocks'] ?? [] as $b) {
                    $html .= '    '.$this->compileBlock($b);
                }
                $html .= '  </div>'."\n";
            }
            $html .= '</div>'."\n";
            return $html;
        }

        if ($type === 'table') {
            return $this->compileTable($block);
        }

        return '';
    }

    private function compileStyle(array $style): string
    {
        return collect($style)
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->map(fn ($v, $k) => strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $k)).': '.$v)
            ->implode('; ');
    }

    private function compileTable(array $block): string
    {
        $columns = $block['columns'] ?? [];
        $className = $block['tableClassName'] ?? 'report-table';
        $styleAttr = ! empty($block['style']) ? ' style="'.$this->compileStyle($block['style']).'"' : '';

        $html = '<table class="'.$className.'"'.$styleAttr.'>'."\n";
        $html .= '  <colgroup>'."\n";
        foreach ($columns as $col) {
            $html .= '    <col style="width: '.($col['width'] ?? 'auto').'">'."\n";
        }
        $html .= '  </colgroup>'."\n";

        $html .= '  <thead>'."\n";
        if (! empty($block['hasTwoTierHeader']) && ! empty($block['topHeader'])) {
            $html .= "    <tr>\n";
            foreach ($block['topHeader'] as $th) {
                $thStyle = 'text-align: '.($th['align'] ?? 'center').';';
                $rowspanAttr = ! empty($th['rowspan']) ? ' rowspan="'.$th['rowspan'].'"' : '';
                $colspanAttr = ! empty($th['colspan']) ? ' colspan="'.$th['colspan'].'"' : '';
                $html .= '      <th'.$rowspanAttr.$colspanAttr.' style="'.$thStyle.'">'.($th['label'] ?? '').'</th>'."\n";
            }
            $html .= "    </tr>\n";

            // Row 2 of header (sub-columns for Hình Thức Thanh Toán)
            $html .= "    <tr>\n";
            $subColumns = array_slice($columns, 8); // Columns 8..13 (Tiền Mặt, CK/Thẻ, Voucher, City, DPCash, DPCard)
            foreach ($subColumns as $col) {
                $html .= '      <th style="text-align: '.($col['align'] ?? 'center').';">'.($col['header'] ?? '').'</th>'."\n";
            }
            $html .= "    </tr>\n";
        } else {
            $html .= "    <tr>\n";
            foreach ($columns as $col) {
                $html .= '      <th style="text-align: '.($col['align'] ?? 'center').';">'.($col['header'] ?? '').'</th>'."\n";
            }
            $html .= "    </tr>\n";
        }
        $html .= "  </thead>\n";

        $groups = $block['groups'] ?? $block['grouping'] ?? [];
        $primaryGroup = $groups[0] ?? null;
        $dataSource = $block['dataSource'] ?? 'rows';
        $customRows = $block['customRows'] ?? [];

        if ($primaryGroup) {
            $groupByAttr = ' data-group-by="'.htmlspecialchars((string) $primaryGroup['field'], ENT_QUOTES, 'UTF-8').'"';
            $html .= '  <tbody class="pms-grouped-rows" data-source="'.$dataSource.'" data-group-configured="1"'.$groupByAttr.'>'."\n";

            $html .= '    <tr class="pms-group-header" data-group-level="0" data-group-field="'.htmlspecialchars((string) $primaryGroup['field'], ENT_QUOTES, 'UTF-8').'">'."\n";
            if (! empty($primaryGroup['headerCells'])) {
                foreach ($primaryGroup['headerCells'] as $cell) {
                    $html .= '      <td colspan="'.($cell['colspan'] ?? count($columns)).'">'
                        .($cell['content'] ?? '').'</td>'."\n";
                }
            } else {
                $html .= '      <td colspan="'.count($columns).'">'.($primaryGroup['label'] ?? '').'</td>'."\n";
            }
            $html .= "    </tr>\n";

            $html .= "    <tr class=\"pms-detail-row\">\n";
            foreach ($columns as $col) {
                $format = ($col['format'] ?? '') === 'number' ? '|number' : '';
                $colStyle = 'text-align: '.($col['align'] ?? 'left').';';
                if (! empty($col['cellStyle']['color'])) {
                    $colStyle .= ' color: '.$col['cellStyle']['color'].';';
                }
                if (! empty($col['cellStyle']['fontWeight'])) {
                    $colStyle .= ' font-weight: '.$col['cellStyle']['fontWeight'].';';
                }
                $html .= '      <td style="'.$colStyle.'">{{'.($col['value'] ?? '').$format.'}}</td>'."\n";
            }
            $html .= "    </tr>\n";

            $groupCustomRows = array_filter($customRows, fn ($r) => ($r['scope'] ?? 'table') === 'group');
            foreach ($groupCustomRows as $crow) {
                $levelAttr = isset($crow['level']) ? ' data-group-level="'.$crow['level'].'"' : '';
                $html .= '    <tr class="pms-group-footer '.($crow['className'] ?? '').'"'.$levelAttr.'>'."\n";
                foreach ($crow['cells'] ?? [] as $cell) {
                    $colspan = isset($cell['colspan']) ? ' colspan="'.$cell['colspan'].'"' : '';
                    $align = $cell['align'] ?? 'left';
                    $val = ! empty($cell['binding']) ? '{{'.$cell['binding'].(! empty($cell['format']) ? '|'.$cell['format'] : '').'}}' : ($cell['content'] ?? '');
                    $style = ! empty($cell['style']['fontWeight']) ? ' font-weight: '.$cell['style']['fontWeight'].';' : '';
                    $html .= '      <td'.$colspan.' style="text-align: '.$align.';'.$style.'">'.$val.'</td>'."\n";
                }
                $html .= "    </tr>\n";
            }

            $html .= "  </tbody>\n";
        } else {
            $html .= "  <tbody>\n";
            $html .= '    <tr class="pms-detail-row" data-source="'.$dataSource.'">'."\n";
            foreach ($columns as $col) {
                $format = ($col['format'] ?? '') === 'number' ? '|number' : '';
                $colStyle = 'text-align: '.($col['align'] ?? 'left').';';
                if (! empty($col['cellStyle']['fontWeight'])) {
                    $colStyle .= ' font-weight: '.$col['cellStyle']['fontWeight'].';';
                }
                $html .= '      <td style="'.$colStyle.'">{{'.($col['value'] ?? '').$format.'}}</td>'."\n";
            }
            $html .= "    </tr>\n";
            $html .= "  </tbody>\n";
        }

        $tableCustomRows = array_filter($customRows, fn ($r) => ($r['scope'] ?? 'table') === 'table');
        if (! empty($tableCustomRows)) {
            $html .= "  <tfoot>\n";
            foreach ($tableCustomRows as $crow) {
                $html .= '    <tr class="'.($crow['className'] ?? '').'">'."\n";
                foreach ($crow['cells'] ?? [] as $cell) {
                    $colspan = isset($cell['colspan']) ? ' colspan="'.$cell['colspan'].'"' : '';
                    $align = $cell['align'] ?? 'left';
                    $val = ! empty($cell['binding']) ? '{{'.$cell['binding'].(! empty($cell['format']) ? '|'.$cell['format'] : '').'}}' : ($cell['content'] ?? '');
                    $style = ! empty($cell['style']['fontWeight']) ? ' font-weight: '.$cell['style']['fontWeight'].';' : '';
                    $html .= '      <td'.$colspan.' style="text-align: '.$align.';'.$style.'">'.$val.'</td>'."\n";
                }
                $html .= "    </tr>\n";
            }
            $html .= "  </tfoot>\n";
        }

        $html .= "</table>\n";
        return $html;
    }
};
