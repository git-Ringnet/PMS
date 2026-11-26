import test from 'node:test'
import assert from 'node:assert/strict'
import { displayNightCount } from '../src/utils/day-use-nights.js'

const room = over => ({ checkIn: '2026-09-17', checkOut: '2026-09-17', hourly: false, nights: 0, lateCheckinCharged: false, ...over })

test('row 358: phòng ở theo giờ bình thường hiện 1 đêm day-use', () => {
  assert.equal(displayNightCount(room({ hourly: true })), 1)
})

test('row 358: phòng late check-in CÓ charge bật Ở theo giờ không hiện thêm đêm', () => {
  assert.equal(displayNightCount(room({ hourly: true, lateCheckinCharged: true })), 0)
})

test('row 358: phòng cùng ngày không ở theo giờ không có đêm', () => {
  assert.equal(displayNightCount(room({ hourly: false })), 0)
})

test('row 358: phòng nhiều đêm giữ nguyên số đêm, kể cả đã late check-in có charge', () => {
  assert.equal(displayNightCount(room({ checkOut: '2026-09-20', nights: 3, lateCheckinCharged: true })), 3)
})

test('row 358: số đêm không hợp lệ thì lấy 1 đêm như trước', () => {
  assert.equal(displayNightCount(room({ checkOut: '2026-09-20', nights: undefined })), 1)
  assert.equal(displayNightCount(room({ checkOut: '2026-09-20', nights: -2 })), 0)
})
