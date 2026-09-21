# Đặc Tả Kỹ Thuật: Báo Cáo Doanh Thu Lễ Tân_Army (Dòng 152)

> **Tài liệu chuẩn bị cho Agent triển khai tiếp theo**  
> Dựa trên phân tích từ file Excel `DANH MỤC BÁO CÁO.xlsx` (Dòng 152, Sheet 74 `BC doanh thu lễ tân`), ảnh giao diện thực tế `[.codex/docs/doc_baocao/images/dong_152_ui_mau.png](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/images/dong_152_ui_mau.png)` và Stored Procedure gốc trên MS SQL Server `ProVistaArmyHotel.dbo.sp_293` ([sp_293_full.sql](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/sql/sp_293_full.sql)).

---

## 1. Thông Tin Chung & Định Danh

* **Tên báo cáo tiếng Việt**: Báo cáo doanh thu lễ tân_army
* **Mã báo cáo (`code`)**: `RECEPTION_REVENUE_ARMY`
* **Mã nguồn dữ liệu (`data_source_code`)**: `RPT_RECEPTION_REVENUE_ARMY`
* **Mã template (`template_code`)**: `RECEPTION_REVENUE_ARMY_REFERENCE`
* **Nhóm báo cáo (`group`)**: `Báo cáo doanh thu`
* **Menu hiển thị**: `['frontdesk', 'cashier', 'report']`
* **Vị trí trong Excel danh mục**: Dòng 152 (STT 6.0)
* **Ghi chú trong Excel**: "Báo cáo theo đơn vị army quy nhơn khi nào làm báo cáo này báo Vy gửi lại store mới nhất"
* **Sheet tham chiếu trong Excel**: Sheet 74 (`BC doanh thu lễ tân`)
* **Ảnh chụp màn hình thực tế**: `[.codex/docs/doc_baocao/images/dong_152_ui_mau.png](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/images/dong_152_ui_mau.png)`
* **File SQL legacy tham chiếu**: [sp_293_full.sql](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/sql/sp_293_full.sql)

---

## 2. Phân Tích Chi Tiết Giao Diện Từ Ảnh Thực Tế (`dong_152_ui_mau.png`)

### 2.1. Panel Bộ Lọc Bên Trái (Left Filter Panel)
* **Chiều rộng panel**: ~ 260px - 280px.
* **Các thành phần điều khiển**:
  1. **Ngày (`p_from_date` ~ `p_to_date`)**:
     - Date Range Picker, giá trị hiển thị mặc định: `Hôm nay` (ví dụ trên ảnh mẫu: `15/07/2026 ~ 15/07/2026`).
  2. **Dịch vụ (`p_service`)**:
     - Multi-select Dropdown (hiển thị `Chọn: 0` khi chưa chọn, cho phép chọn đồng thời nhiều dịch vụ hoặc để trống để xem tất cả), nguồn: `hotel-services`.
  3. **Người dùng (`p_user`)**:
     - Dropdown Select, placeholder: `Select Value`, nguồn: `users`. Mặc định: Tất cả (`""`).
  4. **Nút thực thi**:
     - Button `Hiển thị báo cáo`: Màu xanh biển `#3b82f6`, chữ trắng, bo góc 4px.

---

## 3. Cấu Hình Form Designer & Thông Số Thiết Kế Chi Tiết

### 3.1. Thiết Lập Trang In (Page Settings)
* `page_size`: `'A4'`
* `page_orientation`: `'landscape'` (hoặc `portrait` đều được do bảng chỉ có 11 cột; khuyên dùng `landscape` để cột Mô Tả và Tên Khách thoáng đãng)
* `margin_top`: `8` (mm)
* `margin_bottom`: `8` (mm)
* `margin_left`: `6` (mm)
* `margin_right`: `6` (mm)

### 3.2. Bảng Màu & Typography (Design Tokens)
* **Font chữ chính**: `Segoe UI, Arial, sans-serif`
* **Màu chữ chính**: `#111111`
* **Màu viền bảng**: `#aeb5c0` (1px solid)
* **Màu nền Header bảng**: `#d9deea`
* **Màu liên kết Mã ĐK**: Màu xanh lá `#2e7d32`, in đậm
* **Cỡ chữ**:
  * Tiêu đề chính (`H1`): `17px`, `font-weight: bold`, `text-align: center`, `color: #111111`
  * Tiêu đề phụ (Ngày áp dụng): `10px`, `font-weight: bold`, `text-align: center`
  * Toàn bộ bảng dữ liệu: `9.5px`
* **Padding ô bảng**: `4px 5px`

---

## 4. Chi Tiết Layout Bảng Dữ Liệu (11 Cột - 2 Cấp Phân Nhóm)

### 4.1. Hệ Thống Phân Cấp Dữ Liệu (Grouping Hierarchy)
Báo cáo được gom nhóm theo 2 cấp độ phân tầng:
* **Cấp 1 (Nhóm lớn - `RevenueGroupName`)**:
  - Dòng tiêu đề toàn bảng: `Nhóm doanh thu | Doanh Thu Phòng` hoặc `Nhóm doanh thu | Doanh Thu Dịch Vụ`.
  - Nền xám nhạt `#f1f5f9`, chữ in đậm.
