import assert from 'node:assert/strict'
import test from 'node:test'
import {
  childBreakfastServiceDates,
  hotelServiceDisplayName,
  isChildBreakfastCode,
  isServiceDateInStay,
  normalizeChildBreakfastConfig,
  sortCancelledRooms,
} from '../src/utils/booking-room-services.js'

test('S5: only services inside [arrival, departure) are shown after shortening the stay', () => {
  // Booking 31/08 -> 04/09 shortened to 02/09: services 31/08 and 01/09 remain visible.
  assert.equal(isServiceDateInStay('2026-08-31', '2026-08-31', '2026-09-02'), true)
  assert.equal(isServiceDateInStay('2026-09-01', '2026-08-31', '2026-09-02'), true)
  assert.equal(isServiceDateInStay('2026-09-02', '2026-08-31', '2026-09-02'), false)
  assert.equal(isServiceDateInStay('2026-09-03', '2026-08-31', '2026-09-02'), false)
})

test('S5: moving the arrival date later hides earlier services, extension shows them again', () => {
  assert.equal(isServiceDateInStay('2026-08-31', '2026-09-01', '2026-09-04'), false)
  assert.equal(isServiceDateInStay('2026-09-03', '2026-09-01', '2026-09-04'), true)
  assert.equal(isServiceDateInStay('2026-09-03', '2026-09-01', '2026-09-05 00:00:00'), true)
})

test('S5: a day-use room keeps services of its arrival date only', () => {
  assert.equal(isServiceDateInStay('2026-09-01', '2026-09-01', '2026-09-01', true), true)
  assert.equal(isServiceDateInStay('2026-09-02', '2026-09-01', '2026-09-01', true), false)
})

test('S3: detail mode is the default; service mode only when the backend says so', () => {
  assert.deepEqual(normalizeChildBreakfastConfig(null), { serviceCode: 'BD', serviceName: '', detailMode: true })
  assert.equal(normalizeChildBreakfastConfig({ service_code: 'KC', detail_mode: false }).detailMode, false)
  assert.equal(isChildBreakfastCode('bd', { service_code: 'BD' }), true)
  assert.equal(isChildBreakfastCode('KC', { service_code: 'BD' }), false)
})

test('S3: service-mode rows take priority over breakfast details on the same date', () => {
  const services = [
    { service_code: 'KC', service_date: '2026-09-02' },
    { service_code: 'TR', service_date: '2026-09-03' },
  ]
  assert.deepEqual([...childBreakfastServiceDates(services, { service_code: 'KC', detail_mode: false })], ['2026-09-02'])
  assert.equal(childBreakfastServiceDates(services, { service_code: 'BD', detail_mode: true }).size, 0)
})

test('S6/S11: service description comes from the hotel service definition', () => {
  const hotelServices = [{ code: 'KC', name: 'Kid Surcharge/ Phụ thu trẻ em' }, { code: 'RM', name: 'Dịch vụ phòng nghỉ' }]
  assert.equal(hotelServiceDisplayName('kc', hotelServices, 'Phụ thu trẻ em - Phòng 306'), 'Kid Surcharge/ Phụ thu trẻ em')
  assert.equal(hotelServiceDisplayName('ZZ', hotelServices, 'Fallback'), 'Fallback')
})

test('S9: cancelled rooms are sorted by the latest cancellation by default', () => {
  const rows = [
    { id: 'A', room_number: '0812', cancelled_at: '2026-10-08 16:40:00' },
    { id: 'B', room_number: '1205', cancelled_at: '2026-10-10 09:12:00' },
    { id: 'C', room_number: '', cancelled_at: null },
    { id: 'D', room_number: '1206', cancelled_at: '2026-10-10 09:12:30' },
  ]
  assert.deepEqual(sortCancelledRooms(rows).map(row => row.id), ['D', 'B', 'A', 'C'])
  assert.deepEqual(sortCancelledRooms(rows, 'room_number', 'asc').map(row => row.id), ['A', 'B', 'D', 'C'])
})
