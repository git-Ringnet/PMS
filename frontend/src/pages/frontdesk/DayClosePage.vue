<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  Calendar,
  CalendarDays,
  SlidersHorizontal,
  FileText,
  DollarSign,
  MinusSquare,
  PlusSquare,
  RefreshCw,
  Search,
  CheckSquare,
  Square,
  Grid,
  BedDouble,
  CalendarRange,
  Edit3,
  Settings,
  Briefcase,
  BarChart3,
  History,
  CalendarPlus,
  UserX
} from '@lucide/vue'
import http from '@/services/http'
import { useUiStore } from '@/stores/ui-store'
import LoadingOverlay from '@/components/LoadingOverlay.vue'

const router = useRouter()
const uiStore = useUiStore()

// Active sub-navigation item
const activeSubNav = ref('day-close')
const subNavItems = [
  { id: 'room-map', name: 'Sơ đồ phòng', icon: Grid, route: '/reservation' },
  { id: 'empty-rooms', name: 'Phòng trống', icon: BedDouble, route: '/reservation?view=empty' },
  { id: 'room-plan', name: 'Kế hoạch phòng', icon: CalendarRange, route: '/reservation?view=plan' },
  { id: 'create-booking', name: 'Tạo đăng ký', icon: Edit3, route: '/reservation?action=create' },
  { id: 'checkout', name: 'Trả phòng', icon: DollarSign, route: '/frontdesk?tab=checkout' },
  { id: 'room-management', name: 'Quản lý phòng', icon: Settings, route: '/frontdesk?tab=room-management' },
  { id: 'general-search', name: 'Tìm kiếm chung', icon: Search, route: '/frontdesk?tab=search' },
  { id: 'day-close', name: 'Sang ngày', icon: CalendarDays, route: '/frontdesk?tab=day-close' },
  { id: 'task-list', name: 'DS Công việc', icon: Briefcase, route: '/frontdesk?tab=tasks' },
  { id: 'reports', name: 'Báo cáo', icon: BarChart3, route: '/reports' },
  { id: 'audit-log', name: 'Lịch sử thao tác', icon: History, route: '/reports?tab=audit-log' },
]

// Top right room counters
const arrivalCount = ref(0)
const departureCount = ref(0)

// Trạng thái xử lý phòng đi & phòng đến cho ngày hệ thống
const departuresProcessed = ref(false)
const arrivalsProcessed = ref(true)

// Controls cho Modals
const showArrivalModal = ref(false)
const arrivalFeeOption = ref('all_charged') // 'all_charged' | 'no_charge' | 'has_charge'

const showExtendStayModal = ref(false)
const extendNightsInput = ref(1)

// [Bug A] Noshow modal
const showNoshowModal = ref(false)
const noshowFeeOption = ref('no_charge') // 'all_charged' | 'room_only' | 'no_charge'

const canRollDay = computed(() => {
  return arrivalCount.value === 0 && departureCount.value === 0
})

// Filter tabs
const activeFilterTab = ref('in-house')
const filterTabs = [
  { id: 'in-house', name: 'Phòng đang ở' },
  { id: 'arrivals', name: 'Phòng đến' },
  { id: 'departures', name: 'Phòng đi' },
  { id: 'hourly', name: 'Ở theo giờ' },
]

// Footer options
const occupiedToDirty = ref(true)
const emptyToInspect = ref(true)

// Data structure grouped by company / source (populated dynamically from backend)
const groupData = ref([])

const isRolling = ref(false)
const systemDate = ref('')
const isLoading = ref(false)
const rawBookings = ref([])

// Helper function to extract YYYY-MM-DD reliably in local Vietnam timezone
function getNormalizedDate(val) {
  if (!val) return ''
  const str = String(val).trim()
  if (/^\d{4}-\d{2}-\d{2}$/.test(str)) {
    return str
  }

  try {
    const d = new Date(str)
    if (!isNaN(d.getTime())) {
      return new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Ho_Chi_Minh' }).format(d)
    }
  } catch (e) {}

  return str.substring(0, 10)
}

function getEffectiveArrivalDate(r, b) {
  const arr = r ? (r.arrival_date || b.arrival_date) : b.arrival_date
  return getNormalizedDate(arr)
}

function getEffectiveDepartureDate(r, b) {
  const dep = r ? (r.departure_date || b.departure_date) : b.departure_date
  if (dep) {
    const norm = getNormalizedDate(dep)
    if (norm) return norm
  }

  const arr = getEffectiveArrivalDate(r, b)
  const nights = (r && r.num_of_days) || b.num_of_days || 1
  if (arr && /^\d{4}-\d{2}-\d{2}$/.test(arr)) {
    const parts = arr.split('-')
    const d = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]))
    d.setDate(d.getDate() + nights)
    const y = d.getFullYear()
    const m = String(d.getMonth() + 1).padStart(2, '0')
    const day = String(d.getDate()).padStart(2, '0')
    return `${y}-${m}-${day}`
  }

  return ''
}

// Helper function to format date (YYYY-MM-DD to DD/MM/YYYY)
function formatDateVN(dateStr) {
  if (!dateStr) return '-'
  const ymd = getNormalizedDate(dateStr)
  if (!ymd || !/^\d{4}-\d{2}-\d{2}$/.test(ymd)) return dateStr
  const [year, month, day] = ymd.split('-')
  return `${day}/${month}/${year}`
}

