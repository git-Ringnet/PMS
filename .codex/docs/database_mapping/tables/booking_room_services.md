# `booking_room_services`

- 2026-08-19: Thêm `user_id` nullable FK `users.id` cho người tạo dòng dịch vụ; giữ `created_by` và `posted_by_employee_code`.
- `department`: lưu mã bộ phận runtime `FO` hoặc `HK`; không lưu tên hiển thị. Dữ liệu legacy chưa có mapping mã bộ phận xác minh phải giữ `NULL/quarantine` theo quy tắc import.
- `service_package_detail_no`: đánh dấu dòng dự kiến sinh từ Gói dịch vụ runtime, không có cột legacy được xác minh và không dùng làm khóa import.

- Legacy: `SP2102`
- Trạng thái: `Đã xác minh`
- Phụ thuộc: `booking_rooms`, `guests`; liên kết bill có thể bổ sung sau.

| Legacy | Mới | Chuyển đổi |
|---|---|---|
| `RentalRoomId` | `booking_room_id` | Tra legacy ID của `booking_rooms`. |
| `Ma` | Chưa có cột legacy riêng | Cần lưu khóa ghép legacy hoặc bảng mapping để chống import trùng. |
| `ServiceId` | `service_code` | Giữ mã; chuẩn hóa mã catalog đã được duyệt. |
| `Description` | `service_name` hoặc `note` | Tách tên và ghi chú theo rule import. |
| `FromDate`/`ToDate` | `service_date` | Legacy có khoảng ngày; cần bung thành từng ngày theo đúng `Ma`/nghiệp vụ. |
| `Quantity` | `quantity` | Decimal 6 chữ số thập phân. |
| `Rate` | `rate` | Decimal. |
| `Total` | `total_amount` | Giữ tổng legacy; không bắt buộc tính lại nếu có sai số lịch sử. |
| `IsRoom` | `is_room` | `1=FIT/phòng`, `0=GIT/Master`. |
| `Status` | `is_posted` | Cần bảng quy đổi trạng thái được xác minh trước import. |
| `Currency` | Chưa có cột tương ứng | Runtime hiện xử lý VND; không bỏ mã ngoại tệ nếu dữ liệu legacy có phát sinh. |
| `RoomRateCode` | Chưa có cột tương ứng | Cần quyết định lưu snapshot hoặc liên kết rate code. |
| `Promotion` | Chưa có cột tương ứng | Cần quyết định trước import. |

## Dữ liệu mới không có trực tiếp trong `SP2102`

- Báo cáo No-show dùng các dòng `service_code = RM` theo ngày làm nguồn tiền dự kiến thay cho kết quả tính toán `func_052`; đây là mapping runtime báo cáo, không thay đổi ánh xạ import `SP2102`.

- `department`: lấy từ nguồn phát sinh/catalog; FO cho luồng Lễ tân, HK cho luồng Housekeeping.
- `folio`: mặc định theo nghiệp vụ, không suy ra từ `IsRoom`.
- `service_bill_id` và các cột detail: map sau khi import `SP3000`/`SP3001` nếu xác định được liên kết.
- `guest_id`: chỉ set khi legacy xác định được khách sở hữu.
- Báo cáo `SUPPLEMENTARY_SERVICES` dùng `service_date`, `service_code`, `service_name`, `quantity`, `rate`, `total_amount` và loại trừ `service_code = RM`; không thay đổi mapping import.
- Báo cáo `EXPECTED_BREAKFAST` đọc `service_code`, `service_date`, `quantity` và `total_amount` để nhận diện dịch vụ ăn sáng ngoài; danh sách mã bổ sung lấy từ `hotel_configs`.
- `posted_by_employee_code`: runtime mới, lưu mã nhân viên đưa dịch vụ setup lên bill; legacy không có mapping đã xác minh nên để `NULL` khi import.

## Rủi ro

- Không được mất `IsRoom`; đây là khóa nghiệp vụ phân phối bill Room/Master.
- Không dùng default bộ phận để che dữ liệu thiếu.
- Cần chiến lược idempotent dựa trên `RentalRoomId + Ma`, không chỉ dựa trên mã dịch vụ/ngày.
