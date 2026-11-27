import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { moduleAccessState, setModuleAccess } from '../src/utils/role-module-access.js'

const perms = () => [
  { code: 'hk.room.view', action: 'view', granted: false },
  { code: 'hk.room.edit', action: 'edit', granted: false },
  { code: 'hk.minibar.view', action: 'view', granted: true },
]

test('dòng 21: trạng thái truy cập phân hệ theo quyền xem các màn hình', () => {
  assert.equal(moduleAccessState(perms()), 'some')
  assert.equal(moduleAccessState(perms().map(p => ({ ...p, granted: p.action === 'view' }))), 'all')
  assert.equal(moduleAccessState(perms().map(p => ({ ...p, granted: false }))), 'none')
})

test('dòng 21: tích phân hệ = được xem mọi màn hình, không tự cấp thêm/sửa/xóa', () => {
  const list = perms()
  setModuleAccess(list, true)
  assert.deepEqual(list.map(p => p.granted), [true, false, true])
})

test('dòng 21: bỏ tích phân hệ = không còn vào phân hệ (bỏ cả quyền thao tác)', () => {
  const list = perms().map(p => ({ ...p, granted: true }))
  setModuleAccess(list, false)
  assert.deepEqual(list.map(p => p.granted), [false, false, false])
})

test('dòng 21: mọi phân hệ trong ma trận quyền đều có ô chọn truy cập phân hệ', () => {
  const vue = readFileSync(new URL('../src/pages/system/components/RoleManageTab.vue', import.meta.url), 'utf8')
  assert.doesNotMatch(vue, /<label v-if="module === 'REPORT'"/)
  assert.match(vue, /data-testid="module-access-toggle"/)
})
