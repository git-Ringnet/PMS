# Kế hoạch cập nhật toàn bộ lỗi Booking — 8 section

Ngày phân tích: 14/09/2026. Trạng thái: **Đã triển khai hoàn tất cả 8 section; build frontend và test backend pass 100%; người dùng đã nghiệm thu thực tế**.

Nguồn: `H:\Fix lỗi liên quan Booking.docx`, bản cập nhật lúc 10:29:09 ngày 14/09/2026, dung lượng 899.754 byte. Đọc lại đầy đủ nội dung và xem cả 8 ảnh. Bản cập nhật có **8 section**, thay cho phạm vi 5 section trước đó.

Tài liệu Word là nguồn mô tả lỗi và mong muốn nghiệp vụ. Kế hoạch này thực hiện yêu cầu phân tích/lập kế hoạch của người dùng; các câu “Fix” hay “TASK” trong Word không được hiểu là lệnh tự triển khai code hoặc sửa database. Phương án kỹ thuật và các quy tắc còn mở được phân biệt bên dưới để người dùng bổ sung nghiệp vụ.

Kết luận hiện trạng dựa trên code trong workspace, chưa tái hiện trên ứng dụng đang chạy, chưa truy vấn database thực tế. Số dòng là vị trí lúc lập kế hoạch; ưu tiên tìm theo tên hàm khi code thay đổi. Ảnh được trích từ Word và lưu tại `docs/booking-fix-plan/`.

## 1. Tổng quan: dự án đã có gì, thiếu gì?

| Section | Yêu cầu | Hiện trạng có bằng chứng trong code | Phần cần cập nhật | Ưu tiên |
|---|---|---|---|---|
| 1 | Tự gán phòng theo tầng thấp → cao, gần nhau | Có tự gán, kiểm tra trống, sort tầng/số phòng; tầng lưu dạng chuỗi | Thứ tự tầng theo số/thứ tự nghiệp vụ, dùng nhất quán trong gán phòng | P2 |
| 2 | Nhập giá ăn sáng trẻ em khác 0 | Có input, lưu chi tiết ngày, API nhận `amount >= 0` | Ngăn giá nhập tay bị tính lại và ghi đè; kiểm tra lưu và phụ thu | P1 |
| 3 | Lấy lại phòng sau hủy, giữ đúng số lượng khi chọn mã giá | Có allocation, mã giá, dựng phòng mới và lưu | Thống nhất cách đếm; tách phòng lịch sử; loại vòng ghi đè quantity | P1 |
| 4 | Chỉ lấy FAM thì không bị chặn vì JST | Có validation AV, bỏ qua allocation có quantity bằng 0 | Kiểm tra payload thật, phân bổ lịch sử, AV và dữ liệu G0000022 | P1 |
| 5 | Thông tin khách chỉ gồm phòng trạng thái 0,1,2,4 | Có lọc phòng hủy và khách chuyển, chưa lọc phòng chuyển | Whitelist trạng thái phòng ở cả tải và khởi tạo khách | P2 |
| 6 | Có ít nhất một phòng đang ở thì booking.status = 1 | Có cập nhật header nhưng chờ không còn phòng BOOKED | Đổi điều kiện; rà các thao tác làm thay đổi trạng thái tổng | P1 |
| 7 | Lưu mã `registration_statuses.booking_status_id` | Hiện chủ động lưu PK `registration_statuses.id`, có test bảo vệ cách cũ | Đổi hợp đồng lưu trữ, API, UI, SQL/báo cáo, migration và test | P1, phạm vi lớn |
| 8 | Đổi ngày đi cập nhật ngay số đêm và tiền phòng từng đêm | Model có tính số đêm; Room Map có refresh; đã có nhiều hàm sinh RM | Tính đêm tức thời trên UI; thống nhất đồng bộ lịch RM, loại đêm dư chưa post | P1 |

Không có section nào đủ bằng chứng để kết luận “đã đáp ứng hoàn toàn”. Phần lớn chức năng nền đã tồn tại; cần sửa hành vi và đồng bộ các luồng. Section 7 là thay đổi cách lưu dữ liệu, section 4 có phần điều tra dữ liệu chưa thể kết luận từ ảnh.

## 2. Section 1 — Tự động gán số phòng theo thứ tự tầng

![Section 1 — Gán 105,106 rồi nhảy lên 1005,1006,1105](D:/PMS/docs/booking-fix-plan/image1.png)

### Mô tả và kết quả mong muốn

Tạo booking lấy 5 SUPD. Hiện ảnh cho thấy gán 105, 106, 1005, 1006, 1105 trong khi các tầng 2,3… còn phòng. Mong muốn ưu tiên các tầng gần nhau từ thấp đến cao.

### Dự án đang có / chưa có

- `BookingRoomController::autoAssign()` khoảng dòng 1151–1189 đã lấy phòng theo loại, `orderBy('floor')->orderBy('room_number')`, kiểm tra khóa và chiếm dụng trong khoảng ở.
- Migration `2026_06_11_000005_create_rooms_table.php:20` khai báo `floor` là chuỗi. Sort chuỗi có thể tạo thứ tự 1,10,11,2,3, phù hợp triệu chứng trong ảnh. Cần xác nhận giá trị tầng thật trước khi chốt nguyên nhân runtime.
- `CreateRegistrationPage.vue:4194` đã gọi API tuần tự cho từng phòng được chọn và đếm thành công/thất bại. Không cần xây lại màn hình tự gán.

### Kế hoạch triển khai

