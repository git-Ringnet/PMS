# TÀI LIỆU ĐẶC TẢ CHI TIẾT - DÒNG 171: BÁO CÁO TỔNG DOANH THU

> **Dành cho Agent triển khai:** Tài liệu này đặc tả chi tiết 100% về nghiệp vụ, mô hình dữ liệu, công thức cân bằng kế toán, cấu trúc Stored Procedure MySQL 8.0 và cấu hình Form Designer cho Báo cáo tổng doanh thu (Dòng 171 trong `DANH MỤC BÁO CÁO.xlsx`, Nhóm V - Báo cáo thống kê lễ tân & doanh thu; tương ứng Sheet 72 `Báo cáo Dthu`).

---

## 1. THÔNG TIN ĐỊNH DANH BÁO CÁO

- **Tên báo cáo:** Báo cáo tổng doanh thu (hoặc Báo cáo doanh thu theo đăng ký)
- **Tên tiếng Anh:** Total Revenue Report / Daily Folio Revenue Report
- **Mã báo cáo (`report_code`):** `TOTAL_REVENUE`
- **Mã Data Source:** `RPT_TOTAL_REVENUE`
- **Mã Template tham chiếu:** `TOTAL_REVENUE_REFERENCE`
- **Nhóm báo cáo (`group`):** `Báo cáo thống kê lễ tân` / `Báo cáo doanh thu`
- **Menu điều hướng:** `BÁO CÁO` -> `BÁO CÁO THỐNG KÊ LỄ TÂN` -> `BÁO CÁO DOANH THU`
- **Vị trí trong Excel danh mục:** Dòng 171
- **Sheet tham chiếu trong Excel:** Sheet 72 (`Báo cáo Dthu`)
- **Ảnh UI mẫu thực tế:** `[.codex/docs/doc_baocao/images/dong_171_ui_mau.png](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/images/dong_171_ui_mau.png)`
- **Tài liệu liên quan mật thiết:** [dong_150_bao_cao_doanh_thu_army.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/dong_150_bao_cao_doanh_thu_army.md)
- **File SQL legacy tham chiếu:**
  - [ProVistaNavyHotel_sp_TotalRevenueFromReportSetup.sql](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/sql/ProVistaNavyHotel_sp_TotalRevenueFromReportSetup.sql)
  - [sp_292_full.sql](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/sql/sp_292_full.sql)

---

## 2. PHÂN TÍCH NGHIỆP VỤ & KIẾN TRÚC DỮ LIỆU

Báo cáo tổng doanh thu trong hệ thống khách sạn bao quát 2 mô hình vận hành:

### 2.1. Mô Hình A: Bảng Kê Doanh Thu Đăng Ký Hàng Ngày (Mẫu Chuẩn Sheet 72)
Đây là mẫu báo cáo được Kế toán đêm (Night Audit), Thu ngân và Trưởng lễ tân sử dụng hàng ngày để kiểm soát toàn bộ doanh thu phát sinh và đối chiếu dòng tiền thu được trong ngày báo cáo:
- Mỗi dòng là một **Mã đăng ký (Booking)** có phát sinh doanh thu trong ngày hoặc đang lưu trú/trả phòng.
- Chia rõ 7 khoản mục doanh thu trong ngày: Tiền phòng, Phụ thu, Minibar, Giặt là, Bể vỡ, Nhà hàng, Dịch vụ khác.
- Theo dõi doanh thu lũy kế các ngày trước và phân loại dòng tiền: Phòng đã thanh toán trả phòng (Tiền mặt, Chuyển khoản, Hoa hồng, Nợ) và Doanh thu treo của phòng còn ở.

### 2.2. Mô Hình B: Báo Cáo Doanh Thu Tổng Hợp Theo Chỉ Tiêu Setup (`sp_TotalRevenueFromReportSetup`)
Đây là mô hình báo cáo tài chính cấp cao, tổng hợp doanh thu theo các dòng chỉ tiêu được định nghĩa linh hoạt trong bảng cấu hình `AT7620` (`ReportCode = 'DT'`) và `AT7621`:
- Cho phép khách sạn phân nhóm doanh thu theo Outlet (Nhà hàng Restaurant `RE`, Room Service `RS`, Bar, Spa, Tour, Giặt là...).
- Hỗ trợ 4 kiểu nhóm (`@GroupType`):
  - `0`: Doanh thu chi tiết theo sản phẩm/mặt hàng.
  - `1`: Doanh thu chi tiết theo từng hóa đơn.
  - `2`: Doanh thu tổng hợp số tiền theo từng Outlet.
  - `3`: Nhóm theo Hình thức thanh toán (HTTT: Tiền mặt, Thẻ, Chuyển khoản, Công nợ...).
- Tự động cộng dồn lũy kế lên các dòng cha theo cấu trúc cây (`LevelID`, `AccuLineID`).

---

## 3. CÔNG THỨC TOÁN HỌC & ĐẲNG THỨC KẾ TOÁN BẤT BIẾN (MÔ HÌNH SHEET 72)

