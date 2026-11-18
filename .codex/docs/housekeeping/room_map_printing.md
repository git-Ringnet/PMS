# In Room Map Buồng phòng

- Màn hình: `Buồng phòng -> Sơ đồ phòng`, file `frontend/src/pages/reservation/RoomMapPage.vue`.
- Chỉ xuất hiện ở chế độ danh sách, ngày hiện tại và ngữ cảnh Buồng phòng.
- Người dùng chọn ít nhất một phòng, nhấn biểu tượng In và chọn một trong hai mẫu:
  - `In mẫu nhân viên`: Phiếu làm phòng.
  - `In mẫu Sup`: Phiếu kiểm tra Supervisor.
- Chọn mẫu mở trực tiếp tab xem trước; không chuyển sang màn hình In phân công phòng và không sử dụng `PrintTasksTab`.
- Dữ liệu chỉ đọc từ các phòng đang chọn trên Room Map tại thời điểm in:
  - Số phòng, loại phòng và tình trạng phòng thực tế.
  - Ngày đang xem trên Room Map.
- Trường Nhân viên làm phòng/Supervisor để trống để hỗ trợ đơn vị không dùng phân công nhân viên.
- Không gọi API, không thay đổi phân công, quyền, cơ sở dữ liệu, schema hoặc import legacy.
