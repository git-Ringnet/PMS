# TÀI LIỆU ĐẶC TẢ CHI TIẾT - DÒNG 169: BÁO CÁO DỰ ĐOÁN BÁN PHÒNG

> **Dành cho Agent triển khai:** Tài liệu này đặc tả chi tiết 100% về nghiệp vụ, mô hình dữ liệu, công thức toán học, cấu trúc Stored Procedure MySQL 8.0 và cấu hình Form Designer cho Báo cáo dự đoán bán phòng (Dòng 169 trong DANH MỤC BÁO CÁO.xlsx, STT 4.0 thuộc Nhóm V - Báo cáo liên quan công suất; tương ứng Sheet 22, Sheet 39, Sheet 80).

---

## 1. THÔNG TIN ĐỊNH DANH BÁO CÁO

- **Tên báo cáo:** Báo cáo dự đoán bán phòng
- **Tên tiếng Anh:** Room Forecast Report (Room Sales Forecast)
- **Mã báo cáo (`report_code`):** `ROOM_FORECAST`
- **Mã Data Source:** `RPT_ROOM_FORECAST`
- **Mã Template tham chiếu:** `ROOM_FORECAST_REFERENCE`
- **Menu điều hướng:**
  - Lễ tân (FO): `BÁO CÁO` -> `BÁO CÁO PHÒNG` -> `BÁO CÁO DỰ ĐOÁN BÁN PHÒNG`
  - Buồng phòng (HK): `BUỒNG PHÒNG` -> `BÁO CÁO` -> `BÁO CÁO DỰ ĐOÁN BÁN PHÒNG`
- **Store gốc chỉ định:** `ProVistaNavyHotel.dbo.sp_023` và `ProVistaArmyHotel.dbo.sp_023` (Lưu tại [.codex/docs/doc_baocao/sql/ProVistaNavyHotel_sp_023.sql](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/sql/ProVistaNavyHotel_sp_023.sql))
- **Ảnh UI mẫu thực tế:**
  - [.codex/docs/doc_baocao/images/dong_169_ui_mau_1.png](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/images/dong_169_ui_mau_1.png) (Mẫu đầy đủ FO - 17 cột có Doanh thu & ADR)
  - [.codex/docs/doc_baocao/images/dong_169_ui_mau_2.png](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/images/dong_169_ui_mau_2.png) (Menu vị trí Lễ tân)
  - [.codex/docs/doc_baocao/images/dong_169_ui_mau_3.png](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/images/dong_169_ui_mau_3.png) (Mẫu Buồng phòng HK - 13 cột chỉ hoạt động phòng, không hiển thị tiền)

---

## 2. PHÂN TÍCH NGHIỆP VỤ & BẢN CHẤT TOÁN HỌC

### 2.1. Vị Trí Nghiệp Vụ & Quy Tắc Đối Soát Chéo Bất Biến (Cross-Verification)
Theo ghi chú tại Row 166 của file Excel:
> *"Các báo cáo: Công suất công ty (Dòng 167), Chi tiết công suất công ty (Dòng 166), Dự đoán bán phòng (Dòng 169), Doanh thu theo người bán (Dòng 168) là các báo cáo CÙNG BẢN CHẤT nên được dùng để so sánh đối chiếu số liệu chéo với nhau. Số liệu về Đêm phòng, Doanh thu phòng, Số lượng khách, ADR giữa các báo cáo này KHI KIỂM TRA CHÉO PHẢI KHỚP 100% VỚI NHAU."*

### 2.2. Chi Tiết Các Chỉ Tiêu & Công Thức Toán Học Trong Báo Cáo
Báo cáo phân tích từng ngày trong dải ngày tìm kiếm (`@FromDate` đến `@ToDate`):

1. **Phòng đi (`DepRooms` - Cột 2) & Khách đi (`DepAdult` - Cột 3)**:
   - Là các phòng/khách có `departure_date = @CurrentDate` và trạng thái hợp lệ (`status IN ('CHECKED_OUT', 'INHOUSE', 'CONFIRMED')`).
