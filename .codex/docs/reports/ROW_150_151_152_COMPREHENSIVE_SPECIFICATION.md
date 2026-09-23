# TÀI LIỆU ĐẶC TẢ CHI TIẾT TRIỂN KHAI BÁO CÁO DÒNG 150, 151, 152 (DANH MỤC BÁO CÁO)

> **Dành cho Agent triển khai:** Tài liệu này được biên soạn đầy đủ, khép kín và cặn kẽ 100% về mặt kiến trúc, cơ sở dữ liệu, phân tích giao diện (ảnh chụp hệ thống legacy), Stored Procedure gốc trong SQL Server (SSMS), thuật toán chuyển đổi sang MySQL 8.0, và toàn bộ thông số kỹ thuật cho Report Designer / Form Designer. Bạn có thể đọc và triển khai trực tiếp mà không cần tra cứu thêm tài liệu khác.

---

## MỤC LỤC
1. [Tổng quan dự án & Kiến trúc Report Engine](#1-tổng-quan-dự-án--kiến-trúc-report-engine)
2. [Quy trình triển khai 1 Báo cáo mới chuẩn trong PMS](#2-quy-trình-triển-khai-1-báo-cáo-mới-chuẩn-trong-pms)
3. [Dòng 150: Báo cáo Doanh thu (Army Quy Nhơn) - sp_292](#3-dòng-150-báo-cáo-doanh-thu-army-quy-nhơn---sp_292)
4. [Dòng 151: Báo cáo Doanh thu đăng ký theo ngày đi - sp_238 & sp_240](#4-dòng-151-báo-cáo-doanh-thu-đăng-ký-theo-ngày-đi---sp_238--sp_240)
5. [Dòng 152: Báo cáo Doanh thu lễ tân_Army - sp_293](#5-dòng-152-báo-cáo-doanh-thu-lễ-tân_army---sp_293)
6. [Kế hoạch kiểm thử & Lệnh xác minh (Verification Guide)](#6-kế-hoạch-kiểm-thử--lệnh-xác-minh-verification-guide)

---

## 1. TỔNG QUAN DỰ ÁN & KIẾN TRÚC REPORT ENGINE

### 1.1. Công nghệ & Môi trường
- **Backend:** Laravel 11.x, PHP 8.2+, MySQL 8.0+.
- **Frontend:** Vue 3 (Composition API), Vite, TailwindCSS / Custom Vanilla CSS cho Report Designer.
- **Đa chi nhánh (Multi-tenancy):**
  - Hệ thống sử dụng mô hình cơ sở dữ liệu riêng biệt cho từng chi nhánh.
  - Connection mặc định: `mysql` (khách sạn chính).
  - Connections chi nhánh: `mysql_hkt1`, `mysql_hkt2`, `mysql_hkt3`, `mysql_hkt4`.
  - Connection hệ thống dùng chung: `mysql_system` (lưu users, branches, subscriptions...).
  - **Quy tắc bất biến:** Khi tạo migration cho báo cáo hoặc Stored Procedure, **phải áp dụng đồng bộ trên tất cả 5 connections** `['mysql', 'mysql_hkt1', 'mysql_hkt2', 'mysql_hkt3', 'mysql_hkt4']`. Lệnh migrate toàn hệ thống: `php artisan migrate:all --force`.

### 1.2. Kiến trúc Report Engine của PMS mới
Một báo cáo hoạt động thông qua chuỗi thành phần liên kết:
1. **Stored Procedure MySQL (`rpt_...`):** Thực thi câu truy vấn trích xuất và tính toán dữ liệu, nhận tham số đầu vào (`p_from_date`, `p_to_date`, filter...). Chạy trực tiếp trong database chi nhánh để tối ưu hiệu năng.
2. **Bảng `report_data_sources`:** Đăng ký nguồn dữ liệu (`code`, `source_type = 'procedure'`, `object_name = 'rpt_...'`, `parameter_schema`, `field_schema`, `sample_parameters`).
3. **Bảng `templates`:** Chứa định nghĩa mẫu in / hiển thị (`report` code, `content_json` cho Report Designer, `content_html` cho render blade/HTML, `css` cho print preview, `page_size`, `page_orientation`, `margin_*`).
4. **Bảng `report_definitions`:** Định nghĩa nghiệp vụ của báo cáo (`code`, `name`, `group`, `report_data_source_id`, `parameter_ui_schema` quy định form filter trên frontend, `menu_locations`, menu sorting).
5. **Bảng `report_definition_template`:** Bảng pivot liên kết `report_definition_id` và `template_id` (đánh dấu `is_default = true`).
6. **File tham chiếu Template (`backend/database/report_templates/<code_name>_reference.php`):** Định nghĩa cấu trúc PHP class chuẩn trả về array cấu hình (`definition()`, `html()`, `blocks()`, `css()`, `columns()`).

---

## 2. QUY TRÌNH TRIỂN KHAI 1 BÁO CÁO MỚI CHUẨN TRONG PMS

Khi triển khai báo cáo, Agent thực hiện đúng 4 bước chuẩn hóa:
- **Bước 1: Tạo file Template Reference:** Tạo file `backend/database/report_templates/<code_name>_reference.php` định nghĩa HTML, CSS, blocks JSON, columns, customRows.
- **Bước 2: Tạo file Migration tổng hợp:** Tạo migration trong `backend/database/migrations/YYYY_MM_DD_HHMMSS_create_<code_name>_report.php`:
  - Lặp qua 5 connection (`mysql`, `mysql_hkt1` đến `mysql_hkt4`).
  - Tạo Stored Procedure `rpt_<code_name>`.
  - Insert/Update `report_data_sources`.
  - Insert/Update `templates` (đọc từ file reference).
  - Insert/Update `report_definitions` kèm `parameter_ui_schema`.
  - Insert/Update `report_definition_template`.
- **Bước 3: Chạy migration đa chi nhánh:** Chạy `php artisan migrate:all --force`.
- **Bước 4: Viết Feature Test:** Tạo test trong `backend/tests/Feature/<ReportName>ReportTest.php` kiểm tra:
  - Stored Procedure tồn tại và thực thi được với connection MySQL.
  - Report Definition, Data Source, Template đã liên kết hợp lệ.
  - Endpoint `/api/reports/definitions` và `/api/reports/data` trả về đúng schema.

---

## 3. DÒNG 150: BÁO CÁO DOANH THU (ARMY QUY NHƠN) - SP_292

### 3.1. Thông tin từ danh mục báo cáo & Phân tích ảnh gốc
- **Dòng Excel:** 150.
- **Tên báo cáo:** Báo cáo doanh thu.
- **Đơn vị áp dụng:** Đơn vị Army Quy Nhơn.
- **Mã báo cáo mới:** `REVENUE_ARMY` (hoặc `REVENUE_DAILY_ARMY`).
- **Mã Stored Procedure legacy:** `sp_292`.
- **Ảnh phân tích UI mẫu:** [row_150_image102.png](file:///C:/Users/Nguyen%20Tho%20Thang/.gemini/antigravity-ide/brain/1c24e0d7-6f33-41ef-b79b-a4409f2c5eba/scratch/row_150_image102.png).

#### Phân tích giao diện từ ảnh `row_150_image102.png`:
- **Thanh Filter (Left Sidebar):**
  - `Ngày`: Date picker chọn 1 ngày duy nhất (Mặc định: ngày hiện tại `$today`, ví dụ trên ảnh: `15/07/2026`).
  - `Công ty`: Dropdown chọn công ty/đoàn (Mặc định: Tất cả `0`).
  - `Đăng ký`: Dropdown chọn mã đặt phòng/booking (Mặc định: Tất cả `0`).
  - Nút bấm: `Hiển thị báo cáo`.
- **Thông tin tiêu đề trang in (Report Header):**
  - Trái: Logo khách sạn (`{{hotel.logo}}`) và tên khách sạn (`GOLDEN CREST HOTEL QUY NHON`).
  - Phải: Địa chỉ (`66 Hàn Mặc Tử, P. Quy Nhơn Nam...`), Người dùng: `admin`, Ngày in: `{{report.generated_at}}`.
  - Tiêu đề chính: `BÁO CÁO DOANH THU` (Chữ hoa, đậm, canh giữa, cỡ chữ 18px).
  - Dòng phụ: `Ngày: 15/07/2026` (Canh giữa).
- **Khổ giấy:** A4 Ngang (`landscape`), margins: Top 8mm, Bottom 8mm, Left 6mm, Right 6mm.
- **Cấu trúc bảng dữ liệu (Header 2 tầng, tổng cộng 22 cột):**
  - **Tầng 1 (Super Headers):**
    - `STT` (rowspan 2)
    - `Mã đăng ký` (rowspan 2)
    - `Tên khách` (rowspan 2)
    - `Đơn vị` (rowspan 2)
    - `Ngày đến` (rowspan 2)
    - `Ngày đi` (rowspan 2)
    - `SP` (rowspan 2 - Số lượng phòng)
    - `Các khoản thu trong ngày` (colspan 7: Tiền phòng, Phụ thu tiền phòng, Minibar, Giặt, Bể vỡ, Nhà hàng, Dịch vụ)
    - `Tổng DT` (rowspan 2)
    - `DT ngày trước` (rowspan 2)
    - `Tổng cộng` (rowspan 2)
    - `Phòng đã trả` (colspan 4: TM, CK, HH, Còn nợ)
    - `Phòng còn ở` (rowspan 2)
  - **Tầng 2 (Sub Headers chi tiết):**
    - [1] STT
    - [2] Mã đăng ký
    - [3] Tên khách (Format: `Tên khách - Số phòng`, vd: "Cô Nhung - 1208, 1207, 1206, 1211")
    - [4] Đơn vị (Tên công ty/đối tác OTA, vd: "NKQN- C.Hường", "Expedia", "KHÁCH LẺ")
    - [5] Ngày đến (dd/mm/yyyy)
    - [6] Ngày đi (dd/mm/yyyy)
    - [7] SP (Số phòng, căn giữa)
    - [8] Tiền phòng (`RoomToday`)
    - [9] Phụ thu tiền phòng (`ExtraRoomToday` bao gồm EB, EP, ER, KC, UP, EI, LO và phụ thu ăn sáng BD, BF)
    - [10] Minibar (`MinibarToday`)
    - [11] Giặt (`LaundryToday`)
    - [12] Bể vỡ (`BrokenToday`)
    - [13] Nhà hàng (`RestaurantToday`)
    - [14] Dịch vụ (`OtherToday` - Các dịch vụ còn lại)
    - [15] Tổng DT (`TotalToday` = Tổng các khoản thu trong ngày)
    - [16] DT ngày trước (`PrevDay` - Doanh thu lũy kế các ngày trước của booking)
    - [17] Tổng cộng (`TotalRevenue` = Tổng DT + DT ngày trước)
    - [18] TM (Tiền mặt đã trả `Cash`)
    - [19] CK (Chuyển khoản / thẻ đã trả `BankTransfer`)
    - [20] HH (Hoa hồng `Commission`)
    - [21] Còn nợ (`CityLedger` - Thanh toán công nợ AC)
    - [22] Phòng còn ở (`InhouseRoom` = Tổng cộng - (TM + CK + HH + Còn nợ))
- **Dòng tổng cộng (Summary Row):**
  - Cột 1-6 gộp: "Tổng số BK: 8" (Đếm số lượng đăng ký).
  - Cột 7 (SP): Tổng số phòng (vd: 13).
  - Cột 8-22: Tổng tiền tương ứng của từng cột.
- **Chân trang chữ ký (5 khối chữ ký theo mẫu Quân đội):**
  - Dòng địa danh: `Quy Nhơn, Ngày ... Tháng ... Năm ...`
  - 5 vị trí ký ngang nhau:
    1. `Chữ Ký Người Lập`
    2. `Trưởng Bộ Phận`
    3. `Kế Toán`
    4. `Tổng Quản Lý`
    5. `Giám Đốc`

---

### 3.2. Stored Procedure gốc trong SSMS (`sp_292`)
Mã nguồn trích xuất từ database `ProVistaArmyHotel`:
```sql
CREATE procedure [dbo].[sp_292]( @date datetime, @companyId int = 0, @bookingId int= 0)
as
begin
	declare @prefix varchar(20) = (select top 1 isnull(PrefixBookingId, '') from sp1322)
	
	;with RoomTable as (
		Select BookingId, STRING_AGG(Room, ', ') as RoomString, isnull(count(Room), 0) as RoomNo
		from SP2100
		where status in (0, 1, 2, 4, 100)
		group by BookingId
	)
	select dk.Ma, concat(dk.BookingName, case when isnull(pt.RoomNo, 0) = 0 then '' else ' - ' end, pt.RoomString) BookingName, ct.Company, dk.ArrivalDate, dateadd(day, dk.NumOfDays, dk.ArrivalDate) as DepartureDate, isnull(pt.RoomNo, 0) RoomNo
	into #tempBooking
	from SP2000 dk
	left join RoomTable pt on pt.BookingId = dk.Ma
	left join SP1302 ct on ct.Ma = dk.TravelAgency
	where
	((@date between dk.ArrivalDate and dateadd(day, dk.NumOfDays, dk.ArrivalDate) and status != 3)
	or (dk.Ma in (select isnull(pt.Bookingid, hd.RegisterID2) from func_054(@date, @date,'') dt 
	left join sp3000 hd on hd.Ma = dt.BillIdService
	left join SP2100 pt on hd.RentalRoomId2 = pt.Ma)))
	and (@companyId = 0 or dk.TravelAgency = @companyId)
	and (@bookingId = 0 or dk.Ma = @bookingId)

	declare @minDate date = (select min(ArrivalDate) from #tempBooking)

	select dt.RentalRoomId, dt.ServiceId, dt.BookingId, dt.Date, dt.Total, pt.Status, isnull(hd.RegisterId2, pt.BookingId) as BookingId2
	into #tempRevenue
	From func_054(@minDate, @date,'') dt
	left join SP3000 hd on hd.Ma = dt.BillIdService
	left join SP2100 pt on pt.Ma = case when dt.ServiceId = 'RM' then dt.RentalRoomId else isnull(hd.RentalRoomId2, dt.RentalRoomId) end
	where pt.Bookingid in (select Ma from #tempBooking)
	union all
	select null, ServiceId, RegisterID2, date, amount, dk.Status , dk.ma from SP3000 hd
	left join Sp2000 dk on hd.RegisterID2 = dk.ma
	where edit = 0 and RentalRoomId1 is null and RentalRoomId2 is null and dk.Ma in (select Ma from #tempBooking)

	select isnull(dk.Ma, pt.BookingId) BookingId, 
      sum(case when tt.PaymentMethod = 'CA' then tt.Amount else 0 end) as Cash,
      sum(case when tt.PaymentMethod in ('BT','CD') then tt.Amount else 0 end) as BankTransfer,
      sum(case when tt.PaymentMethod = 'AC' then tt.Amount else 0 end) as CityLedger,
      sum(case when tt.PaymentMethod = 'HH' then tt.Amount else 0 end) as Commission,
      sum(case when tt.PaymentMethod not in ('AC','BT','CD','CA') then tt.Amount else 0 end) as Other
	into #tempPayment
	from SP3002 tt
	left join SP2000 dk on tt.RegisterID2 = dk.Ma
	left join SP2100 pt on tt.RentalRoomId2 = pt.Ma
	where edit = 0 and (RegisterID2 in (select BookingId from #tempRevenue) or RentalRoomId2 in (select RentalRoomId from #tempRevenue))
	and date <= @date
	group by isnull(dk.Ma, pt.BookingId)

	select BookingId2, 
      sum(case when rev.ServiceId = 'RM' and rev.Date = @date then rev.Total else 0 end) as RoomToday,
      sum(case when rev.ServiceId in ('EB', 'EP', 'ER', 'KC', 'UP', 'EI', 'LO') and rev.Date = @date then rev.Total else 0 end) as ExtraRoomToday,
      sum(case when rev.ServiceId in ('BD', 'BF') and rev.Date = @date then rev.Total else 0 end) as BreakfastSurchargeToday,
      sum(case when rev.ServiceId = 'LA' and rev.Date = @date then rev.Total else 0 end) as LaundryToday,
      sum(case when rev.ServiceId = 'MB' and rev.Date = @date then rev.Total else 0 end) as MinibarToday,
      sum(case when rev.ServiceId = 'BR' and rev.Date = @date then rev.Total else 0 end) as BrokenToday,
      sum(case when rev.ServiceId = 'FB' and rev.Date = @date then rev.Total else 0 end) as RestaurantToday,
      sum(case when rev.ServiceId not in ('RM', 'LA', 'FB', 'EB', 'EP', 'ER', 'KC', 'UP', 'BD', 'BF', 'EI', 'LO', 'BR', 'MB') and rev.Date = @date then rev.Total else 0 end) as OtherToday,
      sum(case when rev.Date != @date then rev.Total else 0 end) as PrevDay,
      sum(case when rev.Status = 2 then rev.Total else 0 end) as Checkedout,
      sum(case when rev.Status != 2 then rev.Total else 0 end) as NotCheckedout
	into #tempRevenueDetail
	from #tempRevenue rev
	group by BookingId2

	select dk.*, 
      isnull(dt.RoomToday, 0) RoomToday, 
      isnull(dt.LaundryToday, 0) LaundryToday, 
      isnull(dt.MinibarToday, 0) MinibarToday, 
      isnull(dt.BrokenToday, 0) BrokenToday, 
      isnull(dt.RestaurantToday, 0) RestaurantToday, 
      isnull(dt.ExtraRoomToday, 0) ExtraRoomToday, 
      isnull(dt.BreakfastSurchargeToday, 0) BreakfastSurchargeToday, 
      isnull(dt.OtherToday, 0) OtherToday, 
      (isnull(dt.RoomToday, 0) + isnull(dt.LaundryToday, 0) + isnull(dt.MinibarToday, 0) + isnull(dt.BrokenToday, 0) + isnull(dt.RestaurantToday, 0) + isnull(dt.OtherToday, 0) + isnull(dt.ExtraRoomToday, 0) + isnull(dt.BreakfastSurchargeToday, 0)) TotalToday, 
      isnull(dt.PrevDay, 0) PrevDay, 
      (isnull(dt.Checkedout, 0) + isnull(dt.NotCheckedout, 0)) TotalRevenue, 
      isnull(tt.Cash, 0) Cash, 
      isnull(tt.BankTransfer, 0) BankTransfer, 
      isnull(tt.CityLedger, 0) CityLedger, 
      isnull(tt.Commission, 0) Commission, 
      case when (isnull(dt.Checkedout, 0) + isnull(dt.NotCheckedout, 0) - isnull(tt.Cash, 0) - isnull(tt.BankTransfer, 0) - isnull(tt.CityLedger, 0) - isnull(tt.Commission, 0)) < 0 then 0 
           else (isnull(dt.Checkedout, 0) + isnull(dt.NotCheckedout, 0) - isnull(tt.Cash, 0) - isnull(tt.BankTransfer, 0) - isnull(tt.CityLedger, 0) - isnull(tt.Commission, 0)) end as InhouseRoom
	from #tempBooking dk
	left join #tempRevenueDetail dt on dk.Ma = dt.BookingId2
	left join #tempPayment tt on dk.Ma = tt.BookingId
	where RoomNo != 0 and (isnull(dt.RoomToday, 0) + isnull(dt.LaundryToday, 0) + isnull(dt.MinibarToday, 0) + isnull(dt.BrokenToday, 0) + isnull(dt.RestaurantToday, 0) + isnull(dt.OtherToday, 0) + isnull(dt.ExtraRoomToday, 0) + isnull(dt.BreakfastSurchargeToday, 0) + isnull(dt.PrevDay, 0)) != 0
	order by dk.ArrivalDate
end
```

---

### 3.3. Bóc tách chi tiết các bảng, view và function trong `sp_292`

#### 1. Phân tích hàm bảng `func_054(@fromDate, @toDate, @registrationID)`
* **Bản chất**: Là một `SQL_TABLE_VALUED_FUNCTION` trong SQL Server (kích thước ~ 35KB, 583 dòng code).
* **Mục đích**: Là hàm bóc tách doanh thu cốt lõi (Core Daily Revenue Engine) của hệ thống legacy, phân bổ doanh thu tiền phòng và tất cả các dịch vụ khách sạn theo từng ngày thực tế (`Date`) mà khách lưu trú/sử dụng.
* **Các bảng legacy nội bộ mà `func_054` truy vấn**:
  1. `SP3004` (Hóa đơn tiền phòng theo ngày): Chứa doanh thu buồng phòng từng đêm (`Date`, `TotalAmount0`, `RateCode`, `IsRoomNight`, `Quantity`).
  2. `SP3000` (Hóa đơn dịch vụ): Chứa các khoản phí dịch vụ ngoài phòng (`ServiceId`, `Date`, `TotalAmount0`, `DepartmentId`).
  3. `SP2100` (Phòng thuê): Liên kết `RentalRoomId`, `BookingId`.
  4. `SP2000` (Đặt phòng): Liên kết mã đăng ký `BookingId`.
  5. `SP2102` (Dịch vụ tự động theo ngày): Khuyến mãi, giảm giá (`Promotion`).
  6. `SP1500` & `SP1600`: Ngày hệ thống (`SystemDate`) và tham số cấu hình dịch vụ ăn sáng trẻ em (`Booking_BFChildSetServiceId`).
* **Bảng kết quả mà `func_054` trả về**:
  `[RentalRoomId], [BookingId], [BillIdService], [ServiceId], [RoomRateCode], [Date], [Quantity], [Total], [IsRoom], [DetailId], [DepartmentId], [IsRoomNight], [VoucherCode], [Promotion]`.
* **Cách `sp_292` sử dụng `func_054`**:
  * Gọi `func_054(@date, @date, '')` để tìm các booking phát sinh doanh thu trong ngày `@date`.
  * Gọi `func_054(@minDate, @date, '')` để lấy doanh thu trong ngày (`rev.Date = @date`) tách thành các cột (Tiền phòng, Giường phụ, Minibar, Giặt ủi, Bể vỡ, Nhà hàng, Khác) và gom các ngày trước đó vào `PrevDay` (`rev.Date != @date`).

#### 2. Bảng đối chiếu ánh xạ các bảng trong `sp_292` sang schema MySQL mới
- `func_054` $\rightarrow$ Thay bằng CTE truy vấn trực tiếp bảng `sales_invoices` (kết hợp `booking_room_rates`). Không cần tạo lại function 580 dòng cồng kềnh.
- `sp1322` (Cấu hình hệ thống) $\rightarrow$ `hotels` / `system_configs` (tiền tố booking).
- `SP2000` (Đặt phòng) $\rightarrow$ `bookings` (id, booking_name, arrival_date, departure_date, company_id, status).
- `SP2100` (Phòng thuê) $\rightarrow$ `booking_rooms` (id, booking_id, room_number, status).
- `SP1302` (Công ty) $\rightarrow$ `companies` (id, name, code).
- `SP3000` (Hóa đơn dịch vụ) $\rightarrow$ `sales_invoices` (id, booking_id, rental_room_id, invoice_date, amount, status, edit, payment_id).
- `SP3002` (Thanh toán) $\rightarrow$ `payments` (booking_id, booking_room_id, date, amount, payment_method_id, edit_flag).

---

### 3.4. Chuyển đổi sang MySQL 8.0 Stored Procedure (`rpt_revenue_army`)

```sql
DELIMITER $$

CREATE PROCEDURE rpt_revenue_army(
    IN p_date VARCHAR(20),
    IN p_company_id VARCHAR(50),
    IN p_booking_id VARCHAR(50)
)
READS SQL DATA
BEGIN
    DECLARE v_report_date DATE;
    DECLARE v_company_id INT DEFAULT 0;
    DECLARE v_booking_id INT DEFAULT 0;

    IF p_date LIKE '%/%' THEN
        SET v_report_date = STR_TO_DATE(LEFT(p_date, 10), '%d/%m/%Y');
    ELSE
        SET v_report_date = CAST(LEFT(p_date, 10) AS DATE);
    END IF;

    IF p_company_id IS NOT NULL AND p_company_id != '' AND p_company_id != '0' THEN
        SET v_company_id = CAST(p_company_id AS UNSIGNED);
    END IF;

    IF p_booking_id IS NOT NULL AND p_booking_id != '' AND p_booking_id != '0' THEN
        SET v_booking_id = CAST(p_booking_id AS UNSIGNED);
    END IF;

    WITH RoomAgg AS (
        SELECT 
            booking_id,
            GROUP_CONCAT(room_number ORDER BY room_number SEPARATOR ', ') AS room_numbers,
            COUNT(id) AS room_count
        FROM booking_rooms
        WHERE status IN (0, 1, 2, 4, 100)
        GROUP BY booking_id
    ),
    EligibleBookings AS (
        SELECT 
            b.id AS booking_id,
            CONCAT(b.booking_name, CASE WHEN COALESCE(ra.room_count, 0) > 0 THEN CONCAT(' - ', ra.room_numbers) ELSE '' END) AS guest_display,
            COALESCE(comp.name, comp.trading_name, 'KHÁCH LẺ') AS company_name,
            b.arrival_date,
            b.departure_date,
            COALESCE(ra.room_count, 0) AS room_count
        FROM bookings b
        LEFT JOIN RoomAgg ra ON ra.booking_id = b.id
        LEFT JOIN companies comp ON comp.id = b.company_id
        WHERE b.status != 3
          AND (
              (v_report_date BETWEEN b.arrival_date AND b.departure_date)
              OR EXISTS (
                  SELECT 1 FROM sales_invoices si 
                  WHERE si.booking_id = b.id 
                    AND CAST(si.invoice_date AS DATE) = v_report_date
                    AND si.status != 'cancelled'
              )
          )
          AND (v_company_id = 0 OR b.company_id = v_company_id)
          AND (v_booking_id = 0 OR b.id = v_booking_id)
    ),
    RevDetails AS (
        SELECT 
            si.booking_id,
            SUM(CASE WHEN si.outlet = 'RM' AND CAST(si.invoice_date AS DATE) = v_report_date THEN si.amount ELSE 0 END) AS room_today,
            SUM(CASE WHEN si.outlet IN ('EB', 'EP', 'ER', 'KC', 'UP', 'EI', 'LO', 'BD', 'BF') AND CAST(si.invoice_date AS DATE) = v_report_date THEN si.amount ELSE 0 END) AS extra_room_today,
            SUM(CASE WHEN si.outlet = 'MB' AND CAST(si.invoice_date AS DATE) = v_report_date THEN si.amount ELSE 0 END) AS minibar_today,
            SUM(CASE WHEN si.outlet = 'LA' AND CAST(si.invoice_date AS DATE) = v_report_date THEN si.amount ELSE 0 END) AS laundry_today,
            SUM(CASE WHEN si.outlet IN ('BR', 'BK') AND CAST(si.invoice_date AS DATE) = v_report_date THEN si.amount ELSE 0 END) AS broken_today,
            SUM(CASE WHEN si.outlet = 'FB' AND CAST(si.invoice_date AS DATE) = v_report_date THEN si.amount ELSE 0 END) AS restaurant_today,
            SUM(CASE WHEN si.outlet NOT IN ('RM', 'EB', 'EP', 'ER', 'KC', 'UP', 'EI', 'LO', 'BD', 'BF', 'MB', 'LA', 'BR', 'BK', 'FB') AND CAST(si.invoice_date AS DATE) = v_report_date THEN si.amount ELSE 0 END) AS other_today,
            SUM(CASE WHEN CAST(si.invoice_date AS DATE) < v_report_date THEN si.amount ELSE 0 END) AS prev_day_revenue
        FROM sales_invoices si
        WHERE si.booking_id IN (SELECT booking_id FROM EligibleBookings)
          AND si.status != 'cancelled'
        GROUP BY si.booking_id
    ),
    PayDetails AS (
        SELECT 
            p.booking_id,
            SUM(CASE WHEN pm.code = 'CA' OR p.payment_method_id = 1 THEN p.amount ELSE 0 END) AS cash_paid,
            SUM(CASE WHEN pm.code IN ('BT', 'CD') OR p.payment_method_id IN (2, 3) THEN p.amount ELSE 0 END) AS bank_transfer_paid,
            SUM(CASE WHEN pm.code = 'HH' THEN p.amount ELSE 0 END) AS commission_paid,
            SUM(CASE WHEN pm.code = 'AC' OR p.payment_method_id = 4 THEN p.amount ELSE 0 END) AS city_ledger_paid
        FROM payments p
        LEFT JOIN payment_methods pm ON pm.id = p.payment_method_id
        WHERE p.booking_id IN (SELECT booking_id FROM EligibleBookings)
          AND CAST(p.date AS DATE) <= v_report_date
          AND COALESCE(p.edit_flag, 0) = 0
        GROUP BY p.booking_id
    )
    SELECT 
        ROW_NUMBER() OVER (ORDER BY eb.arrival_date ASC, eb.booking_id ASC) AS `Index`,
        eb.booking_id AS BookingId,
        eb.guest_display AS GuestName,
        eb.company_name AS BusinessName,
        DATE_FORMAT(eb.arrival_date, '%d/%m/%Y') AS ArrivalDate,
        DATE_FORMAT(eb.departure_date, '%d/%m/%Y') AS DepartureDate,
        eb.room_count AS RoomCount,
        ROUND(COALESCE(rd.room_today, 0), 0) AS RoomToday,
        ROUND(COALESCE(rd.extra_room_today, 0), 0) AS ExtraRoomToday,
        ROUND(COALESCE(rd.minibar_today, 0), 0) AS MinibarToday,
        ROUND(COALESCE(rd.laundry_today, 0), 0) AS LaundryToday,
        ROUND(COALESCE(rd.broken_today, 0), 0) AS BrokenToday,
        ROUND(COALESCE(rd.restaurant_today, 0), 0) AS RestaurantToday,
        ROUND(COALESCE(rd.other_today, 0), 0) AS OtherToday,
        ROUND(COALESCE(rd.room_today, 0) + COALESCE(rd.extra_room_today, 0) + COALESCE(rd.minibar_today, 0) + COALESCE(rd.laundry_today, 0) + COALESCE(rd.broken_today, 0) + COALESCE(rd.restaurant_today, 0) + COALESCE(rd.other_today, 0), 0) AS TotalToday,
        ROUND(COALESCE(rd.prev_day_revenue, 0), 0) AS PrevDay,
        ROUND(COALESCE(rd.room_today, 0) + COALESCE(rd.extra_room_today, 0) + COALESCE(rd.minibar_today, 0) + COALESCE(rd.laundry_today, 0) + COALESCE(rd.broken_today, 0) + COALESCE(rd.restaurant_today, 0) + COALESCE(rd.other_today, 0) + COALESCE(rd.prev_day_revenue, 0), 0) AS TotalRevenue,
        ROUND(COALESCE(pd.cash_paid, 0), 0) AS Cash,
        ROUND(COALESCE(pd.bank_transfer_paid, 0), 0) AS BankTransfer,
        ROUND(COALESCE(pd.commission_paid, 0), 0) AS Commission,
        ROUND(COALESCE(pd.city_ledger_paid, 0), 0) AS CityLedger,
        GREATEST(0, ROUND((COALESCE(rd.room_today, 0) + COALESCE(rd.extra_room_today, 0) + COALESCE(rd.minibar_today, 0) + COALESCE(rd.laundry_today, 0) + COALESCE(rd.broken_today, 0) + COALESCE(rd.restaurant_today, 0) + COALESCE(rd.other_today, 0) + COALESCE(rd.prev_day_revenue, 0)) - (COALESCE(pd.cash_paid, 0) + COALESCE(pd.bank_transfer_paid, 0) + COALESCE(pd.commission_paid, 0) + COALESCE(pd.city_ledger_paid, 0)), 0)) AS InhouseRoom
    FROM EligibleBookings eb
    LEFT JOIN RevDetails rd ON rd.booking_id = eb.booking_id
    LEFT JOIN PayDetails pd ON pd.booking_id = eb.booking_id
    WHERE eb.room_count > 0 
      AND (
          COALESCE(rd.room_today, 0) + COALESCE(rd.extra_room_today, 0) + COALESCE(rd.minibar_today, 0) + 
          COALESCE(rd.laundry_today, 0) + COALESCE(rd.broken_today, 0) + COALESCE(rd.restaurant_today, 0) + 
          COALESCE(rd.other_today, 0) + COALESCE(rd.prev_day_revenue, 0)
      ) != 0
    ORDER BY eb.arrival_date ASC, eb.booking_id ASC;
END$$

DELIMITER ;
```

---

### 3.4. Thông số kỹ thuật cho Report Designer (Dòng 150)
- **Report Code:** `REVENUE_ARMY`
- **Template Code:** `REVENUE_ARMY_REFERENCE`
- **File tham chiếu:** `backend/database/report_templates/revenue_army_reference.php`
- **Data Source Code:** `RPT_REVENUE_ARMY`
- **Menu:** `['frontdesk', 'cashier', 'report']`, Group: `Báo cáo doanh thu`, sort_order: `150`.
- **UI Parameter Schema:**
  ```json
  [
    {"name": "p_date", "label": "Ngày", "control": "date", "default": "$today", "required": true},
    {"name": "p_company_id", "label": "Công ty", "control": "select", "default": "0", "options_source": "companies", "required": false},
    {"name": "p_booking_id", "label": "Đăng ký", "control": "select", "default": "0", "options_source": "bookings", "required": false}
  ]
  ```
- **Cấu hình Table Columns trong Designer (22 cột):**
  | Cột | Key | Tiêu đề | Width | Align | Format |
  |---|---|---|---|---|---|
  | 1 | `Index` | STT | 3% | center | number |
  | 2 | `BookingId` | Mã đăng ký | 5% | center | text |
  | 3 | `GuestName` | Tên khách | 12% | left | text |
  | 4 | `BusinessName` | Đơn vị | 8% | left | text |
  | 5 | `ArrivalDate` | Ngày đến | 5% | center | text |
  | 6 | `DepartureDate` | Ngày đi | 5% | center | text |
  | 7 | `RoomCount` | SP | 3% | center | number |
  | 8 | `RoomToday` | Tiền phòng | 5% | right | currency |
  | 9 | `ExtraRoomToday` | Phụ thu tiền phòng | 5% | right | currency |
  | 10 | `MinibarToday` | Minibar | 4% | right | currency |
  | 11 | `LaundryToday` | Giặt | 4% | right | currency |
  | 12 | `BrokenToday` | Bể vỡ | 4% | right | currency |
  | 13 | `RestaurantToday` | Nhà hàng | 5% | right | currency |
  | 14 | `OtherToday` | Dịch vụ | 4% | right | currency |
  | 15 | `TotalToday` | Tổng DT | 5% | right | currency |
  | 16 | `PrevDay` | DT ngày trước | 5% | right | currency |
  | 17 | `TotalRevenue` | Tổng cộng | 6% | right | currency |
  | 18 | `Cash` | TM | 5% | right | currency |
  | 19 | `BankTransfer` | CK | 5% | right | currency |
  | 20 | `Commission` | HH | 4% | right | currency |
  | 21 | `CityLedger` | Còn nợ | 5% | right | currency |
  | 22 | `InhouseRoom` | Phòng còn ở | 5% | right | currency |

- **CustomRows trong Designer:**
  - `revenue_army_grand_total`:
    - Cột 1-6 (colspan 6): Text `"Tổng số BK: {{aggregate.rows.count}}"` (Canh trái, in đậm).
    - Cột 7 (SP): Binding `aggregate.rows.sum.RoomCount` (Canh giữa, in đậm).
    - Cột 8-22: Tương ứng binding `aggregate.rows.sum.<Field>` (Canh phải, in đậm, format currency).
- **Khối chữ ký (Signature Block trong `content_json`):**
  - Khối ngày tháng: `<p style="text-align: right; font-style: italic; margin-bottom: 8px;">Quy Nhơn, Ngày {{report.day}} Tháng {{report.month}} Năm {{report.year}}</p>`
  - 5 cột tỉ lệ `20% - 20% - 20% - 20% - 20%`:
    1. `Chữ Ký Người Lập`
    2. `Trưởng Bộ Phận`
    3. `Kế Toán`
    4. `Tổng Quản Lý`
    5. `Giám Đốc`

---

## 4. DÒNG 151: BÁO CÁO DOANH THU ĐĂNG KÝ THEO NGÀY ĐI - SP_238 & SP_240

### 4.1. Thông tin từ danh mục báo cáo & Phân tích ảnh gốc
- **Dòng Excel:** 151.
- **Tên báo cáo:** Báo cáo doanh thu đăng ký theo ngày đi.
- **Ghi chú Excel:** "Lưu ý mã dịch vụ và cột hiển thị có thay đổi theo đơn vị. `sp_238` (xem chi tiết phòng), `sp_240` (nhóm theo đăng ký)".
- **Mã báo cáo mới:** `REVENUE_BY_DEPARTURE_DATE`.
- **Mã Stored Procedure legacy:** `sp_238` và `sp_240`.
- **Ảnh phân tích UI mẫu:** [row_151_image70.png](file:///C:/Users/Nguyen%20Tho%20Thang/.gemini/antigravity-ide/brain/1c24e0d7-6f33-41ef-b79b-a4409f2c5eba/scratch/row_151_image70.png).

#### Phân tích giao diện từ ảnh `row_151_image70.png`:
- **Thanh Filter (Left Sidebar):**
  - `Ngày`: Khoảng thời gian kèm giờ: `15/07/2026 00:00 ~ 15/07/2026 23:59` (`p_from_date`, `p_to_date`, `p_from_time`, `p_to_time`).
  - `Chọn công ty`: Dropdown công ty (Mặc định: Tất cả).
  - `Nhóm theo đăng ký`: Toggle Switch (Bật/Tắt).
    - **Khi Tắt (Mặc định, như trên ảnh):** Báo cáo gọi chế độ chi tiết từng phòng (`sp_238`), cột `Phòng` hiển thị số phòng riêng biệt (vd: Mã ĐK 262 tách thành dòng không có phòng và các dòng phòng 803, 804).
    - **Khi Bật:** Báo cáo gọi chế độ nhóm theo đăng ký (`sp_240`), cuộn tròn 1 dòng cho mỗi Mã ĐK, gom phòng thành chuỗi.
  - Nút bấm: `Hiển thị báo cáo`.
- **Khổ giấy:** A4 Ngang (`landscape`), margins siêu mỏng 4mm để chứa trọn vẹn 24 cột.
- **Cấu trúc bảng dữ liệu (Header 3 tầng, tổng cộng 24 cột):**
  - **Tầng 1 (Super Headers):**
    - `Mã ĐK` (rowspan 3)
    - `Phòng` (rowspan 3)
    - `Công Ty` (rowspan 3)
    - `Tên Khách` (rowspan 3)
    - `Ngày Đến` (rowspan 3)
    - `Ngày Đi` (rowspan 3)
    - `FO` (colspan 7: Tiền Phòng, PT Thêm Giường, PT Thêm Người, PT Tiền Phòng, Vé, Đưa Đón Khách, DT Khác)
    - `Doanh Thu HK` (colspan 4: Minibar, Giặt Ủi, Bể Vỡ, AS Từ Tiền Phòng)
    - `F&B Revenue` (Super header cho Nhà hàng - colspan 4)
    - `Doanh Thu Khác` (rowspan 3)
    - `Tổng Doanh Thu` (rowspan 3)
    - `Hình Thức Thanh Toán` (rowspan 3)
  - **Tầng 2 (Nhóm phụ):**
    - Bên dưới `F&B Revenue`: `Nhà Hàng` (colspan 4)
  - **Tầng 3 (Cột chi tiết):**
    - [1] `Mã ĐK` (`CodeBooking`)
    - [2] `Phòng` (`RoomNumber`)
    - [3] `Công Ty` (`Company`)
    - [4] `Tên Khách` (`GuestName`)
    - [5] `Ngày Đến` (`Arrival`)
    - [6] `Ngày Đi` (`Departure`)
    - *Nhóm FO:*
      - [7] Tiền Phòng (`RoomCharge`)
      - [8] PT Thêm Giường (`ExtraBed` - Dịch vụ `EB`)
      - [9] PT Thêm Người (`ExtraPerson` - Dịch vụ `EP`)
      - [10] PT Tiền Phòng (`ExtraRoomCharge` - Dịch vụ `EI`, `LO`, `ER`)
      - [11] Vé (`Tour` - Dịch vụ `TO`)
      - [12] Đưa Đón Khách (`Transportation` - Dịch vụ `PU`, `DO`)
      - [13] DT Khác (`Miscel` - Các phụ thu FO khác)
    - *Nhóm Doanh Thu HK:*
      - [14] Minibar (`Minibar` - Dịch vụ `MB`)
      - [15] Giặt Ủi (`Laundry` - Dịch vụ `LA`)
      - [16] Bể Vỡ (`Broken` - Dịch vụ `BR`, `BK`)
      - [17] AS Từ Tiền Phòng (`BreakfastSplitdown` - Dịch vụ `BF` bóc tách từ tiền phòng)
    - *Nhóm F&B / Nhà Hàng:*
      - [18] Phí AS (`BreakfastCharge` - Ăn sáng tính phí riêng ngoài tiền phòng)
      - [19] Thức Ăn (`Food_Res` - Dịch vụ `RF` tại Outlet `RE`)
      - [20] Đồ Uống (`Beverage_Res` - Dịch vụ `RB`, `PC` tại Outlet `RE`)
      - [21] Khác (`Other_Res` - Các dịch vụ F&B khác)
    - *Nhóm Tổng hợp & Thanh toán:*
      - [22] Doanh Thu Khác (`OtherRevenue`)
      - [23] Tổng Doanh Thu (`TotalRevenue` = Tổng các cột doanh thu)
      - [24] Hình Thức Thanh Toán (`PaymentMethod` - Ghép chuỗi phân tách dấu phẩy: `CA, BT, CD...`)
- **Dòng tổng cộng (Total Row):**
  - Cột 1-6 gộp: `"Total"`
  - Cột 7-23: Tổng tiền của từng cột. Cột 23 là Tổng doanh thu toàn bộ (ví dụ trên ảnh: `8,630,000`).

---

### 4.2. Stored Procedure gốc trong SSMS (`sp_238` & `sp_240`)
Điểm cốt lõi giữa `sp_238` và `sp_240`:
- Nhận 5 tham số: `@fromDate date, @toDate date, @companyId int, @fromTime varchar(5), @toTime varchar(5)`.
- Điều kiện lọc ngày đi: `cast(concat(DepartureBK, ' ', isnull(CheckoutTime, @fromTime)) as datetime) between cast(concat(@fromDate, ' ', @fromTime) as datetime) and cast(concat(@toDate, ' ', @toTime) as datetime)`.
- Phân loại doanh thu bằng mã dịch vụ (`ServiceId`) và bộ phận (`DepartmentId` / `Outlet`):
  - `RoomCharge`: `ServiceId = 'RM'`
  - `ExtraBed`: `ServiceId = 'EB'`
  - `ExtraPerson`: `ServiceId = 'EP'`
  - `ExtraRoomCharge`: `ServiceId IN ('EI', 'LO', 'ER')`
  - `Tour`: `ServiceId = 'TO'`
  - `Transportation`: `ServiceId IN ('PU', 'DO')`
  - `Minibar`: `ServiceId = 'MB'`
  - `Laundry`: `ServiceId = 'LA'`
  - `Broken`: `ServiceId IN ('BR', 'BK')`
  - `BreakfastSplitdown`: `ServiceId = 'BF' AND ServiceIdHD = 'RM'`
  - `BreakfastCharge`: `ServiceId IN ('BC', 'BD', 'BF', 'FB') AND ServiceIdHD <> 'RM' AND Outlet = 'RC'`
  - `Food_Res`: `ServiceId = 'RF' AND Outlet = 'RE'`
  - `Beverage_Res`: `ServiceId IN ('RB', 'PC') AND Outlet = 'RE'`
- Chế độ hiển thị:
  - `sp_238` (Chi tiết phòng): Group theo `CodeBooking, RoomCode, Status, RoomNumber, Company, GuestName, Arrival, Departure`.
  - `sp_240` (Nhóm đăng ký): Group theo `CodeBooking, ArrivalDateBK, CheckoutDateBK, Company, GuestName`.

#### Bóc tách các bảng legacy trong `sp_238` & `sp_240`:
1. `sp1322` (Hotel System Configuration): Tiền tố mã booking (`PrefixBookingId`).
2. `SP8060`, `SP8058`, `SP8059` (Report Template Config): Phân loại mẫu báo cáo (`GroupTemplate = 'Total revenue report'`).
3. `SP2000` (Bookings): Mã booking `Ma`, tên khách `BookingName`, ngày đến `ArrivalDate`, số ngày `NumOfDays`, công ty `TravelAgency`.
4. `SP2100` (Booking Rooms): Phòng thuê `Ma`, số phòng `Room`, ngày trả phòng `CheckoutDate`, giờ trả phòng `CheckoutTime`.
5. `SP2300` (Customers): Tên khách lưu trú `FirstName`.
6. `SP1302` (Companies): Tên đơn vị đối tác `Company`.
7. `SP3000` & `SP3001` (Invoices & Details): Hóa đơn và chi tiết dịch vụ (`Amount`, `ServiceId`, `DepartmentId`, `Outlet`). Phân bổ 17 cột doanh thu.
8. `SP3002` & `sp1326` (Payments): Giao dịch thanh toán và danh mục hình thức thanh toán miễn phí cần loại trừ (`HTMienPhi = 1`).

---

### 4.3. Chuyển đổi sang MySQL 8.0 Stored Procedure (`rpt_revenue_by_departure_date`)
Tích hợp cả 2 chế độ vào một Stored Procedure duy nhất qua tham số `p_group_by_booking` (0 = Xem chi tiết phòng theo `sp_238`, 1 = Nhóm theo đăng ký theo `sp_240`):

```sql
DELIMITER $$

CREATE PROCEDURE rpt_revenue_by_departure_date(
    IN p_from_date VARCHAR(20),
    IN p_to_date VARCHAR(20),
    IN p_from_time VARCHAR(10),
    IN p_to_time VARCHAR(10),
    IN p_company_id VARCHAR(50),
    IN p_group_by_booking TINYINT
)
READS SQL DATA
BEGIN
    DECLARE v_from_datetime DATETIME;
    DECLARE v_to_datetime DATETIME;
    DECLARE v_company_id INT DEFAULT 0;
    DECLARE v_group_booking TINYINT DEFAULT 0;

    IF p_from_time IS NULL OR p_from_time = '' THEN SET p_from_time = '00:00'; END IF;
    IF p_to_time IS NULL OR p_to_time = '' THEN SET p_to_time = '23:59'; END IF;

    IF p_from_date LIKE '%/%' THEN
        SET v_from_datetime = STR_TO_DATE(CONCAT(LEFT(p_from_date, 10), ' ', p_from_time, ':00'), '%d/%m/%Y %H:%i:%s');
    ELSE
        SET v_from_datetime = CAST(CONCAT(LEFT(p_from_date, 10), ' ', p_from_time, ':00') AS DATETIME);
    END IF;

    IF p_to_date LIKE '%/%' THEN
        SET v_to_datetime = STR_TO_DATE(CONCAT(LEFT(p_to_date, 10), ' ', p_to_time, ':59'), '%d/%m/%Y %H:%i:%s');
    ELSE
        SET v_to_datetime = CAST(CONCAT(LEFT(p_to_date, 10), ' ', p_to_time, ':59') AS DATETIME);
    END IF;

    IF p_company_id IS NOT NULL AND p_company_id != '' AND p_company_id != '0' THEN
        SET v_company_id = CAST(p_company_id AS UNSIGNED);
    END IF;

    IF p_group_by_booking IS NOT NULL THEN
        SET v_group_booking = p_group_by_booking;
    END IF;

    -- CTE trích xuất các hóa đơn / giao dịch dịch vụ
    WITH InvoiceItems AS (
        SELECT 
            si.booking_id,
            si.rental_room_id,
            br.room_number,
            b.booking_name,
            COALESCE(comp.name, 'KHÁCH LẺ') AS company_name,
            b.arrival_date,
            b.departure_date,
            br.departure_date AS room_departure_date,
            COALESCE(br.departure_time, '12:00') AS room_departure_time,
            si.outlet,
            si.department,
            si.amount,
            si.payment_id,
            CASE WHEN br.room_number IS NOT NULL AND br.room_number != '' THEN br.departure_date ELSE b.departure_date END AS effective_dep_date,
            CASE WHEN br.room_number IS NOT NULL AND br.room_number != '' THEN COALESCE(br.departure_time, '12:00') ELSE '12:00' END AS effective_dep_time
        FROM sales_invoices si
        LEFT JOIN bookings b ON b.id = si.booking_id
        LEFT JOIN booking_rooms br ON br.id = si.rental_room_id
        LEFT JOIN companies comp ON comp.id = b.company_id
        WHERE si.status != 'cancelled'
          AND (v_company_id = 0 OR b.company_id = v_company_id)
    ),
    FilteredItems AS (
        SELECT *
        FROM InvoiceItems
        WHERE CAST(CONCAT(effective_dep_date, ' ', effective_dep_time) AS DATETIME) BETWEEN v_from_datetime AND v_to_datetime
    )
    SELECT 
        fi.booking_id AS CodeBooking,
        CASE WHEN v_group_booking = 1 THEN '' ELSE COALESCE(fi.room_number, '') END AS RoomNumber,
        fi.company_name AS Company,
        fi.booking_name AS GuestName,
        DATE_FORMAT(fi.arrival_date, '%d/%m/%Y') AS Arrival,
        DATE_FORMAT(fi.departure_date, '%d/%m/%Y') AS Departure,
        ROUND(SUM(CASE WHEN fi.outlet = 'RM' THEN fi.amount ELSE 0 END), 0) AS RoomCharge,
        ROUND(SUM(CASE WHEN fi.outlet = 'EB' THEN fi.amount ELSE 0 END), 0) AS ExtraBed,
        ROUND(SUM(CASE WHEN fi.outlet = 'EP' THEN fi.amount ELSE 0 END), 0) AS ExtraPerson,
        ROUND(SUM(CASE WHEN fi.outlet IN ('EI', 'LO', 'ER') THEN fi.amount ELSE 0 END), 0) AS ExtraRoomCharge,
        ROUND(SUM(CASE WHEN fi.outlet = 'TO' THEN fi.amount ELSE 0 END), 0) AS Tour,
        ROUND(SUM(CASE WHEN fi.outlet IN ('PU', 'DO') THEN fi.amount ELSE 0 END), 0) AS Transportation,
        ROUND(SUM(CASE WHEN fi.outlet NOT IN ('RM', 'EB', 'EP', 'EI', 'LO', 'ER', 'TO', 'PU', 'DO', 'MB', 'LA', 'BR', 'BK', 'RF', 'RB', 'PC', 'BC', 'BD', 'BF', 'FB') AND fi.department = 'FO' THEN fi.amount ELSE 0 END), 0) AS Miscel,
        ROUND(SUM(CASE WHEN fi.outlet = 'MB' THEN fi.amount ELSE 0 END), 0) AS Minibar,
        ROUND(SUM(CASE WHEN fi.outlet = 'LA' THEN fi.amount ELSE 0 END), 0) AS Laundry,
        ROUND(SUM(CASE WHEN fi.outlet IN ('BR', 'BK') THEN fi.amount ELSE 0 END), 0) AS Broken,
        ROUND(SUM(CASE WHEN fi.outlet = 'BF' THEN fi.amount ELSE 0 END), 0) AS BreakfastSplitdown,
        ROUND(SUM(CASE WHEN fi.outlet IN ('BC', 'BD') THEN fi.amount ELSE 0 END), 0) AS BreakfastCharge,
        ROUND(SUM(CASE WHEN fi.outlet = 'RF' THEN fi.amount ELSE 0 END), 0) AS Food_Res,
        ROUND(SUM(CASE WHEN fi.outlet IN ('RB', 'PC') THEN fi.amount ELSE 0 END), 0) AS Beverage_Res,
        ROUND(SUM(CASE WHEN fi.department = 'FB' AND fi.outlet NOT IN ('RF', 'RB', 'PC', 'BC', 'BD', 'BF') THEN fi.amount ELSE 0 END), 0) AS Other_Res,
        ROUND(SUM(CASE WHEN fi.department NOT IN ('FO', 'HK', 'FB') THEN fi.amount ELSE 0 END), 0) AS OtherRevenue,
        ROUND(SUM(fi.amount), 0) AS TotalRevenue,
        COALESCE(
            (SELECT GROUP_CONCAT(DISTINCT pm.code SEPARATOR ', ')
             FROM payments p 
             JOIN payment_methods pm ON pm.id = p.payment_method_id 
             WHERE p.booking_id = fi.booking_id AND COALESCE(p.edit_flag, 0) = 0), 
            ''
        ) AS PaymentMethod
    FROM FilteredItems fi
    GROUP BY 
        fi.booking_id,
        CASE WHEN v_group_booking = 1 THEN 0 ELSE fi.room_number END,
        fi.company_name,
        fi.booking_name,
        fi.arrival_date,
        fi.departure_date
    ORDER BY fi.booking_id ASC, RoomNumber ASC;
END$$

DELIMITER ;
```

---

### 4.4. Thông số kỹ thuật cho Report Designer (Dòng 151)
- **Report Code:** `REVENUE_BY_DEPARTURE_DATE`
- **Template Code:** `REVENUE_BY_DEPARTURE_DATE_REFERENCE`
- **File tham chiếu:** `backend/database/report_templates/revenue_by_departure_date_reference.php`
- **Data Source Code:** `RPT_REVENUE_BY_DEPARTURE_DATE`
- **UI Parameter Schema:**
  ```json
  [
    {"name": "p_from_date", "label": "Từ ngày", "control": "date", "default": "$today", "required": true},
    {"name": "p_from_time", "label": "Từ giờ", "control": "time", "default": "00:00", "required": false},
    {"name": "p_to_date", "label": "Đến ngày", "control": "date", "default": "$today", "required": true},
    {"name": "p_to_time", "label": "Đến giờ", "control": "time", "default": "23:59", "required": false},
    {"name": "p_company_id", "label": "Chọn công ty", "control": "select", "default": "0", "options_source": "companies", "required": false},
    {"name": "p_group_by_booking", "label": "Nhóm theo đăng ký", "control": "switch", "default": 0, "required": false}
  ]
  ```
- **Cấu hình Table Columns trong Designer (24 cột):**
  | Cột | Key | Tiêu đề | Width | Align | Format |
  |---|---|---|---|---|---|
  | 1 | `CodeBooking` | Mã ĐK | 4% | center | text |
  | 2 | `RoomNumber` | Phòng | 4% | center | text |
  | 3 | `Company` | Công Ty | 7% | left | text |
  | 4 | `GuestName` | Tên Khách | 10% | left | text |
  | 5 | `Arrival` | Ngày Đến | 5% | center | text |
  | 6 | `Departure` | Ngày Đi | 5% | center | text |
  | 7 | `RoomCharge` | Tiền Phòng | 5% | right | currency |
  | 8 | `ExtraBed` | PT Thêm Giường | 4% | right | currency |
  | 9 | `ExtraPerson` | PT Thêm Người | 4% | right | currency |
  | 10 | `ExtraRoomCharge` | PT Tiền Phòng | 4% | right | currency |
  | 11 | `Tour` | Vé | 3% | right | currency |
  | 12 | `Transportation` | Đưa Đón Khách | 4% | right | currency |
  | 13 | `Miscel` | DT Khác | 3% | right | currency |
  | 14 | `Minibar` | Minibar | 3% | right | currency |
  | 15 | `Laundry` | Giặt Ủi | 3% | right | currency |
  | 16 | `Broken` | Bể Vỡ | 3% | right | currency |
  | 17 | `BreakfastSplitdown` | AS Từ Tiền Phòng | 4% | right | currency |
  | 18 | `BreakfastCharge` | Phí AS | 4% | right | currency |
  | 19 | `Food_Res` | Thức Ăn | 4% | right | currency |
  | 20 | `Beverage_Res` | Đồ Uống | 3% | right | currency |
  | 21 | `Other_Res` | Khác | 3% | right | currency |
  | 22 | `OtherRevenue` | Doanh Thu Khác | 3% | right | currency |
  | 23 | `TotalRevenue` | Tổng Doanh Thu | 5% | right | currency |
  | 24 | `PaymentMethod` | Hình Thức Thanh Toán | 4% | center | text |

---

## 5. DÒNG 152: BÁO CÁO DOANH THU LỄ TÂN_ARMY - SP_293

### 5.1. Thông tin từ danh mục báo cáo & Phân tích ảnh gốc
- **Dòng Excel:** 152.
- **Tên báo cáo:** Báo cáo doanh thu lễ tân_army.
- **Mã báo cáo mới:** `RECEPTION_REVENUE_ARMY`.
- **Mã Stored Procedure legacy:** `sp_293`.
- **Ảnh phân tích UI mẫu:** [row_152_image87.png](file:///C:/Users/Nguyen%20Tho%20Thang/.gemini/antigravity-ide/brain/1c24e0d7-6f33-41ef-b79b-a4409f2c5eba/scratch/row_152_image87.png).

#### Phân tích giao diện từ ảnh `row_152_image87.png`:
- **Thanh Filter (Left Sidebar):**
  - `Ngày`: Date Range picker với preset "Hôm nay" (`15/07/2026 ~ 15/07/2026`).
  - `Dịch vụ`: Multi-select dropdown (`Chọn: 0` - cho phép chọn một hoặc nhiều dịch vụ cần xem, để trống = xem tất cả).
  - `Người dùng`: Dropdown chọn người dùng / thu ngân (Mặc định: Tất cả).
  - Nút bấm: `Hiển thị báo cáo`.
- **Khổ giấy:** A4 Landscape hoặc Portrait, font Segoe UI.
- **Cấu trúc bảng phân cấp (2-Level Grouping Table, 11 cột dữ liệu):**
  - **Cấp Group 1:** `Nhóm doanh thu` (Dòng tiêu đề trải dài: `Nhóm doanh thu | Doanh Thu Phòng`, `Nhóm doanh thu | Doanh Thu Dịch Vụ`).
  - **Cấp Group 2:** `Dịch Vụ: [Mã DV] - [Tên DV]` (vd: `Dịch Vụ: RM - Dịch vụ phòng nghỉ`, `Dịch Vụ: MB - Minibar/Phí Minibar`).
  - **11 cột chi tiết trong bảng:**
    1. `Mã ĐK` (Mã đặt phòng / Booking ID, vd: `279`, `266`)
    2. `Phòng` (Số phòng, vd: `819`)
    3. `Ngày Đến` (dd/mm/yyyy)
    4. `Ngày Đi` (dd/mm/yyyy)
    5. `Tên Khách` (vd: `TRẦN THỊ ÁNH NGA`)
    6. `Mô Tả` (Diễn giải chi tiết dịch vụ, vd: "Dịch vụ phòng nghỉ 806", "Minibar/Phí Minibar")
    7. `Doanh Thu` (Số tiền, canh phải, định dạng số có dấu phẩy)
    8. `HTTT` (Hình thức thanh toán: `CA`, `CK`, `AC`...)
    9. `Công Ty` (Tên công ty/đơn vị, vd: `KHÁCH LẺ`, `NKQN- C Hường`)
    10. `Giờ` (Giờ phát sinh hóa đơn, hh:mm, vd: `09:14`, `09:38`)
    11. `Ghi chú` (Mô tả chi tiết từ thanh toán, vd: "Cash (Tiền mặt)")
  - **Dòng tổng phụ (Subtotals):**
    - Cuối mỗi dịch vụ: Dòng `Tổng: [Số tiền]`.
    - Cuối mỗi nhóm doanh thu: Dòng `Tổng: [Số tiền]`.
- **Bảng tổng hợp phụ cuối báo cáo (Summary Table):**
  - Đặt ở cuối bảng, gồm 2 cột:
    - `Nhóm doanh thu` | `Tổng`
    - `Doanh Thu Phòng` | `800,000`
    - `Doanh Thu Dịch Vụ` | `170,000`
    - `Tổng` | `970,000`

---

### 5.2. Stored Procedure gốc trong SSMS (`sp_293`)
Mã nguồn trích xuất từ database `ProVistaArmyHotel`:
```sql
CREATE procedure sp_293 (@fromDate date, @toDate date, @user varchar(20) = '', @service varchar(10) = '')
as
begin
	declare @prefix varchar(20) = (select top 1 isnull(PrefixBookingId,'') from sp1322)

	;with ServiceSetup as (
		select * from SP1610
		where Report = 'FORevenueReport'
	),
	Lang as (
		select * from SP1602
		where id in (select CONCAT('reportviewer.totalbillreport.', LOWER(DisplayName)) from ServiceSetup)
	)
	select CONCAT(@prefix , isnull(hddv.RegisterId2, hddv.BookingId)) as BookingId, 
         hddv.Date, 
         pt.Room, 
         isnull(hddv.ArrivalDate, dk.ArrivalDate) ArrivalDate,
         isnull(DATEADD(day, hddv.NumOfDays, hddv.ArrivalDate), DATEADD(day, hddv.NumOfDays, dk.ArrivalDate)) DepartureDate,
         hddv.Guest as GuestName, 
         hddv.DescriptionServive, 
         hddv.OriginalRate, 
         hddv.ServiceChargeAmount, 
         hddv.SpecialTaxAmount, 
         hddv.TaxAmount, 
         hddv.Amount,
         A.PaymentMethod, 
         ct.Company, 
         hddv.OpenTime, 
         B.Description, 
         ss.DisplayName, 
         hddv.ServiceId, 
         dv.Service as FirstNameService,
         isnull(la.VI, CONCAT('reportviewer.totalbillreport.', LOWER(DisplayName))) as NameVI
	from vw_018 hddv
	left join SP2000 dk on dk.Ma = hddv.RegisterID2
	left join SP2100 pt on hddv.RentalRoomId2 = pt.Ma
	left join SP1306 dv on hddv.ServiceId = dv.Ma
	left join SP2000 dk1 on dk1.Ma = isnull(dk.Ma, pt.BookingId)
	left join ServiceSetup ss on hddv.ServiceId in (select value from string_split(ss.Service, ','))
	left join Lang la on CONCAT('reportviewer.totalbillreport.', LOWER(ss.DisplayName)) = la.Id
	left join SP1302 ct on ct.Ma = dk1.TravelAgency
	left join (select STRING_AGG(A.PaymentMethod,',') as PaymentMethod, PaymentID 
             from (select distinct PaymentMethod, PaymentID from SP3002 where PaymentID is not null group by PaymentID, PaymentMethod) A 
             group by PaymentID) A on A.PaymentID = hddv.PaymentID
	left join (select STRING_AGG(B.Description,',') as Description, PaymentID 
             from (select distinct Description, PaymentID from SP3002 where PaymentID is not null group by PaymentID, Description) B 
             group by PaymentID) B on B.PaymentID = hddv.PaymentID
	where edit = 0 and hddv.date between @fromDate and @toDate
	  and ss.DisplayName is not null and hddv.DepartmentId in ('FO','HK')
	  and (@service = '' or hddv.ServiceId = @service)
	  and (@user = '' or hddv.Username = @user)
end
```

---

### 5.3. Bóc tách chi tiết các bảng, view và function trong `sp_293`

#### 1. Phân tích View trung tâm `vw_018`
* **Bản chất**: View tổng hợp dữ liệu hóa đơn dịch vụ và buồng phòng kết hợp từ 8 bảng legacy:
  - `SP3000` (hóa đơn dịch vụ), `SP2100` (phòng thuê), `SP2200` (khách thuê phòng), `SP2300` (khách hàng), `SP1306` (dịch vụ), `SP3003` (hóa đơn bán buồng), `SP6000` (buồng phòng HK), `SP5000` (ẩm thực FB).
* **Công thức bóc tách thuế phí của `vw_018`**:
  - `OriginalRate` = `Amount / ((1 + ServiceCharge/100) * (1 + SpecialTax/100) * (1 + Tax/100))`.
  - `ServiceChargeAmount` = `OriginalRate * (ServiceCharge/100)`.
  - `TaxAmount` = `(OriginalRate + ServiceChargeAmount + SpecialTaxAmount) * (Tax/100)`.
* **Dữ liệu cung cấp cho `sp_293`**: `BookingId`, `Date`, `Room`, `ArrivalDate`, `DepartureDate`, `GuestName`, `DescriptionService`, `Amount`, `ServiceId`, `FirstNameService`, `OpenTime`.

#### 2. Phân tích các bảng cấu hình phân nhóm dịch vụ
* **`SP1610` (Service Setup)**: Lọc cấu hình nhóm dịch vụ theo `where Report = 'FORevenueReport'` để phân loại các dịch vụ (`RM`, `MB`, `LA`, `BR`, etc.) vào các nhóm hiển thị `DisplayName` (`Doanh Thu Phòng`, `Doanh Thu Dịch Vụ`).
* **`SP1602` (Language Table)**: Ánh xạ khóa `reportviewer.totalbillreport.[displayname]` sang tên tiếng Việt hiển thị trên giao diện.
* **`SP3002` (Payments)**: Lấy chuỗi phương thức thanh toán `PaymentMethod` (CA, BT, CK, AC) và ghi chú thanh toán `Description`.

#### 3. Bảng đối chiếu ánh xạ sang schema MySQL mới
- `vw_018` $\rightarrow$ `sales_invoices` (kết hợp `booking_rooms`, `bookings`, `hotel_services`). Không cần tạo view cồng kềnh.
- `SP1610` + `SP1602` $\rightarrow$ Phân loại tự động theo `CASE WHEN si.outlet = 'RM' THEN 'Doanh Thu Phòng' ELSE 'Doanh Thu Dịch Vụ' END AS RevenueGroupName`.
- `SP1306` (Dịch vụ) $\rightarrow$ `hotel_services` (`code`, `name`).
- `SP2000` (Đặt phòng) $\rightarrow$ `bookings` (`id`, `booking_name`).
- `SP2100` (Phòng thuê) $\rightarrow$ `booking_rooms` (`id`, `room_number`).
- `SP1302` (Công ty) $\rightarrow$ `companies` (`id`, `name`).
- `SP3002` (Thanh toán) $\rightarrow$ `payments` + `payment_methods` (`payment_method_id` $\rightarrow$ `pm.code`, `description`).

---

### 5.4. Chuyển đổi sang MySQL 8.0 Stored Procedure (`rpt_reception_revenue_army`)

```sql
DELIMITER $$

CREATE PROCEDURE rpt_reception_revenue_army(
    IN p_from_date VARCHAR(20),
    IN p_to_date VARCHAR(20),
    IN p_user VARCHAR(50),
    IN p_service VARCHAR(100)
)
READS SQL DATA
BEGIN
    DECLARE v_from DATE;
    DECLARE v_to DATE;

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

    SELECT 
        si.id AS InvoiceId,
        si.booking_id AS BookingId,
        COALESCE(br.room_number, si.room, '') AS Room,
        DATE_FORMAT(COALESCE(br.arrival_date, b.arrival_date), '%d/%m/%Y') AS ArrivalDate,
        DATE_FORMAT(COALESCE(br.departure_date, b.departure_date), '%d/%m/%Y') AS DepartureDate,
        COALESCE(si.guest_name, b.booking_name, '') AS GuestName,
        COALESCE(si.note, hs.name, 'Dịch vụ') AS DescriptionService,
        ROUND(si.amount, 0) AS Amount,
        COALESCE(pm.code, 'CA') AS HTTT,
        COALESCE(comp.name, 'KHÁCH LẺ') AS Company,
        COALESCE(si.open_time, DATE_FORMAT(si.created_at, '%H:%i')) AS OpenTime,
        COALESCE(p.description, '') AS Note,
        CASE 
            WHEN si.outlet = 'RM' OR hs.code = 'RM' THEN 'Doanh Thu Phòng'
            ELSE 'Doanh Thu Dịch Vụ'
        END AS RevenueGroupName,
        COALESCE(hs.code, si.outlet, 'DV') AS ServiceId,
        COALESCE(hs.name, 'Dịch vụ khác') AS ServiceName
    FROM sales_invoices si
    LEFT JOIN bookings b ON b.id = si.booking_id
    LEFT JOIN booking_rooms br ON br.id = si.rental_room_id
    LEFT JOIN hotel_services hs ON hs.code = si.outlet
    LEFT JOIN companies comp ON comp.id = b.company_id
    LEFT JOIN payments p ON p.id = si.payment_id
    LEFT JOIN payment_methods pm ON pm.id = p.payment_method_id
    WHERE si.status != 'cancelled'
      AND CAST(si.invoice_date AS DATE) BETWEEN v_from AND v_to
      AND (COALESCE(p_user, '') = '' OR si.username = p_user)
      AND (
          COALESCE(p_service, '') = '' 
          OR FIND_IN_SET(COALESCE(hs.code, si.outlet), p_service) > 0
      )
    ORDER BY 
        CASE WHEN RevenueGroupName = 'Doanh Thu Phòng' THEN 1 ELSE 2 END ASC,
        ServiceId ASC,
        si.invoice_date ASC,
        si.id ASC;
END$$

DELIMITER ;
```

---

### 5.4. Thông số kỹ thuật cho Report Designer (Dòng 152)
- **Report Code:** `RECEPTION_REVENUE_ARMY`
- **Template Code:** `RECEPTION_REVENUE_ARMY_REFERENCE`
- **File tham chiếu:** `backend/database/report_templates/reception_revenue_army_reference.php`
- **Data Source Code:** `RPT_RECEPTION_REVENUE_ARMY`
- **UI Parameter Schema:**
  ```json
  [
    {"name": "p_from_date", "label": "Từ ngày", "control": "date", "default": "$today", "required": true},
    {"name": "p_to_date", "label": "Đến ngày", "control": "date", "default": "$today", "required": true},
    {"name": "p_service", "label": "Dịch vụ", "control": "multi-select", "default": "", "options_source": "hotel-services", "required": false},
    {"name": "p_user", "label": "Người dùng", "control": "select", "default": "", "options_source": "users", "required": false}
  ]
  ```
- **Grouping trong Table Designer (2 Cấp Group):**
  1. `level 0`: Field `RevenueGroupName` - Label: `"Nhóm doanh thu: {{row.RevenueGroupName}}"`.
  2. `level 1`: Field `ServiceId` - Label: `"Dịch Vụ: {{row.ServiceId}} - {{row.ServiceName}}"`.
- **Cấu hình Table Columns trong Designer (11 cột):**
  | Cột | Key | Tiêu đề | Width | Align | Format |
  |---|---|---|---|---|---|
  | 1 | `BookingId` | Mã ĐK | 6% | center | text |
  | 2 | `Room` | Phòng | 5% | center | text |
  | 3 | `ArrivalDate` | Ngày Đến | 7% | center | text |
  | 4 | `DepartureDate` | Ngày Đi | 7% | center | text |
  | 5 | `GuestName` | Tên Khách | 14% | left | text |
  | 6 | `DescriptionService` | Mô Tả | 18% | left | text |
  | 7 | `Amount` | Doanh Thu | 8% | right | currency |
  | 8 | `HTTT` | HTTT | 5% | center | text |
  | 9 | `Company` | Công Ty | 12% | left | text |
  | 10 | `OpenTime` | Giờ | 6% | center | text |
  | 11 | `Note` | Ghi chú | 12% | left | text |

- **Bảng tổng hợp phụ (Summary Box Table):**
  Được thiết kế dưới bảng chi tiết dưới dạng một Table tĩnh 2 cột:
  ```html
  <div style="width: 320px; margin: 16px auto 0 auto;">
    <table class="revenue-summary-table" style="width: 100%; border-collapse: collapse; border: 1px solid #111;">
      <thead>
        <tr style="background-color: #f1f5f9; font-weight: bold;">
          <th style="border: 1px solid #111; padding: 6px; text-align: left;">Nhóm doanh thu</th>
          <th style="border: 1px solid #111; padding: 6px; text-align: right;">Tổng</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td style="border: 1px solid #111; padding: 5px;">Doanh Thu Phòng</td>
          <td style="border: 1px solid #111; padding: 5px; text-align: right;">{{aggregate.room_rev|number}}</td>
        </tr>
        <tr>
          <td style="border: 1px solid #111; padding: 5px;">Doanh Thu Dịch Vụ</td>
          <td style="border: 1px solid #111; padding: 5px; text-align: right;">{{aggregate.service_rev|number}}</td>
        </tr>
        <tr style="font-weight: bold; background-color: #f8fafc;">
          <td style="border: 1px solid #111; padding: 6px;">Tổng</td>
          <td style="border: 1px solid #111; padding: 6px; text-align: right;">{{aggregate.rows.sum.Amount|number}}</td>
        </tr>
      </tbody>
    </table>
  </div>
  ```

---

## 6. KẾ HOẠCH KIỂM THỬ & LỆNH XÁC MINH (VERIFICATION GUIDE)

Sau khi tạo các file code, Agent tiến hành các bước xác minh theo thứ tự:

### 6.1. Chạy migrate trên 5 databases
```powershell
cd c:\Users\Nguyen Tho Thang\OneDrive\Desktop\PMS\PMS\backend
php artisan migrate:all --force
```

### 6.2. Kiểm tra syntax PHP các file vừa tạo
```powershell
php -l database/report_templates/revenue_army_reference.php
php -l database/report_templates/revenue_by_departure_date_reference.php
php -l database/report_templates/reception_revenue_army_reference.php
php -l database/migrations/<migration_file>.php
```

### 6.3. Chạy Feature Tests
```powershell
php artisan test --filter=RevenueArmyReportTest
php artisan test --filter=RevenueByDepartureDateReportTest
php artisan test --filter=ReceptionRevenueArmyReportTest
```

### 6.4. Kiểm tra route API của Report
```powershell
php artisan route:list --path=api/reports
```

### 6.5. Kiểm tra build Frontend
```powershell
cd c:\Users\Nguyen Tho Thang\OneDrive\Desktop\PMS\PMS\frontend
npm run build
```

---
*Tài liệu được tạo tự động và xác thực từ SSMS SQL Server và codebase PMS mới.*
