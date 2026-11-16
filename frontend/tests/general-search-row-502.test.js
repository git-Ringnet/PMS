import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

import { isRoomMapRouteOwned, moduleContextFromPath, resolveModulePath } from '../src/utils/module-route.js'

const read = path => readFileSync(new URL(path, import.meta.url), 'utf8')

test('row 502: module path is resolved from the route that opened the screen', () => {
  assert.equal(resolveModulePath('/frontdesk'), '/frontdesk')
  assert.equal(resolveModulePath('/reservation'), '/reservation')
  assert.equal(resolveModulePath('/housekeeping'), '/housekeeping')
  assert.equal(moduleContextFromPath('/reservation'), 'reservation')
})

test('row 502: a cached room map ignores routes of another module', () => {
  // Sơ đồ phòng của Sale đang ẩn không được dựng Tạo đăng ký/Hóa đơn theo URL của Lễ tân.
  assert.equal(isRoomMapRouteOwned('/reservation', '/frontdesk', 'create-res'), false)
  assert.equal(isRoomMapRouteOwned('/reservation', '/frontdesk', 'checkout'), false)
  assert.equal(isRoomMapRouteOwned('/frontdesk', '/reservation', 'create-res'), false)
  assert.equal(isRoomMapRouteOwned('/reservation', '/reservation', 'checkout'), true)
  assert.equal(isRoomMapRouteOwned('/reservation', '/reservation', 'search'), true)
})

test('row 502: front desk room map ignores tabs FrontDeskPage renders itself', () => {
  for (const tab of ['checkout', 'search', 'history', 'day-close', 'reports']) {
    assert.equal(isRoomMapRouteOwned('/frontdesk', '/frontdesk', tab), false, tab)
  }
  assert.equal(isRoomMapRouteOwned('/frontdesk', '/frontdesk', 'create-res'), true)
  assert.equal(isRoomMapRouteOwned('/frontdesk', '/frontdesk', 'room-map'), true)
})

test('row 502: general search keeps the module and only front desk can check in', () => {
  const source = read('../src/pages/frontdesk/GeneralSearchPage.vue')
  for (const handler of ['handleActionReport', 'handleActionPrintConfirmation', 'handleActionPrintResidence', 'handleActionPrintBreakfast', 'handleActionLostFound']) {
    assert.equal(source.includes(handler), false, handler)
  }
  assert.doesNotMatch(source, /path:\s*'\/frontdesk'/)
  assert.match(source, /path:\s*ownerModulePath,\s*\r?\n\s*query:\s*\{\s*\r?\n\s*tab:\s*'checkout'/)
  assert.match(source, /v-if="isFrontdeskModule"[\s\S]{0,200}@click="handleActionCheckIn"/)
  assert.match(source, /async function handleActionCheckIn\(\) \{\s*\r?\n\s*showActionsMenu\.value = false\s*\r?\n\s*if \(!isFrontdeskModule\) return/)
})

test('row 502: hidden cached registration/invoice pages do not react to other routes', () => {
  const registration = read('../src/pages/reservation/CreateRegistrationPage.vue')
  assert.match(registration, /watch\(\(\) => route\.query, \(newQuery\) => \{\s*\r?\n\s*if \(!pageActive\) return/)
  assert.match(registration, /function syncUrlBookingCode\(bookingCode\) \{\s*\r?\n\s*if \(!pageActive\) return/)
  const checkout = read('../src/pages/frontdesk/CheckoutPage.vue')
  assert.match(checkout, /if \(!checkoutPageActive\) \{\s*\r?\n\s*checkoutRouteChangedWhileHidden = true/)
  const roomMap = read('../src/pages/reservation/RoomMapPage.vue')
  assert.match(roomMap, /isRoomMapRouteOwned\(ownerModulePath, path, tab\)/)
})
