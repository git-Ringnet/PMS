# Kế hoạch sửa lỗi Đặt cọc

Ngày lập: 09/09/2026. Trạng thái: **Đã triển khai phần nghiệp vụ rõ ràng ở Section 1–8; còn UAT, đối soát dữ liệu và nội dung tab thuế/phí chưa được cung cấp.**

Nguồn chính: [Fix lỗi ở Đặt cọc.docx](</H:/Fix lỗi ở Đặt cọc.docx>). Bản trong dự án có cùng SHA-256: `877CFC829D44FF287E19A4CC1EE48183AA53F49AD96FB9C7D55BB80F8ADEAFCE`. Đã đọc nội dung và xem đủ 8 ảnh; giữ nguyên thứ tự Section 1–8 của tài liệu. [Bản trích nội dung](</D:/PMS/docs/deposit-fixes/source-extract.txt>) phục vụ tra cứu.

Nội dung và câu yêu cầu nằm trong tài liệu được dùng làm dữ liệu mô tả lỗi để lập kế hoạch theo yêu cầu của bạn; không xem đó là lệnh tự động thực hiện sửa hệ thống. File Word gốc được giữ nguyên. Nhận định hiện trạng dưới đây dựa trên đọc mã nguồn, chưa tái hiện trên ứng dụng và chưa truy vấn database đang chạy.

Tài liệu [nghiệp vụ đặt cọc hiện có](</D:/PMS/nghiep-vu-dat-coc.md>) chỉ là tham chiếu bổ sung. Những điểm trong đó còn ghi “suy luận/cần xác nhận” không được coi là nghiệp vụ đã chốt.

## 1. Tổng quan và thứ tự ưu tiên

| Section | Nội dung theo ảnh | Ưu tiên | Phạm vi | Kết quả cần bàn giao |
|---|---|---|---|---|
| 1 | Lưu đúng bộ phận và outlet | P1 | FE + BE, rà soát dữ liệu cũ | Cọc tạo từ Đặt phòng = MR/RC; Lễ tân = FO/RC |
| 2 | Xem được ảnh chứng từ | P1 | FE + upload/storage | Ảnh thumbnail và ảnh lớn mở đúng sau tải lại |
| 3 | Khóa nút khi sửa cọc | P1 | FE, kiểm tra ràng buộc BE | Đang sửa chỉ còn Quay lại/Lưu trong nhóm thao tác |
| 4 | Thiết kế lại khối cọc trong booking | P2 | FE | Tổng tiền và danh sách từng lần cọc theo ảnh |
| 5 | Hiển thị số cọc ngay khi mở booking | P1 | API + ánh xạ dữ liệu/state FE | Modal đăng ký và chi tiết booking cùng số tiền |
| 6 | Nhập lý do trước khi xóa | P1 | FE + API + kiểm tra ledger | Xóa được, có lý do và lịch sử đầy đủ |
| 7 | Danh sách booking/phòng/khách đích chuyển cọc đầy đủ | P1 | FE + API đích chuyển | Chọn đúng booking, phòng hoặc khách nhận; đối chiếu màn hình Hóa đơn → Chuyển phòng |
| 8 | Quản lý tài khoản ngân hàng trong System | P2, tính năng mới | DB + BE + FE + phân quyền | Danh mục thật thay ba lựa chọn hardcode |

P1: ảnh hưởng thao tác, dữ liệu hoặc khả năng đối soát. P2: cải thiện hiển thị hoặc bổ sung cấu hình. Ưu tiên thực hiện Section 6 và 3 trước để khôi phục thao tác; tiếp theo Section 1, 5, 2, 7; hoàn thiện Section 4 trên dữ liệu đã đồng bộ; Section 8 là gói riêng có migration.

## Section 1 — Lưu đúng `department_id` và `outlet`

![Ảnh Section 1 — Bộ phận MR và outlet NULL](</D:/PMS/docs/deposit-fixes/images/image1.png>)

### Yêu cầu từ tài liệu

| Nơi tạo cọc | `payments.department_id` | `payments.outlet` |
|---|---|---|
| Module Đặt phòng | MR | RC |
| Module Lễ tân, màn hình booking | FO | RC |
| Module Lễ tân, màn hình hóa đơn | FO | RC |

### Hiện trạng đã thấy

- [DepositModal.vue](</D:/PMS/frontend/src/pages/reservation/components/DepositModal.vue>) nhận `departmentId` mặc định MR và gửi giá trị này khi thêm/sửa/tách/chuyển.
- Chỗ gọi modal trong [CreateRegistrationPage.vue](</D:/PMS/frontend/src/pages/reservation/CreateRegistrationPage.vue>) chưa truyền `departmentId`. Cần truy vết ngữ cảnh module khi dùng chung màn hình.
- [PaymentController.php](</D:/PMS/backend/app/Http/Controllers/Api/PaymentController.php>) lấy bộ phận từ request, rồi header `X-Department-ID`, cuối cùng mặc định MR. Payload tạo cọc chưa gán outlet.
- [BookingController.php](</D:/PMS/backend/app/Http/Controllers/Api/BookingController.php>) còn nhánh tạo payment từ `deposit_details` khi lưu booking; nhánh này chưa gán bộ phận/outlet. Dù UI hiện có nơi khóa Thêm cọc trước khi lưu booking, vẫn phải kiểm tra đường API này.

### Kế hoạch thực hiện

1. Liệt kê tất cả entry point tạo cọc: đăng ký booking, sơ đồ phòng, booking từ Lễ tân, hóa đơn và nhánh `deposit_details`.
2. Truyền ngữ cảnh module rõ ràng từ màn hình cha tới modal/service. Không suy ra bộ phận từ tên component vì component được dùng chung.
3. Chuẩn hóa xử lý ở backend: kiểm tra bộ phận hợp lệ theo ngữ cảnh/quyền hiện có; với cọc tạo tại PMS, backend gán `outlet = RC` để không phụ thuộc UI nhớ gửi.
4. Dùng chung quy tắc cho hai đường tạo cọc: API payments và tạo cùng booking. Không sửa lan sang thanh toán thường/Advance Payment nếu không thuộc yêu cầu.
5. Kiểm tra những dòng sinh ra do tách, chuyển, xóa có bị mất outlet hay không. Giữ bộ phận của giao dịch gốc khi sửa HTTT/mô tả; quy tắc bộ phận của dòng điều chỉnh cần chốt riêng.
6. Lập báo cáo dữ liệu lịch sử có outlet NULL hoặc nghi sai bộ phận. Chỉ đề xuất backfill khi xác định được nguồn thao tác từ bằng chứng; không đổi hàng loạt MR thành FO theo phỏng đoán.

### Nghiệm thu

- Tạo cọc ở cả ba vị trí trong bảng, kiểm tra trực tiếp bản ghi lưu đúng MR/RC hoặc FO/RC.
- Đổi module rồi tạo tiếp không dùng nhầm ngữ cảnh cũ.
- Sửa mô tả/HTTT từ module khác không ghi đè bộ phận tạo ban đầu.
- API có ngữ cảnh không hợp lệ bị xử lý rõ ràng; không âm thầm lưu sai bộ phận.
- Nhánh `deposit_details` tuân thủ cùng quy tắc nếu tiếp tục được hỗ trợ.

**Nghiệp vụ bạn bổ sung:** Dòng âm/dương sinh khi chuyển hoặc xóa lấy bộ phận người thao tác hay bộ phận gốc? Có cần sửa dữ liệu đã phát sinh, và bắt đầu từ ngày nào?

**Trạng thái:** [x] Xác minh entry point · [x] Giữ bộ phận gốc cho dòng điều chỉnh · [x] Sửa FE/BE · [ ] Kiểm tra dữ liệu lịch sử · [ ] UAT.

## Section 2 — Xem hình ảnh chứng từ đặt cọc

![Ảnh Section 2 — Preview hiển thị ảnh lỗi](</D:/PMS/docs/deposit-fixes/images/image2.png>)

### Yêu cầu từ tài liệu

