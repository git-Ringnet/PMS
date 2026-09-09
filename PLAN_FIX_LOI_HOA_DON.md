# Kế hoạch sửa lỗi Hóa đơn

Ngày lập: 09/09/2026. Trạng thái: **Đã triển khai code theo plan, chờ UAT và nghiệp vụ còn treo**.

Nguồn: `H:\lỗi Hóa đơn.docx`; bản `D:\PMS\lỗi Hóa đơn.docx` có cùng SHA-256. Sáu ảnh bên dưới được trích trực tiếp từ tài liệu, giữ nguyên thứ tự section. Nội dung tài liệu được dùng làm mô tả lỗi và yêu cầu tham chiếu; các phương án kỹ thuật trong kế hoạch là đề xuất triển khai, không phải nghiệp vụ đã được người dùng chốt.

Màn hình liên quan chính: Hóa đơn/Trả phòng tại `frontend/src/pages/frontdesk/CheckoutPage.vue`. Các nhận định hiện trạng dựa trên đọc code; chưa tái hiện trên ứng dụng hoặc kiểm tra database đang chạy. Không sửa file Word gốc.

## Tổng quan phạm vi

| Section | Hạng mục | Ưu tiên | Phạm vi chính | Tình trạng xác minh |
|---|---|---|---|---|
| 1 | Tách ô tìm kiếm khỏi booking đang hiển thị | P1 | Frontend | Đã sửa, chờ UAT |
| 2 | Căn dòng phòng/khách trong gợi ý tìm kiếm | P2 | Frontend | Đã sửa, chờ UAT |
| 3 | Đồng bộ font, số 0, dấu phân cách và màu chữ | P2 | Frontend | Đã sửa formatter và số, chờ UAT |
| 4 | Tên booking dài che cột Tổng DV/Đã TT | P1 | Frontend | Đã sửa table layout/ellipsis, chờ UAT |
| 5 | Mã thanh toán tăng tuần tự | P1 | Backend, migration, kiểm thử | Đã thêm sequence transaction-safe, chờ kiểm thử đồng thời |
| 6 | Lưu đúng khách khi thanh toán tại dòng khách | P1 | Frontend + backend | Đã chuẩn hóa context/validate, chờ tái hiện UAT |

P1: ảnh hưởng thao tác hoặc dữ liệu; P2: chuẩn hóa hiển thị. Section 2–4 dùng chung nền typography/layout, cần thực hiện đồng bộ. Section 5–6 dùng chung luồng thanh toán, cần kiểm tra chung sau khi sửa.

## Section 1 — Tìm kiếm: giữ booking sau khi xóa nội dung search

![Ảnh 1 — Hành vi tìm kiếm](docs/invoice-fixes/images/image1.png)

**Yêu cầu từ tài liệu**

- Khi chọn một booking trong gợi ý, tải booking vào vùng chi tiết bên dưới.
- Không đưa mã booking vừa chọn trở lại ô Search; sau khi chọn, ô tìm kiếm trống để nhập lượt tiếp theo.
- Xóa chuỗi tìm kiếm không làm mất booking đang hiển thị.

**Hiện trạng trong code**

`selectBookingFromSearch()` gán booking vào `displayedBookingsList` rồi gán `b.code` vào `searchQuery`. `watch(searchQuery)` xóa `displayedBookingsList` khi chuỗi trống và có thể xóa query `bookingCode` trên URL. Chỉ đổi giá trị search thành rỗng trong hàm chọn sẽ kích hoạt watcher và tiếp tục gây lỗi.

**Các bước triển khai**

1. Tách rõ ba trạng thái: chuỗi đang nhập, danh sách gợi ý, booking/phòng/khách đã chọn.
2. Chọn gợi ý: cập nhật ngữ cảnh chi tiết, làm trống chuỗi, đóng dropdown; không xóa booking vừa chọn.
3. Sửa watcher và `clearSearch()` để xóa nội dung tìm kiếm chỉ tác động ô nhập/dropdown. Rà các phụ thuộc `hasCurrentSelectedRoom`, nút thao tác và route.
4. Giữ khả năng mở từ Tìm kiếm chung hoặc URL có booking/phòng; kiểm tra `selectCheckoutBookingFromRoute()` và route watcher.
5. Khi chọn booking mới, reset lựa chọn dịch vụ/thanh toán cũ bằng luồng chọn hiện hữu, tránh thao tác nhầm booking.