function calculateNights(arrivalStr, departureStr) {
  if (!arrivalStr || !departureStr) return 1
  const a = new Date(getNormalizedDate(arrivalStr))
  const d = new Date(getNormalizedDate(departureStr))
  const diffTime = d.getTime() - a.getTime()
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24))
  return diffDays > 0 ? diffDays : 1
}

const fetchRealData = async () => {
  try {
    isLoading.value = true
    // 1. Lấy ngày hệ thống thực tế
    const dateRes = await http.get('/system-date')
    if (dateRes.data && dateRes.data.success && dateRes.data.data) {
      systemDate.value = dateRes.data.data.system_date
    } else {
      systemDate.value = new Date().toISOString().split('T')[0]
    }

    // 2. Lấy danh sách booking từ backend API
    const bookingsRes = await http.get('/bookings')
    if (bookingsRes.data && (bookingsRes.data.success || Array.isArray(bookingsRes.data.data))) {
      const apiData = Array.isArray(bookingsRes.data.data) ? bookingsRes.data.data : (bookingsRes.data || [])
      rawBookings.value = apiData
      
      if (apiData.length > 0) {
        processRealBookings(apiData)
      }
    }
  } catch (err) {
    console.error('Lỗi khi tải dữ liệu thực tế cho trang sang ngày:', err)
  } finally {
    isLoading.value = false
  }
}

function getStatusString(val) {
  if (val === null || val === undefined) return ''
  if (typeof val === 'object') return String(val.name || val.code || val.id || '').toLowerCase()
  return String(val).toLowerCase()
}

function checkRoomStatus(r, b) {
  const rStatus = r ? r.status : null
  const bStatus = b ? (b.status ?? b.registration_status_id) : null

  const raw = rStatus !== null && rStatus !== undefined ? rStatus : bStatus
  const str = getStatusString(raw)

  const isCheckedIn = (
    str === '1' ||
    str === 'checked_in' ||
    str === 'in_house' ||
    str === 'occupied' ||
    str === 'inhouse' ||
    str === 'dang_o'
  )

  const isCheckedOut = (
    str === '2' ||
    str === 'checked_out' ||
    str === 'checkedout' ||
    str === 'da_tra_phong'
  )

  const isCancelled = (
    str === '3' ||
    str === '4' ||
    str === 'cancelled' ||
    str === 'canceled' ||
    str === 'no_show' ||
    str === 'noshow' ||
    str === 'da_huy'
  )

  return {
    isCheckedIn,
    isCheckedOut,
    isCancelled,
    isBooked: !isCheckedIn && !isCheckedOut && !isCancelled
  }
}

function processRealBookings(bookings) {
  const sysDateStr = getNormalizedDate(systemDate.value) || getNormalizedDate(new Date())
  
  // Tính đếm số phòng đến và phòng đi theo ngày hệ thống
  let arrCount = 0
  let depCount = 0
  
  bookings.forEach(b => {
    const rooms = b.booking_rooms || b.bookingRooms || []
    if (rooms.length > 0) {
      rooms.forEach(r => {
        const { isCheckedIn, isCheckedOut, isCancelled } = checkRoomStatus(r, b)
        if (isCancelled || isCheckedOut) return

        const arrDate = getEffectiveArrivalDate(r, b)
        const depDate = getEffectiveDepartureDate(r, b)

        // Phòng đến: Khách đến vào ngày hệ thống nhưng chưa check-in
        if (arrDate === sysDateStr && !isCheckedIn) arrCount++
        // Phòng đi: Khách đang ở và đi vào ngày hệ thống
        if (depDate === sysDateStr && isCheckedIn) depCount++
      })
    } else {
      const { isCheckedIn, isCheckedOut, isCancelled } = checkRoomStatus(null, b)
      if (isCancelled || isCheckedOut) return

      const arrDate = getEffectiveArrivalDate(null, b)
      const depDate = getEffectiveDepartureDate(null, b)

      if (arrDate === sysDateStr && !isCheckedIn) arrCount++
      if (depDate === sysDateStr && isCheckedIn) depCount++
    }
  })

  arrivalCount.value = arrCount
  departureCount.value = depCount

  if (arrCount === 0) arrivalsProcessed.value = true
  if (depCount === 0) departuresProcessed.value = true

  // Nhóm theo Công ty / Nguồn khách
  const groupsMap = {}

  bookings.forEach((b) => {
    const companyName = b.company?.name 
      ? `Công ty: ${b.company.name}` 
      : (b.company_name ? `Công ty: ${b.company_name}` : 'Khách lẻ')

    const rooms = b.booking_rooms || b.bookingRooms || []

    const processItem = (r, rIdx) => {
      const { isCheckedIn, isCheckedOut, isCancelled } = checkRoomStatus(r, b)
      if (isCancelled || isCheckedOut) return

      const arrDate = getEffectiveArrivalDate(r, b)
      const depDate = getEffectiveDepartureDate(r, b)

      // Lọc theo tab hiện tại
      let matchesTab = false
      if (activeFilterTab.value === 'arrivals') {
        // Phòng đến: Chưa check-in và đến hôm nay
        matchesTab = (arrDate === sysDateStr && !isCheckedIn)
      } else if (activeFilterTab.value === 'departures') {
        // Phòng đi: Đang ở (checked-in) và đi hôm nay
        matchesTab = (depDate === sysDateStr && isCheckedIn)
      } else if (activeFilterTab.value === 'hourly') {
        const isSameDay = arrDate === depDate || b.is_hourly || b.is_day_use || (b.num_of_days === 0)
        matchesTab = isSameDay && arrDate === sysDateStr
      } else {
        // 'in-house': Phòng đang ở
        matchesTab = isCheckedIn
      }

      if (!matchesTab) return

      if (!groupsMap[companyName]) {
        groupsMap[companyName] = {
          id: `grp-real-${Object.keys(groupsMap).length + 1}`,
          companyName: companyName,
          expanded: true,
          selected: false,
          items: []
        }
      }

      const guestName = r && r.guests && r.guests.length > 0 
        ? (r.guests[0].guest?.full_name || r.guests[0].full_name || b.contact_name || b.booking_name)
        : (b.contact_name || b.booking_name || 'Khách Vãng Lai')

      groupsMap[companyName].items.push({
        id: `real-row-${b.id}-${r ? (r.id || rIdx) : '0'}`,
        bookingCode: b.code || b.booking_code || `GAL${b.id}`,
        vat: b.has_vat ?? true,
        roomNumber: r ? (r.room?.room_number || r.room?.code || r.room_number || 'Chưa gán') : 'Chưa gán',
        roomType: r ? (r.room_class?.name || r.roomClass?.name || 'Standard') : 'Standard',
        guestName: guestName,
        arrivalDate: formatDateVN(arrDate),
        nights: (r && r.num_of_days) || b.num_of_days || calculateNights(arrDate, depDate),
        departureDate: formatDateVN(depDate),
        breakfast: b.breakfast_included ?? true,
        adults: (r && r.adults_count) || b.adults_count || 1,
        children: (r && r.children_count) || b.children_count || 0,
        rateCode: b.rate_code || '',
        price: (r && (r.price || r.room_rate)) || b.total_amount || 500000,
        extraBed: (r && r.extra_bed_count) || 0,
        extraBedPrice: (r && r.extra_bed_price) || 0,
        selected: false
      })
    }

    if (rooms.length > 0) {
      rooms.forEach((r, rIdx) => processItem(r, rIdx))
    } else {
      processItem(null, 0)
    }
  })

  groupData.value = Object.values(groupsMap)
}

