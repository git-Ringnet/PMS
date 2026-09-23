# TÀI LIỆU ĐẶC TẢ CHI TIẾT - DÒNG 170: BÁO CÁO PHÒNG HÀNG TUẦN

> **Dành cho Agent triển khai:** Tài liệu này đặc tả chi tiết 100% về nghiệp vụ, mô hình dữ liệu, công thức toán học, cấu trúc Stored Procedure MySQL 8.0 và cấu hình Form Designer cho Báo cáo phòng hàng tuần (Dòng 170 trong `DANH MỤC BÁO CÁO.xlsx`, STT 5.0 thuộc Nhóm V - Báo cáo liên quan công suất; tương ứng Sheet 45 `BC phòng hàng tuần`).

---

## 1. THÔNG TIN ĐỊNH DANH BÁO CÁO

- **Tên báo cáo:** Báo cáo phòng hàng tuần
- **Tên tiếng Anh:** Weekly Room Report
- **Mã báo cáo (`report_code`):** `WEEKLY_ROOM_REPORT`
- **Mã Data Source:** `RPT_WEEKLY_ROOM_REPORT`
- **Mã Template tham chiếu:** `WEEKLY_ROOM_REPORT_REFERENCE`
- **Nhóm báo cáo (`group`):** `Báo cáo phòng`
- **Menu điều hướng:** `BÁO CÁO` -> `BÁO CÁO PHÒNG` -> `BÁO CÁO PHÒNG HÀNG TUẦN`
- **Vị trí trong Excel danh mục:** Dòng 170 (STT 5.0)
- **Sheet tham chiếu trong Excel:** Sheet 45 (`BC phòng hàng tuần`)
- **Ảnh UI mẫu thực tế:** `[.codex/docs/doc_baocao/images/dong_170_ui_mau.png](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/images/dong_170_ui_mau.png)`
- **File SQL legacy tham chiếu:**
  - [ProVistaNavyHotel_sp_023_Division.sql](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/sql/ProVistaNavyHotel_sp_023_Division.sql)
  - [ProVistaNavyHotel_sp_023.sql](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/sql/ProVistaNavyHotel_sp_023.sql)

---

## 2. PHÂN TÍCH NGHIỆP VỤ & BẢN CHẤT TOÁN HỌC

### 2.1. Bản Chất Nghiệp Vụ
- Báo cáo phòng hàng tuần là báo cáo tổng hợp tình hình hoạt động buồng phòng trong 1 tuần (7 ngày liên tiếp từ Thứ Hai đến Chủ Nhật).
- Báo cáo tập trung vào 4 chỉ số cốt lõi của khách sạn mỗi ngày:
  1. **ĐẾN (Arrivals):** Số phòng và số khách làm thủ tục check-in trong ngày.
  2. **ĐI (Departures):** Số phòng và số khách làm thủ tục check-out trong ngày.
  3. **Ở (In-House / Stay-Over):** Số phòng và số khách đang lưu trú qua đêm trong ngày.
  4. **CÔNG SUẤT (Occupancy %):** Tỷ lệ lấp đầy phòng dựa trên số phòng lưu trú và số phòng có thể bán thực tế của ngày đó.

### 2.2. Mối Liên Hệ Toán Học & Đối Soát Chéo Với Dòng 169 (`ROOM_FORECAST`)
- Dòng 170 (`WEEKLY_ROOM_REPORT`) chính là bản rút gọn và định dạng theo tuần của Dòng 169 (`ROOM_FORECAST` / `sp_023`).
- Trong hệ thống legacy: Store `sp_023_Division` nhận tham số `@FromDate`, `@ToDate` và gọi trực tiếp `sp_023 @FromDate, @ToDate, '', @BF`.
- **Quy tắc khớp số liệu 100%:**
  - `ĐẾN - Phòng` (Dòng 170) $\equiv$ `P.Đến (ArrRooms)` (Dòng 169).
  - `ĐẾN - Khách` (Dòng 170) $\equiv$ `K.Đến (ArrAdult + ArrChild)` (Dòng 169).
  - `ĐI - Phòng` (Dòng 170) $\equiv$ `P.Đi (DepRooms)` (Dòng 169).
  - `ĐI - Khách` (Dòng 170) $\equiv$ `K.Đi (DepAdult + DepChild)` (Dòng 169).
  - `Ở - Phòng` (Dòng 170) $\equiv$ `P.Ở (OccRooms)` (Dòng 169).
  - `Ở - Khách` (Dòng 170) $\equiv$ `K.Ở (OccAdult + OccChild)` (Dòng 169).
  - `CÔNG SUẤT (%)` (Dòng 170) $\equiv$ `Công suất (PercentOccupancy)` (Dòng 169).

