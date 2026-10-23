import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { billBelongsToCurrentRoom, serviceBillCurrentGuestId, isMasterBillRecord } from '../src/utils/service-bill-ownership.js'

const modal = readFileSync(new URL('../src/components/BookingDetailModal.vue', import.meta.url), 'utf8')
const firstNightCountSource = modal.slice(modal.indexOf('function firstNightCount('), modal.indexOf('function formatDateForInput('))
const firstNightCount = new Function(`${firstNightCountSource}; return firstNightCount`)()

test('183: room modal keeps numeric/string zero instead of defaulting to one', () => {
  assert.equal(firstNightCount(0, 0, 1), 0)
  assert.equal(firstNightCount('0', 0, 1), 0)
  assert.equal(firstNightCount(undefined, 0, 1), 0)
  assert.equal(firstNightCount(null, '', 3), 3)
  assert.equal(firstNightCount(undefined, null, 1), 1)
})

test('183: registration room mapping keeps zero nights after a same-day move', () => {
  const source = readFileSync(new URL('../src/pages/reservation/CreateRegistrationPage.vue', import.meta.url), 'utf8')
  const start = source.indexOf('      let nightsCount = 1', source.indexOf('function bookingToTab(b)'))
  const end = source.indexOf('      const priceNum', start)
  assert.ok(start > 0 && end > start)
  const mapNights = new Function('br', 'b', `${source.slice(start, end)}; return nightsCount`)
  assert.equal(mapNights({ arrival_date: '2026-07-14', departure_date: '2026-07-14', status: 100, ActutalNumOfDays: 0, NumOfDays: 0 }, {}), 0)
  assert.equal(mapNights({ arrival_date: '2026-07-14', departure_date: '2026-07-17', status: 1 }, {}), 3)
  assert.equal(mapNights({ arrival_date: '2026-07-10', departure_date: '2026-07-14', status: 100 }, {}), 4)
})

test('166: moved minibar is absent from source and remains assigned to the moved guest', () => {
  const bill = { Ma: 1, RegisterId1: 4, RentalRoomId1: '104', CustomerId1: 'A', RegisterID2: 5, RentalRoomId2: '112', CustomerId2: 'A' }
  assert.equal(billBelongsToCurrentRoom(bill, '104'), false)
  assert.equal(billBelongsToCurrentRoom(bill, '112'), true)
  assert.equal(serviceBillCurrentGuestId(bill), 'A')
})

test('166: master room charge stays in source booking while target legacy bill keeps its owner', () => {
  const roomCharge = { RegisterId1: 4, RentalRoomId1: '104', CustomerId1: 'A', RegisterID2: 4, RentalRoomId2: null, CustomerId2: null }
  assert.equal(isMasterBillRecord(roomCharge, { id: 4 }), true)
  assert.equal(billBelongsToCurrentRoom(roomCharge, '104'), false)
  assert.equal(billBelongsToCurrentRoom(roomCharge, '112'), false)
  const targetBill = { RentalRoomId1: '112', CustomerId1: 'B', RegisterID2: null, RentalRoomId2: null, CustomerId2: null }
  assert.equal(billBelongsToCurrentRoom(targetBill, '112'), true)
  assert.equal(serviceBillCurrentGuestId(targetBill), 'B')
})
