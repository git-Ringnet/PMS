# Đặc Tả Kỹ Thuật: Báo Cáo Doanh Thu (Đơn Vị Army Quy Nhơn) - Dòng 150

> **Tài liệu chuẩn bị cho Agent triển khai tiếp theo**  
> Dựa trên phân tích từ file Excel `DANH MỤC BÁO CÁO.xlsx` (Dòng 150, Sheet 73 `Báo cáo Dthu`), ảnh giao diện thực tế `[.codex/docs/doc_baocao/images/dong_150_ui_mau.png](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/images/dong_150_ui_mau.png)` và Stored Procedure gốc trên MS SQL Server `ProVistaArmyHotel.dbo.sp_292` ([sp_292_full.sql](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/sql/sp_292_full.sql)).

---

## 1. Thông Tin Chung & Định Danh

* **Tên báo cáo tiếng Việt**: Báo cáo doanh thu
* **Mã báo cáo (`code`)**: `REVENUE_ARMY`
* **Mã nguồn dữ liệu (`data_source_code`)**: `RPT_REVENUE_ARMY`
* **Mã template (`template_code`)**: `REVENUE_ARMY_REFERENCE`
* **Nhóm báo cáo (`group`)**: `Báo cáo doanh thu`
* **Menu hiển thị**: `['frontdesk', 'cashier', 'report']`
* **Vị trí trong Excel danh mục**: Dòng 150 (STT 4.0)
* **Ghi chú trong Excel**: "Mẫu báo cáo của đơn vị army quy nhơn khi nào làm báo cáo này báo Vy gửi lại store mới nhất"
* **Sheet tham chiếu trong Excel**: Sheet 73 (`Báo cáo Dthu`)
* **Ảnh chụp màn hình thực tế**: `[.codex/docs/doc_baocao/images/dong_150_ui_mau.png](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/images/dong_150_ui_mau.png)`
* **File SQL legacy tham chiếu**: [sp_292_full.sql](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/sql/sp_292_full.sql)

---

## 2. Phân Tích Giao Diện & Bộ Lọc (`dong_150_ui_mau.png`)

### 2.1. Panel Bộ Lọc Bên Trái (Left Filter Panel)
* **Chiều rộng panel**: ~ 260px - 280px.
* **Các thành phần điều khiển**:
  1. **Ngày (`p_date`)**: Single Date Picker. Mặc định: `$today` (ví dụ trên ảnh: `15/07/2026`).
  2. **Công ty (`p_company_id`)**: Dropdown Select, placeholder: `Công ty`, nguồn: danh mục `companies`. Mặc định: `0` (Tất cả).
  3. **Đăng ký (`p_booking_id`)**: Dropdown Select, placeholder: `Select Value`, nguồn: danh mục `bookings`. Mặc định: `0` (Tất cả).
  4. **Nút bấm**: `Hiển thị báo cáo` (Màu xanh biển `#3b82f6`).

---

## 3. Cấu Trúc Toàn Diện 100% Của `content_json` (Form Designer)

> **QUAN TRỌNG CHO AGENT TRIỂN KHAI:**  
> Trong hệ thống PMS, `content_json` là **nguồn dữ liệu gốc (source-of-truth)** quy định toàn bộ bố cục, khối (blocks), thuộc tính từng ô, header 2 tầng, danh sách cột, và các dòng tổng cộng `customRows`.  
> File `content_html` và `css` chỉ là bản biên dịch (compiled output) từ `content_json` để render giao diện HTML/PDF.  
> Dưới đây là toàn bộ mã nguồn PHP chuẩn hóa tạo ra `content_json`, sẵn sàng copy vào file `backend/database/report_templates/revenue_army_reference.php`.

