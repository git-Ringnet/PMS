import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

const source = readFileSync(new URL('../src/pages/system/components/OrgStructureTab.vue', import.meta.url), 'utf8')
const service = readFileSync(new URL('../src/services/company-service.js', import.meta.url), 'utf8')

// Dòng 469: bộ phận có Sửa (chỉ tên) và Xóa; vị trí chỉ sửa tên, mã bị khóa.
test('row 469: department edit only sends the name and delete calls the API', () => {
  assert.match(service, /deleteOrganizationDepartment = \(id\) => http\.delete\(`\/organization\/departments\/\$\{id\}`\)/)
  assert.match(source, /updateOrganizationDepartment\(departmentForm\.value\.id, \{ name: departmentForm\.value\.name \}\)/)
  assert.match(source, /data-testid="edit-department"/)
  assert.match(source, /data-testid="delete-department"/)
})

test('row 469: department code and position code are read-only while editing', () => {
  assert.match(source, /v-model="departmentForm\.code"[\s\S]*?:readonly="!!departmentForm\.id"/)
  assert.match(source, /v-model="positionForm\.code"[\s\S]*?:readonly="!!positionForm\.id"/)
  assert.match(source, /updatePosition\(positionForm\.value\.id, \{ name: positionForm\.value\.name \}\)/)
})

test('row 469: deleting a department that still has positions is refused with the notice', () => {
  assert.match(source, /Bộ phận đã có vị trí công việc, không thể xóa\./)
})