**Nghiệm thu**

- [ ] Gõ GAL2 → chọn booking → search trống, booking GAL2 và dữ liệu chi tiết vẫn hiện.
- [ ] Nhập chuỗi khác rồi Backspace hết hoặc bấm X → booking cũ vẫn hiện.
- [ ] Tìm không có kết quả → thông báo tại gợi ý, không xóa chi tiết đã chọn.
- [ ] Chọn trực tiếp phòng/khách từ gợi ý → đúng ngữ cảnh và search trống.
- [ ] Mở từ màn Tìm kiếm chung/URL và đổi bộ lọc không phát sinh vòng lặp route hoặc xóa nhầm lựa chọn.

**Nghiệp vụ bạn bổ sung**

- Khi focus vào search đang trống: hiện tất cả gợi ý theo bộ lọc hay chỉ hiện khi nhập? **Chưa chốt**.
- Khi đổi bộ lọc khiến booking đang chọn không còn trong kết quả: giữ chi tiết hay xóa lựa chọn? **Chưa chốt**.
- Ghi chú bổ sung: …

## Section 2 — Tìm kiếm: bố cục dropdown

![Ảnh 2 — Căn phòng và bỏ in đậm](docs/invoice-fixes/images/image2.png)

**Yêu cầu từ tài liệu**

Đưa số phòng thụt vào sau vị trí mã booking, bỏ dấu phân cách `|`; chỉ in đậm dòng mã/tên booking. Các dòng số phòng và tên khách dùng chữ thường.

**Các bước triển khai**

1. Chỉnh template dropdown trong `CheckoutPage.vue`, cả nhánh nhiều khách và nhánh chỉ có `guestName`.
2. Dùng lưới/cột với khoảng cách thống nhất thay vì dấu phân cách hoặc căn bằng ký tự trắng. Rà cả đường viền đứng có thể tạo cảm giác dấu `|`.
3. Bỏ `font-bold` trên phòng và tên khách, bao gồm khách đầu tiên hiện được áp class theo `gIdx === 0`.
4. Giới hạn tên booking/tên khách bằng ellipsis và cho xem đầy đủ khi hover/focus. Giữ vùng click chọn từng khách.

**Nghiệm thu**

- [ ] Dropdown khớp phân cấp của ảnh 2, không còn vạch ngăn phòng và khách.
- [ ] Chỉ dòng booking in đậm; phòng và tất cả khách không in đậm.
- [ ] Phòng có nhiều khách, tên dài, mã phòng 3–5 ký tự đều căn nhất quán.
- [ ] Click phòng/khách không bị sự kiện dòng booking ghi đè lựa chọn.

**Nghiệp vụ bạn bổ sung**

- Mức thụt lề cụ thể nếu có chuẩn riêng: …
- Cách hiển thị khách ở ghép/thứ tự khách: …

## Section 3 — Giao diện: font, số 0 và định dạng số

![Ảnh 3 — Typography và định dạng tiền](docs/invoice-fixes/images/image3.png)

**Yêu cầu từ tài liệu và chú thích ảnh**

- Đồng bộ cỡ chữ màn hóa đơn với các màn khác.
- Số 0 không có dấu gạch/chấm bên trong.
- Dấu `,` phân cách hàng nghìn, dấu `.` phân cách thập phân: `600,000`, `1,500,000`, `1,234.56`.
- Đổi chữ tại các vị trí được đánh dấu sang đen, gồm tên/mã dịch vụ và số tiền thanh toán.

**Hiện trạng trong code**

Các hàm `formatMoney`, `formatSummaryMoney`, `formatInvoiceMoney`, `formatInvoiceQuantity` đang dùng `Intl.NumberFormat('vi-VN')`. Hàm summary còn làm tròn số nguyên. Màn hình có nhiều `font-mono`, style 9–11px và CSS ghi đè cuối file. Font chung của dự án là Inter trong `frontend/src/style.css`; tham chiếu thiết kế tại `STYLE_GUIDE.md`.

