# Đặc Tả Kỹ Thuật: Báo Cáo Thu Ngân Lễ Tân - Dòng 158

> **Tài liệu chuẩn bị cho Agent triển khai tiếp theo**  
> Dựa trên phân tích từ file Excel `DANH MỤC BÁO CÁO.xlsx` (Dòng 158, Sheet 63 `BC thu ngân`), ảnh giao diện thực tế `[.codex/docs/doc_baocao/images/dong_158_ui_mau.png](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/images/dong_158_ui_mau.png)` và Stored Procedure gốc trên MS SQL Server `ProVistaNavyHotel.dbo.sp_039` ([ProVistaNavyHotel_sp_039.sql](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/sql/ProVistaNavyHotel_sp_039.sql)).

---

## 1. Thông Tin Chung & Định Danh

* **Tên báo cáo tiếng Việt**: Báo cáo thu ngân lễ tân
* **Mã báo cáo (`code`)**: `RECEPTION_CASHIER_SHIFT`
* **Mã nguồn dữ liệu (`data_source_code`)**: `RPT_RECEPTION_CASHIER_SHIFT`
* **Mã template (`template_code`)**: `RECEPTION_CASHIER_SHIFT_REFERENCE`
* **Nhóm báo cáo (`group`)**: `Báo cáo thu ngân`
* **Menu hiển thị**: `['frontdesk', 'cashier', 'report']`
* **Vị trí trong Excel danh mục**: Dòng 158 (STT 12)
* **Sheet tham chiếu trong Excel**: Sheet 63 (`BC thu ngân`)
* **Lưu ý đặc biệt trong Excel**:
  * Đọc đúng Store của Navy: `ProVistaNavyHotel.dbo.sp_039`.
  * Có thông số cấu hình bộ phận khi xem báo cáo: `Report_ListDepartmentCashierShiftReport` (giá trị: `FO,FB,MR,ACC`).
  * Giao diện báo cáo mặc định chọn bộ phận Lễ tân (`FO`).
  * Cho phép người dùng chọn nhiều bộ phận cùng lúc (multi-select).
* **Ảnh chụp màn hình thực tế**: `[.codex/docs/doc_baocao/images/dong_158_ui_mau.png](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/images/dong_158_ui_mau.png)`
* **File SQL legacy tham chiếu**: [ProVistaNavyHotel_sp_039.sql](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/sql/ProVistaNavyHotel_sp_039.sql)

---

## 2. Phân Tích Giao Diện & Bộ Lọc (`dong_158_ui_mau.png`)

### 2.1. Panel Bộ Lọc Bên Trái (Left Filter Panel)
* **Chiều rộng panel**: ~ 260px - 280px.
* **Các trường tham số đầu vào**:
  1. **Chọn ngày (`p_date_range`)**: Date Range Picker. Mặc định `Hôm nay` (Từ ngày - Đến ngày: `$today ~ $today`).
  2. **Ca làm việc (`p_shift`)**: Dropdown Select. Danh mục ca (`shifts`), giá trị mặc định: `Select Value` (rỗng - tất cả các ca).
  3. **Chọn giờ (`p_time_range`)**: Time Range Picker (`00:00 ~ 23:59`).
  4. **Chọn bộ phận (`p_department`)**: Multi-select Dropdown. Mặc định chọn `FO` (Reception / Lễ tân). Nguồn danh sách lấy từ cấu hình tham số hệ thống `Report_ListDepartmentCashierShiftReport` (gồm: `FO: Lễ tân`, `FB: Nhà hàng/Ẩm thực`, `MR: Massage/Spa`, `ACC: Kế toán`).
  5. **Chọn công ty (`p_company_id`)**: Dropdown Select, placeholder: `Công ty`. Mặc định: `-1` hoặc rỗng (Tất cả).
  6. **Chọn người dùng (`p_user`)**: Multi-select / Single Dropdown, placeholder: `Chọn: 0` (Tất cả người dùng).
  7. **Toggles / Switches**:
     * `Hiển thị công ty`: Toggle boolean (mặc định `false`).
     * `Hiển thị đặt cọc`: Toggle boolean (mặc định `true`).
     * `Hiển thị tiền = 0`: Toggle boolean (mặc định `false`).
  8. **Nút thực thi**: `Hiển thị báo cáo` (Nút màu xanh `#3b82f6`).

### 2.2. Bố Cục Trang Báo Cáo (A4 Ngang - Landscape)
1. **Khối Header**:
   * Logo thương hiệu khách sạn bên trái.
   * Tiêu đề chính giữa: **BÁO CÁO THU NGÂN LỄ TÂN** (In hoa, font đậm, size 18pt).
   * Thông tin phụ bên phải: Địa chỉ, Người dùng in: `Admin`, Ngày giờ in.
   * Dòng tiêu chí tìm kiếm:
     * `Ngày: 15/07/2026 ~ 15/07/2026`
     * `Ca: [Tên Ca]      Giờ: 00:00 ~ 23:59`
