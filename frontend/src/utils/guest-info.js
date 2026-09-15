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