**Các bước triển khai**

1. Đối chiếu cỡ chữ với màn lễ tân đang dùng và STYLE_GUIDE; đề xuất nội dung chính tối thiểu 12px, chốt bằng ảnh preview cùng mức zoom.
2. Bỏ font monospace tại dữ liệu ngày/giờ, tiền và folio cần sửa; dùng font chung, có thể dùng tabular numerals để căn số sau khi kiểm tra hình số 0 thực tế.
3. Thống nhất formatter trong phạm vi hóa đơn với dấu theo tài liệu, có thể dùng locale `en-US`. Truyền số gốc, không thay chuỗi dấu bằng thao tác replace.
4. Áp dụng cho bảng booking, dịch vụ, thanh toán, tổng cuối bảng, thẻ folio và preview hóa đơn trong màn hình. Không đổi công thức tính, precision database hay format toàn dự án.
5. Tách quy tắc hiển thị tiền và số lượng; ghi rõ số chữ số thập phân sau khi nghiệp vụ bổ sung. Rà riêng `formatSummaryMoney` để không che phần lẻ trái quy tắc.
6. Đổi màu chữ đúng các vị trí chú thích. Xác định các màu trạng thái/nghiệp vụ còn cần giữ; rà CSS trùng/ghi đè để thay đổi có hiệu lực.

**Nghiệm thu**

- [ ] `0`, `600000`, `1500000`, `1234.56`, `-1234.56` hiển thị đúng; phần lẻ theo quy tắc được chốt.
- [ ] Dữ liệu số dạng string từ API được xử lý đúng, không hiển thị NaN.
- [ ] Số 0 không có ký hiệu giữa; cỡ chữ đọc được và nhất quán với màn tham chiếu.
- [ ] Các ô/tổng/folio biểu diễn cùng một số theo cùng quy tắc; không thay đổi giá trị tính toán.
- [ ] Vị trí đánh dấu “đổi chữ đen” đã được đối chiếu bằng ảnh trước/sau.

**Nghiệp vụ bạn bổ sung**

- VND hiển thị phần lẻ khi có hay luôn làm tròn? Nếu làm tròn, quy tắc và số chữ số: …
- Ngoại tệ/số lượng cho phép tối đa mấy chữ số thập phân: …
- Màn hình dùng làm chuẩn cỡ chữ: …
- Phạm vi mở rộng sang modal nhập tiền, bản in/PDF và xuất file: **chưa chốt; nếu mở rộng cần rà parser và template riêng**.

## Section 4 — Giao diện: tên booking dài và các cột tổng

![Ảnh 4 — Giới hạn tên booking](docs/invoice-fixes/images/image4.png)

**Yêu cầu từ tài liệu**

Tên booking dài hiển thị `…`, luôn thấy hai cột Tổng DV và Đã TT. Số phòng thụt vào để dễ nhìn; tên khách không in đậm. Chú thích ảnh còn yêu cầu tăng cỡ mã booking cho đồng đều.

**Hiện trạng trong code**

Bảng trái dùng auto layout; dòng booking có ô `colspan="2"`, bên trong flex/nowrap và tên có `truncate` nhưng thiếu ràng buộc độ rộng toàn chuỗi. Mã booking dùng `text-[9px]`; dòng khách đầu tiên và nhánh một khách vẫn in đậm.

**Các bước triển khai**

1. Đặt ngân sách chiều rộng cho checkbox, mã/phòng, tên và hai cột tiền; dùng fixed table layout/colgroup hoặc cấu trúc tương đương phù hợp `colspan`.
2. Cho vùng tên co lại với `min-width: 0`, overflow hidden và ellipsis; bảo vệ chiều rộng mã và các cột tiền.
3. Bổ sung xem tên đầy đủ qua hover/focus; không cắt giá trị lưu trữ.
4. Thụt lề số phòng, bỏ in đậm tên khách ở mọi nhánh. Đồng bộ cỡ mã booking với Section 3.
5. Kiểm tra sau khi tăng font; tên dài không được làm panel dịch vụ tràn sang che tiền.

**Nghiệm thu**

