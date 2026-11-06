/** Dòng 492: danh sách đưa đón của đăng ký. Không tự thêm dòng mặc định; rỗng thì giữ rỗng. */
export function normalizeShuttleInfo(list) {
  return Array.isArray(list) ? JSON.parse(JSON.stringify(list)) : []
}

/** Dòng 493: đường dẫn sang Lịch sử thao tác lọc theo đăng ký; null nếu đăng ký chưa lưu. */
export function buildBookingHistoryLocation(currentPath, dbId) {
  if (dbId === null || dbId === undefined || String(dbId).trim() === '') return null
  return { path: currentPath, query: { tab: 'history', registration_code: String(dbId) } }
}
