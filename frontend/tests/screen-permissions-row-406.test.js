import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import {
  hasAccess,
  canOpenScreen,
  filterMenuByPermission,
  ROUTE_PERMISSIONS,
} from '../src/utils/screen-permissions.js'

// Dòng 406: không có quyền thì không hiển thị module / màn hình / tab.
const read = (path) => readFileSync(new URL(path, import.meta.url), 'utf8')
const user = (permissions, extra = {}) => ({ isSuperAdmin: false, isAdmin: false, permissions, ...extra })

const receptionist = user(['fo.booking.view', 'fo.booking.create', 'fo.frontdesk.view', 'fo.checkin', 'fo.checkout', 'fo.payment.view'])

const pmsTiles = [
  { name: 'Đặt Phòng', route: '/reservation' },
  { name: 'Lễ Tân', route: '/frontdesk' },
  { name: 'Buồng Phòng', route: '/housekeeping' },
  { name: 'Báo Cáo Quản Lý', route: '/reports' },
  { name: 'Cấu Hình Hệ Thống', route: '/config' },
]

test('row 406: lễ tân không có quyền buồng phòng thì không thấy module Buồng Phòng', () => {
  const visible = filterMenuByPermission(pmsTiles, receptionist).map(item => item.name)
  assert.deepEqual(visible, ['Đặt Phòng', 'Lễ Tân'])
})

test('row 406: super admin thấy đủ module, user không quyền không thấy gì', () => {
  assert.equal(filterMenuByPermission(pmsTiles, user([], { isSuperAdmin: true })).length, 5)
  assert.equal(filterMenuByPermission(pmsTiles, user([])).length, 0)
})

test('row 406: menu con bị ẩn theo quyền và menu cha rỗng thì ẩn luôn', () => {
  const menu = [
    {
      name: 'Đăng Ký',
      route: '/reservation',
      dropdown: [
        { name: 'Tạo Đăng Ký', tab: 'create-res', permission: ['fo.booking.create'] },
        { name: 'Allotment', tab: 'allotment' },
      ],
    },
    {
      name: 'Khóa phòng',
      route: '/reservation',
      dropdown: [{ name: 'Khóa Phòng', tab: 'lock-room' }],
    },
  ]
  const visible = filterMenuByPermission(menu, receptionist)
  assert.equal(visible.length, 1)
  assert.deepEqual(visible[0].dropdown.map(i => i.name), ['Tạo Đăng Ký'])
})

test('row 406: không được xem báo cáo nào thì ẩn menu Báo cáo', () => {
  const reportMenu = (dropdown) => ({ name: 'Báo cáo', route: '/reservation?tab=reports', reportSlot: true, dropdown })
  assert.equal(filterMenuByPermission([reportMenu(undefined)], receptionist).length, 0)
  assert.equal(filterMenuByPermission([reportMenu([{ name: 'DS khách đến', route: '/reservation?tab=reports&report=A' }])], receptionist).length, 1)
})

test('row 406: tab của module kiểm tra theo quyền màn hình (gõ thẳng ?tab= cũng bị chặn)', () => {
  assert.equal(canOpenScreen(receptionist, '/reservation', { tab: 'room-map' }), true)
  assert.equal(canOpenScreen(receptionist, '/reservation', { tab: 'allotment' }), false)
  assert.equal(canOpenScreen(receptionist, '/frontdesk', { tab: 'day-close' }), false)
  assert.equal(canOpenScreen(receptionist, '/housekeeping', { tab: 'room-map' }), false)

  const hkStaff = user(['hk.view', 'hk.room.status', 'hk.service.bill'])
  assert.equal(canOpenScreen(hkStaff, '/housekeeping', { tab: 'minibar' }), true)
  assert.equal(canOpenScreen(hkStaff, '/housekeeping', { tab: 'inventory' }), false)
})

test('row 406: Sao lưu & khôi phục database chỉ dành cho admin, kể cả khi có system.setting', () => {
  const settingUser = user(['system.setting'])
  assert.equal(canOpenScreen(settingUser, '/config', { view: 'rate' }), true)
  assert.equal(canOpenScreen(settingUser, '/config', { view: 'database-backup' }), false)
  assert.equal(canOpenScreen(user([], { isAdmin: true }), '/config', { view: 'database-backup' }), true)
})

test('row 406: từng màn hình cấu hình có quyền riêng', () => {
  const rateOnly = user(['config.rates.view'])
  assert.equal(canOpenScreen(rateOnly, '/config', {}), true)
  assert.equal(canOpenScreen(rateOnly, '/config', { view: 'rate' }), true)
  assert.equal(canOpenScreen(rateOnly, '/config', { view: 'room' }), false)
  assert.equal(canOpenScreen(receptionist, '/config', {}), false)
  assert.equal(hasAccess(receptionist, ROUTE_PERMISSIONS['/pms']), true)
  assert.equal(hasAccess(receptionist, ROUTE_PERMISSIONS['/fnb']), false)
})

test('row 406: router, menu và trang module dùng chung bộ lọc quyền', () => {
  const router = read('../src/router/index.js')
  assert.match(router, /canOpenScreen\(authStore, to\.path, to\.query\)/)
  assert.match(router, /permission: ROUTE_PERMISSIONS\['\/config'\]/)

  const layout = read('../src/layouts/MainLayout.vue')
  assert.match(layout, /const menuItems = computed\(\(\) => filterMenuByPermission\(rawMenuItems\.value, authStore\)\)/)
  assert.match(layout, /filterMenuByPermission\(rawSubMenuItems\.value, authStore, modulePath\)/)

  assert.match(read('../src/pages/PmsPages.vue'), /v-for="\(item, index\) in visibleMenuItems"/)
  assert.match(read('../src/pages/FnbPages.vue'), /menuItems\.value = filterMenuByPermission\(items, authStore\)/)
  assert.match(read('../src/pages/housekeeping/HousekeepingPage.vue'), /allTabs\.value\.filter\(tab => canOpenScreen/)
  assert.match(read('../src/pages/fnb/OtherPages.vue'), /\.filter\(item => canOpenScreen\(authStore, '\/fnb\/other'/)

  const config = read('../src/pages/config/ConfigPage.vue')
  for (const view of ['hotel', 'room', 'system', 'rate', 'housekeeping-menu', 'design-template', 'database-backup']) {
    assert.match(config, new RegExp(`v-if="canView\\('${view}'\\)"`))
  }
})

test('row 406: xuất / khôi phục database bắt nhập lại mật khẩu và gửi lên server', () => {
  const tab = read('../src/pages/config/components/DatabaseBackupTab.vue')
  assert.match(tab, /const handleExport = \(\) => askPassword\('export'\)/)
  assert.match(tab, /http\.post\('\/system\/database\/export', \{ branch_code: targetCode, password \}/)
  assert.match(tab, /formData\.append\('password', password\)/)
  assert.match(tab, /@click="confirmImport"/)
  assert.match(tab, /type="password"/)
})
