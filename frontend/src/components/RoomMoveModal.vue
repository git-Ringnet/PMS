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
    type: [String, Number, Object],
    default: null,
  },
  roomId: {
    type: [String, Number, Object],
    default: null,
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

// State thu gọn/mở rộng bảng bên phải (Phòng đang ở)
const isRightPanelCollapsed = ref(true)

function toggleRightPanel() {
  isRightPanelCollapsed.value = !isRightPanelCollapsed.value
}

const activeRateCodes = computed(() => {
  return (rateCodes.value || []).filter(rc => !rc.Disable)
})

// Selected Target Room state
const selectedMoveType = ref('available') // 'available' (Phòng trống) | 'merge' (Phòng đang ở)
const selectedTargetRoomNumber = ref('')

// Active Filter & Popover Search Values
const filterAvailClass = ref('')
const filterAvailForm = ref('')
const filterAvailSearch = ref('')
const showAvailSearchPopover = ref(false)
const tempAvailSearch = ref('')

const filterOccClass = ref('')
const filterOccForm = ref('')
const filterOccSearch = ref('')
const showOccSearchPopover = ref(false)
const tempOccSearch = ref('')

function closeAllPopovers() {
  showAvailSearchPopover.value = false
  showOccSearchPopover.value = false
}

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

// Filtered Lists for Left & Right tables
const filteredAvailableRooms = computed(() => {
  return availableRooms.value.filter(r => {
    if (filterAvailClass.value && r.room_class_name !== filterAvailClass.value) return false
    if (filterAvailForm.value && r.room_form_name !== filterAvailForm.value) return false
    if (filterAvailSearch.value) {
      const q = filterAvailSearch.value.toLowerCase()
      const matchNo = String(r.room_number || '').toLowerCase().includes(q)
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
      const matchNo = String(r.room_number || '').toLowerCase().includes(q)
      const matchClass = String(r.room_class_name || '').toLowerCase().includes(q)
      const matchGuest = String(r.primary_guest_name || '').toLowerCase().includes(q)
      if (!matchNo && !matchClass && !matchGuest) return false
    }
    return true
  })
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
  isRightPanelCollapsed.value = true
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
  tempAvailSearch.value = ''
  showAvailSearchPopover.value = false
  filterOccClass.value = ''
  filterOccForm.value = ''
  filterOccSearch.value = ''
  tempOccSearch.value = ''
  showOccSearchPopover.value = false
  showGuestSelectModal.value = false
  guestSelectionList.value = []
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

  populateStandardExtraBedRate()

  if (!selectedRateCode.value) return

  const targetRoom = selectedTargetRoomObj.value || currentRoom.value
  if (!targetRoom) return

  const roomClassId = targetRoom.room_class_id
  const roomFormName = targetRoom.room_form_name || targetRoom.room_form || ''

  const matchedRoomClass = (roomClasses.value || []).find(rc => Number(rc.id) === Number(roomClassId))
  const roomClassCode = targetRoom.room_class_code || matchedRoomClass?.code || matchedRoomClass?.Ma || ''

  const matchedRc = (rateCodes.value || []).find(rc => (rc.Ma || rc.code) === selectedRateCode.value)
  let foundPrice = null

  if (matchedRc) {
    foundPrice = getPriceFromRatePlans(matchedRc, roomClassId, roomClassCode, roomFormName)
    if (foundPrice === null && matchedRc.Value && Number(matchedRc.Value) > 0) {
      foundPrice = Number(matchedRc.Value)
    }
  }

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
    uiStore.showToast('Vui lòng nhập lý do chuyển phòng!', 'error')
    return
  }

  if (selectedMoveType.value === 'available' && !isTargetRoomReady.value) {
    uiStore.showToast('Vui lòng kiểm tra tình trạng phòng (Phòng chưa ở trạng thái Sẵn sàng)', 'error')
    return
  }

  // Chuẩn hóa collection sau khi lọc khách đã chuyển
  const toArray = (value) => Array.isArray(value) ? value : Object.values(value || {})
  const guests = toArray(currentRoom.value?.guests)
  const children = toArray(currentRoom.value?.children)

  if (guests.length === 0 && children.length === 0) {
    uiStore.showToast('Phòng hiện tại không có danh sách khách!', 'error')
    return
  }

  guestSelectionList.value = [
    ...guests.map(g => ({ guest_id: g.guest_id, full_name: g.full_name || 'Khách', is_child: false, selected: true })),
    ...children.map(c => ({
      guest_id: c.guest_id,
      full_name: c.full_name || (c.age_group === 'baby' ? 'Em bé' : 'Trẻ em'),
      is_child: true,
      selected: true,
    })),
  ]
  showGuestSelectModal.value = true
}

function confirmGuestSelection() {
  const selectedAdults = guestSelectionList.value.filter(g => g.selected && !g.is_child).map(g => g.guest_id)
  const selectedChildren = guestSelectionList.value.filter(g => g.selected && g.is_child).map(g => g.guest_id)

  if (selectedAdults.length === 0 && selectedChildren.length === 0) {
    uiStore.showToast('Vui lòng chọn ít nhất 1 khách để chuyển sang phòng gộp!', 'warning')
    return
  }

  showGuestSelectModal.value = false
  executeSubmit(selectedAdults, selectedChildren)
}

async function executeSubmit(selectedGuestIds, selectedChildIds = [], confirmExceedCapacity = false) {
  submitting.value = true
  try {
    const payload = {
      move_type: selectedMoveType.value,
      target_room_number: selectedTargetRoomNumber.value,
      reason: reason.value.trim(),
      selected_guest_ids: selectedGuestIds,
      selected_child_ids: selectedChildIds,
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
      const confirmed = await uiStore.confirm({
        title: 'Xác nhận vượt sức chứa',
        message: errorData.message
      })
      if (confirmed) {
        submitting.value = false
        executeSubmit(selectedGuestIds, selectedChildIds, true)
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
  <div v-if="show" @click="closeAllPopovers" class="modal-backdrop">
    <div @click.stop class="modal-container">

      <!-- Header -->
      <div class="modal-header">
        <div class="header-title">
          <svg class="w-4 h-4 text-white shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor"
            stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M2 4v16M2 8h18a2 2 0 0 1 2 2v10M2 17h20M6 8v9" />
          </svg>
          <span>CHUYỂN PHÒNG</span>
          <span v-if="currentRoom" class="text-xs text-blue-200 font-normal lowercase tracking-normal ml-1">
            (phòng {{ currentRoom.room_number }} - mã đk: {{ currentRoom.booking_code }})
          </span>
        </div>
        <div class="flex items-center gap-3">
          <button v-if="isRightPanelCollapsed" @click="toggleRightPanel" class="toggle-btn-header" type="button">
            <svg class="w-4 h-4 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
              <circle cx="12" cy="12" r="3" />
            </svg>
            <span>Chuyển sang phòng đang ở</span>
          </button>
          <button @click="emit('close')" class="close-btn" title="Đóng">
            ✕
          </button>
        </div>
      </div>

      <!-- Warning Alert Banner -->
      <div v-if="warningMsg"
        class="px-5 py-2 bg-amber-50 border-b border-amber-200 text-amber-900 text-xs font-semibold flex items-center gap-2">
        <svg class="w-4 h-4 shrink-0 text-amber-600" viewBox="0 0 24 24" fill="none" stroke="currentColor"
          stroke-width="2">
          <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" />
          <line x1="12" y1="9" x2="12" y2="13" />
          <line x1="12" y1="17" x2="12.01" y2="17" />
        </svg>
        <span>{{ warningMsg }}</span>
      </div>

      <!-- Body -->
      <div class="modal-body">
        <LoadingOverlay :show="loading" message="Đang tải danh sách phòng khả dụng..." />

        <!-- BẢNG BÊN TRÁI: DANH SÁCH PHÒNG TRỐNG -->
        <div class="table-section left-panel">
          <div class="section-title">
            <span>DANH SÁCH PHÒNG TRỐNG ({{ filteredAvailableRooms.length }})</span>
          </div>

          <div class="table-wrapper">
            <table class="tbl-left">
              <thead>
                <tr>
                  <th class="col-check"></th>

                  <!-- Cột Loại phòng -->
                  <th class="col-loai">Loại phòng</th>

                  <!-- Cột Dạng phòng -->
                  <th class="col-dang">Dạng phòng</th>

                  <!-- Cột Phòng (Search Popover Card positioned inside header cell) -->
                  <th class="col-phong relative">
                    <div class="flex items-center justify-between">
                      <span>Phòng</span>
                      <button @click.stop="toggleAvailSearchPopover" type="button" title="Tìm số phòng"
                        class="header-icon-btn" :class="{ 'active': filterAvailSearch || showAvailSearchPopover }">
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <circle cx="11" cy="11" r="8" />
                          <line x1="21" y1="21" x2="16.65" y2="16.65" />
                        </svg>
                      </button>
                    </div>

                    <!-- Search Popover Floating Card (Directly under column Phòng) -->
                    <div v-if="showAvailSearchPopover" @click.stop class="search-popover-box">
                      <input v-model="tempAvailSearch" @keyup.enter="applyAvailSearch" type="text"
                        placeholder="Search room" class="search-popover-input" autoFocus />
                      <div class="search-popover-actions">
                        <button @click="applyAvailSearch" type="button" class="btn-popover-search">
                          <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2.5">
                            <circle cx="11" cy="11" r="8" />
                            <line x1="21" y1="21" x2="16.65" y2="16.65" />
                          </svg>
                          <span>Search</span>
                        </button>
                        <button @click="resetAvailSearch" type="button" class="btn-popover-reset">
                          <span>Reset</span>
                        </button>
                      </div>
                    </div>
                  </th>

                  <!-- Cột Trạng thái -->
                  <th class="col-them">Trạng thái</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="filteredAvailableRooms.length === 0">
                  <td colspan="5" class="text-center py-8 text-slate-400 italic">
                    Không có phòng trống khả dụng
                  </td>
                </tr>
                <tr v-for="r in filteredAvailableRooms" :key="r.id" @click="selectAvailableRoom(r)"
                  class="cursor-pointer transition-colors"
                  :class="selectedMoveType === 'available' && selectedTargetRoomNumber === r.room_number ? 'bg-blue-50/80 font-semibold' : ''">
                  <td class="col-check">
                    <input type="radio" name="targetRoomGroup"
                      :checked="selectedMoveType === 'available' && selectedTargetRoomNumber === r.room_number"
                      class="cursor-pointer" />
                  </td>
                  <td class="col-loai" :title="r.room_class_name">{{ r.room_class_name }}</td>
                  <td class="col-dang">{{ r.room_form_name }}</td>
                  <td class="col-phong" style="font-weight: 600; color: #1e3a8a;">{{ r.room_number }}</td>
                  <td class="col-them">
                    <span :class="['status-badge', r.is_ready ? 'status-clean' : 'status-dirty']">
                      {{ r.status_label || '' }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- BẢNG BÊN PHẢI: DANH SÁCH PHÒNG ĐANG Ở -->
        <div class="table-section right-panel" :class="{ 'collapsed': isRightPanelCollapsed }">
          <div class="section-title">
            <span>PHÒNG ĐANG Ở ({{ filteredOccupiedRooms.length }})</span>
            <button class="toggle-btn" type="button" @click="toggleRightPanel">
              Thu gọn <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M15 3h6v6M14 10l7-7M9 21H3v-6M10 14l-7 7" />
              </svg>
            </button>
          </div>

          <div class="table-wrapper">
            <table class="tbl-right">
              <thead>
                <tr>
                  <th class="col-check"></th>

                  <!-- Cột Loại phòng -->
                  <th class="col-loai">Loại phòng</th>

                  <!-- Cột Dạng phòng -->
                  <th class="col-dang">Dạng phòng</th>

                  <!-- Cột Phòng (Search Popover Card positioned inside header cell) -->
                  <th class="col-phong relative">
                    <div class="flex items-center justify-between">
                      <span>Phòng</span>
                      <button @click.stop="toggleOccSearchPopover" type="button" title="Tìm số phòng"
                        class="header-icon-btn" :class="{ 'active': filterOccSearch || showOccSearchPopover }">
                        <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <circle cx="11" cy="11" r="8" />
                          <line x1="21" y1="21" x2="16.65" y2="16.65" />
                        </svg>
                      </button>
                    </div>

                    <!-- Search Popover Floating Card (Directly under column Phòng) -->
                    <div v-if="showOccSearchPopover" @click.stop class="search-popover-box">
                      <input v-model="tempOccSearch" @keyup.enter="applyOccSearch" type="text" placeholder="Search room"
                        class="search-popover-input" autoFocus />
                      <div class="search-popover-actions">
                        <button @click="applyOccSearch" type="button" class="btn-popover-search">
                          <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                            stroke-width="2.5">
                            <circle cx="11" cy="11" r="8" />
                            <line x1="21" y1="21" x2="16.65" y2="16.65" />
                          </svg>
                          <span>Search</span>
                        </button>
                        <button @click="resetOccSearch" type="button" class="btn-popover-reset">
                          <span>Reset</span>
                        </button>
                      </div>
                    </div>
                  </th>

                  <!-- Cột Giá phòng & Khách hàng -->
                  <th class="col-gia">Giá phòng</th>
                  <th class="col-them">Khách hàng</th>
                </tr>
              </thead>
              <tbody>
                <tr v-if="filteredOccupiedRooms.length === 0">
                  <td colspan="6" class="text-center py-8 text-slate-400 italic">
                    Không có phòng in-house thỏa điều kiện gộp
                  </td>
                </tr>
                <tr v-for="r in filteredOccupiedRooms" :key="r.booking_room_id" @click="selectOccupiedRoom(r)"
                  class="cursor-pointer transition-colors"
                  :class="selectedMoveType === 'merge' && selectedTargetRoomNumber === r.room_number ? 'bg-blue-50/80 font-semibold' : ''">
                  <td class="col-check">
                    <input type="radio" name="targetRoomGroup"
                      :checked="selectedMoveType === 'merge' && selectedTargetRoomNumber === r.room_number"
                      class="cursor-pointer" />
                  </td>
                  <td class="col-loai" :title="r.room_class_name">{{ r.room_class_name }}</td>
                  <td class="col-dang">{{ r.room_form_name }}</td>
                  <td class="col-phong" style="font-weight: 600; color: #1e3a8a;">{{ r.room_number }}</td>
                  <td class="col-gia">{{ formatCurrency(r.rate) }}</td>
                  <td class="col-them" :title="r.primary_guest_name">Inhouse: {{ r.primary_guest_name || 'Khách' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

      </div>

      <!-- Footer -->
      <div class="modal-footer">
        <div class="inputs-row">
          <!-- Checkbox Thay đổi giá -->
          <div class="input-group checkbox-group">
            <label
              style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-weight: 600; color: #1e293b;">
              <input type="checkbox" v-model="isChangeRate" /> Thay đổi giá
            </label>
          </div>

          <!-- Mã giá phòng -->
          <div class="input-group" :class="{ 'disabled': !isChangeRate }">
            <label>Mã giá phòng</label>
            <select v-model="selectedRateCode" :disabled="!isChangeRate">
              <option value="">Mã giá phòng</option>
              <option v-for="rc in activeRateCodes" :key="rc.Ma || rc.id || rc.code" :value="rc.Ma || rc.code">
                {{ rc.Ma || rc.code }}
              </option>
            </select>
          </div>

          <!-- Giá phòng -->
          <div class="input-group" :class="{ 'disabled': !isChangeRate }">
            <label>Giá phòng</label>
            <input v-model="formattedNewRate" :disabled="!isChangeRate" type="text" />
          </div>

          <!-- Thêm giường -->
          <div class="input-group" :class="{ 'disabled': !isChangeRate }">
            <label>Thêm giường</label>
            <input v-model.number="extraBedQty" :disabled="!isChangeRate" type="number" min="0" />
          </div>

          <!-- Giá thêm giường -->
          <div class="input-group" :class="{ 'disabled': !isChangeRate }">
            <label>Giá thêm giường</label>
            <input v-model="formattedExtraBedRate" :disabled="!isChangeRate" type="text" />
          </div>

          <!-- Trường lý do -->
          <div class="input-group wide">
            <label>Lý do</label>
            <input v-model="reason" type="text" placeholder="Nhập lý do chuyển phòng..." />
          </div>
        </div>

        <!-- Các nút hành động chính -->
        <div class="actions-row">
          <button @click="emit('close')" class="btn btn-gray" type="button">
            <svg class="w-4 h-4 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8" />
              <path d="M3 3v5h5" />
            </svg>
            <span>Quay lại</span>
          </button>
          <button @click="handleSubmit"
            :disabled="submitting || !selectedTargetRoomNumber || (selectedMoveType === 'available' && selectedTargetRoomObj && !isTargetRoomReady)"
            class="btn btn-blue" type="button">
            <svg v-if="submitting" class="w-4 h-4 animate-spin text-white" viewBox="0 0 24 24" fill="none"
              stroke="currentColor">
              <circle cx="12" cy="12" r="10" stroke-width="4" class="opacity-25" />
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
            </svg>
            <svg v-else class="w-4 h-4 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor"
              stroke-width="2">
              <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z" />
              <polyline points="17 21 17 13 7 13 7 21" />
              <polyline points="7 3 7 8 15 8" />
            </svg>
            <span>Lưu</span>
          </button>
        </div>
      </div>

    </div>

    <!-- Modal Chọn Khách (Sub-modal khi gộp phòng) -->
    <div v-if="showGuestSelectModal" @click.stop
      class="fixed inset-0 z-[10000] flex items-center justify-center bg-black/40 backdrop-blur-[2px] p-4 select-none">
      <div
        class="bg-white rounded-xl shadow-2xl w-full max-w-[440px] overflow-hidden border border-slate-200 flex flex-col text-xs">

        <div class="px-4 py-3 bg-[#1e3a8a] text-white flex items-center justify-between">
          <span class="font-bold text-sm text-white tracking-wide">CHỌN KHÁCH</span>
          <button @click="showGuestSelectModal = false" title="Đóng"
            class="text-white/90 hover:text-white bg-transparent border-none text-base font-bold cursor-pointer leading-none">
            ✕
          </button>
        </div>

        <div class="px-4 pt-3 text-[11px] font-semibold text-blue-700">Đã tick = chuyển khách sang phòng mới · Bỏ tick = giữ khách ở phòng cũ</div>
        <div class="p-4 space-y-4 max-h-[60vh] overflow-auto">
          <!-- Người lớn -->
          <div>
            <div class="font-semibold text-slate-700 mb-2">Người lớn</div>
            <div v-if="adultGuests.length === 0" class="text-slate-400 italic pl-1">Không có khách người lớn</div>
            <div class="space-y-2">
              <label v-for="g in adultGuests" :key="g.guest_id"
                class="flex items-center justify-between border border-slate-300 rounded-full px-4 py-2 hover:bg-slate-50 cursor-pointer transition-colors"
                :class="g.selected ? 'border-blue-400 bg-blue-50/50' : 'bg-white'">
                <span class="font-semibold text-slate-800 tracking-wide uppercase">{{ g.full_name }}</span>
                <input type="checkbox" v-model="g.selected"
                  class="rounded text-blue-600 focus:ring-blue-500 w-4 h-4 cursor-pointer" />
              </label>
            </div>
          </div>

          <!-- Trẻ em -->
          <div>
            <div class="font-semibold text-slate-700 mb-2">Trẻ em</div>
            <div v-if="childGuests.length === 0" class="text-slate-400 italic text-[11px] pl-1">Không có khách trẻ em
            </div>
            <div class="space-y-2">
              <label v-for="g in childGuests" :key="g.guest_id"
                class="flex items-center justify-between border border-slate-300 rounded-full px-4 py-2 hover:bg-slate-50 cursor-pointer transition-colors"
                :class="g.selected ? 'border-blue-400 bg-blue-50/50' : 'bg-white'">
                <span class="font-semibold text-slate-800 tracking-wide uppercase">{{ g.full_name }}</span>
                <input type="checkbox" v-model="g.selected"
                  class="rounded text-blue-600 focus:ring-blue-500 w-4 h-4 cursor-pointer" />
              </label>
            </div>
          </div>
        </div>

        <div class="px-4 py-3 bg-slate-50 border-t border-slate-200 flex items-center justify-end gap-2">
          <button @click="showGuestSelectModal = false" type="button" class="btn btn-gray">
            <span>Đóng</span>
          </button>

          <button @click="confirmGuestSelection" :disabled="submitting" type="button" class="btn btn-blue">
            <span>Lưu</span>
          </button>
        </div>

      </div>
    </div>
  </div>
</template>

<style scoped>
/* Nền mờ phía sau của Popup (Backdrop) */
.modal-backdrop {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background-color: rgba(15, 23, 42, 0.6);
  display: flex;
  justify-content: center;
  align-items: center;
  z-index: 9999;
  padding: 12px;
}

/* Khung Popup chính */
.modal-container {
  width: 95vw;
  max-width: 1550px;
  background-color: #ffffff;
  border-radius: 12px;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.15), 0 10px 10px -5px rgba(0, 0, 0, 0.1);
  overflow: hidden;
  border: 1px solid #e2e8f0;
  animation: modalFadeIn 0.3s ease-out;
}

@keyframes modalFadeIn {
  from {
    opacity: 0;
    transform: translateY(-20px);
  }

  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* Thanh tiêu đề màu xanh dương đậm */
.modal-header {
  background-color: #1e3a8a;
  color: white;
  padding: 14px 20px;
  font-weight: 600;
  font-size: 15px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  letter-spacing: 0.5px;
}

.header-title {
  display: flex;
  align-items: center;
  gap: 10px;
  text-transform: uppercase;
}

.close-btn {
  background: transparent;
  border: none;
  color: #ffffff;
  font-size: 20px;
  font-weight: 600;
  cursor: pointer;
  transition: opacity 0.2s;
  line-height: 1;
  opacity: 0.85;
}

.close-btn:hover {
  opacity: 1;
}

/* Header button "Chuyển sang phòng đang ở" */
.toggle-btn-header {
  background-color: rgba(255, 255, 255, 0.15) !important;
  color: #ffffff !important;
  border: none;
  padding: 6px 14px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  border-radius: 6px;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  gap: 6px;
}

.toggle-btn-header:hover {
  background-color: rgba(255, 255, 255, 0.25) !important;
  color: #ffffff !important;
}

/* Nội dung Popup */
.modal-body {
  display: flex;
  padding: 20px;
  gap: 20px;
  align-items: flex-start;
  transition: all 0.3s ease;
  position: relative;
  min-height: 310px;
}

/* Khung chứa các bảng dữ liệu */
.table-section {
  flex: 1;
  display: flex;
  flex-direction: column;
  transition: all 0.3s ease;
  min-width: 0;
}

/* Hiệu ứng ẩn/hiện mượt mà của bảng bên phải */
.table-section.right-panel {
  position: relative;
}

.table-section.right-panel.collapsed {
  flex: 0;
  width: 0;
  padding: 0;
  margin: 0;
  overflow: hidden;
  opacity: 0;
  pointer-events: none;
}

/* Tiêu đề bảng */
.section-title {
  font-size: 13px;
  font-weight: 700;
  margin-bottom: 10px;
  color: #1e3a8a;
  text-transform: uppercase;
  display: flex;
  justify-content: space-between;
  align-items: center;
  letter-spacing: 0.5px;
  height: 28px;
}

/* Nút ẩn trong bảng bên phải */
.table-section .toggle-btn {
  background-color: #eff6ff;
  color: #1d4ed8;
  border: 1px solid #bfdbfe;
  padding: 4px 10px;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  border-radius: 6px;
  transition: all 0.2s;
  display: flex;
  align-items: center;
  gap: 6px;
}

.table-section .toggle-btn:hover {
  background-color: #dbeafe;
}

/* Định dạng bảng và thanh cuộn */
.table-wrapper {
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  overflow-x: auto;
  overflow-y: auto;
  background-color: #ffffff;
  height: 280px;
  position: relative;
}

table {
  width: 100%;
  border-collapse: collapse;
  font-size: 13px;
  text-align: left;
  table-layout: fixed;
}

th,
td {
  padding: 10px 12px;
  border-bottom: 1px solid #e2e8f0;
  border-right: 1px solid #f1f5f9;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

th {
  background-color: #f8fafc;
  color: #475569;
  font-weight: 600;
  position: sticky;
  top: 0;
  z-index: 10;
  box-shadow: inset 0 -1px 0 #e2e8f0;
}

th.col-phong {
  overflow: visible !important;
  /* Giữ tiêu đề cột Phòng cố định khi cuộn; sticky vẫn là mốc đặt popup tìm kiếm. */
  position: sticky !important;
  top: 0;
  z-index: 30 !important;
}

tr:last-child td {
  border-bottom: none;
}

tr:hover {
  background-color: #f8fafc;
}

/* Radio button column styling - fixed 36px width, centered, no ellipsis dots */
th.col-check,
td.col-check {
  width: 36px !important;
  min-width: 36px !important;
  max-width: 36px !important;
  padding: 8px 0 !important;
  text-align: center !important;
  overflow: visible !important;
  text-overflow: clip !important;
  white-space: nowrap !important;
}

/* Header icon button - no border, transparent background */
.header-icon-btn {
  background: transparent !important;
  color: #64748b;
  border: none !important;
  padding: 2px 4px;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.2s;
}

.header-icon-btn:hover,
.header-icon-btn.active {
  color: #1d4ed8;
  background-color: rgba(226, 232, 240, 0.6) !important;
  border-radius: 4px;
}

/* Search Popover Card (Directly under column Phòng header cell) */
.search-popover-box {
  position: absolute;
  top: calc(100% + 4px);
  right: 0;
  z-index: 9999;
  background-color: #ffffff;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.2), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
  padding: 10px;
  width: 220px;
  box-sizing: border-box;
  font-weight: normal;
  text-transform: none;
  white-space: normal;
}

.search-popover-input {
  width: 100%;
  padding: 7px 10px;
  font-size: 13px;
  border: 1px solid #7bc4ff;
  border-radius: 4px;
  outline: none;
  box-shadow: 0 0 0 2px rgba(123, 196, 255, 0.25);
  margin-bottom: 10px;
  box-sizing: border-box;
  color: #1e293b;
  background-color: #ffffff;
}

.search-popover-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.btn-popover-search {
  background-color: #7bc4ff;
  color: #ffffff;
  border: none;
  border-radius: 4px;
  padding: 6px 14px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 4px;
  transition: background-color 0.2s;
}

.btn-popover-search:hover {
  background-color: #60a5fa;
}

.btn-popover-reset {
  background-color: #ffffff;
  color: #334155;
  border: 1px solid #cbd5e1;
  border-radius: 4px;
  padding: 6px 16px;
  font-size: 13px;
  font-weight: 500;
  cursor: pointer;
  transition: background-color 0.2s;
}

.btn-popover-reset:hover {
  background-color: #f1f5f9;
}

/* Tỉ lệ cột cho bảng Trống (5 cột) */
.tbl-left .col-check {
  width: 36px;
}

.tbl-left .col-loai {
  width: 34%;
}

.tbl-left .col-dang {
  width: 18%;
}

.tbl-left .col-phong {
  width: 16%;
}

.tbl-left .col-them {
  width: 26%;
}

/* Tỉ lệ cột cho bảng Đang ở (6 cột) */
.tbl-right .col-check {
  width: 36px;
}

.tbl-right .col-loai {
  width: 28%;
}

.tbl-right .col-dang {
  width: 15%;
}

.tbl-right .col-phong {
  width: 14%;
}

.tbl-right .col-gia {
  width: 17%;
}

.tbl-right .col-them {
  width: 20%;
}

input[type="checkbox"],
input[type="radio"] {
  width: 15px;
  height: 15px;
  border-radius: 4px;
  border: 1px solid #cbd5e1;
  cursor: pointer;
}

/* Phần nhập liệu bên dưới */
.modal-footer {
  padding: 20px;
  border-top: 1px solid #e2e8f0;
  background-color: #ffffff;
}

.inputs-row {
  display: flex;
  gap: 15px;
  margin-bottom: 20px;
  flex-wrap: wrap;
}

.input-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
  flex: 1;
  min-width: 140px;
  transition: opacity 0.2s ease;
}

.input-group.disabled {
  opacity: 0.5;
}

.input-group.checkbox-group {
  flex: 0 1 auto;
  min-width: unset;
  justify-content: flex-end;
  padding-bottom: 8px;
}

.input-group.wide {
  flex: 2;
  min-width: 250px;
}

.input-group label {
  font-size: 12px;
  font-weight: 600;
  color: #64748b;
}

.input-group input,
.input-group select {
  padding: 9px 12px;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  font-size: 13px;
  color: #1e293b;
  background-color: #ffffff;
  outline: none;
  transition: all 0.2s;
}

.input-group input:disabled,
.input-group select:disabled {
  background-color: #f8fafc;
  color: #94a3b8;
  cursor: not-allowed;
  border-color: #e2e8f0;
}

.input-group input:focus:not(:disabled),
.input-group select:focus:not(:disabled) {
  border-color: #3b82f6;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

/* Nút thao tác ở chân trang */
.actions-row {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}

.btn {
  padding: 9px 22px;
  border: none;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 8px;
  transition: all 0.2s;
}

.btn-blue {
  background-color: #2563eb !important;
  color: #ffffff !important;
  box-shadow: 0 2px 4px rgba(37, 99, 235, 0.2);
}

.btn-blue:hover:not(:disabled) {
  background-color: #1d4ed8 !important;
}

.btn-blue:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

.btn-gray {
  background-color: #f1f5f9 !important;
  color: #94a3b8 !important;
  border: 1px solid #e2e8f0 !important;
}

.btn-gray:hover {
  background-color: #e2e8f0 !important;
  color: #64748b !important;
}

/* Badge trạng thái */
.status-badge {
  padding: 3px 8px;
  border-radius: 12px;
  font-size: 11px;
  font-weight: 600;
  display: inline-block;
}

.status-dirty {
  background-color: #fee2e2;
  color: #991b1b;
}

.status-clean {
  background-color: #dcfce7;
  color: #166534;
}
</style>
