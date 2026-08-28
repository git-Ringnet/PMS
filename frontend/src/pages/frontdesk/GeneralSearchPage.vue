<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { fetchGeneralSearch, fetchGeneralSearchOptions, fetchGeneralSearchSuggestions, checkInRoom } from '@/services/booking-service'
import { useAuthStore } from '@/stores/auth-store'
import { useUiStore } from '@/stores/ui-store'
import echo from '@/services/echo'
import http from '@/services/http'
import GuestInfoModal from '@/pages/reservation/components/GuestInfoModal.vue'
import CopyModal from '@/pages/reservation/components/CopyModal.vue'

const router = useRouter()
const uiStore = useUiStore()
const auth = useAuthStore()
const tab = ref('booking')
const loading = ref(false)
const errorMessage = ref('')
const advancedOpen = ref(false)
const showColumnDropdown = ref(false)
const colDropdownRef = ref(null)

const actionsDropdownRef = ref(null)
const showActionsMenu = ref(false)
const showNoshowSubmenu = ref(false)

const showGuestInfoModal = ref(false)
const selectedGuestInfoBookingId = ref(null)

const showCopyModal = ref(false)
const copyModalBookingId = ref(null)
const copyModalArrivalDate = ref('')
const copyModalDepartureDate = ref('')

const expandedBookings = ref(new Set())
const expandedRoomGroups = ref(new Set())
const rows = ref([])
const meta = ref({ total: 0, current_page: 1, last_page: 1, per_page: 200 })
const perPageOptions = [50, 100, 200]
const perPage = ref(200)

const options = ref({
  room_classes: [],
  rate_codes: [],
  users: [],
  companies: [],
  markets: [],
  registration_statuses: [],
  customer_sources: [],
  bookers: []
})

// 12 fields chính thức theo đúng thiết kế mẫu + 4 fields mở rộng
const FIELD_DEFINITIONS = {
  mabk: { id: 'mabk', field: 'booking_code', label: 'Mã BK', type: 'input', placeholder: 'Mã BK' },
  tinhtranglt: { id: 'tinhtranglt', field: 'status', label: 'Tình trạng lưu trú', type: 'select', optionsKey: 'statuses' },
  refcode: { id: 'refcode', field: 'reference_code', label: 'Ref Code', type: 'input', placeholder: 'Nhập Ref Code' },
  bookingname: { id: 'bookingname', field: 'booking_name', label: 'Booking Name', type: 'input', placeholder: 'Nhập tên đăng ký' },
  bookingstatus: { id: 'bookingstatus', field: 'registration_status_id', label: 'Booking Status', type: 'select', optionsKey: 'registration_statuses' },
  contact: { id: 'contact', field: 'contact', label: 'Contact', type: 'input', placeholder: 'Nhập contact / SĐT' },
  booker: { id: 'booker', field: 'booker_id', label: 'Booker', type: 'select', optionsKey: 'bookers' },
  company: { id: 'company', field: 'company_id', label: 'Company', type: 'select', optionsKey: 'companies' },
  marketsegment: { id: 'marketsegment', field: 'market_id', label: 'Market Segment', type: 'select', optionsKey: 'markets' },
  sourcecode: { id: 'sourcecode', field: 'customer_source_id', label: 'Source Code', type: 'select', optionsKey: 'customer_sources' },
  regdate: { id: 'regdate', field: 'booking_date', label: 'Reg Date', type: 'date', placeholder: 'dd/mm/yyyy' },
  usersale: { id: 'usersale', field: 'sales_person', label: 'User Sale', type: 'input', placeholder: 'Chọn / Nhập User Sale' },
  createdby: { id: 'createdby', field: 'created_by_user_id', label: 'Người tạo BK', type: 'select', optionsKey: 'users' },
  ratecode: { id: 'ratecode', field: 'rate_code_id', label: 'Mã giá phòng', type: 'select', optionsKey: 'rate_codes' },
  actualroomclass: { id: 'actualroomclass', field: 'room_class_id', label: 'Loại phòng thực tế', type: 'select', optionsKey: 'room_classes' },
  originalroomclass: { id: 'originalroomclass', field: 'original_room_class_id', label: 'Loại phòng khởi tạo', type: 'select', optionsKey: 'room_classes' }
}

const FIELD_ORDER = [
  'mabk', 'tinhtranglt', 'refcode', 'bookingname', 'bookingstatus', 'contact',
  'booker', 'company', 'marketsegment', 'sourcecode', 'regdate', 'usersale',
  'createdby', 'ratecode', 'actualroomclass', 'originalroomclass'
]

const DEFAULT_QUICK_IDS = ['mabk', 'tinhtranglt']
const DEFAULT_ADV_IDS = [
  'refcode', 'bookingname', 'bookingstatus', 'contact', 'booker', 'company',
  'marketsegment', 'sourcecode', 'regdate', 'usersale', 'createdby', 'ratecode',
  'actualroomclass', 'originalroomclass'
]

const quickIds = ref([...DEFAULT_QUICK_IDS])
const advIds = ref([...DEFAULT_ADV_IDS])

function sortByOrder(ids) {
  return ids.slice().sort((a, b) => FIELD_ORDER.indexOf(a) - FIELD_ORDER.indexOf(b))
}

const sortedQuickIds = computed(() => sortByOrder(quickIds.value))
const sortedAdvIds = computed(() => sortByOrder(advIds.value))

const layoutStorageKey = computed(() => `pms_general_search_layout_${auth.user?.id || 'default'}`)

function restoreLayout() {
  try {
    const saved = JSON.parse(localStorage.getItem(layoutStorageKey.value))
    if (saved && Array.isArray(saved.quick) && Array.isArray(saved.adv)) {
      quickIds.value = saved.quick.filter(id => FIELD_DEFINITIONS[id])
      advIds.value = saved.adv.filter(id => FIELD_DEFINITIONS[id])
      FIELD_ORDER.forEach(id => {
        if (!quickIds.value.includes(id) && !advIds.value.includes(id)) {
          advIds.value.push(id)
        }
      })
      return
    }
  } catch (_) {}
  quickIds.value = [...DEFAULT_QUICK_IDS]
  advIds.value = [...DEFAULT_ADV_IDS]
}

function persistLayout() {
  try {
    localStorage.setItem(layoutStorageKey.value, JSON.stringify({
      quick: quickIds.value,
      adv: advIds.value
    }))
  } catch (_) {}
}

function moveField(id, toQuick) {
  const def = FIELD_DEFINITIONS[id]
  if (def?.field) {
    if (toQuick) {
      if (advDraft.value[def.field] !== undefined) {
        filters.value[def.field] = advDraft.value[def.field]
      }
    } else {
      advDraft.value[def.field] = filters.value[def.field] || ''
    }
  }
  quickIds.value = quickIds.value.filter(x => x !== id)
  advIds.value = advIds.value.filter(x => x !== id)
  if (toQuick) {
    quickIds.value.push(id)
  } else {
    advIds.value.push(id)
  }
  persistLayout()
}

// Drag & Drop Handling for Filters
const draggingId = ref(null)
const isQuickDragOver = ref(false)
const isAdvDragOver = ref(false)

function handleDragStart(id, event) {
  draggingId.value = id
  if (event?.dataTransfer) {
    event.dataTransfer.effectAllowed = 'move'
    event.dataTransfer.setData('text/plain', id)
  }
}

function handleDragOver(zone, event) {
  event.preventDefault()
  if (zone === 'quick') isQuickDragOver.value = true
  if (zone === 'adv') isAdvDragOver.value = true
}

function handleDragLeave(zone) {
  if (zone === 'quick') isQuickDragOver.value = false
  if (zone === 'adv') isAdvDragOver.value = false
}

function handleDrop(zone, event) {
  event.preventDefault()
  isQuickDragOver.value = false
  isAdvDragOver.value = false
  if (!draggingId.value) return
  moveField(draggingId.value, zone === 'quick')
  draggingId.value = null
}

// Filter values
const emptyFilters = () => ({
  use_date: false,
  from_date: '',
  to_date: '',
  search: '',
  status: '',
  booking_code: '',
  reference_code: '',
  booking_name: '',
  registration_status_id: '',
  contact: '',
  booker_id: '',
  company_id: '',
  market_id: '',
  customer_source_id: '',
  booking_date: '',
  sales_person: '',
  created_by_user_id: '',
  rate_code_id: '',
  room_class_id: '',
  original_room_class_id: ''
})

const filters = ref(emptyFilters())
const advDraft = ref(emptyFilters())

function applyAdvancedFilters() {
  advIds.value.forEach(id => {
    const def = FIELD_DEFINITIONS[id]
    if (def?.field) {
      filters.value[def.field] = advDraft.value[def.field] !== undefined ? advDraft.value[def.field] : ''
    }
  })
  search(1)
}

function clearAdvancedFilters() {
  advIds.value.forEach(id => {
    const def = FIELD_DEFINITIONS[id]
    if (def?.field) {
      advDraft.value[def.field] = ''
      filters.value[def.field] = ''
    }
  })
  search(1)
}

function handleMainSearch() {
  advIds.value.forEach(id => {
    const def = FIELD_DEFINITIONS[id]
    if (def?.field && advDraft.value[def.field] !== undefined) {
      filters.value[def.field] = advDraft.value[def.field]
    }
  })
  search(1)
}

const defaults = {
  booking: { by: 'booking_code', dir: 'desc' },
  room: { by: 'arrival_date', dir: 'desc' },
  guest: { by: 'guest_name', dir: 'asc' }
}
const sorts = ref({ ...defaults })

let autoSearchTimer = null
let suggestionTimer = null
let suggestionRequestId = 0
let suppressNextSuggestion = false
const bookingSuggestions = ref([])
const showSuggestions = ref(false)
const fromDateInput = ref(null)
const toDateInput = ref(null)
const selectedIds = ref(new Set())
const selectedCount = computed(() => selectedIds.value.size)

const sortStorageKey = computed(() => `pms_general_search_sort_${auth.user?.id || 'anonymous'}`)

const statusOptions = [
  { value: '', label: 'Tất cả tình trạng' },
  { value: '0', label: 'Đặt phòng' },
  { value: '1', label: 'Đang ở' },
  { value: '2', label: 'Đã trả phòng' },
  { value: '3', label: 'Đã hủy' },
  { value: '4', label: 'No-show' }
]

const tabItems = [
  { id: 'booking', label: 'Đăng Ký' },
  { id: 'room', label: 'Phòng' },
  { id: 'guest', label: 'Khách' }
]

const currentSort = computed(() => sorts.value[tab.value])
const date = value => value ? new Intl.DateTimeFormat('vi-VN').format(new Date(`${value}T00:00:00`)) : '—'
const money = value => new Intl.NumberFormat('vi-VN').format(Number(value || 0))
const statusText = value => statusOptions.find(item => item.value === String(value))?.label || '—'
const sortIcon = column => currentSort.value.by !== column ? '↕' : currentSort.value.dir === 'asc' ? '↑' : '↓'

function restoreSorts() {
  try {
    const saved = JSON.parse(localStorage.getItem(sortStorageKey.value))
    if (saved && ['booking', 'room', 'guest'].every(key => saved[key]?.by && saved[key]?.dir)) {
      sorts.value = saved
    }
  } catch (_) {}
}

function persistSorts() {
  localStorage.setItem(sortStorageKey.value, JSON.stringify(sorts.value))
}

function setSort(by) {
  const old = currentSort.value
  sorts.value = {
    ...sorts.value,
    [tab.value]: { by, dir: old.by === by && old.dir === 'desc' ? 'asc' : 'desc' }
  }
  persistSorts()
  search()
}

function toggleBooking(id) {
  const next = new Set(expandedBookings.value)
  next.has(id) ? next.delete(id) : next.add(id)
  expandedBookings.value = next
}

function toggleRoomGroup(bookingId) {
  const next = new Set(expandedRoomGroups.value)
  next.has(bookingId) ? next.delete(bookingId) : next.add(bookingId)
  expandedRoomGroups.value = next
}

function rowKey(row) {
  return tab.value === 'room' ? `room-${row.id}` : String(row.id)
}

function isSelected(row) {
  return selectedIds.value.has(rowKey(row))
}

function toggleSelected(row) {
  const next = new Set(selectedIds.value)
  const key = rowKey(row)
  next.has(key) ? next.delete(key) : next.add(key)
  selectedIds.value = next
}

function toggleAllSelected() {
  const selectableRows = tab.value === 'room' ? rows.value.flatMap(group => group.rooms || []) : rows.value
  const keys = selectableRows.map(rowKey)
  const allSelected = keys.length > 0 && keys.every(key => selectedIds.value.has(key))
  selectedIds.value = allSelected ? new Set() : new Set(keys)
}

