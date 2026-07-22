<script setup>
import { ref, computed, onMounted, watch, onBeforeUnmount, nextTick } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useRoomStore } from '@/stores/room-store'
import { ROOM_STATUSES } from '@/services/room-service'
import { useUiStore } from '@/stores/ui-store'
import { useAuthStore } from '@/stores/auth-store'
import { lockRoomMove as apiLockRoomMove, unlockRoomMove as apiUnlockRoomMove, fetchSystemDate } from '@/services/booking-service'
import { t } from '@/utils/i18n'
import { TEXT_THEME } from '@/utils/theme'
import BookingDetailModal from '@/components/BookingDetailModal.vue'
import RoomIcon from '@/components/RoomIcon.vue'
import AvailableRoomsPage from './AvailableRoomsPage.vue'
import RoomPlanPage from './RoomPlanPage.vue'
import ShiftWorkPage from './ShiftWorkPage.vue'
import LockRoomPage from './LockRoomPage.vue'
import CompanySettingsPage from '@/pages/config/company/CompanySettingsPage.vue'
import LostAndFound from '@/pages/housekeeping/components/LostAndFound.vue'
import CreateRegistrationPage from './CreateRegistrationPage.vue'
import CheckInPage from './CheckInPage.vue'
import HelpGuidePopover from '@/components/HelpGuidePopover.vue'
import LoadingOverlay from '@/components/LoadingOverlay.vue'

const roomStore = useRoomStore()
const uiStore = useUiStore()
const authStore = useAuthStore()
const route = useRoute()
const router = useRouter()

const getQueryParam = (name) => {
  if (typeof window === 'undefined') return null
  const params = new URLSearchParams(window.location.search)
  return params.get(name)
}

const currentTab = computed(() => route.query.tab || getQueryParam('tab') || 'room-map')

const createRegRef = ref(null)
const showDetailModal = ref(false)
const showBookingDetailModal = ref(false)
const selectedBookingRoom = ref(null)
const showStatsModal = ref(false)
const isLoaded = ref(false)
const isInitialLoad = ref(true)
let pollingInterval = null
const isRoomPlanLoading = ref(false)

watch(currentTab, (newTab) => {
  if (newTab === 'room-plan') {
    isRoomPlanLoading.value = true
  } else {
    isRoomPlanLoading.value = false
  }
}, { immediate: true })

function handleMetricClick(status) {
  filterByStatus(status)
  isGridMode.value = false
}

function handleCurrentClick() {
  resetAllFilters()
  isGridMode.value = true
}
const showSearch = ref(false)
const showFilters = ref(false)
const showSettings = ref(false)

const settings = ref({
  iconSizes: {
    group1: 20,
    group2: 20,
    group3: 10,
    group4: 16,
    group5: 20,
  },
  exactPosition: false,
  floorOrientation: 'Ngang',
  roomWidth: 200,
  roomHeight: 110
})

watch(() => authStore.settings?.room_map, (newRoomMapSettings) => {
  if (newRoomMapSettings) {
    settings.value = {
      iconSizes: {
        group1: parseInt(newRoomMapSettings.iconSizes?.group1 ?? 20),
        group2: parseInt(newRoomMapSettings.iconSizes?.group2 ?? 20),
        group3: parseInt(newRoomMapSettings.iconSizes?.group3 ?? 10),
        group4: parseInt(newRoomMapSettings.iconSizes?.group4 ?? 16),
        group5: parseInt(newRoomMapSettings.iconSizes?.group5 ?? 20),
      },
      exactPosition: newRoomMapSettings.exactPosition === true || newRoomMapSettings.exactPosition === 'true',
      floorOrientation: newRoomMapSettings.floorOrientation || 'Ngang',
      roomWidth: parseInt(newRoomMapSettings.roomWidth ?? 200),
      roomHeight: parseInt(newRoomMapSettings.roomHeight ?? 110)
    }
  }
}, { immediate: true, deep: true })

const cardScale = computed(() => {
  const widthScale = settings.value.roomWidth / 200
  const heightScale = settings.value.roomHeight / 110
  return Math.min(widthScale, heightScale)
})

function saveSettings() {
  authStore.updateUserSettings({
    room_map: {
      iconSizes: {
        group1: settings.value.iconSizes.group1,
        group2: settings.value.iconSizes.group2,
        group3: settings.value.iconSizes.group3,
        group4: settings.value.iconSizes.group4,
        group5: settings.value.iconSizes.group5,
      },
      exactPosition: settings.value.exactPosition,
      floorOrientation: settings.value.floorOrientation,
      roomWidth: settings.value.roomWidth,
      roomHeight: settings.value.roomHeight
    }
  })
  showSettings.value = false
  uiStore.showToast('Cài đặt hiển thị đã được lưu thành công!', 'success')
}

function handleEditBookingFromPlan({ code, id }) {
  // Đảm bảo tab không nằm trong danh sách đóng (closed list) trong localStorage
  const closedKey = 'pms_closed_tabs'
  const closedStr = localStorage.getItem(closedKey)
  let closedList = []
  if (closedStr !== null) {
    try {
      closedList = JSON.parse(closedStr) || []
    } catch (e) {
      closedList = []
    }
  }
  // Loại bỏ id booking này khỏi closed list và lưu lại
  const updatedClosed = closedList.filter(x => String(x) !== String(id) && String(x) !== String(code))
  localStorage.setItem(closedKey, JSON.stringify(updatedClosed))

  router.push({ query: { ...route.query, tab: 'create-res', bookingCode: code } })

  if (createRegRef.value && typeof createRegRef.value.openBookingModalByCode === 'function') {
    createRegRef.value.openBookingModalByCode(code)
  }
}

function resetToDefaultSettings() {
  const defaultRoomMap = {
    iconSizes: { group1: 20, group2: 20, group3: 10, group4: 16, group5: 20 },
    exactPosition: false,
    floorOrientation: 'Ngang',
    roomWidth: 200,
    roomHeight: 110
  }
  settings.value = JSON.parse(JSON.stringify(defaultRoomMap))
  authStore.updateUserSettings({ room_map: defaultRoomMap })
  calculateScale()
  uiStore.showToast('Đã khôi phục cài đặt hiển thị mặc định!', 'success')
}

function handleClickOutsideSettings(event) {
  const settingsBtn = document.querySelector('.settings-btn-trigger')
  const settingsPopover = document.querySelector('.settings-popover-panel')
  if (showSettings.value && settingsPopover && !settingsPopover.contains(event.target) && settingsBtn && !settingsBtn.contains(event.target)) {
    showSettings.value = false
  }
}

// Top toggle state: isFuture (false = Hiện tại, true = Tương Lai)
const isFuture = ref(false)
const rawDate = ref(new Date().toISOString().split('T')[0])

// Bottom toggle state: isGridMode (true = Bảng, false = Lưới)
const isGridMode = ref(true)

// Auto scale / zoom layout state
const autoScale = ref(true)
const manualScale = ref(1.0)
const scaleFactor = ref(1.0)

watch(() => authStore.settings?.room_map, (newRoomMapSettings) => {
  if (newRoomMapSettings) {
    if (newRoomMapSettings.autoScale !== undefined) {
      autoScale.value = newRoomMapSettings.autoScale !== false && newRoomMapSettings.autoScale !== 'false'
    }
    if (newRoomMapSettings.scale !== undefined) {
      manualScale.value = parseFloat(newRoomMapSettings.scale ?? 1.0)
    }
    calculateScale()
  }
}, { immediate: true, deep: true })

function calculateScale() {
  if (autoScale.value) {
    const width = window.innerWidth
    if (width < 1440) {
      scaleFactor.value = Math.max(0.78, width / 1440)
    } else {
      scaleFactor.value = 1.0
    }
  } else {
    scaleFactor.value = manualScale.value
  }
}

function toggleAutoScale() {
  autoScale.value = !autoScale.value
  authStore.updateUserSettings({
    room_map: {
      autoScale: autoScale.value
    }
  })
  calculateScale()
}

function adjustManualScale(direction) {
  if (direction === 'in') {
    manualScale.value = Math.min(1.2, manualScale.value + 0.05)
  } else if (direction === 'out') {
    manualScale.value = Math.max(0.7, manualScale.value - 0.05)
  } else {
    manualScale.value = 1.0
  }
  authStore.updateUserSettings({
    room_map: {
      scale: manualScale.value
    }
  })
  calculateScale()
}

function formatDate(date) {
  const d = new Date(date)
  return `${String(d.getDate()).padStart(2, '0')}/${String(d.getMonth() + 1).padStart(2, '0')}/${d.getFullYear()}`
}

const selectedDate = computed(() => {
  return formatDate(new Date(rawDate.value))
})

watch(isFuture, (newVal) => {
  if (!newVal) {
    rawDate.value = new Date().toISOString().split('T')[0]
  }
})

watch(rawDate, async () => {
  await Promise.all([
    roomStore.fetchRooms({ silent: true }),
    roomStore.fetchStats()
  ])
})

// Fetch 1 lần khi chuyển vào tab Room Map → dữ liệu luôn mới sau khi giao phòng
watch(currentTab, async (newTab) => {
  if (newTab === 'room-map') {
    await Promise.all([
      roomStore.fetchRooms({ silent: true }),
      roomStore.fetchStats()
    ])
  }
})

// Circular widgets stats computed dynamically
const checkinStats = computed(() => {
  const count = roomStore.rooms.filter(r => r.booking_status === 'reserved').length
  return `${count}/23`
})

const checkoutStats = computed(() => {
  const count = roomStore.rooms.filter(r => r.booking_status === 'checkout').length
  return `${count}/31`
})

const occupiedStats = computed(() => {
  const count = roomStore.rooms.filter(r => r.booking_status === 'occupied' || r.booking_status === 'checkout').length
  return `${count}/${roomStore.rooms.length || 181}`
})

const occupancyRateStats = computed(() => {
  return `${roomStore.occupancyRate}%`
})

// Sorted Floors list
const sortedFloors = computed(() => {
  const floors = Object.keys(roomStore.roomsByFloor)
    .map(Number)
    .sort((a, b) => a - b)
  return floors
})

const activeFilter = computed(() => roomStore.filters.status)

// Methods
function handleRoomClick(room) {
  // Click actions on rooms are disabled as per user request to not show room detail modal.
}

const hoverTooltip = ref({
  show: false,
  x: 0,
  y: 0,
  isBelow: false,
  room: null,
})

let tooltipTimeout = null

function showTooltip(event, room) {
  if (!room || (room.booking_status !== 'occupied' && room.booking_status !== 'reserved' && room.booking_status !== 'checkout')) return
  
  if (tooltipTimeout) {
    clearTimeout(tooltipTimeout)
    tooltipTimeout = null
  }

  const rect = event.currentTarget.getBoundingClientRect()
  const tooltipHeight = 280
  const isBelow = rect.top < tooltipHeight

  hoverTooltip.value = {
    show: true,
    x: rect.left + rect.width / 2,
    y: isBelow ? rect.bottom + 8 : rect.top - 8,
    isBelow: isBelow,
    room: room
  }
}

function hideTooltip() {
  if (tooltipTimeout) clearTimeout(tooltipTimeout)
  tooltipTimeout = setTimeout(() => {
    hoverTooltip.value.show = false
  }, 250)
}

function cancelHide() {
  if (tooltipTimeout) {
    clearTimeout(tooltipTimeout)
    tooltipTimeout = null
  }
}

function formatTooltipDate(dateStr) {
  if (!dateStr) return ''
  const parts = dateStr.split('-')
  if (parts.length === 3) {
    return `${parts[2]}/${parts[1]}`
  }
  return dateStr
}

function formatTooltipPrice(price) {
  const num = Math.round(Number(price) || 0)
  return num.toLocaleString('en-US') + 'đ'
}

function closeModal() {
  showDetailModal.value = false
  roomStore.clearSelectedRoom()
}

function closeBookingDetailModal() {
  showBookingDetailModal.value = false
  selectedBookingRoom.value = null
}
 
async function refreshRoomMapAfterGuestChange() {
  await roomStore.fetchRooms({ silent: true })
}

// Checkbox helper for filters
function toggleStatusFilter(status) {
  if (roomStore.filters.status === status) {
    roomStore.setFilter('status', null)
  } else {
    roomStore.setFilter('status', status)
  }
}

function filterByStatus(status) {
  if (roomStore.filters.status === status) {
    roomStore.setFilter('status', null)
  } else {
    roomStore.setFilter('status', status)
  }
}

function resetAllFilters() {
  roomStore.resetFilters()
}

// Show Warning toast for features under development
function showDevelopmentToast(featureName) {
  const isEn = t('roomMap.filterTitle') === 'Filters'
  const msg = isEn ? `Feature "${featureName}" is under development!` : `Tính năng "${featureName}" đang được phát triển!`
  uiStore.showToast(msg, 'warning')
}

// Logic for status dots (Top Left - Green / Top Right - Red)
function hasArrivalToday(room) {
  return room.booking_status === 'reserved'
}

function hasDepartureToday(room) {
  return room.booking_status === 'checkout'
}

function getGuestCount(room) {
  if (room.booking_status === 'occupied' || room.booking_status === 'checkout') {
    return room.max_guests || 2
  }
  return 0
}

function hasExtraBed(room) {
  return false
}

// Room number red color warning rule
function isRoomNumberRed(room) {
  if (room.status === ROOM_STATUSES.MAINTENANCE) return false
  return room.booking_status === 'checkout' || !!room.has_issue
}

// Show Broom icon for dirty rooms
function shouldShowBroom(room) {
  return room.status === ROOM_STATUSES.DIRTY || !room.is_clean
}

// Show Sparkles icon for clean vacant rooms
function shouldShowSparkles(room) {
  const hasBooking = !!room.guest_name || !!room.booking_code || !!room.booking_status
  return !!room.is_clean && (room.status === ROOM_STATUSES.AVAILABLE || !room.status) && !hasBooking
}

function getRoomStatusIconName(room) {
  if (!room) return 'double-check'
  if (room.status === ROOM_STATUSES.MAINTENANCE || room.status === 'ooo' || room.status === 'oos') {
    return room.lock_type === 'OOS' ? 'oos' : 'ooo'
  }
  const hasInhouseGuest = room.booking_status === 'occupied' || room.booking_status === 'checkout' || room.status === ROOM_STATUSES.DIRTY || room.status === ROOM_STATUSES.CHECKOUT || !room.is_clean
  if (hasInhouseGuest) {
    return 'dirty'
  }
  const hasBookingToday = !!room.guest_name || !!room.booking_code || !!room.booking_status
  if (hasBookingToday) {
    return 'double-check'
  }
  if (room.is_clean && (room.status === ROOM_STATUSES.AVAILABLE || !room.status)) {
    return 'clean'
  }
  if (room.status === ROOM_STATUSES.RESERVED) {
    return 'priority'
  }
  return 'double-check'
}

function getStatusIconSize(room) {
  let size = 20
  if (room.status === ROOM_STATUSES.MAINTENANCE) {
    size = settings.value.iconSizes.group1
  } else if (room.status === ROOM_STATUSES.DIRTY || room.status === ROOM_STATUSES.CHECKOUT || !room.is_clean) {
    size = settings.value.iconSizes.group2
  } else if (shouldShowSparkles(room)) {
    size = settings.value.iconSizes.group2
  } else if (room.status === ROOM_STATUSES.AVAILABLE) {
    size = settings.value.iconSizes.group2
  } else if (room.status === ROOM_STATUSES.RESERVED) {
    size = settings.value.iconSizes.group5
  } else if (room.status === ROOM_STATUSES.OCCUPIED) {
    size = settings.value.iconSizes.group2
  }
  return Math.round(size * cardScale.value)
}

function isLightColor(color) {
  if (!color) return true
  const hex = color.replace('#', '')
  if (hex.length < 6) return true
  const r = parseInt(hex.substring(0, 2), 16)
  const g = parseInt(hex.substring(2, 4), 16)
  const b = parseInt(hex.substring(4, 6), 16)
  const brightness = (r * 299 + g * 587 + b * 114) / 1000
  return brightness > 155
}

