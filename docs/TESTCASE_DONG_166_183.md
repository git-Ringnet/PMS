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
| 183-04 | (DB) kiểm tra thêm `NumOfDays` của phòng cũ sau chuyển cùng ngày | `NumOfDays = 0`; không còn số đêm dự kiến cũ | |
| 183-05 | Chuyển khách cùng ngày sang phòng đang ở, chuyển hết khách khỏi phòng nguồn | Phòng nguồn status 100, cả hai cột số đêm bằng 0; phòng đích giữ đúng kỳ ở | |
| 183-06 | Chuyển sau khi đã ở 4 đêm; mở lại Đăng ký và thông tin phòng | Phòng cũ hiển thị 4 đêm, không bị đổi thành 0 | |
| 183-07 | Giao ca → Phòng đến → Tất cả, chọn ngày chuyển; xem dòng phòng cũ | Dòng phòng chuyển cùng ngày hiển thị 0 đêm | |

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
| 166-09 | Kiểm tra bill 100k có sẵn của khách chính phòng B trước/sau chuyển | Không bị sửa owner; `RentalRoomId2`, `CustomerId2` vẫn NULL nếu trước chuyển là NULL | |

## Kiểm tra lại theo note đỏ — 23/10/2026

- Đã đọc lại hai ảnh khách gửi và nội dung Excel. Commit `2507496f` đã có sửa dòng 166 và phần giữ 0 đêm ở modal thông tin phòng.
- **Nguyên nhân 166:** không thể quyết định chuyển bill chỉ từ phòng/khách phát sinh gốc. Bill đã đưa lên Master vẫn có các mã nguồn cũ; cần xét owner hiện tại. Khi bill đi theo khách sang booking khác, phải cập nhật đủ `RegisterID2`, `RentalRoomId2`, `CustomerId2`. Bill sẵn có của phòng đích không bị đổi, khách chính phòng đích giữ nguyên.
- **Nguyên nhân 183:** lấy số đêm bằng `value || 1` coi 0 là thiếu dữ liệu. Ngoài ra, khi kết thúc phòng cũ, `ActutalNumOfDays` có thể đã bằng 0 nhưng `NumOfDays` vẫn giữ số đêm kế hoạch. Bản thay đổi đang có cập nhật đồng thời hai cột, và giữ 0 khi lên danh sách giao ca.
- Lúc kiểm tra lại, đã có 3 test riêng cho hai nhánh note đỏ dòng 166 và chuyển khách phụ; chưa có test riêng 183. Đã bổ sung 2 test HTTP chuyển cùng ngày (phòng trống và phòng đang ở), 1 test API giao ca, 4 test frontend cho số đêm và owner bill.
- **Kết quả đã chạy:** `RoomMoveTest` + `ShiftWorkListTest`: **25/25 đạt, 159 assertions**. Frontend `room-move-red-notes`, `service-bill-ownership`, `booking-room-move-history`: **12/12 đạt**. Test frontend kiểm tra logic đang dùng để ánh xạ Đăng ký/modal và lọc owner; không phải nghiệm thu thao tác UI thực tế.
- **Trạng thái Git:** phần sửa 166 và modal trong commit `2507496f`. Người dùng đã cho phép commit hai thay đổi bổ sung tại `BookingRoomController.php`, `ShiftWorkController.php` cùng test và tài liệu của lần rà soát này. Không push.
- Test backend dùng SQLite bộ nhớ, không sửa dữ liệu vận hành. Các ô KQ phía trên để trống cho lần nghiệm thu UI thực tế; không coi test tự động là bằng chứng đã thao tác đủ mọi case trên UI.