const allCurrentSelected = computed(() => {
  const selectableRows = tab.value === 'room' ? rows.value.flatMap(group => group.rooms || []) : rows.value
  return selectableRows.length > 0 && selectableRows.every(isSelected)
})

function searchParams(page = 1) {
  return {
    tab: tab.value,
    ...filters.value,
    sort_by: currentSort.value.by,
    sort_dir: currentSort.value.dir,
    page,
    per_page: perPage.value
  }
}

async function search(page = 1) {
  page = Number.isInteger(page) && page > 0 ? page : 1
  loading.value = true
  errorMessage.value = ''
  try {
    const { data } = await fetchGeneralSearch(searchParams(page))
    rows.value = data.data?.data || []
    meta.value = data.data?.meta || data.data || meta.value
    if (tab.value === 'room') {
      expandedRoomGroups.value = new Set(rows.value.map(group => group.booking_id))
    }
  } catch (error) {
    rows.value = []
    meta.value = { total: 0, current_page: 1, last_page: 1, per_page: perPage.value }
    errorMessage.value = error.response?.data?.message || 'Không tải được dữ liệu tìm kiếm. Hãy thử lại.'
  } finally {
    loading.value = false
  }
}

function clearFilters() {
  filters.value = emptyFilters()
  initDefaultDates()
  search(1)
}

function clearBookingCode() {
  suggestionRequestId++
  filters.value.booking_code = ''
  bookingSuggestions.value = []
  showSuggestions.value = false
}

function openDatePicker(input) {
  if (!filters.value.use_date || !input) return
  if (typeof input.showPicker === 'function') input.showPicker()
  else input.focus()
}

function selectBookingSuggestion(item) {
  suppressNextSuggestion = true
  suggestionRequestId++
  filters.value.booking_code = item.booking_code
  showSuggestions.value = false
  search(1)
}

async function loadBookingSuggestions() {
  const keyword = (filters.value.booking_code || '').trim()
  if (!keyword) {
    bookingSuggestions.value = []
    showSuggestions.value = false
    return
  }
  const requestId = ++suggestionRequestId
  try {
    const { data } = await fetchGeneralSearchSuggestions(keyword)
    if (requestId !== suggestionRequestId || keyword !== (filters.value.booking_code || '').trim()) return
    bookingSuggestions.value = data.data || []
    showSuggestions.value = bookingSuggestions.value.length > 0
  } catch (_) {
    if (requestId === suggestionRequestId) {
      bookingSuggestions.value = []
      showSuggestions.value = false
    }
  }
}

async function initDefaultDates() {
  try {
    const res = await http.get('/system-date')
    if (res.data?.success && res.data.data?.system_date) {
      const sysDate = res.data.data.system_date
      if (!filters.value.from_date) filters.value.from_date = sysDate
      if (!filters.value.to_date) filters.value.to_date = sysDate
    }
  } catch (_) {}
}

// Helper lấy options theo optionsKey
function getSelectOptions(key) {
  if (key === 'statuses') return statusOptions
  if (key === 'registration_statuses') return options.value.registration_statuses || []
  if (key === 'companies') return options.value.companies || []
  if (key === 'markets') return options.value.markets || []
  if (key === 'customer_sources') return options.value.customer_sources || []
  if (key === 'bookers') return options.value.bookers || []
  if (key === 'users') return options.value.users || []
  if (key === 'rate_codes') return options.value.rate_codes || []
  if (key === 'room_classes') return options.value.room_classes || []
  return []
}

// -------------------------------------------------------------
// SUB-TABLE GROUPING LOGIC (sp_034 logic as shown in Image 3)
// -------------------------------------------------------------
function getBookingRoomGroups(rooms) {
  if (!rooms || !rooms.length) return []
  const map = new Map()
  for (const r of rooms) {
    const rType = r.room_class?.code || r.room_class?.name || '—'
    const arr = r.arrival_date || ''
    const dep = r.departure_date || ''
    const rateCode = r.rate_code || '—'
    const rateVal = Number(r.rate || 0)
    const key = `${rType}|${arr}|${dep}|${rateCode}|${rateVal}`

    if (!map.has(key)) {
      map.set(key, {
        room_type: rType,
        room_count: 0,
        adults: 0,
        children: 0,
        arrival_date: arr,
        departure_date: dep,
        rate_code: rateCode,
        rate: rateVal,
        service_total: 0
      })
    }
    const g = map.get(key)
    g.room_count += 1
    g.adults += Number(r.adults || 0)
    g.children += Number(r.children || 0)
    g.service_total += Number(r.service_total || 0)
  }
  return Array.from(map.values())
}

function getSubtableTotals(groups) {
  return groups.reduce((acc, g) => {
    acc.rooms += g.room_count
    acc.adults += g.adults
    acc.children += g.children
    acc.total += g.service_total
    return acc
  }, { rooms: 0, adults: 0, children: 0, total: 0 })
}

// -------------------------------------------------------------
// COLUMN MANAGER & DRAGGABLE REORDERING (Image 1)
// -------------------------------------------------------------
const DEFAULT_TABLE_COLUMNS = {
  booking: [
    { key: 'booking_code', label: 'Mã BK', visible: true, sortable: true },
    { key: 'reference_code', label: 'Mã tham chiếu', visible: true },
    { key: 'booking_name', label: 'Tên đăng ký', visible: true },
    { key: 'company', label: 'Công ty', visible: true },
    { key: 'market', label: 'Thị trường', visible: true },
    { key: 'arrival_date', label: 'Ngày đến', visible: true, sortable: true },
    { key: 'departure_date', label: 'Ngày đi', visible: true, sortable: true },
    { key: 'nights', label: 'Đêm', visible: true, sortable: true },
    { key: 'original_room_types', label: 'LP khởi tạo', visible: true },
    { key: 'actual_room_types', label: 'LP thực tế', visible: true },
    { key: 'total_amount', label: 'Tổng', visible: true, isNumber: true },
    { key: 'deposit_amount', label: 'Đặt cọc', visible: true, isNumber: true },
    { key: 'registration_status', label: 'Khởi tạo', visible: true },
    { key: 'operation_status', label: 'Thực tế', visible: true },
    { key: 'contact_phone', label: 'Liên hệ', visible: true },
    { key: 'note', label: 'Ghi chú', visible: true },
    { key: 'booking_date', label: 'Ngày đăng ký', visible: true, sortable: true },
    { key: 'sales_person', label: 'Người bán', visible: true },
    { key: 'created_by', label: 'Người tạo', visible: true }
  ],
  room: [
    { key: 'room_number', label: 'Phòng', visible: true, sortable: true },
    { key: 'room_status', label: 'Tình trạng phòng', visible: true },
    { key: 'guest_name', label: 'Tên khách', visible: true },
    { key: 'arrival_date', label: 'Ngày đến', visible: true, sortable: true },
    { key: 'departure_date', label: 'Ngày đi', visible: true, sortable: true },
    { key: 'nights', label: 'Số đêm', visible: true, sortable: true },
    { key: 'rate', label: 'Giá phòng', visible: true, sortable: true, isNumber: true },
    { key: 'rate_code', label: 'Mã giá phòng', visible: true },
    { key: 'actual_room_class', label: 'LP thực tế', visible: true },
    { key: 'original_room_class', label: 'LP khởi tạo', visible: true },
    { key: 'extra_bed_qty', label: 'Thêm giường', visible: true },
    { key: 'extra_bed_rate', label: 'Giá TG', visible: true, isNumber: true },
    { key: 'adults', label: 'Người lớn', visible: true },
    { key: 'children', label: 'Trẻ em', visible: true },
    { key: 'note', label: 'Ghi chú', visible: true },
    { key: 'service_total', label: 'Tổng dịch vụ', visible: true, isNumber: true },
    { key: 'paid_total', label: 'Thanh toán', visible: true, isNumber: true },
    { key: 'checkin_time', label: 'Giờ đến', visible: true },
    { key: 'checkout_time', label: 'Giờ đi', visible: true },
    { key: 'booking_date', label: 'Ngày đăng ký', visible: true }
  ],
  guest: [
    { key: 'guest_name', label: 'Tên khách', visible: true, sortable: true },
    { key: 'booking_code', label: 'Đăng ký', visible: true },
    { key: 'room_number', label: 'Phòng', visible: true },
    { key: 'arrival_date', label: 'Ngày đến', visible: true, sortable: true },
    { key: 'departure_date', label: 'Ngày đi', visible: true, sortable: true },
    { key: 'nights', label: 'Số đêm', visible: true, sortable: true },
    { key: 'rate', label: 'Giá phòng', visible: true, isNumber: true },
    { key: 'rate_code', label: 'Mã giá phòng', visible: true },
    { key: 'company', label: 'Công ty DL', visible: true },
    { key: 'id_type', label: 'Loại giấy tờ', visible: true },
    { key: 'id_number', label: 'Số giấy tờ', visible: true },
    { key: 'email', label: 'Email', visible: true },
    { key: 'phone', label: 'SĐT', visible: true },
    { key: 'dob', label: 'Ngày sinh', visible: true },
    { key: 'nationality', label: 'Quốc tịch', visible: true },
    { key: 'province', label: 'Tỉnh thành', visible: true },
    { key: 'address', label: 'Địa chỉ', visible: true },
    { key: 'visa_no', label: 'Visa', visible: true },
    { key: 'visa_expiry_date', label: 'Ngày hết hạn', visible: true },
    { key: 'entry_date', label: 'Ngày nhập cảnh', visible: true },
    { key: 'border_gate', label: 'Cửa khẩu', visible: true }
  ]
}

const activeColumns = ref({
  booking: JSON.parse(JSON.stringify(DEFAULT_TABLE_COLUMNS.booking)),
  room: JSON.parse(JSON.stringify(DEFAULT_TABLE_COLUMNS.room)),
  guest: JSON.parse(JSON.stringify(DEFAULT_TABLE_COLUMNS.guest))
})

const visibleCols = computed(() => {
  return (activeColumns.value[tab.value] || []).filter(c => c.visible)
})

const columnsStorageKey = computed(() => `pms_general_search_columns_${auth.user?.id || 'default'}_${tab.value}`)

function restoreColumns() {
  try {
    ['booking', 'room', 'guest'].forEach(t => {
      const saved = JSON.parse(localStorage.getItem(`pms_general_search_columns_${auth.user?.id || 'default'}_${t}`))
      if (Array.isArray(saved) && saved.length > 0) {
        const defaultList = DEFAULT_TABLE_COLUMNS[t]
        const merged = []
        saved.forEach(item => {
          const found = defaultList.find(d => d.key === item.key)
          if (found) merged.push({ ...found, visible: item.visible !== false })
        })
        defaultList.forEach(item => {
          if (!merged.some(m => m.key === item.key)) merged.push({ ...item })
        })
        activeColumns.value[t] = merged
      }
    })
  } catch (_) {}
}

function persistColumns() {
  try {
    localStorage.setItem(columnsStorageKey.value, JSON.stringify(activeColumns.value[tab.value]))
  } catch (_) {}
}

function selectAllCols(val) {
  activeColumns.value[tab.value].forEach(c => { c.visible = val })
  persistColumns()
}

function resetColsDefault() {
  activeColumns.value[tab.value] = JSON.parse(JSON.stringify(DEFAULT_TABLE_COLUMNS[tab.value]))
  persistColumns()
}

// Column Drag & Drop in Dropdown List
const draggingColIndex = ref(null)
const dragOverColIndex = ref(null)

function onColDragStart(idx, e) {
  draggingColIndex.value = idx
  if (e?.dataTransfer) {
    e.dataTransfer.effectAllowed = 'move'
    e.dataTransfer.setData('text/plain', String(idx))
  }
}

function onColDragOver(idx, e) {
  e.preventDefault()
  dragOverColIndex.value = idx
}

function onColDragLeave() {
  dragOverColIndex.value = null
}

function onColDrop(idx, e) {
  e.preventDefault()
  if (draggingColIndex.value === null || draggingColIndex.value === idx) {
    draggingColIndex.value = null
    dragOverColIndex.value = null
    return
  }
  const list = [...activeColumns.value[tab.value]]
  const [removed] = list.splice(draggingColIndex.value, 1)
  list.splice(idx, 0, removed)
  activeColumns.value[tab.value] = list
  draggingColIndex.value = null
  dragOverColIndex.value = null
  persistColumns()
}

