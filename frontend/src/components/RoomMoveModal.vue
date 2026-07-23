<script setup>
import { ref, computed, watch } from 'vue'
import { fetchMoveTargetRooms, moveBookingRoom, fetchRoomRateCodes } from '@/services/booking-service'
import http from '@/services/http'
import { useUiStore } from '@/stores/ui-store'
import LoadingOverlay from '@/components/LoadingOverlay.vue'

const props = defineProps({
  show: {
    type: Boolean,
    default: false,
  },
  bookingId: {
    type: [String, Number],
    required: true,
  },
  roomId: {
    type: [String, Number],
    required: true,
  },
})

const emit = defineEmits(['close', 'success'])
const uiStore = useUiStore()

const loading = ref(false)
const submitting = ref(false)
const warningMsg = ref('')

const currentRoom = ref(null)
const availableRooms = ref([])
const occupiedRooms = ref([])
const rateCodes = ref([])
const standardRates = ref([])
const roomClasses = ref([])

const activeRateCodes = computed(() => {
  return (rateCodes.value || []).filter(rc => !rc.Disable)
})

// Selected Target Room state
const selectedMoveType = ref('available') // 'available' (Phòng trống) | 'merge' (Phòng đang ở)
const selectedTargetRoomNumber = ref('')

// Active Filter Values
const filterAvailClass = ref('')
const filterAvailForm = ref('')
const filterAvailSearch = ref('')

const filterOccClass = ref('')
const filterOccForm = ref('')
const filterOccSearch = ref('')

// Popovers visibility & temp search state
const showAvailSearchPopover = ref(false)
const tempAvailSearch = ref('')
const showAvailClassPopover = ref(false)
const showAvailFormPopover = ref(false)

const showOccSearchPopover = ref(false)
const tempOccSearch = ref('')
const showOccClassPopover = ref(false)
const showOccFormPopover = ref(false)

// Sub-modal Chọn khách (Guest selection)
const showGuestSelectModal = ref(false)
const guestSelectionList = ref([])

// Bottom controls
const isChangeRate = ref(false)
const selectedRateCode = ref('')
const newRate = ref(0)
const extraBedQty = ref(0)
const extraBedRate = ref(0)
const reason = ref('')

function closeAllPopovers() {
  showAvailSearchPopover.value = false
  showAvailClassPopover.value = false
  showAvailFormPopover.value = false
  showOccSearchPopover.value = false
  showOccClassPopover.value = false
  showOccFormPopover.value = false
}

// Left Table Popover Toggles & Actions
function toggleAvailSearchPopover() {
  const next = !showAvailSearchPopover.value
  closeAllPopovers()
  showAvailSearchPopover.value = next
  if (next) tempAvailSearch.value = filterAvailSearch.value
}

function applyAvailSearch() {
  filterAvailSearch.value = tempAvailSearch.value.trim()
  showAvailSearchPopover.value = false
}

function resetAvailSearch() {
  tempAvailSearch.value = ''
  filterAvailSearch.value = ''
  showAvailSearchPopover.value = false
}

function toggleAvailClassPopover() {
  const next = !showAvailClassPopover.value
  closeAllPopovers()
  showAvailClassPopover.value = next
}

function setAvailClass(val) {
  filterAvailClass.value = val
  showAvailClassPopover.value = false
}

function toggleAvailFormPopover() {
  const next = !showAvailFormPopover.value
  closeAllPopovers()
  showAvailFormPopover.value = next
}

function setAvailForm(val) {
  filterAvailForm.value = val
  showAvailFormPopover.value = false
}

// Right Table Popover Toggles & Actions
function toggleOccSearchPopover() {
  const next = !showOccSearchPopover.value
  closeAllPopovers()
  showOccSearchPopover.value = next
  if (next) tempOccSearch.value = filterOccSearch.value
}

function applyOccSearch() {
  filterOccSearch.value = tempOccSearch.value.trim()
  showOccSearchPopover.value = false
}

function resetOccSearch() {
  tempOccSearch.value = ''
  filterOccSearch.value = ''
  showOccSearchPopover.value = false
}

function toggleOccClassPopover() {
  const next = !showOccClassPopover.value
  closeAllPopovers()
  showOccClassPopover.value = next
}

function setOccClass(val) {
  filterOccClass.value = val
  showOccClassPopover.value = false
}

function toggleOccFormPopover() {
  const next = !showOccFormPopover.value
  closeAllPopovers()
  showOccFormPopover.value = next
}

function setOccForm(val) {
  filterOccForm.value = val
  showOccFormPopover.value = false
}

function isVirtualRoom(r) {
  if (!r) return false
  if (r.is_internal || r.is_virtual || r.isVirtual) return true

  const floorStr = String(r.floor || '').trim()
  const isFloorZero = ['0', 'Floor 0', 'Tầng 0', 'Floor virtual', 'Virtual'].includes(floorStr)
  const isRowZero = (r.grid_row === undefined || r.grid_row === null) ? true : Number(r.grid_row) === 0
  const isColZero = (r.grid_column === undefined || r.grid_column === null) ? true : Number(r.grid_column) === 0

  if (isFloorZero && isRowZero && isColZero) return true

  return false
}

// Filtered Lists for Left & Right tables
const filteredAvailableRooms = computed(() => {
  return availableRooms.value.filter(r => {
    if (filterAvailClass.value && r.room_class_name !== filterAvailClass.value) return false
    if (filterAvailForm.value && r.room_form_name !== filterAvailForm.value) return false
    if (filterAvailSearch.value) {
      const q = filterAvailSearch.value.toLowerCase()
      const matchNo = String(r.room_number).toLowerCase().includes(q)
      const matchClass = String(r.room_class_name || '').toLowerCase().includes(q)
      if (!matchNo && !matchClass) return false
    }
    return true
  })
})

const filteredOccupiedRooms = computed(() => {
  return occupiedRooms.value.filter(r => {
    if (filterOccClass.value && r.room_class_name !== filterOccClass.value) return false
    if (filterOccForm.value && r.room_form_name !== filterOccForm.value) return false
    if (filterOccSearch.value) {
      const q = filterOccSearch.value.toLowerCase()
      const matchNo = String(r.room_number).toLowerCase().includes(q)
      const matchClass = String(r.room_class_name || '').toLowerCase().includes(q)
      const matchGuest = String(r.primary_guest_name || '').toLowerCase().includes(q)
      if (!matchNo && !matchClass && !matchGuest) return false
    }
    return true
  })
})