### 2.3. Công Thức Chi Tiết Cho Từng Dòng Ngày ($i = 1 \dots 7$)
1. **Ngày & Thứ:**
   - Dải ngày: Từ Thứ Hai (`p_from_date`) đến Chủ Nhật (`p_to_date = p_from_date + 6 DAY`).
   - Tên Thứ hiển thị tiếng Việt:
     - `1` (Monday) $\rightarrow$ `Thứ Hai`
     - `2` (Tuesday) $\rightarrow$ `Thứ Ba`
     - `3` (Wednesday) $\rightarrow$ `Thứ Tư`
     - `4` (Thursday) $\rightarrow$ `Thứ Năm`
     - `5` (Friday) $\rightarrow$ `Thứ Sáu`
     - `6` (Saturday) $\rightarrow$ `Thứ Bảy`
     - `7` (Sunday) $\rightarrow$ `Chủ Nhật`
2. **Đến (Arrivals):**
   - $\text{Phòng Đến} = \text{COUNT}(\text{phòng check-in có } \text{arrival\_date} = \text{Ngày}_i)$.
   - $\text{Khách Đến} = \sum (\text{Adult} + \text{Child}) \text{ của các phòng đến}$.
3. **Đi (Departures):**
   - $\text{Phòng Đi} = \text{COUNT}(\text{phòng check-out có } \text{departure\_date} = \text{Ngày}_i)$.
   - $\text{Khách Đi} = \sum (\text{Adult} + \text{Child}) \text{ của các phòng đi}$.
4. **Ở (Occupied / In-House):**
   - $\text{Phòng Ở} = \text{COUNT}(\text{phòng lưu trú qua đêm trong ngày } \text{Ngày}_i)$.
   - Điều kiện: $\text{arrival\_date} \le \text{Ngày}_i < \text{departure\_date}$ và trạng thái hợp lệ (`INHOUSE`, `CHECKED_OUT`, `CONFIRMED`).
   - $\text{Khách Ở} = \sum (\text{Adult} + \text{Child}) \text{ của các phòng đang ở}$.
5. **Công Suất (%):**
   $$\text{Công suất}_i = \frac{\text{Phòng Ở}_i}{\text{Phòng Khả Dụng}_i} \times 100\%$$
   Trong đó:
   $$\text{Phòng Khả Dụng}_i = \text{Tổng số phòng vật lý} - \text{Phòng khóa bảo trì (OOO)}_i$$

### 2.4. Công Thức Dòng Tổng Cộng (Grand Total Row)
Dòng tổng cộng ở cuối bảng tính toán như sau (xem đối chiếu trực tiếp từ ảnh `dong_170_ui_mau.png`):
- **Cột Ngày:** Ghi chữ `Tổng`.
- **Cột Thứ:** Ghi `7` (tổng số ngày trong tuần).
- **Cột ĐẾN - Phòng:** Tổng số lượt phòng đến trong tuần:
  $$\sum_{i=1}^{7} \text{Phòng Đến}_i$$
  *(Trên ảnh mẫu: $17 + 10 + 11 + 9 + 16 + 18 + 40 = 121$ phòng).*
- **Cột ĐẾN - Khách:** Tổng lượt khách đến trong tuần:
  $$\sum_{i=1}^{7} \text{Khách Đến}_i = 35 + 22 + 23 + 19 + 36 + 38 + 107 = \mathbf{280} \text{ khách}$$
- **Cột ĐI - Phòng:** Tổng số lượt phòng đi trong tuần:
  $$\sum_{i=1}^{7} \text{Phòng Đi}_i = 7 + 19 + 17 + 15 + 7 + 11 + 42 = 118 \text{ phòng}$$
