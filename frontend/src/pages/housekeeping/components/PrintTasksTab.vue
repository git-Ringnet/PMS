<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'
import { useRoute } from 'vue-router'
import { useHkStore, GROUP_COLORS } from '@/stores/hk-store'
import { compareRooms, getStatusSymbols as buildStatusSymbols, buildWorksheetSheets, buildSupervisorRooms } from '@/utils/hk-room-status'
import { useRoomStore } from '@/stores/room-store'
import {
  CalendarDays, Clock3, Search, Filter, ChevronDown,
  Users, Printer, UserCog, Plus, X, Pencil, Loader2,
  GripVertical, CheckSquare, Square, Minus
} from '@lucide/vue'

import { useUiStore } from '@/stores/ui-store'
import LoadingOverlay from '@/components/LoadingOverlay.vue'
import SingleDatePicker from '@/components/SingleDatePicker.vue'
import RoomIcon from '@/components/RoomIcon.vue'
import { fetchSystemDate } from '@/services/booking-service'
import echo from '@/services/echo'

const props = defineProps({
  initialRoomIds: {
    type: Array,
    default: () => [],
  },
  initialDate: {
    type: String,
    default: '',
  },
})

const hkStore = useHkStore()
const roomStore = useRoomStore()
const uiStore = useUiStore()
const route = useRoute()

// Filter panel click-outside
const filterWrapRef = ref(null)
function onDocClick(e) {
  if (filterWrapRef.value && !filterWrapRef.value.contains(e.target)) {
    showFilterPanel.value = false
  }
  if (staffDropdownRef.value && !staffDropdownRef.value.contains(e.target)) {
    showStaffDropdown.value = false
  }
}

// ── Ngày & Ca ────────────────────────────────────────────────
const today = new Date()
const pad = n => String(n).padStart(2, '0')
const workDate = ref(`${today.getFullYear()}-${pad(today.getMonth() + 1)}-${pad(today.getDate())}`)
const selectedShiftId = ref(null)

// Thứ tự ca chuẩn hóa: Ca sáng -> Ca chiều -> Ca tối
const sortedShifts = computed(() => {
  const getShiftOrder = (shift) => {
    const name = String(shift.name || '').trim().toLowerCase()
    if (name === '1' || name === 'sáng' || name === 'morning') return 1
    if (name === '2' || name === 'chiều' || name === 'afternoon') return 2
    if (name === '0' || name === 'tối' || name === 'night') return 3
    return 4
  }
  return [...(hkStore.shifts || [])].sort((a, b) => getShiftOrder(a) - getShiftOrder(b))
})

function getShiftName(shift) {
  const name = String(shift.name).trim().toLowerCase()
  if (name === '1' || name === 'sáng' || name === 'morning') return 'Ca sáng'
  if (name === '2' || name === 'chiều' || name === 'afternoon') return 'Ca chiều'
  if (name === '0' || name === 'tối' || name === 'night') return 'Ca tối'
  return `Ca ${shift.name}`
}

function getShiftShortName(shift) {
  const name = String(shift.name).trim().toLowerCase()
  if (name === '1' || name === 'sáng' || name === 'morning') return 'sáng'
  if (name === '2' || name === 'chiều' || name === 'afternoon') return 'chiều'
  if (name === '0' || name === 'tối' || name === 'night') return 'tối'
  return shift.name
}

// ── Filter phòng ─────────────────────────────────────────────
const searchQ = ref('')
const filterHk = ref('all')
const filterBook = ref('all')
const activeFloors = ref(new Set())
const showFilterPanel = ref(false)

// Phễu lọc chuẩn theo FIX FE Row 62 & Row 63 Note 19/10
const hkFilterOptions = [
  { key: 'all', label: 'Tất cả', icon: null },
  { key: 'clean', label: 'Phòng sạch', icon: 'clean', iconClass: 'text-emerald-500' },
  { key: 'dirty', label: 'Phòng bẩn', icon: 'dirty', iconClass: 'text-amber-600' },
  { key: 'cleaning', label: 'Phòng lau dọn', icon: 'checkout', iconClass: 'text-cyan-600' },
  { key: 'ready', label: 'Phòng sẵn sàng', icon: 'available', iconClass: 'text-emerald-600' },
  { key: 'ooo', label: 'Phòng OOO', icon: 'maintenance', iconClass: 'text-rose-500' },
  { key: 'oos', label: 'Phòng OOS', icon: 'maintenance', iconClass: 'text-amber-500' },
]

const bookingFilterOptions = [
  { key: 'all', label: 'Tất cả' },
  { key: 'reserved', label: 'Check - in' },
  { key: 'checkout', label: 'Check - out' },
  { key: 'occupied', label: 'Stay - over' },
]

// ── Chọn phòng ───────────────────────────────────────────────
const selectedRoomIds = ref(new Set())
const selectedStaffIds = ref(new Set())
const showStaffDropdown = ref(false)
const staffDropdownRef = ref(null)

const dropdownButtonLabel = computed(() => {
  const count = selectedStaffIds.value.size
  if (count === 0) return '-- Chọn nhân viên làm phòng --'
  const names = hkStore.availableStaff
    .filter(s => selectedStaffIds.value.has(s.id))
    .map(s => s.name)
  if (count === 1) return names[0] || '1 nhân viên'
  if (count <= 2 && names.join(', ').length <= 22) {
    return names.join(', ')
  }
  return `Đã chọn ${count} nhân viên`
})

const selectedStaffSummaryTitle = computed(() => {
  if (selectedStaffIds.value.size === 0) return ''
  return hkStore.availableStaff
    .filter(s => selectedStaffIds.value.has(s.id))
    .map(s => s.name)
    .join(', ')
})

// ── Drag & drop ──────────────────────────────────────────────
let dragRoomId = null
let dragFromGroupId = null

// ── Kéo thả (Draggable) Modals ──────────────────────────────
const staffModalPos = ref({ x: 0, y: 0 })
const editGroupModalPos = ref({ x: 0, y: 0 })

function createDragHandler(posRef) {
  let dragStart = { x: 0, y: 0 }
  let isDragging = false
  let rafId = null

  function onMouseMove(e) {
    if (!isDragging) return
    if (rafId) return
    rafId = requestAnimationFrame(() => {
      posRef.value.x = e.clientX - dragStart.x
      posRef.value.y = e.clientY - dragStart.y
      rafId = null
    })
  }

  function onMouseUp() {
    isDragging = false
    if (rafId) {
      cancelAnimationFrame(rafId)
      rafId = null
    }
    document.removeEventListener('mousemove', onMouseMove)
    document.removeEventListener('mouseup', onMouseUp)
  }

  return function startDrag(e) {
    const ignoreTags = ['BUTTON', 'INPUT', 'SELECT', 'TEXTAREA', 'A', 'LABEL', 'SVG', 'PATH']
    if (ignoreTags.includes(e.target.tagName) || e.target.closest('button, input, select, textarea, a, label')) return
    
    isDragging = true
    dragStart.x = e.clientX - posRef.value.x
    dragStart.y = e.clientY - posRef.value.y
    
    document.addEventListener('mousemove', onMouseMove)
    document.addEventListener('mouseup', onMouseUp)
  }
}

const startDragStaffModal = createDragHandler(staffModalPos)
const startDragEditGroupModal = createDragHandler(editGroupModalPos)

// ── Modal quản lý NV ─────────────────────────────────────────
const showStaffModal = ref(false)
const staffTab = ref('active') // active | hidden
const newStaffName = ref('')
const staffSearchQ = ref('')

// ── Modal chỉnh sửa nhóm ─────────────────────────────────────
const showEditGroupModal = ref(false)
const editingGroupId = ref(null)

watch(showStaffModal, (v) => { if (!v) staffModalPos.value = { x: 0, y: 0 } })
watch(showEditGroupModal, (v) => { if (!v) editGroupModalPos.value = { x: 0, y: 0 } })



// ─────────────────────────────────────────────────────────────
// Computed: danh sách phòng với real-time status
// ─────────────────────────────────────────────────────────────
const allRooms = computed(() => {
  return roomStore.rooms
    .filter(r => !r.is_internal && !String(r.room_number || '').startsWith('0') && r.room_class?.is_active !== false)
    .map(r => {
      let displayBookingStatus = r.booking_status || ''
      if (r.booking_status) {
        const dateStr = workDate.value
        const arrStr = r.arrival_date ? r.arrival_date.substring(0, 10) : ''
        const depStr = r.departure_date ? r.departure_date.substring(0, 10) : ''
        
        if (r.booking_status === 'occupied') {
          displayBookingStatus = 'occupied'
        } else if (depStr === dateStr) {
          displayBookingStatus = 'checkout'
        } else if (arrStr === dateStr) {
          displayBookingStatus = 'reserved'
        } else {
          displayBookingStatus = 'occupied'
        }
      }
      return {
        id: Number(r.id),
        room_number: r.room_number,
        floor: r.floor,
        room_type: r.room_class?.name || r.room_type || '',
        room_status_code: r.room_status_code || 'vacant_dirty',
        lock_type: r.lock_type || '',
        is_ooo: Boolean(r.is_ooo || r.room_status_code === 'ooo' || r.room_status_code === 'occupied_ooo' || (r.lock_type === 'OOO' && r.room_status_code !== 'housekeeping')),
        is_oos: Boolean(r.is_oos || r.room_status_code === 'oos' || (r.lock_type === 'OOS' && r.room_status_code !== 'housekeeping')),
        booking_status: r.booking_status || '',
        displayBookingStatus,
        isBackToBack: !!r.is_back_to_back,
        extraBedQty: Number(r.extra_bed_qty || 0),
      }
    })
    .sort(compareRooms)
})

const roomById = computed(() => new Map(allRooms.value.map(r => [r.id, r])))

const floors = computed(() => [...new Set(allRooms.value.map(r => r.floor))].sort((a, b) => Number(a) - Number(b)))

