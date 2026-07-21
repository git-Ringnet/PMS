<template>
  <div 
    v-if="show" 
    class="fixed inset-0 bg-gray-900/40 backdrop-blur-xs flex justify-center items-start pt-16 z-[99999]"
    @click="close"
  >
    <div 
      class="bg-white rounded-2xl shadow-2xl w-full max-w-4xl flex flex-col overflow-visible border border-slate-200"
      @click.stop
    >
      <!-- DÒNG 1: INPUT SEARCH HEADER -->
      <div class="flex items-center px-5 py-3 border-b border-slate-100 rounded-t-2xl bg-white relative">
        <i class="fa-solid fa-magnifying-glass text-slate-400 text-sm mr-3"></i>
        <input 
          type="text" 
          id="gsInput"
          v-model="globalSearchQuery" 
          placeholder="Tìm kiếm" 
          class="flex-1 text-sm text-slate-800 placeholder-slate-400 outline-none bg-transparent font-medium" 
          autofocus
        />
        <!-- NÚT XÓA TỪ KHÓA TÌM KIẾM -->
        <button
          v-if="globalSearchQuery"
          @click="clearQuery"
          class="text-slate-300 hover:text-slate-500 mr-3 border-none bg-transparent cursor-pointer p-1 text-sm leading-none transition"
          title="Xóa từ khóa"
        >
          <i class="fa-solid fa-circle-xmark"></i>
        </button>
        <button 
          @click="close" 
          class="text-slate-400 hover:text-slate-700 transition border-none bg-transparent cursor-pointer p-1 rounded-md text-base leading-none"
          title="Đóng"
        >
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>

      <!-- DÒNG 2: FILTER TOOLBAR (XEM THEO NGÀY ĐẾN + KHOẢNG NGÀY + TÌNH TRẠNG) -->
      <div class="flex items-center justify-between px-5 py-2.5 bg-slate-50/50 border-b border-slate-100 text-xs select-none">
        <!-- BÊN TRÁI: TOGGLE XEM THEO NGÀY ĐẾN & KHOẢNG NGÀY -->
        <div class="flex items-center space-x-3">
          <!-- TOGGLE SWITCH XEM THEO NGÀY ĐẾN -->
          <label class="relative inline-flex items-center cursor-pointer select-none gap-2">
            <input 
              type="checkbox" 
              v-model="filterByArrivalDate" 
              class="sr-only peer"
              @change="handleFilterByArrivalDateChange"
            />
            <div class="w-8 h-4 bg-slate-300 rounded-full peer peer-checked:bg-sky-500 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:after:translate-x-4 shadow-2xs"></div>
            <span class="text-xs font-semibold text-sky-600">Xem theo ngày đến</span>
          </label>

          <!-- KHOẢNG NGÀY (CHỈ HIỂN THỊ KHI BẬT) -->
          <div v-if="filterByArrivalDate" class="flex items-center space-x-1.5 border border-slate-300 rounded-md px-2.5 py-1 bg-white shadow-2xs">
            <input 
              type="text" 
              v-model="displayFromDate" 
              @change="onDisplayFromDateBlur"
              placeholder="dd/mm/yyyy"
              class="w-24 text-center font-medium text-xs text-slate-800 outline-none bg-transparent"
            />
            <i class="fa-regular fa-calendar text-slate-400 text-[11px]"></i>
            <span class="text-slate-400 px-1">-</span>
            <input 
              type="text" 
              v-model="displayToDate" 
              @change="onDisplayToDateBlur"
              placeholder="dd/mm/yyyy"
              class="w-24 text-center font-medium text-xs text-slate-800 outline-none bg-transparent"
            />
            <i class="fa-regular fa-calendar text-slate-400 text-[11px]"></i>
          </div>
        </div>

        <!-- BÊN PHẢI: DROPDOWN TÌNH TRẠNG ĐĂNG KÝ (STATUS 0, 1, 2, 3, 4) -->
        <div class="relative">
          <button 
            type="button" 
            @click="isStatusDropdownOpen = !isStatusDropdownOpen"
            class="flex items-center space-x-1.5 px-3 py-1 bg-white hover:bg-slate-50 border border-slate-300 rounded-md cursor-pointer transition text-left shadow-2xs font-semibold text-slate-700"
          >
            <span>Tình trạng:</span>
            <span class="text-sky-600 font-bold max-w-[120px] truncate">{{ searchStatusLabel }}</span>
            <i class="fa-solid fa-chevron-down text-[9px] text-slate-400 ml-1"></i>
          </button>
          
          <!-- POPUP DROPDOWN (ẢNH 2 MATCH) -->
          <div 
            v-if="isStatusDropdownOpen" 
            class="absolute right-0 top-full mt-1 bg-white border border-slate-200 rounded-xl shadow-xl w-60 p-3 z-[1000] space-y-2 select-none animate-in"
          >
            <!-- TOP BAR POPUP -->
            <div class="flex items-center justify-between border-b border-slate-100 pb-2">
              <label class="relative inline-flex items-center cursor-pointer select-none gap-2">
                <input 
                  type="checkbox" 
                  v-model="isAllStatusesChecked" 
                  class="sr-only peer"
                />
                <div class="w-7 h-3.5 bg-slate-300 rounded-full peer peer-checked:bg-sky-500 after:content-[''] after:absolute after:top-[1.5px] after:left-[1.5px] after:bg-white after:rounded-full after:h-2.5 after:w-2.5 after:transition-all peer-checked:after:translate-x-3.5 shadow-2xs"></div>
                <span class="text-xs font-bold text-slate-700">Tất cả ĐK</span>
              </label>
              <span class="text-[11px] font-semibold text-slate-500 bg-slate-100 px-2 py-0.5 rounded">
                Chọn: {{ tempSelectedStatuses.length }}
              </span>
            </div>

            <!-- DANH SÁCH CHECKBOX TÌNH TRẠNG LƯU TRÚ -->
            <div class="max-h-52 overflow-y-auto space-y-1.5 py-1">
              <label 
                v-for="st in statusOptions" 
                :key="st.value" 
                class="flex items-center space-x-2.5 p-1.5 hover:bg-slate-50 rounded-lg cursor-pointer transition font-medium text-xs text-slate-700"
              >
                <input 
                  type="checkbox" 
                  :value="st.value" 
                  v-model="tempSelectedStatuses" 
                  class="rounded border-slate-300 text-sky-600 focus:ring-sky-500 w-4 h-4 cursor-pointer" 
                />
                <span class="font-semibold" :class="st.textColor">{{ st.label }}</span>
              </label>
            </div>

            <!-- FOOTER NÚT LƯU POPUP -->
            <div class="pt-2 border-t border-slate-100 flex justify-end">
              <button 
                type="button"
                @click="applyStatusFilter"
                class="bg-[#72c0e5] hover:bg-[#5bb2dc] text-white font-bold text-xs px-4 py-1.5 rounded-lg border-none cursor-pointer shadow-xs transition"
              >
                Lưu
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- BẢNG HỂN THỊ KẾT QUẢ TÌM KIẾM -->
      <div class="max-h-[380px] overflow-y-auto rounded-b-2xl">
        <div v-if="globalSearchResults.length > 0">
          <table class="w-full text-left text-xs border-collapse">
            <thead>
              <tr class="bg-slate-50 border-b border-slate-200 text-slate-500 font-extrabold uppercase text-[10px] tracking-wider h-9">
                <th class="p-2.5 pl-5 w-24">MÃ ĐK</th>
                <th class="p-2.5">TÊN ĐĂNG KÝ</th>
                <th class="p-2.5 w-32 text-center">MÃ THAM CHIẾU</th>
                <th class="p-2.5 w-28 text-center">NGÀY ĐẾN</th>
                <th class="p-2.5 w-32 text-center pr-5">TRẠNG THÁI</th>
              </tr>
            </thead>
            <tbody>
              <tr 
                v-for="b in globalSearchResults" 
                :key="b.id"
                class="border-b border-slate-100 hover:bg-sky-50/50 cursor-pointer font-medium text-slate-700 h-12 transition align-middle"
                @click="selectBooking(b)"
              >
                <!-- MÃ ĐK -->
                <td class="p-2.5 pl-5 font-bold font-mono text-sky-600">
                  {{ b.booking_code || b.id }}
                </td>

                <!-- TÊN ĐĂNG KÝ -->
                <td class="p-2.5">
                  <div class="font-bold text-slate-800 text-xs">
                    {{ b.booking_name }}
                  </div>
                  <div class="text-[11px] text-slate-400 font-normal">
                    {{ b.company?.name || b.contact_name || 'Khách lẻ' }}
                  </div>
                </td>

                <!-- MÃ THAM CHIẾU -->
                <td class="p-2.5 text-center text-slate-400 font-mono text-xs">
                  {{ b.external_booking_code || '-' }}
                </td>

                <!-- NGÀY ĐẾN -->
                <td class="p-2.5 text-center font-semibold text-slate-700 text-xs">
                  {{ formatDateDisplay(b.arrival_date || b.check_in) }}
                </td>

                <!-- TRẠNG THÁI (LẤY THEO STATUS 0,1,2,3,4) -->
                <td class="p-2.5 text-center pr-5">
                  <span 
                    class="px-2.5 py-1 rounded-md text-[11px] font-bold inline-block border shadow-2xs"
                    :class="getStatusBadgeStyle(b.status)"
                  >
                    {{ getStatusText(b.status) }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- EMPTY STATE -->
        <div v-else class="px-5 py-10 text-xs text-slate-400 text-center bg-white">
          <div v-if="isLoading" class="flex items-center justify-center space-x-2 text-sky-600">
            <i class="fa-solid fa-circle-notch animate-spin text-base"></i>
            <span class="font-bold">Đang tải danh sách Đăng ký...</span>
          </div>
          <div v-else>
            Không tìm thấy đăng ký nào phù hợp.
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { fetchBookings } from '@/services/booking-service'

const props = defineProps({
  show: Boolean,
  registrationStatuses: Array,
  activeTab: Object,
  systemDate: String
})

const emit = defineEmits(['update:show', 'select-booking'])

const globalSearchQuery = ref('')
const globalSearchResults = ref([])
const filterByArrivalDate = ref(false)
const isLoading = ref(false)

const searchFromDate = ref('')
const searchToDate = ref('')
const displayFromDate = ref('')
const displayToDate = ref('')

// Các tùy chọn status lưu trú (0, 1, 2, 3, 4)
const statusOptions = [
  { value: 0, label: 'Đặt phòng', textColor: 'text-emerald-600' },
  { value: 1, label: 'Đang ở', textColor: 'text-blue-600' },
  { value: 2, label: 'Đã trả phòng', textColor: 'text-slate-600' },
  { value: 3, label: 'Đã hủy', textColor: 'text-red-600' },
  { value: 4, label: 'No Show', textColor: 'text-amber-600' }
]

const selectedStatuses = ref([0, 1, 2, 3, 4])
const tempSelectedStatuses = ref([0, 1, 2, 3, 4])
const isStatusDropdownOpen = ref(false)

const searchStatusLabel = computed(() => {
  if (selectedStatuses.value.length === 0) return 'Không chọn'
  if (selectedStatuses.value.length === statusOptions.length) return 'Tất cả'
  if (selectedStatuses.value.length === 1) {
    const s = statusOptions.find(o => o.value === selectedStatuses.value[0])
    return s ? s.label : '1 chọn'
  }
  return `Chọn: ${selectedStatuses.value.length}`
})

const isAllStatusesChecked = computed({
  get() {
    return tempSelectedStatuses.value.length === statusOptions.length
  },
  set(val) {
    if (val) {
      tempSelectedStatuses.value = statusOptions.map(o => o.value)
    } else {
      tempSelectedStatuses.value = []
    }
  }
})

function getStatusText(status) {
  const st = Number(status)
  switch (st) {
    case 0: return 'Đặt phòng'
    case 1: return 'Đang ở'
    case 2: return 'Đã trả phòng'
    case 3: return 'Đã hủy'
    case 4: return 'No Show'
    default: return 'Khác'
  }
}

function getStatusBadgeStyle(status) {
  const st = Number(status)
  switch (st) {
    case 0: return 'bg-emerald-50 text-emerald-600 border-emerald-200'
    case 1: return 'bg-blue-50 text-blue-600 border-blue-200'
    case 2: return 'bg-slate-100 text-slate-600 border-slate-200'
    case 3: return 'bg-red-50 text-red-600 border-red-200'
    case 4: return 'bg-amber-50 text-amber-600 border-amber-200'
    default: return 'bg-slate-50 text-slate-500 border-slate-200'
  }
}

function formatDateDisplay(dateStr) {
  if (!dateStr) return '-'
  if (dateStr.includes('/')) return dateStr
  const parts = dateStr.split('-')
  if (parts.length === 3) return `${parts[2]}/${parts[1]}/${parts[0]}`
  return dateStr
}

function parseInputDate(displayStr) {
  if (!displayStr) return ''
  const parts = displayStr.trim().split('/')
  if (parts.length === 3) return `${parts[2]}-${parts[1].padStart(2, '0')}-${parts[0].padStart(2, '0')}`
  return displayStr
}

function onDisplayFromDateBlur() {
  searchFromDate.value = parseInputDate(displayFromDate.value)
  executeGlobalSearch()
}

function onDisplayToDateBlur() {
  searchToDate.value = parseInputDate(displayToDate.value)
  executeGlobalSearch()
}

function handleFilterByArrivalDateChange() {
  if (filterByArrivalDate.value) {
    const sysDate = props.systemDate || new Date().toISOString().split('T')[0]
    searchFromDate.value = sysDate
    searchToDate.value = sysDate
    displayFromDate.value = formatDateDisplay(sysDate)
    displayToDate.value = formatDateDisplay(sysDate)
  }
  executeGlobalSearch()
}

function applyStatusFilter() {
  selectedStatuses.value = [...tempSelectedStatuses.value]
  isStatusDropdownOpen.value = false
  executeGlobalSearch()
}

watch(() => props.show, (newVal) => {
  if (newVal) {
    globalSearchQuery.value = ''
    filterByArrivalDate.value = false
    isStatusDropdownOpen.value = false
    selectedStatuses.value = [0, 1, 2, 3, 4]
    tempSelectedStatuses.value = [0, 1, 2, 3, 4]

    const sysDate = props.systemDate || new Date().toISOString().split('T')[0]
    searchFromDate.value = sysDate
    searchToDate.value = sysDate
    displayFromDate.value = formatDateDisplay(sysDate)
    displayToDate.value = formatDateDisplay(sysDate)

    executeGlobalSearch()

    setTimeout(() => {
      const el = document.getElementById('gsInput')
      if (el) el.focus()
    }, 50)
  }
})

async function executeGlobalSearch() {
  isLoading.value = true
  const params = {}
  
  if (globalSearchQuery.value.trim().length > 0) {
    params.search = globalSearchQuery.value.trim()
  }
  
  if (filterByArrivalDate.value) {
    if (searchFromDate.value) params.from_date = searchFromDate.value
    if (searchToDate.value) params.to_date = searchToDate.value
  }

  // Nếu người dùng bỏ chọn tất cả status
  if (selectedStatuses.value.length === 0) {
    globalSearchResults.value = []
    isLoading.value = false
    return
  }

  // Nếu chọn một số status hoặc chọn tất cả
  if (selectedStatuses.value.length > 0) {
    params.status = selectedStatuses.value.join(',')
  }
  
  try {
    const res = await fetchBookings(params)
    let list = res.data?.data || res.data || []
    
    // Sắp xếp mã ĐK (id) giảm dần
    list.sort((a, b) => Number(b.id) - Number(a.id))
    globalSearchResults.value = list
  } catch (err) {
    console.error('Lỗi tìm kiếm booking:', err)
  } finally {
    isLoading.value = false
  }
}

watch(globalSearchQuery, () => {
  executeGlobalSearch()
})

function clearQuery() {
  globalSearchQuery.value = ''
  const el = document.getElementById('gsInput')
  if (el) el.focus()
}

function close() {
  emit('update:show', false)
}

function selectBooking(booking) {
  emit('select-booking', booking)
  close()
}
</script>