2. **Khối Bảng 1: Chi Tiết Giao Dịch Thu Ngân (Detail Grid)**:
   * Header 1 tầng gồm 11 cột:
     * `Mã ĐK`: Mã booking/đăng ký (click liên kết đến chi tiết đặt phòng).
     * `Phòng`: Số phòng lưu trú.
     * `Tên Khách`: Họ tên khách hàng đại diện.
     * `Ngày Đến`: Định dạng `dd/MM/yyyy`.
     * `Ngày Đi`: Định dạng `dd/MM/yyyy`.
     * `Giờ`: Giờ thực hiện thanh toán (`HH:mm`).
     * `Mã TT`: Mã phiếu thu / hóa đơn bán hàng / Bill ID.
     * `Số Tiền`: Số tiền giao dịch (VND, phân cách hàng nghìn).
     * `Người dùng`: Tài khoản thu ngân thực hiện.
     * `Mô Tả`: Diễn giải thanh toán (ví dụ: `Advance Payment (Cash/Tiền mặt)`).
     * `Ghi Chú DT FB`: Ghi chú doanh thu FB / Mã bill dịch vụ FB (nếu có).
   * **Cơ chế Phân nhóm (Grouping 2 cấp)**:
     * **Cấp 1 (Loại nghiệp vụ - Group Type)**:
       * `Deposit` (Đặt cọc)
       * `Cashier` (Thu ngân thanh toán)
       * `Hoàn Trả` (Refund - khi số tiền âm)
     * **Cấp 2 (Phương thức thanh toán - Payment Method)**:
       * Ví dụ: `Thanh Toán: CA - Cash/Tiền mặt`, `Thanh Toán: CD - Thẻ tín dụng (Visa/Master/JCB)`, `Thanh Toán: CK - Chuyển khoản`, `Thanh Toán: AC - Công nợ (City Ledger)`.
     * Dòng tổng nhóm (Subtotal):
       * Dòng tổng từng phương thức: `Tổng: [Số tiền]`
       * Dòng tổng từng loại: `Cashier Total: [Số tiền]`, `Deposit Total: [Số tiền]`
     * Dòng tổng cộng bảng chi tiết:
       * `Tổng số: [Số dòng]` | `Tổng: [Tổng số tiền]`
3. **Khối Bảng 2: Bảng Phân Bổ Tiền Tệ (Currency / Payment Distribution Summary Table)**:
   * Tiêu đề: **Bảng Phân Bổ Tiền Tệ** (Font đậm, căn giữa, size 14pt).
   * Bảng ma trận 6 cột:
     * `HTTT`: Tên phương thức thanh toán (ví dụ `CA (Cash/Tiền mặt)`, `CD (Credit Card/Thẻ tín dụng)`, `CK (Bank Transfer/Chuyển khoản)`).
     * `Thu Ngân`: Tổng tiền thu trực tiếp tại quầy thanh toán (`Amount` của Cashier).
     * `Đặt Cọc`: Tổng tiền đặt cọc nhận trước (`Amount` của Deposit).
     * `Thu Ngân + Đặt Cọc`: Tổng cộng thu ngân và đặt cọc (`Thu Ngân + Đặt Cọc`).
     * `Hoàn Tiền`: Tổng tiền hoàn trả khách (`Amount` âm hoặc tiền refund).
     * `Tổng`: Thực thu ròng (`Thu Ngân + Đặt Cọc - Hoàn Tiền`).
   * Dòng tổng cộng: `Tổng` | [Tổng Thu Ngân] | [Tổng Đặt Cọc] | [Tổng TN+Cọc] | [Tổng Hoàn] | [Tổng Thực Thu].
4. **Khối Bảng 3: Tổng Hợp Công Nợ Công Ty (City Ledger Summary Table)**:
   * Tiêu đề: **Tổng Hợp Công Nợ Công Ty** (Font đậm, căn giữa, size 14pt).
   * Bảng 4 cột:
     * `HTTT`: `AC (City ledger/Công nợ)`.
     * `Tổng`: Tổng số tiền ghi nhận công nợ trong ca/kỳ.
     * `Đã Thanh Toán`: Số tiền công nợ đã được thu.
     * `Còn Lại`: Số dư công nợ còn phải thu.
5. **Khối Footer Chữ Ký**:
   * 3 cột chữ ký: `Nhân viên` (Lập biểu) | `Trưởng phòng` | `Bộ phận kế toán`.

---

## 3. Cấu Trúc Toàn Diện 100% Của `content_json` (Form Designer)

> **Dành cho Agent triển khai:**  
> File PHP dưới đây chứa toàn bộ định nghĩa metadata, cấu trúc khối `blocks`, cột `columns`, dòng tổng cộng `customRows`, template HTML và CSS theo chuẩn của `TemplateRendererService`. Hãy đặt tại:  
> `backend/database/report_templates/reception_cashier_shift_reference.php`.

