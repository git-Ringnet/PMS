# TÀI LIỆU ĐẶC TẢ CHI TIẾT - DÒNG 154: BÁO CÁO HÓA ĐƠN DỊCH VỤ TỔNG HỢP

> **Dành cho Agent triển khai:** Tài liệu này đặc tả chi tiết 100% về nghiệp vụ, giao diện người dùng, cấu trúc Stored Procedure và cấu hình Form Designer cho Báo cáo hóa đơn dịch vụ tổng hợp (Dòng 154 trong DANH MỤC BÁO CÁO.xlsx, STT 8.0, Sheet 20 & Sheet 64).

---

## 1. THÔNG TIN ĐỊNH DANH BÁO CÁO

- **Tên báo cáo:** Báo cáo hóa đơn dịch vụ tổng hợp
- **Tên tiếng Anh:** Summary Service Invoices Report
- **Mã báo cáo (`report_code`):** `SUMMARY_SERVICE_INVOICES`
- **Mã Data Source:** `RPT_SUMMARY_SERVICE_INVOICES`
- **Mã Template tham chiếu:** `SUMMARY_SERVICE_INVOICES_REFERENCE`
- **Menu điều hướng:** `BÁO CÁO` -> `BÁO CÁO THỐNG KÊ` -> `BÁO CÁO HÓA ĐƠN DỊCH VỤ TỔNG HỢP`
- **Store gốc chỉ định:** `ProVistaNavyHotel.dbo.sp_025` (Lưu tại [.codex/docs/doc_baocao/sql/ProVistaNavyHotel_sp_025.sql](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/sql/ProVistaNavyHotel_sp_025.sql))
- **Ảnh UI mẫu thực tế:**
  - [.codex/docs/doc_baocao/images/dong_154_ui_mau.png](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/images/dong_154_ui_mau.png) (Sheet 20)
  - [.codex/docs/doc_baocao/images/sheet_64_img_1.png](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/images/sheet_64_img_1.png) (Sheet 64)
  - [.codex/docs/doc_baocao/images/sheet_0_img_2.png](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/images/sheet_0_img_2.png) (Bảng cấu hình dịch vụ `SP1610`)

---

## 2. PHÂN TÍCH YÊU CẦU ĐẶC THÙ & NGUYÊN TẮC THIẾT KẾ

### 2.1. Tuân Thủ Nghiêm Ngặt Chỉ Đạo "Bỏ Qua Galliot, Làm Theo Navy Trước"
- **Ghi chú trong Excel Row 154:**
  > *"Lưu ý đơn vị galliot và navy cách group doanh thu khác nhau => làm tới nhắn Vy gửi lại store - Làm theo navy trước, galliot làm sau báo V gửi store hoặc DB.*
  > *Galliot group doanh thu theo outlet ở bảng sp3000.*
  > *Navy chỉnh lại store , doanh thu nhóm theo dịch vụ ở bảng sp1610 , report nightauditreport.*
  > *- Tại phần chọn dịch vụ trên báo cáo cho phép check chọn 1 lúc các nhiều dv để xem"*
- **Chỉ đạo của người dùng:** *"chưa có store galliot nên bỏ qua galliot"*.
- **Quy tắc triển khai:**
  1. Bỏ qua hoàn toàn logic group theo Outlet `SP3000` của Galliot.
  2. Triển khai chuẩn xác theo cách phân nhóm dịch vụ của Navy: Ánh xạ mã dịch vụ (`ServiceId`) sang các nhóm doanh thu lớn theo bảng chuẩn `SP1610` (`NightAuditReport`):
     - `RoomRevenue` (Tiền phòng): `BC, BD, BF, EB, EI, EP, ER, LO, RM, TB, DN, GN, HN, HT, LH, MR, MS, NB, TO, WS` -> Hiển thị tên nhóm: **"Doanh Thu Phòng"**
     - `FBRevenue` (Ăn uống): `FB, OT, RB, RF` -> Hiển thị tên nhóm: **"Doanh Thu Nhà Hàng"**
     - `MinibarRevenue` (Minibar): `MB` -> Hiển thị tên nhóm: **"Doanh Thu Minibar"**
     - `LaundryRevenue` (Giặt ủi): `LA` -> Hiển thị tên nhóm: **"Doanh Thu Giặt Là"**
     - `TransportationRevenue` (Đưa đón): `PU, DO` -> Hiển thị tên nhóm: **"Doanh Thu Vận Chuyển"**
     - `OtherRevenue` (Khác): Các dịch vụ còn lại -> Hiển thị tên nhóm: **"Doanh Thu Dịch Vụ"**
  3. Bộ lọc dịch vụ trên UI: Cho phép chọn cùng lúc nhiều dịch vụ (`multi-select` qua danh sách checkbox).
  4. Sửa lỗi cú pháp trong store gốc của Navy: Tại dòng 162 của `sp_025`, store cũ có lỗi typo `cast(vw.Date as Date)e =` khiến truy vấn lỗi cú pháp khi `@ShowDepDate = 0`. Trên MySQL 8.0 được chuẩn hóa thành `DATE(f.date) = v_from`.

