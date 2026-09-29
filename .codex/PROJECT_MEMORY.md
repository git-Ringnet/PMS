# Project Memory — PMS

## Current Status

- 2026-09-24: Đã triển khai các vấn đề Checkout dòng 226/227/229/230/231/232: giữ trạng thái booking/phòng sau thanh toán; chuẩn hóa marker `pack2/pack4`; lấy tài khoản ngân hàng từ cấu hình; chỉnh sửa mô tả bill/payment; tinh gọn Payment Modal; kéo thả bill/cọc/thanh toán trước sang phòng. Không migration, không backfill dữ liệu lịch sử. PHP lint, route list và frontend build đạt; backend feature test liên quan bị treo không xuất output trong môi trường test hiện tại.
- 2026-09-24: Đã triển khai Checkout dòng 234–237 và 239–240: xác thực City Ledger theo `companies.sync_acc`; settlement giới hạn ngày lưu trú/ngày hệ thống/quyền ngày cũ; ghép ngày giờ với ca cấu hình cho Payment/Prepayment; giới hạn ngày FO và đêm tiền phòng theo thời gian lưu trú; chặn FO/HK/room-charge khi No Post, không đổi Night Audit. Dòng 238 chưa đổi owner `service_bills` do consumer ngoài Checkout có thể bị ảnh hưởng. Frontend build và PHP lint đạt; backend tests không chạy được vì test connection `mysql_data` chưa cấu hình.

- 2026-09-23: Đã triển khai mã nguồn Dòng 169/170/171 theo đặc tả đã chốt. Dòng 169 dùng `p_branch=__current__` ẩn theo connection hiện tại; Dòng 170 dùng `p_division=__current__/__all__`, chuẩn hóa tuần Thứ Hai–Chủ Nhật và chỉ trừ OOO; Dòng 171 dùng 22 cột Sheet 72/sp_292, phân nhóm thanh toán theo `payment_group` và bảo toàn đẳng thức kế toán. Chưa chạy migration thật hoặc nghiệm thu browser/PDF/dữ liệu thật.
- 2026-09-23: Hoàn thành nghiên cứu, phân tích sâu và lập bộ tài liệu đặc tả kỹ thuật chi tiết 100% cho 3 báo cáo: Dòng 169 (Báo cáo dự đoán bán phòng / sp_023), Dòng 170 (Báo cáo phòng hàng tuần / sp_023_Division), Dòng 171 (Báo cáo tổng doanh thu / sp_TotalRevenueFromReportSetup & Sheet 72). Bóc tách toàn bộ công thức toán học, ma trận cột (17 cột dòng 169, 2 tầng header 9 cột dòng 170, 22 cột dòng 171), Form Designer reference template PHP, Stored Procedure MySQL 8.0, đẳng thức cân bằng kế toán và quy tắc đối soát chéo bất biến với dòng 166, 167, 168. Lưu tại `.codex/docs/doc_baocao/dong_169_bao_cao_du_doan_ban_phong.md`, `dong_170_bao_cao_phong_hang_tuan.md`, `dong_171_bao_cao_tong_doanh_thu.md` và cẩm nang tổng hợp tại `.codex/docs/reports/ROW_169_170_171_COMPREHENSIVE_SPECIFICATION.md`.
- 2026-09-23: Chuẩn hóa lại luồng Designer cho 7 template Dòng 154/159/160/166/167/168: `content_json` là nguồn giao diện, `content_html` được biên dịch từ JSON, `css` chỉ giữ trình bày; sửa schema `groups`/custom bindings và bổ sung block header/footer còn thiếu. Migration `2026_09_23_170000_sync_revenue_report_json_html_from_templates.php` đồng bộ lại template trên các database branch. Chưa nghiệm thu browser preview/export với dữ liệu thật.
- 2026-09-23: Hoàn tất chuẩn hóa contract và Designer reference cho Dòng 154/159/160/166/167/168. Migration `2026_09_23_150000_standardize_revenue_reports_contracts.php` đã cập nhật 7 procedure, metadata và template trên 5 database; migration `2026_09_23_160000_align_revenue_group_contracts.php` đồng bộ lại nhóm doanh thu legacy Dòng 154 và Dòng 160. Smoke-test gọi đủ 7 report trên cả 5 connection đều thành công; Dòng 160 trả 21 dòng đúng contract. Chưa nghiệm thu browser preview/in/PDF/Excel với dữ liệu thật.
- 2026-09-23: Rà lại và chuẩn hóa lần hai Dòng 154, 159, 160, 166, 167, 168. Đồng bộ field schema Dòng 154 với 16 cột procedure và kiểu `TEXT` của `p_services`; bổ sung grouping runtime cho Dòng 154 và 159; sửa Dòng 167 để khi tách ăn sáng thì cộng đúng sang F&B; cập nhật migration `2026_09_23_130000_finalize_revenue_reports_contracts.php` cho metadata/template/procedure đã triển khai và cập nhật contract tài liệu. Migration đã chạy trên 5 branch; test contract/render đạt 9/9 (86 assertions). Chưa chạy browser preview/export với dữ liệu thật.
- 2026-09-23: Kiểm tra runtime Dòng 154, 159, 160, 166, 167, 168 phát hiện và sửa lỗi load dữ liệu ở Dòng 159 (ORDER BY Amount mơ hồ giữa payments/settlements) và Dòng 167 (ONLY_FULL_GROUP_BY). Migration patch 2026_09_23_100000_fix_revenue_reports_159_167_procedure_contract.php đã chạy trên mysql, HKT1, HKT2, HKT3, HKT4; smoke-test hai procedure trên cả 5 connection không còn lỗi. Không thay đổi bảng, model, executor, controller, frontend hoặc import mapping.
- 2026-09-22: Hoàn thành rà soát cặn kẽ 100% toàn bộ 6 báo cáo (Dòng 154, 159, 160, 166, 167, 168): bổ sung kiến trúc Adapter cho Dòng 154 (`SummaryServiceInvoicesDataAdapter.php`) do executor PMS chỉ nhận result set đầu tiên; bổ sung quy tắc đối soát chéo bất biến 100% giữa Dòng 166, 167, 168; chuẩn hóa tooltip icon `(i)` và cột "Tên đăng ký" Dòng 159; hoàn thiện Cẩm nang triển khai toàn diện [MASTER_IMPLEMENTATION_GUIDE_6_REPORTS.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/reports/MASTER_IMPLEMENTATION_GUIDE_6_REPORTS.md) đảm bảo Agent mới chưa từng tiếp xúc có thể triển khai độc lập, khép kín và chính xác tuyệt đối.
- 2026-09-22: Hoàn thành nghiên cứu, phân tích sâu và lập bộ tài liệu đặc tả kỹ thuật chi tiết 100% cho 3 báo cáo: Dòng 154 (Báo cáo hóa đơn dịch vụ tổng hợp / sp_025 Navy - BỎ QUA Galliot theo yêu cầu), Dòng 167 (Báo cáo công suất công ty / sp_055 & sp_055_Division Navy), Dòng 168 (Báo cáo doanh thu theo người bán / sp_155, sp_158 Army & sp_055 Navy). Đã bóc tách đúng store chỉ định trong Excel và SSMS: `ProVistaNavyHotel.dbo.sp_025` (phân nhóm doanh thu theo bảng `SP1610` NightAuditReport thay vì Outlet bảng `SP3000` của Galliot, hỗ trợ đa chọn dịch vụ `multi-select`, sửa lỗi typo legacy), `ProVistaNavyHotel.dbo.sp_055` (bóc tách 11 cột có đánh số thứ tự từ 1-11, công thức DevExpress Designer %OCC, ADR thực thu, ADR niêm yết không FOC/giảm giá, 5 tùy chọn động gồm ăn sáng, chi tiết, nhóm theo ngày/thị trường/nguồn), hợp nhất 2 kiểu chạy của Báo cáo doanh thu theo người bán (Mode 1: theo ngày đến của BK theo chuẩn Army; Mode 2: theo đêm phòng ở thực tế theo chuẩn Navy). Đã trích xuất ảnh UI mẫu thực tế vào `.codex/docs/doc_baocao/images/`, SQL gốc vào `.codex/docs/doc_baocao/sql/`, lập 3 tài liệu chi tiết tại `.codex/docs/doc_baocao/` và Master Specification tại `.codex/docs/reports/ROW_154_167_168_COMPREHENSIVE_SPECIFICATION.md`.
- 2026-09-22: Hoàn thành nghiên cứu, phân tích sâu và lập bộ tài liệu đặc tả kỹ thuật chi tiết 100% cho 3 báo cáo: Dòng 159 (Báo cáo tiền đặt cọc / sp_076 Navy), Dòng 160 (Báo cáo tổng hợp ngày / sp_279 Navy - BỎ QUA Galliot theo yêu cầu), Dòng 166 (Báo cáo chi tiết công suất công ty / sp_078 Navy). Đã bóc tách đúng store chỉ định trong Excel và SSMS: `ProVistaNavyHotel.dbo.sp_076` (sửa lỗi lọc logic cũ, 5 chế độ lọc có tooltip icon `(i)`, bổ sung cột "Tên đăng ký"), `ProVistaNavyHotel.dbo.sp_279` (7 nhóm chỉ tiêu doanh thu & hoạt động, cột Ngày và Lũy kế tháng, loại trừ Galliot), `ProVistaNavyHotel.dbo.sp_078` (báo cáo đối soát chéo công suất và doanh thu phòng với 21 cột chi tiết). Đã trích xuất ảnh UI mẫu thực tế vào `.codex/docs/doc_baocao/images/`, SQL gốc vào `.codex/docs/doc_baocao/sql/`, lập 3 tài liệu chi tiết tại `.codex/docs/doc_baocao/` và Master Specification tại `.codex/docs/reports/ROW_159_160_166_COMPREHENSIVE_SPECIFICATION.md`.
- 2026-09-22: Sửa UX chọn ngày đơn cho Dòng 150 và 164: `ReportsPage.vue` dùng component `SingleDatePicker` có lịch tiếng Việt thay cho input date native; giá trị gửi backend vẫn là `YYYY-MM-DD`. Frontend production build đạt.
- 2026-09-22: Đã triển khai Dòng 150, 151, 158 và bật hiển thị Dòng 164 trên cả HKT1–HKT4. Stored Procedure của 150/151/158 có fallback theo schema legacy khác nhau của HKT4; row 150 có alias tương thích binding template; row 164 tạm bỏ qua quyền chỉ tại route mở đúng mã báo cáo. Smoke-test trực tiếp cả 4 procedure trên 5 connection đạt.

- 2026-09-25: Hoàn thiện Section 12 thêm cột Tăng/giảm giá chi tiết từng đêm cho phòng (CreateRegistrationPage.vue):
  1. Thêm cột "Tăng/giảm giá" vào giữa cột "Dịch vụ" và "Số lượng" trong bảng mở rộng chi tiết dịch vụ phòng ở cả 2 chế độ hiển thị (Mode A và Mode B).
  2. Bổ sung Popover Tăng/Giảm giá độc lập cho từng đêm phòng (`RM`/`ROOM_CHARGE`), cho phép chọn Tăng/Giảm, nhập % hoặc tiền mặt VND, hiển thị giá gốc (`baseRate`) và giá mới.
  3. Khi điều chỉnh trong popover, hệ thống tính toán và cập nhật tức thì trên giao diện qua `handleServiceRateChange`, lưu vào `room.dailyRoomPrices` và tính lại tổng tiền phòng `room.total`. Khắc phục triệt để lỗi gọi API bất đồng bộ khi đang gõ phím (`@input`) gây đơ giao diện, spam toast và reset giá trị về 0; dữ liệu được đồng bộ xuống database khi nhấn nút "Lưu" của đăng ký.
- 2026-09-25: Hoàn thiện Section 11 đồng bộ base_price và chi tiết tiền phòng khi sửa giá/giảm giá (CreateRegistrationPage.vue & GuestController.php):
  1. Khi sửa giá trực tiếp tại ô `price` trên dòng phòng hoặc đổi giá qua popover Tăng/Giảm giá (`calculateRoomAdjustedPrice`), hệ thống tự động gán đồng thời `price` và `basePrice`, đồng thời gọi `syncRoomPriceToDailyCharges` để đồng bộ toàn bộ các đêm phòng nghỉ chưa post (`is_posted == 0` và có thể chỉnh sửa) trong `room.dailyRoomPrices` và `room.services`.
  2. Bảng chi tiết dịch vụ mở rộng (`getRoomDisplayServices`) và tổng tiền phòng (`calculateRoomTotal`) lập tức hiển thị đơn giá mới đồng bộ cho tất cả các đêm.
  3. Cập nhật `GuestController.php` để thao tác "Room map -> chuột phải -> Thông tin phòng -> sửa giá phòng" cập nhật cả 2 cột `rate` và `base_price` trên bảng `booking_rooms`.
- 2026-09-25: Hoàn thiện Section 9 cảnh báo chọn phòng khi cập nhật hàng loạt (CreateRegistrationPage.vue):
  Khi `actionName === 'Cập nhật'` và người dùng không tick chọn phòng nào (`selectedRows.value.length === 0`), thay thế `openEditModal()` bằng thông báo cảnh báo `uiStore.showToast('Vui lòng chọn phòng để cập nhật.', 'warning')`, đúng với yêu cầu Section 9.
- 2026-09-24: Hiển thị phụ thu ăn sáng trẻ em (Section 8) trong chi tiết dịch vụ phòng (CreateRegistrationPage.vue):
  1. Gắn `childRecords` từ `br.children` có sẵn `breakfast_details` vào đối tượng phòng.
  2. Bổ sung các dòng phụ thu ăn sáng trẻ em (`BD`) vào bảng chi tiết dịch vụ mở rộng (`getRoomDisplayServices`), phân bổ theo từng ngày kèm tên trẻ, số lượng, đơn giá và cờ FIT.
  3. Cộng tiền phụ thu ăn sáng trẻ em vào `getServicesTotal` và `calculateRoomTotal`, giúp tổng tiền phòng phản ánh chính xác phụ thu ăn sáng.
- 2026-09-24: Khắc phục lỗi tắt "Ở theo giờ" (Day use) nhưng bấm Lưu nút vẫn tự bật lại:
  1. Frontend (CreateRegistrationPage.vue): Đưa `is_day_use` vào payload cập nhật `updateBooking` và `syncRoomsToAllocations`. Ràng buộc `hourly = true` chỉ khi ngày đến bằng ngày đi (`r.checkIn === r.checkOut`).
  2. Backend (BookingController.php): Ràng buộc `$roomArrival !== $roomDeparture` ép `$isRoomDayUse = false` trong cả `store`, `update`, `room_allocations` và đồng bộ phòng con, không để dữ liệu day use cũ hoặc header ghi đè phòng lưu trú qua đêm.
- 2026-09-24: Khắc phục lỗi Section 6 (Khóa Day Use cho ngày quá khứ & Đồng bộ hiển thị 0 đêm):
  1. Khóa switch "Ở theo giờ" (CreateRegistrationPage.vue & BookingDetailModal.vue): Bổ sung `isHourlyDisabled(room)`, khóa switch (disabled + cursor-not-allowed) khi ngày đến của phòng hoặc booking nhỏ hơn ngày hệ thống (`checkIn < sysDate`). Chặn và cảnh báo trong `handleHourlyToggle(room)` và `handleRowNightsChangeInline(room)`. Tự động tắt `hourly = false` nếu sửa ngày đến về trước ngày hệ thống.
  2. Đồng bộ hiển thị và số đêm = 0 (Day Use): Sửa modal Thông tin đăng ký từ `{{ modalForm.nights || 1 }} đêm` thành `{{ Number(modalForm.nights) >= 0 ? modalForm.nights : 0 }} đêm`, chấm dứt việc JS ép `0 || 1` thành `1 đêm`. Cho phép `decrementNights()` giảm về 0 đêm. Sửa `handleNightsChange()`, `handleMainNightsChange()`, topbar input `min="0"`, `syncBookingDatesFromRooms()` gán `tab.nights = diff >= 0 ? diff : 0`, đảm bảo số đêm của booking tab và modal khớp chính xác với `0 đêm` của các dòng phòng Day Use.

- 2026-09-24: Khắc phục lỗi WebSocket Reverb và hoàn thiện Section 2 Ngày xác nhận:
  1. WebSocket/Reverb Resilience (Section 1): Tạo SafePusherBroadcaster.php kế thừa PusherBroadcaster, bắt ngoại lệ BroadcastException / Throwable khi tiến trình Reverb server (cổng 8090) offline, ghi Log::warning thay vì ném exception làm sập request với mã HTTP 500 (cURL error 7: Failed to connect to 127.0.0.1 port 8090). Đăng ký mở rộng driver 
everb và pusher với SafePusherBroadcaster trong AppServiceProvider.php. Cấu hình connect_timeout => 0.5s, 	imeout => 1.0s trong config/broadcasting.php.
  2. Section 2 Ngày xác nhận (CreateRegistrationPage.vue): Khắc phục lỗi hiển thị dd/mm/yyyy khi mở modal tạo mới (handleAddTabClick) bằng cách tính toán và gán ngay confirmDate. Tình trạng Guaranteed mặc định bằng ngày hệ thống (sysDate). Tình trạng có cut off (Non-guaranteed, Allotment, Waiting...) tính calcDate = checkIn - cutOff; nếu calcDate < sysDate thì confirmDate = sysDate theo đúng nghiệp vụ. Chặn không tự động tính lại ngày xác nhận khi sửa ngày đến của booking đã tạo (!tab.dbId hoặc isEditModal/dbId). PHP lint và frontend build đạt 100%.



- 2026-09-23: Khắc phục triệt để các tồn đọng & sai lệch logic trong 5 Section của file `Các nghiệp vụ liên quan tới booking 2.docx`:

  1. Section 4: Sửa lỗi truy vấn check trùng phòng Day Use tại `RoomAvailabilityService.php` (dùng `$queryEnd` cho overnight overlap), chặn chính xác việc gán trùng phòng Day Use vào phòng có khách qua đêm đến cùng ngày (và ngược lại).

  2. Section 8 & Section 3/12: Bảo toàn đơn giá Extra Bed từng đêm khi đồng bộ ngày phòng trong `BookingRoomLifecycleService.php` và `BookingRoomController.php`, ngăn chặn việc ghi đè phẳng bằng giá chung phòng `extra_bed_rate`.

  3. Section 17: Chuẩn hóa ngày đi `r.checkOut` từ `DD/MM/YYYY` sang `YYYY-MM-DD` khi bấm Sửa và bổ sung phân tích ngày trong `validateRoomDatesAgainstBooking` trên `CreateRegistrationPage.vue`, sửa lỗi báo sai giai đoạn đăng ký.

  4. Section 16: Trả về thông số `SyncRoomDateByBookingDate` qua `HotelSettingController.php`; trên `CreateRegistrationPage.vue`, chỉ cho phép sửa ngày đến/ngày đi/số đêm trên thanh tiêu đề khi `SyncRoomDateByBookingDate = 1`. Khóa không cho sửa tên booking trực tiếp tại đây.

  5. Section 6: Khóa checkbox "Phòng theo giờ" trên `BookingDetailModal.vue` khi phòng in-house đến trước ngày hệ thống; khi bật Day Use tự động đặt ngày đi = ngày đến, số đêm = 0 và khóa ô chọn ngày đi; `GuestController.php` tiếp nhận `is_day_use` và cập nhật `NumOfDays = 0`.

  - PHP lint đạt 100%, test logic truy vấn Day Use đạt.



- 2026-09-23: Booking Sections 3/5/8/13/14/15/16/18/19 đã audit theo `file word can len plan/Các nghiệp vụ liên quan tới booking 2.docx`: chuẩn hóa quyền service view/add/edit/delete; đổi ngày phòng đồng bộ `actual_arrival_date` và child breakfast details mà không tạo BD service mới; giữ realtime sau commit; tìm booking giữ đúng module; ẩn phòng hủy theo status header; áp dụng `SyncRoomDateByBookingDate`/AV-overroom; xử lý `RegistrationStatusId_BookingCancel`; General Search giữ booking active khi chỉ hủy phòng. Section 5 ghi rõ **Chưa có mô tả nguồn**, không implement. Chi tiết tại `.codex/docs/booking/sections_3_5_8_13_14_15_16_18_19.md`.



- 2026-09-23: Verification cho scope này đã đạt PHP lint và `git diff --check`; focused PHPUnit/browser UAT vẫn cần chạy khi có `mysql_data` và môi trường frontend không còn lỗi native Tailwind oxide/Windows `spawn EPERM`.



- 2026-09-23: Booking Sections 4/6/7/9/11/12/17/20 đã audit theo `Các nghiệp vụ liên quan tới booking 2.docx`: day-use same-day chống trùng phòng với stay qua đêm, lưu `is_day_use`/0 đêm và phân biệt late check-in, hiển thị `move_room`, bulk update rỗng và rate-only không ghi RM service, đồng bộ `rate/base_price`, chi tiết giá từng đêm, sửa ngày đi hợp lệ và chặn undo phòng đích đã move. Tài liệu tại `.codex/docs/booking/sections_4_6_7_9_11_12_17_20.md`; không đổi schema/migration. PHP lint và `git diff --check` đạt; test DB/browser chưa chạy do thiếu `mysql_data` và frontend build có thể vướng native Tailwind oxide/Windows spawn EPERM.



- 2026-09-23: Booking Sections 1/2/10 đã audit và hoàn thiện: edit cọc đổi HTTT luôn chuẩn hóa mô tả theo HTTT mới, ngày xác nhận chỉ tính khi tạo và clamp không trước ngày hệ thống, sửa booking không ghi đè ngày xác nhận; cảnh báo check-in cùng mẫu được gom theo phòng và bỏ tên `AllowCheckinVacantClean` khỏi thông điệp. Tài liệu tại `.codex/docs/booking/sections_1_2_10.md`; test mới bị chặn bởi connection `mysql_data`, frontend build bị chặn bởi native Tailwind oxide/Windows spawn EPERM.



- 2026-09-23: Booking payment quick update đã đồng bộ phạm vi đích chuyển cọc với Checkout (booking/phòng trạng thái 0/1, lọc ID nguồn và chống response tìm kiếm cũ), ánh xạ `booking_room_id`/số phòng cho card cọc và kiểm tra lại phương thức thanh toán ở API khi sửa nhanh. Chi tiết contract và rủi ro tại `.codex/docs/booking/payment_quick_update.md`; chưa UAT trên dữ liệu thật.

- 2026-09-23: Hoàn thiện phần lifecycle Booking room: day-use cùng ngày lưu `ActutalNumOfDays/NumOfDays = 0` và bulk update không còn chặn khoảng ngày bằng nhau nếu phòng đã bật `is_day_use`; các endpoint sửa phòng/header dùng `BookingRoomLifecycleService` để đồng bộ RM/EB và chi tiết ăn sáng trẻ theo giai đoạn mới, giữ nguyên dòng đã post; phòng chuyển không được hủy nhận phòng và phòng hủy chỉ hiện khi booking header đã hủy. Regression test bổ sung trong `BookingBusinessRulesTest`/`UndoCheckInValidationTest`; test feature vẫn bị chặn khi môi trường thiếu connection `mysql_data`, frontend build bị chặn bởi native Tailwind oxide/Windows spawn EPERM. Đặc tả tại `docs/booking-fix-plan/ROOM_LIFECYCLE.md`.

- 2026-09-23: Booking realtime/API hardening: `ReservationUpdated` and `RoomStatusUpdated` now dispatch after the enclosing transaction commits, preventing Room Plan/Room Map/General Search reloads from observing partial booking/room writes or rolled-back changes. `BookingController::restore` now receives `Request`, fixing the `force` overbooking-confirmation branch. No schema, payload, route, or channel changes. Focused BookingTest execution is currently blocked by the test environment's missing `mysql_data` connection.

- 2026-09-23: Xử lý và chuẩn hóa 3 phần nghiệp vụ Khóa phòng (theo ghi chú 22/09):

  1. Section 1: Giao diện Kế hoạch phòng bỏ 2 dòng Tên và Loại khóa phòng, bổ sung "Người khóa: [username/name]". Hover phòng khóa trên Sơ đồ phòng hiển thị tooltip ghi chú và người khóa tương tự Kế hoạch phòng. Service `RoomLockPermissionService` chuẩn hóa kiểm tra Role mở khóa từ thông số `RoleUserUnlockRoomOOO/OOS` trên cả màn hình Khóa phòng và khi đổi trạng thái phòng trên Sơ đồ phòng lưới/danh sách (`RoomController@updateStatus`).

  2. Section 2: Khi người dùng mở khóa phòng, cập nhật `end_date = [ngày hệ thống] [giờ thực hiện mở khóa]`, `status = 'Done'`, `is_active = 2`. Kế hoạch phòng và thống kê kiểm tra theo giờ mở khóa mặc định `FrmOOO_DefineLockByTime` (12:00); phòng khóa từ ngày 10 đến ngày 11 mở khóa lúc 10:41 (< 12:00) thì ngày 10 tính 1 phòng khóa OOO/OOS, ngày 11 không tính phòng khóa. Sửa triệt để lỗi phòng đã mở khóa vẫn còn hiển thị thanh OOO/OOS trên Kế hoạch phòng: quan hệ `allActiveLocks` trên Room.php giữ chuẩn `is_active = 1` và bộ lọc RoomPlanPage.vue bỏ qua khóa đã hoàn tất (`is_active = 2` hoặc `status = 'Done'`). Thống kê OOO lịch sử qua đêm vẫn được tính chuẩn qua RoomAvailabilityService.

  3. Section 3: Chuẩn hóa thứ tự kiểm tra: Overbooking công suất phòng trống AV (`AllowOverRoomTypeRoomKind`) được kiểm tra TRƯỚC; nếu bằng 0 chặn cứng không cho phép (không hiện confirm, không bypass bằng `force: true`). Sau đó kiểm tra unassignable booking (`AllowLockRoomCauseUnassignableRoomBK`) SAU; nếu bằng 0 chặn cứng. Backend test `RoomLockTest` đạt 15/15 tests (50 assertions) 100%, frontend build đạt 100%.


- 2026-09-21: Row 152 `RECEPTION_REVENUE_ARMY` có bảng summary Designer riêng 2 nhóm theo ảnh legacy; dữ liệu tổng hợp từ `Amount` trong nhánh riêng của `ReportDatasetEnricher`. Chưa chạy migration hoặc nghiệm thu trên browser.

- 2026-09-21: Nút Day Close mở report `EXPECTED_ROOM_REVENUE_NIGHT_AUDIT` theo ngày PMS mặc định; chưa kiểm tra trên browser hoặc tài khoản có quyền Night Audit thực tế.

- 2026-09-21: Sửa báo cáo `DEPOSITS_SALE`: tổng phụ `Tổng Theo C.ty` dùng `group.sum.Amount` theo nhóm thanh toán; procedure ưu tiên `payments.created_by`, dự phòng `payments.username`. Migration `2026_09_21_160000` đã cập nhật procedure cùng binding Design/HTML trên `mysql` và HKT1–HKT4. PHP lint và kiểm tra cấu hình DB đạt; chưa kiểm tra preview/in/export trên browser.

- 2026-09-21: Thêm `.codex/docs/reports/report_creation_rules.md` làm quy trình tạo báo cáo độc lập và yêu cầu bắt buộc đọc trong `AGENTS.md`; chỉ dẫn thứ tự đọc, kiến trúc runtime, contract procedure/dataset, Designer, migration, ảnh hưởng file dùng chung và checklist nghiệm thu.

- 2026-09-21: Hoàn thành nghiên cứu, phân tích sâu và lập bộ tài liệu đặc tả kỹ thuật chi tiết 100% cho 3 báo cáo: Dòng 158 (Báo cáo thu ngân lễ tân / sp_039 Navy), Dòng 161 (Báo cáo doanh thu hai giai đoạn / sp_217 Army), Dòng 164 (Báo cáo dự kiến doanh thu tiền phòng tại màn hình sang ngày / sp_095). Đã bóc tách đúng store chỉ định trong Excel và SSMS: `ProVistaNavyHotel.dbo.sp_039` (xử lý khách lẻ `#tempKhachLe`, che số thẻ `CD`), `ProVistaArmyHotel.dbo.sp_217` (logic hai giai đoạn, bổ sung cột Ngày dịch vụ `DateHDDV`, bộ lọc dịch vụ `multi-select`), `ProVistaArmyHotel.dbo.sp_095` (vị trí màn hình Sang ngày, tiền phòng `RM` theo bảng giá ngày kết hợp dịch vụ cố định `SP2102`). Đã trích xuất ảnh UI mẫu thực tế vào `.codex/docs/doc_baocao/images/`, SQL gốc vào `.codex/docs/doc_baocao/sql/`, lập 3 tài liệu chi tiết tại `.codex/docs/doc_baocao/` và Master Specification tại `.codex/docs/reports/ROW_158_161_164_COMPREHENSIVE_SPECIFICATION.md`.

- 2026-09-21: Hoàn thành nghiên cứu, phân tích sâu và lập bộ tài liệu đặc tả kỹ thuật chi tiết 100% cho 3 báo cáo: Dòng 150 (Báo cáo doanh thu Army Quy Nhơn / sp_292), Dòng 151 (Báo cáo doanh thu đăng ký theo ngày đi / sp_238 & sp_240), Dòng 152 (Báo cáo doanh thu lễ tân_army / sp_293). Đã trích xuất ảnh mẫu UI thực tế từ các Sheet 73, Sheet 67, Sheet 74 vào `.codex/docs/doc_baocao/images/`; trích xuất mã nguồn Stored Procedure gốc từ MS SQL Server (SSMS) vào `.codex/docs/doc_baocao/sql/`; cấu hình hoàn chỉnh 100% mã nguồn Template Reference PHP chứa đầy đủ thông số `content_json` (mảng `blocks()`, `columns()`, `customRows` công thức tổng phụ/tổng cộng `aggregate.rows.sum.*`, `grouping` 2 cấp, bảng tĩnh summary box, `topHeader` đa tầng); xây dựng đầy đủ tài liệu hướng dẫn chuyển đổi sang MySQL 8.0 và quy trình triển khai độc lập cho Agent mới tại `.codex/docs/doc_baocao/` và `.codex/docs/reports/ROW_150_151_152_COMPREHENSIVE_SPECIFICATION.md`.