2. **Phòng đến (`ArrRooms` - Cột 4) & Khách đến (`ArrAdult` - Cột 5)**:
   - Là các phòng/khách có `arrival_date = @CurrentDate` và trạng thái hợp lệ (`status IN ('CONFIRMED', 'INHOUSE', 'CHECKED_OUT')`).
3. **Phòng ở (`OccRooms` - Cột 6) & Khách ở (`OccAdult` - Cột 7)**:
   - Tổng số phòng lưu trú qua đêm trong ngày: `@CurrentDate BETWEEN arrival_date AND departure_date - INTERVAL 1 DAY`.
4. **Nội bộ (`HouseUse` - Cột 8)**:
   - Số phòng lưu trú nội bộ của khách sạn (nhân viên, ban giám đốc, phòng kỹ thuật) với `is_internal = 1` hoặc `house_use = 1` hoặc `rate_code = 'HU'`.
5. **Phòng miễn phí (`Phòng MP / FOCAll` - Cột 9)**:
   - Số phòng khách ở miễn phí không tính tiền: `rate = 0` hoặc `rate_code = 'FOC'` hoặc `rate_code IS NULL`.
6. **Phòng bán thực tế (`P.Bán / RoomSales` - Cột 10)**:
   $$\text{P.Bán} = \text{P.Ở (OccRooms)} - \text{Nội bộ (HouseUse)} - \text{Phòng MP (FOCAll)}$$
7. **Doanh thu tiền phòng (`Doanh Thu / Revenue` - Cột 11)**:
   - Tiền phòng thuần (`RM`) + Phụ thu tiền phòng (`ER` / `EB`).
   - Nếu bật tùy chọn `DT bao gồm ăn sáng` (`@BF = 1`): Doanh thu cộng thêm tiền ăn sáng đi kèm trong gói phòng.
   - Nếu tắt (`@BF = 0`): Bóc tách trừ phần doanh thu ăn sáng ra khỏi tiền phòng.
8. **Giá phòng trung bình không tính Nội bộ (`Giá phòng TB (w/o HU) / AvgRate` - Cột 12)**:
   $$\text{AvgRate} = \frac{\text{Doanh Thu (Revenue)}}{\text{P.Ở (OccRooms)} - \text{Nội bộ (HouseUse)}}$$
9. **Giá phòng trung bình thực thu (`Giá phòng TB (w/o HU,FOC) / AvgRate2` - Cột 13)**:
   $$\text{AvgRate2} = \frac{\text{Doanh Thu (Revenue)}}{\text{P.Bán (RoomSales)}}$$
10. **Phòng có thể bán (`Phòng có thể bán / RoomAvible` - Cột 14)**:
    - Tổng số phòng vật lý của khách sạn trừ đi các phòng bị khóa hỏng hóc/sửa chữa (`OOO - Out of Order`):
    $$\text{RoomAvible} = \text{Tổng phòng vật lý} - \text{Phòng OOO}$$
11. **Công suất tổng thể (`Công suất / PercentOccupancy` - Cột 15)**:
    $$\text{PercentOccupancy} = \frac{\text{P.Ở (OccRooms)}}{\text{Phòng có thể bán (RoomAvible)}} \times 100\%$$
12. **Công suất thực thu (`Công suất (w/o HU,FOC) / PercentOccupancy1` - Cột 16)**:
    $$\text{PercentOccupancy1} = \frac{\text{P.Bán (RoomSales)}}{\text{Phòng có thể bán (RoomAvible)}} \times 100\%$$
13. **Doanh thu trên mỗi phòng khả dụng (`DThu/Tổng phòng / RevPAR` - Cột 17)**:
    $$\text{RevPAR} = \frac{\text{Doanh Thu (Revenue)}}{\text{Phòng có thể bán (RoomAvible)}}$$

---

## 3. THIẾT KẾ GIAO DIỆN & BỘ LỌC (UI SPECIFICATION)

### 3.1. Bảng Tham Số Bộ Lọc (`parameter_ui_schema`)

