# Hướng Dẫn Kiểm Thử Trực Tiếp Trên Giao Diện (Booking 4 — 11 Section)

> **Tài liệu kiểm thử thực tế**: Dành cho Tester / Lễ tân / Quản trị viên test trực tiếp bằng chuột và bàn phím trên giao diện web PMS.  
> **Nguồn lỗi**: File `file word can len plan/các lỗi liên quan tới booking 4.docx` (Ảnh lỗi gốc: [docs/booking-4/image1.png](file:///d:/PMS/docs/booking-4/image1.png) đến [image11.png](file:///d:/PMS/docs/booking-4/image11.png)).

---

## 📋 BẢNG CHECKLIST TEST NHANH (10 - 15 PHÚT)

| Section | Nội dung nghiệp vụ cần kiểm tra | Thao tác nhanh trên màn hình | Kết quả mong muốn (Đạt) | Kết quả test |
|---|---|---|---|:---:|
| **S1** | Extra Bed: Xóa 1 ngày & chỉnh SL về 0 | Bấm `+` chi tiết phòng ➔ bấm icon thùng rác xóa EB 1 ngày | Báo thành công, SL về 0, tiền trừ đúng 300k, không báo lỗi đỏ | [ ] Đạt |
| **S2** | Cập nhật nhanh ngày đi ➔ cập nhật số đêm | Tích phòng ➔ Cập nhật ➔ Đổi ngày đi tăng thêm 1 ngày | Số đêm trên Header Booking và cột phòng tự động nhảy đúng | [ ] Đạt |
| **S3** | Chi tiết ăn sáng trẻ em (giá & giai đoạn) | Cập nhật trẻ em = 1; sửa giá từng đêm; dời ngày phòng ở | Lưu giá mới không bị reset; đổi ngày ở giữ nguyên giá và phụ phí | [ ] Đạt |
| **S4** | Hủy booking giữ nguyên giao diện | Bấm Xoá booking ➔ Chọn lý do ➔ Bấm Có | Màn hình ở lại tab booking vừa hủy, không bị văng sang tạo mới | [ ] Đạt |
| **S5** | Rút ngắn ngày ở không hiện DV thừa | Rút ngày đi từ 04/09 về 02/09 trên phòng có dịch vụ ngày 2, 3 | Bảng chi tiết chỉ hiện DV đến ngày 1; gia hạn lại tự hiện lại | [ ] Đạt |
| **S6** | Tên dịch vụ ngày quá khứ đồng bộ tương lai | Bấm `+` xem phòng đã check-in qua nhiều ngày | Tên dịch vụ ngày quá khứ và tương lai đồng bộ theo danh mục | [ ] Đạt |
| **S7** | Hủy phòng nhiều lần không văng lỗi đỏ | Tích phòng ➔ Hủy phòng (bấm hủy liên tiếp hoặc hủy từ 2 nơi) | Luôn báo thành công mượt mà, không bao giờ báo lỗi 404/500 | [ ] Đạt |
| **S8** | Khôi phục booking khớp giai đoạn các phòng | Booking có phòng ngày 1 và phòng ngày 2-3 ➔ Hủy ➔ Khôi phục | Header booking hiển thị bao trùm từ 01/09 đến 03/09 (2 đêm) | [ ] Đạt |
| **S9** | Modal chọn phòng hủy để khôi phục | Bấm Khôi phục (trên toolbar hoặc dock phải) | Hiện popup chọn phòng, sắp xếp theo ngày hủy mới nhất, chặn trùng | [ ] Đạt |
| **S10** | Khóa phòng từ ngày mai không chặn đặt đêm nay | Khóa phòng 305 từ ngày mai; đặt phòng 305 đêm nay (1 đêm) | Cho phép đặt phòng thành công bình thường (vẫn chặn nếu đặt 2 đêm) | [ ] Đạt |
| **S11** | Phụ phí ăn sáng trẻ: hiện trước check-in & post bill | Thêm trẻ em phòng chưa check-in; thử post trước tiền phòng | Hiện tiền phụ phí ngay; post trước tiền phòng kéo theo bill ăn sáng | [ ] Đạt |

---

## 🎯 HƯỚNG DẪN CHI TIẾT TỪNG SECTION

---

### SECTION 1: Extra Bed — Xóa 1 Ngày & Chỉnh Số Lượng Về 0

* **Màn hình**: **Tạo Đăng Ký** (Menu: `Đặt phòng` ➔ `Tạo Đăng Ký`).
* **Mục tiêu**: Xóa Extra Bed (EB) của 1 đêm hoặc chỉnh số lượng EB về 0 không bị lỗi chặn, tiền tính chính xác.
* **Ảnh tham chiếu**: [image1.png](file:///d:/PMS/docs/booking-4/image1.png).

#### Thao tác 1.1: Xóa 1 ngày Extra Bed ở bảng chi tiết phòng
1. Tạo 1 booking 3 đêm (ví dụ ngày 10 đến 13), nhập cột **Thêm giường** = `1` (giá 300,000đ/đêm) ➔ Bấm **Lưu Booking**.
2. Tại dòng phòng, bấm vào dấu **`+`** (màu cam) ở đầu dòng để mở bảng chi tiết theo ngày.
3. Tìm dòng **Giường phụ / Extrabed** của ngày thứ 2 ➔ Rê chuột đến cột *Thành tiền* ➔ Bấm icon **thùng rác (Xóa)** ➔ Bấm **Đồng ý**.

* **Kết quả ĐÚNG**:
  * Hiện thông báo xanh: `Đã xóa Extra Bed ngày ... thành công`.
  * Dòng Extra Bed ngày đó cập nhật số lượng về `0` (thành tiền `0đ`).
  * Tổng tiền phòng và tổng booking giảm đúng 300,000đ.
  * Nhấn `F5` tải lại trang ➔ Vẫn giữ nguyên 0đ cho ngày đó.
  * *(Lỗi cũ: Hiện thông báo đỏ "Không thể xóa dịch vụ khỏi hệ thống!").*

#### Thao tác 1.2: Chỉnh số lượng về 0 trong popup Chi tiết thêm giường
1. Tại dòng phòng, bấm nút **Chi tiết** ở cột *Thêm giường*.
2. Trong popup **CHI TIẾT THÊM GIƯỜNG**, sửa số lượng ngày bất kỳ thành `0` ➔ Bấm **Lưu**.

* **Kết quả ĐÚNG**:
  * Báo lưu thành công. Bấm mở lại nút **Chi tiết** ➔ Ngày đó vẫn lưu số lượng là `0`.
  * *(Lỗi cũ: Giao diện hiện 0 nhưng khi lưu DB không cập nhật, mở lại vẫn bị nhảy về 1).*

---

### SECTION 2: Cập Nhật Nhanh Ngày Đi ➔ Cập Nhật Đúng Số Đêm Của Booking

* **Màn hình**: **Tạo Đăng Ký**.
* **Mục tiêu**: Khi dùng chức năng Cập nhật nhanh để đổi ngày đi, số đêm trên Header Booking phải cập nhật tương ứng.
* **Ảnh tham chiếu**: [image2.png](file:///d:/PMS/docs/booking-4/image2.png).

#### Thao tác:
1. Mở một booking có 5 đêm (ví dụ ngày đến 31/08, ngày đi 05/09). Quan sát Header booking đang hiển thị: `Số đêm: 5`.
2. Tích chọn ô vuông trước dòng phòng ➔ Nhìn sang thanh công cụ bên phải (Dock) ➔ Bấm **Cập nhật**.
3. Trong popup Cập nhật nhanh, đổi **Ngày đi** thành `06/09` (tăng thêm 1 đêm) ➔ Bấm **Lưu**.

* **Kết quả ĐÚNG**:
  * Ngay lập tức trên Header booking hiển thị: `Ngày đi: 06/09` và **`Số đêm: 6`**.
  * Cột "Đêm" ở bảng danh sách phòng cũng tự đổi thành `6`.
  * *(Lỗi cũ: Ngày đi đổi thành 06/09 nhưng số đêm trên Header vẫn đứng yên ở số 5).*

---

### SECTION 3: Chi Tiết Ăn Sáng Trẻ Em

* **Màn hình**: **Tạo Đăng Ký**.
* **Mục tiêu**: Thêm trẻ em tự động có phụ phí ăn sáng; cho phép sửa giá từng đêm; đổi giai đoạn ở không làm mất phụ phí và không đưa giá về 0.
* **Ảnh tham chiếu**: [image3.png](file:///d:/PMS/docs/booking-4/image3.png).

#### Thao tác 3.1: Thêm trẻ em & xem phụ phí
1. Tạo booking 1 phòng ở 4 đêm ➔ Tích chọn phòng ➔ Dock phải bấm **Cập nhật** ➔ Ô *Trẻ em* nhập `1` ➔ Bấm **Lưu**.
2. Bấm dấu **`+`** đầu dòng phòng để mở bảng chi tiết ngày.

* **Kết quả ĐÚNG**:
  * Xuất hiện các dòng **Breakfast Child/Ăn Sáng Trẻ Em** (đơn giá mặc định 90,000đ/đêm) cho đủ 4 đêm.
  * Tổng tiền phòng đã tự động cộng thêm: `4 đêm × 90,000 = 360,000đ`.

#### Thao tác 3.2: Sửa giá ăn sáng trực tiếp trên bảng chi tiết từng đêm
1. Tại bảng chi tiết ngày (dấu `+`), nhấp chuột vào ô **Đơn giá** của dòng *Breakfast Child/Ăn Sáng Trẻ Em* ngày thứ 2.
2. Sửa từ `90,000` thành `100,000` ➔ Nhấp chuột ra ngoài ô nhập (blur).

* **Kết quả ĐÚNG**:
  * Hiện thông báo xanh: `Cập nhật giá phụ thu ăn sáng trẻ em thành công!`.
  * Đơn giá lưu thành 100,000đ. Nhấn F5 tải lại trang giá vẫn giữ nguyên 100,000đ.
  * *(Lỗi cũ: Nhấp ra ngoài hoặc bấm Lưu thì giá tự động nhảy ngược về 90,000đ).*

#### Thao tác 3.3: Đổi giai đoạn ở không bị tắt phụ phí và không bị về 0đ
1. Phòng đang có trẻ em với phụ phí 90,000đ/đêm.
2. Tích chọn phòng ➔ Dock phải bấm **Cập nhật** ➔ Đổi ngày đến và ngày đi dời sang khoảng thời gian khác (ví dụ dời 5 ngày) ➔ Bấm **Lưu**.
3. Bấm nút **Thông tin khách hàng** ➔ Bấm **Lưu** (đây là thao tác trước kia làm lỗi phát sinh).

* **Kết quả ĐÚNG**:
  * Bấm dấu `+` kiểm tra chi tiết phòng: Các dòng phụ phí ăn sáng trẻ em tự động dời sang các ngày mới.
  * Đơn giá vẫn giữ nguyên 90,000đ và cờ phụ phí vẫn bật đầy đủ.
  * *(Lỗi cũ: Sau khi dời ngày và lưu thông tin khách, phụ phí tự động bị tắt và giá nhảy về 0đ).*

#### Thao tác 3.4: Giảm số lượng trẻ em về 0
1. Tích chọn phòng ➔ Dock phải bấm **Cập nhật** ➔ Nhập *Trẻ em* = `0` ➔ Bấm **Lưu**.

* **Kết quả ĐÚNG**: Bảng chi tiết phòng tự động xóa sạch các dòng ăn sáng trẻ em, tổng tiền phòng trừ lại chính xác.

---

### SECTION 4: Hủy Booking Xong Giữ Nguyên Màn Hình Vừa Hủy

* **Màn hình**: **Tạo Đăng Ký**.
* **Mục tiêu**: Khi bấm Xoá (Hủy) một booking, hệ thống giữ nguyên tab booking đó thay vì nhảy sang tạo mới.
* **Ảnh tham chiếu**: [image4.png](file:///d:/PMS/docs/booking-4/image4.png).

#### Thao tác:
1. Mở một booking chưa check-in bất kỳ (ví dụ mã `GAL15`).
2. Trên thanh công cụ trên cùng ➔ Bấm nút **Xoá** ➔ Chọn lý do hủy (ví dụ: *Khách báo hủy*) ➔ Bấm **Có**.

* **Kết quả ĐÚNG**:
  * Báo thông báo xanh: `Đã hủy và xóa đăng ký thành công!`.
  * Màn hình **vẫn giữ nguyên trên tab của booking GAL15**; dòng phòng hiển thị trạng thái `Hủy`.
  * Trên thanh công cụ xuất hiện nút **Khôi phục** (thay thế cho nút Xoá).
  * Đường dẫn URL trên trình duyệt vẫn giữ nguyên mã booking: `bookingCode=GAL15`.
  * *(Lỗi cũ: Hủy xong màn hình bị văng sang một tab mới trắng tinh "Tạo đăng ký mới").*

---

### SECTION 5: Rút Ngắn Ngày Ở Không Hiển Thị Dịch Vụ Ngoài Giai Đoạn

* **Màn hình**: **Tạo Đăng Ký**.
* **Mục tiêu**: Khi rút ngắn ngày đi, các dịch vụ bổ sung của các ngày thừa phía sau phải tự động ẩn đi và không tính tiền, nhưng vẫn được lưu ngầm để khi gia hạn lại sẽ tự phục hồi.
* **Ảnh tham chiếu**: [image5.png](file:///d:/PMS/docs/booking-4/image5.png).

#### Thao tác 5.1: Rút ngắn ngày đi
1. Tạo booking 4 đêm (ngày đến 31/08, ngày đi 04/09: gồm 4 đêm 31, 1, 2, 3).
2. Tích phòng ➔ Dock phải bấm **Dịch vụ bổ sung** ➔ Thêm 1 dịch vụ bất kỳ (ví dụ: *Phụ thu trẻ em* hoặc *Đưa đón*) cho cả 4 đêm ➔ Bấm **Lưu**. (Bấm `+` thấy có đủ 4 ngày dịch vụ).
3. Tích phòng ➔ Dock phải bấm **Cập nhật** ➔ Đổi ngày đi từ `04/09` thành `02/09` (rút ngắn còn 2 đêm) ➔ Bấm **Lưu**.

* **Kết quả ĐÚNG**:
  * Bấm `+` xem chi tiết phòng: Chỉ còn thấy dịch vụ của đêm 31/08 và đêm 01/09.
  * Hoàn toàn **không còn** các dòng dịch vụ của ngày 02/09 và 03/09. Tổng tiền phòng giảm đúng theo 2 đêm.
  * *(Lỗi cũ: Đã rút ngày đi về ngày 02 nhưng bảng chi tiết vẫn hiện dịch vụ ngày 02 và 03).*

#### Thao tác 5.2: Thử gia hạn lại ngày đi
1. Tiếp tục trên booking đó ➔ Bấm **Cập nhật** ➔ Đổi ngày đi quay lại `04/09` ➔ Bấm **Lưu**.

* **Kết quả ĐÚNG**: Bấm `+` thấy các dòng dịch vụ của ngày 02 và 03 tự động xuất hiện lại với đúng đơn giá cũ, không cần phải nhập lại từ đầu.

---

### SECTION 6: Tên Dịch Vụ Ngày Quá Khứ Đồng Bộ Ngày Tương Lai

* **Màn hình**: **Tạo Đăng Ký**.
* **Mục tiêu**: Tên dịch vụ trong bảng chi tiết của các đêm quá khứ (đã post bill) và các đêm tương lai (chưa post) phải đồng nhất theo danh mục dịch vụ.
* **Ảnh tham chiếu**: [image6.png](file:///d:/PMS/docs/booking-4/image6.png).

#### Thao tác:
1. Mở một booking có phòng đang ở (đã check-in qua 1-2 đêm) có dịch vụ phòng nghỉ, Extra bed, hoặc Ăn sáng trẻ.
2. Bấm dấu **`+`** ở đầu dòng phòng để mở bảng chi tiết các ngày.
3. So sánh tên ở cột **Dịch vụ** giữa các dòng ngày quá khứ và các dòng ngày tương lai.

* **Kết quả ĐÚNG**:
  * Tất cả các dòng đều hiển thị tên dịch vụ chuẩn mực, đồng bộ:
    * Tiền phòng: `Dịch vụ phòng nghỉ` (hoặc tên theo danh mục dịch vụ).
    * Giường phụ: `Extrabed/Thêm Giường`.
    * Ăn sáng trẻ: `Breakfast Child/Ăn Sáng Trẻ Em`.
  * *(Lỗi cũ: Các đêm quá khứ bị gắn đuôi dài dòng "- Phòng 306" hoặc format khác biệt so với đêm tương lai).*

---

### SECTION 7: Hủy Phòng Không Báo Lỗi "No query results..."

* **Màn hình**: **Tạo Đăng Ký**.
* **Mục tiêu**: Hủy phòng hoạt động an toàn, không bị hiện lỗi crash backend khi bấm hủy liên tiếp.
* **Ảnh tham chiếu**: [image7.png](file:///d:/PMS/docs/booking-4/image7.png).

#### Thao tác:
1. Mở một booking có từ 2 phòng trở lên (chưa check-in).
Bấm **Có**.2. Tích chọn 1 phòng ➔ Dock phải bấm **Hủy phòng** ➔ Chọn lý do ➔ 
3. Thử thao tác bấm xác nhận nhanh, hoặc hủy lần lượt từng phòng.

* **Kết quả ĐÚNG**:
  * Luôn hiển thị thông báo xanh: `Đã hủy thành công phòng!`.
  * Phòng chuyển sang trạng thái đã hủy.
  * Tuyệt đối không xuất hiện lỗi đỏ: `Hủy phòng thất bại: No query results for model [App\Models\BookingRoom]...`.

---

### SECTION 8: Khôi Phục Booking Khớp Giai Đoạn Các Phòng

* **Màn hình**: **Tạo Đăng Ký**.
* **Mục tiêu**: Khi khôi phục booking có nhiều phòng ở lệch ngày nhau, ngày trên Header booking phải bao trùm toàn bộ các phòng.
* **Ảnh tham chiếu**: [image8.png](file:///d:/PMS/docs/booking-4/image8.png).

#### Thao tác:
1. Tạo 1 booking lấy phòng 1 ở từ `01/09` đến `01/09` (ở trong ngày/day use) ➔ Sau đó hủy phòng này đi.
2. Thêm phòng 2 ở từ `02/09` đến `03/09`.
3. Bấm **Xoá** booking để hủy toàn bộ booking.
4. Bấm **Khôi phục** ➔ Chọn khôi phục cả 2 phòng ➔ Bấm Khôi phục.

* **Kết quả ĐÚNG**:
  * Cả 2 phòng đều được khôi phục về trạng thái *Đăng ký*.
  * Trên Header booking, ngày đến/đi tự động tính bao trùm: **`01/09 ~ 03/09`**, **`Số đêm: 2`**.
  * *(Lỗi cũ: Header booking chỉ hiện 02/09 ~ 03/09 làm phòng ngày 01/09 bị nằm ngoài giai đoạn booking).*

---

### SECTION 9: Modal Chọn Phòng Hủy Cần Khôi Phục

* **Màn hình**: **Tạo Đăng Ký**.
* **Mục tiêu**: Kiểm tra giao diện và tính năng của popup "KHÔI PHỤC PHÒNG ĐÃ HỦY".
* **Ảnh tham chiếu**: [image9.png](file:///d:/PMS/docs/booking-4/image9.png).

#### Thao tác 9.1: Kiểm tra giao diện và sắp xếp cột
1. Mở một booking đã hủy có nhiều phòng (hoặc booking đang ở nhưng có phòng bị hủy).
2. Bấm nút **Khôi phục** trên thanh công cụ (hoặc nút *Khôi phục phòng hủy* ở dock bên phải).

* **Kết quả ĐÚNG**:
  * Hiển thị popup **KHÔI PHỤC PHÒNG ĐÃ HỦY** đồng bộ FE Design System (bo góc `rounded-xl`, phím `Esc` để đóng).
  * Danh sách phòng hiển thị các cột: Ô chọn, **Số phòng**, **Mã loại**, **Tên loại phòng**, **Ngày đến**, **Ngày đi**, **Ngày hủy**, **Người hủy**.
  * Mặc định danh sách được sắp xếp theo **Ngày hủy gần nhất** ở trên đầu.
  * Nhấp chuột vào tiêu đề bất kỳ cột nào (ví dụ Số phòng, Ngày đến) ➔ Có mũi tên sắp xếp tăng dần / giảm dần.
  * Có nút **Chọn tất cả** và dòng đếm số lượng: `Đã chọn X/Y phòng`.

#### Thao tác 9.2: Khôi phục một phần phòng
1. Trong popup, chỉ tích chọn 1 phòng trong số các phòng bị hủy ➔ Bấm **Khôi phục phòng đã chọn**.

* **Kết quả ĐÚNG**: Chỉ đúng phòng được chọn chuyển về trạng thái *Đăng ký*, các phòng còn lại vẫn giữ trạng thái *Hủy*.

#### Thao tác 9.3: Rào chắn an toàn khi có phòng lỗi
1. Nếu có 1 phòng bị trùng với booking khác đã lấy số phòng đó, hoặc phòng đang bị khóa OOO:
2. Tích chọn phòng lỗi đó cùng với 1 phòng hợp lệ ➔ Bấm **Khôi phục phòng đã chọn**.

* **Kết quả ĐÚNG**:
  * Hệ thống chặn lại và báo khung đỏ: `Có phòng không đủ điều kiện khôi phục, chưa khôi phục phòng nào`.
  * Hiển thị rõ lý do bên dưới từng phòng (ví dụ: `Phòng 305: đã được đặt bởi booking khác`).
  * Cả 2 phòng đều giữ nguyên trạng thái hủy để bảo vệ dữ liệu.

---

### SECTION 10: Khóa Phòng Ngày Mai Không Chặn Đặt Đêm Nay

* **Màn hình**: **Kế hoạch phòng** (Menu: `Đặt phòng` ➔ `Kế hoạch phòng`) & **Tạo Đăng Ký**.
* **Mục tiêu**: Phòng đêm nay còn trống thì vẫn cho phép đặt 1 đêm (đến hôm nay, đi ngày mai), kể cả khi ngày mai phòng có lịch khóa sửa chữa.
* **Ảnh tham chiếu**: [image10.png](file:///d:/PMS/docs/booking-4/image10.png).

#### Thao tác:
1. Vào menu **Khóa phòng**: Thực hiện khóa phòng `305` bắt đầu từ ngày mai (ví dụ từ ngày 03/09 đến 05/09).
2. Vào menu **Kế hoạch phòng**:
   * Nhìn dòng phòng `305`, bấm chuột vào ô của ngày **hôm nay** (ngày 02/09).
   * Điền nhanh thông tin booking (Tên, Nguồn khách...) với thời gian ở 1 đêm (từ 02/09 đến 03/09) ➔ Bấm **Lưu**.
3. *(Cách test khác trên màn Tạo Đăng Ký)*: Tạo booking 1 đêm từ 02/09 đến 03/09 ➔ Mở dropdown **Số phòng**.

* **Kết quả ĐÚNG**:
  * Tạo booking thành công bình thường! Trong dropdown Số phòng vẫn xuất hiện phòng `305` để chọn.
  * *(Lỗi cũ: Bị chặn và báo lỗi đỏ "Số phòng 305 đã được gán cho lượt đăng ký khác trong giai đoạn này").*
  * *Kiểm tra rào chặn đúng*: Nếu tạo booking 2 đêm (từ 02/09 đến 04/09, vướng vào ngày bị khóa) thì hệ thống vẫn chặn chuẩn xác.

---

### SECTION 11: Phụ Phí Ăn Sáng Trẻ Em Hiện Trước Check-In & Post Bill

* **Màn hình**: **Tạo Đăng Ký** & **Trả Phòng**.
* **Mục tiêu**: Phụ phí ăn sáng trẻ em hiển thị ngay khi tạo booking (chưa check-in), không bị mất khi thêm dịch vụ khác, và tự động tạo bill cùng tiền phòng.
* **Ảnh tham chiếu**: [image11.png](file:///d:/PMS/docs/booking-4/image11.png).

#### Thao tác 11.1: Hiển thị ngay khi phòng chưa check-in
1. Tạo một booking mới chưa check-in (ví dụ ở 3 đêm) ➔ Cập nhật số lượng trẻ em = `1` ➔ Bấm **Lưu**.
2. Bấm dấu **`+`** mở chi tiết dịch vụ của phòng.

* **Kết quả ĐÚNG**:
  * Thấy ngay các dòng **Breakfast Child/Ăn Sáng Trẻ Em** (90,000đ/đêm) cho từng đêm.
  * Cột **Tổng cộng** của phòng và tổng booking đã tự động cộng đủ tiền ăn sáng trẻ em.
  * *(Lỗi cũ: Trước khi check-in bảng chi tiết chỉ có tiền phòng, không hiện phụ phí ăn sáng trẻ và tổng tiền bị thiếu).*

#### Thao tác 11.2: Không bị mất dòng khi thêm dịch vụ bổ sung khác
1. Tiếp tục trên booking trên, tích chọn phòng ➔ Dock phải bấm **Dịch vụ bổ sung** ➔ Thêm 1 dịch vụ bất kỳ (ví dụ: Giặt là / Đưa đón) ➔ Bấm **Lưu**.
2. Bấm dấu `+` kiểm tra lại cả 2 phòng.

* **Kết quả ĐÚNG**: Các dòng phụ phí ăn sáng trẻ em vẫn còn nguyên vẹn, không bị biến mất.

#### Thao tác 11.3: Post trước tiền phòng tự động kéo theo bill ăn sáng trẻ em
1. Gán số phòng và bấm **Giao phòng** (Check-in) cho phòng có trẻ em.
2. Vào menu **Trả Phòng** ➔ Chọn phòng đó ➔ Bấm chức năng **Post trước tiền phòng**.

* **Kết quả ĐÚNG**:
  * Trong danh sách hóa đơn dịch vụ của phòng: Xuất hiện đồng thời bill tiền phòng **VÀ** bill **Breakfast Child/Ăn Sáng Trẻ Em** (90,000đ).
  * *(Lỗi cũ: Chỉ post tiền phòng, bỏ quên không post bill phụ phí ăn sáng trẻ em).*
