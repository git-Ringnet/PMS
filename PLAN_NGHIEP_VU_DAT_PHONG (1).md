# PLAN CHI TIẾT — NGHIỆP VỤ ĐẶT PHÒNG (BOOKING / RESERVATION)
> Nguồn phân tích: file `DANH SÁCH BẢNG TRONG HỆ THỐNG.xlsx` — tab **TABLE PMS** (danh sách bảng SQL Server, hệ PMS cũ) + tab **MÔ TẢ NGHIỆP VỤ** (mô tả màn hình "Đặt phòng").
> Mục tiêu: chuyển đổi từ SQL Server (PMS cũ, tên bảng dạng `SPxxxx`) sang dự án mới trên MySQL với tên bảng chuẩn hoá (snake_case, tiếng Anh), giữ nguyên đầy đủ nghiệp vụ, business rule, trạng thái, ràng buộc.

---

## 0. CÁCH DÙNG FILE NÀY

Đây là **1 plan nghiệp vụ + mapping dữ liệu**, không phải code. Bạn add file này vào CLI Antigravity (hoặc bất kỳ coding agent nào) làm ngữ cảnh (context) khi giao việc, ví dụ:

```
Đọc kỹ file PLAN_NGHIEP_VU_DAT_PHONG.md.
Hãy tạo migration MySQL cho nhóm bảng "CORE — GIAO DỊCH ĐẶT PHÒNG" ở mục 3.2,
đúng tên bảng/cột đã đề xuất, đúng kiểu dữ liệu, đúng khoá ngoại, đúng enum trạng thái ở mục 2.
Sau đó tạo API CRUD cho chức năng "Tạo mới đặt phòng" theo business rule ở mục 4.1.
```

Nên triển khai theo **thứ tự phase ở mục 6**, đừng đưa hết 1 lần — vì bảng đăng ký (`bookings`) phụ thuộc vào ~15 bảng danh mục phải có trước.

---

## 1. TỔNG QUAN NGHIỆP VỤ "ĐẶT PHÒNG"

Module Đặt phòng (Reservation/Booking) là module lõi của PMS, quản lý vòng đời một booking từ lúc tạo đến khi check-in/check-out/hủy. Một **booking (đăng ký)** có thể chứa **nhiều phòng thuê (booking_room)**, mỗi phòng thuê có thể chứa **nhiều khách (guest)** và **trẻ em (child)**.

Vòng đời trạng thái chung (dùng lại ở nhiều bảng): 
`0 = Đăng ký (reservation) → 1 = Checked-in (inhouse) → 2 = Checked-out → 3 = Cancelled → 4 = No-show → 100 = Chuyển phòng`

Ngoài ra còn 1 tầng trạng thái "chi tiết" riêng cho booking (không phải phòng): **Tình trạng đăng ký (Booking Status)** — ví dụ Guaranteed, None Guaranteed, Waiting, Allotment... dùng để quyết định booking có trừ vào số phòng trống (Room AV) hay không (`IsAvailability`), có tính ngày xác nhận (`CutOffDay`) hay không.

### 1.1 Danh sách chức năng nghiệp vụ (theo tab MÔ TẢ NGHIỆP VỤ)

| # | Chức năng | Mục đích ngắn gọn |
|---|---|---|
| 1 | Tạo mới đặt phòng | Tạo booking + lấy phòng vào booking |
| 2 | Cập nhật | Sửa nhanh ngày đến/đi, giờ, giá, số khách, extra bed cho nhiều phòng cùng lúc |
| 3 | Tự động gán số phòng | Hệ thống tự chọn số phòng trống phù hợp loại phòng |
| 4 | Dịch vụ bổ sung | Gắn dịch vụ định kỳ (ngoài tiền phòng) để tự động lên bill khi sang ngày |
| 5 | Giao phòng (Check-in) | Chuyển trạng thái phòng sang inhouse |
| 6 | Nâng hạng phòng | Đổi loại phòng đã đặt sang loại khác (nâng/hạ hạng) |
| 7 | Thông tin khách | Nhập/khai báo lưu trú (thủ công, import excel, scan CCCD/passport) |
| 8 | Gỡ số phòng | Bỏ số phòng đã gán, đưa về NULL |
| 9 | Hủy phòng | Hủy 1 hoặc nhiều phòng trong booking |
| 10 | Xóa dịch vụ bổ sung | Xóa nhanh dịch vụ tự động đã gắn |
| 11 | Khóa/Mở chuyển phòng (Do not move) | Khóa không cho đổi số phòng đã gán |
| 12 | Xuất Excel | Xuất danh sách booking |
| 13 | In phiếu đăng ký khách | In mẫu xác nhận lưu trú |
| 14 | In hóa đơn tạm | In tạm tính các dịch vụ của phòng |
| 15 | Chi tiết ăn sáng trẻ em | Khai báo số trẻ em ăn sáng + phụ thu theo từng ngày |
| 16 | Thêm giường & phụ thu | Khai báo extra bed + giá theo từng ngày |
| 17 | Yêu cầu đặc biệt (Special Request) | Gắn cờ honeymoon/birthday/baby cot... |
| 18 | Đặt cọc | Ghi nhận, tách, chuyển, xóa tiền cọc |
| 19 | Nhân bản (Copy booking) | Tạo booking mới từ booking gốc |
| 20 | Sửa đăng ký | Sửa thông tin phòng ở trạng thái đăng ký |
| 21 | Xóa (booking) | Hủy toàn bộ booking (chỉ khi chưa inhouse & chưa có cọc) |
| 22 | Khôi phục đăng ký | Khôi phục booking đã hủy |

