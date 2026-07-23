import http from './http'

// ==================== BOOKINGS (ĐĂNG KÝ PHÒNG) ====================

/**
 * Lấy danh sách booking
 * @param {Object} params - Filter: { search, status, registration_status_id, from_date, to_date, arrival_date }
 */
export const fetchBookings = (params = {}) => http.get('/bookings', { params })
export const fetchBookingInitDropdowns = () => http.get('/bookings/init-dropdowns')

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
 * @param {Object} data - Lý do hủy { cancel_reason_id, note }
 */
export const deleteBooking = (id, data = {}) => http.delete(`/bookings/${id}`, { data })
export const copyBooking = (id, data) => http.post(`/bookings/${id}/copy`, data)

// ==================== BOOKING ROOMS (GIAO PHÒNG / CHECK-IN) ====================
export const autoAssignRooms = (bookingId) => http.post(`/bookings/${bookingId}/auto-assign`)
export const assignRoom = (bookingId, roomId, data) => http.post(`/bookings/${bookingId}/rooms/${roomId}/assign`, data)
export const unassignRoom = (bookingId, roomId) => http.patch(`/bookings/${bookingId}/rooms/${roomId}/unassign`)
export const checkInRoom = (bookingId, roomId) => http.patch(`/bookings/${bookingId}/rooms/${roomId}/check-in`)
export const undoCheckInRoom = (bookingId, roomId) => http.post(`/bookings/${bookingId}/rooms/${roomId}/undo-checkin`)
export const upgradeRoom = (bookingId, roomId, data) => http.patch(`/bookings/${bookingId}/rooms/${roomId}/upgrade`, data)
export const cancelBookingRoom = (bookingId, roomId, data = {}) => http.delete(`/bookings/${bookingId}/rooms/${roomId}/cancel`, { data })
export const lockRoomMove = (bookingId, roomId, data = {}) => http.post(`/bookings/${bookingId}/rooms/${roomId}/lock-move`, data)
export const unlockRoomMove = (bookingId, roomId) => http.delete(`/bookings/${bookingId}/rooms/${roomId}/lock-move`)
export const updateBookingRoom = (bookingId, roomId, data) => http.put(`/bookings/${bookingId}/rooms/${roomId}`, data)
export const splitBookingRoom = (bookingId, roomId, data) => http.post(`/bookings/${bookingId}/rooms/${roomId}/split`, data)

// ==================== PAYMENT METHODS (PHƯƠNG THỨC THANH TOÁN) ====================
export const fetchPaymentMethods = (params = {}) => http.get('/payment-methods', { params })

// ==================== REGISTRATION STATUSES (TÌNH TRẠNG ĐẶT PHÒNG) ====================
export const fetchRegistrationStatuses = (params = {}) => http.get('/registration-statuses', { params })

// ==================== ROOM CLASSES & RATE CODES ====================
export const fetchRoomClasses = () => http.get('/room-classes')
export const fetchRoomForms = () => http.get('/room-forms')
export const fetchRoomRateCodes = () => http.get('/room-rate-codes')

// ==================== CONFIG & SYSTEM ====================
export const fetchHotelSettings = () => http.get('/hotel-settings')
export const fetchSystemTime = () => http.get('/system-time')
export const fetchSystemDate = () => http.get('/system-date')

// ==================== USER SETTINGS ====================
export const fetchUserSettings = () => http.get('/user-settings')
export const updateUserSettings = (data) => http.put('/user-settings', data)

