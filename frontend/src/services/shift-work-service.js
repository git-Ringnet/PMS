import http from './http'

/**
 * Tab 1: Danh sách phòng đến
 * @param {Object} params - { date, status, search }
 */
export const fetchArrivals = (params) => http.get('/shift-work/arrivals', { params })

/**
 * Tab 2: Danh sách phòng đi
 * @param {Object} params - { date, status, search }
 */
export const fetchDepartures = (params) => http.get('/shift-work/departures', { params })

/**
 * Tab 3: Đăng ký chờ xác nhận
 * @param {Object} params - { from_date, to_date, search }
 */
export const fetchPending = (params) => http.get('/shift-work/pending', { params })

/**
 * Cập nhật ghi chú xác nhận Sale
 * @param {number|string} bookingId
 * @param {string} note
 */
export const updatePendingNote = (bookingId, note) => http.put(`/shift-work/pending/${bookingId}/note`, { note })

/**
 * Tab 4: Đón tiễn khách
 * @param {Object} params - { date, type, search }
 */
export const fetchShuttle = (params) => http.get('/shift-work/shuttle', { params })

/**
 * Tab 5: Phòng không đến (Noshow)
 * @param {Object} params - { from_date, to_date, search }
 */
export const fetchNoshow = (params) => http.get('/shift-work/noshow', { params })

/**
 * Tab 6: Sinh nhật khách
 * @param {Object} params - { from_date, to_date, search }
 */
export const fetchBirthdays = (params) => http.get('/shift-work/birthdays', { params })
