import assert from 'node:assert/strict'
import { test } from 'node:test'
import { housekeepingStatistics } from './room-map-housekeeping-stats.js'

test('ready, clean and dirty are separated by status code and occupancy', () => {
  const stats = housekeepingStatistics([
    { room_status_code: 'vacant_ready' },
    { room_status_code: 'vacant_clean' },
    { room_status_code: 'vacant_dirty', is_clean: false },
    { room_status_code: 'vacant_ready', booking_status: 'reserved' },
    { room_status_code: 'occupied_ready', booking_status: 'occupied' },
    { room_status_code: 'occupied_clean', booking_status: 'occupied' },
    { room_status_code: 'occupied_dirty', booking_status: 'checkout', is_clean: false },
    { room_status_code: 'ooo' },
    { room_status_code: 'vacant_ready', lock_type: 'OOS' },
  ])

  assert.deepEqual(stats, {
    occupiedReady: 1,
    vacantReady: 2,
    occupiedClean: 1,
    vacantClean: 1,
    occupiedDirty: 1,
    vacantDirty: 1,
  })
})

test('every saleable room is counted exactly once', () => {
  const rooms = [
    { room_status_code: 'vacant_clean' },
    { room_status_code: 'occupied_clean' },
    { room_status_code: 'turndown', booking_status: 'occupied' },
    { room_status_code: 'oos' },
  ]
  const total = Object.values(housekeepingStatistics(rooms)).reduce((sum, value) => sum + value, 0)
  assert.equal(total, 3)
})
