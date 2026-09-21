# Đặc Tả Kỹ Thuật: Báo Cáo Dự Kiến Doanh Thu Tiền Phòng (Màn Hình Sang Ngày) - Dòng 164

> **Tài liệu chuẩn bị cho Agent triển khai tiếp theo**  
> Dựa trên phân tích từ file Excel `DANH MỤC BÁO CÁO.xlsx` (Dòng 164, Sheet 2 `Báo cáo dự kiến doanh thu tiền`), ảnh giao diện thực tế màn hình Sang ngày `[.codex/docs/doc_baocao/images/dong_164_ui_mau.png](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/images/dong_164_ui_mau.png)`, ảnh popup báo cáo thực tế `[.codex/docs/doc_baocao/images/dong_164_popup_ui_mau.png](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/images/dong_164_popup_ui_mau.png)` và Stored Procedure gốc trên MS SQL Server `ProVistaArmyHotel.dbo.sp_095` ([ProVistaArmyHotel_sp_095.sql](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/sql/ProVistaArmyHotel_sp_095.sql)).

---

## 1. Thông Tin Chung & Định Danh

* **Tên báo cáo tiếng Việt**: Báo cáo dự kiến doanh thu tiền phòng
* **Mã báo cáo (`code`)**: `EXPECTED_ROOM_REVENUE_NIGHT_AUDIT`
* **Mã nguồn dữ liệu (`data_source_code`)**: `RPT_EXPECTED_ROOM_REVENUE_NIGHT_AUDIT`
* **Mã template (`template_code`)**: `EXPECTED_ROOM_REVENUE_REFERENCE`
* **Nhóm báo cáo (`group`)**: `Báo cáo quản lý / Sang ngày`
* **Menu & Vị trí kích hoạt**: 
  * Menu chính: `['night_audit', 'frontdesk', 'report']`
  * **Vị trí kích hoạt đặc biệt**: Nút bấm `Báo cáo dự kiến doanh thu tiền phòng` đặt tại thanh bottom action bar của màn hình **Sang Ngày (Night Audit)** (xem ảnh `dong_164_ui_mau.png`).
* **Vị trí trong Excel danh mục**: Dòng 164 (STT 18)
* **Sheet tham chiếu trong Excel**: Sheet 2 (`Báo cáo dự kiến doanh thu tiền`)
* **Mô tả nghiệp vụ trong Excel**:
  * "Báo cáo tại màn hình sang ngày. Báo cáo xem được dự kiến các bill dịch vụ sẽ chạy khi hệ thống sang ngày + các dịch vụ đã post trong ngày để kiểm tra trước doanh thu trước khi sang ngày."
* **Ảnh chụp màn hình thực tế**:
  * Màn hình sang ngày chứa nút bấm: `[.codex/docs/doc_baocao/images/dong_164_ui_mau.png](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/images/dong_164_ui_mau.png)`
  * Popup báo cáo hoàn chỉnh khi click: `[.codex/docs/doc_baocao/images/dong_164_popup_ui_mau.png](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/images/dong_164_popup_ui_mau.png)`
* **File SQL legacy tham chiếu**: [ProVistaArmyHotel_sp_095.sql](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/sql/ProVistaArmyHotel_sp_095.sql)

## Trạng thái tích hợp frontend

- Nút **Báo cáo dự kiến doanh thu tiền phòng** trong `frontend/src/pages/frontdesk/DayClosePage.vue` mở `/reports?report=EXPECTED_ROOM_REVENUE_NIGHT_AUDIT`.
- ReportsPage mở report theo mã này và dùng `$today` làm `p_date` dựa trên ngày PMS; người dùng vẫn bấm **Hiển thị báo cáo** để chạy truy vấn.
- Route `/reports` hiện yêu cầu quyền `mgmt.report.view`; người dùng chỉ có quyền màn hình Sang ngày có thể không mở được report.
- Đã nối handler frontend; chưa kiểm tra bằng browser hoặc xác nhận quyền trên tài khoản thực tế.

---

## 2. Phân Tích Giao Diện & Bố Cục Báo Cáo

### 2.1. Điểm Kích Hoạt Tại Màn Hình Sang Ngày (`dong_164_ui_mau.png`)
* **Màn hình**: Màn hình chức năng `Sang Ngày` (Night Audit).
* **Nút bấm**: Nút màu xanh `Báo cáo dự kiến doanh thu tiền phòng` với icon biểu đồ nằm ở góc dưới bên phải màn hình.
* **Cơ chế hoạt động**: Khi người dùng chuẩn bị sang ngày, click vào nút này để mở Modal / Tab in báo cáo dự kiến doanh thu sẽ được tự động ghi nhận vào tài khoản phòng khi thực hiện đóng ngày.

