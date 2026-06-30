import http from './http'

// ==================== LỊCH SỬ THAO TÁC ====================
export const fetchActivityLogs = (params = {}) => http.get('/activity-logs', { params })
export const fetchActivityLogStats = () => http.get('/activity-logs/stats')
