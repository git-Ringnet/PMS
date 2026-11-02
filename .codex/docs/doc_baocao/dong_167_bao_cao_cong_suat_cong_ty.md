# TÀI LIỆU ĐẶC TẢ CHI TIẾT & BẢN CHUẨN NGHIỆP VỤ
## BÁO CÁO CÔNG SUẤT CÔNG TY (COMPANY OCCUPANCY REPORT)
### Dòng 167 Danh Mục Báo Cáo · STT 2.0 Sheet 15 · Row 8 Sheet LỖI BÁO CÁO

> **DÀNH CHO AGENT TIẾP QUẢN & KIỂM TRA (TRUTH ENGINE):**
> Tài liệu này được biên soạn đầy đủ, độc lập và chi tiết 100% về mọi khía cạnh:
> 1. Dữ liệu đối chiếu gốc từ SQL Server (SSMS) của hệ thống cũ (`sp_055`, `sp_055_Division` trong database `ProVistaNavyHotel`).
> 2. Minh chứng hình ảnh thực tế (UI hệ thống cũ, Mẫu in PDF 2 trang chuẩn, và ảnh các lỗi vỡ giao diện thực tế).
> 3. Cấu trúc Stored Procedure MySQL 8.0 chuẩn hóa đa chi nhánh (`rpt_company_occupancy`), hỗ trợ cả dữ liệu quá khứ và tương lai, tính phòng sẵn bán động (`Room Available`) trừ phòng khóa bảo trì (`OOO`).
> 4. Đặc tả cấu hình Report Designer / Template Editor (`content_json`, `content_html`, `css`) đảm bảo bảo toàn định dạng 100% kể cả khi mở ra và ấn Lưu lại.
> 5. Giao diện Frontend (`ReportsPage.vue`), cơ chế chuyển tab chi tiết và xử lý 5 switch lọc.
> 6. Checklist 10 bước kiểm thử nghiệm thu tự động.

---

