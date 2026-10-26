import test from 'node:test'
import assert from 'node:assert/strict'

import { isRoomPlanVirtualRoomVisible } from '../src/utils/room-plan-virtual-room-visibility.js'

test('Dòng 340: ShowVirtualRoom mặc định hiển thị để giữ nguyên Room Plan hiện có', () => {
  assert.equal(isRoomPlanVirtualRoomVisible(undefined), true)
  assert.equal(isRoomPlanVirtualRoomVisible('1'), true)
  assert.equal(isRoomPlanVirtualRoomVisible(1), true)
})

test('Dòng 340: ShowVirtualRoom = 0 chỉ tắt render dòng phòng chưa gán', () => {
  assert.equal(isRoomPlanVirtualRoomVisible('0'), false)
  assert.equal(isRoomPlanVirtualRoomVisible(0), false)
})
