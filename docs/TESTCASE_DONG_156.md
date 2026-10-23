# Dòng 156 — Kiểm tra note đỏ khi sửa ngày bắt đầu khóa

Rà soát ngày 23/10/2026. Người dùng xác nhận giữ và commit test hồi quy cùng tài liệu. Không push.

## Yêu cầu ưu tiên của khách

Đã đối chiếu ảnh người dùng và Excel `DANH SÁCH BẢNG TRONG HỆ THỐNG (8).xlsx`, sheet **THEO DÕI VẤN ĐỀ CẦN ĐIỀU CHỈNH**, dòng 156, cả ảnh cột G.

**Note đỏ 24/09:** ngày hệ thống 24/9, khóa dự kiến bắt đầu 25/9; sửa ngày bắt đầu thành 24/9 thì phòng bắt đầu khóa ngay, `rooms.room_status_code` phải đổi đúng `ooo` hoặc `oos`.

Phần yêu cầu đi kèm vẫn cần giữ: khóa đã bắt đầu chỉ cho sửa ngày kết thúc/ghi chú/tiến độ; khóa tương lai được sửa ngày bắt đầu; lưu phải kiểm tra booking trùng, AV và booking chưa gán số theo hai thông số của khách.

## Nguyên nhân và tình trạng code

- Trong phiên bản trước commit `b44a1a56`, sửa một khóa có `is_active=1` luôn ghi `ooo` vào phòng: chưa phân biệt OOO/OOS hoặc ngày khóa đã tới/chưa tới. Luồng sửa hàng loạt cập nhật lịch khóa nhưng thiếu bước đồng bộ trạng thái phòng. Hai đường sửa vì thế không cho kết quả nhất quán.
- Commit **`b44a1a56` đã sửa phần này trước lượt rà soát hiện tại**. Cả sửa đơn lẻ và hàng loạt đều gọi `syncRoomStatusAfterLockEdit()`.
- Hàm đồng bộ dùng **ngày hệ thống PMS**: ngày bắt đầu chưa tới giữ khóa `New`; ngày bắt đầu tới ngày hệ thống chuyển `Active` và ghi `ooo`/`oos` theo loại khóa, đồng thời phát sự kiện cập nhật tình trạng phòng. Không lấy ngày lịch máy thay cho ngày PMS khi PMS đã có ngày hệ thống.
- Các kiểm tra trùng booking, AV và khả năng gán nguyên giai đoạn chạy trước bước lưu. Nếu bị chặn, không đổi lịch khóa hay trạng thái phòng.
- Lượt này **không sửa thêm code production** vì các ca tái hiện note đỏ đều đạt với bản hiện có. Chỉ bổ sung test cho cả hai loại khóa, hai đường sửa và trường hợp đang có khách; không thay đổi logic cấu hình của khách.

## Test case nghiệm thu

Thực hiện trên môi trường/dữ liệu thử. Gọi ngày hệ thống PMS là **N**; có thể dùng N=24/9 để đối chiếu ví dụ khách. Không cần thay ngày hệ thống vận hành chỉ để chạy test.