| Tên tham số UI | Mã tham số | Kiểu điều khiển | Mặc định | Tùy chọn / Nguồn dữ liệu |
|---|---|---|---|---|
| **Chọn ngày** | `p_from_date`, `p_to_date` | DateRangePicker | Hôm nay (`$today`) | Dải ngày dự báo (thường chọn từ ngày hiện tại tới 7-30 ngày tới) |
| **Chọn người dùng** | `p_user` | Select / Dropdown | Tất cả (`''`) | `options_source: 'users'` |
| **Chọn khu vực / Chi nhánh** | `p_branch` | Select / Dropdown | Tất cả (`''`) | `options_source: 'branches'` |
| **Loại phòng (Type Room)** | `p_room_type` | Select / Dropdown | Tất cả (`''`) | `options_source: 'room-classes'` |
| **Chi tiết doanh thu (Revenue Detail)**| `p_revenue_detail` | Toggle Switch | `false` | Bật/tắt hiển thị chi tiết tiền phòng và phụ thu |
| **DT bao gồm ăn sáng** | `p_include_breakfast` | Toggle Switch | `true` (BẬT) | Bật: DT tiền phòng gồm ăn sáng; Tắt: trừ tiền ăn sáng |

### 3.2. Bố Cục Trang In & Header Band (Chuẩn Canonical 30% / 70%)
- **Khổ giấy:** A4 Landscape (khổ ngang), Lề: Trên 10mm, Dưới 8mm, Trái 10mm, Phải 10mm.
- **Khối đầu trang:**
  - Trái (30%): Logo khách sạn.
  - Phải (70%): Tên cơ sở, Địa chỉ (`Địa chỉ: ...`), Nhân viên in (`Nhân viên: Admin`), Ngày in (`Ngày: dd/mm/yyyy`).
- **Tiêu đề báo cáo:** **BÁO CÁO DỰ ĐOÁN BÁN PHÒNG** (Font size 18px, chữ hoa đậm, căn giữa).
- **Dòng kỳ báo cáo:** `Ngày: dd/mm/yyyy ~ dd/mm/yyyy` (In nghiêng, căn giữa).

### 3.3. Ma Trận Cột Dữ Liệu Bảng Chi Tiết (17 Cột có đánh số thứ tự)

| STT Cột | Tiêu đề Header | Field Binding | Căn lề | Độ rộng | Format hiển thị / Ghi chú |
|:---:|---|---|:---:|:---:|---|
| **1** | **Ngày** | `Date` | Giữa | 95px | Màu xanh lá `#16a34a`, in đậm, `dd/mm/yyyy` |
| **2** | **P.Đi** | `DepRooms` | Phải | 55px | Số nguyên, phòng trả trong ngày |
| **3** | **K.Đi** | `DepAdult` | Phải | 55px | Số nguyên, khách trả trong ngày |
| **4** | **P.Đến** | `ArrRooms` | Phải | 55px | Số nguyên, phòng nhận trong ngày |
| **5** | **K.Đến** | `ArrAdult` | Phải | 55px | Số nguyên, khách nhận trong ngày |
| **6** | **P.Ở** | `OccRooms` | Phải | 55px | Số nguyên, phòng lưu trú trong ngày |
| **7** | **K.Ở** | `OccAdult` | Phải | 55px | Số nguyên, khách lưu trú trong ngày |
| **8** | **Nội bộ** | `HouseUse` | Phải | 55px | Số nguyên, phòng House Use |
| **9** | **Phòng MP** | `FOCAll` | Phải | 65px | Số nguyên, phòng miễn phí FOC |
| **10** | **P.Bán** | `RoomSales` | Phải | 55px | Số nguyên, phòng bán = P.Ở - Nội bộ - MP |
| **11** | **Doanh Thu** | `Revenue` | Phải | 110px | Định dạng tiền tệ `#,##0` |
| **12** | **Giá phòng TB (w/o HU)** | `AvgRate` | Phải | 95px | Định dạng tiền tệ `#,##0` |
| **13** | **Giá phòng TB (w/o HU,FOC)** | `AvgRate2` | Phải | 100px | Định dạng tiền tệ `#,##0` |
| **14** | **Phòng có thể bán** | `RoomAvible` | Phải | 75px | Số nguyên, tổng phòng khả dụng |
| **15** | **Công suất** | `PercentOccupancy` | Phải | 75px | Tỷ lệ phần trăm `0.00%` |
| **16** | **Công suất(w/o HU,FOC)** | `PercentOccupancy1` | Phải | 85px | Tỷ lệ phần trăm `0.00%` |
| **17** | **DThu/Tổng phòng** | `RevPAR` | Phải | 95px | Định dạng tiền tệ `#,##0` |