Ảnh chứng từ đã gắn vào cọc phải xem được; ảnh chụp hiện là khung preview với biểu tượng ảnh hỏng.

### Hiện trạng đã thấy

- Backend upload vào disk `public`, thư mục `payments`, lưu `image_path`.
- Modal đã có `getImageUrl()` hỗ trợ blob/data/URL tuyệt đối và chuẩn hóa đường `/storage/...`; [vite.config.js](</D:/PMS/frontend/vite.config.js>) cũng đã proxy `/storage`.
- Vì đã có một phần xử lý, chưa thể kết luận lỗi hiện tại chỉ do URL. Cần phân biệt file không tồn tại, đường dẫn cũ, cấu hình phục vụ file, lỗi truy cập và dữ liệu ảnh tạm chưa được lưu.

### Kế hoạch thực hiện

1. Tái hiện với một cọc cũ có ảnh và một cọc mới upload; ghi nhận `image_path`, URL trình duyệt yêu cầu và HTTP status.
2. Theo dõi đầy đủ chuỗi chọn file → upload → lưu đường dẫn → GET cọc → thumbnail → preview → tải lại booking.
3. Rà soát các chỗ dựng URL trong modal cọc và khối booking. Dùng một hàm chuẩn hóa chung nếu đang có nhiều cách xử lý khác nhau; không nhân đôi `/storage`.
4. Kiểm tra file thực, public storage và cấu hình môi trường triển khai. Chỉ sửa cấu hình khi xác định đó là nguyên nhân.
5. Phân biệt ảnh tạm blob/data với đường dẫn bền vững. Với luồng cọc nháp, nếu còn hỗ trợ, cần upload thật khi lưu; không lưu blob URL vào DB.
6. Thêm trạng thái đang tải/lỗi tải ảnh và nút đóng rõ ràng. File đã mất phải hiện “Không tải được chứng từ”, không giả hiển thị thành công.
7. Kiểm tra lớp preview vượt modal cha, không bị cắt khi kéo modal; đóng preview không đóng nhầm toàn bộ màn hình đăng ký.

### Nghiệm thu

- Ảnh mới: xem ngay và sau refresh/đăng nhập lại đều đúng.
- Ảnh cũ còn file: xem được từ danh sách và khi mở dòng sửa cọc.
- Đường `payments/...`, `/storage/payments/...` được xử lý nhất quán; URL tuyệt đối hợp lệ không bị nối sai.
- File mất/lỗi tải có thông báo phù hợp và vẫn đóng preview được.
- Kiểm tra trên môi trường dev và build triển khai; không phụ thuộc hostname localhost.

**Nghiệp vụ bạn bổ sung:** Chứng từ chỉ một ảnh hay nhiều ảnh mỗi cọc? Có cần PDF không? Phạm vi mặc định của gói sửa này là ảnh hiện có; PDF/nhiều ảnh cần mô tả bổ sung.

**Trạng thái:** [ ] Tái hiện URL/file trên môi trường chạy · [x] Chuẩn hóa URL và trạng thái lỗi · [ ] Kiểm tra ảnh cũ/mới · [ ] UAT.

## Section 3 — Khóa Tách/Chuyển/Xóa/Sửa trong chế độ sửa

![Ảnh Section 3 — Các nút cần làm mờ](</D:/PMS/docs/deposit-fixes/images/image3.png>)

### Yêu cầu từ tài liệu

Chọn một dòng và bấm Sửa: làm mờ, không cho thao tác Tách, Chuyển, Xóa, Sửa. Trong nhóm thao tác chỉ dùng Quay lại và Lưu.

### Hiện trạng đã thấy

Modal có `isEditing`; đã khóa số tiền, phòng, ngày, tài khoản ngân hàng và upload trong chế độ sửa. Tuy nhiên bốn nút cần khóa chỉ đang `disabled` theo `isSubmitting`, nên khi sửa nhưng chưa gửi request vẫn có thể thao tác.

### Kế hoạch thực hiện

1. Chuẩn hóa trạng thái xem/thêm, sửa và đang gửi; tính điều kiện bật từng nút từ trạng thái + dòng chọn + quyền + tình trạng cọc.
2. Khi `isEditing`, disable thật bốn nút, đồng thời làm mờ. Thêm guard đầu handler để ngăn gọi thao tác từ phím tắt hoặc sự kiện khác.
3. Khóa thay đổi checkbox dòng và chọn tất cả trong lúc sửa để `editingId` không lệch với dòng được chọn. Không mở modal tách/chuyển chồng lên form đang sửa.
4. Quay lại: bỏ thay đổi chưa lưu, reset form/selection và trở về chế độ thường. Lưu: gửi dữ liệu sửa, khóa lặp request; lỗi API giữ nguyên form để sửa tiếp.
5. Thu gọn payload sửa về HTTT và mô tả, theo quy tắc hiện có. Không gửi lại bộ phận/phòng/tài khoản/ảnh chỉ vì form có những trường đó.
6. Kiểm tra backend vẫn từ chối chỉnh trường ngoài phạm vi, cọc đã dùng/đã xóa hoặc booking không còn được phép sửa theo nghiệp vụ hiện hành.

### Ma trận thao tác

| Trạng thái | Tách/Chuyển/Xóa/Sửa | Quay lại | Lưu |
|---|---|---|---|
| Xem/thêm | Theo số dòng chọn, quyền và tình trạng cọc | Theo luồng hiện tại | Theo form |
| Đang sửa | Mờ và disabled | Bật, hủy bản sửa | Bật nếu hợp lệ |
| Đang gửi lưu | Disabled | Disabled để tránh mất trạng thái | Disabled chống gửi lặp |
| Lưu lỗi | Vẫn khóa nhóm thao tác sửa khác | Bật | Cho thử lại |

### Nghiệm thu

- Bốn nút không kích hoạt bằng chuột/bàn phím khi sửa; không đổi được dòng đang sửa.
- Quay lại không lưu ngầm; Lưu thành công trả về chế độ thường.
- Lỗi API giữ dữ liệu đang nhập; không xuất hiện hai thao tác trên cùng cọc.

**Nghiệp vụ bạn bổ sung:** Khi bấm X/ESC lúc đang sửa, cần cảnh báo bỏ thay đổi hay xử lý như Quay lại? Đề xuất cảnh báo nếu form đã thay đổi.

**Trạng thái:** [x] State/guard · [x] Payload sửa · [x] Kiểm tra tĩnh/build · [ ] UAT.

## Section 4 — Đổi giao diện khối Đặt cọc trên booking

![Ảnh Section 4 — Giao diện cũ và mẫu cần đổi](</D:/PMS/docs/deposit-fixes/images/image4.png>)

### Yêu cầu đọc từ ảnh

- Đổi tiêu đề khối thành “ĐẶT CỌC”, giữ nút “+ Thêm cọc” bên phải.
- Hiển thị một vùng tổng tiền; bên dưới là danh sách các lần cọc, mỗi dòng có ngày, phương thức và số tiền kèm tiền tệ.
- Bỏ cách chỉ hiển thị ngày/mô tả của một khoản cọc trong thẻ rời hiện tại.

### Hiện trạng đã thấy

`CreateRegistrationPage.vue` hiện dùng tiêu đề “Đặt cọc & Thanh toán”, khối tổng có icon tiền và phần chi tiết lấy `firstDepositDate`, `firstDepositMethodName`, `firstDepositNote`; chưa render từng khoản như ảnh mẫu.

### Kế hoạch thực hiện

1. Thay bố cục bằng header → tổng tiền → danh sách dòng có đường phân cách. Bám ảnh mẫu và style hiện có của ứng dụng.
2. Render tất cả khoản trong phạm vi tổng đã chốt ở Section 5; mỗi dòng: `dd/MM/yyyy | Tên HTTT | Số tiền + tiền tệ`.
3. Lấy tên HTTT từ relation/danh mục; mã lạ dùng mã gốc làm fallback, không biến mọi giá trị thiếu thành nhãn “Đặt cọc”.
4. Canh phải số tiền, không xuống dòng giữa số và đơn vị; tên dài có ellipsis và cách xem đủ. Danh sách dài có vùng cuộn, header/nút thêm luôn dễ thấy.
5. Thêm trạng thái rỗng, đang tải và lỗi tải. Không hiển thị 0 như một kết quả đã xác minh trong khi request còn đang chạy hoặc thất bại.
6. Dùng dữ liệu chung với modal và chi tiết booking; chỉ triển khai phần bố cục sau khi hợp đồng dữ liệu Section 5 thống nhất.

