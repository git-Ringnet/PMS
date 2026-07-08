import http from './http'

// ==================== BOOKINGS (ĐĂNG KÝ PHÒNG) ====================

/**
 * Lấy danh sách booking
 * @param {Object} params - Filter: { search, status, registration_status_id, from_date, to_date, arrival_date }
 */
export const fetchBookings = (params = {}) => http.get('/bookings', { params })

/**
 * Lấy chi tiết một booking
 * @param {number} id - ID booking
 */
export const fetchBooking = (id) => http.get(`/bookings/${id}`)

/**
 * Tạo booking mới
 * @param {Object} data - Thông tin booking
 */
export const createBooking = (data) => http.post('/bookings', data)

/**
 * Cập nhật booking
 * @param {number} id - ID booking
 * @param {Object} data - Dữ liệu cập nhật
 */
export const updateBooking = (id, data) => http.put(`/bookings/${id}`, data)

/**
 * Xóa booking
 * @param {number} id - ID booking
 */
export const deleteBooking = (id) => http.delete(`/bookings/${id}`)

// ==================== BOOKING ROOMS (GIAO PHÒNG / CHECK-IN) ====================
export const autoAssignRooms = (bookingId) => http.post(`/bookings/${bookingId}/auto-assign`)
export const assignRoom = (bookingId, roomId, data) => http.post(`/bookings/${bookingId}/rooms/${roomId}/assign`, data)
export const unassignRoom = (bookingId, roomId) => http.post(`/bookings/${bookingId}/rooms/${roomId}/unassign`)
export const checkInRoom = (bookingId, roomId, data) => http.post(`/bookings/${bookingId}/rooms/${roomId}/checkin`, data)
export const undoCheckInRoom = (bookingId, roomId) => http.post(`/bookings/${bookingId}/rooms/${roomId}/undo-checkin`)
export const cancelBookingRoom = (bookingId, roomId, data) => http.post(`/bookings/${bookingId}/rooms/${roomId}/cancel`, data)

// ==================== PAYMENT METHODS (PHƯƠNG THỨC THANH TOÁN) ====================
export const fetchPaymentMethods = (params = {}) => http.get('/payment-methods', { params })

// ==================== REGISTRATION STATUSES (TÌNH TRẠNG ĐẶT PHÒNG) ====================
export const fetchRegistrationStatuses = (params = {}) => http.get('/registration-statuses', { params })

// ==================== ROOM CLASSES & RATE CODES ====================
export const fetchRoomClasses = () => http.get('/room-classes')
export const fetchRoomRateCodes = () => http.get('/room-rate-codes')

// ==================== CONFIG & SYSTEM ====================
export const fetchHotelSettings = () => http.get('/hotel-settings')
export const fetchSystemTime = () => http.get('/system-time')

// ==================== PAYMENTS (ĐẶT CỌC) ====================
export const fetchPayments = (bookingId) => http.get(`/bookings/${bookingId}/payments`)
export const createPayment = (bookingId, data) => http.post(`/bookings/${bookingId}/payments`, data)
export const updatePayment = (id, data) => http.put(`/payments/${id}`, data)
export const deletePayment = (id) => http.delete(`/payments/${id}`)
export const splitPayment = (id, data) => http.post(`/payments/${id}/split`, data)
export const transferPayment = (id, data) => http.post(`/payments/${id}/transfer`, data)
export const fetchCurrencies = (params = {}) => http.get('/currencies', { params })
export const fetchAvailability = (params = {}) => http.get('/availability', { params })
export const checkAvailability = (params = {}) => http.get('/availability/check', { params })




