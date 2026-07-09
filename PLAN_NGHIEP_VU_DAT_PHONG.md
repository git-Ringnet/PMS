# PLAN NGHIỆP VỤ: ĐẶT PHÒNG (BOOKING/RESERVATION) — PMS MIGRATION SQL SERVER → MYSQL

> Nguồn phân tích: `DANH_SÁCH_BẢNG_TRONG_HỆ_THỐNG.xlsx` (sheet `TABLE PMS` + sheet `MÔ TẢ NGHIỆP VỤ`)
> Mục tiêu: chuyển đổi nghiệp vụ Đặt phòng từ hệ PMS cũ (SQL Server, bảng đặt tên `SPxxxx`) sang dự án mới (MySQL, tên bảng theo domain-name), giữ nguyên rule nghiệp vụ.
> Cách dùng: copy từng Epic bên dưới vào Antigravity CLI làm task/spec để code.

---

## 1. BẢNG MAPPING TÊN BẢNG (SQL Server → MySQL)

> Quy ước đặt tên mới: `snake_case`, số nhiều cho bảng danh sách, tiền tố `booking_` cho các bảng con thuộc 1 booking.
> ⚠️ Tên MySQL dưới đây là **đề xuất** dựa trên đúng mô tả nghiệp vụ của từng bảng — bạn có thể đổi lại cho khớp bảng đã tạo sẵn trong dự án mới, miễn giữ đúng vai trò dữ liệu.

### 1.1. Nhóm bảng LÕI của nghiệp vụ Đặt phòng

| STT | Tên bảng SQL Server (cũ) | Mô tả nghiệp vụ (theo file gốc) | Tên bảng MySQL (mới - đề xuất) |
|---|---|---|---|
| 1 | `SP1500` | Ngày hệ thống (system date) — mốc ngày để so sánh ngày đến/ngày đi hợp lệ | `system_date` |
| 2 | `SP2000` | DangKy — bảng header của 1 lượt đặt phòng (booking), sinh mã đăng ký tăng lũy tiến | `bookings` |
| 3 | `SP2100` | PhongThue — chi tiết từng phòng trong 1 booking (ngày đến/đi, giá, số phòng gán, extra bed...) | `booking_rooms` |
| 4 | `SP2200` | PhongThueKhach — gán khách (người lớn) vào từng phòng thuê | `booking_room_guests` |
| 5 | `SP2300` | Khach — thông tin khách lưu trú (người lớn) | `guests` |
| 6 | `SP2400` | TreEm — thông tin trẻ em đi kèm booking | `booking_children` |
| 7 | `SP2500` | PhongThueTreEm — gán trẻ em vào từng phòng thuê | `booking_room_children` |
| 8 | `SP2401` | TreEmAnSangChiTiet — chi tiết ăn sáng/phụ thu ăn sáng trẻ em theo từng ngày | `booking_child_breakfast_details` |
| 9 | `SP2102` | PhongThueDichVuTuDong — dịch vụ set up trước trong booking (tự động post khi sang ngày), gồm cả Extra Bed (EB) | `booking_room_services` |
| 10 | `SP2107` | PhongThueSpecialRequest — yêu cầu đặc biệt đã chọn cho từng phòng | `booking_room_special_requests` |
| 11 | `SP1325` | YeuCauDacBiet (SpecialRequest) — danh mục master các loại yêu cầu đặc biệt (honey moon, birthday, baby cot...) | `special_requests` |
| 12 | `SP1309` | TinhTrangChung — danh mục trạng thái chung dùng chia sẻ nhiều module | `general_statuses` |
| 13 | `SP1311` | TinhTrangDangKy — danh mục trạng thái của booking (VD: 1, 27 = các trạng thái được phép check-in...) | `booking_statuses` |
| 14 | `SP1313` | TinhTrangPhong — danh mục trạng thái phòng vật lý (sẵn sàng, chưa dọn, sạch, khóa OOO/OOS...) | `room_statuses` |
| 15 | `SP8022` | Do not move — khóa không cho đổi/chuyển số phòng đã gán, lưu user khóa | `room_do_not_move_locks` |
| 16 | `SP8052` | HuyPhongDangKy — log/lịch sử hủy phòng trong booking | `booking_room_cancel_logs` |
| 17 | `SP8053` | HuyDangKy — log/lịch sử hủy toàn bộ booking | `booking_cancel_logs` |
| 18 | `SP1334` | Lý do huỷ phòng — danh mục lý do hủy | `cancel_reasons` |
| 19 | `SP8000` | TraPhong — nghiệp vụ trả phòng/check-out (liên quan giai đoạn sau của booking) | `checkouts` |

