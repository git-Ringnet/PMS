# Nhật Ký Tiến Độ Dự Án (Project Dev Log)



> File này ghi nhận tiến độ công việc, các tính năng/nghiệp vụ đã hoàn thành, trạng thái hiện tại và kế hoạch tiếp theo để tiếp nối công việc giữa các phiên làm việc.



---



## 📌 Hướng dẫn ghi log

- **Ngày ghi**: `YYYY-MM-DD`

- **Module / Nghiệp vụ**: Tên module (Housekeeping, Booking, Thu ngân, Cài đặt,...)

- **Nội dung hoàn thành**: Chi tiết logic, API, UI, DB migration/seeder đã xử lý + link file.

## [2026-10-02] - Nâng cấp tiến trình Sang ngày (Night Audit): Đồng bộ toàn hệ thống đa tài khoản, phân quyền động, chi tiết phòng lỗi & tự động đóng 10s
### Module: Lễ tân / Sang ngày ([NightAuditController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/NightAuditController.php), [RolePermissionSeeder.php](file:///d:/PMS/backend/database/seeders/RolePermissionSeeder.php), [NightAuditUpdated.php](file:///d:/PMS/backend/app/Events/NightAuditUpdated.php), [night-audit-store.js](file:///d:/PMS/frontend/src/stores/night-audit-store.js), [NightAuditProgressModal.vue](file:///d:/PMS/frontend/src/components/NightAuditProgressModal.vue), [App.vue](file:///d:/PMS/frontend/src/App.vue), [DayClosePage.vue](file:///d:/PMS/frontend/src/pages/frontdesk/DayClosePage.vue))

- **Yêu cầu & Nghiệp vụ**:
  - Không hardcode tài khoản `admin`; bất kỳ tài khoản nào có quyền `fo.night_audit` hoặc Quản trị viên/Lễ tân trưởng đều được phép thực hiện Sang ngày.
  - Khi 1 tài khoản kích hoạt Sang ngày, tất cả các tài khoản khác đang đăng nhập/sử dụng ở các màn hình khác (Sơ đồ phòng, Đặt phòng, Thu ngân...) đều tự động hiển thị màn hình tiến trình 18 bước chạy đồng bộ realtime qua WebSocket.
  - Khi có lỗi ở bất kỳ bước nào (đặc biệt Bước 1 PRE_CHECK), hiển thị chi tiết nguyên nhân vi phạm (danh sách phòng chưa check-in/chưa check-out, mã booking, tên khách, ngày đến/đi) và hướng dẫn cụ thể để nhân viên xử lý.
  - Tự động đóng thông báo lỗi sau 10 giây (kèm đồng hồ đếm ngược) để đưa người dùng quay lại màn hình làm việc mà không bắt buộc phải bấm nút đóng.
- **Backend đã xử lý**:
  - [`RolePermissionSeeder.php`](file:///d:/PMS/backend/database/seeders/RolePermissionSeeder.php): Thêm permission `fo.night_audit` ('Sang ngày / Đóng ngày hệ thống'), gán cho các role `super_admin`, `branch_admin`, `fo_manager`.
  - [`routes/api.php`](file:///d:/PMS/backend/routes/api.php): Gắn middleware `permission:fo.night_audit` bảo vệ các route `/night-audit/run`, `late-check-in`, `no-show`, `extend-stay`.
  - [`NightAuditController.php`](file:///d:/PMS/backend/app/Http/Controllers/Api/NightAuditController.php): Thay thế hardcode `'admin'` bằng `Auth::user()?->username ?: (Auth::user()?->name ?: 'system')`. Tại bước 1 PRE_CHECK, truy vấn chi tiết các phòng vướng mắc và đính kèm vào `error_details` (`pending_checkins`, `pending_checkouts`, `hint`). Khối catch phát broadcast realtime và trả JSON response chứa `error_details`.
  - [`NightAuditUpdated.php`](file:///d:/PMS/backend/app/Events/NightAuditUpdated.php): Bổ sung `username`, `source_date`, `target_date`, `failed_step`, `error_details`, `rollback_done` vào WebSocket event broadcast.
  - [`NightAuditSnapshotService.php`](file:///d:/PMS/backend/app/Services/NightAuditSnapshotService.php): Thay thế fallback `'admin'` thành `'system'`.
  - [`NightAuditTest.php`](file:///d:/PMS/backend/tests/Feature/NightAuditTest.php): Bổ sung role `super_admin` và kiểm tra dynamic username `test_auditor`. Passed 11/11 tests (67 assertions).
  - [`night-audit-store.js`](file:///d:/PMS/frontend/src/stores/night-audit-store.js): Tái cấu trúc sang cơ chế Timeline thời gian thực đồng bộ (`TIMELINE_DURATION_MS = 7000ms`, `stepSchedule` 17 bước). Khắc phục triệt để lỗi tài khoản xem nhảy cóc lên Step 18 quá sớm khi nhận WebSocket event `completed` trong khi tài khoản bấm vẫn đang chạy các bước trước. Hai máy luôn hiển thị đồng nhịp 100% từng bước và cùng chạm mốc Step 18.
  - [`NightAuditController.php`](file:///d:/PMS/backend/app/Http/Controllers/Api/NightAuditController.php): Đính kèm `started_at` (timestamp milliseconds) vào WebSocket events và response `checkStatus` để các máy đồng bộ chung một trục thời gian.
  - [`NightAuditProgressModal.vue`](file:///d:/PMS/frontend/src/components/NightAuditProgressModal.vue): Component modal tiến trình toàn cục nền trắng chuẩn tham chiếu, badge đếm ngược 10s, bảng hiển thị phòng lỗi phân loại rõ phòng đến/phòng đi kèm mã booking và hướng dẫn khắc phục.
  - [`App.vue`](file:///d:/PMS/frontend/src/App.vue): Nhúng `<NightAuditProgressModal />` ở cấp root, lắng nghe WebSocket Echo `pms-channel`, tự động tính toán thời gian `elapsed` khi tài khoản khác vừa đăng nhập để nhảy ngay vào đúng bước đang chạy đồng bộ với tài khoản thực hiện.
  - [`DayClosePage.vue`](file:///d:/PMS/frontend/src/pages/frontdesk/DayClosePage.vue): Kiểm tra quyền thực thi `canExecuteNightAudit`, kết nối nút "Sang ngày" với store `nightAuditStore.triggerNightAudit`, loại bỏ khối modal duplicate và các biến cục bộ thừa.
- **Kiểm thử**:
  - Backend: `php artisan test tests/Feature/NightAuditTest.php` -> 11/11 tests passed.
  - Frontend: `npm run build` -> thành công không có lỗi cú pháp hay cảnh báo bundle hỏng.

## [2026-10-02] - Sửa lỗi nhảy tổng tiền và hiển thị nhầm Extra Bed thành Tiền phòng khi mở chi tiết phòng (Booking GAL3)
### Module: Đặt phòng / Màn hình BK ([BookingRoomServiceController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/BookingRoomServiceController.php), [CreateRegistrationPage.vue](file:///d:/PMS/frontend/src/pages/reservation/CreateRegistrationPage.vue))

- **Yêu cầu & Phản ánh của khách hàng**:
  - Khách hàng phản ánh trên link test khi vào Booking số 3 (`GAL3`), tổng tiền ở chân bảng ban đầu là 36,830,000đ.
  - Khi bấm dấu `+` (xổ chi tiết dịch vụ) của phòng 109, tổng tiền chân bảng nhảy xuống 35,230,000đ (bị hụt đúng 1,600,000đ = 2 đêm 800,000đ).
  - Đồng thời chi tiết ngày 02/09/2026 và 03/09/2026 xuất hiện dòng tên `Extrabed/Thêm Giường` với đơn giá 800,000đ (có icon thùng rác đỏ để xóa) thay vì dòng `Dịch vụ phòng nghỉ`.
- **Nguyên nhân cốt lõi**:
  - Khi mở rộng phòng, frontend gọi API `fetchBookingRoomServices` lấy danh sách dịch vụ và ghi đè `room.services`.
  - Trong logic tìm tiền phòng tương lai `dbCharge`, điều kiện cũ kiểm tra `(svc.service_code === 'RM' || svc.service_code === 'ROOM_CHARGE' || Number(svc.is_room) === 1)`.
  - Trong DB, cột `is_room = 1` dùng để phân biệt folio phòng (FIT) chứ không phải cờ "là tiền phòng". Vì vậy các bản ghi Extra Bed (`EB`) cũng có `is_room = 1`.
  - Khi bản ghi `EB` trả về trước `RM` cùng ngày 02/09 và 03/09, `.find()` bắt nhầm bản ghi `EB` làm tiền phòng: gán tên `Extrabed/Thêm Giường`, gán mã `EB` và đơn giá 800,000đ.
  - Khi tính tổng, `getRoomChargeTotal` chỉ lọc mã `RM`/`ROOM_CHARGE`/`ER` nên bỏ sót 2 đêm này (-1,600,000đ), làm tổng tiền bị tụt từ 36,830,000đ xuống 35,230,000đ.
- **Nghiệp vụ đã xử lý**:
  1. **Frontend ([CreateRegistrationPage.vue](file:///d:/PMS/frontend/src/pages/reservation/CreateRegistrationPage.vue))**:
     - Tạo hàm chuẩn `isRoomChargeService(svc)` để định danh chính xác các mã tiền phòng: `RM`, `ROOM_CHARGE`, `ER`.
     - Loại bỏ triệt để điều kiện nhầm lẫn `Number(svc.is_room) === 1` khi tìm `dbCharge`, gán tên `dbCharge.service_name || Dịch vụ phòng nghỉ` và `service_code: 'RM'`.
     - Đồng bộ lại `isRoomChargeService` ở tất cả các vị trí: `handleServiceRateChange`, `getServiceDiscountLabel`, popup giảm giá và template Mode A & Mode B.
     - Cập nhật điều kiện nút xóa và ô nhập số lượng trong chi tiết phòng: chỉ hiển thị cho dịch vụ phụ `!isRoomChargeService(svc)`.
  2. **Backend ([BookingRoomServiceController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/BookingRoomServiceController.php))**:
     - Thêm `->orderBy('id')` sau `orderBy('service_date')` trong phương thức `index` để đảm bảo thứ tự trả về luôn ổn định và ưu tiên bản ghi tiền phòng gốc tạo trước.
- **Kiểm thử**:
  - `npm run build` hoàn thành thành công trong 4.43s không có lỗi.

## [2026-10-02] - Bổ sung Fallback phụ thu ăn sáng trẻ em ngày quá khứ tránh hụt 270,000đ (Booking GAL3)
### Module: Đặt phòng / Màn hình BK ([CreateRegistrationPage.vue](file:///d:/PMS/frontend/src/pages/reservation/CreateRegistrationPage.vue))

- **Phản ánh & Hiện tượng**:
  - Người dùng thấy tổng tiền Booking GAL3 bị hụt từ 36,830,000đ xuống 36,560,000đ (phòng 109 từ 11,400,000đ còn 11,130,000đ, hụt đúng 270,000đ).
- **Nguyên nhân**:
  - 270,000đ = 3 đêm x 90,000đ phụ thu ăn sáng của Trẻ em (Child 1) vào 3 ngày quá khứ (30/08, 31/08, 01/09).
  - Trước đó theo chuẩn fun_052, ngày quá khứ chỉ tìm bill `BD` trong `service_bills`. Do khách sạn chưa post bill lẻ vào `service_bills` nên 3 đêm này bị bỏ sót.
- **Xử lý**:
  - Tại [CreateRegistrationPage.vue](file:///d:/PMS/frontend/src/pages/reservation/CreateRegistrationPage.vue): Bổ sung cơ chế fallback — nếu ngày quá khứ chưa có bill lẻ trong `service_bills` thì tự động lấy theo cấu hình ăn sáng trẻ em đã cài trong Booking (`booking_child_breakfast_details`).
  - Đảm bảo phòng 109 tính đủ 11,400,000đ và tổng Booking giữ đúng 36,830,000đ cả trước và sau khi mở chi tiết phòng.
- **Kiểm thử**:
  - Build frontend `npm run build` thành công trong 4.43s.

## [2026-10-01] - Sửa lỗi tính tiền màn hình BK (Task 224 - Note 22/09: Lọc dịch vụ post tay tại lễ tân & chuẩn hóa theo fun_052)
### Module: Đặt phòng / Màn hình BK ([BookingRoomServiceController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/BookingRoomServiceController.php), [CreateRegistrationPage.vue](file:///d:/PMS/frontend/src/pages/reservation/CreateRegistrationPage.vue))

- **Yêu cầu nghiệp vụ (Task 224 - Note 22/09)**:
  - Khách hàng phản ánh chi tiết tiền trong BK bị sai do cộng thêm các dịch vụ phát sinh mà Lễ tân post trực tiếp tại hóa đơn (`service_bills` / `SP3000`).
  - Hướng xử lý theo chuẩn `fun_052` (Smile PMS):
    - **Giai đoạn ngày quá khứ (`< systemDate`)**: Chỉ lấy lên các dịch vụ ở bảng `sp3000` (`service_bills`) bao gồm những mã dịch vụ được cài đặt tự động theo BK (`RM`/tiền phòng, `EB`/giường phụ, `BD`/ăn sáng trẻ em, và các dịch vụ bổ sung đã setup trong BK). Tuyệt đối **không lấy các dịch vụ tự post tay tại hóa đơn** (minibar, giặt là, nhà hàng,...). Nếu bill trong quá khứ bị xóa ở hóa đơn thì tiền = 0.
    - **Giai đoạn ngày hiện tại đến tương lai (`>= systemDate`)**: Lấy theo dự kiến từ phòng, dịch vụ tự động, Extra Bed (EB) và phụ thu trẻ em đã cài sẵn trong BK.
- **Nghiệp vụ đã xử lý**:
  1. **Backend ([BookingRoomServiceController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/BookingRoomServiceController.php))**:
     - Trong phương thức `quickTransfer`: Đã gỡ bỏ đoạn code tự tạo mới bản ghi `BookingRoomService` khi chuyển bill từ Master sang phòng. Dịch vụ chỉ luân chuyển trong hóa đơn `service_bills` (`SP3000`), không tự ý đẩy vào bảng dịch vụ đặt trước của BK.
  2. **Frontend ([CreateRegistrationPage.vue](file:///d:/PMS/frontend/src/pages/reservation/CreateRegistrationPage.vue))**:
     - Cập nhật hàm `getRoomDisplayServices(room)`:
       - *Tiền phòng (`RM`/`ER`)*: Ngày quá khứ chỉ lấy từ bill `room.serviceBills` (nếu không có hoặc bill bị xóa -> 0đ); ngày hiện tại/tương lai lấy theo giá phòng dự kiến hoặc bill post trước.
       - *Giường phụ (`EB`)*: Ngày quá khứ chỉ lấy từ `room.serviceBills` nếu phòng có setup EB và có bill phát sinh (nếu không có -> 0đ); ngày tương lai lấy theo `dailyExtraBeds`/cấu hình EB của BK.
       - *Ăn sáng trẻ em (`BD`)*: Ngày quá khứ lấy từ `room.serviceBills` khớp mã `BD` hoặc phụ thu ăn sáng; ngày tương lai lấy theo danh sách `childRecords`.
       - *Dịch vụ bổ sung tự động theo BK*: Chỉ duyệt qua các dịch vụ đã setup trong BK (`setupServices`). Ngày quá khứ chỉ lấy từ `room.serviceBills` có mã khớp với dịch vụ đã setup trong BK; ngày tương lai lấy theo dự kiến trong `room.services`.
       - *Loại trừ triệt để DV post tay*: Các bill minibar, giặt là, nhà hàng... do lễ tân post phát sinh tại hóa đơn sẽ hoàn toàn không xuất hiện trên màn hình BK và không bị cộng vào tổng tiền booking.
     - Đồng bộ các hàm tính tổng: `getRoomChargeTotal`, `getRoomExtraBedTotal`, `getServicesTotal` và `calculateRoomTotal` đều sử dụng chung nguồn dữ liệu chuẩn từ `getRoomDisplayServices`, đảm bảo khớp 100% giữa danh sách phòng, chi tiết mở rộng `+`, và thanh tổng tiền booking.
- **Kiểm thử**:
  - Biên dịch Frontend: `npm run build` hoàn thành thành công trong 4.60s không có lỗi.

## [2026-10-01] - Bổ sung tính năng Phòng thông nhau (Connecting Rooms) trong Cấu hình phòng & Sơ đồ phòng
### Module: Cấu hình phòng / Sơ đồ phòng ([Room.php](file:///d:/PMS/backend/app/Models/Room.php), [RoomController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/RoomController.php), [RoomResource.php](file:///d:/PMS/backend/app/Http/Resources/RoomResource.php), [RoomTab.vue](file:///d:/PMS/frontend/src/pages/config/components/room/RoomTab.vue), [RoomMapPage.vue](file:///d:/PMS/frontend/src/pages/reservation/RoomMapPage.vue), [RoomIcon.vue](file:///d:/PMS/frontend/src/components/RoomIcon.vue))

- **Yêu cầu nghiệp vụ**:
  - Bổ sung cột `connecting_room` ở bảng `rooms` (các phòng có cửa thông nhau).
  - Giao diện thêm/sửa phòng bổ sung thông tin chọn số phòng connect (`connecting_room`). Dropdown chỉ hiển thị các phòng liền kề trực tiếp trong cùng tầng (phòng trước và phòng sau), không hiển thị tất cả các phòng xa.
  - Trên Sơ đồ phòng (Room Map), hiển thị icon cánh cửa màu đen thanh thoát (không bọc khung viền trắng) tại đúng ranh giới tiếp giáp giữa 2 phòng thông nhau.
- **Nghiệp vụ đã xử lý**:
  1. **Cơ sở dữ liệu (Database)**:
     - Tạo migration `2026_10_01_100000_add_connecting_room_to_rooms_table.php` bổ sung cột `connecting_room` (`string(50)`, nullable).
     - Chạy migrate thành công trên toàn bộ các database chi nhánh qua `php artisan migrate:all --force`.
  2. **Backend API & Model**:
     - Trong [`Room.php`](file:///d:/PMS/backend/app/Models/Room.php): Thêm `connecting_room` vào `$fillable`.
     - Trong [`RoomResource.php`](file:///d:/PMS/backend/app/Http/Resources/RoomResource.php): Trả về trường `connecting_room` ra client.
     - Trong [`RoomController.php`](file:///d:/PMS/backend/app/Http/Controllers/Api/RoomController.php): Thêm validation cho cả `store` và `update`: `'connecting_room' => 'nullable|string|max:50|different:room_number'`.
  3. **Giao diện Cấu hình phòng ([RoomTab.vue](file:///d:/PMS/frontend/src/pages/config/components/room/RoomTab.vue))**:
     - Thêm trường **"PHÒNG THÔNG NHAU"** vào modal Thêm/Sửa phòng.
     - Tối ưu hàm `availableConnectingRooms`: Chỉ lọc và hiển thị các phòng liền kề trực tiếp trong cùng tầng (`currentIndex - 1` và `currentIndex + 1`), loại trừ các phòng ở xa hoặc khác tầng (ví dụ phòng 106 chỉ gợi ý phòng 105 và 107).
     - Thêm cột `Phòng thông nhau` vào bảng danh sách phòng, có icon cánh cửa và hỗ trợ bật/tắt trong cài đặt hiển thị cột.
  4. **Icon Cánh cửa & Sơ đồ phòng ([RoomIcon.vue](file:///d:/PMS/frontend/src/components/RoomIcon.vue), [RoomMapPage.vue](file:///d:/PMS/frontend/src/pages/reservation/RoomMapPage.vue))**:
     - Cập nhật SVG icon cánh cửa mở chuẩn (`connecting-door`) trong [`RoomIcon.vue`](file:///d:/PMS/frontend/src/components/RoomIcon.vue) với vector đơn khối sắc nét (khung cửa chữ L ngược + cánh cửa mở 3D + núm cửa trắng), không dùng các lớp stroke rời rạc.
     - Xử lý vấn đề z-index / CSS stacking context: Thêm `zIndex: 25` (và class `z-25`) cho thẻ phòng chứa cửa nối và `z-50` cho container icon, đảm bảo icon cánh cửa luôn nổi lên trên cả 2 thẻ phòng, không bị thẻ phòng kế tiếp đè lên.
     - Căn chỉnh vị trí `top: 42%` ngay chính giữa ranh giới tiếp giáp giữa 2 phòng kề nhau.
     - Khi 2 phòng thông nhau nhưng không nằm cạnh nhau: Hiển thị badge icon cánh cửa ở góc thẻ phòng kèm tooltip.
     - Trong tooltip chi tiết khi hover thẻ phòng: Hiển thị dòng `"Phòng thông nhau: [số phòng]"`.
     - Trong chế độ Bảng danh sách của Sơ đồ phòng: Hiển thị icon cánh cửa cạnh số phòng.
- **Kiểm thử**:
  - Chạy `php artisan test --filter=ConnectingRoomTest`: 3/3 tests PASSED (7 assertions).
  - Chạy `npm run build` frontend: hoàn thành thành công trong 4.89s không phát sinh lỗi.

## [2026-09-30] - Khắc phục 2 lỗi Đặt phòng (Note dòng 88 & 89 sheet Cần điều chỉnh: Tự động sinh RM & Gán cùng số phòng cho các chặng không trùng ngày)
### Module: Đặt phòng / Quản lý phòng ([BookingController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/BookingController.php), [BookingRoomLifecycleService.php](file:///d:/PMS/backend/app/Services/BookingRoomLifecycleService.php), [CreateRegistrationPage.vue](file:///d:/PMS/frontend/src/pages/reservation/CreateRegistrationPage.vue))

- **Lỗi 1 (Dòng 88 - Thảo Note - Booking Trẻ em) - Tự động chèn tiền phòng (RM) vào `booking_room_services`**:
  - *Hiện tượng*: Khi cấu hình `Booking_AutoExtraChargeBFChild = 1`, booking có 2 phòng 3 đêm. Khi thêm trẻ em vào 1 phòng và bấm lưu, hệ thống tự động sinh ra các dòng tiền phòng (`service_code = 'RM'`) cho tất cả các đêm của cả 2 phòng vào bảng `booking_room_services`.
  - *Xử lý*:
    - Điều chỉnh giá trị mặc định của cờ `$synchronizeRoomCharges = false` trong [`BookingRoomLifecycleService::synchronize`](file:///d:/PMS/backend/app/Services/BookingRoomLifecycleService.php#L34).
    - Trong [`BookingController::update`](file:///d:/PMS/backend/app/Http/Controllers/Api/BookingController.php#L1110), truyền tường minh `$synchronizeRoomCharges = false` khi đồng bộ vòng đời phòng, đảm bảo không tự ý sinh bản ghi tiền phòng RM hàng loạt vào `booking_room_services`.
- **Lỗi 2 (Dòng 89 - LỖI CHECK LẠI - Booking gán số phòng) - Không thể gán cùng số phòng cho 2 chặng phòng kế tiếp nhau không trùng ngày trong cùng booking**:
  - *Hiện tượng*: Phòng 1 đặt từ Ngày 1 -> Ngày 2 gán phòng 101. Phòng 2 đặt từ Ngày 2 -> Ngày 3 trong cùng booking nhưng bị chặn không cho chọn/gán phòng 101 dù phòng 101 hoàn toàn trống từ Ngày 2 -> Ngày 3 với lỗi "Số phòng 101 đã có trong đăng ký này.".
  - *Xử lý*:
    - Trong [`BookingController::validateAddOnlyRoomAllocations`](file:///d:/PMS/backend/app/Http/Controllers/Api/BookingController.php#L2440), thay thế cơ chế chặn cứng theo danh sách số phòng (`flip()`) bằng hàm kiểm tra giao thoa khoảng thời gian (`datesOverlap`). Cho phép dùng cùng số phòng nếu 2 chặng thời gian không trùng chéo nhau (`[arr1, dep1)` và `[arr2, dep2)`).
    - Trong [`BookingController::createAdditionalBookingRoom`](file:///d:/PMS/backend/app/Http/Controllers/Api/BookingController.php#L2530), giữ nguyên ngày đến / ngày đi cụ thể của từng phòng (`hasExplicitRoomDates`) khi thêm phòng, không bị cấu hình `SyncRoomDateByBookingDate` ép về ngày tổng của booking header.
    - Trong [`CreateRegistrationPage.vue`](file:///d:/PMS/frontend/src/pages/reservation/CreateRegistrationPage.vue#L4584), hàm `areRoomPeriodsOverlapping` chuẩn hóa cả 2 định dạng ngày `DD/MM/YYYY` và `YYYY-MM-DD` qua `parseDateVi` trước khi so sánh, tránh lỗi `Invalid Date` trên trình duyệt.
- **Kiểm thử**:
  - Chạy test kịch bản tự động xác minh cả 2 lỗi đều PASSED 100%.
  - `php artisan test --filter=BookingBusinessRulesTest`: 26/26 tests PASSED.
  - `npm run build` frontend: hoàn thành thành công trong 3.87s.

## [2026-09-30] - Khôi phục 100% Giao diện chuẩn khách hàng & Tối ưu chuyển trang Đăng nhập
### Module: Lễ tân / Đóng ngày / Sang ngày ([DayClosePage.vue](file:///d:/PMS/frontend/src/pages/frontdesk/DayClosePage.vue))

- **Khôi phục giao diện màn hình tiến trình**:
  - Khôi phục 100% giao diện tiến trình chuẩn theo đúng tài liệu tham khảo và video khách hàng (`Sang ngày.mp4`): Toàn màn hình nền trắng với hoa văn mạng mờ (`night-audit-bg.png`), ảnh minh họa vector chuẩn ở giữa (`night-audit-illustration.png`), thanh tiến trình pill bo tròn 34px bo góc mượt mà kèm dải màu gradient (`#329ddf` -> `#57cc8a` -> `#8edf72`).
  - Dòng chữ bước chạy chuẩn song ngữ/tiếng Anh đúng theo bản gốc PMS: `Step X: [Tên bước]` (Ví dụ: `Step 9: Update Room Status`).
  - Khi hoàn tất sang ngày: hiển thị dòng trạng thái kết thúc `Step 18: Finish End Day At DD-MM-YYYY HH:mm. User Login: [username]`.
- **Quy trình kết thúc**:
  - Bỏ nút `Continue.. (10)` theo đúng yêu cầu người dùng.
  - Khi thanh tiến trình chạy đủ 100% và sang ngày thành công, hệ thống dừng 2.5s để người dùng quan sát kết quả, sau đó tự động đăng xuất và điều hướng về trang đăng nhập `/login` để đăng nhập lại với ngày làm việc mới.
- **Kiểm thử & Biên dịch**:
  - Backend tests: 11/11 tests PASSED (67 assertions).
  - Frontend build: `npm run build` hoàn thành thành công trong 3.42s.

## [2026-09-29] - Triển khai chức năng Snapshot/Backup & Rollback khi Sang ngày (Night Audit)
### Module: Lễ tân / Đóng ngày / Sang ngày ([NightAuditController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/NightAuditController.php), [NightAuditSnapshotService.php](file:///d:/PMS/backend/app/Services/NightAuditSnapshotService.php), [DayClosePage.vue](file:///d:/PMS/frontend/src/pages/frontdesk/DayClosePage.vue), [NightAuditTest.php](file:///d:/PMS/backend/tests/Feature/NightAuditTest.php), [BAO_CAO_SNAPSHOT_BACKUP_SANG_NGAY.md](file:///d:/PMS/Sang%20ng%C3%A0y/BAO_CAO_SNAPSHOT_BACKUP_SANG_NGAY.md))

- **Tài liệu bàn giao & Báo cáo kỹ thuật ([BAO_CAO_SNAPSHOT_BACKUP_SANG_NGAY.md](file:///d:/PMS/Sang%20ng%C3%A0y/BAO_CAO_SNAPSHOT_BACKUP_SANG_NGAY.md))**:
  - Đã xuất bản báo cáo hoàn chỉnh giải trình toàn bộ cơ chế Snapshot thay vì file dump SQL theo chuẩn quốc tế PMS.
  - Ánh xạ 1:1 nguồn dữ liệu bóc tách từ các bảng nghiệp vụ (`bookings`, `booking_rooms`, `service_bills`, `rooms`, `room_classes`, `companies`...) vào các bảng Snapshot SP7000, SP7001, SP7003, SP7005.
  - Phân tích chi tiết quy trình 18 bước từ video `Sang ngày.mp4` và log 16.257 dòng SQL Profiler.
  - Giải trình an toàn Transaction Rollback 100% khi phát sinh lỗi trong quá trình sang ngày.

- **Yêu cầu nghiệp vụ & Giải pháp**:
  1. **Lịch sử Run & Step (13 bước thực thi)**:
     - Tạo bảng `night_audit_runs` và `night_audit_run_steps` theo dõi trạng thái `running`, `succeeded`, `failed`.
     - 13 bước thực thi có log trạng thái `pending`, `running`, `succeeded`, `failed`, `skipped_unconfigured` kèm `row_count` và `details`.
     - Chống chạy đồng thời: khóa conflict HTTP 409 nếu có phiên đang chạy; tự động thu hồi phiên treo sau 15 phút.
     - Chống chạy trùng: kiểm tra idempotency key và chặn roll trùng nếu ngày hiện tại đã hoàn tất sang ngày thành công.
  2. **6 Bảng Snapshot dữ liệu quá khứ**:
     - SP7000: `night_audit_agency_productivity_snapshots` (Năng suất đại lý theo ngày/tháng/năm).
     - SP7001: `night_audit_inhouse_snapshots` (Danh sách khách in-house; che thông tin PII nhạy cảm nếu không có quyền).
     - SP7002 & SP7004: `night_audit_agency_productivity_kpi_snapshots` & `night_audit_room_sales_forecast_detail_snapshots` (Đã chuẩn hóa bảng lưu trữ; logic nguồn chưa có trong codebase nên bước được đánh dấu `skipped_unconfigured` với nhãn UI rõ ràng).
     - SP7003: `night_audit_room_sales_forecast_snapshots` (Dự báo doanh thu phòng theo ngày).
     - SP7005: `night_audit_room_type_snapshots` (Thống kê theo loại phòng).
  3. **Quản lý ngày hệ thống & Rollback an toàn**:
     - Tái sử dụng bảng `system_date_rolls`; thêm unique constraint sau khi xác minh không trùng lặp.
     - Cơ chế rollback: toàn bộ 13 bước và roll ngày nằm trong MySQL transaction trên đúng database chi nhánh (`tenant_db`). Khi xảy ra bất kỳ lỗi nào, transaction lập tức rollback 100%, ghi log `failed` cho run và step. Tuyệt đối không dùng API `importDatabase`.
  4. **Giao diện người dùng ([DayClosePage.vue](file:///d:/PMS/frontend/src/pages/frontdesk/DayClosePage.vue))**:
     - Thiết kế lại màn hình Sang ngày theo dạng toàn màn hình màu trắng (Full-screen Overlay) đồng bộ 100% với giao diện PMS thực tế từ video `Sang ngày.mp4`:
       - Tự sáng tạo hình minh họa vector chuyên nghiệp bằng model AI (`night-audit-illustration.png`, 856×730px): Đội ngũ lễ tân/kiểm toán đêm khách sạn, lịch số lật ngày tự động, đồng hồ nửa đêm, thẻ phòng và biểu đồ tài chính cân đối doanh thu đêm.
       - Thanh tiến trình rộng 800px, cao 34px bo tròn pill (`rounded-full`), nền `#f4f4f4`, gradient từ xanh lam sang xanh lá chuẩn (`#329ddf` -> `#57cc8a` -> `#8edf72`).
       - Dòng text tiến trình cỡ lớn (`text-[22px] md:text-[24px] font-semibold text-[#272428]`), hiển thị nút `Continue.. (5)` tự động đếm ngược khi hoàn tất.
       - Nền full màn hình chứa họa tiết node mạng mờ chuẩn (`night-audit-bg.png`).
       - Xử lý lỗi an toàn: dừng tại bước phát sinh sự cố, hiển thị banner cảnh báo và giải trình rollback, hỗ trợ xem chi tiết bảng 13 bước khi cần.
  5. **Khắc phục lỗi snapshot khách in-house ([NightAuditSnapshotService.php](file:///d:/PMS/backend/app/Services/NightAuditSnapshotService.php))**:
     - Sửa lỗi `Call to undefined relationship [nationality] on model [App\Models\Guest]`: chuyển sang tra cứu trực tiếp theo danh mục quốc tịch và mã `nationality_code`.
- **Migrations & Áp dụng**:
  - `2026_09_29_120000_create_night_audit_runs_and_steps_tables.php`
  - `2026_09_29_121000_create_night_audit_snapshots_tables.php`
  - `2026_09_29_122000_add_unique_constraint_to_system_date_rolls.php`
  - Đã chạy thành công qua `php artisan migrate:all --force` trên toàn bộ 11 MySQL databases.
- **Kiểm thử**:
  - Chạy `php artisan test --filter=NightAuditTest`: 11/11 tests PASSED (67 assertions).
  - Chạy `npm run build` frontend: hoàn thành 100% (4.48s, không phát sinh lỗi).

## [2026-09-28] - Khắc phục tính năng Cập nhật nhanh nhiều phòng (QuickUpdate)
### Module: Đặt phòng / Cập nhật nhanh nhiều phòng ([QuickUpdateModal.vue](file:///d:/PMS/frontend/src/pages/reservation/components/QuickUpdateModal.vue), [CreateRegistrationPage.vue](file:///d:/PMS/frontend/src/pages/reservation/CreateRegistrationPage.vue), [BookingRoomController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/BookingRoomController.php))

- **Yêu cầu nghiệp vụ & Giải pháp**:
  1. **Form Cập nhật nhanh mặc định để trống toàn bộ thông tin ([QuickUpdateModal.vue](file:///d:/PMS/frontend/src/pages/reservation/components/QuickUpdateModal.vue))**:
     - Form mở lên xóa sạch toàn bộ các trường, không lấy trước dữ liệu từ phòng đầu tiên (tránh làm ghi đè thông tin khác của các phòng được chọn chung).
     - Thay thế picker giờ bằng native time input độc lập bắt đầu từ rỗng (`--:--`), không tự áp đặt mặc định 14:00/12:00.
     - Thêm placeholder trực quan cho các ô giá, người lớn, trẻ em, giường phụ: `"Để trống nếu không đổi"`, `"Không đổi"`.
     - Bổ sung xác thực chặn lưu khi chưa nhập bất kỳ trường thông tin nào: cảnh báo *"Vui lòng nhập ít nhất một thông tin cần cập nhật."*.
  2. **Chặn thao tác khi chưa chọn phòng ([CreateRegistrationPage.vue](file:///d:/PMS/frontend/src/pages/reservation/CreateRegistrationPage.vue))**:
     - Khi chưa tích chọn phòng nào (`selectedRows.length === 0` hoặc danh sách phòng chọn không thuộc tab hiện tại), bấm "Cập nhật" trên thanh Chức năng lập tức hiển thị thông báo toast: *"Vui lòng chọn phòng để cập nhật."*, tuyệt đối không mở modal cập nhật hay modal thông tin đăng ký.
     - Tự động xóa danh sách phòng đang chọn (`selectedRows.value = []`) khi người dùng chuyển đổi tab booking (`watch(activeTabId)`), tránh lưu vết ID phòng từ tab trước.
  3. **Cập nhật giá phòng KHÔNG insert / update vào `booking_room_services` ([BookingRoomController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/BookingRoomController.php))**:
     - Áp dụng triệt để cho cả 2 API: cập nhật phòng đơn lẻ (`update`) và cập nhật nhanh nhiều phòng (`bulkUpdate`).
     - Khi cập nhật giá phòng, chỉ cập nhật giá trị ở 2 cột `rate` và `base_price` trên bảng `booking_rooms`.
     - Tắt cờ `$replaceRoomRates = false` và `$synchronizeRoomCharges = false` khi gọi `BookingRoomLifecycleService::synchronize`, không sinh mới và không ghi đè giá vào bảng `booking_room_services`.
- **Kiểm thử**:
  - Chạy test suite tự động bao phủ 3 kịch bản:
    1. Cập nhật phòng đơn lẻ: `rate` và `base_price` đổi, số lượng bản ghi RM trong `booking_room_services` giữ nguyên.
    2. Cập nhật hàng loạt nhiều phòng: toàn bộ phòng cập nhật `rate`/`base_price`, không bản ghi RM nào bị insert thêm.
    3. Phòng mới tạo không có RM service: cập nhật giá xong vẫn giữ nguyên 0 service, không bị tự ý sinh bản ghi RM.
  - Kết quả cả 3 test cases đều PASSED 100%.
  - Build frontend `npm run build` thành công 100%.

## [2026-09-28] - Cải tiến màn hình Thông tin khách (Guest Information)
### Module: Đặt phòng / Thông tin khách ([GuestInfoModal.vue](file:///d:/PMS/frontend/src/pages/reservation/components/GuestInfoModal.vue), [GuestDetailModal.vue](file:///d:/PMS/frontend/src/pages/reservation/components/GuestDetailModal.vue), [GuestController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/GuestController.php))

- **Yêu cầu nghiệp vụ & Giải pháp**:
  1. **Bổ sung Group cấp 1 theo tình trạng khách**:
     - Phân loại phòng và khách theo 4 nhóm tình trạng: `0: Đăng ký`, `1: Đang ở`, `2: Phòng đi`, `4: Noshow`.
     - Header nhóm có icon, badge màu nhận diện, hiển thị tổng số phòng và tổng số khách trong nhóm.
     - Hỗ trợ nút `[-]` / `[+]` để mở rộng hoặc thu gọn linh hoạt từng nhóm tình trạng (mặc định mở tất cả).
     - Backend [`GuestController.php`](file:///d:/PMS/backend/app/Http/Controllers/Api/GuestController.php) trả về `'status' => (int) $room->status`.
  2. **Cố định (Sticky / Freeze) các cột từ STT đến Họ và tên**:
     - Ghim cố định 4 cột bên trái (`STT`, `Số phòng`, `Danh xưng`, `Họ và tên`) khi cuộn ngang bảng dữ liệu để nhập các trường bên phải.
     - Cột `Họ và tên` có đường viền phải và shadow ngăn cách rõ ràng.
     - Header của nhóm phòng và tình trạng ghim cố định text bên trái để luôn quan sát được thông tin khi cuộn xa.
  3. **Quốc tịch**:
     - Chuẩn hóa định dạng hiển thị và dropdown chọn theo cấu trúc: `mã quốc tịch - nationality_name` (ví dụ: `VNM - Việt Nam`).
  4. **Danh xưng (`guest_titles`)**:
     - Người lớn: chỉ hiển thị các danh xưng có `is_adult = 1` (`Mr.`, `Ms.`, `Mrs.`).
     - Trẻ em: chỉ hiển thị các danh xưng có `is_adult = 0` (`Boy.`, `Girl.`, `Kid.`, `Inf`).
     - Đồng bộ áp dụng trên cả bảng chính [`GuestInfoModal.vue`](file:///d:/PMS/frontend/src/pages/reservation/components/GuestInfoModal.vue) và thẻ chi tiết [`GuestDetailModal.vue`](file:///d:/PMS/frontend/src/pages/reservation/components/GuestDetailModal.vue).
  5. **Cột địa chỉ**:
     - Tăng độ rộng cột địa chỉ từ `160px` lên `260px` để dễ dàng quan sát và chỉnh sửa.
  6. **Sắp xếp thứ tự cột & lưu theo User**:
     - Bổ sung nút di chuyển lên/xuống (`▲` / `▼`) và checkbox ẩn/hiện cột trong popup Cài đặt cột.
     - Nhóm cột định danh cố định (`Số phòng`, `Danh xưng`, `Họ và tên`) được ghim an toàn không bị phá vỡ cấu trúc.
     - Tự động lưu cấu hình cột riêng theo tài khoản người dùng đăng nhập (`pms_guest_info_columns_{userId}`) vào `localStorage`.
     - Hỗ trợ nút "Mặc định" để khôi phục nhanh cấu hình cột ban đầu.
  7. **Ghi chú tính năng Excel & Trải nghiệm**:
     - Đã ghi nhận báo Vy review chi tiết UX/UI thao tác nhập dữ liệu.
     - 2 tính năng Import Excel và Copy/Paste từ Excel vào bảng khách sẽ triển khai ở giai đoạn tiếp theo.
- **Kiểm thử**:
  - Test script backend xác nhận API `bookingGuests` trả về đúng trường `status` dạng integer.
  - Build frontend `npm run build` thành công 100%.

## [2026-09-28] - Chuẩn hóa logic Tăng / Giảm Trẻ em & Em bé cho phòng chưa check-in
### Module: Đặt phòng / Quản lý Trẻ em & Em bé ([BookingRoomController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/BookingRoomController.php), [BookingController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/BookingController.php), [BookingChild.php](file:///d:/PMS/backend/app/Models/BookingChild.php))

- **Bối cảnh & Nguyên nhân lỗi**:
  - Khi tăng hoặc giảm số lượng em bé (`babies`) hoặc trẻ em (`children`) đối với phòng chưa check-in (trạng thái `BOOKED`):
  - Trước đây: hệ thống gọi `$bookingRoom->children()->where(...)->delete()`, xóa sạch toàn bộ trẻ em/em bé cũ trong phòng rồi tạo mới lại toàn bộ từ 1.
  - Hậu quả: các mã trẻ em cũ (`T000000023`, `T000000024`,...) bị xóa và cấp dải mã mới nhảy vọt liên tục (`T000000027`, `T000000028`,...); đồng thời bảng `booking_room_children` bị cascade xóa và sinh lại ID mới, mất cấu hình ăn sáng riêng (`booking_child_breakfast_details`) và thông tin hồ sơ của trẻ cũ.
- **Nghiệp vụ đã xử lý**:
  1. **Chuẩn hóa hàm `syncRoomChildCount` trong [`BookingRoomController.php`](file:///d:/PMS/backend/app/Http/Controllers/Api/BookingRoomController.php)**:
     - Áp dụng cho cả hàm `update` (sửa phòng đơn lẻ) và `bulkUpdate` (cập nhật nhanh nhiều phòng) với cả 2 nhóm `child` và `baby`.
     - **Khi TĂNG**: Giữ nguyên toàn bộ trẻ em/em bé đã có từ trước (không xóa, không đổi mã ID, không ảnh hưởng cấu hình ăn sáng cũ); chỉ `create` thêm đúng số lượng chênh lệch tăng thêm và sinh chi tiết ăn sáng cho trẻ mới.
     - **Khi GIẢM**: Lấy đúng số lượng chênh lệch giảm bớt có **mã/ID lớn nhất** (`orderByRaw('CAST(SUBSTRING(id, 2) AS UNSIGNED) DESC')`), dọn sạch đồng bộ 3 bảng (`booking_child_breakfast_details`, `booking_room_children`, `booking_children`). Các trẻ có mã nhỏ hơn được giữ nguyên 100%.
  2. **Chuẩn hóa hàm `$syncChildCount` trong [`BookingController.php`](file:///d:/PMS/backend/app/Http/Controllers/Api/BookingController.php) (`updateBooking`)**:
     - Đồng bộ cùng cơ chế tăng chỉ thêm mới, giảm xóa mã lớn nhất từ trên xuống, cập nhật lại cột `children_qty` và `babies` trên phòng.
  3. **Bổ sung Event `deleting` trong Model [`BookingChild.php`](file:///d:/PMS/backend/app/Models/BookingChild.php)**:
     - Tự động dọn dẹp sạch sẽ `BookingChildBreakfastDetail` và `BookingRoomChild` khi bất kỳ `BookingChild` nào bị xóa, ngăn ngừa hoàn toàn dữ liệu mồ côi (orphan records).
- **Kiểm thử**:
  - Chạy test script tự động bao phủ 7 kịch bản: tăng trẻ em từ 2 lên 3 -> giảm về 2; tăng em bé từ 1 lên 2 -> giảm về 1; tăng trẻ em qua `updateBooking` lên 4 -> giảm về 1. Kết quả toàn bộ 7 test cases đều PASSED 100%.
  - Build frontend `npm run build` thành công 100%.

## [2026-09-28] - Khắc phục spam thông báo & tự động lưu khi sửa Thêm giường trên màn hình Đăng ký
### Module: Đặt phòng / Sửa Booking / Thêm giường ([CreateRegistrationPage.vue](file:///d:/PMS/frontend/src/pages/reservation/CreateRegistrationPage.vue))

- **Bối cảnh & Nguyên nhân lỗi**:
  - Khi bấm "Sửa" Booking trên thanh chức năng, người dùng thao tác nhập số lượng thêm giường (`extraBedQty`) hoặc giá thêm giường (`extraBedPrice`).
  - Trước đây: cả 2 ô input đều bắt sự kiện `@input` và gọi trực tiếp API `PUT /bookings/.../rooms/...` kèm vòng lặp `POST /booking-rooms/.../services` theo từng ký tự gõ phím.
  - Hậu quả: mỗi ký tự gõ vào (ví dụ gõ giá 30,000) gửi 5 request song song và hiển thị dồn dập hàng loạt thông báo *"Đang lưu thông tin Thêm giường..."* và *"Cập nhật Thêm giường thành công!"* che kín màn hình (như hình phản ánh của khách). Đồng thời gây sai lệch cơ chế "Quay lại" (hủy sửa) vì dữ liệu đã bị ghi sớm vào database.
- **Nghiệp vụ đã xử lý**:
  - Bỏ hoàn toàn việc gọi API ghi sớm và bỏ spam toast trong các hàm `handleInlineExtraBedQtyChange` và `handleInlineExtraBedRateChange`.
  - Toàn bộ thao tác sửa số lượng và giá thêm giường được tính toán cập nhật mượt mà trong bộ nhớ (`room.dailyExtraBeds`, `room.services`, `room.total`).
  - Chuẩn hóa hàm `getRoomExtraBedQty` và `getRoomExtraBedTotal`: khi người dùng đưa số lượng thêm giường về `0`, tổng tiền thêm giường lập tức về `0` chính xác, không bị ảnh hưởng bởi dịch vụ cũ.
  - Khi người dùng bấm nút **Lưu** (trên thanh chức năng) và xác nhận lưu: toàn bộ thông tin đăng ký cùng số lượng, giá và chi tiết thêm giường được đồng bộ lưu xuống Database qua API `updateBooking` một lần duy nhất với 1 thông báo thành công.
  - Thêm nút mũi tên lên/xuống (stepper carets) cho cả 2 ô:
    - Ô **Thêm giường**: bấm nút lên tăng dần 1 đơn vị (+1), nút xuống giảm dần 1 đơn vị (-1, min 0).
    - Ô **Giá thêm giường**: bấm nút lên tăng dần 50,000đ (+50,000), nút xuống giảm dần 50,000đ (-50,000, min 0). Tự động đặt số lượng = 1 nếu đang tăng giá khi số lượng = 0.
- **Kiểm thử**:
  - Build frontend `npm run build` thành công 100%.

## [2026-09-28] - Lưu đồng thời thông tin đăng ký và phòng mới trên màn hình Đăng ký
### Module: Đặt phòng / Sửa và thêm phòng ([CreateRegistrationPage.vue](file:///d:/PMS/frontend/src/pages/reservation/CreateRegistrationPage.vue))

- **Yêu cầu nghiệp vụ**:
  - Trên màn hình thông tin đăng ký, khi vừa thao tác sửa thông tin booking (hoặc thông tin đưa đón) vừa thực hiện lấy phòng ở tab "Lấy phòng".
  - Trước đây: hệ thống chỉ lưu được thao tác tại tab đang đứng (đang đứng ở tab Lấy phòng thì chỉ thêm phòng chứ không lưu thông tin chung).
  - Khắc phục: cho phép lưu được toàn bộ thông tin thay đổi (thông tin chung, đưa đón) và lấy phòng khi lưu Booking.
- **Nghiệp vụ đã xử lý**:
  - Khai báo snapshot độc lập: `initialBookingSnapshot` và `initialRoomsSnapshot`, chia tách `getModalBookingSnapshot()` và `getModalRoomsSnapshot()`.
  - Bổ sung 2 thuộc tính tính toán `isBookingInfoDirty` (kiểm tra thay đổi thông tin chung/đưa đón) và `hasRoomsToAdd` (kiểm tra có phòng đang chọn thêm trong `roomAddDraft`).
  - Cập nhật hàm `handleSaveNewBooking`:
    - Nếu vừa có thay đổi thông tin chung/đưa đón vừa có chọn phòng thêm (`hasRoomsToAddNow && isBookingInfoChanged`): gọi tuần tự API `updateBooking` cập nhật thông tin booking, sau đó gọi `addBookingRooms` để nạp các phòng mới.
    - Nếu chỉ sửa thông tin: gọi `updateBooking`.
    - Nếu chỉ thêm phòng: gọi `addBookingRooms`.
    - Modal popup xác nhận tự động đổi tiêu đề và nội dung phù hợp: *"Xác nhận cập nhật thông tin & thêm phòng"* với nút *"Lưu tất cả"*.
    - Cập nhật nhãn nút bấm lưu tại footer modal hiển thị *"Lưu thay đổi & Thêm phòng"* khi có cả 2 thay đổi.
- **Kiểm thử**:
  - Build frontend `npm run build` thành công 100%.

## [2026-09-28] - Chặn Hủy nhận phòng đối với phòng chuyển trên Sơ đồ phòng (Room Map)
### Module: Lễ tân / Sơ đồ phòng / Hủy nhận phòng ([RoomController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/RoomController.php), [RoomResource.php](file:///d:/PMS/backend/app/Http/Resources/RoomResource.php), [RoomMapPage.vue](file:///d:/PMS/frontend/src/pages/reservation/RoomMapPage.vue), [BookingRoomController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/BookingRoomController.php))

- **Yêu cầu nghiệp vụ**:
  - Đối với các booking/phòng có ngày đến = ngày hệ thống, nhưng được chuyển từ phòng khác qua (room move / room merge) thì KHÔNG cho thao tác hủy nhận phòng.
  - Ví dụ: Phòng 101 check in ngày 9, sau đó ngày 10 chuyển qua phòng 102 -> Phòng 102 không được hủy nhận phòng.
- **Nghiệp vụ đã xử lý**:
  1. **Backend ([RoomController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/RoomController.php), [RoomResource.php](file:///d:/PMS/backend/app/Http/Resources/RoomResource.php))**:
     - Thêm quan hệ `movedFromRoom` vào eager loading của danh sách phòng hôm nay (`$bookingRoomsToday`).
     - Xác định cờ `is_transferred = (bool) ($br->movedFromRoom)` và trả về qua `RoomResource`.
     - [BookingRoomController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/BookingRoomController.php) đã có logic chặn cứng API `undoCheckIn` với thông báo 422: *"Phòng đã được chuyển từ phòng khác, không thể hủy nhận phòng tại đây."*
  2. **Giao diện người dùng ([RoomMapPage.vue](file:///d:/PMS/frontend/src/pages/reservation/RoomMapPage.vue))**:
     - Cập nhật hàm `canShowUndoCheckinForRoom(room)`: kiểm tra `if (room.is_transferred) return false` để ẩn hoàn toàn nút "Hủy nhận phòng" trên Context Menu chuột phải của phòng chuyển.
     - Bổ sung kiểm tra guard trong `handleUndoCheckinFromMenu` và `executeUndoCheckin` với thông báo toast cảnh báo nếu phát hiện phòng chuyển.
- **Kiểm thử**:
  - Viết script kiểm thử xác nhận `movedFromRoom` relation, cờ `is_transferred` trên `RoomResource` và trong DB transaction.
  - Build frontend `npm run build` thành công 100%.

## [2026-09-28] - Hoàn thiện Section 18: Thông số RegistrationStatusId_BookingCancel khi hủy Booking
### Module: Đặt phòng / Hủy đăng ký / Cài đặt cấu hình ([BookingController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/BookingController.php), [HotelDefinitionSeeder.php](file:///d:/PMS/backend/database/seeders/HotelDefinitionSeeder.php), [HotelConfigTab.vue](file:///d:/PMS/frontend/src/pages/config/components/hotel/HotelConfigTab.vue))

- **Yêu cầu nghiệp vụ**:
  - Thông số: `RegistrationStatusId_BookingCancel`.
  - Giá trị của thông số là giá trị tình trạng sẽ được cập nhật vào bảng `Bookings.registration_status_id`.
  - Nếu giá trị khác 0: khi hủy BK thì bảng `Bookings.registration_status_id` lưu theo giá trị của thông số (ví dụ: 28).
  - Nếu thông số = 0: khi hủy BK thì tình trạng ở `Bookings.registration_status_id` giữ nguyên không thay đổi.
- **Nghiệp vụ đã xử lý**:
  1. **Cơ sở dữ liệu & Seeder ([HotelDefinitionSeeder.php](file:///d:/PMS/backend/database/seeders/HotelDefinitionSeeder.php))**:
     - Thêm cấu hình `RegistrationStatusId_BookingCancel` với giá trị mặc định là `'0'` và mô tả rõ ràng.
     - Khởi tạo bản ghi trong bảng `hotel_configs` (CSDL hiện tại giá trị = `0`).
  2. **Xử lý Backend ([BookingController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/BookingController.php))**:
     - Tại hàm `destroy` khi hủy booking: lấy giá trị thông số `RegistrationStatusId_BookingCancel`.
     - Nếu giá trị là số và khác 0: gán trực tiếp giá trị số đó vào `Bookings.registration_status_id`.
     - Nếu giá trị = 0 (hoặc rỗng): giữ nguyên `registration_status_id` hiện tại của booking.
  3. **Giao diện Cấu hình ([HotelConfigTab.vue](file:///d:/PMS/frontend/src/pages/config/components/hotel/HotelConfigTab.vue))**:
     - Thêm dropdown chọn nhanh các tình trạng đăng ký (`0 - Giữ nguyên không thay đổi tình trạng`, `28 - Cancelled`,...) hiển thị mã + tên tiếng Việt trực quan.
     - Hiển thị tên trạng thái tương ứng ngay trên cột "Giá trị" của bảng danh sách cấu hình.
- **Kiểm thử**:
  - Chạy script kiểm thử tự động với 3 trường hợp: thông số = 0 (giữ nguyên), thông số = 28 (lưu 28), thông số = 24 (lưu 24) -> cả 3 trường hợp đều khớp 100%.
  - Build frontend `npm run build` thành công 100%.

## [2026-09-28] - Khắc phục hiển thị Header Summary & Đồng bộ tức thời cấu hình SyncRoomDateByBookingDate
### Module: Đặt phòng / Quản lý đăng ký / Cài đặt cấu hình ([CreateRegistrationPage.vue](file:///d:/PMS/frontend/src/pages/reservation/CreateRegistrationPage.vue), [HotelConfigTab.vue](file:///d:/PMS/frontend/src/pages/config/components/hotel/HotelConfigTab.vue))

- **Bối cảnh & Nguyên nhân**:
  1. Khi người dùng bấm "Sửa", cụm ô chọn ngày đến/đi `<input type="date">` bị rớt xuống dòng thứ hai thay vì nằm ngang hàng với nhãn "Ngày đến/đi:". Nguyên nhân do container bọc input là block `div` không có căn chỉnh flex.
  2. Khi sửa cấu hình `SyncRoomDateByBookingDate = 0` tại Cài đặt, màn hình Tạo đăng ký đang mở không tự động cập nhật lại cấu hình mà phải F5 trang mới nhận giá trị mới.
- **Nghiệp vụ đã xử lý**:
  1. Căn chỉnh giao diện: Chuyển container Ngày đến/đi và Số đêm sang `flex items-center gap-1`, bao bọc ô date picker bằng `inline-flex items-center space-x-1`, đảm bảo luôn hiển thị ngang hàng thẳng tắp kể cả ở chế độ xem lẫn chế độ sửa.
  2. Tích hợp SingleDatePicker với icon lịch và popup chọn ngày trực quan:
     - Thay thế ô input native cũ bằng component [SingleDatePicker.vue](file:///d:/PMS/frontend/src/components/SingleDatePicker.vue) chuẩn giao diện của hệ thống: hiển thị ngày định dạng `DD/MM/YYYY`, tích hợp icon lịch bên phải để mở popup chọn ngày nhanh hoặc gõ trực tiếp.
     - Đồng bộ chiều cao (`h-[26px]`), bo góc và hiệu ứng viền giữa 2 ô ngày đến/đi và ô số đêm.
     - Khi `SyncRoomDateByBookingDate = 0`: Cả 2 ô ngày và ô số đêm bị khóa, khi click vào sẽ bật thông báo toast cảnh báo: *"Thông số cấu hình SyncRoomDateByBookingDate = 0: Không cho phép chỉnh sửa ngày đến/đi và số đêm trực tiếp trên thanh tiêu đề."*.
     - Khi `SyncRoomDateByBookingDate = 1`: Mở click chọn từ lịch bình thường và tự động đồng bộ xuống các phòng con chưa check-in.
  3. Đồng bộ cấu hình thời gian thực:
     - Tại [HotelConfigTab.vue](file:///d:/PMS/frontend/src/pages/config/components/hotel/HotelConfigTab.vue), phát tín hiệu broadcast `{ type: 'hotel-config-updated', name, value }` và dispatch window custom event khi cập nhật cấu hình.
     - Tại [CreateRegistrationPage.vue](file:///d:/PMS/frontend/src/pages/reservation/CreateRegistrationPage.vue), lắng nghe sự kiện cập nhật cấu hình qua broadcast và window event để cập nhật ngay lập tức `hotelSettings.value`.
     - Tự động gọi `loadDropdowns()` khi người dùng bấm hành động "Sửa" để luôn bảo đảm lấy cấu hình mới nhất từ server.
- **Kiểm thử**:
  - Frontend `npm run build` thành công 100% (4.73s).

## [2026-09-25] - Hoàn thiện toàn diện Đồng bộ Realtime đa màn hình & Rà soát triệt để các Section Booking 2
### Module: Đặt phòng / Quản lý đăng ký / Sơ đồ phòng ([CreateRegistrationPage.vue](file:///d:/PMS/frontend/src/pages/reservation/CreateRegistrationPage.vue), [RoomMapPage.vue](file:///d:/PMS/frontend/src/pages/reservation/RoomMapPage.vue), [BookingDetailModal.vue](file:///d:/PMS/frontend/src/components/BookingDetailModal.vue), [QuickUpdateModal.vue](file:///d:/PMS/frontend/src/pages/reservation/components/QuickUpdateModal.vue), [DepositModal.vue](file:///d:/PMS/frontend/src/pages/reservation/components/DepositModal.vue), [SystemSearchModal.vue](file:///d:/PMS/frontend/src/pages/reservation/components/SystemSearchModal.vue), [BookingController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/BookingController.php), [api.php](file:///d:/PMS/backend/routes/api.php))

- **Bối cảnh & Nguyên nhân lỗi**:
  - Khi mở 2 tab/màn hình song song cùng một booking (ví dụ: `GAL1`), người dùng ở Tab 1 cập nhật số ngày/đêm từ 3 thành 5 đêm thì Tab 2 không tự động cập nhật, vẫn giữ 3 đêm (lỗi Section 13).
  - Nguyên nhân:
    1. Hàm `handleSaveNewBooking` trong [CreateRegistrationPage.vue](file:///d:/PMS/frontend/src/pages/reservation/CreateRegistrationPage.vue) sau khi gọi API `updateBooking`, `createBooking`, `addBookingRooms` không phát tín hiệu `notifyRoomUpdates`.
    2. Ở phía Tab nhận tín hiệu, hàm `refreshMatchingBooking` gọi `fetchBooking` nhưng `fetchBooking` chưa được import ở đầu file, gây ra `ReferenceError: fetchBooking is not defined`.
    3. Hàm `fillModalForm` chưa được định nghĩa khi modal đang mở ở chế độ xem.
    4. Nhiều action khác (giao phòng, gán phòng, gỡ số phòng, khóa/mở chuyển phòng, hủy phòng/đăng ký, sao chép, nâng hạng, thêm giường, thêm dịch vụ) chưa phát tín hiệu đồng bộ broadcast.
- **Nghiệp vụ đã xử lý**:
  - **Section 13 (Đồng bộ Realtime 2 tab song song & đa màn hình)**:
    - Bổ sung `fetchBooking` vào imports và định nghĩa hàm `fillModalForm(tab)`.
    - Viết lại hàm `refreshMatchingBooking`: nhận diện booking theo cả `id`, `dbId`, `bookingCode`, tự động gọi `fetchBooking` cập nhật trực tiếp bản ghi tab trong `tabs.value` và nạp lại form modal nếu đang mở không có thay đổi chưa lưu (`fillModalForm`).
    - Bổ sung `notifyRoomUpdates(activeTab.value?.dbId)` vào tất cả các action: lưu đăng ký (`handleSaveNewBooking`), giao phòng (`GIAO PHONG`), tự động gán phòng, gỡ số phòng, khóa/mở chuyển phòng, hủy phòng/đăng ký, nhân bản booking, nâng hạng phòng, lưu thêm giường, lưu/xóa dịch vụ bổ sung, cập nhật nhanh, đặt cọc.
    - Kết nối `BroadcastChannel('pms-room-updates')` và `'pms-channel'` đồng bộ tức thời giữa Room Map và Create Registration.
  - **Section 2 (Ngày xác nhận)**:
    - Khi tạo mới: mặc định `confirmDate = sysDate` trong `emptyForm()`.
    - Trong `handleConfirmDateCalculation(isUserStatusChange = false)`: Nếu là booking cũ (`modalForm.dbId`), chỉ tính lại khi người dùng chủ động đổi trạng thái (`isUserStatusChange = true`), không tự ý đổi khi sửa ngày đến. Khi `calcDate < sysDate`, gán `modalForm.confirmDate = sysDate`.
  - **Section 3 (Phân quyền FO)**:
    - Chuẩn hóa route quyền dịch vụ/RM/EB trong [api.php](file:///d:/PMS/backend/routes/api.php) thành `permission:fo.service.add,fo.service.edit`, không bị chặn sai quyền.
  - **Section 7 (Phòng chuyển)**:
    - Trong `bookingToTab`: Cột phòng chuyển (`transferredFrom`) tự động truy vết số phòng chuyển đến từ `br.move_room` hoặc phòng chuyển đi tương ứng.
    - Trong backend [BookingController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/BookingController.php): bỏ qua phòng status 100 khi kiểm tra trùng số phòng.
  - **Section 10 (Gom nhóm thông báo Check-in)**:
    - Khi giao phòng hàng loạt thất bại, gom các phòng có cùng nguyên nhân lỗi thành 1 dòng (ví dụ: `Phòng 105, 106: đang ở trạng thái chờ kiểm tra (Vacant Clean). Không thể thực hiện nhận phòng.`), tự động loại bỏ tên thông số hệ thống khỏi thông báo.
  - **Section 15 (Ẩn phòng hủy khi booking active)**:
    - `filteredActiveRooms`: Chỉ hiển thị các phòng hủy khi trạng thái đăng ký của booking là "Đã hủy" (status = 3).
  - **Section 16 (SyncRoomDateByBookingDate trên Header & Khóa sửa tên)**:
    - Bỏ input sửa tên booking trên thanh header summary, luôn hiển thị dạng text cố định.
    - Ngày đến/đi và Số đêm trên header chỉ cho phép chỉnh sửa khi cấu hình `SyncRoomDateByBookingDate = 1`.
    - Trong `handleMainDateChange` và `handleMainNightsChange`: chỉ đồng bộ ngày sang phòng con khi `SyncRoomDateByBookingDate = 1` VÀ `checkIn >= sysDate`, và chỉ đồng bộ sang phòng có trạng thái `bookingRoomStatus === 0`.
  - **Section 17 (Lỗi sửa ngày đi phòng)**:
    - Backend [BookingController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/BookingController.php): Không kiểm tra trùng giai đoạn đối với dữ liệu DB cũ khi request đã gửi mảng `room_allocations` mới.
  - **Section 19 (Tìm kiếm trạng thái booking khi hủy phòng)**:
    - [SystemSearchModal.vue](file:///d:/PMS/frontend/src/pages/reservation/components/SystemSearchModal.vue): `getDisplayStatus` trả về đúng `booking.status`, không ép về trạng thái 3 khi các phòng con bị hủy.
- **Kiểm thử**:
  - Frontend `npm run build` thành công 100%.
  - Cú pháp PHP hợp lệ 100% trên toàn bộ các file API & Controller.

## [2026-09-25] - Khắc phục lỗi Tăng/Giảm giá chi tiết từng đêm phòng (Subtable Night Rate Adjustment)
### Module: Đặt phòng / Quản lý đăng ký ([CreateRegistrationPage.vue](file:///d:/PMS/frontend/src/pages/reservation/CreateRegistrationPage.vue))

- **Bối cảnh & Nguyên nhân lỗi**:
  - Khi mở bảng chi tiết đêm phòng và mở popover "Tăng/Giảm giá", người dùng nhập giá trị giảm (ví dụ: `10%`) nhưng "Giá mới" vẫn hiển thị bằng "Giá gốc" (`540,000đ`), các cột "Đơn giá", "Thành tiền" của đêm và giá phòng không cập nhật lại.
  - Nguyên nhân: Tại dòng 7098 trong [CreateRegistrationPage.vue](file:///d:/PMS/frontend/src/pages/reservation/CreateRegistrationPage.vue) (chế độ nhóm phòng MODE B), biểu thức chính quy bị lỗi double-escape `e.target.value.replace(/[^\\d]/g, '')`. Do `/.../` trong JS đã là RegExp literal nên `\\d` bị coi là ký tự `\` hoặc `d`, dẫn đến việc xóa sạch toàn bộ các chữ số (kể cả số 0-9), khiến giá trị sau khi làm sạch luôn là chuỗi rỗng `""` và `disc.discountValue` luôn bằng 0.
  - Đồng thời, hàm `closeDiscountPopover` chưa lưu lại context `room` và `svc` khi người dùng click ra ngoài (global click), chưa cập nhật lại mảng `room.services` và tổng tiền `room.total` sau khi lưu API.
- **Nghiệp vụ đã xử lý**:
  - Sửa regex thành `e.target.value.replace(/[^\d]/g, '')`, đảm bảo giữ đúng giá trị số nguyên phần trăm/số tiền khi người dùng nhập.
  - Thêm `activeDiscountContext` để ghi nhớ phòng và dịch vụ đang được chỉnh sửa chiết khấu; tự động lưu và cập nhật kể cả khi bấm nút "Xong", đổi popover hoặc click ra ngoài.
  - Cập nhật tự động tải lại `room.services` và tính toán lại `room.total = calculateRoomTotal(room)` sau khi gọi API `createBookingRoomService` thành công, đồng thời thông báo toast cho người dùng.
- **Kiểm thử**:
  - Frontend `npm run build` thành công 100%.

## [2026-09-25] - Khắc phục lỗi Thông tin trẻ em trong Booking & Sơ đồ phòng (Room Map)
### Module: Thông tin khách lưu trú & Sơ đồ phòng ([GuestController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/GuestController.php), [GuestInfoModal.vue](file:///d:/PMS/frontend/src/pages/reservation/components/GuestInfoModal.vue), [BookingDetailModal.vue](file:///d:/PMS/frontend/src/components/BookingDetailModal.vue))

- **Bối cảnh & Nguyên nhân lỗi**:
  - Khi thêm trẻ em mới vào phòng rồi lưu từ màn hình Sơ đồ phòng (Room Map), hệ thống bị lỗi 500: `Class "App\Http\Controllers\Api\BookingRoomService" not found`. Nguyên nhân do [GuestController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/GuestController.php) thiếu dòng import model `use App\Models\BookingRoomService;` trong hàm `syncExtraBedServices`.
  - Trong modal "Thông tin khách trong phòng" ([GuestInfoModal.vue](file:///d:/PMS/frontend/src/pages/reservation/components/GuestInfoModal.vue)), khi người dùng bật chế độ chỉnh sửa, dòng trẻ em chỉ xử lý trường `title` và ngày tháng; toàn bộ các cột dữ liệu còn lại (`nationality_code`, `id_type`, `residence_type`, `province`, `district`, `ward`, `guest_type`, `entry_purpose`, `border_gate`) bị rơi vào fallback ô input text thông thường thay vì select dropdown như người lớn.
  - Cột "Thao tác" ở đầu bảng chứa nút "Thẻ khách" / "Thẻ trẻ" chiếm diện tích hiển thị của bảng.
  - Khi thêm hoặc chỉnh sửa trẻ em từ popup chi tiết đặt phòng [BookingDetailModal.vue](file:///d:/PMS/frontend/src/components/BookingDetailModal.vue), các trường giấy tờ tùy thân, liên hệ và địa chỉ của trẻ em bị bỏ trống và không được lưu vào backend.
- **Nghiệp vụ đã xử lý**:
  - **Sửa triệt để lỗi 500 Backend ([GuestController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/GuestController.php))**:
    - Khai báo `use App\Models\BookingRoomService;` ở đầu file.
    - Nâng cấp `addChild`, `updateChild`, `bulkUpdate` hỗ trợ đầy đủ các trường thông tin cá nhân của trẻ em (`id_type`, `id_number`, `id_issue_date`, `passport_number`, `passport_expiry`, `phone`, `email`, `address`, `province`, `district`, `ward`, `residence_type`, `temp_residence_to`, `visa_no`, `entry_date`, `visa_expiry_date`, `entry_purpose`, `border_gate`, `note`).
    - Tự động gán `passport_number` khi `id_type` là hộ chiếu và đồng bộ thông tin lưu trú của phòng cùng địa giới hành chính (`syncGeoFromData`).
  - **Hoàn thiện bảng Thông tin khách trong phòng ([GuestInfoModal.vue](file:///d:/PMS/frontend/src/pages/reservation/components/GuestInfoModal.vue))**:
    - Bỏ cột "Thao tác" ở đầu bảng; người dùng có thể nhấp đúp (double-click) vào bất kỳ dòng nào (hoặc cột STT) để mở Thẻ thông tin chi tiết khách/trẻ em.
    - Bổ sung toàn bộ dropdown chọn cho dòng trẻ em giống như người lớn: Danh xưng, Quốc tịch, Loại giấy tờ, Thường trú/Tạm trú, Loại khách, Mục đích, Cửa khẩu, Tỉnh thành, Quận/Huyện, Phường/Xã.
    - Đồng bộ hiển thị nhãn thân thiện (`getDisplayTitle`) ở chế độ chỉ xem cho trẻ em.
  - **Đồng bộ Sơ đồ phòng ([BookingDetailModal.vue](file:///d:/PMS/frontend/src/components/BookingDetailModal.vue))**:
    - Nạp và ánh xạ đầy đủ các trường giấy tờ, liên hệ cho trẻ em và em bé trong `loadGuests`.
    - Tự động điền dữ liệu của trẻ khi chọn trẻ em/em bé (`selectChild`).
    - Truyền đầy đủ dữ liệu khi thêm mới (`addBookingChild`) và cập nhật (`updateBookingChild`).
    - Bỏ giới hạn cắt cụt 35 quốc tịch (`.slice(0, 35)`), chuẩn hóa mã ISO 3 ký tự và cho phép cuộn/tìm kiếm toàn bộ 250+ quốc gia từ A đến Z.
- **Kiểm thử**:
    - Frontend build thành công 100% không phát sinh lỗi template/script.
    - Cú pháp PHP của [GuestController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/GuestController.php) đạt chuẩn không có lỗi cú pháp.

## [2026-09-25] - Khắc phục lỗi khôi phục Database (max_allowed_packet 1153) & Nâng cấp toàn diện Sao lưu / Khôi phục
### Module: Quản trị Hệ thống / Database Backup & Restore ([DatabaseBackupController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/DatabaseBackupController.php), [DatabaseBackupTab.vue](file:///d:/PMS/frontend/src/pages/config/components/DatabaseBackupTab.vue))

- **Bối cảnh & Nguyên nhân lỗi**:
  - Khi người dùng tải file backup từ server về để khôi phục cho chi nhánh `HKT1` (hoặc `ALL`/`SYSTEM`), hệ thống báo lỗi: `SQLSTATE[08S01]: Communication link failure: 1153 Got a packet bigger than 'max_allowed_packet' bytes (Connection: mysql_hkt1...)`.
  - Cấu hình MySQL/MariaDB mặc định trong XAMPP chỉ cấp `max_allowed_packet = 1M`.
  - Cơ chế import cũ đọc toàn bộ file vào chuỗi `$sqlContent` và truyền cả file hàng chục MB vào một lệnh `DB::unprepared($cleanSql)`, làm MySQL ngắt kết nối ngay lập tức vì vượt quá packet cho phép; đồng thời khi lỗi, Laravel dump toàn bộ nội dung SQL khổng lồ làm tràn vỡ giao diện.
  - File sao lưu cũ không export Stored Procedures/Functions/Views, khiến bản sao lưu bị thiếu các store báo cáo.
- **Nghiệp vụ đã xử lý**:
  - **Tự động nâng `max_allowed_packet` lên 1GB**:
    - Gọi tự động `SET GLOBAL max_allowed_packet = 1073741824;` trong controller khi thực thi tác vụ database.
    - Cập nhật cấu hình file `my.ini` của XAMPP nâng `max_allowed_packet` lên 256M để duy trì sau mỗi lần restart MySQL.
  - **Khôi phục trực tiếp qua `mysql.exe` CLI (O(1) RAM, cực nhanh & chuẩn xác)**:
    - Tìm và gọi `mysql.exe` qua `proc_open` với luồng file descriptor trực tiếp, đặt cờ `--max_allowed_packet=512M` và truyền `MYSQL_PWD`.
    - Tự động chuẩn hóa file SQL trước khi nạp: loại bỏ các câu lệnh `CREATE DATABASE` và `USE` gây xung đột đích khôi phục; chuẩn hóa `DEFINER=CURRENT_USER`; bảo toàn hoàn toàn số thập phân (`0.000000`).
    - Hỗ trợ đầy đủ Stored Procedures, Functions, Triggers, Views và các khối `DELIMITER` phức tạp.
    - Xây dựng cơ chế fallback đọc stream từng câu lệnh độc lập nếu môi trường không có mysql CLI.
  - **Nâng cấp Export đầy đủ Stored Procedures, Functions, Views**:
    - Xuất toàn bộ Stored Procedures (`SHOW PROCEDURE STATUS`) và Stored Functions (`SHOW FUNCTION STATUS`) kèm khối `DELIMITER ;;`.
    - Phân tách riêng `BASE TABLE` và `VIEW`.
    - Giảm chunk size insert xuống 50 dòng/lần tránh phình kích thước từng lệnh INSERT.
  - **Giao diện người dùng ([DatabaseBackupTab.vue](file:///d:/PMS/frontend/src/pages/config/components/DatabaseBackupTab.vue))**:
    - Bọc thông báo lỗi trong container có thanh cuộn `max-h-48 overflow-y-auto break-all`, backend giới hạn độ dài lỗi trả về, không còn tình trạng tràn chữ đỏ khắp màn hình.
- **Kiểm thử**:
  - Đã xuất và khôi phục thành công 100% database `HKT1` (2.67 MB, bao gồm bảng và 25+ stored procedures) trong ~3 giây với HTTP 200.
  - Đã xuất và khôi phục thành công 100% database `SYSTEM` với HTTP 200.
  - Frontend build thành công 100% không phát sinh lỗi.

## [2026-09-25] - Khắc phục lỗi 500 Server Error và hoàn thiện nghiệp vụ Khôi phục Booking hủy khi Over phòng
### Module: Đăng ký đặt phòng ([BookingController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/BookingController.php), [CreateRegistrationPage.vue](file:///d:/PMS/frontend/src/pages/reservation/CreateRegistrationPage.vue))

- **Bối cảnh & Nguyên nhân lỗi**:
  - Khi booking bị hủy được khôi phục trong tình trạng loại phòng bị over (`AV < 0`), hệ thống báo `500 Server Error` (khách hàng gặp trên booking `GAL14`).
  - Nguyên nhân chính: Trong [BookingController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/BookingController.php) thiếu khai báo `use App\Models\RoomClass;` dẫn đến ngoại lệ `Class "App\Http\Controllers\Api\RoomClass" not found`.
  - Thiếu `withTrashed()` trên eager loading relation `bookingRooms` của booking, và `RoomAvailabilityService::getAvailability` yêu cầu tham số kiểu int trong khi `room_class_id` có thể chứa chuỗi ghép dạng `"9-1"`.
- **Nghiệp vụ đã xử lý**:
  - **Kiểm soát Over phòng theo cấu hình chi nhánh `AllowOverRoomTypeRoomKind`**:
    - Khi `AllowOverRoomTypeRoomKind = 1`: Cho phép khôi phục nhưng hiển thị popup cảnh báo xác nhận: *"Số lượng của loại phòng sau khi khôi phục đăng ký đang bị over, bạn có muốn tiếp tục?"*. Khi người dùng bấm *"Tiếp tục"*, hệ thống gửi cờ `force: true` để hoàn tất khôi phục booking và phòng.
    - Khi `AllowOverRoomTypeRoomKind = 0`: Chặn cứng không cho khôi phục và trả về mã 422 cùng thông báo chi tiết danh sách loại phòng đang over.
  - **Ràng buộc bất biến - Chặn trùng số phòng vật lý tuyệt đối**:
    - Cấu hình `AllowOverRoomTypeRoomKind = 1` CHỈ cho phép âm số lượng khả dụng của loại phòng ($AV < 0$).
    - Tuyệt đối không cho phép trùng số phòng vật lý (`room_number`) giữa các phòng đã đặt. Hệ thống kiểm tra cả nội bộ danh sách phòng của booking lẫn các phòng active khác trong cùng khoảng thời gian; nếu phát hiện trùng số phòng sẽ lập tức chặn khôi phục (HTTP 422) kèm chi tiết số phòng và mã booking xung đột.
  - **Đồng bộ Frontend ([CreateRegistrationPage.vue](file:///d:/PMS/frontend/src/pages/reservation/CreateRegistrationPage.vue))**:
    - Chuẩn hóa thông điệp xác nhận ban đầu cho booking hủy / noshow.
    - Xử lý nhận diện phản hồi `needs_confirm` từ backend để hiển thị dialog cảnh báo Over booking với 2 nút "Tiếp tục" và "Hủy".
    - Tự động xóa `tab.dbId` khỏi danh sách `closedIds` trong localStorage (`removeClosedTabId`) và nạp lại booking sau khi khôi phục thành công.

## [2026-09-25] - Ẩn cột "Đặt trước" (isPreassigned) trên bảng phòng Booking
### Module: Đăng ký đặt phòng ([CreateRegistrationPage.vue](file:///d:/PMS/frontend/src/pages/reservation/CreateRegistrationPage.vue))

- **Nghiệp vụ**:
  - Ẩn cột "Đặt trước" (`isPreassigned`) khỏi bảng phòng trong màn hình Đăng ký đặt phòng bằng cách chuyển `visible: false` theo mặc định.
  - Loại bỏ hoàn toàn hiển thị cột không sử dụng này trên cả chế độ bảng phòng chi tiết và rút gọn, tự động co lại chiều rộng `tableWidth`.

## [2026-09-23] - Phân tích đặc tả kỹ thuật Báo cáo Dòng 169, 170, 171 (Báo cáo Dự đoán bán phòng, Báo cáo Phòng hàng tuần, Báo cáo Tổng doanh thu)
### Module: Tài liệu phân tích báo cáo ([ROW_169_170_171_COMPREHENSIVE_SPECIFICATION.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/reports/ROW_169_170_171_COMPREHENSIVE_SPECIFICATION.md), [dong_169_bao_cao_du_doan_ban_phong.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/dong_169_bao_cao_du_doan_ban_phong.md), [dong_170_bao_cao_phong_hang_tuan.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/dong_170_bao_cao_phong_hang_tuan.md), [dong_171_bao_cao_tong_doanh_thu.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/dong_171_bao_cao_tong_doanh_thu.md))

- **Bối cảnh & Yêu cầu**:
  - Đọc và phân tích sâu các dòng 169, 170, 171 từ file Excel `DANH MỤC BÁO CÁO.xlsx`.
  - Trích xuất ảnh giao diện UI thực tế từ các sheet tương ứng trong file Excel:
    - **Dòng 169**: Sheet 22, Sheet 39, Sheet 80 (`dong_169_ui_mau_1.png`, `dong_169_ui_mau_2.png`, `dong_169_ui_mau_3.png`).
    - **Dòng 170**: Sheet 45 (`dong_170_ui_mau.png`).
    - **Dòng 171**: Sheet 72 (`dong_171_ui_mau.png`).
  - Trích xuất và bóc tách Stored Procedure gốc từ MS SQL Server (SSMS `.\MSSQLSERVER01`):
    - **Dòng 169**: `sp_023` (`ProVistaNavyHotel.dbo.sp_023` & `ProVistaArmyHotel.dbo.sp_023`).
    - **Dòng 170**: `sp_023_Division` (`ProVistaNavyHotel.dbo.sp_023_Division`).
    - **Dòng 171**: `sp_TotalRevenueFromReportSetup` (theo cấu hình chỉ tiêu `AT7620` với `ReportCode = 'DT'`, `AT7621`) và `sp_292` (bảng kê folio chi tiết Sheet 72).
  - Soạn thảo tài liệu đặc tả độc lập, khép kín 100% để bất kỳ Agent nào tiếp nhận cũng có đầy đủ công thức toán học, cấu trúc Stored Procedure MySQL 8.0, định nghĩa Form Designer Template (`content_json`, columns, blocks, customRows) và quy tắc đối soát chéo bất biến (Cross-Verification Rules).
- **Đã hoàn thành**:
  - **Dòng 169 - Báo cáo dự đoán bán phòng (`ROOM_FORECAST` / `sp_023`)**:
    - Ma trận 17 cột có đánh số thứ tự chỉ số từ `(1)` đến `(17)`.
    - Phân tách và đặc tả 13 công thức cốt lõi: Phòng đi/đến/ở, Nội bộ (House Use), Phòng miễn phí (FOCAll), Phòng bán (`P.Bán = P.Ở - HU - FOC`), Doanh thu phòng, ADR không tính nội bộ (`AvgRate`), ADR thực thu (`AvgRate2`), Phòng có thể bán (`RoomAvible = Total - OOO`), Công suất tổng thể (`PercentOccupancy`), Công suất thực thu (`PercentOccupancy1`), RevPAR (`DThu/Tổng phòng`).
    - Tùy chọn doanh thu bao gồm ăn sáng (`p_include_breakfast`).
    - Viết hoàn chỉnh PHP reference template `room_forecast_reference.php` và Stored Procedure MySQL 8.0 `rpt_room_forecast`.
  - **Dòng 170 - Báo cáo phòng hàng tuần (`WEEKLY_ROOM_REPORT` / `sp_023_Division`)**:
    - Cấu trúc bảng 2 tầng header: Tầng 1 gồm Ngày (rowspan 2), Thứ (rowspan 2), ĐẾN (colspan 2), ĐI (colspan 2), Ở (colspan 2), CÔNG SUẤT (%) (rowspan 2); Tầng 2 gồm Phòng & Khách dưới ĐẾN, ĐI, Ở.
    - Bộ lọc dropdown chọn tuần ("Tuần này", "Tuần trước", "Tuần sau", tùy chọn tuần).
    - Công thức dòng tổng: Số ngày (7), tổng khách đến (280), tổng khách đi (248), tổng khách ở (1601), công suất bình quân gia quyền cả tuần = $\frac{\sum \text{Phòng Ở}}{\sum \text{Phòng Khả Dụng}} \times 100\% = 79.08\%$.
    - Viết hoàn chỉnh PHP reference template `weekly_room_report_reference.php` và Stored Procedure MySQL 8.0 `rpt_weekly_room_report`.
  - **Dòng 171 - Báo cáo tổng doanh thu (`TOTAL_REVENUE` / `sp_TotalRevenueFromReportSetup` & Sheet 72)**:
    - Bóc tách toàn diện 2 mô hình vận hành:
      + *Mô hình A (Chuẩn Sheet 72 & Army)*: Bảng kê chi tiết 22 cột theo từng booking/folio trong ngày. Phân tách 7 dịch vụ thu trong ngày (Tiền phòng, Phụ thu, Minibar, Giặt, Bể vỡ, Nhà hàng, Dịch vụ khác), DT ngày trước, Tổng cộng, phân bổ thanh toán phòng đã trả (TM, CK, HH, Còn nợ) và Doanh thu treo phòng còn ở.
      + *Mô hình B (Chỉ tiêu Setup `AT7620`/`AT7621`)*: Báo cáo tài chính phân cấp theo Outlet (Nhà hàng RE, Room Service RS, Spa, Tour, Minibar...) qua store `sp_TotalRevenueFromReportSetup`.
    - Thiết lập đẳng thức cân bằng kế toán bất biến: $\text{Tổng cộng} \equiv \text{TM} + \text{CK} + \text{HH} + \text{Còn nợ} + \text{Phòng còn ở}$.
    - Viết hoàn chỉnh PHP reference template `total_revenue_reference.php` và Stored Procedure MySQL 8.0 `rpt_total_revenue`.
  - **Tài liệu ban hành**:
    - [dong_169_bao_cao_du_doan_ban_phong.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/dong_169_bao_cao_du_doan_ban_phong.md)
    - [dong_170_bao_cao_phong_hang_tuan.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/dong_170_bao_cao_phong_hang_tuan.md)
    - [dong_171_bao_cao_tong_doanh_thu.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/dong_171_bao_cao_tong_doanh_thu.md)
    - [ROW_169_170_171_COMPREHENSIVE_SPECIFICATION.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/reports/ROW_169_170_171_COMPREHENSIVE_SPECIFICATION.md)
    - Cập nhật [README.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/README.md) trong thư mục `doc_baocao`.
  - **Quyết định kỹ thuật đã phê duyệt trước khi triển khai**:
    1. *Dòng 171*: Triển khai trước mẫu bảng kê chi tiết theo Folio/Booking (Sheet 72 / `sp_292`) với đầy đủ 22 cột doanh thu và thanh toán; mô hình AT7620/AT7621 là ngoài phạm vi đợt đầu. Gộp `BreakfastSurchargeToday` vào cột Phụ thu tiền phòng.
    2. *Dòng 169*: Triển khai mẫu Lễ tân FO 17 cột đầy đủ, ẩn 4 cột doanh thu (Doanh thu, ADR w/o HU, ADR w/o HU+FOC, RevPAR) trên giao diện template khi chọn chế độ Buồng phòng (HK); chạy cho chi nhánh hiện tại.
    3. *Dòng 170*: Chuẩn hóa chỉ dùng `p_division` (`__current__`, `__all__`), loại bỏ `p_branch`; thống nhất tên cột chi nhánh là `Division`; tính tuần từ Thứ Hai đến Chủ Nhật tại Frontend và guard bằng `DATE_SUB` ở backend.
    4. *Mapping & Database*: Xác nhận `booking_rooms.id` là string(50), phòng liên kết `booking_rooms.room_number` -> `rooms.room_number`; `payments.booking_room_id` là string(50); `payments.payment_method_id` là mã string; cô lập chi nhánh bằng DB connection (`rooms` không có `branch_id`); `bookings.status = 3` là `Deleted` (không ghi trực tiếp `CANCELLED`, cần kết hợp `booking_cancel_logs`).


## [2026-09-22] - Rà soát chi tiết & Hoàn thiện Cẩm nang triển khai toàn diện 6 Báo cáo (Dòng 154, 159, 160, 166, 167, 168)
### Module: Tài liệu phân tích báo cáo ([MASTER_IMPLEMENTATION_GUIDE_6_REPORTS.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/reports/MASTER_IMPLEMENTATION_GUIDE_6_REPORTS.md))

- **Bối cảnh & Yêu cầu**:
  - Rà soát lại toàn bộ 6 báo cáo (Dòng 159, 160, 166, 154, 167, 168) theo yêu cầu người dùng: kiểm tra đối chiếu từng dòng, từng trường, từng công thức và từng ràng buộc để đảm bảo một Agent mới chưa có dữ liệu có thể tiếp nhận và triển khai độc lập, khép kín 100%.
  - Kiểm tra các giới hạn kiến trúc runtime của PMS (quy tắc Result Set đơn của `ReportDataExecutorService`, adapter dẫn xuất dataset phụ, 5 database tenant).
- **Kết quả rà soát & Bổ sung**:
  1. **Dòng 154 (Hóa đơn dịch vụ tổng hợp)**: Bổ sung kiến trúc và mã nguồn PHP Adapter `SummaryServiceInvoicesDataAdapter.php` để sinh bảng phụ `summary` (Doanh Thu | Tổng) và hướng dẫn đăng ký vào `ReportDatasetEnricher.php`, tránh lỗi procedure trả 2 result sets bị nuốt mất bảng 2.
  2. **Dòng 159 (Tiền đặt cọc)**: Chuẩn hóa cấu hình tooltip icon `(i)` trong `parameter_ui_schema` và vị trí cột "Tên đăng ký" (`BookingName`).
  3. **Dòng 160 (Tổng hợp ngày)**: Chuẩn hóa cấu trúc 2 cột dữ liệu (Ngày & Lũy kế tháng), cách mở rộng các nhóm doanh thu mới qua `UNION ALL`.
  4. **Dòng 166, 167, 168 (Công suất công ty & Doanh thu người bán)**: Xác lập và tài liệu hóa **Quy tắc đối soát chéo bất biến (Cross-Verification Rule)** đảm bảo số liệu Đêm phòng, Doanh thu phòng, Số lượng khách, ADR giữa 3 báo cáo này khớp 100% với nhau.
  5. **Ban hành Cẩm nang tổng thể**: Xuất bản [MASTER_IMPLEMENTATION_GUIDE_6_REPORTS.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/reports/MASTER_IMPLEMENTATION_GUIDE_6_REPORTS.md) tích hợp đầy đủ checklist kiểm thử, ma trận metadata, Stored Procedure và cấu hình Designer cho cả 6 báo cáo.

## [2026-09-22] - Phân tích đặc tả kỹ thuật Báo cáo Dòng 154, 167, 168 (Báo cáo Hóa đơn dịch vụ tổng hợp, Báo cáo Công suất công ty, Báo cáo Doanh thu theo người bán)
### Module: Tài liệu phân tích báo cáo ([ROW_154_167_168_COMPREHENSIVE_SPECIFICATION.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/reports/ROW_154_167_168_COMPREHENSIVE_SPECIFICATION.md), [dong_154_bao_cao_hoa_don_dich_vu_tong_hop.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/dong_154_bao_cao_hoa_don_dich_vu_tong_hop.md), [dong_167_bao_cao_cong_suat_cong_ty.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/dong_167_bao_cao_cong_suat_cong_ty.md), [dong_168_bao_cao_doanh_thu_theo_nguoi_ban.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/dong_168_bao_cao_doanh_thu_theo_nguoi_ban.md))

- **Bối cảnh & Yêu cầu**:
  - Đọc và phân tích sâu các dòng 154, 167, 168 từ file Excel `DANH MỤC BÁO CÁO.xlsx`.
  - Tuân thủ nghiêm ngặt chỉ định: **Chưa có store Galliot nên bỏ qua Galliot**, lấy store chuẩn của Navy (`ProVistaNavyHotel`).
  - Trích xuất và bóc tách Stored Procedure gốc từ MS SQL Server (SSMS `.\MSSQLSERVER01`):
    - **Dòng 154**: `sp_025` (`ProVistaNavyHotel.dbo.sp_025`), Sheet 20 & Sheet 64 `BC hóa đơn dịch vụ tổng hợp` (Bỏ qua Galliot).
    - **Dòng 167**: `sp_055` & `sp_055_Division` (`ProVistaNavyHotel.dbo.sp_055`), Sheet 15 `Báo cáo công suất công ty`.
    - **Dòng 168**: `sp_155`, `sp_158` (`ProVistaArmyHotel`) & `sp_055` (`ProVistaNavyHotel`), Sheet 19 `BC doanh thu theo người bán`.
  - Trích xuất ảnh UI thực tế: `dong_154_ui_mau.png`, `sheet_64_img_1.png`, `sheet_0_img_2.png`, `dong_167_ui_mau_1.png`, `dong_167_ui_mau_2.png`.
  - Lập tài liệu đặc tả cặn kẽ khép kín gồm đầy đủ thông số `content_json` (blocks, columns, grouping, customRows, footer, static tables), Stored Procedure MySQL 8.0, mapping cơ sở dữ liệu và hướng dẫn kiểm thử cho Agent triển khai tiếp theo.
- **Đã hoàn thành**:
  - **Dòng 154 - Báo cáo hóa đơn dịch vụ tổng hợp (`sp_025` Navy - Bỏ qua Galliot)**:
    - Bỏ qua gom nhóm theo Outlet `SP3000` của Galliot; tuân thủ chuẩn Navy: ánh xạ dịch vụ sang 6 nhóm doanh thu lớn theo bảng `SP1610` (`NightAuditReport`: Nhà hàng, Minibar, Phòng, Giặt là, Vận chuyển, Dịch vụ khác).
    - Bộ lọc dịch vụ trên UI cho phép đa chọn (`multi-select` qua `FIND_IN_SET`).
    - Sửa lỗi cú pháp store Navy cũ (`cast(vw.Date as Date)e =`).
    - Lưới 11 cột, grouping 2 cấp (Loại doanh thu -> Dịch vụ), bảng thống kê phụ 2 cột (Doanh Thu | Tổng).
    - Viết hoàn chỉnh PHP reference template `summary_service_invoices_reference.php` và Stored Procedure MySQL 8.0 `rpt_summary_service_invoices`.
  - **Dòng 167 - Báo cáo công suất công ty (`sp_055` & `sp_055_Division` Navy)**:
    - Bóc tách cấu trúc 11 cột có đánh số thứ tự từ 1-11 ở header tầng 2.
    - Trích xuất công thức gốc từ DevExpress Designer: `%OCC`, Đêm phòng, ADR thực thu, ADR niêm yết không FOC/giảm giá, Doanh thu phòng, F&B, Khác, Tổng DT.
    - Cấu hình 5 tùy chọn động: DT phòng gồm ăn sáng (`@BF`), xem chi tiết, nhóm theo ngày, theo thị trường, theo nguồn khách (thị trường & nguồn khách chỉ chọn 1 trong 2).
    - Khối Note ghi chú giải thích công thức in dưới chân bảng.
    - Viết hoàn chỉnh PHP reference template `company_occupancy_reference.php` và Stored Procedure MySQL 8.0 `rpt_company_occupancy`.
  - **Dòng 168 - Báo cáo doanh thu theo người bán (`sp_155`, `sp_158` Army & `sp_055` Navy)**:
    - Hợp nhất 2 kiểu chạy của hệ thống cũ thành tham số `Chế độ lọc (Filter Mode)`:
      + `Mode 1`: Lọc theo ngày đến của đặt phòng (Arrival Date Mode - Chuẩn Army `sp_155`/`sp_158`).
      + `Mode 2`: Lọc theo đêm phòng lưu trú thực tế trong kỳ (Stay Date / Room-Night Mode - Chuẩn Navy `sp_055` group theo `@UserSale`).
    - Hỗ trợ 2 mẫu: Tổng hợp theo người bán (11 cột) và Chi tiết từng booking.
    - Viết hoàn chỉnh PHP reference template `salesperson_revenue_reference.php` và Stored Procedure MySQL 8.0 `rpt_salesperson_revenue`.
  - **Tài liệu bàn giao**:
    - [dong_154_bao_cao_hoa_don_dich_vu_tong_hop.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/dong_154_bao_cao_hoa_don_dich_vu_tong_hop.md)
    - [dong_167_bao_cao_cong_suat_cong_ty.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/dong_167_bao_cao_cong_suat_cong_ty.md)
    - [dong_168_bao_cao_doanh_thu_theo_nguoi_ban.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/dong_168_bao_cao_doanh_thu_theo_nguoi_ban.md)
    - [ROW_154_167_168_COMPREHENSIVE_SPECIFICATION.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/reports/ROW_154_167_168_COMPREHENSIVE_SPECIFICATION.md)

## [2026-09-22] - Phân tích đặc tả kỹ thuật Báo cáo Dòng 159, 160, 166 (Báo cáo Tiền đặt cọc, Báo cáo Tổng hợp ngày, Báo cáo Chi tiết công suất công ty)
### Module: Tài liệu phân tích báo cáo ([ROW_159_160_166_COMPREHENSIVE_SPECIFICATION.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/reports/ROW_159_160_166_COMPREHENSIVE_SPECIFICATION.md), [dong_159_bao_cao_tien_dat_coc.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/dong_159_bao_cao_tien_dat_coc.md), [dong_160_bao_cao_tong_hop_ngay.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/dong_160_bao_cao_tong_hop_ngay.md), [dong_166_bao_cao_chi_tiet_cong_suat_cong_ty.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/dong_166_bao_cao_chi_tiet_cong_suat_cong_ty.md))

- **Bối cảnh & Yêu cầu**:
  - Đọc và phân tích sâu các dòng 159, 160, 166 từ file Excel `DANH MỤC BÁO CÁO.xlsx`.
  - Tuân thủ chỉ định: **Chưa có store Galliot nên bỏ qua Galliot**, lấy store chuẩn của Navy (`ProVistaNavyHotel`).
  - Trích xuất và bóc tách Stored Procedure gốc từ MS SQL Server (SSMS `.\MSSQLSERVER01`):
    - **Dòng 159**: `sp_076` (`ProVistaNavyHotel.dbo.sp_076`), Sheet 62 `BC tiền đặt cọc`.
    - **Dòng 160**: `sp_279` (`ProVistaNavyHotel.dbo.sp_279`), Sheet 71 `BC tổng hợp ngày` (Bỏ qua Galliot).
    - **Dòng 166**: `sp_078` (`ProVistaNavyHotel.dbo.sp_078`), Sheet 16 `Báo cáo chi tiết công suất công`.
  - Trích xuất ảnh UI thực tế: `dong_159_ui_mau.png`, `dong_160_ui_mau.png`, `dong_166_ui_mau.png`.
  - Lập tài liệu đặc tả cặn kẽ khép kín gồm đầy đủ thông số `content_json` (blocks, columns, grouping, customRows, footer, static tables), Stored Procedure MySQL 8.0, mapping cơ sở dữ liệu và hướng dẫn kiểm thử cho Agent triển khai tiếp theo.
- **Đã hoàn thành**:
  - **Dòng 159 - Báo cáo tiền đặt cọc (`sp_076` Navy)**:
    - Bóc tách lỗi lọc của store cũ (`Option 1` lọc khác tháng check-out vô lý, `Option 4` thiếu kiểm tra cấn trừ thanh toán).
    - Chuẩn hóa 5 chế độ lọc rõ ràng kèm tooltip icon `(i)` giải thích nghiệp vụ trên UI.
    - Bổ sung cột "Tên đăng ký" (`BookingName`) ngay sau cột Mã ĐK/Phòng.
    - Viết hoàn chỉnh PHP reference template `deposits_summary_reference.php` và Stored Procedure MySQL 8.0 `rpt_deposits_summary`.
  - **Dòng 160 - Báo cáo tổng hợp ngày (`sp_279` Navy - Bỏ qua Galliot)**:
    - Bóc tách cấu trúc 7 nhóm chỉ tiêu quản trị: Doanh thu (Phòng, F&B, Giặt là, Khác, FOC), Hoạt động KS (Inhouse, Checkin, Checkout, Cuối ngày), OCC%, ADR, Ý kiến khách, Cơ sở vật chất, Đề xuất.
    - Phân tách dữ liệu thành 2 cột so sánh: Cột Ngày (Daily) và Cột Lũy kế tháng (MTD).
    - Viết hoàn chỉnh PHP reference template `daily_summary_reference.php` và Stored Procedure MySQL 8.0 `rpt_daily_summary`.
  - **Dòng 166 - Báo cáo chi tiết công suất công ty (`sp_078` Navy)**:
    - Xác định vai trò nghiệp vụ: Báo cáo đối soát chéo công suất, doanh thu phòng, F&B, khác với báo cáo công suất cty và dự đoán bán phòng.
    - Lưới 21 cột chi tiết, công thức tính số đêm phòng/đêm khách trong kỳ, ADR thực thu vs ADR niêm yết không giảm giá.
    - Viết hoàn chỉnh PHP reference template `company_occupancy_detail_reference.php` và Stored Procedure MySQL 8.0 `rpt_company_occupancy_detail`.
  - **Tài liệu bàn giao**:
    - [dong_159_bao_cao_tien_dat_coc.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/dong_159_bao_cao_tien_dat_coc.md)
    - [dong_160_bao_cao_tong_hop_ngay.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/dong_160_bao_cao_tong_hop_ngay.md)
    - [dong_166_bao_cao_chi_tiet_cong_suat_cong_ty.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/dong_166_bao_cao_chi_tiet_cong_suat_cong_ty.md)
    - [ROW_159_160_166_COMPREHENSIVE_SPECIFICATION.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/reports/ROW_159_160_166_COMPREHENSIVE_SPECIFICATION.md)




## [2026-09-25] - Hoàn thiện Section 12: Thêm cột Tăng/giảm giá chi tiết từng đêm cho phòng

### Module: Đăng ký đặt phòng ([CreateRegistrationPage.vue](file:///d:/PMS/frontend/src/pages/reservation/CreateRegistrationPage.vue))

- **Nghiệp vụ**:
  - Thêm cột "Tăng/giảm giá" vào bảng mở rộng chi tiết dịch vụ bên dưới phòng (nằm giữa cột "Dịch vụ" và "Số lượng" theo đúng giao diện nghiệp vụ).
  - Tích hợp Popover Tăng/Giảm giá độc lập cho từng đêm phòng (`RM`/`ROOM_CHARGE`):
    - Hỗ trợ đổi hình thức Tăng (`up`) / Giảm (`down`).
    - Hỗ trợ nhập giá trị theo tỷ lệ `%` hoặc số tiền mặt `VND`.
    - Tính toán hiển thị tức thời qua `getNightAdjustedPrice(room, svc)` (đọc trực tiếp reactive state, không phụ thuộc vào chu kỳ re-render của plain object).
    - Cập nhật trực tiếp đơn giá của đêm đó thông qua `handleServiceRateChange` trên state local, gán vào `room.dailyRoomPrices`, `svc.svc_ref`, và `room.services`.
    - Sửa `getRoomDisplayServices(room)` ưu tiên sử dụng `customRate = room.dailyRoomPrices[dStr]` cho cả `dbCharge` đã lưu trong database, đảm bảo bảng chi tiết và tổng tiền phòng `room.total` lập tức cập nhật giá mới.
    - Nút "Xong" (`closeDiscountPopover(room, svc)`): đóng popover và tự động gửi request lưu bản ghi `createBookingRoomService` xuống database nếu phòng đã tồn tại trên DB.
    - Loại bỏ hoàn toàn gọi API gián đoạn trong lúc đang gõ bàn phím (`@input`), loại bỏ spam toast, giúp giao diện mượt mà không bị đơ giật.

## [2026-09-25] - Hoàn thiện Section 11: Đồng bộ base_price và chi tiết tiền phòng khi sửa giá/giảm giá

### Module: Đăng ký đặt phòng ([CreateRegistrationPage.vue](file:///d:/PMS/frontend/src/pages/reservation/CreateRegistrationPage.vue), [GuestController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/GuestController.php))

- **Nguyên nhân**:
  1. Khi sửa giá trực tiếp tại ô `price` trên dòng phòng, `room.basePrice` không được cập nhật; đồng thời `room.dailyRoomPrices` và các dịch vụ `RM` chưa post trong `room.services` không được làm mới theo đơn giá phòng mới.
  2. Khi áp dụng tăng/giảm giá qua popover (`calculateRoomAdjustedPrice`), hệ thống chỉ gán lại `room.price` và `room.total` mà không cập nhật các đêm tiền phòng chi tiết.
  3. Khi bảng chi tiết dịch vụ mở rộng (`getRoomDisplayServices`) tìm thấy dòng `RM` đã lưu từ trước trong `room.services` (ví dụ đêm đầu lưu giá cũ 650.000đ), nó giữ nguyên 650.000đ thay vì đổi sang giá mới đã giảm (450.000đ).
  4. Tại `GuestController.php` (khi sửa giá qua Thông tin phòng trên Room Map), hệ thống chỉ cập nhật `rate` mà không cập nhật `base_price` theo đặc tả Section 11.
- **Xử lý**:
  1. Thêm hàm `syncRoomPriceToDailyCharges(room, newRate)` trong [CreateRegistrationPage.vue](file:///d:/PMS/frontend/src/pages/reservation/CreateRegistrationPage.vue) để đồng bộ toàn bộ các đêm chưa post (`is_posted == 0` và có thể chỉnh sửa) trong `room.dailyRoomPrices` và `room.services`.
  2. Thêm hàm `handleRoomPriceChange(room, newPrice)` cập nhật đồng bộ `price`, `basePrice`, `total` và gọi `syncRoomPriceToDailyCharges`.
  3. Cập nhật `calculateRoomAdjustedPrice`, `syncAllocationToRooms`, và `handleQuickUpdateSaved` tự động đồng bộ `basePrice` và các đêm chi tiết chưa post.
  4. Cập nhật [GuestController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/GuestController.php) gán đồng thời `$roomData['rate']` và `$roomData['base_price']`.
- **Kiểm thử**: Frontend build thành công 100% không có lỗi (5.50s); cú pháp PHP kiểm tra đạt chuẩn.

## [2026-09-25] - Hoàn thiện Section 9: Cảnh báo chọn phòng khi bấm Cập nhật hàng loạt

### Module: Đăng ký đặt phòng ([CreateRegistrationPage.vue](file:///d:/PMS/frontend/src/pages/reservation/CreateRegistrationPage.vue))

- **Nguyên nhân**: Khi người dùng không tick chọn phòng nào (`selectedRows.value.length === 0`) và bấm `Chức năng` -> `Cập nhật`, nhánh `else` gọi nhầm `openEditModal()`, làm mở popup "Thông tin đăng ký" thay vì hiển thị cảnh báo yêu cầu chọn phòng theo đúng đặc tả Section 9.
- **Xử lý**: Tại `CreateRegistrationPage.vue`, thay thế `openEditModal()` trong nhánh `else` của action `Cập nhật` bằng `uiStore.showToast('Vui lòng chọn phòng để cập nhật.', 'warning')`.
- **Kiểm thử**: Frontend build thành công 100% không có lỗi (5.75s).

## [2026-09-24] - Hiển thị phụ thu ăn sáng trẻ em (Section 8) trong bảng chi tiết dịch vụ phòng

### Module: Đăng ký đặt phòng ([CreateRegistrationPage.vue](file:///d:/PMS/frontend/src/pages/reservation/CreateRegistrationPage.vue))

- **Nguyên nhân**: Dữ liệu phụ thu ăn sáng trẻ em được lưu trong bảng riêng `booking_child_breakfast_details` (theo yêu cầu Section 8), nhưng bảng chi tiết mở rộng dưới dòng phòng (`getRoomDisplayServices`) và hàm tính tổng (`getServicesTotal`) chỉ lấy dữ liệu từ `booking_room_services`, dẫn đến việc không hiển thị dòng phụ thu ăn sáng và không cộng tiền vào Tổng cộng phòng.
- **Xử lý**:
  1. Trong `loadBookings`, gắn `childRecords: br.children || []` vào đối tượng phòng.
  2. Trong `getRoomDisplayServices`, quét danh sách `childRecords` để hiển thị các dòng `Phụ thu ăn sáng trẻ em - {Tên trẻ}` (mã dịch vụ `BD`, số lượng 1, đơn giá và thành tiền theo từng ngày phát sinh phụ thu).
  3. Trong `getServicesTotal` và `calculateRoomTotal`, cộng tiền phụ thu ăn sáng trẻ em vào tổng tiền của phòng.
- **Kiểm thử**: Frontend build thành công 100% không có lỗi (6.82s).

## [2026-09-24] - Sửa lỗi tắt "Ở theo giờ" nhưng sau khi bấm Lưu nút vẫn tự bật lại

### Module: Đăng ký đặt phòng ([CreateRegistrationPage.vue](file:///d:/PMS/frontend/src/pages/reservation/CreateRegistrationPage.vue), [BookingController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/BookingController.php))

- **Nguyên nhân**:
  1. Khi người dùng tắt switch "Ở theo giờ" trên bảng phòng, phòng trở về lưu trú qua đêm (ngày đến != ngày đi, số đêm >= 1). Tuy nhiên, payload `updateBooking` khi ấn nút "Lưu" không gửi trường `is_day_use` lên backend.
  2. Tại backend (`BookingController.php`), biến `$effectiveDayUse` và `$isRoomDayUse` trong vòng lặp cập nhật `room_allocations` vẫn giữ giá trị cũ hoặc gán theo booking cũ mà không kiểm tra điều kiện ngày đến != ngày đi (`$roomArrival !== $roomDeparture`).
  3. Khi frontend gọi `loadBookings()`, trường `is_day_use` trong database vẫn là `1`, khiến frontend gán lại `room.hourly = true` và switch tự bật xanh trở lại.
- **Xử lý**:
  1. Frontend: Đưa `is_day_use` vào payload cập nhật của `updateBooking` và `syncRoomsToAllocations`. Chỉ set `hourly = true` khi `arrival === departure`.
  2. Backend: Bổ sung ràng buộc nếu `$roomArrival !== $roomDeparture` hoặc `$bArr !== $bDep` thì `$isRoomDayUse` và `$effectiveDayUse` bắt buộc là `false`. Cập nhật chính xác `is_day_use` của từng phòng trong `room_allocations`.
- **Kiểm thử**: Frontend build thành công 100% không có lỗi.

## [2026-09-24] - Sửa Section 6 (Chặn Day Use cho ngày quá khứ & Đồng bộ hiển thị 0 đêm)
### Module: Đặt phòng (Booking), Màn hình đăng ký & Chi tiết phòng ([CreateRegistrationPage.vue](file:///d:/PMS/frontend/src/pages/reservation/CreateRegistrationPage.vue), [BookingDetailModal.vue](file:///d:/PMS/frontend/src/components/BookingDetailModal.vue))

- **Section 6: Khóa switch "Ở theo giờ" khi ngày đến < ngày hệ thống**:
  - Tại [CreateRegistrationPage.vue](file:///d:/PMS/frontend/src/pages/reservation/CreateRegistrationPage.vue), bổ sung helper `isHourlyDisabled(room)`: Khóa hoàn toàn switch (disabled và cursor-not-allowed) khi ngày đến của phòng hoặc booking nhỏ hơn ngày hệ thống (`checkIn < systemDate`).
  - Thêm kiểm tra trong `handleHourlyToggle(room)`: Chặn bật và hiển thị toast cảnh báo nếu người dùng cố tình bật Day Use cho ngày trong quá khứ.
  - Tự động tắt `hourly = false` nếu ngày đến được điều chỉnh về trước ngày hệ thống.
  - Tại [BookingDetailModal.vue](file:///d:/PMS/frontend/src/components/BookingDetailModal.vue), chuẩn hóa `isDayUseDisabled` khóa switch cho bất kỳ phòng nào có `arrival_date < systemDate`.
- **Section 6: Đồng bộ hiển thị và tính toán 0 đêm cho phòng Day Use**:
  - Sửa lỗi hiển thị modal "Thông tin đăng ký": Thay `{{ modalForm.nights || 1 }} đêm` thành `{{ Number(modalForm.nights) >= 0 ? modalForm.nights : 0 }} đêm`, chấm dứt tình trạng JS ép `0 || 1` thành `1 đêm`.
  - Cập nhật `decrementNights()` cho phép giảm về `0 đêm` khi ngày đến = ngày đi.
  - Cập nhật `handleNightsChange()`, `handleMainNightsChange()` và input `min="0"` trên header để khi số đêm = 0, ngày đi tự động bằng ngày đến.
  - Sửa `syncBookingDatesFromRooms()` thành `tab.nights = diff >= 0 ? diff : 0`, đảm bảo số đêm của booking tab ngoài header và trong modal khớp chính xác với số đêm = 0 của các dòng phòng Day Use.

## [2026-09-24] - Sửa Section 1, Section 2 và khắc phục lỗi 500 tải danh sách phòng trống (RoomAvailabilityService)
### Module: Đặt phòng (Booking), Phòng trống & WebSocket ([RoomAvailabilityService.php](file:///d:/PMS/backend/app/Services/RoomAvailabilityService.php), [CreateRegistrationPage.vue](file:///d:/PMS/frontend/src/pages/reservation/CreateRegistrationPage.vue), [AppServiceProvider.php](file:///d:/PMS/backend/app/Providers/AppServiceProvider.php), [SafePusherBroadcaster.php](file:///d:/PMS/backend/app/Broadcasting/SafePusherBroadcaster.php), [broadcasting.php](file:///d:/PMS/backend/config/broadcasting.php))

- **Khắc phục lỗi 500 tải phòng trống ([RoomAvailabilityService.php](file:///d:/PMS/backend/app/Services/RoomAvailabilityService.php))**:
  - Tại hàm `isRoomNumberOccupied`, closure `orWhere` kiểm tra trùng phòng Day Use bị thiếu biến `$departureDate` trong khai báo `use (...)`, dẫn đến lỗi `ErrorException: Undefined variable $departureDate at line 232` khi gọi API `/api/rooms/vacant`.
  - Đã bổ sung `$departureDate` vào `use ($arrivalDate, $departureDate, $queryEnd, $arrivalTime, $departureTime)`. API tải phòng trống hoạt động trơn tru 100%.
- **Section 1 & Tối ưu Reverb Broadcaster an toàn (SafePusherBroadcaster)**:
  - Tạo [SafePusherBroadcaster.php](file:///d:/PMS/backend/app/Broadcasting/SafePusherBroadcaster.php) kế thừa PusherBroadcaster, tự động bắt lỗi BroadcastException / Throwable khi tiến trình Reverb server offline, ghi log warning thay vì quăng exception làm sập API request với lỗi HTTP 500.
  - Đăng ký mở rộng driver reverb và pusher với SafePusherBroadcaster trong [AppServiceProvider.php](file:///d:/PMS/backend/app/Providers/AppServiceProvider.php) và cấu hình timeout trong [broadcasting.php](file:///d:/PMS/backend/config/broadcasting.php).
- **Section 2: Chuẩn hóa tính toán Ngày xác nhận (confirm_date)**:
  - Khắc phục lỗi hiển thị dd/mm/yyyy: Trong hàm handleAddTabClick tại [CreateRegistrationPage.vue](file:///d:/PMS/frontend/src/pages/reservation/CreateRegistrationPage.vue), gọi ngay handleConfirmDateCalculation() sau khi gán modalForm.value, đảm bảo ngày xác nhận được tính và hiển thị ngay trên modal tạo mới.
  - Chuẩn hóa công thức tính: Trạng thái Guaranteed gán ngay sysDate; trạng thái có cut off nếu `ngày đến - cutOff < sysDate` thì gán sysDate.
  - Bảo vệ booking đã tạo: Chặn không tính lại ngày xác nhận khi sửa ngày đến của booking đã tạo.

## [2026-09-23] - Chuẩn hóa & khắc phục lỗi 5 Section trong file Các nghiệp vụ liên quan tới booking 2.docx

### Module: Đặt phòng (Booking) & Sơ đồ phòng (Room Map) ([RoomAvailabilityService.php](file:///d:/PMS/backend/app/Services/RoomAvailabilityService.php), [BookingRoomLifecycleService.php](file:///d:/PMS/backend/app/Services/BookingRoomLifecycleService.php), [BookingRoomController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/BookingRoomController.php), [GuestController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/GuestController.php), [HotelSettingController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/HotelSettingController.php), [CreateRegistrationPage.vue](file:///d:/PMS/frontend/src/pages/reservation/CreateRegistrationPage.vue), [BookingDetailModal.vue](file:///d:/PMS/frontend/src/components/BookingDetailModal.vue))



- **Section 4: Sửa lỗi truy vấn check trùng phòng Day Use**:

  - Tại [RoomAvailabilityService.php](file:///d:/PMS/backend/app/Services/RoomAvailabilityService.php), sửa biến so sánh ngày qua đêm từ `$departureDate` thành `$queryEnd` trong closure kiểm tra `isRoomNumberOccupied`.

  - Phát hiện chính xác xung đột khi tạo booking Day Use (ngày đến = ngày đi) trùng phòng với booking qua đêm đến cùng ngày (và ngược lại), ngăn chặn lỗi gán trùng phòng 105.

- **Section 8 & Section 3/12: Bảo toàn đơn giá Extra Bed từng đêm khi đồng bộ ngày phòng**:

  - Tại [BookingRoomLifecycleService.php](file:///d:/PMS/backend/app/Services/BookingRoomLifecycleService.php) và [BookingRoomController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/BookingRoomController.php), giữ nguyên giá `rate` của từng đêm Extra Bed chưa post nếu đã có bản ghi tồn tại; chỉ gán giá phẳng `extra_bed_rate` cho các đêm mới sinh thêm khi kéo dài kỳ lưu trú.

- **Section 17: Sửa lỗi chuẩn hóa ngày đi checkOut trên giao diện inline edit**:

  - Tại [CreateRegistrationPage.vue](file:///d:/PMS/frontend/src/pages/reservation/CreateRegistrationPage.vue), chuẩn hóa cả `r.checkIn` và `r.checkOut` từ định dạng `DD/MM/YYYY` sang `YYYY-MM-DD` khi bấm Sửa và bổ sung xử lý an toàn định dạng trong hàm `validateRoomDatesAgainstBooking`, giải quyết triệt để lỗi báo sai giai đoạn đăng ký khi sửa ngày đi của phòng.

- **Section 16: Ràng buộc giao diện theo thông số SyncRoomDateByBookingDate**:

  - Backend: Bổ sung trả về thông số `SyncRoomDateByBookingDate` trong [HotelSettingController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/HotelSettingController.php).

  - Frontend: Thêm computed `canSyncRoomDates` tại [CreateRegistrationPage.vue](file:///d:/PMS/frontend/src/pages/reservation/CreateRegistrationPage.vue); chỉ hiển thị các ô input sửa ngày đến, ngày đi và số đêm trên thanh tiêu đề khi `SyncRoomDateByBookingDate = 1`. Tên booking được cố định dạng văn bản hiển thị (chỉ sửa qua modal thông tin đăng ký).

- **Section 6: Khóa switch Day Use trên Room Map cho phòng đang ở đến trước ngày hệ thống**:

  - Tại [BookingDetailModal.vue](file:///d:/PMS/frontend/src/components/BookingDetailModal.vue), thêm computed `isDayUseDisabled`: khóa checkbox "Phòng theo giờ" nếu phòng in-house có ngày đến nhỏ hơn Ngày hệ thống (`arrival_date < systemDate`).

  - Khi bật Day Use: tự động gán ngày đi = ngày đến, số đêm = 0, khóa ô chọn ngày đi và gửi thuộc tính `is_day_use` lên backend khi lưu.

  - Tại [GuestController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/GuestController.php), tiếp nhận `is_day_use` và cập nhật `NumOfDays = 0`, `ActutalNumOfDays = 0`.

- **Kiểm thử**:

  - PHP syntax check trên toàn bộ các controller/service đã sửa: 100% không có lỗi.

  - Script test logic `isRoomNumberOccupied`: Đã verify thành công với Day Use và qua đêm cùng ngày.



## [2026-09-23] - Xử lý chuẩn hóa 3 phần nghiệp vụ Khóa phòng (Note 22/09)

### Module: Khóa phòng & Sơ đồ phòng & Thống kê ([RoomLockPermissionService.php](file:///d:/PMS/backend/app/Services/RoomLockPermissionService.php), [RoomLockController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/RoomLockController.php), [RoomController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/RoomController.php), [Room.php](file:///d:/PMS/backend/app/Models/Room.php), [RoomPlanPage.vue](file:///d:/PMS/frontend/src/pages/reservation/RoomPlanPage.vue), [RoomMapPage.vue](file:///d:/PMS/frontend/src/pages/reservation/RoomMapPage.vue))



- **Section 1: Bổ sung ghi chú khóa phòng & Thông số phân quyền RoleUserUnlockRoomOOO/OOS**:

  - Giao diện Kế hoạch phòng: Loại bỏ 2 dòng Tên và Loại khóa phòng trên popover/tooltip, giữ lại phần Ghi chú và bổ sung hiển thị "Người khóa: [username/name]".

  - Sơ đồ phòng (Room Map): Hover vào phòng đang khóa hiển thị tooltip thông tin thời gian, ghi chú và người khóa đồng bộ với Kế hoạch phòng.

  - Phân quyền thông số `RoleUserUnlockRoomOOO/OOS`: Tạo service dùng chung `RoomLockPermissionService` kiểm tra Role/Chức danh của user từ đa nguồn (`user_branch_positions` -> `positions`, `position_branch_roles` -> `roles`, direct `roles`, `job_title`, `job_title_code`, `department`, super admin, v.v.).

  - Áp dụng kiểm tra phân quyền mở khóa trên tất cả các luồng: cả màn hình Khóa phòng (`RoomLockController`) và đổi trạng thái phòng trên Sơ đồ phòng lưới/danh sách (`RoomController@updateStatus`, `bulkUpdateStatus`).

  - Giao diện Cấu hình hệ thống ([HotelConfigTab.vue](file:///d:/PMS/frontend/src/pages/config/components/hotel/HotelConfigTab.vue)): Nâng cấp ô nhập Giá trị cho các thông số phân quyền Role (`RoleUserUnlockRoomOOO/OOS`, `OOORoleUserUnlock`, `OOSRoleUserUnlock`, `RuleUserCorrectOrPostBillPaymentOldDay`,...) từ text thô sang **Dropdown tick chọn vai trò** (multi-select checkbox dropdown kèm tìm kiếm, chọn tất cả/bỏ chọn, tag badge có nút xóa nhanh và thêm mã vai trò tùy biến).

- **Section 2: Cập nhật ngày giờ kết thúc khi mở khóa phòng & Tính toán thống kê phòng trống**:

  - Khi thao tác mở khóa phòng (bao gồm đổi trạng thái phòng trên Sơ đồ phòng), tự động cập nhật `end_date` của bản ghi `room_locks` về `[ngày hệ thống] [giờ thực hiện mở khóa]`, cập nhật `is_active = 2`, `status = 'Done'`, `unlocked_at`, `unlock_username`.

  - Màn hình Kế hoạch phòng và Thống kê: Xử lý theo thông số giờ mở khóa mặc định `FrmOOO_DefineLockByTime` (mặc định `12:00` / `23:59`). Khi phòng khóa qua đêm (từ ngày 10 đến ngày 11 mở khóa lúc 10:41 < 12:00), ngày 10 vẫn tính 1 phòng khóa OOO/OOS, còn ngày 11 không tính phòng khóa.

  - Sửa lỗi hiển thị phòng đã mở khóa trên Kế hoạch phòng ([RoomPlanPage.vue](file:///d:/PMS/frontend/src/pages/reservation/RoomPlanPage.vue)): Khóa đã hoàn tất (`is_active = 2` hoặc `status = 'Done'`) được loại bỏ khỏi lưới Kế hoạch phòng nếu mở trong ngày (không còn chiếm đêm); quan hệ `allActiveLocks` trên [Room.php](file:///d:/PMS/backend/app/Models/Room.php) giữ chuẩn `where('is_active', 1)` để phòng đã mở khóa ngay lập tức biến mất khỏi lưới và hiển thị phòng sẵn sàng (`vacant_ready`). Thống kê OOO lịch sử qua đêm vẫn được tính chuẩn xác qua [RoomAvailabilityService.php](file:///d:/PMS/backend/app/Services/RoomAvailabilityService.php).

- **Section 3: Thứ tự kiểm tra overbooking và thông số AllowLockRoomCauseUnassignableRoomBK**:

  - Chuẩn hóa thứ tự kiểm tra khi tạo/sửa/khóa hàng loạt:

    1. Kiểm tra khóa phòng vật lý trùng lặp (chặn cứng).

    2. Kiểm tra trùng booking trên phòng vật lý (chặn cứng).

    3. Kiểm tra công suất phòng trống AV (`AllowOverRoomTypeRoomKind`) **TRƯỚC**: Nếu giá trị bằng `0` thì chặn cứng không cho phép (không hiện popup hỏi, không cho bypass bằng `force: true`). Nếu bằng `1` thì hiển thị cảnh báo yêu cầu xác nhận.

    4. Kiểm tra phòng trống liên tục cho booking chưa gán số phòng (`AllowLockRoomCauseUnassignableRoomBK`) **SAU**: Nếu giá trị bằng `0` thì chặn cứng không cho phép. Nếu bằng `1` thì hiển thị cảnh báo xác nhận.

- **Kiểm thử**:

  - Backend: `php artisan test --filter=RoomLockTest` đạt 15/15 tests (50 assertions) pass 100%.

  - Frontend: `npm run build` thành công không phát sinh lỗi (4.27s).



## [2026-09-22] - Hoàn thiện Format Tiền Tệ Tự Động & Sửa Nghiệp Vụ Bảng Booking_room_services (Mục 1 - 215-239.docx)

### Module: FrontDesk / Lễ tân & Hóa đơn ([AddServiceModal.vue](file:///c:/xampp/htdocs/PMS/frontend/src/pages/frontdesk/components/AddServiceModal.vue), [AdjustRoomRateModal.vue](file:///c:/xampp/htdocs/PMS/frontend/src/pages/frontdesk/components/AdjustRoomRateModal.vue), [BookingRoomServiceController.php](file:///c:/xampp/htdocs/PMS/backend/app/Http/Controllers/Api/BookingRoomServiceController.php))



## [2026-09-23] - Tối ưu giao diện Yêu cầu đặc biệt (Special Requests) & Xóa bỏ icon ngôi sao vàng

### Module: Thông tin đặt phòng ([BookingDetailModal.vue](file:///d:/PMS/frontend/src/components/BookingDetailModal.vue))



- **Bối cảnh & Vấn đề**:

  - Khối hiển thị Yêu cầu đặc biệt trên modal Thông tin đặt phòng có quá nhiều icon ngôi sao vàng (`★`), tạo cảm giác như đánh giá sao (rating) hoặc VIP thay vì danh sách yêu cầu phòng.

  - Khung bao viền xám kéo dài toàn hàng nhưng tag chỉ chiếm một đoạn nhỏ bên trái, tạo khoảng trống thừa thô cứng và chèn ngang gây đứt đoạn giữa hàng Giá phòng và Thêm giường.

- **Xử lý hoàn thành**:

  - **Xóa bỏ toàn bộ ngôi sao vàng**: Loại bỏ ký tự `★`, icon ngôi sao vàng ở nhãn và icon star trong nút bấm. Thay bằng icon tag/thẻ ghi chú thanh lịch và dot xanh tinh tế.

  - **Bỏ khung hộp xám thô**: Xóa bỏ background và border xám bao quanh. Khối tag tự co giãn tự nhiên theo dạng chip (pill).

  - **Thiết kế lại Chip dạng mềm mại**: Nền xanh pastel nhẹ (`#f0f9ff`), viền mảnh (`#bae6fd`), bo tròn viên thuốc (`border-radius: 9999px`), chữ xanh biển sắc nét.

  - **Bổ sung tính năng gỡ nhanh yêu cầu**: Khi đang ở chế độ Sửa (`isEditingMode`), mỗi tag hiển thị thêm nút `×` nhỏ để người dùng gỡ trực tiếp từng yêu cầu nhanh chóng qua `syncBookingRoomSpecialRequests` mà không cần mở popup.

  - **Tương tác trực quan**: Click vào tag mở ngay modal Yêu cầu đặc biệt để xem/chọn thêm.

- **Kiểm thử**:

  - `npm run build`: Hoàn thành 100% không phát sinh lỗi (4.19s).



## [2026-09-23] - Sửa lỗi lệch ngày đến và số đêm giữa Đặt phòng và Sơ đồ phòng (Room Map)

### Module: Đặt phòng / Sơ đồ phòng ([BookingRoom.php](file:///d:/PMS/backend/app/Models/BookingRoom.php), [BookingController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/BookingController.php), [BookingDetailModal.vue](file:///d:/PMS/frontend/src/components/BookingDetailModal.vue))



- **Bối cảnh & Vấn đề**:

  - Khi booking được dời ngày ở tại màn hình Đăng ký (ví dụ từ `09/08 ~ 10/08` sang `10/08 ~ 11/08`), thông tin phòng trên Sơ đồ phòng khi mở popup Thông tin đặt phòng lại hiển thị ngày đến là `09/08/2026`, ngày đi `11/08/2026`, số đêm bị tính thành 2 đêm và modal Chi tiết thêm giường cũng bị tính thành 2 đêm (`09/08` và `10/08`).

  - Nguyên nhân:

    - Backend: Khi cập nhật ngày booking/phòng, hệ thống chỉ cập nhật bảng `bookings` và `booking_rooms` nhưng thiếu event đồng bộ `actual_arrival_date` cho các khách (`booking_room_guests`), khiến khách bị kẹt ngày cũ `09/08/2026`.

    - Frontend: Hàm `selectGuest()` trong `BookingDetailModal.vue` tự động lấy ngày của khách (`actual_arrival_date`) ghi đè lên ngày đến của phòng, khiến form phòng bị đổi ngày và watcher tính lại thành 2 đêm.

- **Xử lý hoàn thành**:

  - **Đồng bộ Backend Model ([BookingRoom.php](file:///d:/PMS/backend/app/Models/BookingRoom.php))**: Thêm event `updated` cho `BookingRoom` để khi `arrival_date` của phòng thay đổi ở trạng thái `STATUS_BOOKED`, tự động cập nhật `actual_arrival_date` cho tất cả các khách (`guests()`) và trẻ em (`childAssignments()`) của phòng đó.

  - **Đồng bộ API Update ([BookingController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/BookingController.php))**: Đảm bảo cập nhật `actual_arrival_date` và `actual_checkout_date` cho các khách chính và phụ khi phòng chưa check-in.

  - **Ngăn ghi đè Frontend ([BookingDetailModal.vue](file:///d:/PMS/frontend/src/components/BookingDetailModal.vue))**: Bỏ logic ghi đè ngày đến của phòng trong `selectGuest()`. Thông tin lưu trú của phòng luôn giữ nguyên theo ngày phòng (`props.room`).

  - **Chuẩn hóa dữ liệu CSDL**: Cập nhật toàn bộ các khách của Booking GAL4 về đúng ngày đến `10/08/2026` và ngày đi `11/08/2026`.

- **Kiểm thử**:

  - `npm run build`: Hoàn thành 100% không lỗi (4.58s).



## [2026-09-23] - Chuẩn hóa ràng buộc nghiệp vụ Thêm giường (Extra Bed) cho đêm quá khứ bằng Popup ràng buộc

### Module: Đặt phòng / Chi tiết thêm giường ([ExtraBedModal.vue](file:///d:/PMS/frontend/src/pages/reservation/components/ExtraBedModal.vue), [BookingDetailModal.vue](file:///d:/PMS/frontend/src/components/BookingDetailModal.vue))



- **Bối cảnh & Vấn đề**:

  - Khi phòng có đêm thuộc quá khứ (nhỏ hơn Ngày hệ thống), modal thêm giường hiển thị dòng chữ cảnh báo màu vàng ở đáy nhưng form vẫn cho phép chỉnh sửa/thêm giường và lưu thành công, gây mâu thuẫn nghiệp vụ và mâu thuẫn giao diện.

  - Người dùng yêu cầu chuẩn hóa: Khi có thao tác thêm giường vào đêm quá khứ, hệ thống phải hiển thị Popup ràng buộc (thông báo lý do và hướng xử lý) thay vì chỉ để một dòng cảnh báo thụ động ở dưới.

- **Xử lý hoàn thành**:

  - **Khoá chỉnh sửa đêm quá khứ**: Khôi phục lại trạng thái `isLocked = isPastDate || isPosted`. Toàn bộ các đêm quá khứ được hiển thị tag `Quá khứ` và khoá ô nhập số lượng, đơn giá, switch FIT/GIT.

  - **Bảo vệ hàng Total**: Nếu toàn bộ các đêm lưu trú đều là quá khứ (`isAllPastOrLocked`), hàng Total tự động khóa và phủ lớp chặn tương tác.

  - **Hiển thị Popup Ràng Buộc (Constraint Modal)**: Khi người dùng bấm vào ô nhập, nút tăng/giảm hoặc toggle của đêm quá khứ (hoặc dòng Total khi mọi đêm là quá khứ), hệ thống kích hoạt Popup ràng buộc nổi bật ở giữa màn hình (icon tam giác cảnh báo, nội dung: *"Đêm [ngày] thuộc quá khứ (nhỏ hơn Ngày hệ thống [ngày]) không được phép thêm mới/chỉnh sửa Extra Bed. Trường hợp cần phát sinh chi phí quá khứ, vui lòng tạo hóa đơn tại Modun Lễ tân."*, nút bấm *"Đã hiểu"*).

  - **Xóa bỏ dòng cảnh báo màu vàng thụ động** ở đáy modal, giúp giao diện gọn gàng, trực quan và đúng chuẩn UX tương tác.

  - **Bỏ qua đêm quá khứ khi đồng bộ dịch vụ**: Trong `BookingDetailModal.vue`, kiểm tra `if (d.isLocked || d.isPast) continue` trước khi gọi API post dịch vụ, tránh lỗi 422 từ backend.

- **Kiểm thử**:

  - `npm run build`: Hoàn thành thành công 100% không có lỗi (7.04s).



## [2026-09-22] - Hoàn thiện nghiệp vụ Thông tin phòng (BookingDetailModal) theo yêu cầu Section 1 & Section 2

### Module: Thông tin đặt phòng / phòng ([BookingDetailModal.vue](file:///d:/PMS/frontend/src/components/BookingDetailModal.vue), [SingleDatePicker.vue](file:///d:/PMS/frontend/src/components/SingleDatePicker.vue), [GuestController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/GuestController.php), [RoomController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/RoomController.php))



- **Section 1: Gợi ý khách, nhập tay ngày, luồng thêm khách draft và lỗi Extra Bed / Em bé**:

  1. **Gợi ý thông tin khách tại ô Tên khách & Số giấy tờ**:

     - Backend API `searchGuests`: Bổ sung tìm kiếm theo `full_name`, `id_number`, `passport_number`, `phone` kèm đếm số lần lưu trú `stay_count` và tổng doanh thu `total_revenue`. Tìm kiếm nhạy bén ngay từ 1 ký tự nhập vào.

     - Frontend Autocomplete Dropdown: Khi gõ tên hoặc số giấy tờ, hiển thị dropdown đúng định dạng: `[TÊN] - [dd/mm/yyyy] - [SỐ GIẤY TỜ] - [X] BK [- SỐ TIỀN VND]`, tự động highlight từ khóa khớp màu cam; hover màu xanh trời `#38bdf8` chữ trắng. Tách riêng timer debounce cho 2 ô tìm kiếm.

     - Khi chọn khách từ gợi ý: Kế thừa toàn bộ thông tin cá nhân (tên, ngày sinh, cccd, sđt, email, địa chỉ...) vào form mà không thay đổi slot/ID khách hiện tại của phòng ("kế thừa chứ không thay thế").

  2. **Cho phép gõ tay hoặc chọn từ lịch**:

     - Nâng cấp [SingleDatePicker.vue](file:///d:/PMS/frontend/src/components/SingleDatePicker.vue): Thêm thẻ `<input>` cho phép gõ trực tiếp định dạng `dd/mm/yyyy`, `d/m/yyyy`, `dd-mm-yyyy`, `ddmmyyyy`, tự chuẩn hóa sang `YYYY-MM-DD`, blur/enter chuẩn hóa ngày, click icon hoặc input vẫn mở lịch bình thường.

     - Áp dụng thành công cho: Ngày sinh, Ngày phát hành, Ngày đi,...

  3. **Thêm Người lớn / Trẻ em / Em bé theo luồng nháp (Draft)**:

     - Khi bấm `+ Thêm người lớn / trẻ em / em bé`, hệ thống tạo bản ghi draft tạm thời với nhãn badge `(Mới)` trên danh sách khách bên trái, mở form chỉnh sửa và hiển thị banner thông báo hướng dẫn.

     - Người dùng kiểm tra thông tin, có thể chọn kế thừa từ khách cũ, sau đó bấm nút **Lưu** ở header mới hiển thị popup xác nhận và gọi API insert vào CSDL.

     - Nếu bấm **Quay lại** hoặc đổi khách khác: Hủy bản ghi draft, hoàn trả form ban đầu, không gọi API lưu vào CSDL.

  4. **Khắc phục lỗi thêm EB (Em bé & Extra Bed)**:

     - **Em bé (EB)**: Sửa [BookingChild.php](file:///d:/PMS/backend/app/Models/BookingChild.php) gán `status = 1` cho `BookingRoomChild` khi tạo mới, giúp trẻ em / em bé mới tạo không bị loại khỏi query `bookingChildren` khi tải lại phòng.

     - **Extra Bed (EB)**: Sửa lỗi trong [ExtraBedModal.vue](file:///d:/PMS/frontend/src/pages/reservation/components/ExtraBedModal.vue) khi các đêm của phòng in-house bị gán nhầm `isPast` dẫn đến ép số lượng về `0`, dòng Total không áp dụng và khi bấm Lưu bị emit `quantity: 0`. Đã chuyển sang cơ chế `isLocked` (chỉ khóa khi đêm đã post hóa đơn `is_posted == 1`), cho phép nhập/chỉnh sửa đêm in-house bình thường. Đồng bộ cập nhật ngay `pricingInfo.value.extra_bed_qty` và `pricingInfo.value.extra_bed_price` trong [BookingDetailModal.vue](file:///d:/PMS/frontend/src/components/BookingDetailModal.vue).

  5. **Thường trú / Tạm trú**:

     - Nạp danh mục động từ CSDL thông qua API `fetchGuestDefinitions()` (`residence_types`), hiển thị đúng các lựa chọn theo bảng `residence_types` (Thường trú, Tạm trú, Khác) thay vì hardcode.

  6. **Ô Quốc tịch**:

     - Chuyển ô Quốc tịch từ thẻ `<select>` sang input autocomplete searchable (`nationalitySearch`), cho phép gõ tìm kiếm mã/tên quốc gia và hiển thị gợi ý dropdown (`filteredNationalities`) để chọn nhanh.

- **Section 2: Hiển thị danh sách Yêu cầu đặc biệt ra ngoài giao diện**:

  - Sửa lỗi mapping `loadRoomSpecialRequests`: API trả về quan hệ snake_case `special_request` (`item.special_request`), trước đó hàm map đọc `item.specialRequest` (camelCase) khiến `roomSpecialRequests` bị rỗng và không hiển thị ra giao diện.

  - Tải tức thì từ `props.room.special_request_types` và đồng bộ realtime qua API `fetchBookingRoomSpecialRequests(bookingRoomId)`.

  - Hiển thị danh sách các badge yêu cầu đặc biệt đã chọn ra ngoài giao diện ngay dưới nút `[☆ Yêu cầu đặc biệt]` trong khu vực "GIÁ PHÒNG & YÊU CẦU" (khớp Ảnh 2).

  - Cập nhật số lượng tag ngay trên nút `Yêu cầu đặc biệt (X)`, tự động làm mới ngay sau khi lưu từ modal Yêu cầu đặc biệt và đồng bộ hiển thị lên tooltip trên Sơ đồ phòng.

  - Bổ sung đầy đủ các import Vue lifecycle (`ref`, `computed`, `watch`, `onMounted`, `onBeforeUnmount`), `vue-router` (`useRouter`, `useRoute`) và `useUiStore` trong [BookingDetailModal.vue](file:///d:/PMS/frontend/src/components/BookingDetailModal.vue) để khắc phục lỗi `ReferenceError: useRouter is not defined`.

- **Kiểm thử**:

  - Frontend: `npm run build` hoàn thành 100% không có lỗi.

  - Backend: Toàn bộ test suites `RoomMoveTest.php` (14/14 tests), `GuestTest` (41/41 tests), và `tests/Feature/Booking/` (50/50 tests) pass 100%.



## [2026-09-22] - Hoàn thiện 4 Section nghiệp vụ Room Map và CheckInPage theo tài liệu lỗi

### Module: Room Map & Check-In ([RoomMapPage.vue](file:///d:/PMS/frontend/src/pages/reservation/RoomMapPage.vue), [BookingRoomController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/BookingRoomController.php), [RoomMoveModal.vue](file:///d:/PMS/frontend/src/components/RoomMoveModal.vue), [CheckInPage.vue](file:///d:/PMS/frontend/src/pages/reservation/CheckInPage.vue), [RoomMoveTest.php](file:///d:/PMS/backend/tests/Feature/RoomMoveTest.php))



- **Bối cảnh**: Triển khai toàn bộ 4 Section nghiệp vụ từ file đặc tả lỗi Room Map (`Các lỗi liên quan tới Room map.docx`):

  1. **Section 1: Chuyển phòng sang phòng trống - Ràng buộc over phòng theo AllowOverRoomTypeRoomKind**:

     - Kiểm tra AV (khả dụng) của loại phòng đích trong toàn bộ thời gian lưu trú khi chuyển phòng.

     - Nếu chuyển phòng dẫn đến over loại phòng:

       + `AllowOverRoomTypeRoomKind = 0`: Chặn chuyển phòng và báo lỗi: `"Loại phòng đã bị over, không thể chuyển phòng"`.

       + `AllowOverRoomTypeRoomKind = 1`: Hiển thị cảnh báo xác nhận: `"Loại phòng đã bị over, bạn có muốn tiếp tục"` với 2 nút Yes / No. Chọn Yes tiếp tục chuyển phòng; chọn No hủy bỏ thao tác.

  2. **Section 2: Modal Chuyển phòng (Danh sách phòng trống, sắp xếp tự nhiên, chặn phòng bẩn/chờ kiểm tra)**:

     - Danh sách phòng trống khả dụng: Hiển thị đầy đủ các phòng có giai đoạn trống kể cả khi ở tình trạng bẩn (`vacant_dirty`, `turndown`) hoặc chờ kiểm tra (`vacant_clean`).

     - Sắp xếp cột phòng theo thứ tự tự nhiên (natural sort: 101, 102, 103, 1002...).

     - Khi chọn chuyển sang phòng bẩn: Khi bấm Lưu báo lỗi `"Phòng đang trong tình trạng phòng bẩn, không thể chuyển phòng "` và chặn chuyển.

     - Khi chọn chuyển sang phòng chờ kiểm tra (`vacant_clean`): Chỉ cho chuyển vào phòng Sẵn sàng (`vacant_ready`); nếu chọn phòng chờ kiểm tra thì báo lỗi `"Phòng đang trong tình trạng chờ kiểm tra, không thể chuyển phòng "` và chặn chuyển.

  3. **Section 3: Danh sách phòng đến/đi/ở (CheckInPage)**:

     - Bổ sung 2 cột mới cho cả 2 bảng dữ liệu (Bảng phòng chưa đến / chưa trả và Bảng phòng đã đến / đang ở / đã trả):

       + Cột `NL/TE/EB`: Số lượng người lớn / trẻ em / extra bed cho cả cấp booking và cấp phòng (`adults/children/extra_beds`).

       + Cột `Yêu cầu ĐB`: Hiển thị yêu cầu đặc biệt của phòng và booking.

     - Cập nhật colspan bảng trống tương ứng khi ở chế độ đến (12 cột) và chế độ trả phòng (15 cột).

  4. **Section 4: Phím tắt đổi trạng thái trên Sơ đồ phòng (RoomMapPage)**:

     - Khắc phục lỗi khi mở các modal (Thông tin, Chuyển phòng, Chi tiết, Khóa phòng...) bấm phím số 1, 2, 3... làm nhảy popup đổi tình trạng phòng.

     - Chặn toàn bộ phím tắt số khi có bất kỳ modal nào đang mở trên Sơ đồ phòng.

- **Kiểm thử & Xác thực**:

  - Backend tests: Bổ sung 4 test cases trong [RoomMoveTest.php](file:///d:/PMS/backend/tests/Feature/RoomMoveTest.php), 14/14 tests pass 100%; `RoomMoveSameDayNightTest.php` 2/2 tests pass; toàn bộ 50/50 test cases `tests/Feature/Booking/` pass 100%.

  - Frontend build: `npm run build` hoàn thành 100% không lỗi.



## [2026-09-22] - Bổ sung hiển thị icon tình trạng phòng trong các danh sách CheckInPage (Đã đến / Đã đi / Đang ở)

### Module: Đặt phòng / Check-in ([CheckInPage.vue](file:///d:/PMS/frontend/src/pages/reservation/CheckInPage.vue))



- **Yêu cầu**: Đối với các phòng đã gán số phòng trong màn hình CheckInPage (Đã đến, Đã đi, Đang ở...), hiển thị thêm icon tình trạng phòng (Sạch, Bẩn, OOO, OOS, DND,...) bên cạnh số phòng.

- **Xử lý hoàn thành**:

  - Import [RoomIcon.vue](file:///d:/PMS/frontend/src/components/RoomIcon.vue).

  - Hoàn thiện mapping `getRoomStatusIcon(room)`, `getRoomStatusIconClass(room)` và `getRoomStatusTooltip(room)` tra cứu từ danh sách phòng vật lý `roomStore.rooms` (kèm fallback `room.room`).

  - Hỗ trợ đầy đủ các trạng thái: Sẵn sàng, Chờ kiểm tra (Sạch), Chưa dọn (Bẩn), OOO, OOS, DND, dịch vụ dọn phòng, ưu tiên dọn,... kèm màu sắc chuẩn và tooltip tiếng Việt khi hover.

  - Gắn `<RoomIcon>` hiển thị cạnh `room.room_number` ở cả 2 bảng dữ liệu (Bảng phòng chưa đến / chưa trả và Bảng phòng đã đến / đang ở / đã trả).

  - Khai báo bổ sung `const canCancelCheckIn = ref(false)` để sửa lỗi `ReferenceError: canCancelCheckIn is not defined` khi chuyển màn hình từ Sơ đồ phòng.

- **Kiểm thử**:

  - `npm run build`: Hoàn thành 100% không có lỗi.



## [2026-09-21] - Sửa nghiệp vụ Hủy nhận phòng (Undo Check-In): Chặn khi có dịch vụ/cọc, cho phép khi đã hủy/chuyển, chuẩn hóa giao diện xác nhận

### Module: Sơ đồ phòng & Phòng đã đến ([BookingRoomController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/BookingRoomController.php), [UndoCheckInValidationTest.php](file:///d:/PMS/backend/tests/Feature/UndoCheckInValidationTest.php), [RoomMapPage.vue](file:///d:/PMS/frontend/src/pages/reservation/RoomMapPage.vue), [CheckInPage.vue](file:///d:/PMS/frontend/src/pages/reservation/CheckInPage.vue))



- **Nghiệp vụ & Lỗi gốc rễ khách phản ánh ("Ràng thiếu điều kiện")**:

  - Khi check-in phòng -> phát sinh bill/thanh toán/thanh toán trước -> hệ thống trước đây không kiểm tra đầy đủ bill/cọc còn hiệu lực hay đã bị hủy/chuyển.

  - Sau khi người dùng chuyển bill sang phòng khác hoặc hủy bill/cọc, query cũ vẫn kiểm tra `orWhere('RentalRoomId1', $bookingRoom->id)` và quét Master Folio (`RegisterID2 = booking->id`).

  - Do cơ chế `quickTransfer`/`transferFolio` nhân bản bill (`replicate()`) giữ nguyên `RentalRoomId1` là ID phòng gốc trong khi `RentalRoomId2` là phòng đích, câu query cũ match phải dòng bill mới trên phòng đích khiến phòng gốc bị chặn oan dù đã sạch hóa đơn.

- **Xử lý hoàn thành**:

  - **Backend (`BookingController.php` - phương thức `undoCheckIn`)**:

    + Kiểm tra chỉ cho phép hủy nhận phòng cho các phòng vừa check-in trong ngày (`check_in_date === system_date`).

    + Tối ưu kiểm tra hóa đơn dịch vụ (`$hasServiceBills`):

      * Chỉ xét các bill đang gắn vào phòng hiện tại qua cột sở hữu chính thức `RentalRoomId2 = $bookingRoom->id` (kèm fallback legacy khi cả `RentalRoomId2` và `RegisterID2` rỗng/null và `RentalRoomId1 = $bookingRoom->id`).

      * Bắt buộc kiểm tra `COALESCE(Edit, 0) = 0` và loại trừ các trạng thái đã hủy/chuyển `whereNotIn('Status', [3, 4])`.

      * Không quét nhầm Master Folio hay các bill đã được chuyển sang phòng khác (`RentalRoomId2 != $bookingRoom->id`).

    + Tối ưu kiểm tra thanh toán / cọc (`$hasPayments`):

      * Chỉ xét bản ghi gắn với phòng `booking_room_id = $bookingRoom->id`, `edit_flag = 0`, `deleted_at IS NULL`, `status != STATUS_DELETED`.

    + Trả về thông báo lỗi chuẩn nghiệp vụ khi vi phạm:

      `"Hủy nhận phòng không thành công, phòng đã phát sinh dịch vụ hoặc đặt cọc. Vui lòng kiểm tra lại thông tin"`.

  - **Frontend (`RoomMapPage.vue` & `CheckInPage.vue`)**:

    + Chuẩn hóa modal xác nhận hủy nhận phòng:

      * Câu hỏi: `"Vui lòng chọn tình trạng phòng sau khi thực hiện \"Hủy nhận phòng\""`.

      * 2 nút lựa chọn: **Dơ** (`dirty`), **Chờ kiểm tra** (`clean`).

      * Bỏ nút "Đóng" (người dùng đóng bằng nút `[X]` góc trên modal).

    + Frontend chỉ cho phép thực hiện hủy nhận phòng khi phòng check-in trong ngày hôm nay (`systemDate`).

- **Kiểm thử**:

  - Backend tests: Bổ sung 3 test cases trong [UndoCheckInValidationTest.php](file:///d:/PMS/backend/tests/Feature/UndoCheckInValidationTest.php) (chuyển bill sang phòng khác, chuyển cọc sang phòng khác, chuyển bill sang Master Folio). Chạy toàn bộ 9/9 tests pass 100% (25 assertions).

  - Test tương thích [CheckoutBusinessRulesTest.php](file:///d:/PMS/backend/tests/Feature/CheckoutBusinessRulesTest.php): 10/10 tests pass.

  - Frontend build: `npm run build` hoàn thành 100% không có lỗi.



## [2026-09-21] - Sửa lỗi tính năng Chuyển cọc (Khắc phục che mất khúc dưới, lọc đúng phòng đang ở, bỏ dòng Toàn bộ phòng)

### Module: Đặt phòng / Đặt cọc ([DepositModal.vue](file:///d:/PMS/frontend/src/pages/reservation/components/DepositModal.vue))



- **Nguyên nhân**:

  - Giao diện modal chuyển cọc cũ bị nhúng bên trong modal cha có CSS `transform: translate(...)`, kích thước input hẹp (`max-w-[260px]`), danh sách dropdown absolute tràn ra ngoài mép dưới modal dẫn đến bị che khuất phần đáy trên màn hình/cửa sổ nhỏ.

  - Gọi API tải booking có giới hạn cứng `limit: 100` và lọc `status: '0,1'` (thiếu `status: 4`), đồng thời chặn tìm kiếm lại khi `searchResults` đã có dữ liệu làm thiếu nhiều booking so với màn hình Hóa đơn.

  - Computed `transferOptions` cho phép cả phòng ở trạng thái Đăng ký (`status = 0`) chưa check-in / chưa gán số phòng, dẫn đến hiển thị dòng "Chưa xếp | Khách chưa đặt tên".

  - Tự động sinh thêm dòng `type: 'room'` hiển thị `[Số phòng] | Toàn bộ phòng` cho từng phòng gây dư thừa.

- **Xử lý hoàn thành**:

  - **Khắc phục che mất khúc dưới & Đóng dropdown khi click ngoài**:

    + Bọc Modal Chuyển cọc trong `<Teleport to="body">` với `z-[2000000]` và đặt modal ở vị trí cao hơn (`pt-20 items-start`) để có khoảng trống lớn phía dưới cho dropdown mở thoải mái.

    + Thêm logic tự động phát hiện vị trí `checkDropdownPlacement`: Nếu khoảng cách phía dưới nhỏ hơn 230px, dropdown sẽ tự động mở lật ngược lên trên (`openUpwards: bottom-full mb-1.5`), tuyệt đối không bao giờ bị cắt chân hay che mất khúc dưới.

    + Bổ sung listener `pointerdown` toàn cục đóng dropdown ngay lập tức khi người dùng click ra ngoài ô input/dropdown mà không cần phải đóng modal.

    + Giữ nguyên form chuẩn xác như giao diện người dùng yêu cầu: có icon mũi tên `⌄` xoay khi đóng mở, ô xóa nhanh `✕`.

  - **Khắc phục danh sách phòng & booking bị thiếu**:

    + Đồng bộ với màn hình Hóa đơn: Gọi `fetchBookings({ status: '0,1,4' })` bỏ giới hạn `limit: 100` để lấy toàn bộ booking hiện hành.

    + Bổ sung debounce tự động gọi server tìm kiếm khi người dùng nhập từ khóa tìm kiếm mà bộ lọc cục bộ chưa có.

  - **Lọc chuẩn phòng Đang ở & bỏ dòng "Toàn bộ phòng"**:

    + Bỏ hoàn toàn việc tạo dòng `type: 'room'` ("Toàn bộ phòng").

    + Chỉ hiển thị các phòng ĐANG Ở (`Number(room.status) === 1`), chưa checkout và đã có số phòng thực tế (`room_number`).

    + Loại bỏ hoàn toàn các phòng trạng thái Đăng ký (`status = 0`).

    + Tên khách hiển thị format chuẩn: `[Số phòng] | [Tên khách]`, có fallback chuẩn xác sang `room.guest_name`, `booking.booking_name`, `booking.guest_name`, `booking.contact_name`, không bao giờ hiển thị "Khách chưa đặt tên" hay "Chưa xếp".

  - **Căn giữa màn hình & Cho phép kéo di chuyển modal (Draggable)**:

    + Căn giữa modal Chuyển đặt cọc theo trục ngang và dọc (`items-center justify-center p-4`) như các modal chuẩn khác.

    + Thêm tính năng kéo di chuyển tự do (`cursor-move select-none`, `startDragTransferModal`, `transferModalPos`) tại thanh tiêu đề (header) của modal chuyển cọc.

    + Khi di chuyển hoặc mở dropdown, tự động tính toán lại vị trí `checkDropdownPlacement` để dropdown bung lên trên (`openUpwards`) nếu sát đáy màn hình.

- **Kiểm thử**:

  - `npm run build`: Hoàn thành thành công 100% (0 lỗi).



## [2026-09-21] - Đồng bộ tính toán và hiển thị tiền phòng quá khứ theo hóa đơn thực tế (service_bills)

### Module: Đặt phòng / Tạo đăng ký ([CreateRegistrationPage.vue](file:///d:/PMS/frontend/src/pages/reservation/CreateRegistrationPage.vue), [BookingController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/BookingController.php), [CheckoutPage.vue](file:///d:/PMS/frontend/src/pages/frontdesk/CheckoutPage.vue))





- **Yêu cầu (Mục 1 - 215-239.docx & Bảng booking_room_services)**:

  - Tự động thêm dấu phẩy `,` ngăn cách hàng nghìn và cho phép dấu chấm `.` thập phân khi người dùng nhập số tiền (Đơn giá dịch vụ, Tiền phòng tự nhập, Giá phòng điều chỉnh).

  - Khắc phục lỗi lưu sai bảng `booking_room_services`: Chỉ lưu khi thay đổi giá phòng theo đêm ở màn hình booking (`RM`) hoặc thêm dịch vụ bổ sung ở booking (phục vụ night audit tự động chạy sang ngày). Khi post bill phát sinh trực tiếp tại màn hình Lễ tân (FO), Buồng phòng (HK) hoặc Phụ thu thì KHÔNG được insert vào `booking_room_services` làm sai lệch tiền booking.

- **Đã hoàn thành**:

  - Viết hàm `formatInputCurrency` và `parseInputCurrency` chuyên dụng xử lý bóc tách linh hoạt phần nguyên và phần thập phân, bảo toàn vị trí con trỏ nhập liệu (`selectionStart/selectionEnd`).

  - Áp dụng vào:

    - [AddServiceModal.vue](file:///c:/xampp/htdocs/PMS/frontend/src/pages/frontdesk/components/AddServiceModal.vue): Tab Dịch vụ (`unitPriceDisplay` / `onUnitPriceInput`) và Tab Tiền phòng (`customRoomRateDisplay` / `onCustomRoomRateInput`).

    - [AdjustRoomRateModal.vue](file:///c:/xampp/htdocs/PMS/frontend/src/pages/frontdesk/components/AdjustRoomRateModal.vue): Ô Giá phòng (`rateDisplay` / `onRateInput`).

  - Chuẩn hóa cờ `is_room: props.bookingRoomId ? 1 : 0` khi thêm bill FO tại Master Folio.

  - Loại bỏ các lệnh `BookingRoomService::create` không đúng nghiệp vụ trong [BookingRoomServiceController.php](file:///c:/xampp/htdocs/PMS/backend/app/Http/Controllers/Api/BookingRoomServiceController.php) tại:

    1. `postHousekeepingBill`: Không insert vào `booking_room_services` khi post bill buồng phòng (minibar, giặt là).

    2. `postFoServiceBill`: Chỉ cập nhật `service_bill_id` nếu có sẵn dịch vụ đặt trước (`existingBrs`), không insert dòng mới vào `booking_room_services`.

    3. `postRoomCharge`: Không insert `ER` vào `booking_room_services` khi chọn chế độ phụ thu tiền phòng (`mode = 'surcharge'`).

  - Kiểm tra build frontend `npm run build` và backend route list thành công.



## [2026-09-21] - Phân tích đặc tả kỹ thuật Báo cáo Dòng 158, 161, 164 (Báo cáo Thu ngân lễ tân, Doanh thu hai giai đoạn, Dự kiến doanh thu tiền phòng)

### Module: Tài liệu phân tích báo cáo ([ROW_158_161_164_COMPREHENSIVE_SPECIFICATION.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/reports/ROW_158_161_164_COMPREHENSIVE_SPECIFICATION.md), [dong_158_bao_cao_thu_ngan_le_tan.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/dong_158_bao_cao_thu_ngan_le_tan.md), [dong_161_bao_cao_doanh_thu_hai_giai_doan.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/dong_161_bao_cao_doanh_thu_hai_giai_doan.md), [dong_164_bao_cao_du_kien_doanh_thu_tien_phong.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/dong_164_bao_cao_du_kien_doanh_thu_tien_phong.md))



- **Bối cảnh & Yêu cầu**:

  - Đọc và phân tích sâu các dòng 158, 161, 164 từ file Excel `DANH MỤC BÁO CÁO.xlsx`.

  - Đọc đúng Stored Procedure chỉ định:

    - **Dòng 158**: `sp_039` của **Navy** (`ProVistaNavyHotel.dbo.sp_039`), Sheet 63 `BC thu ngân`.

    - **Dòng 161**: `sp_217` theo **Army** (`ProVistaArmyHotel.dbo.sp_217`), Sheet 4 `Báo cáo doanh thu hai giai đoạn`.

    - **Dòng 164**: `sp_095` (`ProVistaArmyHotel.dbo.sp_095`), Sheet 2 `Báo cáo dự kiến doanh thu tiền`.

  - Trích xuất ảnh UI thực tế: `dong_158_ui_mau.png`, `dong_161_ui_mau.png`, `dong_164_ui_mau.png`, `dong_164_popup_ui_mau.png`.

  - Trích xuất và bóc tách các view, hàm, bảng legacy: `vw_004`, `vw_003`, `vw_025`, `vw_044`, `vw_031`, `vw_030`, `func_031`, `SP2102`, `SP3000`, `SP3002`, `SP3003`.

  - Lập tài liệu đặc tả cặn kẽ khép kín gồm đầy đủ thông số `content_json` (blocks, columns, grouping, customRows, footer, static tables), Stored Procedure MySQL 8.0, mapping cơ sở dữ liệu và hướng dẫn kiểm thử.

- **Đã hoàn thành**:

  - **Dòng 158 - Báo cáo thu ngân lễ tân (`sp_039` Navy)**:

    - Bóc tách Store Navy `sp_039`: Xử lý phòng cho khách lẻ qua `#tempKhachLe`, quy tắc che số thẻ `CD` giữ 4 chữ số cuối.

    - Cấu hình tham số bộ phận lọc: `Report_ListDepartmentCashierShiftReport` (`FO,FB,MR,ACC`), mặc định `FO`, hỗ trợ đa chọn.

    - Đặc tả bố cục A4 Landscape gồm 3 bảng: Bảng 1 chi tiết giao dịch (11 cột, grouping 2 cấp Loại & HTTT, subtotal từng cấp), Bảng 2 phân bổ tiền tệ (6 cột), Bảng 3 tổng hợp công nợ công ty (4 cột) và 3 chữ ký chân trang.

    - Viết hoàn chỉnh PHP reference template `reception_cashier_shift_reference.php` và Stored Procedure MySQL 8.0 `rpt_reception_cashier_shift`.

  - **Dòng 161 - Báo cáo doanh thu hai giai đoạn (`sp_217` Army)**:

    - Bóc tách logic hai giai đoạn: Lọc dịch vụ có Tháng/Năm phát sinh khác Tháng/Năm thanh toán hóa đơn: `((MONTH(sb.service_date) <> MONTH(inv.invoice_date)) OR (YEAR(sb.service_date) <> YEAR(inv.invoice_date)))`.

    - Thực hiện 2 điểm nâng cấp bắt buộc theo yêu cầu của Army:

      1. Bổ sung cột "Ngày dịch vụ" (`DateHDDV`) sau cột "Tên khách", lấy từ `service_bills.service_date` (`SP3000.Date`).

      2. Bộ lọc dịch vụ cho phép chọn nhiều cùng lúc (`multi-select` qua `FIND_IN_SET`).

    - Bóc tách công thức tính thuế phí theo `vw_044` (`OriginalRate`, `ServiceChargeAmount`, `SpecialTaxAmount`, `TaxAmount`).

    - Viết hoàn chỉnh PHP reference template `two_period_revenue_reference.php` và Stored Procedure MySQL 8.0 `rpt_two_period_revenue`.

  - **Dòng 164 - Báo cáo dự kiến doanh thu tiền phòng (`sp_095`)**:

    - Xác định vị trí nghiệp vụ: Nút bấm `Báo cáo dự kiến doanh thu tiền phòng` tại màn hình **Sang Ngày (Night Audit)** (`dong_164_ui_mau.png`) mở Popup in xem trước (`dong_164_popup_ui_mau.png`).

    - Bóc tách logic dự kiến doanh thu phòng đang ở (`status IN (0, 1)`) gồm: Tiền phòng (`RM`) theo bảng giá ngày (`booking_room_rates` / `func_031`) + Dịch vụ cố định hàng ngày (`ServiceId <> 'RM'`) từ `booking_room_daily_services` (`SP2102`).

    - Bố cục bảng in 11 cột có tổng cộng doanh thu tiền phòng và dịch vụ cố định trong đêm audit.

    - Viết hoàn chỉnh PHP reference template `expected_room_revenue_reference.php` và Stored Procedure MySQL 8.0 `rpt_expected_room_revenue_night_audit`.

  - **Tài liệu bàn giao**:

    - [dong_158_bao_cao_thu_ngan_le_tan.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/dong_158_bao_cao_thu_ngan_le_tan.md)

    - [dong_161_bao_cao_doanh_thu_hai_giai_doan.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/dong_161_bao_cao_doanh_thu_hai_giai_doan.md)

    - [dong_164_bao_cao_du_kien_doanh_thu_tien_phong.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/dong_164_bao_cao_du_kien_doanh_thu_tien_phong.md)

    - [ROW_158_161_164_COMPREHENSIVE_SPECIFICATION.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/reports/ROW_158_161_164_COMPREHENSIVE_SPECIFICATION.md)



## [2026-09-21] - Phân tích đặc tả kỹ thuật Báo cáo Dòng 150, 151, 152 (Báo cáo Doanh thu, Doanh thu theo ngày đi, Doanh thu lễ tân Army)

### Module: Tài liệu phân tích báo cáo ([ROW_150_151_152_COMPREHENSIVE_SPECIFICATION.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/reports/ROW_150_151_152_COMPREHENSIVE_SPECIFICATION.md), [dong_150_bao_cao_doanh_thu_army.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/dong_150_bao_cao_doanh_thu_army.md), [dong_151_bao_cao_doanh_thu_dang_ky_theo_ngay_di.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/dong_151_bao_cao_doanh_thu_dang_ky_theo_ngay_di.md), [dong_152_bao_cao_doanh_thu_le_tan_army.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/dong_152_bao_cao_doanh_thu_le_tan_army.md))



- **Bối cảnh & Yêu cầu**:

  - Đọc và phân tích sâu các dòng 150, 151, 152 từ file Excel `DANH MỤC BÁO CÁO.xlsx`.

  - Trích xuất ảnh chụp UI mẫu legacy thực tế từ các Sheet 73, Sheet 67, Sheet 74.

  - Trích xuất định nghĩa Stored Procedure gốc từ MS SQL Server (SSMS `.\MSSQLSERVER01`): `sp_292`, `sp_238`, `sp_240`, `sp_293`.

  - Lập tài liệu đặc tả cặn kẽ từ kiến trúc đa chi nhánh, mapping cơ sở dữ liệu, code chuyển đổi Stored Procedure MySQL 8.0, định nghĩa tham số bộ lọc Designer (`parameter_ui_schema`), ma trận cột nhiều tầng, customRows và hướng dẫn kiểm thử chi tiết để Agent mới chưa có thông tin có thể triển khai độc lập ngay lập tức.

- **Đã hoàn thành**:

  - **Dòng 150 - Báo cáo doanh thu (Army Quy Nhơn) (`sp_292`)**:

    - Trích xuất ảnh UI mẫu `dong_150_ui_mau.png` và SQL gốc `sp_292_full.sql`.

    - Đặc tả layout A4 Landscape, 22 cột, header 2 tầng, tổng hợp 7 nhóm doanh thu trong ngày, doanh thu ngày trước, phân bổ hình thức thanh toán (TM, CK, HH, Còn nợ) và số dư phòng còn ở.

    - Cấu hình toàn bộ mã nguồn PHP reference template `revenue_army_reference.php` gồm 100% thông số `content_json`: 22 cột trong `columns()`, khối header thông tin khách sạn/ngày in, bảng động có `topHeader` 13 ô gộp, hàng `customRows` Grand Total gồm nhãn `"Tổng số BK: {{aggregate.rows.count}}"` và 15 binding sums `aggregate.rows.sum.*`, khối chân trang 5 chữ ký quân đội.

  - **Dòng 151 - Báo cáo doanh thu đăng ký theo ngày đi (`sp_238` & `sp_240`)**:

    - Trích xuất ảnh UI mẫu `dong_151_ui_mau.png` và SQL gốc `sp_238_full.sql` (chi tiết phòng), `sp_240_full.sql` (nhóm theo đăng ký).

    - Đặc tả layout A4 Landscape, 24 cột, header 3 tầng (FO, Housekeeping, F&B Revenue - Nhà hàng, Doanh thu khác).

    - Tích hợp công tắc chuyển đổi `p_group_by_booking` (0: chi tiết phòng, 1: nhóm theo đăng ký).

    - Cấu hình toàn bộ mã nguồn PHP reference template `revenue_by_departure_date_reference.php` gồm 100% thông số `content_json`: 24 cột trong `columns()`, khối header thời gian đến phút, bảng động có `topHeader` ma trận đa tầng (FO colspan 7, HK colspan 4, F&B colspan 4, Nhà hàng), hàng `customRows` Grand Total gồm nhãn `"Total"` và 17 binding sums `aggregate.rows.sum.*` cho tất cả cột doanh thu, khối chữ ký 3 cột.

  - **Dòng 152 - Báo cáo doanh thu lễ tân_army (`sp_293`)**:

    - Trích xuất ảnh UI mẫu `dong_152_ui_mau.png` và SQL gốc `sp_293_full.sql`.

    - Đặc tả layout 11 cột với 2 cấp nhóm phân tầng (Cấp 1: Nhóm doanh thu, Cấp 2: Dịch vụ) kèm bảng tổng hợp phụ 2 cột ở chân trang.

    - Cấu hình toàn bộ mã nguồn PHP reference template `reception_revenue_army_reference.php` gồm 100% thông số `content_json`: 11 cột trong `columns()`, bảng động có `grouping` 2 cấp (`RevenueGroupName` và `ServiceId`), hàng `customRows` Subtotal 2 cấp (`scope: group` level 1 và level 0) + Grand Total (`scope: table`), khối bảng tĩnh `summary_revenue_box_table` (`tableType: static`) hiển thị tổng hợp Doanh thu phòng, Doanh thu dịch vụ và Tổng cộng, khối 5 chữ ký quân đội.

  - **Bóc tách chi tiết các hàm, view và bảng legacy trong Stored Procedure**:

    - **Hàm `func_054` (Dòng 150 - `sp_292`)**: Bóc tách chi tiết bản chất của Table-Valued Function tính doanh thu cốt lõi legacy (~35KB, 583 dòng), cấu trúc dữ liệu trả về 14 cột (`RentalRoomId`, `BookingId`, `BillIdService`, `ServiceId`, `Date`, `Total`, `RoomRateCode`, `DepartmentId`...), cách `sp_292` gọi để tách doanh thu trong ngày và ngày trước (`PrevDay`), và phương án thay thế tối ưu bằng CTE query trực tiếp bảng `sales_invoices` trên MySQL 8.0.

    - **Các bảng trong Dòng 151 (`sp_238` & `sp_240`)**: Bóc tách vai trò của `SP8060`/`SP8058`/`SP8059` (cấu hình template), `SP3000`/`SP3001` (hóa đơn & chi tiết hóa đơn), `SP2100` (thời gian trả phòng thực tế `CheckoutDate` + `CheckoutTime`), `sp1326` (hình thức miễn phí cần loại trừ) và bảng đối chiếu sang MySQL mới.

    - **View `vw_018` (Dòng 152 - `sp_293`)**: Bóc tách nguồn gốc View tổng hợp đa bảng (`SP3000`, `SP2100`, `SP2200`, `SP2300`, `SP1306`, `SP3003`, `SP6000`, `SP5000`), công thức bóc tách thuế phí `OriginalRate`, `ServiceChargeAmount`, `TaxAmount`, bảng cấu hình nhóm dịch vụ `SP1610` (`FORevenueReport`), bảng ngôn ngữ `SP1602` và bảng đối chiếu sang MySQL mới.

  - **Tài liệu tổng hợp và lưu trữ**:

    - Tạo tài liệu tổng quan toàn diện: [ROW_150_151_152_COMPREHENSIVE_SPECIFICATION.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/reports/ROW_150_151_152_COMPREHENSIVE_SPECIFICATION.md).

    - Tạo các tài liệu chi tiết độc lập theo chuẩn thư mục `doc_baocao/`: [dong_150_bao_cao_doanh_thu_army.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/dong_150_bao_cao_doanh_thu_army.md), [dong_151_bao_cao_doanh_thu_dang_ky_theo_ngay_di.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/dong_151_bao_cao_doanh_thu_dang_ky_theo_ngay_di.md), [dong_152_bao_cao_doanh_thu_le_tan_army.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/dong_152_bao_cao_doanh_thu_le_tan_army.md).

    - Lưu trữ tập trung 3 file ảnh mẫu tại `.codex/docs/doc_baocao/images/` và 4 file SQL Stored Procedure gốc tại `.codex/docs/doc_baocao/sql/`.



## [2026-09-18] - Bổ sung hàng tổng (customRows) trên Canvas Form Designer cho 4 báo cáo (Dòng 155, 156, 162, 163)

### Module: Form Designer & Biểu mẫu Báo cáo ([unpaid_service_bills_reference.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/report_templates/unpaid_service_bills_reference.php), [room_rate_statistics_reference.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/report_templates/room_rate_statistics_reference.php), [daily_frontdesk_reference.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/report_templates/daily_frontdesk_reference.php), [cancelled_invoices_payments_reference.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/report_templates/cancelled_invoices_payments_reference.php), [2026_09_18_173500_sync_report_custom_rows_designer.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/migrations/2026_09_18_173500_sync_report_custom_rows_designer.php))



- **Nguyên nhân**:

  - Trình thiết kế mẫu Form Designer (`TemplateEditorModal.vue`) hiển thị các hàng tổng phụ và tổng cộng trên canvas dựa vào mảng `block.customRows`.

  - Trong `content_html` đã có sẵn `<tr class="pms-group-footer">` và `<tr class="report-grand-total-row">`, nhưng trong `blocks()` (`content_json`) của các báo cáo chưa được khai báo cấu hình mảng `customRows` tương ứng, dẫn tới việc chỉ hiển thị nút `+ Thêm hàng` mà thiếu các hàng tổng trên canvas.

- **Đã hoàn thành**:

  - **Bổ sung `customRows` vào `blocks()` của 4 file tham chiếu biểu mẫu**:

    1. **BÁO CÁO HÓA ĐƠN DỊCH VỤ CHƯA THANH TOÁN (`unpaid_service_bills_reference.php`)**: Cấu hình 2 hàng customRows (`subtotal_row` scope `group`, `grand_total_row` scope `table`) khớp 15 cột với nhãn `Tổng doanh thu theo dịch vụ` / `TỔNG CỘNG`, công thức tính tổng 4 cột tiền `OriginalRate`, `ServiceChargeAmount`, `TaxAmount`, `TienQDTD`.

    2. **BÁO CÁO THỐNG KÊ MÃ GIÁ PHÒNG (`room_rate_statistics_reference.php`)**: Cấu hình 2 hàng customRows khớp 12 cột với nhãn `Tổng`, công thức `NumOfDays`, `group.count` / `aggregate.rows.count` (số phòng), `Adults`, `Children`.

    3. **BÁO CÁO LỄ TÂN HẰNG NGÀY (`daily_frontdesk_reference.php`)**: Cấu hình 2 hàng customRows khớp 9 cột với nhãn `Tổng`, công thức tính tổng các cột phòng và khách ăn sáng.

    4. **BÁO CÁO HỦY HÓA ĐƠN / THANH TOÁN (`cancelled_invoices_payments_reference.php`)**: Cấu hình 2 hàng customRows khớp 12 cột với nhãn `Tổng` / `Tổng Giai Đoạn`, công thức tính tổng 2 cột tiền âm (`AmountAm`) và tiền dương (`AmountDuong`).

  - **Tạo migration đồng bộ**:

    - [2026_09_18_173500_sync_report_custom_rows_designer.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/migrations/2026_09_18_173500_sync_report_custom_rows_designer.php): Đồng bộ `content_json` và `content_html` vào bảng `templates` trên cả 5 kết nối database (`mysql`, `mysql_hkt1` đến `mysql_hkt4`).

  - **Kiểm thử**:

    - Chạy PHP syntax check `php -l`: Đạt 100% không lỗi cú pháp.

    - Chạy feature test cho cả 4 báo cáo: `UnpaidServiceBillsReportTest` (4/4 passed), `RoomRateStatisticsReportTest` (4/4 passed), `DailyFrontdeskReportTest` (4/4 passed), `CancelledInvoicesPaymentsReportTest` (3/3 passed).



## [2026-09-18] - Chuẩn hóa toàn bộ thông số thiết kế 4 báo cáo (Dòng 155, 156, 162, 163) từ cấu hình Design

### Module: Báo cáo thống kê & Biểu mẫu Design ([cancelled_invoices_payments_reference.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/report_templates/cancelled_invoices_payments_reference.php), [daily_frontdesk_reference.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/report_templates/daily_frontdesk_reference.php), [unpaid_service_bills_reference.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/report_templates/unpaid_service_bills_reference.php), [room_rate_statistics_reference.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/report_templates/room_rate_statistics_reference.php), [2026_09_18_160000_sync_four_reports_perfect_visual_design.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/migrations/2026_09_18_160000_sync_four_reports_perfect_visual_design.php))



- **Bối cảnh & Yêu cầu**:

  - Chuẩn hóa toàn bộ thông số hiển thị trực quan (cỡ chữ, màu sắc, padding, margin, border, căn lề, tỷ lệ cột, tiêu đề nhóm, dòng tổng phụ và dòng tổng cộng) của 4 báo cáo (Dòng 155: Huỷ hóa đơn/thanh toán, Dòng 156: Lễ tân hằng ngày, Dòng 162: HĐ dịch vụ chưa thanh toán, Dòng 163: Thống kê mã giá phòng) theo đúng ảnh chụp thực tế hệ thống cũ (`dong_155_ui_mau.png`, `dong_156_ui_mau.png`, `dong_162_ui_mau.png`, `dong_163_ui_mau.png`).

  - Toàn bộ thông số hiển thị được cấu hình trực tiếp từ Design (`content_json`, `content_html`, `css`, lề trang) trong database `templates`, không hardcode vào logic xử lý dữ liệu hay controller.

- **Đã hoàn thành**:

  - **Dòng 155 (`CANCELLED_INVOICES_PAYMENTS`)**:

    - Header bảng 2 tầng nền `#d9deea`, viền `#aeb5c0`.

    - Nhóm Ngày màu ĐỎ `#d32f2f` in đậm; Nhóm Bộ phận chữ đen đậm.

    - Cột `Mã ĐK/Phòng` màu XANH LÁ `#2e7d32` in đậm căn giữa.

    - Dòng `Tổng` nền trắng; Dòng `Tổng Giai Đoạn` nền `#d9deea`, nhãn căn giữa in đậm, số căn phải.

    - Khối 3 chữ ký `FOM`, `ACC`, `GM` căn giữa cách đều, margin-top 35px.

  - **Dòng 156 (`DAILY_FRONTDESK`)**:

    - Header 9 cột nền `#d9deea`, viền `#aeb5c0`.

    - Dòng nhóm ngày: Cột 1 chữ `Ngày` màu ĐỎ `#d32f2f` in đậm căn giữa; Cột 2 ngày tháng màu ĐỎ `#d32f2f` in đậm căn giữa; các cột còn lại ô trống có viền.

    - 4 dòng phân khúc: **Tất cả 6 cột số lượng đều CĂN GIỮA** (in-house, check-in, check-out, tổng phòng, ăn sáng, không ăn sáng).

    - Dòng `Tổng` ngày nền trắng, nhãn căn giữa, số căn giữa.

    - Dòng `Tổng` toàn báo cáo nền `#d9deea`, nhãn căn giữa, số căn giữa.

  - **Dòng 162 (`UNPAID_SERVICE_BILLS`)**:

    - Bảng 15 cột nền header `#d9deea`, viền `#aeb5c0`.

    - Cột `Mã ĐK` xanh lá `#2e7d32` in đậm căn giữa; cột `Tổng` in đậm căn phải.

    - Dòng `Tổng doanh thu theo dịch vụ` nền trắng, nhãn căn phải ở cột 8; 4 cột tiền in đậm căn phải.

    - Dòng tổng cộng toàn báo cáo nền `#d9deea`.

  - **Dòng 163 (`ROOM_RATE_STATISTICS`)**:

    - Bảng 12 cột nền header `#d9deea`, viền `#aeb5c0`.

    - Cột `Mã ĐK` xanh lá `#2e7d32` in đậm căn giữa.

    - Tất cả các cột `Đêm`, `Phòng`, `Người Lớn`, `Trẻ Em`, `Loại Phòng`, `Mã Giá Phòng` đều CĂN GIỮA.

    - Dòng `Tổng` subtotal nền trắng, nhãn căn phải cột 6; 4 cột số lượng căn giữa in đậm.

    - Dòng `Tổng` toàn báo cáo nền `#d9deea`, nhãn căn phải cột 6; 4 cột số lượng căn giữa in đậm.

  - **Database & Migration**:

    - Tạo migration [2026_09_18_160000_sync_four_reports_perfect_visual_design.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/migrations/2026_09_18_160000_sync_four_reports_perfect_visual_design.php) đồng bộ trực tiếp `content_json`, `content_html`, `css` và lề trang vào bảng `templates` trên toàn bộ 5 kết nối database chi nhánh (`mysql`, `mysql_hkt1`, `mysql_hkt2`, `mysql_hkt3`, `mysql_hkt4`).

- **Kiểm thử & Xác thực**:

    - Migrate hoàn tất 100% trên 5 database branch.

    - Feature tests: `CancelledInvoicesPaymentsReportTest`, `DailyFrontdeskReportTest`, `UnpaidServiceBillsReportTest`, `RoomRateStatisticsReportTest`, `TemplateRendererServiceTest` pass 100%.

    - Frontend node tests: Đạt 14/14 tests.

    - Frontend production build: Thành công 100% (9.60s).



## [2026-09-18] - Triển khai Báo cáo HĐ dịch vụ chưa thanh toán (Dòng 162) & Thống kê mã giá phòng (Dòng 163)

### Module: Báo cáo doanh thu & Báo cáo thống kê ([UNPAID_SERVICE_BILLS](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/report_templates/unpaid_service_bills_reference.php), [ROOM_RATE_STATISTICS](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/report_templates/room_rate_statistics_reference.php))



- **Bối cảnh & Nghiệp vụ**:

  - Dòng 162: `Báo cáo hóa đơn dịch vụ chưa thanh toán` (`UNPAID_SERVICE_BILLS`), chuyển đổi từ legacy `sp_046`, lọc các bill dịch vụ chưa thanh toán (`Edit = 0`, `PaymentId IS NULL`) trong kỳ.

  - Dòng 163: `Báo cáo thống kê mã giá phòng` (`ROOM_RATE_STATISTICS`), chuyển đổi từ legacy `sp_287`, thống kê phòng và số đêm phát sinh lưu trú trong kỳ nhóm theo từng mã giá phòng (`RateCode`).

- **Đã hoàn thành**:

  - **Dòng 162 (`UNPAID_SERVICE_BILLS`)**:

    - Tạo migration [2026_09_18_130000_create_unpaid_service_bills_report.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/migrations/2026_09_18_130000_create_unpaid_service_bills_report.php): khởi tạo Stored Procedure `rpt_unpaid_service_bills`, đồng bộ Data Source `RPT_UNPAID_SERVICE_BILLS`, Template `UNPAID_SERVICE_BILLS_REFERENCE` và Report Definition trên cả 5 database branch (`mysql`, `mysql_hkt1` đến `mysql_hkt4`).

    - Tạo template [unpaid_service_bills_reference.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/report_templates/unpaid_service_bills_reference.php): layout A4 Landscape, 15 cột, gom nhóm theo `ServiceId`, tính tổng phụ theo dịch vụ và tổng cộng toàn báo cáo.

    - Tạo feature test [UnpaidServiceBillsReportTest.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/tests/Feature/UnpaidServiceBillsReportTest.php).

  - **Dòng 163 (`ROOM_RATE_STATISTICS`)**:

    - Bổ sung lookup `rate-codes` vào [ReportLookupController.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/app/Http/Controllers/Api/ReportLookupController.php) lấy danh sách từ `room_rate_codes`.

    - Tạo migration [2026_09_18_140000_create_room_rate_statistics_report.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/migrations/2026_09_18_140000_create_room_rate_statistics_report.php): khởi tạo Stored Procedure `rpt_room_rate_statistics`, đồng bộ Data Source `RPT_ROOM_RATE_STATISTICS`, Template `ROOM_RATE_STATISTICS_REFERENCE` và Report Definition trên cả 5 database branch.

    - Tạo template [room_rate_statistics_reference.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/report_templates/room_rate_statistics_reference.php): layout A4 Landscape, 12 cột, gom nhóm theo `RateCode`, subtotal tính tổng đêm, đếm số phòng, tổng người lớn và trẻ em.

    - Tạo feature test [RoomRateStatisticsReportTest.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/tests/Feature/RoomRateStatisticsReportTest.php).

- **Kiểm thử & Xác thực**:

  - Chạy migrate thành công 100% trên cả 5 kết nối database chi nhánh.

  - Feature tests: `UnpaidServiceBillsReportTest` và `RoomRateStatisticsReportTest` pass toàn bộ (66 assertions).

  - Frontend production build: Thành công 100% (`built in 14.64s`).



## [2026-09-18] - Phân tích đặc tả kỹ thuật Báo cáo Dòng 155 và 156 (Thư mục .codex/docs/doc_baocao)

### Module: Tài liệu phân tích báo cáo ([README.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/README.md), [dong_155_bao_cao_huy_hoa_don_thanh_toan.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/dong_155_bao_cao_huy_hoa_don_thanh_toan.md), [dong_156_bao_cao_le_tan_hang_ngay.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/dong_156_bao_cao_le_tan_hang_ngay.md))



- **Bối cảnh**:

  - Đọc và trích xuất thông tin từ file Excel `DANH MỤC BÁO CÁO.xlsx` tại dòng 155 (Báo cáo hủy hóa đơn/thanh toán) và dòng 156 (Báo cáo lễ tân hàng ngày).

  - Trích xuất ảnh UI chụp màn hình hệ thống legacy từ Sheet 51 và Sheet 71.

  - Dump trực tiếp toàn bộ mã nguồn Stored Procedure gốc từ MS SQL Server (`sp_068`, `sp_070`, `sp_275`).

- **Đã hoàn thành**:

  - Tạo cấu trúc thư mục chuẩn mực tại `C:\Users\Nguyen Tho Thang\OneDrive\Desktop\PMS\PMS\.codex\docs\doc_baocao`:

    - `README.md`: Hướng dẫn tổng quan, bảng so sánh và quy trình triển khai cho Agent kế tiếp.

    - `dong_155_bao_cao_huy_hoa_don_thanh_toan.md`: Đặc tả chi tiết 2 chế độ Huỷ hoá đơn (`sp_068`) và Huỷ thanh toán (`sp_070`), layout 11 cột, header 2 tầng, gom nhóm 2 cấp (Ngày, Bộ phận), logic đối trừ bản ghi âm/dương.

    - `dong_156_bao_cao_le_tan_hang_ngay.md`: Đặc tả chi tiết báo cáo lễ tân hằng ngày (`sp_275`), layout 9 cột, gom nhóm theo Ngày, cố định 4 phân khúc khách (TA, OTA, Corp, Walk-in/FIT/Fanpage), công thức tính số phòng check in/out/inhouse và số suất ăn sáng ngày tiếp theo.

    - `images/`: Chứa 2 ảnh UI mẫu thực tế `dong_155_ui_mau.png` và `dong_156_ui_mau.png`.

    - `sql/`: Chứa mã nguồn SQL gốc đầy đủ `sp_068_full.sql`, `sp_070_full.sql`, `sp_275_full.sql`.



## [2026-09-18] - Chuẩn hóa toàn bộ thuộc tính lề và kích thước báo cáo lấy trực tiếp từ Form Designer

### Module: Render biểu mẫu báo cáo ([TemplateRendererService.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/app/Services/TemplateRendererService.php), [TemplateRendererServiceTest.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/tests/Unit/TemplateRendererServiceTest.php), [sales_invoices_reference.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/report_templates/sales_invoices_reference.php))





- **Bối cảnh & Nguyên nhân**:

  - Người dùng đã cấu hình lề trang (`margin_top: 10`, `margin_bottom: 7`, `margin_left: 5`, `margin_right: 5`) trong Form Designer, nhưng khi hiển thị báo cáo trên web và preview, nội dung vẫn dính sát 100% vào mép trang giấy.

  - Template `sales_invoices_reference.php` và dữ liệu trong database tồn tại đoạn CSS tĩnh `body { margin: 0; padding: 0; }` ghi đè toàn bộ padding của `body`.

  - Khối CSS bảo vệ cuối cùng trong `TemplateRendererService::buildFullHtmlDocument` chỉ khóa `width: 100% !important; max-width: none !important;` mà chưa khóa các thông số lề trang (`padding-top/bottom/left/right`) và `box-sizing: border-box !important;`.

- **Đã hoàn thành**:

  - **Khóa quyền ưu tiên tuyệt đối cho lề trang từ Designer (`TemplateRendererService.php`)**:

    - Bổ sung `padding-top: {$marginTop}mm !important;`, `padding-bottom: {$marginBottom}mm !important;`, `padding-left: {$marginLeft}mm !important;`, `padding-right: {$marginRight}mm !important;` và `box-sizing: border-box !important;` vào khối CSS ưu tiên cuối cùng.

    - Tại `@media print`: thiết lập `body { padding: 0 !important; }` để nhường quyền cho `@page { margin: ... }` quản lý lề trang in vật lý chuẩn xác.

  - **Dọn dẹp CSS xung đột**:

    - Loại bỏ `margin: 0; padding: 0;` trong khối `body` của [sales_invoices_reference.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/report_templates/sales_invoices_reference.php).

    - Cập nhật trực tiếp cột `css` của mẫu `SALES_INVOICES_REFERENCE` trong bảng `templates` trên cả 5 kết nối database chi nhánh (`mysql`, `mysql_hkt1` đến `mysql_hkt4`).

- **Kiểm thử & Xác thực**:

  - PHPUnit test [TemplateRendererServiceTest.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/tests/Unit/TemplateRendererServiceTest.php): 12/12 passed (44 assertions).

  - Feature test `SalesInvoicesReportTest`: 6/6 passed (56 assertions).

  - Frontend test: 14/14 passed.

  - Frontend production build: Thành công 100% (4.68s).



## [2026-09-18] - Khắc phục lỗi Form Designer không thụt lề khi nhập Margin Right và các thông số kích thước

### Module: Cấu hình báo cáo / Form Designer ([TemplateEditorModal.vue](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/frontend/src/pages/config/components/hotel/TemplateEditorModal.vue), [TemplateEditorModalBlockStyling.test.js](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/frontend/src/pages/config/components/hotel/TemplateEditorModalBlockStyling.test.js))



- **Nguyên nhân gốc rễ**:

  - Người dùng nhập số nguyên thuần túy (ví dụ: `20`, `40`) vào ô `Margin Right`, giá trị lưu thành chuỗi `"20"`. Trong CSS, `margin-right: 20` thiếu đơn vị (`px`) nên trình duyệt tự động loại bỏ.

  - Theo chuẩn CSS Box Model (Section 10.3.3): Một phần tử block hoặc table khi có `width: 100%`, tổng chiều rộng đã chiếm toàn bộ container, nên `margin-right` không làm phần tử co lại từ mép phải mà sẽ bị reset về `0` hoặc tràn ra ngoài overflow.

  - Trên Canvas WYSIWYG, thẻ card bao quanh khối (`div.group/block` và `div.group/subblock`) chưa được gắn `:style="getCanvasBlockCardStyle(b)"`. Style trước đó bị đẩy xuống thẻ con bên trong (`table`), trong khi thẻ con lại có class `w-full` và nằm trong container `overflow-x-auto`, dẫn đến việc cả khối lẫn bảng con đều không thụt vào khi chỉnh margin-right.

- **Đã hoàn thành**:

  - **Chuẩn hóa đơn vị kích thước CSS (`normalizeCssDimension`)**: Tự động chuyển đổi số thuần (vd `20`, `40.5`) thành `20px`, bảo toàn các đơn vị hợp lệ (`%`, `mm`, `pt`, `auto`).

  - **Tự động quy đổi độ rộng khi có lề (`resolveBlockStyles`)**:

    - Khi khối có `marginLeft` hoặc `marginRight` và `width` để trống hoặc `100%`, tự động tính toán `width: calc(100% - ${mr})` hoặc `calc(100% - ${ml} - ${mr})`.

    - Thiết lập `boxSizing: 'border-box'` đảm bảo viền và lề không làm vỡ kích thước layout.

  - **Cập nhật hiển thị Canvas (`getCanvasBlockCardStyle` & `getBlockStyle(b, true)`)**:

    - Thẻ card của khối (`div.group/block`) và subblock (`div.group/subblock`) trên cả 3 band (Header, Detail, Footer) được gắn `:style="getCanvasBlockCardStyle(b)"` để phản ánh trực quan ngay lập tức các margin, width, height trên canvas.

    - Các phần tử con bên trong card (`table`, `div[type=text]`, `shape`) sử dụng `getBlockStyle(b, true)` loại trừ margin ngoài để không bị nhân đôi lề.

  - **Cập nhật ô nhập Right Panel**:

    - Bổ sung sự kiện `@blur` tự động chuẩn hóa đơn vị CSS (`normalizeCssDimension`) khi người dùng nhập xong và rời chuột khỏi ô nhập `Padding`, `Margin`, `Width`, `Height`.

    - Bổ sung placeholder trực quan hướng dẫn định dạng (`0px, 20, 5%...`, `100%, 55%, 300px...`).

- **Kiểm thử & Xác thực**:

  - Tạo bộ test mới [TemplateEditorModalBlockStyling.test.js](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/frontend/src/pages/config/components/hotel/TemplateEditorModalBlockStyling.test.js): 14/14 tests passed (100%).

  - Chạy `npm run build` trên `frontend/`: Thành công 100% trong 5.05s.

  - Backend feature tests: Đạt 100%.



## [2026-09-18] - Triển khai Báo cáo hóa đơn bán hàng (SALES_INVOICES - sp_094 legacy)

### Module: Báo cáo thống kê lễ tân ([sales_invoices_reference.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/report_templates/sales_invoices_reference.php), [SalesInvoicesDataAdapter.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/app/Services/Reports/SalesInvoicesDataAdapter.php), [ReportDatasetEnricher.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/app/Services/Reports/ReportDatasetEnricher.php), [2026_09_18_100000_create_sales_invoices_report.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/migrations/2026_09_18_100000_create_sales_invoices_report.php), [sales_invoices.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/reports/sales_invoices.md))



- **Bối cảnh & Nghiệp vụ**:

  - Triển khai báo cáo dòng 153 trong `DANH MỤC BÁO CÁO.xlsx`, Sheet 66 `BC HĐ bán hàng`.

  - Đối chiếu logic từ Stored Procedure `sp_094` và hàm `func_021` trên SQL Server SSMS (`ProVistaDTXHotel`).

  - Đối chiếu giao diện và bộ lọc thực tế từ ảnh chụp màn hình legacy `sheet66_image73.png`.

- **Đã hoàn thành**:

  - **Cơ sở dữ liệu & Migration**:

    - Bổ sung các cột `nullable()` vào bảng `sales_invoices`: `booking_id`, `rental_room_id`, `payment_id`, `company_id`, `guest_name`, `original_rate`, `service_charge_amount`, `special_tax`, `tax`, `discount`, `department`, `pack1`.

    - Tạo Stored Procedure `rpt_sales_invoices` hỗ trợ 9 tham số, phân tách tiền thanh toán vào 6 cột chi tiết (`Cash`, `Card`, `Voucher`, `City`, `DPCash`, `DPCard`), hỗ trợ lọc VATNo theo `p_export_type`.

    - Chạy migration `2026_09_18_100000` thành công trên cả 5 kết nối database chi nhánh (`mysql`, `mysql_hkt1` đến `mysql_hkt4`).

    - Đăng ký `report_data_sources` (`RPT_SALES_INVOICES`), `report_definitions` (`SALES_INVOICES`), `templates` (`SALES_INVOICES_REFERENCE`), và liên kết `report_definition_template`.

    - Cấu hình chuẩn `parameter_ui_schema` 5 control: Chọn ngày, Chọn bộ phận, Chọn công ty, Chọn người dùng, Xem Theo.

  - **Data Adapter & Template**:

    - Tạo `SalesInvoicesDataAdapter`: Định dạng ngày `dd/mm/yyyy`, chuẩn hóa tên khách `BK ...`, tính toán tự động 3 dòng cho `Bảng Phân Bổ Tiền Tệ` (`Bank transfer/ Chuyển khoản`, `Cash/ Tiền mặt`, `Credit Card/ Cà thẻ`) và dòng tổng theo công ty.

    - Cập nhật an toàn `ReportDatasetEnricher` chỉ bổ sung nhánh điều kiện độc lập cho `SALES_INVOICES` và `RPT_SALES_INVOICES` (Zero side-effects).

    - Tạo `sales_invoices_reference.php`: Khổ ngang A4 landscape, header 2 tầng, gom nhóm ngày, dòng tổng phụ `Số lượng:` và `Tổng của Ngày`, dòng tổng toàn bảng, và bảng phân bổ tiền tệ 55% căn giữa.

  - **Kiểm thử & Xác thực**:

    - `SalesInvoicesReportTest.php`: Đạt 5/5 tests (56 assertions, 1 skipped do sqlite).

    - Kiểm thử procedure và rendering thực tế trên MySQL runtime: Tạo HTML chuẩn 100% không lỗi.

    - `VipGuestsReportTest.php`: 3/3 passed (68 assertions).

    - `SharedReportLayoutTest.php`: 2/2 passed (4 assertions).

    - `npm run build`: Frontend build thành công 100% trong 7.92s.

    - Tạo tài liệu đầy đủ tại `.codex/docs/reports/sales_invoices.md`.

  - **Chuẩn hóa chỉ số thuộc tính trong Form Designer**:

    - Bổ sung tường minh các thuộc tính `marginTop`, `marginBottom`, `marginLeft`, `marginRight`, `paddingTop`, `paddingBottom`, `paddingLeft`, `paddingRight` cho tất cả các block.

    - Sửa bảng phụ tiền tệ từ CSS shorthand `margin: 0 auto` sang `marginLeft: auto`, `marginRight: auto`, `marginBottom: 14px`, `borderWidth: 1px`, `borderColor: #aeb5c0` để Form Designer hiển thị chuẩn xác từng ô thuộc tính.

    - Bổ sung `headerStyle` (màu nền `#d9deea`, viền `#aeb5c0`, padding `3px 4px`, font `9.5px`) và `cellStyle` (viền `#aeb5c0`, padding `3px 4px`, font `9.5px`) cho 14 cột bảng chính và 4 cột bảng phân bổ tiền tệ.

    - Đồng bộ khóa `groups` để Form Designer nhận diện và hiển thị trực quan dòng gom nhóm Ngày kèm chữ "Ngày:" xanh lá `#2e7d32`.

    - Đồng bộ lại toàn bộ dữ liệu mẫu template mới trên cả 5 kết nối database (`mysql`, `mysql_hkt1` đến `mysql_hkt4`).

  - **Cải tiến & Hoàn thiện Form Designer ([TemplateEditorModal.vue](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/frontend/src/pages/config/components/hotel/TemplateEditorModal.vue))**:

    - Khôi phục và nâng cấp tính năng chọn ô bảng Detail Table:

      - Click chọn ô bất kỳ trong bảng Detail Table (tiêu đề cột `header`, ô dữ liệu `detail`, ô nhóm `group-cell`, ô tổng/tùy chỉnh `custom-cell`) ở cả 3 band (Header, Detail, Footer).

      - Tô viền đen đậm chuẩn mực (`outline: 2px solid #000000; outline-offset: -2px; box-shadow: inset 0 0 0 2px #000000;`).

      - Hỗ trợ giữ phím `Ctrl`/`Command` để chọn nhiều ô cùng lúc (multi-select).

      - Tự động nạp thuộc tính ô/cột lên cả thanh Floating Toolbar và Bảng Thuộc Tính (Right Panel) để chỉnh sửa trực tiếp: In đậm, In nghiêng, Gạch chân, Cỡ chữ, Căn lề, Màu chữ, Màu nền, Tiêu đề cột/Nội dung ô.

    - Bổ sung ô nhập `Chiều rộng (Width)` và `Chiều cao (Height)` trên Bảng Thuộc Tính (Right Panel) giúp chủ động điều chỉnh độ rộng khối theo phần trăm (`100%`, `55%`) hoặc pixel (`300px`), tránh hiểu nhầm do margin.

    - Cập nhật mẫu [sales_invoices_reference.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/report_templates/sales_invoices_reference.php) cho khối `sales_invoices_allocation_table` lên độ rộng `width: 100%`, `marginLeft: 0px`, `marginRight: 0px` và đồng bộ thành công vào DB của tất cả các chi nhánh.

    - Kiểm thử tự động:

      - `TemplateEditorModalDetailTable.test.js`: 4/4 passed (100%).

      - `TemplateEditorModalPageLayout.test.js`: 5/5 passed (100%).

      - `SalesInvoicesReportTest.php`: 5/5 passed.

      - `npm run build`: Build frontend thành công 100% không lỗi.



## [2026-09-17] - Chuẩn hóa cỡ chữ (fontSize: 9px) và padding ô trong content_json Báo cáo dự kiến khách ăn sáng

### Module: Cấu hình báo cáo & Báo cáo phòng ([expected_breakfast_summary_reference.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/report_templates/expected_breakfast_summary_reference.php), [expected_breakfast_army_summary_reference.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/report_templates/expected_breakfast_army_summary_reference.php), [expected_breakfast_dtx_summary_reference.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/report_templates/expected_breakfast_dtx_summary_reference.php), [expected_breakfast_detail_reference.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/report_templates/expected_breakfast_detail_reference.php), [2026_09_17_183000_align_expected_breakfast_templates_with_legacy_design.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/migrations/2026_09_17_183000_align_expected_breakfast_templates_with_legacy_design.php))



- **Bối cảnh & Vấn đề**:

  - `content_json` trước đó chưa thiết lập thuộc tính `fontSize` rõ ràng ở cấp block bảng, tiêu đề cột (`headerStyle`), ô dữ liệu (`cellStyle`), nhóm (`headerCells`) và hàng tổng (`customRows`).

  - Khi không khai báo `fontSize`, Form Designer và trình duyệt dùng cỡ chữ mặc định (13px–16px) khiến chữ trong bảng bị to quá so với hệ thống cũ.

  - Padding ô trước đó đặt `6px 8px`, làm chiều cao mỗi dòng tăng lên ~32px (chuẩn legacy là padding `4px 4px`, chiều cao dòng ~18–20px).

- **Đã hoàn thành**:

  - Cấu hình đồng bộ `fontSize: '9px'`, padding `4px 4px` vào `content_json` của 4 template reference:

    - Block bảng chính: `style: { width: '100%', fontSize: '9px', borderCollapse: 'collapse' }`.

    - Tất cả các cột: `headerStyle.fontSize: '9px'`, `cellStyle.fontSize: '9px'`, padding `4px 4px`.

    - Tất cả header cells của nhóm: `style.fontSize: '9px'`.

    - Toàn bộ custom rows (tổng phụ và tổng cộng): `style.fontSize: '9px'`, padding `4px 4px`.

    - Bảng thống kê theo quốc gia: `style.fontSize: '9px'`, `columns` header/cell `fontSize: '9px'`.

    - Khối chữ ký: `fontSize: '11px'`.

  - Cập nhật database:

    - Chạy migration `2026_09_17_183000` đồng bộ `content_json` mới vào bảng `templates` trên cả 5 connection (`mysql`, `mysql_hkt1`, `mysql_hkt2`, `mysql_hkt3`, `mysql_hkt4`).

  - Kiểm thử & xác thực:

    - Kiểm tra `check_all_font_sizes.php`: 100% 5 connection và cả 3 mẫu đều đạt `fontSize: 9px` và `padding: 4px 4px`.

    - Backend unit test: `ExpectedBreakfastReportTest.php` đạt 7/7 passed.

    - Frontend build: `npm run build` thành công 100%.



## [2026-09-17] - Khắc phục lỗi Form Designer làm mất kiểu dáng và màu sắc Báo cáo dự kiến khách ăn sáng khi lưu phiên bản

### Module: Cấu hình báo cáo & Báo cáo phòng ([TemplateEditorModal.vue](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/frontend/src/pages/config/components/hotel/TemplateEditorModal.vue), [expected_breakfast_summary_reference.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/report_templates/expected_breakfast_summary_reference.php), [expected_breakfast_army_summary_reference.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/report_templates/expected_breakfast_army_summary_reference.php), [expected_breakfast_dtx_summary_reference.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/report_templates/expected_breakfast_dtx_summary_reference.php), [expected_breakfast_detail_reference.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/report_templates/expected_breakfast_detail_reference.php), [2026_09_17_183000_align_expected_breakfast_templates_with_legacy_design.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/migrations/2026_09_17_183000_align_expected_breakfast_templates_with_legacy_design.php))



- **Bối cảnh & Nguyên nhân**:

  - Khi người dùng vào Form Designer xem/sửa mẫu báo cáo ăn sáng và bấm "Lưu phiên bản", hệ thống tái biên dịch HTML từ `content_json` qua hàm `compileHtml()`.

  - Trước đó, các thuộc tính style (`backgroundColor: '#dee2ed'`, `border: '1px solid #cbd5e1'`, `color: '#b82c2c'`, `color: '#1976d2'`, độ rộng 70% căn giữa cho bảng quốc gia) chỉ nằm ở chuỗi HTML tĩnh ban đầu mà chưa được cấu hình chi tiết vào `content_json` (`headerStyle`, `cellStyle`, `headerCells`, `customRows[].cells[].style`, `style`).

  - Hàm `compileBlockToHtml` trong `TemplateEditorModal.vue` khi sinh thẻ `<table>` chưa gắn `class` (`tableClassName`).

  - Do đó, khi lưu lại phiên bản, HTML tái tạo bị mất màu nền header và bảng thống kê quốc gia bị tràn 100% thay vì 70% căn giữa.

- **Đã hoàn thành**:

  - **Sửa file dùng chung `TemplateEditorModal.vue`** (đã được user phê duyệt):

    - Bổ sung `class="${b.tableClassName || b.className || ''}"` vào thẻ `<table>` khi biên dịch block `table`.

  - **Cấu hình toàn diện thuộc tính Design vào `content_json` trong 4 Template Providers**:

    - `headerStyle`: `{ backgroundColor: '#dee2ed', border: '1px solid #cbd5e1', textAlign: 'center', fontWeight: 'bold' }`.

    - `cellStyle`: `{ border: '1px solid #cbd5e1', textAlign: 'center' }` (cột chuỗi căn `left`).

    - `headerCells`: cấu hình cho nhóm `DateGroup` (chữ "Ngày :" đỏ `#b82c2c`, ngày đen, viền) và `RoomType` (chữ xanh `#1976d2` in hoa, viền).

    - `customRows`: cấu hình `style` nền `#dee2ed`, viền `1px solid #cbd5e1` cho toàn bộ các ô hàng tổng `date_subtotal` và `report_total`.

    - Bảng thống kê quốc gia: cấu hình `style: { width: '70%', marginLeft: 'auto', marginRight: 'auto' }`, header và cells nền `#dee2ed`, viền `1px solid #cbd5e1`.

    - Khối chữ ký: 2 cột `Bộ Phận FO` và `Bộ Phận F&B` in đậm căn giữa.

  - **Đồng bộ Database Migration**:

    - Cập nhật migration `2026_09_17_183000_align_expected_breakfast_templates_with_legacy_design.php` và chạy cập nhật thành công trên cả 5 database chi nhánh (`mysql`, `mysql_hkt1` đến `mysql_hkt4`).

  - **Kiểm thử**:

    - Kiểm tra tái biên dịch Form Designer: HTML giữ nguyên 100% màu nền `#dee2ed`, viền `#cbd5e1`, chữ đỏ `#b82c2c`, chữ xanh `#1976d2`, hàng tổng, và bảng quốc gia có `width: 70%; margin: auto`.

    - Backend unit test: `ExpectedBreakfastReportTest.php` đạt 7/7 tests (84 assertions).

    - Frontend production build: `npm run build` thành công 100% trong 5.93s.



## [2026-09-17] - Chuẩn hóa toàn bộ thông số UX/UI Báo cáo dự kiến khách ăn sáng theo hệ thống cũ vào Form Designer (bảng templates)

### Module: Báo cáo phòng / Báo cáo ăn sáng ([expected_breakfast_summary_reference.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/report_templates/expected_breakfast_summary_reference.php), [expected_breakfast_army_summary_reference.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/report_templates/expected_breakfast_army_summary_reference.php), [expected_breakfast_dtx_summary_reference.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/report_templates/expected_breakfast_dtx_summary_reference.php), [expected_breakfast_detail_reference.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/report_templates/expected_breakfast_detail_reference.php), [2026_09_17_183000_align_expected_breakfast_templates_with_legacy_design.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/migrations/2026_09_17_183000_align_expected_breakfast_templates_with_legacy_design.php), [ExpectedBreakfastReportTest.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/tests/Unit/Reports/ExpectedBreakfastReportTest.php), [expected_breakfast/README.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/expected_breakfast/README.md))



- **Đã hoàn thành**:

  - **Phân tích giao diện pixel-level từ file ảnh legacy**:

    - Trích xuất và đối chiếu 5 hình ảnh thực tế từ `DANH MỤC BÁO CÁO.xlsx`: `BC_dự_kiến_khách_AS_1.png` đến `BC_dự_kiến_khách_AS_4.png` và `BC_dự_kiến_khách_AS_LT_1.png`.

    - Xác định toàn bộ thông số chuẩn: Khổ A4 Portrait, lề 6mm/6mm/8mm/8mm, màu nền header và tổng `#dee2ed`, viền `1px solid #cbd5e1`.

    - Tiêu đề nhóm `DateGroup` chữ "Ngày :" màu đỏ `#b82c2c`, ngày màu đen; nhóm `RoomType` chữ xanh dương `#1976d2` in hoa, in đậm; nhóm `DetailRoom` (mẫu chi tiết) chữ xanh dương `#1976d2` in đậm.

    - Căn lề số lượng khách căn giữa (`center`).

    - Hàng tổng hiển thị số phòng ở Cột 2 (`{{group.count}}` cho hàng tổng ngày, `{{aggregate.rows.count|number}}` cho hàng tổng cộng).

    - Bổ sung khối chữ ký chân trang (`Bộ Phận FO` và `Bộ Phận F&B`) in đậm căn giữa, margin-top 35px.

  - **Cập nhật Template Providers**:

    - Chuẩn hóa cấu trúc blocks, HTML, CSS và metadata lề cho cả 3 mẫu: `EXPECTED_BREAKFAST_ARMY_SUMMARY`, `EXPECTED_BREAKFAST_DTX_SUMMARY`, `EXPECTED_BREAKFAST_DETAIL`.

    - Khắc phục mẫu chi tiết hiển thị số phòng `{{row.Room}}` ở cột Phòng của dòng chi tiết.

  - **Lưu toàn bộ cấu hình vào Form Designer (Database Migration)**:

    - Tạo migration [2026_09_17_183000_align_expected_breakfast_templates_with_legacy_design.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/migrations/2026_09_17_183000_align_expected_breakfast_templates_with_legacy_design.php).

    - Chạy migrate thành công trên cả 5 kết nối database chi nhánh (`mysql`, `mysql_hkt1` đến `mysql_hkt4`).

    - Không hardcode bất kỳ giá trị style/màu sắc nào trong code xử lý backend/frontend.

  - **Kiểm thử & Build**:

    - Unit test [ExpectedBreakfastReportTest.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/tests/Unit/Reports/ExpectedBreakfastReportTest.php): 7/7 tests passed (84 assertions) đạt 100%.

    - Frontend build: `npm run build` thành công không có lỗi (10.24s).

- **Cam kết không ảnh hưởng hệ thống (Zero Impact)**:

  - 0 thay đổi đến Stored Procedure nghiệp vụ `rpt_expected_breakfast`, `rpt_expected_breakfast_1`, `rpt_expected_breakfast_2`.

  - 0 thay đổi file dùng chung frontend hoặc backend core.



## [2026-09-17] - Nâng cấp Form Designer: Chọn ô viền đen, Multi-select và nạp thuộc tính ô Detail Table lên Toolbar

### Module: Cấu hình báo cáo / Form Designer ([TemplateEditorModal.vue](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/frontend/src/pages/config/components/hotel/TemplateEditorModal.vue), [TemplateEditorModalDetailTable.test.js](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/frontend/src/pages/config/components/hotel/TemplateEditorModalDetailTable.test.js))



- **Đã hoàn thành**:

  - **Tô đen quanh viền ô được chọn**:

    - Khi click vào bất kỳ ô nào thuộc bảng Detail Table (Header cột `th`, Ô dữ liệu `td`, Tiêu đề nhóm `gh`, Hàng tùy chỉnh `cr`), ô được gắn class `.selected-detail-cell` với viền đen rõ nét (`outline: 2px solid #000000 !important; outline-offset: -2px !important; box-shadow: inset 0 0 0 2px #000000 !important; position: relative !important; z-index: 20 !important;`) bảo đảm không làm biến dạng hay xê dịch kích thước ô.

    - Áp dụng đồng bộ trên cả 3 band render Detail Table (Header Band, Detail Band, Footer Band).

  - **Hỗ trợ chọn nhiều ô (Multi-select)**:

    - Click thông thường: Chọn 1 ô duy nhất.

    - Giữ phím `Ctrl`, `Cmd` hoặc `Shift` + click: Cho phép chọn thêm hoặc bỏ chọn từng ô vào tập hợp đang chọn.

  - **Nạp & đồng bộ thuộc tính ô lên Toolbar**:

    - Nạp tự động thuộc tính ô lên thanh công cụ Canvas (`v-else-if="isDetailTargetActive"`).

    - **Nội dung / Biến**: Input sửa nhanh chữ hoặc tên biến bind dữ liệu (`detailContent`).

    - **In đậm (Bold)**: Trạng thái active sáng nút `B`, click để bật/tắt `fontWeight: bold` cho toàn bộ các ô đang chọn.

    - **In nghiêng (Italic)**: Trạng thái active sáng nút `I`, click để bật/tắt `fontStyle: italic` cho toàn bộ các ô đang chọn.

    - **Gạch chân (Underline)**: Trạng thái active sáng nút `U`, click để bật/tắt `textDecoration: underline` cho toàn bộ các ô đang chọn.

    - **Cỡ chữ**: Dropdown chọn cỡ chữ (`fontSize`), tự động hiển thị cỡ chữ hiện tại của ô.

    - **Màu chữ**: Color picker (`color`) hiển thị chính xác mã màu chữ hiện tại của ô.

    - **Màu nền ô**: Color picker (`backgroundColor`) hiển thị chính xác mã màu nền ô hiện tại kèm nút "Xóa nền".

    - **Căn lề**: Dropdown chọn căn lề Trái (`left`), Giữa (`center`), Phải (`right`), Đều (`justify`).

    - **Đặt lại**: Nút reset toàn bộ định dạng ô về mặc định.

  - **Đồng bộ renderer HTML**:

    - Cập nhật hàm `customTableCellTextStyle` bổ sung `fontStyle` và `textDecoration` để custom row và group header hiển thị đúng kiểu dáng khi preview và xuất in.

  - **Kiểm thử**:

    - Viết file test [TemplateEditorModalDetailTable.test.js](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/frontend/src/pages/config/components/hotel/TemplateEditorModalDetailTable.test.js): 4/4 tests passed.

    - Toàn bộ suite `TemplateEditorModal*.test.js`: 9/9 tests passed.

    - Frontend production build: `npm run build` thành công 100% (6.67s).

- **Cam kết không ảnh hưởng hệ thống (Zero Impact)**:

  - 0 file backend/migration bị thay đổi.

  - 0 ảnh hưởng đến logic in ấn, API hay cấu trúc template lưu trữ.



## [2026-09-17] - Chuẩn hóa thông số Design và format Báo cáo lịch sử khóa phòng OOO & OOS theo hệ thống cũ (legacy sp_057 & sp_059)

### Module: Báo cáo buồng phòng ([ooo_lock_history_reference.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/report_templates/ooo_lock_history_reference.php), [oos_lock_history_reference.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/report_templates/oos_lock_history_reference.php), [2026_09_17_172000_align_ooo_oos_reports_with_legacy_design.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/migrations/2026_09_17_172000_align_ooo_oos_reports_with_legacy_design.php), [OooLockHistoryReportTest.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/tests/Feature/OooLockHistoryReportTest.php), [OosLockHistoryReportTest.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/tests/Feature/OosLockHistoryReportTest.php), [ooo_lock_history.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/reports/ooo_lock_history.md), [oos_lock_history.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/reports/oos_lock_history.md))



- **Đã hoàn thành**:

  - **Phân tích UX/UI thực tế từ ảnh chụp legacy**:

    - Đối chiếu 4 ảnh chụp (`BC_lịch_sử_khóa_OOO_1.png`, `BC_khóa_phòng_ooo_1.png`, `BC_lịch_sử_khóa_phòng_OOS_1.png`, `BC_khóa_phòng_oos_1.png`).

    - Khổ giấy thực tế là **A4 Portrait** (khổ dọc, ~210mm x 297mm), lề 8mm/8mm/8mm/8mm (trước đó thiết kế A4 Landscape là chưa chính xác).

    - Cột Số phòng: Căn giữa, chữ in đậm, màu xanh lá cây đậm `#2e7d32`.

    - Định dạng ngày giờ: `dd/mm/yyyy - HH:mm` (có dấu nối ` - ` ở giữa ngày và giờ).

    - Tiêu đề nhóm (`Locking` / `UnLock`): Chữ màu đen `#000000` in đậm, nền trắng (trước đó dùng `#851c1c` là sai).

    - Hàng tổng phụ nhóm (Subtotal): Cột 1 `Tổng`, Cột 2 `{{group.count}}` (số phòng/dòng), nền `#dee2ed`.

  - **Template Providers & Stored Procedures**:

    - Chuẩn hóa template [ooo_lock_history_reference.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/report_templates/ooo_lock_history_reference.php) và [oos_lock_history_reference.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/report_templates/oos_lock_history_reference.php):

      - Đặt `page_orientation => portrait`, `page_size => a4`, margins 8mm.

      - Thêm `cellStyle` cho cột `Phòng`: `color: #2e7d32`, `fontWeight: bold`, `textAlign: center`.

      - Cấu hình gom nhóm `grouping` trường `GroupName` (chữ đen `#000000`).

      - Cấu hình `customRows`: Hàng tổng phụ nhóm `scope: group`, `level: 0`, Cột 1 `Tổng`, Cột 2 `{{group.count}}`, nền `#dee2ed`.

    - Cập nhật định dạng ngày giờ `DATE_FORMAT(..., '%d/%m/%Y - %H:%i')` trong cả 2 Stored Procedures `rpt_ooo_lock_history` và `rpt_oos_lock_history`.

    - Cập nhật đồng bộ các migration gốc [2026_08_27_160000_create_ooo_lock_history_report.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/migrations/2026_08_27_160000_create_ooo_lock_history_report.php) và [2026_08_28_170000_create_oos_lock_history_report.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/migrations/2026_08_28_170000_create_oos_lock_history_report.php).

  - **Tạo Migration đồng bộ Multi-DB**:

    - Tạo migration [2026_09_17_172000_align_ooo_oos_reports_with_legacy_design.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/migrations/2026_09_17_172000_align_ooo_oos_reports_with_legacy_design.php) cập nhật SP và nạp lại template chuẩn Designer v1 vào bảng `templates` trên toàn bộ 7 database (`pms_system`, `pms_data`, `pms_db`, `pms_hkt1` đến `pms_hkt4`).

    - Chạy `php artisan migrate:all --force` thành công trên cả 7 database.

  - **Kiểm thử & Tài liệu**:

    - [OooLockHistoryReportTest.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/tests/Feature/OooLockHistoryReportTest.php) và [OosLockHistoryReportTest.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/tests/Feature/OosLockHistoryReportTest.php) đạt 5/5 tests (40 assertions).

    - `npm run build` thành công 100% (7.51s).

    - Cập nhật tài liệu [.codex/docs/reports/ooo_lock_history.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/reports/ooo_lock_history.md) và [.codex/docs/reports/oos_lock_history.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/reports/oos_lock_history.md).

  - **Cam kết không ảnh hưởng hệ thống (Zero Impact)**:

    - 0 file dùng chung backend/frontend bị thay đổi.

    - 0 logic khóa phòng hoặc bảng dữ liệu nghiệp vụ bị thay đổi.



## [2026-09-17] - Triển khai Báo cáo khách VIP (VIP_GUESTS / legacy sp_295)

### Module: Báo cáo khách ([vip_guests_reference.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/report_templates/vip_guests_reference.php), [2026_09_17_170000_create_vip_guests_report.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/migrations/2026_09_17_170000_create_vip_guests_report.php), [VipGuestsReportTest.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/tests/Feature/VipGuestsReportTest.php), [vip_guests.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/reports/vip_guests.md))



- **Đã hoàn thành**:

  - **Báo cáo khách VIP (`VIP_GUESTS` - Row 131 / legacy `sp_295`)**:

    - Stored Procedure `rpt_vip_guests`: Tham số `p_from_date`, `p_to_date`, `p_guest_type`. Lọc khoảng lưu trú (`arrival_date <= p_to_date AND departure_date >= p_from_date`); lọc loại khách VIP (loại trừ `RegularGuest` / `6`); join bảng `rooms` qua `r.room_number = br.room_number`; loại trừ phòng nội bộ (`is_internal = 0`), số phòng ảo (`room_number NOT LIKE '0%'`), booking và phòng bị xóa mềm. Migration đồng bộ `2026_09_17_171000_fix_vip_guests_report_procedure.php` đã cập nhật thành công trên cả 7 database.

    - Khởi động lại service Reverb WebSocket daemon trên cổng 8090.

    - Template `vip_guests_reference.php`: Chuẩn hóa 100% thuộc tính giao diện theo 2 ảnh screenshot hệ thống cũ (`Báo_cáo_khách_VIP_1.png` và `BC_khách_VIP_1.png`):

      - Khổ giấy: A4 Landscape (`landscape`), lề 6mm top/bottom, 5mm left/right.

      - Khối tiêu đề: Logo khách sạn (30%), Thông tin khách sạn/nhân viên/ngày in (70%), divider ngang mảnh `#000000`, tiêu đề căn giữa in đậm, kỳ báo cáo.

      - Bảng 11 cột: `Tên Khách` (16%), `Tình Trạng` (8%), `Đăng Ký` (6.5%), `Phòng` (6.5%), `Loại Khách` (6.5%), `Ngày Đến` (9.5%), `Ngày Đi` (9.5%), `Giá Phòng` (8%), `Người Lớn/Trẻ Em` (6.5%), `Công Ty` (10.5%), `Ghi Chú` (12.5%).

      - Gom nhóm 1 cấp theo `Loại Khách` (`GuestType`): Tiêu đề nhóm nền trắng, chữ đỏ `#ff1414`; Dòng tổng phụ nhóm (Subtotal) `vip_guests_group_total_row` (`scope: group`, `level: 0`, `{{group.count}}`) và Dòng tổng cộng cuối bảng (Grand Total) `vip_guests_grand_total_row` (`scope: table`, `{{aggregate.rows.count|number}}`) nền xám xanh `#dee2ed`.

      - Bộ lọc bên trái: `Ngày` (date range) và `Loại khách` (dropdown: Tất cả, VIP 1, VIP 2, VIP 3, VIP 4).

    - Cấu hình chuẩn Form Designer v1 (`content_json`, `content_html`, `css`) được nạp trực tiếp qua migration.

  - **Cam kết không ảnh hưởng hệ thống (Zero Impact)**:

    - 0 file dùng chung backend và 0 file dùng chung frontend bị thay đổi.

    - 0 bảng dữ liệu nghiệp vụ bị thay đổi cấu trúc.

  - **Multi-DB & Kiểm thử**:

    - Chạy `php artisan migrate:all --force` thành công trên cả 7 database: `pms_system`, `pms_data`, `pms_db`, `pms_hkt1` đến `pms_hkt4`.

    - Test [VipGuestsReportTest.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/tests/Feature/VipGuestsReportTest.php) đạt 3/3 tests (68 assertions).

    - Frontend build `npm run build` thành công 100% trong 6.84s.

    - Tạo tài liệu kỹ thuật [.codex/docs/reports/vip_guests.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/reports/vip_guests.md).



## [2026-09-16] - Triển khai 2 Báo cáo: Báo cáo yêu cầu đặc biệt (ROOM_SPECIAL_REQUESTS) & Báo cáo công nợ đã thanh toán (PAID_COMPANY_DEBTS)

### Module: Báo cáo phòng & Báo cáo công nợ ([room_special_requests_reference.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/report_templates/room_special_requests_reference.php), [paid_company_debts_reference.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/report_templates/paid_company_debts_reference.php), [PaidCompanyDebtsDataAdapter.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/app/Services/Reports/PaidCompanyDebtsDataAdapter.php), [2026_09_16_180000_create_room_special_requests_report.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/migrations/2026_09_16_180000_create_room_special_requests_report.php), [2026_09_16_190000_create_paid_company_debts_report.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/migrations/2026_09_16_190000_create_paid_company_debts_report.php))



- **Đã hoàn thành**:

  - **Báo cáo yêu cầu đặc biệt (`ROOM_SPECIAL_REQUESTS` - Row 122 / legacy `sp_297`)**:

    - Stored Procedure `rpt_room_special_requests`: Hỗ trợ 3 kiểu ngày lọc (`p_date_type`: 1 - Ở, 2 - Đến, 3 - Đi), lọc phòng, user, thứ tự sắp xếp; loại trừ phòng ảo/nội bộ (`is_virtual=0`, `is_internal=0`) và booking hủy (`status <> 99`). Nối các yêu cầu đặc biệt bằng `GROUP_CONCAT(DISTINCT sr.name SEPARATOR ' - ')`.

    - Template `room_special_requests_reference.php`: Khổ giấy A4 ngang (`landscape`), 9 cột chi tiết, gom nhóm theo `BookingId` hiển thị `Đăng Ký: [Mã]` bên trái và `Ghi Chú: [Ghi chú booking]` bên phải, kèm dòng chân nhóm hiển thị tổng số dòng/phòng.

    - Cấu hình chuẩn Form Designer v1 (`content_json`, `content_html`, `css`): Cột, font, màu sắc và padding chuẩn mực được lưu sẵn từ migration.

    - Test [RoomSpecialRequestsReportTest.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/tests/Feature/RoomSpecialRequestsReportTest.php) đạt 3/3 tests (32 assertions).

  - **Báo cáo công nợ đã thanh toán (`PAID_COMPANY_DEBTS` - Row 148 / legacy `sp_294`)**:

    - Stored Procedure `rpt_paid_company_debts`: Kết hợp `payments`, `payment_debt_settlements`, `sales_invoices`, `companies`; tính toán phân bổ các cột tiền theo tỷ lệ thanh toán (Thu tiền, Tạm thu, Trừ cọc, Giảm trừ, Phải thu, Đã thu, Công nợ).

    - Adapter [PaidCompanyDebtsDataAdapter.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/app/Services/Reports/PaidCompanyDebtsDataAdapter.php) & tích hợp [ReportDatasetEnricher.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/app/Services/Reports/ReportDatasetEnricher.php): Tự động tính toán bảng kê hình thức thanh toán (`payment_method_summary`), nạp tên nhân viên thu nợ, và tính các biến tổng tiền Grand Total.

    - Template `paid_company_debts_reference.php`: Khổ giấy A4 ngang (`landscape`), header 2 tầng 16 cột chi tiết, gom nhóm 2 cấp: Ngày TT (`PaymentDateGroup`, đỏ `#b91c1c`) -> Công ty (`CompanyGroup`, đen `#0f172a`), dòng tổng phụ theo từng cấp, sub-table `BẢNG KÊ HÌNH THỨC THANH TOÁN` và khối 4 chữ ký chuẩn kế toán.

    - Cấu hình chuẩn Form Designer v1 (`content_json`, `content_html`, `css`) được nạp trực tiếp qua migration.

    - Test [PaidCompanyDebtsReportTest.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/tests/Feature/PaidCompanyDebtsReportTest.php) đạt 4/4 tests (52 assertions).

  - **Hệ thống Database & Multi-DB**:

    - Chạy `php artisan migrate:all --force` thành công trên cả 7 database: `pms_system`, `pms_data`, `pms_db`, `pms_hkt1`, `pms_hkt2`, `pms_hkt3`, `pms_hkt4`.

  - **Kiểm thử toàn diện**:

    - Toàn bộ suite báo cáo `php artisan test --filter=Report` đạt 148/148 tests (1.316 assertions), không gây bất kỳ lỗi hồi quy nào.

    - Frontend production build `npm run build` thành công 100% trong 4.69s.

    - Tạo tài liệu nghiệp vụ đầy đủ: [.codex/docs/reports/room_special_requests.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/reports/room_special_requests.md) và [.codex/docs/reports/paid_company_debts.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/reports/paid_company_debts.md).



## [2026-09-15] - Tách độc lập 2 Báo cáo dự kiến khách ăn sáng 1 và 2 (EXPECTED_BREAKFAST_1 & EXPECTED_BREAKFAST_2)

### Module: Báo cáo phòng ([expected_breakfast_summary_reference.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/report_templates/expected_breakfast_summary_reference.php), [expected_breakfast_detail_reference.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/report_templates/expected_breakfast_detail_reference.php), [2026_09_15_140000_split_expected_breakfast_reports.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/migrations/2026_09_15_140000_split_expected_breakfast_reports.php))



- **Đã hoàn thành**:

  - **Tách riêng 2 Stored Procedures & 2 Data Sources**:

    - Tạo `rpt_expected_breakfast_1` và data source `EXPECTED_BREAKFAST_1` phục vụ Mẫu 1 (Tổng hợp theo phòng theo legacy `sp_035`).

    - Tạo `rpt_expected_breakfast_2` và data source `EXPECTED_BREAKFAST_2` phục vụ Mẫu 2 (Chi tiết khách trong phòng theo legacy `sp_032`).

    - Tách biệt hoàn toàn thủ tục lưu trữ, tránh lỗi xung đột `report_sources_object_unique` và giới hạn kết nối MySQL PDO khi gọi lồng procedure.

    - Chuẩn hóa kiểu dữ liệu: Chuyển `booking_room_id` trong các bảng tạm (`tmp_active_rooms`, `tmp_room_summary`, `tmp_guest_details`) từ `BIGINT` sang `VARCHAR(50)` khớp với kiểu thực tế của `booking_rooms.id` (chứa chuỗi mã phòng như `'G0000001'`), xử lý dứt điểm lỗi SQL 1366 / HTTP 422 trên MySQL.

  - **Đăng ký 2 Báo cáo độc lập trên menu Báo cáo phòng**:

    - **Báo cáo dự kiến khách ăn sáng 1** (`EXPECTED_BREAKFAST_1`): Sử dụng template `EXPECTED_BREAKFAST_1_STANDARD`, hiển thị 9 cột (`Mã ĐK`, `Phòng`, `Người Lớn`, `Trẻ em`, `Trẻ em MP`, `Tổng`, `Tên Khách Chính`, `Công ty`, `Ghi Chú`), gom nhóm `DateGroup` -> `RoomType`, kèm bảng phụ `THỐNG KÊ KHÁCH THEO QUỐC GIA`.

    - **Báo cáo dự kiến khách ăn sáng 2** (`EXPECTED_BREAKFAST_2`): Sử dụng template `EXPECTED_BREAKFAST_2_STANDARD`, hiển thị 6 cột (`Phòng` để trống, `Tên Khách`, `Quốc Gia`, `Ngày Đến`, `Ngày Đi`, `Ghi Chú`), gom nhóm 3 cấp `DateGroup` -> `RoomType` -> Tiêu đề nhóm phòng `DetailRoom` (`Phòng [Số phòng] - BK [Mã BK] - [Công ty/Khách chính] - Người lớn: X - Trẻ em: Y - Trẻ em MP: Z - Trẻ em KAS: W`), từng dòng hiển thị chi tiết khách người lớn và trẻ em (`Chd. [Tên trẻ]`).

  - **Migration & Multi-DB**:

    - Tạo migration [2026_09_15_140000_split_expected_breakfast_reports.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/migrations/2026_09_15_140000_split_expected_breakfast_reports.php) và chạy thành công trên cả 5 database (`mysql`, `mysql_hkt1`, `mysql_hkt2`, `mysql_hkt3`, `mysql_hkt4`).

    - Đồng bộ `content_json` và `content_html` cho cả 2 template.

  - **Dataset Enricher & Frontend**:

    - [ReportDatasetEnricher.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/app/Services/Reports/ReportDatasetEnricher.php) hỗ trợ cả 2 mã `EXPECTED_BREAKFAST_1` và `EXPECTED_BREAKFAST_2`.

  - **Kiểm thử**:

    - Unit test [ExpectedBreakfastReportTest.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/tests/Unit/Reports/ExpectedBreakfastReportTest.php) đạt 5/5 tests (48 assertions).

    - Toàn bộ suite báo cáo `php artisan test --filter=Report` đạt 139/139 tests (1.194 assertions).

    - Frontend `npm run build` thành công 100%.

    - Cập nhật tài liệu [.codex/docs/expected_breakfast/README.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/expected_breakfast/README.md).



## [2026-09-15] - Triển khai Báo cáo dự kiến khách ăn sáng (EXPECTED_BREAKFAST) theo chuẩn legacy sp_035 & sp_032

### Module: Báo cáo phòng ([expected_breakfast_summary_reference.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/report_templates/expected_breakfast_summary_reference.php), [expected_breakfast_detail_reference.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/report_templates/expected_breakfast_detail_reference.php), [2026_09_15_130000_create_expected_breakfast_report.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/migrations/2026_09_15_130000_create_expected_breakfast_report.php))



- **Đã hoàn thành**:

  - **Khảo sát & Đối chiếu Stored Procedure legacy**:

    - Truy vấn trực tiếp SQL Server `ProVistaDTXHotel` qua `sqlcmd` lấy định nghĩa đầy đủ của `sp_035` (Mẫu tổng hợp) và `sp_032` (Mẫu chi tiết khách trong phòng).

    - Làm rõ quy tắc ngày ăn sáng: ngày báo cáo là ngày ăn sáng của khách (ví dụ vào ngày 1 ra ngày 3 thì ăn sáng ngày 2 và 3).

    - Xử lý các điều kiện:

      - Phòng ở thật (`PHÒNG Ở THẬT`): `report_date BETWEEN ptk.actual_arrival_date + 1 AND ptk.actual_checkout_date`, check-in sớm (`actual_arrival_time <= '00:01'`), hoặc Day Use.

      - Phòng late check-in (`PHÒNG LATE CHECK IN`): chỉ tính khi `p_late_checkin = 1`, xét bảng `late_checkins` và hóa đơn có tiền phòng (`is_room_night = 1`).

      - Xác định ăn sáng (`IsBreakfast`): `booking_rooms.breakfast = 1`, hoặc có dịch vụ ăn sáng ngoài (`BF`, `AL`, `BD`, `BE`, `BU`), hoặc trẻ em có ăn sáng.

      - Phân loại trẻ em: tính phí (`breakfast = 1, is_free = 0, amount > 0`), miễn phí (`breakfast = 1, is_free = 1`), và không ăn sáng (`breakfast = 0`).

  - **Template tham chiếu chuẩn**:

    - Tạo [expected_breakfast_summary_reference.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/report_templates/expected_breakfast_summary_reference.php) định nghĩa mẫu `EXPECTED_BREAKFAST_SUMMARY_STANDARD` (9 cột, gom nhóm theo `DateGroup`, tính tổng cộng và bảng phụ `THỐNG KÊ KHÁCH THEO QUỐC GIA`).

    - Tạo [expected_breakfast_detail_reference.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/report_templates/expected_breakfast_detail_reference.php) định nghĩa mẫu `EXPECTED_BREAKFAST_DETAIL_STANDARD` gom nhóm 2 cấp `RoomType` -> `DetailRoom` và hiển thị chi tiết tên khách, quốc gia, ngày đến, ngày đi, ghi chú.

  - **Migration & Stored Procedure**:

    - Tạo migration [2026_09_15_130000_create_expected_breakfast_report.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/migrations/2026_09_15_130000_create_expected_breakfast_report.php) tạo stored procedure `rpt_expected_breakfast`, đăng ký `report_data_sources`, `report_definitions`, UI schema với đầy đủ các bộ lọc (Ngày, Loại, Người dùng, Sắp xếp theo, Thứ tự, Tính phòng late checkin, Hiển thị thông tin phòng, Đăng ký theo nhóm) và 2 template.

    - Đã chạy `php artisan migrate:all --force` thành công trên toàn bộ 7 database PMS.

  - **Dataset Enrichment & Frontend Integration**:

    - Cập nhật [ReportDatasetEnricher.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/app/Services/Reports/ReportDatasetEnricher.php) tự động tổng hợp bảng `country_summary` (Quốc gia, Số lượng, Tỉ lệ %) và `CountryTotalPax`.

    - Cập nhật [ReportsPage.vue](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/frontend/src/pages/reports/ReportsPage.vue) bổ sung watcher tự động chuyển đổi giữa mẫu tổng hợp và mẫu chi tiết khi toggle checkbox `p_show_room_details` ("Hiển thị thông tin phòng").

  - **Kiểm thử & Tài liệu**:

    - Tạo unit test [ExpectedBreakfastReportTest.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/tests/Unit/Reports/ExpectedBreakfastReportTest.php) kiểm tra toàn diện migration, layout render 2 template, enricher và UI watcher (đạt 5/5 tests, 48 assertions).

    - Chạy toàn bộ test Reports backend đạt 48/48 tests, 495 assertions.

    - Chạy `npm run build` frontend đạt 100%.

    - Tạo tài liệu nghiệp vụ [.codex/docs/expected_breakfast/README.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/expected_breakfast/README.md).



## [2026-09-15] - Triển khai Báo cáo hóa đơn minibar miễn phí (MINIBAR_FREE_INVOICES) theo chuẩn legacy sp_202

### Module: Báo cáo buồng phòng / Minibar ([minibar_free_invoices_reference.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/report_templates/minibar_free_invoices_reference.php), [2026_09_15_110000_create_minibar_free_invoice_report.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/migrations/2026_09_15_110000_create_minibar_free_invoice_report.php))



- **Đã hoàn thành**:

  - **Khảo sát & Đối chiếu dữ liệu thật**:

    - Truy vấn trực tiếp SSMS SQL Server `.\MSSQLSERVER01` -> database `ProVistaDTXHotel` qua `sqlcmd` kiểm tra logic và output của stored procedure `sp_202` với tham số `@outlet = 'MB'`.

    - Xác nhận các hóa đơn minibar miễn phí gắn với phương thức thanh toán `CL` (Complementary / Miễn phí).

  - **Template tham chiếu & Header Band chuẩn hóa**:

    - Chuẩn hóa template [minibar_free_invoices_reference.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/report_templates/minibar_free_invoices_reference.php) theo đúng cấu trúc canonical report header band của hệ thống (dạng `columns` 30% logo / 70% thông tin khách sạn + divider + title + period và wrapper `<div class="report-header-band">`).

    - Bảng chi tiết gồm 13 cột (có cột HTTT) và bảng kê tổng hợp số lượng sản phẩm minibar miễn phí.

  - **Stored Procedure & Migration**:

    - Tạo migration [2026_09_15_110000_create_minibar_free_invoice_report.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/database/migrations/2026_09_15_110000_create_minibar_free_invoice_report.php) tạo stored procedure `rpt_minibar_free_invoices` lọc `housekeeping_service_bills` có `Outlet = 'MB'` và phương thức thanh toán `CL`.

    - Đồng bộ `content_json` và `content_html` chứa report header band chuẩn hóa vào bảng `templates` trên toàn bộ các database chi nhánh (`mysql`, `mysql_hkt1` đến `mysql_hkt4`).

  - **Data Enrichment & UI Integration**:

    - Cập nhật [ReportDatasetEnricher.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/app/Services/Reports/ReportDatasetEnricher.php) hỗ trợ mã `MINIBAR_FREE_INVOICES` trích xuất `product_summary`.

    - Cập nhật [ReportsPage.vue](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/frontend/src/pages/reports/ReportsPage.vue) thêm `MINIBAR_FREE_INVOICES` vào nhóm filter hóa đơn buồng phòng.

  - **Kiểm thử**:

    - Tạo unit test [MinibarFreeInvoicesTemplateTest.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/tests/Unit/Reports/MinibarFreeInvoicesTemplateTest.php), kiểm tra layout, metadata và rendering (đạt 2/2 tests, 14 assertions).

    - Cập nhật [ReportDatasetEnricherHousekeepingTest.php](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/backend/tests/Unit/Reports/ReportDatasetEnricherHousekeepingTest.php) (đạt 1/1 test, 10 assertions).

    - Chạy toàn bộ test Reports backend (43/43 tests, 444 assertions).

    - Chạy `npm run build` frontend thành công 100%.



## [2026-09-14] - Chuẩn hóa tên file template báo cáo hàng bể vỡ trong DB migration (Tương thích Linux)

### Module: Báo cáo dịch vụ / Migrations ([2026_09_11_170000_create_breakage_invoice_product_report.php](file:///d:/PMS/backend/database/migrations/2026_09_11_170000_create_breakage_invoice_product_report.php), [2026_09_11_171000_create_breakage_free_invoice_report.php](file:///d:/PMS/backend/database/migrations/2026_09_11_171000_create_breakage_free_invoice_report.php))



- **Bối cảnh & Vấn đề**:

  - Trên Windows chạy `php artisan db:reset-all --seed-all` thành công do NTFS không phân biệt chữ hoa/thường.

  - Trên máy chủ Linux, lệnh bị crash với lỗi `ErrorException: require(.../BREAKAGE_INVOICES_BY_PRODUCT_reference.php): Failed to open stream: No such file or directory` do Linux phân biệt chữ hoa/thường (case-sensitive) trong khi file thực tế trên disk là chữ thường (`breakage_...`).

- **Khắc phục**:

  - Sửa tên require template sang chữ thường trong [2026_09_11_170000_create_breakage_invoice_product_report.php](file:///d:/PMS/backend/database/migrations/2026_09_11_170000_create_breakage_invoice_product_report.php) (`breakage_invoices_by_product_reference.php`).

  - Sửa tên require template sang chữ thường trong [2026_09_11_171000_create_breakage_free_invoice_report.php](file:///d:/PMS/backend/database/migrations/2026_09_11_171000_create_breakage_free_invoice_report.php) (`breakage_free_invoices_reference.php`).

- **Kiểm thử**: `php -l` kiểm tra cú pháp thành công 100%.



## [2026-09-11] - Tích hợp Thẻ thông tin khách dạng Popup khi Double Click từ Màn hình Thông tin khách Booking

### Module: Đặt phòng / Thông tin khách lưu trú ([GuestInfoModal.vue](file:///d:/PMS/frontend/src/pages/reservation/components/GuestInfoModal.vue), [GuestDetailModal.vue](file:///d:/PMS/frontend/src/pages/reservation/components/GuestDetailModal.vue))



- **Đã hoàn thành**:

  - **Giữ màn hình chính là bảng tổng hợp khách trong phòng ([GuestInfoModal.vue](file:///d:/PMS/frontend/src/pages/reservation/components/GuestInfoModal.vue))**:

    - Header Navy chuẩn phong cách PMS với các nút chức năng: Chỉnh sửa, Quét CCCD (Scan), Xuất Excel, Cài đặt, Đóng.

    - Hiển thị danh sách khách nhóm theo từng phòng (Khách người lớn, Trẻ em).

  - **Tương tác Double Click mở Thẻ khách chi tiết ([GuestDetailModal.vue](file:///d:/PMS/frontend/src/pages/reservation/components/GuestDetailModal.vue))**:

    - Khi **nhấp đúp chuột (Double click)** vào bất kỳ dòng khách nào trong bảng (hoặc bấm nút "Thẻ khách" / "Thẻ trẻ"), hệ thống mở ngay modal **Thẻ thông tin khách** hiển thị toàn bộ thông tin chi tiết của riêng khách đó.

    - Bổ sung cột "Thao tác" với nút bấm nhanh "Thẻ khách" để người dùng tiện click 1 chạm ngoài thao tác double click.

    - Bổ sung dòng gợi ý thao tác ở chân modal: `💡 Mẹo: Nhấp đúp chuột (Double click) vào bất kỳ dòng nào để mở Thẻ thông tin khách`.

  - **Giao diện Thẻ khách chi tiết chuẩn 100% theo mẫu [thong-tin-khach (1).html](file:///d:/PMS/UI/thong-tin-khach%20(1).html)**:

    - Thanh Header Navy (`#1E2D4A`) hiển thị tiêu đề và tên khách.

    - Dải thông tin phòng lưu trú (`.stay`): Số phòng, hạng phòng, đơn giá, ngày đến, ngày đi, số đêm badge.

    - Cột nhận diện (`.side`): Kéo thả ảnh, chọn file, chụp webcam, danh sách thumbnail, xóa ảnh, đếm ảnh.

    - 4 khối trường nhập liệu (`.main`): Thông tin cá nhân, Giấy tờ tùy thân (kèm tự động mờ trường visa nếu là khách VN), Thông tin liên hệ & địa chỉ (dropdown cascading Tỉnh/Quận/Xã), Ghi chú.

  - **Khắc phục lỗi tải ảnh đại diện ("The avatar field must not be greater than 255 characters")**:

    - Khi người dùng tải ảnh lên hoặc kéo thả, tự động gọi API `POST /guests/{id}/avatar` với `FormData` để lưu file vào thư mục máy chủ và nhận về đường dẫn file ngắn (`uploads/avatars/...`).

    - Lọc bỏ chuỗi Base64 / Blob URL trước khi gửi API cập nhật thông tin khách, tránh vượt quá giới hạn độ dài trường `avatar` (255 ký tự).

  - **Mở khóa các trường thị thực / nhập cảnh bị xám ([GuestDetailModal.vue](file:///d:/PMS/frontend/src/pages/reservation/components/GuestDetailModal.vue))**:

    - Gỡ bỏ logic làm mờ/khóa `pointer-events: none` cho các ô Ngày nhập cảnh, Cửa khẩu, Mục đích nhập cảnh, Số Visa. Nhân viên có thể linh hoạt nhập thông tin bất kể quốc tịch của khách.

  - **Bật lịch chọn ngày (Date Picker dialog) trực quan**:

    - Bổ sung sự kiện gọi `showPicker()` khi click vào ô hoặc icon lịch tại các trường Ngày sinh, Ngày cấp, Ngày hết hạn, Ngày nhập cảnh, Tạm trú đến, giúp mở popup chọn lịch ngay lập tức thay vì chỉ nhập text.

  - **Hiển thị thông tin lịch sử và người cập nhật ở chân Modal**:

    - Hiển thị chuẩn theo template: `Cập nhật: dd/mm/yyyy HH:mm · bởi [Tên nhân viên]`.

- **Kiểm thử**: `npm run build` thành công 100%, không phát sinh lỗi.



---



## [2026-09-11] - Chặn triệt để việc gán phòng và tạo booking khi AllowCheckinVacantClean = 0

### Module: Frontdesk / Sơ đồ phòng ([QuickAssignModal.vue](file:///d:/PMS/frontend/src/pages/reservation/components/QuickAssignModal.vue))



- **Bối cảnh & Vấn đề**:

  - Khi phòng ở trạng thái `vacant_clean` (hoặc `vacant_dirty`, `turndown`) và `AllowCheckinVacantClean = 0`, khi bấm Lưu ở modal Nhận phòng nhanh (Walk-in), hệ thống hiện cảnh báo lỗi đỏ nhưng phòng vẫn bị gán vào sơ đồ phòng (tạo booking và gán phòng vật lý trước khi check-in).

- **Khắc phục**:

  - **Kiểm tra trạng thái phòng & cấu hình trước khi tạo booking ([QuickAssignModal.vue](file:///d:/PMS/frontend/src/pages/reservation/components/QuickAssignModal.vue))**:

    - Chuyển toàn bộ bước kiểm tra trạng thái phòng vật lý (`vacant_clean`, `vacant_dirty`, `turndown`, `ooo`, `oos`, `occupied_...`) và đọc cấu hình `AllowCheckinVacantClean` thời gian thực lên **TRƯỚC KHI** gọi API `createBooking()`.

    - **Khi `AllowCheckinVacantClean = 0`**: Bắn cảnh báo lỗi đỏ ngay lập tức và `return` dừng xử lý. Tuyệt đối không tạo booking, không gán phòng vào database, giữ nguyên trạng thái phòng trên Sơ đồ phòng.

    - **Khi `AllowCheckinVacantClean = 1`**: Mở popup xác nhận nhận phòng trước (`uiStore.confirm`). Nếu người dùng bấm "Hủy" thì dừng ngay (không tạo booking, không gán phòng). Nếu người dùng bấm "Tiếp tục" mới tiến hành tạo booking và check-in với `{ confirmed: true }`.

    - **Cơ chế Rollback an toàn**: Nếu có bất kỳ lỗi nào trong quá trình check-in sau khi tạo booking, tự động gọi xóa booking vừa tạo (`http.delete('/bookings/' + createdBooking.id)`), đảm bảo phòng không bị gán sai lệch.

- **Kiểm thử**: `npm run build` thành công 100%.



## [2026-09-11] - Chuẩn hóa quy trình kiểm tra trạng thái phòng khi bấm Lưu / Nhận phòng nhanh

### Module: Frontdesk / Sơ đồ phòng ([RoomMapPage.vue](file:///d:/PMS/frontend/src/pages/reservation/RoomMapPage.vue), [QuickAssignModal.vue](file:///d:/PMS/frontend/src/pages/reservation/components/QuickAssignModal.vue))



- **Bối cảnh & Phản hồi**:

  - Người dùng yêu cầu không hiển thị banner cảnh báo tĩnh chặn trước khi mở modal, mà việc kiểm tra trạng thái phòng phải diễn ra **tại thời điểm bấm "Lưu" / "Nhận phòng"** theo đúng giá trị cấu hình `AllowCheckinVacantClean` thời gian thực từ Backend.

- **Khắc phục**:

  - **Gỡ bỏ banner cảnh báo tĩnh & khôi phục nút Lưu ([QuickAssignModal.vue](file:///d:/PMS/frontend/src/pages/reservation/components/QuickAssignModal.vue))**:

    - Xóa bỏ banner cảnh báo sớm và mở lại nút "Lưu" bình thường để người dùng thao tác nhập liệu tự nhiên.

  - **Kiểm tra trạng thái thời gian thực khi bấm Lưu ([QuickAssignModal.vue](file:///d:/PMS/frontend/src/pages/reservation/components/QuickAssignModal.vue))**:

    - Khi bấm Lưu: Hệ thống gọi API Backend kiểm tra trực tiếp với Database.

    - **Trường hợp `AllowCheckinVacantClean = 1`** (phòng đang Chờ kiểm tra / Vacant Clean):

      - Backend trả về `needs_confirmation = true`.

      - Frontend mở popup xác nhận (`uiStore.confirm`): *"Phòng [Số phòng] đang ở trạng thái chờ kiểm tra. Bạn có muốn tiếp tục nhận phòng không? Tình trạng phòng sẽ được giữ nguyên."*

      - Người dùng bấm "Tiếp tục nhận phòng": Gửi `{ confirmed: true }` $\rightarrow$ Nhận phòng thành công.

    - **Trường hợp `AllowCheckinVacantClean = 0`**:

      - Backend trả về lỗi 422: *"Phòng [Số phòng] đang ở trạng thái chờ kiểm tra (Vacant Clean). Không được phép nhận phòng do cấu hình hệ thống (AllowCheckinVacantClean = 0)."*

      - Frontend hiển thị toast báo lỗi đỏ chi tiết ngay tại thời điểm bấm Lưu, loại bỏ triệt để lỗi nuốt exception báo thành công giả.

  - **Đồng bộ Sơ đồ phòng ([RoomMapPage.vue](file:///d:/PMS/frontend/src/pages/reservation/RoomMapPage.vue))**:

    - Khôi phục Modal xác nhận nhận phòng nhanh gọn, kiểm tra trực tiếp qua Backend khi bấm nút "Nhận phòng".

- **Kiểm thử**: `npm run build` thành công 100%.



## [2026-09-11] - Sửa lỗi Màu sắc, Tooltip và Vạch trạng thái Kế Hoạch Phòng (Room Plan) & Chuẩn hóa Sơ Đồ Phòng

### Module: Đặt phòng / Kế hoạch phòng & Sơ đồ phòng ([RoomPlanPage.vue](file:///d:/PMS/frontend/src/pages/reservation/RoomPlanPage.vue), [RoomMapPage.vue](file:///d:/PMS/frontend/src/pages/reservation/RoomMapPage.vue))



- **Khôi phục logic màu sắc Kế Hoạch Phòng (Room Plan)**:

  - Loại bỏ hoàn toàn việc lấy mã màu mặc định `ColorDefaultBookingRoomMap` (#97D5FF) của Sơ đồ phòng đè lên Kế hoạch phòng.

  - Khôi phục màu sắc độc lập theo cấu hình riêng của Room Plan trong DB (`RoomPlan_ColorRoomReservation`, `RoomPlan_ColorRoomInhouse`, `RoomPlan_ColorRoomLateCheckout`, `RoomPlan_ColorOOO`, `RoomPlan_ColorOOS`).

  - Cả `Reservation` và `Guaranteed` đều ăn theo cấu hình `RoomPlan_ColorRoomReservation` (#E3E8C4), loại bỏ việc màu DB registration_statuses (#4ce410) đè lên phòng đặt trước.

  - Khôi phục thanh Legend trên cùng hiển thị đúng màu riêng từng trạng thái thay vì bị đồng màu xanh ngọc.

- **Khôi phục giao diện Tooltip màu trắng chuẩn ban đầu đầy đủ thông số**:

  - Trả lại theme nền trắng (`bg-white text-slate-800 border-slate-200/80 p-4 shadow-2xl w-[360px]`).

  - Khôi phục đầy đủ 2 khối thông số tài chính và công nợ:

    - Khối 1: *Tiền phòng cần TT*, *Tiền DV cần TT*, *Tổng cộng*.

    - Khối 2: *Tổng tiền BK*, *Đã đặt cọc*, *Còn lại* (`text-rose-600 font-black`).

  - Khôi phục ngày đến ~ ngày đi đầy đủ ngày giờ, lưới 3 cột (Số phòng - Đêm - Giá phòng), số khách (🧑 🧒 👶) và giường phụ.

- **Chuẩn hóa hiển thị thanh booking trên Kế Hoạch Phòng**:

  - Gỡ bỏ icon cọc tiền (`deposit-money.png`) và thuộc tính `pr-9` trên thanh booking, giúp tên booking, công ty và giá phòng hiển thị đầy đủ, không còn bị co cụm hay che khuất trên các booking ngắn ngày (1 đêm).

- **Chuẩn hóa vạch trạng thái đáy thanh phòng Kế Hoạch Phòng**:

  - Giai đoạn lưu trú / ngày đến: Hiển thị vạch màu **Xanh lá 🟢** (`#22c55e`) đại diện cho **Phòng đến**.

  - Tại ngày trả phòng (`showCheckOutIndicator`): Hiển thị vạch màu **Đỏ 🔴** (`#ef4444`) đại diện cho **Phòng đi**.

- **Đồng bộ Sơ Đồ Phòng (Room Map)**:

  - Tách riêng điều kiện gạch chân `isArrivingTomorrow(room)` độc lập với màu sắc số phòng, đảm bảo mọi phòng ngày mai có khách đến đều được gạch chân (`underline font-black decoration-2`) trên cả Card View và cả 2 Table Views.

  - Hoạt động chuẩn xác theo thông số cấu hình `RoomMap_ColorRoomNumberByRoomClass` (0: màu đen mặc định, check-in hôm nay màu đỏ; 1: màu theo `room_classes.color`, không đổi đỏ khi check-in).

- **Đồng bộ màu sắc hai chiều giữa Cấu hình & Kế hoạch phòng (Two-way Color Sync)**:

  - Cả tab **Cấu hình khách sạn** ([HotelConfigTab.vue](file:///d:/PMS/frontend/src/pages/config/components/hotel/HotelConfigTab.vue)) và **Kế hoạch phòng** ([RoomPlanPage.vue](file:///d:/PMS/frontend/src/pages/reservation/RoomPlanPage.vue)) đều đọc/ghi chung một bảng `hotel_configs` trong Database.

  - Tích hợp `fetchHotelSettings()` vào nút "View" (`handleViewClick`) trên Kế hoạch phòng, giúp cập nhật ngay màu cấu hình mới nhất mà không cần tải lại toàn bộ trang (F5).

- **Khôi phục họa tiết sọc chéo (Stripes) chuẩn cho OOO & OOS**:

  - Khắc phục lỗi OOO, OOS và InHouse đều bị đồng màu xanh dương đặc.

  - **OOO**: Khôi phục họa tiết sọc chéo xanh dương (`repeating-linear-gradient(-45deg, #3b82f6, #3b82f6 5px, #60a5fa 5px, #60a5fa 10px)`).

  - **OOS**: Khôi phục họa tiết sọc chéo xám (`repeating-linear-gradient(-45deg, #94a3b8, #94a3b8 5px, #cbd5e1 5px, #cbd5e1 10px)`), sửa triệt để giá trị mặc định của OOS trong DB và seeder về màu xám `#94a3b8` (thay vì bị nhầm `#107eeb` của màu xanh).

  - Đổi chiều góc nghiêng sọc sang `-45deg` chuẩn theo yêu cầu người dùng trên cả thanh dải màu Legend, các khối khóa phòng trên Timeline Grid, và bóng kéo rê (drag ghost).

- **Sửa lỗi hiển thị chấm trạng thái (Status Dots) trên Sơ Đồ Phòng ([RoomMapPage.vue](file:///d:/PMS/frontend/src/pages/reservation/RoomMapPage.vue))**:

  - **Hiện tượng**: Phòng khách ở 1, 2 đêm sau khi bấm check-in lại hiển thị cả 2 chấm cùng lúc: vừa chấm xanh 🟢 (Phòng đến) vừa chấm đỏ 🔴 (Phòng đi).

  - **Nguyên nhân**: Hàm [hasDepartureToday](file:///d:/PMS/frontend/src/pages/reservation/RoomMapPage.vue#L919) kiểm tra phòng đã check-in nhưng không so sánh ngày đi (`departure_date`) với ngày hiện tại (`targetDate`), khiến bất kỳ phòng nào đã nhận phòng đều bị hiển thị chấm đỏ (phòng đi) sai lệch.

  - **Khắc phục**: Bổ sung điều kiện so sánh chính xác `departure_date === targetDate`. Chấm đỏ 🔴 chỉ hiển thị đúng vào ngày khách trả phòng (check-out). Các ngày lưu trú bình thường (ở 1 hay nhiều đêm) không còn bị hiện chấm đỏ.

- **Kiểm thử**: `npm run build` thành công 100%.



## [2026-09-10] - Fix lỗi Sang Ngày kiểm tra booking chưa gán phòng vật lý (chưa lấy phòng)

### Module: Sang ngày / Night Audit ([NightAuditController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/NightAuditController.php), [DayClosePage.vue](file:///d:/PMS/frontend/src/pages/frontdesk/DayClosePage.vue))



- **Nguyên nhân**:

  - Khi tạo booking mới mà chưa gán phòng vật lý (`room_number` là `null` hoặc `'Chưa gán'`), hoặc booking chưa có phòng (`booking_rooms` rỗng), màn hình Sang ngày ([DayClosePage.vue](file:///d:/PMS/frontend/src/pages/frontdesk/DayClosePage.vue)) vẫn lấy booking đó đưa vào danh sách tab "Phòng đến" và đếm vào `arrivalCount`.

  - Điều này làm `canRollDay` bị khóa (`arrivalCount > 0`), đồng thời backend [NightAuditController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/NightAuditController.php) trong `checkStatus()` và `runNightAudit()` quét `BookingRoom` theo ngày mà không kiểm tra phòng vật lý, dẫn đến quăng lỗi `Không thể sang ngày vì vẫn còn phòng chưa check-in hoặc chưa check-out`.

- **Khắc phục**:

  - **Backend ([NightAuditController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/NightAuditController.php))**:

    - Cả 2 hàm `checkStatus()` và `runNightAudit()` bổ sung điều kiện lọc `whereNotNull('room_number')->where('room_number', '!=', '')->whereRaw("LOWER(TRIM(room_number)) NOT IN ('chưa gán', 'chua gan')")` cho cả `pendingCheckIns` và `pendingCheckOuts`.

    - Bỏ qua các booking/phòng chưa gán phòng vật lý, không chặn tiến trình chuyển ngày hệ thống.

  - **Frontend ([DayClosePage.vue](file:///d:/PMS/frontend/src/pages/frontdesk/DayClosePage.vue))**:

    - Bổ sung hàm kiểm tra `hasAssignedRoom(r)` nhằm loại bỏ triệt để các phòng chưa gán (`null`, rỗng, `'Chưa gán'`).

    - Trong `processRealBookings`: Chỉ tính vào số đếm `arrivalCount` đối với các phòng đã được gán phòng vật lý thực tế; loại bỏ hoàn toàn nhánh fallback đếm cả booking không có phòng (`rooms.length === 0`).

    - Trong `processItem`: Bỏ qua các phòng chưa gán phòng vật lý, không đưa vào danh sách bảng dữ liệu của tab "Phòng đến", đảm bảo nút "Sang ngày" không bị chặn vô lý.

- **Kiểm thử**: `npm run build` thành công 100%.



## [2026-09-10] - Chuẩn hóa UI module Tài Khoản Ngân Hàng & Bật tính năng bôi đen / copy text trong System

### Module: System / Tài Khoản Ngân Hàng ([BankAccountTab.vue](file:///d:/PMS/frontend/src/pages/system/components/BankAccountTab.vue), [SystemPage.vue](file:///d:/PMS/frontend/src/pages/system/SystemPage.vue))



- **Đồng bộ chuẩn UI theo BranchManageTab & EmployeeTab**:

  - Loại bỏ khung wrapper card cũ và tiêu đề lớn `<h1>`.

  - Thanh Toolbar trên cùng: Ô tìm kiếm chuẩn `h-[30px]` kèm nút xóa nhanh `✕` và nút "Tìm Kiếm" (`bg-[#8dcbf4] hover:bg-[#70b2db]`).

  - Cụm nút chức năng bên phải: Nút chuyển nhóm subtab (`[1. Ngân Hàng Thanh Toán | 2. Ngân Hàng Trung Gian]`), nút `+ Thêm`, nút Trợ giúp SVG và popover Thiết lập ẩn/hiện cột.

  - Bảng dữ liệu: Bảng viền `border border-slate-200 rounded-lg shadow-2xs`, tiêu đề cố định `sticky top-0 bg-slate-100/90`, hỗ trợ sắp xếp các cột có thể sort (`sortable`), nút xóa tài khoản dùng SVG thùng rác chuẩn.

  - Modal Thêm / Chỉnh sửa: Header màu xanh `bg-[#8dcbf4]`, subtabs phân chia rõ ràng, lưới form 2 cột nhập liệu gọn gàng (hỗ trợ nhập `opened_on` & `closed_on`), footer với các nút `Tiếp`, `Hủy`, `Lưu`.

- **Khắc phục lỗi chặn copy text trong System**:

  - Gỡ bỏ thuộc tính `select-none` thừa ở container gốc của [SystemPage.vue](file:///d:/PMS/frontend/src/pages/system/SystemPage.vue).

  - Bổ sung `select-text` trên các dòng dữ liệu bảng, ô dữ liệu và modal trong [BankAccountTab.vue](file:///d:/PMS/frontend/src/pages/system/components/BankAccountTab.vue) để người dùng có thể bôi đen và sao chép (Ctrl+C) mã tài khoản, số tài khoản, tên ngân hàng thuận tiện.

- **Dọn dẹp code**: Dọn dẹp khối menu lặp trong sidebar của [SystemPage.vue](file:///d:/PMS/frontend/src/pages/system/SystemPage.vue).

- **Kiểm thử**: `npm run build` thành công 100%.



## [2026-09-09] - Fix 4 lỗi Room Map & Check-in Logic (Lễ Tân)

### Module: Room Map / Check-in ([BookingRoomController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/BookingRoomController.php), [CheckInPage.vue](file:///d:/PMS/frontend/src/pages/reservation/CheckInPage.vue), [RoomMapPage.vue](file:///d:/PMS/frontend/src/pages/reservation/RoomMapPage.vue), [RoomPlanPage.vue](file:///d:/PMS/frontend/src/pages/reservation/RoomPlanPage.vue), [HotelDefinitionSeeder.php](file:///d:/PMS/backend/database/seeders/HotelDefinitionSeeder.php), [booking-service.js](file:///d:/PMS/frontend/src/services/booking-service.js))



- **Section 1 – Kiểm tra AllowCheckinVacantClean & Giữ trạng thái phòng khi check-in phòng chờ kiểm tra (vacant_clean / dirty)**:

  - Backend: Kiểm tra trực tiếp `$physicalRoom->room_status_code` thay vì `$physicalRoom->status` (vốn bị accessor `Room.php` ánh xạ mặc định `vacant_clean` thành `available`).

  - Khi `AllowCheckinVacantClean=0`: Chặn check-in đối với phòng `vacant_clean`, `vacant_dirty`, `turndown` → Trả về HTTP 422 ("Không được phép nhận phòng do cấu hình hệ thống").

  - Khi `AllowCheckinVacantClean=1` & chưa có `confirmed`: Trả về HTTP 200 với `needs_confirmation: true` và message yêu cầu xác nhận.

  - Khi `confirmed=true`: Cho phép check-in và **bảo lưu nguyên trạng thái phòng**: `vacant_clean` giữ nguyên `vacant_clean` (không đổi sang `occupied_ready`, giữ nguyên icon ngôi sao ✨ trên Room Map).

  - Frontend `RoomMapPage.vue`: Cập nhật `handleQuickCheckIn()` để bắt `needs_confirmation` và mở dialog `uiStore.confirm`.

  - Frontend `RoomPlanPage.vue`: Bổ sung xử lý `needs_confirmation` khi giao phòng.

  - Frontend `CheckInPage.vue`: Đã có dialog xác nhận và gửi lại với `confirmed: true`.



- **Section 2 – Hiện lại nút "Hủy nhận phòng" tại danh sách phòng đã đến của bộ phận Lễ tân**:

  - **Nguyên nhân gốc (Root Cause)**: `RoomStatusPermissionService::canCancelCheckIn` kiểm tra cấu hình `RoleUserCancelCheckIn`. Trong DB giá trị mặc định là chuỗi rỗng `''`. Code cũ xử lý `if ($roleConfig === '') return false;` khiến 100% người dùng (kể cả Super Admin hay nhân viên Lễ tân) đều bị trả về `can_cancel_checkin: false`. Đồng thời ở frontend, `isArrivalMode` thiếu trường hợp `!props.displayMode`, và `canUndoForDate` bị ràng buộc thừa `searchDate === systemDate`.

  - **Backend ([RoomStatusPermissionService.php](file:///d:/PMS/backend/app/Services/RoomStatusPermissionService.php))**:

    - Khi `RoleUserCancelCheckIn` để trống (mặc định), hệ thống cho phép bộ phận lễ tân hủy nhận phòng (`return true`).

    - Super Admin luôn được bypass quyền hủy nhận phòng.

    - Khi cấu hình có danh sách chức danh cụ thể, kiểm tra theo `job_title_code` / `job_title`.

  - **Frontend ([CheckInPage.vue](file:///d:/PMS/frontend/src/pages/reservation/CheckInPage.vue), [RoomMapPage.vue](file:///d:/PMS/frontend/src/pages/reservation/RoomMapPage.vue))**:

    - Chuẩn hóa `isFrontDesk` nhận diện thêm `route.path.startsWith('/frontdesk')`.

    - `isArrivalMode` hỗ trợ cả `displayMode === 'arrivals'` và `!props.displayMode` (khi mở tab checkin trực tiếp).

    - **Đồng bộ màn hình xác nhận Hủy nhận phòng**: Thay thế popup confirm 2 nút mặc định cũ bằng modal chuẩn 3 nút ("Đóng" / "Dơ" / "Có") đồng bộ 100% với Sơ đồ phòng:

      - Nút "Đóng": Đóng modal, không thực hiện thao tác.

      - Nút "Dơ": Hủy nhận phòng và chuyển trạng thái phòng vật lý thành Phòng bẩn (`vacant_dirty`).

      - Nút "Có": Hủy nhận phòng và chuyển trạng thái phòng vật lý thành Phòng sạch (`vacant_clean`).

    - Thêm watcher `watch([() => props.currentModule, isFrontDesk], loadPermissions)` để luôn nạp lại quyền khi chuyển module/route.

    - Đồng bộ `moduleContext` trong `RoomMapPage.vue` nhận diện `route.path.startsWith('/frontdesk')`.



- **Section 3 – Màu số phòng theo loại phòng + gạch chân ngày mai**:

  - Thêm thông số mới `RoomMap_ColorRoomNumberByRoomClass` vào `HotelDefinitionSeeder.php` (value mặc định `'0'`) và expose qua API `GET /api/hotel-settings` ([HotelSettingController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/HotelSettingController.php)).

  - [RoomResource.php](file:///d:/PMS/backend/app/Http/Resources/RoomResource.php): Trả về `room_class_color` lấy từ `room_classes.color`.

  - [RoomMapPage.vue](file:///d:/PMS/frontend/src/pages/reservation/RoomMapPage.vue):

    - Khi `RoomMap_ColorRoomNumberByRoomClass = 0`: Số phòng mặc định màu đen. Các phòng đang ở có ngày đến = ngày hệ thống (`checkinStr === sysDateStr`) hiển thị màu đỏ (`text-red-600 font-black`), áp dụng chuẩn cho cả module Lễ tân và Đặt phòng.

    - Khi `RoomMap_ColorRoomNumberByRoomClass = 1`: Số phòng hiển thị theo màu `room_classes.color` qua `getRoomNumberStyle(room)`, không đổi sang màu đỏ khi check-in trong ngày. Nếu hạng phòng chưa cấu hình màu riêng hoặc đang mang màu trắng mặc định (`#ffffff`), số phòng tự động hiển thị màu đen chuẩn (`#000000`) thay vì màu trắng.

    - Chức năng gạch chân số phòng khi ngày mai có khách (`isArrivingTomorrow` -> `underline font-black`): hoạt động đồng bộ trên cả Card View và cả 2 List/Table View.



- **Section 4 – Filter "Danh sách phòng đã đến" chỉ theo ngày đang xem (Task #127)**:

  - **Vấn đề**: Khi xem Room Map ngày 11/8/2026, danh sách "Phòng đã đến" hiển thị cả các phòng đang ở có ngày đến trước ngày 11 (như ngày 10/8 thuộc GAL2, GAL3) do điều kiện `isRoomInhouseOnDate` lọc theo `arrival <= date && departure >= date`.

  - **Khắc phục ([CheckInPage.vue](file:///d:/PMS/frontend/src/pages/reservation/CheckInPage.vue))**:

    - Chuẩn hóa điều kiện trong `daDenBookings`:

      - Khi ở chế độ phòng đã đến (`isArrivalMode`): Chỉ hiển thị các phòng có `status === 1` VÀ `normalizeDate(room.arrival_date || room.actual_arrival_date) === normalizeDate(searchDate.value)`. Các phòng check-in từ ngày trước bị loại bỏ 100%.

      - Khi ở chế độ phòng đang ở (`isOccupiedMode`): Giữ nguyên hiển thị tất cả các phòng đang lưu trú theo `isRoomInhouseOnDate`.

      - Khi ở chế độ phòng đã trả (`isDepartureMode`): Lọc theo `room.status === 2` và `departure_date === searchDate.value`.

    - Đồng bộ hiển thị ngày đến trên dòng cha (Parent Row) của bảng: Hiển thị theo ngày đến của các phòng thực tế đang hiển thị trong nhóm (`booking.booking_rooms?.[0]?.arrival_date || booking.arrival_date`), tránh tình trạng phòng con đến ngày 11 nhưng dòng cha hiển thị ngày 10 của booking tổng.



- **Đồng bộ Tooltip & Màu sắc trạng thái Kế Hoạch Phòng (Room Plan) với Sơ Đồ Phòng (Room Map) ([RoomPlanPage.vue](file:///d:/PMS/frontend/src/pages/reservation/RoomPlanPage.vue))**:

  - **Tooltip chi tiết khi hover**:

    - Thay thế modal trắng cũ bằng Tooltip Dark theme chuẩn (`bg-[#2e2e2e]`, text `#f1f5f9`, border `border-neutral-700/60`, rounded-xl, shadow-2xl, w-[320px]) đồng bộ 100% với Sơ đồ phòng.

    - Hiển thị đầy đủ thông tin: 🟢 Ngày đến - 🔴 Ngày đi, Mã ĐK, Tên ĐK, Tên khách, Hạng phòng & Số phòng, Đêm, Số lượng khách (👤 👶), Giờ đến & Giá phòng (amber-400), Quy cách phòng & Đêm, Giá/R/N, Tên công ty, Ghi chú / Yêu cầu và Danh sách chi tiết tên từng khách lưu trú.

  - **Màu sắc thanh đặt phòng (Booking Fill Color)**:

    - Đồng bộ mã màu nền booking: Ưu tiên `booking_color` (hoặc `ColorDefaultBookingRoomMap` `#97D5FF`), giúp màu phòng trên Kế hoạch phòng liên kết đồng nhất với Sơ đồ phòng.

  - **Màu sắc viền đáy trạng thái phòng (Status Bottom Indicators)**:

    - Phòng đã nhận phòng (`InHouse` / `status === 1`): Hiển thị đồng thời cả 2 trạng thái: Nửa trái màu **Xanh (🟢 Đến / Check-in - `bg-emerald-500`)**, Nửa phải màu **Đỏ (🔴 Đi / Check-out - `bg-red-500`)** tương ứng với 2 chấm xanh và đỏ trên Sơ đồ phòng.

    - Phòng chưa nhận phòng (`status === 0` / Đặt trước / Guaranteed): Hiển thị 100% màu **Xanh (🟢 Phòng đến - `bg-emerald-500`)**, không hiển thị màu đỏ do khách chưa làm thủ tục nhận phòng.

    - Phòng đã trả phòng (`CheckedOut` / `status === 2`): Hiển thị màu **Xám (`bg-slate-400`)**.



- **Fix hiển thị phòng có khách đến vào ngày mai trên Sơ đồ phòng (Room Map)**:

  - **Vấn đề**: Đặt phòng đến vào ngày mai (VD: booking GAL2 nhận ngày 10/08/2026 khi ngày hệ thống là 09/08/2026) nhưng số phòng trên Sơ đồ phòng không được gạch chân (`101 (gạch chân) - Phòng khách đến vào ngày mai` theo Trợ giúp), không hiện tooltip và không mở được booking khi double click.

  - **Backend ([RoomController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/RoomController.php), [RoomResource.php](file:///d:/PMS/backend/app/Http/Resources/RoomResource.php))**:

    - Thêm truy vấn `$bookingRoomsTomorrow` với điều kiện `arrival_date = systemDate + 1` và `registrationStatus->is_availability = 1`.

    - Gán `$room->is_arriving_tomorrow = true`, kèm payload `$room->tomorrow_booking`.

    - Nếu phòng hôm nay trống, gán bổ sung các thông tin đặt phòng ngày mai (`booking_code`, `booking_id`, `guest_name`, `arrival_date`, `departure_date`, `rate`,...) để phục vụ hiển thị Tooltip và Double-click.

    - Cập nhật `RoomResource.php` trả về `is_arriving_tomorrow` và `tomorrow_booking`.

  - **Frontend ([RoomMapPage.vue](file:///d:/PMS/frontend/src/pages/reservation/RoomMapPage.vue))**:

    - Sửa `isArrivingTomorrow(room)`: loại bỏ điều kiện chặn `if (!isReserved) return false` (do phòng trống chưa có khách hôm nay có `status === 'available'`), ưu tiên kiểm tra `room.is_arriving_tomorrow === true`.

    - Cập nhật `showTooltip()` cho phép kích hoạt tooltip khi phòng có khách đến ngày mai hoặc có `booking_code`.

    - Cập nhật chế độ xem dạng danh sách (List View / Table View) hiển thị gạch chân số phòng khi `isArrivingTomorrow(room)`.



- **Fix trạng thái chấm xanh (🟢 Phòng đến) và chấm đỏ (🔴 Phòng đi) trên Sơ đồ phòng ([RoomMapPage.vue](file:///d:/PMS/frontend/src/pages/reservation/RoomMapPage.vue))**:

  - **Khắc phục**:

    - Khôi phục chuẩn màu gốc Tailwind (`bg-emerald-500` cho chấm xanh, `bg-red-500` cho chấm đỏ).

    - **Phòng chưa nhận phòng (Đặt trước - 1005, 1006, 1105)**: Chỉ hiển thị chấm xanh (🟢 Phòng đến) ở góc trên-trái, tuyệt đối không hiển thị chấm đỏ (🔴 Phòng đi) do khách chưa làm thủ tục nhận phòng.

    - **Phòng đã nhận phòng (Đang ở - 105, 106)**: Hiển thị đầy đủ cả 2 chấm ở hai bên (trái: chấm xanh đến hôm nay; phải: chấm đỏ đi).

    - **Phòng trống đến ngày mai (205, 206)**: Không hiển thị chấm hôm nay mà giữ gạch chân số phòng theo quy ước.



- **Verification**: `npm run build` ✅ | `db:seed HotelDefinitionSeeder` ✅



---



## [2026-09-08] - Chuẩn hóa toàn bộ Master Data thông tin khách hàng theo file Excel chuẩn

### Module: Khách hàng & Đặt phòng / Master Data & Multi-DB Seeders ([GuestDefinitionSeeder.php](file:///d:/PMS/backend/database/seeders/GuestDefinitionSeeder.php), [GuestDefinitionController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/GuestDefinitionController.php), [ResidenceType.php](file:///d:/PMS/backend/app/Models/ResidenceType.php), [GuestInfoModal.vue](file:///d:/PMS/frontend/src/pages/reservation/components/GuestInfoModal.vue), [GuestDetailModal.vue](file:///d:/PMS/frontend/src/pages/reservation/components/GuestDetailModal.vue))



- **Đã hoàn thành**:

  - **Tách riêng và chuẩn hóa đầy đủ các bảng danh mục khách hàng theo file Excel [ĐỊNH NGHĨA THÔNG TIN KHÁCH HÀNG.xlsx](file:///d:/PMS/ĐỊNH%20NGHĨA%20THÔNG%20TIN%20KHÁCH%20HÀNG.xlsx)**:

    - **`residence_types` (THƯỜNG TRÚ TẠM TRÚ)**: Tạo migration `2026_09_08_120000_create_residence_types_table.php` và Model `ResidenceType.php`. Chạy migration tạo bảng riêng biệt trên tất cả chi nhánh. Nạp 3 bản ghi: `Địa chỉ thường trú (Thường trú)`, `Địa chỉ tạm trú (Tạm trú)`, `Địa chỉ khác (Khác)`.

    - **`guest_titles` (DANH XƯNG)**: Đồng bộ chính xác 6 danh xưng (`Boy.`, `Girl.`, `Inf`, `Kid.`, `Mr.`, `Ms.`), dọn dẹp các mã mẫu cũ.

    - **`border_gates` (CẢNG)**: Nạp đầy đủ 87 cảng biển, sân bay, cửa khẩu từ file Excel (`STS`, `SNB`, `CNT`, `CSG`,...).

    - **`entry_purposes` (MỤC ĐÍCH)**: Nạp đầy đủ 14 mục đích lưu trú chuẩn (`DL`, `CT`, `TM`, `MK`, `HN`, `TT`, `VT`, `DT`, `BC`, `DC`, `HT`, `KH`, `LD`, `TH`).

    - **`guest_types` (LOẠI KHÁCH)**: Nạp 5 cấp bậc phân loại khách chuẩn (`VIP1`, `VIP2`, `VIP3`, `VIP4`, `RegularGuest`). Đồng bộ tường minh ID khớp 100% file Excel (`id = 1, 2, 3, 4, 6`), giải quyết lỗi auto-increment làm lệch ID của `RegularGuest` thành 5.

    - **`id_types` (LOẠI GIẤY TỜ)**: Nạp 4 loại giấy tờ chuẩn (`CCCD`, `Passport`, `GPLX`, `Other`).

    - **`nationalities` (QUỐC TỊCH)**: Cập nhật nạp chính xác **252 bản ghi theo đúng số thứ tự và ID từ sheet QUỐC TỊCH** (bắt đầu bằng `id = 1`: `---` Người nước ngoài, `id = 245`: `VNM` Việt Nam, kết thúc ở `id = 252`: `ZWE` Zimbabwe), loại bỏ 256 dòng từ seeder merged countries cũ.

  - **Backend Seeder & API**:

    - Chuyển đổi [GuestDefinitionSeeder.php](file:///d:/PMS/backend/database/seeders/GuestDefinitionSeeder.php) sang **100% mảng PHP thuần (Hardcoded standard arrays)** tự đóng gói, không phụ thuộc file ngoài hay thư viện đọc Excel/JSON.

    - Cập nhật [NationalitySeeder.php](file:///d:/PMS/backend/database/seeders/NationalitySeeder.php) và file dữ liệu [merged_countries.json](file:///d:/PMS/backend/database/seeders/data/merged_countries.json) đồng bộ 252 quốc tịch theo chuẩn file Excel.

    - Chạy nạp đồng bộ thành công trên toàn bộ 8 database chi nhánh (`pms_hkt1`, `pms_hkt2`, `pms_hkt3`, `pms_hkt4`, `pms_gkt6`, `pms_hkt5`, `pms_loloee`, `pms_hkt8`).

    - Bổ sung `residence_types` vào API `GET /api/guest-definitions` và method `residenceTypes()` trong [GuestDefinitionController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/GuestDefinitionController.php).

  - **Frontend ([GuestInfoModal.vue](file:///d:/PMS/frontend/src/pages/reservation/components/GuestInfoModal.vue), [GuestDetailModal.vue](file:///d:/PMS/frontend/src/pages/reservation/components/GuestDetailModal.vue))**:

    - Thay thế các tùy chọn hardcode bằng dữ liệu động `residence_types` trả về từ API (`Thường trú`, `Tạm trú`, `Khác`).

    - Cập nhật danh sách danh xưng chuẩn (`Boy.`, `Girl.`, `Inf`, `Kid.`, `Mr.`, `Ms.`).

    - Hỗ trợ fallback giữ nguyên dữ liệu lịch sử nếu khách hàng cũ có giá trị tùy chỉnh.

- **Kiểm tra**:

  - `php artisan test --filter=GuestDefinitionMasterDataTest`: 19/19 assertions đạt 100%.

  - Kiểm tra trực tiếp trên Database 8 chi nhánh: `nationalities` có đúng 252 dòng (dòng 1 là "Người nước ngoài"), `guest_types` có đúng 5 dòng với `id = 1, 2, 3, 4, 6`.

  - `npm run build`: Thành công 100%, không phát sinh lỗi template hay cú pháp.



---



## [2026-09-08] - Điều chỉnh menu chuột phải phòng đang ở trên Sơ đồ phòng (Room Map)

### Module: Frontdesk / Reservation / Sơ đồ phòng ([RoomMapPage.vue](file:///d:/PMS/frontend/src/pages/reservation/RoomMapPage.vue))



- **Đã hoàn thành**:

  - **Sửa nút thao tác khi chuột phải vào phòng có khách đang ở (In-house / Occupied)**:

    - Loại bỏ nút "Nhận phòng" hiển thị sai lệch đối với phòng đang có khách lưu trú.

    - Xóa nút pill "Huỷ nhận phòng" trùng lặp ở cuối menu.

    - Hiển thị nút **"Hủy nhận phòng"** trực tiếp trên danh sách menu (giữa "Thông báo" và "In phiếu ăn sáng") khi ngày đến của phòng bằng ngày hệ thống (`canShowUndoCheckinForRoom`).

    - Trường hợp ngày đến nhỏ hơn ngày hệ thống (`arrival_date < system_date`): ẩn hoàn toàn cả nút "Nhận phòng" và "Hủy nhận phòng".

    - Chuẩn hóa hàm so khớp ngày `isRoomNumberRed`: so khớp chuẩn ngày đến với ngày hệ thống để đánh dấu màu đỏ cho phòng nhận trong ngày.

- **Kiểm tra**:

  - `npm run build`: Thành công 100%, không phát sinh lỗi template hay cú pháp.



---



## [2026-09-07] - Tích hợp tự động RbacMatrixSeeder vào luồng Reset Multi-DB

### Module: Hệ thống / RBAC Seeder & Console Commands ([database_domains.php](file:///d:/PMS/backend/config/database_domains.php), [ResetMultiDbCommand.php](file:///d:/PMS/backend/app/Console/Commands/ResetMultiDbCommand.php))



- **Đã hoàn thành**:

  - **Đăng ký seeder vào cấu hình hệ thống**:

    - Bổ sung `Database\Seeders\RbacMatrixSeeder::class` vào mảng `system_seeders` trong [config/database_domains.php](file:///d:/PMS/backend/config/database_domains.php).

  - **Tích hợp tự động vào lệnh Reset Multi-DB ([ResetMultiDbCommand.php](file:///d:/PMS/backend/app/Console/Commands/ResetMultiDbCommand.php))**:

    - Thêm phương thức `seedRbacMatrix()` tự động chạy `db:seed --class=RbacMatrixSeeder` trên `mysql_system`.

    - Gọi tự động ngay sau khi hoàn thành đồng bộ danh sách chi nhánh động (`syncDatabasesToSystemBranches`) và gán quyền Super Admin.

    - Đảm bảo 100% các lần chạy `php artisan db:reset-all --seed-all` (hoặc `target=system`) đều tự động nạp đầy đủ 46 màn hình (184 permissions) và backfill trọn vẹn `branch_role_permissions` trên tất cả chi nhánh phát hiện động (`GKT6`, `HKT5`, `HKT8`...).

- **Kiểm tra**:

  - `php -l`: Cú pháp PHP chuẩn trên cả 2 file.

  - `php artisan test --filter=OrganizationRbacTest`: 15/15 tests đạt (45 assertions).



---



## [2026-09-07] - Tối ưu UI Vị trí công việc theo chi nhánh & Bổ sung nút thu gọn/mở rộng

### Module: Hệ thống / Quản lý Nhân viên ([EmployeeTab.vue](file:///d:/PMS/frontend/src/pages/system/components/EmployeeTab.vue))



- **Đã hoàn thành**:

  - **Khắc phục lỗi tràn UI khối "Vị trí công việc theo chi nhánh"**:

    - Thay thế dropdown select dài dễ bị tràn viền bằng thẻ thông tin **chỉ xem (Read-only Tag)** tinh gọn, bo góc, có icon chức vụ và nhãn phòng ban màu xanh sky nhã nhặn.

    - Thêm `overflow-hidden`, `min-w-0`, `truncate` và `box-border` đảm bảo co giãn hoàn hảo không bao giờ bị tràn khung.

    - Bổ sung padding đáy `pb-12` cho container modal giúp khi cuộn xuống không bị che khuất viền đáy.

  - **Bổ sung nút mũi tên bật/tắt (Collapsible Toggle)**:

    - Cho phép click vào tiêu đề để thu gọn hoặc mở rộng danh sách vị trí chi nhánh.

    - Mũi tên xoay 180 độ có hiệu ứng chuyển động mượt mà, tích hợp badge đếm số chi nhánh đã chọn.

  - **Đồng bộ phân công vị trí tập trung**:

    - Tự động kế thừa chức danh chính của nhân viên sang các chi nhánh được tick chọn.

    - Xác nhận và làm rõ luồng nghiệp vụ: Việc phân công Role và ma trận quyền hạn cho từng vị trí theo chi nhánh được thực hiện tập trung tại **Cơ cấu tổ chức** (modal Sửa ứng dụng & Cấu hình). Modal nhân viên chỉ đóng vai trò xem thông tin phân công.

- **Kiểm tra**:

  - `npm run build`: Thành công 100%.



---



## [2026-09-07] - Tự động sinh Username theo Tên nhân viên & Hiển thị rõ ràng Mật khẩu mặc định

### Module: Hệ thống / Quản lý Nhân viên & Xác thực (`EmployeeTab.vue`, `AuthController.php`, `UserController.php`)



- **Đã hoàn thành**:

  - **Trường Tên đăng nhập (Username) trong modal Thêm/Sửa nhân viên ([EmployeeTab.vue](file:///d:/PMS/frontend/src/pages/system/components/EmployeeTab.vue))**:

    - Bổ sung ô nhập `Tên Đăng Nhập (Username) *` vào form modal nhân viên.

    - Tự động sinh username (`toUsernameSlug`): Khi người dùng nhập "Tên Nhân Viên" (ví dụ: `Thảo Vy` $\rightarrow$ `thaovy`, `Nguyễn Văn A` $\rightarrow$ `nguyenvana`), hệ thống tự động bóc tách dấu tiếng Việt, viết thường không dấu và điền sẵn vào ô Username.

    - Cho phép người dùng tùy ý chỉnh sửa lại username nếu muốn.

    - Hiển thị cột `Tên Đăng Nhập` (Username) ngay sau cột Tên Nhân Viên trên bảng danh sách nhân viên để người quản trị dễ dàng tra cứu.

  - **Minh bạch Mật khẩu mặc định (Email)**:

    - Khu vực mật khẩu khởi tạo được đóng khung nổi bật với badge: `Mật khẩu mặc định: [email nhân viên]`.

    - Placeholder hiển thị động: `Mặc định nếu để trống: [email nhân viên]`.

    - Kèm ghi chú rõ ràng: `Lưu ý: Nếu để trống ô này, mật khẩu đăng nhập ban đầu sẽ là Email của nhân viên. Hệ thống sẽ bắt buộc đổi mật khẩu ở lần đăng nhập đầu tiên.`

    - Nút `Đặt Lại Mật Khẩu`: Cập nhật popup xác nhận và toast thông báo hiển thị chính xác địa chỉ email nhân viên được đặt làm mật khẩu.

  - **Xác thực đăng nhập linh hoạt ([AuthController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/AuthController.php))**:

    - Nâng cấp endpoint `login` hỗ trợ đăng nhập linh hoạt bằng cả **Username** hoặc **Email** (tìm theo `username` hoặc `email` và so khớp mật khẩu bằng `Hash::check`), giúp người dùng đăng nhập thuận tiện, không bị nhầm lẫn.

- **Kiểm tra**:

  - `npm run build`: Thành công 100%, không lỗi template hay syntax.

  - `php artisan test --filter=OrganizationRbacTest`: 15/15 tests passed (45 assertions).

  - `php artisan test --filter=login`: 2/2 tests passed (3 assertions).

- **Trạng thái hiện tại**: Hoàn thành 100%.



## [2026-09-07] - Hoàn thiện RBAC đa ứng dụng, phân quyền kho theo chi nhánh & loại bỏ mã cứng

### Module: Hệ thống / Phân quyền RBAC (`User.php`, `UserOrganizationController.php`, `BranchRolePermissionController.php`, `EmployeeTab.vue`, `OrgStructureTab.vue`, `ForcePasswordChange.php`, `UserController.php`, migration refinements)



- **Đã hoàn thành**:

  - **Mở rộng ma trận phân quyền chi tiết 46 màn hình nghiệp vụ (184 permissions) theo chuẩn [image3.png](file:///d:/PMS/.agents/scratch/docx_media/word/media/image3.png) & thực tế khách sạn**:

    - Nâng cấp [RbacMatrixSeeder.php](file:///d:/PMS/backend/database/seeders/RbacMatrixSeeder.php): tách nhỏ và chi tiết hóa từ 26 lên **46 màn hình nghiệp vụ chuyên sâu** thuộc 6 phân hệ lớn:

      - **FO (14 màn hình)**: Đặt phòng (Booking), Đặt cọc (Deposit), Hóa đơn (Bill/Folio), Công ty & Đại lý (Company/TA), Phân bổ quỹ phòng (Allotment), Hồ sơ khách hàng (Guest Profile), Sơ đồ phòng (Rack/FrontDesk), Giao nhận phòng (Check-in/out), Chuyển phòng (Room Move), Khóa phòng OOO/OOS (Room Lock), Xử lý No-show, Thanh toán & Thu tiền, Cấn trừ công nợ (Debt Settlement), Dịch vụ phòng.

      - **HK (7 màn hình)**: Tổng quan buồng, Trạng thái phòng (Room Status), Phân công dọn phòng (Assignment), Đồ thất lạc (Lost & Found), Hóa đơn minibar/giặt ủi (Service Bills), Kho buồng & vải vóc, Báo cáo buồng phòng.

      - **FB (5 màn hình)**: Tổng quan nhà hàng (Outlets), Order & gọi món, Thanh toán F&B, Menu & sản phẩm, Tiệc & sự kiện.

      - **MGMT (8 màn hình)**: Báo cáo tổng hợp, Báo cáo doanh thu, Báo cáo công suất, Báo cáo khách đến, Báo cáo khách đi, Báo cáo khách lưu trú, Báo cáo hủy phòng, Lịch sử thao tác (Audit Logs).

      - **CONFIG (8 màn hình)**: Thông tin khách sạn, Hạng phòng & loại phòng, Danh mục buồng phòng (Rooms), Bảng giá phòng (Rate Plans), Dịch vụ khách sạn, Ca làm việc (Shifts), Nguồn khách & thị trường (Markets), Ngày hệ thống (Night Audit).

      - **SYSTEM (4 màn hình)**: Quản lý nhân viên, Vai trò & phân quyền, Chi nhánh, Cài đặt hệ thống.

    - Mỗi màn hình đều có đủ 4 actions checkbox độc lập (`View`, `Add`, `Delete`, `Edit`).

    - Nạp thành công **4,256 bản ghi** `branch_role_permissions` trên 8 chi nhánh, phân quyền sát theo từng cấp bậc (Super Admin, Quản trị chi nhánh, Quản lý, Trưởng bộ phận, Nhân viên).

    - Bảo toàn 100% các mã quyền route hiện có, toàn bộ 15/15 tests `OrganizationRbacTest.php` đạt.



  - **Migration [2026_09_05_110000_patch_organization_rbac_refinements.php](file:///d:/PMS/backend/database/migrations/2026_09_05_110000_patch_organization_rbac_refinements.php) — cải tiến toàn diện**:

    - Mở rộng `positions.code` lên `varchar(100)` tương tự `permissions.code`/`screen_code`.

    - Hàm `backfillCustomRolesAndPositions` chỉ xử lý role đang thực sự được gán cho user (`whereIn user_roles`), bỏ qua role đã có `position_branch_roles`, tìm phòng ban theo `department_scope` hoặc phòng ban active đầu tiên (loại bỏ fallback cứng `OT`).

    - Hàm `backfillSuperAdminAcrossBranches` tìm đúng `position_id` từ `PositionBranchRole` tương ứng chi nhánh thay vì tìm tên vị trí có `ADMIN`/`DIR`.

    - Cả hai hàm dùng `config('database_domains.default_application_code')` thay vì hardcode `'PMS'`.

  - **[User.php](file:///d:/PMS/backend/app/Models/User.php)**:

    - `allPermissions(?$branchId, ?$applicationCode)`: `applicationCode` optional, mặc định từ config; loại bỏ hoàn toàn fallback leo thang quyền khi user đã có vị trí.

    - `hasPermission($code, $branchId, $applicationCode)`: tự suy `applicationCode` từ DB permission tương ứng nếu không truyền.

    - `canPerformHistoricalDateActions($branchId, $applicationCode)`: hàm mới tính quyền thao tác ngày cũ từ `Role.allow_historical_date_actions` theo vị trí + chi nhánh + ứng dụng thực tế (không còn đọc `user.settings` hardcode).

    - `isSuperAdmin()`: dùng join trực tiếp (`position_branch_roles` → `roles`) thay vì whereHas lồng nhau để tránh N+1.

  - **[ForcePasswordChange.php](file:///d:/PMS/backend/app/Http/Middleware/ForcePasswordChange.php)** (mới): middleware chặn mọi API business (trả 423) khi `must_change_password = true`, trừ whitelist `/api/login`, `/api/logout`, `/api/me`, `/api/me/change-password`, `/api/hotel-settings`; đăng ký vào group `api`.

  - **[UserController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/UserController.php)**:

    - Không hardcode `'mysql_system'` và `'NB'` prefix: đọc từ `config('database_domains.system_connection')` và `config('database_domains.employee_code_prefix')`.

    - `username` không bắt buộc nhập; tự động fallback về `email` khi để trống.

    - Unique validation dùng `Rule::unique($system.'.users')` để không bị lỗi khi tên connection thay đổi.

  - **[UserOrganizationController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/UserOrganizationController.php)**:

    - `syncWarehouses`: validate kho phải thuộc chi nhánh đã được gán vị trí; kiểm tra warehouse_id có tồn tại thật trong database chi nhánh (cross-DB query); chặn trùng lặp.

    - Dùng `config('database_domains.default_application_code')` thay vì `'PMS'` hardcode.

  - **[BranchRolePermissionController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/BranchRolePermissionController.php)**:

    - Toàn bộ `application_code = 'PMS'` cứng đổi sang `config('database_domains.default_application_code')`.

  - **[PaymentController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/PaymentController.php) & [BookingRoomServiceController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/BookingRoomServiceController.php)**:

    - `canOperateOldDay()` đổi hoàn toàn sang `$user->canPerformHistoricalDateActions(branchId)` từ Role thực tế, loại bỏ việc đọc `user.settings` và username hardcode.

  - **[http.js](file:///d:/PMS/frontend/src/services/http.js)**: Không còn fallback `'HKT1'` / `'1'` cứng cho header `X-Branch-Code`/`X-Branch-Id`; chỉ gắn khi có giá trị thật trong localStorage.

  - **[company-service.js](file:///d:/PMS/frontend/src/services/company-service.js)**: `fetchWarehouses(branch)` nhận tham số chi nhánh để gửi đúng header; tải kho riêng theo từng chi nhánh.

  - **[EmployeeTab.vue](file:///d:/PMS/frontend/src/pages/system/components/EmployeeTab.vue)**:

    - Kho (`warehousesByBranch`) nạp lazy theo từng chi nhánh, không nạp tất cả ngay khi mở modal.

    - Dropdown "Chi nhánh áp dụng quyền kho" cho phép chọn chi nhánh cụ thể trước khi tick kho.

    - `selectedWarehouses` từ `number[]` đổi thành `{system_branch_id, warehouse_id}[]` để lưu đúng chi nhánh.

    - `syncUserOrganization` gửi đúng `application_code` từ `position.branch_roles` (không hardcode PMS); `application_codes` tự tổng hợp từ assignments + ứng dụng hiện hành.

    - `positionsForBranch` bỏ filter `application_code === 'PMS'`; hiển thị vị trí của mọi ứng dụng cho chi nhánh đó.

    - Mật khẩu không còn bắt buộc khi thêm nhân viên; nếu để trống sẽ dùng email làm mật khẩu mặc định.

    - "Đặt Lại Mật Khẩu" gọi `resetUserPassword()` (endpoint `/api/me/reset-password`), không còn set cứng `password123`.

  - **[OrgStructureTab.vue](file:///d:/PMS/frontend/src/pages/system/components/OrgStructureTab.vue)**:

    - Tab "Người dùng" dùng `position.user_assignments` từ API (eager load) thay vì lọc theo `job_title_code` cũ.

    - Dropdown ứng dụng trong modal "Sửa ứng dụng" khi thay đổi sẽ nạp lại đúng assignment của ứng dụng đó.

  - **[OrganizationController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/OrganizationController.php)**:

    - Mở rộng max `code` validation lên `100`.

    - `syncLegacyDepartments()` tự đồng bộ bảng `departments` cũ vào `organization_departments` khi tải trang tổ chức.

- **[config/database_domains.php](file:///d:/PMS/backend/config/database_domains.php)**:

  - Thêm `default_application_code` (default `PMS`) và `employee_code_prefix` (default `NB`) đọc từ `.env`.

- **Kiểm thử**:

  - `OrganizationRbacTest.php`: **15/15 tests đạt (45 assertions)**, thêm 4 test case mới:

    - Quyền POS không phụ thuộc vào PMS (application riêng biệt).

    - `canPerformHistoricalDateActions` phản ánh đúng cờ Role tại chi nhánh.

    - `ForcePasswordChange` chặn API nghiệp vụ khi `must_change_password = true`.

  - `npm run build`: thành công 100%, không lỗi Vue SFC.

  - `php -l`: không lỗi cú pháp trên 6 file backend được sửa.

- **Trạng thái hiện tại**: Hoàn thành 100% — không còn hardcode chi nhánh, ứng dụng, vị trí hay kho.

- **Kế hoạch tiếp theo**: Kiểm thử E2E trên staging; nghiệm thu thao tác ngày cũ theo Role thực tế.



---



## [2026-09-05] - Cập nhật toàn diện Giao diện (UI, Màu sắc, Bố cục, Modals) theo Phân quyền và tạo nhân viên.docx

### Module: Hệ thống / Quản lý Cơ cấu tổ chức, Phân quyền Roles & Quản lý nhân viên (`OrgStructureTab.vue`, `RoleManageTab.vue`, `EmployeeTab.vue`)



- **Đã hoàn thành**:

  - **Trực quan hóa tài liệu mẫu**:

    - Trích xuất toàn bộ 7 ảnh chụp màn hình từ `Phân quyền và tạo nhân viên.docx` làm chuẩn đối chiếu chi tiết (cây tổ chức, modal sửa app, ma trận phân quyền 4 cột, modal nhân viên, danh sách bảng, dropdown vị trí, phân quyền kho & chi nhánh chính).

  - **Cơ cấu tổ chức ([OrgStructureTab.vue](file:///d:/PMS/frontend/src/pages/system/components/OrgStructureTab.vue)) - Hình 1, 2, 3**:

    - Cây phòng ban & vị trí: Cập nhật icon `[-]` nền vuông xanh `#72c6e6`, highlight vị trí được chọn màu xanh `#72c6e6` chữ trắng đậm kèm hiệu ứng mũi tên.

    - Tab "Ứng dụng" / "Người dùng": Thiết kế thẻ ứng dụng hình thoi đặc trưng, hiển thị "Version" và các liên kết thao tác "Xóa" (đỏ) / "Sửa" (xanh).

    - Modal "Sửa ứng dụng" (Hình 2): Banner tiêu đề màu xanh sky `#72c6e6`, bảng cấu hình chọn Role theo chi nhánh với nút "Cấu hình" dạng viên thuốc (pill badge).

    - Modal "Phân quyền" (Hình 3): Banner tiêu đề `#72c6e6`, thanh phụ "Màn hình", ma trận 4 cột quyền chuẩn xác: `View` | `Add` | `Delete` | `Edit`.

  - **Quản lý vai trò & Phân quyền ([RoleManageTab.vue](file:///d:/PMS/frontend/src/pages/system/components/RoleManageTab.vue)) - Hình 3**:

    - Cột danh sách vai trò: Nút `+ Thêm` màu `#0ea5e9`, danh sách vai trò sạch sẽ với thanh chỉ báo active màu xanh.

    - Bảng ma trận quyền: Gom nhóm theo Module với biểu tượng `[-]` nền xám bo góc, tiêu đề Module in hoa đậm, 4 cột thao tác theo đúng thứ tự tài liệu: `View` | `Add` | `Delete` | `Edit`.

    - Chuẩn hóa header bộ lọc: Dropdown chọn Ứng dụng & Chi nhánh gọn gàng, badge ngày giờ lịch sử, đồng bộ toàn bộ modals (Thêm Role, Nhân bản Role, Thêm màn hình) sang banner `#72c6e6`.

  - **Quản lý nhân viên ([EmployeeTab.vue](file:///d:/PMS/frontend/src/pages/system/components/EmployeeTab.vue)) - Hình 4, 5, 6, 7**:

    - Bảng danh sách nhân viên (Hình 5): Căn chỉnh 9 cột mặc định (`Mã NV`, `Tên NV`, `Vị trí`, `Bộ phận`, `Ngày sinh`, `Điện thoại`, `Email`, `Địa chỉ`, `Xóa`), ẩn mặc định 2 cột thừa (tên đăng nhập, chữ ký) nhưng vẫn cho bật qua bánh răng cài đặt.

    - Cột xóa: Đổi nút xóa hình khối xanh cũ thành icon thùng rác đỏ trực tiếp trên ô bảng chuẩn Hình 5.

    - Thanh công cụ: Nút tìm kiếm và nút tròn `(+) Thêm` màu `#72c6e6`.

    - Modal "Chỉnh Sửa / Thêm Nhân Viên" (Hình 4, 6):

      - Form 2 cột với nền input vàng kem nhẹ `#fffbeb` cho các trường nhập liệu.

      - Dropdown Vị trí công việc lọc động theo đúng Bộ phận được chọn (Hình 6).

      - Thẻ Chữ ký: Khung viền nét đứt với icon tròn `+` và nút `Chọn Ảnh`, tích hợp xem trước và icon xóa/xem (Hình 4).

      - Footer: Switch iOS bật/tắt "Người Sử Dụng", nút "Đặt Lại Mật Khẩu", nút Cancel, nút Lưu xanh `#72c6e6`, và nút trợ giúp màu cam `?` bo tròn ở góc trái (Hình 4).

    - Tab "Phân quyền đặc thù" (Hình 7):

      - Bảng Chi nhánh với highlight dòng đang chọn bằng màu `#99cff5` nhạt.

      - Switch iOS bật/tắt "Chi Nhánh Chính".

      - Bảng phân quyền kho chia 3 cột checkboxes gọn gàng mang tiêu đề "Phân Quyền Kho Cho User: [Tên Nhân Viên]".

- **Kiểm tra**:

  - `npm run build`: Thành công 100% không cảnh báo lỗi Vue SFC.

  - `php artisan test tests/Feature/OrganizationRbacTest.php`: 11/11 tests đạt (100%).

- **Trạng thái hiện tại**: Hoàn thành 100%.



---



## [2026-09-05] - Chuẩn hóa Cơ cấu tổ chức, Phân quyền RBAC đa chi nhánh và Quản lý nhân viên

### Module: Hệ thống / Phân quyền & Quản lý nhân viên (`OrganizationController.php`, `BranchRolePermissionController.php`, `UserOrganizationController.php`, `User.php`, `EmployeeTab.vue`, `OrgStructureTab.vue`, `RoleManageTab.vue`, `ForceChangePasswordModal.vue`, `App.vue`)



- **Đã hoàn thành**:

  - **Bảo mật & Xác thực**:

    - [AuthController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/AuthController.php): Loại bỏ triệt để việc log plaintext password vào file log; thêm kiểm tra `is_active_user` khi đăng nhập (trả về 403 nếu tài khoản bị khóa/ngừng kích hoạt); bổ sung endpoint đổi mật khẩu `POST /api/me/change-password`; cập nhật `login()` và `me()` tải danh sách chi nhánh và permissions từ schema mới.

    - [EnsureBranchAccess.php](file:///d:/PMS/backend/app/Http/Middleware/EnsureBranchAccess.php): Chặn người dùng có `is_active_user = false` truy cập API nghiệp vụ với HTTP 403.

    - [api.php](file:///d:/PMS/backend/routes/api.php): Đăng ký route `POST /api/me/change-password` và các route quản trị cơ cấu tổ chức, vai trò ma trận, phân quyền kho.

    - [User.php](file:///d:/PMS/backend/app/Models/User.php):

      - Khắc phục lỗ hổng fallback: User đã gán vị trí ở chi nhánh nhưng có quyền rỗng sẽ **không bao giờ** fallback về quyền cũ (loại bỏ nguy cơ leo thang quyền ngoài ý muốn). Chỉ fallback về `user_roles` đối với user chưa hề được gán vị trí trong schema mới.

      - Super Admin tự động bypass và lấy toàn bộ permissions đang hoạt động.

      - `hasBranchAccess()` kiểm tra quyền Super Admin, `user_branch_positions` và `user_branches`.

  - **Migration & Backfill DB**:

    - [2026_09_05_100000_expand_organization_rbac.php](file:///d:/PMS/backend/database/migrations/2026_09_05_100000_expand_organization_rbac.php): Migration gốc mở rộng schema RBAC, tạo cấu trúc cây phòng ban, vị trí công việc, và vai trò chi nhánh.

    - [2026_09_05_110000_patch_organization_rbac_refinements.php](file:///d:/PMS/backend/database/migrations/2026_09_05_110000_patch_organization_rbac_refinements.php):

      - Tăng kích thước `permissions.code` và `permissions.screen_code` lên `varchar(100)` để chứa đầy đủ mã dài theo cấu trúc module/app.

      - Backfill đầy đủ Super Admin trên mọi chi nhánh hoạt động.

      - Tự động sinh Position và PositionBranchRole cho mọi Custom Role cũ chưa có vị trí.

      - Đã chạy thành công qua `php artisan migrate`.

  - **Backend API Controllers**:

    - [BranchRolePermissionController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/BranchRolePermissionController.php): Tự động thêm tiền tố app (`pos.`, `sys.`) cho màn hình thuộc các ứng dụng ngoài PMS, tránh đè chéo namespace; tự động gán quyền `view` khi chọn bất kỳ hành động `add`/`edit`/`delete`.

    - [UserOrganizationController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/UserOrganizationController.php): Quản lý đồng bộ vị trí nhân viên qua `sync()`; lưu phân quyền kho qua `syncWarehouses()`.

    - [OrganizationController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/OrganizationController.php): Dynamic connection, composite unique mã chức danh theo bộ phận, chống trùng lặp chi nhánh.

  - **Giao diện Frontend**:

    - [EmployeeTab.vue](file:///d:/PMS/frontend/src/pages/system/components/EmployeeTab.vue):

      - Chuyển hoàn toàn sang lưu quyền nhân viên qua API `syncUserOrganization` (`POST /api/users/{id}/organization/sync`), loại bỏ triệt để việc gọi API cũ `syncUserBranches` và `syncUserRoles`.

      - Danh sách bộ phận và vị trí công việc được nạp động từ cây tổ chức (computed), loại bỏ hoàn toàn các mã chức danh cũ hardcode (`RL016`, `RL017`,...).

      - Danh sách kho được lấy động từ API `/api/warehouses`.

      - Phân quyền kho được lưu theo từng chi nhánh qua API `syncUserWarehouses` (`POST /api/users/{id}/warehouses/sync`) vào bảng `user_warehouse_permissions`.

    - [ForceChangePasswordModal.vue](file:///d:/PMS/frontend/src/components/ForceChangePasswordModal.vue) & [App.vue](file:///d:/PMS/frontend/src/App.vue):

      - Modal bắt buộc đổi mật khẩu lần đầu khi `must_change_password === true`, gắn toàn cục tại `App.vue`, không thể đóng/bỏ qua, tích hợp nút đăng xuất an toàn.

    - [company-service.js](file:///d:/PMS/frontend/src/services/company-service.js): Export `fetchWarehouses`, `syncUserWarehouses`, `changeUserPassword`.

- **Kiểm tra & Kiểm thử tự động**:

  - [OrganizationRbacTest.php](file:///d:/PMS/backend/tests/Feature/OrganizationRbacTest.php): Bộ kiểm thử hoàn chỉnh 11 kịch bản nghiệp vụ:

    1. Một nhân viên có vị trí công việc khác nhau tại từng chi nhánh.

    2. Tài khoản chưa kích hoạt / bị khóa (`is_active_user = false`) bị từ chối đăng nhập với HTTP 403.

    3. Không lưu mật khẩu thô vào file log khi đăng nhập.

    4. Cùng một nhân viên nhận bộ quyền hoàn toàn khác nhau tại Chi nhánh A và Chi nhánh B.

    5. Cấp quyền Add/Edit/Delete tự động kéo theo quyền View của màn hình tương ứng.

    6. Super Admin có toàn quyền trên toàn bộ chi nhánh.

    7. Endpoint đổi mật khẩu `/api/me/change-password` xác thực mật khẩu cũ và cập nhật mật khẩu mới.

    8. Fallback tương thích ngược về `user_roles` cũ nếu nhân viên chưa được gán vị trí theo schema mới.

    9. Quyền rỗng tại chi nhánh KHÔNG fallback về legacy roles gây nguy cơ leo thang quyền.

    10. Thêm màn hình non-PMS tự động tiền tố hóa mã permission chống trùng lặp.

    11. Endpoint sync-warehouses lưu chính xác danh sách kho vào DB.

  - Kết quả chạy test: `11 passed, 33 assertions (100%)`.

  - Build frontend Vite (`npm run build`): Thành công 100% không lỗi.

- **Tài liệu bàn giao**:

  - Đã cập nhật toàn diện [RBAC_ORGANIZATION_IMPLEMENTATION_REVIEW.md](file:///d:/PMS/RBAC_ORGANIZATION_IMPLEMENTATION_REVIEW.md) phản ánh đúng trạng thái đã hoàn tất toàn bộ 7 điểm hiệu chỉnh.

- **Trạng thái hiện tại**: Đã hoàn thành 100% tất cả các yêu cầu rà soát và sửa đổi.

- **Kế hoạch tiếp theo**: Sẵn sàng triển khai nghiệm thu và kiểm tra người dùng cuối.



---



## [2026-09-05] - Loại bỏ triệt để phòng đã chuyển (status = 100) khỏi Tab Phòng đến (Sang ngày)

### Module: Frontdesk / Sang ngày (`DayClosePage.vue`)



- **Đã hoàn thành**:

  - Khắc phục lỗi tab "Phòng đến" và biến đếm `arrivalCount` trên trang Sang ngày (`DayClosePage.vue`) lấy cả các phòng đã chuyển (`status = 100` / `move_room`), khiến hệ thống hiểu nhầm còn phòng đến chưa check-in và vô hiệu hóa nút "Sang ngày".

  - Trong [DayClosePage.vue](file:///d:/PMS/frontend/src/pages/frontdesk/DayClosePage.vue):

    - Kiểm tra trực tiếp và loại bỏ ngay lập tức mọi phòng có `Number(r.status) === 100`, `r.status === '100'`, hoặc `r.move_room == 1` ngay từ đầu vòng lặp xử lý danh sách phòng.

    - Cập nhật điều kiện xác định Phòng đến: Bắt buộc phòng phải ở trạng thái Đặt trước chưa check-in (`isBooked && !isCheckedIn`), ngày đến trùng ngày hệ thống (`arrDate === sysDateStr`), và tuyệt đối không phải phòng chuyển (`!isMoved && Number(r.status) !== 100`).

    - Đồng bộ logic loại trừ phòng `status = 100` trên cả biến đếm `arrCount` (nút Sang ngày) và danh sách hiển thị dữ liệu bảng (`processItem`).

- **Kiểm tra**:

  - Build frontend Vite production (`npm run build`): Thành công 100% không lỗi.

  - MariaDB recovery: Đã phục hồi và khởi chạy dịch vụ MariaDB ổn định.

- **Tệp thay đổi**:

  - [DayClosePage.vue](file:///d:/PMS/frontend/src/pages/frontdesk/DayClosePage.vue)



---



## [2026-09-04] - Khắc phục lỗi khóa ngoại 1451 khi sửa số lượng khách / trẻ em trong phòng

### Module: Reservation / Cập nhật phòng (`BookingController.php`)



- **Đã hoàn thành**:

  - Xử lý triệt để lỗi `SQLSTATE[23000]: 1451 Cannot delete or update a parent row (booking_room_guests_guest_id_foreign ON DELETE RESTRICT)` khi cập nhật số lượng khách hoặc trẻ em của phòng.

  - Trong [BookingController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/BookingController.php):

    - Khi giảm số người lớn (`adults`), chỉ gỡ liên kết phòng `$pivotToRemove->delete()`.

    - Đối với bảng `guests`, kiểm tra an toàn: chỉ dọn dẹp profile nếu đó là khách ảo tự sinh (`Guest X`) và không còn bất kỳ liên kết phòng/dịch vụ/thanh toán nào khác (`!BookingRoomGuest::where('guest_id', $gId)->exists()`).

    - Bọc logic dọn dẹp khách ảo trong `try...catch` để việc dọn rác không bao giờ làm gián đoạn hay crash giao dịch lưu đặt phòng.

- **Kiểm tra**:

  - `php -l BookingController.php`: Cú pháp chuẩn, không lỗi.

  - `php artisan test tests/Feature/RoomMoveTest.php`: 10/10 passed.

  - `php artisan test tests/Feature/CheckoutRestoreTest.php`: 4/4 passed.

- **Tệp thay đổi**:

  - [BookingController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/BookingController.php)



---



## [2026-09-04] - Tách danh mục thông tin khách thành 9 bảng Database độc lập

### Module: Reservation / Cấu hình thông tin khách (`GuestDefinitionController.php`, `GuestInfoModal.vue`, `GuestDetailModal.vue`)



- **Đã hoàn thành**:

  - Chuẩn hóa đầy đủ 9 bảng Master Data tương ứng danh sách định nghĩa thông tin khách ProVista:

    1. `guest_titles` (SP8015 - DanhXung): 8 danh xưng chuẩn.

    2. `border_gates` (SP8017 - DanhMucCuaKhau): 13 cửa khẩu quốc tế đường hàng không, đường bộ, đường biển.

    3. `entry_purposes` (SP8019 - DanhMucMucDichLuuTru): 7 mục đích lưu trú/nhập cảnh.

    4. `nationalities` (SP8020 - DanhMucQuocTich): Bảng quốc tịch hiện hữu.

    5. `guest_types` (SP8042 - GuestType): 6 phân loại khách (FIT, GIT, VIP, Crew, Long Stay, Corporate).

    6. `provinces` (SP8047 - Tinh/Thanh Pho): Đã seed 63 tỉnh/thành phố chuẩn Việt Nam.

    7. `districts` (SP8048 - Quận Huyện): Lưu tự động khi người dùng chọn/lưu khách.

    8. `wards` (SP8049 - Phường/ Xã): Lưu tự động khi người dùng chọn/lưu khách.

    9. `id_types` (SP8055 - LoaiGiayTo): 4 loại giấy tờ tùy thân chuẩn (CCCD, CMND, Hộ chiếu, Khác).

  - Backend:

    - Tạo 2 migration `create_guest_definitions_tables` và `create_provinces_districts_wards_tables` chạy trên toàn bộ 9 Database chi nhánh.

    - Tạo Models `GuestTitle`, `BorderGate`, `EntryPurpose`, `GuestType`, `IdType`, `Province`, `District`, `Ward`.

    - Tạo seeders `GuestDefinitionSeeder` và `ProvinceSeeder`, đăng ký vào `BranchDatabaseSeeder`.

    - API `GET /api/guest-definitions` gom dữ liệu 1 request; các endpoint lẻ và `POST /api/geo/sync`.

    - Tự động bóc tách và lưu địa giới hành chính vào `provinces`, `districts`, `wards` khi lưu khách trong `GuestController`.

  - Frontend:

    - Bổ sung `fetchGuestDefinitions()` và `syncGeoData()` vào `booking-service.js`.

    - Cập nhật `GuestInfoModal.vue` & `GuestDetailModal.vue` nạp dữ liệu động cho các dropdown: Danh xưng, Loại giấy tờ, Loại khách, Mục đích, Cửa khẩu (chuyển ô text thành select dropdown).

- **Kiểm tra**:

  - `php artisan migrate:all`: Đạt trên 9/9 database PMS.

  - Seeding: Nạp thành công trên tất cả database chi nhánh.

  - `npm run build`: Hoàn tất thành công, không có lỗi cú pháp hay bundle.

  - Test tinker & scratch `POST /api/geo/sync`: Ghi nhận dữ liệu chuẩn vào DB.

- **Tệp thay đổi**:

  - `backend/database/migrations/2026_09_04_150000_create_guest_definitions_tables.php`

  - `backend/database/migrations/2026_09_04_154000_create_provinces_districts_wards_tables.php`

  - `backend/app/Models/GuestTitle.php`, `BorderGate.php`, `EntryPurpose.php`, `GuestType.php`, `IdType.php`, `Province.php`, `District.php`, `Ward.php`

  - `backend/database/seeders/GuestDefinitionSeeder.php`, `ProvinceSeeder.php`, `BranchDatabaseSeeder.php`

  - `backend/app/Http/Controllers/Api/GuestDefinitionController.php`

  - `backend/app/Http/Controllers/Api/GuestController.php`

  - `backend/routes/api.php`

  - `frontend/src/services/booking-service.js`

  - `frontend/src/pages/reservation/components/GuestInfoModal.vue`

  - `frontend/src/pages/reservation/components/GuestDetailModal.vue`



---

## [2026-09-04] - Sửa chuyển khách sang phòng Inhouse và phân bổ bill theo khách

### Module: Reservation / Chuyển phòng & Hóa đơn (`BookingRoomController.php`, `CheckoutPage.vue`)



- **Đã hoàn thành**:

  - Giữ nguyên khách chính hiện hữu của phòng Inhouse đích; mọi khách mới chuyển tới được thêm dưới dạng khách phụ.

  - Chỉ chuyển các bill đang thuộc phòng nguồn và đúng khách được chọn sang phòng đích; không cập nhật nhầm bill có sẵn của phòng đích.

  - Giữ `RentalRoomId2`, `CustomerId2` là `NULL` đối với bill chưa từng chuyển của phòng đích.

  - Đồng bộ chi tiết dịch vụ theo bill/khách sang phòng đích để phòng cũ không còn hiển thị dịch vụ của khách đã chuyển.

  - Màn Hóa đơn xác định chủ bill theo `Id2` khi bill đã chuyển, nếu chưa chuyển thì dùng `Id1`; bill được tách đúng theo từng khách thay vì gom vào khách chính.

  - Loại khách trạng thái `100` khỏi danh sách khách còn ở của phòng nguồn và luôn ưu tiên hiển thị khách chính phòng đích trước.



- **Kiểm tra**:

  - `RoomMoveTest`: 10/10 test, 66 assertions đạt; có ca kiểm thử riêng cho hai phòng có bill 264.500 và 100.000.

  - Test frontend quyền sở hữu bill và trạng thái checkout: 7/7 đạt.

  - Build frontend Vite và kiểm tra cú pháp PHP đạt.



- **Tệp thay đổi**:

  - `backend/app/Http/Controllers/Api/BookingRoomController.php`

  - `backend/tests/Feature/RoomMoveTest.php`

  - `frontend/src/pages/frontdesk/CheckoutPage.vue`

  - `frontend/src/utils/service-bill-ownership.js`

  - `frontend/tests/service-bill-ownership.test.js`



---

## [2026-09-03] - Đổi trạng thái phòng sau trả phòng thành Trống dơ

### Module: Hóa đơn / Trả phòng (`GuestController.php`)



- **Đã hoàn thành**:

  - Sửa luồng checkout toàn bộ phòng: cập nhật trực tiếp `room_status_code = vacant_dirty` cho phòng thực.

  - Loại bỏ việc gán `status = checkout`, vì mutator của model `Room` chuyển giá trị này thành `turndown`.

  - Giữ nguyên luồng khôi phục checkout: phòng được trả về trạng thái có khách ở khi thao tác hoàn tác thành công.

- **Kiểm tra**:

  - `php -l GuestController.php`: đạt.

  - `CheckoutRestoreTest`: 5 ca đạt.

  - `CheckoutBusinessRulesTest`: 9 ca hiện trả về `403` do quyền API trong môi trường test, không liên quan đến thay đổi trạng thái phòng.

- **Tệp thay đổi**:

  - `backend/app/Http/Controllers/Api/GuestController.php`



---

## [2026-09-03] - Sửa danh sách phòng đích và hiển thị lịch sử chuyển phòng



### Module: Reservation / Chuyển phòng & Booking (`BookingRoomController.php`, `CreateRegistrationPage.vue`)



- **Đã hoàn thành**:

  - Danh sách phòng đích khi chuyển phòng chỉ trả về phòng vật lý `vacant_ready`/`vacant_clean` và còn trống trong toàn bộ giai đoạn ở còn lại.

  - Loại trừ phòng đang ở, phòng trả trong ngày và các phòng chưa sẵn sàng khỏi danh sách phòng trống.

  - Màn hình Booking hiển thị lại phòng cũ đã chuyển (trạng thái `100` - Phòng chuyển) để tra cứu lịch sử.

  - Phòng chuyển được hiển thị chỉ đọc và không cộng lặp vào tổng tiền/tổng số phòng hiện tại.

  - Sửa tiêu đề cột Phòng trong popup Chuyển phòng luôn cố định khi cuộn danh sách.



- **Kiểm tra**:

  - Bộ kiểm thử frontend: 19/19 thành công.

  - Build frontend Vite thành công.

  - Kiểm tra cú pháp PHP và `git diff --check` thành công.



- **Tệp thay đổi**:

  - `backend/app/Http/Controllers/Api/BookingRoomController.php`

  - `frontend/src/pages/reservation/CreateRegistrationPage.vue`

## [2026-09-03] - Điều chỉnh giá tạo đăng ký nhanh từ Kế hoạch phòng

### Module: Reservation / Kế hoạch phòng (`RoomPlanPage.vue`)



- **Đã hoàn thành**:

  - Đổi trường **Rate** từ tổng tiền booking thành **đơn giá một đêm của một phòng**.

  - Khi không chọn Rate Code: tự điền giá phòng chuẩn nếu mọi phòng đã chọn có cùng đơn giá; nếu có bất kỳ mức giá nào khác nhau thì hiển thị `0` để người dùng nhập giá chung.

  - Giá nhập tay được truyền vào từng `room_allocation`, vì vậy nhập `750.000` cho hai phòng sẽ lưu `750.000` cho mỗi phòng/đêm.

  - Giữ nguyên vùng chọn nhiều phòng khi mở menu chuột phải, tránh việc thao tác **Tạo** vô tình chỉ còn một phòng.

  - Rate Code theo ngày (`IsDaily`): hiển thị giá đêm đầu và khóa chỉnh Rate; backend tiếp tục tạo giá theo từng ngày.

  - Rate Code cố định: chỉ cho phép ghi đè giá khi cấu hình `AllowChangeRate = true`; backend tôn trọng giá nhập tay trong trường hợp này.

  - Chuẩn hóa lấy `room_class` và `room_form` từ API để Rate Code tính đúng theo loại/dạng phòng.

- **Kiểm tra**:

  - `node --test`: 19/19 test tính giá và Rate Code đạt.

  - `npm run build`: đạt.

  - `php -l BookingController.php`: đạt.

- **Tệp thay đổi**:

  - `frontend/src/pages/reservation/RoomPlanPage.vue`

  - `backend/app/Http/Controllers/Api/BookingController.php`



---

## [2026-09-03] - Đồng Bộ Chiều Cao Thanh Tầng (Floor Pill) Theo Chiều Cao Phòng (Sơ Đồ Phòng)

### Module: Reservation / Sơ Đồ Phòng (`RoomMapPage.vue`)



- **Đã hoàn thành**:

  - Khắc phục triệt để lỗi thanh chỉ báo Tầng (`.floor-pill`) bị kẹt chiều cao cố định (~65px) không thể thu nhỏ khi người dùng giảm "Chiều cao phòng" xuống 50px hoặc nhỏ hơn.

  - Xây dựng các hàm tính toán style động:

    - [`getFloorPillStyle()`](file:///d:/PMS/frontend/src/pages/reservation/RoomMapPage.vue): Tự động gán `height`, `minHeight`, `maxHeight` bằng chính xác `settings.roomHeight`, bổ sung `box-sizing: border-box`, co giãn padding thông minh (`2px` - `8px`), bo góc tỷ lệ theo chiều cao.

    - [`getFloorTitleStyle()`](file:///d:/PMS/frontend/src/pages/reservation/RoomMapPage.vue) & [`getFloorCountStyle()`](file:///d:/PMS/frontend/src/pages/reservation/RoomMapPage.vue): Tự động co giãn kích cỡ chữ Tầng (`Tầng X`) và số phòng (`(X phòng)`) cân đối từ 8.5px - 13px, căn giữa hoàn hảo.

  - Căn chỉnh hàng hiển thị phòng với `items-center` giúp thanh tầng và các thẻ phòng luôn thẳng hàng đều đặn.

  - Mở rộng thanh trượt "Chiều cao phòng" trong Drawer Cài đặt hiển thị xuống tối thiểu **40px** (trước đây là 50px), cho phép người dùng tùy biến giao diện siêu nhỏ gọn theo ý muốn.

  - Khắc phục triệt để hiện tượng trễ nhịp (delay): Loại bỏ `transition: all 0.35s` trên `.floor-pill` (vốn vô tình gây delay chuyển động chiều cao 0.35 giây so với ô phòng vốn cập nhật tức thì), chuyển sang transition riêng chỉ dành cho hover effects (`transform`, `box-shadow`, `border-color`), giúp thanh Tầng và ô phòng co giãn đồng thời 100% cùng nhịp ở 60fps khi kéo slider.

  - Build kiểm thử Vite thành công 100%.



---



## [2026-09-03] - Nâng Cấp Lệnh Multi-DB Migrate Toàn Bộ (php artisan migrate:all)

### Module: Database / Multi-Tenant Migration Command



- **Đã hoàn thành**:

  - Nâng cấp command [`MigrateMultiDbCommand.php`](file:///d:/PMS/backend/app/Console/Commands/MigrateMultiDbCommand.php):

    - Đăng ký tên lệnh chính thức `php artisan migrate:all` và bí danh `php artisan db:migrate-all`.

    - Hỗ trợ quét tự động (`discoverAllPmsDatabases`) toàn bộ các database `pms_*` trên máy chủ MySQL và bảng `system_branches` (bao gồm các chi nhánh mới như `gkt6`, `hkt5`, `hkt8`, `loloee`...).

    - Tự động phân loại chạy đúng domain: Bảng quản trị hệ thống chạy vào `pms_system`, bảng nghiệp vụ chạy vào từng chi nhánh.

    - **An toàn dữ liệu tuyệt đối**: Không xóa bảng (không reset/fresh), không yêu cầu seeder, chỉ nạp các migration mới còn thiếu.

    - Hỗ trợ tham số mục tiêu: `php artisan migrate:all` (tất cả), `php artisan migrate:all system` (chỉ System), `php artisan migrate:all hkt1` (chỉ 1 chi nhánh).

- **Trạng thái hiện tại**: Đã test chạy thử nghiệm thành công 100% trên cả 9 database PMS hiện có.



---



## [2026-09-07] - Tinh Chỉnh Tooltip Khóa Phòng (Room Plan & Room Map) & Cấu Hình Role Mở Khóa OOO/OOS

### Module: Reservation / Frontdesk / Khóa Phòng (Room Lock) & Cài đặt hệ thống (Hotel Config)



- **Đã xử lý & hoàn thiện**:

  - **1. Tinh Chỉnh Ghi Chú Khóa Phòng Trên Màn Hình Kế Hoạch Phòng ([`RoomPlanPage.vue`](file:///c:/xampp/htdocs/PMS/frontend/src/pages/reservation/RoomPlanPage.vue))**:

    - Bỏ 2 dòng `Tên: ...` và `Loại khóa: ...` trong tooltip khi hover vào dải phòng khóa OOO/OOS.

    - Giữ lại dòng `Ghi chú: [Nội dung ghi chú]`.

    - Bổ sung dòng `Người khóa: [Tên người khóa]` lấy từ trường thông tin người tạo khóa.

  - **2. Bổ Sung Tooltip Ghi Chú Khóa Phòng Trên Sơ Đồ Phòng ([`RoomMapPage.vue`](file:///c:/xampp/htdocs/PMS/frontend/src/pages/reservation/RoomMapPage.vue))**:

    - Hỗ trợ sự kiện hover chuột vào phòng đang khóa (OOO/OOS) hiển thị tooltip tương tự màn hình Kế hoạch phòng: Thời gian khóa (`Từ ngày giờ ~ Đến ngày giờ`), Badge trạng thái khóa (`OOO`/`OOS`), `Ghi chú` và `Người khóa`.

  - **3. Bổ Sung Cấu Hình Phân Quyền Mở Khóa Theo Role ([`HotelDefinitionSeeder.php`](file:///c:/xampp/htdocs/PMS/backend/database/seeders/HotelDefinitionSeeder.php), [`RoomLockController.php`](file:///c:/xampp/htdocs/PMS/backend/app/Http/Controllers/Api/RoomLockController.php))**:

    - Thêm tham số cấu hình:

      - **Tên thông số**: `RoleUserUnlockRoomOOO/OOS`

      - **Giá trị mặc định**: `Admin,FO,FOM,Sales,HK`

      - **Mô tả**: `Danh sách Role user được phép mở khóa phòng OOO/OOS (vd: Admin,FO,FOM,Sales,HK)`

      - Hiển thị trên giao diện cấu hình hệ thống (`is_visible = 1`).

    - Nâng cấp hàm `checkUnlockRolePermission` trong [`RoomLockController.php`](file:///c:/xampp/htdocs/PMS/backend/app/Http/Controllers/Api/RoomLockController.php) kiểm tra so khớp theo **Role/Vai trò** của user (`roles`, `job_title_code`, `job_title`, `department_code`, `department`), không dựa vào tên người dùng cá nhân (username).

  - **4. Sửa Lỗi Hiển Thị Tooltip & Icon Khóa Trên Sơ Đồ Phòng ([`RoomMapPage.vue`](file:///c:/xampp/htdocs/PMS/frontend/src/pages/reservation/RoomMapPage.vue), [`RoomLockController.php`](file:///c:/xampp/htdocs/PMS/backend/app/Http/Controllers/Api/RoomLockController.php), [`RoomController.php`](file:///c:/xampp/htdocs/PMS/backend/app/Http/Controllers/Api/RoomController.php))**:

    - **Hover Tooltip**: Sửa điều kiện `isLockedRoom` trên Sơ đồ phòng: Chỉ hiển thị tooltip khóa khi phòng thực sự đang ở trạng thái OOO/OOS vào ngày đang xem và không có khách đang lưu trú/đặt phòng. Nếu phòng có booking đang ở (như phòng 105 khóa ngày tương lai 13/08), khi hover trên ngày hiện tại vẫn hiển thị chính xác bảng thông tin Booking của khách.

    - **Icon Khóa Phòng**: Sửa logic khi mở khóa phòng ở ngày hiện tại: Backend chỉ giữ trạng thái bảo trì nếu còn lịch khóa đang `Active` trong ngày hôm nay; các lịch khóa trong tương lai (`status = 'New'`) không còn làm kẹt icon ổ khóa ở ngày hiện tại. Đã làm sạch các trạng thái kẹt trong cơ sở dữ liệu.

- **Trạng thái hiện tại**: Đã hoàn tất và kiểm tra build Vite thành công.



---



## [2026-08-28] - Hoàn Thiện & Nâng Cấp Module Tìm Kiếm Chung Chuẩn Thiết Kế Mẫu

### Module: Frontdesk / Reservation - Tìm Kiếm Chung (`/frontdesk?tab=search`, `/reservation?tab=search`)



- **Đã hoàn thành toàn diện theo mẫu [`UI/TÌM KIẾM CHUNG.html`](file:///d:/PMS/UI/TÌM KIẾM CHUNG.html)**:

  - **1. Bộ Lọc Tùy Biến Kéo Thả (Drag & Drop Customizable Filter Bar)**:

    - Hỗ trợ đầy đủ tương tác kéo thả HTML5 (`draggable`) và nút chuyển đổi nhanh (`⇥` chuyển vào nâng cao, `⇤` đưa ra tìm nhanh) cho toàn bộ 16 trường nghiệp vụ ([`GeneralSearchPage.vue`](file:///d:/PMS/frontend/src/pages/frontdesk/GeneralSearchPage.vue)).

    - Cố định thứ tự trường chuẩn (`FIELD_ORDER`: Mã BK, Tình trạng lưu trú, Ref Code, Booking Name, Booking Status, Contact, Booker, Company, Market Segment, Source Code, Reg Date, User Sale,...).

    - Tự động lưu cấu hình vị trí các trường theo từng tài khoản người dùng vào `localStorage` (`pms_general_search_layout_${userId}`).

    - Nút **Bộ lọc nâng cao** hiển thị badge đếm số lượng trường động tương ứng trong bảng điều kiện nâng cao (ví dụ `10`, `12`...).

  - **2. Bộ Chọn Ngày & Toggle Thông Minh**:

    - Nút Toggle switch "Tìm theo ngày" (`use_date`), tự động đồng bộ ngày nghiệp vụ từ hệ thống (`/system-date`).

    - Hiển thị khoảng ngày trực quan kèm nút mở lịch `📅` nhanh.

  - **3. Autocomplete & Gợi Ý Mã Booking (Mã BK)**:

    - Dropdown gợi ý tức thì khi gõ từ khóa Mã BK, hiển thị Mã BK, Tên BK và Mã tham chiếu kèm nút xóa nhanh `×`.

  - **4. Dropdown Quản Lý & Kéo Thả Sắp Xếp Vị Trí Cột (Columns Reordering)**:

    - Chuyển đổi thành **Dropdown menu gắn ngay dưới nút "⚙ Cột hiển thị"** (chuẩn theo ảnh mẫu 1).

    - Tích hợp checkbox ẩn/hiện và hỗ trợ **kéo thả (Drag & Drop) hoặc bấm nút mũi tên `▲`/`▼`** để thay đổi thứ tự các cột trực tiếp.

    - Toàn bộ bảng dữ liệu bên dưới tự động re-render và hiển thị các cột theo đúng thứ tự tùy biến của người dùng, tự động lưu vào `localStorage` (`pms_general_search_columns_${userId}_${tab}`).

  - **5. Bảng Dữ Liệu & Sub-table Phòng Con Gọn Gàng (Compact Sub-table)**:

    - Tinh chỉnh sub-table chi tiết phòng con khi bấm mở rộng `+` trong Tab Đăng Ký thành **bảng gọn gàng, kích thước nhỏ gọn** (chuẩn theo ảnh mẫu 3), không bị tràn 100% chiều ngang.

    - Gom nhóm phòng theo: `Loại Phòng`, `#Phòng`, `#N.Lớn`, `#T.Em`, `Ngày Đến`, `Ngày Đi`, `Mã Giá Phòng`, `Giá Phòng`, `Tổng`.

    - Bổ sung dòng **Tổng cộng ở đáy sub-table** (Tổng số phòng, tổng người lớn, tổng trẻ em, tổng tiền).

    - Bổ sung đầy đủ tính năng **sắp xếp cột (Sorting `↕` / `↑` / `↓`) cho cột `Đêm` / `Số đêm` (`nights`) và `Ngày đi` (`departure_date`)** trên cả 3 tab: **Đăng Ký**, **Phòng**, **Khách** đồng bộ cùng Backend và Frontend.

    - Xử lý **tự động xuống dòng & ngắt chuỗi dài không khoảng trắng (word break / line wrap)** kèm mở rộng không gian hiển thị cho các cột Tên đăng ký (`booking_name`, 240px - 400px), Tên khách (`guest_name`), Loại phòng khởi tạo / thực tế (`room_class_cell`, 170px - 300px), Công ty (`company`), Ghi chú (`note`), Địa chỉ (`address`)... giúp bảng rộng rãi, dễ đọc và không bị tràn kéo dài.

    - **Nút chức năng Top bar & Dropdown Thao tác chuyên biệt theo từng Tab (Ảnh 1, 2, 3, 4)**:

      - Nút **"Nhân bản"**: Chỉ hiển thị ở **Tab Đăng Ký** khi có checkbox được chọn (`tab === 'booking' && selectedCount > 0`), mở modal nhân bản `CopyModal` hỗ trợ chọn ngày đến mới và nhân bản tức thì.

      - Nút **"Thao tác"**: Luôn hiển thị trên thanh công cụ Top Bar. Dropdown menu thiết kế màu trắng sạch sẽ (`#ffffff`, border `#cbd5e1`), icon màu xanh dịu (`#2563eb`), hover êm dịu, không bị chói mắt.

      - Nút **"Nhân bản"**: Luôn hiển thị ở Tab Đăng Ký, tự động đổi màu xám (disabled) khi chưa chọn hoặc chọn nhiều hơn 1 checkbox.

      - **Chức năng "Nhận phòng" (Tab Phòng)**: Cho phép tích chọn **nhiều phòng cùng lúc** (`selectedCount >= 1`), kể cả các phòng chưa gán số phòng (trạng thái Đặt phòng `DP` / `0`), xác nhận nhận phòng hàng loạt và phản hồi chi tiết kết quả.

      - **Modal xác nhận "No Show" (Chuẩn Hình 2)**: Khi bấm `No Show Một Ngày` hoặc `No Show Giai Đoạn`, hiển thị modal popup xác nhận màu xanh chuẩn với 3 tùy chọn tính phí:

        1. `Tính phí tất cả` (`all_charged`)

        2. `Tính phí tiền phòng` (`room_only`)

        3. `không tính phí` (`no_charge`)

        Cùng 2 nút `[Không]` và `[Có]` để thực hiện xử lý no-show đúng tùy chọn tính phí.

      - **Phân tách cơ chế Bộ lọc nhanh vs Bộ lọc nâng cao**:

        - **Bộ lọc nhanh (ở ngoài)**: Khi người dùng nhập/chọn (Mã BK, Tình trạng, ngày...), hệ thống tự động tìm kiếm **Realtime** ngay tức thì.

        - **Bộ lọc nâng cao (ở trong khung Điều kiện nâng cao)**: Người dùng nhập/chọn các trường bên trong khung nâng cao sẽ **không bị realtime nhảy dữ liệu**, dữ liệu được lưu vào bản nháp (`advDraft`). Chỉ khi bấm nút **"Áp dụng"** (hoặc Enter) thì các bộ lọc này mới được thực thi tìm kiếm. Nút **"Xóa lọc"** làm sạch bộ lọc nâng cao.

      - **Submenu "No show"**: Hiển thị dạng flyout mở sang **bên trái** (`right: 100%`) và mũi tên `◀`, không còn bị tràn/che khuất khỏi mép phải màn hình.

      - **Chức năng "Hóa Đơn"**: Bổ sung hỗ trợ đầy đủ các tham số query (`bookingCode`, `booking_code`, `booking_id`, `roomId`, `room_id`) trong [`CheckoutPage.vue`](file:///d:/PMS/frontend/src/pages/frontdesk/CheckoutPage.vue) và [`GeneralSearchPage.vue`](file:///d:/PMS/frontend/src/pages/frontdesk/GeneralSearchPage.vue), giúp khi click mở đúng chính xác booking và phòng được chọn trên màn hình Hóa đơn/Checkout.

      - **Cơ chế giới hạn thao tác theo số lượng checkbox được chọn**:

        - Khi **chưa chọn dòng nào** (`selectedCount === 0`): Các chức năng cần dòng được chọn sẽ hiển thị màu xám disabled trong menu; nút Nhân bản ở ngoài xám disabled; nút Đồ thất lạc luôn click được.

        - Khi tích chọn **nhiều hơn 1 dòng** (`selectedCount > 1`), các chức năng đơn lẻ gồm: **Đăng Ký**, **Hóa Đơn**, **Thông Tin Khách**, **Nhân bản** sẽ tự động **chuyển màu xám (disabled, không cho click)**, riêng **Nhận phòng** và **No Show** cho phép thao tác nhiều phòng cùng lúc.

        - Khi tích chọn **đúng 1 dòng** (`selectedCount === 1`), tất cả chức năng đều sáng lên và hoạt động:

          - `Đăng Ký`: Điều hướng trực tiếp đến đúng phiếu đăng ký / `booking_id` / mã booking đã chọn (ví dụ `GAL1`) trên giao diện tạo/sửa đăng ký.

          - `Hóa Đơn`: Mở trực tiếp màn hình hóa đơn / thanh toán đúng booking và phòng đã chọn.

          - `Thông Tin Khách`: Mở modal chi tiết thông tin khách của booking.

          - `Nhận phòng`: Thực hiện nhận phòng nhanh cho phòng được chọn.

          - `Nhân bản`: Mở modal sao chép booking đã chọn sang ngày đến mới.

      - **Sao lưu & Khôi phục Database Đa Chi Nhánh (Multi-Database Backup & Restore)**:

        - Nâng cấp [`DatabaseBackupController.php`](file:///d:/PMS/backend/app/Http/Controllers/Api/DatabaseBackupController.php) và [`routes/api.php`](file:///d:/PMS/backend/routes/api.php): Hỗ trợ toàn diện 3 cấp độ phạm vi:

          1. **`ALL` (Toàn Bộ Hệ Thống)**: Tự động gom xuất và nạp toàn bộ Database Hệ Thống Chính (`pms_system`) + TẤT CẢ các Database Chi Nhánh con trong 1 file `.sql` duy nhất.

          2. **`SYSTEM` (Database Hệ Thống Quản Trị)**: Xuất và khôi phục riêng Database `pms_system` chứa dữ liệu người dùng, vai trò, chi nhánh...

          3. **Từng Chi Nhánh Con (`HKT1`, `HKT2`...)**: Xuất và khôi phục riêng lẻ từng database nghiệp vụ của chi nhánh đó.

        - **Khôi phục an toàn (Sanitization)**: Tự động loại bỏ các lệnh `CREATE DATABASE` và `USE \`...\`;` khi nạp chi nhánh đơn lẻ, hoặc tự định tuyến nạp theo từng DB khi nạp file tổng hợp `ALL`.

      - **Sửa lỗi hiển thị Thông Báo Booking trên Server ([`BookingNotificationController.php`](file:///d:/PMS/backend/app/Http/Controllers/Api/BookingNotificationController.php) & [`CreateRegistrationPage.vue`](file:///d:/PMS/frontend/src/pages/reservation/CreateRegistrationPage.vue))**:

        - Mở rộng điều kiện lọc API `active`: Trả về toàn bộ thông báo của booking hoặc các thông báo bao quát thời gian lưu trú `arrival_date` $\rightarrow$ `departure_date`, không còn bị chặn khi ngày hiện tại của server khác với ngày tạo thông báo.

        - Kích hoạt gọi `loadActiveBookingNotifications()` ngay sau khi `loadBookings()` tải xong hoặc khi mở booking theo `bookingCode`, đảm bảo thông báo luôn tự động hiển thị popup khi vào xem booking.

      - **Nâng cấp Giao diện Bố cục Thông Tin Đăng Ký ([`CreateRegistrationPage.vue`](file:///d:/PMS/frontend/src/pages/reservation/CreateRegistrationPage.vue))**:

        - Tái cấu trúc thành **Lưới 2 Cột Bento Grid cân xứng & giảm saturation chuyên nghiệp**:

          - **Đồng bộ màu chỉ báo phân khu**: Xanh dương (Thông tin công ty/kênh bán), Tím (Khách & Người liên hệ), Xanh lá (Đặt cọc & Thanh toán), Cam (Ghi chú booking).

          - **Giảm saturation nền**: Toàn bộ input/select dùng nền trắng sạch sẽ `bg-white border-slate-300`, thẻ Đặt cọc tinh gọn `bg-slate-50 border-slate-200` với nút `+ Thêm cọc` thanh lịch.

          - **Phân biệt rõ Editable / Read-only / Calculated fields**:

            - Mã booking: Hiển thị dạng badge read-only xám nhẹ `bg-slate-100 border-slate-200 cursor-default select-all`.

            - Số đêm: Calculated field `2 đêm` tự tính từ ngày lưu trú với nút stepper +/- gọn gàng.

            - Tên đăng ký / Ghi chú: Ô editable viền nét, phản hồi focus mượt mà.

          - **Đồng bộ màu sắc động theo Tùy chỉnh màu nền Topbar (`authStore.settings.topbar_color` / `--pms-custom-theme`)**:

            - Màu nền Topbar Header của Modal Thông tin đăng ký và Modal Đặt Cọc (Thêm/Sửa/Tách/Chuyển cọc) tự động đồng bộ 100% với màu nền Topbar hệ thống (Solid hoặc Gradient như Tinh vân, Đại dương, Hoàng hôn...), tự động chuyển tương phản chữ/icon (`isTopBarThemeDark`).

            - 4 thanh dọc chỉ báo phân khu (Section 1: Công ty/Kênh bán, Section 2: Người liên hệ, Section 3: Đặt cọc, Section 4: Ghi chú) và nút `Cập nhật Booking` ăn theo màu Topbar hệ thống.

            - Nút `Màu BK` trong modal chỉ phục vụ đổi màu thẻ booking trên sơ đồ phòng, không làm ảnh hưởng đến theme màu Topbar của modal.

          - **Tối ưu popup thông báo Booking / Phòng**:

            - Chỉ hiển thị 1 lần khi mở/chuyển sang booking khác hoặc reload trang.

            - Thao tác nội bộ bên trong cùng 1 booking (như mở modal Đặt cọc, thêm/sửa cọc) không bị re-trigger lại popup thông báo.

          - **Footer & Dirty Tracking UX**:

            - Metadata tương phản cao: `👤 testuser • 🕒 28/08/2026 15:19:28`.

            - Hiển thị badge `● Có thay đổi chưa lưu` khi form bị chỉnh sửa.

            - Nút `Cập nhật Booking` tự động disabled nếu form chưa có thay đổi, active sáng màu chủ đạo khi có thay đổi.

    - Tab Phòng ([`sp_041.sql`](file:///d:/PMS/store%20PMS/sp_041.sql)): Gom nhóm Master Booking banner màu xanh nhạt với tổng tiền dịch vụ & tiền thanh toán.

    - Tab Khách ([`sp_043.sql`](file:///d:/PMS/store%20PMS/sp_043.sql)): Đầy đủ 22 trường thông tin khách lưu trú người lớn và trẻ em.

    - Thanh phân trang hiển thị chuẩn PMS (dropdown 50 / 100 / 200 dòng/trang, danh sách nút trang số `1`, `2`, `3`..., nút Trước/Sau và Tổng kết quả).

- **Trạng thái hiện tại**: Hoàn thành 100% các cập nhật: Nhận phòng nhiều phòng hàng loạt, Modal xác nhận No Show theo Hình 2, phân tách Realtime bộ lọc ngoài và Áp dụng thủ công cho bộ lọc nâng cao, fix triệt để lỗi phòng chuyển (status 100) chặn sang ngày, hoàn thiện module Sao lưu & Khôi phục Database Đa Chi Nhánh hỗ trợ ALL, SYSTEM và từng chi nhánh, popup thông báo booking hiển thị chuẩn 1 lần không re-trigger khi đặt cọc, đồng bộ màu Modal Thông tin đăng ký & Modal Đặt Cọc chuẩn theo "Tùy chỉnh màu nền Topbar" hệ thống, build Vite production thành công không lỗi.

- **Kế hoạch tiếp theo**: Tiếp tục hỗ trợ kiểm thử và hoàn thiện các nghiệp vụ tiếp theo.



---



## [2026-08-24] - [Giai Đoạn 1] Xây Dựng Khung Nền Tảng Phân Quyền & Giao Diện Cấu Hình Nhân Viên

### Module: System / Quản Trị Nhân Viên & Phân Quyền (Bước 1)



- **Công việc đã làm ở Bước 1**:

  - **Backend API Routes**:

    - Khởi tạo các API endpoints phục vụ cấu hình phân quyền ([`RoleController.php`](file:///d:/PMS/backend/app/Http/Controllers/Api/RoleController.php), [`UserPermissionController.php`](file:///d:/PMS/backend/app/Http/Controllers/Api/UserPermissionController.php)).

    - Đăng ký middleware kiểm tra quyền cơ bản ([`RequirePermission.php`](file:///d:/PMS/backend/app/Http/Middleware/RequirePermission.php)).

  - **Frontend UI Setup**:

    - Dựng giao diện tab **Phân quyền đặc thù** trong [`EmployeeTab.vue`](file:///d:/PMS/frontend/src/pages/system/components/EmployeeTab.vue) (bảng Chi nhánh, toggle Chi nhánh chính, danh sách kho).

    - Tạo composable [`usePermission.js`](file:///d:/PMS/frontend/src/composables/usePermission.js) và trang 403 [`ForbiddenPage.vue`](file:///d:/PMS/frontend/src/pages/ForbiddenPage.vue).

  - **Trạng thái**:

    - Mới chỉ là **bước đầu tiên (khung nền tảng kỹ thuật và UI mẫu)**, **chưa gán phân quyền thực tế** cho nhân viên nào.

    - Tất cả tài khoản hiện tại vẫn đang truy cập 100% tất cả các chức năng và chi nhánh bình thường.

    - Tạm dừng phần phân quyền tại đây để chuyển sang làm các nghiệp vụ khác.



---



## [2026-08-21] - Triển Khai Hệ Thống Phân Quyền Toàn Diện (RBAC & Multi-Branch Permissions)

### Module: RBAC / Authentication, Authorization & Phân Quyền Theo Chi Nhánh



- **Đã hoàn thành**:

  - **Backend Authorization & Permission Middleware**:

    - Tạo mới middleware [`RequirePermission.php`](file:///d:/PMS/backend/app/Http/Middleware/RequirePermission.php):

      - Tự động kiểm tra quyền user theo chi nhánh cụ thể từ request header/attributes (`_branch_id`).

      - Cho phép Super Admin bypass tự động.

      - Hỗ trợ nhiều permission với logic OR (`->middleware('permission:fo.booking.create,fo.booking.edit')`).

    - Đăng ký alias `permission` trong [`bootstrap/app.php`](file:///d:/PMS/backend/bootstrap/app.php).

    - Cập nhật [`AuthController.php`](file:///d:/PMS/backend/app/Http/Controllers/Api/AuthController.php) hàm `me()`: Trả về đầy đủ `permissions`, `branches`, `active_branch`, `roles` theo từng chi nhánh khi chuyển đổi hoặc refresh trang.

    - Bảo vệ toàn diện các API routes nhạy cảm trong [`api.php`](file:///d:/PMS/backend/routes/api.php) (Bookings, BookingRooms, Check-in, Check-out, Payments, HK Assignment, System Users, System Branches).

  - **Frontend Permission System & Composables**:

    - Nâng cấp [`usePermission.js`](file:///d:/PMS/frontend/src/composables/usePermission.js): Cung cấp `can()`, `canAny()`, `canAll()`, `isSuperAdmin`, `isAdmin`.

    - Nâng cấp [`auth-store.js`](file:///d:/PMS/frontend/src/stores/auth-store.js):

      - `initialize()`: Lấy và lưu trữ permissions/roles/branches tương ứng theo chi nhánh đang active.

      - `switchBranch()`: Tự động gọi API ngầm refresh lại quyền và roles tương ứng với chi nhánh vừa chọn.

    - **Frontend Route Guard**:

      - Cập nhật [`router/index.js`](file:///d:/PMS/frontend/src/router/index.js): Bổ sung `meta.permission` cho từng trang (`/reservation`, `/frontdesk`, `/housekeeping`, `/reports`, `/fnb/*`, `/system`). Tự động chuyển hướng về `/forbidden` khi không đủ quyền.

      - Tạo mới trang 403 cao cấp [`ForbiddenPage.vue`](file:///d:/PMS/frontend/src/pages/ForbiddenPage.vue).

    - **Topbar & Fine-grained UI Permission Guards**:

      - Cập nhật [`MainLayout.vue`](file:///d:/PMS/frontend/src/layouts/MainLayout.vue): Dropdown chi nhánh trên Header chỉ hiển thị các chi nhánh mà tài khoản được phân quyền trong `user_branches`.

      - Cập nhật [`HomePage.vue`](file:///d:/PMS/frontend/src/pages/HomePage.vue): Các thẻ ứng dụng (PMS, F&B, SYSTEM) tự động ẩn/hiện theo quyền của nhân viên.

      - Gắn `v-if="can(...)"` vào các nút hành động cốt lõi: Nhận phòng (`fo.checkin`), Thanh toán & Xóa thanh toán (`fo.payment.create`), Trả phòng (`fo.checkout`), Thanh toán FnB (`fb.payment`).



---



## [2026-08-21] - Tự Động Tạo Tenant Database Khi Thêm Chi Nhánh Mới (Auto Multi-Tenant Provisioning)

### Module: System / Multi-Database & Quản Lý Chi Nhánh



- **Đã hoàn thành**:

  - **Auto Tenant Database Provisioning Engine**:

    - Tạo mới [`TenantDatabaseService.php`](file:///d:/PMS/backend/app/Services/TenantDatabaseService.php):

      - Tự động thực thi SQL tạo Database MySQL `CREATE DATABASE IF NOT EXISTS pms_{code}`.

      - Tự động đăng ký Dynamic Connection vào Runtime Configuration của Laravel (`mysql_{code}`).

      - Tự động chạy toàn bộ migrations khởi tạo schema bảng cho chi nhánh mới.

      - Tự động seed dữ liệu mẫu vận hành chuẩn ban đầu (`DatabaseSeeder`).

      - Tối ưu tải dữ liệu Cơ Cấu Tổ Chức ([`OrgStructureTab.vue`](file:///d:/PMS/frontend/src/pages/system/components/OrgStructureTab.vue)):

        - Chuyển `Promise.all` sang `Promise.allSettled` giúp giao diện không bị treo/trắng khi có request chậm hoặc timeout.

      - Nâng cấp tính năng Xóa Chi Nhánh ([`BranchManageTab.vue`](file:///d:/PMS/frontend/src/pages/system/components/BranchManageTab.vue) & [`SystemBranchController.php`](file:///d:/PMS/backend/app/Http/Controllers/Api/SystemBranchController.php)):

        - Bổ sung Popup modal xác nhận phương thức xóa:

          1. **Chỉ xóa thông tin chi nhánh**: Xóa khỏi bảng quản trị, giữ lại MySQL Database để lưu trữ dữ liệu cũ.

          2. **Xóa chi nhánh & Xóa toàn bộ Database**: Tự động thực thi `DROP DATABASE` xóa sạch cơ sở dữ liệu chi nhánh trên MySQL server.

      - Nâng cấp lệnh `php artisan db:reset-all`:

        - Tự động quét toàn bộ cơ sở dữ liệu `pms_*` có trên máy chủ MySQL (`SHOW DATABASES LIKE 'pms_%'`) và bảng `system_branches` thay vì chỉ reset cứng 5 DB cũ.

        - Đăng ký kết nối động (`Dynamic Connection`) cho mọi database chi nhánh phát hiện được (ví dụ `pms_dai_luc`, `pms_hkt5`, `pms_gkt6`...) để thực hiện `migrate:fresh` và `db:seed`.

        - Bổ sung tùy chọn `--drop-extra` để dọn dẹp các database thử nghiệm rác không có trong danh sách chi nhánh quản lý.

  - **Đồng bộ Ngày Hệ Thống PMS (System Date)**:

    - [`BreakfastPage.vue`](file:///d:/PMS/frontend/src/pages/frontdesk/BreakfastPage.vue): Sửa logic lấy ngày từ `res.data.data.system_date`, chuẩn hóa lấy đúng ngày nghiệp vụ khách sạn (09/08/2026).

    - [`ActivityLogTab.vue`](file:///d:/PMS/frontend/src/pages/system/components/ActivityLogTab.vue): Đồng bộ ngày nghiệp vụ từ `/system-date` và mặc định xem "Tất cả".

  - **Dynamic Connection Switching**:

    - Cập nhật [`SwitchBranchDatabase.php`](file:///d:/PMS/backend/app/Http/Middleware/SwitchBranchDatabase.php): Hỗ trợ phân giải và thiết lập kết nối động theo mã chi nhánh bất kỳ mà **không cần dev phải khai báo tĩnh trong `config/database.php` hay `.env`**.

  - **System Branch Management Controller**:

    - Cập nhật [`SystemBranchController.php`](file:///d:/PMS/backend/app/Http/Controllers/Api/SystemBranchController.php): Tự động gọi `TenantDatabaseService::provisionBranch` khi tạo chi nhánh mới qua `store()`, thêm endpoint `POST /api/system-branches/{id}/provision` để chủ động tái khởi tạo/migrate lại database chi nhánh khi cần.

    - Cập nhật [`SystemBranch.php`](file:///d:/PMS/backend/app/Models/SystemBranch.php) và [`SystemBranchResource.php`](file:///d:/PMS/backend/app/Http/Resources/SystemBranchResource.php) trả về `db_connection`, `db_name`, `organization_type`.

  - **Tài liệu hướng dẫn**:

    - Cập nhật [`DATABASE_GUIDE.md`](file:///d:/PMS/DATABASE_GUIDE.md) bổ sung mục 5 hướng dẫn cơ chế Multi-Tenant Auto Provisioning.



- **🟡 Kế hoạch các giai đoạn tiếp theo (Next Phases)**:

  - **Phase 3: Route Guard Frontend (Bảo vệ đường dẫn)**: Kiểm tra quyền trong `router/index.js`.

  - **Phase 4: Fine-grained Permission UI (Ẩn/Hiện nút bấm theo quyền)**: Gắn `v-if="can('...')"` vào các nút nghiệp vụ.

  - **Phase 5: Lọc danh sách Chi nhánh Topbar theo Nhân viên**: Dropdown chọn chi nhánh trên Topbar chỉ hiển thị các chi nhánh user được cấp phép.



---



## [2026-08-20] - Multi-Database, Dynamic Org Structure & Hệ Thống Phân Quyền (RBAC)

### Module: System / Cơ Cấu Tổ Chức, Ứng Dụng & Phân Quyền



- **Đã hoàn thành**:

  - **Multi-Database Setup & Dynamic Tenant Switching**:

    - Thiết lập hệ thống 5 databases: `pms_system` (quản trị tập trung, auth, users, roles, permissions), `pms_hkt1` (Nha Trang), `pms_hkt2` (TP.HCM), `pms_hkt3` (Đà Nẵng), `pms_hkt4` (Hà Nội).

    - Cấu hình kết nối trong [`config/database.php`](file:///d:/PMS/backend/config/database.php) và [`.env`](file:///d:/PMS/backend/.env).

    - Thêm Middleware [`SwitchBranchDatabase.php`](file:///d:/PMS/backend/app/Http/Middleware/SwitchBranchDatabase.php) tự động chuyển connection DB theo `X-Branch-Code` / `X-Branch-Id` trên từng request nghiệp vụ.

    - Cố định [`PersonalAccessToken.php`](file:///d:/PMS/backend/app/Models/PersonalAccessToken.php), [`User.php`](file:///d:/PMS/backend/app/Models/User.php), [`Role.php`](file:///d:/PMS/backend/app/Models/Role.php), [`Permission.php`](file:///d:/PMS/backend/app/Models/Permission.php), [`UserBranch.php`](file:///d:/PMS/backend/app/Models/UserBranch.php), [`UserRole.php`](file:///d:/PMS/backend/app/Models/UserRole.php), [`SystemBranch.php`](file:///d:/PMS/backend/app/Models/SystemBranch.php), [`UserSetting.php`](file:///d:/PMS/backend/app/Models/UserSetting.php) trên kết nối `mysql_system` để token hợp lệ xuyên suốt mọi chi nhánh khi chuyển đổi.

    - Chuyển `pms_token` và trạng thái xác thực từ `sessionStorage` sang `localStorage` để duy trì phiên đăng nhập khi mở tab mới trong cùng trình duyệt.

    - Cập nhật [`http.js`](file:///d:/PMS/frontend/src/services/http.js) và [`MainLayout.vue`](file:///d:/PMS/frontend/src/layouts/MainLayout.vue) tự động truyền mã chi nhánh đã chọn lên Backend.

    - Thêm Artisan Command [`ResetMultiDbCommand.php`](file:///d:/PMS/backend/app/Console/Commands/ResetMultiDbCommand.php) (`php artisan db:reset-all`) hỗ trợ reset nhanh toàn bộ hoặc từng DB riêng lẻ (`--branch=system`, `--branch=hkt1`, `--seed-all`).

    - Tạo tài liệu hướng dẫn quản trị database: [`DATABASE_GUIDE.md`](file:///d:/PMS/DATABASE_GUIDE.md).

  - **Database Migration, Seeder & Models**:

    - Migration [`2026_08_19_210000_create_roles_and_permissions_tables.php`](file:///d:/PMS/backend/database/migrations/2026_08_19_210000_create_roles_and_permissions_tables.php): `roles`, `permissions`, `role_permissions`, `user_branches`, `user_roles`, `primary_branch_id` trên `users`.

    - Migration [`2026_08_20_150000_update_department_code_length.php`](file:///d:/PMS/backend/database/migrations/2026_08_20_150000_update_department_code_length.php): Tăng độ dài `departments.code` lên 10 ký tự.

    - Models: [`Role.php`](file:///d:/PMS/backend/app/Models/Role.php), [`Permission.php`](file:///d:/PMS/backend/app/Models/Permission.php), [`UserBranch.php`](file:///d:/PMS/backend/app/Models/UserBranch.php), [`UserRole.php`](file:///d:/PMS/backend/app/Models/UserRole.php), [`Module.php`](file:///d:/PMS/backend/app/Models/Module.php), [`Department.php`](file:///d:/PMS/backend/app/Models/Department.php).

    - Helper methods trên [`User.php`](file:///d:/PMS/backend/app/Models/User.php): `allPermissions()`, `hasPermission()`, `hasBranchAccess()`, `isSuperAdmin()`.

    - Seeder [`RolePermissionSeeder.php`](file:///d:/PMS/backend/database/seeders/RolePermissionSeeder.php): 9 vai trò và 39 permissions.

    - Seeder [`ModuleSeeder.php`](file:///d:/PMS/backend/database/seeders/ModuleSeeder.php): Chuẩn hóa 3 ứng dụng cốt lõi `PROVISTA PMS`, `PROVISTA F&B`, `PROVISTA SYSTEM`.

    - Seeder [`DepartmentSeeder.php`](file:///d:/PMS/backend/database/seeders/DepartmentSeeder.php): Chuẩn hóa 4 bộ phận thực tế `BỘ PHẬN LỄ TÂN (FO)`, `BỘ PHẬN BUỒNG PHÒNG (HK)`, `QUẢN TRỊ HỆ THỐNG (SYS)`, `BỘ PHẬN F&B (FB)`.

  - **Backend Controllers & API Routes**:

    - [`RoleController.php`](file:///d:/PMS/backend/app/Http/Controllers/Api/RoleController.php): CRUD vai trò, lấy permissions, sync permissions.

    - [`UserPermissionController.php`](file:///d:/PMS/backend/app/Http/Controllers/Api/UserPermissionController.php): Lấy permissions user, sync chi nhánh được gán và sync vai trò theo chi nhánh.

    - [`DepartmentController.php`](file:///d:/PMS/backend/app/Http/Controllers/Api/DepartmentController.php): Lấy danh sách phòng ban, tạo phòng ban mới.

    - Route `GET /api/modules`: Trả về 3 ứng dụng chính đang hoạt động từ DB.

  - **Frontend UI & State**:

    - [`auth-store.js`](file:///d:/PMS/frontend/src/stores/auth-store.js): Quản trị permissions, branches, activeBranch, roles + getters `hasPermission`, `canAny`, `isSuperAdmin`, `isAdmin`, action `switchBranch`.

    - Composable [`usePermission.js`](file:///d:/PMS/frontend/src/composables/usePermission.js): Cung cấp helper `can(code)`, `canAny(codes)` cho Vue components.

    - [`EmployeeTab.vue`](file:///d:/PMS/frontend/src/pages/system/components/EmployeeTab.vue): Hoàn thiện tab "Phân Quyền Đặc Thù" — checkbox gán chi nhánh được phép truy cập, chọn primary branch, dropdown gán vai trò tương ứng cho từng chi nhánh, hiển thị tổng hợp danh sách quyền thực tế.

    - [`OrgStructureTab.vue`](file:///d:/PMS/frontend/src/pages/system/components/OrgStructureTab.vue): Tải 100% dữ liệu động từ Database (bảng `departments`, `modules`, `roles`, `users`), hiển thị cây thư mục Cơ cấu tổ chức, danh sách 3 Ứng dụng Provista và danh sách Nhân sự theo bộ phận.

    - [`RoleManageTab.vue`](file:///d:/PMS/frontend/src/pages/system/components/RoleManageTab.vue): Giao diện Quản lý vai trò & Ma trận checkbox phân quyền chi tiết theo từng module.

    - [`SystemPage.vue`](file:///d:/PMS/frontend/src/pages/system/SystemPage.vue): Tích hợp 2 tab "Cơ cấu tổ chức" và "Vai trò & Phân quyền".



- **🟡 Kế hoạch các giai đoạn tiếp theo (Next Phases)**:

  - **Phase 3: Route Guard Frontend (Bảo vệ đường dẫn)**:

    - Bổ sung logic kiểm tra quyền trong [`frontend/src/router/index.js`](file:///d:/PMS/frontend/src/router/index.js) (ví dụ: Nhân viên Lễ tân không có quyền vào `/system` hoặc `/housekeeping`, tự động redirect về trang được phép hoặc thông báo 403).

  - **Phase 4: Fine-grained Permission UI (Ẩn/Hiện nút bấm theo quyền)**:

    - Gắn `v-if="can('...')"` vào các nút hành động nghiệp vụ quan trọng ở Frontdesk (Tạo đặt phòng, Check-in, Check-out, Thu tiền, Chuyển phòng, Hủy phòng, In phiếu ăn sáng...), Housekeeping và F&B.

  - **Phase 5: Lọc danh sách Chi nhánh Topbar theo Nhân viên**:

    - Dropdown chọn chi nhánh trên Topbar chỉ hiển thị các chi nhánh mà user đang đăng nhập được gán trong `user_branches` (tài khoản Super Admin được thấy và chuyển sang tất cả các chi nhánh).









### Module: Housekeeping / Quản lý tồn kho & Kiểm kê định kỳ

- **Đã hoàn thành**:

  - Khởi tạo file nhật ký tiến độ [.agents/DAILY_LOG.md](file:///d:/PMS/.agents/DAILY_LOG.md).

  - Cập nhật quy tắc tự động ghi chép và đọc lại tiến độ vào [.agents/AGENTS.md](file:///d:/PMS/.agents/AGENTS.md).

  - Sửa API [OutletController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/OutletController.php) hàm `listHK()` truy vấn chuẩn từ bảng `housekeeping_outlets`.

  - Sửa frontend [InventoryTab.vue](file:///d:/PMS/frontend/src/pages/housekeeping/components/InventoryTab.vue) modal Thêm/Sửa kho bind đúng `ol.code` và `ol.name`.

  - Nâng cấp API `getBill()` trong [InventoryLogController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/InventoryLogController.php) để map linh hoạt mã Outlet, lọc bỏ các hóa đơn buồng phòng đã hủy (`BillEdit = 1`, `Status = 3, 4`), chỉ tính các món hợp lệ (`Deleted = 0`), và tự động bổ sung sản phẩm bán vào phiếu kiểm kê nếu chưa có.

  - Hỗ trợ chọn **nhiều Outlet cho 1 kho** (Multi-select checkbox) ở frontend [InventoryTab.vue](file:///d:/PMS/frontend/src/pages/housekeeping/components/InventoryTab.vue) và backend [WarehouseController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/WarehouseController.php) + [Warehouse.php](file:///d:/PMS/backend/app/Models/Warehouse.php), cho phép Get Bill gom hóa đơn từ tất cả các outlet đã gán.

  - Thêm nút **📋 Bill trực tiếp trên từng cột ngày** và nút **`📋 Lấy Bill Tháng` trên thanh công cụ** trong [InventoryTab.vue](file:///d:/PMS/frontend/src/pages/housekeeping/components/InventoryTab.vue) cùng API [InventoryLogController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/InventoryLogController.php), cho phép 1 cú click tự động quét và đồng bộ hóa đơn xuất kho cho toàn bộ tất cả các ngày trong tháng.

  - Bổ sung tính năng **kết chuyển Tồn cuối tháng trước sang tháng mới** khi tạo phiếu kiểm kê định kỳ:

    - Trong modal Kiểm kê định kỳ, khi chọn tháng mới (ví dụ tháng 8) và bấm nút **`📊 Thống kê`**, hệ thống gọi API `POST /api/inventory/checks/sync-previous-month` trong [InventoryCheckController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/InventoryCheckController.php) để tự động tính Tồn cuối của từng sản phẩm ở tháng trước (Tồn ĐK + Nhập - Xuất - Chuyển) và điền vào 2 cột **Tồn đầu kỳ** và **Số lượng thực tế** của tháng mới.

    - Khi nhân viên sửa lại "Số lượng thực tế", hệ thống tự động tính **Số chênh lệch** = Thực tế - Tồn đầu kỳ, đồng thời ngoài bảng chính ưu tiên lấy **Số lượng thực tế sau kiểm kê** làm mốc Tồn đầu kỳ để tính toán Tồn cuối và phát sinh trong tháng.

    - Đồng bộ thứ tự sắp xếp sản phẩm trong modal Kiểm kê định kỳ luôn theo **Tên A-Z** tương đồng 1:1 với bảng chính bên ngoài, giúp đối chiếu dễ dàng.

  - Thêm dropdown menu khi hover vào mục **GIAO PHÒNG** trên thanh điều hướng chính trong [MainLayout.vue](file:///d:/PMS/frontend/src/layouts/MainLayout.vue), bao gồm 6 mục:

    1. `SƠ ĐỒ PHÒNG`

    2. `NHẬN PHÒNG NHANH`

    3. `TẠO ĐĂNG KÝ`

    4. `ĐẶT CỌC`

    5. `TẠO THẺ KHÓA PHÒNG`

    6. `IN PHIẾU ĂN SÁNG`

- **Trạng thái hiện tại**: Hoàn thiện toàn bộ luồng Kiểm kê tồn kho, Get Bill, kết chuyển tồn cuối và menu dropdown Giao phòng ở phân hệ Lễ tân.



### Module: Frontdesk / In Phiếu Ăn Sáng (Breakfast Coupon - sp_035)

- **Đã hoàn thành**:

  - Xây dựng API `GET /api/breakfast/list` trong [BreakfastController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/BreakfastController.php) và [api.php](file:///d:/PMS/backend/routes/api.php) mô phỏng logic truy vấn MySQL tương đương Stored Procedure `sp_035`:

    - Hỗ trợ lọc theo **Ngày ăn sáng** (`breakfast` - từ sáng ngày hôm sau ngày đến đến sáng ngày đi) hoặc **Ngày đến** (`arrival`).

    - Lọc chỉ lấy các phòng có ăn sáng (`is_breakfast = 1` hoặc có trẻ em ăn sáng).

    - Tính toán số lượng người lớn, trẻ em ăn sáng và danh sách toàn bộ các ngày ăn sáng hợp lệ trong chu kỳ lưu trú.

  - Tạo service [breakfast-service.js](file:///d:/PMS/frontend/src/services/breakfast-service.js) kết nối backend.

  - Xây dựng màn hình [BreakfastPage.vue](file:///d:/PMS/frontend/src/pages/frontdesk/BreakfastPage.vue):

    - Thanh bộ lọc khoảng ngày (Date Range Picker), dropdown chuyển đổi Ngày đến / Ngày ăn sáng, nút `Xem`, nút `In phiếu ăn sáng`.

    - Bảng dữ liệu thiết kế chuẩn cây phân cấp 3 tầng (**Tầng 1: Ngày** -> **Tầng 2: Booking** -> **Tầng 3: Các phòng/phiếu ăn sáng**) với nút thu gọn/mở rộng `+`/`-` màu xanh cyan đúng theo ảnh mẫu.

    - Chuyển đổi dropdown chọn ngày thành **Segmented Toggle Buttons** (`Ngày ăn sáng` / `Ngày đến`) to rõ, trực quan.

    - Cố định thanh **Tổng kết** (Tổng phòng, Tổng người lớn, Tổng trẻ em) luôn nằm ở đáy màn hình (Fixed bottom bar), không bị trôi nổi ở giữa bảng.

    - Nâng cấp nút **In phiếu ăn sáng** thành Modal tùy chọn 2 trong 2 (`1. In tất cả (In All)` hoặc `2. In theo giai đoạn ngày`), loại bỏ dropdown cũ.

    - Tích hợp bộ chọn ngày **Clickable Calendar Picker** (hiển thị `DD/MM/YYYY` kèm icon lịch 📅 $\rightarrow$ click vào là mở bảng chọn ngày (popup calendar) của trình duyệt ngay lập tức, không cần gõ phím).

    - Bộ lọc tìm kiếm nhanh trực tiếp trên từng cột (Mã đăng ký, Tên đăng ký, Phòng, Ngày đến, Ngày đi, Tên khách).

    - Chuẩn hóa định dạng chuỗi ngày `YYYY-MM-DD` tại [BreakfastController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/BreakfastController.php) để loại bỏ hiện tượng lệch múi giờ (UTC ISO-8601 offset làm lùi 1 ngày) giữa bảng danh sách và phiếu in.

    - Sửa logic so khớp ngày ăn sáng của trẻ em (`booking_child_breakfast_details` / `booking_children`), nhận diện chính xác 100% số lượng suất ăn sáng của trẻ em (cả phụ thu và miễn phí) cho từng phòng và sinh đúng số lượng phiếu in tương ứng.

    - Chuẩn hóa phân tách trạng thái phòng theo đúng logic store [sp_035.sql](file:///d:/PMS/sp_035.sql):

      + Khi lọc **Ngày đến (`arrival`)**: Quét theo ngày đến `pt.ArrivalDate` trên bảng kế hoạch phòng, lấy mọi trạng thái (Đăng ký `0`, Đang ở `1`, Chuyển phòng `100`) để in phiếu trước đón khách.

    - Sửa lỗi upload logo công ty tại [SystemPage.vue](file:///d:/PMS/frontend/src/pages/system/SystemPage.vue):

      + Tự động bỏ header `Content-Type: application/json` khi gửi `FormData` trong [http.js](file:///d:/PMS/frontend/src/services/http.js) để trình duyệt tạo `boundary` multipart chính xác.

      + Trả về đường dẫn ảnh tương đối `/uploads/business/...` trong [InfoBusinessResource.php](file:///d:/PMS/backend/app/Http/Resources/InfoBusinessResource.php) giúp tránh lỗi `https://localhost` và tải ảnh mượt mà qua proxy Vite.

    - Cập nhật phiếu in ăn sáng [BreakfastCouponPreview.vue](file:///d:/PMS/frontend/src/pages/frontdesk/components/BreakfastCouponPreview.vue) và [BreakfastPage.vue](file:///d:/PMS/frontend/src/pages/frontdesk/BreakfastPage.vue) ưu tiên lấy Logo và Tên công ty từ cấu hình **Thông tin công ty (`/info-business`)** trong hệ thống (sửa lỗi thứ tự spread object bị ghi đè dữ liệu rỗng), kèm cơ chế fallback tự động hiển thị tên nếu chưa có ảnh.

  - Xây dựng modal [BreakfastPrintModal.vue](file:///d:/PMS/frontend/src/pages/frontdesk/components/BreakfastPrintModal.vue) với giao diện thẻ chọn hình thức in và picker chọn ngày trực quan.

    - Nâng cấp [BreakfastCouponPreview.vue](file:///d:/PMS/frontend/src/pages/frontdesk/components/BreakfastCouponPreview.vue) thành công cụ **Tùy biến Mẫu in Báo cáo / Phiếu Ăn Sáng linh hoạt**:

      + **Bố cục & Kích thước**: Tự do tùy chọn 1 Cột / 2 Cột / 3 Cột ngang, thanh trượt chỉnh chiều cao phiếu (180px - 320px), 4 cỡ chữ (Nhỏ, Chuẩn, Lớn, Rất lớn), 3 cỡ số phòng (Vừa, To nổi bật, Siêu to), kiểu viền (Nét liền, nét đứt, nét đôi) và độ dày viền.

      + **Tiêu đề & Nội dung trường**: Cho phép ẩn/hiện Logo, sửa Tên hiển thị đơn vị, sửa tiêu đề chính/phụ (VD: `PHIẾU ĂN SÁNG / BREAKFAST COUPON`), bật/tắt các trường Mã Booking, Tên khách, Số phòng, Ngày ăn sáng.

      + **Ghi chú chân trang**: Cho phép sửa nội dung dặn dò/điều khoản nhiều dòng hoặc tắt ghi chú.

      + **Lưu cấu hình tự động**: Tự động lưu cấu hình tùy chỉnh vào `localStorage` cho từng máy/khách sạn và hỗ trợ nút "Khôi phục gốc" khi cần.

    - Đảm bảo 100% dữ liệu (Tên khách sạn/công ty, Logo, Mã Booking, Số phòng, Tên khách, Ngày tháng, Số lượng khách) đều đọc động từ Database (`info_businesses`, `hotel_settings`, `bookings`, `booking_rooms`, `guests`), loại bỏ hoàn toàn các chuỗi text hardcode.

  - Liên kết điều hướng từ menu **GIAO PHÒNG -> IN PHIẾU ĂN SÁNG** trong [MainLayout.vue](file:///d:/PMS/frontend/src/layouts/MainLayout.vue) và nhúng vào [RoomMapPage.vue](file:///d:/PMS/frontend/src/pages/reservation/RoomMapPage.vue).

- **Trạng thái hiện tại**: Hoàn thành toàn bộ chức năng Quản lý & In Phiếu Ăn Sáng, dữ liệu động 100% từ Database.



---



## [2026-08-18] - Hoàn thiện Module Danh Sách Công Việc (/frontdesk?tab=shift-work) kết nối Database thực tế

### Module: Frontdesk / Danh Sách Công Việc (Shift Work)

- **Đã hoàn thành**:

  - Xây dựng Backend Controller [ShiftWorkController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/ShiftWorkController.php) và khai báo các API endpoint tại [api.php](file:///d:/PMS/backend/routes/api.php):

    1. `GET /api/shift-work/arrivals`: Truy vấn danh sách phòng đến chuẩn theo Stored Procedure `sp_143` & `sp_147`, lọc theo ngày đến và trạng thái (Chưa nhận phòng / Đã nhận phòng / Tất cả). Nhóm theo Booking sắp xếp tăng dần, tính tiền cọc (`payments` where `pack2 = 'DPR'`), tổng tiền booking, tổng tiền phòng, và danh sách yêu cầu đặc biệt.

    2. `GET /api/shift-work/departures`: Truy vấn danh sách phòng đi chuẩn theo `sp_143`, lọc theo ngày đi và trạng thái (Chưa trả / Đã trả / Tất cả). Tính toán chính xác `Tổng dịch vụ` (`booking_room_services`) và `Tổng thanh toán` (`payments`) ở cả cấp độ từng phòng và dòng tiêu đề Master Booking.

    3. `GET /api/shift-work/pending`: Truy vấn đăng ký chờ xác nhận chuẩn theo `sp_141`, lọc trạng thái Non-guaranteed (`20` hoặc `bk_definite != 1`) trong khoảng ngày (mặc định: Ngày hệ thống $\rightarrow$ +3 ngày). Thống kê chi tiết số lượng từng loại phòng (VD: `SUPD (5), SUPT (1)`), tiền cọc và thông tin liên hệ.

    4. `PUT /api/shift-work/pending/{bookingId}/note`: Cập nhật ghi chú xác nhận của Sale trực tiếp vào trường `note` của Booking trong cơ sở dữ liệu.

    5. `GET /api/shift-work/shuttle`: Truy vấn thông tin đón tiễn sân bay (loại đón/tiễn, chuyến bay, giờ bay/giờ hẹn, số lượng khách, xe/tài xế, ghi chú) từ thông tin booking thực tế.

    6. `GET /api/shift-work/noshow`: Truy vấn danh sách phòng không đến chuẩn theo `sp_054`, bỏ cột Ngày xác nhận, hiển thị tiền phạt/tổng tiền, lý do, người tạo và ca.

    7. `GET /api/shift-work/birthdays`: Truy vấn danh sách khách lưu trú có ngày sinh nhật trong khoảng ngày xem (mặc định: Ngày hệ thống $\rightarrow$ +3 ngày) chuẩn theo `sp_111`.

  - Tạo service [shift-work-service.js](file:///d:/PMS/frontend/src/services/shift-work-service.js) kết nối toàn bộ 7 API endpoint của module.

  - Tái cấu trúc và hoàn thiện giao diện [ShiftWorkPage.vue](file:///d:/PMS/frontend/src/pages/reservation/ShiftWorkPage.vue):

    - Giữ nguyên thiết kế UI và hệ thống màu sắc/bố cục chuẩn theo yêu cầu.

    - Thay thế 100% dữ liệu mock/tĩnh bằng dữ liệu thật đọc từ Database qua API.

    - Tích hợp thanh toolbar: Bộ chọn ngày Calendar Popup (hiển thị `DD/MM/YYYY`), nút chọn nhanh `Hôm nay` & `Ngày mai`, dropdown lọc trạng thái cho từng tab, và ô tìm kiếm nhanh đa trường (mã booking, tên khách, số phòng, tên công ty).

    - Hỗ trợ lưu ghi chú Sale trực tiếp trên Tab Chờ xác nhận với nút Chỉnh sửa / Lưu.

    - Thanh tổng kết (Sticky footer stats) ở đáy màn hình tự động tính toán tổng số đăng ký, tổng số phòng, tổng đêm vắng, tổng lượt đưa đón,... theo dữ liệu thực tế.

    - Tích hợp loading spinner overlay và empty states khi không có bản ghi.

  - Build Vite kiểm thử thành công 100% (`npm run build` không phát sinh lỗi).

- **Trạng thái hiện tại**: Toàn bộ 6 tab thuộc module Danh Sách Công Việc đã hoạt động hoàn toàn với dữ liệu thực tế từ Database.

  - Sửa lỗi truy vấn quan hệ `roomRateCode` trên model `BookingRoom` chuyển sang cột trực tiếp `rate_code`.

  - Đồng bộ chuẩn ngày nghiệp vụ PMS (`SystemDateRoll`) cho cả Backend Controller và Frontend `ShiftWorkPage.vue`, đảm bảo hiển thị đúng booking ngày hệ thống hiện tại (`09/08/2026`).

  - Nâng cấp giao diện bảng dữ liệu (Table Layout) theo đúng 100% thiết kế từ ảnh mẫu của khách hàng:

    + Tiêu đề bảng nền xám nhẹ `#f1f5f9`, font chữ đậm rõ ràng kèm icon sắp xếp `⇅`.

    + Dòng phân nhóm Booking dạng banner dải liền (`colspan`) nền xanh nhạt `#edf5fc`, hiển thị nút thu gọn/mở rộng `+`/`-`, chuỗi thông tin Booking đầy đủ (mã, tên, ngày đến~ngày đi, số đêm, số phòng, ghi chú) ở bên trái và số liệu tài chính (`Đặt cọc : ...`, `Tổng tiền : ...` / `Tiền dịch vụ : ...`, `Tiền đã thanh toán : ...`) căn gọn gàng về bên phải.

    + Các dòng phòng con hiển thị chi tiết, sạch sẽ với đường viền mỏng và hiệu ứng hover nhẹ nhàng.

  - Sửa mapping trường Loại phòng (`roomType`) từ `room_classes.name` (thay vì `room_class_name`), giúp hiển thị chính xác tên loại phòng (`Superior Double`, `Superior Twin`,...) trên tất cả các tab.

  - Tối ưu hóa ô tìm kiếm nhanh: Tự động tìm kiếm tức thì khi gõ phím (Debounce 250ms), loại bỏ nút bấm rườm rà và tích hợp nút icon `x` bên trong ô nhập liệu để reset từ khóa nhanh chóng.

  - Gỡ bỏ badge số `2` màu đỏ trên nút menu `D.S Công Việc` trong [MainLayout.vue](file:///d:/PMS/frontend/src/layouts/MainLayout.vue).

- **Tính năng Giao phòng nhanh / Nhận phòng nhanh trực tiếp từ Sơ đồ phòng (`RoomMapPage.vue`)**:

  - Tạo mới component [QuickAssignModal.vue](file:///d:/PMS/frontend/src/pages/reservation/components/QuickAssignModal.vue) theo đúng 100% bố cục và màu sắc thiết kế mẫu:

    + Cột Thông tin: Ngày đến, Ngày đi, Loại phòng, Dạng phòng, Số phòng, Số đêm.

    + Thẻ Khách hàng: Người lớn, Trẻ em, công tắc **Ở theo giờ** (tự động chuyển `Ngày đi = Ngày đến`, `Số đêm = 0` khi bật và hoàn lại ngày tiếp theo khi tắt).

    + Cột Giá: Giá phòng, Mã giá phòng, Tăng/Giảm giá (% / VNĐ), Thêm giường, Giá thêm giường, nút mở modal Yêu cầu đặc biệt.

  - Kết nối sự kiện click chuột trái vào bất kỳ phòng trống nào trên sơ đồ hoặc chọn **Giao phòng nhanh** từ menu ngữ cảnh để mở modal điền sẵn thông tin phòng.

  - Tích hợp gọi API tạo Booking (`POST /api/bookings`) và tự động Check-in phòng ngay lập tức khi lưu, làm mới dữ liệu sơ đồ phòng realtime.

  - Sửa lỗi đóng/thoát modal **Yêu cầu đặc biệt**: Bổ sung emit `close` và hỗ trợ lưu/trả về danh sách yêu cầu đã chọn khi tạo mới phòng chưa có ID đặt phòng.

  - **Đồng bộ chuẩn hóa Loại phòng & Dạng phòng theo Bảng Giá phòng chuẩn**:

    + Tự động tải dữ liệu bảng Giá phòng chuẩn (`/standard-rates`).

    + Khi click phòng hoặc thay đổi Loại phòng: Tự động điền đúng Dạng phòng tương ứng (`Double`, `Twin`, `Family`, `King`,...), Giá phòng chuẩn (ví dụ `650.000 đ`, `540.000 đ`,...) và Giá thêm giường chuẩn (`300.000 đ`).

  - Sửa lỗi hiển thị danh mục Yêu cầu đặc biệt: Khắc phục sự cố không tải danh mục khi mở modal do thiếu `immediate: true` & `onMounted`, đồng thời tự động tick chọn yêu cầu đặc biệt vừa tạo mới và lưu đồng bộ vào phòng được nhận nhanh.

  - **Tối ưu trải nghiệm Modal Nhận phòng nhanh (`QuickAssignModal.vue`)**:

    + Bỏ lớp phủ làm mờ nền phía sau (`bg-transparent pointer-events-none`), cho phép quan sát trực tiếp sơ đồ phòng.

    + Cho phép nắm giữ thanh tiêu đề (Header) để kéo thả di chuyển modal linh hoạt.

    + Đồng bộ màu sắc Header và các nút hành động (Yêu cầu đặc biệt, Đóng, Lưu) theo đúng **Tùy chỉnh màu nền Topbar** của hệ thống (`themeBg`).

- **Kế hoạch tiếp theo**: Tiếp tục hỗ trợ người dùng kiểm tra các trường hợp nghiệp vụ tiếp theo.



---



## [2026-08-19] - Hoàn thiện toàn diện Hệ thống Lịch Sử Thao Tác (Activity Logs) cho toàn bộ phân hệ PMS

### Module: Frontdesk / Housekeeping / System - Lịch Sử Thao Tác (`/frontdesk?tab=history`, `/housekeeping?tab=history`, `/system?tab=activity-log`)

- **Đã hoàn thành**:

  - **Khắc phục sự cố MariaDB / MySQL**:

    - Sửa lỗi Aria checksum và cấp quyền máy chủ `1130` (`Host 'localhost' is not allowed to connect`), đảm bảo database hoạt động ổn định trên port 3306.

  - **Nâng cấp Backend Logging Engine ([ActivityLogService.php](file:///d:/PMS/backend/app/Services/ActivityLogService.php))**:

    - Bổ sung hàm `logBusiness()` tự động thu thập IP, thiết bị (User-Agent), User đăng nhập (kèm mã NV) và lưu log chi tiết.

    - Chuẩn hóa các helper format mô tả đầy đủ theo đúng văn phong nghiệp vụ khách sạn thực tế:

      - `logBookingCreated()`: Format `* Tạo Mới Đăng Ký {Mã_ĐK} : -Tên: {Tên_BK}, -Ngày đến: {Đến}, -Ngày đi: {Đi} ({Số_Đêm} đêm), -Phòng: {Phòng}, -Loại phòng: {Loại}, -Tổng tiền: {Tổng} đ, -Đặt cọc: {Cọc} đ, -Nguồn: {Nguồn}`

      - `logBookingUpdated()`: Format `* Cập Nhật Thông Tin Đăng Ký {Mã_ĐK} : {Chi_Tiết_Thay_Đổi}`

      - `logCheckIn()`: Format `Check in cho đăng ký {Mã_ĐK} - các phòng: {Phòng}`

      - `logCheckOut()`: Format `Check out cho đăng ký {Mã_ĐK} - các phòng: {Phòng}`

      - `logRoomMove()`: Format `Chuyển phòng: {Phòng_Cũ}({Khách}) -> {Phòng_Mới}({Khách}) Lý do: {Lý_Do}`

      - `logRoomUpgrade()`: Format `Nâng hạng phòng: {Phòng} ({Loại_Cũ} -> {Loại_Mới}) Lý do: {Lý_Do}`

      - `logRoomStatusChanged()`: Format `Phòng {Phòng} Đổi trạng thái: {Trạng_Thái_Cũ} -> {Trạng_Thái_Mới}`

      - `logRoomLock()`: Format `Khóa/Mở khóa phòng {Phòng}: {Lý_Do}`

      - `logServiceAction()`: Format `* Thêm dịch vụ phòng {Phòng} (ĐK {Mã_ĐK}): {Tên_Dịch_Vụ} (SL: {SL}, Đơn giá: {Đơn_Giá} đ, Thành tiền: {Thành_Tiền} đ)`

      - `logPaymentAction()`: Format `* Đặt cọc / Thanh toán đăng ký {Mã_ĐK} (Phòng {Phòng}): {Số_Tiền} đ, Phương thức: {PTTT}`

      - `logDayClose()`: Format `* Chạy sang ngày nghiệp vụ: {Ngày_Cũ} -> {Ngày_Mới}`

      - `logInventoryAction()`: Format `* Nhập/Xuất/Kiểm kê kho {Kho}: {Chi_Tiết}`

  - **Tích hợp kích hoạt Log tự động trên toàn bộ Controllers**:

    - [BookingController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/BookingController.php): Tích hợp trong `store`, `update`, `destroy`, `copy`.

    - [BookingRoomController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/BookingRoomController.php): Tích hợp trong `checkIn`, `moveRoom`, `upgrade`.

    - [GuestController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/GuestController.php): Tích hợp trong `checkoutBooking`, `addGuest`.

    - [PaymentController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/PaymentController.php): Tích hợp trong `store` (Đặt cọc & Thanh toán trước).

    - [RoomController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/RoomController.php): Tích hợp trong `updateStatus` (Đổi trạng thái buồng phòng).

    - [BookingRoomServiceController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/BookingRoomServiceController.php): Tích hợp trong `store` và `postHousekeepingBill`.

    - [api.php](file:///d:/PMS/backend/routes/api.php): Tích hợp trong `/system-date/roll` (Sang ngày / Night audit).

    - [ActivityLogController.php](file:///d:/PMS/backend/app/Http/Controllers/Api/ActivityLogController.php): Tối ưu hóa truy vấn lọc đa trường (`registration_code`, `room_code`, `action`, `user_id`, `date_from`, `date_to`, `search`).

  - **Nâng cấp và Hoàn thiện Giao diện Frontend ([ActivityLogTab.vue](file:///d:/PMS/frontend/src/pages/system/components/ActivityLogTab.vue))**:

    - Thiết kế bảng hiển thị đầy đủ 11 cột chuẩn: **ID**, **Thời gian**, **Người dùng**, **Địa chỉ IP**, **Thiết bị**, **Phân hệ / Màn hình**, **Hành động**, **Mã đăng ký**, **Mã phòng**, **Mô tả chi tiết**, **Chi tiết**.

    - Định dạng cột **Mô tả chi tiết**: Tự động nhận diện và làm nổi bật tiêu đề nghiệp vụ (`*`), nhãn trường (`-Tên:`, `-Phòng:`, `-Giá:`, `-Tổng tiền:`, `-Đặt cọc:`, `Lý do:`), mũi tên chuyển đổi `➜`, xuống dòng rõ ràng, dễ đọc.

    - Bộ lọc nhanh (Quick Filter Chips): `Hôm nay`, `Hôm qua`, `7 ngày qua`, `Tháng này`, `Tất cả`.

    - Bộ lọc nâng cao: Từ ngày - Đến ngày, Mã đăng ký, Mã phòng, Phân loại Hành động (Tạo mới, Cập nhật, Nhận phòng, Trả phòng, Hủy, Khóa phòng, Thanh toán, Thêm dịch vụ, Sang ngày,...), Người dùng, Phân hệ/Màn hình, Tìm kiếm chung (Debounce tức thì).

    - Xuất file Excel/CSV chuẩn UTF-8 BOM, không lỗi font tiếng Việt.

    - Đồng bộ màu sắc giao diện theo Tùy chỉnh màu nền Topbar (`themeBg`).

    - Modal so sánh JSON Diff (Dữ liệu cũ vs Dữ liệu mới) chi tiết.

  - **Tích hợp Routing & Điều hướng**:

    - Cập nhật [FrontDeskPage.vue](file:///d:/PMS/frontend/src/pages/frontdesk/FrontDeskPage.vue) hỗ trợ tab `history` (`/frontdesk?tab=history`).

    - Cập nhật [HousekeepingPage.vue](file:///d:/PMS/frontend/src/pages/housekeeping/HousekeepingPage.vue) liên kết tab `history` hiển thị dữ liệu log thời gian thực.

    - Bổ sung menu item **LỊCH SỬ THAO TÁC** vào menu Lễ tân trên [MainLayout.vue](file:///d:/PMS/frontend/src/layouts/MainLayout.vue).

- **Trạng thái hiện tại**: Hoàn thiện toàn bộ luồng Kiểm kê tồn kho, Get Bill, kết chuyển tồn cuối và menu dropdown Giao phòng ở phân hệ Lễ tân.



---



## [2026-08-26] - Khắc phục lỗi giao diện & tích hợp hệ thống Báo cáo Đa Tab (Multi-Tab Report Viewer)

### Module: Navigation / Reports Page

- **Đã hoàn thành**:

  - **Tích hợp Báo cáo trực tiếp vào Module (Frontdesk / Reservation)**:

    - Cập nhật [`FrontDeskPage.vue`](file:///c:/xampp/htdocs/PMS/frontend/src/pages/frontdesk/FrontDeskPage.vue) và [`RoomMapPage.vue`](file:///c:/xampp/htdocs/PMS/frontend/src/pages/reservation/RoomMapPage.vue) để import và render [`ReportsPage.vue`](file:///c:/xampp/htdocs/PMS/frontend/src/pages/reports/ReportsPage.vue) khi `currentTab === 'reports'`.

    - Việc này giữ nguyên 100% thanh menu chính phía trên và thanh menu sub-navigation bên dưới của phân hệ Lễ tân/Đặt phòng khi người dùng xem báo cáo.

  - **Đồng bộ hóa Route Điều hướng**:

    - Thay đổi logic link trong dropdown báo cáo của [`MainLayout.vue`](file:///c:/xampp/htdocs/PMS/frontend/src/layouts/MainLayout.vue) để trỏ đến `/${context}?tab=reports&report=${code}` thay vì redirect hẳn sang `/reports`.

  - **Xây dựng hệ thống Báo cáo Đa Tab (Multi-Tab System) & Cải tiến Template Báo cáo phòng đến**:

    - Nâng cấp [`ReportsPage.vue`](file:///c:/xampp/htdocs/PMS/frontend/src/pages/reports/ReportsPage.vue) hỗ trợ mở nhiều tab báo cáo đồng thời, cho phép chuyển đổi qua lại linh hoạt hoặc đóng tab.

    - Đồng bộ hóa tab đang mở với query param `report` trên URL.

    - Thiết kế giao diện checkbox dạng toggle-switch màu xanh cyan chuẩn thiết kế mẫu.

    - **Nâng cấp công cụ gom nhóm dữ liệu (Grouping Engine)**: Cập nhật [`TemplateRendererService.php`](file:///c:/xampp/htdocs/PMS/backend/app/Services/TemplateRendererService.php) hỗ trợ gom nhóm 3 cấp (Date -> Company -> Booking) qua các thuộc tính `data-group-by`, `data-subgroup-by` và `data-subsubgroup-by`, đồng thời bổ sung các row template `pms-subsubgroup-header` và `pms-subsubgroup-note`.

    - **Cập nhật Stored Procedure & Template in**: Nâng cấp SP `rpt_arriving_rooms` để trả ra thêm cột `ArrivalDateGroup` phục vụ gom nhóm theo ngày. Đồng bộ và thiết kế lại template HTML/CSS của Báo cáo phòng đến chuẩn chỉnh theo đúng giao diện tham chiếu của khách hàng (hiển thị dòng Ngày màu đỏ đậm, bảng chia cột sắc nét, thông tin Ghi chú & Đăng ký hiển thị rõ ràng, mã Booking in màu xanh lá nổi bật, các dòng tổng cộng theo công ty căn lề chuẩn xác).

- **Trạng thái hiện tại**: Hệ thống báo cáo đa tab và template Báo cáo phòng đến mới đã hoàn thành, tích hợp mượt mà vào luồng Lễ tân/Đặt phòng.

- **Kế hoạch tiếp theo**: Hỗ trợ người dùng kiểm tra các lỗi hoặc tính năng tiếp theo.



---



## [2026-08-27] - Khắc phục lỗi lệch ngày bộ chọn thời gian & định dạng mẫu Báo cáo phòng đến

### Module: Reports / Bộ chọn thời gian & Báo cáo phòng đến



- **Đã hoàn thành**:

  - **Sửa lỗi lệch ngày bộ chọn thời gian**:

    - Nâng cấp [`ReportDateRangePicker.vue`](file:///c:/xampp/htdocs/PMS/frontend/src/components/ReportDateRangePicker.vue) sử dụng hàm format local `YYYY-MM-DD` tự định nghĩa tránh bị lệch múi giờ so với ngày hệ thống do chuyển đổi qua `toISOString()`.

    - Bổ sung đầy đủ 15 mốc thời gian và sắp xếp theo đúng thứ tự trong ảnh yêu cầu (Hôm nay, Tuần này, Tháng này, Quý này, Năm này, Ngày mai, Tuần tiếp theo, Tháng tiếp theo, Quý tiếp theo, Năm tiếp theo, Hôm qua, Tuần trước, Tháng trước, Quý trước, Năm trước, Tùy chỉnh).

  - **Tối ưu xem trước mẫu in A4**:

    - Nâng cấp [`ReportsPage.vue`](file:///c:/xampp/htdocs/PMS/frontend/src/pages/reports/ReportsPage.vue) tự động thu hẹp chiều rộng iframe preview về `max-w-[800px]` (tỷ lệ A4 dọc chuẩn) khi sử dụng mẫu in dọc (`portrait`), giúp hiển thị trực quan và tránh bị kéo giãn dẹt ngang.

  - **Định dạng bảng dữ liệu & Bổ sung bảng kê Loại phòng**:

    - Nâng cấp [`ReportDefinitionController.php`](file:///c:/xampp/htdocs/PMS/backend/app/Http/Controllers/Api/ReportDefinitionController.php): Bổ sung hàm tính toán tự động thống kê Loại phòng (`room_type_summary` và `room_type_summary_total`) lấy từ danh sách khách chính.

    - Cập nhật [`arriving_rooms_reference.php`](file:///c:/xampp/htdocs/PMS/backend/database/report_templates/arriving_rooms_reference.php): Định dạng lại bảng chính còn 10 cột, căn chỉnh lại độ rộng cột, đồng thời nhúng bảng **BẢNG KÊ CHI TIẾT THEO LOẠI PHÒNG** tổng hợp số lượng, đêm, người lớn/trẻ em và tỷ lệ phần trăm xuống cuối trang.

- **Trạng thái hiện tại**: Hoàn thành toàn bộ nghiệp vụ báo cáo phòng đi, phòng đến và sửa lỗi định dạng ngày.



---



## [2026-08-27] - Triển khai Báo cáo phòng đi (Departing Rooms Report) & Sửa định dạng ngày

### Module: Reports / Báo cáo phòng đi & phòng đến



- **Đã hoàn thành**:

  - **MySQL Stored Procedure**:

    - Tạo stored procedure `rpt_departing_rooms` (chuyển đổi từ `sp_008`) để truy vấn dữ liệu phòng đi từ các bảng `booking_rooms`, `bookings`, `booking_room_guests`, `guests`, `companies`, `registration_statuses`, và `booking_room_services`.

  - **Backend & Services**:

    - Tạo service [`DepartingRoomsSummaryService.php`](file:///c:/xampp/htdocs/PMS/backend/app/Services/Reports/DepartingRoomsSummaryService.php) tính toán tổng hợp "BẢNG KÊ CHI TIẾT THEO LOẠI PHÒNG" ở đáy trang.

    - Đăng ký service và cập nhật các luồng tính toán trong [`ReportDefinitionController.php`](file:///c:/xampp/htdocs/PMS/backend/app/Http/Controllers/Api/ReportDefinitionController.php).

    - **Sửa lỗi định dạng ngày**: Nâng cấp [`ReportDefinitionController.php`](file:///c:/xampp/htdocs/PMS/backend/app/Http/Controllers/Api/ReportDefinitionController.php) tự động phát hiện và format các tham số ngày dạng `YYYY-MM-DD` sang `DD/MM/YYYY` trước khi chuyển sang template render, giúp hiển thị định dạng ngày tháng tiếng Việt chuẩn trên cả hai báo cáo phòng đến và phòng đi.

  - **Thiết kế mẫu in (Reference Template)**:

    - Tạo cấu hình mẫu in tham chiếu [`departing_rooms_reference.php`](file:///c:/xampp/htdocs/PMS/backend/database/report_templates/departing_rooms_reference.php) với thiết kế A4 Portrait, hiển thị đầy đủ 10 cột dữ liệu, các hàng tổng cộng theo Công ty/Ngày/Giai đoạn, thông tin Notice và bảng thống kê loại phòng.

  - **Database Migration**:

    - Viết và chạy thành công migration [`2026_08_27_120000_create_departing_rooms_report.php`](file:///c:/xampp/htdocs/PMS/backend/database/migrations/2026_08_27_120000_create_departing_rooms_report.php) để tạo store và seed dữ liệu nguồn, template, và định nghĩa báo cáo động.

- **Trạng thái hiện tại**: Hoàn thành toàn bộ nghiệp vụ, định dạng ngày hiển thị chuẩn `dd/mm/YYYY`.



## [2026-09-04] - Hoàn thiện công suất Room Map và lưu lịch sử checkout sớm

### Module: Kế hoạch phòng / Room Map / Thống kê



- **Công suất phòng**:

  - Tính theo công thức: phòng ở dự kiến cuối ngày / (tổng phòng khách sạn - phòng OOO) * 100%.

  - Chỉ tính phòng vật lý: loại phòng nội bộ (rooms.is_internal = 1) và phòng ảo có số phòng bắt đầu bằng 0.

  - Phòng thật ở tầng 0 vẫn được tính nếu không thuộc hai điều kiện loại trừ trên.

  - Chỉ lấy booking có tình trạng đăng ký registration_statuses.is_availability = 1.

  - Đếm theo số phòng vật lý duy nhất, loại phòng OOO khỏi cả khả năng bán và dự báo phòng ở.

  - Room Map và popup Thống kê cùng sử dụng chỉ số từ API /rooms/stats, tránh lệch công thức giữa hai màn hình.

  - Khi xem ngày lịch sử/tương lai, OOO/OOS lấy theo thời gian hiệu lực của room_locks; room_status_code chỉ dùng cho ngày hệ thống.

- **Checkout sớm**:

  - Bổ sung booking_rooms.planned_departure_date và planned_num_of_days để giữ ngày đi/số đêm dự kiến ban đầu.

  - Khi checkout sớm, departure_date, CheckoutDate và ActutalNumOfDays phản ánh dữ liệu thực tế; dữ liệu kế hoạch không bị ghi đè.

  - Chỉ số early_departures xác định theo CheckoutDate < planned_departure_date.

  - Khi hoàn tác checkout, khôi phục ngày đi và số đêm từ dữ liệu kế hoạch.

  - Migration chỉ có thể khởi tạo dữ liệu kế hoạch cũ từ giá trị hiện còn lưu; các lần checkout sớm đã mất dữ liệu trước bản sửa không thể suy ngược chính xác.

- **Kiểm thử**:

  - Test công suất bao phủ phòng nội bộ, phòng 0xx, phòng thật tầng trệt, OOO và booking không tính availability.

  - Test checkout sớm xác nhận giữ nguyên ngày đi/số đêm dự kiến và cập nhật đúng số đêm thực tế.

  - Test hoàn tác checkout và build frontend production đều thành công.

- **Ghi chú kỹ thuật**:

  - Không tìm thấy mã nguồn sp_195 trong repository; công thức tương đương được triển khai tại service/API hiện hành.

### Bổ sung kiểm tra realtime và toàn bộ chỉ tiêu popup

- Đã chạy migration 2026_09_04_100000 trên database dự án hiện tại.

- Sau mỗi thao tác khóa/mở khóa, sự kiện Echo, khi mở popup, khi quay lại tab và polling dự phòng 15 giây đều đồng bộ lại Rooms + Stats.

- Chống response API cũ ghi đè response mới khi nhiều yêu cầu realtime chạy gần nhau.

- Bỏ cơ chế âm thầm trả mock khi API thống kê lỗi; thêm tham số chống cache cho mỗi lần tải.

- Tách đúng hai công thức:

  - Tổng phòng có thể bán = Tổng phòng - OOO - OOS.

  - Mẫu số công suất = Tổng phòng - OOO.

- Hoàn thiện Room/Pax cho phòng đến, đã đến, đang ở, phòng đi; bổ sung gia hạn, day-use, đặt trong ngày và Walk-in theo đúng source code WALKIN.

- Dữ liệu thực tế kiểm tra trên DB: Tổng 180, OOO 1, OOS 1, có thể bán 178, mẫu số công suất 179, phòng ở 6, phòng trống 172, công suất 3%.

- Test thống kê đạt 38 assertions, bao gồm cả ca toàn bộ phòng OOO để bảo đảm công suất về 0 và không chia cho 0.



### Rà soát lần cuối theo nghiệp vụ sp_195 / Link Hotel

- Database dự án hiện tại không có stored procedure `sp_195`; đã đối chiếu theo mô tả nghiệp vụ và dữ liệu màn hình khách cung cấp.

- Sửa dự báo cuối ngày để tính cả reservation hợp lệ chưa gán số phòng; mỗi dòng `booking_rooms` chưa gán tương ứng một phòng dự kiến.

- Booking đã gán chỉ được tính khi là phòng vật lý, không phải phòng nội bộ/phòng 0xx và không nằm trong OOO/OOS.

- Tách đúng hai chỉ tiêu:

  - **Phòng đến**: gồm phòng đã đến và reservation đến trong ngày, kể cả chưa gán phòng.

  - **Phòng đến đã gán phòng**: chỉ gồm phòng đã đến và reservation đã có số phòng vật lý.

- Chuẩn hóa dữ liệu số đêm ban đầu theo Link Hotel:

  - `booking_rooms.NumOfDays` giữ nguyên số đêm đặt ban đầu.

  - `booking_rooms.ActutalNumOfDays`, `departure_date`, `CheckoutDate` cập nhật theo ngày checkout thực tế.

  - Trả phòng sớm được nhận diện bằng `CheckoutDate = ngày xem` và `ActutalNumOfDays < NumOfDays`.

  - `planned_departure_date` giữ ngày đi dự kiến ban đầu để phục vụ gia hạn và hoàn tác checkout.

- Đã chạy migration đổi tên trường kế hoạch thành `NumOfDays` trên database local.

- Dữ liệu local sau rà soát: tổng phòng vật lý 180, OOO 1, OOS 1, phòng có thể bán 178, phòng dự kiến cuối ngày 9, mẫu số công suất 179, công suất 5%.

- Kiểm thử:

  - RoomOccupancyStatisticsTest đạt 40 assertions, gồm reservation chưa gán phòng, phân biệt phòng đến/đã gán, OOO/OOS, phòng nội bộ, phòng 0xx, is_availability, checkout sớm, gia hạn, day-use, đặt trong ngày và walk-in.

  - Test checkout sớm và 4 test hoàn tác checkout đều đạt.

  - Frontend production build thành công.

  - Bộ CheckoutBusinessRulesTest còn 2 lỗi cũ về room charge chuyển master trả 422; không thuộc thay đổi thống kê/checkout sớm.





## [2026-09-05] - Cơ cấu tổ chức, phân quyền theo chi nhánh và quản lý nhân viên

### Module: System / Cơ cấu tổ chức / Vai trò / Nhân viên



- Đã đối chiếu nghiệp vụ với SP1304, SP8032, SP1604; bảng cũ chỉ dùng tham chiếu, không phụ thuộc runtime.

- Tạo thiết kế System DB cho bộ phận, vị trí, Role theo chi nhánh/ứng dụng, vị trí nhân viên theo chi nhánh/ứng dụng, quyền Role theo chi nhánh và quyền kho.

- Bổ sung metadata màn hình, cờ thao tác ngày cũ và bắt buộc đổi mật khẩu.

- Migration khởi tạo 14 bộ phận, vị trí mặc định, chuẩn hóa và backfill quyền cũ.

- API hỗ trợ CRUD cơ cấu, ma trận View/Add/Edit/Delete, thêm màn hình, copy Role, gán vị trí nhân viên và quyền kho.

- Add/Edit/Delete tự kéo theo View ở frontend và backend.

- Đồng bộ từng ứng dụng, không xóa nhầm POS/SYSTEM khi sửa PMS; dựng lại user_roles để tương thích màn cũ.

- UI Cơ cấu tổ chức, Vai trò & Phân quyền, Nhân viên đã chuyển sang dữ liệu động; một user có thể có vị trí khác nhau theo chi nhánh.

- Nhân viên tự sinh mã NBxxxx; username/mật khẩu mặc định theo email; reset mật khẩu yêu cầu đổi lại; khóa tài khoản thu hồi token.

- Test OrganizationRbacTest đạt 1 test/3 assertions; frontend production build thành công.

- MultiDatabaseArchitectureTest còn một lỗi cũ về branch code/id (mong đợi 422, nhận 200), không thuộc RBAC.

- Chưa chạy migration pms_system và chưa chuyển middleware runtime sang ma trận mới vì cần xác nhận riêng trước khi tác động quyền đăng nhập hiện hành.

- Đã tạo tài liệu bàn giao `RBAC_ORGANIZATION_IMPLEMENTATION_REVIEW.md`, liệt kê schema, API, UI, business rule, kết quả test, phần chưa áp dụng và checklist để Antigravity rà soát.



### [2026-09-07] Rà soát độc lập và hoàn thiện RBAC sau bản sửa Gemini



- Kiểm tra lại yêu cầu khách: Bộ phận → Position, Position × Chi nhánh × Ứng dụng → Role, View/Add/Edit/Delete, user đa chi nhánh, chi nhánh chính, kho, mật khẩu và chữ ký.

- Sửa fallback quyền rỗng, phân giải quyền đa ứng dụng, nhận diện Super Admin đúng assignment và trả đủ quyền ứng dụng trong login/me.

- Nối `allow_historical_date_actions` vào controller bill/payment; bỏ setting cũ mặc định cho phép ngày cũ.

- Đồng bộ bộ phận động từ `departments`/SP1304; tab user của Position đọc từ `user_branch_positions`.

- Sửa Employee UI: không fallback Position toàn hệ thống, kho theo từng chi nhánh, lưu assignment mọi ứng dụng, validate trước khi tạo user, reset mật khẩu qua API về email.

- Backend quyền kho kiểm tra chi nhánh được phép, chống trùng và xác minh kho đúng database chi nhánh.

- Bỏ hardcode HKT1/id 1 trong HTTP client; mã nhân viên dùng `EMPLOYEE_CODE_PREFIX`; ứng dụng mặc định dùng `DEFAULT_APPLICATION_CODE`.

- Siết middleware cho route RBAC tương thích cũ và route chữ ký khai báo trùng.

- Đã chạy migration System DB: `2026_09_05_110000` batch 2 thành công.

- Test: RBAC 15/15, 45 assertions; frontend build thành công; runtime local đúng 8 chi nhánh, 15 bộ phận, 18 Position, 3 ứng dụng và kho HKT1/HKT2.

- Full backend suite: 108/171 passed; 61 lỗi 403 do test cũ thiếu fixture quyền, 2 lỗi môi trường thiếu Dompdf/GD. Không thêm bypass test vào production.

- Chi tiết bằng chứng tại `RBAC_ORGANIZATION_IMPLEMENTATION_REVIEW.md`.



## [2026-09-10] - Hoàn thiện các fix Đặt cọc và CRUD tài khoản ngân hàng



- Triển khai ngữ cảnh cọc MR/FO và outlet RC cho cọc PMS; giữ nguyên outlet của Advance Payment hiện hành.

- Chuẩn hóa URL/preview ảnh chứng từ, khóa Tách/Chuyển/Xóa/Sửa khi đang sửa cọc, và giới hạn payload sửa vào phương thức/mô tả.

- Thêm form lý do xóa gửi đúng body DELETE, bảo toàn bộ phận/outlet/tài khoản/tiền tệ trên dòng đảo, tách và chuyển.

- Đồng bộ tổng active DPR khi mở booking; card hiển thị tiêu đề `ĐẶT CỌC`, từng khoản và vùng cuộn; dropdown chuyển cọc được cập nhật theo booking nhận.

- Thêm migration, API, phân quyền và UI CRUD tài khoản ngân hàng: hai nhóm, 10 cột, lookup kế toán/tiền tệ và nối dropdown cọc; tab thuế/phí cà thẻ giữ placeholder vì chưa có đặc tả.

- Kiểm tra tĩnh, migration pretend, route list và frontend build đạt. Bộ `BookingTest|DebtSettlementTest` chưa chạy qua nghiệp vụ vì fixture quyền trả 403; chưa UAT hoặc backfill dữ liệu lịch sử.



## [2026-09-10] - Chốt lại Section 7 theo booking nhận



- Cập nhật `DepositModal` để dropdown chuyển cọc chỉ hiển thị một option cho mỗi booking Đăng ký/Inhouse hợp lệ, gồm mã booking và tên booking/khách; loại bỏ builder phòng, “Toàn bộ phòng” và Guest 1/Guest 2.

- Khi chọn booking, state và payload chỉ giữ/gửi `target_booking_id`; không gửi `target_room_id` hoặc `target_guest_id`, không mở rộng sang chuyển phòng vật lý.

- Cập nhật yêu cầu, nghiệm thu và kịch bản INT-05 trong `PLAN_FIX_LOI_DAT_COC.md`; compile/build frontend cần chạy lại sau thay đổi.



## [2026-09-10] - Hoàn tác booking-only ở Section 7



- Theo yêu cầu mới nhất, khôi phục dropdown chuyển cọc hiển thị booking và các phòng/khách tương ứng.

- Khi chọn booking gửi `target_booking_id`; khi chọn phòng/khách gửi thêm đúng `target_room_id`/`target_guest_id`.

- Cập nhật kế hoạch và INT-05 để đối chiếu danh sách đích với Hóa đơn → Chuyển phòng; chưa UAT danh sách thực tế.



## [2026-09-10] - Ghi chú commit các Section Đặt cọc



- Ghi lại phạm vi đã làm của Section 1–8 và phần chưa gồm UAT/backfill/tab thuế-phí trong `PLAN_FIX_LOI_DAT_COC.md`.

- Commit đề xuất: `fix(deposit): complete deposit workflow and bank account management`.

- Chưa tạo commit hoặc push GitHub; chờ người dùng commit.


## [2026-09-23] - Triển khai Dòng 169/170/171

- Thêm procedure, metadata, Designer reference và test cho `ROOM_FORECAST`, `WEEKLY_ROOM_REPORT`, `TOTAL_REVENUE`.
- Dòng 170 dùng `p_division=__current__/__all__`, Dòng 169 dùng `p_branch=__current__` ẩn; Dòng 171 phân nhóm thanh toán theo `payment_group` và giữ invariant kế toán.
- PHPUnit 11/11 (115 assertions), PHP lint, route list, `git diff --check` và frontend build đạt.
- Chưa chạy migration/database thật hoặc nghiệm thu browser/PDF với dữ liệu thật.


