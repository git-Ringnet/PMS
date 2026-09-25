# Đặc Tả Kỹ Thuật: Báo Cáo Chi Tiết Công Suất Công Ty - Dòng 166

> **Tài liệu bàn giao cho Agent triển khai độc lập**  
> Căn cứ bóc tách từ file Excel `DANH MỤC BÁO CÁO.xlsx` (Dòng 166, Sheet 16 `Báo cáo chi tiết công suất công`), ảnh giao diện thực tế `[.codex/docs/doc_baocao/images/dong_166_ui_mau.png](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/images/dong_166_ui_mau.png)` và Stored Procedure gốc MS SQL Server `sp_078` của Navy ([ProVistaNavyHotel_sp_078.sql](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/sql/ProVistaNavyHotel_sp_078.sql)).

---

## Contract runtime đã chốt

- Tham số thực tế: `p_from_date`, `p_to_date`, `p_area`, `p_company`, `p_segment`, `p_user_sale`, `p_source_code`.
- Output gồm 21 field; mã hiển thị chính là `BookingCode`, không dùng `BookingId` trong template runtime.
- `BookingCode` là mã nội bộ có prefix (`prefix_booking_id + bookings.id`); `ReferenceCode` mới là mã OTA/external booking code. Hai mã không được dùng thay thế cho nhau.
- Template runtime dùng A4 ngang, lề `6/4/6/4mm`.
- Các đoạn mẫu legacy dùng `p_date_range` hoặc `BookingId` chỉ là bằng chứng tham khảo.
- Source of truth giao diện là `content_json`; `content_html` được biên dịch từ JSON và `css` chỉ giữ phần trình bày. Không thêm giá trị dữ liệu cố định vào HTML.

## 1. Thông Tin Định Danh & Phân Loại

* **Tên báo cáo tiếng Việt**: Báo cáo chi tiết công suất công ty
* **Mã báo cáo (`code`)**: `COMPANY_OCCUPANCY_DETAIL`
* **Mã nguồn dữ liệu (`data_source_code`)**: `RPT_COMPANY_OCCUPANCY_DETAIL`
* **Mã template (`template_code`)**: `COMPANY_OCCUPANCY_DETAIL_REFERENCE`
* **Nhóm báo cáo (`group`)**: `Báo cáo thống kê` (hoặc `Báo cáo công suất`)
* **Menu hiển thị**: `['reservation', 'report', 'statistics']`
* **Vị trí trong Excel**: Dòng 166 (STT 1, Nhóm V - Danh mục Báo cáo liên quan công suất)
* **Sheet tham chiếu**: Sheet 16 (`Báo cáo chi tiết công suất công`)
* **Lưu ý đặc thù từ Excel**:
  * Lưu ý các thông số cốt lõi: `Revenue`, `RevenueER`, `AverageRoomRateIncludedOthersRoomRevenue` (Giá trung bình phòng có bao gồm doanh thu phòng khác ngoài tiền phòng hay không: 0: không bao gồm, 1: có bao gồm).
  * Các báo cáo: Công suất công ty, Chi tiết công suất công ty, Dự đoán bán phòng, Doanh thu theo người bán (xem theo đêm phòng) có cùng bản chất nghiệp vụ; số liệu khi kiểm tra chéo (công suất phòng, doanh thu phòng, phòng bán, số lượng khách) phải khớp 100% với nhau.
* **Tài nguyên đính kèm**:
  * Ảnh UI chụp màn hình hệ thống cũ: [dong_166_ui_mau.png](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/images/dong_166_ui_mau.png)
  * File SQL Stored Procedure legacy: [ProVistaNavyHotel_sp_078.sql](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/sql/ProVistaNavyHotel_sp_078.sql)

---

## 2. Phân Tích Nghiệp Vụ & Bóc Tách Thuật Toán (sp_078)