- 2026-09-21: Sửa nền không đồng nhất trong bảng phân bổ DEPOSITS_SALE: các dòng dữ liệu giữ nền trắng, header và tổng nền xám; lưu style vào `content_json` (Design), biên dịch ra `content_html` và bỏ CSS tô theo vị trí dòng. Migration `2026_09_21_130000` đã đồng bộ template trên 6 connection ứng dụng; PHP lint và `git diff --check` đạt, chưa kiểm tra browser.

- 2026-09-18: Hoàn tất sửa hai báo cáo `CANCELLED_INVOICES_PAYMENTS` và `DAILY_FRONTDESK`. Báo cáo 156 đã đồng bộ layout legacy qua migration `2026_09_18_130000`; báo cáo 155 đã đồng bộ layout A4 ngang 12 cột qua migration `2026_09_18_140000`. Sửa lỗi frontend không resolve `$yesterday`, bổ sung lookup/whitelist Outlet và tiêu đề động theo chế độ hủy. Test liên quan đạt 7/7 (78 assertions), PHP lint và frontend production build đạt.

- 2026-09-18: Khắc phục lỗi lề trang bị dính sát lề khi hiển thị báo cáo: bổ sung quyền ưu tiên tuyệt đối (`!important`) cho các thuộc tính lề trang (`padding-top`, `padding-bottom`, `padding-left`, `padding-right`) và `box-sizing: border-box !important` tại khối CSS bảo vệ cuối cùng trong `TemplateRendererService.php`, đảm bảo 100% thuộc tính lề trang cấu hình từ Form Designer luôn được áp dụng chuẩn xác ra bản xem trước và không bị CSS mẫu ghi đè. Xóa bỏ CSS tĩnh `margin: 0; padding: 0;` trong khối `body` của `sales_invoices_reference.php` và cập nhật trực tiếp bảng `templates` trên cả 5 kết nối chi nhánh (`mysql`, `mysql_hkt1` đến `mysql_hkt4`). Unit tests 12/12 TemplateRendererServiceTest, 6/6 SalesInvoicesReportTest, 14/14 frontend tests và production build đạt 100%.

- 2026-09-18: Triển khai hoàn tất Báo cáo hóa đơn bán hàng (`SALES_INVOICES` / legacy `sp_094` / Row 153 Sheet 66): Procedure `rpt_sales_invoices`, migration `2026_09_18_100000`, adapter `SalesInvoicesDataAdapter`, template `sales_invoices_reference.php`. Đồng bộ thành công trên cả 5 database chi nhánh. Cập nhật `TemplateEditorModal.vue` hoàn thiện tính năng chọn ô Detail Table (viền đen `outline: 2px solid #000000`, multi-select Ctrl, nạp thuộc tính lên toolbar và inspector), bổ sung ô nhập `Chiều rộng (Width)` và `Chiều cao (Height)` trên Right Panel. Cập nhật bảng phân bổ tiền tệ lên `width: 100%`. Unit tests 9/9, backend test 5/5, frontend build đạt 100%.

- 2026-09-17: Đã hợp nhất các migration patch report đã xác minh là lặp lại vào migration gốc: DAY_USE, ROOM_STATUS_HISTORY, DEPOSITS_SALE và LAUNDRY_FREE; loại bỏ các migration đồng bộ procedure/template trung gian của Early Checkout, Transportation, Returning Guests, Company Debt, Minibar, Room Special Requests, VIP và OOO/OOS. Giữ riêng migration backfill Day Use, seed department `MR`, migration chuẩn hóa template dùng chung và chuỗi Expected Breakfast còn phụ thuộc procedure `_1`/`_2`. Chưa chạy reset database.

- 2026-09-17: Chuẩn hóa cỡ chữ (`fontSize: '9px'`) và đệm ô (`padding: '4px 4px'`) trong `content_json` của toàn bộ 3 mẫu Báo cáo dự kiến khách ăn sáng (`EXPECTED_BREAKFAST_ARMY_SUMMARY`, `EXPECTED_BREAKFAST_DTX_SUMMARY`, `EXPECTED_BREAKFAST_DETAIL`): thiết lập đồng bộ tại table block style (`style.fontSize: 9px`), cột tiêu đề/dữ liệu (`headerStyle.fontSize`, `cellStyle.fontSize`), cấp nhóm (`groups[].headerCells[].style.fontSize`), hàng tổng phụ/tổng cộng (`customRows[].cells[].style.fontSize`) và bảng thống kê quốc gia; chữ ký đặt `fontSize: 11px`. Đồng bộ thành công vào database bảng `templates` trên 5 kết nối (`mysql`, `mysql_hkt1` đến `mysql_hkt4`); Unit tests 7/7 và frontend build đạt 100%.

- 2026-09-17: Khắc phục triệt để lỗi Form Designer làm mất kiểu dáng Báo cáo dự kiến khách ăn sáng khi lưu phiên bản: cấu hình đầy đủ style (`headerStyle`, `cellStyle`, `headerCells`, `customRows[].cells[].style`, `style` của bảng quốc gia) trong các template reference và migration `2026_09_16_100000`; bổ sung `tableClassName` vào thẻ `<table>` khi biên dịch block `table` trong `TemplateEditorModal.vue`.

- 2026-09-17: Chuẩn hóa toàn bộ thông số UX/UI của 3 mẫu Báo cáo dự kiến khách ăn sáng (`EXPECTED_BREAKFAST_ARMY_SUMMARY`, `EXPECTED_BREAKFAST_DTX_SUMMARY`, `EXPECTED_BREAKFAST_DETAIL` / legacy `sp_035`, `sp_032`, Row 128) theo hình ảnh thực tế legacy: khổ giấy A4 Portrait, lề 6mm/6mm/8mm/8mm; màu nền header và subtotal/total `#dee2ed`, viền `1px solid #cbd5e1`; tiêu đề nhóm DateGroup chữ "Ngày :" màu đỏ `#b82c2c`, nhóm RoomType và tiêu đề nhóm phòng chữ xanh `#1976d2`; số lượng khách căn giữa; hàng tổng hiển thị số phòng ở Cột 2 (`{{group.count}}` / `{{aggregate.rows.count|number}}`); bổ sung khối chữ ký chân trang (`Bộ Phận FO` và `Bộ Phận F&B`) vào template Designer; lưu toàn bộ thông số vào Form Designer (`templates`) qua migration `2026_09_16_100000` trên 5 database chi nhánh.

- 2026-09-17: Form Designer đã sửa luồng nạp thuộc tính khi chọn block/ô: chuyển block xóa cell selection cũ, ô static đồng bộ `selectedBlockId`, custom/group cell giữ style top-level và style lồng legacy, group fallback không còn hiển thị toolbar giả; `npm run build` đạt.

