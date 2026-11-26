import test from 'node:test'
import assert from 'node:assert/strict'

import {
  splitNoshowRows,
  noshowRowKey,
  noshowSelectionError,
  noshowChargeModalProps,
} from '../src/utils/noshow-room-list.js'

const BY_DATE = { type: 'late_checkin', bookingId: 7, roomId: 'G1', roomNumber: '102', bookingName: 'Đoàn A', guestName: 'Đoàn A', noshowDateIso: '2026-11-24', arrivalDateIso: '2026-11-25', departureDateIso: '2026-11-26', rate: 540000, charged: false }
const BY_STAGE = { type: 'noshow', bookingId: 8, roomId: 'G2', roomNumber: '103', bookingName: 'Đoàn B', noshowDateIso: '2026-11-24', arrivalDateIso: '2026-11-24', departureDateIso: '2026-11-27', rate: 500000, charged: false }

test('Dòng 532: danh sách chia 2 nhóm noshow by date (one day) và noshow by stage', () => {
  const { byDate, byStage } = splitNoshowRows([BY_STAGE, BY_DATE, { ...BY_DATE, roomId: 'G3' }])
  assert.deepEqual(byDate.map(r => r.roomId), ['G1', 'G3'])
  assert.deepEqual(byStage.map(r => r.roomId), ['G2'])
})

test('Dòng 532: khóa dòng khác nhau cho cùng phòng khác ngày noshow', () => {
  assert.notEqual(noshowRowKey(BY_DATE), noshowRowKey({ ...BY_DATE, noshowDateIso: '2026-11-25' }))
  assert.notEqual(noshowRowKey(BY_DATE), noshowRowKey({ ...BY_DATE, type: 'noshow' }))
})

test('Dòng 532: phải chọn đúng 1 phòng để charge', () => {
  assert.match(noshowSelectionError([]), /chọn/)
  assert.match(noshowSelectionError([BY_DATE, BY_STAGE]), /1 phòng/)
  assert.equal(noshowSelectionError([BY_DATE]), '')
  assert.equal(noshowSelectionError([BY_STAGE]), '')
})

test('Dòng 532: noshow one day đã charge thì báo, không mở màn charge', () => {
  const message = noshowSelectionError([{ ...BY_DATE, charged: true }])
  assert.match(message, /24\/11\/2026/)
  assert.match(message, /đã/)
})

test('Dòng 532: màn charge mở với ngày noshow của dòng và giá phòng của dòng', () => {
  const props = noshowChargeModalProps(BY_DATE)
  assert.equal(props.bookingId, 7)
  assert.equal(props.roomId, 'G1')
  assert.equal(props.roomNumber, '102')
  assert.equal(props.defaultDate, '2026-11-24')
  assert.equal(props.roomRate, 540000)
  assert.equal(props.arrivalDate, '2026-11-25')
  assert.equal(props.departureDate, '2026-11-26')
})
