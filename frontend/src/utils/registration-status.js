/**
 * Dòng 540: tình trạng đăng ký không ảnh hưởng phòng (registration_statuses.is_availability = 0, vd Waiting)
 * không chiếm phòng nên màn đăng ký khóa cột Số phòng khi sửa.
 * `registrationStatusId` là mã nghiệp vụ (booking_status_id); nếu không khớp thì thử theo id.
 */
export function isRegistrationStatusWithoutAvailability(statuses, registrationStatusId) {
  if (!registrationStatusId || !Array.isArray(statuses)) return false
  const wanted = Number(registrationStatusId)
  const status = statuses.find(item => Number(item.booking_status_id) === wanted)
    || statuses.find(item => Number(item.id) === wanted)
  if (!status) return false
  return status.is_availability === false || Number(status.is_availability) === 0
}

/**
 * Dòng 540 (note 26/11): sửa đăng ký từ tình trạng giữ phòng (hoặc chưa có tình trạng) sang tình trạng
 * is_availability = 0 thì backend xóa số phòng đã gán. Trả về các số phòng sẽ bị xóa để báo trong thông báo xác nhận.
 */
export function roomNumbersClearedByStatusChange(statuses, oldStatusId, newStatusId, rooms) {
  if (!newStatusId || Number(oldStatusId) === Number(newStatusId)) return []
  if (!isRegistrationStatusWithoutAvailability(statuses, newStatusId)) return []
  if (oldStatusId && isRegistrationStatusWithoutAvailability(statuses, oldStatusId)) return []
  const numbers = (Array.isArray(rooms) ? rooms : [])
    .map(room => String(room?.roomNumber ?? '').trim())
    .filter(Boolean)
  return [...new Set(numbers)]
}
