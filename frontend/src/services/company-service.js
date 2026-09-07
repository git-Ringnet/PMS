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
export const deleteSystemBranch = (id, params = {}) => http.delete(`/system-branches/${id}`, { params })

// ==================== SYSTEM USERS / EMPLOYEES (NHÂN VIÊN HỆ THỐNG) ====================
export const fetchUsers = (params = {}) => http.get('/users', { params })
export const createUser = (data) => http.post('/users', data)
export const updateUser = (id, data) => http.put(`/users/${id}`, data)
export const deleteUser = (id) => http.delete(`/users/${id}`)

// ==================== COMPANY INFO / BUSINESS INFO (THÔNG TIN CÔNG TY) ====================
export const fetchBusinessInfo = () => http.get('/info-business')
export const updateBusinessInfo = (data) => http.put('/info-business', data)
export const uploadBusinessLogo = (formData) => http.post('/info-business/logo', formData)
export const deleteBusinessLogo = () => http.delete('/info-business/logo')

// ==================== SYSTEM USERS / EMPLOYEES (NHÂN VIÊN HỆ THỐNG) SIGNATURE ====================
export const uploadUserSignature = (id, formData) => http.post(`/users/${id}/signature`, formData)
export const deleteUserSignature = (id) => http.delete(`/users/${id}/signature`)

export const syncCompanies = () => http.post('/companies/sync')
export const exportCompaniesExcel = () => http.get('/companies/export', { responseType: 'blob' })
export const importCompaniesExcel = (formData) => http.post('/companies/import', formData, {
  headers: { 'Content-Type': 'multipart/form-data' }
})
export const companyTemplateExcel = () => http.get('/companies/template', { responseType: 'blob' })

// ==================== ROLES & PERMISSIONS ====================
export const fetchRoles = () => http.get('/roles')
export const createRole = (data) => http.post('/roles', data)
export const updateRole = (id, data) => http.put(`/roles/${id}`, data)
export const deleteRole = (id) => http.delete(`/roles/${id}`)
export const fetchAllPermissions = () => http.get('/permissions')
export const syncRolePermissions = (roleId, data) => http.post(`/roles/${roleId}/permissions/sync`, data)

// ==================== USER PERMISSIONS ====================
export const fetchUserPermissions = (userId) => http.get(`/users/${userId}/permissions`)
export const syncUserBranches = (userId, data) => http.post(`/users/${userId}/branches/sync`, data)
export const syncUserRoles = (userId, data) => http.post(`/users/${userId}/roles/sync`, data)
// ==================== DEPARTMENTS & MODULES ====================
export const fetchDepartments = () => http.get('/departments')
export const createDepartment = (data) => http.post('/departments', data)
export const fetchModules = () => http.get('/modules')
export const fetchSystemBranchesList = () => http.get('/system-branches/list')
// ==================== ORGANIZATION RBAC ====================
export const fetchOrganization = () => http.get('/organization')
export const createOrganizationDepartment = (data) => http.post('/organization/departments', data)
export const updateOrganizationDepartment = (id, data) => http.put(`/organization/departments/${id}`, data)
export const createPosition = (data) => http.post('/organization/positions', data)
export const updatePosition = (id, data) => http.put(`/organization/positions/${id}`, data)
export const deletePosition = (id) => http.delete(`/organization/positions/${id}`)
export const syncPositionBranches = (id, data) => http.post(`/organization/positions/${id}/branches/sync`, data)
export const fetchBranchRoleMatrix = (roleId, params) => http.get(`/roles/${roleId}/branch-permissions`, { params })
export const syncBranchRoleMatrix = (roleId, data) => http.post(`/roles/${roleId}/branch-permissions/sync`, data)
export const copyRole = (roleId, data) => http.post(`/roles/${roleId}/copy`, data)
export const createPermissionScreen = (data) => http.post('/permission-screens', data)
export const fetchUserOrganization = (userId) => http.get(`/users/${userId}/organization`)
export const syncUserOrganization = (userId, data) => http.post(`/users/${userId}/organization/sync`, data)
export const syncUserWarehouses = (userId, data) => http.post(`/users/${userId}/warehouses/sync`, data)
export const fetchWarehouses = (branch = null) => http.get('/warehouses', branch ? {
  headers: {
    'X-Branch-Id': String(branch.id),
    'X-Branch-Code': branch.code,
  },
} : {})
export const resetUserPassword = (userId) => http.post(`/users/${userId}/reset-password`)
export const changeUserPassword = (data) => http.post('/me/change-password', data)