### Nghiệm thu

- Dữ liệu mẫu ảnh: 500.000 + 700.000 + 300.000 cho tổng 1.500.000 VND và đủ ba dòng.
- Có 0, 1, nhiều dòng; HTTT dài; booking hẹp và màn hình laptop vẫn không tràn.
- Nút Thêm cọc giữ đúng quyền và ngữ cảnh booking.
- Sau tách/chuyển/xóa, danh sách và tổng đổi đồng thời.

**Nghiệp vụ bạn bổ sung:** Sắp ngày tăng hay giảm? Có cần giờ, mô tả, người nhận khi hover/mở dòng? Đề xuất ban đầu: ngày tăng dần như ảnh, cùng ngày sắp theo thời gian/ID để ổn định.

**Trạng thái:** [x] Dùng định nghĩa active DPR hiện hành · [x] Layout · [x] Trạng thái rỗng và danh sách cuộn · [ ] So ảnh UAT.

## Section 5 — Đồng bộ số tiền cọc khi mở và xem chi tiết booking

![Ảnh Section 5 — Ban đầu bằng 0, mở modal mới có tiền](</D:/PMS/docs/deposit-fixes/images/image5.png>)

### Yêu cầu từ tài liệu

Booking đã có cọc phải hiện số tiền ngay tại thông tin đăng ký và phần chi tiết booking. Không cần bấm Thêm cọc rồi thoát để kích hoạt tải dữ liệu.

### Hiện trạng đã thấy

- Mapping booking trong `CreateRegistrationPage.vue` tính tổng từ `b.payments`; nếu relation không có thì coi là mảng rỗng và gán tổng 0. Chưa thể kết luận mọi response hiện đều thiếu relation; cần kiểm tra request cụ thể gây lỗi.
- Khối form đọc `modalForm.paymentValue`, thanh chi tiết đọc `activeTab.deposit`: hai nhánh state khác nhau.
- `syncDepositsFromBackend()` trong modal tải payments rồi emit tổng tiền; đây là đường có thể giải thích vì sao mở modal mới thấy cọc.
- Bộ lọc tổng trong một số chỗ chỉ xét `edit_flag === 0` và `pack2 === DPR`, chưa làm rõ cọc đã cấn trừ có thuộc “Tổng tiền đặt cọc” không.

### Kế hoạch thực hiện

1. Tái hiện mở booking từ danh sách, tìm kiếm, sơ đồ phòng và refresh trực tiếp. So payload index/detail với state `deposit`, `paymentValue`, `deposits`.
2. Chốt nghĩa của tổng trước khi sửa bộ lọc: tổng cọc còn khả dụng hay tổng đã thu lịch sử. Phân biệt hai khái niệm nếu cần hiển thị cả hai.
3. Đề xuất backend trả summary rõ nghĩa cho booking, thí dụ `deposit_summary.available_amount`, `received_amount`, `currency`; tên cuối cùng theo chuẩn API dự án. Chỉ bổ sung trường thực sự cần sau khi chốt nghiệp vụ.
4. Dùng một quy tắc tính tổng từ payments; nếu còn `bookings.payment_value` dạng cache, đồng bộ trong cùng transaction ở mọi thao tác làm thay đổi số dư. Không dùng `deposit_details` cũ làm nguồn chuẩn cho booking đã lưu.
5. Index cung cấp summary đủ nhẹ; detail cung cấp danh sách khi cần. Không tải toàn bộ payments của mọi booking chỉ để tính tổng trên trình duyệt.
6. Frontend ánh xạ cùng summary vào form và tab chi tiết ngay khi mở. Thiếu dữ liệu phải tải bổ sung hoặc báo trạng thái, không quy thành 0 đã xác nhận.
7. Sau thêm/sửa/tách/xóa/chuyển/cấn trừ, refresh hoặc invalidate đúng booking. Chuyển cọc cập nhật cả nguồn và đích; sự kiện nên kèm booking ID để tránh tải lại toàn bộ.
8. Chặn response cũ ghi đè khi chuyển nhanh từ booking A sang B; không phụ thuộc mở modal cọc để đồng bộ.

### Nghiệm thu

- Booking có cọc 1.000.000: mở lần đầu, đóng/mở lại, refresh, mở từ tìm kiếm đều có số đúng ở cả hai nơi.
- Booking không có cọc hiển thị 0 sau khi tải xong; API lỗi không giả báo 0.
- Tách không đổi tổng; xóa giảm đúng khoản; chuyển giảm nguồn/tăng đích cùng số tiền; không cộng dòng đảo hoặc cộng hai lần.
- Cọc đã cấn trừ hiển thị theo định nghĩa được chốt, nhất quán giữa booking và hóa đơn.
- Đổi nhanh A/B không thấy số dư A trên B; sửa thông tin booking khác không ghi đè tổng cọc bằng state cũ.

**Nghiệp vụ bạn bổ sung — cần chốt trước khi đổi công thức:** “Tổng tiền đặt cọc” là số còn dùng được hay gồm cả cọc đã thanh toán? Tổng booking có cộng cọc riêng phòng/khách không? Advance Payment có nằm trong số này không? Có nhiều tiền tệ hay chỉ VND?

**Trạng thái:** [x] Backend summary · [x] Đồng bộ state và card khi mở booking · [x] Giữ định nghĩa active DPR hiện hành · [ ] Tái hiện payload trên môi trường chạy · [ ] UAT.

## Section 6 — Hiện form nhập lý do xóa cọc

![Ảnh Section 6 — Thiếu ô lý do và lỗi reason required](</D:/PMS/docs/deposit-fixes/images/image6.png>)

### Yêu cầu từ tài liệu

Booking → Đặt cọc → Xóa cần có màn hình nhập lý do; hiện chỉ xác nhận nên không xóa được.

### Nguyên nhân xác định từ code

- `deleteDeposits()` chỉ gọi confirm Đồng ý/Quay lại, sau đó `deletePayment(depId)`.
- [booking-service.js](</D:/PMS/frontend/src/services/booking-service.js>) khai báo `deletePayment(id)` không gửi body.
- Backend `destroy()` bắt buộc `reason: required|string|min:1|max:1000`; phù hợp lỗi “The reason field is required” trong ảnh.

### Kế hoạch thực hiện

1. Thay xác nhận đơn giản bằng modal xóa gồm booking, khoản/số khoản được chọn, tổng tiền, ô Lý do bắt buộc, Quay lại và Xác nhận xóa.
2. Trim lý do, chặn rỗng/toàn khoảng trắng, giới hạn theo BE 1.000 ký tự, hiển thị lỗi tiếng Việt tại field.
3. Sửa service nhận payload lý do và gửi body đúng cách cho DELETE qua HTTP client. Kiểm tra mọi nơi gọi service để giữ tương thích.
4. Gửi ngữ cảnh bộ phận theo Section 1; giữ lý do và selection khi API lỗi; khóa nút gửi lặp.
5. Đối chiếu quyền xóa và quyền ngày cũ phía FE/BE. Không đổi điều kiện ngày chỉ để bỏ qua lỗi quyền; ghi nhận rõ ngày giao dịch/ngày tạo/ngày nghiệp vụ đang dùng.
6. Giữ cơ chế bút toán đảo/soft delete hiện có và kiểm chứng: có lý do, người thao tác, thời điểm, liên kết dòng gốc; không xóa vật lý payment đã ghi sổ.
7. Với chọn nhiều dòng, chốt chính sách. Đề xuất bulk transaction tất cả thành công hoặc không dòng nào bị xóa. Nếu giữ gọi từng dòng, phải báo chính xác phần đã thành công/thất bại, tải lại dữ liệu và không báo thất bại toàn bộ như chưa có thay đổi.
8. Kiểm tra trạng thái lại trong transaction/khóa bản ghi phù hợp để ngăn cùng khoản bị xóa hai lần hoặc vừa xóa vừa cấn trừ. Sau thành công đồng bộ tổng/danh sách theo Section 5.

