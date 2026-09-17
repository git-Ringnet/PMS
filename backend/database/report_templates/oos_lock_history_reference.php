<?php

use App\Services\TemplateRendererService;
use Illuminate\Support\Facades\DB;

return new class
{
    public function apply(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $def = $this->definition();
        DB::table('templates')->where('report', 'OOS_LOCK_HISTORY_STANDARD')->update([
            'page_size' => $def['page_size'],
            'page_orientation' => $def['page_orientation'],
            'margin_top' => $def['margin_top'],
            'margin_right' => $def['margin_right'],
            'margin_bottom' => $def['margin_bottom'],
            'margin_left' => $def['margin_left'],
            'content_json' => json_encode($def['content_json'], JSON_UNESCAPED_UNICODE),
            'content_html' => $def['content_html'],
            'css' => $def['css'],
            'version' => '1.0',
            'updated_at' => now(),
        ]);
    }

    public function definition(): array
    {
        return [
            'report' => 'OOS_LOCK_HISTORY_STANDARD',
            'name' => 'Báo cáo lịch sử khóa phòng OOS',
            'page_size' => 'A4',
            'page_orientation' => 'portrait',
            'margin_top' => 8,
            'margin_right' => 8,
            'margin_bottom' => 8,
            'margin_left' => 8,
            'content_json' => $this->blocks(),
            'content_html' => $this->html(),
            'css' => $this->css(),
        ];
    }

    public function blocks(): array
    {
        return [
            'header' => [
                [
                    'id' => 'oos_hotel',
                    'type' => 'text',
                    'content' => '<div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 4px;"><div style="width: 35%;"><div class="hotel-logo" style="min-height: 50px;">{{hotel.logo}}</div></div><div style="width: 65%;"><div class="hotel-information" style="text-align: right; font-size: 10px; line-height: 1.5; color: #1e293b;"><div><b>Địa chỉ:</b> {{hotel.address}}</div><div><b>Nhân viên:</b> {{report.generated_by}} &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <b>Ngày:</b> {{report.generated_at}}</div></div></div></div>',
                    'style' => ['fontSize' => '10px', 'marginBottom' => '4px'],
                ],
                [
                    'id' => 'oos_divider',
                    'type' => 'divider',
                    'content' => '<hr class="header-divider" style="border: none; border-top: 1px solid #000; margin: 4px 0 8px 0;">',
                    'style' => ['marginTop' => '2px', 'marginBottom' => '6px'],
                ],
                [
                    'id' => 'oos_title',
                    'type' => 'text',
                    'content' => '<h1 style="text-align: center; font-size: 18px; font-weight: bold; margin: 4px 0 2px 0; text-transform: uppercase;">BÁO CÁO LỊCH SỬ KHÓA PHÒNG OOS</h1>',
                    'style' => ['fontSize' => '18px', 'textAlign' => 'center', 'fontWeight' => 'bold', 'marginBottom' => '2px'],
                ],
                [
                    'id' => 'oos_period',
                    'type' => 'text',
                    'content' => '<p class="report-period" style="text-align: center; font-size: 11px; margin: 2px 0 8px 0;"><b>Ngày</b> {{parameters.p_from_date}} &nbsp; ~ &nbsp; {{parameters.p_to_date}}</p>',
                    'style' => ['fontSize' => '11px', 'textAlign' => 'center', 'marginBottom' => '6px'],
                ],
            ],
            'detail' => [[
                'id' => 'oos_lock_history_table',
                'type' => 'table',
                'dataSource' => 'rows',
                'tableType' => 'dynamic',
                'tableStyle' => 'grid',
                'tableClassName' => 'oos-lock-table',
                'style' => ['width' => '100%', 'fontSize' => '11px'],
                'grouping' => [
                    [
                        'id' => 'oos_lock_group',
                        'field' => 'GroupName',
                        'label' => '{{row.GroupName}}',
                        'className' => 'group-title-row',
                        'sort' => 'ASC',
                    ],
                ],
                'columns' => $this->columns(),
                'customRows' => [
                    [
                        'id' => 'oos_group_total_row',
                        'enabledBy' => '',
                        'scope' => 'group',
                        'level' => 0,
                        'className' => 'pms-group-footer',
                        'cells' => [
                            ['id' => 'subtotal_label', 'type' => 'text', 'content' => 'Tổng', 'colspan' => 1, 'align' => 'center', 'style' => ['fontWeight' => 'bold']],
                            ['id' => 'subtotal_count', 'type' => 'binding', 'binding' => 'group.count', 'colspan' => 1, 'align' => 'center', 'format' => 'number', 'style' => ['fontWeight' => 'bold']],
                            ['id' => 'subtotal_c3', 'type' => 'text', 'content' => '', 'colspan' => 1],
                            ['id' => 'subtotal_c4', 'type' => 'text', 'content' => '', 'colspan' => 1],
                            ['id' => 'subtotal_c5', 'type' => 'text', 'content' => '', 'colspan' => 1],
                            ['id' => 'subtotal_c6', 'type' => 'text', 'content' => '', 'colspan' => 1],
                            ['id' => 'subtotal_c7', 'type' => 'text', 'content' => '', 'colspan' => 1],
                        ],
                    ],
                ],
            ]],
            'footer' => [],
        ];
    }

    public function columns(): array
    {
        return [
            ['header' => 'Phòng', 'value' => 'row.Room', 'width' => '8%', 'align' => 'center', 'cellStyle' => ['color' => '#2e7d32', 'fontWeight' => 'bold', 'textAlign' => 'center']],
            ['header' => 'Ngày Bắt Đầu', 'value' => 'row.DateBeginTime', 'width' => '17%', 'align' => 'center'],
            ['header' => 'Ngày Kết Thúc', 'value' => 'row.EndDateTime', 'width' => '17%', 'align' => 'center'],
            ['header' => 'Người Mở Khóa', 'value' => 'row.UserUnlock', 'width' => '12%', 'align' => 'center'],
            ['header' => 'Ngày Khóa', 'value' => 'row.LockDateTime', 'width' => '17%', 'align' => 'center'],
            ['header' => 'Người Khóa', 'value' => 'row.Username', 'width' => '12%', 'align' => 'center'],
            ['header' => 'Mô Tả', 'value' => 'row.Note', 'width' => '17%', 'align' => 'left', 'className' => 'note-cell'],
        ];
    }

    public function html(): string
    {
        $columns = $this->columns();
        $html = '<div class="report-header-band">'."\n";
        $html .= '<div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 4px;">'."\n";
        $html .= '  <div style="width: 35%;">'."\n";
        $html .= '    <div class="hotel-logo" style="min-height: 50px;">{{hotel.logo}}</div>'."\n";
        $html .= '  </div>'."\n";
        $html .= '  <div style="width: 65%;">'."\n";
        $html .= '    <div class="hotel-information" style="text-align: right; font-size: 10px; line-height: 1.5; color: #1e293b;"><div><b>Địa chỉ:</b> {{hotel.address}}</div><div><b>Nhân viên:</b> {{report.generated_by}} &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <b>Ngày:</b> {{report.generated_at}}</div></div>'."\n";
        $html .= '  </div>'."\n";
        $html .= '</div>'."\n";
        $html .= '<hr class="header-divider" style="border: none; border-top: 1px solid #000; margin: 4px 0 8px 0;">'."\n";
        $html .= '<h1 style="text-align: center; font-size: 18px; font-weight: bold; margin: 4px 0 2px 0; text-transform: uppercase;">BÁO CÁO LỊCH SỬ KHÓA PHÒNG OOS</h1>'."\n";
        $html .= '<p class="report-period" style="text-align: center; font-size: 11px; margin: 2px 0 8px 0;"><b>Ngày</b> {{parameters.p_from_date}} &nbsp; ~ &nbsp; {{parameters.p_to_date}}</p>'."\n";
        $html .= '</div>'."\n";

        $html .= '<div class="report-detail-band">'."\n";
        $html .= '<table class="oos-lock-table">'."\n";
        $html .= '  <colgroup>'."\n";
        foreach ($columns as $col) {
            $html .= '    <col style="width: '.$col['width'].'">'."\n";
        }
        $html .= '  </colgroup>'."\n";
        $html .= '  <thead>'."\n";
        $html .= '    <tr>'."\n";
        foreach ($columns as $col) {
            $html .= '      <th style="text-align: '.($col['align'] ?? 'center').';">'.$col['header'].'</th>'."\n";
        }
        $html .= '    </tr>'."\n";
        $html .= '  </thead>'."\n";

        $html .= '  <tbody class="pms-grouped-rows" data-source="rows" data-group-by="GroupName">'."\n";
        $html .= '    <tr class="pms-group-header" data-group-field="GroupName">'."\n";
        $html .= '      <td colspan="7" class="group-title-cell">{{row.GroupName}}</td>'."\n";
        $html .= '    </tr>'."\n";

        $html .= "    <tr class=\"pms-detail-row\">\n";
        foreach ($columns as $col) {
            $classAttr = ! empty($col['className']) ? ' class="'.$col['className'].'"' : '';
            $styleAttr = '';
            if ($col['value'] === 'row.Room') {
                $styleAttr = ' style="text-align: center; font-weight: bold; color: #2e7d32;"';
            } else {
                $styleAttr = ' style="text-align: '.($col['align'] ?? 'center').';"';
            }
            $html .= '      <td'.$classAttr.$styleAttr.'>{{'.$col['value'].'}}</td>'."\n";
        }
        $html .= "    </tr>\n";

        // Group footer row (subtotal)
        $html .= '    <tr class="pms-group-custom-row pms-group-footer" data-group-level="0">'."\n";
        $html .= '      <td style="font-weight: bold; text-align: center;">Tổng</td>'."\n";
        $html .= '      <td style="font-weight: bold; text-align: center;">{{group.count}}</td>'."\n";
        for ($i = 2; $i < count($columns); $i++) {
            $html .= '      <td></td>'."\n";
        }
        $html .= "    </tr>\n";
        $html .= "  </tbody>\n";
        $html .= "</table>\n";
        $html .= "</div>\n";

        return $html;
    }

    public function css(): string
    {
        return <<<'CSS'
.oos-lock-table {
    width: 100%;
    border-collapse: collapse;
    font-family: inherit;
    font-size: 11px;
    line-height: 1.35;
    margin-top: 4px;
}
.oos-lock-table th,
.oos-lock-table td {
    border: 1px solid #c8c8c8;
    padding: 5px 6px;
    vertical-align: middle;
}
.oos-lock-table thead th {
    background-color: #dee2ed;
    color: #0f172a;
    font-weight: bold;
    text-align: center;
    border: 1px solid #c8c8c8;
}
.oos-lock-table .group-title-cell {
    font-weight: bold;
    color: #000000;
    background-color: #ffffff;
    text-align: left;
    padding: 5px 8px;
}
.oos-lock-table .pms-group-header td {
    background-color: #ffffff;
    border: 1px solid #c8c8c8;
}
.oos-lock-table .pms-group-footer td,
.oos-lock-table .pms-group-custom-row td {
    background-color: #dee2ed;
    font-weight: bold;
    border: 1px solid #c8c8c8;
}
.oos-lock-table .note-cell {
    white-space: pre-wrap;
    line-height: 1.35;
}
.hotel-logo img {
    max-height: 50px;
    object-fit: contain;
}
CSS;
    }

    public function render(array $data, ?TemplateRendererService $renderer = null): string
    {
        $renderer ??= new TemplateRendererService();
        return $renderer->render($this->html(), $this->css(), $data, [
            'page_size' => 'A4',
            'page_orientation' => 'portrait',
            'margin_top' => 8,
            'margin_right' => 8,
            'margin_bottom' => 8,
            'margin_left' => 8,
        ]);
    }
};
