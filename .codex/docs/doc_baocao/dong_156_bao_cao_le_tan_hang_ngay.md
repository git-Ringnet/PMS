# Đặc Tả Kỹ Thuật: Báo Cáo Lễ Tân Hằng Ngày (Dòng 156)

> **Tài liệu chuẩn bị cho Agent triển khai tiếp theo**  
> Dựa trên phân tích từ file Excel `DANH MỤC BÁO CÁO.xlsx` (Dòng 156, Sheet 71 `BC lễ tân hằng ngày`), ảnh giao diện thực tế `sheet71_img2_image79.png` và Stored Procedure gốc trên MS SQL Server (`sp_275`).

---

## 1. Thông Tin Chung & Định Danh

* **Tên báo cáo tiếng Việt**: Báo cáo lễ tân hằng ngày
* **Mã báo cáo (`code`)**: `DAILY_FRONTDESK`
* **Mã nguồn dữ liệu (`data_source_code`)**: `RPT_DAILY_FRONTDESK`
* **Mã template (`template_code`)**: `DAILY_FRONTDESK_REFERENCE`
* **Menu hiển thị**: Báo cáo thống kê lễ tân (`frontdesk`, `reservation`)
* **Vị trí trong Excel danh mục**: Dòng 156 (STT 10.0)
* **Sheet tham chiếu trong Excel**: Sheet 71 (`BC lễ tân hằng ngày`)
* **Ảnh chụp màn hình thực tế**: `[.codex/docs/doc_baocao/images/dong_156_ui_mau.png](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/images/dong_156_ui_mau.png)`
* **File SQL legacy tham chiếu**:
  * [sp_275_full.sql](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/sql/sp_275_full.sql): Procedure tính toán thống kê phòng và số lượng suất ăn sáng cho 4 phân khúc khách hàng.

---

## 2. Phân Tích Chi Tiết Giao Diện Từ Ảnh Thực Tế (`dong_156_ui_mau.png`)

### 2.1. Panel Bộ Lọc Bên Trái (Left Filter Panel)
* **Chiều rộng panel**: ~ 260px.
* **Các thành phần điều khiển**:
  1. **Ngày (`p_from_date` ~ `p_to_date`)**:
     - Date Picker / Range Picker.
     - Trong ảnh thực tế legacy: Giá trị mặc định hiển thị là **`Hôm qua`** (Yesterday).
     - Khi xem 1 ngày: `p_from_date` = `p_to_date` = Ngày hôm qua (hoặc người dùng chọn khoảng ngày tùy ý).
  2. **Nút thực thi**:
     - Button `Hiển thị báo cáo`: Màu xanh biển `#3b82f6`, chữ trắng, bo góc 4px.

---

## 3. Cấu Hình Form Designer & Thông Số Thiết Kế Chi Tiết

### 3.1. Thiết Lập Trang In (Page Settings)
* `page_size`: `'A4'`
* `page_orientation`: `'landscape'` (Khổ ngang - giúp 9 cột dữ liệu hiển thị thông thoáng, không bị co ép dòng chữ)
* `margin_top`: `10` (mm)
* `margin_bottom`: `10` (mm)
* `margin_left`: `8` (mm)
* `margin_right`: `8` (mm)

### 3.2. Bảng Màu & Typography (Design Tokens)
* **Font chữ chính**: `Arial, Helvetica, sans-serif`
* **Màu chữ chính**: `#111111`
* **Màu viền bảng**: `#aeb5c0` (1px solid)
* **Màu nền Header bảng**: `#d9deea` (xanh xám nhạt đặc trưng của PMS)
* **Màu nhãn nhóm Ngày**: `#b91c1c` (đỏ đậm)
* **Màu nền dòng Tổng**: `#ffffff` (hoặc `#d9deea` cho dòng tổng kết giai đoạn)
* **Cỡ chữ**:
  * Tiêu đề chính (`H1`): `16px`, `font-weight: bold`, `text-align: center`
  * Tiêu đề phụ (Ngày áp dụng): `10px`, `font-weight: bold`, `text-align: center`
  * Thông tin Header khách sạn: `10px`, `line-height: 1.5`
  * Toàn bộ bảng dữ liệu (Header, Dòng dữ liệu, Subtotal, Total): `9.5px`
* **Padding ô bảng**: `4px 6px` cho header và data cells.

---

## 4. Chi Tiết Layout Bảng Dữ Liệu (9 Cột Đơn Tầng)

### 4.1. Chi Tiết Thuộc Tính Từng Cột