### Nghiệm thu

- Bấm Xóa thấy ô lý do; bỏ trống không gửi; nhập hợp lệ xóa thành công.
- Quay lại không tạo bút toán. HTTP lỗi không làm mất lý do đã nhập.
- Dòng xóa có lý do/người/ngày giờ và dòng đảo liên kết đúng; bật Hiển thị xóa xem được theo quy tắc hiện hành.
- Không quyền/ngày cũ/cọc đã dùng bị từ chối rõ ràng, không thay đổi tiền.
- Hai lần bấm hoặc hai người thao tác không sinh hai dòng đảo cho cùng lần xóa.
- Nhiều dòng tuân thủ đúng chính sách đã chốt; tổng trên booking cập nhật ngay.

**Nghiệp vụ bạn bổ sung:** Lý do tự nhập hay chọn danh mục + ghi chú? Xóa nhiều cọc dùng chung lý do hay từng dòng? Muốn tất cả thành công cùng lúc hay cho phép thành công một phần?

**Trạng thái:** [x] Form lý do · [x] Service/payload DELETE · [x] Báo cáo thành công một phần khi xóa nhiều dòng · [x] Giữ ledger đảo và quyền hiện hành · [ ] UAT.

## Section 7 — Sửa danh sách booking/phòng/khách và đích chuyển cọc

![Ảnh Section 7 — Dropdown bị che và danh sách đối chiếu từ hóa đơn](</D:/PMS/docs/deposit-fixes/images/image7.png>)

### Yêu cầu từ tài liệu và ảnh

Luồng chuyển cọc phải hiển thị **booking nhận và các phòng/khách tương ứng** để chọn đúng nơi nhận. Dropdown cần có một dòng booking (mã + tên), bên dưới là các phòng hợp lệ và khách thuộc từng phòng. Danh sách phải đầy đủ, không bị che phần cuối, và đối chiếu theo danh sách booking/phòng có thể chuyển ở màn hình Hóa đơn → Chuyển phòng.

### Hiện trạng cần xử lý

- Dropdown cần giữ overlay riêng để không bị cây modal cha clipping khi kéo modal hoặc đặt gần mép dưới.
- `transferOptions` phải sinh đủ option booking, phòng và khách; không tự bỏ phòng chỉ vì không có tên khách thực tế. Khách mặc định như Guest 1/Guest 2 được giữ nếu đang là dữ liệu của booking.
- Tìm kiếm theo mã/tên booking, số phòng hoặc tên khách; booking nguồn bị loại bằng ID, kể cả khác kiểu chuỗi/số. Kết quả cũ phải bị bỏ qua khi request mới hoàn tất.
- Khi chọn booking gửi `target_booking_id`; khi chọn phòng/khách gửi thêm `target_room_id`/`target_guest_id` tương ứng, không để lựa chọn cấp dưới bị lưu nhầm về Master.
- Backend vẫn kiểm tra booking/phòng/khách đích và trạng thái tại thời điểm chuyển; gói này không thay đổi chuyển phòng vật lý.

### Kế hoạch thực hiện

1. Chuẩn hóa option đích theo cấu trúc dùng ở màn hình Hóa đơn → Chuyển phòng: booking, từng phòng hợp lệ và từng khách thuộc phòng; giữ ID booking-room/guest chuẩn.
2. Giữ bộ lọc booking trạng thái Đăng ký/Inhouse theo backend hiện có, loại booking nguồn theo ID. Không loại phòng/khách hợp lệ chỉ vì tên khách là mặc định hoặc phòng chưa có tên hiển thị.
3. Hỗ trợ tìm theo mã/tên booking, số phòng và tên khách; xử lý phân trang/giới hạn server để danh sách lớn không bị thiếu.
4. Khi chọn booking đặt room/guest về null; chọn phòng gửi `target_booking_id` + `target_room_id`; chọn khách gửi thêm `target_guest_id`. Đổi hoặc xóa lựa chọn phải xóa ID cấp dưới cũ.
5. Tái sử dụng/đối chiếu nguồn danh sách đích ở CheckoutPage, đặc biệt `transferDestinations`, để phạm vi phòng/booking khớp màn hình Hóa đơn → Chuyển phòng.
6. Giữ overlay có cuộn nội bộ, trạng thái loading/rỗng/lỗi, cơ chế bỏ response tìm kiếm cũ và khóa gửi lặp hiện hành.
7. Sau chuyển đối soát bút toán nguồn/đảo/đích và tổng hai booking; backend tiếp tục chặn đích không hợp lệ hoặc hết trạng thái cho phép.

### Nghiệm thu

- Mở dropdown thấy booking và các dòng phòng/khách thuộc booking; không thiếu phòng hợp lệ so với Hóa đơn → Chuyển phòng.
- Tìm theo mã/tên booking, số phòng hoặc tên khách trả đúng booking trạng thái Đăng ký/Inhouse; booking nguồn không xuất hiện dù ID từ API là chuỗi hay số.
- Chọn booking gửi `target_booking_id`; chọn phòng/khách gửi đúng ID cấp tương ứng, không lưu nhầm về Master.
- Xóa hoặc chọn lại đích không giữ ID/nhãn cũ; response tìm kiếm cũ không ghi đè kết quả mới.
- Trên laptop 1366×768, zoom 125%, kéo modal sát mép dưới vẫn nhìn và chọn được dòng booking/phòng/khách cuối.
- Đích hết điều kiện trong lúc modal mở bị backend từ chối; chuyển thành công bảo toàn lịch sử, bút toán đảo/đích và tổng hai booking.
- Không có thay đổi phòng vật lý; chỉ xác định đích nhận cọc theo booking/phòng/khách được chọn.

**Nghiệp vụ:** Chuyển cọc vẫn hiển thị booking và các phòng/khách tương ứng; danh sách nguồn chuẩn để đối chiếu là Hóa đơn → Chuyển phòng. Các quy tắc trạng thái, quyền và phạm vi chi nhánh/khách sạn tiếp tục dùng backend hiện hành.

**Trạng thái:** [x] Hiển thị booking + phòng/khách · [ ] Đối chiếu nguồn Hóa đơn → Chuyển phòng trên dữ liệu thực tế · [x] ID/payload cấp tương ứng · [x] Overlay không clipping · [ ] Đối soát tiền trên dữ liệu chạy · [ ] UAT.

## Section 8 — Module quản lý tài khoản ngân hàng trong System

**Cập nhật theo hai ảnh người dùng bổ sung ngày 09/09/2026:** thay danh sách trường đề xuất ban đầu bằng các cột và form bên dưới. Bố cục, CRUD, schema/API và lookup kế toán/tiền tệ đã được triển khai; ảnh không tự xác định các quy tắc nghiệp vụ chưa thể hiện như xóa dữ liệu đã dùng hoặc công thức thuế/phí.

![Ảnh Section 8 — Vị trí menu trong System](</D:/PMS/docs/deposit-fixes/images/image8.png>)

![Ảnh Section 8A — Danh sách tài khoản ngân hàng và hai nhóm](</D:/PMS/docs/deposit-fixes/images/section8-bank-account-list.png>)

![Ảnh Section 8B — Modal thêm tài khoản ngân hàng](</D:/PMS/docs/deposit-fixes/images/section8-bank-account-form.png>)

### 8.1. Phạm vi đã xác định từ yêu cầu và ảnh