- **Cột ĐI - Khách:** Tổng lượt khách đi trong tuần:
  $$\sum_{i=1}^{7} \text{Khách Đi}_i = 14 + 36 + 43 + 34 + 13 + 21 + 87 = \mathbf{248} \text{ khách}$$
- **Cột Ở - Phòng:** Tổng số đêm phòng (Room Nights) phát sinh trong tuần:
  $$\sum_{i=1}^{7} \text{Phòng Ở}_i = 113 + 104 + 98 + 92 + 101 + 108 + 106 = 722 \text{ đêm phòng}$$
- **Cột Ở - Khách:** Tổng lượt khách lưu trú qua đêm trong tuần:
  $$\sum_{i=1}^{7} \text{Khách Ở}_i = 246 + 232 + 212 + 197 + 220 + 237 + 257 = \mathbf{1601} \text{ lượt khách}$$
- **Cột CÔNG SUẤT (%):** Công suất bình quân cả tuần (Weighted Average Occupancy):
  $$\text{Công suất TB Tuần} = \frac{\sum_{i=1}^{7} \text{Phòng Ở}_i}{\sum_{i=1}^{7} \text{Phòng Khả Dụng}_i} \times 100\% = \mathbf{79.08\%}$$
  *(Lưu ý: Không lấy trung bình cộng đơn giản của 7 ngày, mà chia tổng đêm phòng ở cho tổng đêm phòng khả dụng cả tuần).*

---

## 3. THIẾT KẾ GIAO DIỆN & BỘ LỌC (UI SPECIFICATION)

### 3.1. Bảng Tham Số Bộ Lọc (`parameter_ui_schema`)