```php
<?php

use App\Services\TemplateRendererService;

return new class
{
    public function definition(): array
    {
        return [
            'code' => 'REVENUE_ARMY',
            'name' => 'Báo cáo doanh thu',
            'report' => 'REVENUE_ARMY_REFERENCE',
            'page_size' => 'A4',
            'page_orientation' => 'landscape',
            'margin_top' => 8,
            'margin_right' => 5,
            'margin_bottom' => 8,
            'margin_left' => 5,
            'version' => '1.0',
            'content_html' => $this->html(),
            'content_json' => $this->blocks(),
            'css' => $this->css(),
            'columns' => $this->columns(),
            'data_contract' => [
                'rows' => [
                    'Index' => 'integer',
                    'BookingId' => 'string',
                    'GuestName' => 'string',
                    'BusinessName' => 'string',
                    'ArrivalDate' => 'string',
                    'DepartureDate' => 'string',
                    'RoomCount' => 'integer',
                    'RoomToday' => 'number',
                    'ExtraRoomToday' => 'number',
                    'MinibarToday' => 'number',
                    'LaundryToday' => 'number',
                    'BrokenToday' => 'number',
                    'RestaurantToday' => 'number',
                    'OtherToday' => 'number',
                    'TotalToday' => 'number',
                    'PrevDay' => 'number',
                    'TotalRevenue' => 'number',
                    'Cash' => 'number',
                    'BankTransfer' => 'number',
                    'Commission' => 'number',
                    'CityLedger' => 'number',
                    'InhouseRoom' => 'number',
                ],
                'parameters' => [
                    'p_date' => 'string',
                    'p_company_id' => 'string',
                    'p_booking_id' => 'string',
                ],
            ],
        ];
    }

    public function columns(): array
    {
        return [
            ['id' => 'col_index', 'field' => 'Index', 'header' => 'STT', 'width' => '2.5%', 'align' => 'center', 'format' => 'text'],
            ['id' => 'col_booking_id', 'field' => 'BookingId', 'header' => 'Mã ĐK', 'width' => '4%', 'align' => 'center', 'format' => 'text'],
            ['id' => 'col_guest_name', 'field' => 'GuestName', 'header' => 'Tên khách', 'width' => '11%', 'align' => 'left', 'format' => 'text'],
            ['id' => 'col_company', 'field' => 'BusinessName', 'header' => 'Đơn vị', 'width' => '7%', 'align' => 'left', 'format' => 'text'],
            ['id' => 'col_arr_date', 'field' => 'ArrivalDate', 'header' => 'Ngày đến', 'width' => '4.5%', 'align' => 'center', 'format' => 'text'],
            ['id' => 'col_dep_date', 'field' => 'DepartureDate', 'header' => 'Ngày đi', 'width' => '4.5%', 'align' => 'center', 'format' => 'text'],
            ['id' => 'col_room_count', 'field' => 'RoomCount', 'header' => 'SP', 'width' => '2.5%', 'align' => 'center', 'format' => 'number'],
            ['id' => 'col_room_today', 'field' => 'RoomToday', 'header' => 'Tiền phòng', 'width' => '4.5%', 'align' => 'right', 'format' => 'number'],
            ['id' => 'col_extra_room', 'field' => 'ExtraRoomToday', 'header' => 'Phụ thu tiền phòng', 'width' => '4.5%', 'align' => 'right', 'format' => 'number'],
            ['id' => 'col_minibar', 'field' => 'MinibarToday', 'header' => 'Minibar', 'width' => '3.5%', 'align' => 'right', 'format' => 'number'],
            ['id' => 'col_laundry', 'field' => 'LaundryToday', 'header' => 'Giặt', 'width' => '3.5%', 'align' => 'right', 'format' => 'number'],
            ['id' => 'col_broken', 'field' => 'BrokenToday', 'header' => 'Bể vỡ', 'width' => '3.5%', 'align' => 'right', 'format' => 'number'],
            ['id' => 'col_restaurant', 'field' => 'RestaurantToday', 'header' => 'Nhà hàng', 'width' => '4.5%', 'align' => 'right', 'format' => 'number'],
            ['id' => 'col_other_service', 'field' => 'OtherToday', 'header' => 'Dịch vụ', 'width' => '4%', 'align' => 'right', 'format' => 'number'],
            ['id' => 'col_total_today', 'field' => 'TotalToday', 'header' => 'Tổng DT', 'width' => '5%', 'align' => 'right', 'format' => 'number'],
            ['id' => 'col_prev_day', 'field' => 'PrevDay', 'header' => 'DT ngày trước', 'width' => '5%', 'align' => 'right', 'format' => 'number'],
            ['id' => 'col_total_rev', 'field' => 'TotalRevenue', 'header' => 'Tổng cộng', 'width' => '5.5%', 'align' => 'right', 'format' => 'number'],
            ['id' => 'col_cash', 'field' => 'Cash', 'header' => 'TM', 'width' => '4.5%', 'align' => 'right', 'format' => 'number'],
            ['id' => 'col_transfer', 'field' => 'BankTransfer', 'header' => 'CK', 'width' => '5%', 'align' => 'right', 'format' => 'number'],
            ['id' => 'col_commission', 'field' => 'Commission', 'header' => 'HH', 'width' => '3.5%', 'align' => 'right', 'format' => 'number'],
            ['id' => 'col_city_ledger', 'field' => 'CityLedger', 'header' => 'Còn nợ', 'width' => '4.5%', 'align' => 'right', 'format' => 'number'],
            ['id' => 'col_inhouse_room', 'field' => 'InhouseRoom', 'header' => 'Phòng còn ở', 'width' => '5%', 'align' => 'right', 'format' => 'number'],
        ];
    }

    public function blocks(): array
    {
        return [
            'header' => [
                [
                    'id' => 'revenue_header_grid',
                    'type' => 'columns',
                    'style' => [
                        'display' => 'flex',
                        'justifyContent' => 'space-between',
                        'alignItems' => 'flex-start',
                        'marginBottom' => '4px',
                    ],
                    'columns' => [
                        [
                            'width' => '30%',
                            'blocks' => [[
                                'id' => 'revenue_logo',
                                'type' => 'text',
                                'content' => '<div class="hotel-logo">{{hotel.logo}}</div>',
                                'style' => ['minHeight' => '50px'],
                            ]],
                        ],
                        [
                            'width' => '70%',
                            'blocks' => [[
                                'id' => 'revenue_hotel_info',
                                'type' => 'text',
                                'content' => '<div class="hotel-information"><div><b>Địa chỉ:</b> {{hotel.address}}</div><div><b>Người dùng:</b> {{report.generated_by}} &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <b>Ngày:</b> {{report.generated_at}}</div></div>',
                                'style' => ['textAlign' => 'right', 'fontSize' => '9.5px', 'lineHeight' => '1.5'],
                            ]],
                        ],
                    ],
                ],
                [
                    'id' => 'revenue_divider',
                    'type' => 'divider',
                    'content' => '<div class="header-divider"></div>',
                    'style' => ['borderTop' => '1px solid #111111', 'marginTop' => '4px', 'marginBottom' => '8px'],
                ],
                [
                    'id' => 'revenue_title',
                    'type' => 'text',
                    'content' => '<h1 style="text-align: center; font-size: 17px; font-weight: bold; margin: 4px 0 2px; color: #111111;">BÁO CÁO DOANH THU</h1>',
                    'style' => ['textAlign' => 'center', 'fontWeight' => 'bold', 'fontSize' => '17px', 'color' => '#111111'],
                ],
                [
                    'id' => 'revenue_period',
                    'type' => 'text',
                    'content' => '<p class="period" style="text-align: center; font-size: 10px; margin: 2px 0 10px; color: #111111;"><b>Ngày:</b> {{parameters.p_date}}</p>',
                    'style' => ['textAlign' => 'center', 'fontSize' => '10px', 'color' => '#111111'],
                ],
            ],
            'detail' => [[
                'id' => 'revenue_army_table',
                'type' => 'table',
                'dataSource' => 'rows',
                'tableType' => 'dynamic',
                'tableClassName' => 'revenue-army-table',
                'style' => [
                    'width' => '100%',
                    'fontSize' => '8.5px',
                    'borderCollapse' => 'collapse',
                    'borderColor' => '#aeb5c0',
                    'borderWidth' => '1px',
                    'borderStyle' => 'solid',
                    'backgroundColor' => '#ffffff',
                ],
                'hasTwoTierHeader' => true,
                'topHeader' => [
                    ['label' => 'STT', 'rowspan' => 2, 'width' => '2.5%', 'align' => 'center'],
                    ['label' => 'Mã ĐK', 'rowspan' => 2, 'width' => '4%', 'align' => 'center'],
                    ['label' => 'Tên khách', 'rowspan' => 2, 'width' => '11%', 'align' => 'center'],
                    ['label' => 'Đơn vị', 'rowspan' => 2, 'width' => '7%', 'align' => 'center'],
                    ['label' => 'Ngày đến', 'rowspan' => 2, 'width' => '4.5%', 'align' => 'center'],
                    ['label' => 'Ngày đi', 'rowspan' => 2, 'width' => '4.5%', 'align' => 'center'],
                    ['label' => 'SP', 'rowspan' => 2, 'width' => '2.5%', 'align' => 'center'],
                    ['label' => 'Các khoản thu trong ngày', 'colspan' => 7, 'align' => 'center'],
                    ['label' => 'Tổng DT', 'rowspan' => 2, 'width' => '5%', 'align' => 'center'],
                    ['label' => 'DT ngày trước', 'rowspan' => 2, 'width' => '5%', 'align' => 'center'],
                    ['label' => 'Tổng cộng', 'rowspan' => 2, 'width' => '5.5%', 'align' => 'center'],
                    ['label' => 'Phòng đã trả', 'colspan' => 4, 'align' => 'center'],
                    ['label' => 'Phòng còn ở', 'rowspan' => 2, 'width' => '5%', 'align' => 'center'],
                ],
                'columns' => $this->columns(),
                'customRows' => [
                    [
                        'id' => 'revenue_army_grand_total_row',
                        'enabledBy' => '',
                        'scope' => 'table',
                        'level' => 0,
                        'className' => 'report-grand-total-row',
                        'cells' => [
                            [
                                'id' => 'total_bk_label',
                                'type' => 'text',
                                'content' => 'Tổng số BK: {{aggregate.rows.count}}',
                                'colspan' => 6,
                                'align' => 'left',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'left', 'backgroundColor' => '#ffffff', 'padding' => '4px 3px'],
                            ],
                            [
                                'id' => 'total_room_count',
                                'type' => 'binding',
                                'binding' => 'aggregate.rows.sum.RoomCount',
                                'colspan' => 1,
                                'align' => 'center',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'center', 'backgroundColor' => '#ffffff', 'padding' => '4px 2px'],
                            ],
                            [
                                'id' => 'total_room_today',
                                'type' => 'binding',
                                'binding' => 'aggregate.rows.sum.RoomToday',
                                'colspan' => 1,
                                'align' => 'right',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'right', 'backgroundColor' => '#ffffff', 'padding' => '4px 2px'],
                            ],
                            [
                                'id' => 'total_extra_room',
                                'type' => 'binding',
                                'binding' => 'aggregate.rows.sum.ExtraRoomToday',
                                'colspan' => 1,
                                'align' => 'right',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'right', 'backgroundColor' => '#ffffff', 'padding' => '4px 2px'],
                            ],
                            [
                                'id' => 'total_minibar',
                                'type' => 'binding',
                                'binding' => 'aggregate.rows.sum.MinibarToday',
                                'colspan' => 1,
                                'align' => 'right',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'right', 'backgroundColor' => '#ffffff', 'padding' => '4px 2px'],
                            ],
                            [
                                'id' => 'total_laundry',
                                'type' => 'binding',
                                'binding' => 'aggregate.rows.sum.LaundryToday',
                                'colspan' => 1,
                                'align' => 'right',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'right', 'backgroundColor' => '#ffffff', 'padding' => '4px 2px'],
                            ],
                            [
                                'id' => 'total_broken',
                                'type' => 'binding',
                                'binding' => 'aggregate.rows.sum.BrokenToday',
                                'colspan' => 1,
                                'align' => 'right',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'right', 'backgroundColor' => '#ffffff', 'padding' => '4px 2px'],
                            ],
                            [
                                'id' => 'total_restaurant',
                                'type' => 'binding',
                                'binding' => 'aggregate.rows.sum.RestaurantToday',
                                'colspan' => 1,
                                'align' => 'right',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'right', 'backgroundColor' => '#ffffff', 'padding' => '4px 2px'],
                            ],
                            [
                                'id' => 'total_other_service',
                                'type' => 'binding',
                                'binding' => 'aggregate.rows.sum.OtherToday',
                                'colspan' => 1,
                                'align' => 'right',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'right', 'backgroundColor' => '#ffffff', 'padding' => '4px 2px'],
                            ],
                            [
                                'id' => 'total_today',
                                'type' => 'binding',
                                'binding' => 'aggregate.rows.sum.TotalToday',
                                'colspan' => 1,
                                'align' => 'right',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'right', 'backgroundColor' => '#ffffff', 'padding' => '4px 2px'],
                            ],
                            [
                                'id' => 'total_prev_day',
                                'type' => 'binding',
                                'binding' => 'aggregate.rows.sum.PrevDay',
                                'colspan' => 1,
                                'align' => 'right',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'right', 'backgroundColor' => '#ffffff', 'padding' => '4px 2px'],
                            ],
                            [
                                'id' => 'total_revenue',
                                'type' => 'binding',
                                'binding' => 'aggregate.rows.sum.TotalRevenue',
                                'colspan' => 1,
                                'align' => 'right',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'right', 'backgroundColor' => '#ffffff', 'padding' => '4px 2px'],
                            ],
                            [
                                'id' => 'total_cash',
                                'type' => 'binding',
                                'binding' => 'aggregate.rows.sum.Cash',
                                'colspan' => 1,
                                'align' => 'right',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'right', 'backgroundColor' => '#ffffff', 'padding' => '4px 2px'],
                            ],
                            [
                                'id' => 'total_transfer',
                                'type' => 'binding',
                                'binding' => 'aggregate.rows.sum.BankTransfer',
                                'colspan' => 1,
                                'align' => 'right',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'right', 'backgroundColor' => '#ffffff', 'padding' => '4px 2px'],
                            ],
                            [
                                'id' => 'total_commission',
                                'type' => 'binding',
                                'binding' => 'aggregate.rows.sum.Commission',
                                'colspan' => 1,
                                'align' => 'right',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'right', 'backgroundColor' => '#ffffff', 'padding' => '4px 2px'],
                            ],
                            [
                                'id' => 'total_city_ledger',
                                'type' => 'binding',
                                'binding' => 'aggregate.rows.sum.CityLedger',
                                'colspan' => 1,
                                'align' => 'right',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'right', 'backgroundColor' => '#ffffff', 'padding' => '4px 2px'],
                            ],
                            [
                                'id' => 'total_inhouse_room',
                                'type' => 'binding',
                                'binding' => 'aggregate.rows.sum.InhouseRoom',
                                'colspan' => 1,
                                'align' => 'right',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'right', 'backgroundColor' => '#ffffff', 'padding' => '4px 2px'],
                            ],
                        ],
                    ],
                ],
            ]],
            'footer' => [
                [
                    'id' => 'revenue_location_date',
                    'type' => 'text',
                    'content' => '<p style="text-align: right; font-style: italic; font-size: 9.5px; margin: 12px 0 10px 0;">Quy Nhơn, Ngày {{report.day}} Tháng {{report.month}} Năm {{report.year}}</p>',
                    'style' => ['textAlign' => 'right', 'fontSize' => '9.5px'],
                ],
                [
                    'id' => 'revenue_signature_grid',
                    'type' => 'columns',
                    'style' => [
                        'display' => 'flex',
                        'justifyContent' => 'space-between',
                        'textAlign' => 'center',
                        'marginTop' => '10px',
                    ],
                    'columns' => [
                        ['width' => '20%', 'blocks' => [['id' => 'sig_1', 'type' => 'text', 'content' => '<b>Chữ Ký Người Lập</b>']]],
                        ['width' => '20%', 'blocks' => [['id' => 'sig_2', 'type' => 'text', 'content' => '<b>Trưởng Bộ Phận</b>']]],
                        ['width' => '20%', 'blocks' => [['id' => 'sig_3', 'type' => 'text', 'content' => '<b>Kế Toán</b>']]],
                        ['width' => '20%', 'blocks' => [['id' => 'sig_4', 'type' => 'text', 'content' => '<b>Tổng Quản Lý</b>']]],
                        ['width' => '20%', 'blocks' => [['id' => 'sig_5', 'type' => 'text', 'content' => '<b>Giám Đốc</b>']]],
                    ],
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
      <div><b>Người dùng:</b> {{report.generated_by}} &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <b>Ngày:</b> {{report.generated_at}}</div>
    </div>
  </div>
  <div class="header-divider"></div>
  <h1>BÁO CÁO DOANH THU</h1>
  <p class="period"><b>Ngày:</b> {{parameters.p_date}}</p>
</div>

<table class="revenue-army-table">
  <thead>
    <tr>
      <th rowspan="2" style="width: 2.5%;">STT</th>
      <th rowspan="2" style="width: 4%;">Mã ĐK</th>
      <th rowspan="2" style="width: 11%;">Tên khách</th>
      <th rowspan="2" style="width: 7%;">Đơn vị</th>
      <th rowspan="2" style="width: 4.5%;">Ngày đến</th>
      <th rowspan="2" style="width: 4.5%;">Ngày đi</th>
      <th rowspan="2" style="width: 2.5%;">SP</th>
      <th colspan="7">Các khoản thu trong ngày</th>
      <th rowspan="2" style="width: 5%;">Tổng DT</th>
      <th rowspan="2" style="width: 5%;">DT ngày trước</th>
      <th rowspan="2" style="width: 5.5%;">Tổng cộng</th>
      <th colspan="4">Phòng đã trả</th>
      <th rowspan="2" style="width: 5%;">Phòng còn ở</th>
    </tr>
    <tr>
      <th style="width: 4.5%;">Tiền phòng</th>
      <th style="width: 4.5%;">Phụ thu tiền phòng</th>
      <th style="width: 3.5%;">Minibar</th>
      <th style="width: 3.5%;">Giặt</th>
      <th style="width: 3.5%;">Bể vỡ</th>
      <th style="width: 4.5%;">Nhà hàng</th>
      <th style="width: 4%;">Dịch vụ</th>
      <th style="width: 4.5%;">TM</th>
      <th style="width: 5%;">CK</th>
      <th style="width: 3.5%;">HH</th>
      <th style="width: 4.5%;">Còn nợ</th>
    </tr>
  </thead>
  <tbody>
    {% for row in rows %}
    <tr>
      <td class="center">{{ row.Index }}</td>
      <td class="center">{{ row.BookingId }}</td>
      <td>{{ row.GuestName }}</td>
      <td>{{ row.BusinessName }}</td>
      <td class="center">{{ row.ArrivalDate }}</td>
      <td class="center">{{ row.DepartureDate }}</td>
      <td class="center">{{ row.RoomCount }}</td>
      <td class="number">{{ row.RoomToday|number }}</td>
      <td class="number">{{ row.ExtraRoomToday|number }}</td>
      <td class="number">{{ row.MinibarToday|number }}</td>
      <td class="number">{{ row.LaundryToday|number }}</td>
      <td class="number">{{ row.BrokenToday|number }}</td>
      <td class="number">{{ row.RestaurantToday|number }}</td>
      <td class="number">{{ row.OtherToday|number }}</td>
      <td class="number bold">{{ row.TotalToday|number }}</td>
      <td class="number">{{ row.PrevDay|number }}</td>
      <td class="number bold">{{ row.TotalRevenue|number }}</td>
      <td class="number">{{ row.Cash|number }}</td>
      <td class="number">{{ row.BankTransfer|number }}</td>
      <td class="number">{{ row.Commission|number }}</td>
      <td class="number">{{ row.CityLedger|number }}</td>
      <td class="number">{{ row.InhouseRoom|number }}</td>
    </tr>
    {% endfor %}
  </tbody>
  <tfoot>
    <tr class="report-grand-total-row">
      <td colspan="6" class="bold text-left">Tổng số BK: {{ aggregate.rows.count }}</td>
      <td class="center bold">{{ aggregate.rows.sum.RoomCount }}</td>
      <td class="number bold">{{ aggregate.rows.sum.RoomToday|number }}</td>
      <td class="number bold">{{ aggregate.rows.sum.ExtraRoomToday|number }}</td>
      <td class="number bold">{{ aggregate.rows.sum.MinibarToday|number }}</td>
      <td class="number bold">{{ aggregate.rows.sum.LaundryToday|number }}</td>
      <td class="number bold">{{ aggregate.rows.sum.BrokenToday|number }}</td>
      <td class="number bold">{{ aggregate.rows.sum.RestaurantToday|number }}</td>
      <td class="number bold">{{ aggregate.rows.sum.OtherToday|number }}</td>
      <td class="number bold">{{ aggregate.rows.sum.TotalToday|number }}</td>
      <td class="number bold">{{ aggregate.rows.sum.PrevDay|number }}</td>
      <td class="number bold">{{ aggregate.rows.sum.TotalRevenue|number }}</td>
      <td class="number bold">{{ aggregate.rows.sum.Cash|number }}</td>
      <td class="number bold">{{ aggregate.rows.sum.BankTransfer|number }}</td>
      <td class="number bold">{{ aggregate.rows.sum.Commission|number }}</td>
      <td class="number bold">{{ aggregate.rows.sum.CityLedger|number }}</td>
      <td class="number bold">{{ aggregate.rows.sum.InhouseRoom|number }}</td>
    </tr>
  </tfoot>
</table>

<div class="footer-block">
  <p class="location-date">Quy Nhơn, Ngày {{ report.day }} Tháng {{ report.month }} Năm {{ report.year }}</p>
  <div class="signature-grid">
    <div class="sig-col"><b>Chữ Ký Người Lập</b></div>
    <div class="sig-col"><b>Trưởng Bộ Phận</b></div>
    <div class="sig-col"><b>Kế Toán</b></div>
    <div class="sig-col"><b>Tổng Quản Lý</b></div>
    <div class="sig-col"><b>Giám Đốc</b></div>
  </div>
</div>
HTML;
    }

    public function css(): string
    {
        return <<<'CSS'
.report-header-band { width: 100%; margin-bottom: 6px; }
.report-header-grid { display: flex; justify-content: space-between; align-items: flex-start; }
.hotel-logo img { max-height: 50px; }
.hotel-information { font-size: 9.5px; line-height: 1.5; text-align: right; color: #111; }
.header-divider { border-top: 1px solid #111; margin: 4px 0 8px 0; }
h1 { font-size: 17px; font-weight: bold; text-align: center; margin: 4px 0 2px 0; color: #111; }
p.period { font-size: 10px; text-align: center; margin: 2px 0 8px 0; color: #111; }
table.revenue-army-table { width: 100%; border-collapse: collapse; font-size: 8.5px; border: 1px solid #aeb5c0; }
table.revenue-army-table th, table.revenue-army-table td { border: 1px solid #aeb5c0; padding: 3px 2px; }
table.revenue-army-table th { background-color: #d9deea; font-weight: bold; text-align: center; color: #111; }
td.center { text-align: center; }
td.number { text-align: right; }
td.bold { font-weight: bold; }
td.text-left { text-align: left; }
tr.report-grand-total-row td { background-color: #ffffff; font-weight: bold; border-top: 2px solid #aeb5c0; }
.location-date { text-align: right; font-style: italic; font-size: 9.5px; margin: 12px 0 10px 0; }
.signature-grid { display: flex; justify-content: space-between; text-align: center; margin-top: 10px; font-size: 9.5px; }
.sig-col { width: 20%; }
CSS;
    }
};
```