### 1.2. Nhóm bảng DANH MỤC/THAM CHIẾU (dùng để dựng dropdown, validate trong màn hình Đặt phòng)

| STT | Tên bảng SQL Server (cũ) | Mô tả | Tên bảng MySQL (mới - đề xuất) |
|---|---|---|---|
| 20 | `SP1322` | ThongTinKhachSan — cấu hình khách sạn (giá extra bed mặc định, giá ăn sáng trẻ em mặc định...) | `hotel_settings` |
| 21 | `SP1000` | Phong — danh sách phòng vật lý (bao gồm cả phòng ảo) | `rooms` |
| 22 | `SP1100` | LoaiPhong — loại phòng (room type) | `room_types` |
| 23 | `SP1101` | NhomLoaiPhong — nhóm loại phòng | `room_type_groups` |
| 24 | `SP1200` | DangPhong — dạng/hạng phòng | `room_kinds` |
| 25 | `SP1302` | CongTy — công ty/đối tác đặt phòng | `companies` |
| 26 | `SP1301` | LoaiCongTy | `company_types` |
| 27 | `SP1308` | MarketSegment — thị trường khách | `market_segments` |
| 28 | `sp1346` | SupMarketSegment — nhóm thị trường cha | `market_segment_groups` |
| 29 | `SP8037` | SourceCode — nguồn khách | `booking_sources` |
| 30 | `SP1328` | Booker — người liên hệ đặt phòng | `bookers` |
| 31 | `SP1304` | BoPhan — bộ phận | `departments` |
| 32 | `SP1306` | DichVu — danh mục dịch vụ (RM=tiền phòng, EB=extra bed, BD=phụ thu ăn sáng trẻ em...) | `services` |
| 33 | `SP1305` | BoPhanDichVu — dịch vụ theo từng bộ phận (vd DepartmentID=FO cho danh sách dịch vụ bổ sung ở lễ tân) | `department_services` |
| 34 | `SP1340` | MaGiaPhong (Rate Code) | `rate_codes` |
| 35 | `SP1341` | ChiTietMaGiaPhong (Rate Plan) | `rate_plans` |
| 36 | `SP1342` | ChiTietGiaTheoTungNgay — giá chi tiết theo từng ngày | `rate_plan_daily_prices` |
| 37 | `SP1332` | Alert | `alerts` |

> Ghi chú: các bảng nhóm HÓA ĐƠN/THANH TOÁN (`SP3000-SP3007`, `SP8035 Folio`...) không thuộc phạm vi mô tả nghiệp vụ Đặt phòng lần này — chỉ liệt kê tham chiếu, chưa build ở giai đoạn này.

---

## 2. THAM SỐ HỆ THỐNG (config, KHÔNG PHẢI BẢNG) cần có trong hệ thống mới

Đây là các **system parameter / setting**, không phải bảng dữ liệu — cần 1 bảng `system_configs` (key-value) để lưu:

