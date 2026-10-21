// Row 400: vacant room numbers for the room-number dropdown.
//
// The vacant API excludes the row's own booking room, so its own room number
// counts as "vacant" for that row only. The cache key therefore includes the
// booking room id; otherwise another booking with the same class/dates would
// reuse the list and offer a room number that is already taken.
export function vacantRoomsKey({ roomClassId, checkIn, checkOut, bookingRoomId }) {
  return `${roomClassId}_${checkIn}_${checkOut}_${bookingRoomId || 'new'}`
}

// `store` is a ref-like object ({ value: {} }). A response is written only if
// it is the latest request for its key and no refresh started after it, so a
// slow, older response cannot bring back a room number that is now taken.
export function createVacantRoomsLoader(store, fetchList) {
  let generation = 0
  const latestRequest = {}
  const paramsByKey = {}

  async function load(key, params) {
    paramsByKey[key] = params
    const startedGeneration = generation
    const requestId = (latestRequest[key] || 0) + 1
    latestRequest[key] = requestId

    const list = await fetchList(params)
    if (startedGeneration !== generation || latestRequest[key] !== requestId) return false

    store.value = { ...store.value, [key]: list }
    return true
  }

  // Bookings changed somewhere: drop in-flight answers and reload every list
  // already shown, keeping the current options until the fresh ones arrive.
  function refresh() {
    generation++
    return Promise.all(Object.keys(paramsByKey).map(key =>
      load(key, paramsByKey[key]).catch(() => false)
    ))
  }

  return { load, refresh }
}