---

## 4. Bóc Tách Chi Tiết Các Bảng, View & Function Trong Stored Procedure Gốc (`sp_292`)

Trong file SQL gốc [sp_292_full.sql](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/sql/sp_292_full.sql), Stored Procedure sử dụng một hàm quan trọng là `func_054` cùng với các bảng legacy chính sau:

### 4.1. Phân Tích Hàm Bảng `func_054(@fromDate, @toDate, @registrationID)`
* **Bản chất**: Là một `SQL_TABLE_VALUED_FUNCTION` trong SQL Server (kích thước ~ 35KB, 583 dòng code).
* **Mục đích**: Là hàm bóc tách doanh thu cốt lõi (Core Daily Revenue Engine) của hệ thống legacy, phân bổ doanh thu tiền phòng và tất cả các dịch vụ khách sạn theo từng ngày thực tế (`Date`) mà khách lưu trú/sử dụng.
* **Các bảng legacy nội bộ mà `func_054` truy vấn**:
  1. `SP3004` (Hóa đơn tiền phòng theo ngày): Chứa doanh thu buồng phòng từng đêm (`Date`, `TotalAmount0`, `RateCode`, `IsRoomNight`, `Quantity`).
  2. `SP3000` (Hóa đơn dịch vụ): Chứa các khoản phí dịch vụ ngoài phòng (`ServiceId`, `Date`, `TotalAmount0`, `DepartmentId`).
  3. `SP2100` (Phòng thuê): Liên kết `RentalRoomId`, `BookingId`.
  4. `SP2000` (Đặt phòng): Liên kết mã đăng ký `BookingId`.
  5. `SP2102` (Dịch vụ tự động theo ngày): Khuyến mãi, giảm giá (`Promotion`).
  6. `SP1500` & `SP1600`: Ngày hệ thống (`SystemDate`) và tham số cấu hình dịch vụ ăn sáng trẻ em (`Booking_BFChildSetServiceId`).
