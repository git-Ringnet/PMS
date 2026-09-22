# Đặc Tả Kỹ Thuật: Báo Cáo Tiền Đặt Cọc - Dòng 159

> **Tài liệu bàn giao cho Agent triển khai độc lập**  
> Căn cứ bóc tách từ file Excel `DANH MỤC BÁO CÁO.xlsx` (Dòng 159, Sheet 62 `BC tiền đặt cọc`), ảnh giao diện thực tế `[.codex/docs/doc_baocao/images/dong_159_ui_mau.png](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/images/dong_159_ui_mau.png)` và Stored Procedure gốc MS SQL Server `sp_076` ([ProVistaNavyHotel_sp_076.sql](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/sql/ProVistaNavyHotel_sp_076.sql)).

---

## 1. Thông Tin Định Danh & Phân Loại

* **Tên báo cáo tiếng Việt**: Báo cáo tiền đặt cọc
* **Mã báo cáo (`code`)**: `DEPOSITS_SUMMARY` *(Phân biệt với `DEPOSITS_SALE` Dòng 149 vốn dành riêng cho bộ phận Sale/MR)*
* **Mã nguồn dữ liệu (`data_source_code`)**: `RPT_DEPOSITS_SUMMARY`
* **Mã template (`template_code`)**: `DEPOSITS_SUMMARY_REFERENCE`
* **Nhóm báo cáo (`group`)**: `Báo cáo thu ngân` (hoặc `Báo cáo thống kê lễ tân`)
* **Menu hiển thị**: `['frontdesk', 'cashier', 'report']`
* **Vị trí trong Excel**: Dòng 159 (STT 13, Nhóm IV - Báo cáo liên quan tiền, doanh thu)
* **Sheet tham chiếu**: Sheet 62 (`BC tiền đặt cọc`)
* **Tài nguyên đính kèm**:
  * Ảnh UI chụp màn hình hệ thống cũ: [dong_159_ui_mau.png](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/images/dong_159_ui_mau.png)
  * File SQL Stored Procedure legacy: [ProVistaNavyHotel_sp_076.sql](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/sql/ProVistaNavyHotel_sp_076.sql)

---

## 2. Phân Tích Nghiệp Vụ & Điểm Cần Khắc Phục Của Legacy

### 2.1. Yêu cầu cải tiến & sửa lỗi bộ lọc từ Excel
* **Hiện trạng legacy (`sp_076`)**: Bộ lọc các điều kiện bị nhầm lẫn giữa ngày đặt cọc, ngày check-in/out và chưa có chế độ xem cọc đã sử dụng thanh toán trong kỳ.
* **Yêu cầu chuẩn hóa 5 chế độ lọc (`p_option`)**:
  1. **Option 1: Xem theo ngày đặt cọc**: Lọc theo ngày thực hiện thao tác cọc (`payments.date` / `SP3002.Date BETWEEN @DateFrom AND @DateTo`).
  2. **Option 2: Xem theo ngày đến**: Lấy các khoản đặt cọc của các Booking có ngày nhận phòng (`bookings.arrival_date` hoặc `booking_rooms.arrival_date`) nằm trong khoảng `@DateFrom ~ @DateTo`.
  3. **Option 3: Xem theo ngày trả phòng**: Lấy các khoản đặt cọc của các Booking có ngày trả phòng (`bookings.departure_date` hoặc `booking_rooms.departure_date`) nằm trong khoảng `@DateFrom ~ @DateTo`.
  4. **Option 4: Cọc chờ đến (Chưa sử dụng)**: Lọc các khoản cọc phát sinh trong kỳ (`payments.date BETWEEN @DateFrom AND @DateTo`) VÀ chưa bị cấn trừ thanh toán (`payments.payment_id IS NULL` hoặc `payments.status = 1` chưa có hóa đơn thanh toán).
  5. **Option 5: Cọc đã sử dụng trong kỳ**: Doanh thu thu được bằng cọc trong kỳ. Lấy theo ngày hóa đơn thanh toán (`sales_invoices.invoice_date` hoặc `payments.created_at` khi thanh toán dùng cọc) nằm trong khoảng `@DateFrom ~ @DateTo`, JOIN sang `payments` để lấy các khoản cọc đã được giải trừ.
