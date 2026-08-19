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
- **Trạng thái hiện tại**: Toàn bộ hệ thống ghi log và hiển thị lịch sử thao tác đã hoàn thành 100%, test cú pháp PHP và Vite build thành công không có lỗi.
- **Kế hoạch tiếp theo**: Hỗ trợ người dùng kiểm tra trực tiếp và tiếp tục các tính năng tiếp theo.




