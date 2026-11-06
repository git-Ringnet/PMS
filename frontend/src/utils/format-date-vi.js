/** Định dạng dd/MM/yyyy từ nhiều kiểu chuỗi ngày (kể cả ISO có giờ). */
export function formatDateVi(dateStr) {
  if (!dateStr) return ''
  const str = String(dateStr).trim()
  // Dòng 487: ISO có giờ (vd 2026-09-02T17:00:00.000000Z) -> đổi sang ngày theo giờ máy, không cắt chuỗi thô.
  if (/^\d{4}-\d{2}-\d{2}T/.test(str)) {
    const iso = new Date(str)
    if (!isNaN(iso)) {
      return `${String(iso.getDate()).padStart(2, '0')}/${String(iso.getMonth() + 1).padStart(2, '0')}/${iso.getFullYear()}`
    }
  }
  const parts = str.split(/[-\/]/)
  if (parts.length === 3) {
    if (parts[0].length === 4) {
      return `${parts[2].padStart(2, '0')}/${parts[1].padStart(2, '0')}/${parts[0]}`
    } else if (parts[2].length === 4) {
      return `${parts[0].padStart(2, '0')}/${parts[1].padStart(2, '0')}/${parts[2]}`
    } else if (parts[2].length === 2) {
      const yyyy = '20' + parts[2]
      return `${parts[0].padStart(2, '0')}/${parts[1].padStart(2, '0')}/${yyyy}`
    }
  }
  const d = new Date(str)
  if (isNaN(d)) return str
  const yyyy = String(d.getFullYear())
  return `${String(d.getDate()).padStart(2, '0')}/${String(d.getMonth() + 1).padStart(2, '0')}/${yyyy}`
}