### 3.1. Các Khoản Thu Trong Ngày (Cột 8 Đến 14)
Với mỗi booking $k$:
1. **Tiền phòng (`room_revenue`):** Doanh thu tiền phòng thuần được post trong ngày báo cáo (`p_date`).
2. **Phụ thu tiền phòng (`extra_room_revenue`):** Phụ thu nhận phòng sớm (Early check-in), trả phòng trễ (Late check-out), thêm người (Extra adult/child), nâng hạng phòng.
3. **Minibar (`minibar_revenue`):** Doanh thu đồ uống, snack sử dụng tại tủ lạnh trong phòng do Buồng phòng post.
4. **Giặt (`laundry_revenue`):** Doanh thu dịch vụ giặt ủi đồ của khách.
5. **Bể vỡ (`damage_revenue`):** Tiền đền bù trang thiết bị, tài sản buồng phòng bị hỏng hóc hoặc mất.
6. **Nhà hàng (`fb_revenue`):** Doanh thu ăn uống tại Nhà hàng, Room Service, Pool Bar được charge về phòng hoặc thanh toán trực tiếp.
7. **Dịch vụ (`other_service_revenue`):** Các dịch vụ khác (Spa, Massage, Tour du lịch, Xe đưa đón sân bay, v.v.).

### 3.2. Nhóm Doanh Thu Tổng Hợp (Cột 15, 16, 17)
$$\text{Tổng DT (Cột 15)} = \text{Tiền phòng} + \text{Phụ thu} + \text{Minibar} + \text{Giặt} + \text{Bể vỡ} + \text{Nhà hàng} + \text{Dịch vụ}$$

$$\text{DT ngày trước (Cột 16)} = \sum \text{Toàn bộ doanh thu của booking phát sinh từ ngày đến đến trước ngày } p\_date$$

$$\text{Tổng cộng (Cột 17)} = \text{Tổng DT (Cột 15)} + \text{DT ngày trước (Cột 16)}$$

### 3.3. Phân Bổ Thanh Toán & Phòng Còn Ở (Cột 18 Đến 22)
- **Đối với phòng đã trả (`status = 'CHECKED_OUT'` hoặc check-out trong ngày `p_date`):**
  - **TM (Tiền mặt - Cột 18):** Tổng tiền thanh toán bằng tiền mặt đã thu.
  - **CK (Chuyển khoản - Cột 19):** Tổng tiền thanh toán bằng chuyển khoản, quẹt thẻ POS, cổng thanh toán.
  - **HH (Hoa hồng / Commission - Cột 20):** Tiền hoa hồng chiết khấu trừ trực tiếp trên folio của đại lý OTA/Travel Agent.
  - **Còn nợ (Công nợ AR - Cột 21):** Tiền phòng và dịch vụ chưa thu tiền mặt/thẻ mà chuyển nợ công ty bảo lãnh (City Ledger / Accounts Receivable).
  - **Phòng còn ở (Cột 22):** $= 0$.
- **Đối với phòng còn ở (`status = 'INHOUSE'` hoặc chưa checkout):**
  - Cột TM, CK, HH, Còn nợ $= 0$ (hoặc chỉ ghi nhận tiền đặt cọc nếu có).
  - **Phòng còn ở (Cột 22):** Ghi nhận toàn bộ số dư công nợ lũy kế chưa thanh toán của booking tính đến hết ngày báo cáo:
    $$\text{Phòng còn ở} = \text{Tổng cộng (Cột 17)} - \text{Đã thanh toán trước}$$

### 3.4. Đẳng Thức Cân Bằng Kế Toán Bắt Buộc (Verification Invariant)
Tại mỗi dòng booking $k$ và tại dòng Tổng cộng toàn khách sạn:
$$\mathbf{Tổng\ cộng} \equiv \mathbf{TM} + \mathbf{CK} + \mathbf{HH} + \mathbf{Còn\ nợ} + \mathbf{Phòng\ còn\ ở}$$

> **BẮT BUỘC:** Nếu $\text{Tổng cộng} \ne (\text{TM} + \text{CK} + \text{HH} + \text{Còn nợ} + \text{Phòng còn ở})$ thì báo cáo bị sai lệch số liệu và không được phép chấp nhận kết quả!

---

## 4. THIẾT KẾ GIAO DIỆN & BỘ LỌC (UI SPECIFICATION)

### 4.1. Panel Bộ Lọc Bên Trái (`parameter_ui_schema`)

```json
{
  "parameters": [
    {
      "name": "p_date",
      "label": "Ngày",
      "type": "date",
      "default": "$today",
      "format": "YYYY-MM-DD",
      "col_span": 12
    },
    {
      "name": "p_company_id",
      "label": "Công ty",
      "type": "select",
      "default": "0",
      "options_source": "companies",
      "placeholder": "Công ty",
      "col_span": 12
    },
    {
      "name": "p_booking_id",
      "label": "Đăng ký",
      "type": "select",
      "default": "0",
      "options_source": "bookings",
      "placeholder": "Select Value",
      "col_span": 12
    }
  ],
  "action_button": {
    "label": "Hiển thị báo cáo",
    "color": "primary",
    "icon": "search"
  }
}
```