Chi tiết business rule của từng chức năng → xem **mục 4**.

---

## 2. CÁC ENUM / TRẠNG THÁI DÙNG XUYÊN SUỐT MODULE

### 2.1 Trạng thái chung (bảng gốc `SP1309` — TinhTrangChung)
| Giá trị | Ý nghĩa |
|---|---|
| 0 | Đăng ký (Reservation) |
| 1 | Checked-in (Inhouse) |
| 2 | Checked-out |
| 3 | Cancelled |
| 4 | No-show |
| 100 | Chuyển phòng |

Dùng cho: `bookings.status`, `booking_rooms.status`, `booking_room_guests.status`, `children.status`, `booking_room_children.status`.

### 2.2 Tình trạng đăng ký / Booking Status (bảng gốc `SP1311` — TinhTrangDangKy)
| Giá trị | Ý nghĩa |
|---|---|
| 1 | Guaranteed |
| 20 | None Guaranteed |
| 24 | Cancelled with Penalty |
| 25 | No-show with Penalty |
| 26 | Cancelled without Penalty |
| 27 | Allotment |
| 28 | Cancelled |
| 29 | Waiting |

Field quan trọng của bảng danh mục này (cần giữ lại khi tạo bảng mới):
- `booking_status_color`: màu hiển thị trên Room Plan
- `cut_off_day`: số ngày để tính lại `confirm_date` = ngày đến − cut_off_day (chỉ áp dụng khi booking chưa chắc chắn)
- `is_availability`: `1` = trừ vào phòng trống thật (Room AV); `0` = booking nháp, không ảnh hưởng AV/báo cáo
- `bk_definite` = 4: khi hủy booking, hệ thống tự chuyển Booking Status về dòng có `bk_definite = 4`; nếu không có dòng nào thì giữ nguyên trạng thái cũ khi hủy

### 2.3 Tình trạng phòng vật lý (bảng gốc `SP1313` — TinhTrangPhong)
| Giá trị | Ý nghĩa |
|---|---|
| 1 | Phòng sẵn sàng |
| 2 | Phòng chưa dọn |
| 3 | Phòng sạch |
| 4 | Phòng sửa chữa |
| 5 | Phòng dịch vụ |
| 6 | Turndown |
| 11 | Chiếm dụng — sẵn sàng |
| 12 | Chiếm dụng — chưa dọn |
| 13 | Chiếm dụng — sạch |
| 14 | Chiếm dụng — OOO |
| 16 | Phòng ưu tiên dọn |

---

## 3. MAPPING BẢNG: SQL SERVER (CŨ) → MYSQL (MỚI)

Quy tắc đặt tên mới: `snake_case`, số nhiều cho bảng master/transaction (`rooms`, `bookings`...), tiếng Anh nghiệp vụ chuẩn ngành khách sạn (PMS terms quốc tế: reservation/booking, folio, rate plan, market segment...).

> Cột "Mã cũ" = tên bảng SQL Server trong file gốc. Cột "Đề xuất mới" = tên bảng cho dự án MySQL (theo đúng tinh thần ví dụ bạn đưa: `sp1500` → `system_date`).

### 3.1 NHÓM DANH MỤC (MASTER DATA) — cần tạo TRƯỚC vì các bảng đăng ký FK tới đây

