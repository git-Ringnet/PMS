const dateOf = value => String(value || '').slice(0, 10)

/**
 * Số đêm hiển thị trong chi tiết phòng của màn Đăng ký.
 * - Cùng ngày đến/đi: chỉ có 1 đêm khi bật "Ở theo giờ" (day-use).
 * - Dòng 358: phòng đã late check-in CÓ charge (đêm cũ đã post bill) thì bật Ở theo giờ vẫn
 *   không hiện thêm đêm day-use; chỉ còn bill đã post của đêm late.
 */
export function displayNightCount(room) {
  const isSameDay = !!room?.checkIn && !!room?.checkOut && dateOf(room.checkIn) === dateOf(room.checkOut)
  if (isSameDay) return room.hourly && !room.lateCheckinCharged ? 1 : 0
  const raw = Number(room?.nights)
  return Number.isFinite(raw) ? Math.max(0, raw) : 1
}