```php
<?php

use App\Services\TemplateRendererService;

return new class
{
    public function definition(): array
    {
        return [
            'code' => 'RECEPTION_CASHIER_SHIFT',
            'name' => 'Báo cáo thu ngân lễ tân',
            'report' => 'RECEPTION_CASHIER_SHIFT_REFERENCE',
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
                    'BookingStatus' => 'integer',
                    'StatusRoom' => 'integer',
                    'BillID' => 'string',
                    'Date' => 'string',
                    'Room' => 'string',
                    'Guest' => 'string',
                    'OpenTime' => 'string',
                    'PaymentID' => 'string',
                    'Amount' => 'number',
                    'Username' => 'string',
                    'Description' => 'string',
                    'PaymentMethod' => 'string',
                    'PaymentMethodName' => 'string',
                    'Department' => 'string',
                    'NumOfRoom' => 'string',
                    'Deposit' => 'string',
                    'ShowDeposit' => 'string',
                    'MaBooking' => 'string',
                    'ArrivalDate' => 'string',
                    'DepartureDate' => 'string',
                    'GuestInfo' => 'string',
                    'Company' => 'string',
                    'CardId' => 'string',
                    'Division' => 'string',
                ],
                'distribution_rows' => [
                    'PaymentMethod' => 'string',
                    'PaymentMethodName' => 'string',
                    'CashierAmount' => 'number',
                    'DepositAmount' => 'number',
                    'TotalCashierDeposit' => 'number',
                    'RefundAmount' => 'number',
                    'NetTotal' => 'number',
                ],
                'city_ledger_rows' => [
                    'PaymentMethod' => 'string',
                    'TotalAmount' => 'number',
                    'PaidAmount' => 'number',
                    'BalanceAmount' => 'number',
                ],
                'parameters' => [
                    'p_from_date' => 'string',
                    'p_to_date' => 'string',
                    'p_department' => 'string',
                    'p_user' => 'string',
                    'p_shift' => 'string',
                    'p_from_time' => 'string',
                    'p_to_time' => 'string',
                    'p_company_id' => 'string',
                    'p_view_deposit' => 'integer',
                    'p_view_amount_zero' => 'integer',
                    'p_payment_method' => 'string',
                ],
            ],
        ];
    }

    public function columns(): array
    {
        return [
            ['key' => 'MaBooking', 'title' => 'Mã ĐK', 'width' => '90px', 'align' => 'center'],
            ['key' => 'Room', 'title' => 'Phòng', 'width' => '65px', 'align' => 'center'],
            ['key' => 'GuestInfo', 'title' => 'Tên Khách', 'width' => '150px', 'align' => 'left'],
            ['key' => 'ArrivalDate', 'title' => 'Ngày Đến', 'width' => '85px', 'align' => 'center'],
            ['key' => 'DepartureDate', 'title' => 'Ngày Đi', 'width' => '85px', 'align' => 'center'],
            ['key' => 'OpenTime', 'title' => 'Giờ', 'width' => '60px', 'align' => 'center'],
            ['key' => 'BillID', 'title' => 'Mã TT', 'width' => '80px', 'align' => 'center'],
            ['key' => 'Amount', 'title' => 'Số Tiền', 'width' => '110px', 'align' => 'right', 'format' => '#,##0'],
            ['key' => 'Username', 'title' => 'Người dùng', 'width' => '80px', 'align' => 'center'],
            ['key' => 'Description', 'title' => 'Mô Tả', 'width' => '180px', 'align' => 'left'],
            ['key' => 'NoteFB', 'title' => 'Ghi Chú DT FB', 'width' => '110px', 'align' => 'left'],
        ];
    }

    public function blocks(): array
    {
        return [
            'header' => [
                'type' => 'header',
                'title' => 'BÁO CÁO THU NGÂN LỄ TÂN',
                'show_logo' => true,
                'show_hotel_info' => true,
                'meta_fields' => [
                    ['label' => 'Ngày', 'value' => '{p_from_date} ~ {p_to_date}'],
                    ['label' => 'Ca', 'value' => '{p_shift}'],
                    ['label' => 'Giờ', 'value' => '{p_from_time} ~ {p_to_time}'],
                ],
            ],
            'grid_details' => [
                'type' => 'grid',
                'data_source' => 'rows',
                'grouping' => [
                    'level1' => [
                        'field' => 'ShowDeposit',
                        'label' => '{ShowDeposit}',
                        'show_header' => true,
                        'subtotal' => true,
                    ],
                    'level2' => [
                        'field' => 'PaymentMethodName',
                        'label' => 'Thanh Toán: {PaymentMethod} - {PaymentMethodName}',
                        'show_header' => true,
                        'subtotal' => true,
                    ],
                ],
                'columns' => $this->columns(),
                'customRows' => [
                    [
                        'type' => 'subtotal_level2',
                        'label' => 'Tổng',
                        'aggregate' => ['Amount' => 'sum'],
                    ],
                    [
                        'type' => 'subtotal_level1',
                        'label' => '{ShowDeposit} Total',
                        'aggregate' => ['Amount' => 'sum'],
                    ],
                    [
                        'type' => 'grand_total',
                        'label' => 'Tổng số: {count(rows)}',
                        'aggregate' => ['Amount' => 'sum'],
                    ],
                ],
            ],
            'table_distribution' => [
                'type' => 'static_grid',
                'title' => 'Bảng Phân Bổ Tiền Tệ',
                'data_source' => 'distribution_rows',
                'columns' => [
                    ['key' => 'PaymentMethodTitle', 'title' => 'HTTT', 'width' => '250px', 'align' => 'left'],
                    ['key' => 'CashierAmount', 'title' => 'Thu Ngân', 'width' => '130px', 'align' => 'right', 'format' => '#,##0'],
                    ['key' => 'DepositAmount', 'title' => 'Đặt Cọc', 'width' => '130px', 'align' => 'right', 'format' => '#,##0'],
                    ['key' => 'TotalCashierDeposit', 'title' => 'Thu Ngân + Đặt Cọc', 'width' => '150px', 'align' => 'right', 'format' => '#,##0'],
                    ['key' => 'RefundAmount', 'title' => 'Hoàn Tiền', 'width' => '130px', 'align' => 'right', 'format' => '#,##0'],
                    ['key' => 'NetTotal', 'title' => 'Tổng', 'width' => '150px', 'align' => 'right', 'format' => '#,##0'],
                ],
                'grand_total' => true,
            ],
            'table_city_ledger' => [
                'type' => 'static_grid',
                'title' => 'Tổng Hợp Công Nợ Công Ty',
                'data_source' => 'city_ledger_rows',
                'columns' => [
                    ['key' => 'PaymentMethodTitle', 'title' => 'HTTT', 'width' => '300px', 'align' => 'left'],
                    ['key' => 'TotalAmount', 'title' => 'Tổng', 'width' => '180px', 'align' => 'right', 'format' => '#,##0'],
                    ['key' => 'PaidAmount', 'title' => 'Đã Thanh Toán', 'width' => '180px', 'align' => 'right', 'format' => '#,##0'],
                    ['key' => 'BalanceAmount', 'title' => 'Còn Lại', 'width' => '180px', 'align' => 'right', 'format' => '#,##0'],
                ],
            ],
            'footer' => [
                'type' => 'signatures',
                'roles' => [
                    ['title' => 'Nhân viên', 'subtitle' => '(Ký, ghi rõ họ tên)'],
                    ['title' => 'Trưởng phòng', 'subtitle' => '(Ký, ghi rõ họ tên)'],
                    ['title' => 'Bộ phận kế toán', 'subtitle' => '(Ký, ghi rõ họ tên)'],
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
            <h1 class="report-title">BÁO CÁO THU NGÂN LỄ TÂN</h1>
            <div class="report-filter-meta">
                <span><strong>Ngày:</strong> {p_from_date} ~ {p_to_date}</span>
                <span style="margin-left: 20px;"><strong>Ca:</strong> {p_shift}</span>
                <span style="margin-left: 20px;"><strong>Giờ:</strong> {p_from_time} ~ {p_to_time}</span>
            </div>
        </div>
        <div class="header-right">
            <div><strong>Địa chỉ:</strong> {hotel_address}</div>
            <div><strong>Người dùng:</strong> {current_user}</div>
            <div><strong>Ngày:</strong> {current_date}</div>
        </div>
    </div>

    <!-- Bảng 1: Chi Tiết Thu Ngân -->
    <table class="report-table main-grid">
        <thead>
            <tr>
                <th style="width: 90px;">Mã ĐK</th>
                <th style="width: 65px;">Phòng</th>
                <th style="width: 150px;">Tên Khách</th>
                <th style="width: 85px;">Ngày Đến</th>
                <th style="width: 85px;">Ngày Đi</th>
                <th style="width: 60px;">Giờ</th>
                <th style="width: 80px;">Mã TT</th>
                <th style="width: 110px;">Số Tiền</th>
                <th style="width: 80px;">Người dùng</th>
                <th style="width: 180px;">Mô Tả</th>
                <th style="width: 110px;">Ghi Chú DT FB</th>
            </tr>
        </thead>
        <tbody>
            {#groups_level1}
            <tr class="group-header-l1">
                <td colspan="11"><strong>{group_name_l1}</strong></td>
            </tr>
            {#groups_level2}
            <tr class="group-header-l2">
                <td colspan="11" style="color: #dc2626; font-weight: bold;">
                    Thanh Toán &nbsp;&nbsp;&nbsp;&nbsp; {group_name_l2}
                </td>
            </tr>
            {#items}
            <tr>
                <td class="text-center text-primary font-bold">{MaBooking}</td>
                <td class="text-center">{Room}</td>
                <td>{GuestInfo}</td>
                <td class="text-center">{ArrivalDate}</td>
                <td class="text-center">{DepartureDate}</td>
                <td class="text-center">{OpenTime}</td>
                <td class="text-center">{BillID}</td>
                <td class="text-right font-bold">{Amount}</td>
                <td class="text-center">{Username}</td>
                <td>{Description}</td>
                <td>{CardId}</td>
            </tr>
            {/items}
            <tr class="subtotal-l2">
                <td colspan="7" class="text-right font-bold">Tổng</td>
                <td class="text-right font-bold">{subtotal_l2_amount}</td>
                <td colspan="3"></td>
            </tr>
            {/groups_level2}
            <tr class="subtotal-l1">
                <td colspan="7" class="text-right font-bold">{group_name_l1} Total</td>
                <td class="text-right font-bold">{subtotal_l1_amount}</td>
                <td colspan="3"></td>
            </tr>
            {/groups_level1}
        </tbody>
        <tfoot>
            <tr class="grand-total">
                <td colspan="2" class="font-bold">Tổng số: {total_records}</td>
                <td colspan="5" class="text-right font-bold">Tổng</td>
                <td class="text-right font-bold">{grand_total_amount}</td>
                <td colspan="3"></td>
            </tr>
        </tfoot>
    </table>

    <!-- Bảng 2: Bảng Phân Bổ Tiền Tệ -->
    <div class="section-title">Bảng Phân Bổ Tiền Tệ</div>
    <table class="report-table summary-grid">
        <thead>
            <tr>
                <th>HTTT</th>
                <th>Thu Ngân</th>
                <th>Đặt Cọc</th>
                <th>Thu Ngân + Đặt Cọc</th>
                <th>Hoàn Tiền</th>
                <th>Tổng</th>
            </tr>
        </thead>
        <tbody>
            {#distribution_rows}
            <tr>
                <td>{PaymentMethodTitle}</td>
                <td class="text-right">{CashierAmount}</td>
                <td class="text-right">{DepositAmount}</td>
                <td class="text-right">{TotalCashierDeposit}</td>
                <td class="text-right">{RefundAmount}</td>
                <td class="text-right font-bold">{NetTotal}</td>
            </tr>
            {/distribution_rows}
        </tbody>
        <tfoot>
            <tr class="grand-total">
                <td class="font-bold">Tổng</td>
                <td class="text-right font-bold">{sum_cashier}</td>
                <td class="text-right font-bold">{sum_deposit}</td>
                <td class="text-right font-bold">{sum_cashier_deposit}</td>
                <td class="text-right font-bold">{sum_refund}</td>
                <td class="text-right font-bold">{sum_net_total}</td>
            </tr>
        </tfoot>
    </table>

    <!-- Bảng 3: Tổng Hợp Công Nợ Công Ty -->
    <div class="section-title">Tổng Hợp Công Nợ Công Ty</div>
    <table class="report-table summary-grid">
        <thead>
            <tr>
                <th>HTTT</th>
                <th>Tổng</th>
                <th>Đã Thanh Toán</th>
                <th>Còn Lại</th>
            </tr>
        </thead>
        <tbody>
            {#city_ledger_rows}
            <tr>
                <td>{PaymentMethodTitle}</td>
                <td class="text-right">{TotalAmount}</td>
                <td class="text-right">{PaidAmount}</td>
                <td class="text-right">{BalanceAmount}</td>
            </tr>
            {/city_ledger_rows}
        </tbody>
    </table>

    <!-- Footer chữ ký -->
    <div class="report-signatures">
        <div class="sig-col">
            <div class="sig-title">Nhân viên</div>
            <div class="sig-space"></div>
        </div>
        <div class="sig-col">
            <div class="sig-title">Trưởng phòng</div>
            <div class="sig-space"></div>
        </div>
        <div class="sig-col">
            <div class="sig-title">Bộ phận kế toán</div>
            <div class="sig-space"></div>
        </div>
    </div>
</div>
HTML;
    }

    public function css(): string
    {
        return <<<'CSS'
.report-container { font-family: 'Times New Roman', Times, serif; font-size: 11pt; color: #111827; }
.report-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px; }
.hotel-logo { max-height: 60px; max-width: 140px; }
.header-center { text-align: center; flex: 1; margin: 0 15px; }
.report-title { font-size: 17pt; font-weight: bold; margin: 0 0 6px 0; text-transform: uppercase; }
.report-filter-meta { font-size: 10pt; color: #374151; }
.header-right { font-size: 9pt; text-align: right; line-height: 1.35; }
.section-title { text-align: center; font-size: 14pt; font-weight: bold; margin: 20px 0 8px 0; }
.report-table { width: 100%; border-collapse: collapse; margin-bottom: 15px; font-size: 9.5pt; }
.report-table th, .report-table td { border: 1px solid #d1d5db; padding: 4px 6px; }
.report-table th { background-color: #e5e7eb; font-weight: bold; text-align: center; }
.group-header-l1 td { background-color: #f3f4f6; font-size: 10pt; padding: 5px; }
.group-header-l2 td { background-color: #ffffff; padding: 4px; }
.subtotal-l2 td, .subtotal-l1 td { background-color: #e5e7eb; }
.grand-total td { background-color: #d1d5db; font-weight: bold; }
.text-center { text-align: center; }
.text-right { text-align: right; }
.font-bold { font-weight: bold; }
.text-primary { color: #2563eb; }
.report-signatures { display: flex; justify-content: space-between; margin-top: 30px; page-break-inside: avoid; }
.sig-col { width: 30%; text-align: center; }
.sig-title { font-weight: bold; font-size: 10.5pt; margin-bottom: 60px; }
CSS;
    }
};
```