### 3.4. Hàng Tổng Cộng Cuối Bảng (Grand Total Row)
- Cột 1 (`Ngày`): `Tổng: {{aggregate.rows.count}}`
- Các cột 2-10: Tổng cộng dồn số lượng phòng và khách: `{{aggregate.rows.sum.DepRooms}}`, `{{aggregate.rows.sum.ArrRooms}}`,...
- Cột 11: Tổng doanh thu tiền phòng: `{{aggregate.rows.sum.Revenue|number}}`
- Cột 12: Giá bình quân gia quyền w/o HU toàn kỳ: `{{aggregate.rows.sum.Revenue / (aggregate.rows.sum.OccRooms - aggregate.rows.sum.HouseUse)|number}}`
- Cột 13: Giá bình quân gia quyền w/o HU, FOC toàn kỳ: `{{aggregate.rows.sum.Revenue / aggregate.rows.sum.RoomSales|number}}`
- Cột 14: Bình quân số phòng có thể bán mỗi ngày: `{{aggregate.rows.avg.RoomAvible|number}}`
- Cột 15: Công suất bình quân gia quyền toàn kỳ: `{{(aggregate.rows.sum.OccRooms / aggregate.rows.sum.RoomAvible) * 100|number:2}}%`
- Cột 16: Công suất bình quân thực thu toàn kỳ: `{{(aggregate.rows.sum.RoomSales / aggregate.rows.sum.RoomAvible) * 100|number:2}}%`
- Cột 17: RevPAR bình quân toàn kỳ: `{{aggregate.rows.sum.Revenue / aggregate.rows.sum.RoomAvible|number}}`

### 3.5. Khối Chữ Ký Chân Trang
Gồm 2 vị trí chữ ký căn đều 2 bên:
- Trái: **Lễ Tân Trưởng**
- Phải: **Bộ phận kinh doanh**

---

## 4. STORED PROCEDURE MYSQL 8.0 (`rpt_room_forecast`)

