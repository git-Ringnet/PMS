# TÀI LIỆU ĐẶC TẢ CHI TIẾT - DÒNG 167: BÁO CÁO CÔNG SUẤT CÔNG TY

> **Dành cho Agent triển khai:** Tài liệu này đặc tả chi tiết 100% về nghiệp vụ, giao diện người dùng, cấu trúc Stored Procedure và cấu hình Form Designer cho Báo cáo công suất công ty (Dòng 167 trong DANH MỤC BÁO CÁO.xlsx, STT 2.0, Sheet 15).

---

## 1. THÔNG TIN ĐỊNH DANH BÁO CÁO

- **Tên báo cáo:** Báo cáo công suất công ty
- **Tên tiếng Anh:** Company Occupancy Report
- **Mã báo cáo (`report_code`):** `COMPANY_OCCUPANCY`
- **Mã Data Source:** `RPT_COMPANY_OCCUPANCY`
- **Mã Template tham chiếu:** `COMPANY_OCCUPANCY_REFERENCE`
- **Menu điều hướng:** `BÁO CÁO` -> `BÁO CÁO THỐNG KÊ` -> `BÁO CÁO CÔNG SUẤT CÔNG TY`
- **Store gốc chỉ định:**
  - Store đơn vị: `ProVistaNavyHotel.dbo.sp_055` (Lưu tại [.codex/docs/doc_baocao/sql/ProVistaNavyHotel_sp_055.sql](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/sql/ProVistaNavyHotel_sp_055.sql))
  - Store liên chi nhánh: `ProVistaNavyHotel.dbo.sp_055_Division` (Lưu tại [.codex/docs/doc_baocao/sql/ProVistaNavyHotel_sp_055_Division.sql](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/sql/ProVistaNavyHotel_sp_055_Division.sql))
- **Ảnh UI mẫu thực tế:**
  - [.codex/docs/doc_baocao/images/dong_167_ui_mau_1.png](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/images/dong_167_ui_mau_1.png) (Giao diện hiển thị bảng báo cáo 11 cột có đánh số thứ tự)
  - [.codex/docs/doc_baocao/images/dong_167_ui_mau_2.png](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/images/dong_167_ui_mau_2.png) (Ảnh DevExpress Designer chứa công thức tính chuẩn xác)

---

## 2. PHÂN TÍCH YÊU CẦU NGHIỆP VỤ & CÁC LỰA CHỌN (OPTIONS)

### 2.1. Yêu Cầu Chỉ Đạo & Quy Tắc Thiết Kế
- **Ghi chú trong Excel Row 167:**
  > *"lưu ý DTX chạy mẫu báo cáo riêng*
  > *làm trước mẫu chung theo navy*
  > *lưu ý các option trên báo cáo*
  > *- doanh thu tiền phòng bao gồm tiền ăn sáng từ tiền phòng hay không*
  > *- xem mẫu chi tiết*
  > *- xem nhóm theo ngày*
  > *- xem theo thị trường*
  > *- xem theo nguồn khách*
  > *mặc định là xem theo công ty, thị trường và nguồn khách lúc xem chỉ được chọn 1 trong hai"*