* **Cấp 2 (Nhóm dịch vụ - `ServiceId` & `ServiceName`)**:
  - Dòng tiêu đề: `Dịch Vụ: [Mã DV] - [Tên DV]` (ví dụ: `Dịch Vụ: RM - Dịch vụ phòng nghỉ`, `Dịch Vụ: MB - Minibar/Phí Minibar`).
  - In đậm, chữ đen.
* **Dòng dữ liệu chi tiết (Detail Rows)**: Hiển thị bên dưới từng nhóm dịch vụ.
* **Dòng tổng phụ Dịch Vụ (Subtotal Service)**: Dòng nhãn `Tổng:` căn phải, số tiền căn phải in đậm.
* **Dòng tổng phụ Nhóm Doanh Thu (Subtotal Group)**: Dòng nhãn `Tổng:` căn phải, số tiền căn phải in đậm.

### 4.2. Chi Tiết Thuộc Tính 11 Cột Dữ Liệu

| STT | Tên cột hiển thị | Field Name | Width | Align | Format | Ghi chú & Logic ánh xạ |
|---|---|---|---|---|---|---|
| 1 | `Mã ĐK` | `BookingId` | `6%` | Center | Text | Mã đặt phòng (màu xanh lá `#2e7d32` đậm) |
| 2 | `Phòng` | `Room` | `5%` | Center | Text | Số phòng lưu trú / sử dụng dịch vụ |
| 3 | `Ngày Đến` | `ArrivalDate` | `7%` | Center | `dd/mm/yyyy` | Ngày đến của khách |
| 4 | `Ngày Đi` | `DepartureDate`| `7%` | Center | `dd/mm/yyyy` | Ngày đi của khách |
| 5 | `Tên Khách` | `GuestName` | `14%` | Left | Text | Tên khách lưu trú |
| 6 | `Mô Tả` | `DescriptionService`|`18%`| Left | Text | Diễn giải dịch vụ (vd: "Dịch vụ phòng nghỉ 806", "Minibar/Phí Minibar") |
| 7 | `Doanh Thu` | `Amount` | `8%` | Right | Tiền tệ | Số tiền doanh thu của hóa đơn |
| 8 | `HTTT` | `HTTT` | `5%` | Center | Text | Mã hình thức thanh toán (vd: `CA`, `CK`, `AC`) |
| 9 | `Công Ty` | `Company` | `12%` | Left | Text | Tên công ty / OTA / Khách lẻ |
| 10 | `Giờ` | `OpenTime` | `6%` | Center | `HH:mm` | Giờ tạo bill dịch vụ (vd: `09:14`, `09:38`) |
| 11 | `Ghi chú` | `Note` | `12%` | Left | Text | Ghi chú thanh toán (vd: `Cash (Tiền mặt)`) |

### 4.3. Bảng Tổng Hợp Phụ Cuối Báo Cáo (Summary Table)
Được cấu hình bằng khối `static-table` hoặc khối tùy biến trong `content_json`, đặt ở cuối báo cáo theo đúng ảnh chụp thực tế `dong_152_ui_mau.png`:
* Căn bên trái hoặc giữa trang in, độ rộng khoảng 320px.
* Cấu trúc 2 cột:
  - Header: `Nhóm doanh thu` | `Tổng`
  - Dòng 1: `Doanh Thu Phòng` | `{{aggregate.room_rev|number}}`
  - Dòng 2: `Doanh Thu Dịch Vụ` | `{{aggregate.service_rev|number}}`
  - Dòng 3 (Grand Total): `Tổng` | `{{aggregate.rows.sum.Amount|number}}` (In đậm, nền `#f1f5f9`).

---

### 4.4. Cấu Trúc File Template Reference Chuẩn (`reception_revenue_army_reference.php`)

> **Toàn bộ cấu hình `content_json`** (gồm blocks `header`, dynamic `table` với `grouping` 2 cấp, `columns`, `customRows` Subtotal 2 cấp & Grand Total `aggregate.rows.sum.*`, khối bảng tĩnh `summary_box`, và `footer` 5 khối chữ ký quân đội) được định nghĩa đầy đủ 100% bên dưới. Agent tiếp theo chỉ việc copy vào `backend/database/report_templates/reception_revenue_army_reference.php`.

