<template>
  <div class="flex flex-col h-full bg-slate-50 p-5 font-sans relative">
    
    <!-- Top Filters Card -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 mb-5 shrink-0 flex items-end justify-between gap-4">
      
      <!-- Left side: Filters -->
      <div class="flex flex-col gap-1.5 relative w-[280px]" ref="datePickerWrapper">
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
              
              <!-- Custom Select Dropdown -->
              <div class="relative" ref="dropdownWrapper">
                <div 
                  @click="showDropdown = !showDropdown"
                  class="border border-slate-300 rounded-lg px-3 py-2 text-[13px] text-slate-700 cursor-pointer flex justify-between items-center hover:border-sky-400 transition-colors"
                >
                  <span>{{ getTempSelectedLabel() }}</span>
                  <ChevronDown class="w-4 h-4 text-slate-500" />
                </div>
                
                <!-- Dropdown List -->
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
      
      <!-- Right side: Actions -->
      <div class="flex items-center gap-3">
        <button class="bg-white hover:bg-slate-50 border border-slate-300 text-slate-700 px-6 py-2 rounded-lg transition-all text-[13px] font-semibold shadow-sm flex items-center gap-2">
          Xem
        </button>
        <button 
          @click="showAddModal = true"
          class="bg-sky-500 hover:bg-sky-600 text-white px-5 py-2 rounded-lg flex items-center transition-all text-[13px] font-semibold shadow-sm gap-1.5"
        >
          <Plus class="w-4 h-4" stroke-width="2.5" />
          Thêm
        </button>
      </div>
    </div>

    <!-- Table Container -->
    <div class="flex-1 bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden flex flex-col relative z-0">
      <div class="overflow-auto flex-1">
        <table class="w-full text-left border-collapse whitespace-nowrap min-w-max">
          <thead class="bg-slate-50 text-slate-600 text-[12px] font-bold border-b border-slate-200 sticky top-0 uppercase tracking-wider">
            <tr>
              <th class="py-3 px-4 border-r border-slate-200">Mã</th>
              <th class="py-3 px-4 border-r border-slate-200">Mã ĐK</th>
              <th class="py-3 px-4 border-r border-slate-200">Phòng</th>
              <th class="py-3 px-4 border-r border-slate-200">Giờ</th>
              <th class="py-3 px-4 border-r border-slate-200">Miễn phí</th>
              <th class="py-3 px-4 border-r border-slate-200">Giá</th>
              <th class="py-3 px-4 border-r border-slate-200">Mã thanh toán</th>
              <th class="py-3 px-4 border-r border-slate-200">Mã hóa đơn tay</th>
              <th class="py-3 px-4 border-r border-slate-200">Người dùng</th>
              <th class="py-3 px-4 border-r border-slate-200">Đã xoá</th>
              <th class="py-3 px-4 border-r border-slate-200">Bộ phận</th>
              <th class="py-3 px-4">Khu Vực</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td colspan="12" class="p-20 text-center">
                <div class="flex flex-col items-center justify-center">
                  <div class="bg-slate-50 rounded-full p-4 mb-3 border border-slate-100">
                    <Inbox class="w-10 h-10 text-slate-300" stroke-width="1.5" />
                  </div>
                  <h3 class="text-[14px] font-bold text-slate-700 mb-1">Chưa có dữ liệu</h3>
                  <p class="text-[12px] text-slate-500">Hãy bấm Xem để tải dữ liệu hóa đơn trong khoảng thời gian này.</p>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <!-- Mock Horizontal Scrollbar -->
      <div class="absolute bottom-1 left-1 right-1 h-1.5 bg-slate-200 rounded-full pointer-events-none opacity-50"></div>
    </div>

    <!-- Thêm Dịch Vụ BP Modal -->
    <div 
      v-if="showAddModal" 
      class="fixed inset-0 z-[100] flex justify-center items-center bg-slate-900/50 backdrop-blur-sm"
    >
      <div class="bg-white rounded-xl shadow-2xl flex flex-col w-[95vw] h-[95vh] max-w-[1400px] overflow-hidden border border-slate-200 transform transition-all">
        <!-- Modal Header -->
        <div class="bg-[#7ac7e6] text-white px-5 py-3 flex justify-between items-center shrink-0">
          <h3 class="text-[16px] font-bold">Thêm dịch vụ BP</h3>
          <div class="flex items-center gap-3">
            <button class="text-white hover:text-sky-100 hover:bg-white/20 p-1.5 rounded-full transition-colors">
              <HelpCircle class="w-4 h-4" />
            </button>
            <button 
              @click="showAddModal = false"
              class="text-white hover:bg-white/20 p-1.5 rounded-full transition-colors"
            >
              <X class="w-5 h-5" />
            </button>
          </div>
        </div>
        
        <!-- Modal Body (PostBillHousekeepingTab) -->
        <div class="flex-1 overflow-hidden bg-slate-50">
          <!-- Reusing the tab component seamlessly -->
          <PostBillHousekeepingTab />
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { Calendar, Plus, Inbox, ChevronDown, CheckCircle2, X, HelpCircle } from '@lucide/vue'
import PostBillHousekeepingTab from './PostBillHousekeepingTab.vue'

