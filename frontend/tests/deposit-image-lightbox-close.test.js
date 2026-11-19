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
  assert.match(source, /function closeTopLayerOnEscape/)
  assert.match(source, /if \(previewImageUrl\.value\) previewImageUrl\.value = null/)
})

test('TC_DP_16 modal: Esc đóng lần lượt ảnh → lý do xóa → tách/chuyển cọc → modal đặt cọc', () => {
  const start = source.indexOf('function closeTopLayerOnEscape')
  const block = source.slice(start, source.indexOf('onUnmounted', start))
  const order = ['previewImageUrl.value', 'isDeleteReasonOpen.value', 'isSplitOpen.value', 'isTransferOpen.value', 'props.show']
  const positions = order.map(key => block.indexOf(key))
  assert.ok(positions.every(pos => pos > -1))
  assert.deepEqual([...positions].sort((a, b) => a - b), positions)
  assert.match(block, /addEventListener\('keydown', closeTopLayerOnEscape\)/)
})
