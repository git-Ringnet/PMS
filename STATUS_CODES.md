# HƯỚNG DẪN MÃ TRẠNG THÁI HỆ THỐNG (PMS STATUS CODES)

Tài liệu này tổng hợp toàn bộ các mã trạng thái (`status` & `room_status_code`) được lưu trong Database, các hằng số (Constants) trong Backend Laravel và nghĩa hiển thị ở Frontend Vue.js.

---

## 1. Bảng Trạng Thái Phòng Thực Tế (`room_status_code` - Bảng `room_statuses`)

Bảng: `room_statuses` & `rooms.room_status_code` | Model: `App\Models\RoomStatus` & `App\Models\Room`

### 1.1. Ma Trận Test Case & Hiển Thị Icon (Room Status Matrix)

| STT  | Mã Tình Trạng (`room_status_code`) | Tên Tiếng Việt       | Tên Tiếng Anh        | Icon Hiển Thị (`RoomIcon.vue`)   | Nút Menu Trong Context Menu  | Trạng Thái Kiểm Thử |
| :--: | :--------------------------------- | :------------------- | :------------------- | :------------------------------- | :--------------------------- | :-----------------: |
| **1**  | `vacant_ready`                     | Phòng sẵn sàng       | Vacant Ready         | *(Không hiển thị icon/default)*  | Sẵn sàng                     | **PASS**            |
| **2**  | `vacant_dirty`                     | Phòng chưa dọn       | Vacant Dirty         | 🧹 `dirty` (Cây chổi)            | Phòng bẩn                    | **PASS**            |
| **3**  | `vacant_clean`                     | Phòng sạch           | Vacant Clean         | ✨ `clean` (Lau dọn / Sạch)      | Phòng sạch                   | **PASS**            |
| **4**  | `ooo`                              | Phòng sửa chữa (OOO) | Out Of Order         | 🔒 `ooo` (Ổ khóa cam)            | Phòng OOO                    | **PASS**            |
| **5**  | `oos`                              | Phòng dịch vụ (OOS)  | Out Of Service       | 🔐 `oos` (Ổ khóa xanh)           | Phòng OOS                    | **PASS**            |
| **6**  | `turndown`                         | Lau dọn / Turndown   | Turndown             | ✨ `checkout` (Lau dọn)          | Lau dọn                      | **PASS**            |
| **7**  | `housekeeping`                     | Dịch vụ dọn phòng    | Housekeeping Service | 🧰 `housekeeping-service`        | Dịch vụ dọn phòng            | **PASS**            |
| **8**  | `dnd`                              | Không làm phiền      | Do Not Disturb       | 🚫 `dnd` (Vòng tròn gạch đỏ)     | Phòng không làm phiền        | **PASS**            |
| **9**  | `vacant_priority`                  | Phòng ưu tiên dọn    | Vacant Priority      | ✏️ `priority` (Bút chì / Ưu tiên)| Phòng ưu tiên                | **PASS**            |
| **10** | `occupied_ready`                   | Chiếm dụng sẵn sàng  | Occupied Ready       | *(Ẩn icon status góc dưới)*      | Sẵn sàng (Phòng có khách)    | **PASS**            |
| **11** | `occupied_dirty`                   | Chiếm dụng chưa dọn  | Occupied Dirty       | 🧹 `dirty` (Cây chổi)            | Phòng bẩn (Phòng có khách)   | **PASS**            |
| **12** | `occupied_clean`                   | Chiếm dụng sạch      | Occupied Clean       | *(Ẩn icon status góc dưới)*      | Phòng sạch (Phòng có khách)  | **PASS**            |
| **13** | `occupied_ooo`                     | Chiếm dụng OOO       | Occupied OOO         | 🔒 `ooo` (Ổ khóa cam)            | Phòng OOO (Phòng có khách)   | **PASS**            |

---

### 1.2. Quy Định Hiển Thị Context Menu & Submenu Theo 3 Trường Hợp

#### 1️⃣ **Trường hợp 1: Phòng Trống Hoàn Toàn (VD: Phòng 1004 SUPTR)**
- **Điều kiện**: `!contextMenu.room.booking_code`
- **Menu chính**: `Giao phòng nhanh` | `Chuyển tình trạng phòng >`
- **Submenu (Đủ 7 tình trạng)**:
  1. `Sẵn sàng` (`vacant_ready`) → Icon `double-check` (2 dấu check)
  2. `Phòng bẩn` (`vacant_dirty`) → Icon `dirty`
  3. `Lau dọn` (`vacant_clean`) → Icon `clean`
  4. `Phòng OOO` (`ooo`) → Icon `ooo`
  5. `Phòng OOS` (`oos`) → Icon `oos`
  6. `Phòng ưu tiên` (`vacant_priority`) → Icon `priority`
  7. `Phòng không làm phiền` (`dnd`) → Icon `dnd`

#### 2️⃣ **Trường hợp 2: Phòng Đã Đăng Ký Nhưng Chưa Nhận Phòng (VD: Phòng 1205 FAM)**
- **Điều kiện**: `contextMenu.room.booking_code && contextMenu.room.booking_status !== 'occupied'`
- **Menu chính**: `Đăng ký` | `Nhận phòng` | `In phiếu ăn sáng` | `In mẫu đăng ký` | `Chuyển tình trạng phòng >`
- **Submenu (5 tình trạng - Không có OOO/OOS)**:
  1. `Sẵn sàng` (`vacant_ready`) → Icon `double-check` (2 dấu check)
  2. `Phòng bẩn` (`vacant_dirty`) → Icon `dirty`
  3. `Lau dọn` (`vacant_clean`) → Icon `clean`
  4. `Phòng ưu tiên` (`vacant_priority`) → Icon `priority`
  5. `Phòng không làm phiền` (`dnd`) → Icon `dnd`

