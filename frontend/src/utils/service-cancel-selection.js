/**
 * Dòng 458: một bill có thể còn dòng booking_room_services không hiện trên bảng (sau nhiều lần chuyển bill).
 * Đã chọn hết các dòng đang hiện của bill thì xóa theo bill, không gửi mã dòng để server tự lấy đủ.
 * Chỉ chọn một phần bill thì vẫn gửi mã dòng để server chặn như cũ.
 */
export function cancelMirrorIds(selectedItems, visibleItems) {
  const selected = new Set(Array.isArray(selectedItems) ? selectedItems : [])
  const partialBillIds = new Set()
  for (const item of Array.isArray(visibleItems) ? visibleItems : []) {
    if (!selected.has(item) && item?.serviceBillId) partialBillIds.add(String(item.serviceBillId))
  }
  const ids = [...selected]
    .filter(item => partialBillIds.has(String(item?.serviceBillId)))
    .map(item => item.bookingRoomServiceId)
    .filter(Number.isInteger)
  return [...new Set(ids)]
}
