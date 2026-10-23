# Test case nghiệm thu - dòng 153, 156, 288 (Khóa phòng)

Trước khi test: `php artisan migrate:all`. Migration `2026_10_22_130000` sửa các phòng đang bị lưu sai OOO/OOS (phòng đang có khách, hoặc chỉ có lịch khóa tương lai).

Ghi kết quả: ✅ Đạt / ❌ Lỗi (kèm ghi chú).

---

## Dòng 153 - Ghi chú khóa phòng + thông số RoleUserUnlockRoomOOO/OOS

| Mã | Bước | Kết quả mong đợi | KQ |
|---|---|---|---|
| 153-01 | Kế hoạch phòng → rê chuột vào thanh khóa OOO/OOS | Chỉ hiện ngày khóa, Ghi chú, Người khóa (không còn dòng Tên, Loại khóa) | |
| 153-02 | Sơ đồ phòng → rê chuột vào phòng đang khóa | Hiện tooltip giống Kế hoạch phòng: ngày khóa, Ghi chú, Người khóa | |
| 153-03 | Thông số `RoleUserUnlockRoomOOO/OOS` = **FOM**. Đăng nhập tài khoản vai trò **Nhân Viên Lễ Tân** (fo_staff) → mở khóa 1 phòng | Báo "Tài khoản của bạn không thuộc vai trò (Role) được phép mở khóa phòng (Vai trò yêu cầu: FOM)", phòng vẫn khóa | |
| 153-04 | Cùng thông số, đăng nhập tài khoản **Trưởng Lễ Tân** (fo_manager) → mở khóa | Mở khóa được | |
| 153-05 | Đổi thông số = **FO** → Trưởng Lễ Tân mở khóa | Bị chặn (FO chỉ là nhân viên lễ tân, không gồm FOM) | |
| 153-06 | Ở Sơ đồ phòng, đổi tình trạng phòng khóa sang phòng trống bằng tài khoản không thuộc Rule | Bị chặn với cùng thông báo | |

Quy đổi mã Rule: Admin = super_admin/branch_admin, FO = fo_staff, FOM = fo_manager, HK = hk_staff, HKM = hk_manager, FB = fb_staff, FBM = fb_manager. Có thể ghi thẳng mã vai trò (vd `fo_manager`).

## Dòng 156 - Sửa phòng khóa

Giả sử ngày hệ thống là **N**.

| Mã | Bước | Kết quả mong đợi | KQ |
|---|---|---|---|
| 156-01 | Khóa phòng: chọn phòng đang khóa có ngày bắt đầu ≤ N → Chỉnh sửa | Ô Bắt đầu bị khóa, sửa được Kết thúc, Ghi chú, Tiến độ bảo trì | |
| 156-02 | Sửa ngày kết thúc lùi thêm 2 ngày → Cập nhật | Lưu được, không báo lỗi | |
| 156-03 | Tạo khóa OOS bắt đầu N+1. Kiểm tra Sơ đồ phòng | Phòng chưa bị khóa (vẫn trống) | |
| 156-04 | Chỉnh sửa khóa đó: đổi ngày bắt đầu = N → Cập nhật | Sơ đồ phòng hiện phòng **OOS** ngay; bảng `rooms.room_status_code` = `oos` | |
| 156-05 | Gia hạn ngày kết thúc khóa trùng vào booking đã đặt của phòng đó | Bị chặn: "trùng lịch với booking ..." | |
| 156-06 | Gia hạn khóa làm hạng phòng hết phòng trống. `AllowOverRoomTypeRoomKind` = 0 | Bị chặn: "hết phòng trống (AV < 0)" | |
| 156-07 | Như 156-06 nhưng `AllowOverRoomTypeRoomKind` = 1 | Hỏi xác nhận, bấm Tiếp tục mới lưu | |
| 156-08 | Gia hạn khóa làm booking chưa gán số không còn phòng nguyên giai đoạn. `AllowLockRoomCauseUnassignableRoomBK` = 0 | Bị chặn: "không đủ phòng trống liên tục để gán cho booking ..." | |
| 156-09 | Như 156-08 nhưng thông số = 1 | Hỏi xác nhận, bấm Tiếp tục mới lưu | |

## Dòng 288 - Phòng có khách nhưng rooms lưu tình trạng khóa

| Mã | Bước | Kết quả mong đợi | KQ |
|---|---|---|---|
| 288-01 | Phòng đang có khách ở (vd 1412) có lịch khóa tương lai → Chỉnh sửa khóa đó (đổi ghi chú / ngày kết thúc) | `rooms.room_status_code` của phòng **không** chuyển sang `ooo` | |
| 288-02 | Sơ đồ phòng → ô Đang ở và danh sách Phòng đang ở | Hai số khớp nhau (vd 25/25), khách phòng đó vẫn được tính | |
| 288-03 | (Server) Sau `migrate:all`, kiểm tra các phòng đang có khách | Không còn phòng nào có khách mà `room_status_code` = `ooo`/`oos` | |
| 288-04 | Sơ đồ phòng → phòng đang có khách → đổi tình trạng phòng sang OOO hoặc OOS (1 phòng hoặc chọn nhiều phòng) | Báo "Phòng ... đang có khách ở, không thể chuyển sang OOO", tình trạng phòng giữ nguyên | |
