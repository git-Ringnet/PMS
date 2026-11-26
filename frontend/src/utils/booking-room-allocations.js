import { standardCheckInTime, standardCheckOutTime } from './standard-stay-times.js'

// Room rows with these statuses are part of the current allocation contract.
// Cancelled, no-show, and moved rows remain visible as history, but never
// contribute to the quantity that creates or restores reservation rooms.
export const ROOM_ALLOCATION_ACTIVE_STATUSES = Object.freeze([0, 1, 2])
export const ROOM_ALLOCATION_HISTORY_STATUSES = Object.freeze([3, 4, 100])
export const ROOM_ALLOCATION_PROTECTED_STATUSES = Object.freeze([1, 2])

function roomStatus(roomOrStatus) {
  if (roomOrStatus && typeof roomOrStatus === 'object') {
    return Number(roomOrStatus.bookingRoomStatus ?? roomOrStatus.status ?? 0)
  }
  return Number(roomOrStatus ?? 0)
}

export function hasBookingRoomId(room) {
  const id = room?.bookingRoomId
  return id !== undefined && id !== null && String(id).trim() !== ''
}

export function isRoomAllocationCandidate(room) {
  // A row without a server id is a draft created by the quantity control.
  // Persisted history is identified by status and is intentionally excluded.
  return !hasBookingRoomId(room) || ROOM_ALLOCATION_ACTIVE_STATUSES.includes(roomStatus(room))
}

export function isRoomAllocationHistory(room) {
  return hasBookingRoomId(room) && ROOM_ALLOCATION_HISTORY_STATUSES.includes(roomStatus(room))
}

export function isEditableAllocationRoom(room) {
  return isRoomAllocationCandidate(room)
    && (!hasBookingRoomId(room) || roomStatus(room) === 0)
}

export function sameRoomClass(room, roomClassId) {
  return String(room?.roomClassId ?? '') === String(roomClassId ?? '')
}

export function countAllocatableRooms(rooms, roomClassId) {
  return (Array.isArray(rooms) ? rooms : [])
    .filter(room => sameRoomClass(room, roomClassId) && isRoomAllocationCandidate(room))
    .length
}

export function countProtectedAllocationRooms(rooms, roomClassId) {
  return (Array.isArray(rooms) ? rooms : [])
    .filter(room => sameRoomClass(room, roomClassId)
      && hasBookingRoomId(room)
      && ROOM_ALLOCATION_PROTECTED_STATUSES.includes(roomStatus(room)))
    .length
}

export function normalizeAllocationQuantity(value) {
  const number = Number(value)
  if (!Number.isFinite(number) || number <= 0) return 0
  return Math.floor(number)
}

export function allocationCandidateRooms(rooms, roomClassId) {
  return (Array.isArray(rooms) ? rooms : [])
    .filter(room => sameRoomClass(room, roomClassId) && isRoomAllocationCandidate(room))
}

// Dòng 514: phòng noshow không nằm trong phân bổ (không chiếm AV) nhưng vẫn phải sửa được
// để khách đến trễ chỉnh ngày đến rồi khôi phục. Gửi riêng để server cập nhật tại chỗ.
export function isNoshowRoom(room) {
  return hasBookingRoomId(room) && roomStatus(room) === 4
}

export function buildNoshowRoomEdits(rooms) {
  return (Array.isArray(rooms) ? rooms : []).filter(isNoshowRoom).map(room => ({
    bookingRoomId: room.bookingRoomId,
    arrivalDate: room.checkIn,
    departureDate: room.checkOut,
    roomNumber: room.roomNumber || '',
    price: Number(room.price) || 0,
    adults: Number(room.adults) || 0,
    babies: Number(room.babies) || 0,
    children: Number(room.children) || 0,
    guestName: room.guestName || '',
    rateCode: room.rateCode && room.rateCode !== 'Vui lòng chọn giá phòng' ? room.rateCode : null,
    breakfast: !!room.breakfast,
    extraBedQty: Number(room.extraBedQty) || 0,
    extraBedPrice: Number(room.extraBedPrice) || 0,
    arrivalTime: room.arrivalTime || standardCheckInTime.value,
    hoursOut: room.hoursOut || standardCheckOutTime.value,
  }))
}