### 2.2. Bố Cục Trang Báo Cáo Popup (`dong_164_popup_ui_mau.png`)
* **Khổ giấy**: A4 Landscape (Ngang).
* **Header**:
  * Logo khách sạn bên góc trái.
  * Tiêu đề chính giữa: **BÁO CÁO DOANH THU DỰ KIẾN** (Font đậm, cỡ 18pt, căn giữa).
  * Dòng ngày báo cáo: `Ngày: dd/MM/yyyy` (ví dụ trên ảnh mẫu: `Ngày: 15/08/2026`).
* **Bảng Dữ Liệu Chi Tiết**:
  * Gồm 11 cột:
    1. `STT`: Số thứ tự tăng dần từ 1..N.
    2. `Mã BK`: Mã đăng ký đặt phòng (`vw.BookingId` / `BookingId2`).
    3. `Khách`: Họ tên khách hàng lưu trú tại phòng (`Guest`).
    4. `Phòng`: Số phòng đang ở (`Room`, ví dụ: 605, 807, 1109...).
    5. `Ngày Đến`: Ngày check-in phòng (`ArrivalDate` - định dạng `dd-MM-yyyy`).
    6. `Ngày Đi`: Ngày check-out dự kiến (`DepartureDate` - định dạng `dd-MM-yyyy`).
    7. `Dịch Vụ`: Mã dịch vụ (`ServiceId`, ví dụ: `RM` là tiền phòng, `MB` là minibar, `LA` là giặt là, `BK` là ăn sáng phụ).
    8. `Mô Tả Dịch Vụ`: Diễn giải dịch vụ (`Dịch vụ phòng nghỉ`, `Ăn sáng trẻ em`...).
    9. `Tổng`: Số tiền dự kiến post (`RateTotal` / `Total` - VND, định dạng `#,#00`).
    10. `Công Ty`: Tên công ty / lữ hành đại diện của booking (`Company`).
    11. `Ghi Chú`: Ghi chú đặt phòng hoặc ghi chú phòng (`NoteBooking`).
* **Dòng Tổng Cộng (Grand Total)**:
  * Nằm ở cuối bảng: Tổng cộng doanh thu dự kiến tiền phòng và dịch vụ cố định trong đêm audit.

---

## 3. Cấu Trúc Toàn Diện 100% Của `content_json` (Form Designer)

> **Dành cho Agent triển khai:**  
> File PHP dưới đây chứa toàn bộ định nghĩa metadata, cấu trúc khối `blocks`, cột `columns`, dòng tổng cộng `customRows`, template HTML và CSS theo chuẩn của `TemplateRendererService`. Hãy đặt tại:  
> `backend/database/report_templates/expected_room_revenue_reference.php`.