```sql
DELIMITER $$

DROP PROCEDURE IF EXISTS `rpt_room_forecast`$$

CREATE PROCEDURE `rpt_room_forecast`(
    IN `p_from_date` VARCHAR(10),
    IN `p_to_date` VARCHAR(10),
    IN `p_user` VARCHAR(50),
    IN `p_branch` VARCHAR(20),
    IN `p_room_type` VARCHAR(20),
    IN `p_revenue_detail` INT,
    IN `p_include_breakfast` INT
)
READS SQL DATA
BEGIN
    DECLARE v_from DATE;
    DECLARE v_to DATE;
    DECLARE v_total_rooms INT DEFAULT 0;

    SET v_from = STR_TO_DATE(p_from_date, '%Y-%m-%d');
    SET v_to = STR_TO_DATE(p_to_date, '%Y-%m-%d');
    IF p_include_breakfast IS NULL THEN SET p_include_breakfast = 1; END IF;

    -- 1. Lấy tổng số phòng vật lý của khách sạn (loại trừ phòng ảo/nội bộ)
    SELECT COUNT(*) INTO v_total_rooms
    FROM rooms r
    WHERE (r.is_internal = 0 OR r.is_internal IS NULL)
      AND (r.room_number NOT LIKE '0%')
      AND (p_room_type IS NULL OR p_room_type = '' OR r.room_class_id = p_room_type);

    -- 2. Sinh lịch ngày và tính toán các chỉ số thống kê
    WITH RECURSIVE calendar AS (
        SELECT v_from AS dt
        UNION ALL
        SELECT dt + INTERVAL 1 DAY FROM calendar WHERE dt + INTERVAL 1 DAY <= v_to
    ),
    daily_stats AS (
        SELECT 
            c.dt AS `Date`,
            
            -- Phòng đi & Khách đi
            COUNT(DISTINCT CASE WHEN br.departure_date = c.dt AND br.status IN ('CHECKED_OUT', 'INHOUSE', 'CONFIRMED') THEN br.id END) AS DepRooms,
            COALESCE(SUM(CASE WHEN br.departure_date = c.dt AND br.status IN ('CHECKED_OUT', 'INHOUSE', 'CONFIRMED') THEN COALESCE(br.adults_qty, 1) ELSE 0 END), 0) AS DepAdult,
            
            -- Phòng đến & Khách đến
            COUNT(DISTINCT CASE WHEN br.arrival_date = c.dt AND br.status IN ('CONFIRMED', 'INHOUSE', 'CHECKED_OUT') THEN br.id END) AS ArrRooms,
            COALESCE(SUM(CASE WHEN br.arrival_date = c.dt AND br.status IN ('CONFIRMED', 'INHOUSE', 'CHECKED_OUT') THEN COALESCE(br.adults_qty, 1) ELSE 0 END), 0) AS ArrAdult,
            
            -- Phòng ở & Khách ở qua đêm
            COUNT(DISTINCT CASE WHEN c.dt >= br.arrival_date AND c.dt < br.departure_date AND br.status IN ('INHOUSE', 'CONFIRMED', 'CHECKED_OUT') THEN br.id END) AS OccRooms,
            COALESCE(SUM(CASE WHEN c.dt >= br.arrival_date AND c.dt < br.departure_date AND br.status IN ('INHOUSE', 'CONFIRMED', 'CHECKED_OUT') THEN COALESCE(br.adults_qty, 1) ELSE 0 END), 0) AS OccAdult,
            
            -- Phòng nội bộ House Use
            COUNT(DISTINCT CASE WHEN c.dt >= br.arrival_date AND c.dt < br.departure_date AND br.status IN ('INHOUSE', 'CONFIRMED', 'CHECKED_OUT') 
                                 AND (br.house_use = 1 OR br.rate_code = 'HU') THEN br.id END) AS HouseUse,
            
            -- Phòng miễn phí FOC
            COUNT(DISTINCT CASE WHEN c.dt >= br.arrival_date AND c.dt < br.departure_date AND br.status IN ('INHOUSE', 'CONFIRMED', 'CHECKED_OUT') 
                                 AND (br.rate = 0 OR br.rate_code = 'FOC' OR br.rate_code IS NULL) AND COALESCE(br.house_use, 0) = 0 THEN br.id END) AS FOCAll,
            
            -- Doanh thu tiền phòng RM & ER
            COALESCE(SUM(
                CASE WHEN c.dt >= br.arrival_date AND c.dt < br.departure_date AND br.status IN ('INHOUSE', 'CONFIRMED', 'CHECKED_OUT')
                THEN 
                    COALESCE(br.rate, 0) + 
                    CASE WHEN p_include_breakfast = 1 THEN COALESCE(br.breakfast_rate, 0) ELSE 0 END
                ELSE 0 END
            ), 0) AS Revenue,
            
            -- Phòng khóa OOO trong ngày
            (SELECT COUNT(*) FROM room_locks rl 
             WHERE rl.lock_type = 'OOO' 
               AND c.dt >= DATE(rl.start_time) AND c.dt <= DATE(rl.end_time)) AS OOO
        FROM calendar c
        LEFT JOIN booking_rooms br ON br.status NOT IN ('CANCELLED', 'NOSHOW')
        LEFT JOIN rooms r ON br.room_id = r.id
        WHERE (p_room_type IS NULL OR p_room_type = '' OR r.room_class_id = p_room_type)
        GROUP BY c.dt
    )
    SELECT 
        DATE_FORMAT(s.`Date`, '%d/%m/%Y') AS `Date`,
        s.DepRooms,
        s.DepAdult,
        s.ArrRooms,
        s.ArrAdult,
        s.OccRooms,
        s.OccAdult,
        s.HouseUse,
        s.FOCAll,
        GREATEST(s.OccRooms - s.HouseUse - s.FOCAll, 0) AS RoomSales,
        s.Revenue,
        ROUND(s.Revenue / NULLIF(s.OccRooms - s.HouseUse, 0), 0) AS AvgRate,
        ROUND(s.Revenue / NULLIF(GREATEST(s.OccRooms - s.HouseUse - s.FOCAll, 0), 0), 0) AS AvgRate2,
        GREATEST(v_total_rooms - s.OOO, 1) AS RoomAvible,
        ROUND((s.OccRooms / GREATEST(v_total_rooms - s.OOO, 1)) * 100, 2) AS PercentOccupancy,
        ROUND((GREATEST(s.OccRooms - s.HouseUse - s.FOCAll, 0) / GREATEST(v_total_rooms - s.OOO, 1)) * 100, 2) AS PercentOccupancy1,
        ROUND(s.Revenue / GREATEST(v_total_rooms - s.OOO, 1), 0) AS RevPAR
    FROM daily_stats s
    ORDER BY s.`Date` ASC;
END$$

DELIMITER ;
```