const availableClassOptions = computed(() => {
  const set = new Set()
  availableRooms.value.forEach(r => {
    if (r.room_class_name) set.add(r.room_class_name)
  })
  return Array.from(set)
})

const availableFormOptions = computed(() => {
  const set = new Set()
  availableRooms.value.forEach(r => {
    if (r.room_form_name) set.add(r.room_form_name)
  })
  return Array.from(set)
})

const occupiedClassOptions = computed(() => {
  const set = new Set()
  occupiedRooms.value.forEach(r => {
    if (r.room_class_name) set.add(r.room_class_name)
  })
  return Array.from(set)
})

const occupiedFormOptions = computed(() => {
  const set = new Set()
  occupiedRooms.value.forEach(r => {
    if (r.room_form_name) set.add(r.room_form_name)
  })
  return Array.from(set)
})

const adultGuests = computed(() => guestSelectionList.value.filter(g => !g.is_child))
const childGuests = computed(() => guestSelectionList.value.filter(g => g.is_child))

const selectedTargetRoomObj = computed(() => {
  if (selectedMoveType.value === 'available') {
    return availableRooms.value.find(r => r.room_number === selectedTargetRoomNumber.value)
  } else {
    return occupiedRooms.value.find(r => r.room_number === selectedTargetRoomNumber.value)
  }
})

const isTargetRoomReady = computed(() => {
  if (selectedMoveType.value !== 'available') return true
  if (!selectedTargetRoomObj.value) return true
  return selectedTargetRoomObj.value.is_ready === true
})

function formatCurrency(val) {
  if (val === null || val === undefined || isNaN(val)) return '0'
  return new Intl.NumberFormat('en-US').format(Number(val))
}

function parseFormattedNumber(formattedStr) {
  if (!formattedStr) return 0
  const cleanStr = String(formattedStr).replace(/[^0-9]/g, '')
  return Number(cleanStr) || 0
}

const formattedNewRate = computed({
  get() {
    return formatCurrency(newRate.value)
  },
  set(val) {
    newRate.value = parseFormattedNumber(val)
  }
})

const formattedExtraBedRate = computed({
  get() {
    return formatCurrency(extraBedRate.value)
  },
  set(val) {
    extraBedRate.value = parseFormattedNumber(val)
  }
})

watch(() => props.show, async (newVal) => {
  if (newVal) {
    resetForm()
    await loadData()
  }
}, { immediate: true })

function resetForm() {
  selectedMoveType.value = 'available'
  selectedTargetRoomNumber.value = ''
  reason.value = ''
  isChangeRate.value = false
  selectedRateCode.value = ''
  newRate.value = 0
  extraBedQty.value = 0
  extraBedRate.value = 0
  filterAvailClass.value = ''
  filterAvailForm.value = ''
  filterAvailSearch.value = ''
  filterOccClass.value = ''
  filterOccForm.value = ''
  filterOccSearch.value = ''
  showGuestSelectModal.value = false
  guestSelectionList.value = []
  closeAllPopovers()
  warningMsg.value = ''
}

async function loadData() {
  if (!props.bookingId || !props.roomId) return
  loading.value = true
  try {
    const [resTarget, resRateCodes, resStandardRates, resRoomClasses] = await Promise.all([
      fetchMoveTargetRooms(props.bookingId, props.roomId),
      fetchRoomRateCodes().catch(() => ({ data: [] })),
      http.get('/standard-rates').catch(() => ({ data: { data: [] } })),
      http.get('/room-classes').catch(() => ({ data: [] }))
    ])

    if (resTarget.data?.success) {
      const data = resTarget.data.data
      currentRoom.value = data.current_room
      availableRooms.value = data.available_rooms || []
      occupiedRooms.value = data.occupied_rooms || []

      newRate.value = currentRoom.value?.rate || 0
      extraBedQty.value = currentRoom.value?.extra_bed_qty || 0
      extraBedRate.value = currentRoom.value?.extra_bed_rate || 0

      if (currentRoom.value?.is_do_not_move) {
        warningMsg.value = `Phòng ${currentRoom.value.room_number} đang bị khóa chuyển phòng (Do Not Move). Vui lòng mở khóa trước.`
      }
    }

    if (Array.isArray(resRateCodes.data)) {
      rateCodes.value = resRateCodes.data
    } else if (resRateCodes.data?.data) {
      rateCodes.value = resRateCodes.data.data
    }

    if (Array.isArray(resStandardRates.data)) {
      standardRates.value = resStandardRates.data
    } else if (resStandardRates.data?.data) {
      standardRates.value = resStandardRates.data.data
    }

    if (Array.isArray(resRoomClasses.data)) {
      roomClasses.value = resRoomClasses.data
    } else if (resRoomClasses.data?.data) {
      roomClasses.value = resRoomClasses.data.data
    }
  } catch (err) {
    console.error('Lỗi khi lấy danh sách phòng chuyển:', err)
    const msg = err.response?.data?.message || 'Không thể lấy thông tin phòng khả dụng.'
    uiStore.showToast(msg, 'error')
  } finally {
    loading.value = false
  }
}

function populateStandardExtraBedRate() {
  if (!isChangeRate.value) return

  const targetRoom = selectedTargetRoomObj.value || currentRoom.value
  if (!targetRoom) return

  const roomClassId = targetRoom.room_class_id
  const matchedSr = (standardRates.value || []).find(sr => Number(sr.room_class_id) === Number(roomClassId))

  if (matchedSr && Number(matchedSr.extra_bed_price) > 0) {
    extraBedRate.value = Number(matchedSr.extra_bed_price)
  }
}

