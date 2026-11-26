// Checklist TC_HT_03 dòng 18 / task 471: "Thêm ứng dụng" cho vị trí công việc hiện danh sách ứng dụng chưa gán để chọn,
// một vị trí gán được nhiều ứng dụng (vd Lễ tân vào cả PMS và POS). "Sửa" chỉ sửa đúng ứng dụng đang chọn.

const code = value => String(value || '').trim().toUpperCase()

/** Mã các ứng dụng vị trí đã được gán (theo cấu hình chi nhánh - vai trò). */
export function assignedApplicationCodes(position) {
  return [...new Set((position?.branch_roles || []).map(item => code(item.application_code)).filter(Boolean))]
}

/** Danh sách ứng dụng hiển thị trong ô chọn của popup: thêm mới = chưa gán, sửa = đúng ứng dụng đang sửa. */
export function applicationChoices(applications, position, mode, editingCode = null) {
  const list = Array.isArray(applications) ? applications : []
  if (mode === 'edit') return list.filter(app => code(app.code) === code(editingCode))
  const assigned = assignedApplicationCodes(position)
  return list.filter(app => !assigned.includes(code(app.code)))
}
