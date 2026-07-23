<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch, nextTick } from 'vue'
import { useRoute } from 'vue-router'
import { useUiStore } from '@/stores/ui-store'
import http from '@/services/http'
import LoadingOverlay from '@/components/LoadingOverlay.vue'
import TimePicker24h from '@/components/TimePicker24h.vue'
import CopyModal from './components/CopyModal.vue'
import UpgradeModal from './components/UpgradeModal.vue'
import DepositModal from './components/DepositModal.vue'
import ServicesModal from './components/ServicesModal.vue'
import DeleteServiceModal from './components/DeleteServiceModal.vue'
import ExtraBedModal from './components/ExtraBedModal.vue'
import SystemSearchModal from './components/SystemSearchModal.vue'
import ChildBreakfastModal from './components/ChildBreakfastModal.vue'
import SpecialRequestsModal from './components/SpecialRequestsModal.vue'
import GuestInfoModal from './components/GuestInfoModal.vue'
import CancelReasonModal from './components/CancelReasonModal.vue'
import {
  fetchMarkets,
  fetchCustomerSources,
  fetchBookers,
  fetchCompanies,
  fetchUsers,
} from '@/services/company-service'
import {
  fetchBookings,
  createBooking,
  updateBooking,
  deleteBooking,
  copyBooking,
  fetchPaymentMethods,
  fetchRegistrationStatuses,
  fetchBookingInitDropdowns,
  fetchRoomClasses,
  fetchRoomRateCodes,
  fetchHotelSettings,
  fetchSystemTime,
  fetchSystemDate,
  fetchPayments,
  createPayment,
  updatePayment,
  deletePayment,
  splitPayment,
  transferPayment,
  fetchCurrencies,
  fetchAvailability,
  checkAvailability,
  fetchVacantRooms,
  autoAssignRoom,
  checkInRoom,
  upgradeRoom,
  unassignRoom,
  cancelBookingRoom,
  fetchHotelServices,
  fetchBookingRoomServices,
  createBookingRoomService,
  deleteBookingRoomServicesBulk,
  lockRoomMove,
  unlockRoomMove
} from '@/services/booking-service'

const route = useRoute()
const uiStore = useUiStore()
import { useAuthStore } from '@/stores/auth-store'
import { useRoomStore } from '@/stores/room-store'
const authStore = useAuthStore()
const roomStore = useRoomStore()

let pmsBc = null
if (typeof BroadcastChannel !== 'undefined') {
  pmsBc = new BroadcastChannel('pms-room-updates')
}

function notifyRoomUpdates() {
  roomStore.fetchRooms({ silent: true })
  roomStore.fetchStats()
  if (pmsBc) pmsBc.postMessage('rooms-updated')
}

// ==================== CONFIG & SYSTEM ====================
function formatLocalYYYYMMDD(dVal) {
  if (!dVal) return ''
  const d = new Date(dVal)
  if (isNaN(d.getTime())) return ''
  const year = d.getFullYear()
  const month = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

const systemDate = ref(formatLocalYYYYMMDD(new Date()))
const hotelSettings = ref({})
const currenciesList = ref([])
const activeCurrency = computed(() => {
  return currenciesList.value.find(c => c.is_main) || { code: 'VND', decimals_to_round: 0 }
})

// ==================== DROPDOWN DATA (từ API) ====================
const markets = ref([])
const customerSources = ref([])
const bookers = ref([])
const companies = ref([])
const paymentMethods = ref([])
const registrationStatuses = ref([])
const users = ref([])
const roomClasses = ref([])
const roomForms = ref([])
const roomRateCodes = ref([])
const activeRoomRateCodes = computed(() => {
  return (roomRateCodes.value || []).filter(rc => !rc.Disable)
})
const hotelServicesList = ref([])
const selectedServiceFilter = ref('all')
const diagnosticErrors = ref([])

// ==================== TAB MANAGEMENT ====================
const tabs = ref([])
const activeTabId = ref(null)
const isLoadingBookings = ref(false)
const isLoading = ref(true)

// --- Persistent closed tabs (localStorage) ---
const CLOSED_TABS_KEY = 'pms_closed_tabs'
function getClosedTabIds() {
  try { return JSON.parse(localStorage.getItem(CLOSED_TABS_KEY) || '[]') } catch { return [] }
}
function addClosedTabId(dbId) {
  if (!dbId) return
  const ids = getClosedTabIds()
  if (!ids.includes(String(dbId))) {
    ids.push(String(dbId))
    localStorage.setItem(CLOSED_TABS_KEY, JSON.stringify(ids))
  }
}
function removeClosedTabId(dbId) {
  if (!dbId) return
  const ids = getClosedTabIds().filter(id => id !== String(dbId))
  localStorage.setItem(CLOSED_TABS_KEY, JSON.stringify(ids))
}

// ==================== MODAL STATES ====================
const isModalOpen = ref(false)
const isEditModal = ref(false)
const isSavingModal = ref(false)
const modalSubTab = ref('info')

// ==================== DRAGGABLE MODAL POSITION ====================
const modalPos = ref({ x: 0, y: 0 })
const isDraggingModal = ref(false)
let dragStart = { x: 0, y: 0 }
let rafId = null

function startDragModal(e) {
  const ignoreTags = ['BUTTON', 'INPUT', 'SELECT', 'TEXTAREA', 'A', 'LABEL']
  if (ignoreTags.includes(e.target.tagName) || e.target.closest('button, input, select, textarea, a, label')) return
  
  isDraggingModal.value = true
  dragStart.x = e.clientX - modalPos.value.x
  dragStart.y = e.clientY - modalPos.value.y
  
  document.addEventListener('mousemove', dragModal)
  document.addEventListener('mouseup', stopDragModal)
}

function dragModal(e) {
  if (!isDraggingModal.value) return
  if (rafId) return
  
  rafId = requestAnimationFrame(() => {
    modalPos.value.x = e.clientX - dragStart.x
    modalPos.value.y = e.clientY - dragStart.y
    rafId = null
  })
}

function stopDragModal() {
  isDraggingModal.value = false
  if (rafId) {
    cancelAnimationFrame(rafId)
    rafId = null
  }
  document.removeEventListener('mousemove', dragModal)
  document.removeEventListener('mouseup', stopDragModal)
}

function closeModal() {
  isModalOpen.value = false
}

// ==================== UI STATES ====================
const showChucNangMenu = ref(false)
function handleBlurChucNang() {
  setTimeout(() => {
    showChucNangMenu.value = false
  }, 200)
}
const selectedRoomAction = ref('0')

// ==================== REDESIGN GLOBAL SEARCH & DOCK ====================
const isGlobalSearchOpen = ref(false)
const isSubListOpen = ref(false)
const isPrintPrice = ref(true)

const tableWrapRef = ref(null)
const footerScrollRef = ref(null)

function handleTableScroll() {
  if (tableWrapRef.value && footerScrollRef.value) {
    footerScrollRef.value.scrollLeft = tableWrapRef.value.scrollLeft
  }
}

function handleGlobalSearchResultClick(booking) {
  // Khi mở lại booking, xóa khỏi danh sách tab đã đóng để F5 hiện lại
  removeClosedTabId(booking.id)
  const existing = tabs.value.find(t => t.dbId === booking.id)
  if (existing) {
    activeTabId.value = existing.id
  } else {
    const newTab = { ...bookingToTab(booking) }
    tabs.value.push(newTab)
    activeTabId.value = newTab.id
  }
}

function openGlobalSearch() {
  isGlobalSearchOpen.value = true
}

function getColWidthPx(col) {
  if (!col || !col.width) return 'auto'
  const match = col.width.match(/w-\[(\d+)px\]/)
  return match ? `${match[1]}px` : 'auto'
}

// ==================== MODAL FORM ====================
const emptyForm = () => ({
  dbId: null,
  bookingCode: '',
  bookingName: '',
  color: hotelSettings.value?.ColorDefaultBookingRoomMap || '#97D5FF',
  checkIn: systemDate.value || new Date().toISOString().split('T')[0],
  checkOut: '',
  nights: 1,
  registrationStatusId: null,
  confirmDate: '',
  expiredDate: '',
  companyId: null,
  paymentMethodId: null,
  paymentValue: 0,
  externalBookingCode: '',
  salesPerson: '',
  isGit: true,
  hasVat: false,
  marketId: null,
  customerSourceId: null,
  bookerId: null,
  contactName: '',
  contactEmail: '',
  contactPhone: '',
  note: '',
  specialRequests: '',
  shuttleInfo: [],
  roomAllocations: [],
  deposits: [],
  createdBy: '',
  createdAt: '',
})

const modalForm = ref(emptyForm())
const isColorChanged = ref(false)
const isColorPickerOpen = ref(false)

const bkColorList = [
  { hex: '#C00000', order: 1 },
  { hex: '#FF0000', order: 2 },
  { hex: '#FFC000', order: 3 },
  { hex: '#FFFF00', order: 4 },
  { hex: '#92D050', order: 5 },
  { hex: '#00B050', order: 6 },
  { hex: '#00B0F0', order: 7 },
  { hex: '#0070C0', order: 8 },
  { hex: '#002060', order: 9 },
  { hex: '#6A5ACD', order: 10 },
  { hex: '#800000', order: 11 },
  { hex: '#DC143C', order: 13 },
  { hex: '#CD5C5C', order: 14 },
  { hex: '#D2691E', order: 16 },
  { hex: '#FF4500', order: 17 },
  { hex: '#FF6347', order: 18 },
  { hex: '#FFA500', order: 19 },
  { hex: '#FFD700', order: 20 },
  { hex: '#FFDEAD', order: 21 },
  { hex: '#F0E68C', order: 23 },
  { hex: '#00FF00', order: 24 },
  { hex: '#00FF7F', order: 25 },
  { hex: '#228B22', order: 26 },
  { hex: '#008B8B', order: 27 },
  { hex: '#008080', order: 28 },
  { hex: '#00CED1', order: 29 },
  { hex: '#48D1CC', order: 30 },
  { hex: '#7FFFD4', order: 31 },
  { hex: '#00FFFF', order: 32 },
  { hex: '#97D5FF', order: 33 },
  { hex: '#0000FF', order: 35 },
  { hex: '#800080', order: 38 },
  { hex: '#FF1493', order: 39 },
  { hex: '#FF69B4', order: 40 },
  { hex: '#808080', order: 41 },
  { hex: '#D3D3D3', order: 42 },
]

const currentColorRgb = computed(() => {
  const hex = (modalForm.value?.color || '#97D5FF').replace('#', '')
  let clean = hex
  if (clean.length === 3) clean = clean.split('').map(c => c + c).join('')
  const num = parseInt(clean, 16) || 0
  return {
    r: (num >> 16) & 255,
    g: (num >> 8) & 255,
    b: num & 255
  }
})

function selectBkColor(hex) {
  modalForm.value.color = hex
  isColorChanged.value = true
  isColorPickerOpen.value = false
}

const activeDepositsList = computed(() => {
  if (!modalForm.value || !modalForm.value.deposits) return []
  return modalForm.value.deposits.filter(d => d.edit_flag === 0 && (d.pack2 === 'DPR' || d.pack2 === undefined))
})

const hasActiveDeposits = computed(() => {
  return activeDepositsList.value.length > 0 && modalForm.value.paymentValue > 0
})

const firstDepositDate = computed(() => {
  if (activeDepositsList.value.length > 0) {
    return activeDepositsList.value[0].date
  }
  return ''
})

const firstDepositNote = computed(() => {
  if (activeDepositsList.value.length > 0) {
    return activeDepositsList.value[0].note || 'Ghi nhận cọc'
  }
  return 'Ghi nhận cọc'
})

const firstDepositMethodName = computed(() => {
  if (activeDepositsList.value.length > 0) {
    const pmId = activeDepositsList.value[0].paymentMethodId
    const pm = paymentMethods.value.find(x => x.id === pmId)
    return pm ? pm.name : 'Đặt cọc'
  }
  return ''
})

// ==================== NHÂN BẢN BOOKING MODAL ====================
const isCopyModalOpen = ref(false)
const copyModalArrivalDate = ref('')
const copyModalDepartureDate = ref('')

// ==================== UPGRADE ROOM MODAL ====================
const isUpgradeModalOpen = ref(false)

// ==================== GUEST INFO MODAL ====================
const isGuestInfoModalOpen = ref(false)

function openGuestInfoModal() {
  const tab = activeTab.value
  if (!tab || !tab.dbId) {
    uiStore.showToast('Vui lòng lưu thông tin đăng ký trước khi xem thông tin khách!', 'warning')
    return
  }
  isGuestInfoModalOpen.value = true
}

// ==================== CHILD BREAKFAST MODAL STATE ====================
const isChildBreakfastModalOpen = ref(false)
const selectedRoomForBreakfast = ref(null)

function openChildBreakfastModal(room) {
  const tab = activeTab.value
  if (!tab || !tab.dbId) {
    uiStore.showToast('Vui lòng lưu thông tin đăng ký trước khi xem chi tiết ăn sáng!', 'warning')
    return
  }
  selectedRoomForBreakfast.value = room
  isChildBreakfastModalOpen.value = true
}

async function onChildBreakfastSaved() {
  await loadBookings()
}

// ==================== DEPOSIT MODAL STATE & ACTIONS ====================
const isDepositModalOpen = ref(false)

function parseApiDate(dateStr) {
  if (!dateStr) return ''
  if (/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) return dateStr
  if (dateStr.includes('T')) {
    const d = new Date(dateStr)
    if (!isNaN(d)) {
      const year = d.getFullYear()
      const month = String(d.getMonth() + 1).padStart(2, '0')
      const day = String(d.getDate()).padStart(2, '0')
      return `${year}-${month}-${day}`
    }
  }
  return dateStr.substring(0, 10)
}

function openDepositModal() {
  if (!modalForm.value.dbId) {
    uiStore.showToast('Vui lòng lưu phiếu đăng ký trước khi đặt cọc!', 'warning')
    return
  }
  isDepositModalOpen.value = true
}

// ==================== TABLE UI STATES ====================
const isEditing = ref(false)
const searchQuery = ref('')
const selectedRows = ref([])
const collapsedSections = ref({
  registrationStatus: false,
  superiorTriple: false,
  deluxeDouble: false
})

const defaultColumns = {
  roomType: true,
  dates: true,
  occupancy: false, // Chiếm dụng
  availability: true, // Phòng trống
  quantity: true, // Số lượng
  price: true, // Giá phòng
  rateCode: true, // Mã giá phòng/Gói
  discount: true, // Tăng/Giảm giá
  upgrade: true, // Nâng hạng phòng
  adults: true, // Người lớn
  babies: true, // Em bé
  children: true, // Trẻ em
  childBreakfastRate: true, // Giá ăn sáng trẻ em
  breakfast: true, // Ăn sáng
}

const visibleColumns = ref({ ...defaultColumns })

watch(() => authStore.settings?.visible_columns?.create_registration, (newVal) => {
  if (newVal) {
    visibleColumns.value = { ...defaultColumns, ...newVal }
  }
}, { immediate: true, deep: true })

watch(visibleColumns, (newVal) => {
  try {
    authStore.updateUserSettings({
      visible_columns: {
        create_registration: newVal
      }
    })
  } catch (e) {
    console.error(e)
  }
}, { deep: true })
const showColumnSelector = ref(false)

const columns = ref([
  { key: 'type', label: 'Loại phòng', visible: true, width: 'w-[125px]' },
  { key: 'shape', label: 'Dạng phòng', visible: true, width: 'w-[75px]', center: true },
  { key: 'roomNumber', label: 'Số phòng', visible: true, width: 'auto', center: true },
  { key: 'checkIn', label: 'Ngày đến', visible: true, width: 'w-[100px]', center: true },
  { key: 'checkOut', label: 'Ngày đi', visible: true, width: 'w-[100px]', center: true },
  { key: 'nights', label: 'Đêm', visible: true, width: 'w-[60px]', center: true },
  { key: 'price', label: 'Giá', visible: true, width: 'w-[95px]', right: true },
  { key: 'rateCode', label: 'Mã giá phòng', visible: true, width: 'auto' },
  { key: 'adjustment', label: 'Giảm/tăng giá', visible: true, width: 'w-[80px]', right: true },
  { key: 'guestName', label: 'Tên khách', visible: true, width: 'auto' },
  { key: 'adults', label: 'N.Lớn', visible: true, width: 'w-[65px]', center: true },
  { key: 'babies', label: 'Em bé', visible: true, width: 'w-[65px]', center: true },
  { key: 'children', label: 'Trẻ em', visible: true, width: 'w-[65px]', center: true },
  { key: 'childBreakfast', label: 'Chi tiết ăn sáng trẻ', visible: true, width: 'w-[130px]', center: true },
  { key: 'breakfast', label: 'Ăn sáng', visible: true, width: 'w-[75px]', center: true },
  { key: 'extraBed', label: 'Thêm giường', visible: true, width: 'w-[90px]', center: true },
  { key: 'extraBedPrice', label: 'Giá thêm giường', visible: true, width: 'w-[115px]', right: true },
  { key: 'hourly', label: 'Ở theo giờ', visible: true, width: 'w-[85px]', center: true },
  { key: 'specialRequests', label: 'Yêu cầu đặc biệt', visible: true, width: 'w-[125px]', center: true },
  { key: 'arrivalTime', label: 'Giờ đến', visible: true, width: 'w-[90px]', center: true },
  { key: 'hoursOut', label: 'Giờ đi', visible: true, width: 'w-[90px]', center: true },
  { key: 'isPreassigned', label: 'Đặt trước', visible: true, width: 'w-[80px]', center: true },
  { key: 'initialRoomClass', label: 'LP Khởi tạo', visible: true, width: 'w-[105px]' },
  { key: 'transferredFrom', label: 'Phòng chuyển', visible: true, width: 'w-[100px]', center: true },
  { key: 'roomStatus', label: 'Trạng thái phòng', visible: true, width: 'w-[120px]', center: true },
  { key: 'allotmentCode', label: 'Mã ALM', visible: true, width: 'w-[100px]' },
  { key: 'roomCode', label: 'Mã phòng', visible: true, width: 'w-[100px]' },
])

const tableWidth = computed(() => {
  let total = 35 + 50 + 45 + 120 // base columns: toggle (35), checkbox (50), STT (45), Tổng cộng (120)
  columns.value.forEach(col => {
    if (col.visible) {
      if (col.width && col.width.startsWith('w-[')) {
        const match = col.width.match(/w-\[(\d+)px\]/)
        total += match ? Number(match[1]) : 100
      } else {
        // Fallback widths for auto-growing columns
        if (col.key === 'roomNumber') total += 110
        else if (col.key === 'rateCode') total += 180
        else if (col.key === 'guestName') total += 180
        else total += 100
      }
    }
  })
  return `${total}px`
})

const time24hOptions = [
  '00:00', '00:30', '01:00', '01:30', '02:00', '02:30', '03:00', '03:30',
  '04:00', '04:30', '05:00', '05:30', '06:00', '06:30', '07:00', '07:30',
  '08:00', '08:30', '09:00', '09:30', '10:00', '10:30', '11:00', '11:30',
  '12:00', '12:30', '13:00', '13:30', '14:00', '14:30', '15:00', '15:30',
  '16:00', '16:30', '17:00', '17:30', '18:00', '18:30', '19:00', '19:30',
  '20:00', '20:30', '21:00', '21:30', '22:00', '22:30', '23:00', '23:30', '23:59'
]

const showTableColumnSelector = ref(false)
const draggedColKey = ref(null)
const expandedRooms = ref([])

async function toggleRoomExpand(room) {
  const roomId = room.id
  const index = expandedRooms.value.indexOf(roomId)
  if (index === -1) {
    if (room.bookingRoomId) {
      try {
        const res = await fetchBookingRoomServices(room.bookingRoomId)
        room.services = res.data?.data || []
      } catch (err) {
        console.error('Lỗi khi tải dịch vụ bổ sung:', err)
      }
    }
    expandedRooms.value.push(roomId)
  } else {
    expandedRooms.value.splice(index, 1)
  }
}

function getRoomDisplayServices(room) {
  const list = []
  const hasDbRoomCharges = room.services && room.services.some(svc => svc.service_code === 'RM' || svc.service_code === 'ROOM_CHARGE')
  
  // 1. Dịch vụ phòng nghỉ mặc định (Room Charge) - chỉ hiển thị nếu DB chưa có bản ghi tiền phòng thực tế
  if (!hasDbRoomCharges) {
    const checkIn = room.checkIn
    const nights = Number(room.nights) || 1
    if (checkIn) {
      for (let i = 0; i < nights; i++) {
        const parts = checkIn.split('-')
        let curr = new Date()
        if (parts.length === 3) {
          curr = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]))
        } else {
          curr = new Date(checkIn)
        }
        curr.setDate(curr.getDate() + i)
        const yyyy = curr.getFullYear()
        const mm = String(curr.getMonth() + 1).padStart(2, '0')
        const dd = String(curr.getDate()).padStart(2, '0')
        const dStr = `${yyyy}-${mm}-${dd}`
        
        list.push({
          id: `room-charge-${room.id}-${i}`,
          service_date: dStr,
          service_name: 'Dịch vụ phòng nghỉ',
          service_code: 'ROOM_CHARGE',
          quantity: 1,
          rate: room.price,
          is_room: true
        })
      }
    } else {
      list.push({
        id: `room-charge-${room.id}`,
        service_date: new Date().toISOString().split('T')[0],
        service_name: 'Dịch vụ phòng nghỉ',
        service_code: 'ROOM_CHARGE',
        quantity: 1,
        rate: room.price,
        is_room: true
      })
    }
  }
  // 2. Các dịch vụ bổ sung
  if (room.services && room.services.length > 0) {
    room.services.forEach(svc => {
      list.push({
        id: svc.id,
        service_date: svc.service_date,
        service_name: svc.service_name || getServiceNameFromCode(svc.service_code),
        service_code: svc.service_code,
        quantity: svc.quantity || 1,
        rate: svc.rate || 0,
        is_room: svc.is_room !== 0
      })
    })
  }
  return list
}

function getServicesTotal(room) {
  if (!room.services || !Array.isArray(room.services)) return 0
  return room.services
    .filter(svc => svc.service_code !== 'EB' && svc.service_code !== 'RM' && svc.service_code !== 'ROOM_CHARGE')
    .reduce((sum, svc) => sum + (Number(svc.rate) * Number(svc.quantity || 1)), 0)
}

function getRoomExtraBedTotal(room) {
  if (!room) return 0
  if (room.extraBedTotalSum !== undefined && room.extraBedTotalSum !== null) {
    return Number(room.extraBedTotalSum) || 0
  }
  if (room.dailyExtraBeds && Array.isArray(room.dailyExtraBeds) && room.dailyExtraBeds.length > 0) {
    return room.dailyExtraBeds.reduce((sum, d) => sum + (Number(d.total) || 0), 0)
  }
  const nights = Number(room.nights) || 1
  const extraBedPrice = Number(room.extraBedPrice) || 0
  const extraBedQty = Number(room.extraBedQty) || 0
  return extraBedPrice * extraBedQty * nights
}

function calculateRoomTotal(room) {
  const nights = Number(room.nights) || 1
  const price = Number(room.price) || 0
  const extraBedTotal = getRoomExtraBedTotal(room)
  const servicesTotal = getServicesTotal(room)
  return (price * nights) + extraBedTotal + servicesTotal
}


function handleDragStart(key) {
  draggedColKey.value = key
}

function handleDrop(targetKey) {
  if (!draggedColKey.value || draggedColKey.value === targetKey) return
  const fromIdx = columns.value.findIndex(c => c.key === draggedColKey.value)
  const toIdx = columns.value.findIndex(c => c.key === targetKey)
  if (fromIdx !== -1 && toIdx !== -1) {
    const [moved] = columns.value.splice(fromIdx, 1)
    columns.value.splice(toIdx, 0, moved)
  }
  draggedColKey.value = null
}

// ==================== COMPUTEDS ====================
const activeTab = computed(() => tabs.value.find(t => t.id === activeTabId.value))

const isCheckInDisabled = computed(() => {
  const tab = activeTab.value
  if (!tab || !tab.rooms || tab.rooms.length === 0) return true

  const selected = tab.rooms.filter(r => selectedRows.value.includes(r.id))
  if (selected.length > 0) {
    return selected.every(r => r.bookingRoomStatus === 1)
  }
  
  const targetList = tab.rooms.filter(r => r.roomNumber)
  if (targetList.length === 0) return true
  
  return targetList.every(r => r.bookingRoomStatus === 1)
})

const filteredActiveRooms = computed(() => {
  if (!activeTab.value || !activeTab.value.rooms) return []
  let list = activeTab.value.rooms

  // Check if ALL rooms in this registration are cancelled
  const allRoomsCancelled = activeTab.value.status === 'CANCELLED' || 
    (list.length > 0 && list.every(r => Number(r.bookingRoomStatus) === 3 || Number(r.bookingRoomStatus) === 100))

  // If not all rooms are cancelled, hide individual cancelled/transferred rooms
  if (!allRoomsCancelled) {
    list = list.filter(r => Number(r.bookingRoomStatus) !== 3 && Number(r.bookingRoomStatus) !== 100)
  }

  if (selectedServiceFilter.value && selectedServiceFilter.value !== 'all') {
    list = list.filter(r => r.services && r.services.some(s => s.service_code === selectedServiceFilter.value))
  }

  if (searchQuery.value) {
    const q = searchQuery.value.toLowerCase()
    list = list.filter(r =>
      r.type.toLowerCase().includes(q) ||
      r.guestName.toLowerCase().includes(q) ||
      (r.roomNumber && r.roomNumber.toLowerCase().includes(q))
    )
  }

  return list
})

const selectRangeVal = computed({
  get() {
    if (!activeTab.value) return 0
    return activeTab.value.rooms.filter(r => selectedRows.value.includes(r.id)).length
  },
  set(val) {
    if (!activeTab.value) return
    const rooms = activeTab.value.rooms
    const countToSelect = Math.min(Number(val), rooms.length)
    const newSelected = []
    for (let i = 0; i < countToSelect; i++) newSelected.push(rooms[i].id)
    selectedRows.value = newSelected
  }
})

const roomsTotalSummary = computed(() => {
  if (!activeTab.value) return { count: 0, priceSum: 0, adults: 0, babies: 0, children: 0, extraBed: 0, total: 0 }
  let priceSum = 0, adults = 0, babies = 0, children = 0, extraBed = 0, total = 0
  const roomList = filteredActiveRooms.value
  roomList.forEach(r => {
    const nights = Number(r.nights) || 1
    const p = Number(r.price) || 0
    priceSum += p * nights
    adults   += Number(r.adults) || 0
    babies   += Number(r.babies) || 0
    children += Number(r.children) || 0
    const ebTotal = getRoomExtraBedTotal(r)
    extraBed += ebTotal
    total    += calculateRoomTotal(r)
  })
  return { count: roomList.length, priceSum, adults, babies, children, extraBed, total }
})

const activeTabStatusName = computed(() => {
  if (!activeTab.value) return '—'
  if (activeTab.value.registrationStatusId) {
    const s = registrationStatuses.value.find(rs => Number(rs.id) === Number(activeTab.value.registrationStatusId))
    if (s) return s.name
  }
  return activeTab.value.statusLabel || '—'
})

const allocationsSummary = computed(() => {
  let availSum = 0
  let qtySum = 0
  let adultSum = 0
  let babySum = 0
  let childSum = 0
  
  if (modalForm.value && modalForm.value.roomAllocations) {
    modalForm.value.roomAllocations.forEach(row => {
      availSum += Number(row.availableRooms) || 0
      qtySum += Number(row.quantity) || 0
      adultSum += Number(row.adults) || 0
      babySum += Number(row.babies) || 0
      childSum += Number(row.children) || 0
    })
  }

  return {
    count: modalForm.value?.roomAllocations?.length || 0,
    availableRooms: availSum,
    quantity: qtySum,
    adults: adultSum,
    babies: babySum,
    children: childSum
  }
})

function getRoomStatusGroupName(status) {
  const s = Number(status)
  if (s === 1) return 'Đang ở'
  if (s === 2) return 'Đã trả phòng'
  if (s === 3) return 'Hủy phòng'
  if (s === 4) return 'No Show'
  if (s === 100) return 'Phòng chuyển'
  return 'Đăng ký'
}

function getStatusOrderAndName(status) {
  const s = Number(status)
  if (s === 0)   return { order: 0, name: 'Đăng ký' }
  if (s === 1)   return { order: 1, name: 'Đang ở' }
  if (s === 2)   return { order: 2, name: 'Phòng đi' }
  if (s === 3 || s === 100) return { order: 3, name: 'Hủy/Chuyển' }
  if (s === 4)   return { order: 4, name: 'Khách không đến' }
  return { order: 5, name: 'Khác' }
}

// Grouped by room type (always) - Returns sorted array
const groupedRooms = computed(() => {
  if (!activeTab.value || !activeTab.value.rooms) return []
  const groupsMap = {}
  filteredActiveRooms.value.forEach(room => {
    const key = room.type || 'Khác'
    if (!groupsMap[key]) groupsMap[key] = []
    groupsMap[key].push(room)
  })

  const groupsList = Object.keys(groupsMap).map(typeName => {
    const rooms = groupsMap[typeName]
    // Sort rooms by bookingRoomId ascending (natural order)
    rooms.sort((a, b) => {
      const idA = String(a.bookingRoomId || '')
      const idB = String(b.bookingRoomId || '')
      return idA.localeCompare(idB, undefined, { numeric: true, sensitivity: 'base' })
    })

    const rc = roomClasses.value.find(c => c.name === typeName || c.code === typeName)
    const order = rc ? (rc.orders !== undefined ? Number(rc.orders) : 0) : 9999

    return {
      typeName,
      order,
      rooms
    }
  })

  // Sort groups by room class order ascending
  groupsList.sort((a, b) => a.order - b.order || a.typeName.localeCompare(b.typeName))
  return groupsList
})

// Whether any room status headers are shown (Always show status headers)
const hasStatusGroups = computed(() => true)

// Build nested: Returns sorted array of status groups, each containing typeGroups sorted by room class order
const groupedRoomsNested = computed(() => {
  if (!activeTab.value || !activeTab.value.rooms) return []
  
  const statusGroupsMap = {}
  filteredActiveRooms.value.forEach(room => {
    const { order: statusOrder, name: statusName } = getStatusOrderAndName(room.bookingRoomStatus)
    if (!statusGroupsMap[statusOrder]) {
      statusGroupsMap[statusOrder] = {
        statusOrder,
        statusName,
        typeGroupsMap: {}
      }
    }
    const typeKey = room.type || 'Khác'
    if (!statusGroupsMap[statusOrder].typeGroupsMap[typeKey]) {
      statusGroupsMap[statusOrder].typeGroupsMap[typeKey] = []
    }
    statusGroupsMap[statusOrder].typeGroupsMap[typeKey].push(room)
  })

  const sortedStatusGroups = Object.values(statusGroupsMap).map(statusGroup => {
    const typeGroupsList = Object.keys(statusGroup.typeGroupsMap).map(typeName => {
      const rooms = statusGroup.typeGroupsMap[typeName]
      // Sort rooms by bookingRoomId ascending (natural order)
      rooms.sort((a, b) => {
        const idA = String(a.bookingRoomId || '')
        const idB = String(b.bookingRoomId || '')
        return idA.localeCompare(idB, undefined, { numeric: true, sensitivity: 'base' })
      })

      const rc = roomClasses.value.find(c => c.name === typeName || c.code === typeName)
      const order = rc ? (rc.orders !== undefined ? Number(rc.orders) : 0) : 9999

      return {
        typeName,
        order,
        rooms
      }
    })

    // Sort type groups by room class order ascending
    typeGroupsList.sort((a, b) => a.order - b.order || a.typeName.localeCompare(b.typeName))

    return {
      statusOrder: statusGroup.statusOrder,
      statusName: statusGroup.statusName,
      typeGroups: typeGroupsList
    }
  })

  // Sort status groups by statusOrder ascending (0 -> 1 -> 2 -> 3 -> 4)
  sortedStatusGroups.sort((a, b) => a.statusOrder - b.statusOrder)
  return sortedStatusGroups
})

function toggleGroupCollapse(typeName) {
  if (collapsedSections.value[typeName] === undefined) {
    collapsedSections.value[typeName] = true
  } else {
    collapsedSections.value[typeName] = !collapsedSections.value[typeName]
  }
}

function syncRoomsToAllocations(tab) {
  if (!tab.roomAllocations || !tab.rooms) return tab.roomAllocations
  return tab.roomAllocations.map(alloc => {
    const matchingRooms = tab.rooms.filter(r => r.roomClassId === alloc.roomClassId)
    const roomsDetail = matchingRooms.map(r => ({
      roomNumber: r.roomNumber || '',
      rateCode: (r.rateCode && r.rateCode !== 'Vui lòng chọn giá phòng') ? r.rateCode : (alloc.rateCode || ''),
      guestName: r.guestName || '',
      adults: Number(r.adults) || 2,
      babies: Number(r.babies) || 0,
      children: Number(r.children) || 0,
      breakfast: r.breakfast !== undefined ? !!r.breakfast : true,
      extraBedPrice: Number(r.extraBedQty) > 0 ? (Number(r.extraBedPrice) || 0) : 0,
      extraBedQty: Number(r.extraBedQty) || 0,
      hourly: !!r.hourly,
      arrivalTime: r.arrivalTime || '14:00',
      hoursOut: r.hoursOut || '12:00',
      isPreassigned: !!r.isPreassigned,
      initialRoomClass: r.initialRoomClass || '',
      transferredFrom: r.transferredFrom || '',
      roomStatus: r.roomStatus || 'Sạch',
      allotmentCode: r.allotmentCode || '',
      roomCode: r.roomCode || '',
      bookingRoomId: r.bookingRoomId || null,
      total: Number(r.total) || 0,
      price: r.price || alloc.price || 0,
      basePrice: r.basePrice || alloc.basePrice || alloc.price || 0,
      discountType: r.discountType || alloc.discountType || 'down',
      discountValue: r.discountValue !== undefined ? r.discountValue : (alloc.discountValue || 0),
      discountUnit: r.discountUnit || alloc.discountUnit || 'percent',
      arrivalDate: r.checkIn || tab.checkIn,
      departureDate: r.checkOut || tab.checkOut,
      nights: Number(r.nights) || 1,
    }))
    return {
      ...alloc,
      quantity: Math.max(Number(alloc.quantity) || 0, matchingRooms.length),
      rooms: roomsDetail,
    }
  })
}

// ==================== LIFECYCLE ====================
onMounted(async () => {
  document.addEventListener('click', handleGlobalClick)
  window.addEventListener('booking-updated', loadBookings)
  window.addEventListener('deposit-updated', loadBookings)
  try {
    isLoading.value = true
    await Promise.all([loadDropdowns(), loadBookings()])
    
    if (route.query.bookingCode) {
      await openBookingModalByCode(route.query.bookingCode)
    } else if (route.query.action === 'new' || route.query.newBooking === 'true' || route.query.roomNumber) {
      await handleAddTabClick()
    }
  } catch (err) {
    console.error('Lỗi khi khởi tạo dữ liệu trang:', err)
  } finally {
    isLoading.value = false
  }
})

function handleTabsWheel(e) {
  if (e.deltaY) {
    e.currentTarget.scrollLeft += e.deltaY
  }
}

onBeforeUnmount(() => {
  document.removeEventListener('click', handleGlobalClick)
  window.removeEventListener('booking-updated', loadBookings)
  window.removeEventListener('deposit-updated', loadBookings)
})