- 2026-09-17: Nâng cấp trải nghiệm Form Designer (TemplateEditorModal.vue): Detail Table hỗ trợ chọn ô có viền đen bao quanh (outline 2px solid #000000), hỗ trợ chọn nhiều ô (Multi-select qua Ctrl/Cmd/Shift), nạp đầy đủ thuộc tính của ô đang chọn lên thanh công cụ (chữ/nội dung, in đậm B, in nghiêng I, gạch chân U, cỡ chữ, màu chữ, màu nền ô kèm nút xóa nền, căn lề và đặt lại). Cập nhật customTableCellTextStyle bổ sung fontStyle và textDecoration; 9/9 frontend unit tests và npm run build đạt 100%.

- 2026-09-17: Hoàn thành chuẩn hóa UX/UI Báo cáo lịch sử khóa phòng OOO/OOS theo ảnh chụp hệ thống cũ; procedure ngày giờ đã được cập nhật trong migration gốc `2026_08_27_160000` và `2026_08_28_170000`, template provider giữ layout cuối.

- 2026-09-17: Đã sửa lỗi ghi chú báo cáo bị dồn thành một dòng: Designer giữ `whiteSpace=pre-wrap`, compiler sinh CSS nội tuyến và provider template giữ cấu hình cuối cho cài đặt mới.

- 2026-09-17: Khắc phục lỗi HTTP 422 của Báo cáo khách VIP (`VIP_GUESTS`): procedure gốc dùng join `r.room_number = br.room_number` do bảng `booking_rooms` không có cột `room_id`.

- 2026-09-17: Hoàn thành triển khai độc lập Báo cáo khách VIP (VIP_GUESTS / legacy sp_295 / Row 131): procedure rpt_vip_guests lọc khách VIP theo ngày và phân loại khách (loại trừ RegularGuest / mã 6); template VIP_GUESTS_STANDARD chuẩn Designer v1 với bố cục A4 Landscape, 11 cột, gom nhóm Loại Khách chữ đỏ #ff1414, nền header/subtotal #dee2ed; 0 file dùng chung bị thay đổi. Migration 2026_09_17_170000 đã chạy thành công trên 7 database; VipGuestsReportTest đạt 3/3 (62 assertions); frontend production build đạt 100%.

- 2026-09-17: `ROOM_SPECIAL_REQUESTS` đã khôi phục bố cục legacy: 9 cột dữ liệu và 2 custom row scope `detail` (`Đăng Ký`, `Ghi Chú`) lặp theo từng dòng; đã sửa renderer dùng chung cho bảng không grouping, giữ xuống dòng ghi chú và migrate trên các database có bảng `templates`.

- 2026-09-17: Báo cáo `ROOM_SPECIAL_REQUESTS` đã bỏ tiêu đề nhóm `Đăng Ký/Ghi Chú`; hai trường này hiển thị thành cột dữ liệu trên từng dòng trong provider/migration gốc.

- 2026-09-17: Sửa canvas preview của Designer để render HTML trong `headerCells.content` thay vì hiển thị thẻ HTML thô; không đổi dữ liệu database và frontend build đạt.

- 2026-09-17: Sửa Designer không nạp cấu hình nhóm legacy của `ROOM_SPECIAL_REQUESTS`: `TemplateEditorModal.vue` nay ánh xạ `grouping` sang `groups`, giữ setup dòng `Đăng Ký/Ghi Chú` khi mở thiết kế; frontend build đạt.

- 2026-09-17: Đã sửa lỗi HTTP 422 của `ROOM_SPECIAL_REQUESTS` và `PAID_COMPANY_DEBTS`: migration gốc dùng đúng `booking_rooms.children_qty`/`room_number`; gọi trực tiếp hai procedure trên 5 connection không còn lỗi SQL.

- 2026-09-16: Hoàn thành triển khai hai báo cáo mới theo danh mục và SSMS:

  1. `ROOM_SPECIAL_REQUESTS` (Row 122 / legacy `sp_297`): Báo cáo yêu cầu đặc biệt; procedure `rpt_room_special_requests` hỗ trợ lọc theo 3 loại ngày (Ở, Đến, Đi), phòng, user; loại phòng ảo/nội bộ và booking hủy; gom nhóm theo Đăng Ký với thông tin ghi chú và tổng số lượng; layout 9 cột theo chuẩn legacy.

  2. `PAID_COMPANY_DEBTS` (Row 148 / legacy `sp_294`): Báo cáo công nợ đã thanh toán; procedure `rpt_paid_company_debts`, adapter `PaidCompanyDebtsDataAdapter` tổng hợp bảng kê HTTT và các tổng tiền; header 2 tầng 16 cột gom nhóm Ngày TT (đỏ) -> Công ty (đen), kèm bảng chữ ký 4 cột.

  - Cả hai template được thiết lập đầy đủ Designer blocks v1 (`content_json`, `content_html`, `css`) trong migration và nạp thành công trên 7 database PMS (`pms_system`, `pms_data`, `pms_db`, `pms_hkt1` đến `pms_hkt4`). Unit/feature tests đạt 148/148 suite Report (1.316 assertions), frontend build đạt 100%.

- 2026-09-16: Báo cáo dự kiến khách ăn sáng đã sửa dữ liệu trẻ em từ `booking_child_breakfast_details` theo ngày dịch vụ legacy, phân loại trẻ tính phí/MP/KAS và phụ thu DTX; quốc gia hiển thị tên đầy đủ viết hoa. Migration `2026_09_16_170000`/`171000` đã chạy; cấu hình JSON ba mẫu và HTML/CSS DTX được giữ tương thích Designer.

- 2026-09-16: Designer của `EXPECTED_BREAKFAST` đã dùng cùng parameter UI schema với Report Viewer và lưu default preview riêng cho ba mẫu. Army/DTX mặc định tổng hợp; mẫu Chi tiết mặc định bật `p_show_room_details`; source không có một report active duy nhất vẫn giữ fallback control cũ.

- 2026-09-16: Đã xóa hai report legacy dư `EXPECTED_BREAKFAST_1`/`EXPECTED_BREAKFAST_2`, hai template chuẩn và hai data source chỉ phục vụ chúng trên 5 database. Giữ một report `EXPECTED_BREAKFAST` cùng ba mẫu đúng nghiệp vụ; đổi tên mẫu chi tiết, không đổi layout hoặc logic procedure.

- 2026-09-16: Đã hợp nhất dòng 128 thành report `EXPECTED_BREAKFAST` với wrapper `rpt_expected_breakfast`; tham số `p_show_room_details` chọn mẫu tổng hợp/chi tiết. Đồng bộ logic legacy `sp_035`/`sp_032` trên 5 database, gồm cutoff `09:30`, availability strict, late check-in phải có room-night bill và mã dịch vụ ăn sáng lấy thêm từ cấu hình. Mẫu Army 9 cột, DTX 10 cột có tiền, chi tiết 6 cột; runtime tải mẫu đã lưu từ Designer.

- 2026-09-15: Hoàn thiện và tách độc lập 2 báo cáo "Báo cáo dự kiến khách ăn sáng 1" (EXPECTED_BREAKFAST_1, Mẫu tổng hợp theo phòng từ legacy sp_035) và "Báo cáo dự kiến khách ăn sáng 2" (EXPECTED_BREAKFAST_2, Mẫu chi tiết khách trong phòng từ legacy sp_032). Tạo 2 Stored Procedures rpt_expected_breakfast_1 và rpt_expected_breakfast_2, 2 Data Sources, 2 Templates và 2 Report Definitions riêng biệt trên menu Báo cáo phòng. Mẫu 1 hiển thị 9 cột với gom nhóm DateGroup -> RoomType kèm bảng phụ Thống kê khách theo quốc gia; Mẫu 2 hiển thị 6 cột với gom nhóm 3 cấp DateGroup -> RoomType -> Tiêu đề nhóm phòng (DetailRoom) và dòng chi tiết từng khách/trẻ em. Đã migrate thành công trên 5 database chi nhánh (mysql, mysql_hkt1 đến mysql_hkt4). Unit tests (5/5, 139/139 suite) và frontend build đạt 100%.

- 2026-09-15: Hoàn thiện báo cáo "Báo cáo hóa đơn minibar miễn phí" (MINIBAR_FREE_INVOICES) theo legacy sp_202 (Outlet='MB', PaymentMethod='CL'). Chuẩn hóa cấu trúc report header band theo canonical format (columns 30% logo / 70% thông tin khách sạn + divider + title + period, wrapper `<div class="report-header-band">`). Stored procedure rpt_minibar_free_invoices, template tham chiếu minibar_free_invoices_reference, enricher và filter frontend đã được đồng bộ và migrate thành công trên toàn bộ database chi nhánh. Unit tests và frontend build đạt 100%.

- 2026-09-15: Xuất XLSX báo cáo dùng HTML đã render để giữ bố cục Report Viewer. Converter đọc style đã Designer biên dịch: tỷ lệ cột/ô (kể cả 30/70), ảnh/logo, merge, font, màu/nền, căn lề, wrap, line-height, padding/margin, height/min-height và viền cạnh. Giữ endpoint/dataset/procedure hiện tại và fallback XLSX cũ khi HTML rỗng hoặc lỗi. Unit test export + renderer đạt 16/16.

- 2026-09-15: Sửa quy đổi tỷ lệ cột XLSX: giới hạn boundary để mọi cột logic có ít nhất một cột Excel. Khắc phục header `LAUNDRY_INVOICES_BY_PRODUCT` làm tròn chồng `Số lượng` sang `Thành tiền`, khiến chiều cao hàng header tăng bất thường. Unit test export + renderer đạt 17/17.



- 2026-09-14: Đã chuẩn hóa Báo cáo đặt cọc Sale (DEPOSITS_SALE) theo legacy sp_039: xác định và đồng bộ mã bộ phận Reservation / Kinh Doanh (code='MR') vào bảng departments trên toàn bộ database chi nhánh (mysql, mysql_hkt1 đến mysql_hkt4), cập nhật ReportLookupController::serviceDepartments để nạp đầy đủ danh mục phòng ban; chuẩn hóa Stored Procedure rpt_deposits_sale sinh GroupHeader (CONCAT(base.ShowDeposit, ' / Thanh Toán: ', base.PaymentMethod)), tính toán các cột tiền theo payments.date; cập nhật template deposits_sale_reference gom nhóm theo GroupHeader; chạy migration độc lập 2026_09_14_231000 đồng bộ metadata và bộ lọc UI theo thứ tự chuẩn legacy (mặc định phòng ban MR, hiển thị tiền cọc bật). PHPUnit test đạt 100%.

- 2026-09-14: Đã hủy thử nghiệm thay Designer bằng PDFme theo yêu cầu; gỡ package, component, adapter và cấu trúc `engine: pdfme`. Designer hiện tiếp tục dùng cấu trúc block legacy đã được nâng cấp, không đổi API/database.



- 2026-09-14: Đã hoàn thiện độc lập báo cáo MINIBAR_INVOICES_BY_PRODUCT theo legacy sp_206 (Outlet='MB'); procedure rpt_minibar_invoices_by_product sinh ID tăng dần, nhóm Loại Minibar chữ đỏ, Thành tiền, Giảm giá và Tổng tiền theo đúng ảnh hệ thống cũ. Đã khắc phục lỗi HTTP 422 và chuẩn hóa cấu trúc Designer blocks cho template minibar_invoices_by_product_reference (sinh content_html từ blocks), bổ sung định dạng số cho group.sum trong TemplateRendererService. Đã sửa hai nút "Hàng bán" (tích hợp filter chuẩn HousekeepingInvoiceFilters đảo bit đúng logic legacy sp_206) và "Nhóm theo ngày" (bổ sung cấp nhóm DateGroup động theo parameters.p_group_by_date trên procedure và template). Đã đồng bộ procedure lên HKT1-HKT4 và template runtime. Unit tests và production build đạt.

- 2026-09-14: PMS Report Designer đã có lớp thao tác v2 gồm Report Explorer, Undo/Redo gom trạng thái, clipboard/nhân bản block, phím tắt, khóa/ẩn block, điều kiện hiển thị block theo tham số, Shape và Page Break; giữ tương thích `content_json` cũ và không đổi API/database.

- 2026-09-14: Thanh công cụ trên canvas đã bỏ các nút Undo/Redo/Chép/Dán/Nhân bản theo yêu cầu; thay bằng thanh thuộc tính thống nhất cho phần tử được chọn, giữ cấu hình riêng cho static/detail table.

- 2026-09-14: Cỡ chữ Designer tăng/giảm theo bước `0.5px`. Header band của các báo cáo được chuẩn hóa bằng CSS dùng chung theo mẫu `MINIBAR_INVOICES_BY_PRODUCT`, áp dụng đồng thời trong canvas preview và TemplateRendererService; không đổi dữ liệu/API.

- 2026-09-14: Đã chạy migration `2026_09_14_230000` và `2026_09_14_230100`: 33 template báo cáo `*_STANDARD`/`*_REFERENCE` trong database dùng chung header `content_json` dạng columns 30%/70% theo mẫu minibar; `content_html` cũng được đồng bộ, giữ nguyên nội dung title/period/detail/footer.



- 2026-09-14: Static-table Inspector đã có chọn nhiều ô, áp dụng theo ô/vùng chọn/hàng/cột/bảng, định dạng mở rộng, chép/dán kiểu, gộp/tách ô và menu chuột phải; canvas và HTML báo cáo cùng dùng `colspan`/`rowspan`.



- 2026-09-14: Form Designer đã hỗ trợ định dạng phần tử cho `static-table` và `table`; bảng phân bổ tiền tệ của `DEPOSITS_SALE` đã chuyển hàng đầu về chữ thường. Frontend build và test style đạt.

- 2026-09-10: LA/BR/MB và COMPANY_DEBT đã có schema/import, procedure/datasource/report/template, adapter và UI. Migration tương thích JSON đã chạy HKT1-HKT4; còn nghiệm thu dữ liệu old/new và browser/in/export.

- 2026-09-11: Đã bổ sung riêng hai mã báo cáo `LAUNDRY_INVOICES_BY_PRODUCT` và `LAUNDRY_FREE_INVOICES`; đã đăng ký migration/template/procedure và nối whitelist UI/runtime. Chưa chạy migration database.



## Recent Changes

- 2026-09-25: Dòng 233 bổ sung nhánh chốt hóa đơn 0đ riêng: UI chỉ thêm payment 0đ vào bảng sau khi bấm **Thêm**, và chỉ gửi cờ `zero_balance_close` khi dịch vụ được cọc/tạm ứng bù đủ; API kiểm tra lại bill/cọc cùng phạm vi trước khi liên kết payment/invoice và đóng bill. Request không có cờ và luồng amount khác 0 giữ nguyên; không migration/backfill. `SalesInvoiceSettlementTest` đạt 8/8 (61 assertions).
- 2026-09-25: `PaymentModal.vue` cập nhật `payAmountNum` theo `remainingAmount` sau thao tác thêm/xóa dòng tạm; giữ nguyên cấu trúc payment rows và điều kiện submit, không thay đổi API/backend hay Prepayment Modal. Frontend build đạt.
- 2026-09-25: Tách cờ No Post Master/phòng mà không đổi schema hoặc dữ liệu cũ; Master room-charge tiếp tục post phòng hợp lệ và trả danh sách phòng riêng lẻ bị bỏ qua. Checkout nhận kết quả một phần; No Post phòng không còn khóa điều chỉnh giá toàn Booking nếu phòng đang chọn hợp lệ.
- 2026-09-25: Checkout khóa nút mở form post FO/HK khi Booking/phòng có No Post; AddServiceModal chặn trước submit, room-charge Master bị khóa nếu bất kỳ phòng đích nào có No Post; HK hiển thị badge và chặn thêm vào giỏ/gửi bill; điều chỉnh giá và early-checkout charge khóa đích bị chặn. Chỉ tác động UI Checkout/Hóa đơn và Buồng phòng; giữ nguyên backend/API.
- 2026-09-25: Sửa `PaymentModal.vue`/`PrepaymentModal.vue` để ca lấy từ `/shifts`, map theo giờ và khóa lưu khi cấu hình ca thiếu/lỗi; UI ngày Payment dùng icon lịch riêng, cho phép gõ bàn phím và xác thực giới hạn ngày; cập nhật giải thích chế độ tiền phòng trong `AddServiceModal.vue`. `PaymentController` fail-closed khi chưa có ca; `BookingRoomServiceController` chặn No Post theo Booking/phòng cho FO, HK, room charge, điều chỉnh giá và no-show. Bổ sung hồi quy ca/No Post; không đổi Night Audit, dòng 238, schema hoặc dữ liệu.
- 2026-09-24: Sửa `CheckoutPage.vue`, `PaymentModal.vue`, `PrepaymentModal.vue`, `PaymentController.php`, `BookingRoomServiceController.php` và route API cho sáu vấn đề Checkout. API chuyển payment chỉ nhận DPR/AP chưa sử dụng và bảo toàn marker khi audit transfer; service bill có endpoint chỉnh mô tả riêng. Cập nhật `.codex/docs/frontdesk_checkout/README.md` và mapping `payments`.
- 2026-09-24: Cập nhật giới hạn ngày/ca và quyền City Ledger ở Payment/Prepayment; backend xác thực ngày/ca/Công ty. Checkout date bounds FO và RM theo phòng; No Post áp dụng cho post FO/HK/room-charge. `service_bills` owner logic (dòng 238) để nguyên do ảnh hưởng tới luồng đọc bill ngoài phạm vi.

- 2026-09-23: Thêm migration/procedure/template/test cho ROOM_FORECAST, WEEKLY_ROOM_REPORT và TOTAL_REVENUE. Cập nhật executor/lookup/frontend dùng chung để hỗ trợ đa chi nhánh Dòng 170, preset tuần và ẩn cột tài chính Dòng 169. Test riêng đạt 11/11 (115 assertions), PHP lint, `git diff --check`, route list và frontend build đạt; chưa chạy migration thật.
- 2026-09-23: Sửa 7 reference template để không dựng HTML báo cáo độc lập ngoài JSON; các giá trị dòng, tổng, nhóm và tham số đều đi qua binding trong `content_json`, sau đó biên dịch thành `content_html` và giữ CSS riêng. Không sửa renderer/designer dùng chung hoặc API.
- 2026-09-23: Dòng 154 dùng đúng nhóm legacy `Doanh Thu Phòng`/`Doanh Thu Nhà Hàng`; Dòng 160 đọc danh sách nhóm doanh thu từ `hotel_configs`, bổ sung các dòng `1-4/1-6/1-8`, sắp xếp số tự nhiên và giữ trạng thái `6-1 = Đã hoàn tất`; Dòng 166/168 tách mã booking nội bộ có prefix khỏi mã tham chiếu OTA; Dòng 168 Mode 1 lấy toàn bộ doanh thu trong thời gian lưu trú, Mode 2 giữ lọc theo đêm phòng.
- 2026-09-23: Đồng bộ contract JSON runtime của 6 dòng trong .codex/docs/reports/REPORT_CONFIGURATIONS_JSON_6_REPORTS.md; loại bỏ mô tả tham số legacy không còn được procedure dùng. Bổ sung test contract cho hai hotfix procedure; test đạt 6/6, 66 assertions.
- 2026-09-22: Tạo migration `2026_09_22_100000` đăng ký Dòng 150 `REVENUE_ARMY`, Dòng 151 `REVENUE_BY_DEPARTURE_DATE`, Dòng 158 `RECEPTION_CASHIER_SHIFT`; thêm các migration `110000`–`160000` để bật row 164, sửa gom nhóm row 151, tương thích schema HKT4 và alias binding template row 150. Cập nhật adapter/enricher riêng cho row 158, template City Ledger và frontend bypass quyền tạm thời chỉ cho row 164. Test mới 4/4, ReportProcedureServicesTest 4/4, frontend build đạt; không sửa schema bảng nghiệp vụ.



- 2026-09-23: Booking Sections 3/5/8/13/14/15/16/18/19 đã audit theo source DOCX. Chuẩn hóa quyền service view/add/edit/delete; đồng bộ actual arrival và child breakfast details khi đổi ngày phòng nhưng không tạo BD service; realtime chỉ dispatch sau commit; tìm booking giữ đúng module; ẩn phòng hủy theo header; áp dụng SyncRoomDateByBookingDate kèm AV/over-room; xử lý RegistrationStatusId_BookingCancel; General Search giữ booking active khi chỉ hủy phòng. Section 5 ghi **Chưa có mô tả nguồn**, không implement. Chi tiết: `.codex/docs/booking/sections_3_5_8_13_14_15_16_18_19.md`.



- 2026-09-23: Booking Sections 4/6/7/9/11/12/17/20: cập nhật RoomAvailabilityService và allocation validation cho day-use/overnight overlap; persist hourly/is_day_use và 0 nights; đưa `IsRoomNightRoomDayUse` vào NightAudit/post room charge; thêm moved-room relation/display; blank Quick Update + no-selection warning; bulk rate-only chỉ cập nhật `booking_rooms`; đồng bộ `rate/base_price` và giữ nightly RM details. Added `.codex/docs/booking/sections_4_6_7_9_11_12_17_20.md`. Không schema/migration.



- 2026-09-23: Hoàn thiện Booking Section 1/2/10 theo `Các nghiệp vụ liên quan tới booking 2.docx`: edit cọc đổi HTTT đồng bộ mô tả sinh tự động; ngày xác nhận chỉ tính khi tạo, clamp theo ngày hệ thống và giữ nguyên khi edit; cảnh báo check-in cùng mẫu được gom theo phòng và bỏ tên `AllowCheckinVacantClean`. Thêm `BookingSectionsOneTwoTenTest.php` và `.codex/docs/booking/sections_1_2_10.md`. PHP lint/git diff check đạt; PHPUnit và frontend build bị chặn bởi môi trường.

- 2026-09-23: Khóa phòng (3 sections note 22/09): (1) Ẩn tên/loại khóa trên tooltip Kế hoạch phòng, thêm Người khóa; Room Map hover hiển thị tooltip phòng khóa; phân quyền RoleUserUnlockRoomOOO/OOS trên cả màn hình Khóa phòng và đổi trạng thái phòng trên Room Map. (2) Mở khóa cập nhật `end_date` về ngày hệ thống + giờ mở khóa (`status = 'Done'`, `is_active = 2`); Kế hoạch phòng/thống kê kiểm tra cutoff 12:00: ngày 10 tính 1 phòng OOO, ngày 11 không tính. (3) Kiểm tra Overbooking AV (`AllowOverRoomTypeRoomKind`) trước; chặn cứng khi = 0. Kiểm tra unassignable booking (`AllowLockRoomCauseUnassignableRoomBK`) sau. 15/15 backend tests passed, frontend build passed.

- 2026-09-21: Row 152: thêm enrichment `revenue_summary` chỉ cho `RPT_RECEPTION_REVENUE_ARMY`/`RECEPTION_REVENUE_ARMY` và bảng summary 2 nhóm trong template reference. Không sửa procedure/data aliases, renderer chung hoặc nhánh báo cáo khác; chưa chạy test, build hay migration database.

- 2026-09-21: Row 164: nối handler Day Close tới report `EXPECTED_ROOM_REVENUE_NIGHT_AUDIT`. ReportsPage mở tab theo mã report; chưa chạy test, build hoặc migration database.

- 2026-09-21: `DEPOSITS_SALE` giờ tổng theo từng `GroupHeader` và procedure lấy mã thao tác từ `created_by` khi `username` trống; migration `2026_09_21_160000` đã chạy trên 5 database ứng dụng. Tạo tài liệu quy trình báo cáo và thêm chỉ dẫn bắt buộc vào `AGENTS.md`; `.gitignore` chỉ mở theo dõi `AGENTS.md`, `PROJECT_MEMORY.md` và hai tài liệu report được chỉ định.

- 2026-09-21: Hoàn thành bộ đặc tả kỹ thuật 100% cho Dòng 158 (Báo cáo thu ngân lễ tân - `sp_039` Navy), Dòng 161 (Báo cáo doanh thu hai giai đoạn - `sp_217` Army), Dòng 164 (Báo cáo dự kiến doanh thu tiền phòng - `sp_095`). Bóc tách cấu trúc 3 bảng và nhóm 2 cấp của Dòng 158; xử lý 2 yêu cầu nâng cấp bắt buộc của Army cho Dòng 161 (thêm cột Ngày dịch vụ `DateHDDV`, bộ lọc multi-select dịch vụ); bóc tách popup màn hình Sang ngày và thuật toán tiền phòng + dịch vụ cố định hàng ngày cho Dòng 164. Cung cấp đầy đủ file tài liệu riêng cho từng báo cáo, Master Specification và template `content_json` chuẩn Form Designer.

- 2026-09-18: Báo cáo lễ tân hằng ngày không còn gửi literal `$yesterday`, loại lỗi `STR_TO_DATE` và thông báo “Unable to execute the report stored procedure”. Báo cáo hủy hóa đơn/thanh toán được chỉnh header, tiêu đề theo mode, nhóm Ngày/Bộ phận, tổng phụ/tổng giai đoạn, tỷ lệ cột và thông tin header theo ảnh legacy. Bổ sung `outlets` cho lookup và Form Designer whitelist; không đổi logic các Store khác.

- 2026-09-18: Khắc phục lỗi lề trang bị dính sát lề khi hiển thị báo cáo: bổ sung quyền ưu tiên tuyệt đối (`!important`) cho các thuộc tính lề trang (`padding-top`, `padding-bottom`, `padding-left`, `padding-right`) và `box-sizing: border-box !important` tại khối CSS bảo vệ cuối cùng trong `TemplateRendererService.php`, đảm bảo 100% thuộc tính lề trang cấu hình từ Form Designer luôn được áp dụng chuẩn xác ra bản xem trước và không bị CSS mẫu ghi đè. Xóa bỏ CSS tĩnh `margin: 0; padding: 0;` trong khối `body` của `sales_invoices_reference.php` và cập nhật trực tiếp bảng `templates` trên cả 5 kết nối chi nhánh (`mysql`, `mysql_hkt1` đến `mysql_hkt4`). Unit tests 12/12 TemplateRendererServiceTest, 6/6 SalesInvoicesReportTest, 14/14 frontend tests và production build đạt 100%.

- 2026-09-18: Triển khai hoàn tất Báo cáo hóa đơn bán hàng (`SALES_INVOICES` / legacy `sp_094` / Row 153 Sheet 66): Procedure `rpt_sales_invoices`, migration `2026_09_18_100000`, adapter `SalesInvoicesDataAdapter`, template `sales_invoices_reference.php`. Đồng bộ thành công trên cả 5 database chi nhánh. Cập nhật `TemplateEditorModal.vue` hoàn thiện tính năng chọn ô Detail Table (viền đen `outline: 2px solid #000000`, multi-select Ctrl, nạp thuộc tính lên toolbar và inspector), bổ sung ô nhập `Chiều rộng (Width)` và `Chiều cao (Height)` trên Right Panel. Cập nhật bảng phân bổ tiền tệ lên `width: 100%`. Unit tests 9/9, backend test 5/5, frontend build đạt 100%.

- 2026-09-17: Gộp các migration report patch đã xác minh là trùng vào migration gốc; cập nhật test/doc tham chiếu theo tên file mới. Giữ migration backfill/seed/global và chuỗi Expected Breakfast có dependency nội bộ. Chưa chạy database reset.

- 2026-09-17: Khắc phục triệt để lỗi Form Designer làm mất kiểu dáng Báo cáo dự kiến khách ăn sáng khi lưu phiên bản: cấu hình đầy đủ style (`headerStyle`, `cellStyle`, `headerCells`, `customRows[].cells[].style`, `style` của bảng quốc gia) trong các template reference và migration `2026_09_16_100000`; bổ sung `tableClassName` vào thẻ `<table>` khi biên dịch block `table` trong `TemplateEditorModal.vue`.

- 2026-09-17: Sửa selection state của `TemplateEditorModal.vue`: block/band selection reset cell target cũ, static cell cập nhật block đang chọn, custom/group cell bảo toàn style legacy khi normalize; group fallback chuyển về block selection. Không đổi API, database hoặc logic runtime báo cáo; production build đạt.

- 2026-09-17: Nâng cấp Form Designer (TemplateEditorModal.vue): Chọn ô Detail Table tô viền đen bao quanh (outline 2px solid #000000, không dịch chuyển bố cục); hỗ trợ chọn nhiều ô (Multi-select) khi nhấn giữ phím Ctrl/Cmd/Shift; Toolbar canvas tự động nạp thuộc tính ô đang chọn (nội dung, in đậm B, in nghiêng I, gạch chân U, cỡ chữ, màu chữ, màu nền ô + xóa nền, căn lề và đặt lại định dạng). Bổ sung fontStyle và textDecoration cho customTableCellTextStyle; tạo TemplateEditorModalDetailTable.test.js (4/4 tests passed), toàn bộ 9/9 tests passed và npm run build đạt 100%.

- 2026-09-17: Chuẩn hóa UX/UI hai báo cáo khóa phòng OOO/OOS theo thiết kế legacy: cập nhật template provider và procedure trong migration gốc `2026_08_27_160000` và `2026_08_28_170000`; cập nhật tài liệu báo cáo tương ứng.

- 2026-09-17: Bổ sung tùy chọn `Giữ xuống dòng` cho custom row trong Designer và bảo toàn thuộc tính này khi nạp/lưu template; test báo cáo đạt 15/15 (82 assertions), frontend build đạt.

- 2026-09-17: Triển khai độc lập Báo cáo khách VIP (`VIP_GUESTS` / Row 131 / legacy `sp_295`). Tạo template provider `vip_guests_reference.php` chuẩn Designer v1 (A4 Landscape, 11 cột, gom nhóm Loại Khách chữ đỏ `#ff1414`, nền header/subtotal `#dee2ed`). Tạo migration `2026_09_17_170000_create_vip_guests_report.php` gồm procedure `rpt_vip_guests`, data source, template và report definition thuộc nhóm Báo cáo khách. Đã chạy migrate thành công trên 7 database; VipGuestsReportTest đạt 3/3 (62 assertions); frontend production build đạt; 0 file dùng chung bị thay đổi.

- 2026-09-17: Khôi phục 2 hàng chi tiết `Đăng Ký`/`Ghi Chú` cho từng dòng `ROOM_SPECIAL_REQUESTS`; cập nhật `TemplateRendererService` và compiler Designer để custom row `detail` hoạt động khi không grouping. Frontend build và test hồi quy đạt.

- 2026-09-17: Cập nhật template `ROOM_SPECIAL_REQUESTS`: xóa `grouping`, thêm hai cột `Đăng Ký`/`Ghi Chú` binding theo từng dòng, điều chỉnh dòng tổng và tạo migration đồng bộ template hiện có.

- 2026-09-17: Canvas Designer render đúng `headerCells.content` của group header bằng HTML; không chạy migration, không đổi API/database/procedure.

- 2026-09-17: Designer report yêu cầu đặc biệt được bổ sung tương thích ngược từ key `grouping` của template legacy sang key `groups` mà Designer sử dụng. Không đổi API, procedure, database schema hoặc layout runtime; frontend production build đạt.

- 2026-09-17: Sửa lỗi stored procedure của report 39/40 gây HTTP 422 do tham chiếu cột không tồn tại (`br.children`, `br.room_id`). Cập nhật migration gốc để cài mới đúng và thêm migration đồng bộ procedure cho database hiện có; không đổi frontend, schema bảng hoặc API. PHP lint đạt; direct CALL trên 5 connection đạt. Nhóm test report đạt 7/8, một test lỗi do SQLite test environment thiếu bảng `users`.

- 2026-09-16: Triển khai 2 migration độc lập `2026_09_16_180000_create_room_special_requests_report.php` (Row 122) và `2026_09_16_190000_create_paid_company_debts_report.php` (Row 148). Tạo 2 template provider chuẩn Designer v1, 1 adapter `PaidCompanyDebtsDataAdapter`, cập nhật `ReportDatasetEnricher` (không phá vỡ backward compatibility). Chạy migration trên 7 database; Feature tests đạt 7/7 mới, 148/148 toàn suite Reports; frontend build đạt.

- 2026-09-16: Theo duyệt của user, sửa file dùng chung `TemplateEditorModal.vue`: Designer chụp trạng thái đã lưu sau khi nạp schema tham số, giữ nguyên `content_html` khi lưu không có thay đổi và chỉ biên dịch lại sau thay đổi canvas. Migration `2026_09_16_170000` sửa hai procedure nội bộ, datasource và metadata/HTML/CSS mẫu; migration `171000` vá JSON mẫu đang lưu. Phạm vi chỉ là `EXPECTED_BREAKFAST`, không đổi API, schema/import, Booking, Checkout hay report khác. `ExpectedBreakfastReportTest` đạt 7/7 (86 assertions); frontend production build đạt.

- 2026-09-16: `TemplateEditorModal` đọc metadata UI của report duy nhất dùng cùng data source, hỗ trợ date range, select/options source và checkbox cho preview; migration `2026_09_16_160000` đồng bộ default preview cho ba mẫu ăn sáng. Không đổi API procedure hay render runtime.

- 2026-09-16: Migration `2026_09_16_150000` dọn metadata report ăn sáng dư có guard dependency; giữ procedure nội bộ `_1`/`_2` vì wrapper dùng chúng. Mẫu detail đổi tên thành `Báo cáo dự kiến khách ăn sáng - Chi tiết`.

- 2026-09-16: Thêm migrations `2026_09_16_100000`–`140000` để vá procedure báo cáo theo legacy, thêm trường tiền DTX, tạo wrapper/report/template dùng chung và đồng bộ `customRows` DTX chỉ khi Designer chưa có. Chỉ sửa watcher `ReportsPage.vue` trong nhánh nhận diện report dự kiến ăn sáng; không đổi logic report khác. Test `ExpectedBreakfastReportTest` đạt 7/7 với 61 assertions; frontend production build đạt.

- 2026-09-15: Thay luồng XLSX raw-field bằng converter HTML → worksheet cho báo cáo đã render; ô bảng, merge, `colgroup`/width ô, CSS `!important`, logo block/cell, màu/căn lề, font decoration, wrap, kích thước và thiết lập trang được chuyển sang PhpSpreadsheet. Bảng phụ được quy đổi vào lưới bảng chính; fallback cũ giữ cho HTML trống/lỗi; không thay đổi API, template, database hay logic truy vấn.

- 2026-09-15: Bổ sung hồi quy cho bảng tỷ lệ cột hẹp 7 cột, bảo đảm exporter không tạo vùng ô độ rộng 0 hoặc ghi đè header khi làm tròn tỷ lệ sang lưới Excel.



- 2026-09-14: Bổ sung lịch sử tối đa 60 snapshot với debounce 350ms, cấp lại toàn bộ ID khi nhân bản block lồng, loại block `visible=false` khỏi HTML runtime và render điều kiện block opt-in theo tham số. Frontend đạt 42/42 utility tests, backend TemplateRenderer 9/9 tests và production build thành công.



- 2026-09-14: Mở rộng schema style ô tĩnh với phông chữ, căn dọc, khoảng dòng, ngắt dòng, bốn padding, bốn cạnh viền và kích thước. Node test 7/7 và frontend production build đạt.



- 2026-09-14: Bổ sung style kế thừa block → hàng → ô cho `static-table`, gồm độ đậm, căn lề, cỡ chữ, màu chữ và màu nền; template cũ giữ tương thích. `DEPOSITS_SALE` dùng cấu hình mới để bỏ in đậm hàng đầu.

- 2026-09-14: Rà soát Designer và bổ sung `headerStyle`/`cellStyle` cho Detail Table, font cho ô nhóm/hàng tùy chỉnh, đồng bộ border/padding canvas với HTML, bỏ ghi đè từ style rỗng và biên dịch ngay khi đổi thuộc tính block. Frontend test 33/33, backend hồi quy 10/10 (32 assertions) và production build đạt; chưa chạy migration database.

- 2026-09-14: Thanh thuộc tính chung phía trên canvas nạp style của ô static table được click, thay cho danh sách hàng/ô dài ở sidebar; vẫn hỗ trợ vùng chữ, header/data Detail Table và ô tổng/nhóm. Frontend build và test utility đạt; cần kiểm tra thao tác trực tiếp trên trình duyệt.

- 2026-09-11: Dòng 137 có procedure/template riêng theo tổng hợp sản phẩm của `sp_206`; dòng 138 có procedure/template riêng cho hóa đơn miễn phí theo `sp_125` và phương thức thanh toán `CL`. Không sửa Checkout/Payment hoặc renderer dùng chung.

- 2026-09-11: `ReportsPage.vue` nhận diện hai mã mới bằng bộ lọc HK hiện có; `ReportDatasetEnricher` chỉ làm giàu dữ liệu cho mã Free, mã theo sản phẩm giữ schema tổng hợp riêng.

- 2026-09-11: Designer tự thu phóng để vừa toàn bộ khổ giấy, kể cả A4 ngang; có mức zoom thủ công và ẩn/hiện panel nguồn dữ liệu, thuộc tính. Chỉ thay đổi trải nghiệm chỉnh sửa canvas, không đổi dữ liệu mẫu, API, Viewer, export hoặc bản in.

- 2026-09-10: Tách đăng ký runtime theo mã báo cáo: LA dùng migration `300000`, BR dùng `320000`, MB dùng `330000`; migration tương thích procedure Công nợ tách `311000`. Mỗi mã có thể được commit và review độc lập, vẫn dùng installer HK chung đã có.

- 2026-09-10: Khôi phục dependency từ `composer.lock` và tạo lại autoload; export PDF/Excel/Word của báo cáo chạy lại được. Bộ kiểm tra Reports/Template/Export đạt 41 tests, 383 assertions; frontend đạt 14 tests.

- 2026-09-10: Renderer và canvas Designer bỏ giới hạn `max-width:210mm` từ CSS mẫu legacy sau khi áp dụng CSS tùy biến; khi chuyển A4 ngang, nội dung LA/BR/MB/Công nợ giãn theo toàn bộ chiều rộng trang đã lưu.

- 2026-09-10: Sửa Designer khi template có `backgroundColor` rỗng: input màu dùng fallback hợp lệ nhưng vẫn giữ nền rỗng. Đổi khổ/chiều/lề và các thuộc tính block của LA/BR/MB/Công nợ không còn bị lỗi trình duyệt chặn render.

- 2026-09-10: Designer và Report Viewer lấy kích thước trang từ metadata `page_size`/`page_orientation`, giữ lề `0mm`; renderer đặt `@page` từ metadata sau Custom CSS để chiều, khổ giấy và lề lưu lại luôn được ưu tiên khi xem trước/in.

- 2026-09-10: Sửa lỗi LA/BR/MB và COMPANY_DEBT trên MariaDB không hỗ trợ `JSON_ARRAYAGG`; thay bằng `GROUP_CONCAT(JSON_OBJECT(...))`, migration hiệu chỉnh đã chạy HKT1-HKT4. Gọi trực tiếp LA trên HKT1 trả 1 dòng trong khoảng 2020–2030; Company Debt và LA ngày 09/09/2026 chạy hợp lệ. Bộ lọc HK đã đồng bộ style Reports chung.

- 2026-09-10: `ReportDefinitionController` cho phép lưu metadata với `options_source=report-shifts` và `service-departments`; whitelist cũ được giữ nguyên.

- 2026-09-10: Modal Cấu hình báo cáo đã đồng bộ control `radio` và toàn bộ `options_source` với whitelist API, gồm phòng, người dùng, Ca làm việc và Bộ phận dịch vụ.

- 2026-09-10: Provider LA/BR/MB và COMPANY_DEBT dùng màu sắc, font và viền của Reports hiện tại; giữ bố cục A4, tỷ lệ cột, grouping và khoảng cách của mẫu legacy.



- 2026-09-10: Bổ sung nullable fields đã xác minh của SP3000/SP3001/SP6000; thêm `sales_invoices` cho tập cột SP3003 cần bởi sp_212 và legacy keys/snapshot cho payments, payment_debt_settlements, companies. Không backfill, không đổi logic Checkout/Payment/post bill.

- 2026-09-10: ReportsPage chỉ dùng bộ lọc legacy riêng cho LAUNDRY_INVOICES/BREAKAGE_INVOICES/MINIBAR_INVOICES; lookup ca/bộ phận và dataset enricher chỉ thêm nhánh cho bốn report mới.



## Known Risks

- 2026-09-25: Dòng 233 chưa nghiệm thu tương tác trên browser hoặc đối chiếu dữ liệu thật; đã xác minh `SalesInvoiceSettlementTest` 8/8, PHP lint và frontend production build.
- 2026-09-25: Dòng 292 mới được xác nhận bằng frontend production build; chưa kiểm tra tương tác Payment Modal trực tiếp trên browser.
- 2026-09-25: Chưa nghiệm thu browser cho Booking No Post, No Post từng phòng và Master post một phần; cần xác nhận UI hiển thị danh sách phòng bị bỏ qua.
- 2026-09-25: Dữ liệu phòng No Post cũ không được chuẩn hóa theo yêu cầu. Cờ phòng từng bị cascade từ Master vẫn được hiểu là cờ riêng của phòng và có thể tiếp tục chặn phòng đó khi Master đã tắt.
- 2026-09-25: Bốn test còn lỗi trong `BookingRoomServiceFolioTest` liên quan lưu `booking_room_services` cho FO/housekeeping; cần điều tra riêng. Chưa nghiệm thu browser các thao tác No Post và UI ngày/ca với tài khoản đăng nhập.
- 2026-09-24: Chưa nghiệm thu browser với dữ liệu thật cho kéo thả phòng, chỉnh mô tả inline và tài khoản ngân hàng; hai feature test backend liên quan chạy quá thời gian không trả output trong môi trường hiện tại nên cần chạy lại trên DB test ổn định.
- 2026-09-24: Dữ liệu lịch sử `payments` có thể còn `pack2=DPR, pack4=PY`; hệ thống không tự backfill, cần quyết định riêng nếu muốn sửa dữ liệu lịch sử.
- 2026-09-24: Feature tests Checkout hiện không khởi động được trong môi trường do connection `mysql_data` chưa được khai báo; cần cấu hình DB test rồi chạy lại. Dòng 238 cần rà soát/duyệt riêng các consumer ownership trước khi triển khai.

- 2026-09-23: Dòng 169/170/171 chưa được chạy migration trên 5 connection và chưa smoke-test procedure với dữ liệu thật; cần xác minh field thực tế của `service_bills`, `room_night_bills`, `payments` và branch permissions trước UAT.
- 2026-09-23: `ReportDefinitionController.php`, `ReportLookupController.php` và `ReportsPage.vue` là file dùng chung đã thay đổi để hỗ trợ Dòng 170; cần regression test các báo cáo multi-branch/các control tham số hiện có.
- 2026-09-23: Luồng JSON→HTML đã được kiểm tra bằng PHP lint, load definition và migration `2026_09_23_170000`/`180000` trên các database branch; chưa nghiệm thu trực quan Designer/preview/export với dữ liệu thật.
- 2026-09-23: Smoke-test chạy ngày 2026-09-23 trên cả 5 connection chưa có dòng dữ liệu cho các báo cáo doanh thu/công suất; cần đối chiếu preview/export với dữ liệu legacy thật sau khi có dữ liệu test. Dòng 160 có 21 dòng hệ thống và đã trả đúng field contract.
- 2026-09-22: Chưa nghiệm thu browser preview/in/PDF/Word/Excel với dữ liệu thật cho các row 150/151/158/164; smoke-test procedure hiện trả 0 dòng theo ngày chạy kiểm tra. Cần đối chiếu số liệu với hệ thống cũ trước khi chốt nghiệp vụ.
- 2026-09-22: Row 158 City Ledger dùng `payments` phương thức `AC` và tổng đối soát từ `payment_debt_settlements`; cần đối chiếu số liệu legacy thực tế. Card ID đang lấy `bookings.card_no` và chỉ che số, vì schema mới không có `payments.card_number`.



- 2026-09-23: Scope Booking Sections 3/8/13/14/15/16/18/19 chưa có runtime PHPUnit/browser UAT trên dữ liệu thật. Focused PHPUnit hiện bị chặn bởi connection `mysql_data`; frontend build có thể bị chặn trước Vite bởi native Tailwind oxide/Windows `spawn EPERM`. Cần kiểm tra thêm matrix quyền từng role, AV/over-room khi header edit không gửi room_allocations, realtime rollback và General Search sau hủy riêng phòng.



- 2026-09-23: Booking Sections 4/6/7/9/11/12/17/20 chưa UAT trên DB/browser thật. Cần xác nhận `IsRoomNightRoomDayUse` = 0/1, late check-in không bị coi day-use, chuỗi move 1209→1208→1209, RM service count sau bulk rate-only, nightly increase/decrease và undo destination move. PHPUnit không khởi chạy vì thiếu connection `mysql_data`; frontend build có thể bị Windows native Tailwind oxide `spawn EPERM`.



- 2026-09-23: Booking Sections 1/2/10 chưa UAT trên database/browser thật; test `BookingSectionsOneTwoTenTest` dừng trước assertion do connection `mysql_data` chưa cấu hình. Cần kiểm tra mô tả cọc tùy biến, cutoff ngày xác nhận, toast gộp nhiều phòng và Quick Update/deposit transfer.

- 2026-09-23: Payment quick update đã theo contract Checkout cho đích chuyển cọc (booking/phòng status 0/1), nhưng commit legacy `37917d9f` từng giới hạn dropdown phòng ở status 1; cần chốt khi UAT để tránh thay đổi phạm vi đích ngoài nghiệp vụ khách sạn.

- 2026-09-23: Booking room lifecycle regression tests cannot execute in the current environment because Laravel resolves the configured `mysql_data` connection, which is not available. Browser/UAT should verify day-use posting (`IsRoomNightRoomDayUse`) and manual child-breakfast amounts after date shifts on a configured branch database.

- 2026-09-23: Frontend production build is blocked before Vite compilation because `@tailwindcss/oxide-win32-x64-msvc` is missing and Windows reports `spawn EPERM`; no dependency files were changed.


- 2026-09-21: Row 152 migration `2026_09_21_180000` chưa được chạy theo workspace; nếu đã áp dụng ở môi trường đích thì cần migration tiến để đồng bộ template summary.

- 2026-09-21: Row 164 mở route `/reports` cần quyền `mgmt.report.view`, sau khi mở tab cần bấm **Hiển thị báo cáo**; `p_date` lấy `$today` của ReportsPage. Cần nghiệm thu trên tài khoản Night Audit thực tế.

- 2026-09-21: Chưa nghiệm thu trực quan preview, in, PDF/Word/Excel của `DEPOSITS_SALE` trên browser sau migration `2026_09_21_160000`; procedure và cấu hình Design/HTML đã được xác nhận trong database.

- 2026-09-18: Chưa có browser E2E với dữ liệu thật cho hai báo cáo 155/156; cần nghiệm thu trực quan preview, in và PDF/Word/Excel. Procedure 155 vẫn cần đối chiếu output BILL/PAYMENT với dữ liệu legacy thật trước khi chốt số liệu nghiệp vụ.

- 2026-09-17: Chưa có browser E2E cho selection Designer; cần kiểm tra thủ công chọn block → ô detail/static → block khác và mở lại template để xác nhận toolbar/panel không giữ target cũ và style legacy được bảo toàn.

- 2026-09-17: Chưa có browser E2E tự động cho thao tác mở → kiểm tra → lưu Designer; cần nghiệm thu thủ công report `ROOM_SPECIAL_REQUESTS` để xác nhận 9 cột và 2 hàng chi tiết lặp theo từng dòng sau khi nạp template đã lưu.

- 2026-09-17: `PaidCompanyDebtsReportTest::test_dataset_enricher_adapts_paid_company_debts` chưa chạy độc lập được trong test environment hiện tại vì SQLite in-memory thiếu bảng `users`; không liên quan procedure hoặc migration vừa sửa.

- 2026-09-16: Cần nghiệm thu trực quan ba mẫu báo cáo theo ảnh legacy trên dữ liệu thật của từng khách sạn, gồm mở/lưu không chỉnh sửa mẫu DTX để xác nhận giữ layout. Dữ liệu kiểm thử hiện xác nhận output procedure, binding và metadata Designer.

- 2026-09-15: Excel không hỗ trợ `letter-spacing`, border-radius, shadow, flex/grid tự do hoặc ảnh remote như trình duyệt; các template dùng các thuộc tính này cần nghiệm thu trực quan sau export.





- 2026-09-14: Designer v2 chưa tương đương toàn bộ DevExpress; còn thiếu canvas tọa độ/ruler/snapline, band động, expression/calculated fields, chart/barcode/subreport và phân trang thiết kế. Chưa chạy browser E2E cho phím tắt và Explorer.



- 2026-09-14: Cần kiểm tra thủ công trên trình duyệt với bảng lớn, đặc biệt vùng có `rowspan`/`colspan` sau thao tác thêm/xóa hàng hoặc cột; Designer chưa có engine dữ liệu/query và các control chuyên sâu của DevExpress.



- 2026-09-14: Chưa kiểm tra thao tác định dạng `static-table`/`table` trực tiếp trên trình duyệt và chưa có component test cho chu trình mở → chỉnh → lưu → mở lại; utility test, backend template test và build được dùng để kiểm tra hồi quy.

- 2026-09-11: Báo cáo Free trên ảnh legacy có cột `HTT`, nhưng chưa có mapping dữ liệu xác minh; template hiện giữ cột `Ca` theo contract hiện tại. Cần đối chiếu output legacy trước nghiệm thu.

- 2026-09-11: Báo cáo theo sản phẩm suy ra `StandardRate` từ `Rate` và `DiscountAmount`; cần đối chiếu trực tiếp với `vw_040` khi có database dữ liệu thật.

- 2026-09-10: Mapping ba cột tiền HK (`BillOriginalAmount`, `BillDiscountAmount`, `BillTotalAmount/BillAmount`) và công thức công nợ còn lại cần đối chiếu output legacy cùng dữ liệu; product summary có thể thiếu nếu dataset vượt giới hạn 5.000 dòng.

- 2026-09-10: COMPANY_DEBT phụ thuộc import `sales_invoices` và legacy keys; chưa chạy migration nên report chưa xuất hiện trong runtime database. `ReCreditLimit` được giữ riêng, không tự coi là `max_debt`.



## Change History



- 2026-09-09: `ROOM_STATUS_HISTORY` đổi bộ lọc `Số phòng` từ ô text thành dropdown; thêm lookup dùng chung `rooms`, cho phép `options_source=rooms`, và migration cập nhật metadata report đã tồn tại. `ReportsPage.vue` dùng renderer select động nên không cần sửa giao diện dùng chung.



- 2026-09-08: Hoàn thiện báo cáo `INHOUSE_GUESTS` theo `sp_285` và nhánh `RM/IsRoomNight` của `func_054`: quá khứ dùng room-night đã post, hiện tại/tương lai sinh cho phòng đang ở, giữ bill phòng ảo đã post và xử lý checkout đúng ngày hệ thống từ room-night ngày trước. Thêm migration/template/test độc lập, không sửa schema hoặc luồng nghiệp vụ dùng chung; test đạt 6/6 với 52 assertions và migration đã chạy HKT1-HKT4. HKT1-HKT4 hiện chưa có `booking_rooms` để đối chiếu acceptance bằng dữ liệu thực.



- 2026-09-08: Khi chạy `migrate:all` cho `INHOUSE_GUESTS`, công cụ đồng thời áp dụng ba migration `ROOM_STATUS_HISTORY` đang chờ (`140000`–`160000`) trên `pms_data` và `pms_db`; HKT1-HKT4 chỉ nhận migration báo cáo mới.



- 2026-09-08: Đồng bộ giao diện `ROOM_STATUS_HISTORY` với ảnh legacy trong workbook: A4 portrait, 5 cột đúng thứ tự, bỏ STT/dòng tổng, thời gian `dd-mm-yyyy HH:mm:ss` dùng ngày nghiệp vụ + giờ thao tác. Template/procedure đã cập nhật HKT1-HKT4; HKT1 trả 4 dòng đúng layout dữ liệu.



- 2026-09-08: `ROOM_STATUS_HISTORY` tách `business_date` khỏi `changed_at`: thao tác runtime lưu ngày nghiệp vụ từ `system_date_rolls`, còn `changed_at` giữ thời gian thực tế. Procedure lọc theo ngày nghiệp vụ; migration đã chạy HKT1-HKT4 và HKT1 trả đúng 4 dòng audit hiện có.



- 2026-09-08: Hoàn thiện báo cáo `ROOM_STATUS_HISTORY` theo legacy `sp_288`: bảng audit lưu `status_from_id`/`status_to_id`, procedure join `room_statuses`, command import giữ mã legacy chưa map, và `RoomController` ghi audit qua service riêng cho thao tác đơn/hàng loạt. Lỗi audit không chặn nghiệp vụ; test đạt 4/4 với 16 assertions và kiểm tra MySQL transaction/procedure đạt.





- 2026-09-05: Report Viewer tự đồng bộ template sau khi Designer lưu phiên bản/rollback, tải lại metadata trang và render lại dataset đang mở. API summary bổ sung bốn lề, tham số preview và `updated_at`; migration `EXTRA_BED` không còn ghi đè layout đã tồn tại. Test `ExtraBedReportTest` đạt 9/9, 69 assertions; frontend build đạt.



- 2026-09-03: Form Design tách định dạng số của cột bảng khỏi binding: cột giữ field chuẩn và `format=number`, compiler sinh modifier `|number`; mẫu cũ có modifier trong binding được tự chuẩn hóa. `ROOM_MOVES` dùng cấu hình này cho Rate/Rate1 và migration `2026_09_03_250000_sync_room_moves_column_formats` đồng bộ template.



- 2026-09-03: Chuẩn hóa `ROOM_MOVES` theo `sp_129/vw_017`: lọc ngày từ guest phòng đích, chọn `MIN(guest_id)`, giữ `BookingId1` không prefix, không loại lịch sử soft-delete, định dạng ngày/giá, đồng bộ STT với sort và đổi người thực hiện thành dropdown user hệ thống. Renderer bảng thường hỗ trợ modifier `|number`; migration `2026_09_03_240000_fix_room_moves_legacy_accuracy` cập nhật procedure/template.



- 2026-09-03: Sửa giá trị “Tất cả chi nhánh” của `CANCELLED_ROOMS` từ chuỗi rỗng sang `__all__`; tránh middleware `ConvertEmptyStringsToNull` biến lựa chọn thành chi nhánh hiện tại. Khi tổng hợp, controller truyền `__current__` cho procedure từng database và giữ `__all__` trong dataset trả về.



- 2026-09-03: Sửa `CANCELLED_ROOMS` khi chọn “Tất cả chi nhánh”: chỉ tổng hợp chi nhánh PMS trong `database_domains.branch_connections`, tránh database ngoài cấu hình làm hỏng request; frontend hiển thị đúng lựa chọn rỗng có nhãn và xóa kết quả cũ khi execute lỗi. Xác minh `SP1100.ShortName` → `room_classes.code`; migration `2026_09_03_220000_fix_cancelled_rooms_all_branches` cập nhật procedure.



- 2026-09-03: Form Design và `TemplateRendererService` hỗ trợ Group Builder động không giới hạn cứng số cấp: thêm/xóa/đổi thứ tự, chọn field từ `field_schema`, nhãn, điều kiện boolean/tinyint và ASC/DESC cho từng cấp. Renderer xử lý đệ quy; HTML cũ `groupBy/subgroupBy` vẫn tương thích. `CANCELLED_ROOMS` đã nâng sang cấu trúc `groups` và migration `2026_09_03_210000_upgrade_cancelled_rooms_group_builder` đã chạy HKT1-HKT4.



- 2026-09-03: Thiết kế lại cấu hình grouping của Form Design: dùng thẻ `Nhóm dữ liệu` với công tắc, trường nhóm, điều kiện bật nhóm và nhãn nội dung thuần; ẩn thẻ `<td>` kỹ thuật, tự tính colspan, sửa danh sách nguồn `rows`, đồng thời mô phỏng dòng nhóm ngay trên canvas. Các bảng không bật nhóm giữ nguyên.



- 2026-09-03: Canvas Form Design hiển thị trực tiếp nhãn `Nhóm theo` và điều kiện `Khi` trên block bảng. Panel thuộc tính bảng cho phép cấu hình trường nhóm, parameter bật nhóm và HTML dòng nhóm; toàn bộ đầu ra grouping được lưu trong mẫu.



- 2026-09-03: Form Design của block bảng đã hỗ trợ cấu hình `Chỉ nhóm khi tham số` từ các parameter boolean/tinyint của data source và compile thành `data-group-enabled-by`. `CANCELLED_ROOMS` lưu `groupEnabledBy=parameters.p_group_by_reason`; tránh mất điều kiện grouping khi chỉnh và lưu lại mẫu. Template không cấu hình giữ nguyên hành vi.



- 2026-09-03: Sửa runtime `CANCELLED_ROOMS` trên HKT1 còn dùng template grouping cũ dù checkbox tắt. Migration `2026_09_03_200000_sync_cancelled_rooms_group_switch` đã đồng bộ `data-group-enabled-by="parameters.p_group_by_reason"` trên HKT1-HKT4; kiểm tra runtime cả bốn database đều đạt.



- 2026-09-03: Hoàn thiện `CANCELLED_ROOMS`: ẩn Booking khỏi sidebar nhưng giữ tham số procedure; không loại lịch sử soft delete; đổi username người hủy sang họ tên từ `mysql_system` có fallback; “Tất cả chi nhánh” tổng hợp các branch được phép; grouping chỉ chạy khi bật. Migration `2026_09_03_190000_fix_cancelled_rooms_report_accuracy` đã chạy trên HKT1-HKT4. Renderer dùng chung chỉ thay đổi khi template khai báo `data-group-enabled-by`; báo cáo khác không bị đổi hành vi.



- 2026-08-28: Sửa riêng template `CANCELLED_BOOKINGS` để trạng thái tắt `p_show_room_info=false` hiển thị bảng tổng hợp. Renderer chuyển boolean `false` thành chuỗi rỗng nên template bổ sung selector `.summary-visible-`; migration `2026_08_28_192000_fix_cancelled_bookings_summary_visibility` chưa chạy theo yêu cầu người dùng. Không sửa renderer dùng chung. Test báo cáo đạt 4/4, 35 assertions.



- 2026-08-28: Bổ sung migration `2026_08_28_191000_update_cancelled_bookings_room_details`; khi bật “Hiển thị thông tin phòng”, `CANCELLED_BOOKINGS` lấy toàn bộ `booking_rooms` thuộc booking có log hủy booking, thay vì phụ thuộc log hủy riêng từng phòng. `Tổng phòng` dùng cùng nguồn; vẫn loại `rooms.is_internal`. Migration chưa chạy theo yêu cầu người dùng. Test báo cáo đạt 3/3, 30 assertions.



- 2026-08-28: Chuẩn hóa thời điểm hủy booking/phòng: `booking_cancel_logs.cancelled_at` dùng ngày nghiệp vụ mới nhất từ `system_date_rolls` kết hợp giờ thao tác thực tế. Hai luồng hủy giữ nguyên trạng thái và cascade; dữ liệu lịch sử không được tự động sửa. Test hủy riêng phòng đạt 1/1 (7 assertions), hủy toàn booking đạt 1/1 (4 assertions), báo cáo hủy đạt 2/2 (24 assertions).

- Known risk cập nhật: các log hủy đã tạo trước thay đổi vẫn giữ ngày máy chủ; cần quyết định backfill riêng vì không thể suy đoán ngày nghiệp vụ lịch sử. Việc lấy họ tên User cross-database và mapping import SP8052/SP8053 vẫn chưa hoàn tất.



- 2026-08-28: Dòng 107 triển khai độc lập báo cáo `CANCELLED_BOOKINGS` theo legacy `sp_284`, `sp_261`, `vw_034`; có chế độ tổng hợp booking và chi tiết phòng, loại `rooms.is_internal`, trả riêng `Adult/Baby/Child`. Không sửa luồng hủy/khôi phục, report engine hoặc schema nghiệp vụ.

- 2026-08-28: Migration `2026_08_28_190000_create_cancelled_bookings_report` đã chạy HKT1-HKT4. Kiểm tra HKT1 có 1 log hủy booking và 8 log hủy phòng; procedure trả đúng 1 dòng tổng hợp và 8 dòng chi tiết trong phạm vi ngày chứa `cancelled_at`. Test đạt 2/2, 24 assertions; frontend build đạt.



- 2026-08-28: Dòng 116 triển khai độc lập `DUE_OUT_ROOMS` theo legacy `sp_008`, dùng procedure `rpt_due_out_rooms` và template A4 ngang `DUE_OUT_ROOMS_STANDARD`; tổng theo ngày đi và toàn giai đoạn. Migration đã chạy HKT1-HKT4; HKT1 tháng 08/2026 trả 10 dòng/22 field và render không còn placeholder. Không sửa `DEPARTING_ROOMS`, schema hoặc mapping import.



- 2026-08-28: Trình thiết kế mẫu báo cáo đồng bộ thay đổi cỡ chữ vào `content_html` ngay khi kéo thanh điều chỉnh; cỡ chữ của từng block được ưu tiên có phạm vi ở canvas và báo cáo, tránh CSS riêng của mẫu ghi đè. Không đổi API, database hoặc logic dữ liệu báo cáo.



- 2026-08-27: Triển khai độc lập báo cáo `DEPARTING_ROOMS` theo legacy `sp_008` bằng migration `2026_08_27_130000_create_departing_rooms_report`, procedure `rpt_departing_rooms` và template `DEPARTING_ROOMS_STANDARD`; không sửa API/controller/frontend dùng chung, không đổi schema bảng hoặc mapping import.

- 2026-08-27: Báo cáo phòng đi hỗ trợ ngày đi, khu vực, công ty, đăng ký, loại phòng, tình trạng đăng ký, khách chính, Room Rate và Services Amount; loại phòng nội bộ/số phòng bắt đầu `0`, no-show, hủy và lịch sử chuyển phòng. Khi tắt Room Rate, Services Amount cũng bị tắt.

- 2026-08-27: Migration báo cáo phòng đi đã chạy trên HKT1-HKT4. Runtime HKT1 trả 2 dòng khách chính/4 dòng toàn bộ khách; HKT1-HKT4 đều trả schema 37 field và render HTML không còn placeholder. Test riêng đạt 2/2, frontend 23/23 và build đạt; full backend 88/134, giữ nguyên 46 lỗi nền trước triển khai.



- 2026-08-25: Row 112: in-house bookings such as GAL1 may add new rooms; existing checked-in, checked-out, cancelled, or transferred rooms and related data are preserved. No schema/API change.



- 2026-08-24: Đổi metric Availability từ `SOFAB` sang `BBC` ở API, Room AV và chi tiết; dữ liệu nguồn vẫn lấy từ `special_requests.code=babycot` và liên kết `booking_room_special_requests`.



- 2026-08-24: SpecialRequestsModal tải yêu cầu đã gắn trực tiếp từ `/booking-rooms/{roomId}/special-requests` khi phòng đã lưu; dữ liệu props chỉ là fallback cho phòng mới. Khắc phục hiển thị sai trong Thông tin đặt phòng.



- 2026-08-24: Booking Detail truyền chuẩn hóa `specialRequests` vào modal yêu cầu đặc biệt và phát sự kiện refresh sau khi lưu; khắc phục form không nạp các yêu cầu đã gắn.



- 2026-08-24: Dòng 124: chuẩn hóa mã `babycot`; Availability API đếm mỗi booking room có Special Request `babycot` một lần theo ngày, trả qua metric nội bộ `SOFAB`; Room AV hiển thị nhãn `BBC`. Không thay đổi schema.



- 2026-08-24: Special Request dòng 23 đã chuẩn hóa còn 3 mã hoạt động `honey_moon`, `birthday`, `baby_cot`; các mã ngoài nghiệp vụ được đặt `is_active=false` để giữ lịch sử. Modal lồng Quick Assign được bổ sung `pointer-events-auto`; API catalog chỉ trả danh mục active.



- 2026-08-24: Khắc phục danh mục Yêu cầu đặc biệt không hiển thị trong form: thêm `SpecialRequestSeeder` vào `BranchDatabaseSeeder` để dữ liệu `special_requests` được seed trên database chi nhánh; không thay đổi schema/API.



- 2026-08-24: Dòng 112 — Tạo đăng ký khóa chỉnh sửa inline khi booking có phòng đã ở/đã trả/hủy/chuyển (`booking_rooms.status=1,2,3,100`); dùng trạng thái phòng làm nguồn khóa thực tế, frontend khóa nút Sửa và backend từ chối cập nhật `room_allocations`. Không thay đổi schema.



- 2026-08-21: Hoàn thiện nền tảng multi-database: tài khoản/quyền/chi nhánh chạy trên `pms_system`, dữ liệu nghiệp vụ chạy theo `pms_hkt1`–`pms_hkt4`; thêm manifest ownership, kiểm tra quyền chi nhánh, migration/seed theo domain và test isolation bằng SQLite.

- 2026-08-21: Booking lưu `created_by_user_id`/`updated_by_user_id` cùng username snapshot, không tạo FK xuyên database. Không thay đổi payload API hoặc UX/UI. Chưa xóa bảng vật lý trùng từ kiến trúc cũ; cleanup cần backup và phê duyệt riêng.

- 2026-08-21: Dòng workbook 137 được chuẩn hóa bằng `RegistrationStatusMapper`: mã legacy `SP1311.BookingStatusId` được tra sang `registration_statuses.id`; frontend Room Plan/Quick Assign không còn fallback cứng về ID `1`. Chưa có importer production trực tiếp từ `SP2000.Status` vì nguồn legacy runtime chưa được cấu hình.



- 2026-08-21: Đã tách liên kết User dùng chung khỏi database chi nhánh: migration `2026_08_21_120000_decouple_system_user_foreign_keys` và `2026_08_21_120001_restore_system_user_foreign_keys` đã chạy trên `pms_system` và HKT1-HKT4; `CompanyController` và `InventoryCheckController` validate User trên `mysql_system`. Logic nghiệp vụ/backend/frontend giữ nguyên, chỉ loại bỏ FK nội bộ không hợp lệ; FK quyền tài khoản đúng trong `pms_system` được giữ lại.

- Known risk: các cột User ID ở bảng nghiệp vụ chi nhánh là tham chiếu logic; khi xóa User dùng chung cần kiểm tra dữ liệu audit/nghiệp vụ liên quan vì database không còn FK bảo vệ.



- 2026-08-21: Added `tests/Feature/Booking/BookingStatusMappingTest.php` for workbook row 137. It verifies new `registration_statuses.id` storage, legacy `booking_status_id` resolution, separation from operational `bookings.status`, and resource output. Result: 4 tests, 11 assertions passed.



**Last reviewed:** 2026-08-19



- 2026-09-03: Form Design hỗ trợ `customRows` trong Detail Table: thêm nhiều hàng trực tiếp trên canvas; mỗi hàng có nhiều ô với văn bản/binding/Count/Sum/Distinct Count, trường tổng hợp, colspan, căn lề, định dạng số và điều kiện hiển thị. `TemplateRendererService` chỉ tính aggregate cho các placeholder mới; mẫu cũ không khai báo giữ nguyên. `NO_SHOW` được nâng từ footer cứng sang hàng tổng cấu hình bằng Count.



- 2026-09-03: Dòng 117 triển khai báo cáo `NO_SHOW` theo `sp_054`/`sp_054_Division`: thêm procedure `rpt_no_show`, sidebar giữ tham số legacy All/Charge/No Charge, người dùng, sort và chi nhánh; template `NO_SHOW_STANDARD` cấu hình nhóm Charge/No Charge và cột theo Form Design. Gom tất cả chi nhánh chỉ chạy riêng cho `NO_SHOW`/`CANCELLED_ROOMS`, không tác động nghiệp vụ no-show hoặc báo cáo khác. Mapping service bill RM cần đối chiếu thêm nếu import legacy có cách ghi bill khác.



- 2026-08-28: Thêm báo cáo `DUE_IN_ROOMS` theo legacy `sp_006` bằng procedure/template riêng; lọc ngày đến, loại phòng, tình trạng đăng ký, khu vực, công ty, đăng ký và khách chính. Không thay đổi schema hoặc luồng booking dùng chung.



- 2026-08-20: Create Registration chuẩn hóa dạng phòng khi tra Rate Code; phòng FAM dùng `Family` thay vì dùng nhầm `FAM`, áp dụng cho dòng phân bổ, phòng mới và phòng chỉnh sửa.

- 2026-08-20: Create Registration tải lại Rate Code trước khi mở/áp mã giá, tránh dùng `Period` cũ sau khi giá vừa được cập nhật ở Rate Setup.

- 2026-08-20: Create Registration chống cache khi đổi Rate Code và tra trực tiếp bản ghi vừa tải theo loại/dạng phòng/ngày đến; dữ liệu runtime `test + FAM + Family + 2026-08-09` được xác minh trả giá `1`.

- 2026-08-20: Select Rate Code tại Create Registration truyền trực tiếp giá trị sự kiện để tránh tính giá bằng mã cũ/rỗng trước khi `v-model` hoàn tất cập nhật.

- 2026-08-20: Sửa khởi tạo dạng phòng tại Create Registration: fallback rỗng không còn khớp nhầm Room Form đầu tiên `Double`; kiểm tra runtime xác nhận `test + FAM + Family + 2026-08-09` hiển thị và giữ giá `1`.

- 2026-08-20: Booking create/update tự tính lại Rate Code từ database: non-daily dùng `DEFAULT`, daily dùng mapping từng ngày; lưu đồng bộ `booking_rooms.rate/base_price/rate_code` và RM từng ngày, không tin giá frontend gửi.

- 2026-08-20: Room Map/Booking Detail truyền trực tiếp Rate Code mới khi đổi select; GuestController phân biệt `IsDaily` khi cập nhật phòng, tránh dùng daily mapping cho mã giá không theo ngày.

- 2026-08-20: Room Map/Quick Assign/Booking Detail khi chọn lại placeholder giá phòng sẽ xóa Rate Code và khôi phục giá chuẩn theo loại phòng + dạng phòng.

- 2026-08-20: Rate Setup lưu ngay mapping từng ngày hoặc khoảng ngày bằng chế độ merge, bảo toàn ngày không chỉnh sửa và từ chối Code không thuộc Rate Code; không thay đổi schema.

- 2026-08-20: Room Map cập nhật Rate Code/giá cho cả nhánh người lớn và trẻ em, nhận giá `0`, fallback giá chuẩn đúng dạng phòng khi bỏ Rate Code và tải lại dữ liệu modal sau khi lưu.

- 2026-08-20: Dòng 134 — Booking Detail lưu `rate_code` khi chỉnh sửa Thông tin phòng; backend đồng bộ giá RM theo từng ngày từ ngày hệ thống trở đi, bảo toàn dòng RM quá khứ/đã post và fallback giá chuẩn khi bỏ Rate code.

- 2026-08-20: Room Map API bổ sung `rate_code` vào `RoomResource`; Booking Detail không còn dùng placeholder `RACK...`, hiển thị đúng Rate code đã lưu.

- 2026-08-20: Dòng 135 — Rate code chỉ nhận giá khớp đúng mã loại phòng và dạng phòng; thiếu cấu hình đúng dạng phòng trả giá `0`, không fallback sang occupancy khác.



- 2026-08-19: F129 OCC/AV: Room Plan now subtracts unassigned booking rooms from AV while counting them in OCC when `registration_status.is_availability = 1`; `is_availability = 0` remains excluded.

- 2026-08-19: F129 day-use: OCC/AV now includes booking rooms with `arrival_date = departure_date` on that date, using `booking_rooms.is_day_use` or booking-level `is_day_use`; Availability API verified 09/08 with OCC = 9 for GAL1/GAL2/GAL4.

- 2026-08-19: F129 room stats: `/rooms/stats` now excludes booking rooms whose registration status has `is_availability = 0` from current/projected occupancy and arrival/departure counters; physical room status counts remain unchanged.

- 2026-08-19: F129 Room Plan UI: resize giữ highlight cố định theo giai đoạn booking ban đầu; vùng auto-scroll phía trên khi kéo booking được mở rộng thêm 80px.

- 2026-08-19: Phần UI F129 về highlight resize, auto-scroll mở rộng và màu vàng nhạt đã được hoàn tác theo yêu cầu; các fix OCC/AV và day-use vẫn giữ nguyên.

- 2026-08-19: Room Plan mở rộng vùng kích hoạt auto-scroll phía trên thêm 120px khi kéo thanh booking; không thay đổi highlight hoặc màu bảng.

- 2026-08-19: Room Plan mở rộng overlay bắt sự kiện kéo theo cùng vùng auto-scroll phía trên (`headerBottom + 122px`), tránh dừng cuộn khi con trỏ đi qua thanh header.



- `HotelDefinitionSeeder` now fills missing service percentages with `5/8/0` (`service_charge`/`tax`/`special_tax`) and seeds every service as active; explicitly declared percentages remain unchanged.

- Correction: Room Map giữ nguyên sơ đồ lưới; các bộ lọc theo thông tin đăng ký, chọn nhiều phòng, đổi trạng thái hàng loạt và in Worksheet áp dụng ở chế độ danh sách.

- 2026-08-19: Room Map dạng lưới đã chuẩn hóa sắp xếp tầng/phòng, bổ sung lọc theo thông tin đăng ký, chọn nhiều phòng, đổi trạng thái hàng loạt và in Worksheet không kèm nhân viên.

- 2026-08-19: Bổ sung `user_id` nullable cho các bảng payment/post bill; model tự ghi `Auth::id()` khi tạo, API eager-load user và vẫn giữ username legacy để fallback/import.



- 2026-08-19: Dòng 119 của workbook được hoàn thiện ở runtime: `hotel_services.is_active` bật/tắt dịch vụ trong danh sách post bill; `fo-list` chỉ trả dịch vụ đang bật và được gán department `FO`. Quan hệ nhiều department qua `department_hotel_service` và bill lịch sử không bị thay đổi.



- Checkout registration filters now distinguish active Reservation/Inhouse rooms from checked-out history; date controls default off and apply only after confirmation.

- Front Desk service posting now preserves FIT/GIT ownership: FIT remains on the room folio, GIT moves the current owner to Booking Master while retaining source room/guest fields.

- Extra Bed quantity/rate synchronization preserves the configured FIT/GIT flag; manual Front Desk service posting defaults to FIT.

- Checkout room panels now classify posted bills by current owner fields before legacy source/description fallbacks, preventing Master-owned RM from reappearing under source rooms.

- Child-breakfast service synchronization preserves each daily FIT/GIT setting instead of forcing all extra-charge rows to Master.

- Checkout service rows are ordered by business date and posting time; generated child-breakfast bills preserve the child-specific description.

- Quick Transfer groups room and Master candidates consistently by `ServiceBill.ServiceId`; group headers use configured FO service descriptions instead of bill-specific text.

- Master Room Rate is prospective only: the current flag selects the owner of newly generated room-charge bills; existing bills remain classified by their stored current-owner fields.

- Checkout service rows use `Tax` and `ServiceCharge` from the linked posted bill, falling back to booking setup values only before a bill exists.

- New service bills snapshot all three configured tax rates: hotel services use `hotel_services`; housekeeping details use each product profile and mixed-rate headers remain `0` instead of inventing a combined percentage.

- Hotel Service configuration exposes CRUD for `service_charge` and `include_service_charge`; values are no longer forced to zero by the frontend.

- Checkout housekeeping rows resolve their description from `hotel_services.name` through the posted bill `ServiceId`; product names stay in bill details, while housekeeping notes appear only in the invoice popup and remain available for bill adjustment.

- Housekeeping outlets have a dedicated `show_in_add_service` flag; it filters Add Service tabs only, while Create Menu continues to show every active outlet.

- Housekeeping outlet configuration exposes direct switches for `is_active` and `show_in_add_service` with immediate API persistence.

- Fresh-schema migrations now define service-detail quantity, service operator employee codes and housekeeping outlet visibility in their original create-table migrations; the three redundant add-column migrations were removed.

- Housekeeping outlets store three runtime-only default tax/charge percentages used to initialize newly created menu products; editing existing products keeps their persisted rates.

**Source of truth:** current repository contents; PHP 8.4.22 is available for Laravel migration commands.



- 2026-08-18: Room Map danh sách “Đang ở” và “Đã đi” dùng chung giao diện với “Đã đến”; “Đã đi” tách phòng chưa trả và phòng đã trả theo trạng thái/ngày đi.







- 2026-08-21: Dòng 137 workbook — chuẩn hóa mapping trạng thái Booking: `bookings.registration_status_id` lưu `registration_statuses.id`; `registration_statuses.booking_status_id` giữ mã legacy SP1311; `bookings.status` chỉ giữ trạng thái vận hành. Resource không còn fallback `booking_status_id` sang `id`. Runtime kiểm tra 8 trạng thái và 0 booking tham chiếu FK không hợp lệ.



## Dòng 138 — Booking room persistence



- 2026-08-25: Chuẩn hóa ngày chuyển phòng theo semantics `[arrival, departure)`: phòng cũ kết thúc đúng ngày chuyển, phòng mới giữ ngày checkout ban đầu; cả API chuyển phòng và `moveToRoom()` dùng trạng thái lịch sử `100`.

- 2026-08-25: Khi đổi ngày đi của phòng Reservation/Inhouse, lịch checkout dự kiến của phòng, khách và trẻ em được đồng bộ về ngày mới, giờ `12:00:00`.

- 2026-08-25: Bổ sung lưu `reason` riêng cho phòng cũ, chuẩn hóa `note` mô tả chuyển phòng, đồng bộ guest và pivot trẻ em trong `moveToRoom`; không thay đổi schema.

- 2026-08-25: Auto test dòng 138 bổ sung trường hợp `moveToRoom` có guest breakfast và trẻ em/chi tiết ăn sáng; `RoomMoveTest` đạt 8/8, 46 assertions.



## Dòng 139 — Booking room guest persistence



- 2026-08-25: Migration audit trẻ em đã chạy riêng trên `mysql_hkt1`–`mysql_hkt4`; không chạy các migration pending không liên quan. Kiểm tra sau migrate: cả bốn DB có schema mới, hiện chưa có dòng `booking_room_children` cần backfill.

- 2026-08-25: Test trọng tâm dòng 138–139 đạt 14/14, 75 assertions; frontend unit 19/19 và production build đạt. Full backend đạt 79/125, 45 lỗi 403 do fixture thiếu quyền và 1 lỗi multi-database có sẵn, không phát hiện lỗi mới trong suite trọng tâm.

- 2026-08-25: Bổ sung ngày/giờ/người check-in, checkout cho `booking_room_children` theo phần đã xác minh của `SP2500`; giữ nguyên ID trẻ và breakfast detail khi chuyển phòng.

- Known risk: `SP2500.Breakfast`, `RentalRoomIdOriginal`, `ChildIdOriginal`, `IsInfant` chưa xác minh mapping import đầy đủ.

- 2026-08-25: Checkout/restore trẻ em đồng bộ pivot phòng; restore đưa lịch checkout dự kiến về `departure_date`, `12:00:00` thay vì để trống.

- 2026-08-25: `BookingRoomGuest::creating` mặc định `actual_checkout_date` theo ngày đi phòng và `actual_checkout_time=12:00:00`; checkout thực tế vẫn ghi đè ngày/giờ thao tác. Không thay đổi schema; `RoomMoveTest` đạt 8/8, 48 assertions.



- 2026-08-25: BookingRoom mặc định `CheckoutDate` theo `departure_date` và `CheckoutTime=12:00:00` cho Reservation mới; checkout/chuyển phòng ghi thời điểm thực tế. Khi chuyển phòng, guest cũ được bổ sung giờ đến nếu thiếu và guest phòng mới kế thừa `breakfast`. Test `RoomMoveTest` đạt 7/7. Chưa thay đổi schema; `CheckoutDate`/`CheckoutTime` được giữ để tương thích legacy.



## Room Plan registration status



- 2026-08-24: Dòng 149: Room Plan tách tính tiền Booking theo ngày hệ thống; quá khứ lấy bill hợp lệ đã phát sinh và loại dịch vụ post tay không thuộc setup, hiện tại/tương lai lấy dữ liệu setup chưa post cùng RM/EB/phụ thu trẻ em. Không đổi schema/API; frontend build đạt.



- 2026-08-21: Room Plan quick booking now loads and requires a visible registration status selection from `/api/bookings/init-dropdowns`; hard-coded status `1` was removed. UX remains scoped to Room Plan.

- 2026-08-21: Room Plan Booking modal is draggable by its blue header and constrained within the viewport; form fields and save logic are unchanged.

- 2026-08-21: Dòng 140 runtime: thêm `GuestStatusSyncService`; `booking_room_guests.status` là nguồn trạng thái theo lần lưu trú, còn `guests.guest_status` được tổng hợp từ toàn bộ phòng/booking của khách. Đã tích hợp check-in, undo check-in, hủy phòng/booking, no-show và khôi phục; backend test 121/121 đạt.



## 1. Project Purpose



This is a replacement PMS that clones the legacy system into a new application.



- Clone legacy features, UX/UI interactions and business logic faithfully unless a change is explicitly approved.

- Support importing legacy-system data after feature parity is complete.

- The project is developed by multiple contributors, each responsible for one or more legacy features.

- Vietnamese is the primary business/UI language, with a partial English switch.



## 2. Runtime Architecture



```text

Browser

  -> Vue 3 SPA (`frontend/`)

  -> Axios client at `/api` with Sanctum bearer token

  -> Vite development proxy / Laravel public build output

  -> Laravel API (`backend/`)

  -> Eloquent models, migrations, seeders

  -> MySQL / MariaDB (bắt buộc kết nối CSDL MySQL cho toàn bộ dự án)

```



| Area | Location | Notes |

|---|---|---|

| SPA entrypoint | `frontend/src/main.js` | Vue, Pinia, router, global stylesheet |

| Routes/auth guard | `frontend/src/router/index.js` | All routes except Home/Login need `pms_token` |

| HTTP client | `frontend/src/services/http.js` | `/api`, Bearer token, language headers, 401/419 logout |

| API definitions | `backend/routes/api.php` | Public login plus Sanctum-protected routes |

| Booking domain | `backend/app/Http/Controllers/Api/Booking*Controller.php` | Largest business-rule area |

| Availability | `backend/app/Services/RoomAvailabilityService.php` | Reservation/room availability logic |

| Legacy DB reference | `old_database_struct/` | Documentation only; do not assume it is current schema |



## 3. Major Modules



| Module | Frontend | Backend domain | Current scope |

|---|---|---|---|

| Authentication & shell | `LoginPage.vue`, `MainLayout.vue` | `AuthController`, Sanctum | Login/logout, user settings, language/theme, system date/shift |

| Reservation | `pages/reservation/` | Booking, BookingRoom, Guest, Availability | Create/update/cancel/copy/restore booking; rooms, guests, services, requests |

| Front desk | `pages/frontdesk/` | BookingRoom, Payment, Room | Check-in, room movement, checkout/payment UI |

| Housekeeping | `pages/housekeeping/` | Room, inventory, lost & found | Operations, minibar/laundry/service charging screens |

| Configuration | `pages/config/`, `pages/system/` | Rooms, rates, companies, users, templates | Hotel/master-data and administration settings |

| F&B | `pages/fnb/` | Outlet, table, product, order, party, promotion | Restaurant tables/orders, party events, menu/printers/reports |

| Reports | `pages/reports/`, `pages/fnb/ReportPage.vue` | mixed/read models | PMS and F&B reporting UI |



## 4. Key Business Flows



### Reservation lifecycle

1. Create booking header with source/company/status/date validations.

2. Add one or more booking rooms with dates, rates, guest counts and room type/form.

3. Check availability; either block or warn based on overbooking setting.

4. Auto-assign or manually assign a physical room; prevent overlapping stay/lock conflicts.

5. Add guests, children/breakfast details, special requests and pre-set services.

6. Check in, then allow constrained updates, room upgrade/split/move/merge/unassign.

7. Record deposits/payments; support split and transfer operations.

8. Cancel or restore booking with audit history.



### F&B lifecycle

1. Configure outlet, location, table, menu product/category, printer and promotion.

2. Create/synchronise active table orders and items.

3. Transfer a table or selected items when required.

4. Manage party/sub-party orders and payments; print logs provide auditability.



## 5. Important Data and State



- Runtime migrations: `backend/database/migrations/`; current schema includes users, hotel/room masters, bookings, payments, F&B, activity logs and user settings.

- Housekeeping service posting uses new-system tables `service_bills`, `service_bill_details`, `housekeeping_service_bills`, and `housekeeping_service_bill_details`; legacy `SP*` tables are reference only.

- Seeders: `backend/database/seeders/`; use for baseline hotel, booking status, system date, templates and F&B samples.

- Browser state: `pms_token`/`pms_user` in session storage; UI language/theme and selected branch in local storage.

- System business date: latest `system_date_rolls` record, falling back to Asia/Ho_Chi_Minh current date.



### Legacy SP3000 service-bill contract



Reference schema: [`old_database_struct/db_schema/ProVistaDTXHotel/tables/SP3000.md`](../old_database_struct/db_schema/ProVistaDTXHotel/tables/SP3000.md).



- `Ma` is the service-line primary key; `Date` is the service-use/business date selected when posting the bill and may differ from `CreatedDate`/`CreatedHour`, which record when the bill was created using the current system date/time.

- `Guest` stores the room guest name; a booking/master bill stores the booking name. `RefId` stores the manually entered bill reference for HK/FB-related bills.

- Department/outlet mapping: FO = Front Desk/PMS, HK = Housekeeping, FB = Restaurant; outlet examples are RC (front desk), MB (minibar), LA (laundry), BR (broken), or the F&B outlet code.

- `ServiceId`, `DescriptionServive`, `Quantity`, `Amount`, `ServiceCharge`, `SpecialTax`, `Tax`, `Currency`, `Exchange` store the posted service, description, total quantity/amount, charges/taxes and currency. FO keeps posted quantity; HK/FB header quantity is `1` because detail quantities live in their source tables. Currency is VND and exchange is `1` for the current single-currency scope.

- `Edit` is the service-line edit flag (`0` active, `1` deleted). `Folio` identifies the payment folio. `PaymentID` is nullable until payment and corresponds to the payment record. `VATNumber`, `Serial`, `InvoiceNumber`, `VatId` and `InvoiceId` are populated by VAT/sales-invoice flows (`InvoiceId` references the sales invoice line identifier; `VatId` references SP8004).

- Original ownership fields preserve where the bill originated: `RegisterId1` for a booking/master, or `RentalRoomId1` + `CustomerId1` for a room-posted bill; master has no rental room/customer. `CompanyId1` is the original company. Current-location fields `RegisterID2`, `RentalRoomId2`, `CustomerId2`, `CompanyId2` change after moving a bill while the `...1` fields remain unchanged.

- `Status`: `1` unpaid, `2` paid, `3` cancelled, `4` transferred. Cancel/transfer operations create a negative offset line; transfer also creates a new line at the destination and updates the old/source lines according to the legacy adjustment behavior.

- `Pack1` records the negative-line ID for cancellation/transfer; `Pack3` records the FB negative-line ID or is set to `1` during accounting revenue pull. `Pack2` is currently unused. `AdjustmentBillId` points to the old bill for adjustment operations. `MisaRefId` is reserved for MISA integration and currently unused.

- `Year`, `Month`, `Day` denormalize the service date. `CreatedUser` is the bill creator; `Username` is the user who posted it; `Ca` is the posting shift. `Updated*` fields are legacy audit fields.

- The following legacy fields are currently unused and should not drive new behavior: `NotPrint`, `BillExchangeRate`, `BillExchangeAmount`, `DebitAccount`, `CreditAccount`, `RevenueAccount`, `CostAccount`, `ParentBillId`, `OwnerUser`, `RootOwnerUser`, `ExchangeRate1/2`, `TotalAmount0/1/2`, `Currency0/1/2`, `ConvertRate/Amount`, `ConvertRate2/Amount2`, `IsSyncT`.

- Import/clone mapping must preserve service date versus creation timestamp, original versus current ownership, folio/payment/status, outlet, negative adjustment relationships and VAT references; do not infer these from the current room after a transfer.



## 6. Current Status

- 2026-09-10: Hoàn thiện phạm vi tách biệt cho mẫu tham chiếu LA/BR/MB và công nợ: LA dùng `groups` hiện hành, CSS giữ định dạng sau khi Designer biên dịch lại; bộ lọc HK mặc định Mã/ASC theo ảnh; thêm adapter dataset chỉ xác thực hình dạng; công nợ giữ chi tiết giải trừ theo tham số hiển thị. Chưa đăng ký report/API hoặc chạy migration vì còn thiếu contract query đã xác minh.

- 2026-09-09: Đã tạo tách biệt provider trình bày LA/công nợ, bộ lọc HK và test không DB; agent chính hoàn thiện bản nháp sau khi hai Luna chạm giới hạn sử dụng. Chưa đăng ký report/API, chưa sửa code dùng chung, chưa chạy migration; BR/MB chưa có template/query riêng. Chi tiết tại `docs/reports/housekeeping_invoices.md` và `docs/reports/company_debt.md`.

- 2026-09-07: DAY_USE_ROOMS now enforces `registration_statuses.is_availability=1` and honors Room/ArrivalDate ASC/DESC in migration `2026_09_07_160000_create_day_use_rooms_report`.

- 2026-09-07: Removed combined N.Lớn/EB/T.Em output from DAY_USE_ROOMS procedure, source metadata and stored template via 2026_09_07_162000_remove_day_use_combined_guest_column; Adult/Baby/Child remain separate.

- 2026-09-07: Booking create/update/add-room flows now synchronize authoritative `bookings.is_day_use` to `booking_rooms.is_day_use`; existing legacy rows still require resave or approved backfill.

- 2026-09-07: Added one-time `2026_09_07_161000_backfill_day_use_to_booking_rooms` to align existing non-deleted room rows with booking Day Use flags; migration down is intentionally no-op.

- 2026-09-07: Implemented isolated DAY_USE_ROOMS report from legacy sp_132 with detail-level booking_rooms data, separate Adult/Baby/Child columns, and internal-room exclusion via rooms.is_internal. No shared frontend/API/booking/checkout logic changed.

- 2026-09-07: Report preview được phóng cố định 125% riêng trong viewer để chữ và font đậm dễ đọc hơn; template, dữ liệu, in và export giữ nguyên tỷ lệ gốc.

- 2026-09-05: EXTRA_BED was corrected against func_054 and current posting flows: posted bill quantity now prefers linked EB/header data, historical virtual-room bills remain visible, availability filters were removed, future setup starts at PMS system date and is deduplicated, nightly room rate prefers room_night_bills, and the template uses Vietnamese labels with ISO date grouping. Focused tests pass 7/7, 60 assertions; MySQL branch runtime validation is pending approval.

- 2026-09-05: Added isolated EXTRA_BED report migration/procedure and standard landscape template. The report separates unposted booking_room_services EB rows from posted service_bills EB rows, supports date and booking filters, and does not change shared Checkout/Night Audit/frontend logic. Added focused contract tests and report documentation. Runtime validation requires MySQL data.



- 2026-09-04: Bổ sung hiển thị `groupFooter` cho bảng grouping cấu hình trong `TemplateRendererService` và preview Band Designer; subtotal dịch vụ không còn bị bỏ qua khi template dùng `data-group-configured="1"`.

- 2026-09-04: SUPPLEMENTARY_SERVICES duoc dieu chinh de dropdown Dich vu chi chon mot gia tri; migration 2026_09_04_120000_set_supplementary_services_single_service da cap nhat database hien tai.

- 2026-09-04: Hiệu chỉnh `SUPPLEMENTARY_SERVICES`: loại `RM` khỏi lookup, đổi multi-select sang dropdown checkbox, sửa subtotal/định dạng tiền và đồng bộ A4 dọc qua migration `2026_09_04_110000_fix_supplementary_services_report_layout`; không thay đổi logic nghiệp vụ khác.

- 2026-09-04: Triển khai độc lập báo cáo `SUPPLEMENTARY_SERVICES` theo dòng 106: procedure `rpt_supplementary_services`, template `SUPPLEMENTARY_SERVICES_STANDARD`, bộ lọc ngày/khoảng ngày và chọn nhiều dịch vụ; không sửa schema nghiệp vụ hoặc logic booking/checkout.

- 2026-09-03: `NO_SHOW` đã hiệu chỉnh theo `sp_054`: tiền dự kiến lấy từ `booking_room_services` RM/fallback giá phòng; Charge xác định riêng qua `service_bills + room_night_bills.is_room_night=1`; No Charge không cần bill và đối chiếu đúng ngày fallback. Lọc user theo `booking_rooms.created_by`, gộp đa chi nhánh hỗ trợ ASC/DESC và đánh lại STT. Migration `300000`/`310000`/`320000` đã chạy HKT1-HKT4.

- 2026-09-03: Đã gộp logic cuối của ROOM_MOVES vào migration chính 100000 và NO_SHOW vào migration chính 260000; các migration hiệu chỉnh sau đó được giữ dưới dạng no-op tương thích, không reset hoặc xóa dữ liệu.



- 2026-09-03: Thêm report độc lập `CANCELLED_ROOMS` theo `sp_261`/`sp_261_Division`/`vw_034`, dùng dữ liệu log hủy phòng và template riêng; không sửa `CANCELLED_BOOKINGS` dòng 107 hoặc logic hủy hiện tại.



- 2026-09-03: Tạo `.codex/docs/reports/data_retrieval/` với quy trình lấy dữ liệu report, mapping bảng/field/template, danh sách procedure runtime và các report legacy chưa triển khai. Chỉ cập nhật tài liệu, không thay đổi runtime.



- 2026-09-03: Triển khai độc lập dòng báo cáo chuyển phòng `ROOM_MOVES` theo legacy `sp_129`/`vw_017`; thêm procedure `rpt_room_moves`, template `ROOM_MOVES_STANDARD`, cấu hình report và test. Không sửa frontend, API dùng chung, logic chuyển phòng hoặc schema/import nghiệp vụ.

- 2026-09-03: Cập nhật layout `ROOM_MOVES_STANDARD` theo mẫu legacy: bổ sung STT, ngày đến, BK chuyển và dùng tên loại phòng cũ/mới; thêm migration layout riêng, không sửa report engine/frontend dùng chung.

- 2026-09-03: Tách header `ROOM_MOVES_STANDARD` thành các block riêng trong Template Editor để không gom thông tin khách sạn, divider, tiêu đề và khoảng ngày vào cùng một text block.



- 2026-08-28: Sửa cảnh báo/lỗi Template Editor khi `templateId` còn `null` hoặc `parameter_defaults` từ API là `null`: chỉ mount modal khi có ID hợp lệ, chuẩn hóa defaults thành object, bảo vệ vùng render và tải dữ liệu ngay khi modal được mount. Không đổi API/database.



- 2026-08-28: Bổ sung báo cáo `OOS_LOCK_HISTORY` theo legacy `sp_059` bằng migration `2026_08_28_170000_create_oos_lock_history_report`, procedure `rpt_oos_lock_history` và template `OOS_LOCK_HISTORY_STANDARD`. Triển khai độc lập trên `room_locks` với `lock_type=OOS`, không thay đổi API/controller/model khóa phòng hoặc báo cáo OOO.



- 2026-08-27: Bộ chọn khoảng ngày báo cáo đã dùng phép tính ngày local thay cho `toISOString()`, sửa lệch một ngày ở các preset Hôm qua/Tuần này/Tháng này trong múi giờ Asia/Bangkok.



- 2026-08-27: Các báo cáo phòng ở, phòng miễn phí và lịch sử khóa OOO đã được hiệu chỉnh. Phòng ở tính tỷ lệ theo phòng duy nhất; phòng miễn phí áp dụng `TachFOC` và gộp bill RM theo phòng/ngày; bộ lọc user OOO đọc System DB. Migration `170000` đã chạy trên HKT1-HKT4.



- 2026-08-27: Đã rollback toàn bộ triển khai `DEPARTING_ROOMS` để quay lại bước lập kế hoạch theo mẫu báo cáo có sẵn. Migration, procedure, cấu hình, template và thay đổi file dùng chung đã được gỡ trên HKT1-HKT4; `ARRIVING_ROOMS` được giữ nguyên.



- 2026-08-26: Dòng 107 — Availability thống nhất metric hiển thị `BBC - Baby Cot`; backend chỉ nhận mã Special Request canonical `BC` từ `booking_room_special_requests`, tính theo khoảng `[arrival_date, departure_date)` và loại trừ ngày checkout. Frontend build đạt; PHP lint đạt.



- Workbook issue row 131 is implemented in Create Registration: rate lookup now uses room-class ID/code and daily mappings, accepts configured zero/FOC prices, preserves an agreed rate when room class changes, and reloads the new-class price only after explicit Rate code selection. No API or schema change was added.

- Rate Setup now normalizes plan matrix keys before display/save. Existing duplicated prefixes such as `TEST2_mini_mini_*` no longer hide values in the plan modal or daily grid; canonical keys are preferred and recognized duplicates are cleaned on the next save.



- Workbook issue row 126 is implemented: arrival lists use room-level arrival dates, module-specific actions and booking-code navigation are applied, and cancel check-in is protected by `RoleUserCancelCheckIn` plus the system date.





- Workbook issue row 122 was implemented for Room Map: selected-date loading, daily EB, guest totals, Birthday/Honeymoon indicators and configurable compact card/text sizing are now supported. Existing API fields and booking logic remain compatible; no schema migration was added.



- Workbook issue row 125 now separates Room Map context-menu actions by module: Reservation shows registration only, Front Desk keeps current actions with role-controlled room-status changes, and Housekeeping shows HK invoice plus room-status changes. Backend status and room-lock endpoints enforce the module rule.





- Issue workbook row 120 was fixed in Room Map: the teleported context menu now anchors beside the selected room, switches sides when needed, uses rendered dimensions and viewport-safe coordinates; submenu alignment adapts near the lower-right viewport. No API or database change.



- Workbook snapshot was refreshed from the user-provided `DANH SACH BANG TRONG HE THONG.xlsx` dated 2026-08-18; see `.codex/docs/system_workbook/06_latest_snapshot_2026-08-18.md`. This documentation-only update does not change runtime code or schema.



- Room Plan Front Desk (`/frontdesk?tab=room-plan`) maps booking bars from `registration_status.name`; the booking card keeps its existing Room Plan color while the bottom stay line and tooltip status label use `registration_status.color`. Bookings with `registration_status.is_availability = 0` remain visible but are excluded from OCC calculations, including unassigned bookings. No API or database schema was changed.



- `bookings.is_master_room_rate` affects only new `RM/ER` bills. Toggling it does not move old bills; Checkout display/totals, settlement and checkout validation use each bill's stored `RentalRoomId2/CustomerId2`. Manual transfers do not change the flag.

- Quick Transfer candidate headers are data-driven: category uses `service_bills.ServiceId`, while the label uses the configured `FO` description with catalog name/code fallback. The modal has no hard-coded service-category labels.

- Checkout sorts services by service date/time with a stable ID fallback. Manual Room Charge and Night Audit normalize generated `BD` descriptions to `Phụ thu ăn sáng trẻ em - <tên trẻ>`; Night Audit matches setup-service dates with `whereDate`.

- Child-breakfast FIT/GIT now reflects the persisted flag. Updating a `BD` owner matches the existing setup service by calendar date without duplicates, and Room Charge posts `is_room=0` BD bills to Master.

- New service bills retain legacy username audit fields and additionally snapshot the authenticated employee code and current system shift. Checkout resolves the employee name by code, with username fallback for historical/imported bills.

- Checkout Room Charge now posts pending Booking setup services for each selected room/date in the same transaction; FIT/GIT ownership and Folio are preserved, while already-linked, posted and out-of-range rows are skipped.

- Front Desk Add Service reads services assigned to hard-coded department `FO` through `department_hotel_service`; its dropdown shows service names without embedded prices and the modal is draggable within the viewport.

- Service Collection still persists legacy positive/negative transfer audit rows. Checkout Master now hides transferred/cancelled audit rows (`Edit=1` or `Status=3/4`) while retaining paid history.



- The `modules` registry seeds the six current portal modules using verified legacy `SP1603.Module` codes (`SALE`, `FO`, `HK`, `RPPMS`, `ST`, `FB&SK`). It is data-only for now: frontend cards, routes and authorization remain unchanged; F&B outlets still come from `outlets`.

- Hard-coded data must be documented under `.codex/docs/hardcoded/`; `/pms` module cards are intentionally hard-coded in `frontend/src/pages/PmsPages.vue` until module authorization/menu loading is approved.

- Front Desk and Night Audit bill descriptions now use `department_hotel_service.description` for the configured service and append the physical room number; the hotel definition seed supplies descriptions for all 27 default services.

- Lost & Found uses the approved `lost-found.html` layout in the shared Housekeeping/Room Map Vue component. CRUD uses `/api/lost-and-found`; date controls display `dd/mm/yy` with a calendar picker, while the PMS schema intentionally omits legacy `SP1333` time fields and retains creator username, multiple images, report/guest/storage extensions, filters and pagination.



- Đã thêm cấu hình outlet/menu riêng cho module HK qua bảng `housekeeping_outlets` và các API `/api/housekeeping/outlets`; không dùng API/bảng `outlets` của F&B. Card cấu hình mới tại `/config` mở màn hình quản lý outlet HK, và post bill HK lấy mã outlet/dịch vụ từ cấu hình. Tìm kiếm hóa đơn và Checkout vẫn dùng dữ liệu bill hiện có, chưa đổi logic thanh toán.



- No Post is implemented in Checkout for Booking Master and individual rooms. Master updates cascade to rooms; an individual room can override the value. Housekeeping-source posting is rejected for No Post rooms, while Front Desk and Night Audit remain allowed.

- Checkout supports per-payment debt settlement for an `AC` payment line. Each settlement is independently recorded and soft-deletable; settlement cannot exceed that debt line's remaining amount.



- The codebase has 153 Vue SFCs, 68 Laravel models, around 47 API controllers and 55 migrations.

- Active work area: PMS -> Front desk -> Checkout tab (`frontend/src/pages/frontdesk/CheckoutPage.vue`) and housekeeping service-bill persistence.

- Availability detail work now adds a read-only `/api/availability/details` flow from `AvailableRoomsPage.vue`; room-class rows support AV/OCC/ALM/OOO/OOS and the TỔNG row additionally supports EB/SOFAB.

- Availability detail OCC rows are grouped by booking code; the modal shows a shared booking note, bold/collapsible `Mã ĐK` header, indented detail rows, compact status/company/room/guest columns and a wider note column.

- Availability detail booking headers now omit guest/note data; each booking total collapses with its detail rows, the overall total is sticky at the panel bottom, and the shared note is merged into one cell spanning each booking's detail rows.

- Availability detail API now filters the right panel by the clicked metric: OCC, ALM, OOO/OOS locks and EB; SOFAB returns no rows until its legacy source mapping is defined.

- Availability grid rows now support independent +/- OCC timeline expansion by room class; double-clicking an OCC room row routes to the related booking in Tạo đăng ký.

- Availability table styling now follows the row-105 baseline: 13px dark text, compact rows, alternating room-class backgrounds, date separators, bold total/max columns and red values only for AV values at or below zero.

- Room-rate adjustment supports either a date range or individually selected eligible nights; registration-rate updates can target the selected room or every room in the booking.

- Booking stays Inhouse after its final room checkout whenever active unpaid Master debt remains, including debt still linked to the checked-out room.

- Housekeeping service posting now records the selected room guest; Checkout filters new guest-linked services to that selected guest.

- Checkout allows eligible service bills, including room-charge `RM`, to transfer to another booking/room; splitting remains unavailable for `RM`.

- Creating a new booking no longer auto-creates room-service rows (`RM`/`EB`); those rows are created only through their explicit operational flows or a later booking update.

- Checkout lists only rooms with an assigned room number in Reservation/Inhouse status and no longer synthesizes display-only `RM` rows from the room rate.

- Checkout service totals no longer include projected room rates; a new booking with no posted services/bills starts at zero.

- All services posted from the Front Desk Add Service form, including `RM`, `RMS`, breakfast and other configured FO services, now persist links to their semantic service bill and bill detail so they can participate in audited transfer and Service Collection.

- Service Collection can move an `RM` bill without changing `is_master_room_rate`; the moved bill renders at its stored destination while future room charges continue following the flag.

- Transfer destination preview never synthesizes room-charge rows from a room rate; it shows only services/bills that have actually been posted.

- Checkout preserves the backend transfer trail for room charges, displaying the source and destination room in the RM description.

- Service Collection accepts either the selected room/guest or the booking Master as destination; a selected secondary guest is validated and retained as the current owner of the collected bill.

- Registration Invoice opens Checkout for the current booking; the adjacent Refresh button in Checkout returns to that booking in Front Desk's `create-res` tab.

- Service splitting creates independent service rows; the target Folio may be the same as the source Folio.

- Even split operations distribute the six-decimal quantity residual to the source row, preserving the original service total.

- Amount splits preserve the product rate and the exact requested amount; `total_amount` is authoritative when the derived fractional quantity is recurring.

- Service splitting now accepts only a requested amount and applies to one fully selected, unpaid, non-VAT, non-room service bill at a time.

- Service transfer now selects a destination booking or room, creates positive and negative service-bill audit lines, and preserves original ownership fields.

- Transfer preview now mirrors Checkout: a room shows grouped service-bill rows, while Master shows only its room-charge (`RM`) rows.

- Checkout service split/transfer uses the existing system toast and loading overlay; destination transfer requests use the numeric booking ID.

- Service transfer descriptions now retain the bill service name and append the source/destination location trail; the destination picker is a searchable dropdown styled like Checkout search.

- The shared transfer endpoint now distinguishes destination transfer requests from drag-to-Folio requests, preserving both Checkout interactions.

- Service transfer is permitted only from/to active Reservation or Inhouse rooms; API validation enforces the rule independently of the Checkout destination list.

- Transfers to a destination room always reset the moved service bill and its room-service rows to Folio 1; Checkout search tolerates structured guest entries.

- Checkout now has a Quick Transfer/Service Collection workflow: it lists unpaid service bills from other active rooms in the same booking, groups them by type, and moves selected bills to the target room's Folio 1 with transfer audit rows.

- Master Checkout now also renders active service bills whose current owner is the booking master, so bills moved to Master no longer disappear from the service list.

- Master summary totals include active service bills currently held by Master, in addition to room charges when the booking is configured to send room rates to Master.

- Checkout keeps RM/RMS on the Master folio after payment while `is_master_room_rate=1`; paid room-rate bills are not re-displayed on room cards.

- Room-row service totals apply the same ownership rule as the service panel, so paid Master room rates are excluded from each room's service total.

- Master settlement scopes payment/deposit updates to Master rows only; room deposits, housekeeping bills and room services are not marked paid by a Master payment.

- Room checkout with `AllowEarlyCheckout=1` presents a choice to charge remaining room nights or continue without that charge; Master checkout ignores room departure dates and checks only Booking financials. Full room checkout updates the Booking status when no active rooms remain.

- Room checkout validates only that room's unused deposits; Master checkout validates all booking deposits.

- Master service summary retains active Master bills (including paid history), while payable totals still use unpaid bills only.

- Quick Transfer Bill uses the standard system loading overlay and operation toast while fetching or transferring bills.

- When a room is the Quick Transfer target, its candidate list includes active unpaid bills currently held by the same booking Master; transferring one back to the room recreates its room-service display rows on Folio 1 and retains positive/negative bill audit rows.

- Service transfer and Quick Transfer preserve each service row's original `service_date`, posting timestamp and `created_at`; only the update/audit timestamps reflect the transfer operation.

- Checkout can cancel one selected non-room service bill with a required reason. Cancellation creates a negative service-bill audit row, marks both rows cancelled, removes original service-bill details and hides the room-service rows.

- Checkout service cards aggregate active service rows by service category, original transfer-source room, Folio and department; this is display-only and does not merge the underlying bills or bill details.

- Checkout service summaries round display values to whole VND; invoice details retain the exact fractional values.

- Master deposits created with a registration are stored on Folio 1. Checkout keeps gross service totals unchanged, shows active deposits as paid amount, and shows each Folio net of its own deposits; Folio A is the sum of Folios 1–3 after that calculation.

- Active deposits can be dragged to Folio 1–3; used deposits cannot move. A pending deposit has no payment code, while a later payment flow must set the same `payment_id` on the deposit and its paid service bills for reconciliation.

- Quick Transfer/Service Collection is available only when a room target is selected, not on Master. Payment-table department and payment-method columns display their stored codes.

- Service Collection now requires the selected room to exist in the current displayed booking list, preventing a stale room-selection state from enabling the action on an empty screen.

- Checkout can split one selected, active unused deposit by a requested amount into Folio 1â€“3. The new split row uses the selected Folio while the source stays on its current Folio; both retain the original date, creation timestamp and department, and the source retains the original total-before-split value.

- Checkout now includes active room-specific deposits in each room's paid amount; successful service posting uses the shared toast instead of a browser alert.

- Unused deposits can be transferred with an audit trail to a Booking Master or eligible Reservation/Inhouse room; the destination preview shows its active deposits and room destinations use Folio 1.

- Checkout date/time columns now prefer the business date plus stored `open_time`/creation time. The header and total-row checkboxes select or clear all visible services or active deposits.

- Checkout's "Xem tất cả khách trong phòng" search list renders each adult guest name correctly; without the option it shows only the room's primary guest.

- With all adult guests visible, Checkout calculates each row's service and payment totals for that guest; Master renders bills owned by Master according to current ownership fields.

- Quick Transfer accepts string guest identifiers and validates that the selected adult guest belongs to the target room.

- Master summary totals include active bills currently owned by Master, including bills that originally arose in a room.

- Moving a bill from Master to a room/guest creates one room-service row from the service-bill header; bill-detail lines remain invoice-only and do not create breakfast/adjustment room services.

- A room charge transferred from Master keeps service code `RM`; `FO` remains its posting department/outlet and is not used as the service code.

- Selecting one or more active, unused deposits enables audited bulk transfer to an eligible Booking Master or room; split deposit remains limited to one selected deposit.

- Cancelling a posted housekeeping service now keeps its housekeeping bill and details for audit while marking the bill cancelled; cancelled bills do not render in Checkout.

- The cancellation form now offers price adjustment only for one selected service card: RM opens the prefilled room-charge form; housekeeping cards open the prefilled housekeeping form.

- The booking/reservation module is the most deeply implemented and documented; `PLAN_NGHIEP_VU_DAT_PHONG.md` describes its detailed business rules.

- Existing feature tests cover booking creation, room locks/moves, room constraints, templates and company import/export.

- No changes were made during the initial architecture review.

- The repository contains legacy database documentation in `old_database_struct/` and business/UI reference files at the root; the legacy application source itself was not identified in this repository.



- Checkout defaults each assigned room to its primary adult guest; the all-guests checkbox expands that room to linked adult secondary guests without duplicating the primary guest.

- Checkout guest/room service totals follow each bill's stored current owner for both paid and unpaid room charges.

- Checkout payment deletion now requires a reason, records it on the reversal row, restores linked service/housekeeping bills, clears payment/invoice links, and preserves guest/Folio context.

- Room settlement includes every unpaid bill currently owned by the room, including `RM/ER`; Master-owned bills remain outside room settlement.

- Checkout service Filter now applies date, payment/VAT status, service code, Folio and department criteria to the visible service list and totals; Reset clears the criteria without changing database data.

- Checkout Filter is scoped to the currently selected Master, room and guest; switching the selection clears the previous filter criteria.

- Checkout excludes assigned rooms whose arrival date is after the system date, and the room-charge API rejects RM posting before the room arrival date.

- Checkout loads Booking statuses Reservation/Inhouse (`0,1`) and always renders their Master rows; it renders only Inhouse room rows (`booking_rooms.status = 1`).

- Checkout supports restoring a room checkout only on the system date when its physical room is not locked or already inhouse for another booking; it restores only the final checkout guest group. Restoring Master changes only the booking status and never restores child rooms.



## 6.4 Checkout / Master room-rate behavior



- When `is_master_room_rate=1`, RM/RMS remains owned by the Master folio after payment; Checkout must not reclassify paid room-rate bills onto room cards.



## 7. Known Risks / Open Questions

- Fresh reset chưa được chạy sau khi hợp nhất migration. Các database đã chạy migration patch cũ không được nâng cấp bằng lịch sử đã rút gọn; cần backup và dùng đúng lệnh reset multi-database.

- 2026-09-10: Các provider mới vẫn là mẫu tham chiếu, chưa được runtime gọi. Cần procedure/call site BR/Free, query bảng kê sản phẩm, mapping tiền snapshot và quy tắc Paid/cutoff của công nợ trước khi tạo datasource hoặc import mapping; dòng số tiền còn nợ theo từng nhóm công ty tiếp tục để `—` vì chưa có contract đã xác minh.

- 2026-09-09: Nhóm hóa đơn HK/công nợ chưa đủ nguồn cho backend: BR/Free lệch procedure, thiếu truy vấn bảng kê/detail, trường snapshot thuế/phí/FOC và mapping SP3003; Pack2 legacy khác runtime. Hai view đã xác minh nguồn ngày khác nhau (SP3000/SP6000). Không suy diễn import hoặc sửa nghiệp vụ để khớp báo cáo. Provider/UI chưa tích hợp, chưa acceptance dữ liệu thực/in/export/Designer; công nợ còn placeholder số nợ theo nhóm và định dạng tổng nhóm chưa hoàn chỉnh. `hotel.logo` chỉ được nhận HTML tin cậy.

- Existing bookings with `bookings.is_day_use=1` and `booking_rooms.is_day_use=0` are not changed automatically until saved or backfilled.

- Day Use backfill changes room-level flags from the booking-level source; verify affected rows before running on each production branch.

- DAY_USE_ROOMS needs MySQL migration and branch browser acceptance with representative Day Use data; static backend tests pass.

- Report preview 125% có thể xuất hiện thanh cuộn ngang trên màn hình nhỏ; đây chỉ là hành vi xem trước và không ảnh hưởng bản in.



- `SUPPLEMENTARY_SERVICES`: database hiện tại chưa có dữ liệu non-RM để đối chiếu số liệu thực tế; cần browser acceptance sau khi có dữ liệu dịch vụ.

- `SUPPLEMENTARY_SERVICES` đã được kiểm tra bằng static migration assertions và template renderer; cần browser acceptance với dữ liệu dịch vụ thực tế để đối chiếu số lượng/giá/tổng và thao tác chọn nhiều dịch vụ.

- Tổng nhóm trong renderer hiện dùng giá trị số thô; tổng toàn báo cáo đã định dạng theo helper hiện có. Không thay đổi renderer dùng chung để tránh ảnh hưởng các report khác.



- `NO_SHOW`: HKT1-HKT4 hiện trả 0 dòng đủ điều kiện trong phạm vi kiểm tra 2000–2100; procedure/rule và test gộp chi nhánh đã đạt nhưng cần browser acceptance khi có dữ liệu No-show kèm bill RM thực tế.



- `CANCELLED_ROOMS` chưa tái hiện tổng hợp nhiều database của `sp_261_Division`; runtime hiện lọc division trong branch hiện tại để không tạo dynamic cross-database coupling.

- `ReportsPage.vue` đã được cập nhật tối thiểu để radio hiển thị dạng nút chọn và checkbox hiển thị toggle kèm nhãn; contract API và dữ liệu các report khác không đổi.

- Sửa lỗi runtime `CANCELLED_ROOMS` 422 do `parameter_schema` có 7 mục trong khi procedure nhận 6; migration `170000` đã chạy trên database hiện tại.



- Ma trận report legacy chưa triển khai vẫn thiếu mapping chi tiết cho một số Store như công nợ, công suất và doanh thu; cần đọc procedure/view gốc trước khi viết migration hoặc import mapping.



- Báo cáo chuyển phòng chưa được chạy migration/runtime trên các database chi nhánh trong phiên này; cần kiểm tra procedure với dữ liệu move thực tế và acceptance UI.



- HKT1-HKT4 hiện không có booking/guest runtime tại ngày hệ thống để đối chiếu toàn bộ báo cáo; logic hiệu chỉnh đã được xác minh bằng fixture transaction và cần browser acceptance khi có dữ liệu nghiệp vụ thực.



- Dữ liệu danh mục được seed tại HKT1 lúc 11:32 ngày 2026-08-27 vẫn được giữ lại theo phê duyệt vì booking hiện tại đã liên kết; không được xóa khi lập kế hoạch lại báo cáo phòng đi.



- Các database vẫn còn bảng vật lý trùng từ kiến trúc cũ. Runtime không dùng các bản sao thuộc nhóm System đã xác minh; chưa xóa nếu chưa có backup và phê duyệt.

- Bảng legacy chưa xác minh mặc định tiếp tục thuộc Branch DB. Chỉ chuyển ownership sau khi có mapping đáng tin cậy.

- Một số user kiểm thử đã tồn tại trong `pms_system` từ trước khi test isolation được bổ sung; chưa xóa vì cần phê duyệt thay đổi dữ liệu riêng.

- Workbook row 131 still requires browser-level acceptance with runtime legacy Rate code data, especially daily plan mappings and bookings whose room class is changed after the price was agreed.



- Workbook documentation is now based on the 2026-08-18 file hash `6DE89EFC786EF6FE376D9598C3C2848B76FB9EAD226FD1E8446F07D15A5B3E9D`; workbook content remains reference material and is not automatically an import mapping.



- Existing `RM/ER` bills that were already moved by the previous toggle behavior remain at their stored owner. The system does not infer or repair their former room/Master ownership automatically; correction requires an explicit audited transfer.

- Front Desk Add Service keeps department code `FO` hard-coded until authorization/module context supplies the posting department dynamically.

- Existing bills created with zero/missing tax profiles are not backfilled; correcting historical tax data requires a separately approved audited migration/import process.

- Legacy tax amount columns are not fully represented in the new service detail schemas; runtime now preserves configured percentages, while import must not recalculate missing historical amounts.



- The legacy meaning of `SP1333.Status` bit values has not been verified. Import must not map the bit to PMS `lost`/`found` until confirmed.



- Room-rate adjustment's individual-night dropdown requires browser-level visual acceptance against the legacy UI; frontend build verifies compilation only.

- Availability detail has no finalized legacy source mapping for SOFAB/Baby Cot; its detail list remains empty until the special-request mapping is defined.



| Priority | Item | Evidence / action |

|---|---|---|

| Medium | Existing Booking records already marked Checkout are not retroactively reopened by the completion rule. | Restore the Booking Master checkout once through the Checkout history UI, then settle the remaining Master debt. |

| Medium | PHP 8.4 CLI does not enable `pdo_mysql` in `C:\php84\php.ini`. | Use `-d extension=pdo_mysql` for local Laravel commands or enable the extension permanently before routine API/database verification. |

| Medium | Historic room-service records have no `guest_id`. | Checkout displays them for the primary guest only; new postings carry the selected guest ID, while legacy unassigned rows retain the primary-guest fallback. |

| Medium | Some historic Front Desk room-service rows have no service-bill link. | Use `services:backfill-bill-links --booking=<booking-code> --apply`; it updates only rows with one exact active bill/detail match and skips ambiguous records. |

| Medium | Split services clone room-service rows and divide quantity/amount; reconciliation with legacy bill-detail tables still needs acceptance testing. | Test double/triple/amount split against posted bills before production use. |

| Medium | Historic room-service rows were created before service-bill links were introduced. | They cannot be split under the new bill-safe flow; post a new service bill or complete an explicit audited backfill. |

| Medium | Historic raw `RM` room-charge rows have no linked `service_bills`. | They can render under Master as room charges but cannot yet be selected by Quick Transfer, which operates on auditable service bills only; define and implement the room-charge bill creation/audit mapping before enabling RM collection. |

| Medium | Deposit-transfer API behavior, including the new multi-deposit transaction, has not yet been verified end-to-end with source and destination records. | Verify a single and multi-deposit transfer to Master and to an eligible room before release. |

| Low | Some historic/generated room-charge rows may not contain a stored creation time. | Checkout shows the hour when `open_time` or a creation timestamp exists; persist a creation time for future room-charge records if legacy parity requires it. |

| Medium | Housekeeping price-adjustment submit currently re-posts the loaded bill data and does not yet write an adjustment-specific audit link. | Define the final adjustment ledger contract before treating the submitted adjustment as complete. |

| Low | Checkout service summaries use display-only whole-VND rounding. | Open invoice detail when the exact fractional amount or six-decimal quantity is needed; persisted values are unchanged. |

| High | F&B/outlet/table/product route block is duplicated. | Consolidate duplicate declarations in `backend/routes/api.php`; verify routes afterwards. |

| Medium | Some `booking-service.js` helpers appear not to match API routes. | Audit each frontend service URL against `backend/routes/api.php` before booking changes. |

| Medium | Checkout records created before `CheckoutDate`/`CheckoutTime` were persisted cannot use the new restore action. | Only rooms with `CheckoutDate = system date` are eligible; legacy history must remain read-only. |

| Medium | Branch choice is stored client-side. | Confirm intended server-side branch scoping and enforce it in API queries if required. |

| Medium | Root/frontend/backend READMEs are starter templates. | Replace with project setup and deployment documentation after environment is stabilised. |

| High | Legacy-to-new feature parity and data mappings are not tracked in one shared matrix. | Create and maintain a feature/data traceability matrix before parallel cloning expands. |



## 8. Delivery Roadmap



### Legacy discovery and team coordination

- Inventory every legacy feature, screen, interaction, business rule, report and import/export operation.

- Maintain a traceability matrix: legacy feature/screen/table -> owner -> new UI -> API -> model/migration -> acceptance evidence.

- For each assigned feature, capture reference behaviour and identify dependent shared files before implementation.

- Use the shared-file approval process before changing cross-feature code.

- Completion: every in-scope legacy feature has an owner, acceptance criteria and migration dependency status.



### Phase 0 — Establish a runnable baseline

- Upgrade local PHP to the version required by `backend/vendor/composer/platform_check.php` (currently >= 8.4.1), then run route listing and targeted tests.

- Document supported PHP, Node, Composer, npm, database and `.env` setup in `backend/README.md` and `frontend/README.md`.

- Verify migrations and seeders on a disposable local database; never reset shared/production data.

- Completion: clean backend boot, frontend production build, migration status and critical tests recorded.



### Phase 1 — API and domain-contract cleanup

- Remove duplicated F&B route registrations.

- Build an endpoint-to-service audit for all files in `frontend/src/services/`.

- Fix stale/missing URLs or implement intentionally missing endpoints; add route-level tests for changed contracts.

- Establish a consistent API response/error envelope where existing controllers differ.

- Completion: no duplicate API definitions; every active frontend service call maps to a protected/public route intentionally.



### Phase 2 — Reservation hardening

- Turn each rule in `PLAN_NGHIEP_VU_DAT_PHONG.md` into an acceptance checklist.

- Prioritise date boundaries, room/lock overlap, room movement, availability, check-in restrictions, services and payment integrity.

- Expand feature tests around controller transactions and negative cases; include branch scoping if it is a confirmed requirement.

- Completion: documented rules mapped to tested API behaviours and UI flows.



### Phase 3 — Operational modules

- Validate front desk checkout/folio workflow and housekeeping service posting against booking/payment data.

- Audit F&B order, party, printer and transfer flows; define idempotency expectations for order sync.

- Define data ownership/integration rules between room charges and F&B.

- Completion: cross-module handoffs have explicit API contracts and regression tests.



### Phase 4 — Quality, security and release readiness

- Add automated frontend checks and an API test suite suitable for CI.

- Define role/permission policy; current route-level authentication does not by itself demonstrate per-action authorisation.

- Add observability conventions using activity logs and structured application logs; scrub sensitive data.

- Create backup, migration, deployment and rollback runbooks.

- Completion: CI gates, documented deployment and validated production-readiness checklist.



### Legacy data import and cutover

- Define source-to-target mappings for legacy tables, codes, statuses, dates, monetary values and attachments.

- Build idempotent import tooling with validation, error reporting, dry-run support, audit log and rollback strategy.

- Reconcile record counts, totals, relationships and sample business flows after each import rehearsal.

- Freeze/sequence cutover data and establish backup/rollback runbooks before production import.

- Completion: successful dry-run import and reconciliation sign-off.



### Shared-file change protocol

- Treat routes, models, migrations, shared stores/services/components, layouts, global styles, configuration, seeders and import tooling as shared.

- Before editing, report target files, affected features/owners, expected API/data/UI impact, and whether the current feature is impacted.

- Obtain approval before the shared-file edit.

- Record approved shared-file changes in **Recent Changes**.



## 9. Working Protocol for Future Sessions



1. Read this file and the module-specific plan before proposing work.

2. Identify legacy behaviour and acceptance criteria before cloning a feature.

3. Inspect the route, frontend service and migration/model together for any cross-layer change.

4. Follow the **Shared-file change protocol** before editing shared code.

5. Run the smallest relevant verification available; record commands/results in **Recent Changes**.

6. Update the status, risks and roadmap whenever the project direction or a blocker changes.



## 10. Recent Changes

- 2026-09-23: Booking payment quick update: DepositModal giữ đúng đích booking/phòng/khách theo Checkout, reload tổng cọc active, CreateRegistration giữ booking-room metadata; PaymentController kiểm tra method khi sửa nhanh; QuickUpdateModal đọc cả `bookingRoomStatus` và `status`. SFC parse/PHP lint/route listing đạt; feature tests và Vite build còn bị chặn bởi `mysql_data`/Tailwind native dependency.

- 2026-09-23: Booking room lifecycle now treats same-day `is_day_use` stays as zero nights, permits their bulk date update, and synchronizes unposted RM/EB/child-breakfast rows after room or reservation-date edits. Moved-to rooms cannot undo check-in, and cancelled rooms are shown only when the booking header is cancelled. Added `docs/booking-fix-plan/ROOM_LIFECYCLE.md` and regression coverage; configured `mysql_data` is still required for full feature execution. Frontend production build remains blocked by the missing Tailwind oxide native binding/Windows `spawn EPERM`.

- 2026-09-23: Booking realtime now uses `ShouldDispatchAfterCommit` on `ReservationUpdated`/`RoomStatusUpdated`; `BookingController::restore(Request $request, $id)` fixes the optional `force` request branch. Documented in `docs/booking-fix-plan/IMPLEMENTATION.md` (a local `.codex/docs/booking/realtime.md` note is also present). PHP lint passed; `php artisan test tests/Feature/BookingTest.php --compact` remains blocked because `mysql_data` is not configured in the test environment.

- 2026-09-10: User duyệt sửa mẫu dùng chung LA mà BR/MB tái sử dụng và tài liệu liên quan. Chuyển grouping LA sang cấu trúc Designer hiện hành, thêm selector CSS theo ID block, mặc định sort Mã/ASC, sửa contract validator; công nợ dùng block text có hàng điều kiện để giữ ẩn/hiện chi tiết giải trừ sau Designer và thêm selector CSS theo ID. Thêm `HousekeepingInvoiceDataAdapter` không suy diễn nghiệp vụ. Kiểm thử đạt backend 19/19 (152 assertions), toàn bộ frontend 37/37, production build và PHP lint; không DB write, migration, schema/import hoặc thay đổi Checkout/Payment.

- 2026-09-09: Hoàn thiện file riêng cho template tham khảo LA/công nợ và bộ lọc HK theo phạm vi user duyệt. Test provider đạt 7/7 (86 assertions), frontend đạt 34/34 (5 test mới), PHP lint và production build đạt; còn cảnh báo chunk >500 KB. Không chạy migration/seed, không đổi schema/import, không sửa code dùng chung hoặc kích hoạt báo cáo runtime. Đã cập nhật tài liệu nhóm reports.

- 2026-09-07: Added DAY_USE_ROOMS migration, procedure, metadata, legacy-style template, focused tests, and report/database mapping documentation. Backend focused tests pass 3/3; runtime branch migration remains pending configured MySQL data.

- 2026-09-07: Bọc iframe báo cáo trong vùng kích thước đã scale và dùng `transform: scale(1.25)` trong `ReportsPage.vue`; frontend production build đạt.

- 2026-09-05: Corrected EXTRA_BED procedure/template and expanded tests from source-presence checks to seven business-contract/render scenarios. PHP syntax passed and ExtraBedReportTest passed 7/7, 60 assertions. No shared Checkout/Night Audit/frontend logic changed.

- 2026-09-05: EXTRA_BED report added with isolated procedure, template, focused contract test and report documentation; no shared business logic changed. Runtime MySQL verification remains pending.

- 2026-09-04: Sửa các điểm lệch UX/UI của `SUPPLEMENTARY_SERVICES`: loại RM khỏi lookup, dropdown checkbox, subtotal theo mẫu và A4 dọc; test/build cần chạy lại sau migration hiệu chỉnh.

- 2026-09-04: Thêm `SUPPLEMENTARY_SERVICES` độc lập theo dữ liệu `booking_room_services`, lookup danh mục `hotel-services`, multi-select trên Viewer/Report Definition Manager, template nhóm theo dịch vụ và test renderer; PHP test đạt `2/2`, `18` assertions, frontend build đạt.

- 2026-09-03: Hợp nhất các migration báo cáo ROOM_MOVES/NO_SHOW vào migration tạo chính; migration phụ giữ tên và lịch sử nhưng không còn thực thi SQL trùng lặp. Test nhóm báo cáo đạt 17/17, 147 assertions.

- 2026-09-03: Sửa No Charge bị đánh nhầm thành Charge khi chọn khoảng nhiều ngày bằng migration `2026_09_03_320000_fix_no_charge_date_matching.php`. Kiểm tra trực tiếp HKT1 với khoảng `03/08–12/08/2026`: All `7`, Charge `1`, No Charge `6`.



- 2026-09-03: Migration `2026_09_03_310000_include_uncharged_no_show_rows.php` bỏ yêu cầu bill RM đối với No Charge và tách nguồn tiền dự kiến khỏi bill thực tế. Test No-show + thao tác ghi log đạt `6/6`, `74` assertions; HKT1-HKT4 hiện chưa có log/phòng No-show thực tế.



- 2026-09-03: Sửa độ chính xác `NO_SHOW` bằng migration `2026_09_03_300000_fix_no_show_legacy_accuracy.php`; controller dùng chung chỉ đổi nhánh sort `NO_SHOW`, không đổi `CANCELLED_ROOMS`. Test liên quan đạt `15/15`, `118` assertions; runtime HKT1-HKT4 xác nhận procedure có rule mới.



- 2026-09-03: Triển khai `CANCELLED_ROOMS` riêng cho dòng 108, mapping log hủy phòng `booking_cancel_logs` với legacy `SP8052`, template 14 cột và test hồi quy; không ảnh hưởng report hủy đăng ký hiện có.

- 2026-09-03: Bổ sung tài liệu `.codex/docs/reports/data_retrieval/README.md` và `report_matrix.md`, mô tả luồng frontend → API → executor → procedure → template và cách lấy dữ liệu từng report.



- 2026-09-03: Thêm `ROOM_MOVES` độc lập theo `sp_129`/`vw_017`, lọc ngày chuyển và người thực hiện, hiển thị phòng cũ/mới; test riêng đạt `2/2`, `11` assertions. Không thay đổi logic backend/frontend dùng chung.

- 2026-09-03: Fix giao diện báo cáo chuyển phòng theo ảnh mẫu legacy với 14 cột và STT; test riêng cập nhật đạt sau thay đổi.



- 2026-08-28: Template Editor nạp CSS mẫu theo scope riêng trên vùng preview; cỡ chữ và định dạng trong OOO/OOS cùng các mẫu khác khớp hơn với báo cáo in, không ảnh hưởng CSS toàn ứng dụng.



- 2026-08-28: Đồng bộ Header của các template báo cáo Inhouse, Complimentary, Due In, OOO và OOS thành block riêng (thông tin khách sạn, đường kẻ, tiêu đề, khoảng ngày); Arriving/Departing đã đúng nên giữ nguyên.



- 2026-08-28: Đồng bộ content_json của template OOO/OOS với content_html bằng Header block; trình thiết kế mẫu hiển thị đúng header như báo cáo in.



- 2026-08-28: Bổ sung content_json block cho template `DUE_IN_ROOMS_STANDARD`; trình thiết kế mẫu không còn hiển thị vùng trống sau khi migration.



- 2026-08-28: Khắc phục Vue warning `templateId=null`, lỗi render `template.parameter_defaults[parameter.name]` và modal trắng do watcher không chạy khi component mount có `isOpen=true`; frontend production build đạt.



- 2026-08-28: Thêm báo cáo lịch sử khóa phòng OOS theo `sp_059`/`SP4002`: lọc khoảng ngày, user, nhóm Locking/UnLock, sắp xếp phòng/ngày, template 7 cột và test hồi quy riêng. Không sửa luồng khóa phòng dùng chung.



- 2026-08-28: Bộ chọn ngày báo cáo hỗ trợ đầy đủ phạm vi hiện tại/tiếp theo/trước theo giao diện legacy; thêm phạm vi quý/năm và test helper. Hướng dẫn manual OOS đã được cập nhật.



- 2026-08-27: Thêm `report-date-range.js` và 2 test múi giờ cho preset báo cáo; frontend đạt 25/25 test và production build đạt. Không đổi API hoặc logic booking/lễ tân/checkout.



- 2026-08-27: Thêm migration `2026_08_27_170000_fix_room_report_data_accuracy.php`, lookup `users` cho báo cáo OOO và test hồi quy. Fixture transaction xác minh tỷ lệ phòng ở và hai nhánh `TachFOC`, sau đó rollback toàn bộ dữ liệu test.



- 2026-08-27: Sửa Checkout không hiển thị phòng vừa giao: frontend ưu tiên `booking_rooms.status` khi nhận diện checkout, không còn coi `CheckoutDate` dự kiến của phòng `status=0/1` là đã trả phòng; dữ liệu legacy thiếu status vẫn fallback theo ngày. Thêm 4 test hồi quy; toàn bộ 23 frontend tests và production build đạt. Không đổi API/database.



- 2026-08-27: Rollback `DEPARTING_ROOMS` trên HKT1-HKT4 và phục hồi `TemplateRendererService.php`, `MainLayout.vue`, `DesignTemplateTab.vue` về Git baseline. Dữ liệu danh mục/booking được giữ nguyên theo phê duyệt.



- 2026-08-21: Implemented System/Branch domain connections, migration/seed commands, branch authorization, cross-database User references and Booking actor IDs. Automated tests now use SQLite and do not write to real `pms_system`.

- 2026-08-20: Fixed Rate Setup plan-matrix reload for row 131. Verified `TEST2/TEST2_mini` was persisted; frontend prefix conversion created duplicated keys and hid values. Added canonical matrix normalization, deterministic duplicate handling and 5 focused tests; combined frontend tests reached 10/10 and build passed. No API/schema/import change.



- 2026-08-20: Fixed workbook row 131 in Create Registration. Rate-code pricing is resolved by room-class ID/code and arrival-date mapping, supports legacy matrix keys and zero/FOC prices, and preserves agreed rate/price during room-class changes until Rate code is explicitly reselected. Added 5 passing Node tests; frontend build passed. No backend, database or import mapping change.



- 2026-08-18: Implemented workbook issue row 126 and business rule row 51 for arrival filtering, module-aware booking navigation, and permission/date-protected cancel check-in. Frontend and PHP verification passed.



- 2026-08-18: Implemented workbook issue row 125 with `RoleUserAllowChangeRoomStatusAtReception`, module-aware Room Map actions and backend enforcement. Added legacy fallback to `AllowChangeRoomStatusAtReception`; syntax/build verification passed.





- 2026-08-18: Implemented workbook issue row 122 across Room Map API/display. The selected Room Map date now controls booking details and daily EB lookup; cards show corrected guest totals, guest names, Birthday/Honeymoon/EB icons and configurable compact sizing. Frontend build and backend syntax checks passed.



- 2026-08-18: Fixed workbook issue row 120 in `RoomMapPage.vue`; context-menu positioning now anchors beside the selected room, switches sides when needed, uses actual rendered height and viewport clamping, with adaptive submenu alignment. Frontend build passed.



- 2026-08-19: Hoàn thiện giao diện Room Map danh sách theo dòng 108: bỏ kính lúp tiêu đề cột, chuyển cập nhật trạng thái và in Worksheet thành hai nút icon trước Help, dùng context menu và quyền `canChangeRoomStatus`; sơ đồ lưới không thay đổi.

- 2026-08-19: Cập nhật nhiều trạng thái phòng qua `POST /api/rooms/bulk-status` trong một transaction; Room Map chỉ tải lại danh sách và thống kê một lần sau thao tác.

- 2026-08-19: Nút in Worksheet trên Room Map điều hướng đến Housekeeping In phân công và truyền danh sách `roomIds` cùng ngày để tự chọn đúng phòng trong form.

- 2026-08-19: Ẩn hai nút Room Map mới ở module Đặt phòng; Room Plan và Create Registration xác định `module/current_module` theo route để booking tạo/xóa ở Front Desk dùng `reception`, tránh lỗi kiểm tra bộ phận.

- 2026-08-19: Chuẩn hóa ownership module theo mã `SALE/FO/HK`; thêm `ModuleCode` normalize alias cũ, migration backfill `bookings.module`, permission và API xóa so sánh mã; dịch vụ tiếp tục lưu mã bộ phận `FO/HK`.

- 2026-08-19: Fix dòng 114: hủy phòng không còn xóa dịch vụ tự động hoặc tự hủy Booking header; xóa Booking dùng `RegistrationStatusId_BookingCancel` nếu cấu hình hợp lệ, nếu không giữ `registration_status_id`. Test hủy phòng và cascade đạt 1 test/6 assertions.



- 2026-08-18: Refreshed `.codex/docs/system_workbook` from the latest user-provided workbook; recorded six-sheet row counts, file hash/size, and issue 300 about preserving or updating Rate Code and room price during room moves. Documentation only; no runtime files changed.



- 2026-08-17: Housekeeping invoice search now exposes print and cancel actions. Cancel is restricted to HK bills that are not edited, unpaid and do not have a VAT number; cancellation keeps the legacy negative-audit flow. Added module documentation in `.codex/docs/housekeeping/invoice_search.md`.

- 2026-08-17: Housekeeping invoice search now opens an invoice-detail popup when a row is clicked; print and cancel actions are inside the popup, matching the legacy interaction.



- 2026-08-14: Corrected Master Room Rate to be prospective only. Toggling the booking flag no longer moves old `RM/ER` bills; manual transfer does not change the flag; updating an old bill preserves its owner. Checkout display/totals, room settlement and checkout validation now use stored current-owner fields. Backend tests passed (53 tests, 217 assertions) and frontend build passed.

- 2026-08-14: Fixed Quick Transfer candidate grouping for room and Master bills. Both now group by `ServiceBill.ServiceId`; headers use `department_hotel_service.description` for `FO`, then catalog name/code fallback, and no longer inherit the first bill's room-specific description. `BookingRoomServiceFolioTest` passed (17 tests, 77 assertions) and frontend build passed.

- 2026-08-14: Checkout service rows now sort ascending by business date, posting time and stable bill/service ID. Generated child-breakfast bills preserve `Phụ thu ăn sáng trẻ em - <tên trẻ>` in both bill headers and details for manual Room Charge and Night Audit. Night Audit setup-service lookups now use `whereDate`. Backend tests passed (20 tests, 87 assertions) and frontend build passed.

- 2026-08-14: Fixed child-breakfast FIT/GIT display and date-based `BD` synchronization. Changing `is_room` updates one setup row instead of inserting a duplicate; Checkout Room Charge keeps `BD is_room=0` on Master. Booking/Checkout tests passed (35 tests, 142 assertions) and frontend build passed.

- 2026-08-14: Added nullable employee audit fields for posted services and automatic `service_bills.Ca` capture from `system_date_rolls.shift`. Checkout now displays `users.name` resolved by employee code and falls back to legacy username. Mapping docs preserve `SP3000.Username/CreatedUser`; `BookingRoomServiceFolioTest` passed (16 tests, 68 assertions) and frontend build passed.

- 2026-08-14: Removed hard-coded zero tax profiles from new `RM`, `ER`, `BF` and FO bills. Hotel-service bills now snapshot `ServiceCharge/SpecialTax/Tax`; housekeeping posting reloads each product profile from the database and stores rates in bill details. Checkout no longer adds percentage rates across multiple products. Relevant backend tests passed (27 tests, 121 assertions) and frontend build passed. Full backend suite passed 105/107; isolated pre-existing failures remain in `BookingTest` (missing `FO` fixture) and `RoomMoveTest` (stale expected date).

- 2026-08-14: Checkout Add Service -> Room Charge now posts pending non-RM Booking setup services for the selected room/date range, preserves FIT/GIT ownership and Folio, and prevents repeated posting through the source row's bill link/status. No migration or import mapping changed. `BookingRoomServiceFolioTest` passed (16 tests, 63 assertions).

- 2026-08-14: Corrected Checkout Master display after Service Collection by filtering transferred/cancelled audit rows without removing the legacy negative transfer record. Front Desk Add Service now shows service names only and its modal supports bounded pointer dragging. The FO service list remains driven by `department_hotel_service` membership. `BookingRoomServiceFolioTest` passed (15 tests, 51 assertions); frontend build passed.



- 2026-08-13: Removed the redundant Lost & Found in-content title bar and navy background; the surrounding module shell remains the navigation title.

- 2026-08-13: Lost & Found now uses the shared loading overlay for list fetch, save and delete operations. Cancel is red, primary actions remain blue on hover, and edit actions use a visible light-blue treatment.

- 2026-08-13: Lost & Found date controls now display `dd/mm/yy` with an end calendar icon while retaining ISO API values. Removed `time_found` and `time_handling` from the Vue form, API, model and base migration; mapping documents the intentional legacy import omission.

- 2026-08-13: Replaced the Lost & Found Vue UI with the approved HTML layout, retained authenticated CRUD, added report/status/search pagination, multiple images and creator tracking, and documented the `SP1333` mapping. `LostAndFoundTest` passed 3/3 and the frontend production build passed.



- 2026-08-12: Removed runtime dependency on Housekeeping outlet `group_key`; HK menu, post-bill grouping, and outlet configuration now use `housekeeping_outlets.code`. The create-outlets migration now owns legacy outlet normalization and HK `open_key` backfill. Frontend build and PHP syntax checks passed.



- 2026-08-07: After a successful room or Master Checkout, Front Desk now clears the selected booking/room/guest, service/payment selections, Folio, filters and panel data; partial/failed checkout keeps the context for follow-up. Frontend build passed.

- 2026-08-11: Added read-only availability detail API and draggable two-panel detail modal. Frontend build, PHP syntax checks and availability route listing passed.



- 2026-08-07: Removed the generic service-success toast from payment, payment-deletion, deposit split/transfer and service-transfer refreshes; each operation now shows only its own result notification. Frontend build passed.



- 2026-08-07: Suppressed the generic “Đã thêm dịch vụ thành công” toast during post-operation refreshes; service deletion now shows only its own success toast. Frontend build passed.



- 2026-08-07: Front Desk Thanh toán trước now sends and persists the selected Checkout Folio, including Master payments, and success responses/toasts use “Thanh toán trước thành công”; AP remains distinct from deposit DPR. `BookingRoomServiceFolioTest` passed (12 tests, 38 assertions); frontend build passed.



- 2026-08-07: Updated the Checkout cancellation dialog so the price-adjustment action is hidden for `RM/RMS`; room-rate adjustment remains available through the Master room-rate flow, while non-room services retain their adjustment action. Frontend build passed.



- 2026-08-07: Fixed Checkout service selection identity to preserve room-service row IDs without numeric coercion; RM/RMS rows can now be selected as a complete group and expose the room-rate adjustment action. Frontend build passed.



- 2026-08-07: Checkout now recognizes both `RM` and `RMS` room-charge rows when opening the cancellation dialog, so room-level price adjustment is available for selected guest room charges. Frontend build passed.



- 2026-08-07: Extended Front Desk guest ownership fix to the Tiền phòng tab: selected room guest is validated and persisted on new RM/RMS bills and room-service rows; Master posting remains unchanged.



- 2026-08-07: Fixed Front Desk Add Service guest ownership: Checkout passes the selected room guest to the FO posting API, the API validates room membership, and new FO bills/service rows retain that guest after refresh. `BookingRoomServiceFolioTest` passed (10 tests, 32 assertions); frontend build passed.



- 2026-08-06: Room settlement now includes an unused room-level deposit with no guest ID when paying a selected guest, aligning backend outstanding calculation with the Checkout deposit total. PHP syntax and BookingRoomServiceFolioTest passed.



- 2026-08-06: Widened the Checkout booking-search dropdown to 420px so booking, room and guest names are readable; frontend build passed.



- 2026-08-06: Removed the intermediate “Đang chuyển bill nhanh” toast so each Checkout operation ends with one final success/error notification; frontend build passed.



- 2026-08-06: Added persisted `service_bill_details.Quantity`, backfilled legacy detail quantities and updated split/detail creation so invoice loading reads stored quantity first. PHP syntax, BookingRoomServiceFolioTest (9/9) and frontend build passed.



- 2026-08-06: Service invoice detail now derives quantity from the linked service row or detail amount/original rate instead of hardcoding quantity 1; frontend build passed.



- 2026-08-06: Removed the temporary frontend reclassification that showed all booking RM bills on Master when the flag was enabled; existing room-owned RM bills remain on their room, and only newly Master-owned bills display on Master. Frontend build passed.



- 2026-08-06: Master Checkout no longer double-counts an RM room-service row when its linked ServiceBill is already present in Master; frontend build passed.



- 2026-08-06: Room-rate Master flag is now prospective: new RM bills follow the current flag, while room-rate updates/adjustments preserve the existing bill owner and never move old Master bills back to rooms when the flag is disabled. PHP syntax and BookingRoomServiceFolioTest passed.



- 2026-08-06: Night Audit room-night bills now clear current room/guest ownership when the booking aggregates room rates to Master, while preserving original room/guest fields; NightAuditTest passed (4 tests, 16 assertions).



- 2026-08-06: Checkout room bill ownership matching now accepts numeric room IDs, room numbers and legacy room descriptions, allowing legacy unpaid bills to appear under the correct room/guest. Frontend build passed.



- 2026-08-06: Added temporary Master unpaid-bill details to the multi-room checkout preview (bill/date/service/room/amount) for manual diagnosis; PHP syntax and frontend build passed. Remove after verification.



- 2026-08-06: Checkout service panel now displays unlinked ServiceBills under their current room/guest owner, while Master continues to show its own bills; duplicate linked service rows are suppressed. Frontend build passed.



- 2026-08-06: Chọn Master trong Checkout giờ luôn bỏ chọn các checkbox phòng còn sót và gọi luồng checkout toàn bộ phòng In-House của booking; frontend build passed.



- 2026-08-06: Checkout settlement now submits selected ServiceBill IDs and scopes amount validation/update to the selected Master/room/guest ownership. Backend test and frontend build passed.



- 2026-08-05: Positioned the conditional Checkout debt-settlement action immediately below Filter and aligned the debt-settlement modal to the approved legacy layout: preloaded payment details, two-column form with a right-side description field, history table, and Close/Print/Add footer. Frontend build passed.



- 2026-08-05: Implemented Checkout per-line debt settlement for a selected `AC` payment: the conditional action opens a history/form modal, `payment_debt_settlements` stores individual settlements with soft deletion, and the API enforces the remaining amount in a database transaction. PHP syntax checks, payment route listing and frontend build passed.



- 2026-08-05: Restored the seeded `AC` (Công nợ) payment method in the Checkout Folio payment modal by excluding only payment group `5` (Miễn phí). Frontend `npm.cmd run build` passed.



- 2026-08-04: Room-rate adjustment now supports individually selected eligible nights or a date range; registration-rate updates can target the selected room or the entire booking. PHP syntax check and frontend build passed.

- 2026-08-04: Widened the room-rate adjustment dialog, made eligible nights visible for direct selection, and replaced the two room-area dropdowns with one room selector plus a whole-booking update checkbox. Frontend build passed.

- 2026-08-04: Checkout refreshes the system date before opening room-rate adjustment, preventing UI-selected nights that the API would reject. Frontend build passed.

- 2026-08-04: Moved the whole-booking rate-update choice into the room section, while retaining the selected room as the audited room-charge target. Frontend build passed.

- 2026-08-04: Aligned room-rate adjustment date validation with the system-date endpoint by reading `system_date_rolls` directly; rejected requests now include the effective stay and system dates. PHP syntax check and `CheckoutBusinessRulesTest` (9 tests) passed.

- 2026-08-04: Room-rate adjustment reloads stay dates after modal, room and system-date data are ready. Frontend build passed.

- 2026-08-04: Room-rate adjustment keeps the actual room stay dates visible and filters invalid/current/future nights before posting. Frontend build passed.

- 2026-08-04: Room-rate adjustment converts UTC room dates to local business dates before display, fixing one-day date shifts. Frontend build passed.

- 2026-08-04: Consolidated `IsAdjustment` into the base `service_bills` migration and removed the follow-up migration for fresh database setup. PHP syntax check and `CheckoutBusinessRulesTest` (9 tests, 31 assertions) passed.



- 2026-08-04: Fixed room rate adjustment modal (`AdjustRoomRateModal.vue`) inline styles so header (`#0788eb`) and blue footer buttons (`Đóng`, `Lưu`) render solidly with white text/icons. Added custom `MM/DD/YYYY` formatted date inputs without browser double calendar icons, matching image 1. Frontend build passed.

- 2026-08-04: Fixed the adjustment dialog width with explicit CSS sizing so its field grid cannot collapse below the legacy layout width.

- 2026-08-04: Refined the room-rate-adjustment modal dimensions, field grid and footer controls to mirror the supplied legacy form.

- 2026-08-04: Room-rate adjustment modal now follows the legacy layout and processes each night in the selected date range with its own audited RM adjustment bill.

- 2026-08-04: Room-rate adjustment no longer checks role permission; the action is visible whenever Checkout Master is selected.

- 2026-08-04: Checkout places the Master-only room-rate adjustment action immediately above the Filter command in the right action bar. Backend adjustment tests (9 tests, 28 assertions) and the frontend build passed.

- 2026-08-04: Implemented Master-only room-rate adjustment. It checks `RoleUserAdjustRoomRate`, room/date/bill eligibility, creates cancelled original/negative audit/new RM bills with `AdjustmentBillId` and `IsAdjustment`, and can update the room rate. `CheckoutBusinessRulesTest`: 9 tests, 28 assertions; frontend build passed.

- 2026-08-04: Aligned room checkout and post-payment completion on the unpaid Master-debt rule. A final room checkout explicitly restores Booking status to Inhouse when Master debt remains. `CheckoutBusinessRulesTest`: 8 tests, 23 assertions passed.

- 2026-08-04: Booking completion now treats an active unpaid bill linked to a checked-out room as Master debt, keeping the booking Inhouse until it is settled. Verified with `CheckoutBusinessRulesTest` (7 tests, 21 assertions) and `frontend/npm.cmd run build`.

- 2026-08-04: Checkout now retains a current Master with no Inhouse rooms for unpaid Master settlement. Room Plan guards closed BroadcastChannel notifications from async callbacks.

- 2026-08-04: Room checkout blocks active unpaid current bills, except RM/RMS when the booking sends room rate to Master. Unpaid room-rate bills keep the Booking Inhouse for Master settlement; unpaid bills are not transferred during checkout.

- 2026-08-04: Checkout Master service total no longer double-counts room-charge bills that were transferred to Master after room checkout.

- 2026-08-04: Checkout history rows now use the legacy pink `#FFD4D4` visual treatment.

- 2026-08-04: Checkout registration filter now applies current, old, virtual and optional departure-date criteria. Old registrations include checked-out Bookings and active Bookings with checked-out rooms; checked-out rows render pink. Frontend build and checkout rules tests passed.

- 2026-08-04: Master settlement now automatically completes a Booking when every room has checked out and no unpaid Master bill or unused deposit remains. Checkout rules tests and frontend build passed.

- 2026-08-04: A Booking with no Inhouse rooms but unpaid Master bills now remains Inhouse so it stays visible in Checkout for settlement. After settlement, Master checkout is allowed without an Inhouse room. Added coverage; checkout rules tests and frontend build passed.

- 2026-08-04: Full room checkout now transfers only unpaid room ServiceBills and linked service rows to Master/Folio 1; paid bill history remains on the checked-out room. The early-checkout prompt hides its duplicate footer actions. Checkout feature tests, restore tests, PHP syntax, and frontend build passed.

- 2026-08-04: Early-checkout Room Charge now lists only dates without an active RM bill; already charged nights never reappear. Added a partial-charge feature test; PHP syntax, Checkout tests and frontend build passed.

- 2026-08-04: Added the early-checkout Room Charge modal: users select remaining dates and a 0–100% charge; the API posts each selected RM date at that rate. PHP syntax, Checkout tests and frontend build passed.

- 2026-08-04: Early checkout now shows the legacy-style choices Close, Checkout and Room Charge. With `AllowEarlyCheckout=1`, Checkout can proceed without remaining-night charges after explicit user selection; Room Charge posts nights only. Added a feature test; Checkout tests, PHP syntax and frontend build passed.

- 2026-08-04: Fixed early-checkout room charges when room rates roll up to Master. Checkout refreshes after charge and merges/deduplicates Master and Booking bill sources, so newly posted RM bills render in Master. Frontend build passed.

- 2026-08-04: Aligned Checkout with business row 62. Master checkout has no confirmation and ignores room departure dates while validating all current Booking bills/deposits. Multi-room checkout previews eligible rooms, requires confirmation for Master debt, then completes eligible rooms. `AllowEarlyCheckout=1` requires remaining RM nights to be posted before room checkout; separate child checkout is supported. Added three feature tests. PHP syntax, routes, Checkout tests and frontend build passed.

- 2026-08-04: Checkout displays Master rows for Booking statuses `0,1`, including GAL2; it renders only Inhouse room rows (`status = 1`). Frontend build passed.

- 2026-08-04: Added Checkout restore for a checked-out room or Master. Room restore is limited to the system date, blocks a room lock or an inhouse reuse, restores only the final checkout guest group, and returns the physical room to occupied; Master restore changes only its booking status. Added four feature tests, PHP syntax/route checks and frontend build passed.

- 2026-08-03: Fixed selected guest state after checkout refresh: if the previous guest no longer exists, Checkout now selects the fresh room primary/first active guest instead of retaining the checked-out guest's name. Frontend build passed.

- 2026-08-03: Fixed Checkout guest display after individual checkout: checked-out guests are no longer reintroduced from the legacy `guest_name` fallback, allowing the next active guest to become the visible primary guest. Frontend build passed.

- 2026-08-03: Fixed room settlement to match ServiceBill ownership by string room IDs (`Gxxxx`), so unpaid services for rooms such as 1009 receive `PaymentId`/paid status correctly. PHP syntax check passed.

- 2026-08-03: Expanded the multi-room Checkout confirmation modal to `max-w-md` with a 320px scrollable room/guest area; frontend build passed.

- 2026-08-03: Multi-room Checkout confirmation now lists each selected room and its guests independently; adult selections are submitted per room and children are shown in the confirmation UI. Frontend build passed.

- 2026-08-03: Checkout panel now starts empty and fills only after a booking is selected from search; clearing/searching does not dump all bookings into the panel. Frontend build passed.

- 2026-08-03: Removed `BookingScenarioSeeder.php` and its `DatabaseSeeder` registration per request; no booking seed data will be recreated automatically.

- 2026-08-03: Removed all runtime data generated by `BookingScenarioSeeder` (`SEED-BOOKING-*`) including linked rooms, guests, children, bills and payments; the seeder file remains available for future test resets.

- 2026-08-03: Checkout now processes all checked rooms in one confirmation flow and synchronizes the left booking list with the search text; frontend build passed.

- 2026-08-03: Checkout validation honors `hotel_configs.AllowEarlyCheckout`: `1` enables the early-checkout charge flow and `0` blocks it; unpaid bills/deposits remain blocked.

- 2026-08-03: Giữ popup bộ lọc Checkout nhưng tạm vô hiệu hóa toàn bộ logic lọc và Áp dụng; các nút chỉ đóng popup để frontend mới tiếp quản.

- 2026-08-03: Added `BookingScenarioSeeder` with 75 idempotent booking scenarios, including virtual/unassigned rooms, mixed room states, multiple guests, children, No Post and Master room-rate cases; all dates are relative to the latest `system_date_rolls.system_date`. PHP syntax checks passed.

- 2026-08-03: Updated Checkout registration filters to use per-room statuses; `Ngày đi ĐK` is unchecked by default, disables date controls until enabled, and applies only after `Áp dụng`.



- 2026-08-03: Added temporary Checkout APIs for room and Master checkout and wired them to the current Checkout page for testing. Primary guest checkout promotes the next active guest; checked-out guests are hidden. Room/Master checkout endpoints remain available for the replacement frontend.

- 2026-08-03: Room-charge posting from Master now excludes rooms not yet arrived, cancelled, or checked out early; room-specific posting rejects cancelled/checked-out rooms.

- 2026-08-03: Fixed F&B DateRangePicker callers to pass `startDate`/`endDate` and use `YYYY-MM-DD` values.

- 2026-08-03: Removed the temporary checkout modal/button integration from the current Checkout page; room/Master checkout APIs and service methods remain available for the replacement frontend.

- 2026-08-03: Aligned checkout APIs with CSV business rules: full-room/Master checkout checks unpaid bills and unused deposits, early room checkout requires charging remaining nights, adult/child constraints are enforced, children are marked checked out on full-room checkout, and the exiting guest's services/payments move to the remaining guest.

- 2026-08-03: Fixed Master room-rate ownership after settlement: RM/RMS stays on Master when `is_master_room_rate=1`, including paid bills, instead of returning to room cards.

- 2026-08-03: Scoped Master settlement updates to Master-owned payments/bills and excluded room deposits/services from the Master payment update; retained active Master services in the summary.

- 2026-08-03: Completed checkout gaps: room checkout closes the Booking when its last active room is checked out, room-scoped unused-deposit validation, and legacy-safe ServiceBill ownership comparisons.



| Date | Change | Files | Verification |

|---|---|---|---|

| 2026-08-03 | Added No Post persistence/API for Booking Master and rooms, linked the Checkout checkbox, and blocked Housekeeping-source bill posting for No Post rooms. | booking/room base migrations, models, No Post controller/routes, Checkout and housekeeping components | PHP syntax, route list and frontend build passed; user will run `migrate:fresh --seed`. |

| 2026-07-30 | Stopped Checkout from adding projected room rates to service totals; new bookings without posted bills now start at zero. | `CheckoutPage.vue`, `PROJECT_MEMORY.md` | `frontend/npm.cmd run build` passed. |

| 2026-07-30 | Added bidirectional Front Desk navigation: Registration Invoice opens Checkout for that booking; the adjacent Refresh button opens Front Desk's `create-res` tab for its registration. | `CreateRegistrationPage.vue`, `CheckoutPage.vue`, `PROJECT_MEMORY.md` | `frontend/npm.cmd run build` passed. |

| 2026-07-30 | Limited Checkout room rows to assigned Reservation/Inhouse rooms and removed display-only synthetic room-charge rows. | `CheckoutPage.vue`, `PROJECT_MEMORY.md` | `frontend/npm.cmd run build` passed. |

| 2026-07-30 | Stopped automatic creation of room-service rows when creating a new booking; the existing booking-update synchronization remains unchanged. | `BookingController.php`, `PROJECT_MEMORY.md` | PHP syntax check passed. |

| 2026-07-30 | Allowed eligible room-charge (`RM`) bills in the service-transfer workflow; service splitting remains blocked for `RM`. | `CheckoutPage.vue`, `BookingRoomServiceController.php`, `PROJECT_MEMORY.md` | PHP syntax check and `frontend/npm.cmd run build` passed. |

| 2026-07-30 | Added the one-card price-adjustment action in the cancellation form, RM form prefill, housekeeping bill form prefill and RM time lookup from the related service bill. | `CheckoutPage.vue`, `CancelServiceModal.vue`, `AddServiceModal.vue`, `AddHousekeepingServiceModal.vue`, `PostBillHousekeepingTab.vue`, `PROJECT_MEMORY.md` | `frontend/npm.cmd run build` passed. |

| 2026-07-30 | Extended service cancellation to retain linked housekeeping bills/details for audit, mark bills cancelled (`Status = 3`, `BillEdit = 1`) and mark details deleted. | `BookingRoomServiceController.php`, `PROJECT_MEMORY.md` | PHP syntax check passed. |

| 2026-07-30 | Enabled audited bulk transfer for selected active unused deposits; one invalid selected deposit rolls back the entire transfer. | `PaymentController.php`, `api.php`, `booking-service.js`, `CheckoutPage.vue`, `PROJECT_MEMORY.md` | Payment controller syntax check, payment route list and frontend build passed. |

| 2026-07-30 | Added hour-aware service/deposit date display and connected select-all checkboxes for visible service rows and active deposits. | `CheckoutPage.vue`, `PROJECT_MEMORY.md` | `frontend/npm.cmd run build` passed. |

| 2026-07-30 | Linked Front Desk Add Service postings to their semantic service-bill and detail records for all configured service codes; RM/RMS room-charge postings now carry the same link. Added an opt-in, uniquely-matched-only command for historic rows. | `BookingRoomServiceController.php`, `BackfillServiceBillLinks.php` | PHP 8.4 syntax checks passed. Scoped local backfill completed only for three uniquely matched GAL2 rows; follow-up dry-run found none remaining. |

| 2026-07-30 | Fixed Checkout room service display and Service Collection with RM: selected rooms render their active service rows; collecting RM to a room disables the booking's Master room-rate flag, while Master renders room charges only when that flag is enabled. | `CheckoutPage.vue`, `BookingRoomServiceController.php` | Frontend build passed. |

| 2026-07-30 | Merged current `main` into `kainning` and resolved the Checkout display conflict. Kept real posted-service rendering, per-guest room filtering, RM transfer behavior and no synthetic room-charge rows. | `CheckoutPage.vue` | PHP syntax and frontend build passed. |

| 2026-07-30 | Removed synthetic RM rows from the Transfer Service destination preview. Rooms with no posted service now show an empty preview. | `CheckoutPage.vue` | `frontend/npm.cmd run build` passed. |

| 2026-07-30 | Preserved the backend RM transfer trail in Checkout descriptions instead of overwriting it with the generic room-charge label. | `CheckoutPage.vue` | `frontend/npm.cmd run build` passed. |

| 2026-07-30 | Restored Master as a Service Collection destination and passed the selected room guest through the collection API, including validation that the guest belongs to the destination room. | `CheckoutPage.vue`, `BookingRoomServiceController.php` | PHP syntax and `frontend/npm.cmd run build` passed. |

| 2026-07-30 | Added room-specific deposit totals to Checkout room rows; replaced the service-post browser alert with the shared toast; added audited unused-deposit transfer to Master or an eligible room with destination deposit preview; coerced Add Service room rate to a numeric prop. | `CheckoutPage.vue`, `TransferPaymentModal.vue`, `PaymentController.php` | Payment controller syntax check, payment route list and frontend build passed. |

| 2026-07-29 | Added Checkout split-deposit flow: selecting one active unused deposit changes the action to split deposit and opens the amount-split form styled like service splitting. The new deposit row may use Folio 1â€“3 while the source row retains its Folio; both retain the source department, original date and creation timestamp. Stale deposit selections clear on refresh or booking/room changes. | `CheckoutPage.vue`, `SplitDepositModal.vue`, `booking-service.js`, `PaymentController.php` | PHP syntax check and `frontend/npm.cmd run build` passed. |

| 2026-07-28 | Created persistent project-memory baseline from repository review. | `AGENTS.md`, `PROJECT_MEMORY.md` | Static repository inspection; runtime Laravel checks blocked by PHP version. |

| 2026-07-28 | Added legacy-clone objective, multi-contributor rules, shared-file approval process and import roadmap. | `AGENTS.md`, `.codex/PROJECT_MEMORY.md` | Documentation update. |

| 2026-07-28 | Reviewed Checkout tab and started frontend local server. Checkout is currently UI-first: its checkout button and payment modals are not wired to an API checkout flow. | `frontend/src/pages/frontdesk/CheckoutPage.vue`, payment modals, payment/booking-room APIs | Frontend HTTP 200 at port 5173; `npm.cmd run build` passed. Backend API could not run: PHP 8.2.12 < required 8.4.1. |

| 2026-07-28 | Diagnosed login 502. | Vite proxy `/api` -> Laravel default port 8000 | Confirmed proxy returns 502; Laravel is unavailable because the local PHP version cannot load installed dependencies. |

| 2026-07-28 | Consolidated initial-schema migrations. Removed duplicate `branches` migration and 13 redundant `Schema::table` migrations because all final columns already exist in their create-table migrations. | `backend/database/migrations/` | Static check: 57 migrations remain; no `Schema::table` migrations or duplicate normalized names. `migrate:fresh --seed` remains blocked before execution by PHP 8.2.12 < required 8.4.1. |

| 2026-07-28 | Updated Checkout service display. Service rows now use selected `service_date`, group housekeeping lines by service type/date/folio, and open a right-side invoice detail drawer. | `frontend/src/pages/frontdesk/CheckoutPage.vue` | `npm.cmd run build` passed. |

| 2026-07-28 | Enforced room-only housekeeping service posting from Checkout and removed housekeeping group prefixes from invoice product names. | `frontend/src/pages/frontdesk/CheckoutPage.vue` | `npm.cmd run build` passed. |

| 2026-07-28 | Updated Checkout service grouping so each posted service batch has its own row/card; products posted together remain in one invoice detail drawer. | `frontend/src/pages/frontdesk/CheckoutPage.vue` | `npm.cmd run build` passed. |

| 2026-07-28 | Corrected Checkout master-row totals and service timestamps. Master no longer aggregates room services; service rows show posting date plus creation time. | `frontend/src/pages/frontdesk/CheckoutPage.vue` | `npm.cmd run build` passed. |

| 2026-07-28 | Added per-room service Folio support. New services default to Folio 1; A shows all services of the selected room; 1/2/3 filter by Folio; selected unposted service cards can be moved between Folios. | migration, `BookingRoomService`, `BookingRoomServiceController`, API route, `booking-service.js`, Checkout and Transfer modal | PHP syntax checks and `npm.cmd run build` passed. Full API/migration run remains blocked by PHP 8.2.12 < required 8.4.1. |

| 2026-07-28 | Moved the persisted room-rate-to-master option to Registration. Checkout now reads `bookings.is_master_room_rate`: enabled puts only room charges on Master; disabled keeps each room charge with its room. Removed the Checkout registration checkbox and service-transfer controls; service selection checkboxes remain. | `CreateRegistrationPage.vue`, `CheckoutPage.vue`, `BookingController.php` | PHP syntax check and frontend build passed. Full API verification remains blocked by PHP 8.2.12 < required 8.4.1. |

| 2026-07-28 | Corrected Master detail display: selecting Master now shows only the room-charge (`RM`) lines for all rooms when room rates are sent to Master. | `frontend/src/pages/frontdesk/CheckoutPage.vue` | `npm.cmd run build` passed. |

| 2026-07-28 | Added drag-and-drop Folio movement for room-service rows. Dropping a service row onto Folio 1/2/3 updates the database through the existing API; dropping on its current Folio does nothing. Guest names in the Checkout room list use a darker, bolder color. | `frontend/src/pages/frontdesk/CheckoutPage.vue` | `npm.cmd run build` passed. Full API verification remains blocked by PHP 8.2.12 < required 8.4.1. |

| 2026-07-28 | Refined Checkout guest emphasis: only the first guest of a room is bold; additional guests use normal weight. | `frontend/src/pages/frontdesk/CheckoutPage.vue` | `npm.cmd run build` passed. |

| 2026-07-28 | Applied the same primary/secondary guest emphasis to the Checkout search dropdown. | `frontend/src/pages/frontdesk/CheckoutPage.vue` | `npm.cmd run build` passed. |

| 2026-07-28 | Increased Checkout primary and secondary guest-name weight to bold. | `frontend/src/pages/frontdesk/CheckoutPage.vue` | `npm.cmd run build` passed. |

| 2026-07-28 | Read and documented the legacy SP3000 service-bill schema and posting rules: service date versus creation time, folio/payment, original/current ownership, transfer/cancellation negative lines, outlet, VAT and unused fields. | `old_database_struct/db_schema/ProVistaDTXHotel/tables/SP3000.md`, `.codex/PROJECT_MEMORY.md` | Static schema review; no runtime changes. |

| 2026-07-28 | Implemented the first housekeeping-posting legacy persistence slice: room selector is readonly, future dates are rejected, old-day permission is checked, department is forced to HK, each product group creates one SP3000/SP6000 bill with SP3001/SP6001 details, room/customer/company links and product discount/increase fields, and posted services are marked `is_posted=1`. | `AddHousekeepingServiceModal.vue`, `PostBillHousekeepingTab.vue`, `BookingRoomServiceController.php`, new `Sp3000/Sp3001/Sp6000/Sp6001` models and legacy-bill migration | PHP syntax checks passed; frontend `npm.cmd run build` passed. Runtime migration/API verification remains blocked by PHP 8.2.12 < required 8.4.1. |

| 2026-07-29 | Renamed the housekeeping legacy-bill models to business names while retaining legacy table names for import compatibility: `ServiceBill` (`sp3000`), `ServiceBillDetail` (`sp3001`), `HousekeepingServiceBill` (`sp6000`), and `HousekeepingServiceBillDetail` (`sp6001`). Corrected their PHP namespaces/imports and updated the housekeeping posting controller. | `backend/app/Models/`, `BookingRoomServiceController.php` | `php -l` passed for all renamed models and the controller. |

| 2026-07-29 | Corrected the model/table approach: legacy `SP*` tables are reference only. Renamed the migration and created new-system bill tables: `service_bills`, `service_bill_details`, `housekeeping_service_bills`, and `housekeeping_service_bill_details`. | `backend/app/Models/`, `BookingRoomServiceController.php`, `2026_07_28_160001_create_service_bills_tables.php` | PHP 8.4.22: migration completed in batch 2; direct database check confirmed all four new tables and no `sp3000/sp3001/sp6000/sp6001` tables. PHP syntax checks passed. |

| 2026-07-29 | Separated housekeeping service posting by guest within a room. The posting form sends a selected `guest_id`; the API verifies room membership and stores it in room-service and housekeeping-bill records. Checkout filters new guest-linked services by the selected guest. | `PostBillHousekeepingTab.vue`, `CheckoutPage.vue`, `BookingRoomServiceController.php`, `BookingRoomService.php`, `HousekeepingServiceBill.php`, `2026_07_29_090000_add_guest_to_room_services_and_housekeeping_bills.php` | PHP syntax checks passed; migration completed; `npm.cmd run build` and `artisan route:list --path=api/booking-room-services` passed with PHP 8.4.22. |

| 2026-07-29 | Fixed Checkout-to-housekeeping handoff: the currently selected guest ID is now passed into the housekeeping modal and retained for the post, preventing the modal from falling back to the room’s primary guest. | `CheckoutPage.vue`, `AddHousekeepingServiceModal.vue`, `PostBillHousekeepingTab.vue` | `npm.cmd run build` passed. |

| 2026-07-29 | Fixed post-success refresh resetting the selected guest to the room primary guest. Checkout now snapshots and restores the selected guest ID/name after reloading booking data. | `frontend/src/pages/frontdesk/CheckoutPage.vue` | `npm.cmd run build` passed. |

| 2026-07-29 | Added Checkout split/transfer service controls. Buttons activate only when selected rows are non-room services; split supports double, triple and amount modes, and transfer moves selected services to a target Folio. Added split API and Folio-aware uniqueness for cloned service rows. | `CheckoutPage.vue`, `SplitServiceModal.vue`, `TransferServiceModal.vue`, `BookingRoomServiceController.php`, `routes/api.php`, `booking-service.js`, service migrations | PHP syntax checks, frontend build, migrations and `artisan route:list --path=api/booking-rooms` passed with PHP 8.4.22. |

| 2026-07-29 | Initial source-Folio exclusion was superseded: the source Folio is now selectable because a split always creates a separate row. | `CheckoutPage.vue`, `SplitServiceModal.vue` | Frontend `npm.cmd run build` passed. |

| 2026-07-29 | Corrected amount-based service splitting. The target amount is allocated with a final residual line and split/source rates are recalculated for the two-decimal quantity constraint, preserving the requested Folio amount and overall service total. | `BookingRoomServiceController.php` | PHP syntax check and frontend build passed. |

| 2026-07-29 | Changed split behavior per business feedback: every split creates a separate service row, without merging with an existing row; source Folio is selectable as a target. Removed the room-service uniqueness constraint that prevented same-Folio split rows. | `SplitServiceModal.vue`, `CheckoutPage.vue`, `BookingRoomServiceController.php`, service migrations | PHP syntax check, frontend build and migration `2026_07_29_110000_allow_split_service_rows_in_same_folio` passed. |

| 2026-07-29 | Rounded Checkout summary-only service amounts to whole VND; the invoice detail drawer retains fractional precision. | `CheckoutPage.vue` | `npm.cmd run build` passed. |

| 2026-07-29 | Fixed even Folio splitting to retain two-decimal quantity residuals (for example, `1` split three ways becomes `0.34 + 0.33 + 0.33`). Invoice detail now displays exact quantity/rate/amount decimals. Rebalanced the verified room-112 test rows from `227,700` to `230,000`. | `BookingRoomServiceController.php`, `CheckoutPage.vue`, runtime test data | PHP syntax check and frontend build passed; room 112 total queried as `230,000`. |

| 2026-07-29 | Increased room-service quantity precision from 2 to 6 decimals. Amount-based splitting now keeps the original product rate and persists the exact requested amount without rate adjustment; invoice detail displays quantity through 6 decimals. | `BookingRoomService.php`, `BookingRoomServiceController.php`, `CheckoutPage.vue`, service migration | Migration ran; PHP syntax check and frontend build passed. Rollback-only test split `70,200` at rate `172,500` exactly. |

| 2026-07-29 | Made the same-Folio split migration a no-op for fresh installs because the base room-service migration no longer creates the removed unique index. | `2026_07_29_110000_allow_split_service_rows_in_same_folio.php` | PHP syntax check passed. |

| 2026-07-29 | Rebuilt service splitting around the current semantic bill tables. Only amount splitting remains; it preserves product rates, updates the source service bill/details, inserts the split bill/details, retains original business/create dates, and blocks room, paid, VAT, incomplete or cross-bill selections. Added explicit bill/detail links to new housekeeping postings. | `SplitServiceModal.vue`, `CheckoutPage.vue`, `BookingRoomServiceController.php`, service-bill models/migration | Migration `2026_07_29_130000_add_service_bill_split_tracking` ran; PHP syntax, API route list and frontend build passed. |

| 2026-07-29 | Replaced Folio-only service transfer with destination booking/room selection. The backend checks unpaid source bills, creates a current positive bill at the destination plus a negative offset bill, marks the source transferred, preserves original ownership and carries service details forward. | `TransferServiceModal.vue`, `CheckoutPage.vue`, `BookingRoomServiceController.php` | PHP syntax, API route list and frontend build passed. |

| 2026-07-29 | Adjusted the service-transfer form: source guest is read-only, destination starts unselected, and selected service rows appear only after a destination booking/room is chosen. | `TransferServiceModal.vue` | Frontend build passed. |

| 2026-07-29 | Changed service-transfer preview to show the existing services of the selected destination booking/room instead of the source services selected for transfer. | `CheckoutPage.vue`, `TransferServiceModal.vue` | Frontend build passed. |

| 2026-07-29 | Verified the transfer endpoint in a rollback-only transaction: an unpaid, fully selected 100,000 service bill transfers from room 110 to room 111 successfully. Added inline API error feedback to the transfer form. | `BookingRoomServiceController.php`, `CheckoutPage.vue`, `TransferServiceModal.vue` | Rollback-only API test and frontend build passed. |

| 2026-07-29 | Corrected transfer destination preview: room selections render grouped service-bill rows and Master renders only the displayed room-charge (`RM`) rows. | `CheckoutPage.vue` | `npm.cmd run build` passed. |

| 2026-07-29 | Fixed transfer destination IDs and added system toast/loading feedback plus hover/active states for Checkout service split/transfer actions. | `CheckoutPage.vue`, `TransferServiceModal.vue`, `SplitServiceModal.vue` | `npm.cmd run build` passed. |

| 2026-07-29 | Made the transfer destination picker searchable and aligned its dropdown style with Checkout search. Transfer descriptions now append source/destination locations and Checkout uses bill descriptions rather than the first product name. | `CheckoutPage.vue`, `TransferServiceModal.vue`, `BookingRoomServiceController.php` | PHP syntax check and frontend build passed. |

| 2026-07-29 | Restored drag-to-Folio handling on the shared service endpoint and changed the transfer destination list to booking-to-room hierarchy. | `BookingRoomServiceController.php`, `CheckoutPage.vue`, `TransferServiceModal.vue` | PHP syntax check and frontend build passed. |

| 2026-07-29 | Restricted service transfer source/destination to Reservation or Inhouse rooms and bookings in both the API and Checkout destination list. | `BookingRoomServiceController.php`, `CheckoutPage.vue` | PHP syntax check and frontend build passed. |

| 2026-07-29 | Fixed Checkout guest search for structured guest objects and reset room-to-room transferred services to destination Folio 1. | `CheckoutPage.vue`, `BookingRoomServiceController.php` | PHP syntax check and frontend build passed. |

| 2026-07-29 | Implemented Checkout Tập hợp DV/Chuyển bill nhanh: same-booking unpaid bill candidates, grouping, selection and transfer to the selected room Folio 1. | `QuickTransferBillModal.vue`, `CheckoutPage.vue`, `booking-service.js`, API routes/controller | PHP syntax, route list and frontend build passed. |

| 2026-07-29 | Adjusted the Quick Transfer Bill modal table structure and sizing to match the legacy reference: separate expand/selection columns, group rows and compact room/guest rows. | `QuickTransferBillModal.vue` | Frontend build passed. |

| 2026-07-29 | Restored display of active service bills transferred to the booking Master; Booking API loads master-owned service bills and Checkout renders them alongside Master room charges. | `Booking.php`, `BookingController.php`, `CheckoutPage.vue` | PHP 8.4 syntax checks passed; `frontend/npm.cmd run build` passed. |

| 2026-07-29 | Added Checkout split-deposit flow: selecting one active unused deposit changes the action to split deposit and opens the amount-split form styled like service splitting. The new deposit row may use Folio 1–3 while the source row retains its Folio; both retain the source department, original date and creation timestamp. Stale deposit selections clear on refresh or booking/room changes. | `CheckoutPage.vue`, `SplitDepositModal.vue`, `booking-service.js`, `PaymentController.php` | PHP syntax check and `frontend/npm.cmd run build` passed. |

| 2026-07-29 | Fixed empty Quick Transfer candidates when selecting a room after bills had been collected to Master. The API now includes same-booking, unpaid Master bills and can transfer them back to the selected room with Folio 1 room-service rows. | `BookingRoomServiceController.php` | PHP 8.4 syntax check passed; local candidate API returned 8 Master bills; rollback-only transfer test passed. |

| 2026-07-29 | Changed Checkout service-card grouping so collected bills are presented as one card per service category and transfer-source room, Folio and department; collecting MB/LA/BR from three rooms therefore shows nine cards rather than three. Bill records remain separate for audit. | `CheckoutPage.vue` | `frontend/npm.cmd run build` passed. |

| 2026-07-29 | Preserved original service-use/posting timestamps when transferring or collecting services; only update timestamps change during the operation. | `BookingRoomServiceController.php` | PHP 8.4 syntax check passed; rollback-only Master-to-room transfer retained `service_date` and `created_at`. |

| 2026-07-29 | Consolidated housekeeping-service follow-up schema changes into the original room-service, guest and service-bill migrations; removed five redundant/no-op 29/07 migrations. Guest FK creation remains ordered after the `guests` table is created. | `2026_07_08_100001_create_booking_room_services_table.php`, `2026_07_08_100006_create_guest_tables.php`, `2026_07_28_160001_create_service_bills_tables.php`, removed five 29/07 migration files | PHP 8.4 syntax checks passed. `migrate:fresh --seed --pretend` is unsupported by this Laravel version; no destructive migration was run. |

| 2026-07-29 | Fixed service-card grouping after Master-to-room collection: grouping now uses the first/original transfer source rather than the intermediate Master location, preserving separate cards for each originating room. | `CheckoutPage.vue` | `frontend/npm.cmd run build` passed. |

| 2026-07-29 | Fixed Master summary total after service collection by adding active Master-owned service bills to its total service amount. | `CheckoutPage.vue` | `frontend/npm.cmd run build` passed. |

| 2026-07-29 | Added visible in-form loading feedback and an in-progress toast for Quick Transfer Bill; existing success/error toasts remain after the operation. | `CheckoutPage.vue`, `QuickTransferBillModal.vue` | `frontend/npm.cmd run build` passed. |

| 2026-07-29 | Replaced the Quick Transfer Bill custom spinner with the shared `LoadingOverlay` component. | `QuickTransferBillModal.vue` | `frontend/npm.cmd run build` passed. |

| 2026-07-29 | Implemented audited Checkout service cancellation: required reason, unpaid/non-VAT/non-room/old-day permission checks, negative service-bill record, cancelled status and deletion of original service-bill details. | `CheckoutPage.vue`, `CancelServiceModal.vue`, `booking-service.js`, `api.php`, `BookingRoomServiceController.php` | PHP syntax check and `frontend/npm.cmd run build` passed. |

| 2026-07-29 | Set registration/Master deposits to Folio 1 and updated Checkout payment/Folio calculations: paid amount is active deposit total, Folios 1–3 deduct their own deposits, and Folio A sums the calculated Folios. | `BookingController.php`, `PaymentController.php`, `CheckoutPage.vue` | PHP syntax checks and `frontend/npm.cmd run build` passed. |

| 2026-07-29 | Hardened the Service Collection button and handler against stale room selection when no room is visibly selected. | `CheckoutPage.vue` | `frontend/npm.cmd run build` passed. |

| 2026-07-29 | Added a visible disabled style to Service Collection when no room is currently selected. | `CheckoutPage.vue` | `frontend/npm.cmd run build` passed. |

| 2026-07-29 | Left the payment-table Xóa column blank for active deposits; only deleted records retain a deletion marker. | `CheckoutPage.vue` | `frontend/npm.cmd run build` passed. |

| 2026-07-30 | Fixed Checkout search rendering when "Xem tất cả khách trong phòng" is enabled: the search list now displays each adult guest name instead of serializing the guest object. | `CheckoutPage.vue` | `frontend/npm.cmd run build` passed. |

| 2026-07-30 | Transfer-service destinations now show only a room's primary adult guest by default; enabling "Xem tất cả khách trong phòng" adds each secondary adult guest and preserves the selected destination guest. | `CheckoutPage.vue` | `frontend/npm.cmd run build` passed. |

| 2026-07-30 | Corrected Checkout all-guest totals and Master service display: guest rows calculate their own service/payment totals, and Master filters service bills by current Master ownership instead of original room ownership. | `CheckoutPage.vue` | `frontend/npm.cmd run build` passed. |

| 2026-07-30 | Fixed Quick Transfer to a secondary guest: guest IDs are string keys, so the endpoint now validates and looks them up as strings rather than coercing them to integers. | `BookingRoomServiceController.php` | PHP syntax check and `frontend/npm.cmd run build` passed. |

| 2026-07-30 | Corrected the Master summary calculation to include active bills by current Master ownership (`RegisterID2` with no `RentalRoomId2`), rather than excluding bills that originated in a room. | `CheckoutPage.vue` | `frontend/npm.cmd run build` passed. |

| 2026-07-30 | Corrected Master-to-room Quick Transfer: it now creates one room-service row from the bill header, retaining invoice details without turning breakfast or adjustment details into extra room services. | `BookingRoomServiceController.php` | PHP syntax check and `frontend/npm.cmd run build` passed. |

| 2026-07-30 | Posting or updating a room-charge (`RM`) now respects the booking's Master-room-rate setting: the current bill owner becomes Master, and its room display row is removed to prevent duplicate Checkout totals. Original room ownership remains on the bill. | `BookingRoomServiceController.php` | PHP syntax check and `frontend/npm.cmd run build` passed. |

| 2026-07-30 | Fixed Master-to-room transfer classification: room-service code now uses the service bill's `ServiceId` (`RM`) rather than its `Outlet` (`FO`). | `BookingRoomServiceController.php` | PHP syntax check and `frontend/npm.cmd run build` passed. |

| 2026-07-31 | Upgraded Checkout Advance Payment modal (`PrepaymentModal.vue`): widened container to `max-w-5xl` for spacious layout, added automatic currency thousand separators formatter to "Số tiền" input (`900,000`), adjusted spacing and padding for shift/time/date/currency fields to prevent overlap, and maintained dynamic database dropdown binding. | `PrepaymentModal.vue`, `CheckoutPage.vue`, `PaymentController.php` | PHP syntax check, `frontend/npm run build` passed. |

# 2026-08-05: Aligned the Checkout debt-settlement modal with the legacy layout; frontend build passed.

# 2026-08-05: Centered and widened the debt-settlement modal; standardized settlement history date/time display.

# 2026-08-05: Replaced the browser confirmation dialog for debt-settlement deletion with an internal PMS-styled confirmation modal.

# 2026-08-05: Debt settlement now formats the input amount as VND and closes on successful creation.

# 2026-08-05: Hardened debt settlement validation for active payment methods/debt records, applied old-day permission checks, normalized the legacy-compatible `HH:mm` time field, and added four API feature tests.

# 2026-08-05: Debt-settlement modal now remains open after add/delete and refreshes its current data.

# 2026-08-05: Debt-settlement add/delete now uses the shared Checkout success toast and keeps the modal open.

# 2026-08-05: Added the shared loading overlay to debt-settlement load, create, and delete operations.

# 2026-08-05: Enforced old-system-date permission on debt-settlement create/delete and added receipt printing from the debt-settlement modal. PHP syntax and frontend build passed.

# 2026-08-06: Checkout Folio drag transfers only the dragged service group. The API accepts only service rows belonging to the selected room and derives linked service bills from those rows, preventing cross-room/guest updates caused by mixed identifiers.

# 2026-08-06: Front Desk deposit creation uses pack2=DPR without pack4=AP. The deposit modal lists active DPR rows only, and Checkout uses deposit-specific action labels.

# 2026-08-06: Checkout invoice popup now loads all `service_bill_details` through a read-only endpoint, so services transferred to another room retain their complete invoice lines; legacy bills without details keep the summary fallback.

# 2026-08-06: Service-transfer preview loads bills by current room ownership (`RentalRoomId2`), scopes Master/room/guest correctly, and marks paid preview rows pink.

# 2026-08-06: Service-transfer preview normalizes legacy/new bill field names. Unpaid rows are white with gray hover; paid rows alone are pink.

# 2026-08-06: Fixed a second preview-normalization pass that was clearing bill date/time and user values.

# 2026-08-06: Housekeeping bill adjustment now synchronizes complimentary payment state; service transfer rejects a paid housekeeping bill even when the linked ServiceBill lacks a payment code.

# 2026-08-06: Checkout service payment display now resolves `current_service_bills`, including a complimentary bill moved to its current room.

# 2026-08-06: Checkout prevents selection, cancellation, transfer, and splitting of paid service bills; paid payment rows retain only payment-deletion workflow.

# 2026-08-06: Housekeeping price adjustment now loads service-bill detail rows, preserving product names, original rates, and quantities after complimentary settlement.

# 2026-08-06: Master automatic room-charge posting now creates RM bills only for Inhouse rooms with assigned room numbers; individual-room posting and other Master modes retain their existing rules.

# 2026-08-06: Checkout separates Advance Payment (`pack4=AP`) from deposits (`pack2=DPR`), scopes room guest totals by current bill/payment ownership, and persists Master room-rate bills with null current room/guest ownership.

# 2026-08-06: Corrected Master Tổng DV to include all displayed RM service rows exactly once, including legacy room-service rows whose bill link is not present in the Master relation.

# 2026-08-06: Master settlement now scopes unpaid bills to current Master ownership only; Advance Payment cards are filtered by selected Master/room/guest ownership.

# 2026-08-06: Quick Transfer candidate loading now includes unpaid services from other guests in the selected target room while excluding the target guest's own services; candidate names use each service's guest owner.

# 2026-08-06: Quick Transfer now permits moving an unpaid service bill between different guests in the same room and still rejects bills belonging to the target guest.

# 2026-08-06: Master service bills now support unpaid split, folio transfer, deletion and room/guest transfer using ServiceBill.Ma; paid Master bills remain blocked.

# 2026-08-06: Housekeeping price-adjustment form now loads quantity, unit rate and total amount directly from service_bill_details instead of defaulting quantity to 1.

# 2026-08-06: Complimentary housekeeping services now persist 100% discount plus original rate/quantity; after deleting the complimentary payment, reopening and disabling Free restores the original bill amount.

# 2026-08-06: Room-rate adjustment now allows the current system date (including a same-day 16/07–16/07 range); future dates remain blocked, and permission checks remain bypassed as requested.

# 2026-08-06: Direct Master checkout now opens the confirmation modal so unpaid bill/deposit API errors are visible; room/guest checkout timestamps continue to use PMS system_date.

# 2026-08-06: Full room checkout now updates booking_rooms.departure_date to PMS system_date; partial guest checkout keeps the original departure date.

# 2026-08-06: Checkout multi-room confirmation now hides detailed bill/error lists and shows concise room statuses and aggregate results.

# 2026-08-07: Combined separate room and guest dropdowns in Housekeeping Add Service (`PostBillHousekeepingTab.vue`) into a single searchable room-guest dropdown styled as `BookingCode · RoomNo · GuestName`.

# 2026-08-07: Checkout now blocks room/Master checkout while an active AC debt payment has a remaining balance after valid debt settlements; zero balance permits checkout. Added CheckoutBusinessRulesTest coverage.

# 2026-08-07: Successful Checkout Folio payment now shows one success toast from the parent flow and clears the selected booking, room, guest, Folio, service/payment selections and panel data after refresh.

# 2026-08-07: Checkout search now renders secondary guests by `guest.name` when “Xem tất cả khách trong phòng” is enabled and passes the full guest object on selection, preventing object JSON from appearing in the search dropdown.

# 2026-08-07: Room Plan line 98 now displays Booking notes, aggregates the gray billing group across all Booking rooms, separates DPR deposits from Booking totals, shows remaining as total Booking minus deposits, and marks rooms with valid deposits using a coin icon. Frontend build passed.

# 2026-08-07: Room Plan financial totals now exclude manually posted Front Desk invoice services (`is_posted=1` or linked bills); only configured Booking services, room charges, Extra Bed and extra-charge child breakfast are included. Frontend build and BookingController PHP syntax passed.

# 2026-08-07: Room Plan now uses a room's actual `CheckoutDate` when calculating nights after early checkout, while Booking totals still aggregate every room and all active DPR deposits. Frontend build passed.

# 2026-08-07: Room Plan deposit totals now exclude Advance Payment (`pack4=AP`) and deposit rows already used for bill payment (`payment_id`); only active DPR deposits from rooms or Booking Master are included.

# 2026-08-07: Room Plan now excludes `[TRANSFER IN ...]` deposit rows created by Checkout transfers; the Booking tooltip deposit total uses only Booking-originated DPR deposits attached to that Booking Master or its rooms.

# 2026-08-07: Room Plan now requests Booking billing relations so DPR deposits created in Tạo đăng ký are available to the tooltip deposit calculation.

# 2026-08-07: Room Plan tooltip gray summary now uses the hovered room's room/service/total amounts; only the lower Booking summary remains aggregated across all rooms in the Booking.

# 2026-08-12: Housekeeping post-bill now filters products by legacy OpenKey=1 and caps the UI service date at PMS system_date; invoice search continues to use API data. Frontend build and PHP syntax checks passed.

# 2026-08-12: Frontdesk Checkout Booking summary `Tổng DV` now sums all active service bills and unposted room-service rows for the Booking without double-counting linked rows. Frontend build passed.

# 2026-08-12: Fixed empty HK post-bill product lists after OpenKey filtering: HK menu product writes and `MenuProductSeeder` now persist `open_key=1` for HK products. Housekeeping outlet schema cleanup and existing-data backfill are consolidated in the create-outlets migration.

# 2026-08-12: Added `Mở bán (OpenKey)` checkbox to the HK product create/edit form; new products default to enabled, while existing values can be toggled and saved.

# 2026-08-12: Reservation child-breakfast surcharge display now uses a hyphen before the child name, and its quantity is read-only in the registration service table. Frontend build passed.

# 2026-08-12: Expanded HotelDefinitionSeeder with the requested hotel-service catalog, including RM, and changed seeding to updateOrCreate by service code to avoid duplicates when reseeding. PHP syntax and diff checks passed.

# 2026-08-12: Standardized room-service metadata through the hotel-services catalog and changed room-charge postings and runtime filtering to use catalog code ER; RMS is no longer recognized. Frontend build, PHP syntax checks, and BookingRoomServiceFolioTest passed.

# 2026-08-12: Room Plan now classifies catalog code ER as room-charge revenue alongside RM, preventing additional room charges from being counted as generic services.

# 2026-08-12: Registration setup services now default to GIT/Master (`is_room=0`), persist department FO, post to Master or room according to `is_room`, and Checkout strips trailing quantity suffixes such as `(1)` from bill descriptions.

# 2026-08-12: Fixed Checkout render error by moving `stripTrailingQuantitySuffix` to component scope so transfer-preview bill mapping can access it.

# 2026-08-12: Checkout room-service filtering now respects `booking_room_services.is_room` for all setup services, so EB/GIT (`is_room=0`) is shown only at Master instead of the source room.

# 2026-08-12: Checkout room totals now exclude GIT setup services (`is_room=0`), display cleanup removes trailing `(1)`, and surcharge room-charge bills now use catalog code ER and catalog description with the source room.

# 2026-08-12: Fixed GAL2 room summary double-counting: Master-owned room-charge bills (`RentalRoomId2` empty) and GIT services are excluded from room totals; legacy room-owned bills remain supported.

# 2026-08-12: Checkout now normalizes `is_room`/`isRoom` before calculating room and guest totals, excludes Master-owned bills from room guest totals, and strips trailing quantity suffixes from invoice detail names.

# 2026-08-12: Checkout service rows no longer append grouped-row counts such as `(1)` to descriptions; Master-owned room bills and non-Master setup services are excluded from room/Master summary cross-counting.

# 2026-08-12: Checkout now determines Master ownership from the `master_service_bills` relation by bill ID before applying legacy ID fallbacks, preventing Master bills from reappearing in room `Tổng DV` values.

# 2026-08-12: Corrected Checkout ownership detection so bills in `master_service_bills` with a non-empty `RentalRoomId2` remain room-owned and appear in the selected room right panel; room service display no longer depends on the booking-level master-rate flag.

# 2026-08-12: Checkout room selection now shows all services for the room by default; guest-level filtering is applied only when a specific guest row is selected.

# 2026-08-12: Checkout room totals and right-panel services now include room-level bill relations (`service_bills`/`current_service_bills`), exclude `is_room=0` setup services from room totals, and retain FIT bills such as BD/EB for the owning room.

# 2026-08-18: Nhận phòng chuẩn hóa `booking_room.status` bằng `Number(...)` khi lọc, khắc phục trường hợp Sơ đồ phòng có phòng nhưng danh sách “Phòng chưa đến/đã đến” hiển thị 0 do API trả trạng thái dạng chuỗi.

# 2026-08-18: Nhận phòng chuẩn hóa ngày ISO có múi giờ theo giờ địa phương PMS; API trả `2026-08-09T00:00:00+07:00` dưới dạng UTC nên frontend không còn loại nhầm phòng đến ngày đã chọn.

# 2026-08-18: Bộ lọc phòng đã đi chuyển ngày hệ thống PMS thành khoảng UTC khi truy vấn để lấy đúng dữ liệu checkout.

# 2026-08-18: Room Map reload danh sách khi chuyển display mode; các cột tài chính chỉ hiển thị ở hai nhóm của màn hình “Đã đi”.

# 2026-08-18: Danh sách phòng chưa trả tại Lễ tân có nút Hóa đơn truyền booking/phòng sang Checkout; Đăng ký và HK chỉ xem, không có thao tác chọn/xử lý.

# 2026-08-18: Room Map reset bộ lọc và chế độ danh sách khi chuyển module, tránh giữ bộ lọc “Đã đi” trên module Đặt phòng.

# 2026-08-18: Tính tiền phòng trên danh sách Đã đi/Chưa trả loại các service bill đã chuyển ownership về Master (`RegisterID2`, không còn `RentalRoomId2`).

# 2026-08-18: Dòng Master cộng riêng các bill Master; dòng phòng chỉ cộng bill thuộc phòng, tổng booking cộng hai nhóm và khử trùng bill.

# 2026-08-18: Checkout xác định bill Master theo ownership hiện tại (`RegisterID2`, `RentalRoomId2`, `CustomerId2`); bill tiền phòng gửi Master vẫn có thể giữ `RentalRoomId1` là phòng nguồn.

# 2026-08-18: Checkout không hiển thị bill đã tập hợp ở cả phòng nguồn và phòng đích; khi có `RentalRoomId2`, ownership hiện tại chỉ là phòng đích.

# 2026-08-18: Danh sách Đã đi/Chưa trả lọc tổng tiền từng phòng theo owner hiện tại của bill; bill có `RentalRoomId2` không còn bị tính lại cho `RentalRoomId1`.

# 2026-08-18: Dòng Master trong danh sách Đã đi/Chưa trả chỉ hiển thị bill Master trực tiếp; tổng tiền các phòng không còn cộng vào dòng Master.

# 2026-08-18: Booking chỉ hiển thị số phòng trong danh sách/dropdown; ẩn cột trạng thái phòng và bỏ hậu tố trạng thái khỏi nhãn phòng, giữ nguyên dữ liệu nội bộ.

# 2026-08-19: DÃ²ng group trong mÃ n hÃ¬nh Tạo đăng ký Ä‘Ã£ Ã¡p mÃ u theo yÃªu cáº§u: Äang á»Ÿ xanh dÆ°Æ¡ng, PhÃ²ng Ä‘i/PhÃ²ng chuyá»ƒn xÃ¡m, Hủy hồng; cÃ¡c group khÃ¡c giá»¯ nguyÃªn.

# 2026-08-19: Tạo đăng ký khôi phục bản sao giao diện khi API lưu thất bại; chỉ xóa backup sau khi lưu thành công, tránh hiển thị dữ liệu chưa được lưu.

# 2026-08-19: Phần rollback khi lưu thất bại đã được hoàn tác theo yêu cầu; hành vi hiện tại giữ nguyên như trước.

# 2026-08-19: Dòng 120: Tạo đăng ký kiểm tra trước khoảng ngày phòng so với Booking và khôi phục snapshot giao diện khi cập nhật thất bại; ngày hệ thống và quy tắc day-use vẫn do backend xác thực.

# 2026-08-19: Room Plan cải thiện auto-scroll khi kéo lên, đánh dấu vùng ngày lưu trú ban đầu khi resize, dùng nền vàng nhạt cho các dòng tổng hợp; thống kê OCC/AV giữ rule is_availability và không dùng fallback tổng phòng 131 khi dữ liệu chưa tải.

# 2026-08-21: Dòng 146 — Create Registration chỉ duy trì một tab Booking; khi chọn Booking khác, tab hiện tại được thay thế thay vì mở thêm tab. Màu chữ tab dùng biến theme, trắng trên nền tối và đen trên nền sáng. Không thay đổi API/database.

# 2026-08-21: Sau khi tạo Booking mới, Create Registration tải lại theo mã Booking vừa tạo và hiển thị đầy đủ dữ liệu trong tab duy nhất. Frontend build đạt.

# 2026-08-21: Tìm kiếm Booking: ô nhập từ khóa được đưa lên thanh Booking; SystemSearchModal chỉ hiển thị kết quả/bộ lọc và tự tải lại khi từ khóa thay đổi. Popup thu gọn `max-w-2xl`; frontend build đạt.

# 2026-08-21: Booking search popup width and anchor position updated to sit below the global search field; frontend build passed.

# 2026-08-21: DÃ²ng 147 â€” Booking search now searches booking name/contact, company name/code, external reference and numeric booking code; arrival filtering sends `date_type=arrival`; Reservation/Front Desk search icons open the Booking Search popup in Create Registration. PHP lint and frontend build passed.

# 2026-08-21: Row 147 clarification - the Booking Search entry point is the magnifying-glass button in the blue main header; submenu search behavior remains unchanged.

# 2026-08-24: Create Registration edit form keeps `modalForm` isolated from the main Booking tab; changing deposit value no longer mutates `activeTab` before save. Main tab data reloads only after a successful Booking update; cancel/save failure preserves the previous main-tab snapshot.

# 2026-09-04: Report Template Designer hỗ trợ phạm vi hàng tùy chỉnh `table`/`group`/`detail`; renderer lặp hàng theo cấp nhóm hoặc từng dòng, giữ tương thích template cũ và render groupFooter trong grouping cấu hình. Backend test và frontend build đạt.

# 2026-09-04: Template Designer đồng bộ lại `content_html` từ `content_json` ngay sau khi tải mẫu, giúp preview dùng đúng cấu hình hiện tại; thao tác Lưu phiên bản vẫn cần thiết để áp dụng cho báo cáo thực tế.

# 2026-09-04: Tạo report độc lập NO_SHOW_BY_DAY theo legacy sp_056/sp_056_Division, dùng procedure rpt_no_show_by_day và template NO_SHOW_BY_DAY_STANDARD; không thay đổi report NO_SHOW hiện tại. Test riêng đạt 2/2.

# 2026-09-04: Quét và hiệu chỉnh NO_SHOW_BY_DAY theo sp_056: sửa mã loại 0=Charge/1=No Charge, ngày đi từ booking_rooms.CheckoutDate, charge phụ thuộc setup RM cùng ngày, prefix Booking không fallback cứng, dropdown Booking, sort Late Check-in đa chi nhánh và tổng theo từng ngày/toàn báo cáo. Migration hiệu chỉnh đã chạy HKT1-HKT4; test riêng đạt 3/3, 31 assertions. Known risk: bốn branch chưa có dữ liệu late_checkins/RM room-night đủ điều kiện để đối chiếu acceptance với dữ liệu thật.

# 2026-09-04: Sửa NO_SHOW_BY_DAY lấy giá từ service_bills.Amount khi bill RM đã post, fallback booking_room_services khi chưa post; HKT1 xác minh 3 phòng Charge hiển thị 540000, 540000, 830000 và 1 phòng No Charge hiển thị 0. Migration 2026_09_04_160000 đã chạy HKT1-HKT4; test report đạt 8/8, 101 assertions.

# 2026-09-14: Designer áp dụng đúng căn lề, cỡ chữ, độ đậm và màu chữ khi người dùng chỉnh block TEXT, kể cả nội dung có CSS/thẻ con; template cũ chưa chỉnh giữ nguyên. Node test 6/6 và frontend build đạt.

- 2026-09-22: Đã triển khai source code cho Dòng 154, 159, 160, 166, 167 và 168 bằng migration `2026_09_22_170000_create_revenue_reports_154_159_160_166_167_168.php`. Mỗi báo cáo có Stored Procedure/source/template/definition riêng; menu chỉ dùng `reservation` và `frontdesk`. Dòng 159 tách theo từng settlement; Dòng 160 để trống PlanAmount/Rate; Dòng 167 chỉ summary; Dòng 168 tách summary/detail. Bổ sung `SummaryServiceInvoicesDataAdapter`, `SalespersonRevenueDataAdapter` và tài liệu hardcoded nhóm doanh thu. Chưa chạy migration trên database và chưa nghiệm thu browser/export với dữ liệu thật.
- 2026-09-22: Mock Room Map chỉ còn tạo phòng ở trạng thái `available`; `SystemConfigurationSeeder` reset toàn bộ `rooms.room_status_code` về `vacant_ready` sau mỗi lần seed để môi trường test bắt đầu với phòng sẵn sàng. Không thay đổi mapping import legacy.

