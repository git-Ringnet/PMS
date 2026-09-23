<?php

return new class
{
    public function definition(): array
    {
        $contentJson = $this->contentJson();

        return [
            'code' => 'WEEKLY_ROOM_REPORT',
            'report' => 'WEEKLY_ROOM_REPORT_REFERENCE',
            'name' => 'Báo cáo phòng hàng tuần',
            'page_size' => 'A4',
            'page_orientation' => 'portrait',
            'margin_top' => 10,
            'margin_right' => 12,
            'margin_bottom' => 10,
            'margin_left' => 12,
            'version' => '1.0',
            'content_json' => $contentJson,
            'content_html' => $this->html(),
            'css' => $this->css(),
        ];
    }

    public function render(array $data): string
    {
        return app(\App\Services\TemplateRendererService::class)->render(
            $this->html(),
            $this->css(),
            $data
        );
    }

    private function contentJson(): array
    {
        return [
            'version' => 2,
            'header' => [
                [
                    'id' => 'weekly_room_report_hotel',
                    'type' => 'text',
                    'content' => '<div class="weekly-room-report-header"><div class="hotel-logo">{{hotel.logo}}</div><div class="hotel-information"><div><b>Địa chỉ:</b> {{hotel.address}}</div><div><b>Nhân viên:</b> {{report.generated_by}}</div><div><b>Ngày in:</b> {{report.generated_at}}</div></div></div>',
                ],
                [
                    'id' => 'weekly_room_report_title',
                    'type' => 'text',
                    'content' => '<h1>BÁO CÁO PHÒNG HÀNG TUẦN</h1><p class="period"><b>Ngày:</b> {{parameters.p_from_date}} ~ {{parameters.p_to_date}}</p><p class="division"><b>Chi nhánh:</b> {{parameters.p_division_label}}</p>',
                ],
            ],
            'detail' => [[
                'id' => 'weekly_room_report_table',
                'type' => 'table',
                'dataSource' => 'rows',
                'tableType' => 'dynamic',
                'tableClassName' => 'weekly-room-report-table',
                'hasTwoTierHeader' => true,
                'headerRows' => [
                    [
                        ['content' => 'Ngày', 'rowspan' => 2, 'align' => 'center'],
                        ['content' => 'Thứ', 'rowspan' => 2, 'align' => 'center'],
                        ['content' => 'ĐẾN', 'colspan' => 2, 'align' => 'center'],
                        ['content' => 'ĐI', 'colspan' => 2, 'align' => 'center'],
                        ['content' => 'Ở', 'colspan' => 2, 'align' => 'center'],
                        ['content' => 'CÔNG SUẤT (%)', 'rowspan' => 2, 'align' => 'center'],
                    ],
                    [
                        ['content' => 'Phòng', 'align' => 'center'],
                        ['content' => 'Khách', 'align' => 'center'],
                        ['content' => 'Phòng', 'align' => 'center'],
                        ['content' => 'Khách', 'align' => 'center'],
                        ['content' => 'Phòng', 'align' => 'center'],
                        ['content' => 'Khách', 'align' => 'center'],
                    ],
                ],
                'columns' => $this->columns(),
                'customRows' => [[
                    'scope' => 'table',
                    'position' => 'footer',
                    'cells' => [
                        ['type' => 'text', 'content' => 'Tổng', 'align' => 'center'],
                        ['type' => 'text', 'content' => '7', 'align' => 'center'],
                        ['type' => 'binding', 'binding' => 'aggregate.rows.sum.arr_rooms', 'format' => 'number', 'align' => 'right'],
                        ['type' => 'binding', 'binding' => 'aggregate.rows.sum.arr_guests', 'format' => 'number', 'align' => 'right'],
                        ['type' => 'binding', 'binding' => 'aggregate.rows.sum.dep_rooms', 'format' => 'number', 'align' => 'right'],
                        ['type' => 'binding', 'binding' => 'aggregate.rows.sum.dep_guests', 'format' => 'number', 'align' => 'right'],
                        ['type' => 'binding', 'binding' => 'aggregate.rows.sum.occ_rooms', 'format' => 'number', 'align' => 'right'],
                        ['type' => 'binding', 'binding' => 'aggregate.rows.sum.occ_guests', 'format' => 'number', 'align' => 'right'],
                        ['type' => 'binding', 'binding' => 'parameters.p_weekly_occupancy_rate', 'align' => 'right', 'suffix' => '%'],
                    ],
                ]],
            ]],
            'footer' => [
                [
                    'id' => 'weekly_room_report_note',
                    'type' => 'text',
                    'content' => '<p class="note">Công suất tuần = Tổng phòng ở / Tổng phòng khả dụng trong 7 ngày.</p>',
                ],
                [
                    'id' => 'weekly_room_report_signatures',
                    'type' => 'text',
                    'content' => '<div class="signatures"><span>Người lập biểu</span><span>Trưởng bộ phận</span><span>Giám đốc khách sạn</span></div>',
                ],
            ],
        ];
    }

    private function columns(): array
    {
        return [
            ['id' => 'weekly_report_date', 'header' => 'Ngày', 'value' => 'row.report_date_display', 'width' => '14%', 'align' => 'center'],
            ['id' => 'weekly_day_name', 'header' => 'Thứ', 'value' => 'row.day_name', 'width' => '12%', 'align' => 'center'],
            ['id' => 'weekly_arr_rooms', 'header' => 'Phòng', 'value' => 'row.arr_rooms', 'width' => '10%', 'align' => 'right', 'format' => 'number'],
            ['id' => 'weekly_arr_guests', 'header' => 'Khách', 'value' => 'row.arr_guests', 'width' => '10%', 'align' => 'right', 'format' => 'number'],
            ['id' => 'weekly_dep_rooms', 'header' => 'Phòng', 'value' => 'row.dep_rooms', 'width' => '10%', 'align' => 'right', 'format' => 'number'],
            ['id' => 'weekly_dep_guests', 'header' => 'Khách', 'value' => 'row.dep_guests', 'width' => '10%', 'align' => 'right', 'format' => 'number'],
            ['id' => 'weekly_occ_rooms', 'header' => 'Phòng', 'value' => 'row.occ_rooms', 'width' => '10%', 'align' => 'right', 'format' => 'number'],
            ['id' => 'weekly_occ_guests', 'header' => 'Khách', 'value' => 'row.occ_guests', 'width' => '10%', 'align' => 'right', 'format' => 'number'],
            ['id' => 'weekly_occupancy_rate', 'header' => 'Công suất (%)', 'value' => 'row.occupancy_rate', 'width' => '14%', 'align' => 'right'],
        ];
    }

    private function html(): string
    {
        return <<<'HTML'
<div class="weekly-room-report-header"><div class="hotel-logo">{{hotel.logo}}</div><div class="hotel-information"><div><b>Địa chỉ:</b> {{hotel.address}}</div><div><b>Nhân viên:</b> {{report.generated_by}}</div><div><b>Ngày in:</b> {{report.generated_at}}</div></div></div>
<h1>BÁO CÁO PHÒNG HÀNG TUẦN</h1>
<p class="period"><b>Ngày:</b> {{parameters.p_from_date}} ~ {{parameters.p_to_date}}</p>
<p class="division"><b>Chi nhánh:</b> {{parameters.p_division_label}}</p>
<table class="weekly-room-report-table"><thead><tr><th rowspan="2">Ngày</th><th rowspan="2">Thứ</th><th colspan="2">ĐẾN</th><th colspan="2">ĐI</th><th colspan="2">Ở</th><th rowspan="2">CÔNG SUẤT (%)</th></tr><tr><th>Phòng</th><th>Khách</th><th>Phòng</th><th>Khách</th><th>Phòng</th><th>Khách</th></tr></thead>
<tbody><tr class="pms-detail-row" data-source="rows"><td class="date-cell">{{row.report_date_display}}</td><td>{{row.day_name}}</td><td class="number-cell">{{row.arr_rooms|number}}</td><td class="number-cell">{{row.arr_guests|number}}</td><td class="number-cell">{{row.dep_rooms|number}}</td><td class="number-cell">{{row.dep_guests|number}}</td><td class="number-cell">{{row.occ_rooms|number}}</td><td class="number-cell">{{row.occ_guests|number}}</td><td class="number-cell">{{row.occupancy_rate}}%</td></tr></tbody>
<tfoot><tr class="pms-custom-row"><td>Tổng</td><td>7</td><td>{{aggregate.rows.sum.arr_rooms|number}}</td><td>{{aggregate.rows.sum.arr_guests|number}}</td><td>{{aggregate.rows.sum.dep_rooms|number}}</td><td>{{aggregate.rows.sum.dep_guests|number}}</td><td>{{aggregate.rows.sum.occ_rooms|number}}</td><td>{{aggregate.rows.sum.occ_guests|number}}</td><td>{{parameters.p_weekly_occupancy_rate}}%</td></tr></tfoot></table>
<p class="note">Công suất tuần = Tổng phòng ở / Tổng phòng khả dụng trong 7 ngày.</p>
<div class="signatures"><span>Người lập biểu</span><span>Trưởng bộ phận</span><span>Giám đốc khách sạn</span></div>
HTML;
    }

    private function css(): string
    {
        return <<<'CSS'
body { color: #111827; font-family: "Times New Roman", serif; font-size: 12px; }
.weekly-room-report-header { display: flex; justify-content: space-between; align-items: center; min-height: 58px; }
.hotel-logo { display: flex; align-items: center; min-height: 50px; }
.hotel-logo img { max-width: 125px; max-height: 55px; object-fit: contain; }
.hotel-information { text-align: right; font-size: 10px; line-height: 1.45; }
h1 { margin: 12px 0 4px; text-align: center; font-size: 18px; letter-spacing: .3px; }
.period, .division { margin: 3px 0; text-align: center; font-size: 11px; }
.weekly-room-report-table { width: 100%; border-collapse: collapse; table-layout: fixed; margin-top: 10px; font-size: 10px; }
.weekly-room-report-table th, .weekly-room-report-table td { border: 1px solid #9ca3af; padding: 5px 3px; line-height: 1.2; vertical-align: middle; }
.weekly-room-report-table th { background: #d9deea; text-align: center; font-weight: bold; }
.weekly-room-report-table td { text-align: center; }
.weekly-room-report-table td.number-cell { text-align: right; }
.weekly-room-report-table td.date-cell { color: #16a34a; font-weight: 600; text-decoration: underline; }
.weekly-room-report-table tfoot td { background: #f3f4f6; border-top: 2px solid #374151; font-weight: bold; text-align: right; }
.weekly-room-report-table tfoot td:first-child, .weekly-room-report-table tfoot td:nth-child(2) { text-align: center; }
.note { margin-top: 9px; font-size: 9px; }
.signatures { display: flex; justify-content: space-around; margin-top: 28px; font-size: 10px; }
@media print { thead { display: table-header-group; } tr { break-inside: avoid; } }
CSS;
    }
};