// ==================== PAYMENTS (ĐẶT CỌC) ====================
export const fetchPayments = (bookingId) => http.get(`/bookings/${bookingId}/payments`)
export const createPayment = (bookingId, data) => {
  if (data instanceof FormData) {
    return http.post(`/bookings/${bookingId}/payments`, data, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    })
  }
  return http.post(`/bookings/${bookingId}/payments`, data)
}
export const updatePayment = (id, data) => {
  if (data instanceof FormData) {
    return http.post(`/payments/${id}`, data, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    })
  }
  return http.put(`/payments/${id}`, data)
}
export const deletePayment = (id) => http.delete(`/payments/${id}`)
export const splitPayment = (id, data) => http.post(`/payments/${id}/split`, data)
export const transferPayment = (id, data) => http.post(`/payments/${id}/transfer`, data)
export const fetchCurrencies = (params = {}) => http.get('/currencies', { params })
export const fetchAvailability = (params = {}) => http.get('/availability', { params })
export const checkAvailability = (params = {}) => http.get('/availability/check', { params })
export const fetchVacantRooms = (params = {}) => http.get('/rooms/vacant', { params })
export const autoAssignRoom = (bookingId, roomId, params = {}) => http.post(`/bookings/${bookingId}/rooms/${roomId}/auto-assign`, null, { params })
export const fetchFOServicesList = () => http.get('/booking-services/fo-list')
export const fetchBookingRoomServices = (roomId) => http.get(`/booking-rooms/${roomId}/services`)
export const createBookingRoomService = (roomId, data) => http.post(`/booking-rooms/${roomId}/services`, data)
export const deleteBookingRoomServicesBulk = (roomId, data) => http.delete(`/booking-rooms/${roomId}/services/bulk`, { data })
export const fetchHotelServices = (params = {}) => http.get('/hotel-services', { params })

// ==================== CHILD BREAKFAST ====================
export const fetchBookingChildren = (bookingId, params) => http.get(`/bookings/${bookingId}/children`, { params })
export const updateChildBreakfastDetail = (childId, detailId, data) => http.patch(`/booking-children/${childId}/breakfast-details/${detailId}`, data)

// ==================== SPECIAL REQUESTS (YÊU CẦU ĐẶC BIỆT) ====================
export const fetchSpecialRequestsCatalog = () => http.get('/special-requests')
export const createSpecialRequestMaster = (data) => http.post('/special-requests', data)
export const deleteSpecialRequestMaster = (id) => http.delete(`/special-requests/${id}`)
export const fetchBookingRoomSpecialRequests = (roomId) => http.get(`/booking-rooms/${roomId}/special-requests`)
export const syncBookingRoomSpecialRequests = (roomId, data) => http.post(`/booking-rooms/${roomId}/special-requests/sync`, data)

// ==================== GUESTS (THÔNG TIN KHÁCH) ====================
export const fetchBookingGuests = (bookingId) => http.get(`/bookings/${bookingId}/guests`)
export const initBookingGuests = (bookingId) => http.post(`/bookings/${bookingId}/init-guests`)
export const updateBookingRoomGuest = (roomId, guestId, data) => http.put(`/booking-rooms/${roomId}/guests/${guestId}`, data)
export const updateBookingChild = (childId, data) => http.put(`/booking-children/${childId}`, data)
export const bulkUpdateBookingGuests = (bookingId, data) => http.post(`/bookings/${bookingId}/bulk-update-guests`, data)

// Room-level guest operations (dùng cho Room Map modal)
export const fetchRoomGuests = (roomId) => http.get(`/booking-rooms/${roomId}/guests`)
export const addRoomGuest = (roomId, data) => http.post(`/booking-rooms/${roomId}/guests`, data)
export const addBookingChild = (bookingId, data) => http.post(`/bookings/${bookingId}/children`, data)
export const removeBookingChild = (bookingId, childId) => http.delete(`/bookings/${bookingId}/children/${childId}`)
export const removeRoomGuest = (roomId, guestId) => http.delete(`/booking-rooms/${roomId}/guests/${guestId}`)
export const uploadGuestAvatar = (guestId, formData) => http.post(`/guests/${guestId}/avatar`, formData, {
  headers: {
    'Content-Type': 'multipart/form-data'
  }
})
export const fetchCancelReasons = () => http.get('/cancel-reasons')

