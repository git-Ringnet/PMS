import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { normalizeArrivalRange } from '../src/utils/arrival-date-range.js'
import { applySavedRoomColumns, serializeRoomColumns } from '../src/utils/booking-room-columns.js'

// ---------------------------------------------------------------- Checklist dòng 95
// Tìm booking toàn hệ thống, bật "Xem theo ngày đến": chọn ngày khác ngày hệ thống phải tìm ngay đúng khoảng ngày đã chọn.

test('row 95: picking a from-date after the to-date moves the to-date too, before searching', () => {
  assert.deepEqual(normalizeArrivalRange('2026-09-07', '2026-09-05', 'from'), { from: '2026-09-07', to: '2026-09-07' })
})

test('row 95: picking a to-date before the from-date moves the from-date too', () => {
  assert.deepEqual(normalizeArrivalRange('2026-09-05', '2026-09-03', 'to'), { from: '2026-09-03', to: '2026-09-03' })
})

test('row 95: a valid range is left untouched', () => {
  assert.deepEqual(normalizeArrivalRange('2026-09-04', '2026-09-06', 'from'), { from: '2026-09-04', to: '2026-09-06' })
  assert.deepEqual(normalizeArrivalRange('2026-09-04', '2026-09-06', 'to'), { from: '2026-09-04', to: '2026-09-06' })
})

test('row 95: same day range and cleared dates are left untouched', () => {
  assert.deepEqual(normalizeArrivalRange('2026-09-05', '2026-09-05', 'from'), { from: '2026-09-05', to: '2026-09-05' })
  assert.deepEqual(normalizeArrivalRange('', '2026-09-05', 'from'), { from: '', to: '2026-09-05' })
  assert.deepEqual(normalizeArrivalRange('2026-09-05', '', 'to'), { from: '2026-09-05', to: '' })
})

test('row 95: the search modal normalizes the range inside the date change handlers', () => {
  const source = readFileSync(new URL('../src/pages/reservation/components/SystemSearchModal.vue', import.meta.url), 'utf8')
  assert.match(source, /@change="handleFromDateChange"/)
  assert.match(source, /@change="handleToDateChange"/)
  assert.doesNotMatch(source, /v-model="searchFromDate"\s+@change="executeGlobalSearch"/)
})

// ---------------------------------------------------------------- Checklist dòng 98 (FIX FE task 109)
// Bảng "Chi tiết phòng booking": thứ tự + ẩn/hiện cột phải giữ theo user sau khi tải lại trang.

const defaults = [
  { key: 'type', label: 'Loại Phòng', visible: true },
  { key: 'shape', label: 'Dạng Phòng', visible: true },
  { key: 'roomNumber', label: 'Số Phòng', visible: true },
  { key: 'isPreassigned', label: 'Đặt Trước', visible: false },
]

test('row 98: serialize keeps the column order and visibility', () => {
  const columns = [defaults[2], defaults[0], { ...defaults[1], visible: false }, defaults[3]]
  assert.deepEqual(serializeRoomColumns(columns), {
    order: ['roomNumber', 'type', 'shape', 'isPreassigned'],
    visible: { roomNumber: true, type: true, shape: false, isPreassigned: false },
  })
})

test('row 98: saved order and visibility are applied on top of the defaults', () => {
  const result = applySavedRoomColumns(defaults, ['roomNumber', 'type', 'shape', 'isPreassigned'], { shape: false, isPreassigned: true })
  assert.deepEqual(result.map(c => c.key), ['roomNumber', 'type', 'shape', 'isPreassigned'])
  assert.deepEqual(result.map(c => c.visible), [true, true, false, true])
  assert.equal(result[0].label, 'Số Phòng')
})

test('row 98: columns added after the user saved are appended and unknown saved keys are ignored', () => {
  const result = applySavedRoomColumns(defaults, ['roomNumber', 'oldRemovedColumn', 'type'], { oldRemovedColumn: true })
  assert.deepEqual(result.map(c => c.key), ['roomNumber', 'type', 'shape', 'isPreassigned'])
  assert.equal(result.find(c => c.key === 'isPreassigned').visible, false)
})

test('row 98: without saved settings the defaults are returned unchanged and not mutated', () => {
  assert.deepEqual(applySavedRoomColumns(defaults, null, null).map(c => c.key), ['type', 'shape', 'roomNumber', 'isPreassigned'])
  const out = applySavedRoomColumns(defaults, ['shape'], { shape: false })
  out[0].visible = true
  assert.equal(defaults[1].visible, true)
})

test('row 98: the registration page restores and saves the booking room columns per user', () => {
  const source = readFileSync(new URL('../src/pages/reservation/CreateRegistrationPage.vue', import.meta.url), 'utf8')
  assert.match(source, /applySavedRoomColumns\(/)
  assert.match(source, /serializeRoomColumns\(/)
  assert.match(source, /create_registration_detail/)
})
