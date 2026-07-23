<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { fetchBookings, checkInRoom, undoCheckInRoom, cancelBookingRoom, fetchSystemTime } from '@/services/booking-service'
import { useUiStore } from '@/stores/ui-store'
import { useRoomStore } from '@/stores/room-store'
import { t } from '@/utils/i18n'
import RoomIcon from '@/components/RoomIcon.vue'
import LoadingOverlay from '@/components/LoadingOverlay.vue'

const uiStore = useUiStore()
const roomStore = useRoomStore()

const props = defineProps({
  initialDate: {
    type: String,
    default: ''
  }
})

// State
const bookings = ref([])
const loading = ref(false)
const searchQuery = ref('')
const searchDate = ref(props.initialDate || '2026-07-09')
const dateInputRef = ref(null)

watch(() => props.initialDate, (newVal) => {
  if (newVal) {
    searchDate.value = newVal
  }
})

const selectedRooms = ref([]) // Holds ids of selected rooms
const collapsedBookings = ref({}) // Booking ID -> collapsed status

// Fetch system date on mount
const fetchSysDate = async () => {
  try {
    const res = await fetchSystemTime()
    if (res.data && res.data.time) {
      searchDate.value = res.data.time.split('T')[0]
    }
  } catch (err) {
    console.error('fetchSystemTime error:', err)
  }
}


const isBeforeOrSameDay = (dateStr1, dateStr2) => {
  if (!dateStr1 || !dateStr2) return false
  const d1 = new Date(dateStr1)
  const d2 = new Date(dateStr2)
  d1.setHours(0,0,0,0)
  d2.setHours(0,0,0,0)
  return d1 <= d2
}

const getPastDateString = (dateStr, daysAgo) => {
  const d = new Date(dateStr)
  d.setDate(d.getDate() - daysAgo)
  return d.toISOString().split('T')[0]
}

// Fetch bookings from database
const loadBookings = async () => {
  loading.value = true
  selectedRooms.value = [] // Reset selection on reload
  try {
    const res = await fetchBookings({
      from_date: getPastDateString(searchDate.value, 30),
      to_date: searchDate.value,
      status: '0,1'
    })
    if (res.data && res.data.success !== false) {
      bookings.value = res.data.data || []
    } else {
      bookings.value = []
    }
  } catch (err) {
    console.error('loadBookings error:', err)
    uiStore.showToast('Không thể tải danh sách đặt phòng.', 'error')
    bookings.value = []
  } finally {
    loading.value = false
  }
}

// Format date display in local timezone
function formatDateDisplay(dateInput) {
  if (!dateInput) return ''
  if (typeof dateInput === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(dateInput)) {
    const parts = dateInput.split('-')
    return `${parts[2]}/${parts[1]}/${parts[0]}`
  }
  const d = new Date(dateInput)
  if (isNaN(d.getTime())) return dateInput
  const day = String(d.getDate()).padStart(2, '0')
  const month = String(d.getMonth() + 1).padStart(2, '0')
  const year = d.getFullYear()
  return `${day}/${month}/${year}`
}

function triggerDatePicker() {
  if (dateInputRef.value) {
    if (typeof dateInputRef.value.showPicker === 'function') {
      dateInputRef.value.showPicker()
    } else {
      dateInputRef.value.click()
    }
  }
}

// Search and filtering
const filteredBookings = computed(() => {
  if (!searchQuery.value) return bookings.value
  const q = searchQuery.value.toLowerCase().trim()
  return bookings.value.filter(booking => {
    const matchesCode = (booking.booking_code || '').toLowerCase().includes(q)
    const matchesName = (booking.booking_name || '').toLowerCase().includes(q)
    const matchesContact = (booking.contact_name || '').toLowerCase().includes(q)
    const matchesCompany = (booking.company?.name || '').toLowerCase().includes(q)
    const matchesRoom = booking.booking_rooms?.some(room => 
      (room.room_number || '').toLowerCase().includes(q) ||
      room.guests?.some(g => {
        const guestObj = g.guest || g
        const name = guestObj.full_name || `${guestObj.first_name || ''} ${guestObj.last_name || ''}`
        return name.toLowerCase().includes(q)
      })
    )
    return matchesCode || matchesName || matchesContact || matchesCompany || matchesRoom
  })
})

