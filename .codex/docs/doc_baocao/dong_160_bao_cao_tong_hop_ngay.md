# Đặc Tả Kỹ Thuật: Báo Cáo Tổng Hợp Ngày - Dòng 160

> **Tài liệu bàn giao cho Agent triển khai độc lập**  
> Căn cứ bóc tách từ file Excel `DANH MỤC BÁO CÁO.xlsx` (Dòng 160, Sheet 71 `BC tổng hợp ngày`), ảnh giao diện thực tế `[.codex/docs/doc_baocao/images/dong_160_ui_mau.png](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/images/dong_160_ui_mau.png)` và Stored Procedure gốc MS SQL Server `sp_279` của Navy ([ProVistaNavyHotel_sp_279.sql](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/sql/ProVistaNavyHotel_sp_279.sql)).  
> *Lưu ý quan trọng theo chỉ đạo: Chưa có store Galliot nên bỏ qua Galliot, làm 100% chuẩn theo Navy.*

---

## Contract runtime đã chốt

- Chỉ có tham số `p_date` kiểu ngày.
- Output gồm 9 field: `GroupIndex`, `SortOrder`, `Content`, `DateAmount`, `MonthAmount`, `PlanAmount`, `Rate`, `IsBold`, `CustomText`.
- Template runtime dùng A4 dọc, lề `10/10/10/10mm`; `PlanAmount` và `Rate` để trống theo nghiệp vụ Navy.
- Runtime trả đủ các dòng doanh thu Navy `1-1` đến `1-11`, sắp xếp theo số thứ tự tự nhiên (`1-2` trước `1-10`); các dòng `1-4`, `1-6`, `1-8` mặc định 0 nếu chưa có mapping cấu hình.
- Dòng `6-1` hiển thị `CustomText = Đã hoàn tất` theo ảnh UI; bản SQL legacy có thêm dòng trạng thái trùng `6-2`, nhưng không render trùng trong mẫu mới.
- Source of truth giao diện là `content_json`; `content_html` được biên dịch từ JSON và `css` chỉ giữ phần trình bày. Không thêm giá trị dữ liệu cố định vào HTML.

## 1. Thông Tin Định Danh & Phân Loại

* **Tên báo cáo tiếng Việt**: Báo cáo tổng hợp ngày
* **Mã báo cáo (`code`)**: `DAILY_SUMMARY`
* **Mã nguồn dữ liệu (`data_source_code`)**: `RPT_DAILY_SUMMARY`
* **Mã template (`template_code`)**: `DAILY_SUMMARY_REFERENCE`
* **Nhóm báo cáo (`group`)**: `Báo cáo quản lý` (hoặc `Báo cáo doanh thu`)
* **Menu hiển thị**: `['frontdesk', 'manager', 'report']`
* **Vị trí trong Excel**: Dòng 160 (STT 15, Nhóm IV - Báo cáo liên quan tiền, doanh thu)
* **Sheet tham chiếu**: Sheet 71 (`BC tổng hợp ngày`)
* **Lưu ý đặc thù**:
  * Đọc theo Store của Navy: `ProVistaNavyHotel.dbo.sp_279`.
  * Danh mục nhóm doanh thu đọc từ bảng cấu hình `SP1610` (`Report = 'NightAuditReport'`) hoặc cấu hình nhóm dịch vụ tương đương.
* **Tài nguyên đính kèm**:
  * Ảnh UI chụp màn hình hệ thống cũ: [dong_160_ui_mau.png](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/images/dong_160_ui_mau.png)
  * File SQL Stored Procedure legacy: [ProVistaNavyHotel_sp_279.sql](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/sql/ProVistaNavyHotel_sp_279.sql)

---

## 2. Phân Tích Nghiệp Vụ & Thuật Toán Tính Toán (sp_279)

