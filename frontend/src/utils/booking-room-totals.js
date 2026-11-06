/**
 * Dòng 482: tổng tiền đăng ký.
 * - Phòng hủy (3): chỉ để truy vết, không tính tiền, không đếm.
 * - Phòng chuyển (100): đã ở một phần giai đoạn trước khi chuyển nên TIỀN vẫn phải cộng vào tổng
 *   (nếu không tổng thiếu tiền phòng chuyển); nhưng không đếm phòng/khách/giường vì khách đã sang phòng mới.
 *
 * @param {Array<object>} rooms
 * @param {{ chargeTotal: Function, extraBedQty: Function, extraBedTotal: Function, roomTotal: Function }} fn
 */
export function summarizeRoomTotals(rooms, fn) {
  const summary = { count: 0, priceSum: 0, adults: 0, babies: 0, children: 0, extraBedQty: 0, extraBed: 0, total: 0 }

  for (const room of Array.isArray(rooms) ? rooms : []) {
    const status = Number(room?.bookingRoomStatus)
    if (status === 3) continue

    summary.priceSum += fn.chargeTotal(room)
    summary.extraBed += fn.extraBedTotal(room)
    summary.total += fn.roomTotal(room)

    if (status === 100) continue
    summary.count += 1
    summary.adults += Number(room.adults) || 0
    summary.babies += Number(room.babies) || 0
    summary.children += Number(room.children) || 0
    summary.extraBedQty += fn.extraBedQty(room)
  }

  return summary
}