```php
<?php

use App\Services\TemplateRendererService;

return new class
{
    public function definition(): array
    {
        return [
            'code' => 'EXPECTED_ROOM_REVENUE_NIGHT_AUDIT',
            'name' => 'Báo cáo dự kiến doanh thu tiền phòng',
            'report' => 'EXPECTED_ROOM_REVENUE_REFERENCE',
            'page_size' => 'A4',
            'page_orientation' => 'landscape',
            'margin_top' => 8,
            'margin_right' => 5,
            'margin_bottom' => 8,
            'margin_left' => 5,
            'version' => '1.0',
            'content_html' => $this->html(),
            'content_json' => $this->blocks(),
            'css' => $this->css(),
            'columns' => $this->columns(),
            'data_contract' => [
                'rows' => [
                    'STT' => 'integer',
                    'BookingCode' => 'string',
                    'Guest' => 'string',
                    'Room' => 'string',
                    'ArrivalDate' => 'string',
                    'DepartureDate' => 'string',
                    'ServiceId' => 'string',
                    'ServiceName' => 'string',
                    'RateTotal' => 'number',
                    'Company' => 'string',
                    'NoteBooking' => 'string',
                    'Adult' => 'integer',
                    'Child' => 'integer',
                ],
                'parameters' => [
                    'p_date' => 'string',
                ],
            ],
        ];
    }

    public function columns(): array
    {
        return [
            ['key' => 'STT', 'title' => 'STT', 'width' => '45px', 'align' => 'center'],
            ['key' => 'BookingCode', 'title' => 'Mã BK', 'width' => '75px', 'align' => 'center'],
            ['key' => 'Guest', 'title' => 'Khách', 'width' => '180px', 'align' => 'left'],
            ['key' => 'Room', 'title' => 'Phòng', 'width' => '65px', 'align' => 'center'],
            ['key' => 'ArrivalDate', 'title' => 'Ngày Đến', 'width' => '90px', 'align' => 'center'],
            ['key' => 'DepartureDate', 'title' => 'Ngày Đi', 'width' => '90px', 'align' => 'center'],
            ['key' => 'ServiceId', 'title' => 'Dịch Vụ', 'width' => '65px', 'align' => 'center'],
            ['key' => 'ServiceName', 'title' => 'Mô Tả Dịch Vụ', 'width' => '170px', 'align' => 'left'],
            ['key' => 'RateTotal', 'title' => 'Tổng', 'width' => '110px', 'align' => 'right', 'format' => '#,##0'],
            ['key' => 'Company', 'title' => 'Công Ty', 'width' => '130px', 'align' => 'left'],
            ['key' => 'NoteBooking', 'title' => 'Ghi Chú', 'width' => '110px', 'align' => 'left'],
        ];
    }

    public function blocks(): array
    {
        return [
            'header' => [
                'type' => 'header',
                'title' => 'BÁO CÁO DOANH THU DỰ KIẾN',
                'show_logo' => true,
                'meta_fields' => [
                    ['label' => 'Ngày', 'value' => '{p_date}'],
                ],
            ],
            'grid_details' => [
                'type' => 'grid',
                'data_source' => 'rows',
                'columns' => $this->columns(),
                'customRows' => [
                    [
                        'type' => 'grand_total',
                        'label' => 'Tổng Cộng:',
                        'aggregate' => [
                            'RateTotal' => 'sum',
                        ],
                    ],
                ],
            ],
        ];
    }

    public function html(): string
    {
        return <<<'HTML'
<div class="report-container">
    <div class="report-header">
        <div class="header-left">
            <img src="{hotel_logo}" class="hotel-logo" alt="Logo" />
        </div>
        <div class="header-center">
            <h1 class="report-title">BÁO CÁO DOANH THU DỰ KIẾN</h1>
            <div class="report-date-meta">
                <strong>Ngày:</strong> {p_date}
            </div>
        </div>
        <div class="header-right"></div>
    </div>

    <table class="report-table main-grid">
        <thead>
            <tr>
                <th style="width: 45px;">STT</th>
                <th style="width: 75px;">Mã BK</th>
                <th style="width: 180px;">Khách</th>
                <th style="width: 65px;">Phòng</th>
                <th style="width: 90px;">Ngày Đến</th>
                <th style="width: 90px;">Ngày Đi</th>
                <th style="width: 65px;">Dịch Vụ</th>
                <th style="width: 170px;">Mô Tả Dịch Vụ</th>
                <th style="width: 110px;">Tổng</th>
                <th style="width: 130px;">Công Ty</th>
                <th style="width: 110px;">Ghi Chú</th>
            </tr>
        </thead>
        <tbody>
            {#rows}
            <tr>
                <td class="text-center">{STT}</td>
                <td class="text-center font-bold text-primary">{BookingCode}</td>
                <td>{Guest}</td>
                <td class="text-center font-semibold">{Room}</td>
                <td class="text-center">{ArrivalDate}</td>
                <td class="text-center">{DepartureDate}</td>
                <td class="text-center font-medium">{ServiceId}</td>
                <td>{ServiceName}</td>
                <td class="text-right font-bold">{RateTotal}</td>
                <td>{Company}</td>
                <td>{NoteBooking}</td>
            </tr>
            {/rows}
        </tbody>
        <tfoot>
            <tr class="grand-total">
                <td colspan="8" class="text-right font-bold">Tổng Cộng:</td>
                <td class="text-right font-bold text-primary">{grand_total_rate}</td>
                <td colspan="2"></td>
            </tr>
        </tfoot>
    </table>
</div>
HTML;
    }

    public function css(): string
    {
        return <<<'CSS'
.report-container { font-family: 'Times New Roman', Times, serif; font-size: 10pt; color: #111827; }
.report-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 15px; }
.hotel-logo { max-height: 55px; max-width: 130px; }
.header-center { text-align: center; flex: 1; }
.report-title { font-size: 18pt; font-weight: bold; margin: 0 0 4px 0; text-transform: uppercase; }
.report-date-meta { font-size: 11pt; color: #374151; }
.header-right { width: 130px; }
.report-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; font-size: 9pt; }
.report-table th, .report-table td { border: 1px solid #d1d5db; padding: 4px 6px; }
.report-table th { background-color: #f3f4f6; font-weight: bold; text-align: center; }
.grand-total td { background-color: #e5e7eb; font-size: 10pt; padding: 6px; }
.text-center { text-align: center; }
.text-right { text-align: right; }
.font-bold { font-weight: bold; }
.font-semibold { font-weight: 600; }
.text-primary { color: #1d4ed8; }
CSS;
    }
};
```

