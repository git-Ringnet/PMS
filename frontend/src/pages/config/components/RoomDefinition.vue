<script setup>
import { ref, reactive, computed, onMounted, onBeforeUnmount, watch } from 'vue'
import http from '@/services/http'
import { useUiStore } from '@/stores/ui-store'

const props = defineProps({
  initialTab: {
    type: String,
    default: 'TÊN LOẠI PHÒNG'
  }
})

const emit = defineEmits(['update:activeTab'])

const uiStore = useUiStore()

const activeRoomTab = ref(props.initialTab)

watch(() => props.initialTab, (newVal) => {
  activeRoomTab.value = newVal
})

watch(activeRoomTab, (newVal) => {
  emit('update:activeTab', newVal)
})

const roomTabs = [
  'TÊN LOẠI PHÒNG',
  'DẠNG PHÒNG',
  'GIÁ PHÒNG CHUẨN',
  'PHÒNG'
]

// Data States
const roomClasses = ref([])
const roomForms = ref([])
const standardRates = ref([])
const rooms = ref([])
const loading = ref(false)

// Persistent settings synchronization state
const rateConfigRecord = ref(null)
const roomConfigRecord = ref(null)
const isLoaded = ref(false)

// Modal State
const isRoomModalOpen = ref(false)
const isEditMode = ref(false)
const currentRoomId = ref(null)

// Room Class Modal State
const isRoomClassModalOpen = ref(false)
const isEditRoomClassMode = ref(false)
const currentRoomClassId = ref(null)
const roomClassImageInput = ref(null)
const roomClassFormState = reactive({
  name: '',
  code: '',
  color: '#ffffff',
  is_active: true,
  group: 'hotel',
  notes: '',
  image: null,
  imagePreview: null
})

// Room Form Modal State
const isRoomFormModalOpen = ref(false)
const isEditRoomFormMode = ref(false)
const currentRoomFormId = ref(null)
const roomFormStateData = reactive({
  name: '',
  max_adults: 0
})

// Standard Rate Modal State
const isStandardRateModalOpen = ref(false)
const isEditStandardRateMode = ref(false)
const currentStandardRateId = ref(null)
const standardRateFormState = reactive({
  room_class_id: '',
  room_form_id: '',
  room_price: 0,
  extra_bed_price: 0
})

// Pagination States
const classPage = ref(1)
const classPageSize = ref(25)
const formPage = ref(1)
const formPageSize = ref(25)
const ratePage = ref(1)
const ratePageSize = ref(25)
const roomPage = ref(1)
const roomPageSize = ref(25)

// Computed Paginated Data
const paginatedRoomClasses = computed(() => {
  const start = (classPage.value - 1) * classPageSize.value
  return roomClasses.value.slice(start, start + classPageSize.value)
})
const totalClassPages = computed(() => Math.ceil(roomClasses.value.length / classPageSize.value) || 1)

const paginatedRoomForms = computed(() => {
  const start = (formPage.value - 1) * formPageSize.value
  return roomForms.value.slice(start, start + formPageSize.value)
})
const totalFormPages = computed(() => Math.ceil(roomForms.value.length / formPageSize.value) || 1)

const paginatedStandardRates = computed(() => {
  const start = (ratePage.value - 1) * ratePageSize.value
  return standardRates.value.slice(start, start + ratePageSize.value)
})
const totalRatePages = computed(() => Math.ceil(standardRates.value.length / ratePageSize.value) || 1)

const paginatedRooms = computed(() => {
  const start = (roomPage.value - 1) * roomPageSize.value
  return rooms.value.slice(start, start + roomPageSize.value)
})
const totalRoomPages = computed(() => Math.ceil(rooms.value.length / roomPageSize.value) || 1)

const roomFormState = reactive({
  room_number: '',
  room_form_id: '',
  room_class_id: '',
  max_guests: 2,
  floor: '',
  area: 'Khu A',
  extra_beds_limit: 0,
  grid_row: 0,
  grid_column: 0,
  owner_room: '',
  linked_room: '',
  is_internal: false,
  notes: ''
})

// Load initial data
onMounted(async () => {
  fetchRoomClasses()
  fetchRoomForms()
  fetchStandardRates()
  fetchRooms()
  await fetchConfigs()
  isLoaded.value = true
  document.addEventListener('click', closeAllPopovers)
})

onBeforeUnmount(() => {
  document.removeEventListener('click', closeAllPopovers)
})

// API Functions
const fetchRoomClasses = async () => {
  try {
    const res = await http.get('/room-classes')
    roomClasses.value = res.data.data || []
  } catch (err) {
    console.error('Lỗi khi tải tên loại phòng:', err)
  }
}

const fetchRoomForms = async () => {
  try {
    const res = await http.get('/room-forms')
    roomForms.value = res.data.data || []
  } catch (err) {
    console.error('Lỗi khi tải dạng phòng:', err)
  }
}

const fetchStandardRates = async () => {
  try {
    const res = await http.get('/standard-rates')
    standardRates.value = res.data.data || []
  } catch (err) {
    console.error('Lỗi khi tải giá phòng chuẩn:', err)
  }
}

const fetchRooms = async () => {
  try {
    const res = await http.get('/rooms')
    rooms.value = res.data.data || []
  } catch (err) {
    console.error('Lỗi khi tải danh sách phòng:', err)
  }
}

// Room CRUD functions
const openAddRoomModal = () => {
  isEditMode.value = false
  currentRoomId.value = null
  Object.assign(roomFormState, {
    room_number: '',
    room_form_id: roomForms.value[0]?.id || '',
    room_class_id: roomClasses.value[0]?.id || '',
    max_guests: 2,
    floor: '',
    area: 'Khu A',
    extra_beds_limit: 0,
    grid_row: 0,
    grid_column: 0,
    owner_room: '',
    linked_room: '',
    is_internal: false,
    notes: ''
  })
  isRoomModalOpen.value = true
}

const openEditRoomModal = (room) => {
  isEditMode.value = true
  currentRoomId.value = room.id
  Object.assign(roomFormState, {
    room_number: room.room_number,
    room_form_id: room.room_form_id,
    room_class_id: room.room_class_id,
    max_guests: room.max_guests,
    floor: String(room.floor),
    area: room.area || 'Khu A',
    extra_beds_limit: room.extra_beds_limit,
    grid_row: room.grid_row,
    grid_column: room.grid_column,
    owner_room: room.owner_room || '',
    linked_room: room.linked_room || '',
    is_internal: room.is_internal,
    notes: room.notes || ''
  })
  isRoomModalOpen.value = true
}

const saveRoom = async () => {
  if (!roomFormState.room_number || !roomFormState.floor) {
    uiStore.showToast('Vui lòng điền các trường bắt buộc (*)', 'warning')
    return
  }
  loading.value = true
  try {
    if (isEditMode.value) {
      await http.put(`/rooms/${currentRoomId.value}`, roomFormState)
      uiStore.showToast('Cập nhật phòng thành công!', 'success')
    } else {
      await http.post('/rooms', roomFormState)
      uiStore.showToast('Thêm phòng mới thành công!', 'success')
    }
    isRoomModalOpen.value = false
    fetchRooms()
  } catch (err) {
    console.error(err)
    uiStore.showToast('Có lỗi xảy ra khi lưu phòng', 'error')
  } finally {
    loading.value = false
  }
}

const deleteRoom = async (roomId) => {
  const confirmed = await uiStore.confirm({
    title: 'Xác nhận xóa',
    message: 'Bạn có chắc chắn muốn xóa phòng này?',
    confirmText: 'Xóa',
    cancelText: 'Hủy'
  })
  if (!confirmed) return
  try {
    await http.delete(`/rooms/${roomId}`)
    uiStore.showToast('Xóa phòng thành công!', 'success')
    fetchRooms()
  } catch (err) {
    console.error(err)
    uiStore.showToast('Không thể xóa phòng này', 'error')
  }
}

// Room Class CRUD
const openAddRoomClassModal = () => {
  isEditRoomClassMode.value = false
  currentRoomClassId.value = null
  Object.assign(roomClassFormState, {
    name: '',
    code: '',
    color: '#ffffff',
    is_active: true,
    group: 'hotel',
    notes: '',
    image: null,
    imagePreview: null
  })
  if (roomClassImageInput.value) {
    roomClassImageInput.value.value = ''
  }
  isRoomClassModalOpen.value = true
}