### 2.1. Bản chất nghiệp vụ
* Báo cáo phân tích chi tiết từng Booking phát sinh lưu trú trong kỳ (`@From ~ @To`), đối chiếu theo từng đối tác / công ty (`TravelAgency` / `Company`).
* Mỗi dòng dữ liệu đại diện cho 1 Booking với đầy đủ: Đêm phòng (`RoomNight`), Đêm khách (`GuestNight`), Doanh thu phòng, Doanh thu F&B, Doanh thu khác, Giá trung bình thực thu (`ADR`) và Giá trung bình trước khuyến mãi/FOC (`OriginalAmount`).

### 2.2. Các công thức tính toán cốt lõi
1. **Đêm phòng (`RoomNight`)**:
   * Số lượt đêm phòng phát sinh trong kỳ của booking: `COUNT(RentalRoomId)` từ các đêm lưu trú thực tế có `ServiceId = 'RM'` và `IsRoomNight = 1`.
2. **Đêm khách (`GuestNight`)**:
   * Số lượt khách lưu trú thực tế tương ứng với các đêm phòng trong kỳ (`COUNT(CustomerId)`).
3. **Doanh thu phòng (`RoomRevenue`)**:
   * Tổng tiền dịch vụ tiền phòng `RM` (và các dịch vụ cấu hình trong tham số `Revenue` nếu cờ `AverageRoomRateIncludedOthersRoomRevenue = 1`).
4. **Doanh thu F&B (`FbRevenue`)**:
   * Tổng tiền các hóa đơn dịch vụ thuộc bộ phận `FB`.
5. **Doanh thu khác (`OtherRevenue`)**:
   * Tổng tiền các dịch vụ phát sinh ngoài tiền phòng và F&B (Minibar, Giặt là, Vận chuyển, Tour...).
6. **Tổng doanh thu (`TotalRevenue`)**:
   * `TotalRevenue = RoomRevenue + FbRevenue + OtherRevenue`.
7. **Giá phòng trung bình (`AverageRate`)**:
   * `AverageRate = RoomRevenue / RoomNight` (Nếu `RoomNight = 0` thì = 0).
8. **Giá phòng TB không FOC/Giảm giá (`AverageRateOriginal`)**:
   * `AverageRateOriginal = OriginalAmount / RoomNight` (Sử dụng giá gốc trước chiết khấu promotion/FOC).

---

## 3. Phân Tích Bố Cục Giao Diện & Form Designer (UI Design)

### 3.1. Left Filter Panel
* `p_date_range`: Khoảng ngày (Mặc định: `$today ~ $today`).
* `p_company`: Dropdown Công ty (Mặc định rỗng - Tất cả).
* `p_segment`: Dropdown Phân khúc thị trường (`market_segments`).
* `p_source`: Dropdown Nguồn khách (`customer_sources`).
* `p_user`: Dropdown Nhân viên phụ trách / Sale (`users`).
* `p_area`: Dropdown Khu vực / Tầng phòng (Mặc định rỗng).
* Nút bấm: `Hiển thị báo cáo` (`#3b82f6`).

### 3.2. Bố Cục Trang Báo Cáo (A4 Landscape - Khổ Ngang)
* **Khổ giấy**: A4 Ngang (`landscape`), lề: `top: 6mm, bottom: 6mm, left: 6mm, right: 6mm`.
* **Header Band**:
  * Logo khách sạn bên trái: Golden Crest Quy Nhơn.
  * Bên phải: Địa chỉ, Nhân viên in, Ngày in (`dd/MM/yyyy`).