1. Tạo dữ liệu kiểm thử tầng 1,2,3,9,10,11 với nhiều số phòng cùng loại; ghi nhận thứ tự trước sửa.
2. Kiểm kê tầng số, tầng ký hiệu, tầng trống và khu/tòa nhà. Với tầng số dùng thứ tự số; nếu có B1/G/M cần thứ tự cấu hình hoặc mapping nghiệp vụ rõ ràng, không ép tất cả thành 0.
3. Tạo một quy tắc sort dùng lại: thứ tự tầng tăng dần → số phòng theo thứ tự tự nhiên → khóa phụ ổn định. Không suy tầng bằng cách cắt ký tự số phòng.
4. Áp dụng vào endpoint mà màn đăng ký thực sự gọi; rà các điểm tự gán khác để tránh mỗi màn một thứ tự.
5. Giữ điều kiện đúng loại/dạng phòng, trống toàn kỳ, OOO/OOS, phòng đã gán và Do Not Move theo quy tắc hiện có. Kiểm tra chống hai người gán cùng phòng ở thời điểm ghi.
6. Giữ phản hồi rõ số thành công/thất bại khi không đủ phòng; không báo thành công cho phòng bị bỏ qua.

### Nghiệm thu

- [ ] Với fixture có 105,106,205,206,305 đều đủ điều kiện, lấy 5 SUPD cho kết quả đúng thứ tự này trước tầng 10.
- [ ] Tầng 2 hết phòng thì chuyển tầng kế tiếp còn phòng hợp lệ.
- [ ] Phòng trống hôm nay nhưng bận giữa kỳ không được gán.
- [ ] Phòng khóa hoặc khác loại không được chọn để đạt tiêu chí gần nhau.
- [ ] Hai lượt gán đồng thời không tạo trùng phòng trong cùng khoảng ở.

### Nghiệp vụ người dùng bổ sung

- “Gần nhau” chỉ theo tầng/số phòng hay cần vị trí thực tế, cùng cánh/tòa? **Chưa chốt**.
- Ưu tiên lấp tầng thấp hay chọn một tầng cao hơn có đủ cả đoàn? Kế hoạch mặc định theo mô tả: tầng thấp trước.
- Ghi chú: …

## 3. Section 2 — Nhập giá chi tiết ăn sáng trẻ em

![Section 2 — Ô thành tiền ăn sáng](D:/PMS/docs/booking-fix-plan/image2.png)

### Mô tả và kết quả mong muốn

Phòng có một trẻ em; mở Chi tiết ăn sáng, nhập giá khác 0 không được. Giá hợp lệ phải nhập được, lưu đúng và mở lại vẫn đúng.

### Dự án đang có / chưa có

- `ChildBreakfastModal.vue` đã có ô giá ở dòng trẻ và từng ngày, formatter/parser, cờ ăn sáng/miễn phí/phụ phí/FIT–GIT.
- `onParentFieldChange():637` nhận giá nhập nhưng dòng 670 tính lại mọi `d.amount`, sau đó dòng 676 gán lại `child.amount` từ ngày đầu.
- `onDetailFieldChange():684` cũng tính lại `detail.amount` tại dòng 700 bất kể field vừa sửa. Đây là đường ghi đè xác định được từ code; giá mặc định bằng 0 có thể gây triệu chứng trong ảnh.
- `GuestController::updateBreakfastDetail():1122` đã validate `amount` numeric, min 0; chỉ tự tính khi request không có amount. Phần API lưu giá đã tồn tại.

### Kế hoạch triển khai

1. Tách xử lý nhập `amount` khỏi xử lý đổi cờ. Khi sửa giá hợp lệ, giữ số nhập; chỉ áp giá mặc định khi khởi tạo hoặc theo sự kiện nghiệp vụ đã chốt.
2. Xử lý riêng chuỗi đang nhập và giá trị số: xóa hết để nhập lại, paste 90,000, chọn toàn bộ rồi thay, không tạo chuỗi nhiều số 0 hoặc nhảy con trỏ.
3. Với dòng cha, xác định phạm vi áp giá cho các ngày; sửa một dòng ngày không ghi đè các ngày khác. Khi giá các ngày khác nhau, dòng cha phải thể hiện rõ giá đại diện hoặc trạng thái nhiều giá.
4. Kiểm tra quy tắc em bé/miễn phí/không ăn sáng; không tự mở quyền tính phí em bé khi chưa có nghiệp vụ bổ sung.
5. Kiểm tra request lưu đúng giá số, API phản hồi lỗi rõ ràng và đồng bộ BD đúng folio FIT/GIT. Rà trường hợp dịch vụ đã post để không sửa tiền đã ghi nhận một cách ngầm định.

### Nghiệm thu

- [ ] Nhập 90000, 120000, 0; chọn toàn bộ/thay/paste đều được và lưu–mở lại giữ nguyên.
- [ ] Sửa ngày thứ hai không đổi ngày thứ nhất; sửa dòng cha áp đúng phạm vi đã chốt.
- [ ] Đổi FIT/GIT không tự mất giá nhập nếu không có quy tắc đổi giá.
- [ ] Các cờ miễn phí/phụ phí nhất quán; giá âm/không hợp lệ bị chặn ở API.
- [ ] Lưu nhiều lần không tạo trùng BD; kiểm tra khoản đã post riêng.

### Nghiệp vụ người dùng bổ sung

- Dòng cha áp tất cả ngày hay chỉ ngày chưa post? …
- Khi bật/tắt phụ phí: giữ giá nhập tay hay nạp lại giá cấu hình? …
- Ai được sửa giá, giới hạn giá và số lẻ cho phép? …

## 4. Section 3 — Lấy lại phòng sau hủy và chọn mã giá

