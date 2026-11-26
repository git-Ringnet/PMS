import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { assignedApplicationCodes, applicationChoices } from '../src/utils/position-applications.js'

const apps = [{ code: 'PMS', name: 'PROVISTA PMS' }, { code: 'POS', name: 'PROVISTA POS' }, { code: 'SYS', name: 'PROVISTA SYSTEM' }]
const position = { branch_roles: [{ application_code: 'PMS', system_branch_id: 1 }, { application_code: 'pms', system_branch_id: 2 }] }

test('dòng 18: Thêm ứng dụng chỉ liệt kê ứng dụng vị trí chưa có', () => {
  assert.deepEqual(assignedApplicationCodes(position), ['PMS'])
  assert.deepEqual(applicationChoices(apps, position, 'add').map(a => a.code), ['POS', 'SYS'])
})

test('dòng 18: Sửa ứng dụng chỉ đúng ứng dụng đang sửa', () => {
  assert.deepEqual(applicationChoices(apps, position, 'edit', 'PMS').map(a => a.code), ['PMS'])
})

test('dòng 18: vị trí chưa gán gì thì Thêm ứng dụng thấy đủ danh sách', () => {
  assert.deepEqual(applicationChoices(apps, { branch_roles: [] }, 'add').map(a => a.code), ['PMS', 'POS', 'SYS'])
})

test('dòng 18: nút Thêm ứng dụng mở popup chế độ thêm, không mở sẵn PMS', () => {
  const vue = readFileSync(new URL('../src/pages/system/components/OrgStructureTab.vue', import.meta.url), 'utf8')
  assert.doesNotMatch(vue, /openApplication\('PMS'\)/)
  assert.match(vue, /openAddApplication/)
  assert.match(vue, /applicationChoices\(/)
})
