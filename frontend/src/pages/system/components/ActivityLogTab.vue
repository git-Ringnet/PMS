<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { fetchActivityLogs, fetchActivityLogStats } from '@/services/activity-log-service'
import { useUiStore } from '@/stores/ui-store'
import { useAuthStore } from '@/stores/auth-store'

const uiStore = useUiStore()
const authStore = useAuthStore()

// Theme Background from Topbar settings
const themeBg = computed(() => authStore.settings?.topbar_color || '#006bdb')

// State data
const logs = ref([])
const loading = ref(false)

// Dropdowns lists (retrieved from stats)
const usersList = ref([])
const componentsList = ref([])

// Pagination
const currentPage = ref(1)
const perPage = ref(30)
const totalItems = ref(0)
const lastPage = ref(1)

const getTodayString = (offsetDays = 0) => {
  const d = new Date()
  if (offsetDays !== 0) {
    d.setDate(d.getDate() + offsetDays)
  }
  const formatter = new Intl.DateTimeFormat('en-US', {
    timeZone: 'Asia/Ho_Chi_Minh',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit'
  })
  const parts = formatter.formatToParts(d)
  const month = parts.find(p => p.type === 'month').value
  const day = parts.find(p => p.type === 'day').value
  const year = parts.find(p => p.type === 'year').value
  return `${year}-${month}-${day}`
}

const getMonthStartString = () => {
  const d = new Date()
  const year = d.getFullYear()
  const month = String(d.getMonth() + 1).padStart(2, '0')
  return `${year}-${month}-01`
}

const todayStr = getTodayString()

// Quick filter chips
const quickDateFilter = ref('today')
const quickFilters = [
  { label: 'Hôm nay', value: 'today', icon: '📅' },
  { label: 'Hôm qua', value: 'yesterday', icon: '⏮️' },
  { label: '7 ngày qua', value: 'last7days', icon: '📆' },
  { label: 'Tháng này', value: 'this_month', icon: '📊' },
  { label: 'Tất cả', value: 'all', icon: '📋' },
]

// Filters input states
const filterDateFrom = ref(todayStr)
const filterDateTo = ref(todayStr)
const filterRegCode = ref('')
const filterRoomCode = ref('')
const filterUserId = ref('')
const filterAction = ref('')
const filterComponent = ref('')
const filterSearch = ref('')

// Active filter states used for queries
const queryParams = ref({
  date_from: todayStr,
  date_to: todayStr,
  registration_code: '',
  room_code: '',
  user_id: '',
  action: '',
  component: '',
  search: '',
  sort_by: 'created_at',
  sort_dir: 'desc',
  page: 1,
  per_page: 30
})

// Modal Detail state
const isModalOpen = ref(false)
const selectedLog = ref(null)

// Debounce timer
let searchTimeout = null

onMounted(() => {
  loadStats()
  handleSearch()
})

// Set quick date preset
const setQuickDate = (val) => {
  quickDateFilter.value = val
  if (val === 'today') {
    filterDateFrom.value = getTodayString()
    filterDateTo.value = getTodayString()
  } else if (val === 'yesterday') {
    filterDateFrom.value = getTodayString(-1)
    filterDateTo.value = getTodayString(-1)
  } else if (val === 'last7days') {
    filterDateFrom.value = getTodayString(-7)
    filterDateTo.value = getTodayString()
  } else if (val === 'this_month') {
    filterDateFrom.value = getMonthStartString()
    filterDateTo.value = getTodayString()
  } else if (val === 'all') {
    filterDateFrom.value = ''
    filterDateTo.value = ''
  }
  handleSearch()
}

// Load metadata lists for filters
const loadStats = async () => {
  try {
    const res = await fetchActivityLogStats()
    if (res.data && res.data.success) {
      usersList.value = res.data.data.users_list || []
      componentsList.value = res.data.data.components_list || []
    }
  } catch (err) {
    console.error('Không thể tải thống kê logs', err)
  }
}

// Fetch logs from API
const loadLogs = async () => {
  loading.value = true
  try {
    const res = await fetchActivityLogs(queryParams.value)
    if (res.data && res.data.success) {
      logs.value = res.data.data || []
      const meta = res.data.meta || {}
      currentPage.value = meta.current_page || 1
      lastPage.value = meta.last_page || 1
      totalItems.value = meta.total || 0
    }
  } catch (err) {
    console.error(err)
    uiStore.showToast('Không thể tải lịch sử thao tác', 'error')
  } finally {
    loading.value = false
  }
}