function moveCol(idx, direction) {
  const targetIdx = idx + direction
  const list = [...activeColumns.value[tab.value]]
  if (targetIdx < 0 || targetIdx >= list.length) return
  const temp = list[idx]
  list[idx] = list[targetIdx]
  list[targetIdx] = temp
  activeColumns.value[tab.value] = list
  persistColumns()
}

function getSelectedRows() {
  if (tab.value === 'room') {
    const allRooms = rows.value.flatMap(group => group.rooms || [])
    return allRooms.filter(r => selectedIds.value.has(`room-${r.id}`))
  }
  return rows.value.filter(r => selectedIds.value.has(String(r.id)))
}

function handleActionRegister() {
  if (selectedCount.value !== 1) return
  showActionsMenu.value = false
  const selected = getSelectedRows()
  if (selected.length !== 1) {
    uiStore.showToast('Chỉ có thể mở đăng ký khi chọn đúng 1 dòng.', 'warning')
    return
  }
  const first = selected[0]
  const bookingCode = first.booking_code || (tab.value === 'booking' ? first.id : first.booking_id)
  if (!bookingCode) {
    uiStore.showToast('Không tìm thấy thông tin mã đăng ký.', 'warning')
    return
  }
  router.push({ path: '/reservation', query: { tab: 'create-res', edit_id: bookingCode } })
}

function handleActionInvoice() {
  if (selectedCount.value !== 1) return
  showActionsMenu.value = false
  const selected = getSelectedRows()
  if (selected.length !== 1) {
    uiStore.showToast('Chỉ có thể xem hóa đơn khi chọn đúng 1 dòng.', 'warning')
    return
  }
  const first = selected[0]
  const bookingCode = first.booking_code || (tab.value === 'booking' ? first.id : first.booking_id)
  const bookingId = tab.value === 'booking' ? first.id : first.booking_id
  const roomId = tab.value === 'room' ? first.id : (first.booking_room_id || first.room_id || undefined)
  router.push({
    path: '/frontdesk',
    query: {
      tab: 'checkout',
      bookingCode: bookingCode || undefined,
      booking_code: bookingCode || undefined,
      booking_id: bookingId || undefined,
      roomId: roomId || undefined,
      room_id: roomId || undefined
    }
  })
}

function handleActionGuestInfo() {
  if (selectedCount.value !== 1) return
  showActionsMenu.value = false
  const selected = getSelectedRows()
  if (selected.length !== 1) {
    uiStore.showToast('Chỉ có thể xem thông tin khách khi chọn đúng 1 dòng.', 'warning')
    return
  }
  const first = selected[0]
  const bookingId = tab.value === 'booking' ? first.id : first.booking_id
  if (!bookingId) {
    uiStore.showToast('Không tìm thấy thông tin đăng ký.', 'warning')
    return
  }
  selectedGuestInfoBookingId.value = bookingId
  showGuestInfoModal.value = true
}

function handleCloneBooking() {
  if (selectedCount.value !== 1) return
  showActionsMenu.value = false
  const selected = getSelectedRows()
  if (selected.length !== 1) {
    uiStore.showToast('Vui lòng tích chọn đúng 1 đăng ký để nhân bản.', 'warning')
    return
  }
  const first = selected[0]
  copyModalBookingId.value = first.id
  copyModalArrivalDate.value = first.arrival_date ? String(first.arrival_date).split('T')[0] : ''
  copyModalDepartureDate.value = first.departure_date ? String(first.departure_date).split('T')[0] : ''
  showCopyModal.value = true
}

function handleBookingCopied() {
  showCopyModal.value = false
  uiStore.showToast('Nhân bản đăng ký thành công!', 'success')
  search(1)
}

function handleActionLostFound() {
  showActionsMenu.value = false
  router.push({ path: '/housekeeping', query: { tab: 'lost-found' } })
}

async function handleActionCheckIn() {
  showActionsMenu.value = false
  if (tab.value !== 'room') return
  const selected = getSelectedRows()
  if (!selected.length) {
    uiStore.showToast('Vui lòng tích chọn ít nhất 1 phòng để nhận phòng.', 'warning')
    return
  }

  const validRooms = selected.filter(r => {
    const s = Number(r.status ?? r.room_status)
    const code = String(r.room_status_code || '').toUpperCase()
    const name = String(r.room_status_name || '').toLowerCase()
    return s === 0 || code === 'DP' || name.includes('đặt phòng') || name.includes('chưa nhận') || name.includes('đăng ký') || s === '0'
  })

  if (!validRooms.length) {
    uiStore.showToast('Các phòng được chọn không ở tình trạng đặt phòng / chưa nhận để nhận phòng.', 'warning')
    return
  }

  const label = validRooms.length === 1
    ? `phòng ${validRooms[0].room_number || validRooms[0].id}`
    : `${validRooms.length} phòng đã chọn`

  const confirmed = await uiStore.confirm({
    title: 'Xác nhận nhận phòng',
    message: `Bạn có chắc chắn muốn thực hiện nhận phòng cho ${label} không?`,
    confirmText: 'Nhận phòng',
    cancelText: 'Hủy'
  })
  if (!confirmed) return

  loading.value = true
  let successCount = 0
  const errors = []
  for (const r of validRooms) {
    try {
      const res = await checkInRoom(r.booking_id, r.id)
      if (res.data && res.data.success !== false) {
        successCount++
      } else {
        errors.push(`Phòng ${r.room_number || r.id}: ${res.data?.message || 'Không thành công'}`)
      }
    } catch (err) {
      errors.push(`Phòng ${r.room_number || r.id}: ${err.response?.data?.message || 'Lỗi nhận phòng'}`)
    }
  }
  loading.value = false

  if (successCount > 0) {
    uiStore.showToast(`Nhận phòng thành công cho ${successCount} phòng!`, 'success')
  }
  if (errors.length > 0) {
    uiStore.showToast(errors.slice(0, 2).join(' | '), 'warning')
  }
  await search(meta.value.current_page || 1)
}

// -------------------------------------------------------------
// NO SHOW MODAL LOGIC (Hình 2: Xác nhận 3 tùy chọn tính phí)
// -------------------------------------------------------------
const noShowModal = ref({
  open: false,
  mode: 'late_checkin',
  selectedOption: 'all_charged'
})

function closeNoShowModal() {
  noShowModal.value.open = false
}

function handleActionNoShowOneDay() {
  showActionsMenu.value = false
  showNoshowSubmenu.value = false
  if (tab.value !== 'room') return
  const selected = getSelectedRows()
  if (!selected.length) {
    uiStore.showToast('Vui lòng tích chọn ít nhất 1 phòng để dời ngày đến (No Show 1 Ngày).', 'warning')
    return
  }
  noShowModal.value = {
    open: true,
    mode: 'late_checkin',
    selectedOption: 'all_charged'
  }
}

function handleActionNoShowPeriod() {
  showActionsMenu.value = false
  showNoshowSubmenu.value = false
  if (tab.value !== 'room') return
  const selected = getSelectedRows()
  if (!selected.length) {
    uiStore.showToast('Vui lòng tích chọn ít nhất 1 phòng để ghi nhận No Show.', 'warning')
    return
  }
  noShowModal.value = {
    open: true,
    mode: 'period',
    selectedOption: 'all_charged'
  }
}

async function confirmNoShowAction() {
  const mode = noShowModal.value.mode
  const chargeOption = noShowModal.value.selectedOption
  const selected = getSelectedRows()
  closeNoShowModal()

  if (!selected.length) return

  loading.value = true
  try {
    if (mode === 'late_checkin') {
      for (const r of selected) {
        await http.post('/night-audit/late-check-in', {
          booking_room_id: r.id,
          charge_option: chargeOption,
          reason: 'Late Check-in'
        })
      }
      uiStore.showToast(`Đã dời ngày đến thành công cho ${selected.length} phòng.`, 'success')
    } else {
      let warningMsg = null
      for (const r of selected) {
        const res = await http.post('/night-audit/no-show', {
          booking_room_id: r.id,
          charge_option: chargeOption,
          reason: 'Khách không đến (Noshow)'
        })
        if (res.data?.warning) {
          warningMsg = res.data.warning
        }
      }
      uiStore.showToast(`Đã ghi nhận No Show thành công cho ${selected.length} phòng.`, 'success')
      if (warningMsg) {
        setTimeout(() => uiStore.showToast(warningMsg, 'warning'), 600)
      }
    }
    await search(meta.value.current_page || 1)
  } catch (err) {
    console.error(err)
    uiStore.showToast(err.response?.data?.message || 'Có lỗi xảy ra khi thực hiện No Show.', 'error')
  } finally {
    loading.value = false
  }
}

function handleActionReport() {
  showActionsMenu.value = false
  uiStore.showToast('Chức năng Báo cáo sẽ được kích hoạt khi cấu hình mẫu báo cáo.', 'info')
}

function handleActionPrintConfirmation() {
  showActionsMenu.value = false
  uiStore.showToast('Chức năng In Xác Nhận sẽ được kích hoạt khi cấu hình mẫu in.', 'info')
}

function handleActionPrintResidence() {
  showActionsMenu.value = false
  uiStore.showToast('Chức năng In Đăng Ký Lưu Trú sẽ được kích hoạt khi cấu hình mẫu in.', 'info')
}

function handleActionPrintBreakfast() {
  showActionsMenu.value = false
  uiStore.showToast('Chức năng In phiếu ăn sáng sẽ được kích hoạt khi cấu hình mẫu in.', 'info')
}

function handleActionExportExcel() {
  showActionsMenu.value = false
  exportTableToCsv()
}

function exportTableToCsv() {
  const cols = visibleCols.value
  if (!cols.length || !rows.value.length) {
    uiStore.showToast('Không có dữ liệu để xuất excel.', 'warning')
    return
  }

  const headers = cols.map(c => `"${c.label.replace(/"/g, '""')}"`)
  const csvRows = [headers.join(',')]

  let dataList = []
  if (tab.value === 'room') {
    dataList = rows.value.flatMap(group => group.rooms || [])
  } else {
    dataList = rows.value
  }

  dataList.forEach(r => {
    const rowValues = cols.map(c => {
      let val = r[c.key]
      if (val === undefined || val === null) val = ''
      return `"${String(val).replace(/"/g, '""')}"`
    })
    csvRows.push(rowValues.join(','))
  })

  const csvContent = '\uFEFF' + csvRows.join('\r\n')
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.setAttribute('href', url)
  link.setAttribute('download', `tim-kiem-chung-${tab.value}-${new Date().toISOString().slice(0,10)}.csv`)
  document.body.appendChild(link)
  link.click()
  document.body.removeChild(link)
  URL.revokeObjectURL(url)
  uiStore.showToast('Đã xuất file excel thành công!', 'success')
}

function handleClickOutsideDropdown(e) {
  if (colDropdownRef.value && !colDropdownRef.value.contains(e.target)) {
    showColumnDropdown.value = false
  }
  if (actionsDropdownRef.value && !actionsDropdownRef.value.contains(e.target)) {
    showActionsMenu.value = false
    showNoshowSubmenu.value = false
  }
}

function handlePerPageChange() {
  search(1)
}

watch(tab, () => {
  expandedBookings.value = new Set()
  expandedRoomGroups.value = new Set()
  selectedIds.value = new Set()
  search(1)
})

const quickFilterValues = computed(() => {
  const obj = {
    use_date: filters.value.use_date,
    from_date: filters.value.from_date,
    to_date: filters.value.to_date
  }
  quickIds.value.forEach(id => {
    const def = FIELD_DEFINITIONS[id]
    if (def?.field) {
      obj[def.field] = filters.value[def.field]
    }
  })
  return obj
})

watch(quickFilterValues, () => {
  clearTimeout(autoSearchTimer)
  autoSearchTimer = setTimeout(() => search(1), 350)
}, { deep: true })

watch(() => filters.value.booking_code, () => {
  clearTimeout(suggestionTimer)
  if (suppressNextSuggestion) {
    suppressNextSuggestion = false
    return
  }
  suggestionTimer = setTimeout(loadBookingSuggestions, 180)
})

function setupEchoListeners() {
  if (echo) {
    echo.channel('pms-channel')
      .listen('.reservation.updated', () => {
        search(meta.value.current_page || 1)
      })
      .listen('.room.status.updated', () => {
        search(meta.value.current_page || 1)
      })
      .listen('.night.audit.updated', () => {
        initDefaultDates()
        search(1)
      })
  }
}

function teardownEchoListeners() {
  if (echo) {
    echo.channel('pms-channel')
      .stopListening('.reservation.updated')
      .stopListening('.room.status.updated')
      .stopListening('.night.audit.updated')
  }
}