| Mã cũ | Tên VN (mô tả) | Đề xuất tên mới (MySQL) | Ghi chú nghiệp vụ giữ lại |
|---|---|---|---|
| SP1322 | ThongTinKhachSan | `hotel_settings` | Có field `extra_bed_rate` (giá extra bed mặc định), field cấu hình giá ăn sáng trẻ em |
| SP1316 | KhuVuc | `room_zones` | Khu vực/tòa nhà chứa phòng |
| SP1101 | NhomLoaiPhong | `room_type_groups` | Nhóm cha của loại phòng |
| SP1100 | LoaiPhong | `room_types` | Loại phòng (Standard, Deluxe...) |
| SP1200 | DangPhong | `room_kinds` | "Dạng phòng" — kết hợp với room_type tạo thành cặp Room Type + Room Kind dùng trong rule `AllowOverRoomTypeRoomKind` |
| SP1000 | Phong | `rooms` | Có field `status` = FK tới `room_statuses`; lưu ý có **phòng ảo** dùng cho bill dịch vụ không lưu trú / gán cho outlet POS — cần cột `is_virtual` |
| SP8038 | GiaPhongChuan | `standard_room_rates` | Giá phòng chuẩn theo loại phòng |
| SP1309 | TinhTrangChung | `general_statuses` | Enum dùng chung — có thể làm bảng lookup hoặc hard-code enum trong code (khuyến nghị: hard-code vì giá trị cố định, xem mục 2.1) |
| SP1311 | TinhTrangDangKy | `booking_statuses` | Xem mục 2.2 — **bảng quan trọng nhất về business rule**, giữ đủ field `cut_off_day`, `is_availability`, `bk_definite`, `booking_status_color`, `is_hidden` |
| SP1313 | TinhTrangPhong | `room_statuses` | Xem mục 2.3 |
| SP8015 | DanhXung | `guest_titles` | Mr/Mrs/Ms — có field xác định giới tính |
| SP8017 | DanhMucCuaKhau | `border_gates` | Cửa khẩu nhập cảnh |
| SP8019 | DanhMucMucDichLuuTru | `stay_purposes` | Mục đích lưu trú |
| SP8020 | DanhMucQuocTich | `nationalities` | Quốc tịch |
| SP8042 | GuestType | `guest_types` | Loại khách |
| SP8047 | Tinh/Thanh Pho | `provinces` | |
| SP8048 | Quận Huyện | `districts` | |
| SP8049 | Phường/Xã | `wards` | |
| SP8055 | LoaiGiayTo | `id_document_types` | CMND/CCCD/Passport |
| SP1304 | BoPhan | `departments` | FO, MR, FB, BQ... |
| SP1306 | DichVu | `services` | Danh mục toàn bộ dịch vụ hệ thống |
| SP1305 | BoPhanDichVu | `department_services` | Dịch vụ dùng được ở modun nào (đặt phòng/lễ tân) |
| SP1326 | HinhThucThanhToan | `payment_methods` | Field `group` (1 Tiền mặt, 2 Thẻ/CK, 3 Voucher, 4 Công nợ, 5 Miễn phí) + field `is_free` |
| SP1329 | Currency | `currencies` | |
| SP8035 | Folio | `folios` | Nhóm bill khi thanh toán |
| SP1319 | CaLamViec | `work_shifts` | |
| SP1325 | YeuCauDacBiet | `special_request_types` | Danh mục yêu cầu đặc biệt — field `type_special` (honeymoon/birthday/baby_cot) điều khiển icon trên Room Map |
| SP1334 | Lý do huỷ phòng | `cancel_reasons` | Có loại "Other" cho phép nhập ghi chú tự do |
| SP8050 | MauDangKy | `booking_color_settings` | Set màu hiển thị BK trên Room Map |
| sp1500 | Ngày hệ thống | **`system_date`** *(ví dụ bạn đã đặt)* | Ngày hệ thống hiện tại — mọi validate ngày đến/đi của booking đều so với bảng này, KHÔNG so với ngày thực (`NOW()`) của server |
| SP8051 | LucSangNgay | `day_end_logs` | Log mỗi lần chạy sang ngày — cột `system_day` phải UNIQUE |
| SP1600 | ThongSo | `system_parameters` | Bảng cấu hình tham số hệ thống (dạng key-value) — chứa các rule quan trọng như `AllowOverRoomTypeRoomKind`, `Booking_HiddenBKInfo`, `Booking_BFChildSetServiceId`, `Booking_AutoExtraChargeBFChild`, `IsCheckBookingStatusWhenCheckin`, `Booking_RuleUserUnLockDoNotMove`, `CheckModuleBeforeDelete` |
| SP1302 | CongTy | `companies` | Field `payment` = 1 cho phép công nợ; `booker` FK tới `SP1328` |
| SP1301 | LoaiCongTy | `company_types` | |
| SP1318 | ChiNhanhCongTy | `company_branches` | |
| SP1308 | MarketSegment | `market_segments` | |
| sp1346 | SupMarketSegment | `market_segment_groups` | Nhóm cha của market segment |
| SP8037 | SourceCode | `booking_sources` | Nguồn khách |
| SP1328 | Booker | `contact_persons` | Người liên hệ của công ty |
| SP1340 | MaGiaPhong (Rate Code) | `rate_codes` | |
| SP1341 | ChiTietMaGiaPhong (RatePlan) | `rate_plans` | |
| SP1342 | ChiTietGiaTheoTungNgay | `rate_plan_daily_prices` | |
| SP1343 | Packege | `rate_packages` | *(ghi chú gốc: chưa sử dụng — có thể bỏ qua ở bản đầu)* |
| SP4001 | PhongOOO | `room_out_of_order` | Sang ngày tự mở khóa theo giờ ở `system_parameters.FrmOOO_DefineLockByTime`, phòng tự chuyển "chưa dọn" |
| SP4002 | PhongOOS | `room_out_of_service` | |
| SP4000 | PhongNoShow | `no_show_rooms` | |
| SP4003 | PhongLateCheckin | `late_checkin_rooms` | |
| SP1332 | Alert | `alerts` | |