const openEditRoomClassModal = (rc) => {
  isEditRoomClassMode.value = true
  currentRoomClassId.value = rc.id
  Object.assign(roomClassFormState, {
    name: rc.name,
    code: rc.code,
    color: rc.color || '#ffffff',
    is_active: rc.is_active,
    group: rc.group || 'hotel',
    notes: rc.notes || '',
    image: null,
    imagePreview: rc.image_url || null
  })
  if (roomClassImageInput.value) {
    roomClassImageInput.value.value = ''
  }
  isRoomClassModalOpen.value = true
}

const triggerRoomClassImageUpload = () => {
  if (roomClassImageInput.value) {
    roomClassImageInput.value.click()
  }
}

const handleRoomClassImageUpload = (e) => {
  const file = e.target.files[0]
  if (!file) return
  roomClassFormState.image = file
  const reader = new FileReader()
  reader.onload = (event) => {
    roomClassFormState.imagePreview = event.target.result
  }
  reader.readAsDataURL(file)
}

const removeRoomClassImage = () => {
  roomClassFormState.image = null
  roomClassFormState.imagePreview = null
  if (roomClassImageInput.value) {
    roomClassImageInput.value.value = ''
  }
}

const saveRoomClass = async () => {
  if (!roomClassFormState.name || !roomClassFormState.code) {
    uiStore.showToast('Vui lòng điền các trường bắt buộc (*)', 'warning')
    return
  }
  loading.value = true
  try {
    const formData = new FormData()
    formData.append('name', roomClassFormState.name)
    formData.append('code', roomClassFormState.code)
    formData.append('color', roomClassFormState.color)
    formData.append('is_active', roomClassFormState.is_active ? '1' : '0')
    formData.append('group', roomClassFormState.group)
    formData.append('notes', roomClassFormState.notes)
    if (roomClassFormState.image) {
      formData.append('image', roomClassFormState.image)
    }

    if (isEditRoomClassMode.value) {
      formData.append('_method', 'PUT')
      await http.post(`/room-classes/${currentRoomClassId.value}`, formData, {
        headers: {
          'Content-Type': 'multipart/form-data'
        }
      })
      uiStore.showToast('Cập nhật loại phòng thành công!', 'success')
    } else {
      await http.post('/room-classes', formData, {
        headers: {
          'Content-Type': 'multipart/form-data'
        }
      })
      uiStore.showToast('Thêm loại phòng thành công!', 'success')
    }
    isRoomClassModalOpen.value = false
    fetchRoomClasses()
  } catch (err) {
    console.error(err)
    const errorMsg = err.response?.data?.message || 'Có lỗi xảy ra khi lưu loại phòng'
    uiStore.showToast(errorMsg, 'error')
  } finally {
    loading.value = false
  }
}

const deleteRoomClass = async (id) => {
  const confirmed = await uiStore.confirm({
    title: 'Xác nhận xóa',
    message: 'Bạn có chắc chắn muốn xóa loại phòng này? Tất cả các phòng thuộc loại phòng này cũng sẽ bị xóa.',
    confirmText: 'Xóa',
    cancelText: 'Hủy'
  })
  if (!confirmed) return
  try {
    await http.delete(`/room-classes/${id}`)
    uiStore.showToast('Xóa loại phòng thành công!', 'success')
    fetchRoomClasses()
    fetchRooms()
  } catch (err) {
    console.error(err)
    const errorMsg = err.response?.data?.message || 'Không thể xóa loại phòng này'
    uiStore.showToast(errorMsg, 'error')
  }
}

// Room Form CRUD
const openAddRoomFormModal = () => {
  isEditRoomFormMode.value = false
  currentRoomFormId.value = null
  Object.assign(roomFormStateData, {
    name: '',
    max_adults: 0
  })
  isRoomFormModalOpen.value = true
}

const openEditRoomFormModal = (rf) => {
  isEditRoomFormMode.value = true
  currentRoomFormId.value = rf.id
  Object.assign(roomFormStateData, {
    name: rf.name,
    max_adults: rf.max_adults
  })
  isRoomFormModalOpen.value = true
}

const saveRoomForm = async () => {
  if (!roomFormStateData.name) {
    uiStore.showToast('Vui lòng nhập tên dạng phòng (*)', 'warning')
    return
  }
  loading.value = true
  try {
    if (isEditRoomFormMode.value) {
      await http.put(`/room-forms/${currentRoomFormId.value}`, roomFormStateData)
      uiStore.showToast('Cập nhật dạng phòng thành công!', 'success')
    } else {
      await http.post('/room-forms', roomFormStateData)
      uiStore.showToast('Thêm dạng phòng thành công!', 'success')
    }
    isRoomFormModalOpen.value = false
    fetchRoomForms()
  } catch (err) {
    console.error(err)
    const errorMsg = err.response?.data?.message || 'Có lỗi xảy ra khi lưu dạng phòng'
    uiStore.showToast(errorMsg, 'error')
  } finally {
    loading.value = false
  }
}

const deleteRoomForm = async (id) => {
  const confirmed = await uiStore.confirm({
    title: 'Xác nhận xóa',
    message: 'Bạn có chắc chắn muốn xóa dạng phòng này? Tất cả các phòng thuộc dạng này cũng sẽ bị xóa.',
    confirmText: 'Xóa',
    cancelText: 'Hủy'
  })
  if (!confirmed) return
  try {
    await http.delete(`/room-forms/${id}`)
    uiStore.showToast('Xóa dạng phòng thành công!', 'success')
    fetchRoomForms()
    fetchRooms()
  } catch (err) {
    console.error(err)
    const errorMsg = err.response?.data?.message || 'Không thể xóa dạng phòng này'
    uiStore.showToast(errorMsg, 'error')
  }
}

// Standard Rate CRUD
const openAddStandardRateModal = () => {
  isEditStandardRateMode.value = false
  currentStandardRateId.value = null
  Object.assign(standardRateFormState, {
    room_class_id: roomClasses.value.filter(item => item.is_active)[0]?.id || roomClasses.value[0]?.id || '',
    room_form_id: roomForms.value[0]?.id || '',
    room_price: 0,
    extra_bed_price: 0
  })
  isStandardRateModalOpen.value = true
}

const openEditStandardRateModal = (rate) => {
  isEditStandardRateMode.value = true
  currentStandardRateId.value = rate.id
  Object.assign(standardRateFormState, {
    room_class_id: rate.room_class_id,
    room_form_id: rate.room_form_id,
    room_price: rate.room_price,
    extra_bed_price: rate.extra_bed_price
  })
  isStandardRateModalOpen.value = true
}

const saveStandardRate = async () => {
  if (!standardRateFormState.room_class_id || !standardRateFormState.room_form_id) {
    uiStore.showToast('Vui lòng chọn Loại phòng và Dạng phòng (*)', 'warning')
    return
  }
  loading.value = true
  try {
    if (isEditStandardRateMode.value) {
      await http.put(`/standard-rates/${currentStandardRateId.value}`, standardRateFormState)
      uiStore.showToast('Cập nhật giá phòng chuẩn thành công!', 'success')
    } else {
      await http.post('/standard-rates', standardRateFormState)
      uiStore.showToast('Thêm giá phòng chuẩn thành công!', 'success')
    }
    isStandardRateModalOpen.value = false
    fetchStandardRates()
  } catch (err) {
    console.error(err)
    const errorMsg = err.response?.data?.message || 'Có lỗi xảy ra khi lưu giá phòng chuẩn'
    uiStore.showToast(errorMsg, 'error')
  } finally {
    loading.value = false
  }
}

const deleteStandardRate = async (id) => {
  const confirmed = await uiStore.confirm({
    title: 'Xác nhận xóa',
    message: 'Bạn có chắc chắn muốn xóa giá phòng chuẩn này?',
    confirmText: 'Xóa',
    cancelText: 'Hủy'
  })
  if (!confirmed) return
  try {
    await http.delete(`/standard-rates/${id}`)
    uiStore.showToast('Xóa giá phòng chuẩn thành công!', 'success')
    fetchStandardRates()
  } catch (err) {
    console.error(err)
    uiStore.showToast('Không thể xóa giá phòng chuẩn', 'error')
  }
}