#### 3️⃣ **Trường hợp 3: Phòng Đã Nhận / In-House (VD: Phòng 708 SUPD)**
- **Điều kiện**: `contextMenu.room.booking_status === 'occupied'`
- **Menu chính**: `Thông tin` | `Đăng ký` | `Hóa đơn` | `Nhóm hóa đơn` | `Chuyển Phòng` | `Thông báo` | `In phiếu ăn sáng` | `In mẫu đăng ký` | `Huỷ nhận phòng` *(nếu vừa check-in)* | `Chuyển tình trạng phòng >`
- **Submenu (6 tình trạng - Có Dịch Vụ Dọn Phòng)**:
  1. `Sẵn sàng` (`vacant_ready` / `occupied_ready`) → Icon `double-check` (2 dấu check)
  2. `Phòng bẩn` (`occupied_dirty`) → Icon `dirty`
  3. `Lau dọn` (`vacant_clean`) → Icon `clean`
  4. `Dịch vụ dọn phòng` (`housekeeping`) → Icon `housekeeping-service`
  5. `Phòng ưu tiên` (`vacant_priority`) → Icon `priority`
  6. `Phòng không làm phiền` (`dnd`) → Icon `dnd`

---

## 2. Trạng Thái Chi Tiết Từng Phòng Trụ Thuộc Booking (`booking_rooms.status`)

Bảng: `booking_rooms` | Model: `App\Models\BookingRoom`

| Mã DB (`status`) | Constant Backend (`BookingRoom::class`) | Trạng Thái Hiển Thị (UI) | Mô Tả / Nghiệp Vụ                                             |
| :--------------: | :------------------------------------- | :----------------------- | :------------------------------------------------------------ |
|     **`0`**      | `STATUS_BOOKED`                        | **TÌNH TRẠNG: ĐĂNG KÝ**  | Phòng đã được gán/đặt nhưng khách **chưa Check-in** (nhận phòng). |
|     **`1`**      | `STATUS_CHECKED_IN`                    | **TÌNH TRẠNG: ĐANG Ở**   | Khách **đã Check-in** và đang lưu trú tại phòng này (Inhouse).   |
|     **`2`**      | `STATUS_CHECKED_OUT`                   | **ĐÃ TRẢ PHÒNG**         | Khách **đã Check-out** (trả phòng) và hoàn tất thanh toán.    |
|     **`3`**      | `STATUS_CANCELLED`                     | **ĐÃ HỦY**               | **Phòng này đã bị hủy bỏ** khỏi đơn Booking.                   |

---

## 3. Trạng Thái Đơn Booking Tổng (`bookings.status`)

Bảng: `bookings` | Model: `App\Models\Booking`

| Mã DB (`status`) | Constant Backend (`Booking::class`) | Tên Trạng Thái                 | Mô Tả / Nghiệp Vụ                                           |
| :--------------: | :---------------------------------- | :----------------------------- | :---------------------------------------------------------- |
|     **`0`**      | `STATUS_RESERVATION`                | **Đăng ký (Reservation)**      | Đơn đặt phòng mới được tạo, chưa có phòng nào Check-in.     |
|     **`1`**      | `STATUS_CHECKIN`                    | **Đang ở (Checked-In)**        | Đơn đã có ít nhất 1 phòng đã Check-in.                      |
|     **`2`**      | `STATUS_CHECKOUT`                   | **Đã trả phòng (Checked-Out)** | Đơn đã hoàn tất Check-out toàn bộ các phòng.                |
|     **`3`**      | `STATUS_DELETED`                    | **Đã xóa / Hủy tổng**          | Toàn bộ đơn Booking đã bị hủy bỏ hoặc xóa khỏi hệ thống.    |
|     **`4`**      | `STATUS_NO_SHOW`                    | **Không đến (No-Show)**        | Khách đặt phòng nhưng không tới nhận phòng trong ngày đến.  |
|    **`100`**     | `STATUS_TRANSFER`                   | **Chuyển phòng (Transfer)**    | Đơn trong trạng thái trung gian khi thực hiện đổi/chuyển.   |

---

## 4. Các Hằng Số Quan Trọng Trong Frontend (`src/services/room-service.js`)

```javascript
// Các mã tình trạng chuẩn (dùng cho context menu và API updateStatus)
export const ROOM_STATUS_CODES = {
  VACANT_READY:    'vacant_ready',
  VACANT_DIRTY:    'vacant_dirty',
  VACANT_CLEAN:    'vacant_clean',
  OOO:             'ooo',
  OOS:             'oos',
  TURNDOWN:        'turndown',
  HOUSEKEEPING:    'housekeeping',
  DND:             'dnd',
  VACANT_PRIORITY: 'vacant_priority',
  OCCUPIED_READY:  'occupied_ready',
  OCCUPIED_DIRTY:  'occupied_dirty',
  OCCUPIED_CLEAN:  'occupied_clean',
  OCCUPIED_OOO:    'occupied_ooo',
}

// Mapping room_status_code -> icon name cho frontend
export const ROOM_STATUS_ICON_MAP = {
  'vacant_ready':    'available',
  'vacant_dirty':    'dirty',
  'vacant_clean':    'clean',
  'ooo':             'ooo',
  'oos':             'oos',
  'turndown':        'checkout',
  'housekeeping':    'housekeeping-service',
  'dnd':             'dnd',
  'vacant_priority': 'priority',
  'occupied_ready':  null,
  'occupied_dirty':  'dirty',
  'occupied_clean':  null,
  'occupied_ooo':    'ooo',
}
```
