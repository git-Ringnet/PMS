// Module-level in-memory view state for LockRoomPage (Dòng 50)
// Giữ nguyên dữ liệu và trạng thái khi chuyển giữa các tab/màn hình trong SPA, không load lại gây bất tiện
let cachedRooms = null
let cachedHotelConfigs = null
let cachedSearchQuery = ''
let cachedStatusFilter = 'Tất cả trạng thái'
let cachedRoomTypeFilter = 'Tất cả loại phòng'
let cachedCollapsedFloors = {}
let cachedSelectedRowKeys = []
let cachedCurrentPage = 1

export function getLockRoomState() {
  return {
    rooms: cachedRooms ? [...cachedRooms] : null,
    hotelConfigs: cachedHotelConfigs ? [...cachedHotelConfigs] : null,
    searchQuery: cachedSearchQuery,
    statusFilter: cachedStatusFilter,
    roomTypeFilter: cachedRoomTypeFilter,
    collapsedFloors: { ...cachedCollapsedFloors },
    selectedRowKeys: [...cachedSelectedRowKeys],
    currentPage: cachedCurrentPage
  }
}

export function setLockRoomState(updates = {}) {
  if (updates.rooms !== undefined) cachedRooms = updates.rooms
  if (updates.hotelConfigs !== undefined) cachedHotelConfigs = updates.hotelConfigs
  if (updates.searchQuery !== undefined) cachedSearchQuery = updates.searchQuery
  if (updates.statusFilter !== undefined) cachedStatusFilter = updates.statusFilter
  if (updates.roomTypeFilter !== undefined) cachedRoomTypeFilter = updates.roomTypeFilter
  if (updates.collapsedFloors !== undefined) cachedCollapsedFloors = updates.collapsedFloors
  if (updates.selectedRowKeys !== undefined) cachedSelectedRowKeys = updates.selectedRowKeys
  if (updates.currentPage !== undefined) cachedCurrentPage = updates.currentPage
}

export function clearLockRoomState() {
  cachedRooms = null
  cachedHotelConfigs = null
  cachedSearchQuery = ''
  cachedStatusFilter = 'Tất cả trạng thái'
  cachedRoomTypeFilter = 'Tất cả loại phòng'
  cachedCollapsedFloors = {}
  cachedSelectedRowKeys = []
  cachedCurrentPage = 1
}
