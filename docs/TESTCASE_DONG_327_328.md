# Test case nghiệm thu - dòng 327, 328 (Danh mục báo cáo / Phân quyền xem báo cáo)

Trước khi test: `php artisan migrate:all` (tạo bảng `report_menu_nodes`, tạo quyền xem `report.<mã>.view` cho từng báo cáo và cấp sẵn cho các vai trò đang có).

Cách vào màn cấu hình: **Cấu hình → Mẫu & Báo cáo → 3. Danh Mục Báo Cáo → Cấu hình menu báo cáo**.

Ghi kết quả: ✅ Đạt / ❌ Lỗi (kèm ghi chú).

---

## Dòng 327 - Cấu hình menu báo cáo theo module, kéo thả

| Mã | Bước | Kết quả mong đợi | KQ |
|---|---|---|---|
| 327-01 | Mở màn Cấu hình menu báo cáo | Bên trái: "Tất cả báo cáo (n)", ô tìm, nhãn ĐK/LT/BP cho biết báo cáo đang ở module nào. Bên phải: 3 tab Đăng ký phòng / Lễ tân / Buồng phòng | |
| 327-02 | Tab Lễ tân, kéo nhóm thứ 2 lên đầu | Nhóm đổi chỗ, hiện "Có thay đổi chưa lưu" | |
| 327-03 | Kéo 1 báo cáo trong nhóm sang cuối nhóm khác | Báo cáo chuyển sang nhóm mới | |
| 327-04 | Bấm X để gỡ 1 báo cáo khỏi nhóm | Báo cáo biến mất khỏi cây; bên trái báo cáo đó lại có nút + | |
| 327-05 | Thêm nhóm → gõ tên "Nhóm QA" → Enter; kéo báo cáo vừa gỡ từ bên trái vào nhóm | Nhóm mới có báo cáo; bên trái báo cáo hiện "Đã xếp" | |
| 327-06 | Thêm 1 nhóm nữa tên "nhóm qa" → Lưu cấu hình | Báo "Nhóm ... bị trùng tên", không lưu | |
| 327-07 | Sửa ở tab Lễ tân rồi chuyển sang tab Đăng ký phòng | Tab Lễ tân có dấu ● (chưa lưu) | |
| 327-08 | Quay lại Lễ tân → Lưu cấu hình | Báo "Đã lưu cấu hình menu báo cáo Lễ tân" | |
| 327-09 | Đóng màn rồi mở lại | Thứ tự giữ đúng như đã lưu | |
| 327-10 | Vào Lễ tân, rê chuột vào menu **Báo Cáo** | Menu đúng thứ tự vừa xếp, có "Nhóm QA" | |
| 327-11 | Vào Đăng ký phòng, xem menu Báo Cáo | Không bị ảnh hưởng bởi thay đổi bên Lễ tân | |
| 327-12 | Xóa nhóm đang có báo cáo | Hỏi xác nhận "... sẽ bị gỡ khỏi menu Lễ tân" | |
| 327-13 | Có thay đổi chưa lưu → bấm X đóng màn | Hỏi "Chưa lưu cấu hình"; chọn Ở lại thì vẫn ở màn | |
| 327-14 | Lưu sau khi xóa nhóm → xem menu Lễ tân | Báo cáo trong nhóm đã xóa không còn trên menu | |

## Dòng 328 - Phân quyền xem báo cáo theo rule

Cần 1 tài khoản không phải admin, ví dụ vai trò **Nhân Viên Lễ Tân** tại chi nhánh HKT1.

| Mã | Bước | Kết quả mong đợi | KQ |
|---|---|---|---|
| 328-01 | Màn Cấu hình menu báo cáo → tìm "chuyển phòng" → bấm 🛡 | Modal Quyền xem: chọn chi nhánh, danh sách vai trò (không có Super Admin), đang tick hết | |
| 328-02 | Bỏ tick **Nhân Viên Lễ Tân** → Lưu quyền | Báo "Đã lưu quyền xem báo cáo" | |
| 328-03 | Tài khoản lễ tân vào Lễ tân → menu Báo Cáo | Không còn "Báo cáo chuyển phòng"; báo cáo khác vẫn có | |
| 328-04 | Tài khoản lễ tân mở thẳng link `/frontdesk?tab=reports&report=ROOM_MOVES` | Không xem được (API trả "Bạn không có quyền xem báo cáo này") | |
| 328-05 | Tài khoản lễ tân không được sửa cấu hình menu báo cáo (gọi API lưu) | Bị chặn (403) | |
| 328-06 | Admin vào **Hệ thống → Vai trò & Phân quyền** → chọn Nhân Viên Lễ Tân → phân hệ **REPORT** | Có danh sách báo cáo đúng theo danh mục; "Báo cáo chuyển phòng" đang không tick; ô "Cho xem tất cả báo cáo" không tick | |
| 328-07 | Tick lại báo cáo đó → Lưu | Báo đã lưu; ô "Cho xem tất cả báo cáo" tự tick | |
| 328-08 | Tài khoản lễ tân tải lại trang | Thấy lại "Báo cáo chuyển phòng" | |
| 328-09 | Đăng nhập Super Admin | Luôn thấy mọi báo cáo | |
