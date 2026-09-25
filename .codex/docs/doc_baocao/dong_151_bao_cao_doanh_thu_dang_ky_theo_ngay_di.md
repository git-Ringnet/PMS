# Đặc Tả Kỹ Thuật: Báo Cáo Doanh Thu Đăng Ký Theo Ngày Đi (Dòng 151)

> **Tài liệu chuẩn bị cho Agent triển khai tiếp theo**  
> Dựa trên phân tích từ file Excel `DANH MỤC BÁO CÁO.xlsx` (Dòng 151, Sheet 67 `BC DT theo ngày đi`), ảnh giao diện thực tế `[.codex/docs/doc_baocao/images/dong_151_ui_mau.png](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/images/dong_151_ui_mau.png)` và Stored Procedure gốc trên MS SQL Server:
> - `sp_238`: Xem chi tiết theo từng phòng ([sp_238_full.sql](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/sql/sp_238_full.sql))
> - `sp_240`: Xem nhóm theo đăng ký/booking ([sp_240_full.sql](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/sql/sp_240_full.sql))

---

## 1. Thông Tin Chung & Định Danh

* **Tên báo cáo tiếng Việt**: Báo cáo doanh thu đăng ký theo ngày đi
* **Mã báo cáo (`code`)**: `REVENUE_BY_DEPARTURE_DATE`
* **Mã nguồn dữ liệu (`data_source_code`)**: `RPT_REVENUE_BY_DEPARTURE_DATE`
* **Mã template (`template_code`)**: `REVENUE_BY_DEPARTURE_DATE_REFERENCE`
* **Nhóm báo cáo (`group`)**: `Báo cáo doanh thu`
* **Menu hiển thị**: `['frontdesk', 'cashier', 'report']`
* **Vị trí trong Excel danh mục**: Dòng 151 (STT 5.0)
* **Ghi chú trong Excel**: "Lưu ý mã dịch vụ và cột hiển thị có thay đổi theo đơn vị. sp_238 (xem chi tiết phòng), sp_240 (nhóm theo đăng ký)"
* **Sheet tham chiếu trong Excel**: Sheet 67 (`BC DT theo ngày đi`)
* **Ảnh chụp màn hình thực tế**: `[.codex/docs/doc_baocao/images/dong_151_ui_mau.png](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/images/dong_151_ui_mau.png)`
* **File SQL legacy tham chiếu**:
  * [sp_238_full.sql](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/sql/sp_238_full.sql) (Chế độ xem chi tiết từng phòng)
  * [sp_240_full.sql](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/sql/sp_240_full.sql) (Chế độ xem nhóm theo đăng ký)

---

## 2. Phân Tích Chi Tiết Giao Diện Từ Ảnh Thực Tế (`dong_151_ui_mau.png`)

### 2.1. Panel Bộ Lọc Bên Trái (Left Filter Panel)
* **Chiều rộng panel**: ~ 260px - 280px.
* **Các thành phần điều khiển**:
  1. **Ngày & Giờ (`p_from_date`, `p_to_date`, `p_from_time`, `p_to_time`)**:
     - Khoảng thời gian đầy đủ gồm ngày và giờ:
       - Từ: `15 / 07 / 2026  00:00`
       - Đến: `15 / 07 / 2026  23:59`
  2. **Chọn công ty (`p_company_id`)**:
     - Dropdown Select, placeholder: `Công ty`, nguồn: danh mục `companies`. Mặc định: Tất cả (`0`).
  3. **Nhóm theo đăng ký (`p_group_by_booking`)**:
     - Toggle Switch (Bật/Tắt).
     - **Khi Tắt (Mặc định)**: Báo cáo xem chi tiết từng phòng theo `sp_238`. Mã ĐK 262 tách thành các dòng phòng riêng biệt (803, 804).
     - **Khi Bật**: Báo cáo xem nhóm theo đăng ký theo `sp_240`. Mỗi Mã ĐK chỉ xuất hiện đúng 1 dòng tổng hợp.
  4. **Nút thực thi**:
     - Button `Hiển thị báo cáo`: Màu xanh biển `#3b82f6`, chữ trắng, bo góc 4px.

---

## 3. Cấu Hình Form Designer & Thông Số Thiết Kế Chi Tiết

### 3.1. Thiết Lập Trang In (Page Settings)
* `page_size`: `'A4'`
* `page_orientation`: `'landscape'` (Khổ ngang - bảng 24 cột)
* `margin_top`: `6` (mm)
* `margin_bottom`: `6` (mm)
* `margin_left`: `4` (mm)
* `margin_right`: `4` (mm)

### 3.2. Bảng Màu & Typography (Design Tokens)
* **Font chữ chính**: `Segoe UI, Arial, sans-serif`
* **Màu chữ chính**: `#111111`
* **Màu viền bảng**: `#aeb5c0` (1px solid)
* **Màu nền Header bảng**: `#d9deea`
* **Cỡ chữ**:
  * Tiêu đề chính (`H1`): `16px`, `font-weight: bold`, `text-align: center`, `color: #111111`
  * Tiêu đề phụ (Ngày áp dụng): `9.5px`, `font-weight: bold`, `text-align: center`
  * Toàn bộ bảng dữ liệu: `8px` (để 24 cột hiển thị sắc nét không bị vỡ dòng)
* **Padding ô bảng**: `2.5px 2px`

---

## 4. Chi Tiết Layout Bảng Dữ Liệu (24 Cột - Header 3 Tầng)

### 4.1. Ma Trận Header 3 Tầng

```text
+-----+-----+-------+---------+-----+-----+---------------------------------------------------------------+-----------------------------------+-----------------------------------+-------+-------+----------+
|     |     |       |         |     |     |                              FO                               |           Doanh Thu HK            |            F&B Revenue            |       |       |          |
|     |     |       |         |     |     +-------+-------+-------+-------+----+-------+-------+----------+-------+-------+-------+-----------+-----------------------------------+ Doanh | Tổng  | Hình     |
| Mã  |Phòng| Công  |   Tên   |Ngày |Ngày | Tiền  |PT Thêm|PT Thêm|PT Tiền| Vé | Đưa   |  DT   | Minibar  | Giặt  | Bể    |AS Từ  |            Nhà Hàng               |  Thu  | Doanh |  Thức    |
| ĐK  |     |  Ty   |  Khách  | Đến | Đi  | Phòng |Giường | Người | Phòng |    | Đón Kh| Khác  |          |  Ủi   |  Vỡ   |Tiền Ph+-----------+-------+-------+-------+ Khác  |  Thu  |Thanh Toán|
|     |     |       |         |     |     |       |       |       |       |    |       |       |          |       |       |       |  Phí AS   |Thức Ăn|Đồ Uống| Khác  |       |       |          |
+-----+-----+-------+---------+-----+-----+-------+-------+-------+-------+----+-------+-------+----------+-------+-------+-------+-----------+-------+-------+-------+-------+-------+----------+
```