function getPriceFromRatePlans(matchedRc, roomClassId, roomClassCode, roomFormName) {
  const plans = matchedRc?.rate_plans || matchedRc?.ratePlans || []
  if (!matchedRc || plans.length === 0) return null

  for (const plan of plans) {
    if (!plan.Period) continue
    let periodObj = plan.Period
    if (typeof periodObj === 'string') {
      try { periodObj = JSON.parse(periodObj) } catch (e) { periodObj = {} }
    }
    if (!periodObj || typeof periodObj !== 'object') continue

    const planCode = (plan.Code || 'DEFAULT').trim().toUpperCase()
    const rateCodeMa = (matchedRc.Ma || matchedRc.code || '').trim().toUpperCase()
    const rcCode = (roomClassCode || '').trim().toUpperCase()
    const rfName = (roomFormName || '').trim()
    const rfUpper = rfName.toUpperCase()

    // 1. Direct matrix grid key lookups (highest priority)
    const matrixKeys = [
      `${planCode}_${rcCode}_${rfName}`,
      `${planCode}_${rcCode}_${rfUpper}`,
      `${rateCodeMa}_${rcCode}_${rfName}`,
      `${rateCodeMa}_${rcCode}_${rfUpper}`,
      `DEFAULT_${rcCode}_${rfName}`,
      `DEFAULT_${rcCode}_${rfUpper}`,
      `${rcCode}_${rfName}`,
      `${rcCode}_${rfUpper}`,
    ]

    for (const key of matrixKeys) {
      if (key && periodObj[key] !== undefined && periodObj[key] !== '' && periodObj[key] !== null) {
        const val = Number(periodObj[key])
        if (!isNaN(val) && val > 0) return val
      }
    }

    // 2. Search all keys in periodObj for room class code + form name
    const keys = Object.keys(periodObj)
    if (rcCode && rfUpper) {
      const matchedKey = keys.find(k => {
        const kUpper = k.toUpperCase()
        return kUpper.includes(rcCode) && kUpper.includes(rfUpper)
      })
      if (matchedKey && periodObj[matchedKey] !== undefined) {
        const val = Number(periodObj[matchedKey])
        if (!isNaN(val) && val > 0) return val
      }
    }

    if (rcCode) {
      const matchedKey = keys.find(k => k.toUpperCase().includes(rcCode))
      if (matchedKey && periodObj[matchedKey] !== undefined) {
        const val = Number(periodObj[matchedKey])
        if (!isNaN(val) && val > 0) return val
      }
    }

    // 3. Fallback to legacy numeric ID keys (lowest priority)
    const legacyKeys = [
      `${planCode}_${roomClassId}_${rfName}`,
      `${planCode}_${roomClassId}_${rfUpper}`,
      `${roomClassId}_${rfName}`,
      `${roomClassId}_${rfUpper}`,
      `${roomClassId}`,
    ]

    for (const key of legacyKeys) {
      if (key && periodObj[key] !== undefined && periodObj[key] !== '' && periodObj[key] !== null) {
        const val = Number(periodObj[key])
        if (!isNaN(val) && val > 0) return val
      }
    }
  }

  return null
}

function applyRateCodePrice() {
  if (!isChangeRate.value) return

  // Tự động điền giá thêm giường từ Giá phòng chuẩn bất kể có chọn mã giá phòng hay không
  populateStandardExtraBedRate()

  if (!selectedRateCode.value) return

  const targetRoom = selectedTargetRoomObj.value || currentRoom.value
  if (!targetRoom) return

  const roomClassId = targetRoom.room_class_id
  const roomFormName = targetRoom.room_form_name || targetRoom.room_form || ''
  
  // Tra cứu mã hạng phòng (code/Ma) từ list roomClasses
  const matchedRoomClass = (roomClasses.value || []).find(rc => Number(rc.id) === Number(roomClassId))
  const roomClassCode = targetRoom.room_class_code || matchedRoomClass?.code || matchedRoomClass?.Ma || ''

  // 1. Tra cứu giá từ mã giá phòng (ratePlans period hoặc Value)
  const matchedRc = (rateCodes.value || []).find(rc => (rc.Ma || rc.code) === selectedRateCode.value)
  let foundPrice = null

  if (matchedRc) {
    foundPrice = getPriceFromRatePlans(matchedRc, roomClassId, roomClassCode, roomFormName)
    if (foundPrice === null && matchedRc.Value && Number(matchedRc.Value) > 0) {
      foundPrice = Number(matchedRc.Value)
    }
  }

  // 2. Tra cứu giá chuẩn (standard_rates) cho hạng phòng
  if (foundPrice === null) {
    const matchedSr = (standardRates.value || []).find(sr => Number(sr.room_class_id) === Number(roomClassId))
    if (matchedSr && Number(matchedSr.room_price) > 0) {
      foundPrice = Number(matchedSr.room_price)
    }
  }

  if (foundPrice !== null) {
    newRate.value = foundPrice
  }
}

watch(selectedRateCode, () => {
  applyRateCodePrice()
})

watch(selectedTargetRoomNumber, () => {
  if (isChangeRate.value) {
    applyRateCodePrice()
  }
})

watch(isChangeRate, (newVal) => {
  if (newVal) {
    populateStandardExtraBedRate()
    if (selectedRateCode.value) {
      applyRateCodePrice()
    }
  } else {
    newRate.value = currentRoom.value?.rate || 0
    extraBedRate.value = currentRoom.value?.extra_bed_rate || 0
  }
})

function selectAvailableRoom(room) {
  selectedMoveType.value = 'available'
  selectedTargetRoomNumber.value = room.room_number
  warningMsg.value = ''

  if (!isChangeRate.value) {
    newRate.value = currentRoom.value?.rate || 0
    extraBedRate.value = currentRoom.value?.extra_bed_rate || 0
  } else {
    populateStandardExtraBedRate()
    if (selectedRateCode.value) {
      applyRateCodePrice()
    }
  }

  if (!room.is_ready) {
    warningMsg.value = `Vui lòng kiểm tra tình trạng phòng: Phòng ${room.room_number} hiện ở trạng thái "${room.status_label}".`
  }
}

function selectOccupiedRoom(room) {
  selectedMoveType.value = 'merge'
  selectedTargetRoomNumber.value = room.room_number
  warningMsg.value = ''

  if (isChangeRate.value) {
    populateStandardExtraBedRate()
    if (selectedRateCode.value) {
      applyRateCodePrice()
    }
  }
}

