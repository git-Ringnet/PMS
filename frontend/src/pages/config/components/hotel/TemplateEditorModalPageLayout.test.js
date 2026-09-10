import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

const source = readFileSync(resolve(import.meta.dirname, 'TemplateEditorModal.vue'), 'utf8')
const reportsSource = readFileSync(resolve(import.meta.dirname, '../../../reports/ReportsPage.vue'), 'utf8')

test('template canvas uses selected paper dimensions and preserves zero margins', () => {
  assert.match(source, /width: pageDimensions\.width/)
  assert.match(source, /minHeight: pageDimensions\.height/)
  assert.match(source, /pageMargin\(template\?\.margin_top\)/)
  assert.doesNotMatch(source, /w-\[210mm\] min-h-\[297mm\] p-6/)
})

test('template designer gives color inputs valid fallback values', () => {
  assert.match(source, /const colorInputValue/)
  assert.match(source, /colorInputValue\(selectedBlock\.style\.backgroundColor, '#ffffff'\)/)
  assert.doesNotMatch(source, /type="color" v-model="selectedBlock\.style\.backgroundColor"/)
})

test('template canvas does not keep a legacy portrait width limit', () => {
  assert.match(source, /\.template-preview-canvas \{ max-width: none !important; \}/)
})

test('report viewer derives its sheet dimensions from the saved page metadata', () => {
  assert.match(reportsSource, /paperSizes/)
  assert.match(reportsSource, /activeTemplate\.value\?\.page_size/)
  assert.match(reportsSource, /activeTemplate\.value\?\.page_orientation === 'landscape'/)
})