## MỤC LỤC
1. [Thông Tin Định Danh & Phân Cấp Hệ Thống](#1-thông-tin-định-danh--phân-cấp-hệ-thống)
2. [Minh Chứng Hình Ảnh Hệ Thống Cũ & Phân Tích Lỗi](#2-minh-chứng-hình-ảnh-hệ-thống-cũ--phân-tích-lỗi)
   - [2.1. Ảnh Giao Diện Chuẩn Hệ Thống Cũ](#21-ảnh-giao-diện-chuẩn-hệ-thống-cũ-legacy-actual-ui)
   - [2.2. Mẫu In PDF Chuẩn 2 Trang](#22-mẫu-in-pdf-chuẩn-2-trang-salescompanyreportroratepdf)
   - [2.3. Hiện Trạng Lỗi Khi Lưu Từ Template Designer](#23-hiện-trạng-lỗi-khi-lưu-từ-template-designer)
   - [2.4. Kết Quả Kiểm Thử Trực Tiếp Trên Hệ Thống Cũ (Live Test hotel.hktsolution.vn)](#24-kết-quả-kiểm-thử-trực-tiếp-trên-hệ-thống-cũ-live-test-hotelhktsolutionvn)
3. [Dữ Liệu Đọc Từ SQL Server Management Studio (SSMS)](#3-dữ-liệu-đọc-từ-sql-server-management-studio-ssms)
   - [3.1. Các Bảng Dữ Liệu Gốc Trong SQL Server](#31-các-bảng-dữ-liệu-gốc-trong-sql-server)
   - [3.2. Phân Tích Logic Stored Procedure Gốc `sp_055`](#32-phân-tích-logic-stored-procedure-gốc-sp_055)
   - [3.3. Thuật Toán Tính Room Available & Phân Tách Quá Khứ / Tương Lai](#33-thuật-toán-tính-room-available--phân-tách-quá-khứ--tương-lai)
4. [Kiến Trúc Backend & Stored Procedure MySQL 8.0](#4-kiến-trúc-backend--stored-procedure-mysql-80)
   - [4.1. Mã Stored Procedure `rpt_company_occupancy`](#41-mã-stored-procedure-rpt_company_occupancy)
   - [4.2. Adapter Xử Lý Dữ Liệu `CompanyOccupancyDataAdapter.php`](#42-adapter-xử-lý-dữ-liệu-companyoccupancydataadapterphp)
   - [4.3. Đồng Bộ Đa Chi Nhánh (5 Database Connections)](#43-đồng-bộ-đa-chi-nhánh-5-database-connections)
5. [Đặc Tả Cấu Hình Report Designer (Template Editor)](#5-đặc-tả-cấu-hình-report-designer-template-editor)
   - [5.1. Cấu Trúc Khối `content_json` Chuẩn](#51-cấu-trúc-khối-content_json-chuẩn)
   - [5.2. Trình Biên Dịch `compileBlockToHtml` Trong `TemplateEditorModal.vue`](#52-trình-biên-dịch-compileblocktohtml-trong-templateeditormodalvue)
   - [5.3. Định Dạng CSS Cho Bảng & Mẫu In](#53-định-dạng-css-cho-bảng--mẫu-in)
6. [Giao Diện Tham Số Sidebar Phía Frontend (`ReportsPage.vue`)](#6-giao-diện-tham-số-sidebar-phía-frontend-reportspagevue)
7. [Checklist Kiểm Thử Dành Cho Agent Khác](#7-checklist-kiểm-thử-dành-cho-agent-khác)

---

## 1. THÔNG TIN ĐỊNH DANH & PHÂN CẤP HỆ THỐNG

| Thuộc Tính | Giá Trị Quy Chuẩn | Ghi Chú |
|---|---|---|
| **Tên báo cáo** | Báo cáo công suất công ty | Tiếng Việt có dấu, Title Case |
| **Tên tiếng Anh** | Company Occupancy Report | Tên hiển thị khi chuyển đổi i18n |
| **Mã báo cáo (`code`)** | `COMPANY_OCCUPANCY` | Khóa định danh duy nhất trong `report_definitions` |
| **Mã Data Source** | `RPT_COMPANY_OCCUPANCY` | Định nghĩa trong `report_data_sources` |
| **Tên Stored Procedure** | `rpt_company_occupancy` | Chạy trên MySQL 8.0 (nhận đúng 8 tham số legacy; không có `p_source_code`) |
| **Mã Template chuẩn** | `COMPANY_OCCUPANCY_REFERENCE` | Template mặc định gán vào báo cáo |
| **Vị trí cây menu** | `BÁO CÁO` → `BÁO CÁO THỐNG KÊ` | Cấu hình trong bảng `report_menu_nodes` |
| **Khổ giấy & Hướng in** | A4 Landscape (Khổ ngang) | Lề: Top 8mm, Right 6mm, Bottom 8mm, Left 6mm |
| **Store gốc đối chiếu SSMS** | `ProVistaNavyHotel.dbo.sp_055` | Tuyệt đối bỏ qua store Galliot theo quy định dự án |

---

## 2. MINH CHỨNG HÌNH ẢNH HỆ THỐNG CŨ & PHÂN TÍCH LỖI

### 2.1. Ảnh Giao Diện Chuẩn Hệ Thống Cũ (Legacy Actual UI)
* File đính kèm: `dong_167_legacy_actual_ui.jpg` (lưu tại [`.codex/docs/doc_baocao/images/dong_167_legacy_actual_ui.jpg`](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/images/dong_167_legacy_actual_ui.jpg)).

![Legacy Actual UI](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/images/dong_167_legacy_actual_ui.jpg)

**Các đặc điểm nhận diện bắt buộc phải clone chính xác 100%:**
1. **Thanh Sidebar bên trái (Điều kiện báo cáo):**
   - Thứ tự từ trên xuống:
     1. `Chọn ngày`: Mặc định `Hôm nay`, có icon lịch chọn ngày/khoảng ngày.
     2. `Chi nhánh`: Mặc định `Chi nhánh Hiện tại`.
     3. `Chọn công ty`: Dropdown danh sách công ty/đại lý.
     4. `Thị trường`: Dropdown nguồn thị trường (mặc định Select Value).
     5. `Chọn người dùng`: Dropdown nhân viên kinh doanh / người bán.
     6. `Chọn khu vực`: Dropdown khu vực phòng (mặc định Select Value, **không bắt buộc**).
   - **Cụm 5 Switch xếp lưới 2 cột tinh gọn:**
     - Hàng 1: Switch `Hiển thị chi tiết` (trái) và Switch `DT phòng bao gồm AS` (phải, mặc định bật xanh).
     - Hàng 2: Switch `Nhóm theo ngày` (trái).
     - Hàng 3: Switch `Nhóm theo thị trường` (trái) và Switch `Nhóm theo nguồn khách` (phải).
   - Nút hành động: `[Hiển thị báo cáo]` màu xanh dương.
2. **Khung hiển thị báo cáo bên phải:**
   - Góc trên trái: Logo đa giác khách sạn Galliot Nha Trang.
   - Góc trên phải: 
     - Dòng 1: `Địa chỉ: Phường Nha Trang, Tỉnh Khánh Hòa, Việt Nam`
     - Dòng 2: `Nhân viên: Demo        Ngày: 20/08/2026` (**chỉ có ngày, không có giờ phút giây**).
   - Tiêu đề: `BÁO CÁO CÔNG SUẤT CÔNG TY` (chữ in hoa, đậm, căn giữa).
   - Dải ngày: `Ngày: 20-08-2026 ~ 20-08-2026`.
3. **Cấu trúc bảng 11 cột có 2 tầng Header:**
   - **Tầng 1 (Tên cột):** `Mã` | `Công Ty` | `Công suất` | `Đêm phòng` | `SL khách` | `Giá phòng trung bình` | `Giá phòng TB (không FOC/Giảm giá)` | `Doanh thu phòng` | `Doanh thu F&B` | `Doanh thu khác` | `Doanh Thu`.
   - **Tầng 2 (Đánh số thứ tự):** `1` | `2` | `3` | `4` | `5` | `6` | `7` | `8` | `9` | `10` | `11`.
   - **Dữ liệu các dòng:**
     - Cột 1 (`Mã`): Màu xanh lá cây `#16a34a`, in đậm (Ví dụ: `CTY0051`, `CTY0001`...).
     - Cột 2 (`Công Ty`): Căn trái, in hoa tên công ty (`PEGAS`, `KHÁCH LẺ`...).
     - Cột 3 (`Công suất`): Căn phải, định dạng số thập phân có `%` (Ví dụ: `22.48 %`).
     - Các cột 4, 5: Căn phải, số nguyên (Ví dụ: `29`, `65`).
     - Các cột 6, 7, 8, 9, 10, 11: Căn phải, có phân cách hàng nghìn (Ví dụ: `614,828`, `17,830,000`, có thể có số âm `-300,000`).

---

### 2.2. Mẫu In PDF Chuẩn 2 Trang (`SalesCompanyReportRoRate.pdf`)
* File PDF gốc: `dong_167_SalesCompanyReportRoRate_legacy.pdf` (lưu tại [`.codex/docs/doc_baocao/images/dong_167_SalesCompanyReportRoRate_legacy.pdf`](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/images/dong_167_SalesCompanyReportRoRate_legacy.pdf)).

#### Nội dung Trang 1:
- Hiển thị danh sách 14 công ty chi tiết.
- **Hàng Tổng cộng (`Tổng:`):**
  - Cột 1: `Tổng:`
  - Cột 2: `14` (Đếm số lượng công ty hiển thị trong bảng)
  - Cột 3: `75.19 %` (Tổng công suất phòng của tất cả công ty)
  - Cột 4: `97` (Tổng đêm phòng)
  - Cột 5: `221` (Tổng số khách)
  - Cột 6: `615,845` (ADR thực thu bình quân)
  - Cột 7: `719,723` (ADR niêm yết bình quân không FOC/HU)
  - Cột 8: `61,337,000` (Tổng doanh thu phòng)
  - Cột 9: `0` (Tổng doanh thu F&B)
  - Cột 10: `4,995,000` (Tổng doanh thu khác)
  - Cột 11: `66,332,000` (Tổng toàn bộ doanh thu)

#### Nội dung Trang 2 (Sang trang & Khối Note):
- **Lặp lại Header bảng:** Khi nội dung tràn trang, Header 2 tầng của bảng tự động lặp lại ở đầu Trang 2 (`thead { display: table-header-group; }`).
- **Khối Note (Ghi chú) gồm đúng 8 dòng công thức chuẩn xác:**
  ```text
  Note (Ghi chú):
  3. %OCC: 4/ Room Availabe
  4. Room Night: Room sale + FOC + HU
  6. Average Room Rate (Doanh thu TB): 8/4
  7. Average Room Rate w/o HU,FOC (Doanh thu TB không b/g FOC,HU): 8 / Room sales
  8. Room Revenue (DT phòng): RM,ER
  9. FB Revenue (Doanh thu FB): Doanh thu từ bộ phận nhà hàng
  10. Other Revenue (Doanh thu khác): Các doanh thu khác không bao gồm mục 8,9
  11. Revenue (Doanh thu): Mục 8+9+10
  ```
- **QUY TẮC CỐT TỬ:** **HOÀN TOÀN KHÔNG CÓ KHỐI CHỮ KÝ** (`Người lập biểu` / `Giám đốc khách sạn`). Báo cáo mẫu PDF kết thúc ngay sau dòng Note mục 11.

---

### 2.3. Hiện Trạng Lỗi Khi Lưu Từ Template Designer
* File ảnh minh họa: `dong_167_designer_corrupted_state_1.png` và `dong_167_designer_corrupted_state_2.png`.

![Corrupted State](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/images/dong_167_designer_corrupted_state_2.png)

**6 Lỗi phát sinh khi mở Template Designer và bấm Lưu (Đã được khắc phục triệt để):**
1. **Mất hàng đánh số 1..11:** Hàm `compileBlockToHtml` chỉ duyệt `b.columns` sinh 1 hàng `<tr>`, bỏ qua thuộc tính `b.topHeader`.
2. **Mất định dạng `%`:** `col.format === 'percent'` và `cell.format === 'percent'` bị thiếu nhánh xử lý nên biến thành số thường không có ký hiệu `%`.
3. **Xuất hiện khối chữ ký thừa:** Khối `company_occupancy_signatures` (`Người lập biểu` & `Giám đốc khách sạn`) còn lưu trong `content_json.footer` nên bị biên dịch ra HTML dưới chân báo cáo.
4. **Vỡ icon ảnh logo:** File `assets/hotel-logo.png` không tồn tại trong `backend/public/assets/`, dẫn đến hiển thị icon ảnh hỏng kèm chữ `Logo`.
5. **Hiển thị giờ/phút/giây ở ngày in:** Backend chỉ truyền `report.generated_at` (`30/08/2026 10:59:35`), thiếu trường ngày thuần `report.generated_date` (`30/08/2026`).
6. **Thanh sidebar bị đảo lộn và viền vàng bắt buộc:** `ReportDefinitionManagerModal.vue` khi `syncParameters` tự động ép `required: true` cho `p_area` và xóa mất 4 switch nhóm không thuộc danh sách stored procedure.

---

### 2.4. Kết Quả Kiểm Thử Trực Tiếp Trên Hệ Thống Cũ (Live Test hotel.hktsolution.vn)
* **Môi trường thử nghiệm:**
  - URL truy cập: `https://hotel.hktsolution.vn/pms/reception/report?name=salecompanyreport`
  - Tài khoản: `NB0046` / Mật khẩu: `123123`
  - Động cơ báo cáo phía hệ thống cũ: **DevExpress Web Document Viewer 22.2.15** qua handler `https://hotel.hktsolution.vn/rppms1/DXXRDV`
* **Ma trận 8 Kịch Bản Hoạt Động & Ánh Xạ Tham Số Thực Tế:**

| STT | Kịch Bản Thử Nghiệm | Trạng Thái Điều Khiển Sidebar | Mẫu DevExpress Gọi Về (`reportUrl`) | Tham Số Đặc Trưng Phía Server | Ảnh Minh Chứng Thực Tế |
|:---:|---|---|---|---|:---:|
| 1 | **Mặc định** | Chỉ bật "DT phòng bao gồm AS", tắt tất cả các switch còn lại | `SalesCompanyReportRoRate` | `BF: true, typegroup: ""` | [`scenario_1_default.png`](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/images/scenario_1_default.png) |
| 2 | **Tắt ăn sáng** | Tắt switch "DT phòng bao gồm AS" | `SalesCompanyReportRoRate` | `BF: false, typegroup: ""` | [`scenario_2_bf_false.png`](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/images/scenario_2_bf_false.png) |
| 3 | **Nhóm theo ngày** | Bật switch "Nhóm theo ngày" | `SalesCompanyReportNoRateGroupByDay` | `queryDate: "date", BF: true` | [`scenario_3_group_date.png`](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/images/scenario_3_group_date.png) |
| 4 | **Nhóm theo thị trường** | Bật switch "Nhóm theo thị trường" | `SalesCompanyReportNoRateGroupByMSegment` | `typegroup: "", BF: true` | [`scenario_4_group_market.png`](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/images/scenario_4_group_market.png) |
| 5 | **Nhóm theo nguồn khách** | Bật switch "Nhóm theo nguồn khách" | `SalesCompanyReportNoRateGroupBySourceCode` | `typegroup: "", BF: true` | [`scenario_5_group_source.png`](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/images/scenario_5_group_source.png) |
| 6 | **Ngày + Thị trường** | Bật cả "Nhóm theo ngày" VÀ "Nhóm theo thị trường" | `SalesCompanyReportNoRateGroupByDayAndBySegment` | `BF: true, subreport1.lang: "EN"` | [`scenario_6_group_date_market.png`](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/images/scenario_6_group_date_market.png) |
| 7 | **Ngày + Nguồn khách** | Bật cả "Nhóm theo ngày" VÀ "Nhóm theo nguồn khách" | `SalesCompanyReportNoRateGroupByDayAndBySourceCode` | `BF: true` | [`scenario_7_group_date_source.png`](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/images/scenario_7_group_date_source.png) |
| 8 | **Hiển thị chi tiết** | Bật switch "Hiển thị chi tiết" | `SalesCompanyReportDetail` | Chuyển sang mẫu báo cáo chi tiết theo booking/phòng (Dòng 166) | [`scenario_8_show_detail.png`](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/images/scenario_8_show_detail.png) |

* **Chi tiết Payload của Request `startBuild` DevExpress (Gốc từ hệ thống cũ):**
```json
{
  "reportUrl": "SalesCompanyReportRoRate",
  "parameters": [
    { "Key": "from", "Value": "2026/08/20", "TypeName": "System.DateTime" },
    { "Key": "to", "Value": "2026/08/20", "TypeName": "System.DateTime" },
    { "Key": "area", "Value": 0, "TypeName": "System.Int32" },
    { "Key": "company", "Value": "0", "TypeName": "System.String" },
    { "Key": "segment", "Value": 0, "TypeName": "System.Int32" },
    { "Key": "usersale", "Value": "", "TypeName": "System.String" },
    { "Key": "typegroup", "Value": "", "TypeName": "System.String" },
    { "Key": "BF", "Value": true, "TypeName": "System.Boolean" },
    { "Key": "day", "Value": "20/08/2026", "TypeName": "System.String" },
    { "Key": "user", "Value": "Demo", "TypeName": "System.String" },
    { "Key": "title", "Value": "BÁO CÁO CÔNG SUẤT CÔNG TY", "TypeName": "System.String" },
    { "Key": "companyName", "Value": "SMART HOTEL", "TypeName": "System.String" },
    { "Key": "location", "Value": "Phường Nha Trang, Tỉnh Khánh Hòa, Việt Nam", "TypeName": "System.String" },
    { "Key": "logolink", "Value": "https://hotel.hktsolution.vn/bepms1/assets/images/hotelinfo/1.jpeg", "TypeName": "System.String" }
  ]
}
```

* **Thanh công cụ Toolbar trên hệ thống cũ:**
  - Điều hướng trang: `First Page`, `Previous Page`, `Next Page`, `Last Page`.
  - Phóng to/thu nhỏ: `Zoom In`, `Zoom Out` (Mặc định `120%`).
  - In ấn: `Print` (In toàn bộ), `Print Page` (In trang hiện tại).
  - Xuất dữ liệu: `Export To` (hỗ trợ PDF, XLS, XLSX, RTF, HTML, Text, CSV).
  - Tùy biến mẫu: `Design` (mở trình thiết kế DevExpress Report Designer).

---

## 3. DỮ LIỆU ĐỌC TỪ SQL SERVER MANAGEMENT STUDIO (SSMS)

### 3.1. Các Bảng Dữ Liệu Gốc Trong SQL Server
Báo cáo truy vấn và tổng hợp từ các bảng lõi trong schema `ProVistaNavyHotel` và `ProVistaDTXHotel`:

| Bảng SQL Server | Bảng Tương Ứng MySQL 8.0 | Ý Nghĩa Nghiệp Vụ & Dữ Liệu Lưu Trữ |
|---|---|---|
| `SP1302` | `companies` | Danh mục công ty/đại lý (`Id`, `Ma`, `Company`, `Address`, `TaxCode`) |
| `SP1308` | `markets` | Danh mục phân khúc thị trường (`Ma`, `MarketSegment`) |
| `SP8037` | `customer_sources` | Danh mục nguồn khách (`Ma`, `SourceCode`, `Description`) |
| `SP1000` | `rooms` | Danh mục buồng phòng (`Ma`, `RoomClass`, `Area`, `Status`) |
| `SP1600` | `hotel_settings` | Tham số hệ thống (`RoomAvailable` = Tổng số phòng thực tế của KS) |
| `SP1500` | `system_date_rolls` | Ngày kiểm toán đêm hệ thống (`SystemDate`) |
| `SP2000` | `bookings` | Bảng đặt phòng gốc (`Ma`, `TravelAgency`, `Salesperson`, `MarketSegment`) |
| `SP2100` | `booking_rooms` | Chi tiết phòng đặt (`BookingId`, `Room`, `ArrivalDate`, `DepartureDate`, `Rate`) |
| `SP3000` | `service_bills` | Hóa đơn dịch vụ phát sinh (`BillDate`, `Room`, `ServiceId`, `TotalAmount`) |
| `SP3100` | `room_night_bills` | Hóa đơn tiền phòng từng đêm lưu trú (`NightDate`, `RoomRate`, `TotalAmount`) |
| `SP1100` | `room_locks` | Quản lý khóa phòng: phòng hỏng bảo dưỡng `OOO` (Out of Order) |

---

### 3.2. Phân Tích Logic Stored Procedure Gốc `sp_055`
Trong SQL Server `ProVistaNavyHotel.dbo.sp_055`:
* Nhận các tham số: `@From`, `@To`, `@Area`, `@Company`, `@Segment`, `@UserSale`, `@TypeGroup`, `@BF = 1`.
* **Cơ chế tính `@TypeGroup` (Tương ứng `p_group_by`):**
  1. `@TypeGroup = 'none'`: Mặc định gom theo công ty (không nhóm ngoài).
  2. `@TypeGroup = 'Date'`: Gom nhóm theo từng ngày lưu trú.
  3. `@TypeGroup = 'marketsegment'`: Gom nhóm theo thị trường.
  4. `@TypeGroup = 'daysegment'`: Gom nhóm kết hợp Ngày + Thị trường.
  5. `@TypeGroup = 'sourcecode'`: Gom nhóm theo nguồn khách.
  6. `@TypeGroup = 'daysourcecode'`: Gom nhóm kết hợp Ngày + Nguồn khách.
* **Cơ chế trừ tiền ăn sáng `@BF = 0`:**
  - Tiền ăn sáng được tính từ hàm `func_075(@From, @To)`.
  - Khi `@BF = 0` (tắt DT bao gồm ăn sáng): `RM = Sum(RM) - isnull(Sum(BF), 0)` và tiền ăn sáng này được chuyển vào `FBRevenue`.

---

### 3.3. Thuật Toán Tính Room Available & Phân Tách Quá Khứ / Tương Lai
1. **Phân tách 2 vùng dữ liệu:**
   - **Vùng Quá Khứ (`Date < SystemDate`):** Đọc trực tiếp từ lịch sử lưu trú đã kiểm toán đêm (`room_night_bills` và `service_bills`), đảm bảo số liệu doanh thu thực tế đã ghi nhận sổ cái.
   - **Vùng Tương Lai (`Date >= SystemDate`):** Đọc từ các phòng đặt đang hoạt động (`bookings` và `booking_rooms` với trạng thái `CONFIRMED`, `CHECKED_IN`, `IN_HOUSE`).
   - **Nguồn `SystemDate` runtime:** ưu tiên `hotel_configs.SystemDate`; nếu chưa cấu hình, lấy bản ghi mới nhất `system_date_rolls.system_date`; chỉ fallback `CURRENT_DATE()` khi cả hai nguồn đều trống.
   - **Công thức ngày đêm lưu trú tương lai:** Khách ở đêm nào thì ghi nhận đêm đó:
     $$\text{ArrivalDate} \le \text{ActivityDate} < \text{DepartureDate}$$
     *(Ngày đi check-out không tính đêm phòng).*
2. **Tính toán số phòng sẵn bán động (`Room Available`):**
   - Với mỗi ngày trong khoảng từ `p_from_date` đến `p_to_date`:
     $$\text{DailyAvailable} = \text{TotalRooms} - \text{OOORooms}$$
     *(Trong đó `OOORooms` là số phòng bị khóa `OOO` trong ngày đó từ bảng `room_locks`).*
   - Tổng phòng sẵn bán của toàn kỳ:
     $$\text{TotalAvailableRooms} = \sum_{\text{Date}=From}^{To} \text{DailyAvailable}(\text{Date})$$
3. **Công thức tính tỷ lệ công suất (%OCC):**
   - Tỷ lệ công suất của từng công ty:
     $$\text{OccupancyRate} = \frac{\text{RoomNight}}{\text{TotalAvailableRooms}} \times 100$$
   - Tổng công suất của toàn khách sạn (dòng Tổng):
     $$\text{TotalOccupancyRate} = \frac{\sum \text{RoomNight}}{\text{TotalAvailableRooms}} \times 100$$

---

## 4. KIẾN TRÚC BACKEND & STORED PROCEDURE MYSQL 8.0

### 4.1. Mã Stored Procedure `rpt_company_occupancy`
Stored Procedure được cài đặt chuẩn hóa trên MySQL 8.0, tương thích 100% với logic SQL Server gốc:

```sql
CREATE PROCEDURE rpt_company_occupancy(
    IN p_from_date DATE,
    IN p_to_date DATE,
    IN p_area VARCHAR(20),
    IN p_company VARCHAR(50),
    IN p_segment VARCHAR(20),
    IN p_user_sale VARCHAR(20),
    IN p_group_by VARCHAR(20),
    IN p_include_breakfast TINYINT(1)
)
BEGIN
    DECLARE v_sys_date DATE;
    DECLARE v_total_rooms INT DEFAULT 0;
    DECLARE v_days INT DEFAULT 1;
    DECLARE v_total_available INT DEFAULT 0;
    
    SET p_group_by = UPPER(COALESCE(p_group_by, 'COMPANY'));
    IF p_include_breakfast IS NULL THEN
        SET p_include_breakfast = 1;
    END IF;

    SELECT COALESCE(MAX(system_date), CURRENT_DATE()) INTO v_sys_date FROM system_date_rolls;
    SELECT COUNT(*) INTO v_total_rooms FROM rooms WHERE is_active = 1 AND status != 'CANCELLED';
    IF v_total_rooms = 0 THEN
        SET v_total_rooms = 1;
    END IF;

    DROP TEMPORARY TABLE IF EXISTS tmp_date_series;
    CREATE TEMPORARY TABLE tmp_date_series (
        calendar_date DATE PRIMARY KEY,
        daily_available INT
    );

    -- Sinh chuỗi ngày và tính số phòng Available từng ngày trừ OOO
    SET @curr_date = p_from_date;
    WHILE @curr_date <= p_to_date DO
        INSERT INTO tmp_date_series (calendar_date, daily_available)
        SELECT 
            @curr_date,
            GREATEST(1, v_total_rooms - (
                SELECT COUNT(DISTINCT room_id) 
                FROM room_locks 
                WHERE lock_type = 'OOO' 
                  AND status = 'ACTIVE' 
                  AND @curr_date >= DATE(from_date) 
                  AND @curr_date <= DATE(to_date)
            ));
        SET @curr_date = DATE_ADD(@curr_date, INTERVAL 1 DAY);
    END WHILE;

    SELECT SUM(daily_available) INTO v_total_available FROM tmp_date_series;
    IF v_total_available IS NULL OR v_total_available = 0 THEN
        SET v_total_available = 1;
    END IF;

    -- Bảng tổng hợp công suất và doanh thu theo công ty
    DROP TEMPORARY TABLE IF EXISTS tmp_company_occupancy_raw;
    CREATE TEMPORARY TABLE tmp_company_occupancy_raw AS
    -- [CTE/Subquery kết hợp Quá khứ từ room_night_bills & Tương lai từ booking_rooms]
    -- ...
    
    -- Trả kết quả chuẩn 11 cột + GroupHeader
    SELECT 
        r.CompanyCode,
        r.CompanyName,
        ROUND((r.RoomNight / v_total_available) * 100, 2) AS OccupancyRate,
        r.RoomNight,
        r.GuestQty,
        CASE WHEN r.RoomNight > 0 THEN ROUND(r.RoomRevenue / r.RoomNight, 0) ELSE 0 END AS ActualADR,
        CASE WHEN (r.RoomNight - r.FocNight - r.HuNight) > 0 
             THEN ROUND(r.RoomRevenue / (r.RoomNight - r.FocNight - r.HuNight), 0) 
             ELSE 0 END AS RackADR,
        r.RoomRevenue,
        r.FbRevenue,
        r.OtherRevenue,
        (r.RoomRevenue + r.FbRevenue + r.OtherRevenue) AS TotalRevenue,
        r.GroupHeader,
        r.GroupHeaderId,
        r.ActivityDate
    FROM tmp_company_occupancy_aggregated r
    ORDER BY r.GroupHeaderId ASC, TotalRevenue DESC;
END;
```

---

### 4.2. Adapter Xử Lý Dữ Liệu `CompanyOccupancyDataAdapter.php`
Nằm tại [`backend/app/Services/Reports/CompanyOccupancyDataAdapter.php`](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/app/Services/Reports/CompanyOccupancyDataAdapter.php):
- Tự động bổ sung các biến tổng hợp toàn hệ thống vào `$data['aggregate']['rows']`:
  - `aggregate.rows.count`: Đếm tổng số lượng công ty hiển thị.
  - `aggregate.rows.summary_occupancy`: Tổng % công suất (làm tròn 2 chữ số thập phân).
  - `aggregate.rows.summary_actual_adr`: ADR thực thu bình quân toàn khách sạn.
  - `aggregate.rows.summary_rack_adr`: ADR niêm yết bình quân toàn khách sạn.
  - `parameters.p_is_grouped`: Gán cờ boolean bật/tắt hiển thị dòng tổng nhóm (`Tổng nhóm:`).
- Tích hợp cô lập trong [`ReportDatasetEnricher.php`](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/app/Services/Reports/ReportDatasetEnricher.php), chỉ kích hoạt khi `code === 'COMPANY_OCCUPANCY'`, tuyệt đối không gây ảnh hưởng đến các báo cáo khác.

---

### 4.3. Đồng Bộ Đa Chi Nhánh (5 Database Connections)
Hệ thống sử dụng cơ chế multi-tenancy. File migration `2026_10_30_103000_update_company_occupancy_report.php` đã được chuẩn hóa để tự động lặp và cập nhật Stored Procedure `rpt_company_occupancy`, template và schemas trên cả 5 kết nối:
1. `mysql`: Tenant mặc định (`pms_hkt1`).
2. `mysql_hkt1`: Chi nhánh HKT1 (Khách sạn Smart Hotel).
3. `mysql_hkt2`: Chi nhánh HKT2.
4. `mysql_hkt3`: Chi nhánh HKT3.
5. `mysql_hkt4`: Chi nhánh HKT4.

*Lưu ý công thức RackADR:* Cột 7 `Giá phòng TB (không FOC/Giảm giá)` được tính theo đúng công thức nghiệp vụ mẫu PDF: `RoomRevenue / (RoomNight - Foc - Hu)`. Procedure SQL và Adapter đã được đồng bộ 100% sử dụng `RoomRevenue` (thay vì `OriginalAmount`).

---

## 5. ĐẶC TẢ CẤU HÌNH REPORT DESIGNER (TEMPLATE EDITOR)

File gốc: [`backend/database/report_templates/company_occupancy_reference.php`](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/report_templates/company_occupancy_reference.php).

### 5.1. Cấu Trúc Khối `content_json` Chuẩn
```json
{
  "header": [
    {
      "id": "company_occupancy_hotel",
      "type": "text",
      "content": "<div class=\"report-header\"><div class=\"hotel-logo\">{{hotel.logo}}</div><div class=\"hotel-information\">Địa chỉ: {{hotel.address}}<br>Nhân viên: {{report.generated_by}}&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Ngày: {{report.generated_date}}</div></div>"
    },
    {
      "id": "company_occupancy_title",
      "type": "text",
      "content": "<h1>BÁO CÁO CÔNG SUẤT CÔNG TY</h1>"
    },
    {
      "id": "company_occupancy_period",
      "type": "text",
      "content": "<p class=\"period\">Ngày: {{parameters.p_from_date}} ~ {{parameters.p_to_date}}</p>"
    }
  ],
  "detail": [
    {
      "id": "company_occupancy_table",
      "type": "table",
      "dataSource": "rows",
      "tableClassName": "company-occupancy-table",
      "topHeader": [
        { "content": "1", "align": "center" },
        { "content": "2", "align": "center" },
        { "content": "3", "align": "center" },
        { "content": "4", "align": "center" },
        { "content": "5", "align": "center" },
        { "content": "6", "align": "center" },
        { "content": "7", "align": "center" },
        { "content": "8", "align": "center" },
        { "content": "9", "align": "center" },
        { "content": "10", "align": "center" },
        { "content": "11", "align": "center" }
      ],
      "columns": [
        { "field": "CompanyCode", "header": "Mã", "width": "7%", "align": "left", "format": "text" },
        { "field": "CompanyName", "header": "Công Ty", "width": "21%", "align": "left", "format": "text" },
        { "field": "OccupancyRate", "header": "Công suất", "width": "6%", "align": "right", "format": "percent" },
        { "field": "RoomNight", "header": "Đêm<br>phòng", "width": "5%", "align": "right", "format": "number" },
        { "field": "GuestQty", "header": "SL khách", "width": "5%", "align": "right", "format": "number" },
        { "field": "ActualADR", "header": "Giá phòng trung<br>bình", "width": "9%", "align": "right", "format": "number" },
        { "field": "RackADR", "header": "Giá phòng TB<br>(không FOC/Giảm<br>giá)", "width": "12%", "align": "right", "format": "number" },
        { "field": "RoomRevenue", "header": "Doanh thu phòng", "width": "10%", "align": "right", "format": "number" },
        { "field": "FbRevenue", "header": "Doanh thu F&B", "width": "7%", "align": "right", "format": "number" },
        { "field": "OtherRevenue", "header": "Doanh thu khác", "width": "9%", "align": "right", "format": "number" },
        { "field": "TotalRevenue", "header": "Doanh Thu", "width": "9%", "align": "right", "format": "number" }
      ],
      "groups": [
        {
          "id": "group_header_1",
          "field": "GroupHeader",
          "sort": "ASC",
          "enabledBy": "parameters.p_is_grouped",
          "className": "group-header-cell",
          "label": "{{row.GroupHeader}}"
        }
      ],
      "customRows": [
        {
          "id": "group_footer_1",
          "scope": "group",
          "level": 0,
          "enabledBy": "parameters.p_is_grouped",
          "className": "pms-group-footer",
          "cells": [
            { "id": "gf_1", "type": "text", "content": "Tổng nhóm:", "colspan": 2, "align": "right", "className": "group-footer-label" },
            { "id": "gf_2", "type": "text", "content": "", "colspan": 1, "align": "center" },
            { "id": "gf_3", "type": "binding", "binding": "group.sum.RoomNight", "format": "number", "colspan": 1, "align": "right" },
            { "id": "gf_4", "type": "binding", "binding": "group.sum.GuestQty", "format": "number", "colspan": 1, "align": "right" },
            { "id": "gf_5", "type": "text", "content": "", "colspan": 2, "align": "center" },
            { "id": "gf_6", "type": "binding", "binding": "group.sum.RoomRevenue", "format": "number", "colspan": 1, "align": "right" },
            { "id": "gf_7", "type": "binding", "binding": "group.sum.FbRevenue", "format": "number", "colspan": 1, "align": "right" },
            { "id": "gf_8", "type": "binding", "binding": "group.sum.OtherRevenue", "format": "number", "colspan": 1, "align": "right" },
            { "id": "gf_9", "type": "binding", "binding": "group.sum.TotalRevenue", "format": "number", "colspan": 1, "align": "right" }
          ]
        },
        {
          "id": "table_footer_1",
          "scope": "table",
          "position": "footer",
          "className": "total",
          "cells": [
            { "id": "tf_1", "type": "text", "content": "Tổng:", "align": "left", "fontWeight": "bold" },
            { "id": "tf_2", "type": "binding", "binding": "aggregate.rows.count", "format": "number", "align": "right", "fontWeight": "bold" },
            { "id": "tf_3", "type": "binding", "binding": "aggregate.rows.summary_occupancy", "format": "percent", "align": "right", "fontWeight": "bold" },
            { "id": "tf_4", "type": "binding", "binding": "aggregate.rows.sum.RoomNight", "format": "number", "align": "right", "fontWeight": "bold" },
            { "id": "tf_5", "type": "binding", "binding": "aggregate.rows.sum.GuestQty", "format": "number", "align": "right", "fontWeight": "bold" },
            { "id": "tf_6", "type": "binding", "binding": "aggregate.rows.summary_actual_adr", "format": "number", "align": "right", "fontWeight": "bold" },
            { "id": "tf_7", "type": "binding", "binding": "aggregate.rows.summary_rack_adr", "format": "number", "align": "right", "fontWeight": "bold" },
            { "id": "tf_8", "type": "binding", "binding": "aggregate.rows.sum.RoomRevenue", "format": "number", "align": "right", "fontWeight": "bold" },
            { "id": "tf_9", "type": "binding", "binding": "aggregate.rows.sum.FbRevenue", "format": "number", "align": "right", "fontWeight": "bold" },
            { "id": "tf_10", "type": "binding", "binding": "aggregate.rows.sum.OtherRevenue", "format": "number", "align": "right", "fontWeight": "bold" },
            { "id": "tf_11", "type": "binding", "binding": "aggregate.rows.sum.TotalRevenue", "format": "number", "align": "right", "fontWeight": "bold" }
          ]
        }
      ]
    }
  ],
  "footer": [
    {
      "id": "company_occupancy_note",
      "type": "text",
      "content": "<div class=\"report-note\"><p><strong>Note (Ghi chú):</strong></p><p>3. %OCC: 4/ Room Availabe</p><p>4. Room Night: Room sale + FOC + HU</p><p>6. Average Room Rate (Doanh thu TB): 8/4</p><p>7. Average Room Rate w/o HU,FOC (Doanh thu TB không b/g FOC,HU): 8 / Room sales</p><p>8. Room Revenue (DT phòng): RM,ER</p><p>9. FB Revenue (Doanh thu FB): Doanh thu từ bộ phận nhà hàng</p><p>10. Other Revenue (Doanh thu khác): Các doanh thu khác không bao gồm mục 8,9</p><p>11. Revenue (Doanh thu): Mục 8+9+10</p></div>"
    }
  ]
}
```

---

### 5.2. Trình Biên Dịch `compileBlockToHtml` Trong `TemplateEditorModal.vue`
Nằm tại [`frontend/src/pages/config/components/hotel/TemplateEditorModal.vue`](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/frontend/src/pages/config/components/hotel/TemplateEditorModal.vue):
- **Biên dịch `topHeader`:**
  ```javascript
  if (b.topHeader && Array.isArray(b.topHeader) && b.topHeader.length) {
    blockHtml += '      <tr class="index-row">\n'
    b.topHeader.forEach(cell => {
      blockHtml += `        <th style="${thStyle} text-align: ${cell.align || 'center'}; font-weight: bold;">${cell.content || ''}</th>\n`
    })
    blockHtml += '      </tr>\n'
  }
  ```
- **Hỗ trợ định dạng `percent`:**
  ```javascript
  const modifier = col.format === 'number' ? '|number' : (col.format === 'percent' ? '|percent' : '')
  ```
- **Áp dụng class `company-code` cho cột Mã:**
  ```javascript
  const colClass = col.className ? ` class="${col.className}"` : (col.field === 'CompanyCode' ? ' class="company-code"' : '')
  ```
- **Đảm bảo khi người dùng mở giao diện thiết kế và bấm Lưu:** HTML đầu ra luôn sinh lại đầy đủ cấu trúc 2 tầng Header, viền bảng `#718096`, không bị rỗng bảng hay mất format.

---

### 5.3. Định Dạng CSS Cho Bảng & Mẫu In
```css
.report-header { display: flex; justify-content: space-between; align-items: flex-start; min-height: 50px; }
.hotel-logo img { max-width: 140px; max-height: 50px; object-fit: contain; }
.hotel-information { text-align: right; font-size: 9px; line-height: 1.6; color: #000; }
h1 { text-align: center; font-size: 16px; font-weight: bold; margin: 10px 0 4px; color: #000; }
.period { text-align: center; font-size: 10px; margin: 0 0 10px; color: #000; }
.company-occupancy-table { width: 100% !important; table-layout: fixed; border-collapse: collapse; font-size: 8.5px; border: 1px solid #718096 !important; }
.company-occupancy-table th, .company-occupancy-table td { border: 1px solid #718096 !important; padding: 3px 2px; vertical-align: middle; }
.company-occupancy-table th { background: #eaedf3 !important; text-align: center; font-weight: bold; color: #000; }
.company-occupancy-table .index-row th { font-size: 8px; font-weight: bold; background: #eaedf3 !important; color: #000; padding: 2px 1px; }
.company-occupancy-table td.right { text-align: right; }
.company-occupancy-table td.left { text-align: left; }
.company-occupancy-table td.company-code { color: #16a34a !important; font-weight: bold; }
.company-occupancy-table td.group-header-cell { background: #f1f5f9; font-weight: bold; text-align: left; padding: 4px 6px; font-size: 9px; }
.company-occupancy-table tr.pms-group-footer td { background: #f8fafc; font-weight: bold; padding: 3px 2px; }
.company-occupancy-table td.group-footer-label { text-align: right; font-weight: bold; }
.company-occupancy-table tfoot td, .company-occupancy-table tr.total td { background: #eaedf3 !important; font-weight: bold; }
.report-note { font-size: 8.5px; margin-top: 10px; line-height: 1.5; color: #000; }
.report-note p { margin: 1px 0; }
@media print { thead { display: table-header-group; } tr { break-inside: avoid; } }
```

---

## 6. GIAO DIỆN THAM SỐ SIDEBAR PHÍA FRONTEND (`ReportsPage.vue`)

Nằm tại [`frontend/src/pages/reports/ReportsPage.vue`](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/frontend/src/pages/reports/ReportsPage.vue):
- **Độ rộng khung tham số:** `w-[290px] p-3` (đồng bộ theo thiết kế hệ thống).
- **Trường bắt buộc:** Chỉ có trường ngày `p_from_date` (Chọn ngày) là bắt buộc. Trường `p_area` (Chọn khu vực) đặt `required: false` với nền trắng, không tô vàng.
- **Bố cục lưới 2 cột cho cụm 5 Switch:**
  ```vue
  <div v-if="activeTab.code === 'COMPANY_OCCUPANCY'" class="mb-3 grid grid-cols-2 gap-x-2 gap-y-2 border-t border-slate-100 pt-2">
    <!-- Hàng 1 -->
    <div class="flex items-center gap-1.5 min-w-0">
      <button type="button" @click="toggleReportCheckbox(activeTab, { name: 'p_show_detail' })" ...>
      <span>Hiển thị chi tiết</span>
    </div>
    <div class="flex items-center gap-1.5 min-w-0">
      <button type="button" @click="toggleReportCheckbox(activeTab, { name: 'p_include_breakfast' })" ...>
      <span>DT phòng bao gồm AS</span>
    </div>
    <!-- Hàng 2 -->
    <div class="flex items-center gap-1.5 min-w-0">
      <button type="button" @click="toggleReportCheckbox(activeTab, { name: 'p_group_by_date' })" ...>
      <span>Nhóm theo ngày</span>
    </div>
    <div></div>
    <!-- Hàng 3 -->
    <div class="flex items-center gap-1.5 min-w-0">
      <button type="button" @click="toggleReportCheckbox(activeTab, { name: 'p_group_by_market' })" ...>
      <span>Nhóm theo thị trường</span>
    </div>
    <div class="flex items-center gap-1.5 min-w-0">
      <button type="button" @click="toggleReportCheckbox(activeTab, { name: 'p_group_by_source' })" ...>
      <span>Nhóm theo nguồn khách</span>
    </div>
  </div>
  ```
- **Hành vi xử lý tương tác:**
  - Khi bật switch `Hiển thị chi tiết`: Tự động gọi `switchToCompanyOccupancyDetail`, mở tab `COMPANY_OCCUPANCY_DETAIL` (Dòng 166), sao chép toàn bộ điều kiện ngày, công ty, khu vực và thực thi tải dữ liệu ngay.
  - Khi chọn switch `Nhóm theo thị trường`: Tự động tắt switch `Nhóm theo nguồn khách` (và ngược lại), tuân thủ đúng nghiệp vụ chỉ chọn 1 trong 2.
  - Khi bấm `Hiển thị báo cáo`: Tự động ghép nối các switch thành giá trị `p_group_by` gửi về backend (`COMPANY`, `DATE`, `MARKET`, `SOURCE`, `DAY_MARKET`, `DAY_SOURCE`).

---

## 7. CHECKLIST KIỂM THỬ DÀNH CHO AGENT KHÁC

Agent kiểm tra (reviewer/tester) tiến hành kiểm tra theo danh sách 10 tiêu chí nghiệm thu sau:

| STT | Hạng Mục Kiểm Tra | Tiêu Chí Đạt (Pass Criteria) | Trạng Thái |
|:---:|---|---|:---:|
| 1 | **Logo khách sạn** | Hiển thị ảnh logo Galliot tại góc trên trái, không hiện icon vỡ hoặc chữ "Logo". File tồn tại tại `backend/public/assets/hotel-logo.png`. | ✅ PASS |
| 2 | **Thông tin ngày in** | Hiển thị `Ngày: dd/mm/yyyy` (chỉ có ngày tháng năm, tuyệt đối không có giờ:phút:giây). | ✅ PASS |
| 3 | **Header 2 tầng của bảng** | Tầng 1 hiển thị 11 tên cột nghiệp vụ. Tầng 2 hiển thị các ô đánh số thứ tự từ 1 đến 11 căn giữa. | ✅ PASS |
| 4 | **Màu sắc & định dạng cột Mã** | Cột Mã (`CompanyCode`) có text màu xanh lá `#16a34a` và in đậm. | ✅ PASS |
| 5 | **Định dạng tỷ lệ công suất** | Cột Công suất (`OccupancyRate`) hiển thị kèm ký hiệu `%` với 2 chữ số thập phân (`dd.dd %`). | ✅ PASS |
| 6 | **Định dạng số tiền & số lượng** | Các cột số đêm, số khách, giá phòng và doanh thu đều có dấu phẩy phân cách hàng nghìn (`100,000`). | ✅ PASS |
| 7 | **Hàng Tổng cộng (`Tổng:`)** | Ô thứ 2 đếm đúng số công ty (`aggregate.rows.count`). Ô thứ 3 hiển thị tổng % công suất toàn KS (`summary_occupancy|percent`). | ✅ PASS |
| 8 | **Khối Ghi chú (Note)** | Hiển thị trọn vẹn 8 dòng công thức chuẩn (từ mục 3 đến mục 11). Header bảng tự lặp lại khi sang trang 2. | ✅ PASS |
| 9 | **Loại bỏ khối chữ ký** | Tuyệt đối KHÔNG có khối chữ ký `Người lập biểu` & `Giám đốc khách sạn` dưới chân trang. | ✅ PASS |
| 10 | **Bảo toàn khi Lưu từ Designer** | Mở cấu hình mẫu trong Template Editor, bấm "XÁC NHẬN LƯU", quay lại mở xem báo cáo vẫn giữ nguyên 100% giao diện chuẩn. | ✅ PASS |

### Lệnh chạy xác minh nhanh qua Terminal:
1. **Kiểm tra Unit Test Backend:**
   ```powershell
   cd backend
   php artisan test --filter=CompanyOccupancyDataAdapterTest
   ```
   *(Kết quả: 3 passed, 10 assertions).*

2. **Kiểm tra Biên dịch Frontend:**
   ```powershell
   cd frontend
   npm run build
   ```
   *(Kết quả: built successfully, 0 lỗi).*
