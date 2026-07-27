<template>
  <div class="flex flex-col h-full bg-slate-50 p-5 font-sans relative">
    
    <!-- Filters Grid Panel -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4 mb-4 flex flex-col gap-3 shrink-0">
      <div class="flex flex-wrap items-end justify-between gap-4">
        <!-- Left side: Date Range -->
        <div class="flex flex-col gap-1.5 relative w-[240px]" ref="datePickerWrapper">
          <label class="text-[11px] font-bold text-slate-500 uppercase tracking-wide">Thời gian</label>
          <div class="relative flex items-center cursor-pointer" @click="openDatePicker">
            <input 
              type="text" 
              readonly
              :value="`${formatDate(fromDate)} ~ ${formatDate(toDate)}`"
              class="w-full border border-slate-300 rounded-lg px-2.5 py-1.5 text-[12px] text-slate-700 bg-white focus:outline-none focus:ring-2 focus:ring-[var(--hk-primary-light)] focus:border-[var(--hk-primary)] cursor-pointer transition-all shadow-sm font-medium"
            />
            <Calendar class="w-4 h-4 text-[var(--hk-primary-dark)] absolute right-3 pointer-events-none" />
          </div>

          <!-- Date Picker Popover -->
          <Transition name="hk-dropdown">
            <div 
              v-if="showDatePicker"
              class="absolute top-[calc(100%+4px)] left-0 w-[350px] bg-white border border-slate-250 rounded-xl shadow-xl z-50 p-4"
            >
              <div class="flex flex-col gap-4">
                <div>
                  <label class="text-[11px] font-bold text-slate-800 mb-1.5 block">Phạm vi ngày</label>
                  
                  <!-- Custom Select Dropdown -->
                  <div class="relative" ref="dropdownWrapper">
                    <div 
                      @click="showDropdown = !showDropdown"
                      class="border border-slate-300 rounded-lg px-3 py-2 text-[12px] text-slate-700 cursor-pointer flex justify-between items-center hover:border-[var(--hk-primary)] transition-colors bg-white font-medium shadow-sm"
                    >
                      <span>{{ getTempSelectedLabel() }}</span>
                      <ChevronDown class="w-4 h-4 text-slate-505" />
                    </div>
                    
                    <!-- Dropdown List -->
                    <Transition name="hk-dropdown">
                      <div 
                        v-if="showDropdown"
                        class="absolute top-full left-0 right-0 mt-1 bg-white border border-slate-200 rounded-lg shadow-lg z-50 max-h-48 overflow-y-auto hk-scroll"
                      >
                        <ul class="py-1">
                          <li 
                            v-for="opt in dateRangeOptions" 
                            :key="opt.value"
                            @click.stop="selectDateRange(opt.value)"
                            class="px-4 py-2 text-[12px] text-slate-700 hover:bg-sky-55 hover:text-[var(--hk-primary-dark)] cursor-pointer transition-colors"
                            :class="{'bg-sky-50 text-[var(--hk-primary-dark)] font-bold': tempDateRangeType === opt.value}"
                          >
                            {{ opt.label }}
                          </li>
                        </ul>
                      </div>
                    </Transition>
                  </div>
                </div>

                <div class="flex items-center gap-2">
                  <div class="relative flex-1 min-w-0">
                    <input 
                      type="date" 
                      v-model="tempFromDate"
                      class="w-full border border-slate-300 rounded-lg px-2.5 py-1.5 text-[12px] text-slate-700 focus:outline-none focus:ring-2 focus:ring-[var(--hk-primary-light)] focus:border-[var(--hk-primary)] transition-all min-w-0 bg-white"
                      @change="tempDateRangeType = 'custom'"
                    />
                  </div>
                  <span class="text-slate-400 font-medium shrink-0">~</span>
                  <div class="relative flex-1 min-w-0">
                    <input 
                      type="date" 
                      v-model="tempToDate"
                      class="w-full border border-slate-300 rounded-lg px-2.5 py-1.5 text-[12px] text-slate-700 focus:outline-none focus:ring-2 focus:ring-[var(--hk-primary-light)] focus:border-[var(--hk-primary)] transition-all min-w-0 bg-white"
                      @change="tempDateRangeType = 'custom'"
                    />
                  </div>
                </div>

                <div class="border-t border-slate-100 pt-3 flex justify-end">
                  <button 
                    @click="applyDateRange"
                    class="btn-primary px-4 py-1.5 rounded-lg flex items-center gap-1.5 text-xs font-bold shadow-sm cursor-pointer"
                  >
                    <CheckCircle2 class="w-4 h-4" />
                    Áp dụng
                  </button>
                </div>
              </div>
            </div>
          </Transition>
        </div>

        <!-- Mã ĐK filter -->
        <div class="flex flex-col gap-1.5 w-[110px]">
          <label class="text-[11px] font-bold text-slate-500 uppercase tracking-wide">Mã ĐK</label>
          <input 
            type="text" 
            data-hk-search
            v-model="filterState.regCode"
            placeholder="Mã ĐK..."
            class="border border-slate-300 rounded-lg px-2.5 py-1.5 text-[12px] text-slate-700 bg-white focus:outline-none focus:ring-2 focus:ring-[var(--hk-primary-light)] focus:border-[var(--hk-primary)] transition-all shadow-sm"
          />
        </div>

        <!-- Phòng Autocomplete/Select filter -->
        <div class="flex flex-col gap-1.5 w-[110px]">
          <label class="text-[11px] font-bold text-slate-500 uppercase tracking-wide">Phòng</label>
          <select 
            v-model="filterState.room"
            class="border border-slate-300 rounded-lg px-2.5 py-1.5 text-[12px] text-slate-700 bg-white focus:outline-none focus:ring-2 focus:ring-[var(--hk-primary-light)] focus:border-[var(--hk-primary)] transition-all shadow-sm cursor-pointer"
          >
            <option value="">Tất cả</option>
            <option v-for="r in ['402', '403', '405', '406', '407']" :key="r" :value="r">Phòng {{ r }}</option>
          </select>
        </div>

        <!-- Bộ phận filter -->
        <div class="flex flex-col gap-1.5 w-[120px]">
          <label class="text-[11px] font-bold text-slate-500 uppercase tracking-wide">Bộ phận</label>
          <select 
            v-model="filterState.dept"
            class="border border-slate-300 rounded-lg px-2.5 py-1.5 text-[12px] text-slate-700 bg-white focus:outline-none focus:ring-2 focus:ring-[var(--hk-primary-light)] focus:border-[var(--hk-primary)] transition-all shadow-sm cursor-pointer"
          >
            <option value="all">Tất cả</option>
            <option value="Minibar">Minibar</option>
            <option value="Giặt ủi">Giặt ủi</option>
            <option value="Hàng đền bù">Hàng đền bù</option>
          </select>
        </div>

        <!-- Người dùng filter -->
        <div class="flex flex-col gap-1.5 w-[130px]">
          <label class="text-[11px] font-bold text-slate-500 uppercase tracking-wide">Người dùng</label>
          <select 
            v-model="filterState.user"
            class="border border-slate-300 rounded-lg px-2.5 py-1.5 text-[12px] text-slate-700 bg-white focus:outline-none focus:ring-2 focus:ring-[var(--hk-primary-light)] focus:border-[var(--hk-primary)] transition-all shadow-sm cursor-pointer"
          >
            <option value="all">Tất cả</option>
            <option value="Nguyễn Văn A">Nguyễn Văn A</option>
            <option value="Trần Thị B">Trần Thị B</option>
            <option value="Lê Văn C">Lê Văn C</option>
          </select>
        </div>

        <!-- Khu vực filter -->
        <div class="flex flex-col gap-1.5 w-[110px]">
          <label class="text-[11px] font-bold text-slate-500 uppercase tracking-wide">Khu vực</label>
          <select 
            v-model="filterState.zone"
            class="border border-slate-300 rounded-lg px-2.5 py-1.5 text-[12px] text-slate-700 bg-white focus:outline-none focus:ring-2 focus:ring-[var(--hk-primary-light)] focus:border-[var(--hk-primary)] transition-all shadow-sm cursor-pointer"
          >
            <option value="all">Tất cả</option>
            <option value="Khu A">Khu A</option>
            <option value="Khu B">Khu B</option>
            <option value="Khu C">Khu C</option>
          </select>
        </div>

        <!-- Right Side buttons -->
        <div class="flex items-center gap-2">
          <button @click="triggerSearch" class="btn-primary px-4 py-1.5 rounded-lg text-xs font-bold shadow-sm cursor-pointer h-[32px] flex items-center justify-center min-w-[70px]">
            <span v-if="isSearching" class="w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin mr-1"></span>
            <span>Xem</span>
          </button>
          <button 
            @click="showAddModal = true"
            class="bg-emerald-500 hover:bg-emerald-600 text-white px-3 py-1.5 rounded-lg flex items-center transition-all text-xs font-bold shadow-sm gap-1.5 h-[32px] cursor-pointer"
          >
            <Plus class="w-3.5 h-3.5" stroke-width="3" />
            Thêm
          </button>
        </div>
      </div>

      <!-- Trạng thái Chip buttons row -->
      <div class="flex items-center gap-2 border-t border-slate-100 pt-2.5 text-xs">
        <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wide mr-1">Trạng thái:</span>
        <button 
          v-for="st in [
            { label: 'Tất cả', value: 'all' },
            { label: 'Đã thanh toán', value: 'paid' },
            { label: 'Chưa thanh toán', value: 'unpaid' },
            { label: 'Đã xóa', value: 'deleted' }
          ]"
          :key="st.value"
          @click="filterState.status = st.value"
          class="px-2.5 py-1 rounded-full text-xs transition-all duration-200 cursor-pointer border"
          :class="filterState.status === st.value
            ? 'bg-[var(--hk-primary-light)] text-sky-850 border-[var(--hk-primary)] font-bold shadow-sm'
            : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'"
        >
          {{ st.label }}
        </button>
      </div>
    </div>

    <!-- Table Container -->
    <div class="flex-1 bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden flex flex-col relative z-0">
      <div class="overflow-auto flex-1 hk-scroll">
        <table class="w-full text-left border-collapse whitespace-nowrap min-w-max text-xs">
          <thead class="bg-slate-100/90 text-slate-650 text-[11px] font-bold border-b border-slate-200 sticky top-0 uppercase tracking-wider z-10">
            <tr>
              <th class="py-3 px-4 border-r border-slate-200 text-center">Mã</th>
              <th class="py-3 px-4 border-r border-slate-200 text-center">Mã ĐK</th>
              <th class="py-3 px-4 border-r border-slate-200 text-center">Phòng</th>
              <th class="py-3 px-4 border-r border-slate-200 text-center">Giờ</th>
              <th class="py-3 px-4 border-r border-slate-200 text-center">Miễn phí</th>
              <th class="py-3 px-4 border-r border-slate-200 text-right">Giá</th>
              <th class="py-3 px-4 border-r border-slate-200 text-center">Mã thanh toán</th>
              <th class="py-3 px-4 border-r border-slate-200 text-center">Mã hóa đơn tay</th>
              <th class="py-3 px-4 border-r border-slate-200 text-center">Người dùng</th>
              <th class="py-3 px-4 border-r border-slate-200 text-center">Đã xoá</th>
              <th class="py-3 px-4 border-r border-slate-200 text-center">Bộ phận</th>
              <th class="py-3 px-4 text-center">Khu Vực</th>
            </tr>
          </thead>

          <tbody v-if="isSearching">
            <!-- loading skeleton state -->
            <tr v-for="i in 5" :key="'sk-'+i" class="animate-pulse">
              <td class="py-4 px-4"><div class="h-3.5 bg-slate-200 rounded w-10 mx-auto"></div></td>
              <td class="py-4 px-4"><div class="h-3.5 bg-slate-200 rounded w-12 mx-auto"></div></td>
              <td class="py-4 px-4"><div class="h-3.5 bg-slate-200 rounded w-16 mx-auto"></div></td>
              <td class="py-4 px-4"><div class="h-3.5 bg-slate-200 rounded w-24 mx-auto"></div></td>
              <td class="py-4 px-4"><div class="h-3.5 bg-slate-200 rounded w-8 mx-auto"></div></td>
              <td class="py-4 px-4"><div class="h-3.5 bg-slate-200 rounded w-16 ml-auto"></div></td>
              <td class="py-4 px-4"><div class="h-3.5 bg-slate-200 rounded w-14 mx-auto"></div></td>
              <td class="py-4 px-4"><div class="h-3.5 bg-slate-200 rounded w-10 mx-auto"></div></td>
              <td class="py-4 px-4"><div class="h-3.5 bg-slate-200 rounded w-20 mx-auto"></div></td>
              <td class="py-4 px-4"><div class="h-3.5 bg-slate-200 rounded w-8 mx-auto"></div></td>
              <td class="py-4 px-4"><div class="h-3.5 bg-slate-200 rounded w-16 mx-auto"></div></td>
              <td class="py-4 px-4"><div class="h-3.5 bg-slate-200 rounded w-12 mx-auto"></div></td>
            </tr>
          </tbody>

          <tbody v-else-if="!hasLoadedData" class="divide-y divide-slate-100">
            <tr>
              <td colspan="12" class="p-20 text-center bg-white">
                <div class="flex flex-col items-center justify-center gap-3">
                  <div class="bg-slate-50 rounded-full p-4 border border-slate-100 animate-bounce">
                    <Inbox class="w-10 h-10 text-slate-350" stroke-width="1.5" />
                  </div>
                  <h3 class="text-[14px] font-bold text-slate-700">Chưa tải dữ liệu</h3>
                  <p class="text-[12px] text-slate-500 max-w-sm mx-auto leading-relaxed">
                    Hãy bấm nút <strong class="text-[var(--hk-primary-dark)]">Xem</strong> ở góc phải trên để tải dữ liệu hóa đơn dịch vụ buồng phòng.
                  </p>
                </div>
              </td>
            </tr>
          </tbody>

          <tbody v-else-if="filteredInvoices.length === 0">
            <tr>
              <td colspan="12" class="p-20 text-center bg-white text-slate-400">
                <div class="flex flex-col items-center justify-center gap-3">
                  <span class="text-4xl">🔍</span>
                  <p class="font-bold text-slate-700 text-[14px]">Không tìm thấy hóa đơn phù hợp</p>
                  <p class="text-[12px] text-slate-500">Thử thay đổi bộ lọc hoặc điều kiện thời gian.</p>
                </div>
              </td>
            </tr>
          </tbody>

          <tbody v-else class="divide-y divide-slate-100">
            <tr 
              v-for="inv in filteredInvoices" 
              :key="inv.id"
              class="hover:bg-slate-50/80 transition-colors text-slate-700 font-medium"
            >
              <td class="py-2.5 px-4 font-bold text-sky-800 border-r border-slate-100 text-center">{{ inv.id }}</td>
              <td class="py-2.5 px-4 font-mono text-center border-r border-slate-100">{{ inv.regCode }}</td>
              <td class="py-2.5 px-4 font-bold text-center text-sky-750 border-r border-slate-100">Phòng {{ inv.room }}</td>
              <td class="py-2.5 px-4 text-center border-r border-slate-100">{{ inv.time }}</td>
              <td class="py-2.5 px-4 text-center border-r border-slate-100">
                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold" :class="inv.isFree ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-500'">
                  {{ inv.isFree ? 'FREE' : 'NO' }}
                </span>
              </td>
              <td class="py-2.5 px-4 text-right border-r border-slate-100 font-bold text-slate-800">{{ formatCurrency(inv.price) }}</td>
              <td class="py-2.5 px-4 text-center border-r border-slate-100 font-mono text-xs">{{ inv.payCode || '—' }}</td>
              <td class="py-2.5 px-4 text-center border-r border-slate-100 font-mono text-xs">{{ inv.manualCode || '—' }}</td>
              <td class="py-2.5 px-4 border-r border-slate-100 text-center">{{ inv.user }}</td>
              <td class="py-2.5 px-4 text-center border-r border-slate-100">
                <span class="px-1.5 py-0.5 rounded text-[10px] font-bold" :class="inv.isDeleted ? 'bg-rose-100 text-rose-800' : 'bg-slate-100 text-slate-500'">
                  {{ inv.isDeleted ? 'YES' : 'NO' }}
                </span>
              </td>
              <td class="py-2.5 px-4 text-center border-r border-slate-100">
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold" :class="getDeptClass(inv.dept)">
                  {{ inv.dept }}
                </span>
              </td>
              <td class="py-2.5 px-4 font-semibold text-slate-600 text-center">{{ inv.zone }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Thêm Dịch Vụ BP Modal -->
    <Transition name="hk-modal">
      <div 
        v-if="showAddModal" 
        class="fixed inset-0 z-[100] flex justify-center items-center bg-slate-900/50 backdrop-blur-sm"
      >
        <div class="bg-white rounded-xl shadow-2xl flex flex-col w-[95vw] h-[95vh] max-w-[1400px] overflow-hidden border border-slate-200 transform transition-all">
          <!-- Modal Header -->
          <div class="px-5 py-3 flex justify-between items-center shrink-0 text-slate-800" style="background: var(--hk-gradient, linear-gradient(135deg, #97D5FF, #6BC1F5))">
            <h3 class="text-[15px] font-black uppercase tracking-wider text-slate-800">Thêm hóa đơn dịch vụ BP</h3>
            <div class="flex items-center gap-3">
              <button class="text-slate-800 hover:bg-black/10 p-1.5 rounded-full transition-colors cursor-pointer border-none bg-transparent">
                <HelpCircle class="w-4 h-4" />
              </button>
              <button 
                @click="showAddModal = false"
                class="text-slate-800 hover:bg-black/10 p-1.5 rounded-full transition-colors cursor-pointer border-none bg-transparent"
              >
                <X class="w-5 h-5" />
              </button>
            </div>
          </div>
          
          <!-- Modal Body (PostBillHousekeepingTab) -->
          <div class="flex-1 overflow-hidden bg-slate-50">
            <PostBillHousekeepingTab />
          </div>
        </div>
      </div>
    </Transition>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import { Calendar, Plus, Inbox, ChevronDown, CheckCircle2, X, HelpCircle } from '@lucide/vue'
import PostBillHousekeepingTab from './PostBillHousekeepingTab.vue'

const route = useRoute()

// Mock Invoice data
const invoicesList = ref([
  { id: 101, regCode: '5197', room: '402', time: '19-06-2026 09:30', isFree: false, price: 45000, payCode: 'PAY-101', manualCode: 'M-129', user: 'Nguyễn Văn A', isDeleted: false, dept: 'Minibar', zone: 'Khu A' },
  { id: 102, regCode: '5407', room: '403', time: '19-06-2026 10:15', isFree: false, price: 120000, payCode: '', manualCode: '', user: 'Trần Thị B', isDeleted: false, dept: 'Giặt ủi', zone: 'Khu B' },
  { id: 103, regCode: '5408', room: '405', time: '18-06-2026 14:00', isFree: true, price: 15000, payCode: 'FREE-103', manualCode: '', user: 'Lê Văn C', isDeleted: false, dept: 'Minibar', zone: 'Khu A' },
  { id: 104, regCode: '5330', room: '406', time: '17-06-2026 16:45', isFree: false, price: 50000, payCode: 'PAY-104', manualCode: 'M-130', user: 'Nguyễn Văn A', isDeleted: true, dept: 'Hàng đền bù', zone: 'Khu C' },
  { id: 105, regCode: '5041', room: '407', time: '15-06-2026 11:20', isFree: false, price: 150000, payCode: 'PAY-105', manualCode: '', user: 'Trần Thị B', isDeleted: false, dept: 'Hàng đền bù', zone: 'Khu B' }
])

const filterState = ref({
  regCode: '',
  room: '',
  status: 'all', // 'all', 'paid', 'unpaid', 'deleted'
  dept: 'all',   // 'all', 'Minibar', 'Giặt ủi', 'Hàng đền bù'
  user: 'all',   // 'all', 'Nguyễn Văn A', 'Trần Thị B', 'Lê Văn C'
  zone: 'all'    // 'all', 'Khu A', 'Khu B', 'Khu C'
})

const hasLoadedData = ref(false)
const isSearching = ref(false)

const triggerSearch = () => {
  isSearching.value = true
  setTimeout(() => {
    isSearching.value = false
    hasLoadedData.value = true
  }, 650)
}

onMounted(() => {
  if (route.query.triggerSearch === 'true') {
    triggerSearch()
  }
})

watch(() => route.query.triggerSearch, (newVal) => {
  if (newVal === 'true') {
    triggerSearch()
  }
})

watch(filterState, () => {
  triggerSearch()
}, { deep: true })

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
  
  const getFirstDayOfMonth = (date) => {
    return new Date(date.getFullYear(), date.getMonth(), 1)
  }
  
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
      return
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

// Close popovers on click outside
const closeClickOutside = (e) => {
  if (showDropdown.value && dropdownWrapper.value && !dropdownWrapper.value.contains(e.target)) {
    showDropdown.value = false
  }
  if (showDatePicker.value && datePickerWrapper.value && !datePickerWrapper.value.contains(e.target)) {
    showDatePicker.value = false
  }
}

// Modal Logic
const showAddModal = ref(false)

const parseInvoiceDate = (dateStr) => {
  const [dPart] = dateStr.split(' ')
  const [d, m, y] = dPart.split('-')
  return new Date(y, m - 1, d)
}

// Invoices computation
const filteredInvoices = computed(() => {
  return invoicesList.value.filter(inv => {
    // 1. Time range filter
    const invDate = parseInvoiceDate(inv.time)
    const start = new Date(fromDate.value)
    const end = new Date(toDate.value)
    
    // Reset hours for date comparison
    invDate.setHours(0,0,0,0)
    start.setHours(0,0,0,0)
    end.setHours(0,0,0,0)
    
    if (invDate < start || invDate > end) return false

    // 2. RegCode filter
    if (filterState.value.regCode && !inv.regCode.toLowerCase().includes(filterState.value.regCode.toLowerCase())) return false

    // 3. Room filter
    if (filterState.value.room && inv.room !== filterState.value.room) return false

    // 4. Dept filter
    if (filterState.value.dept !== 'all' && inv.dept !== filterState.value.dept) return false

    // 5. User filter
    if (filterState.value.user !== 'all' && inv.user !== filterState.value.user) return false

    // 6. Zone filter
    if (filterState.value.zone !== 'all' && inv.zone !== filterState.value.zone) return false

    // 7. Status filter
    if (filterState.value.status !== 'all') {
      if (filterState.value.status === 'deleted') {
        return inv.isDeleted
      }
      if (inv.isDeleted) return false // hide deleted if filtering by paid/unpaid
      
      if (filterState.value.status === 'paid') {
        return inv.payCode !== ''
      }
      if (filterState.value.status === 'unpaid') {
        return inv.payCode === ''
      }
    }

    return true
  })
})

const getDeptClass = (dept) => {
  if (dept === 'Minibar') return 'bg-amber-100 text-amber-800 border border-amber-205'
  if (dept === 'Giặt ủi') return 'bg-blue-100 text-blue-800 border border-blue-205'
  return 'bg-rose-100 text-rose-800 border border-rose-205'
}

const formatCurrency = (value) => {
  return new Intl.NumberFormat('vi-VN').format(value)
}

onMounted(() => {
  document.addEventListener('click', closeClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('click', closeClickOutside)
})
</script>

<style scoped>
/* Scoped custom variables and transitions */
.btn-primary {
  background: var(--hk-gradient, linear-gradient(135deg, #97D5FF, #6BC1F5));
  color: #0f172a;
  transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  border: none;
}
.btn-primary:hover {
  transform: translateY(-1px) scale(1.02);
  box-shadow: 0 4px 12px rgba(151, 213, 255, 0.4);
  filter: brightness(1.03);
}
.btn-primary:active {
  transform: translateY(0);
}

/* Modal transition */
.hk-modal-enter-active {
  animation: hkModalIn 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
.hk-modal-leave-active {
  animation: hkModalOut 0.2s ease-in forwards;
}
@keyframes hkModalIn {
  from {
    opacity: 0;
    transform: scale(0.97) translateY(10px);
  }
  to {
    opacity: 1;
    transform: scale(1) translateY(0);
  }
}
@keyframes hkModalOut {
  from {
    opacity: 1;
    transform: scale(1) translateY(0);
  }
  to {
    opacity: 0;
    transform: scale(0.97) translateY(10px);
  }
}

/* Dropdown transition */
.hk-dropdown-enter-active {
  animation: hkDropIn 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}
.hk-dropdown-leave-active {
  animation: hkDropIn 0.15s cubic-bezier(0.16, 1, 0.3, 1) reverse;
}
@keyframes hkDropIn {
  from {
    opacity: 0;
    transform: translateY(-8px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}
</style>
