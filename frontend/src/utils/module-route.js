// Dòng 502: xác định module (bộ phận) của màn hình để điều hướng giữ đúng module
// và để các trang được KeepAlive giữ lại không phản ứng với route của module khác.

// Các tab ở /frontdesk do FrontDeskPage tự hiển thị, không đi qua RoomMapPage.
export const FRONTDESK_STANDALONE_TABS = Object.freeze(['checkout', 'day-close', 'reports', 'history', 'search'])

export function resolveModulePath(path) {
  const value = String(path || '')
  if (value.startsWith('/frontdesk')) return '/frontdesk'
  if (value.startsWith('/housekeeping')) return '/housekeeping'
  return '/reservation'
}

export function moduleContextFromPath(path) {
  return resolveModulePath(path).slice(1)
}

// Sơ đồ phòng chỉ nhận tab từ route khi route đang hiển thị chính nó.
// Bản đang ẩn trong cache (module khác, hoặc tab FrontDeskPage tự hiển thị) giữ nguyên tab cũ.
export function isRoomMapRouteOwned(ownerPath, routePath, tab) {
  if (resolveModulePath(routePath) !== ownerPath) return false
  if (ownerPath === '/frontdesk' && FRONTDESK_STANDALONE_TABS.includes(String(tab || ''))) return false
  return true
}