---

## 3. THIẾT KẾ GIAO DIỆN & BỘ LỌC (UI SPECIFICATION)

### 3.1. Bảng Tham Số Bộ Lọc (`parameter_ui_schema`)
Bộ lọc nằm ở Left Panel của trang xem báo cáo:

| Tên tham số | Mã tham số | Kiểu dữ liệu | Mặc định | Tùy chọn / Ràng buộc |
|---|---|---|---|---|
| **Ngày** | `p_date_range` | Date Range | Hôm nay (`$today`) | Chọn dải ngày (`FromDate` ~ `ToDate`) hoặc ngày đơn |
| **Bộ phận** | `p_department` | Select / Dropdown | Tất cả (`''`) | Lấy danh mục phòng ban: FO, FB, HK, ENG, SPA... |
| **Dịch vụ** | `p_services` | Multi-select Checkbox | Rỗng (Tất cả) | Danh sách dịch vụ: RM, FB, MB, LA, PU, DO... Chọn nhiều dịch vụ |
| **Người dùng** | `p_user` | Select / Dropdown | Tất cả (`''`) | Danh sách nhân viên thu ngân / lễ tân |
| **Sắp xếp theo** | `p_order_by` | Select & Direction | `Ma` ASC | Tiêu chí sắp xếp: Mã, Ngày, Số phòng... và Hướng: ASC / DESC |
| **Xem HĐ đã xóa** | `p_show_deleted` | Toggle Switch | `false` | Bật/tắt hiển thị các bill/hóa đơn đã hủy/xóa |
| **Nhóm theo Outlet & Dịch vụ** | `p_group_by_service` | Toggle Switch | `true` (BẬT) | Gom nhóm cấp 1 theo Loại Doanh Thu, cấp 2 theo Mã Dịch Vụ |
| **Nhóm theo ngày** | `p_group_by_date` | Toggle Switch | `false` (TẮT) | Bổ sung cấp gom nhóm theo từng Ngày giao dịch |

### 3.2. Cấu Trúc Bố Cục Trang In (Report Layout)
- **Khổ giấy:** A4 Landscape (khổ ngang).
- **Header Band (30% / 70%):**
  - Khối trái: Logo khách sạn.
  - Khối phải: Thông tin địa chỉ khách sạn (`Địa Chỉ: ...`), Nhân viên in (`Người Dùng: Admin`), Ngày giờ in (`Ngày: ...`).
  - Tiêu đề chính giữa: **BÁO CÁO HÓA ĐƠN DỊCH VỤ TỔNG HỢP** (font size 18px, bold, in hoa).
  - Dòng ngày báo cáo: `Ngày: dd/mm/yyyy ~ dd/mm/yyyy` (in nghiêng, căn giữa).

### 3.3. Ma Trận Cột Dữ Liệu Bảng Chi Tiết (10 Cột)