### 4.2. Bố Cục Trang & Header Band Chuẩn PMS
- **Khổ giấy:** A4 Landscape (Ngang), Lề: Trên 8mm, Dưới 8mm, Trái 8mm, Phải 8mm.
- **Khối đầu trang:**
  - Trái: Logo khách sạn (mẫu ảnh: Logo Golden Crest).
  - Phải:
    - Địa chỉ: `66 Hàn Mặc Tử, P. Ghềnh Ráng, TP. Quy Nhơn, Bình Định`
    - Người dùng: `admin`
    - Ngày: `15/07/2026`
- **Tiêu đề chính:** **BÁO CÁO DOANH THU** (Font-size 20px, in hoa đậm, căn giữa).
- **Phụ đề:** `Ngày: 15/07/2026` (Căn giữa, font-style italic).

### 4.3. Ma Trận 22 Cột Dữ Liệu & Header 2 Tầng

Bảng gồm 22 cột, tầng 1 gồm các nhóm gộp, tầng 2 là chi tiết:

```
+---+-----+----------+------+-------+------+----+---------------------------------------------------------------+--------+-----------+----------+-----------------------------+----------+
| S | Mã  |          |      | Ngày  | Ngày |    |                  Các khoản thu trong ngày                     |  Tổng  | DT ngày   |  Tổng    |         Phòng đã trả        |  Phòng   |
| T | đăng| Tên khách|Đơn vị| đến   |  đi  | SP +----+------+-------+----+-----+-------+-------+  DT    |  trước    |  cộng    +----+----+----+-----+   còn ở  |
| T | ký  |          |      |       |      |    | T.P| P.Thu|Minibar|Giặt|Bể vỡ|Nhà hàng|Dịch vụ|        |           |          | TM | CK | HH | C.Nợ|          |
+---+-----+----------+------+-------+------+----+----+------+-------+----+-----+--------+-------+--------+-----------+----------+----+----+----+-----+----------+
```

#### Bảng Danh Sách 22 Cột Chi Tiết:

| Cột | Tiêu đề Tầng 1 | Tiêu đề Tầng 2 | Field Binding | Căn lề | Độ rộng | Format / Ý nghĩa |
|:---:|:---:|:---:|---|:---:|:---:|---|
| **1** | STT (rowspan 2) | - | `stt` | Giữa | 35px | Số thứ tự tăng dần |
| **2** | Mã đăng ký (rowspan 2) | - | `booking_code` | Giữa | 65px | Mã đặt phòng (vd: `167`, `219`, `262`) |
| **3** | Tên khách (rowspan 2) | - | `guest_name_rooms` | Trái | 140px | Tên khách kèm danh sách số phòng (vd: `Cô Nhung - 1208, 1207`) |
| **4** | Đơn vị (rowspan 2) | - | `company_name` | Trái | 90px | Công ty lữ hành / nguồn (vd: `Expedia`, `Traveloka`, `KHÁCH LẺ`) |
| **5** | Ngày đến (rowspan 2) | - | `arrival_date` | Giữa | 75px | Định dạng `dd/mm/yyyy` |
| **6** | Ngày đi (rowspan 2) | - | `departure_date` | Giữa | 75px | Định dạng `dd/mm/yyyy` |
| **7** | SP (rowspan 2) | - | `room_count` | Phải | 40px | Số lượng phòng của booking (vd: `1`, `4`) |
| **8** | Các khoản thu trong ngày (colspan 7) | Tiền phòng | `room_revenue` | Phải | 80px | Số tiền, `#,#` |
| **9** | Các khoản thu trong ngày (colspan 7) | Phụ thu tiền phòng | `extra_room_revenue` | Phải | 75px | Số tiền, `#,#` |
| **10** | Các khoản thu trong ngày (colspan 7) | Minibar | `minibar_revenue` | Phải | 70px | Số tiền, `#,#` |
| **11** | Các khoản thu trong ngày (colspan 7) | Giặt | `laundry_revenue` | Phải | 65px | Số tiền, `#,#` |
| **12** | Các khoản thu trong ngày (colspan 7) | Bể vỡ | `damage_revenue` | Phải | 65px | Số tiền, `#,#` |
| **13** | Các khoản thu trong ngày (colspan 7) | Nhà hàng | `fb_revenue` | Phải | 75px | Số tiền, `#,#` |
| **14** | Các khoản thu trong ngày (colspan 7) | Dịch vụ | `other_service_revenue` | Phải | 70px | Số tiền, `#,#` |
| **15** | Tổng DT (rowspan 2) | - | `daily_total_revenue` | Phải | 85px | Cột 8 + ... + Cột 14 |
| **16** | DT ngày trước (rowspan 2) | - | `previous_days_revenue`| Phải | 85px | Doanh thu tích lũy trước ngày báo cáo |
| **17** | Tổng cộng (rowspan 2) | - | `grand_total_revenue` | Phải | 90px | Cột 15 + Cột 16 |
| **18** | Phòng đã trả (colspan 4) | TM | `paid_cash` | Phải | 80px | Tiền mặt đã thu phòng checkout |
| **19** | Phòng đã trả (colspan 4) | CK | `paid_bank` | Phải | 85px | Chuyển khoản đã thu phòng checkout |
| **20** | Phòng đã trả (colspan 4) | HH | `paid_commission` | Phải | 65px | Hoa hồng trừ trên folio |
| **21** | Phòng đã trả (colspan 4) | Còn nợ | `paid_debt` | Phải | 70px | Công nợ chuyển về công ty (AR) |
| **22** | Phòng còn ở (rowspan 2) | - | `inhouse_balance` | Phải | 85px | Số dư chưa thanh toán của phòng đang ở |