| Tên tham số (cũ) | Ý nghĩa |
|---|---|
| `AllowOverRoomTypeRoomKind` | 0/1 — Có cho phép tạo/sửa booking dẫn đến âm số lượng phòng trống (AV) hay không |
| `Booking_HiddenBKInfo` | Danh sách field cần ẩn trên màn hình booking (VD: DebtAmount, MemberCompany, ManagementDepartment, PaymentMethod) |
| `Booking_BFChildSetServiceId` | Mã dịch vụ áp dụng cho phụ thu ăn sáng trẻ em |
| `Booking_AutoExtraChargeBFChild` | 0/1 — Mặc định có tự động tính phụ thu ăn sáng trẻ em khi thêm trẻ em vào phòng hay không |
| `IsCheckBookingStatusWhenCheckin` | 0/1 — Khi check-in có bắt buộc kiểm tra trạng thái booking hợp lệ (1, 27) hay không |
| `Booking_RuleUserUnLockDoNotMove` | Rule user được phép mở khóa Do Not Move mà không cần là người đã khóa |
| `GiaAnSangTreEm` | Giá ăn sáng mặc định cho trẻ em |
| `BreakfastRateChild` | Thông số tách doanh thu tiền ăn sáng trẻ em ra khỏi tiền phòng khi không tính phụ phí riêng |
| Mã dịch vụ mặc định | `RM` = tiền phòng, `EB` = extra bed, `BD` = phụ thu ăn sáng trẻ em |

---

## 3. CHI TIẾT NGHIỆP VỤ THEO TỪNG CHỨC NĂNG (dùng tên bảng MỚI)

### Epic 1 — Tạo mới đặt phòng (Create Booking)
**Bảng liên quan:** `bookings` (SP2000), `booking_rooms` (SP2100), `system_date` (SP1500), `booking_statuses` (SP1311), `companies` (SP1302), `market_segments` (SP1308), `booking_sources` (SP8037), `hotel_settings` (SP1322)

Business rules:
1. Mã đăng ký (`bookings.booking_code`): mặc định trống khi mở form; chỉ **insert** vào `bookings` (sinh mã tăng lũy tiến) khi tạo thành công.
2. Ngày đến hợp lệ: `check_in_date >= system_date.current_date`. Chặn ngay tại UI lịch chọn ngày, không cho chọn nhỏ hơn ngày hệ thống.
3. Ngày đi: `check_out_date >= check_in_date`. Chặn ngay tại UI lịch chọn ngày đi.
4. Trạng thái đăng ký: hiển thị danh sách các trạng thái **đang active** từ `booking_statuses`.
5. Ngày xác nhận (`confirmation_date`):
   - Mặc định = ngày tạo booking.
   - Với các trạng thái "không chắc chắn" (VD: Non-guaranteed) có `cut_off_days`: `confirmation_date = check_in_date - cut_off_days`.
   - Nếu `(check_in_date - cut_off_days) < check_in_date` không hợp lệ thì lấy `confirmation_date = check_in_date`.
   - VD: check-in 15/07, non-guaranteed cut-off 3 ngày → confirmation_date = 12/07.
6. Công ty: dropdown chỉ hiển thị company có `is_active = 1` (từ `companies`). Khi chọn công ty → tự động điền `market_segment` và `booking_source` theo cấu hình company đó, nhưng vẫn cho **chọn lại thủ công** thị trường khác.
7. `bookings.created_module`: lưu bộ phận tạo booking (VD: `reservation`, `front_office`).

**Sub-flow: Gán/lấy phòng vào booking**
8. Ngày đến/đi của từng `booking_rooms` phải nằm trong khoảng ngày của `bookings` (booking header).
9. Giá ăn sáng trẻ em:
   - Có phụ phí riêng → lấy theo cấu hình `hotel_settings.extra_bed_rate` / giá ăn sáng set ở hotel settings.
   - Không phụ phí (đã gộp vào tiền phòng) → khi tách doanh thu tiền phòng dùng tham số `BreakfastRateChild`.
10. Popup cảnh báo khi tạo booking với trạng thái có `is_availability = 0`.
11. Kiểm tra tham số `AllowOverRoomTypeRoomKind` khi lấy phòng:
    - `= 0` (không cho âm phòng): nhập số lượng phòng > AV hệ thống → cảnh báo "đã vượt quá số lượng phòng trống", khi AV = 0 khóa luôn ô nhập, hiển thị AV màu đỏ nếu = 0 hoặc âm.
    - `= 1` (cho phép âm): vẫn cho lưu nhưng có cảnh báo xác nhận.
12. `Booking_HiddenBKInfo`: dùng để ẩn động các field trên UI theo cấu hình.

---