// --- Date Picker Logic ---
const showDatePicker = ref(false)
const showDropdown = ref(false)
const datePickerWrapper = ref(null)
const dropdownWrapper = ref(null)

const getToday = () => {
  const d = new Date()
  // Adjust for local timezone
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
    // Reset temp variables to current actual values when opening
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
  
  // Set day of week to Monday (1)
  const getMonday = (date) => {
    const day = date.getDay()
    const diff = date.getDate() - day + (day === 0 ? -6 : 1)
    return new Date(date.setDate(diff))
  }
  
  // First day of current month
  const getFirstDayOfMonth = (date) => {
    return new Date(date.getFullYear(), date.getMonth(), 1)
  }
  
  // Last day of current month
  const getLastDayOfMonth = (date) => {
    return new Date(date.getFullYear(), date.getMonth() + 1, 0)
  }

  switch(value) {
    case 'today':
      break
    case 'yesterday':
      start.setDate(start.getDate() - 1)
      end.setDate(end.getDate() - 1)
      break
    case 'tomorrow':
      start.setDate(start.getDate() + 1)
      end.setDate(end.getDate() + 1)
      break
    case 'this_week':
      start = getMonday(new Date(d))
      end = new Date(start)
      end.setDate(end.getDate() + 6)
      break
    case 'last_week':
      start = getMonday(new Date(d))
      start.setDate(start.getDate() - 7)
      end = new Date(start)
      end.setDate(end.getDate() + 6)
      break
    case 'next_week':
      start = getMonday(new Date(d))
      start.setDate(start.getDate() + 7)
      end = new Date(start)
      end.setDate(end.getDate() + 6)
      break
    case 'this_month':
      start = getFirstDayOfMonth(new Date(d))
      end = getLastDayOfMonth(new Date(d))
      break
    case 'last_month':
      start = getFirstDayOfMonth(new Date(d))
      start.setMonth(start.getMonth() - 1)
      end = getLastDayOfMonth(new Date(start))
      break
    case 'next_month':
      start = getFirstDayOfMonth(new Date(d))
      start.setMonth(start.getMonth() + 1)
      end = getLastDayOfMonth(new Date(start))
      break
    case 'custom':
      // Do not change temp dates, let user pick
      return
  }
  
  // Update temp dates
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

// Close popovers on click outside
const closeClickOutside = (e) => {
  if (showDropdown.value && dropdownWrapper.value && !dropdownWrapper.value.contains(e.target)) {
    showDropdown.value = false
  }
  if (showDatePicker.value && datePickerWrapper.value && !datePickerWrapper.value.contains(e.target)) {
    // Only close if we are not clicking inside the date picker popover itself
    // and not clicking inside the input
    showDatePicker.value = false
  }
}

// Modal Logic
const showAddModal = ref(false)

onMounted(() => {
  document.addEventListener('click', closeClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('click', closeClickOutside)
})

</script>
