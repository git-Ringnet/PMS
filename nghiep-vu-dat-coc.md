# TÀI LIỆU NGHIỆP VỤ: CHỨC NĂNG ĐẶT CỌC (DEPOSIT)
> Nguồn: hệ thống PMS cũ (SQL Server), khách hàng cung cấp. Mục đích: làm input để AI/dev phân tích và ánh xạ sang schema MySQL của dự án hiện tại.

---

## PHẦN 1 — MÔ TẢ 2 BẢNG GỐC (SQL SERVER CŨ)

### 1.1. Bảng `SP1326` — Danh mục Hình thức thanh toán (Payment Method)

Bảng lưu danh sách các hình thức thanh toán (tiền mặt, thẻ, chuyển khoản, công nợ, voucher...) dùng chung cho cả module Thanh toán và module Đặt cọc.

| Cột | Ý nghĩa (suy luận từ dữ liệu mẫu) | Ghi chú / Cần xác nhận |
|---|---|---|
| `Ma` | Mã hình thức thanh toán, viết tắt duy nhất (VD: AC, BT, CA, CD, CL, V1, V2, VA, VB, VD, VJ, VM, VO, VU, VV) | Khóa chính (business key) |
| `FirstName` | Tên đầy đủ hình thức thanh toán (VD: "Credit Card", "Cash", "Complimentary", "VCB_VISA_QT"...) | |
| `Account` | Tài khoản kế toán liên kết (đa số NULL trong mẫu, chỉ CD có "NULL" text) | Cần hỏi: có phải mã TK kế toán ánh xạ qua bảng khác? |
| `AccoundName` (sic — lỗi chính tả gốc "AccountName") | Tên tài khoản kế toán tương ứng | |
| `STT` | Số thứ tự hiển thị / thứ tự sắp xếp trên UI | |
| `ShortName` | Tên rút gọn hiển thị (VD: "City ledger", "Cash", "Credit Card", "Complimentary", "Voucher") | |
| `ServiceRate` | Tỷ lệ phí dịch vụ áp dụng cho HTTT này (đa số = 0) | Có thể dùng cho phụ thu thẻ, phí cổng thanh toán |
| `Status` | Trạng thái phân loại: NULL, hoặc "1,2" (dữ liệu dạng chuỗi list mã nhóm) | **Chưa rõ ý nghĩa từng mã** — cần hỏi KH: 1 = ? 2 = ? |
| `Department` | Bộ phận áp dụng (VD: "Reception,Reservation") — dạng chuỗi liệt kê, không chuẩn hoá | Nên tách bảng con `payment_method_department` khi làm mới |
| `Outlet` | Điểm bán/outlet áp dụng | |
| `Group` | Nhóm HTTT (0,1,2,3...) | **Nghi vấn: đây có thể là cột dùng để phân loại "công nợ / tiền mặt / thẻ / voucher"** — cần xác nhận vì đây là điều kiện lọc quan trọng khi tạo cọc |
| `HTMienPhi` | Cờ đánh dấu "Hình thức miễn phí" (0/1) — tên cột đã gợi ý rõ | Dùng để **loại khỏi danh sách khi tạo đặt cọc** theo yêu cầu nghiệp vụ |
| `Disable` | Cờ ẩn/khóa HTTT (0 = đang dùng, khác 0 = ẩn) | |

**Ghi chú quan trọng:** Nghiệp vụ yêu cầu *"không hiển thị hình thức thanh toán là công nợ và các HTTT miễn phí khi tạo đặt cọc"*. Trong bảng mẫu:
- **Miễn phí** → có thể lọc trực tiếp qua cột `HTMienPhi = 1`.
- **Công nợ** → **chưa có cột rõ ràng nào đánh dấu "công nợ"**. Khả năng cao là dựa vào `Ma = 'AC'` (City ledger — theo tên gọi ngành khách sạn, "City Ledger" = công nợ) hoặc dựa vào `Group`. **→ Đây là điểm bắt buộc phải hỏi lại khách hàng trước khi code**, không nên đoán.

