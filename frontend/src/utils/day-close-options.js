// Màn hình Sang ngày (TC_LT_20, task 511).

const STATUS_OPTIONS_KEY = 'pms_day_close_status_options'
const DEFAULT_STATUS_OPTIONS = { occupiedToDirty: true, emptyToInspect: true }

function storageKey(username) {
  return `${STATUS_OPTIONS_KEY}:${String(username || 'anonymous').toLowerCase()}`
}

/** Hai ô "chuyển tình trạng phòng khi sang ngày" giữ theo từng user (lưu ở trình duyệt). */
export function loadStatusOptions(storage, username) {
  try {
    const raw = storage?.getItem(storageKey(username))
    if (!raw) return { ...DEFAULT_STATUS_OPTIONS }
    const saved = JSON.parse(raw)
    return {
      occupiedToDirty: typeof saved.occupiedToDirty === 'boolean' ? saved.occupiedToDirty : DEFAULT_STATUS_OPTIONS.occupiedToDirty,
      emptyToInspect: typeof saved.emptyToInspect === 'boolean' ? saved.emptyToInspect : DEFAULT_STATUS_OPTIONS.emptyToInspect
    }
  } catch (e) {
    return { ...DEFAULT_STATUS_OPTIONS }
  }
}

export function saveStatusOptions(storage, username, options) {
  try {
    storage?.setItem(storageKey(username), JSON.stringify({
      occupiedToDirty: Boolean(options.occupiedToDirty),
      emptyToInspect: Boolean(options.emptyToInspect)
    }))
  } catch (e) {}
}

/** Tab "Phòng đang ở": phòng đang ở, KHÔNG lấy phòng có ngày đi = ngày hệ thống (các phòng này nằm ở tab Phòng đi). */
export function isInHouseForDayClose({ isCheckedIn, departureDate, systemDate }) {
  return Boolean(isCheckedIn) && departureDate !== systemDate
}

/** Thông báo khi trong ngày thực tế đã sang ngày rồi. */
export function alreadyRolledMessage(lastRolledAt) {
  const m = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/.exec(String(lastRolledAt || ''))
  const when = m ? `${m[4]}:${m[5]} ngày ${m[3]}/${m[2]}/${m[1]}` : 'hôm nay'
  return `Hệ thống đã phát hiện lần gần nhất được sang ngày vào lúc ${when}. Bạn có muốn tiếp tục sang ngày không?`
}

export const EXPECTED_REVENUE_REPORT_CODE = 'EXPECTED_ROOM_REVENUE_NIGHT_AUDIT'

/** Dòng 516: báo cáo dự kiến doanh thu mở ở tab Báo cáo của module đang xem, không chuyển sang /reports (module Quản lý). */
export function revenueReportRoute(currentPath) {
  const path = currentPath && currentPath !== '/reports' ? currentPath : '/frontdesk'
  return { path, query: { tab: 'reports', report: EXPECTED_REVENUE_REPORT_CODE } }
}