- Vị trí: System → Thông tin tổ chức → Tài khoản ngân hàng.
- Có màn hình danh sách, tìm kiếm, thêm, xem chi tiết, sửa và xóa tài khoản; không chỉ thay các option trong modal cọc.
- Danh sách chia hai tab: **1. Ngân Hàng Thanh Toán** và **2. Ngân Hàng Trung Gian**.
- Modal có hai tab: **Tài khoản ngân hàng** và **Thông tin hạch toán thuế phí cà thẻ**. Ảnh hiện chỉ cho thấy nội dung tab thứ nhất.
- Dữ liệu danh mục thật được sử dụng ở modal Đặt cọc, thay Vietcombank/BIDV/Techcombank hardcode.
- Không đưa các cột chủ tài khoản, chi nhánh ngân hàng, mặc định, trạng thái vào bảng chính như đề xuất cũ, vì ảnh mới không có các cột đó. Phạm vi đơn vị/quyền vẫn cần xử lý theo kiến trúc hệ thống nhưng không tự thêm thành cột UI.

### 8.2. Màn hình danh sách — đúng thứ tự cột theo ảnh

| Thứ tự | Cột | Hiển thị và xử lý dự kiến |
|---|---|---|
| 1 | STT | Số thứ tự dòng trong kết quả; tự tính, không phải ID nghiệp vụ |
| 2 | Mã Tài Khoản | Mã tài khoản ngân hàng; ví dụ ACB001, TPB001; lưu chuỗi |
| 3 | Số Tài Khoản Ngân Hàng | Hiển thị nguyên chuỗi, giữ số 0 đầu; không dùng formatter số tiền |
| 4 | Tài Khoản | Mã tài khoản hạch toán được chọn trong form, ví dụ 1121; không phải số tài khoản ngân hàng |
| 5 | Mã Tiền Tệ | Mã từ danh mục tiền tệ, ví dụ VND |
| 6 | Tên Ngân Hàng | Tên hiển thị do người dùng khai báo |
| 7 | Ngày Mở | Ngày mở theo nguồn/quy tắc được chốt; định dạng ngày thống nhất; null để trống |
| 8 | Ngày Đóng | Ngày đóng theo nguồn/quy tắc được chốt; null để trống |
| 9 | Diễn Giải | Nội dung diễn giải; hỗ trợ dài, xuống dòng hoặc xem đầy đủ |
| 10 | Xóa | Icon thùng rác đỏ, kiểm tra quyền và mở xác nhận xóa đúng dòng |

**Thanh công cụ và thao tác**

- Ô tìm kiếm + nút “Tìm Kiếm” bên trái; nút “+ Thêm” bên phải. Enter thực hiện tìm kiếm, xóa từ khóa trả về danh sách của tab hiện tại.
- Đề xuất tìm theo mã tài khoản, số tài khoản, mã hạch toán, tên ngân hàng và diễn giải; kết hợp phạm vi tab, phân trang server nếu danh sách lớn.
- Tab Ngân Hàng Thanh Toán hiển thị nhóm không trung gian; tab Ngân Hàng Trung Gian hiển thị nhóm trung gian. Ánh xạ đề xuất qua cờ `is_intermediary` trong form, không tạo hai danh mục độc lập.
- Mở chi tiết bằng click mã tài khoản hoặc double-click dòng; đây là đề xuất tương tác vì ảnh không có cột Sửa. Người có quyền sửa có thể chuyển từ chi tiết sang chế độ sửa; giữ nguyên 10 cột.
- Header cố định khi cuộn, hàng xen kẽ màu như ảnh, tên/diễn giải dài không đè lên cột ngày hoặc Xóa. Có loading, rỗng, lỗi và phân trang khi cần.
- Icon trợ giúp/cài đặt trong ảnh chưa có mô tả chức năng; không tự mở rộng thành module cấu hình riêng trong gói này.

### 8.3. Modal thêm/xem/sửa — tab Tài khoản ngân hàng

Bố cục hai cột, tiêu đề “Thêm Tài Khoản Ngân Hàng”, nút X góc phải; chế độ xem/sửa đổi tiêu đề tương ứng. Giữ dữ liệu khi chuyển tab.

| Vị trí theo ảnh | Trường | Control và quy tắc dự kiến |
|---|---|---|
| Hàng 1 trái | Mã Tài Khoản Ngân Hàng | Text; trim; đề xuất bắt buộc và duy nhất trong phạm vi đơn vị được chốt |
| Hàng 1 phải | Số Tài Khoản Ngân Hàng | Text; đề xuất bắt buộc; giữ nguyên số 0 đầu, không ép sang kiểu số |
| Hàng 2 trái | Tài Khoản Hạch Toán | Dropdown từ danh mục tài khoản kế toán thực; không hardcode 1111/1121 |
| Hàng 2 phải | Ô nền xám không có nhãn | Ảnh chưa đủ để xác định nội dung; dự kiến ô chỉ đọc mô tả tài khoản hạch toán sau khi xác nhận mapping |
| Hàng 3 trái | Tiền tệ | Dropdown từ danh mục tiền tệ hiện có; có nút “+” bên cạnh như ảnh |
| Hàng 3 phải | Ô nền xám không có nhãn | Dự kiến tên tiền tệ chỉ đọc sau khi chọn mã; cần xác nhận |
| Hàng 4 trái | Tên Ngân Hàng | Text; đề xuất bắt buộc, không suy ra chủ tài khoản từ phần tên trong ngoặc |
| Hàng 4 phải | Ô nền xám không có nhãn | Chưa rõ ý nghĩa; không tự đặt tên field hoặc lưu dữ liệu suy đoán |
| Hàng 5 toàn chiều rộng | Diễn Giải | Text/textarea theo giao diện; tùy chọn, giữ nguyên nội dung khi mở lại |
| Hàng 6 trái | Tài Khoản Trung Gian | Switch; đề xuất tắt = thanh toán, bật = trung gian; khởi tạo theo tab danh sách đang mở |

**Quy tắc cần lưu ý từ ảnh**

- Ô nền vàng chưa đủ để khẳng định bắt buộc; các quy tắc bắt buộc ở bảng là đề xuất cần chốt. Backend và frontend phải dùng cùng validation sau khi xác nhận.
- Ảnh danh sách có một giá trị “Số Tài Khoản Ngân Hàng” là văn bản tên ngân hàng, nên không áp regex chỉ-chữ-số cho dữ liệu lịch sử khi chưa xác minh. Mã hạch toán cũng có dòng trống; cần kiểm tra chính sách đối với dữ liệu cũ trước khi đặt NOT NULL.
- Ngày Mở/Ngày Đóng có trong danh sách nhưng không xuất hiện trong form ảnh. Phải xác định là nhập ở đâu hay tự sinh theo sự kiện nào; không tự gán `created_at`/`deleted_at` thành hai ngày này.
- Nút “+” ở Tiền tệ: đề xuất mở luồng tạo danh mục tiền tệ hiện có với đúng quyền; khi hoàn tất tải lại dropdown và chọn giá trị mới. Không có quyền thì không cho tạo; không tự xây danh mục tiền tệ thứ hai.

**Các nút chân modal**

| Nút | Hành vi dự kiến |
|---|---|
| Tiếp | Kiểm tra dữ liệu cần thiết của tab đầu và chuyển sang tab hạch toán thuế/phí; không tự lưu bản ghi khi chỉ bấm Tiếp |
| Cancel | Hủy form; nếu có thay đổi chưa lưu thì xác nhận bỏ thay đổi |
| Lưu | Validate toàn bộ phần đã thuộc phạm vi nghiệp vụ, gửi một request tạo/cập nhật, khóa gửi lặp; lỗi giữ form và chỉ rõ trường lỗi |
| X | Đóng theo cùng quy tắc với Cancel |

### 8.4. Tab Thông tin hạch toán thuế phí cà thẻ

**Đã xác định có tab theo ảnh, chưa có nội dung trường hoặc công thức.** Giữ tab này trong thiết kế và backlog, chờ ảnh/nội dung nghiệp vụ bổ sung để hoàn thành đặc tả. Không tự tạo phần trăm phí, tài khoản thuế, tài khoản phí hoặc bút toán từ tên tab.

