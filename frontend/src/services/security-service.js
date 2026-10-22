import http from './http'

/** Cấu hình Bảo mật nâng cao (chỉ super admin) */
export const fetchSecuritySettings = () => http.get('/security-settings')
export const updateSecuritySettings = (payload) => http.put('/security-settings', payload)
export const fetchMyIp = () => http.get('/security-settings/my-ip')

export const createAllowedIp = (payload) => http.post('/security-settings/ips', payload)
export const updateAllowedIp = (id, payload) => http.put(`/security-settings/ips/${id}`, payload)
export const deleteAllowedIp = (id) => http.delete(`/security-settings/ips/${id}`)

export const createTimeWindow = (payload) => http.post('/security-settings/time-windows', payload)
export const updateTimeWindow = (id, payload) => http.put(`/security-settings/time-windows/${id}`, payload)
export const deleteTimeWindow = (id) => http.delete(`/security-settings/time-windows/${id}`)

export const addExemptUsers = (userIds) => http.post('/security-settings/exempt-users', { user_ids: userIds })
export const removeExemptUser = (userId) => http.delete(`/security-settings/exempt-users/${userId}`)