```php
<?php

namespace Database\ReportTemplates;

class ReceptionRevenueArmyReference
{
    public function definition(): array
    {
        return [
            'code' => 'RECEPTION_REVENUE_ARMY',
            'name' => 'Báo cáo doanh thu lễ tân_army',
            'group' => 'Báo cáo doanh thu',
            'menu' => ['frontdesk', 'cashier', 'report'],
            'description' => 'Báo cáo doanh thu lễ tân chi tiết theo từng khoản thu phân cấp 2 tầng theo nhóm doanh thu và dịch vụ',
            'data_source_code' => 'RPT_RECEPTION_REVENUE_ARMY',
            'page_size' => 'A4',
            'page_orientation' => 'portrait',
            'margin_top' => 8,
            'margin_bottom' => 8,
            'margin_left' => 8,
            'margin_right' => 8,
        ];
    }

    public function columns(): array
    {
        return [
            ['id' => 'booking_id', 'field' => 'BookingId', 'label' => 'Mã ĐK', 'width' => '6%', 'align' => 'center', 'format' => 'text', 'style' => ['fontWeight' => 'bold', 'color' => '#2e7d32']],
            ['id' => 'room', 'field' => 'Room', 'label' => 'Phòng', 'width' => '5%', 'align' => 'center', 'format' => 'text'],
            ['id' => 'arrival_date', 'field' => 'ArrivalDate', 'label' => 'Ngày Đến', 'width' => '7%', 'align' => 'center', 'format' => 'text'],
            ['id' => 'departure_date', 'field' => 'DepartureDate', 'label' => 'Ngày Đi', 'width' => '7%', 'align' => 'center', 'format' => 'text'],
            ['id' => 'guest_name', 'field' => 'GuestName', 'label' => 'Tên Khách', 'width' => '14%', 'align' => 'left', 'format' => 'text'],
            ['id' => 'description_service', 'field' => 'DescriptionService', 'label' => 'Mô Tả', 'width' => '18%', 'align' => 'left', 'format' => 'text'],
            ['id' => 'amount', 'field' => 'Amount', 'label' => 'Doanh Thu', 'width' => '8%', 'align' => 'right', 'format' => 'number', 'style' => ['fontWeight' => 'bold']],
            ['id' => 'httt', 'field' => 'HTTT', 'label' => 'HTTT', 'width' => '5%', 'align' => 'center', 'format' => 'text'],
            ['id' => 'company', 'field' => 'Company', 'label' => 'Công Ty', 'width' => '12%', 'align' => 'left', 'format' => 'text'],
            ['id' => 'open_time', 'field' => 'OpenTime', 'label' => 'Giờ', 'width' => '6%', 'align' => 'center', 'format' => 'text'],
            ['id' => 'note', 'field' => 'Note', 'label' => 'Ghi Chú', 'width' => '12%', 'align' => 'left', 'format' => 'text'],
        ];
    }

    public function blocks(): array
    {
        return [
            'header' => [
                [
                    'id' => 'reception_header_band',
                    'type' => 'columns',
                    'style' => [
                        'display' => 'flex',
                        'justifyContent' => 'space-between',
                        'alignItems' => 'flex-start',
                        'marginBottom' => '6px',
                    ],
                    'columns' => [
                        [
                            'width' => '40%',
                            'blocks' => [[
                                'id' => 'hotel_info_block',
                                'type' => 'text',
                                'content' => '<div style="font-size: 11px; line-height: 1.4;"><strong>{{hotel.name}}</strong><br><span>{{hotel.address}}</span></div>',
                            ]],
                        ],
                        [
                            'width' => '60%',
                            'blocks' => [[
                                'id' => 'print_info_block',
                                'type' => 'text',
                                'content' => '<div style="text-align: right; font-size: 10px; line-height: 1.4;">'
                                    .'<div><b>Nhân viên:</b> {{report.generated_by}} &nbsp;&nbsp; <b>Ngày in:</b> {{report.generated_at}}</div>'
                                    .'</div>',
                            ]],
                        ],
                    ],
                ],
                [
                    'id' => 'reception_title',
                    'type' => 'text',
                    'content' => '<h1 style="text-align: center; font-size: 16px; font-weight: bold; margin: 2px 0; text-transform: uppercase;">BÁO CÁO DOANH THU LỄ TÂN</h1>',
                    'style' => ['textAlign' => 'center', 'fontWeight' => 'bold'],
                ],
                [
                    'id' => 'reception_subtitle',
                    'type' => 'text',
                    'content' => '<p style="text-align: center; font-size: 10px; font-weight: bold; margin: 0 0 2px 0;">Từ ngày {{parameters.p_from_date}} Đến ngày {{parameters.p_to_date}}</p>',
                    'style' => ['textAlign' => 'center'],
                ],
                [
                    'id' => 'reception_user_info',
                    'type' => 'text',
                    'content' => '<p style="text-align: center; font-size: 9.5px; font-style: italic; margin: 0 0 8px 0;">Người dùng: {{parameters.p_user}}</p>',
                    'style' => ['textAlign' => 'center', 'marginBottom' => '8px'],
                ],
            ],
            'detail' => [
                [
                    'id' => 'reception_revenue_army_table',
                    'type' => 'table',
                    'dataSource' => 'rows',
                    'tableType' => 'dynamic',
                    'tableClassName' => 'reception-revenue-table',
                    'style' => [
                        'width' => '100%',
                        'fontSize' => '8.5px',
                        'borderCollapse' => 'collapse',
                        'borderColor' => '#aeb5c0',
                        'borderWidth' => '1px',
                        'borderStyle' => 'solid',
                        'backgroundColor' => '#ffffff',
                    ],
                    'hasTwoTierHeader' => false,
                    'grouping' => [
                        [
                            'id' => 'group_revenue_type',
                            'field' => 'RevenueGroupName',
                            'label' => '{{row.RevenueGroupName}}',
                            'className' => 'group-header-rev-type',
                            'sort' => 'ASC',
                            'headerCells' => [
                                [
                                    'id' => 'rev_type_cell',
                                    'type' => 'text',
                                    'content' => '<span style="font-weight: bold; font-size: 10.5px; text-transform: uppercase;">{{row.RevenueGroupName}}</span>',
                                    'colspan' => 11,
                                    'align' => 'left',
                                    'style' => ['backgroundColor' => '#f1f5f9', 'fontWeight' => 'bold', 'padding' => '4px 6px'],
                                ],
                            ],
                        ],
                        [
                            'id' => 'group_service',
                            'field' => 'ServiceId',
                            'label' => 'Dịch Vụ: {{row.ServiceId}} - {{row.ServiceName}}',
                            'className' => 'group-header-service',
                            'sort' => 'ASC',
                            'headerCells' => [
                                [
                                    'id' => 'service_cell',
                                    'type' => 'text',
                                    'content' => '<span style="font-weight: bold; font-style: italic; color: #1e3a8a; padding-left: 10px;">Dịch Vụ: {{row.ServiceId}} - {{row.ServiceName}}</span>',
                                    'colspan' => 11,
                                    'align' => 'left',
                                    'style' => ['backgroundColor' => '#ffffff', 'padding' => '3px 8px'],
                                ],
                            ],
                        ],
                    ],
                    'columns' => $this->columns(),
                    'customRows' => [
                        [
                            'id' => 'reception_service_subtotal_row',
                            'enabledBy' => '',
                            'scope' => 'group',
                            'level' => 1,
                            'className' => 'service-subtotal-row',
                            'cells' => [
                                ['id' => 'srv_sub_label', 'type' => 'text', 'content' => 'Tổng:', 'colspan' => 6, 'align' => 'right', 'style' => ['fontWeight' => 'bold', 'fontStyle' => 'italic', 'padding' => '3px 6px']],
                                ['id' => 'srv_sub_amt', 'type' => 'binding', 'binding' => 'group.sum.Amount', 'colspan' => 1, 'align' => 'right', 'format' => 'number', 'style' => ['fontWeight' => 'bold', 'padding' => '3px 4px']],
                                ['id' => 'srv_sub_spacer', 'type' => 'text', 'content' => '', 'colspan' => 4, 'align' => 'center', 'style' => ['padding' => '3px 4px']],
                            ],
                        ],
                        [
                            'id' => 'reception_rev_group_subtotal_row',
                            'enabledBy' => '',
                            'scope' => 'group',
                            'level' => 0,
                            'className' => 'rev-group-subtotal-row',
                            'cells' => [
                                ['id' => 'grp_sub_label', 'type' => 'text', 'content' => 'Tổng {{row.RevenueGroupName}}:', 'colspan' => 6, 'align' => 'right', 'style' => ['fontWeight' => 'bold', 'color' => '#b91c1c', 'padding' => '4px 6px']],
                                ['id' => 'grp_sub_amt', 'type' => 'binding', 'binding' => 'group.sum.Amount', 'colspan' => 1, 'align' => 'right', 'format' => 'number', 'style' => ['fontWeight' => 'bold', 'color' => '#b91c1c', 'padding' => '4px 4px']],
                                ['id' => 'grp_sub_spacer', 'type' => 'text', 'content' => '', 'colspan' => 4, 'align' => 'center', 'style' => ['padding' => '4px 4px']],
                            ],
                        ],
                        [
                            'id' => 'reception_grand_total_row',
                            'enabledBy' => '',
                            'scope' => 'table',
                            'level' => 0,
                            'className' => 'report-grand-total-row',
                            'cells' => [
                                ['id' => 'g_sub_label', 'type' => 'text', 'content' => 'Tổng cộng:', 'colspan' => 6, 'align' => 'right', 'style' => ['fontWeight' => 'bold', 'padding' => '4px 6px']],
                                ['id' => 'g_sub_amt', 'type' => 'binding', 'binding' => 'aggregate.rows.sum.Amount', 'colspan' => 1, 'align' => 'right', 'format' => 'number', 'style' => ['fontWeight' => 'bold', 'padding' => '4px 4px']],
                                ['id' => 'g_sub_spacer', 'type' => 'text', 'content' => '', 'colspan' => 4, 'align' => 'center', 'style' => ['padding' => '4px 4px']],
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'summary_revenue_box_table',
                    'type' => 'table',
                    'dataSource' => 'static',
                    'tableType' => 'static',
                    'tableClassName' => 'summary-box-table',
                    'style' => [
                        'width' => '320px',
                        'marginTop' => '16px',
                        'borderCollapse' => 'collapse',
                        'borderColor' => '#aeb5c0',
                        'borderWidth' => '1px',
                        'borderStyle' => 'solid',
                        'fontSize' => '8.5px',
                    ],
                    'columns' => [
                        ['id' => 'sum_col_group', 'field' => 'Group', 'label' => 'Nhóm doanh thu', 'width' => '60%', 'align' => 'left'],
                        ['id' => 'sum_col_total', 'field' => 'Total', 'label' => 'Tổng', 'width' => '40%', 'align' => 'right', 'format' => 'number'],
                    ],
                    'staticRows' => [
                        [
                            ['content' => 'Doanh Thu Phòng', 'align' => 'left', 'style' => ['padding' => '3px 6px']],
                            ['content' => '{{aggregate.room_rev|number}}', 'align' => 'right', 'style' => ['padding' => '3px 6px']],
                        ],
                        [
                            ['content' => 'Doanh Thu Dịch Vụ', 'align' => 'left', 'style' => ['padding' => '3px 6px']],
                            ['content' => '{{aggregate.service_rev|number}}', 'align' => 'right', 'style' => ['padding' => '3px 6px']],
                        ],
                        [
                            ['content' => 'Tổng', 'align' => 'left', 'style' => ['fontWeight' => 'bold', 'backgroundColor' => '#f1f5f9', 'padding' => '3px 6px']],
                            ['content' => '{{aggregate.rows.sum.Amount|number}}', 'align' => 'right', 'style' => ['fontWeight' => 'bold', 'backgroundColor' => '#f1f5f9', 'padding' => '3px 6px']],
                        ],
                    ],
                ],
            ],
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
                            'width' => '20%',
                            'blocks' => [[
                                'id' => 'sig_maker',
                                'type' => 'text',
                                'content' => '<div style="text-align: center; font-size: 9.5px;"><b>NGƯỜI LẬP BIỂU</b><br><span style="font-style: italic; font-size: 8.5px;">(Ký, họ tên)</span><br><br><br><br></div>',
                            ]],
                        ],
                        [
                            'width' => '20%',
                            'blocks' => [[
                                'id' => 'sig_treasurer',
                                'type' => 'text',
                                'content' => '<div style="text-align: center; font-size: 9.5px;"><b>THỦ QUỸ</b><br><span style="font-style: italic; font-size: 8.5px;">(Ký, họ tên)</span><br><br><br><br></div>',
                            ]],
                        ],
                        [
                            'width' => '20%',
                            'blocks' => [[
                                'id' => 'sig_chief_accountant',
                                'type' => 'text',
                                'content' => '<div style="text-align: center; font-size: 9.5px;"><b>KẾ TOÁN TRƯỞNG</b><br><span style="font-style: italic; font-size: 8.5px;">(Ký, họ tên)</span><br><br><br><br></div>',
                            ]],
                        ],
                        [
                            'width' => '20%',
                            'blocks' => [[
                                'id' => 'sig_rev_accountant',
                                'type' => 'text',
                                'content' => '<div style="text-align: center; font-size: 9.5px;"><b>KT DOANH THU</b><br><span style="font-style: italic; font-size: 8.5px;">(Ký, họ tên)</span><br><br><br><br></div>',
                            ]],
                        ],
                        [
                            'width' => '20%',
                            'blocks' => [[
                                'id' => 'sig_director',
                                'type' => 'text',
                                'content' => '<div style="text-align: center; font-size: 9.5px;"><b>GIÁM ĐỐC</b><br><span style="font-style: italic; font-size: 8.5px;">(Ký, họ tên, đóng dấu)</span><br><br><br><br></div>',
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
<div class="report-wrapper portrait-a4">
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
        <h1 class="report-title">BÁO CÁO DOANH THU LỄ TÂN</h1>
        <div class="report-subtitle">Từ ngày {{parameters.p_from_date}} Đến ngày {{parameters.p_to_date}}</div>
        <div class="report-user-info">Người dùng: {{parameters.p_user}}</div>
    </div>

    <table class="report-table reception-revenue-table">
        <thead>
            <tr>
                <th style="width: 6%;">Mã ĐK</th>
                <th style="width: 5%;">Phòng</th>
                <th style="width: 7%;">Ngày Đến</th>
                <th style="width: 7%;">Ngày Đi</th>
                <th style="width: 14%;">Tên Khách</th>
                <th style="width: 18%;">Mô Tả</th>
                <th style="width: 8%;">Doanh Thu</th>
                <th style="width: 5%;">HTTT</th>
                <th style="width: 12%;">Công Ty</th>
                <th style="width: 6%;">Giờ</th>
                <th style="width: 12%;">Ghi Chú</th>
            </tr>
        </thead>
        <tbody>
            {{#each groups}}
            <tr class="group-header-rev-type">
                <td colspan="11" class="font-bold text-left">{{RevenueGroupName}}</td>
            </tr>
                {{#each services}}
                <tr class="group-header-service">
                    <td colspan="11" class="service-title text-left">Dịch Vụ: {{ServiceId}} - {{ServiceName}}</td>
                </tr>
                    {{#each rows}}
                    <tr>
                        <td class="text-center booking-code">{{BookingId}}</td>
                        <td class="text-center">{{Room}}</td>
                        <td class="text-center">{{ArrivalDate}}</td>
                        <td class="text-center">{{DepartureDate}}</td>
                        <td class="text-left">{{GuestName}}</td>
                        <td class="text-left">{{DescriptionService}}</td>
                        <td class="text-right font-bold">{{formatNumber Amount}}</td>
                        <td class="text-center">{{HTTT}}</td>
                        <td class="text-left">{{Company}}</td>
                        <td class="text-center">{{OpenTime}}</td>
                        <td class="text-left">{{Note}}</td>
                    </tr>
                    {{/each}}
                <tr class="service-subtotal-row">
                    <td colspan="6" class="text-right font-bold italic">Tổng:</td>
                    <td class="text-right font-bold">{{formatNumber ServiceTotalAmount}}</td>
                    <td colspan="4"></td>
                </tr>
                {{/each}}
            <tr class="rev-group-subtotal-row">
                <td colspan="6" class="text-right font-bold rev-group-label">Tổng {{RevenueGroupName}}:</td>
                <td class="text-right font-bold rev-group-label">{{formatNumber GroupTotalAmount}}</td>
                <td colspan="4"></td>
            </tr>
            {{/each}}
        </tbody>
        <tfoot>
            <tr class="report-grand-total-row">
                <td colspan="6" class="text-right font-bold">Tổng cộng:</td>
                <td class="text-right font-bold">{{formatNumber aggregate.rows.sum.Amount}}</td>
                <td colspan="4"></td>
            </tr>
        </tfoot>
    </table>

    <table class="summary-box-table">
        <thead>
            <tr>
                <th style="width: 60%; text-align: left;">Nhóm doanh thu</th>
                <th style="width: 40%; text-align: right;">Tổng</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="text-left">Doanh Thu Phòng</td>
                <td class="text-right">{{formatNumber aggregate.room_rev}}</td>
            </tr>
            <tr>
                <td class="text-left">Doanh Thu Dịch Vụ</td>
                <td class="text-right">{{formatNumber aggregate.service_rev}}</td>
            </tr>
            <tr class="font-bold summary-total-row">
                <td class="text-left">Tổng</td>
                <td class="text-right">{{formatNumber aggregate.rows.sum.Amount}}</td>
            </tr>
        </tbody>
    </table>

    <div class="report-signatures">
        <div class="signature-col">
            <strong>NGƯỜI LẬP BIỂU</strong>
            <div class="sign-note">(Ký, họ tên)</div>
        </div>
        <div class="signature-col">
            <strong>THỦ QUỸ</strong>
            <div class="sign-note">(Ký, họ tên)</div>
        </div>
        <div class="signature-col">
            <strong>KẾ TOÁN TRƯỞNG</strong>
            <div class="sign-note">(Ký, họ tên)</div>
        </div>
        <div class="signature-col">
            <strong>KT DOANH THU</strong>
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
.report-wrapper.portrait-a4 {
    width: 100%;
    font-family: 'Segoe UI', Arial, sans-serif;
    color: #111111;
    background: #ffffff;
    font-size: 8.5px;
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
    margin: 2px 0;
    text-transform: uppercase;
}
.report-subtitle {
    text-align: center;
    font-size: 9.5px;
    font-weight: bold;
    margin-bottom: 2px;
}
.report-user-info {
    text-align: center;
    font-size: 9px;
    font-style: italic;
    margin-bottom: 8px;
}
.report-table.reception-revenue-table {
    width: 100%;
    border-collapse: collapse;
    border: 1px solid #aeb5c0;
    table-layout: fixed;
    font-size: 8px;
}
.report-table th, .report-table td {
    border: 1px solid #aeb5c0;
    padding: 3px 2px;
    box-sizing: border-box;
    word-break: break-word;
}
.report-table th {
    background-color: #d9deea;
    font-weight: bold;
    text-align: center;
}
.group-header-rev-type td {
    background-color: #f1f5f9;
    font-weight: bold;
    text-transform: uppercase;
    padding: 4px 6px !important;
}
.group-header-service td {
    background-color: #ffffff;
    padding: 2.5px 10px !important;
}
.service-title {
    font-style: italic;
    color: #1e3a8a;
    font-weight: bold;
}
.booking-code {
    font-weight: bold;
    color: #2e7d32;
}
.service-subtotal-row td {
    background-color: #fafafa;
    border-top: 1px dashed #cbd5e1;
}
.rev-group-subtotal-row td {
    background-color: #f8fafc;
    border-top: 1px solid #94a3b8;
}
.rev-group-label {
    color: #b91c1c;
}
.report-grand-total-row td {
    background-color: #ffffff;
    font-weight: bold;
    border-top: 2px solid #000000;
}
.summary-box-table {
    width: 320px;
    margin-top: 16px;
    border-collapse: collapse;
    border: 1px solid #aeb5c0;
    font-size: 8.5px;
}
.summary-box-table th, .summary-box-table td {
    border: 1px solid #aeb5c0;
    padding: 3px 6px;
}
.summary-box-table th {
    background-color: #d9deea;
    font-weight: bold;
}
.summary-total-row td {
    background-color: #f1f5f9;
}
.text-center { text-align: center; }
.text-left { text-align: left; }
.text-right { text-align: right; }
.font-bold { font-weight: bold; }
.italic { font-style: italic; }
.report-signatures {
    display: flex;
    justify-content: space-between;
    margin-top: 20px;
    page-break-inside: avoid;
}
.signature-col {
    width: 19%;
    text-align: center;
    font-size: 9px;
}
.sign-note {
    font-style: italic;
    font-size: 8px;
    margin-top: 2px;
    margin-bottom: 50px;
}
CSS;
    }
}
```