Khi được bổ sung, cập nhật tại đây: danh sách trường, danh mục nguồn, điều kiện bắt buộc, quan hệ với HTTT/cọc, cách tính và làm tròn nếu có, điều kiện tạo bút toán, hành vi Tiếp/Quay lại và cách lưu hai tab trong cùng transaction. Chưa coi tab này đã hoàn tất chỉ vì dựng được vỏ giao diện. CRUD dữ liệu ngân hàng cơ bản có thể triển khai độc lập; nếu tab thuế/phí là bắt buộc trước khi lưu thì chỉ hoàn tất luồng lưu đầy đủ sau khi có nghiệp vụ.

### 8.5. Luồng CRUD và phân quyền

| Luồng | Các bước | Kết quả và kiểm soát |
|---|---|---|
| Create — Thêm | Chọn nhóm → Thêm → nhập dữ liệu → Tiếp khi cần → Lưu | Sinh một bản ghi đúng nhóm; hiển thị lại danh sách; chống mã trùng và gửi lặp |
| Read — Xem | Tìm/chọn tab → click mã/double-click dòng → xem chi tiết | Tải dữ liệu theo ID; người chỉ có quyền xem thấy form chỉ đọc, không gọi cập nhật |
| Update — Sửa | Từ chi tiết bấm Sửa → sửa → Lưu | Cập nhật đúng ID; giữ lịch sử; nếu đổi cờ trung gian thì dòng chuyển sang tab tương ứng sau tải lại |
| Delete — Xóa | Bấm icon thùng rác → xác nhận rõ mã/số tài khoản → gọi xóa | Hủy xác nhận không thay đổi; BE kiểm tra quyền và tham chiếu; danh sách/modal cọc tải lại sau thành công |

**Đề xuất chính sách xóa, cần chốt:** tài khoản đã có giao dịch không xóa vật lý; từ chối xóa kèm thông báo đang được sử dụng và xử lý ngừng dùng/đóng theo nghiệp vụ được xác nhận. Tài khoản chưa dùng có thể xóa mềm để giữ lịch sử quản trị. Không đồng nhất thao tác Xóa với Ngày Đóng cho đến khi có quy tắc rõ ràng. Phải kiểm tra tham chiếu và trạng thái trong transaction phù hợp để tránh vừa chọn tạo cọc vừa xóa danh mục.

Quyền dự kiến: xem, thêm, sửa, xóa danh mục; quyền chọn tài khoản khi tạo cọc tách khỏi quyền quản trị danh mục. Kiểm tra ở API, không chỉ ẩn nút. Việc sửa mã, đổi nhóm hoặc tiền tệ khi đã có giao dịch phải có chính sách rõ ràng, không làm thay đổi ý nghĩa dữ liệu cũ.

### 8.6. Thiết kế dữ liệu/API và nối vào Đặt cọc

Hiện trạng code đã ghi nhận: modal cọc còn ba option mẫu, `bankAccountId` chứa chuỗi và gửi vào `debit_account`; menu System đang disabled. Rà schema hiện có trước khi tạo bảng mới và xác định danh mục nằm ở DB System hay PMS.

| Dữ liệu đề xuất | Ánh xạ UI / lưu ý |
|---|---|
| `id`, `code` | ID kỹ thuật và Mã Tài Khoản; không dùng số tài khoản làm ID |
| `bank_account_number` | Số Tài Khoản Ngân Hàng kiểu string |
| `accounting_account_id` hoặc mã tương đương | Tài Khoản Hạch Toán; tham chiếu danh mục thực theo kiến trúc hiện có |
| `currency_id` hoặc `currency_code` | Tiền tệ; chuẩn hóa theo danh mục đang dùng |
| `bank_name`, `description` | Tên Ngân Hàng, Diễn Giải |
| `is_intermediary` | Phân nhóm hai tab; không thay thế trạng thái ngừng dùng |
| `opened_on`, `closed_on` nullable | Ngày Mở/Ngày Đóng; chỉ chốt cách ghi khi có nghiệp vụ |
| Phạm vi đơn vị, audit, trạng thái/xóa mềm | Theo kiến trúc/quy tắc được chốt; không tự thêm vào bảng UI |
| `payments.bank_account_id` hoặc liên kết tương đương | Tham chiếu danh mục ngân hàng; không ghi đè ý nghĩa tài khoản hạch toán của `debit_account` |

Tên trường là đề xuất kỹ thuật, phải đối chiếu schema thực trước migration. Không tạo FK xuyên hai kết nối DB nếu kiến trúc không hỗ trợ. Dữ liệu hạch toán thuế/phí sẽ bổ sung sau đặc tả tab 2.

**API dự kiến:** list có search/nhóm/phân trang, detail theo ID, create, update, delete; endpoint và HTTP method theo convention dự án. Lỗi mã trùng, quan hệ danh mục sai, thiếu quyền, bản ghi đang được sử dụng phải có mã/thông báo rõ. Kiểm tra unique theo phạm vi sở hữu ở cả validation và DB.

**Tích hợp Đặt cọc**

1. Dropdown lấy danh mục thật, nhãn đề xuất “Mã tài khoản — Số tài khoản — Tên ngân hàng”; không thêm Chủ tài khoản vì form mới không có trường này.
2. Dùng ID tham chiếu khi tạo cọc; ánh xạ riêng tài khoản hạch toán nếu có nghiệp vụ, không lưu lẫn chuỗi hiển thị với mã kế toán.
3. Cần chốt dropdown cọc chỉ lấy Ngân Hàng Thanh Toán hay gồm Ngân Hàng Trung Gian. Đề xuất chỉ nhóm thanh toán còn được sử dụng, nhưng chưa coi là quy tắc đã được xác nhận.
4. Khi danh mục thay đổi/ngừng dùng/xóa, refresh lựa chọn mới; cọc cũ vẫn hiển thị thông tin lịch sử. Xử lý trường hợp tài khoản không còn hợp lệ lúc người dùng bấm Lưu.
5. Không seed dữ liệu trong ảnh hoặc ba option mẫu thành tài khoản ngân hàng thật. Ánh xạ `debit_account` cũ chỉ khi có bằng chứng khớp; giữ nguyên dữ liệu chưa khớp.
6. Thêm/sửa danh mục không thay đổi số tiền/ngày/bộ phận của cọc cũ. Tách/chuyển/xóa cọc bảo toàn liên kết hoặc snapshot phù hợp.

### 8.7. Các bước triển khai

1. Chốt những điểm còn mở ở 8.9; đối chiếu danh mục kế toán/tiền tệ, kết nối DB và cơ chế quyền hiện có.
2. Chốt hợp đồng dữ liệu, unique, ngày mở/đóng, chính sách xóa và lọc dropdown cọc; chuẩn bị dữ liệu test cho cả hai nhóm.
3. Tạo migration cộng thêm và cơ chế tương thích lịch sử; không đổi nghĩa `debit_account` trực tiếp.
4. Xây API CRUD, search/phân trang, validation, phân quyền và kiểm tra tham chiếu.
5. Bật menu và xây danh sách đúng 10 cột/hai tab; thêm tương tác xem/sửa và xác nhận xóa.
6. Xây modal hai cột theo ảnh, chế độ thêm/xem/sửa, lookup kế toán/tiền tệ, switch trung gian, nút chân modal. Tab thuế/phí hoàn thiện theo đặc tả bổ sung.
7. Nối dropdown cọc, reload dữ liệu và xử lý tài khoản đã ngừng dùng/xóa theo chính sách; đối soát dữ liệu cũ.
8. Chạy kiểm thử API và UAT theo ảnh; bàn giao bằng chứng CRUD, phân quyền, lịch sử và tích hợp cọc.

### 8.8. Tiêu chí nghiệm thu

