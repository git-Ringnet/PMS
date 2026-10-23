// Dòng 327: thao tác trên cây menu báo cáo của một module.
// Cây: [{ type: 'group', name, children: [report] }, { type: 'report', report_id, code, name }]
// Vị trí một nút: { parent: null | chỉ số nhóm ở cấp gốc, index }

export const cloneTree = (tree) => (tree || []).map(item => item.type === 'group'
  ? { ...item, children: (item.children || []).map(child => ({ ...child })) }
  : { ...item })

const listAt = (tree, parent) => parent === null || parent === undefined ? tree : tree[parent]?.children

export const placedReportIds = (tree) => new Set((tree || []).flatMap(item => item.type === 'group'
  ? (item.children || []).map(child => child.report_id)
  : [item.report_id]))

/** Di chuyển một nút tới vị trí mới. Nhóm không được đưa vào trong nhóm khác. */
export const moveNode = (tree, from, to) => {
  const next = cloneTree(tree)
  const source = listAt(next, from.parent)
  const node = source?.[from.index]
  if (!node) return next
  const toParent = to.parent ?? null
  if (node.type === 'group' && toParent !== null) return next
  if (from.parent === null || from.parent === undefined) {
    if (toParent !== null && toParent === from.index) return next
  }

  source.splice(from.index, 1)
  let targetParent = toParent
  let targetIndex = to.index
  // Bỏ nút ở cấp gốc phía trước làm lệch chỉ số nhóm / vị trí đích
  if ((from.parent ?? null) === null && targetParent !== null && from.index < targetParent) targetParent -= 1
  if ((from.parent ?? null) === targetParent && from.index < targetIndex) targetIndex -= 1

  const target = listAt(next, targetParent)
  if (!target) return tree
  target.splice(Math.max(0, Math.min(targetIndex, target.length)), 0, node)
  return next
}

/** Thêm báo cáo từ danh sách bên trái vào cây (mặc định cuối cấp gốc). */
export const insertReport = (tree, report, to = { parent: null, index: Infinity }) => {
  if (placedReportIds(tree).has(report.id)) return cloneTree(tree)
  const next = cloneTree(tree)
  const target = listAt(next, to.parent ?? null)
  if (!target) return next
  const item = { type: 'report', report_id: report.id, code: report.code, name: report.name, is_active: report.is_active }
  target.splice(Math.max(0, Math.min(to.index, target.length)), 0, item)
  return next
}

export const removeNode = (tree, at) => {
  const next = cloneTree(tree)
  listAt(next, at.parent ?? null)?.splice(at.index, 1)
  return next
}

/** Dữ liệu gửi API lưu cấu hình. */
export const toPayload = (tree) => (tree || []).map(item => item.type === 'group'
  ? { type: 'group', name: String(item.name || '').trim(), children: (item.children || []).map(child => ({ report_id: child.report_id })) }
  : { type: 'report', report_id: item.report_id })

/** Kiểm tra trước khi lưu: tên nhóm không trống, không trùng. Trả về thông báo lỗi hoặc ''. */
export const validateTree = (tree) => {
  const names = new Set()
  for (const item of tree || []) {
    if (item.type !== 'group') continue
    const name = String(item.name || '').trim()
    if (!name) return 'Tên nhóm báo cáo không được để trống.'
    const key = name.toLocaleLowerCase('vi')
    if (names.has(key)) return `Nhóm "${name}" bị trùng tên.`
    names.add(key)
  }
  return ''
}
