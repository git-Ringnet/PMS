import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

const source = readFileSync(resolve(import.meta.dirname, 'TemplateEditorModal.vue'), 'utf8')

test('TemplateEditorModal has detail table selection keys and targets', () => {
  assert.match(source, /const selectedDetailCellKeys = ref\(\[\]\)/)
  assert.match(source, /const selectedDetailCellTargets = ref\(\[\]\)/)
  assert.match(source, /const detailCellKey = \(block, kind, idOrIdx\)/)
  assert.match(source, /const isDetailCellSelected = \(key\)/)
  assert.match(source, /const selectDetailCell = \(event, block, kind, targetData\)/)
})

test('TemplateEditorModal has active detail properties and styling toggles', () => {
  assert.match(source, /const isDetailTargetActive = computed/)
  assert.match(source, /const isDetailBold = computed/)
  assert.match(source, /const isDetailItalic = computed/)
  assert.match(source, /const isDetailUnderline = computed/)
  assert.match(source, /const detailTextColor = computed/)
  assert.match(source, /const detailBgColor = computed/)
  assert.match(source, /const toggleDetailBold = \(\) =>/)
  assert.match(source, /const toggleDetailItalic = \(\) =>/)
  assert.match(source, /const toggleDetailUnderline = \(\) =>/)
  assert.match(source, /const applyDetailStyle = \(property, value\) =>/)
  assert.match(source, /const updateDetailContent = \(newVal\) =>/)
})

test('TemplateEditorModal applies selected-detail-cell class and black outline styling', () => {
  assert.match(source, /selected-detail-cell/)
  assert.match(source, /\.selected-detail-cell \{[\s\S]*outline: 2px solid #000000 !important;[\s\S]*outline-offset: -2px !important;[\s\S]*box-shadow: inset 0 0 0 2px #000000 !important;/)
})

test('TemplateEditorModal toolbar has inputs and active classes for detail table properties', () => {
  assert.match(source, /v-else-if="isDetailTargetActive"/)
  assert.match(source, /:class="isDetailBold \? 'bg-sky-100 border-sky-400 text-sky-800 ring-1 ring-sky-400'/)
  assert.match(source, /:class="isDetailItalic \? 'bg-sky-100 border-sky-400 text-sky-800 ring-1 ring-sky-400'/)
  assert.match(source, /:class="isDetailUnderline \? 'bg-sky-100 border-sky-400 text-sky-800 ring-1 ring-sky-400'/)
  assert.match(source, /:value="colorInputValue\(detailTextColor, '#1e293b'\)"/)
  assert.match(source, /:value="colorInputValue\(detailBgColor, '#ffffff'\)"/)
})