---

## 5. CẤU HÌNH FORM DESIGNER REFERENCE (`room_forecast_reference.php`)

```php
<?php

return [
    'name' => 'Báo cáo dự đoán bán phòng',
    'code' => 'ROOM_FORECAST_REFERENCE',
    'page_size' => 'A4',
    'page_orientation' => 'landscape',
    'margin_top' => 10,
    'margin_bottom' => 8,
    'margin_left' => 10,
    'margin_right' => 10,
    'content_json' => [
        'blocks' => [
            [
                'id' => 'block_header_band',
                'type' => 'columns',
                'columns' => [
                    [
                        'width' => '30%',
                        'blocks' => [
                            ['type' => 'image', 'source' => 'hotel_logo', 'style' => ['maxHeight' => '60px']]
                        ]
                    ],
                    [
                        'width' => '70%',
                        'blocks' => [
                            ['type' => 'text', 'content' => '<strong>{{hotel.name}}</strong>', 'style' => ['fontSize' => '13px']],
                            ['type' => 'text', 'content' => 'Địa chỉ: {{hotel.address}}', 'style' => ['fontSize' => '10px']],
                            ['type' => 'text', 'content' => 'Nhân viên: {{user.name}} &nbsp;&nbsp;&nbsp;&nbsp; Ngày: {{report.print_date}}', 'style' => ['fontSize' => '10px', 'textAlign' => 'right']]
                        ]
                    ]
                ]
            ],
            [
                'id' => 'block_title',
                'type' => 'text',
                'content' => '<h2 style="text-align: center; margin: 15px 0 5px 0; font-size: 18px; font-weight: bold;">BÁO CÁO DỰ ĐOÁN BÁN PHÒNG</h2><p style="text-align: center; font-style: italic; font-size: 11px;">Ngày: {{parameters.p_from_date}} ~ {{parameters.p_to_date}}</p>'
            ],
            [
                'id' => 'block_detail_table',
                'type' => 'table',
                'tableType' => 'detail',
                'style' => [
                    'width' => '100%',
                    'fontSize' => '10px',
                    'borderCollapse' => 'collapse',
                    'border' => '1px solid #aeb5c0'
                ],
                'headerStyle' => [
                    'backgroundColor' => '#dee2ed',
                    'fontWeight' => 'bold',
                    'textAlign' => 'center',
                    'padding' => '4px 2px',
                    'border' => '1px solid #aeb5c0'
                ],
                'cellStyle' => [
                    'padding' => '4px 2px',
                    'border' => '1px solid #cbd5e1'
                ],
                'columns' => [
                    ['title' => "Ngày\n(1)", 'value' => 'Date', 'align' => 'center', 'width' => '95px', 'cellStyle' => ['color' => '#16a34a', 'fontWeight' => 'bold']],
                    ['title' => "P.Đi\n(2)", 'value' => 'DepRooms', 'align' => 'right', 'width' => '55px'],
                    ['title' => "K.Đi\n(3)", 'value' => 'DepAdult', 'align' => 'right', 'width' => '55px'],
                    ['title' => "P.Đến\n(4)", 'value' => 'ArrRooms', 'align' => 'right', 'width' => '55px'],
                    ['title' => "K.Đến\n(5)", 'value' => 'ArrAdult', 'align' => 'right', 'width' => '55px'],
                    ['title' => "P.Ở\n(6)", 'value' => 'OccRooms', 'align' => 'right', 'width' => '55px'],
                    ['title' => "K.Ở\n(7)", 'value' => 'OccAdult', 'align' => 'right', 'width' => '55px'],
                    ['title' => "Nội bộ\n(8)", 'value' => 'HouseUse', 'align' => 'right', 'width' => '55px'],
                    ['title' => "Phòng MP\n(9)", 'value' => 'FOCAll', 'align' => 'right', 'width' => '65px'],
                    ['title' => "P.Bán\n(10)", 'value' => 'RoomSales', 'align' => 'right', 'width' => '55px'],
                    ['title' => "Doanh Thu\n(11)", 'value' => 'Revenue', 'align' => 'right', 'width' => '110px', 'format' => 'number'],
                    ['title' => "Giá phòng TB (w/o HU)\n(12)", 'value' => 'AvgRate', 'align' => 'right', 'width' => '95px', 'format' => 'number'],
                    ['title' => "Giá phòng TB (w/o HU,FOC)\n(13)", 'value' => 'AvgRate2', 'align' => 'right', 'width' => '100px', 'format' => 'number'],
                    ['title' => "Phòng có thể bán\n(14)", 'value' => 'RoomAvible', 'align' => 'right', 'width' => '75px'],
                    ['title' => "Công suất\n(15)", 'value' => 'PercentOccupancy', 'align' => 'right', 'width' => '75px', 'format' => 'percent'],
                    ['title' => "Công suất(w/o HU,FOC)\n(16)", 'value' => 'PercentOccupancy1', 'align' => 'right', 'width' => '85px', 'format' => 'percent'],
                    ['title' => "DThu/Tổng phòng\n(17)", 'value' => 'RevPAR', 'align' => 'right', 'width' => '95px', 'format' => 'number']
                ],
                'customRows' => [
                    [
                        'id' => 'grand_total',
                        'scope' => 'table',
                        'style' => ['backgroundColor' => '#dee2ed', 'fontWeight' => 'bold'],
                        'cells' => [
                            ['content' => 'Tổng: {{aggregate.rows.count}}', 'align' => 'center'],
                            ['content' => '{{aggregate.rows.sum.DepRooms}}', 'align' => 'right'],
                            ['content' => '{{aggregate.rows.sum.DepAdult}}', 'align' => 'right'],
                            ['content' => '{{aggregate.rows.sum.ArrRooms}}', 'align' => 'right'],
                            ['content' => '{{aggregate.rows.sum.ArrAdult}}', 'align' => 'right'],
                            ['content' => '{{aggregate.rows.sum.OccRooms}}', 'align' => 'right'],
                            ['content' => '{{aggregate.rows.sum.OccAdult}}', 'align' => 'right'],
                            ['content' => '{{aggregate.rows.sum.HouseUse}}', 'align' => 'right'],
                            ['content' => '{{aggregate.rows.sum.FOCAll}}', 'align' => 'right'],
                            ['content' => '{{aggregate.rows.sum.RoomSales}}', 'align' => 'right'],
                            ['content' => '{{aggregate.rows.sum.Revenue|number}}', 'align' => 'right'],
                            ['content' => '{{aggregate.rows.sum.Revenue / (aggregate.rows.sum.OccRooms - aggregate.rows.sum.HouseUse)|number}}', 'align' => 'right'],
                            ['content' => '{{aggregate.rows.sum.Revenue / aggregate.rows.sum.RoomSales|number}}', 'align' => 'right'],
                            ['content' => '{{aggregate.rows.avg.RoomAvible|number}}', 'align' => 'right'],
                            ['content' => '{{(aggregate.rows.sum.OccRooms / aggregate.rows.sum.RoomAvible) * 100|number:2}}%', 'align' => 'right'],
                            ['content' => '{{(aggregate.rows.sum.RoomSales / aggregate.rows.sum.RoomAvible) * 100|number:2}}%', 'align' => 'right'],
                            ['content' => '{{aggregate.rows.sum.Revenue / aggregate.rows.sum.RoomAvible|number}}', 'align' => 'right']
                        ]
                    ]
                ]
            ],
            [
                'id' => 'block_signatures',
                'type' => 'columns',
                'style' => ['marginTop' => '30px'],
                'columns' => [
                    [
                        'width' => '50%',
                        'blocks' => [
                            ['type' => 'text', 'content' => '<p style="text-align: center; font-weight: bold;">Lễ Tân Trưởng</p>', 'style' => ['fontSize' => '11px']]
                        ]
                    ],
                    [
                        'width' => '50%',
                        'blocks' => [
                            ['type' => 'text', 'content' => '<p style="text-align: center; font-weight: bold;">Bộ phận kinh doanh</p>', 'style' => ['fontSize' => '11px']]
                        ]
                    ]
                ]
            ]
        ]
    ]
];
```

