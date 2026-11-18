import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

const read = (path) => readFileSync(new URL(path, import.meta.url), 'utf8').replace(/\r\n/g, '\n')

// Dòng 153 (17/11): đổi trạng thái phòng bị backend chặn (403 không thuộc Rule mở khóa) nhưng service nuốt lỗi,
// trả {success:true} giả => Sơ đồ phòng báo "thành công" dù thông số RoleUserUnlockRoomOOO/OOS đã chặn.
test('row 153: updateRoomStatus rethrows server rejections instead of faking success', () => {
  const source = read('../src/services/room-service.js')
  const fn = source.match(/async updateRoomStatus\([\s\S]*?\n  \},\n/)[0]
  const catchBlock = fn.match(/catch \(error\) \{([\s\S]*?)\n    \}\n  \},/)[1]
  assert.match(catchBlock, /if \(error\.response\) throw error/)
})

test('row 153: a rejected status change does not replace the whole room map with the load-error panel', () => {
  const store = read('../src/stores/room-store.js')
  const fn = store.match(/async function updateRoomStatus\([\s\S]*?\n  \}\n/)[0]
  assert.doesNotMatch(fn, /error\.value\s*=\s*'/)
  assert.match(fn, /throw err/)
})

test('row 153: the room detail modal shows the server message when the change is rejected', () => {
  const modal = read('../src/components/RoomDetailModal.vue')
  assert.match(modal, /err\.response\?\.data\?\.message \|\| 'Không thể cập nhật trạng thái phòng/)
})