### Epic 2 — Cập nhật nhanh nhiều phòng (Bulk Update Rooms)
**Bảng liên quan:** `booking_rooms` (SP2100), `booking_room_guests` (SP2200), `bookings` (SP2000)

1. Cho phép cập nhật: ngày đến, ngày đi, giờ đến, giờ đi, giá, số người lớn/trẻ em, số lượng + giá extra bed — cho **nhiều phòng cùng lúc**.
2. Chỉ áp dụng cho phòng ở trạng thái **đăng ký** hoặc **đang ở (inhouse)** (một phần thông tin); phòng đã check-out (out) không cho thao tác.
3. Đổi ngày đến/đi:
   - Check số lượng AV còn trống của ngày mới.
   - Nếu `AllowOverRoomTypeRoomKind = 1` → vẫn cho lưu kèm cảnh báo âm phòng.
   - Nếu `= 0` → chặn lưu, báo hết phòng trống.
   - Lưu thành công → update `booking_rooms` và `booking_room_guests`.
4. Nếu phòng đang **inhouse**: chỉ cho sửa ngày đi, giờ đi, giá phòng — khóa các field còn lại.
5. Ràng buộc ngày: `check_in >= system_date`, `check_out > check_in`.
6. Đồng bộ ngược lên `bookings` (header): nếu ngày phòng vượt ra ngoài khoảng ngày hiện có của booking → cập nhật lại `check_in_date`/`check_out_date` của `bookings` đồng thời với `booking_rooms`.
7. Cập nhật giá: cho sửa giá kể cả phòng inhouse, nhưng **đêm đã chạy bill tiền phòng** (ngày < ngày hệ thống) giữ nguyên giá cũ; chỉ áp giá mới cho các đêm `>= system_date` (chưa post bill).
8. Thêm giường/giá thêm giường: update số lượng + giá extra bed trên `booking_rooms`, đồng thời **auto-insert** dịch vụ EB vào `booking_room_services` (SP2102).

---

### Epic 3 — Tự động gán số phòng (Auto Room Assignment)
**Bảng liên quan:** `booking_rooms.room_number` (SP2100.Room), `rooms` (SP1000)

1. Kiểm tra số phòng đúng loại/dạng phòng (`room_type`, `room_kind`) với phòng đang cần gán.
2. Kiểm tra tình trạng: số phòng gán phải **còn trống trong toàn bộ giai đoạn ở**, không được gán trùng số phòng cho 2 booking chồng ngày.
3. Ưu tiên gán theo thứ tự **tầng thấp → tầng cao**.
4. Rule trống liên tục: 1 phòng vật lý chỉ được gán cho booking tiếp theo nếu phòng đó **trống liên tục hết giai đoạn ở mới**, không được gán nếu đang thuộc diện khóa (OOO/OOS) hoặc trùng giai đoạn với booking khác.
   - VD: phòng 109 đã gán cho khách ở 03/07–05/07 → không thể gán cho khách có ngày đến trước 05/07; có thể gán cho khách check-in đúng 05/07 nếu liên tục trống hết giai đoạn mới.

---

### Epic 4 — Dịch vụ bổ sung set-up trước (Pre-set Auto Services)
**Bảng liên quan:** `booking_room_services` (SP2102), `department_services` (SP1305 — filter `department_id = FO`)

1. Danh sách dịch vụ: lấy từ `department_services` với bộ phận Lễ Tân (FO), **loại trừ EB** (EB set riêng ở luồng extra bed).
2. Ngày sử dụng: theo từng đêm ở của phòng (`booking_rooms`).
3. Chỉ áp dụng cho phòng ở trạng thái **đăng ký** hoặc **đang ở**.
4. Không cho chọn thêm dịch vụ ở ngày quá khứ (`< system_date`).
5. Nếu người dùng thao tác trùng ngày + trùng dịch vụ đã có: **update lại giá** (`rate`), không cộng dồn số lượng; chỉ insert thêm cho các ngày chưa có dịch vụ.
6. Khi sang ngày (batch job cuối ngày), các dịch vụ đã set-up sẵn sẽ **tự động post** cùng tiền phòng vào Folio, không cần thao tác thủ công.

