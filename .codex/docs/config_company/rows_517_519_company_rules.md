# Công Ty — Dòng 517, 518, 519

- Nguồn: workbook `DANH SÁCH BẢNG TRONG HỆ THỐNG.xlsx`, sheet `THEO DÕI VẤN ĐỀ CẦN ĐIỀU CHỈNH`, dòng 517–519.

## TC_DP_15 — Danh sách Công Ty

- Bảng có STT theo phân trang và cột `Trạng Thái Sử Dụng`; STT và thao tác không thuộc danh sách ẩn/hiện cột.
- Dropdown Người đặt phòng, Người bán, Thị trường, Nguồn khách và Chi nhánh hiển thị `Chưa chọn`; chọn giá trị này tiếp tục gửi `null` như trước.
- Xuất và file mẫu là `.xlsx`, một thuộc tính một cột; nhập nhận `.xlsx`, `.xls` và CSV legacy.
- Import kiểm tra cột Tên, email, công nợ, tham chiếu danh mục, mã/tên xung đột và dữ liệu trùng trong file; response trả kết quả từng dòng. CSV legacy tiếp tục cập nhật theo Mã hoặc Tên.
- Đây là import vận hành màn hình Công Ty, không xác lập thêm khóa mapping/import production từ `SP1302`.

## Dòng 517 — Trường bắt buộc

- `name` và `trading_name` luôn bắt buộc tại UI và API.
- `hotel_configs.CreateCompanyRequiredField` là thông số theo branch, mặc định rỗng.
- Giá trị hợp lệ, phân cách bằng `,`, `;` hoặc `|`: `address`, `tax_code`, `phone`, `email`, `customer_source_id`, `market_id`, `max_debt`, `bank_account`, `booker_id`, `sales_person_id`, `branch_id`.
- Key không hợp lệ bị bỏ qua; field hợp lệ có nền vàng, dấu `*` và API trả `422` nếu thiếu.
- Import CSV legacy giữ hợp đồng cũ; không tự diễn giải hoặc áp thêm key import từ thông số này.

## Dòng 518 — Gợi ý tên

- Chỉ ở form Thêm Công Ty.
- So khớp tên không phân biệt hoa/thường, dấu tiếng Việt và khoảng trắng.
- Danh sách chỉ để tham khảo, không chọn được, không tự điền và không sao chép dữ liệu.

## Dòng 519 — Xóa công ty đồng bộ

- Khi `SyncCompanyAllBranch=1`, kiểm tra bản sao cùng `code` ở toàn bộ chi nhánh hoạt động trước khi thay đổi.
- Có Booking, Thanh toán, Khuyến mãi F&B, Hóa đơn dịch vụ, hoặc đang là `CompanyDefault` ở bất kỳ chi nhánh nào: chặn toàn bộ thao tác.
- Không có phát sinh: đặt `is_active=false` cho toàn bộ bản sao; không hard-delete dữ liệu lịch sử.
- Không kiểm tra được chi nhánh hoặc có công ty cùng mã nhưng không khớp dữ liệu: chặn thao tác an toàn.
- Khi tắt đồng bộ chuỗi, giữ hard-delete cục bộ hiện có.