watch(() => route.query, async (newQuery) => {
  if (newQuery.bookingCode) {
    await openBookingModalByCode(newQuery.bookingCode)
  } else if (newQuery.action === 'new' || newQuery.newBooking === 'true' || newQuery.roomNumber) {
    await handleAddTabClick()
  }
}, { deep: true })

async function loadDropdowns() {
  try {
    diagnosticErrors.value = []
    const res = await fetchBookingInitDropdowns()
    const data = res.data?.data || {}

    markets.value              = data.markets || []
    customerSources.value      = data.customer_sources || []
    bookers.value              = data.bookers || []
    companies.value            = (data.companies || []).filter(c => c.is_active || c.is_active === undefined)
    paymentMethods.value       = data.payment_methods || []
    registrationStatuses.value = data.registration_statuses || []
    users.value                = (data.users || []).filter(u => u.is_active_user !== false && u.is_active_user !== 0)
    roomClasses.value          = (data.room_classes || []).filter(c => c.is_active !== false)
    roomForms.value            = data.room_forms || []
    roomRateCodes.value        = data.room_rate_codes || []
    currenciesList.value       = data.currencies || []
    hotelServicesList.value    = data.hotel_services || []
    hotelSettings.value        = data.hotel_settings || {}

    if (data.system_date && data.system_date.system_date) {
      systemDate.value = data.system_date.system_date
    } else {
      systemDate.value = data.system_time ? formatLocalYYYYMMDD(data.system_time) : formatLocalYYYYMMDD(new Date())
    }

    // Empty list checks
    if (roomClasses.value.length === 0) {
      diagnosticErrors.value.push("Room Classes API returned 0 active room classes.")
    }
    if (users.value.length === 0) {
      diagnosticErrors.value.push("Users API returned 0 users.")
    }
    if (registrationStatuses.value.length === 0) {
      diagnosticErrors.value.push("Registration Statuses API returned 0 statuses.")
    }
  } catch (err) {
    console.error('Error loading dropdowns:', err)
    diagnosticErrors.value.push(`Uncaught loadDropdowns error: ${err.message}`)
  }
}

async function loadBookings() {
  isLoadingBookings.value = true
  try {
    const prevActiveId = activeTabId.value
    const res = await fetchBookings({ status: '0,1' })
    const allList = res.data?.data || res.data || []

    // Kiểm tra lần đầu vào trang: closedIds chưa có trong localStorage
    const isFirstLoad = localStorage.getItem(CLOSED_TABS_KEY) === null

    if (isFirstLoad && allList.length > 0) {
      // Lần đầu: chỉ mở 1 booking mới nhất (id lớn nhất), KHÔNG đóng các booking khác
      const latestBooking = allList.reduce((max, b) => b.id > max.id ? b : max, allList[0])
      // Khởi tạo localStorage với mảng rỗng (không đánh dấu ai là closed)
      localStorage.setItem(CLOSED_TABS_KEY, JSON.stringify([]))
      tabs.value = [bookingToTab(latestBooking)]
      activeTabId.value = tabs.value[0].id
    } else {
      // Các lần sau: lọc theo closedIds đã lưu
      const closedIds = getClosedTabIds()
      const list = allList.filter(b => !closedIds.includes(String(b.id)))
      tabs.value = list.map(b => bookingToTab(b))

      if (tabs.value.some(t => t.id === prevActiveId)) {
        activeTabId.value = prevActiveId
      } else if (tabs.value.length > 0) {
        activeTabId.value = tabs.value[0].id
      } else {
        // Không còn tab nào → empty state, KHÔNG tạo blank tab
        tabs.value = []
        activeTabId.value = null
      }
    }
  } catch (err) {
    console.error('Lỗi khi tải bookings:', err)
  } finally {
    isLoadingBookings.value = false
  }
}

function bookingToTab(b) {
  const rooms = []
  let idCounter = 1

  // Nếu API trả về booking_rooms (dữ liệu mới)
  if (b.booking_rooms && b.booking_rooms.length > 0) {
    b.booking_rooms.forEach(br => {
      const rc = br.room_class || {}
      const physicalRoom = br.room || {}
      
      let nightsCount = 1
      const ci = new Date(br.arrival_date || b.arrival_date)
      const co = new Date(br.departure_date || b.departure_date)
      if (!isNaN(ci) && !isNaN(co)) {
        const diff = Math.ceil((co - ci) / 86400000)
        nightsCount = diff > 0 ? diff : 1
      }
      const priceNum = Number(br.rate) || 0
      const servicesList = br.services || []
      const servicesTotal = servicesList.filter(svc => svc.service_code !== 'EB' && svc.service_code !== 'RM' && svc.service_code !== 'ROOM_CHARGE').reduce((sum, svc) => sum + (Number(svc.rate) * Number(svc.quantity || 1)), 0)
      const extraBedTotal = (Number(br.extra_bed_rate) || 0) * (Number(br.extra_bed_qty) || 0) * nightsCount
      const totalNum = (priceNum * nightsCount) + extraBedTotal + servicesTotal

      const primaryGuestPivot = br.guests ? br.guests.find(g => g.is_primary) || br.guests[0] : null
      const guestName = primaryGuestPivot && primaryGuestPivot.guest ? primaryGuestPivot.guest.full_name : ''
      const childrenCount = br.children ? br.children.filter(c => c.age_group === 'child').length : 0
      const babiesCount = br.children ? br.children.filter(c => c.age_group === 'baby').length : 0

      rooms.push({
        id: idCounter++,
        bookingRoomId: br.id, // lưu lại id để edit nếu cần
        isDoNotMove: br.is_do_not_move !== undefined ? !!br.is_do_not_move : false,
        type: rc.name || 'Unknown Class',
        shape: (() => {
          const matched = roomClasses.value.find(c => c.id === br.room_class_id)
          return matched ? (matched.room_form_name || matched.code) : (rc.code || '')
        })(),
        roomNumber: physicalRoom.room_number || '',
        checkIn: parseApiDate(br.arrival_date || b.arrival_date),
        checkOut: parseApiDate(br.departure_date || b.departure_date),
        nights: nightsCount,
        price: priceNum,
        rateCode: br.rate_code || 'Vui lòng chọn giá phòng',
        guestName: guestName,
        adults: br.adults || 2,
        babies: babiesCount,
        children: childrenCount,
        breakfast: br.breakfast !== undefined ? !!br.breakfast : true,
        extraBedPrice: br.extra_bed_rate || 0,
        extraBedQty: br.extra_bed_qty || 0,
        hourly: false,
        arrivalTime: br.arrival_time || '14:00',
        hoursOut: br.departure_time || '12:00',
        isPreassigned: !!physicalRoom.room_number,
        initialRoomClass: rc.code || '',
        transferredFrom: '',
        roomStatus: (br.status === 1 || physicalRoom.status === 'dirty') ? 'Bẩn' : (physicalRoom.status === 'cleaning' ? 'Đang dọn' : (physicalRoom.status === 'inspecting' ? 'Kiểm tra' : 'Sạch')),
        allotmentCode: '',
        roomCode: br.id || '',
        total: totalNum,
        roomClassId: br.room_class_id,
        services: br.services || [],
        specialRequests: br.special_requests || [],
        bookingRoomStatus: br.status !== undefined ? Number(br.status) : 0,
        upgradeClassId: null,
        discount: br.discount,
        discountType: br.discount_type || 'down',
        discountValue: br.discount_value !== undefined ? Number(br.discount_value) : 0,
        discountUnit: br.discount_unit || 'percent',
        basePrice: br.base_price !== undefined ? Number(br.base_price) : priceNum,
      })
    })
  } else {
    // Dữ liệu cũ (nếu còn)
    const roomAllocations = b.room_allocations || []
    roomAllocations.forEach(alloc => {
      const qty = Number(alloc.quantity) || 0
      if (qty <= 0) return

      let nightsCount = 1
      const ci = new Date(alloc.arrivalDate || b.arrival_date)
      const co = new Date(alloc.departureDate || b.departure_date)
      if (!isNaN(ci) && !isNaN(co)) {
        const diff = Math.ceil((co - ci) / 86400000)
        nightsCount = diff > 0 ? diff : 1
      }

      const priceNum = Number(alloc.price) || 0
      const totalNum = priceNum * nightsCount

      const rc = roomClasses.value.find(c => c.id === alloc.roomClassId || c.code === alloc.roomClassCode)
      const typeName = alloc.roomClassName || (rc ? rc.name : (alloc.roomClassCode || 'Unknown Class'))
      const shapeName = rc ? (rc.room_form_name || rc.code) : (alloc.roomClassCode || '')

      const roomsInAlloc = alloc.rooms || alloc.roomsDetail || []
      for (let i = 0; i < qty; i++) {
        const roomDetail = roomsInAlloc[i] || {}
        rooms.push({
          id: idCounter++,
          type: typeName,
          shape: shapeName,
          roomNumber: roomDetail.roomNumber || '',
          checkIn: parseApiDate(alloc.arrivalDate || b.arrival_date),
          checkOut: parseApiDate(alloc.departureDate || b.departure_date),
          nights: nightsCount,
          price: priceNum,
          rateCode: roomDetail.rateCode || alloc.ratePlanCode || 'Vui lòng chọn giá phòng',
          guestName: roomDetail.guestName || '',
          adults: Number(roomDetail.adults || alloc.adults) || (rc?.code?.toLowerCase().includes('tr') || rc?.code?.toLowerCase().includes('t') ? 3 : 2),
          babies: Number(roomDetail.babies || alloc.babies) || 0,
          children: Number(roomDetail.children || alloc.children) || 0,
          breakfast: roomDetail.breakfast !== undefined ? !!roomDetail.breakfast : !!alloc.breakfastIncluded,
          extraBedPrice: Number(roomDetail.extraBedPrice) || 0,
          hourly: !!roomDetail.hourly,
          arrivalTime: roomDetail.arrivalTime || '14:00',
          hoursOut: roomDetail.hoursOut || '12:00',
          isPreassigned: roomDetail.isPreassigned !== undefined ? !!roomDetail.isPreassigned : false,
          initialRoomClass: roomDetail.initialRoomClass || shapeName,
          transferredFrom: roomDetail.transferredFrom || '',
          roomStatus: roomDetail.roomStatus || 'Sạch',
          allotmentCode: roomDetail.allotmentCode || '',
          roomCode: roomDetail.roomCode || '',
          total: Number(roomDetail.total) || totalNum,
          roomClassId: alloc.roomClassId,
          bookingRoomStatus: 0,
          upgradeClassId: alloc.upgradeRoomClassId || null,
        })
      }
    })
  }

  const roomAllocations = []
  if (b.booking_rooms && b.booking_rooms.length > 0) {
    const grouped = {}
    b.booking_rooms.forEach(br => {
      const classId = br.room_class_id
      if (!grouped[classId]) {
        const rc = roomClasses.value.find(c => c.id === classId)
        const isBfChecked = hotelSettings.value?.DefaultBreakfast !== undefined 
          ? (Number(hotelSettings.value.DefaultBreakfast) === 1) 
          : true
        grouped[classId] = {
          roomClassId: classId,
          roomClassCode: br.room_class?.code || '',
          roomClassName: br.room_class?.name || '',
          arrivalDate: parseApiDate(br.arrival_date),
          departureDate: parseApiDate(br.departure_date),
          quantity: 0,
          price: Number(br.rate) || 0,
          rateCode: br.rate_code || '',
          discount: br.discount || 'Tăng/Giảm giá',
          discountType: br.discount_type || 'down',
          discountValue: br.discount_value !== undefined ? Number(br.discount_value) : 0,
          discountUnit: br.discount_unit || 'percent',
          basePrice: br.base_price !== undefined ? Number(br.base_price) : (Number(br.rate) || 0),
          upgradeClassId: br.upgrade_class_id || null,
          extraBedPrice: br.extra_bed_rate !== undefined ? Number(br.extra_bed_rate) : (rc?.extra_bed_price !== undefined ? Number(rc.extra_bed_price) : 0),
          adults: br.adults || rc?.max_adults || 2,
          babies: 0,
          children: 0,
          childBreakfastRate: hotelSettings.value?.breakfast_child_rate || 90000,
          breakfastIncluded: br.breakfast !== undefined ? !!br.breakfast : isBfChecked,
        }
      }
      grouped[classId].quantity++
      const childCount = br.children ? br.children.filter(c => c.age_group === 'child').length : 0
      const babyCount = br.children ? br.children.filter(c => c.age_group === 'baby').length : 0
      grouped[classId].children = Math.max(grouped[classId].children || 0, childCount)
      grouped[classId].babies = Math.max(grouped[classId].babies || 0, babyCount)
    })
    Object.values(grouped).forEach(g => {
      roomAllocations.push(g)
    })
  } else {
    const dbAlloc = b.room_allocations || []
    dbAlloc.forEach(alloc => {
      const isBfChecked = hotelSettings.value?.DefaultBreakfast !== undefined 
        ? (Number(hotelSettings.value.DefaultBreakfast) === 1) 
        : true
      roomAllocations.push({
        roomClassId: alloc.roomClassId,
        roomClassCode: alloc.roomClassCode,
        roomClassName: alloc.roomClassName,
        arrivalDate: alloc.arrivalDate,
        departureDate: alloc.departureDate,
        quantity: Number(alloc.quantity) || 0,
        price: Number(alloc.price) || 0,
        rateCode: alloc.rateCode || alloc.ratePlanCode || '',
        discount: alloc.discount || 'Tăng/Giảm giá',
        discountType: alloc.discountType || 'down',
        discountValue: alloc.discountValue !== undefined ? Number(alloc.discountValue) : 0,
        discountUnit: alloc.discountUnit || 'percent',
        basePrice: alloc.basePrice !== undefined ? Number(alloc.basePrice) : (Number(alloc.price) || 0),
        upgradeClassId: alloc.upgradeClassId || alloc.upgradeRoomClassId || null,
        extraBedPrice: alloc.extraBedPrice !== undefined ? Number(alloc.extraBedPrice) : (rc?.extra_bed_price !== undefined ? Number(rc.extra_bed_price) : 0),
        adults: Number(alloc.adults) || 2,
        babies: Number(alloc.babies) || 0,
        children: Number(alloc.children) || 0,
        childBreakfastRate: Number(alloc.childBreakfastRate) || hotelSettings.value?.breakfast_child_rate || 90000,
        breakfastIncluded: alloc.breakfastIncluded !== undefined ? !!alloc.breakfastIncluded : isBfChecked,
      })
    })
  }

  const activePayments = b.payments ? b.payments.filter(p => p.edit_flag === 0 && p.pack2 === 'DPR') : []
  const totalDeposit = activePayments.reduce((sum, p) => sum + Number(p.amount), 0)

  return {
    id: b.booking_code,
    dbId: b.id,
    title: `Booking ${b.booking_code}`,
    bookingName: b.booking_name,
    statusLabel: b.registration_status?.name || '—',
    registrationStatusId: b.registration_status_id,
    checkIn: parseApiDate(b.arrival_date),
    checkOut: parseApiDate(b.departure_date),
    nights: b.num_of_days,
    deposit: totalDeposit,
    company: b.company?.name || '—',
    companyId: b.company_id,
    confirmDate: parseApiDate(b.confirm_date),
    expiredDate: parseApiDate(b.expired_date),
    color: b.color || '#000000',
    marketId: b.market_id,
    market: b.market?.name || '—',
    customerSourceId: b.customer_source_id,
    customerSource: b.customer_source?.name || '—',
    bookerId: b.booker_id,
    contactName: b.contact_name || '',
    contactEmail: b.contact_email || '',
    contactPhone: b.contact_phone || '',
    paymentMethodId: b.payment_method_id,
    paymentValue: totalDeposit,
    externalBookingCode: b.external_booking_code || '',
    salesPerson: b.sales_person || '',
    isGit: b.is_git || false,
    hasVat: b.has_vat || false,
    note: b.note || '',
    specialRequests: b.special_requests || '',
    shuttleInfo: b.shuttle_info || [],
    roomAllocations: roomAllocations,
    deposits: b.payments ? b.payments.map(p => ({
      id: p.id,
      date: p.date ? parseApiDate(p.date).split('-').reverse().join('/') : '',
      time: p.open_time ? p.open_time.substring(0, 5) : '',
      paymentMethodId: p.payment_method_id,
      note: p.description || '',
      amount: Number(p.amount) || 0,
      currency: activeCurrency.value.code || 'VND',
      recipient: p.created_by || 'Admin',
      images: p.image_path ? [p.image_path] : [],
      status: p.status,
      edit_flag: p.edit_flag,
      reversal_ref: p.reversal_ref,
      debit_account: p.debit_account,
      pack2: p.pack2
    })) : [],
    rooms: rooms,
    createdBy: b.created_by || '',
    createdAt: b.created_at || '',
  }
}

function makeBlankTab() {
  const today = systemDate.value || new Date().toISOString().split('T')[0]
  const tomorrowDate = new Date(today); tomorrowDate.setDate(tomorrowDate.getDate() + 1)
  const tomorrow = tomorrowDate.toISOString().split('T')[0]
  return {
    id: 'NEW_BOOKING', dbId: null, title: 'Đăng ký mới', bookingName: 'Chưa có thông tin',
    statusLabel: 'Mới', registrationStatusId: null,
    checkIn: today, checkOut: tomorrow, nights: 1, deposit: 0,
    company: '—', companyId: null, confirmDate: today,
    marketId: null, market: '—', customerSourceId: null, customerSource: '—',
    bookerId: null, contactName: '', contactEmail: '', contactPhone: '',
    paymentMethodId: null, paymentValue: 0, externalBookingCode: '',
    salesPerson: '', isGit: false, hasVat: false, note: '', specialRequests: '',
    rooms: [],
    createdBy: '',
    createdAt: '',
  }
}

// ==================== TAB HANDLERS ====================
function handleTabClick(tabId) { activeTabId.value = tabId }

function handleCloseTab(tabId, event) {
  event.stopPropagation()
  const index = tabs.value.findIndex(t => t.id === tabId)
  const closedTab = tabs.value.find(t => t.id === tabId)
  // Lưu dbId vào localStorage để F5 không hiện lại
  if (closedTab?.dbId) addClosedTabId(closedTab.dbId)
  tabs.value = tabs.value.filter(t => t.id !== tabId)
  if (activeTabId.value === tabId) {
    if (tabs.value.length > 0) {
      activeTabId.value = tabs.value[Math.max(0, index - 1)].id
    } else {
      // Đóng tab cuối → empty state, KHÔNG tạo blank tab
      activeTabId.value = null
    }
  }
}

function initRoomAllocations(existing = [], checkInDate, checkOutDate) {
  const isBreakfastChecked = hotelSettings.value?.DefaultBreakfast !== undefined 
    ? (Number(hotelSettings.value.DefaultBreakfast) === 1) 
    : true

  return roomClasses.value.map(rc => {
    const found = existing.find(e => e.roomClassId === rc.id || e.roomClassCode === rc.code)
    
    // Default available room count
    let defaultAvail = 3
    if (rc.code === 'SUPD') defaultAvail = 0
    else if (rc.code === 'SUPTR' || rc.code === 'DLXDB') defaultAvail = 9
    else if (rc.code === 'DLXTB') defaultAvail = 8
    else if (rc.code === 'DLXT') defaultAvail = 4
    else if (rc.code === 'JST') defaultAvail = 1

    if (found) {
      return {
        roomClassId: rc.id,
        roomClassCode: rc.code,
        roomClassName: rc.name,
        arrivalDate: found.arrivalDate || checkInDate,
        departureDate: found.departureDate || checkOutDate,
        availableRooms: found.availableRooms !== undefined ? found.availableRooms : defaultAvail,
        quantity: found.quantity !== undefined ? Number(found.quantity) : 0,
        price: found.price !== undefined ? Number(found.price) : (rc.room_price !== undefined ? Number(rc.room_price) : 0),
        rateCode: found.rateCode || found.ratePlanCode || '',
        discount: found.discount || 'Tăng/Giảm giá',
        discountType: found.discountType || 'down',
        discountValue: found.discountValue !== undefined ? Number(found.discountValue) : 0,
        discountUnit: found.discountUnit || 'percent',
        basePrice: found.basePrice !== undefined ? Number(found.basePrice) : (found.price !== undefined ? Number(found.price) : (rc.room_price !== undefined ? Number(rc.room_price) : 0)),
        upgradeClassId: found.upgradeClassId || found.upgradeRoomClassId || null,
        adults: found.adults !== undefined ? Number(found.adults) : (rc.max_adults || 2),
        babies: found.babies !== undefined ? Number(found.babies) : 0,
        children: found.children !== undefined ? Number(found.children) : 0,
        childBreakfastRate: found.childBreakfastRate !== undefined ? Number(found.childBreakfastRate) : (hotelSettings.value?.breakfast_child_rate || 90000),
        breakfastIncluded: found.breakfastIncluded !== undefined ? !!found.breakfastIncluded : isBreakfastChecked,
        extraBedPrice: found.extraBedPrice !== undefined ? Number(found.extraBedPrice) : (rc.extra_bed_price !== undefined ? Number(rc.extra_bed_price) : 0),
      }
    }

    return {
      roomClassId: rc.id,
      roomClassCode: rc.code,
      roomClassName: rc.name,
      arrivalDate: checkInDate,
      departureDate: checkOutDate,
      availableRooms: defaultAvail,
      quantity: 0,
      price: rc.room_price !== undefined ? Number(rc.room_price) : 0,
      rateCode: '',
      discount: 'Tăng/Giảm giá',
      discountType: 'down',
      discountValue: 0,
      discountUnit: 'percent',
      basePrice: rc.room_price !== undefined ? Number(rc.room_price) : 0,
      upgradeClassId: null,
      adults: rc.max_adults || 2,
      babies: 0,
      children: 0,
      childBreakfastRate: hotelSettings.value?.breakfast_child_rate || 90000,
      breakfastIncluded: isBreakfastChecked,
      extraBedPrice: rc.extra_bed_price !== undefined ? Number(rc.extra_bed_price) : 0,
    }
  })
}

function syncAllocationToRooms(row) {
  if (!modalForm.value.rooms) return
  modalForm.value.rooms.forEach(r => {
    if (r.roomClassId === row.roomClassId || r.type === row.roomClassName || r.shape === row.roomClassCode) {
      r.price = Number(row.price) || 0
      r.basePrice = Number(row.basePrice || row.price) || 0
      r.rateCode = row.rateCode || 'Vui lòng chọn giá phòng'
      r.adults = Number(row.adults) || 2
      r.babies = Number(row.babies) || 0
      r.children = Number(row.children) || 0
      r.breakfast = !!row.breakfastIncluded
      r.upgradeClassId = row.upgradeClassId || null
      r.total = r.price * (Number(r.nights) || 1)
    }
  })
}

function syncRoomToAllocation(room) {
  const tab = activeTab.value
  if (tab && tab.roomAllocations) {
    const alloc = tab.roomAllocations.find(a => a.roomClassId === room.roomClassId)
    if (alloc) {
      alloc.adults = Number(room.adults) || 2
      alloc.babies = Number(room.babies) || 0
      alloc.children = Number(room.children) || 0
      alloc.breakfastIncluded = !!room.breakfast
    }
  }
}

function handleRateCodeChange(row) {
  if (!row) return
  if (row.rateCode) {
    const rcObj = roomRateCodes.value.find(rc => rc.Ma === row.rateCode)
    if (rcObj) {
      // 1. Check expiration dates
      if (rcObj.BeginDate || rcObj.EndDate) {
        const arr = new Date(row.arrivalDate)
        const dep = new Date(row.departureDate)
        const begin = rcObj.BeginDate ? new Date(rcObj.BeginDate) : null
        const end = rcObj.EndDate ? new Date(rcObj.EndDate) : null
        
        arr.setHours(0,0,0,0)
        dep.setHours(0,0,0,0)
        if (begin) begin.setHours(0,0,0,0)
        if (end) end.setHours(0,0,0,0)

        if ((begin && arr < begin) || (end && dep > end)) {
          uiStore.showToast(`Mã giá phòng ${row.rateCode} đã hết hạn hoặc không áp dụng trong thời gian lưu trú này!`, 'error')
          row.rateCode = ''
          syncAllocationToRooms(row)
          return
        }
      }

      row.breakfastIncluded = !!rcObj.IncludeBF
      
      const price = getRateCodePrice(row.rateCode, row.roomClassCode, row.arrivalDate, row.roomClassId)
      if (price > 0) {
        row.price = price
        row.basePrice = price
      }
    }
  } else {
    // Người dùng chọn lại "— Chọn giá phòng —" (rỗng): Lấy giá mặc định của loại phòng
    const matchedClass = roomClasses.value?.find(c => c.id === Number(row.roomClassId) || c.code === row.roomClassCode)
    if (matchedClass) {
      const defaultPrice = Number(matchedClass.price ?? matchedClass.room_price ?? matchedClass.standard_rate ?? 0)
      row.price = defaultPrice
      row.basePrice = defaultPrice
    }
  }
  syncAllocationToRooms(row)
}

function handleRoomRateCodeChange(room) {
  if (room.rateCode && room.rateCode !== 'Vui lòng chọn giá phòng') {
    const rcObj = roomRateCodes.value.find(rc => rc.Ma === room.rateCode)
    if (rcObj) {
      // Check expiration dates
      if (rcObj.BeginDate || rcObj.EndDate) {
        const arr = new Date(room.checkIn)
        const dep = new Date(room.checkOut)
        const begin = rcObj.BeginDate ? new Date(rcObj.BeginDate) : null
        const end = rcObj.EndDate ? new Date(rcObj.EndDate) : null
        
        arr.setHours(0,0,0,0)
        dep.setHours(0,0,0,0)
        if (begin) begin.setHours(0,0,0,0)
        if (end) end.setHours(0,0,0,0)

        if ((begin && arr < begin) || (end && dep > end)) {
          uiStore.showToast(`Mã giá phòng ${room.rateCode} đã hết hạn hoặc không áp dụng trong thời gian lưu trú này!`, 'error')
          room.rateCode = 'Vui lòng chọn giá phòng'
          return
        }
      }
      
      room.breakfast = !!rcObj.IncludeBF
      
      const price = getRateCodePrice(room.rateCode, room.roomClassCode, room.checkIn)
      if (price > 0) {
        room.price = price
        room.basePrice = price
        room.total = calculateRoomTotal(room)
      }

      // Sync back to allocation row
      if (modalForm.value.roomAllocations) {
        const alloc = modalForm.value.roomAllocations.find(a => a.roomClassId === room.roomClassId)
        if (alloc) {
          alloc.rateCode = room.rateCode
          alloc.price = room.price
          alloc.basePrice = room.price
          alloc.breakfastIncluded = room.breakfast
        }
      }
    }
  }
}

function isPriceDisabled(row) {
  if (!row.rateCode) return false
  const rcObj = roomRateCodes.value.find(rc => rc.Ma === row.rateCode)
  if (rcObj) {
    return !rcObj.AllowChangeRate
  }
  return false
}

function isRoomPriceDisabled(room) {
  if (!room.rateCode || room.rateCode === 'Vui lòng chọn giá phòng') return false
  const rcObj = roomRateCodes.value.find(rc => rc.Ma === room.rateCode)
  if (rcObj) {
    return !rcObj.AllowChangeRate
  }
  return false
}

const activeDiscountRowId = ref(null)

function toggleDiscountPopover(row) {
  if (activeDiscountRowId.value === row.roomClassId) {
    activeDiscountRowId.value = null
  } else {
    activeDiscountRowId.value = row.roomClassId
  }
}

const activeRoomDiscountId = ref(null)

function toggleRoomDiscountPopover(room) {
  if (activeRoomDiscountId.value === room.id) {
    activeRoomDiscountId.value = null
  } else {
    activeRoomDiscountId.value = room.id
  }
}

function calculateRoomAdjustedPrice(room) {
  if (room.basePrice === undefined || room.basePrice === null || isNaN(room.basePrice)) {
    room.basePrice = cleanCurrencyValue(room.price)
  }
  
  let val = Number(room.discountValue) || 0
  if (isNaN(val)) val = 0
  
  let adjusted = Number(room.basePrice) || 0
  if (isNaN(adjusted)) adjusted = 0
  
  if (room.discountUnit === 'percent') {
    const adjustAmount = Math.round(adjusted * (val / 100))
    if (room.discountType === 'up') {
      adjusted = adjusted + adjustAmount
    } else {
      adjusted = Math.max(0, adjusted - adjustAmount)
    }
  } else {
    if (room.discountType === 'up') {
      adjusted = adjusted + val
    } else {
      adjusted = Math.max(0, adjusted - val)
    }
  }
  
  room.price = adjusted
  room.total = calculateRoomTotal(room)
  
  // Sync back to allocation row
  const parentObj = (modalForm.value && isModalOpen.value) ? modalForm.value : activeTab.value
  if (parentObj && parentObj.roomAllocations) {
    const alloc = parentObj.roomAllocations.find(a => a.roomClassId === room.roomClassId)
    if (alloc) {
      alloc.price = room.price
      alloc.basePrice = room.basePrice
      alloc.discountType = room.discountType
      alloc.discountValue = room.discountValue
      alloc.discountUnit = room.discountUnit
    }
  }
}

function closeDiscountPopover() {
  activeDiscountRowId.value = null
}

function handleGlobalClick(event) {
  isColorPickerOpen.value = false
  closeDiscountPopover()
  const toggleBtn = document.getElementById('column-selector-toggle')
  const selectorContainer = document.getElementById('column-selector-container')
  if (toggleBtn && selectorContainer) {
    if (!toggleBtn.contains(event.target) && !selectorContainer.contains(event.target)) {
      showColumnSelector.value = false
    }
  }
}

function calculateAdjustedPrice(row) {
  if (row.basePrice === undefined || row.basePrice === null || isNaN(row.basePrice)) {
    row.basePrice = cleanCurrencyValue(row.price)
  }
  
  let val = Number(row.discountValue) || 0
  if (isNaN(val)) val = 0
  
  let adjusted = Number(row.basePrice) || 0
  if (isNaN(adjusted)) adjusted = 0
  
  if (row.discountUnit === 'percent') {
    const adjustAmount = Math.round(adjusted * (val / 100))
    if (row.discountType === 'up') {
      adjusted = adjusted + adjustAmount
    } else {
      adjusted = Math.max(0, adjusted - adjustAmount)
    }
  } else {
    if (row.discountType === 'up') {
      adjusted = adjusted + val
    } else {
      adjusted = Math.max(0, adjusted - val)
    }
  }
  
  row.price = adjusted
  
  // Also update the formatted discount string to save to database or display
  row.discount = getDiscountLabel(row)
  
  syncAllocationToRooms(row)
}

function getDiscountLabel(row) {
  if (!row.discountValue) return 'Tăng/Giảm giá'
  const sign = row.discountType === 'up' ? '+' : '-'
  const unit = row.discountUnit === 'percent' ? '%' : ''
  const formattedVal = row.discountUnit === 'percent' ? row.discountValue : formatCurrencyInput(row.discountValue)
  return `${sign}${formattedVal}${unit}`
}

function getOccupancyCount(row) {
  if (modalForm.value.status === 3 || modalForm.value.status === 100) {
    return 0
  }
  if (!modalForm.value.rooms) return 0
  return modalForm.value.rooms.filter(r => 
    r.roomClassId === row.roomClassId && 
    r.bookingRoomStatus !== 3 && 
    r.bookingRoomStatus !== 100
  ).length
}

function getRateCodePrice(rateCodeMa, roomClassCode, dateStr, roomClassId) {
  const rcObj = roomRateCodes.value.find(rc => rc.Ma === rateCodeMa || rc.code === rateCodeMa)
  if (!rcObj) return 0
  
  const plans = rcObj.ratePlans || rcObj.rate_plans || []
  if (!plans || plans.length === 0) return 0

  // 1. Find active plan code for the date (dateStr) from daily_mappings
  let activePlanCode = 'DEFAULT'
  if (dateStr && rcObj.daily_mappings && Array.isArray(rcObj.daily_mappings)) {
    const mapping = rcObj.daily_mappings.find(m => m.Date === dateStr)
    if (mapping) {
      activePlanCode = mapping.Code
    }
  }

  // 2. Find the plan corresponding to activePlanCode
  let plan = plans.find(p => p.Code === activePlanCode)
  if (!plan) {
    plan = plans.find(p => p.Code === 'DEFAULT') || plans[0]
  }
  
  if (!plan || !plan.Period) return 0
  
  const period = typeof plan.Period === 'string' ? JSON.parse(plan.Period) : plan.Period
  if (!period) return 0

  const planCode = plan.Code || 'DEFAULT'

  // 3. Tra cứu theo roomClassId (VD: "9" cho JST)
  if (roomClassId && period[roomClassId] !== undefined) {
    const val = Number(period[roomClassId])
    if (!isNaN(val) && val > 0) return val
  }
  if (roomClassId && period[String(roomClassId)] !== undefined) {
    const val = Number(period[String(roomClassId)])
    if (!isNaN(val) && val > 0) return val
  }

  // 4. Tra cứu theo key ${planCode}_${roomClassCode}_Double hoặc ${planCode}_${roomClassCode}_...
  const key = `${planCode}_${roomClassCode}_Double`
  if (period[key] !== undefined) {
    return Number(period[key]) || 0
  }
  
  const legacyKey = `${rateCodeMa}_${roomClassCode}_Double`
  if (period[legacyKey] !== undefined) {
    return Number(period[legacyKey]) || 0
  }
  
  if (period) {
    const matchingKey = Object.keys(period).find(k => 
      k.startsWith(`${planCode}_${roomClassCode}_`) || 
      k.startsWith(`${rateCodeMa}_${roomClassCode}_`) ||
      k.includes(`_${roomClassCode}_`)
    )
    if (matchingKey && period[matchingKey] !== undefined) {
      return Number(period[matchingKey]) || 0
    }
  }

  return Number(rcObj.Value || rcObj.Gia || rcObj.price || 0)
}

async function handleAddTabClick() {
  isEditModal.value = false
  modalPos.value = { x: 0, y: 0 }
  isColorChanged.value = false
  const today = systemDate.value || new Date().toISOString().split('T')[0]
  const tomorrowDate = new Date(today); tomorrowDate.setDate(tomorrowDate.getDate() + 1)
  const tomorrow = tomorrowDate.toISOString().split('T')[0]

  modalForm.value = {
    ...emptyForm(),
    checkIn: today,
    checkOut: tomorrow,
    nights: 1,
    registrationStatusId: registrationStatuses.value.find(s => !s.is_hidden)?.id || null,
    paymentMethodId: null,
    marketId: null,
    customerSourceId: null,
    color: hotelSettings.value?.ColorDefaultBookingRoomMap || '#97D5FF',
    isGit: true,
    shuttleInfo: [
      { id: Date.now(), type: 'Đón', vehicle: '7 Seater car', code: '', date: today, time: '00:00', price: 0, location: '', note: '' }
    ],
    roomAllocations: initRoomAllocations([], today, tomorrow),
  }
  await updateRoomAvailability()
  modalSubTab.value = 'info'
  isModalOpen.value = true
}