---

## 4. Bóc Tách Chi Tiết Logic SQL Legacy (Navy Hotel `sp_039`)

### 4.1. Tham Số Đầu Vào Của Store Gốc (`ProVistaNavyHotel.dbo.sp_039`)
* `@FromDate date`: Ngày bắt đầu xem báo cáo.
* `@ToDate date`: Ngày kết thúc xem báo cáo.
* `@Department varchar(200)`: Danh sách bộ phận phân cách bằng dấu phẩy (ví dụ: `'FO'` hoặc `'FO,FB,MR'`).
* `@User varchar(50)`: Lọc theo người dùng thực hiện thu ngân (`''` = tất cả).
* `@Ca varchar(5)`: Mã ca làm việc (`''` = tất cả).
* `@FromTime varchar(5)`, `@ToTime varchar(5)`: Giờ bắt đầu/kết thúc (`HH:mm`).
* `@Company varchar(20)`: Lọc mã công ty/đại lý (`''` hoặc `'-1'` = tất cả).
* `@ViewDatCoc int`:
  * `0`: Xem tất cả (cả thu ngân và đặt cọc).
  * `1`: Chỉ xem thu ngân.
  * `2`: Chỉ xem đặt cọc.
* `@ViewAmount0 int`: `1`: hiển thị cả dòng có số tiền = 0; `0`: chỉ lấy các giao dịch khác 0.
* `@PaymentMethod varchar(100)`: Lọc theo phương thức thanh toán (`''` = tất cả).

