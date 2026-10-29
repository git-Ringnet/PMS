import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

// Dòng 253 (note 26/10): màn hình Thông tin và màn hình Sang ngày với phòng ở theo giờ.
const read = (path) => readFileSync(new URL(path, import.meta.url), 'utf8')

test('row 253: màn hình Thông tin khóa ngày đi khi bật Phòng theo giờ', () => {
  const modal = read('../src/components/BookingDetailModal.vue')
  assert.match(modal, /v-model="stayInfo\.departure_date"[\s\S]{0,120}:disabled="!isEditingMode \|\| stayInfo\.hourly"/)
  assert.match(modal, /const isHourlyRoom = !!\(newRoom\.is_day_use/)
})

test('row 253: Sang ngày tính số đêm theo ngày đến/đi của phòng, day use = 0 đêm', () => {
  const page = read('../src/pages/frontdesk/DayClosePage.vue')
  assert.match(page, /nights: calculateNights\(arrDate, depDate\),/)
  assert.doesNotMatch(page, /nights: r\.num_of_days \|\| b\.num_of_days/)

  // Chạy đúng hàm calculateNights trong trang
  const source = page.match(/function getNormalizedDate[\s\S]*?\n}\n/)?.[0]
    + page.match(/function calculateNights[\s\S]*?\n}\n/)[0]
  const calculateNights = new Function(`${source}; return calculateNights`)()
  assert.equal(calculateNights('2026-08-09', '2026-08-09'), 0)
  assert.equal(calculateNights('2026-08-09', '2026-08-12'), 3)
  // Ở 2 đêm, gia hạn thêm 1 đêm -> 3 đêm; chỉnh ngày đi từ 4 đêm còn 2 đêm -> 2 đêm
  assert.equal(calculateNights('2026-08-07', '2026-08-10'), 3)
  assert.equal(calculateNights('2026-08-08', '2026-08-10'), 2)
  // API /bookings trả ngày dạng UTC (00:00 giờ VN = 17:00Z hôm trước)
  assert.equal(calculateNights('2026-08-08T17:00:00.000000Z', '2026-08-08T17:00:00.000000Z'), 0)
  assert.equal(calculateNights('2026-08-07T17:00:00.000000Z', '2026-08-10T17:00:00.000000Z'), 3)
})

test('row 253: tab Ở theo giờ nhận cả phòng day use theo cờ của từng phòng', () => {
  const page = read('../src/pages/frontdesk/DayClosePage.vue')
  assert.match(page, /const isSameDay = arrDate === depDate \|\| r\.is_day_use/)
})