watch(activeFilterTab, () => {
  if (rawBookings.value.length > 0) {
    processRealBookings(rawBookings.value)
  }
})

onMounted(() => {
  fetchRealData()
})

// Select all state
const isAllSelected = computed({
  get() {
    let all = true
    for (const group of groupData.value) {
      for (const item of group.items) {
        if (!item.selected) {
          all = false
          break
        }
      }
    }
    return all
  },
  set(val) {
    for (const group of groupData.value) {
      group.selected = val
      for (const item of group.items) {
        item.selected = val
      }
    }
  }
})

function toggleGroupSelect(group, val) {
  group.selected = val
  for (const item of group.items) {
    item.selected = val
  }
}

function updateGroupSelectState(group) {
  group.selected = group.items.every(i => i.selected)
}

function formatPrice(val) {
  if (val === null || val === undefined || isNaN(val)) return '0'
  return new Intl.NumberFormat('vi-VN').format(val)
}

function handleSubNavClick(item) {
  activeSubNav.value = item.id
  if (item.route) {
    router.push(item.route)
  }
}

// Trigger Day Close action
async function handleRollDay() {
  const confirmed = await uiStore.confirm({
    title: 'Xác nhận sang ngày',
    message: 'Bạn có chắc chắn muốn thực hiện chuyển ngày hệ thống?',
    confirmText: 'Đồng ý',
    cancelText: 'Hủy'
  })

  if (!confirmed) return

  try {
    isRolling.value = true
    uiStore.showToast('Đang thực hiện sang ngày hệ thống...', 'info')
    const res = await http.post('/night-audit/run', {
      occupied_to_dirty: occupiedToDirty.value,
      empty_to_inspect: emptyToInspect.value
    })
    if (res.data && res.data.success) {
      uiStore.showToast('Đã chuyển sang ngày tiếp theo thành công!', 'success')
      // [Bug F] Cảnh báo nếu có phòng khóa bị skip vì có khách
      const skipped = res.data.skipped_locks
      if (skipped && skipped.length > 0) {
        const roomList = skipped.map(s => s.room_number).join(', ')
        setTimeout(() => {
          uiStore.showToast(`Cảnh báo: ${skipped.length} phòng chưa được khóa tự động do có khách đang ở (${roomList}). Vui lòng kiểm tra lại.`, 'warning')
        }, 800)
      }
      setTimeout(() => {
        window.location.reload()
      }, skipped?.length > 0 ? 2500 : 800)
    } else {
      uiStore.showToast('Không thể chuyển ngày hệ thống.', 'error')
    }
  } catch (err) {
    console.error(err)
    uiStore.showToast(err.response?.data?.message || 'Có lỗi xảy ra khi chuyển ngày.', 'error')
  } finally {
    isRolling.value = false
  }
}

function handleRevenueReport() {
  uiStore.showToast('Đang tải báo cáo dự kiến doanh thu tiền phòng...', 'info')
}