async function openEditModal() {
  const tab = activeTab.value
  if (!tab) return
  modalPos.value = { x: 0, y: 0 }
  isEditModal.value = true
  isColorChanged.value = false
  modalForm.value = {
    dbId: tab.dbId,
    bookingCode: tab.id,
    bookingName: tab.bookingName,
    color: tab.color || '#000000',
    checkIn: tab.checkIn,
    checkOut: tab.checkOut,
    nights: tab.nights,
    registrationStatusId: tab.registrationStatusId,
    confirmDate: tab.confirmDate || '',
    expiredDate: tab.expiredDate || '',
    companyId: tab.companyId,
    paymentMethodId: tab.paymentMethodId,
    paymentValue: tab.paymentValue || 0,
    externalBookingCode: tab.externalBookingCode || '',
    salesPerson: tab.salesPerson || '',
    isGit: true,
    hasVat: tab.hasVat || false,
    marketId: tab.marketId,
    customerSourceId: tab.customerSourceId,
    bookerId: tab.bookerId,
    contactName: tab.contactName || '',
    contactEmail: tab.contactEmail || '',
    contactPhone: tab.contactPhone || '',
    note: tab.note || '',
    specialRequests: tab.specialRequests || '',
    shuttleInfo: (tab.shuttleInfo && tab.shuttleInfo.length > 0)
      ? JSON.parse(JSON.stringify(tab.shuttleInfo))
      : [ { id: Date.now(), type: 'Đón', vehicle: '7 Seater car', code: '', date: tab.checkIn || systemDate.value || new Date().toISOString().split('T')[0], time: '00:00', price: 0, location: '', note: '' } ],
    roomAllocations: initRoomAllocations(tab.roomAllocations || [], tab.checkIn, tab.checkOut),
    deposits: JSON.parse(JSON.stringify(tab.deposits || [])),
    rooms: JSON.parse(JSON.stringify(tab.rooms || [])),
    createdBy: tab.createdBy || '',
    createdAt: tab.createdAt || '',
  }
  await updateRoomAvailability()
  modalSubTab.value = 'info'
  isModalOpen.value = true
  nextTick(() => {
    autoResizeTextarea()
  })
}

function handleBookerChange() {
  const selected = bookers.value.find(b => b.id === Number(modalForm.value.bookerId))
  if (selected) {
    modalForm.value.contactName  = selected.name
    modalForm.value.contactEmail = selected.email || ''
    modalForm.value.contactPhone = selected.phone || ''
  }
}

function handleCompanyChange() {
  const co = companies.value.find(c => c.id === Number(modalForm.value.companyId))
  if (!co) return

  // Auto-fill thông tin liên hệ từ booker của công ty (nếu chưa có)
  if (co.booker_id) {
    const booker = bookers.value.find(bk => bk.id === co.booker_id)
    if (booker) {
      if (!modalForm.value.bookerId) modalForm.value.bookerId = booker.id
      if (!modalForm.value.contactName)  modalForm.value.contactName  = booker.name  || ''
      if (!modalForm.value.contactEmail) modalForm.value.contactEmail = booker.email || ''
      if (!modalForm.value.contactPhone) modalForm.value.contactPhone = booker.phone || ''
    }
  }

  // Auto-fill người bán từ công ty (nếu chưa chọn)
  if (!modalForm.value.salesPerson && co.sales_person) {
    modalForm.value.salesPerson = co.sales_person.username || co.sales_person.name || ''
  }

  // Auto-fill thị trường từ công ty
  if (co.market_id) {
    modalForm.value.marketId = co.market_id
  }

  // Auto-fill nguồn khách từ công ty
  if (co.customer_source_id) {
    modalForm.value.customerSourceId = co.customer_source_id
  }
}

watch(() => modalForm.value.registrationStatusId, (newId) => {
  if (newId) {
    const st = registrationStatuses.value.find(rs => rs.id === Number(newId))
    if (st && st.is_availability === false) {
      uiStore.showToast('Chú ý: Tình trạng đăng ký này không giữ phòng trống (is_availability = 0)', 'info')
    }
  }
  handleConfirmDateCalculation()
})

watch(() => modalForm.value.checkIn, () => {
  handleConfirmDateCalculation()
})

function addDaysToDateStr(dateStr, days) {
  if (!dateStr) return ''
  const parts = dateStr.split('-')
  if (parts.length !== 3) return dateStr
  const y = parseInt(parts[0], 10)
  const m = parseInt(parts[1], 10) - 1
  const d = parseInt(parts[2], 10)
  const date = new Date(y, m, d)
  date.setDate(date.getDate() + days)
  const newY = date.getFullYear()
  const newM = String(date.getMonth() + 1).padStart(2, '0')
  const newD = String(date.getDate()).padStart(2, '0')
  return `${newY}-${newM}-${newD}`
}

function handleConfirmDateCalculation() {
  const statusId = modalForm.value.registrationStatusId
  if (!statusId) return
  const status = registrationStatuses.value.find(s => s.id === Number(statusId))
  if (!status) return

  const statusNameLower = (status.name || '').toLowerCase()
  const isDefinite = statusNameLower.includes('guaranteed') && 
                     !statusNameLower.includes('none') && 
                     !statusNameLower.includes('non')

  console.log('handleConfirmDateCalculation: status changed', {
    statusId,
    statusName: status.name,
    isDefinite,
    checkIn: modalForm.value.checkIn,
    systemDate: systemDate.value
  })

  if (isDefinite) {
    // Chắc chắn: mặc định bằng ngày tạo bk (systemDate)
    modalForm.value.confirmDate = systemDate.value
  } else {
    // Không chắc chắn: ngày lưu trú - ngày xác nhận định nghĩa (cut-off)
    const cutOff = status.confirmation_days || 0
    if (modalForm.value.checkIn) {
      const calcDate = addDaysToDateStr(modalForm.value.checkIn, -cutOff)
      if (calcDate > modalForm.value.checkIn) {
        modalForm.value.confirmDate = modalForm.value.checkIn
      } else {
        modalForm.value.confirmDate = calcDate
      }
    }
  }
}

async function incrementNights() {
  modalForm.value.nights++
  await handleNightsChange()
}

async function decrementNights() {
  if (modalForm.value.nights > 1) {
    modalForm.value.nights--
    await handleNightsChange()
  }
}

function autoResizeTextarea() {
  nextTick(() => {
    const el = document.getElementById('booking-note-textarea')
    if (el) {
      el.style.height = 'auto'
      el.style.height = el.scrollHeight + 'px'
    }
  })
}

function formatDateTime(val) {
  if (!val) return ''
  const d = new Date(val)
  if (isNaN(d.getTime())) return val
  const day = String(d.getDate()).padStart(2, '0')
  const month = String(d.getMonth() + 1).padStart(2, '0')
  const year = d.getFullYear()
  const hours = String(d.getHours()).padStart(2, '0')
  const minutes = String(d.getMinutes()).padStart(2, '0')
  const seconds = String(d.getSeconds()).padStart(2, '0')
  return `${day}/${month}/${year} ${hours}:${minutes}:${seconds}`
}

async function updateRoomAvailability() {
  if (!modalForm.value.checkIn || !modalForm.value.checkOut) return

  if (modalForm.value.checkIn >= modalForm.value.checkOut) {
    if (modalForm.value.roomAllocations) {
      modalForm.value.roomAllocations.forEach(alloc => {
        alloc.availableRooms = 0
      })
    }
    return
  }

  try {
    const res = await fetchAvailability({
      start_date: modalForm.value.checkIn,
      end_date: modalForm.value.checkOut
    })
    const grid = res.data?.data?.grid || res.data?.grid || {}

    const dates = []
    let curr = new Date(modalForm.value.checkIn)
    const end = new Date(modalForm.value.checkOut)
    while (curr < end) {
      dates.push(curr.toISOString().split('T')[0])
      curr.setDate(curr.getDate() + 1)
    }

    if (modalForm.value.roomAllocations) {
      modalForm.value.roomAllocations.forEach(alloc => {
        const classCode = alloc.roomClassCode
        const dayData = grid[classCode] || {}
        
        let minAv = 999
        dates.forEach(dStr => {
          const av = dayData[dStr]?.av !== undefined ? Number(dayData[dStr].av) : null
          if (av !== null && av < minAv) {
            minAv = av
          }
        })

        if (minAv === 999) {
          minAv = 0
        }
        


        alloc.availableRooms = minAv
      })
    }
  } catch (err) {
    console.error('Lỗi khi tải tình trạng phòng trống:', err)
  }
}

function updateAllocatedRooms(row) {
  if (row.quantity === undefined || row.quantity === null || row.quantity < 0) {
    row.quantity = 0
  }

  validateRoomQuantity(row)

  if (!modalForm.value.rooms) {
    modalForm.value.rooms = []
  }

  const currentRooms = modalForm.value.rooms.filter(r => r.roomClassId === row.roomClassId && !r.bookingRoomId)
  const diff = row.quantity - currentRooms.length

  if (diff > 0) {
    for (let i = 0; i < diff; i++) {
      modalForm.value.rooms.push({
        id: Date.now() + Math.random(),
        roomClassId: row.roomClassId,
        roomClassName: row.roomClassName,
        shape: row.roomClassCode,
        roomNumber: '',
        checkIn: row.arrivalDate || modalForm.value.checkIn,
        checkOut: row.departureDate || modalForm.value.checkOut,
        nights: row.nights || modalForm.value.nights,
        price: row.price || 0,
        rateCode: row.ratePlanCode || 'Vui lòng chọn giá phòng',
        guestName: '',
        adults: row.adults || 2,
        babies: row.babies || 0,
        children: row.children || 0,
        breakfast: row.breakfastIncluded !== undefined ? !!row.breakfastIncluded : true,
        extraBedPrice: row.extraBedPrice !== undefined ? Number(row.extraBedPrice) : 0,
        hourly: false,
        arrivalTime: '14:00',
        hoursOut: '12:00',
        isPreassigned: false,
        initialRoomClass: row.roomClassCode,
        transferredFrom: '',
        roomStatus: 'Sạch',
        allotmentCode: '',
        roomCode: '',
        total: Number(row.price || 0) * (Number(row.nights || modalForm.value.nights) || 1) + getServicesTotal(row),
        discount: null,
        discountType: 'down',
        discountValue: 0,
        discountUnit: 'percent',
        basePrice: row.price || 0,
        bookingRoomStatus: 0,
      })
    }
  } else if (diff < 0) {
    const toRemoveCount = Math.abs(diff)
    let removed = 0
    modalForm.value.rooms = modalForm.value.rooms.filter(r => {
      if (r.roomClassId === row.roomClassId && !r.bookingRoomId && removed < toRemoveCount) {
        removed++
        return false
      }
      return true
    })
  }
}

watch(() => modalForm.value.roomAllocations, (newAllocations) => {
  if (!newAllocations || !modalForm.value.rooms) return
  newAllocations.forEach(alloc => {
    modalForm.value.rooms.forEach(r => {
      if (r.roomClassId === alloc.roomClassId && !r.bookingRoomId) {
        if (r.price !== alloc.price) {
          r.price = alloc.price
          r.total = (alloc.price || 0) * (r.nights || 1)
        }
        r.adults = alloc.adults
        r.babies = alloc.babies
        r.children = alloc.children
        r.breakfast = !!alloc.breakfastIncluded
      }
    })
  })
}, { deep: true })

watch(() => modalForm.value.rooms, (newRooms) => {
  if (!modalForm.value.roomAllocations) return
  const counts = {}
  if (newRooms) {
    newRooms.forEach(r => {
      if (r.roomClassId && !r.bookingRoomId) {
        counts[r.roomClassId] = (counts[r.roomClassId] || 0) + 1
      }
    })
  }
  modalForm.value.roomAllocations.forEach(alloc => {
    const actualQty = counts[alloc.roomClassId] || 0
    if (alloc.quantity !== actualQty) {
      alloc.quantity = actualQty
    }
  })
}, { deep: true, immediate: true })

function validateRoomQuantity(alloc) {
  if (hotelSettings.value?.allow_over_room_type === 0 || hotelSettings.value?.allow_over_room_type === false) {
    if (alloc.quantity > alloc.availableRooms) {
      uiStore.showToast('Cảnh báo: Đã vượt quá số lượng phòng trống cho phép!', 'error')
      if (alloc.availableRooms === 0) {
        alloc.quantity = 0
      }
    }
  }
}

async function handleDateChange() {
  const ci = new Date(modalForm.value.checkIn)
  const co = new Date(modalForm.value.checkOut)
  if (!isNaN(ci) && !isNaN(co)) {
    const diff = Math.ceil((co - ci) / 86400000)
    modalForm.value.nights = diff > 0 ? diff : 1
    
    // Đồng bộ ngày check-in/check-out cho tất cả các phòng & allocations
    if (modalForm.value.roomAllocations) {
      modalForm.value.roomAllocations.forEach(alloc => {
        alloc.arrivalDate = modalForm.value.checkIn
        alloc.departureDate = modalForm.value.checkOut
        alloc.nights = modalForm.value.nights
      })
    }
    if (modalForm.value.rooms) {
      modalForm.value.rooms.forEach(r => {
        r.checkIn = modalForm.value.checkIn
        r.checkOut = modalForm.value.checkOut
        r.nights = modalForm.value.nights
        r.total = (r.price || 0) * (r.nights || 1)
      })
    }
    
    await updateRoomAvailability()
  }
}

async function handleMainDateChange() {
  const tab = activeTab.value
  if (!tab) return
  const ci = new Date(tab.checkIn)
  const co = new Date(tab.checkOut)
  if (!isNaN(ci) && !isNaN(co)) {
    const diff = Math.ceil((co - ci) / 86400000)
    tab.nights = diff > 0 ? diff : 1
    
    // Sync dates to allocations & rooms in tab
    if (tab.roomAllocations) {
      tab.roomAllocations.forEach(alloc => {
        alloc.arrivalDate = tab.checkIn
        alloc.departureDate = tab.checkOut
        alloc.nights = tab.nights
      })
    }
    if (tab.rooms) {
      tab.rooms.forEach(r => {
        r.checkIn = tab.checkIn
        r.checkOut = tab.checkOut
        r.nights = tab.nights
        r.total = (r.price || 0) * (r.nights || 1)
      })
    }
    await updateRoomAvailability()
  }
}

async function handleMainNightsChange() {
  const tab = activeTab.value
  if (!tab) return
  const ci = new Date(tab.checkIn)
  if (!isNaN(ci) && tab.nights > 0) {
    const co = new Date(ci)
    co.setDate(ci.getDate() + Number(tab.nights))
    tab.checkOut = co.toISOString().split('T')[0]
    
    // Sync to rooms and allocations in tab
    if (tab.roomAllocations) {
      tab.roomAllocations.forEach(alloc => {
        alloc.departureDate = tab.checkOut
        alloc.nights = tab.nights
      })
    }
    if (tab.rooms) {
      tab.rooms.forEach(r => {
        r.checkOut = tab.checkOut
        r.nights = tab.nights
        r.total = (r.price || 0) * (r.nights || 1)
      })
    }
    await updateRoomAvailability()
  }
}

async function handleRowDateChange(row) {
  const ci = new Date(row.arrivalDate)
  const co = new Date(row.departureDate)
  if (!isNaN(ci) && !isNaN(co)) {
    const diff = Math.ceil((co - ci) / 86400000)
    row.nights = diff > 0 ? diff : 1
    
    if (row.rateCode) {
      const price = getRateCodePrice(row.rateCode, row.roomClassCode, row.arrivalDate)
      if (price > 0) {
        row.price = price
      }
    }

    if (modalForm.value.rooms) {
      modalForm.value.rooms.forEach(r => {
        if (r.roomClassId === row.roomClassId && !r.bookingRoomId) {
          r.checkIn = row.arrivalDate
          r.checkOut = row.departureDate
          r.nights = row.nights
          r.total = (r.price || 0) * (r.nights || 1)
        }
      })
    }
    syncAllocationToRooms(row)

    try {
      const res = await checkAvailability({
        room_class_id: row.roomClassId,
        arrival_date: row.arrivalDate,
        departure_date: row.departureDate,
        exclude_booking_room_id: undefined
      })
      row.availableRooms = res.data?.av !== undefined ? Number(res.data.av) : 0
    } catch(e) {
      console.error(e)
    }
  }
}

function syncBookingDatesFromRooms(tab) {
  if (!tab || !tab.rooms || tab.rooms.length === 0) return
  let maxCheckOut = null
  let minCheckIn = null
  
  tab.rooms.forEach(r => {
    if (r.checkIn) {
      if (!minCheckIn || r.checkIn < minCheckIn) {
        minCheckIn = r.checkIn
      }
    }
    if (r.checkOut) {
      if (!maxCheckOut || r.checkOut > maxCheckOut) {
        maxCheckOut = r.checkOut
      }
    }
  })
  
  if (minCheckIn) tab.checkIn = minCheckIn
  if (maxCheckOut) tab.checkOut = maxCheckOut
  
  const ci = new Date(tab.checkIn)
  const co = new Date(tab.checkOut)
  if (!isNaN(ci) && !isNaN(co)) {
    const diff = Math.ceil((co - ci) / 86400000)
    tab.nights = diff > 0 ? diff : 1
  }
}

async function handleRowDateChangeInline(room) {
  if (room.checkIn && room.checkOut) {
    const ci = new Date(room.checkIn)
    const co = new Date(room.checkOut)
    if (!isNaN(ci) && !isNaN(co)) {
      if (co <= ci) {
        const nextDay = new Date(ci)
        nextDay.setDate(ci.getDate() + 1)
        room.checkOut = nextDay.toISOString().split('T')[0]
      }
      
      const diffTime = new Date(room.checkOut).getTime() - new Date(room.checkIn).getTime()
      room.nights = Math.max(1, Math.ceil(diffTime / (1000 * 60 * 60 * 24)))
      room.total = calculateRoomTotal(room)

      // Đồng bộ ngược lại allocation của tab hiện tại để khi lưu sẽ update đúng
      const tab = activeTab.value
      if (tab && tab.roomAllocations) {
        const alloc = tab.roomAllocations.find(a => a.roomClassId === room.roomClassId)
        if (alloc) {
          alloc.arrivalDate = room.checkIn
          alloc.departureDate = room.checkOut
          alloc.nights = room.nights
        }
        syncBookingDatesFromRooms(tab)
      }
    }
  }
}

async function handleRowNightsChangeInline(room) {
  if (room.checkIn && room.nights > 0) {
    const ci = new Date(room.checkIn)
    if (!isNaN(ci)) {
      const co = new Date(ci)
      co.setDate(ci.getDate() + Number(room.nights))
      room.checkOut = co.toISOString().split('T')[0]
      room.total = calculateRoomTotal(room)

      // Đồng bộ ngược lại allocation của tab hiện tại
      const tab = activeTab.value
      if (tab && tab.roomAllocations) {
        const alloc = tab.roomAllocations.find(a => a.roomClassId === room.roomClassId)
        if (alloc) {
          alloc.departureDate = room.checkOut
          alloc.nights = Number(room.nights)
        }
        syncBookingDatesFromRooms(tab)
      }
    }
  }
}



function getNormalizedCategory(rc, forms) {
  if (!rc || !rc.name) return ''
  let categoryName = rc.name
  if (forms && forms.length > 0) {
    forms.forEach(rf => {
      const regex = new RegExp('\\b' + rf.name + '\\b', 'gi')
      categoryName = categoryName.replace(regex, '')
    })
  }
  return categoryName.replace(/\s+/g, ' ').trim().toLowerCase()
}

function getAvailableFormsForRoom(room) {
  const currentClass = roomClasses.value.find(c => c.id === room.roomClassId)
  if (!currentClass) return []
  const category = getNormalizedCategory(currentClass, roomForms.value)
  const siblingClasses = roomClasses.value.filter(c => getNormalizedCategory(c, roomForms.value) === category)
  const formIds = siblingClasses.map(c => c.room_form_id).filter(id => id !== undefined && id !== null)
  return roomForms.value.filter(f => formIds.includes(f.id))
}

function getRoomFormId(room) {
  const currentClass = roomClasses.value.find(c => c.id === room.roomClassId)
  return currentClass ? currentClass.room_form_id : ''
}

function handleRoomFormChange(room, formId) {
  const currentClass = roomClasses.value.find(c => c.id === room.roomClassId)
  if (!currentClass) return
  
  const category = getNormalizedCategory(currentClass, roomForms.value)
  const targetClass = roomClasses.value.find(c => {
    return getNormalizedCategory(c, roomForms.value) === category && c.room_form_id === Number(formId)
  })
  
  if (targetClass) {
    const oldClassId = room.roomClassId
    room.roomClassId = targetClass.id
    handleRoomClassChange(room, oldClassId)
  }
}

function handleRoomClassChange(room, oldClassId) {
  const newClass = roomClasses.value.find(c => c.id === Number(room.roomClassId))
  if (newClass) {
    room.type = newClass.name
    room.shape = newClass.room_form_name || newClass.code
    room.roomNumber = '' // reset số phòng cũ
  }
  
  const tab = activeTab.value
  if (!tab || !tab.roomAllocations) return
  
  // 1. Tăng quantity ở allocation mới (hoặc tạo mới nếu chưa có)
  let newAlloc = tab.roomAllocations.find(a => a.roomClassId === Number(room.roomClassId))
  if (!newAlloc && newClass) {
    newAlloc = {
      roomClassId: newClass.id,
      roomClassCode: newClass.code,
      roomClassName: newClass.name,
      arrivalDate: room.checkIn || tab.checkIn,
      departureDate: room.checkOut || tab.checkOut,
      quantity: 0,
      price: Number(newClass.price) || Number(room.price) || 0,
      rateCode: '',
      discount: 'Tăng/Giảm giá',
      discountType: 'down',
      discountValue: 0,
      discountUnit: 'percent',
      basePrice: Number(newClass.price) || Number(room.price) || 0,
      upgradeClassId: null,
      adults: newClass.max_adults || 2,
      babies: 0,
      children: 0,
      breakfastIncluded: true,
      rooms: []
    }
    tab.roomAllocations.push(newAlloc)
  }
  if (newAlloc) {
    newAlloc.quantity = (Number(newAlloc.quantity) || 0) + 1
  }
  
  // 2. Giảm quantity ở allocation cũ
  if (oldClassId !== undefined && oldClassId !== null) {
    const oldAlloc = tab.roomAllocations.find(a => a.roomClassId === Number(oldClassId))
    if (oldAlloc) {
      oldAlloc.quantity = Math.max(0, (Number(oldAlloc.quantity) || 0) - 1)
      if (oldAlloc.quantity === 0) {
        tab.roomAllocations = tab.roomAllocations.filter(a => a.roomClassId !== Number(oldClassId))
      }
    }
  }
}

async function handleNightsChange() {
  const ci = new Date(modalForm.value.checkIn)
  if (!isNaN(ci) && modalForm.value.nights > 0) {
    const co = new Date(ci)
    co.setDate(ci.getDate() + Number(modalForm.value.nights))
    modalForm.value.checkOut = co.toISOString().split('T')[0]
    
    // Đồng bộ ngày check-in/check-out cho tất cả các phòng & allocations
    if (modalForm.value.roomAllocations) {
      modalForm.value.roomAllocations.forEach(alloc => {
        alloc.arrivalDate = modalForm.value.checkIn
        alloc.departureDate = modalForm.value.checkOut
        alloc.nights = Number(modalForm.value.nights)
      })
    }
    if (modalForm.value.rooms) {
      modalForm.value.rooms.forEach(r => {
        r.checkIn = modalForm.value.checkIn
        r.checkOut = modalForm.value.checkOut
        r.nights = Number(modalForm.value.nights)
        r.total = (r.price || 0) * (r.nights || 1)
      })
    }
    
    await updateRoomAvailability()
  }
}

function addShuttleRow() {
  modalForm.value.shuttleInfo.push({
    id: Date.now() + Math.random(),
    type: 'Đón',
    vehicle: '7 Seater car',
    code: '',
    date: modalForm.value.checkIn,
    time: '12:00',
    price: 0,
    location: '',
    note: ''
  })
}

function removeShuttleRow(index) {
  modalForm.value.shuttleInfo.splice(index, 1)
}

function copyConfirmDate() {
  if (modalForm.value.confirmDate) {
    navigator.clipboard.writeText(modalForm.value.confirmDate)
    uiStore.showToast('Đã sao chép ngày xác nhận!', 'info')
  }
}

async function handleSaveNewBooking() {
  if (!modalForm.value.bookingName.trim()) { uiStore.showToast('Vui lòng nhập tên đăng ký!', 'warning'); return }
  if (!modalForm.value.checkIn || !modalForm.value.checkOut) { uiStore.showToast('Vui lòng chọn ngày đến và ngày đi!', 'warning'); return }
  if (!modalForm.value.registrationStatusId) { uiStore.showToast('Vui lòng chọn Tình trạng đăng ký!', 'warning'); return }
  if (!modalForm.value.companyId)        { uiStore.showToast('Vui lòng chọn Công ty!', 'warning'); return }
  if (!modalForm.value.marketId)         { uiStore.showToast('Vui lòng chọn Thị trường!', 'warning'); return }
  if (!modalForm.value.customerSourceId) { uiStore.showToast('Vui lòng chọn Nguồn khách!', 'warning'); return }

  const sysDateStr = systemDate.value || parseApiDate(new Date())
  if (modalForm.value.checkIn < sysDateStr) {
    uiStore.showToast(`Ngày đến không được nhỏ hơn ngày hệ thống (${sysDateStr})!`, 'warning')
    return
  }

  const dupError = validateRoomsDuplication(modalForm.value.rooms)
  if (dupError) {
    uiStore.showToast(dupError, 'error')
    return
  }

  isSavingModal.value = true
  try {
    const payload = {
      booking_name:           modalForm.value.bookingName.toUpperCase(),
      color:                  (isColorChanged.value || modalForm.value.color !== '#000000') ? modalForm.value.color : null,
      arrival_date:           modalForm.value.checkIn,
      departure_date:         modalForm.value.checkOut,
      num_of_days:            modalForm.value.nights,
      registration_status_id: modalForm.value.registrationStatusId || null,
      confirm_date:           modalForm.value.confirmDate || null,
      expired_date:           modalForm.value.expiredDate || null,
      company_id:             modalForm.value.companyId || null,
      market_id:              modalForm.value.marketId || null,
      customer_source_id:     modalForm.value.customerSourceId || null,
      booker_id:              modalForm.value.bookerId || null,
      contact_name:           modalForm.value.contactName || null,
      contact_email:          modalForm.value.contactEmail || null,
      contact_phone:          modalForm.value.contactPhone || null,
      payment_method_id:      modalForm.value.paymentMethodId || null,
      payment_value:          modalForm.value.paymentValue || 0,
      external_booking_code:  modalForm.value.externalBookingCode || null,
      sales_person:           modalForm.value.salesPerson || null,
      is_git:                 modalForm.value.isGit ? 1 : 0,
      has_vat:                modalForm.value.hasVat ? 1 : 0,
      note:                   modalForm.value.note || null,
      special_requests:       modalForm.value.specialRequests || null,
      shuttle_info:           modalForm.value.shuttleInfo || [],
      room_allocations:       syncRoomsToAllocations(modalForm.value),
      deposit_details:        modalForm.value.deposits || [],
    }
    if (isEditModal.value && modalForm.value.dbId) {
      const res = await updateBooking(modalForm.value.dbId, payload)
      const updated = res.data?.data || res.data
      await loadBookings()
      const idx = tabs.value.findIndex(t => t.dbId === modalForm.value.dbId)
      if (idx !== -1) { activeTabId.value = tabs.value[idx].id }
      uiStore.showToast(`Cập nhật đăng ký ${updated.booking_code} thành công!`, 'success')
    } else {
      const res = await createBooking(payload)
      const created = res.data?.data || res.data
      await loadBookings()
      const idx = tabs.value.findIndex(t => t.dbId === created.id)
      if (idx !== -1) { activeTabId.value = tabs.value[idx].id }
      uiStore.showToast(`Tạo đăng ký ${created.booking_code} thành công!`, 'success')
    }
    closeModal()
  } catch (err) {
    const errors = err.response?.data?.errors
    const msg = errors ? Object.values(errors)[0]?.[0] : (err.response?.data?.message || 'Đã xảy ra lỗi!')
    uiStore.showToast(msg, 'error')
  } finally {
    isSavingModal.value = false
  }
}

function parseDateVi(dateStr) {
  if (!dateStr) return ''
  const parts = dateStr.split('/')
  if (parts.length === 3) return `${parts[2]}-${parts[1]}-${parts[0]}`
  return dateStr
}

function formatDateVi(dateStr) {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  if (isNaN(d)) return dateStr
  return `${String(d.getDate()).padStart(2, '0')}/${String(d.getMonth() + 1).padStart(2, '0')}/${d.getFullYear()}`
}

const vacantRoomsMap = ref({})

async function loadVacantRoomsForRoom(room) {
  const roomClassId = room.roomClassId
  const ci = parseDateVi(room.checkIn)
  const co = parseDateVi(room.checkOut)
  if (!roomClassId || !ci || !co) return

  const cacheKey = `${roomClassId}_${ci}_${co}`
  if (vacantRoomsMap.value[cacheKey]) return

  try {
    const res = await fetchVacantRooms({
      room_class_id: roomClassId,
      arrival_date: ci,
      departure_date: co,
      exclude_booking_room_id: room.bookingRoomId || undefined
    })
    vacantRoomsMap.value[cacheKey] = res.data?.data || res.data || []
  } catch (err) {
    console.error('Không thể tải danh sách phòng trống:', err)
  }
}

function getVacantRoomsList(room) {
  const ci = parseDateVi(room.checkIn)
  const co = parseDateVi(room.checkOut)
  const cacheKey = `${room.roomClassId}_${ci}_${co}`
  const list = vacantRoomsMap.value[cacheKey] || []
  
  const parentObj = (isModalOpen.value && modalForm.value) ? modalForm.value : activeTab.value
  const assignedRoomNumbers = (parentObj?.rooms || [])
    .filter(r => r.id !== room.id && r.roomNumber)
    .map(r => r.roomNumber)

  return list.filter(r => r.room_number !== room.roomNumber && !assignedRoomNumbers.includes(r.room_number))
}

function validateRoomsDuplication(rooms) {
  if (!rooms) return null
  const roomsWithNumber = rooms.filter(r => r.roomNumber && String(r.roomNumber).trim() !== '')
  for (let i = 0; i < roomsWithNumber.length; i++) {
    for (let j = i + 1; j < roomsWithNumber.length; j++) {
      const r1 = roomsWithNumber[i]
      const r2 = roomsWithNumber[j]
      if (r1.roomNumber === r2.roomNumber) {
        const start1 = new Date(r1.checkIn)
        const end1 = new Date(r1.checkOut)
        const start2 = new Date(r2.checkIn)
        const end2 = new Date(r2.checkOut)
        if (start1 < end2 && start2 < end1) {
          return `Số phòng ${r1.roomNumber} bị trùng lặp trong giai đoạn ở trùng nhau (${formatDateVi(r1.checkIn)} → ${formatDateVi(r1.checkOut)} và ${formatDateVi(r2.checkIn)} → ${formatDateVi(r2.checkOut)})!`
        }
      }
    }
  }
  return null
}

function formatCurrencyInput(val) {
  if (val === null || val === undefined || val === '') return '';
  let str = String(val).replace(/[^\d.-]/g, '');
  if (!str) return '';
  
  let parts = str.split('.');
  if (parts.length > 2) parts = [parts[0], parts.slice(1).join('')];
  parts[0] = Number(parts[0]).toLocaleString('en-US');
  
  if (parts.length > 1) {
    const decimalPart = parts[1];
    if (decimalPart === '' || /^0+$/.test(decimalPart)) {
      return parts[0];
    }
    return parts.join('.');
  }
  return parts[0];
}

function cleanCurrencyValue(val) {
  if (val === null || val === undefined || val === '') return 0;
  const cleanStr = String(val).replace(/,/g, '');
  return Number(cleanStr) || 0;
}

function handleRowSelectAll(event) {
  selectedRows.value = event.target.checked ? activeTab.value.rooms.map(r => r.id) : []
}

function handleRowSelect(roomId) {
  if (selectedRows.value.includes(roomId)) selectedRows.value = selectedRows.value.filter(id => id !== roomId)
  else selectedRows.value.push(roomId)
}

function handleSelectAllInGroup(rooms, checked) {
  const ids = rooms.map(r => r.id)
  if (checked) {
    ids.forEach(id => {
      if (!selectedRows.value.includes(id)) {
        selectedRows.value.push(id)
      }
    })
  } else {
    selectedRows.value = selectedRows.value.filter(id => !ids.includes(id))
  }
}