| STT | Tên cột hiển thị | Field Name | Width | Align Header | Align Cell | Định dạng dữ liệu | Ý nghĩa nghiệp vụ |
|---|---|---|---|---|---|---|---|
| 1 | `Khách` | `Segment` | `15%` | Center | Left | Text in đậm | Tên phân khúc thị trường |
| 2 | `Số phòng check in` | `CheckinRoom` | `9%` | Center | Right | Số nguyên (`{{item.CheckinRoom\|number}}`) | Lượt phòng check-in trong ngày |
| 3 | `Số phòng check out` | `CheckoutRoom` | `9%` | Center | Right | Số nguyên (`{{item.CheckoutRoom\|number}}`) | Lượt phòng check-out trong ngày |
| 4 | `Số phòng inhouse` | `InhouseRoom` | `9%` | Center | Right | Số nguyên (`{{item.InhouseRoom\|number}}`) | Phòng khách đang lưu trú qua đêm |
| 5 | `Tổng số phòng ở trong ngày` | `DayUseRoom` | `11%` | Center | Right | Số nguyên (`{{item.DayUseRoom\|number}}`) | Tổng lượt phòng sử dụng trong ngày |
| 6 | `Số lượng khách ăn sáng ngày hôm sau` | `BreakfastGuestNum` | `12%` | Center | Right | Số nguyên (`{{item.BreakfastGuestNum\|number}}`) | Suất ăn sáng dự kiến ngày tiếp theo |
| 7 | `Số lượng khách không ăn sáng ngày hôm sau`| `NoBreakfastGuestNum` | `12%` | Center | Right | Số nguyên (`{{item.NoBreakfastGuestNum\|number}}`)| Khách phòng không ăn sáng tiếp theo |
| 8 | `Ghi chú` | `Notes` | `11%` | Center | Left | Text (trống) | Ô ghi chú tác nghiệp của lễ tân |
| 9 | `Ý kiến phản hồi khách hàng` | `CustomerFeedback` | `12%` | Center | Left | Text (trống) | Ý kiến đóng góp từ khách lưu trú |

### 4.2. Gom Nhóm (Grouping)
Gom nhóm theo **Ngày (`DateFormatted`)**:
* Dòng header nhóm:
  ```html
  <tr class="group-header-row date-group">
    <td style="font-weight: bold; background-color: #ffffff; border-top: 1px solid #94a3b8; border-bottom: 1px solid #aeb5c0; padding: 4px 6px; text-align: left;">
      <span style="color: #b91c1c; font-weight: bold;">Ngày</span>
    </td>
    <td colspan="8" style="font-weight: bold; background-color: #ffffff; border-top: 1px solid #94a3b8; border-bottom: 1px solid #aeb5c0; padding: 4px 6px; text-align: left;">
      <span style="color: #b91c1c; font-weight: bold;">{{group.value}}</span>
    </td>
  </tr>
  ```

### 4.3. 4 Dòng Phân Khúc Khách Cố Định (Fixed Market Segments)
Trong mỗi ngày, bảng hiển thị cố định **4 dòng phân khúc khách** theo thứ tự:
1. **`Khách TA`**: Travel Agency, FOC, Voucher
2. **`Khách OTA`**: Online Travel Agent (Booking.com, Agoda,...)
3. **`Khách Corp`**: Khách doanh nghiệp / Công ty hợp đồng
4. **`Khách Walk-in, FIT, Fanpage`**: Khách lẻ, khách vãng lai, trực tiếp, Fanpage

### 4.4. Dòng Tổng Phụ & Tổng Cộng
* **Dòng `Tổng` (của từng Ngày)**:
  * Nền: `#ffffff`, chữ đậm, border-top 1px, border-bottom 1px solid `#aeb5c0`.
  * Cột 1: Chữ `Tổng` (căn giữa, font bold).
  * Cột 2: `{{aggregate.date.sum.CheckinRoom|number}}`
  * Cột 3: `{{aggregate.date.sum.CheckoutRoom|number}}`
  * Cột 4: `{{aggregate.date.sum.InhouseRoom|number}}`
  * Cột 5: `{{aggregate.date.sum.DayUseRoom|number}}`
  * Cột 6: `{{aggregate.date.sum.BreakfastGuestNum|number}}`
  * Cột 7: `{{aggregate.date.sum.NoBreakfastGuestNum|number}}`
  * Cột 8-9: Để trống.
* **Dòng `Tổng` (toàn bộ kỳ xem báo cáo)**:
  * Cột 1: Chữ `Tổng` (căn giữa, font bold).
  * Cột 2-7: Tổng cộng toàn bộ số lượng các ngày trong kỳ (`{{aggregate.rows.sum.CheckinRoom|number}}`,...).
  * Cột 8-9: Để trống.

