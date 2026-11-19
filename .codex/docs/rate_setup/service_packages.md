# Cấu hình giá - Gói dịch vụ

- UI: `frontend/src/pages/config/components/RateSetup.vue`, tab `Gói Dịch Vụ`.
- API cấu hình: `GET|POST /api/service-packages`, `GET|PUT|DELETE /api/service-packages/{servicePackage}`; quyền ghi `config.rates.*`.
- Một gói có header hiệu lực, ngày trong tuần và tối thiểu một chi tiết dịch vụ thuộc `hotel_services`.
- Đăng ký chọn tối đa một gói trong form thông tin Đăng ký. Khi lưu, backend tạo các dòng `booking_room_services` dự kiến, `is_posted=0`, `is_room=0`, folio Master của phòng hoạt động đầu tiên; không tạo hay sửa `service_bills`, thanh toán hoặc dữ liệu Checkout.
- Ngày dòng dịch vụ là `arrival_date + order_day - 1`; backend từ chối nếu ngày đó nằm ngoài thời gian ở hoặc gói không hiệu lực tại ngày đến.
- Khi đổi/bỏ gói, chỉ các dòng gói chưa post được tạo lại/xóa. Nếu bất kỳ dòng gói đã post, backend chặn thay đổi để không sửa hóa đơn đã ghi nhận.
- `bookings.service_package_snapshot` lưu mã, giá, thuế/phí và chi tiết tại thời điểm áp dụng; sửa gói cấu hình về sau không sửa các dòng đăng ký cũ.
- Legacy: xem `.codex/docs/database_mapping/tables/service_packages.md`; không có import production cho gói dịch vụ.