---

## 5. Bóc Tách Chi Tiết Các Bảng, View & Function Trong Stored Procedure Gốc (`sp_293`)

Trong file SQL gốc [sp_293_full.sql](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/sql/sp_293_full.sql), Stored Procedure sử dụng một View trung tâm là `vw_018` và các bảng cấu hình báo cáo:

### 5.1. Phân Tích View Trung Tâm `vw_018`
* **Bản chất**: Là một SQL View tổng hợp đa bảng trong database `ProVistaArmyHotel`.
* **Cấu trúc kết hợp bên trong của `vw_018`**:
  * Bảng gốc: `SP3000 hddv` (Hóa đơn dịch vụ buồng phòng và lễ tân).
  * `LEFT JOIN SP2100 pt`: Lấy thông tin phòng thuê (`Room`, `ArrivalDate`, `NumOfDays`, `CheckoutDate`, `CheckoutTime`).
  * `LEFT JOIN SP2200 ptk` & `SP2300 k`: Lấy thông tin chi tiết khách lưu trú (`FirstName`, `Address`, `Passport`).
  * `LEFT JOIN SP1306 dv`: Lấy danh mục dịch vụ (`Service`, `STT`).
  * `LEFT JOIN SP3003 hdbh`: Lấy hóa đơn thanh toán (`PaymentDate`, `OpenTime`, `Department`).
  * `LEFT JOIN SP6000`: Liên kết phiếu buồng phòng (Minibar, giặt ủi, đền bù).
  * `LEFT JOIN SP5000`: Liên kết phiếu dịch vụ ẩm thực / F&B.
