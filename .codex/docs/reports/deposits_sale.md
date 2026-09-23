# Báo cáo đặt cọc — Module Sale

## Phạm vi

- Report code: `DEPOSITS_SALE`.
- Legacy source: `sp_039`, tham chiếu dữ liệu `vw_004`/`vw_003`.
- Runtime procedure: `rpt_deposits_sale`.
- Runtime source chính: `payments`.
- Template: `DEPOSITS_SALE_REFERENCE`.
- Header dùng block `columns` 30/70 như các báo cáo chuẩn, với nội dung `hotel-logo` và `hotel-information`; đường kẻ là block `divider` cao 16px để có vùng chọn trong Designer.

## Tham số

- `p_from_date`, `p_to_date`: lọc theo `payments.date`.
- `p_department`: mặc định `FO` theo mã bộ phận runtime hiện tại; cho phép danh sách mã phân cách bằng dấu phẩy.
- `p_shift`, `p_from_time`, `p_to_time`.
- `p_company`: lọc theo công ty hiện tại của payment hoặc Booking.
- `p_payment_method`: chọn một mã; khi chọn `Tất cả`, bảng phân bổ chỉ hiện các phương thức có giao dịch trong tập dữ liệu đã lọc.
- `p_user`: lọc theo mã người thực hiện, ưu tiên `payments.created_by`, dự phòng `payments.username`.
- `p_show_deposit`: mặc định bật; khi tắt loại các dòng `pack2 = DPR`.
- `p_show_amount_zero`: mặc định tắt.
- Quy tắc hiện tại theo yêu cầu nghiệp vụ: loại mọi payment có `payment_method_id = AC` khỏi dữ liệu báo cáo trước khi tính tổng và render chi tiết/bảng phân bổ.

## Quy tắc đã triển khai

- Mỗi cột của bảng phân bổ khai báo `headerStyle` và `cellStyle` trong `content_json`: header và hàng tổng có nền xám; mọi dòng dữ liệu có nền trắng, chữ thường.
- CSS không dùng vị trí đầu/cuối của dòng để tô nền dữ liệu; đây là nguyên nhân khiến dòng phương thức đầu và cuối từng có màu xám khác các dòng giữa.
- Designer cho phép cấu hình định dạng riêng từng hàng/từng ô của `static-table`; style ô ưu tiên hơn style hàng.
- Chỉ lấy payment chưa soft-delete và `edit_flag = 0`.
- `PaymentMethod = AC` bị loại khỏi nguồn của `DEPOSITS_SALE`; không xuất hiện ở chi tiết, tổng đặt cọc, tổng chung hoặc bảng phân bổ.
- Mã người thực hiện giao dịch dùng `COALESCE(NULLIF(payments.created_by, ''), NULLIF(payments.username, ''), '')`; đây là nguồn cho cột `Người Dùng`, bộ lọc người dùng và `Mã Thu Ngân`.
- Migration `2026_09_21_120000` đồng bộ procedure/template và mã thu ngân; migration `2026_09_21_130000` cập nhật màu hàng phân bổ; migration `2026_09_21_140000` đồng bộ cấu hình Design, HTML biên dịch và CSS; migration `2026_09_21_150000` cập nhật riêng hai block header theo ID, biên dịch lại header từ cấu hình đang lưu và giữ nguyên detail/footer/CSS trên `mysql` và `mysql_hkt1` đến `mysql_hkt4`.
- `amount < 0` hiển thị là `Hoàn Trả`.
- `pack2 = DPR` hiển thị là `Đặt cọc`; dòng khác hiển thị là `Thu Ngân`.
- Dữ liệu chi tiết được sắp xếp theo ngày, giờ, công ty, Booking và payment.
- Template nhóm theo `GroupHeader` (`Đặt cọc`, `Thu Ngân`, `Hoàn Trả` kết hợp phương thức thanh toán). Hàng `Tổng Theo C.ty` cộng `group.sum.Amount` của nhóm đang hiển thị, không dùng tổng công ty cộng dồn qua mọi phương thức; tiếp theo là tổng tiền đặt cọc và tổng chung.
- Bảng phân bổ động nhóm theo cặp mã phương thức thanh toán và mã người thực hiện; mỗi cặp có phát sinh giao dịch xuất hiện một dòng. Bảng giữ các cột tiền `Thu Ngân`, `Đặt Cọc`, `Thu Ngân + Đặt Cọc`, `Hoàn Tiền`, `Tổng`, và tổng cộng cuối bảng.
- Mẫu trình bày bám ảnh legacy: khổ A4 dọc, header dùng bố cục chuẩn của các báo cáo khác, 11 cột chi tiết, `Mã HĐ`, bảng phân bổ và ba vị trí ký.
- `Mã HĐ` lấy từ `service_bills.InvoiceId` theo `service_bills.PaymentId = payments.id`.
- `content_json` chứa cấu hình Design: block, nội dung, binding, độ rộng cột và style. `content_html` được biên dịch từ JSON; runtime render HTML đã lưu cùng CSS, không đọc lại JSON mỗi lần xem báo cáo. Thay đổi JSON phải biên dịch và lưu lại HTML.
- Bảng phân bổ có chiều rộng block `84%`; từng cột lấy tỷ lệ từ `columns[].width`. Preview Designer dùng `table-layout: fixed` và áp tỷ lệ này lên header/detail cells để khớp HTML báo cáo.
- Các ô preview của bảng động cho phép ngắt chuỗi binding dài (`overflow-wrap: anywhere`) để placeholder không tràn qua ô kế bên; quy tắc này chỉ dùng trong Designer.
- Thứ tự filter: ngày, ca, khung giờ, bộ phận, công ty, người dùng, phương thức thanh toán, các toggle hiển thị.
- CSS riêng của mẫu chỉ đặt table layout, reset margin của bảng và quy tắc in lặp header/tránh ngắt dòng. Màu, font, căn lề và kích thước block/cột được biên dịch từ Design thành inline style trong HTML.
- Designer hỗ trợ chỉnh trực tiếp `Padding Top/Bottom/Left/Right` và `Margin Top/Bottom/Left/Right` trên từng block; thay đổi được lưu trong `content_json` và biên dịch lại khi preview/lưu.
- Các block của mẫu đặt cọc lưu rõ font, margin, padding và width trong `content_json`; không phụ thuộc giá trị mặc định `13px/0px` của Designer khi lưu lại.
- Renderer vẫn cung cấp style nền chung cho bảng. Hàng nhóm và hàng tổng hiện lưu màu/chữ trong Design; padding và một số thuộc tính viền của các hàng này còn dùng mặc định chung do Inspector chưa expose đủ control.
- `payments.created_by` là nguồn ưu tiên cho `Username`, `payments.username` là nguồn dự phòng. Migration `2026_09_21_160000` đồng bộ procedure và chỉ đổi binding của ô tổng phụ trong template đã lưu, giữ các cấu hình Design khác; đã áp dụng trên `mysql` và `mysql_hkt1`–`mysql_hkt4` ngày 21/09/2026.

