import http from './http'

// ==================== QUẢN LÝ PHÒNG TRỐNG (AVAILABILITY) ====================

/**
 * Lấy lưới thông tin phòng trống và thống kê theo khoảng ngày
 * @param {string} startDate YYYY-MM-DD
 * @param {string} endDate YYYY-MM-DD
 */
export const fetchAvailabilityGrid = (startDate, endDate) => {
  return http.get('/availability', {
    params: {
      start_date: startDate,
      end_date: endDate
    }
  })
}

/**
 * Lấy danh sách trạng thái đăng ký phòng (SP1311)
 */
export const fetchRegistrationStatuses = () => {
  return http.get('/registration-statuses', { params: { is_availability: true } })
}