### 2.1. Chu kỳ thời gian tính toán
* `@date`: Ngày xem báo cáo (ví dụ: `2026-07-15`).
* `@beginDate`: Ngày đầu tiên của tháng chứa `@date` (`DATEFROMPARTS(YEAR(@date), MONTH(@date), 1)`).
* Doanh thu được phân tách thành 2 cột độc lập:
  * **Cột NGÀY (`DateAmount`)**: Tổng phát sinh trong đúng ngày `@date`.
  * **Cột LŨY KẾ THÁNG (`MonthAmount`)**: Tổng phát sinh từ ngày đầu tháng `@beginDate` đến ngày `@date`.

### 2.2. Chi tiết 7 nhóm chỉ tiêu trong báo cáo

1. **Nhóm 1: Doanh Thu & FOC**:
   * `1-1: Tổng doanh thu`: Tổng toàn bộ tiền dịch vụ `Amount` phát sinh (`Edit = 0`).
   * `1-2: Doanh thu phòng`: Dịch vụ tiền phòng `RM` và các dịch vụ thuộc `RoomRevenue` cấu hình.
   * `1-3: Doanh thu nhà hàng`: Dịch vụ F&B (`FbRevenue`).
   * `1-4: Doanh thu hội nghị`: Dịch vụ phòng họp / hội nghị (`ConferenceRevenue`).
   * `1-5: Doanh thu Minibar`: Dịch vụ Minibar (`MinibarRevenue`).
   * `1-6: Doanh thu VPTH`: Doanh thu văn phòng tổng hợp (Mặc định 0 nếu không phát sinh).
   * `1-7: Doanh thu giặt ủi`: Dịch vụ giặt ủi (`LaundryRevenue`).
   * `1-8: Doanh thu shop`: Dịch vụ quầy lưu niệm / shop (Mặc định 0 nếu không phát sinh).
   * `1-9: Doanh thu vận chuyển`: Dịch vụ xe đưa đón (`TransportationRevenue`).
   * `1-10: Doanh thu dịch vụ khác`: Các dịch vụ còn lại không nằm trong các nhóm trên.
   * `1-11: FOC`: Số lượng lượt phòng miễn phí (`RoomRateCode = 'FOC'` hoặc giá phòng = 0, loại trừ `HU`).
2. **Nhóm 2: Hoạt Động Khách Sạn**:
   * `2-2: Số phòng In house`: Tổng lượt khách lưu trú trong ngày (`InhouseRoom` từ bảng audit `sp_275`).
   * `2-3: Check in`: Số lượng phòng làm thủ tục nhận phòng trong ngày (`SP2100.ActualArrivalDate = @date`).
   * `2-4: Check out`: Số lượng phòng trả phòng trong ngày (`SP2100.CheckoutDate = @date`).
   * `2-5: Số phòng ở cuối ngày`: Số phòng đang có khách lưu trú tính đến thời điểm chốt ngày.
3. **Nhóm 3: Công Suất Phòng (OCC / %)**:
   * Công thức: `OCC = (Số phòng ở cuối ngày / Tổng số phòng khả dụng) * 100%`.
   * Tổng số phòng khả dụng: `TotalRooms - LockedRooms (OOO/OOS)`.
4. **Nhóm 4: Giá Phòng Bình Quân (ADR)**:
   * Công thức: `ADR = Doanh thu tiền phòng trong ngày / Số phòng ở cuối ngày`.
5. **Nhóm 5: Ý Kiến Khách Hàng**:
   * Khung đánh giá phản hồi ý kiến khách trong ngày (Mặc định để trống ghi chú).
6. **Nhóm 6: Tình Trạng Cơ Sở Vật Chất**:
   * Mặc định ghi nhận trạng thái vận hành: `Đã hoàn tất` (hoặc tình trạng ghi nhận từ bộ phận bảo trì).
7. **Nhóm 7: Đề Xuất**:
   * Khung kiến nghị của bộ phận quản lý/thu ngân.

---

## 3. Phân Tích Bố Cục Giao Diện & Form Designer (UI Design)

