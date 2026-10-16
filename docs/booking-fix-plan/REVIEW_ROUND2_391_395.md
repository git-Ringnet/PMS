# Review đợt 2 — Booking dòng 391, 393, 394, 395

Tài liệu cho reviewer (Gemini) kiểm tra lại các bản sửa. Mỗi mục gồm: yêu cầu nghiệp vụ, nguyên nhân lỗi, cách sửa, file liên quan, test case. Cuối tài liệu có lệnh chạy test và các điểm cần soi kỹ.

Ngày hệ thống trong test backend: **2026-08-07** (`SystemDateRoll` trong `BookingBusinessRulesTest::setUp`).

---

## Dòng 391 — Phòng day use (đã commit `b32cc9be`)

**Yêu cầu**
- Bật "Ở theo giờ": ngày đi = ngày đến, 0 đêm, khóa ô ngày đi/đêm, có **1 dòng tiền phòng vào ngày đến**, trừ 1 AV.
- Tắt "Ở theo giờ": giữ nguyên ngày đi đang chọn, mở ô ngày đi cho sửa, không tự bật lại.
- Phòng ngày đến = ngày đi **không** bật day use: được phép lưu, 0 đêm, không tiền, không trừ AV.
- Post tiền phòng day use: `IsRoomNightRoomDayUse` = 0 → `room_night_bills.is_room_night = 0`; = 1 → `is_room_night = 1`.

**Nguyên nhân và cách sửa**
| Lỗi | Nguyên nhân | Sửa |
|---|---|---|
| Không có tiền khi bật day use | `BookingRoomStayChargeService` chỉ sinh tiền cho [đến, đi) → day use 0 đêm, còn xóa sạch RM chưa post | Day use được tính 1 ngày [đến, đến+1) ở stay-charge, lifecycle, rate code, và bước dọn dịch vụ sau khi lưu |
| Không tạo được phòng cùng ngày không day use | `BookingController::store` có `\|\| true`; `update` ép phòng mới cùng ngày thành day use; cờ day use của booking ghi đè cờ từng phòng | Cờ `is_day_use`/`hourly` của từng phòng là quyết định (`detailDayUseFlag`) |
| Tắt day use thì ngày đi nhảy +1, nhập 0 đêm tự bật day use | `handleHourlyToggle`, `handleRowNightsChangeInline` | Giữ ngày đi; 0 đêm không tự bật; khóa ô ngày đi/đêm khi đang bật |

**File:** `BookingController.php`, `BookingRoomStayChargeService.php`, `BookingRoomLifecycleService.php`, `BookingRoom.php` (phòng đăng ký cùng ngày không day use = 0 đêm), `CreateRegistrationPage.vue`.

**Test tự động:** `test_same_day_room_without_day_use_can_be_created`, `test_update_keeps_per_room_day_use_switch`, `test_day_use_room_gets_projected_room_charge_on_arrival`.

---

## Dòng 393 — Popup "Cập nhật nhanh nhiều phòng" (đã commit `3edcfe4e`)

**Yêu cầu**
- Chỉ chặn ngày đi < ngày đến; cho chọn ngày đi = ngày đến (sau đó tự bật day use ở lưới nếu muốn).
- Cập nhật ngày phòng: giai đoạn đăng ký **giữ nguyên** nếu phòng vẫn nằm trong; **chỉ mở rộng** khi phòng ra ngoài. Số đêm đăng ký luôn tính lại theo giai đoạn cuối.

**Cách sửa**
- `QuickUpdateModal.vue`: `minDepartureDate` = ngày đến; watcher chỉ đẩy ngày đi khi ngày đi < ngày đến.
- `BookingRoomController::bulkUpdate`: chỉ chặn ngày đi < ngày đến; phòng đang ở thêm điều kiện ngày đi ≥ ngày hệ thống; phòng đổi sang 2 ngày khác nhau thì `is_day_use = false`; phòng cùng ngày không day use bỏ qua kiểm tra AV.
- `expandBookingHeaderDates()` thay `syncBookingHeaderDates()` **chỉ trong bulkUpdate** (cập nhật 1 phòng vẫn dùng hàm cũ — ngoài phạm vi).
- `CreateRegistrationPage.vue::handleQuickUpdateSaved` cập nhật header theo cùng quy tắc.

**Test tự động:** `test_quick_update_allows_same_day_and_rejects_departure_before_arrival`, `test_quick_update_only_widens_booking_period`, `test_bulk_update_rooms_business_rules` (đã đổi kỳ vọng: header 21/08 giữ nguyên, ngày đi mở rộng 26/08, 5 đêm).

---

## Dòng 394 — Thông tin đăng ký: giai đoạn đăng ký phải bao trọn giai đoạn phòng (chưa commit)

**Yêu cầu:** sửa ngày đến/đi của đăng ký phải đảm bảo giai đoạn của mọi phòng nằm trong giai đoạn đăng ký. Không hợp lệ → báo
`Ngày đến hoặc ngày đi của đăng ký không khớp với giai đoạn chi tiết của phòng. Vui lòng kiểm tra thông tin.` và **không lưu**. Lưu ý `SyncRoomDateByBookingDate`.