const filteredRooms = computed(() => {
  const q = searchQ.value.trim().toLowerCase()
  return allRooms.value.filter(r => {
    if (filterHk.value !== 'all') {
      const code = String(r.room_status_code || '').toLowerCase()
      if (filterHk.value === 'clean') {
        const isClean = code === 'vacant_clean' || code === 'occupied_clean' || code === 'clean'
        if (!isClean) return false
      } else if (filterHk.value === 'dirty') {
        const isDirty = code === 'vacant_dirty' || code === 'occupied_dirty' || code === 'dirty'
        if (!isDirty) return false
      } else if (filterHk.value === 'cleaning') {
        const isCleaning = code === 'housekeeping' || code === 'turndown' || code === 'cleaning'
        if (!isCleaning) return false
      } else if (filterHk.value === 'ready') {
        const isReady = code === 'vacant_ready' || code === 'occupied_ready' || code === 'ready'
        if (!isReady) return false
      } else if (filterHk.value === 'ooo') {
        if (!r.is_ooo) return false
      } else if (filterHk.value === 'oos') {
        if (!r.is_oos) return false
      }
    }
    if (filterBook.value !== 'all' && r.displayBookingStatus !== filterBook.value) return false
    if (activeFloors.value.size > 0 && !activeFloors.value.has(r.floor)) return false
    if (q && !String(r.room_number).toLowerCase().includes(q) && !r.room_type.toLowerCase().includes(q)) return false
    return true
  })
})

const roomsByFloor = computed(() => {
  const map = {}
  filteredRooms.value.forEach(r => {
    if (!map[r.floor]) map[r.floor] = []
    map[r.floor].push(r)
  })
  return map
})

const sortedFloorNumbers = computed(() => {
  return Object.keys(roomsByFloor.value).map(Number).sort((a, b) => a - b)
})

// ─────────────────────────────────────────────────────────────
// Computed: nhân viên khả dụng (chưa phân công trong ca)
// ─────────────────────────────────────────────────────────────
const availableStaffForPicker = computed(() => hkStore.availableStaff)

const editingGroupAvailableStaff = computed(() => {
  if (!editingGroupId.value) return []
  const editGroup = hkStore.groups.find(g => g.id === editingGroupId.value)
  const currentStaffIds = new Set(editGroup?.staff_list.map(s => s.staff_id) || [])
  const usedInOtherGroups = new Set(
    hkStore.groups
      .filter(g => g.id !== editingGroupId.value)
      .flatMap(g => g.staff_list.map(s => s.staff_id))
  )
  return hkStore.staff.filter(s => !usedInOtherGroups.has(s.id) && !currentStaffIds.has(s.id))
})

const filteredStaffInModal = computed(() => {
  const list = staffTab.value === 'hidden'
    ? hkStore.staffAll.filter(x => x.is_hidden)
    : hkStore.staff
  const q = staffSearchQ.value.trim().toLowerCase()
  if (!q) return list
  return list.filter(s => s.name && s.name.toLowerCase().includes(q))
})

// ── Xử lý phím tắt ESC cho các Modal ────────────────────────
function handleGlobalKeydown(e) {
  if (e.key === 'Escape' || e.key === 'Esc') {
    if (showStaffModal.value) {
      showStaffModal.value = false
      return
    }
    if (showEditGroupModal.value) {
      showEditGroupModal.value = false
      return
    }
    if (showFilterPanel.value) {
      showFilterPanel.value = false
      return
    }
  }
}

// ─────────────────────────────────────────────────────────────
// Load
// ─────────────────────────────────────────────────────────────
onMounted(async () => {
  document.addEventListener('click', onDocClick)
  window.addEventListener('keydown', handleGlobalKeydown)
  
  // Tải ngày hệ thống của khách sạn trước
  try {
    const res = await fetchSystemDate()
    const sysDate = res.data?.data?.system_date || res.data?.system_date
    if (sysDate && !route.query.date && !props.initialDate) {
      workDate.value = sysDate
    }
  } catch (err) {
    console.error('Lỗi khi tải ngày hệ thống:', err)
  }
  if (route.query.date) {
    workDate.value = String(route.query.date)
  } else if (props.initialDate) {
    workDate.value = props.initialDate
  }

  await Promise.all([
    roomStore.fetchRooms?.() || Promise.resolve(),
    hkStore.loadShifts(),
    hkStore.loadStaff(),
    hkStore.loadHkConfig(),
  ])
  if (sortedShifts.value.length > 0) {
    selectedShiftId.value = sortedShifts.value[0].id
    // Load assignment ngay sau khi có cả ngày + ca
    await hkStore.loadAssignment(workDate.value, selectedShiftId.value)
  }

  const preselectedRoomIds = props.initialRoomIds.length
    ? props.initialRoomIds.map(id => Number(id)).filter(id => Number.isInteger(id) && id > 0)
    : String(route.query.roomIds || '')
      .split(',')
      .map(id => Number(id))
      .filter(id => Number.isInteger(id) && id > 0)
  if (preselectedRoomIds.length) {
    const availableIds = new Set(allRooms.value.map(room => Number(room.id)))
    selectedRoomIds.value = new Set(preselectedRoomIds.filter(id => availableIds.has(id)))
  }

  // Lắng nghe sự kiện realtime qua Laravel Echo để đồng bộ trạng thái phòng
  if (echo) {
    echo.channel('pms-channel')
      .listen('.room.status.updated', async () => {
        await roomStore.fetchRooms({ silent: true })
        if (selectedShiftId.value) {
          await hkStore.loadAssignment(workDate.value, selectedShiftId.value)
        }
      })
      .listen('.reservation.updated', async () => {
        await roomStore.fetchRooms({ silent: true })
        if (selectedShiftId.value) {
          await hkStore.loadAssignment(workDate.value, selectedShiftId.value)
        }
      })
  }
})

onUnmounted(() => {
  document.removeEventListener('click', onDocClick)
  window.removeEventListener('keydown', handleGlobalKeydown)
  if (echo) {
    echo.channel('pms-channel').stopListening('.room.status.updated')
    echo.channel('pms-channel').stopListening('.reservation.updated')
  }
})

watch(sortedShifts, (newShifts) => {
  if (newShifts && newShifts.length > 0 && !selectedShiftId.value) {
    selectedShiftId.value = newShifts[0].id
  }
}, { immediate: true })

watch([workDate, selectedShiftId], async ([d, s]) => {
  if (d && s) {
    await hkStore.loadAssignment(d, s)
  }
}, { immediate: false })


// ─────────────────────────────────────────────────────────────
// Helpers: floor selection
// ─────────────────────────────────────────────────────────────
function toggleFloor(floor) {
  if (activeFloors.value.has(floor)) activeFloors.value.delete(floor)
  else activeFloors.value.add(floor)
  activeFloors.value = new Set(activeFloors.value) // trigger reactivity
}
function clearFloors() { activeFloors.value = new Set() }

// ─────────────────────────────────────────────────────────────
// Chọn phòng
// ─────────────────────────────────────────────────────────────
function toggleRoom(id) {
  const s = new Set(selectedRoomIds.value)
  if (s.has(id)) s.delete(id); else s.add(id)
  selectedRoomIds.value = s
}

function handleRoomItemClick(id, event) {
  if (event?.target && event.target.tagName === 'INPUT') {
    return
  }
  toggleRoom(id)
}

function handleRoomCheckboxChange(id, event) {
  const checked = event.target.checked
  const s = new Set(selectedRoomIds.value)
  if (checked) {
    s.add(id)
  } else {
    s.delete(id)
  }
  selectedRoomIds.value = s
}

function toggleFloorRooms(floor, checked) {
  const s = new Set(selectedRoomIds.value)
  const floorRooms = filteredRooms.value.filter(r => r.floor === floor)
  floorRooms.forEach(r => checked ? s.add(r.id) : s.delete(r.id))
  selectedRoomIds.value = s
}

function isFloorAllSelected(floor) {
  const floorRooms = filteredRooms.value.filter(r => r.floor === floor)
  return floorRooms.length > 0 && floorRooms.every(r => selectedRoomIds.value.has(r.id))
}
function isFloorPartialSelected(floor) {
  const floorRooms = filteredRooms.value.filter(r => r.floor === floor)
  return floorRooms.some(r => selectedRoomIds.value.has(r.id)) && !isFloorAllSelected(floor)
}

function toggleSelectAll(checked) {
  const s = new Set(selectedRoomIds.value)
  filteredRooms.value.forEach(r => checked ? s.add(r.id) : s.delete(r.id))
  selectedRoomIds.value = s
}
const isAllSelected = computed(() => filteredRooms.value.length > 0 && filteredRooms.value.every(r => selectedRoomIds.value.has(r.id)))
const isPartialSelected = computed(() => filteredRooms.value.some(r => selectedRoomIds.value.has(r.id)) && !isAllSelected.value)

// ─────────────────────────────────────────────────────────────
// Phân công
// ─────────────────────────────────────────────────────────────
const canAssign = computed(() => selectedRoomIds.value.size > 0 && selectedStaffIds.value.size > 0)

function toggleStaffPicker(staffId) {
  const s = new Set(selectedStaffIds.value)
  if (s.has(staffId)) s.delete(staffId); else s.add(staffId)
  selectedStaffIds.value = s
}

async function doAssign() {
  if (!canAssign.value || !selectedShiftId.value) return
  // Tạo snapshot
  const roomSnapshots = {}
  selectedRoomIds.value.forEach(roomId => {
    const r = allRooms.value.find(x => x.id === roomId)
    if (r) {
      roomSnapshots[roomId] = {
        room_status_snapshot:    r.room_status_code,
        booking_status_snapshot: r.displayBookingStatus,
      }
    }
  })
  try {
    await hkStore.assignRooms({
      date:          workDate.value,
      shiftId:       selectedShiftId.value,
      staffIds:      [...selectedStaffIds.value],
      roomIds:       [...selectedRoomIds.value],
      roomSnapshots,
    })
    uiStore.showToast('Phân công phòng thành công!', 'success')
    selectedRoomIds.value = new Set()
    selectedStaffIds.value = new Set()
  } catch (e) {
    console.error(e)
    uiStore.showToast(e.response?.data?.message || 'Phân công thất bại', 'error')
  }
}

// ─────────────────────────────────────────────────────────────
// Drag & Drop
// ─────────────────────────────────────────────────────────────
function onDragStartFromList(e, roomId) {
  dragRoomId = roomId
  dragFromGroupId = null
  e.dataTransfer.effectAllowed = 'move'
  e.dataTransfer.setData('text/plain', String(roomId))
}