// Handle apply filters
const handleSearch = () => {
  queryParams.value.date_from = filterDateFrom.value
  queryParams.value.date_to = filterDateTo.value
  queryParams.value.registration_code = filterRegCode.value
  queryParams.value.room_code = filterRoomCode.value
  queryParams.value.user_id = filterUserId.value
  queryParams.value.action = filterAction.value
  queryParams.value.component = filterComponent.value
  queryParams.value.search = filterSearch.value
  queryParams.value.page = 1
  
  loadLogs()
}

const onDebouncedSearch = () => {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => {
    handleSearch()
  }, 400)
}

// Clear all filter values
const handleResetFilters = () => {
  quickDateFilter.value = 'today'
  const todayStr = getTodayString()
  filterDateFrom.value = todayStr
  filterDateTo.value = todayStr
  filterRegCode.value = ''
  filterRoomCode.value = ''
  filterUserId.value = ''
  filterAction.value = ''
  filterComponent.value = ''
  filterSearch.value = ''
  
  handleSearch()
}

// Sorting logic
const toggleSort = (field) => {
  if (queryParams.value.sort_by === field) {
    queryParams.value.sort_dir = queryParams.value.sort_dir === 'asc' ? 'desc' : 'asc'
  } else {
    queryParams.value.sort_by = field
    queryParams.value.sort_dir = 'desc'
  }
  queryParams.value.page = 1
  loadLogs()
}

// Pagination logic
const changePage = (page) => {
  if (page < 1 || page > lastPage.value) return
  queryParams.value.page = page
  loadLogs()
}

// Action label translation
const getActionLabel = (action) => {
  const map = {
    'New': 'Tạo mới',
    'Modify': 'Cập nhật',
    'CheckIn': 'Nhận phòng',
    'CheckOut': 'Trả phòng',
    'Cancel': 'Hủy',
    'NoShow': 'Không đến',
    'Lock': 'Khóa phòng',
    'Unlock': 'Mở khóa',
    'Payment': 'Thanh toán',
    'Refund': 'Hoàn tiền',
    'AddService': 'Thêm dịch vụ',
    'DeleteService': 'Xóa dịch vụ',
    'DayClose': 'Sang ngày',
    'Inventory': 'Kho bãi',
    'login': 'Đăng nhập',
    'logout': 'Đăng xuất',
    'login_failed': 'Đăng nhập lỗi',
    'create': 'Thêm mới',
    'update': 'Cập nhật',
    'delete': 'Xóa',
    'upload': 'Tải lên',
    'bulk_action': 'Thao tác loạt'
  }
  return map[action] || action
}

// Action label styling
const getActionClass = (action) => {
  const base = 'px-2 py-0.5 rounded-full text-[10px] font-bold inline-block text-center border whitespace-nowrap '
  const map = {
    'New': 'bg-emerald-50 text-emerald-700 border-emerald-300',
    'Modify': 'bg-blue-50 text-blue-700 border-blue-300',
    'CheckIn': 'bg-indigo-50 text-indigo-700 border-indigo-300',
    'CheckOut': 'bg-purple-50 text-purple-700 border-purple-300',
    'Cancel': 'bg-rose-50 text-rose-700 border-rose-300',
    'NoShow': 'bg-orange-50 text-orange-700 border-orange-300',
    'Lock': 'bg-amber-50 text-amber-800 border-amber-300',
    'Unlock': 'bg-teal-50 text-teal-700 border-teal-300',
    'Payment': 'bg-emerald-50 text-emerald-800 border-emerald-300',
    'Refund': 'bg-pink-50 text-pink-700 border-pink-300',
    'AddService': 'bg-cyan-50 text-cyan-700 border-cyan-300',
    'DeleteService': 'bg-rose-50 text-rose-700 border-rose-300',
    'DayClose': 'bg-violet-50 text-violet-700 border-violet-300',
    'Inventory': 'bg-amber-50 text-amber-700 border-amber-300',
    'login': 'bg-green-50 text-green-700 border-green-300',
    'logout': 'bg-slate-50 text-slate-700 border-slate-300',
    'login_failed': 'bg-red-50 text-red-700 border-red-300',
    'create': 'bg-emerald-50 text-emerald-700 border-emerald-300',
    'update': 'bg-blue-50 text-blue-700 border-blue-300',
    'delete': 'bg-rose-50 text-rose-700 border-rose-300',
  }
  return base + (map[action] || 'bg-slate-50 text-slate-600 border-slate-300')
}

