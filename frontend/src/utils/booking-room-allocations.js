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