// Section 1: PHÒNG CHƯA ĐẾN (booking_room status === 0)
const chuaDenBookings = computed(() => {
  return filteredBookings.value.map(booking => {
    const pendingRooms = (booking.booking_rooms || []).filter(room => 
      room.status === 0 && 
      isBeforeOrSameDay(room.arrival_date, searchDate.value)
    )
    if (pendingRooms.length === 0) return null
    return {
      ...booking,
      booking_rooms: pendingRooms
    }
  }).filter(b => b !== null)
})

const chuaDenRoomsCount = computed(() => {
  return chuaDenBookings.value.reduce((sum, b) => sum + b.booking_rooms.length, 0)
})

// Section 2: PHÒNG ĐÃ ĐẾN (booking_room status === 1)
const daDenBookings = computed(() => {
  return filteredBookings.value.map(booking => {
    const checkedInRooms = (booking.booking_rooms || []).filter(room => 
      room.status === 1
    )
    if (checkedInRooms.length === 0) return null
    return {
      ...booking,
      booking_rooms: checkedInRooms
    }
  }).filter(b => b !== null)
})

const daDenRoomsCount = computed(() => {
  return daDenBookings.value.reduce((sum, b) => sum + b.booking_rooms.length, 0)
})

// Collapsible logic
function toggleCollapse(bookingId) {
  collapsedBookings.value[bookingId] = !collapsedBookings.value[bookingId]
}

// Checkbox selection helper functions
function isRoomSelected(roomId) {
  return selectedRooms.value.includes(roomId)
}

function toggleSelectRoom(roomId) {
  if (isRoomSelected(roomId)) {
    selectedRooms.value = selectedRooms.value.filter(id => id !== roomId)
  } else {
    selectedRooms.value.push(roomId)
  }
}

function isBookingAllSelected(booking, sectionList) {
  const list = sectionList.find(b => b.id === booking.id)
  if (!list || !list.booking_rooms.length) return false
  return list.booking_rooms.every(r => selectedRooms.value.includes(r.id))
}

function isBookingPartiallySelected(booking, sectionList) {
  const list = sectionList.find(b => b.id === booking.id)
  if (!list || !list.booking_rooms.length) return false
  const selectedCount = list.booking_rooms.filter(r => selectedRooms.value.includes(r.id)).length
  return selectedCount > 0 && selectedCount < list.booking_rooms.length
}

function toggleSelectBooking(booking, sectionList) {
  const list = sectionList.find(b => b.id === booking.id)
  if (!list) return

  const allSelected = isBookingAllSelected(booking, sectionList)
  if (allSelected) {
    // Deselect all rooms of this booking in this section
    const roomIds = list.booking_rooms.map(r => r.id)
    selectedRooms.value = selectedRooms.value.filter(id => !roomIds.includes(id))
  } else {
    // Select all rooms of this booking in this section
    list.booking_rooms.forEach(r => {
      if (!selectedRooms.value.includes(r.id)) {
        selectedRooms.value.push(r.id)
      }
    })
  }
}

function isSectionAllSelected(sectionList) {
  if (!sectionList.length) return false
  return sectionList.every(b => b.booking_rooms.every(r => selectedRooms.value.includes(r.id)))
}

function toggleSelectSection(sectionList) {
  const allSelected = isSectionAllSelected(sectionList)
  sectionList.forEach(b => {
    b.booking_rooms.forEach(r => {
      if (allSelected) {
        selectedRooms.value = selectedRooms.value.filter(id => id !== r.id)
      } else {
        if (!selectedRooms.value.includes(r.id)) {
          selectedRooms.value.push(r.id)
        }
      }
    })
  })
}

// Active room checkin-specific selections count
const pendingSelectedCount = computed(() => {
  let count = 0
  chuaDenBookings.value.forEach(b => {
    b.booking_rooms.forEach(r => {
      if (selectedRooms.value.includes(r.id)) count++
    })
  })
  return count
})

const checkedInSelectedCount = computed(() => {
  let count = 0
  daDenBookings.value.forEach(b => {
    b.booking_rooms.forEach(r => {
      if (selectedRooms.value.includes(r.id)) count++
    })
  })
  return count
})