---

### Epic 5 — Giao phòng / Check-in
**Bảng liên quan:** `booking_rooms.status` (SP2100.Status), `booking_statuses` (SP1311), `room_statuses` (SP1313)

1. Chỉ thao tác được khi đứng ở Module Lễ Tân (Front Office).
2. Điều kiện được phép check-in:
   - `check_in_date = system_date` (ngày hệ thống).
   - `booking_rooms.status = 0` (chưa check-in).
   - Nếu tham số `IsCheckBookingStatusWhenCheckin = 1` → bắt buộc trạng thái booking phải thuộc {1, 27}; nếu `= 0` → bỏ qua check này.
   - Trạng thái phòng vật lý phải là "phòng sẵn sàng".

---

### Epic 6 — Nâng hạng phòng (Room Upgrade/Downgrade)
**Bảng liên quan:** `booking_rooms` (SP2100), `room_types` (SP1100)

1. Cho phép đổi loại/dạng phòng của booking từ loại đã đặt sang loại khác (nâng hoặc hạ hạng), chỉ áp dụng phòng ở trạng thái **đăng ký**.
2. Check AV của loại phòng mới trong giai đoạn ở:
   - `AllowOverRoomTypeRoomKind = 1` → cho lưu kèm cảnh báo âm phòng.
   - `= 0` → chặn lưu, báo hết phòng trống.
3. Lưu thành công → giữ lại mã loại phòng cũ vào cột "LP Khởi Tạo" (`booking_rooms.original_room_type` — tương ứng `SP2100.Pack4`).

---

### Epic 7 — Thông tin khách lưu trú (Guest Info)
**Bảng liên quan:** `guests` (SP2300), `booking_children` (SP2400)

1. Phương thức nhập liệu: nhập tay, import từ file Excel mẫu xuất ra từ hệ thống, hoặc scan Passport/CCCD qua máy scan chuyên dụng.
2. Chỉ cho nhập với khách ở trạng thái **đăng ký** hoặc **inhouse**.
3. Cho phép **kế thừa thông tin cũ** đã có trong hệ thống khi nhập tên/số căn cước trùng khách cũ — nếu chọn kế thừa thì lấy full thông tin từ `guests` (người lớn) / `booking_children` (trẻ em).

---

### Epic 8 — Gỡ số phòng (Unassign Room Number)
**Bảng liên quan:** `booking_rooms.room_number` (SP2100.Room)

1. Cập nhật `room_number = NULL` cho các phòng được chọn.

---

### Epic 9 — Hủy phòng / Hủy đăng ký (Cancel Room / Cancel Booking)
**Bảng liên quan:** `booking_rooms` (SP2100), `booking_room_guests` (SP2200), `guests` (SP2300), `booking_children` (SP2400), `booking_room_children` (SP2500), `cancel_reasons` (SP1334), log: `booking_room_cancel_logs` (SP8052), `booking_cancel_logs` (SP8053)

1. Hủy phòng được chọn → cascade cập nhật trạng thái **Hủy** (status = 3) trên:
   - `booking_rooms`
   - `booking_room_guests.status = 3`
   - `guests.guest_status = 3`
   - `booking_children.child_status = 3`
   - `booking_room_children.status = 3`
2. Ghi log lý do hủy vào bảng log tương ứng (`booking_room_cancel_logs` cho hủy 1 phòng, `booking_cancel_logs` cho hủy cả booking), tham chiếu `cancel_reasons`.

---

### Epic 10 — Xóa dịch vụ bổ sung (Bulk Delete Services)
**Bảng liên quan:** `booking_room_services` (SP2102)

1. Hiển thị danh sách dịch vụ bổ sung của các phòng được chọn để xóa hàng loạt.
2. Chỉ cho xóa với phòng **đăng ký / inhouse** và **chỉ những bill có ngày `>= system_date`** (chưa post/quá khứ thì không cho xóa).
3. Riêng Extra Bed (EB): khi xóa hết tất cả các ngày → reset `booking_rooms.extra_bed_qty = 0`, `booking_rooms.extra_bed_rate = 0`.