* **Tiêu đề**: **BÁO CÁO CHI TIẾT CÔNG SUẤT CÔNG TY** (Font size 16pt, in hoa đậm).
* **Thời gian lọc**: `Ngày: {{parameters.p_from_date|date}} ~ {{parameters.p_to_date|date}}`.
* **Ma Trận Lưới Dữ Liệu (21 Cột)**:
  | Cột | Tên Cột | Căn Lề | Độ Rộng | Định Dạng / Ghi Chú |
  |---|---|---|---|---|
  | 1 | **Mã ĐK** | Giữa | 55px | Xanh lá đậm `#2e7d32`, in đậm |
  | 2 | **Mã tham chiếu** | Giữa | 75px | Mã code OTA / Booker ref |
  | 3 | **Tên công ty** | Trái | 110px | Công ty / Đại lý / Khách lẻ |
  | 4 | **Tên khách** | Trái | 120px | Họ tên khách chính |
  | 5 | **Thị trường** | Giữa | 55px | Code phân khúc (WK, COR, OTA...) |
  | 6 | **Nguồn khách** | Giữa | 55px | Code nguồn (WK, COR, OTA...) |
  | 7 | **Ngày tạo** | Giữa | 65px | `dd/MM/yyyy` |
  | 8 | **Ngày đến** | Giữa | 65px | `dd/MM/yyyy` |
  | 9 | **Ngày đi** | Giữa | 65px | `dd/MM/yyyy` |
  | 10 | **Đêm** | Giữa | 45px | Số đêm booking (`NoOfNight`) |
  | 11 | **Phòng** | Giữa | 45px | Số lượng phòng (`NoOfRoom`) |
  | 12 | **Đêm phòng** | Giữa | 55px | Số đêm phòng thực phát sinh (`RoomNight`) |
  | 13 | **Đêm khách** | Giữa | 55px | Số đêm khách thực phát sinh (`GuestNight`) |
  | 14 | **Giá phòng trung bình** | Phải | 85px | Phân cách hàng nghìn (`ADR`) |
  | 15 | **Giá phòng TB(không FOC/Giảm giá)** | Phải | 95px | Giá gốc trung bình |
  | 16 | **Doanh thu phòng** | Phải | 85px | Tiền phòng thực thu |
  | 17 | **Doanh thu F&B** | Phải | 75px | Doanh thu nhà hàng / ẩm thực |
  | 18 | **Doanh thu khác** | Phải | 75px | Doanh thu dịch vụ khác |
  | 19 | **Doanh thu** | Phải | 90px | In đậm, Tổng doanh thu |
  | 20 | **Loại phòng** | Giữa | 55px | Mã hạng phòng (DLXC, DLXOV...) |
  | 21 | **Quốc tịch** | Giữa | 50px | Mã quốc gia (FIN, VNM, KOR, FRA...) |

* **Hàng Tổng Cộng (Grand Total)**:
  * Cột 1: `Tổng: {{aggregate.rows.count}}` (Tổng số Booking).
  * Cột 10 (`Đêm`): `{{aggregate.rows.sum.NoOfNight}}`.
  * Cột 11 (`Phòng`): `{{aggregate.rows.sum.NoOfRoom}}`.
  * Cột 12 (`Đêm phòng`): `{{aggregate.rows.sum.RoomNight}}`.
  * Cột 13 (`Đêm khách`): `{{aggregate.rows.sum.GuestNight}}`.
  * Cột 14 (`Giá phòng TB`): Bình quân doanh thu / đêm phòng toàn bảng.
  * Cột 15 (`Giá phòng TB không FOC`): Bình quân giá gốc toàn bảng.
  * Cột 16 (`Doanh thu phòng`): `{{aggregate.rows.sum.RoomRevenue|number}}`.
  * Cột 17 (`Doanh thu F&B`): `{{aggregate.rows.sum.FbRevenue|number}}`.
  * Cột 18 (`Doanh thu khác`): `{{aggregate.rows.sum.OtherRevenue|number}}`.
  * Cột 19 (`Doanh thu`): `{{aggregate.rows.sum.TotalRevenue|number}}`.

---

## 4. Stored Procedure Chuẩn Hóa MySQL 8.0 (`rpt_company_occupancy_detail`)

> SQL mẫu phía dưới có thể còn alias `BookingId` từ legacy. Khi chạy PMS mới, dùng đúng 21 alias trong `field_schema`, đặc biệt `BookingCode` và `ReferenceCode` như contract ở trên.