* **Tooltip thông tin**: Mỗi radio option có icon `i` nhỏ, hover vào hiển thị popup tooltip giải thích điều kiện lọc đúng như mô tả trên.
* **Bổ sung cột bắt buộc**: Bổ sung cột **Tên đăng ký** (`BookingName`) ngay sau cột **Mã ĐK/Phòng**.

---

## 3. Phân Tích Bố Cục Giao Diện & Bộ Lọc (UI Design)

### 3.1. Left Filter Panel
* **Độ rộng**: `260px - 280px`.
* **Các bộ lọc**:
  1. `p_date_range`: Khoảng ngày (Mặc định: `$today ~ $today`).
  2. `p_shift`: Ca làm việc (Dropdown danh mục ca `shifts`, mặc định rỗng - Tất cả).
  3. `p_department`: Bộ phận thanh toán (Dropdown `departments`, mặc định rỗng).
  4. `p_outlet`: Outlet phát sinh (Dropdown `outlets`, mặc định rỗng).
  5. `p_company`: Công ty / Đại lý (Dropdown `companies`, mặc định rỗng).
  6. `p_user`: Người thực hiện (Dropdown `users`, mặc định rỗng).
  7. `p_sort_by`: Sắp xếp theo (`PaymentId`, `Date`, `Amount`, `Room`).
  8. `p_order_by`: Thứ tự (`ASC` / `DESC`).
  9. `p_option`: Radio Button nhóm 5 lựa chọn (1: Ngày đặt cọc [mặc định], 2: Ngày đến, 3: Ngày trả phòng, 4: Cọc chờ đến, 5: Cọc đã sử dụng).

### 3.2. Bố Cục Trang Báo Cáo (A4 Landscape)
* **Khổ giấy**: A4 Ngang (`landscape`), lề: `top: 8mm, bottom: 8mm, left: 8mm, right: 8mm`.
* **Header Band**: Logo khách sạn bên trái; bên phải gồm: Địa chỉ, Nhân viên in (`{{hotel.printed_by}}`), Ngày giờ in (`{{hotel.printed_at}}`).
* **Tiêu đề**: **BÁO CÁO TIỀN ĐẶT CỌC** (Căn giữa, in hoa đậm, font 16pt).
* **Thời gian lọc**: `Ngày: {{parameters.p_from_date|date}} ~ {{parameters.p_to_date|date}}`.
* **Ma Trận Lưới Dữ Liệu (12 Cột)**:
  | Cột | Tên Cột | Căn Lề | Độ Rộng | Định Dạng / Binding |
  |---|---|---|---|---|
  | 1 | **Mã Đặt Cọc** | Giữa | 75px | Xanh lá đậm `#2e7d32`, `{{row.MaDatCoc}}` |
  | 2 | **Mã TT** | Giữa | 65px | `{{row.MTT}}` (Mã hóa đơn thanh toán giải trừ nếu có) |
  | 3 | **Ngày Đặt Cọc** | Giữa | 85px | `{{row.PaymentDate|date}}` (`dd-MM-yyyy`) |
  | 4 | **Giờ** | Giữa | 55px | `{{row.TimePayment}}` (`HH:mm`) |
  | 5 | **Mã ĐK/Phòng** | Giữa | 100px | `{{row.BookingRoomCode}}` (vd: `SM6748/509`) |
  | 6 | **Tên Đăng Ký** | Trái | 140px | In đậm, `{{row.BookingName}}` *(Cột mới bổ sung theo yêu cầu)* |
  | 7 | **Công Ty** | Trái | 140px | `{{row.BusinessName}}` |
  | 8 | **Ngày Đến** | Giữa | 85px | `{{row.ArrivalDate|date}}` |
  | 9 | **Ngày Đi** | Giữa | 85px | `{{row.DepartureDate|date}}` |
  | 10 | **Tổng** | Phải | 105px | `{{row.Amount|number}}` (Phân cách hàng nghìn) |
  | 11 | **HTTT** | Giữa | 85px | `{{row.PaymentMethodName}}` (Cash, Bank transfer...) |
  | 12 | **Ghi Chú** | Trái | 160px | `{{row.Description}}` |
  | 13 | **Người Dùng** | Giữa | 75px | `{{row.Username}}` |