### 4.2. Khác Biệt Giữa Navy Hotel và Army Hotel
* **Navy Hotel**:
  * Sử dụng bảng tạm `#tempKhachLe` gom nhóm từ `SP2000` (đăng ký) và `SP2100` (phòng) với điều kiện `having count(*) = 1 and pt.BookingId is not null`. Nhờ đó, nếu giao dịch không gắn trực tiếp số phòng thì sẽ hiển thị số phòng của khách lẻ đó (`isnull(bcdt.Room, tmp.Room)`).
  * Không phụ thuộc vào bảng doanh thu F&B riêng lẻ của Army (`SP5000`), giúp báo cáo chạy ổn định và dùng chung được cho các đơn vị chuẩn.
* **Quy tắc hiển thị Thẻ Tín Dụng**:
  * Khi `PaymentMethod = 'CD'`, che số thẻ bằng cách giữ lại 4 chữ số cuối: `replicate('*', len(card.CardId) - 4) + RIGHT(card.CardId, 4)`.

---

## 5. Stored Procedure Chuẩn Hóa Trên MySQL 8.0

> Lưu tại: `backend/database/procedures/rpt_reception_cashier_shift.sql`.

```sql
DELIMITER $$

DROP PROCEDURE IF EXISTS `rpt_reception_cashier_shift`$$

CREATE PROCEDURE `rpt_reception_cashier_shift`(
    IN p_from_date DATE,
    IN p_to_date DATE,
    IN p_department VARCHAR(255),
    IN p_user VARCHAR(100),
    IN p_shift VARCHAR(50),
    IN p_from_time VARCHAR(10),
    IN p_to_time VARCHAR(10),
    IN p_company_id VARCHAR(50),
    IN p_view_deposit INT,
    IN p_view_amount_zero INT,
    IN p_payment_method VARCHAR(255)
)
BEGIN
    SET NOCOUNT ON;

    -- Lấy cấu hình prefix booking
    SET @prefix = (SELECT COALESCE(prefix_booking_id, '') FROM hotel_configurations LIMIT 1);
    IF @prefix IS NULL THEN
        SET @prefix = '';
    END IF;

    -- 1. Bảng tạm xác định phòng cho khách lẻ (1 booking - 1 phòng)
    DROP TEMPORARY TABLE IF EXISTS temp_khach_le;
    CREATE TEMPORARY TABLE temp_khach_le AS
    SELECT 
        br.booking_id, 
        MAX(r.room_number) AS room_number
    FROM bookings b
    JOIN booking_rooms br ON b.id = br.booking_id
    LEFT JOIN rooms r ON br.room_id = r.id
    GROUP BY br.booking_id
    HAVING COUNT(*) = 1 AND br.booking_id IS NOT NULL;

    -- 2. Dữ liệu chi tiết giao dịch thu ngân (Main Grid)
    SELECT 
        b.status AS BookingStatus,
        br.status AS StatusRoom,
        p.invoice_id AS BillID,
        p.payment_date AS `Date`,
        COALESCE(r.room_number, tkl.room_number, '') AS Room,
        p.guest_name AS Guest,
        DATE_FORMAT(p.created_at, '%H:%i') AS OpenTime,
        p.id AS PaymentID,
        p.amount AS Amount,
        p.created_by AS Username,
        p.description AS Description,
        p.payment_method_id AS PaymentMethod,
        pm.name AS PaymentMethodName,
        d.code AS Department,
        r.room_number AS NumOfRoom,
        CASE 
            WHEN p.amount < 0 THEN '2'
            WHEN p.is_deposit = 1 OR p.payment_type = 'deposit' THEN '1'
            ELSE '0'
        END AS Deposit,
        CASE 
            WHEN p.amount < 0 THEN 'Hoàn Trả'
            WHEN p.is_deposit = 1 OR p.payment_type = 'deposit' THEN 'Đặt cọc'
            ELSE 'Thu Ngân'
        END AS ShowDeposit,
        CONCAT(@prefix, COALESCE(b.booking_code, CAST(b.id AS CHAR))) AS MaBooking,
        b.arrival_date AS ArrivalDate,
        b.departure_date AS DepartureDate,
        COALESCE(CONCAT(c.title, ' ', c.full_name), b.booking_name) AS GuestInfo,
        comp.company_name AS Company,
        CASE 
            WHEN p.payment_method_id = 'CD' AND p.card_number IS NOT NULL AND CHAR_LENGTH(p.card_number) >= 4 
            THEN CONCAT(REPEAT('*', CHAR_LENGTH(p.card_number) - 4), RIGHT(p.card_number, 4))
            ELSE ''
        END AS CardId
    FROM payments p
    LEFT JOIN payment_methods pm ON p.payment_method_id = pm.id OR p.payment_method_id = pm.code
    LEFT JOIN departments d ON p.department_id = d.id OR p.department_id = d.code
    LEFT JOIN booking_rooms br ON p.booking_room_id = br.id
    LEFT JOIN rooms r ON br.room_id = r.id
    LEFT JOIN bookings b ON COALESCE(p.booking_id, br.booking_id) = b.id
    LEFT JOIN temp_khach_le tkl ON tkl.booking_id = b.id
    LEFT JOIN customers c ON p.customer_id = c.id
    LEFT JOIN companies comp ON b.company_id = comp.id
    WHERE p.payment_date BETWEEN p_from_date AND p_to_date
      AND (p_user IS NULL OR p_user = '' OR p.created_by = p_user)
      AND (p_shift IS NULL OR p_shift = '' OR p.shift_id = p_shift)
      AND (p_department IS NULL OR p_department = '' OR FIND_IN_SET(COALESCE(d.code, p.department_id), p_department))
      AND (p_payment_method IS NULL OR p_payment_method = '' OR FIND_IN_SET(p.payment_method_id, p_payment_method))
      AND (p_company_id IS NULL OR p_company_id = '' OR p_company_id = '-1' OR b.company_id = p_company_id)
      AND (p_from_time IS NULL OR p_from_time = '' OR DATE_FORMAT(p.created_at, '%H:%i') >= p_from_time)
      AND (p_to_time IS NULL OR p_to_time = '' OR DATE_FORMAT(p.created_at, '%H:%i') <= p_to_time)
      AND (p_view_amount_zero = 1 OR p.amount <> 0)
      AND (
            p_view_deposit = 0 
            OR (p_view_deposit = 1 AND (p.is_deposit = 0 AND p.payment_type <> 'deposit'))
            OR (p_view_deposit = 2 AND (p.is_deposit = 1 OR p.payment_type = 'deposit'))
          )
      AND p.deleted_at IS NULL
    ORDER BY p.payment_date ASC, p.created_at ASC;

    -- 3. Bảng Phân Bổ Tiền Tệ (Distribution Table)
    SELECT 
        pm.code AS PaymentMethod,
        CONCAT(pm.code, ' (', pm.name, ')') AS PaymentMethodTitle,
        SUM(CASE WHEN (p.is_deposit = 0 AND p.payment_type <> 'deposit' AND p.amount > 0) THEN p.amount ELSE 0 END) AS CashierAmount,
        SUM(CASE WHEN (p.is_deposit = 1 OR p.payment_type = 'deposit') AND p.amount > 0 THEN p.amount ELSE 0 END) AS DepositAmount,
        SUM(CASE WHEN p.amount > 0 THEN p.amount ELSE 0 END) AS TotalCashierDeposit,
        SUM(CASE WHEN p.amount < 0 THEN ABS(p.amount) ELSE 0 END) AS RefundAmount,
        SUM(p.amount) AS NetTotal
    FROM payments p
    JOIN payment_methods pm ON p.payment_method_id = pm.id OR p.payment_method_id = pm.code
    LEFT JOIN departments d ON p.department_id = d.id OR p.department_id = d.code
    WHERE p.payment_date BETWEEN p_from_date AND p_to_date
      AND (p_user IS NULL OR p_user = '' OR p.created_by = p_user)
      AND (p_shift IS NULL OR p_shift = '' OR p.shift_id = p_shift)
      AND (p_department IS NULL OR p_department = '' OR FIND_IN_SET(COALESCE(d.code, p.department_id), p_department))
      AND (p_payment_method IS NULL OR p_payment_method = '' OR FIND_IN_SET(p.payment_method_id, p_payment_method))
      AND (p_view_amount_zero = 1 OR p.amount <> 0)
      AND p.deleted_at IS NULL
    GROUP BY pm.code, pm.name
    ORDER BY pm.code ASC;

    -- 4. Bảng Tổng Hợp Công Nợ (City Ledger Table)
    SELECT 
        'AC (City ledger/Công nợ)' AS PaymentMethodTitle,
        SUM(COALESCE(p.amount, 0)) AS TotalAmount,
        SUM(COALESCE(p.paid_amount, 0)) AS PaidAmount,
        SUM(COALESCE(p.amount, 0) - COALESCE(p.paid_amount, 0)) AS BalanceAmount
    FROM payments p
    WHERE p.payment_method_id = 'AC'
      AND p.payment_date BETWEEN p_from_date AND p_to_date
      AND p.deleted_at IS NULL;

    DROP TEMPORARY TABLE IF EXISTS temp_khach_le;
END$$

DELIMITER ;
```