**Nguyên nhân:** `BookingController::update` chỉ kiểm tra phòng `STATUS_BOOKED`. Ảnh lỗi (GAL9) là phòng **Đang ở** (02/09–06/09) nên bị bỏ qua, đăng ký dời được sang 09/09–10/09.

**Cách sửa (`BookingController.php`)**
- Hằng `PERIOD_BOUND_ROOM_STATUSES` = Đăng ký, Đang ở, Đã trả. Bỏ qua Hủy, Noshow, Đã chuyển (100).
- Kiểm tra trước (request không có `room_allocations` — đây là luồng popup "Thông tin đăng ký"): phòng ngoài giai đoạn mới → 422. Phòng Đăng ký được bỏ qua khi `SyncRoomDateByBookingDate = 1`, ngày đến mới ≥ ngày hệ thống và ngày đăng ký có đổi (vì sẽ được đồng bộ theo).
- Kiểm tra sau, ở cuối transaction (khi ngày đăng ký đổi hoặc có `room_allocations`): kiểm tra trạng thái cuối cùng; vi phạm → `throw` → rollback toàn bộ → 422 cùng thông báo.
- Hàm `roomOutsideBookingPeriod()`.

**Test tự động**
- Mới: `test_booking_period_cannot_leave_inhouse_room_outside` (đúng kịch bản ảnh), `test_booking_period_checks_checked_out_rooms_and_allows_widening`, `test_booking_period_with_sync_moves_reservation_rooms`, `test_booking_period_without_sync_rejects_reservation_room_outside`.
- Đã đổi kỳ vọng theo nghiệp vụ mới:
  - TC-11 `test_sync_room_date_by_booking_date_logic`: trước đây dời ngày đến đăng ký 21→22 trong khi phòng đang ở vẫn 21 (sai theo 394). Nay mở rộng ngày đi 25→26: phòng đăng ký đồng bộ theo, phòng đang ở giữ nguyên 21–25.
  - TC-12: đổi chuỗi thông báo.
  - TC-13: đổi tên thành `test_booking_update_blocked_when_checked_in_room_falls_outside`, kỳ vọng 422.

---

## Dòng 395 — Cập nhật ngày gây trùng số phòng (chưa commit)

**Yêu cầu**
- Popup "Cập nhật" (bulk update): đổi ngày đi/ngày đến làm số phòng đã gán trùng giai đoạn với booking khác, hoặc trùng giai đoạn khóa phòng → phải chặn.
- Kế hoạch phòng phải nhìn thấy được khi có 2 booking trùng phòng.

**Nguyên nhân**
- `bulkUpdate` không gọi kiểm tra trùng số phòng (helper `isRoomNumberOccupied` có sẵn nhưng chỉ dùng ở cập nhật 1 phòng/gán phòng).
- `RoomPlanPage.vue`: mọi thanh trong 1 hàng phòng vẽ cùng vị trí (`top: 2px; height: 33px`) nên thanh sau che kín thanh trước.

**Cách sửa**
- `RoomAvailabilityService::findRoomNumberConflict()` (tách từ `isRoomNumberOccupied`, trả về dòng bị trùng kèm booking). `isRoomNumberOccupied` vẫn giữ hành vi cũ.
- `bulkUpdate`: tính trước giai đoạn mới của mọi phòng được chọn; với mỗi phòng có số phòng, đổi ngày và có giữ phòng (không phải phòng cùng ngày không day use):
  1. trùng lượt ở khác (bất kỳ booking nào, loại trừ các phòng đang cập nhật cùng request) → 422 `ROOM_NUMBER_CONFLICT`, thông báo nêu mã đăng ký bị trùng và giai đoạn;
  2. trùng giữa các phòng được chọn cùng lúc (cùng số phòng) → 422;
  3. trùng khóa OOO/OOS đang hoạt động → 422.
- Kế hoạch phòng: `frontend/src/utils/room-plan-conflicts.js` (`roomPlanStayRange`, `markRoomPlanConflicts`). Thanh trùng có viền đỏ, icon cảnh báo, chia làn theo chiều cao; tooltip có dòng "Trùng phòng với: …". Phòng cùng ngày không day use không tính trùng; khóa phòng có tính.

**Test tự động**
- Backend: `test_quick_update_departure_cannot_overlap_same_room_number` (kịch bản ảnh GAL33/GAL32), `test_quick_update_arrival_cannot_overlap_same_room_number`, `test_quick_update_allows_back_to_back_and_rejects_lock`, `test_quick_update_rejects_overlap_between_selected_rooms`.
- Frontend: `frontend/tests/room-plan-conflicts.test.js` (8 case: khoảng ngày, khóa, liền kề, day use, chia làn).

---

## Test case thủ công (UI)

Chuẩn bị: ghi lại ngày hệ thống; dùng ngày ≥ ngày hệ thống trừ khi ghi khác.