---

### 1.2. Bảng `SP3002` — Sổ Thu/Chi (dùng chung cho Thanh toán & Đặt cọc)

Đây là bảng giao dịch trung tâm, mỗi dòng là một bút toán thu (dương) hoặc chi/hoàn (âm). Đặt cọc là một loại bút toán đặc biệt trong bảng này, phân biệt qua `PaymentID`, `Status`, `Edit`.

| Cột | Ý nghĩa (suy luận) | Ghi chú |
|---|---|---|
| `Ma` | Mã giao dịch (khóa chính) | |
| `Date` | Ngày giao dịch (ngày phát sinh nghiệp vụ, do người dùng chọn) | Phải <= ngày hệ thống |
| `OpenTime` | Giờ mở ca / thời điểm giao dịch | |
| `Guest` | Tên khách liên quan | |
| `RefId` | Mã tham chiếu (booking/phòng) | |
| `DepartmentId` | Bộ phận thực hiện giao dịch | |
| `PaymentMethod` | Hình thức thanh toán — FK tới `SP1326.Ma` | |
| `Description` | Diễn giải/ghi chú giao dịch | Với cọc: tự sinh `"Deposit + (HTTT)"`, cho phép sửa |
| `Amount` | Số tiền giao dịch (dương = thu/đặt cọc, âm = hoàn/đối trừ) | |
| `Currency` | Loại tiền | Mặc định VNĐ cho đặt cọc |
| `Exchange` | Tỷ giá quy đổi | |
| `NotPrint` | Cờ không in hoá đơn/phiếu | |
| `Edit` | Cờ đánh dấu dòng đã bị tác động bởi nghiệp vụ sửa/tách/chuyển/xóa (0 = dòng gốc chưa đổi, 1 = dòng đã phát sinh điều chỉnh) | Dùng để loại trừ khỏi số dư hiện tại, chỉ phục vụ audit |
| `Folio` | Số tờ hóa đơn nội bộ (folio) của booking | |
| `PaymentID` | Nếu NULL → cọc **chưa** được dùng để thanh toán; nếu có giá trị → đã cấn trừ vào một lần thanh toán | **Cột điều kiện chính** cho phép Sửa/Tách/Chuyển/Xóa cọc |
| `VATNumber` | Số hóa đơn VAT liên quan | |
| `RegisterID2` | Mã booking/đăng ký đích (dùng khi Chuyển cọc sang booking khác) | |
| `RentalRoomId2` | Mã phòng đích (dùng khi chuyển cọc sang phòng khác) | |
| `CustomerId2` | Mã khách hàng đích | |
| `CompanyId2` | Mã công ty/đối tác đích | |
| `Username` | User thực hiện giao dịch (tại thời điểm tạo) | |
| `Ca` | Ca làm việc | |
| `Status` | Trạng thái dòng: `Status=3` → dòng đã xóa (soft delete), hiển thị khi tick "Hiển thị đã xóa" | Cần hỏi thêm các giá trị Status khác (1,2,...) nghĩa là gì |
| `Outlet` | Điểm bán | |
| `InvoiceId`, `Serial`, `InvoiceNumber` | Thông tin hóa đơn phát hành liên quan | |
| `DebitAccount`, `CreditAccount`, `RevenueAccount`, `CostAccount` | Tài khoản kế toán Nợ/Có/Doanh thu/Chi phí tương ứng bút toán | |
| `PaymentYear`, `PaymentMonth`, `PaymentDay` | Cột tách sẵn từ `Date` để tối ưu truy vấn/báo cáo (denormalized) | |
| `CreatedUser`, `CreatedDate`, `CreatedHour` | Người/ngày/giờ tạo dòng gốc | Với Tách cọc: dòng mới lấy `CreatedDate` = ngày dòng gốc |
| `UpdatedUser`, `UpdatedDate`, `UpdatedHour` | Người/ngày/giờ cập nhật gần nhất | Với Tách cọc: cả 2 dòng (gốc + mới) đều update = thời điểm thao tác |
| `OwnerUser`, `RootOwnerUser` | Chủ sở hữu dữ liệu / chủ sở hữu gốc (phân quyền dữ liệu theo user) | |
| `ExchangeRate1`, `ExchangeRate2` | Tỷ giá quy đổi 1/2 (đa tiền tệ) | |
| `TotalAmount1`, `TotalAmount2` | Tổng tiền quy đổi theo loại tiền 1/2 | |
| `PaymentCurrency0/1/2`, `PaymentExchangeRate1/2`, `PaymentTotalAmount0/1/2` | Bộ 3 cấu trúc lưu số tiền theo tối đa 3 loại tiền tệ khác nhau cho 1 bút toán (đa tiền tệ) | `PaymentTotalAmount2` với cọc = **số tiền ban đầu trước khi tách/chỉnh sửa** (theo yêu cầu nghiệp vụ Tách cọc) |
| `Pack1` ... `Pack5` | Các cột liên kết dùng cho nghiệp vụ đặc biệt: khi **Xóa cọc**, `Pack1` của dòng dương lưu mã dòng âm sinh ra và ngược lại → tạo cặp liên kết 2 chiều để dựng lại lịch sử cho báo cáo | `Pack2..Pack5` chưa rõ mục đích — cần hỏi thêm |
| `VatId` | Mã VAT áp dụng | |

