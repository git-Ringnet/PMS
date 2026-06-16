# Hướng Dẫn Chuyển Đổi và Import Dữ Liệu Khách Hàng (.bak -> MySQL)

Tài liệu này hướng dẫn chi tiết quy trình chuyển đổi (migrate) dữ liệu từ database cũ dạng SQL Server (file backup `.bak`) của khách hàng sang hệ thống mới Laravel + MySQL mà không làm ảnh hưởng đến cấu trúc chuẩn của dự án mới.

---

## Quy Trình Tổng Quan
Quy trình chuyển đổi áp dụng phương pháp **ETL (Extract - Transform - Load)** qua file trung gian CSV:
```
[SQL Server (.bak)] ──(Export Query)──> [Files CSV] ──(Laravel Command)──> [MySQL Project mới]
```

---

## Bước 1: Khôi phục (Restore) Database cũ
Do file `.bak` là định dạng đóng gói của Microsoft SQL Server, trước hết bạn cần dựng nó thành một database để có thể chạy truy vấn xem dữ liệu:
1. Cài đặt **SQL Server Express** và công cụ quản trị **SSMS (SQL Server Management Studio)** cục bộ.
2. Tại SSMS, click chuột phải vào thư mục **Databases** chọn **Restore Database...**
3. Chọn nguồn phục hồi từ **Device**, bấm `...` để tìm và add file `.bak` của khách hàng.
4. Bấm **OK** để khôi phục cơ sở dữ liệu.

---

## Bước 2: Xuất (Export) dữ liệu ra file CSV
Với các bản SSMS thông thường, bạn có thể xuất dữ liệu của từng bảng ra CSV trực tiếp từ bảng kết quả truy vấn (Results Grid) cực kỳ nhanh:

1. Click chuột phải vào Database cũ vừa restore, chọn **New Query**.
2. Viết câu lệnh truy vấn để lấy dữ liệu (ví dụ bảng phòng khóa hỏng `SP4001`):
   ```sql
   SELECT * FROM SP4001
   ```
3. Bấm **Execute** (hoặc nhấn phím `F5`) để chạy truy vấn.
4. Tại bảng kết quả ở bên dưới (Grid Results):
   * Click chuột phải vào **ô vuông trống ở góc trên cùng bên trái** của bảng (ô dùng để chọn/bôi đen toàn bộ dữ liệu).
   * Chọn **Save Results As...**
   * Đặt tên file là `SP4001.csv` và chọn định dạng lưu là `*.csv` (Comma Delimited).
5. Làm tương tự cho các bảng khác cần import.

---

## Bước 3: Đưa các file CSV vào Project mới
1. Đi vào thư mục của project Laravel: `backend/storage/app/`
2. Tạo một thư mục con tên là `imports` (Đường dẫn đầy đủ: `backend/storage/app/imports`).
3. Di chuyển toàn bộ các file CSV đã xuất từ Bước 2 (`SP4001.csv`, `SP4002.csv`...) vào thư mục `imports` này.

---

## Bước 4: Viết Artisan Command thực hiện Mapping (Ánh xạ)
Tạo Artisan Command mới bằng lệnh:
```bash
php artisan make:command TênCommandCủaBạn
```
Viết logic đọc file CSV và ánh xạ dữ liệu theo các nguyên tắc dưới đây:

### 1. Nguyên tắc xử lý lệch cột và đổi tên cột
Bảng cũ và bảng mới thường có tên cột khác nhau và số lượng cột khác nhau. Trong code PHP, bạn chỉ cần gán đúng index (chỉ số) của cột cũ vào tên thuộc tính mới:
```php
// Duyệt từng dòng của file CSV
while (($row = fgetcsv($file)) !== FALSE) {
    // Ví dụ cấu trúc dòng CSV cũ:
    // Cột 0: ID (Bỏ qua)
    // Cột 1: Mã phòng cũ
    // Cột 2: Ngày bắt đầu khóa
    
    // Ta map sang Model mới:
    RoomLock::create([
        'room_id'    => $this->mapRoomId($row[1]), // Tìm ID phòng mới từ mã cũ
        'start_date' => substr(trim($row[2]), 0, 10), // Cắt lấy YYYY-MM-DD
    ]);
}
```

### 2. Nguyên tắc xử lý Khóa ngoại (Foreign Key)
Bảng đích mới của bạn có các trường liên kết khóa ngoại (như `room_id`, `branch_id`, `company_id`). Bạn cần dùng các cột đặc trưng cũ (như mã phòng `701`, mã chi nhánh `CN01`) để truy vấn ra ID tự tăng tương ứng trong database mới trước khi ghi đè:
```php
// Tìm phòng mới dựa theo số phòng cũ
$room = Room::where('room_number', $oldRoomNumber)->first();
if ($room) {
    $newRoomId = $room->id;
}
```

### 3. Nguyên tắc xử lý định dạng ngày tháng (Date/Datetime)
Cơ sở dữ liệu SQL Server cũ thường xuất ngày tháng dạng `YYYY-MM-DD HH:MM:SS.000`. Để tránh lỗi định dạng của MySQL (`Incorrect date value`), hãy làm sạch chuỗi ngày tháng trước khi lưu:
* Cách nhanh: Cắt lấy 10 ký tự đầu: `substr(trim($row[2]), 0, 10)` -> kết quả: `YYYY-MM-DD`.
* Cách chuẩn: Chuyển đổi qua hàm: `date('Y-m-d', strtotime($row[2]))`.

---

## Hướng dẫn Mapping cho các Bảng Tiếp Theo

### 1. Bảng Công Ty (Companies)
* **Bảng cũ ở SQL Server**: `SP1302` (CongTy).
* **Bảng mới ở MySQL**: `companies`.
* **Luồng xử lý lý tưởng**:
  1. Bạn cần đảm bảo đã chạy seeder của các bảng danh mục liên quan trước: Chi nhánh (`branches`), Thị trường (`markets`), Nguồn khách (`customer_sources`), Người đặt phòng (`bookers`).
  2. Khi import bảng `SP1302` (Công ty), bạn đọc các cột chứa mã chi nhánh cũ, mã thị trường cũ,... và truy vấn sang các bảng mới tương ứng để lấy được các ID (`branch_id`, `market_id`, `customer_source_id`, `booker_id`).
  3. Sử dụng cơ chế tự động sinh mã hoặc map cột cũ để ghi đè vào cột `code` (ví dụ: `CTY0001`).

### 2. Bảng Mã Giá Phòng (Rate Codes)
* **Bảng cũ ở SQL Server**: `SP1340` (MaGiaPhong).
* **Bảng mới ở MySQL**: `room_rate_codes`.
* **Luồng xử lý lý tưởng**:
  1. Đọc dữ liệu từ file CSV chứa bảng `SP1340`.
  2. Gán các thuộc tính giá cũ, giá ăn sáng, giá extra bed tương ứng vào các cột `price`, `breakfast_price`, `extra_bed_price`.
  3. Map cột `RateType` cũ (nếu có) sang cột `rate_type` mới (mặc định `'FIT'` hoặc `'GIT'`).