---

## 5. Schema `content_json` Mẫu Chuẩn Cho Form Designer

```json
{
  "header": [
    {
      "id": "daily_fd_header_band",
      "type": "columns",
      "style": { "display": "flex", "justifyContent": "space-between", "marginBottom": "6px" },
      "columns": [
        {
          "width": "35%",
          "blocks": [
            {
              "id": "daily_fd_logo",
              "type": "text",
              "content": "<div class=\"hotel-logo\" style=\"min-height: 50px;\">{{hotel.logo}}</div>"
            }
          ]
        },
        {
          "width": "65%",
          "blocks": [
            {
              "id": "daily_fd_hotel_info",
              "type": "text",
              "content": "<div style=\"text-align: right; font-size: 10px; line-height: 1.5;\"><div><b>Địa chỉ:</b> {{hotel.address}}</div><div><b>Nhân viên:</b> {{report.generated_by}}</div><div><b>Ngày:</b> {{report.generated_at}}</div></div>"
            }
          ]
        }
      ]
    },
    {
      "id": "daily_fd_divider",
      "type": "divider",
      "style": { "borderTop": "1px solid #111", "marginTop": "4px", "marginBottom": "12px" }
    },
    {
      "id": "daily_fd_title",
      "type": "text",
      "content": "<h1 style=\"font-size: 16px; font-weight: bold; text-align: center; margin: 0;\">BÁO CÁO LỄ TÂN HẰNG NGÀY</h1>",
      "style": { "textAlign": "center", "marginBottom": "4px" }
    },
    {
      "id": "daily_fd_period",
      "type": "text",
      "content": "<div style=\"font-size: 10px; text-align: center; font-weight: bold;\">Ngày {{parameters.p_from_date}} ~ {{parameters.p_to_date}}</div>",
      "style": { "textAlign": "center", "marginBottom": "12px" }
    }
  ],
  "detail": [
    {
      "id": "daily_fd_table",
      "type": "table",
      "dataSource": "rows",
      "style": { "width": "100%", "borderCollapse": "collapse", "fontSize": "9.5px" },
      "columns": [
        { "field": "Segment", "label": "Khách", "width": "15%", "align": "left", "bold": true },
        { "field": "CheckinRoom", "label": "Số phòng check in", "width": "9%", "align": "right" },
        { "field": "CheckoutRoom", "label": "Số phòng check out", "width": "9%", "align": "right" },
        { "field": "InhouseRoom", "label": "Số phòng inhouse", "width": "9%", "align": "right" },
        { "field": "DayUseRoom", "label": "Tổng số phòng ở trong ngày", "width": "11%", "align": "right" },
        { "field": "BreakfastGuestNum", "label": "Số lượng khách ăn sáng ngày hôm sau", "width": "12%", "align": "right" },
        { "field": "NoBreakfastGuestNum", "label": "Số lượng khách không ăn sáng ngày hôm sau", "width": "12%", "align": "right" },
        { "field": "Notes", "label": "Ghi chú", "width": "11%", "align": "left" },
        { "field": "CustomerFeedback", "label": "Ý kiến phản hồi khách hàng", "width": "12%", "align": "left" }
      ],
      "groups": [
        {
          "field": "DateFormatted",
          "label": "Ngày",
          "labelColor": "#b91c1c"
        }
      ],
      "customRows": [
        {
          "type": "subtotal",
          "targetGroup": "DateFormatted",
          "cells": [
            { "content": "Tổng", "align": "center", "bold": true },
            { "field": "CheckinRoom", "aggregate": "sum", "align": "right", "bold": true },
            { "field": "CheckoutRoom", "aggregate": "sum", "align": "right", "bold": true },
            { "field": "InhouseRoom", "aggregate": "sum", "align": "right", "bold": true },
            { "field": "DayUseRoom", "aggregate": "sum", "align": "right", "bold": true },
            { "field": "BreakfastGuestNum", "aggregate": "sum", "align": "right", "bold": true },
            { "field": "NoBreakfastGuestNum", "aggregate": "sum", "align": "right", "bold": true },
            { "content": "" },
            { "content": "" }
          ]
        },
        {
          "type": "grand_total",
          "cells": [
            { "content": "Tổng", "align": "center", "bold": true, "backgroundColor": "#d9deea" },
            { "field": "CheckinRoom", "aggregate": "sum", "align": "right", "bold": true, "backgroundColor": "#d9deea" },
            { "field": "CheckoutRoom", "aggregate": "sum", "align": "right", "bold": true, "backgroundColor": "#d9deea" },
            { "field": "InhouseRoom", "aggregate": "sum", "align": "right", "bold": true, "backgroundColor": "#d9deea" },
            { "field": "DayUseRoom", "aggregate": "sum", "align": "right", "bold": true, "backgroundColor": "#d9deea" },
            { "field": "BreakfastGuestNum", "aggregate": "sum", "align": "right", "bold": true, "backgroundColor": "#d9deea" },
            { "field": "NoBreakfastGuestNum", "aggregate": "sum", "align": "right", "bold": true, "backgroundColor": "#d9deea" },
            { "content": "", "backgroundColor": "#d9deea" },
            { "content": "", "backgroundColor": "#d9deea" }
          ]
        }
      ]
    }
  ],
  "footer": []
}
```