const toggleRoomClassActive = async (rc) => {
  try {
    const updatedStatus = !rc.is_active
    await http.put(`/room-classes/${rc.id}`, {
      name: rc.name,
      code: rc.code,
      color: rc.color,
      is_active: updatedStatus ? '1' : '0',
      group: rc.group
    })
    rc.is_active = updatedStatus
    uiStore.showToast('Cập nhật trạng thái sử dụng thành công!', 'success')
    fetchRooms()
    fetchStandardRates()
  } catch (err) {
    console.error(err)
    const errorMsg = err.response?.data?.message || 'Không thể cập nhật trạng thái sử dụng'
    uiStore.showToast(errorMsg, 'error')
    fetchRoomClasses()
  }
}

const formatCurrency = (val) => {
  if (val === null || val === undefined || isNaN(Number(val))) return '0 ₫'
  return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(Number(val))
}

// Column selectors states and helper functions
const isRateColumnSelectorOpen = ref(false)
const isRoomColumnSelectorOpen = ref(false)

const rateColumns = ref([
  { id: 'room_class', label: 'Loại phòng', visible: true },
  { id: 'room_form', label: 'Dạng phòng', visible: true },
  { id: 'room_price', label: 'Giá phòng', visible: true },
  { id: 'extra_bed_price', label: 'Giá thêm giường', visible: true },
  { id: 'action', label: 'Hành động', visible: true },
])

const roomColumns = ref([
  { id: 'room_number', label: 'PHÒNG', visible: true },
  { id: 'room_form', label: 'DẠNG PHÒNG', visible: true },
  { id: 'max_guests', label: 'Khách hàng', visible: true },
  { id: 'extra_beds_limit', label: 'Thêm giường', visible: true },
  { id: 'area', label: 'Khu vực', visible: true },
  { id: 'floor', label: 'Tầng', visible: true },
  { id: 'grid_row', label: 'Hàng', visible: true },
  { id: 'grid_column', label: 'Cột', visible: true },
  { id: 'linked_room', label: 'Liên kết', visible: true },
  { id: 'is_internal', label: 'Phòng nội bộ', visible: true },
  { id: 'owner_room', label: 'Phòng chủ sở hữu', visible: true },
  { id: 'notes', label: 'Ghi chú', visible: true },
  { id: 'action', label: 'Hành động', visible: true },
])

const isRateColumnVisible = (columnId) => {
  const col = rateColumns.value.find(c => c.id === columnId)
  return col ? col.visible : true
}

const isRoomColumnVisible = (columnId) => {
  const col = roomColumns.value.find(c => c.id === columnId)
  return col ? col.visible : true
}

const closeAllPopovers = (e) => {
  if (!e.target.closest('.popover-container')) {
    isRateColumnSelectorOpen.value = false
    isRoomColumnSelectorOpen.value = false
  }
}

// Fetch backend configurations
const fetchConfigs = async () => {
  try {
    const res = await http.get('/hotel-configs')
    const configs = res.data.data || []
    
    const rateConfig = configs.find(c => c.name === 'rate_columns_visibility')
    if (rateConfig) {
      rateConfigRecord.value = rateConfig
      if (rateConfig.value) {
        const visibleIds = rateConfig.value.split(',')
        rateColumns.value.forEach(c => {
          c.visible = visibleIds.includes(c.id)
        })
      }
    }
    
    const roomConfig = configs.find(c => c.name === 'room_columns_visibility')
    if (roomConfig) {
      roomConfigRecord.value = roomConfig
      if (roomConfig.value) {
        const visibleIds = roomConfig.value.split(',')
        roomColumns.value.forEach(c => {
          c.visible = visibleIds.includes(c.id)
        })
      }
    }
  } catch (err) {
    console.error('Lỗi khi tải cấu hình ẩn hiện cột:', err)
  }
}

// Save config helper
const saveConfig = async (type, columns, recordRef) => {
  const name = type === 'rate' ? 'rate_columns_visibility' : 'room_columns_visibility'
  const value = columns.filter(c => c.visible).map(c => c.id).join(',')
  
  try {
    if (recordRef.value) {
      const res = await http.put(`/hotel-configs/${recordRef.value.id}`, {
        name,
        value,
        description: `Cột hiển thị của bảng ${type === 'rate' ? 'giá phòng chuẩn' : 'phòng thực tế'}`
      })
      recordRef.value = res.data.data
    } else {
      const res = await http.post('/hotel-configs', {
        name,
        value,
        description: `Cột hiển thị của bảng ${type === 'rate' ? 'giá phòng chuẩn' : 'phòng thực tế'}`
      })
      recordRef.value = res.data.data
    }
  } catch (err) {
    console.error(`Lỗi khi lưu cấu hình ${name}:`, err)
  }
}

// Watchers for saving settings changes
watch(rateColumns, (newCols) => {
  if (isLoaded.value) {
    saveConfig('rate', newCols, rateConfigRecord)
  }
}, { deep: true })

watch(roomColumns, (newCols) => {
  if (isLoaded.value) {
    saveConfig('room', newCols, roomConfigRecord)
  }
}, { deep: true })

// Grouped table expand states
const expandedRoomClasses = reactive({})
const toggleRoomClassExpand = (classId) => {
  expandedRoomClasses[classId] = !expandedRoomClasses[classId]
}

// Compute rooms grouped by room_class for the current page
const groupedRooms = computed(() => {
  const classMap = {}
  
  // Initialize groups based on all known roomClasses to maintain display order
  roomClasses.value.forEach(rc => {
    classMap[rc.id] = {
      roomClass: rc,
      rooms: [],
      count: rooms.value.filter(r => r.room_class_id === rc.id).length
    }
  })
  
  // Fallback group for rooms without class
  const noClassId = 'no-class'
  classMap[noClassId] = {
    roomClass: { id: noClassId, name: 'Chưa phân loại', code: '-' },
    rooms: [],
    count: rooms.value.filter(r => !r.room_class_id).length
  }
  
  // Group only the paginated rooms to preserve page size limits
  paginatedRooms.value.forEach(r => {
    const classId = r.room_class_id || noClassId
    if (!classMap[classId]) {
      classMap[classId] = {
        roomClass: r.room_class || { id: classId, name: `Loại phòng ${classId}`, code: '' },
        rooms: [],
        count: rooms.value.filter(r => r.room_class_id === classId).length
      }
    }
    classMap[classId].rooms.push(r)
  })
  
  // Filter out empty groups (meaning groups that have no rooms on the current page)
  return Object.values(classMap).filter(g => g.rooms.length > 0)
})

// Quick toggle for is_internal room field
const toggleRoomInternal = async (room) => {
  try {
    const updatedVal = !room.is_internal
    const payload = {
      room_number: room.room_number,
      room_form_id: room.room_form_id,
      room_class_id: room.room_class_id,
      max_guests: room.max_guests,
      floor: String(room.floor),
      area: room.area,
      extra_beds_limit: room.extra_beds_limit,
      grid_row: room.grid_row,
      grid_column: room.grid_column,
      owner_room: room.owner_room,
      linked_room: room.linked_room,
      is_internal: updatedVal,
      status: room.status,
      notes: room.notes
    }
    await http.put(`/rooms/${room.id}`, payload)
    room.is_internal = updatedVal
    uiStore.showToast('Cập nhật trạng thái phòng nội bộ thành công!', 'success')
  } catch (err) {
    console.error(err)
    const errorMsg = err.response?.data?.message || 'Không thể cập nhật trạng thái phòng nội bộ'
    uiStore.showToast(errorMsg, 'error')
  }
}
</script>

