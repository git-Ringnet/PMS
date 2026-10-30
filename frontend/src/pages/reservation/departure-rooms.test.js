import assert from 'node:assert/strict'
import { test } from 'node:test'
import { isPendingDepartureRoom } from './departure-rooms.js'

const day = '2026-09-04'

test('dòng 392: phòng đang ở có ngày đi <= ngày hệ thống là phòng chưa trả', () => {
  assert.equal(isPendingDepartureRoom({ status: 1, arrival_date: '2026-09-02', departure_date: '2026-09-04' }, day), true)
  assert.equal(isPendingDepartureRoom({ status: 1, arrival_date: '2026-09-01', departure_date: '2026-09-03' }, day), true)
  assert.equal(isPendingDepartureRoom({ status: 1, arrival_date: '2026-09-02', departure_date: '2026-09-05' }, day), false)
})

test('dòng 392: phòng chưa nhận có ngày đến = ngày đi được tính, dù không tick Day Use (807, 205, 405)', () => {
  assert.equal(isPendingDepartureRoom({ status: 0, arrival_date: day, departure_date: day, is_day_use: 1 }, day), true)
  assert.equal(isPendingDepartureRoom({ status: 0, arrival_date: day, departure_date: day, is_day_use: 0 }, day), true)
  assert.equal(isPendingDepartureRoom({ status: 0, arrival_date: `${day}T00:00:00.000000Z`, departure_date: `${day} 00:00:00` }, day), true)
})

test('dòng 392: phòng chưa nhận ở qua đêm và phòng đã trả không thuộc danh sách phòng chưa trả', () => {
  assert.equal(isPendingDepartureRoom({ status: 0, arrival_date: day, departure_date: '2026-09-06' }, day), false)
  assert.equal(isPendingDepartureRoom({ status: 2, arrival_date: '2026-09-02', departure_date: day }, day), false)
})
