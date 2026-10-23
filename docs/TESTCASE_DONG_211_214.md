# Test case nghiệm thu - dòng 211, 214

Trước khi test: `php artisan migrate:all` (cập nhật mô tả thông số `AllowCheckinVacantClean`).

Ghi kết quả: ✅ Đạt / ❌ Lỗi (kèm ghi chú).

---

## Dòng 211 - Thông số AllowCheckinVacantClean khi giao phòng

Chuẩn bị: thông số `AllowCheckinVacantClean` = **1**; 3 phòng đến hôm nay đã gán số:
phòng A **bẩn** (Vacant Dirty / chờ dọn), phòng B và C **chờ kiểm tra** (Vacant Clean, có icon ngôi sao).

| Mã | Bước | Kết quả mong đợi | KQ |
|---|---|---|---|
| 211-01 | Sơ đồ phòng (Lễ tân) → chuột phải phòng A → Nhận phòng → Nhận phòng | Báo "Phòng ... đang ở trạng thái chưa dọn (Vacant Dirty). Không thể thực hiện nhận phòng.", phòng không được nhận | |
| 211-02 | Chuột phải phòng B → Nhận phòng → Nhận phòng | Hỏi "Phòng ... đang ở trạng thái chờ kiểm tra (Vacant Clean). Bạn có muốn tiếp tục nhận phòng không?" (không còn câu "Tình trạng phòng sẽ được giữ nguyên") | |
| 211-03 | Bấm đồng ý | Nhận phòng thành công | |
| 211-04 | Màn Booking của phòng C → tích phòng → **Giao phòng (Check-in)** → xác nhận | Hiện hộp "Phòng đang chờ kiểm tra" hỏi tiếp tục nhận phòng (trước đây báo thành công nhưng phòng chưa được nhận) | |
| 211-05 | Bấm **Bỏ qua phòng này** | Báo thất bại "Đã bỏ qua nhận phòng (phòng đang chờ kiểm tra)", phòng chưa nhận | |
| 211-06 | Làm lại 211-04 → **Tiếp tục nhận phòng** | "Giao phòng thành công 1 phòng!", phòng đã nhận | |
| 211-07 | Màn Booking → Giao phòng cho phòng **bẩn** | Báo "chưa dọn (Vacant Dirty). Không thể thực hiện nhận phòng" | |
| 211-08 | Đổi thông số = **0** → nhận phòng chờ kiểm tra | Báo "chờ kiểm tra (Vacant Clean). Không thể thực hiện nhận phòng." | |
| 211-09 | Giao phòng nhanh (phòng trống) chọn phòng bẩn khi thông số = 1 | Báo không thể nhận phòng | |

## Dòng 214 - Thông tin đặt phòng: sửa giá, ăn sáng, rate code, chi tiết thêm giường

Chuẩn bị: phòng đang ở 3 đêm, giá 650.000, có rate code, có ăn sáng, thêm giường 1 x 200.000 mỗi đêm.
Cách vào: Sơ đồ phòng → chuột phải phòng → **Thông tin** → **Sửa**.

| Mã | Bước | Kết quả mong đợi | KQ |
|---|---|---|---|
| 214-01 | Bấm × ở ô **Rate code** | Rate code được xóa trống | |
| 214-02 | Giá phòng nhập **700.000**, bỏ tick **Ăn sáng** → Lưu → Lưu ngay | Báo lưu thành công | |
| 214-03 | Đóng, mở lại Thông tin | Giá **700.000**, Rate code trống, **Ăn sáng không tick** (trước đây luôn hiện tick) | |
| 214-04 | Sửa → **Chi tiết thêm giường**: ngày 2 đổi giá **500.000**, ngày 3 số lượng **0** (không kê giường) → Lưu | Báo "Đã lưu thông tin Thêm giường thành công!" | |
| 214-05 | Đóng, mở lại Thông tin → Chi tiết thêm giường | Ngày 1: 1 x 200.000; ngày 2: 1 x 500.000; ngày 3: 0 | |
| 214-06 | Mở màn Booking của đăng ký đó | Tổng tiền = tiền phòng + thêm giường đúng từng ngày (vd 650.000 x 3 + 200.000 + 500.000 + 0) | |
| 214-07 | Nếu một ngày thêm giường không lưu được (vd ngày đã post) | Báo lỗi rõ ngày nào không lưu được, không báo thành công chung | |
