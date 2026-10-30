// Dòng 406: nguồn duy nhất ánh xạ module / tab / màn hình cấu hình -> mã quyền.
// Router guard, menu trên cùng, menu phụ và các trang chủ module đều đọc từ đây
// để user không có quyền thì không thấy và không vào được màn hình.
// Mỗi giá trị là danh sách mã quyền, chỉ cần có 1 mã là được. 'admin' = vai trò quản trị.

// Mã cũ 'system.setting' vẫn được vào mọi màn hình cấu hình để không mất quyền đã cấp trước đây.
const withSetting = (codes) => [...codes, 'system.setting']

export const CONFIG_VIEW_PERMISSIONS = {
  hotel: withSetting(['config.hotel.view', 'config.shifts.view', 'config.services.view', 'config.markets.view']),
  room: withSetting(['config.room_classes.view', 'config.rooms.view']),
  system: withSetting(['config.system.view']),
  rate: withSetting(['config.rates.view']),
  'housekeeping-menu': withSetting(['config.housekeeping.view']),
  'design-template': withSetting(['config.template.view']),
  // Sao lưu & khôi phục database: chỉ tài khoản quản trị
  'database-backup': ['admin'],
}

export const CONFIG_ACCESS = [...new Set(Object.values(CONFIG_VIEW_PERMISSIONS).flat())]

export const FNB_OTHER_TAB_PERMISSIONS = {
  log: ['fb.view'],
  inventory: ['fb.menu.view', 'fb.menu.manage', 'hk.warehouse.view'],
  vat: ['fb.payment', 'fb.payment.view'],
  settings: ['fb.menu.manage', 'fb.overview.edit'],
}

export const FNB_OTHER_ACCESS = [...new Set(Object.values(FNB_OTHER_TAB_PERMISSIONS).flat())]

export const ROUTE_PERMISSIONS = {
  '/reservation': ['fo.booking.view'],
  '/frontdesk': ['fo.frontdesk.view'],
  '/housekeeping': ['hk.view'],
  '/reports': ['mgmt.report.view'],
  '/config': CONFIG_ACCESS,
  '/system': ['system.user.view'],
  '/fnb/restaurant': ['fb.view'],
  '/fnb/party': ['fb.party.view', 'fb.party.manage'],
  '/fnb/search': ['fb.order.view'],
  '/fnb/other': FNB_OTHER_ACCESS,
  '/fnb/report': ['mgmt.report.view'],
}

const PMS_MODULE_ROUTES = ['/reservation', '/frontdesk', '/housekeeping', '/reports', '/config']
const FNB_MODULE_ROUTES = ['/fnb/restaurant', '/fnb/party', '/fnb/search', '/fnb/report', '/fnb/other']
const unionOf = (paths) => [...new Set(paths.flatMap(p => ROUTE_PERMISSIONS[p]))]

ROUTE_PERMISSIONS['/pms'] = unionOf(PMS_MODULE_ROUTES)
ROUTE_PERMISSIONS['/fnb'] = unionOf(FNB_MODULE_ROUTES)

// Tab dùng chung của Đặt phòng / Lễ tân (cùng RoomMapPage). Tab không khai báo dùng quyền của module.
const FO_TAB_PERMISSIONS = {
  'create-res': ['fo.booking.view'],
  allotment: ['fo.allotment.view'],
  'allotment-detail': ['fo.allotment.view'],
  'allotment-report': ['fo.allotment.view'],
  'lock-room': ['fo.room_lock.view'],
  company: ['fo.company.view'],
  search: ['fo.guest.view'],
  customers: ['fo.guest.view'],
  'residence-declaration': ['fo.guest.view'],
  checkin: ['fo.checkin'],
  checkout: ['fo.payment.view', 'fo.bill.view', 'fo.checkout'],
  'day-close': ['fo.night_audit'],
  'split-old-services': ['fo.night_audit'],
  history: ['mgmt.activity_log'],
}