---

### Epic 11 — Khóa/Mở chuyển phòng (Do Not Move)
**Bảng liên quan:** `room_do_not_move_locks` (SP8022)

**Khóa:**
1. Khóa không cho đổi/chuyển số phòng đã gán (kể cả thao tác kéo-thả ngoài room map) khi phòng đang ở trạng thái Do Not Move.
2. Lưu: user khóa (`locked_by_user`).

**Mở khóa:**
3. Chỉ user đã khóa (`room_do_not_move_locks.locked_by_user`) mới được mở, **trừ khi** user hiện tại nằm trong rule `Booking_RuleUserUnLockDoNotMove` (được phép mở khóa của người khác).

---

### Epic 12 — Xuất Excel / In ấn
**Bảng liên quan:** `bookings`, `booking_rooms` (đọc, không ghi)

1. Xuất Excel: xuất toàn bộ thông tin booking ra file Excel.
2. In phiếu đăng ký khách: in mẫu xác nhận lưu trú cho phòng được chọn — lấy đúng mẫu in đã cấu hình theo khách sạn.
3. In hóa đơn tạm: in tạm tính cho phòng được chọn — cho tùy chọn in tất cả dịch vụ / chỉ tiền phòng / chỉ dịch vụ / tùy chọn dịch vụ cụ thể.

---

### Epic 13 — Chi tiết ăn sáng trẻ em
**Bảng liên quan:** `booking_children` (SP2400), `booking_room_children` (SP2500), `booking_child_breakfast_details` (SP2401)

1. Nhập số lượng em bé/trẻ em cho phòng → hiển thị các dòng chi tiết tương ứng số lượng đã nhập.
2. Em bé mặc định hiểu là **ăn sáng miễn phí**.
3. Thành tiền: lấy theo tham số `GiaAnSangTreEm`, có thể mở rộng xem chi tiết giá từng ngày.
4. Cờ "Ăn sáng" (`breakfast`): 1 = có ăn sáng, 0 = không.
5. Cờ "Miễn phí" (`is_free`): 1 = ăn sáng miễn phí (giá 0đ), 0 = tính phí theo cột thành tiền.
6. Cờ "Phụ phí" (`is_extra_charge`):
   - 1 → post thêm bill dịch vụ tự động mã `BD` (phụ thu ăn sáng trẻ em).
   - 0 → không phát sinh bill riêng, tiền ăn sáng trẻ trừ thẳng vào tiền phòng (giống người lớn).
7. Cờ "FIT/GIT" (`post_to_room`): 1 = FIT, bill phụ thu gửi về Folio của **phòng**; 0 = GIT, bill phụ thu gửi về Folio của **đăng ký (booking)**.

---

### Epic 14 — Thêm giường & phụ thu thêm giường
**Bảng liên quan:** `booking_room_services` (SP2102), `hotel_settings.extra_bed_rate` (SP1322.ExtraBedRate)

1. Nhập số lượng giường phụ; đơn giá mặc định lấy từ `hotel_settings.extra_bed_rate`, cho phép điều chỉnh giá chi tiết theo từng ngày.
2. Ngày dịch vụ `< system_date` → không cho sửa.
3. Cờ FIT/GIT (`is_room`): 1 = FIT (Folio của phòng), 0 = GIT (Folio của đăng ký).

---

### Epic 15 — Yêu cầu đặc biệt (Special Request)
**Bảng liên quan:** `special_requests` (SP1325), `booking_room_special_requests` (SP2107)

1. Danh sách yêu cầu lấy từ master `special_requests`.
2. Check chọn yêu cầu đặc biệt cho khách → insert vào `booking_room_special_requests`.
3. Không cho điều chỉnh với phòng đã check-out.
4. Hiển thị icon theo loại yêu cầu trên Room Map:
   - `honey_moon` → icon trái tim (khi phòng sắp đến hoặc đang inhouse).
   - `birthday` → icon bánh kem.
   - `baby_cot` → tính vào số lượng baby cot hiển thị trên màn hình Room AV.

---