- **Chỉ đạo của người dùng:** *"chưa có store galliot nên bỏ qua galliot"*.
- **Quy tắc triển khai chuẩn hóa:**
  1. Triển khai mẫu chung chuẩn theo Navy `sp_055` và `sp_055_Division`.
  2. Bắt buộc hỗ trợ 5 tùy chọn động trên giao diện:
     - `DT phòng bao gồm AS` (Toggle, mặc định BẬT - tương ứng `@BF = 1`): Khi bật, tiền ăn sáng đi kèm giá phòng được tính gộp vào Doanh thu tiền phòng; khi tắt, tiền ăn sáng được tách riêng sang Doanh thu F&B.
     - `Hiển thị chi tiết` (Toggle, mặc định TẮT): Khi tắt hiển thị bảng tổng hợp theo công ty; khi bật chuyển sang hiển thị danh sách chi tiết từng booking (tương đương Báo cáo Dòng 166).
     - `Nhóm theo ngày` (Toggle, mặc định TẮT - tương ứng `@TypeGroup = 'date'`): Gom nhóm dữ liệu theo từng ngày phát sinh lưu trú.
     - `Nhóm theo thị trường` (Toggle, mặc định TẮT - tương ứng `@TypeGroup = 'marketsegment'`).
     - `Nhóm theo nguồn khách` (Toggle, mặc định TẮT - tương ứng `@TypeGroup = 'sourcecode'`).
     - **Ràng buộc tương hỗ (Mutual Exclusion):** Mặc định xem theo Công ty (`@TypeGroup = 'none'`). `Nhóm theo thị trường` và `Nhóm theo nguồn khách` chỉ được phép chọn tối đa 1 trong 2 cùng lúc (radio-like behavior).

---

## 3. THIẾT KẾ GIAO DIỆN & BỘ LỌC (UI SPECIFICATION)

### 3.1. Bảng Tham Số Bộ Lọc (`parameter_ui_schema`)
Bộ lọc nằm ở Left Panel của trang xem báo cáo:

| Tên tham số | Mã tham số | Kiểu dữ liệu | Mặc định | Tùy chọn / Ràng buộc |
|---|---|---|---|---|
| **Chọn ngày** | `p_date_range` | Date Range | Hôm nay (`$today`) | Dải ngày kiểm tra công suất (`FromDate` ~ `ToDate`) |
| **Chọn công ty** | `p_company_id` | Select / Dropdown | Tất cả (`0`) | Danh mục công ty / đại lý du lịch (`companies`) |
| **Thị trường** | `p_market_segment` | Select / Dropdown | Tất cả (`''`) | Danh mục phân khúc thị trường (`market_segments`) |
| **Chọn người dùng** | `p_user_sale` | Select / Dropdown | Tất cả (`''`) | Danh mục nhân viên kinh doanh (`SalesPerson`) |
| **Chọn khu vực** | `p_area` | Select / Dropdown | Tất cả (`''`) | Khu vực địa lý (`areas`) |
| **Hiển thị chi tiết** | `p_show_detail` | Toggle Switch | `false` (TẮT) | Chuyển sang mẫu chi tiết từng booking |
| **DT phòng bao gồm AS**| `p_include_breakfast`| Toggle Switch | `true` (BẬT) | Doanh thu phòng đã gồm ăn sáng hay tách F&B |
| **Nhóm theo ngày** | `p_group_by_date` | Toggle Switch | `false` (TẮT) | Bổ sung cấp gom nhóm theo từng ngày lưu trú |
| **Nhóm theo thị trường**| `p_group_by_market` | Toggle Switch | `false` (TẮT) | Chỉ chọn 1 trong 2 với Nguồn khách |
| **Nhóm theo nguồn khách**| `p_group_by_source` | Toggle Switch | `false` (TẮT) | Chỉ chọn 1 trong 2 với Thị trường |

### 3.2. Bố Cục Bảng Dữ Liệu 11 Cột & Header 2 Tầng
Header có 2 tầng rõ ràng theo đúng ảnh hệ thống legacy:
- **Tầng 1:** Tên cột nghiệp vụ.
- **Tầng 2:** Đánh số thứ tự cột từ `1` đến `11` (in đậm, căn giữa).

