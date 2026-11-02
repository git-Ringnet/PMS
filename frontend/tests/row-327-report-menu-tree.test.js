import test from 'node:test'
import assert from 'node:assert/strict'
import { addGroup, insertReport, insertReportBefore, moveReport, moveReportBefore, moveSibling, placedReportIds, removeNode, reportGroupPath, toPayload, transferReports, validateTree } from '../src/utils/report-menu-tree.js'

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

test('moves an existing report between categories without duplicating it', () => {
  const moved = moveReport(base(), 2, [2])
  assert.deepEqual(shape(moved), [1, { Khách: [3] }, { 'Doanh thu': [4, 2] }])
  assert.equal([...placedReportIds(moved)].filter(id => id === 2).length, 1)
  assert.deepEqual(reportGroupPath(moved, 2), ['Doanh thu'])
})

test('moving a report supports nested target categories', () => {
  const nested = addGroup(base(), [1], 'Theo ngày')
  const moved = moveReport(nested, 4, [1, 2])
  assert.deepEqual(shape(moved), [1, { Khách: [2, 3, { 'Theo ngày': [4] }] }, { 'Doanh thu': [] }])
})

test('transfers multiple reports without invalidating the target path', () => {
  const transferred = transferReports(base(), [{ id: 1, code: 'C1', name: 'R1' }, { id: 2, code: 'C2', name: 'R2' }], [2])
  assert.deepEqual(shape(transferred), [{ Khách: [3] }, { 'Doanh thu': [4, 1, 2] }])
})

test('reorders a report before another report in the same category', () => {
  const reordered = moveReportBefore(base(), 3, 2)
  assert.deepEqual(shape(reordered), [1, { Khách: [3, 2] }, { 'Doanh thu': [4] }])
})

test('inserts an unplaced report before a selected report', () => {
  const inserted = insertReportBefore(base(), { id: 5, code: 'C5', name: 'R5' }, 2)
  assert.deepEqual(shape(inserted), [1, { Khách: [5, 2, 3] }, { 'Doanh thu': [4] }])
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