async function triggerAction(actionName) {
  if (actionName === 'Sửa') {
    isEditing.value = true
    const tab = activeTab.value
    if (tab && tab.rooms) {
      tab.rooms.forEach(r => {
        if (r.checkIn) {
          let ci = String(r.checkIn).trim();
          if (ci.includes('/')) ci = parseDateVi(ci);
          r.checkIn = parseApiDate(ci);
        }
        if (r.checkOut) {
          let co = String(r.checkOut).trim();
          if (co.includes('/')) co = parseDateVi(co);
          r.checkOut = parseApiDate(co);
        }
      })
    }
    uiStore.showToast('Bạn có thể trực tiếp chỉnh sửa tên khách hàng hoặc mã giá phòng!', 'info')
  } else if (actionName === 'Quay lại') {
    isEditing.value = false
  } else if (actionName === 'Lưu') {
    const tab = activeTab.value
    if (tab) {
      const dupError = validateRoomsDuplication(tab.rooms)
      if (dupError) {
        uiStore.showToast(dupError, 'error')
        return
      }
    }
    uiStore.confirm({
      title: 'Xác nhận lưu đăng ký',
      message: `Bạn có chắc chắn muốn lưu các thông tin thay đổi cho đăng ký "${tab?.bookingName || ''}"?`,
      confirmText: 'Lưu',
      cancelText: 'Hủy'
    }).then(async (confirmed) => {
      if (!confirmed) return
      isEditing.value = false
      if (tab && tab.dbId) {
        try {
          const payload = {
            booking_name:           tab.bookingName.toUpperCase(),
            arrival_date:           tab.checkIn,
            departure_date:         tab.checkOut,
            num_of_days:            tab.nights,
            registration_status_id: tab.registrationStatusId,
            confirm_date:           tab.confirmDate || null,
            company_id:             tab.companyId || null,
            market_id:              tab.marketId || null,
            customer_source_id:     tab.customerSourceId || null,
            booker_id:              tab.bookerId || null,
            contact_name:           tab.contactName || null,
            contact_email:          tab.contactEmail || null,
            contact_phone:          tab.contactPhone || null,
            payment_method_id:      tab.paymentMethodId || null,
            payment_value:          tab.paymentValue || 0,
            external_booking_code:  tab.externalBookingCode || null,
            sales_person:           tab.salesPerson || null,
            is_git:                 tab.isGit ? 1 : 0,
            has_vat:                tab.hasVat ? 1 : 0,
            note:                   tab.note || null,
            special_requests:       tab.specialRequests || null,
            shuttle_info:           tab.shuttleInfo || [],
            room_allocations:       syncRoomsToAllocations(tab),
          }
          const res = await updateBooking(tab.dbId, payload)
          await loadBookings()
          notifyRoomUpdates()
          uiStore.showToast('Lưu thông tin đăng ký thành công!', 'success')
        } catch (err) {
          console.error(err)
          const errMsg = err.response?.data?.message || 'Không thể lưu thông tin đăng ký!'
          uiStore.showToast(errMsg, 'error')
        }
      } else {
        uiStore.showToast('Lưu thông tin đăng ký thành công!', 'success')
      }
    })
  } else if (actionName === 'Cập nhật' || actionName === 'Thông tin đăng ký') {
    openEditModal()
  } else if (actionName === 'Thông tin khách hàng') {
    openGuestInfoModal()
  } else if (actionName === 'Hóa đơn' || actionName === 'Hoá đơn') {
    uiStore.showToast('Đang tiến hành tạo hóa đơn...', 'success')
  } else if (actionName === 'Nhân bản') {
    openCopyModal()
  } else if (actionName === 'GIAO PHÒNG') {
    if (isCheckInDisabled.value) {
      uiStore.showToast('Tất cả phòng được chọn đã được giao phòng!', 'warning')
      return
    }
    const tab = activeTab.value
    if (!tab || !tab.dbId) {
      uiStore.showToast('Vui lòng lưu thông tin đăng ký trước khi giao phòng!', 'warning')
      return
    }
    if (!tab.rooms || tab.rooms.length === 0) {
      uiStore.showToast('Không có phòng nào để giao!', 'info')
      return
    }

    const selected = tab.rooms.filter(r => selectedRows.value.includes(r.id))
    if (selected.length === 0) {
      uiStore.showToast('Vui lòng tích chọn phòng cần giao phòng!', 'warning')
      return
    }
    const targetList = selected

    if (targetList.some(r => !r.roomNumber)) {
      uiStore.showToast('Có phòng chưa được gán số phòng. Vui lòng gán số phòng trước khi giao phòng!', 'warning')
      return
    }

    uiStore.confirm({
      title: 'Xác nhận giao phòng',
      message: `Bạn có chắc chắn muốn giao phòng cho ${targetList.length} phòng đã chọn?`,
      confirmText: 'Đồng ý', cancelText: 'Hủy'
    }).then(async (confirmed) => {
      if (confirmed) {
        uiStore.showToast('Đang tiến hành giao phòng cho khách...', 'info')
        let successCount = 0
        let failCount = 0
        let failMessages = []

        try {
          for (const r of targetList) {
            if (!r.bookingRoomId) continue
            try {
              const res = await checkInRoom(tab.dbId, r.bookingRoomId)
              if (res.data?.success) {
                successCount++
                r.roomStatus = 'Bẩn'
              } else {
                failCount++
                failMessages.push(res.data?.message || `Phòng ${r.roomNumber} thất bại.`)
              }
            } catch (err) {
              console.error(err)
              failCount++
              failMessages.push(err.response?.data?.message || `Phòng ${r.roomNumber} thất bại.`)
            }
          }

          await loadBookings()
          notifyRoomUpdates()
          selectedRows.value = []

          if (successCount > 0) {
            uiStore.showToast(`Giao phòng thành công ${successCount} phòng!${failCount > 0 ? ` (Thất bại ${failCount} phòng: ${failMessages.join(', ')})` : ''}`, 'success')
          } else {
            uiStore.showToast(`Giao phòng thất bại: ${failMessages.join(', ')}`, 'error')
          }
        } catch(err) {
          console.error(err)
          uiStore.showToast('Có lỗi xảy ra khi giao phòng!', 'error')
        }
      }
    })
  } else if (actionName === 'Nâng hạng phòng') {
    openUpgradeModal()
  } else if (actionName === 'Tự động gán phòng') {
    const tab = activeTab.value
    if (!tab || !tab.dbId) {
      uiStore.showToast('Vui lòng lưu thông tin đăng ký trước khi gán số phòng!', 'warning')
      return
    }
    if (!tab.rooms || tab.rooms.length === 0) {
      uiStore.showToast('Không có phòng nào để gán!', 'info')
      return
    }

    const selected = tab.rooms.filter(r => selectedRows.value.includes(r.id))
    if (selected.length === 0) {
      uiStore.showToast('Vui lòng tích chọn phòng cần tự động gán phòng!', 'warning')
      return
    }
    const targetList = selected

    uiStore.confirm({
      title: 'Tự động gán số phòng',
      message: `Bạn có chắc chắn muốn tự động gán số phòng cho ${targetList.length} phòng đã chọn?`,
      confirmText: 'Đồng ý', cancelText: 'Hủy'
    }).then(async (confirmed) => {
      if (!confirmed) return
      uiStore.showToast('Đang tiến hành tự động gán số phòng...', 'info')
      let successCount = 0
      let failCount = 0

      try {
        for (const r of targetList) {
          if (!r.bookingRoomId) continue
          try {
            const res = await autoAssignRoom(tab.dbId, r.bookingRoomId, {
              arrival_date: r.checkIn,
              departure_date: r.checkOut
            })
            if (res.data?.success) {
              r.roomNumber = res.data.room_number || ''
              r.isPreassigned = true
              successCount++
            } else {
              failCount++
            }
          } catch (err) {
            console.error(err)
            failCount++
          }
        }

        selectedRows.value = []

        if (successCount > 0) {
          uiStore.showToast(`Tự động gán thành công ${successCount} phòng!${failCount > 0 ? ` (Thất bại ${failCount} phòng)` : ''}`, 'success')
        } else {
          uiStore.showToast('Không tìm thấy số phòng trống phù hợp cho các phòng đã chọn!', 'error')
        }
      } catch(err) {
        console.error(err)
        uiStore.showToast('Có lỗi xảy ra khi tự động gán phòng!', 'error')
      }
    })
  } else if (actionName === 'Gỡ số phòng') {
    const tab = activeTab.value
    if (tab && tab.rooms) {
      const selected = tab.rooms.filter(r => selectedRows.value.includes(r.id))
      if (selected.length === 0) {
        uiStore.showToast('Vui lòng tích chọn phòng cần gỡ số phòng!', 'warning')
        return
      }
      const targetList = selected

      uiStore.confirm({
        title: 'Xác nhận gỡ số phòng',
        message: `Bạn có chắc chắn muốn gỡ số phòng cho ${targetList.length} phòng đã chọn?`,
        confirmText: 'Đồng ý', cancelText: 'Hủy'
      }).then(async (confirmed) => {
        if (confirmed) {
          if (tab.dbId) {
            uiStore.showToast('Đang tiến hành gỡ số phòng...', 'info')
            try {
              for (const r of targetList) {
                if (!r.bookingRoomId) continue
                await unassignRoom(tab.dbId, r.bookingRoomId)
                r.roomNumber = ''
                r.isPreassigned = false
              }
              uiStore.showToast('Đã gỡ số phòng thành công!', 'success')
            } catch(err) {
              console.error(err)
              uiStore.showToast('Lỗi khi gỡ số phòng trên hệ thống!', 'error')
            }
          } else {
            targetList.forEach(r => {
              r.roomNumber = ''
              r.isPreassigned = false
            })
            uiStore.showToast('Đã gỡ số phòng thành công!', 'success')
          }
          selectedRows.value = []
        }
      })
    }
  } else if (actionName === 'Hủy phòng') {
    const tab = activeTab.value
    if (!tab || !tab.dbId) {
      uiStore.showToast('Vui lòng lưu thông tin đăng ký trước khi hủy phòng!', 'warning')
      return
    }
    const selected = tab.rooms.filter(r => selectedRows.value.includes(r.id))
    if (selected.length === 0) {
      uiStore.showToast('Vui lòng tích chọn phòng cần hủy!', 'warning')
      return
    }
    const targetList = selected

    cancelPendingTarget.value = { type: 'rooms', tab, rooms: targetList }
    cancelModalTitle.value = `Hủy ${targetList.length} phòng đã chọn`
    cancelModalSubTitle.value = `Vui lòng chọn lý do hủy cho ${targetList.length} phòng đã chọn.`
    isCancelReasonModalOpen.value = true
  } else if (actionName === 'Dịch vụ bổ sung') {
    const tab = activeTab.value
    if (!tab || !tab.rooms || tab.rooms.length === 0) return
    const selected = tab.rooms.filter(r => selectedRows.value.includes(r.id))
    if (selected.length === 0) {
      uiStore.showToast('Vui lòng tích chọn phòng cần thêm dịch vụ bổ sung!', 'warning')
      return
    }
    openServicesModal(selected[0])
  } else if (actionName === 'Xóa dịch vụ bổ sung') {
    const tab = activeTab.value
    if (!tab || !tab.rooms || tab.rooms.length === 0) return
    const selected = tab.rooms.filter(r => selectedRows.value.includes(r.id))
    let validRooms = selected.filter(r => r.bookingRoomId)
    // Ngoại lệ: Nếu không tích chọn phòng trước, lấy toàn bộ phòng đã lưu trong booking
    if (validRooms.length === 0) {
      validRooms = tab.rooms.filter(r => r.bookingRoomId)
    }
    if (validRooms.length === 0) {
      uiStore.showToast('Không có phòng nào đã lưu để xóa dịch vụ bổ sung!', 'warning')
      return
    }
    deleteServiceModalRoom.value = validRooms[0]
    deleteServiceModalTargetRooms.value = validRooms
    isDeleteServiceModalOpen.value = true
  } else if (actionName === 'Khóa chuyển phòng') {
    const tab = activeTab.value
    if (!tab || !tab.dbId) {
      uiStore.showToast('Vui lòng lưu thông tin đăng ký trước khi khóa chuyển phòng!', 'warning')
      return
    }
    const selected = tab.rooms.filter(r => selectedRows.value.includes(r.id))
    const targetList = selected.length > 0 ? selected : []
    if (targetList.length === 0) {
      uiStore.showToast('Vui lòng tích chọn phòng muốn khóa chuyển phòng!', 'warning')
      return
    }

    if (targetList.some(r => !r.roomNumber)) {
      uiStore.showToast('Có phòng chưa được gán số phòng. Vui lòng gán số phòng trước khi khóa chuyển phòng!', 'warning')
      return
    }

    if (targetList.some(r => !r.bookingRoomId)) {
      uiStore.showToast('Có phòng chưa được lưu. Vui lòng lưu thông tin đăng ký trước!', 'warning')
      return
    }

    uiStore.confirm({
      title: 'Khóa chuyển phòng',
      message: `Bạn có chắc chắn muốn khóa chuyển phòng cho ${targetList.length} phòng đã chọn?`,
      confirmText: 'Đồng ý', cancelText: 'Hủy'
    }).then(async (confirmed) => {
      if (confirmed) {
        uiStore.showToast('Đang tiến hành khóa chuyển phòng...', 'info')
        let successCount = 0
        let failCount = 0
        let failMessages = []

        try {
          for (const r of targetList) {
            try {
              const res = await lockRoomMove(tab.dbId, r.bookingRoomId)
              if (res.data?.success) {
                successCount++
                r.isDoNotMove = true
              } else {
                failCount++
                failMessages.push(res.data?.message || `Phòng ${r.roomNumber} thất bại.`)
              }
            } catch (err) {
              console.error(err)
              failCount++
              failMessages.push(err.response?.data?.message || `Phòng ${r.roomNumber} thất bại.`)
            }
          }
          await loadBookings()
          selectedRows.value = []

          if (successCount > 0) {
            uiStore.showToast(`Khóa chuyển phòng thành công ${successCount} phòng!${failCount > 0 ? ` (Thất bại ${failCount} phòng: ${failMessages.join(', ')})` : ''}`, 'success')
          } else {
            uiStore.showToast(`Khóa chuyển phòng thất bại: ${failMessages.join(', ')}`, 'error')
          }
        } catch (err) {
          console.error(err)
          uiStore.showToast('Có lỗi xảy ra khi khóa chuyển phòng!', 'error')
        }
      }
    })
  } else if (actionName === 'Mở chuyển phòng') {
    const tab = activeTab.value
    if (!tab || !tab.dbId) {
      uiStore.showToast('Vui lòng lưu thông tin đăng ký trước khi mở khóa chuyển phòng!', 'warning')
      return
    }
    const selected = tab.rooms.filter(r => selectedRows.value.includes(r.id))
    const targetList = selected.length > 0 ? selected : []
    if (targetList.length === 0) {
      uiStore.showToast('Vui lòng tích chọn phòng muốn mở khóa chuyển phòng!', 'warning')
      return
    }

    if (targetList.some(r => !r.roomNumber)) {
      uiStore.showToast('Có phòng chưa được gán số phòng. Vui lòng gán số phòng trước khi mở khóa chuyển phòng!', 'warning')
      return
    }

    if (targetList.some(r => !r.bookingRoomId)) {
      uiStore.showToast('Có phòng chưa được lưu. Vui lòng lưu thông tin đăng ký trước!', 'warning')
      return
    }

    uiStore.confirm({
      title: 'Mở khóa chuyển phòng',
      message: `Bạn có chắc chắn muốn mở khóa chuyển phòng cho ${targetList.length} phòng đã chọn?`,
      confirmText: 'Đồng ý', cancelText: 'Hủy'
    }).then(async (confirmed) => {
      if (confirmed) {
        uiStore.showToast('Đang tiến hành mở khóa chuyển phòng...', 'info')
        let successCount = 0
        let failCount = 0
        let failMessages = []

        try {
          for (const r of targetList) {
            try {
              const res = await unlockRoomMove(tab.dbId, r.bookingRoomId)
              if (res.data?.success) {
                successCount++
                r.isDoNotMove = false
              } else {
                failCount++
                failMessages.push(res.data?.message || `Phòng ${r.roomNumber} thất bại.`)
              }
            } catch (err) {
              console.error(err)
              failCount++
              failMessages.push(err.response?.data?.message || `Phòng ${r.roomNumber} thất bại.`)
            }
          }
          await loadBookings()
          selectedRows.value = []

          if (successCount > 0) {
            uiStore.showToast(`Mở khóa chuyển phòng thành công ${successCount} phòng!${failCount > 0 ? ` (Thất bại ${failCount} phòng: ${failMessages.join(', ')})` : ''}`, 'success')
          } else {
            uiStore.showToast(`Mở khóa chuyển phòng thất bại: ${failMessages.join(', ')}`, 'error')
          }
        } catch (err) {
          console.error(err)
          uiStore.showToast('Có lỗi xảy ra khi mở khóa chuyển phòng!', 'error')
        }
      }
    })
  } else if (actionName === 'Xuất Excel') {
    uiStore.showToast('Đang tải xuống tệp Excel danh sách phòng...', 'success')
  } else if (actionName === 'In phiếu đăng ký khách') {
    const tab = activeTab.value
    const selected = tab?.rooms?.filter(r => selectedRows.value.includes(r.id)) || []
    if (selected.length === 0) {
      uiStore.showToast('Vui lòng tích chọn phòng cần in phiếu đăng ký!', 'warning')
      return
    }
    uiStore.confirm({
      title: 'Xác nhận in phiếu đăng ký',
      message: `Bạn có chắc chắn muốn in phiếu đăng ký khách cho ${selected.length} phòng đã chọn?`,
      confirmText: 'In phiếu', cancelText: 'Hủy'
    }).then((confirmed) => {
      if (confirmed) {
        uiStore.showToast('Đang in phiếu đăng ký khách (Registration Card)...', 'success')
        setTimeout(() => window.print(), 500)
      }
    })
  } else if (actionName === 'In hoá đơn tạm') {
    const tab = activeTab.value
    const selected = tab?.rooms?.filter(r => selectedRows.value.includes(r.id)) || []
    if (selected.length === 0) {
      uiStore.showToast('Vui lòng tích chọn phòng cần in hóa đơn tạm!', 'warning')
      return
    }
    uiStore.confirm({
      title: 'Xác nhận in hóa đơn tạm',
      message: `Bạn có chắc chắn muốn in hóa đơn tạm tính cho ${selected.length} phòng đã chọn?`,
      confirmText: 'In hóa đơn', cancelText: 'Hủy'
    }).then((confirmed) => {
      if (confirmed) {
        uiStore.showToast('Đang in hóa đơn tạm tính (Pro-forma Invoice)...', 'success')
        setTimeout(() => window.print(), 500)
      }
    })
  } else if (actionName === 'Xóa') {
    const tab = activeTab.value
    if (!tab) return
    if (!tab.dbId) {
      const idx = tabs.value.findIndex(t => t.id === activeTabId.value)
      if (idx !== -1) tabs.value.splice(idx, 1)
      if (tabs.value.length > 0) activeTabId.value = tabs.value[tabs.value.length - 1].id
      else {
        tabs.value = []
        activeTabId.value = null
      }
      return
    }
    cancelPendingTarget.value = { type: 'booking', tab }
    cancelModalTitle.value = `Hủy toàn bộ đăng ký ${tab.id}`
    cancelModalSubTitle.value = `Bạn có chắc chắn muốn hủy toàn bộ đăng ký ${tab.id}? Thao tác này sẽ ghi log hủy cho toàn bộ phòng.`
    isCancelReasonModalOpen.value = true
  } else {
    uiStore.showToast(`Tính năng "${actionName}" đang được thực hiện!`, 'info')
  }
}

// ==================== HỦY ĐĂNG KÝ / HỦY PHÒNG REASON MODAL ====================
const isCancelReasonModalOpen = ref(false)
const cancelModalTitle = ref('Xác nhận hủy đặt phòng')
const cancelModalSubTitle = ref('')
const cancelPendingTarget = ref(null)

async function handleConfirmCancelReason(payload) {
  const target = cancelPendingTarget.value
  if (!target) return

  if (target.type === 'booking') {
    const tab = target.tab
    try {
      uiStore.showToast('Đang tiến hành hủy đăng ký...', 'info')
      const res = await deleteBooking(tab.dbId, {
        cancel_reason_id: payload.cancel_reason_id,
        note: payload.note
      })
      if (res.data?.success) {
        const idx = tabs.value.findIndex(t => t.id === activeTabId.value)
        if (idx !== -1) tabs.value.splice(idx, 1)
        if (tabs.value.length > 0) activeTabId.value = tabs.value[tabs.value.length - 1].id
        else {
          tabs.value = []
          activeTabId.value = null
        }
        uiStore.showToast('Đã hủy và xóa đăng ký thành công!', 'success')
      } else {
        uiStore.showToast(res.data?.message || 'Không thể xóa đăng ký!', 'error')
      }
    } catch (err) {
      console.error(err)
      uiStore.showToast(err.response?.data?.message || 'Không thể xóa đăng ký!', 'error')
    }
  } else if (target.type === 'rooms') {
    const { tab, rooms } = target
    let successCount = 0
    let failCount = 0
    let failMessages = []

    uiStore.showToast('Đang tiến hành hủy phòng...', 'info')
    try {
      for (const r of rooms) {
        if (!r.bookingRoomId) continue
        try {
          const res = await cancelBookingRoom(tab.dbId, r.bookingRoomId, {
            cancel_reason_id: payload.cancel_reason_id,
            note: payload.note
          })
          if (res.data?.success) {
            successCount++
          } else {
            failCount++
            failMessages.push(res.data?.message || `Phòng ${r.roomNumber || r.type} thất bại.`)
          }
        } catch (err) {
          console.error(err)
          failCount++
          failMessages.push(err.response?.data?.message || `Phòng ${r.roomNumber || r.type} thất bại.`)
        }
      }

      await loadBookings()
      selectedRows.value = []

      if (successCount > 0) {
        uiStore.showToast(`Đã hủy thành công ${successCount} phòng!${failCount > 0 ? ` (Thất bại ${failCount} phòng: ${failMessages.join(', ')})` : ''}`, 'success')
      } else {
        uiStore.showToast(`Hủy phòng thất bại: ${failMessages.join(', ')}`, 'error')
      }
    } catch (err) {
      console.error(err)
      uiStore.showToast('Có lỗi xảy ra khi hủy phòng!', 'error')
    }
  }
}

// ==================== NHÂN BẢN BOOKING MODAL ====================
async function openCopyModal() {
  const tab = activeTab.value
  if (!tab || !tab.dbId) {
    uiStore.showToast('Vui lòng lưu thông tin đăng ký trước khi nhân bản!', 'warning')
    return
  }
  
  let defaultArrival = tab.checkIn
  let defaultDeparture = tab.checkOut
  
  if (defaultArrival && defaultArrival.includes('/')) {
    defaultArrival = parseDateVi(defaultArrival)
  }
  if (defaultDeparture && defaultDeparture.includes('/')) {
    defaultDeparture = parseDateVi(defaultDeparture)
  }
  
  const sysDateStr = systemDate.value || new Date().toISOString().split('T')[0]
  if (defaultArrival < sysDateStr) {
    defaultArrival = sysDateStr
    const nights = tab.nights || 1
    const depDate = new Date(sysDateStr)
    depDate.setDate(depDate.getDate() + nights)
    defaultDeparture = depDate.toISOString().split('T')[0]
  }
  
  copyModalArrivalDate.value = defaultArrival
  copyModalDepartureDate.value = defaultDeparture
  isCopyModalOpen.value = true
}

function handleCopied(newBooking) {
  if (newBooking) {
    const newTab = bookingToTab(newBooking)
    // Xóa khỏi closed list nếu trước đây đã bị đóng
    removeClosedTabId(newBooking.id)
    tabs.value.push(newTab)
    activeTabId.value = newTab.id
  }
  loadBookings()
}

// ==================== NÂNG HẠNG PHÒNG MODAL ====================
function openUpgradeModal() {
  const tab = activeTab.value
  if (!tab || !tab.dbId) {
    uiStore.showToast('Vui lòng lưu thông tin đăng ký trước khi nâng hạng phòng!', 'warning')
    return
  }
  if (!tab.rooms || tab.rooms.length === 0) {
    uiStore.showToast('Không có phòng nào để nâng hạng!', 'info')
    return
  }

  const selected = tab.rooms.filter(r => selectedRows.value.includes(r.id))
  if (selected.length === 0) {
    uiStore.showToast('Vui lòng tích chọn phòng muốn nâng hạng!', 'warning')
    return
  }

  isUpgradeModalOpen.value = true
}

function handleUpgraded(payload) {
  loadBookings()
  selectedRows.value = []
  if (payload) {
    const { successCount, failCount, failMessages } = payload
    if (successCount > 0) {
      uiStore.showToast(`Nâng hạng phòng thành công cho ${successCount} phòng!${failCount > 0 ? ` (Thất bại ${failCount} phòng: ${failMessages.join(', ')})` : ''}`, 'success')
    }
  } else {
    uiStore.showToast('Nâng hạng phòng thành công!', 'success')
  }
}

// ==================== XÓA DỊCH VỤ BỔ SUNG MODAL ====================
const isDeleteServiceModalOpen = ref(false)
const deleteServiceModalRoom = ref(null)
const deleteServiceModalTargetRooms = ref([])

function handleServiceDeleted() {
  expandedRooms.value = []
  loadBookings()
  selectedRows.value = []
}

// ==================== THÊM GIƯỜNG MODAL ====================
const isExtraBedModalOpen = ref(false)
const extraBedModalRoom = ref(null)

function openExtraBedModal(room) {
  if (!room.extraBedPrice || Number(room.extraBedPrice) === 0) {
    const rc = roomClasses.value.find(c => c.id === room.roomClassId)
    room.extraBedPrice = rc?.extra_bed_price !== undefined ? Number(rc.extra_bed_price) : 300000
  }
  extraBedModalRoom.value = room
  isExtraBedModalOpen.value = true
}

async function handleExtraBedSaved({ quantity, rate, totalExtraBedPrice, dailyRates }) {
  if (!extraBedModalRoom.value) return
  const room = extraBedModalRoom.value
  room.extraBedQty = quantity
  room.extraBedPrice = rate
  if (totalExtraBedPrice !== undefined) {
    room.extraBedTotalSum = totalExtraBedPrice
  }
  if (dailyRates) {
    room.dailyExtraBeds = dailyRates
  }
  room.total = calculateRoomTotal(room)

  // Nếu là booking đã lưu dưới database (có bookingRoomId), tự động đồng bộ API Dịch vụ EB & DB booking_rooms
  if (activeTab.value?.dbId && room.bookingRoomId) {
    try {
      uiStore.showToast('Đang lưu thông tin Thêm giường...', 'info')

      // 1. Cập nhật trực tiếp số lượng và đơn giá extra bed vào bảng booking_rooms
      await http.put(`/bookings/${activeTab.value.dbId}/rooms/${room.bookingRoomId}`, {
        extra_bed_qty: quantity,
        extra_bed_rate: rate
      })

      // 2. Cập nhật chi tiết từng đêm vào dịch vụ booking_room_services
      if (dailyRates && dailyRates.length > 0) {
        for (const d of dailyRates) {
          if (d.isPast) continue // Không lưu/sửa đêm quá khứ
          if (d.quantity > 0) {
            await http.post(`/booking-rooms/${room.bookingRoomId}/services`, {
              service_code: 'EB',
              service_name: 'Extra Bed',
              service_date: d.dateStr,
              quantity: d.quantity,
              rate: d.rate,
              is_room: d.isRoom ? 1 : 0
            })
          }
        }
      }

      await loadBookings()
      uiStore.showToast('Cập nhật Thêm giường vào Database thành công!', 'success')
    } catch (err) {
      console.error('Lỗi khi lưu Extra Bed:', err)
      const detailMsg = err.response?.data?.message || err.message || ''
      uiStore.showToast(`Lỗi khi lưu Thêm giường: ${detailMsg}`, 'error')
    }
  }
}

// ==================== YÊU CẦU ĐẶC BIỆT MODAL ====================
const isSpecialRequestsModalOpen = ref(false)
const specialRequestsModalRoom = ref(null)

function openSpecialRequestsModal(room) {
  specialRequestsModalRoom.value = room
  isSpecialRequestsModalOpen.value = true
}

function handleSpecialRequestsSaved(updatedRequests) {
  if (!specialRequestsModalRoom.value) return
  specialRequestsModalRoom.value.specialRequests = updatedRequests
}

function getRoomSpecialRequestsText(room) {
  if (!room.specialRequests || room.specialRequests.length === 0) return 'Yêu cầu đặc biệt'
  return room.specialRequests
    .map(r => r.special_request?.name || r.specialRequest?.name || r.name || 'Yêu cầu')
    .join(', ')
}

// ==================== DỊCH VỤ BỔ SUNG MODAL ====================
const isServicesModalOpen = ref(false)
const servicesModalRoom = ref(null)
const servicesTargetRooms = computed(() => {
  const tab = activeTab.value
  if (!tab || !tab.rooms) return []
  const selected = tab.rooms.filter(r => selectedRows.value.includes(r.id))
  return selected.length > 0 ? selected : (servicesModalRoom.value ? [servicesModalRoom.value] : [])
})

function openServicesModal(room) {
  if (!room || !room.bookingRoomId) {
    uiStore.showToast('Vui lòng lưu thông tin đăng ký trước khi thêm dịch vụ bổ sung!', 'warning')
    return
  }
  servicesModalRoom.value = room
  isServicesModalOpen.value = true
}

async function handleServicesSaved() {
  expandedRooms.value = [] // Force re-fetch on next expand
  if (isEditing.value || isModalOpen.value) {
    const roomId = servicesModalRoom.value?.bookingRoomId
    if (roomId && servicesModalRoom.value) {
      try {
        const res = await fetchBookingRoomServices(roomId)
        servicesModalRoom.value.services = res.data?.data || []
        servicesModalRoom.value.total = calculateRoomTotal(servicesModalRoom.value)
      } catch (err) {
        console.error('Lỗi khi tải lại dịch vụ phòng:', err)
      }
    }
  } else {
    await loadBookings()
  }
  selectedRows.value = []
}

async function openBookingModalByCode(bookingCode) {
  if (!bookingCode) return
  let foundTab = tabs.value.find(t => String(t.id) === String(bookingCode) || String(t.dbId) === String(bookingCode))
  
  if (foundTab) {
    removeClosedTabId(foundTab.dbId)
    activeTabId.value = foundTab.id
  } else {
    try {
      const res = await fetchBookings({ search: bookingCode })
      const list = res.data?.data || res.data || []
      if (list.length > 0) {
        const bItem = list.find(b => String(b.booking_code) === String(bookingCode) || String(b.id) === String(bookingCode)) || list[0]
        removeClosedTabId(bItem.id)
        const tabObj = bookingToTab(bItem)
        const existingIdx = tabs.value.findIndex(t => t.id === tabObj.id)
        if (existingIdx > -1) {
          tabs.value[existingIdx] = tabObj
        } else {
          tabs.value.push(tabObj)
        }
        activeTabId.value = tabObj.id
      }
    } catch (err) {
      console.error('Lỗi khi mở booking theo mã:', err)
    }
  }
}

defineExpose({
  openBookingModalByCode,
  handleAddTabClick
})
</script>