<template>
  <div class="flex-1 flex flex-col gap-4">
    <!-- Sub Navigation Tabs Bar -->
    <div class="border-b border-slate-200 shrink-0">
      <div class="flex flex-wrap gap-1">
        <button 
          v-for="tab in roomTabs" 
          :key="tab"
          @click="activeRoomTab = tab"
          class="px-4 py-2 text-sm font-bold border-none bg-transparent cursor-pointer relative pb-3 transition-colors"
          :class="activeRoomTab === tab ? 'text-sky-600 border-b-2 border-sky-500' : 'text-slate-500 hover:text-slate-800'"
        >
          {{ tab }}
        </button>
      </div>
    </div>

    <!-- Detail Card Content -->
    <div class="flex-1 bg-white rounded-xl shadow-xs border border-slate-200 p-6 overflow-auto min-h-0 relative">
      <!-- Loading State (Premium 3D Rotating Rings Loader) -->
      <div v-if="!isLoaded" class="flex items-center justify-center absolute inset-0 bg-white/70 z-30">
        <div class="loader">
          <div class="inner one"></div>
          <div class="inner two"></div>
          <div class="inner three"></div>
        </div>
      </div>
      <!-- Sub Tab 1: Room Classes -->
      <div v-if="activeRoomTab === 'TÊN LOẠI PHÒNG'" class="overflow-x-auto">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-sm font-black text-slate-600 uppercase">Danh sách Tên loại phòng</h3>
          <button @click="openAddRoomClassModal" class="px-3 py-1.5 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded text-sm font-bold border-none cursor-pointer">+ Thêm loại</button>
        </div>
        <table class="w-full text-sm text-left border-collapse">
          <thead>
            <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-xs">
              <th class="p-3">Tên loại phòng</th>
              <th class="p-3">Tên viết tắt</th>
              <th class="p-3">Màu sắc</th>
              <th class="p-3">Có sử dụng</th>
              <th class="p-3">Nhóm loại phòng</th>
              <th class="p-3 text-right">Hành động</th>
            </tr>
          </thead>
          <tbody>
            <tr 
              v-for="rc in paginatedRoomClasses" 
              :key="rc.id" 
              @click="openEditRoomClassModal(rc)" 
              class="border-b border-slate-100 hover:bg-slate-50/55 cursor-pointer"
            >
              <td class="p-3 font-bold text-slate-800">{{ rc.name }}</td>
              <td class="p-3 font-semibold text-slate-500">{{ rc.code }}</td>
              <td class="p-3">
                <div class="flex items-center gap-2">
                  <span class="w-4 h-4 rounded-full border border-slate-200 block shadow-xs" :style="{ backgroundColor: rc.color }"></span>
                  <span class="font-mono text-slate-400">{{ rc.color }}</span>
                </div>
              </td>
              <td class="p-3">
                <label @click.stop class="relative inline-flex items-center cursor-pointer">
                  <input type="checkbox" :checked="rc.is_active" @change="toggleRoomClassActive(rc)" class="sr-only peer" />
                  <div class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500"></div>
                </label>
              </td>
              <td class="p-3 text-slate-500 font-semibold uppercase text-xs">{{ rc.group }}</td>
              <td class="p-3 text-right">
                <button @click.stop="deleteRoomClass(rc.id)" class="p-1 hover:bg-red-50 rounded text-red-500 bg-transparent border-none cursor-pointer">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7" /></svg>
                </button>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Pagination controls -->
        <div class="flex items-center justify-end gap-2 mt-4 select-none">
          <button 
            @click="classPage = Math.max(1, classPage - 1)" 
            :disabled="classPage === 1"
            class="w-8 h-8 flex items-center justify-center border border-slate-200 bg-white rounded text-slate-500 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer font-bold"
          >
            &lt;
          </button>
          
          <button 
            v-for="p in totalClassPages" 
            :key="p"
            @click="classPage = p"
            class="w-8 h-8 flex items-center justify-center border rounded font-semibold cursor-pointer text-sm"
            :class="classPage === p ? 'border-sky-500 text-sky-600 font-bold bg-sky-50/20' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
          >
            {{ p }}
          </button>
          
          <button 
            @click="classPage = Math.min(totalClassPages, classPage + 1)" 
            :disabled="classPage === totalClassPages"
            class="w-8 h-8 flex items-center justify-center border border-slate-200 bg-white rounded text-slate-500 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer font-bold"
          >
            &gt;
          </button>
          
          <select 
            v-model="classPageSize" 
            @change="classPage = 1"
            class="border border-slate-200 rounded p-1.5 bg-white text-slate-600 font-semibold text-xs cursor-pointer focus:outline-sky-400"
          >
            <option :value="10">10 / page</option>
            <option :value="25">25 / page</option>
            <option :value="50">50 / page</option>
            <option :value="100">100 / page</option>
          </select>
        </div>
      </div>

      <!-- Sub Tab 2: Room Forms -->
      <div v-if="activeRoomTab === 'DẠNG PHÒNG'" class="overflow-x-auto">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-sm font-black text-slate-600 uppercase">Danh sách Dạng phòng (Giường)</h3>
          <button @click="openAddRoomFormModal" class="px-3 py-1.5 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded text-sm font-bold border-none cursor-pointer">+ Thêm dạng</button>
        </div>
        <table class="w-full text-sm text-left border-collapse">
          <thead>
            <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-xs">
              <th class="p-3">Dạng phòng</th>
              <th class="p-3">Người lớn</th>
              <th class="p-3 text-right">Hành động</th>
            </tr>
          </thead>
          <tbody>
            <tr 
              v-for="rf in paginatedRoomForms" 
              :key="rf.id" 
              @click="openEditRoomFormModal(rf)" 
              class="border-b border-slate-100 hover:bg-slate-50/55 cursor-pointer"
            >
              <td class="p-3 font-bold text-slate-800">{{ rf.name }}</td>
              <td class="p-3 font-bold text-slate-600">{{ rf.max_adults }}</td>
              <td class="p-3 text-right">
                <button @click.stop="deleteRoomForm(rf.id)" class="p-1 hover:bg-red-50 rounded text-red-500 bg-transparent border-none cursor-pointer">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7" /></svg>
                </button>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Pagination controls -->
        <div class="flex items-center justify-end gap-2 mt-4 select-none">
          <button 
            @click="formPage = Math.max(1, formPage - 1)" 
            :disabled="formPage === 1"
            class="w-8 h-8 flex items-center justify-center border border-slate-200 bg-white rounded text-slate-500 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer font-bold"
          >
            &lt;
          </button>
          
          <button 
            v-for="p in totalFormPages" 
            :key="p"
            @click="formPage = p"
            class="w-8 h-8 flex items-center justify-center border rounded font-semibold cursor-pointer text-sm"
            :class="formPage === p ? 'border-sky-500 text-sky-600 font-bold bg-sky-50/20' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
          >
            {{ p }}
          </button>
          
          <button 
            @click="formPage = Math.min(totalFormPages, formPage + 1)" 
            :disabled="formPage === totalFormPages"
            class="w-8 h-8 flex items-center justify-center border border-slate-200 bg-white rounded text-slate-500 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer font-bold"
          >
            &gt;
          </button>
          
          <select 
            v-model="formPageSize" 
            @change="formPage = 1"
            class="border border-slate-200 rounded p-1.5 bg-white text-slate-600 font-semibold text-xs cursor-pointer focus:outline-sky-400"
          >
            <option :value="10">10 / page</option>
            <option :value="25">25 / page</option>
            <option :value="50">50 / page</option>
            <option :value="100">100 / page</option>
          </select>
        </div>
      </div>

      <!-- Sub Tab 3: Standard Rates -->
      <div v-if="activeRoomTab === 'GIÁ PHÒNG CHUẨN'">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-sm font-black text-slate-600 uppercase">Danh sách Giá phòng chuẩn</h3>
          <div class="flex items-center gap-2 relative popover-container">
            <button @click="openAddStandardRateModal" class="px-3 py-1.5 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded text-sm font-bold border-none cursor-pointer">+ Thêm</button>
            <button 
              @click.stop="isRateColumnSelectorOpen = !isRateColumnSelectorOpen"
              class="p-1.5 bg-white border border-slate-200 text-slate-500 hover:text-slate-700 hover:bg-slate-50 rounded cursor-pointer flex items-center justify-center transition-colors shadow-xs"
              title="Ẩn/hiện cột"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
              </svg>
            </button>

            <!-- Column Selector Dropdown -->
            <div 
              v-if="isRateColumnSelectorOpen" 
              class="absolute right-0 top-full mt-1.5 z-45 bg-white border border-slate-200 rounded-lg shadow-lg p-2.5 min-w-[180px] max-h-[300px] overflow-y-auto flex flex-col gap-1"
            >
              <label v-for="col in rateColumns" :key="col.id" class="flex items-center gap-2 p-1.5 hover:bg-slate-50 cursor-pointer rounded select-none">
                <input type="checkbox" v-model="col.visible" class="rounded text-sky-500 border-slate-300 focus:ring-sky-400 w-3.5 h-3.5 cursor-pointer" />
                <span class="text-xs text-slate-700 font-semibold">{{ col.label }}</span>
              </label>
            </div>
          </div>
        </div>
        <div class="overflow-x-auto border border-slate-200 rounded-lg shadow-sm">
          <table class="w-full text-sm text-left border-collapse">
            <thead>
              <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-xs">
                <th v-if="isRateColumnVisible('room_class')" class="p-3">Loại phòng</th>
              <th v-if="isRateColumnVisible('room_form')" class="p-3">Dạng phòng</th>
              <th v-if="isRateColumnVisible('room_price')" class="p-3">Giá phòng</th>
              <th v-if="isRateColumnVisible('extra_bed_price')" class="p-3">Giá thêm giường</th>
              <th v-if="isRateColumnVisible('action')" class="p-3 text-right">Hành động</th>
            </tr>
          </thead>
          <tbody>
            <tr 
              v-for="rate in paginatedStandardRates" 
              :key="rate.id" 
              @click="openEditStandardRateModal(rate)" 
              class="border-b border-slate-100 hover:bg-slate-50/55 cursor-pointer"
            >
              <td v-if="isRateColumnVisible('room_class')" class="p-3 font-bold text-slate-800">{{ rate.room_class?.name }}</td>
              <td v-if="isRateColumnVisible('room_form')" class="p-3 font-bold text-slate-600">{{ rate.room_form?.name }}</td>
              <td v-if="isRateColumnVisible('room_price')" class="p-3 font-extrabold text-sky-700">{{ formatCurrency(rate.room_price) }}</td>
              <td v-if="isRateColumnVisible('extra_bed_price')" class="p-3 font-bold text-amber-600">{{ formatCurrency(rate.extra_bed_price) }}</td>
              <td v-if="isRateColumnVisible('action')" class="p-3 text-right">
                <button @click.stop="deleteStandardRate(rate.id)" class="p-1 hover:bg-red-50 rounded text-red-500 bg-transparent border-none cursor-pointer">
                  <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7" /></svg>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
        </div>

        <!-- Pagination controls -->
        <div class="flex items-center justify-end gap-2 mt-4 select-none">
          <button 
            @click="ratePage = Math.max(1, ratePage - 1)" 
            :disabled="ratePage === 1"
            class="w-8 h-8 flex items-center justify-center border border-slate-200 bg-white rounded text-slate-500 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer font-bold"
          >
            &lt;
          </button>
          
          <button 
            v-for="p in totalRatePages" 
            :key="p"
            @click="ratePage = p"
            class="w-8 h-8 flex items-center justify-center border rounded font-semibold cursor-pointer text-sm"
            :class="ratePage === p ? 'border-sky-500 text-sky-600 font-bold bg-sky-50/20' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
          >
            {{ p }}
          </button>
          
          <button 
            @click="ratePage = Math.min(totalRatePages, ratePage + 1)" 
            :disabled="ratePage === totalRatePages"
            class="w-8 h-8 flex items-center justify-center border border-slate-200 bg-white rounded text-slate-500 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer font-bold"
          >
            &gt;
          </button>
          
          <select 
            v-model="ratePageSize" 
            @change="ratePage = 1"
            class="border border-slate-200 rounded p-1.5 bg-white text-slate-600 font-semibold text-xs cursor-pointer focus:outline-sky-400"
          >
            <option :value="10">10 / page</option>
            <option :value="25">25 / page</option>
            <option :value="50">50 / page</option>
            <option :value="100">100 / page</option>
          </select>
        </div>
      </div>

      <!-- Sub Tab 4: Rooms -->
      <div v-if="activeRoomTab === 'PHÒNG'">
        <div class="flex justify-between items-center mb-4">
          <div class="flex gap-2 relative popover-container">
            <button 
              @click="openAddRoomModal"
              class="px-3 py-1.5 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-lg text-sm font-bold flex items-center gap-1 border-none cursor-pointer shadow-xs transition-colors"
            >
              + Thêm
            </button>
            <button class="px-3 py-1.5 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-lg text-sm font-bold flex items-center gap-1 border-none cursor-pointer shadow-xs transition-colors">
              Nhập excel
            </button>
            <button 
              @click.stop="isRoomColumnSelectorOpen = !isRoomColumnSelectorOpen"
              class="p-2 bg-white border border-slate-200 text-slate-500 hover:text-slate-700 hover:bg-slate-50 rounded-lg cursor-pointer flex items-center justify-center transition-colors shadow-xs"
              title="Ẩn/hiện cột"
            >
              <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4" />
              </svg>
            </button>

            <!-- Column Selector Dropdown -->
            <div 
              v-if="isRoomColumnSelectorOpen" 
              class="absolute right-0 top-full mt-1.5 z-45 bg-white border border-slate-200 rounded-lg shadow-lg p-2.5 min-w-[180px] max-h-[300px] overflow-y-auto flex flex-col gap-1"
            >
              <label v-for="col in roomColumns" :key="col.id" class="flex items-center gap-2 p-1.5 hover:bg-slate-50 cursor-pointer rounded select-none">
                <input type="checkbox" v-model="col.visible" class="rounded text-sky-500 border-slate-300 focus:ring-sky-400 w-3.5 h-3.5 cursor-pointer" />
                <span class="text-xs text-slate-700 font-semibold">{{ col.label }}</span>
              </label>
            </div>
          </div>
        </div>
        
        <div class="overflow-x-auto border border-slate-200 rounded-lg shadow-sm">
          <table class="w-full text-sm text-left border-collapse">
            <thead>
              <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-bold uppercase text-xs">
                <th v-if="isRoomColumnVisible('room_number')" class="p-3">
                  <div class="flex items-center gap-1.5">
                    <span>PHÒNG</span>
                    <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                  </div>
                </th>
                <th v-if="isRoomColumnVisible('room_form')" class="p-3">DẠNG PHÒNG</th>
                <th v-if="isRoomColumnVisible('max_guests')" class="p-3 text-center">Khách hàng</th>
                <th v-if="isRoomColumnVisible('extra_beds_limit')" class="p-3 text-center">Thêm giường</th>
                <th v-if="isRoomColumnVisible('area')" class="p-3">Khu vực</th>
                <th v-if="isRoomColumnVisible('floor')" class="p-3">Tầng</th>
                <th v-if="isRoomColumnVisible('grid_row')" class="p-3 text-center">Hàng</th>
                <th v-if="isRoomColumnVisible('grid_column')" class="p-3 text-center">Cột</th>
                <th v-if="isRoomColumnVisible('linked_room')" class="p-3">Liên kết</th>
                <th v-if="isRoomColumnVisible('is_internal')" class="p-3">Phòng nội bộ</th>
                <th v-if="isRoomColumnVisible('owner_room')" class="p-3">Phòng chủ sở hữu</th>
                <th v-if="isRoomColumnVisible('notes')" class="p-3">Ghi chú</th>
                <th v-if="isRoomColumnVisible('action')" class="p-3 text-right">Hành động</th>
              </tr>
            </thead>
            <tbody>
              <!-- Render Grouped Rooms -->
              <template v-for="g in groupedRooms" :key="g.roomClass.id">
                <!-- Group Header Row -->
                <tr class="border-b border-slate-200 bg-slate-50/40 select-none">
                  <!-- PHÒNG Column -->
                  <td v-if="isRoomColumnVisible('room_number')" class="p-3 font-bold text-slate-700">
                    <div class="flex items-center gap-3">
                      <button 
                        @click.stop="toggleRoomClassExpand(g.roomClass.id)" 
                        class="w-5 h-5 flex items-center justify-center bg-sky-100 hover:bg-sky-200 text-sky-600 border border-sky-300 rounded cursor-pointer transition-colors shrink-0"
                      >
                        <svg v-if="!expandedRoomClasses[g.roomClass.id]" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12h-15" />
                        </svg>
                      </button>
                      <span>{{ g.roomClass.name || 'Loại phòng' }}</span>
                    </div>
                  </td>
                  
                  <!-- DẠNG PHÒNG Column -->
                  <td v-if="isRoomColumnVisible('room_form')" class="p-3 font-bold text-slate-800">
                    {{ g.count }}
                  </td>
                  
                  <!-- Other empty cells to keep vertical grid borders intact -->
                  <td v-if="isRoomColumnVisible('max_guests')" class="p-3"></td>
                  <td v-if="isRoomColumnVisible('extra_beds_limit')" class="p-3"></td>
                  <td v-if="isRoomColumnVisible('area')" class="p-3"></td>
                  <td v-if="isRoomColumnVisible('floor')" class="p-3"></td>
                  <td v-if="isRoomColumnVisible('grid_row')" class="p-3"></td>
                  <td v-if="isRoomColumnVisible('grid_column')" class="p-3"></td>
                  <td v-if="isRoomColumnVisible('linked_room')" class="p-3"></td>
                  <td v-if="isRoomColumnVisible('is_internal')" class="p-3"></td>
                  <td v-if="isRoomColumnVisible('owner_room')" class="p-3"></td>
                  <td v-if="isRoomColumnVisible('notes')" class="p-3"></td>
                  <td v-if="isRoomColumnVisible('action')" class="p-3"></td>
                </tr>
                
                <!-- Expanded Sub-rows (Rooms in Group) -->
                <template v-if="expandedRoomClasses[g.roomClass.id]">
                  <tr 
                    v-for="r in g.rooms" 
                    :key="r.id" 
                    @click="openEditRoomModal(r)" 
                    class="border-b border-slate-100 hover:bg-slate-50/55 cursor-pointer"
                  >
                    <!-- PHÒNG Column (Indented Room Number) -->
                    <td v-if="isRoomColumnVisible('room_number')" class="p-3 font-black text-slate-700 text-sm pl-11">
                      {{ r.room_number }}
                    </td>
                    
                    <!-- DẠNG PHÒNG Column -->
                    <td v-if="isRoomColumnVisible('room_form')" class="p-3 font-semibold text-slate-600">
                      {{ r.room_form?.name }}
                    </td>
                    
                    <!-- Khách hàng -->
                    <td v-if="isRoomColumnVisible('max_guests')" class="p-3 text-slate-600 font-medium text-center">
                      {{ r.max_guests }}
                    </td>
                    
                    <!-- Thêm giường -->
                    <td v-if="isRoomColumnVisible('extra_beds_limit')" class="p-3 text-slate-500 font-medium text-center">
                      {{ r.extra_beds_limit }}
                    </td>
                    
                    <!-- Khu vực -->
                    <td v-if="isRoomColumnVisible('area')" class="p-3 text-slate-500 font-medium">
                      {{ r.area || 'Khu A' }}
                    </td>
                    
                    <!-- Tầng -->
                    <td v-if="isRoomColumnVisible('floor')" class="p-3 text-slate-500 font-medium">
                      Tầng {{ r.floor }}
                    </td>
                    
                    <!-- Hàng -->
                    <td v-if="isRoomColumnVisible('grid_row')" class="p-3 text-slate-500 font-mono text-center">
                      {{ r.grid_row }}
                    </td>
                    
                    <!-- Cột -->
                    <td v-if="isRoomColumnVisible('grid_column')" class="p-3 text-slate-500 font-mono text-center">
                      {{ r.grid_column }}
                    </td>
                    
                    <!-- Liên kết -->
                    <td v-if="isRoomColumnVisible('linked_room')" class="p-3 text-slate-500 font-medium">
                      {{ r.linked_room || '-' }}
                    </td>
                    
                    <!-- Phòng nội bộ (Toggle switch instead of badge) -->
                    <td v-if="isRoomColumnVisible('is_internal')" class="p-3" @click.stop>
                      <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" :checked="r.is_internal" @change="toggleRoomInternal(r)" class="sr-only peer" />
                        <div class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500"></div>
                      </label>
                    </td>
                    
                    <!-- Phòng chủ sở hữu -->
                    <td v-if="isRoomColumnVisible('owner_room')" class="p-3 text-slate-500 font-medium">
                      {{ r.owner_room || '-' }}
                    </td>
                    
                    <!-- Ghi chú -->
                    <td v-if="isRoomColumnVisible('notes')" class="p-3 text-slate-400 italic max-w-[120px] truncate" :title="r.notes">
                      {{ r.notes || '-' }}
                    </td>
                    
                    <!-- Hành động (Delete Button styled like a trash can inside a square light-blue button) -->
                    <td v-if="isRoomColumnVisible('action')" class="p-3 text-right" @click.stop>
                      <div class="flex justify-end">
                        <button 
                          @click="deleteRoom(r.id)"
                          class="w-7 h-7 flex items-center justify-center bg-sky-100 hover:bg-sky-200 border border-sky-300 rounded-lg text-sky-600 hover:text-sky-700 cursor-pointer transition-colors shadow-xs"
                          title="Xóa phòng"
                        >
                          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                          </svg>
                        </button>
                      </div>
                    </td>
                  </tr>
                </template>
              </template>
            </tbody>
          </table>
        </div>

        <!-- Pagination controls with total room count in footer -->
        <div class="flex items-center justify-between mt-4 select-none">
          <div class="text-sm font-bold text-slate-700">
            Tổng số phòng <span class="ml-2 font-black text-base">{{ rooms.length }}</span>
          </div>
          <div class="flex items-center gap-2">
            <button 
              @click="roomPage = Math.max(1, roomPage - 1)" 
              :disabled="roomPage === 1"
              class="w-8 h-8 flex items-center justify-center border border-slate-200 bg-white rounded text-slate-500 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer font-bold"
            >
              &lt;
            </button>
            
            <button 
              v-for="p in totalRoomPages" 
              :key="p"
              @click="roomPage = p"
              class="w-8 h-8 flex items-center justify-center border rounded font-semibold cursor-pointer text-sm"
              :class="roomPage === p ? 'border-sky-500 text-sky-600 font-bold bg-sky-50/20' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
            >
              {{ p }}
            </button>
            
            <button 
              @click="roomPage = Math.min(totalRoomPages, roomPage + 1)" 
              :disabled="roomPage === totalRoomPages"
              class="w-8 h-8 flex items-center justify-center border border-slate-200 bg-white rounded text-slate-500 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer font-bold"
            >
              &gt;
            </button>
            
            <select 
              v-model="roomPageSize" 
              @change="roomPage = 1"
              class="border border-slate-200 rounded p-1.5 bg-white text-slate-600 font-semibold text-xs cursor-pointer focus:outline-sky-400"
            >
              <option :value="10">10 / page</option>
              <option :value="25">25 / page</option>
              <option :value="50">50 / page</option>
              <option :value="100">100 / page</option>
            </select>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- OVERLAY MODAL: ADD / EDIT ROOM -->
  <div 
    v-if="isRoomModalOpen" 
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 backdrop-blur-xs select-none"
  >
    <div class="bg-white rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden border border-slate-200 animate-in fade-in zoom-in-95 duration-200">
      <!-- Modal Header -->
      <div class="bg-[#8dcbf4] px-6 py-4 flex items-center justify-between text-white">
        <h2 class="text-base font-black uppercase tracking-wider">{{ isEditMode ? 'Chỉnh sửa phòng' : 'Thêm phòng' }}</h2>
        <button 
          @click="isRoomModalOpen = false" 
          class="text-white/80 hover:text-white bg-transparent border-none cursor-pointer text-lg font-black"
        >
          ✕
        </button>
      </div>

      <!-- Modal Body Form -->
      <div class="p-6 flex flex-col gap-6 overflow-y-auto max-h-[80vh] text-sm font-bold text-slate-600">
        <!-- Section 1: Room Info -->
        <div class="flex flex-col gap-3">
          <h3 class="text-xs font-black text-sky-600 border-b border-sky-100 pb-1 uppercase tracking-wide">Thông tin phòng*</h3>
          <div class="grid grid-cols-2 gap-4">
            <div class="flex flex-col gap-1.5">
              <span>PHÒNG</span>
              <input 
                type="text" 
                v-model="roomFormState.room_number" 
                placeholder="Nhập số phòng..." 
                class="border border-slate-200 rounded-lg p-2.5 font-bold focus:outline-sky-500 focus:bg-white text-sm" 
              />
            </div>
            <div class="flex flex-col gap-1.5">
              <span>DẠNG PHÒNG</span>
              <select v-model="roomFormState.room_form_id" class="border border-slate-200 rounded-lg p-2.5 bg-white font-semibold focus:outline-sky-500 text-sm">
                <option v-for="f in roomForms" :key="f.id" :value="f.id">{{ f.name }}</option>
              </select>
            </div>
          </div>
          <div class="flex flex-col gap-1.5">
            <span>TÊN LOẠI PHÒNG</span>
            <select v-model="roomFormState.room_class_id" class="border border-slate-200 rounded-lg p-2.5 bg-white font-semibold focus:outline-sky-500 text-sm">
              <option v-for="c in roomClasses.filter(item => item.is_active)" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>
        </div>

        <!-- Section 2: Floor/Grid Info -->
        <div class="flex flex-col gap-3">
          <h3 class="text-xs font-black text-sky-600 border-b border-sky-100 pb-1 uppercase tracking-wide">Thông tin tầng*</h3>
          <div class="grid grid-cols-2 gap-4">
            <div class="flex flex-col gap-1.5">
              <span>Khách hàng</span>
              <input type="number" v-model="roomFormState.max_guests" class="border border-slate-200 rounded-lg p-2.5 font-bold focus:outline-sky-500 text-sm" />
            </div>
            <div class="flex flex-col gap-1.5">
              <span>Tầng</span>
              <input type="text" v-model="roomFormState.floor" placeholder="Nhập tầng..." class="border border-slate-200 rounded-lg p-2.5 font-bold focus:outline-sky-500 text-sm" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div class="flex flex-col gap-1.5">
              <span>Khu vực</span>
              <select v-model="roomFormState.area" class="border border-slate-200 rounded-lg p-2.5 bg-white font-semibold focus:outline-sky-500 text-sm">
                <option value="Khu A">Khu vực A</option>
                <option value="Khu B">Khu vực B</option>
                <option value="Khu C">Khu vực C</option>
              </select>
            </div>
            <div class="flex flex-col gap-1.5">
              <span>Thêm giường</span>
              <input type="number" v-model="roomFormState.extra_beds_limit" class="border border-slate-200 rounded-lg p-2.5 font-bold focus:outline-sky-500 text-sm" />
            </div>
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div class="flex flex-col gap-1.5">
              <span>Hàng</span>
              <input type="number" v-model="roomFormState.grid_row" class="border border-slate-200 rounded-lg p-2.5 font-mono text-red-500 focus:outline-sky-500 text-sm" />
            </div>
            <div class="flex flex-col gap-1.5">
              <span>Cột</span>
              <input type="number" v-model="roomFormState.grid_column" class="border border-slate-200 rounded-lg p-2.5 font-mono text-red-500 focus:outline-sky-500 text-sm" />
            </div>
          </div>
        </div>

        <!-- Section 3: Others -->
        <div class="flex flex-col gap-3">
          <h3 class="text-xs font-black text-sky-600 border-b border-sky-100 pb-1 uppercase tracking-wide">Khác*</h3>
          <div class="flex flex-col gap-1.5">
            <span>Phòng chủ sở hữu</span>
            <select v-model="roomFormState.owner_room" class="border border-slate-200 rounded-lg p-2.5 bg-white font-semibold focus:outline-sky-500 text-sm">
              <option value="">Không có</option>
              <option value="Chủ sở hữu A">Chủ sở hữu A</option>
              <option value="Chủ sở hữu B">Chủ sở hữu B</option>
            </select>
          </div>
          <div class="grid grid-cols-2 gap-4 items-center">
            <div class="flex flex-col gap-1.5">
              <span>Liên kết</span>
              <input type="text" v-model="roomFormState.linked_room" class="border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 text-sm" />
            </div>
            <div class="flex flex-col gap-2 pt-5 select-none">
              <div class="flex items-center justify-between">
                <span>Phòng nội bộ</span>
                <label class="relative inline-flex items-center cursor-pointer">
                  <input type="checkbox" v-model="roomFormState.is_internal" class="sr-only peer">
                  <div class="w-8 h-4.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-blue-500"></div>
                </label>
              </div>
            </div>
          </div>
          <div class="flex flex-col gap-1.5">
            <span>Ghi chú</span>
            <textarea v-model="roomFormState.notes" rows="2" class="border border-slate-200 rounded-lg p-2.5 focus:outline-sky-500 resize-none font-semibold text-sm"></textarea>
          </div>
        </div>
      </div>

      <!-- Modal Footer -->
      <div class="bg-slate-50 px-6 py-4 flex items-center justify-end gap-2 border-t border-slate-100">
        <button 
          @click="isRoomModalOpen = false" 
          class="px-4 py-2 border border-slate-200 bg-white hover:bg-slate-100 text-slate-600 rounded-lg font-bold text-sm cursor-pointer transition-colors"
        >
          Đóng
        </button>
        <button 
          @click="saveRoom"
          class="px-4 py-2 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-lg font-bold text-sm border-none cursor-pointer shadow-xs transition-colors"
        >
          Lưu phòng
        </button>
      </div>
    </div>
  </div>

  <!-- OVERLAY MODAL: ADD ROOM CLASS -->
  <div 
    v-if="isRoomClassModalOpen" 
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 backdrop-blur-xs select-none"
  >
    <div class="bg-white rounded-2xl w-full max-w-md shadow-2xl overflow-hidden border border-slate-200 animate-in fade-in zoom-in-95 duration-200">
      <!-- Modal Header -->
      <div class="bg-[#8dcbf4] px-6 py-4 flex items-center justify-between text-white">
        <h2 class="text-base font-black uppercase tracking-wider text-white">{{ isEditRoomClassMode ? 'Chỉnh sửa loại phòng' : 'Thêm loại phòng' }}</h2>
        <button 
          @click="isRoomClassModalOpen = false" 
          class="text-white hover:text-white/80 bg-transparent border-none cursor-pointer text-lg font-black"
        >
          ✕
        </button>
      </div>

      <!-- Modal Body Form -->
      <div class="p-6 flex flex-col gap-4 overflow-y-auto max-h-[75vh] text-sm text-slate-700">
        <div class="flex flex-col gap-1.5">
          <span class="font-bold text-slate-600 uppercase text-xs">TÊN LOẠI PHÒNG</span>
          <input 
            type="text" 
            v-model="roomClassFormState.name" 
            placeholder="Nhập tên loại phòng..." 
            class="border border-slate-200 rounded-lg p-2.5 font-semibold focus:outline-sky-400 focus:bg-white text-sm" 
          />
        </div>

        <div class="flex flex-col gap-1.5">
          <span class="font-bold text-slate-600 uppercase text-xs">Tên viết tắt</span>
          <input 
            type="text" 
            v-model="roomClassFormState.code" 
            placeholder="Nhập tên viết tắt..." 
            class="border border-slate-200 rounded-lg p-2.5 font-semibold focus:outline-sky-400 focus:bg-white text-sm" 
          />
        </div>

        <div class="flex items-center gap-4 py-1.5">
          <span class="font-bold text-slate-600 uppercase text-xs">Có sử dụng</span>
          <label class="relative inline-flex items-center cursor-pointer">
            <input type="checkbox" v-model="roomClassFormState.is_active" class="sr-only peer" />
            <div class="w-10 h-5.5 bg-slate-200 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4.5 after:w-4.5 after:transition-all peer-checked:bg-sky-400"></div>
          </label>
        </div>

        <div class="flex flex-col gap-1.5">
          <span class="font-bold text-slate-600 uppercase text-xs">Nhóm loại phòng</span>
          <select v-model="roomClassFormState.group" class="border border-slate-200 rounded-lg p-2.5 bg-white font-semibold focus:outline-sky-400 text-sm">
            <option value="" disabled>Select Value</option>
            <option value="hotel">HOTEL</option>
          </select>
        </div>

        <div class="flex flex-col gap-1.5">
          <span class="font-bold text-slate-600 uppercase text-xs">Màu sắc</span>
          <div class="flex gap-2 items-center">
            <input 
              type="text" 
              v-model="roomClassFormState.color" 
              placeholder="#ffffff" 
              class="flex-1 border border-slate-200 rounded-lg p-2.5 font-mono focus:outline-sky-400 focus:bg-white text-sm" 
            />
            <input 
              type="color" 
              v-model="roomClassFormState.color" 
              class="w-10 h-10 p-0 border-none cursor-pointer rounded bg-transparent"
            />
          </div>
        </div>

        <div class="flex flex-col gap-1.5">
          <span class="font-bold text-slate-600 uppercase text-xs">Ghi chú</span>
          <textarea 
            v-model="roomClassFormState.notes" 
            rows="2" 
            placeholder="Nhập ghi chú..." 
            class="border border-slate-200 rounded-lg p-2.5 focus:outline-sky-400 resize-none font-semibold text-sm"
          ></textarea>
        </div>

        <!-- Hidden input for file upload -->
        <input 
          type="file" 
          ref="roomClassImageInput" 
          @change="handleRoomClassImageUpload" 
          accept="image/*" 
          class="hidden" 
        />

        <!-- Upload area -->
        <div 
          @click="triggerRoomClassImageUpload"
          class="border-2 border-dashed border-slate-350 rounded-lg p-6 flex flex-col items-center justify-center cursor-pointer hover:bg-slate-50 transition-colors"
          style="min-height: 120px;"
        >
          <div v-if="!roomClassFormState.imagePreview" class="flex flex-col items-center justify-center text-slate-400 gap-1.5">
            <span class="text-xl font-bold">+ Upload</span>
          </div>
          <div v-else class="relative w-full h-full flex items-center justify-center">
            <img :src="roomClassFormState.imagePreview" class="max-h-28 rounded object-contain" />
            <button 
              type="button" 
              @click.stop="removeRoomClassImage" 
              class="absolute -top-2 -right-2 bg-red-500 text-white w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold shadow hover:bg-red-600 cursor-pointer border-none"
            >
              ✕
            </button>
          </div>
        </div>
      </div>

      <!-- Modal Footer -->
      <div class="bg-slate-50 px-6 py-4 flex items-center justify-end gap-3 border-t border-slate-100">
        <button 
          @click="isRoomClassModalOpen = false" 
          class="px-5 py-2 bg-[#8dcbf4]/90 hover:bg-[#8dcbf4] text-white rounded-lg font-bold text-sm border-none cursor-pointer flex items-center gap-1.5 transition-colors shadow-xs"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          Đóng
        </button>
        <button 
          @click="saveRoomClass"
          class="px-5 py-2 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-lg font-bold text-sm border-none cursor-pointer flex items-center gap-1.5 transition-colors shadow-xs"
        >
          <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
            <path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/>
          </svg>
          Lưu
        </button>
      </div>
    </div>
  </div>

  <!-- OVERLAY MODAL: ADD / EDIT ROOM FORM -->
  <div 
    v-if="isRoomFormModalOpen" 
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 backdrop-blur-xs select-none"
  >
    <div class="bg-white rounded-2xl w-full max-w-sm shadow-2xl overflow-hidden border border-slate-200 animate-in fade-in zoom-in-95 duration-200">
      <!-- Modal Header -->
      <div class="bg-[#8dcbf4] px-6 py-4 flex items-center justify-between text-white">
        <h2 class="text-base font-black uppercase tracking-wider text-white">
          {{ isEditRoomFormMode ? 'Chỉnh sửa dạng phòng' : 'Thêm dạng phòng' }}
        </h2>
        <button 
          @click="isRoomFormModalOpen = false" 
          class="text-white hover:text-white/80 bg-transparent border-none cursor-pointer text-lg font-black"
        >
          ✕
        </button>
      </div>

      <!-- Modal Body Form -->
      <div class="p-6 flex flex-col gap-6 text-sm text-slate-700">
        <div class="grid grid-cols-[100px_1fr] items-center gap-4">
          <span class="font-bold text-slate-600 text-xs uppercase">DẠNG PHÒNG</span>
          <input 
            type="text" 
            v-model="roomFormStateData.name" 
            placeholder="Nhập tên dạng phòng..." 
            class="border border-slate-200 rounded-lg p-2.5 font-semibold focus:outline-sky-400 focus:bg-white text-sm" 
          />
        </div>

        <div class="grid grid-cols-[100px_1fr] items-center gap-4">
          <span class="font-bold text-slate-600 text-xs uppercase">Người lớn</span>
          <input 
            type="number" 
            v-model="roomFormStateData.max_adults" 
            min="0"
            class="border border-slate-200 rounded-lg p-2.5 font-semibold focus:outline-sky-400 focus:bg-white text-sm" 
          />
        </div>
      </div>

      <!-- Modal Footer -->
      <div class="bg-slate-50 px-6 py-4 flex items-center justify-end gap-3 border-t border-slate-100">
        <button 
          @click="isRoomFormModalOpen = false" 
          class="px-5 py-2 bg-[#8dcbf4]/90 hover:bg-[#8dcbf4] text-white rounded-lg font-bold text-sm border-none cursor-pointer flex items-center gap-1.5 transition-colors shadow-xs"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          Đóng
        </button>
        <button 
          @click="saveRoomForm"
          class="px-5 py-2 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-lg font-bold text-sm border-none cursor-pointer flex items-center gap-1.5 transition-colors shadow-xs"
        >
          <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
            <path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/>
          </svg>
          Lưu
        </button>
      </div>
    </div>
  </div>

  <!-- OVERLAY MODAL: ADD / EDIT STANDARD RATE -->
  <div 
    v-if="isStandardRateModalOpen" 
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 backdrop-blur-xs select-none"
  >
    <div class="bg-white rounded-2xl w-full max-w-lg shadow-2xl overflow-hidden border border-slate-200 animate-in fade-in zoom-in-95 duration-200">
      <!-- Modal Header -->
      <div class="bg-[#8dcbf4] px-6 py-4 flex items-center justify-between text-white">
        <h2 class="text-base font-black uppercase tracking-wider text-white">
          {{ isEditStandardRateMode ? 'Cập nhật giá phòng chuẩn' : 'Thêm giá phòng chuẩn' }}
        </h2>
        <button 
          @click="isStandardRateModalOpen = false" 
          class="text-white hover:text-white/80 bg-transparent border-none cursor-pointer text-lg font-black"
        >
          ✕
        </button>
      </div>

      <!-- Modal Body Form -->
      <div class="p-6 flex flex-col gap-6 text-sm text-slate-700">
        <div class="grid grid-cols-2 gap-4">
          <div class="flex flex-col gap-1.5">
            <span class="font-bold text-slate-600 text-xs uppercase">Loại phòng</span>
            <select v-model="standardRateFormState.room_class_id" class="border border-slate-200 rounded-lg p-2.5 bg-white font-semibold focus:outline-sky-400 text-sm">
              <option value="" disabled>Select Value</option>
              <option v-for="c in roomClasses.filter(item => item.is_active || item.id === standardRateFormState.room_class_id)" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </div>

          <div class="flex flex-col gap-1.5">
            <span class="font-bold text-slate-600 text-xs uppercase">Dạng phòng</span>
            <select v-model="standardRateFormState.room_form_id" class="border border-slate-200 rounded-lg p-2.5 bg-white font-semibold focus:outline-sky-400 text-sm">
              <option value="" disabled>Select Value</option>
              <option v-for="f in roomForms" :key="f.id" :value="f.id">{{ f.name }}</option>
            </select>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div class="flex flex-col gap-1.5">
            <span class="font-bold text-slate-600 text-xs uppercase">Giá phòng</span>
            <input 
              type="number" 
              v-model="standardRateFormState.room_price" 
              min="0"
              placeholder="0"
              class="border border-slate-200 rounded-lg p-2.5 font-semibold focus:outline-sky-400 focus:bg-white text-sm" 
            />
          </div>

          <div class="flex flex-col gap-1.5">
            <span class="font-bold text-slate-600 text-xs uppercase">Giá thêm giường</span>
            <input 
              type="number" 
              v-model="standardRateFormState.extra_bed_price" 
              min="0"
              placeholder="0"
              class="border border-slate-200 rounded-lg p-2.5 font-semibold focus:outline-sky-400 focus:bg-white text-sm" 
            />
          </div>
        </div>
      </div>

      <!-- Modal Footer -->
      <div class="bg-slate-50 px-6 py-4 flex items-center justify-end gap-3 border-t border-slate-100">
        <button 
          @click="isStandardRateModalOpen = false" 
          class="px-5 py-2 bg-[#8dcbf4]/90 hover:bg-[#8dcbf4] text-white rounded-lg font-bold text-sm border-none cursor-pointer flex items-center gap-1.5 transition-colors shadow-xs"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          Đóng
        </button>
        <button 
          @click="saveStandardRate"
          class="px-5 py-2 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-lg font-bold text-sm border-none cursor-pointer flex items-center gap-1.5 transition-colors shadow-xs"
        >
          <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
            <path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/>
          </svg>
          Lưu
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.animate-in {
  animation: fadeIn 0.2s ease-out forwards;
}
@keyframes fadeIn {
  from { opacity: 0; transform: scale(0.95); }
  to { opacity: 1; transform: scale(1); }
}
</style>
