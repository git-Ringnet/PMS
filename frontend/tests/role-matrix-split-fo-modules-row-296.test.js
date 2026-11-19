import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { FO_SALES_MODULE, splitFrontOfficeModules } from '../src/utils/screen-permissions.js'

const perm = (screen_code, action = 'view') => ({ id: `${screen_code}.${action}`, screen_code, action, module: 'FO', granted: true })

test('TC_LT_22 dòng 296 (task 473): khối FO tách thành Đặt phòng (Sales) và Lễ tân, giữ nguyên thứ tự và dữ liệu', () => {
  const groups = {
    CONFIG: [{ id: 1, module: 'CONFIG', screen_code: 'config.hotel' }],
    FO: [perm('fo.booking'), perm('fo.deposit'), perm('fo.frontdesk'), perm('fo.payment'), perm('fo.company'), perm('fo.checkin_checkout')],
    HK: [{ id: 2, module: 'HK', screen_code: 'hk.overview' }],
  }
  const result = splitFrontOfficeModules(groups)

  assert.deepEqual(Object.keys(result), ['CONFIG', FO_SALES_MODULE, 'FO', 'HK'])
  assert.deepEqual(result[FO_SALES_MODULE].map(p => p.screen_code), ['fo.booking', 'fo.deposit', 'fo.company'])
  assert.deepEqual(result.FO.map(p => p.screen_code), ['fo.frontdesk', 'fo.payment', 'fo.checkin_checkout'])
  assert.ok(result[FO_SALES_MODULE].every(p => p.module === FO_SALES_MODULE))
  assert.equal(result.CONFIG, groups.CONFIG)
  // Số quyền không đổi: tách nhóm chỉ để hiển thị, không làm mất / trùng quyền khi lưu.
  assert.equal(result[FO_SALES_MODULE].length + result.FO.length, groups.FO.length)
})

test('không có khối FO hoặc không có màn Sales thì giữ nguyên', () => {
  const noFo = { HK: [{ id: 2 }] }
  assert.equal(splitFrontOfficeModules(noFo), noFo)
  const onlyReception = { FO: [perm('fo.frontdesk')] }
  assert.equal(splitFrontOfficeModules(onlyReception), onlyReception)
})

test('màn Vai trò & Phân quyền dùng hàm tách và nhãn module mới', () => {
  const source = readFileSync(new URL('../src/pages/system/components/RoleManageTab.vue', import.meta.url), 'utf8').replace(/\r\n/g, '\n')
  assert.match(source, /groups\.value = splitFrontOfficeModules\(/)
  assert.match(source, /\.\.\.PERMISSION_MODULE_LABELS/)
})

test('TC_LT_22 dòng 298 (task 471): ma trận quyền chỉ nhận kết quả của lần nạp mới nhất', () => {
  const source = readFileSync(new URL('../src/pages/system/components/RoleManageTab.vue', import.meta.url), 'utf8').replace(/\r\n/g, '\n')
  const block = source.slice(source.indexOf('async function loadMatrix'), source.indexOf('onMounted(loadBase)'))
  assert.match(source, /let matrixRequestId = 0/)
  assert.match(block, /const requestId = \+\+matrixRequestId/)
  assert.ok(block.indexOf('if (requestId !== matrixRequestId) return') > block.indexOf('await fetchBranchRoleMatrix'))
  assert.ok(block.indexOf('if (requestId !== matrixRequestId) return') < block.indexOf('groups.value = splitFrontOfficeModules'))
})
