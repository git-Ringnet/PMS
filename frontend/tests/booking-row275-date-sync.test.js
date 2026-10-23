import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { isEditableAllocationRoom } from '../src/utils/booking-room-allocations.js'

const source = readFileSync(new URL('../src/pages/reservation/CreateRegistrationPage.vue', import.meta.url), 'utf8')

for (const handler of ['handleMainDateChange', 'handleMainNightsChange']) {
  test(`275: ${handler} leaves rooms unchanged when synchronization is disabled`, async () => {
    const tab = { checkIn: '2026-08-30', checkOut: '2026-09-06', nights: 7,
      rooms: [{ bookingRoomStatus: 0, checkIn: '2026-08-31', checkOut: '2026-09-04', nights: 4 }] }
    const before = structuredClone(tab.rooms)
    let warnings = 0
    const start = source.indexOf(`async function ${handler}()`)
    const end = source.indexOf('\nasync function ', start + 1)
    await new Function('activeTab', 'isSyncRoomDateEnabled', 'warnSyncRoomDateDisabled',
      `${source.slice(start, end)}; return ${handler}()`)(
      { value: tab }, { value: false }, () => { warnings++ },
    )
    assert.equal(warnings, 1)
    assert.deepEqual(tab.rooms, before)
  })

  test(`275: ${handler} updates reserved departure under a past booking without changing arrival`, async () => {
    const tab = {
      checkIn: '2026-08-30', checkOut: '2026-09-06', nights: 7,
      roomAllocations: [{ arrivalDate: '2026-08-31', departureDate: '2026-09-04', nights: 4 }],
      rooms: [
        { bookingRoomId: 'old', bookingRoomStatus: 1, checkIn: '2026-08-30', checkOut: '2026-09-01', nights: 2 },
        { bookingRoomId: 'new', bookingRoomStatus: 0, checkIn: '2026-08-31', checkOut: '2026-09-04', nights: 4, price: 500000 },
      ],
    }
    const start = source.indexOf(`async function ${handler}()`)
    const end = source.indexOf('\nasync function ', start + 1)
    assert.ok(start >= 0 && end > start)
    const bindings = {
      activeTab: { value: tab }, systemDate: { value: '2026-08-31' },
      isSyncRoomDateEnabled: { value: true }, isEditableAllocationRoom,
      warnSyncRoomDateDisabled: () => assert.fail('Synchronization must be enabled'),
      parseApiDate: () => '2026-10-23', applyRateCodeDailyPricesToRoom: () => {}, updateRoomAvailability: async () => {},
    }
    await new Function(...Object.keys(bindings), `${source.slice(start, end)}; return ${handler}()`)(...Object.values(bindings))
    assert.equal(tab.rooms[1].checkIn, '2026-08-31')
    assert.equal(tab.rooms[1].checkOut, '2026-09-06')
    assert.equal(tab.rooms[1].nights, 6)
    assert.equal(tab.rooms[0].checkOut, '2026-09-01')
    assert.equal(tab.roomAllocations[0].arrivalDate, '2026-08-31')
    assert.equal(tab.roomAllocations[0].departureDate, '2026-09-06')
    assert.equal(tab.roomAllocations[0].nights, 6)
  })
}
