const clone = value => {
  if (typeof structuredClone === 'function') {
    try {
      return structuredClone(value)
    } catch {
      // Vue exposes blocks as reactive proxies, which structuredClone rejects.
    }
  }
  return JSON.parse(JSON.stringify(value))
}

export const createDesignerSnapshot = state => JSON.stringify(state)

export const parseDesignerSnapshot = snapshot => clone(JSON.parse(snapshot))

export const pushDesignerSnapshot = (entries, index, snapshot, limit = 60) => {
  const current = entries[index]
  if (current === snapshot) return { entries, index, changed: false }

  const nextEntries = [...entries.slice(0, index + 1), snapshot]
  const overflow = Math.max(0, nextEntries.length - limit)
  return {
    entries: overflow ? nextEntries.slice(overflow) : nextEntries,
    index: nextEntries.length - 1 - overflow,
    changed: true,
  }
}

export const moveDesignerHistory = (entries, index, direction) => {
  const nextIndex = Math.min(entries.length - 1, Math.max(0, index + direction))
  return {
    index: nextIndex,
    snapshot: entries[nextIndex] || null,
    changed: nextIndex !== index,
  }
}

export const cloneDesignerBlock = (block, createId) => {
  const cloned = clone(block)

  const renew = item => {
    item.id = createId(item.type || 'block')
    if (Array.isArray(item.columns)) {
      item.columns.forEach(column => {
        if (Array.isArray(column.blocks)) column.blocks.forEach(renew)
      })
    }
    if (Array.isArray(item.rows)) {
      item.rows.forEach(row => {
        if (row.id) row.id = createId('row')
        row.cells?.forEach(cell => {
          if (cell.id) cell.id = createId('cell')
        })
      })
    }
    item.customRows?.forEach(row => {
      row.id = createId('custom-row')
      row.cells?.forEach(cell => { cell.id = createId('custom-cell') })
    })
    item.groups?.forEach(group => {
      group.id = createId('group')
      group.headerCells?.forEach(cell => { cell.id = createId('group-cell') })
    })
  }

  renew(cloned)
  return cloned
}
