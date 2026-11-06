---
name: excel-row-fix
description: Xử lý dòng lỗi từ Excel "THEO DÕI VẤN ĐỀ CẦN ĐIỀU CHỈNH" của khách (vd "làm dòng 482", "tới 491 492 493"). Đọc chữ + ẢNH của dòng, phân tích nguyên nhân, sửa, test, báo cáo kèm hướng dẫn nghiệm thu tay, commit sau khi người dùng đồng ý.
---

Đọc và làm theo `docs/QUY_TRINH_XU_LY_DONG_EXCEL.md` (quy trình đầy đủ, mẫu báo cáo, bảng nghiệm thu).

Tóm tắt bắt buộc:
1. Đọc dòng + trích ảnh: `python docs/tools/excel_row_reader.py <dòng...>` rồi **mở từng ảnh**. Dùng file Excel (N) mới nhất, sheet đúng tên. Note đỏ ngày mới nhất thắng; "check task N" → đọc dòng N; "check store sp_X" → cần file trong `store PMS/`, thiếu thì hỏi người dùng.
2. Phân tích trước khi sửa: Ý khách · Hiện tượng · Nguyên nhân (file:dòng, nói rõ nếu chưa chứng minh) · Cách khắc phục · Ảnh hưởng · Điểm chưa rõ (hỏi nếu mơ hồ hoặc đổi hành vi cũ).
3. Test tái hiện lỗi trước (đỏ) → sửa → xanh. Phân biệt lỗi cũ với lỗi mới.
4. Báo cáo: nguyên nhân, đã sửa/không đổi, test, **bảng nghiệm thu tay** (mã, chuẩn bị dữ liệu, thao tác UI, kết quả mong đợi/sai), phần chưa kiểm chứng. Có trình duyệt điều khiển được thì chạy các ca đó trên UI thật; không có thì nói rõ chưa chạy UI.
5. Chỉ commit khi người dùng bảo, trên nhánh đang đứng, không kèm `.agents/DAILY_LOG.md`, `.xlsx`, `store PMS/*.sql`.