```json
{
  "parameters": [
    {
      "name": "p_week_preset",
      "label": "Chọn ngày",
      "type": "select",
      "default": "this_week",
      "options": [
        { "value": "this_week", "label": "Tuần này" },
        { "value": "last_week", "label": "Tuần trước" },
        { "value": "next_week", "label": "Tuần sau" },
        { "value": "custom", "label": "Tùy chọn tuần..." }
      ],
      "has_date_picker": true,
      "date_range_binding": ["p_from_date", "p_to_date"],
      "col_span": 12
    },
    {
      "name": "p_branch",
      "label": "Chi nhánh",
      "type": "select",
      "default": "0",
      "options_source": "branches",
      "placeholder": "Chi nhánh hiện tại",
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

### 3.2. Bố Cục Trang & Header Band Chuẩn PMS
- **Khổ giấy:** A4 Portrait (Đứng) hoặc Landscape (Ngang). Khuyến nghị mặc định: **A4 Portrait** (vì bảng chỉ có 9 cột, chiều rộng bảng vừa vặn trang dọc rất đẹp và chuyên nghiệp).
- **Lề trang:** Trên 10mm, Dưới 10mm, Trái 12mm, Phải 12mm.
- **Khối đầu trang (Header block):**
  - Trái: Logo khách sạn.
  - Phải:
    - Địa chỉ: `195 Nguyễn Thiện Thuật, ... Việt Nam`
    - Nhân viên: `Admin`
    - Ngày in: `24/08/2026 10:34 SA`
- **Tiêu đề chính:** **BÁO CÁO PHÒNG HÀNG TUẦN** (Chữ hoa in đậm, font-size 18px, căn giữa).
- **Dòng phụ đề kỳ báo cáo:** `Ngày: 24/08/2026 ~ 30/08/2026` (Căn giữa, font-style italic).

### 3.3. Ma Trận Cột & Cấu Trúc Header 2 Tầng (2-Tier Header Matrix)

Bảng dữ liệu gồm 9 cột hiển thị, chia thành 2 tầng header:

```
+------------+------------+--------------------+--------------------+--------------------+--------------+
|            |            |        ĐẾN         |         ĐI         |         Ở          |  CÔNG SUẤT   |
|    Ngày    |    Thứ     +---------+----------+---------+----------+---------+----------+     (%)      |
|            |            |  Phòng  |  Khách   |  Phòng  |  Khách   |  Phòng  |  Khách   |              |
+------------+------------+---------+----------+---------+----------+---------+----------+--------------+
| 24/08/2026 |  Thứ Hai   |   17    |    35    |    7    |    14    |   113   |   246    |    88.98%    |
| 25/08/2026 |   Thứ Ba   |   10    |    22    |   19    |    36    |   104   |   232    |    79.39%    |
|    ...     |    ...     |   ...   |   ...    |   ...   |   ...    |   ...   |   ...    |     ...      |
+------------+------------+---------+----------+---------+----------+---------+----------+--------------+
|    Tổng    |     7      |   121   |   280    |   118   |   248    |   722   |   1601   |    79.08%    |
+------------+------------+---------+----------+---------+----------+---------+----------+--------------+
```

#### Chi Tiết Định Dạng Từng Cột:

| Cột số | Tiêu đề Tầng 1 | Tiêu đề Tầng 2 | Field Binding | Căn lề | Độ rộng | Định dạng / Tương tác |
|:---:|:---:|:---:|---|:---:|:---:|---|
| **1** | Ngày (rowspan 2) | - | `report_date` | Giữa | 100px | `dd/mm/yyyy`, chữ màu xanh lá `#16a34a`, có gạch chân, click để drill-down xem chi tiết phòng ngày đó |
| **2** | Thứ (rowspan 2) | - | `day_name` | Giữa | 85px | Văn bản: `Thứ Hai` $\dots$ `Chủ Nhật`. Dòng tổng ghi số ngày `7` |
| **3** | ĐẾN (colspan 2) | Phòng | `arr_rooms` | Phải | 65px | Số nguyên, phân cách hàng nghìn |
| **4** | ĐẾN (colspan 2) | Khách | `arr_guests` | Phải | 65px | Số nguyên, phân cách hàng nghìn |
| **5** | ĐI (colspan 2) | Phòng | `dep_rooms` | Phải | 65px | Số nguyên, phân cách hàng nghìn |
| **6** | ĐI (colspan 2) | Khách | `dep_guests` | Phải | 65px | Số nguyên, phân cách hàng nghìn |
| **7** | Ở (colspan 2) | Phòng | `occ_rooms` | Phải | 65px | Số nguyên, phân cách hàng nghìn |
| **8** | Ở (colspan 2) | Khách | `occ_guests` | Phải | 65px | Số nguyên, phân cách hàng nghìn |
| **9** | CÔNG SUẤT (%) (rowspan 2) | - | `occupancy_rate` | Phải | 95px | Định dạng phần trăm 2 chữ số thập phân (`#,##0.00%`), vd: `88.98%` |

---

## 4. CẤU TRÚC TOÀN DIỆN `content_json` (FORM DESIGNER TEMPLATE)

Agent triển khai lưu file này tại `backend/database/report_templates/weekly_room_report_reference.php`.