function getRoomCardStyle(room, floorIdx, roomIdx) {
  const isOccupiedOrCheckout = room.booking_status === 'occupied' || room.booking_status === 'checkout'
  const hasBkColor = isOccupiedOrCheckout && room.booking_color && room.booking_color !== ''

  const baseStyle = {
    animationDelay: `${(floorIdx * 80) + (roomIdx * 20)}ms`,
    width: settings.value.roomWidth + 'px',
    height: settings.value.roomHeight + 'px',
    minHeight: settings.value.roomHeight + 'px',
    maxHeight: settings.value.roomHeight + 'px'
  }

  if (hasBkColor) {
    const textColor = '#1e293b'
    return {
      ...baseStyle,
      background: room.booking_color,
      backgroundImage: 'none',
      borderColor: room.booking_color,
      color: textColor
    }
  }

  return baseStyle
}

// Guest names list matching image 2
function getMockGuestName(room) {
  return room.guest_name || ''
}

// Booking ID generator
function getMockRegId(room) {
  return room.booking_code || ''
}

// Booking details/registration name matching image 2
function getMockRegName(room) {
  return room.booking_name || ''
}

// Company list matching image 2
function getMockCompany(room) {
  return room.company_name || ''
}

// Room shape description
function getRoomTypeShape(room) {
  const type = room.room_type || room.room_class?.code || '';
  if (type.includes('D') || type.includes('Double')) return 'Double'
  if (type.includes('TB') || type.includes('Twin')) return 'Twin'
  if (type.includes('TR') || type.includes('Triple')) return 'Triple'
  return 'Family'
}

// Date formatter for columns
function formatDateShort(dateStr) {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  return `${String(d.getDate()).padStart(2, '0')}-${String(d.getMonth() + 1).padStart(2, '0')}-${d.getFullYear()}`
}

// Context Menu State
const contextMenu = ref({
  show: false,
  x: 0,
  y: 0,
  room: null,
  isLeft: false,
})

const statusItems = [
  { key: ROOM_STATUSES.AVAILABLE, label: 'Sẵn sàng' },
  { key: ROOM_STATUSES.DIRTY, label: 'Phòng bẩn' },
  { key: ROOM_STATUSES.CHECKOUT, label: 'Lau dọn' },
  { key: ROOM_STATUSES.MAINTENANCE, label: 'Dịch vụ dọn phòng' },
  { key: ROOM_STATUSES.RESERVED, label: 'Phòng ưu tiên' },
  { key: ROOM_STATUSES.OCCUPIED, label: 'Phòng không làm phiền' },
]

function handleContextMenu(event, room) {
  event.preventDefault()
  
  const menuWidth = 220
  const menuHeight = 340 // Safe height estimation of context menu
  
  let x = event.clientX
  let y = event.clientY
  let isBottom = false
  
  // Shift left if menu would overflow the right edge
  if (x + menuWidth > window.innerWidth) {
    x = window.innerWidth - menuWidth - 8
  }
  
  // Shift up if menu would overflow the bottom edge
  if (y + menuHeight > window.innerHeight) {
    y = event.clientY - menuHeight
    if (y < 8) y = 8
    isBottom = true
  }
  
  const isLeft = event.clientX > window.innerWidth - 460
  contextMenu.value = {
    show: true,
    x: x,
    y: y,
    room: room,
    isLeft: isLeft,
    isBottom: isBottom,
  }
}

function closeContextMenu() {
  contextMenu.value.show = false
}

// Show room info modal
function showRoomInfo(room) {
  if (room.booking_code) {
    selectedBookingRoom.value = room
    showBookingDetailModal.value = true
  }
  closeContextMenu()
}

// Trigger context menu action and link pages/features
function triggerMenuItem(actionName) {
  if (['Đăng ký', 'Hóa đơn', 'Nhóm hóa đơn', 'Chuyển Phòng', 'In phiếu ăn sáng', 'In mẫu đăng ký'].includes(actionName)) {
    if (contextMenu.value.room && contextMenu.value.room.booking_code) {
      router.push({
        query: {
          ...route.query,
          tab: 'create-res',
          bookingCode: contextMenu.value.room.booking_code
        }
      })
      uiStore.showToast(`Chuyển đến Phiếu Đăng ký ${contextMenu.value.room.booking_code} để thực hiện "${actionName}"`, 'success')
      closeContextMenu()
      return
    } else {
      uiStore.showToast('Phòng chưa được giao hoặc không có mã đăng ký!', 'warning')
      closeContextMenu()
      return
    }
  }

  const isEn = t('roomMap.filterTitle') === 'Filters'
  const msg = isEn ? `Feature "${actionName}" is under development!` : `Tính năng "${actionName}" đang được phát triển!`
  uiStore.showToast(msg, 'warning')
  closeContextMenu()
}

async function lockRoomMove(room) {
  closeContextMenu()
  if (!room || !room.booking_code || !room.booking_room_id || !room.booking_id) {
    uiStore.showToast('Phòng chưa được gán hoặc không tìm thấy mã đặt phòng!', 'warning')
    return
  }

  const confirmed = await uiStore.confirm({
    title: 'Khóa chuyển phòng',
    message: `Bạn có chắc chắn muốn khóa chuyển phòng ${room.room_number}?`,
    confirmText: 'Đồng ý',
    cancelText: 'Hủy bỏ'
  })

  if (confirmed) {
    try {
      await apiLockRoomMove(room.booking_id, room.booking_room_id)
      uiStore.showToast(`Đã khóa chuyển phòng ${room.room_number} thành công!`, 'success')
      await roomStore.fetchRooms({ silent: true })
    } catch (err) {
      const msg = err.response?.data?.message || 'Không thể khóa chuyển phòng.'
      uiStore.showToast(msg, 'error')
    }
  }
}

async function unlockRoomMove(room) {
  closeContextMenu()
  if (!room || !room.booking_code || !room.booking_room_id || !room.booking_id) {
    uiStore.showToast('Phòng chưa được gán hoặc không tìm thấy mã đặt phòng!', 'warning')
    return
  }

  const confirmed = await uiStore.confirm({
    title: 'Mở khóa chuyển phòng',
    message: `Bạn có chắc chắn muốn mở khóa chuyển phòng ${room.room_number}?`,
    confirmText: 'Đồng ý',
    cancelText: 'Hủy bỏ'
  })

  if (confirmed) {
    try {
      await apiUnlockRoomMove(room.booking_id, room.booking_room_id)
      uiStore.showToast(`Đã mở khóa chuyển phòng ${room.room_number} thành công!`, 'success')
      await roomStore.fetchRooms({ silent: true })
    } catch (err) {
      const msg = err.response?.data?.message || 'Không thể mở khóa chuyển phòng.'
      uiStore.showToast(msg, 'error')
    }
  }
}

// Change room status directly from context menu
async function changeRoomStatus(room, newStatus, lockType = null) {
  if (!room || (newStatus === room.status && lockType === room.lock_type)) return
  
  let statusKey = 'available'
  if (newStatus === ROOM_STATUSES.DIRTY) statusKey = 'dirty'
  else if (newStatus === ROOM_STATUSES.CHECKOUT) statusKey = 'cleaning'
  else if (newStatus === ROOM_STATUSES.MAINTENANCE) {
    statusKey = lockType ? (lockType === 'OOS' ? 'lockOos' : 'lockOoo') : 'maintenance'
  }
  else if (newStatus === ROOM_STATUSES.RESERVED) statusKey = 'priorityRoom'
  else if (newStatus === ROOM_STATUSES.OCCUPIED) statusKey = 'dnd'
  
  const statusLabel = t(`roomMap.${statusKey}`)
  
  // Close context menu BEFORE showing confirm dialog
  closeContextMenu()
  
  const confirmed = await uiStore.confirm({
    title: t('roomMap.changeStatusTitle'),
    message: t('roomMap.changeStatusConfirm', { room: room.room_number, status: statusLabel }),
    confirmText: t('roomMap.filterTitle') === 'Filters' ? 'Agree' : 'Đồng ý',
    cancelText: t('roomMap.filterTitle') === 'Filters' ? 'Cancel' : 'Hủy bỏ'
  })

  if (confirmed) {
    try {
      await roomStore.updateRoomStatus(room.id, newStatus, lockType)
      uiStore.showToast(t('roomMap.changeStatusSuccess', { room: room.room_number, status: statusLabel }), 'success')
    } catch (err) {
      uiStore.showToast(t('roomMap.changeStatusError'), 'error')
    }
  }
}

onMounted(async () => {
  // Fetch system date and set rawDate
  try {
    const dateRes = await fetchSystemDate()
    if (dateRes?.data?.success && dateRes?.data?.data?.system_date) {
      rawDate.value = dateRes.data.data.system_date
    }
  } catch (err) {
    console.error('Lỗi khi tải ngày hệ thống cho sơ đồ phòng:', err)
  }

  // Migration to set default roomWidth to 200px for existing local storage sessions
  if (localStorage.getItem('pms_room_width_migrated_200') !== 'true') {
    settings.value.roomWidth = 200
    localStorage.setItem('pms_room_width', '200')
    localStorage.setItem('pms_room_width_migrated_200', 'true')
  }

  // Run data fetches in parallel to minimize load latency
  await Promise.all([
    roomStore.fetchRooms(),
    roomStore.fetchStats()
  ])
  isLoaded.value = true
  isInitialLoad.value = false
  
  window.addEventListener('click', closeContextMenu)
  window.addEventListener('click', handleClickOutsideSettings)
  
  calculateScale()
  window.addEventListener('resize', calculateScale)
})

onBeforeUnmount(() => {
  window.removeEventListener('click', closeContextMenu)
  window.removeEventListener('click', handleClickOutsideSettings)
  window.removeEventListener('resize', calculateScale)
})

watch(() => contextMenu.value.show, (newVal) => {
  if (newVal) {
    window.addEventListener('scroll', closeContextMenu, { passive: true })
  } else {
    window.removeEventListener('scroll', closeContextMenu)
  }
})

const uniqueRoomTypes = computed(() => {
  const types = new Set(roomStore.rooms.map(r => r.room_type || r.room_class?.code).filter(Boolean))
  return [...types].sort()
})

const uniqueFloors = computed(() => {
  const floors = new Set(roomStore.rooms.map(r => r.floor).filter(Boolean))
  return [...floors].sort((a, b) => a - b)
})
</script>

