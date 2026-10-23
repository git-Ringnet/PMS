import test from 'node:test'
import assert from 'node:assert/strict'
import { insertReport, moveNode, placedReportIds, removeNode, toPayload, validateTree } from '../src/utils/report-menu-tree.js'

const r = (id, name = `R${id}`) => ({ type: 'report', report_id: id, code: `C${id}`, name })
const g = (name, children = []) => ({ type: 'group', name, children })
const shape = tree => tree.map(item => item.type === 'group' ? { [item.name]: item.children.map(c => c.report_id) } : item.report_id)

const base = () => [r(1), g('Khách', [r(2), r(3)]), g('Doanh thu', [r(4)])]

test('moves report inside the same group', () => {
  assert.deepEqual(shape(moveNode(base(), { parent: 1, index: 0 }, { parent: 1, index: 2 })), [1, { Khách: [3, 2] }, { 'Doanh thu': [4] }])
})

test('moves top-level report into a later group (index shift)', () => {
  assert.deepEqual(shape(moveNode(base(), { parent: null, index: 0 }, { parent: 2, index: 0 })), [{ Khách: [2, 3] }, { 'Doanh thu': [1, 4] }])
})

test('moves report out of group to top level', () => {
  assert.deepEqual(shape(moveNode(base(), { parent: 1, index: 1 }, { parent: null, index: 0 })), [3, 1, { Khách: [2] }, { 'Doanh thu': [4] }])
})

test('reorders groups and refuses group inside group', () => {
  assert.deepEqual(shape(moveNode(base(), { parent: null, index: 2 }, { parent: null, index: 0 })), [{ 'Doanh thu': [4] }, 1, { Khách: [2, 3] }])
  assert.deepEqual(shape(moveNode(base(), { parent: null, index: 2 }, { parent: 1, index: 0 })), shape(base()))
})

test('inserts a report once and removes nodes', () => {
  const added = insertReport(base(), { id: 5, code: 'C5', name: 'R5' }, { parent: 1, index: 1 })
  assert.deepEqual(shape(added), [1, { Khách: [2, 5, 3] }, { 'Doanh thu': [4] }])
  assert.deepEqual(shape(insertReport(added, { id: 5 })), shape(added))
  assert.deepEqual([...placedReportIds(added)].sort(), [1, 2, 3, 4, 5])
  assert.deepEqual(shape(removeNode(added, { parent: null, index: 1 })), [1, { 'Doanh thu': [4] }])
})

test('payload and validation', () => {
  assert.deepEqual(toPayload([r(1), g(' Khách ', [r(2)])]), [
    { type: 'report', report_id: 1 },
    { type: 'group', name: 'Khách', children: [{ report_id: 2 }] }
  ])
  assert.equal(validateTree([g('  ')]), 'Tên nhóm báo cáo không được để trống.')
  assert.match(validateTree([g('Khách'), g('khách')]), /trùng/)
  assert.equal(validateTree(base()), '')
})
