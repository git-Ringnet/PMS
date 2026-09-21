# Đặc Tả Kỹ Thuật: Báo Cáo Hủy Hóa Đơn / Thanh Toán (Dòng 155)

> **Tài liệu chuẩn bị cho Agent triển khai tiếp theo**  
> Dựa trên phân tích từ file Excel `DANH MỤC BÁO CÁO.xlsx` (Dòng 155, Sheet 51 `BC hủy hđ-thanh toán`), ảnh giao diện thực tế `sheet51_img2_image60.png` và Stored Procedure gốc trên MS SQL Server (`sp_068`, `sp_070`).

---

## 1. Thông Tin Chung & Định Danh

* **Tên báo cáo tiếng Việt**: Báo cáo hủy hóa đơn/thanh toán
* **Mã báo cáo (`code`)**: `CANCELLED_INVOICES_PAYMENTS`
* **Mã nguồn dữ liệu (`data_source_code`)**: `RPT_CANCELLED_INVOICES_PAYMENTS`
* **Mã template (`template_code`)**: `CANCELLED_INVOICES_PAYMENTS_REFERENCE`
* **Menu hiển thị**: Báo cáo hủy phòng / Lễ tân (`frontdesk`, `reservation`)
* **Vị trí trong Excel danh mục**: Dòng 155 (STT 9.0)
* **Sheet tham chiếu trong Excel**: Sheet 51 (`BC hủy hđ-thanh toán`)
* **Ảnh chụp màn hình thực tế**: `[.codex/docs/doc_baocao/images/dong_155_ui_mau.png](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/images/dong_155_ui_mau.png)`
* **File SQL legacy tham chiếu**:
  * [sp_068_full.sql](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/sql/sp_068_full.sql): Xử lý chế độ **Huỷ hoá đơn** (dịch vụ buồng phòng / lễ tân)
  * [sp_070_full.sql](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/sql/sp_070_full.sql): Xử lý chế độ **Huỷ thanh toán** (phiếu thu / cấn trừ)

---

## 2. Phân Tích Chi Tiết Giao Diện Từ Ảnh Thực Tế (`dong_155_ui_mau.png`)

### 2.1. Panel Bộ Lọc Bên Trái (Left Filter Panel)
* **Chiều rộng panel**: ~ 260px - 280px.
* **Các thành phần điều khiển**:
  1. **Radio Button (Chế độ xem)**:
     - `(•) Huỷ hoá đơn` & `( ) Huỷ thanh toán`
     - Nằm ngang hàng, margin-bottom: 12px.
     - Giá trị: `BILL` (Huỷ hoá đơn) / `PAYMENT` (Huỷ thanh toán).
  2. **Ngày (`p_from_date` ~ `p_to_date`)**:
     - Date Range Picker, giá trị hiển thị mặc định: `Hôm nay`.
  3. **Bộ phận (`p_department`)**:
     - Dropdown Select, placeholder: `Select Value` (hoặc `-- Tất cả --`), nguồn dữ liệu: `service-departments`.
  4. **Outlet (`p_outlet`)**:
     - Dropdown Select, placeholder: `Select Value`, nguồn: danh mục outlet.
  5. **Dịch vụ (`p_service`)**:
     - Dropdown Select, placeholder: `Select Value`, nguồn: `hotel-services` (Chỉ hiện/áp dụng khi chọn radio `Huỷ hoá đơn`).
  6. **Người dùng (`p_user`)**:
     - Dropdown Select, placeholder: `Select Value`, nguồn: `users`.
  7. **Sắp xếp theo (`p_sort_by` & `p_sort_type`)**:
     - Gồm 2 ô cạnh nhau: Chọn trường sắp xếp (`Room` - Phòng, `CreatedHour` - Giờ tạo) và thứ tự (`ASC` / `DESC`). Mặc định: `Room` - `ASC`.
  8. **Nút thực thi**:
     - Button `Hiển thị báo cáo`: Màu xanh biển `#3b82f6`, chữ trắng, bo góc 4px.

