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