// Check-in action (Nhận phòng)
const handleCheckIn = async () => {
  if (pendingSelectedCount.value === 0) return

  const selectedRoomsToProcess = []
  chuaDenBookings.value.forEach(b => {
    b.booking_rooms.forEach(r => {
      if (selectedRooms.value.includes(r.id)) {
        selectedRoomsToProcess.push({ bookingId: b.id, roomId: r.id, roomNumber: r.room_number })
      }
    })
  })

  const unassigned = selectedRoomsToProcess.filter(item => !item.roomNumber)
  if (unassigned.length > 0) {
    uiStore.showToast('Có phòng chưa được gán số phòng. Vui lòng gán số phòng trước khi giao phòng!', 'warning')
    return
  }

  const confirmed = await uiStore.confirm({
    title: 'Xác nhận nhận phòng',
    message: `Bạn có chắc chắn muốn nhận phòng cho ${selectedRoomsToProcess.length} phòng đã chọn không?`,
    confirmText: 'Nhận phòng',
    cancelText: 'Hủy'
  })
  if (!confirmed) return

  loading.value = true
  let successCount = 0
  let errorMessages = []

  for (const item of selectedRoomsToProcess) {
    try {
      const res = await checkInRoom(item.bookingId, item.roomId)
      if (res.data && res.data.success !== false) {
        successCount++
      } else {
        errorMessages.push(`Phòng ${item.roomNumber || 'chưa gán'}: ${res.data?.message || 'Lỗi không xác định'}`)
      }
    } catch (err) {
      console.error(err)
      const msg = err.response?.data?.message || 'Lỗi kết nối máy chủ'
      errorMessages.push(`Phòng ${item.roomNumber || 'chưa gán'}: ${msg}`)
    }
  }

  // Reload room status & bookings
  await roomStore.fetchRooms()
  await loadBookings()

  if (successCount > 0) {
    uiStore.showToast(`Đã nhận phòng thành công cho ${successCount} phòng!`, 'success')
  }
  if (errorMessages.length > 0) {
    errorMessages.forEach(msg => {
      uiStore.showToast(msg, 'error')
    })
  }
}

// Cancel Check-in action (Hủy nhận phòng)
const handleUndoCheckIn = async () => {
  if (checkedInSelectedCount.value === 0) return

  const selectedRoomsToProcess = []
  daDenBookings.value.forEach(b => {
    b.booking_rooms.forEach(r => {
      if (selectedRooms.value.includes(r.id)) {
        selectedRoomsToProcess.push({ bookingId: b.id, roomId: r.id, roomNumber: r.room_number })
      }
    })
  })

  const confirmed = await uiStore.confirm({
    title: 'Xác nhận hủy nhận phòng',
    message: `Bạn có chắc chắn muốn hủy nhận phòng cho ${selectedRoomsToProcess.length} phòng đã chọn không?`,
    confirmText: 'Hủy nhận phòng',
    cancelText: 'Hủy'
  })
  if (!confirmed) return

  loading.value = true
  let successCount = 0
  let errorMessages = []

  for (const item of selectedRoomsToProcess) {
    try {
      const res = await undoCheckInRoom(item.bookingId, item.roomId)
      if (res.data && res.data.success !== false) {
        successCount++
      } else {
        errorMessages.push(`Phòng ${item.roomNumber}: ${res.data?.message || 'Lỗi không xác định'}`)
      }
    } catch (err) {
      console.error(err)
      const msg = err.response?.data?.message || 'Lỗi kết nối máy chủ'
      errorMessages.push(`Phòng ${item.roomNumber}: ${msg}`)
    }
  }

  // Reload room status & bookings
  await roomStore.fetchRooms()
  await loadBookings()

  if (successCount > 0) {
    uiStore.showToast(`Đã hủy nhận phòng thành công cho ${successCount} phòng!`, 'success')
  }
  if (errorMessages.length > 0) {
    errorMessages.forEach(msg => {
      uiStore.showToast(msg, 'error')
    })
  }
}