---

## 3. Cấu Hình Form Designer & Thông Số Thiết Kế Chi Tiết

### 3.1. Thiết Lập Trang In (Page Settings)
* `page_size`: `'A4'`
* `page_orientation`: `'landscape'` (Khổ ngang - do bảng có 11 cột nhiều thông tin)
* `margin_top`: `8` (mm)
* `margin_bottom`: `8` (mm)
* `margin_left`: `6` (mm)
* `margin_right`: `6` (mm)

### 3.2. Bảng Màu & Typography (Design Tokens)
* **Font chữ chính**: `Arial, Helvetica, sans-serif`
* **Màu chữ chính**: `#111111`
* **Màu viền bảng**: `#aeb5c0` (1px solid)
* **Màu nền Header bảng**: `#d9deea` (xanh xám nhạt đặc trưng của PMS)
* **Màu nhãn nhóm Ngày**: `#b91c1c` (đỏ đậm)
* **Màu nhãn nhóm Bộ phận**: `#1e293b` (xanh than / đen xám)
* **Màu nền dòng Tổng Giai Đoạn**: `#d9deea`
* **Cỡ chữ**:
  * Tiêu đề chính (`H1`): `16px`, `font-weight: bold`, `text-align: center`
  * Tiêu đề phụ (Ngày áp dụng): `10px`, `font-weight: bold`, `text-align: center`
  * Thông tin Header khách sạn: `10px`, `line-height: 1.5`
  * Toàn bộ bảng dữ liệu (Header, Dòng dữ liệu, Subtotal, Total): `9.5px`
  * Chữ ký Footer: `10px`, `font-weight: bold`
* **Padding ô bảng**: `3px 4px` (đảm bảo hiển thị gọn gàng, không bị tràn trang)

---

## 4. Chi Tiết Layout Bảng Dữ Liệu (11 Cột - 2 Tầng Header)

### 4.1. Ma Trận Header 2 Tầng

```text
+-----+---------------+---------------+-----------------------------------------------+-----------------------------------------------+---------------+
|     |               |               |                 Thông Tin Tạo                 |                 Thông Tin Hủy                 |               |
| STT |  Mã ĐK/Phòng  |    Dịch Vụ    +---------+---------+-------------+-------------+---------+---------+-------------+-------------+     Lý Do     |
|     |               |               |  Ngày   |   Giờ   |    Tổng     | Người Dùng  |  Ngày   |   Giờ   |    Tổng     | Người Dùng  |               |
+-----+---------------+---------------+---------+---------+-------------+-------------+---------+---------+-------------+-------------+---------------+
```

### 4.2. Chi Tiết Thuộc Tính Từng Cột

| STT | Tên cột hiển thị | Field Name | Width | Align | Format | CSS Đặc Biệt |
|---|---|---|---|---|---|---|
| 1 | `STT` | `Index` | `3.5%` | Center | Số nguyên | `font-weight: normal;` |
| 2 | `Mã ĐK/Phòng` | `Room1` | `10%` | Center | Text (vd: `C:GAL105/105`) | `font-weight: normal;` |
| 3 | `Dịch Vụ` | `Service` | `12.5%` | Left | Text (vd: `Minibar`, `Cash`) | `font-weight: normal;` |
| 4 | `Ngày` (Tạo) | `CreatedDate` | `7.5%` | Center | `dd/mm/yyyy` | Nằm dưới `Thông Tin Tạo` |
| 5 | `Giờ` (Tạo) | `CreatedHour` | `5.5%` | Center | `HH:mm` | Nằm dưới `Thông Tin Tạo` |
| 6 | `Tổng` (Tạo) | `AmountAm` | `10%` | Right | Number (`#,##0`) | `font-weight: normal;` |
| 7 | `Người Dùng` (Tạo)| `CreatedUser` | `8%` | Left | Text | Nằm dưới `Thông Tin Tạo` |
| 8 | `Ngày` (Hủy) | `Date` | `7.5%` | Center | `dd/mm/yyyy` | Nằm dưới `Thông Tin Hủy` |
| 9 | `Giờ` (Hủy) | `OpenTime` | `5.5%` | Center | `HH:mm` | Nằm dưới `Thông Tin Hủy` |
| 10| `Tổng` (Hủy) | `AmountDuong`| `10%` | Right | Number (`#,##0`) | `font-weight: normal;` |
| 11| `Người Dùng` (Hủy)| `Username` | `8%` | Left | Text | Nằm dưới `Thông Tin Hủy` |
| 12| `Lý Do` | `Description` | `12%` | Left | Text (Lý do hủy) | `white-space: normal;` |