| Cột | Tên Cột (Tầng 1) | Số TT (Tầng 2) | Field | Căn lề | Độ rộng | Công thức tính toán |
|:---:|---|:---:|---|:---:|:---:|---|
| 1 | **Mã** | `1` | `CompanyCode` | Trái | 75px | Mã công ty (chữ xanh lá `#16a34a`, VD: `CTY0052`) |
| 2 | **Công Ty** | `2` | `CompanyName` | Trái | 170px | Tên công ty / đại lý (VD: `BINH ĐOÀN 15`, `KHÁCH LẺ`) |
| 3 | **Công suất** | `3` | `OccupancyRate` | Phải | 75px | `%OCC = [Đêm phòng (4)] / [RoomAvailable] * 100` |
| 4 | **Đêm phòng** | `4` | `RoomNight` | Phải | 75px | Tổng đêm phòng: `Room sales + FOC + HU` |
| 5 | **SL khách** | `5` | `GuestQty` | Phải | 65px | Số lượng khách lưu trú |
| 6 | **Giá phòng trung bình** | `6` | `AverageRate` | Phải | 110px | `ADR thực thu = [DT phòng (8)] / [Đêm phòng (4)]` |
| 7 | **Giá phòng TB (không FOC/Giảm giá)** | `7` | `AverageRateWithoutFoc` | Phải | 130px | `ADR niêm yết = [DT phòng (8)] / ([Đêm phòng (4)] - FOC - HU)` |
| 8 | **Doanh thu phòng** | `8` | `RoomRevenue` | Phải | 110px | Doanh thu dịch vụ phòng (`RM`) |
| 9 | **Doanh thu F&B** | `9` | `FbRevenue` | Phải | 100px | Doanh thu nhà hàng, ăn uống |
| 10 | **Doanh thu khác** | `10` | `OtherRevenue` | Phải | 100px | Doanh thu minibar, giặt là, dịch vụ khác |
| 11 | **Doanh Thu** | `11` | `TotalRevenue` | Phải | 110px | `Tổng DT = Cột 8 + Cột 9 + Cột 10` |

### 3.3. Hàng Tổng Cộng (Grand Total)
- Cột 1: `Tổng:`
- Cột 2: `{{aggregate.rows.count}}` (Đếm số lượng công ty)
- Cột 3: `{{aggregate.rows.sum.RoomNight / RoomAvailable * 100|percent}}` (Tổng công suất phòng toàn KS)
- Cột 4: `{{aggregate.rows.sum.RoomNight}}` (Tổng đêm phòng)
- Cột 5: `{{aggregate.rows.sum.GuestQty}}` (Tổng số khách)
- Cột 6: `{{aggregate.rows.sum.RoomRevenue / aggregate.rows.sum.RoomNight|number}}` (ADR thực thu bình quân toàn KS)
- Cột 7: `{{aggregate.rows.sum.RoomRevenue / (aggregate.rows.sum.RoomNight - aggregate.rows.sum.Foc - aggregate.rows.sum.Hu)|number}}` (ADR niêm yết toàn KS)
- Cột 8: `{{aggregate.rows.sum.RoomRevenue|number}}` (Tổng doanh thu phòng)
- Cột 9: `{{aggregate.rows.sum.FbRevenue|number}}` (Tổng doanh thu F&B)
- Cột 10: `{{aggregate.rows.sum.OtherRevenue|number}}` (Tổng doanh thu khác)
- Cột 11: `{{aggregate.rows.sum.TotalRevenue|number}}` (Tổng doanh thu toàn bộ)

### 3.4. Khối Note Ghi Chú Dưới Chân Bảng (Bắt Buộc In Ra Báo Cáo)
Được đặt trực tiếp dưới bảng báo cáo để người xem hiểu rõ ý nghĩa số liệu:
```text
Note (Ghi chú):
3. %OCC: 4 / Room Available
4. Room Night: Room sale + FOC + HU
6. Average Room Rate (Doanh thu TB): 8 / 4
7. Average Room Rate w/o HU,FOC (Doanh thu TB không b/g FOC,HU): 8 / Room sales
8. Room Revenue (DT phòng): RM
9. FB Revenue (Doanh thu FB): Doanh thu từ bộ phận nhà hàng
10. Other Revenue (Doanh thu khác): Các doanh thu khác không bao gồm mục 8, 9
11. Revenue (Doanh thu): Mục 8 + 9 + 10
```

---

## 4. THIẾT KẾ STORED PROCEDURE MYSQL 8.0 (`rpt_company_occupancy`)

