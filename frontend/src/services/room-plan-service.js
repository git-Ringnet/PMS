import http from './http'

export const updateRoomPlanBookingRoomStay = (bookingId, roomId, data) =>
  http.put(`/bookings/${bookingId}/rooms/${roomId}/room-plan-stay`, data)

// Kept internal while legacy Room Plan selection is handled entirely before save.
export const bulkUpdateRoomPlanBookingRoomStays = (bookingId, data) =>
  http.put(`/bookings/${bookingId}/rooms/room-plan-stay/bulk`, data)
