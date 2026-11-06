// Dòng 498 (Khai báo lưu trú): quy tắc hiển thị một dòng khách theo ngày xem và tick "Phòng chuyển".
// Hàm thuần để test được; mọi thời gian là timestamp 0h của ngày (hoặc null).
//
// status: 0 đăng ký, 1 đang ở, 2 đã trả, 100 phòng cũ đã chuyển đi.
// isSameDayMoved: phòng mới được chuyển tới cùng ngày với ngày đến của phòng cũ (vừa check in rồi chuyển).
// isStayedThenMoved: phòng mới được chuyển tới sau khi đã ở ít nhất 1 đêm ở phòng cũ.
export function isResidenceRowVisible(row, targetTime, filters = {}) {
  const status = Number(row.status)
  const { arrTime, depTime } = row
  const inRange = arrTime != null && depTime != null && arrTime < targetTime && targetTime < depTime

  // Phòng cũ đã chuyển đi: là phòng check in từ đầu nên hiển thị ở danh sách chính của ngày đến (không cần tick
  // Phòng chuyển) và các ngày đang ở trước ngày chuyển. Chuyển ngay trong ngày đến (0 đêm) thì không hiển thị.
  if (status === 100) {
    if (arrTime == null || arrTime === depTime) return false
    if (arrTime === targetTime) return true
    return inRange ? !!filters.inHouse : false
  }

  // Phòng có ngày đến = ngày đi: chỉ hiển thị đúng ngày đó, kể cả khi đã trả phòng trong ngày.
  // Nếu đó là phòng vừa chuyển tới (chuyển rồi trả trong ngày) thì vẫn chỉ hiện khi tick "Phòng chuyển".
  if (status === 2 && arrTime != null && arrTime === depTime) {
    if (arrTime !== targetTime) return false
    return (row.isStayedThenMoved || row.isSameDayMoved) ? !!filters.roomMove : true
  }

  if (status === 1) {
    // Phòng vừa được chuyển tới: ngày chuyển chỉ hiển thị khi tick "Phòng chuyển".
    if (row.isStayedThenMoved || row.isSameDayMoved) {
      if (arrTime === targetTime) return !!filters.roomMove
      return inRange ? !!filters.inHouse : false
    }
    if (arrTime === targetTime) return true
    return inRange ? !!filters.inHouse : false
  }

  return false
}

// Dòng 498: thông số Immigration_ExportExelChangeToRegistered = 1.
// Bật switch "Khách đã đăng ký" chỉ hiển thị khách đã xuất; tắt chỉ hiển thị khách chưa xuất (không hiển thị chung).
export function matchesDeclarationFilter(isDeclared, showDeclared) {
  return showDeclared ? !!isDeclared : !isDeclared
}
