import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

const source = readFileSync(new URL('../src/pages/reservation/components/SystemSearchModal.vue', import.meta.url), 'utf8')

// Dòng 463: chọn booking trong kết quả xong thì nội dung tìm kiếm phải được xóa.
test('row 463: selecting a booking clears the search text', () => {
  const body = source.match(/function selectBooking\(booking\) \{([\s\S]*?)\n\}/)[1]
  assert.match(body, /emit\('update:query', ''\)/)
  assert.match(body, /emit\('select-booking', booking\)/)
})

// Dòng 463: nút bật "Xem theo ngày đến" bị lệch vì chấm tròn định vị theo cả label thay vì theo rãnh.
test('row 463: the arrival-date toggle knob is positioned against its own track', () => {
  const label = source.match(/<!-- TOGGLE SWITCH XEM THEO NGÀY ĐẾN -->\s*<label class="([^"]*)"/)[1]
  assert.doesNotMatch(label, /\brelative\b/)
  const track = source.match(/<div class="([^"]*\bw-8 h-4[^"]*)"><\/div>/)[1]
  assert.match(track, /\brelative\b/)
  assert.match(track, /\bshrink-0\b/)
})

// Dòng 463: chọn ngày đến thì tự tải lại, không cần bấm Lưu ở Tình trạng.
test('row 463: picking a date searches immediately', () => {
  assert.match(source, /function applyArrivalRange[\s\S]*?executeGlobalSearch\(\)/)
  assert.match(source, /@change="handleFromDateChange"/)
  assert.match(source, /@change="handleToDateChange"/)
})