// Cancel Selected Rooms action (Hủy đặt phòng hàng loạt)
const handleCancelSelected = async () => {
  if (pendingSelectedCount.value === 0) return

  const selectedRoomsToProcess = []
  chuaDenBookings.value.forEach(b => {
    b.booking_rooms.forEach(r => {
      if (selectedRooms.value.includes(r.id)) {
        selectedRoomsToProcess.push({ bookingId: b.id, roomId: r.id, roomNumber: r.room_number })
      }
    })
  })

  const confirmed = await uiStore.confirm({
    title: 'Xác nhận hủy đặt phòng',
    message: `Bạn có chắc chắn muốn hủy đặt phòng cho ${selectedRoomsToProcess.length} phòng đã chọn?`,
    confirmText: 'Hủy đặt phòng',
    cancelText: 'Hủy'
  })
  if (!confirmed) return

  loading.value = true
  let successCount = 0
  let errorMessages = []

  for (const item of selectedRoomsToProcess) {
    try {
      const res = await cancelBookingRoom(item.bookingId, item.roomId)
      if (res.data && res.data.success !== false) {
        successCount++
      } else {
        errorMessages.push(`Phòng ${item.roomNumber || 'chưa gán'}: ${res.data?.message || 'Lỗi khi hủy phòng'}`)
      }
    } catch (err) {
      console.error(err)
      const msg = err.response?.data?.message || 'Lỗi kết nối máy chủ'
      errorMessages.push(`Phòng ${item.roomNumber || 'chưa gán'}: ${msg}`)
    }
  }

  await roomStore.fetchRooms()
  await loadBookings()

  if (successCount > 0) {
    uiStore.showToast(`Đã hủy đặt phòng thành công cho ${successCount} phòng!`, 'success')
  }
  if (errorMessages.length > 0) {
    errorMessages.forEach(msg => uiStore.showToast(msg, 'error'))
  }
}

// Get guest names for display
function getRoomGuestName(room, booking) {
  if (room.guests && room.guests.length > 0) {
    const validNames = room.guests
      .map(g => {
        const guestObj = g.guest || g
        if (guestObj.full_name && guestObj.full_name.trim()) return guestObj.full_name.trim()
        const fn = `${guestObj.first_name || ''} ${guestObj.last_name || ''}`.trim()
        return fn !== '' ? fn : null
      })
      .filter(Boolean)
    if (validNames.length > 0) return validNames.join(', ')
  }
  if (room.guest_name && room.guest_name.trim()) return room.guest_name.trim()
  if (room.primary_guest_name && room.primary_guest_name.trim()) return room.primary_guest_name.trim()
  return '-'
}

// Lifecycle hooks
onMounted(async () => {
  await fetchSysDate()
  await loadBookings()
})

watch(searchDate, async () => {
  await loadBookings()
})
</script>

