# Tài Liệu Đặc Tả Báo Cáo Dòng 155 & 156 (Thư Mục `doc_baocao`)

Thư mục này chứa toàn bộ tài liệu phân tích nghiệp vụ, mô hình dữ liệu, đặc tả giao diện và mã nguồn SQL legacy cho **Dòng 155** và **Dòng 156** trong file `DANH MỤC BÁO CÁO.xlsx`, phục vụ cho Agent đọc và triển khai độc lập.

---

## 📂 Cấu Trúc Thư Mục

```text
.codex/docs/doc_baocao/
├── README.md                                  # Hướng dẫn tổng quan & phân loại
├── dong_155_bao_cao_huy_hoa_don_thanh_toan.md # Đặc tả chi tiết Dòng 155 (sp_068, sp_070)
├── dong_156_bao_cao_le_tan_hang_ngay.md       # Đặc tả chi tiết Dòng 156 (sp_275)
├── images/                                    # Ảnh chụp màn hình UI thực tế từ hệ thống legacy
│   ├── dong_155_ui_mau.png                    # UI thực tế Dòng 155 (Sheet 51)
│   └── dong_156_ui_mau.png                    # UI thực tế Dòng 156 (Sheet 71)
└── sql/                                       # Mã nguồn Stored Procedure gốc trích xuất từ MS SQL Server
    ├── sp_068_full.sql                        # SQL gốc sp_068 (Huỷ hoá đơn)
    ├── sp_070_full.sql                        # SQL gốc sp_070 (Huỷ thanh toán)
    └── sp_275_full.sql                        # SQL gốc sp_275 (Lễ tân hằng ngày)
```

---

## 📊 Bảng Đối Chiếu 2 Báo Cáo

| Thuộc tính | Dòng 155 | Dòng 156 |
|---|---|---|
| **Tên báo cáo** | **Báo cáo hủy hóa đơn/thanh toán** | **Báo cáo lễ tân hằng ngày** |
| **STT trong Excel** | 9.0 | 10.0 |
| **Mã đề xuất (`code`)** | `CANCELLED_INVOICES_PAYMENTS` | `DAILY_FRONTDESK` |
| **Nhóm báo cáo (`group`)** | `cancellation` (hoặc `frontdesk`) | `frontdesk` |
| **Sheet Excel legacy** | **Sheet 51** (`BC hủy hđ-thanh toán`) | **Sheet 71** (`BC lễ tân hằng ngày`) |
| **Store legacy gốc** | `sp_068` (Huỷ hoá đơn), `sp_070` (Huỷ thanh toán) | `sp_275` |
| **Tài liệu chi tiết** | [dong_155_bao_cao_huy_hoa_don_thanh_toan.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/dong_155_bao_cao_huy_hoa_don_thanh_toan.md) | [dong_156_bao_cao_le_tan_hang_ngay.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/dong_156_bao_cao_le_tan_hang_ngay.md) |
| **Ảnh UI mẫu** | [dong_155_ui_mau.png](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/images/dong_155_ui_mau.png) | [dong_156_ui_mau.png](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/images/dong_156_ui_mau.png) |
| **File SQL gốc** | [sp_068_full.sql](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/sql/sp_068_full.sql), [sp_070_full.sql](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/sql/sp_070_full.sql) | [sp_275_full.sql](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/sql/sp_275_full.sql) |

---

## 🛠️ Quy Trình Triển Khai Chuẩn Cho Agent Kế Tiếp

Khi một Agent nhận nhiệm vụ triển khai 2 báo cáo này, Agent chỉ cần thực hiện theo các bước chuẩn mực sau:

1. **Đọc tài liệu đặc tả**:
   - Mở file `.codex/docs/doc_baocao/dong_155_bao_cao_huy_hoa_don_thanh_toan.md` hoặc `.codex/docs/doc_baocao/dong_156_bao_cao_le_tan_hang_ngay.md`.
   - Xem ảnh UI mẫu tương ứng trong thư mục `images/`.
   - Đọc file SQL tương ứng trong thư mục `sql/` để nắm trọn vẹn logic join và tính toán.
2. **Tạo Migration (`backend/database/migrations/`)**:
   - Viết migration tạo Stored Procedure MySQL chuyển ngữ từ SQL Server legacy.
   - Luôn loop qua cả 5 kết nối database chi nhánh: `['mysql', 'mysql_hkt1', 'mysql_hkt2', 'mysql_hkt3', 'mysql_hkt4']`.
   - Đăng ký `ReportDataSource` và `ReportDefinition`.
3. **Tạo Template Reference (`backend/database/report_templates/`)**:
   - Viết file template `.php` trả về `html` và `content_json` hỗ trợ Form Designer WYSIWYG.
   - Áp dụng đầy đủ cấu hình margin, padding, viền, font size chuẩn (9.5px - 10px cho bảng, 16px cho tiêu đề).
4. **Kiểm Thử (Verification)**:
   - Chạy `php artisan migrate`.
   - Viết Feature Test trong `backend/tests/Feature/`.
   - Chạy `npm run build` trên `frontend/`.
5. **Ghi Nhật Ký**:
   - Cập nhật [.agents/DAILY_LOG.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.agents/DAILY_LOG.md) và [.codex/PROJECT_MEMORY.md](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/PROJECT_MEMORY.md).

## Trạng thái triển khai hiện tại

- Dòng 155 và 156 đã có procedure, metadata, template Designer và test riêng trong `backend/`.
- Dòng 155 dùng `CANCELLED_INVOICES_PAYMENTS_REFERENCE`, hỗ trợ mode `BILL`/`PAYMENT`, lookup `outlets`, header 2 tầng 12 cột và tổng theo Ngày/Bộ phận.
- Dòng 156 dùng `DAILY_FRONTDESK_REFERENCE`, mặc định ngày `$yesterday` được Report Viewer resolve theo system date; layout đã đồng bộ theo ảnh legacy.
- Migration đồng bộ layout hiện có: `2026_09_18_130000_sync_daily_frontdesk_design.php` và `2026_09_18_140000_sync_cancelled_invoices_payments_design.php`.
- Đã kiểm tra: 7 test báo cáo, 78 assertions; PHP lint; frontend production build. Còn cần nghiệm thu browser/export trên dữ liệu thật.
