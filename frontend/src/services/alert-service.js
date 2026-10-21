import http from './http'

/**
 * Danh sách khách sinh nhật / phòng có special request
 * @param {Object} params - { from_date, to_date } (YYYY-MM-DD, bỏ trống = ngày hệ thống ~ +3 ngày)
 */
export const fetchAlerts = (params) => http.get('/alerts', { params })

/** Cập nhật tình trạng xử lý (0: Chưa xử lý, 1: Đã xử lý) */
export const updateAlertStatus = (eventKey, status) => http.put('/alerts/status', { event_key: eventKey, status })

/** Nội dung cập nhật hệ thống (icon chuông) */
export const fetchSystemUpdates = () => http.get('/system-updates')
/** Đánh dấu đã đọc: truyền danh sách id, bỏ trống = đọc tất cả */
export const markSystemUpdatesRead = (ids) => http.post('/system-updates/read', ids ? { ids } : {})
export const createSystemUpdate = (payload) => http.post('/system-updates', payload)
export const deleteSystemUpdate = (id) => http.delete(`/system-updates/${id}`)