onBeforeUnmount(() => {
  clearTimeout(autoSearchTimer)
  clearTimeout(suggestionTimer)
  window.removeEventListener('click', handleClickOutsideDropdown)
  teardownEchoListeners()
})

onMounted(async () => {
  restoreSorts()
  restoreLayout()
  restoreColumns()
  await initDefaultDates()
  advDraft.value = { ...filters.value }
  window.addEventListener('click', handleClickOutsideDropdown)
  setupEchoListeners()
  try {
    const { data } = await fetchGeneralSearchOptions()
    if (data?.data) options.value = data.data
  } catch (_) {}
  search(1)
})
</script>

<template>
  <main class="general-search-wrapper">
    <!-- TOP BAR: TABS & ACTIONS -->
    <div class="tabs-header">
      <div class="tabs-left">
        <button
          v-for="item in tabItems"
          :key="item.id"
          class="tab-btn"
          :class="{ active: tab === item.id }"
          @click="tab = item.id"
        >
          {{ item.label }}
        </button>
      </div>
      <div class="tabs-actions">
        <button
          class="btn-custom"
          :class="{ active: advancedOpen }"
          @click="advancedOpen = !advancedOpen"
        >
          Bộ lọc nâng cao <span class="badge-count">{{ advIds.length }}</span>
        </button>

        <!-- Nút Nhân bản: Luôn hiển thị ở Tab Đăng Ký (Image 1) -->
        <button
          v-if="tab === 'booking'"
          class="btn-primary"
          :disabled="selectedCount !== 1"
          :class="{ 'is-disabled': selectedCount !== 1 }"
          :title="selectedCount !== 1 ? 'Chỉ áp dụng khi chọn đúng 1 đăng ký' : 'Nhân bản đăng ký'"
          @click="handleCloneBooking"
        >
          Nhân bản <span v-if="selectedCount > 0">({{ selectedCount }})</span>
        </button>

        <!-- Nút Tìm kiếm: Luôn hiển thị -->
        <button class="btn-primary" @click="handleMainSearch">
          Tìm kiếm
        </button>

        <!-- Nút Thao tác: Luôn hiển thị (Image 1, 2, 3) -->
        <div ref="actionsDropdownRef" class="actions-dropdown-wrap">
          <button
            class="btn-primary action-btn-trigger"
            :class="{ active: showActionsMenu }"
            @click.stop="showActionsMenu = !showActionsMenu"
          >
            <span>Thao tác</span>
            <span class="dropdown-caret">▼</span>
          </button>

          <!-- MENU DROPDOWN CHỨC NĂNG THAO TÁC THEO TỪNG TAB -->
          <div v-if="showActionsMenu" class="actions-menu-popover" @click.stop>
            <!-- TAB ĐĂNG KÝ (Image 1) -->
            <template v-if="tab === 'booking'">
              <button
                class="action-menu-item"
                :disabled="selectedCount !== 1"
                :class="{ 'is-disabled': selectedCount !== 1 }"
                :title="selectedCount !== 1 ? 'Chỉ áp dụng khi chọn đúng 1 đăng ký' : 'Mở chi tiết phiếu đăng ký'"
                @click="handleActionRegister"
              >
                <i class="fa-solid fa-book-open item-icon"></i>
                <span>Đăng Ký</span>
              </button>
              <button
                class="action-menu-item"
                :disabled="selectedCount === 0"
                :class="{ 'is-disabled': selectedCount === 0 }"
                @click="handleActionReport"
              >
                <i class="fa-solid fa-chart-column item-icon"></i>
                <span>Báo cáo</span>
              </button>
              <button
                class="action-menu-item"
                :disabled="selectedCount === 0"
                :class="{ 'is-disabled': selectedCount === 0 }"
                @click="handleActionPrintConfirmation"
              >
                <i class="fa-solid fa-print item-icon"></i>
                <span>In Xác Nhận</span>
              </button>
              <button
                class="action-menu-item"
                :disabled="selectedCount === 0"
                :class="{ 'is-disabled': selectedCount === 0 }"
                @click="handleActionPrintResidence"
              >
                <i class="fa-solid fa-print item-icon"></i>
                <span>In Đăng Ký Lưu Trú</span>
              </button>
              <button
                class="action-menu-item"
                :disabled="selectedCount === 0"
                :class="{ 'is-disabled': selectedCount === 0 }"
                @click="handleActionExportExcel"
              >
                <i class="fa-solid fa-file-excel item-icon"></i>
                <span>Xuất excel</span>
              </button>
              <button class="action-menu-item" @click="handleActionLostFound">
                <i class="fa-solid fa-box-open item-icon"></i>
                <span>Đồ thất lạc</span>
              </button>
            </template>

            <!-- TAB PHÒNG (Image 2 & 4) -->
            <template v-else-if="tab === 'room'">
              <button
                class="action-menu-item"
                :disabled="selectedCount !== 1"
                :class="{ 'is-disabled': selectedCount !== 1 }"
                :title="selectedCount !== 1 ? 'Chỉ áp dụng khi chọn đúng 1 phòng' : 'Xem thông tin khách'"
                @click="handleActionGuestInfo"
              >
                <i class="fa-solid fa-id-card item-icon"></i>
                <span>Thông Tin Khách</span>
              </button>
              <button
                class="action-menu-item"
                :disabled="selectedCount !== 1"
                :class="{ 'is-disabled': selectedCount !== 1 }"
                :title="selectedCount !== 1 ? 'Chỉ áp dụng khi chọn đúng 1 phòng' : 'Mở hóa đơn / thu ngân'"
                @click="handleActionInvoice"
              >
                <i class="fa-solid fa-receipt item-icon"></i>
                <span>Hóa Đơn</span>
              </button>
              <button
                class="action-menu-item"
                :disabled="selectedCount !== 1"
                :class="{ 'is-disabled': selectedCount !== 1 }"
                :title="selectedCount !== 1 ? 'Chỉ áp dụng khi chọn đúng 1 phòng' : 'Mở chi tiết phiếu đăng ký'"
                @click="handleActionRegister"
              >
                <i class="fa-solid fa-book-open item-icon"></i>
                <span>Đăng Ký</span>
              </button>
              <button
                class="action-menu-item"
                :disabled="selectedCount === 0"
                :class="{ 'is-disabled': selectedCount === 0 }"
                @click="handleActionReport"
              >
                <i class="fa-solid fa-chart-column item-icon"></i>
                <span>Báo cáo</span>
              </button>
              <button
                class="action-menu-item"
                :disabled="selectedCount === 0"
                :class="{ 'is-disabled': selectedCount === 0 }"
                @click="handleActionPrintConfirmation"
              >
                <i class="fa-solid fa-print item-icon"></i>
                <span>In Xác Nhận</span>
              </button>
              <button
                class="action-menu-item"
                :disabled="selectedCount === 0"
                :class="{ 'is-disabled': selectedCount === 0 }"
                @click="handleActionPrintResidence"
              >
                <i class="fa-solid fa-print item-icon"></i>
                <span>In Đăng Ký Lưu Trú</span>
              </button>
              <button
                class="action-menu-item"
                :disabled="selectedCount === 0"
                :class="{ 'is-disabled': selectedCount === 0 }"
                @click="handleActionPrintBreakfast"
              >
                <i class="fa-solid fa-utensils item-icon"></i>
                <span>In phiếu ăn sáng</span>
              </button>
              <button
                class="action-menu-item"
                :disabled="selectedCount === 0"
                :class="{ 'is-disabled': selectedCount === 0 }"
                @click="handleActionExportExcel"
              >
                <i class="fa-solid fa-file-excel item-icon"></i>
                <span>Xuất excel</span>
              </button>
              <!-- NO SHOW VỚI SUBMENU CẤP 2 (Image 4) -->
              <div
                class="action-menu-item has-submenu"
                :class="{ 'is-disabled': selectedCount === 0 }"
                @mouseenter="selectedCount > 0 ? showNoshowSubmenu = true : null"
                @mouseleave="showNoshowSubmenu = false"
              >
                <div class="submenu-row-title">
                  <i class="fa-solid fa-eye-slash item-icon"></i>
                  <span>No show</span>
                  <span class="submenu-arrow">◀</span>
                </div>
                <!-- SUBMENU FLYOUT BÊN TRÁI -->
                <div v-if="showNoshowSubmenu && selectedCount > 0" class="action-submenu-flyout">
                  <button class="action-submenu-item" @click="handleActionNoShowOneDay">
                    No Show Một Ngày
                  </button>
                  <button class="action-submenu-item" @click="handleActionNoShowPeriod">
                    No Show Giai Đoạn
                  </button>
                </div>
              </div>
              <button
                class="action-menu-item"
                :disabled="selectedCount === 0"
                :class="{ 'is-disabled': selectedCount === 0 }"
                :title="selectedCount === 0 ? 'Vui lòng chọn ít nhất 1 phòng' : 'Nhận phòng nhanh'"
                @click="handleActionCheckIn"
              >
                <i class="fa-solid fa-paper-plane item-icon"></i>
                <span>Nhận phòng</span>
              </button>
              <button class="action-menu-item" @click="handleActionLostFound">
                <i class="fa-solid fa-box-open item-icon"></i>
                <span>Đồ thất lạc</span>
              </button>
            </template>

            <!-- TAB KHÁCH (Image 3) -->
            <template v-else-if="tab === 'guest'">
              <button
                class="action-menu-item"
                :disabled="selectedCount !== 1"
                :class="{ 'is-disabled': selectedCount !== 1 }"
                :title="selectedCount !== 1 ? 'Chỉ áp dụng khi chọn đúng 1 khách' : 'Xem thông tin khách'"
                @click="handleActionGuestInfo"
              >
                <i class="fa-solid fa-id-card item-icon"></i>
                <span>Thông Tin Khách</span>
              </button>
              <button
                class="action-menu-item"
                :disabled="selectedCount !== 1"
                :class="{ 'is-disabled': selectedCount !== 1 }"
                :title="selectedCount !== 1 ? 'Chỉ áp dụng khi chọn đúng 1 khách' : 'Mở hóa đơn / thu ngân'"
                @click="handleActionInvoice"
              >
                <i class="fa-solid fa-receipt item-icon"></i>
                <span>Hóa Đơn</span>
              </button>
              <button
                class="action-menu-item"
                :disabled="selectedCount !== 1"
                :class="{ 'is-disabled': selectedCount !== 1 }"
                :title="selectedCount !== 1 ? 'Chỉ áp dụng khi chọn đúng 1 khách' : 'Mở chi tiết phiếu đăng ký'"
                @click="handleActionRegister"
              >
                <i class="fa-solid fa-book-open item-icon"></i>
                <span>Đăng Ký</span>
              </button>
              <button
                class="action-menu-item"
                :disabled="selectedCount === 0"
                :class="{ 'is-disabled': selectedCount === 0 }"
                @click="handleActionReport"
              >
                <i class="fa-solid fa-chart-column item-icon"></i>
                <span>Báo cáo</span>
              </button>
              <button
                class="action-menu-item"
                :disabled="selectedCount === 0"
                :class="{ 'is-disabled': selectedCount === 0 }"
                @click="handleActionPrintConfirmation"
              >
                <i class="fa-solid fa-print item-icon"></i>
                <span>In Xác Nhận</span>
              </button>
              <button
                class="action-menu-item"
                :disabled="selectedCount === 0"
                :class="{ 'is-disabled': selectedCount === 0 }"
                @click="handleActionPrintResidence"
              >
                <i class="fa-solid fa-print item-icon"></i>
                <span>In Đăng Ký Lưu Trú</span>
              </button>
              <button
                class="action-menu-item"
                :disabled="selectedCount === 0"
                :class="{ 'is-disabled': selectedCount === 0 }"
                @click="handleActionExportExcel"
              >
                <i class="fa-solid fa-file-excel item-icon"></i>
                <span>Xuất excel</span>
              </button>
              <button class="action-menu-item" @click="handleActionLostFound">
                <i class="fa-solid fa-box-open item-icon"></i>
                <span>Đồ thất lạc</span>
              </button>
            </template>
          </div>
        </div>
      </div>
    </div>

    <!-- FILTER BAR -->
    <div class="filterbar">
      <div class="filterbar-row">
        <!-- Date Toggle Switch -->
        <label class="toggle-date">
          <input v-model="filters.use_date" type="checkbox" class="sr-only">
          <span class="switch-pill" :class="{ on: filters.use_date }"></span>
          <span>Tìm theo ngày</span>
        </label>

        <!-- Date Range Box -->
        <div class="date-range-box" :class="{ disabled: !filters.use_date }">
          <input
            ref="fromDateInput"
            v-model="filters.from_date"
            type="date"
            :disabled="!filters.use_date"
            @click="openDatePicker(fromDateInput)"
          >
          <span class="range-sep">~</span>
          <input
            ref="toDateInput"
            v-model="filters.to_date"
            type="date"
            :disabled="!filters.use_date"
            @click="openDatePicker(toDateInput)"
          >
          <button
            class="calendar-btn"
            :disabled="!filters.use_date"
            title="Chọn lịch"
            @click="openDatePicker(fromDateInput)"
          >
            📅
          </button>
        </div>

        <div class="divider-hint">Kéo field vào/ra để tùy chỉnh</div>

        <!-- QUICK ZONE: DRAG & DROP TARGET #1 -->
        <div
          id="quickZone"
          class="quick-zone"
          :class="{ 'drag-over': isQuickDragOver }"
          @dragover="handleDragOver('quick', $event)"
          @dragleave="handleDragLeave('quick')"
          @drop="handleDrop('quick', $event)"
        >
          <template v-for="id in sortedQuickIds" :key="id">
            <div
              v-if="FIELD_DEFINITIONS[id]"
              class="qpill"
              draggable="true"
              @dragstart="handleDragStart(id, $event)"
            >
              <span class="handle" title="Kéo thả">⠿</span>

              <!-- Mabk with Suggestion Dropdown -->
              <template v-if="id === 'mabk'">
                <div class="suggestion-wrap">
                  <input
                    v-model="filters.booking_code"
                    :placeholder="FIELD_DEFINITIONS[id].placeholder"
                    @focus="bookingSuggestions.length && (showSuggestions = true)"
                    @keyup.enter="search(1)"
                  >
                  <button
                    v-if="filters.booking_code"
                    class="clear-input"
                    title="Xóa"
                    @click.prevent="clearBookingCode"
                  >
                    ×
                  </button>
                  <div v-if="showSuggestions" class="suggestion-list">
                    <button
                      v-for="item in bookingSuggestions"
                      :key="item.id"
                      @mousedown.prevent="selectBookingSuggestion(item)"
                    >
                      <strong>{{ item.booking_code }}</strong>
                      <span>{{ item.booking_name || '—' }}</span>
                      <small v-if="item.reference_code">{{ item.reference_code }}</small>
                    </button>
                  </div>
                </div>
              </template>

              <!-- Select Controls -->
              <template v-else-if="FIELD_DEFINITIONS[id].type === 'select'">
                <select v-model="filters[FIELD_DEFINITIONS[id].field]">
                  <option value="">{{ FIELD_DEFINITIONS[id].label }}</option>
                  <option
                    v-for="opt in getSelectOptions(FIELD_DEFINITIONS[id].optionsKey)"
                    :key="opt.id || opt.value"
                    :value="opt.id !== undefined ? opt.id : opt.value"
                  >
                    {{ opt.code ? `${opt.code} — ` : '' }}{{ opt.name || opt.label || opt.username }}
                  </option>
                </select>
              </template>

              <!-- Date Controls -->
              <template v-else-if="FIELD_DEFINITIONS[id].type === 'date'">
                <input
                  v-model="filters[FIELD_DEFINITIONS[id].field]"
                  type="date"
                  :placeholder="FIELD_DEFINITIONS[id].placeholder"
                >
              </template>

              <!-- Input Controls -->
              <template v-else>
                <input
                  v-model="filters[FIELD_DEFINITIONS[id].field]"
                  :placeholder="FIELD_DEFINITIONS[id].placeholder || FIELD_DEFINITIONS[id].label"
                  @keyup.enter="search(1)"
                >
              </template>

              <!-- Move to Advanced Zone Button -->
              <button
                class="mv-btn"
                title="Chuyển vào bộ lọc nâng cao"
                @click="moveField(id, false)"
              >
                ⇥
              </button>
            </div>
          </template>
        </div>
      </div>

      <!-- ADVANCED PANEL: DRAG & DROP TARGET #2 -->
      <div v-if="advancedOpen" class="adv-panel">
        <div class="adv-title">
          <span>ĐIỀU KIỆN NÂNG CAO</span>
          <span class="adv-hint">Kéo thả một field ra thanh tìm nhanh để dùng thường xuyên hơn</span>
        </div>

        <div
          id="advZone"
          class="adv-zone"
          :class="{ 'drag-over': isAdvDragOver }"
          @dragover="handleDragOver('adv', $event)"
          @dragleave="handleDragLeave('adv')"
          @drop="handleDrop('adv', $event)"
        >
          <template v-if="sortedAdvIds.length">
            <div
              v-for="id in sortedAdvIds"
              :key="id"
              class="acard"
              draggable="true"
              @dragstart="handleDragStart(id, $event)"
            >
              <div class="acard-top">
                <label>{{ FIELD_DEFINITIONS[id].label }}</label>
                <button
                  class="mv-btn"
                  title="Đưa ra thanh tìm nhanh"
                  @click="moveField(id, true)"
                >
                  ⇤
                </button>
              </div>

              <!-- Input / Select Controls in Advanced Zone (chỉ lưu draft, không tìm kiếm realtime) -->
              <template v-if="id === 'mabk'">
                <input
                  v-model="advDraft.booking_code"
                  :placeholder="FIELD_DEFINITIONS[id].placeholder"
                  @keyup.enter="applyAdvancedFilters"
                >
              </template>
              <template v-else-if="FIELD_DEFINITIONS[id].type === 'select'">
                <select v-model="advDraft[FIELD_DEFINITIONS[id].field]">
                  <option value="">Tất cả</option>
                  <option
                    v-for="opt in getSelectOptions(FIELD_DEFINITIONS[id].optionsKey)"
                    :key="opt.id || opt.value"
                    :value="opt.id !== undefined ? opt.id : opt.value"
                  >
                    {{ opt.code ? `${opt.code} — ` : '' }}{{ opt.name || opt.label || opt.username }}
                  </option>
                </select>
              </template>
              <template v-else-if="FIELD_DEFINITIONS[id].type === 'date'">
                <input
                  v-model="advDraft[FIELD_DEFINITIONS[id].field]"
                  type="date"
                >
              </template>
              <template v-else>
                <input
                  v-model="advDraft[FIELD_DEFINITIONS[id].field]"
                  :placeholder="FIELD_DEFINITIONS[id].placeholder || FIELD_DEFINITIONS[id].label"
                  @keyup.enter="applyAdvancedFilters"
                >
              </template>
            </div>
          </template>
          <div v-else class="empty-adv-slot">
            Kéo field vào đây để đưa vào bộ lọc nâng cao
          </div>
        </div>

        <div class="adv-actions">
          <button class="btn-custom" @click="clearAdvancedFilters">Xóa lọc</button>
          <button class="btn-primary" @click="applyAdvancedFilters">Áp dụng</button>
        </div>
      </div>
    </div>

    <!-- TOOLBAR: TOTAL COUNT & DROPDOWN COLUMN MANAGER (Image 1) -->
    <div class="toolbar">
      <div class="toolbar-left">
        Tổng: <strong>{{ meta.total || 0 }}</strong> kết quả
        <span v-if="loading" class="loading-tag">Đang tải dữ liệu…</span>
        <span v-if="errorMessage" class="error-tag">{{ errorMessage }}</span>
      </div>
      <div class="toolbar-right" ref="colDropdownRef">
        <button
          class="btn-custom"
          :class="{ active: showColumnDropdown }"
          @click.stop="showColumnDropdown = !showColumnDropdown"
        >
          ⚙ Cột hiển thị
        </button>

        <!-- DROPDOWN LIST WITH CHECKBOXES & DRAGGABLE REORDERING (Image 1) -->
        <div v-if="showColumnDropdown" class="col-dropdown-menu" @click.stop>
          <div class="col-dropdown-header">
            <span class="col-dropdown-title">Cột hiển thị ({{ tab === 'booking' ? 'Đăng Ký' : tab === 'room' ? 'Phòng' : 'Khách' }})</span>
            <div class="col-dropdown-quick-actions">
              <button class="text-link-btn" @click="selectAllCols(true)">Hiện tất cả</button>
              <span class="sep-dot">·</span>
              <button class="text-link-btn" @click="resetColsDefault">Mặc định</button>
            </div>
          </div>
          <div class="col-dropdown-list">
            <div
              v-for="(col, index) in activeColumns[tab]"
              :key="col.key"
              class="col-dropdown-item"
              :class="{
                'is-dragging': draggingColIndex === index,
                'drag-target': dragOverColIndex === index
              }"
              draggable="true"
              @dragstart="onColDragStart(index, $event)"
              @dragover="onColDragOver(index, $event)"
              @dragleave="onColDragLeave"
              @drop="onColDrop(index, $event)"
            >
              <span class="col-drag-handle" title="Kéo để đổi vị trí">⠿</span>
              <label class="col-checkbox-label">
                <input
                  v-model="col.visible"
                  type="checkbox"
                  class="col-checkbox"
                  @change="persistColumns"
                >
                <span class="col-label-text">{{ col.label }}</span>
              </label>
              <div class="col-move-actions">
                <button
                  class="col-btn-arrow"
                  :disabled="index === 0"
                  title="Di chuyển lên"
                  @click.stop="moveCol(index, -1)"
                >
                  ▲
                </button>
                <button
                  class="col-btn-arrow"
                  :disabled="index === activeColumns[tab].length - 1"
                  title="Di chuyển xuống"
                  @click.stop="moveCol(index, 1)"
                >
                  ▼
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- TABLE CONTAINER -->
    <div class="tablewrap">
      <!-- TAB 1: ĐĂNG KÝ (sp_034) -->
      <table v-if="tab === 'booking'" class="data-table">
        <thead>
          <tr>
            <th style="width: 36px; text-align: center;">
              <input type="checkbox" :checked="allCurrentSelected" @change="toggleAllSelected">
            </th>
            <th style="width: 36px; text-align: center;"></th>
            <template v-for="col in visibleCols" :key="col.key">
              <th
                :class="{ sortable: col.sortable, number: col.isNumber }"
                @click="col.sortable ? setSort(col.key) : null"
              >
                {{ col.label }} <span v-if="col.sortable">{{ sortIcon(col.key) }}</span>
              </th>
            </template>
          </tr>
        </thead>
        <tbody>
          <template v-for="row in rows" :key="row.id">
            <tr :class="{ selected: isSelected(row) }">
              <td style="text-align: center;">
                <input type="checkbox" :checked="isSelected(row)" @change="toggleSelected(row)">
              </td>
              <td style="text-align: center;">
                <button class="expand-btn" @click="toggleBooking(row.id)">
                  {{ expandedBookings.has(row.id) ? '−' : '+' }}
                </button>
              </td>

              <!-- Dynamic columns according to active reordered columns -->
              <template v-for="col in visibleCols" :key="col.key">
                <td v-if="col.key === 'booking_code'" class="mono">{{ row.booking_code }}</td>
                <td v-else-if="col.key === 'reference_code'">{{ row.reference_code || '—' }}</td>
                <td v-else-if="col.key === 'booking_name'" class="name-cell font-medium">{{ row.booking_name }}</td>
                <td v-else-if="col.key === 'company'" class="wrap-cell">{{ row.company || 'KHÁCH LẺ' }}</td>
                <td v-else-if="col.key === 'market'" class="wrap-cell">{{ row.market || '—' }}</td>
                <td v-else-if="col.key === 'arrival_date'">{{ date(row.arrival_date) }}</td>
                <td v-else-if="col.key === 'departure_date'">{{ date(row.departure_date) }}</td>
                <td v-else-if="col.key === 'nights'">{{ row.nights }}</td>
                <td v-else-if="col.key === 'original_room_types'" class="room-class-cell">{{ row.original_room_types || '—' }}</td>
                <td v-else-if="col.key === 'actual_room_types'" class="room-class-cell">{{ row.actual_room_types || '—' }}</td>
                <td v-else-if="col.key === 'total_amount'" class="number font-medium">{{ money(row.total_amount) }}</td>
                <td v-else-if="col.key === 'deposit_amount'" class="number">{{ money(row.deposit_amount) }}</td>
                <td v-else-if="col.key === 'registration_status'">
                  <span class="status-pill status-guaranteed">{{ row.registration_status || 'Guaranteed' }}</span>
                </td>
                <td v-else-if="col.key === 'operation_status'">
                  <span class="status-pill" :class="`status-${row.operation_status}`">{{ statusText(row.operation_status) }}</span>
                </td>
                <td v-else-if="col.key === 'contact_phone'">{{ row.contact_phone || '—' }}</td>
                <td v-else-if="col.key === 'note'" class="note-cell">{{ row.note || '—' }}</td>
                <td v-else-if="col.key === 'booking_date'">{{ date(row.booking_date) }}</td>
                <td v-else-if="col.key === 'sales_person'" class="wrap-cell">{{ row.sales_person || '—' }}</td>
                <td v-else-if="col.key === 'created_by'" class="wrap-cell">{{ row.created_by || '—' }}</td>
                <td v-else>{{ row[col.key] || '—' }}</td>
              </template>
            </tr>

            <!-- SUB-TABLE PHÒNG CON KHI MỞ RỘNG (Gọn gàng đúng theo Ảnh 3) -->
            <tr v-if="expandedBookings.has(row.id)" class="subtable-row">
              <td colspan="2"></td>
              <td :colspan="visibleCols.length" class="subtable-td">
                <div class="subtable-container">
                  <table class="compact-subtable">
                    <thead>
                      <tr>
                        <th class="text-left" style="min-width: 100px;">Loại Phòng</th>
                        <th class="text-center" style="width: 70px;">#Phòng</th>
                        <th class="text-center" style="width: 70px;">#N.Lớn</th>
                        <th class="text-center" style="width: 70px;">#T.Em</th>
                        <th class="text-center" style="width: 95px;">Ngày Đến</th>
                        <th class="text-center" style="width: 95px;">Ngày Đi</th>
                        <th class="text-center" style="width: 105px;">Mã Giá Phòng</th>
                        <th class="number" style="width: 95px;">Giá Phòng</th>
                        <th class="number" style="width: 95px;">Tổng</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr
                        v-for="(grp, gIdx) in getBookingRoomGroups(row.rooms)"
                        :key="gIdx"
                      >
                        <td class="text-left font-bold text-slate-800">{{ grp.room_type }}</td>
                        <td class="text-center">{{ grp.room_count }}</td>
                        <td class="text-center">{{ grp.adults }}</td>
                        <td class="text-center">{{ grp.children }}</td>
                        <td class="text-center">{{ date(grp.arrival_date) }}</td>
                        <td class="text-center">{{ date(grp.departure_date) }}</td>
                        <td class="text-center">{{ grp.rate_code }}</td>
                        <td class="number">{{ money(grp.rate) }}</td>
                        <td class="number">{{ money(grp.service_total) }}</td>
                      </tr>
                      <!-- Total summary row at bottom of subtable (Image 3) -->
                      <tr class="subtable-total-row">
                        <td class="text-left font-bold">Tổng</td>
                        <td class="text-center font-bold">{{ getSubtableTotals(getBookingRoomGroups(row.rooms)).rooms }}</td>
                        <td class="text-center font-bold">{{ getSubtableTotals(getBookingRoomGroups(row.rooms)).adults }}</td>
                        <td class="text-center font-bold">{{ getSubtableTotals(getBookingRoomGroups(row.rooms)).children }}</td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td class="number font-bold">{{ money(getSubtableTotals(getBookingRoomGroups(row.rooms)).total) }}</td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </td>
            </tr>
          </template>
          <tr v-if="!loading && rows.length === 0" class="empty-row">
            <td :colspan="visibleCols.length + 2">Không có kết quả nào phù hợp</td>
          </tr>
        </tbody>
      </table>

      <!-- TAB 2: PHÒNG (sp_041) -->
      <table v-else-if="tab === 'room'" class="data-table room-table">
        <thead>
          <tr>
            <th style="width: 36px; text-align: center;">
              <input type="checkbox" :checked="allCurrentSelected" @change="toggleAllSelected">
            </th>
            <th style="width: 36px; text-align: center;"></th>
            <template v-for="col in visibleCols" :key="col.key">
              <th
                :class="{ sortable: col.sortable, number: col.isNumber }"
                @click="col.sortable ? setSort(col.key) : null"
              >
                {{ col.label }} <span v-if="col.sortable">{{ sortIcon(col.key) }}</span>
              </th>
            </template>
          </tr>
        </thead>
        <tbody>
          <template v-for="group in rows" :key="group.booking_id">
            <!-- MASTER BOOKING BANNER -->
            <tr class="master-banner-row">
              <td style="text-align: center;">
                <button class="expand-btn" @click="toggleRoomGroup(group.booking_id)">
                  {{ expandedRoomGroups.has(group.booking_id) ? '−' : '+' }}
                </button>
              </td>
              <td :colspan="Math.max(1, visibleCols.length - 2)" class="master-banner-left">
                <div class="banner-title">
                  <strong>Booking {{ group.booking_code }}: {{ group.booking_name }}</strong>
                  <span class="banner-dates">· {{ date(group.arrival_date) }} ~ {{ date(group.departure_date) }}</span>
                  <span class="banner-night">· Room Night: {{ group.nights }}</span>
                </div>
                <div class="banner-note">Ghi chú: {{ group.note || '—' }}</div>
              </td>
              <td colspan="3" class="master-banner-right">
                <div class="banner-money">Tiền dịch vụ: <strong>{{ money(group.service_total) }}</strong></div>
                <div class="banner-money">Tiền đã thanh toán: <strong>{{ money(group.paid_total) }}</strong></div>
              </td>
            </tr>

            <!-- ROOM ITEMS UNDER MASTER BOOKING -->
            <template v-if="expandedRoomGroups.has(group.booking_id)">
              <tr
                v-for="room in group.rooms"
                :key="room.id"
                :class="{ selected: isSelected(room) }"
              >
                <td style="text-align: center;">
                  <input type="checkbox" :checked="isSelected(room)" @change="toggleSelected(room)">
                </td>
                <td></td>

                <!-- Dynamic room columns according to active reordered columns -->
                <template v-for="col in visibleCols" :key="col.key">
                  <td v-if="col.key === 'room_number'" class="mono">{{ room.room_number || '—' }}</td>
                  <td v-else-if="col.key === 'room_status'">
                    <span class="status-pill" :class="`status-${room.room_status}`">{{ statusText(room.room_status) }}</span>
                  </td>
                  <td v-else-if="col.key === 'guest_name'" class="name-cell font-medium">{{ room.guest_name || '—' }}</td>
                  <td v-else-if="col.key === 'arrival_date'">{{ date(room.arrival_date) }}</td>
                  <td v-else-if="col.key === 'departure_date'">{{ date(room.departure_date) }}</td>
                  <td v-else-if="col.key === 'nights'">{{ room.nights }}</td>
                  <td v-else-if="col.key === 'rate'" class="number font-medium">{{ money(room.rate) }}</td>
                  <td v-else-if="col.key === 'rate_code'">{{ room.rate_code || '—' }}</td>
                  <td v-else-if="col.key === 'actual_room_class'" class="room-class-cell">{{ room.room_class?.code || '—' }}</td>
                  <td v-else-if="col.key === 'original_room_class'" class="room-class-cell">{{ room.original_room_class?.code || '—' }}</td>
                  <td v-else-if="col.key === 'extra_bed_qty'">{{ room.extra_bed_qty }}</td>
                  <td v-else-if="col.key === 'extra_bed_rate'" class="number">{{ money(room.extra_bed_rate) }}</td>
                  <td v-else-if="col.key === 'adults'">{{ room.adults }}</td>
                  <td v-else-if="col.key === 'children'">{{ room.children }}</td>
                  <td v-else-if="col.key === 'note'" class="note-cell">{{ room.note || '—' }}</td>
                  <td v-else-if="col.key === 'service_total'" class="number font-medium">{{ money(room.service_total) }}</td>
                  <td v-else-if="col.key === 'paid_total'" class="number">{{ money(room.paid_total) }}</td>
                  <td v-else-if="col.key === 'checkin_time'">{{ room.checkin_time || '—' }}</td>
                  <td v-else-if="col.key === 'checkout_time'">{{ room.checkout_time || '12:30' }}</td>
                  <td v-else-if="col.key === 'booking_date'">{{ date(room.booking_date) }}</td>
                  <td v-else>{{ room[col.key] || '—' }}</td>
                </template>
              </tr>
            </template>
          </template>
          <tr v-if="!loading && rows.length === 0" class="empty-row">
            <td :colspan="visibleCols.length + 2">Không có kết quả nào phù hợp</td>
          </tr>
        </tbody>
      </table>

      <!-- TAB 3: KHÁCH (sp_043) -->
      <table v-else class="data-table guest-table">
        <thead>
          <tr>
            <th style="width: 36px; text-align: center;">
              <input type="checkbox" :checked="allCurrentSelected" @change="toggleAllSelected">
            </th>
            <template v-for="col in visibleCols" :key="col.key">
              <th
                :class="{ sortable: col.sortable, number: col.isNumber }"
                @click="col.sortable ? setSort(col.key) : null"
              >
                {{ col.label }} <span v-if="col.sortable">{{ sortIcon(col.key) }}</span>
              </th>
            </template>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in rows"
            :key="row.id"
            :class="{ selected: isSelected(row) }"
          >
            <td style="text-align: center;">
              <input type="checkbox" :checked="isSelected(row)" @change="toggleSelected(row)">
            </td>

            <!-- Dynamic guest columns according to active reordered columns -->
            <template v-for="col in visibleCols" :key="col.key">
              <td v-if="col.key === 'guest_name'" class="name-cell font-medium">{{ row.guest_name }}</td>
              <td v-else-if="col.key === 'booking_code'" class="mono">{{ row.booking_code }}</td>
              <td v-else-if="col.key === 'room_number'">{{ row.room_number || '—' }}</td>
              <td v-else-if="col.key === 'arrival_date'">{{ date(row.arrival_date) }}</td>
              <td v-else-if="col.key === 'departure_date'">{{ date(row.departure_date) }}</td>
              <td v-else-if="col.key === 'nights'">{{ row.nights }}</td>
              <td v-else-if="col.key === 'rate'" class="number font-medium">{{ money(row.rate) }}</td>
              <td v-else-if="col.key === 'rate_code'">{{ row.rate_code || '—' }}</td>
              <td v-else-if="col.key === 'company'" class="wrap-cell">{{ row.company || 'KHÁCH LẺ' }}</td>
              <td v-else-if="col.key === 'id_type'">{{ row.id_type || '—' }}</td>
              <td v-else-if="col.key === 'id_number'">{{ row.id_number || '—' }}</td>
              <td v-else-if="col.key === 'email'" class="wrap-cell">{{ row.email || '—' }}</td>
              <td v-else-if="col.key === 'phone'">{{ row.phone || '—' }}</td>
              <td v-else-if="col.key === 'dob'">{{ date(row.dob) }}</td>
              <td v-else-if="col.key === 'nationality'" class="wrap-cell">{{ row.nationality || '—' }}</td>
              <td v-else-if="col.key === 'province'" class="wrap-cell">{{ row.province || '—' }}</td>
              <td v-else-if="col.key === 'address'" class="note-cell">{{ row.address || '—' }}</td>
              <td v-else-if="col.key === 'visa_no'">{{ row.visa_no || '—' }}</td>
              <td v-else-if="col.key === 'visa_expiry_date'">{{ date(row.visa_expiry_date) }}</td>
              <td v-else-if="col.key === 'entry_date'">{{ date(row.entry_date) }}</td>
              <td v-else-if="col.key === 'border_gate'" class="wrap-cell">{{ row.border_gate || '—' }}</td>
              <td v-else>{{ row[col.key] || '—' }}</td>
            </template>
          </tr>
          <tr v-if="!loading && rows.length === 0" class="empty-row">
            <td :colspan="visibleCols.length + 1">Không có kết quả nào phù hợp</td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- PAGER FOOTER -->
    <div class="pager">
      <div class="pager-left">
        <select v-model.number="perPage" class="per-page-select" @change="handlePerPageChange">
          <option v-for="size in perPageOptions" :key="size" :value="size">{{ size }} / trang</option>
        </select>
      </div>
      <div class="pager-center">
        <button
          class="pg"
          :disabled="meta.current_page <= 1"
          @click="search(meta.current_page - 1)"
        >
          ‹
        </button>
        <button
          v-for="p in Math.min(meta.last_page || 1, 7)"
          :key="p"
          class="pg"
          :class="{ active: meta.current_page === p }"
          @click="search(p)"
        >
          {{ p }}
        </button>
        <span v-if="(meta.last_page || 1) > 7" class="pg-more">…</span>
        <button
          class="pg"
          :disabled="meta.current_page >= (meta.last_page || 1)"
          @click="search(meta.current_page + 1)"
        >
          ›
        </button>
      </div>
      <div class="pager-right">
        Tổng: <strong>{{ meta.total || 0 }}</strong>
      </div>
    </div>

    <!-- THÔNG TIN KHÁCH MODAL -->
    <GuestInfoModal
      :show="showGuestInfoModal"
      :bookingId="selectedGuestInfoBookingId"
      @close="showGuestInfoModal = false"
    />

    <!-- NHÂN BẢN BOOKING MODAL -->
    <CopyModal
      v-model:show="showCopyModal"
      :bookingId="copyModalBookingId"
      :defaultArrival="copyModalArrivalDate"
      :defaultDeparture="copyModalDepartureDate"
      @copied="handleBookingCopied"
    />

    <!-- NO-SHOW CONFIRMATION MODAL (Hình 2: Xác nhận 3 tùy chọn tính phí) -->
    <div v-if="noShowModal.open" class="noshow-modal-backdrop" @click="closeNoShowModal">
      <div class="noshow-modal-box" @click.stop>
        <div class="noshow-modal-header">
          <span class="noshow-modal-title">Xác nhận</span>
          <button type="button" class="noshow-modal-close" @click="closeNoShowModal">✕</button>
        </div>
        <div class="noshow-modal-body">
          <div class="noshow-options-grid">
            <button
              type="button"
              class="noshow-option-card"
              :class="{ active: noShowModal.selectedOption === 'all_charged' }"
              @click="noShowModal.selectedOption = 'all_charged'"
            >
              <span>Tính phí tất cả</span>
            </button>
            <button
              type="button"
              class="noshow-option-card"
              :class="{ active: noShowModal.selectedOption === 'room_only' }"
              @click="noShowModal.selectedOption = 'room_only'"
            >
              <span>Tính phí tiền phòng</span>
            </button>
            <button
              type="button"
              class="noshow-option-card"
              :class="{ active: noShowModal.selectedOption === 'no_charge' }"
              @click="noShowModal.selectedOption = 'no_charge'"
            >
              <span>không tính phí</span>
            </button>
          </div>
        </div>
        <div class="noshow-modal-footer">
          <button type="button" class="noshow-btn-action" @click="closeNoShowModal">
            Không
          </button>
          <button type="button" class="noshow-btn-action" @click="confirmNoShowAction">
            Có
          </button>
        </div>
      </div>
    </div>
  </main>