function onDragStartFromGroup(e, roomId, groupId) {
  dragRoomId = roomId
  dragFromGroupId = groupId
  e.dataTransfer.effectAllowed = 'move'
  e.dataTransfer.setData('text/plain', String(roomId))
  e.stopPropagation()
}

function onDragOver(e) { e.preventDefault(); e.dataTransfer.dropEffect = 'move' }

async function onDropToGroup(e, groupId) {
  e.preventDefault()
  if (!dragRoomId || dragFromGroupId === groupId) return
  const rid = dragRoomId
  dragRoomId = null; dragFromGroupId = null

  const room = allRooms.value.find(r => r.id === rid)
  try {
    await hkStore.moveRoomToGroup({
      date: workDate.value,
      shiftId: selectedShiftId.value,
      groupId,
      roomId: rid,
      roomSnapshot: room ? {
        room_status_snapshot:    room.room_status_code,
        booking_status_snapshot: room.booking_status,
      } : {},
    })
    uiStore.showToast('Chuyển phòng phân công thành công!', 'success')
  } catch (e) {
    console.error(e)
    uiStore.showToast(e.response?.data?.message || 'Không thể chuyển phòng', 'error')
  }
}

// ─────────────────────────────────────────────────────────────
// Xóa nhóm / phòng
// ─────────────────────────────────────────────────────────────
async function confirmRemoveGroup(groupId) {
  const confirmed = await uiStore.confirm({
    title: 'Xác nhận xóa',
    message: 'Bạn có chắc chắn muốn xóa nhóm này không?',
    confirmText: 'Có',
    cancelText: 'Không'
  })
  if (confirmed) {
    try {
      await hkStore.removeGroup({ date: workDate.value, shiftId: selectedShiftId.value, groupId })
      uiStore.showToast('Xóa nhóm phân công thành công!', 'success')
    } catch (e) {
      console.error(e)
      uiStore.showToast(e.response?.data?.message || 'Không thể xóa nhóm', 'error')
    }
  }
}

async function confirmRemoveRoom(groupId, roomId) {
  const confirmed = await uiStore.confirm({
    title: 'Xác nhận gỡ phòng',
    message: 'Bạn có chắc chắn muốn bỏ phòng này khỏi nhóm?',
    confirmText: 'Có',
    cancelText: 'Không'
  })
  if (confirmed) {
    try {
      await hkStore.removeRoomFromGroup({ date: workDate.value, shiftId: selectedShiftId.value, groupId, roomId })
      uiStore.showToast('Đã gỡ phòng khỏi nhóm!', 'success')
    } catch (e) {
      console.error(e)
      uiStore.showToast(e.response?.data?.message || 'Không thể gỡ phòng', 'error')
    }
  }
}

// ─────────────────────────────────────────────────────────────
// Edit group staff
// ─────────────────────────────────────────────────────────────
function openEditGroup(groupId) {
  editingGroupId.value = groupId
  showEditGroupModal.value = true
}

const editingGroup = computed(() => hkStore.groups.find(g => g.id === editingGroupId.value))

async function addStaffToGroup(staffId) {
  const g = editingGroup.value
  if (!g) return
  const newIds = [...g.staff_list.map(s => s.staff_id), staffId]
  try {
    await hkStore.updateGroupStaff({ date: workDate.value, shiftId: selectedShiftId.value, groupId: g.id, staffIds: newIds })
    uiStore.showToast('Đã thêm nhân viên vào nhóm!', 'success')
  } catch (e) {
    console.error(e)
    uiStore.showToast(e.response?.data?.message || 'Không thể thêm nhân viên', 'error')
  }
}

async function removeStaffFromGroup(staffId) {
  const g = editingGroup.value
  if (!g) return
  const newIds = g.staff_list.map(s => s.staff_id).filter(id => id !== staffId)
  try {
    await hkStore.updateGroupStaff({ date: workDate.value, shiftId: selectedShiftId.value, groupId: g.id, staffIds: newIds })
    uiStore.showToast('Đã xóa nhân viên khỏi nhóm!', 'success')
  } catch (e) {
    console.error(e)
    uiStore.showToast(e.response?.data?.message || 'Không thể xóa nhân viên', 'error')
  }
}

// ─────────────────────────────────────────────────────────────
// Staff management
// ─────────────────────────────────────────────────────────────
async function submitAddStaff() {
  if (!newStaffName.value.trim()) return
  try {
    await hkStore.addStaff(newStaffName.value.trim())
    uiStore.showToast('Thêm nhân viên mới thành công!', 'success')
    newStaffName.value = ''
  } catch (e) {
    console.error(e)
    uiStore.showToast(e.response?.data?.message || 'Không thể thêm nhân viên', 'error')
  }
}

async function doHideStaff(staffId, hide) {
  try {
    await hkStore.toggleHideStaff(staffId, hide)
    uiStore.showToast(hide ? 'Đã ẩn nhân viên!' : 'Đã hiện nhân viên!', 'success')
  } catch (e) {
    console.error(e)
    uiStore.showToast(e.response?.data?.message || 'Thao tác thất bại', 'error')
  }
}

async function doDeleteStaff(staffId) {
  const confirmed = await uiStore.confirm({
    title: 'Xác nhận xóa',
    message: 'Bạn có chắc chắn muốn xóa nhân viên này không?',
    confirmText: 'Có',
    cancelText: 'Không'
  })
  if (confirmed) {
    try {
      await hkStore.deleteStaff(staffId)
      // Xóa khỏi selection nếu đang được chọn
      if (selectedStaffIds.value.has(staffId)) {
        const s = new Set(selectedStaffIds.value)
        s.delete(staffId)
        selectedStaffIds.value = s
      }
      uiStore.showToast('Xóa nhân viên thành công!', 'success')
    } catch (e) {
      console.error(e)
      uiStore.showToast(e.response?.data?.message || 'Không thể xóa nhân viên', 'error')
    }
  }
}

// ─────────────────────────────────────────────────────────────
// Helpers: ký hiệu & màu
// ─────────────────────────────────────────────────────────────
function getHkCodeBadge(statusCode) {
  return hkStore.activeSymbols.hk[statusCode]?.code || statusCode || ''
}
function getHkColor(statusCode) {
  return hkStore.activeSymbols.hk[statusCode]?.color || '#94a3b8'
}
// Dòng 357: ký hiệu tình trạng phòng (back-to-back: CI, <buồng>, CO; kèm EB khi có giường phụ)
function getStatusSymbols(roomStatusCode, bookingStatus, liveRoom = null) {
  return buildStatusSymbols(hkStore.activeSymbols, roomStatusCode, bookingStatus, liveRoom)
}

function getStatusText(roomStatusCode, bookingStatus, liveRoom = null) {
  return getStatusSymbols(roomStatusCode, bookingStatus, liveRoom).map(s => s.code).join(', ')
}

function getGroupRoomSymbols(r) {
  return getStatusSymbols(r.room_status_snapshot, r.booking_status_snapshot, roomById.value.get(Number(r.room_id)))
}

/** Phòng của nhóm phân công theo thứ tự tầng. */
function sortedGroupRooms(group) {
  return [...group.rooms].sort((a, b) => compareRooms(
    { floor: roomById.value.get(Number(a.room_id))?.floor, room_number: a.room_number },
    { floor: roomById.value.get(Number(b.room_id))?.floor, room_number: b.room_number },
  ))
}
function getGroupColor(idx) {
  return GROUP_COLORS[idx % GROUP_COLORS.length]
}
function getInitials(name) {
  return (name || '').split(' ').slice(-2).map(w => w[0]).join('').toUpperCase()
}

// ─────────────────────────────────────────────────────────────
// PRINT
// ─────────────────────────────────────────────────────────────
const printMode = ref('group') // 'group' | 'room'
const selectedGroupsToPrint = ref(new Set())

function toggleGroupPrint(gid) {
  const s = new Set(selectedGroupsToPrint.value)
  if (s.has(gid)) s.delete(gid); else s.add(gid)
  selectedGroupsToPrint.value = s
}

function doPrint() {
  if (printMode.value === 'group') printByGroup()
  else printByRoom()
}

function getEnglishShiftName(shift) {
  if (!shift) return 'WORK'
  const name = String(shift.name).trim().toLowerCase()
  if (name === '1' || name === 'sáng' || name === 'morning') return 'MORNING'
  if (name === '2' || name === 'chiều' || name === 'afternoon') return 'AFTERNOON'
  if (name === '0' || name === 'tối' || name === 'night') return 'NIGHT'
  return name.toUpperCase()
}

function buildWorksheetLegend() {
  const hkItems = Object.entries(hkStore.activeSymbols.hk).map(([, v]) => `${v.code}: ${v.label}`)
  const bkItems = Object.entries(hkStore.activeSymbols.booking).filter(([, v]) => v.code).map(([, v]) => `${v.code}: ${v.label}`)
  return [...bkItems, ...hkItems]
}

// Dòng 357: mở bản in ở tab mới để kiểm tra trước, bấm nút In trên thanh công cụ mới in
function openPrintPreview(title, bodyHtml, extraStyle = '') {
  const win = window.open('', '_blank')
  if (!win) {
    uiStore.showToast('Trình duyệt đang chặn mở tab mới. Vui lòng cho phép pop-up để xem bản in.', 'warning')
    return
  }
  win.document.write(`<!DOCTYPE html><html><head><meta charset="utf-8"><title>${title}</title>
    <style>
      body { margin:0; background:#e2e8f0; }
      .print-toolbar { position:sticky; top:0; z-index:10; display:flex; align-items:center; justify-content:space-between; gap:12px;
        padding:8px 16px; background:#1e293b; color:#fff; font:600 13px -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif; }
      .print-toolbar button { display:inline-flex; align-items:center; gap:6px; padding:6px 14px; border:none; border-radius:6px;
        background:#0ea5e9; color:#fff; font:600 13px inherit; cursor:pointer; }
      .print-toolbar button:hover { background:#0284c7; }
      .print-sheet { background:#fff; max-width:1100px; margin:12px auto; box-shadow:0 1px 4px rgba(15,23,42,.15); }
      @media print {
        .print-toolbar { display:none; }
        body { background:#fff; }
        .print-sheet { margin:0; max-width:none; box-shadow:none; }
      }
      ${extraStyle}
    </style></head>
    <body>
      <div class="print-toolbar">
        <span>${title}</span>
        <button type="button" onclick="window.print()" title="In">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
          In
        </button>
      </div>
      <div class="print-sheet">${bodyHtml}</div>
    </body></html>`)
  win.document.close()
  win.focus()
}