* **Phân Tầng Gom Nhóm (Grouping)**:
  * **Cấp 1**: Gom nhóm theo `Deposit` (chữ đỏ `#d32f2f`).
  * **Cấp 2**: Gom nhóm theo `PaymentMethod` (hoặc Trạng thái `MTTGROUP`: Chưa dùng / Đã dùng).
  * **Hàng Tổng Phụ (Subtotal)**: `Tổng phương thức: {{group.sum.Amount|number}}`.
  * **Hàng Tổng Cộng (Grand Total)**: `Tổng cộng: {{aggregate.rows.sum.Amount|number}}` kèm tổng số món cọc `Số lượng cọc: {{aggregate.rows.count}}`.

---

## 4. Stored Procedure Chuẩn Hóa MySQL 8.0 (`rpt_deposits_summary`)

```sql
DELIMITER $$

DROP PROCEDURE IF EXISTS `rpt_deposits_summary`$$

CREATE PROCEDURE `rpt_deposits_summary`(
    IN p_from_date VARCHAR(10),
    IN p_to_date VARCHAR(10),
    IN p_option INT,              -- 1: Ngày cọc, 2: Ngày đến, 3: Ngày đi, 4: Cọc chờ đến, 5: Cọc đã sử dụng
    IN p_shift VARCHAR(20),
    IN p_department VARCHAR(20),
    IN p_outlet VARCHAR(20),
    IN p_company VARCHAR(50),
    IN p_user VARCHAR(50),
    IN p_sort_by VARCHAR(50),
    IN p_order_by VARCHAR(10)
)
BEGIN
    DECLARE v_from DATE;
    DECLARE v_to DATE;
    DECLARE v_prefix VARCHAR(20);

    SET v_from = IF(p_from_date IS NOT NULL AND p_from_date != '', STR_TO_DATE(p_from_date, '%Y-%m-%d'), CURDATE());
    SET v_to = IF(p_to_date IS NOT NULL AND p_to_date != '', STR_TO_DATE(p_to_date, '%Y-%m-%d'), CURDATE());

    SELECT COALESCE(prefix_booking_id, '') INTO v_prefix FROM hotel_settings LIMIT 1;
    IF v_prefix IS NULL THEN SET v_prefix = ''; END IF;

    SELECT 
        p.id AS MaDatCoc,
        COALESCE(p.payment_id, '') AS MTT,
        DATE(p.date) AS PaymentDate,
        TIME_FORMAT(p.created_at, '%H:%i') AS TimePayment,
        CASE 
            WHEN r.room_number IS NOT NULL AND r.room_number != '' THEN CONCAT(v_prefix, b.id, '/', r.room_number)
            ELSE CONCAT(v_prefix, b.id)
        END AS BookingRoomCode,
        COALESCE(b.booking_name, '') AS BookingName,
        COALESCE(c.name, c.company_name, 'Khách lẻ') AS BusinessName,
        DATE(COALESCE(br.arrival_date, b.arrival_date)) AS ArrivalDate,
        DATE(COALESCE(br.departure_date, b.departure_date)) AS DepartureDate,
        ABS(COALESCE(p.amount, 0)) AS Amount,
        COALESCE(pm.name, p.payment_method) AS PaymentMethodName,
        p.payment_method AS PaymentMethod,
        COALESCE(p.description, '') AS Description,
        COALESCE(p.username, p.created_by, '') AS Username,
        CASE 
            WHEN p.payment_id IS NULL OR p.payment_id = '' THEN 0 
            ELSE 1 
        END AS MTTGROUP,
        COALESCE(r.room_number, '') AS Room
    FROM payments p
    LEFT JOIN bookings b ON p.booking_id = b.id OR p.register_id2 = b.id
    LEFT JOIN booking_rooms br ON p.booking_room_id = br.id OR p.rental_room_id2 = br.id
    LEFT JOIN rooms r ON br.room_id = r.id
    LEFT JOIN companies c ON b.company_id = c.id OR p.company_id2 = c.id
    LEFT JOIN payment_methods pm ON p.payment_method = pm.code
    WHERE p.status = 1 
      AND (p.pack2 = 'DPR' OR p.pack4 = 'AP' OR p.amount > 0)
      AND (r.room_number IS NULL OR r.room_number NOT LIKE '0%')
      AND (
          -- Option 1: Ngày thực hiện đặt cọc (mặc định)
          (p_option = 1 AND DATE(p.date) BETWEEN v_from AND v_to)
          -- Option 2: Ngày đến của Booking / Phòng
          OR (p_option = 2 AND (DATE(b.arrival_date) BETWEEN v_from AND v_to OR DATE(br.arrival_date) BETWEEN v_from AND v_to))
          -- Option 3: Ngày đi của Booking / Phòng
          OR (p_option = 3 AND (DATE(b.departure_date) BETWEEN v_from AND v_to OR DATE(br.departure_date) BETWEEN v_from AND v_to))
          -- Option 4: Cọc chờ đến (chưa sử dụng, payment_id is null)
          OR (p_option = 4 AND DATE(p.date) BETWEEN v_from AND v_to AND (p.payment_id IS NULL OR p.payment_id = ''))
          -- Option 5: Cọc đã sử dụng trong kỳ (đã được cấn trừ vào hóa đơn thanh toán trong kỳ)
          OR (p_option = 5 AND p.payment_id IS NOT NULL AND p.payment_id != '' AND DATE(p.updated_at) BETWEEN v_from AND v_to)
      )
      AND (p_company IS NULL OR p_company = '' OR c.id = p_company OR c.code = p_company)
      AND (p_user IS NULL OR p_user = '' OR p.username LIKE CONCAT('%', p_user, '%') OR p.created_by LIKE CONCAT('%', p_user, '%'))
      AND (p_shift IS NULL OR p_shift = '' OR p.shift = p_shift)
    ORDER BY 
        CASE WHEN p_order_by = 'DESC' AND p_sort_by = 'Date' THEN p.date END DESC,
        CASE WHEN p_order_by = 'ASC' AND p_sort_by = 'Date' THEN p.date END ASC,
        CASE WHEN p_order_by = 'DESC' AND p_sort_by = 'Amount' THEN p.amount END DESC,
        CASE WHEN p_order_by = 'ASC' AND p_sort_by = 'Amount' THEN p.amount END ASC,
        p.id ASC;
END$$

DELIMITER ;
```

