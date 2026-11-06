import test from 'node:test'
import assert from 'node:assert/strict'
import { isCheckedOutPerson, splitCheckedOutPeople } from '../src/utils/guest-info.js'

test('row 474: nhận diện khách/trẻ em đã check out', () => {
  assert.equal(isCheckedOutPerson({ stay_status: 2 }), true)
  assert.equal(isCheckedOutPerson({ child_status: 2 }), true)
  assert.equal(isCheckedOutPerson({ stay_status: 1 }), false)
  assert.equal(isCheckedOutPerson(null), false)
})

test('row 474: khách đã trả phòng tách khỏi nhóm phòng đang ở', () => {
  const rooms = [{
    booking_room_id: 'G1', status: 1,
    guests: [{ id: 'a', stay_status: 1 }, { id: 'b', stay_status: 1 }, { id: 'c', stay_status: 2 }],
    children: [{ id: 'k', child_status: 2 }],
  }]

  const out = splitCheckedOutPeople(rooms)

  assert.equal(out.length, 2)
  assert.deepEqual(out[0].guests.map(g => g.id), ['a', 'b'])
  assert.deepEqual(out[0].children, [])
  assert.equal(out[0].status, 1)
  assert.deepEqual(out[1].guests.map(g => g.id), ['c'])
  assert.deepEqual(out[1].children.map(c => c.id), ['k'])
  assert.equal(out[1].status, 2)
})

test('row 474: phòng chỉ còn khách đã trả không sinh nhóm đang ở rỗng; phòng đã out giữ nguyên', () => {
  const onlyOut = splitCheckedOutPeople([{ booking_room_id: 'G2', status: 1, guests: [{ id: 'x', stay_status: 2 }], children: [] }])
  assert.equal(onlyOut.length, 1)
  assert.equal(onlyOut[0].status, 2)

  const whole = { booking_room_id: 'G3', status: 2, guests: [{ id: 'y', stay_status: 2 }], children: [] }
  assert.deepEqual(splitCheckedOutPeople([whole]), [whole])
})
