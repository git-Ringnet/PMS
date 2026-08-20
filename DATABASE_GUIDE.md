# Hướng Dẫn Quản Trị Hệ Thống Multi-Database (Provista PMS)

Hệ thống được thiết kế với kiến trúc **1 Database Quản trị Hệ thống (System)** và **4 Database Chi nhánh (Tenant Databases)** vận hành độc lập.

---

## 1. Danh Sách 5 Database

| Tên Database | Kết nối trong Laravel | Mục đích |
| :--- | :--- | :--- |
| `pms_system` | `mysql_system` | Quản trị tập trung: Tài khoản người dùng, Token Sanctum, Roles, Permissions, Quản trị chi nhánh, Thông tin doanh nghiệp. |
| `pms_hkt1` | `mysql_hkt1` / `mysql` | Nghiệp vụ Chi nhánh 1 - **HKT Nha Trang** (Đặt phòng, Phòng, Hóa đơn, Buồng phòng, F&B,...). |
| `pms_hkt2` | `mysql_hkt2` | Nghiệp vụ Chi nhánh 2 - **HKT TP.HCM**. |
| `pms_hkt3` | `mysql_hkt3` | Nghiệp vụ Chi nhánh 3 - **HKT Đà Nẵng**. |
| `pms_hkt4` | `mysql_hkt4` | Nghiệp vụ Chi nhánh 4 - **HKT Hà Nội**. |

---

## 2. Cấu hình file `.env` trên Server / Local

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_USERNAME=root
DB_PASSWORD=

# Tên 5 database (Nếu không khai báo sẽ lấy tên mặc định bên dưới)
DB_DATABASE=pms_hkt1
DB_DATABASE_SYSTEM=pms_system
DB_DATABASE_HKT1=pms_hkt1
DB_DATABASE_HKT2=pms_hkt2
DB_DATABASE_HKT3=pms_hkt3
DB_DATABASE_HKT4=pms_hkt4
```

---

## 3. Lệnh Tự Động Khởi Tạo & Reset (1 Click Command)

Khi triển khai trên Server hoặc Local, **không cần tạo thủ công**, chỉ cần chạy lệnh sau:

### 👉 Reset và Seed toàn bộ 5 database (Khuyên dùng khi cài mới):
```bash
php artisan db:reset-all --seed-all
```
> Lệnh này sẽ:
> 1. **Tự động tạo mới 5 database trên MySQL nếu chưa có** (`pms_system`, `pms_hkt1`, `pms_hkt2`, `pms_hkt3`, `pms_hkt4`).
> 2. Chạy `migrate:fresh` và seed đầy đủ quyền, vai trò, ứng dụng cho `pms_system`.
> 3. Chạy `migrate:fresh` và seed dữ liệu vận hành phòng, buồng phòng, F&B cho cả 4 chi nhánh.

---

### 👉 Chỉ reset riêng 1 chi nhánh:
```bash
# Chỉ reset chi nhánh Nha Trang (HKT 1)
php artisan db:reset-all hkt1 --seed

# Chỉ reset chi nhánh TP.HCM (HKT 2)
php artisan db:reset-all hkt2 --seed

# Chỉ reset database Quản trị hệ thống (System)
php artisan db:reset-all system
```

---

## 4. Cách Kiểm Tra Hoạt Động Của Hệ Thống

1. **Kiểm tra kết nối 5 Database:**
   ```bash
   php artisan tinker --execute="foreach (['mysql_system', 'mysql_hkt1', 'mysql_hkt2', 'mysql_hkt3', 'mysql_hkt4'] as \$c) { echo \$c . ' -> ' . DB::connection(\$c)->getDatabaseName() . ' (OK)' . PHP_EOL; }"
   ```

2. **Kiểm tra chuyển đổi trên giao diện:**
   - Đăng nhập hệ thống.
   - Chọn chi nhánh **HKT 2 (TP.HCM)** trên Header ➔ Tạo 1 phòng hoặc đặt phòng mới.
   - Chuyển sang chi nhánh **HKT 1 (Nha Trang)** ➔ Kiểm tra phòng vừa tạo **không** xuất hiện bên HKT 1 (Dữ liệu hoàn toàn độc lập giữa các database chi nhánh).
