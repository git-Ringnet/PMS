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
    - Ngoài bảng chính, khi chuyển đổi giữa các tháng, bảng hiển thị đúng Tồn ĐK và số liệu XNT của từng tháng.
- **Trạng thái hiện tại**: Toàn bộ luồng B1 (Tạo kho), B2 (Kiểm kê định kỳ & kết chuyển tồn cuối giữa các tháng), B3 (Nhật ký XNT & Chuyển kho), Get Bill buồng phòng theo tháng đã hoàn thành và hoạt động chính xác.
- **Kế hoạch tiếp theo**: Tiếp tục kiểm tra và hoàn thiện các nghiệp vụ tiếp theo.

