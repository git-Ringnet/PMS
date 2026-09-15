# Triển khai sửa lỗi Booking — 14/09/2026

Nguồn yêu cầu: bản Word 8 section, kế hoạch tại [PLAN_FIX_LOI_BOOKING.md](D:/PMS/PLAN_FIX_LOI_BOOKING.md).

Người dùng yêu cầu dùng sub-agent GPT-5.6 Luna, reasoning **max**. Đã tạo 3 agent: `booking_assignment_status` (1+6 và review migration 7), `booking_guests_breakfast_stay` (2+5, backend 8), `booking_reallocation` (3+4, tích hợp 7). Agent chính sửa frontend 8, hợp đồng/migration 7, tích hợp và kiểm thử.

## Phạm vi và giả định

- Tầng số ưu tiên tăng dần; tầng ký hiệu giữ thứ tự tự nhiên ổn định sau tầng số. Chưa có mapping vị trí địa lý/cánh/tòa để tối ưu cả đoàn theo khoảng cách thực tế.
- Theo correction mới, tab `Lấy phòng` là form add-only độc lập. `quantity` chỉ đếm phòng mới trong draft và mặc định 0; không prefill/đếm/chỉnh giá, số người hoặc ngày của `booking_room` đã lưu. Chỉnh phòng đã lưu dùng API/UI riêng.
- Giá ăn sáng nhập tay được giữ; đổi cờ ăn sáng/miễn phí/phụ phí theo quy tắc cấu hình đang có. Em bé vẫn miễn phí. FIT/GIT chỉ đổi nơi nhận tiền.
- Phòng 0,1,2,4 được xem trong Thông tin khách; không sinh khách mặc định mới cho phòng lịch sử hủy/chuyển.
- Có phòng đang ở thì header là 1. Trường hợp không còn phòng đang ở giữ fallback nghiệp vụ của từng thao tác, không tự đặt ra ma trận trạng thái mới.
- Ngày đến bằng ngày đi tiếp tục tính một ngày theo model hiện tại; chưa đổi nghiệp vụ day-use.
- Giá/dịch vụ đã post được bảo toàn; sửa kỳ ở xử lý RM dự kiến. Chưa tự sửa hóa đơn đã post, chưa thay quy trình điều chỉnh tiền EB/BD ngoài mô tả lỗi.

## Chuyển đổi dữ liệu section 7

**Chưa chạy trên database vận hành.** Chỉ chuẩn bị code/migration và test trên database test riêng. Không mở ứng dụng với bộ frontend/backend mới trên database chưa chuyển đổi để nhập dữ liệu booking.

1. Xác định đúng database khách sạn/tenant. Backup database cùng phiên bản backend/frontend và procedure hiện tại.
2. Đối soát `registration_statuses`: mỗi mã nghiệp vụ phải duy nhất; mọi booking có trạng thái phải ánh xạ được qua PK cũ. Nếu dữ liệu đã trộn PK/mã nghiệp vụ phải xử lý mapping riêng, không đoán theo giá trị số.
3. Chạy thử migration trên bản sao MySQL cùng phiên bản vận hành, đặc biệt test procedure/view/báo cáo do khách sạn tùy chỉnh. Test SQLite không xác nhận MySQL DDL, quyền tạo procedure, definer hay collation.
4. Dừng ghi booking trong cửa sổ triển khai. Chạy migration mới cùng bộ code tương thích, không chỉ sửa file migration cũ đã chạy.
5. Đối soát số booking, mã/nhãn/màu; test None Guaranteed=20 với PK=2 và các mã khác 1; kiểm tra AV, check-in, hủy/noshow, tạo nhanh và báo cáo.
6. Mở lại ghi sau khi đối soát. Tải lại frontend, tránh client cũ gửi PK.

Migration giữ cột `registration_status_pk_before_codes` và bảng `booking_status_code_cutover`. Đây là dữ liệu phục hồi; không xóa thủ công sau khi deploy. Cột backup được ẩn khỏi JSON Booking. Rollback ánh xạ **mã hiện tại** về PK để xử lý cả booking đã tạo hoặc đổi trạng thái sau cutover, đồng thời khôi phục procedure. Cần rollback cùng bộ code cũ khi ngừng ghi; không rollback database đơn lẻ trong lúc frontend mới còn hoạt động.

## Đối soát header section 6

Lệnh mới mặc định chỉ đọc, cần chọn đúng database trước khi dùng:

```text
php artisan bookings:reconcile-inhouse-status
```

Sau khi xem danh sách ứng viên, bản áp dụng là:

```text
php artisan bookings:reconcile-inhouse-status --apply
```