</template>

<style scoped>
:root {
  --blue: #1f6fd6;
  --blue-dark: #0c4ea0;
  --blue-bg: #e8f1fc;
  --green: #7cc242;
  --ink: #1f2430;
  --ink-soft: #5b6270;
  --ink-mute: #8b909c;
  --line: #e3e6ec;
  --line-strong: #c9ced8;
  --surface: #ffffff;
  --page: #f4f5f7;
  --radius: 6px;
}

.general-search-wrapper {
  display: flex;
  flex-direction: column;
  height: 100%;
  background: #f4f5f7;
  color: #1f2430;
  font-size: 13px;
  overflow: hidden;
  padding: 12px 16px;
  box-sizing: border-box;
}

/* TABS HEADER */
.tabs-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-bottom: 1px solid #e3e6ec;
  padding: 0 4px 6px;
  margin-bottom: 10px;
}
.tabs-left {
  display: flex;
  gap: 22px;
}
.tab-btn {
  padding: 8px 4px;
  font-size: 14px;
  color: #8b909c;
  cursor: pointer;
  border: 0;
  background: transparent;
  border-bottom: 2px solid transparent;
  font-weight: 500;
  transition: color 0.15s;
}
.tab-btn.active {
  color: #1f6fd6;
  border-color: #1f6fd6;
  font-weight: 700;
}
.tabs-actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