---

## PHẦN 2 — CHI TIẾT NGHIỆP VỤ CHỨC NĂNG ĐẶT CỌC

### 2.0. Mục đích tổng quát
Cho phép ghi nhận và quản lý các khoản tiền khách đặt cọc trước khi lưu trú. Khi thanh toán booking, hệ thống **tự động cấn trừ** số dư cọc còn khả dụng (chưa gắn `PaymentID`) vào số tiền booking phải thanh toán, từ đó tính ra: số tiền khách còn phải trả thêm, hoặc số tiền cần hoàn lại nếu cọc lớn hơn công nợ.

### 2.1. Tạo đặt cọc
- **Tên đăng ký**: hiển thị tên booking đang thao tác, **không cho sửa**.
- **Phương thức thanh toán**: load từ `SP1326`, **loại bỏ**:
  - HTTT có cờ miễn phí (`HTMienPhi = 1`)
  - HTTT loại công nợ (cột xác định — *cần khách hàng xác nhận, xem lưu ý ở mục 1.1*)
- **Số tiền**: nhập số tiền khách đặt cọc.
- **Ngày**: phải `<=` ngày hệ thống. Nếu chọn ngày trong quá khứ → hệ thống kiểm tra **quyền "cho phép điều chỉnh ngày cũ"** của user hiện tại; không có quyền thì không cho lưu.
- **Mô tả**: tự sinh theo cú pháp `Deposit + (Tên HTTT)`, người dùng có thể sửa tay.
- **Tiền tệ**: mặc định VNĐ.
- **Hiển thị đã xóa**: khi bật, hiển thị thêm các dòng có `Status = 3` (bao gồm cả dòng âm và dòng dương của các lần xóa).

### 2.2. Sửa đặt cọc
- Chỉ cho sửa **2 trường**: Hình thức thanh toán, Ghi chú/Mô tả. Không cho sửa số tiền, ngày.
- Điều kiện cho phép sửa (**cả 2 điều kiện phải đúng**):
  1. Booking chưa check-out.
  2. Đặt cọc chưa được dùng để thanh toán (`PaymentID IS NULL`).

