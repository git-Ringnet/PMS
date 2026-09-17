<?php

use App\Services\TemplateRendererService;

/**
 * Pure reference provider for the Expected Breakfast Detail Report (legacy sp_032).
 */
return new class
{
    public function definition(): array
    {
        return [
            'report' => 'EXPECTED_BREAKFAST_2_REFERENCE',
            'name' => 'Báo cáo dự kiến khách ăn sáng - Chi tiết',
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
<table class="breakfast-detail-table">
  <colgroup>
    <col style="width:8%">
    <col style="width:30%">
    <col style="width:24%">
    <col style="width:12%">
    <col style="width:12%">
    <col style="width:14%">
  </colgroup>
  <thead>
    <tr>
      <th>Phòng</th>
      <th>Tên Khách</th>
      <th>Quốc Gia</th>
      <th>Ngày Đến</th>
      <th>Ngày Đi</th>
      <th>Ghi Chú</th>
    </tr>
  </thead>
  <tbody class="pms-grouped-rows" data-source="rows" data-group-by="DateGroup" data-subgroup-by="RoomType" data-subsubgroup-by="DetailRoom">
    <tr class="pms-group-header date-group-row">
      <td colspan="6" class="date-header-cell"><span class="date-label">Ngày :</span> {{row.DateGroup}}</td>
    </tr>
    <tr class="pms-subgroup-header room-type-row">
      <td colspan="6" class="room-type-cell">{{row.RoomType}}</td>
    </tr>
    <tr class="pms-subsubgroup-header detail-room-row">
      <td colspan="6" class="detail-room-cell">{{row.DetailRoom}}</td>
    </tr>
    <tr class="pms-detail-row">
      <td style="text-align:center;">{{row.Room}}</td>
      <td style="text-align:left;">{{row.GuestName}}</td>
      <td style="text-align:left;">{{row.Nationality}}</td>
      <td style="text-align:center;">{{row.ArrivalDate}}</td>
      <td style="text-align:center;">{{row.DepartureDate}}</td>
      <td style="text-align:left;">{{row.Note}}</td>
    </tr>
  </tbody>
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
            'expected_breakfast_detail',
            '<h1>BÁO CÁO DỰ KIẾN KHÁCH ĂN SÁNG</h1>',
            '<p class="report-period"><b>Ngày :</b> {{parameters.p_from_date}} &nbsp; ~ &nbsp; {{parameters.p_to_date}}</p>'
        );

        return [
            'header' => $header,
            'detail' => [
                [
                    'id' => 'expected_breakfast_detail_table',
                    'type' => 'table',
                    'dataSource' => 'rows',
                    'tableType' => 'dynamic',
                    'tableStyle' => 'grid',
                    'tableClassName' => 'breakfast-detail-table',
                    'style' => [
                        'width' => '100%',
                        'fontSize' => '9px',
                        'borderCollapse' => 'collapse',
                    ],
                    'columns' => [
                        ['header' => 'Phòng', 'value' => 'row.Room', 'width' => '8%', 'align' => 'center',
                         'headerStyle' => ['backgroundColor' => '#dee2ed', 'border' => '1px solid #cbd5e1', 'textAlign' => 'center', 'fontWeight' => 'bold', 'padding' => '4px 4px', 'fontSize' => '9px'],
                         'cellStyle' => ['border' => '1px solid #cbd5e1', 'textAlign' => 'center', 'padding' => '4px 4px', 'fontSize' => '9px']],
                        ['header' => 'Tên Khách', 'value' => 'row.GuestName', 'width' => '30%', 'align' => 'left',
                         'headerStyle' => ['backgroundColor' => '#dee2ed', 'border' => '1px solid #cbd5e1', 'textAlign' => 'center', 'fontWeight' => 'bold', 'padding' => '4px 4px', 'fontSize' => '9px'],
                         'cellStyle' => ['border' => '1px solid #cbd5e1', 'textAlign' => 'left', 'padding' => '4px 4px', 'fontSize' => '9px']],
                        ['header' => 'Quốc Gia', 'value' => 'row.Nationality', 'width' => '24%', 'align' => 'left',
                         'headerStyle' => ['backgroundColor' => '#dee2ed', 'border' => '1px solid #cbd5e1', 'textAlign' => 'center', 'fontWeight' => 'bold', 'padding' => '4px 4px', 'fontSize' => '9px'],
                         'cellStyle' => ['border' => '1px solid #cbd5e1', 'textAlign' => 'left', 'padding' => '4px 4px', 'fontSize' => '9px']],
                        ['header' => 'Ngày Đến', 'value' => 'row.ArrivalDate', 'width' => '12%', 'align' => 'center',
                         'headerStyle' => ['backgroundColor' => '#dee2ed', 'border' => '1px solid #cbd5e1', 'textAlign' => 'center', 'fontWeight' => 'bold', 'padding' => '4px 4px', 'fontSize' => '9px'],
                         'cellStyle' => ['border' => '1px solid #cbd5e1', 'textAlign' => 'center', 'padding' => '4px 4px', 'fontSize' => '9px']],
                        ['header' => 'Ngày Đi', 'value' => 'row.DepartureDate', 'width' => '12%', 'align' => 'center',
                         'headerStyle' => ['backgroundColor' => '#dee2ed', 'border' => '1px solid #cbd5e1', 'textAlign' => 'center', 'fontWeight' => 'bold', 'padding' => '4px 4px', 'fontSize' => '9px'],
                         'cellStyle' => ['border' => '1px solid #cbd5e1', 'textAlign' => 'center', 'padding' => '4px 4px', 'fontSize' => '9px']],
                        ['header' => 'Ghi Chú', 'value' => 'row.Note', 'width' => '14%', 'align' => 'left',
                         'headerStyle' => ['backgroundColor' => '#dee2ed', 'border' => '1px solid #cbd5e1', 'textAlign' => 'center', 'fontWeight' => 'bold', 'padding' => '4px 4px', 'fontSize' => '9px'],
                         'cellStyle' => ['border' => '1px solid #cbd5e1', 'textAlign' => 'left', 'padding' => '4px 4px', 'fontSize' => '9px']],
                    ],
                    'groups' => [
                        [
                            'id' => 'expected_breakfast_detail_date_group',
                            'field' => 'DateGroup',
                            'label' => 'Ngày : {{row.DateGroup}}',
                            'className' => 'date-group-row',
                            'enabledBy' => '',
                            'sort' => 'ASC',
                            'headerCells' => [
                                [
                                    'id' => 'detail_date_group_cell',
                                    'type' => 'text',
                                    'content' => '<span style="color:#b82c2c;font-weight:bold;">Ngày :</span> <span style="color:#000000;font-weight:bold;">{{row.DateGroup}}</span>',
                                    'colspan' => 6,
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
                                    'id' => 'detail_room_type_group_cell',
                                    'type' => 'text',
                                    'content' => '{{row.RoomType}}',
                                    'colspan' => 6,
                                    'align' => 'left',
                                    'style' => [
                                        'backgroundColor' => '#ffffff',
                                        'color' => '#1976d2',
                                        'border' => '1px solid #cbd5e1',
                                        'padding' => '4px 6px',
                                        'fontWeight' => 'bold',
                                        'textTransform' => 'uppercase',
                                        'fontSize' => '9px',
                                    ],
                                ],
                            ],
                        ],
                        [
                            'id' => 'expected_breakfast_detail_room_group',
                            'field' => 'DetailRoom',
                            'label' => '{{row.DetailRoom}}',
                            'className' => 'detail-room-group',
                            'enabledBy' => '',
                            'sort' => 'ASC',
                            'headerCells' => [
                                [
                                    'id' => 'detail_room_cell',
                                    'type' => 'text',
                                    'content' => '{{row.DetailRoom}}',
                                    'colspan' => 6,
                                    'align' => 'left',
                                    'style' => [
                                        'backgroundColor' => '#ffffff',
                                        'color' => '#1976d2',
                                        'border' => '1px solid #cbd5e1',
                                        'padding' => '4px 6px',
                                        'fontWeight' => 'bold',
                                        'fontSize' => '9px',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'footer' => [
                [
                    'id' => 'expected_breakfast_detail_footer_signatures',
                    'type' => 'columns',
                    'className' => 'footer-signatures',
                    'style' => [
                        'marginTop' => '35px',
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
.breakfast-detail-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
.breakfast-detail-table th, .breakfast-detail-table td { border: 1px solid #cbd5e1; padding: 4px 5px; line-height: 1.25; vertical-align: middle; }
.breakfast-detail-table th { background: #dee2ed; color: #0f172a; font-weight: bold; text-align: center; padding: 5px 4px; }
.date-header-cell { background: #ffffff; color: #000000; font-weight: bold; text-align: left !important; padding: 4px 6px; }
.date-header-cell .date-label { color: #b82c2c; }
.room-type-cell { background: #ffffff; color: #1976d2; font-weight: bold; font-size: 9.5px; text-align: left !important; text-transform: uppercase; padding: 4px 6px; }
.detail-room-cell { background: #ffffff; color: #1976d2; font-weight: bold; font-size: 9.5px; text-align: left !important; padding: 4px 6px; }
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