---

## 4. Bóc Tách Chi Tiết Logic SQL Legacy (`sp_095`)

### 4.1. Bản Chất Nghiệp Vụ Của `sp_095`
Store `sp_095` nhận tham số `@date` (ngày đang chạy tiến trình Night Audit).
Nó truy vấn các phòng đang ở thỏa mãn điều kiện:
```sql
where @date between vw.ArrivalDate and vw.DepartureDate - 1 
  and vw.Status in (0, 1) 
  and vw.BookingId is not null
```
* Điều kiện `@date between vw.ArrivalDate and vw.DepartureDate - 1`: Phòng đang có khách ở qua đêm ngày `@date`. Đêm cuối cùng trước ngày check-out là `DepartureDate - 1`.
* `vw.Status in (0, 1)`: Phòng đang có trạng thái Đặt phòng hợp lệ (0: Booked / Check-in chờ, 1: In-house / Đang ở).

### 4.2. Hai Khối Dữ Liệu Gom Nhóm Trong `lst`
1. **Khối 1: Doanh thu tiền phòng (`ServiceId = 'RM'`)**:
   ```sql
   select RentalRoomId, ServiceId, Sum(Total) as Total, count(*) as Quantity
   from dbo.func_031(@date, @date)
   where ServiceId = 'RM'
   group by RentalRoomId, ServiceId
   ```
   * Hàm `func_031(@date, @date)` tính tiền phòng theo ngày dựa trên bảng giá gán cho phòng (`booking_room_rates` / `SP2100.Rate`).
2. **Khối 2: Doanh thu dịch vụ cố định theo ngày (`ServiceId <> 'RM'`)**:
   ```sql
   select RentalRoomId, ServiceId, sum(Total) as Total, sum(Quantity) as Quantity
   from SP2102
   where ServiceId != 'RM' and FromDate = @date and Quantity != 0
   group by RentalRoomId, ServiceId
   ```
   * Bảng `SP2102` lưu các gói dịch vụ định kỳ gán kèm theo phòng (như ăn sáng bổ sung, đón tiễn sân bay theo ngày, extra bed, phụ thu...).
3. **Thứ tự sắp xếp**:
   * Luôn ưu tiên hiển thị dòng tiền phòng (`ServiceId = 'RM'`) lên đầu cho mỗi phòng, tiếp theo là các dịch vụ khác theo mã dịch vụ tăng dần:
   `order by case when ServiceId = 'RM' then 0 else 1 end ASC, ServiceId ASC`.

---

## 5. Stored Procedure Chuẩn Hóa Trên MySQL 8.0

> Lưu tại: `backend/database/procedures/rpt_expected_room_revenue_night_audit.sql`.

