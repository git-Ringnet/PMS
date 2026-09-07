<?php

use Illuminate\Support\Facades\DB;

return new class
{
    public function apply(): void
    {
        DB::table('templates')->where('report', 'CHECKED_OUT_GUESTS_STANDARD')->update([
            'content_json' => json_encode($this->blocks(), JSON_UNESCAPED_UNICODE),
            'content_html' => $this->html(),
            'css' => $this->css(),
            'updated_at' => now(),
        ]);
    }

    private function blocks(): array
    {
        return [
            'header' => [
                [
                    'id' => 'checked_out_hotel_header',
                    'type' => 'columns',
                    'style' => ['marginBottom' => '8px', 'whiteSpace' => 'normal'],
                    'columns' => [
                        [
                            'width' => '38%',
                            'blocks' => [[
                                'id' => 'checked_out_logo',
                                'type' => 'image',
                                'content' => 'hotel.logo',
                                'imageUrl' => '',
                                'style' => ['textAlign' => 'left', 'paddingTop' => '4px', 'paddingBottom' => '4px', 'whiteSpace' => 'normal'],
                            ]],
                        ],
                        [
                            'width' => '62%',
                            'blocks' => [[
                                'id' => 'checked_out_hotel_meta',
                                'type' => 'text',
                                'content' => '<div><b>Địa chỉ:</b>&nbsp;&nbsp;&nbsp; {{hotel.address}}</div><div><b>Nhân viên:</b>&nbsp;&nbsp; {{report.generated_by}} <b style="float:right">Ngày:&nbsp;&nbsp; {{report.generated_at}}</b></div>',
                                'style' => ['fontSize' => '9px', 'lineHeight' => '1.8', 'paddingTop' => '8px', 'whiteSpace' => 'normal'],
                            ]],
                        ],
                    ],
                ],
                [
                    'id' => 'checked_out_divider',
                    'type' => 'divider',
                    'content' => '<hr class="header-divider">',
                    'style' => ['marginBottom' => '34px', 'whiteSpace' => 'normal'],
                ],
                [
                    'id' => 'checked_out_title',
                    'type' => 'text',
                    'content' => '<h1>BÁO CÁO DANH SÁCH KHÁCH ĐÃ TRẢ PHÒNG</h1>',
                    'style' => ['textAlign' => 'center', 'fontSize' => '20px', 'fontWeight' => 'bold', 'marginBottom' => '30px', 'whiteSpace' => 'normal'],
                ],
                [
                    'id' => 'checked_out_period',
                    'type' => 'text',
                    'content' => '<p><b>Ngày:</b>&nbsp;&nbsp; {{parameters.p_from_date}} &nbsp;&nbsp; ~ &nbsp;&nbsp; {{parameters.p_to_date}}</p>',
                    'style' => ['textAlign' => 'center', 'fontSize' => '9px', 'fontWeight' => 'bold', 'marginBottom' => '18px', 'whiteSpace' => 'normal'],
                ],
            ],
            'detail' => [[
                'id' => 'checked_out_rows',
                'type' => 'table',
                'dataSource' => 'rows',
                'tableType' => 'dynamic',
                'tableStyle' => 'grid',
                'groups' => [[
                    'id' => 'checked_out_booking_group',
                    'field' => 'BookingId',
                    'label' => 'Tên Nhóm: {{row.BookingName}}',
                    'className' => 'booking-group',
                    'sort' => 'ASC',
                ]],
                'groupBy' => 'BookingId',
                'style' => ['whiteSpace' => 'normal'],
                'columns' => $this->columns(),
                'customRows' => [
                    [
                        'id' => 'checked_out_group_note',
                        'scope' => 'detail',
                        'level' => 0,
                        'enabledBy' => 'parameters.p_show_note',
                        'className' => 'booking-note',
                        'cells' => [[
                            'id' => 'checked_out_group_note_value',
                            'type' => 'text',
                            'content' => '<b>Ghi Chú:</b> {{row.NoteMoi}}',
                            'colspan' => 13,
                            'align' => 'left',
                            'format' => '',
                            'className' => 'note-content',
                        ]],
                    ],
                    [
                        'id' => 'checked_out_group_total',
                        'scope' => 'group',
                        'level' => 0,
                        'enabledBy' => '',
                        'className' => 'booking-total',
                        'cells' => [
                            ['id' => 'checked_out_total_label', 'type' => 'text', 'content' => 'Tổng cộng', 'colspan' => 2, 'align' => 'left', 'format' => '', 'className' => 'total-label'],
                            ['id' => 'checked_out_room_total', 'type' => 'text', 'content' => '{{group.distinct.RentalRoomId}}', 'colspan' => 1, 'align' => 'center', 'format' => '', 'className' => 'total-value'],
                            ['id' => 'checked_out_guest_label', 'type' => 'text', 'content' => 'Tổng số Khách', 'colspan' => 2, 'align' => 'center', 'format' => '', 'className' => 'total-label'],
                            ['id' => 'checked_out_guest_total', 'type' => 'text', 'content' => '{{group.count}}', 'colspan' => 1, 'align' => 'center', 'format' => '', 'className' => 'total-value'],
                            ['id' => 'checked_out_total_empty', 'type' => 'text', 'content' => '', 'colspan' => 7, 'align' => 'left', 'format' => '', 'className' => 'total-empty'],
                        ],
                    ],
                ],
            ]],
            'footer' => [[
                'id' => 'checked_out_summary',
                'type' => 'static-table',
                'tableStyle' => 'grid',
                'style' => ['fontSize' => '9px', 'marginTop' => '0px', 'width' => '46%', 'whiteSpace' => 'normal'],
                'columns' => [
                    ['width' => '20%'],
                    ['width' => '20%'],
                    ['width' => '20%'],
                    ['width' => '6%'],
                ],
                'rows' => [
                    ['cells' => [['content' => '<b>Phòng</b>'], ['content' => ''], ['content' => '<b>Tên Khách</b>'], ['content' => '<b>{{summary.row_count}}</b>']]],
                    ['cells' => [['content' => ''], ['content' => ''], ['content' => '<b>Người Việt Nam</b>'], ['content' => '']]],
                    ['cells' => [['content' => ''], ['content' => ''], ['content' => '<b>Người nước ngoài</b>'], ['content' => '']]],
                    ['cells' => [['content' => ''], ['content' => ''], ['content' => '<b>Khác</b>'], ['content' => '']]],
                ],
            ]],
        ];
    }

    private function columns(): array
    {
        return [
            ['header' => 'Phòng', 'value' => 'row.Room', 'width' => '6%', 'align' => 'center'],
            ['header' => 'Dạng<br>phòng', 'value' => 'row.RoomKind', 'width' => '7%', 'align' => 'center'],
            ['header' => 'Loại<br>phòng', 'value' => 'row.RoomType', 'width' => '7%', 'align' => 'center'],
            ['header' => 'Mã<br>ĐK', 'value' => 'row.BookingId', 'width' => '7%', 'align' => 'center'],
            ['header' => 'Tên nhóm', 'value' => 'row.BookingName', 'width' => '11%', 'align' => 'left'],
            ['header' => 'Tên khách', 'value' => 'row.Guest', 'width' => '12%', 'align' => 'left'],
            ['header' => 'Quốc tịch', 'value' => 'row.Nationality', 'width' => '8%', 'align' => 'center'],
            ['header' => 'Ngày đến', 'value' => 'row.ArrivalDate', 'width' => '9%', 'align' => 'center'],
            ['header' => 'Ngày đi', 'value' => 'row.DepartureDate', 'width' => '9%', 'align' => 'center'],
            ['header' => 'Số<br>đêm', 'value' => 'row.NumOfDays', 'width' => '5%', 'align' => 'center'],
            ['header' => 'Giờ trả<br>phòng', 'value' => 'row.CheckoutTime', 'width' => '8%', 'align' => 'center'],
            ['header' => 'Người<br>dùng', 'value' => 'row.UserCheckout', 'width' => '7%', 'align' => 'center'],
            ['header' => 'Ghi chú', 'value' => 'row.NoteMoi', 'width' => '10%', 'align' => 'left'],
        ];
    }

    private function html(): string
    {
        return <<<'HTML'
<div class="report-header-band"><div class="hotel-header"><div class="hotel-logo">{{hotel.logo}}</div><div class="hotel-meta"><div><b>Địa chỉ:</b> {{hotel.address}}</div><div><b>Nhân viên:</b> {{report.generated_by}}</div><div><b>Ngày:</b> {{report.generated_at}}</div></div></div><hr><h1>BÁO CÁO DANH SÁCH KHÁCH ĐÃ TRẢ PHÒNG</h1><p class="period"><b>Ngày:</b> {{parameters.p_from_date}} &nbsp; ~ &nbsp; {{parameters.p_to_date}}</p></div>
<table class="checked-out-table"><thead><tr><th>Phòng</th><th>Dạng<br>phòng</th><th>Loại<br>phòng</th><th>Mã<br>ĐK</th><th>Tên nhóm</th><th>Tên khách</th><th>Quốc tịch</th><th>Ngày đến</th><th>Ngày đi</th><th>Số<br>đêm</th><th>Giờ trả<br>phòng</th><th>Người<br>dùng</th><th>Ghi chú</th></tr></thead><tbody class="pms-grouped-rows" data-source="rows" data-group-configured="1" data-group-by="BookingId"><tr class="pms-group-header" data-group-level="0" data-group-field="BookingId" data-group-sort="ASC"><td colspan="13" class="booking-group">Tên Nhóm: {{row.BookingName}}</td></tr><tr class="pms-subgroup-note" data-visible-by="parameters.p_show_note"><td colspan="13"><b>Ghi Chú:</b> {{row.NoteMoi}}</td></tr><tr class="pms-detail-row"><td>{{row.Room}}</td><td>{{row.RoomKind}}</td><td>{{row.RoomType}}</td><td>{{row.BookingId}}</td><td>{{row.BookingName}}</td><td>{{row.Guest}}</td><td>{{row.Nationality}}</td><td>{{row.ArrivalDate}}</td><td>{{row.DepartureDate}}</td><td>{{row.NumOfDays}}</td><td>{{row.CheckoutTime}}</td><td>{{row.UserCheckout}}</td><td>{{row.NoteMoi}}</td></tr><tr class="pms-group-footer"><td colspan="2" class="total-label">Tổng cộng</td><td>{{group.distinct.RentalRoomId}}</td><td colspan="2" class="total-label">Tổng số Khách</td><td>{{group.count}}</td><td colspan="7"></td></tr></tbody></table>
<table class="guest-summary"><tbody><tr><td class="summary-room"><b>Phòng</b></td><td></td><td><b>Tên Khách</b></td><td><b>{{summary.row_count}}</b></td></tr><tr><td></td><td></td><td><b>Người Việt Nam</b></td><td></td></tr><tr><td></td><td></td><td><b>Người nước ngoài</b></td><td></td></tr><tr><td></td><td></td><td><b>Khác</b></td><td></td></tr></tbody></table>
HTML;
    }

    private function css(): string
    {
        return 'body{color:#111;font-family:Arial,Helvetica,sans-serif;font-size:8px}.report-header-band table,.report-detail-band table,.report-footer-band table{margin-top:0;margin-bottom:0}.hotel-header{display:grid;grid-template-columns:38% 62%;align-items:center}.hotel-logo{display:flex;align-items:center}.hotel-logo img,.hotel-logo-image{display:block;max-width:120px;max-height:70px;object-fit:contain}.hotel-meta{display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;font-size:8px;align-items:center}.hotel-meta div:nth-child(2){text-align:center}.hotel-meta div:nth-child(3){text-align:right}hr,.header-divider{border:0;border-top:1px solid #111;margin:0}h1{margin:0;text-align:center;font-size:18px;font-weight:700}.period{margin:0;text-align:center;font-weight:700}.checked-out-table{width:100%;border-collapse:collapse;table-layout:fixed}.checked-out-table th,.checked-out-table td{border:1px solid #999;padding:3px 4px;vertical-align:middle;overflow-wrap:anywhere;line-height:1.1}.checked-out-table th{background:#dce3ef;text-align:center;font-weight:700}.checked-out-table th:nth-child(1){width:6%}.checked-out-table th:nth-child(2),.checked-out-table th:nth-child(3),.checked-out-table th:nth-child(4){width:7%}.checked-out-table th:nth-child(5){width:11%}.checked-out-table th:nth-child(6){width:12%}.checked-out-table th:nth-child(7){width:8%}.checked-out-table th:nth-child(8),.checked-out-table th:nth-child(9){width:9%}.checked-out-table th:nth-child(10){width:5%}.checked-out-table th:nth-child(11){width:8%}.checked-out-table th:nth-child(12){width:7%}.checked-out-table th:nth-child(13){width:10%}.checked-out-table td:nth-child(1),.checked-out-table td:nth-child(2),.checked-out-table td:nth-child(3),.checked-out-table td:nth-child(4),.checked-out-table td:nth-child(7),.checked-out-table td:nth-child(8),.checked-out-table td:nth-child(9),.checked-out-table td:nth-child(10),.checked-out-table td:nth-child(11),.checked-out-table td:nth-child(12){text-align:center}.pms-group-header td{background:#fff;font-weight:700;text-align:center}.pms-subgroup-note td{font-weight:700;text-align:center}.pms-group-footer td{font-weight:700}.total-label{text-align:left!important}.guest-summary{width:46%;border-collapse:collapse;table-layout:fixed}.guest-summary td{border:1px solid #999;padding:3px 4px;height:16px}.guest-summary td:nth-child(1),.guest-summary td:nth-child(2){width:20%}.guest-summary td:nth-child(3){width:20%}.guest-summary td:nth-child(4){width:6%;text-align:right;background:#dce3ef}.guest-summary .summary-room{background:#dce3ef}@media print{thead{display:table-header-group}tr{break-inside:avoid}}';
    }
};