### 4.3. Gom Nhóm (Grouping)
1. **Cấp 1 - Gom nhóm theo Ngày (`DateFormatted`)**:
   * Dòng tiêu đề:
     ```html
     <tr class="group-header-row date-group">
       <td colspan="12" style="font-weight: bold; background-color: #ffffff; border-top: 1px solid #94a3b8; border-bottom: 1px solid #aeb5c0; padding: 4px 6px;">
         <span style="color: #b91c1c; font-weight: bold;">Ngày:</span> {{group.value}}
       </td>
     </tr>
     ```
2. **Cấp 2 - Gom nhóm theo Bộ Phận (`DepartmentId` hoặc `DepartmentName`)**:
   * Dòng tiêu đề con:
     ```html
     <tr class="group-header-row dept-group">
       <td colspan="12" style="font-weight: bold; background-color: #ffffff; padding-left: 14px; border-bottom: 1px solid #cbd5e1;">
         Bộ phận: {{group.value}}
       </td>
     </tr>
     ```

### 4.4. Dòng Tổng Phụ & Tổng Cộng
* **Dòng `Tổng` (của Bộ phận trong ngày)**:
  * Cột 1-5 (colspan 5): Chữ `Tổng` (căn giữa, font bold).
  * Cột 6: `{{aggregate.department.sum.AmountAm|number}}` (căn phải, font bold).
  * Cột 7-9 (colspan 3): Để trống.
  * Cột 10: `{{aggregate.department.sum.AmountDuong|number}}` (căn phải, font bold).
  * Cột 11-12 (colspan 2): Để trống.
* **Dòng `Tổng Giai Đoạn` (Grand Total toàn bộ báo cáo)**:
  * Nền: `#d9deea`, chữ đậm, viền trên 1px, viền dưới 2px solid `#aeb5c0`.
  * Cột 1-5 (colspan 5): Chữ `Tổng Giai Đoạn` (căn giữa, font bold).
  * Cột 6: `{{aggregate.rows.sum.AmountAm|number}}` (căn phải, font bold).
  * Cột 7-9 (colspan 3): Để trống.
  * Cột 10: `{{aggregate.rows.sum.AmountDuong|number}}` (căn phải, font bold).
  * Cột 11-12 (colspan 2): Để trống.

### 4.5. Footer Chữ Ký
Margin top: 25px. 3 cột cân xứng:
* Cột 1: `FOM` (Trưởng bộ phận Lễ tân)
* Cột 2: `ACC` (Kế toán trưởng)
* Cột 3: `GM` (Tổng giám đốc)

---

## 5. Schema `content_json` Mẫu Chuẩn Cho Form Designer

Dưới đây là cấu trúc `blocks` hoàn chỉnh để Agent kế tiếp đưa trực tiếp vào template reference PHP:

```json
{
  "header": [
    {
      "id": "cancelled_header_band",
      "type": "columns",
      "style": { "display": "flex", "justifyContent": "space-between", "marginBottom": "6px" },
      "columns": [
        {
          "width": "35%",
          "blocks": [
            {
              "id": "cancelled_logo",
              "type": "text",
              "content": "<div class=\"hotel-logo\" style=\"min-height: 50px;\">{{hotel.logo}}</div>"
            }
          ]
        },
        {
          "width": "65%",
          "blocks": [
            {
              "id": "cancelled_hotel_info",
              "type": "text",
              "content": "<div style=\"text-align: right; font-size: 10px; line-height: 1.5;\"><div><b>Địa chỉ:</b> {{hotel.address}}</div><div><b>Nhân viên:</b> {{report.generated_by}}</div><div><b>Ngày:</b> {{report.generated_at}}</div></div>"
            }
          ]
        }
      ]
    },
    {
      "id": "cancelled_divider",
      "type": "divider",
      "style": { "borderTop": "1px solid #111", "marginTop": "4px", "marginBottom": "12px" }
    },
    {
      "id": "cancelled_title",
      "type": "text",
      "content": "<h1 style=\"font-size: 16px; font-weight: bold; text-align: center; margin: 0;\">BÁO CÁO HUỶ HOÁ ĐƠN</h1>",
      "style": { "textAlign": "center", "marginBottom": "4px" }
    },
    {
      "id": "cancelled_period",
      "type": "text",
      "content": "<div style=\"font-size: 10px; text-align: center; font-weight: bold;\">Ngày: {{parameters.p_from_date}} ~ {{parameters.p_to_date}}</div>",
      "style": { "textAlign": "center", "marginBottom": "10px" }
    }
  ],
  "detail": [
    {
      "id": "cancelled_table",
      "type": "table",
      "dataSource": "rows",
      "style": { "width": "100%", "borderCollapse": "collapse", "fontSize": "9.5px" },
      "headerGroups": [
        {
          "headers": [
            { "label": "STT", "rowspan": 2, "width": "3.5%", "align": "center" },
            { "label": "Mã ĐK/Phòng", "rowspan": 2, "width": "10%", "align": "center" },
            { "label": "Dịch Vụ", "rowspan": 2, "width": "12.5%", "align": "left" },
            { "label": "Thông Tin Tạo", "colspan": 4, "width": "31%", "align": "center" },
            { "label": "Thông Tin Hủy", "colspan": 4, "width": "31%", "align": "center" },
            { "label": "Lý Do", "rowspan": 2, "width": "12%", "align": "left" }
          ]
        },
        {
          "headers": [
            { "label": "Ngày", "width": "7.5%", "align": "center" },
            { "label": "Giờ", "width": "5.5%", "align": "center" },
            { "label": "Tổng", "width": "10%", "align": "right" },
            { "label": "Người Dùng", "width": "8%", "align": "left" },
            { "label": "Ngày", "width": "7.5%", "align": "center" },
            { "label": "Giờ", "width": "5.5%", "align": "center" },
            { "label": "Tổng", "width": "10%", "align": "right" },
            { "label": "Người Dùng", "width": "8%", "align": "left" }
          ]
        }
      ],
      "groups": [
        {
          "field": "InvoiceDateFormatted",
          "label": "Ngày:",
          "labelColor": "#b91c1c"
        },
        {
          "field": "DepartmentId",
          "label": "Bộ phận:",
          "labelColor": "#1e293b"
        }
      ],
      "customRows": [
        {
          "type": "subtotal",
          "targetGroup": "DepartmentId",
          "cells": [
            { "colspan": 5, "content": "Tổng", "align": "center", "bold": true },
            { "field": "AmountAm", "aggregate": "sum", "align": "right", "bold": true },
            { "colspan": 3, "content": "" },
            { "field": "AmountDuong", "aggregate": "sum", "align": "right", "bold": true },
            { "colspan": 2, "content": "" }
          ]
        },
        {
          "type": "grand_total",
          "cells": [
            { "colspan": 5, "content": "Tổng Giai Đoạn", "align": "center", "bold": true, "backgroundColor": "#d9deea" },
            { "field": "AmountAm", "aggregate": "sum", "align": "right", "bold": true, "backgroundColor": "#d9deea" },
            { "colspan": 3, "content": "", "backgroundColor": "#d9deea" },
            { "field": "AmountDuong", "aggregate": "sum", "align": "right", "bold": true, "backgroundColor": "#d9deea" },
            { "colspan": 2, "content": "", "backgroundColor": "#d9deea" }
          ]
        }
      ]
    }
  ],
  "footer": [
    {
      "id": "cancelled_signatures",
      "type": "columns",
      "style": { "display": "flex", "justifyContent": "space-around", "marginTop": "25px", "textAlign": "center" },
      "columns": [
        { "width": "33%", "blocks": [{ "id": "sig_fom", "type": "text", "content": "<b>FOM</b>" }] },
        { "width": "33%", "blocks": [{ "id": "sig_acc", "type": "text", "content": "<b>ACC</b>" }] },
        { "width": "33%", "blocks": [{ "id": "sig_gm", "type": "text", "content": "<b>GM</b>" }] }
      ]
    }
  ]
}
```