| Mã | Chuẩn bị và thao tác | Kết quả mong đợi |
|---|---|---|
| 156-01 | Phòng trống, khóa OOO từ N+1 đến N+3. Chỉ sửa ghi chú/tiến độ, lưu. | Khóa vẫn `New`, phòng chưa chuyển OOO; nội dung sửa được lưu. |
| 156-02 | Như trên, sửa ngày bắt đầu từ N+1 về N bằng form Chỉnh sửa. | Lưu thành công; khóa `Active`, `rooms.room_status_code=ooo` ngay. Mở lại vẫn đúng. |
| 156-03 | Lặp 156-02 cho khóa OOS. | Khóa `Active`, `rooms.room_status_code=oos`, không bị ghi nhầm `ooo`. |
| 156-04 | Lặp 156-02/03 bằng sửa trực tiếp danh sách/hàng loạt. | Cùng kết quả với sửa đơn lẻ; các loại khóa không bị đổi. |
| 156-05 | Sau khi kích hoạt khóa, xem Sơ đồ phòng và tải lại trang. | Phòng hiển thị OOO/OOS tương ứng; khớp dữ liệu `rooms`. |
| 156-06 | Khóa có ngày bắt đầu ≤ N: sửa ngày kết thúc, ghi chú, tiến độ bảo trì. | Cho lưu nếu không vi phạm điều kiện khác; ngày bắt đầu giữ nguyên. |
| 156-07 | Thử sửa ngày bắt đầu của khóa đã bắt đầu. | UI khóa trường này; gửi API sửa khác ngày bắt đầu cũng bị chặn, dữ liệu giữ nguyên. |
| 156-08 | Khóa tương lai N+1; sửa về N khi phòng đang có khách/booking trùng. Thử cả `force=true` tại API. | Bị chặn do trùng booking; lịch vẫn bắt đầu N+1, khóa vẫn `New`, tình trạng phòng đang ở không bị đổi sang OOO/OOS. |
| 156-09 | Gia hạn khóa trùng booking đã gán số phòng. | Bị chặn, không thay đổi ngày kết thúc hoặc tình trạng phòng. |
| 156-10 | Gia hạn làm AV âm, `AllowOverRoomTypeRoomKind=0`; sau đó kiểm tra khi =1. | =0 chặn; =1 yêu cầu xác nhận. Chưa xác nhận thì không lưu; còn phải qua kiểm tra booking chưa gán số. |
| 156-11 | Gia hạn làm booking chưa gán số không còn phòng trống liên tục, `AllowLockRoomCauseUnassignableRoomBK=0`, rồi =1. | =0 chặn kể cả `force=true`; =1 hỏi xác nhận, đồng ý mới lưu nếu không vướng điều kiện chặn khác. |
| 156-12 | Khóa tương lai: sửa ngày bắt đầu thành N-1; hoặc đặt ngày kết thúc trước ngày bắt đầu. | Bị chặn; không thay đổi lịch hay trạng thái phòng. |

## Bằng chứng kiểm tra đã chạy

- Trước bổ sung: `RoomLockTest` **25/25 đạt, 95 assertions**.
- Bổ sung 8 ca HTTP qua data provider: OOO/OOS × đơn lẻ/hàng loạt, gồm kích hoạt đúng ví dụ 25/9 → 24/9 và chặn khi có khách. Kiểm tra trực tiếp cả `room_locks` và `rooms`, ghi chú/tiến độ, trạng thái trước/sau và sự kiện cập nhật phòng.
- Sau bổ sung: `RoomLockTest` **33/33 đạt, 175 assertions**. Các test cũ kiểm tra gia hạn/AV/booking chưa gán số vẫn đạt.
- Frontend `room-lock-row156.test.js`: **3/3 đạt**, kiểm tra logic gửi request đúng ngày PMS, tải lại danh sách và thông báo cập nhật phòng sau lưu; kiểm tra chặn ngày tương lai sửa về trước ngày hệ thống. Đây là test logic của component, không phải UAT trình duyệt.
- Mở rộng thêm `RoomOccupancyStatisticsTest`: tổng **34/35 đạt**, một test có sẵn `test_all_room_map_statistics_follow_business_rules` lỗi kỳ vọng thống kê (2 khác 1). Không có code production nào được thay đổi trong lượt này; chưa sửa lỗi thống kê ngoài phạm vi 156.
- Test backend dùng SQLite bộ nhớ. Không sửa database vận hành, không chạy migration/seed vận hành. Chưa thao tác nghiệm thu UI thực tế hoặc kiểm tra client khác nhận realtime; các case bảng trên để người dùng nghiệm thu.

Nếu môi trường thực tế vẫn gặp note đỏ, cần đối chiếu phiên bản đang triển khai có commit `b44a1a56`, ngày PMS và bản ghi khóa/phòng cụ thể trước khi sửa thêm. Không khẳng định lỗi dữ liệu lịch sử đã tự được sửa chỉ dựa vào test.
