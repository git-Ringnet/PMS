# CHECKLIST NGHIỆM THU TÍNH NĂNG & BÁO CÁO PMS (ĐỐI CHIẾU SÁT THỰC TẾ LỖI KHÁCH HÀNG — CẬP NHẬT FILE 5)

> Căn cứ dữ liệu kiểm tra nghiệm thu ngày 12/10/2026 trên chi nhánh **HKT1** (Ngày nghiệp vụ 30/08/2026).
> Đã tinh gọn: Mỗi báo cáo gom về đúng 1 mục hoàn chỉnh, không phân mảnh tham số.
> Đã đối chiếu đầy đủ với sheet **THEO DÕI VẤN ĐỀ CẦN ĐIỀU CHỈNH** trong file `DANH SÁCH BẢNG TRONG HỆ THỐNG (5).xlsx`: Phân loại chính xác các mục còn lỗi/lưu ý sang màu Vàng kèm note lỗi cụ thể của khách hàng, tuyệt đối không tự ý chấm Pass hết; đã loại bỏ hoàn toàn 6 dòng ERP mở rộng giai đoạn sau.
> File Excel: [Checklist_nghiem_thu_PMS_5_Dot1.xlsx](Checklist_nghiem_thu_PMS_5_Dot1.xlsx) (và bản cập nhật [Checklist_nghiem_thu_PMS_5_Dot1_v6.xlsx](Checklist_nghiem_thu_PMS_5_Dot1_v6.xlsx))

## TỔNG QUAN PHÂN BỔ 891 MỤC CHỨC NĂNG (5 PHÂN HỆ / BỘ PHẬN CHÍNH THỨC)

| Phân hệ / Bộ phận | Chức năng nghiệp vụ | Báo cáo hoàn chỉnh | Tổng số mục | Đã hoàn thành (Xanh) | Hoàn thành có lỗi/lưu ý (Vàng) | Chưa đạt / Đang phát triển (Đỏ) |
|---|---|---|---|---|---|---|
| **Lễ tân (FO)** | 301 | 59 | **360** | 298 | 58 | 4 |
| **Buồng phòng (HK)** | 165 | 2 | **167** | 154 | 13 | 0 |
| **Đặt phòng / Sales** | 213 | 56 | **269** | 237 | 32 | 0 |
| **Cấu hình hệ thống (Hotel Config)** | 58 | 0 | **58** | 46 | 12 | 0 |
| **Hệ thống (Provista System)** | 37 | 0 | **37** | 35 | 2 | 0 |
| **Tổng cộng (5 Bộ phận chính)** | **774** | **117** | **891** | **770** | **117** | **4** |