### 4.2. Chi Tiết Thuộc Tính Từng Cột

| STT | Tên cột hiển thị | Field Name | Width | Align | Format | Định nghĩa dịch vụ legacy |
|---|---|---|---|---|---|---|
| 1 | `Mã ĐK` | `CodeBooking` | `3.5%`| Center | Text | Mã đặt phòng |
| 2 | `Phòng` | `RoomNumber` | `3%` | Center | Text | Số phòng (để trống nếu nhóm theo ĐK) |
| 3 | `Công Ty` | `Company` | `7%` | Left | Text | Tên đơn vị / OTA / Công ty |
| 4 | `Tên Khách` | `GuestName` | `9.5%`| Left | Text | Họ tên khách chính |
| 5 | `Ngày Đến` | `Arrival` | `4.5%`| Center | `dd/mm/yyyy` | Ngày đến |
| 6 | `Ngày Đi` | `Departure` | `4.5%`| Center | `dd/mm/yyyy` | Ngày đi |
| 7 | `Tiền Phòng` | `RoomCharge` | `4.5%`| Right | Tiền tệ | Tiền phòng lưu trú (`RM`) |
| 8 | `PT Thêm Giường` | `ExtraBed` | `3.5%`| Right | Tiền tệ | Kê thêm giường phụ (`EB`) |
| 9 | `PT Thêm Người` | `ExtraPerson`| `3.5%`| Right | Tiền tệ | Phụ thu thêm người (`EP`) |
| 10 | `PT Tiền Phòng` | `ExtraRoomCharge`|`3.5%`| Right | Tiền tệ | Nhận sớm / Trả trễ (`EI`, `LO`, `ER`) |
| 11 | `Vé` | `Tour` | `3%` | Right | Tiền tệ | Vé tham quan / tour (`TO`) |
| 12 | `Đưa Đón Khách` | `Transportation`|`3.5%`| Right | Tiền tệ | Dịch vụ xe đưa đón sân bay (`PU`, `DO`) |
| 13 | `DT Khác` (FO) | `Miscel` | `3.5%`| Right | Tiền tệ | Phụ thu khác của FO |
| 14 | `Minibar` | `Minibar` | `3.5%`| Right | Tiền tệ | Đồ uống buồng phòng (`MB`) |
| 15 | `Giặt Ủi` | `Laundry` | `3.5%`| Right | Tiền tệ | Dịch vụ giặt ủi (`LA`) |
| 16 | `Bể Vỡ` | `Broken` | `3%` | Right | Tiền tệ | Đền bù tài sản bể vỡ (`BR`, `BK`) |
| 17 | `AS Từ Tiền Phòng`| `BreakfastSplitdown`|`4%`| Right | Tiền tệ | Ăn sáng trích từ tiền phòng (`BF` pack) |
| 18 | `Phí AS` | `BreakfastCharge`|`3.5%`| Right | Tiền tệ | Ăn sáng thu thêm ngoài gói (`BF`, `BC`) |
| 19 | `Thức Ăn` | `Food_Res` | `4%` | Right | Tiền tệ | Món ăn nhà hàng (`RF` tại Outlet `RE`) |
| 20 | `Đồ Uống` | `Beverage_Res`|`3.5%`| Right | Tiền tệ | Thức uống nhà hàng (`RB`, `PC`) |
| 21 | `Khác` (F&B) | `Other_Res` | `3%` | Right | Tiền tệ | Doanh thu F&B khác |
| 22 | `Doanh Thu Khác` | `OtherRevenue`| `3%` | Right | Tiền tệ | Các outlet dịch vụ khác |
| 23 | `Tổng Doanh Thu` | `TotalRevenue`| `5%` | Right | Tiền tệ | Tổng cộng từ Cột 7 đến Cột 22 |
| 24 | `Hình Thức Thanh Toán`| `PaymentMethod`|`4.5%`| Center | Text | Chuỗi HTTT (vd: `CA`, `BT`, `AC`) |

### 4.3. Dòng Tổng Cộng (Total Row)
* **Vị trí**: Nằm trong thẻ `<tfoot>`.
* **Cột 1 đến Cột 6 (colspan 6)**: Nhãn `"Total"` (In đậm, căn giữa, nền `#ffffff`).
* **Cột 7 đến Cột 23**: Tổng tiền cộng dồn của từng cột tương ứng (In đậm, căn phải). Cột 23 thể hiện Tổng Doanh Thu Toàn Báo Cáo.
* **Cột 24**: Ô trống.

---

### 4.4. Cấu Trúc File Template Reference Chuẩn (`revenue_by_departure_date_reference.php`)

> **Toàn bộ cấu hình `content_json`** (gồm blocks `header`, dynamic `table` với `topHeader`, `columns`, `customRows` Grand Total `aggregate.rows.sum.*`, và `footer` chữ ký) được định nghĩa đầy đủ 100% bên dưới. Agent tiếp theo chỉ việc copy vào `backend/database/report_templates/revenue_by_departure_date_reference.php`.