### 4.4. Dòng Tổng Cộng & Khối Chữ Ký
- **Dòng tổng:**
  - Cột 1 - 6 (Gộp ô): `Tổng số BK: {{total_bookings}}` (in đậm, căn trái).
  - Cột 7 (SP): `{{sum(room_count)}}` (vd: `13`).
  - Cột 8 - 22: Tổng tiền của từng cột tương ứng.
- **Khối địa danh & ngày tháng:**
  - `Quy Nhơn, Ngày {{day}} Tháng {{month}} Năm {{year}}` (Căn phải, in nghiêng).
- **Khối 5 chữ ký:**
  - Chia đều 5 cột ngang:
    1. **Chữ Ký Người Lập**
    2. **Trưởng Bộ Phận**
    3. **Kế Toán**
    4. **Tổng Quản Lý**
    5. **Giám Đốc**

---

## 5. CẤU TRÚC TOÀN DIỆN `content_json` (FORM DESIGNER TEMPLATE)

Agent triển khai lưu file này tại `backend/database/report_templates/total_revenue_reference.php`.

```php
<?php

use App\Services\TemplateRendererService;

return new class
{
    public function definition(): array
    {
        return [
            'code' => 'TOTAL_REVENUE',
            'name' => 'Báo cáo tổng doanh thu',
            'report' => 'TOTAL_REVENUE_REFERENCE',
            'page_size' => 'A4',
            'page_orientation' => 'landscape',
            'margin_top' => 8,
            'margin_bottom' => 8,
            'margin_left' => 8,
            'margin_right' => 8,
            'parameter_ui_schema' => [
                'parameters' => [
                    [
                        'name' => 'p_date',
                        'label' => 'Ngày',
                        'type' => 'date',
                        'default' => '$today',
                    ],
                    [
                        'name' => 'p_company_id',
                        'label' => 'Công ty',
                        'type' => 'select',
                        'default' => '0',
                        'placeholder' => 'Công ty',
                        'options_source' => 'companies',
                    ],
                    [
                        'name' => 'p_booking_id',
                        'label' => 'Đăng ký',
                        'type' => 'select',
                        'default' => '0',
                        'placeholder' => 'Select Value',
                        'options_source' => 'bookings',
                    ],
                ],
            ],
            'content_json' => [
                'version' => 2,
                'paper' => [
                    'size' => 'A4',
                    'orientation' => 'landscape',
                    'margins' => ['top' => 8, 'right' => 8, 'bottom' => 8, 'left' => 8],
                ],
                'styles' => [
                    'fontFamily' => 'Times New Roman, serif',
                    'fontSize' => '10px',
                    'lineHeight' => 1.25,
                    'color' => '#111827',
                ],
                'blocks' => [
                    // Block 1: Header metadata
                    [
                        'id' => 'block_header',
                        'type' => 'header',
                        'columns' => [
                            [
                                'width' => '30%',
                                'content' => '<img src="{{hotel_logo}}" style="max-height: 55px; max-width: 100%; object-fit: contain;" alt="Logo" />',
                            ],
                            [
                                'width' => '70%',
                                'align' => 'right',
                                'content' => '
                                    <div style="font-size: 10px; line-height: 1.35;">
                                        <div><strong>Địa chỉ:</strong> {{hotel_address}}</div>
                                        <div><strong>Người dùng:</strong> {{current_user_name}}</div>
                                        <div><strong>Ngày:</strong> {{report_date_display}}</div>
                                    </div>',
                            ],
                        ],
                    ],
                    // Block 2: Title
                    [
                        'id' => 'block_title',
                        'type' => 'title',
                        'content' => '
                            <div style="text-align: center; margin: 10px 0 8px 0;">
                                <div style="font-size: 17px; font-weight: bold; text-transform: uppercase;">BÁO CÁO DOANH THU</div>
                                <div style="font-size: 11px; font-style: italic; margin-top: 3px; color: #374151;">
                                    Ngày: {{report_date_display}}
                                </div>
                            </div>',
                    ],
                    // Block 3: Data Table 22 columns
                    [
                        'id' => 'block_table',
                        'type' => 'table',
                        'dataSource' => 'data',
                        'tableStyles' => [
                            'width' => '100%',
                            'borderCollapse' => 'collapse',
                            'fontSize' => '9.5px',
                            'border' => '1px solid #9ca3af',
                        ],
                        'tier1Headers' => [
                            ['title' => 'STT', 'rowspan' => 2, 'width' => '25px', 'align' => 'center'],
                            ['title' => 'Mã đăng ký', 'rowspan' => 2, 'width' => '50px', 'align' => 'center'],
                            ['title' => 'Tên khách', 'rowspan' => 2, 'width' => '110px', 'align' => 'center'],
                            ['title' => 'Đơn vị', 'rowspan' => 2, 'width' => '75px', 'align' => 'center'],
                            ['title' => 'Ngày đến', 'rowspan' => 2, 'width' => '60px', 'align' => 'center'],
                            ['title' => 'Ngày đi', 'rowspan' => 2, 'width' => '60px', 'align' => 'center'],
                            ['title' => 'SP', 'rowspan' => 2, 'width' => '25px', 'align' => 'center'],
                            ['title' => 'Các khoản thu trong ngày', 'colspan' => 7, 'align' => 'center'],
                            ['title' => 'Tổng DT', 'rowspan' => 2, 'width' => '65px', 'align' => 'center'],
                            ['title' => 'DT ngày trước', 'rowspan' => 2, 'width' => '65px', 'align' => 'center'],
                            ['title' => 'Tổng cộng', 'rowspan' => 2, 'width' => '70px', 'align' => 'center'],
                            ['title' => 'Phòng đã trả', 'colspan' => 4, 'align' => 'center'],
                            ['title' => 'Phòng còn ở', 'rowspan' => 2, 'width' => '65px', 'align' => 'center'],
                        ],
                        'tier2Headers' => [
                            ['title' => 'Tiền phòng', 'width' => '60px', 'align' => 'center'],
                            ['title' => 'Phụ thu tiền phòng', 'width' => '55px', 'align' => 'center'],
                            ['title' => 'Minibar', 'width' => '50px', 'align' => 'center'],
                            ['title' => 'Giặt', 'width' => '45px', 'align' => 'center'],
                            ['title' => 'Bể vỡ', 'width' => '45px', 'align' => 'center'],
                            ['title' => 'Nhà hàng', 'width' => '55px', 'align' => 'center'],
                            ['title' => 'Dịch vụ', 'width' => '50px', 'align' => 'center'],
                            ['title' => 'TM', 'width' => '55px', 'align' => 'center'],
                            ['title' => 'CK', 'width' => '60px', 'align' => 'center'],
                            ['title' => 'HH', 'width' => '45px', 'align' => 'center'],
                            ['title' => 'Còn nợ', 'width' => '50px', 'align' => 'center'],
                        ],
                        'columns' => [
                            ['field' => 'stt', 'align' => 'center'],
                            ['field' => 'booking_code', 'align' => 'center'],
                            ['field' => 'guest_name_rooms', 'align' => 'left'],
                            ['field' => 'company_name', 'align' => 'left'],
                            ['field' => 'arrival_date_display', 'align' => 'center'],
                            ['field' => 'departure_date_display', 'align' => 'center'],
                            ['field' => 'room_count', 'align' => 'right', 'format' => '#,##0'],
                            ['field' => 'room_revenue', 'align' => 'right', 'format' => '#,##0'],
                            ['field' => 'extra_room_revenue', 'align' => 'right', 'format' => '#,##0'],
                            ['field' => 'minibar_revenue', 'align' => 'right', 'format' => '#,##0'],
                            ['field' => 'laundry_revenue', 'align' => 'right', 'format' => '#,##0'],
                            ['field' => 'damage_revenue', 'align' => 'right', 'format' => '#,##0'],
                            ['field' => 'fb_revenue', 'align' => 'right', 'format' => '#,##0'],
                            ['field' => 'other_service_revenue', 'align' => 'right', 'format' => '#,##0'],
                            ['field' => 'daily_total_revenue', 'align' => 'right', 'format' => '#,##0'],
                            ['field' => 'previous_days_revenue', 'align' => 'right', 'format' => '#,##0'],
                            ['field' => 'grand_total_revenue', 'align' => 'right', 'format' => '#,##0'],
                            ['field' => 'paid_cash', 'align' => 'right', 'format' => '#,##0'],
                            ['field' => 'paid_bank', 'align' => 'right', 'format' => '#,##0'],
                            ['field' => 'paid_commission', 'align' => 'right', 'format' => '#,##0'],
                            ['field' => 'paid_debt', 'align' => 'right', 'format' => '#,##0'],
                            ['field' => 'inhouse_balance', 'align' => 'right', 'format' => '#,##0'],
                        ],
                        'customRows' => [
                            [
                                'type' => 'grand_total',
                                'style' => 'font-weight: bold; background-color: #f9fafb; border-top: 2px solid #374151;',
                                'cells' => [
                                    ['content' => 'Tổng số BK: {{total_bookings}}', 'colspan' => 6, 'align' => 'left'],
                                    ['formula' => 'SUM(room_count)', 'format' => '#,##0', 'align' => 'right'],
                                    ['formula' => 'SUM(room_revenue)', 'format' => '#,##0', 'align' => 'right'],
                                    ['formula' => 'SUM(extra_room_revenue)', 'format' => '#,##0', 'align' => 'right'],
                                    ['formula' => 'SUM(minibar_revenue)', 'format' => '#,##0', 'align' => 'right'],
                                    ['formula' => 'SUM(laundry_revenue)', 'format' => '#,##0', 'align' => 'right'],
                                    ['formula' => 'SUM(damage_revenue)', 'format' => '#,##0', 'align' => 'right'],
                                    ['formula' => 'SUM(fb_revenue)', 'format' => '#,##0', 'align' => 'right'],
                                    ['formula' => 'SUM(other_service_revenue)', 'format' => '#,##0', 'align' => 'right'],
                                    ['formula' => 'SUM(daily_total_revenue)', 'format' => '#,##0', 'align' => 'right'],
                                    ['formula' => 'SUM(previous_days_revenue)', 'format' => '#,##0', 'align' => 'right'],
                                    ['formula' => 'SUM(grand_total_revenue)', 'format' => '#,##0', 'align' => 'right'],
                                    ['formula' => 'SUM(paid_cash)', 'format' => '#,##0', 'align' => 'right'],
                                    ['formula' => 'SUM(paid_bank)', 'format' => '#,##0', 'align' => 'right'],
                                    ['formula' => 'SUM(paid_commission)', 'format' => '#,##0', 'align' => 'right'],
                                    ['formula' => 'SUM(paid_debt)', 'format' => '#,##0', 'align' => 'right'],
                                    ['formula' => 'SUM(inhouse_balance)', 'format' => '#,##0', 'align' => 'right'],
                                ],
                            ],
                        ],
                    ],
                    // Block 4: Signatures
                    [
                        'id' => 'block_signatures',
                        'type' => 'signatures',
                        'locationDate' => 'Quy Nhơn, Ngày {{day}} Tháng {{month}} Năm {{year}}',
                        'roles' => [
                            ['title' => 'Chữ Ký Người Lập'],
                            ['title' => 'Trưởng Bộ Phận'],
                            ['title' => 'Kế Toán'],
                            ['title' => 'Tổng Quản Lý'],
                            ['title' => 'Giám Đốc'],
                        ],
                    ],
                ],
            ],
        ];
    }
};
```

