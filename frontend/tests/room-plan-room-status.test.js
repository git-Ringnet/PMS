import test from 'node:test'
import assert from 'node:assert/strict'

import { resolveRoomPlanStatusIcon } from '../src/utils/room-plan-room-status.js'

const ROOM_STATUS_ICON_MAP = {
  vacant_ready: null,
  vacant_clean: 'clean',
  vacant_dirty: 'dirty',
}

test('Room Plan follows the persisted room status returned to Room Map', () => {
  assert.equal(resolveRoomPlanStatusIcon({ room_status_code: 'vacant_ready' }, ROOM_STATUS_ICON_MAP), null)
  assert.equal(resolveRoomPlanStatusIcon({ room_status_code: 'vacant_clean' }, ROOM_STATUS_ICON_MAP), 'clean')
  assert.equal(resolveRoomPlanStatusIcon({ room_status_code: 'vacant_dirty' }, ROOM_STATUS_ICON_MAP), 'dirty')
})

test('Room Plan does not let a new same-day booking replace a persisted inspection status', () => {
  assert.equal(resolveRoomPlanStatusIcon({
    room_status_code: 'vacant_clean',
    booking_status: 'reserved',
    booking_code: 'GAL9',
  }, ROOM_STATUS_ICON_MAP), 'clean')
})

test('legacy rooms keep the existing fallback status behavior', () => {
  assert.equal(resolveRoomPlanStatusIcon({ lock_type: 'OOS' }, ROOM_STATUS_ICON_MAP), 'oos')
  assert.equal(resolveRoomPlanStatusIcon({ status: 'dirty' }, ROOM_STATUS_ICON_MAP), 'dirty')
})
