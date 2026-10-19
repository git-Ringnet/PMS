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
      if (rangesOverlap(a._range, b._range)) {
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

    // Greedy lane assignment by arrival date.
    group.sort((a, b) => a._range.start.localeCompare(b._range.start) || String(a.bookingRoomId || '').localeCompare(String(b.bookingRoomId || '')))
    const laneEnds = []
    for (const bar of group) {
      let lane = laneEnds.findIndex(end => end <= bar._range.start)
      if (lane === -1) {
        lane = laneEnds.length
        laneEnds.push(bar._range.end)
      } else {
        laneEnds[lane] = bar._range.end
      }
      bar.conflictLane = lane
    }
    for (const bar of group) {
      bar.hasConflict = true
      bar.conflictLaneCount = laneEnds.length
      bar.conflictWith = neighbours.get(bar).map(conflictLabel)
    }
  }

  return items
}
