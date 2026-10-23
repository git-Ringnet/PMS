/**
 * Room Plan must use the same persisted housekeeping status as Room Map.
 * Legacy fallback only applies when the API does not provide room_status_code.
 */
export function resolveRoomPlanStatusIcon(room, statusIconMap = {}) {
  if (!room) return null

  const code = room.room_status_code
  if (code && Object.prototype.hasOwnProperty.call(statusIconMap, code)) {
    return statusIconMap[code]
  }

  if (room.lock_type === 'OOS') return 'oos'
  if (room.lock_type === 'OOO') return 'ooo'
  if (room.status === 'dirty') return 'dirty'
  if (room.status === 'checkout') return 'checkout'
  if (room.status === 'maintenance') return 'housekeeping-service'

  return null
}