* **Bảng kết quả mà `func_054` trả về**:
  | Cột trả về | Kiểu dữ liệu | Ý nghĩa nghiệp vụ |
  |---|---|---|
  | `RentalRoomId` | `varchar(10)` | Mã phòng thuê (khóa ngoại `SP2100`) |
  | `BookingId` | `bigint` | Mã đặt phòng (khóa ngoại `SP2000`) |
  | `BillIdService` | `bigint` | Mã hóa đơn dịch vụ (`SP3000.Ma`) |
  | `ServiceId` | `char(2)` | Mã dịch vụ (`RM`: Tiền phòng, `MB`: Minibar, `LA`: Giặt ủi, `FB`: Nhà hàng, `EB`: Giường phụ, `EP`: Thêm người...) |
  | `RoomRateCode` | `varchar(20)` | Mã giá phòng áp dụng |
  | `Date` | `date` | Ngày phát sinh doanh thu |
  | `Quantity` | `float` | Số lượng dịch vụ |
  | `Total` | `decimal(18,2)` | Số tiền doanh thu của ngày đó |
  | `DepartmentId` | `varchar(5)` | Mã bộ phận (`FO`: Lễ tân, `HK`: Buồng phòng, `FB`: Nhà hàng) |
  | `IsRoomNight` | `smallint` | Cờ đánh dấu có phải đêm phòng hay không |
  | `Promotion` | `varchar(500)` | Thông tin chương trình khuyến mãi |
