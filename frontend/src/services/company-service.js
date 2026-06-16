import http from './http'

// ==================== MARKETS (THỊ TRƯỜNG) ====================
export const fetchMarkets = () => http.get('/markets')
export const createMarket = (data) => http.post('/markets', data)
export const updateMarket = (id, data) => http.put(`/markets/${id}`, data)
export const deleteMarket = (id) => http.delete(`/markets/${id}`)

// ==================== CUSTOMER SOURCES (NGUỒN KHÁCH) ====================
export const fetchCustomerSources = () => http.get('/customer-sources')
export const createCustomerSource = (data) => http.post('/customer-sources', data)
export const updateCustomerSource = (id, data) => http.put(`/customer-sources/${id}`, data)
export const deleteCustomerSource = (id) => http.delete(`/customer-sources/${id}`)

// ==================== BRANCHES (CHI NHÁNH) ====================
export const fetchBranches = () => http.get('/branches')
export const createBranch = (data) => http.post('/branches', data)
export const updateBranch = (id, data) => http.put(`/branches/${id}`, data)
export const deleteBranch = (id) => http.delete(`/branches/${id}`)

// ==================== BOOKERS (Người đặt phòng) ====================
export const fetchBookers = () => http.get('/bookers')
export const createBooker = (data) => http.post('/bookers', data)
export const updateBooker = (id, data) => http.put(`/bookers/${id}`, data)
export const deleteBooker = (id) => http.delete(`/bookers/${id}`)

// ==================== COMPANIES (CÔNG TY) ====================
export const fetchCompanies = (params = {}) => http.get('/companies', { params })
export const createCompany = (data) => http.post('/companies', data)
export const updateCompany = (id, data) => http.put(`/companies/${id}`, data)
export const deleteCompany = (id) => http.delete(`/companies/${id}`)

// ==================== SYSTEM BRANCHES (CHI NHÁNH HỆ THỐNG) ====================
export const fetchSystemBranches = (params = {}) => http.get('/system-branches', { params })
export const createSystemBranch = (data) => http.post('/system-branches', data)
export const updateSystemBranch = (id, data) => http.put(`/system-branches/${id}`, data)
export const deleteSystemBranch = (id) => http.delete(`/system-branches/${id}`)

// ==================== SYSTEM USERS / EMPLOYEES (NHÂN VIÊN HỆ THỐNG) ====================
export const fetchUsers = (params = {}) => http.get('/users', { params })
export const createUser = (data) => http.post('/users', data)
export const updateUser = (id, data) => http.put(`/users/${id}`, data)
export const deleteUser = (id) => http.delete(`/users/${id}`)

// ==================== COMPANY INFO / BUSINESS INFO (THÔNG TIN CÔNG TY) ====================
export const fetchBusinessInfo = () => http.get('/info-business')
export const updateBusinessInfo = (data) => http.put('/info-business', data)
export const uploadBusinessLogo = (formData) => http.post('/info-business/logo', formData, {
  headers: { 'Content-Type': 'multipart/form-data' }
})
export const deleteBusinessLogo = () => http.delete('/info-business/logo')

// ==================== SYSTEM USERS / EMPLOYEES (NHÂN VIÊN HỆ THỐNG) SIGNATURE ====================
export const uploadUserSignature = (id, formData) => http.post(`/users/${id}/signature`, formData, {
  headers: { 'Content-Type': 'multipart/form-data' }
})
export const deleteUserSignature = (id) => http.delete(`/users/${id}/signature`)