- [ ] Booking tên ngắn/dài 150 ký tự vẫn thấy toàn bộ Tổng DV và Đã TT tại viewport desktop được hỗ trợ.
- [ ] Tên dài có ellipsis và xem được đầy đủ; mã booking vẫn phân biệt được.
- [ ] Kiểm tra tiền lớn, tiền âm, nhiều khách và bật/tắt sidebar.
- [ ] Kiểm tra 1366×768 và 1920×1080, zoom 100%; đề xuất thêm 125% để phát hiện tràn khi tăng font.

**Nghiệp vụ bạn bổ sung**

- Độ phân giải/zoom thực tế tại quầy: …
- Giới hạn số tiền hiển thị cần hỗ trợ và có cần mở rộng panel trái không: …

## Section 5 — Bảng payments: mã thanh toán tăng tuần tự

![Ảnh 5 — Mã thanh toán nhảy không tuần tự](docs/invoice-fixes/images/image5.png)

**Yêu cầu từ tài liệu**

Lần thanh toán đầu là 1, lần tiếp theo là 2, tăng lũy tiến; không xuất hiện trình tự như 11122 rồi 473. Tài liệu ghi `payments_id`, nhưng ảnh và schema thực tế là **`payments.payment_id`**. Cần phân biệt với **`payments.id`**, khóa của từng dòng dữ liệu.

**Nguyên nhân đã thấy trong code**

`backend/app/Http/Controllers/Api/PaymentController.php`, hàm `settlePayment()`, đang tạo mã bằng `Payment::max('id')` cộng `rand(100, 500)`. Một lần settlement có thể tạo nhiều dòng theo phương thức thanh toán và gắn cùng mã vào cọc/dịch vụ. Vì vậy không thay máy móc bằng ID của từng dòng hoặc áp unique trên `payments.payment_id`.

**Các bước triển khai**

1. Chốt đơn vị đánh số: đề xuất **một mã cho một lần xác nhận thanh toán**, nhiều phương thức cùng lần dùng chung mã, phù hợp luồng hiện hữu.
2. Rà toàn bộ nơi đọc/ghi mã: payments, ServiceBill, booking_room_services, in/tra cứu/hủy thanh toán và các module có chung miền mã. Phân biệt các trường cùng tên nhưng tham chiếu `payments.id`, ví dụ payment_debt_settlements.
3. Thiết kế bộ đếm settlement tập trung theo phạm vi đã chốt, dự kiến service riêng và migration bảng sequence. Tạo sẵn dòng sequence cho từng phạm vi; khóa/cập nhật nguyên tử trong transaction cùng thao tác thanh toán. Không dùng `max + 1` thiếu khóa.
4. Lấy mã một lần trong transaction, dùng thống nhất cho các dòng và liên kết liên quan; đảm bảo concurrent requests không cấp trùng. Xử lý khởi tạo sequence và retry khi xung đột.
5. Với dữ liệu mới rỗng, sequence bắt đầu 1. Với dữ liệu đang có: lập báo cáo read-only về miền mã và trùng mã; đề xuất khởi tạo cao hơn mã hiện hữu trong toàn bộ miền liên kết, không chỉ `payments.id`.
6. Không đánh lại mã lịch sử trong bản sửa mặc định. Nếu cần renumber, tách migration có bảng ánh xạ, kiểm tra đầy đủ liên kết, sao lưu và dry-run để duyệt riêng.
7. Kiểm tra gửi lặp/double click; xác định cơ chế chống ghi thanh toán hai lần theo cùng yêu cầu, ngoài cơ chế chống trùng số.

**Nghiệm thu**

- [ ] Database test rỗng: hai lần thanh toán thành công tạo mã 1 rồi 2.
- [ ] Một lần có tiền mặt + chuyển khoản dùng chung mã nếu nghiệp vụ chốt theo settlement; lần sau tăng đúng một.
- [ ] Hai phiên thanh toán đồng thời nhận mã khác nhau, không ghi đè/liên kết chéo.
- [ ] Lỗi giữa transaction không để lại payment hoặc liên kết thanh toán dở dang; hành vi số bị bỏ trống theo quy tắc đã chốt.
- [ ] Gửi lại cùng yêu cầu không thu hai lần theo cơ chế chống lặp được lựa chọn.
- [ ] Dữ liệu cũ vẫn tra cứu, in, hủy và đối trừ đúng; cọc chưa settlement vẫn có mã null theo nghiệp vụ hiện hữu.