---

## 6. QUY TRÌNH KIỂM THỬ & ĐỐI SOÁT CHÉO DỮ LIỆU

1. **Khớp nối bất biến giữa Dòng 169 và Dòng 167 (Công suất công ty)**:
   - Khi chạy cùng 1 khoảng ngày (VD: `13/07/2026 ~ 13/07/2026`):
     - `OccRooms` (Dòng 169 Cột 6) **PHẢI BẰNG** `Đêm phòng` (Dòng 167 Cột 2).
     - `Revenue` (Dòng 169 Cột 11) **PHẢI BẰNG** `DT Phòng` (Dòng 167 Cột 6).
     - `AvgRate` (Dòng 169 Cột 12) **PHẢI BẰNG** `ADR` (Dòng 167 Cột 3).
     - `AvgRate2` (Dòng 169 Cột 13) **PHẢI BẰNG** `ADR niêm yết` (Dòng 167 Cột 4).
     - `PercentOccupancy` (Dòng 169 Cột 15) **PHẢI BẰNG** `%OCC` (Dòng 167 Cột 5).
2. **Kiểm tra Migration 5 Database**:
   - `php artisan migrate:all --force`
   - Đảm bảo procedure `rpt_room_forecast` được tạo thành công trên `mysql`, `mysql_hkt1`, `mysql_hkt2`, `mysql_hkt3`, `mysql_hkt4`.