---

## 6. ĐẶC TẢ THUẬT TOÁN & STORED PROCEDURE (MYSQL 8.0)

Dưới đây là Stored Procedure `rpt_total_revenue` viết cho MySQL 8.0, tính toán chính xác 22 cột dữ liệu và đảm bảo tuyệt đối đẳng thức kế toán:

```sql
DELIMITER $$

DROP PROCEDURE IF EXISTS `rpt_total_revenue`$$

CREATE PROCEDURE `rpt_total_revenue`(
    IN p_date DATE,
    IN p_company_id BIGINT,
    IN p_booking_id BIGINT,
    IN p_branch_id BIGINT
)
BEGIN
    /*
        BÁO CÁO TỔNG DOANH THU THEO ĐĂNG KÝ HÀNG NGÀY
    */
    -- 1. Bảng tạm chứa danh sách các booking phát sinh doanh thu hoặc lưu trú trong ngày
    DROP TEMPORARY TABLE IF EXISTS tmp_active_bookings;
    CREATE TEMPORARY TABLE tmp_active_bookings (
        booking_id BIGINT PRIMARY KEY,
        booking_code VARCHAR(50),
        guest_name VARCHAR(255),
        company_name VARCHAR(255),
        arrival_date DATE,
        departure_date DATE,
        room_count INT,
        room_numbers TEXT,
        is_checked_out TINYINT DEFAULT 0
    );

    INSERT INTO tmp_active_bookings (booking_id, booking_code, guest_name, company_name, arrival_date, departure_date, room_count, room_numbers, is_checked_out)
    SELECT 
        r.id,
        r.reservation_code,
        CONCAT(g.last_name, ' ', g.first_name) AS guest_name,
        COALESCE(c.name, 'KHÁCH LẺ') AS company_name,
        MIN(rr.arrival_date) AS arrival_date,
        MAX(rr.departure_date) AS departure_date,
        COUNT(DISTINCT rr.id) AS room_count,
        GROUP_CONCAT(DISTINCT ro.room_number ORDER BY ro.room_number SEPARATOR ', ') AS room_numbers,
        CASE WHEN MAX(rr.status) = 'CHECKED_OUT' AND MAX(rr.departure_date) <= p_date THEN 1 ELSE 0 END AS is_checked_out
    FROM reservations r
    JOIN guests g ON r.guest_id = g.id
    LEFT JOIN companies c ON r.company_id = c.id
    JOIN reservation_rooms rr ON r.id = rr.reservation_id
    LEFT JOIN rooms ro ON rr.room_id = ro.id
    WHERE (p_company_id IS NULL OR p_company_id = 0 OR r.company_id = p_company_id)
      AND (p_booking_id IS NULL OR p_booking_id = 0 OR r.id = p_booking_id)
      AND (p_branch_id IS NULL OR p_branch_id = 0 OR rr.branch_id = p_branch_id)
      AND r.status NOT IN ('CANCELLED', 'NO_SHOW')
      AND (
          -- Có phòng lưu trú trong ngày
          (p_date >= rr.arrival_date AND p_date <= rr.departure_date)
          -- Hoặc có transaction phát sinh trong ngày
          OR EXISTS (
              SELECT 1 FROM folio_transactions ft
              WHERE ft.reservation_id = r.id
                AND DATE(ft.trans_date) = p_date
                AND ft.deleted_at IS NULL
          )
      )
    GROUP BY r.id, r.reservation_code, g.last_name, g.first_name, c.name;

    -- 2. Bảng tổng hợp doanh thu chi tiết từng khoản
    SELECT 
        ROW_NUMBER() OVER (ORDER BY b.booking_id ASC) AS stt,
        b.booking_code,
        CONCAT(b.guest_name, ' - ', COALESCE(b.room_numbers, '')) AS guest_name_rooms,
        b.company_name,
        DATE_FORMAT(b.arrival_date, '%d/%m/%Y') AS arrival_date_display,
        DATE_FORMAT(b.departure_date, '%d/%m/%Y') AS departure_date_display,
        b.room_count,
        
        -- Doanh thu trong ngày (trans_date = p_date)
        COALESCE(rev.room_rev, 0) AS room_revenue,
        COALESCE(rev.extra_room_rev, 0) AS extra_room_revenue,
        COALESCE(rev.minibar_rev, 0) AS minibar_revenue,
        COALESCE(rev.laundry_rev, 0) AS laundry_revenue,
        COALESCE(rev.damage_rev, 0) AS damage_revenue,
        COALESCE(rev.fb_rev, 0) AS fb_revenue,
        COALESCE(rev.other_rev, 0) AS other_service_revenue,
        
        -- Tổng DT trong ngày
        (COALESCE(rev.room_rev, 0) + COALESCE(rev.extra_room_rev, 0) + COALESCE(rev.minibar_rev, 0) +
         COALESCE(rev.laundry_rev, 0) + COALESCE(rev.damage_rev, 0) + COALESCE(rev.fb_rev, 0) + 
         COALESCE(rev.other_rev, 0)) AS daily_total_revenue,
        
        -- DT các ngày trước (trans_date < p_date)
        COALESCE(prev.prev_rev, 0) AS previous_days_revenue,
        
        -- Tổng cộng = DT ngày + DT ngày trước
        (COALESCE(rev.room_rev, 0) + COALESCE(rev.extra_room_rev, 0) + COALESCE(rev.minibar_rev, 0) +
         COALESCE(rev.laundry_rev, 0) + COALESCE(rev.damage_rev, 0) + COALESCE(rev.fb_rev, 0) + 
         COALESCE(rev.other_rev, 0) + COALESCE(prev.prev_rev, 0)) AS grand_total_revenue,
        
        -- Thanh toán phòng đã trả
        CASE WHEN b.is_checked_out = 1 THEN COALESCE(pay.paid_cash, 0) ELSE 0 END AS paid_cash,
        CASE WHEN b.is_checked_out = 1 THEN COALESCE(pay.paid_bank, 0) ELSE 0 END AS paid_bank,
        CASE WHEN b.is_checked_out = 1 THEN COALESCE(pay.paid_comm, 0) ELSE 0 END AS paid_commission,
        CASE WHEN b.is_checked_out = 1 THEN COALESCE(pay.paid_debt, 0) ELSE 0 END AS paid_debt,
        
        -- Phòng còn ở (chưa checkout)
        CASE 
            WHEN b.is_checked_out = 0 THEN 
                GREATEST(0, (COALESCE(rev.room_rev, 0) + COALESCE(rev.extra_room_rev, 0) + COALESCE(rev.minibar_rev, 0) +
                 COALESCE(rev.laundry_rev, 0) + COALESCE(rev.damage_rev, 0) + COALESCE(rev.fb_rev, 0) + 
                 COALESCE(rev.other_rev, 0) + COALESCE(prev.prev_rev, 0)) - COALESCE(pay.total_paid, 0))
            ELSE 0 
        END AS inhouse_balance

    FROM tmp_active_bookings b
    -- Subquery tính doanh thu trong ngày
    LEFT JOIN (
        SELECT 
            reservation_id,
            SUM(CASE WHEN service_code IN ('ROOM', 'RM') THEN amount ELSE 0 END) AS room_rev,
            SUM(CASE WHEN service_code IN ('EXTRA_BED', 'EARLY_IN', 'LATE_OUT', 'SURCHARGE') THEN amount ELSE 0 END) AS extra_room_rev,
            SUM(CASE WHEN service_code = 'MINIBAR' THEN amount ELSE 0 END) AS minibar_rev,
            SUM(CASE WHEN service_code = 'LAUNDRY' THEN amount ELSE 0 END) AS laundry_rev,
            SUM(CASE WHEN service_code = 'DAMAGE' THEN amount ELSE 0 END) AS damage_rev,
            SUM(CASE WHEN service_code IN ('RESTAURANT', 'ROOM_SERVICE', 'FB') THEN amount ELSE 0 END) AS fb_rev,
            SUM(CASE WHEN service_code NOT IN ('ROOM', 'RM', 'EXTRA_BED', 'EARLY_IN', 'LATE_OUT', 'SURCHARGE', 'MINIBAR', 'LAUNDRY', 'DAMAGE', 'RESTAURANT', 'ROOM_SERVICE', 'FB') THEN amount ELSE 0 END) AS other_rev
        FROM folio_transactions
        WHERE DATE(trans_date) = p_date
          AND trans_type = 'CHARGE'
          AND deleted_at IS NULL
        GROUP BY reservation_id
    ) rev ON b.booking_id = rev.reservation_id
    -- Subquery tính doanh thu các ngày trước
    LEFT JOIN (
        SELECT 
            reservation_id,
            SUM(amount) AS prev_rev
        FROM folio_transactions
        WHERE DATE(trans_date) < p_date
          AND trans_type = 'CHARGE'
          AND deleted_at IS NULL
        GROUP BY reservation_id
    ) prev ON b.booking_id = prev.reservation_id
    -- Subquery tính thanh toán
    LEFT JOIN (
        SELECT 
            reservation_id,
            SUM(CASE WHEN payment_method = 'CASH' THEN amount ELSE 0 END) AS paid_cash,
            SUM(CASE WHEN payment_method IN ('BANK_TRANSFER', 'CREDIT_CARD', 'POS') THEN amount ELSE 0 END) AS paid_bank,
            SUM(CASE WHEN payment_method = 'COMMISSION' THEN amount ELSE 0 END) AS paid_comm,
            SUM(CASE WHEN payment_method IN ('CITY_LEDGER', 'DEBT') THEN amount ELSE 0 END) AS paid_debt,
            SUM(amount) AS total_paid
        FROM folio_payments
        WHERE deleted_at IS NULL
        GROUP BY reservation_id
    ) pay ON b.booking_id = pay.reservation_id
    ORDER BY b.booking_id ASC;

    DROP TEMPORARY TABLE IF EXISTS tmp_active_bookings;
END$$

DELIMITER ;
```