const HK_TAB_PERMISSIONS = {
  'lock-room': ['fo.room_lock.view', 'hk.room.status'],
  'print-tasks': ['hk.assign.view', 'hk.assign'],
  'add-service': ['hk.service.view', 'hk.service.bill'],
  minibar: ['hk.service.view', 'hk.service.bill'],
  laundry: ['hk.service.view', 'hk.service.bill'],
  compensation: ['hk.service.view', 'hk.service.bill'],
  'invoice-search': ['hk.service.view', 'hk.service.bill'],
  inventory: ['hk.warehouse.view'],
  'create-menu': ['hk.warehouse.manage', 'config.housekeeping.view'],
  'lost-found': ['hk.lost_found.view', 'hk.lost_found.manage'],
  history: ['mgmt.activity_log'],
  reports: ['hk.report.view', 'mgmt.report.view'],
}

export const TAB_PERMISSIONS = {
  '/reservation': FO_TAB_PERMISSIONS,
  '/frontdesk': FO_TAB_PERMISSIONS,
  '/housekeeping': HK_TAB_PERMISSIONS,
  '/fnb/other': FNB_OTHER_TAB_PERMISSIONS,
}

/** Danh sách quyền cần cho 1 màn hình (path + tab/view). Trả về [] nếu màn hình không giới hạn. */
export function screenPermissions(path, query = {}) {
  const codes = [...(ROUTE_PERMISSIONS[path] || [])]
  const tabCodes = TAB_PERMISSIONS[path]?.[query.tab]
  if (path === '/config' && query.view && CONFIG_VIEW_PERMISSIONS[query.view]) {
    return { route: codes, screen: CONFIG_VIEW_PERMISSIONS[query.view] }
  }
  return { route: codes, screen: tabCodes || [] }
}

/** auth: { isSuperAdmin, isAdmin, permissions } (authStore). */
export function hasAccess(auth, codes) {
  const list = Array.isArray(codes) ? codes : (codes ? [codes] : [])
  if (!list.length) return true
  if (auth.isSuperAdmin) return true
  if (list.includes('admin') && auth.isAdmin) return true
  const granted = auth.permissions || []
  return list.some(code => granted.includes(code))
}

export function canOpenScreen(auth, path, query = {}) {
  const { route, screen } = screenPermissions(path, query)
  return hasAccess(auth, route) && hasAccess(auth, screen)
}

function splitRoute(target, fallbackPath) {
  if (!target || target === '#') return { path: fallbackPath, query: {} }
  const [path, search = ''] = String(target).split('?')
  return { path: path || fallbackPath, query: Object.fromEntries(new URLSearchParams(search)) }
}

/**
 * Lọc menu (kể cả dropdown/children lồng nhau) theo quyền.
 * Mục con kế thừa đường dẫn của mục cha khi chỉ khai báo `tab`.
 * Mục cha có dropdown mà không còn mục con nào thì ẩn luôn.
 */
export function filterMenuByPermission(items, auth, parentPath = '') {
  return (items || []).reduce((result, item) => {
    // Menu báo cáo động đã được lọc theo quyền từng báo cáo ở backend;
    // user không được xem báo cáo nào thì ẩn luôn menu Báo cáo
    if (item.reportSlot) {
      if (item.dropdown?.length) result.push(item)
      return result
    }

    const { path, query } = splitRoute(item.route, parentPath)
    const screenQuery = { ...query, ...(item.tab ? { tab: item.tab } : {}) }
    const allowed = item.permission
      ? hasAccess(auth, item.permission)
      : canOpenScreen(auth, path, screenQuery)
    if (!allowed) return result

    const next = { ...item }
    for (const key of ['dropdown', 'children']) {
      if (!Array.isArray(item[key])) continue
      next[key] = filterMenuByPermission(item[key], auth, path)
      if (!next[key].length) return result
    }
    result.push(next)
    return result
  }, [])
}
