# Đặc Tả Kỹ Thuật: Báo Cáo Doanh Thu Hai Giai Đoạn - Dòng 161

> **Tài liệu chuẩn bị cho Agent triển khai tiếp theo**  
> Dựa trên phân tích từ file Excel `DANH MỤC BÁO CÁO.xlsx` (Dòng 161, Sheet 4 `Báo cáo doanh thu hai giai đoạn`), ảnh giao diện thực tế `[.codex/docs/doc_baocao/images/dong_161_ui_mau.png](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/images/dong_161_ui_mau.png)` và Stored Procedure gốc trên MS SQL Server `ProVistaArmyHotel.dbo.sp_217` ([ProVistaArmyHotel_sp_217.sql](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/sql/ProVistaArmyHotel_sp_217.sql)).

---

## 1. Thông Tin Chung & Định Danh

* **Tên báo cáo tiếng Việt**: Báo cáo doanh thu hai giai đoạn
* **Mã báo cáo (`code`)**: `TWO_PERIOD_REVENUE`
* **Mã nguồn dữ liệu (`data_source_code`)**: `RPT_TWO_PERIOD_REVENUE`
* **Mã template (`template_code`)**: `TWO_PERIOD_REVENUE_REFERENCE`
* **Nhóm báo cáo (`group`)**: `Báo cáo doanh thu`
* **Menu hiển thị**: `['frontdesk', 'cashier', 'report']`
* **Vị trí trong Excel danh mục**: Dòng 161 (STT 16)
* **Sheet tham chiếu trong Excel**: Sheet 4 (`Báo cáo doanh thu hai giai đoạn`)
* **Lưu ý đặc biệt trong Excel & Nghiệp vụ**:
  * Đọc đúng Store của Army: `ProVistaArmyHotel.dbo.sp_217` (các bên khác chưa update store fix lỗi).
  * **Bản chất nghiệp vụ cốt lõi**: Báo cáo hiển thị những dịch vụ phát sinh ở 1 tháng nhưng lại được thanh toán ở tháng khác.  
    *Ví dụ thực tế*: Khách lưu trú từ ngày 25/08 out ngày 05/09. Khi người dùng xem báo cáo từ ngày 01/09 đến 30/09, hệ thống sẽ lọc ra các hóa đơn dịch vụ được thanh toán trong tháng 9, nhưng dịch vụ đó thực tế đã được post (phát sinh) ở các tháng trước (tháng 8 trở về trước).
  * **2 Điểm nâng cấp bắt buộc**:
    1. **Bổ sung cột "Ngày dịch vụ"** (`DateHDDV`) nằm ngay sau cột "Tên khách" (lấy dữ liệu cột `date` tại bảng `service_bills` / `SP3000`).
    2. **Bộ lọc dịch vụ cho phép chọn nhiều cùng lúc** (`multi-select`): Store cũ chỉ cho phép chọn 1 dịch vụ đơn lẻ hoặc rỗng để xem tất cả. Hệ thống mới phải cho phép tick chọn đồng thời nhiều dịch vụ.
* **Ảnh chụp màn hình thực tế**: `[.codex/docs/doc_baocao/images/dong_161_ui_mau.png](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/images/dong_161_ui_mau.png)`
* **File SQL legacy tham chiếu**: [ProVistaArmyHotel_sp_217.sql](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/sql/ProVistaArmyHotel_sp_217.sql)

---

## 2. Phân Tích Giao Diện & Bộ Lọc (`dong_161_ui_mau.png`)

### 2.1. Panel Bộ Lọc Bên Trái (Left Filter Panel)
* **Chiều rộng panel**: ~ 260px - 280px.
* **Các trường điều khiển**:
  1. **Ngày (`p_date_range`)**: Date Range Picker. Mặc định: `Tháng này` (`01/MM/yyyy ~ LastDay/MM/yyyy`).
  2. **Bộ phận (`p_department`)**: Dropdown Select (`FO`, `FB`, `HKS`, `SPA`, v.v.). Mặc định: rỗng (Tất cả).
  3. **Outlet (`p_outlet`)**: Dropdown Select (`FB`, `LA`, `RC`, v.v.). Mặc định: rỗng (Tất cả).
  4. **Dịch vụ (`p_services`)**: **Multi-select Dropdown** (Check chọn 1 lúc nhiều dịch vụ; ví dụ: Giặt ủi, Ăn sáng, Nhà hàng...).
  5. **Chọn người dùng (`p_user`)**: Dropdown Select thu ngân / lễ tân (`Select Value`).
  6. **Sắp xếp theo (`p_order_by`)**: Dropdown chọn trường sắp xếp (`Ma`, `Date`, `InvoiceId`, `Room`) + Thứ tự `ASC` / `DESC`.
  7. **Toggles / Switches**:
     * `Nhóm theo ngày`: Boolean toggle (mặc định `false`).
     * `Đăng ký`: Boolean toggle (mặc định `false`).
  8. **Nút bấm**: `Hiển thị báo cáo` (Màu xanh dương `#3b82f6`).

