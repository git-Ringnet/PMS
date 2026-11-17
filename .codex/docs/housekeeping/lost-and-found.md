# Lost & Found

## Liên kết

- Tab: `/housekeeping?tab=lost-found`
- Component: `frontend/src/pages/housekeeping/components/LostAndFound.vue`
- API: `/api/lost-and-found`
- Controller: `backend/app/Http/Controllers/Api/LostAndFoundController.php`
- Bảng PMS: `lost_and_found_items`
- Bảng legacy: `SP1333`

## Nghiệp vụ

- Theo dõi đồ khách báo thất lạc hoặc đồ được tìm thấy trong khách sạn.
- Lưu thông tin khách, mặt hàng, ngày báo cáo, ngày/người/địa điểm tìm thấy và vị trí lưu trữ; không lưu giờ tìm thấy/xử lý.
- Khi xử lý, cập nhật ngày, phương thức, người giao, người nhận và ghi chú.
- Hỗ trợ nhiều hình ảnh và xem ảnh phóng lớn.
- Dùng `LoadingOverlay` chung khi tải danh sách, lưu hoặc xóa phiếu.
- Dùng `LoadingOverlay` chung khi tải danh sách, lưu hoặc xóa phiếu.
- `log_no` do backend tự tăng; user tạo lấy từ tài khoản đăng nhập.
- Trạng thái màn hình gồm `lost` và `found`.

## Ràng buộc & Giao diện (Cập nhật Dòng 67, 68 FIX FE)

- Bắt buộc `item_found` và `date_reported`; hiển thị điểm nhấn trường bắt buộc nền `#FFF8DB`, viền `#F1DD8A`, focus `#eab308`.
- Bộ lọc ngày dùng `date_reported` và mặc định bằng ngày hệ thống.
- Các ô ngày dùng `SingleDatePicker` hỗ trợ nhập tay, gõ số tự nhảy `/`, ràng buộc 8 số `ddmmyyyy`, định dạng chuẩn `dd/mm/yyyy`, tích hợp nút xóa nhanh `x` và gửi API dưới dạng `yyyy-mm-dd`.
- Bảng danh sách: tiêu đề cột Title Case, căn giữa và có viền đầy đủ; nội dung font Roboto đồng nhất 12px (`text-xs`).
- Thanh công cụ: bỏ nút "Đặt lại"; ô tìm kiếm bổ sung nút `(x)` xóa nhanh.
- Modal Thêm/Sửa: header đồng bộ màu theme hệ thống (`var(--pms-custom-theme, #006bdb)`), hỗ trợ phím `Esc` để đóng; in đậm 3 tiêu đề nhóm (Thông tin chung, Thông tin giao nhận, Hình ảnh đồ vật); bổ sung nút `(x)` cho tất cả ô nhập liệu; nút hành động chuẩn `.btn-pms-primary` và `.btn-pms-close`.
- Component được dùng tại cả Housekeeping và Sơ đồ phòng.
- Chiều mapping `SP1333.Status` chưa xác minh; không tự suy đoán khi import.

## Phân quyền và xóa phiếu

- Xem: `hk.lost_found.view`; tạo: `hk.lost_found.create`; sửa/xử lý: `hk.lost_found.manage`; xóa: `hk.lost_found.delete`.
- FE chỉ hiển thị từng thao tác khi có đúng quyền; API kiểm tra lại quyền theo HTTP method.
- Xóa luôn yêu cầu xác nhận. Phiếu đã có dữ liệu xử lý hoặc giao nhận không được xóa cứng.
