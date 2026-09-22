# TÀI LIỆU ĐẶC TẢ CHI TIẾT - DÒNG 168: BÁO CÁO DOANH THU THEO NGƯỜI BÁN

> **Dành cho Agent triển khai:** Tài liệu này đặc tả chi tiết 100% về nghiệp vụ, giao diện người dùng, cấu trúc Stored Procedure và cấu hình Form Designer cho Báo cáo doanh thu theo người bán (Dòng 168 trong DANH MỤC BÁO CÁO.xlsx, STT 3.0, Sheet 19).

---

## 1. THÔNG TIN ĐỊNH DANH BÁO CÁO

- **Tên báo cáo:** Báo cáo doanh thu theo người bán
- **Tên tiếng Anh:** Salesperson Revenue Report
- **Mã báo cáo (`report_code`):** `SALESPERSON_REVENUE`
- **Mã Data Source:** `RPT_SALESPERSON_REVENUE`
- **Mã Template tham chiếu:** `SALESPERSON_REVENUE_REFERENCE`
- **Menu điều hướng:** `BÁO CÁO` -> `BÁO CÁO THỐNG KÊ` -> `BÁO CÁO DOANH THU THEO NGƯỜI BÁN`
- **Store gốc chỉ định:**
  - Chế độ lọc theo ngày đến (Army): `ProVistaArmyHotel.dbo.sp_155` (chi tiết) và `sp_158` (tổng hợp) (Lưu tại [.codex/docs/doc_baocao/sql/ProVistaArmyHotel_sp_155.sql](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/sql/ProVistaArmyHotel_sp_155.sql) & [.codex/docs/doc_baocao/sql/ProVistaArmyHotel_sp_158.sql](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/sql/ProVistaArmyHotel_sp_158.sql))
  - Chế độ lọc theo đêm phòng (Navy): Tương tự logic `ProVistaNavyHotel.dbo.sp_055` nhóm theo `@UserSale`.
- **Ảnh UI mẫu thực tế:** Sheet 19 `BC doanh thu theo người bán` kết hợp layout của Báo cáo công suất công ty.

---

## 2. PHÂN TÍCH YÊU CẦU NGHIỆP VỤ & NGUYÊN TẮC THIẾT KẾ

### 2.1. Yêu Cầu Hợp Nhất 2 Kiểu Chạy Khác Nhau Của Hệ Thống Cũ
- **Ghi chú trong Excel Row 168:**
  > *"Hiện tại chỉ chạy theo 1 kiểu - nên mỗi đơn vị store đang khác nhau)*
  > *=> làm thêm option để chọn báo cáo chạy theo kiểu nào*
  > *- lọc theo ngày đến ( store theo army) - ngày tìm kiếm là ngày đến của BK*
  > *- lọc theo đêm phòng ở ( giống báo cáo công suất cty, nhưng group theo user sale)"*
- **Quy tắc triển khai chuẩn hóa trên PMS mới:**
  1. Thêm tham số **`Chế độ lọc (Filter Mode)`** trên giao diện:
     - **Mode 1 - Theo ngày đến của đặt phòng (Arrival Date Mode - Chuẩn Army):** Tìm kiếm tất cả booking có ngày đến (`arrival_date`) rơi vào khoảng thời gian tìm kiếm. Toàn bộ doanh thu phòng và dịch vụ của booking đó được tính cho nhân viên kinh doanh (`SalesPerson`) phụ trách booking.
     - **Mode 2 - Theo đêm phòng lưu trú thực tế trong kỳ (Stay Date / Room-Night Mode - Chuẩn Navy):** Tìm kiếm các đêm phòng và hóa đơn dịch vụ phát sinh thực tế trong khoảng thời gian tìm kiếm (tương tự Báo cáo công suất công ty Dòng 167), sau đó gom nhóm theo người bán (`SalesPerson`).
  2. Thêm tùy chọn **`Hiển thị chi tiết (Show Detail)`**:
     - **Mẫu Tổng Hợp (Default):** Thống kê theo từng nhân viên kinh doanh (Mã NV, Tên NV, %OCC, Đêm phòng, SL khách, ADR thực thu, ADR niêm yết, DT Tiền phòng, DT F&B, DT Khác, Tổng DT).
     - **Mẫu Chi Tiết:** Liệt kê chi tiết từng booking do nhân viên đó phụ trách (Mã ĐK, Tên ĐK, Ngày đến, Ngày đi, Đêm phòng, FOC, Khách, Công ty, Thị trường, DT Tiền phòng, DT F&B, DT Khác, Tổng DT, Người tạo).

