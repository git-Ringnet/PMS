import assert from 'node:assert/strict'
import test from 'node:test'

import {
  GUEST_INFO_ROOM_STATUSES,
  normalizeGuestInfoGroups,
} from '../src/utils/guest-info.js'

test('guest information normalization keeps only room statuses 0, 1, 2 and 4', () => {
  const groups = normalizeGuestInfoGroups([
    { booking_room_id: 'G0', status: 0, guests: null, children: null },
    { booking_room_id: 'G1', status: 1, guests: [{ id: 'K1' }], children: [{ age_group: 'baby' }] },
    { booking_room_id: 'G2', status: 2, guests: [], children: [{ age_group: 'child' }] },
    { booking_room_id: 'G3', status: 3, guests: [{ id: 'cancelled' }] },
    { booking_room_id: 'G4', status: 4, guests: [], children: [] },
    { booking_room_id: 'G100', status: 100, guests: [{ id: 'moved' }] },
  ])

  assert.deepEqual(groups.map(group => group.booking_room_id), ['G0', 'G1', 'G2', 'G4'])
  assert.deepEqual(groups.map(group => group.guests.length), [0, 1, 0, 0])
  assert.deepEqual(groups.map(group => group.children.length), [0, 1, 1, 0])
  assert.equal(groups[1].babies_count, 1)
  assert.equal(groups[2].children_count, 1)
  assert.deepEqual([...GUEST_INFO_ROOM_STATUSES], [0, 1, 2, 4])
})