```php
<?php

namespace Database\ReportTemplates;

class RevenueByDepartureDateReference
{
    public function definition(): array
    {
        return [
            'code' => 'REVENUE_BY_DEPARTURE_DATE',
            'name' => 'Báo cáo doanh thu đăng ký theo ngày đi',
            'group' => 'Báo cáo doanh thu',
            'menu' => ['frontdesk', 'cashier', 'report'],
            'description' => 'Báo cáo tổng hợp doanh thu theo từng đặt phòng hoặc theo phòng dựa trên ngày giờ trả phòng thực tế',
            'data_source_code' => 'RPT_REVENUE_BY_DEPARTURE_DATE',
            'page_size' => 'A4',
            'page_orientation' => 'landscape',
            'margin_top' => 6,
            'margin_bottom' => 6,
            'margin_left' => 4,
            'margin_right' => 4,
        ];
    }

    public function columns(): array
    {
        return [
            ['id' => 'code_booking', 'field' => 'CodeBooking', 'label' => 'Mã ĐK', 'width' => '3.5%', 'align' => 'center', 'format' => 'text'],
            ['id' => 'room_number', 'field' => 'RoomNumber', 'label' => 'Phòng', 'width' => '3%', 'align' => 'center', 'format' => 'text'],
            ['id' => 'company', 'field' => 'Company', 'label' => 'Công Ty', 'width' => '7%', 'align' => 'left', 'format' => 'text'],
            ['id' => 'guest_name', 'field' => 'GuestName', 'label' => 'Tên Khách', 'width' => '9%', 'align' => 'left', 'format' => 'text'],
            ['id' => 'arrival', 'field' => 'Arrival', 'label' => 'Ngày Đến', 'width' => '4%', 'align' => 'center', 'format' => 'text'],
            ['id' => 'departure', 'field' => 'Departure', 'label' => 'Ngày Đi', 'width' => '4%', 'align' => 'center', 'format' => 'text'],
            // Nhóm FO
            ['id' => 'room_charge', 'field' => 'RoomCharge', 'label' => 'Tiền Phòng', 'width' => '4.5%', 'align' => 'right', 'format' => 'number'],
            ['id' => 'extra_bed', 'field' => 'ExtraBed', 'label' => 'PT Thêm Giường', 'width' => '3.5%', 'align' => 'right', 'format' => 'number'],
            ['id' => 'extra_person', 'field' => 'ExtraPerson', 'label' => 'PT Thêm Người', 'width' => '3.5%', 'align' => 'right', 'format' => 'number'],
            ['id' => 'extra_room', 'field' => 'ExtraRoomCharge', 'label' => 'PT Tiền Phòng', 'width' => '3.5%', 'align' => 'right', 'format' => 'number'],
            ['id' => 'tour', 'field' => 'Tour', 'label' => 'Vé', 'width' => '3%', 'align' => 'right', 'format' => 'number'],
            ['id' => 'transportation', 'field' => 'Transportation', 'label' => 'Đưa Đón Khách', 'width' => '3.5%', 'align' => 'right', 'format' => 'number'],
            ['id' => 'miscel', 'field' => 'Miscel', 'label' => 'DT Khác', 'width' => '3.5%', 'align' => 'right', 'format' => 'number'],
            // Nhóm HK
            ['id' => 'minibar', 'field' => 'Minibar', 'label' => 'Minibar', 'width' => '3.5%', 'align' => 'right', 'format' => 'number'],
            ['id' => 'laundry', 'field' => 'Laundry', 'label' => 'Giặt Ủi', 'width' => '3.5%', 'align' => 'right', 'format' => 'number'],
            ['id' => 'broken', 'field' => 'Broken', 'label' => 'Bể Vỡ', 'width' => '3%', 'align' => 'right', 'format' => 'number'],
            ['id' => 'bf_split', 'field' => 'BreakfastSplitdown', 'label' => 'AS Từ Tiền Phòng', 'width' => '4%', 'align' => 'right', 'format' => 'number'],
            // Nhóm F&B
            ['id' => 'bf_charge', 'field' => 'BreakfastCharge', 'label' => 'Phí AS', 'width' => '3.5%', 'align' => 'right', 'format' => 'number'],
            ['id' => 'food_res', 'field' => 'Food_Res', 'label' => 'Thức Ăn', 'width' => '4%', 'align' => 'right', 'format' => 'number'],
            ['id' => 'bev_res', 'field' => 'Beverage_Res', 'label' => 'Đồ Uống', 'width' => '3.5%', 'align' => 'right', 'format' => 'number'],
            ['id' => 'other_res', 'field' => 'Other_Res', 'label' => 'Khác', 'width' => '3%', 'align' => 'right', 'format' => 'number'],
            // Khác & Tổng
            ['id' => 'other_rev', 'field' => 'OtherRevenue', 'label' => 'Doanh Thu Khác', 'width' => '3%', 'align' => 'right', 'format' => 'number'],
            ['id' => 'total_rev', 'field' => 'TotalRevenue', 'label' => 'Tổng Doanh Thu', 'width' => '5%', 'align' => 'right', 'format' => 'number'],
            ['id' => 'payment_method', 'field' => 'PaymentMethod', 'label' => 'Hình Thức Thanh Toán', 'width' => '4.5%', 'align' => 'center', 'format' => 'text'],
        ];
    }

    public function blocks(): array
    {
        return [
            'header' => [
                [
                    'id' => 'header_band',
                    'type' => 'columns',
                    'style' => [
                        'display' => 'flex',
                        'justifyContent' => 'space-between',
                        'alignItems' => 'flex-start',
                        'marginBottom' => '6px',
                    ],
                    'columns' => [
                        [
                            'width' => '35%',
                            'blocks' => [[
                                'id' => 'hotel_logo',
                                'type' => 'text',
                                'content' => '<div class="hotel-logo" style="min-height: 48px; font-weight: bold; font-size: 13px;">{{hotel.name}}<br><span style="font-size: 10px; font-weight: normal;">{{hotel.address}}</span></div>',
                                'style' => ['fontSize' => '11px'],
                            ]],
                        ],
                        [
                            'width' => '65%',
                            'blocks' => [[
                                'id' => 'hotel_info',
                                'type' => 'text',
                                'content' => '<div class="hotel-information" style="text-align: right; font-size: 10px; line-height: 1.4;">'
                                    .'<div><b>Nhân viên:</b> {{report.generated_by}} &nbsp;&nbsp; <b>Ngày in:</b> {{report.generated_at}}</div>'
                                    .'</div>',
                                'style' => ['textAlign' => 'right'],
                            ]],
                        ],
                    ],
                ],
                [
                    'id' => 'report_title',
                    'type' => 'text',
                    'content' => '<h1 style="text-align: center; font-size: 16px; font-weight: bold; margin: 2px 0 4px 0; text-transform: uppercase;">BÁO CÁO DOANH THU ĐĂNG KÝ THEO NGÀY ĐI</h1>',
                    'style' => ['textAlign' => 'center', 'fontWeight' => 'bold'],
                ],
                [
                    'id' => 'report_period',
                    'type' => 'text',
                    'content' => '<p style="text-align: center; font-size: 9.5px; font-weight: bold; margin: 0 0 8px 0;">Từ ngày {{parameters.p_from_date}} {{parameters.p_from_time}} đến ngày {{parameters.p_to_date}} {{parameters.p_to_time}}</p>',
                    'style' => ['textAlign' => 'center', 'fontSize' => '9.5px', 'marginBottom' => '8px'],
                ],
            ],
            'detail' => [[
                'id' => 'revenue_by_departure_date_table',
                'type' => 'table',
                'dataSource' => 'rows',
                'tableType' => 'dynamic',
                'tableClassName' => 'revenue-departure-table',
                'style' => [
                    'width' => '100%',
                    'fontSize' => '8px',
                    'borderCollapse' => 'collapse',
                    'borderColor' => '#aeb5c0',
                    'borderWidth' => '1px',
                    'borderStyle' => 'solid',
                    'backgroundColor' => '#ffffff',
                ],
                'hasTwoTierHeader' => true,
                'topHeader' => [
                    ['label' => 'Mã ĐK', 'rowspan' => 2, 'width' => '3.5%', 'align' => 'center'],
                    ['label' => 'Phòng', 'rowspan' => 2, 'width' => '3%', 'align' => 'center'],
                    ['label' => 'Công Ty', 'rowspan' => 2, 'width' => '7%', 'align' => 'center'],
                    ['label' => 'Tên Khách', 'rowspan' => 2, 'width' => '9%', 'align' => 'center'],
                    ['label' => 'Ngày Đến', 'rowspan' => 2, 'width' => '4%', 'align' => 'center'],
                    ['label' => 'Ngày Đi', 'rowspan' => 2, 'width' => '4%', 'align' => 'center'],
                    ['label' => 'FO', 'colspan' => 7, 'align' => 'center', 'style' => ['backgroundColor' => '#d9deea', 'fontWeight' => 'bold']],
                    ['label' => 'Doanh Thu HK', 'colspan' => 4, 'align' => 'center', 'style' => ['backgroundColor' => '#d9deea', 'fontWeight' => 'bold']],
                    ['label' => 'F&B Revenue', 'colspan' => 4, 'align' => 'center', 'style' => ['backgroundColor' => '#d9deea', 'fontWeight' => 'bold']],
                    ['label' => 'Doanh Thu Khác', 'rowspan' => 2, 'width' => '3%', 'align' => 'center'],
                    ['label' => 'Tổng Doanh Thu', 'rowspan' => 2, 'width' => '5%', 'align' => 'center'],
                    ['label' => 'Hình Thức Thanh Toán', 'rowspan' => 2, 'width' => '4.5%', 'align' => 'center'],
                ],
                'columns' => $this->columns(),
                'customRows' => [
                    [
                        'id' => 'revenue_departure_grand_total_row',
                        'enabledBy' => '',
                        'scope' => 'table',
                        'level' => 0,
                        'className' => 'report-grand-total-row',
                        'cells' => [
                            [
                                'id' => 'total_label',
                                'type' => 'text',
                                'content' => 'Total',
                                'colspan' => 6,
                                'align' => 'center',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'center', 'backgroundColor' => '#ffffff', 'padding' => '3px 2px'],
                            ],
                            [
                                'id' => 'total_room_charge',
                                'type' => 'binding',
                                'binding' => 'aggregate.rows.sum.RoomCharge',
                                'colspan' => 1,
                                'align' => 'right',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'right', 'backgroundColor' => '#ffffff', 'padding' => '3px 2px'],
                            ],
                            [
                                'id' => 'total_extra_bed',
                                'type' => 'binding',
                                'binding' => 'aggregate.rows.sum.ExtraBed',
                                'colspan' => 1,
                                'align' => 'right',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'right', 'backgroundColor' => '#ffffff', 'padding' => '3px 2px'],
                            ],
                            [
                                'id' => 'total_extra_person',
                                'type' => 'binding',
                                'binding' => 'aggregate.rows.sum.ExtraPerson',
                                'colspan' => 1,
                                'align' => 'right',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'right', 'backgroundColor' => '#ffffff', 'padding' => '3px 2px'],
                            ],
                            [
                                'id' => 'total_extra_room',
                                'type' => 'binding',
                                'binding' => 'aggregate.rows.sum.ExtraRoomCharge',
                                'colspan' => 1,
                                'align' => 'right',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'right', 'backgroundColor' => '#ffffff', 'padding' => '3px 2px'],
                            ],
                            [
                                'id' => 'total_tour',
                                'type' => 'binding',
                                'binding' => 'aggregate.rows.sum.Tour',
                                'colspan' => 1,
                                'align' => 'right',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'right', 'backgroundColor' => '#ffffff', 'padding' => '3px 2px'],
                            ],
                            [
                                'id' => 'total_trans',
                                'type' => 'binding',
                                'binding' => 'aggregate.rows.sum.Transportation',
                                'colspan' => 1,
                                'align' => 'right',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'right', 'backgroundColor' => '#ffffff', 'padding' => '3px 2px'],
                            ],
                            [
                                'id' => 'total_miscel',
                                'type' => 'binding',
                                'binding' => 'aggregate.rows.sum.Miscel',
                                'colspan' => 1,
                                'align' => 'right',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'right', 'backgroundColor' => '#ffffff', 'padding' => '3px 2px'],
                            ],
                            [
                                'id' => 'total_minibar',
                                'type' => 'binding',
                                'binding' => 'aggregate.rows.sum.Minibar',
                                'colspan' => 1,
                                'align' => 'right',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'right', 'backgroundColor' => '#ffffff', 'padding' => '3px 2px'],
                            ],
                            [
                                'id' => 'total_laundry',
                                'type' => 'binding',
                                'binding' => 'aggregate.rows.sum.Laundry',
                                'colspan' => 1,
                                'align' => 'right',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'right', 'backgroundColor' => '#ffffff', 'padding' => '3px 2px'],
                            ],
                            [
                                'id' => 'total_broken',
                                'type' => 'binding',
                                'binding' => 'aggregate.rows.sum.Broken',
                                'colspan' => 1,
                                'align' => 'right',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'right', 'backgroundColor' => '#ffffff', 'padding' => '3px 2px'],
                            ],
                            [
                                'id' => 'total_bf_split',
                                'type' => 'binding',
                                'binding' => 'aggregate.rows.sum.BreakfastSplitdown',
                                'colspan' => 1,
                                'align' => 'right',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'right', 'backgroundColor' => '#ffffff', 'padding' => '3px 2px'],
                            ],
                            [
                                'id' => 'total_bf_charge',
                                'type' => 'binding',
                                'binding' => 'aggregate.rows.sum.BreakfastCharge',
                                'colspan' => 1,
                                'align' => 'right',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'right', 'backgroundColor' => '#ffffff', 'padding' => '3px 2px'],
                            ],
                            [
                                'id' => 'total_food_res',
                                'type' => 'binding',
                                'binding' => 'aggregate.rows.sum.Food_Res',
                                'colspan' => 1,
                                'align' => 'right',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'right', 'backgroundColor' => '#ffffff', 'padding' => '3px 2px'],
                            ],
                            [
                                'id' => 'total_bev_res',
                                'type' => 'binding',
                                'binding' => 'aggregate.rows.sum.Beverage_Res',
                                'colspan' => 1,
                                'align' => 'right',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'right', 'backgroundColor' => '#ffffff', 'padding' => '3px 2px'],
                            ],
                            [
                                'id' => 'total_other_res',
                                'type' => 'binding',
                                'binding' => 'aggregate.rows.sum.Other_Res',
                                'colspan' => 1,
                                'align' => 'right',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'right', 'backgroundColor' => '#ffffff', 'padding' => '3px 2px'],
                            ],
                            [
                                'id' => 'total_other_rev',
                                'type' => 'binding',
                                'binding' => 'aggregate.rows.sum.OtherRevenue',
                                'colspan' => 1,
                                'align' => 'right',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'right', 'backgroundColor' => '#ffffff', 'padding' => '3px 2px'],
                            ],
                            [
                                'id' => 'total_revenue',
                                'type' => 'binding',
                                'binding' => 'aggregate.rows.sum.TotalRevenue',
                                'colspan' => 1,
                                'align' => 'right',
                                'format' => 'number',
                                'style' => ['fontWeight' => 'bold', 'textAlign' => 'right', 'backgroundColor' => '#ffffff', 'padding' => '3px 2px'],
                            ],
                            [
                                'id' => 'total_payment_empty',
                                'type' => 'text',
                                'content' => '',
                                'colspan' => 1,
                                'align' => 'center',
                                'style' => ['backgroundColor' => '#ffffff', 'padding' => '3px 2px'],
                            ],
                        ],
                    ],
                ],
            ]],
            'footer' => [
                [
                    'id' => 'footer_signatures',
                    'type' => 'columns',
                    'style' => [
                        'display' => 'flex',
                        'justifyContent' => 'space-between',
                        'marginTop' => '24px',
                        'pageBreakInside' => 'avoid',
                    ],
                    'columns' => [
                        [
                            'width' => '33%',
                            'blocks' => [[
                                'id' => 'sig_maker',
                                'type' => 'text',
                                'content' => '<div style="text-align: center; font-size: 10px;"><b>NGƯỜI LẬP BIỂU</b><br><span style="font-style: italic; font-size: 9px;">(Ký, họ tên)</span><br><br><br><br></div>',
                            ]],
                        ],
                        [
                            'width' => '33%',
                            'blocks' => [[
                                'id' => 'sig_accountant',
                                'type' => 'text',
                                'content' => '<div style="text-align: center; font-size: 10px;"><b>KẾ TOÁN TRƯỞNG</b><br><span style="font-style: italic; font-size: 9px;">(Ký, họ tên)</span><br><br><br><br></div>',
                            ]],
                        ],
                        [
                            'width' => '33%',
                            'blocks' => [[
                                'id' => 'sig_manager',
                                'type' => 'text',
                                'content' => '<div style="text-align: center; font-size: 10px;"><b>GIÁM ĐỐC</b><br><span style="font-style: italic; font-size: 9px;">(Ký, họ tên, đóng dấu)</span><br><br><br><br></div>',
                            ]],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function html(): string
    {
        return <<<'HTML'
<div class="report-wrapper landscape-a4">
    <div class="report-header">
        <div class="header-band">
            <div class="hotel-info-left">
                <strong>{{hotel.name}}</strong><br>
                <span>{{hotel.address}}</span>
            </div>
            <div class="hotel-info-right">
                <div><strong>Nhân viên:</strong> {{report.generated_by}}</div>
                <div><strong>Ngày in:</strong> {{report.generated_at}}</div>
            </div>
        </div>
        <h1 class="report-title">BÁO CÁO DOANH THU ĐĂNG KÝ THEO NGÀY ĐI</h1>
        <div class="report-subtitle">Từ ngày {{parameters.p_from_date}} {{parameters.p_from_time}} đến ngày {{parameters.p_to_date}} {{parameters.p_to_time}}</div>
    </div>

    <table class="report-table revenue-departure-table">
        <thead>
            <tr class="super-header">
                <th rowspan="3" class="w-code">Mã ĐK</th>
                <th rowspan="3" class="w-room">Phòng</th>
                <th rowspan="3" class="w-company">Công Ty</th>
                <th rowspan="3" class="w-guest">Tên Khách</th>
                <th rowspan="3" class="w-date">Ngày Đến</th>
                <th rowspan="3" class="w-date">Ngày Đi</th>
                <th colspan="7" class="group-header">FO</th>
                <th colspan="4" class="group-header">Doanh Thu HK</th>
                <th colspan="4" class="group-header">F&B Revenue</th>
                <th rowspan="3" class="w-money">Doanh Thu Khác</th>
                <th rowspan="3" class="w-money">Tổng Doanh Thu</th>
                <th rowspan="3" class="w-pm">Hình Thức Thanh Toán</th>
            </tr>
            <tr class="sub-header-1">
                <th rowspan="2">Tiền Phòng</th>
                <th rowspan="2">PT Thêm Giường</th>
                <th rowspan="2">PT Thêm Người</th>
                <th rowspan="2">PT Tiền Phòng</th>
                <th rowspan="2">Vé</th>
                <th rowspan="2">Đưa Đón Khách</th>
                <th rowspan="2">DT Khác</th>
                <th rowspan="2">Minibar</th>
                <th rowspan="2">Giặt Ủi</th>
                <th rowspan="2">Bể Vỡ</th>
                <th rowspan="2">AS Từ Tiền Phòng</th>
                <th rowspan="2">Phí AS</th>
                <th colspan="3" class="restaurant-group">Nhà Hàng</th>
            </tr>
            <tr class="sub-header-2">
                <th>Thức Ăn</th>
                <th>Đồ Uống</th>
                <th>Khác</th>
            </tr>
        </thead>
        <tbody>
            {{#each rows}}
            <tr>
                <td class="text-center">{{CodeBooking}}</td>
                <td class="text-center">{{RoomNumber}}</td>
                <td class="text-left">{{Company}}</td>
                <td class="text-left">{{GuestName}}</td>
                <td class="text-center">{{Arrival}}</td>
                <td class="text-center">{{Departure}}</td>
                <td class="text-right">{{formatNumber RoomCharge}}</td>
                <td class="text-right">{{formatNumber ExtraBed}}</td>
                <td class="text-right">{{formatNumber ExtraPerson}}</td>
                <td class="text-right">{{formatNumber ExtraRoomCharge}}</td>
                <td class="text-right">{{formatNumber Tour}}</td>
                <td class="text-right">{{formatNumber Transportation}}</td>
                <td class="text-right">{{formatNumber Miscel}}</td>
                <td class="text-right">{{formatNumber Minibar}}</td>
                <td class="text-right">{{formatNumber Laundry}}</td>
                <td class="text-right">{{formatNumber Broken}}</td>
                <td class="text-right">{{formatNumber BreakfastSplitdown}}</td>
                <td class="text-right">{{formatNumber BreakfastCharge}}</td>
                <td class="text-right">{{formatNumber Food_Res}}</td>
                <td class="text-right">{{formatNumber Beverage_Res}}</td>
                <td class="text-right">{{formatNumber Other_Res}}</td>
                <td class="text-right">{{formatNumber OtherRevenue}}</td>
                <td class="text-right font-bold">{{formatNumber TotalRevenue}}</td>
                <td class="text-center">{{PaymentMethod}}</td>
            </tr>
            {{/each}}
        </tbody>
        <tfoot>
            <tr class="report-grand-total-row">
                <td colspan="6" class="text-center font-bold">Total</td>
                <td class="text-right font-bold">{{formatNumber aggregate.rows.sum.RoomCharge}}</td>
                <td class="text-right font-bold">{{formatNumber aggregate.rows.sum.ExtraBed}}</td>
                <td class="text-right font-bold">{{formatNumber aggregate.rows.sum.ExtraPerson}}</td>
                <td class="text-right font-bold">{{formatNumber aggregate.rows.sum.ExtraRoomCharge}}</td>
                <td class="text-right font-bold">{{formatNumber aggregate.rows.sum.Tour}}</td>
                <td class="text-right font-bold">{{formatNumber aggregate.rows.sum.Transportation}}</td>
                <td class="text-right font-bold">{{formatNumber aggregate.rows.sum.Miscel}}</td>
                <td class="text-right font-bold">{{formatNumber aggregate.rows.sum.Minibar}}</td>
                <td class="text-right font-bold">{{formatNumber aggregate.rows.sum.Laundry}}</td>
                <td class="text-right font-bold">{{formatNumber aggregate.rows.sum.Broken}}</td>
                <td class="text-right font-bold">{{formatNumber aggregate.rows.sum.BreakfastSplitdown}}</td>
                <td class="text-right font-bold">{{formatNumber aggregate.rows.sum.BreakfastCharge}}</td>
                <td class="text-right font-bold">{{formatNumber aggregate.rows.sum.Food_Res}}</td>
                <td class="text-right font-bold">{{formatNumber aggregate.rows.sum.Beverage_Res}}</td>
                <td class="text-right font-bold">{{formatNumber aggregate.rows.sum.Other_Res}}</td>
                <td class="text-right font-bold">{{formatNumber aggregate.rows.sum.OtherRevenue}}</td>
                <td class="text-right font-bold">{{formatNumber aggregate.rows.sum.TotalRevenue}}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    <div class="report-signatures">
        <div class="signature-col">
            <strong>NGƯỜI LẬP BIỂU</strong>
            <div class="sign-note">(Ký, họ tên)</div>
        </div>
        <div class="signature-col">
            <strong>KẾ TOÁN TRƯỞNG</strong>
            <div class="sign-note">(Ký, họ tên)</div>
        </div>
        <div class="signature-col">
            <strong>GIÁM ĐỐC</strong>
            <div class="sign-note">(Ký, họ tên, đóng dấu)</div>
        </div>
    </div>
</div>
HTML;
    }

    public function css(): string
    {
        return <<<'CSS'
.report-wrapper.landscape-a4 {
    width: 100%;
    font-family: 'Segoe UI', Arial, sans-serif;
    color: #111111;
    background: #ffffff;
    font-size: 8px;
    padding: 0;
}
.header-band {
    display: flex;
    justify-content: space-between;
    margin-bottom: 6px;
}
.hotel-info-left strong {
    font-size: 12px;
}
.hotel-info-left span {
    font-size: 9.5px;
}
.hotel-info-right {
    text-align: right;
    font-size: 9.5px;
}
.report-title {
    text-align: center;
    font-size: 15px;
    font-weight: bold;
    margin: 2px 0 3px 0;
    text-transform: uppercase;
}
.report-subtitle {
    text-align: center;
    font-size: 9px;
    font-weight: bold;
    margin-bottom: 8px;
}
.report-table.revenue-departure-table {
    width: 100%;
    border-collapse: collapse;
    border: 1px solid #aeb5c0;
    table-layout: fixed;
    font-size: 7.5px;
}
.report-table th, .report-table td {
    border: 1px solid #aeb5c0;
    padding: 2.5px 1.5px;
    box-sizing: border-box;
    word-break: break-word;
}
.report-table th {
    background-color: #d9deea;
    font-weight: bold;
    text-align: center;
}
.group-header, .restaurant-group {
    background-color: #c9d2e3 !important;
}
.report-grand-total-row td {
    background-color: #ffffff;
    font-weight: bold;
    border-top: 2px solid #000000;
}
.text-center { text-align: center; }
.text-left { text-align: left; }
.text-right { text-align: right; }
.font-bold { font-weight: bold; }
.report-signatures {
    display: flex;
    justify-content: space-between;
    margin-top: 20px;
    page-break-inside: avoid;
}
.signature-col {
    width: 32%;
    text-align: center;
    font-size: 9.5px;
}
.sign-note {
    font-style: italic;
    font-size: 8.5px;
    margin-top: 2px;
    margin-bottom: 50px;
}
CSS;
    }
}
```