---

## 6. Bảng Đối Chiếu Trường Dữ Liệu (Field Mapping Table)

| Tên Cột Báo Cáo | Cột Legacy (`vw_004` / `sp_039`) | Bảng & Cột Mới (Laravel / MySQL) | Ý Nghĩa / Quy Tắc Tính |
|---|---|---|---|
| `Mã ĐK` | `MaBooking` (`@prefix + dk.Ma`) | `bookings.booking_code` | Mã định danh đặt phòng |
| `Phòng` | `isnull(bcdt.Room, tmp.Room)` | `rooms.room_number` / `temp_khach_le` | Số phòng lưu trú |
| `Tên Khách` | `GuestInfo` | `customers.full_name` / `bookings.booking_name` | Tên khách thanh toán hoặc khách đại diện |
| `Ngày Đến` | `ArrivalDate` | `bookings.arrival_date` | Ngày check-in dự kiến/thực tế |
| `Ngày Đi` | `DepartureDate` | `bookings.departure_date` | Ngày check-out dự kiến/thực tế |
| `Giờ` | `OpenTime` | `DATE_FORMAT(payments.created_at, '%H:%i')` | Giờ giao dịch |
| `Mã TT` | `dv.InvoiceId` / `BillID` | `payments.invoice_id` | Mã phiếu thu / bill thanh toán |
| `Số Tiền` | `bcdt.Amount` | `payments.amount` | Số tiền giao dịch (+ là thu, - là hoàn) |
| `Người dùng` | `bcdt.Username` | `payments.created_by` | Tài khoản nhân viên thực hiện thu tiền |
| `Mô Tả` | `bcdt.Description` | `payments.description` | Diễn giải thu tiền |
| `Ghi Chú DT FB` | `CardId` (Thẻ CD) | `payments.card_number` (che 4 số cuối) | 4 số cuối thẻ tín dụng nếu HTTT là 'CD' |

---

## 7. Trạng thái triển khai runtime

- Mã runtime: `RECEPTION_CASHIER_SHIFT`, procedure `rpt_reception_cashier_shift`, template `RECEPTION_CASHIER_SHIFT_REFERENCE`.
- Migration đã chạy: `2026_09_22_100000` và các migration tương thích schema đến `2026_09_22_160000` trên HKT1–HKT4.
- Enricher tạo `currency_allocations` và `city_ledger_rows`; City Ledger lọc phương thức `AC` và đối soát `payment_debt_settlements`.
- Smoke-test `CALL rpt_reception_cashier_shift(...)` đạt trên `pms_hkt1`–`pms_hkt4`; chưa nghiệm thu số liệu với dữ liệu legacy thật.
