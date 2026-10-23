# Test case nghiệm thu - dòng 259 (Sang ngày - tab Phòng đến)

Lỗi: tab Phòng đến ở màn Sang ngày chỉ lấy phòng đã gán số phòng, nên còn phòng chưa nhận (chưa gán số) vẫn sang ngày được.
Sửa: lấy cả phòng có ngày đến = ngày hệ thống chưa gán số phòng; bước kiểm tra trước khi sang ngày cũng chặn các phòng này.

Chuẩn bị (ngày hệ thống N), 3 đăng ký đến ngày N, chưa check-in:
- **U**: chưa gán số phòng, tình trạng Guaranteed
- **A**: đã gán số phòng (vd 1001)
- **W**: chưa gán số phòng, tình trạng Waiting

Ghi kết quả: ✅ Đạt / ❌ Lỗi (kèm ghi chú).

| Mã | Bước | Kết quả mong đợi | KQ |
|---|---|---|---|
| 259-00 | Sơ đồ phòng → bấm thẻ **Đã đến** → xem "Phòng chưa đến" | Có 3 phòng U, A, W | |
| 259-01 | Lễ tân → **Sang ngày** | Vòng tròn "Phòng đến" = **3** (khớp với 259-00) | |
| 259-02 | Cùng màn | Nút **Sang ngày** bị khóa | |
| 259-03 | Bấm tab **Phòng đến** | Dòng U: cột Phòng = **Chưa gán** | |
| 259-04 | Cùng tab | Dòng A: cột Phòng = **1001** (phòng đã gán vẫn hiện như trước) | |
| 259-05 | Cùng tab | Dòng W (Waiting) cũng hiện, cột Phòng = **Chưa gán** | |
| 259-06 | Còn phòng đến chưa xử lý mà bấm Sang ngày (nếu nút bấm được) | Báo "Không thể sang ngày vì vẫn còn ... phòng chưa check-in", ngày hệ thống không đổi | |
| 259-07 | Tích U → **Khách không đến (No Show)** → Xác nhận Noshow | Báo "Đã ghi nhận khách không đến...", U biến mất khỏi tab | |
| 259-08 | Tích W → **Cập nhật ngày đến (No Show One Day)** → Xác nhận cập nhật | Báo dời ngày đến thành công (ngày đến N+1), W biến mất | |
| 259-09 | Tích A → Cập nhật ngày đến (hoặc nhận phòng) | Không còn phòng đến chưa xử lý | |
| 259-10 | Mở lại Sang ngày → tab Phòng đến | Không còn U, A, W. Nếu Phòng đi cũng đã xử lý hết thì nút Sang ngày mở lại | |

Ghi chú: đăng ký **Waiting** chưa gán số phòng cũng phải xử lý trước khi sang ngày, giống danh sách "Phòng chưa đến" ở Sơ đồ phòng.