### 2.2. Bố Cục Trang Báo Cáo (A4 Ngang - Landscape)
1. **Khối Header**:
   * Tiêu đề chính giữa: **BÁO CÁO DOANH THU HAI GIAI ĐOẠN** (Font đậm, cỡ 18pt, căn giữa).
   * Dòng điều kiện lọc: `Ngày: 01/09/2026 ~ 30/09/2026` (Căn giữa/phải).
2. **Khối Bảng Dữ Liệu Chi Tiết**:
   * Gồm 17 cột tiêu đề:
     1. `STT`: Số thứ tự dòng tăng dần.
     2. `Mã HĐ`: Mã hóa đơn bán lẻ dịch vụ (`SP3000.Ma` / `service_bills.code`).
     3. `Mã ĐK`: Mã đăng ký booking (`SP2000.Ma` hoặc `Prefix + BookingId`).
     4. `Ngày Đến`: Ngày check-in (`ArrivalDateVW` - dd/MM/yyyy).
     5. `Ngày Đi`: Ngày check-out (`DepartureDateVW` - dd/MM/yyyy).
     6. `Tên Khách`: Họ tên khách hàng (`Guest`).
     7. **`Ngày Dịch Vụ`**: **Cột nâng cấp mới** (`DateHDDV` - dd/MM/yyyy - Ngày post dịch vụ thực tế).
     8. `Mô Tả`: Diễn giải chi tiết dịch vụ (`DescriptionServive`).
     9. `Giá Gốc`: Doanh thu trước phí dịch vụ và thuế (`OriginalRate`).
     10. `Phí Dịch Vụ`: Tiền phí dịch vụ (`ServiceChargeAmount`).
     11. `Thuế Đặc Biệt`: Tiền thuế tiêu thụ đặc biệt nếu có (`SpecialTaxAmount`).
     12. `Thuế`: Tiền thuế VAT (`TaxAmount`).
     13. `Doanh Thu`: Tổng tiền thanh toán đã bao gồm phí và thuế (`Amount` / `TotalAmount0`).
     14. `Hình Thức Thanh Toán`: Mã HTTT (`PaymentMethod` như `CD`, `BT`, `CA`, `CK`).
     15. `Công Ty`: Tên đối tác, công ty lữ hành hoặc `Khách lẻ` (`Company`).
     16. `Người Dùng`: Username thực hiện giao dịch (`Username`).
     17. `Giờ`: Giờ post dịch vụ (`OpenTime` - `HH:mm`).
3. **Cơ Chế Phân Nhóm (Grouping)**:
   * **Level 1**: Gom theo `Outlet` (ví dụ: `Outlet: FB`, `Outlet: LA`, `Outlet: RC`).
   * **Level 2**: Gom theo `Dịch Vụ`.
   * **Dòng Subtotal**:
     * `Tổng:` | [Tổng Giá Gốc] | [Tổng Phí DV] | [Tổng Thuế ĐB] | [Tổng Thuế] | [Tổng Doanh Thu].
   * **Dòng Grand Total**:
     * `Tổng Cộng:` | [Grand Giá Gốc] | [Grand Phí DV] | [Grand Thuế ĐB] | [Grand Thuế] | [Grand Doanh Thu].

---

## 3. Cấu Trúc Toàn Diện 100% Của `content_json` (Form Designer)

> **Dành cho Agent triển khai:**  
> File PHP dưới đây chứa toàn bộ định nghĩa metadata, cấu trúc khối `blocks`, cột `columns`, dòng tổng cộng `customRows`, template HTML và CSS theo chuẩn của `TemplateRendererService`. Hãy đặt tại:  
> `backend/database/report_templates/two_period_revenue_reference.php`.