![Section 3 — Quantity nhảy khi chọn BAR](D:/PMS/docs/booking-fix-plan/image3.png)

### Mô tả và kết quả mong muốn

GAL6 lấy 2 FAM + 1 JST dùng BAR, hủy cả ba rồi lấy lại. Nhập 5 hiện 3; gỡ mã giá nhập số lượng rồi chọn lại BAR thì số lượng về 0. Số lượng người dùng nhập phải ổn định và tạo đúng số phòng mới.

### Dự án đang có / chưa có

- `CreateRegistrationPage.vue:2089–2127` dựng allocation từ `booking_rooms`, chưa lọc trạng thái trước khi gom và lấy mã giá của dòng đầu.
- `getPersistedRoomCount():3096` đếm mọi phòng đã có ID; `getProtectedRoomCount():3102` bao gồm cả trạng thái 3 và 100.
- `updateAllocatedRooms():3110` lấy số yêu cầu trừ số phòng đã lưu để tạo phòng mới. Trong khi watcher `rooms` tại 3207 chỉ đếm phòng **chưa có ID** rồi ghi ngược số đó vào allocation.
- Hai cách hiểu quantity khác nhau giải thích được khả năng nhập 5 rồi bị trừ 2 thành 3; chưa khẳng định dữ liệu GAL6 hiện tại đúng cấu hình đó nếu chưa tái hiện.
- Đã có xử lý giá ngày theo rate code, không cần xây lại dropdown BAR. Cần tách tác động giá khỏi số lượng và phòng lịch sử.

### Kế hoạch triển khai

1. Tái hiện chính xác chuỗi 2 FAM + 1 JST → hủy → mở Lấy phòng → nhập 5 → đổi/gỡ/chọn BAR. Chụp state và request ở từng bước.
2. Chốt và đặt tên rõ số phòng hiện hữu hợp lệ, số phòng lấy thêm, tổng yêu cầu; không dùng cùng một quantity với hai nghĩa.
3. Tách bản ghi hủy/chuyển khỏi tập tính nhu cầu lấy phòng. Bảo toàn lịch sử và ID; bảo vệ lịch sử không có nghĩa cộng lịch sử vào số phòng cần lấy.
4. Chuẩn hóa khâu dựng form, bộ đếm, min input, tăng/giảm, dựng payload theo cùng quy tắc. Rà các trạng thái 1/2/4 theo quyền sửa hiện hữu.
5. Bỏ vòng đồng bộ hai chiều mâu thuẫn; chỉ cho thao tác số lượng tạo/bớt draft. Thay đổi giá chỉ cập nhật giá và chi tiết tiền.
6. Kiểm soát kết quả tải giá bất đồng bộ: đổi BAR → mã khác nhanh thì response cũ không ghi đè lựa chọn mới hoặc số lượng.
7. Sau lưu lấy dữ liệu server làm chuẩn; đóng/mở lại cho cùng số lượng và đúng số ID mới.

### Kết quả triển khai trong workspace (14/09/2026)

- Theo correction mới, tab `Lấy phòng` là form **add-only độc lập**: `quantity` chỉ là số phòng mới cần thêm, mặc định 0; các `booking_room` đã lưu không được prefill, không được tính vào quantity và không nhận giá/người lớn/trẻ em/ngày từ draft này.
- Draft add-room dùng state tách biệt, không dùng chung reference với chi tiết booking. Chỉ request `intent=add_only` thành công mới insert `booking_rooms`; lỗi giữ draft để sửa, còn hủy/reopen sẽ bỏ draft và sau thành công refetch server rồi xóa draft.
- Sửa phòng đã lưu tiếp tục đi qua API/UI chỉnh phòng hiện hữu riêng. Không dùng payload add-room để thay thế, khôi phục hoặc rewrite các dòng đã lưu.
- Các test helper/frontend và `BookingAllocationConsistencyTest` (**5 tests, 17 assertions**) ở trên là kết quả trước correction add-only. Theo yêu cầu hiện tại, chưa viết/chạy test hoặc build cho thay đổi add-only mới.
- Availability và occupancy không còn gọi method không tồn tại `HotelSetting::getSetting()`; cấu hình `FrmOOO_DefineLockByTime` được đọc từ `hotel_configs` qua `HotelConfig`, với fallback `12:00`.
- Chưa nghiệm thu chuỗi GAL6 trên giao diện thật, thao tác bàn phím/paste và đổi BAR nhanh trong trình duyệt. Cần chạy UAT với dữ liệu khách sạn sau khi migration status hoàn tất.

### Nghiệm thu

- [ ] Lặp đúng kịch bản GAL6, nhập 5 vẫn là 5; BAR không làm về 0.
- [ ] Hủy 3 phòng rồi lấy lại 2 FAM tạo đúng 2 phòng mới; ba dòng hủy vẫn giữ lịch sử.
- [ ] Tăng/giảm bằng bàn phím, nút mũi tên, paste cho cùng kết quả.
- [ ] Booking có phòng đang ở không bị tạo lại/xóa nhầm phòng hiện hữu.
- [ ] Đổi mã giá nhanh, API giá lỗi, đóng form không lưu không làm biến đổi phòng đã lưu.

### Nghiệp vụ người dùng bổ sung

- Ô Số lượng khi chỉnh booking là tổng số phòng hợp lệ hay số phòng lấy thêm? …
- Khi lấy lại sau hủy, mặc định giữ BAR cũ hay để trống? Việc tự hiện BAR chưa được coi riêng là lỗi nếu chưa chốt quy tắc này.
- Ghi chú: …

## 5. Section 4 — Chặn sai loại phòng và kiểm tra dữ liệu G0000022