/* BUTTONS */
.btn-custom {
  height: 34px;
  border-radius: 6px;
  border: 1px solid #c9ced8;
  background: #fff;
  color: #1f2430;
  font-size: 13px;
  padding: 0 14px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  cursor: pointer;
  white-space: nowrap;
}
.btn-custom:hover {
  background: #f7f8fa;
}
.btn-custom.active {
  border-color: #1f6fd6;
  color: #1f6fd6;
}
.btn-ghost {
  height: 34px;
  border-radius: 6px;
  border: 1px solid transparent;
  background: transparent;
  color: #5b6270;
  font-size: 13px;
  padding: 0 12px;
  cursor: default;
  opacity: 0.6;
}
.btn-ghost.enabled {
  opacity: 1;
  cursor: pointer;
  border-color: #c9ced8;
  background: #fff;
  color: #1f2430;
}
.btn-ghost.enabled:hover {
  background: #f7f8fa;
}
.btn-primary {
  height: 34px;
  border-radius: 6px;
  border: 1px solid #1f6fd6;
  background: #1f6fd6;
  color: #fff;
  font-size: 13px;
  font-weight: 600;
  padding: 0 16px;
  display: inline-flex;
  align-items: center;
  cursor: pointer;
  white-space: nowrap;
}
.btn-primary:hover {
  background: #0c4ea0;
}
.btn-primary:disabled,
.btn-primary.is-disabled {
  opacity: 0.5;
  cursor: not-allowed;
  background: #94a3b8;
  border-color: #94a3b8;
  pointer-events: none;
}
.badge-count {
  background: #e8f1fc;
  color: #0c4ea0;
  font-size: 11px;
  font-weight: 700;
  border-radius: 999px;
  padding: 1px 7px;
  margin-left: 2px;
}

