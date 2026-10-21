// Row 395: overlapping stays on the same physical room must be visible on the
// room plan. Every bar in a room row used to share one position, so a second
// booking on the same nights hid the first one completely.

const DAY_MS = 86400000

function addDays(ymd, days) {
  const [y, m, d] = String(ymd).split('-').map(Number)
  const date = new Date(Date.UTC(y, m - 1, d) + days * DAY_MS)
  return date.toISOString().slice(0, 10)
}

/**
 * Days a bar holds the room as a half-open [start, end) range of YYYY-MM-DD.
 * - Overnight stay: [arrival, departure).
 * - Day-use (arrival = departure, isDayUse): its calendar day.
 * - Same-day row without day-use: holds nothing (no AV, no room).
 * - OOO/OOS lock: [start, end] inclusive, unless it ends before the lock
 *   cut-off time on a later day (then the end day is free).
 */
export function roomPlanStayRange({ start, end, isLock = false, isDayUse = false, lockEndedEarly = false }) {
  if (!start || !end) return null
  if (isLock) {
    return { start, end: lockEndedEarly ? end : addDays(end, 1) }
  }
  if (start === end) {
    return isDayUse ? { start, end: addDays(start, 1) } : null
  }
  return start < end ? { start, end } : null
}

function rangesOverlap(a, b) {
  return a.start < b.end && b.start < a.end
}

function timeToMinutes(value) {
  const match = String(value || '').trim().match(/^(\d{1,2}):(\d{2})(?::\d{2})?$/)
  if (!match) return null

  const hours = Number(match[1])
  const minutes = Number(match[2])
  if (hours < 0 || hours > 24 || minutes < 0 || minutes > 59) return null
  if (hours === 24) return minutes === 0 ? 1440 : null

  return hours * 60 + minutes
}

function dayUseTimesOverlap(a, b) {
  if (!a.isDayUse || !b.isDayUse) return true

  const aArrival = timeToMinutes(a.arrival_time ?? a.arrivalTime)
  const aDeparture = timeToMinutes(a.departure_time ?? a.departureTime)
  const bArrival = timeToMinutes(b.arrival_time ?? b.arrivalTime)
  const bDeparture = timeToMinutes(b.departure_time ?? b.departureTime)

  // Thiếu/sai giờ phải giữ cảnh báo hiện hữu, không coi là khoảng trống an toàn.
  if (aArrival === null || aDeparture === null || bArrival === null || bDeparture === null) return true
  if (aDeparture <= aArrival || bDeparture <= bArrival) return true

  return aArrival < bDeparture && bArrival < aDeparture
}

function itemsOverlap(a, b) {
  if (!rangesOverlap(a._range, b._range)) return false
  return dayUseTimesOverlap(a, b)
}

function conflictLabel(item) {
  if (item.code === 'LOCK' || item.type === 'OOO' || item.type === 'OOS') {
    return `Khóa phòng ${item.type || ''}`.trim()
  }
  return item.code || item.bookingCode || item.name || 'Đăng ký khác'
}

/**
 * Marks bars of ONE room row that overlap another bar of the same row.
 * Each conflicting bar gets:
 *   hasConflict, conflictLane (0-based), conflictLaneCount, conflictWith[]
 * Lanes are assigned per group of mutually connected overlaps so the bars
 * can be drawn stacked instead of on top of each other.
 * Items must carry `_range` from roomPlanStayRange (null = ignored).
 */
export function markRoomPlanConflicts(items) {
  const list = (items || []).filter(item => item && item._range)
  for (const item of items || []) {
    if (!item) continue
    item.hasConflict = false
    item.conflictLane = 0
    item.conflictLaneCount = 1
    item.conflictWith = []
  }

  const neighbours = new Map(list.map(item => [item, []]))
  for (let i = 0; i < list.length; i++) {
    for (let j = i + 1; j < list.length; j++) {
      const a = list[i]
      const b = list[j]
      // Two render entries of the same stay row are not a conflict.
      if (a.bookingRoomId && a.bookingRoomId === b.bookingRoomId) continue
      if (itemsOverlap(a, b)) {
        neighbours.get(a).push(b)
        neighbours.get(b).push(a)
      }
    }
  }

  const visited = new Set()
  for (const item of list) {
    if (visited.has(item) || neighbours.get(item).length === 0) continue
    // Collect the connected group of overlapping bars.
    const group = []
    const stack = [item]
    visited.add(item)
    while (stack.length) {
      const current = stack.pop()
      group.push(current)
      for (const next of neighbours.get(current)) {
        if (!visited.has(next)) {
          visited.add(next)
          stack.push(next)
        }
      }
    }

    // Xếp làn theo chính khoảng thời gian thực tế để Day Use liền giờ dùng lại
    // cùng một làn, còn các khoảng giao nhau vẫn không che nhau.
    group.sort((a, b) => a._range.start.localeCompare(b._range.start) || String(a.arrival_time ?? a.arrivalTime ?? '').localeCompare(String(b.arrival_time ?? b.arrivalTime ?? '')) || String(a.bookingRoomId || '').localeCompare(String(b.bookingRoomId || '')))
    const lanes = []
    for (const bar of group) {
      let lane = lanes.findIndex(laneItems => laneItems.every(item => !itemsOverlap(item, bar)))
      if (lane === -1) {
        lane = lanes.length
        lanes.push([bar])
      } else {
        lanes[lane].push(bar)
      }
      bar.conflictLane = lane
    }
    for (const bar of group) {
      bar.hasConflict = true
      bar.conflictLaneCount = lanes.length
      bar.conflictWith = neighbours.get(bar).map(conflictLabel)
    }
  }

  return items
}
