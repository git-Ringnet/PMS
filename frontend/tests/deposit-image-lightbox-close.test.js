import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'

const source = readFileSync(new URL('../src/pages/reservation/components/DepositModal.vue', import.meta.url), 'utf8').replace(/\r\n/g, '\n')

test('xem ảnh cọc: lightbox ở body, nút đóng nằm trong khung và Esc đóng được', () => {
  const start = source.indexOf('<!-- Image Preview Lightbox Modal -->')
  const block = source.slice(start, source.indexOf('<!-- Delete reason form', start))
  assert.match(block, /<Teleport to="body">/)
  assert.match(block, /title="Đóng \(Esc\)"[^>]*class="absolute top-3 right-3/)
  assert.doesNotMatch(block, /-top-3 -right-3/)
  assert.match(source, /event\.key === 'Escape'\) previewImageUrl\.value = null/)
})
