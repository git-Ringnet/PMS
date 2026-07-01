import http from './http'

// ==================== OUTLETS ====================
export const fetchOutlets = () => http.get('/outlets')
export const createOutlet = (data) => http.post('/outlets', data)
export const updateOutlet = (id, data) => http.put(`/outlets/${id}`, data)
export const deleteOutlet = (id) => http.delete(`/outlets/${id}`)
export const reorderOutlets = (orders) => http.post('/outlets/reorder', { orders })

// ==================== DEPARTMENTS ====================
export const fetchDepartments = () => http.get('/departments')

// ==================== HOTEL SERVICES ====================
export const fetchHotelServices = () => http.get('/hotel-services')

// ==================== FB LOCATIONS (ZONES) ====================
export const fetchFbLocations = (outletCode) => http.get('/fb-locations', { params: { outlet_code: outletCode } })
export const createFbLocation = (formData) => http.post('/fb-locations', formData, {
  headers: { 'Content-Type': 'multipart/form-data' }
})
export const updateFbLocation = (id, formData) => http.post(`/fb-locations/${id}?_method=PUT`, formData, {
  headers: { 'Content-Type': 'multipart/form-data' }
})
export const deleteFbLocation = (id) => http.delete(`/fb-locations/${id}`)

// ==================== FB TABLES ====================
export const fetchFbTables = (params) => {
  const queryParams = typeof params === 'object' ? params : { location_id: params }
  return http.get('/fb-tables', { params: queryParams })
}
export const createFbTable = (data) => http.post('/fb-tables', data)
export const updateFbTable = (id, data) => http.put(`/fb-tables/${id}`, data)
export const deleteFbTable = (id) => http.delete(`/fb-tables/${id}`)
export const bulkCreateFbTables = (data) => http.post('/fb-tables/bulk-create', data)
export const deleteFbTableRow = (data) => http.post('/fb-tables/delete-row', data)
