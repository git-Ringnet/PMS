import http from './http'

// ==================== LỊCH SỬ THAO TÁC ====================
export const fetchActivityLogs = (params = {}) => http.get('/activity-logs', { params })
export const fetchActivityLogStats = () => http.get('/activity-logs/stats')
// Checklist TC_HT_06 dòng 37: server dựng file .xlsx từ bảng đã định dạng (mỗi cột 1 ô).
export const exportActivityLogsExcel = (table) => http.post('/activity-logs/export', table, { responseType: 'blob' })
