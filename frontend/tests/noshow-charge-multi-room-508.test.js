import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import {
  noshowNightlyRate,
  noshowRoomsTotal,
  noshowDescription,
} from '../src/utils/noshow-charge.js'

const modalSource = readFileSync(new URL('../src/pages/reservation/components/ChargeNoshowModal.vue', import.meta.url), 'utf8')

test('row 508 (note 26/11): Tiền phòng tự động là tiền 1 đêm của phòng, không phải tổng các đêm', () => {
  assert.equal(noshowNightlyRate('2026-09-05', 850000, null), 850000)
  // Có giá thiết lập riêng cho đêm đầu khoảng chọn thì lấy giá đó
  assert.equal(noshowNightlyRate('2026-09-05', 850000, { '2026-09-05': 700000 }), 700000)
  assert.equal(noshowNightlyRate('', 850000, null), 850000)
})

test('row 508 (note 26/11): Tổng tiền = tổng tiền từng đêm của tất cả phòng đã chọn', () => {
  const rooms = [
    { roomRate: 850000, dailyRoomPrices: null },
    { roomRate: 850000, dailyRoomPrices: { '2026-09-06': 600000 } },
  ]
  assert.equal(noshowRoomsTotal(rooms, '2026-09-05', '2026-09-06'), 850000 * 2 + 850000 + 600000)
  assert.equal(noshowRoomsTotal(rooms.slice(0, 1), '2026-09-05', '2026-09-05'), 850000)
})

test('row 540/543: mô tả bill noshow = "Dịch vụ phòng nghỉ noshow <số phòng>"', () => {
  assert.equal(noshowDescription('106'), 'Dịch vụ phòng nghỉ noshow 106')
  assert.equal(noshowDescription('106', 'Room charge'), 'Room charge noshow 106')
  assert.equal(noshowDescription(''), 'Dịch vụ phòng nghỉ noshow')
})

test('row 508 (note 26/11): ô Tiền phòng tự động hiển thị tiền 1 đêm, ô Tổng tiền hiển thị tổng', () => {
  assert.match(modalSource, /Tiền phòng tự động<\/label>\s*<input[\s\S]*?:value="nightlyRateText"/)
  assert.match(modalSource, /Tổng tiền<\/label>[\s\S]*?:value="autoRateText"/)
})

test('row 508 (note 26/11): modal nhận nhiều phòng và charge cho từng phòng', () => {
  assert.match(modalSource, /rooms:\s*\{\s*type:\s*Array/)
  assert.match(modalSource, /for \(const room of targetRooms\.value\)/)
})
