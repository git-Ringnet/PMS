import test from 'node:test'
import assert from 'node:assert/strict'
import { addGroup, insertReport, moveSibling, placedReportIds, removeNode, toPayload, validateTree } from '../src/utils/report-menu-tree.js'

const r = (id, name = `R${id}`) => ({ type: 'report', report_id: id, code: `C${id}`, name })
const g = (name, children = []) => ({ type: 'group', name, children })
const shape = tree => tree.map(item => item.type === 'group' ? { [item.name]: shape(item.children) } : item.report_id)

const base = () => [r(1), g('Khách', [r(2), r(3)]), g('Doanh thu', [r(4)])]

test('supports nested categories and sibling ordering', () => {
  const nested = addGroup(base(), [1], 'Theo ngày')
  assert.deepEqual(shape(nested), [1, { Khách: [2, 3, { 'Theo ngày': [] }] }, { 'Doanh thu': [4] }])
  assert.deepEqual(shape(moveSibling(nested, [1, 2], -1)), [1, { Khách: [2, { 'Theo ngày': [] }, 3] }, { 'Doanh thu': [4] }])
})

test('inserts a report once and removes nodes', () => {
  const added = insertReport(base(), { id: 5, code: 'C5', name: 'R5' }, [1])
  assert.deepEqual(shape(added), [1, { Khách: [2, 3, 5] }, { 'Doanh thu': [4] }])
  assert.deepEqual(shape(insertReport(added, { id: 5 })), shape(added))
  assert.deepEqual([...placedReportIds(added)].sort(), [1, 2, 3, 4, 5])
  assert.deepEqual(shape(removeNode(added, [1])), [1, 2, 3, 5, { 'Doanh thu': [4] }])
})

test('payload and validation', () => {
  assert.deepEqual(toPayload([r(1), g(' Khách ', [g('Theo ngày', [r(2)])])]), [
    { type: 'report', report_id: 1 },
    { type: 'group', name: 'Khách', children: [{ type: 'group', name: 'Theo ngày', children: [{ type: 'report', report_id: 2 }] }] }
  ])
  assert.equal(validateTree([g('  ')]), 'Tên danh mục báo cáo không được để trống.')
  assert.match(validateTree([g('Khách'), g('khách')]), /trùng/)
  assert.match(validateTree([g('Khách', [r(2)]), g('Doanh thu', [r(2)])]), /một lần/)
  assert.equal(validateTree(base()), '')
})
