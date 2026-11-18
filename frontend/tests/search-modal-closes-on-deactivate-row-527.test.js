import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

const read = (path) => readFileSync(new URL(path, import.meta.url), 'utf8').replace(/\r\n/g, '\n')

// Dòng 527 (17/11): đang ở màn hình đăng ký, mở tìm kiếm BK rồi bấm logo về giao diện module thì khung "Kết quả tìm kiếm Booking"
// vẫn nằm đè lên Portal. Modal được Teleport ra body còn trang đăng ký bị KeepAlive giữ ẩn nên không tự đóng.
test('row 527: the registration page closes its booking-search modal when it is deactivated', () => {
  const source = read('../src/pages/reservation/CreateRegistrationPage.vue')
  const hook = source.match(/onDeactivated\(\(\) => \{([\s\S]*?)\n\}\)/)[1]
  assert.match(hook, /pageActive = false/)
  assert.match(hook, /isGlobalSearchOpen\.value = false/)
})

test('row 527: the header booking-search modal closes when the route changes', () => {
  const source = read('../src/layouts/MainLayout.vue')
  assert.match(source, /watch\(\(\) => route\.path, \(\) => \{[\s\S]*?isHeaderBookingSearchOpen\.value = false/)
})