* **Công thức bóc tách thuế & phí bên trong `vw_018`**:
  * `OriginalRate` (Giá trước thuế phí):  
    $$\text{OriginalRate} = \frac{\text{Amount}}{(1 + \frac{\text{ServiceCharge}}{100}) \times (1 + \frac{\text{SpecialTax}}{100}) \times (1 + \frac{\text{Tax}}{100})}$$
  * `ServiceChargeAmount` (Phí dịch vụ): $\text{OriginalRate} \times \frac{\text{ServiceCharge}}{100}$.
  * `TaxAmount` (Thuế VAT): Tiền thuế GTGT tương ứng.
* **Các trường dữ liệu `vw_018` cung cấp cho `sp_293`**:
  * `BookingId`, `Date`, `Room`, `ArrivalDate`, `DepartureDate`.
  * `GuestName` (Họ tên khách).
  * `DescriptionService` (Mô tả dịch vụ: "Dịch vụ phòng nghỉ 806", "Minibar/Phí Minibar"...).
  * `Amount` (Tổng tiền thanh toán cuối cùng của khoản thu).
  * `ServiceId`, `FirstNameService` (Mã dịch vụ và tên dịch vụ: `RM`, `MB`, `LA`, `BR`, v.v.).
  * `OpenTime`, `Username` (Giờ tạo bill và nhân viên lập bill).

