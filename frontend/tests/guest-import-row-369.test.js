import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { applyImportedGuests } from '../src/utils/guest-import.js'

const groups = () => ([
  { room_number: '101', guests: [{ id: 1, full_name: 'Guest 1' }, { id: 2, full_name: 'Guest 2' }] },
  { room_number: '102', guests: [{ id: 3, full_name: 'Guest 1' }] },
])

test('row 369: rows with a room number fill the free guests of that room in order', () => {
  const g = groups()
  const { filled, unmatched } = applyImportedGuests(g, [
    { room_number: '101', full_name: 'NGUYEN VAN A', dob: '1990-05-15' },
    { room_number: '101', full_name: 'TRAN THI B' },
    { room_number: '102', full_name: 'LE VAN C' },
  ])
  assert.equal(filled.length, 3)
  assert.equal(unmatched.length, 0)
  assert.equal(g[0].guests[0].full_name, 'NGUYEN VAN A')
  assert.equal(g[0].guests[0].dob, '1990-05-15')
  assert.equal(g[0].guests[1].full_name, 'TRAN THI B')
  assert.equal(g[1].guests[0].full_name, 'LE VAN C')
})

test('row 369: rows without room number fill free guests across rooms; extra rows are reported', () => {
  const g = groups()
  const { filled, unmatched } = applyImportedGuests(g, [
    { full_name: 'A' }, { full_name: 'B' }, { full_name: 'C' }, { full_name: 'D' },
  ])
  assert.equal(filled.length, 3)
  assert.deepEqual(unmatched.map((r) => r.full_name), ['D'])
})

test('row 369: checked-out guests are never overwritten and empty cells keep old data', () => {
  const g = [{ room_number: '101', guests: [{ id: 1, full_name: 'OLD OUT', status: 2, checked_out_at: '2026-09-10' }, { id: 2, full_name: 'Guest 1', phone: '0900' }] }]
  const { filled } = applyImportedGuests(g, [{ room_number: '101', full_name: 'NEW', phone: '' }])
  assert.equal(filled.length, 1)
  assert.equal(g[0].guests[0].full_name, 'OLD OUT')
  assert.equal(g[0].guests[1].full_name, 'NEW')
  assert.equal(g[0].guests[1].phone, '0900')
})

test('row 369: guest info modal exposes template download and Excel import while editing', () => {
  const modal = readFileSync(new URL('../src/pages/reservation/components/GuestInfoModal.vue', import.meta.url), 'utf8')
  assert.match(modal, /data-testid="guest-import-btn"/)
  assert.match(modal, /data-testid="guest-import-template-btn"/)
  assert.match(modal, /applyImportedGuests/)
})

test('row 369: a room number not in the booking is reported with its own reason', () => {
  const g = groups()
  const { filled, unmatched } = applyImportedGuests(g, [{ room_number: '999', full_name: 'X' }])
  assert.equal(filled.length, 0)
  assert.equal(unmatched[0].reason, 'room_not_in_booking')
})

test('row 369: a guest who already has a real name is never overwritten', () => {
  const g = [{ room_number: '805', guests: [{ id: 1, full_name: 'NGUYEN NGOC ANH TAM' }] }]
  const { filled, unmatched } = applyImportedGuests(g, [{ room_number: '805', full_name: 'IMPORT' }])
  assert.equal(filled.length, 0)
  assert.equal(unmatched[0].reason, 'no_free_slot')
  assert.equal(g[0].guests[0].full_name, 'NGUYEN NGOC ANH TAM')
})
