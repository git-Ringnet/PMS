<template>
  <div class="flex flex-col h-full bg-slate-50 p-5 font-sans">
    
    <!-- Top Filters Header -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 mb-5 shrink-0">
      <div class="flex items-center justify-between mb-5">
        <h2 class="text-base font-bold text-slate-800">Bộ Lọc Tìm Kiếm</h2>
        <div class="flex items-center gap-3">
          <button class="bg-white hover:bg-slate-50 border border-slate-300 text-slate-700 px-4 py-2.5 rounded-lg transition-all text-[13px] font-semibold flex items-center gap-2 shadow-sm">
            <Download class="w-4 h-4 text-slate-500" />
            Xuất Excel
          </button>
          <button class="bg-sky-500 hover:bg-sky-600 text-white px-6 py-2.5 rounded-lg transition-all text-[13px] font-semibold shadow-sm flex items-center gap-2">
            <Search class="w-4 h-4" stroke-width="2.5" />
            Tìm kiếm
          </button>
        </div>
      </div>

      <div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
        
        <!-- Date Range -->
        <div class="col-span-1 flex flex-col gap-1.5 relative" ref="datePickerWrapper">
          <label class="text-[12px] font-medium text-slate-600">Thời gian</label>
          <div class="relative flex items-center cursor-pointer" @click="openDatePicker">
            <input 
              type="text" 
              readonly
              :value="`${formatDate(fromDate)} ~ ${formatDate(toDate)}`"
              class="w-full border border-slate-300 rounded-lg px-3 py-2 text-[13px] text-slate-700 bg-white focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 cursor-pointer transition-all"
            />
            <Calendar class="w-4 h-4 text-sky-500 absolute right-3 pointer-events-none" />
          </div>

          <!-- Date Picker Popover -->
          <div 
            v-if="showDatePicker"
            class="absolute top-[calc(100%+4px)] left-0 w-[380px] bg-white border border-slate-200 rounded-xl shadow-xl z-30 p-5"
          >
            <div class="flex flex-col gap-4">
              <div>
                <label class="text-[12px] font-bold text-slate-800 mb-1.5 block">Phạm vi ngày</label>
                
                <div class="relative" ref="dropdownWrapper">
                  <div 
                    @click="showDropdown = !showDropdown"
                    class="border border-slate-300 rounded-lg px-3 py-2 text-[13px] text-slate-700 cursor-pointer flex justify-between items-center hover:border-sky-400 transition-colors"
                  >
                    <span>{{ getTempSelectedLabel() }}</span>
                    <ChevronDown class="w-4 h-4 text-slate-500" />
                  </div>
                  
                  <div 
                    v-if="showDropdown"
                    class="absolute top-full left-0 right-0 mt-1 bg-white border border-slate-200 rounded-lg shadow-lg z-40 max-h-48 overflow-y-auto"
                  >
                    <ul class="py-1">
                      <li 
                        v-for="opt in dateRangeOptions" 
                        :key="opt.value"
                        @click.stop="selectDateRange(opt.value)"
                        class="px-4 py-2 text-[13px] text-slate-700 hover:bg-sky-50 hover:text-sky-600 cursor-pointer transition-colors"
                        :class="{'bg-sky-50 text-sky-600 font-medium': tempDateRangeType === opt.value}"
                      >
                        {{ opt.label }}
                      </li>
                    </ul>
                  </div>
                </div>
              </div>

              <div class="flex items-center gap-2">
                <div class="relative flex-1 min-w-0">
                  <input 
                    type="date" 
                    v-model="tempFromDate"
                    class="w-full border border-slate-300 rounded-lg px-2 py-2 text-[13px] text-slate-700 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 transition-all min-w-0"
                    @change="tempDateRangeType = 'custom'"
                  />
                </div>
                <span class="text-slate-400 font-medium shrink-0">~</span>
                <div class="relative flex-1 min-w-0">
                  <input 
                    type="date" 
                    v-model="tempToDate"
                    class="w-full border border-slate-300 rounded-lg px-2 py-2 text-[13px] text-slate-700 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 transition-all min-w-0"
                    @change="tempDateRangeType = 'custom'"
                  />
                </div>
              </div>

              <div class="border-t border-slate-100 pt-4 mt-2 flex justify-end">
                <button 
                  @click="applyDateRange"
                  class="bg-sky-500 hover:bg-sky-600 text-white px-5 py-2 rounded-lg flex items-center gap-2 transition-colors text-[13px] font-semibold shadow-sm"
                >
                  <CheckCircle2 class="w-4 h-4" />
                  Áp dụng
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Mã đăng ký -->
        <div class="col-span-1 flex flex-col gap-1.5">
          <label class="text-[12px] font-medium text-slate-600">Mã đăng ký</label>
          <input 
            type="text" 
            placeholder="Nhập mã..."
            class="border border-slate-300 rounded-lg px-3 py-2 text-[13px] text-slate-700 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 transition-all"
          />
        </div>

        <!-- Mã phòng -->
        <div class="col-span-1 flex flex-col gap-1.5">
          <label class="text-[12px] font-medium text-slate-600">Mã phòng</label>
          <input 
            type="text" 
            placeholder="Nhập mã..."
            class="border border-slate-300 rounded-lg px-3 py-2 text-[13px] text-slate-700 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 transition-all"
          />
        </div>

        <!-- Người dùng -->
        <div class="col-span-1 flex flex-col gap-1.5">
          <label class="text-[12px] font-medium text-slate-600">Người dùng</label>
          <input 
            type="text" 
            placeholder="Tên user..."
            class="border border-slate-300 rounded-lg px-3 py-2 text-[13px] text-slate-700 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 transition-all"
          />
        </div>

        <!-- Hành động -->
        <div class="col-span-1 flex flex-col gap-1.5">
          <label class="text-[12px] font-medium text-slate-600">Hành động</label>
          <div class="relative">
            <select class="w-full border border-slate-300 rounded-lg px-3 py-2 text-[13px] text-slate-700 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 transition-all appearance-none bg-white cursor-pointer">
              <option value="" disabled selected>Chọn thao tác</option>
              <option>Test</option>
              <option>Tạo mới</option>
              <option>Cập nhật</option>
              <option>Xóa</option>
              <option>Login</option>
              <option>Logout</option>
              <option>Print</option>
            </select>
            <ChevronDown class="w-4 h-4 text-slate-400 absolute right-3 top-2.5 pointer-events-none" />
          </div>
        </div>

        <!-- Màn hình -->
        <div class="col-span-1 flex flex-col gap-1.5">
          <label class="text-[12px] font-medium text-slate-600">Màn hình</label>
          <input 
            type="text" 
            placeholder="Tên màn hình..."
            class="border border-slate-300 rounded-lg px-3 py-2 text-[13px] text-slate-700 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 transition-all"
          />
        </div>

      </div>
    </div>

    <!-- Table Container -->
    <div class="flex-1 bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden flex flex-col relative z-0">
      <div class="overflow-auto flex-1">
        <table class="w-full text-left border-collapse whitespace-nowrap min-w-max">
          <thead class="bg-slate-50 text-slate-600 text-[12px] font-bold border-b border-slate-200 sticky top-0 uppercase tracking-wider">
            <tr>
              <th class="py-3 px-4 border-r border-slate-200 w-16 text-center">ID</th>
              <th class="py-3 px-4 border-r border-slate-200">Thời gian</th>
              <th class="py-3 px-4 border-r border-slate-200 group cursor-pointer hover:bg-slate-100 transition-colors">
                <div class="flex items-center justify-between gap-3">
                  <span>Trình duyệt</span>
                  <Filter class="w-3.5 h-3.5 text-slate-400" />
                </div>
              </th>
              <th class="py-3 px-4 border-r border-slate-200 group cursor-pointer hover:bg-slate-100 transition-colors">
                <div class="flex items-center justify-between gap-3">
                  <span>Màn hình</span>
                  <Filter class="w-3.5 h-3.5 text-slate-400" />
                </div>
              </th>
              <th class="py-3 px-4 border-r border-slate-200 group cursor-pointer hover:bg-slate-100 transition-colors">
                <div class="flex items-center justify-between gap-3">
                  <span>Người dùng</span>
                  <Filter class="w-3.5 h-3.5 text-slate-400" />
                </div>
              </th>
              <th class="py-3 px-4 border-r border-slate-200">Ngày</th>
              <th class="py-3 px-4 border-r border-slate-200 group cursor-pointer hover:bg-slate-100 transition-colors">
                <div class="flex items-center justify-between gap-3">
                  <span>Hành động</span>
                  <Filter class="w-3.5 h-3.5 text-slate-400" />
                </div>
              </th>
              <th class="py-3 px-4 border-r border-slate-200 relative group cursor-pointer hover:bg-slate-100 transition-colors">
                <div class="flex items-center justify-between gap-3" @click="toggleSearchPopover('regCode')">
                  <span>Mã đăng ký</span>
                  <Search class="w-3.5 h-3.5 text-slate-400" />
                </div>
                <!-- Search Popover -->
                <div 
                  v-if="activeSearchColumn === 'regCode'"
                  class="absolute top-[calc(100%+4px)] left-0 w-[280px] bg-white border border-slate-200 rounded-xl shadow-xl z-50 p-4 flex flex-col gap-3 font-normal normal-case tracking-normal"
                  @click.stop
                >
                  <input 
                    type="text" 
                    placeholder="Nhập mã đăng ký..." 
                    class="border border-slate-300 rounded-lg px-3 py-2 text-[13px] w-full text-slate-700 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 transition-all"
                  />
                  <div class="flex items-center gap-2">
                    <button @click="activeSearchColumn = null" class="flex-1 bg-sky-500 hover:bg-sky-600 text-white py-2 rounded-lg text-[13px] font-semibold flex items-center justify-center gap-2 transition-colors">
                      <Search class="w-4 h-4" stroke-width="2.5" />
                      Tìm kiếm
                    </button>
                    <button @click="activeSearchColumn = null" class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-700 py-2 rounded-lg text-[13px] font-semibold transition-colors">
                      Làm mới
                    </button>
                  </div>
                </div>
              </th>
              <th class="py-3 px-4 border-r border-slate-200 relative group cursor-pointer hover:bg-slate-100 transition-colors">
                <div class="flex items-center justify-between gap-3" @click="toggleSearchPopover('roomCode')">
                  <span>Mã phòng</span>
                  <Search class="w-3.5 h-3.5 text-slate-400" />
                </div>
                <!-- Search Popover -->
                <div 
                  v-if="activeSearchColumn === 'roomCode'"
                  class="absolute top-[calc(100%+4px)] left-0 w-[280px] bg-white border border-slate-200 rounded-xl shadow-xl z-50 p-4 flex flex-col gap-3 font-normal normal-case tracking-normal"
                  @click.stop
                >
                  <input 
                    type="text" 
                    placeholder="Nhập mã phòng..." 
                    class="border border-slate-300 rounded-lg px-3 py-2 text-[13px] w-full text-slate-700 focus:outline-none focus:ring-2 focus:ring-sky-100 focus:border-sky-400 transition-all"
                  />
                  <div class="flex items-center gap-2">
                    <button @click="activeSearchColumn = null" class="flex-1 bg-sky-500 hover:bg-sky-600 text-white py-2 rounded-lg text-[13px] font-semibold flex items-center justify-center gap-2 transition-colors">
                      <Search class="w-4 h-4" stroke-width="2.5" />
                      Tìm kiếm
                    </button>
                    <button @click="activeSearchColumn = null" class="flex-1 bg-slate-100 hover:bg-slate-200 text-slate-700 py-2 rounded-lg text-[13px] font-semibold transition-colors">
                      Làm mới
                    </button>
                  </div>
                </div>
              </th>
              <th class="py-3 px-4">Mô tả</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td colspan="10" class="p-20 text-center">
                <div class="flex flex-col items-center justify-center">
                  <div class="bg-slate-50 rounded-full p-4 mb-3 border border-slate-100">
                    <Inbox class="w-10 h-10 text-slate-300" stroke-width="1.5" />
                  </div>
                  <h3 class="text-[14px] font-bold text-slate-700 mb-1">Chưa có dữ liệu</h3>
                  <p class="text-[12px] text-slate-500">Hãy thử thay đổi bộ lọc tìm kiếm để xem lịch sử thao tác.</p>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { Calendar, ChevronDown, Download, Filter, Search, Inbox, CheckCircle2 } from '@lucide/vue'

