export function buildRoomPlanSelectionRectangle({
  rooms = [],
  dayKeys = [],
  startRoomIndex,
  endRoomIndex,
  startDayIndex,
  endDayIndex,
  selectionGroup,
  isSelectable = () => true,
} = {}) {
  const minRoomIndex = Math.min(startRoomIndex, endRoomIndex)
  const maxRoomIndex = Math.max(startRoomIndex, endRoomIndex)
  const minDayIndex = Math.min(startDayIndex, endDayIndex)
  const maxDayIndex = Math.max(startDayIndex, endDayIndex)
  const cells = []

  for (let roomIndex = minRoomIndex; roomIndex <= maxRoomIndex; roomIndex++) {
    const room = rooms[roomIndex]
    if (!room || room.isVirtual) return { valid: false, cells: [] }

    for (let dayIndex = minDayIndex; dayIndex <= maxDayIndex; dayIndex++) {
      const date = dayKeys[dayIndex]
      if (!date || !isSelectable(room, date)) return { valid: false, cells: [] }
      cells.push({ room: room.room, date, dayIdx: dayIndex, selectionGroup })
    }
  }

  return { valid: true, cells }
}

export function replaceRoomPlanSelectionGroup(selectedCells = [], selectionGroup, cells = []) {
  return [
    ...selectedCells.filter(cell => cell.selectionGroup !== selectionGroup),
    ...cells,
  ]
}

export function getRoomPlanSelectionGroupBounds(selectedCells = [], selectionGroup, dayKeys = []) {
  const cells = selectedCells.filter(cell => cell.selectionGroup === selectionGroup)
  const roomNumbers = [...new Set(cells.map(cell => cell.room))]
  const dayIndexes = cells
    .map(cell => Number.isInteger(cell.dayIdx) ? cell.dayIdx : dayKeys.indexOf(cell.date))
    .filter(index => index >= 0)

  if (roomNumbers.length === 0 || dayIndexes.length === 0) return null

  return {
    roomNumbers,
    startDayIndex: Math.min(...dayIndexes),
    endDayIndex: Math.max(...dayIndexes),
  }
}
