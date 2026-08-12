import http from './http'

export const fetchHousekeepingOutlets = () => http.get('/housekeeping/outlets')
export const createHousekeepingOutlet = (data) => http.post('/housekeeping/outlets', data)
export const updateHousekeepingOutlet = (id, data) => http.put(`/housekeeping/outlets/${id}`, data)
export const deleteHousekeepingOutlet = (id) => http.delete(`/housekeeping/outlets/${id}`)
export const forceDeleteHousekeepingOutlet = (id) => http.delete(`/housekeeping/outlets/${id}/force`)
export const reorderHousekeepingOutlets = (orders) => http.post('/housekeeping/outlets/reorder', { orders })
