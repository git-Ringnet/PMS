/**
 * Parse a breakfast amount from either a number or a formatted text input.
 * Empty/invalid values intentionally become zero so the API never receives
 * NaN or a locale-specific thousands separator.
 */
export function normalizeBreakfastAmount(value) {
  if (typeof value === 'number') {
    return Number.isFinite(value) && value >= 0 ? value : 0
  }

  const raw = String(value ?? '').trim()
  // Do not turn a negative input such as "-100" into a valid positive
  // amount by stripping the sign before parsing it.
  if (/^[-−]/.test(raw)) return 0

  const normalized = raw
    .replace(/,/g, '')
    .replace(/[^0-9.]/g, '')

  if (!normalized) return 0

  const amount = Number(normalized)
  return Number.isFinite(amount) && amount >= 0 ? amount : 0
}

export function breakfastAmountForState(detail, ageGroup, prices = {}) {
  if (!detail?.breakfast || ageGroup === 'baby' || detail.is_free) return 0

  const amount = detail.is_extra_charge
    ? prices.extraCharge
    : prices.noCharge

  return normalizeBreakfastAmount(amount)
}

/**
 * Apply a parent-row edit to all of its daily rows. An amount edit is kept as
 * entered; only state-toggle edits resolve the configured default amount.
 */
export function applyParentBreakfastField(child, field, value, prices = {}) {
  if (!child) return child

  const amount = field === 'amount'
    ? normalizeBreakfastAmount(value)
    : value

  child[field] = amount

  if (child.age_group === 'baby') {
    child.is_free = true
    child.is_extra_charge = false
  } else {
    if (field === 'is_extra_charge' && value) child.is_free = false
    if (field === 'is_free' && value) child.is_extra_charge = false
  }

  ;(Array.isArray(child.breakfast_details) ? child.breakfast_details : []).forEach(detail => {
    if (field === 'amount') {
      detail.amount = child.age_group === 'baby' ? 0 : amount
      return
    }

    detail[field] = value
    if (child.age_group === 'baby') {
      detail.is_free = true
      detail.is_extra_charge = false
    } else {
      if (field === 'is_extra_charge' && value) detail.is_free = false
      if (field === 'is_free' && value) detail.is_extra_charge = false
    }

    if (['breakfast', 'is_free', 'is_extra_charge'].includes(field)) {
      detail.amount = breakfastAmountForState(detail, child.age_group, prices)
    }
  })

  syncParentFromFirstDetail(child)
  return child
}

/**
 * Apply a daily-row edit. A direct amount edit survives the parent-row sync.
 */
export function applyDetailBreakfastField(child, detail, field, value, prices = {}) {
  if (!child || !detail) return detail

  const amount = field === 'amount'
    ? normalizeBreakfastAmount(value)
    : value

  detail[field] = amount

  if (child.age_group === 'baby') {
    detail.is_free = true
    detail.is_extra_charge = false
  } else {
    if (field === 'is_extra_charge' && value) detail.is_free = false
    if (field === 'is_free' && value) detail.is_extra_charge = false
  }

  if (field === 'amount') {
    detail.amount = child.age_group === 'baby' ? 0 : amount
  } else if (['breakfast', 'is_free', 'is_extra_charge'].includes(field)) {
    detail.amount = breakfastAmountForState(detail, child.age_group, prices)
  }

  syncParentFromFirstDetail(child)
  return detail
}

function syncParentFromFirstDetail(child) {
  const first = Array.isArray(child.breakfast_details)
    ? child.breakfast_details[0]
    : null
  if (!first) return

  child.amount = normalizeBreakfastAmount(first.amount)
  child.breakfast = !!first.breakfast
  child.is_free = !!first.is_free
  child.is_extra_charge = !!first.is_extra_charge
  child.is_room = !!first.is_room
}