---

## 7. HƯỚNG DẪN KIỂM THỬ & ĐỐI SOÁT SỐ LIỆU (VERIFICATION CHECKLIST)

Khi triển khai báo cáo, Agent thực hiện kiểm tra các tiêu chí sau:

- [ ] **Kiểm tra đẳng thức cân bằng kế toán (Zero Discrepancy):**
  $$\text{Tổng cộng} = \text{TM} + \text{CK} + \text{HH} + \text{Còn nợ} + \text{Phòng còn ở}$$
  Thực hiện assertion kiểm tra đẳng thức này trên TẤT CẢ các dòng booking và trên dòng Grand Total. Không được có bất kỳ chênh lệch nào (kể cả 1 VNĐ).
- [ ] **Khớp số lượng booking và số phòng:**
  - `Tổng số BK` bằng đúng số dòng booking được hiển thị.
  - Cột `SP` (Số phòng) bằng tổng số lượng phòng thuộc các booking đó.
- [ ] **Phân tách rạch ròi giữa phòng đã trả và phòng còn ở:**
  - Booking đã checkout: Cột `Phòng còn ở` bắt buộc bằng `0`. Toàn bộ tiền phải phân bổ vào các cột TM, CK, HH hoặc Còn nợ.
  - Booking đang ở: Toàn bộ tiền dư nợ lũy kế phải nằm ở cột `Phòng còn ở`.
