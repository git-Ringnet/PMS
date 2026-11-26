// Giờ nhận phòng / trả phòng tiêu chuẩn của khách sạn
// (Cấu hình hệ thống › Định nghĩa khách sạn › Thông tin khách sạn).
// Dùng làm giờ mặc định cho phòng mới; chưa cấu hình thì 14:00 / 12:00 như trước.
import { ref } from 'vue'

export const DEFAULT_CHECK_IN_TIME = '14:00'
export const DEFAULT_CHECK_OUT_TIME = '12:00'

export const standardCheckInTime = ref(DEFAULT_CHECK_IN_TIME)
export const standardCheckOutTime = ref(DEFAULT_CHECK_OUT_TIME)

/** "9:5", "09:05", "09:05:00" -> "09:05"; sai định dạng trả về null. */
export function normalizeStayTime(value) {
  const match = String(value ?? '').trim().match(/^(\d{1,2}):(\d{1,2})(?::\d{1,2})?$/)
  if (!match) return null
  const hour = Number(match[1])
  const minute = Number(match[2])
  if (hour > 23 || minute > 59) return null
  return `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`
}

/** Nhận dữ liệu /hotel-settings (đã lấy sẵn) để cập nhật giờ chuẩn. */
// Chỉ cập nhật khi dữ liệu có khóa giờ chuẩn: trang chưa tải xong (object rỗng) không được
// ghi đè giá trị đã tải ở trang khác. Giá trị sai định dạng thì quay về mặc định.
export function applyStandardStayTimes(settings) {
  if (settings && settings.StandardCheckInTime !== undefined && settings.StandardCheckInTime !== null) {
    standardCheckInTime.value = normalizeStayTime(settings.StandardCheckInTime) || DEFAULT_CHECK_IN_TIME
  }
  if (settings && settings.StandardCheckOutTime !== undefined && settings.StandardCheckOutTime !== null) {
    standardCheckOutTime.value = normalizeStayTime(settings.StandardCheckOutTime) || DEFAULT_CHECK_OUT_TIME
  }
}

let pending = null

/** Tải giờ chuẩn một lần cho cả phiên; force = true để tải lại sau khi lưu cấu hình. */
export function loadStandardStayTimes(force = false) {
  if (!pending || force) {
    // Import động để các hàm thuần ở file này dùng được trong test node (không kéo theo http/alias).
    pending = import('@/services/booking-service')
      .then(({ fetchHotelSettings }) => fetchHotelSettings())
      .then(res => applyStandardStayTimes(res?.data?.data ?? res?.data ?? {}))
      .catch(() => { pending = null })
  }
  return pending
}