```php
<?php

use App\Services\TemplateRendererService;

return new class
{
    public function definition(): array
    {
        return [
            'code' => 'TWO_PERIOD_REVENUE',
            'name' => 'Báo cáo doanh thu hai giai đoạn',
            'report' => 'TWO_PERIOD_REVENUE_REFERENCE',
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
                    'Ma' => 'string',
                    'RegisterID2' => 'string',
                    'ArrivalDateVW' => 'string',
                    'DepartureDateVW' => 'string',
                    'Guest' => 'string',
                    'DateHDDV' => 'string',
                    'DescriptionServive' => 'string',
                    'OriginalRate' => 'number',
                    'ServiceChargeAmount' => 'number',
                    'SpecialTaxAmount' => 'number',
                    'TaxAmount' => 'number',
                    'Amount' => 'number',
                    'PaymentMethod' => 'string',
                    'Company' => 'string',
                    'Username' => 'string',
                    'OpenTime' => 'string',
                    'Outlet' => 'string',
                    'OutletName' => 'string',
                    'ServiceId' => 'string',
                    'FirstNameService' => 'string',
                ],
                'parameters' => [
                    'p_from_date' => 'string',
                    'p_to_date' => 'string',
                    'p_department' => 'string',
                    'p_outlet' => 'string',
                    'p_services' => 'string',
                    'p_user' => 'string',
                    'p_order_by' => 'string',
                    'p_order_direction' => 'string',
                ],
            ],
        ];
    }

    public function columns(): array
    {
        return [
            ['key' => 'STT', 'title' => 'STT', 'width' => '40px', 'align' => 'center'],
            ['key' => 'Ma', 'title' => 'Mã HĐ', 'width' => '65px', 'align' => 'center'],
            ['key' => 'RegisterID2', 'title' => 'Mã ĐK', 'width' => '70px', 'align' => 'center'],
            ['key' => 'ArrivalDateVW', 'title' => 'Ngày Đến', 'width' => '80px', 'align' => 'center'],
            ['key' => 'DepartureDateVW', 'title' => 'Ngày Đi', 'width' => '80px', 'align' => 'center'],
            ['key' => 'Guest', 'title' => 'Tên Khách', 'width' => '130px', 'align' => 'left'],
            ['key' => 'DateHDDV', 'title' => 'Ngày Dịch Vụ', 'width' => '85px', 'align' => 'center'],
            ['key' => 'DescriptionServive', 'title' => 'Mô Tả', 'width' => '170px', 'align' => 'left'],
            ['key' => 'OriginalRate', 'title' => 'Giá Gốc', 'width' => '90px', 'align' => 'right', 'format' => '#,##0'],
            ['key' => 'ServiceChargeAmount', 'title' => 'Phí Dịch Vụ', 'width' => '80px', 'align' => 'right', 'format' => '#,##0'],
            ['key' => 'SpecialTaxAmount', 'title' => 'Thuế Đặc Biệt', 'width' => '75px', 'align' => 'right', 'format' => '#,##0'],
            ['key' => 'TaxAmount', 'title' => 'Thuế', 'width' => '75px', 'align' => 'right', 'format' => '#,##0'],
            ['key' => 'Amount', 'title' => 'Doanh Thu', 'width' => '95px', 'align' => 'right', 'format' => '#,##0'],
            ['key' => 'PaymentMethod', 'title' => 'Hình Thức Thanh Toán', 'width' => '65px', 'align' => 'center'],
            ['key' => 'Company', 'title' => 'Công Ty', 'width' => '100px', 'align' => 'left'],
            ['key' => 'Username', 'title' => 'Người Dùng', 'width' => '70px', 'align' => 'center'],
            ['key' => 'OpenTime', 'title' => 'Giờ', 'width' => '55px', 'align' => 'center'],
        ];
    }

    public function blocks(): array
    {
        return [
            'header' => [
                'type' => 'header',
                'title' => 'BÁO CÁO DOANH THU HAI GIAI ĐOẠN',
                'show_logo' => false,
                'meta_fields' => [
                    ['label' => 'Ngày', 'value' => '{p_from_date} ~ {p_to_date}'],
                ],
            ],
            'grid_details' => [
                'type' => 'grid',
                'data_source' => 'rows',
                'grouping' => [
                    'level1' => [
                        'field' => 'Outlet',
                        'label' => 'Outlet: {Outlet} - {OutletName}',
                        'show_header' => true,
                        'subtotal' => true,
                    ],
                    'level2' => [
                        'field' => 'FirstNameService',
                        'label' => 'Dịch Vụ: {FirstNameService}',
                        'show_header' => true,
                        'subtotal' => false,
                    ],
                ],
                'columns' => $this->columns(),
                'customRows' => [
                    [
                        'type' => 'subtotal_level1',
                        'label' => 'Tổng:',
                        'aggregate' => [
                            'OriginalRate' => 'sum',
                            'ServiceChargeAmount' => 'sum',
                            'SpecialTaxAmount' => 'sum',
                            'TaxAmount' => 'sum',
                            'Amount' => 'sum',
                        ],
                    ],
                    [
                        'type' => 'grand_total',
                        'label' => 'Tổng Cộng:',
                        'aggregate' => [
                            'OriginalRate' => 'sum',
                            'ServiceChargeAmount' => 'sum',
                            'SpecialTaxAmount' => 'sum',
                            'TaxAmount' => 'sum',
                            'Amount' => 'sum',
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
        <h1 class="report-title">BÁO CÁO DOANH THU HAI GIAI ĐOẠN</h1>
        <div class="report-date-meta">
            <strong>Ngày:</strong> {p_from_date} ~ {p_to_date}
        </div>
    </div>

    <table class="report-table main-grid">
        <thead>
            <tr>
                <th style="width: 40px;">STT</th>
                <th style="width: 65px;">Mã HĐ</th>
                <th style="width: 70px;">Mã ĐK</th>
                <th style="width: 80px;">Ngày Đến</th>
                <th style="width: 80px;">Ngày Đi</th>
                <th style="width: 130px;">Tên Khách</th>
                <th style="width: 85px;">Ngày Dịch Vụ</th>
                <th style="width: 170px;">Mô Tả</th>
                <th style="width: 90px;">Giá Gốc</th>
                <th style="width: 80px;">Phí Dịch Vụ</th>
                <th style="width: 75px;">Thuế Đặc Biệt</th>
                <th style="width: 75px;">Thuế</th>
                <th style="width: 95px;">Doanh Thu</th>
                <th style="width: 65px;">Hình Thức Thanh Toán</th>
                <th style="width: 100px;">Công Ty</th>
                <th style="width: 70px;">Người Dùng</th>
                <th style="width: 55px;">Giờ</th>
            </tr>
        </thead>
        <tbody>
            {#groups_level1}
            <tr class="group-header-l1">
                <td colspan="17" style="font-weight: bold; color: #1e3a8a;">
                    Outlet: {group_name_l1}
                </td>
            </tr>
            {#groups_level2}
            <tr class="group-header-l2">
                <td colspan="17" style="font-style: italic; color: #16a34a;">
                    Dịch Vụ
                </td>
            </tr>
            {#items}
            <tr>
                <td class="text-center">{STT}</td>
                <td class="text-center font-semibold">{Ma}</td>
                <td class="text-center text-primary font-bold">{RegisterID2}</td>
                <td class="text-center">{ArrivalDateVW}</td>
                <td class="text-center">{DepartureDateVW}</td>
                <td>{Guest}</td>
                <td class="text-center font-bold text-amber-700">{DateHDDV}</td>
                <td>{DescriptionServive}</td>
                <td class="text-right">{OriginalRate}</td>
                <td class="text-right">{ServiceChargeAmount}</td>
                <td class="text-right">{SpecialTaxAmount}</td>
                <td class="text-right">{TaxAmount}</td>
                <td class="text-right font-bold">{Amount}</td>
                <td class="text-center">{PaymentMethod}</td>
                <td>{Company}</td>
                <td class="text-center">{Username}</td>
                <td class="text-center">{OpenTime}</td>
            </tr>
            {/items}
            {/groups_level2}
            <tr class="subtotal-l1">
                <td colspan="8" class="text-right font-bold">Tổng:</td>
                <td class="text-right font-bold">{subtotal_l1_original_rate}</td>
                <td class="text-right font-bold">{subtotal_l1_service_charge}</td>
                <td class="text-right font-bold">{subtotal_l1_special_tax}</td>
                <td class="text-right font-bold">{subtotal_l1_tax}</td>
                <td class="text-right font-bold">{subtotal_l1_amount}</td>
                <td colspan="4"></td>
            </tr>
            {/groups_level1}
        </tbody>
        <tfoot>
            <tr class="grand-total">
                <td colspan="8" class="text-right font-bold">Tổng Cộng:</td>
                <td class="text-right font-bold">{grand_original_rate}</td>
                <td class="text-right font-bold">{grand_service_charge}</td>
                <td class="text-right font-bold">{grand_special_tax}</td>
                <td class="text-right font-bold">{grand_tax}</td>
                <td class="text-right font-bold">{grand_amount}</td>
                <td colspan="4"></td>
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
.report-header { text-align: center; margin-bottom: 15px; }
.report-title { font-size: 18pt; font-weight: bold; margin: 0 0 6px 0; text-transform: uppercase; }
.report-date-meta { font-size: 10.5pt; color: #374151; }
.report-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; font-size: 8.5pt; }
.report-table th, .report-table td { border: 1px solid #d1d5db; padding: 3px 5px; }
.report-table th { background-color: #f3f4f6; font-weight: bold; text-align: center; }
.group-header-l1 td { background-color: #eff6ff; font-size: 9.5pt; padding: 4px; }
.group-header-l2 td { background-color: #ffffff; padding: 3px; }
.subtotal-l1 td { background-color: #f3f4f6; font-size: 9pt; }
.grand-total td { background-color: #e5e7eb; font-size: 9.5pt; }
.text-center { text-align: center; }
.text-right { text-align: right; }
.font-bold { font-weight: bold; }
.text-primary { color: #1d4ed8; }
.text-amber-700 { color: #b45309; }
CSS;
    }
};
```