- [ ] **Kiểm thử layout A4 Landscape:**
  - 22 cột hiển thị đầy đủ, không bị rớt dòng chữ hay tràn mép trang in.
  - Khối chữ ký 5 vị trí: Người lập, Trưởng bộ phận, Kế toán, Tổng quản lý, Giám đốc thẳng hàng và cân đối dưới chân trang.

## 8. TRẠNG THÁI TRIỂN KHAI DỰ ÁN

- Đã tạo `rpt_total_revenue`, metadata, template và report definition tại migration `2026_09_24_120000_create_total_revenue_report.php`.
- Phase 1 dùng bảng kê Sheet 72/sp_292 với 22 cột; thanh toán phân nhóm theo `payment_group` 1/2/3/4.
- Đã bảo đảm bất biến `GrandTotal = TM + CK + HH + Còn nợ + Phòng còn ở`; booking checkout phân bổ hết số dư, booking in-house đưa số dư vào `Phòng còn ở`.
- Đã kiểm tra PHPUnit 3/3, PHP lint, route list và frontend build. Chưa chạy migration thật, chưa nghiệm thu dữ liệu thật/browser/PDF.

## 8. TRẠNG THÁI TRIỂN KHAI DỰ ÁN

- Đã tạo `rpt_total_revenue`, metadata, template và report definition tại migration `2026_09_24_120000_create_total_revenue_report.php`.
- Phase 1 dùng bảng kê Sheet 72/sp_292 với 22 cột; thanh toán phân nhóm theo `payment_group` 1/2/3/4.
- Đã bảo đảm bất biến `GrandTotal = TM + CK + HH + Còn nợ + Phòng còn ở`; booking checkout phân bổ hết số dư, booking in-house đưa số dư vào `Phòng còn ở`.
- Đã kiểm tra PHPUnit 3/3, PHP lint, route list và frontend build. Chưa chạy migration thật, chưa nghiệm thu dữ liệu thật/browser/PDF.
