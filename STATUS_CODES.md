# HƯỚNG DẪN MÃ TRẠNG THÁI HỆ THỐNG (PMS STATUS CODES)

Tài liệu này tổng hợp toàn bộ các mã trạng thái (`status`) được lưu trong Database, các hằng số (Constants) trong Backend Laravel và nghĩa hiển thị ở Frontend Vue.js.

---

## 1. Trạng Thái Chi Tiết Từng Phòng (`booking_rooms.status`)

Bảng: `booking_rooms` | Model: `App\Models\BookingRoom`

| Mã DB (`status`) | Constant Backend (`BookingRoom::class`) | Trạng Thái Hiển Thị (UI) | Mô Tả / Nghiệp Vụ |
|:---:|---|---|---|
| **`0`** | `STATUS_BOOKED` | **TÌNH TRẠNG: ĐĂNG KÝ** | Phòng đã được gán/đặt nhưng khách **chưa Check-in** (nhận phòng). |
| **`1`** | `STATUS_CHECKED_IN` | **TÌNH TRẠNG: ĐANG Ở** | Khách **đã Check-in** và đang lưu trú tại phòng này (Inhouse). |
| **`2`** | `STATUS_CHECKED_OUT` | **ĐÃ TRẢ PHÒNG** | Khách **đã Check-out** (trả phòng) và hoàn tất thanh toán cho phòng này. |
| **`3`** | `STATUS_CANCELLED` | **ĐÃ HỦY** | **Phòng này đã bị hủy bỏ** khỏi đơn Booking. |

---

## 2. Trạng Thái Đơn Booking Tổng (`bookings.status`)

Bảng: `bookings` | Model: `App\Models\Booking`

| Mã DB (`status`) | Constant Backend (`Booking::class`) | Tên Trạng Thái | Mô Tả / Nghiệp Vụ |
|:---:|---|---|---|
| **`0`** | `STATUS_RESERVATION` | **Đăng ký (Reservation)** | Đơn đặt phòng mới được tạo, chưa có phòng nào Check-in. |
| **`1`** | `STATUS_CHECKIN` | **Đang ở (Checked-In)** | Đơn đã có ít nhất 1 phòng đã Check-in. |
| **`2`** | `STATUS_CHECKOUT` | **Đã trả phòng (Checked-Out)** | Đơn đã hoàn tất Check-out toàn bộ các phòng. |
| **`3`** | `STATUS_DELETED` | **Đã xóa / Hủy tổng** | Toàn bộ đơn Booking đã bị hủy bỏ hoặc xóa khỏi hệ thống. |
| **`4`** | `STATUS_NO_SHOW` | **Không đến (No-Show)** | Khách đặt phòng nhưng không tới nhận phòng trong ngày đến. |
| **`100`** | `STATUS_TRANSFER` | **Chuyển phòng (Transfer)** | Đơn trong trạng thái trung gian khi thực hiện đổi/chuyển phòng. |

---

## 3. Trạng Thái Vệ Sinh & Sơ Đồ Phòng Thực Tế (`rooms.status`)

Bảng: `rooms` | Model: `App\Models\Room`

| Tên Trạng Thái | Màu Sắc / Biểu Tượng | Mô Tả |
|---|---|---|
| **Sạch** | Xanh lá (`#22c55e`) | Phòng sẵn sàng đón khách. |
| **Bẩn** | Đỏ / Cam (`#ef4444`) | Phòng chưa dọn dẹp sau khi khách trả. |
| **Sửa chữa (OOO)** | Xám (`#64748b`) | Phòng Out of Order (đang bảo trì/hỏng hóc). |
| **Đang ở** | Xanh dương (`#0284c7`) | Phòng đang có khách ở. |

---

## 4. Các Hằng Số Quan Trọng Trong Code

### Backend (`app/Models/BookingRoom.php`)
```php
const STATUS_BOOKED      = 0; // Đã đặt, chưa check-in
const STATUS_CHECKED_IN  = 1; // Đang ở (inhouse)
const STATUS_CHECKED_OUT = 2; // Đã trả phòng
const STATUS_CANCELLED   = 3; // Đã hủy
```

### Backend (`app/Models/Booking.php`)
```php
const STATUS_RESERVATION = 0;   // Đăng ký
const STATUS_CHECKIN     = 1;   // Checked In
const STATUS_CHECKOUT    = 2;   // Checked Out
const STATUS_DELETED     = 3;   // Đã xóa
const STATUS_NO_SHOW     = 4;   // No Show
const STATUS_TRANSFER    = 100; // Chuyển phòng
```