// Date formatter (Formats explicitly to Asia/Ho_Chi_Minh timezone)
const formatDateTime = (dateStr) => {
  if (!dateStr) return '-'
  try {
    const d = new Date(dateStr)
    if (isNaN(d.getTime())) return dateStr
    
    const formatter = new Intl.DateTimeFormat('en-US', {
      timeZone: 'Asia/Ho_Chi_Minh',
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
      hour: 'numeric',
      minute: '2-digit',
      second: '2-digit',
      hour12: false
    })
    
    const parts = formatter.formatToParts(d)
    const month = parts.find(p => p.type === 'month').value
    const day = parts.find(p => p.type === 'day').value
    const year = parts.find(p => p.type === 'year').value
    let hour = parts.find(p => p.type === 'hour').value
    const minute = parts.find(p => p.type === 'minute').value
    const second = parts.find(p => p.type === 'second').value
    
    if (hour.length === 1) hour = '0' + hour
    
    return `${day}/${month}/${year} ${hour}:${minute}:${second}`
  } catch (e) {
    return dateStr
  }
}

// Format values nicely for the comparison diff table (handling array/object values)
const formatDiffValue = (val) => {
  if (val === null || val === undefined) return 'null'
  if (typeof val === 'object') {
    try {
      return JSON.stringify(val, null, 2)
    } catch (e) {
      return String(val)
    }
  }
  return String(val)
}

// Details modal opener
const viewDetails = (log) => {
  selectedLog.value = log
  isModalOpen.value = true
}

// Parse user agent to simpler device format
const parseUserAgent = (ua) => {
  if (!ua) return 'Không rõ'
  if (ua.includes('Mobi') || ua.includes('Android') || ua.includes('iPhone')) {
    return 'Di động'
  }
  if (ua.includes('Windows')) {
    return 'PC (Windows)'
  }
  if (ua.includes('Macintosh')) {
    return 'PC (macOS)'
  }
  if (ua.includes('Linux')) {
    return 'PC (Linux)'
  }
  return 'Desktop/Browser'
}

// Format rich description with styled tags and highlights
const formatDescriptionHtml = (text) => {
  if (!text) return '-'
  
  // Escape basic HTML
  let escaped = text
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')

  // Replace arrow -> with bold arrow
  escaped = escaped.replace(/-&gt;/g, '<span class="font-bold text-sky-600 mx-1">➜</span>')

  // Highlight bold prefix lines: * ... :
  escaped = escaped.replace(/^(\*\s*[^:]+:)/m, '<span class="font-bold text-slate-900">$1</span>')

  // Highlight field labels: -Tên:, -Ngày đến:, -Phòng:, -Giá phòng:, -Tổng tiền:, -Đặt cọc:, -Trạng thái:, -Ghi chú:, Lý do:, Mã khách:, Họ tên:
  const labelPatterns = [
    /-Tên:/g, /-Ngày đến:/g, /-Ngày đi:/g, /-Phòng:/g, /-Loại phòng:/g,
    /-Giá phòng:/g, /-Giá:/g, /-Tổng tiền:/g, /-Đặt cọc:/g, /-Trạng thái:/g,
    /-Ghi chú:/g, /-Công ty:/g, /-Nguồn:/g, /Lý do:/g, /Mã khách:/g, /Họ tên:/g,
    /Check in cho đăng ký/g, /Check out cho đăng ký/g, /Chuyển phòng:/g, /Đổi trạng thái:/g,
    /Nâng hạng phòng:/g, /Khóa phòng/g, /Mở khóa phòng/g
  ]

  labelPatterns.forEach(pattern => {
    escaped = escaped.replace(pattern, (match) => `<span class="font-bold text-slate-800">${match}</span>`)
  })

  // Format line breaks
  escaped = escaped.replace(/\.Cập nhật/g, '.<br/>Cập nhật')
  escaped = escaped.replace(/,\s*-/g, '<br/>-')

  return escaped
}

