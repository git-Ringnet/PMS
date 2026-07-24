<script setup>
import { ref, computed, onMounted, watch, onBeforeUnmount } from 'vue'
import { ROOM_STATUSES, roomService } from '@/services/room-service'
import { useUiStore } from '@/stores/ui-store'
import { useRoomStore } from '@/stores/room-store'
import RoomIcon from '@/components/RoomIcon.vue'
import { fetchBookings, checkInRoom, unassignRoom, fetchRoomRateCodes, cancelBookingRoom, fetchSystemDate, fetchUserSettings, updateUserSettings, fetchHotelSettings, updateBookingRoom, splitBookingRoom, createBooking, lockRoomMove, unlockRoomMove } from '@/services/booking-service'
import { fetchCompanies, fetchMarkets, fetchCustomerSources } from '@/services/company-service'
import CancelReasonModal from './components/CancelReasonModal.vue'
import { useAuthStore } from '@/stores/auth-store'
import http from '@/services/http'

const uiStore = useUiStore()
const roomStore = useRoomStore()
const authStore = useAuthStore()

const isAdmin = computed(() => {
  const u = authStore.user
  if (!u) return false
  return u.username === 'admin' || u.job_title_code === 'RL001' || u.department_code === 'MGMT'
})

const legendConfigKeys = {
  'Reservation': 'RoomPlan_ColorRoomReservation',
  'Guaranteed': 'RoomPlan_ColorRoomReservation',
  'InHouse': 'RoomPlan_ColorRoomInhouse',
  'Late Checkout': 'RoomPlan_ColorRoomLateCheckout',
  'OOO': 'RoomPlan_ColorOOO',
  'OOS': 'RoomPlan_ColorOOS'
}

const presetColors = [
  '#E3E8C4', '#4a90e2', '#FCF55F', '#107eeb',
  '#97D5FF', '#2ECC71', '#E74C3C', '#F1C40F',
  '#9B59B6', '#1ABC9C', '#34495E', '#FFFFFF'
]

const systemDate = ref('')

// Hsv/Rgb/Hex Helpers
function hsvToRgb(h, s, v) {
  s = s / 100
  v = v / 100
  let r, g, b
  const i = Math.floor(h / 60) % 6
  const f = h / 60 - i
  const p = v * (1 - s)
  const q = v * (1 - f * s)
  const t = v * (1 - (1 - f) * s)
  switch (i) {
    case 0: r = v; g = t; b = p; break
    case 1: r = q; g = v; b = p; break
    case 2: r = p; g = v; b = t; break
    case 3: r = p; g = q; b = v; break
    case 4: r = t; g = p; b = v; break
    case 5: r = v; g = p; b = q; break
  }
  return {
    r: Math.round(r * 255),
    g: Math.round(g * 255),
    b: Math.round(b * 255)
  }
}

function rgbToHsv(r, g, b) {
  r /= 255; g /= 255; b /= 255
  const max = Math.max(r, g, b), min = Math.min(r, g, b)
  let h, s, v = max
  const d = max - min
  s = max === 0 ? 0 : d / max
  if (max === min) {
    h = 0
  } else {
    switch (max) {
      case r: h = (g - b) / d + (g < b ? 6 : 0); break
      case g: h = (b - r) / d + 2; break
      case b: h = (r - g) / d + 4; break
    }
    h /= 6
  }
  return {
    h: Math.round(h * 360),
    s: Math.round(s * 100),
    v: Math.round(v * 100)
  }
}

