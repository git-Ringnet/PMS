import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import test from 'node:test'
import assert from 'node:assert/strict'

const source = readFileSync(resolve(import.meta.dirname, 'TemplateEditorModal.vue'), 'utf8')

test('preserves legacy groups and two-tier header contracts when saving a template', () => {
  assert.match(source, /normalized\.grouping\) && normalized\.grouping\.length \? normalized\.grouping/)
  assert.match(source, /content: cell\.content \?\? cell\.label \?\? ''/)
  assert.match(source, /const hasTwoTierHeader = b\.hasTwoTierHeader === true/)
  assert.match(source, /class="pms-top-header-row"/)
  assert.match(source, /<th colspan="\$\{Math\.max\(1, Number\(cell\.colspan\) \|\| 1\)\}"/)
  assert.match(source, /customTableCellTextStyle = cell => mergeConfiguredStyles\(cell\?\.style/)
  assert.match(source, /pms-group-header\$\{group\.className/)
})
