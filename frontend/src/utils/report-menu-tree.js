// Cây menu: group có thể chứa group hoặc report ở mọi cấp.
export const cloneTree = (tree) => (tree || []).map(item => item.type === 'group'
  ? { ...item, children: cloneTree(item.children || []) }
  : { ...item })

const atPath = (tree, path = []) => path.reduce((items, index) => items?.[index]?.children, tree)
const parentPath = (path) => path.slice(0, -1)
const itemAt = (tree, path) => atPath(tree, parentPath(path))?.[path.at(-1)]

export const placedReportIds = (tree) => new Set((tree || []).flatMap(item => item.type === 'group'
  ? [...placedReportIds(item.children || [])]
  : [item.report_id]))

export const insertReport = (tree, report, targetPath = []) => {
  if (placedReportIds(tree).has(report.id)) return cloneTree(tree)
  const next = cloneTree(tree)
  const destination = atPath(next, targetPath)
  if (!destination) return next
  destination.push({ type: 'report', report_id: report.id, code: report.code, name: report.name, is_active: report.is_active })
  return next
}

export const addGroup = (tree, targetPath = [], name = 'Danh mục báo cáo mới') => {
  const next = cloneTree(tree)
  const destination = atPath(next, targetPath)
  if (!destination) return next
  destination.push({ type: 'group', name, children: [] })
  return next
}

export const renameGroup = (tree, path, name) => {
  const next = cloneTree(tree)
  const item = itemAt(next, path)
  if (item?.type === 'group') item.name = name
  return next
}

// Removing a group promotes its children to the removed group's parent.
export const removeNode = (tree, path) => {
  const next = cloneTree(tree)
  const siblings = atPath(next, parentPath(path))
  const index = path.at(-1)
  const [removed] = siblings?.splice(index, 1) || []
  if (removed?.type === 'group' && removed.children?.length) siblings.splice(index, 0, ...removed.children)
  return next
}

export const moveSibling = (tree, path, step) => {
  const next = cloneTree(tree)
  const siblings = atPath(next, parentPath(path))
  const index = path.at(-1)
  const target = index + step
  if (!siblings || target < 0 || target >= siblings.length) return next
  ;[siblings[index], siblings[target]] = [siblings[target], siblings[index]]
  return next
}

export const toPayload = (tree) => (tree || []).map(item => item.type === 'group'
  ? { type: 'group', name: String(item.name || '').trim(), children: toPayload(item.children || []) }
  : { type: 'report', report_id: item.report_id })

export const validateTree = (tree) => {
  const reports = new Set()
  const validate = (items) => {
    const names = new Set()
    for (const item of items || []) {
      if (item.type === 'group') {
        const name = String(item.name || '').trim()
        if (!name) return 'Tên danh mục báo cáo không được để trống.'
        const key = name.toLocaleLowerCase('vi')
        if (names.has(key)) return `Danh mục "${name}" bị trùng tên cùng cấp.`
        names.add(key)
        const error = validate(item.children || [])
        if (error) return error
      } else if (!item.report_id || reports.has(item.report_id)) {
        return item.report_id ? 'Một báo cáo chỉ được xếp một lần trong menu của module.' : 'Mục báo cáo không hợp lệ.'
      } else {
        reports.add(item.report_id)
      }
    }
    return ''
  }
  return validate(tree)
}
