export const GUEST_INFO_ROOM_STATUSES = Object.freeze([0, 1, 2, 4])

function normalizeCount(value, fallback = 0) {
  if (value === null || value === undefined || value === '') return fallback
  const count = Number(value)
  return Number.isFinite(count) && count >= 0 ? Math.trunc(count) : fallback
}

/**
 * Keep the guest-information view safe when an API response contains null
 * collections or numeric fields encoded as strings.
 */
export function normalizeGuestInfoGroups(value) {
  const groups = Array.isArray(value) ? value : []

  return groups
    .filter(group => {
      if (group?.status === undefined || group?.status === null) return true
      return GUEST_INFO_ROOM_STATUSES.includes(Number(group.status))
    })
    .map(group => {
      const guests = Array.isArray(group?.guests)
        ? group.guests.filter(Boolean)
        : []
      const children = Array.isArray(group?.children)
        ? group.children.filter(Boolean)
        : []
      const babies = children.filter(child => child.age_group === 'baby').length
      const olderChildren = children.filter(child => child.age_group === 'child').length

      return {
        ...group,
        guests,
        children,
        adults_count: normalizeCount(group?.adults_count, guests.length),
        babies_count: normalizeCount(group?.babies_count, babies),
        children_count: normalizeCount(group?.children_count, olderChildren),
      }
    })
}

/** Dòng 474: khách/trẻ em đã check out (status = 2) không được sửa, xóa. */
export function isCheckedOutPerson(person) {
  if (!person) return false
  return Number(person.stay_status ?? person.child_status ?? person.status) === 2
}

/**
 * Tách khách/trẻ em đã trả phòng ra khỏi phòng đang ở thành nhóm riêng (tình trạng Phòng đi).
 * Phòng đã check out toàn bộ (status = 2) giữ nguyên.
 */
export function splitCheckedOutPeople(rooms) {
  const result = []
  for (const room of Array.isArray(rooms) ? rooms : []) {
    if (Number(room?.status) === 2) {
      result.push(room)
      continue
    }
    const guests = room?.guests || []
    const children = room?.children || []
    const outGuests = guests.filter(isCheckedOutPerson)
    const outChildren = children.filter(isCheckedOutPerson)
    if (!outGuests.length && !outChildren.length) {
      result.push(room)
      continue
    }
    const activeGuests = guests.filter(g => !isCheckedOutPerson(g))
    const activeChildren = children.filter(c => !isCheckedOutPerson(c))
    if (activeGuests.length || activeChildren.length) {
      result.push({ ...room, guests: activeGuests, children: activeChildren })
    }
    result.push({ ...room, status: 2, guests: outGuests, children: outChildren, _checkedOutSplit: true })
  }
  return result
}
