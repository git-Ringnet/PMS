// Cột bảng "Chi tiết phòng booking" (checklist dòng 98 / FIX FE task 109):
// thứ tự và ẩn/hiện cột được lưu theo user (user-settings trên server) nên không mất khi tải lại trang
// hay đăng nhập máy khác.

/** Chuyển danh sách cột hiện tại thành dữ liệu lưu: thứ tự key + map key -> hiển thị. */
export function serializeRoomColumns(columns) {
  return {
    order: columns.map(col => col.key),
    visible: Object.fromEntries(columns.map(col => [col.key, !!col.visible])),
  }
}

/**
 * Áp thứ tự/ẩn hiện đã lưu lên danh sách cột mặc định (không sửa mảng mặc định).
 * Key lạ bị bỏ qua, cột mới thêm sau khi user lưu được nối vào cuối theo thứ tự mặc định.
 */
export function applySavedRoomColumns(defaultColumns, savedOrder, savedVisible) {
  const byKey = new Map(defaultColumns.map(col => [col.key, col]))
  const ordered = []
  if (Array.isArray(savedOrder)) {
    for (const key of savedOrder) {
      if (byKey.has(key) && !ordered.includes(key)) ordered.push(key)
    }
  }
  for (const col of defaultColumns) {
    if (!ordered.includes(col.key)) ordered.push(col.key)
  }

  const visibleMap = savedVisible && typeof savedVisible === 'object' ? savedVisible : {}
  return ordered.map(key => {
    const col = { ...byKey.get(key) }
    if (typeof visibleMap[key] === 'boolean') col.visible = visibleMap[key]
    return col
  })
}
