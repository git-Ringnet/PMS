# Booking room lifecycle — date and day-use invariants

## Scope

- `BookingRoom::ActutalNumOfDays` and `NumOfDays` are zero for a day-use room, including same-day arrival/departure.
- The normal overnight fallback remains one night for a same-day room that is not marked `is_day_use`.
- The bulk room update endpoint accepts equal arrival/departure dates only for rooms already marked day-use.
- Room date edits synchronize unposted projected RM/EB rows and child-breakfast details through `BookingRoomLifecycleService`; posted rows remain financial history.
- Booking header date synchronization still applies only to reservation rooms when `SyncRoomDateByBookingDate=1`; checked-in/checked-out/moved history is not rewritten.
- A moved-to room is not eligible for undo check-in, even when the move occurred on the current PMS date; only the original check-in segment can be undone.
- The Create Registration screen hides room status `3` history unless the booking header itself is status `3`.

## API and components

- `PUT /api/bookings/{bookingId}/rooms/{roomId}` — single room update.
- `POST /api/bookings/{bookingId}/rooms/bulk-update` — bulk room update.
- `PUT /api/bookings/{bookingId}` — booking header update; reservation-room dates follow `SyncRoomDateByBookingDate`.
- `POST /api/bookings/{bookingId}/rooms/{roomId}/undo-checkin` — undo only the original same-day check-in segment.
- `backend/app/Models/BookingRoom.php` — lifecycle-derived night count and guest actual-date hooks.
- `backend/app/Services/BookingRoomLifecycleService.php` — shared synchronization of date-dependent projected rows.

## Business constraints

- A day-use stay occupies availability only when `is_day_use=1`; a same-day non-day-use row is not counted by `RoomAvailabilityService`.
- RM/EB/child-breakfast rows outside the new `[arrival_date, departure_date)` period are removed only when unposted.
- Posted service/bill history is never rewritten by a date edit.
- Child-breakfast details are regenerated for the current stay dates and retain an existing entered amount when possible; a missing/zero non-free amount uses the configured child breakfast rate.

## Verification

- PHP lint: `BookingRoom.php`, `BookingRoomLifecycleService.php`, `BookingRoomController.php`, `BookingController.php` and the Booking business-rules test.
- Regression test: `BookingBusinessRulesTest::test_day_use_room_persists_zero_nights_and_accepts_same_day_bulk_update`.
- Full feature execution remains environment-blocked when the configured `mysql_data` connection is absent; route registration is verified with `php artisan route:list --path=api`.