function hexToRgb(hex) {
  hex = hex.replace(/^#/, '')
  if (hex.length === 3) {
    hex = hex.split('').map(c => c + c).join('')
  }
  const num = parseInt(hex, 16)
  return {
    r: (num >> 16) & 255,
    g: (num >> 8) & 255,
    b: num & 255
  }
}

function rgbToHex(r, g, b) {
  const toHex = (c) => {
    const h = Math.max(0, Math.min(255, c)).toString(16)
    return h.length === 1 ? '0' + h : h
  }
  return '#' + toHex(r) + toHex(g) + toHex(b)
}

function toLocalDateStr(dateVal) {
  if (!dateVal) return ''
  if (typeof dateVal === 'string' && !dateVal.includes('T') && !dateVal.includes('Z') && !dateVal.includes('+')) {
    return dateVal.substring(0, 10)
  }
  const d = new Date(dateVal)
  if (isNaN(d.getTime())) return ''
  const year = d.getFullYear()
  const month = String(d.getMonth() + 1).padStart(2, '0')
  const day = String(d.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

const showColorPicker = ref(false)
const pickerPosition = ref({ top: 0, left: 0 })
const selectedLegendForColor = ref(null)
const tempColorValue = ref('#ffffff')
const savingColor = ref(false)
const colorPickerRef = ref(null)
const colorAreaRef = ref(null)

const hue = ref(0)
const saturation = ref(100)
const value = ref(100)
const alpha = ref(100)

const computedRgb = computed(() => hsvToRgb(hue.value, saturation.value, value.value))
const computedHex = computed(() => rgbToHex(computedRgb.value.r, computedRgb.value.g, computedRgb.value.b))

const pickerPresets = [
  '#D32F2F', '#F57C00', '#FBC02D', '#5D4037', '#7CB342', '#388E3C', '#8E24AA', '#5E35B1',
  '#1E88E5', '#00ACC1', '#00897B', '#000000', '#424242', '#9E9E9E', '#E0E0E0', '#FFFFFF'
]

const emit = defineEmits(['loading', 'edit-booking'])

const getInitialDates = () => {
  const start = new Date()
  const end = new Date()
  end.setDate(start.getDate() + 29)
  
  const format = (d) => {
    const y = d.getFullYear()
    const m = String(d.getMonth() + 1).padStart(2, '0')
    const dd = String(d.getDate()).padStart(2, '0')
    return `${y}-${m}-${dd}`
  }
  
  const formatDMY = (d) => {
    const y = d.getFullYear()
    const m = String(d.getMonth() + 1).padStart(2, '0')
    const dd = String(d.getDate()).padStart(2, '0')
    return `${dd} / ${m} / ${y}`
  }
  
  return {
    start,
    end,
    startStr: format(start),
    endStr: format(end),
    rangeText: `${formatDMY(start)} ~ ${formatDMY(end)}`
  }
}

const initDates = getInitialDates()

// Filter states
const dateRangeText = ref(initDates.rangeText)
const selectedRoomTypes = ref([])
const tempSelectedRoomTypes = ref([])
const showRoomTypePopover = ref(false)
const roomTypeSearchQuery = ref('')

// Date selection popover states
const endDate = ref(initDates.end)
const showDatePickerPopover = ref(false)
const tempStartDateStr = ref(initDates.startStr)
const tempEndDateStr = ref(initDates.endStr)

// Search & filter states
const showFilterDrawer = ref(false)
const selectedStatuses = ref([])
const selectedCompanies = ref([])
const tempSelectedStatuses = ref([])
const tempSelectedCompanies = ref([])
const showCompanyFilterDropdown = ref(false)
const companySearchQuery = ref('')
const allCompanies = ref([])

// Quick booking modal states
const showQuickBookingModal = ref(false)
const rateCodes = ref([])
const standardRates = ref([])
const allMarkets = ref([])
const allCustomerSources = ref([])
const quickBookingCompanySearch = ref('KHÁCH LẺ')
const showQuickBookingCompanyDropdown = ref(false)
const quickBookingForm = ref({
  company: 'KHÁCH LẺ',
  marketId: null,
  customerSourceId: null,
  marketSegment: 'Free Individual Traveler',
  sourceCode: 'Free Individual Traveler',
  bookingName: 'Walkin Guest',
  rateCode: 'Vui lòng chọn giá phòng',
  rate: '890000'
})

// Lock room modal states
const showLockRoomModal = ref(false)
const lockRoomType = ref('OOO') // 'OOO' | 'OOS'
const lockRoomForm = ref({
  note: ''
})

// Cell selections state
const selectedCells = ref([])
const isDragSelecting = ref(false)
const dragSelectionStart = ref(null) // { roomIdx, dayIdx, roomNo }
const initialSelectedCells = ref([])

// Resize state
const resizeState = ref(null)

// Drag and drop & Splitting Room states
const draggedBooking = ref(null)
const draggedOverRoom = ref(null)
const draggedOverDayIdx = ref(null)
const draggedBookingRect = ref(null)
const dragGhostY = ref(0)

const activeHighlightRoom = computed(() => draggedOverRoom.value)
const activeHighlightDayIdx = computed(() => draggedOverDayIdx.value)
const splittingBooking = ref(null)
const splitIndex = ref(-1)

function saveRoomTypeSelection() {
  selectedRoomTypes.value = [...tempSelectedRoomTypes.value]
  showRoomTypePopover.value = false
}

function closeRoomTypePopover() {
  showRoomTypePopover.value = false
}

function saveDateRange() {
  const start = new Date(tempStartDateStr.value)
  const end = new Date(tempEndDateStr.value)
  if (isNaN(start.getTime()) || isNaN(end.getTime())) {
    uiStore.showToast('Định dạng ngày không hợp lệ', 'error')
    return
  }
  if (start.getTime() > end.getTime()) {
    uiStore.showToast('Ngày bắt đầu không được lớn hơn ngày kết thúc', 'warning')
    return
  }
  startDate.value = start
  endDate.value = end
  dateRangeText.value = `${formatDateToDMY(formatDateStr(start))} ~ ${formatDateToDMY(formatDateStr(end))}`
  showDatePickerPopover.value = false
}

async function loadCompanies() {
  try {
    const res = await fetchCompanies({ limit: 1000 })
    if (res && res.data && res.data.success && res.data.data) {
      allCompanies.value = res.data.data
    }
  } catch (err) {
    console.error('Failed to load companies:', err)
  }
}

async function loadRateCodes() {
  try {
    const res = await fetchRoomRateCodes()
    if (res && res.data && res.data.data) {
      rateCodes.value = res.data.data
    } else if (res && res.data) {
      rateCodes.value = res.data
    }
  } catch (err) {
    console.error('Failed to load rate codes:', err)
  }
}

function openFilterDrawer() {
  tempSelectedStatuses.value = [...selectedStatuses.value]
  tempSelectedCompanies.value = [...selectedCompanies.value]
  tempSelectedRoomTypes.value = [...selectedRoomTypes.value]
  showFilterDrawer.value = true
}

function applyFilters() {
  selectedStatuses.value = [...tempSelectedStatuses.value]
  selectedCompanies.value = [...tempSelectedCompanies.value]
  selectedRoomTypes.value = [...tempSelectedRoomTypes.value]
  showFilterDrawer.value = false
}

function clearFilters() {
  tempSelectedStatuses.value = []
  tempSelectedCompanies.value = []
  tempSelectedRoomTypes.value = []
  selectedStatuses.value = []
  selectedCompanies.value = []
  selectedRoomTypes.value = []
}

const companyFilterData = computed(() => {
  const list = []
  
  allCompanies.value.forEach(c => {
    const name = c.company_name || c.name
    if (name && !list.includes(name)) {
      list.push(name)
    }
  })

  // Fallback from loaded bookings
  if (bookings.value) {
    bookings.value.forEach(b => {
      if (b.company && !list.includes(b.company)) {
        list.push(b.company)
      }
    })
  }

  const sortedList = list.sort((a, b) => a.localeCompare(b))

  const query = removeVietnameseTones(companySearchQuery.value.toLowerCase().trim())
  const matched = sortedList.filter(name => {
    return removeVietnameseTones(name.toLowerCase()).includes(query)
  })

  // Display selected ones first, then limit others matching the query to 20 items
  const finalSet = new Set()
  tempSelectedCompanies.value.forEach(name => {
    finalSet.add(name)
  })

  let addedCount = 0
  const LIMIT = 20
  for (const name of matched) {
    if (!finalSet.has(name)) {
      if (addedCount < LIMIT) {
        finalSet.add(name)
        addedCount++
      }
    }
  }

  return {
    items: Array.from(finalSet),
    totalMatched: matched.length,
    hasMore: matched.length > LIMIT
  }
})

const displayCompanies = computed(() => companyFilterData.value.items)

const allCompanyNames = computed(() => {
  const list = ['KHÁCH LẺ']
  if (allCompanies.value && allCompanies.value.length > 0) {
    allCompanies.value.forEach(c => {
      const name = c.company_name || c.name || c.company_code
      if (name && !list.some(item => item.toLowerCase() === String(name).toLowerCase())) {
        list.push(name)
      }
    })
  }
  if (bookings.value) {
    bookings.value.forEach(b => {
      if (b.company && !list.some(item => item.toLowerCase() === String(b.company).toLowerCase())) {
        list.push(b.company)
      }
    })
  }
  return list
})

const filteredQuickBookingCompanies = computed(() => {
  const query = removeVietnameseTones(quickBookingCompanySearch.value.toLowerCase().trim())
  
  if (!query || query === 'khach le') {
    return allCompanyNames.value
  }
  
  return allCompanyNames.value.filter(cName => removeVietnameseTones(String(cName).toLowerCase()).includes(query))
})

function selectQuickBookingCompany(cName) {
  quickBookingForm.value.company = cName
  quickBookingCompanySearch.value = cName
  showQuickBookingCompanyDropdown.value = false

  const co = allCompanies.value.find(c => {
    const name = c.company_name || c.name || c.company_code
    return name && String(name).toLowerCase() === String(cName).toLowerCase()
  })

  if (co) {
    if (co.market_id) {
      quickBookingForm.value.marketId = co.market_id
    } else if (co.market?.id) {
      quickBookingForm.value.marketId = co.market.id
    }

    if (co.customer_source_id) {
      quickBookingForm.value.customerSourceId = co.customer_source_id
    } else if (co.customer_source?.id) {
      quickBookingForm.value.customerSourceId = co.customer_source.id
    }

    if (co.rate_code) {
      quickBookingForm.value.rateCode = co.rate_code
      calculateQuickBookingPrice()
    }
  }
}

watch(showRoomTypePopover, (isOpen) => {
  if (isOpen) {
    tempSelectedRoomTypes.value = [...selectedRoomTypes.value]
    roomTypeSearchQuery.value = ''
  }
})

const isAllSelected = computed({
  get: () => {
    if (roomTypes.value.length === 0) return false
    return roomTypes.value.every(t => tempSelectedRoomTypes.value.includes(t.code))
  },
  set: (val) => {
    if (val) {
      tempSelectedRoomTypes.value = roomTypes.value.map(t => t.code)
    } else {
      tempSelectedRoomTypes.value = []
    }
  }
})

const filteredRoomTypesList = computed(() => {
  const query = removeVietnameseTones(roomTypeSearchQuery.value.toLowerCase().trim())
  if (!query) return roomTypes.value
  return roomTypes.value.filter(t => {
    const nameClean = removeVietnameseTones(t.name).toLowerCase()
    const codeClean = removeVietnameseTones(t.code).toLowerCase()
    return nameClean.includes(query) || codeClean.includes(query)
  })
})

const showNights = ref(true)
const showNotes = ref(true)
const startDate = ref(initDates.start)
const activeGroupSetting = ref('Phòng') // 'Loại phòng' | 'Dạng phòng' | 'Tầng' | 'Phòng'
const settingsLoaded = ref(false) // Tránh auto-save khi đang load settings lần đầu
const hotelSettings = ref(null)
const showPlanSettings = ref(false)
const showWaitingList = ref(false)
const searchInput = ref('')
const activeSearchQuery = ref('')

function handleSearchTrigger() {
  activeSearchQuery.value = searchInput.value
}

function handleClearSearch() {
  searchInput.value = ''
  activeSearchQuery.value = ''
}

function removeVietnameseTones(str) {
  if (!str) return ''
  return str
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/đ/g, 'd')
    .replace(/Đ/g, 'D')
}

function isBookingMatched(bk) {
  if (!activeSearchQuery.value) return true
  const query = removeVietnameseTones(activeSearchQuery.value.toLowerCase().trim())
  
  const nameClean = removeVietnameseTones(bk.name || '').toLowerCase()
  const codeClean = removeVietnameseTones(bk.code || '').toLowerCase()
  const guestClean = removeVietnameseTones(bk.guestName || '').toLowerCase()
  const companyClean = removeVietnameseTones(bk.company || '').toLowerCase()
  
  return nameClean.includes(query) || 
         codeClean.includes(query) || 
         guestClean.includes(query) ||
         companyClean.includes(query)
}

function formatDateStr(d) {
  const yyyy = d.getFullYear()
  const mm = String(d.getMonth() + 1).padStart(2, '0')
  const dd = String(d.getDate()).padStart(2, '0')
  return `${yyyy}-${mm}-${dd}`
}

function formatDateToDMY(dateStr) {
  if (!dateStr) return ''
  const localDate = toLocalDateStr(dateStr)
  if (!localDate) return dateStr
  const parts = localDate.split('-')
  if (parts.length === 3) {
    return `${parts[2]}/${parts[1]}/${parts[0]}`
  }
  return dateStr
}

const waitlistStartDate = ref(new Date())
const waitlistEndDate = ref(new Date())

watch(showWaitingList, (newVal) => {
  if (newVal) {
    if (startDate.value) waitlistStartDate.value = new Date(startDate.value)
    if (endDate.value) waitlistEndDate.value = new Date(endDate.value)
  }
})

watch([startDate, endDate], ([newStart, newEnd]) => {
  if (newStart) waitlistStartDate.value = new Date(newStart)
  if (newEnd) waitlistEndDate.value = new Date(newEnd)
}, { immediate: true })

const showWaitlistDatePickerPopover = ref(false)
const tempWaitlistStartDateStr = ref('')
const tempWaitlistEndDateStr = ref('')

const waitlistDateRangeText = computed(() => {
  const start = formatDateToDMY(formatDateStr(waitlistStartDate.value))
  const end = formatDateToDMY(formatDateStr(waitlistEndDate.value))
  return `${start} ~ ${end}`
})

function toggleWaitlistDatePickerPopover() {
  showWaitlistDatePickerPopover.value = !showWaitlistDatePickerPopover.value
  if (showWaitlistDatePickerPopover.value) {
    tempWaitlistStartDateStr.value = formatDateStr(waitlistStartDate.value)
    tempWaitlistEndDateStr.value = formatDateStr(waitlistEndDate.value)
  }
}

function closeWaitlistDatePickerPopover() {
  showWaitlistDatePickerPopover.value = false
}

function saveWaitlistDateRange() {
  if (tempWaitlistStartDateStr.value) {
    waitlistStartDate.value = new Date(tempWaitlistStartDateStr.value)
  }
  if (tempWaitlistEndDateStr.value) {
    waitlistEndDate.value = new Date(tempWaitlistEndDateStr.value)
  }
  showWaitlistDatePickerPopover.value = false
}

const waitingListItems = computed(() => {
  if (!waitlistStartDate.value || !waitlistEndDate.value) return []
  const startStr = formatDateStr(waitlistStartDate.value)
  const endStr = formatDateStr(waitlistEndDate.value)

  return bookings.value.filter(b => {
    if (b.code === 'LOCK') return false
    const isUnassigned = b.isVirtual || !b.room || b.room === 'PM' || b.room === 'Chưa gán' || (typeof b.room === 'number' && b.room < 0)
    const regName = (b.registrationStatusName || '').toLowerCase()
    const isWaitingStatus = regName.includes('wait') || regName.includes('chờ')

    if (!isUnassigned && !isWaitingStatus) return false
    if (!b.checkIn) return false

    const inDateStr = b.checkIn.split(' ')[0]
    return inDateStr >= startStr && inDateStr <= endStr
  }).map(b => {
    const inDate = b.checkIn ? b.checkIn.split(' ')[0] : ''
    const outDate = b.checkOut ? b.checkOut.split(' ')[0] : ''
    return {
      guestName: b.guestName || b.name || 'Mr. Guest',
      checkIn: inDate ? formatDateToDMY(inDate) : '',
      checkOut: outDate ? formatDateToDMY(outDate) : '',
      type: b.typeClass || 'DLXT'
    }
  })
})

// Vietnamese weekdays mapping
const weekDays = ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7']

// Legend items
const legends = [
  { name: 'OOO', class: 'bg-[repeating-linear-gradient(45deg,#3b82f6,#3b82f6_5px,#60a5fa_5px,#60a5fa_10px)] text-white border-blue-400' },
  { name: 'OOS', class: 'bg-[repeating-linear-gradient(45deg,#94a3b8,#94a3b8_5px,#cbd5e1_5px,#cbd5e1_10px)] text-white border-slate-400' },
  { name: 'InHouse', class: 'bg-[#c9eeff] text-[#0369a1] border-[#7dd3fc]' },
  { name: 'Reservation', class: 'bg-[#fef3c7] text-[#b45309] border-[#fde68a]' },
  { name: 'Late Checkout', class: 'bg-[#fef9c3] text-[#854d0e] border-[#fef08a]' },
  { name: 'Guaranteed', class: 'bg-[#dcfce7] text-[#15803d] border-[#bbf7d0]' }
]

// Generate columns for the timeline based on startDate -> endDate range
const days = computed(() => {
  const list = []
  const start = new Date(startDate.value)
  const end = new Date(endDate.value)
  const diffTime = end.getTime() - start.getTime()
  const totalDays = Math.max(Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1, 1)
  for (let i = 0; i < totalDays; i++) {
    const current = new Date(start)
    current.setDate(start.getDate() + i)
    const dayOfWeek = current.getDay()
    
    list.push({
      dateStr: `${String(current.getDate()).padStart(2, '0')}/${String(current.getMonth() + 1).padStart(2, '0')}`,
      dow: weekDays[dayOfWeek],
      isWeekend: dayOfWeek === 0 || dayOfWeek === 6,
      isSunday: dayOfWeek === 0,
      fullDate: current
    })
  }
  return list
})

// Mock Rooms (Fallback if store is empty)
const mockRoomsList = [
  { room: '401', type: 'DLXD Double', hasBroom: false },
  { room: '402', type: 'DLXTB Twin', hasBroom: true },
  { room: '403', type: 'DLXTB Twin', hasBroom: true },
  { room: '404', type: 'SUPT Twin', hasBroom: true },
  { room: '405', type: 'FAM Family', hasBroom: false },
  { room: '406', type: 'SUPT Twin', hasBroom: false },
  { room: '407', type: 'SUPTR Triple', hasBroom: true },
  { room: '408', type: 'SUPD Double', hasBroom: false },
  { room: '409', type: 'SUPTR Triple', hasBroom: true },
  { room: '410', type: 'SUPT Twin', hasBroom: false },
  { room: '411', type: 'DLXDB Double', hasBroom: false },
  { room: '412', type: 'DLXDB Double', hasBroom: false },
  { room: '501', type: 'DLXD Double', hasBroom: false },
  { room: '502', type: 'DLXTB Twin', hasBroom: false },
  { room: '503', type: 'DLXTB Twin', hasBroom: false },
  { room: '504', type: 'SUPT Twin', hasBroom: false },
  { room: '505', type: 'FAM Family', hasBroom: false },
  { room: '506', type: 'SUPT Twin', hasBroom: false }
]

// Computed dbRooms - queries roomStore, falls back to mock list if empty
const dbRooms = computed(() => {
  const currentLocks = roomLocks.value
  let list = []
  if (!roomStore.rooms || roomStore.rooms.length === 0) {
    // Only fall back to mock list if store has finished loading and is still empty
    if (!roomStore.loading) {
      list = mockRoomsList.map(r => {
        const type = r.type ? r.type.split(' ')[0] : ''
        let shape = 'Family'
        if (r.type && (r.type.includes('Double') || r.type.includes('D'))) shape = 'Double'
        else if (r.type && (r.type.includes('Twin') || r.type.includes('TB'))) shape = 'Twin'
        else if (r.type && (r.type.includes('Triple') || r.type.includes('TR'))) shape = 'Triple'

        const hasBroom = r.hasBroom
        const hasSparkles = !r.hasBroom && (r.room === '411' || r.room === '412' || r.room === '401')

        return {
          room: r.room,
          type,
          floor: r.room.substring(0, r.room.length - 2) || '1',
          shape,
          hasBroom,
          hasSparkles,
          isVirtual: false,
          active_locks: [],
          orders: 0,
          classOrders: 0
        }
      })
    } else {
      list = []
    }
  } else {
    list = roomStore.rooms.map(r => {
      const type = r.room_class?.code || r.room_type || ''
      let shape = r.room_form?.name || r.room_form?.code || ''
      if (!shape) {
        const tLower = (type + ' ' + (r.room_class?.name || '')).toLowerCase()
        if (tLower.includes('double') || tLower.includes('d')) shape = 'Double'
        else if (tLower.includes('twin') || tLower.includes('tb')) shape = 'Twin'
        else if (tLower.includes('triple') || tLower.includes('tr')) shape = 'Triple'
        else shape = 'Family'
      }

      return {
        room: r.room_number,
        type,
        floor: r.floor || r.room_number.substring(0, r.room_number.length - 2) || '1',
        shape,
        status: r.status,
        is_clean: r.is_clean !== undefined ? r.is_clean : true,
        lock_type: r.status === 'maintenance' ? r.lock_type : null,
        hasBroom: r.status === 'dirty' || !r.is_clean,
        hasSparkles: !!r.is_clean && r.status === 'available' && !r.booking_status,
        isVirtual: false,
        active_locks: r.active_locks ? [...r.active_locks] : [],
        orders: r.orders || 0,
        classOrders: r.room_class?.orders || 0,
        classId: r.room_class_id
      }
    })
  }

  // Combine native locks and local locks
  list.forEach(room => {
    if (!room.active_locks) room.active_locks = []
    const matchingLocalLocks = currentLocks.filter(l => String(l.room) === String(room.room))
    matchingLocalLocks.forEach(l => {
      if (!room.active_locks.some(exist => exist.lock_start_date === l.lock_start_date && exist.lock_end_date === l.lock_end_date)) {
        room.active_locks.push({
          lock_start_date: l.lock_start_date,
          lock_end_date: l.lock_end_date,
          lock_type: l.lock_type,
          lock_reason: l.lock_reason
        })
      }
    })
  })

  // 2. Append virtual rooms from active bookings
  const virtualRooms = new Set()
  bookings.value.forEach(bk => {
    if (bk.isVirtual) {
      virtualRooms.add(bk.room)
    }
  })

  virtualRooms.forEach(vRoom => {
    if (!list.some(r => r.room === vRoom)) {
      const bk = bookings.value.find(b => b.room === vRoom)
      const type = bk ? bk.typeClass || 'DLXD' : 'DLXD'
      
      const foundRoom = roomStore.rooms ? roomStore.rooms.find(r => (r.room_class?.code || r.room_type) === type) : null
      const classId = foundRoom ? foundRoom.room_class_id : null
      const classOrders = foundRoom ? (foundRoom.room_class?.orders || 0) : 0

      const physicalMatch = list.find(r => r.type === type && !r.isVirtual)
      let shape = physicalMatch ? physicalMatch.shape : (foundRoom?.room_form?.name || foundRoom?.room_form?.code || '')
      if (!shape) {
        const tLower = type.toLowerCase()
        if (tLower.includes('double') || tLower.includes('d')) shape = 'Double'
        else if (tLower.includes('twin') || tLower.includes('tb')) shape = 'Twin'
        else if (tLower.includes('triple') || tLower.includes('tr')) shape = 'Triple'
        else shape = 'Family'
      }

      list.push({
        room: vRoom,
        type,
        floor: '99',
        shape,
        hasBroom: false,
        hasSparkles: false,
        isVirtual: true,
        active_locks: [],
        orders: 999999,
        classOrders,
        classId
      })
    }
  })

  // Filter by selected room type
  if (selectedRoomTypes.value && selectedRoomTypes.value.length > 0) {
    list = list.filter(r => selectedRoomTypes.value.includes(r.type))
  }

  // Helper for sorting rooms by orders ASC, then room number
  const compareByRoomOrders = (rA, rB) => {
    const ordA = rA.orders !== undefined ? Number(rA.orders) : 0
    const ordB = rB.orders !== undefined ? Number(rB.orders) : 0
    if (ordA !== ordB) {
      return ordA - ordB
    }
    const numA = parseInt(rA.room)
    const numB = parseInt(rB.room)
    if (!isNaN(numA) && !isNaN(numB)) {
      return numA - numB
    }
    return rA.room.localeCompare(rB.room)
  }

  // Sort and group according to activeGroupSetting
  return [...list].sort((a, b) => {
    if (activeGroupSetting.value === 'Loại phòng') {
      // 1. Group by Room Class orders (or type code)
      const oA = a.classOrders !== undefined ? Number(a.classOrders) : 0
      const oB = b.classOrders !== undefined ? Number(b.classOrders) : 0
      if (oA !== oB) return oA - oB

      const typeComp = (a.type || '').localeCompare(b.type || '')
      if (typeComp !== 0) return typeComp

      // 2. Within the same Room Class group: Physical rooms first, then Virtual rooms
      if (a.isVirtual !== b.isVirtual) {
        return a.isVirtual ? 1 : -1
      }

      // 3. If both physical, sort by room orders ASC
      if (!a.isVirtual && !b.isVirtual) {
        return compareByRoomOrders(a, b)
      }

      // 4. If both virtual, sort by room / booking code
      return a.room.localeCompare(b.room)

    } else if (activeGroupSetting.value === 'Loại giường' || activeGroupSetting.value === 'Dạng phòng') {
      // 1. Group by Shape / Bed Type name
      const sA = a.shape || ''
      const sB = b.shape || ''
      const comp = sA.localeCompare(sB)
      if (comp !== 0) return comp

      // 2. Within the same Bed Type group: Physical rooms first, then Virtual rooms
      if (a.isVirtual !== b.isVirtual) {
        return a.isVirtual ? 1 : -1
      }

      // 3. If both physical, sort by room orders ASC
      if (!a.isVirtual && !b.isVirtual) {
        return compareByRoomOrders(a, b)
      }

      // 4. If both virtual, sort by room / booking code
      return a.room.localeCompare(b.room)

    } else if (activeGroupSetting.value === 'Tầng') {
      // Virtual rooms always go to the bottom for Floor grouping
      if (a.isVirtual !== b.isVirtual) {
        return a.isVirtual ? 1 : -1
      }
      if (a.isVirtual && b.isVirtual) {
        return a.room.localeCompare(b.room)
      }
      const fA = parseInt(a.floor) || 0
      const fB = parseInt(b.floor) || 0
      if (fA !== fB) return fA - fB
      return compareByRoomOrders(a, b)

    } else {
      // Default ('Phòng'): Virtual rooms always go to the bottom
      if (a.isVirtual !== b.isVirtual) {
        return a.isVirtual ? 1 : -1
      }
      if (a.isVirtual && b.isVirtual) {
        return a.room.localeCompare(b.room)
      }
      return compareByRoomOrders(a, b)
    }
  })
})

// Dynamic room types list for dropdown
const roomTypes = computed(() => {
  const typesMap = new Map()
  if (roomStore.rooms && roomStore.rooms.length > 0) {
    roomStore.rooms.forEach(r => {
      const code = r.room_class?.code || r.room_type
      const name = r.room_type_name || r.room_class?.name || code
      const order = r.room_class?.orders !== undefined ? Number(r.room_class.orders) : 9999
      if (code) {
        typesMap.set(code, { name, order })
      }
    })
  } else if (!roomStore.loading) {
    mockRoomsList.forEach((r, idx) => {
      if (r.type) {
        const code = r.type.split(' ')[0]
        typesMap.set(code, { name: r.type, order: idx })
      }
    })
  }
  return [...typesMap.entries()].map(([code, data]) => ({
    code,
    name: data.name,
    order: data.order
  })).sort((a, b) => {
    if (a.order !== b.order) {
      return a.order - b.order
    }
    return a.name.localeCompare(b.name)
  })
})

// Mock bookings as initial/fallback data
const fallbackBookings = [
  // Room 402
  {
    room: '402',
    checkIn: '2026-07-10 14:00',
    checkOut: '2026-07-15 12:00',
    type: 'InHouse',
    code: 'GAL5333',
    name: 'TRAVEL CONCIERGE - 2637449 (COMMITMENT)',
    company: 'TRAVEL CONCIERGE',
    guestName: 'Mr. Group Guest',
    nights: 5,
    checkoutHour: '12:00',
    adults: 2,
    children: 1,
    extraBed: 0,
    specialRequest: 'Commitment booking',
    price: '890,000',
    label: 'CIERGE - (COMMITME...'
  },
  // Room 403
  {
    room: '403',
    checkIn: '2026-07-15 14:00',
    checkOut: '2026-07-24 12:00',
    type: 'Guaranteed',
    code: 'GAL5169',
    name: 'ANEX TOUR - 112418515',
    company: 'ANEX TOUR',
    guestName: 'Mr. Guest 1',
    nights: 9,
    checkoutHour: '12:00',
    adults: 2,
    children: 0,
    extraBed: 0,
    specialRequest: '',
    price: '890,000',
    label: 'GAL 5169 - ANEX TOUR - 112418515 - ANEX TOUR -      890,000đ'
  },
  // Room 404
  {
    room: '404',
    checkIn: '2026-07-10 14:00',
    checkOut: '2026-07-15 12:00',
    type: 'Guaranteed',
    code: 'GAL4616',
    name: 'THIÊN LÂM',
    company: 'THIÊN LÂM',
    guestName: 'Mr. Guest 4616',
    nights: 5,
    checkoutHour: '12:00',
    adults: 1,
    children: 0,
    extraBed: 0,
    specialRequest: '',
    price: '750,000',
    label: 'GAL 4616 - THIÊN L...'
  },
  {
    room: '404',
    checkIn: '2026-07-24 14:00',
    checkOut: '2026-07-29 12:00',
    type: 'Guaranteed',
    code: 'GAL4822',
    name: 'ANEX TOUR',
    company: 'ANEX TOUR',
    guestName: 'Mr. Guest 4822',
    nights: 5,
    checkoutHour: '12:00',
    adults: 2,
    children: 0,
    extraBed: 0,
    specialRequest: '',
    price: '890,000',
    label: 'GAL 4822 - ANEX TOUR...'
  },
  // Room 405
  {
    room: '405',
    checkIn: '2026-07-10 14:00',
    checkOut: '2026-07-15 12:00',
    type: 'Guaranteed',
    code: 'GAL1669',
    name: 'TRIP.COM - 16691081',
    company: 'TRIP.COM',
    guestName: 'Mr. Trip Guest',
    nights: 5,
    checkoutHour: '12:00',
    adults: 2,
    children: 0,
    extraBed: 0,
    specialRequest: '',
    price: '890,000',
    label: '- TRIP.COM - 16691081...'
  },
  {
    room: '405',
    checkIn: '2026-07-24 14:00',
    checkOut: '2026-07-29 12:00',
    type: 'Guaranteed',
    code: 'GAL4822',
    name: 'ANEX TOUR',
    company: 'ANEX TOUR',
    guestName: 'Mr. Guest 4822',
    nights: 5,
    checkoutHour: '12:00',
    adults: 2,
    children: 0,
    extraBed: 0,
    specialRequest: '',
    price: '890,000',
    label: 'GAL 4822 - ANEX TOUR...'
  },
  // Room 406
  {
    room: '406',
    checkIn: '2026-07-10 14:00',
    checkOut: '2026-07-15 12:00',
    type: 'Guaranteed',
    code: 'GAL4616',
    name: 'THIÊN LÂM',
    company: 'THIÊN LÂM',
    guestName: 'Mr. Guest 4616',
    nights: 5,
    checkoutHour: '12:00',
    adults: 1,
    children: 0,
    extraBed: 0,
    specialRequest: '',
    price: '750,000',
    label: 'GAL 4616 - THIÊN L...'
  },
  {
    room: '406',
    checkIn: '2026-07-24 14:00',
    checkOut: '2026-07-29 12:00',
    type: 'Guaranteed',
    code: 'GAL4822',
    name: 'ANEX TOUR',
    company: 'ANEX TOUR',
    guestName: 'Mr. Guest 4822',
    nights: 5,
    checkoutHour: '12:00',
    adults: 2,
    children: 0,
    extraBed: 0,
    specialRequest: '',
    price: '890,000',
    label: 'GAL 4822 - ANEX TOUR...'
  },
  // Room 407
  {
    room: '407',
    checkIn: '2026-07-18 14:00',
    checkOut: '2026-07-24 12:00',
    type: 'Reservation',
    code: 'GAL5549',
    name: 'ANEX TOUR - 112418515',
    company: 'ANEX TOUR',
    guestName: 'Mr. Guest 5549',
    nights: 6,
    checkoutHour: '12:00',
    adults: 2,
    children: 0,
    extraBed: 0,
    specialRequest: '',
    price: '890,000',
    label: 'GAL 5549 - ANEX TOUR - 112...'
  },
  // Room 408
  {
    room: '408',
    checkIn: '2026-07-10 14:00',
    checkOut: '2026-07-18 12:00',
    type: 'InHouse',
    code: 'GAL_IN_408',
    name: 'TRAVEL CONCIERGE - 2639638 (COMMITMENT)',
    company: 'TRAVEL CONCIERGE',
    guestName: 'Mr. Travel Guest',
    nights: 8,
    checkoutHour: '12:00',
    adults: 2,
    children: 0,
    extraBed: 0,
    specialRequest: '',
    price: '890,000',
    label: 'TRAVEL CONCIERGE - (COMMITMENT)...'
  },
  {
    room: '408',
    checkIn: '2026-07-20 14:00',
    checkOut: '2026-07-24 12:00',
    type: 'Guaranteed',
    code: 'GAL5853',
    name: 'ANEX TOUR',
    company: 'ANEX TOUR',
    guestName: 'Mr. Guest 5853',
    nights: 4,
    checkoutHour: '12:00',
    adults: 2,
    children: 0,
    extraBed: 0,
    specialRequest: '',
    price: '890,000',
    label: 'GAL 5853 - ANEX TOUR...'
  },
  // Room 409
  {
    room: '409',
    checkIn: '2026-07-10 14:00',
    checkOut: '2026-07-15 12:00',
    type: 'Guaranteed',
    code: 'GAL4616',
    name: 'THIÊN LÂM',
    company: 'THIÊN LÂM',
    guestName: 'Mr. Guest 4616',
    nights: 5,
    checkoutHour: '12:00',
    adults: 1,
    children: 0,
    extraBed: 0,
    specialRequest: '',
    price: '750,000',
    label: 'GAL 4616 - THIÊN L...'
  },
  {
    room: '409',
    checkIn: '2026-07-24 14:00',
    checkOut: '2026-07-29 12:00',
    type: 'Guaranteed',
    code: 'GAL4822',
    name: 'ANEX TOUR',
    company: 'ANEX TOUR',
    guestName: 'Mr. Guest 4822',
    nights: 5,
    checkoutHour: '12:00',
    adults: 2,
    children: 0,
    extraBed: 0,
    specialRequest: '',
    price: '890,000',
    label: 'GAL 4822 - ANEX TOUR...'
  }
]

// State to store real bookings (initially empty to prevent mock flash)
const bookings = ref([])
const roomLocks = ref([])
const loadingBookings = ref(false)

// Function to fetch actual bookings from backend
async function loadBookings() {
  try {
    loadingBookings.value = true
    emit('loading', true)
    
    const formatDateStr = (d) => {
      const year = d.getFullYear()
      const month = String(d.getMonth() + 1).padStart(2, '0')
      const day = String(d.getDate()).padStart(2, '0')
      return `${year}-${month}-${day}`
    }

    const startRange = new Date(startDate.value)
    startRange.setDate(startRange.getDate() - 15) // fetch older bookings that overlap
    const endRange = new Date(endDate.value)
    endRange.setDate(endRange.getDate() + 15) // fetch future bookings

    const res = await fetchBookings({
      from_date: formatDateStr(startRange),
      to_date: formatDateStr(endRange)
    })

    if (res && res.data && res.data.success && res.data.data && res.data.data.length > 0) {
      const apiBookings = []
      
      res.data.data.forEach(b => {
        if (Number(b.status) === 3) return
        if (!b.booking_rooms) return

        b.booking_rooms.forEach(br => {
          if (![0, 1, 2].includes(Number(br.status))) return
          const isVirtual = !br.room_number
          const roomVal = br.room_number || br.id
          if (!roomVal) return
          const typeClass = br.room_class?.code || br.room_type || 'DLXD'

          // Map status values to matching UI classes
          let type = 'Reservation'
          if (br.status === 1) {
            type = 'InHouse'
          } else if (br.status === 2) {
            type = 'CheckedOut'
          } else if (br.status === 0) {
            type = 'Reservation'
          } else {
            const regStatus = b.registration_status?.name || ''
            if (regStatus.toLowerCase().includes('guaranteed') || regStatus.toLowerCase().includes('đảm bảo')) {
              type = 'Reservation'
            } else if (regStatus.toLowerCase().includes('allotment')) {
              type = 'Allotment'
            } else if (regStatus.toLowerCase().includes('late') || regStatus.toLowerCase().includes('trễ')) {
              type = 'Late Checkout'
            } else {
              type = 'Reservation'
            }
          }

          let guestName = b.contact_name || b.booking_name || 'Guest'
          if (br.guests && br.guests.length > 0 && br.guests[0].guest) {
            guestName = br.guests[0].guest.full_name || guestName
          }

          const priceVal = br.rate ? Number(br.rate) : 0
          const priceStr = priceVal > 0 ? priceVal.toLocaleString() : '0'
          const companyName = b.company?.company_name || 'Khách lẻ'
          const label = `${b.booking_code} - ${b.booking_name} - ${companyName} - ${priceStr}đ`

          const arrivalStr = br.arrival_date ? toLocalDateStr(br.arrival_date) : (b.arrival_date ? toLocalDateStr(b.arrival_date) : '')
          const departureStr = br.departure_date ? toLocalDateStr(br.departure_date) : (b.departure_date ? toLocalDateStr(b.departure_date) : '')
          const arrivalTime = br.arrival_time || '14:00'
          const departureTime = br.departure_time || '12:00'

          const roomServices = br.services || []
          const roomChargeDue = roomServices
            .filter(s => s.service_code === 'RM' || s.is_room === 1)
            .reduce((sum, s) => sum + (Number(s.rate) * Number(s.quantity)), 0) 
            || (Number(br.rate) * Number(b.num_of_days || 1))

          const serviceChargeDue = roomServices
            .filter(s => s.service_code !== 'RM' && s.is_room !== 1)
            .reduce((sum, s) => sum + (Number(s.rate) * Number(s.quantity)), 0)

          const totalAmount = roomChargeDue + serviceChargeDue

          const activePayments = b.payments ? b.payments.filter(p => p.edit_flag === 0 && p.pack2 === 'DPR') : []
          const depositAmount = activePayments.reduce((sum, p) => sum + Number(p.amount), 0)

          const paidPayments = b.payments ? b.payments.filter(p => p.edit_flag === 0) : []
          const paidAmount = paidPayments.reduce((sum, p) => sum + Number(p.amount), 0)

          const balance = Math.max(0, totalAmount - paidAmount)

          apiBookings.push({
            room: roomVal,
            checkIn: `${arrivalStr} ${arrivalTime}`,
            checkOut: `${departureStr} ${departureTime}`,
            type,
            typeClass,
            code: b.booking_code,
            name: b.booking_name,
            company: b.company?.company_name || 'Khách lẻ',
            guestName,
            registrationStatusName: b.registration_status?.name || '',
            nights: Math.round((new Date(departureStr) - new Date(arrivalStr)) / (1000 * 60 * 60 * 24)) || 1,
            isDoNotMove: !!br.is_do_not_move,
            checkoutHour: departureTime,
            adults: br.adults || 2,
            children: br.children ? br.children.filter(c => c.age_group === 'child').length : 0,
            babies: br.children ? br.children.filter(c => c.age_group === 'baby').length : 0,
            extraBed: br.extra_bed_qty || 0,
            specialRequest: b.special_requests || br.note || '',
            price: priceStr,
            label,
            isVirtual,
            bookingId: b.id,
            bookingRoomId: br.id,
            phone: b.contact_phone || '',
            roomChargeDue,
            serviceChargeDue,
            totalAmount,
            depositAmount,
            paidAmount,
            balance
          })
        })
      })

      if (apiBookings.length > 0) {
        bookings.value = apiBookings
      } else {
        bookings.value = []
      }
    } else {
      bookings.value = []
    }
  } catch (err) {
    console.error('Failed to load real bookings, keeping fallbacks:', err)
  } finally {
    loadingBookings.value = false
    emit('loading', false)
  }
}

function togglePlanSettings(event) {
  event.stopPropagation()
  showPlanSettings.value = !showPlanSettings.value
}

function closePlanSettings() {
  showPlanSettings.value = false
}

function closeDatePickerPopover() {
  showDatePickerPopover.value = false
}

function toggleCompanyFilterDropdown(event) {
  event.stopPropagation()
  showCompanyFilterDropdown.value = !showCompanyFilterDropdown.value
}

function handleWindowClick(event) {
  const container = document.querySelector('.quick-booking-company-search-container')
  if (container && !container.contains(event.target)) {
    showQuickBookingCompanyDropdown.value = false
  }
  
  const compDropdownContainer = document.querySelector('#filter-company-dropdown-container')
  if (compDropdownContainer && !compDropdownContainer.contains(event.target)) {
    showCompanyFilterDropdown.value = false
  }

  if (showColorPicker.value && colorPickerRef.value && !colorPickerRef.value.contains(event.target)) {
    const isPill = event.target.closest('.px-2.py-0.5.border.rounded')
    if (!isPill) {
      showColorPicker.value = false
    }
  }
}

let bc = null

onMounted(async () => {
  // 1. Fetch system date, user settings, and hotel settings in parallel
  let sysDateStr = null
  try {
    const [sysDateRes, userSettingsRes, hotelSettingsRes] = await Promise.allSettled([
      fetchSystemDate(),
      fetchUserSettings(),
      fetchHotelSettings()
    ])

    // Apply system date
    if (sysDateRes.status === 'fulfilled' && sysDateRes.value?.data?.data?.system_date) {
      sysDateStr = sysDateRes.value.data.data.system_date
    }
    systemDate.value = sysDateStr || formatDateStr(new Date())

    // Apply user settings
    if (userSettingsRes.status === 'fulfilled' && userSettingsRes.value?.data?.data) {
      const s = userSettingsRes.value.data.data
      if (s.sort_option) activeGroupSetting.value = s.sort_option
      if (typeof s.night_view === 'boolean') showNights.value = s.night_view
      if (typeof s.show_notes === 'boolean') showNotes.value = s.show_notes
    }

    // Apply hotel settings (custom colors)
    if (hotelSettingsRes.status === 'fulfilled' && hotelSettingsRes.value?.data?.data) {
      hotelSettings.value = hotelSettingsRes.value.data.data
    }
  } catch (err) {
    console.error('Failed to load initial settings:', err)
  }

  // 2. Initialize date range: system date + 30 days
  const baseDate = sysDateStr ? new Date(sysDateStr) : new Date()
  const endDateVal = new Date(baseDate)
  endDateVal.setDate(baseDate.getDate() + 29)

  startDate.value = baseDate
  endDate.value = endDateVal
  tempStartDateStr.value = formatDateStr(baseDate)
  tempEndDateStr.value = formatDateStr(endDateVal)
  dateRangeText.value = `${formatDateToDMY(formatDateStr(baseDate))} ~ ${formatDateToDMY(formatDateStr(endDateVal))}`

  // 3. Mark settings loaded so watchers can start auto-saving
  settingsLoaded.value = true

  // 4. Load data
  roomStore.fetchRooms()
  loadBookings()
  loadCompanies()
  loadRateCodes()
  
  window.addEventListener('click', closeContextMenu)
  window.addEventListener('click', closePlanSettings)
  window.addEventListener('click', closeDatePickerPopover)
  window.addEventListener('click', closeWaitlistDatePickerPopover)
  window.addEventListener('click', handleWindowClick)

  if (typeof BroadcastChannel !== 'undefined') {
    bc = new BroadcastChannel('pms-room-updates')
    bc.onmessage = (event) => {
      if (event.data === 'rooms-updated') {
        roomStore.fetchRooms()
        loadBookings()
      }
    }
  }
})

onBeforeUnmount(() => {
  window.removeEventListener('click', closeContextMenu)
  window.removeEventListener('click', closePlanSettings)
  window.removeEventListener('click', closeDatePickerPopover)
  window.removeEventListener('click', closeWaitlistDatePickerPopover)
  window.removeEventListener('click', handleWindowClick)
  if (bc) {
    bc.close()
  }
})

// Listen to date changes to reload data
watch([startDate, endDate], () => {
  loadBookings()
})

// Auto-save user settings on change (debounced)
let settingsSaveTimer = null
watch([activeGroupSetting, showNights, showNotes], () => {
  if (!settingsLoaded.value) return
  clearTimeout(settingsSaveTimer)
  settingsSaveTimer = setTimeout(async () => {
    try {
      await updateUserSettings({
        sort_option: activeGroupSetting.value,
        night_view: showNights.value,
        show_notes: showNotes.value,
      })
    } catch (err) {
      console.error('Failed to save user settings:', err)
    }
  }, 500)
})

// Helper to parse "YYYY-MM-DD HH:MM" strings as local Dates to avoid timezone shifts
const parseDateTime = (str) => {
  if (!str) return new Date()
  const parts = str.split(' ')
  const dateParts = parts[0].split('-').map(Number)
  const timeStr = parts[1] || '00:00'
  const timeParts = timeStr.split(':').map(Number)
  // Month is 0-indexed in JS Date constructor
  return new Date(
    dateParts[0],
    dateParts[1] - 1,
    dateParts[2],
    timeParts[0] || 0,
    timeParts[1] || 0,
    timeParts[2] || 0
  )
}

const formatMoney = (val) => {
  const num = Number(val)
  if (isNaN(num)) return '0'
  return num.toLocaleString('vi-VN')
}


function matchesSelectedStatus(bk) {
  if (selectedStatuses.value.length === 0) return true
  const mapped = []
  if (bk.type === 'Reservation' || bk.type === 'Guaranteed' || bk.type === 'Allotment' || bk.type === 'None Guaranteed') {
    mapped.push('Đã đặt')
  }
  if (bk.type === 'InHouse') {
    mapped.push('Đang ở')
  }
  if (bk.type === 'Late Checkout' || bk.type === 'CheckedOut') {
    mapped.push('Đã trả')
  }
  return mapped.some(s => selectedStatuses.value.includes(s))
}

const visibleLegends = computed(() => {
  return legends
})

function getLegendDotClass(name) {
  if (name === 'OOO') return 'bg-blue-500'
  if (name === 'OOS') return 'bg-slate-400'
  if (name === 'InHouse') return 'bg-sky-400'
  if (name === 'Reservation') return 'bg-amber-400'
  if (name === 'Late Checkout') return 'bg-yellow-400'
  if (name === 'Guaranteed') return 'bg-emerald-500'
  return 'bg-slate-400'
}

function isCellOccupied(roomNo, date) {
  const rKey = String(roomNo)
  const roomBookings = processedBookings.value[rKey] || processedBookings.value[roomNo] || []
  if (!roomBookings || roomBookings.length === 0) return false

  const targetDateStr = formatDateStr(date)

  return roomBookings.some(bk => {
    const checkInDateStr = formatDateStr(parseDateTime(bk.checkIn))
    const checkOutDateStr = formatDateStr(parseDateTime(bk.checkOut))

    const isLockItem = bk.code === 'LOCK' || bk.type === 'OOO' || bk.type === 'OOS'
    if (isLockItem) {
      return targetDateStr >= checkInDateStr && targetDateStr <= checkOutDateStr
    } else {
      if (checkInDateStr === checkOutDateStr) {
        return targetDateStr === checkInDateStr
      }
      return targetDateStr >= checkInDateStr && targetDateStr < checkOutDateStr
    }
  })
}

function isCellSelected(roomNo, date) {
  const dateStr = formatDateStr(date)
  return selectedCells.value.some(c => c.room === roomNo && c.date === dateStr)
}

function handleCellMouseDown(roomItem, dayItem, dayIdx, roomIdx, event) {
  if (event.button !== 0) return // Left click only
  if (!event.ctrlKey && !event.metaKey) return
  if (roomItem.isVirtual || isCellOccupied(roomItem.room, dayItem.fullDate)) return

  event.preventDefault()
  isDragSelecting.value = true
  dragSelectionStart.value = { roomIdx, dayIdx, roomNo: roomItem.room }

  // Save current selection snapshot before this drag operation
  initialSelectedCells.value = [...selectedCells.value]

  updateDragSelection(roomIdx, dayIdx)

  window.addEventListener('mouseup', handleGlobalCellMouseUp)
}

function handleCellMouseEnter(roomItem, dayItem, dayIdx, roomIdx, event) {
  if (!isDragSelecting.value || !dragSelectionStart.value) return
  if (roomItem.isVirtual) return

  updateDragSelection(roomIdx, dayIdx)
}

function updateDragSelection(currentRoomIdx, currentDayIdx) {
  const start = dragSelectionStart.value
  if (!start) return

  const minRoomIdx = Math.min(start.roomIdx, currentRoomIdx)
  const maxRoomIdx = Math.max(start.roomIdx, currentRoomIdx)
  const minDayIdx = Math.min(start.dayIdx, currentDayIdx)
  const maxDayIdx = Math.max(start.dayIdx, currentDayIdx)

  // Start with snapshot before current drag
  const newSelection = [...initialSelectedCells.value]

  for (let rIdx = minRoomIdx; rIdx <= maxRoomIdx; rIdx++) {
    const roomObj = dbRooms.value[rIdx]
    if (!roomObj || roomObj.isVirtual) continue
    const roomNo = roomObj.room

    for (let dIdx = minDayIdx; dIdx <= maxDayIdx; dIdx++) {
      const dayObj = days.value[dIdx]
      if (!dayObj) continue

      if (isCellOccupied(roomNo, dayObj.fullDate)) continue

      const dateStr = formatDateStr(dayObj.fullDate)
      const exists = newSelection.some(c => c.room === roomNo && c.date === dateStr)
      if (!exists) {
        newSelection.push({
          room: roomNo,
          date: dateStr,
          dayIdx: dIdx
        })
      }
    }
  }

  selectedCells.value = newSelection
}

function handleGlobalCellMouseUp() {
  if (isDragSelecting.value) {
    isDragSelecting.value = false
    dragSelectionStart.value = null
    initialSelectedCells.value = []
  }
  window.removeEventListener('mouseup', handleGlobalCellMouseUp)
}

function handleCellClick(roomItem, dayItem, dayIdx, event) {
  if (roomItem.isVirtual || isCellOccupied(roomItem.room, dayItem.fullDate)) {
    return
  }

  // Selection is ONLY allowed when holding Ctrl or Cmd key
  if (!event.ctrlKey && !event.metaKey) {
    selectedCells.value = []
  }
}

const selectedRoomsRanges = computed(() => {
  if (selectedCells.value.length === 0) return []
  
  const groups = {}
  selectedCells.value.forEach(c => {
    if (!groups[c.room]) groups[c.room] = []
    groups[c.room].push(c.date)
  })
  
  const result = []
  Object.keys(groups).forEach(roomNo => {
    const dateStrings = [...new Set(groups[roomNo])].sort()
    if (dateStrings.length === 0) return

    let currentBlock = [dateStrings[0]]

    for (let i = 1; i < dateStrings.length; i++) {
      const prevDate = new Date(dateStrings[i - 1])
      const currDate = new Date(dateStrings[i])
      const diffDays = Math.round((currDate.getTime() - prevDate.getTime()) / (1000 * 3600 * 24))

      if (diffDays === 1) {
        currentBlock.push(dateStrings[i])
      } else {
        const checkIn = currentBlock[0]
        const lastDateStr = currentBlock[currentBlock.length - 1]
        const lastDateObj = new Date(lastDateStr)
        const nextDay = new Date(lastDateObj)
        nextDay.setDate(nextDay.getDate() + 1)
        const checkOut = formatDateStr(nextDay)

        result.push({
          room: roomNo,
          checkIn,
          checkOut,
          lastDate: lastDateStr,
          nights: currentBlock.length
        })

        currentBlock = [dateStrings[i]]
      }
    }

    if (currentBlock.length > 0) {
      const checkIn = currentBlock[0]
      const lastDateStr = currentBlock[currentBlock.length - 1]
      const lastDateObj = new Date(lastDateStr)
      const nextDay = new Date(lastDateObj)
      nextDay.setDate(nextDay.getDate() + 1)
      const checkOut = formatDateStr(nextDay)

      result.push({
        room: roomNo,
        checkIn,
        checkOut,
        lastDate: lastDateStr,
        nights: currentBlock.length
      })
    }
  })
  return result
})

const selectedDateRange = computed(() => {
  const ranges = selectedRoomsRanges.value
  return ranges.length > 0 ? ranges[0] : null
})

// Process bookings placement dynamically relative to the days grid
const processedBookings = computed(() => {
  const map = {}
  if (days.value.length === 0) return map

  const visibleStart = new Date(days.value[0].fullDate)
  visibleStart.setHours(0, 0, 0, 0)
  const visibleEnd = new Date(days.value[days.value.length - 1].fullDate)
  visibleEnd.setHours(23, 59, 59, 999)

  const combinedList = [...bookings.value]
  dbRooms.value.forEach(room => {
    if (room.isVirtual) return
    if (room.active_locks && room.active_locks.length > 0) {
      room.active_locks.forEach(lock => {
        combinedList.push({
          room: String(room.room),
          checkIn: lock.lock_start_date,
          checkOut: lock.lock_end_date,
          type: lock.lock_type?.toUpperCase() === 'OOS' ? 'OOS' : 'OOO',
          typeClass: room.type || 'DLXD',
          code: 'LOCK',
          name: lock.lock_reason || 'Bảo trì phòng',
          company: lock.lock_type?.toUpperCase() === 'OOS' ? 'Khóa OOS' : 'Khóa OOO',
          guestName: 'LOCK',
          nights: 1,
          checkoutHour: '12:00',
          adults: 0,
          children: 0,
          babies: 0,
          extraBed: 0,
          specialRequest: lock.lock_reason || '',
          price: '0',
          label: `${lock.lock_type?.toUpperCase() === 'OOS' ? 'OOS' : 'OOO'} - ${lock.lock_reason || 'Bảo trì'}`,
          isVirtual: false,
          bookingRoomId: lock.id || lock.lock_id,
          lockId: lock.id || lock.lock_id,
          phone: '',
          roomChargeDue: 0,
          serviceChargeDue: 0,
          totalAmount: 0,
          depositAmount: 0,
          paidAmount: 0,
          balance: 0
        })
      })
    }
  })

  combinedList.forEach(bk => {
    if (bk.bookingId) {
      if (selectedStatuses.value.length > 0 && !matchesSelectedStatus(bk)) {
        return
      }
      if (selectedCompanies.value.length > 0 && !selectedCompanies.value.includes(bk.company)) {
        return
      }
    }

    let checkInDate = parseDateTime(bk.checkIn)
    let checkOutDate = parseDateTime(bk.checkOut)

    if (resizeState.value && resizeState.value.booking.bookingRoomId === bk.bookingRoomId) {
      checkInDate = resizeState.value.tempCheckIn
      checkOutDate = resizeState.value.tempCheckOut
    }
    
    // Check if there is overlap with visible grid range
    if (checkOutDate < visibleStart || checkInDate > visibleEnd) {
      return
    }

    // Find start index
    let startIdx = 0
    let isCheckInVisible = true
    if (checkInDate >= visibleStart) {
      startIdx = days.value.findIndex(d => {
        const dDate = new Date(d.fullDate)
        return dDate.getDate() === checkInDate.getDate() && 
               dDate.getMonth() === checkInDate.getMonth() && 
               dDate.getFullYear() === checkInDate.getFullYear()
      })
      if (startIdx === -1) {
        startIdx = 0
        isCheckInVisible = false
      }
    } else {
      isCheckInVisible = false
    }

    // Find end index
    let endIdx = days.value.length - 1
    let isCheckOutVisible = true
    if (checkOutDate <= visibleEnd) {
      endIdx = days.value.findIndex(d => {
        const dDate = new Date(d.fullDate)
        return dDate.getDate() === checkOutDate.getDate() && 
               dDate.getMonth() === checkOutDate.getMonth() && 
               dDate.getFullYear() === checkOutDate.getFullYear()
      })
      if (endIdx === -1) {
        endIdx = days.value.length - 1
        isCheckOutVisible = false
      }
    } else {
      isCheckOutVisible = false
    }

    // Left offset ratio (0% to start at the left boundary of the first day cell)
    const leftRatio = showNights.value ? 0 : 0.5

    // Total columns spanned (occupy full cells or half cells)
    const isLockItem = bk.code === 'LOCK' || bk.type === 'OOO' || bk.type === 'OOS'
    const span = Math.max(1, isLockItem ? (endIdx - startIdx + 1) : (endIdx - startIdx))
    const showCheckOutIndicator = !showNights.value && isCheckOutVisible

    // Formatting for tooltip display
    const checkInFormatted = `${String(checkInDate.getDate()).padStart(2, '0')}/${String(checkInDate.getMonth() + 1).padStart(2, '0')}`
    const checkOutFormatted = `${String(checkOutDate.getDate()).padStart(2, '0')}/${String(checkOutDate.getMonth() + 1).padStart(2, '0')}`
    const checkInTimeStr = `${String(checkInDate.getHours()).padStart(2, '0')}:${String(checkInDate.getMinutes()).padStart(2, '0')}`
    const checkOutTimeStr = `${String(checkOutDate.getHours()).padStart(2, '0')}:${String(checkOutDate.getMinutes()).padStart(2, '0')}`

    const formatFullDateTime = (d) => {
      const dd = String(d.getDate()).padStart(2, '0')
      const mm = String(d.getMonth() + 1).padStart(2, '0')
      const yyyy = d.getFullYear()
      const hh = String(d.getHours()).padStart(2, '0')
      const min = String(d.getMinutes()).padStart(2, '0')
      return `${dd}/${mm}/${yyyy} ${hh}:${min}`
    }

    const checkInFull = formatFullDateTime(checkInDate)
    const checkOutFull = formatFullDateTime(checkOutDate)

    const item = {
      ...bk,
      startIndex: startIdx,
      endIndex: endIdx,
      leftRatio,
      span,
      checkInDateStr: checkInFormatted,
      checkInTimeStr,
      checkOutDateStr: checkOutFormatted,
      checkOutTimeStr,
      checkInFull,
      checkOutFull,
      isCheckInVisible,
      isCheckOutVisible,
      showCheckOutIndicator
    }

    if (!map[bk.room]) {
      map[bk.room] = []
    }
    map[bk.room].push(item)
  })

  return map
})

const dynamicStats = computed(() => {
  const numDays = days.value.length
  if (numDays === 0) {
    return {
      occ: [],
      av: [],
      ooo: [],
      occRooms: [],
      avRooms: [],
      oooRooms: [],
      allPeriodAvRooms: [],
      allPeriodOccRooms: [],
      allPeriodOooRooms: [],
      totalRooms: 131,
      totalOccSum: 0,
      totalAvSum: 0,
      totalOooSum: 0,
      totalOccPercent: '0.00'
    }
  }

  const physicalRooms = dbRooms.value.filter(r => !r.isVirtual && !r.is_internal)
  const totalRooms = physicalRooms.length || 131

  const occCounts = Array(numDays).fill(0)
  const oooCounts = Array(numDays).fill(0)
  const avCounts = Array(numDays).fill(0)

  const occRooms = Array(numDays).fill(null).map(() => [])
  const oooRooms = Array(numDays).fill(null).map(() => [])
  const avRooms = Array(numDays).fill(null).map(() => [])

  for (let idx = 0; idx < numDays; idx++) {
    let occ = 0
    let ooo = 0

    physicalRooms.forEach(room => {
      const roomNo = room.room
      const items = processedBookings.value[roomNo] || []
      
      const hasGuest = items.some(item => {
        if (item.type === 'OOO' || item.type === 'OOS') return false
        return idx >= item.startIndex && idx < item.endIndex
      })

      const hasLock = items.some(item => {
        if (item.type !== 'OOO' && item.type !== 'OOS') return false
        return idx >= item.startIndex && idx < item.endIndex
      })

      if (hasGuest) {
        occ++
        occRooms[idx].push(roomNo)
      } else if (hasLock) {
        ooo++
        oooRooms[idx].push(roomNo)
      } else {
        avRooms[idx].push(roomNo)
      }
    })

    occCounts[idx] = occ
    oooCounts[idx] = ooo
    avCounts[idx] = Math.max(0, totalRooms - occ - ooo)
  }

  const totalOccSum = occCounts.reduce((a, b) => a + b, 0)
  const totalAvSum = avCounts.reduce((a, b) => a + b, 0)
  const totalOooSum = oooCounts.reduce((a, b) => a + b, 0)
  const totalPossible = totalRooms * numDays
  const totalOccPercent = totalPossible > 0 ? ((totalOccSum / totalPossible) * 100).toFixed(2) : '0.00'

  const allPeriodAvRooms = physicalRooms.map(r => r.room).filter(roomNo => {
    for (let idx = 0; idx < numDays; idx++) {
      if (!avRooms[idx].includes(roomNo)) return false
    }
    return true
  }).sort((a, b) => a.localeCompare(b))

  const allPeriodOccRooms = physicalRooms.map(r => r.room).filter(roomNo => {
    for (let idx = 0; idx < numDays; idx++) {
      if (occRooms[idx].includes(roomNo)) return true
    }
    return false
  }).sort((a, b) => a.localeCompare(b))

  const allPeriodOooRooms = physicalRooms.map(r => r.room).filter(roomNo => {
    for (let idx = 0; idx < numDays; idx++) {
      if (oooRooms[idx].includes(roomNo)) return true
    }
    return false
  }).sort((a, b) => a.localeCompare(b))

  return {
    occ: occCounts,
    av: avCounts,
    ooo: oooCounts,
    occRooms,
    avRooms,
    oooRooms,
    allPeriodAvRooms,
    allPeriodOccRooms,
    allPeriodOooRooms,
    totalRooms,
    totalOccSum,
    totalAvSum,
    totalOooSum,
    totalOccPercent
  }
})

// Tooltip positioning & hover states
const hoveredBooking = ref(null)
const tooltipX = ref(0)
const tooltipY = ref(0)
const tooltipBelow = ref(false)

watch(showNotes, (newVal) => {
  if (!newVal) {
    hoveredBooking.value = null
  }
})

function showTooltip(booking, event) {
  if (!showNotes.value || draggedBooking.value) return
  hoveredBooking.value = booking
  updateTooltipPosition(event)
}

function updateTooltipPosition(event) {
  if (draggedBooking.value) return
  const tooltipWidth = 360
  const tooltipHeight = 420
  const minTop = 65 // Không bị khuất dưới thanh tabbar ở trên
  const padding = 10

  let rect = null
  if (event && event.currentTarget && event.currentTarget.getBoundingClientRect) {
    rect = event.currentTarget.getBoundingClientRect()
  }

  let left = 0
  let top = 0

  if (rect) {
    // Hiển thị bên PHẢI thẻ booking
    left = rect.right + 12
    if (left + tooltipWidth > window.innerWidth - padding) {
      // Nếu tràn mép phải màn hình thì chuyển sang bên TRÁI thẻ booking
      left = rect.left - tooltipWidth - 12
    }
    top = rect.top
  } else {
    left = event.clientX + 15
    if (left + tooltipWidth > window.innerWidth - padding) {
      left = event.clientX - tooltipWidth - 15
    }
    top = event.clientY
  }

  // Đảm bảo không tràn lề ngang màn hình
  if (left < padding) left = padding
  if (left + tooltipWidth > window.innerWidth - padding) {
    left = window.innerWidth - tooltipWidth - padding
  }

  // Khống chế không bị khuất dưới tabbar top (minTop) và không vượt quá đáy màn hình
  if (top + tooltipHeight > window.innerHeight - padding) {
    top = window.innerHeight - tooltipHeight - padding
  }
  if (top < minTop) {
    top = minTop
  }

  tooltipX.value = left
  tooltipY.value = top
}

function hideTooltip() {
  hoveredBooking.value = null
}

// Map legend colors to Tailwind classes
function getBookingClass(type) {
  if (type === 'InHouse') return 'bg-[#c9eeff] text-black border-[#7dd3fc]'
  if (type === 'Reservation') return 'bg-[#fef3c7] text-black border-[#fde68a]'
  if (type === 'Late Checkout') return 'bg-[#fef9c3] text-black border-[#fef08a]'
  if (type === 'CheckedOut') return 'bg-slate-200 text-black border-slate-300'
  if (type === 'Guaranteed') return 'bg-[#dcfce7] text-black border-[#bbf7d0]'
  if (type === 'Allotment') return 'bg-[#ffedd5] text-black border-[#fed7aa]'
  if (type === 'OOO') return 'bg-[repeating-linear-gradient(45deg,#3b82f6,#3b82f6_5px,#60a5fa_5px,#60a5fa_10px)] text-black border-blue-400'
  if (type === 'OOS') return 'bg-[repeating-linear-gradient(45deg,#94a3b8,#94a3b8_5px,#cbd5e1_5px,#cbd5e1_10px)] text-black border-slate-400'
  return 'bg-[#1e293b] text-black border-slate-700'
}

function getBookingStyle(type) {
  if (type === 'CheckedOut') {
    return { backgroundColor: '#cbd5e1', borderColor: '#94a3b8', color: '#000000' }
  }
  if (!hotelSettings.value) return { color: '#000000' }
  
  if (type === 'Reservation') {
    const color = hotelSettings.value.RoomPlan_ColorRoomReservation || '#E3E8C4'
    return { backgroundColor: color, borderColor: color, color: '#000000' }
  }
  if (type === 'InHouse') {
    const color = hotelSettings.value.RoomPlan_ColorRoomInhouse || '#4a90e2'
    return { backgroundColor: color, borderColor: color, color: '#000000' }
  }
  if (type === 'Late Checkout') {
    const color = hotelSettings.value.RoomPlan_ColorRoomLateCheckout || '#FCF55F'
    return { backgroundColor: color, borderColor: color, color: '#000000' }
  }
  if (type === 'OOO') {
    const color = hotelSettings.value.RoomPlan_ColorOOO || '#107eeb'
    return { backgroundColor: color, borderColor: color, color: '#000000' }
  }
  if (type === 'OOS') {
    const color = hotelSettings.value.RoomPlan_ColorOOS || '#107eeb'
    return { backgroundColor: color, borderColor: color, color: '#000000' }
  }
  return { color: '#000000' }
}

function getLegendStyle(name) {
  return getBookingStyle(name)
}

// Sum stats at bottom
const occStats = [108, 111, 90, 92, 105, 96, 87, 88, 99, 88, 84, 85, 66, 63, 83, 80, 48, 67, 61, 35, 47]
const avStats = [23, 20, 41, 39, 26, 35, 44, 43, 32, 43, 47, 46, 65, 68, 48, 51, 83, 64, 70, 96, 84]

async function handleViewClick() {
  await loadBookings()
  uiStore.showToast('Đã tải lại kế hoạch phòng!', 'success')
}

// Context Menu State
const contextMenu = ref({
  show: false,
  x: 0,
  y: 0,
  type: null, // 'cell' | 'green-booking' | 'blue-booking'
  booking: null,
  room: null,
  day: null
})

function handleCellContextMenu(roomItem, dayItem, event) {
  event.preventDefault()
  
  if (isCellOccupied(roomItem.room, dayItem.fullDate)) {
    return
  }

  const dateStr = formatDateStr(dayItem.fullDate)
  const isAlreadySelected = selectedCells.value.some(c => c.room === roomItem.room && c.date === dateStr)
  
  if (!isAlreadySelected || selectedCells.value.length === 0) {
    selectedCells.value = [{
      room: roomItem.room,
      date: dateStr,
      dayIdx: days.value.findIndex(d => formatDateStr(d.fullDate) === dateStr)
    }]
  }

  const menuType = 'cell-actions'

  let x = event.clientX
  let y = event.clientY
  const menuWidth = 180
  const menuHeight = menuType === 'cell-actions' ? 120 : 50

  if (x + menuWidth > window.innerWidth) {
    x = window.innerWidth - menuWidth - 8
  }
  if (y + menuHeight > window.innerHeight) {
    y = window.innerHeight - menuHeight - 8
  }

  contextMenu.value = {
    show: true,
    x: x,
    y: y,
    type: menuType,
    booking: null,
    room: roomItem,
    day: dayItem
  }
}

function handleBookingDblClick(booking) {
  if (booking.code === 'LOCK') return
  emit('edit-booking', { code: booking.code, id: booking.bookingId })
}

function startResize(bk, type, event) {
  if (bk.code === 'LOCK') return

  if (bk.type === 'InHouse') {
    uiStore.showToast('Phòng đang In-house không được phép thay đổi ngày trên Room Plan.', 'warning')
    return
  }
  if (bk.type === 'CheckedOut') {
    uiStore.showToast('Phòng đã Check-out không được phép thay đổi ngày trên Room Plan.', 'warning')
    return
  }

  // Only allow resizing for reservation types
  const allowedResizeTypes = ['Reservation', 'Guaranteed', 'Tentative', 'Allotment']
  if (!allowedResizeTypes.includes(bk.type)) {
    uiStore.showToast('Chỉ cho phép thay đổi ngày đối với phòng trạng thái Đặt trước (Reservation).', 'warning')
    return
  }

  // Check permission for left handle (start / arrival date change)
  if (type === 'start') {
    const isAllowChangeArrival = Number(hotelSettings.value?.RoomPlan_AllowChangeArrivalDate) === 1
    if (!isAllowChangeArrival) {
      uiStore.showToast('Không cho phép thay đổi ngày đến trên Room Plan (Cấu hình RoomPlan_AllowChangeArrivalDate đang tắt)', 'warning')
      return
    }
  }

  event.preventDefault()
  event.stopPropagation()

  const td = event.target.closest('td')
  const cellWidth = td ? td.getBoundingClientRect().width : 100

  const checkInDate = parseDateTime(bk.checkIn)
  const checkOutDate = parseDateTime(bk.checkOut)

  resizeState.value = {
    booking: bk,
    type,
    initialX: event.clientX,
    cellWidth,
    originalCheckIn: checkInDate,
    originalCheckOut: checkOutDate,
    tempCheckIn: new Date(checkInDate),
    tempCheckOut: new Date(checkOutDate)
  }

  window.addEventListener('mousemove', handleResizeMouseMove)
  window.addEventListener('mouseup', handleResizeMouseUp)
}

function handleResizeMouseMove(event) {
  if (!resizeState.value) return

  const { type, initialX, cellWidth, originalCheckIn, originalCheckOut } = resizeState.value
  const deltaX = event.clientX - initialX
  const cellDelta = Math.round(deltaX / cellWidth)

  if (type === 'start') {
    const newCheckIn = new Date(originalCheckIn)
    newCheckIn.setDate(originalCheckIn.getDate() + cellDelta)
    
    // Validate: cannot exceed or meet checkout (must be at least 1 day before checkout)
    const checkOutLimit = new Date(originalCheckOut)
    checkOutLimit.setDate(originalCheckOut.getDate() - 1)
    
    const newCheckInDay = new Date(newCheckIn).setHours(0, 0, 0, 0)
    const checkOutLimitDay = new Date(checkOutLimit).setHours(0, 0, 0, 0)

    if (newCheckInDay <= checkOutLimitDay) {
      resizeState.value.tempCheckIn = newCheckIn
    }
  } else {
    const newCheckOut = new Date(originalCheckOut)
    newCheckOut.setDate(originalCheckOut.getDate() + cellDelta)
    
    // Validate: cannot be equal to or before checkin (must be at least 1 day after checkin)
    const checkInLimit = new Date(originalCheckIn)
    checkInLimit.setDate(originalCheckIn.getDate() + 1)
    
    const newCheckOutDay = new Date(newCheckOut).setHours(0, 0, 0, 0)
    const checkInLimitDay = new Date(checkInLimit).setHours(0, 0, 0, 0)

    if (newCheckOutDay >= checkInLimitDay) {
      resizeState.value.tempCheckOut = newCheckOut
    }
  }
}

async function handleResizeMouseUp(event) {
  window.removeEventListener('mousemove', handleResizeMouseMove)
  window.removeEventListener('mouseup', handleResizeMouseUp)

  if (!resizeState.value) return
  const state = resizeState.value
  resizeState.value = null

  const checkInStr = formatDateStr(state.tempCheckIn)
  const checkOutStr = formatDateStr(state.tempCheckOut)

  const originalCheckInStr = formatDateStr(state.originalCheckIn)
  const originalCheckOutStr = formatDateStr(state.originalCheckOut)

  if (checkInStr !== originalCheckInStr || checkOutStr !== originalCheckOutStr) {
    try {
      emit('loading', true)
      
      const payload = {
        arrival_date: checkInStr,
        departure_date: checkOutStr
      }

      const res = await updateBookingRoom(state.booking.bookingId, state.booking.bookingRoomId, payload)

      if (res && res.data && res.data.success) {
        if (res.data.warning) {
          uiStore.showToast(res.data.warning, 'warning')
        } else {
          uiStore.showToast('Cập nhật ngày lưu trú thành công!', 'success')
        }
      } else {
        uiStore.showToast(res?.data?.message || 'Không thể cập nhật ngày lưu trú.', 'error')
      }
      await loadBookings()
    } catch (err) {
      console.error(err)
      uiStore.showToast(err.response?.data?.message || 'Không thể cập nhật ngày lưu trú.', 'error')
      await loadBookings()
    } finally {
      emit('loading', false)
    }
  }
}

function handleBookingContextMenu(booking, event) {
  event.preventDefault()
  event.stopPropagation()
  
  let x = event.clientX
  let y = event.clientY
  const menuWidth = 180
  
  let type = 'green-booking'
  if (booking.code === 'LOCK' || booking.type === 'OOO' || booking.type === 'OOS' || booking.isLockRoom) {
    type = 'lock-actions'
  } else if (booking.type === 'InHouse') {
    type = 'blue-booking'
  } else if (booking.type === 'Guaranteed') {
    type = 'green-booking'
  } else {
    type = 'green-booking'
  }

  const menuHeight = type === 'lock-actions' ? 50 : (type === 'green-booking' ? 150 : 50)

  if (x + menuWidth > window.innerWidth) {
    x = window.innerWidth - menuWidth - 8
  }
  if (y + menuHeight > window.innerHeight) {
    y = window.innerHeight - menuHeight - 8
  }

  contextMenu.value = {
    show: true,
    x: x,
    y: y,
    type: type,
    booking: booking,
    room: null,
    day: null
  }
}

function closeContextMenu() {
  contextMenu.value.show = false
}

async function loadDropdownsAndPrices() {
  try {
    const promises = []
    if (allCompanies.value.length === 0) promises.push(fetchCompanies().catch(() => ({ data: [] })))
    else promises.push(Promise.resolve({ data: allCompanies.value }))

    if (rateCodes.value.length === 0) promises.push(fetchRoomRateCodes().catch(() => ({ data: [] })))
    else promises.push(Promise.resolve({ data: rateCodes.value }))

    if (standardRates.value.length === 0) promises.push(http.get('/standard-rates').catch(() => ({ data: { data: [] } })))
    else promises.push(Promise.resolve({ data: standardRates.value }))

    if (allMarkets.value.length === 0) promises.push(fetchMarkets().catch(() => ({ data: [] })))
    else promises.push(Promise.resolve({ data: allMarkets.value }))

    if (allCustomerSources.value.length === 0) promises.push(fetchCustomerSources().catch(() => ({ data: [] })))
    else promises.push(Promise.resolve({ data: allCustomerSources.value }))

    const [compRes, rcRes, srRes, mktRes, srcRes] = await Promise.all(promises)
    
    if (compRes?.data) allCompanies.value = compRes.data.data || compRes.data || []
    if (rcRes?.data) rateCodes.value = rcRes.data.data || rcRes.data || []
    if (srRes?.data) standardRates.value = srRes.data.data || srRes.data || []
    if (mktRes?.data) allMarkets.value = mktRes.data.data || mktRes.data || []
    if (srcRes?.data) allCustomerSources.value = srcRes.data.data || srcRes.data || []

    // If company is selected, auto-set default market & source code
    if (quickBookingCompanySearch.value && allCompanies.value.length > 0) {
      const co = allCompanies.value.find(c => {
        const name = c.company_name || c.name || c.company_code
        return name && String(name).toLowerCase() === String(quickBookingCompanySearch.value).toLowerCase()
      })
      if (co) {
        if (co.market_id) quickBookingForm.value.marketId = co.market_id
        else if (co.market?.id) quickBookingForm.value.marketId = co.market.id
        if (co.customer_source_id) quickBookingForm.value.customerSourceId = co.customer_source_id
        else if (co.customer_source?.id) quickBookingForm.value.customerSourceId = co.customer_source.id
      }
    }
    // Fallback if not set
    if (!quickBookingForm.value.marketId && allMarkets.value.length > 0) {
      quickBookingForm.value.marketId = allMarkets.value[0].id
    }
    if (!quickBookingForm.value.customerSourceId && allCustomerSources.value.length > 0) {
      quickBookingForm.value.customerSourceId = allCustomerSources.value[0].id
    }
  } catch (err) {
    console.error('Error loading quick booking data:', err)
  }
}

loadDropdownsAndPrices()

function getRoomUnitPrice(roomNo, rateCodeMa) {
  const roomObj = roomStore.rooms ? roomStore.rooms.find(r => String(r.room_number) === String(roomNo)) : null
  const roomClassId = roomObj ? roomObj.room_class_id : null
  const roomFormId = roomObj ? roomObj.room_form_id : null

  // 1. Nếu có chọn Rate Code
  if (rateCodeMa && rateCodeMa !== 'Vui lòng chọn giá phòng') {
    const matchedRc = rateCodes.value.find(rc => rc.Ma === rateCodeMa)
    if (matchedRc) {
      const plans = matchedRc.rate_plans || matchedRc.ratePlans || []
      if (plans.length > 0) {
        const roomClassCode = (roomObj?.roomClass?.Ma || roomObj?.room_class_code || roomObj?.roomClass?.name || '').toUpperCase()
        const roomFormName = (roomObj?.roomForm?.name || roomObj?.room_form_name || '').trim()

        for (const plan of plans) {
          if (plan.Period) {
            let periodObj = plan.Period
            if (typeof periodObj === 'string') {
              try { periodObj = JSON.parse(periodObj) } catch (e) { periodObj = {} }
            }
            if (periodObj && typeof periodObj === 'object') {
              const planCode = (plan.Code || 'DEFAULT').toUpperCase()
              const rcCode = roomClassCode
              const rfName = roomFormName
              const rfUpper = rfName.toUpperCase()

              const candidateKeys = [
                `${planCode}_${rcCode}_${rfName}`,
                `${planCode}_${rcCode}_${rfUpper}`,
                `${rateCodeMa}_${rcCode}_${rfName}`,
                `${rateCodeMa}_${rcCode}_${rfUpper}`,
                `${rcCode}_${rfName}`,
                `${rcCode}_${rfUpper}`,
                `${planCode}_${roomClassId}_${rfName}`,
                `${roomClassId}_${rfName}`,
                `${planCode}_${rcCode}`,
                `${rateCodeMa}_${rcCode}`,
                `${rcCode}`,
                `${roomClassId}`,
              ]

              let found = null
              for (const key of candidateKeys) {
                if (key && periodObj[key] !== undefined) {
                  const val = Number(periodObj[key])
                  if (!isNaN(val) && val > 0) { found = val; break; }
                }
              }

              if (found === null && rcCode) {
                const keys = Object.keys(periodObj)
                const matchedKey = keys.find(k => {
                  const kUpper = k.toUpperCase()
                  return kUpper.includes(rcCode) && (!rfUpper || kUpper.includes(rfUpper))
                })
                if (matchedKey && periodObj[matchedKey] !== undefined) {
                  const val = Number(periodObj[matchedKey])
                  if (!isNaN(val) && val > 0) found = val
                }
              }

              if (found !== null) return found
            }
          }
        }
      }
      if (matchedRc.Value && Number(matchedRc.Value) > 0) {
        return Number(matchedRc.Value)
      }
    }
  }

  // 2. Nếu KHÔNG chọn Rate Code: Tra cứu từ bảng standard_rates
  if (standardRates.value && standardRates.value.length > 0 && roomClassId) {
    const matchedSr = standardRates.value.find(sr => 
      Number(sr.room_class_id) === Number(roomClassId) && 
      (!roomFormId || Number(sr.room_form_id) === Number(roomFormId))
    )
    if (matchedSr && Number(matchedSr.room_price) > 0) {
      return Number(matchedSr.room_price)
    }
  }

  // 3. Fallback: Lấy giá niêm yết của roomClass / room
  if (roomObj) {
    if (roomObj.room_class && Number(roomObj.room_class.price) > 0) return Number(roomObj.room_class.price)
    if (Number(roomObj.price) > 0) return Number(roomObj.price)
  }
  return 890000
}

function calculateQuickBookingPrice() {
  const ranges = selectedRoomsRanges.value
  if (!ranges || ranges.length === 0) return 0

  let totalPrice = 0
  ranges.forEach(range => {
    const unitPrice = getRoomUnitPrice(range.room, quickBookingForm.value.rateCode)
    const nights = range.nights || 1
    totalPrice += unitPrice * nights
  })

  quickBookingForm.value.rate = totalPrice
  return totalPrice
}

async function triggerMenuAction(actionName) {
  closeContextMenu()
  
  if (actionName === 'Tạo') {
    showQuickBookingModal.value = true
    quickBookingCompanySearch.value = 'KHÁCH LẺ'
    quickBookingForm.value = {
      company: 'KHÁCH LẺ',
      marketSegment: 'Free Individual Traveler',
      sourceCode: 'Free Individual Traveler',
      bookingName: 'Walkin Guest',
      rateCode: 'Vui lòng chọn giá phòng',
      rate: '0'
    }
    await loadDropdownsAndPrices()
    calculateQuickBookingPrice()
  } else if (actionName === 'Khóa phòng OOO') {
    lockRoomType.value = 'OOO'
    lockRoomForm.value.note = ''
    showLockRoomModal.value = true
  } else if (actionName === 'Khóa phòng OOS') {
    lockRoomType.value = 'OOS'
    lockRoomForm.value.note = ''
    showLockRoomModal.value = true
  } else if (actionName === 'Danh sách chờ') {
    showWaitingList.value = true
  } else if (actionName === 'Gỡ số phòng') {
    const booking = contextMenu.value.booking
    if (booking && booking.bookingId && booking.bookingRoomId) {
      try {
        loadingBookings.value = true
        emit('loading', true)
        const res = await unassignRoom(booking.bookingId, booking.bookingRoomId)
        if (res && res.data && res.data.success) {
          uiStore.showToast('Đã gỡ số phòng thành công!', 'success')
          await loadBookings()
        } else {
          uiStore.showToast(res?.data?.message || 'Có lỗi xảy ra khi gỡ số phòng.', 'error')
        }
      } catch (err) {
        console.error(err)
        uiStore.showToast('Không thể kết nối đến máy chủ.', 'error')
      } finally {
        loadingBookings.value = false
        emit('loading', false)
      }
    } else {
      uiStore.showToast('Không tìm thấy thông tin đăng ký.', 'error')
    }
  } else if (actionName === 'Mở khóa phòng') {
    const booking = contextMenu.value.booking
    const lockId = booking?.lockId || booking?.bookingRoomId
    if (booking && lockId) {
      uiStore.confirm({
        title: 'Xác nhận mở khóa phòng',
        message: 'Bạn có chắc chắn muốn mở khóa phòng này không?',
        confirmText: 'Đồng ý',
        cancelText: 'Hủy'
      }).then(async (confirmUnlock) => {
        if (!confirmUnlock) return
        try {
          loadingBookings.value = true
          emit('loading', true)
          const res = await roomService.deleteRoomLock(lockId)
          if (res && (res.success || res.data?.success)) {
            uiStore.showToast('Đã mở khóa phòng thành công!', 'success')
            await roomStore.fetchRooms()
            await loadBookings()
            if (bc) bc.postMessage('rooms-updated')
          } else {
            uiStore.showToast(res?.message || res?.data?.message || 'Có lỗi xảy ra khi mở khóa phòng.', 'error')
          }
        } catch (err) {
          console.error(err)
          uiStore.showToast(err.response?.data?.message || 'Không thể mở khóa phòng.', 'error')
        } finally {
          loadingBookings.value = false
          emit('loading', false)
        }
      })
    } else {
      uiStore.showToast('Không tìm thấy thông tin khóa phòng.', 'error')
    }
  } else if (actionName === 'Hủy phòng') {
    const booking = contextMenu.value.booking
    if (booking) {
      if (booking.bookingId && booking.bookingRoomId && typeof booking.bookingId === 'number' && booking.bookingId < 1e12) {
        pendingCancelBooking.value = booking
        isCancelReasonModalOpen.value = true
      } else {
        bookings.value = bookings.value.filter(b => b.bookingRoomId !== booking.bookingRoomId)
        uiStore.showToast('Đã hủy phòng thành công!', 'success')
      }
    } else {
      uiStore.showToast('Không tìm thấy thông tin đăng ký.', 'error')
    }
  } else if (actionName === 'Tách Phòng') {
    const booking = contextMenu.value.booking
    if (booking) {
      if (booking.nights && booking.nights > 1) {
        splittingBooking.value = booking
        splitIndex.value = booking.startIndex + 1
        uiStore.showToast('Chế độ tách phòng: Giữ phím Ctrl và kéo thanh màu trắng để chọn vị trí cắt, sau đó bấm Split.', 'info')
      } else {
        uiStore.showToast('Không thể tách đăng ký phòng có thời hạn 1 đêm.', 'warning')
      }
    }
  } else if (actionName === 'Khóa Di Chuyển Phòng') {
    const booking = contextMenu.value.booking
    if (booking) {
      try {
        loadingBookings.value = true
        emit('loading', true)
        const res = await lockRoomMove(booking.bookingId, booking.bookingRoomId, { note: 'Khóa di chuyển từ sơ đồ phòng' })
        if (res && res.data && res.data.success) {
          uiStore.showToast('Đã khóa di chuyển phòng thành công!', 'success')
          await loadBookings()
        } else {
          uiStore.showToast(res?.data?.message || 'Có lỗi xảy ra khi khóa di chuyển.', 'error')
        }
      } catch (err) {
        console.error(err)
        uiStore.showToast(err.response?.data?.message || 'Không thể khóa di chuyển.', 'error')
      } finally {
        loadingBookings.value = false
        emit('loading', false)
      }
    }
  } else if (actionName === 'Mở Khóa Di Chuyển Phòng') {
    const booking = contextMenu.value.booking
    if (booking) {
      try {
        loadingBookings.value = true
        emit('loading', true)
        const res = await unlockRoomMove(booking.bookingId, booking.bookingRoomId)
        if (res && res.data && res.data.success) {
          uiStore.showToast('Đã mở khóa di chuyển phòng thành công!', 'success')
          await loadBookings()
        } else {
          uiStore.showToast(res?.data?.message || 'Có lỗi xảy ra khi mở khóa di chuyển.', 'error')
        }
      } catch (err) {
        console.error(err)
        uiStore.showToast(err.response?.data?.message || 'Không thể mở khóa di chuyển.', 'error')
      } finally {
        loadingBookings.value = false
        emit('loading', false)
      }
    }
  } else if (actionName === 'Giao phòng') {
    const booking = contextMenu.value.booking
    if (booking && booking.bookingId && booking.bookingRoomId) {
      if (!canCheckInBooking(booking)) {
        uiStore.showToast('Phòng không đủ điều kiện giao phòng (Ngày đến phải bằng ngày hệ thống)!', 'warning')
        return
      }
      uiStore.confirm({
        title: 'Xác nhận giao phòng',
        message: `Bạn có chắc chắn muốn giao phòng ${booking.room} cho đăng ký ${booking.code}?`,
        confirmText: 'Đồng ý',
        cancelText: 'Hủy'
      }).then(async (confirmed) => {
        if (!confirmed) return
        try {
          loadingBookings.value = true
          emit('loading', true)
          const res = await checkInRoom(booking.bookingId, booking.bookingRoomId)
          if (res && res.data && res.data.success !== false) {
            uiStore.showToast(`Giao phòng ${booking.room} thành công!`, 'success')
            await roomStore.fetchRooms()
            await loadBookings()
            if (bc) bc.postMessage('rooms-updated')
          } else {
            uiStore.showToast(res?.data?.message || 'Giao phòng thất bại.', 'error')
          }
        } catch (err) {
          console.error(err)
          const msg = err.response?.data?.message || 'Có lỗi xảy ra khi giao phòng.'
          uiStore.showToast(msg, 'error')
        } finally {
          loadingBookings.value = false
          emit('loading', false)
        }
      })
    } else {
      uiStore.showToast('Không tìm thấy thông tin đăng ký phòng.', 'error')
    }
  } else {
    const bookingCode = contextMenu.value.booking ? contextMenu.value.booking.code : ''
    uiStore.showToast(`Đã thực hiện: "${actionName}"` + (bookingCode ? ` cho đăng ký ${bookingCode}` : ''), 'success')
  }
}

function canCheckInBooking(bk) {
  if (!bk || bk.code === 'LOCK' || bk.isVirtual || !bk.bookingId || !bk.bookingRoomId) return false
  if (bk.type === 'InHouse' || bk.type === 'CheckedOut' || bk.status === 1) return false
  if (!bk.room) return false

  const arrivalDateStr = bk.checkIn ? bk.checkIn.split(' ')[0] : (bk.arrival_date || '')
  const sysDateStr = systemDate.value || formatDateStr(new Date())

  return arrivalDateStr === sysDateStr
}

function handleDragStart(bk, event) {
  if (bk.type === 'InHouse') {
    uiStore.showToast('Phòng đang In-house không được phép kéo chuyển phòng trên Room Plan.', 'warning')
    event.preventDefault()
    return
  }
  if (bk.type === 'CheckedOut') {
    uiStore.showToast('Phòng đã Check-out không được phép kéo chuyển phòng trên Room Plan.', 'warning')
    event.preventDefault()
    return
  }
  if (bk.isDoNotMove) {
    uiStore.showToast('Phòng đang bị khóa di chuyển (Do Not Move). Không thể kéo thả.', 'warning')
    event.preventDefault()
    return
  }
  if (splittingBooking.value) {
    event.preventDefault()
    return
  }
  hideTooltip()
  draggedBooking.value = bk

  if (event && event.currentTarget) {
    const el = event.currentTarget
    const rect = el.getBoundingClientRect()
    draggedBookingRect.value = {
      left: rect.left,
      width: rect.width
    }
    dragGhostY.value = rect.top + 2

    try {
      const transparentImg = new Image()
      transparentImg.src = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'
      event.dataTransfer.setDragImage(transparentImg, 0, 0)
    } catch (e) {
      // Fallback if setDragImage not supported
    }
  }

  event.dataTransfer.setData('text/plain', bk.bookingRoomId)
  event.dataTransfer.effectAllowed = 'move'

  window.addEventListener('wheel', handleDragWheel, { passive: true })
  window.addEventListener('dragover', handleGlobalDragOver)
}

let scrollAnimationFrame = null
let currentScrollX = 0
let currentScrollY = 0
let scrollContainer = null

function getDragVerticalBounds() {
  if (!scrollContainer) {
    scrollContainer = document.querySelector('.overflow-auto') || document.querySelector('.overflow-y-auto')
  }
  if (!scrollContainer) {
    return { minTop: 0, maxTop: window.innerHeight - 35, headerBottom: 0, occTop: window.innerHeight }
  }

  const containerRect = scrollContainer.getBoundingClientRect()
  const theadEl = scrollContainer.querySelector('thead')
  const tfootEl = scrollContainer.querySelector('tfoot')

  const headerBottom = theadEl ? theadEl.getBoundingClientRect().bottom : containerRect.top + 40
  const occTop = tfootEl ? tfootEl.getBoundingClientRect().top : containerRect.bottom - 40

  const minTop = Math.max(headerBottom + 2, containerRect.top + 42)
  const maxTop = Math.min(occTop - 35, containerRect.bottom - 40)

  return { minTop, maxTop, headerBottom, occTop, containerRect }
}

function handleDragWheel(e) {
  if (!draggedBooking.value) return
  if (!scrollContainer) {
    scrollContainer = document.querySelector('.overflow-auto') || document.querySelector('.overflow-y-auto')
  }
  if (scrollContainer) {
    scrollContainer.scrollTop += e.deltaY
  }
}

function handleGlobalDragOver(event) {
  if (!draggedBooking.value) return
  event.preventDefault()

  const { minTop, maxTop, headerBottom, occTop } = getDragVerticalBounds()
  const clientY = event.clientY

  // If mouse is inside or above header, clamp ghost card strictly below header!
  if (clientY <= headerBottom) {
    dragGhostY.value = minTop
  } else if (clientY >= occTop) {
    dragGhostY.value = maxTop
  }

  let scrollY = 0

  // Trigger auto-scroll UP as soon as mouse reaches within 25px of header bottom
  if (clientY < headerBottom + 25) {
    const overflow = Math.max(0, (headerBottom + 25) - clientY)
    // Smooth speed: Starts at 2px/frame, gradually speeds up to 12px/frame as mouse moves further up
    const speed = Math.min(12, Math.round(2 + (overflow / 10) * 2))
    scrollY = -speed
  } 
  // Trigger auto-scroll DOWN as soon as mouse reaches within 25px of OCC top
  else if (clientY > occTop - 25) {
    const overflow = Math.max(0, clientY - (occTop - 25))
    // Smooth speed: Starts at 2px/frame, gradually speeds up to 12px/frame as mouse moves further down
    const speed = Math.min(12, Math.round(2 + (overflow / 10) * 2))
    scrollY = speed
  }

  currentScrollX = 0
  currentScrollY = scrollY

  if (scrollY !== 0) {
    startAutoScrollLoop()
  } else {
    stopDragAutoScroll()
  }
}

function stopDragAutoScroll() {
  if (scrollAnimationFrame) {
    cancelAnimationFrame(scrollAnimationFrame)
    scrollAnimationFrame = null
  }
  currentScrollX = 0
  currentScrollY = 0
  scrollContainer = null
  window.removeEventListener('wheel', handleDragWheel)
  window.removeEventListener('dragover', handleGlobalDragOver)
}

function startAutoScrollLoop() {
  if (scrollAnimationFrame) return
  const loop = () => {
    if (scrollContainer && currentScrollY !== 0) {
      scrollContainer.scrollTop += currentScrollY
      const { minTop, maxTop } = getDragVerticalBounds()
      if (draggedBooking.value) {
        dragGhostY.value = Math.max(minTop, Math.min(dragGhostY.value, maxTop))
      }
      scrollAnimationFrame = requestAnimationFrame(loop)
    } else {
      scrollAnimationFrame = null
    }
  }
  scrollAnimationFrame = requestAnimationFrame(loop)
}

function handleDragOver(event, item, dayIdx) {
  event.preventDefault()
  if (item && item.room && draggedOverRoom.value !== item.room) {
    draggedOverRoom.value = item.room
  }
  if (draggedBooking.value) {
    draggedOverDayIdx.value = draggedBooking.value.startIndex
    if (event && event.currentTarget) {
      const cellRect = event.currentTarget.getBoundingClientRect()
      const { minTop, maxTop } = getDragVerticalBounds()
      const targetY = cellRect.top + 2
      dragGhostY.value = Math.max(minTop, Math.min(targetY, maxTop))
    }
  } else if (dayIdx !== undefined && draggedOverDayIdx.value !== dayIdx) {
    draggedOverDayIdx.value = dayIdx
  }

  handleGlobalDragOver(event)
}

function handleDragEnd() {
  draggedBooking.value = null
  draggedBookingRect.value = null
  draggedOverRoom.value = null
  draggedOverDayIdx.value = null
  stopDragAutoScroll()
}

async function handleDrop(targetRoom, targetDay, event) {
  event.preventDefault()
  draggedOverRoom.value = null
  draggedOverDayIdx.value = null
  stopDragAutoScroll()

  let bk = draggedBooking.value
  if (!bk && event.dataTransfer) {
    const brId = event.dataTransfer.getData('text/plain')
    if (brId) {
      bk = bookings.value.find(b => String(b.bookingRoomId) === String(brId))
    }
  }
  if (!bk) return
  draggedBooking.value = null

  if (bk.code === 'LOCK') {
    uiStore.showToast('Không thể kéo thả ô bảo trì phòng.', 'error')
    return
  }

  const targetRoomNumber = targetRoom.isVirtual ? null : targetRoom.room
  const targetRoomClass = targetRoom.type
  const originalRoomClass = bk.typeClass

  // Keep original check-in and check-out dates (only change room)
  const originalCheckIn = parseDateTime(bk.checkIn)
  const originalCheckOut = parseDateTime(bk.checkOut)
  const arrivalStr = formatDateStr(originalCheckIn)
  const departureStr = formatDateStr(originalCheckOut)

  // Check if target room is currently locked (OOO/OOS) or occupied
  if (targetRoomNumber) {
    const dStart = new Date(arrivalStr)
    const dEnd = new Date(departureStr)
    for (let d = new Date(dStart); d < dEnd; d.setDate(d.getDate() + 1)) {
      const dateStr = formatDateStr(d)
      const rKey = String(targetRoomNumber)
      const roomBookings = processedBookings.value[rKey] || processedBookings.value[targetRoomNumber] || []

      const isLockedOrOccupied = roomBookings.some(b => {
        if (b.bookingRoomId === bk.bookingRoomId) return false
        const startStr = formatDateStr(parseDateTime(b.checkIn))
        const endStr = formatDateStr(parseDateTime(b.checkOut))
        const isLockItem = b.code === 'LOCK' || b.type === 'OOO' || b.type === 'OOS'
        if (isLockItem) {
          return dateStr >= startStr && dateStr <= endStr
        }
        return dateStr >= startStr && dateStr < endStr
      })

      if (isLockedOrOccupied) {
        uiStore.showToast(`Phòng ${targetRoomNumber} đang bị khóa (OOO/OOS) hoặc đã có khách trong khoảng thời gian này. Không thể kéo chuyển phòng!`, 'error')
        return
      }
    }
  }

  const proceedMove = async (differentClass = false) => {
    try {
      loadingBookings.value = true
      emit('loading', true)

      const payload = {
        room_number: targetRoomNumber,
        arrival_date: arrivalStr,
        departure_date: departureStr
      }
      if (differentClass && targetRoom.classId) {
        payload.room_class_id = targetRoom.classId
      }

      const res = await updateBookingRoom(bk.bookingId, bk.bookingRoomId, payload)

      if (res && res.data && res.data.success) {
        if (res.data.warning) {
          uiStore.showToast(res.data.warning, 'warning')
        } else {
          uiStore.showToast('Chuyển phòng thành công!', 'success')
        }
        await loadBookings()
      } else {
        uiStore.showToast(res?.data?.message || 'Có lỗi xảy ra khi chuyển phòng.', 'error')
      }
    } catch (err) {
      console.error(err)
      uiStore.showToast(err.response?.data?.message || 'Không thể chuyển phòng.', 'error')
    } finally {
      loadingBookings.value = false
      emit('loading', false)
    }
  }

  if (originalRoomClass !== targetRoomClass) {
    uiStore.confirm({
      title: 'Xác nhận chuyển phòng khác loại',
      message: `Bạn đang chuyển phòng khác loại phòng (${originalRoomClass} -> ${targetRoomClass}). Bạn có muốn xác nhận thay đổi này?`,
      confirmText: 'Đồng ý',
      cancelText: 'Hủy'
    }).then(async (confirmed) => {
      if (confirmed) {
        await proceedMove(true)
      }
    })
  } else {
    await proceedMove(false)
  }
}

function cancelSplit() {
  splittingBooking.value = null
  splitIndex.value = -1
}

async function executeSplit() {
  const bk = splittingBooking.value
  if (!bk) return

  const splitDateObj = new Date(days.value[splitIndex.value].fullDate)
  const splitDateStr = formatDateStr(splitDateObj)

  try {
    loadingBookings.value = true
    emit('loading', true)

    const res = await splitBookingRoom(bk.bookingId, bk.bookingRoomId, {
      split_date: splitDateStr
    })

    if (res && res.data && res.data.success) {
      uiStore.showToast('Tách phòng thành công!', 'success')
      splittingBooking.value = null
      splitIndex.value = -1
      await loadBookings()
    } else {
      uiStore.showToast(res?.data?.message || 'Có lỗi xảy ra khi tách phòng.', 'error')
    }
  } catch (err) {
    console.error(err)
    uiStore.showToast(err.response?.data?.message || 'Không thể tách phòng.', 'error')
  } finally {
    loadingBookings.value = false
    emit('loading', false)
  }
}

function startDragSplitBar(event) {
  event.preventDefault()
  const startX = event.clientX
  const initialSplitIndex = splitIndex.value
  
  // Measure exact cell width dynamically from the rendered table grid column
  const tableEl = event.target.closest('table')
  const cellEl = tableEl ? tableEl.querySelector('tbody td.border-r') : null
  const cellWidth = cellEl ? cellEl.getBoundingClientRect().width : 62
  
  const handleMouseMove = (moveEvent) => {
    const deltaX = moveEvent.clientX - startX
    const deltaDays = Math.round(deltaX / cellWidth)
    let newIdx = initialSplitIndex + deltaDays
    
    // splitIndex must be strictly between startIndex and endIndex
    const minIdx = splittingBooking.value.startIndex + 1
    const maxIdx = splittingBooking.value.endIndex
    
    if (newIdx < minIdx) newIdx = minIdx
    if (newIdx > maxIdx) newIdx = maxIdx
    
    splitIndex.value = newIdx
  }
  
  const handleMouseUp = () => {
    window.removeEventListener('mousemove', handleMouseMove)
    window.removeEventListener('mouseup', handleMouseUp)
  }
  
  window.addEventListener('mousemove', handleMouseMove)
  window.addEventListener('mouseup', handleMouseUp)
}

async function saveQuickBooking() {
  const ranges = selectedRoomsRanges.value
  if (ranges.length === 0) return

  try {
    loadingBookings.value = true
    emit('loading', true)

    // 1. Công ty ID từ database
    const companyName = quickBookingForm.value.company || 'KHÁCH LẺ'
    const companyObj = allCompanies.value.find(c => c.company_name?.toLowerCase() === companyName.toLowerCase())
    const companyId = companyObj ? companyObj.id : 1

    // 2. Xác định ngày đến sớm nhất và ngày đi muộn nhất
    let overallArrival = ranges[0].checkIn
    let overallDeparture = ranges[0].checkOut

    ranges.forEach(r => {
      if (r.checkIn < overallArrival) overallArrival = r.checkIn
      if (r.checkOut > overallDeparture) overallDeparture = r.checkOut
    })

    const overallNights = Math.round((new Date(overallDeparture) - new Date(overallArrival)) / (1000 * 60 * 60 * 24)) || 1
    const selectedRateCode = quickBookingForm.value.rateCode !== 'Vui lòng chọn giá phòng' ? quickBookingForm.value.rateCode : null

    // 3. Gom nhóm phòng theo roomClassId, checkIn, checkOut cho room_allocations
    const allocationsMap = {}

    ranges.forEach(range => {
      const roomObj = roomStore.rooms ? roomStore.rooms.find(r => String(r.room_number) === String(range.room)) : null
      const roomClassId = roomObj ? roomObj.room_class_id : 1
      const unitPrice = getRoomUnitPrice(range.room, quickBookingForm.value.rateCode)

      const groupKey = `${roomClassId}_${range.checkIn}_${range.checkOut}`

      if (!allocationsMap[groupKey]) {
        allocationsMap[groupKey] = {
          quantity: 0,
          roomClassId: roomClassId,
          arrivalDate: range.checkIn,
          departureDate: range.checkOut,
          price: unitPrice,
          rateCode: selectedRateCode,
          breakfastIncluded: true,
          rooms: []
        }
      }

      allocationsMap[groupKey].quantity += 1
      allocationsMap[groupKey].rooms.push({
        roomNumber: range.room,
        guestName: quickBookingForm.value.bookingName || 'Walkin Guest',
        arrivalDate: range.checkIn,
        departureDate: range.checkOut,
        nights: range.nights || 1,
        adults: 2,
        children: 0,
        babies: 0
      })
    })

    const room_allocations = Object.values(allocationsMap)

    const sysDateStr = systemDate.value || formatDateStr(new Date())
    if (overallArrival < sysDateStr) {
      uiStore.showToast(`Ngày đến không được nhỏ hơn ngày hệ thống (${sysDateStr})!`, 'error')
      return
    }

    // 4. Gọi 1 request duy nhất tạo 1 phiếu Booking
    const payload = {
      booking_name: quickBookingForm.value.bookingName || 'Walkin Guest',
      arrival_date: overallArrival,
      departure_date: overallDeparture,
      num_of_days: overallNights,
      registration_status_id: quickBookingForm.value.registrationStatusId || 1,
      company_id: companyId,
      market_id: quickBookingForm.value.marketId || 1,
      customer_source_id: quickBookingForm.value.customerSourceId || 1,
      contact_name: quickBookingForm.value.bookingName || 'Walkin Guest',
      contact_phone: '',
      note: '',
      room_allocations: room_allocations,
      module: 'reception',
      created_module: 'reception'
    }

    await createBooking(payload)

    uiStore.showToast('Đã tạo phiếu đăng ký nhanh thành công!', 'success')
    showQuickBookingModal.value = false
    selectedCells.value = []
    
    await roomStore.fetchRooms()
    await loadBookings()
    if (bc) bc.postMessage('rooms-updated')
  } catch (err) {
    console.error(err)
    uiStore.showToast(err.response?.data?.message || 'Có lỗi xảy ra khi tạo đăng ký nhanh.', 'error')
  } finally {
    loadingBookings.value = false
    emit('loading', false)
  }
}

async function saveLockRoom() {
  const ranges = selectedRoomsRanges.value
  if (ranges.length === 0) return

  const note = lockRoomForm.value.note ? lockRoomForm.value.note.trim() : ''
  if (!note) {
    uiStore.showToast('Vui lòng nhập ghi chú (Note) cho khóa phòng.', 'warning')
    return
  }

  try {
    loadingBookings.value = true
    emit('loading', true)

    // Verify no selected cell is occupied
    for (const range of ranges) {
      const dStart = new Date(range.checkIn)
      const dEnd = new Date(range.lastDate || range.checkIn)
      for (let d = new Date(dStart); d <= dEnd; d.setDate(d.getDate() + 1)) {
        if (isCellOccupied(range.room, d)) {
          uiStore.showToast(`Phòng ${range.room} ngày ${formatDateStr(d)} đã có lịch đặt/khóa phòng, không thể thực hiện khóa.`, 'error')
          loadingBookings.value = false
          emit('loading', false)
          return
        }
      }
    }

    const sysDateStr = systemDate.value || formatDateStr(new Date())
    const endTimeConfig = hotelSettings.value?.FrmOOO_DefineLockByTime || '23:59:59'
    const formattedEndTime = endTimeConfig.includes(':') && endTimeConfig.split(':').length === 2 ? `${endTimeConfig}:59` : endTimeConfig

    const locksPayload = ranges.map(range => {
      const lockStartDate = range.checkIn
      const lockEndDate = range.lastDate || range.checkIn

      let startTimeStr = '00:00:00'
      if (lockStartDate <= sysDateStr) {
        const now = new Date()
        const hh = String(now.getHours()).padStart(2, '0')
        const mm = String(now.getMinutes()).padStart(2, '0')
        const ss = String(now.getSeconds()).padStart(2, '0')
        startTimeStr = `${hh}:${mm}:${ss}`
      }

      return {
        room_number: range.room,
        start_date: `${lockStartDate} ${startTimeStr}`,
        end_date: `${lockEndDate} ${formattedEndTime}`,
        lock_type: lockRoomType.value,
        reason: note
      }
    })

    const bulkLockObj = {
      locks: locksPayload,
      lock_type: lockRoomType.value,
      reason: note,
      force: false
    }

    try {
      await roomService.bulkLockRooms(bulkLockObj)
    } catch (err) {
      const resData = err.response?.data
      if (resData && resData.require_confirm) {
        const proceed = await uiStore.confirm({
          title: 'Cảnh báo phòng âm',
          message: resData.message || 'Phòng âm. Bạn có muốn tiếp tục thao tác?',
          confirmText: 'Tiếp tục',
          cancelText: 'Hủy'
        })
        if (proceed) {
          bulkLockObj.force = true
          await roomService.bulkLockRooms(bulkLockObj)
        } else {
          return
        }
      } else {
        throw err
      }
    }

    await roomStore.fetchRooms()
    await loadBookings()
    if (bc) bc.postMessage('rooms-updated')
    
    uiStore.showToast(`Đã khóa các phòng thành công!`, 'success')
  } catch (err) {
    console.error(err)
    uiStore.showToast(err.response?.data?.message || 'Có lỗi xảy ra khi khóa phòng.', 'error')
  } finally {
    loadingBookings.value = false
    emit('loading', false)
    showLockRoomModal.value = false
    selectedCells.value = []
  }
}

function parseColorToHsv(colorStr) {
  if (!colorStr) colorStr = '#ffffff'
  const rgb = hexToRgb(colorStr)
  const hsv = rgbToHsv(rgb.r, rgb.g, rgb.b)
  hue.value = hsv.h
  saturation.value = hsv.s
  value.value = hsv.v
  alpha.value = 100
}

function handleLegendClick(legName, event) {
  if (!isAdmin.value) return
  const configKey = legendConfigKeys[legName]
  if (!configKey) return
  
  let curColor = '#ffffff'
  if (legName === 'Reservation' || legName === 'Guaranteed') {
    curColor = hotelSettings.value?.RoomPlan_ColorRoomReservation || '#E3E8C4'
  }
  else if (legName === 'InHouse') curColor = hotelSettings.value?.RoomPlan_ColorRoomInhouse || '#4a90e2'
  else if (legName === 'Late Checkout') curColor = hotelSettings.value?.RoomPlan_ColorRoomLateCheckout || '#FCF55F'
  else if (legName === 'OOO') curColor = hotelSettings.value?.RoomPlan_ColorOOO || '#107eeb'
  else if (legName === 'OOS') curColor = hotelSettings.value?.RoomPlan_ColorOOS || '#107eeb'
  
  selectedLegendForColor.value = { name: legName, configKey }
  parseColorToHsv(curColor)
  
  const rect = event.target.getBoundingClientRect()
  pickerPosition.value = {
    top: rect.bottom + window.scrollY + 8,
    left: Math.min(rect.left + window.scrollX, window.innerWidth - 280)
  }
  showColorPicker.value = true
}

async function saveLegendColor() {
  if (!selectedLegendForColor.value) return
  savingColor.value = true
  try {
    const key = selectedLegendForColor.value.configKey
    const color = computedHex.value
    await http.put('/hotel-settings', {
      hotel_name: hotelSettings.value?.hotel_name || 'Galliot Hotel Nha Trang',
      [key]: color
    })
    if (hotelSettings.value) {
      hotelSettings.value[key] = color
    }
    uiStore.showToast('Cập nhật màu sắc thành công!', 'success')
    showColorPicker.value = false
  } catch (err) {
    console.error(err)
    const msg = err.response?.data?.message || 'Không thể lưu màu sắc'
    uiStore.showToast(msg, 'error')
  } finally {
    savingColor.value = false
  }
}

// ==================== HỦY PHÒNG REASON MODAL ====================
const isCancelReasonModalOpen = ref(false)
const pendingCancelBooking = ref(null)

async function handleConfirmCancelRoomPlan(payload) {
  const booking = pendingCancelBooking.value
  if (!booking || !booking.bookingId || !booking.bookingRoomId) return

  try {
    loadingBookings.value = true
    emit('loading', true)
    const res = await cancelBookingRoom(booking.bookingId, booking.bookingRoomId, {
      cancel_reason_id: payload.cancel_reason_id,
      note: payload.note,
      current_module: 'reception'
    })
    if (res && res.data && res.data.success) {
      uiStore.showToast('Đã hủy phòng thành công!', 'success')
      await loadBookings()
    } else {
      uiStore.showToast(res?.data?.message || 'Có lỗi xảy ra khi hủy phòng.', 'error')
    }
  } catch (err) {
    console.error(err)
    const msg = err.response?.data?.message || 'Không thể kết nối đến máy chủ.'
    uiStore.showToast(msg, 'error')
  } finally {
    loadingBookings.value = false
    emit('loading', false)
  }
}

// Drag & Drop handlers for custom color picker
function startDragColorArea(e) {
  handleColorAreaDrag(e)
  window.addEventListener('mousemove', handleColorAreaDrag)
  window.addEventListener('mouseup', stopDragColorArea)
}

function handleColorAreaDrag(e) {
  if (!colorAreaRef.value) return
  const rect = colorAreaRef.value.getBoundingClientRect()
  let x = e.clientX - rect.left
  let y = e.clientY - rect.top
  x = Math.max(0, Math.min(x, rect.width))
  y = Math.max(0, Math.min(y, rect.height))
  
  saturation.value = Math.round((x / rect.width) * 100)
  value.value = Math.round((1 - y / rect.height) * 100)
}

function stopDragColorArea() {
  window.removeEventListener('mousemove', handleColorAreaDrag)
  window.removeEventListener('mouseup', stopDragColorArea)
}

function startDragHue(e) {
  handleHueDrag(e)
  window.addEventListener('mousemove', handleHueDrag)
  window.addEventListener('mouseup', stopDragHue)
}

function handleHueDrag(e) {
  const slider = e.target.closest('.relative')
  if (!slider) return
  const rect = slider.getBoundingClientRect()
  let x = e.clientX - rect.left
  x = Math.max(0, Math.min(x, rect.width))
  hue.value = Math.round((x / rect.width) * 360)
}

function stopDragHue() {
  window.removeEventListener('mousemove', handleHueDrag)
  window.removeEventListener('mouseup', stopDragHue)
}

function startDragAlpha(e) {
  handleAlphaDrag(e)
  window.addEventListener('mousemove', handleAlphaDrag)
  window.addEventListener('mouseup', stopDragAlpha)
}

function handleAlphaDrag(e) {
  const slider = e.target.closest('.relative')
  if (!slider) return
  const rect = slider.getBoundingClientRect()
  let x = e.clientX - rect.left
  x = Math.max(0, Math.min(x, rect.width))
  alpha.value = Math.round((x / rect.width) * 100)
}

function stopDragAlpha() {
  window.removeEventListener('mousemove', handleAlphaDrag)
  window.removeEventListener('mouseup', stopDragAlpha)
}

function handleHexInputChange(e) {
  let val = e.target.value
  if (!val.startsWith('#')) val = '#' + val
  const rgb = hexToRgb(val)
  if (rgb) {
    const hsv = rgbToHsv(rgb.r, rgb.g, rgb.b)
    hue.value = hsv.h
    saturation.value = hsv.s
    value.value = hsv.v
  }
}

function handleRgbInput(channel, val) {
  const num = parseInt(val) || 0
  const rgb = { ...computedRgb.value }
  rgb[channel] = Math.max(0, Math.min(255, num))
  const hsv = rgbToHsv(rgb.r, rgb.g, rgb.b)
  hue.value = hsv.h
  saturation.value = hsv.s
  value.value = hsv.v
}

function selectPresetColor(color) {
  parseColorToHsv(color)
}

function isTodayDate(dateVal) {
  if (!dateVal) return false
  const dStr = toLocalDateStr(dateVal)
  const sysDateVal = systemDate.value ? toLocalDateStr(systemDate.value) : ''
  return dStr === sysDateVal
}

function isTodayOrActiveBooking(b) {
  if (!b || !b.checkIn || !b.checkOut) return false
  const sysDateStr = systemDate.value ? toLocalDateStr(systemDate.value) : toLocalDateStr(new Date())
  const inStr = toLocalDateStr(b.checkIn)
  const outStr = toLocalDateStr(b.checkOut)
  return sysDateStr >= inStr && sysDateStr < outStr
}

function getRoomStatusIconName(item) {
  if (!item) return null
  // 1. Lock (OOO / OOS) - Only when currently locked today
  if (item.status === 'maintenance' || item.status === 'ooo' || item.status === 'oos') {
    return item.lock_type === 'OOS' ? 'oos' : 'ooo'
  }
  // 2. Dirty Room / Housekeeping needed -> broom icon
  if (item.status === 'dirty' || item.status === 'checkout' || item.is_clean === false || item.is_clean === 0) {
    return 'dirty'
  }
  // 3. Occupied (In-house) and clean -> No extra icon
  if (item.booking_status === 'occupied' || item.status === 'occupied') {
    return null
  }
  // 4. Has reserved booking today -> double-check icon (✓✓)
  const hasBookingToday = !!item.guest_name || !!item.booking_code || (bookings.value && bookings.value.some(b => String(b.room) === String(item.room) && isTodayOrActiveBooking(b) && b.status !== 'occupied'))
  if (hasBookingToday) {
    return 'double-check'
  }
  // 5. Sparkles ONLY for clean vacant available room without active booking today
  if (item.is_clean && (item.status === 'available' || !item.status)) {
    return 'clean'
  }
  if (item.status === 'reserved') {
    return 'priority'
  }
  return null
}
</script>

<template>
  <div class="flex-1 bg-white rounded-xl shadow-sm border border-slate-200 p-4 flex flex-col gap-4 overflow-hidden h-full">
    <!-- Top Filters & Controls -->
    <div class="flex items-center justify-between shrink-0">
      <!-- Left side controls -->
      <div class="flex items-center gap-3">
        <!-- Date Selector Display & Popover Picker -->
        <div class="relative select-none flex items-center">
          <button 
            @click.stop="showDatePickerPopover = !showDatePickerPopover; if (showDatePickerPopover) { tempStartDateStr = formatDateStr(startDate); tempEndDateStr = formatDateStr(endDate); }" 
            class="flex items-center gap-1.5 border border-slate-200 rounded-lg bg-slate-50 px-3 py-1.5 shadow-sm text-xs font-semibold text-slate-700 hover:bg-slate-100 transition-colors cursor-pointer h-[30px]"
          >
            <span>{{ dateRangeText }}</span>
            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
          </button>

          <!-- Date Picker Popover -->
          <div 
            v-if="showDatePickerPopover" 
            class="absolute left-0 top-[34px] bg-white border border-slate-200 rounded-lg shadow-xl z-50 p-3.5 flex flex-col gap-3 w-[260px]"
            @click.stop
          >
            <h4 class="text-xs font-extrabold text-slate-800 m-0">Chọn khoảng thời gian</h4>
            <div class="flex flex-col gap-2">
              <div class="flex flex-col gap-1">
                <span class="text-[10px] font-bold text-slate-500 uppercase">Từ ngày</span>
                <input 
                  type="date" 
                  v-model="tempStartDateStr"
                  class="border border-slate-200 rounded px-2 py-1 text-xs text-slate-700 focus:outline-none focus:ring-1 focus:ring-blue-500 w-full font-sans"
                />
              </div>
              <div class="flex flex-col gap-1">
                <span class="text-[10px] font-bold text-slate-500 uppercase">Đến ngày</span>
                <input 
                  type="date" 
                  v-model="tempEndDateStr"
                  class="border border-slate-200 rounded px-2 py-1 text-xs text-slate-700 focus:outline-none focus:ring-1 focus:ring-blue-500 w-full font-sans"
                />
              </div>
            </div>
            <div class="flex items-center justify-between border-t border-slate-100 pt-2 mt-1">
              <button 
                @click="showDatePickerPopover = false"
                class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded text-[11px] font-semibold border-none cursor-pointer transition-colors"
              >
                Đóng
              </button>
              <button 
                @click="saveDateRange"
                class="px-4 py-1.5 bg-[#7dd3fc] hover:bg-sky-400 text-white rounded text-[11px] font-bold border-none shadow-sm cursor-pointer transition-colors"
              >
                Lưu
              </button>
            </div>
          </div>
        </div>

        <!-- View Button -->
        <button 
          @click="handleViewClick"
          class="px-4 py-1.5 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-xs font-bold border-none shadow-sm transition-colors cursor-pointer h-[30px]"
        >
          View
        </button>

        <!-- Sort Select Dropdown -->
        <div class="relative select-none flex items-center">
          <button 
            @click.stop="showPlanSettings = !showPlanSettings"
            class="flex items-center gap-1.5 border border-slate-200 rounded-lg bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 cursor-pointer h-[30px] transition-colors"
          >
            <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12" />
            </svg>
            <span>Sắp xếp: {{ activeGroupSetting === 'Phòng' ? 'Số phòng' : activeGroupSetting }}</span>
            <svg class="w-3 h-3 text-slate-400 transition-transform duration-200" :class="{ 'rotate-180': showPlanSettings }" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
          </button>
          
          <div 
            v-if="showPlanSettings" 
            class="absolute left-0 top-[34px] bg-white border border-slate-200 rounded-lg shadow-xl z-50 py-1 min-w-[120px]"
          >
            <button 
              v-for="opt in [
                { value: 'Phòng', label: 'Số phòng' },
                { value: 'Loại phòng', label: 'Loại phòng' },
                { value: 'Loại giường', label: 'Loại giường' },
                { value: 'Tầng', label: 'Tầng' }
              ]" 
              :key="opt.value"
              @click="activeGroupSetting = opt.value; showPlanSettings = false"
              class="w-full text-left px-3 py-2 text-xs font-semibold hover:bg-slate-50 transition-colors border-none bg-transparent cursor-pointer"
              :class="activeGroupSetting === opt.value ? 'text-blue-500 bg-blue-50/50' : 'text-slate-700'"
            >
              {{ opt.label }}
            </button>
          </div>
        </div>

        <!-- Switch Xem đêm -->
        <div class="flex items-center gap-1.5 select-none ml-1">
          <span class="text-[10px] text-slate-500 font-extrabold uppercase">{{ showNights ? 'Xem đêm' : 'Xem ngày' }}</span>
          <label class="relative inline-flex items-center cursor-pointer">
            <input type="checkbox" v-model="showNights" class="sr-only peer">
            <div class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500"></div>
          </label>
        </div>

        <!-- Switch Ghi chú -->
        <div class="flex items-center gap-1.5 select-none">
          <span class="text-[10px] text-slate-500 font-extrabold uppercase">Ghi chú</span>
          <label class="relative inline-flex items-center cursor-pointer">
            <input type="checkbox" v-model="showNotes" class="sr-only peer">
            <div class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500"></div>
          </label>
        </div>
      </div>

      <!-- Right side controls (Search & Filter) -->
      <div class="flex items-center gap-2">
        <!-- Dynamic Active Legends Pill Bar -->
        <div class="flex items-center gap-1.5 select-none shrink-0 mr-1.5">
          <div 
            v-for="leg in visibleLegends" 
            :key="leg.name" 
            class="px-2 py-0.5 border rounded text-[9px] font-extrabold whitespace-nowrap shadow-2xs leading-none uppercase select-none" 
            :class="[
              leg.class,
              (isAdmin && legendConfigKeys[leg.name]) ? 'cursor-pointer hover:scale-105 transition-transform duration-150' : ''
            ]"
            :style="getLegendStyle(leg.name)"
            @click="handleLegendClick(leg.name, $event)"
          >
            {{ leg.name }}
          </div>
        </div>

        <!-- Search Input Group -->
        <div class="relative flex items-center border border-slate-200 rounded-lg bg-white shadow-sm overflow-hidden select-none h-[30px] pr-2.5">
          <input 
            type="text" 
            v-model="searchInput" 
            @change="handleSearchTrigger"
            @keyup.enter="handleSearchTrigger"
            placeholder="Tìm kiếm khách hàng, mã đặt"
            class="search-input-reset text-slate-700 text-xs w-[210px] pl-3.5 pr-8"
            style="border: none !important; outline: none !important; box-shadow: none !important; background-color: transparent !important; height: auto !important; border-radius: 0 !important;"
          />
          <button 
            v-if="searchInput" 
            @click="handleClearSearch"
            class="absolute right-[28px] p-0.5 hover:bg-slate-100 rounded-full text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer flex items-center justify-center z-10"
          >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
          <svg class="w-3.5 h-3.5 text-slate-400 shrink-0 select-none pointer-events-none" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </div>

        <!-- Filter Drawer Trigger Button -->
        <button 
          @click="openFilterDrawer"
          class="flex items-center gap-1.5 px-3 py-1.5 border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 rounded-lg text-xs font-semibold shadow-sm transition-colors cursor-pointer h-[30px]"
        >
          <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v3.022a1.2 1.2 0 01-.328.814l-4.747 4.748a1.2 1.2 0 00-.328.814v3.169a1.2 1.2 0 01-.694 1.086l-2.851 1.426c-.843.421-1.85-.192-1.85-1.137v-4.544a1.2 1.2 0 00-.328-.814L5.34 8.572A1.2 1.2 0 015.012 7.76V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0112 3z" />
          </svg>
          <span>Bộ lọc</span>
        </button>
      </div>
    </div>

    <!-- Timeline Grid Matrix -->
    <div class="flex-1 overflow-auto border border-slate-200 rounded-lg relative" @dragover="handleGlobalDragOver($event)">
      <table class="w-full text-xs border-collapse table-fixed select-none">
        <colgroup>
          <col class="w-[120px] sticky left-0 z-30" />
          <col v-for="(day, idx) in days" :key="idx" class="w-[62px]" />
        </colgroup>

        <!-- Header -->
        <thead>
          <tr class="border-b border-slate-200 text-slate-700 font-bold select-none h-10">
            <th class="p-2 border-r border-slate-200 text-center sticky left-0 top-0 z-40 bg-slate-100 shadow-[inset_-1px_0_0_#e2e8f0]"></th>
            <th 
              v-for="(day, idx) in days" 
              :key="idx" 
              class="p-1 border-r border-slate-200 text-center sticky top-0 z-30 shadow-[inset_0_-1px_0_#e2e8f0]"
              :class="[
                isTodayDate(day.fullDate) ? 'bg-[#ff7043] text-white border-[#ff7043]' : (day.isWeekend ? 'bg-[#72b5f7] text-white border-[#72b5f7]' : 'bg-slate-100 text-slate-700')
              ]"
            >
              <div class="flex flex-col items-center justify-center leading-tight py-0.5">
                <span class="text-[11px] font-extrabold uppercase">{{ day.dow }}</span>
                <span class="text-[10px] opacity-90 font-medium">{{ day.dateStr }}</span>
              </div>
            </th>
          </tr>
        </thead>

        <!-- Body Grid -->
        <tbody>
          <!-- Timeline Rows -->
          <template v-for="(item, idx) in dbRooms" :key="item.room">
            <!-- Group Header Row for Room Type, Bed Type (Loại giường), or Floor -->
            <tr 
              v-if="(activeGroupSetting === 'Loại phòng' && (idx === 0 || dbRooms[idx - 1].type !== item.type)) ||
                    ((activeGroupSetting === 'Loại giường' || activeGroupSetting === 'Dạng phòng') && (idx === 0 || dbRooms[idx - 1].shape !== item.shape)) ||
                    (activeGroupSetting === 'Tầng' && (item.isVirtual ? (idx === 0 || !dbRooms[idx - 1].isVirtual) : (idx === 0 || dbRooms[idx - 1].floor !== item.floor))) ||
                    (activeGroupSetting === 'Phòng' && item.isVirtual && (idx === 0 || !dbRooms[idx - 1].isVirtual))"
              class="border-b border-slate-200 select-none h-6 bg-slate-100"
            >
              <td 
                :colspan="days.length + 1" 
                class="p-1 pl-3 font-bold text-slate-800 bg-slate-100 border-r border-slate-200 sticky left-0 z-20 text-[11px] shadow-[inset_-1px_0_0_#e2e8f0] text-left uppercase"
              >
                {{ 
                  (activeGroupSetting === 'Phòng' || activeGroupSetting === 'Tầng') && item.isVirtual 
                    ? 'CHƯA GÁN PHÒNG' 
                    : (activeGroupSetting === 'Tầng' ? `TẦNG ${item.floor}` : ((activeGroupSetting === 'Loại giường' || activeGroupSetting === 'Dạng phòng') ? item.shape : item.type)) 
                }}
              </td>
            </tr>

            <tr 
              class="border-b border-slate-300 h-[38px] hover:bg-slate-50 relative"
              :class="item.isVirtual ? 'bg-[#fdf6e2]' : ''"
            >
              <!-- Room Info (Sticky Left) -->
              <td 
                class="p-0.5 px-1 border-r border-slate-300 sticky left-0 z-20 shadow-[inset_-1px_0_0_#cbd5e1] h-[37px] overflow-hidden transition-colors"
                :class="[
                  activeHighlightRoom === item.room ? '!bg-amber-200 shadow-[inset_-1px_0_0_#d97706]' : (item.isVirtual ? 'bg-[#fdf6e2]' : 'bg-white')
                ]"
              >
                <div class="flex items-center justify-between h-full w-full gap-0.5">
                  <!-- Room Number (Left side) -->
                  <span 
                    class="font-normal text-slate-700 select-none truncate"
                    :class="item.isVirtual ? 'text-[10px] font-medium max-w-[72px]' : 'text-[12px]'"
                    :title="item.room"
                  >
                    {{ item.room }}
                  </span>
                  
                  <!-- Details & Status (Right side) -->
                  <div class="flex items-center gap-1 select-none shrink-0">
                    <div class="flex flex-col items-end text-[9px] leading-tight font-normal text-slate-500">
                      <span class="font-normal text-slate-700 uppercase text-[10px]">{{ item.type }}</span>
                      <span class="text-slate-500 font-normal text-[8px]">{{ item.shape }}</span>
                    </div>

                    <!-- Status Icon (Synchronized with RoomMapPage) -->
                    <div v-if="getRoomStatusIconName(item)" class="w-4 h-4 flex items-center justify-center shrink-0">
                      <RoomIcon 
                        :name="getRoomStatusIconName(item)" 
                        :monochrome="getRoomStatusIconName(item) === 'ooo'"
                        class="w-4 h-4 text-slate-600" 
                      />
                    </div>
                  </div>
                </div>
              </td>

              <!-- Grid Cells -->
              <td 
                v-for="(day, dayIdx) in days" 
                :key="dayIdx" 
                class="border-r border-slate-300 h-full p-0 relative transition-colors duration-75"
                :class="[
                  isCellSelected(item.room, day.fullDate)
                    ? 'ring-2 ring-blue-400 bg-[#72b5f7]/60 z-20 shadow-sm'
                    : (
                        (activeHighlightRoom === item.room)
                          ? 'bg-amber-100/60 shadow-xs'
                          : (item.isVirtual
                              ? (isTodayDate(day.fullDate) ? 'bg-[#ff7043]/15' : (day.isWeekend ? 'bg-[#72b5f7]/20' : 'bg-[#fdf6e2]'))
                              : (isTodayDate(day.fullDate) ? 'bg-[#ff7043]/20' : (day.isWeekend ? 'bg-[#72b5f7]/30' : 'bg-white')))
                      )
                ]"
                @mousedown="handleCellMouseDown(item, day, dayIdx, idx, $event)"
                @mouseenter="handleCellMouseEnter(item, day, dayIdx, idx, $event)"
                @click="handleCellClick(item, day, dayIdx, $event)"
                @contextmenu.prevent="handleCellContextMenu(item, day, $event)"
                @dragover="handleDragOver($event, item, dayIdx)"
                @drop="handleDrop(item, day, $event)"
              >
                <!-- Render bookings starting at this cell -->
                <template v-if="processedBookings[item.room]">
                  <div
                    v-for="(bk, bkIdx) in processedBookings[item.room].filter(b => b.startIndex === dayIdx)"
                    :key="bkIdx"
                    @mouseenter="showTooltip(bk, $event)"
                    @mousemove="updateTooltipPosition($event)"
                    @mouseleave="hideTooltip"
                    @click.stop
                    @dblclick.prevent.stop="handleBookingDblClick(bk)"
                    @contextmenu.prevent.stop="handleBookingContextMenu(bk, $event)"
                    draggable="true"
                    @dragstart="handleDragStart(bk, $event)"
                    @dragend="handleDragEnd"
                    class="absolute top-[2px] h-[33px] border rounded flex items-center px-2.5 z-10 text-[9px] font-bold leading-tight select-none shadow-xs cursor-pointer hover:brightness-95 hover:shadow-md transition-all"
                    :class="[
                      isBookingMatched(bk) ? getBookingClass(bk.type) : 'bg-slate-100 text-slate-400 border-slate-200 opacity-60',
                      splittingBooking?.bookingRoomId === bk.bookingRoomId ? 'z-30 overflow-visible' : 'overflow-hidden',
                      draggedBooking?.bookingRoomId === bk.bookingRoomId ? 'opacity-40 border-dashed' : ''
                    ]"
                    :style="{
                      left: `${bk.leftRatio * 100}%`,
                      width: `calc(${bk.span * 100}% - 2px)`,
                      ...(isBookingMatched(bk) ? getBookingStyle(bk.type) : {})
                    }"
                  >
                    <!-- Left resize handle -->
                    <div 
                      v-if="bk.code !== 'LOCK'"
                      class="absolute top-0 bottom-0 left-0 w-2 z-20 select-none"
                      :style="{ cursor: Number(hotelSettings?.RoomPlan_AllowChangeArrivalDate) === 1 ? 'w-resize' : 'not-allowed' }"
                      @mousedown.stop="startResize(bk, 'start', $event)"
                    ></div>

                    <!-- Right resize handle -->
                    <div 
                      v-if="bk.code !== 'LOCK'"
                      class="absolute top-0 bottom-0 right-0 w-2 z-20 select-none"
                      style="cursor: e-resize;"
                      @mousedown.stop="startResize(bk, 'end', $event)"
                    ></div>

                    <div class="flex items-center gap-1 truncate block w-full pr-1 pb-1.5">
                      <svg 
                        v-if="bk.isDoNotMove"
                        class="w-3 h-3 text-slate-700 shrink-0" 
                        fill="currentColor" 
                        viewBox="0 0 20 20"
                      >
                        <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
                      </svg>
                      <span class="truncate">{{ bk.label }}</span>
                    </div>

                    <!-- Green line for stay / arrival -->
                    <div 
                      v-if="bk.type !== 'OOO' && bk.type !== 'OOS'"
                      class="absolute bottom-0 left-0 h-[3px]"
                      :class="isBookingMatched(bk) ? 'bg-[#22c55e]' : 'bg-slate-300'"
                      :style="{
                        right: bk.showCheckOutIndicator ? `${(0.5 / bk.span) * 100}%` : '0px'
                      }"
                    ></div>

                    <!-- Red line for check-out day -->
                    <div 
                      v-if="bk.type !== 'OOO' && bk.type !== 'OOS' && bk.showCheckOutIndicator"
                      class="absolute bottom-0 right-0 h-[3px]"
                      :class="isBookingMatched(bk) ? 'bg-[#ef4444]' : 'bg-slate-300'"
                      :style="{
                        width: `${(0.5 / bk.span) * 100}%`
                      }"
                    ></div>

                    <!-- Split Handle / Control -->
                    <div 
                      v-if="splittingBooking?.bookingRoomId === bk.bookingRoomId && bk.type !== 'OOO' && bk.type !== 'OOS' && bk.code !== 'LOCK'"
                      class="absolute top-0 bottom-0 w-[4px] bg-white cursor-ew-resize z-40 shadow-[0_0_4px_rgba(0,0,0,0.5)]"
                      :style="{ left: `calc(${((splitIndex - bk.startIndex) / bk.span) * 100}% - 2px)` }"
                      @mousedown.prevent.stop="startDragSplitBar($event)"
                    ></div>

                    <!-- Split Buttons Overlay -->
                    <div 
                      v-if="splittingBooking?.bookingRoomId === bk.bookingRoomId && bk.type !== 'OOO' && bk.type !== 'OOS' && bk.code !== 'LOCK'"
                      class="absolute bottom-[-22px] left-0 flex gap-1 z-50 select-none bg-white border border-slate-200 shadow-lg rounded p-0.5"
                    >
                      <button 
                        @click.stop="cancelSplit" 
                        class="bg-rose-500 hover:bg-rose-600 text-white text-[9px] font-bold px-1.5 py-0.5 rounded border-none cursor-pointer shadow-xs"
                      >
                        Close
                      </button>
                      <button 
                        @click.stop="executeSplit" 
                        class="bg-sky-500 hover:bg-sky-600 text-white text-[9px] font-bold px-1.5 py-0.5 rounded border-none cursor-pointer shadow-xs"
                      >
                        Split
                      </button>
                    </div>
                  </div>
                </template>
              </td>
            </tr>
          </template>
        </tbody>
        <!-- Summary Footers wrapped inside tfoot for gapless sticky bottom rendering -->
        <tfoot class="sticky bottom-0 z-30 bg-white shadow-[0_-2px_4px_rgba(0,0,0,0.05)] border-t border-slate-200" @dragover="handleGlobalDragOver($event)">
          <!-- Summary OCC Footer Row -->
          <tr class="h-[38px] font-black text-slate-800">
            <td 
              class="p-1 sticky left-0 bg-[#93c5fd] shadow-[inset_-1px_-1px_0_#60a5fa] font-extrabold text-[9px] px-1 select-none leading-tight z-40 cursor-help h-[38px] box-border whitespace-nowrap overflow-hidden"
              :title="'Danh sách phòng bận ít nhất một ngày trong giai đoạn này:\n' + (dynamicStats.allPeriodOccRooms?.join(', ') || 'Không có')"
            >
              <div class="flex items-center justify-between w-full text-slate-900 text-[10px] font-black gap-0.5">
                <span>OCC</span>
                <span class="truncate">{{ dynamicStats.totalOccSum }} ({{ dynamicStats.totalOccPercent }}%)</span>
              </div>
            </td>
            <td 
              v-for="(day, idx) in days" 
              :key="idx" 
              class="p-1 text-center text-[9px] font-bold text-slate-800 shadow-[inset_-1px_-1px_0_#93c5fd] cursor-help h-[38px] box-border whitespace-nowrap overflow-hidden leading-tight"
              :class="[
                isTodayDate(day.fullDate) ? 'bg-[#ff7043]/30 shadow-[inset_-1px_-1px_0_#ff8a65]' : 'bg-[#e0f2fe]'
              ]"
              :title="'Danh sách phòng bận ngày ' + day.dateStr + ':\n' + (dynamicStats.occRooms[idx]?.join(', ') || 'Không có')"
            >
              {{ dynamicStats.occ[idx] }} ({{ dynamicStats.totalRooms > 0 ? ((dynamicStats.occ[idx] / dynamicStats.totalRooms) * 100).toFixed(2) : 0 }}%)
            </td>
          </tr>

          <!-- Summary AV Footer Row -->
          <tr class="bg-white h-[38px] font-black text-slate-800">
            <td 
              class="p-1 sticky left-0 bg-[#bae6fd] shadow-[inset_-1px_-1px_0_#7dd3fc] font-extrabold text-[9px] px-1 select-none leading-tight z-40 cursor-help h-[38px] box-border whitespace-nowrap overflow-hidden"
              :title="'Danh sách phòng trống suốt giai đoạn này:\n' + (dynamicStats.allPeriodAvRooms?.join(', ') || 'Không có')"
            >
              <div class="flex items-center justify-between w-full text-slate-900 text-[10px] font-black gap-0.5">
                <span>AV</span>
                <span>{{ dynamicStats.totalAvSum }}</span>
              </div>
            </td>
            <td 
              v-for="(day, idx) in days" 
              :key="idx" 
              class="p-1 text-center text-[9px] font-bold text-slate-700 shadow-[inset_-1px_-1px_0_#e2e8f0] cursor-help h-[38px] box-border whitespace-nowrap overflow-hidden leading-tight"
              :class="[
                isTodayDate(day.fullDate) ? 'bg-[#ff7043]/20' : (day.isWeekend ? 'bg-[#72b5f7]/30' : 'bg-white')
              ]"
              :title="'Danh sách phòng trống ngày ' + day.dateStr + ':\n' + (dynamicStats.avRooms[idx]?.join(', ') || 'Không có')"
            >
              {{ dynamicStats.av[idx] }}
            </td>
          </tr>

          <!-- Summary OOO Footer Row -->
          <tr class="bg-white h-[38px] font-black text-slate-800">
            <td 
              class="p-1 sticky left-0 bg-[#bae6fd] shadow-[inset_-1px_-1px_0_#7dd3fc] font-extrabold text-[9px] px-1 select-none leading-tight z-40 cursor-help h-[38px] box-border whitespace-nowrap overflow-hidden"
              :title="'Danh sách phòng khóa bảo trì ít nhất một ngày trong giai đoạn này:\n' + (dynamicStats.allPeriodOooRooms?.join(', ') || 'Không có')"
            >
              <div class="flex items-center justify-between w-full text-slate-900 text-[10px] font-black gap-0.5">
                <span>OOO</span>
                <span>{{ dynamicStats.totalOooSum }}</span>
              </div>
            </td>
            <td 
              v-for="(day, idx) in days" 
              :key="idx" 
              class="p-1 text-center text-[9px] font-bold text-slate-500 shadow-[inset_-1px_-1px_0_#e2e8f0] cursor-help h-[38px] box-border whitespace-nowrap overflow-hidden leading-tight"
              :class="[
                isTodayDate(day.fullDate) ? 'bg-[#ff7043]/20' : (day.isWeekend ? 'bg-[#72b5f7]/30' : 'bg-white')
              ]"
              :title="'Danh sách phòng khóa bảo trì ngày ' + day.dateStr + ':\n' + (dynamicStats.oooRooms[idx]?.join(', ') || 'Không có')"
            >
              {{ dynamicStats.ooo[idx] }}
            </td>
          </tr>
        </tfoot>
      </table>
    </div>

    <!-- Custom Tooltip -->
    <div 
      v-if="hoveredBooking" 
      class="fixed z-[9999] bg-white text-slate-800 text-[11px] rounded-xl border border-slate-200/80 p-4 shadow-2xl pointer-events-none w-[360px] max-h-[calc(100vh-80px)] overflow-y-auto font-sans"
      :style="{
        left: `${tooltipX}px`,
        top: `${tooltipY}px`
      }"
    >
      <!-- Mode 1: OOO / OOS Locks -->
      <template v-if="hoveredBooking.code === 'LOCK'">
        <div class="flex items-center justify-between font-bold text-xs pb-2 border-b border-slate-100 mb-2">
          <div class="flex items-center gap-1.5">
            <span :class="hoveredBooking.type === 'OOS' ? 'text-slate-500' : 'text-blue-500'">●</span>
            <span>{{ hoveredBooking.checkInFull }}</span>
            <span class="mx-1 text-slate-400">~</span>
            <span>{{ hoveredBooking.checkOutFull }}</span>
          </div>
          <div class="text-slate-400 font-extrabold uppercase">
            {{ hoveredBooking.type }}
          </div>
        </div>
        <div class="flex flex-col gap-1.5 font-semibold text-slate-600">
          <div>Tên: {{ hoveredBooking.name }}</div>
          <div>Loại khóa: {{ hoveredBooking.company }}</div>
          <div>Ghi chú: {{ hoveredBooking.specialRequest }}</div>
        </div>
      </template>

      <!-- Mode 2: Regular Bookings (Matching Image 2) -->
      <template v-else>
        <div class="flex flex-col gap-1.5">
          <!-- Row 1: Mã ĐK -->
          <div class="flex justify-between items-center border-b border-slate-100 pb-1.5">
            <span class="font-extrabold text-slate-900 text-xs">Mã ĐK: {{ hoveredBooking.code }}</span>
            <span class="text-[9px] text-slate-400 font-bold uppercase">{{ hoveredBooking.type }}</span>
          </div>

          <!-- Row 2: Ngày đến ~ Ngày đi -->
          <div class="text-slate-600 font-semibold">
            Ngày đến: <span class="font-bold text-slate-800">{{ hoveredBooking.checkInFull }}</span> ~ Ngày đi: <span class="font-bold text-slate-800">{{ hoveredBooking.checkOutFull }}</span>
          </div>

          <!-- Row 3: Tên ĐK -->
          <div class="text-slate-600 font-semibold">
            Tên ĐK: <span class="font-bold text-slate-800">{{ hoveredBooking.name }}{{ hoveredBooking.company !== 'Khách lẻ' ? `/${hoveredBooking.company}` : '' }}{{ hoveredBooking.phone ? `-${hoveredBooking.phone}` : '' }}</span>
          </div>

          <!-- Row 4: Công ty -->
          <div class="text-slate-600 font-semibold">
            Công ty: <span class="font-bold text-slate-800">{{ hoveredBooking.company }}</span>
          </div>

          <!-- Row 5: Số phòng, Đêm, Giá phòng -->
          <div class="grid grid-cols-3 gap-2 border-t border-slate-100 pt-2 text-slate-600 font-semibold">
            <div>Số phòng: <span class="font-bold text-slate-800">{{ hoveredBooking.room }}</span></div>
            <div>Đêm: <span class="font-bold text-slate-800">{{ hoveredBooking.nights }}</span></div>
            <div class="text-right">Giá phòng: <span class="font-bold text-slate-800">{{ hoveredBooking.price }}</span></div>
          </div>

          <!-- Row 6: Số khách & Thêm giường -->
          <div class="flex justify-between items-center text-slate-600 font-semibold pb-2 border-b border-slate-100">
            <div class="flex items-center gap-1">
              <span>Số khách:</span>
              <span class="flex items-center gap-2.5 ml-1.5">
                <span class="flex items-center gap-0.5" title="Người lớn"><i class="fa-solid fa-user text-slate-400 text-[10px]"></i> {{ hoveredBooking.adults }}</span>
                <span class="flex items-center gap-0.5" title="Trẻ em"><i class="fa-solid fa-child text-slate-400 text-[10px]"></i> {{ hoveredBooking.children }}</span>
                <span class="flex items-center gap-0.5" title="Em bé"><i class="fa-solid fa-baby text-slate-400 text-[10px]"></i> {{ hoveredBooking.babies }}</span>
              </span>
            </div>
            <div>Thêm giường: <span class="font-bold text-slate-800">{{ hoveredBooking.extraBed }}</span></div>
          </div>

          <!-- Billing Info box -->
          <div class="bg-blue-50/50 rounded-lg p-2.5 my-1 border border-blue-100/60 text-slate-600 font-semibold flex justify-between items-stretch">
            <!-- Left Side: Breakdown -->
            <div class="flex-1 flex flex-col gap-1 pr-3 border-r border-slate-200/50 justify-center">
              <div class="flex justify-between items-center text-[10px]">
                <span class="text-slate-500">Tiền phòng cần TT :</span>
                <span class="font-extrabold text-slate-800 ml-2">{{ formatMoney(hoveredBooking.roomChargeDue) }} ₫</span>
              </div>
              <div class="flex justify-between items-center text-[10px]">
                <span class="text-slate-500">Tiền DV cần TT :</span>
                <span class="font-extrabold text-slate-800 ml-2">{{ formatMoney(hoveredBooking.serviceChargeDue) }} ₫</span>
              </div>
            </div>

            <!-- Right Side: Total -->
            <div class="pl-3 flex flex-col justify-center items-center shrink-0 min-w-[90px]">
              <span class="text-[9px] text-slate-400 font-extrabold uppercase mb-0.5">Tổng cộng</span>
              <span class="text-xs text-blue-600 font-black">{{ formatMoney(hoveredBooking.totalAmount) }} ₫</span>
            </div>
          </div>

          <!-- Payment Info -->
          <div class="flex flex-col gap-1 text-slate-600 font-semibold pt-1">
            <div class="flex justify-between items-center text-[10px]">
              <span>Đã đặt cọc:</span>
              <span class="font-extrabold text-slate-800">{{ formatMoney(hoveredBooking.depositAmount) }} ₫</span>
            </div>
            <div class="flex justify-between items-center text-[10px]">
              <span>Đã thanh toán:</span>
              <span class="font-extrabold text-slate-800">{{ formatMoney(hoveredBooking.paidAmount) }} ₫</span>
            </div>
            <div class="flex justify-between items-center pt-1.5 border-t border-slate-100 font-extrabold text-slate-900 text-xs">
              <span>Còn lại:</span>
              <span class="text-rose-600 font-black">{{ formatMoney(hoveredBooking.balance) }} ₫</span>
            </div>
          </div>

          <!-- Notes -->
          <div class="border-t border-slate-100 pt-1.5 mt-1 text-slate-600 font-semibold text-left">
            <span>Ghi chú: </span>
            <span class="text-slate-700 italic font-medium">{{ hoveredBooking.specialRequest || '—' }}</span>
          </div>
        </div>
      </template>
    </div>

    <!-- Context Menu -->
    <div 
      v-if="contextMenu.show" 
      class="fixed z-50 bg-white rounded-lg border border-slate-200 shadow-lg py-1 select-none min-w-[160px] font-sans"
      :style="{
        left: `${contextMenu.x}px`,
        top: `${contextMenu.y}px`
      }"
    >
      <!-- Option for Cell Actions -->
      <template v-if="contextMenu.type === 'cell-actions'">
        <button 
          @click="triggerMenuAction('Tạo')"
          class="w-full text-left px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 border-none bg-transparent cursor-pointer transition-colors"
        >
          Tạo
        </button>
        <button 
          @click="triggerMenuAction('Khóa phòng OOO')"
          class="w-full text-left px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 border-none bg-transparent cursor-pointer transition-colors"
        >
          Khóa phòng OOO
        </button>
        <button 
          @click="triggerMenuAction('Khóa phòng OOS')"
          class="w-full text-left px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 border-none bg-transparent cursor-pointer transition-colors"
        >
          Khóa phòng OOS
        </button>
      </template>

      <!-- Option for Cell Waitlist -->
      <template v-else-if="contextMenu.type === 'cell-waitlist'">
        <button 
          @click="triggerMenuAction('Danh sách chờ')"
          class="w-full text-left px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 border-none bg-transparent cursor-pointer transition-colors"
        >
          Danh sách chờ
        </button>
      </template>

      <!-- Options for Green Booking -->
      <template v-else-if="contextMenu.type === 'green-booking'">
        <button 
          v-if="canCheckInBooking(contextMenu.booking)"
          @click="triggerMenuAction('Giao phòng')"
          class="w-full text-left px-4 py-2.5 text-xs font-bold text-emerald-600 hover:bg-emerald-50 border-none bg-transparent cursor-pointer transition-colors"
        >
          Giao phòng
        </button>
        <button 
          v-if="!contextMenu.booking?.isVirtual"
          @click="triggerMenuAction('Tách Phòng')"
          class="w-full text-left px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 border-none bg-transparent cursor-pointer transition-colors"
        >
          Tách Phòng
        </button>
        <button 
          v-if="!contextMenu.booking?.isDoNotMove"
          @click="triggerMenuAction('Khóa Di Chuyển Phòng')"
          class="w-full text-left px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 border-none bg-transparent cursor-pointer transition-colors"
        >
          Khóa Di Chuyển Phòng
        </button>
        <button 
          v-else
          @click="triggerMenuAction('Mở Khóa Di Chuyển Phòng')"
          class="w-full text-left px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 border-none bg-transparent cursor-pointer transition-colors"
        >
          Mở Khóa Di Chuyển Phòng
        </button>
        <button 
          @click="triggerMenuAction('Gỡ số phòng')"
          class="w-full text-left px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 border-none bg-transparent cursor-pointer transition-colors"
        >
          Gỡ số phòng
        </button>
        <button 
          @click="triggerMenuAction('Hủy phòng')"
          class="w-full text-left px-4 py-2.5 text-xs font-bold text-rose-600 hover:bg-rose-50 border-none bg-transparent cursor-pointer transition-colors"
        >
          Hủy phòng
        </button>
      </template>

      <!-- Options for Blue Booking -->
      <template v-else-if="contextMenu.type === 'blue-booking'">
        <button 
          v-if="!contextMenu.booking?.isDoNotMove"
          @click="triggerMenuAction('Khóa Di Chuyển Phòng')"
          class="w-full text-left px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 border-none bg-transparent cursor-pointer transition-colors"
        >
          Khóa Di Chuyển Phòng
        </button>
        <button 
          v-else
          @click="triggerMenuAction('Mở Khóa Di Chuyển Phòng')"
          class="w-full text-left px-4 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 border-none bg-transparent cursor-pointer transition-colors"
        >
          Mở Khóa Di Chuyển Phòng
        </button>
      </template>

      <!-- Options for Room Lock -->
      <template v-else-if="contextMenu.type === 'lock-actions'">
        <button 
          @click="triggerMenuAction('Mở khóa phòng')"
          class="w-full text-left px-4 py-2.5 text-xs font-bold text-rose-600 hover:bg-rose-50 border-none bg-transparent cursor-pointer transition-colors"
        >
          Mở khóa phòng
        </button>
      </template>
    </div>

    <!-- Filter side drawer -->
    <transition name="slide">
      <div v-if="showFilterDrawer" class="fixed right-4 top-20 bottom-4 w-[320px] bg-white shadow-2xl rounded-2xl border border-slate-200 flex flex-col z-[100] overflow-hidden">
        <!-- Header -->
        <div class="h-[50px] bg-slate-100 flex items-center justify-between px-4 select-none shrink-0 border-b border-slate-200">
          <span class="font-extrabold text-slate-800 text-[14px]">Bộ lọc</span>
          <button @click="showFilterDrawer = false" class="text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer flex items-center justify-center p-1 rounded-full hover:bg-slate-200 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Content Body -->
        <div class="flex-1 p-4 flex flex-col overflow-y-auto gap-4 bg-white text-xs select-none">
          <!-- 1. Lọc Công ty -->
          <div class="flex flex-col gap-2 relative" id="filter-company-dropdown-container">
            <span class="font-bold text-slate-500 uppercase text-[10px] tracking-wider font-semibold">Công ty ({{ tempSelectedCompanies.length }})</span>
            
            <!-- Custom Selector Box -->
            <div 
              @click="toggleCompanyFilterDropdown"
              class="w-full border border-slate-200 rounded-lg px-3 py-2 text-slate-700 bg-white hover:border-slate-300 focus:outline-none focus:ring-1 focus:ring-blue-500 font-semibold cursor-pointer flex items-center justify-between text-[11px] min-h-[32px] shadow-2xs"
            >
              <div class="truncate max-w-[220px]">
                <span v-if="tempSelectedCompanies.length === 0" class="text-slate-400">Chọn công ty...</span>
                <span v-else class="text-slate-800 font-bold">
                  {{ tempSelectedCompanies.join(', ') }}
                </span>
              </div>
              <span class="text-slate-400 shrink-0 ml-1">
                <svg class="w-3.5 h-3.5 transition-transform duration-200" :class="showCompanyFilterDropdown ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                </svg>
              </span>
            </div>

            <!-- Floating Searchable List Popover -->
            <div 
              v-if="showCompanyFilterDropdown" 
              class="absolute left-0 right-0 top-[calc(100%+4px)] z-50 bg-white border border-slate-200 rounded-lg shadow-xl flex flex-col gap-2 p-2 max-h-[220px] overflow-hidden"
            >
              <!-- Search box inside dropdown -->
              <div class="relative flex items-center border border-slate-200 rounded bg-slate-50 px-2 py-1 h-[28px] shrink-0">
                <input 
                  type="text" 
                  v-model="companySearchQuery"
                  placeholder="Tìm công ty..."
                  class="search-input-reset text-slate-700 text-[11px] w-full pr-5"
                  style="border: none !important; outline: none !important; box-shadow: none !important; background-color: transparent !important; height: auto !important; padding: 0 !important; border-radius: 0 !important;"
                />
                <svg class="w-3.5 h-3.5 text-slate-400 absolute right-2 pointer-events-none" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
              </div>

              <!-- List of companies -->
              <div class="overflow-y-auto flex flex-col gap-1 pr-0.5">
                <span v-if="displayCompanies.length === 0" class="text-slate-400 text-center py-2 text-[10px]">Không tìm thấy công ty</span>
                <label 
                  v-else
                  v-for="cName in displayCompanies" 
                  :key="cName" 
                  class="flex items-center gap-2 cursor-pointer hover:bg-slate-100 p-1.5 rounded select-none text-[11px] text-slate-700 font-semibold truncate transition-colors"
                  :title="cName"
                >
                  <input 
                    type="checkbox" 
                    :value="cName" 
                    v-model="tempSelectedCompanies" 
                    class="rounded border-slate-300 text-blue-500 focus:ring-blue-500 w-3.5 h-3.5 cursor-pointer"
                  />
                  <span class="truncate">{{ cName }}</span>
                </label>
              </div>

              <!-- More matches indicator -->
              <div v-if="companyFilterData.hasMore" class="text-slate-400 text-center py-1.5 text-[9px] italic border-t border-slate-100 mt-1 select-none pointer-events-none shrink-0 leading-tight">
                Chỉ hiển thị 20 kết quả đầu tiên.<br/>Nhập thêm ký tự để thu hẹp tìm kiếm...
              </div>
            </div>
          </div>

          <!-- 2. Lọc Loại phòng -->
          <div class="flex flex-col gap-2">
            <span class="font-bold text-slate-500 uppercase text-[10px] tracking-wider font-semibold">Loại phòng ({{ tempSelectedRoomTypes.length }})</span>
            <div class="border border-slate-200 rounded-md p-1.5 bg-slate-50/50 max-h-[220px] overflow-y-auto flex flex-col gap-1 shadow-inner">
              <label 
                v-for="t in roomTypes" 
                :key="t.code" 
                class="flex items-center gap-2 cursor-pointer hover:bg-slate-100/80 p-1 rounded select-none text-[11px] text-slate-700 font-semibold truncate"
                :title="t.name"
              >
                <input 
                  type="checkbox" 
                  :value="t.code" 
                  v-model="tempSelectedRoomTypes" 
                  class="rounded border-slate-300 text-blue-500 focus:ring-blue-500 w-3.5 h-3.5 cursor-pointer"
                />
                <span class="truncate">{{ t.name }}</span>
              </label>
            </div>
          </div>
        </div>

        <!-- Footer Actions -->
        <div class="h-[55px] border-t border-slate-200 bg-slate-50 p-3 flex items-center justify-between shrink-0">
          <button 
            @click="clearFilters"
            class="px-4 py-1.5 bg-white hover:bg-slate-100 text-slate-700 border border-slate-200 rounded-md text-xs font-bold cursor-pointer transition-colors shadow-xs"
          >
            Xóa lọc
          </button>
          <button 
            @click="applyFilters"
            class="px-5 py-1.5 bg-blue-500 hover:bg-blue-600 text-white border-none rounded-md text-xs font-bold cursor-pointer transition-colors shadow-xs"
          >
            Áp dụng
          </button>
        </div>
      </div>
    </transition>

    <!-- Waiting List side drawer -->
    <transition name="slide">
      <div v-if="showWaitingList" class="fixed right-4 top-20 bottom-4 w-[650px] max-w-[92vw] bg-white shadow-2xl rounded-2xl border border-slate-200 flex flex-col z-[100] overflow-hidden">
        <!-- Header -->
        <div class="h-[50px] bg-[#7dd3fc] flex items-center justify-between px-4 select-none shrink-0 relative">
          <div class="flex-1 flex justify-center">
            <span class="font-extrabold text-white text-[15px] tracking-wide">Danh sách chờ</span>
          </div>
          <!-- Close button -->
          <button @click="showWaitingList = false" class="absolute right-4 text-white hover:text-slate-100 bg-transparent border-none cursor-pointer flex items-center justify-center p-1 rounded-full hover:bg-white/10 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Content Body -->
        <div class="flex-1 p-4 flex flex-col overflow-hidden bg-slate-50/50">
          <!-- Date Range Control (Identical to Kế Hoạch Phòng topbar) -->
          <div class="flex items-center gap-2 mb-4 select-none shrink-0">
            <div class="relative">
              <button 
                @click.stop="toggleWaitlistDatePickerPopover"
                class="flex items-center gap-2 border border-slate-200 rounded-lg bg-white px-3 py-1.5 shadow-sm text-xs font-semibold text-slate-700 hover:bg-slate-50 transition-colors cursor-pointer h-[32px]"
              >
                <span>{{ waitlistDateRangeText }}</span>
                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
              </button>

              <!-- Date Picker Popover for Waitlist -->
              <div 
                v-if="showWaitlistDatePickerPopover" 
                class="absolute left-0 top-[36px] bg-white border border-slate-200 rounded-lg shadow-xl z-[150] p-3.5 flex flex-col gap-3 w-[260px]"
                @click.stop
              >
                <h4 class="text-xs font-extrabold text-slate-800 m-0">Chọn khoảng thời gian</h4>
                <div class="flex flex-col gap-2">
                  <div class="flex flex-col gap-1">
                    <span class="text-[10px] font-bold text-slate-500 uppercase">Từ ngày</span>
                    <input 
                      type="date" 
                      v-model="tempWaitlistStartDateStr"
                      class="border border-slate-200 rounded px-2 py-1 text-xs text-slate-700 focus:outline-none focus:ring-1 focus:ring-blue-500 w-full font-sans"
                    />
                  </div>
                  <div class="flex flex-col gap-1">
                    <span class="text-[10px] font-bold text-slate-500 uppercase">Đến ngày</span>
                    <input 
                      type="date" 
                      v-model="tempWaitlistEndDateStr"
                      class="border border-slate-200 rounded px-2 py-1 text-xs text-slate-700 focus:outline-none focus:ring-1 focus:ring-blue-500 w-full font-sans"
                    />
                  </div>
                </div>
                <div class="flex items-center justify-between border-t border-slate-100 pt-2 mt-1">
                  <button 
                    @click="showWaitlistDatePickerPopover = false"
                    class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded text-[11px] font-semibold border-none cursor-pointer transition-colors"
                  >
                    Đóng
                  </button>
                  <button 
                    @click="saveWaitlistDateRange"
                    class="px-4 py-1.5 bg-[#7dd3fc] hover:bg-sky-400 text-white rounded text-[11px] font-bold border-none shadow-sm cursor-pointer transition-colors"
                  >
                    Lưu
                  </button>
                </div>
              </div>
            </div>

            <!-- View Button -->
            <button 
              @click="saveWaitlistDateRange"
              class="px-4 py-1.5 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-xs font-bold border-none shadow-sm transition-colors cursor-pointer h-[32px]"
            >
              View
            </button>
          </div>

          <!-- Waiting List Table Container -->
          <div class="flex-1 overflow-auto border border-slate-200 rounded-lg bg-white shadow-xs">
            <table class="w-full text-xs text-left border-collapse table-fixed select-none">
              <colgroup>
                <col class="w-[200px]" />
                <col class="w-[140px]" />
                <col class="w-[140px]" />
                <col class="w-[120px]" />
              </colgroup>
              <thead class="sticky top-0 z-10 bg-slate-100 text-slate-600 font-bold border-b border-slate-200 h-9">
                <tr>
                  <th class="px-3 py-2 font-bold text-slate-700 text-[11px]">Tên khách</th>
                  <th class="px-2 py-2 text-center font-bold text-slate-700 text-[11px]">
                    <span class="w-2.5 h-2.5 inline-block rounded-full bg-emerald-500 mr-1"></span>
                    Ngày đến
                  </th>
                  <th class="px-2 py-2 text-center font-bold text-slate-700 text-[11px]">
                    <span class="w-2.5 h-2.5 inline-block rounded-full bg-rose-500 mr-1"></span>
                    Ngày đi
                  </th>
                  <th class="px-3 py-2 text-right font-bold text-slate-700 text-[11px]">Loại phòng</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 font-normal text-slate-600">
                <tr v-if="waitingListItems.length === 0" class="h-20 bg-white">
                  <td colspan="4" class="text-center text-slate-400 py-6 select-none font-medium">
                    Không có đăng ký nào trong danh sách chờ
                  </td>
                </tr>
                <tr v-else v-for="(item, idx) in waitingListItems" :key="idx" class="hover:bg-slate-50/50 h-[38px]">
                  <td class="px-3 py-2 truncate text-slate-800 font-medium text-[11px]">{{ item.guestName }}</td>
                  <td class="px-2 py-2 text-center text-[11px] text-slate-600 font-normal">{{ item.checkIn }}</td>
                  <td class="px-2 py-2 text-center text-[11px] text-slate-600 font-normal">{{ item.checkOut }}</td>
                  <td class="px-3 py-2 text-right font-bold text-slate-700 text-[11px]">{{ item.type }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </transition>
    <!-- Quick Booking Modal -->
    <div v-if="showQuickBookingModal" class="fixed inset-0 z-[200] flex items-center justify-center bg-black/50 select-none">
      <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 w-[450px] overflow-hidden flex flex-col font-sans">
        <!-- Header -->
        <div class="h-[50px] bg-blue-600 flex items-center justify-between px-4 text-white shrink-0">
          <span class="font-bold text-sm tracking-wide">Booking</span>
          <button @click="showQuickBookingModal = false" class="text-white hover:text-slate-200 bg-transparent border-none cursor-pointer flex items-center justify-center p-1 rounded-full hover:bg-white/10 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Form Body -->
        <div class="p-5 flex flex-col gap-4 text-xs text-left">
          <!-- Searchable Company Dropdown -->
          <div class="flex flex-col gap-1.5 relative" id="quick-booking-company-container">
            <label class="font-bold text-slate-700 text-left w-full block">Company:</label>
            <div class="relative">
              <input 
                type="text" 
                v-model="quickBookingCompanySearch" 
                @focus="showQuickBookingCompanyDropdown = true"
                @input="showQuickBookingCompanyDropdown = true"
                placeholder="Tìm kiếm công ty..."
                class="w-full border border-slate-200 rounded-lg px-3 py-2 text-slate-800 focus:outline-none focus:ring-1 focus:ring-blue-500 font-semibold cursor-pointer pr-8"
              />
              <span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                </svg>
              </span>
            </div>
            
            <!-- Floating dropdown list -->
            <div 
              v-if="showQuickBookingCompanyDropdown" 
              class="absolute left-0 right-0 top-[calc(100%+4px)] z-50 bg-white border border-slate-200 rounded-lg shadow-lg max-h-[180px] overflow-y-auto py-1 font-sans text-xs"
            >
              <div 
                v-if="filteredQuickBookingCompanies.length === 0" 
                class="px-3 py-2 text-slate-400 italic text-center"
              >
                Không tìm thấy công ty nào
              </div>
              <div 
                v-else
                v-for="cName in filteredQuickBookingCompanies" 
                :key="cName"
                @click="selectQuickBookingCompany(cName)"
                class="px-3 py-2 hover:bg-blue-50 text-slate-700 font-semibold cursor-pointer transition-colors truncate"
                :class="quickBookingForm.company === cName ? 'bg-blue-50/50 text-blue-600 font-bold' : ''"
              >
                {{ cName }}
              </div>
            </div>
          </div>

          <!-- Market Segment & Source Code -->
          <div class="grid grid-cols-2 gap-4">
            <div class="flex flex-col gap-1.5">
              <label class="font-bold text-slate-700 text-left w-full block">Market Segment</label>
              <select v-model="quickBookingForm.marketId" class="w-full border border-slate-200 rounded-lg bg-[#fffbeb] px-3 py-2 text-slate-800 focus:outline-none focus:ring-1 focus:ring-blue-500 font-semibold cursor-pointer">
                <option :value="null" disabled>— Chọn thị trường —</option>
                <option v-for="m in allMarkets" :key="m.id" :value="m.id">
                  {{ m.name }}
                </option>
              </select>
            </div>
            <div class="flex flex-col gap-1.5">
              <label class="font-bold text-slate-700 text-left w-full block">Source Code</label>
              <select v-model="quickBookingForm.customerSourceId" class="w-full border border-slate-200 rounded-lg bg-[#fffbeb] px-3 py-2 text-slate-800 focus:outline-none focus:ring-1 focus:ring-blue-500 font-semibold cursor-pointer">
                <option :value="null" disabled>— Chọn nguồn khách —</option>
                <option v-for="s in allCustomerSources" :key="s.id" :value="s.id">
                  {{ s.name }}
                </option>
              </select>
            </div>
          </div>

          <!-- Booking name -->
          <div class="flex flex-col gap-1.5">
            <label class="font-bold text-slate-700 text-left w-full block">Booking name:</label>
            <input type="text" v-model="quickBookingForm.bookingName" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-slate-800 focus:outline-none focus:ring-1 focus:ring-blue-500 font-semibold" />
          </div>

          <!-- Rate Code -->
          <div class="flex flex-col gap-1.5">
            <label class="font-bold text-slate-700 text-left w-full block">Rate Code:</label>
            <select v-model="quickBookingForm.rateCode" @change="calculateQuickBookingPrice" class="w-full border border-slate-200 rounded-lg bg-white px-3 py-2 text-slate-800 focus:outline-none focus:ring-1 focus:ring-blue-500 font-semibold cursor-pointer">
              <option value="Vui lòng chọn giá phòng">Vui lòng chọn giá phòng</option>
              <option v-for="rc in rateCodes" :key="rc.Ma" :value="rc.Ma">
                {{ rc.Ma }} - {{ rc.Description || 'Không có mô tả' }}
              </option>
            </select>
          </div>

          <!-- Rate -->
          <div class="flex flex-col gap-1.5">
            <label class="font-bold text-slate-700 text-left w-full block">Rate:</label>
            <div class="relative flex items-center border border-slate-200 rounded-lg overflow-hidden bg-white shadow-xs">
              <input type="number" v-model="quickBookingForm.rate" class="w-full border-none px-3 py-2 text-slate-800 focus:outline-none focus:ring-0 font-semibold" />
              <div class="absolute right-2 flex flex-col gap-0.5 z-10">
                <button 
                  type="button" 
                  @click="quickBookingForm.rate = Number(quickBookingForm.rate || 0) + 50000"
                  class="p-0.5 hover:bg-slate-100 rounded text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer flex items-center justify-center h-3 w-4.5"
                >
                  <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 15.75l7.5-7.5 7.5 7.5" />
                  </svg>
                </button>
                <button 
                  type="button" 
                  @click="quickBookingForm.rate = Math.max(0, Number(quickBookingForm.rate || 0) - 50000)"
                  class="p-0.5 hover:bg-slate-100 rounded text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer flex items-center justify-center h-3 w-4.5"
                >
                  <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                  </svg>
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Footer Actions -->
        <div class="p-4 border-t border-slate-100 flex items-center justify-end gap-3 shrink-0">
          <button 
            @click="showQuickBookingModal = false"
            class="flex items-center gap-1.5 px-4 py-2 border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 rounded-lg text-xs font-bold cursor-pointer transition-colors shadow-sm"
          >
            <svg class="w-4 h-4 text-rose-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>Đóng</span>
          </button>
          <button 
            @click="saveQuickBooking"
            class="flex items-center gap-1.5 px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white border-none rounded-lg text-xs font-bold cursor-pointer transition-colors shadow-md"
          >
            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
            </svg>
            <span>Lưu</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Lock Room Modal -->
    <div v-if="showLockRoomModal" class="fixed inset-0 z-[200] flex items-center justify-center bg-black/50 select-none">
      <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 w-[360px] overflow-hidden flex flex-col font-sans">
        <!-- Header -->
        <div class="h-[50px] bg-blue-600 flex items-center justify-between px-4 text-white shrink-0">
          <span class="font-bold text-sm tracking-wide">Lock Room {{ lockRoomType }}</span>
          <button @click="showLockRoomModal = false" class="text-white hover:text-slate-200 bg-transparent border-none cursor-pointer flex items-center justify-center p-1 rounded-full hover:bg-white/10 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Form Body -->
        <div class="p-5 flex flex-col gap-2.5 text-xs text-left">
          <label class="font-bold text-slate-700 text-[13px] text-left w-full block">Note <span class="text-red-500">*</span></label>
          <input type="text" v-model="lockRoomForm.note" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-slate-800 focus:outline-none focus:ring-1 focus:ring-blue-500 font-semibold" placeholder="Nhập ghi chú khóa phòng..." />
        </div>

        <!-- Footer Actions -->
        <div class="p-4 border-t border-slate-100 flex items-center justify-end gap-3 shrink-0">
          <button 
            @click="showLockRoomModal = false"
            class="flex items-center gap-1.5 px-4 py-2 border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 rounded-lg text-xs font-bold cursor-pointer transition-colors shadow-sm"
          >
            <svg class="w-4 h-4 text-rose-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>Đóng</span>
          </button>
          <button 
            @click="saveLockRoom"
            class="flex items-center gap-1.5 px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white border-none rounded-lg text-xs font-bold cursor-pointer transition-colors shadow-md"
          >
            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
            </svg>
            <span>Lưu</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Legend Color Picker Popover -->
    <div 
      v-if="showColorPicker && selectedLegendForColor"
      ref="colorPickerRef"
      class="fixed z-50 bg-white rounded-xl shadow-2xl border border-slate-200 p-3 w-[260px] flex flex-col gap-3 select-none text-left"
      :style="{ top: `${pickerPosition.top}px`, left: `${pickerPosition.left}px`, boxShadow: '0 10px 25px -5px rgba(0,0,0,0.1), 0 8px 10px -6px rgba(0,0,0,0.1)' }"
    >
      <!-- Color saturation/value gradient block -->
      <div 
        ref="colorAreaRef"
        class="h-32 rounded-lg relative overflow-hidden cursor-crosshair border border-slate-200"
        :style="{ backgroundColor: `hsl(${hue}, 100%, 50%)` }"
        @mousedown="startDragColorArea"
      >
        <!-- White saturation gradient overlay -->
        <div class="absolute inset-0" style="background: linear-gradient(to right, #fff, transparent);"></div>
        <!-- Black value gradient overlay -->
        <div class="absolute inset-0" style="background: linear-gradient(to top, #000, transparent);"></div>
        
        <!-- White pointer circle -->
        <div 
          class="absolute w-3 h-3 rounded-full border-2 border-white -translate-x-1.5 -translate-y-1.5 shadow-sm pointer-events-none"
          :style="{ left: `${saturation}%`, top: `${100 - value}%` }"
        ></div>
      </div>

      <!-- Hue & Alpha Sliders row next to Preview box -->
      <div class="flex items-center gap-3">
        <!-- Sliders stacked -->
        <div class="flex-1 flex flex-col gap-2">
          <!-- Hue Rainbow Slider -->
          <div 
            class="h-2.5 rounded relative cursor-pointer" 
            style="background: linear-gradient(to right, #f00 0%, #ff0 17%, #0f0 33%, #0ff 50%, #00f 67%, #f0f 83%, #f00 100%);"
            @mousedown="startDragHue"
          >
            <div 
              class="absolute top-[-2px] bottom-[-2px] w-1.5 bg-white border border-slate-400 rounded cursor-pointer shadow-sm translate-x-[-50%]"
              :style="{ left: `${(hue / 360) * 100}%` }"
            ></div>
          </div>

          <!-- Alpha Opacity Slider -->
          <div 
            class="h-2.5 rounded relative cursor-pointer overflow-hidden border border-slate-100" 
            style="background-image: linear-gradient(45deg, #ccc 25%, transparent 25%), linear-gradient(-45deg, #ccc 25%, transparent 25%), linear-gradient(45deg, transparent 75%, #ccc 75%), linear-gradient(-45deg, transparent 75%, #ccc 75%); background-size: 8px 8px; background-position: 0 0, 0 4px, 4px -4px, -4px 0;"
            @mousedown="startDragAlpha"
          >
            <!-- Overlay showing opacity to current solid color -->
            <div 
              class="absolute inset-0"
              :style="{ background: `linear-gradient(to right, transparent, ${computedHex})` }"
            ></div>
            <div 
              class="absolute top-[-2px] bottom-[-2px] w-1.5 bg-white border border-slate-400 rounded cursor-pointer shadow-sm translate-x-[-50%]"
              :style="{ left: `${alpha}%` }"
            ></div>
          </div>
        </div>

        <!-- Solid/Current Color Preview Block -->
        <div 
          class="w-8 h-8 rounded border border-slate-300 shadow-3xs shrink-0" 
          :style="{ backgroundColor: computedHex }"
        ></div>
      </div>

      <!-- Hex & RGB Inputs Row -->
      <div class="grid grid-cols-5 gap-1.5 text-[10px] text-slate-400 font-bold uppercase">
        <div class="flex flex-col items-center gap-0.5">
          <input 
            type="text" 
            :value="computedHex.replace('#', '').toUpperCase()"
            @change="handleHexInputChange"
            class="w-full text-center border border-slate-200 rounded px-1 py-1 font-mono text-[10px] uppercase text-slate-700 focus:outline-sky-400 h-6 leading-none"
          />
          <span class="text-[9px] font-bold text-slate-400">Hex</span>
        </div>
        <div class="flex flex-col items-center gap-0.5">
          <input 
            type="number" 
            :value="computedRgb.r"
            @input="handleRgbInput('r', $event.target.value)"
            class="w-full text-center border border-slate-200 rounded px-1 py-1 font-mono text-[10px] text-slate-700 focus:outline-sky-400 h-6 leading-none"
          />
          <span class="text-[9px] font-bold text-slate-400">R</span>
        </div>
        <div class="flex flex-col items-center gap-0.5">
          <input 
            type="number" 
            :value="computedRgb.g"
            @input="handleRgbInput('g', $event.target.value)"
            class="w-full text-center border border-slate-200 rounded px-1 py-1 font-mono text-[10px] text-slate-700 focus:outline-sky-400 h-6 leading-none"
          />
          <span class="text-[9px] font-bold text-slate-400">G</span>
        </div>
        <div class="flex flex-col items-center gap-0.5">
          <input 
            type="number" 
            :value="computedRgb.b"
            @input="handleRgbInput('b', $event.target.value)"
            class="w-full text-center border border-slate-200 rounded px-1 py-1 font-mono text-[10px] text-slate-700 focus:outline-sky-400 h-6 leading-none"
          />
          <span class="text-[9px] font-bold text-slate-400">B</span>
        </div>
        <div class="flex flex-col items-center gap-0.5">
          <input 
            type="number" 
            v-model="alpha"
            min="0"
            max="100"
            class="w-full text-center border border-slate-200 rounded px-1 py-1 font-mono text-[10px] text-slate-700 focus:outline-sky-400 h-6 leading-none"
          />
          <span class="text-[9px] font-bold text-slate-400">A</span>
        </div>
      </div>

      <!-- Preset Grid -->
      <div class="grid grid-cols-8 gap-1.5 pt-1.5 border-t border-slate-100 justify-items-center">
        <button 
          v-for="color in pickerPresets" 
          :key="color"
          @click="selectPresetColor(color)"
          class="w-4 h-4 rounded-full border border-slate-200/50 cursor-pointer shadow-3xs hover:scale-115 transition-transform duration-100 p-0"
          :style="{ backgroundColor: color }"
        ></button>
      </div>

      <!-- Actions (Blue Save Button at bottom) -->
      <div class="flex items-center gap-1.5 border-t border-slate-100 pt-2 shrink-0">
        <button 
          @click="saveLegendColor" 
          :disabled="savingColor"
          class="w-full py-1.5 bg-[#72b5f7] hover:bg-[#5da3e5] text-white rounded-lg font-bold text-xs border-none cursor-pointer shadow-md transition-colors disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-1"
        >
          <span v-if="savingColor" class="w-3.5 h-3.5 border-2 border-white/50 border-t-white rounded-full animate-spin"></span>
          Save
        </button>
      </div>
    </div>
  </div>

  <!-- HỦY PHÒNG REASON MODAL -->
  <Teleport to="body">
    <CancelReasonModal
      v-model:show="isCancelReasonModalOpen"
      title="Xác nhận hủy phòng"
      subTitle="Vui lòng chọn lý do hủy phòng bên dưới. Nội dung sẽ được ghi log vào hệ thống."
      @confirm="handleConfirmCancelRoomPlan"
    />
  </Teleport>

  <!-- CUSTOM VERTICAL DRAG GHOST -->
  <Teleport to="body">
    <div 
      v-if="draggedBooking && draggedBookingRect"
      class="fixed z-[9999] pointer-events-none border rounded flex items-center px-2.5 text-[9px] font-bold leading-tight select-none shadow-2xl opacity-90 transition-all duration-100 ease-out"
      :class="[
        isBookingMatched(draggedBooking) ? getBookingClass(draggedBooking.type) : 'bg-slate-100 text-slate-400 border-slate-200'
      ]"
      :style="{
        left: `${draggedBookingRect.left}px`,
        width: `${draggedBookingRect.width}px`,
        top: `${dragGhostY}px`,
        height: '33px',
        ...(isBookingMatched(draggedBooking) ? getBookingStyle(draggedBooking.type) : {})
      }"
    >
      <div class="flex items-center gap-1 truncate block w-full pr-1 pb-1.5">
        <svg 
          v-if="draggedBooking.isDoNotMove"
          class="w-3 h-3 text-slate-700 shrink-0" 
          fill="currentColor" 
          viewBox="0 0 20 20"
        >
          <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
        </svg>
        <span class="truncate">{{ draggedBooking.label }}</span>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.slide-enter-active, .slide-leave-active {
  transition: transform 0.35s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.3s ease;
}
.slide-enter-from, .slide-leave-to {
  transform: translateX(100%);
  opacity: 0;
}

input.search-input-reset {
  border: none !important;
  outline: none !important;
  box-shadow: none !important;
  background: transparent !important;
  background-color: transparent !important;
  margin: 0 !important;
  border-radius: 0 !important;
  height: auto !important;
}
input.search-input-reset:focus {
  border: none !important;
  outline: none !important;
  box-shadow: none !important;
  background: transparent !important;
  background-color: transparent !important;
}
</style>