```php
<?php

use App\Services\TemplateRendererService;

return new class
{
    public function definition(): array
    {
        return [
            'code' => 'WEEKLY_ROOM_REPORT',
            'name' => 'Báo cáo phòng hàng tuần',
            'report' => 'WEEKLY_ROOM_REPORT_REFERENCE',
            'page_size' => 'A4',
            'page_orientation' => 'portrait',
            'margin_top' => 10,
            'margin_bottom' => 10,
            'margin_left' => 12,
            'margin_right' => 12,
            'parameter_ui_schema' => [
                'parameters' => [
                    [
                        'name' => 'p_week_preset',
                        'label' => 'Chọn ngày',
                        'type' => 'select',
                        'default' => 'this_week',
                        'options' => [
                            ['value' => 'this_week', 'label' => 'Tuần này'],
                            ['value' => 'last_week', 'label' => 'Tuần trước'],
                            ['value' => 'next_week', 'label' => 'Tuần sau'],
                        ],
                        'has_date_picker' => true,
                        'date_range_binding' => ['p_from_date', 'p_to_date'],
                    ],
                    [
                        'name' => 'p_branch',
                        'label' => 'Chi nhánh',
                        'type' => 'select',
                        'default' => '0',
                        'placeholder' => 'Chi nhánh hiện tại',
                        'options_source' => 'branches',
                    ],
                ],
            ],
            'content_json' => [
                'version' => 2,
                'paper' => [
                    'size' => 'A4',
                    'orientation' => 'portrait',
                    'margins' => ['top' => 10, 'right' => 12, 'bottom' => 10, 'left' => 12],
                ],
                'styles' => [
                    'fontFamily' => 'Times New Roman, serif',
                    'fontSize' => '12px',
                    'lineHeight' => 1.3,
                    'color' => '#111827',
                ],
                'blocks' => [
                    // Block 1: Header metadata (Logo + Hotel Info)
                    [
                        'id' => 'block_header',
                        'type' => 'header',
                        'columns' => [
                            [
                                'width' => '30%',
                                'content' => '<img src="{{hotel_logo}}" style="max-height: 60px; max-width: 100%; object-fit: contain;" alt="Logo" />',
                            ],
                            [
                                'width' => '70%',
                                'align' => 'right',
                                'content' => '
                                    <div style="font-size: 11px; line-height: 1.4;">
                                        <div><strong>Địa chỉ:</strong> {{hotel_address}}</div>
                                        <div><strong>Nhân viên:</strong> {{current_user_name}}</div>
                                        <div><strong>Ngày in:</strong> {{current_datetime}}</div>
                                    </div>',
                            ],
                        ],
                    ],
                    // Block 2: Title & Date Range
                    [
                        'id' => 'block_title',
                        'type' => 'title',
                        'content' => '
                            <div style="text-align: center; margin: 15px 0 10px 0;">
                                <div style="font-size: 18px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px;">BÁO CÁO PHÒNG HÀNG TUẦN</div>
                                <div style="font-size: 12px; font-style: italic; margin-top: 4px; color: #374151;">
                                    Ngày: {{from_date_formatted}} ~ {{to_date_formatted}}
                                </div>
                            </div>',
                    ],
                    // Block 3: Data Table with 2-tier Header
                    [
                        'id' => 'block_table',
                        'type' => 'table',
                        'dataSource' => 'data',
                        'tableStyles' => [
                            'width' => '100%',
                            'borderCollapse' => 'collapse',
                            'fontSize' => '11px',
                            'border' => '1px solid #9ca3af',
                        ],
                        'tier1Headers' => [
                            ['title' => 'Ngày', 'rowspan' => 2, 'width' => '14%', 'align' => 'center'],
                            ['title' => 'Thứ', 'rowspan' => 2, 'width' => '12%', 'align' => 'center'],
                            ['title' => 'ĐẾN', 'colspan' => 2, 'width' => '20%', 'align' => 'center'],
                            ['title' => 'ĐI', 'colspan' => 2, 'width' => '20%', 'align' => 'center'],
                            ['title' => 'Ở', 'colspan' => 2, 'width' => '20%', 'align' => 'center'],
                            ['title' => 'CÔNG SUẤT (%)', 'rowspan' => 2, 'width' => '14%', 'align' => 'center'],
                        ],
                        'tier2Headers' => [
                            ['title' => 'Phòng', 'align' => 'center'],
                            ['title' => 'Khách', 'align' => 'center'],
                            ['title' => 'Phòng', 'align' => 'center'],
                            ['title' => 'Khách', 'align' => 'center'],
                            ['title' => 'Phòng', 'align' => 'center'],
                            ['title' => 'Khách', 'align' => 'center'],
                        ],
                        'columns' => [
                            [
                                'field' => 'report_date_display',
                                'align' => 'center',
                                'render' => '<span style="color: #16a34a; font-weight: 600; text-decoration: underline; cursor: pointer;">{{row.report_date_display}}</span>',
                            ],
                            [
                                'field' => 'day_name',
                                'align' => 'center',
                            ],
                            [
                                'field' => 'arr_rooms',
                                'align' => 'right',
                                'format' => '#,##0',
                            ],
                            [
                                'field' => 'arr_guests',
                                'align' => 'right',
                                'format' => '#,##0',
                            ],
                            [
                                'field' => 'dep_rooms',
                                'align' => 'right',
                                'format' => '#,##0',
                            ],
                            [
                                'field' => 'dep_guests',
                                'align' => 'right',
                                'format' => '#,##0',
                            ],
                            [
                                'field' => 'occ_rooms',
                                'align' => 'right',
                                'format' => '#,##0',
                            ],
                            [
                                'field' => 'occ_guests',
                                'align' => 'right',
                                'format' => '#,##0',
                            ],
                            [
                                'field' => 'occupancy_rate',
                                'align' => 'right',
                                'format' => '#,##0.00%',
                            ],
                        ],
                        'customRows' => [
                            [
                                'type' => 'grand_total',
                                'style' => 'font-weight: bold; background-color: #f3f4f6; border-top: 2px solid #374151;',
                                'cells' => [
                                    ['content' => 'Tổng', 'align' => 'center'],
                                    ['content' => '7', 'align' => 'center'],
                                    ['formula' => 'SUM(arr_rooms)', 'format' => '#,##0', 'align' => 'right'],
                                    ['formula' => 'SUM(arr_guests)', 'format' => '#,##0', 'align' => 'right'],
                                    ['formula' => 'SUM(dep_rooms)', 'format' => '#,##0', 'align' => 'right'],
                                    ['formula' => 'SUM(dep_guests)', 'format' => '#,##0', 'align' => 'right'],
                                    ['formula' => 'SUM(occ_rooms)', 'format' => '#,##0', 'align' => 'right'],
                                    ['formula' => 'SUM(occ_guests)', 'format' => '#,##0', 'align' => 'right'],
                                    ['formula' => 'WEIGHTED_AVG(occ_rooms, available_rooms)', 'format' => '#,##0.00%', 'align' => 'right'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
};
```

