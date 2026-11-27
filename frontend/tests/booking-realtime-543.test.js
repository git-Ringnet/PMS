import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { notifyBookingsUpdated } from '../src/utils/booking-update-broadcast.js'

const read = path => readFileSync(new URL(path, import.meta.url), 'utf8')

function fakeChannel() {
  const sent = []
  class FakeChannel {
    constructor(name) { this.name = name; sent.push({ open: name }) }
    postMessage(message) { sent.push(message) }
    close() { sent.push('closed') }
  }
  return { FakeChannel, sent }
}

test('row 543: báo các booking đã thay đổi qua kênh pms-room-updates, mỗi booking một lần', () => {
  const { FakeChannel, sent } = fakeChannel()
  assert.equal(notifyBookingsUpdated([12, 12, 15, null, ''], FakeChannel), 2)
  assert.deepEqual(sent[0], { open: 'pms-room-updates' })
  const bookingMessages = sent.filter(item => item?.type === 'booking-updated')
  assert.deepEqual(bookingMessages.map(item => item.bookingId), [12, 15])
  assert.ok(sent.includes('rooms-updated'))
  assert.equal(sent.at(-1), 'closed')
})

test('row 543: không có BroadcastChannel hoặc không có booking thì không gửi gì', () => {
  assert.equal(notifyBookingsUpdated([1], null), 0)
  const { FakeChannel, sent } = fakeChannel()
  assert.equal(notifyBookingsUpdated([], FakeChannel), 0)
  assert.equal(sent.length, 0)
})

test('row 543: Sang ngày báo booking đã thay đổi sau khi noshow / late check-in', () => {
  const source = read('../src/pages/frontdesk/DayClosePage.vue')
  assert.match(source, /bookingId: b\.id,/)
  assert.match(source, /late-check-in'[\s\S]*?notifyBookingsUpdated\(selected\.map\(item => item\.bookingId\)\)/)
  assert.match(source, /night-audit\/no-show'[\s\S]*?notifyBookingsUpdated\(selected\.map\(item => item\.bookingId\)\)/)
})

test('row 543: màn Đăng ký nhận sự kiện reservation.updated (máy khác) thì tải lại booking đang mở', () => {
  const source = read('../src/pages/reservation/CreateRegistrationPage.vue')
  assert.match(source, /listen\('\.reservation\.updated', handleReservationUpdatedEcho\)/)
  assert.match(source, /stopListening\('\.reservation\.updated', handleReservationUpdatedEcho\)/)
  assert.match(source, /function handleReservationUpdatedEcho\(event\) \{[\s\S]*?refreshMatchingBooking\(/)
})