<template>
  <div class="h-full flex flex-col bg-slate-50 text-slate-800 animate-in select-none">
    
    <!-- DIAGNOSTIC WARNINGS BANNER -->
    <div v-if="diagnosticErrors.length" class="bg-rose-600 text-white text-xs px-4 py-2 font-bold flex flex-col gap-1 z-[99999] shrink-0 border-b border-rose-700 shadow-sm">
      <div class="flex items-center gap-1.5 font-black text-sm">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        Cảnh báo hệ thống: Lỗi tải dữ liệu từ server
      </div>
      <ul class="list-disc list-inside mt-1 font-semibold pl-1 text-[11px] opacity-95">
        <li v-for="err in diagnosticErrors" :key="err" class="mt-0.5">{{ err }}</li>
      </ul>
    </div>
    
    <!-- DYNAMIC TABS HEADER BAR (Redesigned Top Bar) -->
    <div class="topbar shrink-0 flex items-center justify-between gap-3">
      <!-- Tabs list -->
      <div class="flex items-center gap-1.5 overflow-x-auto flex-1 min-w-0 scrollbar-none" @wheel.passive="handleTabsWheel">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          @click="handleTabClick(tab.id)"
          class="booking-tab cursor-pointer whitespace-nowrap shrink-0"
          :style="tab.id === activeTabId ? 'background: var(--navy-3); border: 1px solid rgba(255,255,255,0.15)' : 'background: rgba(255,255,255,0.06); color: #c7d2e0'"
        >
          <span>{{ tab.title }}</span>
          <span
            v-if="tabs.length > 1"
            @click.stop="handleCloseTab(tab.id, $event)"
            class="x hover:text-white ml-1.5 transition-colors font-bold"
          >
            ✕
          </span>
        </button>

        <!-- ADD TAB BUTTON -->
        <button
          @click="handleAddTabClick"
          class="add-booking-btn shrink-0"
          title="Tạo đăng ký mới"
        >
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg>
          <span>Booking mới</span>
        </button>
      </div>

      <!-- Action Buttons -->
      <div v-if="activeTab" class="flex items-center gap-2 shrink-0 ml-auto">
        <button class="btn" @click="triggerAction('Thông tin đăng ký')">
          <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/></svg>
          Thông tin đăng ký
        </button>
        
        <button v-if="!isEditing" class="btn" @click="triggerAction('Sửa')">
          <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4z"/></svg>
          Sửa
        </button>
        <button v-else class="btn" @click="triggerAction('Quay lại')">
          <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"/></svg>
          Quay lại
        </button>

        <button 
          class="btn blue" 
          @click="triggerAction('Lưu')"
          :disabled="!isEditing"
          :class="{ 'opacity-50 cursor-not-allowed': !isEditing }"
        >
          <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><path d="M17 21v-8H7v8M7 3v5h8"/></svg>
          Lưu
        </button>

        <button class="btn" @click="triggerAction('Nhân bản')">
          <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
          Nhân bản
        </button>

        <button class="btn red" @click="triggerAction('Xóa')">
          <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2m3 0l-1 14a2 2 0 01-2 2H7a2 2 0 01-2-2L4 6"/></svg>
          Xoá
        </button>

        <button class="btn" @click="triggerAction('In')">
          <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9V2h12v7"/><rect x="6" y="14" width="12" height="8"/><path d="M6 14H4a2 2 0 01-2-2v-3a2 2 0 012-2h16a2 2 0 012 2v3a2 2 0 01-2 2h-2"/></svg>
          In
        </button>

        <button class="btn" @click="triggerAction('Thông báo')">
          <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8a6 6 0 00-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 01-3.4 0"/></svg>
          Thông báo
        </button>
      </div>

      <div class="topbar-divider"></div>

      <button class="global-search-btn" @click="openGlobalSearch" title="Tìm kiếm toàn hệ thống">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4-4"/></svg>
        <span>Tìm kiếm toàn hệ thống</span>
      </button>

      <!-- Hotel Service Filter + Column Selector in Top Bar -->
      <div v-if="activeTab" class="flex items-center gap-1.5 ml-2.5 text-white/90 text-xs font-bold shrink-0">

        <!-- Column Selector in Top Bar -->
        <div class="relative inline-block text-left">
          <button 
            @click="showTableColumnSelector = !showTableColumnSelector" 
            class="flex items-center justify-center p-1.5 rounded hover:bg-white/10 transition-colors h-7 w-7 text-white/70 hover:text-white" 
            title="Tùy chọn cột hiển thị"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
            </svg>
          </button>
          
          <div 
            v-if="showTableColumnSelector" 
            class="fixed inset-0 z-45 cursor-default" 
            @click="showTableColumnSelector = false"
          ></div>
          
          <div 
            v-if="showTableColumnSelector" 
            class="absolute right-0 mt-1.5 w-60 bg-white border border-slate-200 rounded-lg shadow-xl z-50 py-1.5 max-h-80 overflow-y-auto"
          >
            <div class="text-[11px] font-bold text-slate-500 mb-1 px-3 border-b pb-1 select-none">ẨN/HIỆN CỘT BẢNG</div>
            <div 
              v-for="col in columns" 
              :key="col.key" 
              class="px-3.5 py-1.5 hover:bg-slate-50 flex items-center gap-2 text-xs font-semibold text-slate-700 select-none cursor-grab active:cursor-grabbing"
              draggable="true"
              @dragstart="handleDragStart(col.key)"
              @dragover.prevent
              @drop="handleDrop(col.key)"
            >
              <span class="text-slate-400 font-bold cursor-grab active:cursor-grabbing text-xs leading-none">⋮⋮</span>
              <input 
                type="checkbox" 
                v-model="col.visible" 
                class="rounded text-[#006bdb] focus:ring-[#006bdb] w-3.5 h-3.5 cursor-pointer"
              />
              <span class="flex-1 cursor-pointer" @click="col.visible = !col.visible">{{ col.label }}</span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- EMPTY STATE: Không còn tab nào -->
    <div v-if="tabs.length === 0 && !isLoading" class="flex-1 flex flex-col items-center justify-center gap-4" style="background: var(--bg-page, #f0f4f8);">
      <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.2">
        <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/>
        <rect x="9" y="3" width="6" height="4" rx="1"/>
        <path d="M9 12h6M9 16h4"/>
      </svg>
      <div class="text-slate-400 text-sm font-medium">Chưa có đăng ký nào đang mở</div>
      <button
        @click="handleAddTabClick"
        class="flex items-center gap-2 px-5 py-2.5 rounded-lg text-sm font-semibold text-white transition-all cursor-pointer hover:brightness-110 hover:shadow-lg hover:-translate-y-0.5 active:translate-y-0 active:scale-95 duration-200"
        style="background: linear-gradient(135deg, #006bdb, #0050a8); box-shadow: 0 2px 8px rgba(0,107,219,0.3);"
      >
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 5v14M5 12h14"/></svg>
        Tạo đăng ký mới
      </button>
    </div>

    <!-- MAIN TAB CONTENT PANEL -->
    <div v-if="activeTab" class="flex-1 flex flex-col overflow-hidden">
      
      <!-- SUMMARY BAR Redesigned -->
      <div 
        class="summary-bar shrink-0 cursor-pointer" 
        @click="isEditing ? null : openEditModal"
        :title="isEditing ? '' : `Chi tiết phiếu đăng ký: ${activeTab.bookingName || 'Trống'}\nTrạng thái: ${activeTabStatusName || 'Trống'}\nNgày đến/đi: ${formatDateVi(activeTab.checkIn)} ~ ${formatDateVi(activeTab.checkOut)}\nĐặt cọc: ${(activeTab.deposit || 0).toLocaleString('en-US')} VND\nCông ty: ${activeTab.company || '---'}\nXác nhận: ${formatDateVi(activeTab.confirmDate) || '---'}\n\n-> Click để mở chi tiết thông tin đăng ký (Edit Modal)`"
      >
        <div>
          <span class="label">Tên đăng ký:</span>
          <input 
            v-if="isEditing" 
            type="text" 
            v-model="activeTab.bookingName" 
            class="border border-slate-300 rounded px-2 py-0.5 text-xs w-48 font-semibold text-slate-800 focus:outline-none focus:border-blue-500 uppercase" 
            @click.stop
          />
          <b v-else class="uppercase font-black text-slate-800">{{ activeTab.bookingName || 'Trống' }}</b>
        </div>
        <div><span class="label">Trạng thái:</span><span class="status-pill select-none">{{ activeTabStatusName || 'Trống' }}</span></div>
        <div>
          <span class="label">Ngày đến/đi:</span>
          <div v-if="isEditing" class="flex items-center space-x-1" @click.stop>
            <input 
              type="date" 
              v-model="activeTab.checkIn" 
              @change="handleMainDateChange" 
              class="border border-slate-300 rounded px-1.5 py-0.5 text-xs font-semibold text-slate-800 focus:outline-none" 
            />
            <span>~</span>
            <input 
              type="date" 
              v-model="activeTab.checkOut" 
              @change="handleMainDateChange" 
              class="border border-slate-300 rounded px-1.5 py-0.5 text-xs font-semibold text-slate-800 focus:outline-none" 
            />
          </div>
          <b v-else class="font-black text-slate-800">{{ formatDateVi(activeTab.checkIn) }} ~ {{ formatDateVi(activeTab.checkOut) }}</b>
        </div>
        <div v-if="isEditing" class="flex items-center space-x-1" @click.stop>
          <span class="label">Số đêm:</span>
          <input 
            type="number" 
            v-model.number="activeTab.nights" 
            @input="handleMainNightsChange" 
            min="1" 
            class="border border-slate-300 rounded px-1 py-0.5 text-xs font-semibold text-slate-800 focus:outline-none w-12 text-center" 
          />
        </div>
        <div><span class="label">Đặt cọc:</span><b class="font-black text-slate-800">{{ (activeTab.deposit || 0).toLocaleString('en-US') }}</b></div>
        <div><span class="label">Công ty:</span><b class="font-black text-[#0f7d8c]">{{ activeTab.company || '---' }}</b></div>
        <div><span class="label">Xác nhận:</span><b class="font-bold text-slate-500">{{ formatDateVi(activeTab.confirmDate) || '---' }}</b></div>
        
        <div class="flex items-center gap-2" style="margin-left:auto;">
          <button class="chip-plain primary font-black" style="border-radius: 20px; padding: 7px 16px; background: var(--teal) !important; color: #fff !important;" @click.stop="triggerAction('Hóa đơn')">Hoá đơn</button>
        </div>
      </div>



      <!-- MAIN LAYOUT -->
      <div class="main-layout flex-1 flex min-h-0 relative bg-[#eef1f5]">
        <!-- COLUMN WRAPPER FOR TABLE AND FOOTER (Aligns with tableWrap ref) -->
        <div class="flex-1 flex flex-col min-w-0 min-h-0 relative">
          <!-- ROOMS DATA TABLE LIST -->
          <div class="table-wrap flex-1 min-h-[300px]" id="tableWrap" ref="tableWrapRef" @scroll="handleTableScroll">
          <table :style="{ width: tableWidth }" class="text-left border-collapse text-xs table-auto">
            <colgroup>
              <col style="width: 30px" />
              <col style="width: 30px" />
              <col style="width: 30px" />
              <col style="width: 30px" />
              <col style="width: 40px" />
              <col v-for="col in columns.filter(c => c.visible)" :key="col.key" :style="{ width: getColWidthPx(col) }" />
              <col style="width: 120px" />
            </colgroup>
            <thead>
              <tr class="bg-slate-50 border-b border-slate-200 text-gray-900 font-bold select-none whitespace-nowrap h-9 text-xs">
                <th class="p-0 border-r border-slate-200 text-center" colspan="4">
                  <div class="flex items-center justify-start h-full">
                    <div class="w-[30px] shrink-0"></div>
                    <div class="w-[30px] shrink-0 flex items-center justify-center">
                      <input type="checkbox" @change="handleRowSelectAll" :checked="selectedRows.length === activeTab.rooms.length && activeTab.rooms.length > 0" />
                    </div>
                  </div>
                </th>
                <th class="p-2 border-r border-slate-200 text-center w-[40px]">STT</th>
                <th v-for="col in columns.filter(c => c.visible)" 
                    :key="col.key"
                    draggable="true"
                    @dragstart="handleDragStart(col.key)"
                    @dragover.prevent
                    @drop="handleDrop(col.key)"
                    class="p-1 border-r border-slate-200 cursor-move select-none hover:bg-slate-100 transition-colors group/hdr"
                    :class="[col.width, col.center ? 'text-center' : '', col.right ? 'text-right' : '']"
                >
                  <div class="flex items-center justify-center gap-0.5 w-full relative">
                    <span class="text-[10px] uppercase font-bold text-slate-700 tracking-wider text-center leading-tight whitespace-normal break-words">{{ col.label }}</span>
                    <span class="text-slate-300 text-[8px] font-black cursor-grab select-none shrink-0 opacity-40 group-hover/hdr:opacity-100 transition-opacity">⋮</span>
                  </div>
                </th>
                <th class="p-2 text-right w-[120px] bg-slate-100 text-slate-700 font-extrabold sticky-shadow-left z-20">Tổng cộng</th>
              </tr>
            </thead>
            <tbody class="font-semibold text-gray-900 select-text">
              <!-- Nested Rows of Rooms -->
              <template v-if="true">
                <!-- ===== MODE A: No checked-in rooms → flat group by room type ===== -->
                <template v-if="!hasStatusGroups">
                  <template v-for="group in groupedRooms" :key="group.typeName">
                    <!-- Type Group Header Row -->
                    <tr 
                      class="bg-slate-50/70 border-b border-slate-200 font-bold h-8 text-gray-900 cursor-pointer select-none"
                      @click="toggleGroupCollapse(group.typeName)"
                    >
                      <td class="p-2 border-r border-slate-200 text-center bg-slate-100/10"></td>
                      <td class="p-2 border-r border-slate-200 text-center" @click.stop>
                        <button 
                          @click="toggleGroupCollapse(group.typeName)" 
                          class="w-5 h-5 flex items-center justify-center rounded bg-[#8cc3f3] hover:bg-[#6baae6] text-white font-bold select-none cursor-pointer border-none"
                          style="font-size: 13px; line-height: 1;"
                        >
                          {{ collapsedSections[group.typeName] ? '+' : '−' }}
                        </button>
                      </td>
                      <td class="p-2 border-r border-slate-200 text-center" @click.stop>
                        <input 
                          type="checkbox" 
                          :checked="group.rooms.length > 0 && group.rooms.every(r => selectedRows.includes(r.id))" 
                          @change="e => handleSelectAllInGroup(group.rooms, e.target.checked)" 
                        />
                      </td>
                      <td :colspan="columns.filter(c => c.visible).length + 2" class="p-2 text-gray-900 font-bold text-xs uppercase tracking-wider pl-4">
                        {{ group.typeName }} ({{ group.rooms.length }})
                      </td>
                      <td class="bg-[#e2e8f0] sticky-shadow-left z-10"></td>
                    </tr>

                  <!-- Rooms in Group -->
                  <template v-if="!collapsedSections[group.typeName]">
                    <template v-for="(room, idx) in group.rooms" :key="room.id">
                      <tr 
                        class="border-b border-slate-200 hover:bg-sky-50/30 transition-colors h-9 group cursor-pointer"
                        :class="{ 'bg-sky-50/60 ring-1 ring-inset ring-sky-200': selectedRows.includes(room.id) }"
                        @click="handleRowSelect(room.id)"
                        :title="`Phòng: ${room.roomNumber || '(chưa gán)'} | Khách: ${room.guestName || ''} | CI: ${room.checkIn} → CO: ${room.checkOut} | ${room.nights} đêm | ${(Number(room.total)||0).toLocaleString('en-US')}đ`"
                      >
                        <td class="p-2 border-r border-slate-200 text-center bg-slate-100/10"></td>
                        <td class="p-2 border-r border-slate-200 text-center bg-slate-100/10"></td>
                        <td class="p-2 border-r border-slate-200 text-center" @click.stop>
                          <button 
                            @click="toggleRoomExpand(room)"
                            class="w-5 h-5 flex items-center justify-center rounded transition-colors text-white font-bold select-none cursor-pointer"
                            :class="expandedRooms.includes(room.id) ? 'bg-[#ff9e3b] hover:bg-[#e08422]' : 'bg-[#8cc3f3] hover:bg-[#6baae6]'"
                            style="font-size: 13px; line-height: 1;"
                          >
                            {{ expandedRooms.includes(room.id) ? '−' : '+' }}
                          </button>
                        </td>
                        <td class="p-2 border-r border-slate-200 text-center" @click.stop>
                          <input type="checkbox" :checked="selectedRows.includes(room.id)" @change="handleRowSelect(room.id)" />
                        </td>
                        <td class="p-2 border-r border-slate-200 text-center text-gray-500 font-semibold bg-slate-50/30">{{ idx + 1 }}</td>
                        <td v-for="col in columns.filter(c => c.visible)" 
                            :key="col.key" 
                            class="p-2 border-r border-slate-200" 
                            :class="[
                              col.center ? 'text-center' : '', 
                              col.right ? 'text-right' : '', 
                              col.key === 'adjustment' ? 'overflow-visible' : 'overflow-hidden'
                            ]"
                        >
                        <template v-if="col.key === 'type'">
                          <select 
                            v-if="isEditing" 
                            v-model="room.roomClassId" 
                            @focus="room._oldClassId = room.roomClassId"
                            @change="handleRoomClassChange(room, room._oldClassId)"
                            class="bg-white border border-slate-300 rounded px-1 py-0.5 text-[11px] w-full font-semibold focus:outline-none cursor-pointer"
                          >
                            <option v-for="rc in roomClasses" :key="rc.id" :value="rc.id">{{ rc.name }}</option>
                          </select>
                          <span v-else class="text-gray-900 font-semibold truncate block w-full" :title="room.type">{{ room.type }}</span>
                        </template>
                        <template v-else-if="col.key === 'shape'">
                          <select 
                            v-if="isEditing" 
                            :value="getRoomFormId(room)" 
                            @change="handleRoomFormChange(room, $event.target.value)" 
                            class="bg-white border border-slate-300 rounded px-1 py-0.5 text-[11px] w-full font-semibold focus:outline-none cursor-pointer text-center"
                          >
                            <option v-for="rf in getAvailableFormsForRoom(room)" :key="rf.id" :value="rf.id">{{ rf.name }}</option>
                          </select>
                          <span v-else class="text-gray-900 font-semibold">{{ room.shape }}</span>
                        </template>
                        <template v-else-if="col.key === 'roomNumber'">
                          <div v-if="room.isDoNotMove" class="flex items-center justify-center gap-1.5 text-[11px] font-bold text-gray-700 bg-slate-100 border border-slate-200 rounded px-1.5 py-0.5 max-w-[95px] mx-auto select-none" title="Khóa chuyển phòng (Do Not Move)">
                            <span>{{ room.roomNumber || '-' }}</span>
                            <svg class="w-3 h-3 text-red-500 fill-current" viewBox="0 0 24 24">
                              <rect x="3" y="11" width="18" height="11" rx="2" ry="2" fill="none" stroke="currentColor" stroke-width="2.5"></rect>
                              <path d="M7 11V7a5 5 0 0 1 10 0v4" fill="none" stroke="currentColor" stroke-width="2.5"></path>
                            </svg>
                          </div>
                          <select 
                            v-else-if="isEditing" 
                            v-model="room.roomNumber" 
                            @focus="loadVacantRoomsForRoom(room)"
                            class="bg-white border border-slate-300 rounded px-1 py-0.5 text-[11px] w-full font-semibold focus:outline-none text-center cursor-pointer"
                          >
                            <option value="">Chọn phòng</option>
                            <option v-if="room.roomNumber" :value="room.roomNumber">{{ room.roomNumber }}</option>
                            <option 
                              v-for="vRoom in getVacantRoomsList(room)" 
                              :key="vRoom.room_number" 
                              :value="vRoom.room_number"
                            >
                              {{ vRoom.room_number }} ({{ vRoom.status }})
                            </option>
                          </select>
                          <span v-else>{{ room.roomNumber || '-' }}</span>
                        </template>
                        <template v-else-if="col.key === 'checkIn'">
                          <input 
                            v-if="isEditing" 
                            type="date" 
                            v-model="room.checkIn" 
                            @change="handleRowDateChangeInline(room)"
                            @click="$event.target.showPicker && $event.target.showPicker()"
                            class="date-span-input border border-slate-300 rounded px-1 py-0.5 text-[11px] font-semibold text-slate-800 bg-white shadow-sm text-center focus:outline-none inline-block mx-auto" 
                          />
                          <span v-else class="text-gray-500 font-semibold">{{ formatDateVi(room.checkIn) }}</span>
                        </template>
                        <template v-else-if="col.key === 'checkOut'">
                          <input 
                            v-if="isEditing" 
                            type="date" 
                            v-model="room.checkOut" 
                            @change="handleRowDateChangeInline(room)"
                            @click="$event.target.showPicker && $event.target.showPicker()"
                            class="date-span-input border border-slate-300 rounded px-1 py-0.5 text-[11px] font-semibold text-slate-800 bg-white shadow-sm text-center focus:outline-none inline-block mx-auto" 
                          />
                          <span v-else class="text-gray-500 font-semibold">{{ formatDateVi(room.checkOut) }}</span>
                        </template>
                        <template v-else-if="col.key === 'nights'">
                          <input 
                            v-if="isEditing" 
                            type="number" 
                            v-model.number="room.nights" 
                            min="1"
                            @input="handleRowNightsChangeInline(room)"
                            @click.stop
                            class="border border-slate-300 rounded px-1 py-0.5 text-[11px] w-[50px] font-semibold focus:outline-none text-center bg-white mx-auto block" 
                          />
                          <span v-else class="text-gray-900 font-semibold">{{ room.nights }}</span>
                        </template>
                        <template v-else-if="col.key === 'price'">
                          <input 
                            v-if="isEditing" 
                            type="text" 
                            :value="formatCurrencyInput(room.price)" 
                            :disabled="isRoomPriceDisabled(room)"
                            @input="e => { room.price = cleanCurrencyValue(e.target.value); room.total = calculateRoomTotal(room) }"
                            @focus="e => { if (cleanCurrencyValue(e.target.value) === 0) e.target.value = ''; e.target.select() }"
                            class="border rounded px-1.5 py-0.5 text-xs w-full font-semibold focus:outline-none text-right" 
                            :class="isRoomPriceDisabled(room) ? 'bg-slate-100 cursor-not-allowed text-slate-400 border-slate-200' : 'bg-white border-slate-300 text-slate-900'"
                          />
                          <span v-else>{{ formatCurrencyInput(room.price) }}</span>
                        </template>
                        <template v-else-if="col.key === 'rateCode'">
                          <select 
                            v-if="isEditing" 
                            v-model="room.rateCode" 
                            @change="handleRoomRateCodeChange(room)"
                            class="bg-white border border-slate-300 rounded px-1.5 py-0.5 text-[11px] w-full font-semibold focus:outline-none truncate"
                          >
                            <option value="Vui lòng chọn giá phòng" disabled>Chọn giá phòng</option>
                            <option v-for="rc in activeRoomRateCodes" :key="rc.id" :value="rc.Ma">{{ rc.Ma }}</option>
                          </select>
                          <span v-else class="text-slate-400 font-semibold truncate block w-full" :title="room.rateCode">
                            {{ room.rateCode === 'Vui lòng chọn giá phòng' ? 'Chưa chọn giá' : room.rateCode }}
                          </span>
                        </template>
                        <template v-else-if="col.key === 'adjustment'">
                          <div v-if="isEditing" class="relative">
                            <div 
                              @click.stop="toggleRoomDiscountPopover(room)"
                              class="w-full border border-slate-300 rounded px-1 py-0.5 text-slate-700 shadow-sm text-xs flex items-center justify-between cursor-pointer bg-white overflow-hidden"
                              :title="getDiscountLabel(room)"
                            >
                              <span class="font-bold text-[10px] truncate min-w-0" :class="room.discountValue ? 'text-sky-600' : 'text-slate-500'">{{ getDiscountLabel(room) }}</span>
                              <i class="fa-solid fa-calculator text-slate-400 text-[10px] shrink-0 ml-0.5"></i>
                            </div>
                            
                            <!-- Popover UI for Room -->
                            <div 
                              v-if="activeRoomDiscountId === room.id" 
                              @click.stop
                              class="absolute left-1/2 -translate-x-1/2 z-[9999] bg-white border border-slate-200 rounded-lg p-2.5 shadow-xl flex flex-col gap-2 w-[185px] pointer-events-auto"
                              :class="idx < 2 ? 'top-full mt-1.5' : 'bottom-full mb-1.5'"
                            >
                              <!-- Toggle Tăng/Giảm -->
                              <div class="flex items-center gap-1.5 select-none">
                                <button 
                                  type="button"
                                  @click.stop="room.discountType = 'up'; calculateRoomAdjustedPrice(room)"
                                  class="flex-1 py-1 rounded text-[10px] font-extrabold cursor-pointer border transition-colors flex items-center justify-center gap-1"
                                  :style="{ minHeight: '26px' }"
                                  :class="room.discountType === 'up' ? 'bg-sky-100 text-sky-700 border-sky-300' : 'bg-slate-50 text-slate-400 border-slate-200 hover:bg-slate-100'"
                                >
                                  <i class="fa-solid fa-angles-up text-emerald-500"></i>
                                  <span>Tăng</span>
                                </button>
                                <button 
                                  type="button"
                                  @click.stop="room.discountType = 'down'; calculateRoomAdjustedPrice(room)"
                                  class="flex-1 py-1 rounded text-[10px] font-extrabold cursor-pointer border transition-colors flex items-center justify-center gap-1"
                                  :style="{ minHeight: '26px' }"
                                  :class="room.discountType === 'down' ? 'bg-sky-100 text-sky-700 border-sky-300' : 'bg-slate-50 text-slate-400 border-slate-200 hover:bg-slate-100'"
                                >
                                  <i class="fa-solid fa-angles-down text-rose-500"></i>
                                  <span>Giảm</span>
                                </button>
                              </div>
                              
                              <!-- Input and unit toggle -->
                              <div class="flex items-center gap-1.5">
                                <div class="relative flex-1 border border-slate-300 rounded bg-white shadow-sm flex items-center h-[26px]">
                                  <input 
                                    type="text"
                                    :value="room.discountUnit === 'percent' ? room.discountValue : formatCurrencyInput(room.discountValue)"
                                    @input="e => { room.discountValue = room.discountUnit === 'percent' ? Number(e.target.value.replace(/[^\d]/g, '')) || 0 : cleanCurrencyValue(e.target.value); calculateRoomAdjustedPrice(room) }"
                                    @focus="e => { if (cleanCurrencyValue(e.target.value) === 0) e.target.value = ''; e.target.select() }"
                                    class="w-full text-right px-1.5 focus:outline-none text-[11px] bg-transparent border-none outline-none font-bold text-slate-800"
                                  />
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer select-none">
                                  <input 
                                    type="checkbox" 
                                    :checked="room.discountUnit === 'percent'" 
                                    @change="e => { room.discountUnit = e.target.checked ? 'percent' : 'amount'; calculateRoomAdjustedPrice(room) }"
                                    class="sr-only peer"
                                  />
                                  <div class="w-11 h-[20px] bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-[20px] after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-[16px] after:w-[16px] after:transition-all peer-checked:bg-sky-500"></div>
                                  <span 
                                    class="absolute text-[8px] font-black pointer-events-none select-none transition-all"
                                    :class="room.discountUnit === 'percent' ? 'left-[6px] text-white' : 'right-[7px] text-slate-500'"
                                  >
                                    {{ room.discountUnit === 'percent' ? '%' : 'VND' }}
                                  </span>
                                </label>
                              </div>
                              
                              <!-- Price summary preview -->
                              <div class="text-[10px] text-slate-500 border-t border-slate-100 pt-1.5 mt-0.5 flex flex-col gap-0.5 select-none">
                                <div class="flex justify-between"><span>Giá gốc:</span><span class="font-bold">{{ formatCurrencyInput(room.basePrice) }}đ</span></div>
                                <div class="flex justify-between"><span>Giá mới:</span><span class="font-bold text-sky-600">{{ formatCurrencyInput(room.price) }}đ</span></div>
                              </div>
                            </div>
                          </div>
                          <span v-else class="text-[11px] font-bold" :class="room.discountValue ? 'text-sky-600' : 'text-slate-500'">{{ getDiscountLabel(room) }}</span>
                        </template>
                        <template v-else-if="col.key === 'guestName'">
                          <input 
                            v-if="isEditing" 
                            type="text" 
                            v-model="room.guestName" 
                            class="bg-white border border-slate-300 rounded px-1.5 py-0.5 text-xs w-full font-semibold focus:outline-none" 
                          />
                          <span v-else class="text-gray-900 font-semibold">{{ room.guestName }}</span>
                        </template>
                        <template v-else-if="col.key === 'adults'">
                          <div v-if="isEditing" class="relative w-full min-w-[35px] max-w-[50px] mx-auto border border-slate-300 rounded-md h-[26px] bg-white shadow-sm flex items-center" @click.stop>
                            <input 
                              type="number" 
                              v-model.number="room.adults" 
                              min="1" 
                              @input="syncRoomToAllocation(room)" 
                              @focus="$event.target.select()" 
                              class="w-full text-center pr-3 focus:outline-none text-[11px] bg-transparent border-none outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                            />
                            <div class="flex flex-col text-slate-800 absolute right-1 top-0 bottom-0 justify-center items-center w-3 select-none">
                              <button @click.prevent="room.adults++; syncRoomToAllocation(room)" class="hover:text-black leading-[0.6] outline-none border-none bg-transparent cursor-pointer p-0"><i class="fa-solid fa-caret-up text-[9px]"></i></button>
                              <button @click.prevent="room.adults > 1 ? (room.adults--, syncRoomToAllocation(room)) : null" class="hover:text-black leading-[0.6] outline-none border-none bg-transparent cursor-pointer p-0"><i class="fa-solid fa-caret-down text-[9px]"></i></button>
                            </div>
                          </div>
                          <span v-else>{{ room.adults }}</span>
                        </template>
                        <template v-else-if="col.key === 'babies'">
                          <div v-if="isEditing" class="relative w-full min-w-[35px] max-w-[50px] mx-auto border border-slate-300 rounded-md h-[26px] bg-white shadow-sm flex items-center" @click.stop>
                            <input 
                              type="number" 
                              v-model.number="room.babies" 
                              min="0" 
                              @input="syncRoomToAllocation(room)" 
                              @focus="$event.target.select()" 
                              class="w-full text-center pr-3 focus:outline-none text-[11px] bg-transparent border-none outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                            />
                            <div class="flex flex-col text-slate-800 absolute right-1 top-0 bottom-0 justify-center items-center w-3 select-none">
                              <button @click.prevent="room.babies++; syncRoomToAllocation(room)" class="hover:text-black leading-[0.6] outline-none border-none bg-transparent cursor-pointer p-0"><i class="fa-solid fa-caret-up text-[9px]"></i></button>
                              <button @click.prevent="room.babies > 0 ? (room.babies--, syncRoomToAllocation(room)) : null" class="hover:text-black leading-[0.6] outline-none border-none bg-transparent cursor-pointer p-0"><i class="fa-solid fa-caret-down text-[9px]"></i></button>
                            </div>
                          </div>
                          <span v-else>{{ room.babies }}</span>
                        </template>
                        <template v-else-if="col.key === 'children'">
                          <div v-if="isEditing" class="relative w-full min-w-[35px] max-w-[50px] mx-auto border border-slate-300 rounded-md h-[26px] bg-white shadow-sm flex items-center" @click.stop>
                            <input 
                              type="number" 
                              v-model.number="room.children" 
                              min="0" 
                              @input="syncRoomToAllocation(room)" 
                              @focus="$event.target.select()" 
                              class="w-full text-center pr-3 focus:outline-none text-[11px] bg-transparent border-none outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                            />
                            <div class="flex flex-col text-slate-800 absolute right-1 top-0 bottom-0 justify-center items-center w-3 select-none">
                              <button @click.prevent="room.children++; syncRoomToAllocation(room)" class="hover:text-black leading-[0.6] outline-none border-none bg-transparent cursor-pointer p-0"><i class="fa-solid fa-caret-up text-[9px]"></i></button>
                              <button @click.prevent="room.children > 0 ? (room.children--, syncRoomToAllocation(room)) : null" class="hover:text-black leading-[0.6] outline-none border-none bg-transparent cursor-pointer p-0"><i class="fa-solid fa-caret-down text-[9px]"></i></button>
                            </div>
                          </div>
                          <span v-else>{{ room.children }}</span>
                        </template>
                        <template v-else-if="col.key === 'childBreakfast'">
                          <button @click.stop="openChildBreakfastModal(room)" class="px-2 py-0.5 border border-sky-200 hover:border-sky-300 bg-sky-50 text-sky-700 rounded text-[9px] font-semibold cursor-pointer">Chi tiết</button>
                        </template>
                        <template v-else-if="col.key === 'breakfast'">
                          <label class="relative inline-flex items-center cursor-pointer scale-75" @click.stop>
                            <input type="checkbox" v-model="room.breakfast" class="sr-only peer" :disabled="!isEditing" @change="syncRoomToAllocation(room)">
                            <div class="w-8 h-4 bg-slate-200 rounded-full peer peer-checked:bg-blue-500 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:after:translate-x-4"></div>
                          </label>
                        </template>
                        <template v-else-if="col.key === 'upgrade'">
                          <select v-model="room.upgradeClassId" @click.stop class="w-full border border-slate-300 rounded-md h-[26px] pl-1.5 pr-4 appearance-none focus:outline-none text-slate-700 bg-white shadow-sm cursor-pointer text-[10px]">
                            <option :value="null" disabled>Chọn hạng</option>
                            <option v-for="rc in roomClasses" :key="rc.id" :value="rc.id">{{ rc.code }}</option>
                          </select>
                        </template>
                        <template v-else-if="col.key === 'extraBed'">
                          <div class="flex items-center justify-center gap-1">
                            <span class="font-bold text-slate-700 text-[11px]">{{ room.extraBedQty || 0 }}</span>
                            <button @click.stop="openExtraBedModal(room)" class="px-1.5 py-0.5 border border-sky-200 hover:border-sky-300 bg-sky-50 text-sky-700 rounded text-[9px] font-semibold cursor-pointer shadow-2xs">
                              <span>Chi tiết</span>
                            </button>
                          </div>
                        </template>
                        <template v-else-if="col.key === 'extraBedPrice'">
                          <span class="text-gray-900 font-semibold" :title="`Đơn giá: ${formatCurrencyInput(room.extraBedPrice)}/đêm`">{{ getRoomExtraBedTotal(room) > 0 ? formatCurrencyInput(getRoomExtraBedTotal(room)) : '' }}</span>
                        </template>
                        <template v-else-if="col.key === 'hourly'">
                          <label class="relative inline-flex items-center cursor-pointer scale-75">
                            <input type="checkbox" v-model="room.hourly" class="sr-only peer" :disabled="!isEditing">
                            <div class="w-8 h-4 bg-slate-200 rounded-full peer peer-checked:bg-blue-500 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:after:translate-x-4"></div>
                          </label>
                        </template>
                        <template v-else-if="col.key === 'specialRequests'">
                          <button 
                            @click.stop="openSpecialRequestsModal(room)" 
                            class="px-1.5 py-0.5 border border-sky-200 hover:border-sky-300 bg-sky-50 text-sky-700 rounded text-[9px] font-semibold cursor-pointer max-w-[120px] truncate"
                            :title="getRoomSpecialRequestsText(room)"
                          >
                            {{ getRoomSpecialRequestsText(room) }}
                          </button>
                        </template>
                        <template v-else-if="col.key === 'arrivalTime'">
                          <TimePicker24h 
                            v-if="isEditing" 
                            v-model="room.arrivalTime" 
                            default-time="14:00"
                            :disabled="!isEditing" 
                          />
                          <span v-else>{{ room.arrivalTime || '14:00' }}</span>
                        </template>
                        <template v-else-if="col.key === 'hoursOut'">
                          <TimePicker24h 
                            v-if="isEditing" 
                            v-model="room.hoursOut" 
                            default-time="12:00"
                            :disabled="!isEditing" 
                          />
                          <span v-else>{{ room.hoursOut || '12:00' }}</span>
                        </template>
                        <template v-else-if="col.key === 'isPreassigned'">
                          <input 
                            type="checkbox" 
                            v-model="room.isPreassigned" 
                            :disabled="!isEditing"
                            class="rounded text-sky-500 focus:ring-sky-500 w-3.5 h-3.5 cursor-pointer inline-block"
                          />
                        </template>
                        <template v-else-if="col.key === 'initialRoomClass'">
                          <input 
                            v-if="isEditing" 
                            type="text" 
                            v-model="room.initialRoomClass" 
                            class="bg-white border border-slate-300 rounded px-1.5 py-0.5 text-xs w-full font-semibold focus:outline-none text-center" 
                          />
                          <span v-else>{{ room.initialRoomClass || room.shape || '-' }}</span>
                        </template>
                        <template v-else-if="col.key === 'transferredFrom'">
                          <input 
                            v-if="isEditing" 
                            type="text" 
                            v-model="room.transferredFrom" 
                            class="bg-white border border-slate-300 rounded px-1.5 py-0.5 text-xs w-full font-semibold focus:outline-none text-center" 
                          />
                          <span v-else>{{ room.transferredFrom || '-' }}</span>
                        </template>
                        <template v-else-if="col.key === 'roomStatus'">
                          <select 
                            v-if="isEditing" 
                            v-model="room.roomStatus" 
                            class="bg-white border border-slate-300 rounded px-1.5 py-0.5 text-xs w-full font-semibold focus:outline-none text-center"
                          >
                            <option value="Sạch">Sạch</option>
                            <option value="Bẩn">Bẩn</option>
                            <option value="Đang dọn">Đang dọn</option>
                            <option value="Kiểm tra">Kiểm tra</option>
                          </select>
                          <span v-else 
                                :class="{
                                  'text-green-600': room.roomStatus === 'Sạch',
                                  'text-rose-600': room.roomStatus === 'Bẩn',
                                  'text-amber-600': room.roomStatus === 'Đang dọn',
                                  'text-sky-600': room.roomStatus === 'Kiểm tra'
                                }">
                            {{ room.roomStatus || 'Sạch' }}
                          </span>
                        </template>
                        <template v-else-if="col.key === 'allotmentCode'">
                          <input 
                            v-if="isEditing" 
                            type="text" 
                            v-model="room.allotmentCode" 
                            class="bg-white border border-slate-300 rounded px-1.5 py-0.5 text-xs w-full font-semibold focus:outline-none" 
                          />
                          <span v-else>{{ room.allotmentCode || '-' }}</span>
                        </template>
                        <template v-else-if="col.key === 'roomCode'">
                          <input 
                            v-if="isEditing" 
                            type="text" 
                            v-model="room.roomCode" 
                            class="bg-white border border-slate-300 rounded px-1.5 py-0.5 text-xs w-full font-semibold focus:outline-none" 
                          />
                          <span v-else>{{ room.roomCode || '-' }}</span>
                        </template>
                        </td>
                        <td class="p-2 text-right text-gray-900 font-bold bg-[#f1f5f9] group-hover:bg-[#e2e8f0] sticky-shadow-left z-10" @click.stop>{{ (Number(room.total) || 0).toLocaleString('en-US') }}</td>
                      </tr>

                      <!-- Expanded Services Row -->
                      <tr v-if="expandedRooms.includes(room.id)" :key="`services-${room.id}`" class="bg-slate-50/30">
                        <td class="p-0 border-r border-b border-slate-200 bg-slate-50/10"></td>
                        <td class="p-0 border-r border-b border-slate-200 bg-slate-50/10"></td>
                        <td class="p-0 border-r border-b border-slate-200 bg-slate-50/10"></td>
                        <td class="p-0 border-r border-b border-slate-200 bg-slate-50/10"></td>
                        <td class="p-0 border-r border-b border-slate-200 bg-slate-50/10"></td>
                        <td :colspan="columns.filter(c => c.visible).length + 1" class="p-3 border-b border-slate-200 bg-slate-50/20 text-left pl-6">
                          <div class="max-w-[850px] border border-slate-200 rounded shadow-xs overflow-hidden bg-white my-1" @click.stop>
                            <table class="w-full text-left border-collapse text-[11px] table-fixed">
                              <colgroup>
                                <col style="width: 100px;" />
                                <col style="width: 200px;" />
                                <col style="width: 110px;" />
                                <col style="width: 110px;" />
                                <col style="width: 70px;" />
                                <col style="width: 100px;" />
                                <col style="width: 110px;" />
                                <col style="width: 80px;" />
                              </colgroup>
                              <thead>
                                <tr class="bg-slate-100 text-slate-700 font-bold border-b border-slate-200 select-none">
                                  <th class="p-2 border-r border-slate-200">Ngày</th>
                                  <th class="p-2 border-r border-slate-200">Dịch vụ</th>
                                  <th class="p-2 border-r border-slate-200">Mã Giá Phòng</th>
                                  <th class="p-2 border-r border-slate-200">Tăng/giảm giá</th>
                                  <th class="p-2 border-r border-slate-200 text-center">Số lượng</th>
                                  <th class="p-2 border-r border-slate-200 text-right">Đơn giá</th>
                                  <th class="p-2 border-r border-slate-200 text-right">Thành tiền</th>
                                  <th class="p-2 text-center">GIT/FIT</th>
                                </tr>
                              </thead>
                              <tbody>
                                <tr 
                                  v-for="svc in getRoomDisplayServices(room)" 
                                  :key="svc.id" 
                                  class="border-b border-slate-100 hover:bg-slate-50/80 text-slate-600 font-semibold"
                                >
                                  <td class="p-2 border-r border-slate-100">{{ formatDateVi(svc.service_date) }}</td>
                                  <td class="p-2 border-r border-slate-100 text-slate-800 font-bold">{{ svc.service_name }}</td>
                                  <td class="p-2 border-r border-slate-100 text-slate-400 italic">—</td>
                                  <td class="p-2 border-r border-slate-100 text-slate-400 italic">Tăng/Giảm giá</td>
                                  <td class="p-2 border-r border-slate-100 text-center text-slate-700">{{ svc.quantity !== undefined && svc.quantity !== null ? Number(svc.quantity) : 1 }}</td>
                                  <td class="p-2 border-r border-slate-100 text-right text-slate-800 font-bold">{{ (Number(svc.rate) || 0).toLocaleString('en-US') }}</td>
                                  <td class="p-2 border-r border-slate-100 text-right text-sky-700 font-bold">{{ (Number(svc.quantity || 1) * Number(svc.rate || 0)).toLocaleString('en-US') }}</td>
                                  <td class="p-2 text-center">
                                    <span class="px-1.5 py-0.5 rounded bg-sky-100 text-sky-700 text-[9px] font-bold uppercase select-none">FIT</span>
                                  </td>
                                </tr>
                              </tbody>
                            </table>
                          </div>
                        </td>
                      </tr>
                    </template>
                  </template>
                </template><!-- end v-for typeName -->
                </template><!-- end v-if="!hasStatusGroups" MODE A -->

                <!-- ===== MODE B: Mixed check-in/booked → status group → room type sub-group ===== -->
                <template v-else>
                  <template v-for="statusGroup in groupedRoomsNested" :key="statusGroup.statusName">
                    <!-- Status Section Header (e.g. "Đang ở", "Đã đặt") -->
                    <tr 
                      class="border-b font-bold h-8 cursor-pointer select-none"
                      :class="statusGroup.statusOrder === 3 ? 'bg-red-100 border-red-300 text-red-900 font-extrabold' : 'bg-[#dbeafe]/60 border-blue-200 text-blue-900'"
                      @click="toggleGroupCollapse('status_' + statusGroup.statusName)"
                    >
                      <td class="p-2 border-r border-blue-200 text-center" @click.stop>
                        <button 
                          @click="toggleGroupCollapse('status_' + statusGroup.statusName)" 
                          class="w-5 h-5 flex items-center justify-center rounded text-white font-bold select-none cursor-pointer border-none"
                          :class="statusGroup.statusOrder === 3 ? 'bg-red-500 hover:bg-red-600' : 'bg-blue-400 hover:bg-blue-500'"
                          style="font-size: 13px; line-height: 1;"
                        >
                          {{ collapsedSections['status_' + statusGroup.statusName] ? '+' : '−' }}
                        </button>
                      </td>
                      <td class="p-2 border-r border-blue-200 text-center" @click.stop>
                        <input 
                          type="checkbox" 
                          :checked="statusGroup.typeGroups.flatMap(g => g.rooms).length > 0 && statusGroup.typeGroups.flatMap(g => g.rooms).every(r => selectedRows.includes(r.id))" 
                          @change="e => handleSelectAllInGroup(statusGroup.typeGroups.flatMap(g => g.rooms), e.target.checked)" 
                        />
                      </td>
                      <td :colspan="columns.filter(c => c.visible).length + 3" class="p-2 font-bold text-xs uppercase tracking-wider" :class="statusGroup.statusOrder === 3 ? 'text-red-900 font-black' : 'text-blue-900'">
                        Tình trạng: {{ statusGroup.statusName }} ({{ statusGroup.typeGroups.reduce((acc, curr) => acc + curr.rooms.length, 0) }})
                      </td>
                      <td class="sticky-shadow-left z-10" :class="statusGroup.statusOrder === 3 ? 'bg-red-200' : 'bg-[#bfdbfe]'"></td>
                    </tr>

                    <!-- Room-type sub-groups within this status section -->
                    <template v-if="!collapsedSections['status_' + statusGroup.statusName]">
                      <template v-for="group in statusGroup.typeGroups" :key="group.typeName">
                        <!-- Sub-group Header (Room Type) -->
                        <tr 
                          class="bg-slate-50/70 border-b border-slate-200 font-bold h-8 text-gray-900 cursor-pointer select-none"
                          @click="toggleGroupCollapse(statusGroup.statusName + '_' + group.typeName)"
                        >
                          <td class="p-2 border-r border-slate-200 text-center bg-slate-100/10"></td>
                          <td class="p-2 border-r border-slate-200 text-center" @click.stop>
                            <button 
                              @click="toggleGroupCollapse(statusGroup.statusName + '_' + group.typeName)" 
                              class="w-5 h-5 flex items-center justify-center rounded bg-[#8cc3f3] hover:bg-[#6baae6] text-white font-bold select-none cursor-pointer border-none"
                              style="font-size: 13px; line-height: 1;"
                            >
                              {{ collapsedSections[statusGroup.statusName + '_' + group.typeName] ? '+' : '−' }}
                            </button>
                          </td>
                          <td class="p-2 border-r border-slate-200 text-center" @click.stop>
                            <input 
                              type="checkbox" 
                              :checked="group.rooms.length > 0 && group.rooms.every(r => selectedRows.includes(r.id))" 
                              @change="e => handleSelectAllInGroup(group.rooms, e.target.checked)" 
                            />
                          </td>
                          <td :colspan="columns.filter(c => c.visible).length + 2" class="p-2 text-gray-700 font-bold text-xs uppercase tracking-wider pl-4">
                            {{ group.typeName }} ({{ group.rooms.length }})
                          </td>
                          <td class="bg-[#e2e8f0] sticky-shadow-left z-10"></td>
                        </tr>

                        <!-- Rooms within this sub-group -->
                        <template v-if="!collapsedSections[statusGroup.statusName + '_' + group.typeName]">
                          <template v-for="(room, idx) in group.rooms" :key="room.id">
                            <tr 
                              class="border-b border-slate-200 hover:bg-sky-50/30 transition-colors h-9 group cursor-pointer text-gray-900"
                              :class="[
                                selectedRows.includes(room.id) ? 'bg-sky-50/60 ring-1 ring-inset ring-sky-200' : '',
                                (Number(room.bookingRoomStatus) === 3 || Number(room.bookingRoomStatus) === 100) ? 'cancelled-room text-red-700 bg-red-50/40 font-medium' : ''
                              ]"
                              @click="handleRowSelect(room.id)"
                              :title="`Phòng: ${room.roomNumber || '(chưa gán)'} | Khách: ${room.guestName || ''} | CI: ${room.checkIn} → CO: ${room.checkOut} | ${room.nights} đêm | ${(Number(room.total)||0).toLocaleString('en-US')}đ`"
                            >
                              <td class="p-2 border-r border-slate-200 text-center bg-slate-100/10"></td>
                              <td class="p-2 border-r border-slate-200 text-center bg-slate-100/10"></td>
                              <td class="p-2 border-r border-slate-200 text-center" @click.stop>
                                <button 
                                  @click="toggleRoomExpand(room)"
                                  class="w-5 h-5 flex items-center justify-center rounded transition-colors text-white font-bold select-none cursor-pointer"
                                  :class="expandedRooms.includes(room.id) ? 'bg-[#ff9e3b] hover:bg-[#e08422]' : 'bg-[#8cc3f3] hover:bg-[#6baae6]'"
                                  style="font-size: 13px; line-height: 1;"
                                >
                                  {{ expandedRooms.includes(room.id) ? '−' : '+' }}
                                </button>
                              </td>
                              <td class="p-2 border-r border-slate-200 text-center" @click.stop>
                                <input type="checkbox" :checked="selectedRows.includes(room.id)" @change="handleRowSelect(room.id)" />
                              </td>
                              <td class="p-2 border-r border-slate-200 text-center text-gray-500 font-semibold bg-slate-50/30">{{ idx + 1 }}</td>
                              <td v-for="col in columns.filter(c => c.visible)" 
                                  :key="col.key" 
                                  class="p-2 border-r border-slate-200" 
                                  :class="[col.center ? 'text-center' : '', col.right ? 'text-right' : '']"
                              >
                                <template v-if="col.key === 'type'">
                                  <select 
                                    v-if="isEditing" 
                                    v-model="room.roomClassId" 
                                    @focus="room._oldClassId = room.roomClassId" 
                                    @change="handleRoomClassChange(room, room._oldClassId)" 
                                    class="bg-white border border-slate-300 rounded px-1 py-0.5 text-[11px] w-full font-semibold focus:outline-none cursor-pointer"
                                  >
                                    <option v-for="rc in roomClasses" :key="rc.id" :value="rc.id">{{ rc.name }}</option>
                                  </select>
                                  <span v-else class="text-gray-900 font-semibold truncate block w-full" :title="room.type">{{ room.type }}</span>
                                </template>
                                <template v-else-if="col.key === 'shape'">
                                  <select 
                                    v-if="isEditing" 
                                    :value="getRoomFormId(room)" 
                                    @change="handleRoomFormChange(room, $event.target.value)" 
                                    class="bg-white border border-slate-300 rounded px-1 py-0.5 text-[11px] w-full font-semibold focus:outline-none cursor-pointer text-center"
                                  >
                                    <option v-for="rf in getAvailableFormsForRoom(room)" :key="rf.id" :value="rf.id">{{ rf.name }}</option>
                                  </select>
                                  <span v-else class="text-gray-900 font-semibold">{{ room.shape }}</span>
                                </template>
                                <template v-else-if="col.key === 'roomNumber'">
                                  <div v-if="room.isDoNotMove" class="flex items-center justify-center gap-1.5 text-[11px] font-bold text-gray-700 bg-slate-100 border border-slate-200 rounded px-1.5 py-0.5 max-w-[95px] mx-auto select-none" title="Khóa chuyển phòng (Do Not Move)">
                                    <span>{{ room.roomNumber || '-' }}</span>
                                    <svg class="w-3 h-3 text-red-500 fill-current" viewBox="0 0 24 24">
                                      <rect x="3" y="11" width="18" height="11" rx="2" ry="2" fill="none" stroke="currentColor" stroke-width="2.5"></rect>
                                      <path d="M7 11V7a5 5 0 0 1 10 0v4" fill="none" stroke="currentColor" stroke-width="2.5"></path>
                                    </svg>
                                  </div>
                                  <select 
                                    v-else-if="isEditing" 
                                    v-model="room.roomNumber" 
                                    @focus="loadVacantRoomsForRoom(room)"
                                    class="bg-white border border-slate-300 rounded px-1 py-0.5 text-[11px] w-full font-semibold focus:outline-none text-center cursor-pointer"
                                  >
                                    <option value="">Chọn phòng</option>
                                    <option v-if="room.roomNumber" :value="room.roomNumber">{{ room.roomNumber }}</option>
                                    <option 
                                      v-for="vRoom in getVacantRoomsList(room)" 
                                      :key="vRoom.room_number" 
                                      :value="vRoom.room_number"
                                    >
                                      {{ vRoom.room_number }} ({{ vRoom.status }})
                                    </option>
                                  </select>
                                  <span v-else>{{ room.roomNumber || '-' }}</span>
                                </template>
                                <template v-else-if="col.key === 'checkIn'">
                                  <input 
                                    v-if="isEditing" 
                                    type="date" 
                                    v-model="room.checkIn" 
                                    @change="handleRowDateChangeInline(room)"
                                    @click="$event.target.showPicker && $event.target.showPicker()"
                                    class="date-span-input border border-slate-300 rounded px-1 py-0.5 text-[11px] font-semibold text-slate-800 bg-white shadow-sm text-center focus:outline-none inline-block mx-auto" 
                                  />
                                  <span v-else class="text-gray-500 font-semibold">{{ formatDateVi(room.checkIn) }}</span>
                                </template>
                                <template v-else-if="col.key === 'checkOut'">
                                  <input 
                                    v-if="isEditing" 
                                    type="date" 
                                    v-model="room.checkOut" 
                                    @change="handleRowDateChangeInline(room)"
                                    @click="$event.target.showPicker && $event.target.showPicker()"
                                    class="date-span-input border border-slate-300 rounded px-1 py-0.5 text-[11px] font-semibold text-slate-800 bg-white shadow-sm text-center focus:outline-none inline-block mx-auto" 
                                  />
                                  <span v-else class="text-gray-500 font-semibold">{{ formatDateVi(room.checkOut) }}</span>
                                </template>
                                <template v-else-if="col.key === 'nights'">
                                  <input 
                                    v-if="isEditing" 
                                    type="number" 
                                    v-model.number="room.nights" 
                                    min="1"
                                    @input="handleRowNightsChangeInline(room)"
                                    @click.stop
                                    class="border border-slate-300 rounded px-1 py-0.5 text-[11px] w-[50px] font-semibold focus:outline-none text-center bg-white mx-auto block" 
                                  />
                                  <span v-else class="text-gray-900 font-semibold">{{ room.nights }}</span>
                                </template>
                                <template v-else-if="col.key === 'price'">
                                  <input 
                                    v-if="isEditing" 
                                    type="text" 
                                    :value="formatCurrencyInput(room.price)" 
                                    :disabled="isRoomPriceDisabled(room)"
                                    @input="e => { room.price = cleanCurrencyValue(e.target.value); room.total = calculateRoomTotal(room) }"
                                    @focus="e => { if (cleanCurrencyValue(e.target.value) === 0) e.target.value = ''; e.target.select() }"
                                    class="border rounded px-1.5 py-0.5 text-xs w-full font-semibold focus:outline-none text-right" 
                                    :class="isRoomPriceDisabled(room) ? 'bg-slate-100 cursor-not-allowed text-slate-400 border-slate-200' : 'bg-white border-slate-300 text-slate-900'"
                                  />
                                  <span v-else>{{ formatCurrencyInput(room.price) }}</span>
                                </template>
                                <template v-else-if="col.key === 'rateCode'">
                                  <select 
                                    v-if="isEditing" 
                                    v-model="room.rateCode" 
                                    @change="handleRoomRateCodeChange(room)"
                                    class="bg-white border border-slate-300 rounded px-1.5 py-0.5 text-[11px] w-full font-semibold focus:outline-none truncate"
                                  >
                                    <option value="Vui lòng chọn giá phòng" disabled>Chọn giá phòng</option>
                                    <option v-for="rc in activeRoomRateCodes" :key="rc.id" :value="rc.Ma">{{ rc.Ma }}</option>
                                  </select>
                                  <span v-else class="text-slate-400 font-semibold truncate block w-full" :title="room.rateCode">
                                    {{ room.rateCode === 'Vui lòng chọn giá phòng' ? 'Chưa chọn giá' : room.rateCode }}
                                  </span>
                                </template>
                                <template v-else-if="col.key === 'adjustment'">
                                  <div v-if="isEditing" class="relative">
                                    <div 
                                      @click.stop="toggleRoomDiscountPopover(room)"
                                      class="w-full border border-slate-300 rounded px-1 py-0.5 text-slate-700 shadow-sm text-xs flex items-center justify-between cursor-pointer bg-white overflow-hidden"
                                      :title="getDiscountLabel(room)"
                                    >
                                      <span class="font-bold text-[10px] truncate min-w-0" :class="room.discountValue ? 'text-sky-600' : 'text-slate-500'">{{ getDiscountLabel(room) }}</span>
                                      <i class="fa-solid fa-calculator text-slate-400 text-[10px] shrink-0 ml-0.5"></i>
                                    </div>
                                    
                                    <!-- Popover UI for Room -->
                                    <div 
                                      v-if="activeRoomDiscountId === room.id" 
                                      @click.stop
                                      class="absolute left-1/2 -translate-x-1/2 z-[9999] bg-white border border-slate-200 rounded-lg p-2.5 shadow-xl flex flex-col gap-2 w-[185px] pointer-events-auto"
                                      :class="idx < 2 ? 'top-full mt-1.5' : 'bottom-full mb-1.5'"
                                    >
                                      <!-- Toggle Tăng/Giảm -->
                                      <div class="flex items-center gap-1.5 select-none">
                                        <button 
                                          type="button"
                                          @click.stop="room.discountType = 'up'; calculateRoomAdjustedPrice(room)"
                                          class="flex-1 py-1 rounded text-[10px] font-extrabold cursor-pointer border transition-colors flex items-center justify-center gap-1"
                                          :style="{ minHeight: '26px' }"
                                          :class="room.discountType === 'up' ? 'bg-sky-100 text-sky-700 border-sky-300' : 'bg-slate-50 text-slate-400 border-slate-200 hover:bg-slate-100'"
                                        >
                                          <i class="fa-solid fa-angles-up text-emerald-500"></i>
                                          <span>Tăng</span>
                                        </button>
                                        <button 
                                          type="button"
                                          @click.stop="room.discountType = 'down'; calculateRoomAdjustedPrice(room)"
                                          class="flex-1 py-1 rounded text-[10px] font-extrabold cursor-pointer border transition-colors flex items-center justify-center gap-1"
                                          :style="{ minHeight: '26px' }"
                                          :class="room.discountType === 'down' ? 'bg-sky-100 text-sky-700 border-sky-300' : 'bg-slate-50 text-slate-400 border-slate-200 hover:bg-slate-100'"
                                        >
                                          <i class="fa-solid fa-angles-down text-rose-500"></i>
                                          <span>Giảm</span>
                                        </button>
                                      </div>
                                      
                                      <!-- Input and unit toggle -->
                                      <div class="flex items-center gap-1.5">
                                        <div class="relative flex-1 border border-slate-300 rounded bg-white shadow-sm flex items-center h-[26px]">
                                          <input 
                                            type="text"
                                            :value="room.discountUnit === 'percent' ? room.discountValue : formatCurrencyInput(room.discountValue)"
                                            @input="e => { room.discountValue = room.discountUnit === 'percent' ? Number(e.target.value.replace(/[^\d]/g, '')) || 0 : cleanCurrencyValue(e.target.value); calculateRoomAdjustedPrice(room) }"
                                            @focus="e => { if (cleanCurrencyValue(e.target.value) === 0) e.target.value = ''; e.target.select() }"
                                            class="w-full text-right px-1.5 focus:outline-none text-[11px] bg-transparent border-none outline-none font-bold text-slate-800"
                                          />
                                        </div>
                                        <label class="relative inline-flex items-center cursor-pointer select-none">
                                          <input 
                                            type="checkbox" 
                                            :checked="room.discountUnit === 'percent'" 
                                            @change="e => { room.discountUnit = e.target.checked ? 'percent' : 'amount'; calculateRoomAdjustedPrice(room) }"
                                            class="sr-only peer"
                                          />
                                          <div class="w-11 h-[20px] bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-[20px] after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-[16px] after:w-[16px] after:transition-all peer-checked:bg-sky-500"></div>
                                          <span 
                                            class="absolute text-[8px] font-black pointer-events-none select-none transition-all"
                                            :class="room.discountUnit === 'percent' ? 'left-[6px] text-white' : 'right-[7px] text-slate-500'"
                                          >
                                            {{ room.discountUnit === 'percent' ? '%' : 'VND' }}
                                          </span>
                                        </label>
                                      </div>
                                      
                                      <!-- Price summary preview -->
                                      <div class="text-[10px] text-slate-500 border-t border-slate-100 pt-1.5 mt-0.5 flex flex-col gap-0.5 select-none">
                                        <div class="flex justify-between"><span>Giá gốc:</span><span class="font-bold">{{ formatCurrencyInput(room.basePrice) }}đ</span></div>
                                        <div class="flex justify-between"><span>Giá mới:</span><span class="font-bold text-sky-600">{{ formatCurrencyInput(room.price) }}đ</span></div>
                                      </div>
                                    </div>
                                  </div>
                                  <span v-else class="text-[11px] font-bold" :class="room.discountValue ? 'text-sky-600' : 'text-slate-500'">{{ getDiscountLabel(room) }}</span>
                                </template>
                                <template v-else-if="col.key === 'guestName'">
                                  <input 
                                    v-if="isEditing" 
                                    type="text" 
                                    v-model="room.guestName" 
                                    class="bg-white border border-slate-300 rounded px-1.5 py-0.5 text-xs w-full font-semibold focus:outline-none" 
                                  />
                                  <span v-else class="text-gray-900 font-semibold">{{ room.guestName }}</span>
                                </template>
                                <template v-else-if="col.key === 'adults'">
                                  <div v-if="isEditing" class="relative w-full min-w-[35px] max-w-[50px] mx-auto border border-slate-300 rounded-md h-[26px] bg-white shadow-sm flex items-center" @click.stop>
                                    <input 
                                      type="number" 
                                      v-model.number="room.adults" 
                                      min="1" 
                                      @input="syncRoomToAllocation(room)" 
                                      @focus="$event.target.select()" 
                                      class="w-full text-center pr-3 focus:outline-none text-[11px] bg-transparent border-none outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                                    />
                                    <div class="flex flex-col text-slate-800 absolute right-1 top-0 bottom-0 justify-center items-center w-3 select-none">
                                      <button @click.prevent="room.adults++; syncRoomToAllocation(room)" class="hover:text-black leading-[0.6] outline-none border-none bg-transparent cursor-pointer p-0"><i class="fa-solid fa-caret-up text-[9px]"></i></button>
                                      <button @click.prevent="room.adults > 1 ? (room.adults--, syncRoomToAllocation(room)) : null" class="hover:text-black leading-[0.6] outline-none border-none bg-transparent cursor-pointer p-0"><i class="fa-solid fa-caret-down text-[9px]"></i></button>
                                    </div>
                                  </div>
                                  <span v-else>{{ room.adults }}</span>
                                </template>
                                <template v-else-if="col.key === 'babies'">
                                  <div v-if="isEditing" class="relative w-full min-w-[35px] max-w-[50px] mx-auto border border-slate-300 rounded-md h-[26px] bg-white shadow-sm flex items-center" @click.stop>
                                    <input 
                                      type="number" 
                                      v-model.number="room.babies" 
                                      min="0" 
                                      @input="syncRoomToAllocation(room)" 
                                      @focus="$event.target.select()" 
                                      class="w-full text-center pr-3 focus:outline-none text-[11px] bg-transparent border-none outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                                    />
                                    <div class="flex flex-col text-slate-800 absolute right-1 top-0 bottom-0 justify-center items-center w-3 select-none">
                                      <button @click.prevent="room.babies++; syncRoomToAllocation(room)" class="hover:text-black leading-[0.6] outline-none border-none bg-transparent cursor-pointer p-0"><i class="fa-solid fa-caret-up text-[9px]"></i></button>
                                      <button @click.prevent="room.babies > 0 ? (room.babies--, syncRoomToAllocation(room)) : null" class="hover:text-black leading-[0.6] outline-none border-none bg-transparent cursor-pointer p-0"><i class="fa-solid fa-caret-down text-[9px]"></i></button>
                                    </div>
                                  </div>
                                  <span v-else>{{ room.babies }}</span>
                                </template>
                                <template v-else-if="col.key === 'children'">
                                  <div v-if="isEditing" class="relative w-full min-w-[35px] max-w-[50px] mx-auto border border-slate-300 rounded-md h-[26px] bg-white shadow-sm flex items-center" @click.stop>
                                    <input 
                                      type="number" 
                                      v-model.number="room.children" 
                                      min="0" 
                                      @input="syncRoomToAllocation(room)" 
                                      @focus="$event.target.select()" 
                                      class="w-full text-center pr-3 focus:outline-none text-[11px] bg-transparent border-none outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                                    />
                                    <div class="flex flex-col text-slate-800 absolute right-1 top-0 bottom-0 justify-center items-center w-3 select-none">
                                      <button @click.prevent="room.children++; syncRoomToAllocation(room)" class="hover:text-black leading-[0.6] outline-none border-none bg-transparent cursor-pointer p-0"><i class="fa-solid fa-caret-up text-[9px]"></i></button>
                                      <button @click.prevent="room.children > 0 ? (room.children--, syncRoomToAllocation(room)) : null" class="hover:text-black leading-[0.6] outline-none border-none bg-transparent cursor-pointer p-0"><i class="fa-solid fa-caret-down text-[9px]"></i></button>
                                    </div>
                                  </div>
                                  <span v-else>{{ room.children }}</span>
                                </template>
                                <template v-else-if="col.key === 'childBreakfast'">
                                  <button @click.stop="openChildBreakfastModal(room)" class="px-2 py-0.5 border border-sky-200 hover:border-sky-300 bg-sky-50 text-sky-700 rounded text-[9px] font-semibold cursor-pointer">Chi tiết</button>
                                </template>
                                <template v-else-if="col.key === 'breakfast'">
                                  <label class="relative inline-flex items-center cursor-pointer scale-75" @click.stop>
                                    <input type="checkbox" v-model="room.breakfast" class="sr-only peer" :disabled="!isEditing" @change="syncRoomToAllocation(room)">
                                    <div class="w-8 h-4 bg-slate-200 rounded-full peer peer-checked:bg-blue-500 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:after:translate-x-4"></div>
                                  </label>
                                </template>
                                <template v-else-if="col.key === 'upgrade'">
                                  <select v-model="room.upgradeClassId" @click.stop class="w-full border border-slate-300 rounded-md h-[26px] pl-1.5 pr-4 appearance-none focus:outline-none text-slate-700 bg-white shadow-sm cursor-pointer text-[10px]">
                                    <option :value="null" disabled>Chọn hạng</option>
                                    <option v-for="rc in roomClasses" :key="rc.id" :value="rc.id">{{ rc.code }}</option>
                                  </select>
                                </template>
                                <template v-else-if="col.key === 'extraBed'">
                                  <div class="flex items-center justify-center gap-1">
                                    <span class="font-bold text-slate-700 text-[11px]">{{ room.extraBedQty || 0 }}</span>
                                    <button @click.stop="openExtraBedModal(room)" class="px-1.5 py-0.5 border border-sky-200 hover:border-sky-300 bg-sky-50 text-sky-700 rounded text-[9px] font-semibold cursor-pointer shadow-2xs">
                                      <span>Chi tiết</span>
                                    </button>
                                  </div>
                                </template>
                                <template v-else-if="col.key === 'extraBedPrice'">
                                  <span class="text-gray-900 font-semibold">{{ Number(room.extraBedQty) > 0 ? formatCurrencyInput(room.extraBedPrice) : '' }}</span>
                                </template>
                                <template v-else-if="col.key === 'hourly'">
                                  <label class="relative inline-flex items-center cursor-pointer scale-75">
                                    <input type="checkbox" v-model="room.hourly" class="sr-only peer" :disabled="!isEditing">
                                    <div class="w-8 h-4 bg-slate-200 rounded-full peer peer-checked:bg-blue-500 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:after:translate-x-4"></div>
                                  </label>
                                </template>
                                <template v-else-if="col.key === 'specialRequests'">
                                  <button 
                                    @click.stop="openSpecialRequestsModal(room)" 
                                    class="px-1.5 py-0.5 border border-sky-200 hover:border-sky-300 bg-sky-50 text-sky-700 rounded text-[9px] font-semibold cursor-pointer max-w-[120px] truncate"
                                    :title="getRoomSpecialRequestsText(room)"
                                  >
                                    {{ getRoomSpecialRequestsText(room) }}
                                  </button>
                                </template>
                                <template v-else-if="col.key === 'arrivalTime'">
                                  <TimePicker24h 
                                    v-if="isEditing" 
                                    v-model="room.arrivalTime" 
                                    default-time="14:00"
                                    :disabled="!isEditing" 
                                  />
                                  <span v-else>{{ room.arrivalTime || '14:00' }}</span>
                                </template>
                                <template v-else-if="col.key === 'hoursOut'">
                                  <TimePicker24h 
                                    v-if="isEditing" 
                                    v-model="room.hoursOut" 
                                    default-time="12:00"
                                    :disabled="!isEditing" 
                                  />
                                  <span v-else>{{ room.hoursOut || '12:00' }}</span>
                                </template>
                                <template v-else-if="col.key === 'isPreassigned'">
                                  <input 
                                    type="checkbox" 
                                    v-model="room.isPreassigned" 
                                    :disabled="!isEditing"
                                    class="rounded text-sky-500 focus:ring-sky-500 w-3.5 h-3.5 cursor-pointer inline-block"
                                  />
                                </template>
                                <template v-else-if="col.key === 'initialRoomClass'">
                                  <input 
                                    v-if="isEditing" 
                                    type="text" 
                                    v-model="room.initialRoomClass" 
                                    class="bg-white border border-slate-300 rounded px-1.5 py-0.5 text-xs w-full font-semibold focus:outline-none text-center" 
                                  />
                                  <span v-else>{{ room.initialRoomClass || room.shape || '-' }}</span>
                                </template>
                                <template v-else-if="col.key === 'transferredFrom'">
                                  <input 
                                    v-if="isEditing" 
                                    type="text" 
                                    v-model="room.transferredFrom" 
                                    class="bg-white border border-slate-300 rounded px-1.5 py-0.5 text-xs w-full font-semibold focus:outline-none text-center" 
                                  />
                                  <span v-else>{{ room.transferredFrom || '-' }}</span>
                                </template>
                                <template v-else-if="col.key === 'roomStatus'">
                                  <select 
                                    v-if="isEditing" 
                                    v-model="room.roomStatus" 
                                    class="bg-white border border-slate-300 rounded px-1.5 py-0.5 text-xs w-full font-semibold focus:outline-none text-center"
                                  >
                                    <option value="Sạch">Sạch</option>
                                    <option value="Bẩn">Bẩn</option>
                                    <option value="Đang dọn">Đang dọn</option>
                                    <option value="Kiểm tra">Kiểm tra</option>
                                  </select>
                                  <span v-else 
                                        :class="{
                                          'text-green-600': room.roomStatus === 'Sạch',
                                          'text-rose-600': room.roomStatus === 'Bẩn',
                                          'text-amber-600': room.roomStatus === 'Đang dọn',
                                          'text-sky-600': room.roomStatus === 'Kiểm tra'
                                        }">
                                    {{ room.roomStatus || 'Sạch' }}
                                  </span>
                                </template>
                                <template v-else-if="col.key === 'allotmentCode'">
                                  <input 
                                    v-if="isEditing" 
                                    type="text" 
                                    v-model="room.allotmentCode" 
                                    class="bg-white border border-slate-300 rounded px-1.5 py-0.5 text-xs w-full font-semibold focus:outline-none" 
                                  />
                                  <span v-else>{{ room.allotmentCode || '-' }}</span>
                                </template>
                                <template v-else-if="col.key === 'roomCode'">
                                  <input 
                                    v-if="isEditing" 
                                    type="text" 
                                    v-model="room.roomCode" 
                                    class="bg-white border border-slate-300 rounded px-1.5 py-0.5 text-xs w-full font-semibold focus:outline-none" 
                                  />
                                  <span v-else>{{ room.roomCode || '-' }}</span>
                                </template>
                                <template v-else>
                                  <span class="text-slate-400">—</span>
                                </template>
                              </td>
                              <td class="p-2 text-right text-gray-900 font-bold bg-[#f1f5f9] group-hover:bg-[#e2e8f0] sticky-shadow-left z-10" @click.stop>{{ (Number(room.total) || 0).toLocaleString('en-US') }}</td>
                            </tr>

                            <!-- Expanded Services Row (read-only for checked-in) -->
                            <tr v-if="expandedRooms.includes(room.id)" :key="`services-b-${room.id}`" class="bg-slate-50/30">
                              <td class="p-0 border-r border-b border-slate-200 bg-slate-50/10"></td>
                              <td class="p-0 border-r border-b border-slate-200 bg-slate-50/10"></td>
                              <td class="p-0 border-r border-b border-slate-200 bg-slate-50/10"></td>
                              <td class="p-0 border-r border-b border-slate-200 bg-slate-50/10"></td>
                              <td class="p-0 border-r border-b border-slate-200 bg-slate-50/10"></td>
                              <td :colspan="columns.filter(c => c.visible).length + 1" class="p-3 border-b border-slate-200 bg-slate-50/20 text-left pl-6">
                                <div class="max-w-[750px] border border-slate-200 rounded shadow-xs overflow-hidden bg-white my-1" @click.stop>
                                  <table class="w-full text-left border-collapse text-[11px] table-fixed">
                                    <colgroup>
                                      <col style="width: 100px;" />
                                      <col style="width: 200px;" />
                                      <col style="width: 70px;" />
                                      <col style="width: 100px;" />
                                      <col style="width: 110px;" />
                                    </colgroup>
                                    <thead>
                                      <tr class="bg-slate-100 text-slate-700 font-bold border-b border-slate-200">
                                        <th class="p-2 border-r border-slate-200">Ngày</th>
                                        <th class="p-2 border-r border-slate-200">Dịch vụ</th>
                                        <th class="p-2 border-r border-slate-200 text-center">Số lượng</th>
                                        <th class="p-2 border-r border-slate-200 text-right">Đơn giá</th>
                                        <th class="p-2 text-right">Thành tiền</th>
                                      </tr>
                                    </thead>
                                    <tbody>
                                      <tr 
                                        v-for="svc in getRoomDisplayServices(room)" 
                                        :key="svc.id" 
                                        class="border-b border-slate-100 hover:bg-slate-50/80 text-slate-600 font-semibold"
                                      >
                                        <td class="p-2 border-r border-slate-100">{{ formatDateVi(svc.service_date) }}</td>
                                        <td class="p-2 border-r border-slate-100 text-slate-800 font-bold">{{ svc.service_name }}</td>
                                        <td class="p-2 border-r border-slate-100 text-center text-slate-700">{{ svc.quantity !== undefined && svc.quantity !== null ? Number(svc.quantity) : 1 }}</td>
                                        <td class="p-2 border-r border-slate-100 text-right text-slate-800 font-bold">{{ (Number(svc.rate) || 0).toLocaleString('en-US') }}</td>
                                        <td class="p-2 text-right text-sky-700 font-bold">{{ (Number(svc.quantity || 1) * Number(svc.rate || 0)).toLocaleString('en-US') }}</td>
                                      </tr>
                                    </tbody>
                                  </table>
                                </div>
                              </td>
                            </tr>
                          </template>
                        </template>
                      </template>
                    </template>
                  </template>
                </template><!-- end MODE B -->
              </template><!-- end v-if="!collapsedSections.registrationStatus" -->
            </tbody>
          </table>
        </div>

        <!-- PAGE FOOTER (Redesigned with Synchronized Horizontal Scroll) -->
        <div class="page-footer shrink-0">
          <div class="footer-scroll" id="footerScroll" ref="footerScrollRef">
            <table class="footer-table" :style="{ width: tableWidth }" style="table-layout: fixed;">
              <colgroup>
                <col style="width: 35px" />
                <col style="width: 50px" />
                <col style="width: 45px" />
                <col v-for="col in columns.filter(c => c.visible)" :key="col.key" :style="{ width: getColWidthPx(col) }" />
                <col style="width: 120px" />
              </colgroup>
              <tr class="h-9">
                <td class="p-2 border-r border-[#cbd5e1] text-center w-[35px]"></td>
                <td class="p-2 border-r border-[#cbd5e1] text-center w-[50px]"></td>
                <td class="p-2 border-r border-[#cbd5e1] text-center w-[45px]"></td>
                <td v-for="col in columns.filter(c => c.visible)" 
                    :key="col.key" 
                    class="p-2 border-r border-[#cbd5e1] font-bold" 
                    :class="[col.center ? 'text-center' : '', col.right ? 'text-right' : '']"
                >
                  <template v-if="col.key === 'type'">
                    Tổng cộng: {{ roomsTotalSummary.count }}
                  </template>
                  <template v-else-if="col.key === 'price'">
                    {{ formatCurrencyInput(roomsTotalSummary.priceSum) }}
                  </template>
                  <template v-else-if="col.key === 'adults'">
                    {{ roomsTotalSummary.adults }}
                  </template>
                  <template v-else-if="col.key === 'babies'">
                    {{ roomsTotalSummary.babies }}
                  </template>
                  <template v-else-if="col.key === 'children'">
                    {{ roomsTotalSummary.children }}
                  </template>
                  <template v-else-if="col.key === 'extraBedPrice'">
                    {{ formatCurrencyInput(roomsTotalSummary.extraBed) }}
                  </template>
                  <template v-else>
                    -
                  </template>
                </td>
                <td class="p-2 text-right text-sky-800 font-extrabold text-sm sticky right-0 bg-[#cbd5e1] border-l border-[#cbd5e1] z-10 w-[120px] shadow-[-4px_0_8px_rgba(15,23,42,0.12)]">
                  {{ formatCurrencyInput(roomsTotalSummary.total) }}
                </td>
              </tr>
            </table>
          </div>
        </div>
      </div>

        <!-- ACTION DOCK (Redesigned Sidebar Dock) -->
        <aside class="dock shrink-0" id="dock">
          <div class="dock-head"><span class="dot"></span>Chức năng</div>

          <div class="dock-group">
            <div class="dock-group-label">Cập nhật đăng ký</div>
            <div class="dock-item" @click="triggerAction('Cập nhật')">
              <span class="di">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="17" height="17"><path d="M21 12a9 9 0 11-3-6.7"/><path d="M21 3v6h-6"/></svg>
              </span>
              <span class="lbl">Cập nhật</span>
            </div>
            <div class="dock-item" @click="triggerAction('Tự động gán phòng')">
              <span class="di">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="17" height="17"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M9 9h.01M15 9h.01M9 15h6"/></svg>
              </span>
              <span class="lbl">Tự động gán phòng</span>
            </div>
            <div class="dock-item" @click="triggerAction('Nâng hạng phòng')">
              <span class="di">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="17" height="17"><path d="M12 19V5"/><path d="M5 12l7-7 7 7"/></svg>
              </span>
              <span class="lbl">Nâng hạng phòng</span>
            </div>
            <div class="dock-item" @click="triggerAction('Thông tin khách hàng')">
              <span class="di">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="17" height="17"><circle cx="12" cy="8" r="4"/><path d="M4 21v-1a7 7 0 0114 0v1"/></svg>
              </span>
              <span class="lbl">Thông tin khách hàng</span>
            </div>
          </div>

          <div class="dock-group">
            <div class="dock-group-label">Giao phòng</div>
            <div 
              class="dock-item" 
              :class="{ 'opacity-50 pointer-events-none cursor-not-allowed': isCheckInDisabled }" 
              @click="!isCheckInDisabled && triggerAction('GIAO PHÒNG')"
            >
              <span class="di">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="17" height="17"><rect x="5" y="11" width="14" height="9" rx="1"/><path d="M8 11V7a4 4 0 118 0v4"/></svg>
              </span>
              <span class="lbl">Giao phòng (Check-in)</span>
            </div>
          </div>

          <div class="dock-group">
            <div class="dock-group-label">Dịch vụ bổ sung</div>
            <div class="dock-item" @click="triggerAction('Dịch vụ bổ sung')">
              <span class="di">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="17" height="17"><circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/></svg>
              </span>
              <span class="lbl">Thêm dịch vụ bổ sung</span>
            </div>
            <div class="dock-item danger" @click="triggerAction('Xóa dịch vụ bổ sung')">
              <span class="di">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="17" height="17"><path d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2m3 0l-1 14a2 2 0 01-2 2H7a2 2 0 01-2-2L4 6"/></svg>
              </span>
              <span class="lbl">Xóa dịch vụ bổ sung</span>
            </div>
          </div>

          <div class="dock-group">
            <div class="dock-group-label">Tác vụ phòng</div>
            <div class="dock-item" @click="triggerAction('Gỡ số phòng')">
              <span class="di">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="17" height="17"><path d="M18 6L6 18M6 6l12 12"/></svg>
              </span>
              <span class="lbl">Gỡ số phòng</span>
            </div>
            <div class="dock-item danger" @click="triggerAction('Hủy phòng')">
              <span class="di">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="17" height="17"><circle cx="12" cy="12" r="9"/><path d="M9 15l6-6M9 9l6 6"/></svg>
              </span>
              <span class="lbl">Hủy phòng</span>
            </div>
          </div>

          <div class="dock-group">
            <div class="dock-group-label">Chuyển phòng</div>
            <div class="dock-item" @click="triggerAction('Khóa chuyển phòng')">
              <span class="di">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="17" height="17"><rect x="5" y="11" width="14" height="9" rx="1"/><path d="M8 11V7a4 4 0 118 0v4"/></svg>
              </span>
              <span class="lbl">Khóa chuyển phòng</span>
            </div>
            <div class="dock-item" @click="triggerAction('Mở chuyển phòng')">
              <span class="di">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="17" height="17"><rect x="5" y="11" width="14" height="9" rx="1"/><path d="M8 11V9a4 4 0 018 0"/></svg>
              </span>
              <span class="lbl">Mở chuyển phòng</span>
            </div>
          </div>

          <div class="dock-group">
            <div class="dock-group-label">Xuất / In ấn</div>
            <div class="dock-item" @click="triggerAction('Xuất Excel')">
              <span class="di">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="17" height="17"><path d="M8 3H4v18h16V9l-6-6H8z"/><path d="M8 13l2 2 4-4"/></svg>
              </span>
              <span class="lbl">Xuất Excel</span>
            </div>
            <div class="dock-item expandable" @click="isSubListOpen = !isSubListOpen">
              <span class="di">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="17" height="17"><path d="M6 9V2h12v7"/><rect x="6" y="14" width="12" height="8"/><path d="M6 14H4a2 2 0 01-2-2v-3a2 2 0 012-2h16a2 2 0 012 2v3a2 2 0 01-2 2h-2"/></svg>
              </span>
              <span class="lbl">In phiếu đăng ký khách<br><span class="sub-current">Đang chọn: {{ isPrintPrice ? 'Hiện giá' : 'Không hiện giá' }}</span></span>
              <span class="chevron" :class="{ open: isSubListOpen }">›</span>
            </div>
            <div class="sub-list" :class="{ open: isSubListOpen }">
              <div class="sub-item" :class="{ selected: isPrintPrice }" @click.stop="isPrintPrice = true"><span class="sub-dot"></span>Hiện giá</div>
              <div class="sub-item" :class="{ selected: !isPrintPrice }" @click.stop="isPrintPrice = false"><span class="sub-dot"></span>Không hiện giá</div>
            </div>
            <div class="dock-item" @click="triggerAction('In hoá đơn tạm')">
              <span class="di">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="17" height="17"><path d="M6 9V2h12v7"/><rect x="6" y="14" width="12" height="8"/><path d="M6 14H4a2 2 0 01-2-2v-3a2 2 0 012-2h16a2 2 0 012 2v3a2 2 0 01-2 2h-2"/></svg>
              </span>
              <span class="lbl">In hoá đơn tạm</span>
            </div>
          </div>


        </aside>
      </div>



    </div>

    <!-- GLOBAL SYSTEM SEARCH OVERLAY -->
    <Teleport to="body">
      <SystemSearchModal 
        v-model:show="isGlobalSearchOpen" 
        :registrationStatuses="registrationStatuses" 
        :activeTab="activeTab" 
        :systemDate="systemDate"
        @select-booking="handleGlobalSearchResultClick" 
      />
    </Teleport>

    <!-- MOCKUP CREATE REGISTRATION MODAL (Image 2 Match) -->
    <Teleport to="body">
      <div 
        v-if="isModalOpen" 
        class="fixed inset-0 bg-black/50 z-[9999] flex items-center justify-center p-4 backdrop-blur-xs animate-in"
      >
        <div 
          class="bg-white rounded-xl shadow-2xl w-full max-w-[1400px] overflow-hidden border border-gray-300 flex flex-col max-h-[90vh]"
          :style="{ transform: `translate(${modalPos.x}px, ${modalPos.y}px)` }"
        >
          
          <!-- MODAL HEADER (FINAL.html Style) -->
          <div 
            class="bg-[#243c5a] text-white flex justify-between items-center px-4 py-2 shrink-0 cursor-move select-none"
            @mousedown="startDragModal"
          >
            <div class="flex items-center space-x-2 font-semibold text-sm">
                <i class="fa-solid fa-file-lines text-blue-300"></i>
                <span>{{ isEditModal ? 'THÔNG TIN ĐĂNG KÝ' : 'TẠO ĐĂNG KÝ' }}</span>
            </div>
            <div class="flex items-center space-x-3">
                <div class="relative">
                    <button 
                      type="button"
                      @click.stop="isColorPickerOpen = !isColorPickerOpen"
                      class="flex items-center space-x-1.5 bg-white/10 hover:bg-white/20 border border-white/20 rounded-lg h-[26px] px-2 shadow-inner cursor-pointer select-none transition"
                    >
                        <span class="text-xs font-medium text-gray-200">Màu BK</span>
                        <span class="w-3.5 h-3.5 rounded-full inline-block border border-white/40 shadow-xs" :style="{ backgroundColor: modalForm.color || '#97D5FF' }"></span>
                    </button>

                    <!-- COLOR PICKER POPOVER (Match Image 1) -->
                    <div 
                      v-if="isColorPickerOpen" 
                      class="absolute left-0 top-full mt-1.5 bg-white border border-gray-200 rounded-lg shadow-2xl p-2.5 z-[99999] w-[232px] select-none text-gray-800 animate-in"
                      @click.stop
                    >
                        <div class="grid grid-cols-9 gap-1 mb-2">
                            <button
                              v-for="c in bkColorList"
                              :key="c.hex"
                              type="button"
                              @click="selectBkColor(c.hex)"
                              class="w-5 h-5 rounded-xs relative flex items-center justify-center cursor-pointer border border-black/10 hover:scale-110 transition-transform p-0"
                              :style="{ backgroundColor: c.hex }"
                              :title="c.hex"
                            >
                                <span v-if="modalForm.color && modalForm.color.toUpperCase() === c.hex.toUpperCase()" class="w-1.5 h-1.5 rounded-full bg-black/80 inline-block"></span>
                            </button>
                        </div>

                        <!-- FOOTER: HEX + RGB -->
                        <div class="flex items-center justify-between pt-1.5 border-t border-gray-200 text-[11px] font-sans text-gray-600">
                            <div class="flex items-center space-x-1">
                                <span class="w-3 h-3 rounded-xs border border-gray-300 inline-block" :style="{ backgroundColor: modalForm.color || '#97D5FF' }"></span>
                                <span class="font-bold text-gray-800 text-[10px]">{{ (modalForm.color || '#97D5FF').toUpperCase() }}</span>
                            </div>
                            <div class="text-[10px] font-medium text-gray-500 flex space-x-1">
                                <span>R <span class="font-bold text-gray-800">{{ currentColorRgb.r }}</span></span>
                                <span>G <span class="font-bold text-gray-800">{{ currentColorRgb.g }}</span></span>
                                <span>B <span class="font-bold text-gray-800">{{ currentColorRgb.b }}</span></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center space-x-1.5">
                    <span class="text-xs font-medium text-gray-300">Tiền phòng gửi Master</span>
                    <div class="relative inline-block w-8 h-4 align-middle select-none transition duration-200 ease-in">
                        <input type="checkbox" v-model="modalForm.isGit" id="git-toggle" class="sr-only peer"/>
                        <label for="git-toggle" class="block overflow-hidden h-4 rounded-full bg-gray-400 peer-checked:bg-blue-500 cursor-pointer transition-colors duration-200"></label>
                        <span class="absolute block w-3 h-3 rounded-full bg-white top-0.5 left-0.5 peer-checked:translate-x-4 transition-transform duration-200 pointer-events-none"></span>
                    </div>
                </div>
                <div class="flex items-center space-x-2 text-gray-300">
                    <button class="flex items-center space-x-1 text-xs bg-white/10 hover:bg-white/20 px-2 py-0.5 rounded-md transition text-gray-200 hover:text-white font-medium shadow-sm cursor-pointer border-none bg-transparent">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                        <span>Lịch sử booking</span>
                    </button>
                    <button class="hover:text-white cursor-pointer border-none bg-transparent"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                    <button @click="closeModal" class="hover:text-white ml-1 bg-red-500/20 px-1.5 py-0.5 rounded-md cursor-pointer border-none"><i class="fa-solid fa-xmark text-red-400"></i></button>
                </div>
            </div>
          </div>

          <!-- SECOND ACTION STRIP (GENERAL DETAILS) -->
          <div class="px-4 py-2 border-b border-gray-200 flex flex-wrap items-end justify-between gap-2 bg-gray-50/50 shrink-0">
            <div class="flex flex-col">
                <div class="text-xs text-gray-500 font-semibold mb-0.5">Mã booking</div>
                <div class="font-bold text-sm text-gray-900 h-[32px] flex items-center px-1">{{ modalForm.bookingCode || ((hotelSettings?.prefix_booking_id || 'GAL') + ' (Tự động)') }}</div>
            </div>
            <div class="flex flex-col flex-1 min-w-[140px]">
                <div class="text-xs text-gray-500 font-semibold mb-0.5">Tên đăng ký <span class="text-red-500">*</span></div>
                <input 
                  type="text" 
                  v-model="modalForm.bookingName" 
                  placeholder="Nhập tên đăng ký..."
                  class="font-bold text-sm text-black border border-blue-200 rounded-xl px-3 h-[32px] flex items-center bg-blue-50/70 shadow-sm w-full outline-none focus:border-blue-400 focus:bg-blue-50/90 uppercase"
                />
            </div>
            <div class="flex flex-col">
                <div class="text-xs text-gray-500 font-semibold mb-0.5">Mã tham chiếu</div>
                <input 
                  type="text" 
                  v-model="modalForm.externalBookingCode" 
                  placeholder="Mã tham chiếu" 
                  class="border border-gray-300 rounded-xl px-3 text-xs focus:outline-none focus:border-blue-500 w-28 h-[32px] shadow-sm font-semibold"
                />
            </div>
            <div class="flex flex-col shrink-0 w-[230px]">
                <div class="text-xs text-gray-500 font-semibold mb-0.5">Ngày lưu trú</div>
                <div class="flex items-center space-x-2 text-xs font-semibold text-gray-800 border border-gray-300 rounded-xl px-2 h-[32px] bg-white shadow-sm w-full">
                    <i class="fa-regular fa-calendar text-blue-500 shrink-0"></i>
                    <input 
                      type="date" 
                      v-model="modalForm.checkIn" 
                      :min="systemDate"
                      @change="handleDateChange"
                      @click="$event.target.showPicker && $event.target.showPicker()"
                      class="date-span-input checkin-date-input"
                    />
                    <i class="fa-solid fa-arrow-right text-gray-400 text-xs shrink-0"></i>
                    <input 
                      type="date" 
                      v-model="modalForm.checkOut" 
                      :min="modalForm.checkIn"
                      @change="handleDateChange"
                      @click="$event.target.showPicker && $event.target.showPicker()"
                      class="date-span-input checkout-date-input"
                    />
                </div>
            </div>
            <div class="flex flex-col w-[65px]">
                <div class="text-xs text-gray-500 font-semibold mb-0.5">Đêm</div>
                <div class="font-bold text-sm text-gray-900 border border-gray-300 rounded-xl h-[32px] flex items-center justify-between px-2 bg-white shadow-sm relative">
                    <input 
                      type="number" 
                      v-model="modalForm.nights" 
                      @input="handleNightsChange"
                      min="1"
                      class="border-none bg-transparent text-center w-8 text-sm font-bold focus:outline-none focus:ring-0 p-0 w-8"
                    />
                    <div class="flex flex-col select-none">
                        <button type="button" @click="incrementNights" class="text-slate-400 hover:text-blue-500 text-[8px] leading-none px-1 border-none bg-transparent cursor-pointer"><i class="fa-solid fa-chevron-up"></i></button>
                        <button type="button" @click="decrementNights" class="text-slate-400 hover:text-blue-500 text-[8px] leading-none px-1 border-none bg-transparent cursor-pointer"><i class="fa-solid fa-chevron-down"></i></button>
                    </div>
                </div>
            </div>
            <div class="flex flex-col">
                <div class="text-xs text-gray-500 font-semibold mb-0.5">Tình trạng đăng ký <span class="text-red-500">*</span></div>
                <div class="relative w-full flex items-center">
                    <select 
                      v-model="modalForm.registrationStatusId"
                      @change="handleConfirmDateCalculation"
                      class="w-full bg-blue-50/70 border border-blue-200 text-black rounded-xl pl-3 pr-8 text-xs focus:outline-none focus:border-blue-400 appearance-none font-bold h-[32px] shadow-sm cursor-pointer"
                    >
                        <option :value="null" disabled>— Chọn —</option>
                        <option v-for="rs in registrationStatuses.filter(s => !s.is_hidden || s.id === modalForm.registrationStatusId)" :key="rs.id" :value="rs.id">{{ rs.name }}</option>
                    </select>
                    <button 
                      v-if="modalForm.registrationStatusId"
                      type="button" 
                      @click.stop="modalForm.registrationStatusId = null; handleConfirmDateCalculation()"
                      class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-500 bg-transparent border-none p-0 cursor-pointer text-xs select-none"
                      style="z-index: 10;"
                      title="Xóa chọn"
                    >
                      <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </div>
            <div class="flex flex-col shrink-0 w-[150px]">
                <div class="text-xs text-gray-500 font-semibold mb-0.5">Ngày xác nhận</div>
                <div class="flex items-center space-x-2 text-xs text-gray-800 border border-gray-300 rounded-xl px-2 h-[32px] bg-white shadow-sm relative w-full">
                    <input 
                      type="date" 
                      v-model="modalForm.confirmDate" 
                      @click="$event.target.showPicker && $event.target.showPicker()"
                      class="date-span-input w-full"
                    />
                    <i class="fa-regular fa-calendar text-gray-400 pointer-events-none shrink-0"></i>
                    <button @click="copyConfirmDate" type="button" class="text-gray-400 hover:text-gray-600 cursor-pointer border-none bg-transparent p-0 shrink-0">
                      <i class="fa-regular fa-copy"></i>
                    </button>
                </div>
            </div>
          </div>

          <!-- TABS STRIP -->
          <div class="flex px-4 border-b border-gray-200 bg-white shrink-0">
              <button 
                @click="modalSubTab = 'info'"
                class="px-3 py-2 font-bold border-b-2 flex items-center space-x-1.5 text-xs cursor-pointer border-none bg-transparent"
                :class="modalSubTab === 'info' ? 'text-blue-600 border-blue-600' : 'text-gray-500 hover:text-gray-700 border-transparent'"
              >
                  <i class="fa-solid fa-address-card"></i>
                  <span>Thông tin chung</span>
              </button>
              <button 
                @click="modalSubTab = 'shuttle'"
                class="px-3 py-2 font-bold border-b-2 flex items-center space-x-1.5 text-xs cursor-pointer border-none bg-transparent"
                :class="modalSubTab === 'shuttle' ? 'text-blue-600 border-blue-600' : 'text-gray-500 hover:text-gray-700 border-transparent'"
              >
                  <i class="fa-solid fa-van-shuttle"></i>
                  <span>Thông tin đưa đón</span>
              </button>
              <button 
                @click="modalSubTab = 'rooms'"
                class="px-3 py-2 font-bold border-b-2 flex items-center space-x-1.5 text-xs cursor-pointer border-none bg-transparent"
                :class="modalSubTab === 'rooms' ? 'text-blue-600 border-blue-600' : 'text-gray-500 hover:text-gray-700 border-transparent'"
              >
                  <i class="fa-solid fa-bed"></i>
                  <span>Lấy phòng</span>
              </button>
          </div>

          <!-- MODAL CONTENT SCROLL -->
          <div class="flex-1 p-3 overflow-y-auto flex flex-col gap-3 bg-gray-50">
            
            <!-- Tab 1: Thông tin chung -->
            <div v-if="modalSubTab === 'info'" class="flex flex-col space-y-2.5 w-full">
              <!-- Section 1: Công ty -->
              <div class="bg-white p-3 rounded-xl border border-gray-200 shadow-xs">
                  <h3 class="font-bold text-gray-800 mb-1.5 flex items-center text-xs uppercase tracking-wider">
                      <div class="w-1 h-3 bg-blue-500 rounded-full mr-1.5"></div>
                      Công ty
                  </h3>
                  <div class="flex flex-wrap lg:flex-nowrap gap-2 items-end">
                      <div class="flex-1 min-w-[160px] flex flex-col gap-0.5">
                          <label class="block text-[11px] text-gray-500 font-bold">Công ty <span class="text-red-500">*</span></label>
                          <div class="relative w-full flex items-center group">
                              <select 
                                v-model="modalForm.companyId"
                                @change="handleCompanyChange"
                                class="w-full border border-blue-200 rounded-xl pl-2.5 pr-8 py-1 focus:outline-none focus:ring-1 focus:ring-blue-400 text-xs font-bold bg-blue-50/70 text-black h-[32px] cursor-pointer appearance-none"
                              >
                                  <option :value="null" disabled>— Chọn công ty —</option>
                                  <option v-for="c in companies" :key="c.id" :value="c.id">[{{ c.code }}] {{ c.name }}</option>
                              </select>
                              <span 
                                class="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none transition-opacity duration-150 animate-none"
                                :class="{ 'group-hover:opacity-0': modalForm.companyId }"
                              >
                                  <i class="fa-solid fa-chevron-down text-[10px]"></i>
                              </span>
                              <button 
                                v-if="modalForm.companyId"
                                type="button"
                                @click.stop="modalForm.companyId = null; handleCompanyChange()"
                                class="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-500 bg-transparent border-none p-0 cursor-pointer text-xs select-none opacity-0 group-hover:opacity-100 transition-opacity duration-150"
                                style="z-index: 10;"
                                title="Xóa chọn"
                              >
                                  <i class="fa-solid fa-xmark"></i>
                              </button>
                          </div>
                      </div>
                      <div class="flex-1 min-w-[130px] flex flex-col gap-0.5">
                          <label class="block text-[11px] text-gray-500 font-bold">Thị trường</label>
                          <div class="relative w-full flex items-center group">
                              <select 
                                v-model="modalForm.marketId"
                                class="w-full border border-blue-200 rounded-xl pl-2.5 pr-8 py-1 focus:outline-none focus:ring-1 focus:ring-blue-400 text-xs bg-blue-50/70 text-black h-[32px] cursor-pointer font-bold appearance-none"
                              >
                                  <option :value="null" disabled>— Chọn thị trường —</option>
                                  <option v-for="m in markets" :key="m.id" :value="m.id">{{ m.name }}</option>
                              </select>
                              <span 
                                class="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none transition-opacity duration-150"
                                :class="{ 'group-hover:opacity-0': modalForm.marketId }"
                              >
                                  <i class="fa-solid fa-chevron-down text-[10px]"></i>
                              </span>
                              <button 
                                v-if="modalForm.marketId"
                                type="button"
                                @click.stop="modalForm.marketId = null"
                                class="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-500 bg-transparent border-none p-0 cursor-pointer text-xs select-none opacity-0 group-hover:opacity-100 transition-opacity duration-150"
                                style="z-index: 10;"
                                title="Xóa chọn"
                              >
                                  <i class="fa-solid fa-xmark"></i>
                              </button>
                          </div>
                      </div>
                      <div class="flex-1 min-w-[130px] flex flex-col gap-0.5">
                          <label class="block text-[11px] text-gray-500 font-bold">Nguồn khách</label>
                          <div class="relative w-full flex items-center group">
                              <select 
                                v-model="modalForm.customerSourceId"
                                class="w-full border border-blue-200 rounded-xl pl-2.5 pr-8 py-1 focus:outline-none focus:ring-1 focus:ring-blue-400 text-xs bg-blue-50/70 text-black h-[32px] cursor-pointer font-bold appearance-none"
                              >
                                  <option :value="null" disabled>— Chọn nguồn khách —</option>
                                  <option v-for="s in customerSources" :key="s.id" :value="s.id">{{ s.name }}</option>
                              </select>
                              <span 
                                class="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none transition-opacity duration-150"
                                :class="{ 'group-hover:opacity-0': modalForm.customerSourceId }"
                              >
                                  <i class="fa-solid fa-chevron-down text-[10px]"></i>
                              </span>
                              <button 
                                v-if="modalForm.customerSourceId"
                                type="button"
                                @click.stop="modalForm.customerSourceId = null"
                                class="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-500 bg-transparent border-none p-0 cursor-pointer text-xs select-none opacity-0 group-hover:opacity-100 transition-opacity duration-150"
                                style="z-index: 10;"
                                title="Xóa chọn"
                              >
                                  <i class="fa-solid fa-xmark"></i>
                              </button>
                          </div>
                      </div>
                      <div class="flex-1 min-w-[130px] flex flex-col gap-0.5">
                          <label class="block text-[11px] text-gray-500 font-bold">Người bán</label>
                          <div class="relative w-full flex items-center group">
                              <select 
                                v-model="modalForm.salesPerson"
                                class="w-full border border-gray-300 rounded-xl pl-2.5 pr-8 py-1 focus:outline-none focus:border-blue-500 text-xs bg-white h-[32px] font-bold appearance-none cursor-pointer"
                              >
                                  <option value="" disabled>— Chọn người bán —</option>
                                  <option v-for="u in users" :key="u.id" :value="u.username || u.name">{{ u.name || u.username }}</option>
                              </select>
                              <span 
                                class="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none transition-opacity duration-150"
                                :class="{ 'group-hover:opacity-0': modalForm.salesPerson }"
                              >
                                  <i class="fa-solid fa-chevron-down text-[10px]"></i>
                              </span>
                              <button 
                                v-if="modalForm.salesPerson"
                                type="button"
                                @click.stop="modalForm.salesPerson = ''"
                                class="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-500 bg-transparent border-none p-0 cursor-pointer text-xs select-none opacity-0 group-hover:opacity-100 transition-opacity duration-150"
                                style="z-index: 10;"
                                title="Xóa chọn"
                              >
                                  <i class="fa-solid fa-xmark"></i>
                              </button>
                          </div>
                      </div>
                      <div class="flex-1 min-w-[150px] flex flex-col gap-0.5">
                          <label class="block text-[11px] text-gray-500 font-bold">Phương thức thanh toán</label>
                          <div class="relative w-full flex items-center group">
                              <select 
                                v-model="modalForm.paymentMethodId"
                                class="w-full border border-gray-300 rounded-xl pl-2.5 pr-8 py-1 focus:outline-none focus:border-blue-500 text-xs bg-white h-[32px] font-bold appearance-none cursor-pointer"
                              >
                                  <option :value="null" disabled>Chọn phương thức...</option>
                                  <option v-for="pm in paymentMethods" :key="pm.id" :value="pm.id">{{ pm.name }}</option>
                              </select>
                              <span 
                                class="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none transition-opacity duration-150"
                                :class="{ 'group-hover:opacity-0': modalForm.paymentMethodId }"
                              >
                                  <i class="fa-solid fa-chevron-down text-[10px]"></i>
                              </span>
                              <button 
                                v-if="modalForm.paymentMethodId"
                                type="button"
                                @click.stop="modalForm.paymentMethodId = null"
                                class="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-500 bg-transparent border-none p-0 cursor-pointer text-xs select-none opacity-0 group-hover:opacity-100 transition-opacity duration-150"
                                style="z-index: 10;"
                                title="Xóa chọn"
                              >
                                  <i class="fa-solid fa-xmark"></i>
                              </button>
                          </div>
                      </div>
                  </div>
              </div>

              <!-- Section 2: Người liên hệ -->
              <div class="bg-white p-3 rounded-xl border border-gray-200 shadow-xs">
                  <h3 class="font-bold text-gray-800 mb-1.5 flex items-center text-xs uppercase tracking-wider">
                      <div class="w-1 h-3 bg-purple-500 rounded-full mr-1.5"></div>
                      Người liên hệ
                  </h3>
                  <div class="flex flex-wrap md:flex-nowrap gap-2 items-end">
                      <div class="flex-2 min-w-[260px] flex flex-col gap-0.5">
                          <label class="block text-[11px] text-gray-500 font-bold">Người đặt phòng</label>
                          <div class="flex space-x-1.5 h-[32px]">
                              <div class="relative flex-1 flex items-center group">
                                  <select 
                                    v-model="modalForm.bookerId"
                                    @change="handleBookerChange"
                                    class="w-full border border-gray-300 rounded-xl pl-2.5 pr-8 py-1 focus:outline-none focus:border-blue-500 text-xs bg-white font-bold h-[32px] cursor-pointer appearance-none"
                                  >
                                      <option :value="null" disabled>Chọn người đặt...</option>
                                      <option v-for="b in bookers" :key="b.id" :value="b.id">{{ b.name }}</option>
                                  </select>
                                  <span 
                                    class="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none transition-opacity duration-150"
                                    :class="{ 'group-hover:opacity-0': modalForm.bookerId }"
                                  >
                                      <i class="fa-solid fa-chevron-down text-[10px]"></i>
                                  </span>
                                  <button 
                                    v-if="modalForm.bookerId"
                                    type="button"
                                    @click.stop="modalForm.bookerId = null; handleBookerChange()"
                                    class="absolute right-3.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-red-500 bg-transparent border-none p-0 cursor-pointer text-xs select-none opacity-0 group-hover:opacity-100 transition-opacity duration-150"
                                    style="z-index: 10;"
                                    title="Xóa chọn"
                                  >
                                      <i class="fa-solid fa-xmark"></i>
                                  </button>
                              </div>
                              <button type="button" class="bg-gray-100 border border-gray-300 px-2.5 py-1 rounded-xl text-gray-600 hover:bg-gray-200 transition cursor-pointer flex-shrink-0">
                                  <i class="fa-solid fa-user-plus text-xs"></i>
                              </button>
                          </div>
                      </div>
                      <div class="flex-1 min-w-[160px] flex flex-col gap-0.5">
                          <label class="block text-[11px] text-gray-500 font-bold">Số điện thoại</label>
                          <input 
                            type="text" 
                            v-model="modalForm.contactPhone"
                            placeholder="Số điện thoại" 
                            class="w-full border border-gray-300 rounded-xl px-2.5 py-1 focus:outline-none focus:border-blue-500 text-xs h-[32px] font-bold"
                          >
                      </div>
                      <div class="flex-1 min-w-[160px] flex flex-col gap-0.5">
                          <label class="block text-[11px] text-gray-500 font-bold">Email</label>
                          <input 
                            type="email" 
                            v-model="modalForm.contactEmail"
                            placeholder="Email" 
                            class="w-full border border-gray-300 rounded-xl px-2.5 py-1 focus:outline-none focus:border-blue-500 text-xs h-[32px] font-bold"
                          >
                      </div>
                  </div>
              </div>

              <!-- Section 3: Đặt cọc -->
              <div class="bg-white p-3 rounded-xl border border-gray-200 shadow-xs relative">
                  <div class="flex justify-between items-center mb-1.5">
                      <h3 class="font-bold text-gray-800 flex items-center text-xs uppercase tracking-wider">
                          <div class="w-1 h-3 bg-green-500 rounded-full mr-1.5"></div>
                          Đặt cọc
                      </h3>
                      <button 
                        @click="openDepositModal" 
                        :disabled="!modalForm.dbId"
                        type="button" 
                        class="rounded-md px-2 py-0.5 text-[11px] transition flex items-center gap-1 shadow-xs"
                        :class="modalForm.dbId ? 'text-gray-600 bg-gray-100 hover:bg-gray-200 cursor-pointer' : 'text-gray-400 bg-gray-100 cursor-not-allowed opacity-60'"
                      >
                          <i class="fa-solid fa-plus"></i> Thêm cọc
                      </button>
                  </div>
                  <div class="bg-gray-50 border border-gray-200 rounded-xl p-2 flex flex-wrap md:flex-nowrap gap-4 items-center shadow-inner">
                      <!-- Số tiền first -->
                      <div class="text-xs text-gray-800 flex items-center font-bold">
                          <span class="text-[11px] text-gray-400 font-medium mr-1.5">Số tiền:</span>
                          <input 
                            type="text" 
                            readonly
                            :value="formatCurrencyInput(modalForm.paymentValue)"
                            class="border border-gray-200 rounded-lg px-2.5 py-0.5 text-xs font-black text-slate-500 focus:outline-none w-28 bg-slate-100/50 mr-1.5 text-right shadow-inner cursor-not-allowed"
                          />
                      </div>
                      
                      <!-- Only show Date and Note if booking has deposits -->
                      <template v-if="hasActiveDeposits">
                          <div class="text-xs font-semibold text-gray-800 flex items-center">
                              <span class="text-[11px] text-gray-400 font-medium mr-1.5">Ngày:</span> {{ firstDepositDate }}
                          </div>
                          <div class="text-xs text-gray-800 flex items-center font-semibold">
                              <i class="fa-solid fa-money-bill-wave text-green-600 mr-1.5 text-xs"></i> ({{ firstDepositNote }})
                          </div>
                          <div class="font-bold text-blue-600 bg-blue-50 px-2 py-0.5 rounded-lg text-xs md:ml-auto border border-blue-100">
                              {{ firstDepositMethodName }}
                          </div>
                      </template>
                  </div>
              </div>

              <!-- Section 4: Ghi chú -->
              <div class="bg-white p-3 rounded-xl border border-gray-200 shadow-xs flex flex-col">
                  <h3 class="font-bold text-gray-800 mb-1.5 flex items-center text-xs uppercase tracking-wider">
                      <div class="w-1 h-3 bg-yellow-400 rounded-full mr-1.5"></div>
                      Ghi chú
                  </h3>
                  <textarea 
                    id="booking-note-textarea"
                    v-model="modalForm.note"
                    @input="autoResizeTextarea"
                    placeholder="Nhập ghi chú tại đây..." 
                    class="w-full border border-gray-300 rounded-xl p-2 focus:outline-none focus:border-blue-500 text-xs resize-none min-h-[54px] shadow-inner bg-gray-50/30 font-semibold overflow-hidden"
                  ></textarea>
              </div>
            </div>

            <!-- Tab 2: Thông tin đưa đón -->
            <div v-else-if="modalSubTab === 'shuttle'" class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm flex flex-col gap-4 animate-in">
              <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs table-auto">
                  <thead class="bg-slate-50 text-slate-500 font-semibold border-y border-slate-200">
                    <tr>
                      <th class="py-2 px-2 text-center w-[8%] font-semibold text-[11px]">Đón/Đưa</th>
                      <th class="py-2 px-2 text-center w-[12%] font-semibold text-[11px]">Phương tiện</th>
                      <th class="py-2 px-2 text-center w-[11%] font-semibold text-[11px]">Mã hiệu / Biển số</th>
                      <th class="py-2 px-2 text-center w-[12%] font-semibold text-[11px]">Ngày đón/đưa</th>
                      <th class="py-2 px-2 text-center w-[8%] font-semibold text-[11px]">Giờ</th>
                      <th class="py-2 px-2 text-center w-[10%] font-semibold text-[11px]">Hiện giá</th>
                      <th class="py-2 px-2 text-center w-[15%] font-semibold text-[11px]">Địa điểm</th>
                      <th class="py-2 px-2 text-center w-[18%] font-semibold text-[11px]">Ghi chú</th>
                      <th class="py-2 px-2 w-[6%] text-center font-semibold text-[11px]">Hành động</th>
                    </tr>
                  </thead>
                  <tbody class="text-[11px] text-slate-700 font-medium">
                    <tr v-for="(row, idx) in modalForm.shuttleInfo" :key="row.id" class="border-b border-slate-200 hover:bg-slate-50/50 transition-colors">
                      
                      <!-- Đón/Đưa -->
                      <td class="py-2 px-2">
                        <select v-model="row.type" class="w-full border border-slate-300 rounded-md h-[30px] px-2 text-slate-700 focus:outline-none bg-white cursor-pointer shadow-sm text-[11px] font-bold">
                          <option value="Đón">Đón</option>
                          <option value="Đưa">Đưa</option>
                        </select>
                      </td>

                      <!-- Phương tiện -->
                      <td class="py-2 px-2">
                        <select v-model="row.vehicle" class="w-full border border-slate-300 rounded-md h-[30px] px-2 text-slate-700 focus:outline-none bg-white cursor-pointer shadow-sm text-[11px]">
                          <option value="Select Value" disabled>— Chọn xe —</option>
                          <option value="4 Seater car">4 Seater car</option>
                          <option value="7 Seater car">7 Seater car</option>
                          <option value="16 Seater car">16 Seater car</option>
                          <option value="Bus">Bus</option>
                          <option value="Khác">Khác</option>
                        </select>
                      </td>

                      <!-- Mã hiệu / Biển số -->
                      <td class="py-2 px-2">
                        <input type="text" v-model="row.code" placeholder="Mã hiệu / Biển số" class="w-full border border-slate-300 rounded-md h-[30px] px-2 text-slate-700 focus:outline-none bg-white shadow-sm text-[11px] font-bold" />
                      </td>

                      <!-- Ngày -->
                      <td class="py-2 px-2">
                        <div class="flex items-center justify-between border border-slate-300 rounded-md px-2 bg-white h-[30px] shadow-sm">
                          <input type="date" v-model="row.date" @click="$event.target.showPicker && $event.target.showPicker()" class="border-none bg-transparent p-0 text-[11px] font-bold text-slate-700 w-full focus:outline-none cursor-pointer date-span-input" />
                          <button @click.prevent="row.date = modalForm.checkIn" class="p-0.5 hover:bg-slate-100 rounded text-slate-400 border-none bg-transparent cursor-pointer ml-1" title="Sao chép ngày check-in">
                            <i class="fa-regular fa-calendar-days text-[11px]"></i>
                          </button>
                        </div>
                      </td>

                      <!-- Giờ -->
                      <td class="py-2 px-2">
                        <input type="time" v-model="row.time" class="border border-slate-300 rounded-md px-2 text-[11px] font-bold text-slate-700 w-full focus:outline-none bg-white cursor-pointer shadow-sm h-[30px]" />
                      </td>

                      <!-- Hiện giá -->
                      <td class="py-2 px-2">
                        <div class="relative w-full border border-slate-300 rounded-md h-[30px] bg-white shadow-sm flex items-center">
                          <input type="text" :value="formatCurrencyInput(row.price)" @input="e => row.price = cleanCurrencyValue(e.target.value)" @focus="e => { if (cleanCurrencyValue(e.target.value) === 0) e.target.value = ''; e.target.select() }" class="w-full text-right pl-2 pr-5 focus:outline-none text-[11px] bg-transparent border-none outline-none font-bold text-slate-800">
                          <div class="flex flex-col text-slate-800 absolute right-1.5 top-0 bottom-0 justify-center items-center w-3 select-none">
                            <button @click.prevent="row.price = (Number(row.price) || 0) + 10000" class="hover:text-black leading-[0.6] outline-none border-none bg-transparent cursor-pointer p-0"><i class="fa-solid fa-caret-up text-[9px]"></i></button>
                            <button @click.prevent="row.price >= 10000 ? row.price = (Number(row.price) || 0) - 10000 : null" class="hover:text-black leading-[0.6] outline-none border-none bg-transparent cursor-pointer p-0"><i class="fa-solid fa-caret-down text-[9px]"></i></button>
                          </div>
                        </div>
                      </td>

                      <!-- Địa điểm -->
                      <td class="py-2 px-2">
                        <select v-model="row.location" class="w-full border border-slate-300 rounded-md h-[30px] px-2 text-slate-700 focus:outline-none bg-white cursor-pointer shadow-sm text-[11px]">
                          <option value="" disabled>— Chọn địa điểm —</option>
                          <option value="Sân bay Cam Ranh">Sân bay Cam Ranh</option>
                          <option value="Ga Nha Trang">Ga Nha Trang</option>
                          <option value="Bến xe Nha Trang">Bến xe Nha Trang</option>
                          <option value="Khách sạn">Khách sạn</option>
                          <option value="Khác">Khác</option>
                        </select>
                      </td>

                      <!-- Ghi chú -->
                      <td class="py-2 px-2">
                        <input type="text" v-model="row.note" placeholder="Nhập ghi chú..." class="w-full border border-slate-300 rounded-md h-[30px] px-2 text-slate-700 focus:outline-none bg-white shadow-sm text-[11px]" />
                      </td>

                      <!-- Hành động (Xóa) -->
                      <td class="py-2 px-2 text-center">
                        <button @click.prevent="removeShuttleRow(idx)" class="text-rose-500 hover:text-rose-700 hover:underline border-none bg-transparent cursor-pointer font-bold text-xs">
                          <i class="fa-regular fa-trash-can mr-1"></i>Xóa
                        </button>
                      </td>
                    </tr>
                    <tr v-if="modalForm.shuttleInfo.length === 0">
                      <td colspan="9" class="py-8 text-center text-slate-400 font-bold text-xs bg-slate-50/10">
                        Chưa có lịch đưa đón nào được thêm. Bấm nút (+) bên dưới để thêm mới.
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <!-- Add Row Button -->
              <div class="flex">
                <button
                  @click.prevent="addShuttleRow"
                  class="w-7 h-7 bg-[#006bdb]/10 hover:bg-[#006bdb]/20 text-[#006bdb] rounded-full flex items-center justify-center cursor-pointer border-none transition-all active:scale-90"
                  title="Thêm dòng mới"
                >
                  <span class="font-black text-base">+</span>
                </button>
              </div>
            </div>

            <!-- Tab 3: Lấy phòng -->
            <div v-else-if="modalSubTab === 'rooms'" class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm flex flex-col gap-4 relative animate-in">
              
              <!-- Column Selector Icon at Top Right -->
              <div class="flex justify-end items-center relative z-20 shrink-0">
                <button
                  id="column-selector-toggle"
                  @click="showColumnSelector = !showColumnSelector"
                  class="p-1.5 rounded-lg hover:bg-slate-100 text-slate-500 border border-slate-200 bg-white shadow-xs cursor-pointer transition-colors"
                  title="Cấu hình hiển thị cột"
                >
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75" />
                  </svg>
                </button>

                <!-- Column Toggle Dropdown -->
                <div v-if="showColumnSelector" id="column-selector-container" class="absolute right-0 top-10 bg-white border border-slate-200 rounded-lg shadow-xl p-3 w-56 flex flex-col gap-2 z-30 select-none animate-in">
                  <span class="text-xs font-black text-slate-400 uppercase tracking-wider border-b pb-1">Cột hiển thị</span>
                  
                  <div class="flex flex-col gap-1.5 overflow-y-auto max-h-72 text-sm font-bold text-slate-700">
                    <label class="flex items-center gap-2 cursor-pointer hover:bg-slate-50 p-1 rounded">
                      <input type="checkbox" v-model="visibleColumns.roomType" class="rounded text-sky-500 focus:ring-sky-500" />
                      <span>Loại/Dạng</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer hover:bg-slate-50 p-1 rounded">
                      <input type="checkbox" v-model="visibleColumns.dates" class="rounded text-sky-500 focus:ring-sky-500" />
                      <span>Ngày đến -> Ngày đi</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer hover:bg-slate-50 p-1 rounded">
                      <input type="checkbox" v-model="visibleColumns.occupancy" class="rounded text-sky-500 focus:ring-sky-500" />
                      <span>Chiếm dụng</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer hover:bg-slate-50 p-1 rounded">
                      <input type="checkbox" v-model="visibleColumns.availability" class="rounded text-sky-500 focus:ring-sky-500" />
                      <span>Phòng trống</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer hover:bg-slate-50 p-1 rounded">
                      <input type="checkbox" v-model="visibleColumns.quantity" class="rounded text-sky-500 focus:ring-sky-500" />
                      <span>Số lượng</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer hover:bg-slate-50 p-1 rounded">
                      <input type="checkbox" v-model="visibleColumns.price" class="rounded text-sky-500 focus:ring-sky-500" />
                      <span>Giá phòng</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer hover:bg-slate-50 p-1 rounded">
                      <input type="checkbox" v-model="visibleColumns.rateCode" class="rounded text-sky-500 focus:ring-sky-500" />
                      <span>Mã giá phòng/Gói</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer hover:bg-slate-50 p-1 rounded">
                      <input type="checkbox" v-model="visibleColumns.discount" class="rounded text-sky-500 focus:ring-sky-500" />
                      <span>Tăng/Giảm giá</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer hover:bg-slate-50 p-1 rounded">
                      <input type="checkbox" v-model="visibleColumns.upgrade" class="rounded text-sky-500 focus:ring-sky-500" />
                      <span>Nâng hạng phòng</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer hover:bg-slate-50 p-1 rounded">
                      <input type="checkbox" v-model="visibleColumns.adults" class="rounded text-sky-500 focus:ring-sky-500" />
                      <span>Người lớn</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer hover:bg-slate-50 p-1 rounded">
                      <input type="checkbox" v-model="visibleColumns.babies" class="rounded text-sky-500 focus:ring-sky-500" />
                      <span>Em bé</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer hover:bg-slate-50 p-1 rounded">
                      <input type="checkbox" v-model="visibleColumns.children" class="rounded text-sky-500 focus:ring-sky-500" />
                      <span>Trẻ em</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer hover:bg-slate-50 p-1 rounded">
                      <input type="checkbox" v-model="visibleColumns.childBreakfastRate" class="rounded text-sky-500 focus:ring-sky-500" />
                      <span>Giá ăn sáng TE</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer hover:bg-slate-50 p-1 rounded">
                      <input type="checkbox" v-model="visibleColumns.breakfast" class="rounded text-sky-500 focus:ring-sky-500" />
                      <span>Ăn sáng</span>
                    </label>
                  </div>
                </div>
              </div>

              <!-- Grid rooms table allocations -->
              <div class="w-full px-4 pb-4 overflow-x-auto">
                <table class="min-w-[1300px] w-full border-collapse text-left text-xs table-auto">
                  <thead class="bg-slate-50 text-slate-500 font-semibold border-y border-slate-200">
                    <tr>
                      <th v-if="visibleColumns.roomType" class="py-2 px-2 text-center w-[6%] font-semibold text-[11px]">Loại/Dạng</th>
                      <th v-if="visibleColumns.dates" class="py-2 px-2 text-center w-[17%] font-semibold text-[11px]">Ngày đến ~ Ngày đi</th>
                      <th v-if="visibleColumns.occupancy" class="py-2 px-1 text-center w-[5%] font-semibold text-[11px]">Chiếm dụng</th>
                      <th v-if="visibleColumns.availability" class="py-2 px-1 text-center w-[5%] font-semibold text-[11px]">Trống</th>
                      <th v-if="visibleColumns.quantity" class="py-2 px-1 text-center w-[7%] font-semibold text-[11px] bg-slate-100/50">Số lượng</th>
                      <th v-if="visibleColumns.price" class="py-2 px-1 text-center w-[10%] font-semibold text-[11px]">Giá phòng</th>
                      <th v-if="visibleColumns.rateCode" class="py-2 px-2 text-center w-[13%] font-semibold text-[11px]">Mã giá phòng</th>
                      <th v-if="visibleColumns.discount" class="py-2 px-2 text-center w-[10%] font-semibold text-[11px]">Tăng/Giảm</th>
                      <th v-if="visibleColumns.upgrade" class="py-2 px-2 text-center w-[11%] font-semibold text-[11px]">Nâng hạng</th>
                      <th v-if="visibleColumns.adults" class="py-2 px-1 text-center w-[5%] font-semibold text-[11px]">Người lớn</th>
                      <th v-if="visibleColumns.babies" class="py-2 px-1 text-center w-[5%] font-semibold text-[11px]">Em bé</th>
                      <th v-if="visibleColumns.children" class="py-2 px-1 text-center w-[5%] font-semibold text-[11px]">Trẻ em</th>
                      <th v-if="visibleColumns.childBreakfastRate" class="py-2 px-1 text-center w-[11%] font-semibold text-[11px]">Giá ăn sáng trẻ em</th>
                      <th v-if="visibleColumns.breakfast" class="py-2 px-1 text-center w-[4%] font-semibold text-[11px]">Ăn sáng</th>
                    </tr>
                  </thead>
                  
                  <tbody class="text-[11px] text-slate-700 font-medium select-none">
                    <tr v-for="(row, idx) in modalForm.roomAllocations" :key="row.roomClassId" class="border-b border-slate-200 hover:bg-slate-50/50 transition-colors">
                      
                      <!-- Loại/Dạng -->
                      <td v-if="visibleColumns.roomType" class="py-2 px-2 font-bold text-slate-900">{{ row.roomClassCode }}</td>
                      
                      <!-- Ngày đến ~ Ngày đi -->
                      <td v-if="visibleColumns.dates" class="py-2 px-2">
                        <div class="flex items-center justify-center space-x-1 border border-slate-300 rounded-md px-1.5 py-0.5 bg-white h-[30px] shadow-sm text-center whitespace-nowrap">
                          <input 
                            type="date" 
                            v-model="row.arrivalDate" 
                            :min="systemDate"
                            @change="handleRowDateChange(row)"
                            @click="$event.target.showPicker && $event.target.showPicker()"
                            class="date-span-input text-[11px] font-bold text-slate-700 focus:outline-none cursor-pointer bg-transparent border-none p-0 outline-none w-[76px]"
                          />
                          <span class="text-slate-400">~</span>
                          <input 
                            type="date" 
                            v-model="row.departureDate" 
                            :min="row.arrivalDate"
                            @change="handleRowDateChange(row)"
                            @click="$event.target.showPicker && $event.target.showPicker()"
                            class="date-span-input text-[11px] font-bold text-slate-700 focus:outline-none cursor-pointer bg-transparent border-none p-0 outline-none w-[76px]"
                          />
                        </div>
                      </td>
                      
                      <!-- Chiếm dụng -->
                      <td v-if="visibleColumns.occupancy" class="py-2 px-1 text-center font-semibold text-slate-600">{{ getOccupancyCount(row) }}</td>
                      
                      <!-- Phòng trống -->
                      <td v-if="visibleColumns.availability" class="py-2 px-1 text-center font-bold" :class="row.availableRooms <= 0 ? 'text-rose-600' : 'text-slate-800'">
                        {{ row.availableRooms }}
                      </td>
                      
                      <!-- Số lượng -->
                      <td v-if="visibleColumns.quantity" class="py-2 px-1 bg-slate-50/30">
                        <div class="relative w-full min-w-[40px] max-w-[60px] mx-auto border border-slate-300 rounded-md h-[30px] bg-white shadow-sm flex items-center">
                          <input type="number" v-model.number="row.quantity" min="0" @input="updateAllocatedRooms(row)" @focus="$event.target.select()" class="w-full text-center pr-4 focus:outline-none text-[11px] bg-transparent border-none outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
                          <div class="flex flex-col text-slate-800 absolute right-1.5 top-0 bottom-0 justify-center items-center w-3 select-none">
                            <button @click.prevent="row.quantity++; updateAllocatedRooms(row)" class="hover:text-black leading-[0.6] outline-none border-none bg-transparent cursor-pointer p-0"><i class="fa-solid fa-caret-up text-[9px]"></i></button>
                            <button @click.prevent="row.quantity > 0 ? (row.quantity--, updateAllocatedRooms(row)) : null" class="hover:text-black leading-[0.6] outline-none border-none bg-transparent cursor-pointer p-0"><i class="fa-solid fa-caret-down text-[9px]"></i></button>
                          </div>
                        </div>
                      </td>

                      <!-- Giá phòng -->
                      <td v-if="visibleColumns.price" class="py-2 px-1">
                        <div class="relative w-full min-w-[70px] mx-auto border border-slate-300 rounded-md h-[30px] shadow-sm flex items-center" :class="isPriceDisabled(row) ? 'bg-slate-100 cursor-not-allowed' : 'bg-white'">
                          <input type="text" :value="formatCurrencyInput(row.price)" :disabled="isPriceDisabled(row)" @input="e => { row.price = cleanCurrencyValue(e.target.value); row.basePrice = row.price; syncAllocationToRooms(row) }" @focus="e => { if (cleanCurrencyValue(e.target.value) === 0) e.target.value = ''; e.target.select() }" class="w-full text-right pl-2 pr-5 focus:outline-none text-[11px] bg-transparent border-none outline-none font-bold" :class="isPriceDisabled(row) ? 'text-slate-400 cursor-not-allowed' : 'text-slate-800'">
                          <div v-if="!isPriceDisabled(row)" class="flex flex-col text-slate-800 absolute right-1.5 top-0 bottom-0 justify-center items-center w-3 select-none">
                            <button @click.prevent="row.price = (Number(row.price) || 0) + 50000; row.basePrice = row.price; syncAllocationToRooms(row)" class="hover:text-black leading-[0.6] outline-none border-none bg-transparent cursor-pointer p-0"><i class="fa-solid fa-caret-up text-[9px]"></i></button>
                            <button @click.prevent="row.price >= 50000 ? (row.price = (Number(row.price) || 0) - 50000, row.basePrice = row.price, syncAllocationToRooms(row)) : null" class="hover:text-black leading-[0.6] outline-none border-none bg-transparent cursor-pointer p-0"><i class="fa-solid fa-caret-down text-[9px]"></i></button>
                          </div>
                        </div>
                      </td>

                      <!-- Mã giá phòng -->
                      <td v-if="visibleColumns.rateCode" class="py-2 px-2">
                        <select 
                          v-model="row.rateCode" 
                          @change="handleRateCodeChange(row)"
                          class="w-full border border-slate-300 rounded-md h-[30px] px-2 text-slate-700 focus:outline-none shadow-sm bg-white text-[11px]"
                        >
                          <option value="">— Chọn giá phòng —</option>
                          <option v-for="rc in activeRoomRateCodes" :key="rc.id" :value="rc.Ma">{{ rc.Ma }}</option>
                        </select>
                      </td>

                      <!-- Tăng/Giảm -->
                      <td v-if="visibleColumns.discount" class="py-2 px-2 relative">
                        <div 
                          @click.stop="toggleDiscountPopover(row)"
                          class="w-full border border-slate-300 rounded-md h-[30px] px-2 text-slate-700 shadow-sm text-[11px] flex items-center justify-between cursor-pointer bg-white"
                        >
                          <span class="font-bold" :class="row.discountValue ? 'text-sky-600' : 'text-slate-500'">{{ getDiscountLabel(row) }}</span>
                          <i class="fa-solid fa-calculator text-slate-400 text-[10px]"></i>
                        </div>
                        
                        <!-- Popover UI -->
                        <div 
                          v-if="activeDiscountRowId === row.roomClassId" 
                          @click.stop
                          class="absolute left-1/2 -translate-x-1/2 z-[9999] bg-white border border-slate-200 rounded-lg p-2.5 shadow-xl flex flex-col gap-2 w-[185px] pointer-events-auto"
                          :class="idx < 2 ? 'top-full mt-1.5' : 'bottom-full mb-1.5'"
                        >
                          <!-- Toggle Tăng/Giảm -->
                          <div class="flex items-center gap-1.5 select-none">
                            <button 
                              type="button"
                              @click.stop="row.discountType = 'up'; calculateAdjustedPrice(row)"
                              class="flex-1 py-1 rounded text-[10px] font-extrabold cursor-pointer border transition-colors flex items-center justify-center gap-1"
                              :style="{ minHeight: '26px' }"
                              :class="row.discountType === 'up' ? 'bg-sky-100 text-sky-700 border-sky-300' : 'bg-slate-50 text-slate-400 border-slate-200 hover:bg-slate-100'"
                            >
                              <i class="fa-solid fa-angles-up text-emerald-500"></i>
                              <span>Tăng</span>
                            </button>
                            <button 
                              type="button"
                              @click.stop="row.discountType = 'down'; calculateAdjustedPrice(row)"
                              class="flex-1 py-1 rounded text-[10px] font-extrabold cursor-pointer border transition-colors flex items-center justify-center gap-1"
                              :style="{ minHeight: '26px' }"
                              :class="row.discountType === 'down' ? 'bg-sky-100 text-sky-700 border-sky-300' : 'bg-slate-50 text-slate-400 border-slate-200 hover:bg-slate-100'"
                            >
                              <i class="fa-solid fa-angles-down text-rose-500"></i>
                              <span>Giảm</span>
                            </button>
                          </div>
                          
                          <!-- Input and unit toggle -->
                          <div class="flex items-center gap-1.5">
                            <!-- Input -->
                            <div class="relative flex-1 border border-slate-300 rounded bg-white shadow-sm flex items-center h-[26px]">
                              <input 
                                type="text"
                                :value="row.discountUnit === 'percent' ? row.discountValue : formatCurrencyInput(row.discountValue)"
                                @input="e => { row.discountValue = row.discountUnit === 'percent' ? Number(e.target.value.replace(/[^\d]/g, '')) || 0 : cleanCurrencyValue(e.target.value); calculateAdjustedPrice(row) }"
                                @focus="e => { if (cleanCurrencyValue(e.target.value) === 0) e.target.value = ''; e.target.select() }"
                                class="w-full text-right px-1.5 focus:outline-none text-[11px] bg-transparent border-none outline-none font-bold text-slate-800"
                              />
                            </div>
                            
                            <!-- Toggle switch unit -->
                            <label class="relative inline-flex items-center cursor-pointer select-none">
                              <input 
                                type="checkbox" 
                                :checked="row.discountUnit === 'percent'" 
                                @change="e => { row.discountUnit = e.target.checked ? 'percent' : 'amount'; calculateAdjustedPrice(row) }"
                                class="sr-only peer"
                              />
                              <div class="w-11 h-[20px] bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-[20px] after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-[16px] after:w-[16px] after:transition-all peer-checked:bg-sky-500"></div>
                              <span 
                                class="absolute text-[8px] font-black pointer-events-none select-none transition-all"
                                :class="row.discountUnit === 'percent' ? 'left-[6px] text-white' : 'right-[7px] text-slate-500'"
                              >
                                {{ row.discountUnit === 'percent' ? '%' : 'đ' }}
                              </span>
                            </label>
                          </div>
                          
                          <!-- Price summary preview -->
                          <div class="text-[10px] text-slate-500 border-t border-slate-100 pt-1.5 mt-0.5 flex flex-col gap-0.5 select-none">
                            <div class="flex justify-between">
                              <span>Giá gốc:</span>
                              <span class="font-bold text-slate-700">{{ formatCurrencyInput(row.basePrice || 0) }}</span>
                            </div>
                            <div class="flex justify-between">
                              <span>Giá mới:</span>
                              <span class="font-bold text-sky-600">{{ formatCurrencyInput(row.price || 0) }}</span>
                            </div>
                          </div>
                        </div>
                      </td>

                      <!-- Nâng hạng -->
                      <td v-if="visibleColumns.upgrade" class="py-2 px-2">
                        <div class="relative">
                          <select v-model="row.upgradeClassId" @change="syncAllocationToRooms(row)" class="w-full border border-slate-300 rounded-md h-[30px] pl-2 pr-5 appearance-none focus:outline-none text-slate-700 bg-white shadow-sm cursor-pointer text-[11px]">
                            <option :value="null">Gốc</option>
                            <option v-for="rc in roomClasses" :key="rc.id" :value="rc.id">{{ rc.code }}</option>
                          </select>
                          <i class="fa-solid fa-chevron-down absolute right-2.5 top-2.5 text-slate-400 text-[10px] pointer-events-none"></i>
                        </div>
                      </td>

                      <!-- Người lớn -->
                      <td v-if="visibleColumns.adults" class="py-2 px-1">
                        <div class="relative w-full min-w-[35px] max-w-[50px] mx-auto border border-slate-300 rounded-md h-[30px] bg-white shadow-sm flex items-center">
                          <input type="number" v-model.number="row.adults" min="1" @input="syncAllocationToRooms(row)" @focus="$event.target.select()" class="w-full text-center pr-4 focus:outline-none text-[11px] bg-transparent border-none outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
                          <div class="flex flex-col text-slate-800 absolute right-1.5 top-0 bottom-0 justify-center items-center w-3 select-none">
                            <button @click.prevent="row.adults++; syncAllocationToRooms(row)" class="hover:text-black leading-[0.6] outline-none border-none bg-transparent cursor-pointer p-0"><i class="fa-solid fa-caret-up text-[9px]"></i></button>
                            <button @click.prevent="row.adults > 1 ? (row.adults--, syncAllocationToRooms(row)) : null" class="hover:text-black leading-[0.6] outline-none border-none bg-transparent cursor-pointer p-0"><i class="fa-solid fa-caret-down text-[9px]"></i></button>
                          </div>
                        </div>
                      </td>

                      <!-- Em bé -->
                      <td v-if="visibleColumns.babies" class="py-2 px-1">
                        <div class="relative w-full min-w-[35px] max-w-[50px] mx-auto border border-slate-300 rounded-md h-[30px] bg-white shadow-sm flex items-center">
                          <input type="number" v-model.number="row.babies" min="0" @input="syncAllocationToRooms(row)" @focus="$event.target.select()" class="w-full text-center pr-4 focus:outline-none text-[11px] bg-transparent border-none outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
                          <div class="flex flex-col text-slate-800 absolute right-1.5 top-0 bottom-0 justify-center items-center w-3 select-none">
                            <button @click.prevent="row.babies++; syncAllocationToRooms(row)" class="hover:text-black leading-[0.6] outline-none border-none bg-transparent cursor-pointer p-0"><i class="fa-solid fa-caret-up text-[9px]"></i></button>
                            <button @click.prevent="row.babies > 0 ? (row.babies--, syncAllocationToRooms(row)) : null" class="hover:text-black leading-[0.6] outline-none border-none bg-transparent cursor-pointer p-0"><i class="fa-solid fa-caret-down text-[9px]"></i></button>
                          </div>
                        </div>
                      </td>

                      <!-- Trẻ em -->
                      <td v-if="visibleColumns.children" class="py-2 px-1">
                        <div class="relative w-full min-w-[35px] max-w-[50px] mx-auto border border-slate-300 rounded-md h-[30px] bg-white shadow-sm flex items-center">
                          <input type="number" v-model.number="row.children" min="0" @input="syncAllocationToRooms(row)" @focus="$event.target.select()" class="w-full text-center pr-4 focus:outline-none text-[11px] bg-transparent border-none outline-none [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none">
                          <div class="flex flex-col text-slate-800 absolute right-1.5 top-0 bottom-0 justify-center items-center w-3 select-none">
                            <button @click.prevent="row.children++; syncAllocationToRooms(row)" class="hover:text-black leading-[0.6] outline-none border-none bg-transparent cursor-pointer p-0"><i class="fa-solid fa-caret-up text-[9px]"></i></button>
                            <button @click.prevent="row.children > 0 ? (row.children--, syncAllocationToRooms(row)) : null" class="hover:text-black leading-[0.6] outline-none border-none bg-transparent cursor-pointer p-0"><i class="fa-solid fa-caret-down text-[9px]"></i></button>
                          </div>
                        </div>
                      </td>

                      <!-- Giá ăn sáng trẻ em -->
                      <td v-if="visibleColumns.childBreakfastRate" class="py-2 px-1">
                        <div class="relative w-full min-w-[90px] mx-auto border border-slate-300 rounded-md h-[30px] bg-white shadow-sm flex items-center">
                          <input type="text" :value="formatCurrencyInput(row.childBreakfastRate)" @input="e => { row.childBreakfastRate = cleanCurrencyValue(e.target.value); syncAllocationToRooms(row) }" @focus="e => { if (cleanCurrencyValue(e.target.value) === 0) e.target.value = ''; e.target.select() }" class="w-full text-right pl-2 pr-5 focus:outline-none text-[11px] bg-transparent border-none outline-none font-bold text-slate-800">
                          <div class="flex flex-col text-slate-800 absolute right-1.5 top-0 bottom-0 justify-center items-center w-3 select-none">
                            <button @click.prevent="row.childBreakfastRate = (Number(row.childBreakfastRate) || 0) + 5000; syncAllocationToRooms(row)" class="hover:text-black leading-[0.6] outline-none border-none bg-transparent cursor-pointer p-0"><i class="fa-solid fa-caret-up text-[9px]"></i></button>
                            <button @click.prevent="row.childBreakfastRate >= 5000 ? (row.childBreakfastRate = (Number(row.childBreakfastRate) || 0) - 5000, syncAllocationToRooms(row)) : null" class="hover:text-black leading-[0.6] outline-none border-none bg-transparent cursor-pointer p-0"><i class="fa-solid fa-caret-down text-[9px]"></i></button>
                          </div>
                        </div>
                      </td>

                      <!-- Ăn sáng -->
                      <td v-if="visibleColumns.breakfast" class="py-2 px-1 text-center">
                        <input type="checkbox" v-model="row.breakfastIncluded" @change="syncAllocationToRooms(row)" class="w-4 h-4 accent-blue-500 cursor-pointer rounded border-slate-300">
                      </td>

                    </tr>
                  </tbody>
                  
                  <tfoot class="bg-white font-bold text-slate-900 border-t border-slate-300 text-[11px]">
                    <tr>
                      <td v-if="visibleColumns.roomType" class="py-2.5 px-2 text-left text-slate-800">Tổng</td>
                      <td v-if="visibleColumns.dates" class="py-2.5 px-2"></td>
                      <td v-if="visibleColumns.occupancy" class="py-2.5 px-1 text-center"></td>
                      <td v-if="visibleColumns.availability" class="py-2.5 px-1 text-center text-[11px] font-semibold">{{ allocationsSummary.availableRooms }}</td>
                      <td v-if="visibleColumns.quantity" class="py-2.5 px-1 text-center text-[11px] font-semibold text-sky-600 bg-slate-100/30">{{ allocationsSummary.quantity }}</td>
                      <td v-if="visibleColumns.price" class="py-2.5 px-1"></td>
                      <td v-if="visibleColumns.rateCode" class="py-2.5 px-2"></td>
                      <td v-if="visibleColumns.discount" class="py-2.5 px-2"></td>
                      <td v-if="visibleColumns.upgrade" class="py-2.5 px-2"></td>
                      <td v-if="visibleColumns.adults" class="py-2.5 px-1 text-center text-[11px] font-semibold text-slate-800">{{ allocationsSummary.adults }}</td>
                      <td v-if="visibleColumns.babies" class="py-2.5 px-1 text-center text-[11px] font-semibold text-slate-800">{{ allocationsSummary.babies }}</td>
                      <td v-if="visibleColumns.children" class="py-2.5 px-1 text-center text-[11px] font-semibold text-slate-800">{{ allocationsSummary.children }}</td>
                      <td v-if="visibleColumns.childBreakfastRate" class="py-2.5 px-1"></td>
                      <td v-if="visibleColumns.breakfast" class="py-2.5 px-1"></td>
                    </tr>
                  </tfoot>
                </table>
              </div>
            </div>

          </div>

          <!-- MODAL FOOTER -->
          <div class="bg-white border-t border-gray-200 p-2.5 flex flex-col sm:flex-row justify-between items-center gap-2 shrink-0 rounded-b-xl">
              <div class="text-xs text-gray-400 flex flex-wrap items-center gap-2 w-full sm:w-auto justify-center sm:justify-start pl-1">
                  <div class="flex items-center space-x-1.5">
                      <i class="fa-solid fa-user-pen text-gray-300 text-xs"></i>
                      <span>Tạo bởi: <strong class="text-gray-500 font-bold">{{ modalForm.createdBy || authStore.user?.username || 'system' }}</strong></span>
                  </div>
                  <div v-if="modalForm.createdAt" class="flex items-center space-x-1.5">
                      <span class="text-gray-300">|</span>
                      <i class="fa-solid fa-clock text-gray-300 text-xs"></i>
                      <span>Thời điểm tạo: <strong class="text-gray-500 font-bold">{{ formatDateTime(modalForm.createdAt) }}</strong></span>
                  </div>
              </div>
              <div class="flex items-center space-x-2 w-full sm:w-auto justify-center sm:justify-end">
                  <button 
                    @click="closeModal"
                    :disabled="isSavingModal"
                    class="px-4 py-1.5 border border-gray-300 text-gray-700 font-bold rounded-xl hover:bg-gray-50 transition text-xs cursor-pointer bg-white disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                      Hủy bỏ
                  </button>
                  <button 
                    @click="handleSaveNewBooking"
                    :disabled="isSavingModal"
                    class="px-4 py-1.5 bg-[#2563eb] text-white font-bold rounded-xl hover:bg-blue-700 transition flex items-center space-x-1.5 shadow-md text-xs cursor-pointer border-none disabled:opacity-50 disabled:cursor-not-allowed"
                  >
                      <i v-if="isSavingModal" class="fa-solid fa-circle-notch animate-spin"></i>
                      <i v-else class="fa-regular fa-floppy-disk"></i>
                      <span>{{ isSavingModal ? 'Đang lưu...' : (modalForm.dbId ? 'Cập nhật Booking' : 'Lưu Booking') }}</span>
                  </button>
              </div>
          </div>

        </div>
      </div>
    </Teleport>

    <!-- DEPOSIT MODAL MATCHING ĐẶT CỌC.html -->
    <Teleport to="body">
      <DepositModal 
        v-model:show="isDepositModalOpen" 
        :bookingId="modalForm?.dbId" 
        :bookingName="modalForm?.bookingName" 
        :bookingCode="modalForm?.bookingCode" 
        :paymentMethods="paymentMethods" 
        :currenciesList="currenciesList" 
        v-model:deposits="modalForm.deposits" 
        @update:paymentValue="modalForm.paymentValue = $event; if (activeTab) { activeTab.deposit = $event; activeTab.paymentValue = $event; }" 
      />
    </Teleport>

    <!-- DỊCH VỤ BỔ SUNG MODAL (Screenshot 4 Match) -->
    <Teleport to="body">
      <ServicesModal 
        v-model:show="isServicesModalOpen" 
        :room="servicesModalRoom" 
        :targetRooms="servicesTargetRooms" 
        :hotelServicesList="hotelServicesList" 
        :systemDate="systemDate"
        @saved="handleServicesSaved" 
      />
    </Teleport>

    <!-- NHÂN BẢN BOOKING MODAL -->
    <Teleport to="body">
      <CopyModal 
        v-model:show="isCopyModalOpen" 
        :bookingId="activeTab?.dbId" 
        :defaultArrival="copyModalArrivalDate" 
        :defaultDeparture="copyModalDepartureDate" 
        @copied="handleCopied" 
      />
    </Teleport>

    <!-- NÂNG HẠNG PHÒNG MODAL -->
    <Teleport to="body">
      <UpgradeModal 
        v-model:show="isUpgradeModalOpen" 
        :bookingId="activeTab?.dbId" 
        :targetRooms="activeTab?.rooms ? activeTab.rooms.filter(r => selectedRows.includes(r.id)) : []" 
        :roomClasses="roomClasses" 
        :roomForms="roomForms"
        :roomRateCodes="roomRateCodes"
        @upgraded="handleUpgraded" 
      />
    </Teleport>

    <!-- THÔNG TIN KHÁCH MODAL -->
    <GuestInfoModal
      :show="isGuestInfoModalOpen"
      :bookingId="activeTab?.dbId"
      @close="isGuestInfoModalOpen = false"
      @saved="loadBookings"
    />

    <!-- XÓA DỊCH VỤ BỔ SUNG MODAL -->
    <Teleport to="body">
      <DeleteServiceModal
        v-model:show="isDeleteServiceModalOpen"
        :room="deleteServiceModalRoom"
        :targetRooms="deleteServiceModalTargetRooms"
        :systemDate="systemDate"
        @deleted="handleServiceDeleted"
      />
    </Teleport>

    <!-- THÊM GIƯỜNG MODAL -->
    <Teleport to="body">
      <ExtraBedModal
        v-model:show="isExtraBedModalOpen"
        :room="extraBedModalRoom"
        :systemDate="systemDate"
        @saved="handleExtraBedSaved"
      />
    </Teleport>

    <!-- CHI TIẾT ĂN SÁNG TRẺ EM MODAL -->
    <Teleport to="body">
      <ChildBreakfastModal
        v-model:show="isChildBreakfastModalOpen"
        :room="selectedRoomForBreakfast"
        :bookingId="activeTab?.dbId"
        @saved="onChildBreakfastSaved"
      />
    </Teleport>

    <!-- YÊU CẦU ĐẶC BIỆT MODAL -->
    <Teleport to="body">
      <SpecialRequestsModal
        v-model:show="isSpecialRequestsModalOpen"
        :room="specialRequestsModalRoom"
        @saved="handleSpecialRequestsSaved"
      />
    </Teleport>

    <!-- HỦY PHÒNG / HỦY ĐẶC ĐĂNG KÝ MODAL -->
    <Teleport to="body">
      <CancelReasonModal
        v-model:show="isCancelReasonModalOpen"
        :title="cancelModalTitle"
        :subTitle="cancelModalSubTitle"
        @confirm="handleConfirmCancelReason"
      />
    </Teleport>

    <!-- Global Loading Overlay -->
    <LoadingOverlay :show="isLoading" />
  </div>
</template>

<style src="./CreateRegistrationPage.css"></style>

<style>
.cancelled-room,
.cancelled-room span,
.cancelled-room td {
  color: #b91c1c !important;
}
</style>