| Mã | Kiểm tra | Điều kiện đạt |
|---|---|---|
| BA-01 | Danh sách | Đúng 10 cột, đúng thứ tự; ngày trống không hiện ngày giả; chữ dài không che Xóa |
| BA-02 | Phân nhóm | Có hai tab; switch trung gian quyết định nhóm; cập nhật nhóm không nhân đôi dòng |
| BA-03 | Thêm | Form đúng các trường ảnh; lưu một lần, hiển thị ngay; số 0 đầu được giữ |
| BA-04 | Tìm/xem | Tìm đúng các trường hỗ trợ; mở lại đủ dữ liệu, đúng tiền tệ và tài khoản hạch toán |
| BA-05 | Sửa | Đúng bản ghi; lỗi giữ dữ liệu nhập; Cancel/X không lưu ngầm |
| BA-06 | Xóa | Hỏi xác nhận đúng dòng; kiểm tra quyền/tham chiếu; không xóa nhầm lịch sử cọc |
| BA-07 | Lookup | HT hạch toán/tiền tệ lấy dữ liệu thật; nút + tiền tệ theo quyền và cập nhật lại lựa chọn |
| BA-08 | Validation | Mã trùng, thiếu trường bắt buộc, dữ liệu danh mục không hợp lệ có lỗi tại field; giữ tương thích dữ liệu cũ theo chính sách |
| BA-09 | Ngày mở/đóng | Hiển thị và cập nhật đúng nghiệp vụ đã chốt; không dùng audit timestamp thay thế tùy tiện |
| BA-10 | Tích hợp cọc | Bỏ hardcode; đúng nhóm đủ điều kiện; lưu/mở lại đúng ID, không lẫn mã kế toán và số ngân hàng |
| BA-11 | Lịch sử/quyền | Tài khoản không còn dùng không được chọn mới theo chính sách; giao dịch cũ đọc được; API chặn thiếu quyền |
| BA-12 | Tab thuế/phí | Sau khi có đặc tả: đúng trường/quy tắc, Tiếp chuyển tab không tạo bản ghi dở dang, Lưu bảo toàn dữ liệu cả hai tab |

### 8.9. Nghiệp vụ còn cần bổ sung

- Nội dung tab **Thông tin hạch toán thuế phí cà thẻ** và ý nghĩa ba ô xám chưa có nhãn ở tab đầu.
- Nguồn/cách nhập Ngày Mở, Ngày Đóng; việc đóng tài khoản ảnh hưởng lựa chọn mới như thế nào.
- Phạm vi danh mục công ty/chi nhánh/khách sạn và unique mã/số tài khoản trong phạm vi nào.
- Các trường bắt buộc; có cho dữ liệu mới nhập số tài khoản dạng văn bản như một dòng trong ảnh không; quy tắc sửa mã/tiền tệ/nhóm của tài khoản đã dùng.
- Chính sách xóa tài khoản đã/chưa có giao dịch; vai trò được quản lý danh mục.
- Modal cọc lấy nhóm thanh toán hay cả trung gian; HTTT nào bắt buộc chọn tài khoản; có được đổi ngân hàng của cọc đã tạo không.

Các cột danh sách và trường có nhãn trong ảnh đã được đưa vào phạm vi, không hỏi lại việc có cần tiền tệ/mã tài khoản hạch toán hay không.

**Trạng thái:** [x] Cập nhật cột và form theo hai ảnh · [ ] Bổ sung tab thuế/phí và quy tắc còn mở · [x] Schema/API/quyền · [x] CRUD System · [x] Nối cọc và đọc dữ liệu lịch sử (chưa backfill) · [ ] UAT.

## 2. Các nguyên tắc chung khi triển khai

1. Payments là nguồn giao dịch để đối soát; mọi tổng/cached value phải theo cùng định nghĩa, không cộng lại cả dòng đảo và dòng đã chuyển.
2. Backend kiểm tra quyền, quan hệ booking/phòng/khách và trạng thái tại thời điểm thực hiện; disable UI không thay thế kiểm tra dữ liệu.
3. Thao tác làm đổi tiền cần transaction và chống lặp/đụng nhau phù hợp. Không sửa luồng thanh toán thường ngoài phần tích hợp cần thiết của 8 section.
4. Phân biệt ngày nghiệp vụ, ngày giao dịch và ngày tạo; giữ nguyên quy tắc hiện hành đến khi nghiệp vụ mới được chốt.
5. Các kiểm tra kiểu dữ liệu ID/status cần nhất quán để tránh `0` dạng chuỗi bị loại khỏi tổng hoặc dropdown.
6. Mọi lỗi tải phải có trạng thái rõ ràng; không dùng 0/mảng rỗng giả như dữ liệu thành công.

## 3. Các gói triển khai và ước lượng

Ước lượng sơ bộ theo ngày công gồm dev và kiểm tra trong gói, chưa gồm thời gian chờ nghiệp vụ/UAT. Cần hiệu chỉnh sau khi tái hiện và xác minh kiến trúc ngân hàng; không phải ngày cam kết bàn giao.

| Gói | Công việc | Phụ thuộc | Ước lượng | Bằng chứng hoàn thành |
|---|---|---|---|---|
| A | Tái hiện 8 lỗi, bộ dữ liệu mẫu, ghi nhận request/response | Có môi trường thử | 0,5–1 ngày | Danh sách hiện tượng và nguyên nhân xác minh |
| B | Section 3 + 6: khóa thao tác, lý do xóa | Chốt chính sách xóa nhiều nếu đổi bulk | 1–1,5 ngày | Video thao tác, test API lý do/quyền/dòng đảo |
| C | Section 1: ngữ cảnh MR/FO/RC | Chốt dòng điều chỉnh | 0,5–1 ngày | Ma trận entry point và bản ghi DB |
| D | Section 5 + 4: summary/state và layout | Chốt nghĩa tổng | 1,5–2,5 ngày | Mở booking lần đầu đúng số, ảnh giao diện mới |
| E | Section 2: ảnh | Xác minh file/storage thực tế | 0,5–1 ngày | Ảnh cũ/mới xem được dev và build |
| F | Section 7: đích chuyển, payload, dropdown | Chốt phạm vi đích | 1–2 ngày | Danh sách đối chiếu, ID đích và số dư |
| G | Section 8: CRUD ngân hàng, hai nhóm, form theo ảnh, nối cọc | Chốt DB/phạm vi/quyền/ngày/xóa | 3–4 ngày | Migration, API, danh sách 10 cột, CRUD, modal cọc dùng dữ liệu thật; chưa gồm nội dung tab thuế/phí chưa được cung cấp |
| H | Kiểm tra tích hợp, UAT, chuẩn bị triển khai | Các gói cần phát hành đã xong | 1–1,5 ngày | Biên bản test, UAT và phương án rollback |

Tổng sơ bộ sau cập nhật Section 8: **9–14,5 ngày công**, chưa gồm nội dung tab hạch toán thuế/phí cà thẻ chưa có đặc tả; giả định không cần khôi phục file ảnh mất hoặc chuyển đổi dữ liệu lịch sử lớn. Nếu kiến trúc tài khoản ngân hàng cần thay đổi nhiều kết nối/dữ liệu dùng chung, ước lượng lại gói G. Gói B–F có thể bàn giao trước gói G khi đã kiểm tra các phụ thuộc chung.

## 4. Bộ dữ liệu và kiểm thử tích hợp

Chuẩn bị booking Đăng ký/Inhouse/đã checkout; cọc Master/phòng/khách; cọc mới/ngày cũ/đã dùng/đã xóa/đã chuyển; người có/không có quyền; ảnh còn/mất; tài khoản active/inactive; nhiều booking và khách Guest 1/Guest 2.

| Mã | Kịch bản | Điều kiện đạt |
|---|---|---|
| INT-01 | Đặt phòng tạo cọc 1.000.000 có ảnh | MR/RC, ảnh bền vững, tổng đúng ngay |
| INT-02 | Lễ tân mở booking trên, thêm 500.000 | Dòng mới FO/RC, dòng cũ vẫn MR/RC, tổng 1.500.000 |
| INT-03 | Sửa HTTT/mô tả | Khóa đúng nút, không đổi số tiền/ngày/bộ phận gốc |
| INT-04 | Tách 1.000.000 thành 400.000 + 600.000 | Tổng không đổi; danh sách card và modal cùng dữ liệu |
| INT-05 | Chuyển 400.000 sang booking/phòng/khách B | Dropdown đủ booking và phòng/khách như Hóa đơn → Chuyển phòng; payload gửi đúng ID đích cấp đã chọn; nguồn giảm/đích tăng 400.000 |
| INT-06 | Xóa 600.000 có lý do | Đảo đúng một lần, lịch sử đầy đủ, tổng cập nhật |
| INT-07 | Xóa/cấn trừ cùng khoản từ hai phiên | Không dùng hoặc đảo cùng giá trị hai lần |
| INT-08 | Refresh và mở lại A/B từ nhiều entry point | Tổng đúng không cần mở Thêm cọc |
| INT-09 | Ngừng dùng tài khoản ngân hàng đã có cọc | Không chọn mới được, cọc cũ vẫn hiển thị đúng |
| INT-10 | API lỗi/response chậm/đổi booking nhanh | Không mất form, không hiện sai tổng hoặc đích |
| INT-11 | Hóa đơn sau các thao tác cọc | Cấn trừ/quyền/folio/phòng/khách không bị hồi quy |