---

## 3. THIẾT KẾ GIAO DIỆN & BỘ LỌC (UI SPECIFICATION)

### 3.1. Bảng Tham Số Bộ Lọc (`parameter_ui_schema`)
Bộ lọc nằm ở Left Panel của trang xem báo cáo:

| Tên tham số | Mã tham số | Kiểu dữ liệu | Mặc định | Tùy chọn / Ràng buộc |
|---|---|---|---|---|
| **Chọn ngày** | `p_date_range` | Date Range | Hôm nay (`$today`) | Dải ngày tìm kiếm (`FromDate` ~ `ToDate`) |
| **Kiểu chạy báo cáo**| `p_filter_mode` | Select / Dropdown | `1` (Theo ngày đến) | 1: Theo ngày đến của BK (Army); 2: Theo đêm phòng ở thực tế (Navy) |
| **Người bán** | `p_sales_person` | Select / Dropdown | Tất cả (`''`) | Danh mục nhân viên Sales (`users`) |
| **Thị trường** | `p_market_segment` | Select / Dropdown | Tất cả (`''`) | Phân khúc thị trường |
| **Công ty** | `p_company_id` | Select / Dropdown | Tất cả (`0`) | Danh mục công ty / đại lý |
| **Hiển thị chi tiết** | `p_show_detail` | Toggle Switch | `false` (TẮT) | Bật: Xem danh sách booking chi tiết; Tắt: Xem bảng tổng hợp |

### 3.2. Bố Cục Bảng Tổng Hợp Theo Người Bán (11 Cột)

| Cột | Tiêu đề | Field | Căn lề | Độ rộng | Công thức tính toán |
|:---:|---|---|:---:|:---:|---|
| 1 | **Mã NV** | `SalesPersonCode` | Trái | 75px | Mã nhân viên kinh doanh (màu xanh lá `#16a34a`) |
| 2 | **Người Bán** | `SalesPersonName` | Trái | 170px | Họ tên nhân viên kinh doanh |
| 3 | **Công suất** | `OccupancyRate` | Phải | 75px | `%OCC = [Đêm phòng] / [RoomAvailable] * 100` |
| 4 | **Đêm phòng** | `RoomNight` | Phải | 75px | Tổng số đêm phòng do Sales mang lại |
| 5 | **SL khách** | `GuestQty` | Phải | 65px | Tổng số lượng khách |
| 6 | **Giá phòng TB** | `AverageRate` | Phải | 110px | `ADR thực thu = [DT phòng] / [Đêm phòng]` |
| 7 | **Giá phòng TB (k/g FOC)** | `AverageRateWithoutFoc` | Phải | 130px | `ADR niêm yết = [DT phòng] / ([Đêm phòng] - FOC - HU)` |
| 8 | **Doanh thu phòng** | `RoomRevenue` | Phải | 110px | Doanh thu tiền phòng thực tế |
| 9 | **Doanh thu F&B** | `FbRevenue` | Phải | 100px | Doanh thu ăn uống, nhà hàng |
| 10 | **Doanh thu khác** | `OtherRevenue` | Phải | 100px | Doanh thu spa, giặt là, đưa đón, dịch vụ khác |
| 11 | **Doanh Thu** | `TotalRevenue` | Phải | 110px | `Tổng DT = Cột 8 + Cột 9 + Cột 10` |

### 3.3. Hàng Tổng Cộng (Grand Total)
- Cột 1: `Tổng:`
- Cột 2: `{{aggregate.rows.count}}` (Số lượng nhân viên sales)
- Cột 3: `{{aggregate.rows.sum.OccupancyRate|percent}}`
- Cột 4: `{{aggregate.rows.sum.RoomNight|number}}`
- Cột 5: `{{aggregate.rows.sum.GuestQty|number}}`
- Cột 6: `{{aggregate.rows.sum.RoomRevenue / aggregate.rows.sum.RoomNight|number}}`
- Cột 7: `{{aggregate.rows.sum.RoomRevenue / (aggregate.rows.sum.RoomNight - aggregate.rows.sum.Foc - aggregate.rows.sum.Hu)|number}}`
- Cột 8: `{{aggregate.rows.sum.RoomRevenue|number}}`
- Cột 9: `{{aggregate.rows.sum.FbRevenue|number}}`
- Cột 10: `{{aggregate.rows.sum.OtherRevenue|number}}`
- Cột 11: `{{aggregate.rows.sum.TotalRevenue|number}}`