### Epic 16 — Đặt cọc (Deposit)
**Bảng liên quan:** (thuộc module Thanh toán, ngoài phạm vi core nhưng liên kết trực tiếp với booking)

1. Khi tạo đặt cọc: **không hiển thị** hình thức thanh toán là "công nợ" và các hình thức thanh toán miễn phí trong danh sách chọn.

---

## 4. ĐỀ XUẤT CẤU TRÚC BẢNG MYSQL CHO CÁC BẢNG LÕI

> Đây là gợi ý field tối thiểu suy ra từ mô tả nghiệp vụ — bạn đối chiếu lại với các bảng đã có sẵn trong dự án mới trước khi migrate.

```sql
-- system_date (SP1500)
CREATE TABLE system_date (
  id INT PRIMARY KEY AUTO_INCREMENT,
  hotel_id INT NOT NULL,
  current_date DATE NOT NULL,
  updated_at DATETIME
);

-- bookings (SP2000)
CREATE TABLE bookings (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  booking_code VARCHAR(30) UNIQUE,          -- sinh tự động khi tạo thành công
  check_in_date DATE NOT NULL,
  check_out_date DATE NOT NULL,
  confirmation_date DATE,
  booking_status_id INT,                    -- FK booking_statuses
  company_id BIGINT NULL,                   -- FK companies
  market_segment_id INT NULL,               -- FK market_segments
  booking_source_id INT NULL,               -- FK booking_sources
  booker_id BIGINT NULL,
  created_module VARCHAR(30),               -- reservation, front_office...
  created_at DATETIME,
  updated_at DATETIME
);

-- booking_rooms (SP2100)
CREATE TABLE booking_rooms (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  booking_id BIGINT NOT NULL,               -- FK bookings
  room_id INT NULL,                         -- FK rooms (số phòng đã gán, null nếu chưa gán)
  room_type_id INT NOT NULL,
  original_room_type_id INT NULL,           -- lưu loại phòng khởi tạo trước khi nâng hạng (Pack4)
  check_in_date DATE NOT NULL,
  check_out_date DATE NOT NULL,
  check_in_time TIME,
  check_out_time TIME,
  rate DECIMAL(15,2),
  adults INT DEFAULT 0,
  extra_bed_qty INT DEFAULT 0,
  extra_bed_rate DECIMAL(15,2) DEFAULT 0,
  status TINYINT DEFAULT 0,                 -- 0 booked,... 3 cancelled
  is_do_not_move TINYINT DEFAULT 0,
  updated_at DATETIME
);

-- booking_room_guests (SP2200)
CREATE TABLE booking_room_guests (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  booking_room_id BIGINT NOT NULL,
  guest_id BIGINT NOT NULL,
  status TINYINT DEFAULT 0
);

-- guests (SP2300)
CREATE TABLE guests (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  full_name VARCHAR(150),
  id_number VARCHAR(50),
  passport_number VARCHAR(50),
  nationality_id INT,
  guest_status TINYINT DEFAULT 0
);

-- booking_children (SP2400)
CREATE TABLE booking_children (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  booking_id BIGINT NOT NULL,
  full_name VARCHAR(150),
  child_status TINYINT DEFAULT 0
);

-- booking_room_children (SP2500)
CREATE TABLE booking_room_children (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  booking_room_id BIGINT NOT NULL,
  booking_child_id BIGINT NOT NULL,
  status TINYINT DEFAULT 0
);

-- booking_child_breakfast_details (SP2401)
CREATE TABLE booking_child_breakfast_details (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  booking_room_child_id BIGINT NOT NULL,
  service_date DATE NOT NULL,
  breakfast TINYINT DEFAULT 0,       -- 1 có ăn sáng
  is_free TINYINT DEFAULT 1,         -- 1 miễn phí
  is_extra_charge TINYINT DEFAULT 0, -- 1 post bill BD
  post_to_room TINYINT DEFAULT 1,    -- 1 FIT (folio phòng) / 0 GIT (folio booking)
  amount DECIMAL(15,2) DEFAULT 0
);

-- booking_room_services (SP2102) — dùng chung cho dịch vụ set-up trước + extra bed
CREATE TABLE booking_room_services (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  booking_room_id BIGINT NOT NULL,
  service_id INT NOT NULL,           -- FK services (RM, EB, BD...)
  service_date DATE NOT NULL,
  quantity DECIMAL(10,2) DEFAULT 1,
  rate DECIMAL(15,2) DEFAULT 0,
  is_room TINYINT DEFAULT 1          -- 1 FIT / 0 GIT
);

-- booking_room_special_requests (SP2107)
CREATE TABLE booking_room_special_requests (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  booking_room_id BIGINT NOT NULL,
  special_request_id INT NOT NULL    -- FK special_requests
);

-- room_do_not_move_locks (SP8022)
CREATE TABLE room_do_not_move_locks (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  booking_room_id BIGINT NOT NULL,
  locked_by_user_id INT NOT NULL,
  locked_at DATETIME,
  unlocked_by_user_id INT NULL,
  unlocked_at DATETIME NULL
);

-- booking_room_cancel_logs (SP8052) / booking_cancel_logs (SP8053)
CREATE TABLE booking_room_cancel_logs (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  booking_room_id BIGINT NOT NULL,
  cancel_reason_id INT,
  cancelled_by_user_id INT,
  cancelled_at DATETIME
);

CREATE TABLE booking_cancel_logs (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  booking_id BIGINT NOT NULL,
  cancel_reason_id INT,
  cancelled_by_user_id INT,
  cancelled_at DATETIME
);
```

