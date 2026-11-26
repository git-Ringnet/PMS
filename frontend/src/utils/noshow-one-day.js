/**
 * Dòng 529 (Sang ngày, tab Phòng đến): phòng có ngày đến = ngày đi = ngày hệ thống (0 đêm)
 * không được Noshow one day (dời ngày đến +1) vì ngày đến mới sẽ lớn hơn ngày đi.
 * Chỉ được Noshow by stage.
 */
export function zeroNightArrivalItems(items) {
  return (items || []).filter(item => Number(item?.nights) <= 0)
}

export function zeroNightArrivalMessage(zeroNightItems) {
  const rooms = zeroNightItems.map(item => item.roomNumber).join(', ')
  return `Phòng ${rooms} có ngày đến bằng ngày đi (0 đêm) nên không thể Noshow one day. Vui lòng dùng Noshow (by stage).`
}
