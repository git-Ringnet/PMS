import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

// Dòng 414: cột Dạng phòng của booking lấy theo booking_rooms.RoomKind, không theo giá phòng chuẩn hiện tại.
const source = readFileSync(new URL('../src/pages/reservation/CreateRegistrationPage.vue', import.meta.url), 'utf8')

function extractShapeResolver() {
  const start = source.indexOf('        shape: (() => {', source.indexOf('function bookingToTab('))
  const end = source.indexOf('})(),', start)
  assert.ok(start > 0 && end > start, 'Không tìm thấy cách lấy dạng phòng của booking')
  const body = source.slice(start + '        shape: (() => {'.length, end)
  return (br, roomForms, roomClasses) => new Function('br', 'roomForms', 'roomClasses', 'rc', body)(
    br, { value: roomForms }, { value: roomClasses }, br.room_class || {},
  )
}

const resolveShape = extractShapeResolver()
const roomForms = [{ id: 1, name: 'Double' }, { id: 5, name: 'King' }]
// Giá phòng chuẩn đã đổi Suite sang dạng Double.
const roomClasses = [{ id: 9, code: 'JST', room_form_name: 'Double' }]

test('row 414: booked room keeps the room kind saved in booking_rooms', () => {
  assert.equal(resolveShape({ room_class_id: 9, RoomKind: 5 }, roomForms, roomClasses), 'King')
})

test('row 414: room without saved RoomKind falls back to the room class form', () => {
  assert.equal(resolveShape({ room_class_id: 9, RoomKind: null }, roomForms, roomClasses), 'Double')
})

test('row 414: data in the customer screenshot (Suite rooms, standard rate now Suite - Double)', () => {
  const forms = [
    { id: 1, name: 'Double' }, { id: 2, name: 'Twin' }, { id: 3, name: 'Triple' },
    { id: 4, name: 'Family' }, { id: 5, name: 'King' },
  ]
  const classes = [{ id: 9, code: 'JST', name: 'Suite', room_form_name: 'Double' }]
  const rooms = [
    { id: 'G0000055', room_class_id: 9, RoomKind: 5, expected: 'King' },
    { id: 'G0000108', room_class_id: 9, RoomKind: 5, expected: 'King' },
    { id: 'G0000109', room_class_id: 9, RoomKind: 4, expected: 'Family' },
    { id: 'G0000110', room_class_id: 9, RoomKind: null, expected: 'Double' },
    { id: 'G0000111', room_class_id: 9, RoomKind: 5, expected: 'King' },
  ]
  for (const room of rooms) {
    assert.equal(resolveShape(room, forms, classes), room.expected, room.id)
  }
})