```sql
DELIMITER $$
DROP PROCEDURE IF EXISTS `rpt_company_occupancy`$$
CREATE PROCEDURE `rpt_company_occupancy`(
    IN `p_from_date` VARCHAR(10),
    IN `p_to_date` VARCHAR(10),
    IN `p_company_id` INT,
    IN `p_market_segment` VARCHAR(50),
    IN `p_user_sale` VARCHAR(50),
    IN `p_area` VARCHAR(50),
    IN `p_include_breakfast` INT,     -- 1: Gồm ăn sáng, 0: Tách ăn sáng sang FB
    IN `p_type_group` VARCHAR(20)     -- 'none', 'date', 'marketsegment', 'sourcecode'
)
BEGIN
    DECLARE v_from DATE;
    DECLARE v_to DATE;
    DECLARE v_room_available INT DEFAULT 100;
    DECLARE v_days INT;

    SET v_from = STR_TO_DATE(p_from_date, '%Y-%m-%d');
    SET v_to = STR_TO_DATE(p_to_date, '%Y-%m-%d');
    SET v_days = DATEDIFF(v_to, v_from) + 1;

    -- Lấy tổng số phòng kinh doanh sẵn có (Room Available)
    SELECT COUNT(*) INTO v_room_available FROM rooms WHERE status <> 'DELETED' AND is_house_use = 0;
    IF v_room_available = 0 THEN SET v_room_available = 100; END IF;
    SET v_room_available = v_room_available * v_days;

    -- Truy vấn tổng hợp số liệu công suất theo công ty
    SELECT 
        raw.CompanyCode,
        raw.CompanyName,
        
        -- Cột 3: % Công suất
        ROUND((raw.RoomNight * 100.0) / v_room_available, 2) AS OccupancyRate,
        
        -- Cột 4: Đêm phòng
        raw.RoomNight,
        
        -- Cột 5: Số lượng khách
        raw.GuestQty,
        
        -- Cột 6: Giá phòng TB thực thu (ADR)
        CASE WHEN raw.RoomNight > 0 THEN ROUND(raw.RoomRevenue / raw.RoomNight, 0) ELSE 0 END AS AverageRate,
        
        -- Cột 7: Giá phòng TB không FOC/Giảm giá
        CASE 
            WHEN (raw.RoomNight - raw.Foc - raw.Hu) > 0 
            THEN ROUND(raw.RoomRevenue / (raw.RoomNight - raw.Foc - raw.Hu), 0) 
            ELSE 0 
        END AS AverageRateWithoutFoc,
        
        -- Cột 8: Doanh thu phòng
        raw.RoomRevenue,
        
        -- Cột 9: Doanh thu F&B
        raw.FbRevenue,
        
        -- Cột 10: Doanh thu khác
        raw.OtherRevenue,
        
        -- Cột 11: Tổng Doanh thu
        (raw.RoomRevenue + raw.FbRevenue + raw.OtherRevenue) AS TotalRevenue,
        
        raw.Foc,
        raw.Hu,
        raw.GroupHeader
    FROM (
        SELECT 
            COALESCE(c.code, 'CTY0001') AS CompanyCode,
            COALESCE(c.name, 'KHÁCH LẺ') AS CompanyName,
            
            -- Tổng đêm phòng = Đêm phòng phát sinh thực tế trong kỳ
            SUM(DATEDIFF(
                LEAST(b.departure_date, DATE_ADD(v_to, INTERVAL 1 DAY)),
                GREATEST(b.arrival_date, v_from)
            ) * COALESCE(b.total_rooms, 1)) AS RoomNight,
            
            -- Số lượng khách
            SUM(COALESCE(b.total_guests, 1)) AS GuestQty,
            
            -- Doanh thu phòng (Nếu include_breakfast = 1 thì gồm cả gói ăn sáng)
            SUM(COALESCE(rev.room_rev, 0) + CASE WHEN p_include_breakfast = 1 THEN COALESCE(rev.breakfast_rev, 0) ELSE 0 END) AS RoomRevenue,
            
            -- Doanh thu F&B
            SUM(COALESCE(rev.fb_rev, 0) + CASE WHEN p_include_breakfast = 0 THEN COALESCE(rev.breakfast_rev, 0) ELSE 0 END) AS FbRevenue,
            
            -- Doanh thu khác
            SUM(COALESCE(rev.other_rev, 0)) AS OtherRevenue,
            
            -- Số đêm FOC & House Use
            SUM(CASE WHEN b.is_foc = 1 THEN DATEDIFF(LEAST(b.departure_date, DATE_ADD(v_to, INTERVAL 1 DAY)), GREATEST(b.arrival_date, v_from)) ELSE 0 END) AS Foc,
            SUM(CASE WHEN b.is_house_use = 1 THEN DATEDIFF(LEAST(b.departure_date, DATE_ADD(v_to, INTERVAL 1 DAY)), GREATEST(b.arrival_date, v_from)) ELSE 0 END) AS Hu,
            
            -- Tiêu đề nhóm động theo TypeGroup
            CASE 
                WHEN p_type_group = 'marketsegment' THEN COALESCE(m.name, 'Nội địa')
                WHEN p_type_group = 'sourcecode' THEN COALESCE(s.name, 'Trực tiếp')
                ELSE 'Báo Cáo Công Suất Công Ty'
            END AS GroupHeader
        FROM bookings b
        LEFT JOIN companies c ON b.company_id = c.id
        LEFT JOIN market_segments m ON b.market_segment_id = m.id
        LEFT JOIN booking_sources s ON b.source_id = s.id
        LEFT JOIN (
            SELECT 
                folio_id,
                SUM(room_amount) AS room_rev,
                SUM(breakfast_amount) AS breakfast_rev,
                SUM(fb_amount) AS fb_rev,
                SUM(other_amount) AS other_rev
            FROM folio_details
            WHERE service_date BETWEEN v_from AND v_to
            GROUP BY folio_id
        ) rev ON b.folio_id = rev.folio_id
        WHERE b.arrival_date <= v_to AND b.departure_date > v_from
          AND (b.status IS NULL OR b.status NOT IN ('CANCELLED', 'NO_SHOW'))
          AND (p_company_id IS NULL OR p_company_id = 0 OR b.company_id = p_company_id)
          AND (p_market_segment IS NULL OR p_market_segment = '' OR b.market_segment = p_market_segment)
          AND (p_user_sale IS NULL OR p_user_sale = '' OR b.sales_person = p_user_sale)
        GROUP BY c.id, c.code, c.name, GroupHeader
    ) raw
    ORDER BY raw.RoomRevenue DESC;
END$$
DELIMITER ;
```