**Nghiệp vụ bạn bổ sung — cần chốt trước khi triển khai đánh số**

| Điểm cần chốt | Đề xuất ban đầu | Bạn bổ sung |
|---|---|---|
| Phạm vi dãy số | Theo chi nhánh/database nghiệp vụ; cần xác minh kiến trúc thực tế | … |
| Reset số | Không reset theo booking/ngày/năm | … |
| Đơn vị tăng | Một lần xác nhận thanh toán, không phải từng dòng payments | … |
| Hủy/rollback | Không tái sử dụng mã đã phát hành; quy tắc không hở số cần định nghĩa riêng | … |
| Dữ liệu cũ | Giữ mã lịch sử, tiếp nối từ mốc không trùng | … |
| “Bắt đầu 1” | Áp dụng database mới; nếu áp dụng dữ liệu đang dùng cần phương án chuyển đổi riêng | … |

Mã `invoiceCode` cũng đang có logic random nhưng là miền khác; ghi nhận để đánh giá liên quan, chưa tự đưa việc đổi mã hóa đơn VAT vào phạm vi Section 5.

## Section 6 — Bảng payments: lưu mã khách theo dòng được chọn

![Ảnh 6 — guest_id bị null khi thanh toán tại phòng](docs/invoice-fixes/images/image6.png)

**Yêu cầu từ tài liệu**

Thanh toán tại dòng khách nào thì lưu mã khách đó; giữ mã phòng thuê tương ứng. Thanh toán tại booking chỉ gắn phạm vi booking, không gắn phòng/khách. Các trường khác như công ty vẫn theo nghiệp vụ hiện hữu.

**Hiện trạng cần phân biệt với ảnh lỗi**

Code hiện tại đã có chuỗi `selectedGuestId` → prop `PaymentModal.selectedGuestId` → payload `guest_id` → `Payment::create(guest_id)`. `selectBookingHeader()` reset guest về null. `selectRoomItemRow()` chọn khách cụ thể hoặc `r.allGuests[0]`, có thể trả null khi dữ liệu khách thiếu. Backend đọc nhiều alias vào `$reqRoomId`/`$reqGuestId` để lọc nhưng khi create lại đọc trực tiếp snake_case. Chưa đủ bằng chứng kết luận một nguyên nhân duy nhất cho ảnh cũ.

**Các bước triển khai**

1. Tái hiện bằng booking/phòng một khách và nhiều khách; ghi nhận ID khi chọn dòng, props modal, request thực tế và dòng lưu DB. So sánh đường vào từ dropdown, bảng trái, đổi khách và Tìm kiếm chung.
2. Chuẩn hóa ID khách là `guests.id`, tránh nhầm ID bản ghi liên kết booking_room_guests hoặc nhãn tên khách. Kiểm tra nhánh thiếu `allGuests` và dữ liệu cũ.
3. Duy trì selection theo booking + phòng thuê + khách. Modal sử dụng cùng ngữ cảnh với số tiền, cọc và dịch vụ đang hiển thị; không để ngữ cảnh đổi âm thầm khi modal đang mở.
4. Chuẩn hóa request một lần tại backend, dùng cùng bộ ID cho validate, lọc và create. Xác minh phòng thuộc booking, khách thuộc phòng; từ chối cặp không hợp lệ trước khi ghi dữ liệu.
5. Đối với thao tác tại dòng khách, yêu cầu xác định được guest_id. Nếu thiếu, hiển thị lỗi để chọn đúng khách; không tự đoán theo tên hoặc chọn khách đầu tiên trái ý người dùng.
6. Đối với Master: `booking_id` đúng, `booking_room_id = null`, `guest_id = null`; bảo đảm guest cũ không bị truyền theo sau khi đổi lựa chọn.
7. Rà bộ lọc cọc/dịch vụ và tổng theo khách. Hiện có nhánh coi cọc `guest_id = null` là thuộc phạm vi khách đang thanh toán; cần quy tắc rõ cho cọc chung phòng để không dùng hai lần hoặc phân bổ sai.
8. Lập danh sách payment lịch sử có phòng nhưng thiếu khách; chỉ backfill khi có bằng chứng xác định duy nhất, không gán hàng loạt theo khách hiện tại của phòng.

