import http from './http'

export const updateRoomPlanBookingRoomStay = (bookingId, roomId, data) =>
  http.put(`/bookings/${bookingId}/rooms/${roomId}/room-plan-stay`, data)