function handleSubmit() {
  if (currentRoom.value?.is_do_not_move) {
    uiStore.showToast(`Phòng ${currentRoom.value.room_number} đang bị khóa chuyển phòng!`, 'error')
    return
  }

  if (!selectedTargetRoomNumber.value) {
    uiStore.showToast('Vui lòng chọn phòng đích!', 'warning')
    return
  }

  if (!reason.value.trim()) {
    uiStore.showToast('Vui lòng nhập lý do chuyển phòng!', 'warning')
    return
  }

  if (selectedMoveType.value === 'available') {
    const target = availableRooms.value.find(r => r.room_number === selectedTargetRoomNumber.value)
    if (target && !target.is_ready) {
      uiStore.showToast('Vui lòng kiểm tra tình trạng phòng (Phòng chưa ở trạng thái Sẵn sàng)', 'error')
      return
    }
    // Form A: Submit directly with all guests
    executeSubmit((currentRoom.value?.guests || []).map(g => g.guest_id))
  } else if (selectedMoveType.value === 'merge') {
    // Form B: Open "Chọn khách" sub-modal
    const guests = currentRoom.value?.guests || []
    const children = currentRoom.value?.children || []

    if (guests.length === 0 && children.length === 0) {
      uiStore.showToast('Phòng hiện tại không có danh sách khách!', 'error')
      return
    }

    const adultList = guests.map(g => ({
      guest_id: g.guest_id,
      full_name: g.full_name || 'Khách',
      is_child: false,
      selected: true, // Default all checked
    }))

    const childList = children.map(c => ({
      guest_id: c.guest_id,
      full_name: c.full_name || (c.age_group === 'baby' ? 'Em bé' : 'Trẻ em'),
      is_child: true,
      selected: true, // Default all checked
    }))

    guestSelectionList.value = [...adultList, ...childList]
    showGuestSelectModal.value = true
  }
}

function confirmGuestSelection() {
  const selectedIds = guestSelectionList.value.filter(g => g.selected).map(g => g.guest_id)

  if (selectedIds.length === 0) {
    uiStore.showToast('Vui lòng chọn ít nhất 1 khách để chuyển sang phòng gộp!', 'warning')
    return
  }

  showGuestSelectModal.value = false
  executeSubmit(selectedIds)
}