### 2.3. Tách đặt cọc (Split)
- Hỗ trợ 3 kiểu tách: **tách đôi** (2 phần bằng nhau), **tách ba** (3 phần bằng nhau), hoặc **tách theo số tiền tự nhập**.
- Xử lý dữ liệu:
  - **Update** dòng gốc: số tiền còn lại sau khi tách.
  - **Insert** thêm 1 (hoặc nhiều) dòng mới với phần số tiền còn lại.
  - Dòng mới lấy `Date`, `CreatedDate` = **giống dòng gốc** (giữ nguyên ngày phát sinh ban đầu).
  - `UpdatedDate` của **cả 2 dòng** (gốc + mới) = thời điểm thực hiện tách.
  - `PaymentTotalAmount2` = số tiền **trước khi tách** (lưu lại để biết giá trị gốc, phục vụ truy vết).
- Điều kiện cho phép tách (giống mục 2.2): booking chưa check-out **và** `PaymentID IS NULL`.
- ⚠️ Cần xác nhận thêm: quy tắc làm tròn khi tách 3 phần không chia hết hết số nguyên (đồng dư đưa vào dòng nào).

### 2.4. Chuyển đặt cọc (Transfer)
- Cho phép chuyển cọc từ booking/phòng hiện tại sang **booking/phòng khác** (chỉ áp dụng cho booking đích có tình trạng **"Đăng ký" hoặc "Inhouse"**).
- Xử lý dữ liệu (sinh ra **3 dòng**, không sửa trực tiếp dòng gốc):
  1. Dòng gốc (dương, đã tồn tại) → gắn `Edit = 1`.
  2. Dòng âm mới sinh, đối trừ giá trị dòng gốc, `CreateDate` = ngày hệ thống hiện tại, `CreateUser` = user thực hiện, `Edit = 1`.
  3. Dòng dương mới, mang thông tin booking đích (`RegisterID2`, `CompanyId2`...), `Edit = 0`, nhưng `CreateDate`/`CreateUser` = **giống dòng gốc ban đầu** (giữ lịch sử tạo).
- Điều kiện cho phép chuyển: booking chưa check-out **và** `PaymentID IS NULL`.

### 2.5. Xóa đặt cọc (Delete)
- Kiểm tra quyền theo 2 tầng:
  1. User có **quyền xóa cọc** hay không.
  2. Nếu có quyền xóa → kiểm tra tiếp: nếu `CreatedDate` của dòng cọc **< ngày hệ thống** (dữ liệu cũ) thì cần thêm **quyền xóa dữ liệu ngày cũ**.
- Xử lý dữ liệu: **không xóa vật lý** — sinh ra 1 dòng âm đối trừ với dòng gốc:
  - Cả 2 dòng (gốc dương + dòng âm mới) đều gắn `Edit = 1`.
  - `Pack1` của dòng dương = mã dòng âm vừa sinh; `Pack1` của dòng âm = mã dòng dương gốc (liên kết 2 chiều).
  - Mục đích: phục vụ **báo cáo xóa cọc**, truy vết được: dòng nào tạo lúc nào, xóa lúc nào, bởi ai.
- Điều kiện cho phép xóa: đặt cọc **chưa** được dùng để thanh toán (`PaymentID IS NULL`).

### 2.6. Cấn trừ cọc khi thanh toán (điểm cần bổ sung — chưa được mô tả chi tiết trong yêu cầu gốc)
- Khi thanh toán booking, hệ thống lấy tổng các dòng cọc khả dụng (`PaymentID IS NULL`, `Status ≠ 3`, `Edit = 0` hoặc là dòng cuối trong chuỗi edit) của booking đó, trừ vào số tiền phải thu.
- ⚠️ **Chưa rõ và cần hỏi khách hàng**:
  - Thứ tự cấn trừ nếu có nhiều dòng cọc (theo ngày tạo trước, theo HTTT, hay theo số tiền)?
  - Khi cấn trừ, các dòng cọc có được gắn `PaymentID` toàn phần hay cấn trừ từng phần (một dòng cọc có thể cấn trừ 1 phần, còn dư 1 phần)?
  - Trường hợp cọc > công nợ phải hoàn tiền: hoàn qua hình thức nào, có sinh dòng mới trong `SP3002` không?