// --- Date Picker Logic ---
const showDatePicker = ref(false)
const showDropdown = ref(false)
const datePickerWrapper = ref(null)
const dropdownWrapper = ref(null)

const getToday = () => {
  const d = new Date()
  d.setMinutes(d.getMinutes() - d.getTimezoneOffset())
  return d.toISOString().split('T')[0]
}

const fromDate = ref(getToday())
const toDate = ref(getToday())
const tempFromDate = ref(getToday())
const tempToDate = ref(getToday())
const dateRangeType = ref('today')
const tempDateRangeType = ref('today')

const dateRangeOptions = [
  { label: 'Hôm nay', value: 'today' },
  { label: 'Tuần này', value: 'this_week' },
  { label: 'Tháng này', value: 'this_month' },
  { label: 'Ngày mai', value: 'tomorrow' },
  { label: 'Tuần tiếp theo', value: 'next_week' },
  { label: 'Tháng tiếp theo', value: 'next_month' },
  { label: 'Hôm qua', value: 'yesterday' },
  { label: 'Tuần trước', value: 'last_week' },
  { label: 'Tháng trước', value: 'last_month' },
  { label: 'Tùy chỉnh', value: 'custom' },
]

const getTempSelectedLabel = () => {
  const opt = dateRangeOptions.find(o => o.value === tempDateRangeType.value)
  return opt ? opt.label : 'Tùy chỉnh'
}