```sql
DELIMITER $$

DROP PROCEDURE IF EXISTS `rpt_company_occupancy_detail`$$

CREATE PROCEDURE `rpt_company_occupancy_detail`(
    IN p_from_date VARCHAR(10),
    IN p_to_date VARCHAR(10),
    IN p_area VARCHAR(20),
    IN p_company VARCHAR(50),
    IN p_segment VARCHAR(20),
    IN p_user_sale VARCHAR(50),
    IN p_source_code VARCHAR(20)
)
BEGIN
    DECLARE v_from DATE;
    DECLARE v_to DATE;
    DECLARE v_prefix VARCHAR(20);

    SET v_from = IF(p_from_date IS NOT NULL AND p_from_date != '', STR_TO_DATE(p_from_date, '%Y-%m-%d'), CURDATE());
    SET v_to = IF(p_to_date IS NOT NULL AND p_to_date != '', STR_TO_DATE(p_to_date, '%Y-%m-%d'), CURDATE());

    SELECT COALESCE(prefix_booking_id, '') INTO v_prefix FROM hotel_settings LIMIT 1;
    IF v_prefix IS NULL THEN SET v_prefix = ''; END IF;

    -- Bảng tạm tổng hợp doanh thu theo booking trong kỳ
    DROP TEMPORARY TABLE IF EXISTS tmp_bkg_rev;
    CREATE TEMPORARY TABLE tmp_bkg_rev AS
    SELECT 
        sb.booking_id,
        SUM(CASE WHEN sb.service_id = 'RM' THEN sb.amount ELSE 0 END) AS room_rev,
        SUM(CASE WHEN sb.department = 'FB' OR sb.department_id = 'FB' THEN sb.amount ELSE 0 END) AS fb_rev,
        SUM(CASE WHEN sb.service_id != 'RM' AND COALESCE(sb.department, sb.department_id, '') != 'FB' THEN sb.amount ELSE 0 END) AS other_rev,
        SUM(COALESCE(sb.original_rate, sb.amount)) AS original_amount,
        COUNT(DISTINCT CASE WHEN sb.service_id = 'RM' THEN sb.service_date END) AS room_nights
    FROM service_bills sb
    WHERE sb.status != 3 
      AND DATE(sb.service_date) BETWEEN v_from AND v_to
    GROUP BY sb.booking_id;

    -- Query chính trả về ma trận dữ liệu
    SELECT 
        b.id AS BookingIdRaw,
        CONCAT(v_prefix, b.id) AS BookingId,
        COALESCE(b.booking_code, '') AS BookingCode,
        COALESCE(c.name, c.company_name, 'Khách lẻ') AS CompanyName,
        COALESCE(g.full_name, b.booking_name, '') AS GuestName,
        COALESCE(ms.code, 'WK') AS MarketSegment,
        COALESCE(cs.code, 'WK') AS SourceCode,
        DATE(b.created_at) AS BookingDate,
        DATE(b.arrival_date) AS ArrivalDate,
        DATE(b.departure_date) AS DepartureDate,
        GREATEST(DATEDIFF(b.departure_date, b.arrival_date), 1) AS NoOfNight,
        COALESCE((SELECT COUNT(*) FROM booking_rooms WHERE booking_id = b.id AND status NOT IN (3, 100)), 1) AS NoOfRoom,
        COALESCE(rev.room_nights, 0) AS RoomNight,
        COALESCE((SELECT COUNT(bg.guest_id) FROM booking_room_guests bg 
                  INNER JOIN booking_rooms br2 ON bg.booking_room_id = br2.id 
                  WHERE br2.booking_id = b.id), 1) * COALESCE(rev.room_nights, 1) AS GuestNight,
        -- Giá phòng TB thực thu
        CASE 
            WHEN COALESCE(rev.room_nights, 0) > 0 THEN ROUND(rev.room_rev / rev.room_nights, 0)
            ELSE 0 
        END AS AverageRate,
        -- Giá phòng TB không FOC/Giảm giá
        CASE 
            WHEN COALESCE(rev.room_nights, 0) > 0 THEN ROUND(COALESCE(rev.original_amount, rev.room_rev) / rev.room_nights, 0)
            ELSE 0 
        END AS AverageRateOriginal,
        COALESCE(rev.room_rev, 0) AS RoomRevenue,
        COALESCE(rev.fb_rev, 0) AS FbRevenue,
        COALESCE(rev.other_rev, 0) AS OtherRevenue,
        COALESCE(rev.room_rev, 0) + COALESCE(rev.fb_rev, 0) + COALESCE(rev.other_rev, 0) AS TotalRevenue,
        COALESCE(rc.code, '') AS RoomType,
        COALESCE(nat.code, 'VNM') AS Nationality
    FROM bookings b
    LEFT JOIN tmp_bkg_rev rev ON b.id = rev.booking_id
    LEFT JOIN companies c ON b.company_id = c.id
    LEFT JOIN market_segments ms ON b.market_segment_id = ms.id
    LEFT JOIN customer_sources cs ON b.source_id = cs.id
    LEFT JOIN guests g ON b.guest_id = g.id
    LEFT JOIN nationalities nat ON g.nationality_id = nat.id
    LEFT JOIN booking_rooms br ON br.id = (SELECT MIN(id) FROM booking_rooms WHERE booking_id = b.id AND status NOT IN (3, 100))
    LEFT JOIN room_classes rc ON br.room_class_id = rc.id
    WHERE (
        (DATE(b.arrival_date) BETWEEN v_from AND v_to OR DATE(b.departure_date) BETWEEN v_from AND v_to)
        OR (DATE(b.arrival_date) <= v_from AND DATE(b.departure_date) >= v_to)
        OR (rev.booking_id IS NOT NULL)
    )
    AND b.status NOT IN (3, 100)
    AND (p_company IS NULL OR p_company = '' OR c.id = p_company OR c.code = p_company)
    AND (p_segment IS NULL OR p_segment = '' OR ms.code = p_segment OR ms.id = p_segment)
    AND (p_source_code IS NULL OR p_source_code = '' OR cs.code = p_source_code OR cs.id = p_source_code)
    AND (p_user_sale IS NULL OR p_user_sale = '' OR b.salesperson_id = p_user_sale OR b.created_by LIKE CONCAT('%', p_user_sale, '%'))
    ORDER BY b.id ASC;

    DROP TEMPORARY TABLE IF EXISTS tmp_bkg_rev;
END$$

DELIMITER ;
```

