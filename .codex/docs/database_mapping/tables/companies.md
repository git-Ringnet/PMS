# `companies`

- Legacy: `SP1302`
- Trạng thái: `Đã xác minh`
- Ghi chú: Giữ legacy company ID.
- Báo cáo `EXPECTED_BREAKFAST` chỉ đọc tên công ty/đối tác để hiển thị mẫu; không tạo FK từ mã legacy.
- 2026-09-10: Bổ sung `legacy_id` nullable unique giữ `SP1302.Ma` và `legacy_re_credit_limit` nullable giữ raw `SP1302.ReCreditLimit` cho báo cáo `sp_212`. Không backfill.
- `sales_person_id` là scalar ID của User dùng chung trong `mysql_system`; validation tạo/cập nhật Company kiểm tra đúng connection này.
- Import vận hành từ màn hình Công Ty nhận XLSX/XLS/CSV, vẫn nhận CSV legacy theo Mã/Tên; đây không phải importer production `SP1302` và không dùng thêm khóa legacy.

## Mapping cột

- Mapping cấp bảng đã được ghi tại `../TABLE_MAP.md`.
- Chưa được dùng file này để import production cho đến khi các cột, khóa, nullable/default và quy tắc chuyển đổi được đối chiếu đầy đủ.
- Khi thay đổi schema hoặc viết import cho bảng này, phải bổ sung mapping từng cột và tác động khóa ngoại tại đây.

## Mapping khóa/báo cáo

| Legacy | Mới | Chuyển đổi |
|---|---|---|
| `Ma` | `legacy_id` | Giữ khóa công ty legacy; nullable, unique, chưa backfill. |
| `ReCreditLimit` | `legacy_re_credit_limit` | Giữ snapshot hạn mức legacy; quan hệ tương đương với `max_debt` chưa xác minh. |
| `Company` | `name` | Mapping tên đã có ở cấp bảng; `sp_212` dùng tên này qua join `ct.Ma=tam.Company`. |

`max_debt` không được tự ghi từ `ReCreditLimit` trong migration này; cần xác nhận nghiệp vụ và dữ liệu đối chiếu trước khi dùng thay thế.