**Nghiệm thu**

- [ ] Chọn khách A → tất cả dòng tạo trong lần thanh toán lưu đúng booking, phòng thuê và guest A.
- [ ] Cùng phòng có khách A/B → thanh toán B không lưu A, không lấy nhầm cọc/dịch vụ của A.
- [ ] Chuyển từ khách sang booking → payment mới có room/guest null.
- [ ] Thiếu khách hoặc gửi guest thuộc phòng/booking khác → API trả lỗi, không ghi payment hay tiêu thụ cọc.
- [ ] Một lần nhiều phương thức đều lưu cùng ngữ cảnh khách.
- [ ] Tải lại trang → lọc theo khách và tổng tiền vẫn đúng; mã khách không mất sau lưu.
- [ ] Cọc chung phòng và payment lịch sử null guest được xử lý theo nghiệp vụ đã chốt.

**Nghiệp vụ bạn bổ sung**

- Có cho thanh toán ở cấp phòng nhưng chưa chọn khách không? Nếu có thì guest_id cần lưu gì: …
- Cọc chung phòng được dùng cho một khách như thế nào; có phân bổ/tách cọc không: …
- Khi khách đổi phòng, thanh toán cũ giữ quan hệ tại thời điểm phát sinh hay chuyển theo khách: …
- Có yêu cầu sửa dữ liệu lịch sử không, nguồn chứng minh mã khách: …

## Thứ tự thực hiện và đầu ra

| Giai đoạn | Công việc | Đầu ra | Ước lượng sơ bộ |
|---|---|---|---|
| 1 | Tái hiện 6 lỗi, bổ sung nghiệp vụ đánh số/khách/hiển thị phần lẻ | Bộ dữ liệu test, ảnh trước sửa, các quyết định nghiệp vụ | 0.5–1 ngày |
| 2 | Sửa Section 1; làm chung Section 2, 3, 4 | Bản giao diện và ảnh sau sửa tương ứng 1–4 | 1–2 ngày |
| 3 | Sửa Section 6, chốt API/ngữ cảnh; triển khai Section 5 | Sửa backend/frontend liên quan, sequence migration, test dữ liệu và đồng thời | 1.5–2.5 ngày |
| 4 | Hồi quy liên luồng, UAT theo 6 ảnh và kiểm tra dữ liệu | Checklist nghiệm thu, kết quả test, danh sách tồn đọng | 0.5–1 ngày |

Tổng dự kiến **3.5–6.5 ngày công**, là ước lượng kế hoạch cho một người thực hiện, chưa gồm chờ nghiệp vụ, xử lý dữ liệu lịch sử phức tạp hoặc thay đổi bản in/VAT. Có thể thực hiện Section 1–4 trong khi bạn bổ sung quy tắc Section 5–6.

## Kế hoạch kiểm thử và bàn giao

1. Chuẩn bị dữ liệu test riêng: booking tên dài, phòng một khách, phòng nhiều khách, cọc Master, cọc chung phòng, cọc theo khách, folio A/1/2/3, thanh toán nhiều phương thức và dữ liệu legacy null guest.
2. Frontend: kiểm tra thao tác search/selection và formatter bằng test hành vi phù hợp hạ tầng hiện có; chạy `npm run build` trong frontend. Dùng ảnh thực tế kiểm tra Section 2–4, không tạo test chỉ soi tên CSS.
3. Backend: bổ sung feature test cho settlement sequence, liên kết khách/phòng/booking và rollback. Test đồng thời trên cùng loại database với môi trường triển khai để kiểm chứng khóa; không chỉ dựa vào SQLite.
4. Hồi quy có mục tiêu: checkout, hủy/đối trừ thanh toán, đổi folio, chuyển cọc, công nợ, tra cứu và in từ mã thanh toán. Tham khảo các test hiện có `CheckoutBusinessRulesTest`, `CheckoutRestoreTest`, `DebtSettlementTest`; bổ sung settlement test riêng khi chưa được bao phủ.
5. Triển khai sau nghiệm thu: kiểm tra sequence trên staging, sao lưu trước migration dữ liệu, giữ mapping nếu có chuyển đổi. Rollback ứng dụng phải tránh quay về bộ sinh mã random; không drop sequence đang dùng hoặc đánh lại mã tùy ý.
6. Bàn giao mỗi section gồm: ảnh trước/sau, file/hàm đã đổi, test đã chạy, kết quả và nghiệp vụ còn treo. Chỉ đánh dấu hoàn tất khi tiêu chí section đạt và các điểm nghiệp vụ bắt buộc đã được chốt.

