import test from 'node:test'
import assert from 'node:assert/strict'

import {
  cloneDesignerBlock,
  createDesignerSnapshot,
  moveDesignerHistory,
  parseDesignerSnapshot,
  pushDesignerSnapshot,
} from '../src/utils/report-designer-history.js'

test('history drops redo states after a new edit', () => {
  const result = pushDesignerSnapshot(['a', 'b', 'c'], 1, 'd')
  assert.deepEqual(result.entries, ['a', 'b', 'd'])
  assert.equal(result.index, 2)
})

test('history ignores an unchanged snapshot and enforces its limit', () => {
  assert.equal(pushDesignerSnapshot(['a'], 0, 'a').changed, false)
  assert.deepEqual(pushDesignerSnapshot(['a', 'b'], 1, 'c', 2), {
    entries: ['b', 'c'],
    index: 1,
    changed: true,
  })
})

test('history navigation stays inside available entries', () => {
  assert.deepEqual(moveDesignerHistory(['a', 'b'], 1, -1), { index: 0, snapshot: 'a', changed: true })
  assert.equal(moveDesignerHistory(['a', 'b'], 0, -1).changed, false)
})

test('snapshot parsing returns an independent value', () => {
  const original = { blocks: [{ content: 'A' }] }
  const parsed = parseDesignerSnapshot(createDesignerSnapshot(original))
  parsed.blocks[0].content = 'B'
  assert.equal(original.blocks[0].content, 'A')
})

test('block cloning renews nested identifiers without mutating source', () => {
  let sequence = 0
  const source = {
    id: 'old',
    type: 'columns',
    columns: [{ blocks: [{ id: 'nested', type: 'text', content: 'A' }] }],
    customRows: [{ id: 'row', cells: [{ id: 'cell' }] }],
  }
  const copy = cloneDesignerBlock(source, type => `${type}-${++sequence}`)

  assert.notEqual(copy.id, source.id)
  assert.notEqual(copy.columns[0].blocks[0].id, source.columns[0].blocks[0].id)
  assert.notEqual(copy.customRows[0].id, source.customRows[0].id)
  assert.equal(source.id, 'old')
})

test('block cloning supports reactive-style proxy objects', () => {
  const source = new Proxy({ id: 'proxy', type: 'text', content: 'A' }, {})
  const copy = cloneDesignerBlock(source, type => `${type}-new`)
  assert.equal(copy.id, 'text-new')
  assert.equal(copy.content, 'A')
})