```sql
DELIMITER $$

DROP PROCEDURE IF EXISTS `rpt_expected_room_revenue_night_audit`$$

CREATE PROCEDURE `rpt_expected_room_revenue_night_audit`(
    IN p_date DATE
)
BEGIN
    SET NOCOUNT ON;

    SET @prefix = (SELECT COALESCE(prefix_booking_id, '') FROM hotel_configurations LIMIT 1);
    IF @prefix IS NULL THEN
        SET @prefix = '';
    END IF;

    SET @row_index = 0;

    -- Bảng tạm dự kiến doanh thu gồm: Tiền phòng (RM) + Dịch vụ cố định hàng ngày (SP2102)
    DROP TEMPORARY TABLE IF EXISTS temp_expected_revenue;
    CREATE TEMPORARY TABLE temp_expected_revenue AS
    
    -- 1. Tiền phòng (RM) lấy từ bảng giá phòng theo ngày (booking_room_rates)
    SELECT 
        br.id AS booking_room_id,
        'RM' AS service_id,
        'Dịch vụ phòng nghỉ' AS service_name,
        COALESCE(brr.rate, br.room_rate, 0) AS total_amount,
        1 AS quantity
    FROM booking_rooms br
    LEFT JOIN booking_room_rates brr ON brr.booking_room_id = br.id AND brr.rate_date = p_date
    WHERE p_date >= br.arrival_date AND p_date < br.departure_date
      AND br.status IN (0, 1)
      AND br.deleted_at IS NULL

    UNION ALL

    -- 2. Dịch vụ cố định hàng ngày kèm theo phòng (booking_room_daily_services / SP2102)
    SELECT 
        brds.booking_room_id,
        s.code AS service_id,
        s.name AS service_name,
        COALESCE(brds.total_amount, brds.price * brds.quantity, 0) AS total_amount,
        COALESCE(brds.quantity, 1) AS quantity
    FROM booking_room_daily_services brds
    JOIN services s ON brds.service_id = s.id OR brds.service_id = s.code
    WHERE brds.service_date = p_date
      AND brds.quantity <> 0
      AND s.code <> 'RM'
      AND brds.deleted_at IS NULL;

    -- Kết quả trả về cho báo cáo
    SELECT 
        (@row_index := @row_index + 1) AS STT,
        CONCAT(@prefix, COALESCE(b.booking_code, CAST(b.id AS CHAR))) AS BookingCode,
        COALESCE(CONCAT(c.title, ' ', c.full_name), b.booking_name) AS Guest,
        r.room_number AS Room,
        DATE_FORMAT(br.arrival_date, '%d-%m-%Y') AS ArrivalDate,
        DATE_FORMAT(br.departure_date, '%d-%m-%Y') AS DepartureDate,
        ter.service_id AS ServiceId,
        ter.service_name AS ServiceName,
        ter.total_amount AS RateTotal,
        COALESCE(comp.company_name, '') AS Company,
        COALESCE(b.notes, br.notes, '') AS NoteBooking,
        br.adults AS Adult,
        br.children AS Child
    FROM booking_rooms br
    INNER JOIN temp_expected_revenue ter ON br.id = ter.booking_room_id
    INNER JOIN bookings b ON br.booking_id = b.id
    LEFT JOIN rooms r ON br.room_id = r.id
    LEFT JOIN customers c ON b.customer_id = c.id
    LEFT JOIN companies comp ON b.company_id = comp.id
    WHERE p_date >= br.arrival_date AND p_date < br.departure_date
      AND br.status IN (0, 1)
      AND br.deleted_at IS NULL
    ORDER BY 
        CASE WHEN ter.service_id = 'RM' THEN 0 ELSE 1 END ASC,
        r.room_number ASC,
        ter.service_id ASC;

    DROP TEMPORARY TABLE IF EXISTS temp_expected_revenue;
END$$

DELIMITER ;
```

---

## 6. Bảng Đối Chiếu Trường Dữ Liệu (Field Mapping Table)

| Tên Cột Báo Cáo | Cột Legacy (`vw_031` / `sp_095`) | Bảng & Cột Mới (Laravel / MySQL) | Ý Nghĩa / Ghi Chú |
|---|---|---|---|
| `STT` | Biến đếm số thứ tự | `@row_index := @row_index + 1` | Số thứ tự tăng dần |
| `Mã BK` | `BookingId2` / `Prefix + vw.BookingId` | `bookings.booking_code` | Mã đăng ký đặt phòng |
| `Khách` | `vw.Guest` | `customers.full_name` / `bookings.booking_name` | Tên khách ở trong phòng |
| `Phòng` | `vw.Room` | `rooms.room_number` | Số phòng lưu trú |
| `Ngày Đến` | `vw.ArrivalDate` | `booking_rooms.arrival_date` | Ngày check-in phòng |
| `Ngày Đi` | `vw.DepartureDate` | `booking_rooms.departure_date` | Ngày check-out phòng |
| `Dịch Vụ` | `lst.ServiceId` | `services.code` (`RM`, `MB`, `BK`...) | Mã dịch vụ phát sinh |
| `Mô Tả Dịch Vụ` | `dv.Service` | `services.name` | Tên dịch vụ hiển thị |
| `Tổng` | `lst.Total` (`RateTotal`) | `booking_room_rates.rate` hoặc `daily_services` | Tiền dự kiến post trong đêm audit |
| `Công Ty` | `Company` | `companies.company_name` | Tên công ty / lữ hành |
| `Ghi Chú` | `vw.NoteBooking` | `bookings.notes` | Ghi chú đặt phòng |