### 3.2 NHÓM LÕI — GIAO DỊCH ĐẶT PHÒNG (CORE TRANSACTION) — trọng tâm của module

| Mã cũ | Tên VN | Đề xuất tên mới | Vai trò |
|---|---|---|---|
| **SP2000** | DangKy | **`bookings`** | **Bảng gốc của 1 đăng ký (booking header)** |
| **SP2100** | PhongThue | **`booking_rooms`** | Chi tiết từng phòng trong booking (1 booking → N phòng) |
| **SP2200** | PhongThueKhach | **`booking_room_guests`** | Người lớn ở trong 1 phòng thuê (1 phòng → N khách) |
| **SP2300** | Khach | **`guests`** | Hồ sơ chi tiết khách (thông tin cá nhân, giấy tờ) |
| **SP2400** | TreEm | **`children`** | Hồ sơ trẻ em |
| **SP2500** | PhongThueTreEm | **`booking_room_children`** | Trẻ em ở trong phòng thuê nào |
| **SP2401** | TreEmAnSangChiTiet | **`children_breakfast_details`** | Giá ăn sáng trẻ em theo từng ngày |
| **SP2102** | PhongThueDichVuTuDong | **`booking_room_auto_services`** | Dịch vụ tự động phát sinh theo từng đêm (giá phòng thay đổi theo đêm, EB theo ngày, dịch vụ định kỳ) |
| **SP2107** | PhongThueSpecialRequest | **`booking_room_special_requests`** | Yêu cầu đặc biệt đã gắn cho phòng |
| SP8052 | HuyPhongDangKy | `booking_room_cancel_logs` | Log hủy từng phòng |
| SP8053 | HuyDangKy | `booking_cancel_logs` | Log hủy cả booking |
| SP8000 | TraPhong | `room_return_logs` | Log trả phòng / check-out |
| SP8022 | Do not move | `room_lock_logs` | Khóa không cho đổi số phòng — field `user_lock` để kiểm tra ai khóa mới được mở (trừ khi user nằm trong rule `Booking_RuleUserUnLockDoNotMove`) |
| SP3002 | ThanhToan | `payments` | Dùng chung cho cả Đặt cọc & Thanh toán — xem chi tiết field ở mục 4.9, đây là bảng phức tạp nhất hệ thống, **nên tách riêng 1 nhánh phân tích khi code** |

### 3.3 NHÓM PHỤ TRỢ (liên quan gián tiếp, có thể làm sau)

| Mã cũ | Tên VN | Đề xuất tên mới | Ghi chú |
|---|---|---|---|
| SP8028 | PhuongTienDuaDon | `transport_vehicles` | Đưa đón khách |
| SP8045 | DiaDiemDuaDonSanKhach | `pickup_locations` | |
| SP1315 | Transpostation | `transportations` | |
| SP1333 | Lost and found | `lost_and_found` | |
| sp1345 | Allotment | `allotments` | Hợp đồng phân bổ phòng — `booking_rooms.allotment_id` tham chiếu tới đây khi lấy phòng từ allotment |

---

## 4. BUSINESS RULE CHI TIẾT THEO TỪNG CHỨC NĂNG (để code logic, không chỉ tạo bảng)

