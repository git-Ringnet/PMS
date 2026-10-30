import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { formatGuestSuggestion, inheritGuestInfo } from '../src/utils/guest-suggestion.js'

// Dòng 282 (note 23/10): gợi ý khách ở màn hình Thông tin khách trong phòng / Thông tin khách.
const read = (path) => readFileSync(new URL(path, import.meta.url), 'utf8')

test('row 282: dòng gợi ý hiển thị giá phòng mới nhất, không hiển thị doanh thu bill cũ', () => {
  const text = formatGuestSuggestion({
    full_name: 'NGUYEN VAN A', dob: '1990-05-20', id_number: '079090000001',
    stay_count: 2, last_room_rate: 950000, total_revenue: 1500000,
  }, 'NGUYEN')
  assert.match(text, /950\.000 VND/)
  assert.doesNotMatch(text, /1\.500\.000/)
  assert.match(text, /20\/05\/1990/)
  assert.match(text, /2 BK/)
  assert.match(text, /<span class="text-orange-600 font-extrabold">NGUYEN<\/span>/)

  // Khách chưa có giá phòng thì không hiện tiền
  assert.doesNotMatch(formatGuestSuggestion({ full_name: 'B', last_room_rate: null }, ''), /VND/)
})

test('row 282: tên khách được escape trước khi đưa vào v-html', () => {
  const text = formatGuestSuggestion({ full_name: '<img src=x onerror=alert(1)>' }, '')
  assert.doesNotMatch(text, /<img/)
  assert.match(text, /&lt;img/)
})

test('row 282: kế thừa thông tin khách cũ, không thay khách đang nhập', () => {
  const row = { id: 77, booking_room_id: 'G01', full_name: 'NGUYEN', phone: '0901', email: 'cu@x.vn', id_number: '' }
  inheritGuestInfo(row, {
    id: 5, full_name: 'NGUYEN VAN A', phone: '0909', email: '', id_number: '079090000001',
    dob: '1990-05-20T00:00:00.000000Z',
  })
  assert.equal(row.id, 77)
  assert.equal(row.booking_room_id, 'G01')
  assert.equal(row.full_name, 'NGUYEN VAN A')
  assert.equal(row.phone, '0909')
  assert.equal(row.email, 'cu@x.vn')
  assert.equal(row.id_number, '079090000001')
  assert.equal(row.dob, '1990-05-20')
})

test('row 282: gợi ý ở ô Họ tên / Số giấy tờ của cả hai màn hình thông tin khách', () => {
  const info = read('../src/pages/reservation/components/GuestInfoModal.vue')
  assert.match(info, /<GuestSuggestInput\s+v-if="col\.key === 'full_name' \|\| col\.key === 'id_number'"/)
  assert.match(info, /@select="selectGuestSuggestion\(guest, \$event\)"/)

  const detail = read('../src/pages/reservation/components/GuestDetailModal.vue')
  assert.match(detail, /<GuestSuggestInput v-model="form\.full_name"[^>]*@select="selectGuestSuggestion"/)
  assert.match(detail, /<GuestSuggestInput v-model="form\.id_number"[^>]*@select="selectGuestSuggestion"/)

  const modal = read('../src/components/BookingDetailModal.vue')
  assert.match(modal, /g\.last_room_rate/)
  assert.doesNotMatch(modal, /formatNumber\(g\.total_revenue\)/)
})