---

## 5. BACKLOG ĐỀ XUẤT ĐƯA VÀO ANTIGRAVITY CLI (checklist)

```
[ ] Epic 1: API + UI Tạo mới đặt phòng (bookings, booking_rooms)
[ ] Epic 2: API Cập nhật nhanh nhiều phòng (bulk update booking_rooms)
[ ] Epic 3: Service Tự động gán số phòng (room assignment algorithm)
[ ] Epic 4: API Dịch vụ bổ sung set-up trước (booking_room_services)
[ ] Epic 5: API Check-in / Giao phòng
[ ] Epic 6: API Nâng hạng phòng
[ ] Epic 7: API/UI Thông tin khách (nhập tay/import excel/scan passport)
[ ] Epic 8: API Gỡ số phòng
[ ] Epic 9: API Hủy phòng / Hủy đăng ký (cascade + log)
[ ] Epic 10: API Xóa dịch vụ bổ sung hàng loạt
[ ] Epic 11: API Khóa/Mở Do Not Move
[ ] Epic 12: Export Excel / In phiếu / In hóa đơn tạm
[ ] Epic 13: API Chi tiết ăn sáng trẻ em
[ ] Epic 14: API Thêm giường & phụ thu
[ ] Epic 15: API Special Request + icon Room Map
[ ] Epic 16: Rule ẩn hình thức thanh toán khi Đặt cọc
[ ] Nền tảng: bảng system_configs (key-value) cho toàn bộ tham số ở mục 2
[ ] Nền tảng: seed dữ liệu danh mục (room_statuses, booking_statuses, special_requests...)
```

---

## 6. LƯU Ý KHI MIGRATE MYSQL

- Toàn bộ so sánh ngày dùng chung 1 nguồn `system_date`, không dùng `NOW()`/`CURDATE()` của server để tránh lệch giờ khách sạn vs. giờ server.
- Các thao tác cascade (hủy phòng, nâng hạng, cập nhật ngày) nên bọc trong **transaction** vì ảnh hưởng nhiều bảng cùng lúc (`booking_rooms`, `booking_room_guests`, `guests`, `booking_children`, `booking_room_children`).
- Toàn bộ rule "chặn/AV âm phòng" nên tách thành 1 **service dùng chung** (`RoomAvailabilityChecker`) vì được tái sử dụng ở nhiều Epic: 1 (tạo mới), 2 (cập nhật), 6 (nâng hạng).
- Field `is_room` / `post_to_room` (FIT/GIT) là 1 khái niệm lặp lại ở nhiều bảng (Epic 13, 14) — nên chuẩn hóa tên field giống nhau xuyên suốt DB mới.