### 3.1. Left Filter Panel
* `p_date`: Chọn ngày xem báo cáo (Single Date Picker, mặc định `$today`).
* Nút bấm: `Hiển thị báo cáo` (`#3b82f6`).

### 3.2. Bố Cục Trang Báo Cáo (A4 Portrait - Khổ Dọc)
* **Khổ giấy**: A4 Dọc (`portrait`), lề: `top: 10mm, bottom: 10mm, left: 10mm, right: 10mm`.
* **Header**:
  * Tên khách sạn bên trái: `{{hotel.name}}` (In hoa đậm).
  * Tiêu đề chính giữa: **BÁO CÁO TỔNG HỢP NGÀY** (Font size 16pt, in đậm).
  * Dòng ngày: `{{parameters.p_date|date}}` (`dd/MM/yyyy`).
* **Bảng Ma Trận 6 Cột**:
  | STT | Cột | Tiêu Đề | Căn Lề | Độ Rộng | Ghi Chú |
  |---|---|---|---|---|---|
  | 1 | `STT` | **STT** | Giữa | 45px | Merge ô dọc theo nhóm chỉ tiêu (1, 2, 3, 4, 5, 6, 7) |
  | 2 | `Content` | **NỘI DUNG** | Trái | 200px | Tên chỉ tiêu báo cáo |
  | 3 | `DateAmount` | **NGÀY<br><span style="font-weight: normal;">{{parameters.p_date\|date}}</span>** | Phải | 100px | Số liệu phát sinh trong ngày |
  | 4 | `MonthAmount` | **LŨY KẾ THÁNG {{parameters.p_month}}** | Phải | 115px | Lũy kế từ ngày 1 đến ngày xem |
  | 5 | `PlanAmount` | **DỰ KIẾN THÁNG {{parameters.p_next_month}}** | Phải | 115px | Mục tiêu / Dự kiến (để trống hoặc nạp nếu có) |
  | 6 | `Rate` | **Tỉ lệ hoàn thành** | Giữa | 95px | Tỷ lệ hoàn thành so với kế hoạch (%) |

---

## 4. Stored Procedure Chuẩn Hóa MySQL 8.0 (`rpt_daily_summary`)

> SQL mẫu phía dưới là bản mô tả legacy. Contract chạy thật nằm trong migration `2026_09_22_170000...`; cấu hình `Revenue` và `AverageRoomRateIncludedOthersRoomRevenue` được đọc từ `hotel_configs`.

