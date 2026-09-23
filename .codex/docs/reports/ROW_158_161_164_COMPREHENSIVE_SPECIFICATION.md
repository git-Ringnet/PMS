# TÀI LIỆU ĐẶC TẢ CHI TIẾT TRIỂN KHAI BÁO CÁO DÒNG 158, 161, 164 (DANH MỤC BÁO CÁO)

> **Dành cho Agent triển khai:** Tài liệu này được biên soạn đầy đủ, khép kín và cặn kẽ 100% về mặt kiến trúc, cơ sở dữ liệu, phân tích giao diện (ảnh chụp hệ thống legacy), Stored Procedure gốc trong SQL Server (SSMS) với lưu ý đọc chính xác store chỉ định, thuật toán chuyển đổi sang MySQL 8.0, và toàn bộ thông số kỹ thuật cho Report Designer / Form Designer (`content_json`, columns, blocks, customRows). Bạn có thể đọc và triển khai trực tiếp mà không cần tra cứu thêm tài liệu khác.

---

## MỤC LỤC
1. [Tổng quan dự án & Bản đồ liên kết 3 báo cáo](#1-tổng-quan-dự-án--bản-đồ-liên-kết-3-báo-cáo)
2. [Quy trình triển khai chuẩn trong hệ thống PMS](#2-quy-trình-triển-khai-chuẩn-trong-hệ-thống-pms)
3. [Dòng 158: Báo Cáo Thu Ngân Lễ Tân (sp_039 Navy)](#3-dòng-158-báo-cáo-thu-ngân-lễ-tân-sp_039-navy)
4. [Dòng 161: Báo Cáo Doanh Thu Hai Giai Đoạn (sp_217 Army)](#4-dòng-161-báo-cáo-doanh-thu-hai-giai-đoạn-sp_217-army)
5. [Dòng 164: Báo Cáo Dự Kiến Doanh Thu Tiền Phòng - Màn Hình Sang Ngày (sp_095)](#5-dòng-164-báo-cáo-dự-kiến-doanh-thu-tiền-phòng---màn-hình-sang-ngày-sp_095)
6. [Kế hoạch kiểm thử & Lệnh xác minh (Verification Guide)](#6-kế-hoạch-kiểm-thử--lệnh-xác-minh-verification-guide)

---

## 1. TỔNG QUAN DỰ ÁN & BẢN ĐỒ LIÊN KẾT 3 BÁO CÁO

### 1.1. Kiến Trúc Cơ Sở Dữ Liệu Đa Chi Nhánh (Multi-Tenancy)
- **Backend:** Laravel 11.x, PHP 8.2+, MySQL 8.0+.
- **Frontend:** Vue 3 (Composition API), Vite, TailwindCSS / Custom Vanilla CSS cho Report Engine.
- **5 Database Connections Độc Lập:**
  - `mysql`: Database khách sạn chính (Primary Tenant).
  - `mysql_hkt1`, `mysql_hkt2`, `mysql_hkt3`, `mysql_hkt4`: Database của 4 chi nhánh.
  - `mysql_system`: Quản lý người dùng, phân quyền, danh mục chi nhánh dùng chung.
  - **Quy tắc bất biến:** Khi tạo Migration hoặc Stored Procedure mới cho báo cáo, **phải lặp qua toàn bộ 5 connection** `['mysql', 'mysql_hkt1', 'mysql_hkt2', 'mysql_hkt3', 'mysql_hkt4']` để tạo đồng bộ. Chạy migrate toàn hệ thống: `php artisan migrate:all --force`.

### 1.2. Bản Đồ Liên Kết 3 Báo Cáo Được Phân Tích

| Thuộc Tính | Dòng 158 (STT 12) | Dòng 161 (STT 16) | Dòng 164 (STT 18) |
|---|---|---|---|
| **Tên báo cáo** | Báo cáo thu ngân lễ tân | Báo cáo doanh thu hai giai đoạn | Báo cáo dự kiến doanh thu tiền phòng |
| **Mã báo cáo (`code`)** | `RECEPTION_CASHIER_SHIFT` | `TWO_PERIOD_REVENUE` | `EXPECTED_ROOM_REVENUE_NIGHT_AUDIT` |
| **Mã Data Source** | `RPT_RECEPTION_CASHIER_SHIFT` | `RPT_TWO_PERIOD_REVENUE` | `RPT_EXPECTED_ROOM_REVENUE_NIGHT_AUDIT` |
| **Mã Template** | `RECEPTION_CASHIER_SHIFT_REFERENCE` | `TWO_PERIOD_REVENUE_REFERENCE` | `EXPECTED_ROOM_REVENUE_REFERENCE` |
| **Store Gốc Chỉ Định** | `ProVistaNavyHotel.dbo.sp_039` | `ProVistaArmyHotel.dbo.sp_217` | `ProVistaArmyHotel.dbo.sp_095` |
| **Lưu Ý Đặc Biệt** | Xem của Navy; cấu hình bộ phận `Report_ListDepartmentCashierShiftReport` (`FO,FB,MR,ACC`), mặc định `FO` | Xem theo Army; Bổ sung cột "Ngày dịch vụ", lọc dịch vụ multi-select | Đặt tại màn hình Sang ngày (Night Audit); popup xem trước doanh thu trước khi đóng ngày |
| **Ảnh UI Mẫu** | [dong_158_ui_mau.png](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/images/dong_158_ui_mau.png) | [dong_161_ui_mau.png](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/images/dong_161_ui_mau.png) | [dong_164_ui_mau.png](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/images/dong_164_ui_mau.png) & [dong_164_popup_ui_mau.png](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/images/dong_164_popup_ui_mau.png) |
| **File Tài Liệu Riêng** | [dong_158_bao_cao_thu_ngan_le_tan.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/dong_158_bao_cao_thu_ngan_le_tan.md) | [dong_161_bao_cao_doanh_thu_hai_giai_doan.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/dong_161_bao_cao_doanh_thu_hai_giai_doan.md) | [dong_164_bao_cao_du_kien_doanh_thu_tien_phong.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/dong_164_bao_cao_du_kien_doanh_thu_tien_phong.md) |

---

## 2. QUY TRÌNH TRIỂN KHAI CHUẨN TRONG HỆ THỐNG PMS

Để triển khai bất kỳ báo cáo nào trong 3 báo cáo trên, Agent thực hiện theo quy trình 4 bước khép kín:

```
[Bước 1: Tạo Template Reference] 
    └── backend/database/report_templates/<code_name>_reference.php
[Bước 2: Tạo Migration Đa Chi Nhánh] 
    └── backend/database/migrations/YYYY_MM_DD_HHMMSS_create_<code_name>_report.php
          ├── Drop & Create Procedure rpt_<code_name> (Lặp qua 5 connections)
          ├── Upsert report_data_sources
          ├── Upsert templates (content_json, html, css, columns)
          ├── Upsert report_definitions (kèm parameter_ui_schema)
          └── Upsert report_definition_template
[Bước 3: Chạy Lệnh Migrate Toàn Hệ Thống]
    └── php artisan migrate:all --force
[Bước 4: Viết Feature Test Kiểm Thử Tự Động]
    └── backend/tests/Feature/<ReportName>ReportTest.php
```

---

## 3. DÒNG 158: BÁO CÁO THU NGÂN LỄ TÂN (SP_039 NAVY)

### 3.1. Phân Tích Store Gốc Navy vs Army
* **Tại sao phải xem store của Navy?**
  * Trong database của Army (`ProVistaArmyHotel.dbo.sp_039`), logic bị ràng buộc cứng với bảng F&B riêng lẻ (`SP5000`) và bảng kế toán nội bộ của Army (`SP3007`).
  * Trong database của Navy (`ProVistaNavyHotel.dbo.sp_039`), logic tổng quát và chuẩn hóa hơn: sử dụng bảng tạm `#tempKhachLe` từ `SP2000` (đăng ký) và `SP2100` (phòng) để gán chính xác số phòng cho khách lẻ ngay cả khi giao dịch thanh toán không lưu trực tiếp mã phòng (`isnull(bcdt.Room, tmp.Room)`).
* **Cấu hình danh sách bộ phận lọc**:
  * Tên tham số cấu hình hệ thống: `Report_ListDepartmentCashierShiftReport`.
  * Giá trị danh sách: `FO,FB,MR,ACC` (`FO`: Lễ tân, `FB`: Nhà hàng, `MR`: Spa/Massage, `ACC`: Kế toán).
  * Giao diện báo cáo mặc định chọn `FO` (Reception / Lễ tân) và hỗ trợ đa chọn (multi-select).
* **Quy tắc Thẻ Tín Dụng (Credit Card)**:
  * Khi `PaymentMethod = 'CD'`, che số thẻ giữ lại 4 số cuối: `replicate('*', len(card.CardId) - 4) + RIGHT(card.CardId, 4)`.

### 3.2. Cấu Trúc Giao Diện 3 Bảng & Footer Chữ Ký
1. **Bảng 1: Chi tiết giao dịch thu ngân (Main Grid)**:
   * 11 cột: `Mã ĐK`, `Phòng`, `Tên Khách`, `Ngày Đến`, `Ngày Đi`, `Giờ`, `Mã TT`, `Số Tiền`, `Người dùng`, `Mô Tả`, `Ghi Chú DT FB`.
   * Grouping 2 cấp:
     * Cấp 1: Loại giao dịch (`Deposit` / `Cashier` / `Hoàn Trả`).
     * Cấp 2: Phương thức thanh toán (`Thanh Toán: CA - Cash/Tiền mặt`, `CD - Thẻ tín dụng`...).
   * Subtotals cho từng cấp và dòng tổng cộng bảng.
2. **Bảng 2: Bảng Phân Bổ Tiền Tệ (Distribution Summary Table)**:
   * 6 cột: `HTTT`, `Thu Ngân`, `Đặt Cọc`, `Thu Ngân + Đặt Cọc`, `Hoàn Tiền`, `Tổng`.
3. **Bảng 3: Tổng Hợp Công Nợ Công Ty (City Ledger Summary Table)**:
   * 4 cột: `HTTT` (`AC (City ledger/Công nợ)`), `Tổng`, `Đã Thanh Toán`, `Còn Lại`.
4. **Footer chữ ký 3 bên**: `Nhân viên` | `Trưởng phòng` | `Bộ phận kế toán`.

### 3.3. File Template Reference Đầy Đủ (Form Designer)
* **Vị trí file:** `backend/database/report_templates/reception_cashier_shift_reference.php`.
* Chi tiết mã nguồn class PHP đầy đủ 100% các khối `blocks()`, `columns()`, `html()`, `css()` đã được đặc tả chi tiết tại tài liệu [dong_158_bao_cao_thu_ngan_le_tan.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/dong_158_bao_cao_thu_ngan_le_tan.md#3-cấu-trúc-toàn-diện-100-của-content_json-form-designer).

### 3.4. Stored Procedure MySQL 8.0 (`rpt_reception_cashier_shift`)
* **Vị trí file:** `backend/database/procedures/rpt_reception_cashier_shift.sql`.
* Bao gồm 3 khối truy vấn trả về:
  1. Main Grid chi tiết thanh toán kết hợp phân loại `Deposit` / `Cashier` / `Hoàn Trả`.
  2. Summary Grid phân bổ tiền tệ theo phương thức thanh toán.
  3. Summary Grid tổng hợp công nợ `AC`.

---

## 4. DÒNG 161: BÁO CÁO DOANH THU HAI GIAI ĐOẠN (SP_217 ARMY)

### 4.1. Nghiệp Vụ Cốt Lõi: Hai Giai Đoạn Là Gì?
* Báo cáo truy vết các dịch vụ **được sử dụng/post ở một tháng nhưng lại thanh toán ở tháng khác**.
* *Ví dụ*: Khách lưu trú từ 28/08 đến 02/09. Dịch vụ giặt ủi hoặc nhà hàng phát sinh ngày 29/08. Đến ngày 02/09 khách mới thanh toán hóa đơn check-out. Khi quản lý xem báo cáo doanh thu tháng 9 (01/09 - 30/09), báo cáo này sẽ liệt kê các bill dịch vụ tháng 8 được thanh toán vào tháng 9 để kế toán đối soát doanh thu phát sinh trước đó.
* **Điều kiện lọc trong SQL Server**:
  ```sql
  ((DATEPART(Month, vw.Date) <> DATEPART(Month, hd.Date))
   OR (DATEPART(Year, vw.Date) <> DATEPART(Year, hd.Date)))
  ```
  *(với `vw.Date` là ngày dịch vụ trong `SP3000`, `hd.Date` là ngày thanh toán hóa đơn trong `SP3003`)*.

### 4.2. Hai Điểm Nâng Cấp Bắt Buộc Theo Yêu Cầu Của Army
1. **Bổ sung cột "Ngày dịch vụ" (`DateHDDV`)**: Nằm ngay sau cột "Tên khách", hiển thị ngày phát sinh thực tế của dịch vụ từ cột `service_date` (`SP3000.Date`).
2. **Bộ lọc dịch vụ cho phép chọn nhiều (`p_services` multi-select)**: Store cũ `sp_217` có lỗi là khi `@Service <> ''` chỉ lọc được 1 mã dịch vụ đơn lẻ và bị mất cột ngày dịch vụ. Store MySQL 8.0 mới xử lý hoàn hảo bằng hàm `FIND_IN_SET(sb.service_id, p_services)` hỗ trợ danh sách dịch vụ chọn nhiều.

### 4.3. Bố Cục 17 Cột Dữ Liệu
`STT`, `Mã HĐ`, `Mã ĐK`, `Ngày Đến`, `Ngày Đi`, `Tên Khách`, **`Ngày Dịch Vụ`** (cột mới), `Mô Tả`, `Giá Gốc`, `Phí Dịch Vụ`, `Thuế Đặc Biệt`, `Thuế`, `Doanh Thu`, `Hình Thức Thanh Toán`, `Công Ty`, `Người Dùng`, `Giờ`.
* Grouping 2 cấp: `Outlet` -> `Dịch Vụ`.
* Subtotal theo Outlet và Grand Total toàn bộ.
* Chi tiết template PHP và Stored Procedure MySQL 8.0: Xem chi tiết tại [dong_161_bao_cao_doanh_thu_hai_giai_doan.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/dong_161_bao_cao_doanh_thu_hai_giai_doan.md).

---

## 5. DÒNG 164: BÁO CÁO DỰ KIẾN DOANH THU TIỀN PHÒNG - MÀN HÌNH SANG NGÀY (SP_095)

### 5.1. Vị Trí Nghiệp Vụ Đặc Thù: Sang Ngày (Night Audit)
* Khác với các báo cáo thông thường nằm trong menu Báo cáo, **Báo cáo dự kiến doanh thu tiền phòng** được đặt trực tiếp tại màn hình **Sang Ngày (Night Audit)**.
* **Mục đích:** Trước khi bấm nút thực hiện đóng ngày (Day Close / Night Audit), kế toán đêm và trưởng ca lễ tân cần kiểm tra trước toàn bộ doanh thu tiền phòng và các dịch vụ cố định hàng ngày dự kiến sẽ được hệ thống tự động post vào tài khoản phòng để phát hiện sai lệch giá, phòng chưa check-in/check-out hoặc thiếu dịch vụ.
* Nút bấm: `Báo cáo dự kiến doanh thu tiền phòng` (nằm ở thanh thao tác phía dưới bên phải màn hình Sang ngày, xem ảnh `dong_164_ui_mau.png`). Khi bấm sẽ mở Popup in ấn `dong_164_popup_ui_mau.png`.

### 5.2. Công Thức Gom Doanh Thu Dự Kiến
Báo cáo quét tất cả các phòng đang lưu trú thỏa mãn:
`p_date >= br.arrival_date AND p_date < br.departure_date AND br.status IN (0, 1)`.
Doanh thu bao gồm 2 nguồn:
1. **Tiền phòng (`ServiceId = 'RM'`)**: Lấy giá tiền phòng được gán theo ngày của phòng đó (`booking_room_rates` / `SP2100.Rate`).
2. **Dịch vụ cố định hàng ngày (`ServiceId <> 'RM'`)**: Lấy các dịch vụ có `FromDate = p_date` và `Quantity <> 0` trong bảng `booking_room_daily_services` (`SP2102`) như ăn sáng phụ, giường phụ, dịch vụ đón tiễn...

### 5.3. Bố Cục 11 Cột Báo Cáo Popup
`STT`, `Mã BK`, `Khách`, `Phòng`, `Ngày Đến`, `Ngày Đi`, `Dịch Vụ`, `Mô Tả Dịch Vụ`, `Tổng`, `Công Ty`, `Ghi Chú`.
* Chi tiết template PHP và Stored Procedure MySQL 8.0: Xem chi tiết tại [dong_164_bao_cao_du_kien_doanh_thu_tien_phong.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/dong_164_bao_cao_du_kien_doanh_thu_tien_phong.md).

---

## 6. KẾ HOẠCH KIỂM THỬ & LỆNH XÁC MINH (VERIFICATION GUIDE)

Sau khi tạo code, Agent thực hiện các lệnh kiểm tra sau trong terminal:

### 6.1. Kiểm Tra Stored Procedure và Schema Migration
```bash
# 1. Chạy migration trên toàn bộ các connection chi nhánh
cd backend
php artisan migrate:all --force

# 2. Kiểm tra Stored Procedure tồn tại trong MySQL
php artisan tinker --execute="DB::select('SHOW PROCEDURE STATUS WHERE Name IN (\'rpt_reception_cashier_shift\', \'rpt_two_period_revenue\', \'rpt_expected_room_revenue_night_audit\')');"
```

### 6.2. Kiểm Tra Endpoint API Trả Về Dữ Liệu
```bash
# 1. Danh sách định nghĩa báo cáo (Definitions)
curl -s -H "Accept: application/json" http://localhost:8000/api/reports/definitions | jq '.data[] | select(.code | contains("RECEPTION_CASHIER") or contains("TWO_PERIOD") or contains("EXPECTED_ROOM"))'

# 2. Dữ liệu báo cáo Thu ngân lễ tân (Dòng 158)
curl -s -X POST -H "Content-Type: application/json" -H "Accept: application/json" \
  http://localhost:8000/api/reports/data \
  -d '{"report_code": "RECEPTION_CASHIER_SHIFT", "parameters": {"p_from_date": "2026-07-01", "p_to_date": "2026-07-31", "p_department": "FO"}}'

# 3. Dữ liệu báo cáo Doanh thu hai giai đoạn (Dòng 161)
curl -s -X POST -H "Content-Type: application/json" -H "Accept: application/json" \
  http://localhost:8000/api/reports/data \
  -d '{"report_code": "TWO_PERIOD_REVENUE", "parameters": {"p_from_date": "2026-09-01", "p_to_date": "2026-09-30"}}'

# 4. Dữ liệu báo cáo Dự kiến doanh thu tiền phòng (Dòng 164)
curl -s -X POST -H "Content-Type: application/json" -H "Accept: application/json" \
  http://localhost:8000/api/reports/data \
  -d '{"report_code": "EXPECTED_ROOM_REVENUE_NIGHT_AUDIT", "parameters": {"p_date": "2026-08-15"}}'
```

### 6.3. Kiểm Tra Frontend Build
```bash
cd frontend
npm run build
```
