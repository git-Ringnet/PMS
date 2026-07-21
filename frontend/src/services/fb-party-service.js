import http from './http'

export const fetchParties = (filters = {}) => {
  return http.get('/fb-parties', { params: filters })
}

export const createParty = (partyData) => {
  return http.post('/fb-parties', partyData)
}

export const getParty = (id) => {
  return http.get(`/fb-parties/${id}`)
}

export const updateParty = (id, partyData) => {
  return http.put(`/fb-parties/${id}`, partyData)
}

export const cancelParty = (id, reason) => {
  return http.post(`/fb-parties/${id}/cancel`, { reason })
}

export const completeSubParty = (partyId, subPartyId) => {
  return http.post(`/fb-parties/${partyId}/sub-parties/${subPartyId}/complete`)
}

export const checkFbPartyConflict = (data) => {
  return http.post('/fb-parties/check-conflict', data)
}