![Section 4 — Chỉ chọn FAM nhưng báo thiếu Suite/JST](D:/PMS/docs/booking-fix-plan/image4.png)

### Mô tả và kết quả mong muốn

GAL6 chỉ chọn 2 FAM, JST hiển thị 0, lưu lại báo thiếu Suite với AV -1. Word ghi nhận mã G0000022 lỗi data. Đây là thông tin cần kiểm chứng, chưa đủ để kết luận bản ghi nào phải sửa.

### Dự án đang có / chưa có

- `BookingController::validateRoomAllocations():1861` đã bỏ qua quantity <= 0 tại dòng 1891. Vì vậy không nên lập phương án chỉ thêm “bỏ qua số lượng 0”; code đã có.
- Nhánh lỗi thiếu phòng tại 1913–1915 chỉ chạy khi allocation có quantity dương. Cần bắt payload và xác định JST bị dựng lại từ lịch sử hay lỗi từ luồng khác.
- Luồng update tại 884–910 tạm soft-delete các phòng BOOKED để kiểm tra AV rồi khôi phục khi lỗi. Cần rà transaction và các nhánh khôi phục.
- Chữ ký validator có `excludeBookingId`; lời gọi `getAvailability()` tại 1907–1911 không truyền tham số này. Phải kiểm tra toàn bộ cách tính AV, không giả định booking đã được loại trừ đúng trong mọi nhánh.

### Kế hoạch triển khai

1. Điều tra chung với section 3: so sánh form, `syncRoomsToAllocations()`, payload, danh sách allocation server validate và lỗi trả về.
2. Với dữ liệu thử có AV FAM đủ, gửi trực tiếp FAM=2/JST=0: nếu API qua nhưng UI lỗi, tập trung dựng payload; nếu vẫn lỗi, truy luồng validation cụ thể.
3. Chuẩn hóa payload chỉ chứa nhu cầu hợp lệ; lịch sử hủy/chuyển không trở thành phòng cần đặt mới. Kiểm tra quyền sở hữu mọi `bookingRoomId` trong request.
4. Rà `RoomAvailabilityService` về trạng thái chiếm dụng, soft-delete, khoảng [đến, đi), room lock và phòng của chính booking. Tính nhu cầu theo loại và từng ngày khi các phòng có kỳ ở khác nhau.
5. Tách kiểm tra sức chứa khỏi việc sửa trạng thái tạm nếu có thể; bảo đảm lưu/khôi phục trong transaction, không để phòng biến mất khi validation fail.
6. Điều tra G0000022 trên bản sao dữ liệu: xác định đó là mã của bảng nào, quan hệ với GAL6, status, deleted_at, booking phòng, khách, log hủy và dịch vụ. Không suy mã G0000022 chính là booking_code.
7. Nếu xác nhận dữ liệu sai: lập danh sách ID, giá trị trước/sau, lý do, tác động AV; chuẩn bị script có dry-run, backup, transaction, kiểm tra chạy lại và rollback. Tách việc sửa dữ liệu cũ khỏi sửa nguyên nhân tạo lỗi mới.

### Kết quả triển khai trong workspace (14/09/2026)

- Validator nhận biết nhu cầu mới theo từng loại và kỳ ở; allocation có quantity bằng 0 không gọi kiểm tra AV. `bookingRoomId` được giới hạn trong booking đang sửa, ID trùng trong payload bị chặn, và phòng đã hủy/no-show/chuyển không được khôi phục ngầm.
- Kiểm thử backend đã bao phủ FAM đủ phòng với JST bằng 0 và trường hợp partial-inhouse; kiểm tra status mapping cũng chạy với mã nghiệp vụ khác PK. Chưa truy vấn, sửa hoặc kết luận bản ghi G0000022.
- Chưa chạy migration trên MySQL vận hành. Test SQLite không thay thế kiểm tra DDL/procedure/collation/quyền MySQL; cần backup, dry-run và đối soát trước cửa sổ triển khai.

### Nghiệm thu

- [ ] FAM=2/JST=0, FAM đủ phòng: lưu thành công dù JST đang âm do dữ liệu/booking khác.
- [ ] FAM thiếu thật: chặn đúng FAM, báo đúng ngày/nhu cầu/AV; không đổi dữ liệu sau lỗi.
- [ ] Phòng hủy/chuyển không chiếm AV sai; thao tác đặt phòng đồng thời không vượt quy tắc sức chứa.
- [ ] Dữ liệu được sửa, nếu cần, có báo cáo trước/sau và không thay đổi hóa đơn/lịch sử ngoài phạm vi.

### Nghiệp vụ người dùng bổ sung

- Bổ sung khách sạn/database, mã đặt phòng và ý nghĩa G0000022 khi bước điều tra dữ liệu bắt đầu: …
- Quy tắc cho phép âm loại phòng áp dụng cấu hình nào trong vận hành thực tế? …

## 6. Section 5 — Lọc phòng trong Thông tin khách

![Section 5 — Phòng chuyển còn xuất hiện và số khách NaN](D:/PMS/docs/booking-fix-plan/image5.png)

### Mô tả và kết quả mong muốn

Thông tin khách chỉ hiển thị phòng có trạng thái **0,1,2,4**; loại phòng hủy 3 và phòng chuyển 100. Ảnh còn có “NaN khách”, cần kiểm tra cùng vùng hiển thị.

### Dự án đang có / chưa có