async function executeSubmit(selectedGuestIds, confirmExceedCapacity = false) {
  submitting.value = true
  try {
    const payload = {
      move_type: selectedMoveType.value,
      target_room_number: selectedTargetRoomNumber.value,
      reason: reason.value.trim(),
      selected_guest_ids: selectedGuestIds,
      is_change_rate: isChangeRate.value,
      rate_code: selectedRateCode.value,
      rate: Number(newRate.value),
      extra_bed_qty: Number(extraBedQty.value),
      extra_bed_rate: Number(extraBedRate.value),
      confirm_exceed_capacity: confirmExceedCapacity,
    }

    const res = await moveBookingRoom(props.bookingId, props.roomId, payload)
    if (res.data?.success) {
      uiStore.showToast(res.data.message || 'Thao tác chuyển phòng thành công!', 'success')
      emit('success')
      emit('close')
    } else {
      uiStore.showToast(res.data?.message || 'Không thể thực hiện chuyển phòng.', 'error')
    }
  } catch (err) {
    console.error('Lỗi khi chuyển phòng:', err)
    const errorData = err.response?.data
    if (errorData?.require_capacity_confirm) {
      if (confirm(errorData.message)) {
        submitting.value = false
        executeSubmit(selectedGuestIds, true)
        return
      }
    } else if (errorData?.detail) {
      uiStore.showToast(`${errorData.message}: ${errorData.detail}`, 'error')
    } else {
      uiStore.showToast(errorData?.message || 'Có lỗi xảy ra khi thực hiện chuyển phòng.', 'error')
    }
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <div v-if="show" @click="closeAllPopovers" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/40 backdrop-blur-[2px] p-2 select-none">
    <div @click.stop class="bg-white rounded-lg shadow-2xl w-full max-w-[1450px] overflow-hidden border border-slate-300 flex flex-col max-h-[95vh] text-xs">
      
      <!-- Top Window Header (Light Blue) -->
      <div class="px-4 py-2.5 bg-[#87c1e3] text-white flex items-center justify-between shadow-xs">
        <div class="flex items-center gap-2">
          <svg class="w-4 h-4 text-white shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M8 3L4 7l4 4M4 7h16M16 21l4-4-4-4M20 17H4" />
          </svg>
          <span class="font-bold text-sm text-white tracking-wide">Chuyển Phòng</span>
          <span v-if="currentRoom" class="text-xs text-sky-100 font-normal">
            (Phòng {{ currentRoom.room_number }} - Mã ĐK: {{ currentRoom.booking_code }})
          </span>
        </div>
        <div class="flex items-center gap-3">
          <button title="Trợ giúp" class="text-white/90 hover:text-white bg-transparent border-none text-sm cursor-pointer">
            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
              <circle cx="12" cy="12" r="10"/>
              <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
              <line x1="12" y1="17" x2="12.01" y2="17"/>
            </svg>
          </button>
          <button @click="emit('close')" title="Đóng" class="text-white/90 hover:text-white bg-transparent border-none text-base font-bold cursor-pointer leading-none">
            ✕
          </button>
        </div>
      </div>

      <!-- Warning Alert Banner -->
      <div v-if="warningMsg" class="px-4 py-1.5 bg-amber-100 border-b border-amber-300 text-amber-900 text-xs font-semibold flex items-center gap-2">
        <svg class="w-4 h-4 shrink-0 text-amber-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
          <line x1="12" y1="9" x2="12" y2="13"/>
          <line x1="12" y1="17" x2="12.01" y2="17"/>
        </svg>
        <span>{{ warningMsg }}</span>
      </div>

      <!-- Main Dual Table Body -->
      <div class="flex-1 overflow-hidden p-3 grid grid-cols-2 gap-3 bg-slate-100/60 min-h-[380px]">
        
        <!-- LEFT PANEL: Phòng trống -->
        <div class="bg-white rounded border border-slate-300 flex flex-col overflow-hidden shadow-2xs">
          <div class="px-3 py-1.5 bg-slate-100 border-b border-slate-300 font-bold text-slate-700 text-xs flex justify-between items-center">
            <span>Phòng trống</span>
            <span class="text-[11px] font-normal text-slate-500">({{ filteredAvailableRooms.length }} phòng)</span>
          </div>

          <div class="flex-1 overflow-auto">
            <table class="w-full text-left border-collapse text-[11px]">
              <thead class="bg-slate-50 sticky top-0 border-b border-slate-300 text-slate-700 font-bold z-10">
                <tr class="h-8 divide-x divide-slate-200">
                  <th class="w-8 px-2 text-center"></th>
                  
                  <!-- Column: Loại phòng -->
                  <th class="px-2 py-1 relative">
                    <div class="flex items-center justify-between">
                      <span>Loại phòng</span>
                      <button @click.stop="toggleAvailClassPopover" class="p-0.5 hover:bg-slate-200 rounded cursor-pointer border-none bg-transparent" :class="filterAvailClass ? 'text-sky-600 font-bold' : 'text-slate-400'">
                        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                      </button>
                    </div>
                    <!-- Class Filter Popover -->
                    <div v-if="showAvailClassPopover" @click.stop class="absolute top-full left-0 mt-1 z-50 bg-white rounded-md shadow-xl border border-slate-200 p-2 w-[180px] text-xs font-normal text-slate-800 max-h-48 overflow-auto">
                      <div @click="setAvailClass('')" class="px-2 py-1 hover:bg-sky-50 rounded cursor-pointer flex items-center justify-between" :class="!filterAvailClass ? 'font-bold text-sky-700 bg-sky-50' : ''">
                        <span>Tất cả loại phòng</span>
                        <span v-if="!filterAvailClass">✓</span>
                      </div>
                      <div v-for="opt in availClassOptions" :key="opt.id" @click="setAvailClass(opt.id)" class="px-2 py-1 hover:bg-sky-50 rounded cursor-pointer flex items-center justify-between" :class="filterAvailClass === String(opt.id) ? 'font-bold text-sky-700 bg-sky-50' : ''">
                        <span class="truncate">{{ opt.name }}</span>
                        <span v-if="filterAvailClass === String(opt.id)">✓</span>
                      </div>
                    </div>
                  </th>

                  <!-- Column: Dạng phòng -->
                  <th class="px-2 py-1 relative">
                    <div class="flex items-center justify-between">
                      <span>Dạng phòng</span>
                      <button @click.stop="toggleAvailFormPopover" class="p-0.5 hover:bg-slate-200 rounded cursor-pointer border-none bg-transparent" :class="filterAvailForm ? 'text-sky-600 font-bold' : 'text-slate-400'">
                        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                      </button>
                    </div>
                    <!-- Form Filter Popover -->
                    <div v-if="showAvailFormPopover" @click.stop class="absolute top-full left-0 mt-1 z-50 bg-white rounded-md shadow-xl border border-slate-200 p-2 w-[160px] text-xs font-normal text-slate-800 max-h-48 overflow-auto">
                      <div @click="setAvailForm('')" class="px-2 py-1 hover:bg-sky-50 rounded cursor-pointer flex items-center justify-between" :class="!filterAvailForm ? 'font-bold text-sky-700 bg-sky-50' : ''">
                        <span>Tất cả dạng phòng</span>
                        <span v-if="!filterAvailForm">✓</span>
                      </div>
                      <div v-for="fName in availFormOptions" :key="fName" @click="setAvailForm(fName)" class="px-2 py-1 hover:bg-sky-50 rounded cursor-pointer flex items-center justify-between" :class="filterAvailForm === fName ? 'font-bold text-sky-700 bg-sky-50' : ''">
                        <span class="truncate">{{ fName }}</span>
                        <span v-if="filterAvailForm === fName">✓</span>
                      </div>
                    </div>
                  </th>

                  <!-- Column: Phòng (With Search Icon Popover) -->
                  <th class="px-2 py-1 relative">
                    <div class="flex items-center justify-between">
                      <span>Phòng</span>
                      <button @click.stop="toggleAvailSearchPopover" class="p-0.5 hover:bg-slate-200 rounded cursor-pointer border-none bg-transparent" :class="filterAvailSearch ? 'text-sky-600 font-bold' : 'text-slate-400'">
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                      </button>
                    </div>

                    <!-- Search Popover Box -->
                    <div v-if="showAvailSearchPopover" @click.stop class="absolute top-full left-0 mt-1 z-50 bg-white rounded-md shadow-xl border border-slate-200 p-2.5 w-[210px] text-xs font-normal text-slate-800">
                      <input
                        v-model="tempAvailSearch"
                        @keyup.enter="applyAvailSearch"
                        type="text"
                        placeholder="Search room"
                        class="w-full px-2 py-1 text-xs border border-slate-300 rounded outline-none focus:border-sky-500 mb-2"
                        autoFocus
                      />
                      <div class="flex items-center gap-1.5 justify-end">
                        <button
                          @click="applyAvailSearch"
                          type="button"
                          class="px-3 py-1 bg-[#7bc4ff] hover:bg-[#64b5f6] text-white font-semibold rounded text-xs shadow-2xs flex items-center gap-1 cursor-pointer border-none"
                        >
                          <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                          <span>Search</span>
                        </button>
                        <button
                          @click="resetAvailSearch"
                          type="button"
                          class="px-3 py-1 bg-white hover:bg-slate-100 text-slate-700 font-medium rounded text-xs border border-slate-300 cursor-pointer"
                        >
                          <span>Reset</span>
                        </button>
                      </div>
                    </div>
                  </th>

                  <th class="px-2 py-1 text-right">Giá phòng</th>
                  <th class="px-2 py-1 text-right">Giá thêm giường</th>
                  <th class="px-2 py-1">Trạng thái</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-200 text-slate-700 bg-white">
                <tr v-if="filteredAvailableRooms.length === 0">
                  <td colspan="7" class="py-8 text-center text-slate-400 italic">
                    Không có phòng trống khả dụng
                  </td>
                </tr>
                <tr
                  v-for="r in filteredAvailableRooms"
                  :key="r.id"
                  @click="selectAvailableRoom(r)"
                  class="h-8 hover:bg-sky-50/70 cursor-pointer transition-colors divide-x divide-slate-100"
                  :class="[
                    selectedMoveType === 'available' && selectedTargetRoomNumber === r.room_number
                      ? 'bg-sky-100/90 font-semibold'
                      : '',
                    !r.is_ready ? 'text-amber-900 bg-amber-50/30' : ''
                  ]"
                >
                  <td class="text-center px-1">
                    <input
                      type="radio"
                      name="targetRoomGroup"
                      :checked="selectedMoveType === 'available' && selectedTargetRoomNumber === r.room_number"
                      class="text-sky-600 focus:ring-sky-500 rounded-full w-3.5 h-3.5 cursor-pointer"
                    />
                  </td>
                  <td class="px-2 truncate max-w-[220px]" :title="r.room_class_name">{{ r.room_class_name }}</td>
                  <td class="px-2 truncate max-w-[110px]">{{ r.room_form_name }}</td>
                  <td class="px-2 font-bold text-slate-900">{{ r.room_number }}</td>
                  <td class="px-2 text-right tabular-nums">{{ formatCurrency(r.rate) }}</td>
                  <td class="px-2 text-right tabular-nums">{{ formatCurrency(r.extra_bed_rate) }}</td>
                  <td class="px-2 truncate">
                    <span :class="r.is_ready ? 'text-slate-700' : 'text-amber-700 font-semibold'">
                      {{ r.status_label }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- RIGHT PANEL: Phòng đang ở (Gộp phòng) -->
        <div class="bg-blue-50/40 rounded border border-slate-300 flex flex-col overflow-hidden shadow-2xs">
          <div class="px-3 py-1.5 bg-blue-100/70 border-b border-slate-300 font-bold text-slate-700 text-xs flex justify-between items-center">
            <span>Phòng đang ở (Gộp phòng)</span>
            <span class="text-[11px] font-normal text-slate-500">({{ filteredOccupiedRooms.length }} phòng)</span>
          </div>

          <div class="flex-1 overflow-auto">
            <table class="w-full text-left border-collapse text-[11px]">
              <thead class="bg-blue-100/50 sticky top-0 border-b border-slate-300 text-slate-700 font-bold z-10">
                <tr class="h-8 divide-x divide-slate-200">
                  <th class="w-8 px-2 text-center"></th>
                  
                  <!-- Column: Loại phòng -->
                  <th class="px-2 py-1 relative">
                    <div class="flex items-center justify-between">
                      <span>Loại phòng</span>
                      <button @click.stop="toggleOccClassPopover" class="p-0.5 hover:bg-blue-200 rounded cursor-pointer border-none bg-transparent" :class="filterOccClass ? 'text-sky-600 font-bold' : 'text-slate-400'">
                        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                      </button>
                    </div>
                    <!-- Class Filter Popover -->
                    <div v-if="showOccClassPopover" @click.stop class="absolute top-full left-0 mt-1 z-50 bg-white rounded-md shadow-xl border border-slate-200 p-2 w-[180px] text-xs font-normal text-slate-800 max-h-48 overflow-auto">
                      <div @click="setOccClass('')" class="px-2 py-1 hover:bg-sky-50 rounded cursor-pointer flex items-center justify-between" :class="!filterOccClass ? 'font-bold text-sky-700 bg-sky-50' : ''">
                        <span>Tất cả loại phòng</span>
                        <span v-if="!filterOccClass">✓</span>
                      </div>
                      <div v-for="cName in occClassOptions" :key="cName" @click="setOccClass(cName)" class="px-2 py-1 hover:bg-sky-50 rounded cursor-pointer flex items-center justify-between" :class="filterOccClass === cName ? 'font-bold text-sky-700 bg-sky-50' : ''">
                        <span class="truncate">{{ cName }}</span>
                        <span v-if="filterOccClass === cName">✓</span>
                      </div>
                    </div>
                  </th>

                  <!-- Column: Dạng phòng -->
                  <th class="px-2 py-1 relative">
                    <div class="flex items-center justify-between">
                      <span>Dạng phòng</span>
                      <button @click.stop="toggleOccFormPopover" class="p-0.5 hover:bg-blue-200 rounded cursor-pointer border-none bg-transparent" :class="filterOccForm ? 'text-sky-600 font-bold' : 'text-slate-400'">
                        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9l6 6 6-6"/></svg>
                      </button>
                    </div>
                    <!-- Form Filter Popover -->
                    <div v-if="showOccFormPopover" @click.stop class="absolute top-full left-0 mt-1 z-50 bg-white rounded-md shadow-xl border border-slate-200 p-2 w-[160px] text-xs font-normal text-slate-800 max-h-48 overflow-auto">
                      <div @click="setOccForm('')" class="px-2 py-1 hover:bg-sky-50 rounded cursor-pointer flex items-center justify-between" :class="!filterOccForm ? 'font-bold text-sky-700 bg-sky-50' : ''">
                        <span>Tất cả dạng phòng</span>
                        <span v-if="!filterOccForm">✓</span>
                      </div>
                      <div v-for="fName in occFormOptions" :key="fName" @click="setOccForm(fName)" class="px-2 py-1 hover:bg-sky-50 rounded cursor-pointer flex items-center justify-between" :class="filterOccForm === fName ? 'font-bold text-sky-700 bg-sky-50' : ''">
                        <span class="truncate">{{ fName }}</span>
                        <span v-if="filterOccForm === fName">✓</span>
                      </div>
                    </div>
                  </th>

                  <!-- Column: Phòng (Search Popover) -->
                  <th class="px-2 py-1 relative">
                    <div class="flex items-center justify-between">
                      <span>Phòng</span>
                      <button @click.stop="toggleOccSearchPopover" class="p-0.5 hover:bg-blue-200 rounded cursor-pointer border-none bg-transparent" :class="filterOccSearch ? 'text-sky-600 font-bold' : 'text-slate-400'">
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                      </button>
                    </div>

                    <!-- Search Popover Box -->
                    <div v-if="showOccSearchPopover" @click.stop class="absolute top-full left-0 mt-1 z-50 bg-white rounded-md shadow-xl border border-slate-200 p-2.5 w-[210px] text-xs font-normal text-slate-800">
                      <input
                        v-model="tempOccSearch"
                        @keyup.enter="applyOccSearch"
                        type="text"
                        placeholder="Search room"
                        class="w-full px-2 py-1 text-xs border border-slate-300 rounded outline-none focus:border-sky-500 mb-2"
                        autoFocus
                      />
                      <div class="flex items-center gap-1.5 justify-end">
                        <button
                          @click="applyOccSearch"
                          type="button"
                          class="px-3 py-1 bg-[#7bc4ff] hover:bg-[#64b5f6] text-white font-semibold rounded text-xs shadow-2xs flex items-center gap-1 cursor-pointer border-none"
                        >
                          <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                          <span>Search</span>
                        </button>
                        <button
                          @click="resetOccSearch"
                          type="button"
                          class="px-3 py-1 bg-white hover:bg-slate-100 text-slate-700 font-medium rounded text-xs border border-slate-300 cursor-pointer"
                        >
                          <span>Reset</span>
                        </button>
                      </div>
                    </div>
                  </th>

                  <th class="px-2 py-1 text-right">Giá phòng</th>
                  <th class="px-2 py-1 text-right">Giá thêm giường</th>
                  <th class="px-2 py-1">Trạng thái</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-200 text-slate-700 bg-blue-50/20">
                <tr v-if="filteredOccupiedRooms.length === 0">
                  <td colspan="7" class="py-8 text-center text-slate-400 italic">
                    Không có phòng in-house thỏa điều kiện gộp
                  </td>
                </tr>
                <tr
                  v-for="r in filteredOccupiedRooms"
                  :key="r.booking_room_id"
                  @click="selectOccupiedRoom(r)"
                  class="h-8 hover:bg-blue-100/60 cursor-pointer transition-colors divide-x divide-slate-100"
                  :class="selectedMoveType === 'merge' && selectedTargetRoomNumber === r.room_number ? 'bg-blue-200/80 font-semibold' : ''"
                >
                  <td class="text-center px-1">
                    <input
                      type="radio"
                      name="targetRoomGroup"
                      :checked="selectedMoveType === 'merge' && selectedTargetRoomNumber === r.room_number"
                      class="text-sky-600 focus:ring-sky-500 rounded-full w-3.5 h-3.5 cursor-pointer"
                    />
                  </td>
                  <td class="px-2 truncate max-w-[220px]" :title="r.room_class_name">{{ r.room_class_name }}</td>
                  <td class="px-2 truncate max-w-[110px]">{{ r.room_form_name }}</td>
                  <td class="px-2 font-bold text-slate-900">{{ r.room_number }}</td>
                  <td class="px-2 text-right tabular-nums">{{ formatCurrency(r.rate) }}</td>
                  <td class="px-2 text-right tabular-nums">{{ formatCurrency(r.extra_bed_rate) }}</td>
                  <td class="px-2 truncate text-slate-700" :title="r.status_label">
                    {{ r.status_label }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

      </div>

      <!-- Bottom Controls Bar -->
      <div class="px-4 py-3 bg-white border-t border-slate-300 flex items-center justify-between gap-3 text-xs">
        <div class="flex items-center gap-4 flex-1">
          <!-- Checkbox Thay đổi giá -->
          <label class="flex items-center gap-1.5 font-medium text-slate-700 cursor-pointer shrink-0">
            <input
              type="checkbox"
              v-model="isChangeRate"
              class="rounded border-slate-300 text-sky-600 focus:ring-sky-500 w-4 h-4"
            />
            <span>Thay đổi giá</span>
          </label>

          <!-- Mã giá phòng -->
          <div class="flex items-center gap-1.5">
            <span class="text-slate-600 shrink-0">Mã giá phòng</span>
            <select
              v-model="selectedRateCode"
              :disabled="!isChangeRate"
              class="w-28 px-2 py-1 text-xs border border-slate-300 rounded bg-slate-50 disabled:bg-slate-100 disabled:text-slate-400 outline-none focus:border-sky-500 font-medium truncate"
              :title="selectedRateCode"
            >
              <option value="">Mã giá phòng</option>
              <option v-for="rc in activeRateCodes" :key="rc.Ma || rc.id || rc.code" :value="rc.Ma || rc.code">
                {{ rc.Ma || rc.code }}
              </option>
            </select>
          </div>

          <!-- Giá phòng -->
          <div class="flex items-center gap-1.5">
            <span class="text-slate-600 shrink-0">Giá phòng</span>
            <input
              v-model="formattedNewRate"
              :disabled="!isChangeRate"
              type="text"
              class="w-20 px-2 py-1 text-xs text-right border border-slate-300 rounded bg-slate-50 disabled:bg-slate-100 disabled:text-slate-400 outline-none focus:border-sky-500 font-medium"
            />
          </div>

          <!-- Thêm giường -->
          <div class="flex items-center gap-1.5">
            <span class="text-slate-600 shrink-0">Thêm giường</span>
            <input
              v-model.number="extraBedQty"
              :disabled="!isChangeRate"
              type="number"
              min="0"
              class="w-14 px-2 py-1 text-xs text-right border border-slate-300 rounded bg-slate-50 disabled:bg-slate-100 disabled:text-slate-400 outline-none focus:border-sky-500 font-medium"
            />
          </div>

          <!-- Giá thêm giường -->
          <div class="flex items-center gap-1.5">
            <span class="text-slate-600 shrink-0">Giá thêm giường</span>
            <input
              v-model="formattedExtraBedRate"
              :disabled="!isChangeRate"
              type="text"
              class="w-20 px-2 py-1 text-xs text-right border border-slate-300 rounded bg-slate-50 disabled:bg-slate-100 disabled:text-slate-400 outline-none focus:border-sky-500 font-medium"
            />
          </div>

          <!-- Lý do (Yellow Background as Screenshot) -->
          <div class="flex items-center gap-1.5 flex-1 min-w-[200px]">
            <span class="text-slate-600 shrink-0 font-medium">Lý do</span>
            <input
              v-model="reason"
              type="text"
              placeholder="Nhập lý do chuyển phòng..."
              class="w-full px-2.5 py-1 text-xs border border-slate-300 rounded outline-none focus:border-sky-500 bg-[#ffffcc] text-slate-900 font-medium"
            />
          </div>
        </div>
      </div>

      <!-- Footer Buttons -->
      <div class="px-4 py-2.5 bg-slate-100 border-t border-slate-200 flex items-center justify-end gap-2">
        <button
          @click="emit('close')"
          type="button"
          class="px-3.5 py-1.5 text-xs font-semibold text-slate-700 bg-sky-200/70 hover:bg-sky-300 border border-sky-300 rounded shadow-xs transition-colors flex items-center gap-1.5 cursor-pointer"
        >
          <svg class="w-3.5 h-3.5 text-slate-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
          <span>Đóng</span>
        </button>

        <button
          @click="emit('close')"
          type="button"
          class="px-3.5 py-1.5 text-xs font-semibold text-sky-800 bg-sky-100 hover:bg-sky-200 border border-sky-300 rounded shadow-xs transition-colors flex items-center gap-1.5 cursor-pointer"
        >
          <svg class="w-3.5 h-3.5 text-sky-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M9 14L4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 5.5 5.5v0a5.5 5.5 0 0 1-5.5 5.5H11"/></svg>
          <span>Quay lại</span>
        </button>

        <button
          @click="handleSubmit"
          :disabled="submitting || !selectedTargetRoomNumber || (selectedMoveType === 'available' && selectedTargetRoomObj && !isTargetRoomReady)"
          type="button"
          class="px-4 py-1.5 text-xs font-semibold text-white bg-[#5bb0db] hover:bg-[#4a9ec8] disabled:opacity-50 disabled:cursor-not-allowed border border-sky-600/30 rounded shadow-xs transition-colors flex items-center gap-1.5 cursor-pointer"
        >
          <svg v-if="submitting" class="w-3.5 h-3.5 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <circle cx="12" cy="12" r="10" stroke-width="4" class="opacity-25"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
          </svg>
          <svg v-else class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
          <span>Lưu</span>
        </button>
      </div>

      <!-- Loading overlay -->
      <LoadingOverlay :show="loading" message="Đang tải danh sách phòng khả dụng..." />
    </div>

    <!-- Modal Chọn Khách (Sub-modal matching exact screenshot) -->
    <div v-if="showGuestSelectModal" @click.stop class="fixed inset-0 z-[10000] flex items-center justify-center bg-black/40 backdrop-blur-[2px] p-4 select-none">
      <div class="bg-white rounded-xl shadow-2xl w-full max-w-[440px] overflow-hidden border border-slate-200 flex flex-col text-xs">
        
        <!-- Header (Light Blue matching screenshot) -->
        <div class="px-4 py-2.5 bg-[#87c1e3] text-white flex items-center justify-between shadow-xs">
          <span class="font-bold text-sm text-white tracking-wide">Chọn khách</span>
          <button @click="showGuestSelectModal = false" title="Đóng" class="text-white/90 hover:text-white bg-transparent border-none text-base font-bold cursor-pointer leading-none">
            ✕
          </button>
        </div>

        <!-- Body: Guest List -->
        <div class="p-4 space-y-4 max-h-[60vh] overflow-auto">
          <!-- Người lớn -->
          <div>
            <div class="font-semibold text-slate-700 mb-2">Người lớn</div>
            <div v-if="adultGuests.length === 0" class="text-slate-400 italic pl-1">Không có khách người lớn</div>
            <div class="space-y-2">
              <label
                v-for="g in adultGuests"
                :key="g.guest_id"
                class="flex items-center justify-between border border-slate-300 rounded-full px-4 py-2 hover:bg-slate-50 cursor-pointer transition-colors"
                :class="g.selected ? 'border-sky-400 bg-sky-50/50' : 'bg-white'"
              >
                <span class="font-semibold text-slate-800 tracking-wide uppercase">{{ g.full_name }}</span>
                <input
                  type="checkbox"
                  v-model="g.selected"
                  class="rounded text-sky-600 focus:ring-sky-500 w-4 h-4 cursor-pointer"
                />
              </label>
            </div>
          </div>

          <!-- Trẻ em -->
          <div>
            <div class="font-semibold text-slate-700 mb-2">Trẻ em</div>
            <div v-if="childGuests.length === 0" class="text-slate-400 italic text-[11px] pl-1">Không có khách trẻ em</div>
            <div class="space-y-2">
              <label
                v-for="g in childGuests"
                :key="g.guest_id"
                class="flex items-center justify-between border border-slate-300 rounded-full px-4 py-2 hover:bg-slate-50 cursor-pointer transition-colors"
                :class="g.selected ? 'border-sky-400 bg-sky-50/50' : 'bg-white'"
              >
                <span class="font-semibold text-slate-800 tracking-wide uppercase">{{ g.full_name }}</span>
                <input
                  type="checkbox"
                  v-model="g.selected"
                  class="rounded text-sky-600 focus:ring-sky-500 w-4 h-4 cursor-pointer"
                />
              </label>
            </div>
          </div>
        </div>

        <!-- Footer Buttons -->
        <div class="px-4 py-3 bg-slate-50 border-t border-slate-200 flex items-center justify-end gap-2">
          <button
            @click="showGuestSelectModal = false"
            type="button"
            class="px-4 py-1.5 text-xs font-semibold text-sky-800 bg-sky-100 hover:bg-sky-200 border border-sky-300 rounded-lg shadow-2xs transition-colors flex items-center gap-1.5 cursor-pointer"
          >
            <svg class="w-3.5 h-3.5 text-sky-700" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
            <span>Đóng</span>
          </button>

          <button
            @click="confirmGuestSelection"
            :disabled="submitting"
            type="button"
            class="px-4 py-1.5 text-xs font-semibold text-white bg-[#5bb0db] hover:bg-[#4a9ec8] disabled:opacity-50 border border-sky-600/30 rounded-lg shadow-2xs transition-colors flex items-center gap-1.5 cursor-pointer"
          >
            <svg v-if="submitting" class="w-3.5 h-3.5 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor">
              <circle cx="12" cy="12" r="10" stroke-width="4" class="opacity-25"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
            </svg>
            <svg v-else class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
            <span>Lưu</span>
          </button>
        </div>

      </div>
    </div>
  </div>
</template>
