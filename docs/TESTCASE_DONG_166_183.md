# Test case nghiệm thu nhanh - dòng 166, 183

Ghi kết quả: ✅ Đạt / ❌ Lỗi (kèm ghi chú).

---

## Dòng 183 - Chuyển phòng cùng ngày: số đêm phòng cũ = 0

Chuẩn bị: 1 đăng ký đến hôm nay (vd phòng 405, 2 đêm).

| Mã | Bước | Kết quả mong đợi | KQ |
|---|---|---|---|
| 183-01 | Nhận phòng 405 → chuyển phòng 405 sang 1008 ngay trong ngày | Chuyển thành công | |
| 183-02 | Mở màn **Đăng ký** (Booking) của đăng ký đó | Nhóm "Phòng chuyển": phòng 405, ngày đến = ngày đi = hôm nay, **Đêm = 0**, tổng tiền 0. Phòng 1008: số đêm còn lại (vd 2) | |
| 183-03 | (DB) `booking_rooms` của phòng 405 | `ActutalNumOfDays = 0`, status = 100 | |

## Dòng 166 - Chuyển khách sang phòng đang ở (note đỏ)

Chuẩn bị: phòng A (vd 104) đang ở có khách chính; tiền phòng 2 đêm đang ở **master/booking**; 1 bill minibar 69k post **tại phòng**.
Phòng B (vd 112) đang ở có khách chính riêng và 1 bill dịch vụ 100k.

| Mã | Bước | Kết quả mong đợi | KQ |
|---|---|---|---|
| 166-01 | Chuyển phòng → **chuyển khách chính** của phòng A sang phòng B (phòng đang ở) | Thành công | |
| 166-02 | Mở **Hóa đơn** đăng ký của phòng A | 2 bill tiền phòng **vẫn ở master** của đăng ký A, không đi theo khách | |
| 166-03 | Mở **Hóa đơn** đăng ký của phòng B | Bill tách theo khách: khách chính B = 100k (bill gốc), khách vừa chuyển tới = **69k (chỉ minibar)** | |
| 166-04 | (DB) `service_bills` bill minibar | `RentalRoomId2` = phòng B, `CustomerId2` = khách chuyển, **`RegisterID2` = mã đăng ký của phòng B** | |
| 166-05 | (DB) `service_bills` bill tiền phòng | `RegisterID2` = đăng ký A, `RentalRoomId2`/`CustomerId2` = NULL (không đổi) | |
| 166-06 | Phòng B: thông tin khách | Khách chính phòng B giữ nguyên, khách chuyển tới là khách phụ | |
| 166-07 | Trường hợp tiền phòng post **tại phòng** A (RentalRoomId2 = phòng A) → chuyển khách chính sang B | **Toàn bộ** bill của phòng A (tiền phòng + dịch vụ) chuyển theo khách chính sang B | |
| 166-08 | Chuyển **khách phụ** của phòng A sang B | Chỉ bill của khách phụ đó chuyển, bill của khách khác ở lại phòng A | |
