<?php

use App\Services\TemplateRendererService;

/**
 * Reference template provider for ROOM_SPECIAL_REQUESTS (sp_297 legacy reference).
 * Configured with full Designer blocks (content_json), HTML, and CSS.
 */
return new class
{
    public function definition(): array
    {
        return [
            'code' => 'ROOM_SPECIAL_REQUESTS',
            'name' => 'Báo cáo yêu cầu đặc biệt',
            'report' => 'ROOM_SPECIAL_REQUESTS_REFERENCE',
            'page_size' => 'A4',
            'page_orientation' => 'portrait',
            'margin_top' => 6,
            'margin_right' => 5,
            'margin_bottom' => 6,
            'margin_left' => 5,
            'version' => '1.0',
            'content_html' => $this->html(),
            'content_json' => $this->blocks(),
            'css' => $this->css(),
            'columns' => $this->columns(),
            'data_contract' => [
                'rows' => [
                    'BookingId' => 'string',
                    'BookingNote' => 'string',
                    'Room' => 'string',
                    'RoomType' => 'string',
                    'GuestName' => 'string',
                    'ArrivalDate' => 'string',
                    'DepartureDate' => 'string',
                    'Adults' => 'number',
                    'Children' => 'number',
                    'AdultsDisplay' => 'string',
                    'ChildrenDisplay' => 'string',
                    'SpecialRequests' => 'string',
                ],
                'parameters' => [
                    'p_from_date' => 'string',
                    'p_to_date' => 'string',
                    'p_date_type' => 'int',
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
                'id' => 'room_special_requests_header_band',
                'type' => 'columns',
                'style' => [
                    'display' => 'flex',
                    'justifyContent' => 'space-between',
                    'alignItems' => 'flex-start',
                    'marginBottom' => '6px',
                ],
                'columns' => [
                    [
                        'width' => '30%',
                        'blocks' => [[
                            'id' => 'room_special_requests_logo',
                            'type' => 'text',
                            'content' => '<div class="hotel-logo" style="min-height: 58px;">{{hotel.logo}}</div>',
                            'style' => ['fontSize' => '13px'],
                        ]],
                    ],
                    [
                        'width' => '70%',
                        'blocks' => [[
                            'id' => 'room_special_requests_hotel_info',
                            'type' => 'text',
                            'content' => '<div class="hotel-information" style="text-align: right; font-size: 11px; line-height: 1.4;">'
                                .'<div><b>Địa chỉ:</b> {{hotel.address}}</div>'
                                .'<div><b>Nhân viên:</b> {{report.generated_by}} &nbsp;&nbsp; <b>Ngày:</b> {{report.generated_at}}</div>'
                                .'</div>',
                            'style' => ['textAlign' => 'right'],
                        ]],
                    ],
                ],
            ],
            [
                'id' => 'room_special_requests_divider',
                'type' => 'divider',
                'content' => '<hr class="header-divider" style="border: none; border-top: 1px solid #000; margin: 4px 0 10px 0;">',
                'style' => ['marginTop' => '2px', 'marginBottom' => '8px'],
            ],
            [
                'id' => 'room_special_requests_title',
                'type' => 'text',
                'content' => '<h1 style="text-align: center; font-size: 18px; font-weight: bold; margin: 6px 0 4px 0; text-transform: uppercase;">BÁO CÁO YÊU CẦU ĐẶC BIỆT</h1>',
                'style' => ['textAlign' => 'center', 'fontWeight' => 'bold'],
            ],
            [
                'id' => 'room_special_requests_period',
                'type' => 'text',
                'content' => '<p class="period" style="text-align: center; font-size: 11px; margin: 2px 0 12px 0;"><b>Ngày:</b> {{parameters.p_from_date}} &nbsp; ~ &nbsp; {{parameters.p_to_date}}</p>',
                'style' => ['textAlign' => 'center', 'fontSize' => '11px', 'marginBottom' => '10px'],
            ],
        ];

        return [
            'header' => $header,
            'detail' => [[
                'id' => 'room_special_requests_table',
                'type' => 'table',
                'dataSource' => 'rows',
                'tableType' => 'dynamic',
                'tableStyle' => 'grid',
                'tableClassName' => 'room-special-requests-table',
                'style' => ['width' => '100%', 'fontSize' => '11px'],
                'columns' => $this->columns(),
                'customRows' => [
                    [
                        'id' => 'room_special_requests_booking_row',
                        'enabledBy' => '',
                        'scope' => 'detail',
                        'level' => 0,
                        'className' => 'booking-registration-row',
                        'cells' => [
                            ['id' => 'booking_label', 'type' => 'text', 'content' => 'Đăng Ký:', 'colspan' => 1, 'align' => 'left', 'style' => ['fontWeight' => 'bold', 'padding' => '5px 6px']],
                            ['id' => 'booking_value', 'type' => 'binding', 'binding' => 'row.BookingId', 'colspan' => 8, 'align' => 'left', 'style' => ['fontWeight' => 'bold', 'padding' => '5px 6px']],
                        ],
                    ],
                    [
                        'id' => 'room_special_requests_note_row',
                        'enabledBy' => '',
                        'scope' => 'detail',
                        'level' => 0,
                        'className' => 'booking-note-row',
                        'cells' => [
                            ['id' => 'note_label', 'type' => 'text', 'content' => 'Ghi Chú:', 'colspan' => 1, 'align' => 'left', 'style' => ['fontWeight' => 'bold', 'padding' => '5px 6px']],
                            ['id' => 'note_value', 'type' => 'binding', 'binding' => 'row.BookingNote', 'colspan' => 8, 'align' => 'left', 'style' => ['fontWeight' => 'normal', 'padding' => '5px 6px', 'whiteSpace' => 'pre-wrap']],
                        ],
                    ],
                    [
                        'id' => 'room_special_requests_total_row',
                        'enabledBy' => '',
                        'scope' => 'table',
                        'level' => 0,
                        'className' => 'report-total-row',
                        'cells' => [
                            ['id' => 'total_label', 'type' => 'text', 'content' => 'Tổng', 'colspan' => 1, 'align' => 'center', 'style' => ['fontWeight' => 'bold', 'padding' => '5px 6px']],
                            ['id' => 'total_count', 'type' => 'binding', 'binding' => 'aggregate.rows.count', 'colspan' => 1, 'align' => 'center', 'format' => 'number', 'style' => ['fontWeight' => 'bold', 'padding' => '5px 6px']],
                            ['id' => 'total_spacer', 'type' => 'text', 'content' => '', 'colspan' => 7, 'align' => 'left', 'style' => ['padding' => '5px 6px']],
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
            ['header' => 'Mã ĐK', 'value' => 'row.BookingId', 'width' => '8%', 'align' => 'center', 'style' => ['color' => '#16a34a', 'fontWeight' => 'bold']],
            ['header' => 'Phòng', 'value' => 'row.Room', 'width' => '7%', 'align' => 'center'],
            ['header' => 'Loại Phòng', 'value' => 'row.RoomType', 'width' => '14%', 'align' => 'left'],
            ['header' => 'Tên Khách', 'value' => 'row.GuestName', 'width' => '13%', 'align' => 'left'],
            ['header' => 'Ngày Đến', 'value' => 'row.ArrivalDate', 'width' => '10%', 'align' => 'center'],
            ['header' => 'Ngày Đi', 'value' => 'row.DepartureDate', 'width' => '10%', 'align' => 'center'],
            ['header' => 'N.Lớn', 'value' => 'row.AdultsDisplay', 'width' => '6%', 'align' => 'center'],
            ['header' => 'T.Em', 'value' => 'row.ChildrenDisplay', 'width' => '6%', 'align' => 'center'],
            ['header' => 'Yêu Cầu Đặc Biệt', 'value' => 'row.SpecialRequests', 'width' => '26%', 'align' => 'left', 'style' => ['fontWeight' => '500', 'color' => '#0f172a']],
        ];
    }

    public function css(): string
    {
        return <<<'CSS'
.room-special-requests-table {
    width: 100%;
    border-collapse: collapse;
    font-family: inherit;
    font-size: 11px;
    line-height: 1.3;
    margin-top: 4px;
}
.room-special-requests-table th,
.room-special-requests-table td {
    border: 1px solid #cbd5e1;
    padding: 4px 6px;
    vertical-align: middle;
}
.room-special-requests-table thead th {
    background-color: #f1f5f9;
    color: #0f172a;
    font-weight: bold;
    text-align: center;
    border-bottom: 2px solid #94a3b8;
}
.room-special-requests-table .booking-header-cell {
    background-color: #f8fafc;
    border-top: 1px solid #94a3b8;
    border-bottom: 1px solid #cbd5e1;
}
.room-special-requests-table .report-total-row td {
    background-color: #f1f5f9;
    font-weight: bold;
    border-top: 2px solid #94a3b8;
}
.room-special-requests-table .booking-note-row td:last-child {
    white-space: pre-wrap;
}
.hotel-logo img {
    max-height: 52px;
    object-fit: contain;
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
            $html = '<div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px;">'."\n";
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

    private function compileTable(array $block): string
    {
        $columns = $block['columns'] ?? [];
        $className = $block['tableClassName'] ?? 'report-table';

        $html = '<table class="'.$className.'">'."\n";
        $html .= '  <colgroup>'."\n";
        foreach ($columns as $col) {
            $html .= '    <col style="width: '.($col['width'] ?? 'auto').'">'."\n";
        }
        $html .= '  </colgroup>'."\n";

        $html .= '  <thead>'."\n    <tr>\n";
        foreach ($columns as $col) {
            $html .= '      <th style="text-align: '.($col['align'] ?? 'center').';">'.($col['header'] ?? '').'</th>'."\n";
        }
        $html .= "    </tr>\n  </thead>\n";

        $groups = $block['grouping'] ?? [];
        $primaryGroup = $groups[0] ?? null;
        $groupByAttr = $primaryGroup ? ' data-group-by="'.htmlspecialchars((string) $primaryGroup['field'], ENT_QUOTES, 'UTF-8').'"' : '';

        $configuredAttr = $primaryGroup ? '' : ' data-group-configured="1"';
        $html .= '  <tbody class="pms-grouped-rows" data-source="'.($block['dataSource'] ?? 'rows').'"'.$configuredAttr.$groupByAttr.'>'."\n";

        if ($primaryGroup) {
            $html .= '    <tr class="pms-group-header" data-group-field="'.htmlspecialchars((string) $primaryGroup['field'], ENT_QUOTES, 'UTF-8').'">'."\n";
            if (! empty($primaryGroup['headerCells'])) {
                foreach ($primaryGroup['headerCells'] as $cell) {
                    $html .= '      <td colspan="'.($cell['colspan'] ?? count($columns)).'" class="'.($cell['className'] ?? '').'">'
                        .($cell['content'] ?? '').'</td>'."\n";
                }
            } else {
                $html .= '      <td colspan="'.count($columns).'">'.($primaryGroup['label'] ?? '').'</td>'."\n";
            }
            $html .= "    </tr>\n";
        }

        $html .= "    <tr class=\"pms-detail-row\">\n";
        foreach ($columns as $col) {
            $format = ($col['format'] ?? '') === 'number' ? '|number' : '';
            $styleAttr = ! empty($col['style']) ? ' style="'.$this->formatInlineStyle($col['style'], $col['align'] ?? 'left').'"' : ' style="text-align: '.($col['align'] ?? 'left').';"';
            $html .= '      <td'.$styleAttr.'>{{'.($col['value'] ?? '').$format.'}}</td>'."\n";
        }
        $html .= "    </tr>\n";

        $compileCustomRow = function (array $crow, string $rowClass): string {
            $rowHtml = '    <tr class="'.$rowClass.' '.($crow['className'] ?? '').'">'."\n";
            foreach ($crow['cells'] ?? [] as $cell) {
                $colspan = isset($cell['colspan']) ? ' colspan="'.$cell['colspan'].'"' : '';
                $align = $cell['align'] ?? 'left';
                $val = ! empty($cell['binding']) ? '{{'.$cell['binding'].(! empty($cell['format']) ? '|'.$cell['format'] : '').'}}' : ($cell['content'] ?? '');
                $style = ! empty($cell['style']) ? $this->formatInlineStyle($cell['style'], $align) : 'text-align: '.$align.';';
                $rowHtml .= '      <td'.$colspan.' style="'.$style.'">'.$val.'</td>'."\n";
            }
            return $rowHtml."    </tr>\n";
        };

        $customRows = $block['customRows'] ?? [];
        foreach (array_filter($customRows, fn (array $row): bool => ($row['scope'] ?? 'table') === 'detail') as $customRow) {
            $html .= $compileCustomRow($customRow, 'pms-detail-custom-row');
        }

        $html .= "  </tbody>\n";

        $tableCustomRows = array_filter($customRows, fn (array $row): bool => ($row['scope'] ?? 'table') === 'table');
        if (! empty($tableCustomRows)) {
            $html .= "  <tfoot>\n";
            foreach ($tableCustomRows as $customRow) {
                $html .= $compileCustomRow($customRow, 'pms-custom-row');
            }
            $html .= "  </tfoot>\n";
        }

        $html .= "</table>\n";
        return $html;
    }

    private function formatInlineStyle(array $style, string $defaultAlign): string
    {
        $css = [];
        $css[] = 'text-align: '.($style['textAlign'] ?? $defaultAlign);
        if (! empty($style['color'])) {
            $css[] = 'color: '.$style['color'];
        }
        if (! empty($style['fontWeight'])) {
            $css[] = 'font-weight: '.$style['fontWeight'];
        }
        if (! empty($style['backgroundColor'])) {
            $css[] = 'background-color: '.$style['backgroundColor'];
        }
        if (! empty($style['whiteSpace'])) {
            $css[] = 'white-space: '.$style['whiteSpace'];
        }
        return implode('; ', $css).';';
    }
};