async function handlePostRoomCharge() {
  const selected = getSelectedDepartureItems()
  if (selected.length === 0) {
    uiStore.showToast('Vui lòng tích chọn ít nhất 1 phòng để post tiền phòng.', 'warning')
    return
  }

  try {
    isLoading.value = true
    const sysDateStr = getNormalizedDate(systemDate.value)
    
    for (const item of selected) {
      const parts = item.id.split('-')
      const bookingRoomId = parts[parts.length - 1]
      await http.post('/booking-room-services/post-room-charge', {
        booking_room_id: bookingRoomId,
        date_from: sysDateStr,
        date_to: sysDateStr,
        mode: 'auto'
      })
    }
    uiStore.showToast('Đã post tiền phòng thành công cho các phòng đã chọn.', 'success')
    await fetchRealData()
  } catch (err) {
    console.error(err)
    uiStore.showToast(err.response?.data?.message || 'Có lỗi xảy ra khi post tiền phòng.', 'error')
  } finally {
    isLoading.value = false
  }
}

function getSelectedDepartureItems() {
  const selected = []
  groupData.value.forEach(g => {
    g.items.forEach(item => {
      if (item.selected) selected.push(item)
    })
  })
  return selected
}

function handleExtendStay() {
  const selected = getSelectedDepartureItems()
  if (selected.length === 0) {
    uiStore.showToast('Vui lòng tích chọn ít nhất 1 phòng đi để gia hạn.', 'warning')
    return
  }
  extendNightsInput.value = 1
  showExtendStayModal.value = true
}

async function confirmExtendStay() {
  const selected = getSelectedDepartureItems()
  if (selected.length === 0) {
    showExtendStayModal.value = false
    return
  }

  const nightsToAdd = Math.max(1, parseInt(extendNightsInput.value) || 1)

  try {
    isLoading.value = true
    showExtendStayModal.value = false

    for (const item of selected) {
      const parts = item.id.split('-')
      const bookingRoomId = parts[parts.length - 1]
      await http.post('/night-audit/extend-stay', {
        booking_room_id: bookingRoomId,
        nights: nightsToAdd
      })
    }
    uiStore.showToast(`Đã gia hạn thành công ${nightsToAdd} đêm cho ${selected.length} phòng!`, 'success')
    await fetchRealData()
  } catch (err) {
    console.error(err)
    uiStore.showToast(err.response?.data?.message || 'Có lỗi xảy ra khi gia hạn phòng.', 'error')
  } finally {
    isLoading.value = false
  }
}

function handleProcessDepartures() {
  departuresProcessed.value = true
  uiStore.showToast('Đã xử lý hoàn tất tất cả phòng đi!', 'success')
}

function handleUpdateArrivalDate() {
  const selected = getSelectedDepartureItems()
  if (selected.length === 0) {
    uiStore.showToast('Vui lòng tích chọn ít nhất 1 phòng đến để cập nhật.', 'warning')
    return
  }
  showArrivalModal.value = true
}

async function confirmArrivalUpdate() {
  const selected = getSelectedDepartureItems()
  if (selected.length === 0) {
    uiStore.showToast('Vui lòng tích chọn ít nhất 1 phòng đến để dời ngày.', 'warning')
    showArrivalModal.value = false
    return
  }

  try {
    isLoading.value = true
    showArrivalModal.value = false

    const mappedOption = arrivalFeeOption.value === 'has_charge' ? 'room_only' : arrivalFeeOption.value

    for (const item of selected) {
      const parts = item.id.split('-')
      const bookingRoomId = parts[parts.length - 1]
      await http.post('/night-audit/late-check-in', {
        booking_room_id: bookingRoomId,
        charge_option: mappedOption,
        reason: 'Late Check-in'
      })
    }
    uiStore.showToast(`Đã dời ngày đến thành công cho ${selected.length} phòng.`, 'success')
    await fetchRealData()
  } catch (err) {
    console.error(err)
    uiStore.showToast(err.response?.data?.message || 'Có lỗi xảy ra khi dời ngày đến.', 'error')
  } finally {
    isLoading.value = false
  }
}

// [Bug A] handleNoShow mở modal thay vì hardcode no_charge
function handleNoShow() {
  const selected = getSelectedDepartureItems()
  if (selected.length === 0) {
    uiStore.showToast('Vui lòng tích chọn ít nhất 1 phòng đến để noshow.', 'warning')
    return
  }
  noshowFeeOption.value = 'no_charge'
  showNoshowModal.value = true
}

async function confirmNoshow() {
  const selected = getSelectedDepartureItems()
  if (selected.length === 0) {
    showNoshowModal.value = false
    return
  }

  try {
    isLoading.value = true
    showNoshowModal.value = false

    let warningMsg = null
    for (const item of selected) {
      const parts = item.id.split('-')
      const bookingRoomId = parts[parts.length - 1]
      const res = await http.post('/night-audit/no-show', {
        booking_room_id: bookingRoomId,
        charge_option: noshowFeeOption.value,
        reason: 'Khách không đến (Noshow)'
      })
      // [Bug D] Hiển thị warning nếu booking còn phòng chưa xử lý
      if (res.data?.warning) {
        warningMsg = res.data.warning
      }
    }

    uiStore.showToast('Đã ghi nhận Noshow thành công cho các phòng đã chọn.', 'success')
    if (warningMsg) {
      setTimeout(() => uiStore.showToast(warningMsg, 'warning'), 600)
    }
    await fetchRealData()
  } catch (err) {
    console.error(err)
    uiStore.showToast(err.response?.data?.message || 'Có lỗi xảy ra khi thực hiện noshow.', 'error')
  } finally {
    isLoading.value = false
  }
}
</script>

