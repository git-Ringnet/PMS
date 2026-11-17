# Tìm kiếm hóa đơn Buồng phòng

- Màn hình: `Buồng phòng -> Dịch vụ -> Tìm kiếm hóa đơn` (`invoice-search`).
- Frontend: `frontend/src/pages/housekeeping/components/SearchInvoiceTab.vue`.
- Backend: `BookingRoomServiceController::searchHousekeepingInvoices` và `cancelHousekeepingInvoice`.
- API:
  - `GET /api/housekeeping/service-bills`.
  - `POST /api/housekeeping/service-bills/{billId}/cancel`.

## Quy tắc đã triển khai

- Danh sách tìm kiếm gồm bill HK và bill FO post; lọc theo ngày, phòng, outlet, bộ phận, user và trạng thái thanh toán. Không lọc theo Mã ĐK vì đây không phải trường hiển thị trên màn hình danh sách.
- Khi chọn nhiều điều kiện, API kết hợp chúng bằng `AND`; kết quả chỉ gồm bill thỏa đồng thời mọi điều kiện đã chọn.
- Chi tiết sản phẩm lấy từ `housekeeping_service_bill_details`; bill dịch vụ liên kết qua `service_bills`.
- Chỉ bill HK chưa chỉnh sửa, chưa thanh toán và chưa có `VatNumber` mới được xóa.
- Bill FO, bill đã thanh toán, bill đã xuất VAT hoặc bill đã xóa không được xóa.
- Xóa bill tạo các dòng âm audit cho bill HK và bill dịch vụ, đồng thời đánh dấu bill gốc đã xóa.
- Bấm vào dòng hóa đơn mở popup chi tiết; nút In và Hủy hóa đơn nằm trong popup.
- Form Thêm từ Tìm kiếm hóa đơn luôn post trong ngữ cảnh HK; sau event `success` danh sách được tải lại ngay với nguyên bộ lọc hiện hành.
- Popup chi tiết đọc theo `service_bill_id` và hiển thị snapshot sản phẩm, số lượng, đơn giá, giảm/tăng giá cùng phí/thuế; bill miễn phí có nhãn nổi bật tại thông tin chung.
- Bấm vào dòng hóa đơn mở popup chi tiết; nút In và Hủy hóa đơn nằm trong popup.
- Nút In tạo bản in từ dữ liệu bill và chi tiết sản phẩm đang hiển thị.
- Component dùng một node gốc để tương thích `<Transition>` khi chuyển tab; prop `initialRoomId` chỉ được truyền vào tab Thêm dịch vụ.
- Component có một node gốc để hỗ trợ chuyển tab bằng `<Transition>`; `initialRoomId` chỉ được truyền cho tab Thêm dịch vụ.

## Legacy đối chiếu

- `SP6000`: bill tổng Buồng phòng.
- `SP6001`: chi tiết sản phẩm.
- `SP3000`/`SP3001`: bill dịch vụ và chi tiết dịch vụ liên kết.
- `SP3002`: thanh toán, bao gồm thanh toán miễn phí `CL`.