---

## 5. Bóc Tách Chi Tiết Các Bảng, View & Logic Trong Stored Procedure Gốc (`sp_238` & `sp_240`)

Trong file SQL gốc [sp_238_full.sql](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/sql/sp_238_full.sql) (chi tiết phòng) và [sp_240_full.sql](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/sql/sp_240_full.sql) (nhóm theo booking), hệ thống legacy sử dụng các bảng và logic nghiệp vụ sau:

### 5.1. Danh Sách Các Bảng Legacy Được Sử Dụng
1. **`sp1322` (Hotel System Configuration)**:
   - Truy vấn `select top 1 isnull(PrefixBookingId, '') from sp1322` để lấy tiền tố mã đặt phòng (vd: `BK`, `RS`).
2. **`SP8060`, `SP8058`, `SP8059` (Report Template Config Tables)**:
   - Kiểm tra loại template của khách sạn: `where GroupTemplate = 'Total revenue report'`. Nếu khách sạn dùng mẫu đặc thù (vd: `'Tulip'`) thì áp dụng phân nhánh lọc riêng.
3. **`SP2000` (Bookings / Đặt phòng)**:
   - Các trường sử dụng: `Ma` (Booking ID), `BookingName` (Tên đoàn/khách), `ArrivalDate`, `NumOfDays`, `TravelAgency` (Mã công ty), `Status`.
