import test from 'node:test'
import assert from 'node:assert/strict'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'

const source = readFileSync(resolve(import.meta.dirname, 'TemplateEditorModal.vue'), 'utf8')

test('TemplateEditorModal has normalizeCssDimension and resolveBlockStyles for margins and dimensions', () => {
  assert.match(source, /const normalizeCssDimension = \(val\) =>/)
  assert.match(source, /const resolveBlockStyles = \(b\) =>/)
  assert.match(source, /compiledStyles\.width = `calc\(100% - \${ml} - \${mr}\)`/)
  assert.match(source, /compiledStyles\.width = `calc\(100% - \${mr}\)`/)
  assert.match(source, /compiledStyles\.width = `calc\(100% - \${ml}\)`/)
  assert.match(source, /compiledStyles\.boxSizing = 'border-box'/)
})

test('TemplateEditorModal defines getCanvasBlockCardStyle and inner getBlockStyle', () => {
  assert.match(source, /const getBlockStyle = \(b, isInner = false\) =>/)
  assert.match(source, /const getCanvasBlockCardStyle = \(b\) =>/)
  assert.match(source, /cardStyle\.marginRight = resolved\.marginRight/)
  assert.match(source, /cardStyle\.boxSizing = 'border-box'/)
})

test('canvas cards for Header, Detail, and Footer bands bind getCanvasBlockCardStyle', () => {
  assert.match(source, /v-for="\(b, idx\) in blocks\.header"[^>]*:style="getCanvasBlockCardStyle\(b\)"/s)
  assert.match(source, /v-for="\(b, idx\) in blocks\.detail"[^>]*:style="getCanvasBlockCardStyle\(b\)"/s)
  assert.match(source, /v-for="\(b, idx\) in blocks\.footer"[^>]*:style="getCanvasBlockCardStyle\(b\)"/s)
  assert.match(source, /v-for="\(subBlock, subIdx\) in col\.blocks"[^>]*:style="getCanvasBlockCardStyle\(subBlock\)"/s)
})

test('inner canvas elements use getBlockStyle(b, true) to prevent margin duplication', () => {
  assert.match(source, /v-if="b\.type === 'text'"[^>]*:style="getBlockStyle\(b, true\)"/s)
  assert.match(source, /<table[^>]*:style="getBlockStyle\(b, true\)"/)
  assert.match(source, /v-else-if="b\.type === 'shape'"[^>]*getBlockStyle\(b, true\)/s)
})

test('right panel margin and dimension inputs have blur normalization and placeholders', () => {
  assert.match(source, /v-model="selectedBlock\.style\.marginRight"[^>]*@blur="selectedBlock\.style\.marginRight = normalizeCssDimension\(selectedBlock\.style\.marginRight\); compileHtml\(\)"/s)
  assert.match(source, /v-model="selectedBlock\.style\.width"[^>]*@blur="selectedBlock\.style\.width = normalizeCssDimension\(selectedBlock\.style\.width\); compileHtml\(\)"/s)
  assert.match(source, /placeholder="0px, 20, 5%\.\.\."/)
  assert.match(source, /placeholder="100%, 55%, 300px\.\.\."/)
})