> *Ghi chú Excel:* File Excel chính thức chuẩn hóa 5 sheet bộ phận chính, loại bỏ sạch các ghost rows, 6 dòng ERP mở rộng và 6 dòng Allotment/Channel Manager (chưa cần thiết trong Đợt 1), đồng thời chuyển toàn bộ nhãn phản hồi từ "Khách note" sang "PMS note", đồng bộ 100% chuẩn giao diện (Calibri 11pt Regular, chiều cao dòng 46pt, header Cyan #49ACC0 cao 32pt, thanh tiêu đề Navy #204F79).


---

# BUỒNG PHÒNG (HOUSEKEEPING) (14 Modules — 167 Mục)

## Module: Sơ đồ phòng (28 mục)

- **Mở sơ đồ phòng**
  - *Mô tả / Tham số:* Hiển thị danh sách phòng theo tầng, số phòng, loại phòng và tình trạng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Mở sơ đồ phòng" hoạt động ổn định trên hệ thống.`
- **Đổi ngày xem**
  - *Mô tả / Tham số:* Số liệu phòng, khách và booking phản ánh ngày nghiệp vụ được chọn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Đổi ngày xem" hoạt động ổn định trên hệ thống.`
- **Đã đến**
  - *Mô tả / Tham số:* Mở danh sách phòng đến/đã nhận phòng theo ngày.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Đã đến" hoạt động ổn định trên hệ thống.`
- **Đã đi**
  - *Mô tả / Tham số:* Mở danh sách phòng đi/đã trả phòng theo ngày.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Đã đi" hoạt động ổn định trên hệ thống.`
- **Đang ở**
  - *Mô tả / Tham số:* Mở danh sách phòng khách đang lưu trú.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Đang ở" hoạt động ổn định trên hệ thống.`
- **Khóa OOO**
  - *Mô tả / Tham số:* Hiển thị đúng phòng bị khóa OOO và số lượng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Khóa OOO" hoạt động ổn định trên hệ thống.`
- **Khóa OOS**
  - *Mô tả / Tham số:* Hiển thị đúng phòng bị khóa OOS và số lượng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Khóa OOS" hoạt động ổn định trên hệ thống.`
- **Công suất**
  - *Mô tả / Tham số:* Mở thống kê và đối chiếu tỷ lệ chiếm dụng với số phòng có thể bán.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Công suất" hoạt động ổn định trên hệ thống.`
- **Xem danh sách**
  - *Mô tả / Tham số:* Chuyển từ sơ đồ sang bảng có khách, booking, ngày đến/đi.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Xem danh sách" hoạt động ổn định trên hệ thống.`
- **Xem sơ đồ lưới**
  - *Mô tả / Tham số:* Chuyển về thẻ phòng theo tầng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Xem sơ đồ lưới" hoạt động ổn định trên hệ thống.`
- **Thu nhỏ/phóng to/Auto Fit**
  - *Mô tả / Tham số:* Thay đổi tỷ lệ xem, không mất phòng hoặc che chức năng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Thu nhỏ/phóng to/Auto Fit" hoạt động ổn định trên hệ thống.`
- **Bật/tắt bộ lọc**
  - *Mô tả / Tham số:* Mở, đóng panel bộ lọc.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Bật/tắt bộ lọc" hoạt động ổn định trên hệ thống.`
- **Lọc theo tầng**
  - *Mô tả / Tham số:* Chỉ hiển thị phòng thuộc tầng đã chọn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lọc theo tầng" hoạt động ổn định trên hệ thống.`
- **Lọc theo loại phòng**
  - *Mô tả / Tham số:* Chỉ hiển thị loại phòng đã chọn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lọc theo loại phòng" hoạt động ổn định trên hệ thống.`
- **Lọc mã đăng ký/tên khách**
  - *Mô tả / Tham số:* Trả về phòng phù hợp booking hoặc tên khách.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lọc mã đăng ký/tên khách" hoạt động ổn định trên hệ thống.`
- **Lọc tình trạng đăng ký**
  - *Mô tả / Tham số:* Áp dụng tình trạng đăng ký được cấu hình.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lọc tình trạng đăng ký" hoạt động ổn định trên hệ thống.`
- **Lọc lưu trú**
  - *Mô tả / Tham số:* Phân biệt trống, đang ở, đến, đi, OOO.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lọc lưu trú" hoạt động ổn định trên hệ thống.`
- **Lọc tình trạng buồng phòng**
  - *Mô tả / Tham số:* Phân biệt sạch, bẩn, chờ kiểm tra và sẵn sàng theo quyền.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lọc tình trạng buồng phòng" hoạt động ổn định trên hệ thống.`
- **Xóa bộ lọc**
  - *Mô tả / Tham số:* Khôi phục toàn bộ danh sách và số lượng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Xóa bộ lọc" hoạt động ổn định trên hệ thống.`
- **Lọc tại tiêu đề cột**
  - *Mô tả / Tham số:* Bộ lọc số phòng, tên khách, mã/tên đăng ký, ngày đến/đi hoạt động độc lập.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lọc tại tiêu đề cột" hoạt động ổn định trên hệ thống.`
- **Chọn nhiều/chọn tất cả phòng**
  - *Mô tả / Tham số:* Chỉ chọn phòng đang hiển thị; bỏ chọn không tác động dữ liệu.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Chọn nhiều/chọn tất cả phòng" hoạt động ổn định trên hệ thống.`
- **Cài đặt hiển thị**
  - *Mô tả / Tham số:* Mở hộp kích thước icon/chữ/phòng, hướng tầng, vị trí chính xác.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Cài đặt hiển thị" hoạt động ổn định trên hệ thống.`
- **Lưu/khôi phục cài đặt**
  - *Mô tả / Tham số:* Thiết lập được giữ theo tài khoản; Mặc định khôi phục đúng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lưu/khôi phục cài đặt" hoạt động ổn định trên hệ thống.`
- **Chi tiết phòng**
  - *Mô tả / Tham số:* Mở thông tin phòng/khách/booking, dịch vụ và lịch sử tương ứng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Chi tiết phòng" hoạt động ổn định trên hệ thống.`
- **Menu chuột phải**
  - *Mô tả / Tham số:* Hiển thị tác vụ phù hợp tình trạng phòng và quyền của bộ phận.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Menu chuột phải" hoạt động ổn định trên hệ thống.`
- **Biểu tượng nghiệp vụ**
  - *Mô tả / Tham số:* Thể hiện extra bed, khách VIP, yêu cầu đặc biệt, sinh nhật, honeymoon, DND.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Biểu tượng nghiệp vụ" hoạt động ổn định trên hệ thống.`
- **Phòng thông nhau**
  - *Mô tả / Tham số:* Hiển thị đúng các cặp phòng connecting và cửa kết nối.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Phòng thông nhau" hoạt động ổn định trên hệ thống.`
- **Cập nhật thời gian thực**
  - *Mô tả / Tham số:* Dữ liệu thay đổi ở module khác cập nhật qua websocket/refresh.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Cập nhật thời gian thực" hoạt động ổn định trên hệ thống.`

## Module: Kế hoạch phòng (19 mục)

- **Mở kế hoạch phòng**
  - *Mô tả / Tham số:* Hiển thị lịch phòng theo khoảng ngày và hàng số phòng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Mở kế hoạch phòng" hoạt động ổn định trên hệ thống.`
- **Chọn khoảng ngày**
  - *Mô tả / Tham số:* Lịch hiển thị chính xác bắt đầu/kết thúc và ngày nghiệp vụ.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Chọn khoảng ngày" hoạt động ổn định trên hệ thống.`
- **Đổi View**
  - *Mô tả / Tham số:* Đổi cách hiển thị lịch mà không làm mất booking.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Đổi View" hoạt động ổn định trên hệ thống.`
- **Sắp xếp**
  - *Mô tả / Tham số:* Sắp xếp số phòng/loại phòng theo tùy chọn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Sắp xếp" hoạt động ổn định trên hệ thống.`
- **Tìm kiếm**
  - *Mô tả / Tham số:* Lọc khách hàng, mã đăng ký và phòng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Tìm kiếm" hoạt động ổn định trên hệ thống.`
- **Bộ lọc**
  - *Mô tả / Tham số:* Lọc loại/dạng phòng, tình trạng; Xóa lọc khôi phục.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Bộ lọc" hoạt động ổn định trên hệ thống.`
- **Mở booking từ lịch**
  - *Mô tả / Tham số:* Mở đúng booking và thông tin phòng trên thanh lịch.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Mở booking từ lịch" hoạt động ổn định trên hệ thống.`
- **Đặt phòng nhanh**
  - *Mô tả / Tham số:* Chọn phòng/giai đoạn rồi nhập tên BK, market, source, tình trạng và lưu.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Đặt phòng nhanh" hoạt động ổn định trên hệ thống.`
- **Đặt phòng nhanh - dữ liệu thiếu**
  - *Mô tả / Tham số:* Chặn lưu khi thiếu tên đăng ký, thị trường, nguồn khách hoặc tình trạng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Đặt phòng nhanh - dữ liệu thiếu" hoạt động ổn định trên hệ thống.`
- **Giao phòng**
  - *Mô tả / Tham số:* Gán đúng số phòng cho booking và cập nhật ma trận AV.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Giao phòng" hoạt động ổn định trên hệ thống.`
- **Tách phòng**
  - *Mô tả / Tham số:* Chia giai đoạn theo ngày tách, giữ đúng tổng đêm và dịch vụ.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Tách phòng" hoạt động ổn định trên hệ thống.`
- **Chuyển phòng trên lịch**
  - *Mô tả / Tham số:* Chỉ chuyển khi phòng nhận phù hợp giai đoạn và điều kiện phòng.
  - *Đánh giá cá nhân:* `Hoàn thành nhưng còn lỗi (PMS note Dòng 305, 306, 530): Cần kiểm tra chuyển phòng sau khi ở qua đêm / ngày checkout; cấu hình AllowOverRoomTypeRoomKind=1 khi Suite=0 chưa cảnh báo đúng.`
- **Khóa/mở chuyển phòng**
  - *Mô tả / Tham số:* Tôn trọng khóa di chuyển và quyền thao tác.
  - *Đánh giá cá nhân:* `Hoàn thành nhưng còn lỗi (PMS note Dòng 305, 306, 530): Cần kiểm tra chuyển phòng sau khi ở qua đêm / ngày checkout; cấu hình AllowOverRoomTypeRoomKind=1 khi Suite=0 chưa cảnh báo đúng.`
- **Gỡ số phòng**
  - *Mô tả / Tham số:* Bỏ gán số phòng, giữ dòng đăng ký và số lượng allocation.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Gỡ số phòng" hoạt động ổn định trên hệ thống.`
- **Hủy phòng**
  - *Mô tả / Tham số:* Nhập lý do và cập nhật trạng thái, AV, lịch phòng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Hủy phòng" hoạt động ổn định trên hệ thống.`
- **Khóa OOO/OOS**
  - *Mô tả / Tham số:* Chọn giai đoạn, lý do; thể hiện vùng khóa chính xác.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Khóa OOO/OOS" hoạt động ổn định trên hệ thống.`
- **Mở khóa phòng**
  - *Mô tả / Tham số:* Cập nhật vùng khóa theo ngày/giờ mở khóa nghiệp vụ.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Mở khóa phòng" hoạt động ổn định trên hệ thống.`
- **Danh sách chờ**
  - *Mô tả / Tham số:* Hiển thị và thao tác booking chờ theo tình trạng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Danh sách chờ" hoạt động ổn định trên hệ thống.`
- **Biên ngày đến/đi**
  - *Mô tả / Tham số:* Phòng checkout không chiếm đêm tiếp theo; không chồng lấn booking nhận cùng ngày.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Biên ngày đến/đi" hoạt động ổn định trên hệ thống.`

## Module: Khóa phòng OOO/OOS (11 mục)

- **Mở danh sách khóa**
  - *Mô tả / Tham số:* Tải phòng, loại phòng, tình trạng khóa và lịch sử.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Mở danh sách khóa" hoạt động ổn định trên hệ thống.`
- **Lọc khóa**
  - *Mô tả / Tham số:* Tìm số phòng, lọc loại phòng và Sẵn sàng/OOO/OOS.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lọc khóa" hoạt động ổn định trên hệ thống.`
- **Khóa OOO**
  - *Mô tả / Tham số:* Chọn một/nhiều phòng, bắt đầu/kết thúc, lý do; lưu khóa đúng giai đoạn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Khóa OOO" hoạt động ổn định trên hệ thống.`
- **Khóa OOS**
  - *Mô tả / Tham số:* Ghi nhận OOS đúng quy tắc bán phòng và công suất.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Khóa OOS" hoạt động ổn định trên hệ thống.`
- **Sửa khóa**
  - *Mô tả / Tham số:* Sửa giai đoạn/lý do từng phòng hoặc hàng loạt; không ghi đè phòng khác.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Sửa khóa" hoạt động ổn định trên hệ thống.`
- **Mở khóa**
  - *Mô tả / Tham số:* Ngày/giờ kết thúc theo ngày hệ thống và giờ thực hiện.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Mở khóa" hoạt động ổn định trên hệ thống.`
- **Lịch sử khóa**
  - *Mô tả / Tham số:* Xem các lần khóa/mở, người thao tác, lý do và giai đoạn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lịch sử khóa" hoạt động ổn định trên hệ thống.`
- **Availability khi mở sớm**
  - *Mô tả / Tham số:* Giữ khóa các ngày quá khứ; ngày mở áp giờ cutoff của cấu hình.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Availability khi mở sớm" hoạt động ổn định trên hệ thống.`
- **Kiểm tra booking chưa gán phòng**
  - *Mô tả / Tham số:* Không khóa làm booking mất khả năng assign khi cấu hình không cho phép.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Kiểm tra booking chưa gán phòng" hoạt động ổn định trên hệ thống.`
- **Xung đột phòng có khách**
  - *Mô tả / Tham số:* Chặn khóa trái phép trên phòng đang lưu trú hoặc giai đoạn xung đột.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Xung đột phòng có khách" hoạt động ổn định trên hệ thống.`
- **Sửa/Hủy/Lưu hàng loạt**
  - *Mô tả / Tham số:* Chỉ lưu dòng thay đổi; Hủy khôi phục dữ liệu và xử lý lỗi rõ ràng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Sửa/Hủy/Lưu hàng loạt" hoạt động ổn định trên hệ thống.`

## Module: Lịch sử thao tác (6 mục)

- **Mở lịch sử thao tác**
  - *Mô tả / Tham số:* Có bộ lọc ngày, đăng ký, phòng, hành động, người/bộ phận.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Mở lịch sử thao tác" hoạt động ổn định trên hệ thống.`
- **Lọc thời gian**
  - *Mô tả / Tham số:* Tất cả/Hôm nay/Hôm qua/7 ngày/Tháng này và khoảng tùy chọn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lọc thời gian" hoạt động ổn định trên hệ thống.`
- **Lọc hành động**
  - *Mô tả / Tham số:* Tạo/sửa/check-in/check-out/hủy/noshow/khóa/mở/thanh toán/cọc/dịch vụ/sang ngày.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lọc hành động" hoạt động ổn định trên hệ thống.`
- **Lọc mã booking/phòng/người dùng**
  - *Mô tả / Tham số:* Kết quả đúng điều kiện và chi nhánh.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lọc mã booking/phòng/người dùng" hoạt động ổn định trên hệ thống.`
- **Tìm nội dung/Hủy lọc**
  - *Mô tả / Tham số:* Tra cứu từ khóa và khôi phục kết quả.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Tìm nội dung/Hủy lọc" hoạt động ổn định trên hệ thống.`
- **Xuất Excel lịch sử**
  - *Mô tả / Tham số:* Xuất đúng dữ liệu lọc, người, thời gian và thông tin thay đổi.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Xuất Excel lịch sử" hoạt động ổn định trên hệ thống.`

## Module: Dịch vụ minibar/giặt ủi/đền bù (20 mục)

- **Mở form dịch vụ**
  - *Mô tả / Tham số:* Tải danh mục outlet, nhóm sản phẩm, khách/phòng và ngày hệ thống.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Mở form dịch vụ" hoạt động ổn định trên hệ thống.`
- **Menu Minibar**
  - *Mô tả / Tham số:* Mở đúng danh mục minibar và không lẫn outlet khác.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Menu Minibar" hoạt động ổn định trên hệ thống.`
- **Menu Giặt ủi**
  - *Mô tả / Tham số:* Mở đúng danh mục giặt ủi và không lẫn minibar.
  - *Đánh giá cá nhân:* `Hoàn thành nhưng còn lỗi (PMS-03): Mở menu Giặt ủi/Hàng đền bù tự động nhảy sang tab con Minibar do thiếu prop outlet mặc định.`
- **Menu Hàng đền bù**
  - *Mô tả / Tham số:* Mở đúng danh mục đền bù và không lẫn outlet khác.
  - *Đánh giá cá nhân:* `Hoàn thành nhưng còn lỗi (PMS-03): Mở menu Giặt ủi/Hàng đền bù tự động nhảy sang tab con Minibar do thiếu prop outlet mặc định.`
- **Amenity**
  - *Mô tả / Tham số:* Tab outlet cấu hình hiển thị đủ sản phẩm được phép.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Amenity" hoạt động ổn định trên hệ thống.`
- **Chọn phòng/khách**
  - *Mô tả / Tham số:* Danh sách đúng khách Inhouse; mở từ sơ đồ có ngữ cảnh phòng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Chọn phòng/khách" hoạt động ổn định trên hệ thống.`
- **Ngày post dịch vụ**
  - *Mô tả / Tham số:* Theo ngày hệ thống/phạm vi ở, không post ngoài giai đoạn hoặc quyền ngày cũ.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Ngày post dịch vụ" hoạt động ổn định trên hệ thống.`
- **Lọc sản phẩm**
  - *Mô tả / Tham số:* Tìm theo tên; xóa tìm kiếm khôi phục danh sách.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lọc sản phẩm" hoạt động ổn định trên hệ thống.`
- **Thêm sản phẩm vào giỏ**
  - *Mô tả / Tham số:* Sản phẩm/đơn giá/nhóm đúng; thêm lại tăng số lượng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Thêm sản phẩm vào giỏ" hoạt động ổn định trên hệ thống.`
- **Thay đổi số lượng**
  - *Mô tả / Tham số:* Nhập số lượng hợp lệ và tính lại thành tiền.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Thay đổi số lượng" hoạt động ổn định trên hệ thống.`
- **Giảm giá từng dòng**
  - *Mô tả / Tham số:* Tính % giảm và số tiền giảm đúng; giới hạn giá trị.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Giảm giá từng dòng" hoạt động ổn định trên hệ thống.`
- **Phụ thu từng dòng**
  - *Mô tả / Tham số:* Đổi chế độ phụ thu, tính đúng tổng và không lưu nhầm giảm giá.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Phụ thu từng dòng" hoạt động ổn định trên hệ thống.`
- **Giảm giá/phụ thu toàn bill**
  - *Mô tả / Tham số:* Kết hợp tỷ lệ chung và từng dòng theo giới hạn cấu hình.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Giảm giá/phụ thu toàn bill" hoạt động ổn định trên hệ thống.`
- **Miễn phí**
  - *Mô tả / Tham số:* Thành tiền thu về 0; bill có freeitem và giá trị gốc phục vụ báo cáo.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Miễn phí" hoạt động ổn định trên hệ thống.`
- **Ghi chú bill**
  - *Mô tả / Tham số:* Lưu ghi chú vào bill đúng phòng/khách.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Ghi chú bill" hoạt động ổn định trên hệ thống.`
- **Xóa dòng giỏ**
  - *Mô tả / Tham số:* Bỏ đúng sản phẩm chưa post, tổng tiền cập nhật.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Xóa dòng giỏ" hoạt động ổn định trên hệ thống.`
- **Undo**
  - *Mô tả / Tham số:* Hoàn tác thao tác trước hoặc ghi rõ chức năng xóa toàn bộ giỏ; không gây mất dữ liệu ngoài ý muốn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Undo" hoạt động ổn định trên hệ thống.`
- **Gửi về phòng**
  - *Mô tả / Tham số:* Tạo bill/chi tiết, ownership đúng khách/phòng, cập nhật checkout.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Gửi về phòng" hoạt động ổn định trên hệ thống.`
- **Thuế/phí phục vụ**
  - *Mô tả / Tham số:* Đơn giá gộp, thuế, phí và thành tiền lưu nhất quán.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Thuế/phí phục vụ" hoạt động ổn định trên hệ thống.`
- **No Post và quyền post**
  - *Mô tả / Tham số:* Chặn phòng No Post và tài khoản không có quyền bill.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "No Post và quyền post" hoạt động ổn định trên hệ thống.`

## Module: Phân công làm phòng (13 mục)

- **Mở phân công phòng**
  - *Mô tả / Tham số:* Tải ngày, ca, phòng và nhóm nhân viên.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Mở phân công phòng" hoạt động ổn định trên hệ thống.`
- **Lọc ngày/ca**
  - *Mô tả / Tham số:* Danh sách phân công đúng ca/ngày nghiệp vụ.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lọc ngày/ca" hoạt động ổn định trên hệ thống.`
- **Lọc tầng/số phòng/loại phòng**
  - *Mô tả / Tham số:* Giữ lựa chọn và số lượng phòng đúng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lọc tầng/số phòng/loại phòng" hoạt động ổn định trên hệ thống.`
- **Lọc tình trạng phòng/HK**
  - *Mô tả / Tham số:* Chọn phòng cần dọn, khách đến/đi/ở theo mã trạng thái.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lọc tình trạng phòng/HK" hoạt động ổn định trên hệ thống.`
- **Chọn phòng**
  - *Mô tả / Tham số:* Chọn từng phòng/theo tầng/tất cả và bỏ chọn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Chọn phòng" hoạt động ổn định trên hệ thống.`
- **Chọn nhân viên/Phân công**
  - *Mô tả / Tham số:* Tạo nhóm và gán đúng phòng/nhân viên theo ngày/ca.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Chọn nhân viên/Phân công" hoạt động ổn định trên hệ thống.`
- **Kéo thả giữa nhóm**
  - *Mô tả / Tham số:* Chuyển phân công đúng phòng, không trùng nhóm.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Kéo thả giữa nhóm" hoạt động ổn định trên hệ thống.`
- **Sửa nhóm**
  - *Mô tả / Tham số:* Thêm/bỏ nhân viên trong nhóm đã phân công.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Sửa nhóm" hoạt động ổn định trên hệ thống.`
- **Bỏ phòng/xóa nhóm**
  - *Mô tả / Tham số:* Có xác nhận và không tác động ngoài ngày/ca đang xem.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Bỏ phòng/xóa nhóm" hoạt động ổn định trên hệ thống.`
- **Quản lý nhân viên HK**
  - *Mô tả / Tham số:* Thêm, ẩn/hiện, xóa theo quyền và ràng buộc phân công.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Quản lý nhân viên HK" hoạt động ổn định trên hệ thống.`
- **In lịch theo nhóm NV**
  - *Mô tả / Tham số:* In phòng, nhân viên, ca, ngày và ký hiệu trạng thái.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "In lịch theo nhóm NV" hoạt động ổn định trên hệ thống.`
- **In lịch theo phòng Supervisor**
  - *Mô tả / Tham số:* In đúng phòng và nhóm phụ trách theo mẫu.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "In lịch theo phòng Supervisor" hoạt động ổn định trên hệ thống.`
- **Chọn nhóm cần in**
  - *Mô tả / Tham số:* Chỉ in nhóm được chọn, không in lại nhóm đã bỏ.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Chọn nhóm cần in" hoạt động ổn định trên hệ thống.`

## Module: Tồn kho và kiểm kê (19 mục)

- **Mở tồn kho**
  - *Mô tả / Tham số:* Tải kho, tháng, sản phẩm và bảng nhập/xuất/tồn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Mở tồn kho" hoạt động ổn định trên hệ thống.`
- **Thêm kho**
  - *Mô tả / Tham số:* Tên kho và liên kết outlet đúng, không trùng ngoài quy tắc.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Thêm kho" hoạt động ổn định trên hệ thống.`
- **Sửa/xóa kho**
  - *Mô tả / Tham số:* Theo quyền và ràng buộc phát sinh; có xác nhận khi cần.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Sửa/xóa kho" hoạt động ổn định trên hệ thống.`
- **Chọn tháng/kho/outlet/nhóm**
  - *Mô tả / Tham số:* Bảng tồn kho đổi đúng phạm vi chọn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Chọn tháng/kho/outlet/nhóm" hoạt động ổn định trên hệ thống.`
- **Lọc có/không phát sinh**
  - *Mô tả / Tham số:* Phân loại đúng sản phẩm theo nhật ký tháng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lọc có/không phát sinh" hoạt động ổn định trên hệ thống.`
- **Sắp xếp tồn kho**
  - *Mô tả / Tham số:* Tên A-Z/Z-A, tồn cuối tăng/giảm hoạt động.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Sắp xếp tồn kho" hoạt động ổn định trên hệ thống.`
- **Nhập đầu kỳ/phát sinh**
  - *Mô tả / Tham số:* Lưu số lượng ngày theo quyền; giữ công thức tồn cuối.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Nhập đầu kỳ/phát sinh" hoạt động ổn định trên hệ thống.`
- **Lấy Bill ngày**
  - *Mô tả / Tham số:* Tổng hợp bill phù hợp ngày hệ thống và kho/outlet.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lấy Bill ngày" hoạt động ổn định trên hệ thống.`
- **Lấy Bill tháng**
  - *Mô tả / Tham số:* Tổng hợp chính xác toàn tháng; không cộng lặp khi lấy lại.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lấy Bill tháng" hoạt động ổn định trên hệ thống.`
- **Chuyển kho**
  - *Mô tả / Tham số:* Chọn kho nhận, sản phẩm, số lượng; trừ nguồn/cộng đích cùng giao dịch.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Chuyển kho" hoạt động ổn định trên hệ thống.`
- **Thống kê tồn**
  - *Mô tả / Tham số:* Tổng đúng bộ lọc, nhóm và phạm vi tháng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Thống kê tồn" hoạt động ổn định trên hệ thống.`
- **Xuất Excel tồn kho**
  - *Mô tả / Tham số:* File giữ nhập/xuất/tồn và ngày/tháng đúng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Xuất Excel tồn kho" hoạt động ổn định trên hệ thống.`
- **Kiểm kê định kỳ**
  - *Mô tả / Tham số:* Chọn/tạo phiếu kiểm kê theo kỳ/kho và tải danh sách.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Kiểm kê định kỳ" hoạt động ổn định trên hệ thống.`
- **Thêm sản phẩm kiểm kê**
  - *Mô tả / Tham số:* Chỉ thêm sản phẩm phù hợp, không trùng dòng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Thêm sản phẩm kiểm kê" hoạt động ổn định trên hệ thống.`
- **Sửa số kiểm kê**
  - *Mô tả / Tham số:* Tăng/giảm/gõ số lượng hợp lệ và lưu chênh lệch.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Sửa số kiểm kê" hoạt động ổn định trên hệ thống.`
- **Đồng bộ kỳ trước**
  - *Mô tả / Tham số:* Lấy đúng số cuối kỳ trước làm đầu kỳ, không ghi sai kỳ.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Đồng bộ kỳ trước" hoạt động ổn định trên hệ thống.`
- **Xóa phiếu kiểm kê**
  - *Mô tả / Tham số:* Có quyền/xác nhận và giữ nhất quán tồn kho.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Xóa phiếu kiểm kê" hoạt động ổn định trên hệ thống.`
- **Xuất Excel kiểm kê**
  - *Mô tả / Tham số:* Đúng số hệ thống, thực đếm và chênh lệch.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Xuất Excel kiểm kê" hoạt động ổn định trên hệ thống.`
- **Nhật ký kho**
  - *Mô tả / Tham số:* Đối chiếu phát sinh theo sản phẩm/ngày; xem bill liên quan.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Nhật ký kho" hoạt động ổn định trên hệ thống.`

## Module: Tạo menu và sản phẩm (14 mục)

- **Mở tạo menu**
  - *Mô tả / Tham số:* Tải outlet/nhóm và sản phẩm thực tế.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Mở tạo menu" hoạt động ổn định trên hệ thống.`
- **Chọn outlet/nhóm**
  - *Mô tả / Tham số:* Hiển thị MB, LA, BR, AM và nhóm tương ứng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Chọn outlet/nhóm" hoạt động ổn định trên hệ thống.`
- **Tạo/sửa nhóm**
  - *Mô tả / Tham số:* Mã/tên/loại/outlet được lưu và dùng trên form dịch vụ.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Tạo/sửa nhóm" hoạt động ổn định trên hệ thống.`
- **Xóa nhóm**
  - *Mô tả / Tham số:* Kiểm tra sản phẩm liên quan và quyền.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Xóa nhóm" hoạt động ổn định trên hệ thống.`
- **Thêm/sửa sản phẩm**
  - *Mô tả / Tham số:* Mã, tên, nhóm, giá, đơn vị, tax, phí phục vụ, ảnh và thuộc tính được lưu.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Thêm/sửa sản phẩm" hoạt động ổn định trên hệ thống.`
- **Theo dõi tồn kho**
  - *Mô tả / Tham số:* Thiết lập tracking đúng và xuất hiện trong kiểm kê.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Theo dõi tồn kho" hoạt động ổn định trên hệ thống.`
- **Active/Inactive/open key**
  - *Mô tả / Tham số:* Sản phẩm không hoạt động không được chọn post bill.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Active/Inactive/open key" hoạt động ổn định trên hệ thống.`
- **Bật/tắt hàng loạt**
  - *Mô tả / Tham số:* Chỉ đổi trạng thái sản phẩm được chọn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Bật/tắt hàng loạt" hoạt động ổn định trên hệ thống.`
- **Xóa sản phẩm**
  - *Mô tả / Tham số:* Chặn sản phẩm có nghiệp vụ ràng buộc hoặc xử lý theo quy tắc.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Xóa sản phẩm" hoạt động ổn định trên hệ thống.`
- **Tìm/lọc sản phẩm**
  - *Mô tả / Tham số:* Tên, trạng thái, khoảng giá, tracking; đúng tập kết quả.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Tìm/lọc sản phẩm" hoạt động ổn định trên hệ thống.`
- **Sắp xếp sản phẩm**
  - *Mô tả / Tham số:* Theo tên hoặc giá tăng/giảm.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Sắp xếp sản phẩm" hoạt động ổn định trên hệ thống.`
- **Nhập Excel sản phẩm**
  - *Mô tả / Tham số:* Kiểm tra cấu trúc, mã trùng, nhóm và số tiền; báo lỗi rõ.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Nhập Excel sản phẩm" hoạt động ổn định trên hệ thống.`
- **Xuất Excel sản phẩm**
  - *Mô tả / Tham số:* Danh mục, giá và thuộc tính khớp dữ liệu.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Xuất Excel sản phẩm" hoạt động ổn định trên hệ thống.`
- **In menu**
  - *Mô tả / Tham số:* Bố cục và giá/sản phẩm khớp lựa chọn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "In menu" hoạt động ổn định trên hệ thống.`

## Module: Đồ thất lạc Lost and Found (8 mục)

- **Mở đồ thất lạc**
  - *Mô tả / Tham số:* Tải danh sách và bộ lọc Lost/Found/thời gian.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Mở đồ thất lạc" hoạt động ổn định trên hệ thống.`
- **Thêm phiếu Lost**
  - *Mô tả / Tham số:* Nhập đồ, phòng, khách, nơi/ngày phát hiện, người liên quan và ghi chú.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Thêm phiếu Lost" hoạt động ổn định trên hệ thống.`
- **Thêm phiếu Found**
  - *Mô tả / Tham số:* Ghi nhận đồ tìm thấy, thông tin liên hệ và nơi lưu giữ.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Thêm phiếu Found" hoạt động ổn định trên hệ thống.`
- **Sửa phiếu**
  - *Mô tả / Tham số:* Thay đổi thông tin đúng bản ghi, không lẫn phiếu khác.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Sửa phiếu" hoạt động ổn định trên hệ thống.`
- **Ảnh đồ thất lạc**
  - *Mô tả / Tham số:* Thêm/xem/xóa ảnh đúng phiếu.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Ảnh đồ thất lạc" hoạt động ổn định trên hệ thống.`
- **Tình trạng xử lý**
  - *Mô tả / Tham số:* Ghi nhận trạng thái, người nhận/trả đồ và nội dung bàn giao theo trường trên form.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Tình trạng xử lý" hoạt động ổn định trên hệ thống.`
- **Lọc/đặt lại/phân trang**
  - *Mô tả / Tham số:* Danh sách đúng loại Lost/Found và khoảng ngày.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lọc/đặt lại/phân trang" hoạt động ổn định trên hệ thống.`
- **Xóa phiếu**
  - *Mô tả / Tham số:* Theo quyền, xác nhận và ràng buộc dữ liệu.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Xóa phiếu" hoạt động ổn định trên hệ thống.`

## Module: Tìm kiếm hóa đơn buồng phòng (8 mục)

- **Mở tìm kiếm hóa đơn**
  - *Mô tả / Tham số:* Hiển thị khoảng ngày, mã ĐK, phòng, bộ phận, người, outlet và trạng thái.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Mở tìm kiếm hóa đơn" hoạt động ổn định trên hệ thống.`
- **Xem hóa đơn**
  - *Mô tả / Tham số:* Tải danh sách theo điều kiện; trường hợp rỗng có thông báo rõ.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Xem hóa đơn" hoạt động ổn định trên hệ thống.`
- **Lọc đã/chưa thanh toán/đã xóa**
  - *Mô tả / Tham số:* Đúng trạng thái bill.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lọc đã/chưa thanh toán/đã xóa" hoạt động ổn định trên hệ thống.`
- **Lọc mã ĐK/phòng/bộ phận/người/outlet**
  - *Mô tả / Tham số:* Kết quả đáp ứng đồng thời điều kiện đã chọn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lọc mã ĐK/phòng/bộ phận/người/outlet" hoạt động ổn định trên hệ thống.`
- **Xem chi tiết hóa đơn**
  - *Mô tả / Tham số:* Hiển thị đúng sản phẩm, giá, số lượng, giảm/tăng, thuế/phí.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Xem chi tiết hóa đơn" hoạt động ổn định trên hệ thống.`
- **Thêm bill từ tìm kiếm**
  - *Mô tả / Tham số:* Mở form và post đúng ngữ cảnh theo quyền.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Thêm bill từ tìm kiếm" hoạt động ổn định trên hệ thống.`
- **In hóa đơn**
  - *Mô tả / Tham số:* Nội dung bill và giá/miễn phí khớp dữ liệu.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "In hóa đơn" hoạt động ổn định trên hệ thống.`
- **Hủy hóa đơn**
  - *Mô tả / Tham số:* Nhập lý do, kiểm tra quyền/trạng thái; lưu bill hủy và audit.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Hủy hóa đơn" hoạt động ổn định trên hệ thống.`

## Module: Tình trạng buồng phòng (9 mục)

- **Ready**
  - *Mô tả / Tham số:* Đổi tình trạng phòng sẵn sàng theo quyền và trạng thái lưu trú.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Ready" hoạt động ổn định trên hệ thống.`
- **Dirty**
  - *Mô tả / Tham số:* Đổi phòng bẩn, giữ đúng phân biệt phòng trống/phòng có khách.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Dirty" hoạt động ổn định trên hệ thống.`
- **Vacant Clean**
  - *Mô tả / Tham số:* Đổi phòng sạch chờ kiểm tra, không mặc định coi là Ready.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Vacant Clean" hoạt động ổn định trên hệ thống.`
- **Housekeeping**
  - *Mô tả / Tham số:* Ghi nhận trạng thái đang làm phòng theo danh mục.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Housekeeping" hoạt động ổn định trên hệ thống.`
- **Priority**
  - *Mô tả / Tham số:* Đánh dấu ưu tiên làm phòng và hiển thị icon.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Priority" hoạt động ổn định trên hệ thống.`
- **DND**
  - *Mô tả / Tham số:* Đánh dấu không làm phiền và hiển thị đúng icon.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "DND" hoạt động ổn định trên hệ thống.`
- **Đổi tình trạng hàng loạt**
  - *Mô tả / Tham số:* Chỉ cập nhật phòng đã chọn và kiểm tra quyền backend.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Đổi tình trạng hàng loạt" hoạt động ổn định trên hệ thống.`
- **Phím tắt 1/2/3**
  - *Mô tả / Tham số:* Đổi Ready/Clean/Dirty theo đúng quyền; không chạy khi đang nhập form/modal.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Phím tắt 1/2/3" hoạt động ổn định trên hệ thống.`
- **In phòng đã chọn**
  - *Mô tả / Tham số:* Bản in đúng số phòng được chọn và tình trạng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "In phòng đã chọn" hoạt động ổn định trên hệ thống.`

## Module: Tài khoản và quyền bộ phận (10 mục)

- **Đăng nhập**
  - *Mô tả / Tham số:* Tài khoản hợp lệ vào được PMS; kiểm tra thông báo khi sai/thiếu dữ liệu.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Đăng nhập" hoạt động ổn định trên hệ thống.`
- **Đăng xuất/phiên hết hạn**
  - *Mô tả / Tham số:* Hủy phiên và trở về login, không giữ quyền cũ.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Đăng xuất/phiên hết hạn" hoạt động ổn định trên hệ thống.`
- **Truy cập module**
  - *Mô tả / Tham số:* Tài khoản bộ phận chỉ vào module/màn hình được phân quyền.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Truy cập module" hoạt động ổn định trên hệ thống.`
- **View/Add/Edit/Delete**
  - *Mô tả / Tham số:* Kiểm tra cả UI và API, không chỉ ẩn nút trên giao diện.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "View/Add/Edit/Delete" hoạt động ổn định trên hệ thống.`
- **Phạm vi chi nhánh**
  - *Mô tả / Tham số:* Dữ liệu và quyền áp dụng đúng chi nhánh được chọn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Phạm vi chi nhánh" hoạt động ổn định trên hệ thống.`
- **Thao tác ngày cũ**
  - *Mô tả / Tham số:* Bill/thanh toán ngày cũ chỉ được xử lý khi có quyền/cấu hình.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Thao tác ngày cũ" hoạt động ổn định trên hệ thống.`
- **Xác minh mật khẩu**
  - *Mô tả / Tham số:* Các thao tác cần xác minh chặn mật khẩu sai và không tiết lộ dữ liệu.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Xác minh mật khẩu" hoạt động ổn định trên hệ thống.`
- **Ngôn ngữ Việt/Anh**
  - *Mô tả / Tham số:* Nhãn, ngày, tiền và thông báo đúng ngôn ngữ trên các màn hình.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Ngôn ngữ Việt/Anh" hoạt động ổn định trên hệ thống.`
- **Modal và ngày/số**
  - *Mô tả / Tham số:* Esc đóng, trường bắt buộc rõ, ngày/số được format hợp lệ và thao tác không bị che.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Modal và ngày/số" hoạt động ổn định trên hệ thống.`
- **Đăng nhập tài khoản hợp lệ**
  - *Mô tả / Tham số:* Đăng nhập testuser vào PMS local, mở module theo quyền hiện có.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Đăng nhập tài khoản hợp lệ" hoạt động ổn định trên hệ thống.`

## Module: Báo cáo / Báo cáo dịch vụ (1 mục)

- **Báo cáo hóa đơn minibar (theo sản phẩm)**
  - *Mô tả / Tham số:* Tổng hợp sản phẩm minibar theo sp_206 legacy. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày, Đến ngày, Ca làm việc, Chọn bộ phận, Chọn người dùng, Hàng bán, Nhóm theo ngày, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`

## Module: Báo cáo / Báo cáo phòng (1 mục)

- **Báo cáo dự đoán bán phòng**
  - *Mô tả / Tham số:* Báo cáo dự đoán bán phòng 17 cột FO; bỏ 4 cột tài chính ở chế độ HK bằng p_revenue_detail. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Chọn ngày, Đến ngày, Người dùng / Người bán, Chi nhánh, Loại phòng, Hiển thị chi tiết doanh thu, Doanh thu phòng bao gồm ăn sáng, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`

# ĐẶT PHÒNG / SALES (27 Modules — 275 Mục)

## Module: Sơ đồ phòng (28 mục)

- **Mở sơ đồ phòng**
  - *Mô tả / Tham số:* Hiển thị danh sách phòng theo tầng, số phòng, loại phòng và tình trạng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Mở sơ đồ phòng" hoạt động ổn định trên hệ thống.`
- **Đổi ngày xem**
  - *Mô tả / Tham số:* Số liệu phòng, khách và booking phản ánh ngày nghiệp vụ được chọn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Đổi ngày xem" hoạt động ổn định trên hệ thống.`
- **Đã đến**
  - *Mô tả / Tham số:* Mở danh sách phòng đến/đã nhận phòng theo ngày.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Đã đến" hoạt động ổn định trên hệ thống.`
- **Đã đi**
  - *Mô tả / Tham số:* Mở danh sách phòng đi/đã trả phòng theo ngày.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Đã đi" hoạt động ổn định trên hệ thống.`
- **Đang ở**
  - *Mô tả / Tham số:* Mở danh sách phòng khách đang lưu trú.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Đang ở" hoạt động ổn định trên hệ thống.`
- **Khóa OOO**
  - *Mô tả / Tham số:* Hiển thị đúng phòng bị khóa OOO và số lượng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Khóa OOO" hoạt động ổn định trên hệ thống.`
- **Khóa OOS**
  - *Mô tả / Tham số:* Hiển thị đúng phòng bị khóa OOS và số lượng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Khóa OOS" hoạt động ổn định trên hệ thống.`
- **Công suất**
  - *Mô tả / Tham số:* Mở thống kê và đối chiếu tỷ lệ chiếm dụng với số phòng có thể bán.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Công suất" hoạt động ổn định trên hệ thống.`
- **Xem danh sách**
  - *Mô tả / Tham số:* Chuyển từ sơ đồ sang bảng có khách, booking, ngày đến/đi.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Xem danh sách" hoạt động ổn định trên hệ thống.`
- **Xem sơ đồ lưới**
  - *Mô tả / Tham số:* Chuyển về thẻ phòng theo tầng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Xem sơ đồ lưới" hoạt động ổn định trên hệ thống.`
- **Thu nhỏ/phóng to/Auto Fit**
  - *Mô tả / Tham số:* Thay đổi tỷ lệ xem, không mất phòng hoặc che chức năng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Thu nhỏ/phóng to/Auto Fit" hoạt động ổn định trên hệ thống.`
- **Bật/tắt bộ lọc**
  - *Mô tả / Tham số:* Mở, đóng panel bộ lọc.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Bật/tắt bộ lọc" hoạt động ổn định trên hệ thống.`
- **Lọc theo tầng**
  - *Mô tả / Tham số:* Chỉ hiển thị phòng thuộc tầng đã chọn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lọc theo tầng" hoạt động ổn định trên hệ thống.`
- **Lọc theo loại phòng**
  - *Mô tả / Tham số:* Chỉ hiển thị loại phòng đã chọn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lọc theo loại phòng" hoạt động ổn định trên hệ thống.`
- **Lọc mã đăng ký/tên khách**
  - *Mô tả / Tham số:* Trả về phòng phù hợp booking hoặc tên khách.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lọc mã đăng ký/tên khách" hoạt động ổn định trên hệ thống.`
- **Lọc tình trạng đăng ký**
  - *Mô tả / Tham số:* Áp dụng tình trạng đăng ký được cấu hình.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lọc tình trạng đăng ký" hoạt động ổn định trên hệ thống.`
- **Lọc lưu trú**
  - *Mô tả / Tham số:* Phân biệt trống, đang ở, đến, đi, OOO.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lọc lưu trú" hoạt động ổn định trên hệ thống.`
- **Lọc tình trạng buồng phòng**
  - *Mô tả / Tham số:* Phân biệt sạch, bẩn, chờ kiểm tra và sẵn sàng theo quyền.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lọc tình trạng buồng phòng" hoạt động ổn định trên hệ thống.`
- **Xóa bộ lọc**
  - *Mô tả / Tham số:* Khôi phục toàn bộ danh sách và số lượng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Xóa bộ lọc" hoạt động ổn định trên hệ thống.`
- **Lọc tại tiêu đề cột**
  - *Mô tả / Tham số:* Bộ lọc số phòng, tên khách, mã/tên đăng ký, ngày đến/đi hoạt động độc lập.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lọc tại tiêu đề cột" hoạt động ổn định trên hệ thống.`
- **Chọn nhiều/chọn tất cả phòng**
  - *Mô tả / Tham số:* Chỉ chọn phòng đang hiển thị; bỏ chọn không tác động dữ liệu.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Chọn nhiều/chọn tất cả phòng" hoạt động ổn định trên hệ thống.`
- **Cài đặt hiển thị**
  - *Mô tả / Tham số:* Mở hộp kích thước icon/chữ/phòng, hướng tầng, vị trí chính xác.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Cài đặt hiển thị" hoạt động ổn định trên hệ thống.`
- **Lưu/khôi phục cài đặt**
  - *Mô tả / Tham số:* Thiết lập được giữ theo tài khoản; Mặc định khôi phục đúng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lưu/khôi phục cài đặt" hoạt động ổn định trên hệ thống.`
- **Chi tiết phòng**
  - *Mô tả / Tham số:* Mở thông tin phòng/khách/booking, dịch vụ và lịch sử tương ứng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Chi tiết phòng" hoạt động ổn định trên hệ thống.`
- **Menu chuột phải**
  - *Mô tả / Tham số:* Hiển thị tác vụ phù hợp tình trạng phòng và quyền của bộ phận.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Menu chuột phải" hoạt động ổn định trên hệ thống.`
- **Biểu tượng nghiệp vụ**
  - *Mô tả / Tham số:* Thể hiện extra bed, khách VIP, yêu cầu đặc biệt, sinh nhật, honeymoon, DND.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Biểu tượng nghiệp vụ" hoạt động ổn định trên hệ thống.`
- **Phòng thông nhau**
  - *Mô tả / Tham số:* Hiển thị đúng các cặp phòng connecting và cửa kết nối.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Phòng thông nhau" hoạt động ổn định trên hệ thống.`
- **Cập nhật thời gian thực**
  - *Mô tả / Tham số:* Dữ liệu thay đổi ở module khác cập nhật qua websocket/refresh.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Cập nhật thời gian thực" hoạt động ổn định trên hệ thống.`

## Module: Phòng trống (15 mục)

- **Mở bảng phòng trống**
  - *Mô tả / Tham số:* Có dữ liệu loại phòng, ngày, tổng số phòng và thống kê.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Mở bảng phòng trống" hoạt động ổn định trên hệ thống.`
- **Chọn phạm vi ngày**
  - *Mô tả / Tham số:* Hôm nay/mai, tuần/tháng hiện tại, tiếp theo, trước và tùy chỉnh trả đúng khoảng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Chọn phạm vi ngày" hoạt động ổn định trên hệ thống.`
- **Áp dụng phạm vi ngày**
  - *Mô tả / Tham số:* Tải lại ma trận theo khoảng đã chọn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Áp dụng phạm vi ngày" hoạt động ổn định trên hệ thống.`
- **AV**
  - *Mô tả / Tham số:* Xem số phòng trống theo loại phòng và ngày.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "AV" hoạt động ổn định trên hệ thống.`
- **OCC**
  - *Mô tả / Tham số:* Xem số phòng chiếm dụng và công suất.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "OCC" hoạt động ổn định trên hệ thống.`
- **ALM**
  - *Mô tả / Tham số:* Xem số phòng allotment.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "ALM" hoạt động ổn định trên hệ thống.`
- **OOO**
  - *Mô tả / Tham số:* Xem phòng khóa không bán theo ngày.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "OOO" hoạt động ổn định trên hệ thống.`
- **OOS**
  - *Mô tả / Tham số:* Xem phòng khóa OOS theo ngày.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "OOS" hoạt động ổn định trên hệ thống.`
- **EB**
  - *Mô tả / Tham số:* Xem tổng extra bed theo ngày.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "EB" hoạt động ổn định trên hệ thống.`
- **BBC**
  - *Mô tả / Tham số:* Xem số liệu ăn sáng trẻ em theo ngày.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "BBC" hoạt động ổn định trên hệ thống.`
- **Mở rộng loại phòng**
  - *Mô tả / Tham số:* Hiển thị dữ liệu dạng phòng chi tiết dưới loại phòng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Mở rộng loại phòng" hoạt động ổn định trên hệ thống.`
- **Chi tiết ô ma trận**
  - *Mô tả / Tham số:* Mở các booking/phòng cấu thành chỉ tiêu của ô được chọn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Chi tiết ô ma trận" hoạt động ổn định trên hệ thống.`
- **Thống kê**
  - *Mô tả / Tham số:* Đối chiếu Series, Allotment, đảm bảo/không đảm bảo, phòng đến, khách, hủy, noshow.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Thống kê" hoạt động ổn định trên hệ thống.`
- **Giữ phạm vi xem**
  - *Mô tả / Tham số:* Đổi tab rồi quay lại giữ khoảng ngày; reload áp dụng mặc định hệ thống.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Giữ phạm vi xem" hoạt động ổn định trên hệ thống.`
- **Xuất Excel**
  - *Mô tả / Tham số:* Tải file đúng số liệu, loại phòng và phạm vi đang xem.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Xuất Excel" hoạt động ổn định trên hệ thống.`

## Module: Kế hoạch phòng (19 mục)

- **Mở kế hoạch phòng**
  - *Mô tả / Tham số:* Hiển thị lịch phòng theo khoảng ngày và hàng số phòng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Mở kế hoạch phòng" hoạt động ổn định trên hệ thống.`
- **Chọn khoảng ngày**
  - *Mô tả / Tham số:* Lịch hiển thị chính xác bắt đầu/kết thúc và ngày nghiệp vụ.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Chọn khoảng ngày" hoạt động ổn định trên hệ thống.`
- **Đổi View**
  - *Mô tả / Tham số:* Đổi cách hiển thị lịch mà không làm mất booking.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Đổi View" hoạt động ổn định trên hệ thống.`
- **Sắp xếp**
  - *Mô tả / Tham số:* Sắp xếp số phòng/loại phòng theo tùy chọn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Sắp xếp" hoạt động ổn định trên hệ thống.`
- **Tìm kiếm**
  - *Mô tả / Tham số:* Lọc khách hàng, mã đăng ký và phòng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Tìm kiếm" hoạt động ổn định trên hệ thống.`
- **Bộ lọc**
  - *Mô tả / Tham số:* Lọc loại/dạng phòng, tình trạng; Xóa lọc khôi phục.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Bộ lọc" hoạt động ổn định trên hệ thống.`
- **Mở booking từ lịch**
  - *Mô tả / Tham số:* Mở đúng booking và thông tin phòng trên thanh lịch.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Mở booking từ lịch" hoạt động ổn định trên hệ thống.`
- **Đặt phòng nhanh**
  - *Mô tả / Tham số:* Chọn phòng/giai đoạn rồi nhập tên BK, market, source, tình trạng và lưu.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Đặt phòng nhanh" hoạt động ổn định trên hệ thống.`
- **Đặt phòng nhanh - dữ liệu thiếu**
  - *Mô tả / Tham số:* Chặn lưu khi thiếu tên đăng ký, thị trường, nguồn khách hoặc tình trạng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Đặt phòng nhanh - dữ liệu thiếu" hoạt động ổn định trên hệ thống.`
- **Giao phòng**
  - *Mô tả / Tham số:* Gán đúng số phòng cho booking và cập nhật ma trận AV.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Giao phòng" hoạt động ổn định trên hệ thống.`
- **Tách phòng**
  - *Mô tả / Tham số:* Chia giai đoạn theo ngày tách, giữ đúng tổng đêm và dịch vụ.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Tách phòng" hoạt động ổn định trên hệ thống.`
- **Chuyển phòng trên lịch**
  - *Mô tả / Tham số:* Chỉ chuyển khi phòng nhận phù hợp giai đoạn và điều kiện phòng.
  - *Đánh giá cá nhân:* `Hoàn thành nhưng còn lỗi (PMS note Dòng 305, 306, 530): Cần kiểm tra chuyển phòng sau khi ở qua đêm / ngày checkout; cấu hình AllowOverRoomTypeRoomKind=1 khi Suite=0 chưa cảnh báo đúng.`
- **Khóa/mở chuyển phòng**
  - *Mô tả / Tham số:* Tôn trọng khóa di chuyển và quyền thao tác.
  - *Đánh giá cá nhân:* `Hoàn thành nhưng còn lỗi (PMS note Dòng 305, 306, 530): Cần kiểm tra chuyển phòng sau khi ở qua đêm / ngày checkout; cấu hình AllowOverRoomTypeRoomKind=1 khi Suite=0 chưa cảnh báo đúng.`
- **Gỡ số phòng**
  - *Mô tả / Tham số:* Bỏ gán số phòng, giữ dòng đăng ký và số lượng allocation.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Gỡ số phòng" hoạt động ổn định trên hệ thống.`
- **Hủy phòng**
  - *Mô tả / Tham số:* Nhập lý do và cập nhật trạng thái, AV, lịch phòng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Hủy phòng" hoạt động ổn định trên hệ thống.`
- **Khóa OOO/OOS**
  - *Mô tả / Tham số:* Chọn giai đoạn, lý do; thể hiện vùng khóa chính xác.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Khóa OOO/OOS" hoạt động ổn định trên hệ thống.`
- **Mở khóa phòng**
  - *Mô tả / Tham số:* Cập nhật vùng khóa theo ngày/giờ mở khóa nghiệp vụ.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Mở khóa phòng" hoạt động ổn định trên hệ thống.`
- **Danh sách chờ**
  - *Mô tả / Tham số:* Hiển thị và thao tác booking chờ theo tình trạng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Danh sách chờ" hoạt động ổn định trên hệ thống.`
- **Biên ngày đến/đi**
  - *Mô tả / Tham số:* Phòng checkout không chiếm đêm tiếp theo; không chồng lấn booking nhận cùng ngày.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Biên ngày đến/đi" hoạt động ổn định trên hệ thống.`

## Module: Tạo đăng ký - thông tin booking (37 mục)

- **Mở màn hình đăng ký**
  - *Mô tả / Tham số:* Hiển thị tab booking, thanh tác vụ và bảng phòng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Mở màn hình đăng ký" hoạt động ổn định trên hệ thống.`
- **Mở form Booking mới**
  - *Mô tả / Tham số:* Mở modal tạo đăng ký, mã booking tự sinh, ngày mặc định theo hệ thống.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Mở form Booking mới" hoạt động ổn định trên hệ thống.`
- **Tên đăng ký**
  - *Mô tả / Tham số:* Nhập và lưu tên, chặn tên rỗng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Tên đăng ký" hoạt động ổn định trên hệ thống.`
- **Mã tham chiếu**
  - *Mô tả / Tham số:* Nhập mã đối tác/OTA và tra cứu lại.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Mã tham chiếu" hoạt động ổn định trên hệ thống.`
- **Ngày đến/ngày đi**
  - *Mô tả / Tham số:* Chọn/gõ ngày; chặn ngày đi trước ngày đến.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Ngày đến/ngày đi" hoạt động ổn định trên hệ thống.`
- **Số đêm**
  - *Mô tả / Tham số:* Tính và điều chỉnh số đêm đồng bộ với ngày lưu trú; hỗ trợ Day Use.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Số đêm" hoạt động ổn định trên hệ thống.`
- **Tình trạng đăng ký**
  - *Mô tả / Tham số:* Chọn Guaranteed, None Guaranteed, Waiting, Allotment, Cancelled và tình trạng phạt theo cấu hình.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Tình trạng đăng ký" hoạt động ổn định trên hệ thống.`
- **Ngày xác nhận/cut-off**
  - *Mô tả / Tham số:* Tính đúng khi tạo mới; không tự thay đổi ngoài quy tắc khi sửa booking.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Ngày xác nhận/cut-off" hoạt động ổn định trên hệ thống.`
- **Công ty**
  - *Mô tả / Tham số:* Chọn công ty hợp lệ và đồng bộ thông tin liên quan.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Công ty" hoạt động ổn định trên hệ thống.`
- **Phương thức thanh toán**
  - *Mô tả / Tham số:* Chọn Cash, City ledger, chuyển khoản, thẻ, voucher, complimentary theo danh mục.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Phương thức thanh toán" hoạt động ổn định trên hệ thống.`
- **Thị trường**
  - *Mô tả / Tham số:* Chọn market segment theo danh mục.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Thị trường" hoạt động ổn định trên hệ thống.`
- **Nguồn khách**
  - *Mô tả / Tham số:* Chọn source/channel theo danh mục.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Nguồn khách" hoạt động ổn định trên hệ thống.`
- **Người bán**
  - *Mô tả / Tham số:* Gán salesperson hợp lệ và lưu cho báo cáo.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Người bán" hoạt động ổn định trên hệ thống.`
- **Người đặt phòng**
  - *Mô tả / Tham số:* Chọn người đặt; thêm người đặt từ form theo quyền.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Người đặt phòng" hoạt động ổn định trên hệ thống.`
- **Thông tin liên hệ**
  - *Mô tả / Tham số:* Nhập số điện thoại/email và lưu chính xác.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Thông tin liên hệ" hoạt động ổn định trên hệ thống.`
- **Ghi chú/Màu BK**
  - *Mô tả / Tham số:* Ghi chú xuống dòng, chọn màu để nhận biết booking.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Ghi chú/Màu BK" hoạt động ổn định trên hệ thống.`
- **Tiền phòng gửi Master**
  - *Mô tả / Tham số:* Ghi nhận room charge về phiếu tổng khi bật tùy chọn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Tiền phòng gửi Master" hoạt động ổn định trên hệ thống.`
- **Thông tin đưa đón**
  - *Mô tả / Tham số:* Thêm/sửa/xóa từng dòng ngày, giờ, chuyến, nội dung đưa đón.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Thông tin đưa đón" hoạt động ổn định trên hệ thống.`
- **Lấy phòng**
  - *Mô tả / Tham số:* Chọn loại/dạng phòng, số lượng, giai đoạn và mã giá.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lấy phòng" hoạt động ổn định trên hệ thống.`
- **Kiểm tra tồn AV**
  - *Mô tả / Tham số:* Chặn/cảnh báo lấy vượt phòng theo cấu hình AllowOverRoomTypeRoomKind.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Kiểm tra tồn AV" hoạt động ổn định trên hệ thống.`
- **Giữ allocation**
  - *Mô tả / Tham số:* Lấy lại phòng sau hủy không bị số lượng/mã giá cũ chi phối sai.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Giữ allocation" hoạt động ổn định trên hệ thống.`
- **Lưu booking mới**
  - *Mô tả / Tham số:* Tạo booking và phòng/allocation đồng bộ; không sinh trùng khi bấm lặp.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lưu booking mới" hoạt động ổn định trên hệ thống.`
- **Chặn lưu dữ liệu thiếu**
  - *Mô tả / Tham số:* Không tạo booking khi thiếu tên và các trường nghiệp vụ bắt buộc.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Chặn lưu dữ liệu thiếu" hoạt động ổn định trên hệ thống.`
- **Sửa/Quay lại/Lưu**
  - *Mô tả / Tham số:* Vào chế độ sửa, hủy sửa hoặc lưu đúng dữ liệu thay đổi.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Sửa/Quay lại/Lưu" hoạt động ổn định trên hệ thống.`
- **Nhân bản**
  - *Mô tả / Tham số:* Copy booking theo giai đoạn, số lượng và lựa chọn sao chép.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Nhân bản" hoạt động ổn định trên hệ thống.`
- **Xóa booking**
  - *Mô tả / Tham số:* Chặn trường hợp không hợp lệ, xử lý phòng/dịch vụ/cọc nhất quán.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Xóa booking" hoạt động ổn định trên hệ thống.`
- **Khôi phục booking**
  - *Mô tả / Tham số:* Phục hồi đăng ký hủy sau khi kiểm tra trùng phòng và availability.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Khôi phục booking" hoạt động ổn định trên hệ thống.`
- **Nhiều tab booking**
  - *Mô tả / Tham số:* Đổi/đóng tab; giữ đúng booking đang hoạt động, không lẫn dữ liệu.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Nhiều tab booking" hoạt động ổn định trên hệ thống.`
- **Tìm booking toàn hệ thống**
  - *Mô tả / Tham số:* Tìm theo mã/tên, chọn kết quả mở đúng đăng ký.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Tìm booking toàn hệ thống" hoạt động ổn định trên hệ thống.`
- **Thông báo booking**
  - *Mô tả / Tham số:* Thêm/sửa/xóa thông báo theo ngày, phòng, nội dung; đối chiếu khi đến hạn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Thông báo booking" hoạt động ổn định trên hệ thống.`
- **Lịch sử booking**
  - *Mô tả / Tham số:* Hiển thị người, ngày/giờ, hành động và dữ liệu cập nhật.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lịch sử booking" hoạt động ổn định trên hệ thống.`
- **Tùy chọn cột hiển thị**
  - *Mô tả / Tham số:* Ẩn/hiện, đổi thứ tự/độ rộng, giữ cài đặt bảng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Tùy chọn cột hiển thị" hoạt động ổn định trên hệ thống.`
- **Sắp xếp/nhóm bảng**
  - *Mô tả / Tham số:* Nhóm theo trạng thái/loại phòng; mở rộng/thu gọn không làm sai tổng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Sắp xếp/nhóm bảng" hoạt động ổn định trên hệ thống.`
- **Chọn nhiều phòng**
  - *Mô tả / Tham số:* Chọn dòng/nhóm/tất cả và tác vụ chỉ áp dụng phòng đã chọn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Chọn nhiều phòng" hoạt động ổn định trên hệ thống.`
- **Xuất Excel đăng ký**
  - *Mô tả / Tham số:* File giữ đủ dữ liệu booking/phòng và cột nghiệp vụ.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Xuất Excel đăng ký" hoạt động ổn định trên hệ thống.`
- **In xác nhận/phiếu đăng ký**
  - *Mô tả / Tham số:* Đúng khách, phòng, giai đoạn; tùy chọn hiện/không hiện giá.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "In xác nhận/phiếu đăng ký" hoạt động ổn định trên hệ thống.`
- **In hóa đơn tạm**
  - *Mô tả / Tham số:* Tổng tiền phòng, dịch vụ và phụ thu khớp booking.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "In hóa đơn tạm" hoạt động ổn định trên hệ thống.`

## Module: Tạo đăng ký - phòng và tác vụ (22 mục)

- **Chi tiết phòng theo đêm**
  - *Mô tả / Tham số:* Mở chi tiết tiền phòng và các dịch vụ từng ngày.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Chi tiết phòng theo đêm" hoạt động ổn định trên hệ thống.`
- **Giá phòng/mã giá**
  - *Mô tả / Tham số:* Áp mã giá, rate plan và daily mapping đúng ngày/loại/dạng phòng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Giá phòng/mã giá" hoạt động ổn định trên hệ thống.`
- **Tăng/giảm giá**
  - *Mô tả / Tham số:* Điều chỉnh giá đúng phạm vi, tổng tiền và quyền.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Tăng/giảm giá" hoạt động ổn định trên hệ thống.`
- **Người lớn/trẻ em/em bé**
  - *Mô tả / Tham số:* Nhập số lượng hợp lệ, cập nhật allocation và tổng khách.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Người lớn/trẻ em/em bé" hoạt động ổn định trên hệ thống.`
- **Ngày/giờ từng phòng**
  - *Mô tả / Tham số:* Cập nhật ngày đến/đi, giờ và số đêm theo ràng buộc booking.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Ngày/giờ từng phòng" hoạt động ổn định trên hệ thống.`
- **Phòng Day Use/ở theo giờ**
  - *Mô tả / Tham số:* Chấp nhận ngày đến=ngày đi, hiển thị đêm/phí đúng quy tắc.
  - *Đánh giá cá nhân:* `Hoàn thành nhưng còn lỗi (PMS note Dòng 529, 535): Chưa cập nhật đúng ngày đến = ngày đi; số lượng thống kê đến/đi/ở màn hình Room map bị lệch khi có phòng Day use.`
- **Tự động gán phòng**
  - *Mô tả / Tham số:* Gán đủ số phòng khả dụng, ưu tiên tầng thấp và phòng gần nhau.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Tự động gán phòng" hoạt động ổn định trên hệ thống.`
- **Gán phòng thủ công**
  - *Mô tả / Tham số:* Không gán trùng giai đoạn; tính cả Day Use và phòng khóa.
  - *Đánh giá cá nhân:* `Hoàn thành nhưng còn lỗi (PMS note Dòng 89): Chưa tạo được booking có ngày đến = ngày đi (Day use) trừ phòng AV; phòng in 1 out 2 gán phòng 101 thì phòng khác in 2 out 3 không gán được cho 101.`
- **Nâng hạng phòng**
  - *Mô tả / Tham số:* Kiểm tra AV loại nhận, giữ loại khởi tạo và cập nhật giá theo lựa chọn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Nâng hạng phòng" hoạt động ổn định trên hệ thống.`
- **Cập nhật nhanh**
  - *Mô tả / Tham số:* Đổi giai đoạn/giá cho phòng được chọn đúng quyền và cấu hình SALE/FO.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Cập nhật nhanh" hoạt động ổn định trên hệ thống.`
- **Khóa chỉnh phòng Inhouse**
  - *Mô tả / Tham số:* Khóa ngày đến; giá/ngày đi chỉ sửa khi có quyền/cấu hình cho phép.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Khóa chỉnh phòng Inhouse" hoạt động ổn định trên hệ thống.`
- **Gỡ số phòng**
  - *Mô tả / Tham số:* Không gỡ trái phép phòng đã nhận; allocation và AV đúng sau gỡ.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Gỡ số phòng" hoạt động ổn định trên hệ thống.`
- **Hủy phòng**
  - *Mô tả / Tham số:* Nhập lý do, xử lý phạt, giữ lịch sử và tổng booking chính xác.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Hủy phòng" hoạt động ổn định trên hệ thống.`
- **Chuyển phòng**
  - *Mô tả / Tham số:* Kiểm tra giai đoạn trống, trạng thái Ready và giới hạn AV trước khi chuyển.
  - *Đánh giá cá nhân:* `Hoàn thành nhưng còn lỗi (PMS note Dòng 305, 306, 530): Cần kiểm tra chuyển phòng sau khi ở qua đêm / ngày checkout; cấu hình AllowOverRoomTypeRoomKind=1 khi Suite=0 chưa cảnh báo đúng.`
- **Khóa/mở chuyển phòng**
  - *Mô tả / Tham số:* Trạng thái khóa bảo vệ đúng dòng phòng và được lưu.
  - *Đánh giá cá nhân:* `Hoàn thành nhưng còn lỗi (PMS note Dòng 305, 306, 530): Cần kiểm tra chuyển phòng sau khi ở qua đêm / ngày checkout; cấu hình AllowOverRoomTypeRoomKind=1 khi Suite=0 chưa cảnh báo đúng.`
- **Charge No-show**
  - *Mô tả / Tham số:* Nhập mức phạt, lưu bill đúng ngày/bộ phận và booking.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Charge No-show" hoạt động ổn định trên hệ thống.`
- **Đồng bộ trạng thái booking**
  - *Mô tả / Tham số:* Một phòng nhận/đi/hủy làm trạng thái booking tuân theo quy tắc toàn bộ phòng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Đồng bộ trạng thái booking" hoạt động ổn định trên hệ thống.`
- **Lịch sử phòng chuyển**
  - *Mô tả / Tham số:* Phòng cũ chỉ đọc; ngày đi/ngày đến và bill quá khứ được giữ.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lịch sử phòng chuyển" hoạt động ổn định trên hệ thống.`
- **Chuyển cùng ngày chưa post bill**
  - *Mô tả / Tham số:* Phòng cũ zero night không tự sinh dòng tiền phòng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Chuyển cùng ngày chưa post bill" hoạt động ổn định trên hệ thống.`
- **Chuyển cùng ngày đã post bill**
  - *Mô tả / Tham số:* Phòng mới kế thừa tham chiếu nhưng không cộng/post bill hai lần.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Chuyển cùng ngày đã post bill" hoạt động ổn định trên hệ thống.`
- **Chuyển sau một đêm**
  - *Mô tả / Tham số:* Phòng cũ giữ bill trước ngày chuyển; phòng mới tính đúng các đêm còn lại.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Chuyển sau một đêm" hoạt động ổn định trên hệ thống.`
- **Kế thừa dịch vụ khi chuyển**
  - *Mô tả / Tham số:* Extra bed/dịch vụ tự động chuyển đúng giai đoạn còn lại.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Kế thừa dịch vụ khi chuyển" hoạt động ổn định trên hệ thống.`

## Module: Thông tin khách (10 mục)

- **Mở thông tin khách**
  - *Mô tả / Tham số:* Hiển thị khách đúng booking/phòng, không đưa phòng lịch sử chuyển vào danh sách hoạt động.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Mở thông tin khách" hoạt động ổn định trên hệ thống.`
- **Thêm khách chính/khách cùng phòng**
  - *Mô tả / Tham số:* Nhập thông tin và liên kết đúng phòng, không nhân bản ngoài ý muốn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Thêm khách chính/khách cùng phòng" hoạt động ổn định trên hệ thống.`
- **Sửa hồ sơ khách**
  - *Mô tả / Tham số:* Lưu họ tên, giới tính, ngày sinh, quốc tịch, địa chỉ, giấy tờ.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Sửa hồ sơ khách" hoạt động ổn định trên hệ thống.`
- **Ngày cấp/hết hạn giấy tờ**
  - *Mô tả / Tham số:* Chọn/gõ/xóa ngày, kiểm tra dữ liệu không hợp lệ.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Ngày cấp/hết hạn giấy tờ" hoạt động ổn định trên hệ thống.`
- **Ảnh khách**
  - *Mô tả / Tham số:* Tải/chụp/xóa ảnh; xem ảnh đúng khách.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Ảnh khách" hoạt động ổn định trên hệ thống.`
- **Scan giấy tờ**
  - *Mô tả / Tham số:* Kiểm tra thiết bị và nhận dữ liệu; không chấm đạt khi chưa có thiết bị.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Scan giấy tờ" hoạt động ổn định trên hệ thống.`
- **Khách VIP/đặc điểm**
  - *Mô tả / Tham số:* Ghi nhận VIP, sinh nhật, honeymoon và hiển thị biểu tượng phù hợp.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Khách VIP/đặc điểm" hoạt động ổn định trên hệ thống.`
- **Xóa khách**
  - *Mô tả / Tham số:* Chặn khi có bill/thanh toán ràng buộc; không xóa nhầm khách khác.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Xóa khách" hoạt động ổn định trên hệ thống.`
- **Xuất Excel khách**
  - *Mô tả / Tham số:* Xuất đúng danh sách, phòng và thông tin giấy tờ.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Xuất Excel khách" hoạt động ổn định trên hệ thống.`
- **Cài đặt hiển thị khách**
  - *Mô tả / Tham số:* Lưu/khôi phục vị trí/cột theo cấu hình.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Cài đặt hiển thị khách" hoạt động ổn định trên hệ thống.`

## Module: Dịch vụ bổ sung (9 mục)

- **Thêm dịch vụ bổ sung**
  - *Mô tả / Tham số:* Chọn dịch vụ, ngày, phòng/Master, số lượng, đơn giá và lưu.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Thêm dịch vụ bổ sung" hoạt động ổn định trên hệ thống.`
- **Phạm vi ngày dịch vụ**
  - *Mô tả / Tham số:* Chỉ áp dụng ngày lưu trú hợp lệ, không lệch múi giờ.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Phạm vi ngày dịch vụ" hoạt động ổn định trên hệ thống.`
- **Phòng/Master**
  - *Mô tả / Tham số:* Bill thuộc đúng booking room hoặc phiếu tổng theo lựa chọn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Phòng/Master" hoạt động ổn định trên hệ thống.`
- **Sửa đơn giá/số lượng từng ngày**
  - *Mô tả / Tham số:* Tổng chi tiết và tổng booking cập nhật đúng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Sửa đơn giá/số lượng từng ngày" hoạt động ổn định trên hệ thống.`
- **Xóa dịch vụ**
  - *Mô tả / Tham số:* Chọn phòng/tất cả, nhập lý do và áp quyền/trạng thái bill.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Xóa dịch vụ" hoạt động ổn định trên hệ thống.`
- **Extra Bed**
  - *Mô tả / Tham số:* Nhập số giường, giá, từng đêm; tổng thêm giường khớp.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Extra Bed" hoạt động ổn định trên hệ thống.`
- **Chi tiết ăn sáng trẻ em**
  - *Mô tả / Tham số:* Nhập hồ sơ trẻ, tuổi, ngày và giá phụ thu; hỗ trợ nhập giá khác 0.
  - *Đánh giá cá nhân:* `Hoàn thành nhưng còn lỗi (PMS note Dòng 88, 537): Thông số Booking_AutoExtraChargeBFChild chưa hoạt động đúng, bật extra charge chưa tự cộng vào tổng booking.`
- **Yêu cầu đặc biệt**
  - *Mô tả / Tham số:* Chọn/tạo yêu cầu, ghi nội dung, số lượng và lưu đúng phòng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Yêu cầu đặc biệt" hoạt động ổn định trên hệ thống.`
- **Dịch vụ ngày quá khứ**
  - *Mô tả / Tham số:* Bill đã post/lịch sử không bị sửa sai; áp quyền thao tác ngày cũ.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Dịch vụ ngày quá khứ" hoạt động ổn định trên hệ thống.`

## Module: Đặt cọc (10 mục)

- **Mở đặt cọc**
  - *Mô tả / Tham số:* Tải cọc đúng booking, tổng tiền và ảnh chứng từ.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Mở đặt cọc" hoạt động ổn định trên hệ thống.`
- **Thêm cọc**
  - *Mô tả / Tham số:* Nhập số tiền, tiền tệ, ngày/giờ, ca, HTTT, tài khoản ngân hàng, mô tả.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Thêm cọc" hoạt động ổn định trên hệ thống.`
- **Danh mục tài khoản ngân hàng**
  - *Mô tả / Tham số:* Dùng tài khoản cấu hình hệ thống, không hardcode danh sách.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Danh mục tài khoản ngân hàng" hoạt động ổn định trên hệ thống.`
- **Ảnh chứng từ**
  - *Mô tả / Tham số:* Tải/xem/xóa ảnh đúng cọc và quyền.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Ảnh chứng từ" hoạt động ổn định trên hệ thống.`
- **Lưu bộ phận/outlet**
  - *Mô tả / Tham số:* SALE lưu MR, FO lưu FO; outlet RC theo yêu cầu nghiệp vụ.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lưu bộ phận/outlet" hoạt động ổn định trên hệ thống.`
- **Sửa cọc**
  - *Mô tả / Tham số:* Chỉ bật Quay lại/Lưu; đổi HTTT cập nhật mô tả tương ứng.
  - *Đánh giá cá nhân:* `Hoàn thành nhưng lưu ý (DOC-005): Khi sửa đổi HTTT của cọc, phần mô tả cần tự động cập nhật theo HTTT mới.`
- **Tách cọc**
  - *Mô tả / Tham số:* Tách số tiền hoặc 1/2, 1/3; tổng tiền trước/sau không đổi.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Tách cọc" hoạt động ổn định trên hệ thống.`
- **Chuyển cọc**
  - *Mô tả / Tham số:* Danh sách booking/phòng/khách nhận đầy đủ; chuyển đúng ownership và folio.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Chuyển cọc" hoạt động ổn định trên hệ thống.`
- **Xóa cọc**
  - *Mô tả / Tham số:* Nhập lý do, hủy xác nhận không xóa; giữ lịch sử/audit.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Xóa cọc" hoạt động ổn định trên hệ thống.`
- **Đối chiếu tổng cọc**
  - *Mô tả / Tham số:* Tổng trên đăng ký, modal cọc và hóa đơn khớp sau thêm/sửa/tách/chuyển/xóa.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Đối chiếu tổng cọc" hoạt động ổn định trên hệ thống.`

## Module: Khóa phòng OOO/OOS (11 mục)

- **Mở danh sách khóa**
  - *Mô tả / Tham số:* Tải phòng, loại phòng, tình trạng khóa và lịch sử.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Mở danh sách khóa" hoạt động ổn định trên hệ thống.`
- **Lọc khóa**
  - *Mô tả / Tham số:* Tìm số phòng, lọc loại phòng và Sẵn sàng/OOO/OOS.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lọc khóa" hoạt động ổn định trên hệ thống.`
- **Khóa OOO**
  - *Mô tả / Tham số:* Chọn một/nhiều phòng, bắt đầu/kết thúc, lý do; lưu khóa đúng giai đoạn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Khóa OOO" hoạt động ổn định trên hệ thống.`
- **Khóa OOS**
  - *Mô tả / Tham số:* Ghi nhận OOS đúng quy tắc bán phòng và công suất.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Khóa OOS" hoạt động ổn định trên hệ thống.`
- **Sửa khóa**
  - *Mô tả / Tham số:* Sửa giai đoạn/lý do từng phòng hoặc hàng loạt; không ghi đè phòng khác.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Sửa khóa" hoạt động ổn định trên hệ thống.`
- **Mở khóa**
  - *Mô tả / Tham số:* Ngày/giờ kết thúc theo ngày hệ thống và giờ thực hiện.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Mở khóa" hoạt động ổn định trên hệ thống.`
- **Lịch sử khóa**
  - *Mô tả / Tham số:* Xem các lần khóa/mở, người thao tác, lý do và giai đoạn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lịch sử khóa" hoạt động ổn định trên hệ thống.`
- **Availability khi mở sớm**
  - *Mô tả / Tham số:* Giữ khóa các ngày quá khứ; ngày mở áp giờ cutoff của cấu hình.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Availability khi mở sớm" hoạt động ổn định trên hệ thống.`
- **Kiểm tra booking chưa gán phòng**
  - *Mô tả / Tham số:* Không khóa làm booking mất khả năng assign khi cấu hình không cho phép.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Kiểm tra booking chưa gán phòng" hoạt động ổn định trên hệ thống.`
- **Xung đột phòng có khách**
  - *Mô tả / Tham số:* Chặn khóa trái phép trên phòng đang lưu trú hoặc giai đoạn xung đột.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Xung đột phòng có khách" hoạt động ổn định trên hệ thống.`
- **Sửa/Hủy/Lưu hàng loạt**
  - *Mô tả / Tham số:* Chỉ lưu dòng thay đổi; Hủy khôi phục dữ liệu và xử lý lỗi rõ ràng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Sửa/Hủy/Lưu hàng loạt" hoạt động ổn định trên hệ thống.`

## Module: Danh sách công việc (11 mục)

- **Mở danh sách công việc**
  - *Mô tả / Tham số:* Hiển thị đủ sáu tab nghiệp vụ và bộ lọc ngày.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Mở danh sách công việc" hoạt động ổn định trên hệ thống.`
- **Phòng đến**
  - *Mô tả / Tham số:* Xem chưa nhận/đã nhận/tất cả; đúng tổng phòng và khách.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Phòng đến" hoạt động ổn định trên hệ thống.`
- **Phòng đi**
  - *Mô tả / Tham số:* Xem phòng đến hạn trả theo ngày.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Phòng đi" hoạt động ổn định trên hệ thống.`
- **Đăng ký chờ xác nhận**
  - *Mô tả / Tham số:* Xem booking chờ; chỉnh/lưu ghi chú theo quyền.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Đăng ký chờ xác nhận" hoạt động ổn định trên hệ thống.`
- **Đón tiễn khách**
  - *Mô tả / Tham số:* Danh sách chuyến theo thời gian/khoảng ngày.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Đón tiễn khách" hoạt động ổn định trên hệ thống.`
- **Phòng không đến (Noshow)**
  - *Mô tả / Tham số:* Danh sách khách không đến theo ngày, booking và trạng thái.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Phòng không đến (Noshow)" hoạt động ổn định trên hệ thống.`
- **Sinh nhật khách**
  - *Mô tả / Tham số:* Hiển thị khách có ngày sinh trong khoảng đã chọn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Sinh nhật khách" hoạt động ổn định trên hệ thống.`
- **Gõ/chọn/sao chép ngày**
  - *Mô tả / Tham số:* Nhập 6/8 chữ số, Hôm nay/Ngày mai, xóa nhanh và lịch hoạt động.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Gõ/chọn/sao chép ngày" hoạt động ổn định trên hệ thống.`
- **Tìm kiếm công việc**
  - *Mô tả / Tham số:* Lọc mã BK, tên, phòng, công ty đúng dữ liệu.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Tìm kiếm công việc" hoạt động ổn định trên hệ thống.`
- **Mở booking bằng double-click**
  - *Mô tả / Tham số:* Nhấp đúp mã đăng ký mở đúng chi tiết booking.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Mở booking bằng double-click" hoạt động ổn định trên hệ thống.`
- **Thu gọn/ghi chú nhóm**
  - *Mô tả / Tham số:* Giữ đúng tổng room night/phòng, dịch vụ, cọc và nội dung ghi chú.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Thu gọn/ghi chú nhóm" hoạt động ổn định trên hệ thống.`

## Module: Tìm kiếm chung (14 mục)

- **Mở tìm kiếm chung**
  - *Mô tả / Tham số:* Hiển thị ba tab Đăng ký/Phòng/Khách và bộ lọc.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Mở tìm kiếm chung" hoạt động ổn định trên hệ thống.`
- **Tìm đăng ký**
  - *Mô tả / Tham số:* Tra cứu mã BK, tình trạng và bộ lọc nâng cao.
  - *Đánh giá cá nhân:* `Hoàn thành nhưng còn lỗi (PMS note Dòng 541): Tab Đăng ký và Phòng đôi khi báo Server Error; tab khách thiếu phân trang và thông tin phòng.`
- **Tìm phòng**
  - *Mô tả / Tham số:* Tra cứu số phòng, khách, thời gian và tình trạng lưu trú.
  - *Đánh giá cá nhân:* `Hoàn thành nhưng còn lỗi (PMS note Dòng 541): Tab Đăng ký và Phòng đôi khi báo Server Error; tab khách thiếu phân trang và thông tin phòng.`
- **Tìm khách**
  - *Mô tả / Tham số:* Tìm hồ sơ khách, thông tin liên hệ/giấy tờ theo các trường trên màn hình.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Tìm khách" hoạt động ổn định trên hệ thống.`
- **Bộ lọc nâng cao**
  - *Mô tả / Tham số:* Kết hợp điều kiện ngày, công ty, thị trường, nguồn và người bán.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Bộ lọc nâng cao" hoạt động ổn định trên hệ thống.`
- **Xóa/Áp dụng bộ lọc**
  - *Mô tả / Tham số:* Điều kiện được cập nhật, không giữ điều kiện ẩn ngoài ý muốn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Xóa/Áp dụng bộ lọc" hoạt động ổn định trên hệ thống.`
- **Cột hiển thị**
  - *Mô tả / Tham số:* Ẩn/hiện cột; Hiện tất cả khôi phục bảng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Cột hiển thị" hoạt động ổn định trên hệ thống.`
- **Phân trang**
  - *Mô tả / Tham số:* Đổi 50/100/200 dòng, trang trước/sau giữ đúng tập kết quả.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Phân trang" hoạt động ổn định trên hệ thống.`
- **Thao tác - Đăng ký/Hóa đơn**
  - *Mô tả / Tham số:* Mở đúng booking/phòng được chọn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Thao tác - Đăng ký/Hóa đơn" hoạt động ổn định trên hệ thống.`
- **Thao tác - Thông tin khách/Đồ thất lạc**
  - *Mô tả / Tham số:* Mở đúng ngữ cảnh khách/phòng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Thao tác - Thông tin khách/Đồ thất lạc" hoạt động ổn định trên hệ thống.`
- **Thao tác - In/Xuất Excel**
  - *Mô tả / Tham số:* In xác nhận, đăng ký lưu trú, phiếu ăn sáng và xuất đúng kết quả.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Thao tác - In/Xuất Excel" hoạt động ổn định trên hệ thống.`
- **Nhân bản từ tìm kiếm**
  - *Mô tả / Tham số:* Nhân bản đúng booking được chọn, không lẫn dòng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Nhân bản từ tìm kiếm" hoạt động ổn định trên hệ thống.`
- **No-show một ngày/giai đoạn**
  - *Mô tả / Tham số:* Áp dụng ngày, lý do, phạt và quyền theo booking.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "No-show một ngày/giai đoạn" hoạt động ổn định trên hệ thống.`
- **Nhận phòng từ tìm kiếm**
  - *Mô tả / Tham số:* Chặn phòng không hợp lệ và cập nhật trạng thái đồng bộ.
  - *Đánh giá cá nhân:* `Hoàn thành nhưng lưu ý (PMS note Dòng 257): Bảng booking_rooms, booking_room_guest chưa ghi nhận giờ check-in thực tế của phòng.`

## Module: Lịch sử thao tác (6 mục)

- **Mở lịch sử thao tác**
  - *Mô tả / Tham số:* Có bộ lọc ngày, đăng ký, phòng, hành động, người/bộ phận.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Mở lịch sử thao tác" hoạt động ổn định trên hệ thống.`
- **Lọc thời gian**
  - *Mô tả / Tham số:* Tất cả/Hôm nay/Hôm qua/7 ngày/Tháng này và khoảng tùy chọn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lọc thời gian" hoạt động ổn định trên hệ thống.`
- **Lọc hành động**
  - *Mô tả / Tham số:* Tạo/sửa/check-in/check-out/hủy/noshow/khóa/mở/thanh toán/cọc/dịch vụ/sang ngày.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lọc hành động" hoạt động ổn định trên hệ thống.`
- **Lọc mã booking/phòng/người dùng**
  - *Mô tả / Tham số:* Kết quả đúng điều kiện và chi nhánh.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lọc mã booking/phòng/người dùng" hoạt động ổn định trên hệ thống.`
- **Tìm nội dung/Hủy lọc**
  - *Mô tả / Tham số:* Tra cứu từ khóa và khôi phục kết quả.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Tìm nội dung/Hủy lọc" hoạt động ổn định trên hệ thống.`
- **Xuất Excel lịch sử**
  - *Mô tả / Tham số:* Xuất đúng dữ liệu lọc, người, thời gian và thông tin thay đổi.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Xuất Excel lịch sử" hoạt động ổn định trên hệ thống.`

## Module: Công ty và kênh bán (11 mục)

- **Mở danh mục công ty**
  - *Mô tả / Tham số:* Hiển thị công ty, market, source, chi nhánh, người đặt phòng.
  - *Đánh giá cá nhân:* `Hoàn thành nhưng còn lỗi (PMS note Dòng 11, Dòng 56-57 FIX FE): Chưa có cột STT và cờ isuse; form thêm công ty chưa thể đưa dropdown về trạng thái chưa chọn.`
- **Thêm/sửa công ty**
  - *Mô tả / Tham số:* Mã, tên, địa chỉ, MST, liên hệ, trạng thái hợp lệ; không trùng mã.
  - *Đánh giá cá nhân:* `Hoàn thành nhưng còn lỗi (PMS note Dòng 11, Dòng 56-57 FIX FE): Chưa có cột STT và cờ isuse; form thêm công ty chưa thể đưa dropdown về trạng thái chưa chọn.`
- **Xóa công ty**
  - *Mô tả / Tham số:* Chặn khi bị ràng buộc booking/dữ liệu nghiệp vụ.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Xóa công ty" hoạt động ổn định trên hệ thống.`
- **Đồng bộ công ty**
  - *Mô tả / Tham số:* Đồng bộ nguồn dữ liệu, không nhân bản mã hoặc mất liên kết.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Đồng bộ công ty" hoạt động ổn định trên hệ thống.`
- **Tìm/lọc/ẩn cột/phân trang**
  - *Mô tả / Tham số:* Áp dụng đúng dữ liệu công ty trên bảng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Tìm/lọc/ẩn cột/phân trang" hoạt động ổn định trên hệ thống.`
- **Nhập Excel/Tải mẫu**
  - *Mô tả / Tham số:* Đúng cấu trúc, kiểm tra dữ liệu thiếu/trùng và báo kết quả từng dòng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Nhập Excel/Tải mẫu" hoạt động ổn định trên hệ thống.`
- **Xuất Excel công ty**
  - *Mô tả / Tham số:* File đủ trường và đúng danh sách.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Xuất Excel công ty" hoạt động ổn định trên hệ thống.`
- **Thị trường**
  - *Mô tả / Tham số:* Thêm/sửa/xóa market theo quyền, sử dụng được ở booking.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Thị trường" hoạt động ổn định trên hệ thống.`
- **Nguồn khách**
  - *Mô tả / Tham số:* Thêm/sửa/xóa source theo quyền, sử dụng được ở booking.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Nguồn khách" hoạt động ổn định trên hệ thống.`
- **Chi nhánh**
  - *Mô tả / Tham số:* Hiển thị cấu hình chi nhánh và phạm vi được phép.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Chi nhánh" hoạt động ổn định trên hệ thống.`
- **Người đặt phòng**
  - *Mô tả / Tham số:* Thêm/sửa/xóa thông tin liên hệ, chọn được từ booking.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Người đặt phòng" hoạt động ổn định trên hệ thống.`

## Module: Allotment và Channel Manager (6 mục)

- **Danh sách Allotment**
  - *Mô tả / Tham số:* Dữ liệu đối tác/loại phòng, phân bổ/đã bán/còn lại từ dữ liệu thực.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Danh sách Allotment" hoạt động ổn định trên hệ thống.`
- **Tạo Allotment mới**
  - *Mô tả / Tham số:* Tạo giai đoạn/loại phòng/số lượng/đối tác và lưu phân bổ thực.
  - *Đánh giá cá nhân:* `Chưa đạt — Đang phát triển: Nút "+ Tạo Allotment Mới" báo tính năng đang phát triển, dữ liệu mock (PMS-01).`
- **Chi tiết Allotment**
  - *Mô tả / Tham số:* Mã ALM, đối tác, phòng, ngày bắt đầu/kết thúc và trạng thái từ dữ liệu thực.
  - *Đánh giá cá nhân:* `Chưa đạt — Đang phát triển: Nút "+ Tạo Allotment Mới" báo tính năng đang phát triển, dữ liệu mock (PMS-01).`
- **Báo cáo phân bổ Allotment**
  - *Mô tả / Tham số:* Tổng phân bổ/chiếm dụng/doanh thu và hiệu suất kênh tính từ dữ liệu thực.
  - *Đánh giá cá nhân:* `Chưa đạt — Đang phát triển: Nút "+ Tạo Allotment Mới" báo tính năng đang phát triển, dữ liệu mock (PMS-01).`
- **Danh sách Channel Manager**
  - *Mô tả / Tham số:* Thể hiện kết nối OTA, phòng trống, giá, lần đồng bộ và trạng thái thực.
  - *Đánh giá cá nhân:* `Chưa đạt — Đang phát triển: Nút "Đồng bộ ngay" báo tính năng đang phát triển, chưa kết nối OTA (PMS-02).`
- **Đồng bộ ngay**
  - *Mô tả / Tham số:* Gửi/nhận availability, giá và booking qua kết nối đã cấu hình.
  - *Đánh giá cá nhân:* `Chưa đạt — Đang phát triển: Nút "Đồng bộ ngay" báo tính năng đang phát triển, chưa kết nối OTA (PMS-02).`

## Module: Tài khoản và quyền bộ phận (10 mục)

- **Đăng nhập**
  - *Mô tả / Tham số:* Tài khoản hợp lệ vào được PMS; kiểm tra thông báo khi sai/thiếu dữ liệu.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Đăng nhập" hoạt động ổn định trên hệ thống.`
- **Đăng xuất/phiên hết hạn**
  - *Mô tả / Tham số:* Hủy phiên và trở về login, không giữ quyền cũ.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Đăng xuất/phiên hết hạn" hoạt động ổn định trên hệ thống.`
- **Truy cập module**
  - *Mô tả / Tham số:* Tài khoản bộ phận chỉ vào module/màn hình được phân quyền.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Truy cập module" hoạt động ổn định trên hệ thống.`
- **View/Add/Edit/Delete**
  - *Mô tả / Tham số:* Kiểm tra cả UI và API, không chỉ ẩn nút trên giao diện.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "View/Add/Edit/Delete" hoạt động ổn định trên hệ thống.`
- **Phạm vi chi nhánh**
  - *Mô tả / Tham số:* Dữ liệu và quyền áp dụng đúng chi nhánh được chọn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Phạm vi chi nhánh" hoạt động ổn định trên hệ thống.`
- **Thao tác ngày cũ**
  - *Mô tả / Tham số:* Bill/thanh toán ngày cũ chỉ được xử lý khi có quyền/cấu hình.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Thao tác ngày cũ" hoạt động ổn định trên hệ thống.`
- **Xác minh mật khẩu**
  - *Mô tả / Tham số:* Các thao tác cần xác minh chặn mật khẩu sai và không tiết lộ dữ liệu.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Xác minh mật khẩu" hoạt động ổn định trên hệ thống.`
- **Ngôn ngữ Việt/Anh**
  - *Mô tả / Tham số:* Nhãn, ngày, tiền và thông báo đúng ngôn ngữ trên các màn hình.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Ngôn ngữ Việt/Anh" hoạt động ổn định trên hệ thống.`
- **Modal và ngày/số**
  - *Mô tả / Tham số:* Esc đóng, trường bắt buộc rõ, ngày/số được format hợp lệ và thao tác không bị che.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Modal và ngày/số" hoạt động ổn định trên hệ thống.`
- **Đăng nhập tài khoản hợp lệ**
  - *Mô tả / Tham số:* Đăng nhập testuser vào PMS local, mở module theo quyền hiện có.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Đăng nhập tài khoản hợp lệ" hoạt động ổn định trên hệ thống.`

## Module: Báo cáo / Báo cáo công nợ (2 mục)

- **Báo cáo công nợ công ty**
  - *Mô tả / Tham số:* Công nợ công ty theo ngày, công ty và chi tiết giải trừ tùy chọn. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Từ ngày, Đến ngày, Chọn công ty, Nhóm theo ngày, Hiển thị chi tiết giải trừ, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo công nợ đã thanh toán**
  - *Mô tả / Tham số:* Báo cáo chi tiết các khoản nợ công ty đã thanh toán, đối trừ và số dư còn lại. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Từ ngày, Đến ngày, Xem theo, Chọn công ty, Nhóm theo công ty, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`

## Module: Báo cáo / Báo cáo công suất (2 mục)

- **Báo cáo chi tiết công suất công ty**
  - *Mô tả / Tham số:* Chi tiết công suất và doanh thu theo từng booking. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Từ ngày, Đến ngày, Khu vực, Công ty, Thị trường, Người bán, Nguồn khách, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo công suất công ty**
  - *Mô tả / Tham số:* Tổng hợp 11 cột công suất công ty; chi tiết nằm ở báo cáo Dòng 166. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Từ ngày, Đến ngày, Khu vực, Công ty, Thị trường, Người bán, Nguồn khách, Nhóm theo, DT phòng bao gồm ăn sáng, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`

## Module: Báo cáo / Báo cáo dịch vụ (9 mục)

- **Báo cáo hóa đơn giặt ủi**
  - *Mô tả / Tham số:* Danh sách hóa đơn dịch vụ LA theo logic legacy sp_125/sp_202. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày, Đến ngày, Ca làm việc, Chọn bộ phận, Chọn người dùng, Loại dữ liệu, Sắp xếp theo, Thứ tự, Xem chi tiết, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo hóa đơn hàng bể vỡ**
  - *Mô tả / Tham số:* Danh sách hóa đơn dịch vụ BR theo logic legacy sp_125/sp_202. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày, Đến ngày, Ca làm việc, Chọn bộ phận, Chọn người dùng, Loại dữ liệu, Sắp xếp theo, Thứ tự, Xem chi tiết, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo hóa đơn giặt ủi theo sản phẩm**
  - *Mô tả / Tham số:* Tổng hợp sản phẩm giặt ủi theo sp_206 legacy. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày, Đến ngày, Ca làm việc, Chọn bộ phận, Chọn người dùng, Hàng bán, Nhóm theo ngày, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo hóa đơn minibar**
  - *Mô tả / Tham số:* Danh sách hóa đơn dịch vụ MB theo logic legacy sp_125/sp_202. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày, Đến ngày, Ca làm việc, Chọn bộ phận, Chọn người dùng, Loại dữ liệu, Sắp xếp theo, Thứ tự, Xem chi tiết, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo hóa đơn giặt ủi miễn phí**
  - *Mô tả / Tham số:* Danh sách hóa đơn dịch vụ LA theo logic legacy sp_125/sp_202. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày, Đến ngày, Chọn người dùng, Sắp xếp theo, Thứ tự, Detail, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo hóa đơn minibar (theo sản phẩm)**
  - *Mô tả / Tham số:* Tổng hợp sản phẩm minibar theo sp_206 legacy. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày, Đến ngày, Ca làm việc, Chọn bộ phận, Chọn người dùng, Hàng bán, Nhóm theo ngày, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo hóa đơn hàng bể vỡ theo sản phẩm**
  - *Mô tả / Tham số:* Tổng hợp sản phẩm hàng bể vỡ theo sp_206 legacy. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày, Đến ngày, Ca làm việc, Chọn bộ phận, Chọn người dùng, Hàng bán, Nhóm theo ngày, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo hóa đơn hàng bể vỡ miễn phí**
  - *Mô tả / Tham số:* Danh sách hóa đơn dịch vụ BR theo logic legacy sp_125/sp_202. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày, Đến ngày, Chọn người dùng, Sắp xếp theo, Thứ tự, Xem chi tiết, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo hóa đơn minibar miễn phí**
  - *Mô tả / Tham số:* Danh sách hóa đơn dịch vụ MB theo logic legacy sp_125/sp_202. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày, Đến ngày, Chọn người dùng, Sắp xếp theo, Thứ tự, Detail, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`

## Module: Báo cáo / Báo cáo doanh thu (7 mục)

- **Báo cáo doanh thu**
  - *Mô tả / Tham số:* Báo cáo doanh thu theo ngày theo legacy sp_292 Army. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày, Công ty, Đăng ký, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo doanh thu đăng ký theo ngày đi**
  - *Mô tả / Tham số:* Báo cáo doanh thu đăng ký theo ngày đi theo legacy sp_238 và sp_240. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Từ ngày, Đến ngày, Từ giờ, Đến giờ, Công ty, Nhóm theo đăng ký, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo hóa đơn dịch vụ tổng hợp**
  - *Mô tả / Tham số:* Dịch vụ phát sinh theo ngày, nhóm theo mã dịch vụ và nhóm doanh thu Navy. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Từ ngày, Đến ngày, Bộ phận, Dịch vụ, Người dùng, Sắp xếp theo, Hiển thị đã xóa, Nhóm theo dịch vụ, Nhóm theo ngày, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo tổng hợp ngày**
  - *Mô tả / Tham số:* Tổng hợp ngày theo doanh thu, hoạt động phòng, OCC và ADR. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày báo cáo, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo doanh thu hai giai đoạn**
  - *Mô tả / Tham số:* Dịch vụ có tháng phát sinh khác tháng thanh toán hóa đơn, theo legacy Army sp_217. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày, Đến ngày, Bộ phận, Outlet, Dịch vụ, Chọn người dùng, Sắp xếp theo, Thứ tự, Nhóm theo ngày, Đăng ký, Chế độ ngày thanh toán, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo tổng hợp doanh thu theo người bán**
  - *Mô tả / Tham số:* Tổng hợp doanh thu theo người bán, hỗ trợ Army arrival và Navy room-night. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Từ ngày, Đến ngày, Chế độ lọc, Người bán, Thị trường, Công ty, Nhóm theo, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo chi tiết doanh thu theo người bán**
  - *Mô tả / Tham số:* Chi tiết doanh thu từng booking theo người bán, hỗ trợ Army arrival và Navy room-night. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Từ ngày, Đến ngày, Chế độ lọc, Người bán, Thị trường, Công ty, Nhóm theo, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`

## Module: Báo cáo / Báo cáo hủy phòng (2 mục)

- **Báo cáo hủy đăng ký**
  - *Mô tả / Tham số:* Booking và phòng bị hủy theo legacy sp_284/sp_261. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Chọn ngày, Đến ngày, Hiển thị thông tin phòng, Loại ngày, Đăng ký, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo phòng hủy**
  - *Mô tả / Tham số:* Danh sách phòng hủy theo sp_261/vw_034. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày, Đến ngày, Loại ngày, Booking, Chi nhánh, Nhóm theo lý do hủy phòng, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`

## Module: Báo cáo / Báo cáo khách (4 mục)

- **Báo cáo danh sách khách ở**
  - *Mô tả / Tham số:* Danh sách khách theo từng ngày lưu trú và tổng hợp quốc tịch/giới tính. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày, Đến ngày, Công ty/Đại lý, Lọc theo đăng ký, Cách xem, Hiển thị đăng ký, Hiển thị ghi chú, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo đưa đón khách**
  - *Mô tả / Tham số:* Tổng hợp toàn bộ lịch đưa đón khách theo ngày. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày, Đến ngày, Nhóm theo ngày, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo khách quay lại**
  - *Mô tả / Tham số:* Danh sách khách quay lại theo Passport và giai đoạn lưu trú. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày ở, Đến ngày, Lọc đăng ký theo giai đoạn ở, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo khách VIP**
  - *Mô tả / Tham số:* Danh sách khách VIP theo loại khách và thời gian lưu trú. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày, Đến ngày, Loại khách, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`

## Module: Báo cáo / Báo cáo phòng (19 mục)

- **Báo cáo phòng đến**
  - *Mô tả / Tham số:* Danh sách khách/phòng có ngày đến trong khoảng được chọn. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Chọn ngày, Đến ngày, Chọn loại phòng, Chọn tình trạng đăng ký, Chọn khu vực, Công ty / lữ hành, Đăng ký phòng, Chỉ hiển thị khách chính, Hiển thị giá phòng, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo phòng ở**
  - *Mô tả / Tham số:* Danh sách phòng/khách đang ở theo ngày, chuyển đổi từ legacy sp_167. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Chọn ngày, Đến ngày, Thực tế, Chọn loại phòng, Chọn tình trạng đăng ký, Chọn khu vực, Chọn công ty, Chọn đăng ký, Hiển thị khách chính, Hiển thị chi tiết, Giá phòng, VAT, Không VAT, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo danh sách khách đã nhận phòng**
  - *Mô tả / Tham số:* Danh sách khách tại các phòng đã nhận phòng, theo ngày nhận phòng thực tế. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Chọn ngày, Đến ngày, Sắp xếp theo, Lọc theo công ty, Lọc theo đăng ký, Hiển thị ghi chú, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo phòng miễn phí**
  - *Mô tả / Tham số:* Phòng có giá phòng miễn phí theo legacy sp_048. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày, Đến ngày, Mã giá phòng, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo phòng No-show**
  - *Mô tả / Tham số:* Danh sách phòng không đến theo sp_054. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày, Đến ngày, Loại tiền, Người dùng, Loại tiền legacy, Sắp xếp theo ngày No-show, Chi nhánh, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo phòng No Show theo ngày**
  - *Mô tả / Tham số:* Danh sách phòng No Show theo ngày theo ProVista sp_056. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày, Đến ngày, Loại tiền, Người dùng, Loại tiền legacy, Sắp xếp theo ngày Check-in, Chọn đăng ký, Chi nhánh, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo phòng hàng tuần**
  - *Mô tả / Tham số:* Tổng hợp Đến, Đi, Ở và Công suất theo 7 ngày trong tuần. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Tuần bắt đầu, Tuần kết thúc, Chi nhánh, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo phòng đi**
  - *Mô tả / Tham số:* Danh sách khách/phòng có ngày đi trong khoảng được chọn. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Chọn ngày, Đến ngày, Chọn loại phòng, Chọn tình trạng đăng ký, Chọn khu vực, Chọn công ty, Chọn đăng ký, Chỉ hiển thị khách chính, Room Rate, Services Amount, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Danh sách phòng Due In**
  - *Mô tả / Tham số:* Danh sách phòng có khách đến theo legacy sp_006. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Chọn ngày, Đến ngày, Chọn khu vực, Chọn công ty, Chọn đăng ký, Chọn loại phòng, Chọn tình trạng đăng ký, Chỉ hiển thị khách chính, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Danh sách phòng Due Out**
  - *Mô tả / Tham số:* Danh sách phòng có ngày đi trong khoảng chọn theo legacy sp_008. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Chọn ngày, Đến ngày, Chọn loại phòng, Chọn tình trạng đăng ký, Chọn khu vực, Chọn công ty, Chọn đăng ký, Chỉ hiển thị khách chính, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo chuyển phòng**
  - *Mô tả / Tham số:* Danh sách phòng chuyển theo ngày chuyển và người thực hiện. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Từ ngày, Đến ngày, Người thực hiện, Sắp xếp theo, Thứ tự, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo thay đổi tình trạng phòng**
  - *Mô tả / Tham số:* Lịch sử thao tác thay đổi tình trạng phòng theo sp_288. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Từ ngày, Đến ngày, Người dùng, Số phòng, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo phòng checkout sớm**
  - *Mô tả / Tham số:* Thống kê các phòng checkout sớm theo ngày checkout thực tế. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày checkout, Đến ngày, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo dịch vụ bổ sung**
  - *Mô tả / Tham số:* Danh sách dịch vụ bổ sung theo ngày dịch vụ và loại dịch vụ. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày, Đến ngày, Dịch vụ, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo Extra Bed**
  - *Mô tả / Tham số:* Thống kê số lượng và doanh thu Extra Bed theo từng ngày. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Từ ngày, Đến ngày, Đăng ký, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo danh sách khách đã trả phòng**
  - *Mô tả / Tham số:* Danh sách khách đã trả phòng theo legacy sp_149. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Từ ngày, Đến ngày, Sắp xếp theo, Lọc theo công ty, Lọc theo đăng ký, Hiển thị ghi chú, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo phòng ở trong ngày (Day Use)**
  - *Mô tả / Tham số:* Danh sách phòng Day Use theo legacy sp_132. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày, Đến ngày, Người dùng, Sắp xếp theo, Thứ tự, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo dự kiến khách ăn sáng**
  - *Mô tả / Tham số:* Hai mẫu tổng hợp và chi tiết theo legacy sp_035/sp_032. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày, Đến ngày, Xem theo, Người dùng, Sắp xếp theo, Thứ tự, Nhận phòng trễ, Hiển thị thông tin phòng, Đăng ký theo nhóm, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo yêu cầu đặc biệt**
  - *Mô tả / Tham số:* Danh sách các phòng có yêu cầu đặc biệt theo đăng ký và khoảng thời gian. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Chọn ngày, Đến ngày, Xem theo ngày, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`

## Module: Báo cáo / Báo cáo phòng khóa (2 mục)

- **Báo cáo lịch sử khóa phòng OOO**
  - *Mô tả / Tham số:* Lịch sử khóa và mở khóa phòng OOO theo sp_057. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày, Đến ngày, Người dùng, Sắp xếp theo, Thứ tự, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo lịch sử khóa phòng OOS**
  - *Mô tả / Tham số:* Lịch sử khóa và mở khóa phòng OOS theo sp_059. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày, Đến ngày, Người dùng, Sắp xếp theo, Thứ tự, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`

## Module: Báo cáo / Báo cáo quản lý / Sang ngày (1 mục)

- **Báo cáo dự kiến doanh thu tiền phòng**
  - *Mô tả / Tham số:* Xem khoản tiền phòng và dịch vụ dự kiến hoặc đã post trong ngày trước khi chạy Sang Ngày. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày báo cáo, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`

## Module: Báo cáo / Báo cáo thống kê (1 mục)

- **Báo cáo thống kê mã giá phòng**
  - *Mô tả / Tham số:* Báo cáo thống kê lượt phòng theo mã giá phòng theo sp_287 legacy. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Chọn ngày, Đến ngày, Mã giá phòng, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`

## Module: Báo cáo / Báo cáo thống kê lễ tân (5 mục)

- **Báo cáo đặt cọc (Module Sale)**
  - *Mô tả / Tham số:* Báo cáo đặt cọc theo ngày payment.date, bộ phận, user, ca, công ty và phương thức thanh toán. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày, Đến ngày, Chọn bộ phận, Chọn người dùng, Ca làm việc, Từ giờ, Đến giờ, Chọn công ty, Hiển thị tiền cọc, Hiển thị tiền = 0, Phương thức thanh toán, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo hóa đơn bán hàng**
  - *Mô tả / Tham số:* Báo cáo chi tiết hóa đơn bán hàng theo ngày, khách, công ty và hình thức thanh toán theo sp_094 legacy. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Chọn ngày, Đến ngày, Chọn bộ phận, Chọn công ty, Chọn người dùng, Xem Theo, Sắp xếp theo, Thứ tự sắp xếp, p_outlet, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo tổng doanh thu**
  - *Mô tả / Tham số:* Bảng kê chi tiết doanh thu theo booking, thanh toán và số dư phòng còn ở theo Sheet 72/sp_292. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày, Công ty, Đăng ký, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo hủy hóa đơn/thanh toán**
  - *Mô tả / Tham số:* Báo cáo hủy hóa đơn và hủy thanh toán theo legacy sp_068/sp_070. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Chế độ xem, Ngày, Đến ngày, Bộ phận, Outlet, Dịch vụ, Người dùng, Sắp xếp theo, Thứ tự, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo lễ tân hằng ngày**
  - *Mô tả / Tham số:* Báo cáo phòng và suất ăn sáng theo 4 phân khúc khách hàng theo legacy sp_275. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Chọn ngày, Đến ngày, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`

## Module: Báo cáo / Báo cáo thu ngân (2 mục)

- **Báo cáo thu ngân lễ tân**
  - *Mô tả / Tham số:* Báo cáo thu ngân lễ tân theo legacy sp_039 Navy. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày, Đến ngày, Bộ phận, Người dùng, Ca làm việc, Từ giờ, Đến giờ, Công ty, Hiển thị đặt cọc, Hiển thị tiền = 0, Phương thức thanh toán, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo tiền đặt cọc**
  - *Mô tả / Tham số:* Tiền đặt cọc và từng lần cấn trừ thực tế. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày, Đến ngày, Điều kiện lọc cọc, Ca làm việc, Bộ phận, Outlet, Công ty, Người dùng, Sắp xếp theo, Thứ tự, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`

# LỄ TÂN (FRONT OFFICE) (34 Modules — 360 Mục)

## Module: Sơ đồ phòng (28 mục)

- **Mở sơ đồ phòng**
  - *Mô tả / Tham số:* Hiển thị danh sách phòng theo tầng, số phòng, loại phòng và tình trạng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Mở sơ đồ phòng" hoạt động ổn định trên hệ thống.`
- **Đổi ngày xem**
  - *Mô tả / Tham số:* Số liệu phòng, khách và booking phản ánh ngày nghiệp vụ được chọn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Đổi ngày xem" hoạt động ổn định trên hệ thống.`
- **Đã đến**
  - *Mô tả / Tham số:* Mở danh sách phòng đến/đã nhận phòng theo ngày.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Đã đến" hoạt động ổn định trên hệ thống.`
- **Đã đi**
  - *Mô tả / Tham số:* Mở danh sách phòng đi/đã trả phòng theo ngày.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Đã đi" hoạt động ổn định trên hệ thống.`
- **Đang ở**
  - *Mô tả / Tham số:* Mở danh sách phòng khách đang lưu trú.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Đang ở" hoạt động ổn định trên hệ thống.`
- **Khóa OOO**
  - *Mô tả / Tham số:* Hiển thị đúng phòng bị khóa OOO và số lượng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Khóa OOO" hoạt động ổn định trên hệ thống.`
- **Khóa OOS**
  - *Mô tả / Tham số:* Hiển thị đúng phòng bị khóa OOS và số lượng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Khóa OOS" hoạt động ổn định trên hệ thống.`
- **Công suất**
  - *Mô tả / Tham số:* Mở thống kê và đối chiếu tỷ lệ chiếm dụng với số phòng có thể bán.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Công suất" hoạt động ổn định trên hệ thống.`
- **Xem danh sách**
  - *Mô tả / Tham số:* Chuyển từ sơ đồ sang bảng có khách, booking, ngày đến/đi.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Xem danh sách" hoạt động ổn định trên hệ thống.`
- **Xem sơ đồ lưới**
  - *Mô tả / Tham số:* Chuyển về thẻ phòng theo tầng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Xem sơ đồ lưới" hoạt động ổn định trên hệ thống.`
- **Thu nhỏ/phóng to/Auto Fit**
  - *Mô tả / Tham số:* Thay đổi tỷ lệ xem, không mất phòng hoặc che chức năng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Thu nhỏ/phóng to/Auto Fit" hoạt động ổn định trên hệ thống.`
- **Bật/tắt bộ lọc**
  - *Mô tả / Tham số:* Mở, đóng panel bộ lọc.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Bật/tắt bộ lọc" hoạt động ổn định trên hệ thống.`
- **Lọc theo tầng**
  - *Mô tả / Tham số:* Chỉ hiển thị phòng thuộc tầng đã chọn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lọc theo tầng" hoạt động ổn định trên hệ thống.`
- **Lọc theo loại phòng**
  - *Mô tả / Tham số:* Chỉ hiển thị loại phòng đã chọn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lọc theo loại phòng" hoạt động ổn định trên hệ thống.`
- **Lọc mã đăng ký/tên khách**
  - *Mô tả / Tham số:* Trả về phòng phù hợp booking hoặc tên khách.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lọc mã đăng ký/tên khách" hoạt động ổn định trên hệ thống.`
- **Lọc tình trạng đăng ký**
  - *Mô tả / Tham số:* Áp dụng tình trạng đăng ký được cấu hình.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lọc tình trạng đăng ký" hoạt động ổn định trên hệ thống.`
- **Lọc lưu trú**
  - *Mô tả / Tham số:* Phân biệt trống, đang ở, đến, đi, OOO.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lọc lưu trú" hoạt động ổn định trên hệ thống.`
- **Lọc tình trạng buồng phòng**
  - *Mô tả / Tham số:* Phân biệt sạch, bẩn, chờ kiểm tra và sẵn sàng theo quyền.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lọc tình trạng buồng phòng" hoạt động ổn định trên hệ thống.`
- **Xóa bộ lọc**
  - *Mô tả / Tham số:* Khôi phục toàn bộ danh sách và số lượng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Xóa bộ lọc" hoạt động ổn định trên hệ thống.`
- **Lọc tại tiêu đề cột**
  - *Mô tả / Tham số:* Bộ lọc số phòng, tên khách, mã/tên đăng ký, ngày đến/đi hoạt động độc lập.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lọc tại tiêu đề cột" hoạt động ổn định trên hệ thống.`
- **Chọn nhiều/chọn tất cả phòng**
  - *Mô tả / Tham số:* Chỉ chọn phòng đang hiển thị; bỏ chọn không tác động dữ liệu.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Chọn nhiều/chọn tất cả phòng" hoạt động ổn định trên hệ thống.`
- **Cài đặt hiển thị**
  - *Mô tả / Tham số:* Mở hộp kích thước icon/chữ/phòng, hướng tầng, vị trí chính xác.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Cài đặt hiển thị" hoạt động ổn định trên hệ thống.`
- **Lưu/khôi phục cài đặt**
  - *Mô tả / Tham số:* Thiết lập được giữ theo tài khoản; Mặc định khôi phục đúng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lưu/khôi phục cài đặt" hoạt động ổn định trên hệ thống.`
- **Chi tiết phòng**
  - *Mô tả / Tham số:* Mở thông tin phòng/khách/booking, dịch vụ và lịch sử tương ứng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Chi tiết phòng" hoạt động ổn định trên hệ thống.`
- **Menu chuột phải**
  - *Mô tả / Tham số:* Hiển thị tác vụ phù hợp tình trạng phòng và quyền của bộ phận.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Menu chuột phải" hoạt động ổn định trên hệ thống.`
- **Biểu tượng nghiệp vụ**
  - *Mô tả / Tham số:* Thể hiện extra bed, khách VIP, yêu cầu đặc biệt, sinh nhật, honeymoon, DND.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Biểu tượng nghiệp vụ" hoạt động ổn định trên hệ thống.`
- **Phòng thông nhau**
  - *Mô tả / Tham số:* Hiển thị đúng các cặp phòng connecting và cửa kết nối.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Phòng thông nhau" hoạt động ổn định trên hệ thống.`
- **Cập nhật thời gian thực**
  - *Mô tả / Tham số:* Dữ liệu thay đổi ở module khác cập nhật qua websocket/refresh.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Cập nhật thời gian thực" hoạt động ổn định trên hệ thống.`

## Module: Phòng trống (15 mục)

- **Mở bảng phòng trống**
  - *Mô tả / Tham số:* Có dữ liệu loại phòng, ngày, tổng số phòng và thống kê.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Mở bảng phòng trống" hoạt động ổn định trên hệ thống.`
- **Chọn phạm vi ngày**
  - *Mô tả / Tham số:* Hôm nay/mai, tuần/tháng hiện tại, tiếp theo, trước và tùy chỉnh trả đúng khoảng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Chọn phạm vi ngày" hoạt động ổn định trên hệ thống.`
- **Áp dụng phạm vi ngày**
  - *Mô tả / Tham số:* Tải lại ma trận theo khoảng đã chọn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Áp dụng phạm vi ngày" hoạt động ổn định trên hệ thống.`
- **AV**
  - *Mô tả / Tham số:* Xem số phòng trống theo loại phòng và ngày.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "AV" hoạt động ổn định trên hệ thống.`
- **OCC**
  - *Mô tả / Tham số:* Xem số phòng chiếm dụng và công suất.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "OCC" hoạt động ổn định trên hệ thống.`
- **ALM**
  - *Mô tả / Tham số:* Xem số phòng allotment.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "ALM" hoạt động ổn định trên hệ thống.`
- **OOO**
  - *Mô tả / Tham số:* Xem phòng khóa không bán theo ngày.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "OOO" hoạt động ổn định trên hệ thống.`
- **OOS**
  - *Mô tả / Tham số:* Xem phòng khóa OOS theo ngày.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "OOS" hoạt động ổn định trên hệ thống.`
- **EB**
  - *Mô tả / Tham số:* Xem tổng extra bed theo ngày.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "EB" hoạt động ổn định trên hệ thống.`
- **BBC**
  - *Mô tả / Tham số:* Xem số liệu ăn sáng trẻ em theo ngày.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "BBC" hoạt động ổn định trên hệ thống.`
- **Mở rộng loại phòng**
  - *Mô tả / Tham số:* Hiển thị dữ liệu dạng phòng chi tiết dưới loại phòng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Mở rộng loại phòng" hoạt động ổn định trên hệ thống.`
- **Chi tiết ô ma trận**
  - *Mô tả / Tham số:* Mở các booking/phòng cấu thành chỉ tiêu của ô được chọn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Chi tiết ô ma trận" hoạt động ổn định trên hệ thống.`
- **Thống kê**
  - *Mô tả / Tham số:* Đối chiếu Series, Allotment, đảm bảo/không đảm bảo, phòng đến, khách, hủy, noshow.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Thống kê" hoạt động ổn định trên hệ thống.`
- **Giữ phạm vi xem**
  - *Mô tả / Tham số:* Đổi tab rồi quay lại giữ khoảng ngày; reload áp dụng mặc định hệ thống.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Giữ phạm vi xem" hoạt động ổn định trên hệ thống.`
- **Xuất Excel**
  - *Mô tả / Tham số:* Tải file đúng số liệu, loại phòng và phạm vi đang xem.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Xuất Excel" hoạt động ổn định trên hệ thống.`

## Module: Kế hoạch phòng (19 mục)

- **Mở kế hoạch phòng**
  - *Mô tả / Tham số:* Hiển thị lịch phòng theo khoảng ngày và hàng số phòng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Mở kế hoạch phòng" hoạt động ổn định trên hệ thống.`
- **Chọn khoảng ngày**
  - *Mô tả / Tham số:* Lịch hiển thị chính xác bắt đầu/kết thúc và ngày nghiệp vụ.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Chọn khoảng ngày" hoạt động ổn định trên hệ thống.`
- **Đổi View**
  - *Mô tả / Tham số:* Đổi cách hiển thị lịch mà không làm mất booking.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Đổi View" hoạt động ổn định trên hệ thống.`
- **Sắp xếp**
  - *Mô tả / Tham số:* Sắp xếp số phòng/loại phòng theo tùy chọn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Sắp xếp" hoạt động ổn định trên hệ thống.`
- **Tìm kiếm**
  - *Mô tả / Tham số:* Lọc khách hàng, mã đăng ký và phòng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Tìm kiếm" hoạt động ổn định trên hệ thống.`
- **Bộ lọc**
  - *Mô tả / Tham số:* Lọc loại/dạng phòng, tình trạng; Xóa lọc khôi phục.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Bộ lọc" hoạt động ổn định trên hệ thống.`
- **Mở booking từ lịch**
  - *Mô tả / Tham số:* Mở đúng booking và thông tin phòng trên thanh lịch.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Mở booking từ lịch" hoạt động ổn định trên hệ thống.`
- **Đặt phòng nhanh**
  - *Mô tả / Tham số:* Chọn phòng/giai đoạn rồi nhập tên BK, market, source, tình trạng và lưu.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Đặt phòng nhanh" hoạt động ổn định trên hệ thống.`
- **Đặt phòng nhanh - dữ liệu thiếu**
  - *Mô tả / Tham số:* Chặn lưu khi thiếu tên đăng ký, thị trường, nguồn khách hoặc tình trạng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Đặt phòng nhanh - dữ liệu thiếu" hoạt động ổn định trên hệ thống.`
- **Giao phòng**
  - *Mô tả / Tham số:* Gán đúng số phòng cho booking và cập nhật ma trận AV.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Giao phòng" hoạt động ổn định trên hệ thống.`
- **Tách phòng**
  - *Mô tả / Tham số:* Chia giai đoạn theo ngày tách, giữ đúng tổng đêm và dịch vụ.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Tách phòng" hoạt động ổn định trên hệ thống.`
- **Chuyển phòng trên lịch**
  - *Mô tả / Tham số:* Chỉ chuyển khi phòng nhận phù hợp giai đoạn và điều kiện phòng.
  - *Đánh giá cá nhân:* `Hoàn thành nhưng còn lỗi (PMS note Dòng 305, 306, 530): Cần kiểm tra chuyển phòng sau khi ở qua đêm / ngày checkout; cấu hình AllowOverRoomTypeRoomKind=1 khi Suite=0 chưa cảnh báo đúng.`
- **Khóa/mở chuyển phòng**
  - *Mô tả / Tham số:* Tôn trọng khóa di chuyển và quyền thao tác.
  - *Đánh giá cá nhân:* `Hoàn thành nhưng còn lỗi (PMS note Dòng 305, 306, 530): Cần kiểm tra chuyển phòng sau khi ở qua đêm / ngày checkout; cấu hình AllowOverRoomTypeRoomKind=1 khi Suite=0 chưa cảnh báo đúng.`
- **Gỡ số phòng**
  - *Mô tả / Tham số:* Bỏ gán số phòng, giữ dòng đăng ký và số lượng allocation.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Gỡ số phòng" hoạt động ổn định trên hệ thống.`
- **Hủy phòng**
  - *Mô tả / Tham số:* Nhập lý do và cập nhật trạng thái, AV, lịch phòng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Hủy phòng" hoạt động ổn định trên hệ thống.`
- **Khóa OOO/OOS**
  - *Mô tả / Tham số:* Chọn giai đoạn, lý do; thể hiện vùng khóa chính xác.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Khóa OOO/OOS" hoạt động ổn định trên hệ thống.`
- **Mở khóa phòng**
  - *Mô tả / Tham số:* Cập nhật vùng khóa theo ngày/giờ mở khóa nghiệp vụ.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Mở khóa phòng" hoạt động ổn định trên hệ thống.`
- **Danh sách chờ**
  - *Mô tả / Tham số:* Hiển thị và thao tác booking chờ theo tình trạng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Danh sách chờ" hoạt động ổn định trên hệ thống.`
- **Biên ngày đến/đi**
  - *Mô tả / Tham số:* Phòng checkout không chiếm đêm tiếp theo; không chồng lấn booking nhận cùng ngày.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Biên ngày đến/đi" hoạt động ổn định trên hệ thống.`

## Module: Tạo đăng ký - thông tin booking (37 mục)

- **Mở màn hình đăng ký**
  - *Mô tả / Tham số:* Hiển thị tab booking, thanh tác vụ và bảng phòng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Mở màn hình đăng ký" hoạt động ổn định trên hệ thống.`
- **Mở form Booking mới**
  - *Mô tả / Tham số:* Mở modal tạo đăng ký, mã booking tự sinh, ngày mặc định theo hệ thống.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Mở form Booking mới" hoạt động ổn định trên hệ thống.`
- **Tên đăng ký**
  - *Mô tả / Tham số:* Nhập và lưu tên, chặn tên rỗng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Tên đăng ký" hoạt động ổn định trên hệ thống.`
- **Mã tham chiếu**
  - *Mô tả / Tham số:* Nhập mã đối tác/OTA và tra cứu lại.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Mã tham chiếu" hoạt động ổn định trên hệ thống.`
- **Ngày đến/ngày đi**
  - *Mô tả / Tham số:* Chọn/gõ ngày; chặn ngày đi trước ngày đến.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Ngày đến/ngày đi" hoạt động ổn định trên hệ thống.`
- **Số đêm**
  - *Mô tả / Tham số:* Tính và điều chỉnh số đêm đồng bộ với ngày lưu trú; hỗ trợ Day Use.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Số đêm" hoạt động ổn định trên hệ thống.`
- **Tình trạng đăng ký**
  - *Mô tả / Tham số:* Chọn Guaranteed, None Guaranteed, Waiting, Allotment, Cancelled và tình trạng phạt theo cấu hình.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Tình trạng đăng ký" hoạt động ổn định trên hệ thống.`
- **Ngày xác nhận/cut-off**
  - *Mô tả / Tham số:* Tính đúng khi tạo mới; không tự thay đổi ngoài quy tắc khi sửa booking.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Ngày xác nhận/cut-off" hoạt động ổn định trên hệ thống.`
- **Công ty**
  - *Mô tả / Tham số:* Chọn công ty hợp lệ và đồng bộ thông tin liên quan.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Công ty" hoạt động ổn định trên hệ thống.`
- **Phương thức thanh toán**
  - *Mô tả / Tham số:* Chọn Cash, City ledger, chuyển khoản, thẻ, voucher, complimentary theo danh mục.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Phương thức thanh toán" hoạt động ổn định trên hệ thống.`
- **Thị trường**
  - *Mô tả / Tham số:* Chọn market segment theo danh mục.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Thị trường" hoạt động ổn định trên hệ thống.`
- **Nguồn khách**
  - *Mô tả / Tham số:* Chọn source/channel theo danh mục.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Nguồn khách" hoạt động ổn định trên hệ thống.`
- **Người bán**
  - *Mô tả / Tham số:* Gán salesperson hợp lệ và lưu cho báo cáo.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Người bán" hoạt động ổn định trên hệ thống.`
- **Người đặt phòng**
  - *Mô tả / Tham số:* Chọn người đặt; thêm người đặt từ form theo quyền.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Người đặt phòng" hoạt động ổn định trên hệ thống.`
- **Thông tin liên hệ**
  - *Mô tả / Tham số:* Nhập số điện thoại/email và lưu chính xác.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Thông tin liên hệ" hoạt động ổn định trên hệ thống.`
- **Ghi chú/Màu BK**
  - *Mô tả / Tham số:* Ghi chú xuống dòng, chọn màu để nhận biết booking.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Ghi chú/Màu BK" hoạt động ổn định trên hệ thống.`
- **Tiền phòng gửi Master**
  - *Mô tả / Tham số:* Ghi nhận room charge về phiếu tổng khi bật tùy chọn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Tiền phòng gửi Master" hoạt động ổn định trên hệ thống.`
- **Thông tin đưa đón**
  - *Mô tả / Tham số:* Thêm/sửa/xóa từng dòng ngày, giờ, chuyến, nội dung đưa đón.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Thông tin đưa đón" hoạt động ổn định trên hệ thống.`
- **Lấy phòng**
  - *Mô tả / Tham số:* Chọn loại/dạng phòng, số lượng, giai đoạn và mã giá.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lấy phòng" hoạt động ổn định trên hệ thống.`
- **Kiểm tra tồn AV**
  - *Mô tả / Tham số:* Chặn/cảnh báo lấy vượt phòng theo cấu hình AllowOverRoomTypeRoomKind.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Kiểm tra tồn AV" hoạt động ổn định trên hệ thống.`
- **Giữ allocation**
  - *Mô tả / Tham số:* Lấy lại phòng sau hủy không bị số lượng/mã giá cũ chi phối sai.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Giữ allocation" hoạt động ổn định trên hệ thống.`
- **Lưu booking mới**
  - *Mô tả / Tham số:* Tạo booking và phòng/allocation đồng bộ; không sinh trùng khi bấm lặp.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lưu booking mới" hoạt động ổn định trên hệ thống.`
- **Chặn lưu dữ liệu thiếu**
  - *Mô tả / Tham số:* Không tạo booking khi thiếu tên và các trường nghiệp vụ bắt buộc.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Chặn lưu dữ liệu thiếu" hoạt động ổn định trên hệ thống.`
- **Sửa/Quay lại/Lưu**
  - *Mô tả / Tham số:* Vào chế độ sửa, hủy sửa hoặc lưu đúng dữ liệu thay đổi.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Sửa/Quay lại/Lưu" hoạt động ổn định trên hệ thống.`
- **Nhân bản**
  - *Mô tả / Tham số:* Copy booking theo giai đoạn, số lượng và lựa chọn sao chép.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Nhân bản" hoạt động ổn định trên hệ thống.`
- **Xóa booking**
  - *Mô tả / Tham số:* Chặn trường hợp không hợp lệ, xử lý phòng/dịch vụ/cọc nhất quán.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Xóa booking" hoạt động ổn định trên hệ thống.`
- **Khôi phục booking**
  - *Mô tả / Tham số:* Phục hồi đăng ký hủy sau khi kiểm tra trùng phòng và availability.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Khôi phục booking" hoạt động ổn định trên hệ thống.`
- **Nhiều tab booking**
  - *Mô tả / Tham số:* Đổi/đóng tab; giữ đúng booking đang hoạt động, không lẫn dữ liệu.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Nhiều tab booking" hoạt động ổn định trên hệ thống.`
- **Tìm booking toàn hệ thống**
  - *Mô tả / Tham số:* Tìm theo mã/tên, chọn kết quả mở đúng đăng ký.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Tìm booking toàn hệ thống" hoạt động ổn định trên hệ thống.`
- **Thông báo booking**
  - *Mô tả / Tham số:* Thêm/sửa/xóa thông báo theo ngày, phòng, nội dung; đối chiếu khi đến hạn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Thông báo booking" hoạt động ổn định trên hệ thống.`
- **Lịch sử booking**
  - *Mô tả / Tham số:* Hiển thị người, ngày/giờ, hành động và dữ liệu cập nhật.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lịch sử booking" hoạt động ổn định trên hệ thống.`
- **Tùy chọn cột hiển thị**
  - *Mô tả / Tham số:* Ẩn/hiện, đổi thứ tự/độ rộng, giữ cài đặt bảng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Tùy chọn cột hiển thị" hoạt động ổn định trên hệ thống.`
- **Sắp xếp/nhóm bảng**
  - *Mô tả / Tham số:* Nhóm theo trạng thái/loại phòng; mở rộng/thu gọn không làm sai tổng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Sắp xếp/nhóm bảng" hoạt động ổn định trên hệ thống.`
- **Chọn nhiều phòng**
  - *Mô tả / Tham số:* Chọn dòng/nhóm/tất cả và tác vụ chỉ áp dụng phòng đã chọn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Chọn nhiều phòng" hoạt động ổn định trên hệ thống.`
- **Xuất Excel đăng ký**
  - *Mô tả / Tham số:* File giữ đủ dữ liệu booking/phòng và cột nghiệp vụ.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Xuất Excel đăng ký" hoạt động ổn định trên hệ thống.`
- **In xác nhận/phiếu đăng ký**
  - *Mô tả / Tham số:* Đúng khách, phòng, giai đoạn; tùy chọn hiện/không hiện giá.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "In xác nhận/phiếu đăng ký" hoạt động ổn định trên hệ thống.`
- **In hóa đơn tạm**
  - *Mô tả / Tham số:* Tổng tiền phòng, dịch vụ và phụ thu khớp booking.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "In hóa đơn tạm" hoạt động ổn định trên hệ thống.`

## Module: Tạo đăng ký - phòng và tác vụ (22 mục)

- **Chi tiết phòng theo đêm**
  - *Mô tả / Tham số:* Mở chi tiết tiền phòng và các dịch vụ từng ngày.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Chi tiết phòng theo đêm" hoạt động ổn định trên hệ thống.`
- **Giá phòng/mã giá**
  - *Mô tả / Tham số:* Áp mã giá, rate plan và daily mapping đúng ngày/loại/dạng phòng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Giá phòng/mã giá" hoạt động ổn định trên hệ thống.`
- **Tăng/giảm giá**
  - *Mô tả / Tham số:* Điều chỉnh giá đúng phạm vi, tổng tiền và quyền.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Tăng/giảm giá" hoạt động ổn định trên hệ thống.`
- **Người lớn/trẻ em/em bé**
  - *Mô tả / Tham số:* Nhập số lượng hợp lệ, cập nhật allocation và tổng khách.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Người lớn/trẻ em/em bé" hoạt động ổn định trên hệ thống.`
- **Ngày/giờ từng phòng**
  - *Mô tả / Tham số:* Cập nhật ngày đến/đi, giờ và số đêm theo ràng buộc booking.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Ngày/giờ từng phòng" hoạt động ổn định trên hệ thống.`
- **Phòng Day Use/ở theo giờ**
  - *Mô tả / Tham số:* Chấp nhận ngày đến=ngày đi, hiển thị đêm/phí đúng quy tắc.
  - *Đánh giá cá nhân:* `Hoàn thành nhưng còn lỗi (PMS note Dòng 529, 535): Chưa cập nhật đúng ngày đến = ngày đi; số lượng thống kê đến/đi/ở màn hình Room map bị lệch khi có phòng Day use.`
- **Tự động gán phòng**
  - *Mô tả / Tham số:* Gán đủ số phòng khả dụng, ưu tiên tầng thấp và phòng gần nhau.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Tự động gán phòng" hoạt động ổn định trên hệ thống.`
- **Gán phòng thủ công**
  - *Mô tả / Tham số:* Không gán trùng giai đoạn; tính cả Day Use và phòng khóa.
  - *Đánh giá cá nhân:* `Hoàn thành nhưng còn lỗi (PMS note Dòng 89): Chưa tạo được booking có ngày đến = ngày đi (Day use) trừ phòng AV; phòng in 1 out 2 gán phòng 101 thì phòng khác in 2 out 3 không gán được cho 101.`
- **Nâng hạng phòng**
  - *Mô tả / Tham số:* Kiểm tra AV loại nhận, giữ loại khởi tạo và cập nhật giá theo lựa chọn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Nâng hạng phòng" hoạt động ổn định trên hệ thống.`
- **Cập nhật nhanh**
  - *Mô tả / Tham số:* Đổi giai đoạn/giá cho phòng được chọn đúng quyền và cấu hình SALE/FO.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Cập nhật nhanh" hoạt động ổn định trên hệ thống.`
- **Khóa chỉnh phòng Inhouse**
  - *Mô tả / Tham số:* Khóa ngày đến; giá/ngày đi chỉ sửa khi có quyền/cấu hình cho phép.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Khóa chỉnh phòng Inhouse" hoạt động ổn định trên hệ thống.`
- **Gỡ số phòng**
  - *Mô tả / Tham số:* Không gỡ trái phép phòng đã nhận; allocation và AV đúng sau gỡ.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Gỡ số phòng" hoạt động ổn định trên hệ thống.`
- **Hủy phòng**
  - *Mô tả / Tham số:* Nhập lý do, xử lý phạt, giữ lịch sử và tổng booking chính xác.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Hủy phòng" hoạt động ổn định trên hệ thống.`
- **Chuyển phòng**
  - *Mô tả / Tham số:* Kiểm tra giai đoạn trống, trạng thái Ready và giới hạn AV trước khi chuyển.
  - *Đánh giá cá nhân:* `Hoàn thành nhưng còn lỗi (PMS note Dòng 305, 306, 530): Cần kiểm tra chuyển phòng sau khi ở qua đêm / ngày checkout; cấu hình AllowOverRoomTypeRoomKind=1 khi Suite=0 chưa cảnh báo đúng.`
- **Khóa/mở chuyển phòng**
  - *Mô tả / Tham số:* Trạng thái khóa bảo vệ đúng dòng phòng và được lưu.
  - *Đánh giá cá nhân:* `Hoàn thành nhưng còn lỗi (PMS note Dòng 305, 306, 530): Cần kiểm tra chuyển phòng sau khi ở qua đêm / ngày checkout; cấu hình AllowOverRoomTypeRoomKind=1 khi Suite=0 chưa cảnh báo đúng.`
- **Charge No-show**
  - *Mô tả / Tham số:* Nhập mức phạt, lưu bill đúng ngày/bộ phận và booking.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Charge No-show" hoạt động ổn định trên hệ thống.`
- **Đồng bộ trạng thái booking**
  - *Mô tả / Tham số:* Một phòng nhận/đi/hủy làm trạng thái booking tuân theo quy tắc toàn bộ phòng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Đồng bộ trạng thái booking" hoạt động ổn định trên hệ thống.`
- **Lịch sử phòng chuyển**
  - *Mô tả / Tham số:* Phòng cũ chỉ đọc; ngày đi/ngày đến và bill quá khứ được giữ.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lịch sử phòng chuyển" hoạt động ổn định trên hệ thống.`
- **Chuyển cùng ngày chưa post bill**
  - *Mô tả / Tham số:* Phòng cũ zero night không tự sinh dòng tiền phòng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Chuyển cùng ngày chưa post bill" hoạt động ổn định trên hệ thống.`
- **Chuyển cùng ngày đã post bill**
  - *Mô tả / Tham số:* Phòng mới kế thừa tham chiếu nhưng không cộng/post bill hai lần.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Chuyển cùng ngày đã post bill" hoạt động ổn định trên hệ thống.`
- **Chuyển sau một đêm**
  - *Mô tả / Tham số:* Phòng cũ giữ bill trước ngày chuyển; phòng mới tính đúng các đêm còn lại.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Chuyển sau một đêm" hoạt động ổn định trên hệ thống.`
- **Kế thừa dịch vụ khi chuyển**
  - *Mô tả / Tham số:* Extra bed/dịch vụ tự động chuyển đúng giai đoạn còn lại.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Kế thừa dịch vụ khi chuyển" hoạt động ổn định trên hệ thống.`

## Module: Thông tin khách (10 mục)

- **Mở thông tin khách**
  - *Mô tả / Tham số:* Hiển thị khách đúng booking/phòng, không đưa phòng lịch sử chuyển vào danh sách hoạt động.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Mở thông tin khách" hoạt động ổn định trên hệ thống.`
- **Thêm khách chính/khách cùng phòng**
  - *Mô tả / Tham số:* Nhập thông tin và liên kết đúng phòng, không nhân bản ngoài ý muốn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Thêm khách chính/khách cùng phòng" hoạt động ổn định trên hệ thống.`
- **Sửa hồ sơ khách**
  - *Mô tả / Tham số:* Lưu họ tên, giới tính, ngày sinh, quốc tịch, địa chỉ, giấy tờ.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Sửa hồ sơ khách" hoạt động ổn định trên hệ thống.`
- **Ngày cấp/hết hạn giấy tờ**
  - *Mô tả / Tham số:* Chọn/gõ/xóa ngày, kiểm tra dữ liệu không hợp lệ.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Ngày cấp/hết hạn giấy tờ" hoạt động ổn định trên hệ thống.`
- **Ảnh khách**
  - *Mô tả / Tham số:* Tải/chụp/xóa ảnh; xem ảnh đúng khách.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Ảnh khách" hoạt động ổn định trên hệ thống.`
- **Scan giấy tờ**
  - *Mô tả / Tham số:* Kiểm tra thiết bị và nhận dữ liệu; không chấm đạt khi chưa có thiết bị.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Scan giấy tờ" hoạt động ổn định trên hệ thống.`
- **Khách VIP/đặc điểm**
  - *Mô tả / Tham số:* Ghi nhận VIP, sinh nhật, honeymoon và hiển thị biểu tượng phù hợp.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Khách VIP/đặc điểm" hoạt động ổn định trên hệ thống.`
- **Xóa khách**
  - *Mô tả / Tham số:* Chặn khi có bill/thanh toán ràng buộc; không xóa nhầm khách khác.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Xóa khách" hoạt động ổn định trên hệ thống.`
- **Xuất Excel khách**
  - *Mô tả / Tham số:* Xuất đúng danh sách, phòng và thông tin giấy tờ.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Xuất Excel khách" hoạt động ổn định trên hệ thống.`
- **Cài đặt hiển thị khách**
  - *Mô tả / Tham số:* Lưu/khôi phục vị trí/cột theo cấu hình.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Cài đặt hiển thị khách" hoạt động ổn định trên hệ thống.`

## Module: Dịch vụ bổ sung (9 mục)

- **Thêm dịch vụ bổ sung**
  - *Mô tả / Tham số:* Chọn dịch vụ, ngày, phòng/Master, số lượng, đơn giá và lưu.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Thêm dịch vụ bổ sung" hoạt động ổn định trên hệ thống.`
- **Phạm vi ngày dịch vụ**
  - *Mô tả / Tham số:* Chỉ áp dụng ngày lưu trú hợp lệ, không lệch múi giờ.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Phạm vi ngày dịch vụ" hoạt động ổn định trên hệ thống.`
- **Phòng/Master**
  - *Mô tả / Tham số:* Bill thuộc đúng booking room hoặc phiếu tổng theo lựa chọn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Phòng/Master" hoạt động ổn định trên hệ thống.`
- **Sửa đơn giá/số lượng từng ngày**
  - *Mô tả / Tham số:* Tổng chi tiết và tổng booking cập nhật đúng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Sửa đơn giá/số lượng từng ngày" hoạt động ổn định trên hệ thống.`
- **Xóa dịch vụ**
  - *Mô tả / Tham số:* Chọn phòng/tất cả, nhập lý do và áp quyền/trạng thái bill.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Xóa dịch vụ" hoạt động ổn định trên hệ thống.`
- **Extra Bed**
  - *Mô tả / Tham số:* Nhập số giường, giá, từng đêm; tổng thêm giường khớp.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Extra Bed" hoạt động ổn định trên hệ thống.`
- **Chi tiết ăn sáng trẻ em**
  - *Mô tả / Tham số:* Nhập hồ sơ trẻ, tuổi, ngày và giá phụ thu; hỗ trợ nhập giá khác 0.
  - *Đánh giá cá nhân:* `Hoàn thành nhưng còn lỗi (PMS note Dòng 88, 537): Thông số Booking_AutoExtraChargeBFChild chưa hoạt động đúng, bật extra charge chưa tự cộng vào tổng booking.`
- **Yêu cầu đặc biệt**
  - *Mô tả / Tham số:* Chọn/tạo yêu cầu, ghi nội dung, số lượng và lưu đúng phòng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Yêu cầu đặc biệt" hoạt động ổn định trên hệ thống.`
- **Dịch vụ ngày quá khứ**
  - *Mô tả / Tham số:* Bill đã post/lịch sử không bị sửa sai; áp quyền thao tác ngày cũ.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Dịch vụ ngày quá khứ" hoạt động ổn định trên hệ thống.`

## Module: Đặt cọc (11 mục)

- **Mở đặt cọc**
  - *Mô tả / Tham số:* Tải cọc đúng booking, tổng tiền và ảnh chứng từ.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Mở đặt cọc" hoạt động ổn định trên hệ thống.`
- **Thêm cọc**
  - *Mô tả / Tham số:* Nhập số tiền, tiền tệ, ngày/giờ, ca, HTTT, tài khoản ngân hàng, mô tả.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Thêm cọc" hoạt động ổn định trên hệ thống.`
- **Danh mục tài khoản ngân hàng**
  - *Mô tả / Tham số:* Dùng tài khoản cấu hình hệ thống, không hardcode danh sách.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Danh mục tài khoản ngân hàng" hoạt động ổn định trên hệ thống.`
- **Ảnh chứng từ**
  - *Mô tả / Tham số:* Tải/xem/xóa ảnh đúng cọc và quyền.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Ảnh chứng từ" hoạt động ổn định trên hệ thống.`
- **Lưu bộ phận/outlet**
  - *Mô tả / Tham số:* SALE lưu MR, FO lưu FO; outlet RC theo yêu cầu nghiệp vụ.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lưu bộ phận/outlet" hoạt động ổn định trên hệ thống.`
- **Sửa cọc**
  - *Mô tả / Tham số:* Chỉ bật Quay lại/Lưu; đổi HTTT cập nhật mô tả tương ứng.
  - *Đánh giá cá nhân:* `Hoàn thành nhưng lưu ý (DOC-005): Khi sửa đổi HTTT của cọc, phần mô tả cần tự động cập nhật theo HTTT mới.`
- **Tách cọc**
  - *Mô tả / Tham số:* Tách số tiền hoặc 1/2, 1/3; tổng tiền trước/sau không đổi.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Tách cọc" hoạt động ổn định trên hệ thống.`
- **Chuyển cọc**
  - *Mô tả / Tham số:* Danh sách booking/phòng/khách nhận đầy đủ; chuyển đúng ownership và folio.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Chuyển cọc" hoạt động ổn định trên hệ thống.`
- **Xóa cọc**
  - *Mô tả / Tham số:* Nhập lý do, hủy xác nhận không xóa; giữ lịch sử/audit.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Xóa cọc" hoạt động ổn định trên hệ thống.`
- **Đối chiếu tổng cọc**
  - *Mô tả / Tham số:* Tổng trên đăng ký, modal cọc và hóa đơn khớp sau thêm/sửa/tách/chuyển/xóa.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Đối chiếu tổng cọc" hoạt động ổn định trên hệ thống.`
- **Mở cọc từ menu Giao phòng**
  - *Mô tả / Tham số:* Menu GIAO PHÒNG > ĐẶT CỌC cần mở hộp cọc đúng booking.
  - *Đánh giá cá nhân:* `Hoàn thành nhưng còn lỗi (PMS-07): Menu truyền tham số modal=deposit lệch với action=deposit nên không tự mở popup cọc.`

## Module: Khóa phòng OOO/OOS (11 mục)

- **Mở danh sách khóa**
  - *Mô tả / Tham số:* Tải phòng, loại phòng, tình trạng khóa và lịch sử.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Mở danh sách khóa" hoạt động ổn định trên hệ thống.`
- **Lọc khóa**
  - *Mô tả / Tham số:* Tìm số phòng, lọc loại phòng và Sẵn sàng/OOO/OOS.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lọc khóa" hoạt động ổn định trên hệ thống.`
- **Khóa OOO**
  - *Mô tả / Tham số:* Chọn một/nhiều phòng, bắt đầu/kết thúc, lý do; lưu khóa đúng giai đoạn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Khóa OOO" hoạt động ổn định trên hệ thống.`
- **Khóa OOS**
  - *Mô tả / Tham số:* Ghi nhận OOS đúng quy tắc bán phòng và công suất.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Khóa OOS" hoạt động ổn định trên hệ thống.`
- **Sửa khóa**
  - *Mô tả / Tham số:* Sửa giai đoạn/lý do từng phòng hoặc hàng loạt; không ghi đè phòng khác.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Sửa khóa" hoạt động ổn định trên hệ thống.`
- **Mở khóa**
  - *Mô tả / Tham số:* Ngày/giờ kết thúc theo ngày hệ thống và giờ thực hiện.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Mở khóa" hoạt động ổn định trên hệ thống.`
- **Lịch sử khóa**
  - *Mô tả / Tham số:* Xem các lần khóa/mở, người thao tác, lý do và giai đoạn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lịch sử khóa" hoạt động ổn định trên hệ thống.`
- **Availability khi mở sớm**
  - *Mô tả / Tham số:* Giữ khóa các ngày quá khứ; ngày mở áp giờ cutoff của cấu hình.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Availability khi mở sớm" hoạt động ổn định trên hệ thống.`
- **Kiểm tra booking chưa gán phòng**
  - *Mô tả / Tham số:* Không khóa làm booking mất khả năng assign khi cấu hình không cho phép.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Kiểm tra booking chưa gán phòng" hoạt động ổn định trên hệ thống.`
- **Xung đột phòng có khách**
  - *Mô tả / Tham số:* Chặn khóa trái phép trên phòng đang lưu trú hoặc giai đoạn xung đột.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Xung đột phòng có khách" hoạt động ổn định trên hệ thống.`
- **Sửa/Hủy/Lưu hàng loạt**
  - *Mô tả / Tham số:* Chỉ lưu dòng thay đổi; Hủy khôi phục dữ liệu và xử lý lỗi rõ ràng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Sửa/Hủy/Lưu hàng loạt" hoạt động ổn định trên hệ thống.`

## Module: Danh sách công việc (11 mục)

- **Mở danh sách công việc**
  - *Mô tả / Tham số:* Hiển thị đủ sáu tab nghiệp vụ và bộ lọc ngày.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Mở danh sách công việc" hoạt động ổn định trên hệ thống.`
- **Phòng đến**
  - *Mô tả / Tham số:* Xem chưa nhận/đã nhận/tất cả; đúng tổng phòng và khách.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Phòng đến" hoạt động ổn định trên hệ thống.`
- **Phòng đi**
  - *Mô tả / Tham số:* Xem phòng đến hạn trả theo ngày.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Phòng đi" hoạt động ổn định trên hệ thống.`
- **Đăng ký chờ xác nhận**
  - *Mô tả / Tham số:* Xem booking chờ; chỉnh/lưu ghi chú theo quyền.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Đăng ký chờ xác nhận" hoạt động ổn định trên hệ thống.`
- **Đón tiễn khách**
  - *Mô tả / Tham số:* Danh sách chuyến theo thời gian/khoảng ngày.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Đón tiễn khách" hoạt động ổn định trên hệ thống.`
- **Phòng không đến (Noshow)**
  - *Mô tả / Tham số:* Danh sách khách không đến theo ngày, booking và trạng thái.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Phòng không đến (Noshow)" hoạt động ổn định trên hệ thống.`
- **Sinh nhật khách**
  - *Mô tả / Tham số:* Hiển thị khách có ngày sinh trong khoảng đã chọn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Sinh nhật khách" hoạt động ổn định trên hệ thống.`
- **Gõ/chọn/sao chép ngày**
  - *Mô tả / Tham số:* Nhập 6/8 chữ số, Hôm nay/Ngày mai, xóa nhanh và lịch hoạt động.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Gõ/chọn/sao chép ngày" hoạt động ổn định trên hệ thống.`
- **Tìm kiếm công việc**
  - *Mô tả / Tham số:* Lọc mã BK, tên, phòng, công ty đúng dữ liệu.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Tìm kiếm công việc" hoạt động ổn định trên hệ thống.`
- **Mở booking bằng double-click**
  - *Mô tả / Tham số:* Nhấp đúp mã đăng ký mở đúng chi tiết booking.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Mở booking bằng double-click" hoạt động ổn định trên hệ thống.`
- **Thu gọn/ghi chú nhóm**
  - *Mô tả / Tham số:* Giữ đúng tổng room night/phòng, dịch vụ, cọc và nội dung ghi chú.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Thu gọn/ghi chú nhóm" hoạt động ổn định trên hệ thống.`

## Module: Tìm kiếm chung (14 mục)

- **Mở tìm kiếm chung**
  - *Mô tả / Tham số:* Hiển thị ba tab Đăng ký/Phòng/Khách và bộ lọc.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Mở tìm kiếm chung" hoạt động ổn định trên hệ thống.`
- **Tìm đăng ký**
  - *Mô tả / Tham số:* Tra cứu mã BK, tình trạng và bộ lọc nâng cao.
  - *Đánh giá cá nhân:* `Hoàn thành nhưng còn lỗi (PMS note Dòng 541): Tab Đăng ký và Phòng đôi khi báo Server Error; tab khách thiếu phân trang và thông tin phòng.`
- **Tìm phòng**
  - *Mô tả / Tham số:* Tra cứu số phòng, khách, thời gian và tình trạng lưu trú.
  - *Đánh giá cá nhân:* `Hoàn thành nhưng còn lỗi (PMS note Dòng 541): Tab Đăng ký và Phòng đôi khi báo Server Error; tab khách thiếu phân trang và thông tin phòng.`
- **Tìm khách**
  - *Mô tả / Tham số:* Tìm hồ sơ khách, thông tin liên hệ/giấy tờ theo các trường trên màn hình.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Tìm khách" hoạt động ổn định trên hệ thống.`
- **Bộ lọc nâng cao**
  - *Mô tả / Tham số:* Kết hợp điều kiện ngày, công ty, thị trường, nguồn và người bán.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Bộ lọc nâng cao" hoạt động ổn định trên hệ thống.`
- **Xóa/Áp dụng bộ lọc**
  - *Mô tả / Tham số:* Điều kiện được cập nhật, không giữ điều kiện ẩn ngoài ý muốn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Xóa/Áp dụng bộ lọc" hoạt động ổn định trên hệ thống.`
- **Cột hiển thị**
  - *Mô tả / Tham số:* Ẩn/hiện cột; Hiện tất cả khôi phục bảng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Cột hiển thị" hoạt động ổn định trên hệ thống.`
- **Phân trang**
  - *Mô tả / Tham số:* Đổi 50/100/200 dòng, trang trước/sau giữ đúng tập kết quả.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Phân trang" hoạt động ổn định trên hệ thống.`
- **Thao tác - Đăng ký/Hóa đơn**
  - *Mô tả / Tham số:* Mở đúng booking/phòng được chọn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Thao tác - Đăng ký/Hóa đơn" hoạt động ổn định trên hệ thống.`
- **Thao tác - Thông tin khách/Đồ thất lạc**
  - *Mô tả / Tham số:* Mở đúng ngữ cảnh khách/phòng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Thao tác - Thông tin khách/Đồ thất lạc" hoạt động ổn định trên hệ thống.`
- **Thao tác - In/Xuất Excel**
  - *Mô tả / Tham số:* In xác nhận, đăng ký lưu trú, phiếu ăn sáng và xuất đúng kết quả.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Thao tác - In/Xuất Excel" hoạt động ổn định trên hệ thống.`
- **Nhân bản từ tìm kiếm**
  - *Mô tả / Tham số:* Nhân bản đúng booking được chọn, không lẫn dòng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Nhân bản từ tìm kiếm" hoạt động ổn định trên hệ thống.`
- **No-show một ngày/giai đoạn**
  - *Mô tả / Tham số:* Áp dụng ngày, lý do, phạt và quyền theo booking.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "No-show một ngày/giai đoạn" hoạt động ổn định trên hệ thống.`
- **Nhận phòng từ tìm kiếm**
  - *Mô tả / Tham số:* Chặn phòng không hợp lệ và cập nhật trạng thái đồng bộ.
  - *Đánh giá cá nhân:* `Hoàn thành nhưng lưu ý (PMS note Dòng 257): Bảng booking_rooms, booking_room_guest chưa ghi nhận giờ check-in thực tế của phòng.`

## Module: Lịch sử thao tác (6 mục)

- **Mở lịch sử thao tác**
  - *Mô tả / Tham số:* Có bộ lọc ngày, đăng ký, phòng, hành động, người/bộ phận.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Mở lịch sử thao tác" hoạt động ổn định trên hệ thống.`
- **Lọc thời gian**
  - *Mô tả / Tham số:* Tất cả/Hôm nay/Hôm qua/7 ngày/Tháng này và khoảng tùy chọn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lọc thời gian" hoạt động ổn định trên hệ thống.`
- **Lọc hành động**
  - *Mô tả / Tham số:* Tạo/sửa/check-in/check-out/hủy/noshow/khóa/mở/thanh toán/cọc/dịch vụ/sang ngày.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lọc hành động" hoạt động ổn định trên hệ thống.`
- **Lọc mã booking/phòng/người dùng**
  - *Mô tả / Tham số:* Kết quả đúng điều kiện và chi nhánh.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lọc mã booking/phòng/người dùng" hoạt động ổn định trên hệ thống.`
- **Tìm nội dung/Hủy lọc**
  - *Mô tả / Tham số:* Tra cứu từ khóa và khôi phục kết quả.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Tìm nội dung/Hủy lọc" hoạt động ổn định trên hệ thống.`
- **Xuất Excel lịch sử**
  - *Mô tả / Tham số:* Xuất đúng dữ liệu lọc, người, thời gian và thông tin thay đổi.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Xuất Excel lịch sử" hoạt động ổn định trên hệ thống.`

## Module: Nhận phòng và khách lưu trú (10 mục)

- **Mở danh sách đến/đi/ở**
  - *Mô tả / Tham số:* Hiển thị booking/phòng/khách, ngày, giá, NL/TE/EB và tổng số.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Mở danh sách đến/đi/ở" hoạt động ổn định trên hệ thống.`
- **Lọc ngày/tình trạng**
  - *Mô tả / Tham số:* Xem phòng đến, đã đến, đang ở, phòng đi đúng ngày nghiệp vụ.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lọc ngày/tình trạng" hoạt động ổn định trên hệ thống.`
- **Nhận phòng**
  - *Mô tả / Tham số:* Check-in phòng đã gán và sẵn sàng; cập nhật booking, phòng và khách.
  - *Đánh giá cá nhân:* `Hoàn thành nhưng lưu ý (PMS note Dòng 257): Bảng booking_rooms, booking_room_guest chưa ghi nhận giờ check-in thực tế của phòng.`
- **Nhận phòng hàng loạt**
  - *Mô tả / Tham số:* Chỉ nhận phòng đã chọn, mỗi phòng được kiểm tra trạng thái.
  - *Đánh giá cá nhân:* `Hoàn thành nhưng lưu ý (PMS note Dòng 257): Bảng booking_rooms, booking_room_guest chưa ghi nhận giờ check-in thực tế của phòng.`
- **Chặn nhận phòng bẩn/chờ kiểm tra**
  - *Mô tả / Tham số:* Cảnh báo phòng Dirty/Vacant Clean và tôn trọng cấu hình Ready.
  - *Đánh giá cá nhân:* `Hoàn thành nhưng lưu ý (PMS note Dòng 257): Bảng booking_rooms, booking_room_guest chưa ghi nhận giờ check-in thực tế của phòng.`
- **Hủy nhận phòng**
  - *Mô tả / Tham số:* Xác nhận hủy; chặn khi có nghiệp vụ ràng buộc; khôi phục trạng thái đúng.
  - *Đánh giá cá nhân:* `Hoàn thành nhưng lưu ý (PMS note Dòng 257): Bảng booking_rooms, booking_room_guest chưa ghi nhận giờ check-in thực tế của phòng.`
- **Hóa đơn từ phòng**
  - *Mô tả / Tham số:* Mở đúng booking/room/guest trên checkout.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Hóa đơn từ phòng" hoạt động ổn định trên hệ thống.`
- **Nhận phòng nhanh từ menu**
  - *Mô tả / Tham số:* Mở đúng form walk-in/check-in, không chỉ quay về sơ đồ.
  - *Đánh giá cá nhân:* `Hoàn thành nhưng lưu ý (PMS note Dòng 257): Bảng booking_rooms, booking_room_guest chưa ghi nhận giờ check-in thực tế của phòng.`
- **Tạo thẻ khóa phòng**
  - *Mô tả / Tham số:* Mở nghiệp vụ tạo thẻ và kết nối thiết bị khi được cấu hình.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Tạo thẻ khóa phòng" hoạt động ổn định trên hệ thống.`
- **Chuyển phòng từ sơ đồ**
  - *Mô tả / Tham số:* Phòng nhận Ready, đủ AV và giai đoạn phù hợp; giữ bill/cọc/khách.
  - *Đánh giá cá nhân:* `Hoàn thành nhưng còn lỗi (PMS note Dòng 305, 306, 530): Cần kiểm tra chuyển phòng sau khi ở qua đêm / ngày checkout; cấu hình AllowOverRoomTypeRoomKind=1 khi Suite=0 chưa cảnh báo đúng.`

## Module: Hóa đơn - tra cứu và Folio (14 mục)

- **Mở màn hình trả phòng/hóa đơn**
  - *Mô tả / Tham số:* Hiển thị bộ chọn booking/phòng, thông tin khách, dịch vụ, thanh toán và Folio.
  - *Đánh giá cá nhân:* `Hoàn thành nhưng lưu ý (PMS note Dòng 241): Phòng có 2 khách thực hiện check-out 1 khách thì hệ thống chưa cập nhật lại số lượng khách còn lưu trú.`
- **Tìm booking/phòng/khách**
  - *Mô tả / Tham số:* Kết quả phù hợp số phòng, tên khách hoặc mã booking.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Tìm booking/phòng/khách" hoạt động ổn định trên hệ thống.`
- **Chọn kết quả tìm kiếm**
  - *Mô tả / Tham số:* Mở chi tiết đúng booking; ô search không bị gán lại mã hoặc xóa kết quả ngoài ý muốn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Chọn kết quả tìm kiếm" hoạt động ổn định trên hệ thống.`
- **Đăng ký hiện tại/đăng ký cũ/phòng ảo**
  - *Mô tả / Tham số:* Lọc đúng phạm vi và giữ dữ liệu lịch sử chỉ đọc theo quy tắc.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Đăng ký hiện tại/đăng ký cũ/phòng ảo" hoạt động ổn định trên hệ thống.`
- **Xem tất cả khách trong phòng**
  - *Mô tả / Tham số:* Hiển thị đủ khách và cho chọn chủ thể bill/thanh toán.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Xem tất cả khách trong phòng" hoạt động ổn định trên hệ thống.`
- **Xem đăng ký**
  - *Mô tả / Tham số:* Mở thông tin booking từ hóa đơn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Xem đăng ký" hoạt động ổn định trên hệ thống.`
- **Folio A/1/2/3**
  - *Mô tả / Tham số:* Hiển thị tổng và danh sách đúng folio; A là tổng hợp.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Folio A/1/2/3" hoạt động ổn định trên hệ thống.`
- **Ownership bill dịch vụ**
  - *Mô tả / Tham số:* Bill đã chuyển chỉ thuộc phòng/khách nhận; không cộng lại phòng nguồn/Master.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Ownership bill dịch vụ" hoạt động ổn định trên hệ thống.`
- **Bill phòng chuyển**
  - *Mô tả / Tham số:* Tham chiếu lịch sử chỉ đọc và không nhân tổng tiền.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Bill phòng chuyển" hoạt động ổn định trên hệ thống.`
- **Lọc dịch vụ**
  - *Mô tả / Tham số:* Theo ngày, loại, trạng thái, VAT, folio và lựa chọn hiển thị.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lọc dịch vụ" hoạt động ổn định trên hệ thống.`
- **No Post**
  - *Mô tả / Tham số:* Khóa post/sửa bill theo booking/phòng, không chỉ khóa trên giao diện.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "No Post" hoạt động ổn định trên hệ thống.`
- **Ghi chú/mô tả**
  - *Mô tả / Tham số:* Sửa mô tả bill/ghi chú theo quyền và lưu đúng dòng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Ghi chú/mô tả" hoạt động ổn định trên hệ thống.`
- **Nhóm bill/chi tiết bill**
  - *Mô tả / Tham số:* Mở nhóm loại dịch vụ và chi tiết sản phẩm/tax/phí phục vụ.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Nhóm bill/chi tiết bill" hoạt động ổn định trên hệ thống.`
- **Tổng tiền**
  - *Mô tả / Tham số:* Đối chiếu dịch vụ, thanh toán, cọc chưa dùng và còn phải thu theo folio.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Tổng tiền" hoạt động ổn định trên hệ thống.`

## Module: Hóa đơn - dịch vụ (11 mục)

- **Thêm dịch vụ**
  - *Mô tả / Tham số:* Chọn dịch vụ, ngày/giờ, bộ phận, số lượng, giá, mô tả, room/Master và folio.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Thêm dịch vụ" hoạt động ổn định trên hệ thống.`
- **Post tiền phòng**
  - *Mô tả / Tham số:* Chọn đêm/ngày, giá và đối tượng; không post trùng đêm đã thu.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Post tiền phòng" hoạt động ổn định trên hệ thống.`
- **Thêm dịch vụ buồng phòng**
  - *Mô tả / Tham số:* Mở minibar/giặt ủi/đền bù, chọn khách/phòng, sản phẩm và post bill.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Thêm dịch vụ buồng phòng" hoạt động ổn định trên hệ thống.`
- **Tập hợp dịch vụ**
  - *Mô tả / Tham số:* Chọn bill cùng booking hoặc bill trực tiếp không có mirror; giữ loại bill và quyền.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Tập hợp dịch vụ" hoạt động ổn định trên hệ thống.`
- **Tách dịch vụ**
  - *Mô tả / Tham số:* Tách số lượng/số tiền sang folio, giữ tổng bill gốc và chi tiết.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Tách dịch vụ" hoạt động ổn định trên hệ thống.`
- **Chuyển dịch vụ**
  - *Mô tả / Tham số:* Chọn booking/phòng/khách/Master nhận; cập nhật bill, ownership, mirror và audit.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Chuyển dịch vụ" hoạt động ổn định trên hệ thống.`
- **Kéo thả bill sang folio/phòng**
  - *Mô tả / Tham số:* Chỉ chuyển bill hợp lệ, không chuyển bill đã thanh toán hoặc lịch sử.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Kéo thả bill sang folio/phòng" hoạt động ổn định trên hệ thống.`
- **Xóa dịch vụ**
  - *Mô tả / Tham số:* Nhập lý do, yêu cầu mật khẩu nếu có cấu hình; giữ bill hủy và audit.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Xóa dịch vụ" hoạt động ổn định trên hệ thống.`
- **Điều chỉnh giá bill**
  - *Mô tả / Tham số:* Theo quyền RuleUserModifyRateBillService; không sửa sai ngày/trạng thái.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Điều chỉnh giá bill" hoạt động ổn định trên hệ thống.`
- **Điều chỉnh tiền phòng**
  - *Mô tả / Tham số:* Theo cấu hình RoleUserAdjustRoomRate; cập nhật bill đủ điều kiện.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Điều chỉnh tiền phòng" hoạt động ổn định trên hệ thống.`
- **Tách dịch vụ ngày cũ**
  - *Mô tả / Tham số:* Chọn giai đoạn/đối tượng, không làm sai dữ liệu audit đêm cũ.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Tách dịch vụ ngày cũ" hoạt động ổn định trên hệ thống.`

## Module: Hóa đơn - thanh toán và công nợ (17 mục)

- **Thanh toán trước**
  - *Mô tả / Tham số:* Nhập ca/giờ/ngày, tiền tệ, HTTT, tài khoản ngân hàng, số tiền, mô tả và lưu.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Thanh toán trước" hoạt động ổn định trên hệ thống.`
- **Thanh toán**
  - *Mô tả / Tham số:* Chọn bill/folio; nhập một hoặc nhiều HTTT, tổng tiền khớp số phải thu.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Thanh toán" hoạt động ổn định trên hệ thống.`
- **Tài khoản ngân hàng**
  - *Mô tả / Tham số:* Danh mục lấy từ cấu hình, chọn đúng tài khoản khi chuyển khoản.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Tài khoản ngân hàng" hoạt động ổn định trên hệ thống.`
- **Dùng cọc/thanh toán trước**
  - *Mô tả / Tham số:* Phân bổ đúng cọc vào lần thanh toán, không sử dụng hai lần.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Dùng cọc/thanh toán trước" hoạt động ổn định trên hệ thống.`
- **Mã thanh toán**
  - *Mô tả / Tham số:* Sinh payment_id tăng theo quy tắc, không nhảy sai do dùng id nguồn khác.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Mã thanh toán" hoạt động ổn định trên hệ thống.`
- **Room/Guest/Folio/Department**
  - *Mô tả / Tham số:* Lưu đủ liên kết booking room, guest, folio và FO/RC.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Room/Guest/Folio/Department" hoạt động ổn định trên hệ thống.`
- **Tách đặt cọc**
  - *Mô tả / Tham số:* Tách cọc sang folio, bảo toàn tổng tiền và ownership.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Tách đặt cọc" hoạt động ổn định trên hệ thống.`
- **Chuyển thanh toán/cọc**
  - *Mô tả / Tham số:* Chọn đúng booking/phòng/khách nhận; cập nhật số dư và audit.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Chuyển thanh toán/cọc" hoạt động ổn định trên hệ thống.`
- **Xóa thanh toán**
  - *Mô tả / Tham số:* Nhập lý do, kiểm tra quyền/ngày cũ, trả lại trạng thái bill đúng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Xóa thanh toán" hoạt động ổn định trên hệ thống.`
- **Thanh toán công nợ**
  - *Mô tả / Tham số:* Chọn công ty/hóa đơn, giải trừ từng khoản và in chứng từ.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Thanh toán công nợ" hoạt động ổn định trên hệ thống.`
- **HTTT City ledger**
  - *Mô tả / Tham số:* Ghi công nợ công ty đúng khoản, không coi là tiền mặt đã thu.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "HTTT City ledger" hoạt động ổn định trên hệ thống.`
- **Ngoại tệ/tỷ giá**
  - *Mô tả / Tham số:* Quy đổi, làm tròn và tổng tiền theo tiền tệ được cấu hình.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Ngoại tệ/tỷ giá" hoạt động ổn định trên hệ thống.`
- **In hóa đơn Folio**
  - *Mô tả / Tham số:* Mẫu in đúng khách, dịch vụ, cọc, thanh toán và số dư.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "In hóa đơn Folio" hoạt động ổn định trên hệ thống.`
- **In Bill/Hiện giá/Không hiện giá**
  - *Mô tả / Tham số:* Các lựa chọn in có tác dụng, không chỉ hiển thị menu.
  - *Đánh giá cá nhân:* `Chưa đạt — Thiếu xử lý: Nút In Bill, In VAT, Hủy VAT trên Checkout chưa gắn handler thực thi (PMS-06).`
- **Menu VAT**
  - *Mô tả / Tham số:* Mở màn hình VAT từ Quản lý hóa đơn.
  - *Đánh giá cá nhân:* `Chưa đạt — Chưa hoàn thiện: Menu Quản lý hóa đơn > VAT gắn route "#" chưa có màn hình xử lý (PMS-05).`
- **In VAT**
  - *Mô tả / Tham số:* Thực hiện tạo/xuất/in hóa đơn VAT và ghi nhận số VAT theo quy tắc.
  - *Đánh giá cá nhân:* `Chưa đạt — Thiếu xử lý: Nút In Bill, In VAT, Hủy VAT trên Checkout chưa gắn handler thực thi (PMS-06).`
- **Hủy VAT**
  - *Mô tả / Tham số:* Có tác vụ hủy theo quyền, xác nhận/lý do và lịch sử.
  - *Đánh giá cá nhân:* `Chưa đạt — Thiếu xử lý: Nút In Bill, In VAT, Hủy VAT trên Checkout chưa gắn handler thực thi (PMS-06).`

## Module: Trả phòng (7 mục)

- **Trả phòng đúng hạn**
  - *Mô tả / Tham số:* Chốt phòng hợp lệ, số dư được xử lý; chuyển tình trạng phòng theo quy tắc.
  - *Đánh giá cá nhân:* `Hoàn thành nhưng lưu ý (PMS note Dòng 241): Phòng có 2 khách thực hiện check-out 1 khách thì hệ thống chưa cập nhật lại số lượng khách còn lưu trú.`
- **Trả phòng sớm có tính phí**
  - *Mô tả / Tham số:* Tính đúng đêm/phí còn lại và cập nhật bill.
  - *Đánh giá cá nhân:* `Hoàn thành nhưng lưu ý (PMS note Dòng 241): Phòng có 2 khách thực hiện check-out 1 khách thì hệ thống chưa cập nhật lại số lượng khách còn lưu trú.`
- **Trả phòng sớm không tính phí**
  - *Mô tả / Tham số:* Cắt giai đoạn/dịch vụ tự động theo ngày đi thực tế.
  - *Đánh giá cá nhân:* `Hoàn thành nhưng lưu ý (PMS note Dòng 241): Phòng có 2 khách thực hiện check-out 1 khách thì hệ thống chưa cập nhật lại số lượng khách còn lưu trú.`
- **Trả phòng hàng loạt**
  - *Mô tả / Tham số:* Chỉ checkout phòng đã chọn và không bỏ qua ràng buộc tiền/quyền.
  - *Đánh giá cá nhân:* `Hoàn thành nhưng lưu ý (PMS note Dòng 241): Phòng có 2 khách thực hiện check-out 1 khách thì hệ thống chưa cập nhật lại số lượng khách còn lưu trú.`
- **Khôi phục trả phòng**
  - *Mô tả / Tham số:* Kiểm tra xung đột số phòng/booking trước khi restore.
  - *Đánh giá cá nhân:* `Hoàn thành nhưng lưu ý (PMS note Dòng 241): Phòng có 2 khách thực hiện check-out 1 khách thì hệ thống chưa cập nhật lại số lượng khách còn lưu trú.`
- **Phòng ảo**
  - *Mô tả / Tham số:* Quản lý folio và bill của phòng ảo, phân biệt với phòng vật lý.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Phòng ảo" hoạt động ổn định trên hệ thống.`
- **Hóa đơn SALE chỉ đọc**
  - *Mô tả / Tham số:* Từ Sales không cho sửa bill/thu tiền ngoài cấu hình đã cho phép.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Hóa đơn SALE chỉ đọc" hoạt động ổn định trên hệ thống.`

## Module: Khai báo lưu trú (8 mục)

- **Mở khai báo lưu trú**
  - *Mô tả / Tham số:* Danh sách khách/phòng/ngày và thông tin giấy tờ.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Mở khai báo lưu trú" hoạt động ổn định trên hệ thống.`
- **Lọc/chọn khách**
  - *Mô tả / Tham số:* Theo ngày, khách Việt Nam/nước ngoài, tình trạng và chọn nhiều dòng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lọc/chọn khách" hoạt động ổn định trên hệ thống.`
- **Thông tin khai báo**
  - *Mô tả / Tham số:* Sửa trường cho phép; kiểm tra thiếu giấy tờ/quốc tịch/ngày và lưu.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Thông tin khai báo" hoạt động ổn định trên hệ thống.`
- **Khách sau chuyển phòng**
  - *Mô tả / Tham số:* Ngày chuyển hiển thị phòng mới; phòng cũ chỉ xuất khi chọn lịch sử phòng chuyển.
  - *Đánh giá cá nhân:* `Hoàn thành nhưng còn lỗi (PMS note Dòng 305, 306, 530): Cần kiểm tra chuyển phòng sau khi ở qua đêm / ngày checkout; cấu hình AllowOverRoomTypeRoomKind=1 khi Suite=0 chưa cảnh báo đúng.`
- **Xuất Excel chung**
  - *Mô tả / Tham số:* Chỉ xuất khách được chọn và đúng ngày.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Xuất Excel chung" hoạt động ổn định trên hệ thống.`
- **Xuất mẫu khách Việt Nam**
  - *Mô tả / Tham số:* Đúng trường/cột theo mẫu được cấu hình.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Xuất mẫu khách Việt Nam" hoạt động ổn định trên hệ thống.`
- **Xuất mẫu khách nước ngoài**
  - *Mô tả / Tham số:* Đúng hộ chiếu/quốc tịch và cột theo mẫu.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Xuất mẫu khách nước ngoài" hoạt động ổn định trên hệ thống.`
- **Xuất XML/CSV**
  - *Mô tả / Tham số:* Định dạng hợp lệ và dữ liệu tiếng Việt được giữ.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Xuất XML/CSV" hoạt động ổn định trên hệ thống.`

## Module: Phiếu ăn sáng (7 mục)

- **Mở phiếu ăn sáng**
  - *Mô tả / Tham số:* Hiển thị điều kiện ngày ăn sáng/ngày đến và bảng phòng/khách.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Mở phiếu ăn sáng" hoạt động ổn định trên hệ thống.`
- **Xem theo ngày ăn sáng**
  - *Mô tả / Tham số:* Đúng khách được ăn sáng trong khoảng chọn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Xem theo ngày ăn sáng" hoạt động ổn định trên hệ thống.`
- **Xem theo ngày đến**
  - *Mô tả / Tham số:* Đúng khách đến trong khoảng chọn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Xem theo ngày đến" hoạt động ổn định trên hệ thống.`
- **Lọc cột/chọn dòng**
  - *Mô tả / Tham số:* Mã/tên BK, phòng, ngày đến/đi, tên khách và dòng được chọn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Lọc cột/chọn dòng" hoạt động ổn định trên hệ thống.`
- **Điều kiện in phiếu**
  - *Mô tả / Tham số:* Chọn phòng/ngày/số lượng phiếu và kiểm tra khách hợp lệ.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Điều kiện in phiếu" hoạt động ổn định trên hệ thống.`
- **Xem trước/In phiếu**
  - *Mô tả / Tham số:* Nội dung, barcode/QR, ngày, phòng và suất ăn khớp.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Xem trước/In phiếu" hoạt động ổn định trên hệ thống.`
- **Bố cục/khổ phiếu**
  - *Mô tả / Tham số:* Một/hai/ba cột, kích thước chữ, tiêu đề/trường/ghi chú chân và khôi phục gốc.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Bố cục/khổ phiếu" hoạt động ổn định trên hệ thống.`

## Module: Sang ngày/Night Audit (15 mục)

- **Mở sang ngày**
  - *Mô tả / Tham số:* Hiển thị phòng đang ở, phòng đến, phòng đi, ở theo giờ.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Mở sang ngày" hoạt động ổn định trên hệ thống.`
- **Phòng đang ở**
  - *Mô tả / Tham số:* Tổng hợp đêm ở và dịch vụ tự động cần post.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Phòng đang ở" hoạt động ổn định trên hệ thống.`
- **Phòng đến**
  - *Mô tả / Tham số:* Chọn cập nhật ngày đến (No Show One Day) hoặc No Show.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Phòng đến" hoạt động ổn định trên hệ thống.`
- **Phòng đi**
  - *Mô tả / Tham số:* Chọn gia hạn đêm phòng hoặc đánh dấu đã xử lý theo quy tắc.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Phòng đi" hoạt động ổn định trên hệ thống.`
- **Ở theo giờ**
  - *Mô tả / Tham số:* Xử lý phòng day use/hourly đúng ngày nghiệp vụ.
  - *Đánh giá cá nhân:* `Hoàn thành nhưng còn lỗi (PMS note Dòng 529, 535): Chưa cập nhật đúng ngày đến = ngày đi; số lượng thống kê đến/đi/ở màn hình Room map bị lệch khi có phòng Day use.`
- **Post tiền phòng trước audit**
  - *Mô tả / Tham số:* Không thu lặp đêm đã post; tính thuế/phí đúng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Post tiền phòng trước audit" hoạt động ổn định trên hệ thống.`
- **Gia hạn phòng**
  - *Mô tả / Tham số:* Kiểm tra AV/xung đột và tăng ngày đi/dịch vụ tương ứng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Gia hạn phòng" hoạt động ổn định trên hệ thống.`
- **Cập nhật ngày đến**
  - *Mô tả / Tham số:* Chỉ thay phòng/booking được chọn, điều chỉnh giai đoạn và tiền phù hợp.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Cập nhật ngày đến" hoạt động ổn định trên hệ thống.`
- **No-show**
  - *Mô tả / Tham số:* Cập nhật trạng thái, lý do/phạt và AV; không tác động phòng đã ở.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "No-show" hoạt động ổn định trên hệ thống.`
- **Báo cáo dự kiến doanh thu**
  - *Mô tả / Tham số:* Mở đúng báo cáo dự kiến tiền phòng phục vụ audit.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Báo cáo dự kiến doanh thu" hoạt động ổn định trên hệ thống.`
- **Cấu hình chuyển tình trạng HK**
  - *Mô tả / Tham số:* Đang ở chuyển Dirty, phòng trống chuyển Inspect theo tùy chọn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Cấu hình chuyển tình trạng HK" hoạt động ổn định trên hệ thống.`
- **Xác nhận sang ngày**
  - *Mô tả / Tham số:* Có xác nhận trước khi đổi ngày nghiệp vụ.
  - *Đánh giá cá nhân:* `Hoàn thành quy trình — Cần thử nghiệm thực tế (PMS note Dòng 259): Cần kiểm tra xử lý phòng có ngày đến = ngày hệ thống chưa gán số khi chạy audit.`
- **Chặn chạy lặp/force rerun**
  - *Mô tả / Tham số:* Không post bill trùng; tái chạy cần tuân quy tắc đã cấu hình.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Chặn chạy lặp/force rerun" hoạt động ổn định trên hệ thống.`
- **Tiến trình audit**
  - *Mô tả / Tham số:* Hiển thị bước, tiến độ, lỗi; khóa thao tác cạnh tranh khi đang chạy.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Tiến trình audit" hoạt động ổn định trên hệ thống.`
- **Hoàn tất audit**
  - *Mô tả / Tham số:* Ngày nghiệp vụ tăng đúng một ngày, bill tự động/extra bed/ăn sáng không lặp.
  - *Đánh giá cá nhân:* `Hoàn thành quy trình — Cần thử nghiệm thực tế (PMS note Dòng 259): Cần kiểm tra xử lý phòng có ngày đến = ngày hệ thống chưa gán số khi chạy audit.`

## Module: Tình trạng buồng phòng (9 mục)

- **Ready**
  - *Mô tả / Tham số:* Đổi tình trạng phòng sẵn sàng theo quyền và trạng thái lưu trú.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Ready" hoạt động ổn định trên hệ thống.`
- **Dirty**
  - *Mô tả / Tham số:* Đổi phòng bẩn, giữ đúng phân biệt phòng trống/phòng có khách.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Dirty" hoạt động ổn định trên hệ thống.`
- **Vacant Clean**
  - *Mô tả / Tham số:* Đổi phòng sạch chờ kiểm tra, không mặc định coi là Ready.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Vacant Clean" hoạt động ổn định trên hệ thống.`
- **Housekeeping**
  - *Mô tả / Tham số:* Ghi nhận trạng thái đang làm phòng theo danh mục.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Housekeeping" hoạt động ổn định trên hệ thống.`
- **Priority**
  - *Mô tả / Tham số:* Đánh dấu ưu tiên làm phòng và hiển thị icon.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Priority" hoạt động ổn định trên hệ thống.`
- **DND**
  - *Mô tả / Tham số:* Đánh dấu không làm phiền và hiển thị đúng icon.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "DND" hoạt động ổn định trên hệ thống.`
- **Đổi tình trạng hàng loạt**
  - *Mô tả / Tham số:* Chỉ cập nhật phòng đã chọn và kiểm tra quyền backend.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Đổi tình trạng hàng loạt" hoạt động ổn định trên hệ thống.`
- **Phím tắt 1/2/3**
  - *Mô tả / Tham số:* Đổi Ready/Clean/Dirty theo đúng quyền; không chạy khi đang nhập form/modal.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Phím tắt 1/2/3" hoạt động ổn định trên hệ thống.`
- **In phòng đã chọn**
  - *Mô tả / Tham số:* Bản in đúng số phòng được chọn và tình trạng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "In phòng đã chọn" hoạt động ổn định trên hệ thống.`

## Module: Tài khoản và quyền bộ phận (10 mục)

- **Đăng nhập**
  - *Mô tả / Tham số:* Tài khoản hợp lệ vào được PMS; kiểm tra thông báo khi sai/thiếu dữ liệu.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Đăng nhập" hoạt động ổn định trên hệ thống.`
- **Đăng xuất/phiên hết hạn**
  - *Mô tả / Tham số:* Hủy phiên và trở về login, không giữ quyền cũ.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Đăng xuất/phiên hết hạn" hoạt động ổn định trên hệ thống.`
- **Truy cập module**
  - *Mô tả / Tham số:* Tài khoản bộ phận chỉ vào module/màn hình được phân quyền.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Truy cập module" hoạt động ổn định trên hệ thống.`
- **View/Add/Edit/Delete**
  - *Mô tả / Tham số:* Kiểm tra cả UI và API, không chỉ ẩn nút trên giao diện.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "View/Add/Edit/Delete" hoạt động ổn định trên hệ thống.`
- **Phạm vi chi nhánh**
  - *Mô tả / Tham số:* Dữ liệu và quyền áp dụng đúng chi nhánh được chọn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Phạm vi chi nhánh" hoạt động ổn định trên hệ thống.`
- **Thao tác ngày cũ**
  - *Mô tả / Tham số:* Bill/thanh toán ngày cũ chỉ được xử lý khi có quyền/cấu hình.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Thao tác ngày cũ" hoạt động ổn định trên hệ thống.`
- **Xác minh mật khẩu**
  - *Mô tả / Tham số:* Các thao tác cần xác minh chặn mật khẩu sai và không tiết lộ dữ liệu.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Xác minh mật khẩu" hoạt động ổn định trên hệ thống.`
- **Ngôn ngữ Việt/Anh**
  - *Mô tả / Tham số:* Nhãn, ngày, tiền và thông báo đúng ngôn ngữ trên các màn hình.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Ngôn ngữ Việt/Anh" hoạt động ổn định trên hệ thống.`
- **Modal và ngày/số**
  - *Mô tả / Tham số:* Esc đóng, trường bắt buộc rõ, ngày/số được format hợp lệ và thao tác không bị che.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Modal và ngày/số" hoạt động ổn định trên hệ thống.`
- **Đăng nhập tài khoản hợp lệ**
  - *Mô tả / Tham số:* Đăng nhập testuser vào PMS local, mở module theo quyền hiện có.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng "Đăng nhập tài khoản hợp lệ" hoạt động ổn định trên hệ thống.`

## Module: Báo cáo / Báo cáo công nợ (2 mục)

- **Báo cáo công nợ công ty**
  - *Mô tả / Tham số:* Công nợ công ty theo ngày, công ty và chi tiết giải trừ tùy chọn. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Từ ngày, Đến ngày, Chọn công ty, Nhóm theo ngày, Hiển thị chi tiết giải trừ, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo công nợ đã thanh toán**
  - *Mô tả / Tham số:* Báo cáo chi tiết các khoản nợ công ty đã thanh toán, đối trừ và số dư còn lại. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Từ ngày, Đến ngày, Xem theo, Chọn công ty, Nhóm theo công ty, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`

## Module: Báo cáo / Báo cáo công suất (2 mục)

- **Báo cáo chi tiết công suất công ty**
  - *Mô tả / Tham số:* Chi tiết công suất và doanh thu theo từng booking. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Từ ngày, Đến ngày, Khu vực, Công ty, Thị trường, Người bán, Nguồn khách, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo công suất công ty**
  - *Mô tả / Tham số:* Tổng hợp 11 cột công suất công ty; chi tiết nằm ở báo cáo Dòng 166. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Từ ngày, Đến ngày, Khu vực, Công ty, Thị trường, Người bán, Nguồn khách, Nhóm theo, DT phòng bao gồm ăn sáng, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`

## Module: Báo cáo / Báo cáo dịch vụ (9 mục)

- **Báo cáo hóa đơn giặt ủi**
  - *Mô tả / Tham số:* Danh sách hóa đơn dịch vụ LA theo logic legacy sp_125/sp_202. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày, Đến ngày, Ca làm việc, Chọn bộ phận, Chọn người dùng, Loại dữ liệu, Sắp xếp theo, Thứ tự, Xem chi tiết, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo hóa đơn hàng bể vỡ**
  - *Mô tả / Tham số:* Danh sách hóa đơn dịch vụ BR theo logic legacy sp_125/sp_202. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày, Đến ngày, Ca làm việc, Chọn bộ phận, Chọn người dùng, Loại dữ liệu, Sắp xếp theo, Thứ tự, Xem chi tiết, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo hóa đơn giặt ủi theo sản phẩm**
  - *Mô tả / Tham số:* Tổng hợp sản phẩm giặt ủi theo sp_206 legacy. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày, Đến ngày, Ca làm việc, Chọn bộ phận, Chọn người dùng, Hàng bán, Nhóm theo ngày, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo hóa đơn minibar**
  - *Mô tả / Tham số:* Danh sách hóa đơn dịch vụ MB theo logic legacy sp_125/sp_202. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày, Đến ngày, Ca làm việc, Chọn bộ phận, Chọn người dùng, Loại dữ liệu, Sắp xếp theo, Thứ tự, Xem chi tiết, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo hóa đơn giặt ủi miễn phí**
  - *Mô tả / Tham số:* Danh sách hóa đơn dịch vụ LA theo logic legacy sp_125/sp_202. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày, Đến ngày, Chọn người dùng, Sắp xếp theo, Thứ tự, Detail, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo hóa đơn minibar (theo sản phẩm)**
  - *Mô tả / Tham số:* Tổng hợp sản phẩm minibar theo sp_206 legacy. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày, Đến ngày, Ca làm việc, Chọn bộ phận, Chọn người dùng, Hàng bán, Nhóm theo ngày, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo hóa đơn hàng bể vỡ theo sản phẩm**
  - *Mô tả / Tham số:* Tổng hợp sản phẩm hàng bể vỡ theo sp_206 legacy. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày, Đến ngày, Ca làm việc, Chọn bộ phận, Chọn người dùng, Hàng bán, Nhóm theo ngày, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo hóa đơn hàng bể vỡ miễn phí**
  - *Mô tả / Tham số:* Danh sách hóa đơn dịch vụ BR theo logic legacy sp_125/sp_202. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày, Đến ngày, Chọn người dùng, Sắp xếp theo, Thứ tự, Xem chi tiết, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo hóa đơn minibar miễn phí**
  - *Mô tả / Tham số:* Danh sách hóa đơn dịch vụ MB theo logic legacy sp_125/sp_202. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày, Đến ngày, Chọn người dùng, Sắp xếp theo, Thứ tự, Detail, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`

## Module: Báo cáo / Báo cáo doanh thu (9 mục)

- **Báo cáo doanh thu**
  - *Mô tả / Tham số:* Báo cáo doanh thu theo ngày theo legacy sp_292 Army. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày, Công ty, Đăng ký, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo doanh thu đăng ký theo ngày đi**
  - *Mô tả / Tham số:* Báo cáo doanh thu đăng ký theo ngày đi theo legacy sp_238 và sp_240. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Từ ngày, Đến ngày, Từ giờ, Đến giờ, Công ty, Nhóm theo đăng ký, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo doanh thu lễ tân Army**
  - *Mô tả / Tham số:* Doanh thu lễ tân theo ngày, nhóm dịch vụ và dịch vụ theo sp_293 Army. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày, Đến ngày, Người dùng, Dịch vụ, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo hóa đơn dịch vụ tổng hợp**
  - *Mô tả / Tham số:* Dịch vụ phát sinh theo ngày, nhóm theo mã dịch vụ và nhóm doanh thu Navy. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Từ ngày, Đến ngày, Bộ phận, Dịch vụ, Người dùng, Sắp xếp theo, Hiển thị đã xóa, Nhóm theo dịch vụ, Nhóm theo ngày, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo tổng hợp ngày**
  - *Mô tả / Tham số:* Tổng hợp ngày theo doanh thu, hoạt động phòng, OCC và ADR. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày báo cáo, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo doanh thu hai giai đoạn**
  - *Mô tả / Tham số:* Dịch vụ có tháng phát sinh khác tháng thanh toán hóa đơn, theo legacy Army sp_217. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày, Đến ngày, Bộ phận, Outlet, Dịch vụ, Chọn người dùng, Sắp xếp theo, Thứ tự, Nhóm theo ngày, Đăng ký, Chế độ ngày thanh toán, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo tổng hợp doanh thu theo người bán**
  - *Mô tả / Tham số:* Tổng hợp doanh thu theo người bán, hỗ trợ Army arrival và Navy room-night. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Từ ngày, Đến ngày, Chế độ lọc, Người bán, Thị trường, Công ty, Nhóm theo, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo chi tiết doanh thu theo người bán**
  - *Mô tả / Tham số:* Chi tiết doanh thu từng booking theo người bán, hỗ trợ Army arrival và Navy room-night. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Từ ngày, Đến ngày, Chế độ lọc, Người bán, Thị trường, Công ty, Nhóm theo, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo hóa đơn dịch vụ chưa thanh toán**
  - *Mô tả / Tham số:* Báo cáo chi tiết các bill dịch vụ chưa thanh toán theo sp_046 legacy. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Chọn ngày, Đến ngày, Chọn người dùng, Sắp xếp theo, Thứ tự sắp xếp, Bộ phận, Dịch vụ, Công ty, p_outlet, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`

## Module: Báo cáo / Báo cáo hủy phòng (2 mục)

- **Báo cáo hủy đăng ký**
  - *Mô tả / Tham số:* Booking và phòng bị hủy theo legacy sp_284/sp_261. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Chọn ngày, Đến ngày, Hiển thị thông tin phòng, Loại ngày, Đăng ký, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo phòng hủy**
  - *Mô tả / Tham số:* Danh sách phòng hủy theo sp_261/vw_034. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày, Đến ngày, Loại ngày, Booking, Chi nhánh, Nhóm theo lý do hủy phòng, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`

## Module: Báo cáo / Báo cáo khách (4 mục)

- **Báo cáo danh sách khách ở**
  - *Mô tả / Tham số:* Danh sách khách theo từng ngày lưu trú và tổng hợp quốc tịch/giới tính. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày, Đến ngày, Công ty/Đại lý, Lọc theo đăng ký, Cách xem, Hiển thị đăng ký, Hiển thị ghi chú, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo đưa đón khách**
  - *Mô tả / Tham số:* Tổng hợp toàn bộ lịch đưa đón khách theo ngày. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày, Đến ngày, Nhóm theo ngày, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo khách quay lại**
  - *Mô tả / Tham số:* Danh sách khách quay lại theo Passport và giai đoạn lưu trú. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày ở, Đến ngày, Lọc đăng ký theo giai đoạn ở, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo khách VIP**
  - *Mô tả / Tham số:* Danh sách khách VIP theo loại khách và thời gian lưu trú. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày, Đến ngày, Loại khách, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`

## Module: Báo cáo / Báo cáo phòng (20 mục)

- **Báo cáo phòng đến**
  - *Mô tả / Tham số:* Danh sách khách/phòng có ngày đến trong khoảng được chọn. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Chọn ngày, Đến ngày, Chọn loại phòng, Chọn tình trạng đăng ký, Chọn khu vực, Công ty / lữ hành, Đăng ký phòng, Chỉ hiển thị khách chính, Hiển thị giá phòng, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo phòng ở**
  - *Mô tả / Tham số:* Danh sách phòng/khách đang ở theo ngày, chuyển đổi từ legacy sp_167. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Chọn ngày, Đến ngày, Thực tế, Chọn loại phòng, Chọn tình trạng đăng ký, Chọn khu vực, Chọn công ty, Chọn đăng ký, Hiển thị khách chính, Hiển thị chi tiết, Giá phòng, VAT, Không VAT, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo danh sách khách đã nhận phòng**
  - *Mô tả / Tham số:* Danh sách khách tại các phòng đã nhận phòng, theo ngày nhận phòng thực tế. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Chọn ngày, Đến ngày, Sắp xếp theo, Lọc theo công ty, Lọc theo đăng ký, Hiển thị ghi chú, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo phòng miễn phí**
  - *Mô tả / Tham số:* Phòng có giá phòng miễn phí theo legacy sp_048. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày, Đến ngày, Mã giá phòng, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo phòng No-show**
  - *Mô tả / Tham số:* Danh sách phòng không đến theo sp_054. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày, Đến ngày, Loại tiền, Người dùng, Loại tiền legacy, Sắp xếp theo ngày No-show, Chi nhánh, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo phòng No Show theo ngày**
  - *Mô tả / Tham số:* Danh sách phòng No Show theo ngày theo ProVista sp_056. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày, Đến ngày, Loại tiền, Người dùng, Loại tiền legacy, Sắp xếp theo ngày Check-in, Chọn đăng ký, Chi nhánh, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo phòng hàng tuần**
  - *Mô tả / Tham số:* Tổng hợp Đến, Đi, Ở và Công suất theo 7 ngày trong tuần. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Tuần bắt đầu, Tuần kết thúc, Chi nhánh, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo phòng đi**
  - *Mô tả / Tham số:* Danh sách khách/phòng có ngày đi trong khoảng được chọn. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Chọn ngày, Đến ngày, Chọn loại phòng, Chọn tình trạng đăng ký, Chọn khu vực, Chọn công ty, Chọn đăng ký, Chỉ hiển thị khách chính, Room Rate, Services Amount, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Danh sách phòng Due In**
  - *Mô tả / Tham số:* Danh sách phòng có khách đến theo legacy sp_006. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Chọn ngày, Đến ngày, Chọn khu vực, Chọn công ty, Chọn đăng ký, Chọn loại phòng, Chọn tình trạng đăng ký, Chỉ hiển thị khách chính, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Danh sách phòng Due Out**
  - *Mô tả / Tham số:* Danh sách phòng có ngày đi trong khoảng chọn theo legacy sp_008. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Chọn ngày, Đến ngày, Chọn loại phòng, Chọn tình trạng đăng ký, Chọn khu vực, Chọn công ty, Chọn đăng ký, Chỉ hiển thị khách chính, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo chuyển phòng**
  - *Mô tả / Tham số:* Danh sách phòng chuyển theo ngày chuyển và người thực hiện. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Từ ngày, Đến ngày, Người thực hiện, Sắp xếp theo, Thứ tự, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo thay đổi tình trạng phòng**
  - *Mô tả / Tham số:* Lịch sử thao tác thay đổi tình trạng phòng theo sp_288. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Từ ngày, Đến ngày, Người dùng, Số phòng, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo phòng checkout sớm**
  - *Mô tả / Tham số:* Thống kê các phòng checkout sớm theo ngày checkout thực tế. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày checkout, Đến ngày, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo dịch vụ bổ sung**
  - *Mô tả / Tham số:* Danh sách dịch vụ bổ sung theo ngày dịch vụ và loại dịch vụ. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày, Đến ngày, Dịch vụ, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo Extra Bed**
  - *Mô tả / Tham số:* Thống kê số lượng và doanh thu Extra Bed theo từng ngày. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Từ ngày, Đến ngày, Đăng ký, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo danh sách khách đã trả phòng**
  - *Mô tả / Tham số:* Danh sách khách đã trả phòng theo legacy sp_149. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Từ ngày, Đến ngày, Sắp xếp theo, Lọc theo công ty, Lọc theo đăng ký, Hiển thị ghi chú, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo phòng ở trong ngày (Day Use)**
  - *Mô tả / Tham số:* Danh sách phòng Day Use theo legacy sp_132. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày, Đến ngày, Người dùng, Sắp xếp theo, Thứ tự, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo dự kiến khách ăn sáng**
  - *Mô tả / Tham số:* Hai mẫu tổng hợp và chi tiết theo legacy sp_035/sp_032. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày, Đến ngày, Xem theo, Người dùng, Sắp xếp theo, Thứ tự, Nhận phòng trễ, Hiển thị thông tin phòng, Đăng ký theo nhóm, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo yêu cầu đặc biệt**
  - *Mô tả / Tham số:* Danh sách các phòng có yêu cầu đặc biệt theo đăng ký và khoảng thời gian. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Chọn ngày, Đến ngày, Xem theo ngày, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo dự đoán bán phòng**
  - *Mô tả / Tham số:* Báo cáo dự đoán bán phòng 17 cột FO; bỏ 4 cột tài chính ở chế độ HK bằng p_revenue_detail. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Chọn ngày, Đến ngày, Người dùng / Người bán, Chi nhánh, Loại phòng, Hiển thị chi tiết doanh thu, Doanh thu phòng bao gồm ăn sáng, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`

## Module: Báo cáo / Báo cáo phòng khóa (2 mục)

- **Báo cáo lịch sử khóa phòng OOO**
  - *Mô tả / Tham số:* Lịch sử khóa và mở khóa phòng OOO theo sp_057. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày, Đến ngày, Người dùng, Sắp xếp theo, Thứ tự, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo lịch sử khóa phòng OOS**
  - *Mô tả / Tham số:* Lịch sử khóa và mở khóa phòng OOS theo sp_059. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày, Đến ngày, Người dùng, Sắp xếp theo, Thứ tự, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`

## Module: Báo cáo / Báo cáo quản lý / Sang ngày (1 mục)

- **Báo cáo dự kiến doanh thu tiền phòng**
  - *Mô tả / Tham số:* Xem khoản tiền phòng và dịch vụ dự kiến hoặc đã post trong ngày trước khi chạy Sang Ngày. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày báo cáo, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`

## Module: Báo cáo / Báo cáo thống kê (1 mục)

- **Báo cáo thống kê mã giá phòng**
  - *Mô tả / Tham số:* Báo cáo thống kê lượt phòng theo mã giá phòng theo sp_287 legacy. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Chọn ngày, Đến ngày, Mã giá phòng, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`

## Module: Báo cáo / Báo cáo thống kê lễ tân (5 mục)

- **Báo cáo đặt cọc (Module Sale)**
  - *Mô tả / Tham số:* Báo cáo đặt cọc theo ngày payment.date, bộ phận, user, ca, công ty và phương thức thanh toán. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày, Đến ngày, Chọn bộ phận, Chọn người dùng, Ca làm việc, Từ giờ, Đến giờ, Chọn công ty, Hiển thị tiền cọc, Hiển thị tiền = 0, Phương thức thanh toán, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo hóa đơn bán hàng**
  - *Mô tả / Tham số:* Báo cáo chi tiết hóa đơn bán hàng theo ngày, khách, công ty và hình thức thanh toán theo sp_094 legacy. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Chọn ngày, Đến ngày, Chọn bộ phận, Chọn công ty, Chọn người dùng, Xem Theo, Sắp xếp theo, Thứ tự sắp xếp, p_outlet, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo tổng doanh thu**
  - *Mô tả / Tham số:* Bảng kê chi tiết doanh thu theo booking, thanh toán và số dư phòng còn ở theo Sheet 72/sp_292. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày, Công ty, Đăng ký, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo hủy hóa đơn/thanh toán**
  - *Mô tả / Tham số:* Báo cáo hủy hóa đơn và hủy thanh toán theo legacy sp_068/sp_070. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Chế độ xem, Ngày, Đến ngày, Bộ phận, Outlet, Dịch vụ, Người dùng, Sắp xếp theo, Thứ tự, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo lễ tân hằng ngày**
  - *Mô tả / Tham số:* Báo cáo phòng và suất ăn sáng theo 4 phân khúc khách hàng theo legacy sp_275. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Chọn ngày, Đến ngày, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`

## Module: Báo cáo / Báo cáo thu ngân (2 mục)

- **Báo cáo thu ngân lễ tân**
  - *Mô tả / Tham số:* Báo cáo thu ngân lễ tân theo legacy sp_039 Navy. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày, Đến ngày, Bộ phận, Người dùng, Ca làm việc, Từ giờ, Đến giờ, Công ty, Hiển thị đặt cọc, Hiển thị tiền = 0, Phương thức thanh toán, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`
- **Báo cáo tiền đặt cọc**
  - *Mô tả / Tham số:* Tiền đặt cọc và từng lần cấn trừ thực tế. Chạy báo cáo và nhận HTML kết quả theo tham số. Bộ lọc hỗ trợ: Ngày, Đến ngày, Điều kiện lọc cọc, Ca làm việc, Bộ phận, Outlet, Công ty, Người dùng, Sắp xếp theo, Thứ tự, xuất/in. Hỗ trợ xem trước và xuất Excel/Word/PDF/In.
  - *Đánh giá cá nhân:* `Đã hoàn thành (API & Render): Báo cáo chạy thành công, kết xuất HTML chuẩn dữ liệu HKT1 với ngày mẫu 30/08/2026; xuất Excel/PDF chuẩn định dạng.`



---

# CẤU HÌNH HỆ THỐNG (HOTEL CONFIG) (7 Modules — 58 Mục)

## Module: Định nghĩa khách sạn (14 mục)

- **Thông tin khách sạn**
  - *Mô tả / Tham số:* Khai báo thông tin nhận diện pháp nhân khách sạn: Tên khách sạn, Địa chỉ, Mã số thuế, Điện thoại, Email, Website, Logo khách sạn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng quản lý thông tin khách sạn hoạt động ổn định, lưu trữ đầy đủ hồ sơ khách sạn.`
- **Thông tin khách sạn - Giờ Check-in / Check-out**
  - *Mô tả / Tham số:* Thiết lập khung giờ nhận phòng tiêu chuẩn (14:00) và giờ trả phòng tiêu chuẩn (12:00) phục vụ tính tiền lưu trú.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Cấu hình giờ Check-in / Check-out hoạt động chuẩn xác, liên kết đồng bộ sang sơ đồ phòng.`
- **Thông tin khách sạn - Cấu hình tiền tệ & giá ăn sáng**
  - *Mô tả / Tham số:* Cấu hình đơn vị tiền tệ chính (VND/USD) và mức giá tiền ăn sáng mặc định cho người lớn và trẻ em.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Cấu hình tiền tệ và giá ăn sáng mặc định hoạt động ổn định, tự động fill vào booking.`
- **Dịch vụ khách sạn - Khai báo danh mục dịch vụ**
  - *Mô tả / Tham số:* Khai báo các dịch vụ khách sạn cung cấp (Minibar, Giặt ủi, Spa, Nhà hàng, Đưa đón sân bay, Thuê xe...), mã dịch vụ, tên dịch vụ và bộ phận phụ trách.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Danh mục dịch vụ khách sạn hoạt động đầy đủ, cho phép quản lý chi tiết từng dịch vụ.`
- **Dịch vụ khách sạn - Cấu hình thuế VAT & Phí phục vụ**
  - *Mô tả / Tham số:* Thiết lập thuế suất VAT (%) và tỷ lệ phí phục vụ (%) cho từng dịch vụ khách sạn.
  - *Đánh giá cá nhân:* `Hoàn thành nhưng còn lỗi (PMS note Dòng 115): Màn hình dịch vụ khách sạn đang thiếu thông tin cấu hình phí phục vụ (service charge %).`
- **Dịch vụ khách sạn - Thao tác Thêm / Sửa / Xóa**
  - *Mô tả / Tham số:* Thêm mới dịch vụ, chỉnh sửa thông tin dịch vụ và xóa hoặc tạm ngưng các dịch vụ không còn hoạt động.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Thao tác thêm mới, sửa, bật/tắt trạng thái dịch vụ hoạt động ổn định.`
- **Ca làm việc - Quản lý ca trực Lễ tân & Thu ngân**
  - *Mô tả / Tham số:* Khai báo danh mục ca làm việc (Ca sáng, Ca chiều, Ca đêm, Ca hành chính) gồm Mã ca, Tên ca, Giờ bắt đầu và Giờ kết thúc ca.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng quản lý ca làm việc hoạt động ổn định, hỗ trợ đầy đủ thêm, sửa, xóa ca.`
- **Cấu hình khách sạn - Tham số vận hành chung**
  - *Mô tả / Tham số:* Thiết lập tham số vận hành: Cut-off date mặc định, thời gian tự động hủy booking quá hạn, cấu hình màu sắc sơ đồ phòng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Các tham số cấu hình chung được lưu trữ và áp dụng chính xác cho toàn hệ thống.`
- **Cấu hình khách sạn - Tham số bán vượt phòng (Allow Over)**
  - *Mô tả / Tham số:* Cấu hình tham số cho phép hoặc chặn bán vượt phòng (AllowOverRoomTypeRoomKind) khi tạo đặt phòng hoặc chuyển trạng thái booking.
  - *Đánh giá cá nhân:* `Hoàn thành nhưng còn lỗi (PMS note Dòng 530, 531): Cần kiểm soát chặt chẽ việc chặn overbooking khi chuyển đổi trạng thái booking từ Waiting sang Guaranteed và khi cập nhật ngày phòng.`
- **Cấu hình khách sạn - Phụ thu Check-in sớm / Check-out muộn**
  - *Mô tả / Tham số:* Thiết lập quy tắc và tỷ lệ phần trăm phụ thu khi khách nhận phòng sớm hoặc trả phòng muộn theo từng khung giờ quy định.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Thiết lập quy tắc tính phụ thu check-in sớm / check-out muộn hoạt động tốt.`
- **Chi nhánh áp dụng**
  - *Mô tả / Tham số:* Xem danh sách các chi nhánh được áp dụng cấu hình khách sạn và kích hoạt chi nhánh vận hành.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Danh sách chi nhánh liên kết hiển thị đúng thông tin và quản lý độc lập.`
- **Mẫu in chứng từ khách sạn**
  - *Mô tả / Tham số:* Xem danh mục và thiết lập mẫu in mặc định cho từng loại chứng từ giao dịch (Xác nhận đặt phòng, Phiếu đăng ký, Hóa đơn...).
  - *Đánh giá cá nhân:* `Đã hoàn thành: Quản lý gán mẫu in mặc định cho từng chức năng in hoạt động ổn định.`
- **Mẫu in - Xem thử mẫu (Preview)**
  - *Mô tả / Tham số:* Chức năng xem trước mẫu in trực quan trước khi đưa vào áp dụng chính thức cho các bộ phận nghiệp vụ.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Tính năng xem trước mẫu in hiển thị chính xác layout và trường dữ liệu.`
- **Bộ phận dịch vụ**
  - *Mô tả / Tham số:* Khai báo danh mục bộ phận cung cấp dịch vụ (Lễ tân, Nhà hàng, Buồng phòng, Spa), quản lý mã bộ phận, tên bộ phận và gán dịch vụ trực thuộc.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng định nghĩa bộ phận dịch vụ hoạt động ổn định.`

## Module: Định nghĩa phòng (10 mục)

- **Tên loại phòng - Khai báo hạng phòng**
  - *Mô tả / Tham số:* Khai báo danh mục loại phòng / hạng phòng (Standard, Superior, Deluxe, Suite, Family...), mã loại phòng, tên hiển thị và tên viết tắt.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Quản lý loại phòng hoạt động ổn định, lưu trữ đầy đủ thông tin phân loại.`
- **Tên loại phòng - Màu sắc nhận diện**
  - *Mô tả / Tham số:* Thiết lập mã màu sắc nhận diện riêng cho từng loại phòng hiển thị trên Sơ đồ phòng (Room Map).
  - *Đánh giá cá nhân:* `Đã hoàn thành: Cấu hình màu sắc loại phòng hiển thị trực quan và đồng bộ chuẩn trên Room Map.`
- **Tên loại phòng - Bật / Tắt trạng thái sử dụng**
  - *Mô tả / Tham số:* Quản lý trạng thái Có sử dụng / Tạm khóa đối với từng loại phòng, hỗ trợ thêm mới, chỉnh sửa và ẩn loại phòng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Thao tác bật/tắt trạng thái và cập nhật loại phòng hoạt động ổn định.`
- **Dạng phòng - Khai báo loại giường & sức chứa**
  - *Mô tả / Tham số:* Khai báo các dạng phòng / loại giường (Single, Double, Twin, King, Queen, Extra Bed) kèm số lượng người lớn tiêu chuẩn và trẻ em tối đa.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Cấu hình dạng phòng và sức chứa chuẩn hoạt động chính xác, tự động áp dụng khi lấy phòng.`
- **Giá phòng chuẩn - Thiết lập bảng giá niêm yết**
  - *Mô tả / Tham số:* Thiết lập bảng giá niêm yết chuẩn (Rack Rate) theo ma trận Loại phòng x Dạng phòng cho ngày thường và cuối tuần.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Bảng giá phòng chuẩn hoạt động ổn định, lưu giá chính xác theo từng tổ hợp phòng.`
- **Giá phòng chuẩn - Đơn giá giường phụ (Extra Bed)**
  - *Mô tả / Tham số:* Thiết lập mức giá phụ thu thêm giường phụ (Extra Bed Rate) cho từng loại phòng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Cấu hình giá thêm giường phụ hoạt động chính xác, liên kết sang nghiệp vụ lễ tân.`
- **Danh sách phòng - Khai báo số phòng & tầng**
  - *Mô tả / Tham số:* Khai báo danh sách số phòng thực tế trong khách sạn, số tầng, khu vực/tòa nhà, loại phòng và dạng phòng tương ứng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Danh sách phòng hoạt động đầy đủ, quản lý chi tiết toàn bộ phòng ốc khách sạn.`
- **Danh sách phòng - Phòng nội bộ (Internal Room)**
  - *Mô tả / Tham số:* Đánh dấu và quản lý các phòng nội bộ sử dụng cho mục đích vận hành nội bộ khách sạn, không tính vào quỹ phòng kinh doanh.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Cờ đánh dấu phòng nội bộ hoạt động chính xác, loại trừ khỏi quỹ phòng bán.`
- **Danh sách phòng - Phòng thông nhau (Connecting Rooms)**
  - *Mô tả / Tham số:* Thiết lập liên kết các phòng thông nhau để lễ tân tiện theo dõi và sắp xếp cho khách gia đình.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Cấu hình phòng thông nhau hiển thị đầy đủ thông tin liên kết giữa các cặp phòng.`
- **Danh sách phòng - Tọa độ hiển thị Room Map**
  - *Mô tả / Tham số:* Cấu hình vị trí tọa độ (Hàng và Cột) để sắp xếp vị trí hiển thị ô phòng trên giao diện Sơ đồ phòng trực quan.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Tọa độ hàng/cột được lưu chuẩn và phản ánh chính xác vị trí hiển thị trên Room Map.`

## Module: Định nghĩa hệ thống (10 mục)

- **Hình thức thanh toán - Khai báo danh mục HTTT**
  - *Mô tả / Tham số:* Khai báo các hình thức thanh toán (Tiền mặt, Chuyển khoản, Thẻ tín dụng/POS, QR Code, Miễn phí, Công nợ...), mã, tên và bộ phận áp dụng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Danh mục hình thức thanh toán hoạt động ổn định, hỗ trợ đa dạng phương thức thanh toán.`
- **Hình thức thanh toán - Tài khoản ngân hàng & Phí dịch vụ**
  - *Mô tả / Tham số:* Thiết lập tài khoản ngân hàng liên kết và cấu hình tỷ lệ phí dịch vụ thu thêm theo từng hình thức thanh toán.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Liên kết tài khoản ngân hàng và phí dịch vụ thanh toán hoạt động tốt.`
- **Hình thức thanh toán - Cờ Miễn phí & Ẩn thanh toán**
  - *Mô tả / Tham số:* Thiết lập đánh dấu hình thức miễn phí, chặn sắp xếp thứ tự và ẩn các hình thức thanh toán không sử dụng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Các cờ phân loại HTTT hoạt động chính xác, chỉ hiển thị HTTT hợp lệ tại quầy thu ngân.`
- **Tiền tệ & Tỉ giá - Danh mục loại tiền tệ**
  - *Mô tả / Tham số:* Khai báo các loại ngoại tệ giao dịch (VND, USD, EUR, JPY...), mã tiền tệ, tên, quốc gia và hình ảnh nhận diện.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Quản lý danh mục tiền tệ hoạt động ổn định, lưu trữ đầy đủ thông tin tiền tệ.`
- **Tiền tệ & Tỉ giá - Cập nhật tỉ giá quy đổi**
  - *Mô tả / Tham số:* Thiết lập và cập nhật tỉ giá quy đổi giữa các loại tiền tệ so với đồng tiền chính của hệ thống.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Quản lý tỉ giá quy đổi hoạt động chính xác, hỗ trợ quy đổi tiền phòng và dịch vụ.`
- **Tiền tệ & Tỉ giá - Quy tắc làm tròn số thập phân**
  - *Mô tả / Tham số:* Cấu hình số chữ số làm tròn sau dấu phẩy (làm tròn 0, 1, 2 chữ số thập phân) cho từng loại tiền tệ.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Cấu hình làm tròn số thập phân hoạt động chuẩn xác theo định dạng tiền tệ quốc tế.`
- **Đơn vị tính - Quản lý danh mục ĐVT**
  - *Mô tả / Tham số:* Khai báo danh mục đơn vị tính cho hàng hóa và dịch vụ (Đêm, Giờ, Chai, Lon, Gói, Lần, Bộ...), mã, tên, symbol và trạng thái sử dụng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Quản lý đơn vị tính hoạt động ổn định, hỗ trợ thêm, sửa, xóa đơn vị tính.`
- **Tình trạng đăng ký - Quản lý trạng thái Booking**
  - *Mô tả / Tham số:* Khai báo danh mục trạng thái đặt phòng (Guaranteed, Non-Guaranteed, Waiting, Cancelled, In-house) kèm cờ trừ phòng trống và cờ ẩn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Quản lý tình trạng đăng ký hoạt động chuẩn, kiểm soát chính xác quỹ phòng trống.`
- **Cấu hình Housekeeping - Ký hiệu trạng thái buồng**
  - *Mô tả / Tham số:* Thiết lập các ký hiệu viết tắt cho các trạng thái phòng buồng (Clean, Dirty, Inspected, Out of Order, Out of Service, Touch-up...).
  - *Đánh giá cá nhân:* `Đã hoàn thành: Cấu hình ký hiệu buồng phòng hoạt động ổn định, đồng bộ với sơ đồ buồng phòng.`
- **Cấu hình Housekeeping - Tùy chọn cột bảng in giao việc**
  - *Mô tả / Tham số:* Cấu hình các cột thông tin hiển thị trên bảng phân công buồng phòng và bảng in giao việc ca sáng (Room Morning Worksheet).
  - *Đánh giá cá nhân:* `Đã hoàn thành: Tùy biến cột in giao việc buồng phòng hoạt động chính xác theo mẫu in thực tế.`

## Module: Thiết lập giá (5 mục)

- **Mã giá phòng (Rate Code) - Khai báo gói giá**
  - *Mô tả / Tham số:* Khai báo danh mục mã giá / chính sách giá phòng (FIT, OTA, Corporate, Mùa thấp điểm, Mùa cao điểm...), mã giá, mô tả và loại tiền tệ.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng quản lý Rate Code và ma trận giá phòng đã được xử lý và hoạt động ổn định trên hệ thống.`
- **Mã giá phòng - Thời hạn áp dụng & Cờ ăn sáng**
  - *Mô tả / Tham số:* Cấu hình khoảng thời gian hiệu lực (Từ ngày - Đến ngày), tùy chọn bao gồm ăn sáng hay không theo mã giá phòng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Thiết lập ngày hiệu lực và cờ ăn sáng theo mã giá phòng hoạt động chính xác.`
- **Mã giá phòng - Quyền tự nhập giá & Channel Manager**
  - *Mô tả / Tham số:* Cấu hình tùy chọn cho phép nhân viên lễ tân tự nhập/ghi đè giá và cấu hình liên kết mã giá với Channel Manager.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Cấu hình quyền ghi đè giá và cờ Channel Manager hoạt động đúng theo phân quyền.`
- **Ma trận giá phòng chi tiết (Rate Matrix)**
  - *Mô tả / Tham số:* Thiết lập bảng giá chi tiết theo từng Loại phòng và từng ngày trong tuần hoặc từng giai đoạn ngày cụ thể.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Ma trận giá phòng chi tiết lưu trữ chính xác theo từng tổ hợp ngày và hạng phòng.`
- **Gói dịch vụ (Packages & Add-ons)**
  - *Mô tả / Tham số:* Khai báo các gói bán phòng kết hợp dịch vụ gia tăng (Room + Tour, Room + Spa, Ăn sáng + Ăn trưa), đơn giá và bộ phận doanh thu.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Quản lý gói dịch vụ đính kèm hoạt động ổn định, tự động cộng dịch vụ khi chọn gói.`

## Module: Định nghĩa buồng phòng (5 mục)

- **Outlet buồng phòng - Khai báo điểm phục vụ**
  - *Mô tả / Tham số:* Khai báo danh mục outlet / điểm cung cấp của bộ phận buồng (Minibar tầng, Giặt ủi buồng phòng, Đồ vải, Vật tư tiêu hao).
  - *Đánh giá cá nhân:* `Đã hoàn thành: Khai báo outlet buồng phòng hoạt động ổn định, liên kết chính xác dịch vụ khách sạn.`
- **Outlet buồng phòng - Thuế VAT & Phí phục vụ**
  - *Mô tả / Tham số:* Cấu hình tỷ lệ phần trăm thuế VAT mặc định và tỷ lệ phí phục vụ mặc định cho từng outlet buồng phòng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Cấu hình thuế suất và phí phục vụ outlet buồng phòng áp dụng chính xác khi tính tiền.`
- **Outlet buồng phòng - Tùy chọn hiển thị & Kéo thả sắp xếp**
  - *Mô tả / Tham số:* Tùy chọn cho phép hiển thị outlet trong màn hình Thêm dịch vụ và sắp xếp thứ tự ưu tiên bằng thao tác kéo thả trực quan.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Tính năng kéo thả sắp xếp thứ tự outlet và cờ hiển thị hoạt động trơn tru.`
- **Outlet buồng phòng - Thêm / Sửa / Xóa an toàn**
  - *Mô tả / Tham số:* Thao tác thêm mới, chỉnh sửa thông tin, bật/tắt hoạt động và xóa vĩnh viễn outlet buồng phòng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Quản lý thêm, sửa, xóa outlet buồng phòng hoạt động đầy đủ.`
- **Menu hàng hóa buồng phòng - Danh mục & Hình ảnh**
  - *Mô tả / Tham số:* Quản lý danh mục hàng hóa minibar và bảng giá giặt ủi gắn với từng outlet, tải lên hình ảnh minh họa sản phẩm.
  - *Đánh giá cá nhân:* `Hoàn thành nhưng còn lỗi (PMS note Dòng 540): Chức năng thêm hình ảnh đại diện cho sản phẩm/hàng hóa buồng phòng chưa hiển thị được ảnh sau khi tải lên.`

## Module: Mẫu & Báo cáo (7 mục)

- **Thư viện mẫu in - Quản lý kho mẫu chứng từ**
  - *Mô tả / Tham số:* Quản lý kho mẫu in chứng từ (Booking Confirmation, Registration Card, Deposit, Receipt, Room Morning Worksheet, Invoice, Breakfast Ticket...).
  - *Đánh giá cá nhân:* `Đã hoàn thành: Thư viện mẫu in chứng từ hoạt động đầy đủ, hỗ trợ quản lý đa dạng mẫu biểu.`
- **Trình thiết kế mẫu in - Soạn thảo WYSIWYG trực quan**
  - *Mô tả / Tham số:* Trình soạn thảo giao diện mẫu in trực quan, kéo thả bố cục, chèn hình ảnh, logo và văn bản tùy biến.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Trình thiết kế mẫu in trực quan hoạt động trơn tru, dễ dàng chỉnh sửa layout.`
- **Trình thiết kế mẫu in - Bảng chi tiết (Detail Table)**
  - *Mô tả / Tham số:* Cấu hình bảng dữ liệu chi tiết hóa đơn/dịch vụ, thêm/xóa cột, tùy chỉnh độ rộng cột, căn lề và định dạng số tiền.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng cấu hình bảng chi tiết hỗ trợ đầy đủ thêm/xóa cột và định dạng dữ liệu.`
- **Trình thiết kế mẫu in - Trường dữ liệu động**
  - *Mô tả / Tham số:* Nhúng các trường tham số động từ cơ sở dữ liệu ({hotel_name}, {guest_name}, {room_number}, {total_amount}...) vào mẫu in.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Tham số động liên kết chuẩn xác với dữ liệu thực tế của từng booking/hóa đơn.`
- **Trình thiết kế mẫu in - Lịch sử phiên bản & Rollback**
  - *Mô tả / Tham số:* Lưu trữ lịch sử các phiên bản chỉnh sửa mẫu in, người cập nhật và hỗ trợ khôi phục (Rollback) về phiên bản trước.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Tính năng quản lý phiên bản mẫu in và khôi phục hoạt động rất tốt.`
- **Nguồn dữ liệu báo cáo (Report Data Source)**
  - *Mô tả / Tham số:* Quản lý nguồn dữ liệu báo cáo, khai báo kết nối bảng dữ liệu, trường thông tin và điều kiện truy vấn SQL.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Trình quản lý nguồn dữ liệu báo cáo hoạt động ổn định, cung cấp dữ liệu cho mẫu in.`
- **Định nghĩa danh mục báo cáo - Phân nhóm & Bộ lọc**
  - *Mô tả / Tham số:* Khai báo danh mục báo cáo tùy chỉnh: Mã báo cáo, Tên báo cáo, Nhóm hiển thị, Vị trí menu và các tham số lọc điều kiện.
  - *Đánh giá cá nhân:* `Hoàn thành nhưng còn lỗi (PMS note Dòng 325): Chức năng cấu hình danh mục báo cáo cần tối ưu giao diện trực quan hơn, xem xét tách riêng màn hình cấu hình thứ tự kéo thả theo từng module.`

## Module: Sao lưu & Khôi phục Database (7 mục)

- **Sao lưu Database - Sao lưu theo Chi nhánh**
  - *Mô tả / Tham số:* Tạo bản sao lưu cơ sở dữ liệu tức thời cho từng Chi nhánh cụ thể (HKT1, chi nhánh 2...) lưu trữ độc lập.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Sao lưu database theo chi nhánh hoạt động hoàn hảo, trích xuất dữ liệu MySQL chính xác.`
- **Sao lưu Database - Sao lưu Hệ thống chính (System DB)**
  - *Mô tả / Tham số:* Tạo bản sao lưu cho Database Hệ thống chính (`pms_system`) chứa thông tin người dùng, vai trò, chi nhánh và phân quyền.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Sao lưu database hệ thống chính hoạt động an toàn, bảo vệ toàn vẹn cấu hình người dùng.`
- **Sao lưu Database - Sao lưu Toàn bộ (ALL Multi-Database)**
  - *Mô tả / Tham số:* Tạo bản sao lưu toàn bộ hệ thống gồm tất cả các cơ sở dữ liệu chi nhánh và hệ thống chính vào một gói lưu trữ.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Sao lưu toàn bộ đa cơ sở dữ liệu hoạt động ổn định và nhất quán.`
- **Sao lưu Database - Tùy chọn lưu trữ Local & Google Drive**
  - *Mô tả / Tham số:* Tùy chọn lưu trữ bản sao lưu tại máy chủ cục bộ (Local Storage) hoặc đẩy trực tiếp lên đám mây (Google Drive).
  - *Đánh giá cá nhân:* `Đã hoàn thành: Cơ chế đồng bộ bản sao lưu lên đám mây và lưu trữ cục bộ hoạt động ổn định.`
- **Lịch sử sao lưu - Quản lý file backup & Tải về**
  - *Mô tả / Tham số:* Xem danh sách các bản backup đã tạo (Tên file, Dung lượng MB, Thời gian tạo, Người sao lưu, Trạng thái), hỗ trợ tải file về máy tính.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Quản lý danh sách backup và tải về file nén dữ liệu (.sql/.zip) hoạt động trơn tru.`
- **Khôi phục Database (Restore) - Import file & Khôi phục**
  - *Mô tả / Tham số:* Chọn file sao lưu tải lên từ máy tính hoặc chọn trực tiếp từ danh sách backup hệ thống để phục hồi cơ sở dữ liệu.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng import và restore cơ sở dữ liệu hoạt động chính xác.`
- **Khôi phục Database - Cơ chế an toàn xác nhận**
  - *Mô tả / Tham số:* Hiển thị cảnh báo nguy cơ ghi đè dữ liệu và yêu cầu xác nhận mật khẩu/mã an toàn trước khi thực hiện restore dữ liệu.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Cơ chế xác nhận bảo vệ an toàn dữ liệu hoạt động nghiêm ngặt, ngăn chặn ghi đè ngoài ý muốn.`

---

# HỆ THỐNG (PROVISTA SYSTEM) (8 Modules — 43 Mục)

## Module: Thông tin công ty (5 mục)

- **Hồ sơ pháp nhân doanh nghiệp**
  - *Mô tả / Tham số:* Khai báo thông tin pháp nhân doanh nghiệp sở hữu chuỗi khách sạn: Tên công ty, Đơn vị chủ quản, Mã số thuế doanh nghiệp.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Chức năng quản lý thông tin pháp nhân công ty hoạt động ổn định.`
- **Địa chỉ trụ sở & Thông tin liên hệ**
  - *Mô tả / Tham số:* Khai báo địa chỉ trụ sở chính, số điện thoại hotline, email doanh nghiệp và website chính thức.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Lưu trữ và cập nhật đầy đủ thông tin liên hệ trụ sở công ty.`
- **Ban điều hành & Đại diện pháp luật**
  - *Mô tả / Tham số:* Khai báo thông tin lãnh đạo doanh nghiệp: Chủ tịch hội đồng quản trị, Giám đốc điều hành, Kế toán trưởng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Quản lý thông tin ban điều hành doanh nghiệp hoạt động chính xác.`
- **Tài khoản ngân hàng pháp nhân**
  - *Mô tả / Tham số:* Khai báo thông tin tài khoản ngân hàng chính của pháp nhân doanh nghiệp phục vụ giao dịch hợp đồng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Quản lý thông tin ngân hàng doanh nghiệp hoạt động tốt.`
- **Thêm nhanh chi nhánh từ thông tin công ty**
  - *Mô tả / Tham số:* Nút tác vụ nhanh hỗ trợ thêm mới chi nhánh trực tiếp ngay từ giao diện Thông tin công ty.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Thao tác thêm nhanh chi nhánh hoạt động tiện lợi và chính xác.`

## Module: Chi nhánh (6 mục)

- **Danh sách chi nhánh cơ sở khách sạn**
  - *Mô tả / Tham số:* Quản lý danh sách các chi nhánh / cơ sở khách sạn trong hệ thống tập đoàn (Mã chi nhánh, Tên chi nhánh, Địa chỉ, SĐT, Email, MST).
  - *Đánh giá cá nhân:* `Đã hoàn thành: Quản lý danh sách chi nhánh hoạt động ổn định, hiển thị đầy đủ thông tin cơ sở.`
- **Kỳ kế toán chi nhánh**
  - *Mô tả / Tham số:* Thiết lập và quản lý kỳ kế toán hoạt động độc lập của từng chi nhánh trong chuỗi khách sạn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Cấu hình kỳ kế toán chi nhánh hoạt động chuẩn xác theo từng năm tài chính.`
- **Thêm mới chi nhánh & Tự động tạo Database**
  - *Mô tả / Tham số:* Thêm mới chi nhánh cơ sở: Tự động khởi tạo kết nối cơ sở dữ liệu MySQL riêng biệt tương ứng với mã chi nhánh mới.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Cơ chế tự động tạo và cấu hình database riêng cho chi nhánh mới hoạt động rất tốt.`
- **Chỉnh sửa thông tin chi nhánh**
  - *Mô tả / Tham số:* Cập nhật thông tin liên hệ, địa chỉ, số điện thoại và trạng thái vận hành của từng chi nhánh.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Cập nhật thông tin chi nhánh hoạt động trơn tru.`
- **Xóa chi nhánh - Mức 1: Chỉ xóa quản lý**
  - *Mô tả / Tham số:* Tùy chọn chỉ xóa thông tin chi nhánh khỏi danh sách quản lý, giữ lại nguyên vẹn cơ sở dữ liệu MySQL để lưu trữ lịch sử.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Tùy chọn xóa mềm chi nhánh hoạt động an toàn, bảo tồn toàn vẹn cơ sở dữ liệu.`
- **Xóa chi nhánh - Mức 2: Xóa toàn bộ Database (DROP)**
  - *Mô tả / Tham số:* Tùy chọn Xóa chi nhánh và XÓA TOÀN BỘ DATABASE (chạy lệnh DROP DATABASE với cảnh báo an toàn cấp cao).
  - *Đánh giá cá nhân:* `Đã hoàn thành: Cơ chế xóa database triệt để có cảnh báo xác nhận rõ ràng, hoạt động chính xác.`

## Module: Cơ cấu tổ chức (4 mục)

- **Sơ đồ phòng ban (Departments)**
  - *Mô tả / Tham số:* Khai báo danh mục các phòng ban trong doanh nghiệp (Lễ tân, Buồng phòng, Kinh doanh, Kế toán, Kỹ thuật, Ban giám đốc), mã và tên phòng ban.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Quản lý cơ cấu phòng ban hoạt động ổn định, trực quan.`
- **Vị trí công việc (Positions)**
  - *Mô tả / Tham số:* Khai báo danh mục chức danh / vị trí công việc theo từng phòng ban (Trưởng bộ phận, Giám sát, Nhân viên lễ tân, Thu ngân, Nhân viên buồng).
  - *Đánh giá cá nhân:* `Đã hoàn thành: Quản lý vị trí công việc hoạt động tốt, liên kết chặt chẽ với từng phòng ban.`
- **Phân quyền ứng dụng theo vị trí**
  - *Mô tả / Tham số:* Gán danh mục ứng dụng được phép truy cập theo từng vị trí công việc (PMS, POS, HRM, Kế toán...).
  - *Đánh giá cá nhân:* `Đã hoàn thành: Phân quyền ứng dụng theo chức danh hoạt động chính xác theo mô hình RBAC.`
- **Quản lý nhân sự theo vị trí công việc**
  - *Mô tả / Tham số:* Hiển thị danh sách nhân viên đang đảm nhiệm từng vị trí công việc và điều chuyển nhân sự giữa các vị trí dễ dàng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Quản lý nhân sự theo vị trí công việc hoạt động trực quan và thuận tiện.`

## Module: Vai trò & Phân quyền (6 mục)

- **Quản lý nhóm vai trò người dùng (Roles)**
  - *Mô tả / Tham số:* Khai báo danh mục vai trò người dùng (Admin tổng, Quản lý khách sạn, Trưởng lễ tân, Nhân viên lễ tân, Kế toán, Nhân viên buồng...), mã vai trò và mô tả.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Quản lý nhóm vai trò người dùng hoạt động ổn định, hỗ trợ tạo mới và sửa vai trò.`
- **Phân quyền theo Phân hệ & Màn hình**
  - *Mô tả / Tham số:* Phân quyền chi tiết theo từng Phân hệ (Module) và Màn hình / Đường dẫn (Route Path) trong toàn hệ thống.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Ma trận phân quyền theo từng màn hình chức năng hoạt động chính xác và chặt chẽ.`
- **Bộ 4 quyền chuẩn CRUD**
  - *Mô tả / Tham số:* Thiết lập chi tiết quyền Xem (View), Thêm (Add), Sửa (Edit), Xóa (Delete) trên từng màn hình chức năng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Bộ quyền CRUD được kiểm soát nghiêm ngặt trên cả Frontend và Backend API.`
- **Phân quyền tác vụ đặc thù nhạy cảm**
  - *Mô tả / Tham số:* Cấu hình quyền nghiệp vụ nhạy cảm: Quyền xóa bill/hóa đơn, Quyền post bill vào ngày cũ, Quyền sửa ngày quá khứ.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Cơ chế chặn và cấp quyền cho các hành động nhạy cảm hoạt động an toàn.`
- **Sao chép phân quyền (Copy Roles)**
  - *Mô tả / Tham số:* Tính năng Copy phân quyền - sao chép nhanh toàn bộ quyền từ một vai trò mẫu sang vai trò mới tạo để tiết kiệm thời gian thiết lập.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Tính năng sao chép phân quyền hoạt động nhanh chóng và chính xác 100%.`
- **Phân quyền xem báo cáo theo Rule**
  - *Mô tả / Tham số:* Phân quyền chi tiết quyền truy cập và xem từng báo cáo hệ thống theo vai trò người dùng.
  - *Đánh giá cá nhân:* `Hoàn thành nhưng còn lỗi (PMS note Dòng 326): Cần rà soát và hoàn thiện cơ chế phân quyền xem/không được xem báo cáo chi tiết theo rule vai trò người dùng.`

## Module: Nhân viên & Tài khoản (7 mục)

- **Quản lý hồ sơ nhân viên**
  - *Mô tả / Tham số:* Khai báo hồ sơ nhân viên: Mã nhân viên, Tên nhân viên, Ngày sinh, Điện thoại, Email, Địa chỉ, Ngày bắt đầu làm việc.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Quản lý hồ sơ nhân viên hoạt động ổn định, lưu trữ đầy đủ thông tin nhân sự.`
- **Gán Bộ phận & Vị trí công việc**
  - *Mô tả / Tham số:* Gán nhân viên vào Bộ phận và Vị trí công việc cụ thể trong doanh nghiệp để tự động thừa hưởng quyền tương ứng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Gán cơ cấu tổ chức cho nhân viên hoạt động chính xác.`
- **Tài khoản đăng nhập hệ thống (User Account)**
  - *Mô tả / Tham số:* Khai báo tài khoản đăng nhập hệ thống (Username, Email, Mật khẩu khởi tạo) gắn với hồ sơ nhân viên.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Khởi tạo tài khoản người dùng hoạt động trơn tru, bảo mật mật khẩu bằng mã hóa hash.`
- **Phân quyền vai trò tài khoản**
  - *Mô tả / Tham số:* Gán vai trò / nhóm quyền (Role) cho tài khoản nhân viên để kích hoạt quyền truy cập tương ứng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Gán vai trò cho tài khoản người dùng hoạt động chính xác.`
- **Phân quyền Chi nhánh làm việc**
  - *Mô tả / Tham số:* Thiết lập Chi nhánh chính và danh sách các chi nhánh được phép truy cập làm việc cho từng nhân viên.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Phân quyền đa chi nhánh hoạt động chuẩn xác, kiểm soát chuyển đổi chi nhánh.`
- **Phân quyền quản lý kho áp dụng**
  - *Mô tả / Tham số:* Phân quyền cho phép nhân viên truy cập và thao tác tại các kho hàng hóa / vật tư cụ thể của khách sạn.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Phân quyền kho áp dụng cho tài khoản hoạt động ổn định.`
- **Đặt lại mật khẩu (Reset Password)**
  - *Mô tả / Tham số:* Chức năng quản trị viên Đặt lại mật khẩu an toàn cho tài khoản nhân viên khi có yêu cầu.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Đặt lại mật khẩu tài khoản hoạt động tốt, có cảnh báo bảo mật.`

## Module: Lịch sử thao tác hệ thống (5 mục)

- **Nhật ký kiểm toán toàn diện (Audit Trail)**
  - *Mô tả / Tham số:* Ghi nhận chi tiết mọi giao dịch và hành động của người dùng trên toàn hệ thống (Thời gian, User, Màn hình, Hành động, Mã phòng, Booking...).
  - *Đánh giá cá nhân:* `Đã hoàn thành: Nhật ký kiểm toán tự động ghi nhận đầy đủ mọi thao tác của người dùng trên hệ thống.`
- **Bộ lọc tìm kiếm lịch sử nâng cao**
  - *Mô tả / Tham số:* Lọc tìm kiếm theo khoảng thời gian (Từ ngày - Đến ngày), theo Người dùng cụ thể, theo Phân hệ/Màn hình và loại hành động.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Bộ lọc lịch sử thao tác đa tiêu chí hoạt động nhanh và chính xác.`
- **Tìm kiếm nhanh theo Số phòng / Booking**
  - *Mô tả / Tham số:* Tìm kiếm nhanh lịch sử thao tác theo Số phòng hoặc Mã đăng ký đặt phòng để phục vụ tra soát sự cố.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Tìm kiếm nhanh theo số phòng và mã booking hoạt động rất tiện lợi.`
- **Xem chi tiết thay đổi dữ liệu (Data Diff)**
  - *Mô tả / Tham số:* Hiển thị popup so sánh dữ liệu chi tiết trước và sau khi thay đổi (Before & After) của bản ghi dữ liệu.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Giao diện so sánh dữ liệu thay đổi trực quan, phục vụ đắc lực công tác kiểm tra.`
- **Xuất dữ liệu nhật ký ra Excel**
  - *Mô tả / Tham số:* Xuất kết quả lọc nhật ký thao tác ra file Excel phục vụ lưu trữ kiểm toán định kỳ và báo cáo quản lý.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Xuất file Excel nhật ký thao tác đầy đủ định dạng và dữ liệu chuẩn.`

## Module: Tài khoản ngân hàng (4 mục)

- **Khai báo tài khoản ngân hàng doanh nghiệp**
  - *Mô tả / Tham số:* Khai báo danh mục tài khoản ngân hàng (Số tài khoản, Tên ngân hàng, Chi nhánh ngân hàng, Tiền tệ, Tên chủ tài khoản).
  - *Đánh giá cá nhân:* `Đã hoàn thành: Khai báo tài khoản ngân hàng hoạt động ổn định, lưu trữ đầy đủ thông tin tài khoản.`
- **Tài khoản trung gian (Intermediary Account)**
  - *Mô tả / Tham số:* Thiết lập đánh dấu tài khoản ngân hàng trung gian phục vụ thu hộ/chi hộ và thanh toán điện tử.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Đánh dấu tài khoản trung gian hoạt động chính xác theo cấu hình.`
- **Hạch toán kế toán & Phí cà thẻ máy POS**
  - *Mô tả / Tham số:* Gán tài khoản hạch toán kế toán tương ứng và cấu hình tỷ lệ phí quẹt thẻ máy POS theo từng loại thẻ thanh toán.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Cấu hình hạch toán và phí quẹt thẻ POS hoạt động chính xác.`
- **Quản lý trạng thái & Thao tác tài khoản**
  - *Mô tả / Tham số:* Bật/Tắt trạng thái hoạt động (Active/Inactive), thêm mới, chỉnh sửa thông tin và xóa tài khoản ngân hàng.
  - *Đánh giá cá nhân:* `Đã hoàn thành: Thao tác thêm, sửa, bật/tắt trạng thái tài khoản ngân hàng hoạt động tốt.`

## Module: Danh mục Quản lý & Kế toán (Mở rộng ERP) (6 mục)

- **Danh mục đối tượng (Khách hàng & Nhà cung cấp)**
  - *Mô tả / Tham số:* Khai báo và quản lý danh mục đối tượng khách hàng doanh nghiệp, đối tác lữ hành, nhà cung cấp (Mở rộng ERP).
  - *Đánh giá cá nhân:* `Chưa đạt — Đang phát triển: Phân hệ chuẩn bị tích hợp ERP đang được giữ trạng thái mở rộng (disabled) trong giai đoạn tiếp theo.`
- **Danh mục vật tư**
  - *Mô tả / Tham số:* Khai báo danh mục vật tư hàng hóa mua vào phục vụ vận hành khách sạn (Mở rộng ERP).
  - *Đánh giá cá nhân:* `Chưa đạt — Đang phát triển: Phân hệ chuẩn bị tích hợp ERP đang được giữ trạng thái mở rộng (disabled) trong giai đoạn tiếp theo.`
- **Danh mục hàng bán**
  - *Mô tả / Tham số:* Khai báo danh mục hàng hóa, dịch vụ thương mại bán lẻ mở rộng (Mở rộng ERP).
  - *Đánh giá cá nhân:* `Chưa đạt — Đang phát triển: Phân hệ chuẩn bị tích hợp ERP đang được giữ trạng thái mở rộng (disabled) trong giai đoạn tiếp theo.`
- **Định nghĩa mua hàng**
  - *Mô tả / Tham số:* Cấu hình quy trình mua hàng, duyệt đề xuất và theo dõi công nợ nhà cung cấp (Mở rộng ERP).
  - *Đánh giá cá nhân:* `Chưa đạt — Đang phát triển: Phân hệ chuẩn bị tích hợp ERP đang được giữ trạng thái mở rộng (disabled) trong giai đoạn tiếp theo.`
- **Thông số hệ thống**
  - *Mô tả / Tham số:* Quản lý danh mục các biến số và tham số cấu hình toàn cục của hệ sinh thái phần mềm (Mở rộng ERP).
  - *Đánh giá cá nhân:* `Chưa đạt — Đang phát triển: Phân hệ chuẩn bị tích hợp ERP đang được giữ trạng thái mở rộng (disabled) trong giai đoạn tiếp theo.`
- **Danh mục tài khoản kế toán**
  - *Mô tả / Tham số:* Thiết lập hệ thống danh mục tài khoản kế toán doanh nghiệp phục vụ tích hợp hạch toán (Mở rộng ERP).
  - *Đánh giá cá nhân:* `Chưa đạt — Đang phát triển: Phân hệ chuẩn bị tích hợp ERP đang được giữ trạng thái mở rộng (disabled) trong giai đoạn tiếp theo.`