### 4.1 Tạo mới đặt phòng
- Mã đăng ký (`bookings.code`): auto-increment logic riêng (không phải AUTO_INCREMENT thường) — sinh mã tăng dần khi **insert thành công**, kiểm tra trùng với `bookings` hiện có.
- Ngày đến (`arrival_date`) phải **≥ system_date** (lấy từ bảng `system_date`, không phải giờ server thực) — chặn ngay ở UI lẫn validate backend.
- Ngày đi phải ≥ ngày đến.
- `confirm_date` mặc định = ngày tạo booking; nếu `booking_status.is_availability = 0` (chưa chắc chắn) thì tính lại: `confirm_date = arrival_date − cut_off_day` (nếu kết quả < ngày hiện tại thì lấy = arrival_date).
- Chọn Công ty (`company_id`): chỉ liệt kê company có `is_use = 1`; khi chọn company, tự fill `market_segment` + `booking_source` theo cấu hình company (cho phép sửa lại).
- `bookings.module`: lưu module tạo booking (reservation / front_office...) — dùng để phân quyền xóa theo `system_parameters.CheckModuleBeforeDelete`.
- **Lấy phòng vào booking**: ngày đến/đi của từng `booking_room` phải nằm trong khoảng ngày của `bookings`.
- Giá ăn sáng trẻ em: nếu hotel có phụ thu (`hotel_settings`) → tính riêng; nếu không phụ thu, gộp vào tiền phòng → tách doanh thu theo tham số `BreakfastRateChild`.
- Khi tạo booking với `booking_status.is_availability = 0`: hiển thị popup cảnh báo cho user biết booking này không trừ vào phòng trống.
- Khi lấy phòng, nếu SL phòng lấy > SL phòng AV: kiểm tra `system_parameters.AllowOverRoomTypeRoomKind`
  - `= 0`: chặn, hiện cảnh báo "vượt quá số phòng trống cho phép", input disable khi AV = 0
  - `= 1`: vẫn cho phép, cảnh báo âm phòng (UI hiển thị số AV màu đỏ khi ≤ 0)
- Mã dịch vụ mặc định: tiền phòng = `RM`, extra bed = `EB`, phụ thu ăn sáng trẻ em = theo `system_parameters.Booking_BFChildSetServiceId`.
- Tham số `Booking_HiddenBKInfo`: danh sách field cần ẩn trên form booking (VD: DebtAmount, MemberCompany, ManagementDepartment, PaymentMethod) — nên thiết kế UI theo config-driven field, không hard-code.
- Tham số `Booking_AutoExtraChargeBFChild`: `0` = mặc định trẻ em ăn sáng miễn phí không tính phụ phí; `1` = mặc định tính phụ phí ăn sáng trẻ em theo giá ở `hotel_settings`.

### 4.2 Cập nhật (sửa nhanh nhiều phòng)
- Chỉ cho sửa phòng ở trạng thái **đăng ký** hoặc **inhouse** (1 phần thông tin); phòng đã **check-out** khóa hoàn toàn.
- Nếu phòng đang inhouse: chỉ cho sửa ngày đi, giờ đi, giá phòng — khóa các field khác.
- Sửa ngày đến/đi: check lại AV theo rule `AllowOverRoomTypeRoomKind` (giống mục 4.1); ngày đến ≥ system_date; ngày đi > ngày đến.
- Đồng bộ 2 chiều: sửa ngày phòng → nếu vượt ra ngoài khoảng ngày của booking cha → tự động cập nhật lại ngày của `bookings` luôn (update cả `bookings`, `booking_rooms`, `booking_room_guests`).
- Sửa giá: với phòng đang inhouse, những đêm **đã chạy bill tiền phòng** (ngày < system_date) giữ nguyên giá cũ, chỉ áp giá mới cho đêm ≥ system_date.
- Sửa extra bed & giá extra bed: cập nhật số lượng/giá + insert dòng dịch vụ tự động EB vào `booking_room_auto_services`.

### 4.3 Tự động gán số phòng
- Chỉ gán số phòng đúng loại/dạng phòng đã chọn.
- Số phòng gán phải **trống toàn bộ khoảng ngày ở** của phòng thuê, không được trùng phòng khác trong cùng khoảng ngày.
- Thứ tự ưu tiên: tầng thấp → tầng cao.
- Cập nhật `booking_rooms.room_id`.
- Logic overlap ngày: 1 phòng vật lý có thể gán cho booking thứ 2 nếu ngày đến của booking sau = ngày đi của booking trước (liền kề, không chồng lấn).

### 4.4 Dịch vụ bổ sung (định kỳ)
- Danh sách dịch vụ lấy từ `department_services` với `department = FO` (không gồm EB vì EB set riêng có số lượng).
- Chỉ post cho phòng ở trạng thái đăng ký/inhouse; không cho chọn ngày quá khứ (< system_date).
- Nếu user thêm trùng ngày + dịch vụ đã có → **cập nhật lại giá**, KHÔNG cộng dồn số lượng; chỉ insert thêm cho các ngày chưa có.
- Khi sang ngày (night audit), các dịch vụ này tự động post vào Folio của phòng/booking mà không cần lễ tân thao tác.