---

## 5. ĐẶC TẢ THUẬT TOÁN & STORED PROCEDURE (MYSQL 8.0)

Dưới đây là Stored Procedure `rpt_weekly_room_report` viết cho MySQL 8.0, thực hiện trọn vẹn nghiệp vụ tính toán cho 7 ngày trong tuần:

```sql
DELIMITER $$

DROP PROCEDURE IF EXISTS `rpt_weekly_room_report`$$

CREATE PROCEDURE `rpt_weekly_room_report`(
    IN p_from_date DATE,
    IN p_to_date DATE,
    IN p_branch_id BIGINT
)
BEGIN
    /*
        BÁO CÁO PHÒNG HÀNG TUẦN - 7 NGÀY
        Nếu p_from_date truyền vào là giữa tuần, chuẩn hóa về ngày Thứ Hai đầu tuần.
        p_to_date chuẩn hóa về Chủ Nhật cuối tuần đó.
    */
    DECLARE v_start_date DATE;
    DECLARE v_end_date DATE;
    DECLARE v_total_rooms INT DEFAULT 0;

    -- Chuẩn hóa về Thứ Hai (WEEKDAY trong MySQL: 0 = Monday, 6 = Sunday)
    SET v_start_date = DATE_SUB(p_from_date, INTERVAL WEEKDAY(p_from_date) DAY);
    SET v_end_date = DATE_ADD(v_start_date, INTERVAL 6 DAY);

    -- Lấy tổng số phòng vật lý của khách sạn / chi nhánh
    SELECT COUNT(*) INTO v_total_rooms
    FROM rooms
    WHERE (p_branch_id IS NULL OR p_branch_id = 0 OR branch_id = p_branch_id)
      AND is_active = 1
      AND deleted_at IS NULL;

    -- Tạo bảng ngày cho 7 ngày trong tuần (Thứ Hai -> Chủ Nhật)
    WITH RECURSIVE week_days AS (
        SELECT v_start_date AS cur_date, 0 AS day_offset
        UNION ALL
        SELECT DATE_ADD(cur_date, INTERVAL 1 DAY), day_offset + 1
        FROM week_days
        WHERE day_offset < 6
    ),
    -- Tính số phòng bảo trì OOO từng ngày
    daily_ooo AS (
        SELECT 
            wd.cur_date,
            COUNT(DISTINCT rm.room_id) AS ooo_count
        FROM week_days wd
        LEFT JOIN room_maintenances rm 
            ON wd.cur_date BETWEEN rm.start_date AND rm.end_date
            AND rm.maintenance_type = 'OOO'
            AND rm.status IN ('ACTIVE', 'CONFIRMED')
            AND rm.deleted_at IS NULL
        GROUP BY wd.cur_date
    ),
    -- Tính phòng & khách ĐẾN (Arrivals)
    daily_arr AS (
        SELECT 
            rr.arrival_date AS cur_date,
            COUNT(DISTINCT rr.id) AS arr_rooms,
            SUM(COALESCE(rr.adults, 0) + COALESCE(rr.children, 0)) AS arr_guests
        FROM reservation_rooms rr
        JOIN reservations r ON rr.reservation_id = r.id
        WHERE rr.arrival_date BETWEEN v_start_date AND v_end_date
          AND rr.status IN ('CONFIRMED', 'CHECKED_IN', 'CHECKED_OUT')
          AND r.status NOT IN ('CANCELLED', 'NO_SHOW')
          AND rr.deleted_at IS NULL
        GROUP BY rr.arrival_date
    ),
    -- Tính phòng & khách ĐI (Departures)
    daily_dep AS (
        SELECT 
            rr.departure_date AS cur_date,
            COUNT(DISTINCT rr.id) AS dep_rooms,
            SUM(COALESCE(rr.adults, 0) + COALESCE(rr.children, 0)) AS dep_guests
        FROM reservation_rooms rr
        JOIN reservations r ON rr.reservation_id = r.id
        WHERE rr.departure_date BETWEEN v_start_date AND v_end_date
          AND rr.status IN ('CONFIRMED', 'CHECKED_IN', 'CHECKED_OUT')
          AND r.status NOT IN ('CANCELLED', 'NO_SHOW')
          AND rr.deleted_at IS NULL
        GROUP BY rr.departure_date
    ),
    -- Tính phòng & khách Ở (In-House / Stay-Over)
    daily_occ AS (
        SELECT 
            wd.cur_date,
            COUNT(DISTINCT rr.id) AS occ_rooms,
            SUM(COALESCE(rr.adults, 0) + COALESCE(rr.children, 0)) AS occ_guests
        FROM week_days wd
        LEFT JOIN reservation_rooms rr 
            ON wd.cur_date >= rr.arrival_date 
            AND wd.cur_date < rr.departure_date
            AND rr.status IN ('CONFIRMED', 'CHECKED_IN', 'CHECKED_OUT')
            AND rr.deleted_at IS NULL
        LEFT JOIN reservations r 
            ON rr.reservation_id = r.id
            AND r.status NOT IN ('CANCELLED', 'NO_SHOW')
        GROUP BY wd.cur_date
    )
    SELECT 
        wd.cur_date AS report_date,
        DATE_FORMAT(wd.cur_date, '%d/%m/%Y') AS report_date_display,
        CASE WEEKDAY(wd.cur_date)
            WHEN 0 THEN 'Thứ Hai'
            WHEN 1 THEN 'Thứ Ba'
            WHEN 2 THEN 'Thứ Tư'
            WHEN 3 THEN 'Thứ Năm'
            WHEN 4 THEN 'Thứ Sáu'
            WHEN 5 THEN 'Thứ Bảy'
            WHEN 6 THEN 'Chủ Nhật'
        END AS day_name,
        COALESCE(arr.arr_rooms, 0) AS arr_rooms,
        COALESCE(arr.arr_guests, 0) AS arr_guests,
        COALESCE(dep.dep_rooms, 0) AS dep_rooms,
        COALESCE(dep.dep_guests, 0) AS dep_guests,
        COALESCE(occ.occ_rooms, 0) AS occ_rooms,
        COALESCE(occ.occ_guests, 0) AS occ_guests,
        GREATEST(0, v_total_rooms - COALESCE(ooo.ooo_count, 0)) AS available_rooms,
        CASE 
            WHEN (v_total_rooms - COALESCE(ooo.ooo_count, 0)) > 0 
            THEN ROUND((COALESCE(occ.occ_rooms, 0) * 100.0) / (v_total_rooms - COALESCE(ooo.ooo_count, 0)), 2)
            ELSE 0.00
        END AS occupancy_rate
    FROM week_days wd
    LEFT JOIN daily_arr arr ON wd.cur_date = arr.cur_date
    LEFT JOIN daily_dep dep ON wd.cur_date = dep.cur_date
    LEFT JOIN daily_occ occ ON wd.cur_date = occ.cur_date
    LEFT JOIN daily_ooo ooo ON wd.cur_date = ooo.cur_date
    ORDER BY wd.cur_date ASC;

END$$

DELIMITER ;
```