---

## 4. THIẾT KẾ STORED PROCEDURE MYSQL 8.0 (`rpt_salesperson_revenue`)

```sql
DELIMITER $$
DROP PROCEDURE IF EXISTS `rpt_salesperson_revenue`$$
CREATE PROCEDURE `rpt_salesperson_revenue`(
    IN `p_from_date` VARCHAR(10),
    IN `p_to_date` VARCHAR(10),
    IN `p_filter_mode` INT,           -- 1: Lọc theo ngày đến (Army), 2: Lọc theo đêm phòng ở (Navy)
    IN `p_sales_person` VARCHAR(50),  -- Mã hoặc username nhân viên sales
    IN `p_market_segment` VARCHAR(50),
    IN `p_company_id` INT,
    IN `p_show_detail` INT            -- 0: Tổng hợp theo sales, 1: Chi tiết từng booking
)
BEGIN
    DECLARE v_from DATE;
    DECLARE v_to DATE;
    DECLARE v_room_available INT DEFAULT 100;
    DECLARE v_days INT;

    SET v_from = STR_TO_DATE(p_from_date, '%Y-%m-%d');
    SET v_to = STR_TO_DATE(p_to_date, '%Y-%m-%d');
    SET v_days = DATEDIFF(v_to, v_from) + 1;

    SELECT COUNT(*) INTO v_room_available FROM rooms WHERE status <> 'DELETED' AND is_house_use = 0;
    IF v_room_available = 0 THEN SET v_room_available = 100; END IF;
    SET v_room_available = v_room_available * v_days;

    IF p_show_detail = 0 THEN
        -- BẢNG TỔNG HỢP THEO NGƯỜI BÁN
        SELECT 
            COALESCE(u.code, b.sales_person, 'OTHER') AS SalesPersonCode,
            COALESCE(u.name, b.sales_person, 'Không xác định / Khách lẻ') AS SalesPersonName,
            
            -- % Công suất
            ROUND((SUM(CASE 
                WHEN p_filter_mode = 1 THEN DATEDIFF(b.departure_date, b.arrival_date) * COALESCE(b.total_rooms, 1)
                ELSE DATEDIFF(LEAST(b.departure_date, DATE_ADD(v_to, INTERVAL 1 DAY)), GREATEST(b.arrival_date, v_from)) * COALESCE(b.total_rooms, 1)
            END) * 100.0) / v_room_available, 2) AS OccupancyRate,
            
            -- Đêm phòng
            SUM(CASE 
                WHEN p_filter_mode = 1 THEN DATEDIFF(b.departure_date, b.arrival_date) * COALESCE(b.total_rooms, 1)
                ELSE DATEDIFF(LEAST(b.departure_date, DATE_ADD(v_to, INTERVAL 1 DAY)), GREATEST(b.arrival_date, v_from)) * COALESCE(b.total_rooms, 1)
            END) AS RoomNight,
            
            -- Khách
            SUM(COALESCE(b.total_guests, 1)) AS GuestQty,
            
            -- ADR thực thu
            CASE 
                WHEN SUM(CASE 
                    WHEN p_filter_mode = 1 THEN DATEDIFF(b.departure_date, b.arrival_date) * COALESCE(b.total_rooms, 1)
                    ELSE DATEDIFF(LEAST(b.departure_date, DATE_ADD(v_to, INTERVAL 1 DAY)), GREATEST(b.arrival_date, v_from)) * COALESCE(b.total_rooms, 1)
                END) > 0 
                THEN ROUND(SUM(COALESCE(rev.room_rev, 0)) / SUM(CASE 
                    WHEN p_filter_mode = 1 THEN DATEDIFF(b.departure_date, b.arrival_date) * COALESCE(b.total_rooms, 1)
                    ELSE DATEDIFF(LEAST(b.departure_date, DATE_ADD(v_to, INTERVAL 1 DAY)), GREATEST(b.arrival_date, v_from)) * COALESCE(b.total_rooms, 1)
                END), 0)
                ELSE 0 
            END AS AverageRate,

            -- ADR không FOC
            CASE 
                WHEN (SUM(CASE 
                    WHEN p_filter_mode = 1 THEN DATEDIFF(b.departure_date, b.arrival_date) * COALESCE(b.total_rooms, 1)
                    ELSE DATEDIFF(LEAST(b.departure_date, DATE_ADD(v_to, INTERVAL 1 DAY)), GREATEST(b.arrival_date, v_from)) * COALESCE(b.total_rooms, 1)
                END) - SUM(CASE WHEN b.is_foc = 1 OR b.is_house_use = 1 THEN DATEDIFF(b.departure_date, b.arrival_date) ELSE 0 END)) > 0
                THEN ROUND(SUM(COALESCE(rev.room_rev, 0)) / (SUM(CASE 
                    WHEN p_filter_mode = 1 THEN DATEDIFF(b.departure_date, b.arrival_date) * COALESCE(b.total_rooms, 1)
                    ELSE DATEDIFF(LEAST(b.departure_date, DATE_ADD(v_to, INTERVAL 1 DAY)), GREATEST(b.arrival_date, v_from)) * COALESCE(b.total_rooms, 1)
                END) - SUM(CASE WHEN b.is_foc = 1 OR b.is_house_use = 1 THEN DATEDIFF(b.departure_date, b.arrival_date) ELSE 0 END)), 0)
                ELSE 0
            END AS AverageRateWithoutFoc,

            SUM(COALESCE(rev.room_rev, 0)) AS RoomRevenue,
            SUM(COALESCE(rev.fb_rev, 0)) AS FbRevenue,
            SUM(COALESCE(rev.other_rev, 0)) AS OtherRevenue,
            SUM(COALESCE(rev.room_rev, 0) + COALESCE(rev.fb_rev, 0) + COALESCE(rev.other_rev, 0)) AS TotalRevenue,
            SUM(CASE WHEN b.is_foc = 1 THEN 1 ELSE 0 END) AS Foc,
            SUM(CASE WHEN b.is_house_use = 1 THEN 1 ELSE 0 END) AS Hu
        FROM bookings b
        LEFT JOIN users u ON b.sales_person = u.username OR b.sales_person = u.code
        LEFT JOIN (
            SELECT 
                folio_id,
                SUM(room_amount) AS room_rev,
                SUM(fb_amount) AS fb_rev,
                SUM(other_amount) AS other_rev
            FROM folio_details
            WHERE service_date BETWEEN v_from AND v_to
            GROUP BY folio_id
        ) rev ON b.folio_id = rev.folio_id
        WHERE (
            (p_filter_mode = 1 AND b.arrival_date BETWEEN v_from AND v_to) OR
            (p_filter_mode = 2 AND b.arrival_date <= v_to AND b.departure_date > v_from)
        )
        AND (b.status IS NULL OR b.status NOT IN ('CANCELLED', 'NO_SHOW'))
        AND (p_sales_person IS NULL OR p_sales_person = '' OR b.sales_person = p_sales_person)
        AND (p_market_segment IS NULL OR p_market_segment = '' OR b.market_segment = p_market_segment)
        AND (p_company_id IS NULL OR p_company_id = 0 OR b.company_id = p_company_id)
        GROUP BY b.sales_person, u.code, u.name
        ORDER BY RoomRevenue DESC;

    ELSE
        -- BẢNG CHI TIẾT THEO TỪNG BOOKING
        SELECT 
            b.booking_code AS BookingCode,
            COALESCE(b.booking_name, b.guest_name, '') AS BookingName,
            DATE_FORMAT(b.arrival_date, '%d/%m/%Y') AS ArrivalDate,
            DATE_FORMAT(b.departure_date, '%d/%m/%Y') AS DepartureDate,
            DATEDIFF(b.departure_date, b.arrival_date) * COALESCE(b.total_rooms, 1) AS RoomNight,
            CASE WHEN b.is_foc = 1 THEN 1 ELSE 0 END AS Foc,
            COALESCE(b.total_guests, 1) AS Guests,
            COALESCE(c.name, 'KHÁCH LẺ') AS CompanyName,
            COALESCE(m.name, b.market_segment, 'Nội địa') AS MarketSegment,
            COALESCE(rev.room_rev, 0) AS RoomRevenue,
            COALESCE(rev.fb_rev, 0) AS FbRevenue,
            COALESCE(rev.other_rev, 0) AS OtherRevenue,
            (COALESCE(rev.room_rev, 0) + COALESCE(rev.fb_rev, 0) + COALESCE(rev.other_rev, 0)) AS TotalRevenue,
            b.created_by AS UserCreated,
            COALESCE(u.name, b.sales_person, '') AS SalesPersonName
        FROM bookings b
        LEFT JOIN companies c ON b.company_id = c.id
        LEFT JOIN market_segments m ON b.market_segment_id = m.id
        LEFT JOIN users u ON b.sales_person = u.username OR b.sales_person = u.code
        LEFT JOIN (
            SELECT 
                folio_id,
                SUM(room_amount) AS room_rev,
                SUM(fb_amount) AS fb_rev,
                SUM(other_amount) AS other_rev
            FROM folio_details
            WHERE service_date BETWEEN v_from AND v_to
            GROUP BY folio_id
        ) rev ON b.folio_id = rev.folio_id
        WHERE (
            (p_filter_mode = 1 AND b.arrival_date BETWEEN v_from AND v_to) OR
            (p_filter_mode = 2 AND b.arrival_date <= v_to AND b.departure_date > v_from)
        )
        AND (b.status IS NULL OR b.status NOT IN ('CANCELLED', 'NO_SHOW'))
        AND (p_sales_person IS NULL OR p_sales_person = '' OR b.sales_person = p_sales_person)
        AND (p_market_segment IS NULL OR p_market_segment = '' OR b.market_segment = p_market_segment)
        AND (p_company_id IS NULL OR p_company_id = 0 OR b.company_id = p_company_id)
        ORDER BY b.sales_person, b.arrival_date;
    END IF;
END$$
DELIMITER ;
```