---

## 6. Logic Stored Procedure MySQL (`rpt_daily_frontdesk`)

Chuyển ngữ từ SQL Server `sp_275` sang MySQL:

```sql
CREATE PROCEDURE rpt_daily_frontdesk(
    IN p_from_date VARCHAR(20),
    IN p_to_date VARCHAR(20)
)
READS SQL DATA
BEGIN
    DECLARE v_from DATE;
    DECLARE v_to DATE;
    DECLARE v_cur_date DATE;

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

    -- Tạo bảng tạm lưu trữ kết quả cuối cùng
    DROP TEMPORARY TABLE IF EXISTS tmp_daily_frontdesk;
    CREATE TEMPORARY TABLE tmp_daily_frontdesk (
        `Date` DATE,
        `DateFormatted` VARCHAR(20),
        `SegmentOrder` INT,
        `Segment` VARCHAR(100),
        `CheckinRoom` INT DEFAULT 0,
        `CheckoutRoom` INT DEFAULT 0,
        `InhouseRoom` INT DEFAULT 0,
        `DayUseRoom` INT DEFAULT 0,
        `BreakfastGuestNum` INT DEFAULT 0,
        `NoBreakfastGuestNum` INT DEFAULT 0,
        `Notes` VARCHAR(255) DEFAULT '',
        `CustomerFeedback` VARCHAR(255) DEFAULT ''
    );

    SET v_cur_date = v_from;

    WHILE v_cur_date <= v_to DO
        -- 1. Nạp 4 phân khúc chuẩn cho từng ngày
        INSERT INTO tmp_daily_frontdesk (`Date`, `DateFormatted`, `SegmentOrder`, `Segment`)
        VALUES 
            (v_cur_date, DATE_FORMAT(v_cur_date, '%d/%m/%Y'), 1, 'Khách TA'),
            (v_cur_date, DATE_FORMAT(v_cur_date, '%d/%m/%Y'), 2, 'Khách OTA'),
            (v_cur_date, DATE_FORMAT(v_cur_date, '%d/%m/%Y'), 3, 'Khách Corp'),
            (v_cur_date, DATE_FORMAT(v_cur_date, '%d/%m/%Y'), 4, 'Khách Walk-in, FIT, Fanpage');

        -- 2. Cập nhật Số phòng Check-in trong ngày
        UPDATE tmp_daily_frontdesk t
        LEFT JOIN (
            SELECT 
                CASE 
                    WHEN m.name IN ('Travel Agent', 'FOC', 'Voucher') THEN 'Khách TA'
                    WHEN m.name = 'Online Travel Agent' THEN 'Khách OTA'
                    WHEN m.name = 'Corporate' THEN 'Khách Corp'
                    ELSE 'Khách Walk-in, FIT, Fanpage'
                END AS Segment,
                COUNT(DISTINCT r.id) AS TotalCheckin
            FROM booking_rooms r
            JOIN bookings b ON r.booking_id = b.id
            LEFT JOIN companies c ON b.company_id = c.id
            LEFT JOIN market_segments m ON c.market_segment_id = m.id
            WHERE CAST(r.arrival_date AS DATE) = v_cur_date
              AND r.status IN (0, 1, 2, 100)
              AND (r.room IS NULL OR r.room NOT LIKE '0%')
            GROUP BY Segment
        ) sub ON t.Segment = sub.Segment
        SET t.CheckinRoom = COALESCE(sub.TotalCheckin, 0)
        WHERE t.`Date` = v_cur_date;

        -- 3. Cập nhật Số phòng Check-out trong ngày
        UPDATE tmp_daily_frontdesk t
        LEFT JOIN (
            SELECT 
                CASE 
                    WHEN m.name IN ('Travel Agent', 'FOC', 'Voucher') THEN 'Khách TA'
                    WHEN m.name = 'Online Travel Agent' THEN 'Khách OTA'
                    WHEN m.name = 'Corporate' THEN 'Khách Corp'
                    ELSE 'Khách Walk-in, FIT, Fanpage'
                END AS Segment,
                COUNT(DISTINCT r.id) AS TotalCheckout
            FROM booking_rooms r
            JOIN bookings b ON r.booking_id = b.id
            LEFT JOIN companies c ON b.company_id = c.id
            LEFT JOIN market_segments m ON c.market_segment_id = m.id
            WHERE CAST(r.checkout_date AS DATE) = v_cur_date
              AND r.status IN (0, 1, 2)
              AND (r.room IS NULL OR r.room NOT LIKE '0%')
            GROUP BY Segment
        ) sub ON t.Segment = sub.Segment
        SET t.CheckoutRoom = COALESCE(sub.TotalCheckout, 0)
        WHERE t.`Date` = v_cur_date;

        -- 4. Cập nhật Số phòng Inhouse qua đêm
        UPDATE tmp_daily_frontdesk t
        LEFT JOIN (
            SELECT 
                CASE 
                    WHEN m.name IN ('Travel Agent', 'FOC', 'Voucher') THEN 'Khách TA'
                    WHEN m.name = 'Online Travel Agent' THEN 'Khách OTA'
                    WHEN m.name = 'Corporate' THEN 'Khách Corp'
                    ELSE 'Khách Walk-in, FIT, Fanpage'
                END AS Segment,
                COUNT(DISTINCT r.id) AS TotalInhouse
            FROM booking_rooms r
            JOIN bookings b ON r.booking_id = b.id
            LEFT JOIN companies c ON b.company_id = c.id
            LEFT JOIN market_segments m ON c.market_segment_id = m.id
            WHERE CAST(r.arrival_date AS DATE) < v_cur_date
              AND CAST(r.checkout_date AS DATE) > v_cur_date
              AND r.status IN (1, 2)
              AND (r.room IS NULL OR r.room NOT LIKE '0%')
            GROUP BY Segment
        ) sub ON t.Segment = sub.Segment
        SET t.InhouseRoom = COALESCE(sub.TotalInhouse, 0)
        WHERE t.`Date` = v_cur_date;

        -- 5. Tổng số phòng ở trong ngày = Inhouse + Checkin
        UPDATE tmp_daily_frontdesk t
        SET t.DayUseRoom = t.InhouseRoom + t.CheckinRoom
        WHERE t.`Date` = v_cur_date;

        -- 6. Suất ăn sáng ngày hôm sau (Date + 1)
        UPDATE tmp_daily_frontdesk t
        LEFT JOIN (
            SELECT 
                CASE 
                    WHEN m.name IN ('Travel Agent', 'FOC', 'Voucher') THEN 'Khách TA'
                    WHEN m.name = 'Online Travel Agent' THEN 'Khách OTA'
                    WHEN m.name = 'Corporate' THEN 'Khách Corp'
                    ELSE 'Khách Walk-in, FIT, Fanpage'
                END AS Segment,
                SUM(CASE WHEN COALESCE(c_adult.has_breakfast, r.breakfast, 1) = 1 THEN 1 ELSE 0 END) AS TotalBf,
                SUM(CASE WHEN COALESCE(c_adult.has_breakfast, r.breakfast, 1) = 0 THEN 1 ELSE 0 END) AS TotalNoBf
            FROM booking_rooms r
            JOIN bookings b ON r.booking_id = b.id
            LEFT JOIN customers c_adult ON c_adult.booking_room_id = r.id
            LEFT JOIN companies c ON b.company_id = c.id
            LEFT JOIN market_segments m ON c.market_segment_id = m.id
            WHERE CAST(r.arrival_date AS DATE) <= DATE_ADD(v_cur_date, INTERVAL 1 DAY)
              AND CAST(r.checkout_date AS DATE) >= DATE_ADD(v_cur_date, INTERVAL 1 DAY)
              AND r.status IN (0, 1, 2, 100)
            GROUP BY Segment
        ) sub ON t.Segment = sub.Segment
        SET 
            t.BreakfastGuestNum = COALESCE(sub.TotalBf, 0),
            t.NoBreakfastGuestNum = COALESCE(sub.TotalNoBf, 0)
        WHERE t.`Date` = v_cur_date;

        SET v_cur_date = DATE_ADD(v_cur_date, INTERVAL 1 DAY);
    END WHILE;

    SELECT * FROM tmp_daily_frontdesk
    ORDER BY `Date` ASC, `SegmentOrder` ASC;

    DROP TEMPORARY TABLE IF EXISTS tmp_daily_frontdesk;
END;
```