* **Cách `sp_292` sử dụng `func_054`**:
  * **Lần 1 (Dòng 24 `sp_292_full.sql`)**: Gọi `func_054(@date, @date, '')` để tìm tất cả các booking có phát sinh doanh thu trong ngày báo cáo `@date`.
  * **Lần 2 (Dòng 39 `sp_292_full.sql`)**: Gọi `func_054(@minDate, @date, '')` để lấy chi tiết doanh thu từ ngày khách đến đầu tiên đến ngày `@date`. Từ đó chia làm:
    - **Doanh thu trong ngày (`rev.Date = @date`)**: Tách ra `RoomToday`, `ExtraRoomToday`, `BreakfastSurchargeToday`, `LaundryToday`, `MinibarToday`, `BrokenToday`, `RestaurantToday`, `OtherToday`.
    - **Doanh thu ngày trước (`rev.Date != @date`)**: Gom vào `PrevDay`.

### 4.2. Bảng Đối Chiếu Ánh Xạ Các Bảng Trong `sp_292` Sang Schema MySQL Mới

| Bảng/Hàm Legacy | Mục đích trong `sp_292` | Bảng tương ứng trong MySQL PMS mới | Cột ánh xạ tương đương |
|---|---|---|---|
| `func_054` | Bóc tách doanh thu phòng & dịch vụ theo ngày | `sales_invoices` (kết hợp `booking_room_rates`) | `amount`, `invoice_date`, `outlet`, `department` |
| `sp1322` | Cấu hình tiền tố booking (`PrefixBookingId`) | `hotels` / `system_configs` | Tiền tố hiển thị mã booking |
| `SP2000` | Thông tin đặt phòng chính (`Ma`, `BookingName`, `ArrivalDate`, `NumOfDays`, `TravelAgency`, `Status`) | `bookings` | `id`, `booking_name`, `arrival_date`, `departure_date`, `company_id`, `status` |
| `SP2100` | Thông tin phòng thuê (`BookingId`, `Room`, `Status`, `RentalRoomId`) | `booking_rooms` | `booking_id`, `room_number`, `status` |
| `SP1302` | Danh mục công ty / đại lý OTA (`Ma`, `Company`) | `companies` | `id`, `name` |
| `SP3000` | Hóa đơn dịch vụ & buồng phòng (`RegisterId2`, `RentalRoomId2`, `Date`, `edit`, `Amount`) | `sales_invoices` | `booking_id`, `rental_room_id`, `invoice_date`, `amount`, `status != 'cancelled'` |
| `SP3002` | Giao dịch thanh toán (`PaymentMethod`, `Amount`, `Date`, `edit`) | `payments` kết hợp `payment_methods` | `payment_method_id` $\rightarrow$ `pm.code` (`CA`: TM, `BT`/`CD`: CK, `HH`: Hoa hồng, `AC`: Công nợ) |