4. **`SP2100` (Booking Rooms / Phòng thuê)**:
   - Các trường sử dụng: `Ma` (Room Rental ID), `BookingId`, `Room` (Số phòng), `ArrivalDate`, `CheckoutDate`, `CheckoutTime`, `Status`, `DayUse`.
   - Thời gian trả phòng thực tế được tính bằng `CheckoutDate + CheckoutTime`, nếu chưa trả phòng thì fallback về `ArrivalDate + NumOfDays` kèm giờ mặc định `12:00`.
5. **`SP2300` (Customers / Khách hàng)**:
   - `Id`, `FirstName` (Họ tên khách lưu trú chính).
6. **`SP1302` (Companies / Đại lý du lịch & OTA)**:
   - `Ma`, `Company` (Tên đơn vị, công ty đối tác).
7. **`SP3000` & `SP3001` (Sales Invoices & Invoice Details)**:
   - `SP3000`: Hóa đơn dịch vụ cha (`Ma`, `ServiceId`, `Outlet`, `PaymentID`, `Edit`, `RentalRoomId1`, `RentalRoomId2`, `RegisterId1`, `RegisterId2`).
   - `SP3001`: Chi tiết hạng mục hóa đơn (`BillServiceId`, `Amount`, `ServiceId`, `DepartmentId`).
   - Phân loại 17 nhóm dịch vụ doanh thu thông qua cặp `(ServiceId, DepartmentId, Outlet)`:
     - Tiền phòng (`RM`), Kê giường phụ (`EB`), Phụ thu người (`EP`), Nhận sớm/trả trễ (`EI, LO, ER`).
     - Minibar (`MB`), Giặt ủi (`LA`), Bể vỡ đền bù (`BR, BK`).
     - Ăn sáng trích tiền phòng (`BF` tại outlet phòng), Ăn sáng thu ngoài gói (`BC, BD, BF` tại outlet `RC`).
     - Nhà hàng F&B: Thức ăn (`RF` tại outlet `RE`), Đồ uống (`RB, PC` tại outlet `RE`), F&B khác.