- `GuestController::bookingGuests():34` chỉ loại phòng CANCELLED, vì vậy phòng 100 vẫn có nhóm tiêu đề dù khách chuyển đã bị lọc tại dòng 55.
- `initGuests():67` cũng chỉ loại CANCELLED; mở modal gọi `initBookingGuests()` trước khi tải khách (`GuestInfoModal.vue:603–610`). Chỉ sửa API đọc sẽ còn khả năng khởi tạo khách cho phòng lịch sử.
- API hiện trả danh sách nhóm, UI gán trực tiếp vào `guestData`; cần thống nhất lọc trước khi tạo nhóm.

### Kế hoạch triển khai

1. Dùng tập trạng thái cho riêng màn Thông tin khách: `[0,1,2,4]`; áp dụng ở API danh sách và chặn khởi tạo trên phòng ngoài tập này.
2. Tách quyền hiển thị khỏi quyền tạo khách: trạng thái 2/4 được xem theo Word, nhưng không mặc định tạo guest trống mới cho phòng đã kết thúc nếu chưa chốt.
3. Chuẩn hóa collection thành mảng liên tục, tổng khách thành số; kiểm tra người lớn/trẻ em và nhóm không khách để loại NaN.
4. Dùng cùng tập hiển thị cho bảng, chế độ sửa, chọn khách và export. Bảo toàn màn lịch sử chuyển phòng ở nơi khác.
5. Rà chuyển phòng nhiều lần, ID khách/phòng đích, tránh gộp trùng hoặc hiển thị lại khách ở phòng nguồn.

### Nghiệm thu

- [ ] Fixture đủ status 0,1,2,3,4,100: chỉ 0,1,2,4 xuất hiện.
- [ ] Mở modal nhiều lần không khởi tạo khách cho phòng 3/100.
- [ ] Chuỗi chuyển 109 → 1009 → 1109 chỉ hiện nhóm đúng trạng thái; dữ liệu lịch sử vẫn còn.
- [ ] Nhóm không khách hiển thị 0, không NaN; export khớp bảng.

### Nghiệp vụ người dùng bổ sung

- Phòng checkout/noshow thiếu tên khách có được bổ sung hoặc khởi tạo khách mặc định không? …
- Ghi chú: …

## 7. Section 6 — Booking có một phòng check-in thì status = 1

![Section 6 — Booking có phòng ở nhưng header vẫn status 0](D:/PMS/docs/booking-fix-plan/image6.png)

### Mô tả và kết quả mong muốn

Ngay khi có ít nhất một phòng đang ở (`booking_rooms.status = 1`), `bookings.status` phải bằng 1 dù vẫn có phòng đặt trước.

### Dự án đang có / chưa có

- `BookingRoomController.php:780–786` chỉ đổi header khi truy vấn không còn phòng BOOKED. Đây là điều kiện khác yêu cầu mới.
- Luồng undo check-in đã có kiểm tra phòng inhouse còn lại ở khoảng 849–854; cần dùng nhất quán với check-in, checkout, restore, hủy/chuyển.
- `BookingController.php:746` dùng `Booking::STATUS_CHECKIN` để quyết định nhánh chỉ cho thêm phòng. Đổi status sớm có thể đổi quyền/thao tác sửa booking, phải kiểm tra cùng section 3–4.
- `bookings.status` là trạng thái vòng đời, khác `registration_status_id` của section 7; không dùng hai trường thay nhau.

### Kế hoạch triển khai

1. Sửa điều kiện sau check-in thành tồn tại phòng status 1, cập nhật cùng transaction với trạng thái phòng.
2. Tập trung quy tắc tổng hợp header vào một hàm/service dùng chung. Quy tắc chắc chắn: có phòng 1 → header 1.
3. Với trường hợp không còn phòng 1, lập bảng quyết định theo trạng thái còn lại, giữ quy tắc đã có nếu phù hợp; đưa các tổ hợp chưa được Word mô tả vào nghiệp vụ bổ sung.
4. Rà mọi điểm ghi header: check-in từng phòng/nhiều phòng, undo, checkout phòng/booking, restore checkout, chuyển phòng, hủy/noshow và job liên quan.
5. Kiểm tra booking check-in một phần vẫn cho phép thao tác hợp lệ với các phòng chưa đến; API không chỉ dựa header mà bỏ mất quyền sửa theo trạng thái phòng.
6. Chuẩn bị backfill có dry-run cho booking đang có phòng 1 nhưng header sai; chỉ sửa tập sai đã xác minh.

### Nghiệm thu

- [ ] `[0,0,1]`, `[1]`, `[1,2,3,100]` đều có header 1 ngay sau commit.
- [ ] Undo một phòng khi còn phòng 1: header vẫn 1.
- [ ] Undo/checkout phòng 1 cuối: header theo bảng quyết định đã chốt, không kẹt 1.
- [ ] Chuyển phòng không làm header rơi về 0 giữa luồng.
- [ ] Booking check-in một phần vẫn lấy thêm/sửa phần được phép; thao tác bị cấm vẫn bị API chặn.

### Nghiệp vụ người dùng bổ sung

- Hết phòng đang ở nhưng còn phòng đặt trước: header về 0 hay vẫn coi booking đã bắt đầu lưu trú? …
- Tất cả còn lại chỉ là checkout/hủy/noshow: thứ tự ưu tiên trạng thái tổng? …

## 8. Section 7 — Lưu mã tình trạng tương thích hệ thống cũ

![Section 7 — id và booking_status_id khác nhau](D:/PMS/docs/booking-fix-plan/image7.png)

### Mô tả và kết quả mong muốn

Chuyển từ `bookings.registration_status_id = registration_statuses.id` sang `bookings.registration_status_id = registration_statuses.booking_status_id`. Ví dụ ảnh: None Guaranteed có id=2, mã nghiệp vụ=20, booking phải lưu 20. Guaranteed có hai giá trị cùng bằng 1 nên chỉ test Guaranteed sẽ không phát hiện sai mapping.

