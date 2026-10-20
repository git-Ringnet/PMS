const CANCELLED_STATUS = 3

// Booking period after a quick room update (rows 393 + 432). Arrival only
// widens; departure widens too, but when the departure was edited it follows
// the latest departure of the rooms shown on the booking (status 3 ignored).
// Dates are YYYY-MM-DD strings; rooms are { checkIn, checkOut, status }.
export function bookingPeriodAfterQuickUpdate({ headerIn, headerOut, rooms = [], departureEdited = false }) {
  const checkIns = rooms.map(r => r.checkIn).filter(Boolean).sort()
  const checkOuts = rooms.map(r => r.checkOut).filter(Boolean).sort()
  if (checkIns.length === 0 || checkOuts.length === 0) return null

  const checkIn = headerIn && headerIn < checkIns[0] ? headerIn : checkIns[0]
  const roomsOut = checkOuts[checkOuts.length - 1]
  let checkOut = headerOut && headerOut > roomsOut ? headerOut : roomsOut

  if (departureEdited) {
    const shownOuts = rooms
      .filter(r => Number(r.status) !== CANCELLED_STATUS)
      .map(r => r.checkOut).filter(Boolean).sort()
    if (shownOuts.length > 0) {
      const shownOut = shownOuts[shownOuts.length - 1]
      checkOut = shownOut > checkIn ? shownOut : checkIn
    }
  }

  const diff = Math.round((new Date(checkOut) - new Date(checkIn)) / 86400000)
  return { checkIn, checkOut, nights: Number.isNaN(diff) ? null : Math.max(0, diff) }
}

// Row 433: a cancelled booking opens its registration info read-only.
export function isRegistrationReadonly(bookingStatus) {
  return Number(bookingStatus) === CANCELLED_STATUS
}
