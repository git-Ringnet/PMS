// Bộ lọc dịch vụ trên màn Hóa đơn (modal "Lọc Dịch Vụ").

const upper = value => String(value ?? '').trim().toUpperCase()

const pad = value => String(value).padStart(2, '0')

/**
 * Ngày (Y-m-d) theo giờ máy. Laravel trả cột date đã cast dạng UTC ("2026-09-01T17:00:00.000000Z" = 02/09 giờ VN),
 * cắt 10 ký tự đầu sẽ lệch 1 ngày.
 */
export const normalizeServiceDate = value => {
  if (!value) return ''
  const text = String(value)
  if (/T\d{2}:\d{2}.*(Z|[+-]\d{2}:?\d{2})$/u.test(text)) {
    const date = new Date(text)
    if (!Number.isNaN(date.getTime())) return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
  }
  const match = text.match(/^\d{4}-\d{2}-\d{2}/)
  return match ? match[0] : text.slice(0, 10)
}

/**
 * filter = { startDate, endDate, displayFilter, services: [{ code, folio }], departments: [code] }.
 * Không tick dịch vụ / bộ phận nào thì không lọc theo mục đó; folio rỗng = mọi folio.
 * Danh sách "Đã xoá" do nơi gọi truyền vào (services đã là bill đã xóa).
 */
export function filterServiceItems(services, filter) {
  if (!filter) return services
  const serviceRules = new Map((filter.services || []).map(rule => [upper(rule.code), String(rule.folio || '')]))
  const departmentCodes = new Set((filter.departments || []).map(upper))

  return services.filter(service => {
    const date = normalizeServiceDate(service.serviceDate || service.createdAt)
    if (filter.startDate && (!date || date < filter.startDate)) return false
    if (filter.endDate && (!date || date > filter.endDate)) return false
    if (serviceRules.size > 0) {
      const code = upper(service.serviceCode)
      if (!serviceRules.has(code)) return false
      const ruleFolio = serviceRules.get(code)
      if (ruleFolio && String(service.folio) !== ruleFolio) return false
    }
    if (departmentCodes.size > 0 && !departmentCodes.has(upper(service.department))) return false

    const paid = Boolean(service.isPaid || service.paymentCode || Number(service.status) === 2)
    const hasVat = Boolean(service.invoiceCode || service.vatNo)
    if (filter.displayFilter === 'unpaid' && paid) return false
    if (filter.displayFilter === 'paid' && !paid) return false
    if (filter.displayFilter === 'vat' && !hasVat) return false
    if (filter.displayFilter === 'unprinted_vat' && (!service.vatNo || service.invoiceCode)) return false
    return true
  })
}

/** Bill đã xóa (Edit=1, Status=3), bỏ dòng âm đối trừ (Pack1 = mã bill gốc). */
export function deletedServiceBills(bills) {
  const billIds = new Set(bills.map(bill => String(bill?.Ma ?? '')))
  return bills.filter(bill => (
    Number(bill?.Edit) === 1
    && Number(bill?.Status) === 3
    && !(bill.Pack1 && billIds.has(String(bill.Pack1)))
  ))
}