const formatDate = (dateStr) => {
  if (!dateStr) return ''
  const [y, m, d] = dateStr.split('-')
  return `${d}/${m}/${y}`
}

const openDatePicker = () => {
  if (!showDatePicker.value) {
    tempFromDate.value = fromDate.value
    tempToDate.value = toDate.value
    tempDateRangeType.value = dateRangeType.value
    showDatePicker.value = true
  } else {
    showDatePicker.value = false
  }
}

const selectDateRange = (value) => {
  tempDateRangeType.value = value
  showDropdown.value = false
  
  const d = new Date()
  let start = new Date(d)
  let end = new Date(d)
  
  const getMonday = (date) => {
    const day = date.getDay()
    const diff = date.getDate() - day + (day === 0 ? -6 : 1)
    return new Date(date.setDate(diff))
  }
  
  const getFirstDayOfMonth = (date) => new Date(date.getFullYear(), date.getMonth(), 1)
  const getLastDayOfMonth = (date) => new Date(date.getFullYear(), date.getMonth() + 1, 0)

  switch(value) {
    case 'today': break
    case 'yesterday':
      start.setDate(start.getDate() - 1); end.setDate(end.getDate() - 1); break
    case 'tomorrow':
      start.setDate(start.getDate() + 1); end.setDate(end.getDate() + 1); break
    case 'this_week':
      start = getMonday(new Date(d)); end = new Date(start); end.setDate(end.getDate() + 6); break
    case 'last_week':
      start = getMonday(new Date(d)); start.setDate(start.getDate() - 7); end = new Date(start); end.setDate(end.getDate() + 6); break
    case 'next_week':
      start = getMonday(new Date(d)); start.setDate(start.getDate() + 7); end = new Date(start); end.setDate(end.getDate() + 6); break
    case 'this_month':
      start = getFirstDayOfMonth(new Date(d)); end = getLastDayOfMonth(new Date(d)); break
    case 'last_month':
      start = getFirstDayOfMonth(new Date(d)); start.setMonth(start.getMonth() - 1); end = getLastDayOfMonth(new Date(start)); break
    case 'next_month':
      start = getFirstDayOfMonth(new Date(d)); start.setMonth(start.getMonth() + 1); end = getLastDayOfMonth(new Date(start)); break
    case 'custom': return
  }
  
  start.setMinutes(start.getMinutes() - start.getTimezoneOffset())
  end.setMinutes(end.getMinutes() - end.getTimezoneOffset())
  
  tempFromDate.value = start.toISOString().split('T')[0]
  tempToDate.value = end.toISOString().split('T')[0]
}