| Cột | Tiêu đề | Field | Căn lề | Độ rộng | Format / Ghi chú |
|:---:|---|---|:---:|:---:|---|
| 1 | **Mã ĐK** | `BookingCode` | Trái | 70px | Màu xanh lá (`#16a34a`), in đậm, có prefix (VD: `BK_180`, `262`) |
| 2 | **Phòng** | `RoomNumber` | Giữa | 60px | Số phòng lưu trú (VD: `1105`, `1005`, `804`) |
| 3 | **Ngày Đến** | `ArrivalDate` | Giữa | 85px | Định dạng `dd/mm/yyyy` |
| 4 | **Ngày Đi** | `DepartureDate` | Giữa | 85px | Định dạng `dd/mm/yyyy` |
| 5 | **Tên Khách** | `GuestName` | Trái | 160px | Tên khách hàng hoặc tên đoàn / tên đặt phòng |
| 6 | **Mô Tả** | `Description` | Trái | 190px | Diễn giải dịch vụ (VD: `Minibar/Phí Minibar (R_1105=>BK_180)`) |
| 7 | **Doanh Thu** | `Amount` | Phải | 100px | Định dạng số tiền có dấu phẩy phân tách nghìn |
| 8 | **HTTT** | `PaymentMethod` | Giữa | 55px | Mã hình thức thanh toán (VD: `CA`, `AC`, `CD`, `CL`) |
| 9 | **Công Ty** | `CompanyName` | Trái | 130px | Tên công ty/đại lý (VD: `BINH ĐOÀN 15`, `NKQN-C.Hường`, `Expedia`) |
| 10 | **Giờ** | `OpenTime` | Giữa | 55px | Giờ phát sinh hóa đơn `HH:mm` |
| 11 | **Ghi chú** | `Note` | Trái | 120px | Diễn giải thanh toán (VD: `City ledger/Công nợ`) |

### 3.4. Cấu Trúc Nhóm & Hàng Tổng (Grouping & Totals)
- **Nhóm cấp 1 (Outlet / Nhóm doanh thu lớn):**
  - Header cell: `font-weight: bold; background-color: #f1f5f9;`
  - Nội dung: `Doanh Thu Nhà Hàng`, `Doanh Thu Minibar`, `Doanh Thu Phòng`, `Doanh Thu Dịch Vụ`...
- **Nhóm cấp 2 (Mã và tên dịch vụ):**
  - Header cell: `font-weight: bold; padding-left: 15px;`
  - Nội dung: `Dịch Vụ: FB - Food and Beverage/ Dịch Vụ Ăn Uống`, `Dịch Vụ: MB - Minibar/Phí Minibar`...
  - Dòng tổng phụ nhóm (Subtotal):
    - Ô nhãn: Cột 6 (`Mô Tả`): `Tổng:`
    - Ô tiền: Cột 7 (`Doanh Thu`): `{{group.sum.Amount|number}}`
- **Dòng tổng cộng toàn bảng (Grand Total):**
  - Cột 6 (`Mô Tả`): `Tổng:`
  - Cột 7 (`Doanh Thu`): `{{aggregate.rows.sum.Amount|number}}`

### 3.5. Bảng Thống Kê Tổng Hợp (Summary Box)
Nằm ngay dưới bảng chi tiết:
- Gồm 2 cột: `Doanh Thu` (Căn trái, 70% width) | `Tổng` (Căn phải, 30% width).
- Các dòng thống kê tự động:
  1. `Doanh Thu Nhà Hàng`: `<Tổng tiền F&B>`
  2. `Doanh Thu Minibar`: `<Tổng tiền Minibar>`
  3. `Doanh Thu Phòng`: `<Tổng tiền Phòng>`
  4. `Doanh Thu Dịch Vụ`: `<Tổng tiền dịch vụ khác>`
  5. `Tổng`: `<Tổng cộng doanh thu toàn khách sạn>`

---

## 4. THIẾT KẾ STORED PROCEDURE MYSQL 8.0 (`rpt_summary_service_invoices`)