Khi triển khai: bổ sung test backend có ý nghĩa cho bộ phận/outlet, lý do xóa, tổng cọc, đích chuyển booking/phòng/khách, quyền danh mục ngân hàng và cạnh tranh thao tác. Chạy các test payment/booking/folio liên quan đang có; build frontend theo scripts dự án; kiểm thử giao diện thủ công cho Section 2–4 và dropdown booking/phòng/khách. Không cần viết test tự động chỉ để kiểm tra class CSS.

## 5. Triển khai dữ liệu và điều kiện bàn giao

- Gói sửa UI/API thuần tách khỏi migration ngân hàng để có thể phát hành và rollback rõ ràng.
- Migration ưu tiên cộng thêm trường/bảng; thử trên dữ liệu mẫu có cọc cũ trước. Kiểm tra báo cáo số lượng/tổng tiền trước và sau, bảo toàn lịch sử.
- Backfill outlet/bank account là bước riêng sau khi có quy tắc và báo cáo dữ liệu cụ thể; không đưa cập nhật suy đoán vào migration mặc định.
- Rollback ứng dụng phải giữ được khả năng đọc dữ liệu cũ/mới; không xóa dữ liệu giao dịch mới hoặc bảng ngân hàng đã có liên kết chỉ để lùi phiên bản.
- Mỗi section hoàn tất cần ảnh/video trước–sau, kết quả test và các điểm nghiệp vụ còn treo. Chỉ đánh dấu “Hoàn thành” sau khi tiêu chí tương ứng đạt.
- Hạng mục chưa chốt nghiệp vụ giữ trạng thái chờ phần quyết định đó; các phần rõ ràng như gửi lý do, khóa nút, xử lý clipping vẫn có thể chuẩn bị/sửa độc lập khi bắt đầu triển khai.

## 6. Mẫu để bạn bổ sung nghiệp vụ theo section

Bạn có thể bổ sung trực tiếp dưới từng section phía trên hoặc dùng bảng này. Khi có bổ sung, cập nhật đồng thời yêu cầu, giải pháp và test của section tương ứng; giữ số section/ảnh để dễ đối chiếu.

| Section | Nội dung cần bổ sung/chốt | Phản hồi nghiệp vụ | Trạng thái |
|---|---|---|---|
| 1 | Bộ phận của dòng điều chỉnh; có backfill cũ không | _Bạn bổ sung tại đây_ | Chờ |
| 2 | Một/nhiều ảnh; có PDF không | _Bạn bổ sung tại đây_ | Chờ, mặc định phạm vi ảnh hiện có |
| 3 | X/ESC khi đang sửa | _Bạn bổ sung tại đây_ | Chờ, có đề xuất ở section |
| 4 | Thứ tự dòng và trường cần xem thêm | _Bạn bổ sung tại đây_ | Chờ, có thể bám ảnh trước |
| 5 | Tổng khả dụng/lịch sử; phạm vi phòng/khách/AP/tiền tệ | _Bạn bổ sung tại đây_ | Cần chốt trước công thức |
| 6 | Lý do tự nhập/danh mục; xóa nhiều dòng | _Bạn bổ sung tại đây_ | Cần chốt chính sách nhiều dòng |
| 7 | Phạm vi booking/phòng/khách đích và nguồn danh sách Hóa đơn → Chuyển phòng | Đã khôi phục hiển thị booking + phòng/khách tương ứng theo yêu cầu mới nhất | Đã rõ hướng hiển thị; còn UAT đối chiếu danh sách thực tế |
| 8 | Tab thuế/phí, ô xám, ngày mở/đóng, phạm vi/unique/quyền/xóa, nhóm dùng cho cọc | Đã bổ sung hai ảnh: danh sách 10 cột và form CRUD tại Section 8 | Đã rõ bố cục; còn chốt các quy tắc ở 8.9 |

### Nhật ký cập nhật

| Ngày | Nội dung | Tình trạng |
|---|---|---|
| 09/09/2026 | Lập kế hoạch 8 section theo Word/ảnh, đối chiếu mã nguồn, thêm tiêu chí nghiệm thu và chỗ bổ sung nghiệp vụ | Chưa sửa code ứng dụng |
| 09/09/2026 | Cập nhật Section 8 theo hai ảnh mới: 10 cột, hai nhóm ngân hàng, form và luồng CRUD, mapping kế toán/tiền tệ, kiểm thử; điều chỉnh ước lượng, ghi rõ phần tab thuế/phí chưa có nội dung | Chỉ cập nhật kế hoạch |
| 10/09/2026 | Triển khai FE/BE cho Section 1–8: MR/FO + RC, URL/preview chứng từ, khóa thao tác khi sửa, card và tổng active DPR, lý do xóa/ledger, đích chuyển đủ ID, migration/API/CRUD ngân hàng và lookup thật; giữ tab thuế/phí ở placeholder | Kiểm tra tĩnh, migration pretend, route list và frontend build đạt; bộ test Booking/DebtSettlement hiện bị 403 quyền fixture; còn UAT/đối soát dữ liệu lịch sử |

## Ghi chú commit GitHub

### Phạm vi đã thực hiện

- **Section 1:** Lưu đúng bộ phận MR/FO theo module tạo cọc và `outlet = RC` cho cọc PMS; giữ bộ phận/outlet trên các dòng điều chỉnh.
- **Section 2:** Upload, lưu đường dẫn và xem thumbnail/preview ảnh chứng từ; xử lý URL `/storage` và trạng thái lỗi.
- **Section 3:** Khóa Tách/Chuyển/Xóa/Sửa khi đang sửa cọc; giới hạn form sửa và payload đúng phạm vi.
- **Section 4:** Cập nhật card Đặt cọc trên booking: tổng tiền, từng khoản, tiền tệ và vùng cuộn.
- **Section 5:** Đồng bộ tổng active DPR khi mở booking và sau thao tác; tránh hiển thị 0 giả do chưa tải dữ liệu.
- **Section 6:** Thêm form nhập lý do xóa, gửi đúng payload DELETE và giữ ledger đảo/audit/quyền hiện hành.
- **Section 7:** Dropdown chuyển cọc hiển thị booking, phòng và khách tương ứng; chọn đúng cấp gửi `target_booking_id`, `target_room_id`, `target_guest_id`; xử lý danh sách đầy đủ, tìm kiếm và overlay không clipping.
- **Section 8:** Thêm migration/API/phân quyền/UI CRUD tài khoản ngân hàng; hai nhóm Thanh toán/Trung gian, 10 cột danh sách, form lookup kế toán/tiền tệ và nối dropdown đặt cọc thay dữ liệu hardcode.

### Chưa nằm trong commit hoàn tất

- UAT giao diện và đối soát số dư trên dữ liệu thực tế.
- Backfill dữ liệu lịch sử outlet/tài khoản ngân hàng.
- Nội dung chi tiết tab “Thông tin hạch toán thuế phí cà thẻ” vì chưa có đặc tả.

### Commit message đề xuất

```text
fix(deposit): complete deposit workflow and bank account management

- fix deposit department/outlet by source module
- add receipt image preview and delete reason
- lock deposit actions while editing and sync booking totals
- improve deposit transfer destinations and payload IDs
- add bank account CRUD, grouping, permissions, and deposit lookup
```