---

## 6. HƯỚNG DẪN KIỂM THỬ & ĐỐI SOÁT SỐ LIỆU (VERIFICATION CHECKLIST)

Khi triển khai xong báo cáo, Agent thực hiện kiểm tra các tiêu chí sau:

- [ ] **Khớp số ngày tuần:** Luôn hiển thị chính xác 7 dòng từ Thứ Hai đến Chủ Nhật. Nếu người dùng chọn một ngày bất kỳ (ví dụ Thứ Tư), hệ thống tự động nhận diện tuần chứa Thứ Tư đó và tải đủ 7 ngày của tuần.
- [ ] **Đối soát chéo với Dòng 169 (`ROOM_FORECAST`):**
  - Mở đồng thời Dòng 169 và Dòng 170 trên cùng khoảng ngày 7 ngày.
  - Kiểm tra từng ngày: `ĐẾN - Phòng` = `P.Đến`, `ĐI - Phòng` = `P.Đi`, `Ở - Phòng` = `P.Ở`, `CÔNG SUẤT (%)` = `Công suất`. Sai lệch 1 đơn vị là bug.
- [ ] **Đối soát dòng Tổng:**
  - Tổng khách đến = tổng cộng 7 ngày.
  - Tổng khách đi = tổng cộng 7 ngày.
  - Tổng khách ở = tổng cộng 7 ngày.
  - Công suất trung bình cả tuần = $\frac{\sum \text{Phòng Ở}}{\sum \text{Phòng Khả Dụng}} \times 100\%$, KHÔNG ĐƯỢC lấy trung bình cộng số học của cột công suất.