---

## PHẦN 3 — CÁC ĐIỂM CẦN KHÁCH HÀNG XÁC NHẬN LẠI (chưa đủ dữ liệu để code chắc)

1. Cột nào trong `SP1326` xác định HTTT thuộc loại **"công nợ"** để loại khỏi danh sách khi tạo cọc? (`Ma`, `Group`, hay cột khác chưa liệt kê)
2. Ý nghĩa cụ thể các giá trị của `SP1326.Status` (NULL / "1,2") và `SP1326.Group` (0/1/2/3)?
3. Ý nghĩa các giá trị `SP3002.Status` khác ngoài `3` (xóa)?
4. Mục đích cột `Pack2`–`Pack5` trong `SP3002`?
5. Quy tắc chia số dư khi Tách ba không chia hết?
6. Logic cấn trừ cọc vào thanh toán: thứ tự, cấn trừ toàn phần/từng phần, và cách xử lý hoàn tiền dư.
7. "Ngày hệ thống" dùng so sánh khi tạo/xóa cọc là ngày server hay ngày nghiệp vụ (business/audit date) của khách sạn?

---

## PHẦN 4 — YÊU CẦU GỬI CHO AI CỦA DỰ ÁN (đã có schema MySQL thực tế)

> Copy nguyên phần dưới đây để đưa vào AI/dev đang làm dự án — AI đó sẽ tự đối chiếu với database MySQL thực tế của dự án.

```
Tôi đang cần triển khai chức năng "Đặt cọc" (Deposit) cho hệ thống PMS khách sạn,
được chuyển đổi từ hệ thống cũ (SQL Server) sang MySQL trong dự án hiện tại.

Nhiệm vụ của bạn:
1. Đọc và phân tích schema MySQL hiện có trong dự án (tất cả bảng liên quan tới:
   booking/registration, payment method, payment/thu chi, phân quyền user, phòng, khách hàng).
2. Đối chiếu với 2 bảng nghiệp vụ cũ dưới đây (SQL Server), xác định:
   - Bảng/cột nào trong dự án hiện tại đã có thể tái sử dụng tương đương.
   - Bảng/cột nào còn thiếu, cần tạo mới hoặc migrate thêm.
3. Đề xuất thiết kế schema MySQL chuẩn hóa (không bắt buộc giữ tên bảng SP1326/SP3002)
   để đáp ứng đầy đủ nghiệp vụ mô tả ở PHẦN 2 (Tạo, Sửa, Tách, Chuyển, Xóa đặt cọc,
   và cấn trừ cọc vào thanh toán).
4. Với mỗi chức năng (Tạo/Sửa/Tách/Chuyển/Xóa), viết ra:
   - Điều kiện validate đầu vào.
   - Logic xử lý dữ liệu (insert/update nào, các cột nào bị ảnh hưởng).
   - Điều kiện phân quyền cần kiểm tra.
5. Liệt kê rõ các giả định bạn phải tự đưa ra (nếu schema hiện tại không đủ thông tin
   để xác định chắc chắn), để tôi xác nhận lại trước khi code.
6. Ưu tiên thiết kế theo hướng "sổ cái không xóa vật lý" (append-only ledger) như hệ
   thống cũ đang làm (dùng dòng âm đối trừ + liên kết cặp để phục vụ audit/báo cáo),
   trừ khi bạn thấy dự án hiện tại có cách làm khác tốt hơn — nếu vậy hãy giải thích
   trade-off trước khi đề xuất.

--- MÔ TẢ BẢNG GỐC (SQL SERVER CŨ) ---
[Dán toàn bộ PHẦN 1 và PHẦN 2 của tài liệu này vào đây]
```

---

*Tài liệu này tổng hợp lại yêu cầu gốc của khách hàng, có bổ sung diễn giải cột dữ liệu và đánh dấu rõ các điểm còn mơ hồ cần hỏi lại trước khi thiết kế schema/code chính thức.*