```sql
DELIMITER $$

DROP PROCEDURE IF EXISTS `rpt_daily_summary`$$

CREATE PROCEDURE `rpt_daily_summary`(
    IN p_date VARCHAR(10)
)
BEGIN
    DECLARE v_date DATE;
    DECLARE v_begin_date DATE;
    DECLARE v_total_rooms INT DEFAULT 0;
    DECLARE v_locked_rooms INT DEFAULT 0;
    DECLARE v_available_rooms INT DEFAULT 0;
    DECLARE v_checkin INT DEFAULT 0;
    DECLARE v_checkout INT DEFAULT 0;
    DECLARE v_inhouse INT DEFAULT 0;
    DECLARE v_room_rev_day DECIMAL(15,2) DEFAULT 0;
    DECLARE v_occ DECIMAL(5,2) DEFAULT 0;
    DECLARE v_adr DECIMAL(15,2) DEFAULT 0;
    DECLARE v_foc_day INT DEFAULT 0;
    DECLARE v_foc_month INT DEFAULT 0;

    SET v_date = IF(p_date IS NOT NULL AND p_date != '', STR_TO_DATE(p_date, '%Y-%m-%d'), CURDATE());
    SET v_begin_date = DATE_FORMAT(v_date, '%Y-%m-01');

    -- 1. Thống kê số phòng khả dụng
    SELECT COUNT(*) INTO v_total_rooms FROM rooms WHERE status != 'out_of_inventory';
    SELECT COUNT(*) INTO v_locked_rooms FROM room_locks 
    WHERE status = 'active' AND v_date BETWEEN start_date AND end_date;
    SET v_available_rooms = GREATEST(v_total_rooms - v_locked_rooms, 1);

    -- 2. Thống kê hoạt động nhận/trả phòng trong ngày
    SELECT COUNT(*) INTO v_checkin FROM booking_rooms 
    WHERE DATE(arrival_date) = v_date AND status IN (0, 1, 2);

    SELECT COUNT(*) INTO v_checkout FROM booking_rooms 
    WHERE DATE(departure_date) = v_date AND status IN (0, 1, 2);

    SELECT COUNT(*) INTO v_inhouse FROM booking_rooms 
    WHERE v_date BETWEEN DATE(arrival_date) AND DATE(departure_date) AND status = 1;

    -- 3. Thống kê FOC
    SELECT COUNT(*) INTO v_foc_day FROM booking_rooms 
    WHERE v_date BETWEEN DATE(arrival_date) AND DATE(departure_date) 
      AND (rate_code = 'FOC' OR rate = 0);

    SELECT COUNT(*) INTO v_foc_month FROM booking_rooms 
    WHERE arrival_date <= v_date AND departure_date >= v_begin_date
      AND (rate_code = 'FOC' OR rate = 0);

    -- 4. Bảng tạm chứa kết quả
    DROP TEMPORARY TABLE IF EXISTS tmp_daily_summary_result;
    CREATE TEMPORARY TABLE tmp_daily_summary_result (
        sort_order VARCHAR(10),
        group_idx INT,
        content VARCHAR(150),
        date_amount DECIMAL(15,2),
        month_amount DECIMAL(15,2),
        is_bold TINYINT DEFAULT 0,
        custom_text VARCHAR(100) DEFAULT NULL
    );

    -- Nhóm 1: Doanh thu
    -- 1-1: Tổng doanh thu
    INSERT INTO tmp_daily_summary_result 
    SELECT '1-1', 1, 'Tổng doanh thu',
        COALESCE(SUM(CASE WHEN DATE(sb.service_date) = v_date THEN sb.amount ELSE 0 END), 0),
        COALESCE(SUM(sb.amount), 0),
        1, NULL
    FROM service_bills sb
    WHERE sb.status != 3 AND DATE(sb.service_date) BETWEEN v_begin_date AND v_date;

    -- 1-2: Doanh thu phòng
    INSERT INTO tmp_daily_summary_result 
    SELECT '1-2', 1, 'Doanh thu phòng',
        COALESCE(SUM(CASE WHEN DATE(sb.service_date) = v_date THEN sb.amount ELSE 0 END), 0),
        COALESCE(SUM(sb.amount), 0),
        0, NULL
    FROM service_bills sb
    WHERE sb.status != 3 AND sb.service_id = 'RM' AND DATE(sb.service_date) BETWEEN v_begin_date AND v_date;

    -- Lưu doanh thu phòng trong ngày để tính ADR
    SELECT date_amount INTO v_room_rev_day FROM tmp_daily_summary_result WHERE sort_order = '1-2';

    -- 1-3: Doanh thu Minibar
    INSERT INTO tmp_daily_summary_result 
    SELECT '1-3', 1, 'Doanh thu Minibar',
        COALESCE(SUM(CASE WHEN DATE(sb.service_date) = v_date THEN sb.amount ELSE 0 END), 0),
        COALESCE(SUM(sb.amount), 0),
        0, NULL
    FROM service_bills sb
    WHERE sb.status != 3 AND (sb.service_id = 'MB' OR sb.outlet = 'MB') AND DATE(sb.service_date) BETWEEN v_begin_date AND v_date;

    -- 1-4: Doanh thu giặt ủi
    INSERT INTO tmp_daily_summary_result 
    SELECT '1-4', 1, 'Doanh thu giặt ủi',
        COALESCE(SUM(CASE WHEN DATE(sb.service_date) = v_date THEN sb.amount ELSE 0 END), 0),
        COALESCE(SUM(sb.amount), 0),
        0, NULL
    FROM service_bills sb
    WHERE sb.status != 3 AND (sb.service_id = 'LA' OR sb.outlet = 'LA') AND DATE(sb.service_date) BETWEEN v_begin_date AND v_date;

    -- 1-5: Doanh thu dịch vụ khác
    INSERT INTO tmp_daily_summary_result 
    SELECT '1-5', 1, 'Doanh thu dịch vụ khác',
        COALESCE(SUM(CASE WHEN DATE(sb.service_date) = v_date THEN sb.amount ELSE 0 END), 0),
        COALESCE(SUM(sb.amount), 0),
        0, NULL
    FROM service_bills sb
    WHERE sb.status != 3 AND sb.service_id NOT IN ('RM', 'MB', 'LA') AND DATE(sb.service_date) BETWEEN v_begin_date AND v_date;

    -- 1-6: FOC
    INSERT INTO tmp_daily_summary_result VALUES ('1-6', 1, 'FOC', v_foc_day, v_foc_month, 0, NULL);

    -- Nhóm 2: Hoạt động khách sạn
    INSERT INTO tmp_daily_summary_result VALUES 
    ('2-1', 2, 'Hoạt động khách sạn', NULL, NULL, 1, NULL),
    ('2-2', 2, 'Số phòng In house', v_inhouse, NULL, 0, NULL),
    ('2-3', 2, 'Check in', v_checkin, NULL, 0, NULL),
    ('2-4', 2, 'Check out', v_checkout, NULL, 0, NULL),
    ('2-5', 2, 'Số phòng ở cuối ngày', v_inhouse, NULL, 0, NULL);

    -- Nhóm 3: OCC%
    IF v_available_rooms > 0 THEN
        SET v_occ = ROUND((v_inhouse / v_available_rooms) * 100, 2);
    END IF;
    INSERT INTO tmp_daily_summary_result VALUES ('3-1', 3, 'Công suất phòng (OCC)/%', v_occ, NULL, 1, CONCAT(v_occ, '%'));

    -- Nhóm 4: ADR
    IF v_inhouse > 0 THEN
        SET v_adr = ROUND(v_room_rev_day / v_inhouse, 0);
    END IF;
    INSERT INTO tmp_daily_summary_result VALUES ('4-1', 4, 'Giá phòng bình quân (ADR)', v_adr, NULL, 1, NULL);

    -- Nhóm 5, 6, 7: Đánh giá & Ghi chú
    INSERT INTO tmp_daily_summary_result VALUES 
    ('5-1', 5, 'Ý kiến khách hàng', NULL, NULL, 1, ''),
    ('6-1', 6, 'Tình trạng cơ sở vật chất', NULL, NULL, 1, 'Đã hoàn tất'),
    ('7-1', 7, 'Đề xuất', NULL, NULL, 1, '');

    -- Trả dữ liệu ra ngoài
    SELECT 
        group_idx AS GroupIndex,
        sort_order AS SortOrder,
        content AS Content,
        date_amount AS DateAmount,
        month_amount AS MonthAmount,
        is_bold AS IsBold,
        custom_text AS CustomText
    FROM tmp_daily_summary_result
    ORDER BY sort_order ASC;

    DROP TEMPORARY TABLE IF EXISTS tmp_daily_summary_result;
END$$

DELIMITER ;
```