// CSV Export logic
const handleExport = async () => {
  try {
    uiStore.showToast('Bắt đầu xuất dữ liệu...', 'info')
    const exportParams = {
      ...queryParams.value,
      per_page: 1000,
      page: 1
    }
    const res = await fetchActivityLogs(exportParams)
    if (res.data && res.data.success && res.data.data.length > 0) {
      const data = res.data.data
      
      let csvContent = '\uFEFF' // BOM for Excel encoding UTF-8
      const headers = ['ID', 'Thời gian', 'Người dùng', 'Mã NV', 'Địa chỉ IP', 'Thiết bị', 'Phân hệ / Màn hình', 'Hành động', 'Mã đăng ký', 'Mã phòng', 'Mô tả chi tiết']
      csvContent += headers.join(',') + '\n'
      
      data.forEach(log => {
        const row = [
          log.id,
          `"${formatDateTime(log.created_at)}"`,
          `"${log.user_name || 'Hệ thống'}"`,
          `"${log.employee_code || 'N/A'}"`,
          `"${log.ip_address || 'N/A'}"`,
          `"${parseUserAgent(log.user_agent)}"`,
          `"${log.component || log.module || 'N/A'}"`,
          `"${getActionLabel(log.action)}"`,
          `"${log.target_label || 'N/A'}"`,
          `"${log.target_id || 'N/A'}"`,
          `"${(log.description || '').replace(/"/g, '""')}"`
        ]
        csvContent += row.join(',') + '\n'
      })
      
      const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' })
      const link = document.createElement('a')
      const url = URL.createObjectURL(blob)
      link.setAttribute('href', url)
      link.setAttribute('download', `Lich_su_thao_tac_${getTodayString()}.csv`)
      link.style.visibility = 'hidden'
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)
      uiStore.showToast('Xuất file thành công!', 'success')
    } else {
      uiStore.showToast('Không có dữ liệu phù hợp để xuất', 'warning')
    }
  } catch (err) {
    console.error('Lỗi khi xuất CSV:', err)
    uiStore.showToast('Không thể xuất lịch sử thao tác', 'error')
  }
}
</script>

