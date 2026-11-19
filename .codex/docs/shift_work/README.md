# Danh sách công việc

- API: `GET /api/shift-work/shuttle`, `GET /api/shift-work/birthdays`.
- Đưa đón: đọc từng dòng `bookings.shuttle_info[]`; dùng `date` và `type` của chính dòng đưa đón. Không suy diễn từ ngày đến/đi hoặc chuyến bay cấp booking.
- Sinh nhật (dòng 530): ngày sinh lặp theo năm phải nằm đồng thời trong khoảng lọc và giai đoạn ở của `booking_rooms`; áp dụng cho khách, trẻ em và em bé.
- Không thay đổi schema, migration, mapping/import legacy, payload lưu Đăng ký, Báo cáo hoặc Checkout.
