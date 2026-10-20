import assert from 'node:assert/strict'
import test from 'node:test'
import { readFileSync } from 'node:fs'
import { bookingPeriodAfterQuickUpdate, isRegistrationReadonly } from '../src/utils/booking-quick-update.js'

const room = (checkIn, checkOut, status = 0) => ({ checkIn, checkOut, status })

// ---------- Dòng 432 ----------

test('432: 1 phòng 01 -> 10, cập nhật ngày đi 08 thì BK đi 08', () => {
  const period = bookingPeriodAfterQuickUpdate({
    headerIn: '2026-11-01', headerOut: '2026-11-10',
    rooms: [room('2026-11-01', '2026-11-08')],
    departureEdited: true,
  })
  assert.deepEqual(period, { checkIn: '2026-11-01', checkOut: '2026-11-08', nights: 7 })
})

test('432: nhiều phòng thì BK đi theo ngày đi lớn nhất, bỏ qua phòng hủy', () => {
  const period = bookingPeriodAfterQuickUpdate({
    headerIn: '2026-11-01', headerOut: '2026-11-12',
    rooms: [
      room('2026-11-01', '2026-11-05'),
      room('2026-11-01', '2026-11-07'),
      room('2026-11-01', '2026-11-12', 3),
    ],
    departureEdited: true,
  })
  assert.deepEqual(period, { checkIn: '2026-11-01', checkOut: '2026-11-07', nights: 6 })
})

test('432: phòng đã checkout/noshow vẫn được tính', () => {
  const period = bookingPeriodAfterQuickUpdate({
    headerIn: '2026-11-01', headerOut: '2026-11-10',
    rooms: [room('2026-11-01', '2026-11-04'), room('2026-11-01', '2026-11-06', 2), room('2026-11-01', '2026-11-05', 4)],
    departureEdited: true,
  })
  assert.equal(period.checkOut, '2026-11-06')
})

test('432: kéo dài ngày đi phòng thì BK kéo dài theo', () => {
  const period = bookingPeriodAfterQuickUpdate({
    headerIn: '2026-11-01', headerOut: '2026-11-10',
    rooms: [room('2026-11-01', '2026-11-13')],
    departureEdited: true,
  })
  assert.deepEqual(period, { checkIn: '2026-11-01', checkOut: '2026-11-13', nights: 12 })
})

test('393: không đổi ngày đi thì giai đoạn BK chỉ mở rộng, không co lại', () => {
  const period = bookingPeriodAfterQuickUpdate({
    headerIn: '2026-11-01', headerOut: '2026-11-10',
    rooms: [room('2026-11-02', '2026-11-08')],
    departureEdited: false,
  })
  assert.deepEqual(period, { checkIn: '2026-11-01', checkOut: '2026-11-10', nights: 9 })
})

test('432: tất cả phòng đã hủy thì giữ ngày BK', () => {
  const period = bookingPeriodAfterQuickUpdate({
    headerIn: '2026-11-01', headerOut: '2026-11-10',
    rooms: [room('2026-11-01', '2026-11-05', 3)],
    departureEdited: true,
  })
  assert.equal(period.checkOut, '2026-11-10')
})

test('không có phòng thì không tính', () => {
  assert.equal(bookingPeriodAfterQuickUpdate({ headerIn: '2026-11-01', headerOut: '2026-11-10', rooms: [] }), null)
})

// ---------- Dòng 433 ----------

test('433: chỉ BK đã hủy (status 3) mở ở chế độ chỉ xem', () => {
  assert.equal(isRegistrationReadonly(3), true)
  assert.equal(isRegistrationReadonly('3'), true)
  for (const status of [0, 1, 2, 4, null, undefined]) {
    assert.equal(isRegistrationReadonly(status), false)
  }
})

test('433: modal thông tin đăng ký không chặn BK đã hủy và khóa sửa khi chỉ xem', () => {
  const src = readFileSync(new URL('../src/pages/reservation/CreateRegistrationPage.vue', import.meta.url), 'utf8')
  const openEdit = src.slice(src.indexOf('async function openEditModal()'), src.indexOf('async function saveCheckoutMetadata()'))
  assert.ok(!openEdit.includes('Không thể chỉnh sửa đăng ký đã xóa'), 'openEditModal không còn chặn BK đã hủy')
  assert.match(src, /async function handleSaveNewBooking\(\) \{\s*if \(isModalReadonly\.value\) return/)
  assert.ok(src.includes('v-if="!isModalReadonly"\n                    @click="handleSaveNewBooking"'), 'ẩn nút Lưu khi chỉ xem')
  assert.equal((src.match(/:inert="isModalReadonly \|\| null"/g) || []).length, 2, 'khóa thanh thông tin chung và nội dung modal')
})