---

## 5. MÃ NGUỒN TEMPLATE REFERENCE PHP (`company_occupancy_reference.php`)

```php
<?php

namespace Database\ReportTemplates;

class CompanyOccupancyReference
{
    public static function definition(): array
    {
        return [
            'code' => 'COMPANY_OCCUPANCY',
            'name' => 'Báo cáo công suất công ty',
            'category' => 'Báo cáo thống kê',
            'layout' => 'A4_LANDSCAPE',
            'data_source_code' => 'RPT_COMPANY_OCCUPANCY',
            'template_code' => 'COMPANY_OCCUPANCY_REFERENCE',
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
                    'content' => "<h2 style='text-align: center; margin: 15px 0 5px 0; font-size: 18px; font-weight: bold; text-transform: uppercase;'>BÁO CÁO CÔNG SUẤT CÔNG TY</h2>"
                        . "<div style='text-align: center; font-size: 12px; font-style: italic;'>Ngày: {{parameters.p_from_date|date('d/m/Y')}} ~ {{parameters.p_to_date|date('d/m/Y')}}</div>",
                ],
                [
                    'id' => 'main_table',
                    'type' => 'table',
                    'dataset' => 'details',
                    'topHeader' => [
                        'cells' => [
                            ['content' => '1', 'style' => ['textAlign' => 'center', 'fontWeight' => 'bold']],
                            ['content' => '2', 'style' => ['textAlign' => 'center', 'fontWeight' => 'bold']],
                            ['content' => '3', 'style' => ['textAlign' => 'center', 'fontWeight' => 'bold']],
                            ['content' => '4', 'style' => ['textAlign' => 'center', 'fontWeight' => 'bold']],
                            ['content' => '5', 'style' => ['textAlign' => 'center', 'fontWeight' => 'bold']],
                            ['content' => '6', 'style' => ['textAlign' => 'center', 'fontWeight' => 'bold']],
                            ['content' => '7', 'style' => ['textAlign' => 'center', 'fontWeight' => 'bold']],
                            ['content' => '8', 'style' => ['textAlign' => 'center', 'fontWeight' => 'bold']],
                            ['content' => '9', 'style' => ['textAlign' => 'center', 'fontWeight' => 'bold']],
                            ['content' => '10', 'style' => ['textAlign' => 'center', 'fontWeight' => 'bold']],
                            ['content' => '11', 'style' => ['textAlign' => 'center', 'fontWeight' => 'bold']],
                        ],
                    ],
                    'columns' => [
                        ['field' => 'CompanyCode', 'title' => 'Mã', 'width' => '75px', 'align' => 'left', 'cellStyle' => ['color' => '#16a34a', 'fontWeight' => 'bold']],
                        ['field' => 'CompanyName', 'title' => 'Công Ty', 'width' => '170px', 'align' => 'left'],
                        ['field' => 'OccupancyRate', 'title' => 'Công suất', 'width' => '75px', 'align' => 'right', 'format' => 'percent'],
                        ['field' => 'RoomNight', 'title' => 'Đêm phòng', 'width' => '75px', 'align' => 'right', 'format' => 'number'],
                        ['field' => 'GuestQty', 'title' => 'SL khách', 'width' => '65px', 'align' => 'right', 'format' => 'number'],
                        ['field' => 'AverageRate', 'title' => 'Giá phòng trung bình', 'width' => '110px', 'align' => 'right', 'format' => 'number'],
                        ['field' => 'AverageRateWithoutFoc', 'title' => 'Giá phòng TB (không FOC/Giảm giá)', 'width' => '130px', 'align' => 'right', 'format' => 'number'],
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
                [
                    'id' => 'notes_block',
                    'type' => 'text',
                    'content' => "<div style='margin-top: 15px; font-size: 11px; line-height: 1.6; color: #334155;'>"
                        . "<div><strong>Note (Ghi chú):</strong></div>"
                        . "<div>3. %OCC: 4 / Room Available</div>"
                        . "<div>4. Room Night: Room sale + FOC + HU</div>"
                        . "<div>6. Average Room Rate (Doanh thu TB): 8 / 4</div>"
                        . "<div>7. Average Room Rate w/o HU,FOC (Doanh thu TB không b/g FOC,HU): 8 / Room sales</div>"
                        . "<div>8. Room Revenue (DT phòng): RM</div>"
                        . "<div>9. FB Revenue (Doanh thu FB): Doanh thu từ bộ phận nhà hàng</div>"
                        . "<div>10. Other Revenue (Doanh thu khác): Các doanh thu khác không bao gồm mục 8, 9</div>"
                        . "<div>11. Revenue (Doanh thu): Mục 8 + 9 + 10</div>"
                        . "</div>",
                ],
            ],
        ];
    }
}
```
## 6. Quyết định triển khai đã chốt

- Giữ duy nhất template tổng hợp 11 cột.
- Bỏ `p_show_detail`; báo cáo chi tiết được xem độc lập tại `COMPANY_OCCUPANCY_DETAIL` Dòng 166.
- Menu chỉ dùng `reservation` và `frontdesk`; các nhóm Market/Source được snapshot inline tại migration.