## Phạm vi ảnh hưởng

- Thay đổi procedure và template được triển khai bằng migration riêng; adapter tạo bảng phân bổ thuộc báo cáo này.
- `ReportDatasetEnricher` có một nhánh giới hạn ở `DEPOSITS_SALE`; các báo cáo khác không bị ảnh hưởng.
- Không sửa `PaymentController`, luồng Deposit/Advance Payment, Checkout, `ReportsPage.vue`, route hoặc report renderer dùng chung.

## Chưa xác minh

- Đối chiếu trực tiếp output với database legacy `sp_039`/`vw_003`/`vw_004`.
- Trước khi áp dụng migration `2026_09_21_160000`, database HKT1 ngày 09/08/2026 có `created_by` nhưng `username` rỗng cho các dòng BT/CA/CD/VO; procedure lưu trong DB chưa đọc `created_by`, dù migration `2026_09_21_120000` có logic đó trong mã nguồn. Sau khi chạy migration mới, cả năm database ứng dụng đã xác nhận procedure và Design/HTML mang cấu hình mới.
- Chưa nghiệm thu trực quan bản preview/export trên browser sau migration `2026_09_21_160000`.
- Bộ schema legacy trong repository không chứa source của `sp_039`/`vw_003`/`vw_004`; hành vi legacy với phương thức AC chưa xác minh.
- Mã bộ phận Sale thực tế của từng chi nhánh legacy.
- Quy tắc legacy đầy đủ của tham số `@ViewDatCoc`.
- Quy tắc hiển thị các dòng đã chuyển/tách/đã dùng để thanh toán.
- Layout chi tiết ngoài ảnh mẫu và số liệu thực tế của phần phân bổ tiền.

## Định dạng bảng phân bổ

- `Bảng Phân Bổ Tiền Tệ` dùng dynamic table nhận `currency_allocations` từ `DepositsSaleDataAdapter`.
- Các cặp phương thức/mã thu ngân được sắp xếp theo mã phương thức rồi mã thu ngân.
- Hàng tổng cộng cộng từng cột tiền từ các dòng phân bổ; AC đã bị procedure loại trước bước tổng hợp.