---

## 4. Bóc Tách Chi Tiết Logic SQL Legacy (Army Hotel `sp_217`)

### 4.1. Phân Tích Logic So Sánh Hai Giai Đoạn (Two Periods Comparison)
Trong store gốc `sp_217`, điều kiện cốt lõi xác định giao dịch thuộc 2 giai đoạn là:
```sql
and ((DATEPART(Month, vw.Date) <> DATEPART(Month, hd.Date))
  or ((DATEPART(Month, vw.Date) = DATEPART(Month, hd.Date)) 
      and (DATEPART(Year, vw.Date) <> DATEPART(Year, hd.Date))))
```
* `vw.Date`: Là ngày ghi nhận phát sinh dịch vụ (`Date` của bảng `SP3000` / `service_bills`).
* `hd.Date`: Là ngày xuất hóa đơn / thanh toán (`Date` của bảng `SP3003` / `invoices`).
* Điều kiện lọc khoảng thời gian theo kỳ thanh toán hóa đơn:
  `hd.Date between @FromDate and @ToDate` (khi `@ShowDepDate = 1`).
* Loại trừ các hình thức thanh toán miễn phí:
  `hd.Pack1 not in (select Ma from SP1326 where HTMienPhi = 1)`.

### 4.2. Công Thức Bóc Tách Doanh Thu, Phí & Thuế (Kế Thừa Từ `vw_044`)
Từ `vw_044.sql`, với tổng tiền bao gồm phí & thuế là `TotalAmount0`:
* **Giá gốc (`OriginalRate`)**:
  $$\text{OriginalRate} = \frac{\text{TotalAmount0}}{(1 + \text{Tax}) \times (1 + \text{SpecialTax}) \times (1 + \text{ServiceCharge})}$$
