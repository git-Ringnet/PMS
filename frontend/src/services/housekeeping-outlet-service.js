import http from './http'

export const fetchHousekeepingOutlets = () => http.get('/housekeeping/outlets')
export const createHousekeepingOutlet = (data) => {
  if (data instanceof FormData) {
    return http.post('/housekeeping/outlets', data, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
  }
  return http.post('/housekeeping/outlets', data)
}
export const updateHousekeepingOutlet = (id, data) => {
  if (data instanceof FormData) {
    if (!data.has('_method')) data.append('_method', 'PUT')
    return http.post(`/housekeeping/outlets/${id}`, data, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })
  }
  return http.put(`/housekeeping/outlets/${id}`, data)
}
export const deleteHousekeepingOutlet = (id) => http.delete(`/housekeeping/outlets/${id}`)
export const forceDeleteHousekeepingOutlet = (id) => http.delete(`/housekeeping/outlets/${id}/force`)
export const reorderHousekeepingOutlets = (orders) => http.post('/housekeeping/outlets/reorder', { orders })

