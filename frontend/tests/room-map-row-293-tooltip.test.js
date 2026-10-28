import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

// Dòng 293 (note 27/10): thông tin nhanh của booking trên Room map.
const source = readFileSync(new URL('../src/pages/reservation/RoomMapPage.vue', import.meta.url), 'utf8')
const start = source.indexOf('<!-- Mode 2: Booking thông thường')
const end = source.indexOf('<!-- Back-to-back notification -->', start)
const bookingTooltip = source.slice(start, end)

test('row 293: tooltip block is found', () => {
  assert.ok(start > 0 && end > start)
})

test('row 293: company comes first, then special requests, then note', () => {
  const company = bookingTooltip.indexOf('hoverTooltip.room.company_name')
  const specialRequests = bookingTooltip.indexOf('getListSpecialRequests(hoverTooltip.room)')
  const note = bookingTooltip.indexOf('hoverTooltip.room.booking_note')
  assert.ok(company > 0 && specialRequests > 0 && note > 0)
  assert.ok(company < specialRequests, 'Tên công ty phải nằm trên Yêu cầu đặc biệt')
  assert.ok(specialRequests < note, 'Yêu cầu đặc biệt phải nằm trên Ghi chú')
})

test('row 293: titles and content use the FE text colour, not custom colours or tiny fonts', () => {
  assert.doesNotMatch(bookingTooltip, /text-(rose|amber|sky|slate)-(6|7|8|9)00/)
  assert.doesNotMatch(bookingTooltip, /text-white/)
  assert.doesNotMatch(bookingTooltip, /text-\[(10|11)px\]/)
  assert.match(source, /'bg-white text-\[#000000D9\] text-xs border border-slate-300'/)
})