---

## 5. MÃ NGUỒN TEMPLATE REFERENCE PHP (`salesperson_revenue_reference.php`)

```php
<?php

namespace Database\ReportTemplates;

class SalespersonRevenueReference
{
    public static function definition(): array
    {
        return [
            'code' => 'SALESPERSON_REVENUE',
            'name' => 'Báo cáo doanh thu theo người bán',
            'category' => 'Báo cáo thống kê',
            'layout' => 'A4_LANDSCAPE',
            'data_source_code' => 'RPT_SALESPERSON_REVENUE',
            'template_code' => 'SALESPERSON_REVENUE_REFERENCE',
        ];
    }

    public static function contentJson(): array
    {
        return [
            'meta' => [
                'orientation' => 'landscape',
                'pageSize' => 'A4',
                'margins' => ['top' => 10, 'right' => 10, 'bottom' => 10, 'left' => 10],
            ],
            'blocks' => [
                [
                    'id' => 'header_band',
                    'type' => 'columns',
                    'columns' => [
                        [
                            'width' => '30%',
                            'type' => 'image',
                            'src' => '{{hotel.logo}}',
                            'style' => ['maxHeight' => '60px'],
                        ],
                        [
                            'width' => '70%',
                            'type' => 'text',
                            'content' => "<div style='text-align: right; font-size: 11px; line-height: 1.5;'>"
                                . "<div><strong>Địa chỉ:</strong> {{hotel.address}}</div>"
                                . "<div><strong>Nhân viên:</strong> {{auth.user_name}}</div>"
                                . "<div><strong>Ngày:</strong> {{system.now|date('d/m/Y')}}</div>"
                                . "</div>",
                        ],
                    ],
                ],
                [
                    'id' => 'report_title',
                    'type' => 'text',
                    'content' => "<h2 style='text-align: center; margin: 15px 0 5px 0; font-size: 18px; font-weight: bold; text-transform: uppercase;'>BÁO CÁO DOANH THU THEO NGƯỜI BÁN</h2>"
                        . "<div style='text-align: center; font-size: 12px; font-style: italic;'>Ngày: {{parameters.p_from_date|date('d/m/Y')}} ~ {{parameters.p_to_date|date('d/m/Y')}}</div>",
                ],
                [
                    'id' => 'main_table',
                    'type' => 'table',
                    'dataset' => 'details',
                    'columns' => [
                        ['field' => 'SalesPersonCode', 'title' => 'Mã NV', 'width' => '75px', 'align' => 'left', 'cellStyle' => ['color' => '#16a34a', 'fontWeight' => 'bold']],
                        ['field' => 'SalesPersonName', 'title' => 'Người Bán', 'width' => '170px', 'align' => 'left'],
                        ['field' => 'OccupancyRate', 'title' => 'Công suất', 'width' => '75px', 'align' => 'right', 'format' => 'percent'],
                        ['field' => 'RoomNight', 'title' => 'Đêm phòng', 'width' => '75px', 'align' => 'right', 'format' => 'number'],
                        ['field' => 'GuestQty', 'title' => 'SL khách', 'width' => '65px', 'align' => 'right', 'format' => 'number'],
                        ['field' => 'AverageRate', 'title' => 'Giá phòng TB', 'width' => '110px', 'align' => 'right', 'format' => 'number'],
                        ['field' => 'AverageRateWithoutFoc', 'title' => 'Giá phòng TB (k/g FOC)', 'width' => '130px', 'align' => 'right', 'format' => 'number'],
                        ['field' => 'RoomRevenue', 'title' => 'Doanh thu phòng', 'width' => '110px', 'align' => 'right', 'format' => 'number'],
                        ['field' => 'FbRevenue', 'title' => 'Doanh thu F&B', 'width' => '100px', 'align' => 'right', 'format' => 'number'],
                        ['field' => 'OtherRevenue', 'title' => 'Doanh thu khác', 'width' => '100px', 'align' => 'right', 'format' => 'number'],
                        ['field' => 'TotalRevenue', 'title' => 'Doanh Thu', 'width' => '110px', 'align' => 'right', 'format' => 'number'],
                    ],
                    'customRows' => [
                        [
                            'scope' => 'table',
                            'position' => 'footer',
                            'cells' => [
                                ['content' => 'Tổng:', 'style' => ['textAlign' => 'center', 'fontWeight' => 'bold']],
                                ['content' => '{{aggregate.rows.count}}', 'style' => ['textAlign' => 'left', 'fontWeight' => 'bold']],
                                ['content' => '{{aggregate.rows.sum.OccupancyRate|percent}}', 'style' => ['textAlign' => 'right', 'fontWeight' => 'bold']],
                                ['content' => '{{aggregate.rows.sum.RoomNight|number}}', 'style' => ['textAlign' => 'right', 'fontWeight' => 'bold']],
                                ['content' => '{{aggregate.rows.sum.GuestQty|number}}', 'style' => ['textAlign' => 'right', 'fontWeight' => 'bold']],
                                ['content' => '{{aggregate.rows.sum.RoomRevenue / aggregate.rows.sum.RoomNight|number}}', 'style' => ['textAlign' => 'right', 'fontWeight' => 'bold']],
                                ['content' => '{{aggregate.rows.sum.RoomRevenue / (aggregate.rows.sum.RoomNight - aggregate.rows.sum.Foc - aggregate.rows.sum.Hu)|number}}', 'style' => ['textAlign' => 'right', 'fontWeight' => 'bold']],
                                ['content' => '{{aggregate.rows.sum.RoomRevenue|number}}', 'style' => ['textAlign' => 'right', 'fontWeight' => 'bold']],
                                ['content' => '{{aggregate.rows.sum.FbRevenue|number}}', 'style' => ['textAlign' => 'right', 'fontWeight' => 'bold']],
                                ['content' => '{{aggregate.rows.sum.OtherRevenue|number}}', 'style' => ['textAlign' => 'right', 'fontWeight' => 'bold']],
                                ['content' => '{{aggregate.rows.sum.TotalRevenue|number}}', 'style' => ['textAlign' => 'right', 'fontWeight' => 'bold']],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
```
## 6. Quyết định triển khai đã chốt

- Tách thành `SALESPERSON_REVENUE_SUMMARY` và `SALESPERSON_REVENUE_DETAIL`, mỗi report có source/procedure/template riêng.
- Summary contract gồm 11 cột; detail contract gồm 14 cột theo quyết định nghiệp vụ.
- Giữ Mode 1 theo ngày đến và Mode 2 theo đêm phòng ở; không dùng `p_show_detail`.
- Tên người bán ưu tiên system user name, fallback `bookings.sales_person`, `bookings.created_by`, cuối cùng `Chưa phân công`.
