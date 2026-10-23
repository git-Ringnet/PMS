# Dòng 275 — Note đỏ mới ngày 23/10/2026

Đã sửa và kiểm chứng tự động; người dùng cho phép commit ngày 23/10/2026. Chưa push.

## Yêu cầu ưu tiên

Nguồn chính: ảnh khách note đỏ 23/10 do người dùng gửi. Phần note mới bổ sung yêu cầu cho thông số `SyncRoomDateByBookingDate`; không áp dụng điều kiện cũ để bỏ qua toàn bộ phòng khi ngày đến booking đã ở quá khứ.

Ví dụ của khách:

1. Ngày PMS **31/8**, booking đến **30/8**, có phòng Inhouse **30/8–1/9**.
2. Đổi ngày đi booking sang **4/9**: phòng Inhouse không đổi — đúng.
3. Thêm phòng mới còn trạng thái **Đăng ký (0)**, kỳ ở **31/8–4/9**.
4. Đổi ngày đi booking sang **6/9**: phòng đăng ký phải thành **31/8–6/9**; phòng Inhouse vẫn **30/8–1/9**.

Booking có ngày đến 30/8 trước ngày PMS 31/8 nên không kéo ngày đến phòng mới lùi về 30/8. Chỉ đồng bộ ngày đi của phòng đăng ký. Phòng mới có **6 đêm**, booking có **7 đêm**; không sao chép số đêm booking sang phòng mới.

## Nguyên nhân đã xác minh

- Backend `BookingController` dùng điều kiện chung `ngày đến booking >= ngày PMS` để bật đồng bộ cả ngày đến và ngày đi. Booking bắt đầu 30/8 không qua điều kiện, nên phòng mới vẫn ở ngày đi 4/9.
- Frontend `CreateRegistrationPage` cũng dùng điều kiện này trong cả đổi ngày tiêu đề và đổi số đêm; dữ liệu hiển thị trước lưu không kéo theo ngày đi mới.
- Nhánh lưu kèm `room_allocations` còn cập nhật ngày phòng trong database trước bước kiểm tra AV. Bộ kiểm tra có thể thấy kỳ ở mới là dữ liệu đã lưu và bỏ qua như trường hợp không thay đổi. Đồng thời vòng lặp chỉnh ngày trên mảng tạm không bảo đảm cập nhật dữ liệu payload dùng để kiểm tra.
- Đã tái hiện trước sửa: **2 test HTTP và 2 test frontend lỗi**, đều nhận ngày đi phòng **4/9** thay vì **6/9**. Test AV tiếp tục phát hiện nhánh có danh sách phòng cho lưu dù cấu hình không cho over; sau sửa đã bị chặn và rollback đúng.

## Thay đổi

- Tách điều kiện đồng bộ ngày đến khỏi điều kiện đồng bộ ngày đi; chỉ cập nhật tự động phòng **trạng thái 0**.
- Booking đến trong quá khứ: giữ ngày đến riêng của phòng, vẫn kéo ngày đi theo booking khi đủ điều kiện. Phòng Inhouse và các trạng thái khác không tự kéo theo.
- Tính số đêm riêng từ ngày đến/đi của từng phòng, áp dụng cả thay ngày đi và thay số đêm tiêu đề.
- Cả request chỉ có thông tin booking và request kèm danh sách phòng đều chuẩn hóa kỳ ở sẽ lưu, kiểm tra AV theo kỳ ở đó trước khi ghi. Nhánh danh sách không ghi ngày mới quá sớm; lỗi sẽ rollback cả booking/phòng và không để phòng bị xóa mềm dở dang.
- Giữ cấu hình đồng bộ `=0`, các quy tắc AV/over đang có, kiểm tra phòng nằm trong kỳ booking và quy tắc bảo vệ phòng Inhouse. Không sửa tên booking, phân quyền hoặc các phần nghiệp vụ khác.

## Test case nghiệm thu

Thực hiện với dữ liệu thử và ngày PMS phù hợp; các ngày 30/8, 31/8, 4/9, 6/9 dưới đây là fixture theo ví dụ khách, không yêu cầu đổi ngày hệ thống vận hành để nghiệm thu.