## Theo dõi cập nhật

| Section | Phân tích | Nghiệp vụ bổ sung | Triển khai | Test/UAT |
|---|---|---|---|---|
| 1 | Đã đối chiếu | Chờ bổ sung tùy chọn search/filter | Đã triển khai | Chờ browser UAT |
| 2 | Đã đối chiếu | Quy cách căn dòng đã chốt theo grid 3 cột chung | Đã triển khai | Đã parse Vue SFC; chờ browser UAT |
| 3 | Đã đối chiếu | Chờ quy tắc phần lẻ/phạm vi | Đã triển khai theo tối đa 2 chữ số lẻ; bổ sung formatter `en-US` ổn định cho `PrepaymentModal.vue` và `PaymentModal.vue`, để số 0 hiển thị rõ `0` và dùng tabular numerals cho ô tiền | Đã parse Vue SFC; chờ browser UAT |
| 4 | Đã đối chiếu | Chờ xác nhận viewport thực tế | Đã triển khai bảng hiển thị với ô tên booking riêng, fixed columns, ellipsis, cột trái `minmax(410px,430px)` và phòng dòng con `pl-10` | Đã parse Vue SFC; chờ UAT 1366×768 và 1920×1080 |
| 5 | Đã xác định nguyên nhân trong code | Chờ phạm vi, reset, lịch sử | Đã triển khai sequence transaction-safe | Đã đạt unit sequence; chờ test concurrent |
| 6 | Đã xác định đường lưu và thêm validate | Chờ cấp phòng/cọc chung/lịch sử | Đã triển khai frontend/backend | Đã đạt PHP lint; chờ E2E |

## Cập nhật triển khai ngày 09/09/2026

Đã triển khai các thay đổi trong workspace. Những mục nghiệp vụ chưa được chốt vẫn giữ nguyên theo nguyên tắc an toàn dữ liệu; không backfill hoặc đánh lại mã lịch sử.