/* FILTER BAR */
.filterbar {
  background: #fff;
  border: 1px solid #e3e6ec;
  border-radius: 8px;
  padding: 12px 14px;
  margin-bottom: 10px;
  flex-shrink: 0;
}
.filterbar-row {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

/* Date Toggle & Box */
.toggle-date {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  height: 34px;
  padding: 0 10px;
  border: 1px solid #c9ced8;
  border-radius: 6px;
  background: #fff;
  color: #5b6270;
  font-size: 12.5px;
  white-space: nowrap;
  cursor: pointer;
  user-select: none;
}
.switch-pill {
  width: 30px;
  height: 16px;
  border-radius: 999px;
  background: #cbd5e1;
  position: relative;
  transition: background 0.2s;
}
.switch-pill::after {
  content: "";
  position: absolute;
  left: 2px;
  top: 2px;
  width: 12px;
  height: 12px;
  border-radius: 50%;
  background: #fff;
  transition: transform 0.2s;
}
.switch-pill.on {
  background: #1f6fd6;
}
.switch-pill.on::after {
  transform: translateX(14px);
}
.date-range-box {
  display: flex;
  align-items: center;
  height: 34px;
  border: 1px solid #c9ced8;
  border-radius: 6px;
  background: #fff;
  padding: 0 8px;
  gap: 6px;
}
.date-range-box input {
  border: 0;
  outline: 0;
  font-size: 12.5px;
  color: #1f2430;
  background: transparent;
  width: 120px;
}
.date-range-box.disabled {
  background: #f8fafc;
  opacity: 0.6;
}
.range-sep {
  color: #8b909c;
}
.calendar-btn {
  border: 0;
  background: transparent;
  cursor: pointer;
  font-size: 14px;
  padding: 0;
}
.divider-hint {
  font-size: 11px;
  color: #8b909c;
  border-left: 1px dashed #c9ced8;
  padding-left: 10px;
  margin-left: 2px;
}

/* QUICK ZONE & PILLS */
.quick-zone {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  min-height: 34px;
  padding: 2px;
  border-radius: 6px;
  transition: outline 0.15s;
}
.quick-zone.drag-over {
  outline: 2px dashed #1f6fd6;
  outline-offset: 2px;
}
.qpill {
  position: relative;
  display: flex;
  align-items: center;
  gap: 4px;
  height: 34px;
  border: 1px solid #c9ced8;
  border-radius: 6px;
  background: #fff;
  padding: 0 6px 0 8px;
  cursor: grab;
}
.qpill input, .qpill select {
  border: none;
  outline: none;
  font-size: 13px;
  color: #1f2430;
  width: 130px;
  background: transparent;
}
.qpill select {
  width: 155px;
}
.qpill .handle {
  color: #8b909c;
  font-size: 14px;
  cursor: grab;
  user-select: none;
}
.mv-btn {
  border: none;
  background: transparent;
  color: #8b909c;
  cursor: pointer;
  font-size: 13px;
  padding: 2px 4px;
  border-radius: 4px;
  line-height: 1;
}
.mv-btn:hover {
  background: #e8f1fc;
  color: #0c4ea0;
}

/* ADVANCED PANEL & ZONE */
.adv-panel {
  border-top: 1px solid #e3e6ec;
  margin-top: 12px;
  padding-top: 12px;
}
.adv-title {
  display: flex;
  align-items: center;
  justify-content: space-between;
  font-size: 12px;
  font-weight: 700;
  color: #5b6270;
  margin: 0 0 10px;
  letter-spacing: 0.03em;
}
.adv-hint {
  font-size: 11px;
  color: #8b909c;
  font-weight: 400;
  letter-spacing: 0;
}
.adv-zone {
  display: grid;
  grid-template-columns: repeat(6, minmax(0, 1fr));
  gap: 8px 12px;
  min-height: 44px;
  border: 1px solid #c9ced8;
  border-radius: 8px;
  background: #fbfbfc;
  padding: 12px 14px;
}
.adv-zone.drag-over {
  outline: 2px dashed #1f6fd6;
  outline-offset: -2px;
}
.acard {
  position: relative;
  padding: 0;
  background: transparent;
  cursor: grab;
  min-width: 0;
}
.acard-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 3px;
  gap: 4px;
}
.acard-top label {
  font-size: 10.5px;
  color: #8b909c;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  font-weight: 600;
}
.acard input, .acard select {
  width: 100%;
  height: 30px;
  border: 1px solid #c9ced8;
  border-radius: 4px;
  padding: 0 6px;
  font-size: 12.5px;
  background: #fff;
  box-sizing: border-box;
}
.empty-adv-slot {
  grid-column: span 6;
  border: 1px dashed #c9ced8;
  color: #8b909c;
  display: flex;
  align-items: center;
  justify-content: center;
  height: 56px;
  font-size: 12px;
  border-radius: 6px;
}
.adv-actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  margin-top: 12px;
  border-top: 1px solid #e3e6ec;
  padding-top: 10px;
}

/* SUGGESTIONS */
.suggestion-wrap {
  position: relative;
  display: flex;
  align-items: center;
}
.suggestion-wrap input {
  padding-right: 18px;
}
.clear-input {
  position: absolute;
  right: 2px;
  border: 0;
  background: transparent;
  color: #8b909c;
  font-size: 16px;
  cursor: pointer;
  padding: 0;
  line-height: 1;
}
.suggestion-list {
  position: absolute;
  z-index: 20;
  top: 36px;
  left: 0;
  width: 320px;
  max-height: 260px;
  overflow: auto;
  border: 1px solid #c9ced8;
  border-radius: 6px;
  background: #fff;
  box-shadow: 0 8px 20px rgba(31, 36, 48, 0.14);
}
.suggestion-list button {
  display: grid;
  grid-template-columns: 70px 1fr;
  gap: 2px 8px;
  width: 100%;
  border: 0;
  border-bottom: 1px solid #f0f2f5;
  background: #fff;
  padding: 8px 10px;
  text-align: left;
  cursor: pointer;
  font-size: 12px;
  color: #1f2430;
}
.suggestion-list button:hover {
  background: #e8f1fc;
}
.suggestion-list strong {
  color: #1f6fd6;
}
.suggestion-list span {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.suggestion-list small {
  grid-column: 2;
  color: #8b909c;
}

/* TOOLBAR & DROPDOWN COLUMN MANAGER */
.toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin: 6px 0 8px;
  flex-shrink: 0;
}
.toolbar-left {
  color: #5b6270;
  font-size: 12.5px;
}
.toolbar-right {
  position: relative;
}
.loading-tag {
  color: #1f6fd6;
  margin-left: 8px;
  font-weight: 500;
}
.error-tag {
  color: #dc2626;
  margin-left: 8px;
  font-weight: 600;
}