| Mã | Thao tác | Kết quả mong đợi |
|---|---|---|
| 275-01 | Cấu hình =1. Ngày PMS 31/8, booking/phòng Inhouse 30/8–1/9. Đổi booking đến 4/9. | Booking đi 4/9; phòng Inhouse vẫn đi 1/9. |
| 275-02 | Thêm phòng đăng ký 31/8–4/9 vào booking trên; đổi ngày đi booking thành 6/9. | Phòng mới thành 31/8–6/9, 6 đêm; Inhouse vẫn 30/8–1/9. |
| 275-03 | Đóng và mở lại booking sau lưu, đối chiếu `booking_rooms`. | Ngày phòng mới và số đêm đã lưu đúng; không chỉ đúng trên giao diện tạm. |
| 275-04 | Lặp bằng cách sửa số đêm booking từ 5 thành 7. | Ngày đi booking 6/9; phòng đăng ký giữ ngày đến 31/8, tính 6 đêm; Inhouse không đổi. |
| 275-05 | Thử cả luồng cập nhật thông tin booking và lưu màn có danh sách phòng. | Kết quả giống nhau; payload ngày phòng cũ không làm mất đồng bộ ngày đi. |
| 275-06 | Cấu hình =0; sửa ngày đi booking trong Thông tin đăng ký. | Không tự đổi ngày phòng con. Các giới hạn kỳ booking/phòng vẫn được kiểm tra. Trên tiêu đề, trường ngày/số đêm giữ quy tắc khóa hiện có. |
| 275-07 | Cấu hình =1; booking chưa bắt đầu, ngày đến ≥ ngày PMS; thay ngày đến/đi booking. | Phòng đăng ký vẫn đồng bộ bình thường; phòng trạng thái khác được bảo vệ theo quy tắc hiện có. |
| 275-08 | Có thêm phòng đã trả hoặc lịch sử; thực hiện 275-02. | Chỉ phòng đăng ký được kéo ngày; không tự kéo phòng đã trả/lịch sử theo tiêu đề. |
| 275-09 | Đặt hết AV trong hai đêm gia hạn 4/9–6/9; tắt cho phép over, rồi lưu đổi booking sang 6/9. | Bị chặn; booking và phòng mới vẫn đi 4/9. Không lưu một phần. |
| 275-10 | Lặp 275-09 bằng request có danh sách phòng, gửi ngày phòng cũ 4/9. | Vẫn kiểm tra AV theo kỳ đến 6/9 và chặn; phòng không bị xóa mềm sau lỗi. |
| 275-11 | Rút ngắn kỳ booking đến mức phòng Inhouse nằm ngoài kỳ booking. | Bị chặn theo kiểm tra kỳ ở hiện hành; không kéo ngày Inhouse để né lỗi. |
| 275-12 | Sửa trực tiếp kỳ ở phòng mà không đổi kỳ booking, gồm cấu hình =0. | Giữ nghiệp vụ chỉnh ngày phòng trực tiếp; không tự kéo lại theo tiêu đề nếu booking không đổi ngày. |

## Kiểm chứng đã thực hiện

- Backend có **6 ca mới**: bật/tắt sync × request chỉ tiêu đề/kèm phòng; và hai ca AV không đủ phải rollback.
- Hồi quy `BookingBusinessRulesTest` + `BookingFourSectionsTest`: **113/113 đạt, 659 assertions**. Gồm các quy tắc kỳ booking, phòng Inhouse, chỉnh ngày phòng trực tiếp và dữ liệu dịch vụ theo ngày.
- Frontend test mới: **4/4 đạt** cho đổi ngày đi, đổi số đêm, bật/tắt sync. Nhóm frontend liên quan tổng **17/17 đạt**.
- Build production, PHP lint và `git diff --check` đạt. Build thử được đặt ngoài repo và dọn sau kiểm tra.
- Chưa thao tác nghiệm thu trên UI/database thật. Test frontend chạy logic từ component với fixture; không khẳng định đã chạy mọi case trong bảng trên bằng trình duyệt.
- Không migrate/seed hoặc chỉnh dữ liệu vận hành. Người dùng đã cho phép commit phần 275; không push. Giữ riêng thay đổi test/tài liệu dòng 156 đang chờ nghiệm thu.
