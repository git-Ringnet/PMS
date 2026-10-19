# Kế hoạch sửa lỗi Booking 4 — 11 section

Nguồn: `file word can len plan/các lỗi liên quan tới booking 4.docx` (14/10/2026). Ảnh trích ra tại `docs/booking-4/imageN.png`.

## Tổng quan

| # | Lỗi | Nguyên nhân tìm được trong code | Hướng sửa |
|---|---|---|---|
| 1 | Xóa EB 1 ngày báo lỗi; chỉnh SL EB về 0 DB không đổi | Đã sửa ở commit Dòng 368. Còn sót: `GuestController::syncExtraBedServices` (sửa từ Room Map) `forceDelete` EB khi SL = 0 | Giữ dòng EB SL 0 thay vì xóa cứng; test lại luồng Dòng 368 |
| 2 | Cập nhật nhanh ngày đi, `bookings.num_of_days` không đổi | Đã sửa ở commit Dòng 369 (`syncBookingHeaderDates`) | Bổ sung test hồi quy |
| 3 | Phụ phí ăn sáng trẻ: lưu sai bảng, đổi giai đoạn mất phụ phí/giá về 0 | (a) Sửa phòng ở màn Đăng ký (`BookingController::update`) đổi ngày nhưng không đồng bộ `booking_child_breakfast_details`; (b) Dịch vụ tự động mã BD luôn insert `booking_room_services`; (c) Dòng BD trong chi tiết tiền không sửa giá được | Service mới `ChildBreakfastService`: chế độ **detail** khi `Booking_BFChildSetServiceId` = mã dịch vụ "Breakfast Child/Ăn Sáng Trẻ Em" → chỉ ghi `booking_child_breakfast_details`; ngược lại chế độ **service** → ghi `booking_room_services` và ưu tiên hiển thị bảng này. Đổi giai đoạn: giữ giá + cờ phụ phí, chỉ dời ngày. Sửa giá dòng BD theo đêm → cập nhật chi tiết ngày đó |
| 4 | Hủy BK xong nhảy sang màn tạo mới | `handleConfirmCancelReason` xóa tab sau khi hủy | Tải lại chính booking vừa hủy |
| 5 | Rút ngày đi vẫn hiện DV bổ sung ngày ngoài giai đoạn | Hiển thị lấy mọi dòng `booking_room_services`; `update` xóa cứng DV >= ngày đi | Không xóa DV bổ sung (giữ để gia hạn lại như Link Hotel/SP2102), chỉ hiển thị + tính tiền DV dự kiến nằm trong [ngày đến, ngày đi) |
| 6 | Mô tả DV ngày quá khứ khác ngày tương lai | Quá khứ lấy `service_bills.DescriptionServive`, tương lai lấy `service_name` | Chi tiết tiền lấy tên theo `hotel_services.name` cho mọi dòng |
| 7 | Hủy phòng lúc OK lúc báo "No query results for model BookingRoom" | API `findOrFail` trả 404 thô khi phòng không còn hoạt động trong booking (đã hủy/xóa tạm); FE gửi tuần tự không chống bấm lặp | API trả thông báo rõ, coi phòng đã hủy là đã xong; FE chặn gửi trùng, đọc danh sách phòng mới nhất |
| 8 | Khôi phục BK: giai đoạn BK không khớp phòng | Hủy BK không cập nhật ngày BK theo phòng; khôi phục chỉ xét ngày đến của header | Hủy BK: ngày đến/đi BK = min/max ngày các phòng. Khôi phục: xét ngày từng phòng, sau đó tính lại ngày + số đêm BK theo phòng đang hoạt động |
| 9 | Màn hình chọn phòng hủy cần khôi phục | Chưa có | API `GET /bookings/{id}/cancelled-rooms` + `restore` nhận `room_ids`; modal chọn phòng, sắp theo ngày hủy mới nhất, cột có sắp xếp; 1 phòng không đạt → không khôi phục phòng nào |
| 10 | Khóa phòng từ 3/9 chặn đặt đêm 2/9 (out 3/9) | `isRoomNumberOccupied` so `start_date <= ngày đi 23:59:59` | Hàm dùng chung `hasBlockingLock`: khóa chỉ chặn khi bắt đầu trước ngày đi (khoảng nửa mở) |
| 11 | Phụ phí ăn sáng trẻ không hiện trước check-in, mất sau khi thêm DV, không post bill | API `GET /bookings/{id}` không load `children.breakfastDetails` (mọi lần refresh 1 booking làm mất dòng BD); post tiền phòng/sang ngày chỉ post `booking_room_services` | Load đủ quan hệ; post phụ phí ăn sáng trẻ từ chi tiết cùng lúc tiền phòng (post tay trước & sang ngày), không post trùng |

## Quy ước nghiệp vụ đã chốt theo tài liệu

- Chế độ detail: mã cấu hình (`Booking_BFChildSetServiceId`, mặc định BD) trùng mã dịch vụ có tên "Breakfast Child/Ăn Sáng Trẻ Em" trong danh mục dịch vụ.
- Bill phụ phí ăn sáng trẻ: `ServiceId` = mã cấu hình, `CustomerId1` = mã trẻ, folio theo cờ FIT/GIT (`is_room`), mô tả theo định nghĩa dịch vụ khách sạn.
- Section 9 "trường hợp như dòng 378": hiểu là phòng có ngày đến < ngày hệ thống thì không khôi phục (giữ quy tắc khôi phục hiện có). Cần xác nhận lại.

## Kiểm thử

- Backend: `tests/Feature/Booking/BookingFourSectionsTest.php` cho section 2, 3, 5, 7, 8, 9, 10, 11 và hồi quy section 1.
- Frontend: `tests/booking-four-display.test.js` cho lọc DV theo giai đoạn, tên DV, chế độ ăn sáng trẻ.
- Danh sách test tay cuối file `docs/booking-4/TEST_TRUC_TIEP.md`.