8. **`SP3002` (Payments / Thanh toán)**:
   - `PaymentId`, `PaymentMethod`, `Date`, `OpenTime`.
   - Kết hợp bảng `sp1326` (Hình thức thanh toán miễn phí - `HTMienPhi = 1`) để loại trừ các bill không tính doanh thu thực tế.

### 5.2. Bảng Đối Chiếu Ánh Xạ Sang Schema MySQL PMS Mới

| Bảng Legacy | Ý nghĩa trong `sp_238` / `sp_240` | Bảng tương ứng MySQL PMS | Cột tương đương trong PMS mới |
|---|---|---|---|
| `sp1322` | Tiền tố mã đặt phòng | `hotels` / `system_configs` | Tiền tố hiển thị mã đặt phòng |
| `SP2000` | Thông tin đặt phòng chính | `bookings` | `id`, `booking_name`, `arrival_date`, `departure_date`, `company_id`, `status` |
| `SP2100` | Phòng thuê & thời gian trả phòng thực tế | `booking_rooms` | `id`, `booking_id`, `room_number`, `arrival_date`, `departure_date`, `departure_time`, `status` |
| `SP2300` | Thông tin khách hàng | `customers` | `first_name`, `last_name` |
| `SP1302` | Danh mục công ty / đối tác OTA | `companies` | `id`, `name` |
| `SP3000` + `SP3001` | Hóa đơn & chi tiết doanh thu dịch vụ | `sales_invoices` | `id`, `booking_id`, `rental_room_id`, `outlet`, `department`, `amount`, `status != 'cancelled'` |
| `SP3002` + `sp1326` | Giao dịch thanh toán & hình thức thanh toán | `payments` + `payment_methods` | `payment_method_id` $\rightarrow$ `pm.code`, `amount`, `edit_flag = 0` |