---

## 6. Logic Stored Procedure MySQL (`rpt_cancelled_invoices_payments`)

Chi tiết logic truy vấn kết hợp cả 2 store legacy `sp_068` và `sp_070`:

```sql
CREATE PROCEDURE rpt_cancelled_invoices_payments(
    IN p_mode VARCHAR(10),        -- 'BILL' hoặc 'PAYMENT'
    IN p_from_date VARCHAR(20),
    IN p_to_date VARCHAR(20),
    IN p_department VARCHAR(20),
    IN p_outlet VARCHAR(20),
    IN p_service VARCHAR(20),
    IN p_user VARCHAR(50),
    IN p_sort_by VARCHAR(20),
    IN p_sort_type VARCHAR(10)
)
READS SQL DATA
BEGIN
    DECLARE v_prefix VARCHAR(20) DEFAULT '';
    DECLARE v_from DATE;
    DECLARE v_to DATE;

    SELECT COALESCE(prefix_booking_id, '') INTO v_prefix FROM hotel_settings LIMIT 1;

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

    IF UPPER(COALESCE(p_mode, 'BILL')) = 'BILL' THEN
        -- TRƯỜNG HỢP 1: HUỶ HOÁ ĐƠN (sp_068)
        SELECT
            duong.Ma AS Id,
            CASE
                WHEN pt.room IS NULL AND duong.RegisterID2 IS NOT NULL THEN CONCAT(v_prefix, duong.RegisterID2)
                WHEN pt.room IS NOT NULL AND duong.RegisterID2 IS NULL THEN pt.room
                ELSE CONCAT(v_prefix, duong.RegisterID2, '/', pt.room)
            END AS Room1,
            COALESCE(srv.name, srv.service, duong.DescriptionServive, duong.ServiceId, '') AS Service,
            COALESCE(duong.DepartmentId, am.DepartmentId, 'FO') AS DepartmentId,
            DATE_FORMAT(am.Date, '%d/%m/%Y') AS CreatedDate,
            COALESCE(am.OpenTime, '') AS CreatedHour,
            COALESCE(am.TotalAmount0, am.Amount, 0) AS AmountAm,
            COALESCE(am.CreatedUser, am.Username, '') AS CreatedUser,
            DATE_FORMAT(duong.Date, '%d/%m/%Y') AS InvoiceDateFormatted,
            DATE_FORMAT(duong.Date, '%d/%m/%Y') AS Date,
            COALESCE(duong.OpenTime, '') AS OpenTime,
            COALESCE(duong.TotalAmount0, duong.Amount, 0) AS AmountDuong,
            COALESCE(duong.Username, '') AS Username,
            COALESCE(duong.DescriptionServive, duong.description, '') AS Description
        FROM service_bills duong
        INNER JOIN service_bills am ON duong.Pack1 = CAST(am.Ma AS CHAR) OR duong.AdjustmentBillId = am.Ma
        LEFT JOIN hotel_services srv ON srv.code = duong.ServiceId OR srv.Ma = duong.ServiceId
        LEFT JOIN booking_rooms pt ON pt.id = duong.RentalRoomId2 OR pt.legacy_id = duong.RentalRoomId2
        WHERE duong.Status = 3 AND duong.Edit = 1
          AND CAST(duong.Date AS DATE) BETWEEN v_from AND v_to
          AND (p_department = '' OR duong.DepartmentId = p_department)
          AND (p_outlet = '' OR duong.Outlet = p_outlet)
          AND (p_service = '' OR duong.ServiceId = p_service)
          AND (p_user = '' OR duong.Username LIKE CONCAT('%', p_user, '%'))
        ORDER BY
            CASE WHEN p_sort_by = 'CreatedHour' AND p_sort_type = 'DESC' THEN am.OpenTime END DESC,
            CASE WHEN p_sort_by = 'CreatedHour' THEN am.OpenTime END ASC,
            CASE WHEN p_sort_by = 'Room' AND p_sort_type = 'DESC' THEN pt.room END DESC,
            pt.room ASC;
    ELSE
        -- TRƯỜNG HỢP 2: HUỶ THANH TOÁN (sp_070)
        SELECT
            duong.id AS Id,
            CASE
                WHEN pt.room IS NULL AND duong.booking_id IS NOT NULL THEN CONCAT(v_prefix, duong.booking_id)
                WHEN pt.room IS NOT NULL AND duong.booking_id IS NULL THEN pt.room
                ELSE CONCAT(v_prefix, duong.booking_id, '/', pt.room)
            END AS Room1,
            COALESCE(pm.name, duong.payment_method_id, 'Cash') AS Service,
            COALESCE(duong.department_id, am.department_id, 'FO') AS DepartmentId,
            DATE_FORMAT(am.date, '%d/%m/%Y') AS CreatedDate,
            COALESCE(am.open_time, '') AS CreatedHour,
            COALESCE(am.amount, 0) AS AmountAm,
            COALESCE(am.created_by, '') AS CreatedUser,
            DATE_FORMAT(duong.date, '%d/%m/%Y') AS InvoiceDateFormatted,
            DATE_FORMAT(duong.date, '%d/%m/%Y') AS Date,
            COALESCE(duong.open_time, '') AS OpenTime,
            COALESCE(duong.amount, 0) AS AmountDuong,
            COALESCE(duong.created_by, duong.username, '') AS Username,
            COALESCE(duong.reason, duong.description, '') AS Description
        FROM payments duong
        INNER JOIN payments am ON duong.reversal_ref = am.id OR duong.pack1 = CAST(am.id AS CHAR) OR duong.pack1 = CAST(am.legacy_id AS CHAR)
        LEFT JOIN payment_methods pm ON pm.code = duong.payment_method_id
        LEFT JOIN booking_rooms pt ON pt.id = duong.booking_room_id
        WHERE duong.status = 3 AND duong.edit_flag = 1
          AND CAST(duong.date AS DATE) BETWEEN v_from AND v_to
          AND (p_department = '' OR duong.department_id = p_department)
          AND (p_outlet = '' OR duong.outlet = p_outlet)
          AND (p_user = '' OR duong.created_by LIKE CONCAT('%', p_user, '%'))
        ORDER BY
            CASE WHEN p_sort_by = 'CreatedHour' AND p_sort_type = 'DESC' THEN am.open_time END DESC,
            CASE WHEN p_sort_by = 'CreatedHour' THEN am.open_time END ASC,
            CASE WHEN p_sort_by = 'Room' AND p_sort_type = 'DESC' THEN pt.room END DESC,
            pt.room ASC;
    END IF;
END;
```