<template>
  <div class="day-close-page relative flex flex-col min-h-screen bg-white text-slate-800 text-xs font-sans">
    <!-- Standard system LoadingOverlay -->
    <LoadingOverlay :show="isLoading" />

    <!-- 1. TOP FILTER BAR WITH COUNTERS -->
    <section class="bg-white border-b border-gray-200 px-4 py-1.5 flex items-center justify-between shadow-xs sticky top-0 z-20">
      <div class="flex items-center space-x-3">
        <div class="flex items-center space-x-1 bg-gray-100 p-0.5 rounded">
          <button
            v-for="tab in filterTabs"
            :key="tab.id"
            @click="activeFilterTab = tab.id"
            :class="[
              'px-3 py-1.5 rounded font-medium transition-colors cursor-pointer text-xs',
              activeFilterTab === tab.id
                ? 'bg-white text-blue-600 font-bold shadow-xs'
                : 'text-gray-700 hover:text-gray-900'
            ]"
          >
            {{ tab.name }}
          </button>
        </div>

        <!-- Action button Sang ngày -->
        <button
          @click="handleRollDay"
          :disabled="!canRollDay || isRolling"
          :title="!canRollDay ? 'Vui lòng xử lý hết phòng đi và cập nhật phòng đến trước khi sang ngày' : 'Thực hiện chuyển ngày hệ thống'"
          class="bg-[#4a85df] hover:bg-[#3972c7] active:bg-[#2b5fa8] text-white px-4 py-1.5 rounded font-semibold flex items-center gap-1.5 shadow-xs transition-all cursor-pointer text-xs disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-[#4a85df]"
        >
          <Calendar class="w-4 h-4" />
          <span>{{ isRolling ? 'Đang xử lý...' : 'Sang ngày' }}</span>
        </button>
      </div>

      <!-- Right counters & filter icon -->
      <div class="flex items-center space-x-3 shrink-0">
        <button
          v-if="activeFilterTab === 'in-house' || activeFilterTab === 'hourly'"
          class="p-1.5 rounded text-gray-600 hover:bg-gray-100 transition-colors"
          title="Cấu hình / Bộ lọc"
        >
          <SlidersHorizontal class="w-4 h-4" />
        </button>

        <template v-if="activeFilterTab === 'in-house'">
          <div
            @click="activeFilterTab = 'arrivals'"
            class="flex items-center justify-center w-12 h-12 rounded-full bg-[#7ca668] text-white font-medium flex-col text-[10px] leading-tight shadow-xs cursor-pointer hover:opacity-90 hover:scale-105 transition-all text-center p-1 select-none"
            title="Chuyển tab Phòng đến"
          >
            <span class="whitespace-nowrap text-[9px] scale-90">Phòng đến</span>
            <span class="font-bold text-xs leading-none mt-0.5">{{ arrivalCount }}</span>
          </div>

          <div
            @click="activeFilterTab = 'departures'"
            class="flex items-center justify-center w-12 h-12 rounded-full bg-[#8c594d] text-white font-medium flex-col text-[10px] leading-tight shadow-xs cursor-pointer hover:opacity-90 hover:scale-105 transition-all text-center p-1 select-none"
            title="Chuyển tab Phòng đi"
          >
            <span class="whitespace-nowrap text-[9px] scale-90">Phòng đi</span>
            <span class="font-bold text-xs leading-none mt-0.5">{{ departureCount }}</span>
          </div>
        </template>
      </div>
    </section>

    <!-- 2. DATA TABLE CONTENT -->
    <main class="flex-1 p-3 overflow-x-auto">
      <div class="bg-white border border-gray-300 rounded shadow-xs overflow-hidden min-w-[1200px]">
        <table class="w-full text-left border-collapse">
          <thead>
            <tr class="bg-[#e9ecef] text-gray-800 font-bold border-b border-gray-300 text-[11px]">
              <th class="py-2.5 px-3 w-12 text-center border-r border-gray-300">
                <input
                  type="checkbox"
                  v-model="isAllSelected"
                  class="w-5 h-5 rounded border-gray-400 text-blue-600 focus:ring-blue-500 cursor-pointer align-middle"
                />
              </th>
              <th class="py-2.5 px-3 border-r border-gray-300">Đăng ký</th>
              <th class="py-2.5 px-3 w-16 text-center border-r border-gray-300">VAT</th>
              <th class="py-2.5 px-3 border-r border-gray-300">Phòng</th>
              <th class="py-2.5 px-3 border-r border-gray-300">Loại Phòng</th>
              <th class="py-2.5 px-3 border-r border-gray-300">Khách</th>
              <th class="py-2.5 px-3 border-r border-gray-300 text-center">Phòng đến</th>
              <th class="py-2.5 px-3 border-r border-gray-300 text-center">Đêm</th>
              <th class="py-2.5 px-3 border-r border-gray-300 text-center">Phòng đi</th>
              <th class="py-2.5 px-3 w-20 text-center border-r border-gray-300">Ăn Sáng</th>
              <th class="py-2.5 px-3 border-r border-gray-300 text-center">Người lớn</th>
              <th class="py-2.5 px-3 border-r border-gray-300 text-center">Trẻ em</th>
              <th class="py-2.5 px-3 border-r border-gray-300">Mã giá phòng</th>
              <th class="py-2.5 px-3 border-r border-gray-300 text-right">Giá</th>
              <th class="py-2.5 px-3 border-r border-gray-300 text-center">Thêm giường</th>
              <th class="py-2.5 px-3 text-right">Giá thêm giường</th>
            </tr>
          </thead>
          <tbody>
            <!-- Empty State -->
            <tr v-if="!isLoading && groupData.length === 0">
              <td colspan="16" class="py-12 text-center text-gray-400 font-medium">
                Không tìm thấy dữ liệu phòng cho ngày hệ thống hiện tại.
              </td>
            </tr>

            <template v-else-if="!isLoading" v-for="group in groupData" :key="group.id">
              <!-- Group Header Row -->
              <tr class="bg-white border-b border-gray-300 text-gray-900 font-bold hover:bg-gray-50 transition-colors">
                <td class="py-2 px-3 text-center border-r border-gray-300 whitespace-nowrap">
                  <div class="flex items-center justify-center space-x-2">
                    <button
                      @click="group.expanded = !group.expanded"
                      class="w-4 h-4 bg-[#3b82f6] hover:bg-blue-700 text-white rounded-xs flex items-center justify-center font-bold text-xs focus:outline-none"
                      :title="group.expanded ? 'Thu gọn' : 'Mở rộng'"
                    >
                      <span>{{ group.expanded ? '-' : '+' }}</span>
                    </button>
                    <input
                      type="checkbox"
                      v-model="group.selected"
                      @change="toggleGroupSelect(group, group.selected)"
                      class="w-5 h-5 rounded border-gray-400 text-blue-600 focus:ring-blue-500 cursor-pointer align-middle"
                    />
                  </div>
                </td>
                <td colspan="15" class="py-2 px-3 font-bold text-gray-900">
                  {{ group.companyName }}
                </td>
              </tr>

              <!-- Items in Group -->
              <template v-if="group.expanded">
                <tr
                  v-for="item in group.items"
                  :key="item.id"
                  class="border-b border-gray-200 hover:bg-blue-50/40 transition-colors text-gray-800"
                >
                  <td class="py-2 px-3 text-center border-r border-gray-200">
                    <input
                      type="checkbox"
                      v-model="item.selected"
                      @change="updateGroupSelectState(group)"
                      class="w-5 h-5 rounded border-gray-400 text-blue-600 focus:ring-blue-500 cursor-pointer align-middle"
                    />
                  </td>
                  <td class="py-2 px-3 border-r border-gray-200 font-medium text-gray-900">{{ item.bookingCode }}</td>
                  <td class="py-2 px-3 border-r border-gray-200 text-center">
                    <label class="relative inline-flex items-center cursor-default select-none pointer-events-none">
                      <input type="checkbox" :checked="item.vat" disabled class="sr-only peer" />
                      <div class="w-9 h-5 bg-gray-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-[#3b82f6]"></div>
                    </label>
                  </td>
                  <td class="py-2 px-3 border-r border-gray-200 font-semibold text-gray-900">{{ item.roomNumber }}</td>
                  <td class="py-2 px-3 border-r border-gray-200 text-gray-800">{{ item.roomType }}</td>
                  <td class="py-2 px-3 border-r border-gray-200 font-medium text-gray-900">{{ item.guestName }}</td>
                  <td class="py-2 px-3 border-r border-gray-200 text-center whitespace-nowrap">{{ item.arrivalDate }}</td>
                  <td class="py-2 px-3 border-r border-gray-200 text-center font-medium">{{ item.nights }}</td>
                  <td class="py-2 px-3 border-r border-gray-200 text-center whitespace-nowrap">{{ item.departureDate }}</td>
                  <td class="py-2 px-3 border-r border-gray-200 text-center">
                    <label class="relative inline-flex items-center cursor-default select-none pointer-events-none">
                      <input type="checkbox" :checked="item.breakfast" disabled class="sr-only peer" />
                      <div class="w-9 h-5 bg-gray-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-[#3b82f6]"></div>
                    </label>
                  </td>
                  <td class="py-2 px-3 border-r border-gray-200 text-center">{{ item.adults }}</td>
                  <td class="py-2 px-3 border-r border-gray-200 text-center">{{ item.children }}</td>
                  <td class="py-2 px-3 border-r border-gray-200 text-gray-500">{{ item.rateCode || '-' }}</td>
                  <td class="py-2 px-3 border-r border-gray-200 text-right font-medium text-gray-900">{{ formatPrice(item.price) }}</td>
                  <td class="py-2 px-3 border-r border-gray-200 text-center">{{ item.extraBed }}</td>
                  <td class="py-2 px-3 text-right text-gray-900">{{ formatPrice(item.extraBedPrice) }}</td>
                </tr>
              </template>
            </template>
          </tbody>
        </table>
      </div>
    </main>

    <!-- 3. BOTTOM ACTION FOOTER BAR -->
    <footer class="bg-white border-t border-gray-300 px-4 py-2.5 flex items-center justify-between shadow-lg sticky bottom-0 z-20">
      <div v-if="activeFilterTab === 'in-house'" class="flex items-center space-x-6">
        <label class="flex items-center space-x-2.5 cursor-pointer font-semibold text-gray-800 hover:text-gray-900 select-none">
          <input
            type="checkbox"
            v-model="occupiedToDirty"
            class="w-5 h-5 rounded border-gray-400 text-blue-600 focus:ring-blue-500 cursor-pointer align-middle"
          />
          <span>Phòng đang ở -&gt; Phòng dơ</span>
        </label>

        <label class="flex items-center space-x-2.5 cursor-pointer font-semibold text-gray-800 hover:text-gray-900 select-none">
          <input
            type="checkbox"
            v-model="emptyToInspect"
            class="w-5 h-5 rounded border-gray-400 text-blue-600 focus:ring-blue-500 cursor-pointer align-middle"
          />
          <span>Phòng trống sẵn sàng -&gt; Phòng chờ kiểm tra</span>
        </label>
      </div>
      <div v-else class="flex-1"></div>

      <div class="flex items-center space-x-3">
        <!-- Tab Arrivals Footer Buttons -->
        <template v-if="activeFilterTab === 'arrivals'">
          <button
            @click="handleUpdateArrivalDate"
            class="bg-[#6184c7] hover:bg-[#4b6cb7] active:bg-[#395697] text-white px-3.5 py-1.5 rounded font-semibold flex items-center gap-1.5 shadow-xs transition-colors cursor-pointer text-xs"
          >
            <RefreshCw class="w-3.5 h-3.5" />
            <span>Cập nhật ngày đến (No Show One Day)</span>
          </button>

          <button
            @click="handleNoShow"
            class="bg-[#6184c7] hover:bg-[#4b6cb7] active:bg-[#395697] text-white px-3.5 py-1.5 rounded font-semibold flex items-center gap-1.5 shadow-xs transition-colors cursor-pointer text-xs"
          >
            <UserX class="w-3.5 h-3.5" />
            <span>Khách không đến (No Show)</span>
          </button>
        </template>

        <!-- Tab Departures Footer Button -->
        <template v-else-if="activeFilterTab === 'departures'">
          <button
            @click="handleExtendStay"
            class="bg-[#6184c7] hover:bg-[#4b6cb7] active:bg-[#395697] text-white px-3.5 py-1.5 rounded font-semibold flex items-center gap-1.5 shadow-xs transition-colors cursor-pointer text-xs"
          >
            <CalendarPlus class="w-3.5 h-3.5" />
            <span>Gia hạn đêm phòng</span>
          </button>
        </template>

        <!-- Tab In-House / Hourly / Default Footer Buttons -->
        <template v-else>
          <button
            @click="handleRevenueReport"
            class="bg-[#3b82f6] hover:bg-[#2563eb] active:bg-[#1d4ed8] text-white px-4 py-1.5 rounded font-semibold flex items-center gap-1.5 shadow-xs transition-colors cursor-pointer text-xs"
          >
            <FileText class="w-4 h-4" />
            <span>Báo cáo dự kiến doanh thu tiền phòng</span>
          </button>

          <button
            @click="handlePostRoomCharge"
            class="bg-[#4a85df] hover:bg-[#3972c7] active:bg-[#2b5fa8] text-white px-4 py-1.5 rounded font-semibold flex items-center gap-1.5 shadow-xs transition-colors cursor-pointer text-xs"
          >
            <DollarSign class="w-4 h-4" />
            <span>Tiền phòng</span>
          </button>
        </template>
      </div>
    </footer>

    <!-- MODAL NOSHOW - TUY CHON TINH PHI [Bug A] -->
    <div v-if="showNoshowModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-lg shadow-xl w-full max-w-md overflow-hidden">
        <div class="bg-red-600 text-white px-4 py-3 font-semibold flex justify-between items-center text-sm">
          <div class="flex items-center gap-2">
            <UserX class="w-4 h-4" />
            <span>Noshow - Tuỳ chọn tính phí</span>
          </div>
          <button @click="showNoshowModal = false" class="hover:opacity-80 text-white font-bold text-base">&times;</button>
        </div>
        <div class="p-5 space-y-4 text-xs">
          <p class="text-gray-700 font-medium">Vui lòng chọn hình thức tính phí khi ghi nhận Noshow:</p>
          <div class="space-y-2.5">
            <label class="flex items-start gap-3 p-3 border border-gray-200 rounded-md hover:bg-gray-50 cursor-pointer transition-colors">
              <input type="radio" v-model="noshowFeeOption" value="all_charged" class="mt-0.5 text-red-600 focus:ring-red-500" />
              <div>
                <div class="font-bold text-gray-800">Tất cả tính phí</div>
                <div class="text-gray-500 text-[11px]">Tính tiền phòng + dịch vụ bổ sung của đêm đầu tiên (ngày đến).</div>
              </div>
            </label>

            <label class="flex items-start gap-3 p-3 border border-gray-200 rounded-md hover:bg-gray-50 cursor-pointer transition-colors">
              <input type="radio" v-model="noshowFeeOption" value="room_only" class="mt-0.5 text-red-600 focus:ring-red-500" />
              <div>
                <div class="font-bold text-gray-800">Chỉ tính tiền phòng</div>
                <div class="text-gray-500 text-[11px]">Tính 1 đêm tiền phòng đầu tiên (ngày đến), không tính các dịch vụ kèm theo.</div>
              </div>
            </label>

            <label class="flex items-start gap-3 p-3 border border-gray-200 rounded-md hover:bg-gray-50 cursor-pointer transition-colors">
              <input type="radio" v-model="noshowFeeOption" value="no_charge" class="mt-0.5 text-red-600 focus:ring-red-500" />
              <div>
                <div class="font-bold text-gray-800">Không tính phí</div>
                <div class="text-gray-500 text-[11px]">Miễn phí toàn bộ, không ghi nhận bất kỳ khoản tiền nào.</div>
              </div>
            </label>
          </div>
        </div>
        <div class="bg-gray-50 px-4 py-3 flex justify-end space-x-2 border-t border-gray-200">
          <button
            @click="showNoshowModal = false"
            class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded font-medium text-xs transition-colors"
          >
            Hủy
          </button>
          <button
            @click="confirmNoshow"
            class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded font-semibold text-xs transition-colors"
          >
            Xác nhận Noshow
          </button>
        </div>
      </div>
    </div>

    <!-- MODAL GIA HAN DEM PHONG -->
    <div v-if="showExtendStayModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-lg shadow-xl w-full max-w-md overflow-hidden">
        <div class="bg-blue-600 text-white px-4 py-3 font-semibold flex justify-between items-center text-sm">
          <div class="flex items-center gap-2">
            <CalendarPlus class="w-4 h-4" />
            <span>Gia hạn đêm phòng</span>
          </div>
          <button @click="showExtendStayModal = false" class="hover:opacity-80 text-white font-bold text-base">&times;</button>
        </div>
        <div class="p-5 space-y-4 text-xs">
          <p class="text-gray-600 font-medium">
            Số phòng được chọn: <span class="font-bold text-blue-600">{{ getSelectedDepartureItems().length }}</span> phòng
          </p>
          <div>
            <label class="block font-semibold text-gray-700 mb-1.5">Số ngày muốn gia hạn:</label>
            <input
              type="number"
              v-model.number="extendNightsInput"
              min="1"
              max="30"
              class="w-full border border-gray-300 rounded px-3 py-2 text-xs focus:outline-none focus:border-blue-500 font-medium"
              placeholder="Nhập số ngày (ví dụ: 1)"
            />
          </div>
        </div>
        <div class="bg-gray-50 px-4 py-3 flex justify-end space-x-2 border-t border-gray-200">
          <button
            @click="showExtendStayModal = false"
            class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded font-medium text-xs transition-colors"
          >
            Hủy
          </button>
          <button
            @click="confirmExtendStay"
            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded font-semibold text-xs transition-colors"
          >
            Xác nhận gia hạn
          </button>
        </div>
      </div>
    </div>

    <!-- MODAL CAP NHAT PHONG DEN (OPTIONS TINH PHI) -->
    <div v-if="showArrivalModal" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
      <div class="bg-white rounded-lg shadow-xl w-full max-w-md overflow-hidden">
        <div class="bg-blue-600 text-white px-4 py-3 font-semibold flex justify-between items-center text-sm">
          <div class="flex items-center gap-2">
            <RefreshCw class="w-4 h-4" />
            <span>Cập nhật phòng đến - Tùy chọn tính phí</span>
          </div>
          <button @click="showArrivalModal = false" class="hover:opacity-80 text-white font-bold text-base">&times;</button>
        </div>
        <div class="p-5 space-y-4 text-xs">
          <p class="text-gray-700 font-medium">Vui lòng chọn hình thức tính phí khi cập nhật phòng đến:</p>
          <div class="space-y-2.5">
            <label class="flex items-start gap-3 p-3 border border-gray-200 rounded-md hover:bg-gray-50 cursor-pointer transition-colors">
              <input type="radio" v-model="arrivalFeeOption" value="all_charged" class="mt-0.5 text-blue-600 focus:ring-blue-500" />
              <div>
                <div class="font-bold text-gray-800">Tất cả tính phí</div>
                <div class="text-gray-500 text-[11px]">Tính đầy đủ phí phòng cho tất cả các lượt phòng đến trong ngày.</div>
              </div>
            </label>

            <label class="flex items-start gap-3 p-3 border border-gray-200 rounded-md hover:bg-gray-50 cursor-pointer transition-colors">
              <input type="radio" v-model="arrivalFeeOption" value="no_charge" class="mt-0.5 text-blue-600 focus:ring-blue-500" />
              <div>
                <div class="font-bold text-gray-800">Không tính phí</div>
                <div class="text-gray-500 text-[11px]">Miễn phí tiền phòng (0 VNĐ) cho tất cả lượt phòng đến.</div>
              </div>
            </label>

            <label class="flex items-start gap-3 p-3 border border-gray-200 rounded-md hover:bg-gray-50 cursor-pointer transition-colors">
              <input type="radio" v-model="arrivalFeeOption" value="has_charge" class="mt-0.5 text-blue-600 focus:ring-blue-500" />
              <div>
                <div class="font-bold text-gray-800">Có tính phí</div>
                <div class="text-gray-500 text-[11px]">Giữ nguyên phí chuẩn và áp dụng phí riêng theo từng phòng.</div>
              </div>
            </label>
          </div>
        </div>
        <div class="bg-gray-50 px-4 py-3 flex justify-end space-x-2 border-t border-gray-200">
          <button
            @click="showArrivalModal = false"
            class="px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded font-medium text-xs transition-colors"
          >
            Hủy
          </button>
          <button
            @click="confirmArrivalUpdate"
            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded font-semibold text-xs transition-colors"
          >
            Xác nhận cập nhật
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* Custom scrollbar hiding utility */
.scrollbar-none::-webkit-scrollbar {
  display: none;
}
.scrollbar-none {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>