function legendGridHtml(legend) {
  return legend.map(l => {
    const parts = l.split(':')
    const code = parts[0]?.trim() || ''
    const label = parts[1]?.trim() || ''
    return `<div style="font-size:9px;line-height:1.4;color:#334155;">
      <strong style="color:#0f172a;display:inline-block;width:32px;">${code}</strong>: ${label}
    </div>`
  }).join('')
}

/** Phòng của nhóm phân công: tình trạng lấy theo lịch phân công (snapshot), kèm cờ back-to-back / EB hiện tại. */
function groupRoomForPrint(r) {
  const live = roomById.value.get(Number(r.room_id))
  return {
    room_id: Number(r.room_id),
    room_number: r.room_number,
    room_type: r.room_class_name || live?.room_type || '',
    floor: live?.floor ?? null,
    status: getStatusText(r.room_status_snapshot, r.booking_status_snapshot, live),
    isDirty: String(r.room_status_snapshot).includes('dirty'),
  }
}

/** Phòng chọn từ danh sách bên trái: tình trạng lấy theo danh sách phòng. */
function listRoomForPrint(room) {
  return {
    room_id: room.id,
    room_number: room.room_number,
    room_type: room.room_type,
    floor: room.floor,
    status: getStatusText(room.room_status_code, room.displayBookingStatus, room),
    isDirty: String(room.room_status_code).includes('dirty'),
  }
}

function printByGroup() {
  const sheets = buildWorksheetSheets(
    hkStore.groups, selectedGroupsToPrint.value, selectedRoomIds.value, hkStore.roomGroupMap, roomById.value,
  ).map(sheet => ({
    staffNames: sheet.staffNames,
    rooms: sheet.rooms.map(item => item.source === 'group' ? groupRoomForPrint(item.room) : listRoomForPrint(item.room)),
  }))
  if (!sheets.length) { alert('Chưa có phòng nào để in. Chọn phòng ở danh sách bên trái hoặc tích "In lịch" ở nhóm phân công.'); return }

  const shift = hkStore.shifts.find(s => s.id == selectedShiftId.value)
  const engShiftName = getEnglishShiftName(shift)
  const dateStr = workDate.value.split('-').reverse().join('/')
  const legendHtml = legendGridHtml(buildWorksheetLegend())

  const COLS = hkStore.activeWorksheetCols

  const pages = sheets.map(sheet => {
    const sortedRooms = [...sheet.rooms].sort(compareRooms)
    const rows = sortedRooms.map((r, i) => {
      const roomNumColor = r.isDirty ? '#b91c1c' : '#0f172a'

      const cellHtml = COLS.map(c => {
        if (c.is_fixed) {
          const lbl = String(c.label).toUpperCase()
          if (lbl === 'STT') {
            return `<td style="text-align:center;font-size:9px;border:1px solid #1e293b;padding:4px;color:#64748b;width:${c.width || 'auto'};">${i + 1}</td>`
          } else if (lbl === 'PHÒNG') {
            return `<td style="text-align:center;font-weight:800;font-size:12px;color:${roomNumColor};border:1px solid #1e293b;padding:4px;font-family:monospace;width:${c.width || 'auto'};">${r.room_number}</td>`
          } else if (lbl === 'LOẠI') {
            return `<td style="text-align:center;font-size:9px;border:1px solid #1e293b;padding:4px;color:#334155;width:${c.width || 'auto'};">${r.room_type || ''}</td>`
          } else if (lbl === 'TÌNH TRẠNG' || lbl === 'TRẠNG THÁI') {
            return `<td style="text-align:center;font-weight:700;font-size:9.5px;border:1px solid #1e293b;padding:4px;color:#0f172a;width:${c.width || 'auto'};">${r.status}</td>`
          }
        }
        return `<td style="border:1px solid #1e293b;padding:4px;width:${c.width || 'auto'};"></td>`
      }).join('')

      return `<tr style="height: 30px;">${cellHtml}</tr>`
    }).join('')

    const hasGroups = COLS.some(c => c.parent_label)
    const row1Cells = []
    const row2Cells = []
    const processedIndices = new Set()

    for (let idx = 0; idx < COLS.length; idx++) {
      if (processedIndices.has(idx)) continue
      const col = COLS[idx]

      if (!hasGroups) {
        row1Cells.push(`<th style="font-size:8px;padding:6px 4px;text-align:center;border:1px solid #1e293b;font-weight:700;width:${col.width || 'auto'};">${col.label}</th>`)
        processedIndices.add(idx)
      } else {
        if (!col.parent_label) {
          row1Cells.push(`<th rowspan="2" style="font-size:8px;padding:6px 4px;text-align:center;border:1px solid #1e293b;font-weight:700;width:${col.width || 'auto'};">${col.label}</th>`)
          processedIndices.add(idx)
        } else {
          let count = 0
          let totalWidth = 0
          let hasWidth = false
          const groupCols = []

          for (let j = idx; j < COLS.length; j++) {
            if (COLS[j].parent_label === col.parent_label) {
              count++
              groupCols.push(COLS[j])
              processedIndices.add(j)
              const wVal = parseInt(COLS[j].width)
              if (!isNaN(wVal)) {
                totalWidth += wVal
                hasWidth = true
              }
            } else {
              break
            }
          }

          const groupWidthStr = hasWidth ? `width:${totalWidth}px;` : ''
          row1Cells.push(`<th colspan="${count}" style="font-size:8px;padding:4px 2px;text-align:center;border:1px solid #1e293b;font-weight:700;${groupWidthStr}">${col.parent_label}</th>`)

          groupCols.forEach(gc => {
            row2Cells.push(`<th style="font-size:7.5px;padding:4px 2px;text-align:center;border:1px solid #1e293b;font-weight:700;white-space:pre-line;word-break:break-all;width:${gc.width || 'auto'};">${gc.label}</th>`)
          })
        }
      }
    }

    const headerHtml = `
      <tr style="background:#f1f5f9;border-bottom:1px solid #1e293b;">
        ${row1Cells.join('')}
      </tr>
      ${hasGroups && row2Cells.length ? `<tr style="background:#f1f5f9;border-bottom:2px solid #1e293b;">${row2Cells.join('')}</tr>` : ''}
    `

    const staffHtml = sheet.staffNames
      ? `<strong style="font-size:12px;color:#0f172a;">${sheet.staffNames}</strong>`
      : `<span style="display:inline-block;width:220px;border-bottom:1px solid #475569;">&nbsp;</span>`

    return `<div class="page" style="padding:15mm 10mm;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#0f172a;">
      <div style="display:flex;justify-content:space-between;align-items:flex-end;border-bottom:2px solid #0f172a;padding-bottom:8px;margin-bottom:12px;">
        <div>
          <h2 style="font-size:15px;font-weight:800;margin:0;text-transform:uppercase;letter-spacing:0.5px;">ROOM ATTENDANT ${engShiftName} WORKSHEET</h2>
          <div style="font-size:9px;color:#475569;margin-top:2px;">PMS HOTEL HOUSEKEEPING SYSTEM</div>
        </div>
        <div style="text-align:right;font-size:10px;font-weight:500;">
          <div>DATE: <span style="font-weight:700;">${dateStr}</span></div>
          <div style="margin-top:2px;">SHIFT: <span style="font-weight:700;color:#0284c7;">${engShiftName}</span></div>
        </div>
      </div>

      <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:8px 12px;margin-bottom:12px;font-size:11px;display:flex;justify-content:space-between;align-items:center;">
        <div>HOUSEKEEPING ATTENDANT: ${staffHtml}</div>
        <div>TOTAL ROOMS: <strong style="font-size:12px;color:#0f172a;">${sortedRooms.length}</strong></div>
      </div>

      <table style="width:100%;border-collapse:collapse;font-size:9px;border:1px solid #1e293b;">
        <thead>
          ${headerHtml}
        </thead>
        <tbody>${rows}</tbody>
      </table>

      <div style="margin-top:15px;border-top:1px dashed #cbd5e1;padding-top:10px;">
        <div style="font-size:9px;font-weight:700;color:#475569;margin-bottom:6px;text-transform:uppercase;letter-spacing:0.5px;">BẢNG GIẢI THÍCH KÝ HIỆU / STATUS LEGEND:</div>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:6px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:8px 12px;">
          ${legendHtml}
        </div>
      </div>
    </div>`
  })

  openPrintPreview('Worksheet', pages.join(''), '@media print{.page{page-break-after:always;}}')
}

