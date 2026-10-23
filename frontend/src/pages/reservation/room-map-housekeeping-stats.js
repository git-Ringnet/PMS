// Bảng "Buồng phòng" của Thống kê room map: Occ/Vac theo việc phòng có khách ở hay không,
// trạng thái sẵn sàng/sạch/dơ theo room_status_code. Phòng OOO/OOS không tính.
const DIRTY_CODES = ['vacant_dirty', 'occupied_dirty']
const CLEAN_CODES = ['vacant_clean', 'occupied_clean']
const LOCKED_CODES = ['ooo', 'oos', 'occupied_ooo']

export const isLockedRoom = room => LOCKED_CODES.includes(room.room_status_code)
  || ['OOO', 'OOS'].includes(room.lock_type)

export const isInHouseRoom = room => ['occupied', 'checkout'].includes(room.booking_status)

export function housekeepingState(room) {
  if (DIRTY_CODES.includes(room.room_status_code) || room.status === 'dirty' || room.is_clean === false) return 'dirty'
  if (CLEAN_CODES.includes(room.room_status_code)) return 'clean'
  return 'ready'
}

export function housekeepingStatistics(rooms = []) {
  const counts = {
    occupiedReady: 0,
    vacantReady: 0,
    occupiedClean: 0,
    vacantClean: 0,
    occupiedDirty: 0,
    vacantDirty: 0,
  }
  for (const room of rooms) {
    if (isLockedRoom(room)) continue
    const side = isInHouseRoom(room) ? 'occupied' : 'vacant'
    const state = housekeepingState(room)
    counts[`${side}${state[0].toUpperCase()}${state.slice(1)}`] += 1
  }
  return counts
}