* **Phí dịch vụ (`ServiceChargeAmount`)**:
  $$\text{ServiceChargeAmount} = \text{OriginalRate} \times \text{ServiceCharge}$$
* **Thuế tiêu thụ đặc biệt (`SpecialTaxAmount`)**:
  $$\text{SpecialTaxAmount} = (\text{OriginalRate} + \text{ServiceChargeAmount}) \times \text{SpecialTax}$$
* **Thuế VAT (`TaxAmount`)**:
  $$\text{TaxAmount} = \text{TotalAmount0} - \text{OriginalRate} - \text{ServiceChargeAmount} - \text{SpecialTaxAmount}$$

---

## 5. Stored Procedure Chuẩn Hóa Trên MySQL 8.0

> Lưu tại: `backend/database/procedures/rpt_two_period_revenue.sql`.

```sql
DELIMITER $$

DROP PROCEDURE IF EXISTS `rpt_two_period_revenue`$$

CREATE PROCEDURE `rpt_two_period_revenue`(
    IN p_from_date DATE,
    IN p_to_date DATE,
    IN p_department VARCHAR(100),
    IN p_outlet VARCHAR(100),
    IN p_services TEXT,         -- Hỗ trợ đa chọn danh sách dịch vụ (comma-separated: 'RM,FB,LA')
    IN p_user VARCHAR(100),
    IN p_order_by VARCHAR(50),
    IN p_order_direction VARCHAR(10)
)
BEGIN
    SET NOCOUNT ON;

    SET @prefix = (SELECT COALESCE(prefix_booking_id, '') FROM hotel_configurations LIMIT 1);
    IF @prefix IS NULL THEN
        SET @prefix = '';
    END IF;

    SET @row_index = 0;

    SELECT 
        (@row_index := @row_index + 1) AS STT,
        sb.code AS Ma,
        CONCAT(@prefix, COALESCE(b.booking_code, CAST(b.id AS CHAR))) AS RegisterID2,
        COALESCE(b.arrival_date, br.arrival_date) AS ArrivalDateVW,
        COALESCE(b.departure_date, br.departure_date) AS DepartureDateVW,
        COALESCE(CONCAT(c.title, ' ', c.full_name), b.booking_name) AS Guest,
        sb.service_date AS DateHDDV,       -- CỘT BỔ SUNG: Ngày phát sinh hóa đơn dịch vụ
        sb.description AS DescriptionServive,
        
        -- Bóc tách doanh thu gốc, phí dịch vụ, thuế tiêu thụ đặc biệt, thuế VAT
        ROUND(
            sb.total_amount / (1 + COALESCE(sb.tax_rate, 0) / 100) 
                            / (1 + COALESCE(sb.special_tax_rate, 0) / 100) 
                            / (1 + COALESCE(sb.service_charge_rate, 0) / 100)
        , 2) AS OriginalRate,
        
        ROUND(
            (sb.total_amount / (1 + COALESCE(sb.tax_rate, 0) / 100) / (1 + COALESCE(sb.special_tax_rate, 0) / 100))
            * (COALESCE(sb.service_charge_rate, 0) / 100) / (1 + COALESCE(sb.service_charge_rate, 0) / 100)
        , 2) AS ServiceChargeAmount,
        
        ROUND(
            (sb.total_amount / (1 + COALESCE(sb.tax_rate, 0) / 100))
            * (COALESCE(sb.special_tax_rate, 0) / 100) / (1 + COALESCE(sb.special_tax_rate, 0) / 100)
        , 2) AS SpecialTaxAmount,
        
        ROUND(
            (sb.total_amount * COALESCE(sb.tax_rate, 0) / 100) / (1 + COALESCE(sb.tax_rate, 0) / 100)
        , 2) AS TaxAmount,
        
        sb.total_amount AS Amount,
        inv.payment_method_id AS PaymentMethod,
        COALESCE(comp.company_name, 'KHÁCH LẺ') AS Company,
        sb.created_by AS Username,
        DATE_FORMAT(sb.created_at, '%H:%i') AS OpenTime,
        COALESCE(sb.outlet_id, 'RC') AS Outlet,
        COALESCE(outl.name, 'Reception') AS OutletName,
        sb.service_id AS ServiceId,
        srv.name AS FirstNameService,
        DATE_FORMAT(sb.service_date, '%m-%Y') AS DateFormat,
        DATE_FORMAT(sb.service_date, '%Y-%m') AS `Date`,
        inv.invoice_date AS DateHDBH
    FROM service_bills sb
    INNER JOIN invoices inv ON sb.invoice_id = inv.id
    LEFT JOIN services srv ON sb.service_id = srv.id OR sb.service_id = srv.code
    LEFT JOIN outlets outl ON sb.outlet_id = outl.id OR sb.outlet_id = outl.code
    LEFT JOIN booking_rooms br ON sb.booking_room_id = br.id
    LEFT JOIN bookings b ON COALESCE(sb.booking_id, br.booking_id) = b.id
    LEFT JOIN customers c ON sb.customer_id = c.id
    LEFT JOIN companies comp ON b.company_id = comp.id
    LEFT JOIN payment_methods pm ON inv.payment_method_id = pm.id OR inv.payment_method_id = pm.code
    WHERE inv.invoice_date BETWEEN p_from_date AND p_to_date
      AND sb.payment_id IS NOT NULL
      AND (pm.is_complimentary IS NULL OR pm.is_complimentary = 0)
      
      -- ĐIỀU KIỆN HAI GIAI ĐOẠN: Tháng/Năm dịch vụ KHÁC Tháng/Năm thanh toán hóa đơn
      AND (
            (MONTH(sb.service_date) <> MONTH(inv.invoice_date))
            OR (YEAR(sb.service_date) <> YEAR(inv.invoice_date))
          )
          
      -- Bộ lọc người dùng
      AND (p_user IS NULL OR p_user = '' OR sb.created_by = p_user)
      
      -- Bộ lọc bộ phận
      AND (p_department IS NULL OR p_department = '' OR sb.department_id = p_department)
      
      -- Bộ lọc Outlet
      AND (p_outlet IS NULL OR p_outlet = '' OR sb.outlet_id = p_outlet)
      
      -- Bộ lọc dịch vụ đa chọn (Multi-select)
      AND (
            p_services IS NULL 
            OR p_services = '' 
            OR FIND_IN_SET(sb.service_id, p_services) 
            OR FIND_IN_SET(srv.code, p_services)
          )
      AND sb.deleted_at IS NULL
    ORDER BY 
        CASE WHEN p_order_by = 'Date' AND p_order_direction = 'DESC' THEN sb.service_date END DESC,
        CASE WHEN p_order_by = 'Date' THEN sb.service_date END ASC,
        CASE WHEN p_order_by = 'Ma' AND p_order_direction = 'DESC' THEN sb.id END DESC,
        sb.id ASC;
END$$

DELIMITER ;
```