### 4.5 Giao phòng (Check-in)
- Chỉ thao tác được khi đứng ở module Lễ tân (Front Office).
- Điều kiện: `arrival_date = system_date` và `booking_rooms.status = 0` (đăng ký).
- Nếu `system_parameters.IsCheckBookingStatusWhenCheckin = 1`: chỉ cho check-in khi Booking Status ∈ {1 (Guaranteed), 27 (Allotment)}; nếu = 0 thì bỏ qua check này.
- Trạng thái phòng vật lý phải = "Phòng sẵn sàng".

### 4.6 Nâng hạng phòng
- Chỉ áp dụng cho phòng ở trạng thái đăng ký.
- Check AV của loại phòng mới trong khoảng ngày ở — áp dụng rule `AllowOverRoomTypeRoomKind` như mục 4.1.
- Lưu lại loại phòng cũ vào cột "LP Khởi Tạo" (`booking_rooms.original_room_type_code`, tương đương `SP2100.Pack4`).

### 4.7 Thông tin khách
- 3 cách nhập liệu: nhập tay, import từ Excel mẫu, scan CCCD/passport qua máy scan chuyên dụng.
- Chỉ cho nhập với khách ở trạng thái đăng ký & inhouse.
- Cho phép "kế thừa" thông tin khách cũ đã có trong hệ thống (autofill toàn bộ field từ `guests`/`children`).

### 4.8 Gỡ số phòng / Hủy phòng / Xóa dịch vụ bổ sung
- **Gỡ số phòng**: set `booking_rooms.room_id = NULL`.
- **Hủy phòng**: cascade update status = 3 (Cancelled) cho `booking_rooms`, `booking_room_guests`, `guests.guest_status`, `children.status`, `booking_room_children.status`.
- **Xóa dịch vụ bổ sung**: chỉ cho xóa dịch vụ ở phòng đăng ký/inhouse, chỉ những dòng có ngày ≥ system_date; nếu xóa hết ngày của Extra Bed → reset `booking_rooms.extra_bed = 0`, `extra_bed_rate = 0`.

### 4.9 Đặt cọc (Deposit) — dùng bảng `payments`
Đây là bảng nghiệp vụ phức tạp nhất, cần thiết kế field đầy đủ ngay từ đầu:

| Field (gợi ý) | Ý nghĩa |
|---|---|
| `date` | Ngày cọc — cho phép chọn ngày cũ (khác ngày thao tác thực) |
| `open_time` | Thời gian tạo |
| `guest_display` | Hiển thị theo quy tắc: mã booking + công ty + tên booker |
| `department_id` | Bộ phận tạo cọc, theo module thao tác (MR/FO/FB) |
| `payment_method_id` | FK `payment_methods` — **không hiển thị** hình thức Công nợ & Miễn phí khi tạo cọc |
| `description` | Mặc định `"Deposit + (hình thức TT)"`, cho sửa |
| `amount` | Số tiền |
| `edit_flag` | 0 = bình thường, 1 = dòng đã hủy/đối trừ |
| `folio_id` | Mặc định folio 1 lúc tạo cọc, cập nhật lại khi thanh toán |
| `payment_id` | Mã thanh toán khi dòng cọc được dùng để thanh toán |
| `vat_number`, `serial`, `invoice_number` | Thông tin hóa đơn VAT nếu xuất |
| `booking_id`, `booking_room_id`, `guest_id`, `company_id` | Tham chiếu tới đối tượng phát sinh cọc (booking hoặc phòng) |
| `username`, `shift` | User & ca tạo |
| `status` | 1 = chưa thanh toán, 2 = đã thanh toán, 3 = đã xóa |
| `outlet` | Nếu tạo ở module nhà hàng (FB/Banquet...) |
| `debit_account` | Tài khoản ngân hàng |
| `pack2` | `"DPR"` nếu là dòng đặt cọc (phân biệt với thanh toán thường) |
| `pack4` | `"AP"` nếu là advance payment |
| `reversal_ref` | Khi xóa/chuyển cọc, sinh dòng âm đối trừ dòng gốc; 2 dòng lưu `edit_flag=1` và tham chiếu chéo mã của nhau (dùng cho báo cáo hủy cọc) |

