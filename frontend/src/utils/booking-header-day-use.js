/**
 * A zero-night change made from the booking header is an explicit Day-use
 * instruction. This is deliberately separate from a per-room zero-night
 * edit, which legacy PMS keeps as a non-Day-use room until its toggle is on.
 */
export function isHeaderDayUseTransition({ nights, checkIn, checkOut } = {}) {
  return Number(nights) === 0
    && Boolean(checkIn)
    && checkIn === checkOut
}

/**
 * Keep only rooms controlled by the booking-header date synchronization in
 * step with its Day-use intent. Protected/history rooms are supplied through
 * the predicate and remain untouched.
 */
export function synchronizeHeaderDayUseRooms(rooms, isDayUse, isEligible = () => true) {
  ;(Array.isArray(rooms) ? rooms : []).forEach(room => {
    if (isEligible(room)) {
      room.hourly = Boolean(isDayUse)
    }
  })
}