---

## 5. File Template Reference PHP (`company_occupancy_detail_reference.php`)

> File đặt tại: `backend/database/report_templates/company_occupancy_detail_reference.php`

```php
<?php

use App\Services\TemplateRendererService;

return new class
{
    public function definition(): array
    {
        return [
            'code' => 'COMPANY_OCCUPANCY_DETAIL',
            'name' => 'Báo cáo chi tiết công suất công ty',
            'report' => 'COMPANY_OCCUPANCY_DETAIL_REFERENCE',
            'page_size' => 'A4',
            'page_orientation' => 'landscape',
            'margin_top' => 6,
            'margin_bottom' => 6,
            'margin_left' => 6,
            'margin_right' => 6,
            'parameter_ui_schema' => [
                'columns' => 1,
                'fields' => [
                    [
                        'name' => 'p_date_range',
                        'label' => 'Chọn ngày',
                        'type' => 'date-range',
                        'default' => ['$today', '$today'],
                        'required' => true,
                    ],
                    [
                        'name' => 'p_company',
                        'label' => 'Chọn công ty',
                        'type' => 'select',
                        'options_source' => 'companies',
                        'default' => '',
                    ],
                    [
                        'name' => 'p_segment',
                        'label' => 'Thị trường',
                        'type' => 'select',
                        'options_source' => 'market-segments',
                        'default' => '',
                    ],
                    [
                        'name' => 'p_source_code',
                        'label' => 'Nguồn khách',
                        'type' => 'select',
                        'options_source' => 'customer-sources',
                        'default' => '',
                    ],
                    [
                        'name' => 'p_user_sale',
                        'label' => 'Chọn người dùng',
                        'type' => 'select',
                        'options_source' => 'users',
                        'default' => '',
                    ],
                    [
                        'name' => 'p_area',
                        'label' => 'Chọn khu vực',
                        'type' => 'select',
                        'options_source' => 'room-areas',
                        'default' => '',
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
                                'height' => '50px',
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
                'content' => '<div style="text-align: center; margin: 10px 0 6px 0;">'
                    . '<h2 style="font-size: 15pt; font-weight: bold; margin: 0; color: #0f172a;">BÁO CÁO CHI TIẾT CÔNG SUẤT CÔNG TY</h2>'
                    . '<p style="font-size: 8.5pt; color: #334155; margin: 4px 0 0 0;">Ngày: {{parameters.p_from_date|date}} ~ {{parameters.p_to_date|date}}</p>'
                    . '</div>',
            ],
            [
                'id' => 'occupancy_detail_table',
                'type' => 'table',
                'dataset' => 'rows',
                'style' => [
                    'width' => '100%',
                    'borderCollapse' => 'collapse',
                    'fontSize' => '8.5px',
                ],
                'headerStyle' => [
                    'backgroundColor' => '#dee2ed',
                    'border' => '1px solid #94a3b8',
                    'padding' => '4px 2px',
                    'textAlign' => 'center',
                    'fontWeight' => 'bold',
                    'color' => '#0f172a',
                ],
                'cellStyle' => [
                    'border' => '1px solid #cbd5e1',
                    'padding' => '3px 2px',
                ],
                'columns' => [
                    ['field' => 'BookingId', 'title' => 'Mã ĐK', 'width' => '4.5%', 'align' => 'center', 'style' => ['color' => '#2e7d32', 'fontWeight' => 'bold']],
                    ['field' => 'BookingCode', 'title' => 'Mã tham chiếu', 'width' => '5.5%', 'align' => 'center'],
                    ['field' => 'CompanyName', 'title' => 'Tên công ty', 'width' => '7.5%', 'align' => 'left'],
                    ['field' => 'GuestName', 'title' => 'Tên khách', 'width' => '8.5%', 'align' => 'left'],
                    ['field' => 'MarketSegment', 'title' => 'Thị trường', 'width' => '3.5%', 'align' => 'center'],
                    ['field' => 'SourceCode', 'title' => 'Nguồn khách', 'width' => '3.5%', 'align' => 'center'],
                    ['field' => 'BookingDate', 'title' => 'Ngày tạo', 'width' => '5%', 'align' => 'center', 'format' => 'date'],
                    ['field' => 'ArrivalDate', 'title' => 'Ngày đến', 'width' => '5%', 'align' => 'center', 'format' => 'date'],
                    ['field' => 'DepartureDate', 'title' => 'Ngày đi', 'width' => '5%', 'align' => 'center', 'format' => 'date'],
                    ['field' => 'NoOfNight', 'title' => 'Đêm', 'width' => '3%', 'align' => 'center'],
                    ['field' => 'NoOfRoom', 'title' => 'Phòng', 'width' => '3%', 'align' => 'center'],
                    ['field' => 'RoomNight', 'title' => 'Đêm phòng', 'width' => '3.5%', 'align' => 'center'],
                    ['field' => 'GuestNight', 'title' => 'Đêm khách', 'width' => '3.5%', 'align' => 'center'],
                    ['field' => 'AverageRate', 'title' => 'Giá phòng trung bình', 'width' => '6.5%', 'align' => 'right', 'format' => 'number'],
                    ['field' => 'AverageRateOriginal', 'title' => 'Giá phòng TB(không FOC/Giảm giá)', 'width' => '7.5%', 'align' => 'right', 'format' => 'number'],
                    ['field' => 'RoomRevenue', 'title' => 'Doanh thu phòng', 'width' => '6.5%', 'align' => 'right', 'format' => 'number'],
                    ['field' => 'FbRevenue', 'title' => 'Doanh thu F&B', 'width' => '5.5%', 'align' => 'right', 'format' => 'number'],
                    ['field' => 'OtherRevenue', 'title' => 'Doanh thu khác', 'width' => '5.5%', 'align' => 'right', 'format' => 'number'],
                    ['field' => 'TotalRevenue', 'title' => 'Doanh thu', 'width' => '7%', 'align' => 'right', 'format' => 'number', 'style' => ['fontWeight' => 'bold']],
                    ['field' => 'RoomType', 'title' => 'Loại phòng', 'width' => '4%', 'align' => 'center'],
                    ['field' => 'Nationality', 'title' => 'Quốc tịch', 'width' => '3.5%', 'align' => 'center'],
                ],
                'customRows' => [
                    [
                        'scope' => 'table',
                        'cells' => [
                            ['colspan' => 9, 'content' => 'Tổng: {{aggregate.rows.count}}', 'style' => ['textAlign' => 'left', 'fontWeight' => 'bold', 'backgroundColor' => '#dee2ed', 'padding' => '4px 6px']],
                            ['content' => '{{aggregate.rows.sum.NoOfNight}}', 'style' => ['textAlign' => 'center', 'fontWeight' => 'bold', 'backgroundColor' => '#dee2ed']],
                            ['content' => '{{aggregate.rows.sum.NoOfRoom}}', 'style' => ['textAlign' => 'center', 'fontWeight' => 'bold', 'backgroundColor' => '#dee2ed']],
                            ['content' => '{{aggregate.rows.sum.RoomNight}}', 'style' => ['textAlign' => 'center', 'fontWeight' => 'bold', 'backgroundColor' => '#dee2ed']],
                            ['content' => '{{aggregate.rows.sum.GuestNight}}', 'style' => ['textAlign' => 'center', 'fontWeight' => 'bold', 'backgroundColor' => '#dee2ed']],
                            ['content' => '{{aggregate.rows.avg.AverageRate|number}}', 'style' => ['textAlign' => 'right', 'fontWeight' => 'bold', 'backgroundColor' => '#dee2ed']],
                            ['content' => '{{aggregate.rows.avg.AverageRateOriginal|number}}', 'style' => ['textAlign' => 'right', 'fontWeight' => 'bold', 'backgroundColor' => '#dee2ed']],
                            ['content' => '{{aggregate.rows.sum.RoomRevenue|number}}', 'style' => ['textAlign' => 'right', 'fontWeight' => 'bold', 'backgroundColor' => '#dee2ed']],
                            ['content' => '{{aggregate.rows.sum.FbRevenue|number}}', 'style' => ['textAlign' => 'right', 'fontWeight' => 'bold', 'backgroundColor' => '#dee2ed']],
                            ['content' => '{{aggregate.rows.sum.OtherRevenue|number}}', 'style' => ['textAlign' => 'right', 'fontWeight' => 'bold', 'backgroundColor' => '#dee2ed']],
                            ['content' => '{{aggregate.rows.sum.TotalRevenue|number}}', 'style' => ['textAlign' => 'right', 'fontWeight' => 'bold', 'backgroundColor' => '#dee2ed']],
                            ['colspan' => 2, 'content' => '', 'style' => ['backgroundColor' => '#dee2ed']],
                        ],
                    ],
                ],
            ],
            [
                'id' => 'signatures_band',
                'type' => 'columns',
                'style' => ['marginTop' => '25px'],
                'columns' => [
                    ['width' => '33.33%', 'blocks' => [['type' => 'text', 'content' => '<div style="text-align: center; font-size: 8.5pt;"><strong>Người lập biểu</strong><br><em>(Ký, họ tên)</em></div>']]],
                    ['width' => '33.33%', 'blocks' => [['type' => 'text', 'content' => '<div style="text-align: center; font-size: 8.5pt;"><strong>Trưởng bộ phận kinh doanh</strong><br><em>(Ký, họ tên)</em></div>']]],
                    ['width' => '33.33%', 'blocks' => [['type' => 'text', 'content' => '<div style="text-align: center; font-size: 8.5pt;"><strong>Giám đốc</strong><br><em>(Ký, họ tên)</em></div>']]],
                ]
            ]
        ];
    }
};
```
## 6. Trạng thái triển khai

- Đã đăng ký `COMPANY_OCCUPANCY_DETAIL` với 21 cột contract.
- Market và customer source dùng options inline snapshot trong migration; không mở rộng lookup dùng chung.
- Doanh thu dùng room-night đã post và service bill hiện có; không tạo bảng hoặc function mới.
