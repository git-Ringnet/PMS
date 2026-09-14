<?php

use Illuminate\Support\Facades\DB;

return new class
{
    public function apply(): void
    {
        DB::table('templates')->where('report', 'INHOUSE_GUESTS_STANDARD')->update([
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
                    'id' => 'inhouse_guests_hotel_header',
                    'type' => 'columns',
                    'style' => ['marginBottom' => '8px', 'whiteSpace' => 'normal'],
                    'columns' => [
                        [
                            'width' => '36%',
                            'blocks' => [[
                                'id' => 'inhouse_guests_logo',
                                'type' => 'image',
                                'content' => 'hotel.logo',
                                'imageUrl' => '',
                                'style' => ['textAlign' => 'left', 'paddingTop' => '4px', 'paddingBottom' => '4px', 'whiteSpace' => 'normal'],
                            ]],
                        ],
                        [
                            'width' => '64%',
                            'blocks' => [[
                                'id' => 'inhouse_guests_hotel_meta',
                                'type' => 'text',
                                'content' => '<div><b>Địa chỉ:</b>&nbsp;&nbsp; {{hotel.address}}</div><div><b>Nhân viên:</b>&nbsp;&nbsp; {{report.generated_by}} <b style="float:right">Ngày:&nbsp;&nbsp; {{report.generated_at}}</b></div>',
                                'style' => ['fontSize' => '9px', 'lineHeight' => '1.8', 'paddingTop' => '8px', 'whiteSpace' => 'normal'],
                            ]],
                        ],
                    ],
                ],
                [
                    'id' => 'inhouse_guests_divider',
                    'type' => 'divider',
                    'content' => '<hr class="header-divider">',
                    'style' => ['marginBottom' => '12px', 'whiteSpace' => 'normal'],
                ],
                [
                    'id' => 'inhouse_guests_title',
                    'type' => 'text',
                    'content' => '<h1>BÁO CÁO DANH SÁCH KHÁCH Ở</h1>',
                    'style' => ['textAlign' => 'center', 'fontSize' => '20px', 'fontWeight' => 'bold', 'marginBottom' => '8px', 'whiteSpace' => 'normal'],
                ],
                [
                    'id' => 'inhouse_guests_period',
                    'type' => 'text',
                    'content' => '<p><b>Ngày:</b>&nbsp;&nbsp; {{parameters.p_from_date}} &nbsp;&nbsp; ~ &nbsp;&nbsp; {{parameters.p_to_date}}</p>',
                    'style' => ['textAlign' => 'center', 'fontSize' => '9px', 'fontWeight' => 'bold', 'marginBottom' => '10px', 'whiteSpace' => 'normal'],
                ],
            ],
            'detail' => [[
                'id' => 'inhouse_guests_rows',
                'type' => 'table',
                'dataSource' => 'rows',
                'tableType' => 'dynamic',
                'tableStyle' => 'grid',
                'groups' => [
                    ['id' => 'inhouse_guests_date_group', 'field' => 'StayDateGroup', 'label' => 'Ngày: {{row.StayDateGroup}}', 'className' => 'date-group', 'sort' => 'ASC'],
                    ['id' => 'inhouse_guests_agency_group', 'field' => 'AgencyGroupKey', 'label' => 'Đại lý du lịch: {{row.AgencyLabel}}', 'className' => 'agency-group', 'sort' => 'ASC', 'enabledBy' => 'parameters.p_view_agency'],
                    ['id' => 'inhouse_guests_booking_group', 'field' => 'BookingGroupKey', 'label' => 'Đăng ký: {{row.BookingId}} - {{row.BookingName}}', 'className' => 'booking-group', 'sort' => 'ASC', 'enabledBy' => 'parameters.p_show_booking'],
                ],
                'style' => ['whiteSpace' => 'normal'],
                'columns' => $this->columns(),
                'customRows' => [],
            ]],
            'footer' => [
                [
                    'id' => 'inhouse_guests_totals',
                    'type' => 'static-table',
                    'tableStyle' => 'grid',
                    'style' => ['fontSize' => '9px', 'marginTop' => '0px', 'width' => '100%', 'whiteSpace' => 'normal'],
                    'columns' => [['width' => '16%'], ['width' => '8%'], ['width' => '18%'], ['width' => '8%'], ['width' => '50%']],
                    'rows' => [
                        ['cells' => [['content' => '<b>Tổng số phòng</b>'], ['content' => '<b>{{aggregate.rows.distinct_count.RentalRoomId}}</b>'], ['content' => '<b>Tổng số khách</b>'], ['content' => '<b>{{aggregate.rows.count}}</b>'], ['content' => '']]],
                    ],
                ],
                [
                    'id' => 'inhouse_guests_nationality_summary',
                    'type' => 'table',
                    'dataSource' => 'rows',
                    'tableType' => 'dynamic',
                    'tableStyle' => 'grid',
                    'groups' => [[
                        'id' => 'inhouse_guests_nationality_group',
                        'field' => 'NationalityName',
                        'label' => '{{row.NationalityName}}',
                        'className' => 'nationality-row',
                        'sort' => 'ASC',
                    ]],
                    'style' => ['fontSize' => '9px', 'marginTop' => '16px', 'whiteSpace' => 'normal'],
                    'columns' => $this->summaryColumns(),
                    'customRows' => [],
                ],
            ],
        ];
    }

    private function columns(): array
    {
        return [
            ['header' => 'Mã ĐK', 'value' => 'row.BookingId', 'width' => '9%', 'align' => 'center'],
            ['header' => 'Phòng', 'value' => 'row.Room', 'width' => '6%', 'align' => 'center'],
            ['header' => 'Dạng<br>Phòng', 'value' => 'row.RoomKind', 'width' => '8%', 'align' => 'center'],
            ['header' => 'Tên ĐK', 'value' => 'row.BookingName', 'width' => '15%', 'align' => 'center'],
            ['header' => 'Tên Khách', 'value' => 'row.Guest', 'width' => '17%', 'align' => 'center'],
            ['header' => 'Ngày Đến', 'value' => 'row.ArrivalDate', 'width' => '10%', 'align' => 'center'],
            ['header' => 'Ngày Đi', 'value' => 'row.DepartureDate', 'width' => '10%', 'align' => 'center'],
            ['header' => 'Số<br>Đêm', 'value' => 'row.NumOfDays', 'width' => '6%', 'align' => 'center'],
            ['header' => 'Quốc Tịch', 'value' => 'row.NationalityName', 'width' => '10%', 'align' => 'center'],
            ['header' => 'Ghi Chú', 'value' => 'row.Note', 'width' => '9%', 'align' => 'left'],
        ];
    }

    private function summaryColumns(): array
    {
        return [
            ['header' => 'Quốc tịch', 'value' => 'row.NationalityName', 'width' => '21%', 'align' => 'center'],
            ['header' => 'Đếm Khách', 'value' => 'row.NationalityGuestCount', 'width' => '14%', 'align' => 'center'],
            ['header' => 'Số Lượng Phòng', 'value' => 'row.NationalityRoomCount', 'width' => '17%', 'align' => 'center'],
            ['header' => 'Phần Trăm Theo<br>Đếm Khách (%)', 'value' => 'row.NationalityPercent', 'width' => '18%', 'align' => 'center'],
            ['header' => 'Nam', 'value' => 'row.NationalityMaleCount', 'width' => '10%', 'align' => 'center'],
            ['header' => 'Nữ', 'value' => 'row.NationalityFemaleCount', 'width' => '10%', 'align' => 'center'],
            ['header' => 'Khác', 'value' => 'row.NationalityOtherCount', 'width' => '10%', 'align' => 'center'],
        ];
    }

    private function html(): string
    {
        return <<<'HTML'
<div class="report-header-band"><div class="hotel-header"><div class="hotel-logo">{{hotel.logo}}</div><div class="hotel-meta"><div><b>Địa chỉ:</b> {{hotel.address}}</div><div><b>Nhân viên:</b> {{report.generated_by}} <b class="generated-date">Ngày: {{report.generated_at}}</b></div></div></div><hr><h1>BÁO CÁO DANH SÁCH KHÁCH Ở</h1><p class="period"><b>Ngày:</b> {{parameters.p_from_date}} &nbsp; ~ &nbsp; {{parameters.p_to_date}}</p></div>
<table class="inhouse-guests-table"><thead><tr><th>Mã ĐK</th><th>Phòng</th><th>Dạng<br>Phòng</th><th>Tên ĐK</th><th>Tên Khách</th><th>Ngày Đến</th><th>Ngày Đi</th><th>Số<br>Đêm</th><th>Quốc Tịch</th><th>Ghi Chú</th></tr></thead><tbody class="pms-grouped-rows" data-source="rows" data-group-configured="1" data-group-by="StayDateGroup"><tr class="pms-group-header" data-group-level="0" data-group-field="StayDateGroup" data-group-sort="ASC"><td colspan="10" class="date-group">Ngày: &nbsp;&nbsp; {{row.StayDateGroup}}</td></tr><tr class="pms-group-header" data-group-level="1" data-group-field="AgencyGroupKey" data-group-sort="ASC" data-group-enabled-by="parameters.p_view_agency"><td colspan="10" class="agency-group">Đại lý du lịch: {{row.AgencyLabel}}</td></tr><tr class="pms-group-header" data-group-level="2" data-group-field="BookingGroupKey" data-group-sort="ASC" data-group-enabled-by="parameters.p_show_booking"><td colspan="10" class="booking-group">Đăng ký: {{row.BookingId}} - {{row.BookingName}}</td></tr><tr class="pms-detail-row"><td>{{row.BookingId}}</td><td>{{row.Room}}</td><td>{{row.RoomKind}}</td><td>{{row.BookingName}}</td><td>{{row.Guest}}</td><td>{{row.ArrivalDate}}</td><td>{{row.DepartureDate}}</td><td>{{row.NumOfDays}}</td><td>{{row.NationalityName}}</td><td>{{row.Note}}</td></tr></tbody><tfoot><tr class="report-total"><td colspan="2"><b>Tổng số phòng</b></td><td><b>{{aggregate.rows.distinct_count.RentalRoomId}}</b></td><td colspan="2"><b>Tổng số Khách</b></td><td><b>{{aggregate.rows.count}}</b></td><td colspan="4"></td></tr></tfoot></table>
<table class="nationality-summary"><thead><tr><th>Quốc tịch</th><th>Đếm Khách</th><th>Số Lượng Phòng</th><th>Phần Trăm Theo<br>Đếm Khách (%)</th><th>Nam</th><th>Nữ</th><th>Khác</th></tr></thead><tbody class="pms-grouped-rows" data-source="rows" data-group-by="NationalityName"><tr class="pms-group-header"><td>{{row.NationalityName}}</td><td>{{row.NationalityGuestCount}}</td><td>{{row.NationalityRoomCount}}</td><td>{{row.NationalityPercent}}%</td><td>{{row.NationalityMaleCount}}</td><td>{{row.NationalityFemaleCount}}</td><td>{{row.NationalityOtherCount}}</td></tr></tbody><tfoot><tr><td><b>Tổng cộng</b></td><td><b>{{aggregate.rows.count}}</b></td><td><b>{{aggregate.rows.distinct_count.RentalRoomId}}</b></td><td><b>100.00%</b></td><td><b>{{aggregate.rows.sum.GenderMale}}</b></td><td><b>{{aggregate.rows.sum.GenderFemale}}</b></td><td><b>{{aggregate.rows.sum.GenderOther}}</b></td></tr></tfoot></table>
HTML;
    }

    private function css(): string
    {
        return 'body{color:#111;font-family:Arial,Helvetica,sans-serif;font-size:8px}.report-header-band table,.report-detail-band table,.report-footer-band table{margin-top:0;margin-bottom:0}.hotel-header{display:grid;grid-template-columns:36% 64%;align-items:center}.hotel-logo{display:flex;align-items:center}.hotel-logo img,.hotel-logo-image{display:block;max-width:120px;max-height:70px;object-fit:contain}.hotel-meta{font-size:8px;line-height:1.8}.generated-date{float:right}hr,.header-divider{border:0;border-top:1px solid #111;margin:0 0 12px}h1{margin:0;text-align:center;font-size:18px;font-weight:700}.period{margin:8px 0 10px;text-align:center;font-weight:700}.inhouse-guests-table,.nationality-summary{width:100%;border-collapse:collapse;table-layout:fixed}.inhouse-guests-table th,.inhouse-guests-table td,.nationality-summary th,.nationality-summary td{border:1px solid #aeb5c0;padding:3px 4px;vertical-align:middle;overflow-wrap:anywhere;line-height:1.1}.inhouse-guests-table th,.nationality-summary th{background:#dce3ef;text-align:center;font-weight:700}.inhouse-guests-table th:nth-child(1){width:9%}.inhouse-guests-table th:nth-child(2){width:6%}.inhouse-guests-table th:nth-child(3){width:8%}.inhouse-guests-table th:nth-child(4){width:15%}.inhouse-guests-table th:nth-child(5){width:17%}.inhouse-guests-table th:nth-child(6),.inhouse-guests-table th:nth-child(7){width:10%}.inhouse-guests-table th:nth-child(8){width:6%}.inhouse-guests-table th:nth-child(9){width:10%}.inhouse-guests-table th:nth-child(10){width:9%}.inhouse-guests-table td{text-align:center}.inhouse-guests-table td:last-child{text-align:left}.date-group{color:#d71920;text-align:left!important;font-weight:700}.agency-group,.booking-group{background:#f3f5f8;text-align:left!important;font-weight:700}.report-total td{background:#dce3ef;font-weight:700}.nationality-summary{margin-top:16px}.nationality-summary th:nth-child(1){width:21%}.nationality-summary th:nth-child(2){width:14%}.nationality-summary th:nth-child(3){width:17%}.nationality-summary th:nth-child(4){width:18%}.nationality-summary th:nth-child(5),.nationality-summary th:nth-child(6),.nationality-summary th:nth-child(7){width:10%}.nationality-summary td{text-align:center;font-weight:700}.nationality-summary tfoot td{background:#dce3ef}@media print{thead{display:table-header-group}tr{break-inside:avoid}}';
    }
};