Lệnh chỉ sửa booking có phòng inhouse nhưng header khác 1; mỗi lần ghi đọc lại trạng thái trong transaction. Không đổi quy tắc các booking không có phòng inhouse. Chưa chạy hai lệnh này trên dữ liệu vận hành.

## Dữ liệu G0000022 và UAT

Chưa truy vấn/sửa dữ liệu G0000022. Model BookingRoom sinh ID dạng `G` + 7 chữ số, nhưng vẫn cần đối chiếu bản ghi cụ thể với GAL6 và log hủy để xác nhận lỗi dữ liệu thực tế. Không coi thay đổi code là đã sửa bản ghi lịch sử.

Các ảnh trong thư mục này là ảnh lỗi nguồn, **không phải ảnh nghiệm thu sau sửa**. Cần UAT theo từng section với dữ liệu khách sạn, gồm bàn phím/paste vào ô giá, chuỗi hủy/lấy lại/đổi BAR, chuyển phòng, sửa ngày đi từ Room Map và lọc báo cáo sau cutover.

## Kiểm thử

Các lệnh kiểm tra dùng trong workspace:

```text
cd backend
php vendor/phpunit/phpunit/phpunit --filter BookingBusinessRulesTest
php vendor/phpunit/phpunit/phpunit --filter BookingStatusMappingTest
php vendor/phpunit/phpunit/phpunit --filter RegistrationStatusCutoverTest
php vendor/phpunit/phpunit/phpunit --filter ReconcileBookingStatusTest
php artisan test tests/Feature/Booking/BookingAllocationConsistencyTest.php --compact
```

```text
cd frontend
node --test --experimental-test-isolation=none tests/*.test.js
npm run build -- --configLoader native
```

Kết quả đã chạy trong workspace:

- Trước correction add-only, `BookingAllocationConsistencyTest`: **5 tests, 17 assertions passed**; bao phủ lịch sử hủy/chuyển, JST quantity 0, cập nhật partial-inhouse, chống lặp `bookingRoomId` và tạo booking mới 3 loại phòng × 2 phòng qua availability.
- `BookingBusinessRulesTest`: **24 tests, 116 assertions passed**.
- `BookingStatusMappingTest` và `BookingTest`: **6 tests, 35 assertions passed**; có mã nghiệp vụ khác PK.
- Tập backend mục tiêu gồm allocation, business rules, status mapping, Booking, ChargeNoshow, folio và các test cutover/reconcile: **70 tests, 323 assertions passed**.
- Đã sửa lỗi runtime `HotelSetting::getSetting()` trong availability/occupancy: `FrmOOO_DefineLockByTime` được đọc từ `hotel_configs` bằng `HotelConfig::where(...)->value(...)`, giữ fallback `12:00`; regression tạo booking mới chạy thành công.
- Add-room mới dùng `POST /bookings/{id}/add-rooms` với `intent=add_only`; backend chỉ validate/insert detail mới trong transaction và từ chối `bookingRoomId`. FE giữ draft riêng, không gửi `room_allocations` vào API update booking hiện hữu.
- Toàn bộ frontend test files: **39 tests passed** khi chạy với `--experimental-test-isolation=none`.
- `npm run build -- --configLoader native`: **passed**; Vite build hoàn tất.

Windows sandbox chặn spawn ở chế độ mặc định; Node tests cần chạy không tách process như lệnh trên. Chưa chạy UAT trên trình duyệt với chuỗi GAL6, đổi BAR nhanh, keyboard/paste và dữ liệu thật. Chưa chạy migration trên MySQL vận hành; SQLite không xác nhận DDL/procedure/quyền/collation của MySQL. Chưa truy vấn hoặc sửa G0000022. Cần backup, dry-run migration, đối soát status/AV và nghiệm thu từng section trước khi mở ghi dữ liệu.

`RoomLockTest::test_section_2_allow_lock_room_cause_unassignable_room_bk` vẫn còn một assertion riêng về nội dung cảnh báo AV (fixture chờ thông báo thiếu phòng liên tục, endpoint hiện trả cảnh báo AV âm phòng); failure này không đi qua accessor `HotelSetting::getSetting()` và nằm ngoài regression tạo booking mới.

Các thay đổi add-only trong correction này **chưa viết/chạy test hoặc build theo yêu cầu người dùng**. Cần kiểm tra thủ công theo repro: booking đã có 2 phòng, mở `Lấy phòng`, sửa giá/người nhưng quantity mới vẫn 0, lưu và đối chiếu các trường của 2 `booking_rooms` không đổi; sau đó thử thêm phòng, thử lỗi availability và thử hủy/reopen draft.