function printByRoom() {
  // FL/Supervisor Check List: in từ lịch phân công (nhóm đã tích "In lịch") và/hoặc danh sách phòng bên trái.
  // Tình trạng lấy theo nơi chọn in: nhóm phân công -> tình trạng lúc phân công; danh sách phòng -> tình trạng hiện tại.
  const printRooms = buildSupervisorRooms(hkStore.groups, selectedGroupsToPrint.value, selectedRoomIds.value, roomById.value)
    .map(item => item.source === 'group' ? groupRoomForPrint(item.room) : listRoomForPrint(item.room))
    .sort(compareRooms)
  if (!printRooms.length) { alert('Chọn phòng cần in trong danh sách bên trái hoặc tích "In lịch" ở nhóm phân công'); return }

  const shift = hkStore.shifts.find(s => s.id == selectedShiftId.value)
  const engShiftName = getEnglishShiftName(shift)
  const dateStr = workDate.value.split('-').reverse().join('/')
  const legendHtml = legendGridHtml(buildWorksheetLegend())

  const rows = printRooms.map(r => {
    const roomNumColor = r.isDirty ? '#b91c1c' : '#059669'

    // 3 cột đầu là dữ liệu, các cột còn lại để trống cho supervisor ghi
    const dataCells = [
      `<td style="text-align:center;color:${roomNumColor};font-weight:800;font-size:12px;font-family:monospace;border:1px solid #475569;padding:6px 8px;">${r.room_number}</td>`,
      `<td style="text-align:center;font-size:10px;border:1px solid #475569;padding:6px 8px;color:#334155;">${r.room_type}</td>`,
      `<td style="text-align:center;font-weight:700;font-size:10px;border:1px solid #475569;padding:6px 8px;color:#0f172a;">${r.status}</td>`,
      ...hkStore.activeSupervisorCols.slice(3).map((c, i) =>
        i === hkStore.activeSupervisorCols.slice(3).findIndex(x => x.label === 'Attendance')
          ? `<td style="text-align:center;border:1px solid #475569;padding:6px 8px;font-size:11px;color:#64748b;">☐</td>`
          : `<td style="border:1px solid #475569;padding:6px 8px;"></td>`
      )
    ].join('')
    return `<tr style="height:32px;">${dataCells}</tr>`
  }).join('')

  const body = `<div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#0f172a;padding:10mm;">
    <div style="display:flex;justify-content:space-between;align-items:flex-end;border-bottom:2px solid #0f172a;padding-bottom:8px;margin-bottom:15px;">
      <div>
        <h1 style="font-size:18px;font-weight:800;margin:0;text-transform:uppercase;letter-spacing:0.5px;">FL / SUPERVISOR CHECK LIST</h1>
        <div style="font-size:10px;color:#475569;margin-top:2px;">PMS HOTEL HOUSEKEEPING SYSTEM</div>
      </div>
      <div style="text-align:right;font-size:10px;font-weight:500;">
        <div>DATE: <span style="font-weight:700;">${dateStr}</span></div>
        <div style="margin-top:2px;">SHIFT: <span style="font-weight:700;color:#0284c7;">${engShiftName}</span></div>
      </div>
    </div>

    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:10px 15px;margin-bottom:15px;font-size:11px;display:grid;grid-template-columns:2fr 1fr 1fr;gap:20px;">
      <div>SUPERVISOR NAME: <span style="display:inline-block;width:140px;border-bottom:1px solid #475569;margin-left:4px;">&nbsp;</span></div>
      <div>BLOCK: <span style="display:inline-block;width:60px;border-bottom:1px solid #475569;margin-left:4px;">&nbsp;</span></div>
      <div style="text-align:right;">TOTAL ROOMS: <strong style="font-size:12px;color:#0f172a;">${printRooms.length}</strong></div>
    </div>

    <table class="sup-table">
      <thead>
        <tr>
          ${hkStore.activeSupervisorCols.map(c => `<th style="text-align:center;${c.width ? 'width:' + c.width : ''}">${c.label}</th>`).join('')}
        </tr>
      </thead>
      <tbody>${rows}</tbody>
    </table>

    <div style="margin-top:20px;display:flex;justify-content:space-between;align-items:flex-start;gap:20px;">
      <div style="flex:1;border:1px solid #cbd5e1;border-radius:6px;padding:10px 15px;background:#f8fafc;">
        <div style="font-weight:700;font-size:11px;margin-bottom:8px;color:#0f172a;text-transform:uppercase;">Side Duties (Công việc phụ)</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:10px;">
          ${['Pantry (Kho tủ)', 'Corridor (Hành lang)', 'Elevator (Thang máy)', 'Trolley (Xe đẩy)'].map(d => `
            <div style="display:flex;align-items:center;gap:6px;">
              <span>- ${d}:</span>
              <span style="font-weight:500;color:#475569;">Đạt ☐ / Chưa đạt ☐</span>
            </div>
          `).join('')}
        </div>
      </div>
      <div style="width:250px;text-align:right;margin-top:10px;">
        <div style="font-weight:700;font-size:11px;color:#0f172a;text-transform:uppercase;margin-bottom:40px;">SUPERVISOR SIGNATURE</div>
        <div style="border-top:1px solid #475569;display:inline-block;width:180px;"></div>
      </div>
    </div>

    <div style="margin-top:20px;border-top:1px dashed #cbd5e1;padding-top:10px;">
      <div style="font-size:9px;font-weight:700;color:#475569;margin-bottom:6px;text-transform:uppercase;letter-spacing:0.5px;">BẢNG GIẢI THÍCH KÝ HIỆU / STATUS LEGEND:</div>
      <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:6px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:8px 12px;">
        ${legendHtml}
      </div>
    </div>

    <div style="margin-top:15px;font-size:9px;color:#64748b;text-align:right;">Printed by: ${new Date().toLocaleString('vi-VN')}</div>
  </div>`

  openPrintPreview('FL/Supervisor Check List', body, `
    .sup-table { width:100%; border-collapse:collapse; font-size:10px; }
    .sup-table th, .sup-table td { border:1px solid #475569; padding:6px 8px; }
    .sup-table th { background:#f1f5f9; font-weight:700; text-transform:uppercase; font-size:9px; letter-spacing:0.5px; }
  `)
}
</script>