<template>
  <div class="p-4 bg-slate-50 flex-1 flex flex-col overflow-hidden text-xs font-sans min-h-[500px]">
    
    <!-- Quick Filter Chips Bar -->
    <div class="flex items-center gap-2 mb-3 flex-wrap shrink-0">
      <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider mr-1">Lọc nhanh thời gian:</span>
      <button 
        v-for="chip in quickFilters" 
        :key="chip.value"
        @click="setQuickDate(chip.value)"
        class="px-3 py-1 rounded-full text-xs font-semibold transition-all duration-200 flex items-center gap-1.5 border cursor-pointer select-none"
        :class="quickDateFilter === chip.value 
          ? 'bg-sky-100 text-sky-800 border-sky-400 shadow-xs font-bold scale-[1.02]' 
          : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-100'"
      >
        <span>{{ chip.icon }}</span>
        <span>{{ chip.label }}</span>
      </button>
    </div>

    <!-- Filters Layout -->
    <div class="bg-white border border-slate-200 rounded-xl p-3.5 mb-3.5 shadow-2xs shrink-0">
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-2.5">
        <!-- Date From -->
        <div class="flex flex-col gap-1">
          <label class="font-bold text-slate-600 text-[11px]">Từ ngày</label>
          <input 
            v-model="filterDateFrom" 
            type="date" 
            @change="handleSearch"
            class="border border-slate-300 rounded-lg px-2 py-1 h-[30px] focus:outline-none focus:ring-1 focus:ring-sky-400 font-semibold text-slate-700 bg-white"
          />
        </div>

        <!-- Date To -->
        <div class="flex flex-col gap-1">
          <label class="font-bold text-slate-600 text-[11px]">Đến ngày</label>
          <input 
            v-model="filterDateTo" 
            type="date" 
            @change="handleSearch"
            class="border border-slate-300 rounded-lg px-2 py-1 h-[30px] focus:outline-none focus:ring-1 focus:ring-sky-400 font-semibold text-slate-700 bg-white"
          />
        </div>

        <!-- Registration Code -->
        <div class="flex flex-col gap-1">
          <label class="font-bold text-slate-600 text-[11px]">Mã đăng ký</label>
          <input 
            v-model="filterRegCode" 
            type="text" 
            placeholder="Mã đăng ký..."
            @input="onDebouncedSearch"
            class="border border-slate-300 rounded-lg px-2 py-1 h-[30px] focus:outline-none focus:ring-1 focus:ring-sky-400 font-semibold text-slate-700 bg-white"
            @keyup.enter="handleSearch"
          />
        </div>

        <!-- Room Code -->
        <div class="flex flex-col gap-1">
          <label class="font-bold text-slate-600 text-[11px]">Mã phòng</label>
          <input 
            v-model="filterRoomCode" 
            type="text" 
            placeholder="Mã phòng/Số phòng..."
            @input="onDebouncedSearch"
            class="border border-slate-300 rounded-lg px-2 py-1 h-[30px] focus:outline-none focus:ring-1 focus:ring-sky-400 font-semibold text-slate-700 bg-white"
            @keyup.enter="handleSearch"
          />
        </div>

        <!-- Action Selection Dropdown -->
        <div class="flex flex-col gap-1">
          <label class="font-bold text-slate-600 text-[11px]">Hành động</label>
          <select 
            v-model="filterAction"
            @change="handleSearch"
            class="border border-slate-300 rounded-lg px-2 py-1 h-[30px] focus:outline-none focus:ring-1 focus:ring-sky-400 font-semibold text-slate-700 bg-white cursor-pointer"
          >
            <option value="">-- Tất cả hành động --</option>
            <option value="New">Tạo mới (New)</option>
            <option value="Modify">Cập nhật (Modify)</option>
            <option value="CheckIn">Nhận phòng (CheckIn)</option>
            <option value="CheckOut">Trả phòng (CheckOut)</option>
            <option value="Cancel">Hủy đăng ký (Cancel)</option>
            <option value="NoShow">Không đến (NoShow)</option>
            <option value="Lock">Khóa phòng (Lock)</option>
            <option value="Unlock">Mở khóa (Unlock)</option>
            <option value="Payment">Thanh toán (Payment)</option>
            <option value="Refund">Hoàn tiền (Refund)</option>
            <option value="AddService">Thêm dịch vụ</option>
            <option value="DeleteService">Xóa dịch vụ</option>
            <option value="DayClose">Sang ngày (DayClose)</option>
            <option value="Inventory">Kho bãi (Inventory)</option>
            <option value="login">Đăng nhập (Login)</option>
            <option value="logout">Đăng xuất (Logout)</option>
          </select>
        </div>

        <!-- User Selection Dropdown -->
        <div class="flex flex-col gap-1">
          <label class="font-bold text-slate-600 text-[11px]">Người dùng</label>
          <select 
            v-model="filterUserId"
            @change="handleSearch"
            class="border border-slate-300 rounded-lg px-2 py-1 h-[30px] focus:outline-none focus:ring-1 focus:ring-sky-400 font-semibold text-slate-700 bg-white cursor-pointer"
          >
            <option value="">-- Tất cả --</option>
            <option v-for="u in usersList" :key="u.user_id" :value="u.user_id">
              {{ u.user_name || 'User' }} ({{ u.employee_code || 'N/A' }})
            </option>
          </select>
        </div>

        <!-- Screen (Component) Selection Dropdown -->
        <div class="flex flex-col gap-1">
          <label class="font-bold text-slate-600 text-[11px]">Phân hệ / Màn hình</label>
          <select 
            v-model="filterComponent"
            @change="handleSearch"
            class="border border-slate-300 rounded-lg px-2 py-1 h-[30px] focus:outline-none focus:ring-1 focus:ring-sky-400 font-semibold text-slate-700 bg-white cursor-pointer"
          >
            <option value="">-- Tất cả --</option>
            <option v-for="c in componentsList" :key="c" :value="c">
              {{ c }}
            </option>
          </select>
        </div>

        <!-- Global Search -->
        <div class="flex flex-col gap-1">
          <label class="font-bold text-slate-600 text-[11px]">Tìm kiếm chung</label>
          <input 
            v-model="filterSearch" 
            type="text" 
            placeholder="Nội dung bất kỳ..."
            @input="onDebouncedSearch"
            class="border border-slate-300 rounded-lg px-2 py-1 h-[30px] focus:outline-none focus:ring-1 focus:ring-sky-400 font-semibold text-slate-700 bg-white"
            @keyup.enter="handleSearch"
          />
        </div>
      </div>

      <!-- Action buttons -->
      <div class="flex items-center justify-between gap-2 mt-3 pt-2.5 border-t border-slate-100">
        <div class="text-[11px] text-slate-500 font-bold flex items-center gap-1.5">
          <span>📊 Tổng cộng: <strong class="text-sky-600">{{ totalItems }}</strong> thao tác</span>
        </div>
        <div class="flex items-center gap-2">
          <button 
            @click="handleResetFilters"
            class="px-3 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg font-bold text-xs border border-slate-200 cursor-pointer flex items-center gap-1 transition-colors h-[30px]"
          >
            🔄 Hủy Lọc
          </button>
          <button 
            @click="handleSearch"
            :style="{ background: themeBg }"
            class="px-4 py-1 text-white rounded-lg font-bold text-xs border-none cursor-pointer flex items-center gap-1.5 transition-all shadow-xs h-[30px] hover:brightness-110"
          >
            🔍 Tìm Kiếm
          </button>
          <button 
            @click="handleExport"
            class="px-3.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-bold text-xs border-none cursor-pointer flex items-center gap-1.5 transition-all shadow-xs h-[30px]"
          >
            📥 Xuất Excel
          </button>
        </div>
      </div>
    </div>

    <!-- Data Table Container -->
    <div class="overflow-auto border border-slate-200 rounded-xl shadow-xs flex-1 max-h-full bg-white relative">
      <!-- Loading Overlay -->
      <div v-if="loading" class="absolute inset-0 bg-white/70 flex items-center justify-center z-10">
        <div class="flex flex-col items-center gap-2 bg-white p-4 rounded-xl shadow-lg border border-slate-100">
          <div class="w-8 h-8 rounded-full border-3 border-slate-200 border-t-sky-500 animate-spin"></div>
          <span class="font-bold text-sky-700 text-xs">Đang tải lịch sử thao tác...</span>
        </div>
      </div>

      <table class="w-full text-left border-collapse text-xs">
        <thead>
          <tr class="bg-slate-100 border-b border-slate-200 text-slate-700 font-bold select-none h-10 sticky top-0 z-20 shadow-2xs">
            <th @click="toggleSort('id')" class="p-2 border-r border-slate-200 w-14 cursor-pointer hover:bg-slate-200 text-center uppercase whitespace-nowrap">
              ID <span v-if="queryParams.sort_by === 'id'" class="text-[9px] text-sky-600">{{ queryParams.sort_dir === 'asc' ? '▲' : '▼' }}</span>
            </th>
            <th @click="toggleSort('created_at')" class="p-2 border-r border-slate-200 w-36 cursor-pointer hover:bg-slate-200 uppercase whitespace-nowrap">
              Thời gian <span v-if="queryParams.sort_by === 'created_at'" class="text-[9px] text-sky-600">{{ queryParams.sort_dir === 'asc' ? '▲' : '▼' }}</span>
            </th>
            <th class="p-2 border-r border-slate-200 w-32 uppercase whitespace-nowrap">Người dùng</th>
            <th class="p-2 border-r border-slate-200 w-28 uppercase whitespace-nowrap">Địa chỉ IP</th>
            <th class="p-2 border-r border-slate-200 w-28 uppercase whitespace-nowrap">Thiết bị</th>
            <th class="p-2 border-r border-slate-200 w-36 uppercase whitespace-nowrap">Phân hệ / Màn hình</th>
            <th @click="toggleSort('action')" class="p-2 border-r border-slate-200 w-28 cursor-pointer hover:bg-slate-200 uppercase text-center whitespace-nowrap">
              Hành động <span v-if="queryParams.sort_by === 'action'" class="text-[9px] text-sky-600">{{ queryParams.sort_dir === 'asc' ? '▲' : '▼' }}</span>
            </th>
            <th class="p-2 border-r border-slate-200 w-28 uppercase whitespace-nowrap">Mã đăng ký</th>
            <th class="p-2 border-r border-slate-200 w-24 uppercase whitespace-nowrap">Mã phòng</th>
            <th class="p-2 border-r border-slate-200 uppercase min-w-[340px]">Mô tả chi tiết</th>
            <th class="p-2 text-center w-20 uppercase whitespace-nowrap">Chi tiết</th>
          </tr>
        </thead>
        <tbody>
          <tr 
            v-for="log in logs" 
            :key="log.id" 
            class="border-b border-slate-200 hover:bg-sky-50/50 transition-colors font-medium text-slate-700"
          >
            <td class="p-2.5 border-r border-slate-200 text-center text-slate-500 font-mono">{{ log.id }}</td>
            <td class="p-2.5 border-r border-slate-200 text-slate-600 font-medium whitespace-nowrap">{{ formatDateTime(log.created_at) }}</td>
            <td class="p-2.5 border-r border-slate-200 text-slate-800">
              <div class="font-bold text-slate-900">{{ log.user_name || 'Hệ thống' }}</div>
              <div class="text-[10px] text-slate-400 font-normal">Mã NV: {{ log.employee_code || 'N/A' }}</div>
            </td>
            <td class="p-2.5 border-r border-slate-200 text-slate-500 font-mono text-[11px] whitespace-nowrap">{{ log.ip_address || '-' }}</td>
            <td class="p-2.5 border-r border-slate-200 text-slate-500 font-normal whitespace-nowrap" :title="log.user_agent">
              {{ parseUserAgent(log.user_agent) }}
            </td>
            <td class="p-2.5 border-r border-slate-200 text-slate-700">
              <span class="font-bold text-slate-800">{{ log.component || '-' }}</span>
              <div class="text-[10px] text-slate-400 font-normal uppercase tracking-wider">{{ log.module || 'other' }}</div>
            </td>
            <td class="p-2.5 border-r border-slate-200 text-center whitespace-nowrap">
              <span :class="getActionClass(log.action)">{{ getActionLabel(log.action) }}</span>
            </td>
            <td class="p-2.5 border-r border-slate-200 font-mono font-bold text-indigo-700 whitespace-nowrap">
              {{ log.target_label || '-' }}
            </td>
            <td class="p-2.5 border-r border-slate-200 font-mono font-bold text-emerald-700 whitespace-nowrap">
              {{ log.target_id || '-' }}
            </td>
            <td class="p-2.5 border-r border-slate-200 text-slate-700 font-normal min-w-[340px] max-w-[550px] whitespace-pre-wrap leading-relaxed">
              <div v-html="formatDescriptionHtml(log.description)"></div>
            </td>
            <td class="p-2.5 text-center whitespace-nowrap">
              <button 
                v-if="log.old_values || log.new_values"
                @click="viewDetails(log)"
                :style="{ background: themeBg }"
                class="px-2.5 py-1 text-white rounded-md text-[10px] font-bold border-none cursor-pointer transition-all shadow-2xs hover:brightness-110"
              >
                Chi tiết
              </button>
              <span v-else class="text-slate-400 font-normal">-</span>
            </td>
          </tr>

          <tr v-if="logs.length === 0 && !loading">
            <td colspan="11" class="p-16 text-center text-slate-400 text-sm font-semibold">
              Không tìm thấy lịch sử thao tác nào phù hợp với bộ lọc.
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination Footer -->
    <div v-if="lastPage > 1" class="flex items-center justify-between mt-3 gap-2 select-none shrink-0 border-t border-slate-200 pt-3">
      <div class="text-xs text-slate-600 font-bold">
        Hiển thị {{ logs.length }} / {{ totalItems }} bản ghi (Trang {{ currentPage }} / {{ lastPage }})
      </div>
      <div class="flex items-center gap-1.5">
        <button 
          @click="changePage(currentPage - 1)" 
          :disabled="currentPage === 1"
          class="px-3 py-1 border border-slate-300 rounded-lg text-xs font-bold text-slate-600 bg-white hover:bg-slate-50 cursor-pointer disabled:opacity-40"
        >
          &lt; Trước
        </button>
        <button 
          v-for="p in lastPage" 
          :key="p"
          @click="changePage(p)"
          class="px-3 py-1 border rounded-lg text-xs font-bold cursor-pointer transition-all"
          :class="currentPage === p ? 'border-sky-500 text-white font-black shadow-xs' : 'border-slate-300 text-slate-600 bg-white hover:bg-slate-50'"
          :style="currentPage === p ? { background: themeBg } : {}"
        >
          {{ p }}
        </button>
        <button 
          @click="changePage(currentPage + 1)" 
          :disabled="currentPage === lastPage"
          class="px-3 py-1 border border-slate-300 rounded-lg text-xs font-bold text-slate-600 bg-white hover:bg-slate-50 cursor-pointer disabled:opacity-40"
        >
          Sau &gt;
        </button>
      </div>
    </div>

    <!-- Modals: View Changes JSON Diff -->
    <div 
      v-if="isModalOpen" 
      class="fixed inset-0 z-[1000] flex items-center justify-center bg-black/60 backdrop-blur-xs p-4"
    >
      <div class="bg-white rounded-2xl w-full max-w-4xl shadow-2xl overflow-hidden border border-slate-200 animate-in max-h-[90vh] flex flex-col">
        <!-- Modal Header -->
        <div :style="{ background: themeBg }" class="px-5 py-3.5 flex items-center justify-between text-white shrink-0 shadow-xs">
          <div class="flex items-center gap-2.5">
            <span class="px-2.5 py-0.5 bg-white/20 text-white rounded-md text-[11px] font-black uppercase">Log #{{ selectedLog?.id }}</span>
            <h2 class="text-sm font-bold tracking-wide">Chi Tiết Thay Đổi Dữ Liệu</h2>
          </div>
          <button @click="isModalOpen = false" class="text-white/80 hover:text-white bg-transparent border-none cursor-pointer text-xl font-bold leading-none">✕</button>
        </div>

        <!-- Modal Info Bar -->
        <div class="bg-slate-50 border-b border-slate-200 p-4 grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs font-medium text-slate-700 shrink-0">
          <div>
            <span class="text-slate-400 block text-[10px] uppercase font-bold">Thời gian</span>
            <strong>{{ formatDateTime(selectedLog?.created_at) }}</strong>
          </div>
          <div>
            <span class="text-slate-400 block text-[10px] uppercase font-bold">Người thực hiện</span>
            <strong>{{ selectedLog?.user_name }}</strong> ({{ selectedLog?.employee_code || 'N/A' }})
          </div>
          <div>
            <span class="text-slate-400 block text-[10px] uppercase font-bold">Hành động</span>
            <span :class="getActionClass(selectedLog?.action)">{{ getActionLabel(selectedLog?.action) }}</span>
          </div>
          <div>
            <span class="text-slate-400 block text-[10px] uppercase font-bold">Màn hình</span>
            <strong>{{ selectedLog?.component || selectedLog?.module }}</strong>
          </div>
        </div>

        <!-- Modal Body: Mô tả & Diff Values -->
        <div class="p-4 overflow-y-auto flex-1 flex flex-col gap-4 text-xs">
          <!-- Mô tả đầy đủ -->
          <div class="bg-sky-50/50 border border-sky-100 rounded-xl p-3.5">
            <span class="text-sky-800 font-bold block mb-1">Mô tả nghiệp vụ:</span>
            <div class="text-slate-800 whitespace-pre-wrap leading-relaxed font-medium" v-html="formatDescriptionHtml(selectedLog?.description)"></div>
          </div>

          <!-- So sánh dữ liệu Cũ vs Mới -->
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4 flex-1">
            <!-- Old values -->
            <div class="flex flex-col border border-rose-200 rounded-xl overflow-hidden">
              <div class="bg-rose-50 border-b border-rose-200 px-3.5 py-2 font-bold text-rose-800 flex items-center gap-1.5">
                <span>◀ Dữ liệu trước khi sửa (Old Values)</span>
              </div>
              <pre class="p-3 bg-slate-900 text-rose-300 font-mono text-[11px] overflow-auto flex-1 max-h-[300px] leading-relaxed rounded-b-xl">{{ formatDiffValue(selectedLog?.old_values) }}</pre>
            </div>

            <!-- New values -->
            <div class="flex flex-col border border-emerald-200 rounded-xl overflow-hidden">
              <div class="bg-emerald-50 border-b border-emerald-200 px-3.5 py-2 font-bold text-emerald-800 flex items-center gap-1.5">
                <span>▶ Dữ liệu sau khi sửa (New Values)</span>
              </div>
              <pre class="p-3 bg-slate-900 text-emerald-300 font-mono text-[11px] overflow-auto flex-1 max-h-[300px] leading-relaxed rounded-b-xl">{{ formatDiffValue(selectedLog?.new_values) }}</pre>
            </div>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="bg-slate-50 border-t border-slate-200 px-5 py-3 flex justify-end shrink-0">
          <button 
            @click="isModalOpen = false"
            class="px-5 py-1.5 bg-slate-600 hover:bg-slate-700 text-white rounded-lg font-bold text-xs border-none cursor-pointer transition-colors shadow-xs"
          >
            Đóng
          </button>
        </div>
      </div>
    </div>

  </div>
</template>

<style scoped>
pre {
  white-space: pre-wrap;
  word-break: break-word;
}
</style>