---

## 5. File Template Reference PHP (`deposits_summary_reference.php`)

> File đặt tại: `backend/database/report_templates/deposits_summary_reference.php`

```php
<?php

use App\Services\TemplateRendererService;

return new class
{
    public function definition(): array
    {
        return [
            'code' => 'DEPOSITS_SUMMARY',
            'name' => 'Báo cáo tiền đặt cọc',
            'report' => 'DEPOSITS_SUMMARY_REFERENCE',
            'page_size' => 'A4',
            'page_orientation' => 'landscape',
            'margin_top' => 8,
            'margin_bottom' => 8,
            'margin_left' => 8,
            'margin_right' => 8,
            'parameter_ui_schema' => [
                'columns' => 1,
                'fields' => [
                    [
                        'name' => 'p_date_range',
                        'label' => 'Ngày',
                        'type' => 'date-range',
                        'default' => ['$today', '$today'],
                        'required' => true,
                    ],
                    [
                        'name' => 'p_shift',
                        'label' => 'Ca',
                        'type' => 'select',
                        'options_source' => 'shifts',
                        'default' => '',
                    ],
                    [
                        'name' => 'p_department',
                        'label' => 'Bộ phận',
                        'type' => 'select',
                        'options_source' => 'departments',
                        'default' => '',
                    ],
                    [
                        'name' => 'p_outlet',
                        'label' => 'Outlet',
                        'type' => 'select',
                        'options_source' => 'outlets',
                        'default' => '',
                    ],
                    [
                        'name' => 'p_company',
                        'label' => 'Công ty',
                        'type' => 'select',
                        'options_source' => 'companies',
                        'default' => '',
                    ],
                    [
                        'name' => 'p_user',
                        'label' => 'Người dùng',
                        'type' => 'select',
                        'options_source' => 'users',
                        'default' => '',
                    ],
                    [
                        'name' => 'p_option',
                        'label' => 'Điều kiện lọc cọc',
                        'type' => 'radio',
                        'options' => [
                            ['value' => 1, 'label' => 'Xem theo ngày đặt cọc', 'tooltip' => 'Lọc theo ngày phát sinh giao dịch đặt cọc'],
                            ['value' => 2, 'label' => 'Xem theo ngày đến', 'tooltip' => 'Lọc theo ngày nhận phòng của Booking'],
                            ['value' => 3, 'label' => 'Xem theo ngày trả phòng', 'tooltip' => 'Lọc theo ngày trả phòng của Booking'],
                            ['value' => 4, 'label' => 'Cọc chờ đến', 'tooltip' => 'Các khoản cọc chưa được sử dụng thanh toán'],
                            ['value' => 5, 'label' => 'Cọc đã sử dụng trong kỳ', 'tooltip' => 'Các khoản cọc đã được dùng để thanh toán hóa đơn trong kỳ'],
                        ],
                        'default' => 1,
                    ],
                ],
            ],
        ];
    }

    public function blocks(): array
    {
        return [
            [
                'id' => 'header_band',
                'type' => 'columns',
                'columns' => [
                    [
                        'width' => '30%',
                        'blocks' => [
                            [
                                'type' => 'image',
                                'src' => '{{hotel.logo_url}}',
                                'height' => '55px',
                            ]
                        ]
                    ],
                    [
                        'width' => '70%',
                        'blocks' => [
                            [
                                'type' => 'text',
                                'content' => '<div style="text-align: right; font-size: 8.5pt; color: #475569;">'
                                    . '<div>Địa chỉ: {{hotel.address}}</div>'
                                    . '<div>Nhân viên: {{hotel.printed_by}} | Ngày in: {{hotel.printed_at}}</div>'
                                    . '</div>',
                            ]
                        ]
                    ]
                ]
            ],
            [
                'id' => 'title_band',
                'type' => 'text',
                'content' => '<div style="text-align: center; margin: 12px 0 6px 0;">'
                    . '<h2 style="font-size: 16pt; font-weight: bold; margin: 0; color: #0f172a;">BÁO CÁO TIỀN ĐẶT CỌC</h2>'
                    . '<p style="font-size: 9pt; color: #334155; margin: 4px 0 0 0;">Ngày: {{parameters.p_from_date|date}} ~ {{parameters.p_to_date|date}}</p>'
                    . '</div>',
            ],
            [
                'id' => 'deposit_table',
                'type' => 'table',
                'dataset' => 'rows',
                'style' => [
                    'width' => '100%',
                    'borderCollapse' => 'collapse',
                    'fontSize' => '9px',
                ],
                'headerStyle' => [
                    'backgroundColor' => '#dee2ed',
                    'border' => '1px solid #aeb5c0',
                    'padding' => '4px 3px',
                    'textAlign' => 'center',
                    'fontWeight' => 'bold',
                    'color' => '#1e293b',
                ],
                'cellStyle' => [
                    'border' => '1px solid #cbd5e1',
                    'padding' => '4px 3px',
                ],
                'groups' => [
                    [
                        'field' => 'PaymentMethodName',
                        'headerCells' => [
                            [
                                'colspan' => 13,
                                'content' => '<span style="color: #b82c2c; font-weight: bold;">Hình thức: {{group.value}}</span>',
                                'style' => ['backgroundColor' => '#f1f5f9', 'padding' => '4px 8px', 'textAlign' => 'left', 'fontWeight' => 'bold'],
                            ]
                        ],
                    ]
                ],
                'columns' => [
                    ['field' => 'MaDatCoc', 'title' => 'Mã Đặt Cọc', 'width' => '7%', 'align' => 'center', 'style' => ['color' => '#2e7d32', 'fontWeight' => 'bold']],
                    ['field' => 'MTT', 'title' => 'Mã TT', 'width' => '6%', 'align' => 'center'],
                    ['field' => 'PaymentDate', 'title' => 'Ngày Đặt Cọc', 'width' => '8%', 'align' => 'center', 'format' => 'date'],
                    ['field' => 'TimePayment', 'title' => 'Giờ', 'width' => '5%', 'align' => 'center'],
                    ['field' => 'BookingRoomCode', 'title' => 'Mã ĐK/Phòng', 'width' => '9%', 'align' => 'center', 'style' => ['fontWeight' => 'bold']],
                    ['field' => 'BookingName', 'title' => 'Tên Đăng Ký', 'width' => '12%', 'align' => 'left'],
                    ['field' => 'BusinessName', 'title' => 'Công Ty', 'width' => '12%', 'align' => 'left'],
                    ['field' => 'ArrivalDate', 'title' => 'Ngày Đến', 'width' => '7%', 'align' => 'center', 'format' => 'date'],
                    ['field' => 'DepartureDate', 'title' => 'Ngày Đi', 'width' => '7%', 'align' => 'center', 'format' => 'date'],
                    ['field' => 'Amount', 'title' => 'Tổng', 'width' => '9%', 'align' => 'right', 'format' => 'number', 'style' => ['fontWeight' => 'bold']],
                    ['field' => 'PaymentMethodName', 'title' => 'HTTT', 'width' => '7%', 'align' => 'center'],
                    ['field' => 'Description', 'title' => 'Ghi Chú', 'width' => '13%', 'align' => 'left'],
                    ['field' => 'Username', 'title' => 'Người Dùng', 'width' => '6%', 'align' => 'center'],
                ],
                'customRows' => [
                    [
                        'scope' => 'group',
                        'cells' => [
                            ['colspan' => 9, 'content' => 'Tổng {{group.value}}:', 'style' => ['textAlign' => 'right', 'fontWeight' => 'bold', 'padding' => '4px 6px']],
                            ['content' => '{{group.sum.Amount|number}}', 'style' => ['textAlign' => 'right', 'fontWeight' => 'bold', 'padding' => '4px 6px']],
                            ['colspan' => 3, 'content' => ''],
                        ],
                    ],
                    [
                        'scope' => 'table',
                        'cells' => [
                            ['colspan' => 5, 'content' => 'Tổng số lượng: {{aggregate.rows.count}} món', 'style' => ['textAlign' => 'left', 'fontWeight' => 'bold', 'backgroundColor' => '#dee2ed', 'padding' => '4px 6px']],
                            ['colspan' => 4, 'content' => 'TỔNG CỘNG:', 'style' => ['textAlign' => 'right', 'fontWeight' => 'bold', 'backgroundColor' => '#dee2ed', 'padding' => '4px 6px']],
                            ['content' => '{{aggregate.rows.sum.Amount|number}}', 'style' => ['textAlign' => 'right', 'fontWeight' => 'bold', 'backgroundColor' => '#dee2ed', 'padding' => '4px 6px']],
                            ['colspan' => 3, 'content' => '', 'style' => ['backgroundColor' => '#dee2ed']],
                        ],
                    ],
                ],
            ],
            [
                'id' => 'signatures_band',
                'type' => 'columns',
                'style' => ['marginTop' => '25px'],
                'columns' => [
                    ['width' => '33.33%', 'blocks' => [['type' => 'text', 'content' => '<div style="text-align: center; font-size: 9pt;"><strong>Người lập biểu</strong><br><em>(Ký, họ tên)</em></div>']]],
                    ['width' => '33.33%', 'blocks' => [['type' => 'text', 'content' => '<div style="text-align: center; font-size: 9pt;"><strong>Trưởng bộ phận</strong><br><em>(Ký, họ tên)</em></div>']]],
                    ['width' => '33.33%', 'blocks' => [['type' => 'text', 'content' => '<div style="text-align: center; font-size: 9pt;"><strong>Kế toán trưởng</strong><br><em>(Ký, họ tên)</em></div>']]],
                ]
            ]
        ];
    }
};
```
## 6. Quyết định triển khai đã chốt

- Option 5 tách một dòng cho từng `payment_debt_settlements` còn hiệu lực.
- Ngày ưu tiên `payment_debt_settlements.payment_date`, fallback `sales_invoices.payment_date`, `sales_invoices.invoice_date`, rồi `payments.date`.
- Số tiền Option 5 lấy từ `payment_debt_settlements.amount`; mã cọc giữ từ payment gốc và mã thanh toán lấy từ invoice/payment settlement.
- Runtime contract chính xác 13 cột theo quyết định nghiệp vụ.
