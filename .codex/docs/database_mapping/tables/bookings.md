# `bookings`

- Legacy: `SP2000`
- Trạng thái: `Đã xác minh`
- Ghi chú: Giữ legacy ID để map bảng con.
- Báo cáo `EXPECTED_BREAKFAST` dùng booking ID, tên booking và trạng thái đăng ký để hiển thị/lọc theo mapping legacy đã xác minh.
- `module`: runtime lưu mã module `SALE` (Đặt phòng), `FO` (Lễ tân), `HK` (Buồng phòng). Migration backfill các giá trị cũ `reservation`, `reception/frontdesk`, `housekeeping`; API normalize alias cũ khi đọc để không hỏng dữ liệu/import.
- `created_by_user_id`, `updated_by_user_id`: scalar ID nullable của User dùng chung trong `pms_system`; không có FK xuyên database.
- `service_package_id` và `service_package_snapshot`: liên kết/snapshot runtime của Gói dịch vụ. `SP2000.PackageCode` có tồn tại nhưng kiểu `bigint` không khớp trực tiếp `SP1317.PackageCode` (`varchar(20)`); mapping và import cột này là `Chưa xác minh`.
- `is_service_only`: cờ runtime mới, mặc định `false`, không map từ `SP2000`. Migration `2026_09_28_130000_add_service_only_virtual_folio_support.php` chỉ backfill `true` khi Booking có ít nhất một dòng virtual (`room_number` bắt đầu `0` hoặc Room có `is_internal=1`) và không có dòng vật lý/chưa gán; gồm cả booking_room soft-delete khi xác định hỗn hợp. Không đổi dữ liệu/ID/date legacy khác. Cờ đánh dấu Booking kỹ thuật neo folio dịch vụ, không phải lượt lưu trú.
- Migration `2026_09_29_150000_create_initial_virtual_room_service_folios.php` và API `POST /api/rooms/{room_number}/service-folio` tạo Booking runtime `is_service_only=true`, tên `PHÒNG ẢO <room_number>`, ngày theo system date, trạng thái Reservation, `module=FO` cho Room `0%` có `is_internal=true` khi chưa có BookingRoom đang hoạt động. API gọi khi chọn phòng trong Checkout, idempotent; chỉ GET/lọc phòng không ghi DB. Booking này không có Guest/Company/Payment, không map ngược thành SP2000 và không kế thừa bill lịch sử; folio chỉ neo nghiệp vụ post dịch vụ mới.
- API từ chối sửa/xóa/nhân bản/khôi phục Booking service-only bằng các API nghiệp vụ lưu trú; API dịch vụ và thanh toán hiện hành vẫn được dùng cho folio này.
- `created_by`, `updated_by`: tiếp tục giữ username snapshot để tương thích `SP2000`, import và audit.
- Dữ liệu cũ chỉ backfill actor ID khi username khớp duy nhất; không khớp giữ `NULL`.
- Mapping trạng thái legacy được tập trung tại `App\\Services\\RegistrationStatusMapper::idFromLegacyCode()`. Hàm nhận mã `SP1311.BookingStatusId` và trả `registration_statuses.id`; không ghi trực tiếp mã legacy vào Booking.
- Chưa có importer production đọc trực tiếp `SP2000.Status`; khi có nguồn legacy runtime phải bổ sung importer và test dữ liệu trước khi chạy.

## Mapping cột

- Mapping cấp bảng đã được ghi tại `../TABLE_MAP.md`.
- Chưa được dùng file này để import production cho đến khi các cột, khóa, nullable/default và quy tắc chuyển đổi được đối chiếu đầy đủ.
- Khi thay đổi schema hoặc viết import cho bảng này, phải bổ sung mapping từng cột và tác động khóa ngoại tại đây.
# Mapping trạng thái đăng ký

- `bookings.registration_status_id` là FK tới `registration_statuses.id`.
- Không dùng trực tiếp `registration_statuses.booking_status_id` hoặc mã `SP1311` cho cột FK này.
- `bookings.status` giữ trạng thái vận hành: Reservation, Inhouse, Checkout, Deleted/Cancelled và No-show.
- Khi import `SP2000.Status`, phải tra mapping qua `registration_statuses.booking_status_id` trước khi ghi `bookings.registration_status_id`.