| Section | File/hàm đã cập nhật | Trạng thái triển khai | Xác minh |
|---|---|---|---|
| 1 | `frontend/src/pages/frontdesk/CheckoutPage.vue`: `selectBookingFromSearch`, watcher `searchQuery`, `clearSearch` | Đã sửa: chọn booking làm trống ô tìm kiếm nhưng giữ booking/chi tiết; xóa nội dung tìm kiếm không xóa lựa chọn | Chưa có browser UAT |
| 2 | `frontend/src/pages/frontdesk/CheckoutPage.vue`: template dropdown | Đã sửa: dòng cha và mọi dòng con dùng chung `grid-cols-[50px_75px_minmax(0,1fr)]`; BKK/mã/tên là 3 cột cố định, dòng con để trống cột BKK rồi đặt phòng dưới mã và khách dưới tên; bỏ dấu `|`, chỉ booking in đậm, ellipsis có title | Đã parse Vue SFC; chờ browser UAT |
| 3 | `frontend/src/pages/frontdesk/CheckoutPage.vue`: `formatMoney`, `formatSummaryMoney`, `formatInvoiceMoney`, `formatInvoiceQuantity` và bảng dịch vụ/thanh toán/hóa đơn; `frontend/src/pages/frontdesk/components/PrepaymentModal.vue`; `frontend/src/pages/frontdesk/components/PaymentModal.vue` | Đã sửa: locale `en-US`, giữ tối đa 2 chữ số lẻ, số 0 hiển thị `0`, dùng tabular numerals thay `font-mono` ở các ô tiền, đổi mã dịch vụ và số tiền thanh toán về chữ đen; không đổi parsing/input hoặc công thức | Đã parse Vue SFC; chờ browser UAT |
| 4 | `frontend/src/pages/frontdesk/CheckoutPage.vue`: bảng `checkout-bookings-panel`/`displayedBookingsList`, CSS cuối file và grid chính | Đã sửa: `table-fixed`, `colgroup`, cột tiền cố định; loại bỏ override `table-layout: auto` bằng `table-layout: fixed !important`; dòng booking cha dùng các ô riêng cho mã và tên cùng hàng, tên có `w-full min-w-0 truncate` + `title`, `td` tên có overflow/ellipsis nên không thể đẩy/che Tổng DV và Đã TT; tăng cột trái từ `minmax(360px,380px)` lên `minmax(410px,430px)` để panel kéo tới vị trí chuẩn, giữ service/payment/sidebar trong các cột riêng; tăng padding số phòng dòng con từ `pl-6` lên `pl-10` ở cả nhánh nhiều khách và một khách | Đã parse Vue SFC và diff check; chờ browser UAT ở 1366×768/1920×1080 |
| 5 | `backend/app/Models/PaymentSequence.php`; migration `backend/database/migrations/2026_09_09_000001_create_payment_sequences_table.php`; `PaymentController::nextSettlementCode` và `settlePayment`; `backend/tests/Unit/PaymentSequenceTest.php` | Đã sửa: một mã cho một settlement, cấp số trong transaction qua row lock; database mới bắt đầu 1; database cũ tiếp nối số numeric lớn nhất; không sửa lịch sử. Đã khôi phục các helper `resolvePaymentMethodCode`, `assertActiveDebtPayment`, `resolveDebtSettlementMethod` để các luồng payment/debt settlement vẫn validate và lookup PaymentMethod như trước | Unit test sequence đạt 1 test/4 assertions; PHP lint đạt; chưa có test concurrent trên DB triển khai |
| 6 | `CheckoutPage.vue::openPaymentModal/selectRoomItemRow`; `PaymentModal.vue` đã truyền payload; `PaymentController::settlePayment` | Đã sửa: chọn khách chính đúng theo `isPrimary`; chặn thanh toán dòng phòng khi thiếu guest_id; backend chuẩn hóa room id, xác minh room thuộc booking và guest thuộc room, loại guest đã hủy/no-show/chuyển phòng | PHP lint đạt; chưa có E2E với dữ liệu nhiều khách |

### Kiểm thử đã chạy

- `php -l app/Http/Controllers/Api/PaymentController.php`: đạt.
- `php -l app/Models/PaymentSequence.php`: đạt.
- `php -l database/migrations/2026_09_09_000001_create_payment_sequences_table.php`: đạt.
- `php -l tests/Unit/PaymentSequenceTest.php`: đạt.
- `php artisan test --filter=PaymentSequenceTest --stop-on-failure`: đạt, 1 test/4 assertions.
- Vue SFC parse `PrepaymentModal.vue` và `PaymentModal.vue`: đạt.
- `npm run build` trong `frontend`: chưa chạy qua do môi trường hiện tại không load được native binary `@tailwindcss/oxide-win32-x64-msvc` và gặp `spawn EPERM`; chưa có bằng chứng lỗi Vue/logic từ thay đổi.

### Còn chờ nghiệp vụ/UAT

- Chốt phạm vi/reset/hủy và quy tắc tiếp nối cho dãy payment_id; hiện dùng một dãy `settlement`, không reset, không tái sử dụng mã.
- Chốt cách dùng cọc chung phòng khi thanh toán ở cấp khách; hiện các query legacy vẫn cho phép cọc `guest_id` rỗng trong phạm vi khách để bảo toàn hành vi cũ.
- Chạy UAT theo ảnh 1–6, đặc biệt search giữ chi tiết, tên 150 ký tự ở hai viewport, phòng nhiều khách A/B và hai settlement đồng thời trên DB triển khai.

Khi bạn bổ sung nghiệp vụ, cập nhật trực tiếp mục tương ứng trong Section 1–6, điều chỉnh tiêu chí nghiệm thu cùng lúc và ghi ngày quyết định để tránh lệch giữa mô tả và test.