### Dự án đang có / chưa có

- `Booking.php:132–134` ghi chú rõ đang lưu PK và quan hệ `belongsTo(..., 'id')`. Ghi chú “dòng 137” trong Word được đối chiếu bằng đoạn quan hệ này, không coi số dòng trong ảnh là vị trí cố định.
- `BookingController.php:307,769` validate `exists:registration_statuses,id`; các nhánh tìm tình trạng dùng `find()`, hủy có nhánh lưu `$configuredStatus->id`.
- `CreateRegistrationPage.vue:1501` tìm nhãn theo `rs.id`; dropdown và payload cần rà đồng bộ.
- `BookingStatusMappingTest.php:46–47` đang khẳng định lưu id và khác booking_status_id. Cần thay test này theo hợp đồng mới, không giữ expectation cũ rồi coi migration là lỗi.
- Nhiều SQL báo cáo join `rs.id = b.registration_status_id`: due-in, due-out, inhouse, cancelled rooms/bookings, inhouse guests, returning guests; migration sửa availability ngày 07/09 cũng có join cũ. Phạm vi vượt một model.

### Kế hoạch triển khai

1. **Kiểm kê hợp đồng**: mọi reader/writer của registration_status_id trong model, controller, resource, UI, service, seeder, import, job, báo cáo, procedure và bộ lọc. Giữ tách biệt lifecycle status.
2. **Kiểm kê dữ liệu**: bảng mapping id → booking_status_id; mã null/trùng; booking không join được; kiểu dữ liệu/index/FK; database có đang trộn mã nghiệp vụ với PK không.
3. **Chốt hợp đồng mới**: giữ tên cột theo yêu cầu Word; giá trị là mã nghiệp vụ. Quan hệ dùng owner key booking_status_id; validation kiểm tra cột này; dropdown gửi mã nghiệp vụ, resource vẫn phân biệt id danh mục và mã nghiệp vụ.
4. **Chuẩn bị migration mới**: không chỉ sửa migration đã chạy. Thiết lập tính duy nhất của mã sau khi xử lý dữ liệu vi phạm; xử lý FK cũ theo schema thực tế, backfill bằng mapping được lưu trước chuyển đổi, tạo FK/index phù hợp nếu dùng ràng buộc.
5. **Chống đổi lặp/nhầm mã**: không đoán một giá trị là PK hay mã chỉ vì nó tồn tại trong bảng. Đánh dấu phiên bản chuyển đổi và lưu mapping/giá trị trước; chạy lại phải không đổi 20 sang một mã khác.
6. **Cập nhật code ghi/đọc**: thay `find()` dùng PK bằng truy vấn mã ở nơi nhận giá trị booking; sửa chọn trạng thái, confirm date, cấu hình hủy/noshow, copy/restore, search, import/export và lọc.
7. **Cập nhật SQL đã triển khai**: migration mới thay procedure/view/report definition còn join id, đổi giá trị option của bộ lọc; kiểm tra SQL runtime lưu trong database chứ không chỉ file PHP.
8. **Triển khai đồng bộ**: kiểm thử trên bản sao dữ liệu; tạm ngừng ghi trong cửa sổ cutover hoặc dùng phiên bản API rõ ràng. Backup → chuyển dữ liệu/schema/procedure → code tương thích → đối soát → mở lại ghi. Không để frontend cũ gửi PK vào backend mới.
9. **Rollback**: phục hồi theo snapshot mapping từng booking, cùng phiên bản code và SQL báo cáo. Xác định cách xử lý booking phát sinh sau cutover trước khi rollback.

### Nghiệm thu

- [ ] None Guaranteed lưu 20 dù id=2; test thêm mã 24,25,26,27,28,29 theo danh mục thực tế.
- [ ] Tạo/sửa/copy/hủy/noshow/restore và import đều lưu cùng hệ mã.
- [ ] Nhãn/màu, ngày xác nhận, bộ lọc và số liệu báo cáo trước/sau tương đương về nghiệp vụ.
- [ ] Không còn booking mồ côi; mapping duy nhất; chạy lại migration/backfill không đổi dữ liệu lần hai.
- [ ] Test có trường hợp id trùng với mã của một dòng khác; không tự suy đoán gây ánh xạ sai.
- [ ] Thử rollback trên staging; cấu hình chứa mã trạng thái được diễn giải đúng.

### Nghiệp vụ người dùng bổ sung

- Danh mục mã chuẩn ngoài các mã trong ảnh; có mã riêng từng khách sạn không? …
- Hệ tích hợp/import nào hiện gửi PK, hệ nào gửi mã cũ? …
- Cửa sổ triển khai và đầu mối đối soát dữ liệu: …

## 9. Section 8 — Đổi ngày đi cập nhật số đêm và chi tiết tiền phòng

![Section 8 — Đêm cuối còn dư và số đêm chưa refresh](D:/PMS/docs/booking-fix-plan/image8.png)

### Mô tả và kết quả mong muốn

Room Map → chuột phải phòng đang ở → Thông tin → đổi ngày đi từ 12 sang 11. Số đêm phải đổi ngay. Sau lưu, màn đăng ký không còn tiền phòng dự kiến đêm 11; không cần bấm Sửa/Lưu đăng ký lần nữa.

### Dự án đang có / chưa có

