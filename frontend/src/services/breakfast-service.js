import http from './http'

/**
 * Lấy danh sách phòng ăn sáng
 * @param {Object} params - { from_date, to_date, date_type: 'breakfast'|'arrival', show_type: 1|0|2, search }
 */
export const fetchBreakfastList = (params = {}) => http.get('/breakfast/list', { params })
export const fetchHotelSettings = () => http.get('/hotel-settings')