<template>
  <div class="hk-assign-root">
    <div class="app-body-wrap">

    <!-- ══════════════════ LEFT PANEL ══════════════════ -->
    <div class="left-panel">

      <!-- Header: Ngày + Ca -->
      <div class="left-header">
        <div class="date-shift-row">
          <div class="date-picker-wrap">
            <SingleDatePicker
              v-model="workDate"
              class="w-[142px] shrink-0"
              input-class="!h-8 !px-2 !text-xs font-roboto"
              @change="hkStore.loadAssignment(workDate, selectedShiftId)"
            />
          </div>
          <div class="shift-tabs">
            <button
              v-for="shift in sortedShifts"
              :key="shift.id"
              class="shift-tab"
              :class="{ active: selectedShiftId === shift.id }"
              @click="selectedShiftId = shift.id"
              :title="`${getShiftName(shift)} (${shift.start_time} - ${shift.end_time})`"
            >
              <span>Ca</span>
              <span class="shift-name-text">{{ getShiftShortName(shift) }}</span>
            </button>
          </div>
        </div>

        <!-- Search + Filter -->
        <div class="search-row">
          <div class="search-wrap">
            <Search :size="13" class="search-icon" />
            <input v-model="searchQ" type="text" placeholder="Tìm số phòng, loại phòng..." class="search-input" />
            <button v-if="searchQ" type="button" class="btn-clear-quick" @click="searchQ = ''" title="Xóa tìm kiếm">
              <X :size="12" />
            </button>
          </div>
          <div class="filter-wrap" ref="filterWrapRef">
            <button class="filter-btn" :class="{ active: filterHk !== 'all' || filterBook !== 'all' }" @click.stop="showFilterPanel = !showFilterPanel">
              <Filter :size="13" />
              <span v-if="filterHk !== 'all' || filterBook !== 'all'" class="filter-badge"></span>
            </button>
            <div v-if="showFilterPanel" class="filter-panel">
              <div class="fp-section">
                <div class="fp-label">Vệ sinh</div>
                <div class="fp-chips">
                  <button
                    v-for="opt in hkFilterOptions"
                    :key="opt.key"
                    class="fc-chip"
                    :class="{ on: filterHk === opt.key }"
                    @click="filterHk = opt.key"
                  >
                    <RoomIcon
                      v-if="opt.icon"
                      :name="opt.icon"
                      class="w-3.5 h-3.5 shrink-0 inline-block"
                      :class="filterHk === opt.key ? 'text-white' : opt.iconClass"
                    />
                    <span>{{ opt.label }}</span>
                  </button>
                </div>
              </div>
              <div class="fp-divider"></div>
              <div class="fp-section">
                <div class="fp-label">Đặt phòng</div>
                <div class="fp-chips">
                  <button
                    v-for="opt in bookingFilterOptions"
                    :key="opt.key"
                    class="fc-chip"
                    :class="{ on: filterBook === opt.key }"
                    @click="filterBook = opt.key"
                  >{{ opt.label }}</button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Floor chips -->
        <div class="floor-row">
          <button class="fc" :class="{ 'all-on': activeFloors.size === 0 }" @click="clearFloors()">Tất cả</button>
          <button v-for="f in floors" :key="f"
            class="fc" :class="{ on: activeFloors.has(f) }"
            @click="toggleFloor(f)">T{{ f }}</button>
        </div>

        <!-- Select all -->
        <div class="select-all-row">
          <label class="check-label">
            <input type="checkbox"
              :checked="isAllSelected"
              :indeterminate="isPartialSelected"
              @change="toggleSelectAll($event.target.checked)" />
            Chọn tất cả
            <span class="sel-count" v-if="selectedRoomIds.size > 0">({{ selectedRoomIds.size }})</span>
          </label>
        </div>
      </div><!-- /left-header -->

      <!-- Room list -->
      <div class="room-list-scroll">
        <div v-if="hkStore.loading" class="list-loading"><Loader2 :size="18" class="spin" /> Đang tải...</div>
        <template v-else v-for="floor in sortedFloorNumbers" :key="floor">
          <div class="floor-label">
            <input type="checkbox"
              :checked="isFloorAllSelected(floor)"
              :indeterminate="isFloorPartialSelected(floor)"
              @change="toggleFloorRooms(floor, $event.target.checked)"
              class="floor-cb" />
            TẦNG {{ floor }}
          </div>
          <div
            v-for="room in roomsByFloor[floor]" :key="room.id"
            class="room-item"
            :class="{ selected: selectedRoomIds.has(room.id), assigned: !!hkStore.roomGroupMap[room.id] }"
            :draggable="!hkStore.saving"
            @dragstart="!hkStore.saving && onDragStartFromList($event, room.id)"
            @click="handleRoomItemClick(room.id, $event)"
          >
            <input
              type="checkbox"
              :checked="selectedRoomIds.has(room.id)"
              @click.stop
              @change="handleRoomCheckboxChange(room.id, $event)"
              class="room-cb"
            />
            <span class="room-num">{{ room.room_number }}</span>
            <span class="room-code">
              <template v-for="(sym, sIdx) in getStatusSymbols(room.room_status_code, room.displayBookingStatus, room)" :key="sIdx">
                <span v-if="sIdx > 0" style="color: #64748b; margin: 0 1px;">,</span>
                <span :style="{ color: sym.color }">{{ sym.code }}</span>
              </template>
            </span>
            <span class="room-type">{{ room.room_type }}</span>
            <span
              v-if="hkStore.roomGroupMap[room.id]"
              class="assigned-dot"
              title="Đã phân công. Chọn và phân công lại sẽ chuyển sang nhóm mới."
            >✓</span>
          </div>
        </template>
        <div v-if="!hkStore.loading && filteredRooms.length === 0" class="empty-msg">Không có phòng phù hợp</div>
      </div>
    </div><!-- /left-panel -->

    <!-- ══════════════════ RIGHT PANEL ══════════════════ -->
    <div class="right-panel">

      <!-- Groups list -->
      <div class="groups-scroll">
        <div v-if="hkStore.loading" class="list-loading"><Loader2 :size="18" class="spin" /> Đang tải...</div>

        <div v-else-if="!selectedShiftId" class="empty-assign">
          <Clock3 :size="32" class="empty-icon" />
          <p>Chọn ca làm việc để bắt đầu phân công</p>
        </div>

        <div v-else-if="hkStore.groups.length === 0" class="empty-assign">
          <Users :size="32" class="empty-icon" />
          <p>Chưa có phân công trong ca này<br><small>Chọn phòng ở bên trái, chọn nhân viên rồi nhấn Phân công</small></p>
        </div>

        <div
          v-for="(group, gIdx) in hkStore.groups" :key="group.id"
          class="group-box"
          :style="{ '--gcolor': group.color || getGroupColor(gIdx) }"
          @dragover.prevent="onDragOver"
          @drop="onDropToGroup($event, group.id)"
        >
          <!-- Group header -->
          <div class="group-header">
            <div class="group-names-block">
              <div v-for="s in group.staff_list" :key="s.staff_id" class="gn-name">{{ s.name }}</div>
              <div class="gn-sub">{{ group.rooms.length }} phòng được gán</div>
            </div>
            <div class="group-actions">
              <label class="print-cb-label">
                <input type="checkbox"
                  :checked="selectedGroupsToPrint.has(group.id)"
                  @change="toggleGroupPrint(group.id)" />
                <span>In lịch</span>
              </label>
              <button class="btn-group-edit" title="Chỉnh sửa nhân viên" @click="openEditGroup(group.id)"><Pencil :size="12" /></button>
              <button class="btn-group-del" title="Xóa nhóm" @click="confirmRemoveGroup(group.id)"><X :size="14" /></button>
            </div>
          </div>

          <!-- Room rows -->
          <div
            v-for="r in sortedGroupRooms(group)" :key="r.room_id"
            class="group-room-row"
            :draggable="!hkStore.saving"
            @dragstart="!hkStore.saving && onDragStartFromGroup($event, r.room_id, group.id)"
          >
            <span class="gr-num-bold">{{ r.room_number }}</span>
            <div class="gr-info-block">
              <div class="gr-status-line">
                <template v-for="(sym, sIdx) in getGroupRoomSymbols(r)" :key="sIdx">
                  <span v-if="sIdx > 0" style="color: #64748b; margin: 0 1px;">,</span>
                  <span :style="{ color: sym.color }">{{ sym.code }}</span>
                </template>
              </div>
              <div class="gr-class-line">{{ r.room_class_name }}</div>
            </div>
            <button class="btn-rm-room" @click="confirmRemoveRoom(group.id, r.room_id)"><X :size="11" /></button>
          </div>

          <!-- Drop hint -->
          <div class="drop-hint">Kéo phòng vào đây</div>
        </div>
      </div><!-- /groups-scroll -->
    </div><!-- /right-panel -->
    </div><!-- /app-body-wrap -->

    <!-- Assign bar (fixed across bottom) -->
    <div class="assign-bar">
      <div class="assign-info" v-if="selectedRoomIds.size > 0">
        <span class="badge-rooms">{{ selectedRoomIds.size }} phòng đã chọn</span>
      </div>
      <div v-else class="assign-info">
        <span style="font-size: 12px; color: #94a3b8;">Chọn phòng ở bên trái để phân công</span>
      </div>

      <div class="assign-actions-right" style="display: flex; align-items: center; gap: 12px; flex: 1; justify-content: flex-end; min-width: 0;">
        <!-- Staff picker (multi-select dropdown) -->
        <div class="staff-dropdown-container" ref="staffDropdownRef">
          <button class="btn-staff-dropdown" @click="showStaffDropdown = !showStaffDropdown" :title="selectedStaffSummaryTitle">
            <span class="truncate flex-1 text-left select-none">{{ dropdownButtonLabel }}</span>
            <span v-if="selectedStaffIds.size > 1" class="staff-count-badge">{{ selectedStaffIds.size }}</span>
            <ChevronDown :size="14" class="caret-icon shrink-0" :class="{ open: showStaffDropdown }" />
          </button>
          
          <Transition name="fade-slide">
            <div v-if="showStaffDropdown" class="staff-dropdown-panel">
              <div
                v-for="s in availableStaffForPicker" :key="s.id"
                class="dropdown-staff-item"
                :class="{ selected: selectedStaffIds.has(s.id) }"
                @click="toggleStaffPicker(s.id)"
              >
                <input
                  type="checkbox"
                  :checked="selectedStaffIds.has(s.id)"
                  @click.stop
                  @change="toggleStaffPicker(s.id)"
                  class="dropdown-staff-cb"
                />
                <span class="dropdown-staff-avatar" :style="{ background: GROUP_COLORS[s.id % GROUP_COLORS.length] }">
                  {{ getInitials(s.name) }}
                </span>
                <span class="dropdown-staff-name">{{ s.name }}</span>
              </div>
              <div v-if="availableStaffForPicker.length === 0" class="no-staff-dropdown-msg">
                Tất cả nhân viên đã được phân công
              </div>
            </div>
          </Transition>
        </div>

        <div class="bar-actions" style="flex-shrink: 0;">
          <button class="btn-pms-primary" :disabled="!canAssign || hkStore.saving" @click="doAssign">
            <Loader2 v-if="hkStore.saving" :size="14" class="spin" />
            <Plus v-else :size="14" />
            <span>Phân công</span>
          </button>
          <button class="btn-pms-secondary" @click="showStaffModal = true">
            <UserCog :size="14" />
            <span>Quản lý NV</span>
          </button>
          <div class="print-split">
            <button class="btn-pms-secondary !rounded-r-none !border-r-0" @click="doPrint">
              <Printer :size="14" />
              <span>In lịch</span>
            </button>
            <div class="print-mode-wrap">
              <select v-model="printMode" class="print-mode-select">
                <option value="group">Theo nhóm NV</option>
                <option value="room">Theo phòng (Supervisor)</option>
              </select>
            </div>
          </div>
        </div>
      </div>
    </div><!-- /assign-bar -->

    <!-- ══════════════════ MODAL: QUẢN LÝ NHÂN VIÊN ══════════════════ -->
    <Teleport to="body">
      <Transition name="modal-fade">
        <div v-if="showStaffModal" class="modal-overlay" @click.self="showStaffModal = false">
          <div
            class="modal"
            :style="{ transform: `translate(${staffModalPos.x}px, ${staffModalPos.y}px)` }"
          >
            <div class="modal-head cursor-move select-none" @mousedown="startDragStaffModal">
              <h3>Quản lý nhân viên Housekeeping</h3>
              <button class="modal-close" @click="showStaffModal = false" title="Đóng (Esc)"><X :size="16" /></button>
            </div>
            <div class="modal-tabs">
              <button :class="{ 'mtab-on': staffTab === 'active' }" @click="staffTab = 'active'">
                Đang hoạt động ({{ hkStore.staff.length }})
              </button>
              <button :class="{ 'mtab-on': staffTab === 'hidden' }" @click="staffTab = 'hidden'">
                Đã ẩn ({{ hkStore.staffAll.filter(s => s.is_hidden).length }})
              </button>
            </div>
            <div class="modal-body">
              <!-- Add staff -->
              <div v-if="staffTab === 'active'" class="add-staff-row">
                <div class="add-staff-input-wrap">
                  <input
                    v-model="newStaffName"
                    type="text"
                    placeholder="Nhập tên nhân viên mới..."
                    @keydown.enter="submitAddStaff"
                    class="add-staff-input"
                  />
                  <button
                    v-if="newStaffName"
                    type="button"
                    class="btn-clear-quick"
                    @click="newStaffName = ''"
                    title="Xóa nhanh"
                  >
                    <X :size="12" />
                  </button>
                </div>
                <button class="btn-pms-primary" @click="submitAddStaff">
                  <Plus :size="14" />
                  <span>Thêm</span>
                </button>
              </div>

              <!-- Staff search -->
              <div class="staff-search-row">
                <div class="staff-search-wrap">
                  <Search :size="13" class="search-icon" />
                  <input
                    v-model="staffSearchQ"
                    type="text"
                    placeholder="Tìm kiếm nhân viên..."
                    class="staff-search-input"
                  />
                  <button
                    v-if="staffSearchQ"
                    type="button"
                    class="btn-clear-quick"
                    @click="staffSearchQ = ''"
                    title="Xóa nhanh"
                  >
                    <X :size="12" />
                  </button>
                </div>
              </div>

              <!-- Staff list -->
              <div class="staff-master-list">
                <div v-for="s in filteredStaffInModal" :key="s.id" class="sml-item">
                  <span class="sml-avatar" :style="{ background: GROUP_COLORS[s.id % GROUP_COLORS.length], filter: s.is_hidden ? 'grayscale(1)' : '' }">{{ getInitials(s.name) }}</span>
                  <span class="sml-name">{{ s.name }}</span>
                  <button class="btn-sml-action" @click="doHideStaff(s.id, !s.is_hidden)">
                    {{ s.is_hidden ? '👁 Hiện' : '👁 Ẩn' }}
                  </button>
                  <button v-if="!s.is_hidden" class="btn-sml-del" @click="doDeleteStaff(s.id)" title="Xóa">
                    <X :size="12" />
                  </button>
                </div>
                <div v-if="filteredStaffInModal.length === 0" class="empty-msg">
                  {{ staffTab === 'hidden' ? 'Không có nhân viên ẩn phù hợp' : 'Không tìm thấy nhân viên' }}
                </div>
              </div>
            </div>
            <div class="modal-foot">
              <button class="btn-pms-close" @click="showStaffModal = false">
                <X :size="14" />
                <span>Đóng</span>
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- ══════════════════ MODAL: CHỈNH SỬA NHÓM ══════════════════ -->
    <Teleport to="body">
      <Transition name="modal-fade">
        <div v-if="showEditGroupModal && editingGroup" class="modal-overlay" @click.self="showEditGroupModal = false">
          <div
            class="modal"
            :style="{
              transform: `translate(${editGroupModalPos.x}px, ${editGroupModalPos.y}px)`,
              maxWidth: '420px'
            }"
          >
            <div class="modal-head cursor-move select-none" @mousedown="startDragEditGroupModal">
              <h3>Chỉnh sửa nhân viên nhóm</h3>
              <button class="modal-close" @click="showEditGroupModal = false" title="Đóng (Esc)"><X :size="16" /></button>
            </div>
            <div class="modal-body">
              <div class="eg-section-label">Nhân viên hiện tại:</div>
              <div class="eg-list">
                <div v-for="s in editingGroup.staff_list" :key="s.staff_id" class="eg-item">
                  <span class="eg-name">{{ s.name }}</span>
                  <button class="btn-eg-rm" @click="removeStaffFromGroup(s.staff_id)">Xóa khỏi nhóm</button>
                </div>
                <div v-if="!editingGroup.staff_list.length" class="empty-msg">Chưa có nhân viên</div>
              </div>
              <div class="eg-section-label" style="margin-top:12px;">Thêm nhân viên:</div>
              <div class="eg-list">
                <div v-for="s in editingGroupAvailableStaff" :key="s.id" class="eg-item">
                  <span class="eg-name">{{ s.name }}</span>
                  <button class="btn-eg-add" @click="addStaffToGroup(s.id)">+ Thêm</button>
                </div>
                <div v-if="editingGroupAvailableStaff.length === 0" class="empty-msg">Không còn nhân viên khả dụng</div>
              </div>
            </div>
            <div class="modal-foot">
              <button class="btn-pms-close" @click="showEditGroupModal = false">
                <X :size="14" />
                <span>Đóng</span>
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Project-wide standard loading overlay -->
    <LoadingOverlay :show="hkStore.loading" />

  </div>