- `BookingDetailModal.vue:379–381` lấy nights từ props vào state; ô ngày đi tại 1053 và ô đêm tại 1069 dùng hai giá trị riêng, chưa thấy phép tính phản ứng theo ngày nhập.
- `handleSave():639–682` lưu qua `updateBookingRoomGuest()` hoặc `updateBookingChild()`, sau đó emit refresh. Đây là luồng cần sửa, không chỉ endpoint cập nhật phòng thông thường.
- `RoomMapPage.vue:778–786` đã fetch lại phòng và gán lại selected room theo booking_room_id. Phần refresh nền đã có, cần kiểm tra mapping và cập nhật modal đang mở.
- `BookingRoom` có các hook tính lại `ActutalNumOfDays` khi ngày thay đổi. Không kết luận database thiếu tính đêm chỉ từ việc UI hiển thị cũ.
- `GuestController::updateBookingRoomFromGuestRequest():851` gọi đồng bộ RM khi request có rate_code; `syncRateCodeRoomCharges():871` upsert các ngày trong kỳ nhưng không loại RM dư ngoài kỳ.
- `BookingRoomController::upsertRoomChargeServices():1292` đã có xóa RM chưa post ngoài kỳ. Hai luồng đang dùng logic khác nhau, cần thống nhất thay vì thêm lần lưu đăng ký phụ.

### Kế hoạch triển khai

1. Tính nights phản ứng theo ngày đến/đi ngay trên modal; dùng helper ngày chung, kiểm tra dữ liệu không hợp lệ và quy tắc day-use. Không dùng `|| 1` để che giá trị 0 nếu quy tắc hiển thị cho phép 0 đêm.
2. Xác định trường ngày/đêm thực tế, kế hoạch và lịch sử gốc; không cập nhật đè các trường audit bằng ngày rút ngắn mới khi chúng dùng để lưu lịch sử.
3. Tạo hoặc trích service đồng bộ kỳ ở và RM dùng chung cho chỉnh thông tin khách/người lớn/trẻ em, sửa phòng, cập nhật nhanh và sửa đăng ký. Trigger khi đổi ngày, không phụ thuộc có gửi rate_code.
4. Trong transaction: kiểm tra quyền/trạng thái/ngày nghiệp vụ/AV nếu kéo dài → cập nhật ngày → lập tập đêm [đến, đi) → loại RM dự kiến chưa post ngoài kỳ → upsert RM còn thiếu theo mã giá hoặc giá thỏa thuận → cập nhật số đêm/tổng phù hợp.
5. Giữ các khoản đã post và tiền đã thanh toán; nếu đổi ngày liên quan khoản đã post thì áp dụng quy trình điều chỉnh được chốt, không xóa âm thầm. Giữ dịch vụ thủ công như đưa đón, minibar trong ảnh.
6. Rà EB/BD và lịch khách đi kèm khi rút/kéo dài kỳ; xác định phần nào cần cùng kỳ ở, phần nào có lịch riêng. Ghi riêng quyết định nghiệp vụ trước khi thay đổi tiền.
7. API trả dữ liệu mới cần thiết hoặc refetch đúng booking_room_id; cập nhật modal hiện tại, Room Map và chi tiết đăng ký. Rà sự kiện reservation/room hiện hữu, tránh response cũ ghi đè thay đổi mới.
8. Nhánh không chọn được khách/phòng để lưu phải báo rõ; không báo thành công khi thực tế không gửi request cập nhật.

### Nghiệm thu

- [ ] Fixture đến 09, đi 12: hiển thị 3 đêm; sửa đi 11: lập tức còn 2 đêm trước lưu.
- [ ] Sau lưu chỉ còn RM dự kiến ngày 09,10; RM chưa post ngày 11 bị loại mà không cần lưu đăng ký lại.
- [ ] Có/không mã giá, giá tay, chọn người lớn/trẻ em đều đi qua quy tắc đồng bộ.
- [ ] Kéo dài lại từ 11 lên 12 tạo đúng một RM ngày 11; lưu lặp không nhân đôi tiền.
- [ ] RM đã post và dịch vụ khác vẫn còn đúng; API lỗi không để ngày và RM lệch nhau.
- [ ] Test qua tháng/năm, ngày đi bằng ngày đến, ngày đi không hợp lệ và quy tắc ngày nghiệp vụ.
- [ ] Modal đang mở, Room Map, đăng ký đang mở và lần mở lại thống nhất ngày/đêm/tiền.

### Nghiệp vụ người dùng bổ sung

- Đêm đã post ngoài kỳ mới: chặn sửa ngày hay cho sửa và tạo điều chỉnh theo quy trình? …
- EB/BD tương lai tự rút theo ngày đi hay cần xác nhận riêng? …
- Day-use hiển thị 0 đêm hay 1 ngày tính tiền; khi sửa ngày phòng có cập nhật kỳ booking tổng không? …

## 10. Trình tự thực hiện và phụ thuộc

| Đợt | Công việc | Điều kiện hoàn thành |
|---|---|---|
| 0 | Tái hiện 8 section trên dữ liệu thử; chốt các quy tắc mở; khảo sát mapping section 7 và G0000022 | Có fixture, payload và bảng hiện trạng dữ liệu; không còn nhầm 2 trường trạng thái |
| 1 | Section 3 + 4: quantity, payload, AV, hủy/lấy lại | GAL6 tạo lại phòng đúng số lượng, không lỗi sai loại; rollback khi lưu lỗi |
| 2 | Section 6 và kiểm tra lại 3–4 với booking check-in một phần | Header đúng, quyền thao tác phòng còn lại đúng |
| 3 | Section 8 + 2: kỳ ở, RM và giá ăn sáng | Ngày/đêm/giá đồng bộ; không tạo trùng hoặc sửa khoản đã post sai quy tắc |
| 4 | Section 1 + 5: thứ tự tự gán và danh sách khách | Gán đúng tầng; chỉ hiện đúng nhóm khách |
| 5 | Section 7: hoàn thiện chuyển đổi toàn hệ thống và chạy thử cutover | Mapping dữ liệu, UI, API, SQL báo cáo và rollback đã kiểm thử |
| 6 | UAT tổng hợp, sửa dữ liệu đã xác minh, triển khai và đối soát | Đủ bằng chứng nghiệm thu 8 section và kiểm tra sau triển khai |