```sql
DELIMITER $$
DROP PROCEDURE IF EXISTS `rpt_summary_service_invoices`$$
CREATE PROCEDURE `rpt_summary_service_invoices`(
    IN `p_from_date` VARCHAR(10),
    IN `p_to_date` VARCHAR(10),
    IN `p_department` VARCHAR(20),
    IN `p_services` TEXT,             -- Danh sách mã dịch vụ ngăn cách bằng dấu phẩy: 'FB,MB,RM'
    IN `p_user` VARCHAR(50),
    IN `p_order_by` VARCHAR(20),      -- 'Ma', 'Date', 'Room'
    IN `p_show_deleted` INT,          -- 0: Không xem, 1: Xem hóa đơn xóa
    IN `p_group_by_service` INT,      -- 1: Nhóm theo outlet/dịch vụ
    IN `p_group_by_date` INT          -- 1: Nhóm theo ngày
)
BEGIN
    DECLARE v_from DATE;
    DECLARE v_to DATE;
    SET v_from = STR_TO_DATE(p_from_date, '%Y-%m-%d');
    SET v_to = STR_TO_DATE(p_to_date, '%Y-%m-%d');

    -- Bảng kết quả chi tiết
    SELECT 
        ROW_NUMBER() OVER (ORDER BY sb.service_date, sb.id) AS Stt,
        COALESCE(b.booking_code, CONCAT('BK-', b.id)) AS BookingCode,
        COALESCE(br.room_number, r.room_number, '') AS RoomNumber,
        DATE_FORMAT(b.arrival_date, '%d/%m/%Y') AS ArrivalDate,
        DATE_FORMAT(b.departure_date, '%d/%m/%Y') AS DepartureDate,
        COALESCE(b.guest_name, b.booking_name, '') AS GuestName,
        COALESCE(sb.description, s.name, '') AS Description,
        sb.amount AS Amount,
        COALESCE(p.payment_method, sb.payment_method, 'AC') AS PaymentMethod,
        COALESCE(c.name, 'KHÁCH LẺ') AS CompanyName,
        DATE_FORMAT(sb.created_at, '%H:%i') AS OpenTime,
        COALESCE(sb.note, pm.name, '') AS Note,
        sb.service_id AS ServiceCode,
        COALESCE(s.name, sb.service_id) AS ServiceName,
        
        -- Phân nhóm Doanh thu theo chuẩn Navy (SP1610 / NightAuditReport)
        CASE 
            WHEN sb.service_id IN ('FB', 'OT', 'RB', 'RF', 'BD', 'BF') THEN 'Doanh Thu Nhà Hàng'
            WHEN sb.service_id = 'MB' THEN 'Doanh Thu Minibar'
            WHEN sb.service_id IN ('RM', 'EB', 'ER', 'LO', 'TB', 'DN', 'GN', 'HN', 'HT', 'LH', 'MR', 'MS', 'NB', 'TO', 'WS') THEN 'Doanh Thu Phòng'
            WHEN sb.service_id = 'LA' THEN 'Doanh Thu Giặt Là'
            WHEN sb.service_id IN ('PU', 'DO') THEN 'Doanh Thu Vận Chuyển'
            ELSE 'Doanh Thu Dịch Vụ'
        END AS RevenueGroupName,

        CONCAT('Dịch Vụ: ', sb.service_id, ' - ', COALESCE(s.name, sb.service_id)) AS ServiceGroupHeader,
        DATE_FORMAT(sb.service_date, '%d/%m/%Y') AS DateGroupHeader
    FROM service_bills sb
    LEFT JOIN bookings b ON sb.booking_id = b.id
    LEFT JOIN booking_rooms br ON sb.booking_room_id = br.id
    LEFT JOIN rooms r ON br.room_id = r.id
    LEFT JOIN services s ON sb.service_id = s.code
    LEFT JOIN companies c ON b.company_id = c.id
    LEFT JOIN payments p ON sb.payment_id = p.id
    LEFT JOIN payment_methods pm ON p.payment_method = pm.code
    WHERE sb.service_date BETWEEN v_from AND v_to
      AND (p_show_deleted = 1 OR sb.deleted_at IS NULL)
      AND (p_department IS NULL OR p_department = '' OR s.department_code = p_department)
      AND (p_services IS NULL OR p_services = '' OR FIND_IN_SET(sb.service_id, p_services) > 0)
      AND (p_user IS NULL OR p_user = '' OR sb.created_by = p_user)
    ORDER BY 
        CASE WHEN p_group_by_date = 1 THEN sb.service_date END,
        RevenueGroupName,
        sb.service_id,
        sb.created_at;

    -- Bảng kết quả chính (Chi tiết các dịch vụ)
    -- LƯU Ý KIẾN TRÚC: Executor chỉ nhận Result Set đầu tiên từ Stored Procedure.
    -- Bảng phụ Summary Box (Doanh Thu | Tổng) sẽ được sinh tự động thông qua Adapter
    -- SummaryServiceInvoicesDataAdapter.php đăng ký tại ReportDatasetEnricher.
    -- Vì vậy procedure chỉ cần SELECT đúng 1 result set duy nhất để đảm bảo an toàn tuyệt đối.
END$$
DELIMITER ;
```

---

## 4.2. MÃ NGUỒN ADAPTER DẪN XUẤT BẢNG PHỤ (`SummaryServiceInvoicesDataAdapter.php`)

> **Quy tắc hệ thống PMS:** `ReportDataExecutorService` chỉ tiêu thụ result set đầu tiên từ Stored Procedure. Bảng phụ tóm tắt 2 cột (`Doanh Thu` | `Tổng`) đặt dưới báo cáo được sinh ra từ chính tập dữ liệu `rows` thông qua Adapter:

```php
<?php

namespace App\Services\Reports;

use InvalidArgumentException;

/**
 * Adapter cho Báo cáo hóa đơn dịch vụ tổng hợp (SUMMARY_SERVICE_INVOICES).
 * Tự động tính toán bảng phụ thống kê tổng hợp (summary box) từ tập rows chi tiết.
 */
final class SummaryServiceInvoicesDataAdapter
{
    public function adapt(array $result): array
    {
        $rows = array_values($result['rows'] ?? []);
        $groupSums = [
            'Doanh Thu Nhà Hàng' => 0.0,
            'Doanh Thu Minibar' => 0.0,
            'Doanh Thu Phòng' => 0.0,
            'Doanh Thu Giặt Là' => 0.0,
            'Doanh Thu Vận Chuyển' => 0.0,
            'Doanh Thu Dịch Vụ' => 0.0,
        ];

        $total = 0.0;
        foreach ($rows as $row) {
            $rowArr = (array) $row;
            $groupName = (string) ($rowArr['RevenueGroupName'] ?? 'Doanh Thu Dịch Vụ');
            $amount = (float) ($rowArr['Amount'] ?? 0);
            
            if (! isset($groupSums[$groupName])) {
                $groupSums[$groupName] = 0.0;
            }
            $groupSums[$groupName] += $amount;
            $total += $amount;
        }

        // Dựng danh sách cho bảng static-table
        $summaryRows = [];
        foreach ($groupSums as $name => $sum) {
            if ($sum > 0) {
                $summaryRows[] = [
                    'DoanhThu' => $name,
                    'Tong' => number_format($sum, 0, '.', ','),
                ];
            }
        }
        $summaryRows[] = [
            'DoanhThu' => 'Tổng',
            'Tong' => number_format($total, 0, '.', ','),
        ];

        $result['summary'] = $summaryRows;
        return $result;
    }
}
```

### 4.3. Đăng Ký Trong `ReportDatasetEnricher.php`
Khi triển khai, Agent thêm 1 dòng vào `backend/app/Services/Reports/ReportDatasetEnricher.php`:
```php
'SUMMARY_SERVICE_INVOICES', 'RPT_SUMMARY_SERVICE_INVOICES' => app(\App\Services\Reports\SummaryServiceInvoicesDataAdapter::class)->adapt($result),
```
*(Lưu ý: Báo cáo việc sửa file dùng chung `ReportDatasetEnricher.php` theo đúng rule).*


---

## 5. MÃ NGUỒN TEMPLATE REFERENCE PHP (`summary_service_invoices_reference.php`)

