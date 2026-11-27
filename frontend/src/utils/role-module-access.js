// Checklist TC_HT_04 dòng 21 / task 473: trong ma trận quyền, mỗi phân hệ (module) có ô chọn để cấp nhanh
// cho vai trò được truy cập phân hệ đó (vd nhân viên lễ tân vào được cả Lễ tân và Buồng phòng).

const isView = permission => (permission.action || 'view') === 'view'

/** 'all' khi được xem mọi màn hình của phân hệ, 'some' khi được một phần, 'none' khi không được vào. */
export function moduleAccessState(permissions) {
  const views = (permissions || []).filter(isView)
  const granted = views.filter(item => item.granted).length
  if (!views.length || granted === 0) return 'none'
  return granted === views.length ? 'all' : 'some'
}

/** Tích: được xem mọi màn hình của phân hệ. Bỏ tích: không còn vào phân hệ (bỏ cả quyền thêm/sửa/xóa). */
export function setModuleAccess(permissions, checked) {
  for (const permission of permissions || []) {
    if (checked) {
      if (isView(permission)) permission.granted = true
    } else {
      permission.granted = false
    }
  }
}