### 5.2. Các Bảng Cấu Hình Phân Nhóm Dịch Vụ Trong `sp_293`
1. **`SP1610` (Service Setup for Reports)**:
   * Chứa cấu hình nhóm dịch vụ dành riêng cho báo cáo lễ tân (`where Report = 'FORevenueReport'`).
   * Xác định danh sách dịch vụ thuộc từng nhóm hiển thị (`DisplayName`: vd `"Doanh Thu Phòng"`, `"Doanh Thu Dịch Vụ"`).
2. **`SP1602` (Language & Localization)**:
   * Bảng ngôn ngữ ánh xạ khóa `reportviewer.totalbillreport.[displayname]` sang tên tiếng Việt `la.VI`.
3. **`SP3002` (Payments)**:
   * Bảng thanh toán tổng hợp phương thức thanh toán (`PaymentMethod`) và ghi chú thanh toán (`Description`).

### 5.3. Bảng Đối Chiếu Ánh Xạ Sang Schema MySQL PMS Mới

| Đối tượng Legacy | Mục đích trong `sp_293` | Bảng tương ứng MySQL PMS | Cột tương đương trong PMS mới |
|---|---|---|---|
| `vw_018` | View tổng hợp hóa đơn dịch vụ & buồng phòng | `sales_invoices` kết hợp `booking_rooms` | `id`, `booking_id`, `rental_room_id`, `amount`, `outlet`, `invoice_date`, `guest_name`, `note` |
| `SP1610` + `SP1602` | Cấu hình phân nhóm: Doanh Thu Phòng vs Dịch Vụ | Phân nhóm tự động bằng `CASE WHEN outlet = 'RM' THEN 'Doanh Thu Phòng' ELSE 'Doanh Thu Dịch Vụ' END` | Cột tính toán `RevenueGroupName` |
| `SP1306` | Danh mục dịch vụ khách sạn | `hotel_services` | `code`, `name` |
| `SP2000` | Đặt phòng | `bookings` | `id`, `booking_name`, `arrival_date`, `departure_date` |
| `SP2100` | Phòng thuê | `booking_rooms` | `id`, `room_number`, `arrival_date`, `departure_date` |
| `SP1302` | Công ty | `companies` | `id`, `name` |
| `SP3002` | Thanh toán | `payments` + `payment_methods` | `payment_method_id` $\rightarrow$ `pm.code`, `description` |

