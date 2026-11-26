/**
 * Dòng 532: tab "Phòng noshow" ở màn Sang ngày.
 * Danh sách gồm noshow by date (late check-in / noshow one day) và noshow by stage; chọn 1 phòng để charge bù.
 */

export function splitNoshowRows(rows) {
  const list = rows || []
  return {
    byDate: list.filter(row => row.type === 'late_checkin'),
    byStage: list.filter(row => row.type !== 'late_checkin'),
  }
}

export function noshowRowKey(row) {
  return `${row.type}-${row.roomId}-${row.noshowDateIso}`
}

function formatDmy(iso) {
  const [y, m, d] = String(iso || '').split('-')
  return y && m && d ? `${d}/${m}/${y}` : String(iso || '')
}

/** Trả '' nếu hợp lệ, ngược lại là thông báo lỗi để hiện cho người dùng. */
export function noshowSelectionError(selectedRows) {
  if (!selectedRows || selectedRows.length === 0) return 'Vui lòng tích chọn 1 phòng noshow để charge.'
  if (selectedRows.length > 1) return 'Chỉ charge được từng phòng một, vui lòng chỉ chọn 1 phòng.'
  const row = selectedRows[0]
  if (row.type === 'late_checkin' && row.charged) {
    return `Phòng ${row.roomNumber} đã được charge tiền phòng ngày ${formatDmy(row.noshowDateIso)}.`
  }
  return ''
}

/** Thuộc tính truyền cho ChargeNoshowModal (cùng màn charge noshow của màn đăng ký). */
export function noshowChargeModalProps(row) {
  return {
    bookingId: row.bookingId,
    bookingName: row.bookingName,
    guestName: row.guestName || row.bookingName,
    roomId: row.roomId,
    roomNumber: row.roomNumber,
    arrivalDate: row.arrivalDateIso,
    departureDate: row.departureDateIso,
    roomRate: Number(row.rate) || 0,
    defaultDate: row.noshowDateIso,
  }
}
