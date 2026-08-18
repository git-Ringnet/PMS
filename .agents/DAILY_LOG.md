# Nhật Ký Tiến Độ Dự Án (Project Dev Log)

> File này ghi nhận tiến độ công việc, các tính năng/nghiệp vụ đã hoàn thành, trạng thái hiện tại và kế hoạch tiếp theo để tiếp nối công việc giữa các phiên làm việc.

---

## 📌 Hướng dẫn ghi log
- **Ngày ghi**: `YYYY-MM-DD`
- **Module / Nghiệp vụ**: Tên module (Housekeeping, Booking, Thu ngân, Cài đặt,...)
- **Nội dung hoàn thành**: Chi tiết logic, API, UI, DB migration/seeder đã xử lý + link file.
- **Trạng thái hiện tại**: Đang dừng ở bước nào, cần lưu ý gì.
- **Kế hoạch tiếp theo**: Việc cần làm tiếp khi mở lại dự án.

---

## [2026-08-17] - Khởi tạo hệ thống theo dõi tiến độ & Hoàn thiện Kiểm kê kho (Get Bill)
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
- **Kế hoạch tiếp theo**: Tiếp tục kiểm tra và hoàn thiện các nghiệp vụ tiếp theo.


