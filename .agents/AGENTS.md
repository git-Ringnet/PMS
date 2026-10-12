# Project-Scoped Rules

- **Trước khi chỉnh sửa các file dùng chung**: Hỏi trước người dùng và đưa ra phân tích xem thay đổi có ảnh hưởng đến các file khác hoặc các phần việc đang thực hiện hay không.
- **Always be concise**: Câu trả lời luôn ngắn gọn, đi vào trọng tâm.
- **Use bullet points**: Sử dụng danh sách gạch đầu dòng (bullet points) để trình bày thông tin.
- **Do not explain unless asked**: Không giải thích dài dòng trừ khi được yêu cầu.
- **Nhật ký tiến độ (DAILY_LOG.md)**: 
  - Sau khi hoàn thành một nghiệp vụ/tính năng, tự động cập nhật chi tiết vào [.agents/DAILY_LOG.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.agents/DAILY_LOG.md).
  - Đầu mỗi phiên làm việc mới, đọc file [.agents/DAILY_LOG.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.agents/DAILY_LOG.md) để nắm tiến độ và công việc tiếp theo.
- **Quy chuẩn đồng bộ giao diện Frontend (FE Design System)**:
  - Đầu mỗi phiên làm việc hoặc trước khi tạo trang mới, form mới, modal mới hay sửa bất kỳ UI nào, bắt buộc đọc [.codex/docs/frontend_design_system/README.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/frontend_design_system/README.md).
  - Nghiêm ngặt tuân thủ: Font Roboto; nội dung đồng nhất 12px (`text-xs`); tiêu đề/nhãn/tổng `semi-bold` (600) `#000000D9`; ô nhập `regular` (400) `#000000D9`; placeholder xám `#A8B0BF`. Cấm dùng cỡ chữ < 12px.
  - Nút chức năng phải dùng đúng class trong `style.css`: Lưu = `.btn-pms-primary`, Đóng = `.btn-pms-close` (nền xanh, icon X, chữ trắng đồng bộ style với nút Lưu), Xóa = `.btn-pms-danger`, Công cụ/Bộ lọc = `.btn-pms-secondary`. Chiều cao toolbar đồng bộ 32px (`h-8`).
  - Trường bắt buộc: Nền vàng nhạt `#FFF8DB`, viền `#F1DD8A`, nhãn có `*` đỏ và chặn lưu khi dữ liệu rỗng.
  - Thanh trên cùng của các form/modal: Bắt buộc cùng màu với màu theme hệ thống (`var(--pms-custom-theme, #006bdb)` / `topbarThemeBg`), cấm hardcode màu navy/đen.
  - Ngày tháng & Số: Ngày tháng năm theo dạng `dd/mm/yyyy` (placeholder `dd/mm/yyyy`). Khi nhập tay bằng số: Vừa gõ xong ngày (2 chữ số) và tháng (2 chữ số) thì hệ thống tự động hiển thị dấu gạch chéo `/` ngay lập tức (ví dụ: `18` -> `18/`, `12` -> `18/12/`) để biết đang nhập tới đâu; bắt buộc phải nhập đủ 8 chữ số (`ddmmyyyy`) mới tự động chuyển đổi sang `dd/mm/yyyy`, tuyệt đối không tự động chuyển đổi khi mới nhập 6 chữ số; rào lại ở tháng chỉ nhập được tối đa 12 tháng (01 đến 12), nếu số đầu tiên ở phần tháng lớn hơn 1 (2 đến 9) thì tự chuyển thành `0X/` và nhảy sang vị trí nhập năm luôn (ví dụ gõ `5` ở tháng thì tự thành `05/` rồi cho nhập năm luôn); số tiền/số lượng phải có dấu phẩy phân cách hàng nghìn, triệu, tỉ (`100,000`). Nút xóa nhanh `x` (nếu có) phải tách biệt, không dính sát text.
  - Bo góc: Các modal/form đồng bộ bo góc `rounded-xl` (12px), card/hộp chức năng `rounded-lg` (8px).
  - Phím tắt Esc: Mọi modal/form khi hiển thị phải hỗ trợ nhấn `Esc` (`Escape`) để đóng form nhanh. Khi đã có nút `x` trên header và hỗ trợ phím `Esc`, có thể lược bỏ nút Đóng ở footer để tối ưu không gian, chỉ giữ nút Lưu.
