// Chuyển đổi giữa 'YYYY-MM' (giá trị lưu) và 'mm/yyyy' (hiển thị/gõ tay).

export function formatMonthDisplay(value) {
  const m = /^(\d{4})-(\d{2})$/.exec(String(value || ''))
  return m ? `${m[2]}/${m[1]}` : ''
}

// Trả về 'YYYY-MM' nếu hợp lệ, null nếu sai định dạng. Chấp nhận m/yyyy, mm/yyyy, mm-yyyy, mmyyyy.
export function parseMonthInput(text) {
  const s = String(text || '').trim()
  const m = /^(\d{1,2})\s*[/\-.]?\s*(\d{4})$/.exec(s)
  if (!m) return null
  const month = Number(m[1])
  const year = Number(m[2])
  if (month < 1 || month > 12 || year < 1900 || year > 2999) return null
  return `${year}-${String(month).padStart(2, '0')}`
}