---

## 6. Thuật Toán Stored Procedure MySQL 8.0 (`rpt_revenue_by_departure_date`)

```sql
DELIMITER $$

CREATE PROCEDURE rpt_revenue_by_departure_date(
    IN p_from_date VARCHAR(20),
    IN p_to_date VARCHAR(20),
    IN p_from_time VARCHAR(10),
    IN p_to_time VARCHAR(10),
    IN p_company_id VARCHAR(50),
    IN p_group_by_booking TINYINT
)
READS SQL DATA
BEGIN
    DECLARE v_from_datetime DATETIME;
    DECLARE v_to_datetime DATETIME;
    DECLARE v_company_id INT DEFAULT 0;
    DECLARE v_group_booking TINYINT DEFAULT 0;

    IF p_from_time IS NULL OR p_from_time = '' THEN SET p_from_time = '00:00'; END IF;
    IF p_to_time IS NULL OR p_to_time = '' THEN SET p_to_time = '23:59'; END IF;

    IF p_from_date LIKE '%/%' THEN
        SET v_from_datetime = STR_TO_DATE(CONCAT(LEFT(p_from_date, 10), ' ', p_from_time, ':00'), '%d/%m/%Y %H:%i:%s');
    ELSE
        SET v_from_datetime = CAST(CONCAT(LEFT(p_from_date, 10), ' ', p_from_time, ':00') AS DATETIME);
    END IF;

    IF p_to_date LIKE '%/%' THEN
        SET v_to_datetime = STR_TO_DATE(CONCAT(LEFT(p_to_date, 10), ' ', p_to_time, ':59'), '%d/%m/%Y %H:%i:%s');
    ELSE
        SET v_to_datetime = CAST(CONCAT(LEFT(p_to_date, 10), ' ', p_to_time, ':59') AS DATETIME);
    END IF;

    IF p_company_id IS NOT NULL AND p_company_id != '' AND p_company_id != '0' THEN
        SET v_company_id = CAST(p_company_id AS UNSIGNED);
    END IF;

    IF p_group_by_booking IS NOT NULL THEN
        SET v_group_booking = p_group_by_booking;
    END IF;

    WITH InvoiceItems AS (
        SELECT 
            si.booking_id,
            si.rental_room_id,
            br.room_number,
            b.booking_name,
            COALESCE(comp.name, 'KHÁCH LẺ') AS company_name,
            b.arrival_date,
            b.departure_date,
            br.departure_date AS room_departure_date,
            COALESCE(br.departure_time, '12:00') AS room_departure_time,
            si.outlet,
            si.department,
            si.amount,
            si.payment_id,
            CASE WHEN br.room_number IS NOT NULL AND br.room_number != '' THEN br.departure_date ELSE b.departure_date END AS effective_dep_date,
            CASE WHEN br.room_number IS NOT NULL AND br.room_number != '' THEN COALESCE(br.departure_time, '12:00') ELSE '12:00' END AS effective_dep_time
        FROM sales_invoices si
        LEFT JOIN bookings b ON b.id = si.booking_id
        LEFT JOIN booking_rooms br ON br.id = si.rental_room_id
        LEFT JOIN companies comp ON comp.id = b.company_id
        WHERE si.status != 'cancelled'
          AND (v_company_id = 0 OR b.company_id = v_company_id)
    ),
    FilteredItems AS (
        SELECT *
        FROM InvoiceItems
        WHERE CAST(CONCAT(effective_dep_date, ' ', effective_dep_time) AS DATETIME) BETWEEN v_from_datetime AND v_to_datetime
    )
    SELECT 
        fi.booking_id AS CodeBooking,
        CASE WHEN v_group_booking = 1 THEN '' ELSE COALESCE(fi.room_number, '') END AS RoomNumber,
        fi.company_name AS Company,
        fi.booking_name AS GuestName,
        DATE_FORMAT(fi.arrival_date, '%d/%m/%Y') AS Arrival,
        DATE_FORMAT(fi.departure_date, '%d/%m/%Y') AS Departure,
        ROUND(SUM(CASE WHEN fi.outlet = 'RM' THEN fi.amount ELSE 0 END), 0) AS RoomCharge,
        ROUND(SUM(CASE WHEN fi.outlet = 'EB' THEN fi.amount ELSE 0 END), 0) AS ExtraBed,
        ROUND(SUM(CASE WHEN fi.outlet = 'EP' THEN fi.amount ELSE 0 END), 0) AS ExtraPerson,
        ROUND(SUM(CASE WHEN fi.outlet IN ('EI', 'LO', 'ER') THEN fi.amount ELSE 0 END), 0) AS ExtraRoomCharge,
        ROUND(SUM(CASE WHEN fi.outlet = 'TO' THEN fi.amount ELSE 0 END), 0) AS Tour,
        ROUND(SUM(CASE WHEN fi.outlet IN ('PU', 'DO') THEN fi.amount ELSE 0 END), 0) AS Transportation,
        ROUND(SUM(CASE WHEN fi.outlet NOT IN ('RM', 'EB', 'EP', 'EI', 'LO', 'ER', 'TO', 'PU', 'DO', 'MB', 'LA', 'BR', 'BK', 'RF', 'RB', 'PC', 'BC', 'BD', 'BF', 'FB') AND fi.department = 'FO' THEN fi.amount ELSE 0 END), 0) AS Miscel,
        ROUND(SUM(CASE WHEN fi.outlet = 'MB' THEN fi.amount ELSE 0 END), 0) AS Minibar,
        ROUND(SUM(CASE WHEN fi.outlet = 'LA' THEN fi.amount ELSE 0 END), 0) AS Laundry,
        ROUND(SUM(CASE WHEN fi.outlet IN ('BR', 'BK') THEN fi.amount ELSE 0 END), 0) AS Broken,
        ROUND(SUM(CASE WHEN fi.outlet = 'BF' THEN fi.amount ELSE 0 END), 0) AS BreakfastSplitdown,
        ROUND(SUM(CASE WHEN fi.outlet IN ('BC', 'BD') THEN fi.amount ELSE 0 END), 0) AS BreakfastCharge,
        ROUND(SUM(CASE WHEN fi.outlet = 'RF' THEN fi.amount ELSE 0 END), 0) AS Food_Res,
        ROUND(SUM(CASE WHEN fi.outlet IN ('RB', 'PC') THEN fi.amount ELSE 0 END), 0) AS Beverage_Res,
        ROUND(SUM(CASE WHEN fi.department = 'FB' AND fi.outlet NOT IN ('RF', 'RB', 'PC', 'BC', 'BD', 'BF') THEN fi.amount ELSE 0 END), 0) AS Other_Res,
        ROUND(SUM(CASE WHEN fi.department NOT IN ('FO', 'HK', 'FB') THEN fi.amount ELSE 0 END), 0) AS OtherRevenue,
        ROUND(SUM(fi.amount), 0) AS TotalRevenue,
        COALESCE(
            (SELECT GROUP_CONCAT(DISTINCT pm.code SEPARATOR ', ')
             FROM payments p 
             JOIN payment_methods pm ON pm.id = p.payment_method_id 
             WHERE p.booking_id = fi.booking_id AND COALESCE(p.edit_flag, 0) = 0), 
            ''
        ) AS PaymentMethod
    FROM FilteredItems fi
    GROUP BY 
        fi.booking_id,
        CASE WHEN v_group_booking = 1 THEN 0 ELSE fi.room_number END,
        fi.company_name,
        fi.booking_name,
        fi.arrival_date,
        fi.departure_date
    ORDER BY fi.booking_id ASC, RoomNumber ASC;
END$$

DELIMITER ;
```

---

## 6. Hướng Dẫn Các Bước Triển Khai Cho Agent

1. **Tạo file template reference**: `backend/database/report_templates/revenue_by_departure_date_reference.php`.
2. **Tạo migration tổng hợp**: `backend/database/migrations/2026_09_22_110000_create_revenue_by_departure_date_report.php`.
3. **Chạy migration**: `php artisan migrate:all --force`.
4. **Tạo test case**: `backend/tests/Feature/RevenueByDepartureDateReportTest.php`.
5. **Chạy kiểm thử xác nhận**: `php artisan test --filter=RevenueByDepartureDateReportTest`.

---

## 7. Trạng thái triển khai runtime

- Mã runtime: `REVENUE_BY_DEPARTURE_DATE`, procedure `rpt_revenue_by_departure_date`, template `REVENUE_BY_DEPARTURE_DATE_REFERENCE`.
- Migration đã chạy: `2026_09_22_100000` và các migration sửa `ONLY_FULL_GROUP_BY`/tương thích schema đến `2026_09_22_160000` trên HKT1–HKT4.
- Smoke-test `CALL rpt_revenue_by_departure_date(...)` đạt trên `pms_hkt1`–`pms_hkt4`; chưa nghiệm thu số liệu với dữ liệu legacy thật.