<template>
  <div class="flex h-full w-full overflow-hidden bg-white">
    <!-- Main Content Area Wrapper -->
    <div class="flex-1 flex flex-col min-h-0 min-w-0 bg-white" :style="{ zoom: scaleFactor }">
      
      <!-- TOP HORIZONTAL METRICS BAR (Only displayed for Room Map tab) -->
      <div v-if="currentTab === 'room-map'" class="relative bg-white border-b border-slate-200 px-6 py-3 shrink-0 flex items-center justify-between gap-4 select-none">
        <div class="flex items-center gap-3 overflow-x-auto px-1.5 py-1 scrollbar-thin">
          <!-- Date card -->
          <button @click="handleCurrentClick"
            class="bg-white border hover:border-slate-300 rounded-xl px-4 py-2.5 flex items-center gap-3 shadow-xs shrink-0 cursor-pointer text-left transition-all hover:shadow-md hover:-translate-y-0.5 transform-gpu border-slate-200/80">
            <div class="w-8 h-8 rounded-lg bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-500 shrink-0">
              <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect x="3" y="6" width="18" height="15" rx="2" fill="#F8FAFC" stroke="#6366F1" stroke-width="2"/>
                <path d="M3 6C3 4.89543 3.89543 4 5 4H19C20.1046 4 21 4.89543 21 6V9H3V6Z" fill="#EE4444"/>
                <path d="M8 2V5" stroke="#475569" stroke-width="2" stroke-linecap="round"/>
                <path d="M16 2V5" stroke="#475569" stroke-width="2" stroke-linecap="round"/>
                <circle cx="7" cy="13" r="1" fill="#6366F1"/>
                <circle cx="12" cy="13" r="1" fill="#6366F1"/>
                <circle cx="17" cy="13" r="1" fill="#6366F1"/>
                <circle cx="7" cy="17" r="1" fill="#6366F1"/>
                <circle cx="12" cy="17" r="1" fill="#6366F1"/>
                <circle cx="17" cy="17" r="1" fill="#6366F1"/>
              </svg>
            </div>
            <div class="flex flex-col">
              <span class="text-[12px] leading-tight" :class="TEXT_THEME.statsValue">{{ selectedDate }}</span>
              <span class="text-[10px] uppercase mt-0.5" :class="TEXT_THEME.statsLabel">{{ t('roomMap.current') }}</span>
            </div>
          </button>
 
          <!-- Đã đến card -->
          <button @click="handleMetricClick(ROOM_STATUSES.RESERVED)" 
            class="bg-white border hover:border-slate-300 rounded-xl px-4 py-2.5 flex items-center gap-3 shadow-xs shrink-0 cursor-pointer text-left transition-all hover:shadow-md hover:-translate-y-0.5 transform-gpu"
            :class="activeFilter === ROOM_STATUSES.RESERVED ? 'ring-2 ring-inset ring-[#97d5ff] border-[#97d5ff] bg-[#97d5ff]/5' : 'border-slate-200/80'">
            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-emerald-50 to-emerald-100/60 border border-emerald-200/50 flex items-center justify-center text-emerald-600 shadow-xs shrink-0">
              <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M19 21H5C3.89543 21 3 20.1046 3 19V5C3 3.89543 3.89543 3 5 3H19C20.1046 3 21 3.89543 21 5V19C21 20.1046 20.1046 21 19 21Z" fill="#F0FDF4" stroke="#22C55E" stroke-width="2"/>
                <path d="M10 17H6V7H10" stroke="#16A34A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M18 12H9" stroke="#16A34A" stroke-width="2" stroke-linecap="round"/>
                <path d="M12 9L9 12L12 15" stroke="#16A34A" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </div>
            <div class="flex flex-col">
              <span class="text-[10px] uppercase leading-tight" :class="TEXT_THEME.statsLabel">{{ t('roomMap.arrivals') }}</span>
              <span class="text-[13px] mt-0.5" :class="TEXT_THEME.statsValue">{{ checkinStats }}</span>
            </div>
          </button>
 
          <!-- Đã đi card -->
          <button @click="handleMetricClick(ROOM_STATUSES.CHECKOUT)" 
            class="bg-white border hover:border-slate-300 rounded-xl px-4 py-2.5 flex items-center gap-3 shadow-xs shrink-0 cursor-pointer text-left transition-all hover:shadow-md hover:-translate-y-0.5 transform-gpu"
            :class="activeFilter === ROOM_STATUSES.CHECKOUT ? 'ring-2 ring-inset ring-[#97d5ff] border-[#97d5ff] bg-[#97d5ff]/5' : 'border-slate-200/80'">
            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-rose-50 to-rose-100/60 border border-rose-200/50 flex items-center justify-center text-rose-500 shadow-xs shrink-0">
              <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M19 21H5C3.89543 21 3 20.1046 3 19V5C3 3.89543 3.89543 3 5 3H19C20.1046 3 21 3.89543 21 5V19C21 20.1046 20.1046 21 19 21Z" fill="#FEF2F2" stroke="#EF4444" stroke-width="2"/>
                <path d="M6 17H10V7H6" stroke="#DC2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M11 12H20" stroke="#DC2626" stroke-width="2" stroke-linecap="round"/>
                <path d="M17 9L20 12L17 15" stroke="#DC2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
              </svg>
            </div>
            <div class="flex flex-col">
              <span class="text-[10px] uppercase leading-tight" :class="TEXT_THEME.statsLabel">{{ t('roomMap.departures') }}</span>
              <span class="text-[13px] mt-0.5" :class="TEXT_THEME.statsValue">{{ checkoutStats }}</span>
            </div>
          </button>
 
          <!-- Đang ở card -->
          <button @click="handleMetricClick(ROOM_STATUSES.OCCUPIED)" 
            class="bg-white border hover:border-slate-300 rounded-xl px-4 py-2.5 flex items-center gap-3 shadow-xs shrink-0 cursor-pointer text-left transition-all hover:shadow-md hover:-translate-y-0.5 transform-gpu"
            :class="activeFilter === ROOM_STATUSES.OCCUPIED ? 'ring-2 ring-inset ring-[#97d5ff] border-[#97d5ff] bg-[#97d5ff]/5' : 'border-slate-200/80'">
            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-sky-50 to-sky-100/80 border border-sky-200/50 flex items-center justify-center text-sky-600 shadow-xs shrink-0">
              <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M2 20V5H4V14H20V5H22V20H20V17H4V20H2C2 20 2 20 2 20Z" fill="#3B82F6"/>
                <rect x="5" y="10" width="14" height="4" rx="1" fill="#93C5FD"/>
                <rect x="6" y="7" width="4" height="2.5" rx="0.5" fill="#1D4ED8"/>
                <rect x="14" y="7" width="4" height="2.5" rx="0.5" fill="#1D4ED8"/>
              </svg>
            </div>
            <div class="flex flex-col">
              <span class="text-[10px] uppercase leading-tight" :class="TEXT_THEME.statsLabel">{{ t('roomMap.occupied') }}</span>
              <span class="text-[13px] mt-0.5" :class="TEXT_THEME.statsValue">{{ occupiedStats }}</span>
            </div>
          </button>
 
          <!-- Khóa OOO card -->
          <button @click="handleMetricClick('OOO')" 
            class="bg-white border hover:border-slate-300 rounded-xl px-4 py-2.5 flex items-center gap-3 shadow-xs shrink-0 cursor-pointer text-left transition-all hover:shadow-md hover:-translate-y-0.5 transform-gpu"
            :class="activeFilter === 'OOO' ? 'ring-2 ring-inset ring-[#97d5ff] border-[#97d5ff] bg-[#97d5ff]/5' : 'border-slate-200/80'">
            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-amber-50 to-amber-100/60 border border-amber-200/50 flex items-center justify-center shadow-xs shrink-0">
              <RoomIcon name="ooo" class="w-5 h-5" />
            </div>
            <div class="flex flex-col">
              <span class="text-[10px] uppercase leading-tight" :class="TEXT_THEME.statsLabel">{{ t('roomMap.lockOoo') }}</span>
              <span class="text-[13px] mt-0.5" :class="TEXT_THEME.statsValue">{{ roomStore.rooms.filter(r => r.status === ROOM_STATUSES.MAINTENANCE && r.lock_type !== 'OOS').length }}</span>
            </div>
          </button>
 
          <!-- Khóa OOS card -->
          <button @click="handleMetricClick('OOS')" 
            class="bg-white border hover:border-slate-300 rounded-xl px-4 py-2.5 flex items-center gap-3 shadow-xs shrink-0 cursor-pointer text-left transition-all hover:shadow-md hover:-translate-y-0.5 transform-gpu"
            :class="activeFilter === 'OOS' ? 'ring-2 ring-inset ring-[#97d5ff] border-[#97d5ff] bg-[#97d5ff]/5' : 'border-slate-200/80'">
            <div class="w-8 h-8 rounded-lg bg-gradient-to-br from-emerald-50 to-emerald-100/60 border border-emerald-200/40 flex items-center justify-center text-emerald-600 shadow-xs shrink-0">
              <RoomIcon name="oos" class="w-5 h-5 text-emerald-600" />
            </div>
            <div class="flex flex-col">
              <span class="text-[10px] uppercase leading-tight" :class="TEXT_THEME.statsLabel">{{ t('roomMap.lockOos') }}</span>
              <span class="text-[13px] mt-0.5" :class="TEXT_THEME.statsValue">{{ roomStore.rooms.filter(r => r.status === ROOM_STATUSES.MAINTENANCE && r.lock_type === 'OOS').length }}</span>
            </div>
          </button>
 
          <!-- Công suất card -->
          <button @click="showStatsModal = true" 
            class="bg-white border hover:border-slate-300 rounded-xl px-4 py-2.5 flex items-center gap-3 shadow-xs shrink-0 cursor-pointer text-left transition-all hover:shadow-md hover:-translate-y-0.5 transform-gpu"
            :class="showStatsModal ? 'ring-2 ring-inset ring-[#97d5ff] border-[#97d5ff] bg-[#97d5ff]/5' : 'border-slate-200/80'">
            <div class="w-8 h-8 rounded-full flex items-center justify-center text-slate-800 font-extrabold text-[10px] shrink-0 relative">
              <svg class="absolute inset-0 w-full h-full transform -rotate-90" viewBox="0 0 36 36">
                <path
                  class="text-slate-100"
                  stroke="currentColor"
                  stroke-width="4.5"
                  fill="none"
                  d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                />
                <path
                  class="text-amber-500 transition-all duration-1000 ease-out progress-ring-path"
                  :stroke-dasharray="`${roomStore.occupancyRate || 0}, 100`"
                  stroke-width="4.5"
                  stroke-linecap="round"
                  fill="none"
                  stroke="currentColor"
                  d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                />
              </svg>
              <span class="relative z-10 text-[9px] leading-none" :class="TEXT_THEME.statsValue">
                {{ occupancyRateStats }}
              </span>
            </div>
            <div class="flex flex-col">
              <span class="text-[10px] uppercase leading-tight" :class="TEXT_THEME.statsLabel">{{ t('roomMap.occupancy') }}</span>
              <span class="text-[13px] mt-0.5" :class="TEXT_THEME.statsValue">{{ occupancyRateStats }}</span>
            </div>
          </button>
        </div>

        <!-- View Mode & Zoom switchers -->
        <div class="flex items-center gap-2.5 shrink-0">
          <!-- Help / Guide Trigger -->
          <HelpGuidePopover />

          <!-- Zoom Layout Controls -->
          <div class="flex items-center gap-1 bg-slate-50 border border-slate-200/80 rounded-lg p-0.5 select-none shrink-0 font-semibold text-[11.5px] text-slate-700">
            <!-- Auto Scale Toggle Button -->
            <button 
              @click="toggleAutoScale" 
              class="px-2.5 py-1 rounded-md cursor-pointer transition-all duration-200 text-[11px] border-none"
              :class="autoScale ? 'bg-sky-500 text-white font-bold shadow-xs' : 'bg-transparent text-slate-500 hover:bg-slate-100'"
              title="Tự động thu phóng để vừa khít màn hình"
            >
              Auto Fit
            </button>
            
            <!-- Manual Zoom Controls (Disabled if Auto Scale is enabled) -->
            <div class="flex items-center gap-0.5 pl-1 pr-0.5" :class="autoScale ? 'opacity-40 pointer-events-none' : ''">
              <button 
                @click="adjustManualScale('out')" 
                class="w-5.5 h-5.5 rounded-md hover:bg-slate-200 flex items-center justify-center cursor-pointer font-extrabold border-none text-slate-600 active:scale-90 transition-transform"
                title="Thu nhỏ"
              >
                -
              </button>
              <span class="w-9 text-center font-bold text-slate-800 text-[10.5px] tabular-nums">
                {{ Math.round(scaleFactor * 100) }}%
              </span>
              <button 
                @click="adjustManualScale('in')" 
                class="w-5.5 h-5.5 rounded-md hover:bg-slate-200 flex items-center justify-center cursor-pointer font-extrabold border-none text-slate-600 active:scale-90 transition-transform"
                title="Phóng to"
              >
                +
              </button>
            </div>
          </div>

          <div class="flex items-center gap-1">
            <button @click="isGridMode = false" 
              class="p-2 border rounded-lg cursor-pointer transition-colors"
              :class="!isGridMode ? 'bg-[#97d5ff]/20 border-[#97d5ff] text-sky-700' : 'bg-white border-slate-200 text-slate-400 hover:bg-slate-50'"
              :title="t('roomMap.listView')">
              <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
              </svg>
            </button>
            <button @click="isGridMode = true" 
              class="p-2 border rounded-lg cursor-pointer transition-colors"
              :class="isGridMode ? 'bg-[#97d5ff]/20 border-[#97d5ff] text-sky-700' : 'bg-white border-slate-200 text-slate-400 hover:bg-slate-50'"
              :title="t('roomMap.gridView')">
              <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
              </svg>
            </button>
            <button @click="showFilters = !showFilters" 
              class="p-2 border rounded-lg cursor-pointer transition-colors"
              :class="showFilters ? 'bg-[#97d5ff]/20 border-[#97d5ff] text-sky-700' : 'bg-white border-slate-200 text-slate-400 hover:bg-slate-50'"
              :title="t('roomMap.toggleFilter')">
              <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 0 1 1-1h16a1 1 0 0 1 1 1v2.586a1 1 0 0 1-.293.707l-6.414 6.414a1 1 0 0 0-.293.707V17l-4 4v-6.586a1 1 0 0 0-.293-.707L3.293 7.293A1 1 0 0 1 3 6.586V4z" />
              </svg>
            </button>
            <button @click="showSettings = !showSettings" 
              class="p-2 border rounded-lg cursor-pointer transition-colors settings-btn-trigger"
              :class="showSettings ? 'bg-[#97d5ff]/20 border-[#97d5ff] text-sky-700' : 'bg-white border-slate-200 text-slate-400 hover:bg-slate-50'"
              title="Cài đặt hiển thị">
              <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.43l-1.003.828c-.293.241-.438.613-.43.992a7.723 7.723 0 010 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.43l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 010-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.645-.869L9.594 3.94z" />
                <circle cx="12" cy="12" r="3" />
              </svg>
            </button>
          </div>

          <!-- Settings Dropdown Popover -->
          <div v-if="showSettings" 
            class="absolute right-6 top-16 w-72 bg-white rounded-xl shadow-2xl border border-slate-200/80 p-5 z-[50] flex flex-col gap-4 font-sans select-none animate-[fadeIn_0.15s_ease-out] settings-popover-panel text-slate-800">
            
            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
              <h3 class="text-sm font-black uppercase tracking-wider text-slate-800">Cài đặt hiển thị</h3>
              <button @click="showSettings = false" class="p-1.5 hover:bg-slate-100 rounded-lg text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer flex items-center justify-center transition-colors" title="Đóng">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
              </button>
            </div>

            <!-- Icon Sizing section -->
            <div class="flex flex-col gap-3">
              <span class="text-xs font-black uppercase text-slate-400 tracking-wider text-left">Icon</span>
              
              <!-- Group 1: Lock, Birthday, Honeymoon, Extra Bed -->
              <div class="flex flex-col gap-1">
                <div class="flex items-center justify-between text-slate-700">
                  <div class="flex items-center gap-2">
                    <RoomIcon name="ooo" class="w-5 h-5 text-amber-500" />
                    <RoomIcon name="birthday" class="w-5 h-5 text-pink-500" />
                    <RoomIcon name="honeymoon" class="w-5 h-5 text-red-500" />
                    <RoomIcon name="extra-bed" class="w-5 h-5 text-slate-600" />
                  </div>
                  <span class="text-[11px] text-slate-500 font-black">{{ settings.iconSizes.group1 }}px</span>
                </div>
                <input type="range" min="12" max="50" v-model.number="settings.iconSizes.group1" class="w-full h-1 rounded-lg appearance-none cursor-pointer accent-sky-500" :style="{ background: 'linear-gradient(to right, #0ea5e9 0%, #0ea5e9 ' + ((settings.iconSizes.group1 - 12) / (50 - 12) * 100) + '%, #e2e8f0 ' + ((settings.iconSizes.group1 - 12) / (50 - 12) * 100) + '%, #e2e8f0 100%)' }" />
              </div>

              <!-- Group 2: Clean, Double check, Dirty -->
              <div class="flex flex-col gap-1">
                <div class="flex items-center justify-between text-slate-700">
                  <div class="flex items-center gap-2">
                    <RoomIcon name="clean" class="w-5 h-5 text-emerald-500" />
                    <RoomIcon name="double-check" class="w-5 h-5 text-blue-500" />
                    <RoomIcon name="dirty" class="w-5 h-5 text-amber-600" />
                  </div>
                  <span class="text-[11px] text-slate-500 font-black">{{ settings.iconSizes.group2 }}px</span>
                </div>
                <input type="range" min="12" max="50" v-model.number="settings.iconSizes.group2" class="w-full h-1 rounded-lg appearance-none cursor-pointer accent-sky-500" :style="{ background: 'linear-gradient(to right, #0ea5e9 0%, #0ea5e9 ' + ((settings.iconSizes.group2 - 12) / (50 - 12) * 100) + '%, #e2e8f0 ' + ((settings.iconSizes.group2 - 12) / (50 - 12) * 100) + '%, #e2e8f0 100%)' }" />
              </div>

              <!-- Group 3: Green status dot, Red status dot, Split dot -->
              <div class="flex flex-col gap-1">
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 border border-white/20 shadow-xs"></span>
                    <span class="w-2.5 h-2.5 rounded-full bg-red-500 border border-white/20 shadow-xs"></span>
                    <span class="w-2.5 h-2.5 rounded-full border border-white/20 shadow-xs bg-gradient-to-r from-emerald-500 from-50% to-red-500 to-50%"></span>
                  </div>
                  <span class="text-[11px] text-slate-500 font-black">{{ settings.iconSizes.group3 }}px</span>
                </div>
                <input type="range" min="6" max="50" v-model.number="settings.iconSizes.group3" class="w-full h-1 rounded-lg appearance-none cursor-pointer accent-sky-500" :style="{ background: 'linear-gradient(to right, #0ea5e9 0%, #0ea5e9 ' + ((settings.iconSizes.group3 - 6) / (50 - 6) * 100) + '%, #e2e8f0 ' + ((settings.iconSizes.group3 - 6) / (50 - 6) * 100) + '%, #e2e8f0 100%)' }" />
              </div>

              <!-- Group 4: Walkin -->
              <div class="flex flex-col gap-1">
                <div class="flex items-center justify-between text-slate-700">
                  <div class="flex items-center gap-2">
                    <RoomIcon name="walkin" class="w-5 h-5 text-slate-600" />
                  </div>
                  <span class="text-[11px] text-slate-500 font-black">{{ settings.iconSizes.group4 }}px</span>
                </div>
                <input type="range" min="12" max="50" v-model.number="settings.iconSizes.group4" class="w-full h-1 rounded-lg appearance-none cursor-pointer accent-sky-500" :style="{ background: 'linear-gradient(to right, #0ea5e9 0%, #0ea5e9 ' + ((settings.iconSizes.group4 - 12) / (50 - 12) * 100) + '%, #e2e8f0 ' + ((settings.iconSizes.group4 - 12) / (50 - 12) * 100) + '%, #e2e8f0 100%)' }" />
              </div>

              <!-- Group 5: Priority, DND -->
              <div class="flex flex-col gap-1">
                <div class="flex items-center justify-between text-slate-700">
                  <div class="flex items-center gap-2">
                    <RoomIcon name="priority" class="w-5 h-5 text-sky-500" />
                    <RoomIcon name="dnd" class="w-5 h-5 text-slate-500" />
                  </div>
                  <span class="text-[11px] text-slate-500 font-black">{{ settings.iconSizes.group5 }}px</span>
                </div>
                <input type="range" min="12" max="50" v-model.number="settings.iconSizes.group5" class="w-full h-1 rounded-lg appearance-none cursor-pointer accent-sky-500" :style="{ background: 'linear-gradient(to right, #0ea5e9 0%, #0ea5e9 ' + ((settings.iconSizes.group5 - 12) / (50 - 12) * 100) + '%, #e2e8f0 ' + ((settings.iconSizes.group5 - 12) / (50 - 12) * 100) + '%, #e2e8f0 100%)' }" />
              </div>
            </div>

            <hr class="border-slate-100" />

            <!-- Exact Position Toggle -->
            <div class="flex items-center justify-between">
              <span class="text-xs font-bold text-slate-700">Vị trí chính xác</span>
              <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" v-model="settings.exactPosition" class="sr-only peer" />
                <div class="w-9 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-sky-500"></div>
              </label>
            </div>

            <!-- Floor Orientation Toggle -->
            <div class="flex items-center justify-between">
              <span class="text-xs font-bold text-slate-700">Hướng của tầng</span>
              <div class="flex items-center bg-slate-100 rounded-lg p-0.5 border border-slate-200 text-[11px] font-black">
                <button @click="settings.floorOrientation = 'Ngang'"
                  class="px-2.5 py-1 rounded-md transition-all border-none cursor-pointer"
                  :class="settings.floorOrientation === 'Ngang' ? 'bg-sky-500 text-white shadow-xs' : 'text-slate-500 bg-transparent hover:bg-slate-200'">
                  Ngang
                </button>
                <button @click="settings.floorOrientation = 'Dọc'"
                  class="px-2.5 py-1 rounded-md transition-all border-none cursor-pointer"
                  :class="settings.floorOrientation === 'Dọc' ? 'bg-sky-500 text-white shadow-xs' : 'text-slate-500 bg-transparent hover:bg-slate-200'">
                  Dọc
                </button>
              </div>
            </div>

            <hr class="border-slate-100" />

            <!-- Room Width Slider -->
            <div class="flex flex-col gap-1.5 text-xs font-bold text-slate-700">
              <div class="flex justify-between">
                <span class="text-left">Chiều dài phòng</span>
                <span class="text-slate-500">{{ settings.roomWidth }}px</span>
              </div>
              <input type="range" min="120" max="300" v-model.number="settings.roomWidth" class="w-full h-1 rounded-lg appearance-none cursor-pointer accent-sky-500" :style="{ background: 'linear-gradient(to right, #0ea5e9 0%, #0ea5e9 ' + ((settings.roomWidth - 120) / (300 - 120) * 100) + '%, #e2e8f0 ' + ((settings.roomWidth - 120) / (300 - 120) * 100) + '%, #e2e8f0 100%)' }" />
            </div>

            <!-- Room Height Slider -->
            <div class="flex flex-col gap-1.5 text-xs font-bold text-slate-700">
              <div class="flex justify-between">
                <span class="text-left">Chiều cao phòng</span>
                <span class="text-slate-500">{{ settings.roomHeight }}px</span>
              </div>
              <input type="range" min="80" max="200" v-model.number="settings.roomHeight" class="w-full h-1 rounded-lg appearance-none cursor-pointer accent-sky-500" :style="{ background: 'linear-gradient(to right, #0ea5e9 0%, #0ea5e9 ' + ((settings.roomHeight - 80) / (200 - 80) * 100) + '%, #e2e8f0 ' + ((settings.roomHeight - 80) / (200 - 80) * 100) + '%, #e2e8f0 100%)' }" />
            </div>

            <!-- Buttons Group -->
            <div class="flex gap-2.5 mt-2">
              <button @click="resetToDefaultSettings" 
                class="flex-1 py-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-black rounded-xl text-xs tracking-wider uppercase transition-colors shadow-xs border-none cursor-pointer flex items-center justify-center gap-1.5">
                Mặc định
              </button>
              <button @click="saveSettings" 
                class="flex-1 py-2.5 bg-[#97d5ff] hover:bg-[#7bc4ff] text-slate-900 font-black rounded-xl text-xs tracking-wider uppercase transition-colors shadow-xs border-none cursor-pointer flex items-center justify-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Lưu
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Main Layout Body (Grid or list or subpages) -->
      <div class="flex-1 flex min-h-0 min-w-0 overflow-hidden relative">
        
        <!-- Global Loading Overlay -->
        <LoadingOverlay :show="!isLoaded || (currentTab === 'room-plan' && isRoomPlanLoading)" />

        <template v-if="isLoaded">
          <!-- Left/Center content container -->
          <div class="flex-1 min-w-0 overflow-hidden bg-white flex flex-col gap-4">
          
          <!-- Tab 1: Phòng Trống AvailableRoomsPage -->
          <div v-if="currentTab === 'available'" class="h-full overflow-hidden">
            <AvailableRoomsPage />
          </div>

          <!-- Tab 2: Kế hoạch phòng RoomPlanPage -->
          <div v-else-if="currentTab === 'room-plan'" class="h-full overflow-hidden">
            <RoomPlanPage 
              @loading="val => isRoomPlanLoading = val" 
              @edit-booking="handleEditBookingFromPlan"
            />
          </div>

          <!-- Tab 3: D.S Công Việc ShiftWorkPage -->
          <div v-else-if="currentTab === 'shift-work'" class="h-full overflow-hidden">
            <ShiftWorkPage />
          </div>

          <!-- Tab 4: Công Ty CompanySettingsPage -->
          <div v-else-if="currentTab === 'company'" class="h-full overflow-hidden">
            <CompanySettingsPage />
          </div>

          <!-- Tab 5: Khóa Phòng LockRoomPage -->
          <div v-else-if="currentTab === 'lock-room'" class="h-full overflow-hidden">
            <LockRoomPage />
          </div>

          <!-- Tab 6: Quản Lý Đồ Thất Lạc LostAndFound -->
          <div v-else-if="currentTab === 'lost-found'" class="h-full overflow-hidden">
            <LostAndFound />
          </div>

          <!-- Tab 7: Tạo Đăng Ký CreateRegistrationPage -->
          <div v-else-if="currentTab === 'create-res'" class="h-full overflow-hidden">
            <CreateRegistrationPage ref="createRegRef" />
          </div>

          <!-- Tab Checkin: Nhận phòng (Đến / Đã đến) -->
          <div v-else-if="currentTab === 'checkin'" class="h-full overflow-hidden">
            <CheckInPage :initial-date="rawDate" />
          </div>

          <!-- Tab 8: ALLOTMENT Tab -->
          <div v-else-if="currentTab === 'allotment'" class="bg-white rounded-xl shadow-xs border border-slate-200 p-5 flex flex-col gap-5 text-slate-800">
            <div class="flex justify-between items-center pb-4 border-b border-slate-100">
              <div>
                <h2 class="text-base font-black tracking-wide uppercase text-slate-800">{{ t('roomMap.allotmentConfigTitle') }}</h2>
                <p class="text-xs text-slate-400 font-bold mt-1">{{ t('roomMap.allotmentConfigDesc') }}</p>
              </div>
              <button @click="showDevelopmentToast(t('roomMap.createNewAllotment'))" class="px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-black shadow-sm transition-all border-none cursor-pointer">
                {{ t('roomMap.createNewAllotment') }}
              </button>
            </div>
            
            <table class="w-full text-left border-collapse text-xs">
              <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold select-none h-9">
                  <th class="p-2.5">{{ t('roomMap.partnerOta') }}</th>
                  <th class="p-2.5">{{ t('roomMap.allotmentRoomType') }}</th>
                  <th class="p-2.5 text-center">{{ t('roomMap.allotmentQty') }}</th>
                  <th class="p-2.5 text-center">{{ t('roomMap.allotmentSold') }}</th>
                  <th class="p-2.5 text-center">{{ t('roomMap.allotmentRemaining') }}</th>
                  <th class="p-2.5 text-center">{{ t('roomMap.allotmentStatus') }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 font-bold text-slate-700">
                <tr v-for="item in [
                  { partner: 'Agoda.com', type: 'Deluxe Double', allocated: 10, sold: 6, status: 'Đang mở' },
                  { partner: 'Booking.com', type: 'Executive Suite', allocated: 5, sold: 3, status: 'Đang mở' },
                  { partner: 'Travel Concierge', type: 'Standard Twin', allocated: 8, sold: 8, status: 'Đã hết' },
                  { partner: 'Trip.com', type: 'Standard Double', allocated: 6, sold: 1, status: 'Đang mở' }
                ]" :key="item.partner" class="hover:bg-slate-50 h-10">
                  <td class="p-2.5 text-slate-900 font-black">{{ item.partner }}</td>
                  <td class="p-2.5">{{ item.type }}</td>
                  <td class="p-2.5 text-center text-slate-900">{{ item.allocated }} {{ t('roomMap.allotmentRoomsUnit') }}</td>
                  <td class="p-2.5 text-center text-sky-600">{{ item.sold }}</td>
                  <td class="p-2.5 text-center text-slate-500">{{ item.allocated - item.sold }}</td>
                  <td class="p-2.5 text-center">
                    <span class="px-2 py-0.5 rounded text-[10px] font-black border" :class="item.status === 'Đang mở' ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : 'bg-red-50 text-red-500 border-red-100'">
                      {{ item.status === 'Đang mở' ? t('roomMap.allotmentOpen') : t('roomMap.allotmentSoldOut') }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Tab 9: CHI TIẾT ALLOTMENT Tab -->
          <div v-else-if="currentTab === 'allotment-detail'" class="bg-white rounded-xl shadow-xs border border-slate-200 p-5 flex flex-col gap-5 text-slate-800">
            <div class="pb-4 border-b border-slate-100">
              <h2 class="text-base font-black tracking-wide uppercase text-slate-800">{{ t('roomMap.allotmentDetailTitle') }}</h2>
              <p class="text-xs text-slate-400 font-bold mt-1">{{ t('roomMap.allotmentDetailDesc') }}</p>
            </div>

            <table class="w-full text-left border-collapse text-xs">
              <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold select-none h-9">
                  <th class="p-2.5">{{ t('roomMap.allotmentIdCode') }}</th>
                  <th class="p-2.5">{{ t('roomMap.allotmentPartner') }}</th>
                  <th class="p-2.5 text-center">{{ t('roomMap.allotmentRoomNo') }}</th>
                  <th class="p-2.5 text-center">{{ t('roomMap.allotmentStartDate') }}</th>
                  <th class="p-2.5 text-center">{{ t('roomMap.allotmentEndDate') }}</th>
                  <th class="p-2.5 text-center">{{ t('roomMap.allotmentUnlock') }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 font-bold text-slate-700">
                <tr v-for="item in [
                  { id: 'AL-1009', partner: 'Agoda.com', room: '202', start: '10-06-2026', end: '20-06-2026', active: true },
                  { id: 'AL-1012', partner: 'Booking.com', room: '304', start: '12-06-2026', end: '22-06-2026', active: true },
                  { id: 'AL-1015', partner: 'Travel Concierge', room: '104', start: '15-06-2026', end: '25-06-2026', active: false }
                ]" :key="item.id" class="hover:bg-slate-50 h-10">
                  <td class="p-2.5 text-slate-900 font-black">{{ item.id }}</td>
                  <td class="p-2.5 text-slate-900">{{ item.partner }}</td>
                  <td class="p-2.5 text-center font-black">{{ item.room }}</td>
                  <td class="p-2.5 text-center text-slate-500">{{ item.start }}</td>
                  <td class="p-2.5 text-center text-slate-500">{{ item.end }}</td>
                  <td class="p-2.5 text-center">
                    <span class="px-2 py-0.5 rounded text-[10px] font-black border" :class="item.active ? 'bg-sky-50 text-sky-600 border-sky-100' : 'bg-slate-100 text-slate-500 border-slate-200'">
                      {{ item.active ? t('roomMap.allotmentOpen') : t('roomMap.allotmentLocked') }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Tab 10: BÁO CÁO PHÂN BỔ PHÒNG ALLOTMENT -->
          <div v-else-if="currentTab === 'allotment-report'" class="bg-white rounded-xl shadow-xs border border-slate-200 p-5 flex flex-col gap-6 text-slate-800">
            <div class="pb-4 border-b border-slate-100">
              <h2 class="text-base font-black tracking-wide uppercase text-slate-800">{{ t('roomMap.allotmentReportTitle') }}</h2>
              <p class="text-xs text-slate-400 font-bold mt-1">{{ t('roomMap.allotmentReportDesc') }}</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div v-for="item in [
                { title: t('roomMap.allotmentTotalRooms'), value: '45 ' + t('roomMap.allotmentRoomsUnit'), color: 'text-blue-600', desc: t('roomMap.allotmentAllocatedMonth') },
                { title: t('roomMap.allotmentOccupiedRooms'), value: '29 ' + t('roomMap.allotmentRoomsUnit'), color: 'text-emerald-600', desc: t('roomMap.allotmentOccupiedDesc') },
                { title: t('roomMap.allotmentRevenueVal'), value: '38.250.000 đ', color: 'text-indigo-600', desc: t('roomMap.allotmentRevenueDesc') }
              ]" :key="item.title" class="bg-slate-50 rounded-xl p-4 border border-slate-100">
                <span class="text-[11px] font-black uppercase text-slate-400 tracking-wide block mb-1">{{ item.title }}</span>
                <span class="text-xl font-black block" :class="item.color">{{ item.value }}</span>
                <span class="text-[10px] text-slate-500 block mt-1.5 font-bold">{{ item.desc }}</span>
              </div>
            </div>

            <div class="flex flex-col gap-3.5 mt-2">
              <span class="text-xs font-black uppercase text-slate-600 tracking-wider">{{ t('roomMap.allotmentChannelPerf') }}</span>
              <div v-for="k in [
                { name: 'Agoda.com', percent: '80%', sold: `16/20 ${t('roomMap.allotmentRoomsUnit')}` },
                { name: 'Booking.com', percent: '60%', sold: `9/15 ${t('roomMap.allotmentRoomsUnit')}` },
                { name: 'Travel Concierge', percent: '40%', sold: `4/10 ${t('roomMap.allotmentRoomsUnit')}` }
              ]" :key="k.name" class="flex flex-col gap-1.5">
                <div class="flex justify-between text-xs font-bold text-slate-700">
                  <span>{{ k.name }}</span>
                  <span>{{ k.sold }} ({{ k.percent }})</span>
                </div>
                <div class="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
                  <div class="h-full bg-blue-500 rounded-full" :style="{ width: k.percent }"></div>
                </div>
              </div>
            </div>
          </div>

          <!-- Tab 11: BÁO CÁO ĐĂNG KÝ Tab -->
          <div v-else-if="currentTab === 'report-reg'" class="bg-white rounded-xl shadow-xs border border-slate-200 p-5 flex flex-col gap-5 text-slate-800">
            <div class="pb-4 border-b border-slate-100">
              <h2 class="text-base font-black tracking-wide uppercase text-slate-800">{{ t('roomMap.regReportTitle') }}</h2>
              <p class="text-xs text-slate-400 font-bold mt-1">{{ t('roomMap.regReportDesc') }}</p>
            </div>

            <table class="w-full text-left border-collapse text-xs">
              <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold select-none h-9">
                  <th class="p-2.5">{{ t('roomMap.regIdCode') }}</th>
                  <th class="p-2.5">{{ t('roomMap.regCustomer') }}</th>
                  <th class="p-2.5 text-center">{{ t('roomMap.regRoomNo') }}</th>
                  <th class="p-2.5 text-center">{{ t('roomMap.regCheckIn') }}</th>
                  <th class="p-2.5 text-center">{{ t('roomMap.regCheckOut') }}</th>
                  <th class="p-2.5 text-center">{{ t('roomMap.regStatus') }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 font-bold text-slate-700">
                <tr v-for="item in [
                  { id: 'REG-552', guest: 'Ms. Ivanova Daria', room: '102', in: '12-06-2026', out: '16-06-2026', status: 'Hoạt động' },
                  { id: 'REG-553', guest: 'Mr. Rachid Boufarki', room: '204', in: '14-06-2026', out: '18-06-2026', status: 'Hoạt động' },
                  { id: 'REG-554', guest: 'Walkin Guest', room: '302', in: '15-06-2026', out: '16-06-2026', status: 'Hoàn thành' }
                ]" :key="item.id" class="hover:bg-slate-50 h-10">
                  <td class="p-2.5 text-slate-900 font-black text-sm">{{ item.id }}</td>
                  <td class="p-2.5 text-slate-900">{{ item.guest }}</td>
                  <td class="p-2.5 text-center font-black">{{ item.room }}</td>
                  <td class="p-2.5 text-center text-slate-500">{{ item.in }}</td>
                  <td class="p-2.5 text-center text-slate-500">{{ item.out }}</td>
                  <td class="p-2.5 text-center">
                    <span class="px-2 py-0.5 rounded text-[10px] font-black border" :class="item.status === 'Hoạt động' ? 'bg-sky-50 text-sky-600 border-sky-100' : 'bg-slate-100 text-slate-500 border-slate-200'">
                      {{ item.status === 'Hoạt động' ? t('roomMap.regActive') : t('roomMap.regCompleted') }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Tab 12: BÁO CÁO THỐNG KÊ Tab -->
          <div v-else-if="currentTab === 'report-stats'" class="bg-white rounded-xl shadow-xs border border-slate-200 p-5 flex flex-col gap-5 text-slate-800">
            <div class="pb-4 border-b border-slate-100">
              <h2 class="text-base font-black tracking-wide uppercase text-slate-800">{{ t('roomMap.statReportTitle') }}</h2>
              <p class="text-xs text-slate-400 font-bold mt-1">{{ t('roomMap.statReportDesc') }}</p>
            </div>

            <div class="grid grid-cols-2 gap-4">
              <div class="bg-slate-50 rounded-xl p-4 border border-slate-100 flex flex-col justify-center">
                <span class="text-[10px] font-black text-slate-400 uppercase tracking-wide">{{ t('roomMap.statAverageOccupancy') }}</span>
                <span class="text-3xl font-black text-blue-600 tracking-tight mt-1">72.8%</span>
              </div>
              <div class="bg-slate-50 rounded-xl p-4 border border-slate-100 flex flex-col justify-center">
                <span class="text-[10px] font-black text-slate-400 uppercase tracking-wide">{{ t('roomMap.statAverageStay') }}</span>
                <span class="text-3xl font-black text-emerald-600 tracking-tight mt-1">2.4 {{ t('roomMap.statDaysUnit') }}</span>
              </div>
            </div>

            <table class="w-full text-left border-collapse text-xs mt-2">
              <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold select-none h-9">
                  <th class="p-2.5">{{ t('roomMap.statRoomCategory') }}</th>
                  <th class="p-2.5 text-center">{{ t('roomMap.statTotalRooms') }}</th>
                  <th class="p-2.5 text-center">{{ t('roomMap.statOccupiedRooms') }}</th>
                  <th class="p-2.5 text-center">{{ t('roomMap.statRepairRooms') }}</th>
                  <th class="p-2.5 text-center">{{ t('roomMap.statEfficiency') }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 font-bold text-slate-700">
                <tr v-for="item in [
                  { name: 'Standard Double', total: 30, occ: 22, repair: 1, rate: '73.3%' },
                  { name: 'Deluxe Twin', total: 25, occ: 20, repair: 0, rate: '80.0%' },
                  { name: 'Executive Suite', total: 12, occ: 7, repair: 2, rate: '58.3%' }
                ]" :key="item.name" class="hover:bg-slate-50 h-10">
                  <td class="p-2.5 text-slate-900 font-black">{{ item.name }}</td>
                  <td class="p-2.5 text-center text-slate-800">{{ item.total }}</td>
                  <td class="p-2.5 text-center text-sky-600">{{ item.occ }}</td>
                  <td class="p-2.5 text-center text-rose-500">{{ item.repair }}</td>
                  <td class="p-2.5 text-center text-slate-900">{{ item.rate }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Tab 13: BÁO CÁO PHÒNG Tab -->
          <div v-else-if="currentTab === 'report-rooms'" class="bg-white rounded-xl shadow-xs border border-slate-200 p-5 flex flex-col gap-5 text-slate-800">
            <div class="pb-4 border-b border-slate-100">
              <h2 class="text-base font-black tracking-wide uppercase text-slate-800">{{ t('roomMap.roomsReportTitle') }}</h2>
              <p class="text-xs text-slate-400 font-bold mt-1">{{ t('roomMap.roomsReportDesc') }}</p>
            </div>

            <table class="w-full text-left border-collapse text-xs">
              <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold select-none h-9">
                  <th class="p-2.5">{{ t('roomMap.roomsReportStatus') }}</th>
                  <th class="p-2.5 text-center">{{ t('roomMap.roomsReportQty') }}</th>
                  <th class="p-2.5 text-center">{{ t('roomMap.roomsReportPct') }}</th>
                  <th class="p-2.5">{{ t('roomMap.roomsReportNotes') }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 font-bold text-slate-700">
                <tr v-for="item in [
                  { key: 'roomsCleanVacant', count: 42, pct: '39.3%' },
                  { key: 'roomsOccupied', count: 45, pct: '42.1%' },
                  { key: 'roomsDirtyVacant', count: 15, pct: '14.0%' },
                  { key: 'roomsMaintenance', count: 5, pct: '4.6%' }
                ]" :key="item.key" class="hover:bg-slate-50 h-10">
                  <td class="p-2.5 text-slate-900 font-black">{{ t('roomMap.' + item.key) }}</td>
                  <td class="p-2.5 text-center font-black text-slate-800">{{ item.count }} {{ t('roomMap.allotmentRoomsUnit') }}</td>
                  <td class="p-2.5 text-center text-sky-600">{{ item.pct }}</td>
                  <td class="p-2.5 text-slate-500 font-medium">{{ t('roomMap.' + item.key + 'Desc') }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Tab 14: BÁO CÁO HỦY PHÒNG Tab -->
          <div v-else-if="currentTab === 'report-cancel'" class="bg-white rounded-xl shadow-xs border border-slate-200 p-5 flex flex-col gap-5 text-slate-800">
            <div class="pb-4 border-b border-slate-100">
              <h2 class="text-base font-black tracking-wide uppercase text-slate-800">{{ t('roomMap.cancelReportTitle') }}</h2>
              <p class="text-xs text-slate-400 font-bold mt-1">{{ t('roomMap.cancelReportDesc') }}</p>
            </div>

            <table class="w-full text-left border-collapse text-xs">
              <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold select-none h-9">
                  <th class="p-2.5">{{ t('roomMap.cancelIdCode') }}</th>
                  <th class="p-2.5">{{ t('roomMap.cancelGuestName') }}</th>
                  <th class="p-2.5 text-center">{{ t('roomMap.cancelDate') }}</th>
                  <th class="p-2.5 text-right">{{ t('roomMap.cancelValue') }}</th>
                  <th class="p-2.5">{{ t('roomMap.cancelReason') }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 font-bold text-slate-700">
                <tr v-for="item in [
                  { id: 'CN-201', guest: 'Mr. David Lee', date: '08-06-2026', val: '2.500.000', reasonKey: 'cancelReason1' },
                  { id: 'CN-202', guest: 'Ms. Nguyen Kim Chi', date: '10-06-2026', val: '3.600.000', reasonKey: 'cancelReason2' },
                  { id: 'CN-203', guest: 'Mr. Park Jung Woo', date: '12-06-2026', val: '1.200.000', reasonKey: 'cancelReason3' }
                ]" :key="item.id" class="hover:bg-slate-50 h-10">
                  <td class="p-2.5 text-rose-600 font-black text-sm">{{ item.id }}</td>
                  <td class="p-2.5 text-slate-900">{{ item.guest }}</td>
                  <td class="p-2.5 text-center text-slate-500">{{ item.date }}</td>
                  <td class="p-2.5 text-right text-slate-800 font-black">{{ item.val }} đ</td>
                  <td class="p-2.5 text-slate-500 font-medium truncate max-w-[300px]">{{ t('roomMap.' + item.reasonKey) }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Tab 15: CHANNEL MANAGER Tab -->
          <div v-else-if="currentTab === 'channel-manager'" class="bg-white rounded-xl shadow-xs border border-slate-200 p-5 flex flex-col gap-5 text-slate-800">
            <div class="flex justify-between items-center pb-4 border-b border-slate-100">
              <div>
                <h2 class="text-base font-black tracking-wide uppercase text-slate-800">{{ t('roomMap.channelManagerTitle') }}</h2>
                <p class="text-xs text-slate-400 font-bold mt-1">{{ t('roomMap.channelManagerDesc') }}</p>
              </div>
              <button @click="showDevelopmentToast(t('roomMap.channelManagerSyncNow'))" class="px-3.5 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded-lg text-xs font-black shadow-sm transition-all border-none cursor-pointer">
                {{ t('roomMap.channelManagerSyncNow') }}
              </button>
            </div>

            <table class="w-full text-left border-collapse text-xs">
              <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold select-none h-9">
                  <th class="p-2.5">{{ t('roomMap.channelManagerOtaLink') }}</th>
                  <th class="p-2.5 text-center">{{ t('roomMap.channelManagerAvailRooms') }}</th>
                  <th class="p-2.5 text-right">{{ t('roomMap.channelManagerSyncRates') }}</th>
                  <th class="p-2.5 text-center">{{ t('roomMap.channelManagerLastSync') }}</th>
                  <th class="p-2.5 text-center">{{ t('roomMap.channelManagerConnStatus') }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 font-bold text-slate-700">
                <tr v-for="item in [
                  { channel: 'Agoda API Connection', rooms: 15, rate: '650.000 đ', timeKey: 'channelManagerMinsAgo', timeVal: 10, status: 'active' },
                  { channel: 'Booking.com XML Sync', rooms: 12, rate: '680.000 đ', timeKey: 'channelManagerMinsAgo', timeVal: 5, status: 'active' },
                  { channel: 'Expedia.com OTA link', rooms: 8, rate: '720.000 đ', timeKey: 'channelManagerHourAgo', timeVal: 1, status: 'reconnecting' }
                ]" :key="item.channel" class="hover:bg-slate-50 h-10">
                  <td class="p-2.5 text-slate-900 font-black">{{ item.channel }}</td>
                  <td class="p-2.5 text-center font-black text-slate-800">{{ item.rooms }} {{ t('roomMap.allotmentRoomsUnit') }}</td>
                  <td class="p-2.5 text-right text-emerald-600 font-black">{{ item.rate }}</td>
                  <td class="p-2.5 text-center text-slate-400">{{ t('roomMap.' + item.timeKey, { n: item.timeVal }) }}</td>
                  <td class="p-2.5 text-center">
                    <span class="px-2 py-0.5 rounded text-[10px] font-black border" :class="item.status === 'active' ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : 'bg-amber-50 text-amber-600 border-amber-100'">
                      {{ item.status === 'active' ? t('roomMap.regActive') : t('roomMap.channelManagerReconnecting') }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- THE DEFAULT ROOM MAP VIEW -->
          <div v-else class="flex-1 flex flex-col gap-4 min-h-0">
            
            <!-- Check-in Page view when filtering by RESERVED (Đã đến) -->
            <div v-if="activeFilter === ROOM_STATUSES.RESERVED" class="flex-1 flex flex-col min-h-0">
              <CheckInPage :initial-date="rawDate" />
            </div>

            <!-- Loading State -->
            <div v-else-if="roomStore.loading" class="flex items-center justify-center flex-1 relative min-h-[250px]">
              <LoadingOverlay :show="roomStore.loading" />
            </div>

            <!-- Error State -->
            <div v-else-if="roomStore.error" class="flex items-center justify-center flex-1">
              <div class="text-center p-8 bg-white rounded-xl shadow-sm max-w-md border border-slate-200">
                <svg class="w-12 h-12 text-red-400 mx-auto mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                </svg>
                <p class="text-slate-700 font-medium mb-2">{{ roomStore.error }}</p>
                <button @click="roomStore.fetchRooms()" class="px-4 py-2 bg-blue-500 text-white rounded-lg text-sm font-semibold hover:bg-blue-600 transition-colors cursor-pointer border-none shadow-sm">
                  Thử lại
                </button>
              </div>
            </div>

            <!-- Room Grid view (Lưới sơ đồ) -->
            <!-- Room Grid view (Lưới sơ đồ) -->
            <div v-else-if="isGridMode" 
              class="flex-1 overflow-auto pt-2.5 pr-2.5 pb-4 scrollbar-thin animate-room-grid"
              :class="settings.floorOrientation === 'Ngang' ? 'flex flex-col gap-1' : 'flex flex-row gap-5 items-start'"
            >
              <div
                v-for="(floor, floorIdx) in sortedFloors"
                :key="floor"
                :class="[
                  settings.floorOrientation === 'Ngang' ? 'flex gap-4' : 'flex flex-col gap-2',
                  isInitialLoad ? 'floor-row-animate' : ''
                ]"
                :style="isInitialLoad ? { animationDelay: `${floorIdx * 65}ms` } : {}"
              >
                <!-- Vertical Floor Pill shape on the left - Sticky to keep floor numbers visible -->
                <div class="floor-pill cursor-pointer"
                  :style="settings.floorOrientation === 'Dọc' ? {
                    width: settings.roomWidth + 'px',
                    height: 'auto',
                    position: 'static',
                    padding: '8px 4px',
                    flexDirection: 'row',
                    gap: '6px'
                  } : {}"
                >
                  <span>{{ t('roomMap.floor', { floor }) }}</span>
                  <span class="text-[10px] opacity-80 font-bold mt-1" :style="settings.floorOrientation === 'Dọc' ? { marginTop: '0px' } : {}">
                    {{ t('roomMap.roomsCount', { count: roomStore.roomsByFloor[floor]?.length || 0 }) }}
                  </span>
                </div>

                <!-- Rooms horizontal/vertical flex container inside this floor -->
                <div :class="settings.floorOrientation === 'Ngang' ? 'flex gap-1.5' : 'flex flex-col gap-1.5'">
                  <div
                    v-for="(room, roomIdx) in roomStore.roomsByFloor[floor]"
                    :key="room.id"
                    class="room-card"
                    :class="[
                      isInitialLoad ? 'room-card-animate' : '',
                      (room.status === ROOM_STATUSES.OCCUPIED || room.status === ROOM_STATUSES.CHECKOUT) ? 'occupied-room' : ''
                    ]"
                    :style="getRoomCardStyle(room, floorIdx, roomIdx)"
                    @click="handleRoomClick(room)"
                    @contextmenu.prevent="handleContextMenu($event, room)"
                    @mouseenter="showTooltip($event, room)"
                    @mousemove="showTooltip($event, room)"
                    @mouseleave="hideTooltip"
                  >
                    <!-- Status Indicator Dot (Top Left - Check-in Today) -->
                    <div v-if="hasArrivalToday(room)" class="absolute top-2.5 left-2.5">
                      <span class="rounded-full block border border-white/20 shadow-sm bg-emerald-500 relative"
                        :style="{ width: (settings.iconSizes.group3 * cardScale) + 'px', height: (settings.iconSizes.group3 * cardScale) + 'px' }"></span>
                    </div>

                    <!-- Status Indicator Dot (Top Right - Check-out Today) -->
                    <div v-if="hasDepartureToday(room)" class="absolute top-2.5 right-2.5">
                      <span class="rounded-full block border border-white/20 shadow-sm bg-red-500 relative"
                        :style="{ width: (settings.iconSizes.group3 * cardScale) + 'px', height: (settings.iconSizes.group3 * cardScale) + 'px' }"></span>
                    </div>

                    <!-- Room Content (Centered) -->
                    <div class="flex flex-col items-center justify-center w-full my-auto">
                      <!-- Room Number -->
                      <div class="font-bold text-[18px] leading-tight text-center w-full flex items-center justify-center gap-1">
                        <span :class="isRoomNumberRed(room) ? 'text-red-600' : (room.booking_color ? 'text-inherit' : 'text-gray-900')">
                          {{ room.room_number }}
                        </span>
                      </div>

                      <!-- Room Type (e.g. SUPT) -->
                      <div class="text-[10px] font-bold uppercase text-center w-full mt-0.5" :class="room.booking_color ? 'text-inherit opacity-80' : 'text-gray-500'">
                        {{ room.room_type || room.room_class?.code }}
                      </div>

                      <!-- Guest Name (if occupied or reserved) -->
                      <div v-if="room.booking_status === 'occupied' || room.booking_status === 'reserved' || room.booking_status === 'checkout'" class="text-[11px] font-bold leading-tight text-center w-full mt-1 truncate max-w-full" :class="room.booking_color ? 'text-inherit' : 'text-gray-900'">
                        {{ getMockGuestName(room) }}
                      </div>
                    </div>

                    <!-- Bottom row: Icons (Absolute Positioned to allow overflow boundaries) -->
                    <!-- Bottom Left Corner: Guest Count and Extra Bed -->
                    <div class="absolute flex items-center gap-1.5 pointer-events-none overflow-visible shrink-0"
                      :style="{
                        left: (10 - (settings.iconSizes.group4 - 16) * cardScale) + 'px',
                        bottom: (6 - (settings.iconSizes.group4 - 16) * cardScale) + 'px'
                      }"
                      :class="room.booking_color ? 'text-inherit opacity-85' : 'text-slate-500'"
                    >
                      <template v-if="getGuestCount(room) > 0">
                        <span class="flex items-center gap-0.5 font-bold text-[10.5px] leading-none" :class="room.booking_color ? 'text-inherit' : 'text-gray-900'">
                          <RoomIcon :name="getGuestCount(room) > 2 ? 'more-than-2-guests' : 'walkin'" :class="room.booking_color ? 'text-inherit' : 'text-gray-600'" :style="{ width: (settings.iconSizes.group4 * cardScale) + 'px', height: (settings.iconSizes.group4 * cardScale) + 'px' }" />
                          {{ getGuestCount(room) }}
                        </span>
                      </template>
                      <template v-if="hasExtraBed(room) && (room.status === ROOM_STATUSES.OCCUPIED || room.status === ROOM_STATUSES.RESERVED || hasArrivalToday(room))">
                        <RoomIcon name="extra-bed" class="text-gray-600 pl-0.5" :style="{ width: (settings.iconSizes.group1 * cardScale) + 'px', height: (settings.iconSizes.group1 * cardScale) + 'px' }" />
                      </template>
                    </div>

                    <!-- Bottom Right Corner: Status Icon -->
                    <div class="absolute text-slate-400 pointer-events-none overflow-visible shrink-0"
                      :style="{
                        right: (10 - (getStatusIconSize(room) - 20 * cardScale)) + 'px',
                        bottom: (6 - (getStatusIconSize(room) - 20 * cardScale)) + 'px'
                      }"
                    >
                      <RoomIcon 
                        :name="getRoomStatusIconName(room)" 
                        :monochrome="getRoomStatusIconName(room) === 'ooo'"
                        class="text-gray-600" 
                        :style="{ width: getStatusIconSize(room) + 'px', height: getStatusIconSize(room) + 'px' }" 
                      />
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Room List Table view (Bảng danh sách) -->
            <div v-else class="bg-white rounded-xl shadow-xs border border-slate-200 overflow-x-auto overflow-y-auto min-h-[300px] ml-1">
              <table class="w-full text-left border-collapse text-xs table-fixed min-w-[1400px]">
                <thead>
                  <tr class="bg-slate-50 border-b border-slate-200 select-none whitespace-nowrap" :class="TEXT_THEME.tableHeader">
                    <th class="p-2 border-r border-slate-200 text-center w-[60px]">{{ t('roomMap.status') }}</th>
                    <th class="p-2 border-r border-slate-200 text-center w-[85px] leading-tight text-[10px]">{{ t('roomMap.lateIn') }}</th>
                    <th class="p-2 border-r border-slate-200 text-center w-[100px] leading-tight text-[10px]">{{ t('roomMap.planMove') }}</th>
                    <th class="p-2 border-r border-slate-200 text-center w-[85px] leading-tight text-[10px]">{{ t('roomMap.roomStatus') }}</th>
                    <th class="p-2 border-r border-slate-200 text-center w-[85px] leading-tight text-[10px]">{{ t('roomMap.extraBed') }}</th>
                    <th class="p-2 border-r border-slate-200 text-center w-[80px] leading-tight text-[10px]">{{ t('roomMap.splReq') }}</th>
                    <th class="p-2 border-r border-slate-200 text-center w-[85px]">{{ t('roomMap.roomType') }}</th>
                    <th class="p-2 border-r border-slate-200 text-center w-[95px]">{{ t('roomMap.roomShape') }}</th>
                    <th class="p-2 border-r border-slate-200 text-center w-[75px]">{{ t('roomMap.roomNum') }}</th>
                    <th class="p-2 border-r border-slate-200 w-[180px]">{{ t('roomMap.guestName') }}</th>
                    <th class="p-2 border-r border-slate-200 text-center w-[75px]">{{ t('roomMap.regId') }}</th>
                    <th class="p-2 border-r border-slate-200 w-[240px]">{{ t('roomMap.regName') }}</th>
                    <th class="p-2 border-r border-slate-200 text-center w-[95px]">{{ t('roomMap.arrivalDate') }}</th>
                    <th class="p-2 border-r border-slate-200 text-center w-[95px]">{{ t('roomMap.departureDate') }}</th>
                    <th class="p-2 border-r border-slate-200 w-[200px]">{{ t('roomMap.company') }}</th>
                    <th class="p-2 text-center w-[60px]">{{ t('roomMap.floorHeader') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="room in roomStore.filteredRooms"
                    :key="room.id"
                    class="border-b border-slate-200 hover:bg-slate-50 transition-colors cursor-pointer select-none h-9"
                    :class="room.status === ROOM_STATUSES.OCCUPIED ? 'bg-[#97d5ff]/40 hover:bg-[#97d5ff]/60' : 'bg-white'"
                    @click="handleRoomClick(room)"
                    @contextmenu.prevent="handleContextMenu($event, room)"
                  >
                    <!-- TTDK (Status Dot) -->
                    <td class="p-2 border-r border-slate-200 text-center">
                      <div class="flex items-center justify-center gap-1">
                        <span
                          v-if="hasArrivalToday(room)"
                          class="w-2.5 h-2.5 rounded-full block border border-white/20 shadow-sm bg-emerald-500"
                        ></span>
                        <span
                          v-if="hasDepartureToday(room)"
                          class="w-2.5 h-2.5 rounded-full block border border-white/20 shadow-sm bg-red-500"
                        ></span>
                      </div>
                    </td>
                    <!-- Nhận phòng trễ -->
                    <td class="p-2 border-r border-slate-200 text-center" :class="TEXT_THEME.tableCell">-</td>
                    <!-- Chuyển phòng kế hoạch -->
                    <td class="p-2 border-r border-slate-200 text-center" :class="TEXT_THEME.tableCell">-</td>
                    <!-- TT Phòng (Status Icon) -->
                    <td class="p-2 border-r border-slate-200 text-center" :class="TEXT_THEME.tableCell">
                      <div class="flex items-center justify-center">
                        <RoomIcon 
                          :name="getRoomStatusIconName(room)" 
                          :monochrome="getRoomStatusIconName(room) === 'ooo'"
                          class="w-5 h-5 text-slate-600" 
                        />
                      </div>
                    </td>
                    <!-- Thêm giường -->
                    <td class="p-2 border-r border-slate-200 text-center" :class="TEXT_THEME.tableCell">-</td>
                    <!-- Yêu cầu DB -->
                    <td class="p-2 border-r border-slate-200 text-center" :class="TEXT_THEME.tableCell">-</td>
                    <!-- Loại phòng -->
                    <td class="p-2 border-r border-slate-200 text-center" :class="TEXT_THEME.tableCell">
                      {{ room.room_type || room.room_class?.code }}
                    </td>
                    <!-- Dạng phòng -->
                    <td class="p-2 border-r border-slate-200 text-center text-[11px]" :class="TEXT_THEME.tableCell">
                      {{ getRoomTypeShape(room) }}
                    </td>
                    <!-- Phòng -->
                    <td class="p-2 border-r border-slate-200 text-center text-[13px]" :class="TEXT_THEME.tableCell">
                      <span :class="isRoomNumberRed(room) ? 'text-red-500 font-bold' : ''" class="flex items-center justify-center gap-1">
                        {{ room.room_number }}
                        <span v-if="room.is_do_not_move" class="inline-flex items-center text-red-500" title="Khóa chuyển phòng (Do Not Move)">
                          <svg class="w-3.5 h-3.5 fill-current" viewBox="0 0 24 24">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2" fill="none" stroke="currentColor" stroke-width="2.5"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4" fill="none" stroke="currentColor" stroke-width="2.5"></path>
                          </svg>
                        </span>
                      </span>
                    </td>
                    <!-- Tên khách -->
                    <td class="p-2 border-r border-slate-200 truncate" :class="TEXT_THEME.tableCell">
                      {{ getMockGuestName(room) }}
                    </td>
                    <!-- Mã DK -->
                    <td class="p-2 border-r border-slate-200 text-center text-[11px]" :class="TEXT_THEME.tableCell">
                      {{ getMockRegId(room) }}
                    </td>
                    <!-- Tên đăng ký -->
                    <td class="p-2 border-r border-slate-200 text-[11px] truncate" :class="TEXT_THEME.tableCell">
                      {{ getMockRegName(room) }}
                    </td>
                    <!-- Ngày đến -->
                    <td class="p-2 border-r border-slate-200 text-center text-[11px]" :class="TEXT_THEME.tableCell">
                      {{ formatDateShort(room.check_in) || (room.status === ROOM_STATUSES.OCCUPIED || room.status === ROOM_STATUSES.RESERVED ? '09-06-2026' : '') }}
                    </td>
                    <!-- Ngày đi -->
                    <td class="p-2 border-r border-slate-200 text-center text-[11px]" :class="TEXT_THEME.tableCell">
                      {{ formatDateShort(room.check_out) || (room.status === ROOM_STATUSES.OCCUPIED || room.status === ROOM_STATUSES.RESERVED ? '14-06-2026' : '') }}
                    </td>
                    <!-- Công ty -->
                    <td class="p-2 border-r border-slate-200 text-[11px] truncate" :class="TEXT_THEME.tableCell">
                      {{ getMockCompany(room) }}
                    </td>
                    <!-- Tầng -->
                    <td class="p-2 text-center" :class="TEXT_THEME.tableCell">
                      {{ room.floor }}
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

          </div>
        </div>

        <!-- RIGHT FILTER PANEL (BỘ LỌC) (Only displayed for Room Map tab) -->
        <div 
          v-if="currentTab === 'room-map'"
          class="filter-panel-wrapper overflow-hidden flex shrink-0 bg-white h-full"
          :class="showFilters ? 'border-l border-slate-200' : 'border-l-0'"
          :style="{ width: showFilters ? '320px' : '0px' }"
        >
          <aside class="w-80 bg-white p-5 flex flex-col gap-4 overflow-y-auto h-full select-none">
            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
              <h3 class="text-sm uppercase tracking-wider" :class="TEXT_THEME.menuTitle">{{ t('roomMap.filterTitle') }}</h3>
              <div class="flex items-center gap-2.5">
                <button @click="resetAllFilters" class="text-xs text-blue-500 hover:underline bg-transparent border-none cursor-pointer font-normal">
                  {{ t('roomMap.clearFilter') }}
                </button>
                <button @click="showFilters = false" class="p-1.5 hover:bg-slate-100 rounded-lg text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer flex items-center justify-center transition-colors" :title="t('roomMap.all') === 'All' ? 'Close' : 'Đóng'">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>
            </div>
            
            <div class="flex flex-col gap-3.5 text-xs">
              <!-- Ngày filter -->
              <div class="flex flex-col gap-1.5">
                <span :class="TEXT_THEME.sidebarLabel">{{ t('roomMap.date') }}</span>
                <input type="date" v-model="rawDate" class="border border-slate-200 rounded-lg p-2.5 focus:outline-none focus:ring-2 focus:ring-[#97d5ff] bg-white" :class="TEXT_THEME.sidebarLabel" />
              </div>

              <!-- Tầng filter -->
              <div class="flex flex-col gap-1.5">
                <span :class="TEXT_THEME.sidebarLabel">{{ t('roomMap.floorLabel') }}</span>
                <select v-model="roomStore.filters.floor" class="border border-slate-200 rounded-lg p-2.5 focus:outline-none focus:ring-2 focus:ring-[#97d5ff] bg-white" :class="TEXT_THEME.sidebarLabel">
                  <option :value="null">{{ t('roomMap.all') }}</option>
                  <option v-for="floor in uniqueFloors" :key="floor" :value="floor">{{ t('roomMap.floor', { floor }) }}</option>
                </select>
              </div>

              <!-- Loại phòng filter -->
              <div class="flex flex-col gap-1.5">
                <span :class="TEXT_THEME.sidebarLabel">{{ t('roomMap.roomTypeLabel') }}</span>
                <select v-model="roomStore.filters.roomType" class="border border-slate-200 rounded-lg p-2.5 focus:outline-none focus:ring-2 focus:ring-[#97d5ff] bg-white" :class="TEXT_THEME.sidebarLabel">
                  <option :value="null">{{ t('roomMap.all') }}</option>
                  <option v-for="type in uniqueRoomTypes" :key="type" :value="type">{{ type }}</option>
                </select>
              </div>

              <!-- Trạng thái lưu trú checkboxes -->
              <div class="flex flex-col gap-2 pt-2">
                <span :class="TEXT_THEME.sidebarLabel">{{ t('roomMap.statusLabel') }}</span>
                <div class="flex flex-col gap-2">
                  <label v-for="st in [
                    { key: ROOM_STATUSES.AVAILABLE, labelKey: 'filterVacant' },
                    { key: ROOM_STATUSES.OCCUPIED, labelKey: 'filterOccupied' },
                    { key: ROOM_STATUSES.RESERVED, labelKey: 'filterArrival' },
                    { key: ROOM_STATUSES.CHECKOUT, labelKey: 'filterDeparture' },
                    { key: ROOM_STATUSES.MAINTENANCE, labelKey: 'filterLocked' }
                  ]" :key="st.key" class="flex items-center gap-2 cursor-pointer" :class="TEXT_THEME.sidebarLabel">
                    <input type="checkbox" :checked="roomStore.filters.status === st.key" 
                      @change="toggleStatusFilter(st.key)"
                      class="rounded border-slate-300 text-sky-600 focus:ring-[#97d5ff] h-4.5 w-4.5" />
                    <span>{{ t('roomMap.' + st.labelKey) }}</span>
                  </label>
                </div>
              </div>

              <!-- Trạng thái buồng phòng -->
              <div class="flex flex-col gap-1.5 pt-2">
                <span :class="TEXT_THEME.sidebarLabel">{{ t('roomMap.housekeepingStatus') }}</span>
                <select class="border border-slate-200 rounded-lg p-2.5 focus:outline-none focus:ring-2 focus:ring-[#97d5ff] bg-white cursor-not-allowed opacity-60" :class="TEXT_THEME.sidebarLabel" disabled>
                  <option value="all">{{ t('roomMap.all') }}</option>
                </select>
              </div>
            </div>

            <button @click="resetAllFilters" class="mt-auto w-full py-3 bg-[#97d5ff] hover:bg-[#7bc4ff] text-black rounded-xl text-xs tracking-wider uppercase transition-colors shadow-xs border-none cursor-pointer font-normal">
              {{ t('roomMap.applyFilters') }}
            </button>
          </aside>
        </div>

        </template>
      </div>
    </div>


    <!-- Booking Detail Modal -->
    <BookingDetailModal
      v-if="showBookingDetailModal && selectedBookingRoom"
      :room="selectedBookingRoom"
      @close="closeBookingDetailModal"
      @refresh="refreshRoomMapAfterGuestChange"
    />

    <!-- Hover Tooltip -->
    <Teleport to="body">
      <Transition name="tooltip-fade">
        <div
          v-if="hoverTooltip.show && hoverTooltip.room"
          class="fixed z-[99999] pointer-events-auto bg-[#2e2e2e] text-[#f1f5f9] border border-neutral-700/60 rounded-xl shadow-2xl p-3.5 w-[320px] text-[11px] leading-relaxed -translate-x-1/2"
          :class="hoverTooltip.isBelow ? 'translate-y-0' : '-translate-y-full'"
          :style="{ top: hoverTooltip.y + 'px', left: hoverTooltip.x + 'px' }"
          @mouseenter="cancelHide"
          @mouseleave="hideTooltip"
        >
        <!-- Header: Dates and Booking Code -->
        <div class="flex items-center justify-between font-bold border-b border-neutral-700/60 pb-1.5 mb-2 text-[12px] text-white">
          <div class="flex items-center gap-1.5">
            <span class="text-xs">🟢</span>
            <span>{{ formatTooltipDate(hoverTooltip.room.arrival_date) }}</span>
            <span class="text-neutral-500 font-normal">-</span>
            <span class="text-xs">🔴</span>
            <span>{{ formatTooltipDate(hoverTooltip.room.departure_date) }}</span>
          </div>
          <div>
            <span class="text-neutral-400 font-normal">Mã ĐK:</span>
            <span class="ml-1 text-sky-400">{{ hoverTooltip.room.booking_code }}</span>
          </div>
        </div>

        <!-- Details list -->
        <ul class="space-y-1 pl-0 list-none m-0 text-neutral-300">
          <li class="flex items-start gap-1">
            <span class="text-neutral-500">•</span>
            <span>Tên ĐK: <strong class="text-white">{{ hoverTooltip.room.booking_name }}</strong></span>
          </li>
          <li class="flex items-start gap-1">
            <span class="text-neutral-500">•</span>
            <span>Tên: <strong class="text-white">{{ hoverTooltip.room.guest_name }}</strong></span>
          </li>
          <li class="flex items-start gap-1">
            <span class="text-neutral-500">•</span>
            <span>{{ hoverTooltip.room.room_type_name }} (Phòng {{ hoverTooltip.room.room_number }})</span>
          </li>
          <li class="flex items-start gap-1">
            <span class="text-neutral-500">•</span>
            <span>Đêm: {{ hoverTooltip.room.nights }}</span>
          </li>
          <li class="flex items-center gap-2">
            <span class="text-neutral-500">•</span>
            <span class="flex items-center gap-1">
              {{ hoverTooltip.room.adults }} 🧑
              {{ hoverTooltip.room.children }} 🧒
              {{ hoverTooltip.room.babies }} 👶
            </span>
          </li>
          <li class="flex items-center justify-between">
            <span class="flex items-center gap-1">
              <span class="text-neutral-500">•</span>
              <span>Thời gian đến: {{ hoverTooltip.room.arrival_time }}</span>
            </span>
            <strong class="text-amber-400 text-xs">{{ formatTooltipPrice(hoverTooltip.room.rate) }}</strong>
          </li>
        </ul>

        <!-- Divider -->
        <div class="h-px bg-neutral-700/60 my-2"></div>

        <!-- Lower Section (Description/Company details) -->
        <div class="text-neutral-400 space-y-1">
          <div class="uppercase font-bold text-neutral-300">
            1 {{ hoverTooltip.room.room_type_name }} - {{ hoverTooltip.room.adults > 2 ? 'TRPL' : 'DBL' }} ({{ hoverTooltip.room.nights }} ĐÊM)*
          </div>
          <div>{{ formatTooltipPrice(hoverTooltip.room.rate) }}/R/N</div>
          <div v-if="hoverTooltip.room.company_name" class="uppercase text-neutral-300">CTY: {{ hoverTooltip.room.company_name }}</div>
          <div v-if="hoverTooltip.room.booking_note" class="text-neutral-400 italic">Ghi chú: {{ hoverTooltip.room.booking_note }}</div>
          <div v-if="hoverTooltip.room.special_requests" class="text-neutral-400 italic">Yêu cầu: {{ hoverTooltip.room.special_requests }}</div>

          <div class="h-px bg-neutral-700/30 my-1.5" v-if="hoverTooltip.room.guest_details && hoverTooltip.room.guest_details.length > 0"></div>
          
          <template v-if="hoverTooltip.room.guest_details && hoverTooltip.room.guest_details.length > 0">
            <div class="text-neutral-300 font-bold uppercase text-[10px] tracking-wider mb-0.5">Tên khách:</div>
            <div v-for="(gName, idx) in hoverTooltip.room.guest_details" :key="idx" class="uppercase text-neutral-200 pl-1">
              • {{ gName }}
            </div>
          </template>
        </div>

        <!-- Triangle Pointer -->
        <div
          class="absolute left-1/2 -translate-x-1/2 w-0 h-0 border-l-[6px] border-l-transparent border-r-[6px] border-r-transparent"
          :class="hoverTooltip.isBelow
            ? 'top-0 -translate-y-full border-b-[6px] border-b-[#2e2e2e] border-t-0'
            : 'bottom-0 translate-y-full border-t-[6px] border-t-[#2e2e2e] border-b-0'"
        ></div>
      </div>
      </Transition>
    </Teleport>

    <!-- Teleported Context Menu -->
    <Teleport to="body">
      <div
        v-if="contextMenu.show && contextMenu.room"
        class="fixed z-[9999] bg-[#eaeaea] border border-slate-300 rounded-xl shadow-2xl py-1.5 w-[220px] select-none animate-[fadeIn_0.15s_ease-out]"
        :class="TEXT_THEME.menuItem"
        :style="{ top: contextMenu.y + 'px', left: contextMenu.x + 'px' }"
        @click.stop
      >
        <!-- Thông tin -->
        <button
          v-if="contextMenu.room.booking_code"
          @click="showRoomInfo(contextMenu.room)"
          class="w-full flex items-center gap-2.5 px-3 py-2 text-xs hover:bg-slate-200 transition-colors text-left bg-transparent border-none cursor-pointer"
          :class="TEXT_THEME.menuItem"
        >
          <svg class="w-4.5 h-4.5 text-cyan-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10" />
            <line x1="12" y1="16" x2="12" y2="12" />
            <line x1="12" y1="8" x2="12.01" y2="8" />
          </svg>
          <span>{{ t('roomMap.info') }}</span>
        </button>

        <!-- Đăng ký -->
        <button
          @click="triggerMenuItem('Đăng ký')"
          class="w-full flex items-center gap-2.5 px-3 py-2 text-xs hover:bg-slate-200 transition-colors text-left bg-transparent border-none cursor-pointer"
          :class="TEXT_THEME.menuItem"
        >
          <svg class="w-4.5 h-4.5 text-sky-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
            <circle cx="9" cy="7" r="4" />
            <line x1="19" y1="8" x2="19" y2="14" />
            <line x1="16" y1="11" x2="22" y2="11" />
          </svg>
          <span>{{ t('roomMap.registration') }}</span>
        </button>

        <!-- Hóa đơn -->
        <button
          @click="triggerMenuItem('Hóa đơn')"
          class="w-full flex items-center gap-2.5 px-3 py-2 text-xs hover:bg-slate-200 transition-colors text-left bg-transparent border-none cursor-pointer"
          :class="TEXT_THEME.menuItem"
        >
          <svg class="w-4.5 h-4.5 text-[#0284c7]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
            <polyline points="14 2 14 8 20 8" />
            <line x1="16" y1="13" x2="8" y2="13" />
            <line x1="16" y1="17" x2="8" y2="17" />
          </svg>
          <span>{{ t('roomMap.invoice') }}</span>
        </button>

        <!-- Nhóm hóa đơn -->
        <button
          @click="triggerMenuItem('Nhóm hóa đơn')"
          class="w-full flex items-center gap-2.5 px-3 py-2 text-xs hover:bg-slate-200 transition-colors text-left bg-transparent border-none cursor-pointer"
          :class="TEXT_THEME.menuItem"
        >
          <svg class="w-4.5 h-4.5 text-[#0284c7]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <ellipse cx="12" cy="5" rx="9" ry="3" />
            <path d="M3 5v6c0 1.66 4 3 9 3s9-1.34 9-3V5" />
            <path d="M3 11v6c0 1.66 4 3 9 3s9-1.34 9-3v-6" />
          </svg>
          <span>{{ t('roomMap.groupInvoice') }}</span>
        </button>

        <div class="h-px bg-slate-300 my-1"></div>

        <!-- Chuyển Phòng Group -->
        <div v-if="contextMenu.room.booking_code" class="px-3 py-1 text-[10px] font-bold text-slate-400 uppercase tracking-wider mt-1 border-t border-slate-300 select-none">
          Chuyển Phòng
        </div>

        <button
          v-if="contextMenu.room.booking_code"
          @click="contextMenu.room.is_do_not_move ? null : lockRoomMove(contextMenu.room)"
          class="w-full flex items-center gap-2.5 px-3 py-2 text-xs transition-colors text-left bg-transparent border-none"
          :class="[
            TEXT_THEME.menuItem,
            contextMenu.room.is_do_not_move ? 'opacity-40 cursor-not-allowed text-slate-400' : 'hover:bg-slate-200 cursor-pointer'
          ]"
        >
          <svg class="w-4 h-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
          </svg>
          <span>Khóa chuyển phòng</span>
        </button>

        <button
          v-if="contextMenu.room.booking_code"
          @click="!contextMenu.room.is_do_not_move ? null : unlockRoomMove(contextMenu.room)"
          class="w-full flex items-center gap-2.5 px-3 py-2 text-xs transition-colors text-left bg-transparent border-none"
          :class="[
            TEXT_THEME.menuItem,
            !contextMenu.room.is_do_not_move ? 'opacity-40 cursor-not-allowed text-slate-400' : 'hover:bg-slate-200 cursor-pointer'
          ]"
        >
          <svg class="w-4 h-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
            <path d="M7 11V7a5 5 0 0 1 9.9-1"></path>
          </svg>
          <span>Mở chuyển phòng</span>
        </button>

        <!-- Thông báo -->
        <button
          @click="triggerMenuItem('Thông báo')"
          class="w-full flex items-center gap-2.5 px-3 py-2 text-xs hover:bg-slate-200 transition-colors text-left bg-transparent border-none cursor-pointer"
          :class="TEXT_THEME.menuItem"
        >
          <svg class="w-4.5 h-4.5 text-sky-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" />
            <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" />
          </svg>
          <span>{{ t('roomMap.notifications') }}</span>
        </button>

        <!-- In phiếu ăn sáng -->
        <button
          @click="triggerMenuItem('In phiếu ăn sáng')"
          class="w-full flex items-center gap-2.5 px-3 py-2 text-xs hover:bg-slate-200 transition-colors text-left bg-transparent border-none cursor-pointer"
          :class="TEXT_THEME.menuItem"
        >
          <svg class="w-4.5 h-4.5 text-[#0284c7]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M17 8h1a4 4 0 1 1 0 8h-1" />
            <path d="M3 8h14v9a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4Z" />
            <line x1="6" y1="2" x2="6" y2="4" />
            <line x1="10" y1="2" x2="10" y2="4" />
            <line x1="14" y1="2" x2="14" y2="4" />
          </svg>
          <span>{{ t('roomMap.printBreakfast') }}</span>
        </button>

        <!-- In mẫu đăng ký -->
        <button
          @click="triggerMenuItem('In mẫu đăng ký')"
          class="w-full flex items-center gap-2.5 px-3 py-2 text-xs hover:bg-slate-200 transition-colors text-left bg-transparent border-none cursor-pointer"
          :class="TEXT_THEME.menuItem"
        >
          <svg class="w-4.5 h-4.5 text-sky-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="6 9 6 2 18 2 18 9" />
            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2" />
            <rect x="6" y="14" width="12" height="8" />
          </svg>
          <span>{{ t('roomMap.printRegForm') }}</span>
        </button>

        <!-- Chuyển tình trạng phòng (Submenu Trigger) -->
        <div class="relative group mt-0.5">
          <div
            class="flex items-center justify-between px-3 py-2 text-xs font-normal transition-colors cursor-pointer select-none hover:brightness-90"
            :style="{
              background: 'var(--pms-custom-theme, #006bdb)',
              color: 'var(--pms-custom-theme-text, #ffffff)'
            }"
          >
            <div class="flex items-center gap-2.5">
              <svg class="w-4.5 h-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67" />
              </svg>
              <span>{{ t('roomMap.changeRoomStatus') }}</span>
            </div>
            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="9 18 15 12 9 6" />
            </svg>
          </div>

          <!-- Submenu Panel -->
          <div
            class="absolute hidden group-hover:block bg-[#eaeaea] border border-slate-300 rounded-xl shadow-2xl py-1.5 w-60 z-[99999]"
            :class="[
              contextMenu.isLeft ? 'right-full mr-1' : 'left-full ml-1',
              'bottom-0'
            ]"
          >
            <!-- Sẵn sàng -->
            <button
              @click="changeRoomStatus(contextMenu.room, ROOM_STATUSES.AVAILABLE)"
              class="w-full flex items-center gap-3 px-4 py-2.5 text-xs hover:bg-slate-200 transition-colors text-left bg-transparent border-none cursor-pointer"
              :class="TEXT_THEME.menuItem"
            >
              <RoomIcon name="double-check" class="w-5 h-5 text-blue-500" />
              <span>{{ t('roomMap.available') }}</span>
            </button>

            <!-- Phòng bẩn -->
            <button
              @click="changeRoomStatus(contextMenu.room, ROOM_STATUSES.DIRTY)"
              class="w-full flex items-center gap-3 px-4 py-2.5 text-xs hover:bg-slate-200 transition-colors text-left bg-transparent border-none cursor-pointer"
              :class="TEXT_THEME.menuItem"
            >
              <RoomIcon name="dirty" class="w-5 h-5 text-amber-600" />
              <span>{{ t('roomMap.dirty') }}</span>
            </button>

            <!-- Lau dọn -->
            <button
              @click="changeRoomStatus(contextMenu.room, ROOM_STATUSES.CHECKOUT)"
              class="w-full flex items-center gap-3 px-4 py-2.5 text-xs hover:bg-slate-200 transition-colors text-left bg-transparent border-none cursor-pointer"
              :class="TEXT_THEME.menuItem"
            >
              <RoomIcon name="clean" class="w-5 h-5 text-cyan-500" />
              <span>{{ t('roomMap.cleaning') }}</span>
            </button>

            <!-- Phòng OOO -->
            <button
              v-if="contextMenu.room && contextMenu.room.status !== ROOM_STATUSES.OCCUPIED && contextMenu.room.status !== ROOM_STATUSES.CHECKOUT"
              @click="changeRoomStatus(contextMenu.room, ROOM_STATUSES.MAINTENANCE, 'OOO')"
              class="w-full flex items-center gap-3 px-4 py-2.5 text-xs hover:bg-slate-200 transition-colors text-left bg-transparent border-none cursor-pointer"
              :class="TEXT_THEME.menuItem"
            >
              <RoomIcon name="ooo" class="w-5 h-5 text-amber-500" />
              <span>Phòng OOO</span>
            </button>

            <!-- Phòng OOS -->
            <button
              v-if="contextMenu.room && contextMenu.room.status !== ROOM_STATUSES.OCCUPIED && contextMenu.room.status !== ROOM_STATUSES.CHECKOUT"
              @click="changeRoomStatus(contextMenu.room, ROOM_STATUSES.MAINTENANCE, 'OOS')"
              class="w-full flex items-center gap-3 px-4 py-2.5 text-xs hover:bg-slate-200 transition-colors text-left bg-transparent border-none cursor-pointer"
              :class="TEXT_THEME.menuItem"
            >
              <RoomIcon name="oos" class="w-5 h-5 text-emerald-500" />
              <span>Phòng OOS</span>
            </button>

            <!-- Dịch vụ dọn phòng -->
            <button
              v-if="contextMenu.room && (contextMenu.room.status === ROOM_STATUSES.OCCUPIED || contextMenu.room.status === ROOM_STATUSES.CHECKOUT)"
              @click="changeRoomStatus(contextMenu.room, ROOM_STATUSES.MAINTENANCE)"
              class="w-full flex items-center gap-3 px-4 py-2.5 text-xs hover:bg-slate-200 transition-colors text-left bg-transparent border-none cursor-pointer"
              :class="TEXT_THEME.menuItem"
            >
              <RoomIcon name="housekeeping-service" class="w-5 h-5 text-sky-500" />
              <span>{{ t('roomMap.maintenance') }}</span>
            </button>

            <!-- Phòng ưu tiên -->
            <button
              @click="changeRoomStatus(contextMenu.room, ROOM_STATUSES.RESERVED)"
              class="w-full flex items-center gap-3 px-4 py-2.5 text-xs hover:bg-slate-200 transition-colors text-left bg-transparent border-none cursor-pointer"
              :class="TEXT_THEME.menuItem"
            >
              <RoomIcon name="priority" class="w-5 h-5 text-sky-500" />
              <span>{{ t('roomMap.priorityRoom') }}</span>
            </button>

            <!-- Phòng không làm phiền -->
            <button
              @click="changeRoomStatus(contextMenu.room, ROOM_STATUSES.OCCUPIED)"
              class="w-full flex items-center gap-3 px-4 py-2.5 text-xs hover:bg-slate-200 transition-colors text-left bg-transparent border-none cursor-pointer"
              :class="TEXT_THEME.menuItem"
            >
              <RoomIcon name="dnd" class="w-5 h-5 text-slate-500" />
              <span>{{ t('roomMap.dnd') }}</span>
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Thống kê Modal (Popup) -->
    <div v-if="showStatsModal" class="fixed inset-0 z-[99999] flex items-center justify-center bg-black/55 backdrop-blur-[2px] p-4 select-none">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl overflow-hidden border border-slate-200 flex flex-col max-h-[90vh] animate-[fadeIn_0.2s_ease-out]">
        <!-- Header -->
        <div class="bg-blue-600 text-white px-5 py-3 flex items-center justify-between">
          <h3 class="text-sm font-extrabold tracking-wide uppercase">Thống kê</h3>
          <button @click="showStatsModal = false" class="text-white/80 hover:text-white bg-transparent border-none text-lg font-black cursor-pointer leading-none">
            ✕
          </button>
        </div>

        <!-- Scrollable content -->
        <div class="p-6 overflow-y-auto grid grid-cols-1 md:grid-cols-2 gap-5 bg-slate-50 flex-1">
          <!-- 1. Tổng quan Card -->
          <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-xs">
            <h4 class="text-slate-900 font-extrabold text-xs mb-3 pb-1.5 border-b border-slate-100">Tổng quan</h4>
            <div class="flex flex-col gap-2.5 text-xs text-slate-700">
              <div class="flex items-center justify-between">
                <span class="font-semibold">Tổng phòng</span>
                <span class="px-2 py-0.5 bg-slate-100 border border-slate-200 rounded font-bold text-slate-800 text-right min-w-[50px] inline-block tabular-nums">{{ roomStore.rooms.length }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="font-semibold text-amber-700">OOO</span>
                <span class="px-2 py-0.5 bg-amber-50 border border-amber-200 rounded font-bold text-amber-800 text-right min-w-[50px] inline-block tabular-nums">{{ roomStore.rooms.filter(r => r.status === ROOM_STATUSES.MAINTENANCE && r.lock_type !== 'OOS').length }}</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="font-semibold text-emerald-700">OOS</span>
                <span class="px-2 py-0.5 bg-emerald-50 border border-emerald-200 rounded font-bold text-emerald-800 text-right min-w-[50px] inline-block tabular-nums">{{ roomStore.rooms.filter(r => r.status === ROOM_STATUSES.MAINTENANCE && r.lock_type === 'OOS').length }}</span>
              </div>
              <div class="flex items-center justify-between pt-1 border-t border-dashed border-slate-200 font-bold text-slate-900">
                <span>Tổng phòng có thể bán</span>
                <span class="px-2 py-0.5 bg-blue-50 border border-blue-200 rounded text-blue-700 text-right min-w-[50px] inline-block tabular-nums">{{ roomStore.rooms.length - roomStore.rooms.filter(r => r.status === ROOM_STATUSES.MAINTENANCE).length }}</span>
              </div>
            </div>
          </div>

          <!-- 2. Buồng phòng Card -->
          <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-xs">
            <h4 class="text-slate-900 font-extrabold text-xs mb-3 pb-1.5 border-b border-slate-100">Buồng phòng</h4>
            <table class="w-full text-xs border-collapse">
              <thead>
                <tr class="text-slate-500 font-bold border-b border-slate-100">
                  <th class="pb-1.5 text-left font-semibold">Trạng thái</th>
                  <th class="pb-1.5 text-right w-16 font-bold text-[11px] text-red-500">Occ</th>
                  <th class="pb-1.5 text-right w-16 font-bold text-[11px] text-emerald-500">Vac</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 text-slate-700">
                <tr class="h-8">
                  <td class="font-semibold">Phòng sẵn sàng</td>
                  <td class="text-right font-bold tabular-nums">0</td>
                  <td class="text-right font-bold tabular-nums">{{ roomStore.rooms.filter(r => r.status === ROOM_STATUSES.AVAILABLE && r.is_clean).length }}</td>
                </tr>
                <tr class="h-8">
                  <td class="font-semibold">Phòng sạch</td>
                  <td class="text-right font-bold tabular-nums">0</td>
                  <td class="text-right font-bold text-slate-400 tabular-nums">0</td>
                </tr>
                <tr class="h-8">
                  <td class="font-semibold">Phòng dơ</td>
                  <td class="text-right font-bold text-red-600 tabular-nums">{{ roomStore.rooms.filter(r => r.status === ROOM_STATUSES.OCCUPIED && (r.status === ROOM_STATUSES.DIRTY || !r.is_clean)).length }}</td>
                  <td class="text-right font-bold text-red-600 tabular-nums">{{ roomStore.rooms.filter(r => r.status !== ROOM_STATUSES.OCCUPIED && (r.status === ROOM_STATUSES.DIRTY || !r.is_clean)).length }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- 3. Trạng thái phòng Card -->
          <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-xs md:col-span-1">
            <h4 class="text-slate-900 font-extrabold text-xs mb-3 pb-1.5 border-b border-slate-100">Trạng thái phòng</h4>
            <table class="w-full text-xs border-collapse">
              <thead>
                <tr class="text-slate-500 font-bold border-b border-slate-100">
                  <th class="pb-1.5 text-left font-semibold">Trạng thái</th>
                  <th class="pb-1.5 text-right w-16 font-bold text-[11px]">Room</th>
                  <th class="pb-1.5 text-right w-16 font-bold text-[11px]">Pax</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 text-slate-700">
                <tr class="h-7.5">
                  <td class="font-semibold">Phòng chưa trả</td>
                  <td class="text-right font-bold text-slate-400 tabular-nums">0</td>
                  <td class="text-right font-bold text-slate-400 tabular-nums">0</td>
                </tr>
                <tr class="h-7.5">
                  <td class="font-semibold">Phòng đã trả</td>
                  <td class="text-right font-bold text-red-500 tabular-nums">{{ roomStore.rooms.filter(r => r.status === ROOM_STATUSES.CHECKOUT).length }}</td>
                  <td class="text-right font-bold text-red-500 tabular-nums">{{ roomStore.rooms.filter(r => r.status === ROOM_STATUSES.CHECKOUT).reduce((sum, r) => sum + getGuestCount(r), 0) }}</td>
                </tr>
                <tr class="h-7.5">
                  <td class="font-semibold">Phòng đến</td>
                  <td class="text-right font-bold text-emerald-600 tabular-nums">{{ roomStore.rooms.filter(r => r.status === ROOM_STATUSES.RESERVED).length }}</td>
                  <td class="text-right font-bold text-emerald-600 tabular-nums">{{ roomStore.rooms.filter(r => r.status === ROOM_STATUSES.RESERVED).reduce((sum, r) => sum + getGuestCount(r), 0) }}</td>
                </tr>
                <tr class="h-7.5">
                  <td class="font-semibold">Phòng đến đã gán phòng</td>
                  <td class="text-right font-bold text-emerald-600 tabular-nums">{{ roomStore.rooms.filter(r => r.status === ROOM_STATUSES.RESERVED).length }}</td>
                  <td class="text-right font-bold text-emerald-600 tabular-nums">{{ roomStore.rooms.filter(r => r.status === ROOM_STATUSES.RESERVED).reduce((sum, r) => sum + getGuestCount(r), 0) }}</td>
                </tr>
                <tr class="h-7.5">
                  <td class="font-semibold">Phòng đã đến</td>
                  <td class="text-right font-bold text-slate-800 tabular-nums">{{ roomStore.rooms.filter(r => r.status === ROOM_STATUSES.OCCUPIED && r.id % 7 === 1).length }}</td>
                  <td class="text-right font-bold text-slate-800 tabular-nums">{{ roomStore.rooms.filter(r => r.status === ROOM_STATUSES.OCCUPIED && r.id % 7 === 1).reduce((sum, r) => sum + getGuestCount(r), 0) }}</td>
                </tr>
                <tr class="h-7.5">
                  <td class="font-semibold text-sky-700">Phòng đang ở</td>
                  <td class="text-right font-bold text-sky-700 tabular-nums">{{ roomStore.rooms.filter(r => r.status === ROOM_STATUSES.OCCUPIED).length }}</td>
                  <td class="text-right font-bold text-sky-700 tabular-nums">{{ roomStore.rooms.filter(r => r.status === ROOM_STATUSES.OCCUPIED).reduce((sum, r) => sum + getGuestCount(r), 0) }}</td>
                </tr>
                <tr class="h-7.5">
                  <td class="font-semibold">Trả phòng sớm</td>
                  <td class="text-right font-bold text-slate-400 tabular-nums">0</td>
                  <td class="text-right font-bold text-slate-400 tabular-nums">0</td>
                </tr>
                <tr class="h-7.5">
                  <td class="font-semibold">Phòng ở theo giờ</td>
                  <td class="text-right font-bold text-slate-400 tabular-nums">0</td>
                  <td class="text-right font-bold text-slate-400 tabular-nums">0</td>
                </tr>
                <tr class="h-7.5">
                  <td class="font-semibold">Đặt phòng trong ngày</td>
                  <td class="text-right font-bold text-slate-400 tabular-nums">0</td>
                  <td class="text-right font-bold text-slate-400 tabular-nums">0</td>
                </tr>
                <tr class="h-7.5">
                  <td class="font-semibold">Khách vãng lai</td>
                  <td class="text-right font-bold text-slate-400 tabular-nums">0</td>
                  <td class="text-right font-bold text-slate-400 tabular-nums">0</td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- 4. Dự báo cuối ngày Card -->
          <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-xs md:col-span-1 flex flex-col justify-between">
            <div>
              <h4 class="text-slate-900 font-extrabold text-xs mb-3 pb-1.5 border-b border-slate-100">Dự báo cuối ngày</h4>
              <table class="w-full text-xs border-collapse">
                <thead>
                  <tr class="text-slate-500 font-bold border-b border-slate-100">
                    <th class="pb-1.5 text-left font-semibold">Dự báo</th>
                    <th class="pb-1.5 text-right w-14 font-bold text-[11px]">Room</th>
                    <th class="pb-1.5 text-right w-14 font-bold text-[11px]">Pax</th>
                    <th class="pb-1.5 text-right w-16 font-bold text-[11px]">%</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700">
                  <tr class="h-7">
                    <td class="font-semibold">Khách lẻ</td>
                    <td class="text-right font-bold tabular-nums">{{ roomStore.rooms.filter(r => r.status === ROOM_STATUSES.OCCUPIED).length }}</td>
                    <td class="text-right font-bold tabular-nums">{{ roomStore.rooms.filter(r => r.status === ROOM_STATUSES.OCCUPIED).reduce((sum, r) => sum + getGuestCount(r), 0) }}</td>
                    <td class="text-right font-bold text-slate-400">-</td>
                  </tr>
                  <tr class="h-7">
                    <td class="font-semibold">Khách đoàn</td>
                    <td class="text-right font-bold text-slate-400 tabular-nums">0</td>
                    <td class="text-right font-bold text-slate-400 tabular-nums">0</td>
                    <td class="text-right font-bold text-slate-400">-</td>
                  </tr>
                  <tr class="h-7">
                    <td class="font-semibold">Phòng ở (ko b/g COMP.HU)</td>
                    <td class="text-right font-bold tabular-nums">{{ roomStore.rooms.filter(r => r.status === ROOM_STATUSES.OCCUPIED).length }}</td>
                    <td class="text-right font-bold tabular-nums">{{ roomStore.rooms.filter(r => r.status === ROOM_STATUSES.OCCUPIED).reduce((sum, r) => sum + getGuestCount(r), 0) }}</td>
                    <td class="text-right font-bold text-sky-600 tabular-nums">{{ roomStore.occupancyRate }}%</td>
                  </tr>
                  <tr class="h-7">
                    <td class="font-semibold">Phòng COMP</td>
                    <td class="text-right font-bold text-slate-400 tabular-nums">0</td>
                    <td class="text-right font-bold text-slate-400 tabular-nums">0</td>
                    <td class="text-right font-bold text-slate-400">-</td>
                  </tr>
                  <tr class="h-7">
                    <td class="font-semibold">Phòng ở (ko b/g HU)</td>
                    <td class="text-right font-bold tabular-nums">{{ roomStore.rooms.filter(r => r.status === ROOM_STATUSES.OCCUPIED).length }}</td>
                    <td class="text-right font-bold tabular-nums">{{ roomStore.rooms.filter(r => r.status === ROOM_STATUSES.OCCUPIED).reduce((sum, r) => sum + getGuestCount(r), 0) }}</td>
                    <td class="text-right font-bold text-sky-600 tabular-nums">{{ roomStore.occupancyRate }}%</td>
                  </tr>
                  <tr class="h-7">
                    <td class="font-semibold">Phòng nội bộ</td>
                    <td class="text-right font-bold text-slate-400 tabular-nums">0</td>
                    <td class="text-right font-bold text-slate-400 tabular-nums">0</td>
                    <td class="text-right font-bold text-slate-400">-</td>
                  </tr>
                  <tr class="h-7">
                    <td class="font-semibold text-blue-700">Phòng ở</td>
                    <td class="text-right font-bold text-blue-700 tabular-nums">{{ roomStore.rooms.filter(r => r.status === ROOM_STATUSES.OCCUPIED).length }}</td>
                    <td class="text-right font-bold text-blue-700 tabular-nums">{{ roomStore.rooms.filter(r => r.status === ROOM_STATUSES.OCCUPIED).reduce((sum, r) => sum + getGuestCount(r), 0) }}</td>
                    <td class="text-right font-bold text-blue-700 tabular-nums">{{ roomStore.occupancyRate }}%</td>
                  </tr>
                  <tr class="h-7">
                    <td class="font-semibold text-red-500">Phòng trống</td>
                    <td class="text-right font-bold text-red-500 tabular-nums">{{ roomStore.rooms.length - roomStore.rooms.filter(r => r.status === ROOM_STATUSES.OCCUPIED || r.status === ROOM_STATUSES.MAINTENANCE).length }}</td>
                    <td class="text-right font-bold text-slate-400 tabular-nums">-</td>
                    <td class="text-right font-bold text-slate-400">-</td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- Forecast Sub-section -->
            <div class="mt-4 pt-3.5 border-t border-slate-200 text-xs text-slate-700 flex flex-col gap-2 bg-slate-50 p-3 rounded-lg border">
              <h5 class="font-bold text-slate-900 mb-1 select-none">Dự báo</h5>
              <div class="flex items-center justify-between">
                <span class="font-semibold">Doanh thu</span>
                <span class="font-bold text-emerald-600">44,724,486 đ</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="font-semibold">Giá phòng trung bình (ko b/g HU)</span>
                <span class="font-bold text-slate-800">559,056 đ</span>
              </div>
              <div class="flex items-center justify-between">
                <span class="font-semibold">DT bình quân/ Tổng phòng có thể bán</span>
                <span class="font-bold text-slate-800">552,154 đ</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
@keyframes fadeIn {
  from {
    opacity: 0;
    transform: scale(0.97);
  }
  to {
    opacity: 1;
    transform: scale(1);
  }
}

@keyframes slideUp {
  from {
    opacity: 0;
    transform: translateY(16px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes cardEntrance {
  from {
    opacity: 0;
    transform: scale(0.92) translateY(12px);
  }
  to {
    opacity: 1;
    transform: scale(1) translateY(0);
  }
}

.floor-row-animate {
  animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) both;
}

.room-card-animate {
  animation: cardEntrance 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) both;
}

.pulse-dot-ring {
  position: relative;
}

.scrollbar-thin::-webkit-scrollbar {
  height: 6px;
  width: 6px;
}
.scrollbar-thin::-webkit-scrollbar-track {
  background: transparent;
}
.scrollbar-thin::-webkit-scrollbar-thumb {
  background: #97d5ff;
  border-radius: 4px;
}
.scrollbar-thin::-webkit-scrollbar-thumb:hover {
  background: #7bc4ff;
}

.filter-panel-wrapper {
  transition: width 0.45s cubic-bezier(0.16, 1, 0.3, 1), border-color 0.45s ease;
  will-change: width;
}

/* Tooltip Fade-in / Fade-out Transition */
.tooltip-fade-enter-active,
.tooltip-fade-leave-active {
  transition: opacity 0.18s cubic-bezier(0.16, 1, 0.3, 1), transform 0.18s cubic-bezier(0.16, 1, 0.3, 1);
}

.tooltip-fade-enter-from,
.tooltip-fade-leave-to {
  opacity: 0;
  transform: translate(-50%, -96%) scale(0.97) !important;
}

.tooltip-fade-enter-from.translate-y-0,
.tooltip-fade-leave-to.translate-y-0 {
  opacity: 0;
  transform: translate(-50%, 4px) scale(0.97) !important;
}
</style>






