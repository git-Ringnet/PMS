const CHECKED_OUT_STATUSES = new Set(['2', 'checkout', 'checked_out', 'checkedout'])
const ACTIVE_OR_NON_CHECKOUT_STATUSES = new Set([
  '0', '1', '3', '4', '100',
  'reservation', 'booked', 'inhouse', 'checked_in', 'checkedin',
  'cancelled', 'canceled', 'noshow', 'no_show', 'moved', 'transferred',
])

export function isCheckedOutRecord(record) {
  const status = String(record?.status ?? '').trim().toLowerCase()

  if (CHECKED_OUT_STATUSES.has(status)) return true
  if (ACTIVE_OR_NON_CHECKOUT_STATUSES.has(status)) return false

  return Boolean(record?.CheckoutDate || record?.checkout_date)
}