**Business rule đặt cọc**:
- **Sửa**: chỉ sửa hình thức TT + ghi chú; chỉ khi booking chưa check-out **và** cọc chưa thanh toán (`payment_id IS NULL`).
- **Tách cọc**: tách đôi/ba/theo số tiền tùy chọn — insert dòng mới, update dòng gốc, lưu `payment_total_amount_before_split`; điều kiện giống trên.
- **Chuyển cọc**: chuyển sang booking/phòng khác (đang ở trạng thái đăng ký/inhouse) — sinh dòng âm đối trừ + dòng dương mới, giữ nguyên `create_date`/`create_user` gốc; điều kiện giống trên.
- **Xóa**: check quyền xóa + quyền xóa dữ liệu ngày cũ; sinh dòng âm đối trừ; điều kiện `payment_id IS NULL`.

### 4.10 Nhân bản (Copy booking)
- Nhập lại ngày đến/đi cho booking mới; copy toàn bộ thông tin + số lượng/loại phòng của booking gốc; **không copy** tiền cọc và dịch vụ tự động.
- `system_parameters.IsCopyAllBooking`:
  - `= 1`: copy cả phòng — check AV như mục 4.1; nếu thiếu phòng và `AllowOverRoomTypeRoomKind = 0` → hỏi user có tiếp tục không (nếu không → chỉ tạo booking, bỏ qua phòng; nếu hủy → không tạo gì)
  - `= 0`: chỉ copy thông tin booking, không copy phòng
- Insert liên quan: `bookings`, `booking_rooms`, `booking_room_guests`, `guests`, và nếu có trẻ em thì `children`, `booking_room_children`.

### 4.11 Xóa & Khôi phục booking
- **Xóa**: chỉ khi TẤT CẢ phòng trong booking đều ở trạng thái đăng ký (chưa có phòng inhouse) và booking **không có** cọc/advance payment (nếu có → cảnh báo, chặn xóa). Cascade update status = 3 (Cancelled), `booking_status = 28`.
- **Khôi phục**: 
  - Nếu `arrival_date` < system_date → chỉ khôi phục thông tin booking, KHÔNG khôi phục phòng.
  - Nếu `arrival_date` > system_date → khôi phục cả booking + phòng.
  - `booking_status` vẫn giữ nguyên "Cancelled" sau khi khôi phục để user tự kiểm tra lại thực tế.

---

## 5. QUAN HỆ KHÓA CHÍNH (ERD RÚT GỌN — NHÓM CORE)

```
companies ──┐
market_segments ──┼──< bookings >── booking_statuses
booking_sources ──┘        │
                            ├──< booking_rooms >── rooms ── room_types / room_kinds
                            │         │
                            │         ├──< booking_room_guests >── guests
                            │         ├──< booking_room_children >── children
                            │         ├──< booking_room_auto_services >── services
                            │         ├──< booking_room_special_requests >── special_request_types
                            │         └──< children_breakfast_details
                            │
                            └──< payments (deposit)
```

- `bookings.id` = PK, dùng làm FK cho toàn bộ nhóm transaction.
- `booking_rooms.booking_id` → `bookings.id`
- `booking_room_guests.booking_room_id` → `booking_rooms.id`
- `booking_room_guests.guest_id` → `guests.id`
- `booking_room_children.booking_room_id` → `booking_rooms.id`
- `booking_room_children.child_id` → `children.id`
- `children_breakfast_details.booking_room_child_id` → `booking_room_children.id`
- `booking_room_auto_services.booking_room_id` → `booking_rooms.id`, `.service_id` → `services.id`

---

## 6. ROADMAP TRIỂN KHAI ĐỀ XUẤT (để giao việc từng phase cho CLI)

**Phase 0 — Chuẩn bị**
- Tạo `system_date`, `system_parameters`, `general_statuses`/enum cứng, `booking_statuses`, `room_statuses`.

**Phase 1 — Danh mục nền (Master data)**
- Toàn bộ bảng ở mục 3.1: rooms, room_types, room_kinds, guest_titles, nationalities, id_document_types, departments, services, payment_methods, companies, market_segments, booking_sources, contact_persons, rate_codes/rate_plans, cancel_reasons, special_request_types.

**Phase 2 — Core Booking (CRUD cơ bản)**
- `bookings`, `booking_rooms`, `booking_room_guests`, `guests`, `children`, `booking_room_children`.
- API: Tạo mới đặt phòng (4.1), Sửa đăng ký (4.2), Xóa/Hủy (4.8, 4.11), Khôi phục (4.11).

**Phase 3 — Nghiệp vụ nâng cao trên booking**
- Tự động gán số phòng (4.3), Nâng hạng phòng (4.6), Giao phòng/Check-in (4.5), Do-not-move (mục 3.2 `room_lock_logs`).

**Phase 4 — Dịch vụ & phụ thu**
- `booking_room_auto_services`, `children_breakfast_details`, extra bed, `booking_room_special_requests`.