---

## 6. Bảng Đối Chiếu Trường Dữ Liệu (Field Mapping Table)

| Tên Cột Báo Cáo | Cột Legacy (`vw_044` / `sp_217`) | Bảng & Cột Mới (Laravel / MySQL) | Ý Nghĩa / Ghi Chú |
|---|---|---|---|
| `STT` | `ROW_NUMBER() OVER (...)` | Biến đếm `@row_index` | Số thứ tự |
| `Mã HĐ` | `vw.Ma` | `service_bills.code` | Mã hóa đơn dịch vụ |
| `Mã ĐK` | `RegisterID2` | `bookings.booking_code` | Mã booking liên quan |
| `Ngày Đến` | `vw.ArrivalDateVW` | `bookings.arrival_date` | Ngày check-in |
| `Ngày Đi` | `vw.DepartureDateVW` | `bookings.departure_date` | Ngày check-out |
| `Tên Khách` | `Guest` | `customers.full_name` / `bookings.booking_name` | Tên khách thanh toán hoặc khách ở |
| **`Ngày Dịch Vụ`** | `vw.Date` (`DateHDDV`) | `service_bills.service_date` | **Cột mới**: Ngày post dịch vụ thực tế |
| `Mô Tả` | `vw.DescriptionServive` | `service_bills.description` | Diễn giải tên dịch vụ / món |
| `Giá Gốc` | `vw.OriginalRate` | Công thức tính bóc thuế phí | Doanh thu thuần trước phí & thuế |
| `Phí Dịch Vụ` | `vw.ServiceChargeAmount` | Công thức tính phí phục vụ | Tiền phí phục vụ |
| `Thuế Đặc Biệt`| `vw.SpecialTaxAmount` | Công thức thuế TTĐB | Tiền thuế tiêu thụ đặc biệt |
| `Thuế` | `vw.TaxAmount` | Công thức thuế VAT | Tiền thuế VAT |
| `Doanh Thu` | `vw.Amount` (`TotalAmount0`) | `service_bills.total_amount` | Tổng thanh toán |
| `HTTT` | `hd.Pack1` (`PaymentMethod`) | `invoices.payment_method_id` | Phương thức thanh toán hóa đơn |
| `Công Ty` | `Company` | `companies.company_name` | Tên công ty / lữ hành |
| `Người Dùng` | `vw.Username` | `service_bills.created_by` | Nhân viên post dịch vụ |
| `Giờ` | `vw.OpenTime` | `DATE_FORMAT(created_at, '%H:%i')` | Giờ phát sinh |
