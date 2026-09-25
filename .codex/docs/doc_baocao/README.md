# Tài Liệu Đặc Tả Báo Cáo (Thư Mục `doc_baocao`)

Thư mục này chứa toàn bộ tài liệu phân tích nghiệp vụ, mô hình dữ liệu, đặc tả giao diện và mã nguồn SQL legacy cho các báo cáo trong file `DANH MỤC BÁO CÁO.xlsx`, phục vụ cho Agent đọc và triển khai độc lập.

---

## 📂 Cấu Trúc Thư Mục

```text
.codex/docs/doc_baocao/
├── README.md                                               # Hướng dẫn tổng quan & phân loại
├── dong_150_bao_cao_doanh_thu_army.md                      # Đặc tả chi tiết Dòng 150 (sp_292)
├── dong_151_bao_cao_doanh_thu_dang_ky_theo_ngay_di.md       # Đặc tả chi tiết Dòng 151 (sp_238, sp_240)
├── dong_152_bao_cao_doanh_thu_le_tan_army.md               # Đặc tả chi tiết Dòng 152 (sp_293)
├── dong_155_bao_cao_huy_hoa_don_thanh_toan.md              # Đặc tả chi tiết Dòng 155 (sp_068, sp_070)
├── dong_156_bao_cao_le_tan_hang_ngay.md                    # Đặc tả chi tiết Dòng 156 (sp_275)
├── images/                                                 # Ảnh chụp màn hình UI thực tế từ hệ thống legacy
│   ├── dong_150_ui_mau.png                                 # UI thực tế Dòng 150 (Sheet 73)
│   ├── dong_151_ui_mau.png                                 # UI thực tế Dòng 151 (Sheet 67)
│   ├── dong_152_ui_mau.png                                 # UI thực tế Dòng 152 (Sheet 74)
│   ├── dong_155_ui_mau.png                                 # UI thực tế Dòng 155 (Sheet 51)
│   └── dong_156_ui_mau.png                                 # UI thực tế Dòng 156 (Sheet 71)
└── sql/                                                    # Mã nguồn Stored Procedure gốc trích xuất từ MS SQL Server
    ├── sp_292_full.sql                                     # SQL gốc sp_292 (Doanh thu Army Quy Nhơn)
    ├── sp_238_full.sql                                     # SQL gốc sp_238 (Doanh thu theo ngày đi - chi tiết phòng)
    ├── sp_240_full.sql                                     # SQL gốc sp_240 (Doanh thu theo ngày đi - nhóm đăng ký)
    ├── sp_293_full.sql                                     # SQL gốc sp_293 (Doanh thu lễ tân Army)
    ├── sp_068_full.sql                                     # SQL gốc sp_068 (Huỷ hoá đơn)
    ├── sp_070_full.sql                                     # SQL gốc sp_070 (Huỷ thanh toán)
    └── sp_275_full.sql                                     # SQL gốc sp_275 (Lễ tân hằng ngày)
```

---

## 📊 Bảng Đối Chiếu Các Báo Cáo

