import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

const read = path => readFileSync(new URL(path, import.meta.url), 'utf8')

test('row 540 (note 26/11): Room map – DS phòng đến (CheckInPage) loại đăng ký is_availability=0', () => {
  const source = read('../src/pages/reservation/CheckInPage.vue')
  assert.match(source, /fetchBookings\(\{[\s\S]*?affects_availability: true[\s\S]*?\}\)/)
})

test('row 540 (note 26/11): Sang ngày – phòng đến loại đăng ký is_availability=0', () => {
  const source = read('../src/pages/frontdesk/DayClosePage.vue')
  assert.match(source, /http\.get\('\/bookings', \{ params: \{ stay_only: true, affects_availability: true \} \}\)/)
})

test('row 540 (note 26/11): Kế hoạch phòng không cho kéo xếp phòng cho đăng ký is_availability=0', () => {
  const source = read('../src/pages/reservation/RoomPlanPage.vue')
  assert.match(source, /function handleDragStart\(bk, event\) \{[\s\S]*?bk\.isWithoutAvailability[\s\S]*?event\.preventDefault\(\)/)
  // Kéo thả thực tế đi qua pointer: chặn trước khi bắt đầu kéo
  assert.match(source, /function handleBookingPointerMove\(event\) \{[\s\S]*?if \(bk\.isWithoutAvailability\) \{[\s\S]*?stopBookingPointerDrag\(\)/)
  assert.match(source, /isWithoutAvailability: registrationStatus\?\.is_availability !== undefined/)
})
