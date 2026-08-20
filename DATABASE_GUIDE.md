# Hướng Dẫn Quản Trị Multi-Database & Reset Dữ Liệu PMS

> Hệ thống PMS sử dụng kiến trúc **Multi-Database** (mỗi chi nhánh 1 database nghiệp vụ riêng biệt và 1 database quản trị trung tâm).

---

## 1. Danh Sách Các Database Trong Hệ Thống

| Database | Connection | Vai trò / Chi nhánh | Ghi chú |
| :--- | :--- | :--- | :--- |
| **`pms_system`** | `mysql_system` | Quản trị trung tâm toàn hệ thống | Quản lý Users, Roles, Permissions, Danh mục chi nhánh |
| **`pms_hkt1`** | `mysql_hkt1` | Chi nhánh **HKT 1 - Nha Trang** | Database chi nhánh chính (mặc định) |
| **`pms_hkt2`** | `mysql_hkt2` | Chi nhánh **HKT 2 - TP.HCM** | Dữ liệu nghiệp vụ chi nhánh 2 |
| **`pms_hkt3`** | `mysql_hkt3` | Chi nhánh **HKT 3 - Đà Nẵng** | Dữ liệu nghiệp vụ chi nhánh 3 |
| **`pms_hkt4`** | `mysql_hkt4` | Chi nhánh **HKT 4 - Hà Nội** | Dữ liệu nghiệp vụ chi nhánh 4 |

---

## 2. Lệnh Reset Toàn Bộ (All Databases)

Để reset sạch toàn bộ 5 databases chỉ bằng 1 lệnh duy nhất:

### Reset toàn bộ và Seed dữ liệu cho chi nhánh chính (`pms_hkt1`):
```bash
php artisan db:reset-all --seed
```

### Reset toàn bộ sạch sẽ (chỉ tạo bảng, không seed):
```bash
php artisan db:reset-all
```

### Reset và Seed dữ liệu cho **tất cả** các chi nhánh:
```bash
php artisan db:reset-all --seed-all
```

---

## 3. Lệnh Reset Riêng Từng Database Cụ Thể

Bạn có thể truyền tên mục tiêu vào lệnh: `system`, `hkt1`, `hkt2`, `hkt3`, `hkt4`.

| Mục đích | Lệnh thực hiện |
| :--- | :--- |
| **Reset riêng System DB** | `php artisan db:reset-all system` |
| **Reset + Seed riêng HKT1** | `php artisan db:reset-all hkt1 --seed` |
| **Reset riêng HKT2** | `php artisan db:reset-all hkt2` |
| **Reset riêng HKT3** | `php artisan db:reset-all hkt3` |
| **Reset riêng HKT4** | `php artisan db:reset-all hkt4` |

---

## 4. Các Lệnh Chuẩn Của Laravel (Truyền `--database`)

Nếu muốn sử dụng lệnh chuẩn của Laravel `migrate:fresh`:

- **System**: `php artisan migrate:fresh --database=mysql_system --force`
- **HKT 1**: `php artisan migrate:fresh --seed --database=mysql_hkt1 --force`
- **HKT 2**: `php artisan migrate:fresh --database=mysql_hkt2 --force`
- **HKT 3**: `php artisan migrate:fresh --database=mysql_hkt3 --force`
- **HKT 4**: `php artisan migrate:fresh --database=mysql_hkt4 --force`

---

## 5. Lưu Ý Quan Trọng
- Lệnh mặc định `php artisan migrate:fresh --seed` (không chỉ định connection) sẽ chạy trên database mặc định trong `.env` (`DB_DATABASE=pms_hkt1`).
- Database `pms` cũ (nếu còn) có thể DROP vì hệ thống đã chuyển sang dùng `pms_system` và `pms_hkt1..4`.
