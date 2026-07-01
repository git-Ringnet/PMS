<script setup>
import { ref, onMounted } from 'vue'
import { fetchActivityLogs, fetchActivityLogStats } from '@/services/activity-log-service'
import { useUiStore } from '@/stores/ui-store'

const uiStore = useUiStore()

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

const getTodayString = () => {
  const d = new Date()
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

const todayStr = getTodayString()

// Filters input states
const filterDateFrom = ref(todayStr) // Default to today in Asia/Ho_Chi_Minh
const filterDateTo = ref(todayStr)
const filterRegCode = ref('')
const filterRoomCode = ref('')
const filterUserId = ref('')
const filterComponent = ref('')
const filterSearch = ref('')

// Active filter states used for queries
const queryParams = ref({
  date_from: todayStr,
  date_to: todayStr,
  registration_code: '',
  room_code: '',
  user_id: '',
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

onMounted(() => {
  loadStats()
  handleSearch()
})

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
  queryParams.value.component = filterComponent.value
  queryParams.value.search = filterSearch.value
  queryParams.value.page = 1
  
  loadLogs()
}

// Clear all filter values
const handleResetFilters = () => {
  const todayStr = getTodayString()
  filterDateFrom.value = todayStr
  filterDateTo.value = todayStr
  filterRegCode.value = ''
  filterRoomCode.value = ''
  filterUserId.value = ''
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
  const base = 'px-2 py-0.5 rounded-full text-[10px] font-bold inline-block text-center border '
  const map = {
    'login': 'bg-green-50 text-green-700 border-green-200',
    'logout': 'bg-slate-50 text-slate-700 border-slate-200',
    'login_failed': 'bg-red-50 text-red-700 border-red-200',
    'create': 'bg-sky-50 text-sky-700 border-sky-200',
    'update': 'bg-amber-50 text-amber-700 border-amber-200',
    'delete': 'bg-rose-50 text-rose-700 border-rose-200',
  }
  return base + (map[action] || 'bg-slate-50 text-slate-600 border-slate-200')
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

// CSV Export logic
const handleExport = async () => {
  try {
    uiStore.showToast('Bắt đầu xuất dữ liệu...', 'info')
    // Fetch with high limit to get all filtered logs
    const exportParams = {
      ...queryParams.value,
      per_page: 1000,
      page: 1
    }
    const res = await fetchActivityLogs(exportParams)
    if (res.data && res.data.success && res.data.data.length > 0) {
      const data = res.data.data
      
      // Build CSV BOM + content
      let csvContent = '\uFEFF' // BOM for Excel encoding UTF-8
      const headers = ['ID', 'Thời gian', 'Người dùng', 'Địa chỉ IP', 'Màn hình', 'Hành động', 'Mã đăng ký', 'Mã phòng', 'Mô tả chi tiết']
      csvContent += headers.join(',') + '\n'
      
      data.forEach(log => {
        const row = [
          log.id,
          `"${formatDateTime(log.created_at)}"`,
          `"${log.user_name || 'Hệ thống'} (${log.employee_code || 'N/A'})"`,
          `"${log.ip_address || 'N/A'}"`,
          `"${log.component || log.module || 'N/A'}"`,
          `"${getActionLabel(log.action)}"`,
          `"${log.target_label || 'N/A'}"`,
          `"${log.target_id || 'N/A'}"`,
          `"${(log.description || '').replace(/"/g, '""')}"` // Escape double quotes
        ]
        csvContent += row.join(',') + '\n'
      })
      
      // Trigger browser download
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
  <div class="p-3 bg-white flex-1 flex flex-col overflow-hidden text-xs">
    <!-- Filters Layout (Matches legacy PMS filters) -->
    <div class="bg-slate-50 border border-slate-200 rounded-lg p-3 mb-3 shrink-0">
      <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-2.5">
        <!-- Date From -->
        <div class="flex flex-col gap-1">
          <label class="font-bold text-slate-600">Từ ngày</label>
          <input 
            v-model="filterDateFrom" 
            type="date" 
            class="border border-slate-200 rounded-md p-1 h-[28px] focus:outline-sky-500 font-semibold text-slate-700 bg-white"
          />
        </div>

        <!-- Date To -->
        <div class="flex flex-col gap-1">
          <label class="font-bold text-slate-600">Đến ngày</label>
          <input 
            v-model="filterDateTo" 
            type="date" 
            class="border border-slate-200 rounded-md p-1 h-[28px] focus:outline-sky-500 font-semibold text-slate-700 bg-white"
          />
        </div>

        <!-- Registration Code -->
        <div class="flex flex-col gap-1">
          <label class="font-bold text-slate-600">Mã đăng ký</label>
          <input 
            v-model="filterRegCode" 
            type="text" 
            placeholder="Mã đăng ký..."
            class="border border-slate-200 rounded-md p-1 h-[28px] focus:outline-sky-500 font-semibold text-slate-700 bg-white"
            @keyup.enter="handleSearch"
          />
        </div>

        <!-- Room Code -->
        <div class="flex flex-col gap-1">
          <label class="font-bold text-slate-600">Mã phòng</label>
          <input 
            v-model="filterRoomCode" 
            type="text" 
            placeholder="Mã phòng..."
            class="border border-slate-200 rounded-md p-1 h-[28px] focus:outline-sky-500 font-semibold text-slate-700 bg-white"
            @keyup.enter="handleSearch"
          />
        </div>

        <!-- User Selection Dropdown -->
        <div class="flex flex-col gap-1">
          <label class="font-bold text-slate-600">Người dùng</label>
          <select 
            v-model="filterUserId"
            class="border border-slate-200 rounded-md p-1 h-[28px] focus:outline-sky-500 font-semibold text-slate-700 bg-white"
          >
            <option value="">-- Tất cả --</option>
            <option v-for="u in usersList" :key="u.user_id" :value="u.user_id">
              {{ u.user_name || 'User' }} ({{ u.employee_code || 'N/A' }})
            </option>
          </select>
        </div>

        <!-- Screen (Component) Selection Dropdown -->
        <div class="flex flex-col gap-1">
          <label class="font-bold text-slate-600">Màn hình</label>
          <select 
            v-model="filterComponent"
            class="border border-slate-200 rounded-md p-1 h-[28px] focus:outline-sky-500 font-semibold text-slate-700 bg-white"
          >
            <option value="">-- Tất cả --</option>
            <option v-for="c in componentsList" :key="c" :value="c">
              {{ c }}
            </option>
          </select>
        </div>

        <!-- Global Search -->
        <div class="flex flex-col gap-1">
          <label class="font-bold text-slate-600">Tìm kiếm chung</label>
          <input 
            v-model="filterSearch" 
            type="text" 
            placeholder="Nhập nội dung tìm kiếm..."
            class="border border-slate-200 rounded-md p-1 h-[28px] focus:outline-sky-500 font-semibold text-slate-700 bg-white"
            @keyup.enter="handleSearch"
          />
        </div>
      </div>

      <!-- Action buttons -->
      <div class="flex items-center justify-end gap-2 mt-2.5">
        <button 
          @click="handleResetFilters"
          class="px-4 py-1 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-md font-bold text-xs border-none cursor-pointer flex items-center gap-1 transition-colors h-[28px]"
        >
          Hủy Lọc
        </button>
        <button 
          @click="handleSearch"
          class="px-4 py-1 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-md font-bold text-xs border-none cursor-pointer flex items-center gap-1 transition-colors h-[28px]"
        >
          Tìm Kiếm
        </button>
        <button 
          @click="handleExport"
          class="px-4 py-1 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded-md font-bold text-xs border-none cursor-pointer flex items-center gap-1 transition-colors h-[28px]"
        >
          Xuất CSV
        </button>
      </div>
    </div>

    <!-- Data Table Container -->
    <div class="overflow-auto border border-slate-200 rounded-lg shadow-sm flex-1 max-h-full bg-white relative">
      <!-- Loading Overlay -->
      <div v-if="loading" class="absolute inset-0 bg-white/70 flex items-center justify-center z-10">
        <div class="flex flex-col items-center gap-2">
          <div class="w-8 h-8 rounded-full border-4 border-slate-200 border-t-sky-500 animate-spin"></div>
          <span class="font-bold text-sky-600">Đang tải lịch sử...</span>
        </div>
      </div>

      <table class="w-full text-left border-collapse text-xs">
        <thead>
          <tr class="bg-slate-100 border-b border-slate-200 text-slate-700 font-bold select-none h-9 sticky top-0 z-20">
            <th @click="toggleSort('id')" class="p-2 border-r border-slate-200 w-14 cursor-pointer hover:bg-slate-200 text-center uppercase">
              ID <span v-if="queryParams.sort_by === 'id'" class="text-[9px] text-sky-500">{{ queryParams.sort_dir === 'asc' ? '▲' : '▼' }}</span>
            </th>
            <th @click="toggleSort('created_at')" class="p-2 border-r border-slate-200 w-36 cursor-pointer hover:bg-slate-200 uppercase">
              Thời gian <span v-if="queryParams.sort_by === 'created_at'" class="text-[9px] text-sky-500">{{ queryParams.sort_dir === 'asc' ? '▲' : '▼' }}</span>
            </th>
            <th class="p-2 border-r border-slate-200 w-32 uppercase">Người dùng</th>
            <th class="p-2 border-r border-slate-200 w-28 uppercase">Địa chỉ IP</th>
            <th class="p-2 border-r border-slate-200 w-28 uppercase">Thiết bị</th>
            <th class="p-2 border-r border-slate-200 w-32 uppercase">Màn hình</th>
            <th @click="toggleSort('action')" class="p-2 border-r border-slate-200 w-28 cursor-pointer hover:bg-slate-200 uppercase">
              Hành động <span v-if="queryParams.sort_by === 'action'" class="text-[9px] text-sky-500">{{ queryParams.sort_dir === 'asc' ? '▲' : '▼' }}</span>
            </th>
            <th class="p-2 border-r border-slate-200 w-28 uppercase">Mã đăng ký</th>
            <th class="p-2 border-r border-slate-200 w-24 uppercase">Mã phòng</th>
            <th class="p-2 border-r border-slate-200 uppercase">Mô tả</th>
            <th class="p-2 text-center w-20 uppercase">Chi tiết</th>
          </tr>
        </thead>
        <tbody>
          <tr 
            v-for="log in logs" 
            :key="log.id" 
            class="border-b border-slate-200 hover:bg-[#bdecfe]/30 h-9 transition-colors font-semibold text-slate-700"
          >
            <td class="p-2 border-r border-slate-200 text-center text-slate-500 font-normal">{{ log.id }}</td>
            <td class="p-2 border-r border-slate-200 text-slate-600 font-medium">{{ formatDateTime(log.created_at) }}</td>
            <td class="p-2 border-r border-slate-200 text-slate-800">
              <div>{{ log.user_name || 'Hệ thống' }}</div>
              <div class="text-[9px] text-slate-400 font-normal">Mã NV: {{ log.employee_code || 'N/A' }}</div>
            </td>
            <td class="p-2 border-r border-slate-200 text-slate-500 font-mono font-normal">{{ log.ip_address || '-' }}</td>
            <td class="p-2 border-r border-slate-200 text-slate-500 font-normal truncate max-w-[110px]" :title="log.user_agent">
              {{ parseUserAgent(log.user_agent) }}
            </td>
            <td class="p-2 border-r border-slate-200 text-slate-600">
              <span class="font-bold">{{ log.component || '-' }}</span>
              <div class="text-[9px] text-slate-400 font-normal uppercase">{{ log.module || 'other' }}</div>
            </td>
            <td class="p-2 border-r border-slate-200 text-center">
              <span :class="getActionClass(log.action)">{{ getActionLabel(log.action) }}</span>
            </td>
            <td class="p-2 border-r border-slate-200 font-mono text-indigo-600 font-normal">{{ log.target_label || '-' }}</td>
            <td class="p-2 border-r border-slate-200 font-mono font-normal">{{ log.target_id || '-' }}</td>
            <td class="p-2 border-r border-slate-200 text-slate-600 font-normal truncate max-w-[200px]" :title="log.description">
              {{ log.description }}
            </td>
            <td class="p-2 text-center">
              <button 
                v-if="log.old_values || log.new_values"
                @click="viewDetails(log)"
                class="px-2 py-0.5 bg-[#8dcbf4] hover:bg-[#70b2db] text-white rounded text-[10px] font-bold border-none cursor-pointer transition-colors shadow-2xs"
              >
                Chi tiết
              </button>
              <span v-else class="text-slate-400 font-normal">-</span>
            </td>
          </tr>

          <tr v-if="logs.length === 0 && !loading">
            <td colspan="11" class="p-12 text-center text-slate-400 text-xs font-semibold">
              Không tìm thấy lịch sử thao tác phù hợp
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Pagination Footer -->
    <div v-if="lastPage > 1" class="flex items-center justify-between mt-3 gap-1 select-none shrink-0 border-t border-slate-100 pt-3">
      <div class="text-[10px] text-slate-500 font-bold">
        Hiển thị {{ logs.length }} / {{ totalItems }} bản ghi
      </div>
      <div class="flex items-center gap-1">
        <button 
          @click="changePage(currentPage - 1)" 
          :disabled="currentPage === 1"
          class="px-2.5 py-1 border border-slate-200 rounded text-xs text-slate-500 bg-white hover:bg-slate-50 cursor-pointer disabled:opacity-40"
        >
          &lt;
        </button>
        <button 
          v-for="p in lastPage" 
          :key="p"
          @click="changePage(p)"
          class="px-2.5 py-1 border rounded text-xs font-bold cursor-pointer"
          :class="currentPage === p ? 'border-sky-400 text-sky-600 bg-sky-50' : 'border-slate-200 text-slate-500 bg-white hover:bg-slate-50'"
        >
          {{ p }}
        </button>
        <button 
          @click="changePage(currentPage + 1)" 
          :disabled="currentPage === lastPage"
          class="px-2.5 py-1 border border-slate-200 rounded text-xs text-slate-500 bg-white hover:bg-slate-50 cursor-pointer disabled:opacity-40"
        >
          &gt;
        </button>
      </div>
    </div>

    <!-- Modals: View Changes JSON Diff (Wow Factor) -->
    <div 
      v-if="isModalOpen" 
      class="fixed inset-0 z-[1000] flex items-center justify-center bg-black/60 backdrop-blur-xs"
    >
      <div class="bg-white rounded-xl w-full max-w-3xl shadow-2xl overflow-hidden border border-slate-100 animate-in">
        <!-- Modal Header -->
        <div class="bg-[#8dcbf4] px-5 py-3 flex items-center justify-between text-white">
          <div class="flex items-center gap-2">
            <span class="px-2 py-0.5 bg-white/20 text-white rounded text-[10px] font-black uppercase">Log #{{ selectedLog?.id }}</span>
            <h2 class="text-sm font-bold tracking-wide">Chi Tiết Thay Đổi Dữ Liệu</h2>
          </div>
          <button @click="isModalOpen = false" class="text-white/80 hover:text-white bg-transparent border-none cursor-pointer text-lg font-light leading-none">✕</button>
        </div>

        <!-- Modal Info Bar -->
        <div class="bg-slate-50 border-b border-slate-100 p-4 grid grid-cols-2 gap-4 text-xs font-semibold text-slate-600">
          <div>
            <p><span class="text-slate-400">Người thực hiện:</span> <span class="text-slate-800 font-bold">{{ selectedLog?.user_name }}</span></p>
            <p class="mt-1"><span class="text-slate-400">Thời gian:</span> <span class="text-slate-800">{{ formatDateTime(selectedLog?.created_at) }}</span></p>
          </div>
          <div>
            <p><span class="text-slate-400">Hành động:</span> <span :class="getActionClass(selectedLog?.action)">{{ getActionLabel(selectedLog?.action) }}</span></p>
            <p class="mt-1"><span class="text-slate-400">Đối tượng tác động:</span> <span class="text-indigo-600">{{ selectedLog?.target_label || 'Không rõ' }} (ID: {{ selectedLog?.target_id || 'N/A' }})</span></p>
          </div>
        </div>

        <!-- Modal Body: Diff List -->
        <div class="p-6 max-h-[60vh] overflow-y-auto scrollbar-none text-xs">
          <!-- Diff Table -->
          <div class="border border-slate-200 rounded-lg overflow-hidden">
            <table class="w-full text-left border-collapse">
              <thead>
                <tr class="bg-slate-50 border-b border-slate-200 font-bold text-slate-700">
                  <th class="p-2 border-r border-slate-200 w-1/3">Trường dữ liệu</th>
                  <th class="p-2 border-r border-slate-200 bg-rose-50/50 text-rose-700 w-1/3">Giá trị cũ (Before)</th>
                  <th class="p-2 bg-green-50/50 text-green-700 w-1/3">Giá trị mới (After)</th>
                </tr>
              </thead>
              <tbody>
                <!-- For Updates (diff) -->
                <template v-if="selectedLog?.action === 'update'">
                  <tr 
                    v-for="(newVal, key) in selectedLog.new_values" 
                    :key="key"
                    class="border-b border-slate-100 hover:bg-slate-50"
                  >
                    <td class="p-2 border-r border-slate-200 font-bold text-slate-700 font-mono">{{ key }}</td>
                    <td class="p-2 border-r border-slate-200 bg-rose-50/30 text-rose-600 font-mono break-all whitespace-pre-wrap">{{ formatDiffValue(selectedLog.old_values?.[key]) }}</td>
                    <td class="p-2 bg-green-50/30 text-green-600 font-mono break-all font-bold whitespace-pre-wrap">{{ formatDiffValue(newVal) }}</td>
                  </tr>
                </template>

                <!-- For Creates -->
                <template v-else-if="selectedLog?.action === 'create'">
                  <tr 
                    v-for="(val, key) in selectedLog.new_values" 
                    :key="key"
                    class="border-b border-slate-100 hover:bg-slate-50"
                  >
                    <td class="p-2 border-r border-slate-200 font-bold text-slate-700 font-mono">{{ key }}</td>
                    <td class="p-2 border-r border-slate-200 bg-slate-50 text-slate-400 font-mono italic">-- Trống --</td>
                    <td class="p-2 bg-green-50/30 text-green-600 font-mono break-all font-bold whitespace-pre-wrap">{{ formatDiffValue(val) }}</td>
                  </tr>
                </template>

                <!-- For Deletes -->
                <template v-else-if="selectedLog?.action === 'delete'">
                  <tr 
                    v-for="(val, key) in selectedLog.old_values" 
                    :key="key"
                    class="border-b border-slate-100 hover:bg-slate-50"
                  >
                    <td class="p-2 border-r border-slate-200 font-bold text-slate-700 font-mono">{{ key }}</td>
                    <td class="p-2 border-r border-slate-200 bg-rose-50/30 text-rose-600 font-mono break-all font-bold whitespace-pre-wrap">{{ formatDiffValue(val) }}</td>
                    <td class="p-2 bg-slate-50 text-slate-400 font-mono italic">-- Đã xóa --</td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="bg-slate-50 border-t border-slate-100 px-6 py-3 flex items-center justify-end">
          <button 
            @click="isModalOpen = false" 
            class="px-5 py-1.5 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-md font-bold text-xs cursor-pointer border-none transition-colors"
          >
            Đóng
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.animate-in {
  animation: fadeIn 0.18s ease-out forwards;
}
@keyframes fadeIn {
  from { opacity: 0; transform: scale(0.96); }
  to { opacity: 1; transform: scale(1); }
}
</style>
