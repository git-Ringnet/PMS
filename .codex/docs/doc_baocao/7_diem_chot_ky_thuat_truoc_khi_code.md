# Tổng Hợp Các Điểm Kỹ Thuật Đã Chốt Trước Khi Triển Khai (Dòng 155 & 156)

> Tài liệu này ghi nhận 7 điểm cốt lõi đã được kiểm chứng trực tiếp trên database legacy MS SQL Server (`ProVistaDTXHotel`) và runtime MySQL 5 chi nhánh.

---

### 1. Tham Số Lọc Ngày Dòng 155 (`p_date_filter_type`)
* **Chốt**: **KHÔNG DÙNG** tham số `p_date_filter_type`.
* **Căn cứ**:
  * Trên giao diện web legacy (`dong_155_ui_mau.png`), hoàn toàn không có lựa chọn loại ngày.
  * Trong SQL Server, `sp_070` không có tham số này; `sp_068` có `@ShowDepDate` nhưng mặc định luôn là `1`.
* **Quy chuẩn Procedure `rpt_cancelled_invoices_payments`**: Nhận đúng **9 tham số**:
  `p_mode`, `p_from_date`, `p_to_date`, `p_department`, `p_outlet`, `p_service`, `p_user`, `p_sort_by`, `p_sort_type`.

---

### 2. Tiêu Chí Lọc Dòng 155 (Bản Ghi Gốc hay Bản Ghi Hủy)
* **`Date`**: Lọc theo **bản ghi GỐC** (`am.Date` / `am.CreatedDate` BETWEEN `v_from` AND `v_to`). Nghiệp vụ: Xem các hóa đơn/thanh toán phát sinh trong kỳ bị hủy.
* **`User`**: Lọc theo **người thực hiện HỦY** (`duong.Username` / `duong.created_by`).
* **`Department`**: Lọc theo **bộ phận của bản ghi GỐC** (`am.DepartmentId` / `am.department_id`).
* **`Outlet`**: Lọc theo **outlet phát sinh thao tác hủy** (`duong.Outlet`).

---

### 3. Quan Hệ Giữa `payments.reversal_ref` và `payments.legacy_id`
* **Dữ liệu mới (thao tác trên web)**: Lưu mã thanh toán gốc vào `payments.reversal_ref = am.id`.
* **Dữ liệu legacy import (SP3002)**: Bản ghi hủy lưu mã gốc vào cột `pack1`, mã gốc lưu ở `legacy_id`.
* **Điều kiện JOIN chuẩn xác**:
  ```sql
  ON duong.reversal_ref = am.id 
  OR duong.pack1 = CAST(am.id AS CHAR) 
  OR duong.pack1 = CAST(am.legacy_id AS CHAR)
  ```

---

### 4. Trường Số Tiền Hiển Thị (Amount / TotalAmount0 / Giá Trị Tuyệt Đối)
* Hệ thống lưu đối trừ theo cặp: 1 dòng dương và 1 dòng âm.
* Báo cáo chia làm 2 cột: `Tổng` (Tạo) và `Tổng` (Hủy).
* **Bắt buộc dùng `ABS(...)`**:
  * Cột Tạo: `ABS(COALESCE(am.amount, am.total_amount0, 0))`
  * Cột Hủy: `ABS(COALESCE(duong.amount, duong.total_amount0, 0))`
  * Đảm bảo các hàm tính tổng (`aggregate.sum`) cộng dồn chính xác, không bị triệt tiêu về 0.
* Trên MySQL mới: Sử dụng trường **`amount`** làm chuẩn dữ liệu tiền tệ.

---

### 5. Mapping Phân Khúc Dòng 156 và Công Thức Day-Use
* **Quy tắc mapping `market_segments`**:
  * `Khách TA`: `Travel Agent`, `FOC`, `Voucher`.
  * `Khách OTA`: `Online Travel Agent`.
  * `Khách Corp`: `Corporate`.
  * `Khách Walk-in, FIT, Fanpage`: `Walk-In`, `Free Individual Traveler`, `Fanpage` (hoặc booking lẻ không có công ty).
* **Công thức Day-use (`Tổng số phòng ở trong ngày`)**:
  * `DayUseRoom = InhouseRoom + CheckinRoom`
  * Là tổng số lượt phòng có khách lưu trú thực tế trong ngày (gồm phòng lưu trú qua đêm chuyển sang + phòng nhận mới trong ngày).

---

### 6. Bộ Dữ Liệu Đối Chiếu Kiểm Thử Chuẩn (Test Bench)
* **Dòng 155 - Huỷ hoá đơn (`sp_068`)**:
  * `2026-01-03`: 60 hoá đơn hủy.
  * `2025-09-01`: 26 hoá đơn hủy.
* **Dòng 155 - Huỷ thanh toán (`sp_070`)**:
  * `2025-08-29`: 22 giao dịch hủy thanh toán.
  * `2025-08-19`: 21 giao dịch hủy thanh toán.
  * `2025-08-18`: 20 giao dịch hủy thanh toán.
* **Dòng 156 - Lễ tân hằng ngày (`sp_275`)**:
  * Ngày `2025-08-01`: Phân khúc `Khách OTA` có số liệu thực tế đã kiểm chứng:
    * `CheckinRoom` = 19
    * `CheckoutRoom` = 20
    * `InhouseRoom` = 15
    * `DayUseRoom` = 34 (19 + 15)
    * `BreakfastGuestNum` = 27 (Suất ăn sáng ngày tiếp theo 02/08)
    * `NoBreakfastGuestNum` = 41 (Khách không ăn sáng ngày 02/08)
    * Các phân khúc TA, Corp, Walk-in: 0.

---

### 7. Trạng Thái Migration Thực Tế Trên 5 Database Branch
* Đã kiểm tra trực tiếp qua lệnh artisan DB:
  * `mysql`: 141 migrations, mới nhất: `2026_09_18_100000_create_sales_invoices_report` (batch 1).
  * `mysql_hkt1`: 141 migrations, mới nhất: `2026_09_18_100000_create_sales_invoices_report` (batch 1).
  * `mysql_hkt2`: 141 migrations, mới nhất: `2026_09_18_100000_create_sales_invoices_report` (batch 1).
  * `mysql_hkt3`: 141 migrations, mới nhất: `2026_09_18_100000_create_sales_invoices_report` (batch 1).
  * `mysql_hkt4`: 141 migrations, mới nhất: `2026_09_18_100000_create_sales_invoices_report` (batch 1).
* **Kết luận**: Cả 5 database chi nhánh đang ở **trạng thái đồng bộ 100%**.