---

## 5. Thuật Toán Stored Procedure MySQL 8.0 (`rpt_revenue_army`)

```sql
DELIMITER $$

CREATE PROCEDURE rpt_revenue_army(
    IN p_date VARCHAR(20),
    IN p_company_id VARCHAR(50),
    IN p_booking_id VARCHAR(50)
)
READS SQL DATA
BEGIN
    DECLARE v_report_date DATE;
    DECLARE v_company_id INT DEFAULT 0;
    DECLARE v_booking_id INT DEFAULT 0;

    IF p_date LIKE '%/%' THEN
        SET v_report_date = STR_TO_DATE(LEFT(p_date, 10), '%d/%m/%Y');
    ELSE
        SET v_report_date = CAST(LEFT(p_date, 10) AS DATE);
    END IF;

    IF p_company_id IS NOT NULL AND p_company_id != '' AND p_company_id != '0' THEN
        SET v_company_id = CAST(p_company_id AS UNSIGNED);
    END IF;

    IF p_booking_id IS NOT NULL AND p_booking_id != '' AND p_booking_id != '0' THEN
        SET v_booking_id = CAST(p_booking_id AS UNSIGNED);
    END IF;

    WITH RoomAgg AS (
        SELECT 
            booking_id,
            GROUP_CONCAT(room_number ORDER BY room_number SEPARATOR ', ') AS room_numbers,
            COUNT(id) AS room_count
        FROM booking_rooms
        WHERE status IN (0, 1, 2, 4, 100)
        GROUP BY booking_id
    ),
    EligibleBookings AS (
        SELECT 
            b.id AS booking_id,
            CONCAT(b.booking_name, CASE WHEN COALESCE(ra.room_count, 0) > 0 THEN CONCAT(' - ', ra.room_numbers) ELSE '' END) AS guest_display,
            COALESCE(comp.name, comp.trading_name, 'KHÁCH LẺ') AS company_name,
            b.arrival_date,
            b.departure_date,
            COALESCE(ra.room_count, 0) AS room_count
        FROM bookings b
        LEFT JOIN RoomAgg ra ON ra.booking_id = b.id
        LEFT JOIN companies comp ON comp.id = b.company_id
        WHERE b.status != 3
          AND (
              (v_report_date BETWEEN b.arrival_date AND b.departure_date)
              OR EXISTS (
                  SELECT 1 FROM sales_invoices si 
                  WHERE si.booking_id = b.id 
                    AND CAST(si.invoice_date AS DATE) = v_report_date
                    AND si.status != 'cancelled'
              )
          )
          AND (v_company_id = 0 OR b.company_id = v_company_id)
          AND (v_booking_id = 0 OR b.id = v_booking_id)
    ),
    RevDetails AS (
        SELECT 
            si.booking_id,
            SUM(CASE WHEN si.outlet = 'RM' AND CAST(si.invoice_date AS DATE) = v_report_date THEN si.amount ELSE 0 END) AS room_today,
            SUM(CASE WHEN si.outlet IN ('EB', 'EP', 'ER', 'KC', 'UP', 'EI', 'LO', 'BD', 'BF') AND CAST(si.invoice_date AS DATE) = v_report_date THEN si.amount ELSE 0 END) AS extra_room_today,
            SUM(CASE WHEN si.outlet = 'MB' AND CAST(si.invoice_date AS DATE) = v_report_date THEN si.amount ELSE 0 END) AS minibar_today,
            SUM(CASE WHEN si.outlet = 'LA' AND CAST(si.invoice_date AS DATE) = v_report_date THEN si.amount ELSE 0 END) AS laundry_today,
            SUM(CASE WHEN si.outlet IN ('BR', 'BK') AND CAST(si.invoice_date AS DATE) = v_report_date THEN si.amount ELSE 0 END) AS broken_today,
            SUM(CASE WHEN si.outlet = 'FB' AND CAST(si.invoice_date AS DATE) = v_report_date THEN si.amount ELSE 0 END) AS restaurant_today,
            SUM(CASE WHEN si.outlet NOT IN ('RM', 'EB', 'EP', 'ER', 'KC', 'UP', 'EI', 'LO', 'BD', 'BF', 'MB', 'LA', 'BR', 'BK', 'FB') AND CAST(si.invoice_date AS DATE) = v_report_date THEN si.amount ELSE 0 END) AS other_today,
            SUM(CASE WHEN CAST(si.invoice_date AS DATE) < v_report_date THEN si.amount ELSE 0 END) AS prev_day_revenue
        FROM sales_invoices si
        WHERE si.booking_id IN (SELECT booking_id FROM EligibleBookings)
          AND si.status != 'cancelled'
        GROUP BY si.booking_id
    ),
    PayDetails AS (
        SELECT 
            p.booking_id,
            SUM(CASE WHEN pm.code = 'CA' OR p.payment_method_id = 1 THEN p.amount ELSE 0 END) AS cash_paid,
            SUM(CASE WHEN pm.code IN ('BT', 'CD') OR p.payment_method_id IN (2, 3) THEN p.amount ELSE 0 END) AS bank_transfer_paid,
            SUM(CASE WHEN pm.code = 'HH' THEN p.amount ELSE 0 END) AS commission_paid,
            SUM(CASE WHEN pm.code = 'AC' OR p.payment_method_id = 4 THEN p.amount ELSE 0 END) AS city_ledger_paid
        FROM payments p
        LEFT JOIN payment_methods pm ON pm.id = p.payment_method_id
        WHERE p.booking_id IN (SELECT booking_id FROM EligibleBookings)
          AND CAST(p.date AS DATE) <= v_report_date
          AND COALESCE(p.edit_flag, 0) = 0
        GROUP BY p.booking_id
    )
    SELECT 
        ROW_NUMBER() OVER (ORDER BY eb.arrival_date ASC, eb.booking_id ASC) AS `Index`,
        eb.booking_id AS BookingId,
        eb.guest_display AS GuestName,
        eb.company_name AS BusinessName,
        DATE_FORMAT(eb.arrival_date, '%d/%m/%Y') AS ArrivalDate,
        DATE_FORMAT(eb.departure_date, '%d/%m/%Y') AS DepartureDate,
        eb.room_count AS RoomCount,
        ROUND(COALESCE(rd.room_today, 0), 0) AS RoomToday,
        ROUND(COALESCE(rd.extra_room_today, 0), 0) AS ExtraRoomToday,
        ROUND(COALESCE(rd.minibar_today, 0), 0) AS MinibarToday,
        ROUND(COALESCE(rd.laundry_today, 0), 0) AS LaundryToday,
        ROUND(COALESCE(rd.broken_today, 0), 0) AS BrokenToday,
        ROUND(COALESCE(rd.restaurant_today, 0), 0) AS RestaurantToday,
        ROUND(COALESCE(rd.other_today, 0), 0) AS OtherToday,
        ROUND(COALESCE(rd.room_today, 0) + COALESCE(rd.extra_room_today, 0) + COALESCE(rd.minibar_today, 0) + COALESCE(rd.laundry_today, 0) + COALESCE(rd.broken_today, 0) + COALESCE(rd.restaurant_today, 0) + COALESCE(rd.other_today, 0), 0) AS TotalToday,
        ROUND(COALESCE(rd.prev_day_revenue, 0), 0) AS PrevDay,
        ROUND(COALESCE(rd.room_today, 0) + COALESCE(rd.extra_room_today, 0) + COALESCE(rd.minibar_today, 0) + COALESCE(rd.laundry_today, 0) + COALESCE(rd.broken_today, 0) + COALESCE(rd.restaurant_today, 0) + COALESCE(rd.other_today, 0) + COALESCE(rd.prev_day_revenue, 0), 0) AS TotalRevenue,
        ROUND(COALESCE(pd.cash_paid, 0), 0) AS Cash,
        ROUND(COALESCE(pd.bank_transfer_paid, 0), 0) AS BankTransfer,
        ROUND(COALESCE(pd.commission_paid, 0), 0) AS Commission,
        ROUND(COALESCE(pd.city_ledger_paid, 0), 0) AS CityLedger,
        GREATEST(0, ROUND((COALESCE(rd.room_today, 0) + COALESCE(rd.extra_room_today, 0) + COALESCE(rd.minibar_today, 0) + COALESCE(rd.laundry_today, 0) + COALESCE(rd.broken_today, 0) + COALESCE(rd.restaurant_today, 0) + COALESCE(rd.other_today, 0) + COALESCE(rd.prev_day_revenue, 0)) - (COALESCE(pd.cash_paid, 0) + COALESCE(pd.bank_transfer_paid, 0) + COALESCE(pd.commission_paid, 0) + COALESCE(pd.city_ledger_paid, 0)), 0)) AS InhouseRoom
    FROM EligibleBookings eb
    LEFT JOIN RevDetails rd ON rd.booking_id = eb.booking_id
    LEFT JOIN PayDetails pd ON pd.booking_id = eb.booking_id
    WHERE eb.room_count > 0 
      AND (
          COALESCE(rd.room_today, 0) + COALESCE(rd.extra_room_today, 0) + COALESCE(rd.minibar_today, 0) + 
          COALESCE(rd.laundry_today, 0) + COALESCE(rd.broken_today, 0) + COALESCE(rd.restaurant_today, 0) + 
          COALESCE(rd.other_today, 0) + COALESCE(rd.prev_day_revenue, 0)
      ) != 0
    ORDER BY eb.arrival_date ASC, eb.booking_id ASC;
END$$

DELIMITER ;
```

---

## 5. Hướng Dẫn Các Bước Triển Khai Cho Agent

1. **Tạo file template reference**: Tạo `backend/database/report_templates/revenue_army_reference.php` với đúng nội dung mã PHP ở Mục 3.
2. **Tạo migration tổng hợp**: Tạo migration `backend/database/migrations/2026_09_22_100000_create_revenue_army_report.php` áp dụng Stored Procedure trên 5 kết nối chi nhánh `['mysql', 'mysql_hkt1', 'mysql_hkt2', 'mysql_hkt3', 'mysql_hkt4']` và seed `report_data_sources`, `templates`, `report_definitions`, `report_definition_template`.
3. **Chạy migrate**: `php artisan migrate:all --force`.
4. **Viết test case**: `backend/tests/Feature/RevenueArmyReportTest.php` kiểm tra Stored Procedure và API report.
5. **Kiểm tra**: Chạy `php artisan test --filter=RevenueArmyReportTest`.
