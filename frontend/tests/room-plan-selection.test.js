import test from 'node:test'
import assert from 'node:assert/strict'

import {
  buildRoomPlanSelectionRectangle,
  getRoomPlanSelectionGroupBounds,
  replaceRoomPlanSelectionGroup,
} from '../src/utils/room-plan-selection.js'

const rooms = [{ room: '101' }, { room: '102' }, { room: '103' }]
const days = ['2026-10-21', '2026-10-22', '2026-10-23']

test('Dòng 246: Ctrl kéo nhiều phòng tạo một giai đoạn giống nhau cho từng phòng', () => {
  const result = buildRoomPlanSelectionRectangle({
    rooms,
    dayKeys: days,
    startRoomIndex: 0,
    endRoomIndex: 2,
    startDayIndex: 0,
    endDayIndex: 2,
    selectionGroup: 8,
  })

  assert.equal(result.valid, true)
  assert.equal(result.cells.length, 9)
  assert.deepEqual(getRoomPlanSelectionGroupBounds(result.cells, 8, days), {
    roomNumbers: ['101', '102', '103'],
    startDayIndex: 0,
    endDayIndex: 2,
  })
})

test('Dòng 246: không áp dụng một phần khi một ô trong vùng đã có booking', () => {
  const result = buildRoomPlanSelectionRectangle({
    rooms,
    dayKeys: days,
    startRoomIndex: 0,
    endRoomIndex: 2,
    startDayIndex: 0,
    endDayIndex: 2,
    selectionGroup: 8,
    isSelectable: (room, date) => !(room.room === '102' && date === '2026-10-22'),
  })

  assert.deepEqual(result, { valid: false, cells: [] })
})

test('Dòng 246: kéo biên thay thế cả nhóm, không làm mất vùng chọn khác', () => {
  const retained = [{ room: '201', date: '2026-10-21', dayIdx: 0, selectionGroup: 1 }]
  const nextGroup = buildRoomPlanSelectionRectangle({
    rooms: rooms.slice(0, 2),
    dayKeys: days,
    startRoomIndex: 0,
    endRoomIndex: 1,
    startDayIndex: 0,
    endDayIndex: 1,
    selectionGroup: 8,
  }).cells
  const replaced = replaceRoomPlanSelectionGroup([...retained, { room: '101', date: '2026-10-21', dayIdx: 0, selectionGroup: 8 }], 8, nextGroup)

  assert.equal(replaced.filter(cell => cell.selectionGroup === 1).length, 1)
  assert.equal(replaced.filter(cell => cell.selectionGroup === 8).length, 4)
})