| # | Dòng | Bước | Kỳ vọng |
|---|---|---|---|
| 1 | 394 | Booking có 1 phòng **đang ở** 02/09–06/09. Mở **Thông tin đăng ký**, đổi ngày thành 09/09–10/09, Lưu | Toast "Ngày đến hoặc ngày đi của đăng ký không khớp…", F5 ngày đăng ký vẫn như cũ |
| 2 | 394 | Cùng booking, đổi ngày đi đăng ký 06/09 → 08/09 | Lưu được, phòng đang ở vẫn 02/09–06/09 |
| 3 | 394 | `SyncRoomDateByBookingDate = 1`, booking chỉ có phòng **đăng ký**, dời ngày đăng ký sang giai đoạn khác | Lưu được, phòng dời theo |
| 4 | 394 | `SyncRoomDateByBookingDate = 0`, như case 3 | Bị chặn với thông báo trên |
| 5 | 394 | Booking có phòng đã trả 07–08, thu hẹp ngày đến đăng ký sang 08 | Bị chặn |
| 6 | 395 | BK1 phòng 304 07/09–08/09; BK2 phòng 304 08/09–10/09. Tại BK1 chọn phòng → **Cập nhật** ngày đi 10/09 | 422: "Số phòng 304 bị trùng giai đoạn với đăng ký GALxx (08/09/2026 - 10/09/2026)…", phòng không đổi |
| 7 | 395 | Tương tự case 6 nhưng đổi **ngày đến** để chồng lên BK2 | Bị chặn |
| 8 | 395 | Khóa OOO phòng 304 ngày 12–13, cập nhật phòng sang 11–14 | "Số phòng 304 đang bị khóa (OOO/OOS)…" |
| 9 | 395 | Cập nhật để BK1 trả 08/09, BK2 đến 08/09 (liền kề) | Lưu được |
| 10 | 395 | Dữ liệu cũ đã trùng (tạo bằng SQL hoặc trước bản sửa): mở **Kế hoạch phòng** | Cả 2 thanh hiện, chia đôi chiều cao, viền đỏ + icon; hover thấy "Trùng phòng với: GALxx" |
| 11 | 393 | Popup Cập nhật: chọn ngày đi = ngày đến | Lịch cho chọn, lưu được, phòng 0 đêm, chưa bật day use |
| 12 | 393 | Đăng ký 01/09–03/09, cập nhật phòng 02/09–03/09 | Đăng ký giữ 01/09–03/09, 2 đêm |
| 13 | 391 | Bật/tắt "Ở theo giờ", lưu, F5 | Như mô tả mục 391 |

---

## Lệnh chạy test

```bash
# Backend (SQLite in-memory)
cd backend
php artisan test --filter=BookingBusinessRulesTest
php artisan test            # toàn bộ

# Frontend
cd frontend
node --test tests/*.test.js
npx vite build
```

**Baseline:** toàn bộ backend có **15 test lỗi sẵn từ trước** (không liên quan: thiếu GD extension, lỗi khóa ngoại SQLite trong `RestoreBookingConflictTest`, `UndoCheckInValidationTest`, `RoomOccupancyStatisticsTest`…). Sau bản sửa: 618 test, 589 qua, đúng 15 lỗi đó. Frontend 102/102.

---

## Điểm cần reviewer soi kỹ

1. **Dữ liệu cũ không nhất quán (394):** booking có phòng đã nằm ngoài giai đoạn từ trước. Mọi lần sửa ngày đăng ký hoặc lưu lưới (có `room_allocations`) sẽ bị chặn cho đến khi sửa lại ngày. Đây là chủ ý theo spec, nhưng cần xác nhận với nghiệp vụ.
2. **Phòng Đã chuyển (status 100)** không được tính vào ràng buộc 394. Kiểm tra lại với luồng chuyển phòng/tách phòng.
3. **Kiểm tra sau ở cuối transaction (394)** chạy cả khi lưu lưới đăng ký. Cần kiểm tra không chặn nhầm luồng thêm phòng / hủy phòng / no-show.
4. **395 chỉ áp dụng popup Cập nhật nhanh.** Lưu lưới đăng ký và kéo thả trên kế hoạch phòng dùng các kiểm tra trùng có sẵn (`BookingController` dòng ~2975/3293, `RoomPlanBookingRoomController`). Nên kiểm tra chéo có đường nào khác tạo ra trùng không.
5. **Kế hoạch phòng** chỉ phát hiện trùng giữa các thanh nằm trong khoảng ngày đang xem.
6. `findRoomNumberConflict` **không loại trừ cùng booking** khi gọi từ bulkUpdate (trùng trong cùng booking cũng là lỗi), khác với cập nhật 1 phòng (có loại trừ booking). Xem có cần thống nhất không.
7. Ngày đi của phòng đang ở trong bulkUpdate phải ≥ ngày hệ thống (thêm ở dòng 393). Kiểm tra không ảnh hưởng luồng trả phòng sớm.