| Thuộc tính | Dòng 150 | Dòng 151 | Dòng 152 | Dòng 155 | Dòng 156 | Dòng 169 | Dòng 170 | Dòng 171 |
|---|---|---|---|---|---|---|---|---|
| **Tên báo cáo** | **Báo cáo doanh thu** | **Báo cáo doanh thu đăng ký theo ngày đi** | **Báo cáo doanh thu lễ tân_army** | **Báo cáo hủy hóa đơn/thanh toán** | **Báo cáo lễ tân hằng ngày** | **Báo cáo dự đoán bán phòng** | **Báo cáo phòng hàng tuần** | **Báo cáo tổng doanh thu** |
| **STT trong Excel** | 4.0 | 5.0 | 6.0 | 9.0 | 10.0 | 4.0 | 5.0 | None |
| **Mã đề xuất (`code`)** | `REVENUE_ARMY` | `REVENUE_BY_DEPARTURE_DATE` | `RECEPTION_REVENUE_ARMY` | `CANCELLED_INVOICES_PAYMENTS` | `DAILY_FRONTDESK` | `ROOM_FORECAST` | `WEEKLY_ROOM_REPORT` | `TOTAL_REVENUE` |
| **Nhóm báo cáo** | `revenue` | `revenue` | `revenue` | `cancellation` | `frontdesk` | `room` | `room` | `revenue` / `frontdesk` |
| **Sheet Excel legacy** | **Sheet 73** (`Báo cáo Dthu`) | **Sheet 67** (`BC DT theo ngày đi`) | **Sheet 74** (`BC doanh thu lễ tân`) | **Sheet 51** (`BC hủy hđ-thanh toán`) | **Sheet 71** (`BC lễ tân hằng ngày`) | **Sheet 22, 39, 80** | **Sheet 45** (`BC phòng hàng tuần`) | **Sheet 72** (`Báo cáo Dthu`) |
| **Store legacy gốc** | `sp_292` | `sp_238`, `sp_240` | `sp_293` | `sp_068`, `sp_070` | `sp_275` | `sp_023` | `sp_023_Division` | `sp_TotalRevenueFromReportSetup` / `sp_292` |
| **Tài liệu chi tiết** | [dong_150...](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/dong_150_bao_cao_doanh_thu_army.md) | [dong_151...](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/dong_151_bao_cao_doanh_thu_dang_ky_theo_ngay_di.md) | [dong_152...](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/dong_152_bao_cao_doanh_thu_le_tan_army.md) | [dong_155...](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/dong_155_bao_cao_huy_hoa_don_thanh_toan.md) | [dong_156...](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/dong_156_bao_cao_le_tan_hang_ngay.md) | [dong_169...](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/dong_169_bao_cao_du_doan_ban_phong.md) | [dong_170...](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/dong_170_bao_cao_phong_hang_tuan.md) | [dong_171...](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/dong_171_bao_cao_tong_doanh_thu.md) |
| **Ảnh UI mẫu** | [dong_150_ui_mau.png](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/images/dong_150_ui_mau.png) | [dong_151_ui_mau.png](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/images/dong_151_ui_mau.png) | [dong_152_ui_mau.png](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/images/dong_152_ui_mau.png) | [dong_155_ui_mau.png](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/images/dong_155_ui_mau.png) | [dong_156_ui_mau.png](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/images/dong_156_ui_mau.png) | [dong_169_ui_mau_1.png](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/images/dong_169_ui_mau_1.png) | [dong_170_ui_mau.png](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/images/dong_170_ui_mau.png) | [dong_171_ui_mau.png](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/images/dong_171_ui_mau.png) |
| **File SQL gốc** | [sp_292_full.sql](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/sql/sp_292_full.sql) | [sp_238_full.sql](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/sql/sp_238_full.sql), [sp_240_full.sql](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/sql/sp_240_full.sql) | [sp_293_full.sql](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/sql/sp_293_full.sql) | [sp_068_full.sql](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/sql/sp_068_full.sql), [sp_070_full.sql](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/sql/sp_070_full.sql) | [sp_275_full.sql](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/sql/sp_275_full.sql) | [ProVistaNavyHotel_sp_023.sql](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/sql/ProVistaNavyHotel_sp_023.sql) | [ProVistaNavyHotel_sp_023_Division.sql](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/sql/ProVistaNavyHotel_sp_023_Division.sql) | [ProVistaNavyHotel_sp_TotalRevenueFromReportSetup.sql](file:///c:/Users/Nguyen%20Tho%20Thang/OneDrive/Desktop/PMS/PMS/.codex/docs/doc_baocao/sql/ProVistaNavyHotel_sp_TotalRevenueFromReportSetup.sql) |

---

## 🛠️ Quy Trình Triển Khai Chuẩn Cho Agent Kế Tiếp

Khi một Agent nhận nhiệm vụ triển khai các báo cáo này, Agent thực hiện theo các bước chuẩn mực sau:

1. **Đọc tài liệu đặc tả**:
   - Mở file tài liệu đặc tả chi tiết trong thư mục `doc_baocao/`.
   - Xem ảnh UI mẫu tương ứng trong thư mục `images/`.
   - Đọc file SQL tương ứng trong thư mục `sql/` để nắm trọn vẹn logic join và tính toán.
2. **Tạo Migration (`backend/database/migrations/`)**:
   - Viết migration tạo Stored Procedure MySQL chuyển ngữ từ SQL Server legacy.
   - Luôn loop qua cả 5 kết nối database chi nhánh: `['mysql', 'mysql_hkt1', 'mysql_hkt2', 'mysql_hkt3', 'mysql_hkt4']`.
   - Đăng ký `ReportDataSource` và `ReportDefinition`.
3. **Tạo Template Reference (`backend/database/report_templates/`)**:
   - Viết file template `.php` trả về `html` và `content_json` hỗ trợ Form Designer WYSIWYG.
   - Khai báo đầy đủ `customRows` để hiển thị hàng tổng phụ (Subtotal) và hàng tổng cộng (Grand Total) trên Canvas Form Designer.
4. **Kiểm Thử (Verification)**:
   - Chạy `php artisan migrate:all --force`.
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