</template>

<style scoped>
/* ── Root layout ─────────────────────────────────────────── */
.hk-assign-root {
  display: flex;
  flex-direction: column;
  height: 100%;
  overflow: hidden;
  background: #f8fafc;
  font-family: 'Roboto', system-ui, -apple-system, sans-serif;
  font-size: 12px;
  color: #000000D9;
}
.app-body-wrap {
  display: flex;
  flex: 1;
  overflow: hidden;
}

/* ── Left panel ──────────────────────────────────────────── */
.left-panel {
  width: 300px;
  min-width: 280px;
  display: flex;
  flex-direction: column;
  border-right: 1px solid #e2e8f0;
  background: #fff;
}
.left-header {
  padding: 10px 8px 0;
  border-bottom: 1px solid #e2e8f0;
}
.date-shift-row {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 8px;
}
.date-picker-wrap {
  width: 142px;
  flex-shrink: 0;
}
.shift-tabs { display: flex; gap: 4px; align-items: center; }
.shift-tab {
  display: inline-flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  width: 38px;
  height: 38px;
  border-radius: 50%;
  font-size: 12px;
  font-weight: 700;
  line-height: 1.1;
  border: 1px solid #cbd5e1;
  cursor: pointer;
  background: #fff;
  color: #000000D9;
  transition: all .15s;
}
.shift-tab.active {
  background: var(--pms-custom-theme, #006bdb);
  border-color: var(--pms-custom-theme, #006bdb);
  color: #fff;
  font-weight: 700;
}
.shift-name-text {
  font-weight: 700;
}

.search-row { display: flex; gap: 6px; margin-bottom: 6px; }
.search-wrap {
  flex: 1;
  display: flex;
  align-items: center;
  gap: 6px;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  padding: 0 8px;
  height: 32px;
  background: #fff;
}
.search-icon { color: #94a3b8; flex-shrink: 0; }
.search-input {
  border: none;
  outline: none;
  font-size: 12px;
  font-family: 'Roboto', sans-serif;
  width: 100%;
  color: #000000D9;
  background: transparent;
  padding: 0;
}
.btn-clear-quick {
  background: none;
  border: none;
  padding: 2px;
  color: #94a3b8;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 4px;
  flex-shrink: 0;
  transition: color 0.15s;
}
.btn-clear-quick:hover {
  color: #ef4444;
}

.filter-wrap { position: relative; }
.filter-btn {
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  cursor: pointer;
  background: #f8fafc;
  color: #000000D9;
  position: relative;
}
.filter-btn.active { background: #eff6ff; border-color: #93c5fd; color: #3b82f6; }
.filter-badge { position: absolute; top: 4px; right: 4px; width: 6px; height: 6px; border-radius: 50%; background: #3b82f6; }
.filter-panel {
  position: absolute;
  top: 38px;
  right: 0;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 10px;
  min-width: 220px;
  z-index: 100;
  box-shadow: 0 4px 16px rgba(0,0,0,.08);
}
.fp-section { margin-bottom: 6px; }
.fp-label { font-size: 12px; font-weight: 600; color: #000000D9; margin-bottom: 4px; text-transform: uppercase; letter-spacing: .5px; }
.fp-chips { display: flex; flex-wrap: wrap; gap: 4px; }
.fc-chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  padding: 3px 8px;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  cursor: pointer;
  background: #f8fafc;
  color: #000000D9;
  transition: all 0.15s;
}
.fc-chip.on {
  background: var(--pms-custom-theme, #006bdb);
  border-color: var(--pms-custom-theme, #006bdb);
  color: #fff;
  font-weight: 500;
}
.fp-divider { border-top: 1px solid #f1f5f9; margin: 6px 0; }

.floor-row { display: flex; flex-wrap: wrap; gap: 4px; padding: 6px 0; }
.fc {
  font-size: 12px;
  padding: 2px 8px;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  cursor: pointer;
  background: #f8fafc;
  color: #000000D9;
  transition: all 0.15s;
}
.fc.all-on { background: #1e293b; color: #fff; border-color: #1e293b; }
.fc.on { background: var(--pms-custom-theme, #006bdb); color: #fff; border-color: var(--pms-custom-theme, #006bdb); }

.select-all-row { padding: 4px 0 8px; border-top: 1px solid #f1f5f9; }
.check-label { display: flex; align-items: center; gap: 6px; font-size: 12px; color: #000000D9; cursor: pointer; }
.sel-count { font-weight: 600; color: var(--pms-custom-theme, #006bdb); }

/* Room list */
.room-list-scroll { flex: 1; overflow-y: auto; padding: 0 8px 8px; }
.floor-label { font-size: 12px; font-weight: 600; color: #000000D9; letter-spacing: .5px; padding: 6px 4px 3px; display: flex; align-items: center; gap: 5px; }
.floor-cb { accent-color: var(--pms-custom-theme, #006bdb); cursor: pointer; }
.room-cb { accent-color: var(--pms-custom-theme, #006bdb); cursor: pointer; flex-shrink: 0; }
.room-item {
  display: flex; align-items: center; gap: 6px; padding: 5px 6px;
  border-radius: 6px; cursor: pointer; margin-bottom: 2px;
  border: 1px solid transparent; transition: all .12s;
}
.room-item:hover { background: #f0f9ff; border-color: #bae6fd; }
.room-item.selected { background: #e0f2fe; border-color: #7dd3fc; }
.room-item.assigned { opacity: .7; }
.room-num { font-size: 12px; font-weight: 700; color: #000000D9; min-width: 34px; }
.room-code { font-size: 12px; font-weight: 700; min-width: 48px; }
.room-type { font-size: 12px; font-weight: 400; color: #000000D9; flex: 1; text-align: right; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.assigned-dot { font-size: 12px; color: #10b981; flex-shrink: 0; }
.list-loading, .empty-msg { padding: 20px; text-align: center; font-size: 12px; color: #94a3b8; display: flex; align-items: center; justify-content: center; gap: 8px; }

/* ── Right panel ─────────────────────────────────────────── */
.right-panel { flex: 1; display: flex; flex-direction: column; overflow: hidden; }

/* Assign bar */
.assign-bar {
  padding: 10px 20px;
  border-top: 1px solid #e2e8f0;
  background: #fff;
  display: flex;
  gap: 16px;
  align-items: center;
  justify-content: space-between;
  box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.05);
  z-index: 10;
  flex-wrap: wrap;
}
.assign-info { display: flex; align-items: center; gap: 8px; }
.badge-rooms { background: #eff6ff; border: 1px solid #bfdbfe; color: #006bdb; font-size: 12px; font-weight: 600; padding: 2px 8px; border-radius: 10px; }

/* Staff dropdown selection */
.staff-dropdown-container {
  position: relative;
  display: inline-block;
}
.btn-staff-dropdown {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  width: 250px;
  max-width: 280px;
  height: 32px;
  padding: 0 10px;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  background: #fff;
  color: #000000D9;
  font-size: 12px;
  cursor: pointer;
  outline: none;
  white-space: nowrap;
  overflow: hidden;
  transition: all .15s;
}
.btn-staff-dropdown:hover {
  border-color: #94a3b8;
  color: #000000D9;
}
.staff-count-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 20px;
  height: 20px;
  padding: 0 4px;
  border-radius: 10px;
  font-size: 12px;
  font-weight: 700;
  line-height: 1;
  background: var(--pms-custom-theme, #006bdb);
  color: #fff;
  flex-shrink: 0;
}
.btn-staff-dropdown .caret-icon {
  transition: transform .15s;
  color: #94a3b8;
}
.btn-staff-dropdown .caret-icon.open {
  transform: rotate(180deg);
}

.staff-dropdown-panel {
  position: absolute;
  bottom: 100%;
  left: 0;
  margin-bottom: 6px;
  background: #fff;
  border: 1px solid #cbd5e1;
  border-radius: 8px;
  padding: 6px;
  width: 240px;
  max-height: 280px;
  overflow-y: auto;
  box-shadow: 0 -4px 16px rgba(0,0,0,.08);
  z-index: 100;
}
.dropdown-staff-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 6px 8px;
  border-radius: 6px;
  cursor: pointer;
  transition: background .12s;
  user-select: none;
}
.dropdown-staff-item:hover {
  background: #f8fafc;
}
.dropdown-staff-item.selected {
  background: #eff6ff;
}
.dropdown-staff-cb {
  accent-color: var(--pms-custom-theme, #006bdb);
  cursor: pointer;
  width: 14px;
  height: 14px;
}
.dropdown-staff-avatar {
  width: 24px;
  height: 24px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 700;
  color: #fff;
  flex-shrink: 0;
}
.dropdown-staff-name {
  font-size: 12px;
  color: #000000D9;
}
.no-staff-dropdown-msg {
  padding: 12px 8px;
  text-align: center;
  font-size: 12px;
  color: #94a3b8;
  font-style: italic;
}

.bar-actions { display: flex; gap: 8px; align-items: center; flex-shrink: 0; }

.print-split {
  display: flex;
  align-items: center;
  height: 32px;
}
.print-mode-wrap {
  height: 32px;
}
.print-mode-select {
  height: 32px;
  border: 1px solid #cbd5e1;
  border-left: none;
  border-radius: 0 6px 6px 0;
  background: #fff;
  color: #334155;
  font-size: 12px;
  padding: 0 8px;
  cursor: pointer;
  outline: none;
  font-family: 'Roboto', sans-serif;
  transition: border-color 0.15s;
}
.print-mode-select:focus {
  border-color: #94a3b8;
}

/* Groups scroll */
.groups-scroll {
  flex: 1;
  overflow-y: auto;
  padding: 16px;
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 16px;
  align-content: flex-start;
}
.empty-assign { display: flex; flex-direction: column; align-items: center; justify-content: center; grid-column: 1 / -1; height: 100%; color: #94a3b8; text-align: center; gap: 8px; padding-top: 60px; }
.empty-icon { color: #cbd5e1; }
.empty-assign p { font-size: 12px; font-weight: 600; }
.empty-assign small { font-size: 12px; color: #cbd5e1; }

/* Group box */
.group-box {
  background: #fff;
  border-radius: 8px;
  border: 1px solid #e2e8f0;
  border-left: 4px solid var(--gcolor);
  display: flex;
  flex-direction: column;
  height: fit-content;
  transition: box-shadow .15s;
}
.group-box:hover { box-shadow: 0 4px 12px rgba(0,0,0,.06); }

.group-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  padding: 12px;
  border-bottom: 1px solid #f1f5f9;
}
.group-names-block {
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.gn-name {
  font-size: 12px;
  font-weight: 600;
  color: #000000D9;
  line-height: 1.2;
}
.gn-sub {
  font-size: 12px;
  color: #000000D9;
  margin-top: 2px;
}
.group-actions {
  display: flex;
  align-items: center;
  gap: 6px;
}
.print-cb-label {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 12px;
  color: #000000D9;
  cursor: pointer;
  white-space: nowrap;
}
.btn-group-edit {
  width: 24px;
  height: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1px solid #cbd5e1;
  border-radius: 6px;
  background: #fff;
  color: #f97316;
  cursor: pointer;
  transition: all 0.15s;
}
.btn-group-edit:hover {
  border-color: #f97316;
  background: #fff7ed;
}
.btn-group-del {
  width: 24px;
  height: 24px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: none;
  background: transparent;
  color: #94a3b8;
  cursor: pointer;
  transition: all 0.15s;
}
.btn-group-del:hover {
  color: #ef4444;
}

/* Room row in group */
.group-room-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 12px;
  border-bottom: 1px solid #f1f5f9;
  cursor: grab;
  transition: background .1s;
}
.group-room-row:hover { background: #f8fafc; }
.group-room-row:last-of-type { border-bottom: none; }

.gr-num-bold {
  font-size: 12px;
  font-weight: 700;
  color: #000000D9;
  min-width: 40px;
}
.gr-info-block {
  flex: 1;
  margin-left: 10px;
  display: flex;
  flex-direction: column;
  gap: 2px;
}
.gr-status-line {
  font-size: 12px;
  font-weight: 600;
}
.gr-class-line {
  font-size: 12px;
  color: #000000D9;
}
.btn-rm-room {
  width: 20px;
  height: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: none;
  background: transparent;
  color: #fda4af;
  cursor: pointer;
  border-radius: 4px;
  transition: all 0.15s;
}
.btn-rm-room:hover {
  background: #fff1f2;
  color: #ef4444;
}
.drop-hint { text-align: center; font-size: 12px; color: #cbd5e1; padding: 6px; font-style: italic; }

/* ── Modals ──────────────────────────────────────────────── */
.modal-overlay {
  position: fixed; inset: 0; background: rgba(0,0,0,.35); z-index: 1000;
  display: flex; align-items: center; justify-content: center;
}
.modal {
  background: #fff;
  border-radius: 12px;
  width: 480px;
  max-width: 95vw;
  max-height: 80vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 20px 60px rgba(0,0,0,.2);
  overflow: hidden;
  font-family: 'Roboto', sans-serif;
}
.modal-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 16px;
  background: var(--pms-custom-theme, #006bdb);
  color: #ffffff;
  cursor: move;
  user-select: none;
}
.modal-head h3 {
  font-size: 12px;
  font-weight: 600;
  color: #ffffff;
  margin: 0;
}
.modal-close {
  width: 26px;
  height: 26px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 6px;
  border: none;
  cursor: pointer;
  background: rgba(255, 255, 255, 0.15);
  color: #ffffff;
  transition: all 0.15s;
}
.modal-close:hover {
  background: rgba(255, 255, 255, 0.3);
  color: #ffffff;
}
.modal-tabs { display: flex; border-bottom: 1px solid #f1f5f9; }
.modal-tabs button {
  flex: 1;
  padding: 8px;
  font-size: 12px;
  font-weight: 500;
  border: none;
  cursor: pointer;
  background: none;
  color: #000000D9;
  transition: all 0.15s;
}
.modal-tabs button.mtab-on {
  color: var(--pms-custom-theme, #006bdb);
  font-weight: 600;
  border-bottom: 2px solid var(--pms-custom-theme, #006bdb);
}
.modal-body { flex: 1; overflow-y: auto; padding: 14px 16px; }
.modal-foot { padding: 10px 16px; border-top: 1px solid #f1f5f9; display: flex; justify-content: flex-end; gap: 8px; }

.add-staff-row { display: flex; gap: 8px; margin-bottom: 10px; }
.add-staff-input-wrap {
  flex: 1;
  display: flex;
  align-items: center;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  padding: 0 8px;
  background: #fff;
  height: 32px;
}
.add-staff-input-wrap:focus-within {
  border-color: var(--pms-custom-theme, #006bdb);
}
.add-staff-input {
  flex: 1;
  border: none;
  outline: none;
  font-size: 12px;
  font-family: 'Roboto', sans-serif;
  color: #000000D9;
  background: transparent;
  padding: 0;
}

.staff-search-row {
  margin-bottom: 12px;
}
.staff-search-wrap {
  display: flex;
  align-items: center;
  gap: 6px;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
  padding: 0 8px;
  background: #fff;
  height: 32px;
}
.staff-search-wrap:focus-within {
  border-color: var(--pms-custom-theme, #006bdb);
}
.staff-search-input {
  flex: 1;
  border: none;
  outline: none;
  font-size: 12px;
  font-family: 'Roboto', sans-serif;
  color: #000000D9;
  background: transparent;
  padding: 0;
}

.staff-master-list { display: flex; flex-direction: column; gap: 5px; }
.sml-item { display: flex; align-items: center; gap: 8px; padding: 6px 8px; border: 1px solid #f1f5f9; border-radius: 7px; }
.sml-avatar { width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 700; color: #fff; flex-shrink: 0; }
.sml-name { flex: 1; font-size: 12px; color: #000000D9; }
.btn-sml-action { font-size: 12px; padding: 3px 8px; border: 1px solid #e2e8f0; border-radius: 6px; cursor: pointer; background: #f8fafc; color: #000000D9; }
.btn-sml-action:hover { background: #f1f5f9; }
.btn-sml-del { width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; border: 1px solid #fca5a5; border-radius: 5px; cursor: pointer; background: #fff; color: #ef4444; }
.btn-sml-del:hover { background: #fee2e2; }

/* Edit group modal */
.eg-section-label { font-size: 12px; color: #000000D9; font-weight: 600; text-transform: uppercase; margin-bottom: 6px; }
.eg-list { display: flex; flex-direction: column; gap: 5px; }
.eg-item { display: flex; align-items: center; gap: 8px; padding: 6px 10px; border: 1px solid #e2e8f0; border-radius: 7px; }
.eg-name { flex: 1; font-size: 12px; color: #000000D9; }
.btn-eg-rm { font-size: 12px; padding: 2px 8px; border: 1px solid #fca5a5; border-radius: 5px; cursor: pointer; background: #fff; color: #ef4444; }
.btn-eg-rm:hover { background: #fee2e2; }
.btn-eg-add { font-size: 12px; padding: 2px 8px; border: none; border-radius: 5px; cursor: pointer; background: var(--pms-custom-theme, #006bdb); color: #fff; }
.btn-eg-add:hover { opacity: 0.9; }

/* Transition animations */
.modal-fade-enter-active, .modal-fade-leave-active {
  transition: opacity 0.2s ease;
}
.modal-fade-enter-from, .modal-fade-leave-to {
  opacity: 0;
}
.modal-fade-enter-active .modal, .modal-fade-leave-active .modal {
  transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.modal-fade-enter-from .modal, .modal-fade-leave-to .modal {
  transform: scale(0.92);
}

.fade-slide-enter-active, .fade-slide-leave-active {
  transition: all 0.2s ease;
}
.fade-slide-enter-from, .fade-slide-leave-to {
  opacity: 0;
  transform: translateY(10px);
}

/* Spin animation */
.spin { animation: hkSpin 1s linear infinite; }
@keyframes hkSpin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

/* Scrollbar */
.room-list-scroll::-webkit-scrollbar,
.groups-scroll::-webkit-scrollbar { width: 4px; }
.room-list-scroll::-webkit-scrollbar-thumb,
.groups-scroll::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 4px; }
</style>