**Phase 5 — Tài chính**
- `payments` (đặt cọc) — nên làm riêng vì logic tách/chuyển/xóa cọc phức tạp (mục 4.9).
- `folios`.

**Phase 6 — Tiện ích**
- Nhân bản booking (4.10), Xuất Excel, In phiếu đăng ký/hóa đơn tạm.

**Phase 7 — Vận hành ngày (Night Audit)** *(liên quan nhưng thuộc module khác)*
- `day_end_logs`, cập nhật `system_date`, tự động mở khóa `room_out_of_order` theo giờ, tự động post `booking_room_auto_services` vào bill.

---

## 7. LƯU Ý KHI CHUYỂN ĐỔI MSSQL → MYSQL

1. **Ngày hệ thống là nguồn sự thật cho "hôm nay"**, không dùng `NOW()`/`CURDATE()` của MySQL để validate nghiệp vụ — luôn đọc từ bảng `system_date`.
2. Các cột kiểu `Pack1/Pack2/Pack3/Pack4` trong bảng gốc là kiểu "cột đa năng" (dùng lại cho nhiều mục đích tùy nghiệp vụ) — **không nên giữ kiểu này ở DB mới**, hãy tách thành cột có tên rõ nghĩa (đã đề xuất ở mục 4, ví dụ `original_room_type_code` thay cho `Pack4`).
3. `system_parameters` (gốc `SP1600`) nên thiết kế dạng key-value (`param_key`, `param_value`, `description`) để dễ mở rộng, thay vì cứng nhiều cột.
4. Toàn bộ mã tăng lũy tiến kiểu "check bảng rồi +1" (VD mã booking) nên cân nhắc dùng `AUTO_INCREMENT` chuẩn của MySQL + có thể thêm cột `code` riêng dạng chuỗi hiển thị nếu cần format đặc thù (VD: BK000123).
5. Cẩn thận field so sánh **case-insensitive** của SQL Server (VD tên bảng `sp1345`, `Sp5422` viết hoa/thường lẫn lộn trong file gốc) — MySQL mặc định phân biệt hoa/thường tùy collation, cần chuẩn hóa 1 kiểu duy nhất khi đặt tên bảng/cột mới (khuyến nghị toàn bộ `snake_case` chữ thường).
6. Rule cascade status (hủy booking → hủy phòng → hủy khách → hủy trẻ em...) nên viết ở **tầng service/application**, không nên dùng `ON DELETE CASCADE` cứng ở MySQL vì đây là **soft-cancel** (update status), không phải xóa bản ghi (`DELETE`).

---

## 8. BẢNG TRA CỨU NHANH — TOÀN BỘ MÃ CŨ LIÊN QUAN ĐẾN ĐẶT PHÒNG

*(Danh sách rút gọn các mã hay bị nhắc chéo trong business rule, để tiện tìm khi đọc lại mô tả gốc)*

| Mã cũ | = Tên mới |
|---|---|
| sp1500 | `system_date` |
| SP1600 | `system_parameters` |
| SP1309 | `general_statuses` (enum) |
| SP1311 | `booking_statuses` |
| SP1313 | `room_statuses` |
| SP2000 | `bookings` |
| SP2100 | `booking_rooms` |
| SP2200 | `booking_room_guests` |
| SP2300 | `guests` |
| SP2400 | `children` |
| SP2500 | `booking_room_children` |
| SP2401 | `children_breakfast_details` |
| SP2102 | `booking_room_auto_services` |
| SP2107 | `booking_room_special_requests` |
| SP8052 | `booking_room_cancel_logs` |
| SP8053 | `booking_cancel_logs` |
| SP8000 | `room_return_logs` |
| SP8022 | `room_lock_logs` |
| SP3002 | `payments` |
| SP1302 | `companies` |
| SP1308 | `market_segments` |
| SP8037 | `booking_sources` |
| SP1328 | `contact_persons` |
| SP1340/1341/1342 | `rate_codes` / `rate_plans` / `rate_plan_daily_prices` |
| SP1325 | `special_request_types` |
| SP1334 | `cancel_reasons` |
| SP1000/1100/1200/1101 | `rooms` / `room_types` / `room_kinds` / `room_type_groups` |
| SP1322 | `hotel_settings` |
| SP1326 | `payment_methods` |
| SP8035 | `folios` |

---

**Hết plan.** Nếu bạn muốn, bước tiếp theo mình có thể viết luôn **file `.sql` migration** cho Phase 0 + Phase 1 (danh mục nền), hoặc viết chi tiết **schema đầy đủ (kèm kiểu dữ liệu cột)** cho nhóm Core Booking ở mục 3.2.
