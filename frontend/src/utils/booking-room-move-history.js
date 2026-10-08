export function normalizeMoveHistoryBillReferences(bills) {
  const seen = new Set()
  return (Array.isArray(bills) ? bills : []).flatMap(bill => {
    const id = bill?.bill_id ?? bill?.Ma ?? bill?.id
    if (id === null || id === undefined || String(id).trim() === '' || seen.has(String(id))) return []
    seen.add(String(id))

    return [{
      ...bill,
      Ma: id,
      Date: bill.date ?? bill.Date ?? bill.service_date,
      ServiceId: bill.service_code ?? bill.ServiceId ?? bill.service_code,
      DescriptionServive: bill.description ?? bill.DescriptionServive ?? bill.service_name,
      Quantity: bill.quantity ?? bill.Quantity,
      Amount: bill.amount ?? bill.Amount,
      RentalRoomId1: bill.source_room_id ?? bill.RentalRoomId1,
      RentalRoomId2: bill.folio_room_id ?? bill.RentalRoomId2,
      Edit: bill.Edit ?? 0,
      Status: bill.Status ?? 0,
      is_posted_reference: true,
      from_move_history: true,
    }]
  })
}

export function isMoveHistoryBillReference(service) {
  return Boolean(service?.from_move_history || service?.bill_ref?.from_move_history)
}

function dateOnly(value) {
  const raw = String(value ?? '').trim()
  if (/^\d{4}-\d{2}-\d{2}/.test(raw)) return raw.slice(0, 10)
  const parts = raw.split('/')
  if (parts.length === 3 && parts[2].length === 4) return `${parts[2]}-${parts[1]}-${parts[0]}`
  return ''
}

export function mergeMoveHistoryBillReferences(existingBills, moveHistoryBills, arrivalDate, departureDate) {
  const normalizedHistory = normalizeMoveHistoryBillReferences(moveHistoryBills)
  const start = dateOnly(arrivalDate)
  const end = dateOnly(departureDate)
  const stayHistory = start && end && start < end
    ? normalizedHistory.filter(bill => {
        const date = dateOnly(bill.Date)
        return date && date >= start && date < end
      })
    : []
  const existingIds = new Set((Array.isArray(existingBills) ? existingBills : [])
    .map(bill => bill?.Ma ?? bill?.id)
    .filter(id => id !== null && id !== undefined)
    .map(String))
  return [
    ...(Array.isArray(existingBills) ? existingBills : []),
    ...stayHistory.filter(bill => !existingIds.has(String(bill.Ma))),
  ]
}

export function calculateChargeableServiceTotals(services) {
  const totals = { roomCharge: 0, extraBed: 0, services: 0, total: 0 }
  for (const service of Array.isArray(services) ? services : []) {
    if (isMoveHistoryBillReference(service)) continue
    const amount = Number(service.rate) * Number(service.quantity || 1)
    const code = String(service.service_code ?? service.ServiceId ?? '').trim().toUpperCase()
    if (['ROOM_CHARGE', 'RM', 'ER'].includes(code)) totals.roomCharge += amount
    else if (code === 'EB') totals.extraBed += amount
    else totals.services += amount
    totals.total += amount
  }
  return totals
}