Khảo sát section 7 phải làm từ đợt 0 vì có thể ảnh hưởng AV, trạng thái hủy/noshow và bộ lọc. Bảng trên là thứ tự tích hợp đề xuất, không phải yêu cầu dùng agent song song. Nếu nghiệp vụ muốn chia release, ưu tiên hoàn thiện 3–4–6; section 7 phải phát hành đồng bộ các thành phần phụ thuộc.

Ước lượng sơ bộ cho một lập trình viên hiểu dự án: khảo sát/tái hiện 1–2 ngày; section 3–4 khoảng 2–3 ngày; section 6 khoảng 0,5–1 ngày; section 8 khoảng 1,5–2,5 ngày; section 2 khoảng 0,5–1 ngày; section 1 và 5 tổng 1–2 ngày; section 7 khoảng 2–4 ngày; tích hợp/UAT 1–2 ngày. Tổng khoảng **10–18 ngày công**, chưa gồm chờ chốt nghiệp vụ và xử lý dữ liệu không chuẩn phát hiện thêm. Đây là dự toán lập kế hoạch, cập nhật sau đợt 0.

## 11. Kế hoạch kiểm thử và cập nhật tài liệu

### Kiểm thử tự động dự kiến khi triển khai

- Backend: bổ sung test hành vi cho auto-assign, breakfast amount, cancel/reallocate, guest room visibility, partial check-in, stay/RM sync và migration mapping. Tái sử dụng `BookingBusinessRulesTest`, `BookingRoomPersistenceTest`, `BookingRoomServiceFolioTest`, cập nhật `BookingStatusMappingTest` theo hợp đồng section 7.
- Frontend: kiểm thử helper sort tầng, input tiền, quantity/payload, ngày/đêm. Test tương tác Vue hoặc UAT trình duyệt cho vòng watcher và việc giữ con trỏ; test helper đơn thuần không đủ xác nhận hết section 2–3.
- Dùng `php artisan test` với nhóm test liên quan ở backend; chạy trên database test riêng. Với frontend dùng cách chạy test hiện có trong `frontend/tests` và `npm run build`; package hiện chưa có script `npm test`.
- Migration/procedure và sort database cần test trên cùng hệ quản trị thực tế, không chỉ SQLite. Kiểm tra đồng thời cho AV/gán phòng và lưu trạng thái.

### Kịch bản UAT xuyên suốt

1. Tạo booking có FAM/JST/SUPD, chọn mã giá, tự gán số phòng.
2. Hủy một số phòng, lấy lại chỉ FAM, đổi/gỡ/chọn BAR, lưu và kiểm tra số phòng thực tế.
3. Check-in một phòng, xem header, tiếp tục thao tác các phòng chưa đến.
4. Thêm trẻ em, nhập giá ăn sáng theo ngày, lưu và mở lại.
5. Chuyển phòng nhiều lần, kiểm tra Thông tin khách không có nhóm phòng nguồn.
6. Sửa ngày đi từ Room Map, kiểm tra ngay số đêm, RM, EB/BD theo quy tắc chốt và tiền đã post.
7. Đổi tình trạng đăng ký khác Guaranteed, đối chiếu mã lưu, tên/màu, báo cáo và AV.

### Bằng chứng cần lưu cho mỗi section

- Ảnh trước/sau theo cùng tình huống; request/response đã loại thông tin nhạy cảm; test pass/fail; dữ liệu đối soát cần thiết.
- Trạng thái riêng: chưa triển khai → đang triển khai → đã test kỹ thuật → chờ UAT → nghiệm thu. Không gộp “có code” thành “đã hết lỗi”.
- Cập nhật chính section tương ứng trong kế hoạch này: kết quả triển khai, file thay đổi, quyết định nghiệp vụ và lỗi còn mở.
- Sau section 6–7, cập nhật `booking_module_analysis.md`, `STATUS_CODES.md`, tài liệu nghiệp vụ đặt phòng và hướng dẫn database liên quan để không còn mô tả trái với code. Không tự thay nội dung Word nguồn.

## 12. Tiêu chí hoàn tất toàn bộ

- [ ] Cả 8 section đạt ca nghiệm thu và có ảnh/bằng chứng sau sửa.
- [ ] Những quy tắc chưa chốt đã có quyết định hoặc được tách thành hạng mục còn mở với phạm vi rõ ràng.
- [ ] Không còn sai lệch quantity giữa UI/payload/DB; không phát sinh trùng phòng hoặc mất lịch sử hủy/chuyển.
- [ ] Booking status và registration status đúng hai mục đích, mọi reader/writer dùng cùng hợp đồng.
- [ ] Ngày/đêm/dịch vụ phòng đồng bộ qua mọi luồng chỉnh sửa liên quan.
- [ ] Dữ liệu G0000022 có kết luận điều tra; nếu cần sửa đã có đối soát, không suy đoán từ ảnh.
- [ ] Migration section 7 và mọi sửa dữ liệu có dry-run, kiểm tra chạy lại, rollback và đối soát sau triển khai.
- [ ] Build, test phù hợp và UAT tổng hợp hoàn tất; các section được cập nhật trạng thái thực tế.