/* DROPDOWN MENU FOR COLUMNS (Image 1) */
.col-dropdown-menu {
  position: absolute;
  top: 100%;
  right: 0;
  margin-top: 4px;
  z-index: 60;
  width: 260px;
  background: #fff;
  border: 1px solid #c9ced8;
  border-radius: 6px;
  box-shadow: 0 6px 20px rgba(15, 23, 42, 0.15);
  display: flex;
  flex-direction: column;
  overflow: hidden;
}
.col-dropdown-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 12px;
  background: #f8fafc;
  border-bottom: 1px solid #e3e6ec;
  font-size: 11.5px;
}
.col-dropdown-title {
  font-weight: 700;
  color: #334155;
}
.col-dropdown-quick-actions {
  display: flex;
  align-items: center;
  gap: 4px;
}
.text-link-btn {
  border: 0;
  background: transparent;
  color: #1f6fd6;
  font-size: 11.5px;
  font-weight: 600;
  cursor: pointer;
  padding: 0;
}
.text-link-btn:hover {
  text-decoration: underline;
}
.sep-dot {
  color: #94a3b8;
}
.col-dropdown-list {
  max-height: 320px;
  overflow-y: auto;
  padding: 4px 0;
}
.col-dropdown-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 6px 10px;
  gap: 6px;
  border-bottom: 1px solid #f8fafc;
  transition: background 0.15s;
  user-select: none;
}
.col-dropdown-item:hover {
  background: #f1f5f9;
}
.col-dropdown-item.is-dragging {
  opacity: 0.4;
  background: #e2e8f0;
}
.col-dropdown-item.drag-target {
  border-top: 2px solid #1f6fd6;
}
.col-drag-handle {
  color: #94a3b8;
  font-size: 14px;
  cursor: grab;
  user-select: none;
}
.col-checkbox-label {
  display: flex;
  align-items: center;
  gap: 8px;
  flex: 1;
  min-width: 0;
  cursor: pointer;
}
.col-checkbox {
  width: 16px;
  height: 16px;
  accent-color: #1f6fd6;
  cursor: pointer;
}
.col-label-text {
  font-size: 12.5px;
  color: #1e293b;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.col-move-actions {
  display: flex;
  gap: 2px;
}
.col-btn-arrow {
  border: 0;
  background: transparent;
  color: #94a3b8;
  font-size: 10px;
  padding: 2px 4px;
  cursor: pointer;
  border-radius: 3px;
}
.col-btn-arrow:hover:not(:disabled) {
  background: #e2e8f0;
  color: #1f6fd6;
}
.col-btn-arrow:disabled {
  opacity: 0.25;
  cursor: default;
}

/* TABLE */
.tablewrap {
  flex: 1;
  background: #fff;
  border: 1px solid #e3e6ec;
  border-radius: 8px;
  overflow: auto;
  min-height: 0;
}
.data-table {
  border-collapse: collapse;
  width: 100%;
  min-width: 1400px;
}
.data-table thead th {
  position: sticky;
  top: 0;
  z-index: 2;
  background: #f7f8fa;
  text-align: left;
  font-size: 11.5px;
  color: #5b6270;
  font-weight: 700;
  padding: 10px 10px;
  border-bottom: 1px solid #e3e6ec;
  border-right: 1px solid #f0f2f5;
  white-space: nowrap;
}
.data-table tbody td {
  padding: 9px 10px;
  border-bottom: 1px solid #e3e6ec;
  border-right: 1px solid #f4f5f7;
  vertical-align: top;
  font-size: 12.5px;
  color: #1f2430;
  white-space: nowrap;
}
.data-table tbody tr:hover:not(.subtable-row):not(.master-banner-row) {
  background: #fafbfc;
}
.data-table tr.selected td {
  background: #e8f1fc;
}
.sortable {
  cursor: pointer;
  user-select: none;
}
.mono {
  color: #0c4ea0;
  font-weight: 700;
}
.number {
  text-align: right;
}
.name-cell {
  white-space: normal !important;
  word-break: break-word !important;
  overflow-wrap: anywhere !important;
  min-width: 240px;
  max-width: 400px;
  line-height: 1.35;
}
.room-class-cell {
  white-space: normal !important;
  word-break: break-word !important;
  overflow-wrap: anywhere !important;
  min-width: 170px;
  max-width: 300px;
  line-height: 1.35;
}
.wrap-cell {
  white-space: normal !important;
  word-break: break-word !important;
  overflow-wrap: anywhere !important;
  min-width: 130px;
  max-width: 260px;
  line-height: 1.35;
}
.note-cell {
  white-space: normal !important;
  word-break: break-word !important;
  overflow-wrap: anywhere !important;
  min-width: 160px;
  max-width: 300px;
  color: #5b6270;
  line-height: 1.4;
}
.expand-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 18px;
  height: 18px;
  border: 0;
  background: #1f6fd6;
  color: #fff;
  border-radius: 3px;
  font-size: 14px;
  font-weight: bold;
  line-height: 1;
  cursor: pointer;
}
.status-pill {
  display: inline-block;
  background: #7cc242;
  color: #fff;
  font-weight: 600;
  border-radius: 4px;
  padding: 2px 8px;
  font-size: 11px;
}
.status-guaranteed {
  background: #22c55e;
}
.status-0 {
  background: #3b82f6;
}
.status-1 {
  background: #10b981;
}
.status-2 {
  background: #64748b;
}
.status-3, .status-4 {
  background: #ef4444;
}

/* ------------------------------------------------------------- */
/* COMPACT SUB-TABLE (Image 3) */
/* ------------------------------------------------------------- */
.subtable-row td {
  background: #f8fafc;
  padding: 8px 12px;
}
.subtable-td {
  padding: 6px 0 !important;
}
.subtable-container {
  display: inline-block;
  margin: 4px 0 6px 40px;
  background: #fff;
  border: 1px solid #d1d5db;
  border-radius: 4px;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.04);
  overflow: hidden;
}
.compact-subtable {
  border-collapse: collapse;
  min-width: 720px;
  width: auto;
  font-size: 12px;
}
.compact-subtable thead th {
  position: static;
  background: #f1f5f9;
  color: #1e293b;
  font-size: 11.5px;
  font-weight: 700;
  padding: 7px 10px;
  border-bottom: 1px solid #cbd5e1;
  border-right: 1px solid #e2e8f0;
  white-space: nowrap;
}
.compact-subtable tbody td {
  padding: 7px 10px;
  font-size: 12px;
  color: #1e293b;
  border-bottom: 1px solid #e2e8f0;
  border-right: 1px solid #f1f5f9;
  white-space: nowrap;
}
.compact-subtable tbody tr:hover {
  background: #f8fafc;
}
.subtable-total-row td {
  background: #f8fafc;
  border-top: 1px solid #cbd5e1;
  font-weight: 700;
  color: #0f172a;
}
.text-left {
  text-align: left;
}
.text-center {
  text-align: center;
}

/* MASTER BANNER IN ROOM TAB */
.master-banner-row td {
  background: #edf5fc;
  border-bottom: 1px solid #cbd5e1;
}
.master-banner-left {
  line-height: 1.5;
}
.banner-title {
  color: #0c4ea0;
}
.banner-dates, .banner-night {
  color: #475569;
  margin-left: 4px;
}
.banner-note {
  color: #64748b;
  font-size: 11.5px;
}
.master-banner-right {
  text-align: right;
  line-height: 1.5;
  color: #0c4ea0;
}
.banner-money strong {
  font-weight: 700;
}

/* PAGER */
.pager {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 4px 0;
  color: #8b909c;
  font-size: 12.5px;
  flex-shrink: 0;
}
.pager-center {
  display: flex;
  align-items: center;
  gap: 4px;
}
.pg {
  width: 26px;
  height: 26px;
  border-radius: 4px;
  border: 1px solid #c9ced8;
  background: #fff;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  font-size: 12px;
  color: #1f2430;
}
.pg:hover:not(:disabled) {
  background: #f7f8fa;
}
.pg.active {
  background: #1f6fd6;
  border-color: #1f6fd6;
  color: #fff;
  font-weight: 700;
}
.pg:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}
.pg-more {
  padding: 0 4px;
}
.per-page-select {
  height: 28px;
  border: 1px solid #c9ced8;
  border-radius: 4px;
  background: #fff;
  font-size: 12px;
  padding: 0 6px;
  color: #1f2430;
}
.empty-row td {
  height: 140px;
  text-align: center;
  vertical-align: middle;
  color: #8b909c;
  font-size: 13px;
}

/* ==================== ACTIONS DROPDOWN & SUBMENUS (Images 1, 2, 3, 4) ==================== */
.actions-dropdown-wrap {
  position: relative;
  display: inline-block;
}

.action-btn-trigger {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.dropdown-caret {
  font-size: 9px;
  opacity: 0.85;
}

.actions-menu-popover {
  position: absolute;
  top: calc(100% + 4px);
  right: 0;
  min-width: 190px;
  background: #ffffff;
  border-radius: 6px;
  box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
  padding: 5px 0;
  z-index: 1000;
  border: 1px solid #cbd5e1;
}

.action-menu-item {
  display: flex;
  align-items: center;
  width: 100%;
  text-align: left;
  padding: 8px 14px;
  font-size: 13px;
  font-weight: 500;
  color: #1e293b;
  background: transparent;
  border: none;
  cursor: pointer;
  transition: all 0.15s ease;
  gap: 10px;
  user-select: none;
}

.action-menu-item:hover:not(:disabled):not(.is-disabled) {
  background: #f1f5f9;
  color: #1d4ed8;
}

.action-menu-item:hover:not(:disabled):not(.is-disabled) .item-icon {
  color: #1d4ed8;
}

.action-menu-item:disabled,
.action-menu-item.is-disabled {
  opacity: 0.4;
  cursor: not-allowed;
  color: #94a3b8 !important;
  background: transparent !important;
  pointer-events: none;
}

.action-menu-item:disabled .item-icon,
.action-menu-item.is-disabled .item-icon {
  color: #94a3b8 !important;
}

.action-menu-item .item-icon {
  width: 16px;
  text-align: center;
  font-size: 13px;
  color: #2563eb;
}

.has-submenu {
  position: relative;
}

.submenu-row-title {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
}

.submenu-arrow {
  margin-left: auto;
  font-size: 9px;
  color: #64748b;
}

.action-submenu-flyout {
  position: absolute;
  top: 0;
  right: 100%;
  left: auto;
  margin-right: 4px;
  min-width: 175px;
  background: #ffffff;
  border-radius: 6px;
  box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
  padding: 5px 0;
  border: 1px solid #cbd5e1;
  z-index: 1001;
}

.action-submenu-item {
  display: block;
  width: 100%;
  text-align: left;
  padding: 8px 14px;
  font-size: 13px;
  font-weight: 500;
  color: #1e293b;
  background: transparent;
  border: none;
  cursor: pointer;
  transition: background 0.15s ease, color 0.15s ease;
  user-select: none;
}

.action-submenu-item:hover {
  background: #f1f5f9;
  color: #1d4ed8;
}

/* ==================== NO SHOW CONFIRMATION MODAL (Image 2) ==================== */
.noshow-modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(15, 23, 42, 0.45);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 2500;
  backdrop-filter: blur(1px);
}

.noshow-modal-box {
  background: #ffffff;
  width: 440px;
  max-width: 92vw;
  border-radius: 6px;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2), 0 10px 10px -5px rgba(0, 0, 0, 0.1);
  overflow: hidden;
  border: 1px solid #cbd5e1;
  animation: noshowPop 0.15s ease-out;
}

@keyframes noshowPop {
  from {
    transform: scale(0.96);
    opacity: 0;
  }
  to {
    transform: scale(1);
    opacity: 1;
  }
}

.noshow-modal-header {
  background: #2563eb;
  color: #ffffff;
  padding: 10px 16px;
  font-weight: 700;
  font-size: 15px;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.noshow-modal-title {
  font-weight: 700;
  font-size: 15px;
}

.noshow-modal-close {
  background: transparent;
  border: none;
  color: #ffffff;
  font-size: 16px;
  line-height: 1;
  font-weight: 700;
  cursor: pointer;
  padding: 2px 6px;
  opacity: 0.85;
  transition: opacity 0.15s;
}

.noshow-modal-close:hover {
  opacity: 1;
}

.noshow-modal-body {
  padding: 20px 18px 14px;
}

.noshow-options-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 12px;
}

.noshow-option-card {
  background: #ffffff;
  border: 1px solid #cbd5e1;
  border-radius: 4px;
  padding: 18px 14px;
  font-size: 14px;
  font-weight: 700;
  color: #0f172a;
  cursor: pointer;
  text-align: left;
  line-height: 1.35;
  transition: all 0.15s ease;
  display: flex;
  align-items: center;
}

.noshow-option-card:hover {
  border-color: #93c5fd;
  background: #f8fafc;
}

.noshow-option-card.active {
  border: 1.5px solid #2563eb;
  color: #2563eb;
  background: #eff6ff;
}

.noshow-modal-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 10px 18px 18px;
}

.noshow-btn-action {
  background: #2563eb;
  color: #ffffff;
  border: none;
  border-radius: 4px;
  padding: 7px 22px;
  font-size: 13px;
  font-weight: 700;
  cursor: pointer;
  transition: background 0.15s ease;
}

.noshow-btn-action:hover {
  background: #1d4ed8;
}

.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
}
</style>