---

## 6. Thuật Toán Stored Procedure MySQL 8.0 (`rpt_reception_revenue_army`)

```sql
DELIMITER $$

CREATE PROCEDURE rpt_reception_revenue_army(
    IN p_from_date VARCHAR(20),
    IN p_to_date VARCHAR(20),
    IN p_user VARCHAR(50),
    IN p_service VARCHAR(100)
)
READS SQL DATA
BEGIN
    DECLARE v_from DATE;
    DECLARE v_to DATE;

    IF p_from_date LIKE '%/%' THEN
        SET v_from = STR_TO_DATE(LEFT(p_from_date, 10), '%d/%m/%Y');
    ELSE
        SET v_from = CAST(LEFT(p_from_date, 10) AS DATE);
    END IF;

    IF p_to_date LIKE '%/%' THEN
        SET v_to = STR_TO_DATE(LEFT(p_to_date, 10), '%d/%m/%Y');
    ELSE
        SET v_to = CAST(LEFT(p_to_date, 10) AS DATE);
    END IF;

    SELECT 
        si.id AS InvoiceId,
        si.booking_id AS BookingId,
        COALESCE(br.room_number, si.room, '') AS Room,
        DATE_FORMAT(COALESCE(br.arrival_date, b.arrival_date), '%d/%m/%Y') AS ArrivalDate,
        DATE_FORMAT(COALESCE(br.departure_date, b.departure_date), '%d/%m/%Y') AS DepartureDate,
        COALESCE(si.guest_name, b.booking_name, '') AS GuestName,
        COALESCE(si.note, hs.name, 'Dịch vụ') AS DescriptionService,
        ROUND(si.amount, 0) AS Amount,
        COALESCE(pm.code, 'CA') AS HTTT,
        COALESCE(comp.name, 'KHÁCH LẺ') AS Company,
        COALESCE(si.open_time, DATE_FORMAT(si.created_at, '%H:%i')) AS OpenTime,
        COALESCE(p.description, '') AS Note,
        CASE 
            WHEN si.outlet = 'RM' OR hs.code = 'RM' THEN 'Doanh Thu Phòng'
            ELSE 'Doanh Thu Dịch Vụ'
        END AS RevenueGroupName,
        COALESCE(hs.code, si.outlet, 'DV') AS ServiceId,
        COALESCE(hs.name, 'Dịch vụ khác') AS ServiceName
    FROM sales_invoices si
    LEFT JOIN bookings b ON b.id = si.booking_id
    LEFT JOIN booking_rooms br ON br.id = si.rental_room_id
    LEFT JOIN hotel_services hs ON hs.code = si.outlet
    LEFT JOIN companies comp ON comp.id = b.company_id
    LEFT JOIN payments p ON p.id = si.payment_id
    LEFT JOIN payment_methods pm ON pm.id = p.payment_method_id
    WHERE si.status != 'cancelled'
      AND CAST(si.invoice_date AS DATE) BETWEEN v_from AND v_to
      AND (COALESCE(p_user, '') = '' OR si.username = p_user)
      AND (
          COALESCE(p_service, '') = '' 
          OR FIND_IN_SET(COALESCE(hs.code, si.outlet), p_service) > 0
      )
    ORDER BY 
        CASE WHEN RevenueGroupName = 'Doanh Thu Phòng' THEN 1 ELSE 2 END ASC,
        ServiceId ASC,
        si.invoice_date ASC,
        si.id ASC;
END$$

DELIMITER ;
```

---

## 6. Hướng Dẫn Các Bước Triển Khai Cho Agent

1. **Tạo file template reference**: `backend/database/report_templates/reception_revenue_army_reference.php`.
2. **Tạo migration tổng hợp**: `backend/database/migrations/2026_09_22_120000_create_reception_revenue_army_report.php`.
3. **Chạy migration**: `php artisan migrate:all --force`.
4. **Tạo test case**: `backend/tests/Feature/ReceptionRevenueArmyReportTest.php`.
5. **Chạy kiểm thử xác nhận**: `php artisan test --filter=ReceptionRevenueArmyReportTest`.