---

## 5. File Template Reference PHP (`daily_summary_reference.php`)

> File đặt tại: `backend/database/report_templates/daily_summary_reference.php`

```php
<?php

use App\Services\TemplateRendererService;

return new class
{
    public function definition(): array
    {
        return [
            'code' => 'DAILY_SUMMARY',
            'name' => 'Báo cáo tổng hợp ngày',
            'report' => 'DAILY_SUMMARY_REFERENCE',
            'page_size' => 'A4',
            'page_orientation' => 'portrait',
            'margin_top' => 10,
            'margin_bottom' => 10,
            'margin_left' => 10,
            'margin_right' => 10,
            'parameter_ui_schema' => [
                'columns' => 1,
                'fields' => [
                    [
                        'name' => 'p_date',
                        'label' => 'Ngày',
                        'type' => 'date',
                        'default' => '$today',
                        'required' => true,
                    ]
                ],
            ],
        ];
    }

    public function blocks(): array
    {
        return [
            [
                'id' => 'header_band',
                'type' => 'text',
                'content' => '<div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #0f172a; padding-bottom: 8px;">'
                    . '<div style="font-size: 11pt; font-weight: bold; color: #1e293b;">{{hotel.name}}</div>'
                    . '<div style="font-size: 8.5pt; color: #64748b;">Ngày in: {{hotel.printed_at}}</div>'
                    . '</div>',
            ],
            [
                'id' => 'title_band',
                'type' => 'text',
                'content' => '<div style="text-align: center; margin: 16px 0 12px 0;">'
                    . '<h2 style="font-size: 16pt; font-weight: bold; margin: 0; color: #0f172a;">BÁO CÁO TỔNG HỢP NGÀY</h2>'
                    . '<p style="font-size: 9.5pt; color: #334155; margin: 4px 0 0 0; font-weight: 500;">{{parameters.p_date|date}}</p>'
                    . '</div>',
            ],
            [
                'id' => 'summary_table',
                'type' => 'table',
                'dataset' => 'rows',
                'style' => [
                    'width' => '100%',
                    'borderCollapse' => 'collapse',
                    'fontSize' => '9.5px',
                ],
                'headerStyle' => [
                    'backgroundColor' => '#dee2ed',
                    'border' => '1px solid #94a3b8',
                    'padding' => '6px 5px',
                    'textAlign' => 'center',
                    'fontWeight' => 'bold',
                    'color' => '#0f172a',
                ],
                'cellStyle' => [
                    'border' => '1px solid #cbd5e1',
                    'padding' => '5px 6px',
                ],
                'columns' => [
                    ['field' => 'GroupIndex', 'title' => 'STT', 'width' => '8%', 'align' => 'center', 'style' => ['fontWeight' => 'bold']],
                    ['field' => 'Content', 'title' => 'NỘI DUNG', 'width' => '36%', 'align' => 'left'],
                    ['field' => 'DateAmount', 'title' => 'NGÀY', 'width' => '15%', 'align' => 'right', 'format' => 'number'],
                    ['field' => 'MonthAmount', 'title' => 'LŨY KẾ THÁNG', 'width' => '15%', 'align' => 'right', 'format' => 'number'],
                    ['field' => 'PlanAmount', 'title' => 'DỰ KIẾN THÁNG', 'width' => '14%', 'align' => 'right', 'format' => 'number'],
                    ['field' => 'CustomText', 'title' => 'Tỉ lệ hoàn thành', 'width' => '12%', 'align' => 'center'],
                ],
            ],
            [
                'id' => 'signatures_band',
                'type' => 'columns',
                'style' => ['marginTop' => '35px'],
                'columns' => [
                    ['width' => '50%', 'blocks' => [['type' => 'text', 'content' => '<div style="text-align: center; font-size: 9pt;"><strong>Người lập biểu</strong><br><em>(Ký, ghi rõ họ tên)</em></div>']]],
                    ['width' => '50%', 'blocks' => [['type' => 'text', 'content' => '<div style="text-align: center; font-size: 9pt;"><strong>Giám đốc khách sạn</strong><br><em>(Ký, ghi rõ họ tên)</em></div>']]],
                ]
            ]
        ];
    }
};
```
## 6. Quyết định triển khai đã chốt

- `PlanAmount` và `Rate` trả `NULL`, template hiển thị ô trống.
- Chỉ nhận `p_date`; nhãn lũy kế tháng được enrich thành `parameters.p_month_label` vì renderer hiện không hỗ trợ filter `date`.
- Logic đêm phòng dùng cùng nguyên tắc `posted_room_nights`/`eligible_room_days` của `rpt_inhouse_guests`.