<template>
  <div class="bg-[#f8fafc] flex flex-col h-full relative select-none">
    <LoadingOverlay :show="loading" />

    <!-- Top Action & Search Bar -->
    <div class="bg-white border-b border-slate-200 px-6 py-3 flex items-center justify-between gap-4 shrink-0 shadow-xs">
      <!-- Left: Date Picker -->
      <div class="flex items-center gap-3">
        <div class="flex items-center border border-slate-200 rounded-lg p-0.5 bg-white shadow-xs hover:border-slate-300 transition-colors">
          <span 
            @click="triggerDatePicker"
            class="text-xs font-extrabold text-slate-700 px-3 py-1.5 cursor-pointer flex items-center gap-2"
          >
            <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <rect x="3" y="4" width="18" height="18" rx="2" ry="2" />
              <line x1="16" y1="2" x2="16" y2="6" />
              <line x1="8" y1="2" x2="8" y2="6" />
              <line x1="3" y1="10" x2="21" y2="10" />
            </svg>
            {{ formatDateDisplay(searchDate) }}
          </span>
          <input
            ref="dateInputRef"
            type="date"
            v-model="searchDate"
            class="w-0 h-0 opacity-0 p-0 border-none absolute -z-10"
          />
        </div>
        <span class="text-xs text-slate-400 font-semibold">Ngày hệ thống hiện tại</span>
      </div>

      <!-- Right: Search Bar -->
      <div class="relative w-80">
        <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
          <svg class="h-4.5 w-4.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
        </span>
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Tìm tên khách, mã ĐK, phòng..."
          class="block w-full pl-9 pr-8 py-2 border border-slate-200 rounded-lg bg-white text-xs font-medium placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500 transition-all"
        />
        <button
          v-if="searchQuery"
          @click="searchQuery = ''"
          class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer"
        >
          <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
    </div>

    <!-- Main Lists Containers -->
    <div class="flex-1 p-6 overflow-y-auto flex flex-col gap-6">
      
      <!-- SECTION 1: PHÒNG CHƯA ĐẾN -->
      <div class="flex flex-col gap-3">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <h2 class="text-sm font-black text-slate-900 tracking-wide uppercase">Phòng chưa đến</h2>
            <span class="bg-amber-100 text-amber-800 rounded px-2 py-0.5 text-[11px] font-black leading-none shadow-2xs">
              {{ chuaDenRoomsCount }} PHÒNG
            </span>
          </div>
          <div class="flex items-center gap-2">
            <button
              @click="handleCheckIn"
              :disabled="pendingSelectedCount === 0"
              class="flex items-center gap-1.5 px-4 py-2 rounded-lg text-xs font-bold transition-all shadow-sm border-none"
              :class="pendingSelectedCount > 0 
                ? 'bg-[#006bdb] hover:bg-[#005bb8] text-white cursor-pointer active:scale-97' 
                : 'bg-slate-200 text-slate-400 cursor-not-allowed'"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              Nhận phòng
            </button>
            <button
              @click="handleCancelSelected"
              :disabled="pendingSelectedCount === 0"
              class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-bold transition-all shadow-sm border border-red-200 bg-white"
              :class="pendingSelectedCount > 0 
                ? 'text-red-600 hover:bg-red-50 hover:border-red-300 cursor-pointer active:scale-97' 
                : 'text-slate-300 border-slate-200 cursor-not-allowed'"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
              Hủy đặt phòng
            </button>
          </div>
        </div>

        <!-- Table 1 -->
        <div class="bg-white border border-slate-200 rounded-xl shadow-xs overflow-hidden">
          <table class="w-full text-left border-collapse text-xs table-fixed">
            <thead>
              <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold select-none h-9">
                <th class="p-2.5 text-center w-10">
                  <input
                    type="checkbox"
                    :checked="isSectionAllSelected(chuaDenBookings)"
                    @change="toggleSelectSection(chuaDenBookings)"
                    class="rounded border-slate-300 text-sky-600 focus:ring-sky-500 w-3.5 h-3.5"
                  />
                </th>
                <th class="p-2.5 w-[130px]">Mã DK</th>
                <th class="p-2.5 w-[140px]">Mã Tham chiếu</th>
                <th class="p-2.5 w-[250px]">Tên BK / Khách</th>
                <th class="p-2.5 w-[180px]">Công ty</th>
                <th class="p-2.5 text-center w-[120px]">Trạng thái</th>
                <th class="p-2.5 text-center w-[100px]">Ngày đến</th>
                <th class="p-2.5 text-center w-[100px]">Ngày đi</th>
                <th class="p-2.5 text-center w-[80px]">Phòng</th>
                <th class="p-2.5 pl-4">Ghi chú</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
              <template v-if="chuaDenBookings.length === 0">
                <tr>
                  <td colspan="10" class="p-8 text-center text-slate-400 font-medium bg-slate-50/30">
                    Không có phòng nào chưa đến trong ngày hôm nay.
                  </td>
                </tr>
              </template>
              
              <template v-else v-for="booking in chuaDenBookings" :key="booking.id">
                <!-- Parent Row -->
                <tr class="hover:bg-slate-50/50 transition-colors h-10 font-semibold bg-slate-50/20">
                  <td class="p-2.5 text-center">
                    <input
                      type="checkbox"
                      :checked="isBookingAllSelected(booking, chuaDenBookings)"
                      :indeterminate="isBookingPartiallySelected(booking, chuaDenBookings)"
                      @change="toggleSelectBooking(booking, chuaDenBookings)"
                      class="rounded border-slate-300 text-sky-600 focus:ring-sky-500 w-3.5 h-3.5"
                    />
                  </td>
                  <td class="p-2.5 font-bold text-slate-900 flex items-center gap-1.5 h-10">
                    <button
                      @click="toggleCollapse(booking.id)"
                      class="w-4 h-4 flex items-center justify-center text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer p-0"
                    >
                      <span class="text-[10px] transform transition-transform" :class="collapsedBookings[booking.id] ? '-rotate-90' : ''">▼</span>
                    </button>
                    <span>{{ booking.booking_code }}</span>
                  </td>
                  <td class="p-2.5 text-slate-500">{{ booking.external_booking_code || '-' }}</td>
                  <td class="p-2.5 text-slate-800 font-bold uppercase truncate">{{ booking.booking_name }}</td>
                  <td class="p-2.5 text-slate-600 truncate">{{ booking.company?.name || 'KHÁCH LẺ' }}</td>
                  <td class="p-2.5 text-center">
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold border bg-emerald-50 text-emerald-600 border-emerald-100">
                      {{ booking.registration_status?.name || 'Guaranteed' }}
                    </span>
                  </td>
                  <td class="p-2.5 text-center text-slate-600">{{ formatDateDisplay(booking.arrival_date) }}</td>
                  <td class="p-2.5 text-center text-slate-600">{{ formatDateDisplay(booking.departure_date) }}</td>
                  <td class="p-2.5 text-center font-bold text-slate-700">{{ booking.booking_rooms.length }}</td>
                  <td class="p-2.5 pl-4 text-slate-500 italic truncate max-w-[200px]" :title="booking.note">{{ booking.note || '-' }}</td>
                </tr>

                <!-- Child Rows -->
                <tr 
                  v-if="!collapsedBookings[booking.id]"
                  v-for="room in booking.booking_rooms" 
                  :key="room.id"
                  class="border-b border-slate-100 hover:bg-slate-50/30 transition-colors h-9"
                >
                  <td class="p-2.5 text-center bg-slate-50/5">
                    <input
                      type="checkbox"
                      :checked="isRoomSelected(room.id)"
                      @change="toggleSelectRoom(room.id)"
                      class="rounded border-slate-300 text-sky-600 focus:ring-sky-500 w-3.5 h-3.5"
                    />
                  </td>
                  <td class="p-2.5 pl-6 font-bold text-sky-600">
                    {{ room.room_number || '--' }}
                  </td>
                  <td class="p-2.5"></td>
                  <td class="p-2.5 text-slate-600 truncate pl-6 flex items-center gap-1.5 h-9">
                    <span class="w-1.5 h-1.5 rounded-full bg-slate-300 shrink-0"></span>
                    <span>{{ getRoomGuestName(room, booking) }}</span>
                  </td>
                  <td class="p-2.5"></td>
                  <td class="p-2.5 text-center"></td>
                  <td class="p-2.5 text-center text-slate-500">{{ formatDateDisplay(room.arrival_date) }}</td>
                  <td class="p-2.5 text-center text-slate-500">{{ formatDateDisplay(room.departure_date) }}</td>
                  <td class="p-2.5 text-center"></td>
                  <td class="p-2.5 pl-4 text-slate-400 text-[11px] truncate">{{ room.note || '-' }}</td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>

      <!-- SECTION 2: PHÒNG ĐÃ ĐẾN -->
      <div class="flex flex-col gap-3">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <h2 class="text-sm font-black text-slate-900 tracking-wide uppercase">Phòng đã đến</h2>
            <span class="bg-emerald-100 text-emerald-800 rounded px-2 py-0.5 text-[11px] font-black leading-none shadow-2xs">
              {{ daDenRoomsCount }} PHÒNG
            </span>
          </div>
          <button
            @click="handleUndoCheckIn"
            :disabled="checkedInSelectedCount === 0"
            class="flex items-center gap-1.5 px-4 py-2 rounded-lg text-xs font-bold transition-all shadow-sm border border-red-200 bg-white"
            :class="checkedInSelectedCount > 0 
              ? 'text-red-600 hover:bg-red-50 hover:border-red-300 cursor-pointer active:scale-97' 
              : 'text-slate-300 border-slate-200 cursor-not-allowed'"
          >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
            Hủy nhận phòng
          </button>
        </div>

        <!-- Table 2 -->
        <div class="bg-white border border-slate-200 rounded-xl shadow-xs overflow-hidden">
          <table class="w-full text-left border-collapse text-xs table-fixed">
            <thead>
              <tr class="bg-slate-50 border-b border-slate-200 text-slate-600 font-bold select-none h-9">
                <th class="p-2.5 text-center w-10">
                  <input
                    type="checkbox"
                    :checked="isSectionAllSelected(daDenBookings)"
                    @change="toggleSelectSection(daDenBookings)"
                    class="rounded border-slate-300 text-sky-600 focus:ring-sky-500 w-3.5 h-3.5"
                  />
                </th>
                <th class="p-2.5 w-[130px]">Mã DK</th>
                <th class="p-2.5 w-[140px]">Mã Tham chiếu</th>
                <th class="p-2.5 w-[250px]">Tên BK / Khách</th>
                <th class="p-2.5 w-[180px]">Công ty</th>
                <th class="p-2.5 text-center w-[120px]">Trạng thái</th>
                <th class="p-2.5 text-center w-[100px]">Ngày đến</th>
                <th class="p-2.5 text-center w-[100px]">Ngày đi</th>
                <th class="p-2.5 text-center w-[80px]">Phòng</th>
                <th class="p-2.5 pl-4">Ghi chú</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200">
              <template v-if="daDenBookings.length === 0">
                <tr>
                  <td colspan="10" class="p-8 text-center text-slate-400 font-medium bg-slate-50/30">
                    Không có phòng nào đã đến trong ngày hôm nay.
                  </td>
                </tr>
              </template>
              
              <template v-else v-for="booking in daDenBookings" :key="booking.id">
                <!-- Parent Row -->
                <tr class="hover:bg-slate-50/50 transition-colors h-10 font-semibold bg-slate-50/20">
                  <td class="p-2.5 text-center">
                    <input
                      type="checkbox"
                      :checked="isBookingAllSelected(booking, daDenBookings)"
                      :indeterminate="isBookingPartiallySelected(booking, daDenBookings)"
                      @change="toggleSelectBooking(booking, daDenBookings)"
                      class="rounded border-slate-300 text-sky-600 focus:ring-sky-500 w-3.5 h-3.5"
                    />
                  </td>
                  <td class="p-2.5 font-bold text-slate-900 flex items-center gap-1.5 h-10">
                    <button
                      @click="toggleCollapse(booking.id)"
                      class="w-4 h-4 flex items-center justify-center text-slate-400 hover:text-slate-600 bg-transparent border-none cursor-pointer p-0"
                    >
                      <span class="text-[10px] transform transition-transform" :class="collapsedBookings[booking.id] ? '-rotate-90' : ''">▼</span>
                    </button>
                    <span>{{ booking.booking_code }}</span>
                  </td>
                  <td class="p-2.5 text-slate-500">{{ booking.external_booking_code || '-' }}</td>
                  <td class="p-2.5 text-slate-800 font-bold uppercase truncate">{{ booking.booking_name }}</td>
                  <td class="p-2.5 text-slate-600 truncate">{{ booking.company?.name || 'KHÁCH LẺ' }}</td>
                  <td class="p-2.5 text-center">
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold border bg-emerald-50 text-emerald-600 border-emerald-100">
                      {{ booking.registration_status?.name || 'Guaranteed' }}
                    </span>
                  </td>
                  <td class="p-2.5 text-center text-slate-600">{{ formatDateDisplay(booking.arrival_date) }}</td>
                  <td class="p-2.5 text-center text-slate-600">{{ formatDateDisplay(booking.departure_date) }}</td>
                  <td class="p-2.5 text-center font-bold text-slate-700">{{ booking.booking_rooms.length }}</td>
                  <td class="p-2.5 pl-4 text-slate-500 italic truncate max-w-[200px]" :title="booking.note">{{ booking.note || '-' }}</td>
                </tr>

                <!-- Child Rows -->
                <tr 
                  v-if="!collapsedBookings[booking.id]"
                  v-for="room in booking.booking_rooms" 
                  :key="room.id"
                  class="border-b border-slate-100 hover:bg-slate-50/30 transition-colors h-9"
                >
                  <td class="p-2.5 text-center bg-slate-50/5">
                    <input
                      type="checkbox"
                      :checked="isRoomSelected(room.id)"
                      @change="toggleSelectRoom(room.id)"
                      class="rounded border-slate-300 text-sky-600 focus:ring-sky-500 w-3.5 h-3.5"
                    />
                  </td>
                  <td class="p-2.5 pl-6 font-bold text-sky-600">
                    {{ room.room_number || '--' }}
                  </td>
                  <td class="p-2.5"></td>
                  <td class="p-2.5 text-slate-600 truncate pl-6 flex items-center gap-1.5 h-9">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0"></span>
                    <span>{{ getRoomGuestName(room, booking) }}</span>
                  </td>
                  <td class="p-2.5"></td>
                  <td class="p-2.5 text-center"></td>
                  <td class="p-2.5 text-center text-slate-500">{{ formatDateDisplay(room.arrival_date) }}</td>
                  <td class="p-2.5 text-center text-slate-500">{{ formatDateDisplay(room.departure_date) }}</td>
                  <td class="p-2.5 text-center"></td>
                  <td class="p-2.5 pl-4 text-slate-400 text-[11px] truncate">{{ room.note || '-' }}</td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>
</template>

<style scoped>
.shadow-xs {
  box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
}
.shadow-2xs {
  box-shadow: 0 1px 1px 0 rgba(0, 0, 0, 0.03);
}
</style>