## 7. TRẠNG THÁI TRIỂN KHAI DỰ ÁN

- Đã tạo procedure `rpt_room_forecast`, data source, report definition và template Designer reference tại migration `2026_09_24_100000_create_room_forecast_report.php`.
- Contract runtime gồm 17 cột FO; chế độ HK ẩn đúng 4 cột tài chính bằng `p_revenue_detail`, nhưng procedure vẫn trả đủ dữ liệu.
- `p_branch` là tham số ẩn mặc định `__current__`; dữ liệu được cô lập theo connection chi nhánh hiện tại, không join `branch_id`.
- Đã kiểm tra PHPUnit 4/4 và PHP lint. Chưa chạy migration thật, chưa nghiệm thu browser/PDF với dữ liệu chi nhánh thật.

## 7. TRẠNG THÁI TRIỂN KHAI DỰ ÁN

- Đã tạo procedure `rpt_room_forecast`, data source, report definition và template Designer reference tại migration `2026_09_24_100000_create_room_forecast_report.php`.
- Contract runtime gồm 17 cột FO; chế độ HK ẩn đúng 4 cột tài chính bằng `p_revenue_detail`, nhưng procedure vẫn trả đủ dữ liệu.
- `p_branch` là tham số ẩn mặc định `__current__`; dữ liệu được cô lập theo connection chi nhánh hiện tại, không join `branch_id`.
- Đã kiểm tra PHPUnit 4/4 và PHP lint. Chưa chạy migration thật, chưa nghiệm thu browser/PDF với dữ liệu chi nhánh thật.
