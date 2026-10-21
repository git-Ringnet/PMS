const CHECKOUT_SELECTION_KEY = 'pms_checkout_selection_v1'

const LEGACY_SELECTION_KEYS = [
  'pms_checkout_last_booking_id',
  'pms_checkout_last_room_id',
  'pms_checkout_last_guest_id',
]

const VALID_REGISTER_SCOPES = new Set(['current', 'old', 'virtual'])

const normalizeId = (value) => {
  if (value === null || value === undefined || value === '') return null
  return String(value)
}

const normalizeContext = (context = {}) => ({
  branchId: normalizeId(context.branchId) || '',
  userId: normalizeId(context.userId) || '',
})

export function clearCheckoutSelection(storage) {
  if (!storage) return

  try {
    storage.removeItem(CHECKOUT_SELECTION_KEY)
    LEGACY_SELECTION_KEYS.forEach(key => storage.removeItem(key))
  } catch {
    // Storage có thể bị trình duyệt chặn; Checkout vẫn tiếp tục hoạt động không có restore.
  }
}

export function saveCheckoutSelection(storage, selection = {}, context = {}) {
  if (!storage) return null

  const bookingId = normalizeId(selection.bookingId)
  if (!bookingId) {
    clearCheckoutSelection(storage)
    return null
  }

  const normalizedContext = normalizeContext(context)
  const payload = {
    version: 1,
    bookingId,
    roomId: normalizeId(selection.roomId),
    guestId: normalizeId(selection.guestId),
    registerScope: VALID_REGISTER_SCOPES.has(selection.registerScope)
      ? selection.registerScope
      : null,
    ...normalizedContext,
  }

  try {
    storage.setItem(CHECKOUT_SELECTION_KEY, JSON.stringify(payload))
    LEGACY_SELECTION_KEYS.forEach(key => storage.removeItem(key))
    return payload
  } catch {
    return null
  }
}

export function loadCheckoutSelection(storage, context = {}) {
  if (!storage) return null

  let raw
  try {
    raw = storage.getItem(CHECKOUT_SELECTION_KEY)
  } catch {
    return null
  }

  if (!raw) return null

  try {
    const payload = JSON.parse(raw)
    const currentContext = normalizeContext(context)
    const storedContext = normalizeContext(payload)
    const bookingId = normalizeId(payload?.bookingId)

    if (
      payload?.version !== 1 ||
      !bookingId ||
      storedContext.branchId !== currentContext.branchId ||
      storedContext.userId !== currentContext.userId
    ) {
      clearCheckoutSelection(storage)
      return null
    }

    return {
      bookingId,
      roomId: normalizeId(payload.roomId),
      guestId: normalizeId(payload.guestId),
      registerScope: VALID_REGISTER_SCOPES.has(payload.registerScope)
        ? payload.registerScope
        : null,
    }
  } catch {
    clearCheckoutSelection(storage)
    return null
  }
}