- [ ] **Kiểm thử hiển thị HTML & In ấn PDF:**
  - Kiểm tra bảng 2 tầng header không bị vỡ layout hoặc tràn lề giấy A4 Portrait.
  - Cột Ngày hiển thị định dạng link click màu xanh lá `#16a34a`.
  - Đầy đủ khối thông tin khách sạn bên phải: Địa chỉ, Nhân viên, Ngày in.

## 7. TRẠNG THÁI TRIỂN KHAI DỰ ÁN

- Đã tạo `rpt_weekly_room_report`, metadata, template và report definition tại migration `2026_09_24_110000_create_weekly_room_report.php`.
- Contract chỉ dùng `p_division` với `__current__`/`__all__`; cột chi nhánh là `Division`.
- Tuần được chuẩn hóa Thứ Hai–Chủ Nhật theo `Asia/Ho_Chi_Minh`; công suất tổng dùng tổng phòng ở chia tổng phòng khả dụng.
- Phòng OOO bị trừ khỏi sức chứa; OOS không bị trừ theo quyết định nghiệp vụ hiện tại.
- Đã kiểm tra PHPUnit 4/4, PHP lint, route list và frontend build. Chưa chạy migration thật, chưa nghiệm thu dữ liệu thật/browser/PDF.

## 7. TRẠNG THÁI TRIỂN KHAI DỰ ÁN

- Đã tạo `rpt_weekly_room_report`, metadata, template và report definition tại migration `2026_09_24_110000_create_weekly_room_report.php`.
- Contract chỉ dùng `p_division` với `__current__`/`__all__`; cột chi nhánh là `Division`.
- Tuần được chuẩn hóa Thứ Hai–Chủ Nhật theo `Asia/Ho_Chi_Minh`; công suất tổng dùng tổng phòng ở chia tổng phòng khả dụng.
- Phòng OOO bị trừ khỏi sức chứa; OOS không bị trừ theo quyết định nghiệp vụ hiện tại.
- Đã kiểm tra PHPUnit 4/4, PHP lint, route list và frontend build. Chưa chạy migration thật, chưa nghiệm thu dữ liệu thật/browser/PDF.
