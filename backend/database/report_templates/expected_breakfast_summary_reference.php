<?php

use App\Services\TemplateRendererService;

/**
 * Pure reference provider for the Expected Breakfast Summary Report (legacy sp_035).
 */
return new class
{
    public function definition(): array
    {
        return [
            'report' => 'EXPECTED_BREAKFAST_1_REFERENCE',
            'name' => 'Báo cáo dự kiến khách ăn sáng 1 - Mẫu tham chiếu',
            'page_size' => 'A4',
            'page_orientation' => 'portrait',
            'margin_top' => 6,
            'margin_bottom' => 6,
            'margin_left' => 8,
            'margin_right' => 8,
            'version' => '1.0',
            'content_html' => $this->html(),
            'content_json' => $this->blocks(),
            'css' => $this->css(),
        ];
    }

    public function html(): string
    {
        $headerProvider = require database_path('report_templates/minibar_invoices_by_product_reference.php');
        $headerHtml = $headerProvider->compileHeader($this->blocks()['header']);

        return $headerHtml.<<<'HTML'
<table class="breakfast-summary-table">
  <colgroup>
    <col style="width:8%">
    <col style="width:6%">
    <col style="width:7%">
    <col style="width:6%">
    <col style="width:7%">
    <col style="width:6%">
    <col style="width:23%">
    <col style="width:21%">
    <col style="width:16%">
  </colgroup>
  <thead>
    <tr>
      <th>Mã ĐK</th>
      <th>Phòng</th>
      <th>Người Lớn</th>
      <th>Trẻ em</th>
      <th>Trẻ Em MP</th>
      <th>Tổng</th>
      <th>Tên Khách Chính</th>
      <th>Công ty</th>
      <th>Ghi Chú</th>
    </tr>
  </thead>
  <tbody class="pms-grouped-rows" data-source="rows" data-group-by="DateGroup" data-subgroup-by="RoomType">
    <tr class="pms-group-header">
      <td colspan="9" class="date-group"><span class="date-label">Ngày :</span> {{row.DateGroup}}</td>
    </tr>
    <tr class="pms-subgroup-header">
      <td colspan="9" class="room-type-group">{{row.RoomType}}</td>
    </tr>
    <tr class="pms-detail-row">
      <td style="text-align:center;">{{row.BookingCode}}</td>
      <td style="text-align:center;">{{row.Room}}</td>
      <td style="text-align:center;">{{row.Adults|number}}</td>
      <td style="text-align:center;">{{row.Children|number}}</td>
      <td style="text-align:center;">{{row.ChildrenNK|number}}</td>
      <td style="text-align:center;">{{row.TotalPax|number}}</td>
      <td style="text-align:left;">{{row.GuestName}}</td>
      <td style="text-align:left;">{{row.CompanyName}}</td>
      <td style="text-align:left;">{{row.Note}}</td>
    </tr>
    <tr class="pms-group-footer date-subtotal-row">
      <td style="font-weight:bold; text-align:center;">Tổng Ngày</td>
      <td style="font-weight:bold; text-align:center;">{{row.DateTotalRooms|default:group.count}}</td>
      <td style="font-weight:bold; text-align:center;">{{row.DateTotalAdults|number}}</td>
      <td style="font-weight:bold; text-align:center;">{{row.DateTotalChildren|number}}</td>
      <td style="font-weight:bold; text-align:center;">{{row.DateTotalChildrenNK|number}}</td>
      <td style="font-weight:bold; text-align:center;">{{row.DateTotalPax|number}}</td>
      <td colspan="3"></td>
    </tr>
  </tbody>
  <tfoot>
    <tr class="report-total-row">
      <td style="font-weight:bold; text-align:center;">Tổng</td>
      <td style="font-weight:bold; text-align:center;">{{aggregate.rows.count|number}}</td>
      <td style="font-weight:bold; text-align:center;">{{totals.Adults|number}}</td>
      <td style="font-weight:bold; text-align:center;">{{totals.Children|number}}</td>
      <td style="font-weight:bold; text-align:center;">{{totals.ChildrenNK|number}}</td>
      <td style="font-weight:bold; text-align:center;">{{totals.TotalPax|number}}</td>
      <td colspan="3"></td>
    </tr>
  </tfoot>
</table>

<h2 class="country-summary-title">THỐNG KÊ KHÁCH THEO QUỐC GIA</h2>
<table class="country-summary-table">
  <colgroup>
    <col style="width:40%">
    <col style="width:30%">
    <col style="width:30%">
  </colgroup>
  <thead>
    <tr>
      <th>Quốc Gia</th>
      <th>Số Lượng</th>
      <th>Tỉ lệ (%)</th>
    </tr>
  </thead>
  <tbody>
    <tr class="pms-detail-row" data-source="country_summary">
      <td style="text-align:left;">{{item.Nationality}}</td>
      <td style="text-align:center;">{{item.Quantity|number}}</td>
      <td style="text-align:center;">{{item.Percentage}}</td>
    </tr>
  </tbody>
  <tfoot>
    <tr class="country-total-row">
      <td style="font-weight:bold; text-align:center;">Tổng</td>
      <td style="font-weight:bold; text-align:center;">{{totals.CountryTotalPax|number}}</td>
      <td style="font-weight:bold; text-align:center;">100.00%</td>
    </tr>
  </tfoot>
</table>

<div class="footer-signatures" style="margin-top: 35px; display: grid; grid-template-columns: 1fr 1fr;">
  <div style="text-align: center; font-weight: bold; font-size: 11px;">Bộ Phận FO</div>
  <div style="text-align: center; font-weight: bold; font-size: 11px;">Bộ Phận F&B</div>
</div>
HTML;
    }

    public function blocks(): array
    {
        $headerProvider = require database_path('report_templates/minibar_invoices_by_product_reference.php');
        $header = $headerProvider->headerBlocks(
            'expected_breakfast_summary',
            '<h1>BÁO CÁO DỰ KIẾN KHÁCH ĂN SÁNG</h1>',
            '<p class="report-period"><b>Ngày :</b> {{parameters.p_from_date}} &nbsp; ~ &nbsp; {{parameters.p_to_date}}</p>'
        );

        return [
            'header' => $header,
            'detail' => [
                [
                    'id' => 'expected_breakfast_summary_table',
                    'type' => 'table',
                    'dataSource' => 'rows',
                    'tableType' => 'dynamic',
                    'tableStyle' => 'grid',
                    'tableClassName' => 'breakfast-summary-table',
                    'style' => [
                        'width' => '100%',
                        'fontSize' => '9px',
                        'borderCollapse' => 'collapse',
                    ],
                    'groups' => [
                        [
                            'id' => 'expected_breakfast_date_group',
                            'field' => 'DateGroup',
                            'label' => 'Ngày : {{row.DateGroup}}',
                            'className' => 'date-group',
                            'enabledBy' => '',
                            'sort' => 'ASC',
                            'headerCells' => [
                                [
                                    'id' => 'date_group_cell',
                                    'type' => 'text',
                                    'content' => '<span style="color:#b82c2c;font-weight:bold;">Ngày :</span> <span style="color:#000000;font-weight:bold;">{{row.DateGroup}}</span>',
                                    'colspan' => 9,
                                    'align' => 'left',
                                    'style' => [
                                        'backgroundColor' => '#ffffff',
                                        'border' => '1px solid #cbd5e1',
                                        'padding' => '4px 6px',
                                        'fontWeight' => 'bold',
                                        'fontSize' => '9px',
                                    ],
                                ],
                            ],
                        ],
                        [
                            'id' => 'expected_breakfast_room_type_group',
                            'field' => 'RoomType',
                            'label' => '{{row.RoomType}}',
                            'className' => 'room-type-group',
                            'enabledBy' => '',
                            'sort' => 'ASC',
                            'headerCells' => [
                                [
                                    'id' => 'room_type_group_cell',
                                    'type' => 'text',
                                    'content' => '{{row.RoomType}}',
                                    'colspan' => 9,
                                    'align' => 'left',
                                    'style' => [
                                        'backgroundColor' => '#ffffff',
                                        'color' => '#1976d2',
                                        'border' => '1px solid #cbd5e1',
                                        'padding' => '4px 6px',
                                        'fontWeight' => 'bold',
                                        'fontSize' => '9px',
                                        'textTransform' => 'uppercase',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'columns' => [
                        ['header' => 'Mã ĐK', 'value' => 'row.BookingCode', 'width' => '8%', 'align' => 'center',
                         'headerStyle' => ['backgroundColor' => '#dee2ed', 'border' => '1px solid #cbd5e1', 'textAlign' => 'center', 'fontWeight' => 'bold', 'padding' => '4px 4px', 'fontSize' => '9px'],
                         'cellStyle' => ['border' => '1px solid #cbd5e1', 'textAlign' => 'center', 'padding' => '4px 4px', 'fontSize' => '9px']],
                        ['header' => 'Phòng', 'value' => 'row.Room', 'width' => '6%', 'align' => 'center',
                         'headerStyle' => ['backgroundColor' => '#dee2ed', 'border' => '1px solid #cbd5e1', 'textAlign' => 'center', 'fontWeight' => 'bold', 'padding' => '4px 4px', 'fontSize' => '9px'],
                         'cellStyle' => ['border' => '1px solid #cbd5e1', 'textAlign' => 'center', 'padding' => '4px 4px', 'fontSize' => '9px']],
                        ['header' => 'Người Lớn', 'value' => 'row.Adults', 'width' => '7%', 'align' => 'center', 'format' => 'number',
                         'headerStyle' => ['backgroundColor' => '#dee2ed', 'border' => '1px solid #cbd5e1', 'textAlign' => 'center', 'fontWeight' => 'bold', 'padding' => '4px 4px', 'fontSize' => '9px'],
                         'cellStyle' => ['border' => '1px solid #cbd5e1', 'textAlign' => 'center', 'padding' => '4px 4px', 'fontSize' => '9px']],
                        ['header' => 'Trẻ em', 'value' => 'row.Children', 'width' => '6%', 'align' => 'center', 'format' => 'number',
                         'headerStyle' => ['backgroundColor' => '#dee2ed', 'border' => '1px solid #cbd5e1', 'textAlign' => 'center', 'fontWeight' => 'bold', 'padding' => '4px 4px', 'fontSize' => '9px'],
                         'cellStyle' => ['border' => '1px solid #cbd5e1', 'textAlign' => 'center', 'padding' => '4px 4px', 'fontSize' => '9px']],
                        ['header' => 'Trẻ Em MP', 'value' => 'row.ChildrenNK', 'width' => '7%', 'align' => 'center', 'format' => 'number',
                         'headerStyle' => ['backgroundColor' => '#dee2ed', 'border' => '1px solid #cbd5e1', 'textAlign' => 'center', 'fontWeight' => 'bold', 'padding' => '4px 4px', 'fontSize' => '9px'],
                         'cellStyle' => ['border' => '1px solid #cbd5e1', 'textAlign' => 'center', 'padding' => '4px 4px', 'fontSize' => '9px']],
                        ['header' => 'Tổng', 'value' => 'row.TotalPax', 'width' => '6%', 'align' => 'center', 'format' => 'number',
                         'headerStyle' => ['backgroundColor' => '#dee2ed', 'border' => '1px solid #cbd5e1', 'textAlign' => 'center', 'fontWeight' => 'bold', 'padding' => '4px 4px', 'fontSize' => '9px'],
                         'cellStyle' => ['border' => '1px solid #cbd5e1', 'textAlign' => 'center', 'padding' => '4px 4px', 'fontSize' => '9px']],
                        ['header' => 'Tên Khách Chính', 'value' => 'row.GuestName', 'width' => '23%', 'align' => 'left',
                         'headerStyle' => ['backgroundColor' => '#dee2ed', 'border' => '1px solid #cbd5e1', 'textAlign' => 'center', 'fontWeight' => 'bold', 'padding' => '4px 4px', 'fontSize' => '9px'],
                         'cellStyle' => ['border' => '1px solid #cbd5e1', 'textAlign' => 'left', 'padding' => '4px 4px', 'fontSize' => '9px']],
                        ['header' => 'Công ty', 'value' => 'row.CompanyName', 'width' => '21%', 'align' => 'left',
                         'headerStyle' => ['backgroundColor' => '#dee2ed', 'border' => '1px solid #cbd5e1', 'textAlign' => 'center', 'fontWeight' => 'bold', 'padding' => '4px 4px', 'fontSize' => '9px'],
                         'cellStyle' => ['border' => '1px solid #cbd5e1', 'textAlign' => 'left', 'padding' => '4px 4px', 'fontSize' => '9px']],
                        ['header' => 'Ghi Chú', 'value' => 'row.Note', 'width' => '16%', 'align' => 'left',
                         'headerStyle' => ['backgroundColor' => '#dee2ed', 'border' => '1px solid #cbd5e1', 'textAlign' => 'center', 'fontWeight' => 'bold', 'padding' => '4px 4px', 'fontSize' => '9px'],
                         'cellStyle' => ['border' => '1px solid #cbd5e1', 'textAlign' => 'left', 'padding' => '4px 4px', 'fontSize' => '9px']],
                    ],
                    'customRows' => [
                        [
                            'id' => 'expected_breakfast_date_subtotal',
                            'enabledBy' => '',
                            'scope' => 'group',
                            'level' => 0,
                            'className' => 'date-subtotal-row',
                            'cells' => [
                                ['id' => 'date_subtotal_label', 'type' => 'text', 'content' => 'Tổng Ngày', 'colspan' => 1, 'align' => 'center',
                                 'style' => ['backgroundColor' => '#dee2ed', 'border' => '1px solid #cbd5e1', 'fontWeight' => 'bold', 'textAlign' => 'center', 'padding' => '4px 4px', 'fontSize' => '9px']],
                                ['id' => 'date_subtotal_rooms', 'type' => 'text', 'content' => '{{group.count}}', 'colspan' => 1, 'align' => 'center',
                                 'style' => ['backgroundColor' => '#dee2ed', 'border' => '1px solid #cbd5e1', 'fontWeight' => 'bold', 'textAlign' => 'center', 'padding' => '4px 4px', 'fontSize' => '9px']],
                                ['id' => 'date_subtotal_adults', 'type' => 'binding', 'binding' => 'row.DateTotalAdults', 'colspan' => 1, 'align' => 'center', 'format' => 'number',
                                 'style' => ['backgroundColor' => '#dee2ed', 'border' => '1px solid #cbd5e1', 'fontWeight' => 'bold', 'textAlign' => 'center', 'padding' => '4px 4px', 'fontSize' => '9px']],
                                ['id' => 'date_subtotal_children', 'type' => 'binding', 'binding' => 'row.DateTotalChildren', 'colspan' => 1, 'align' => 'center', 'format' => 'number',
                                 'style' => ['backgroundColor' => '#dee2ed', 'border' => '1px solid #cbd5e1', 'fontWeight' => 'bold', 'textAlign' => 'center', 'padding' => '4px 4px', 'fontSize' => '9px']],
                                ['id' => 'date_subtotal_children_nk', 'type' => 'binding', 'binding' => 'row.DateTotalChildrenNK', 'colspan' => 1, 'align' => 'center', 'format' => 'number',
                                 'style' => ['backgroundColor' => '#dee2ed', 'border' => '1px solid #cbd5e1', 'fontWeight' => 'bold', 'textAlign' => 'center', 'padding' => '4px 4px', 'fontSize' => '9px']],
                                ['id' => 'date_subtotal_total_pax', 'type' => 'binding', 'binding' => 'row.DateTotalPax', 'colspan' => 1, 'align' => 'center', 'format' => 'number',
                                 'style' => ['backgroundColor' => '#dee2ed', 'border' => '1px solid #cbd5e1', 'fontWeight' => 'bold', 'textAlign' => 'center', 'padding' => '4px 4px', 'fontSize' => '9px']],
                                ['id' => 'date_subtotal_spacer', 'type' => 'text', 'content' => '', 'colspan' => 3, 'align' => 'left',
                                 'style' => ['backgroundColor' => '#dee2ed', 'border' => '1px solid #cbd5e1', 'padding' => '4px 4px', 'fontSize' => '9px']],
                            ],
                        ],
                        [
                            'id' => 'expected_breakfast_total',
                            'enabledBy' => '',
                            'scope' => 'table',
                            'level' => 0,
                            'className' => 'report-total-row',
                            'cells' => [
                                ['id' => 'total_label', 'type' => 'text', 'content' => 'Tổng', 'colspan' => 1, 'align' => 'center',
                                 'style' => ['backgroundColor' => '#dee2ed', 'border' => '1px solid #cbd5e1', 'fontWeight' => 'bold', 'textAlign' => 'center', 'padding' => '4px 4px', 'fontSize' => '9px']],
                                ['id' => 'total_rooms', 'type' => 'text', 'content' => '{{aggregate.rows.count|number}}', 'colspan' => 1, 'align' => 'center',
                                 'style' => ['backgroundColor' => '#dee2ed', 'border' => '1px solid #cbd5e1', 'fontWeight' => 'bold', 'textAlign' => 'center', 'padding' => '4px 4px', 'fontSize' => '9px']],
                                ['id' => 'total_adults', 'type' => 'binding', 'binding' => 'totals.Adults', 'colspan' => 1, 'align' => 'center', 'format' => 'number',
                                 'style' => ['backgroundColor' => '#dee2ed', 'border' => '1px solid #cbd5e1', 'fontWeight' => 'bold', 'textAlign' => 'center', 'padding' => '4px 4px', 'fontSize' => '9px']],
                                ['id' => 'total_children', 'type' => 'binding', 'binding' => 'totals.Children', 'colspan' => 1, 'align' => 'center', 'format' => 'number',
                                 'style' => ['backgroundColor' => '#dee2ed', 'border' => '1px solid #cbd5e1', 'fontWeight' => 'bold', 'textAlign' => 'center', 'padding' => '4px 4px', 'fontSize' => '9px']],
                                ['id' => 'total_children_nk', 'type' => 'binding', 'binding' => 'totals.ChildrenNK', 'colspan' => 1, 'align' => 'center', 'format' => 'number',
                                 'style' => ['backgroundColor' => '#dee2ed', 'border' => '1px solid #cbd5e1', 'fontWeight' => 'bold', 'textAlign' => 'center', 'padding' => '4px 4px', 'fontSize' => '9px']],
                                ['id' => 'total_total_pax', 'type' => 'binding', 'binding' => 'totals.TotalPax', 'colspan' => 1, 'align' => 'center', 'format' => 'number',
                                 'style' => ['backgroundColor' => '#dee2ed', 'border' => '1px solid #cbd5e1', 'fontWeight' => 'bold', 'textAlign' => 'center', 'padding' => '4px 4px', 'fontSize' => '9px']],
                                ['id' => 'total_spacer', 'type' => 'text', 'content' => '', 'colspan' => 3, 'align' => 'left',
                                 'style' => ['backgroundColor' => '#dee2ed', 'border' => '1px solid #cbd5e1', 'padding' => '4px 4px', 'fontSize' => '9px']],
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'expected_breakfast_country_title',
                    'type' => 'text',
                    'style' => [
                        'textAlign' => 'center',
                        'fontWeight' => 'bold',
                        'fontSize' => '11px',
                        'marginTop' => '20px',
                        'marginBottom' => '8px',
                    ],
                    'content' => '<h2 class="country-summary-title" style="margin:0;font-size:11px;font-weight:bold;text-align:center;">THỐNG KÊ KHÁCH THEO QUỐC GIA</h2>',
                ],
                [
                    'id' => 'expected_breakfast_country_table',
                    'type' => 'table',
                    'dataSource' => 'country_summary',
                    'tableType' => 'dynamic',
                    'tableStyle' => 'grid',
                    'tableClassName' => 'country-summary-table',
                    'style' => [
                        'width' => '70%',
                        'marginLeft' => 'auto',
                        'marginRight' => 'auto',
                        'fontSize' => '9px',
                        'borderCollapse' => 'collapse',
                    ],
                    'columns' => [
                        ['header' => 'Quốc Gia', 'value' => 'item.Nationality', 'width' => '40%', 'align' => 'left',
                         'headerStyle' => ['backgroundColor' => '#dee2ed', 'border' => '1px solid #cbd5e1', 'textAlign' => 'center', 'fontWeight' => 'bold', 'padding' => '4px 4px', 'fontSize' => '9px'],
                         'cellStyle' => ['border' => '1px solid #cbd5e1', 'textAlign' => 'left', 'padding' => '4px 4px', 'fontSize' => '9px']],
                        ['header' => 'Số Lượng', 'value' => 'item.Quantity', 'width' => '30%', 'align' => 'center', 'format' => 'number',
                         'headerStyle' => ['backgroundColor' => '#dee2ed', 'border' => '1px solid #cbd5e1', 'textAlign' => 'center', 'fontWeight' => 'bold', 'padding' => '4px 4px', 'fontSize' => '9px'],
                         'cellStyle' => ['border' => '1px solid #cbd5e1', 'textAlign' => 'center', 'padding' => '4px 4px', 'fontSize' => '9px']],
                        ['header' => 'Tỉ lệ (%)', 'value' => 'item.Percentage', 'width' => '30%', 'align' => 'center',
                         'headerStyle' => ['backgroundColor' => '#dee2ed', 'border' => '1px solid #cbd5e1', 'textAlign' => 'center', 'fontWeight' => 'bold', 'padding' => '4px 4px', 'fontSize' => '9px'],
                         'cellStyle' => ['border' => '1px solid #cbd5e1', 'textAlign' => 'center', 'padding' => '4px 4px', 'fontSize' => '9px']],
                    ],
                    'customRows' => [
                        [
                            'id' => 'expected_breakfast_country_total',
                            'enabledBy' => '',
                            'scope' => 'table',
                            'level' => 0,
                            'className' => 'country-total-row',
                            'cells' => [
                                ['id' => 'country_total_label', 'type' => 'text', 'content' => 'Tổng', 'colspan' => 1, 'align' => 'center',
                                 'style' => ['backgroundColor' => '#dee2ed', 'border' => '1px solid #cbd5e1', 'fontWeight' => 'bold', 'textAlign' => 'center', 'padding' => '4px 4px', 'fontSize' => '9px']],
                                ['id' => 'country_total_quantity', 'type' => 'binding', 'binding' => 'totals.CountryTotalPax', 'colspan' => 1, 'align' => 'center', 'format' => 'number',
                                 'style' => ['backgroundColor' => '#dee2ed', 'border' => '1px solid #cbd5e1', 'fontWeight' => 'bold', 'textAlign' => 'center', 'padding' => '4px 4px', 'fontSize' => '9px']],
                                ['id' => 'country_total_percent', 'type' => 'text', 'content' => '100.00%', 'colspan' => 1, 'align' => 'center',
                                 'style' => ['backgroundColor' => '#dee2ed', 'border' => '1px solid #cbd5e1', 'fontWeight' => 'bold', 'textAlign' => 'center', 'padding' => '4px 4px', 'fontSize' => '9px']],
                            ],
                        ],
                    ],
                ],
            ],
            'footer' => [
                [
                    'id' => 'expected_breakfast_footer_signatures',
                    'type' => 'columns',
                    'className' => 'footer-signatures',
                    'style' => [
                        'marginTop' => '30px',
                        'fontSize' => '11px',
                    ],
                    'columns' => [
                        [
                            'id' => 'sig_fo',
                            'width' => '50%',
                            'blocks' => [
                                ['id' => 'sig_fo_title', 'type' => 'text', 'content' => '<p style="text-align: center; font-weight: bold; font-size: 11px; margin: 0;">Bộ Phận FO</p>'],
                            ],
                        ],
                        [
                            'id' => 'sig_fb',
                            'width' => '50%',
                            'blocks' => [
                                ['id' => 'sig_fb_title', 'type' => 'text', 'content' => '<p style="text-align: center; font-weight: bold; font-size: 11px; margin: 0;">Bộ Phận F&B</p>'],
                            ],
                        ],
                    ],
                ],
            ],

        ];
    }

    public function css(): string
    {
        return <<<'CSS'
body { color: #0f172a; font-family: Arial, Helvetica, sans-serif; font-size: 9px; max-width: 210mm; margin: 0 auto; }
.hotel-header { display: grid; grid-template-columns: 175px 1fr; align-items: center; min-height: 65px; }
.hotel-logo { display: flex; align-items: center; min-height: 55px; }
.hotel-logo img { max-width: 120px; max-height: 55px; object-fit: contain; }
.hotel-information { line-height: 1.8; }
.header-divider { margin: 6px 0 10px; border: 0; border-top: 1px solid #000000; }
h1 { margin: 0; text-align: center; font-size: 18px; font-weight: bold; color: #000000; }
.report-period { margin: 4px 0 12px; text-align: center; font-size: 11px; }
.breakfast-summary-table, .country-summary-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
.breakfast-summary-table th, .breakfast-summary-table td, .country-summary-table th, .country-summary-table td { border: 1px solid #cbd5e1; padding: 4px 4px; line-height: 1.2; vertical-align: middle; }
.breakfast-summary-table th, .country-summary-table th { background: #dee2ed; color: #0f172a; font-weight: bold; text-align: center; padding: 5px 4px; }
.breakfast-summary-table .date-group { background: #ffffff; color: #000000; font-weight: bold; text-align: left !important; padding: 4px 6px; }
.breakfast-summary-table .date-group .date-label { color: #b82c2c; }
.breakfast-summary-table .room-type-group { background: #ffffff; color: #1976d2; font-weight: bold; text-align: left !important; text-transform: uppercase; padding: 4px 6px; }
.date-subtotal-row td { background: #dee2ed; font-weight: bold; color: #0f172a; }
.report-total-row td { background: #dee2ed; font-weight: bold; color: #0f172a; border-top: 1.5px solid #94a3b8; }
.country-summary-title { margin: 26px 0 12px; text-align: center; font-size: 15px; font-weight: bold; color: #000000; }
.country-summary-table { width: 70%; margin: 0 auto; }
.country-total-row td { background: #dee2ed; font-weight: bold; color: #0f172a; }
.footer-signatures { margin-top: 35px; }
@media print {
  thead { display: table-header-group; }
  tr { break-inside: avoid; }
}
CSS;
    }

    public function render(array $data, ?TemplateRendererService $renderer = null): string
    {
        $renderer ??= new TemplateRendererService();
        $d = $this->definition();

        return $renderer->render(
            $this->html(),
            $this->css(),
            $this->prepareData($data),
            array_intersect_key($d, array_flip(['page_size', 'page_orientation', 'margin_top', 'margin_bottom', 'margin_left', 'margin_right']))
        );
    }

    public function prepareData(array $data): array
    {
        return $this->sanitize($data);
    }

    private function sanitize(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($item) => $this->sanitize($item), $value);
        }
        if (! is_string($value)) {
            return $value;
        }

        return str_replace(['{', '}'], ['&#123;', '&#125;'], htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    }
};
