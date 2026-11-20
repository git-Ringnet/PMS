# `service_packages` và `service_package_items`

- Legacy tham chiếu: `SP1317`, `SP1330`; trạng thái mapping: `Một phần`.
- `SP1317.PackageCode` nhóm các dòng chi tiết; `DetailCode`, `ServiceCode`, `Quantity`, `Rate`, thuế/phí, `OrderDay`, `Outlet`, `Note` có tên/cấu trúc tương ứng.
- `SP1330` có `PackageCode`, bộ phận, dịch vụ/sản phẩm, outlet, giá và tổng; chưa đủ bằng chứng xác định đây là cùng một tập chi tiết với `SP1317`.
- `service_packages.id` là khóa runtime mới; không suy diễn từ `SP1317.PackageCode` hoặc `SP1330.Ma`.
- `bookings.service_package_id` là liên kết runtime. `SP2000.PackageCode` tồn tại nhưng kiểu `bigint` không khớp trực tiếp với `SP1317.PackageCode` kiểu `varchar(20)`; mapping/import cho liên kết này là `Chưa xác minh`.
- `bookings.service_package_snapshot` lưu cấu hình tại thời điểm áp dụng để chỉnh sửa gói sau này không làm đổi đăng ký đã lưu.
- Chưa có importer production cho các bảng này. Khi triển khai import, phải xác minh dữ liệu thực tế, quan hệ khóa gói và quy tắc idempotent trước.
