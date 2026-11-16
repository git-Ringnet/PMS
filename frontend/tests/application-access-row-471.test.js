import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { hasApplication, requiredApplication } from '../src/utils/screen-permissions.js'

const read = (path) => readFileSync(new URL(path, import.meta.url), 'utf8')

test('row 471: PMS screens need PMS, F&B screens need POS, system/config are not application-bound', () => {
  assert.equal(requiredApplication('/frontdesk'), 'PMS')
  assert.equal(requiredApplication('/reservation'), 'PMS')
  assert.equal(requiredApplication('/pms'), 'PMS')
  assert.equal(requiredApplication('/fnb'), 'POS')
  assert.equal(requiredApplication('/fnb/restaurant'), 'POS')
  assert.equal(requiredApplication('/system'), null)
  assert.equal(requiredApplication('/config'), null)
})

test('row 471: user with only PMS cannot open the F&B application', () => {
  const hkm = { isSuperAdmin: false, applications: ['PMS'] }
  assert.equal(hasApplication(hkm, 'PMS'), true)
  assert.equal(hasApplication(hkm, 'POS'), false)
  assert.equal(hasApplication({ isSuperAdmin: false, applications: ['pms', 'pos'] }, 'POS'), true)
})

test('row 471: super admin and legacy users without positions are not restricted', () => {
  assert.equal(hasApplication({ isSuperAdmin: true, applications: ['PMS'] }, 'POS'), true)
  assert.equal(hasApplication({ isSuperAdmin: false, applications: null }, 'POS'), true)
  assert.equal(hasApplication({ isSuperAdmin: false, applications: [] }, 'PMS'), false)
})

test('row 471: home cards and router guard use the assigned applications', () => {
  const home = read('../src/pages/HomePage.vue')
  assert.match(home, /authStore\.hasApplication\('PMS'\)/)
  assert.match(home, /authStore\.hasApplication\('POS'\)/)
  assert.match(read('../src/router/index.js'), /hasApplication\(authStore, requiredApplication\(to\.path\)\)/)
  const store = read('../src/stores/auth-store.js')
  assert.match(store, /applications/)
  assert.match(store, /removeItem\('pms_applications'\)/)
})