```php
<?php

namespace Database\ReportTemplates;

class SummaryServiceInvoicesReference
{
    public static function definition(): array
    {
        return [
            'code' => 'SUMMARY_SERVICE_INVOICES',
            'name' => 'Báo cáo hóa đơn dịch vụ tổng hợp',
            'category' => 'Báo cáo thống kê',
            'layout' => 'A4_LANDSCAPE',
            'data_source_code' => 'RPT_SUMMARY_SERVICE_INVOICES',
            'template_code' => 'SUMMARY_SERVICE_INVOICES_REFERENCE',
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
                                . "<div><strong>Địa Chỉ:</strong> {{hotel.address}}</div>"
                                . "<div><strong>Người Dùng:</strong> {{auth.user_name}}</div>"
                                . "<div><strong>Ngày:</strong> {{system.now|date('d/m/Y')}}</div>"
                                . "</div>",
                        ],
                    ],
                ],
                [
                    'id' => 'report_title',
                    'type' => 'text',
                    'content' => "<h2 style='text-align: center; margin: 15px 0 5px 0; font-size: 18px; font-weight: bold; text-transform: uppercase;'>BÁO CÁO HÓA ĐƠN DỊCH VỤ TỔNG HỢP</h2>"
                        . "<div style='text-align: center; font-size: 12px; font-style: italic;'>Ngày: {{parameters.p_from_date|date('d/m/Y')}} ~ {{parameters.p_to_date|date('d/m/Y')}}</div>",
                ],
                [
                    'id' => 'main_table',
                    'type' => 'table',
                    'dataset' => 'details',
                    'grouping' => [
                        [
                            'field' => 'RevenueGroupName',
                            'headerCell' => [
                                'content' => "<strong>{{group.value}}</strong>",
                                'style' => ['backgroundColor' => '#f1f5f9', 'fontSize' => '11px', 'padding' => '6px', 'fontWeight' => 'bold'],
                            ],
                        ],
                        [
                            'field' => 'ServiceGroupHeader',
                            'headerCell' => [
                                'content' => "<strong>{{group.value}}</strong>",
                                'style' => ['backgroundColor' => '#ffffff', 'fontSize' => '10.5px', 'padding' => '4px 15px', 'fontWeight' => 'bold'],
                            ],
                            'subtotalRow' => [
                                'cells' => [
                                    ['colSpan' => 6, 'content' => 'Tổng:', 'style' => ['textAlign' => 'right', 'fontWeight' => 'bold']],
                                    ['content' => '{{group.sum.Amount|number}}', 'style' => ['textAlign' => 'right', 'fontWeight' => 'bold']],
                                    ['colSpan' => 4, 'content' => ''],
                                ],
                            ],
                        ],
                    ],
                    'columns' => [
                        ['field' => 'BookingCode', 'title' => 'Mã ĐK', 'width' => '70px', 'align' => 'left', 'cellStyle' => ['color' => '#16a34a', 'fontWeight' => 'bold']],
                        ['field' => 'RoomNumber', 'title' => 'Phòng', 'width' => '60px', 'align' => 'center'],
                        ['field' => 'ArrivalDate', 'title' => 'Ngày Đến', 'width' => '85px', 'align' => 'center'],
                        ['field' => 'DepartureDate', 'title' => 'Ngày Đi', 'width' => '85px', 'align' => 'center'],
                        ['field' => 'GuestName', 'title' => 'Tên Khách', 'width' => '160px', 'align' => 'left'],
                        ['field' => 'Description', 'title' => 'Mô Tả', 'width' => '190px', 'align' => 'left'],
                        ['field' => 'Amount', 'title' => 'Doanh Thu', 'width' => '100px', 'align' => 'right', 'format' => 'number'],
                        ['field' => 'PaymentMethod', 'title' => 'HTTT', 'width' => '55px', 'align' => 'center'],
                        ['field' => 'CompanyName', 'title' => 'Công Ty', 'width' => '130px', 'align' => 'left'],
                        ['field' => 'OpenTime', 'title' => 'Giờ', 'width' => '55px', 'align' => 'center'],
                        ['field' => 'Note', 'title' => 'Ghi chú', 'width' => '120px', 'align' => 'left'],
                    ],
                    'customRows' => [
                        [
                            'scope' => 'table',
                            'position' => 'footer',
                            'cells' => [
                                ['colSpan' => 6, 'content' => 'Tổng:', 'style' => ['textAlign' => 'right', 'fontWeight' => 'bold', 'fontSize' => '11px']],
                                ['content' => '{{aggregate.rows.sum.Amount|number}}', 'style' => ['textAlign' => 'right', 'fontWeight' => 'bold', 'fontSize' => '11px']],
                                ['colSpan' => 4, 'content' => ''],
                            ],
                        ],
                    ],
                ],
                [
                    'id' => 'summary_table',
                    'type' => 'static-table',
                    'style' => ['width' => '350px', 'margin' => '20px auto 0 auto', 'borderCollapse' => 'collapse'],
                    'headers' => [
                        ['title' => 'Doanh Thu', 'style' => ['backgroundColor' => '#dee2ed', 'fontWeight' => 'bold', 'textAlign' => 'center', 'padding' => '5px']],
                        ['title' => 'Tổng', 'style' => ['backgroundColor' => '#dee2ed', 'fontWeight' => 'bold', 'textAlign' => 'center', 'padding' => '5px', 'width' => '120px']],
                    ],
                    'rows' => '{{dataset.summary}}',
                ],
            ],
        ];
    }
}
```
## 6. Trạng thái triển khai

- Đã đăng ký `SUMMARY_SERVICE_INVOICES`, `RPT_SUMMARY_SERVICE_INVOICES`, `rpt_summary_service_invoices` và template reference tương ứng.
- Contract runtime dùng 11 cột; bảng tổng hợp dưới bảng chi tiết được tạo bởi `SummaryServiceInvoicesDataAdapter` trong dataset `service_summary`.
- Nhóm doanh thu dùng rule cố định đã ghi tại `.codex/docs/hardcoded/report_revenue_legacy_rules.md`.