const applyDateRange = () => {
  fromDate.value = tempFromDate.value
  toDate.value = tempToDate.value
  dateRangeType.value = tempDateRangeType.value
  showDatePicker.value = false
}

// --- Search Popover Logic ---
const activeSearchColumn = ref(null)

const toggleSearchPopover = (col) => {
  if (activeSearchColumn.value === col) {
    activeSearchColumn.value = null
  } else {
    activeSearchColumn.value = col
  }
}

// --- Click Outside Logic ---
const closeClickOutside = (e) => {
  if (showDropdown.value && dropdownWrapper.value && !dropdownWrapper.value.contains(e.target)) {
    showDropdown.value = false
  }
  if (showDatePicker.value && datePickerWrapper.value && !datePickerWrapper.value.contains(e.target)) {
    showDatePicker.value = false
  }
  // If clicking outside any search popover, close it. 
  // We identify outside clicks if the target isn't inside a relative th that has the popover.
  // A simple way is to check if it's not a th or doesn't have our active state, but the easiest is:
  if (activeSearchColumn.value) {
    const isInsideTh = e.target.closest('th')
    if (!isInsideTh) {
      activeSearchColumn.value = null
    }
  }
}

onMounted(() => {
  document.addEventListener('click', closeClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('click', closeClickOutside)
})
</script>
